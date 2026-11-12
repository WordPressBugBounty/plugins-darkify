<?php

namespace ThemeAtelier\Darkify\Includes;

if (! defined('ABSPATH')) {
    die;
}

/**
 * Keeps Darkify working on cached and optimized pages.
 *
 * Why Darkify needs less than WP Dark Mode
 * ----------------------------------------
 * WP Dark Mode reads the visitor's choice from a cookie in PHP and prints
 * `data-wp-dark-mode-active` on <html>. A page cache then freezes the FIRST
 * visitor's mode into the HTML everyone gets, so its "Exclude from Caching"
 * option stops printing that attribute and lets JS decide in the browser.
 *
 * Darkify never had that problem: nothing it prints depends on the visitor.
 * The choice lives in `localStorage.darkify_last_state`, and the dark/light
 * decision (remembered choice, URL parameter, OS preference, time/sun
 * schedule) is made in the browser by the inline <head> snippet and
 * client_main.js. So the same cached HTML is correct for every visitor.
 *
 * What does break Darkify on cached sites
 * ---------------------------------------
 * 1. Optimization layers that ship with page caches (WP Rocket "Delay/Defer
 *    JS", LiteSpeed JS defer/delay/combine, Autoptimize inline-JS
 *    aggregation, SiteGround combine/async, Cloudflare Rocket Loader,
 *    Jetpack Boost, ...). If they delay the <head> pre-paint snippet the page
 *    flashes white, and if they move the inline `var darkify_*` config below
 *    client_main.js the engine reads undefined values. WP Dark Mode handles
 *    this with `nowprocket` / `data-no-optimize` style attributes; this class
 *    does the same, on every tag Darkify prints, plus each plugin's own
 *    exclusion filters. Controlled by the `exclude_from_cache` setting.
 * 2. Stale pages after a settings change: colours and config are inlined into
 *    every page, so a cached page keeps the old values until the cache
 *    expires. Known page caches are purged once when the `darkify` option
 *    actually changes (filterable).
 * 3. The admin Live Preview renders the frontend with UNSAVED settings. It must
 *    never be stored by a page cache, and most page caches ignore
 *    Cache-Control and look for DONOTCACHEPAGE instead.
 */
class DarkifyCacheCompat
{
    const OPTION_KEY = 'darkify';

    /** Script handle of the frontend engine. */
    const SCRIPT_HANDLE = 'darkify-client-main';

    /** Purge already queued for this request. */
    private static $purge_queued = false;

    public function __construct()
    {
        // Optimizer exclusions. The option is checked inside each callback so the
        // Live Preview's unsaved value (swapped in on `init`) is respected.
        add_filter('script_loader_tag', array($this, 'mark_script_tag'), 10, 2);
        add_filter('wp_inline_script_attributes', array($this, 'mark_inline_script_attributes'), 10, 2);

        // Autoptimize (comma-separated strings, matched against the tag/URL).
        add_filter('autoptimize_filter_js_exclude', array($this, 'autoptimize_js_exclude'));
        add_filter('autoptimize_filter_css_exclude', array($this, 'autoptimize_css_exclude'));

        // WP Rocket (arrays of patterns).
        add_filter('rocket_delay_js_exclusions', array($this, 'append_js_patterns'));
        add_filter('rocket_exclude_defer_js', array($this, 'append_js_file_patterns'));
        add_filter('rocket_exclude_js', array($this, 'append_js_file_patterns'));
        add_filter('rocket_excluded_inline_js_content', array($this, 'append_inline_patterns'));

        // LiteSpeed Cache (arrays of patterns).
        add_filter('litespeed_optimize_js_excludes', array($this, 'append_js_patterns'));
        add_filter('litespeed_optm_js_defer_exc', array($this, 'append_js_patterns'));

        // SiteGround Optimizer (arrays of handles / inline content).
        add_filter('sgo_js_minify_exclude', array($this, 'append_handle'));
        add_filter('sgo_javascript_combine_exclude', array($this, 'append_handle'));
        add_filter('sgo_js_async_exclude', array($this, 'append_handle'));
        add_filter('sgo_javascript_combine_excluded_inline_content', array($this, 'append_inline_patterns'));

        // Purge page caches once when the saved settings really change.
        // update_option_{name} only fires when the value differs.
        add_action('update_option_' . self::OPTION_KEY, array($this, 'queue_purge'), 10, 0);
        add_action('add_option_' . self::OPTION_KEY, array($this, 'queue_purge'), 10, 0);
    }

    /**
     * Whether the optimizer exclusions are on (setting default: on).
     */
    public static function is_enabled()
    {
        $options = get_option(self::OPTION_KEY);
        $enabled = (is_array($options) && isset($options['exclude_from_cache'])) ? (bool) $options['exclude_from_cache'] : true;

        /**
         * Filters whether Darkify's scripts are excluded from cache/optimization plugins.
         *
         * @param bool $enabled
         */
        return (bool) apply_filters('darkify_exclude_from_cache', $enabled);
    }

    /**
     * Attributes that tell optimizers to leave a script alone.
     *
     * - data-no-optimize / data-no-defer / data-no-minify: LiteSpeed Cache
     * - nowprocket: WP Rocket (Delay JS, Defer JS, Combine)
     * - data-cfasync="false": Cloudflare Rocket Loader
     * - data-pagespeed-no-defer: PageSpeed module (mod_pagespeed / ngx_pagespeed)
     * - data-jetpack-boost="ignore": Jetpack Boost "Defer non-essential JS"
     * - data-wpfc-render="false": WP Fastest Cache render-blocking JS
     * - nitro-exclude: NitroPack
     *
     * Unknown attributes are ignored by browsers, so the list is safe to
     * extend through the `darkify_cache_exclusion_attributes` filter.
     *
     * @return array<string,string|true> name => value (true = boolean attribute)
     */
    public static function script_attributes()
    {
        $attributes = array(
            'data-cfasync'            => 'false',
            'data-no-optimize'        => '1',
            'data-no-defer'           => '1',
            'data-no-minify'          => '1',
            'nowprocket'              => true,
            'data-pagespeed-no-defer' => true,
            'data-jetpack-boost'      => 'ignore',
            'data-wpfc-render'        => 'false',
            'nitro-exclude'           => true,
        );

        return (array) apply_filters('darkify_cache_exclusion_attributes', $attributes);
    }

    /**
     * Build the attribute string for raw HTML.
     */
    private static function attribute_string()
    {
        $out = '';
        foreach (self::script_attributes() as $name => $value) {
            $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $name);
            if ('' === $name) {
                continue;
            }
            $out .= (true === $value) ? ' ' . $name : ' ' . $name . '="' . esc_attr($value) . '"';
        }
        return $out;
    }

    /**
     * Add the exclusion attributes to every <script> tag in a chunk of
     * Darkify's own output (header/footer templates, early iframe guard).
     *
     * Tags that already carry `data-no-optimize` are left untouched, so the
     * call is idempotent.
     *
     * @param string $html
     * @return string
     */
    public static function mark_html($html)
    {
        if ('' === $html || ! is_string($html) || ! self::is_enabled()) {
            return $html;
        }

        $attrs = self::attribute_string();

        return preg_replace_callback(
            '/<script\b([^>]*)>/i',
            static function ($m) use ($attrs) {
                if (false !== stripos($m[1], 'data-no-optimize')) {
                    return $m[0];
                }
                return '<script' . $attrs . $m[1] . '>';
            },
            $html
        );
    }

    /**
     * Render a template/callback and mark the scripts it prints.
     *
     * @param callable $callback Prints markup.
     */
    public static function print_marked($callback)
    {
        ob_start();
        call_user_func($callback);
        $html = (string) ob_get_clean();
        // Markup is Darkify's own already-escaped template output; only static
        // attributes defined above are added to it.
        echo self::mark_html($html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * External script tag (client_main.js) printed by WP_Scripts.
     */
    public function mark_script_tag($tag, $handle)
    {
        if (self::SCRIPT_HANDLE !== $handle || is_admin()) {
            return $tag;
        }
        return self::mark_html($tag);
    }

    /**
     * Inline 'before'/'after' scripts attached to client_main.js
     * (wp_add_inline_script, WordPress 6.4+). These never pass through
     * `script_loader_tag`, which is why WP Dark Mode's `wp-dark-mode-js-extra`
     * check in that filter is never hit.
     */
    public function mark_inline_script_attributes($attributes, $data = '')
    {
        if (is_admin() || empty($attributes['id']) || 0 !== strpos($attributes['id'], self::SCRIPT_HANDLE . '-js-') || ! self::is_enabled()) {
            return $attributes;
        }
        foreach (self::script_attributes() as $name => $value) {
            if (! isset($attributes[$name])) {
                $attributes[$name] = $value;
            }
        }
        return $attributes;
    }

    /**
     * URL fragments for client_main.js (with or without .min).
     */
    private static function js_file_patterns()
    {
        return array('/src/assets/js/client_main.js', '/src/assets/js/client_main.min.js');
    }

    /**
     * Content fragments that identify Darkify's inline scripts: every one of
     * them either declares `darkify_*` variables or reads
     * `localStorage.darkify_*`.
     */
    private static function inline_patterns()
    {
        return array('darkify_');
    }

    public function append_js_patterns($list)
    {
        if (! self::is_enabled()) {
            return $list;
        }
        return array_values(array_unique(array_merge((array) $list, self::js_file_patterns(), self::inline_patterns())));
    }

    public function append_js_file_patterns($list)
    {
        if (! self::is_enabled()) {
            return $list;
        }
        return array_values(array_unique(array_merge((array) $list, self::js_file_patterns())));
    }

    public function append_inline_patterns($list)
    {
        if (! self::is_enabled()) {
            return $list;
        }
        return array_values(array_unique(array_merge((array) $list, self::inline_patterns())));
    }

    public function append_handle($list)
    {
        if (! self::is_enabled()) {
            return $list;
        }
        $list   = (array) $list;
        $list[] = self::SCRIPT_HANDLE;
        return array_values(array_unique($list));
    }

    /**
     * Autoptimize. `client_main` was already excluded before this class
     * existed and stays excluded regardless of the setting (backward
     * compatibility). The inline-script fragment is added on top.
     */
    public function autoptimize_js_exclude($excludes)
    {
        $excludes = (string) $excludes;
        $excludes .= ',client_main.js,client_main.min.js';
        if (self::is_enabled()) {
            $excludes .= ',darkify_inline_js,darkify_';
        }
        return $excludes;
    }

    /**
     * Autoptimize inline CSS aggregation: keep the <head> colour variables
     * and pre-paint rules inline so they apply before first paint.
     */
    public function autoptimize_css_exclude($excludes)
    {
        if (! self::is_enabled()) {
            return $excludes;
        }
        return (string) $excludes . ',darkify_inline_css';
    }

    /**
     * Mark the current response as uncacheable for page caches and CDNs.
     * Used by the Live Preview, which renders unsaved settings.
     *
     * nocache_headers() alone is not enough: WP Super Cache, W3 Total Cache,
     * WP Rocket, WP Fastest Cache, Cache Enabler and others decide from the
     * DONOTCACHEPAGE constant, and LiteSpeed Cache from its own API.
     */
    public static function prevent_page_caching()
    {
        if (! defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- shared constant read by page-cache plugins.
        }
        do_action('litespeed_control_set_nocache', 'Darkify live preview');
        if (! headers_sent()) {
            nocache_headers();
        }
    }

    /**
     * Queue a single purge at the end of the request, however many times the
     * option is written while saving.
     */
    public function queue_purge()
    {
        if (self::$purge_queued) {
            return;
        }
        self::$purge_queued = true;
        add_action('shutdown', array(__CLASS__, 'purge_page_caches'), 0);
    }

    /**
     * Purge full-page caches through each plugin's public API. Every call is
     * guarded, so nothing runs for plugins that are not installed.
     */
    public static function purge_page_caches()
    {
        /**
         * Filters whether Darkify purges page caches after its settings change.
         *
         * @param bool $purge
         */
        if (! apply_filters('darkify_purge_cache_on_settings_change', true)) {
            return;
        }

        // WP Rocket.
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        // LiteSpeed Cache.
        do_action('litespeed_purge_all');
        // W3 Total Cache.
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        // WP Super Cache.
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        // WP Fastest Cache.
        if (function_exists('wpfc_clear_all_cache')) {
            wpfc_clear_all_cache(true);
        }
        // SiteGround Optimizer.
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache();
        }
        // WP-Optimize.
        if (function_exists('wpo_cache_flush')) {
            wpo_cache_flush();
        }
        // Comet Cache.
        if (class_exists('comet_cache') && method_exists('comet_cache', 'clear')) {
            \comet_cache::clear();
        }
        // Autoptimize (aggregated files may contain inlined Darkify config).
        if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) {
            \autoptimizeCache::clearall();
        }
        // Cache Enabler, Breeze, Hummingbird, Nginx Helper (action APIs; no-ops when absent).
        do_action('cache_enabler_clear_complete_cache');
        do_action('breeze_clear_all_cache');
        do_action('wphb_clear_page_cache');
        do_action('rt_nginx_helper_purge_all');
        // WP Engine.
        if (class_exists('WpeCommon')) {
            if (method_exists('WpeCommon', 'purge_memcached')) {
                \WpeCommon::purge_memcached();
            }
            if (method_exists('WpeCommon', 'purge_varnish_cache')) {
                \WpeCommon::purge_varnish_cache();
            }
        }

        /**
         * Fires after Darkify asked page caches to purge. Hook in here for a
         * cache layer not covered above (CDN, host cache, ...).
         */
        do_action('darkify_after_cache_purge');
    }
}
