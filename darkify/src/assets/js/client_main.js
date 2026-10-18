"use strict";

// Skip ALL Darkify initialization when this page is running inside a frontend
// iframe and the parent site has "Frontend Iframe Dark Mode" turned OFF.
// This is the only reliable guard for same-origin embedded pages that have
// their own copy of Darkify running — they must not self-initialize dark mode.
var _dkf_iframe_disabled = (
    window !== window.top &&
    typeof darkify_is_this_admin_panel !== "undefined" &&
    darkify_is_this_admin_panel !== "1" &&
    typeof darkify_enable_frontend_iframe_dark_mode !== "undefined" &&
    darkify_enable_frontend_iframe_dark_mode !== "1"
);

let has_process_run_at_least_once = false;
let old_transition = "";
let has_background_img_url = false;

/* ==========================================================================
   Developer diagnostics
   --------------------------------------------------------------------------
   Off by default and off in production. The whole layer is gated behind one
   boolean resolved once at startup; when it is false every instrumentation
   point is a single `if` against a constant that the JIT folds away, and no
   timers, no counters and no console output exist. Nothing here runs for a
   visitor.

   Turn it on per-browser with `?darkify_debug=1` on any URL (it persists for
   the session), or from the console with:

       localStorage.darkify_debug = "1"   // then reload
       delete localStorage.darkify_debug  // to turn it back off

   Then inspect from the console:

       darkifyDebug.report()   // summary table: passes, timings, counts
       darkifyDebug.elements() // the elements this engine processed
       darkifyDebug.css()      // generated pseudo-element CSS + its size
       darkifyDebug.reset()    // zero the counters

   This exists because the failure mode these numbers describe — an engine
   quietly doing quadratic work — is invisible from the page. Nothing renders
   wrong; the tab just stops responding, and without counters there is no way
   for a site owner or a support agent to tell an engine problem from a theme
   problem. The counters below are the same ones used to measure this release.
   ========================================================================== */

const DARKIFY_DEBUG = (function () {
  try {
    if (
      typeof location !== "undefined" &&
      location.search.indexOf("darkify_debug=1") !== -1
    ) {
      localStorage.darkify_debug = "1";
      return true;
    }
    if (
      typeof location !== "undefined" &&
      location.search.indexOf("darkify_debug=0") !== -1
    ) {
      delete localStorage.darkify_debug;
      return false;
    }
    return localStorage.darkify_debug === "1";
  } catch (e) {
    return false;
  }
})();

const darkify_debug_state = DARKIFY_DEBUG
  ? {
      started: (typeof performance !== "undefined" ? performance.now() : 0),
      full_walks: 0,
      incremental_walks: 0,
      state_sweeps: 0,
      class_redrives: 0,
      elements_processed: 0,
      elements_reprocessed: 0,
      observer_callbacks: 0,
      nodes_queued: 0,
      walk_ms: 0,
      sweep_ms: 0,
      errors: [],
    }
  : null;

/** Time `fn`, adding the elapsed milliseconds to `bucket`. Debug builds only. */
function darkify_debug_time(bucket, fn) {
  if (!DARKIFY_DEBUG) {
    return fn();
  }
  var t = performance.now();
  try {
    return fn();
  } finally {
    darkify_debug_state[bucket] += performance.now() - t;
  }
}

function darkify_debug_count(key, n) {
  if (DARKIFY_DEBUG) {
    darkify_debug_state[key] += n === undefined ? 1 : n;
  }
}

function darkify_debug_error(where, err) {
  if (DARKIFY_DEBUG) {
    darkify_debug_state.errors.push(where + ": " + (err && err.message ? err.message : err));
  }
}

if (DARKIFY_DEBUG) {
  window.darkifyDebug = {
    /** Raw counters, if you want to diff them yourself. */
    state: darkify_debug_state,

    report: function () {
      var s = darkify_debug_state;
      var processed = document.querySelectorAll(".darkify_processed").length;
      var inline = document.querySelectorAll("[style]").length;
      var pseudo = document.querySelectorAll("[data-darkify-pseudo]").length;
      var css = this.css();

      var rows = {
        "dark mode active": darkify_is_dark(),
        "elements in document": document.getElementsByTagName("*").length,
        "elements processed (marked)": processed,
        "process calls (incl. re-process)": s.elements_processed,
        "re-processed after class change": s.elements_reprocessed,
        "full DOM walks": s.full_walks,
        "incremental walks": s.incremental_walks,
        "state sweeps (dark<->light)": s.state_sweeps,
        "class-change redrives": s.class_redrives,
        "observer callbacks": s.observer_callbacks,
        "nodes queued by observer": s.nodes_queued,
        "time in walks (ms)": +s.walk_ms.toFixed(1),
        "time in state sweeps (ms)": +s.sweep_ms.toFixed(1),
        "elements with inline style": inline,
        "pseudo-element rules written": pseudo,
        "generated CSS (bytes)": css.bytes,
        errors: s.errors.length,
      };

      if (typeof console.table === "function") {
        console.table(rows);
      } else {
        console.log(rows);
      }
      if (s.errors.length) {
        console.warn("Darkify errors:", s.errors);
      }
      return rows;
    },

    /** The elements this engine has classified, with the class it assigned. */
    elements: function () {
      return Array.from(document.querySelectorAll(".darkify_processed")).map(
        function (el) {
          return {
            el: el,
            tag: el.nodeName.toLowerCase(),
            darkify: Array.from(el.classList)
              .filter(function (c) {
                return c.indexOf("darkify_") === 0;
              })
              .join(" "),
          };
        },
      );
    },

    /** The stylesheet the engine generates for pseudo-elements, and its size. */
    css: function () {
      // Every stylesheet this engine injects, so the reported size is the
      // engine's real CSS footprint rather than one of its sheets.
      var ids = [
        "darkify-pseudo-surfaces",
        "darkify_blend_guard_style",
        "darkify-iframe-css",
        "darkify-iframe-vars",
        "darkify-block-editor-css",
      ];
      var text = "";
      var per = {};
      ids.forEach(function (id) {
        var el = document.getElementById(id);
        var t = el ? el.textContent : "";
        if (t) {
          per[id] = t.length;
          text += t;
        }
      });
      return { bytes: text.length, sheets: per, text: text };
    },

    reset: function () {
      Object.keys(darkify_debug_state).forEach(function (k) {
        if (typeof darkify_debug_state[k] === "number") {
          darkify_debug_state[k] = 0;
        }
      });
      darkify_debug_state.errors.length = 0;
    },
  };

  console.log(
    "%cDarkify debug enabled%c — run darkifyDebug.report() for engine stats, " +
      "darkifyDebug.elements() for processed elements. " +
      "Disable with ?darkify_debug=0",
    "background:#2154ea;color:#fff;padding:2px 6px;border-radius:3px",
    "",
  );
}
let darken_level = parseInt(darkify_bg_image_darken_to) / 100;
darken_level = darken_level.toFixed(1);
let darkify_secondary_bg_color = "";

/* ==========================================================================
   Colour transform helpers.
   --------------------------------------------------------------------------
   Everything else in this engine repaints an element by stamping a
   `darkify_style_*` class on it and letting client_main.css win the cascade.
   That doesn't reach generated boxes (`::before`/`::after`), which have no
   element of their own for a class to land on — darkify_transform_color() and
   darkify_recolor_gradient() below are what darkify_pseudo_declarations()
   uses to give those a dark-mode colour/gradient instead.
   ========================================================================== */

var darkify_gradient_mode = "recolor";

/**
 * This whole layer is frontend-only.
 *
 * wp-admin's colours are WordPress's own chrome, already handled by the
 * class-based repaint, and re-deriving them here only fights it — an editor
 * full of preserved accents turns into one tinted wash, because an editor is
 * thousands of small nodes each carrying an accent that this layer would
 * faithfully preserve. So the admin keeps exactly the behaviour it had before
 * this layer existed.
 */
var darkify_adaptive_layer_enabled = !(
  typeof darkify_is_this_admin_panel !== "undefined" &&
  darkify_is_this_admin_panel === "1"
);

/** Matches one functional colour token inside a longer value (a gradient stop). */
var DARKIFY_COLOR_TOKEN = /rgba?\([^()]*\)/g;

/**
 * Parse any colour the engine can meet — computed `rgb()`/`rgba()` (comma or
 * space syntax) and the hex an admin field stores.
 *
 * @return {{r:number,g:number,b:number,a:number}|null}
 */
function darkify_parse_color(value) {
  if (!value) {
    return null;
  }

  var str = String(value).trim().toLowerCase();

  if (str === "transparent") {
    return { r: 0, g: 0, b: 0, a: 0 };
  }

  var hex = str.match(/^#([0-9a-f]{3,8})$/);
  if (hex) {
    var digits = hex[1];
    if (digits.length === 3 || digits.length === 4) {
      digits = digits
        .split("")
        .map(function (c) {
          return c + c;
        })
        .join("");
    }
    if (digits.length !== 6 && digits.length !== 8) {
      return null;
    }
    return {
      r: parseInt(digits.slice(0, 2), 16),
      g: parseInt(digits.slice(2, 4), 16),
      b: parseInt(digits.slice(4, 6), 16),
      a: digits.length === 8 ? parseInt(digits.slice(6, 8), 16) / 255 : 1,
    };
  }

  var fn = str.match(/^rgba?\(([^)]*)\)$/);
  if (!fn) {
    return null;
  }

  var parts = fn[1].replace(/\//g, " ").split(/[\s,]+/).filter(Boolean);
  if (parts.length < 3) {
    return null;
  }

  var channel = function (part) {
    var n =
      part.indexOf("%") !== -1
        ? (parseFloat(part) * 255) / 100
        : parseFloat(part);
    return isNaN(n) ? 0 : n;
  };

  var alpha = 1;
  if (parts.length > 3) {
    alpha =
      parts[3].indexOf("%") !== -1
        ? parseFloat(parts[3]) / 100
        : parseFloat(parts[3]);
    if (isNaN(alpha)) {
      alpha = 1;
    }
  }

  return {
    r: channel(parts[0]),
    g: channel(parts[1]),
    b: channel(parts[2]),
    a: alpha,
  };
}

/** `#rrggbb` form of any parseable colour — the override map's key. */
function darkify_normalize_color(value) {
  var color = darkify_parse_color(value);
  if (!color) {
    return "";
  }

  var byte = function (n) {
    var v = Math.max(0, Math.min(255, Math.round(n)));
    return (v < 16 ? "0" : "") + v.toString(16);
  };

  return "#" + byte(color.r) + byte(color.g) + byte(color.b);
}

function darkify_rgb_to_hsl(color) {
  var r = color.r / 255;
  var g = color.g / 255;
  var b = color.b / 255;
  var max = Math.max(r, g, b);
  var min = Math.min(r, g, b);
  var l = (max + min) / 2;
  var h = 0;
  var s = 0;

  if (max !== min) {
    var d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    if (max === r) {
      h = (g - b) / d + (g < b ? 6 : 0);
    } else if (max === g) {
      h = (b - r) / d + 2;
    } else {
      h = (r - g) / d + 4;
    }
    h *= 60;
  }

  return { h: h, s: s, l: l, a: color.a };
}

/**
 * How much colour a value actually carries, 0 (grey) to 1 (fully saturated),
 * measured as the spread between its strongest and weakest channel.
 *
 * This exists because HSL saturation cannot be trusted near white or black. Its
 * denominator (`2 - max - min`) collapses as lightness approaches either end, so
 * a barely-tinted off-white is divided by almost nothing and comes out looking
 * saturated: Astra's page background `#f5f7f9` spans 4/255 of the channel range —
 * grey to any eye — yet scores s = 0.25, well past the brand threshold. Every
 * theme that ships a tinted off-white (Astra, Kadence, GeneratePress) hit the
 * same thing, and their section backgrounds were being treated as brand colour,
 * keeping a hue the user never chose instead of resolving to the preset's
 * secondary background.
 *
 * Chroma has no such denominator, so it reports what the eye sees at any
 * lightness. A real brand colour still measures high (`#0084d6` → 0.84), so
 * nothing that should keep its hue loses it.
 *
 * @param {{r:number,g:number,b:number}} color
 * @return {number}
 */
function darkify_chroma(color) {
  var max = Math.max(color.r, color.g, color.b);
  var min = Math.min(color.r, color.g, color.b);
  return (max - min) / 255;
}

/**
 * Contrast a small control's background must clear against the page backdrop.
 *
 * Deliberately low. This is an "is it there at all" floor, not a WCAG text
 * target: the goal is that a track, handle or stepper reads as an object on the
 * page, while still sitting quietly behind the content the way it did in light
 * mode. Pushing it higher makes every slider and divider on the site louder in
 * dark mode than its designer drew it.
 */
var DARKIFY_CONTROL_MIN_CONTRAST = 1.8;

/**
 * Whether an element is small enough that vanishing into the page is a bug
 * rather than the intended result.
 *
 * Two shapes qualify. A hairline — a track, divider, rule or progress bar — is
 * thin in one axis whatever its length. A control — a slider handle, stepper
 * button, swatch or badge — is small in both. Anything larger is a surface and
 * is left to the surface ramp, which is what stops a black hero section or a
 * dark footer from being lifted into grey.
 */
function darkify_is_control_sized(element) {
  if (!element || typeof element.getBoundingClientRect !== "function") {
    return false;
  }

  var rect = element.getBoundingClientRect();
  if (!rect.width || !rect.height) {
    return false;
  }

  var hairline = rect.height <= 8 || rect.width <= 8;
  var control = rect.width <= 64 && rect.height <= 64;

  return hairline || control;
}

/**
 * HSL back to RGB, so a colour this file just derived can be measured.
 *
 * @param {{h:number,s:number,l:number}} hsl
 * @return {{r:number,g:number,b:number}}
 */
function darkify_hsl_to_rgb(hsl) {
  var h = ((hsl.h % 360) + 360) % 360 / 360;
  var s = Math.max(0, Math.min(1, hsl.s));
  var l = Math.max(0, Math.min(1, hsl.l));

  if (s === 0) {
    var v = Math.round(l * 255);
    return { r: v, g: v, b: v };
  }

  var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
  var p = 2 * l - q;
  var channel = function (t) {
    if (t < 0) t += 1;
    if (t > 1) t -= 1;
    if (t < 1 / 6) return p + (q - p) * 6 * t;
    if (t < 1 / 2) return q;
    if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
    return p;
  };

  return {
    r: Math.round(channel(h + 1 / 3) * 255),
    g: Math.round(channel(h) * 255),
    b: Math.round(channel(h - 1 / 3) * 255),
  };
}

/** WCAG relative luminance. */
function darkify_relative_luminance(rgb) {
  var channel = function (value) {
    var c = value / 255;
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  };
  return (
    0.2126 * channel(rgb.r) +
    0.7152 * channel(rgb.g) +
    0.0722 * channel(rgb.b)
  );
}

/** WCAG contrast ratio between two relative luminances. */
function darkify_contrast_ratio(a, b) {
  var lighter = Math.max(a, b);
  var darker = Math.min(a, b);
  return (lighter + 0.05) / (darker + 0.05);
}

/**
 * The first solid colour actually behind `element`, walking up the DOM as it
 * is live right now — not the page's general dark-mode surface tokens.
 *
 * Most of the time these agree: a card gets its background painted dark by
 * the class-based repaint before its children are visited, so by the time an
 * icon inside it is processed, reading the parent's live computed background
 * already returns the dark value. But some containers — a WordPress social
 * icon link, a badge with its own configured background colour — are never
 * touched by the class-based repaint at all and keep their light-mode colour
 * on purpose. An icon inside one of those doesn't sit on the page's dark
 * surface; it sits on that untouched colour, and judging its contrast against
 * the wrong backdrop is how a dark icon on a light circle — already perfectly
 * readable — gets "fixed" into matching its own circle.
 */
function darkify_nearest_opaque_background(element) {
  var node = element.parentElement;
  while (node && node.nodeType === 1) {
    var bg = darkify_parse_color(window.getComputedStyle(node, null).backgroundColor);
    if (bg && bg.a >= 0.5) {
      return bg;
    }
    node = node.parentElement;
  }
  return null;
}

/**
 * Lift a small control's colour until it can actually be seen.
 *
 * The ramps above are built for surfaces, where landing near the page's own
 * depth is the correct answer — a section that blends into the page is a
 * section that stopped competing with the content. For a control that same
 * answer erases it. A WooCommerce price slider is the clearest case: its track
 * is a 4px `#e5e5e5` line and its handles are solid black, so the ramp sends the
 * track and the handles to within 0.03 lightness of the page and the whole
 * control disappears — the user cannot see there is anything to drag. Quantity
 * steppers, thin dividers, progress bars and toggle tracks all fail the same
 * way.
 *
 * Applied only to small elements, and that limit is what makes it safe: a
 * genuinely dark *section* must stay dark, and only something control-sized can
 * be lifted without turning a black hero into a grey one.
 *
 * @param {{h:number,s:number,l:number,a:number}} hsl The derived colour.
 * @param {number} min_ratio Contrast to guarantee against the page backdrop.
 * @return {{h:number,s:number,l:number,a:number}}
 */
function darkify_ensure_control_visible(hsl, min_ratio) {
  var tokens = darkify_surface_tokens();
  if (!tokens) {
    return hsl;
  }

  // Measured against the lighter of the two surface tokens: a control sitting on
  // a raised card is the harder case, and clearing that clears the page too.
  var backdrop = darkify_relative_luminance(
    darkify_hsl_to_rgb(tokens.raised.l > tokens.base.l ? tokens.raised : tokens.base),
  );

  var lifted = { h: hsl.h, s: hsl.s, l: hsl.l, a: hsl.a };
  // 0.62 keeps the lift below the palette's text level, so a lifted control
  // never outshines the words next to it.
  while (lifted.l < 0.62) {
    var ratio = darkify_contrast_ratio(
      darkify_relative_luminance(darkify_hsl_to_rgb(lifted)),
      backdrop,
    );
    if (ratio >= min_ratio) {
      break;
    }
    lifted.l += 0.02;
  }

  return lifted;
}

function darkify_hsl_to_css(hsl) {
  return (
    "hsla(" +
    Math.round(hsl.h) +
    ", " +
    Math.round(Math.max(0, Math.min(1, hsl.s)) * 100) +
    "%, " +
    Math.round(Math.max(0, Math.min(1, hsl.l)) * 100) +
    "%, " +
    Math.round((hsl.a === undefined ? 1 : hsl.a) * 100) / 100 +
    ")"
  );
}

/**
 * The dark-mode counterpart of one light-mode colour, or "" to leave it alone.
 *
 * Hue is always preserved and only chroma and lightness move: that is what
 * separates this from an inversion, which turns a blue accent orange. A colour
 * near the grey axis is treated as structural and mapped onto the dark
 * neutral ramp; a saturated one is treated as brand and keeps its identity with
 * its chroma capped and its lightness pulled to a level that reads on a dark
 * backdrop instead of blooming against it.
 *
 * @param {string} value The light-mode colour, in any parseable form.
 * @param {string} role  text | icon | background | border.
 * @param {{neutrals?:boolean,control?:boolean}} [opts]
 *        `neutrals` — also transform greys. Off by default, and that default is
 *        load-bearing: greys are what the colour presets are made of, so
 *        re-deriving them here would quietly overrule whichever palette the user
 *        picked and make the preset picker meaningless. Greys belong to the
 *        class-based repaint; this layer only takes them when there is no token
 *        to fall back on (a gradient stop or a generated box's own colour).
 */
function darkify_transform_color(value, role, opts) {
  opts = opts || {};

  var color = darkify_parse_color(value);
  if (!color || color.a === 0) {
    return "";
  }

  var hsl = darkify_rgb_to_hsl(color);
  var chroma = darkify_chroma(color);
  // Either measure may call a colour grey. HSL saturation catches mid-lightness
  // greys; chroma catches the tinted off-whites and near-blacks whose saturation
  // HSL inflates (see darkify_chroma). A colour has to look like brand on BOTH
  // to be treated as brand.
  var neutral = hsl.s < 0.12 || chroma < 0.06;

  // A tinted near-black is body copy, not a brand colour.
  //
  // Themes rarely set text to pure #000: they pick an ink like #0A232A or
  // #101828 — a hue, but at a lightness no design ever uses for an accent.
  // HSL reports those at saturation 0.6+ (32 levels of spread over a range that
  // narrow measures as highly saturated), and chroma of 0.13 clears the grey
  // test, so the pair above calls the page's own text a brand colour and the
  // foreground ramp lifts it to a light teal at 62% saturation. Every heading
  // and paragraph on the site comes out tinted, and the palette's text colour —
  // which the user actually picked — is overridden by an inline `!important`
  // that the class-based repaint cannot outrank.
  //
  // Lightness is what separates the two cases: an accent has to be visible
  // against a light page, so it lives in the midtones, while ink and its
  // near-white counterpart (light text inside a dark section) sit at the ends.
  // Foreground roles only — a near-black *surface* is structure, and the
  // surface ramp above already places it. The chroma ceiling keeps a genuinely
  // saturated deep colour (a dark-red brand rule, say) on the brand path.
  if (
    !neutral &&
    role !== "background" &&
    chroma < 0.35 &&
    (hsl.l < 0.22 || hsl.l > 0.92)
  ) {
    neutral = true;
  }

  // Surfaces are placed on the palette ramp unconditionally, ahead of the Brand
  // Colors setting, because that setting is about brand *hues* and this is not a
  // hue question — it is the page's structure. Gating it here would mean two
  // things, both wrong: distinct surfaces would collapse back into one flat
  // token, and gradients (which recolour regardless) would keep showing the
  // separation that solid sections had just lost — the same inconsistency
  // between a gradient section and the solid one under it that started all of
  // this.
  if (neutral && role === "background") {
    return darkify_surface_color(hsl, opts.control);
  }

  if (neutral) {
    // Neutral text and borders carry no structural role, so they follow the
    // palette's own tokens rather than a ramp.
    if (!opts.neutrals) {
      return "";
    }
    var neutral_tokens = darkify_surface_tokens();
    var neutral_target =
      role === "border"
        ? neutral_tokens && neutral_tokens.border
        : neutral_tokens && neutral_tokens.text;
    if (neutral_target) {
      return darkify_hsl_to_css({
        h: neutral_target.h,
        s: neutral_target.s,
        l: neutral_target.l,
        a: hsl.a,
      });
    }
  }

  // A brand colour keeps its hue — that is the whole point of adaptive — but its
  // depth is anchored to the palette rather than to a fixed constant. Without
  // that anchor a blue panel renders at exactly the same lightness whichever
  // preset is chosen, so on a light-ish preset it sits *below* the palette's own
  // surfaces and on a very dark one it floats above them: the brand colour and
  // the preset visibly belong to different designs. Anchoring means the same
  // blue reads as the same depth of surface in every preset.
  var tokens = darkify_surface_tokens();
  var anchor = function (token, fallback) {
    return token ? token.l : fallback;
  };

  if (role === "background") {
    // Brand surfaces sit at the palette's raised-surface depth, nudged a little
    // by how light the original was so two brand shades stay distinguishable.
    var raised = anchor(tokens && tokens.raised, 0.09);
    hsl.l = Math.max(0.04, raised + (hsl.l - 0.5) * 0.12);
    hsl.s = Math.min(hsl.s, 0.55);
    if (opts.control) {
      hsl = darkify_ensure_control_visible(hsl, DARKIFY_CONTROL_MIN_CONTRAST);
    }
  } else if (role === "border") {
    var line = anchor(tokens && tokens.border, 0.29);
    hsl.l = Math.max(0.08, line + (hsl.l - 0.5) * 0.1);
    hsl.s = Math.min(hsl.s, 0.45);
  } else {
    // Text and icons share a ramp: both are foreground, both have to stay
    // legible without going bright enough to bloom. The palette's own text
    // colour sets the level they aim for.
    var fg = anchor(tokens && tokens.text, 0.75);
    hsl.l = Math.max(0.5, Math.min(0.86, fg + (0.5 - hsl.l) * 0.12));
    hsl.s = Math.min(hsl.s, 0.62);
  }

  return darkify_hsl_to_css(hsl);
}

/**
 * The palette's two surface tokens, as HSL.
 *
 * Cached against the <html> class list because the theme picker swaps palettes
 * by changing a class, which changes what these custom properties resolve to.
 */
var darkify_surface_cache = null;

function darkify_surface_tokens() {
  var key = document.documentElement.className;
  if (darkify_surface_cache && darkify_surface_cache.key === key) {
    return darkify_surface_cache.value;
  }

  var styles = window.getComputedStyle(document.documentElement);
  var read = function (name) {
    var parsed = darkify_parse_color(styles.getPropertyValue(name).trim());
    return parsed ? darkify_rgb_to_hsl(parsed) : null;
  };

  var base = read("--darkify_dark_mode_bg");
  var raised = read("--darkify_dark_mode_secondary_bg");
  var text = read("--darkify_dark_mode_text_color");
  var border = read("--darkify_dark_mode_border_color");

  var value =
    base && raised
      ? { base: base, raised: raised, text: text, border: border }
      : null;

  darkify_surface_cache = { key: key, value: value };
  return value;
}

/**
 * Place a neutral surface on the palette's ramp instead of flattening it.
 *
 * A light design separates its sections by a few percent of lightness — white
 * against #F5F5F5 — and that difference is the page's structure: it is what
 * makes a card read as sitting on a page rather than merging into it. Handing
 * every one of those surfaces to the same token erases the structure, which is
 * why sections that were distinct in light mode ran together in dark.
 *
 * The mapping is inverted, as dark interfaces are built: the *lightest* light
 * surface becomes the *darkest* dark one, and the subtler alternates lift away
 * from it. Both ends come from the chosen palette, so a preset still decides
 * the actual colours — including its hue, which matters for the tinted presets.
 */
/**
 * Where a surface that was ALREADY dark in the light design belongs.
 *
 * The ramp below this exists to convert light surfaces, and it reads from the
 * band [0.96, 1.0] because that is where light surfaces live — crowded up
 * against white, a few percent apart. A surface at l = 0.07 is not a member of
 * that population, and running it through anyway clamps it to the far end and
 * lands it on the raised token.
 *
 * For a section that is the wrong answer twice over. A near-black band on a pale
 * page is not a background the designer happened to pick; it is a contrast
 * device — a CTA strip, a dark footer, a highlighted row — whose entire job is
 * to stand apart from the page around it. Clamping paints it the exact colour of
 * the palette's own surface, so on a dark page it stops being a band at all and
 * merges into the section above and below it. The design loses a division it was
 * built around, and nothing in the output shows why.
 *
 * The relation worth keeping is the one the light design stated: this surface is
 * darker than the page. So it is kept where it is, which preserves that relation
 * on a dark page too, and only nudged when it would otherwise land on one of the
 * palette's own surfaces. It nudges downward for the same reason — darker than
 * the page is what it was, and moving down can never lift a black band into grey.
 *
 * Saturation is still capped, so a dark brand band (navy, oxblood) keeps its hue
 * without the full light-mode chroma blooming on a dark backdrop.
 */
function darkify_preserved_dark_surface(hsl, tokens) {
  var out = {
    h: hsl.h,
    s: Math.min(hsl.s, 0.55),
    l: hsl.l,
    a: hsl.a,
  };

  var lands_on = function (token) {
    return token && Math.abs(out.l - token.l) < 0.015;
  };

  if (lands_on(tokens.base) || lands_on(tokens.raised)) {
    out.l = Math.max(
      0,
      Math.min(tokens.base.l, tokens.raised.l) - 0.03,
    );
  }

  return out;
}

function darkify_surface_color(hsl, control) {
  var tokens = darkify_surface_tokens();
  if (!tokens) {
    return "";
  }

  // A surface that arrived dark is not a light surface being converted, and the
  // ramp below has nothing useful to say about it — see
  // darkify_preserved_dark_surface(). The boundary is the palette's own line
  // colour, the lightest tone it still treats as structure rather than content,
  // so what counts as "already dark" follows whichever preset is in use instead
  // of a constant that would suit only one of them.
  //
  // Controls are excluded and must be: their parts are told apart BY their
  // greys, and a black slider handle left black is a control the user cannot
  // see. They keep the second leg further down, which lifts them deliberately.
  var already_dark = tokens.border ? tokens.border.l : 0.3;
  if (!control && hsl.l <= already_dark) {
    return darkify_hsl_to_css(darkify_preserved_dark_surface(hsl, tokens));
  }

  // Light surfaces live in a narrow band near white, so the band this reads
  // from is narrow too — otherwise every one of them rounds to the same end.
  var t = Math.max(0, Math.min(1, (1 - hsl.l) / 0.04));
  var mix = function (from, to) {
    return from + (to - from) * t;
  };

  var out = {
    h: mix(tokens.base.h, tokens.raised.h),
    s: mix(tokens.base.s, tokens.raised.s),
    l: mix(tokens.base.l, tokens.raised.l),
    a: hsl.a,
  };

  // Past the band, a surface can only clamp to the raised token — correct for a
  // section (a page is two or three surfaces deep, and flattening the rest onto
  // the raised one is what keeps it calm) but wrong for a control, whose parts
  // are told apart *by* their greys. A price slider is drawn as a light track
  // with a black filled range; clamped, both land on the raised token, the two
  // become one colour and the control still reads as a single dead line even
  // after the visibility floor lifts it.
  //
  // So controls get a second leg: below the band their lightness keeps climbing
  // toward the border token and a little past it, which preserves the ordering
  // the design drew — the range stays stronger than the track it sits on.
  //
  // Controls only, and that restriction is the whole safety argument: run this
  // on any element and a black hero section or a dark footer would be lifted
  // into grey, inverting a design that was already dark.
  if (control && hsl.l < 0.96 && tokens.border) {
    var line = tokens.border;
    var depth = Math.max(0, Math.min(1, (0.96 - hsl.l) / 0.36));
    var beyond = Math.max(0, Math.min(1, (0.6 - hsl.l) / 0.6));

    out.h = line.h;
    out.s = line.s;
    out.l =
      tokens.raised.l +
      (line.l - tokens.raised.l) * depth +
      Math.min(0.16, 0.16 * beyond);
  }

  if (control) {
    out = darkify_ensure_control_visible(out, DARKIFY_CONTROL_MIN_CONTRAST);
  }

  return darkify_hsl_to_css(out);
}

/** True while dark mode is on. */
function darkify_is_dark() {
  return document
    .getElementsByTagName("html")[0]
    .classList.contains("darkify_dark_mode_enabled");
}

/**
 * Drop pseudo-element entries from a comma-separated selector list.
 *
 * `element.matches()` rejects a selector carrying a pseudo-element outright, so
 * one such entry throws for the whole list. They reach the Disallowed Elements
 * list routinely: Custom CSS selectors are auto-excluded (see
 * DarkifyUtils::extractCustomCssSelectorsForAutoExcluding), and a stylesheet
 * writing `::before` is entirely ordinary. Nothing is lost by dropping them —
 * a pseudo-element is not a node the walk could have visited anyway.
 */
function darkify_strip_pseudo_elements(selector) {
  if (!selector) return selector;
  return selector
    .split(",")
    .map(function (s) {
      return s.trim();
    })
    .filter(function (s) {
      return !/:{1,2}(?:before|after|first-line|first-letter|placeholder|selection|marker|backdrop|spelling-error|grammar-error|cue|file-selector-button|part|slotted)\b/.test(
        s,
      );
    })
    .join(", ");
}

/**
 * `element.matches()`, but a malformed selector cannot take the engine down.
 *
 * matches() is all-or-nothing: hand it a comma-joined list and a single
 * malformed entry anywhere in it throws SyntaxError, so the honest answer for
 * every other selector in the list is lost too. That is how one bad selector —
 * picked up from Custom CSS auto-exclusion, or typed into Disallowed
 * Elements — used to silently switch the whole restriction off, or worse throw
 * from an uncaught call site. Retry per selector so the good ones still count
 * and only the broken entry is skipped.
 */
function darkify_safe_matches(element, selector) {
  var safe = darkify_strip_pseudo_elements(selector);
  if (!safe) return false;
  try {
    return element.matches(safe);
  } catch (e) {
    if (safe.indexOf(",") === -1) return false;
    var parts = safe.split(",");
    for (var i = 0; i < parts.length; i++) {
      var one = parts[i].trim();
      if (!one) continue;
      try {
        if (element.matches(one)) return true;
      } catch (e2) {
        /* skip this selector only */
      }
    }
    return false;
  }
}

/**
 * The switcher and anything the user marked `darkify_ignore` are off limits.
 *
 * The class-based rules exclude them through `:not()`; an inline declaration
 * has no such selector to hide behind, so the check has to happen here or this
 * layer would repaint the toggle it is controlled by.
 */
function darkify_is_excluded_from_adaptation(element) {
  return (
    darkify_is_off_limits(element) ||
    (!!element.closest && !!element.closest(".darkify_self_themed"))
  );
}

/**
 * Is this element inside something the engine must not touch at all?
 *
 * "Off limits" has to mean untouched: no classes, no `data-darkify-*`
 * bookkeeping, no inline styles, no injected nodes — and the same for
 * everything inside it, so marking a section covers its whole subtree. It is
 * ancestor-aware through `closest()` precisely so it holds whether the target
 * is a full section, a container, or a single element.
 *
 * This has to be asked at the entry point. The engine already had a disallowed
 * check, but it sat deep inside darkify_process_element_settled(), by which
 * point the element had already been read and stamped with the bookkeeping the
 * engine writes before it decides anything — and the subtree capture in
 * darkify_capture_override_sources() reached into those sections from an
 * allowed ancestor. Returning early from the middle of the pipeline can never
 * mean "untouched".
 *
 * Two groups are covered:
 *
 *   - The engine's own furniture, `.darkify_switch` and `.darkify_ignore`.
 *     These carry their own colours and are unconditionally hands-off; nothing
 *     about them is configurable, so nothing gates them. `darkify_ignore` is
 *     the documented escape hatch for "leave this subtree alone".
 *   - The user's own Disallowed Elements — but only when "Force To Correct" is
 *     off, since that option means "keep writing corrective colours onto these
 *     elements", which is the opposite of hands-off. The two cannot both hold,
 *     so the option decides.
 *
 * Deliberately NOT the built-in builder exclusions in
 * `darkify_disallowed_elements`. Those are a different list with a different
 * purpose — they still need their generated-box surfaces handled, or a
 * builder's overlay section stays light (see darkify_process_element_settled).
 * Only the user's own `_raw` list means "hands off".
 */
function darkify_is_off_limits(element) {
  if (!element || element.nodeType !== 1 || !element.closest) {
    return false;
  }

  if (element.closest(".darkify_switch, .darkify_ignore")) {
    return true;
  }

  if (
    typeof darkify_disallowed_elements_raw === "string" &&
    darkify_disallowed_elements_raw.length > 0 &&
    darkify_disallowed_elements_force_to_correct !== "1"
  ) {
    // User-authored selectors: one bad entry must not take the engine down.
    try {
      if (element.closest(darkify_disallowed_elements_raw)) {
        return true;
      }
    } catch (e) {
      return false;
    }
  }

  return false;
}

/** Remember the inline declarations a writer is about to overwrite. */
function darkify_store_inline(element, key, props) {
  if (element.dataset[key]) {
    return;
  }

  var saved = {};
  props.forEach(function (prop) {
    saved[prop] = element.style.getPropertyValue(prop);
  });
  element.dataset[key] = JSON.stringify(saved);
}

/** Put back whatever `darkify_store_inline` saved, then forget it. */
function darkify_restore_inline(element, key, props) {
  var saved = {};
  if (element.dataset[key]) {
    try {
      saved = JSON.parse(element.dataset[key]);
    } catch (e) {
      saved = {};
    }
  }

  props.forEach(function (prop) {
    element.style.removeProperty(prop);
    if (saved[prop]) {
      element.style.setProperty(prop, saved[prop]);
    }
  });

  delete element.dataset[key];
}

/**
 * Recolour every stop of a gradient, leaving its geometry untouched.
 *
 * Greys are included here — unlike the element path — because a gradient has no
 * token to fall back to: leaving a white stop white would keep a bright band
 * across the section, which is the whole complaint.
 */
function darkify_recolor_gradient(image) {
  // Strictly per stop, through the same darkify_transform_color() that solid
  // backgrounds go through — and that shared path is the point, not an
  // implementation detail.
  //
  // A gradient is very often a fade INTO something: a section whose last stop is
  // the page's own background colour, so the section dissolves into the page
  // with no visible edge. Map the stops with one function and the solid page
  // with another — or collapse the gradient to a single colour because its stops
  // look close enough — and that last stop stops matching what the page became.
  // The fade then ends on a colour the page does not have, and the seam the
  // design was built to avoid appears exactly where the section meets the page.
  //
  // Sending both through the same function is what guarantees they still agree:
  // whatever `#f8f6f3` becomes as a page background, it becomes as a gradient
  // stop too. Any future "smooth this out" shortcut here has to preserve that
  // property or it will reintroduce the seam.
  return image.replace(DARKIFY_COLOR_TOKEN, function (token) {
    return (
      darkify_transform_color(token, "background", {
        force: true,
        neutrals: true,
      }) || token
    );
  });
}

/* ==========================================================================
   Deterministic fixes — gradients, shadows, icon colour, colour overrides.
   --------------------------------------------------------------------------
   Three things the class-based repaint above cannot reach: a gradient lives
   in `background-image`, which no `darkify_style_*` rule touches; a coloured
   shadow was never repainted at all; an SVG icon's `fill`/`stroke` isn't
   `color`, so it doesn't inherit the text repaint either. Left alone, all
   three keep their full light-mode brightness on a dark page — a gradient
   section stays a bright band next to a properly darkened solid one, and a
   saturated icon or shadow reads as a glow.

   Unlike the adaptive layer this replaced, nothing here classifies a colour
   as "brand" or preserves its hue: every one of these resolves to a plain
   preset token, deterministically, same as a solid background already does.
   The one exception is the override map below, and that is exact-match only
   — it acts on a colour an admin explicitly pinned, not one this engine
   decided was worth preserving.
   ========================================================================== */

/**
 * Manual light→dark colour map, keyed by scope.
 *
 * Consulted before the deterministic fallback in every writer below, so a
 * site can pin the handful of colours that need a specific replacement
 * without changing how anything else on the page is handled.
 */
var darkify_color_override_map = (function () {
  var rules = [];

  if (
    typeof darkify_color_overrides === "undefined" ||
    !darkify_color_overrides
  ) {
    return rules;
  }

  var rows = darkify_color_overrides;
  if (typeof rows === "string") {
    try {
      rows = JSON.parse(rows);
    } catch (e) {
      return rules;
    }
  }

  if (!Array.isArray(rows)) {
    return rules;
  }

  rows.forEach(function (row) {
    if (!row || !row.light_color || !row.dark_color) {
      return;
    }
    var from = darkify_parse_color(row.light_color);
    if (!from) {
      return;
    }
    rules.push({
      r: from.r,
      g: from.g,
      b: from.b,
      scope: row.override_scope || "all",
      to: row.dark_color,
    });
  });

  return rules;
})();

var darkify_has_color_overrides = darkify_color_override_map.length > 0;

/**
 * How far a rendered colour may sit from a pinned one and still count as it,
 * as a squared RGB distance. Zero — an exact match — is the default and the
 * documented behaviour: #ffffff and #fffffe are two different rules and stay
 * two different rules, so a second override pinned one channel away from the
 * first is never swallowed by it.
 *
 * A site that would rather absorb a typo or a rounded build-pipeline value
 * can widen this from its own script, before client_main.js runs, by setting
 * `window.darkify_override_tolerance` — 432 is roughly five percent per
 * channel, the old built-in fuzz.
 */
var DARKIFY_OVERRIDE_TOLERANCE =
  typeof window !== "undefined" &&
  typeof window.darkify_override_tolerance === "number" &&
  window.darkify_override_tolerance >= 0
    ? window.darkify_override_tolerance
    : 0;

/** The pinned dark colour for `color` in `role`, or "" if none is close enough. */
function darkify_override_for(color, role) {
  var best = "";
  // Not seeded with the tolerance: at the default of zero that would reject
  // the exact match too, since a distance of 0 is not < 0. The tolerance is
  // the admission gate; this only tracks which admitted rule is closest.
  var best_distance = Infinity;

  for (var i = 0; i < darkify_color_override_map.length; i++) {
    var rule = darkify_color_override_map[i];
    if (rule.scope !== "all" && rule.scope !== role) {
      continue;
    }

    var dr = rule.r - color.r;
    var dg = rule.g - color.g;
    var db = rule.b - color.b;
    var distance = dr * dr + dg * dg + db * db;

    if (distance > DARKIFY_OVERRIDE_TOLERANCE) {
      continue;
    }

    // `<`, so two rows pinned to the same colour resolve to the first one
    // listed rather than the last — the repeater's order is visible to the
    // admin, and stable, which nothing about "whichever loop iteration ran
    // last" is.
    if (distance < best_distance) {
      best_distance = distance;
      best = rule.to;
    }
  }

  return best;
}

/** Colour properties the override writer may pin, and therefore must undo. */
var DARKIFY_OVERRIDE_PROPS = [
  "color",
  "background-color",
  "border-top-color",
  "border-right-color",
  "border-bottom-color",
  "border-left-color",
];

var DARKIFY_SHADOW_PROPS = ["box-shadow", "text-shadow"];

/** SVG paint (`fill`/`stroke`) only means something on SVG content. */
function darkify_is_svg_node(element) {
  return (
    typeof SVGElement !== "undefined" &&
    element instanceof SVGElement &&
    element.nodeName.toLowerCase() !== "svg"
  );
}

/**
 * Whether a border side is actually drawn, so pinning a colour to it means
 * something. Mirrors the class-based repaint's own guard: WordPress's global
 * stylesheet ships `html :where([style*="border-color"]) { border-style:
 * solid }`, so writing a colour for a side the design left undrawn makes that
 * selector start matching and draws a line that light mode never had.
 */
function darkify_border_side_is_drawn(element, prop, computedStyle) {
  // "border-top-color" -> "top"
  var side = prop.slice(7, -6);
  var style = computedStyle || window.getComputedStyle(element, null);

  var line = style.getPropertyValue("border-" + side + "-style");
  if (!line || line === "none" || line === "hidden") {
    return false;
  }

  return parseFloat(style.getPropertyValue("border-" + side + "-width")) > 0;
}

/**
 * Pin an element's own colours to an admin-configured override, if any match.
 *
 * Gated on `darkify_has_color_overrides` before touching a single element, so
 * a site with no overrides configured — the default, and the common case —
 * pays no cost here and gets no inline styles from this writer at all.
 *
 * Matches against `data-darkify-override-src` (captured in
 * darkify_process_element_settled(), before the class-based repaint could
 * touch this same element) rather than the live computed style: by the time
 * this writer runs, `background-color`/`color`/`border-*-color` have usually
 * already been repainted to the preset's own tokens, and matching against
 * that would compare the engine's own output to the override instead of the
 * design's original colour.
 */
function darkify_color_override_props(element) {
  // darkify_process_overlay_background() already owns background-color for
  // elements the class-based repaint never reaches (the same
  // `DARKIFY_BG_CLASS_FAMILY` self-gate it uses) — this writer touching the
  // same property from a second, independent "previous value" snapshot would
  // corrupt the restore chain on the way back to light, since each writer's
  // store/restore only knows about its own prior write, not the other one's.
  // Excluded from the *stored* prop list too, not just the write, so a stale
  // "previous value" the other writer left behind never gets captured here
  // in the first place.
  var ownsBackground = DARKIFY_BG_CLASS_FAMILY.some(function (cls) {
    return element.classList.contains(cls);
  });
  return ownsBackground
    ? DARKIFY_OVERRIDE_PROPS
    : DARKIFY_OVERRIDE_PROPS.filter(function (prop) {
        return prop !== "background-color";
      });
}

/**
 * Record the design's own colours for an element and everything under it, so
 * darkify_process_color_overrides() has something honest to match against.
 *
 * Why the whole subtree, when the caller only asked about one element: `color`
 * is an inherited property and border colours resolve through `currentColor`,
 * while the engine's walk is document order — parents first. Capturing each
 * element lazily as the walk reached it meant a child was read only *after* its
 * ancestors had already been repainted, so what got stored as "the design's
 * colour" was really the preset's link token, and the override for the real
 * colour never matched.
 *
 * That went unnoticed while dark mode was reached by toggling, because the
 * light-mode pass had already stamped every element with a pristine snapshot
 * and the `!dataset.darkifyOverrideSrc` guard kept it. Loading a page with dark
 * mode already on has no prior light pass, so the dark pass was the first one
 * to write the snapshot — which is why overrides survived a toggle but were
 * lost on reload.
 *
 * Snapshotting top-down from the first element the walk touches fixes the
 * ordering: every descendant is read before anything has been repainted, and
 * the guard then makes each element's own visit a no-op. Total getComputedStyle
 * calls are unchanged — one per element — they just happen earlier.
 *
 * @param {Element} element      Element the walk is about to process.
 * @param {CSSStyleDeclaration} [computedStyle] Already-read style for `element`.
 */
function darkify_capture_override_sources(element, computedStyle) {
  if (!darkify_has_color_overrides || element.dataset.darkifyOverrideSrc) {
    return;
  }

  var store = function (node, style) {
    // The walk that reaches here may have started on an allowed ancestor, and
    // this recursion visits its whole subtree — so an off-limits section can be
    // stamped from the outside even though the engine never processes it
    // directly. Checked per node for that reason.
    if (darkify_is_off_limits(node)) {
      return;
    }
    if (node.dataset.darkifyOverrideSrc) {
      return;
    }
    var s = style || window.getComputedStyle(node, null);
    node.dataset.darkifyOverrideSrc = JSON.stringify({
      "background-color": s.backgroundColor,
      color: s.color,
      "border-top-color": s.borderTopColor,
      "border-right-color": s.borderRightColor,
      "border-bottom-color": s.borderBottomColor,
      "border-left-color": s.borderLeftColor,
    });
  };

  store(element, computedStyle);

  if (!element.querySelectorAll) {
    return;
  }
  element.querySelectorAll("*").forEach(function (node) {
    store(node);
  });
}

/**
 * Where each overridable property parks its value while an override is active.
 *
 * `var` is the custom property holding the colour; `cls` is the marker class
 * that lets client_main.css apply it. Both halves are needed: the class is what
 * scopes the rule to elements that actually have this property overridden, so
 * an element with only a background override never gets a `color` declaration
 * pointing at an undefined variable.
 */
var DARKIFY_OVERRIDE_SLOTS = {
  "background-color": { var: "--darkify-ovr-bg", cls: "darkify_ovr_bg" },
  color: { var: "--darkify-ovr-color", cls: "darkify_ovr_color" },
  "border-top-color": { var: "--darkify-ovr-bt", cls: "darkify_ovr_bt" },
  "border-right-color": { var: "--darkify-ovr-br", cls: "darkify_ovr_br" },
  "border-bottom-color": { var: "--darkify-ovr-bb", cls: "darkify_ovr_bb" },
  "border-left-color": { var: "--darkify-ovr-bl", cls: "darkify_ovr_bl" },
};

/** Take every override slot back off an element (light mode, or a re-read). */
function darkify_clear_override_slots(element) {
  for (var prop in DARKIFY_OVERRIDE_SLOTS) {
    if (!Object.prototype.hasOwnProperty.call(DARKIFY_OVERRIDE_SLOTS, prop)) {
      continue;
    }
    var slot = DARKIFY_OVERRIDE_SLOTS[prop];
    element.style.removeProperty(slot.var);
    element.classList.remove(slot.cls);
  }
}

function darkify_process_color_overrides(element) {
  if (!darkify_has_color_overrides) {
    return;
  }

  var props = darkify_color_override_props(element);

  if (darkify_is_dark()) {
    if (element.classList.contains("darkify_color_overridden")) {
      return;
    }

    var style = window.getComputedStyle(element, null);
    var source = {};
    if (element.dataset.darkifyOverrideSrc) {
      try {
        source = JSON.parse(element.dataset.darkifyOverrideSrc);
      } catch (e) {
        source = {};
      }
    }
    var next = {};
    var changed = false;

    props.forEach(function (prop) {
      var role =
        prop === "background-color"
          ? "background"
          : prop === "color"
            ? "text"
            : "border";
      if (role === "border" && !darkify_border_side_is_drawn(element, prop, style)) {
        return;
      }
      var raw = source[prop] || style.getPropertyValue(prop);
      var color = darkify_parse_color(raw);
      if (!color || color.a === 0) {
        return;
      }
      var mapped = darkify_override_for(color, role);
      if (mapped) {
        next[prop] = mapped;
        changed = true;
      }
    });

    if (!changed) {
      return;
    }

    darkify_store_inline(element, "darkifyOverridePrev", props);
    element.classList.add("darkify_color_overridden");
    Object.keys(next).forEach(function (prop) {
      var slot = DARKIFY_OVERRIDE_SLOTS[prop];
      if (!slot) {
        // A property with no slot keeps the original inline write. Nothing
        // currently reaches this, but it means an added property degrades to
        // the old behaviour rather than silently not applying.
        element.style.setProperty(prop, next[prop], "important");
        return;
      }
      // The value goes in as a CUSTOM PROPERTY, not as the property itself.
      //
      // It used to be written as `element.style.setProperty(prop, value,
      // "important")`, and an inline `!important` declaration outranks every
      // author stylesheet rule there is — including
      // `.darkify_style_link:hover`. So any element carrying an override lost
      // its dark-mode hover entirely: the colour was pinned in every state.
      //
      // A custom property carries the value without competing for `color` or
      // `background-color` itself. The paired class lets the stylesheet apply
      // it at a specificity that sits deliberately BETWEEN the two rules that
      // matter (see client_main.css): above the base `.darkify_style_*` rules,
      // so the override still wins the normal state, and below the `:hover`
      // rules, so hover works again.
      element.style.setProperty(slot.var, next[prop]);
      element.classList.add(slot.cls);
    });
  } else if (element.classList.contains("darkify_color_overridden")) {
    darkify_restore_inline(element, "darkifyOverridePrev", props);
    darkify_clear_override_slots(element);
    element.classList.remove("darkify_color_overridden");
  }
}

/**
 * Recolour a gradient onto two fixed preset tones, keeping its own type and
 * direction. `image` is a computed `background-image` value — always
 * function notation with `rgb()`/`rgba()` colours, since that's what
 * getComputedStyle() normalises everything to, which is what makes finding
 * the colour stops by regex reliable here.
 *
 * A stop whose original colour matches a configured colour override (scope
 * "background" or "all") is pinned to that override instead of the preset
 * token — the same escape hatch a plain background colour gets, just applied
 * per stop. Anything left over still falls onto the two-tone ramp: the first
 * stop to the base token, every stop after it to the raised token. Stop
 * positions (`0%`, `100%`, ...) are dropped rather than carried over, same as
 * the fallback below — for the common two-stop case that's invisible, since
 * a bare two-stop gradient already defaults to 0%/100%.
 *
 * Returns null for anything that isn't a plain gradient function (a `url()`
 * layer, multiple background layers, `none`), so the caller can fall back.
 */
function darkify_preset_gradient(image) {
  var fn = image.match(
    /^(repeating-linear-gradient|repeating-radial-gradient|repeating-conic-gradient|linear-gradient|radial-gradient|conic-gradient)\(/,
  );
  if (!fn || image.slice(-1) !== ")") {
    return null;
  }

  var tokens = darkify_surface_tokens();
  if (!tokens || !tokens.base || !tokens.raised) {
    return null;
  }

  // Strip the function name and its wrapping parens, then split on the
  // top-level commas — reusing the same parenthesis-depth-aware splitter the
  // shadow writer below uses, since `rgb(...)` commas are not layer breaks
  // here either.
  var inner = image.slice(fn[0].length, -1);
  var parts = darkify_split_shadow_layers(inner);

  // Whatever isn't a colour stop is direction/shape — "90deg", "to right",
  // "circle at center" — and gets kept verbatim so the recoloured gradient
  // still fades the way the design drew it, just between different colours.
  var direction = [];
  var colorParts = [];
  parts.forEach(function (part) {
    DARKIFY_COLOR_TOKEN.lastIndex = 0;
    if (DARKIFY_COLOR_TOKEN.test(part)) {
      colorParts.push(part);
    } else {
      direction.push(part.trim());
    }
  });

  var base = darkify_hsl_to_css({
    h: tokens.base.h,
    s: tokens.base.s,
    l: tokens.base.l,
    a: 1,
  });
  var raised = darkify_hsl_to_css({
    h: tokens.raised.h,
    s: tokens.raised.s,
    l: tokens.raised.l,
    a: 1,
  });

  var stops =
    colorParts.length >= 2
      ? colorParts.map(function (part, index) {
          DARKIFY_COLOR_TOKEN.lastIndex = 0;
          var match = DARKIFY_COLOR_TOKEN.exec(part);
          var color = match ? darkify_parse_color(match[0]) : null;
          var mapped =
            color && darkify_has_color_overrides
              ? darkify_override_for(color, "background")
              : "";
          return mapped || (index === 0 ? base : raised);
        })
      : [base, raised];

  var head = direction.length ? direction.join(", ") + ", " : "";
  return fn[1] + "(" + head + stops.join(", ") + ")";
}

/**
 * True when a gradient is a scrim rather than a surface.
 *
 * A scrim is the fade a design paints OVER something else — a photo, a video,
 * a blob-patterned section background — to keep text readable on it:
 * `linear-gradient(0deg, rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0))`. Every one of
 * its stops is translucent, because the whole point is that the layer beneath
 * shows through. Recolouring it onto two opaque preset tones the way a surface
 * gradient is recoloured does not darken that layer, it deletes it: the photo
 * or pattern underneath disappears behind a flat dark band.
 *
 * The test is deliberately strict — every stop must be translucent. One opaque
 * stop means the gradient does paint its own surface somewhere, and the preset
 * path is right for it.
 */
function darkify_is_scrim_gradient(image) {
  DARKIFY_COLOR_TOKEN.lastIndex = 0;
  var tokens = image.match(DARKIFY_COLOR_TOKEN);
  if (!tokens || tokens.length < 2) {
    return false;
  }

  for (var i = 0; i < tokens.length; i++) {
    var color = darkify_parse_color(tokens[i]);
    if (!color || color.a >= 1) {
      return false;
    }
  }

  return true;
}

/**
 * Recolour a scrim, keeping every stop's alpha and the gradient's geometry.
 *
 * A dark scrim is already what dark mode wants — a black fade over a photo
 * reads the same on a dark page — so it is returned untouched, and the caller
 * treats an unchanged value as "leave this element alone" rather than writing
 * an inline copy of what the design already said.
 *
 * A light scrim (a white fade meant to lift text off a bright photo) is the
 * case that has to move: kept as-is it is a bright band across a dark page.
 * It is flipped to the dark end of its own hue at the same alpha, so it still
 * fades over whatever is beneath instead of covering it.
 */
function darkify_scrim_gradient(image) {
  return image.replace(DARKIFY_COLOR_TOKEN, function (token) {
    var color = darkify_parse_color(token);
    if (!color || color.a === 0) {
      return token;
    }

    var hsl = darkify_rgb_to_hsl(color);
    if (hsl.l <= 0.5) {
      return token;
    }

    return darkify_hsl_to_css({
      h: hsl.h,
      s: Math.min(hsl.s, 0.4),
      l: Math.max(0.04, Math.min(0.22, 1 - hsl.l)),
      a: color.a,
    });
  });
}

/**
 * Recolour a gradient background onto the preset — its own direction and
 * shape kept, its colours replaced by two fixed preset tones — so a gradient
 * hero and a solid hero read as the same design again instead of one staying
 * a bright band next to a properly darkened section. Falls back to a flat
 * preset background if the gradient's syntax can't be parsed (a `url()`
 * layer mixed in, multiple backgrounds) rather than leaving it untouched.
 *
 * `data-darkify-gradient-src` is captured once, in
 * darkify_process_element_settled(), before the class-based repaint's
 * `background` shorthand can reset `background-image` to `none` — without it
 * a toggle (which re-visits an already-repainted element) could never recover
 * the original gradient to recolour, or tell a handled one apart from a page
 * that never had one.
 */
function darkify_process_gradient(element) {
  if (darkify_is_dark()) {
    var source = element.dataset.darkifyGradientSrc;
    if (!source) {
      return;
    }
    if (element.classList.contains("darkify_gradient_flattened")) {
      return;
    }

    // A scrim is an overlay over content, not a surface of its own: flattening
    // it onto opaque preset tones would hide the photo or pattern it was drawn
    // on top of. See darkify_is_scrim_gradient().
    if (darkify_is_scrim_gradient(source)) {
      var scrim = darkify_scrim_gradient(source);
      if (scrim === source) {
        return;
      }
      darkify_store_inline(element, "darkifyGradientPrev", [
        "background-image",
        "background-color",
      ]);
      element.classList.add("darkify_gradient_flattened");
      element.style.setProperty("background-image", scrim, "important");
      return;
    }

    darkify_store_inline(element, "darkifyGradientPrev", [
      "background-image",
      "background-color",
    ]);
    element.classList.add("darkify_gradient_flattened");

    var recoloured = darkify_preset_gradient(source);
    if (recoloured) {
      element.style.setProperty("background-image", recoloured, "important");
      return;
    }

    // Fallback: couldn't parse it as a plain gradient function, so flatten to
    // a solid preset background instead of leaving the light-mode gradient in
    // place.
    element.style.setProperty("background-image", "none", "important");
    var tokens = darkify_surface_tokens();
    if (tokens && tokens.raised) {
      element.style.setProperty(
        "background-color",
        darkify_hsl_to_css({
          h: tokens.raised.h,
          s: tokens.raised.s,
          l: tokens.raised.l,
          a: 1,
        }),
        "important",
      );
    }
  } else if (element.classList.contains("darkify_gradient_flattened")) {
    darkify_restore_inline(element, "darkifyGradientPrev", [
      "background-image",
      "background-color",
    ]);
    element.classList.remove("darkify_gradient_flattened");
  }
}

/**
 * Split a shadow value into its comma-separated layers.
 *
 * Commas inside `rgb()` / `rgba()` / `color-mix()` are not layer separators,
 * so the split has to track parenthesis depth rather than call
 * `value.split(",")`.
 */
function darkify_split_shadow_layers(value) {
  var layers = [];
  var depth = 0;
  var start = 0;

  for (var i = 0; i < value.length; i++) {
    var ch = value.charAt(i);
    if (ch === "(") {
      depth++;
    } else if (ch === ")") {
      depth--;
    } else if (ch === "," && depth === 0) {
      layers.push(value.slice(start, i));
      start = i + 1;
    }
  }
  layers.push(value.slice(start));

  return layers;
}

/**
 * Whether one shadow layer is a ring — a hairline drawn with `spread` rather
 * than with `border`.
 *
 * `box-shadow: 0 0 0 1px <colour>` is how modern designs draw a card outline:
 * no offset, no blur, a one-pixel spread. Neutralising it to black the same
 * way a cast shadow is neutralised would make it vanish — black on
 * near-black is nothing at all — so a ring is recoloured with the palette's
 * border token instead, recognised geometrically: the two offsets and the
 * blur are all zero and the spread is positive.
 */
function darkify_shadow_layer_is_ring(layer) {
  var lengths = layer
    .replace(DARKIFY_COLOR_TOKEN, " ")
    .replace(/#[0-9a-f]{3,8}\b/gi, " ")
    .replace(/\b(inset|none)\b/gi, " ")
    .trim()
    .split(/\s+/)
    .filter(function (part) {
      return part !== "";
    });

  if (lengths.length !== 4) {
    return false;
  }

  var values = lengths.map(parseFloat);
  for (var i = 0; i < values.length; i++) {
    if (isNaN(values[i])) {
      return false;
    }
  }

  return (
    values[0] === 0 && values[1] === 0 && values[2] === 0 && values[3] > 0
  );
}

/** Repaint a ring layer with the palette's border colour, same as a real border gets. */
function darkify_recolor_shadow_ring(layer) {
  var tokens = darkify_surface_tokens();
  if (!tokens || !tokens.border) {
    return null;
  }

  var replaced = false;
  var recolored = layer.replace(DARKIFY_COLOR_TOKEN, function (token) {
    var color = darkify_parse_color(token);
    if (!color || color.a === 0) {
      return token;
    }
    replaced = true;
    return darkify_hsl_to_css({
      h: tokens.border.h,
      s: tokens.border.s,
      l: tokens.border.l,
      a: 1,
    });
  });

  return replaced ? recolored : null;
}

/** Drop a shadow layer's hue but keep its geometry, so depth survives and glow doesn't. */
function darkify_neutralize_shadow_layer(value, prop) {
  if (prop === "box-shadow" && darkify_shadow_layer_is_ring(value)) {
    var ring = darkify_recolor_shadow_ring(value);
    if (ring) {
      return ring;
    }
  }

  return value.replace(DARKIFY_COLOR_TOKEN, function (token) {
    var color = darkify_parse_color(token);
    if (!color || color.a === 0) {
      return token;
    }

    var mapped = darkify_has_color_overrides
      ? darkify_override_for(color, "shadow")
      : "";
    if (mapped) {
      return mapped;
    }

    // A fully opaque shadow colour is a light-mode choice that would read as
    // a solid slab on a dark page, so it drops to a plausible ambient alpha.
    var alpha = color.a < 1 ? color.a : 0.45;
    return "rgba(0, 0, 0, " + Math.round(alpha * 100) / 100 + ")";
  });
}

function darkify_neutralize_shadow(value, prop) {
  // Rings only exist on `box-shadow`; a text shadow has no spread to draw one.
  if (prop === "box-shadow" && value.indexOf(",") !== -1) {
    return darkify_split_shadow_layers(value)
      .map(function (layer) {
        return darkify_neutralize_shadow_layer(layer, prop);
      })
      .join(",");
  }

  return darkify_neutralize_shadow_layer(value, prop);
}

/**
 * Take the light-mode colour out of `box-shadow` / `text-shadow`, always — a
 * tinted shadow is the one thing that keeps announcing the light design after
 * everything else has gone dark, sitting on top of the dimmed surface and
 * undoing it. There is no "off" switch: neutralising keeps the shadow's
 * geometry, so a card keeps its depth, and only ever drops the colour that
 * made it glow.
 */
function darkify_process_shadow(element) {
  if (darkify_is_dark()) {
    if (element.classList.contains("darkify_shadow_neutralized")) {
      return;
    }

    var style = window.getComputedStyle(element, null);
    var values = {};
    var found = false;

    DARKIFY_SHADOW_PROPS.forEach(function (prop) {
      var value = style.getPropertyValue(prop);
      if (!value || value === "none") {
        return;
      }
      found = true;
      values[prop] = darkify_neutralize_shadow(value, prop);
    });

    if (!found) {
      return;
    }

    darkify_store_inline(element, "darkifyShadowPrev", DARKIFY_SHADOW_PROPS);
    element.classList.add("darkify_shadow_neutralized");

    Object.keys(values).forEach(function (prop) {
      element.style.setProperty(prop, values[prop], "important");
    });
  } else if (element.classList.contains("darkify_shadow_neutralized")) {
    darkify_restore_inline(element, "darkifyShadowPrev", DARKIFY_SHADOW_PROPS);
    element.classList.remove("darkify_shadow_neutralized");
  }
}

/**
 * Give an icon's own colour a dark-mode depth via darkify_transform_color(),
 * the same hue-preserving ramp gradients and background-image icons already
 * go through — a brand-coloured icon (a gold star rating, a blue logo mark)
 * keeps its hue and only has its lightness/chroma pulled to a level that
 * reads on a dark backdrop, while a near-black/grey glyph (the common case:
 * an SVG `plus`/`minus` or chevron drawn at light-mode ink) is neutral enough
 * to land on the palette's text token instead. Forcing every icon onto the
 * flat text token regardless of its own hue was tried and reverted: it made
 * every accent-coloured icon on the page — star ratings included — read as
 * the same shade of grey as body text, which is not what "dark mode" means
 * for a deliberately branded colour. `color` is checked on both icon fonts
 * (`<i>`) and inline SVG, since an SVG shape without its own `fill` paints
 * from `currentColor`; `fill`/`stroke` are only checked where they can mean
 * something.
 *
 * `color` specifically has to be read carefully: by the time this runs, the
 * class-based repaint above has already stamped `darkify_style_txt` onto
 * this same element and repainted its `color` via CSS — `fill`/`stroke`
 * aren't `color`, so the class-based rules never touch them, but `color`
 * itself is already the fallback token, not the design's own value. Where
 * `data-darkify_preserved_color` exists (it does by default — see
 * darkify_process_element_settled()), that's the element's real original
 * colour captured before the class-based repaint touched it, and is used
 * instead of the live (already-repainted) computed value.
 */
function darkify_process_icon_color(element) {
  var nodeName = element.nodeName.toLowerCase();
  var isSvgPaint = darkify_is_svg_node(element) || nodeName === "svg";
  if (!isSvgPaint && nodeName !== "i") {
    return;
  }

  if (darkify_is_dark()) {
    if (element.classList.contains("darkify_icon_recoloured")) {
      return;
    }

    var style = window.getComputedStyle(element, null);
    var tokens = darkify_surface_tokens();
    if (!tokens || !tokens.text) {
      return;
    }

    var props;
    if (isSvgPaint && nodeName !== "svg") {
      // An inner shape (`<path>`, `<circle>`, ...) almost never states its
      // own fill/stroke — it inherits from the `<svg>` root, which is always
      // walked first and is what actually carries the icon's colour. Reading
      // an inherited fill/stroke here would read whatever the root was just
      // correctly recoloured to, then try to match THAT against override
      // rules meant for the *original* colour — fails, and silently
      // overwrites a correct inherited value with the fallback token. Only a
      // shape that states its own fill/stroke gets treated independently.
      props = ["color"];
      if (element.getAttribute("fill") || element.style.getPropertyValue("fill")) {
        props.push("fill");
      }
      if (element.getAttribute("stroke") || element.style.getPropertyValue("stroke")) {
        props.push("stroke");
      }
    } else {
      props = isSvgPaint ? ["color", "fill", "stroke"] : ["color"];
    }
    var next = {};
    var changed = false;
    var liveColor = style.getPropertyValue("color");

    props.forEach(function (prop) {
      // `fill`/`stroke` written as `currentColor` — the normal way an icon
      // SVG paints itself — don't carry an independent value at all: reading
      // them here returns whatever `color` currently resolves to, live. If
      // that's this same already-repainted `color`, this property is a
      // second name for the same problem and gets the same fix; if it
      // differs, it's a real colour of its own — `fill`/`stroke` are never
      // touched by the class-based repaint, so the live read is already the
      // design's own value.
      var tracksColor = prop !== "color" && style.getPropertyValue(prop) === liveColor;
      var raw =
        (prop === "color" || tracksColor) && element.dataset.darkify_preserved_color
          ? element.dataset.darkify_preserved_color
          : style.getPropertyValue(prop);
      var color = darkify_parse_color(raw);
      if (!color || color.a === 0) {
        return;
      }

      // An admin-configured override is an explicit instruction about this
      // exact colour, so it is resolved BEFORE the contrast bail below. An
      // icon colour that already reads clearly against its own backdrop —
      // Elementor's default #6ec1e4 on a dark page, say — never needs the
      // automatic recolour, and gating the override behind that same check
      // meant a pinned "Icons only" colour silently did nothing on precisely
      // the icons whose colour the design got right.
      var mapped = darkify_has_color_overrides
        ? darkify_override_for(color, "icon")
        : "";

      if (!mapped) {
        // Judge the icon against what's actually behind it, not the page's
        // general dark backdrop — see darkify_nearest_opaque_background(). A
        // colour that already reads clearly in its own real context is left
        // exactly as designed; recolouring it here would be undoing a contrast
        // the design already got right.
        var backdrop = darkify_nearest_opaque_background(element);
        if (backdrop) {
          var ratio = darkify_contrast_ratio(
            darkify_relative_luminance(color),
            darkify_relative_luminance(backdrop),
          );
          if (ratio >= DARKIFY_CONTROL_MIN_CONTRAST) {
            return;
          }
        }

        // neutrals:true because this property already has a concrete colour
        // to restore on toggle-back (darkifyIconPrev, below) — unlike the
        // gradient-stop/background-image callers of this same function, an
        // icon with no colour of its own never reaches this branch, so there
        // is no bare palette to protect by leaving greys alone.
        mapped = darkify_transform_color(raw, "icon", { neutrals: true });
      }
      next[prop] =
        mapped ||
        darkify_hsl_to_css({
          h: tokens.text.h,
          s: tokens.text.s,
          l: tokens.text.l,
          a: color.a,
        });
      changed = true;
    });

    if (!changed) {
      return;
    }

    darkify_store_inline(element, "darkifyIconPrev", ["color", "fill", "stroke"]);
    element.classList.add("darkify_icon_recoloured");
    Object.keys(next).forEach(function (prop) {
      element.style.setProperty(prop, next[prop], "important");
    });
  } else if (element.classList.contains("darkify_icon_recoloured")) {
    darkify_restore_inline(element, "darkifyIconPrev", ["color", "fill", "stroke"]);
    element.classList.remove("darkify_icon_recoloured");
  }
}

/** Class-based background repaint's own family — see darkify_process_overlay_background(). */
var DARKIFY_BG_CLASS_FAMILY = [
  "darkify_style_all",
  "darkify_style_bg_txt",
  "darkify_style_bg_border",
  "darkify_style_bg",
  "darkify_style_secondary_bg",
];

/**
 * Flatten a plain solid background colour for the one class of element the
 * class-based repaint deliberately never reaches: a builder's own overlay div
 * (Elementor's `.elementor-background-overlay` and similar), sitting on top
 * of a background image. Nothing else darkens these — they're excluded from
 * the class-based system specifically so the real picture underneath doesn't
 * get painted over — so without this they keep their full light-mode colour
 * forever, opacity and all.
 *
 * Self-gating on `DARKIFY_BG_CLASS_FAMILY`: any element the class-based
 * repaint already painted a background on is skipped, so this only ever does
 * something for the elements nothing else reaches.
 *
 * That self-gating isn't enough on its own, though: plenty of real, content-
 * bearing elements never pick up a `darkify_style_*` class either — a
 * countdown timer's number box, a page-builder button — because whatever
 * classified the page didn't recognise them as "background+text", not
 * because they're meant to be skipped. An Elementor overlay div is always
 * empty — its whole job is sitting over a photo, with nothing of its own to
 * read — so `textContent` is empty too. A button labelled "Grab The Deal" or
 * a box showing "01" is not, and flattening its background without a paired
 * foreground adjustment is how a white button with dark text and a dark
 * button with dark text end up pixel-for-pixel the same colour: invisible.
 * Skipping anything with real text keeps this writer aimed at the empty
 * overlay divs it was written for.
 */
function darkify_process_overlay_background(element) {
  if (
    DARKIFY_BG_CLASS_FAMILY.some(function (cls) {
      return element.classList.contains(cls);
    })
  ) {
    return;
  }

  if (element.textContent && element.textContent.trim()) {
    return;
  }

  if (darkify_is_dark()) {
    if (element.classList.contains("darkify_overlay_flattened")) {
      return;
    }

    var style = window.getComputedStyle(element, null);
    if (style.backgroundImage && style.backgroundImage !== "none") {
      // A gradient here belongs to darkify_process_gradient(); a photo is the
      // whole reason this element is excluded from the class-based repaint in
      // the first place. Either way, this writer stays off `background-image`.
      return;
    }

    var color = darkify_parse_color(style.backgroundColor);
    if (!color || color.a === 0) {
      return;
    }

    var target = darkify_has_color_overrides
      ? darkify_override_for(color, "background")
      : "";
    if (!target) {
      var tokens = darkify_surface_tokens();
      if (!tokens || !tokens.raised) {
        return;
      }
      target = darkify_hsl_to_css({
        h: tokens.raised.h,
        s: tokens.raised.s,
        l: tokens.raised.l,
        a: color.a,
      });
    }

    darkify_store_inline(element, "darkifyOverlayPrev", ["background-color"]);
    element.classList.add("darkify_overlay_flattened");
    element.style.setProperty("background-color", target, "important");
  } else if (element.classList.contains("darkify_overlay_flattened")) {
    darkify_restore_inline(element, "darkifyOverlayPrev", ["background-color"]);
    element.classList.remove("darkify_overlay_flattened");
  }
}

/**
 * The five writers above, in the order a single element needs them. Wrapped
 * so every call site — the main pass, the disallowed-elements branch, and
 * the dark/light state sweep — runs them identically.
 *
 * Excluded elements are checked once here rather than in each writer: the
 * switcher and anything marked `darkify_ignore` carry their own colours by
 * design, and none of these writers has a selector to hide behind the way the
 * class-based rules do.
 *
 * Marks the element `data-darkify-tracked` so the state sweep's second pass
 * can find it again. This can't rely on `.darkify_processed`: a builder's own
 * overlay div (`.elementor-background-overlay` and friends) is deliberately
 * never marked `darkify_processed` — that's what keeps the class-based
 * repaint off it — so without a marker of its own, the *only* time it would
 * ever be revisited is the pass that first walked it. Toggle dark mode on a
 * page that loaded light and a gradient overlay like this would be painted
 * once and never touched again.
 */
function darkify_process_deterministic_fixes(element) {
  if (darkify_is_excluded_from_adaptation(element)) {
    return;
  }
  if (!element.dataset.darkifyTracked) {
    element.dataset.darkifyTracked = "1";
  }
  darkify_process_gradient(element);
  darkify_process_shadow(element);
  darkify_process_icon_color(element);
  darkify_process_overlay_background(element);
  darkify_process_color_overrides(element);
}

darkify_init_keyboard_shortcut_listener();
darkify_init_os_mode_change_listener();
darkify_init_switch_a11y();

/*
 * Every mutation batch used to trigger a full-document walk of its own. While
 * the browser is still parsing the page that fires constantly, and each pass
 * re-queries the whole document — so the work needed to darken the page was
 * competing with the parsing that produces it, and the page stayed visibly
 * light for longer the bigger it was. Coalescing to one pass per frame keeps
 * every node covered (the walk skips `.darkify_processed`, so a later pass
 * picks up whatever the last one missed) at a fraction of the cost.
 */
let darkify_walk_scheduled = false;

/* --------------------------------------------------------------------------
   Incremental walking.

   The childList observer used to answer every DOM mutation with a full
   `document.querySelectorAll("*")` walk. The `.darkify_processed` exclusion in
   that selector meant each element was only *styled* once, but the query itself
   — matching a universal selector against the entire document — was paid in
   full on every frame in which anything changed. On pages that mutate
   continuously (infinite scroll, carousels, WooCommerce fragment refreshes,
   anything React-rendered) that is a whole-document match every 16ms, forever,
   and it scales with page size.

   Added nodes are the only thing a childList mutation can introduce that needs
   processing, so they are collected as roots and only those subtrees are
   walked. The first pass is still a full walk — that is the page's initial
   state, and there is no subtree to narrow it to.
   -------------------------------------------------------------------------- */

/** Subtree roots inserted since the last walk. */
const darkify_pending_roots = new Set();

/** Forces the next walk to cover the whole document (used for the first pass). */
let darkify_full_walk_needed = true;

/**
 * Selector for "an element the engine may style", shared by both walk paths.
 *
 * `html` is in the exclusion list to match the previous behaviour exactly. The
 * old selector was written `"* :not(...)"` — with a descendant combinator — so
 * it could only ever match elements that have a parent, silently excluding the
 * root element. Naming it here keeps that exclusion while letting the selector
 * be a plain compound one, which is also what makes it valid to run against a
 * subtree root in `darkify_walk_roots()`.
 */
const DARKIFY_WALK_SELECTOR =
  "*:not(html, head, title, link, meta, script, style, defs, filter, .darkify_processed)";

function darkify_walk_roots(roots) {
  darkify_debug_count("incremental_walks");
  darkify_debug_time("walk_ms", function () {
  darkify_suspend_class_watch(function () {
    roots.forEach(function (root) {
      if (!root.isConnected) {
        return;
      }
      // The root itself is a new node too, not just its descendants —
      // `querySelectorAll` below only reaches its descendants.
      //
      // Matched with a plain `matches()` guarded by try/catch rather than the
      // engine's pseudo-element-stripping helper: DARKIFY_WALK_SELECTOR is a
      // fixed compound selector with no pseudo-elements in it, so there is
      // nothing to strip, and the helper does not exist in every build of this
      // engine.
      if (root.nodeType === 1 && !root.classList.contains("darkify_processed")) {
        var walkable = false;
        try {
          walkable = root.matches(DARKIFY_WALK_SELECTOR);
        } catch (e) {
          walkable = false;
        }
        if (walkable) {
          darkify_process_element(root);
        }
      }
      if (root.querySelectorAll) {
        root.querySelectorAll(DARKIFY_WALK_SELECTOR).forEach(function (element) {
          darkify_process_element(element);
        });
      }

      // A subtree can be detached and later re-inserted wholesale — a chat
      // widget reopening its own greeting card, a cookie banner re-showing
      // itself — which fires the same "added" mutation a brand new node
      // would. `.darkify_processed` above skips it on the assumption that
      // already-processed means already-correct, which class-based styling
      // guarantees (it only ever applies under `.darkify_dark_mode_enabled`)
      // but these writers' inline `!important` overrides do not: those are
      // written once and only undone by the state sweep that follows a
      // dark<->light toggle, so a node detached at that exact moment keeps
      // whichever mode's styling it last had, indefinitely, once reattached.
      // Re-running the same idempotent writers the sweep uses catches it the
      // moment it reappears instead.
      darkify_resync_reattached_deterministic_fixes(root);
    });
  });
  });

  darkify_finish_painting();
}

/** Selector for anything the deterministic writers have touched inline. */
const DARKIFY_DETERMINISTIC_MARKER_SELECTOR =
  ".darkify_gradient_flattened, .darkify_shadow_neutralized, .darkify_icon_recoloured, .darkify_overlay_flattened, .darkify_color_overridden";

function darkify_resync_reattached_deterministic_fixes(root) {
  if (root.nodeType !== 1) {
    return;
  }
  var marked = false;
  try {
    marked = root.matches(DARKIFY_DETERMINISTIC_MARKER_SELECTOR);
  } catch (e) {
    marked = false;
  }
  if (marked) {
    darkify_process_deterministic_fixes(root);
  }
  if (root.querySelectorAll) {
    root
      .querySelectorAll(DARKIFY_DETERMINISTIC_MARKER_SELECTOR)
      .forEach(function (element) {
        darkify_process_deterministic_fixes(element);
      });
  }
}

function darkify_schedule_walk() {
  if (darkify_walk_scheduled) {
    return;
  }
  darkify_walk_scheduled = true;

  var run = function () {
    darkify_walk_scheduled = false;

    if (darkify_full_walk_needed) {
      darkify_full_walk_needed = false;
      darkify_pending_roots.clear();
      darkify_init_processes();
    } else if (darkify_pending_roots.size > 0) {
      var roots = Array.from(darkify_pending_roots);
      darkify_pending_roots.clear();
      darkify_walk_roots(roots);
    }

    darkify_process_iframes(); // ✅ darkify proceed iframe
  };

  if (typeof requestAnimationFrame === "function") {
    requestAnimationFrame(run);
  } else {
    setTimeout(run, 0);
  }
}

const darkify_observer = new MutationObserver(function (mutationsList) {
  darkify_debug_count("observer_callbacks");
  var has_added = false;

  for (var i = 0; i < mutationsList.length; i++) {
    var added = mutationsList[i].addedNodes;
    for (var j = 0; j < added.length; j++) {
      var node = added[j];
      if (node.nodeType !== 1) {
        continue;
      }
      // Engine-generated nodes carry no design of their own to read, and
      // queueing them here is how an observer ends up feeding itself.
      if (
        node.classList &&
        (node.classList.contains("darkify_switch") ||
          node.classList.contains("darkify_ignore"))
      ) {
        continue;
      }
      if (!darkify_full_walk_needed) {
        darkify_pending_roots.add(node);
        darkify_debug_count("nodes_queued");
      }
      has_added = true;
    }
  }

  if (has_added || darkify_full_walk_needed) {
    darkify_schedule_walk();
  }
});

/* ==========================================================================
   Class-change watching — one delegated observer for the whole document
   --------------------------------------------------------------------------
   A processed element whose class list changes may have been re-styled by the
   theme (`.is-active`, `.is-open`, a builder's scroll state), so it has to be
   re-classified against its new colours. That requirement is unchanged; how it
   is watched is what changed here.

   The previous implementation registered this observer on every element
   individually, and — because a re-process necessarily writes classes, which
   wakes the observer again — it defended itself by disconnecting and then
   re-registering across `document.querySelectorAll("*")` inside its own
   callback. Every single class mutation anywhere on the page therefore cost one
   full-document query plus one `observe()` call per element, and each pass
   wrote ~16 classes per element, so the registrations multiplied against each
   other. Measured on a 2,929-element page: 1.23 million `observe()` calls
   during load and 6.31 million on a single dark-mode toggle. That is the freeze
   the reviews describe; it grows with the square of the page size, which is why
   it is survivable on a small theme demo and fatal on a real builder page.

   A subtree observer on `document` sees exactly the same mutations for one
   `observe()` call total, and never needs re-registering as the DOM changes —
   new nodes are covered the moment they are inserted. Self-inflicted mutations
   are suppressed by depth counter rather than by disconnecting, so no external
   mutation can slip through a window where the observer was off. Targets are
   coalesced into a Set and drained once per frame, so an element whose classes
   change ten times in one frame is re-processed once.
   ========================================================================== */

/** Depth of the current engine-owned write. >0 means "ignore what we see". */
let darkify_class_watch_depth = 0;

/** Targets awaiting re-classification, deduplicated. */
const darkify_class_dirty = new Set();
let darkify_class_drain_scheduled = false;

/**
 * Run `fn` with class-change watching suppressed.
 *
 * The engine stamps `darkify_style_*`, `darkify_processed` and friends as its
 * normal operation. Those writes must not feed back into the watcher, or every
 * pass would re-queue everything it just touched and never settle. Records
 * accumulated during the write are dropped on the way out — `takeRecords()`
 * empties the queue without invoking the callback — so only genuinely external
 * changes survive to be acted on.
 *
 * `whole_document` decides how far the transition suppression that comes with a
 * pass reaches, and it is off by default. Document-wide suppression cancels
 * every transition running anywhere on the page, so it may only be used by the
 * two passes that genuinely read the whole document — the first walk and the
 * dark-mode state sweep — where nothing else is animating anyway. The
 * incremental passes run constantly (a menu opening, a slider advancing, any
 * theme class flip wakes them), and suppressing document-wide for those killed
 * animations on elements the pass never even looked at: the hover effect two
 * sections away snapped instead of easing. Those passes suppress per element
 * instead, in darkify_process_element().
 */
let darkify_pass_is_whole_document = false;

function darkify_suspend_class_watch(fn, whole_document) {
  // Entering the outermost engine write is also the start of a "pass", so this
  // is where transitions are suppressed for its duration — see
  // darkify_begin_pass() for why that is done once here rather than per element.
  let owns_pass = false;
  if (darkify_class_watch_depth === 0) {
    darkify_pass_is_whole_document = whole_document === true;
    if (darkify_pass_is_whole_document) {
      owns_pass = true;
      darkify_begin_pass();
    }
  }
  darkify_class_watch_depth++;
  try {
    return fn();
  } finally {
    darkify_class_watch_depth--;
    if (darkify_class_watch_depth === 0) {
      elements_class_changed.takeRecords();
      if (owns_pass) {
        darkify_end_pass();
      }
      darkify_pass_is_whole_document = false;
    }
  }
}

function darkify_drain_class_changes() {
  darkify_class_drain_scheduled = false;
  darkify_debug_count("class_redrives");

  if (darkify_class_dirty.size === 0) {
    return;
  }

  const targets = Array.from(darkify_class_dirty);
  darkify_class_dirty.clear();

  darkify_suspend_class_watch(function () {
    for (let i = 0; i < targets.length; i++) {
      const target = targets[i];

      // Dropped from the document between queueing and draining.
      if (!target.isConnected) {
        continue;
      }

      // Re-checked at drain time, not at queue time: a class may have been
      // added and removed again within the same frame, leaving the element
      // exactly as the last pass classified it. Comparing the settled value
      // skips the re-process entirely in that case.
      const now = target.classList.toString();
      if (target.dataset.darkify_preserved_classes === now) {
        continue;
      }

      target.classList.remove("darkify_processed");
      darkify_debug_count("elements_reprocessed");
      // Re-read of an already-coloured element: leave its transitions alone so
      // the animation that triggered this (accordion, dropdown, hover) eases.
      darkify_process_element(target, true);
      target.dataset.darkify_preserved_classes = target.classList.toString();
    }
  });
}

const elements_class_changed = new MutationObserver((mutationsList) => {
  if (darkify_class_watch_depth > 0 || document.readyState === "loading") {
    return;
  }

  for (let i = 0; i < mutationsList.length; i++) {
    const target = mutationsList[i].target;

    if (
      target.nodeType !== 1 ||
      !target.classList.contains("darkify_processed")
    ) {
      continue;
    }

    // Cheap reject before taking on any work: an element whose settled class
    // string still matches what the last pass recorded has nothing to
    // re-classify. This is the common case during hover and scroll churn.
    if (target.dataset.darkify_preserved_classes === target.classList.toString()) {
      continue;
    }

    darkify_class_dirty.add(target);
  }

  if (darkify_class_dirty.size > 0 && !darkify_class_drain_scheduled) {
    darkify_class_drain_scheduled = true;
    if (typeof requestAnimationFrame === "function") {
      requestAnimationFrame(darkify_drain_class_changes);
    } else {
      setTimeout(darkify_drain_class_changes, 0);
    }
  }
});

/**
 * Re-run the state-dependent writers across the page after dark mode flips.
 *
 * Everything the engine does through classes flips for free — the rules are all
 * scoped under `.darkify_dark_mode_enabled`, so the cascade does the work. What
 * this sweep exists for is the writers that produce *inline* declarations
 * (the adaptive colour layer, image filters, the background-image darkener,
 * translucency): those aren't gated on the state class and have to be applied
 * and undone by hand as it changes.
 *
 * Three things changed here, none of them to what the sweep does:
 *
 *   * it selects `.darkify_processed` directly instead of walking every element
 *     in the document and testing each one for that class;
 *   * it reads each element's computed style once and passes it down, rather
 *     than calling `getComputedStyle` twice per element for the icon check;
 *   * it is called once per settled state change rather than once per mutation
 *     record (see the observer below).
 */
/**
 * Repaint everything a palette change touches, without reloading the page.
 *
 * Changing a preset rewrites the `--darkify_dark_mode_*` variables, and every
 * rule written in terms of them follows instantly. Two groups do not:
 *
 *   The state sweep's writers — image brightness, icon backgrounds, inline SVG —
 *   which set inline values that contain no variable to re-resolve.
 *
 *   The deterministic writers — gradients, shadows, overlays — which additionally
 *   refuse to run twice. darkify_process_gradient() returns immediately when it
 *   finds its own `darkify_gradient_flattened` marker, so a second pass changes
 *   nothing at all. That guard is right during a normal walk, where re-running
 *   would read an already-flattened background as if it were the source; it is
 *   wrong here, where the source is exactly what we want re-read against new
 *   colours. Clearing the markers first is what lets those writers work from
 *   `dataset.darkifyGradientSrc` again, and darkify_store_inline() already
 *   refuses to overwrite a saved original, so the light-mode restore stays
 *   intact.
 *
 * Called by the Live Preview when a preset or an individual colour changes. It
 * is why switching preset used to leave a hero gradient on the previous
 * palette's colours until the frame was reloaded.
 */
function darkify_repaint_palette() {
  if (!darkify_is_dark()) {
    return;
  }

  var markers = [
    "darkify_gradient_flattened",
    "darkify_shadow_neutralized",
    "darkify_icon_recoloured",
    "darkify_overlay_flattened",
    "darkify_color_overridden",
  ];

  darkify_suspend_class_watch(function () {
    document
      .querySelectorAll(DARKIFY_DETERMINISTIC_MARKER_SELECTOR)
      .forEach(function (element) {
        for (var i = 0; i < markers.length; i++) {
          element.classList.remove(markers[i]);
        }
        darkify_process_deterministic_fixes(element);
      });
  });

  darkify_state_sweep();
}

function darkify_state_sweep() {
  darkify_debug_count("state_sweeps");
  darkify_debug_time("sweep_ms", function () {
  darkify_suspend_class_watch(function () {
    {
      document
        .querySelectorAll(".darkify_processed")
        .forEach((element) => {
          {
            if (
              darkify_disallowed_elements.length > 0 &&
              darkify_safe_matches(element, darkify_disallowed_elements)
            ) {
              return;
            }
            // Mirrors the branch in darkify_process_element(): an inline SVG
            // is an icon and must not be washed. Without this the sweep undoes
            // the classification's decision — it re-runs the darkener on a
            // chevron, and even where the recoloured icon still wins on
            // `background-image`, the `background-size: 100% 100%` the wash
            // brings with it stays behind and stretches the glyph across the
            // whole control.
            var darkify_sweep_style = window.getComputedStyle(element, null);
            var darkify_sweep_bg = darkify_sweep_style.backgroundImage;
            if (
              darkify_sweep_bg &&
              darkify_sweep_bg.indexOf("data:image/svg+xml") !== -1
            ) {
              darkify_process_icon_background(element, darkify_sweep_style);
            } else if (darkify_enable_bg_image_darken === "1") {
              darkify_darken_bg_image(element, darken_level);
            }
            if (
              (darkify_enable_low_image_brightness === "1" ||
                darkify_enable_image_grayscale === "1") &&
              element.nodeName.toLowerCase() === "img"
            ) {
              darkify_img_brightness_and_grayscale(element);
            }
            if (
              darkify_enable_invert_inline_svg === "1" &&
              element.nodeName.toLowerCase() === "svg"
            ) {
              darkify_invert_inline_svg(element);
            }
            if (
              darkify_enable_low_video_brightness === "1" ||
              darkify_enable_video_grayscale === "1"
            ) {
              if (element.nodeName.toLowerCase() === "video") {
                darkify_video_brightness_and_grayscale(element);
              }
              if (
                element.nodeName.toLowerCase() === "iframe" &&
                element.getAttribute("src") != null
              ) {
                const srcAttribute = element.getAttribute("src");
                if (
                  srcAttribute.includes("youtube") ||
                  srcAttribute.includes("vimeo") ||
                  srcAttribute.includes("dailymotion")
                ) {
                  darkify_video_brightness_and_grayscale(element);
                }
              }
            }
            if (element.hasAttribute("data-darkify_alpha_bg")) {
              darkify_fix_background_color_alpha(element);
            }
            // Unlike the class-based rules, these writers' inline
            // declarations aren't gated on `darkify_dark_mode_enabled` — they
            // have to be applied and undone by hand as the state flips.
            darkify_process_deterministic_fixes(element);
          }
        });

      // Second pass for anything the writers above touched that the loop
      // over `.darkify_processed` cannot see, because it never became
      // `darkify_processed` — builder overlays, most of all. Re-visiting an
      // element already handled above is harmless: every writer guards on
      // its own marker.
      document
        .querySelectorAll("[data-darkify-tracked]")
        .forEach(function (element) {
          darkify_process_deterministic_fixes(element);
        });
    }
  }, true);
  });
}

/* --------------------------------------------------------------------------
   The state observer itself.

   Previously this watched `<html>` with a bare `{ attributes: true }` and ran
   the full sweep once per mutation record. Neither part was safe on a real
   site. Unfiltered means every attribute anything writes to `<html>` wakes it —
   and `<html>` is the busiest element on a modern page: scroll-lock libraries,
   smooth-scroll scripts, builders and cookie banners all write `style` or
   toggle helper classes there, sometimes per scroll frame. Per-record means a
   script that flips two classes in one tick paid for two full sweeps.

   Now: the filter narrows it to `class`, and the dark state is compared against
   the last one acted on, so the sweep runs only when dark mode has genuinely
   changed — a theme adding `.scrolled` to `<html>` costs one string comparison
   instead of a full-page repaint. Coalescing onto a frame collapses a burst of
   class writes into a single sweep.
   -------------------------------------------------------------------------- */

let darkify_last_swept_state = null;
let darkify_state_sweep_scheduled = false;

const dark_mode_status_changed = new MutationObserver(() => {
  const is_dark = document.documentElement.classList.contains(
    "darkify_dark_mode_enabled",
  );

  if (is_dark === darkify_last_swept_state) {
    return;
  }
  darkify_last_swept_state = is_dark;

  if (darkify_state_sweep_scheduled) {
    return;
  }
  darkify_state_sweep_scheduled = true;

  const run = function () {
    darkify_state_sweep_scheduled = false;
    darkify_state_sweep();
  };

  if (typeof requestAnimationFrame === "function") {
    requestAnimationFrame(run);
  } else {
    setTimeout(run, 0);
  }
});

function darkify_change_state() {
  if (darkify_is_this_admin_panel === "1") {
    localStorage.darkify_admin_panel_last_state = document
      .getElementsByTagName("html")[0]
      .classList.contains("darkify_dark_mode_enabled")
      ? "1"
      : "0";
  } else {
    localStorage.darkify_last_state = document
      .getElementsByTagName("html")[0]
      .classList.contains("darkify_dark_mode_enabled")
      ? "1"
      : "0";
  }
}

function darkify_switch_trigger() {
  if (!has_process_run_at_least_once) {
    darkify_init_processes();
    darkify_init_observer();
  }

  const htmlElement = document.getElementsByTagName("html")[0];

  if (htmlElement.classList.contains("darkify_dark_mode_enabled")) {
    htmlElement.classList.remove("darkify_dark_mode_enabled");
  } else {
    htmlElement.classList.add("darkify_dark_mode_enabled");
  }

  darkify_change_state();

  darkify_process_iframes(); // ✅ darkify proceed iframe
}

function darkify_theme_select(theme) {
  if (!darkify_is_block_editor_context()) return;
  if (!has_process_run_at_least_once) {
    darkify_init_processes();
    darkify_init_observer();
  }

  const htmlElement = document.documentElement;

  // ✅ Remove all previous theme classes (IMPORTANT)
  htmlElement.classList.forEach((cls) => {
    if (cls.startsWith("darkify-")) {
      htmlElement.classList.remove(cls);
    }
  });

  // ✅ Save in localStorage
  localStorage.darkify_selected_theme = theme;

  // ✅ Update all select dropdowns
  darkify_update_theme_selectors(theme);

  if (darkify_is_this_admin_panel === "1") {
    if (localStorage.darkify_admin_panel_last_state === "1") {
      htmlElement.classList.add("darkify_dark_mode_enabled");
      htmlElement.classList.add("darkify-" + theme);
    } else {
      htmlElement.classList.remove("darkify_dark_mode_enabled");
    }
  }

  darkify_change_state();

  // Apply the new palette to the parent first, then sync iframes so they pick
  // up the updated variables rather than the previous palette.
  darkify_apply_palette(theme);

  darkify_process_iframes();
}

document.addEventListener("DOMContentLoaded", function () {
  if (!darkify_is_block_editor_context()) return;

  let theme = localStorage.getItem("darkify_selected_theme") || "set1";

  darkify_theme_select(theme);
});

function darkify_update_theme_selectors(theme) {
  document
    .querySelectorAll(".darkify-theme-selector")
    .forEach(function (select) {
      if (select.value !== theme) {
        select.value = theme;
      }
    });
}

function darkify_is_block_editor_context() {
  return (
    typeof document !== "undefined" &&
    document.body &&
    (document.body.classList.contains("block-editor-page") ||
      document.querySelector(".edit-post-visual-editor") !== null ||
      document.querySelector(".block-editor") !== null)
  );
}

function darkify_restore_selected_theme() {
  if (!darkify_is_block_editor_context()) return;
  const storedTheme = localStorage.darkify_selected_theme;

  if (!storedTheme) return;

  darkify_update_theme_selectors(storedTheme);
  darkify_theme_select(storedTheme);
}

function darkify_apply_palette(theme) {
  if (!darkify_is_block_editor_context()) return;
  const palettes = {
    set1: {
      bg: "#0F0F0F",
      secondary_bg: "#171717",
      text_color: "#BEBEBE",
      link_color: "#E7E7E7",
      link_hover_color: "#BEBEBE",
      input_bg: "#2D2D2D",
      input_text_color: "#BEBEBE",
      input_placeholder_color: "#BEBEBE",
      border_color: "#4A4A4A",
      btn_text_color: "#BEBEBE",
      btn_bg: "#4A4A4A",
      btn_text_hover_color: "#BEBEBE",
      btn_hover_bg: "#2D2D2D",
      btn_border_color: "#4A4A4A",
      btn_hover_border_color: "#2D2D2D",
    },
    set3: {
      bg: "#211e3c",
      secondary_bg: "#302C57",
      text_color: "#B1BBD8",
      link_color: "#8071fb",
      link_hover_color: "#B1BBD8",
      input_bg: "#2A264D",
      input_text_color: "#B1BBD8",
      input_placeholder_color: "#B1BBD8",
      border_color: "#4E478D",
      btn_text_color: "#B1BBD8",
      btn_bg: "#4E478D",
      btn_text_hover_color: "#B1BBD8",
      btn_hover_bg: "#2A264D",
      btn_border_color: "#4E478D",
      btn_hover_border_color: "#2A264D",
    },

    set6: {
      bg: "#082032",
      secondary_bg: "#061825",
      text_color: "#B5D9F3",
      link_color: "#61bbff",
      link_hover_color: "#B5D9F3",
      input_bg: "#0E3755",
      input_text_color: "#B5D9F3",
      input_placeholder_color: "#B5D9F3",
      border_color: "#144E78",
      btn_text_color: "#B5D9F3",
      btn_bg: "#144E78",
      btn_text_hover_color: "#B5D9F3",
      btn_hover_bg: "#0E3755",
      btn_border_color: "#144E78",
      btn_hover_border_color: "#0E3755",
    },

    set9: {
      bg: "#04261d",
      secondary_bg: "#021e16",
      text_color: "#C1D2BB",
      link_color: "#00d29a",
      link_hover_color: "#C1D2BB",
      input_bg: "#073d2f",
      input_text_color: "#C1D2BB",
      input_placeholder_color: "#C1D2BB",
      border_color: "#095541",
      btn_text_color: "#C1D2BB",
      btn_bg: "#095541",
      btn_text_hover_color: "#C1D2BB",
      btn_hover_bg: "#073d2f",
      btn_border_color: "#095541",
      btn_hover_border_color: "#073d2f",
    },

    set10: {
      bg: "#171004",
      secondary_bg: "#211706",
      text_color: "#E0D2BD",
      link_color: "#e09525",
      link_hover_color: "#E0D2BD",
      input_bg: "#372911",
      input_text_color: "#E0D2BD",
      input_placeholder_color: "#E0D2BD",
      border_color: "#5D4010",
      btn_text_color: "#E0D2BD",
      btn_bg: "#5D4010",
      btn_text_hover_color: "#E0D2BD",
      btn_hover_bg: "#372911",
      btn_border_color: "#5D4010",
      btn_hover_border_color: "#372911",
    },
  };

  const palette = palettes[theme] || palettes["set1"];

  document.documentElement.style.setProperty(
    "--darkify_dark_mode_bg",
    palette.bg,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_secondary_bg",
    palette.secondary_bg,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_text_color",
    palette.text_color,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_link_color",
    palette.link_color,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_link_hover_color",
    palette.link_hover_color,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_input_bg",
    palette.input_bg,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_input_text_color",
    palette.input_text_color,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_input_placeholder_color",
    palette.input_placeholder_color,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_border_color",
    palette.border_color,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_btn_bg",
    palette.btn_bg,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_btn_text_color",
    palette.btn_text_color,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_btn_hover_bg",
    palette.btn_hover_bg,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_btn_text_hover_color",
    palette.btn_text_hover_color,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_btn_border_color",
    palette.btn_border_color,
  );
  document.documentElement.style.setProperty(
    "--darkify_dark_mode_btn_hover_border_color",
    palette.btn_hover_border_color,
  );
}

// ---------------------------------------------------------------------------
// Iframe dark mode
//
// Same-origin iframes are isolated documents: the parent's :root CSS variables,
// stylesheet and dark-mode class do not cascade into them. To keep an iframe in
// sync with the parent theme we mirror three things into the iframe document:
//   1. the darkify_dark_mode_enabled class on <html>
//   2. the theme CSS variables (kept live so colour/palette changes propagate)
//   3. the plugin stylesheet, plus a run of the element classifier so inner
//      content (cards, sections, etc.) is darkened, not just <body>/<a>/inputs
// Colour changes only need the variables refreshed; the var-driven class rules
// then re-theme everything instantly without re-walking the DOM.
// ---------------------------------------------------------------------------

const DARKIFY_IFRAME_THEME_VARS = [
  "--darkify_dark_mode_bg", "--darkify_dark_mode_secondary_bg",
  "--darkify_dark_mode_text_color", "--darkify_dark_mode_link_color",
  "--darkify_dark_mode_link_hover_color", "--darkify_dark_mode_input_bg",
  "--darkify_dark_mode_input_text_color", "--darkify_dark_mode_input_placeholder_color",
  "--darkify_dark_mode_border_color", "--darkify_dark_mode_btn_bg",
  "--darkify_dark_mode_btn_text_color", "--darkify_dark_mode_btn_hover_bg",
  "--darkify_dark_mode_btn_text_hover_color",
  "--darkify_dark_mode_btn_border_color",
  "--darkify_dark_mode_btn_hover_border_color",
];

// Tracks per-iframe-document observers so we don't attach duplicates and can
// react to dynamically injected content (e.g. React/SPA pages inside the frame).
const darkify_iframe_doc_observers = new WeakMap();

// Tracks per-iframe-document "keep our style last" observers so we attach only
// one per document. Keyed on iframeDoc.
const darkify_editor_head_watchers = new WeakMap();

// Serialise the parent's current theme variables as a :root {} rule. Computed
// style is used so the value reflects the active theme regardless of whether it
// was set inline (block editor) or via an inline <style> block (frontend).
function darkify_serialize_root_vars() {
  const inline = document.documentElement.style;
  const computed = getComputedStyle(document.documentElement);
  let rootVars = ":root {";
  DARKIFY_IFRAME_THEME_VARS.forEach(function (varName) {
    const val = (
      inline.getPropertyValue(varName) || computed.getPropertyValue(varName)
    ).trim();
    if (val) rootVars += varName + ": " + val + ";";
  });
  return rootVars + "}";
}

// Whether dark mode may apply to iframe content. The "Frontend Iframe Dark Mode"
// option only affects the frontend — the Gutenberg editor canvas is unaffected.
// Treated as enabled when the flag is absent (backward compatible: existing users
// who haven't re-saved settings keep the previous default-on behaviour).
function darkify_iframe_dark_enabled() {
  if (darkify_is_this_admin_panel === "1") return true;
  return (
    typeof darkify_enable_frontend_iframe_dark_mode === "undefined" ||
    darkify_enable_frontend_iframe_dark_mode === "1"
  );
}

// Build the theme payload broadcast to iframes via postMessage. This is the
// only channel that works for CROSS-ORIGIN iframes (e.g. an app served from a
// different host/port), where the browser forbids touching contentDocument.
// The receiving page applies these variables to its own theme.
function darkify_build_theme_payload() {
  const inline = document.documentElement.style;
  const computed = getComputedStyle(document.documentElement);
  const vars = {};
  DARKIFY_IFRAME_THEME_VARS.forEach(function (varName) {
    const val = (
      inline.getPropertyValue(varName) || computed.getPropertyValue(varName)
    ).trim();
    if (val) vars[varName] = val;
  });

  return {
    source: "darkify",
    type: "darkify-theme",
    // Honour the Frontend Iframe Dark Mode option here too, so the handshake
    // reply can't push dark mode into a cross-origin iframe when it is disabled.
    enabled:
      darkify_iframe_dark_enabled() &&
      document.documentElement.classList.contains("darkify_dark_mode_enabled"),
    vars: vars,
  };
}

// Post the current theme to an iframe window. Works regardless of origin and is
// silently ignored by frames that don't run the darkify receiver snippet.
function darkify_post_theme_to_iframe(iframe, payload) {
  try {
    const win = iframe.contentWindow;
    if (win) win.postMessage(payload || darkify_build_theme_payload(), "*");
  } catch (e) {
    // ignore — frame not ready / inaccessible window reference
  }
}

// Copy / refresh the theme variables inside an iframe document. Cheap and
// idempotent — called on every theme change so colours stay in sync.
function darkify_sync_iframe_vars(iframeDoc) {
  const head = iframeDoc.head || iframeDoc.documentElement;
  let style = iframeDoc.getElementById("darkify-iframe-vars");
  if (!style) {
    style = iframeDoc.createElement("style");
    style.id = "darkify-iframe-vars";
    head.appendChild(style);
  }
  style.textContent = darkify_serialize_root_vars();
}

// Resolve the URL of the plugin's main stylesheet as loaded in the parent, so
// the same var-driven .darkify_* rules can be injected into the iframe.
function darkify_get_main_css_href() {
  const link = document.querySelector('link[href*="client_main"]');
  return link ? link.href : null;
}

// Inject the plugin stylesheet (full class-based engine rules) plus a small
// baseline so the frame is themed immediately, before/independent of the
// element classifier pass.
function darkify_inject_css_into_iframe(iframeDoc) {
  const head = iframeDoc.head || iframeDoc.documentElement;

  if (!iframeDoc.getElementById("darkify-iframe-main-css")) {
    const href = darkify_get_main_css_href();
    if (href) {
      const link = iframeDoc.createElement("link");
      link.id = "darkify-iframe-main-css";
      link.rel = "stylesheet";
      link.href = href;
      head.appendChild(link);
    }
  }

  if (iframeDoc.getElementById("darkify-iframe-css")) return;

  const style = iframeDoc.createElement("style");
  style.id = "darkify-iframe-css";
  style.textContent = `
    html.darkify_dark_mode_enabled,
    html.darkify_dark_mode_enabled body {
      background: var(--darkify_dark_mode_secondary_bg) !important;
      color: var(--darkify_dark_mode_text_color) !important;
    }

    html.darkify_dark_mode_enabled a {
      color: var(--darkify_dark_mode_link_color) !important;
    }
    html.darkify_dark_mode_enabled a:hover {
      color: var(--darkify_dark_mode_link_hover_color) !important;
    }

    html.darkify_dark_mode_enabled input,
    html.darkify_dark_mode_enabled select,
    html.darkify_dark_mode_enabled textarea {
      background: var(--darkify_dark_mode_input_bg) !important;
      color: var(--darkify_dark_mode_input_text_color) !important;
      border-color: var(--darkify_dark_mode_border_color) !important;
    }

    html.darkify_dark_mode_enabled input::placeholder,
    html.darkify_dark_mode_enabled textarea::placeholder {
      color: var(--darkify_dark_mode_input_placeholder_color) !important;
    }

    /* TinyMCE editor body */
    html.darkify_dark_mode_enabled body#tinymce,
    html.darkify_dark_mode_enabled .mce-content-body {
      background: var(--darkify_dark_mode_secondary_bg) !important;
      color: var(--darkify_dark_mode_text_color) !important;
    }
  `;
  head.appendChild(style);
}
function darkify_inject_block_editor_css_into_iframe(iframeDoc) {
  const head = iframeDoc.head || iframeDoc.documentElement;
  let style = iframeDoc.getElementById("darkify-block-editor-css");
  if (!style) {
    style = iframeDoc.createElement("style");
    style.id = "darkify-block-editor-css";
  }

  style.textContent = `
    /* ── Primary containers (covers all themes) ─────────────────── */
    html.darkify_dark_mode_enabled,
    html.darkify_dark_mode_enabled body,
    html.darkify_dark_mode_enabled body.editor-styles-wrapper,
    html.darkify_dark_mode_enabled body.block-editor-iframe__body,
    html.darkify_dark_mode_enabled .editor-styles-wrapper,
    html.darkify_dark_mode_enabled .is-root-container,
    html.darkify_dark_mode_enabled .wp-block-post-content,
    html.darkify_dark_mode_enabled .block-editor-block-list__layout,
    html.darkify_dark_mode_enabled .block-editor-iframe__body,
    html.darkify_dark_mode_enabled .wp-site-blocks,
    html.darkify_dark_mode_enabled .entry-content,
    html.darkify_dark_mode_enabled .site-content {
      background: var(--darkify_dark_mode_bg) !important;
      background-color: var(--darkify_dark_mode_bg) !important;
      color: var(--darkify_dark_mode_text_color) !important;
    }

    /* ── Override CSS variables used by themes to drive backgrounds ─
       Kadence: --global-palette9 (bg), --global-palette1 (text)
       WordPress Global Styles: --wp--style--color--background        */
    html.darkify_dark_mode_enabled body {
      --wp--style--color--background: var(--darkify_dark_mode_bg);
      --wp--preset--color--background: var(--darkify_dark_mode_bg);
      --wp--preset--color--base: var(--darkify_dark_mode_bg);
      --wp--preset--color--contrast: var(--darkify_dark_mode_text_color);
      --global-palette9: var(--darkify_dark_mode_bg);
      --global-palette8: var(--darkify_dark_mode_secondary_bg);
      --global-palette7: var(--darkify_dark_mode_secondary_bg);
      --global-palette1: var(--darkify_dark_mode_text_color);
      --global-palette2: var(--darkify_dark_mode_text_color);
      --global-palette3: var(--darkify_dark_mode_link_color);
      --global-palette6: var(--darkify_dark_mode_border_color);
    }

    /* ── Text elements ──────────────────────────────────────────── */
    html.darkify_dark_mode_enabled p,
    html.darkify_dark_mode_enabled h1,
    html.darkify_dark_mode_enabled h2,
    html.darkify_dark_mode_enabled h3,
    html.darkify_dark_mode_enabled h4,
    html.darkify_dark_mode_enabled h5,
    html.darkify_dark_mode_enabled h6,
    html.darkify_dark_mode_enabled li,
    html.darkify_dark_mode_enabled td,
    html.darkify_dark_mode_enabled th,
    html.darkify_dark_mode_enabled blockquote,
    html.darkify_dark_mode_enabled pre,
    html.darkify_dark_mode_enabled span {
      color: var(--darkify_dark_mode_text_color) !important;
    }

    /* ── Links ──────────────────────────────────────────────────── */
    html.darkify_dark_mode_enabled a {
      color: var(--darkify_dark_mode_link_color) !important;
    }
    html.darkify_dark_mode_enabled a:hover {
      color: var(--darkify_dark_mode_link_hover_color) !important;
    }

    /* ── Form elements ──────────────────────────────────────────── */
    html.darkify_dark_mode_enabled input,
    html.darkify_dark_mode_enabled select,
    html.darkify_dark_mode_enabled textarea {
      background: var(--darkify_dark_mode_input_bg) !important;
      color: var(--darkify_dark_mode_input_text_color) !important;
      border-color: var(--darkify_dark_mode_border_color) !important;
    }
    html.darkify_dark_mode_enabled input::placeholder,
    html.darkify_dark_mode_enabled textarea::placeholder {
      color: var(--darkify_dark_mode_input_placeholder_color) !important;
    }

    /* ── Blocks ─────────────────────────────────────────────────── */
    html.darkify_dark_mode_enabled .wp-block {
      color: var(--darkify_dark_mode_text_color) !important;
    }
    html.darkify_dark_mode_enabled img {
      filter: brightness(80%);
    }
  `;

  // Always move to end of <head> so our rules load after any theme stylesheet.
  // head.appendChild is a no-op-safe move: if the element is already in the
  // tree it is first removed then re-inserted at the end.
  head.appendChild(style);

  // Keep it last: observe for new <link>/<style> tags Kadence (or any theme)
  // injects after us and immediately re-append our style to the end.
  darkify_keep_editor_style_last(iframeDoc, style);
}

/**
 * Run `fn` on the next frame, falling back to a task when the document has no
 * animation frames to give (a detached or hidden realm). The parent window's
 * clock is used deliberately: an iframe that is display:none never services its
 * own rAF, and the editor canvas is hidden for a beat during some transitions.
 */
function darkify_next_frame(fn) {
  if (typeof requestAnimationFrame === "function") {
    requestAnimationFrame(fn);
  } else {
    setTimeout(fn, 0);
  }
}

/**
 * Ceiling on how many times we will re-append our editor <style> to the end of
 * <head>. A normal editor boot inserts a few dozen stylesheets; anything past
 * this is two parties both insisting on being last, and the only way out is to
 * stop playing.
 */
const DARKIFY_EDITOR_STYLE_MAX_MOVES = 100;

// MutationObserver that keeps darkify-block-editor-css as the last stylesheet
// in the editor iframe <head>. Called once per iframeDoc (guarded by WeakMap).
//
// Re-appending our own <style> is itself a childList mutation on <head>, so this
// observer always sees its own move. Alone that terminates — the
// `lastElementChild` check exits on the second pass. It stops terminating when
// something else also wants to be last: Gutenberg re-inserts block styles as
// blocks register, and each of our moves provokes another of theirs. Coalescing
// to one move per frame keeps that from running synchronously inside the
// observer callback, and the move cap bounds it outright.
function darkify_keep_editor_style_last(iframeDoc, ourStyle) {
  if (darkify_editor_head_watchers.has(iframeDoc)) return;
  const head = iframeDoc.head;
  if (!head) return;

  let scheduled = false;
  let moves = 0;

  const obs = new MutationObserver(function () {
    if (scheduled) return;
    // If our style is already last, nothing to do.
    if (head.lastElementChild === ourStyle) return;

    scheduled = true;
    darkify_next_frame(function () {
      scheduled = false;
      // The head may have settled on its own while we waited for the frame.
      if (head.lastElementChild === ourStyle) return;

      moves++;
      // A new sheet was added after ours — move us to the end.
      head.appendChild(ourStyle);

      if (moves >= DARKIFY_EDITOR_STYLE_MAX_MOVES) {
        // Give up rather than keep trading appends forever. Whatever insists on
        // outranking us wins the cascade; a theme-coloured editor beats a frozen
        // one.
        obs.disconnect();
        darkify_editor_head_watchers.delete(iframeDoc);
      }
    });
  });

  obs.observe(head, { childList: true });
  darkify_editor_head_watchers.set(iframeDoc, obs);
}

/**
 * Per-iframe incremental walk state: the subtree roots added since the last
 * drain, and whether a drain is already booked for the next frame.
 */
const darkify_iframe_walk_state = new WeakMap();

/**
 * Past this many queued roots, walking each one costs more than one flat pass
 * over the document — the `.darkify_processed` exclusion in the selector means
 * that pass only pays for nodes it has not already styled.
 */
const DARKIFY_IFRAME_FULL_PASS_THRESHOLD = 200;

function darkify_process_iframe_element(element) {
  try {
    darkify_process_element(element);
  } catch (e) {
    // skip elements that can't be processed
  }
}

/** Style one newly-added subtree: the root itself, then its descendants. */
function darkify_walk_iframe_root(root) {
  if (!root.isConnected) return;

  if (root.nodeType === 1 && !root.classList.contains("darkify_processed")) {
    let walkable = false;
    try {
      walkable = root.matches(DARKIFY_WALK_SELECTOR);
    } catch (e) {
      walkable = false;
    }
    if (walkable) {
      darkify_process_iframe_element(root);
    }
  }

  if (root.querySelectorAll) {
    root
      .querySelectorAll(DARKIFY_WALK_SELECTOR)
      .forEach(darkify_process_iframe_element);
  }
}

/** Flat pass over every not-yet-styled element in the iframe document. */
function darkify_walk_iframe_all(iframeDoc) {
  if (!iframeDoc.documentElement) return;
  iframeDoc
    .querySelectorAll(DARKIFY_WALK_SELECTOR)
    .forEach(darkify_process_iframe_element);
}

/** Drain one iframe's queued roots on the next frame. Idempotent per frame. */
function darkify_schedule_iframe_walk(iframeDoc) {
  const state = darkify_iframe_walk_state.get(iframeDoc);
  if (!state || state.scheduled) return;
  state.scheduled = true;

  darkify_next_frame(function () {
    state.scheduled = false;
    if (!iframeDoc.documentElement) return;

    const roots = Array.from(state.roots);
    state.roots.clear();
    if (roots.length === 0) return;

    if (roots.length >= DARKIFY_IFRAME_FULL_PASS_THRESHOLD) {
      darkify_walk_iframe_all(iframeDoc);
      return;
    }

    roots.forEach(darkify_walk_iframe_root);
  });
}

// Run the element classifier across an iframe document and keep watching it for
// dynamically added nodes. window.getComputedStyle resolves styles of
// same-origin iframe elements, so the existing engine works unchanged.
//
// The watch is incremental and frame-batched, mirroring the main document's
// scheduler (see darkify_schedule_walk). It used to re-query and re-walk the
// whole document on every mutation batch, with each visited element costing a
// getComputedStyle — a forced style recalc. That is survivable on a page that
// mutates occasionally and fatal in the block editor, where every keystroke is a
// childList mutation: the editor stopped responding on any post long enough to
// make the walk expensive.
function darkify_run_engine_on_iframe(iframeDoc) {
  darkify_walk_iframe_all(iframeDoc);

  if (darkify_iframe_doc_observers.has(iframeDoc)) return;

  darkify_iframe_walk_state.set(iframeDoc, {
    roots: new Set(),
    scheduled: false,
  });

  const observer = new MutationObserver(function (mutationsList) {
    const state = darkify_iframe_walk_state.get(iframeDoc);
    if (!state) return;

    for (let i = 0; i < mutationsList.length; i++) {
      const added = mutationsList[i].addedNodes;
      for (let j = 0; j < added.length; j++) {
        const node = added[j];
        if (node.nodeType !== 1) continue;
        // Engine-generated nodes carry no design of their own to read, and
        // queueing them here is how an observer ends up feeding itself.
        if (
          node.classList &&
          (node.classList.contains("darkify_switch") ||
            node.classList.contains("darkify_ignore"))
        ) {
          continue;
        }
        state.roots.add(node);
      }
    }

    if (state.roots.size > 0) {
      darkify_schedule_iframe_walk(iframeDoc);
    }
  });

  observer.observe(iframeDoc.documentElement, {
    childList: true,
    subtree: true,
  });
  darkify_iframe_doc_observers.set(iframeDoc, observer);
}

// Apply CSS invert filter to a cross-origin iframe element as a fallback dark
// mode technique — the only browser-allowed approach when the embedded site
// does not run the darkify receiver script.
function darkify_apply_filter_to_iframe(iframe, enabled) {
  if (enabled) {
    iframe.style.filter = "brightness(0.6)";
  } else {
    iframe.style.filter = "";
  }
}

// Detect whether an iframe is cross-origin by attempting to access its document.
function darkify_is_cross_origin_iframe(iframe) {
  try {
    // Accessing contentDocument throws SecurityError for cross-origin frames.
    void (iframe.contentDocument || iframe.contentWindow?.document);
    return false;
  } catch (e) {
    return true;
  }
}
function darkify_apply_dark_to_iframe(iframe) {
  const isEditorCanvas = iframe.name === "editor-canvas";
  if (darkify_is_this_admin_panel === "1" && !isEditorCanvas) return;

  const enabled = document.documentElement.classList.contains(
    "darkify_dark_mode_enabled",
  );

  // Same-origin path: directly inject styles + run the engine. Throws a
  // SecurityError for cross-origin frames, which we swallow — those are handled
  // via postMessage + CSS filter fallback below.
  const applyDirect = function () {
    let iframeDoc;
    try {
      iframeDoc = iframe.contentDocument || iframe.contentWindow?.document;
    } catch (e) {
      // Cross-origin: apply CSS filter to the iframe element as fallback.
      if (!isEditorCanvas && darkify_is_this_admin_panel !== "1") {
        darkify_apply_filter_to_iframe(
          iframe,
          document.documentElement.classList.contains(
            "darkify_dark_mode_enabled",
          ),
        );
      }
      return;
    }
    if (!iframeDoc || !iframeDoc.documentElement) return;

    // Same-origin: clear any filter that was applied before we could access the doc.
    iframe.style.filter = "";

    if (enabled) {
      iframeDoc.documentElement.classList.add("darkify_dark_mode_enabled");
      darkify_inject_css_into_iframe(iframeDoc);
      darkify_sync_iframe_vars(iframeDoc);
      if (isEditorCanvas) {
        darkify_inject_block_editor_css_into_iframe(iframeDoc);
      } else if (darkify_is_this_admin_panel !== "1") {
        darkify_run_engine_on_iframe(iframeDoc);
      }
    } else {
      iframeDoc.documentElement.classList.remove("darkify_dark_mode_enabled");
    }
  };

  // Always broadcast the current theme first. This MUST run independently of the
  // (throwing) contentDocument access below, otherwise live toggles/palette
  // changes never reach a cross-origin iframe — they'd only sync on reload.
  darkify_post_theme_to_iframe(iframe);

  // Re-broadcast + re-apply on every (re)load/navigation so content swapped
  // inside the frame is re-themed. Bind once per iframe to avoid stacking.
  if (!iframe.dataset.darkifyIframeBound) {
    iframe.dataset.darkifyIframeBound = "1";
    iframe.addEventListener("load", function () {
      darkify_post_theme_to_iframe(iframe);
      applyDirect();
    });
  }

  applyDirect();
}
function darkify_process_iframes() {
  if (darkify_is_this_admin_panel === "1") {
    // Admin panel: only target the Gutenberg editor-canvas iframe
    const editorCanvas = document.querySelector('iframe[name="editor-canvas"]');
    if (editorCanvas) darkify_apply_dark_to_iframe(editorCanvas);
    return;
  }

  // Respect the "Frontend Iframe Dark Mode" setting.
  // Existing users without this option saved get the default-on behaviour.
  if (!darkify_iframe_dark_enabled()) return;

  document.querySelectorAll("iframe").forEach(darkify_apply_dark_to_iframe);
}

// Watch the parent for theme changes (dark toggle, palette switch, customizer
// live edits) and re-sync every iframe. darkify_process_iframes re-runs the
// var sync, which is what propagates new colours into the frames.
let darkify_parent_theme_observer = null;
let darkify_iframe_sync_scheduled = false;
function darkify_schedule_iframe_sync() {
  if (darkify_iframe_sync_scheduled) return;
  darkify_iframe_sync_scheduled = true;
  requestAnimationFrame(function () {
    darkify_iframe_sync_scheduled = false;
    darkify_process_iframes();
  });
}
function darkify_watch_parent_theme() {
  if (darkify_parent_theme_observer) return;

  darkify_parent_theme_observer = new MutationObserver(darkify_schedule_iframe_sync);

  // <html> class (dark on/off) and inline style (block-editor palette vars).
  darkify_parent_theme_observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ["class", "style"],
  });

  // Frontend palette variables live in an inline <style> block; watch its text
  // so customizer / dynamic edits to the :root variables are picked up too.
  const inlineCss = document.querySelector("style.darkify_inline_css");
  if (inlineCss) {
    darkify_parent_theme_observer.observe(inlineCss, {
      childList: true,
      characterData: true,
      subtree: true,
    });
  }

  // In the admin panel, Gutenberg inserts the editor-canvas iframe into the DOM
  // asynchronously — after the initial darkify_process_iframes() call has
  // already run. Watch document.body so we re-sync the moment it appears.
  if (darkify_is_this_admin_panel === "1" && document.body) {
    var darkify_canvas_dom_observer = new MutationObserver(function () {
      if (document.querySelector('iframe[name="editor-canvas"]')) {
        darkify_schedule_iframe_sync();
      }
    });
    darkify_canvas_dom_observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }
}

if (!_dkf_iframe_disabled) {
  if (document.readyState !== "loading") {
    darkify_watch_parent_theme();
  } else {
    document.addEventListener("DOMContentLoaded", darkify_watch_parent_theme);
  }
}

// Handshake: an iframe that loads after — or before — the parent is ready can
// ask for the current theme, and we reply to that frame directly. This makes
// initial sync reliable regardless of which side finishes loading first.
window.addEventListener("message", function (event) {
  const data = event.data;
  if (!data || data.source !== "darkify" || data.type !== "darkify-request-theme")
    return;
  try {
    if (event.source) {
      event.source.postMessage(darkify_build_theme_payload(), "*");
    }
  } catch (e) {
    // ignore unreachable source window
  }
});

function darkify_init_keyboard_shortcut_listener() {
  if (darkify_enable_keyboard_shortcut === "1") {
    // The combo is a normalized string set in the admin (e.g. "ctrl+alt+d"):
    // modifiers in any order plus one key. Match on the PHYSICAL key (event.code)
    // so macOS Option-diacritics don't break it.
    var combo =
      typeof darkify_keyboard_shortcut_keys === "string" &&
      darkify_keyboard_shortcut_keys
        ? darkify_keyboard_shortcut_keys.toLowerCase()
        : "ctrl+alt+d";
    var parts = combo.split("+");
    var need_ctrl = parts.indexOf("ctrl") !== -1;
    var need_alt = parts.indexOf("alt") !== -1;
    var need_shift = parts.indexOf("shift") !== -1;
    var need_meta = parts.indexOf("meta") !== -1;
    var need_key = parts[parts.length - 1];
    var expected_code = null;
    if (/^[a-z]$/.test(need_key)) {
      expected_code = "key" + need_key;
    } else if (/^[0-9]$/.test(need_key)) {
      expected_code = "digit" + need_key;
    }
    // A combo with neither Ctrl nor Cmd (Shift+D, or Alt/Option+D, which types
    // "∂" on a Mac) is a character a visitor can type. Inside a text field it
    // must stay text, or writing a capital "D" in a comment would flip the
    // theme (WCAG 2.1.4 Character Key Shortcuts).
    var types_a_character = !need_ctrl && !need_meta;

    // addEventListener, not `document.onkeydown =`: assigning the property
    // silently replaced any handler a theme or plugin had put there (and was
    // replaced by theirs in turn).
    document.addEventListener("keydown", function (event) {
      if (event.repeat || event.isComposing) {
        return;
      }
      if (types_a_character && darkify_is_editable_target(event.target)) {
        return;
      }
      var key_matches =
        (expected_code &&
          typeof event.code === "string" &&
          event.code.toLowerCase() === expected_code) ||
        (typeof event.key === "string" &&
          event.key.toLowerCase() === need_key);
      if (
        event.ctrlKey === need_ctrl &&
        event.altKey === need_alt &&
        event.shiftKey === need_shift &&
        event.metaKey === need_meta &&
        key_matches
      ) {
        event.preventDefault();
        darkify_switch_trigger();
      }
    });
  }
}

/** True when keystrokes on `target` would type text. */
function darkify_is_editable_target(target) {
  if (!target || target.nodeType !== 1) {
    return false;
  }
  if (target.isContentEditable) {
    return true;
  }
  var tag = target.tagName;
  if (tag === "TEXTAREA" || tag === "SELECT") {
    return true;
  }
  if (tag === "INPUT") {
    // Inputs that take no typed text (checkbox, radio, button, range, ...).
    return !/^(button|checkbox|color|file|hidden|image|radio|range|reset|submit)$/i.test(
      target.type || "",
    );
  }
  return false;
}

function darkify_init_os_mode_change_listener() {
  if (darkify_is_this_admin_panel === "0" && darkify_enable_os_aware === "1") {
    window
      .matchMedia("(prefers-color-scheme: dark)")
      .addEventListener("change", (event) => {
        const mode = event.matches ? "dark" : "light";
        const htmlElement = document.getElementsByTagName("html")[0];
        if (mode === "dark") {
          htmlElement.classList.add("darkify_dark_mode_enabled");
        } else if (mode === "light") {
          htmlElement.classList.remove("darkify_dark_mode_enabled");
        }

        darkify_change_state();
      });
  }
}

function darkify_init_alternative_dark_mode_switch() {
  if (darkify_alternative_dark_mode_switch.length > 0) {
    const elements = document.querySelectorAll(
      darkify_alternative_dark_mode_switch,
    );
    for (let i = 0; i < elements.length; i++) {
      const element = elements[i];
      element.addEventListener("click", () => {
        darkify_switch_trigger();
      });
    }
  }
}

/*
 * Click handling and screen-reader state for every `.darkify_switch` button
 * (floating, mobile, menu, shortcode, block, Elementor — see
 * Darkify::switcher_wrapper()).
 *
 * One delegated listener instead of an inline `onclick` per switch: it covers
 * switches inserted later (popups, AJAX-loaded content) and survives a strict
 * Content-Security-Policy. Keyboard activation needs nothing extra because the
 * switch is a native <button>.
 *
 * `aria-pressed` mirrors the `darkify_dark_mode_enabled` class, whatever flips
 * it — a switch, the keyboard shortcut, the OS listener, time-based mode. The
 * engine's own observers are only wired on first toggle, so this keeps its own:
 * one element, one attribute, and a no-op when the state has not changed.
 */
function darkify_init_switch_a11y() {
  var last_pressed = null;

  function sync(force) {
    var pressed = document.documentElement.classList.contains(
      "darkify_dark_mode_enabled",
    )
      ? "true"
      : "false";
    if (!force && pressed === last_pressed) {
      return;
    }
    last_pressed = pressed;
    var switches = document.querySelectorAll(".darkify_switch");
    for (var i = 0; i < switches.length; i++) {
      if (switches[i].getAttribute("aria-pressed") !== pressed) {
        switches[i].setAttribute("aria-pressed", pressed);
      }
    }
  }

  document.addEventListener("click", function (event) {
    var target = event.target;
    var el = target && target.closest ? target.closest(".darkify_switch") : null;
    // Markup cached before the switch became a button still carries its own
    // inline onclick; handling it here as well would toggle twice.
    if (!el || el.hasAttribute("onclick")) {
      return;
    }
    darkify_switch_trigger();
  });

  // A switch inserted after the last sync (a popup, a lazy-loaded section)
  // still holds the server's "false". Correct it before a screen reader reads
  // it out on focus.
  document.addEventListener("focusin", function (event) {
    var target = event.target;
    if (target && target.classList && target.classList.contains("darkify_switch")) {
      sync(true);
    }
  });

  new MutationObserver(function () {
    sync(false);
  }).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ["class"],
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      sync(true);
    });
  } else {
    sync(true);
  }
}

function darkify_init_attention_effect() {
  if (darkify_enable_switch_attention !== "1") return;
  if (!darkify_switch_attention_effect || darkify_switch_attention_effect === "none") return;
  var switchEl = document.getElementById("darkify_switch_" + darkify_switch_unique_id);
  if (switchEl) {
    switchEl.classList.add("darkify_attention_" + darkify_switch_attention_effect);
  }
}

function get_bg_color_to_preserve(element, fromDataset) {
  let color = window.getComputedStyle(element, null).backgroundColor;
  if (!fromDataset) {
    color = element.dataset.darkify_preserved_bg;
  }
  if (
    (color === "transparent" ||
      color === "rgba(0, 0, 0, 0)" ||
      color === "rgba(255,255,255,0)") &&
    element.parentNode.nodeType === 1
  ) {
    color = get_bg_color_to_preserve(element.parentNode, false);
  } else if (
    element.parentNode.nodeType === 1 &&
    element.parentNode.hasAttribute("data-darkify_preserved_bg") &&
    window.getComputedStyle(element.parentNode, null).backgroundColor === color
  ) {
    color = get_bg_color_to_preserve(element.parentNode, false);
  }
  return color;
}

function get_txt_color_to_preserve(element, fromDataset) {
  let color = window.getComputedStyle(element, null).color;
  if (!fromDataset) {
    color = element.dataset.darkify_preserved_color;
  }
  if (
    (color === "transparent" ||
      color === "rgba(0, 0, 0, 0)" ||
      color === "rgba(255,255,255,0)") &&
    element.parentNode.nodeType === 1
  ) {
    color = get_txt_color_to_preserve(element.parentNode, false);
  } else if (
    element.parentNode.nodeType === 1 &&
    element.parentNode.hasAttribute("data-darkify_preserved_color") &&
    window.getComputedStyle(element.parentNode, null).color === color
  ) {
    color = get_txt_color_to_preserve(element.parentNode, false);
  }
  return color;
}

/**
 * Whether a black wash can be folded into this layer's background image.
 *
 * The darkener works by prepending an opaque-black gradient to `background-image`,
 * which assumes the layer is composited normally — then the result really is
 * "the picture, dimmed".
 *
 * A layer with a blend mode breaks that assumption, and `multiply` breaks it
 * completely: multiply against black is black, whatever is underneath. A
 * builder's texture overlay is drawn exactly that way — a pale watermark
 * multiplied over the section at low opacity, which in light mode tints it
 * faintly. Fold a 60%-black wash into that and the overlay stops being a
 * watermark and becomes a dark rectangle stamped over the section, with a hard
 * seam along its edge. It reads as a patch of the page that failed to convert,
 * which is the opposite of what the darkener is for.
 *
 * Left alone, such a layer needs no help: it multiplies a pale image over an
 * already-dark surface and all but disappears, which is the right outcome.
 */
/** Every background property that is a per-layer list, in the order it is written. */
var DARKIFY_DARKEN_LAYER_PROPS = [
  "background-image",
  "background-size",
  "background-repeat",
  "background-position",
  "background-origin",
  "background-clip",
  "background-attachment",
];

/**
 * The per-layer geometry a prepended darkening layer needs to cover its box.
 *
 * `background-image` is a list — and so are `background-size`, `-repeat`,
 * `-position`, `-origin`, `-clip` and `-attachment`. Each layer takes the value
 * at its own index, and a list shorter than the image list is cycled. So
 * prepending a wash to `background-image` alone does not add a layer carrying
 * sane defaults: it shifts every other list by one, and the wash silently
 * inherits whatever geometry the design wrote for the picture.
 *
 * Where that geometry is `cover` / `repeat`, the wash happens to fill the box
 * and the result looks right, which is why this went unnoticed for so long.
 * Where it is anything else, the wash stops covering the element. A decorative
 * watermark drawn at `background-size: 37%` with `no-repeat` turns the wash into
 * a hard-edged black rectangle over 37% of the section — a dark panel with
 * visible corners, in the shape of nothing the design contains. `contain`,
 * pixel sizes, positioned icons and sprite sheets all fail the same way.
 *
 * Naming the wash's own geometry is what makes it a layer instead of a shift: it
 * fills the border box exactly, once, however the picture beneath it is sized,
 * tiled, positioned or clipped. Prepending (rather than replacing) leaves every
 * value the design wrote still attached to the layer it was written for —
 * including the last `background-clip`, which is the one that clips the
 * background colour.
 */
function darkify_darken_layer_geometry(style) {
  return {
    "background-size": "100% 100%, " + style.backgroundSize,
    "background-repeat": "no-repeat, " + style.backgroundRepeat,
    "background-position": "0% 0%, " + style.backgroundPosition,
    "background-origin": "border-box, " + style.backgroundOrigin,
    "background-clip": "border-box, " + style.backgroundClip,
    "background-attachment": "scroll, " + style.backgroundAttachment,
  };
}

/** Those declarations as CSS text, for the rules the pseudo paths write. */
function darkify_darken_layer_css(style) {
  var geometry = darkify_darken_layer_geometry(style);
  var css = "";
  Object.keys(geometry).forEach(function (prop) {
    css += prop + ": " + geometry[prop] + " !important;";
  });
  return css;
}

function darkify_can_darken_layer(style) {
  if (!style) {
    return false;
  }

  var blend = style.mixBlendMode || style.getPropertyValue("mix-blend-mode");
  return !blend || blend === "normal";
}

/**
 * Measured mean luminance per image URL, so one picture is sampled once for the
 * whole page. `null` means "sampled and unusable" — a cross-origin image whose
 * pixels a canvas may not read, or one that failed to load — and the wash falls
 * back to the configured level for those.
 */
var DARKIFY_IMAGE_LUMA = {};
var DARKIFY_IMAGE_LUMA_WAITING = {};

/** At or below this mean luminance a picture needs no wash at all. */
var DARKIFY_IMAGE_DARK_FLOOR = 0.16;
/** At or above it, the wash is applied at the level the user configured. */
var DARKIFY_IMAGE_DARK_CEIL = 0.5;

/** The first `url(...)` in a `background-image` list, unquoted. */
function darkify_first_image_url(value) {
  var match = value && value.match(/url\((['"]?)([^'")]+)\1\)/);
  return match ? match[2] : "";
}

/**
 * Sample a picture's mean luminance, once, off a 16x16 canvas.
 *
 * The size is deliberate: the wash only needs to know "is this picture bright
 * or is it already dark", and drawing a 1850px hero down to 256 pixels answers
 * that for the cost of one small draw. Alpha is weighted in, so a mostly
 * transparent PNG doesn't read as black.
 *
 * A cross-origin image without CORS headers taints the canvas and `getImageData`
 * throws; that is caught and cached as `null` rather than retried, so a page
 * full of third-party images doesn't sample the same failure on every pass.
 */
function darkify_measure_image_luma(url, done) {
  if (Object.prototype.hasOwnProperty.call(DARKIFY_IMAGE_LUMA, url)) {
    done(DARKIFY_IMAGE_LUMA[url]);
    return;
  }
  if (DARKIFY_IMAGE_LUMA_WAITING[url]) {
    DARKIFY_IMAGE_LUMA_WAITING[url].push(done);
    return;
  }
  DARKIFY_IMAGE_LUMA_WAITING[url] = [done];

  var finish = function (value) {
    DARKIFY_IMAGE_LUMA[url] = value;
    var waiting = DARKIFY_IMAGE_LUMA_WAITING[url] || [];
    delete DARKIFY_IMAGE_LUMA_WAITING[url];
    waiting.forEach(function (callback) {
      callback(value);
    });
  };

  var img = new Image();
  img.crossOrigin = "anonymous";
  img.onload = function () {
    try {
      var size = 16;
      var canvas = document.createElement("canvas");
      canvas.width = size;
      canvas.height = size;
      var ctx = canvas.getContext("2d", { willReadFrequently: true });
      ctx.drawImage(img, 0, 0, size, size);
      var data = ctx.getImageData(0, 0, size, size).data;
      var total = 0;
      var count = 0;
      for (var i = 0; i < data.length; i += 4) {
        var alpha = data[i + 3] / 255;
        if (alpha === 0) {
          continue;
        }
        total +=
          ((0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2]) /
            255) *
          alpha;
        count++;
      }
      finish(count ? total / count : null);
    } catch (e) {
      finish(null);
    }
  };
  img.onerror = function () {
    finish(null);
  };
  img.src = url;
}

/**
 * Scale the configured wash by how bright the picture under it actually is.
 *
 * The wash exists to stop a bright light-mode photo glaring out of a dark page.
 * A picture that is already dark — a near-black hero, a deep-green pattern —
 * has nothing to dim, and stamping 60% black over it does not darken the design,
 * it erases it: the section becomes a flat black band and whatever shape or
 * texture the picture carried is gone. That is the same mistake the scrim rule
 * above fixes for gradients, one layer down.
 *
 * Below the floor the wash is dropped entirely, above the ceiling it is applied
 * in full, and in between it ramps, so there is no visible step between a
 * picture that just cleared the floor and one that just missed it.
 *
 * @return {string} A level string in the same one-decimal form the caller's
 *         configured level uses, "0.0" meaning "no wash".
 */
function darkify_darken_level_for_image(level, url) {
  var configured = parseFloat(level) || 0;
  if (!url || !Object.prototype.hasOwnProperty.call(DARKIFY_IMAGE_LUMA, url)) {
    return String(level);
  }

  var luma = DARKIFY_IMAGE_LUMA[url];
  if (luma === null || luma === undefined) {
    return String(level);
  }
  if (luma <= DARKIFY_IMAGE_DARK_FLOOR) {
    return "0.0";
  }

  var scale = Math.min(
    1,
    (luma - DARKIFY_IMAGE_DARK_FLOOR) /
      (DARKIFY_IMAGE_DARK_CEIL - DARKIFY_IMAGE_DARK_FLOOR),
  );
  return (configured * scale).toFixed(1);
}

/**
 * The style element carrying one generated box's wash.
 *
 * The id is stored on the element and reused, rather than regenerated per call:
 * the wash is re-applied whenever a measurement comes back, and a fresh random
 * id each time would leave the previous `<style>` in the head still painting the
 * old, full-strength wash through a selector that still matches.
 */
function darkify_darken_pseudo_style(element, pseudo) {
  var attribute = "data-darkify-" + pseudo + "-style-id";
  var styleId = element.getAttribute(attribute);
  if (!styleId) {
    styleId =
      "darkify-" + pseudo + "-" + Math.random().toString(36).substr(2, 9);
    element.setAttribute(attribute, styleId);
  }

  var styleElement = document.getElementById(styleId);
  if (!styleElement) {
    styleElement = document.createElement("style");
    styleElement.id = styleId;
    document.head.appendChild(styleElement);
  }

  return { id: styleId, node: styleElement };
}

function darkify_darken_bg_image(element, level) {
  if (
    document
      .getElementsByTagName("html")[0]
      .classList.contains("darkify_dark_mode_enabled")
  ) {
    const mainStyle = window.getComputedStyle(element, null);
    const beforeStyle = window.getComputedStyle(element, ":before");
    const afterStyle = window.getComputedStyle(element, ":after");

    // Any picture on this element that hasn't been sampled yet is sampled now,
    // and the wash is re-applied once the measurement lands. Until then the
    // configured level stands: over-darkening for a frame is recoverable, while
    // showing a bright light-mode photo on a dark page for a frame is the flash
    // this engine exists to avoid.
    var pending = [];
    [mainStyle, beforeStyle, afterStyle].forEach(function (style) {
      if (!style || !style.backgroundImage) {
        return;
      }
      var url = darkify_first_image_url(style.backgroundImage);
      if (
        url &&
        !Object.prototype.hasOwnProperty.call(DARKIFY_IMAGE_LUMA, url) &&
        pending.indexOf(url) === -1
      ) {
        pending.push(url);
      }
    });

    if (pending.length) {
      var remaining = pending.length;
      pending.forEach(function (url) {
        darkify_measure_image_luma(url, function () {
          remaining--;
          if (remaining > 0 || !element.isConnected) {
            return;
          }
          // Re-apply from the design's own values: the inline wash written
          // below is restored first so this pass measures the picture, not the
          // last pass's output.
          if (element.dataset && element.dataset.darkifyDarkenPrev) {
            darkify_restore_inline(
              element,
              "darkifyDarkenPrev",
              DARKIFY_DARKEN_LAYER_PROPS,
            );
          }
          darkify_darken_bg_image(element, level);
        });
      });
    }

    var mainLevel = darkify_darken_level_for_image(
      level,
      darkify_first_image_url(mainStyle.backgroundImage),
    );

    if (
      darkify_can_darken_layer(mainStyle) &&
      mainStyle.backgroundImage !== "none" &&
      mainStyle.backgroundImage.includes("url") &&
      mainLevel !== "0.0" &&
      !mainStyle.backgroundImage.includes("rgba(0, 0, 0, " + mainLevel + ")")
    ) {
      darkify_store_inline(
        element,
        "darkifyDarkenPrev",
        DARKIFY_DARKEN_LAYER_PROPS,
      );

      element.style.setProperty(
        "background-image",
        "linear-gradient(rgba(0, 0, 0, " +
          mainLevel +
          "), rgba(0, 0, 0, " +
          mainLevel +
          ")), " +
          mainStyle.backgroundImage,
      );

      var main_geometry = darkify_darken_layer_geometry(mainStyle);
      Object.keys(main_geometry).forEach(function (prop) {
        element.style.setProperty(prop, main_geometry[prop]);
      });
    }

    // Process :before pseudo-element
    var beforeLevel = darkify_darken_level_for_image(
      level,
      darkify_first_image_url(beforeStyle.backgroundImage),
    );

    if (
      darkify_can_darken_layer(beforeStyle) &&
      beforeStyle.backgroundImage !== "none" &&
      beforeStyle.backgroundImage.includes("url") &&
      !beforeStyle.backgroundImage.includes("rgba(0, 0, 0, " + beforeLevel + ")")
    ) {
      const beforeSheet = darkify_darken_pseudo_style(element, "before");

      // Store original background for reset
      element.dataset.darkifyOriginalBeforeBg = beforeStyle.backgroundImage;

      // A picture dark enough to need no wash still gets its rule cleared —
      // an earlier pass may have written a full-strength one before the
      // measurement came back.
      if (beforeLevel === "0.0") {
        beforeSheet.node.textContent = "";
      } else {
        beforeSheet.node.textContent = `
        .darkify_dark_mode_enabled [data-darkify-before-style-id="${beforeSheet.id}"]::before {
          background-image: linear-gradient(rgba(0, 0, 0, ${beforeLevel}), rgba(0, 0, 0, ${beforeLevel})), ${beforeStyle.backgroundImage} !important;
          ${darkify_darken_layer_css(beforeStyle)}
        }
      `;

        // Ensure position relative on parent
        if (window.getComputedStyle(element).position === "static") {
          element.style.position = "relative";
        }
      }
    }

    // Process :after pseudo-element
    var afterLevel = darkify_darken_level_for_image(
      level,
      darkify_first_image_url(afterStyle.backgroundImage),
    );

    if (
      darkify_can_darken_layer(afterStyle) &&
      afterStyle.backgroundImage !== "none" &&
      afterStyle.backgroundImage.includes("url") &&
      !afterStyle.backgroundImage.includes("rgba(0, 0, 0, " + afterLevel + ")")
    ) {
      const afterSheet = darkify_darken_pseudo_style(element, "after");

      // Store original background for reset
      element.dataset.darkifyOriginalAfterBg = afterStyle.backgroundImage;

      if (afterLevel === "0.0") {
        afterSheet.node.textContent = "";
      } else {
        afterSheet.node.textContent = `
        .darkify_dark_mode_enabled [data-darkify-after-style-id="${afterSheet.id}"]::after {
          background-image: linear-gradient(rgba(0, 0, 0, ${afterLevel}), rgba(0, 0, 0, ${afterLevel})), ${afterStyle.backgroundImage} !important;
          ${darkify_darken_layer_css(afterStyle)}
        }
      `;

        // Ensure position relative on parent
        if (window.getComputedStyle(element).position === "static") {
          element.style.position = "relative";
        }
      }
    }
  } else if (element.dataset && element.dataset.darkifyDarkenPrev) {
    // Restore from the saved inline declarations rather than by editing the
    // computed value back: the wash now writes six geometry properties beside
    // the image, and unpicking a string only ever put the image back — the
    // element kept the wash's `background-size`/`-repeat` in light mode, which
    // re-sized the design's own picture.
    darkify_restore_inline(
      element,
      "darkifyDarkenPrev",
      DARKIFY_DARKEN_LAYER_PROPS,
    );
  }
}

function darkify_img_brightness_and_grayscale(element) {
  if (
    document
      .getElementsByTagName("html")[0]
      .classList.contains("darkify_dark_mode_enabled")
  ) {
    if (
      !element.classList.contains("darkify_changed_brightness_and_grayscale")
    ) {
      element.dataset.darkify_preserved_filter = element.style.filter;
      element.classList.add("darkify_changed_brightness_and_grayscale");

      if (
        darkify_enable_low_image_brightness === "1" &&
        darkify_enable_image_grayscale === "1"
      ) {
        element.style.filter =
          "brightness(" +
          darkify_image_brightness_to +
          "%)" +
          " " +
          "grayscale(" +
          darkify_image_grayscale_to +
          "%)";
      } else {
        if (darkify_enable_low_image_brightness === "1") {
          element.style.filter =
            "brightness(" + darkify_image_brightness_to + "%)";
        } else if (darkify_enable_image_grayscale === "1") {
          element.style.filter =
            "grayscale(" + darkify_image_grayscale_to + "%)";
        }
      }
    }
  } else if (
    element.classList.contains("darkify_changed_brightness_and_grayscale")
  ) {
    element.style.filter = element.dataset.darkify_preserved_filter;
    element.classList.remove("darkify_changed_brightness_and_grayscale");
    delete element.dataset.darkify_preserved_filter;
  }
}

function darkify_invert_inline_svg(element) {
  if (document.body.classList.contains("block-editor-page")) return;
  if (
    document
      .getElementsByTagName("html")[0]
      .classList.contains("darkify_dark_mode_enabled")
  ) {
    element.style.filter = "invert(1)";
    element.classList.add("darkify_inverted_inline_svg");
  } else if (element.classList.contains("darkify_inverted_inline_svg")) {
    element.style.filter = element.style.filter.replace("invert(1)", "");
    element.classList.remove("darkify_inverted_inline_svg");
  }
}

function darkify_video_brightness_and_grayscale(element) {
  if (
    document
      .getElementsByTagName("html")[0]
      .classList.contains("darkify_dark_mode_enabled")
  ) {
    if (
      !element.classList.contains(
        "darkify_changed_video_brightness_and_grayscale",
      )
    ) {
      element.dataset.darkify_preserved_filter = element.style.filter;
      element.classList.add("darkify_changed_video_brightness_and_grayscale");
      if (
        darkify_enable_low_video_brightness === "1" &&
        darkify_enable_video_grayscale === "1"
      ) {
        element.style.filter =
          "brightness(" +
          darkify_video_brightness_to +
          "%)" +
          " " +
          "grayscale(" +
          darkify_video_grayscale_to +
          "%)";
      } else {
        if (darkify_enable_low_video_brightness === "1") {
          element.style.filter =
            "brightness(" + darkify_video_brightness_to + "%)";
        } else if (darkify_enable_video_grayscale === "1") {
          element.style.filter =
            "grayscale(" + darkify_video_grayscale_to + "%)";
        }
      }
    }
  } else if (
    element.classList.contains("darkify_changed_video_brightness_and_grayscale")
  ) {
    element.style.filter = element.dataset.darkify_preserved_filter;
    element.classList.remove("darkify_changed_video_brightness_and_grayscale");
    delete element.dataset.darkify_preserved_filter;
  }
}

function darkify_replace_video(videoElement, videos) {
  if (
    document
      .getElementsByTagName("html")[0]
      .classList.contains("darkify_dark_mode_enabled")
  ) {
    for (let i = 0; i < videos.length; i++) {
      const normalVideo = videos[i].normal_video;
      const normalVideoPath = new URL(normalVideo).pathname;
      const darkVideo = videos[i].dark_video;
      const darkVideoPath = new URL(darkVideo).pathname;

      if (
        videoElement.getAttribute("src") != null &&
        videoElement.getAttribute("src").includes(normalVideoPath)
      ) {
        videoElement.src = darkVideo;
        videoElement.classList.add("darkify_replaced_video");
      }

      if (videoElement.querySelectorAll("source") != null) {
        let sources = videoElement.querySelectorAll("source");
        for (let j = 0; j < sources.length; j++) {
          if (
            sources[j].getAttribute("src") != null &&
            sources[j].getAttribute("src").includes(normalVideoPath)
          ) {
            sources[j].src = darkVideo + "?_=" + Date.now();
            videoElement.classList.add("darkify_replaced_video");
            videoElement.load();
          }
        }
      }
    }
  } else {
    if (videoElement.classList.contains("darkify_replaced_video")) {
      for (let i = 0; i < videos.length; i++) {
        const normalVideo = videos[i].normal_video;
        const normalVideoPath = new URL(normalVideo).pathname;
        const darkVideo = videos[i].dark_video;
        const darkVideoPath = new URL(darkVideo).pathname;

        if (
          videoElement.getAttribute("src") != null &&
          videoElement.getAttribute("src").includes(darkVideoPath)
        ) {
          videoElement.src = normalVideo;
          videoElement.classList.remove("darkify_replaced_video");
        }

        if (videoElement.querySelectorAll("source") != null) {
          let sources = videoElement.querySelectorAll("source");
          for (let j = 0; j < sources.length; j++) {
            if (
              sources[j].getAttribute("src") != null &&
              sources[j].getAttribute("src").includes(darkVideoPath)
            ) {
              sources[j].src = normalVideo + "?_=" + Date.now();
              videoElement.classList.remove("darkify_replaced_video");
              videoElement.load();
            }
          }
        }
      }
    }
  }
}

/* ==========================================================================
   Generated-box surfaces — ::before / ::after
   --------------------------------------------------------------------------
   Every writer above reaches an element by putting something ON it: a
   `darkify_style_*` class, or an inline declaration. A generated box has no
   node to put anything on, so all of them are blind to it.

   That blind spot is exactly where a modern page builder keeps its section
   backgrounds. Spectra, Elementor, Kadence, Divi and GenerateBlocks all paint a
   container's gradient, overlay or tint on `::before`/`::after` rather than on
   the container itself, because a generated box can be stacked, blended and
   faded independently of the content sitting above it. The container is left
   `background-color: transparent`.

   To the classifier such a container looks like a bare text wrapper, so it is
   given `darkify_style_txt*`: its copy is repainted for a dark page while the
   surface behind that copy is still the light-mode gradient. That is the
   washed-out hero — pale grey type on a white section, unreadable — and it gets
   worse the more of a design lives in the builder rather than in the theme.

   A stylesheet rule is the only thing that can reach a generated box, so this
   layer writes one. Each element that needs it gets a short id, and one rule per
   pseudo goes into a single shared stylesheet keyed on that id. Three things
   fall out of doing this in CSS rather than inline:

     * the rules are prefixed with `.darkify_dark_mode_enabled`, so switching
       back to light unpaints them with no restore pass and nothing to remember;
     * the colours come from the same darkify_transform_color() the element path
       uses, so the palette presets, Brand Colors, the gradient setting and the
       manual colour overrides all apply to a generated box exactly as they do
       to a real one, with no second implementation to keep in step;
     * one stylesheet replaces the one-<style>-node-per-element the previous
       pseudo handler created, which on a builder page meant hundreds of nodes
       in <head> and a style recalculation for each of them.
   ========================================================================== */

var DARKIFY_PSEUDO_NAMES = ["before", "after"];

/**
 * Elements that never render generated content.
 *
 * Listed to be skipped rather than handled: `::before` on an `<img>` or an
 * `<input>` produces no box at all, so probing one costs two style resolutions
 * and can only ever return nothing. On an image-heavy page that is most of the
 * document.
 */
var DARKIFY_NO_GENERATED_BOX = {
  img: 1,
  br: 1,
  hr: 1,
  input: 1,
  select: 1,
  textarea: 1,
  option: 1,
  iframe: 1,
  video: 1,
  audio: 1,
  canvas: 1,
  embed: 1,
  object: 1,
  source: 1,
  track: 1,
  meta: 1,
  link: 1,
  script: 1,
  style: 1,
};

/**
 * Ceiling on how many elements may carry a generated-box rule.
 *
 * The rule table is keyed by id and an id is never reused, so on a long-lived
 * SPA — an admin screen, a filtered shop archive — it would otherwise grow for
 * as long as the tab stays open. Well above what a real page needs: the site
 * that motivated this layer uses eleven.
 */
var DARKIFY_PSEUDO_RULE_LIMIT = 4000;

var darkify_pseudo_sheet_node = null;
var darkify_pseudo_rules = Object.create(null);
var darkify_pseudo_rule_count = 0;
var darkify_pseudo_flush_scheduled = false;
var darkify_pseudo_seq = 0;

/** The single stylesheet every generated-box rule is written into. */
function darkify_pseudo_sheet() {
  if (darkify_pseudo_sheet_node && darkify_pseudo_sheet_node.parentNode) {
    return darkify_pseudo_sheet_node;
  }

  var node = document.getElementById("darkify-pseudo-surfaces");
  if (!node) {
    node = document.createElement("style");
    node.id = "darkify-pseudo-surfaces";
    // Marked so the engine never walks into its own stylesheet.
    node.className = "darkify_ignore";
    (document.head || document.documentElement).appendChild(node);
  }

  darkify_pseudo_sheet_node = node;
  return node;
}

/**
 * Write the collected rules out, at most once per frame.
 *
 * Batched for the same reason the DOM walk is: one pass over a builder page
 * hands this hundreds of elements, and rewriting the sheet per element would
 * invalidate style for the whole document each time.
 */
function darkify_flush_pseudo_rules(immediate) {
  if (!immediate) {
    if (darkify_pseudo_flush_scheduled) {
      return;
    }
    darkify_pseudo_flush_scheduled = true;

    var run = function () {
      darkify_pseudo_flush_scheduled = false;
      darkify_flush_pseudo_rules(true);
    };

    if (typeof requestAnimationFrame === "function") {
      requestAnimationFrame(run);
    } else {
      setTimeout(run, 0);
    }
    return;
  }

  darkify_pseudo_flush_scheduled = false;

  var css = "";
  for (var id in darkify_pseudo_rules) {
    css += darkify_pseudo_rules[id];
  }

  darkify_pseudo_sheet().textContent = css;
}

/** A colour that paints nothing, in any of the forms a computed style reports. */
function darkify_is_transparent_color(value) {
  if (!value) {
    return true;
  }
  var color = darkify_parse_color(value);
  return !color || color.a === 0;
}

/**
 * Whether a generated box is off limits.
 *
 * Free ships no pseudo-level allow/deny list, so nothing is excluded here — the
 * element-level exclusions are applied by the caller. Pro overrides this with
 * the pseudo halves of its Allowed / Disallowed Elements settings, which is why
 * it is a named function rather than inlined.
 */
function darkify_pseudo_is_disallowed(element, pseudo) {
  return false;
}

/**
 * The dark counterpart of a generated box's surface colour.
 *
 * On the frontend this is the ordinary transform, so a brand-coloured overlay
 * keeps its hue exactly as a brand-coloured element does.
 *
 * wp-admin takes the other branch, and deliberately. The adaptive layer is off
 * there (see darkify_adaptive_layer_enabled) because the admin's colours are
 * WordPress's own chrome rather than a design worth preserving, and an editor
 * full of preserved accents turns into one tinted wash. A generated box is held
 * to the same rule: it goes onto the neutral surface ramp whatever hue it
 * started with — which is also what the pseudo handler before this one did, so
 * admin screens keep the behaviour they had.
 */
function darkify_pseudo_surface_color(value, element) {
  var control = darkify_is_control_sized(element);

  if (darkify_adaptive_layer_enabled) {
    return darkify_transform_color(value, "background", {
      force: true,
      neutrals: true,
      control: control,
    });
  }

  var color = darkify_parse_color(value);
  return color ? darkify_surface_color(darkify_rgb_to_hsl(color), control) : "";
}

/**
 * The dark-mode declarations one generated box needs, or "" if it needs none.
 *
 * `ownerStyle` is the element's own computed style, passed in rather than read
 * again: it is the reference for deciding which of the box's colours are its
 * own and which are merely inherited.
 */
function darkify_pseudo_declarations(element, style, ownerStyle) {
  var out = "";

  var image = style.backgroundImage;
  var has_image = !!image && image !== "none";
  // A `url()` on a generated box is a picture, and pictures already belong to
  // darkify_darken_bg_image(), which writes this same pseudo through a rule of
  // its own. Recolouring the declaration here would be two writers fighting
  // over one property, so the picture case is left entirely to that one.
  var has_url = has_image && image.indexOf("url(") !== -1;
  var has_gradient = has_image && image.indexOf("gradient(") !== -1;

  if (has_gradient && !has_url && darkify_gradient_mode !== "keep") {
    // A generated box carrying a scrim is the single most common way a builder
    // draws an overlay (`.elementor-background-overlay::before` and friends),
    // so the same rule the element path uses applies here: keep its alpha, and
    // write nothing at all when the scrim is already dark.
    if (darkify_is_scrim_gradient(image)) {
      var scrim_image = darkify_scrim_gradient(image);
      if (scrim_image !== image) {
        out += "background-image:" + scrim_image + " !important;";
      }
    } else {
      out +=
        "background-image:" +
        (darkify_gradient_mode === "flatten"
          ? "none"
          : darkify_recolor_gradient(image)) +
        " !important;";
    }
  }

  if (!has_url) {
    var background = style.backgroundColor;
    if (!darkify_is_transparent_color(background)) {
      // Neutrals included, and forced past the Brand Colors setting, for the
      // same reason the gradient path includes them: a generated box has no
      // `darkify_style_*` class to fall back on, so a grey left alone here is
      // not deferred to the class repaint — it is simply left light.
      var surface = darkify_pseudo_surface_color(background, element);
      if (surface) {
        out += "background-color:" + surface + " !important;";
      }
    }
  }

  // Only a colour the box states for itself. An inherited one belongs to the
  // element, which the class-based repaint has already handled — and because a
  // generated box inherits the *repainted* value, an inherited colour read here
  // is Darkify's own output. Deriving from that would drift it a little further
  // on every pass.
  var color = style.color;
  if (
    !darkify_is_transparent_color(color) &&
    (!ownerStyle || color !== ownerStyle.color)
  ) {
    var foreground = darkify_transform_color(color, "text", {
      force: true,
      neutrals: true,
    });
    if (foreground) {
      out += "color:" + foreground + " !important;";
    }
  }

  // Borders are how a generated box draws a tooltip arrow, a caret or a rule.
  // Each side is taken separately because that is how those shapes are built:
  // one side carries the colour and the other three are transparent, and
  // painting the transparent ones would turn an arrow into a square.
  var sides = ["top", "right", "bottom", "left"];
  for (var i = 0; i < sides.length; i++) {
    var side = sides[i];
    if (style.getPropertyValue("border-" + side + "-style") === "none") {
      continue;
    }
    if (parseFloat(style.getPropertyValue("border-" + side + "-width")) === 0) {
      continue;
    }

    var line = style.getPropertyValue("border-" + side + "-color");
    if (darkify_is_transparent_color(line)) {
      continue;
    }
    if (ownerStyle && line === ownerStyle.getPropertyValue("border-" + side + "-color")) {
      continue;
    }

    var edge = darkify_transform_color(line, "border", {
      force: true,
      neutrals: true,
    });
    if (edge) {
      out += "border-" + side + "-color:" + edge + " !important;";
    }
  }

  return out;
}

/**
 * Bring an element's generated boxes into dark mode.
 *
 * Runs once per element and then marks it. Re-reading on a later pass would be
 * worse than useless: by then the box resolves through this layer's own rule,
 * so the "light-mode colour" it reports is a dark one, and each pass would walk
 * the colour further from the design. That is the same once-only contract the
 * gradient and shadow writers keep, for the same reason.
 *
 * Nothing has to be undone when dark mode goes off: the rules are written under
 * `.darkify_dark_mode_enabled`, so the cascade does it.
 */
function darkify_process_pseudo_surfaces(element, ownerStyle) {
  if (DARKIFY_NO_GENERATED_BOX[element.nodeName.toLowerCase()]) {
    return;
  }
  if (element.hasAttribute("data-darkify-pseudo")) {
    return;
  }
  if (darkify_is_excluded_from_adaptation(element)) {
    return;
  }
  if (darkify_pseudo_rule_count >= DARKIFY_PSEUDO_RULE_LIMIT) {
    return;
  }

  var rules = "";

  for (var i = 0; i < DARKIFY_PSEUDO_NAMES.length; i++) {
    var pseudo = DARKIFY_PSEUDO_NAMES[i];
    if (darkify_pseudo_is_disallowed(element, pseudo)) {
      continue;
    }

    var style;
    try {
      style = window.getComputedStyle(element, "::" + pseudo);
    } catch (e) {
      continue;
    }

    // `content: none` means no box was generated, so there is nothing painted
    // to bring across — the overwhelmingly common case, and the cheapest test
    // available for it.
    if (!style || style.content === "none" || style.display === "none") {
      continue;
    }

    var declarations = "";
    try {
      declarations = darkify_pseudo_declarations(element, style, ownerStyle);
    } catch (e) {
      declarations = "";
    }
    if (!declarations) {
      continue;
    }

    rules +=
      '.darkify_dark_mode_enabled [data-darkify-pseudo="__ID__"]::' +
      pseudo +
      "{" +
      declarations +
      "}";
  }

  if (!rules) {
    return;
  }

  // The id is minted only once something is actually going to be written, so an
  // ordinary element — a clearfix, a list marker, anything whose generated box
  // paints nothing — leaves no attribute behind and costs no rule.
  var id = "p" + ++darkify_pseudo_seq;
  element.setAttribute("data-darkify-pseudo", id);

  darkify_pseudo_rules[id] = rules.split("__ID__").join(id);
  darkify_pseudo_rule_count++;
  darkify_flush_pseudo_rules(false);
}

/**
 * Kept for the init path, which calls it once the first walk is through.
 *
 * The rules are now written as each element is reached rather than collected
 * and applied in a second sweep — that is what makes AJAX and builder-injected
 * content work, since nothing re-runs a sweep for it — so all this has left to
 * do is make sure the last batch is on the page.
 */
function darkify_apply_pseudo_bg_styles() {
  darkify_flush_pseudo_rules(true);
}

function darkify_fix_background_color_alpha(element) {
  if (
    document
      .getElementsByTagName("html")[0]
      .classList.contains("darkify_dark_mode_enabled")
  ) {
    if (element.hasAttribute("data-darkify_alpha_bg")) {
      var alphaValue = element.dataset.darkify_alpha_bg
        .replace("rgba(", "")
        .replace(")", "")
        .split(",")[3]
        .trim();
      var backgroundColor = window.getComputedStyle(
        element,
        null,
      ).backgroundColor;

      if (!backgroundColor.includes("rgba")) {
        element.style.setProperty(
          "background-color",
          backgroundColor
            .replace(")", ", " + alphaValue + ")")
            .replace("rgb", "rgba"),
          "important",
        );
      }
    }
  } else if (element.hasAttribute("data-darkify_alpha_bg")) {
    element.style.backgroundColor = "";
  }
}

function darkify_implement_secondary_bg() {
  let maxAreaElement = null;
  let maxArea = 0;

  const elements = document.querySelectorAll(
    "* :not(head, title, link, meta, script, style, defs, filter)",
  );

  for (let i = 0; i < elements.length; i++) {
    const element = elements[i];
    if (element.hasAttribute("data-darkify_secondary_bg_finder")) {
      const secondaryBgColor = element.dataset.darkify_secondary_bg_finder;
      if (
        secondaryBgColor !== "transparent" &&
        secondaryBgColor !== "rgba(0, 0, 0, 0)"
      ) {
        const boundingRect = element.getBoundingClientRect();
        const area = boundingRect.width * boundingRect.height;
        if (area > maxArea) {
          maxArea = area;
          maxAreaElement = secondaryBgColor;
        }
      }
    }
  }

  for (let i = 0; i < elements.length; i++) {
    const element = elements[i];
    if (element.hasAttribute("data-darkify_secondary_bg_finder")) {
      if (
        element.classList.contains("darkify_style_all") ||
        element.classList.contains("darkify_style_bg_txt") ||
        element.classList.contains("darkify_style_bg_border") ||
        element.classList.contains("darkify_style_bg")
      ) {
        const isDifferentSecondaryBg =
          maxAreaElement !== element.dataset.darkify_secondary_bg_finder;
        if (isDifferentSecondaryBg) {
          element.classList.add("darkify_style_secondary_bg");
        }
      }
      delete element.dataset.darkify_secondary_bg_finder;
    }
  }

  darkify_secondary_bg_color = maxAreaElement;
}

function darkify_recheck_on_css_loaded_later() {
  document
    .querySelectorAll(
      ".darkify_style_txt_border, .darkify_style_txt, .darkify_style_border",
    )
    .forEach(function (element) {
      const computedStyle = window.getComputedStyle(element, null);
      const backgroundColor = computedStyle.backgroundColor;
      if (
        backgroundColor !== "rgba(0, 0, 0, 0)" &&
        backgroundColor !== "rgba(255, 255, 255, 0)"
      ) {
        darkify_process_element(element);
      }
    });
}

function darkify_check_preloading() {
  let isPreloaded = false;
  const lastState = localStorage.darkify_last_state
    ? localStorage.darkify_last_state
    : "not_set";
  const adminPanelLastState = localStorage.darkify_admin_panel_last_state
    ? localStorage.darkify_admin_panel_last_state
    : "not_set";

  if (darkify_is_this_admin_panel === "1") {
    if (adminPanelLastState === "1") {
      isPreloaded = true;
    }
  } else {
    if (lastState === "1" || lastState === "0") {
      if (lastState === "1") {
        isPreloaded = true;
      }
    } else {
      if (darkify_enable_default_dark_mode === "1") {
        isPreloaded = true;
      }
      if (darkify_enable_time_based_dark === "1") {
        const currentDate = new Date();
        const darkStart = new Date();
        const darkStop = new Date();
        darkStart.setHours(
          parseInt(darkify_time_based_dark_start.split(":")[0]),
        );
        darkStart.setMinutes(
          parseInt(darkify_time_based_dark_start.split(":")[1]),
        );
        darkStop.setHours(parseInt(darkify_time_based_dark_stop.split(":")[0]));
        darkStop.setMinutes(
          parseInt(darkify_time_based_dark_stop.split(":")[1]),
        );

        if (
          parseInt(darkify_time_based_dark_stop.split(":")[0]) >=
          parseInt(darkify_time_based_dark_start.split(":")[0])
        ) {
          if (
            currentDate.getTime() > darkStart.getTime() &&
            currentDate.getTime() < darkStop.getTime()
          ) {
            isPreloaded = true;
          }
        } else if (currentDate.getHours() > 12) {
          if (
            currentDate.getTime() > darkStart.getTime() &&
            currentDate.getTime() > darkStop.getTime()
          ) {
            isPreloaded = true;
          }
        } else if (
          currentDate.getTime() < darkStart.getTime() &&
          currentDate.getTime() < darkStop.getTime()
        ) {
          isPreloaded = true;
        }
      }
    }
  }

  if (
    darkify_is_this_admin_panel === "0" &&
    darkify_enable_os_aware === "1" &&
    window.matchMedia &&
    window.matchMedia("(prefers-color-scheme: dark)").matches &&
    lastState !== "1" &&
    lastState !== "0"
  ) {
    isPreloaded = true;
  }

  return isPreloaded;
}

/* ── Self-theming app detection ───────────────────────────────────────────
 *
 * Darkify darkens a page by reading each element's colours and stamping an
 * `!important` override on top. That is right for classic admin markup, which
 * takes its colours from the cascade — but a modern admin app (React, Vue, …)
 * built on design tokens does not. It ships its own complete dark theme keyed
 * on `dark` on <html>, which Darkify now sets, so by the time the engine walks
 * the page that app has ALREADY themed itself. Repainting it then does not help;
 * it flattens the palette, collapsing cards, popovers and page background into
 * one flat grey and fighting a theme that was already correct.
 *
 * So the engine needs to recognise "this subtree already handled it" — without
 * knowing anything about which plugin drew it. The signal used here is the same
 * convention that made the app dark in the first place:
 *
 *   1. The page ships a stylesheet rule that references the `dark` class AND
 *      declares custom properties — i.e. a `.dark { --background: … }` token
 *      block. That is the fingerprint of a class-switched token theme
 *      (Tailwind's class strategy, shadcn/ui, and Darkify's own React admin).
 *   2. An element whose resolved colour IS one of those token values is being
 *      painted by that theme, so it is left alone, along with its subtree.
 *
 * Both halves are structural, not nominal: no plugin name, no container id, no
 * DOM-shape assumption. A page with no such token block yields an empty set and
 * every element takes exactly the path it took before, so classic admin screens
 * are untouched.
 */

var darkify_dark_token_names = null;
var darkify_dark_token_colors = null;
var darkify_dark_token_signature = null;

/**
 * Names of the custom properties a `dark`-keyed rule declares, read once from
 * the page's own stylesheets.
 *
 * A rule only counts when it BOTH references the class and declares `--*`
 * properties. That pairing is what separates a theme's token block from an
 * ordinary dark variant utility (Tailwind compiles `dark:bg-card` to a selector
 * that also mentions the class but only sets `background-color`), and it is why
 * the class-name test does not need to be clever.
 */
function darkify_collect_dark_token_names() {
  if (darkify_dark_token_names !== null) {
    return darkify_dark_token_names;
  }

  var names = {};
  // `.dark` not followed by a word character or hyphen, so Darkify's own
  // `.darkify_*` classes (and any `.dark-theme` of someone else's) don't count.
  var dark_class = /\.dark(?![\w-])/;

  function scan(rules) {
    for (var i = 0; i < rules.length; i++) {
      var rule = rules[i];

      if (
        rule.selectorText &&
        rule.style &&
        dark_class.test(rule.selectorText)
      ) {
        for (var j = 0; j < rule.style.length; j++) {
          var prop = rule.style[j];
          if (prop.charAt(0) === "-" && prop.charAt(1) === "-") {
            names[prop] = true;
          }
        }
      }

      // @media / @supports nest their own rule lists — and so, since CSS
      // Nesting shipped, does an ordinary style rule, which now exposes an
      // empty `cssRules` of its own. Recursing on existence rather than on
      // length therefore swallowed EVERY top-level rule (each one looked like a
      // group with no children), which is why this found nothing at all.
      if (rule.cssRules && rule.cssRules.length) {
        scan(rule.cssRules);
      }
    }
  }

  var sheets = document.styleSheets;
  for (var s = 0; s < sheets.length; s++) {
    try {
      if (sheets[s].cssRules) {
        scan(sheets[s].cssRules);
      }
    } catch (e) {
      // Cross-origin stylesheet — unreadable by design, and never one of ours.
    }
  }

  darkify_dark_token_names = Object.keys(names);
  return darkify_dark_token_names;
}

/**
 * Those tokens resolved to real colours, as a lookup keyed by computed value.
 *
 * Custom properties compute to their raw token text (`oklch(…)`, `#0c1116`),
 * which never string-matches the `rgb(…)` an element reports, so each one is
 * resolved through a probe element and compared in that normalised form. The
 * probe lives in the document so it inherits the same theme the app sees.
 *
 * Cached against the root's class list: that is what carries `dark` and the
 * palette classes, so the cache drops exactly when the resolved values change.
 */
function darkify_dark_theme_colors() {
  var names = darkify_collect_dark_token_names();
  if (names.length === 0 || !document.body) {
    return null;
  }

  var signature = document.documentElement.className;
  if (darkify_dark_token_colors !== null && darkify_dark_token_signature === signature) {
    return darkify_dark_token_colors;
  }

  var probe = document.createElement("span");
  // Excluded from the engine and from layout; purely a colour resolver.
  probe.className = "darkify_ignore";
  probe.style.cssText =
    "position:absolute;left:-9999px;top:-9999px;width:0;height:0;visibility:hidden;pointer-events:none;";
  document.body.appendChild(probe);

  var colors = {};
  for (var i = 0; i < names.length; i++) {
    probe.style.color = "";
    probe.style.color = "var(" + names[i] + ")";
    var resolved = window.getComputedStyle(probe).color;
    // Tokens that aren't colours (radii, spacing) simply don't resolve to one.
    if (resolved && resolved.indexOf("rgb") === 0) {
      colors[resolved] = true;
    }
  }

  probe.parentNode.removeChild(probe);

  darkify_dark_token_colors = colors;
  darkify_dark_token_signature = signature;
  return colors;
}

/** Whether self-theming detection should run at all on this page. */
function darkify_self_theming_active() {
  return (
    typeof darkify_is_this_admin_panel !== "undefined" &&
    darkify_is_this_admin_panel === "1" &&
    // Only meaningful while the class is on: with it off the tokens resolve to
    // the app's LIGHT values, and matching those would skip the very elements
    // that still need darkening.
    document.documentElement.classList.contains("dark")
  );
}

/** Cheap ancestor check — an already-identified subtree is skipped wholesale. */
function darkify_in_self_themed_subtree(element) {
  return (
    darkify_self_theming_active() &&
    !!element.closest &&
    !!element.closest(".darkify_self_themed")
  );
}

/**
 * Mark `element` when its own colours come from the page's dark token theme.
 *
 * Marking the element rather than testing every node keeps this O(1) per
 * subtree: document order means the outermost themed container is reached
 * first, and everything below it then short-circuits on the ancestor check —
 * including nodes React mounts later, which is what makes SPA route changes and
 * late-rendered components work without re-detection.
 */
function darkify_mark_if_self_themed(element, computedStyle) {
  if (!darkify_self_theming_active()) {
    return false;
  }

  // Never hand the whole document over: <html>/<body> belong to wp-admin, and
  // the engine still owns the page backdrop behind any app.
  var nodeName = element.nodeName.toLowerCase();
  if (nodeName === "html" || nodeName === "body") {
    return false;
  }

  var colors = darkify_dark_theme_colors();
  if (!colors) {
    return false;
  }

  if (colors[computedStyle.color] || colors[computedStyle.backgroundColor]) {
    element.classList.add("darkify_self_themed");
    return true;
  }

  return false;
}

var DARKIFY_BORDER_SIDES = ["top", "right", "bottom", "left"];

/** Whether one border side actually paints a line. */
function darkify_border_side_paints(style, side) {
  var line = style.getPropertyValue("border-" + side + "-style");
  if (!line || line === "none" || line === "hidden") {
    return false;
  }
  if (parseFloat(style.getPropertyValue("border-" + side + "-width")) <= 0) {
    return false;
  }
  var color = darkify_parse_color(
    style.getPropertyValue("border-" + side + "-color"),
  );
  return !!color && color.a !== 0;
}

/**
 * The element's border colour for classification, and the transparent-side
 * markers that go with it.
 *
 * The shorthand `borderColor` cannot answer the question the classifier is
 * actually asking. When the four sides differ it returns a list — Woo's product
 * card reports `"rgb(51,51,51) rgba(0,0,0,0) rgba(0,0,0,0)"` — which is not
 * equal to the transparent sentinel, so the element counted as bordered and was
 * given a border class. Every side then got painted, including the three that
 * paint nothing, and the card grew a box in dark mode that it does not have in
 * light.
 *
 * Two things are wrong there and both are fixed here. A side is only a border
 * if it has a style, a width AND a colour; measuring all four says whether this
 * element has any border at all. And a side that has a width but no colour is
 * spacing — the `border: 8px solid transparent` idiom — so it is marked, and
 * the rules in client_main.css pin it back to transparent whichever colour rule
 * ends up matching.
 *
 * @return {string} A painted side's colour, or the transparent sentinel when
 *                  the design draws no border at all.
 */
function darkify_border_color_for_classification(element, style) {
  var painted = "";

  for (var i = 0; i < DARKIFY_BORDER_SIDES.length; i++) {
    var side = DARKIFY_BORDER_SIDES[i];
    var marker = "darkify_border_keep_" + side;

    if (darkify_border_side_paints(style, side)) {
      if (!painted) {
        painted = style.getPropertyValue("border-" + side + "-color");
      }
      element.classList.remove(marker);
      continue;
    }

    // Width without colour is spacing, and only spacing needs protecting — a
    // side with no width paints nothing whatever colour it is given.
    var occupies =
      parseFloat(style.getPropertyValue("border-" + side + "-width")) > 0;
    if (occupies) {
      element.classList.add(marker);
    } else {
      element.classList.remove(marker);
    }
  }

  return painted || "rgba(0, 0, 0, 0)";
}

/**
 * Whether a colour this element reports may be a frame of an animation rather
 * than a value the design chose.
 *
 * A transition makes `getComputedStyle` report the interpolated colour of the
 * moment. That matters here because the transition is usually Darkify's own
 * doing: stamping a class repaints the element, the theme animates the change,
 * and a walk that reaches the element while that is running reads a colour
 * halfway between the design's and the plugin's.
 *
 * Interpolating a transparent background to an opaque one passes through
 * `rgba(45, 45, 45, 0.62)` — partly transparent, and indistinguishable by value
 * from a translucent surface the designer meant. Recorded as one, it was then
 * re-applied as the element's "own" alpha, and a `<select>` the design left
 * open kept a half-see-through grey box. The alpha landed somewhere different
 * on every load, which is the signature of reading an animation.
 *
 * Nothing here is fixable by looking harder at the value, so the value is not
 * trusted at all when a transition covers it.
 */
function darkify_color_may_be_animating(style) {
  var duration = style.transitionDuration;
  if (!duration || /^(0s)(,\s*0s)*$/.test(duration.trim())) {
    return false;
  }

  var props = style.transitionProperty || "";
  return (
    props.indexOf("all") !== -1 ||
    props.indexOf("background") !== -1 ||
    props.indexOf("color") !== -1
  );
}

/* ==========================================================================
   Reading stable colours
   --------------------------------------------------------------------------
   The engine decides what an element is by reading its computed colours. A
   transition breaks that read: `getComputedStyle` reports the interpolated
   colour of the current frame, so what comes back is a point somewhere between
   two values rather than either of them.

   The transition is usually Darkify's own. Stamping a `darkify_style_*` class
   repaints the element, the theme animates the repaint, and the next pass — the
   class-change observer re-processing that very element — arrives mid-animation.
   It strips the classes to read the design's colours again, but the strip
   animates too, so the value it reads is still partly the plugin's.

   Everything downstream then inherits that. A transparent background
   interpolating toward an opaque one reports `rgba(45, 45, 45, 0.62)`, which is
   indistinguishable by value from a translucent surface someone designed, so it
   was recorded and re-applied as one: a `<select>` the design left open kept a
   half-see-through box, at a different opacity on every load.

   The cure is to take the element out of transition for the length of its own
   pass, which cancels anything running and makes every read land on a settled
   value. Deliberately per element and inline: a stylesheet that switched all
   transitions off around the whole walk did the same job but invalidated style
   for the entire document twice per pass, and style recalculation rose by about
   a third. `transition-property` is not inherited, so writing it here costs one
   element's recalculation — work this pass is doing anyway.
   ========================================================================== */

/**
 * SVG icons a design paints through `background-image`.
 *
 * A `<select>` gets its dropdown chevron this way, and so do accordions,
 * checkboxes, radio marks, pagination arrows and search buttons: an inline
 * `data:image/svg+xml` URI with the colour written into the markup, almost
 * always a dark grey chosen to read on a light page. Nothing in the engine
 * could touch it. The colour lives inside a URL, not in a CSS property, so no
 * class and no inline declaration reaches it, and the icon stayed `#333333` on
 * a dark background — present, correctly positioned, and invisible.
 *
 * Worse, it used to be handed to darkify_darken_bg_image(), which exists to
 * stop a photograph glaring on a dark page. An icon is the opposite case: it is
 * foreground, it is already too dark, and dimming it 60% finishes the job of
 * hiding it. An inline SVG is never a photograph, so it takes this path instead.
 *
 * The colours go through the same darkify_transform_color() as everything else,
 * in the `icon` role, so they land at the palette's foreground level and a
 * coloured icon keeps its hue.
 */
// Quoting matters here and the obvious character class gets it wrong. The
// markup inside these URIs is full of unencoded single quotes
// (`class='ast-arrow-svg'`), so a pattern that stops at the first quote of
// either kind matches nothing at all — which is exactly how the first version
// of this silently did nothing. Each quoting form gets its own alternative, and
// each one only terminates on its own delimiter.
var DARKIFY_SVG_DATA_URI =
  /url\(\s*(?:"(data:image\/svg\+xml[^"]*)"|'(data:image\/svg\+xml[^']*)'|(data:image\/svg\+xml[^)\s]*))\s*\)/gi;
var DARKIFY_SVG_PAINT =
  /(fill|stroke|stop-color|flood-color)\s*[:=]\s*(['"]?)(#[0-9a-fA-F]{3,8}|rgba?\([^)]*\))\2/g;

/** Cap on the markup this will parse — artwork is not an icon. */
var DARKIFY_SVG_MAX_LENGTH = 8000;

function darkify_recolor_svg_background(image) {
  return String(image).replace(
    DARKIFY_SVG_DATA_URI,
    function (whole, doubled, singled, bare) {
      var uri = doubled || singled || bare;
      var quote = doubled ? '"' : singled ? "'" : "";
      var comma = uri.indexOf(",");
      if (comma === -1) {
        return whole;
      }

      var head = uri.slice(0, comma + 1);
      // Base64 payloads are not worth decoding for this, and an icon shipped
      // that way is rare enough not to matter.
      if (head.indexOf("base64") !== -1) {
        return whole;
      }

      var markup;
      try {
        markup = decodeURIComponent(uri.slice(comma + 1));
      } catch (e) {
        return whole;
      }
      if (markup.length > DARKIFY_SVG_MAX_LENGTH) {
        return whole;
      }

      var changed = false;
      var next = markup.replace(
        DARKIFY_SVG_PAINT,
        function (match, prop, q, color) {
          var mapped = "";
          try {
            mapped = darkify_transform_color(color, "icon", {
              force: true,
              neutrals: true,
            });
          } catch (e) {
            mapped = "";
          }
          if (!mapped) {
            return match;
          }
          changed = true;
          // Rebuilt around the colour so the original separator
          // (`fill="x"` vs `fill:x`) survives untouched.
          var at = match.indexOf(color);
          return match.slice(0, at) + mapped + match.slice(at + color.length);
        },
      );

      if (!changed) {
        return whole;
      }

      return "url(" + quote + head + encodeURIComponent(next) + quote + ")";
    },
  );
}

var darkify_icon_seq = 0;

/**
 * Give an element's SVG background icons a dark-mode colour.
 *
 * Written as a rule in the shared stylesheet rather than inline, for the reason
 * the generated-box layer uses it: the rule is gated on
 * `darkify_dark_mode_enabled`, so switching back to light unpaints it with no
 * restore pass and nothing to remember.
 */
function darkify_process_icon_background(element, style) {
  if (element.hasAttribute("data-darkify-icon")) {
    return;
  }
  if (darkify_pseudo_rule_count >= DARKIFY_PSEUDO_RULE_LIMIT) {
    return;
  }

  var image = style.backgroundImage;
  var next = darkify_recolor_svg_background(image);
  if (next === image) {
    return;
  }

  var id = "i" + ++darkify_icon_seq;
  element.setAttribute("data-darkify-icon", id);
  darkify_pseudo_rules[id] =
    '.darkify_dark_mode_enabled [data-darkify-icon="' +
    id +
    '"]{background-image:' +
    next +
    " !important;}";
  darkify_pseudo_rule_count++;
  darkify_flush_pseudo_rules(false);
}

/**
 * Take the page out of transition while a pass reads and repaints.
 *
 * Why this exists is unchanged: a running colour transition makes
 * `getComputedStyle` report the interpolated colour rather than the settled
 * one, so an element caught mid-animation is classified on a colour that
 * belongs to neither state. Suppressing transitions makes the read truthful.
 *
 * How it is done is what changed, and it is the single most expensive line the
 * old engine had. It used to write `transition-property: none` *inline on each
 * element* and then immediately call `getComputedStyle` on that same element.
 * A style write followed by a style read is a forced synchronous recalculation,
 * and doing it per element means the browser recalculates styles once per
 * element instead of once per pass — 2,806 forced recalcs on the measured page,
 * which is where its 1.34 seconds of style recalculation came from. It also
 * wrote and removed two inline properties per element for a value that is
 * identical for all of them.
 *
 * One class on `<html>`, applied before the pass and removed after it, produces
 * exactly the same suppression for one style invalidation total. The rule lives
 * in client_main.css next to the engine's other structural rules.
 */
function darkify_begin_pass() {
  document.documentElement.classList.add("darkify_suspend_transitions");
}

function darkify_end_pass() {
  document.documentElement.classList.remove("darkify_suspend_transitions");
}

/**
 * Elements waiting to have `darkify_no_transition` taken off again.
 *
 * The marker must NOT come off in the same task that assigned the colour.
 * Style is computed once, at the end of a task, so a synchronous remove means
 * the browser only ever sees the finished state — new colour, transitions live
 * — and eases into it, which is the very thing being prevented. Releasing on
 * the next frame lets the colour land while transitions are still off; by then
 * nothing is changing colour any more, so taking the marker off animates
 * nothing.
 *
 * Batched into one callback rather than a forced style flush per element:
 * per-element synchronous recalculation is the cost that darkify_begin_pass()
 * exists to avoid, and this path must not reintroduce it.
 */
const darkify_no_transition_pending = new Set();
let darkify_no_transition_scheduled = false;

function darkify_flush_no_transition() {
  darkify_no_transition_scheduled = false;

  // Inside the class-watch suspension: elements_class_changed watches `class`
  // across the whole document, so removing the marker would otherwise queue
  // every one of these elements for a re-read that has nothing to look at.
  darkify_suspend_class_watch(function () {
    darkify_no_transition_pending.forEach(function (el) {
      el.classList.remove("darkify_no_transition");
    });
    darkify_no_transition_pending.clear();
  });
}

function darkify_release_no_transition(element) {
  darkify_no_transition_pending.add(element);
  if (darkify_no_transition_scheduled) {
    return;
  }
  darkify_no_transition_scheduled = true;
  if (typeof requestAnimationFrame === "function") {
    requestAnimationFrame(darkify_flush_no_transition);
  } else {
    setTimeout(darkify_flush_no_transition, 0);
  }
}

/**
 * Process one element, suppressing its transitions only when that is right.
 *
 * `transition-property` is not inherited, so the marker costs this element's own
 * style recalculation — work the pass is doing anyway when it reads it — and
 * nothing for the rest of the document.
 */
function darkify_process_element(element, is_reprocess) {
  // Before anything else, and before the counter: an off-limits element is not
  // "processed", it is not seen. Nothing below may run for it — not the read,
  // not the bookkeeping attributes, not the transition marker, not the
  // `darkify_processed` class. See darkify_is_off_limits().
  if (darkify_is_off_limits(element)) {
    return;
  }

  darkify_debug_count("elements_processed");

  // Only one case needs the transition marker, so both of the others leave
  // together:
  //
  //   - A whole-document pass is already out of transition for its full
  //     duration via darkify_begin_pass(); marking per element would be
  //     redundant work on every element on the page.
  //   - A re-read must KEEP its transitions, so the animation that triggered it
  //     — an accordion opening, a dropdown, a hover state — still eases.
  //
  // What is left is an element being coloured for the FIRST time: a node that
  // arrived after the initial walk. Dark mode has to land on it instantly,
  // because with transitions live it fades from its light colour to its dark
  // one in full view. That fade is the background flicker, and it shows up on a
  // hard reload because with nothing cached far more of the page arrives late
  // and is coloured here rather than by the walk the FOUC guard hides.
  //
  // Blanket suppression used to cover the re-read case too, which is what made
  // those animations snap instead of ease. The caller is the only thing that
  // can separate them: darkify_drain_class_changes() strips `darkify_processed`
  // before calling in, so the element's own classes cannot be the signal.
  if (darkify_pass_is_whole_document || is_reprocess) {
    darkify_process_element_settled(element);
    return;
  }

  element.classList.add("darkify_no_transition");
  try {
    darkify_process_element_settled(element);
  } finally {
    darkify_release_no_transition(element);
  }
}

/**
 * Colour properties the engine reads and therefore needs settled values for.
 * Keyed by the camelCase name a keyframe uses, valued by the CSS name.
 */
var DARKIFY_SETTLE_PROPS = {
  color: "color",
  backgroundColor: "background-color",
  backgroundImage: "background-image",
  borderTopColor: "border-top-color",
  borderRightColor: "border-right-color",
  borderBottomColor: "border-bottom-color",
  borderLeftColor: "border-left-color",
  outlineColor: "outline-color",
  fill: "fill",
  stroke: "stroke",
};

function darkify_css_to_camel(prop) {
  return prop.replace(/-([a-z])/g, function (m, c) {
    return c.toUpperCase();
  });
}

/**
 * A view of an element's computed style in which any colour currently mid
 * transition reads as the value it is heading for.
 *
 * The engine has to map the colour the design actually asked for. Sampling
 * `getComputedStyle` while a transition is running gives an interpolated
 * value instead — the frame the animation happens to be on — and mapping that
 * produces a wrong colour that also changes from load to load.
 *
 * The engine used to solve this by cancelling the element's transitions for
 * the duration of the read (`transition-property: none`, read, un-cancel).
 * That could not work: the read itself is a forced style recalculation, and a
 * recalculation is when the browser decides whether a transition starts, so it
 * saw the cancellation and the element jumped to its end value with nothing
 * left to animate. Every accordion, dropdown and hover state the engine
 * touched snapped instead of easing.
 *
 * A running transition already knows where it is going, so ask it rather than
 * interfering: the last keyframe of a CSSTransition holds the end value. That
 * is the settled colour, obtained without touching the page's animation and
 * without a second pass to correct a value read too early.
 *
 * Returns `computedStyle` itself unless something is actually transitioning,
 * so the normal case allocates nothing. Keyframe animations are deliberately
 * ignored — they loop, and have no single value to settle on.
 *
 * @param {Element} element
 * @param {CSSStyleDeclaration} computedStyle
 * @return {CSSStyleDeclaration} The style, or a view of it with settled colours.
 */
function darkify_settled_style(element, computedStyle) {
  if (typeof element.getAnimations !== "function") {
    return computedStyle;
  }

  var running;
  try {
    running = element.getAnimations();
  } catch (e) {
    return computedStyle;
  }
  if (!running || running.length === 0) {
    return computedStyle;
  }

  var settled = null;
  for (var i = 0; i < running.length; i++) {
    var anim = running[i];
    var cssProp = anim.transitionProperty;
    if (!cssProp) {
      continue;
    }
    var camel = darkify_css_to_camel(cssProp);
    if (!DARKIFY_SETTLE_PROPS[camel]) {
      continue;
    }
    var frames;
    try {
      frames = anim.effect.getKeyframes();
    } catch (e) {
      continue;
    }
    var last = frames && frames[frames.length - 1];
    if (!last || last[camel] == null) {
      continue;
    }
    settled = settled || {};
    settled[camel] = last[camel];
  }

  if (!settled) {
    return computedStyle;
  }

  return new Proxy(computedStyle, {
    get: function (target, key) {
      if (typeof key === "string" && settled[key] !== undefined) {
        return settled[key];
      }
      if (key === "getPropertyValue") {
        return function (prop) {
          var camel = darkify_css_to_camel(prop);
          return settled[camel] !== undefined
            ? settled[camel]
            : target.getPropertyValue(prop);
        };
      }
      var value = target[key];
      return typeof value === "function" ? value.bind(target) : value;
    },
  });
}

function darkify_process_element_settled(element) {
  // Before any style read: everything under an app that themes itself is left
  // exactly as that app painted it.
  if (darkify_in_self_themed_subtree(element)) {
    return;
  }

  var computedStyle = window.getComputedStyle(element, null);

  // Anything mid transition reads as the colour it is heading for, so the
  // mapping below is made from the design's value and not from whichever
  // animation frame this pass happened to land on.
  computedStyle = darkify_settled_style(element, computedStyle);

  var old_transition = "";

  // if (computedStyle.transition !== "all 0s ease 0s") {
  //   old_transition = computedStyle.transition;
  //   // element.style.setProperty("transition", "none");
  // }

  if (
    element.classList.contains("darkify_style_all") ||
    element.classList.contains("darkify_style_bg_txt") ||
    element.classList.contains("darkify_style_bg_border") ||
    element.classList.contains("darkify_style_txt_border") ||
    element.classList.contains("darkify_style_bg") ||
    element.classList.contains("darkify_style_txt") ||
    element.classList.contains("darkify_style_border") ||
    element.classList.contains("darkify_style_secondary_bg")
  ) {
    element.classList.remove("darkify_style_all");
    element.classList.remove("darkify_style_bg_txt");
    element.classList.remove("darkify_style_bg_border");
    element.classList.remove("darkify_style_txt_border");
    element.classList.remove("darkify_style_bg");
    element.classList.remove("darkify_style_txt");
    element.classList.remove("darkify_style_border");
    element.classList.remove("darkify_style_secondary_bg");
  }

  // Before anything reads `computedStyle` below: drop the translucency-fix
  // background this pass stamped on a previous visit. It writes
  // `background-color` inline, so leaving it in place would let Darkify's own
  // output be read back below as the design's alpha and re-applied on top of
  // itself — the element fading a little further on every pass.
  if (element.hasAttribute("data-darkify_alpha_bg")) {
    element.style.removeProperty("background-color");
    element.removeAttribute("data-darkify_alpha_bg");
  }

  // The deterministic writers' inline declarations have to come off for the
  // same reason: they carry `!important`, so they'd survive the class reset
  // above and answer for the element below, where everything is classified
  // from `computedStyle`. Each one re-applies at the end of this function (or
  // is left off, if the design no longer needs it), so nothing is lost.
  if (element.classList.contains("darkify_gradient_flattened")) {
    darkify_restore_inline(element, "darkifyGradientPrev", [
      "background-image",
      "background-color",
    ]);
    element.classList.remove("darkify_gradient_flattened");
  }
  if (element.classList.contains("darkify_shadow_neutralized")) {
    darkify_restore_inline(element, "darkifyShadowPrev", DARKIFY_SHADOW_PROPS);
    element.classList.remove("darkify_shadow_neutralized");
  }
  if (element.classList.contains("darkify_icon_recoloured")) {
    darkify_restore_inline(element, "darkifyIconPrev", ["color", "fill", "stroke"]);
    element.classList.remove("darkify_icon_recoloured");
  }
  if (element.classList.contains("darkify_overlay_flattened")) {
    darkify_restore_inline(element, "darkifyOverlayPrev", ["background-color"]);
    element.classList.remove("darkify_overlay_flattened");
  }
  if (element.classList.contains("darkify_color_overridden")) {
    darkify_restore_inline(element, "darkifyOverridePrev", DARKIFY_OVERRIDE_PROPS);
    element.classList.remove("darkify_color_overridden");
  }

  // Same reasoning for the transparent-side markers, and they would fail in a
  // nastier way if left on: each one forces its side to `transparent`, which is
  // exactly the state that causes it to be applied. Measured again while still
  // marked, every marked side re-measures as transparent and the marker becomes
  // permanent — including on an element whose border the design does draw.
  // Clearing them first means each pass measures the design, not the last pass.
  for (
    var darkify_side_index = 0;
    darkify_side_index < DARKIFY_BORDER_SIDES.length;
    darkify_side_index++
  ) {
    element.classList.remove(
      "darkify_border_keep_" + DARKIFY_BORDER_SIDES[darkify_side_index],
    );
  }


  var nodeName = element.nodeName.toLowerCase();
  var backgroundColor = computedStyle.backgroundColor;
  var color = computedStyle.color;
  var borderColor = darkify_border_color_for_classification(
    element,
    computedStyle,
  );
  var backgroundImage = computedStyle.backgroundImage;

  // Captured once, before the class-based repaint below can reset
  // `background-image` to `none` via the `background` shorthand — a later
  // pass (a toggle re-visiting an already-repainted element) would otherwise
  // never be able to recover the gradient to recolour, or tell a handled one
  // apart from a page that never had one. See darkify_process_gradient().
  if (
    !element.dataset.darkifyGradientSrc &&
    backgroundImage &&
    backgroundImage.indexOf("gradient(") !== -1 &&
    backgroundImage.indexOf("url(") === -1
  ) {
    element.dataset.darkifyGradientSrc = backgroundImage;
  }

  // Same idea, for the same reason, for darkify_process_color_overrides():
  // by the time that writer runs, the class-based repaint below may already
  // have painted this element's own background/text/border colours, and it
  // needs the design's own values to match overrides against, not its own
  // eventual output. Gated on `darkify_has_color_overrides` — with no
  // overrides configured (the default) this costs nothing.
  //
  // Snapshots the whole subtree, not just this element: `color` inherits, and
  // border colours resolve through `currentColor`, so a child read *after* its
  // parent was repainted reports the preset's token as if it were the design's
  // own colour, and its override then never matches. See the function.
  darkify_capture_override_sources(element, computedStyle);

  if (
    nodeName === "body" &&
    (backgroundColor === "rgba(0, 0, 0, 0)" ||
      backgroundColor === "rgba(255, 255, 255, 0)")
  ) {
    element.style.setProperty("background-color", "rgb(255, 255, 255)");
    backgroundColor = window.getComputedStyle(element, null).backgroundColor;
  }

  if (darkify_disallowed_elements.length > 0) {
    if (darkify_safe_matches(element, darkify_disallowed_elements)) {
      // Two different things end up on this list. The user's own Disallowed
      // Elements mean "leave this alone", and are left alone. The rest are
      // built-in builder exclusions (`.elementor-background-overlay` and
      // friends) that exist because painting them a flat dark colour would
      // destroy what they are — an overlay covering its own image. Those still
      // get their generated-box (`::before`/`::after`) surfaces handled below,
      // just not the class-based repaint.
      var user_disallowed = false;
      if (darkify_disallowed_elements_raw.length > 0) {
        user_disallowed = darkify_safe_matches(
          element,
          darkify_disallowed_elements_raw,
        );
      }

      if (!user_disallowed) {
        // Generated boxes for the same reason: a builder's overlay is exactly
        // the element most likely to carry its surface on a `::before`, and
        // skipping it here is what let those sections stay light.
        darkify_process_pseudo_surfaces(element, computedStyle);
        darkify_process_deterministic_fixes(element);
      }

      // if (old_transition !== "") {
      //   element.style.setProperty("transition", old_transition);
      // }
      // element.classList.remove("darkify_processed");
      return;
    }
  }

  // The element's colours resolve to the page's own dark tokens, so its theme
  // has already dressed it — and its whole subtree with it.
  if (darkify_mark_if_self_themed(element, computedStyle)) {
    return;
  }

  var has_background_img_url = false;
  if (backgroundImage !== "none" && backgroundImage.includes("url")) {
    has_background_img_url = true;
    if (backgroundImage.indexOf("data:image/svg+xml") !== -1) {
      // An inline SVG is an icon, never a photograph — it needs recolouring to
      // stay legible, and the darkener would do the exact opposite.
      darkify_process_icon_background(element, computedStyle);
    } else if (darkify_enable_bg_image_darken === "1") {
      darkify_darken_bg_image(element, darken_level);
    }
  }
  if (
    backgroundColor !== "rgba(0, 0, 0, 0)" &&
    backgroundColor !== "rgba(255, 255, 255, 0)" &&
    !has_background_img_url
  ) {
    if (!element.hasAttribute("data-darkify_secondary_bg_finder")) {
      element.dataset.darkify_secondary_bg_finder = backgroundColor;
    }
    if (darkify_secondary_bg_color !== "") {
      var isSecondaryBgColorDifferent =
        darkify_secondary_bg_color !==
        element.dataset.darkify_secondary_bg_finder;
      if (isSecondaryBgColorDifferent) {
        element.classList.add("darkify_style_secondary_bg");
      }
      delete element.dataset.darkify_secondary_bg_finder;
    }
  }
  if (
    backgroundColor !== "rgba(0, 0, 0, 0)" &&
    color !== "rgba(0, 0, 0, 0)" &&
    borderColor !== "rgba(0, 0, 0, 0)" &&
    backgroundColor !== "rgba(255, 255, 255, 0)" &&
    color !== "rgba(255, 255, 255, 0)" &&
    borderColor !== "rgba(255, 255, 255, 0)" &&
    has_background_img_url === false
  ) {
    element.classList.add("darkify_style_all");
  } else {
    if (
      backgroundColor !== "rgba(0, 0, 0, 0)" &&
      color !== "rgba(0, 0, 0, 0)" &&
      backgroundColor !== "rgba(255, 255, 255, 0)" &&
      color !== "rgba(255, 255, 255, 0)" &&
      has_background_img_url === false
    ) {
      element.classList.add("darkify_style_bg_txt");
    } else {
      if (
        backgroundColor !== "rgba(0, 0, 0, 0)" &&
        borderColor !== "rgba(0, 0, 0, 0)" &&
        backgroundColor !== "rgba(255, 255, 255, 0)" &&
        borderColor !== "rgba(255, 255, 255, 0)" &&
        has_background_img_url === false
      ) {
        element.classList.add("darkify_style_bg_border");
      } else {
        if (
          color !== "rgba(0, 0, 0, 0)" &&
          borderColor !== "rgba(0, 0, 0, 0)" &&
          color !== "rgba(255, 255, 255, 0)" &&
          borderColor !== "rgba(255, 255, 255, 0)"
        ) {
          element.classList.add("darkify_style_txt_border");
        } else {
          if (
            backgroundColor !== "rgba(0, 0, 0, 0)" &&
            backgroundColor !== "rgba(255, 255, 255, 0)" &&
            has_background_img_url === false
          ) {
            element.classList.add("darkify_style_bg");
          } else {
            if (
              color !== "rgba(0, 0, 0, 0)" &&
              color !== "rgba(255, 255, 255, 0)"
            ) {
              element.classList.add("darkify_style_txt");
            } else if (
              borderColor !== "rgba(0, 0, 0, 0)" &&
              borderColor !== "rgba(255, 255, 255, 0)"
            ) {
              element.classList.add("darkify_style_border");
            }
          }
        }
      }
    }
  }
  // A gradient-only element gets the secondary surface so a gradient section
  // reads as a surface rather than staying light — except when the gradient is
  // a scrim. A scrim element is transparent by design: it sits over a photo or
  // a patterned parent and its whole job is letting that show through. Painting
  // an opaque secondary background on it covers what it was drawn over, which
  // is the same erasure darkify_process_gradient() avoids one layer up — the
  // scrim there would survive, only for this class to paint over it anyway.
  if (
    backgroundImage !== "none" &&
    !has_background_img_url &&
    !darkify_is_scrim_gradient(backgroundImage) &&
    !element.classList.contains("darkify_style_all") &&
    !element.classList.contains("darkify_style_bg_txt") &&
    !element.classList.contains("darkify_style_bg_border") &&
    !element.classList.contains("darkify_style_bg")
  ) {
    element.classList.add("darkify_style_secondary_bg");
  }

  if (nodeName === "a") {
    element.classList.add("darkify_style_link");
  }

  if (
    nodeName === "input" ||
    nodeName === "select" ||
    nodeName === "textarea"
  ) {
    element.classList.add("darkify_style_form_element");
  }

  const hasTargetClass = darkify_allowed_btn_class.some((cls) =>
    element.classList.contains(cls),
  );

  // A `<button>` the design gave neither a background nor a border is a link
  // wearing a button tag — the pattern plugins use for "Remove", "Edit",
  // "Cancel" actions so they read as text but stay keyboard-operable. Painting
  // it with the button tokens invents a filled box the light-mode page never
  // had, and the hover token makes one appear under the cursor. Ghost buttons
  // are excluded from this: they carry a visible border, which is exactly what
  // marks them as a button rather than a link.
  //
  // An icon button is NOT link-like, even though it is just as backgroundless.
  // It has to keep the button treatment for its hover state: a themed hover is
  // the only thing standing between the user and the design's light-mode hover
  // colour, which on a dark page flares white under the cursor (Modern Cart's
  // quantity stepper hovers to `#f0f9ff`). Text is what makes a link a link, so
  // a button carrying a glyph instead stays a button.
  var carries_glyph = !!(
    element.querySelector && element.querySelector("svg, img, canvas")
  );
  var carries_text = !!(element.textContent || "").trim();

  var paints_like_link =
    !carries_glyph &&
    carries_text &&
    (backgroundColor === "rgba(0, 0, 0, 0)" ||
      backgroundColor === "rgba(255, 255, 255, 0)") &&
    (borderColor === "rgba(0, 0, 0, 0)" ||
      borderColor === "rgba(255, 255, 255, 0)" ||
      computedStyle.borderTopWidth === "0px");

  if (
    (nodeName === "button" || hasTargetClass || element.type === "submit") &&
    !paints_like_link
  ) {
    element.classList.add("darkify_style_button");
    element.classList.remove("darkify_style_secondary_bg");
    element.classList.remove("darkify_style_all");
    element.classList.remove("darkify_style_link");
  } else if (paints_like_link && nodeName === "button") {
    element.classList.add("darkify_style_link");
  }

  if (
    (darkify_enable_low_image_brightness === "1" ||
      darkify_enable_image_grayscale === "1") &&
    nodeName === "img"
  ) {
    darkify_img_brightness_and_grayscale(element);
  }

  if (darkify_enable_invert_inline_svg === "1" && nodeName === "svg") {
    darkify_invert_inline_svg(element);
  }

  if (
    darkify_enable_low_video_brightness === "1" ||
    darkify_enable_video_grayscale === "1"
  ) {
    if (nodeName === "video") {
      darkify_video_brightness_and_grayscale(element);
    }

    if (nodeName === "iframe") {
      const srcAttribute = element.getAttribute("src");
      if (srcAttribute !== null) {
        if (
          srcAttribute.includes("youtube") ||
          srcAttribute.includes("vimeo") ||
          srcAttribute.includes("dailymotion")
        ) {
          darkify_video_brightness_and_grayscale(element);
        }
      }
    }
  }

  darkify_process_pseudo_surfaces(element, computedStyle);
  darkify_process_deterministic_fixes(element);

  // Translucency last, run once per pass: a semi-transparent background left
  // as-is would let the dark page show through wherever the design intended
  // page-behind-panel; painting it opaque-dark keeps the panel a panel.
  var darkify_bg_alpha = darkify_parse_color(backgroundColor);
  if (
    darkify_bg_alpha &&
    darkify_bg_alpha.a > 0 &&
    darkify_bg_alpha.a < 1 &&
    !darkify_color_may_be_animating(computedStyle)
  ) {
    element.dataset.darkify_alpha_bg = backgroundColor;
    darkify_fix_background_color_alpha(element);
  }


  // if (old_transition !== "") {
  //   setTimeout(function () {
  //     element.style.setProperty("transition", old_transition);
  //   }, 0);
  // }

  element.classList.add("darkify_processed");

  // The settled class string this pass produced. The delegated class watcher
  // compares against it to tell an external change apart from the engine's own
  // output, so it has to be written after the last `classList` call above.
  //
  // This replaces a per-element `setTimeout` that registered the class observer
  // on each element as it was processed — 3,348 timers and as many observer
  // registrations on the measured page, for a job one subtree observer now does
  // with a single registration made once at startup.
  element.dataset.darkify_preserved_classes = element.classList.toString();
}

/**
 * Drop the pre-paint baseline set by the <head> snippet.
 *
 * That baseline is a blunt dark wash that exists only to cover the gap between
 * first paint and the first DOM walk. Once the walk has assigned real colours
 * it is not just redundant but wrong — it would keep flattening the things the
 * engine deliberately leaves alone, such as a builder's background overlay — so
 * it comes off as soon as the first pass is through.
 *
 * The snippet carries its own timeout that removes it regardless, so a failure
 * in here cannot leave the wash stuck on the page.
 */
function darkify_finish_painting() {
  // Not while the parser is still producing the page. A walk that finishes
  // mid-parse has only covered what existed when it started, so dropping the
  // baseline here would expose every section the parser has not reached yet —
  // the page goes dark at the top and light further down, which is exactly the
  // split-page state this is meant to prevent. Once parsing is done, the walk
  // that follows has seen the whole document.
  if (document.readyState === "loading") {
    return;
  }

  document
    .getElementsByTagName("html")[0]
    .classList.remove("darkify_prepaint");
}

// Guarantees the walk that clears the baseline: the passes during parsing all
// bail out of darkify_finish_painting(), so without a pass after parsing the
// baseline would linger until the <head> snippet's timeout swept it away.
//
// Strictly gated on the baseline being present, i.e. on this page having loaded
// in dark mode. A page that loads light must not be walked here: the engine
// only wires up its observers on first use, and darkify_switch_trigger() does
// that wiring precisely because no walk has happened yet. Walking early would
// satisfy that check without wiring anything, and the switch would then flip
// the class with nothing listening for it.
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", function () {
    if (
      document.documentElement.classList.contains("darkify_prepaint")
    ) {
      darkify_schedule_walk();
    }
  });
}

function darkify_init_processes() {
  has_process_run_at_least_once = true;
  darkify_full_walk_needed = false;
  darkify_debug_count("full_walks");

  darkify_debug_time("walk_ms", function () {
  darkify_suspend_class_watch(function () {
    document
      .querySelectorAll(DARKIFY_WALK_SELECTOR)
      .forEach(function (element) {
        darkify_process_element(element);
      });
  }, true);
  });

  darkify_finish_painting();

  // Colours are on the page now, so the FOUC guard has nothing left to
  // hide. Dropped here rather than with the backdrop above, which waits
  // for parsing to finish and so lingers well past this point.
  document.documentElement.classList.remove("darkify_fouc_guard");
}

/**
 * Mirror the engine's dark state onto `dark` on <html> — admin panel only.
 *
 * A React-based admin screen doesn't take its colours from the cascade, so the
 * class-stamping the engine does to the rest of wp-admin can't reach it: it
 * reads design tokens that only switch when `dark` is on an ancestor
 * (Tailwind's class strategy, shadcn/ui, and Darkify's own React admin all key
 * on exactly that class). The <head> snippet in header_script.php sets it for
 * the first paint; this keeps it in step afterwards, so the admin-bar switch
 * re-themes those screens live instead of only after a reload.
 *
 * Deliberately generic — it mirrors our own state onto a shared convention and
 * names no plugin, so any admin app following that convention inherits the
 * theme. Screens with no such stylesheet loaded simply have an inert class.
 */
function darkify_sync_react_dark_class() {
  if (
    typeof darkify_is_this_admin_panel === "undefined" ||
    darkify_is_this_admin_panel !== "1"
  ) {
    return;
  }

  var html = document.documentElement;
  var should_be_dark = html.classList.contains("darkify_dark_mode_enabled");

  // The no-op guard matters: this runs from an observer watching the same
  // element's class list, so toggling unconditionally would re-trigger it in a
  // loop. Bailing when nothing changes breaks the cycle.
  if (html.classList.contains("dark") === should_be_dark) {
    return;
  }

  html.classList.toggle("dark", should_be_dark);
}

function darkify_init_observer() {
  darkify_observer.observe(document, {
    attributes: false,
    childList: true,
    characterData: false,
    subtree: true,
  });

  // Seeded before observing so the first genuine flip is detected as a change
  // rather than the observer mistaking the current state for one.
  darkify_last_swept_state = document.documentElement.classList.contains(
    "darkify_dark_mode_enabled",
  );
  dark_mode_status_changed.observe(document.getElementsByTagName("html")[0], {
    attributes: true,
    attributeFilter: ["class"],
  });

  // One delegated registration for class changes across the whole document,
  // replacing the per-element registration that used to happen inside
  // darkify_process_element(). Subtree coverage means nodes added later are
  // watched from the moment they are inserted, with no re-registration pass.
  elements_class_changed.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ["class"],
    subtree: true,
  });

  // Keep `dark` in step with every route that flips `darkify_dark_mode_enabled`
  // (the admin-bar switch, the keyboard shortcut, OS/time-based changes, the
  // theme picker) without having to patch each one.
  darkify_sync_react_dark_class();
  new MutationObserver(darkify_sync_react_dark_class).observe(
    document.documentElement,
    { attributes: true, attributeFilter: ["class"] },
  );

  if (document.readyState !== "loading") {
    if (!has_process_run_at_least_once) {
      darkify_init_processes();
    }
    darkify_implement_secondary_bg();
    darkify_apply_pseudo_bg_styles();
    darkify_recheck_on_css_loaded_later();
    darkify_restore_selected_theme();
  } else {
    document.addEventListener("DOMContentLoaded", function () {
      if (!has_process_run_at_least_once) {
        darkify_init_processes();
      }
      darkify_implement_secondary_bg();
      darkify_apply_pseudo_bg_styles();
      darkify_recheck_on_css_loaded_later();
      darkify_restore_selected_theme();
    });
  }
}

if (!_dkf_iframe_disabled && darkify_check_preloading()) {
  // Tell the <head> pre-paint snippet that the engine is here and will be the
  // one to drop `darkify_fouc_guard` / `darkify_prepaint`. Without this the
  // snippet can only guess how long the first walk takes, and on a hard reload
  // — nothing in cache, so the engine and the stylesheets it reads are all
  // re-fetched — its old fixed 800ms timer un-hid the page while it was still
  // wearing the theme's light colours, producing the flip to dark that reads as
  // flicker. Set here rather than at the top of the file on purpose: this is the
  // branch where the engine actually commits to painting, so a page that bails
  // out (frontend iframe with iframe dark mode off, dark mode not active) still
  // lets the snippet's failsafe release the guard on schedule.
  window.darkify_engine_loaded = true;

  document
    .getElementsByTagName("html")[0]
    .classList.add("darkify_dark_mode_enabled");
  darkify_init_observer();
  darkify_process_iframes(); // ✅ darkify proceed iframe
}

if (document.readyState !== "loading") {
  darkify_restore_selected_theme();
} else {
  document.addEventListener("DOMContentLoaded", darkify_restore_selected_theme);
}
