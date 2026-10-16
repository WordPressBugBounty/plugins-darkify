<?php

/**
 * Admin live-preview REST controller.
 *
 * Renders the admin's live preview by loading the site's REAL frontend homepage
 * inside an iframe with the *unsaved* settings applied — the same approach Chat
 * Help Pro uses to preview the real frontend widget, adapted to Darkify, whose
 * "frontend output" is the entire dark-moded page rather than a single widget.
 *
 * Flow:
 *   1. The admin SPA POSTs the current (unsaved) form values here. They are
 *      sanitized, merged over the real saved `darkify` option, and stored in a
 *      short-lived per-user transient — NEVER written to the real option.
 *   2. The iframe requests the homepage with `?darkify_preview=<nonce>`. On that
 *      one request, `maybe_enable_preview()` filters `option_darkify` to return
 *      the transient's merged values, so Darkify's normal frontend code renders
 *      the page exactly as it would once the settings were saved.
 *
 * Because the override is (a) read-only, (b) scoped to a single nonce-verified
 * request from a `manage_options` user, and (c) applied only to that user's own
 * transient, it changes nothing persistent and is invisible to every other
 * visitor and request — full backward compatibility.
 *
 * @package    darkify
 * @subpackage darkify/src/Admin/Rest
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\Rest;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    die;
}

class PreviewRest extends AbstractRestController
{
    /** The option key the preview drives (Darkify stores everything here). */
    const OPTION_KEY = 'darkify';

    /** Query var the iframe carries to request a preview render. */
    const PREVIEW_QUERY_VAR = 'darkify_preview';

    /** Nonce action guarding the preview render. */
    const PREVIEW_NONCE_ACTION = 'darkify_preview';

    /** Transient lifetime — long enough for an editing session, short enough to
     *  self-clean. Refreshed on every preview POST. */
    const PREVIEW_TTL = HOUR_IN_SECONDS;

    public function __construct()
    {
        parent::__construct();
        // Runs on EVERY request (this controller is constructed in the plugin
        // boot, not only in wp-admin), so it can catch the frontend iframe
        // request and swap in the preview values before Darkify's frontend
        // reads the option.
        \add_action('init', [$this, 'maybe_enable_preview']);
    }

    public function register_routes(): void
    {
        \register_rest_route(self::NS, '/preview', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'store_preview'],
            'permission_callback' => [$this, 'can_manage'],
        ]);
    }

    /** Per-user transient key so one admin's preview never leaks into another's. */
    private function transient_key(int $user_id): string
    {
        return 'darkify_admin_preview_' . $user_id;
    }

    /**
     * POST /preview — sanitize + merge the unsaved values over the saved option
     * and stash them in the current user's transient. Returns the URL the iframe
     * should load. Never persists to the real option.
     */
    public function store_preview(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = \get_current_user_id();
        if (! $user_id) {
            return new WP_REST_Response(['message' => \__('Not allowed.', 'darkify')], 403);
        }

        $incoming = $request->get_param('values');
        $incoming = \is_array($incoming) ? $incoming : [];

        $sections  = $this->get_registered_sections(self::OPTION_KEY);
        $type_map  = $this->collect_field_types($sections);
        $sanitized = $this->sanitize_values($incoming, $type_map);
        // Free plugin: the preview renders with Pro-locked values stripped too,
        // so it always shows what the free frontend would actually do.
        $sanitized = $this->strip_pro_keys($sanitized, $sections);

        $existing = \get_option(self::OPTION_KEY, []);
        $existing = \is_array($existing) ? $existing : [];
        $merged   = \array_merge($existing, $sanitized);

        \set_transient($this->transient_key($user_id), $merged, self::PREVIEW_TTL);

        return \rest_ensure_response([
            'ok'  => true,
            'url' => $this->preview_url(),
        ]);
    }

    /** The homepage URL carrying the preview nonce. */
    public function preview_url(): string
    {
        return \add_query_arg(
            self::PREVIEW_QUERY_VAR,
            \wp_create_nonce(self::PREVIEW_NONCE_ACTION),
            \home_url('/')
        );
    }

    /**
     * On a valid preview request, make Darkify's frontend read the unsaved
     * preview values instead of the saved option — for this request only.
     */
    public function maybe_enable_preview(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified on the next line.
        $token = isset($_GET[self::PREVIEW_QUERY_VAR]) ? \sanitize_text_field(\wp_unslash($_GET[self::PREVIEW_QUERY_VAR])) : '';
        if ($token === '' || ! \wp_verify_nonce($token, self::PREVIEW_NONCE_ACTION)) {
            return;
        }
        if (! \is_user_logged_in() || ! \current_user_can('manage_options')) {
            return;
        }

        $preview = \get_transient($this->transient_key(\get_current_user_id()));
        if (! \is_array($preview)) {
            return;
        }

        // The preview renders the frontend with the admin's *unsaved* settings
        // and NO forced mode: it uses the exact same light/dark logic a real
        // visitor would (this browser's stored toggle state, then the unsaved
        // `enable_default_dark_mode` / OS-aware / time-based settings). So it
        // starts in whatever mode the site actually would, and the admin can
        // flip the in-preview switch just like on the front end — the switch
        // icon then stays in sync because dark mode is never force-applied
        // out-of-band.
        \add_filter('option_' . self::OPTION_KEY, function () use ($preview) {
            return $preview;
        }, 99);

        // Tell the frontend engine this exact page load IS the admin's own Live
        // Preview iframe — not a real third-party iframe embed on someone's page.
        // This makes it behave like the TOP-LEVEL front end: the "Frontend Iframe
        // Dark Mode" setting (which governs iframes NESTED WITHIN a page) can't
        // suppress the engine on the previewed page itself. It does NOT force any
        // mode — the light/dark state is still whatever the front end would show.
        // See Frontend::darkify_early_iframe_guard() and the
        // `darkify_is_admin_live_preview` JS flag in client_main.js's
        // `_dkf_iframe_disabled` guard.
        \add_filter('darkify_is_admin_live_preview', '__return_true');

        // Keep the preview context when browsing INSIDE the iframe. Without
        // this, clicking any link inside the preview navigates to a plain URL
        // with no token, so this whole method no-ops for that page: it silently
        // reverts to the SAVED settings, and — because a tokenless page in an
        // iframe fails client_main.js's `_dkf_iframe_disabled` guard whenever
        // "Frontend Iframe Dark Mode" is off — the engine switches itself off
        // entirely and the page renders light. Carrying the token forward keeps
        // the previewed page a preview.
        \add_action('wp_head', [$this, 'print_preview_link_persistence'], 1);

        // A cleaner canvas: no admin bar overlapping the previewed page.
        \add_filter('show_admin_bar', '__return_false');

        // Strip other plugins' floating/overlay UI (chat widgets, cookie and
        // consent banners, popups, announcement bars, …) from the preview only
        // — see print_preview_overlay_filter() for why this is safe to do
        // unconditionally for "any installed plugin" without touching the real
        // frontend or the page's own layout/content.
        \add_action('wp_head', [$this, 'print_preview_overlay_filter'], 1);

        // Mark the response so it is never cached by page caches / CDNs.
        if (! \headers_sent()) {
            \nocache_headers();
        }
    }

    /**
     * Carry the preview token across link clicks inside the preview iframe.
     *
     * Only ever registered from maybe_enable_preview(), so it prints on preview
     * requests alone — a real visitor's page never sees it.
     *
     * A delegated listener rather than rewriting every `href`: it costs one
     * handler regardless of page size and it also covers links a theme or
     * plugin injects later. It defers to the page in every case where the click
     * wasn't a plain same-tab navigation to another page of this site —
     * modified clicks (new tab), `target`ed links, external hosts, in-page
     * anchors, and anything a script already handled (`defaultPrevented`) are
     * left completely alone.
     */
    public function print_preview_link_persistence(): void
    {
        $var   = \wp_json_encode(self::PREVIEW_QUERY_VAR);
        $token = \wp_json_encode(\wp_create_nonce(self::PREVIEW_NONCE_ACTION));
        ?>
        <script type="text/javascript" class="darkify_preview_js">
            (function () {
                var VAR = <?php echo $var; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_json_encode output. ?>;
                var TOKEN = <?php echo $token; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_json_encode output. ?>;
                document.addEventListener('click', function (e) {
                    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                        return;
                    }
                    var a = (e.target && e.target.closest) ? e.target.closest('a') : null;
                    if (!a || !a.getAttribute('href')) {
                        return;
                    }
                    if (a.hasAttribute('download') || (a.target && a.target !== '_self')) {
                        return;
                    }
                    var u;
                    try {
                        u = new URL(a.href, window.location.href);
                    } catch (err) {
                        return;
                    }
                    if (u.origin !== window.location.origin) {
                        return;
                    }
                    if (u.searchParams.get(VAR)) {
                        return;
                    }
                    /* Same page + hash only: let the browser jump the anchor. */
                    if (u.pathname === window.location.pathname && u.search === window.location.search && u.hash) {
                        return;
                    }
                    u.searchParams.set(VAR, TOKEN);
                    e.preventDefault();
                    window.location.href = u.toString();
                });
            }());
        </script>
        <?php
    }

    /**
     * Hide third-party plugin overlay UI inside the preview iframe.
     *
     * Only ever registered from maybe_enable_preview(), i.e. only for this one
     * nonce-verified preview request — never on a real visitor's page load — so
     * this cannot affect the actual frontend.
     *
     * Two layers, because no single technique reliably catches "any installed
     * plugin, not just named ones":
     *
     *   1. A CSS denylist of the id/class substrings the most common overlay
     *      categories (cookie/consent banners, chat widgets, popups/modals,
     *      sticky announcement bars) overwhelmingly use in the wild. Cheap,
     *      instant, and — being plain CSS — it also matches elements a plugin
     *      injects into the page *after* this prints (e.g. via its own
     *      wp_footer script), since the browser re-evaluates selectors as the
     *      DOM changes.
     *   2. A small script that hides anything else `position: fixed` or
     *      `sticky` UNLESS it reads as an ordinary slim top bar (the shape a
     *      theme's own sticky header/nav takes) — the general fallback for
     *      overlay UI the CSS list didn't anticipate. It re-scans on a few
     *      delays and watches the DOM (MutationObserver, attached to
     *      `<html>` so it works even though this prints in `<head>` before
     *      `<body>` exists) to catch widgets that inject themselves late.
     *
     * Both explicitly exempt Darkify's own floating switch (`.darkify_switch`,
     * `#darkify_switch_*` — see Darkify::switcher_wrapper()) and the admin
     * bar, and neither touches in-flow page content, so the theme's real
     * layout renders untouched.
     */
    public function print_preview_overlay_filter(): void
    {
        ?>
        <style id="darkify-preview-overlay-filter">
            /* The denylist is grouped in :is() so the shared exemptions below
               apply to every entry at once — Darkify's own switch, and the
               theme's semantic site header / banner landmark, which must never
               be filtered out: it is page chrome the admin is previewing, not
               third-party overlay UI, and some themes name their header bar with
               words that appear in this list ("sticky-bar", "top bar", …). */
            :is(
                [id*="cookie" i], [class*="cookie" i],
                [id*="consent" i], [class*="consent" i],
                [id*="gdpr" i], [class*="gdpr" i],
                [id*="cookielaw" i], [class*="cookielaw" i],
                [id*="cky-consent" i], [class*="cky-" i],
                [id*="chat-widget" i], [class*="chat-widget" i],
                [id*="livechat" i], [class*="livechat" i],
                [id*="tawk" i], [class*="tawk" i],
                [id*="crisp-client" i], [id*="intercom" i], [class*="intercom-" i],
                [id*="drift-frame" i], [id*="fc_frame" i], [id*="fc_widget" i],
                [id*="hubspot-messages" i], [id*="zsiq" i], [class*="zsiq" i],
                [id*="fb-customer-chat" i], [id*="messenger-chat" i],
                [id*="whatsapp" i], [class*="whatsapp" i],
                [id*="popup" i], [class*="popup" i],
                [id*="modal" i], [class*="modal-overlay" i],
                [class*="pum-" i], [id*="pum-" i],
                [class*="elementor-popup-modal" i],
                [class*="announcement-bar" i], [id*="hello-bar" i], [class*="hello-bar" i],
                [class*="sticky-bar" i], [class*="notification-bar" i]
            ):not([class*="darkify" i]):not([id*="darkify" i]):not(header):not([role="banner"]):not(header *):not([role="banner"] *)
            {
                display: none !important;
                visibility: hidden !important;
                pointer-events: none !important;
            }
        </style>
        <script id="darkify-preview-overlay-script">
        (function () {
            var KEEP_RE = /darkify|wpadminbar/i;
            /* Class/id hints a theme uses for its own site header / primary nav. */
            var HEADER_RE = /(^|[\s_-])(site[_-]?header|main[_-]?header|page[_-]?header|header[_-]?wrap|masthead|navbar|nav[_-]?bar|main[_-]?nav|primary[_-]?nav|site[_-]?nav|top[_-]?bar|topbar)([\s_-]|$)|(^|[\s])(ta[_-]header|header)([\s]|$)/i;
            function classOf(el) {
                var c = el.className;
                return (typeof c === "string") ? c : (el.getAttribute ? (el.getAttribute("class") || "") : "");
            }
            function isExempt(el) {
                while (el && el.nodeType === 1) {
                    if (KEEP_RE.test(el.id || "") || KEEP_RE.test(classOf(el))) return true;
                    el = el.parentElement;
                }
                return false;
            }
            /**
             * Is this the theme's own site header rather than third-party overlay UI?
             *
             * A theme header is very often `position: fixed` and is NOT a slim bar
             * flush to the top: it may sit at an offset (`top: 2rem` floating navs)
             * and may stack a promo/announcement strip above the nav, pushing it well
             * past 140px tall. Judging it by the slim-top-bar shape alone hid real
             * headers from the preview, which is the one part of the page an admin
             * most wants to see dark-moded.
             *
             * Identified structurally (a `<header>` element, `role="banner"`, or a
             * header/nav class name) plus anchored near the top of the page and not
             * covering the viewport — so a full-screen mobile menu overlay or a modal
             * that happens to contain a `<nav>` is still filtered out.
             */
            function isSiteHeader(el, rect, vw, vh) {
                var tag = (el.tagName || "").toLowerCase();
                var looksHeader =
                    tag === "header" ||
                    (el.getAttribute && el.getAttribute("role") === "banner") ||
                    HEADER_RE.test(el.id || "") ||
                    HEADER_RE.test(classOf(el)) ||
                    (tag === "nav" && rect.width >= vw * 0.5);
                if (!looksHeader) return false;
                /* Anchored to the top strip of the viewport, spanning most of it,
                   and not tall enough to be a full-screen takeover. */
                return rect.top <= 160 && rect.width >= vw * 0.5 && rect.height <= vh * 0.5;
            }
            function maybeHide(el) {
                if (!el || el.nodeType !== 1 || isExempt(el)) return;
                var style;
                try { style = getComputedStyle(el); } catch (e) { return; }
                if (style.position !== "fixed" && style.position !== "sticky") return;
                var rect = el.getBoundingClientRect();
                if (rect.width === 0 && rect.height === 0) return;
                var vw = window.innerWidth || document.documentElement.clientWidth;
                var vh = window.innerHeight || document.documentElement.clientHeight;
                if (isSiteHeader(el, rect, vw, vh)) return;
                var coversViewport = rect.width >= vw * 0.6 && rect.height >= vh * 0.6;
                var slimTopBar = rect.top <= 4 && rect.height <= 140;
                if (coversViewport || !slimTopBar) {
                    el.style.setProperty("display", "none", "important");
                }
            }
            function scan(root) {
                if (root && root.querySelectorAll) {
                    root.querySelectorAll("*").forEach(maybeHide);
                }
            }
            function safeScan() {
                if (document.body) scan(document.body);
            }
            safeScan();
            [300, 800, 1500, 3000].forEach(function (ms) {
                setTimeout(safeScan, ms);
            });
            new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    if (m.type === "childList") {
                        m.addedNodes.forEach(function (node) {
                            if (node.nodeType !== 1) return;
                            maybeHide(node);
                            if (node.querySelectorAll) node.querySelectorAll("*").forEach(maybeHide);
                        });
                    } else if (m.type === "attributes") {
                        maybeHide(m.target);
                    }
                });
            }).observe(document.documentElement, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ["style", "class"],
            });
        })();
        </script>
        <?php
    }
}
