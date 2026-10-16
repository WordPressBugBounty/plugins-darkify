/**
 * Darkify — admin dark mode, Dark Reader engine.
 *
 * This replaces the per-element classifier (client_main.js) for wp-admin only.
 * The frontend is untouched and still runs that engine.
 *
 * Why the swap: the classifier walks every element, reads getComputedStyle on
 * each (plus ::before/::after), and re-runs on DOM mutations. wp-admin — the
 * block editor above all — mutates continuously, and React rewrites classes on
 * nodes the engine has stamped, so the walk never settles. Past a few hundred
 * elements the editor stops responding.
 *
 * Dark Reader works at the stylesheet level instead: it parses the sheets once,
 * emits an inverted shadow stylesheet, and watches for *new stylesheets* rather
 * than element churn. Typing in the editor costs it nothing, so the failure
 * mode is structurally absent rather than tuned away.
 *
 * What this file owns:
 *   - the on/off state for the admin (localStorage + the plugin's option)
 *   - the palette -> Dark Reader theme mapping
 *   - the same-origin editor iframes (block editor canvas, classic TinyMCE),
 *     which Dark Reader's page-level API does not reach on its own
 *
 * Integration contract: `darkify_switch_trigger()` and `darkify_theme_select()`
 * must stay on `window` under exactly those names. The admin-bar node is
 * registered with an inline `onclick="darkify_switch_trigger()"` (see
 * Admin::darkify_admin_bar_switch), and the React settings screen calls
 * `darkify_theme_select`. Renaming either breaks the toggle silently.
 */
(function () {
  "use strict";

  if (typeof DarkReader === "undefined") {
    return;
  }

  var HTML = document.documentElement;
  var DARK_CLASS = "darkify_dark_mode_enabled";
  var STATE_KEY = "darkify_admin_panel_last_state";
  /*
   * The editor's palette keeps the existing key, so the toolbar dropdown a user
   * has already set carries over. The rest of wp-admin gets its own, because the
   * two are independent choices.
   */
  var THEME_KEY = "darkify_selected_theme";
  var ADMIN_THEME_KEY = "darkify_admin_palette";

  /** Where to fetch the library from inside an iframe realm. Set by PHP. */
  var LIB_SRC =
    typeof window.darkifyDarkReaderSrc === "string"
      ? window.darkifyDarkReaderSrc
      : "";

  /* ---------------------------------------------------------------------- */
  /* Palettes                                                               */
  /* ---------------------------------------------------------------------- */

  /*
   * Copied from client_main.js's darkify_apply_palette() so the admin shows the
   * same named presets the frontend does. Only the fields Dark Reader can act
   * on are carried across: it derives every other colour itself, which is the
   * whole reason it handles unknown markup the classifier could not.
   *
   * The button/placeholder entries the frontend palette also carries have no
   * Dark Reader equivalent and are deliberately dropped rather than faked.
   */
  var PALETTES = {
    set1: { bg: "#0F0F0F", secondary_bg: "#171717", text_color: "#BEBEBE", link_color: "#E7E7E7", link_hover_color: "#BEBEBE", border_color: "#4A4A4A", input_bg: "#2D2D2D", input_text_color: "#BEBEBE", btn_bg: "#4A4A4A", btn_text_color: "#BEBEBE" },
    set3: { bg: "#211e3c", secondary_bg: "#302C57", text_color: "#B1BBD8", link_color: "#8071fb", link_hover_color: "#B1BBD8", border_color: "#4E478D", input_bg: "#2A264D", input_text_color: "#B1BBD8", btn_bg: "#4E478D", btn_text_color: "#B1BBD8" },
    set6: { bg: "#082032", secondary_bg: "#061825", text_color: "#B5D9F3", link_color: "#61bbff", link_hover_color: "#B5D9F3", border_color: "#144E78", input_bg: "#0E3755", input_text_color: "#B5D9F3", btn_bg: "#144E78", btn_text_color: "#B5D9F3" },
    set9: { bg: "#04261d", secondary_bg: "#021e16", text_color: "#C1D2BB", link_color: "#00d29a", link_hover_color: "#C1D2BB", border_color: "#095541", input_bg: "#073d2f", input_text_color: "#C1D2BB", btn_bg: "#095541", btn_text_color: "#C1D2BB" },
    set10: { bg: "#171004", secondary_bg: "#211706", text_color: "#E0D2BD", link_color: "#e09525", link_hover_color: "#E0D2BD", border_color: "#5D4010", input_bg: "#372911", input_text_color: "#E0D2BD", btn_bg: "#5D4010", btn_text_color: "#E0D2BD" },
  };


  /**
   * Which palette applies here, or "auto" for Dark Reader's own derivation.
   *
   * "auto" is the default, and it is the better default. Forcing
   * darkSchemeBackgroundColor/darkSchemeTextColor overrides the per-colour
   * inversion that is the entire reason Dark Reader handles markup this plugin
   * has never seen. Pinning two colours flattens that into "everything is this
   * grey", and any theme whose surfaces carry meaning through colour loses it.
   * A palette is now something a user opts into, not something they get.
   *
   * The block editor keeps its own choice, independent of the rest of wp-admin:
   * the editor is where a writer is looking at their content and may well want a
   * different surface than the one they want on the plugins list.
   */
  function isEditorContext() {
    return !!(
      document.body &&
      (document.body.classList.contains("block-editor-page") ||
        document.querySelector('iframe[name="editor-canvas"]'))
    );
  }

  function paletteKey() {
    return isEditorContext() ? THEME_KEY : ADMIN_THEME_KEY;
  }

  /**
   * The site-wide default for this context, from the plugin's settings.
   *
   * Absent or unrecognised means "auto", which keeps installs that predate these
   * two fields on Dark Reader's derivation rather than silently pinning them to
   * a preset they never chose.
   */
  function paletteDefault() {
    var value = isEditorContext()
      ? window.darkify_editor_palette_default
      : window.darkify_admin_palette_default;
    return PALETTES[value] ? value : "auto";
  }

  /**
   * The palette in force: a per-user choice if one exists, otherwise the site
   * default. The editor's toolbar dropdown writes the per-user value, and it
   * winning over the setting is the relationship that control has always had.
   */
  /*
   * Unsaved values from the settings screen, so the two palette pickers show
   * their effect while you are choosing rather than only after a save and a
   * reload. Outranks both the per-user choice and the saved default for as long
   * as the screen holds them; cleared when it stops previewing.
   */
  var previewOverrides = null;

  function isPaletteValue(value) {
    return value === "auto" || !!PALETTES[value];
  }

  function currentPaletteName() {
    var context = isEditorContext() ? "editor" : "admin";

    if (previewOverrides && isPaletteValue(previewOverrides[context])) {
      return previewOverrides[context];
    }

    var stored;
    try {
      stored = localStorage.getItem(paletteKey());
    } catch (e) {
      stored = null;
    }

    if (isPaletteValue(stored)) {
      return stored;
    }

    return paletteDefault();
  }

  /** The palette record, or null when running on Dark Reader's own colours. */
  function currentPalette() {
    var name = currentPaletteName();
    return name === "auto" ? null : PALETTES[name];
  }

  /* ---------------------------------------------------------------------- */
  /* Theme + fixes                                                          */
  /* ---------------------------------------------------------------------- */

  function buildTheme() {
    var theme = {
      mode: 1,
      brightness: 100,
      contrast: 100,
      grayscale: 0,
      sepia: 0,
      selectionColor: "auto",
      // wp-admin's form controls are styled by WordPress, not by the UA. Letting
      // Dark Reader restyle system controls on top of that double-darkens them.
      styleSystemControls: false,
    };

    var p = currentPalette();
    if (p) {
      theme.darkSchemeBackgroundColor = p.bg;
      theme.darkSchemeTextColor = p.text_color;
      theme.scrollbarColor = p.secondary_bg;
    }

    return theme;
  }

  function buildFixes() {
    var p = currentPalette();

    /*
     * The two-tone page.
     *
     * wp-admin paints `body { background: #f0f0f1 }` and leaves <html> unpainted.
     * Dark Reader derives body's dark colour *from* #f0f0f1, but paints <html>
     * with darkSchemeBackgroundColor — a different value. Whenever the content is
     * shorter than the viewport, <html> shows below <body> and the join is
     * visible as a horizontal seam, usually right around 100vh. (The same seam
     * appears in the Dark Reader extension and in other plugins built on it; it
     * is inherent to theming those two elements independently, not to this
     * integration.)
     *
     * `${...}` hands the colour to Dark Reader's own processing, so <html> is
     * asked for the same derivation <body> gets. This alone does NOT settle it —
     * Dark Reader's own root rule is `!important` as well and outranks this one —
     * so syncRootBackground() pins the real value inline after the fact. This
     * rule stays as the pre-paint approximation, narrowing the seam before that
     * measurement can happen; do not remove it, and do not assume it is
     * sufficient on its own.
     *
     * Matching the colours is the fix either way; stretching #wpwrap to full
     * height only moves the seam somewhere less obvious.
     */
    var css =
      "html { background-color: ${#f0f0f1} !important; }\n" +
      // The login and about screens paint a different base colour.
      "body.login, body.about-php { background-color: ${#f0f0f1} !important; }\n";

    /*
     * Only a chosen palette asserts a link colour. On "auto", Dark Reader's
     * derived link colour is the correct one — it is computed from the link's
     * own original colour, so a theme that colours its links deliberately keeps
     * that distinction instead of having it overwritten.
     */
    if (p) {
      /*
       * Specificity, not just !important.
       *
       * Dark Reader rewrites every stylesheet rule it darkens and marks the
       * result `!important`, so a plain `a { ... !important }` no longer beats
       * core's own link rules — it only ties with them, and the more specific
       * one wins. `div.notice a, div.error a, div.updated a` (wp-admin
       * common.css, specificity 0,1,2) is exactly that case: list-table and
       * row-action links took the palette colour while every link inside an
       * admin notice stayed WordPress blue.
       *
       * `html body a` plus the :not() chain lands at 0,5,3 — above core's
       * class-based link rules, and still below anything anchored to an id, so
       * #adminmenu and #wpadminbar keep Dark Reader's derived colours (a bright
       * palette link colour on the menu would be far too loud).
       *
       * The exclusions are the buttons: they carry their own fill, and their
       * label has to stay readable against it rather than become a link colour.
       */
      var notButton =
        ":not(.button)" +
        ":not(.button-primary)" +
        ":not(.button-secondary)" +
        ":not(.button-link)" +
        ":not(.page-title-action)";

      css +=
        "html body a" + notButton + ", html body a" + notButton + ":visited" +
        " { color: " + p.link_color + " !important; }\n" +
        "html body a" + notButton + ":hover, html body a" + notButton + ":focus" +
        " { color: " + p.link_hover_color + " !important; }\n";
    }

    return {
      invert: [],

      css: css,

      /*
       * Inline styles that carry meaning rather than design. A colour picker's
       * swatch *is* the value it represents — inverting it makes the control
       * lie about what it will apply. Same for the block editor's palette
       * circles and the theme/pattern previews, which are showing the user the
       * light-mode design on purpose.
       */
      ignoreInlineStyle: [
        ".darkify_switch",
        ".darkify_ignore",
        ".wp-picker-container",
        ".wp-color-result",
        ".color-option",
        ".components-color-picker",
        ".components-circular-option-picker__option",
        ".components-palette-edit__colors",
        ".block-editor-color-gradient-control",
        ".block-editor-block-preview__container",
        ".block-editor-block-preview__content",
        ".block-editor-patterns__list",
        ".editor-styles-wrapper",
      ],

      /*
       * Image analysis fetches and samples every image to decide whether to
       * invert it. In wp-admin that means the whole media library, and a
       * screenshot or a logo inverted "helpfully" is simply wrong. Off wholesale.
       */
      ignoreImageAnalysis: ["*"],

      disableStyleSheetsProxy: false,
      ignoreCSSUrl: [],
    };
  }

  /* ---------------------------------------------------------------------- */
  /* State                                                                  */
  /* ---------------------------------------------------------------------- */

  /**
   * Whether Admin Panel Dark Mode is switched on in the plugin's settings.
   *
   * Darkify's own settings screens load this engine even while the option is
   * off, so the admin-bar icon can appear the moment it is switched on without
   * a reload. A stale remembered state must not darken those screens. An absent
   * flag counts as enabled, which keeps installs that have not re-saved since
   * the flag was introduced behaving as they did.
   */
  /**
   * Darkify's own React settings screens ship a complete dark theme of their own
   * (`.dark { --background: oklch(...) }` in darkify-react/src/index.css),
   * mirrored onto <html> by adminDarkMode.js and set pre-paint by PHP. It styles
   * the surrounding wp-admin chrome too (`.dark #wpcontent`).
   *
   * Running Dark Reader over that darkens an already-dark app twice, and Dark
   * Reader 4.9 does not parse `oklch()`, so the unresolved custom properties
   * settle on neighbouring token values — which is why card borders and toggle
   * backgrounds came out red (`--destructive`). The app themes itself; the
   * engine's only job on these screens is to keep the toggle working.
   */
  function isSelfThemedScreen() {
    return window.darkifyAdminSelfThemed === true;
  }

  function optionEnabled() {
    return (
      typeof window.darkify_admin_panel_dark_enabled === "undefined" ||
      window.darkify_admin_panel_dark_enabled === "1"
    );
  }

  function readState() {
    try {
      return localStorage.getItem(STATE_KEY) === "1";
    } catch (e) {
      return false;
    }
  }

  function writeState(on) {
    try {
      localStorage.setItem(STATE_KEY, on ? "1" : "0");
    } catch (e) {
      // Private mode / blocked storage: the toggle still works for this page
      // load, it just will not be remembered.
    }
  }

  function isDark() {
    return HTML.classList.contains(DARK_CLASS);
  }

  /* ---------------------------------------------------------------------- */
  /* Iframes                                                                */
  /* ---------------------------------------------------------------------- */

  /*
   * The block editor canvas (WP 6.3+) and the classic editor's TinyMCE body are
   * separate documents. Dark Reader's page-level API binds the realm it was
   * loaded into, so each same-origin frame needs its own copy loaded inside it
   * and enabled there. Cross-origin frames are unreachable and left alone.
   */
  var IFRAME_SELECTOR =
    'iframe[name="editor-canvas"], iframe#content_ifr, .mce-container-body iframe';

  /** The classic editor's own toggle state, written by admin-classic-editor.js. */
  var CLASSIC_MODE_KEY = "darkify_classic_editor_mode";

  function isClassicFrame(iframe) {
    return (
      iframe.id === "content_ifr" ||
      !!(iframe.closest && iframe.closest(".mce-container-body"))
    );
  }

  /**
   * Whether the classic editor's content area should be dark.
   *
   * The TinyMCE toolbar carries its own moon/sun button with its own remembered
   * state, so unlike the block editor canvas this frame is not simply "whatever
   * the admin is". Honouring only the page state made that button do nothing:
   * it removed the legacy stylesheet it manages while Dark Reader carried on
   * painting the frame dark underneath.
   *
   * With no remembered choice the frame follows the admin, which is what someone
   * who has never touched the button expects.
   */
  function classicWantsDark(pageDark) {
    var stored;
    try {
      stored = localStorage.getItem(CLASSIC_MODE_KEY);
    } catch (e) {
      stored = null;
    }

    if (stored === "1") return true;
    if (stored === "0") return false;
    return pageDark;
  }

  /**
   * Put the Dark Reader bundle into a same-origin frame without enabling it.
   *
   * Idempotent: the script element's id is the guard, so repeated calls (every
   * toggle, every canvas re-scan) load it once. Enabling is deliberately not
   * done here — this only makes `win.DarkReader` exist so a later enable() is
   * the sole cost.
   */
  function ensureLibraryInFrame(doc) {
    if (!LIB_SRC || !doc || doc.getElementById("darkify-darkreader-lib")) {
      return;
    }
    var win = doc.defaultView;
    if (!win || win.DarkReader) {
      return;
    }

    var script = doc.createElement("script");
    script.id = "darkify-darkreader-lib";
    script.src = LIB_SRC;
    (doc.head || doc.documentElement).appendChild(script);
  }

  function applyToIframe(iframe, enabled) {
    if (isClassicFrame(iframe)) {
      /*
       * Replaces the page state rather than narrowing it. The classic editor's
       * moon button darkens the content frame on its own terms — a light admin
       * with a dark writing area is a combination people deliberately choose,
       * and the legacy stylesheet this replaced supported it. Writing this as
       * `enabled && classicWantsDark(...)` made page-dark a precondition, so the
       * button did nothing whenever the admin was light.
       */
      enabled = classicWantsDark(enabled);
    }

    var doc;
    var win;
    try {
      doc = iframe.contentDocument;
      win = iframe.contentWindow;
    } catch (e) {
      return; // cross-origin
    }
    if (!doc || !doc.documentElement || !win) {
      return;
    }

    if (!enabled) {
      try {
        if (win.DarkReader && win.DarkReader.isEnabled()) {
          win.DarkReader.disable();
        }
      } catch (e) {
        // Frame navigated out from under us.
      }

      /*
       * Load the library into the frame anyway, while nothing is waiting on it.
       *
       * Turning dark on is inherently slower than turning it off — enable()
       * analyses every stylesheet and generates an inverted one, disable() only
       * tears down what already exists. That asymmetry belongs to Dark Reader
       * and cannot be removed here. What can be removed is the rest of the first
       * toggle's bill: without this, the first light->dark in the editor also
       * pays to fetch 106 KB into the canvas frame and parse it, before the
       * analysis has even started. Paying that during idle time after load means
       * the click only costs the part that is genuinely unavoidable.
       */
      ensureLibraryInFrame(doc);
      return;
    }

    if (win.DarkReader) {
      try {
        win.DarkReader.enable(buildTheme(), buildFixes());
      } catch (e) {
        // ignore
      }
      return;
    }

    if (!LIB_SRC || doc.getElementById("darkify-darkreader-lib")) {
      return; // no URL to load, or a load is already in flight
    }

    var script = doc.createElement("script");
    script.id = "darkify-darkreader-lib";
    script.src = LIB_SRC;
    script.onload = function () {
      try {
        win.DarkReader.setFetchMethod(win.fetch.bind(win));
        win.DarkReader.enable(buildTheme(), buildFixes());
      } catch (e) {
        // ignore
      }
    };
    (doc.head || doc.documentElement).appendChild(script);
  }

  function applyToAllIframes(enabled) {
    var frames = document.querySelectorAll(IFRAME_SELECTOR);
    for (var i = 0; i < frames.length; i++) {
      var frame = frames[i];
      // Re-apply after every navigation of the frame, bound once per element.
      if (!frame.dataset.darkifyDrBound) {
        frame.dataset.darkifyDrBound = "1";
        frame.addEventListener("load", function () {
          applyToIframe(this, isDark());
        });
      }
      applyToIframe(frame, enabled);
    }
  }

  /*
   * The canvas iframe is mounted asynchronously, well after this script runs,
   * and is replaced when the editor switches between visual and code view. One
   * cheap querySelector per frame is enough to notice; the rAF gate keeps the
   * editor's constant DOM churn from turning that into per-mutation work.
   */
  var scanScheduled = false;

  function scheduleIframeScan() {
    if (scanScheduled) {
      return;
    }
    scanScheduled = true;
    requestAnimationFrame(function () {
      scanScheduled = false;
      if (document.querySelector(IFRAME_SELECTOR)) {
        applyToAllIframes(isDark());
      }
    });
  }

  var frameWatcher = null;

  function watchForIframes() {
    // Reachable from both boot paths and from every toggle; a second observer
    // on the same body would double every scan for no benefit.
    if (frameWatcher || !document.body) {
      return;
    }
    frameWatcher = new MutationObserver(scheduleIframeScan);
    frameWatcher.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }

  /* ---------------------------------------------------------------------- */
  /* Apply                                                                  */
  /* ---------------------------------------------------------------------- */

  /*
   * Darkify's own screens are themed by their own design tokens, not by Dark
   * Reader, so a chosen palette has to reach them a different way: by writing
   * the palette's colours into those tokens directly.
   *
   * The map is deliberately partial. Four groups are left alone:
   *
   *   --destructive                     a delete button that stops being red
   *                                     stops communicating danger.
   *   --chart-*                         categorical colours; they have to stay
   *                                     distinguishable from each other.
   *
   * --primary and --sidebar-primary ARE mapped, and the pairing that made them
   * look risky is what makes them safe: they take `btn_bg` and `btn_text_color`,
   * which is the palette's own button fill and its text — a pair its author
   * already chose to be readable together. Taking both halves from that one pair
   * is a different thing from overwriting half of shadcn's. Without them the
   * switches, primary buttons and selected states kept a near-white default and
   * were the only parts of the screen a chosen palette never reached.
   */
  var SELF_THEMED_TOKENS = {
    "--background": "bg",
    "--card": "secondary_bg",
    "--popover": "secondary_bg",
    "--sidebar": "secondary_bg",
    "--secondary": "secondary_bg",
    "--muted": "secondary_bg",
    /*
     * Accent is the "one step lighter than the surface" slot: hovered menu
     * items, the selected sidebar entry. Handing it `secondary_bg` — the same
     * value --card/--popover/--sidebar already take — made the active nav item
     * disappear the moment a palette was chosen, while `auto` (no palette, Dark
     * Reader deriving each colour) kept it visible. `border_color` is the
     * palette's own next step up from its surfaces, which is the relation the
     * app's own dark tokens have (--sidebar 0.205 vs --sidebar-accent 0.269).
     */
    "--accent": "border_color",
    "--sidebar-accent": "border_color",
    "--foreground": "text_color",
    "--card-foreground": "text_color",
    "--popover-foreground": "text_color",
    "--secondary-foreground": "text_color",
    "--accent-foreground": "text_color",
    "--sidebar-foreground": "text_color",
    "--sidebar-accent-foreground": "text_color",
    "--muted-foreground": "input_text_color",
    "--border": "border_color",
    "--input": "border_color",
    "--sidebar-border": "border_color",
    "--ring": "link_color",
    "--primary": "btn_bg",
    "--primary-foreground": "btn_text_color",
    "--sidebar-primary": "btn_bg",
    "--sidebar-primary-foreground": "btn_text_color",
  };

  /**
   * Push the active palette into the self-themed app's tokens, or clear them.
   *
   * Cleared on "auto" and in light mode alike, which hands the app back to the
   * tokens its own stylesheet defines rather than leaving a half-applied
   * palette behind.
   */
  function applySelfThemedTokens() {
    var p = isDark() ? currentPalette() : null;

    for (var token in SELF_THEMED_TOKENS) {
      if (!Object.prototype.hasOwnProperty.call(SELF_THEMED_TOKENS, token)) {
        continue;
      }
      var value = p ? p[SELF_THEMED_TOKENS[token]] : null;

      /*
       * A missing field clears the token rather than writing it.
       *
       * setProperty() stringifies whatever it is handed, so a key this palette
       * does not carry became the literal text "undefined" — a custom property
       * that parses but can never resolve. Every `var()` reading it then failed,
       * and a failed var() takes its whole declaration with it: the switch track
       * did not fall back to a default colour, it lost its background entirely
       * and rendered transparent. Clearing instead lets the stylesheet's own
       * value stand, which is wrong-looking at worst rather than invisible.
       */
      if (value) {
        HTML.style.setProperty(token, value);
      } else {
        HTML.style.removeProperty(token);
      }
    }
  }

  /*
   * Erase the horizontal seam near the bottom of short admin pages.
   *
   * wp-admin paints `body { background: #f0f0f1 }` and leaves <html> unpainted.
   * Dark Reader derives body's colour from #f0f0f1 but paints <html> with
   * darkSchemeBackgroundColor — a different value. When the content is shorter
   * than the viewport, <html> shows below <body> and the join is visible. The
   * admin menu column ends at the same line, which is what makes the seam run
   * the full width of the page.
   *
   * Declaring `html { background-color: ${#f0f0f1} }` in fixes.css does not win:
   * Dark Reader's own generated rule for the root element is `!important` too,
   * and it is the one that applies. So rather than predicting the colour, read
   * back the one it actually produced for <body> and pin <html> to it inline —
   * an inline `!important` outranks any stylesheet, including its own.
   *
   * Runs twice on purpose. Dark Reader processes stylesheets as it finds them,
   * and a sheet that arrives late (an admin page loading its own CSS) can change
   * what body resolves to after the first read.
   */
  function syncRootBackground() {
    if (!document.body) {
      return;
    }

    var color = window.getComputedStyle(document.body).backgroundColor;

    // Transparent means body is not painting anything of its own, so there is no
    // second colour to reconcile and nothing to correct.
    if (!color || color === "transparent" || color === "rgba(0, 0, 0, 0)") {
      HTML.style.removeProperty("background-color");
      return;
    }

    HTML.style.setProperty("background-color", color, "important");
  }

  function scheduleRootBackgroundSync() {
    if (typeof requestAnimationFrame === "function") {
      requestAnimationFrame(syncRootBackground);
    } else {
      setTimeout(syncRootBackground, 0);
    }
    setTimeout(syncRootBackground, 300);
  }

  function apply() {
    var enabled = isDark();

    if (isSelfThemedScreen()) {
      // The page's own CSS does the painting here; the palette reaches it
      // through its tokens. Disable defensively in case a previous navigation
      // on this document had Dark Reader on.
      DarkReader.disable();
      applySelfThemedTokens();
      return;
    }

    if (enabled) {
      DarkReader.enable(buildTheme(), buildFixes());
    } else {
      DarkReader.disable();
    }

    if (enabled) {
      scheduleRootBackgroundSync();
    } else {
      HTML.style.removeProperty("background-color");
    }

    applyToAllIframes(enabled);
    watchForIframes();
  }

  /**
   * Keep the palette class on <html> in step with the frontend engine's
   * convention, so CSS that keys off it (the switch, the admin bar icon) still
   * matches. "auto" gets no class — there is no palette to name.
   */
  function applyPaletteClass() {
    var classes = Array.prototype.slice.call(HTML.classList);
    for (var i = 0; i < classes.length; i++) {
      if (classes[i].indexOf("darkify-set") === 0) {
        HTML.classList.remove(classes[i]);
      }
    }

    var name = currentPaletteName();
    if (name !== "auto") {
      HTML.classList.add("darkify-" + name);
    }
  }

  /* ---------------------------------------------------------------------- */
  /* Public API (names are load-bearing — see the file header)              */
  /* ---------------------------------------------------------------------- */

  window.darkify_switch_trigger = function () {
    if (!optionEnabled()) {
      return;
    }
    HTML.classList.toggle(DARK_CLASS);
    writeState(isDark());
    apply();
  };

  window.darkify_theme_select = function (theme) {
    // "auto" is a valid choice, not a missing one: it hands the colours back to
    // Dark Reader's derivation.
    if (isPaletteValue(theme)) {
      try {
        localStorage.setItem(paletteKey(), theme);
      } catch (e) {
        // not remembered, still applied below
      }
    }

    applyPaletteClass();
    apply();
  };

  /** Exposed for support: what the engine thinks it is doing right now. */
  window.darkifyAdminEngine = {
    engine: "darkreader",
    version: typeof DarkReader.getVersion === "function" ? DarkReader.getVersion() : null,
    isDark: isDark,
    palette: currentPaletteName,
    paletteKey: paletteKey,
    selfThemed: isSelfThemedScreen,
    palettes: function () {
      return ["auto"].concat(Object.keys(PALETTES));
    },
    theme: buildTheme,
    fixes: buildFixes,
    reapply: apply,

    /**
     * Preview unsaved palette choices.
     *
     * @param {{admin?: string, editor?: string}|null} overrides
     *        Palette ids to show, or null to drop back to saved values.
     */
    preview: function (overrides) {
      previewOverrides = overrides || null;
      applyPaletteClass();
      apply();
    },
  };

  /* ---------------------------------------------------------------------- */
  /* Boot                                                                   */
  /* ---------------------------------------------------------------------- */

  function init() {
    // Cross-origin admin stylesheets (a CDN-hosted plugin sheet) are otherwise
    // skipped, leaving patches of the admin un-darkened.
    try {
      DarkReader.setFetchMethod(window.fetch.bind(window));
    } catch (e) {
      // ignore
    }

    var on = optionEnabled() && readState();

    if (on) {
      HTML.classList.add(DARK_CLASS);
      applyPaletteClass();
    } else {
      HTML.classList.remove(DARK_CLASS);
    }

    if (on && !isSelfThemedScreen()) {
      DarkReader.enable(buildTheme(), buildFixes());
    } else {
      DarkReader.disable();
    }

    if (isSelfThemedScreen()) {
      applySelfThemedTokens();
    } else if (on) {
      // init() runs in <head>, where there is no <body> to measure yet.
      if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", scheduleRootBackgroundSync);
      } else {
        scheduleRootBackgroundSync();
      }
    }
  }

  /*
   * Two-stage boot, and the split is the whole point.
   *
   * The paint has to be claimed before the browser makes one: this script is
   * printed in <head>, and Dark Reader is built to run there — enable() does not
   * need a parsed body. Waiting for DOMContentLoaded to call it would let the
   * light admin paint first and then swap, which is exactly the flash the
   * pre-paint snippet in header_script.php exists to prevent.
   *
   * The iframe work genuinely does need a body to query and observe, so only
   * that half waits.
   */
  init();

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initFrames);
  } else {
    initFrames();
  }

  function initFrames() {
    if (isSelfThemedScreen()) {
      return;
    }

    /*
     * init() ran in <head>, where there is no <body> to test — so
     * isEditorContext() was necessarily false and the page was themed with the
     * admin palette. On the block editor that is the wrong one, and it would
     * have left the editor's chrome on the admin palette while only the canvas
     * picked up the editor's. Now that the body exists the context is knowable,
     * so re-theme the page before touching the frames.
     */
    if (isEditorContext()) {
      apply();
      return; // apply() reaches the frames itself
    }

    applyToAllIframes(isDark());
    watchForIframes();
  }
})();
