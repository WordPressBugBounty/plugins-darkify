<?php

namespace ThemeAtelier\Darkify\Frontend;

use ThemeAtelier\Darkify\Includes\DarkifyExternalSupport;
use ThemeAtelier\Darkify\Includes\DarkifyUtils;

if (!defined('ABSPATH')) {
    die;
} // Cannot access directly.

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://themeatelier.net
 * @since      1.0.0
 *
 * @package    Darkify
 * @subpackage Darkify/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Darkify
 * @subpackage Darkify/public
 * @author     ThemeAtelier <themeatelierbd@gmail.com>
 */
class Frontend
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    public $utils;
    public $settings;
    public $external_support;
    public $unique_id;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        $this->utils = new DarkifyUtils($this);
        $this->external_support = new DarkifyExternalSupport($this);

        new DarkifyShortcode($this);

        if (function_exists('wp_rand')) {
            $this->unique_id = wp_rand();
        }

        // Register unconditionally and gate inside the callback. The constructor
        // runs at plugin boot (before `init`), so the option read here is always
        // the SAVED value — deciding registration here means the admin Live
        // Preview, which swaps in unsaved values only on `init` (see
        // PreviewRest::maybe_enable_preview), could never show a newly enabled
        // menu switch until saved. The filter runs at render time, after `init`,
        // so re-reading the option in the callback sees the preview values.
        add_filter('wp_nav_menu_items', array($this, 'darkify_switch_in_menu'), 10, 2);
        // Block-theme and Kadence navigations never fire `wp_nav_menu_items` —
        // they are blocks, not wp_nav_menu() calls — so they need their own hook.
        add_filter('render_block', array($this, 'darkify_switch_in_block_menu'), 10, 2);

        add_action('wp_enqueue_scripts', array($this, 'darkify_client_enqueue'), 100);
        add_action('login_enqueue_scripts', array($this, 'darkify_client_enqueue'));
        add_action('register_enqueue_scripts', array($this, 'darkify_client_enqueue'));
        add_action('wp_head', array($this, 'darkify_client_header_script'), 1);
        add_action('login_head', array($this, 'darkify_client_header_script'), 1);
        add_action('register_head', array($this, 'darkify_client_header_script'), 1);
        add_action('wp_footer', array($this, 'darkify_client_footer_script'));
        add_action('login_footer', array($this, 'darkify_client_footer_script'));
        add_action('register_footer', array($this, 'darkify_client_footer_script'));

        add_action('init', array($this, 'darkify_register_switcher_styles'), 5);
        add_filter('autoptimize_filter_js_exclude', array($this, 'darkify_exclude_js_from_cache_plugins'));
    }

    public function darkify_exclude_js_from_cache_plugins($excludes)
    {
        $excludes .= ',client_main.js,client_main.min.js';
        return $excludes;
    }

    public function darkify_register_switcher_styles()
    {
        $min = (apply_filters('darkify_dev_mode', false) || WP_DEBUG) ? '' : '.min';
        wp_register_style('darkify-client-main', DARKIFY_DIR_URL . 'src/assets/css/client_main' . $min . '.css', array(), DARKIFY_VERSION);
        wp_register_style('theme-classic',    DARKIFY_DIR_URL . 'src/assets/css/switcher/classic'    . $min . '.css', array('darkify-client-main'), DARKIFY_VERSION, 'all');
        wp_register_style('theme-expand',     DARKIFY_DIR_URL . 'src/assets/css/switcher/expand'     . $min . '.css', array('darkify-client-main'), DARKIFY_VERSION, 'all');
        wp_register_style('theme-inner-moon', DARKIFY_DIR_URL . 'src/assets/css/switcher/inner-moon' . $min . '.css', array('darkify-client-main'), DARKIFY_VERSION, 'all');
        wp_register_style('theme-within',     DARKIFY_DIR_URL . 'src/assets/css/switcher/within'     . $min . '.css', array('darkify-client-main'), DARKIFY_VERSION, 'all');
        wp_register_style('theme-orbit',      DARKIFY_DIR_URL . 'src/assets/css/switcher/orbit'      . $min . '.css', array('darkify-client-main'), DARKIFY_VERSION, 'all');
    }

    function darkify_client_enqueue()
    {
        if ($this->darkify_is_dark_mode_allowed()) {
            $options = get_option('darkify');
            $min = (apply_filters('darkify_dev_mode', false) || WP_DEBUG) ? '' : '.min';

            wp_enqueue_style('darkify-client-main', DARKIFY_DIR_URL . 'src/assets/css/client_main' . $min . '.css', array(), DARKIFY_VERSION);
            wp_enqueue_script('darkify-client-main', DARKIFY_DIR_URL . 'src/assets/js/client_main' . $min . '.js', array(), DARKIFY_VERSION);

            // When "Frontend Iframe Dark Mode" is OFF, use a two-stage inline script
            // strategy that covers all scenarios — source JS, minified JS,
            // cross-origin iframes, and same-origin WP pages embedded as iframes.
            //
            // ROOT CAUSE: a same-origin WP page loaded inside an <iframe> has its
            // OWN copy of client_main[.min].js running.  That copy calls
            // darkify_check_preloading() → localStorage.darkify_last_state="1" →
            // adds darkify_dark_mode_enabled to the iframe's <html> → goes dark.
            // The parent's darkify_process_iframes guard is irrelevant here.
            //
            // Stage 1 — 'before' (runs BEFORE client_main[.min].js on EVERY page):
            //   If this page is rendering inside a frontend iframe, intercept the
            //   localStorage.darkify_last_state property via Object.defineProperty so
            //   that darkify_check_preloading() always reads "0" and returns false.
            //   This prevents the iframe page's own Darkify from initialising dark
            //   mode at all — works with both source and minified JS because
            //   darkify_check_preloading() always reads the property directly.
            //   The source JS also has a _dkf_iframe_disabled guard for belt-and-
            //   suspenders coverage.
            //
            // Stage 2 — 'after' (runs AFTER client_main[.min].js on EVERY page):
            //   • Cleans up the localStorage property intercept from Stage 1.
            //   • Overrides the five injection helpers so the PARENT page's
            //     darkify_apply_dark_to_iframe() applyDirect() closures (which look
            //     up these functions by name in the global scope at call time) become
            //     no-ops for cross-origin and not-yet-loaded iframes.
            $enable_frontend_iframe = isset($options['enable_frontend_iframe_dark_mode']) ? $options['enable_frontend_iframe_dark_mode'] : true;
            if (!$enable_frontend_iframe) {

                // Stage 1 — intercept localStorage.darkify_last_state BEFORE client_main.
                wp_add_inline_script(
                    'darkify-client-main',
                    '(function(){' .
                        // Only apply when this window is a child frame, the setting
                        // exists and is OFF, and it is not the admin panel context.
                        'if(window===window.top)return;' .
                        'if(typeof darkify_enable_frontend_iframe_dark_mode==="undefined"' .
                            '||darkify_enable_frontend_iframe_dark_mode==="1")return;' .
                        'if(typeof darkify_is_this_admin_panel!=="undefined"' .
                            '&&darkify_is_this_admin_panel==="1")return;' .
                        // Define an own property on localStorage that intercepts reads
                        // of darkify_last_state.  darkify_check_preloading() uses
                        // localStorage.darkify_last_state (property access, not getItem),
                        // so this getter is hit every time the function runs.
                        // Returning "0" makes the function take the "was explicitly set
                        // to 0 → not preloaded" branch and return false.
                        // The setter is silently ignored so state writes in the iframe
                        // don't pollute the shared localStorage for other tabs/pages.
                        'try{' .
                            'Object.defineProperty(localStorage,"darkify_last_state",{' .
                                'get:function(){return"0";},set:function(){},configurable:true' .
                            '});' .
                        '}catch(e){' .
                            // Fallback for environments where Object.defineProperty on
                            // Storage objects is not supported: temporarily set the value.
                            'try{window._dkf_ls_bk=localStorage.darkify_last_state;' .
                                'localStorage.darkify_last_state="0";}catch(e2){}' .
                        '}' .
                    '})();',
                    'before'
                );

                // Stage 2 — cleanup + injection helper overrides AFTER client_main.
                wp_add_inline_script(
                    'darkify-client-main',
                    '(function(){' .
                        // Remove the own-property intercept so subsequent reads of
                        // localStorage.darkify_last_state return the real stored value.
                        // "delete" removes the own property, restoring prototype lookup.
                        'if(window!==window.top){' .
                            'try{delete localStorage.darkify_last_state;}catch(e){' .
                                // Fallback: restore saved value
                                'try{if(typeof window._dkf_ls_bk!=="undefined"){' .
                                    'if(window._dkf_ls_bk)localStorage.darkify_last_state=window._dkf_ls_bk;' .
                                    'else localStorage.removeItem("darkify_last_state");' .
                                '}delete window._dkf_ls_bk;}catch(e2){}' .
                            '}' .
                        '}' .
                        // Override injection helpers so the PARENT page's applyDirect()
                        // closures (which already hold references to these global
                        // functions) become no-ops for frontend iframes.
                        'function _dkfAdmin(){' .
                            'return typeof darkify_is_this_admin_panel!=="undefined"&&darkify_is_this_admin_panel==="1";' .
                        '}' .
                        'if(typeof darkify_process_iframes==="function"){' .
                            'var _op=darkify_process_iframes;' .
                            'darkify_process_iframes=function(){if(_dkfAdmin())return _op();};' .
                        '}' .
                        'if(typeof darkify_inject_css_into_iframe==="function"){' .
                            'var _oi=darkify_inject_css_into_iframe;' .
                            'darkify_inject_css_into_iframe=function(d){if(_dkfAdmin())return _oi(d);};' .
                        '}' .
                        'if(typeof darkify_sync_iframe_vars==="function"){' .
                            'var _ov=darkify_sync_iframe_vars;' .
                            'darkify_sync_iframe_vars=function(d){if(_dkfAdmin())return _ov(d);};' .
                        '}' .
                        'if(typeof darkify_run_engine_on_iframe==="function"){' .
                            'var _oe=darkify_run_engine_on_iframe;' .
                            'darkify_run_engine_on_iframe=function(d){if(_dkfAdmin())return _oe(d);};' .
                        '}' .
                        'if(typeof darkify_inject_block_editor_css_into_iframe==="function"){' .
                            'var _ob=darkify_inject_block_editor_css_into_iframe;' .
                            'darkify_inject_block_editor_css_into_iframe=function(d){if(_dkfAdmin())return _ob(d);};' .
                        '}' .
                    '})();',
                    'after'
                );
            }

            // Enqueue the global floating switcher style from settings
            $enable_dark_mode_switch = isset($options['enable_dark_mode_switch']) ? $options['enable_dark_mode_switch'] : true;
            if ($enable_dark_mode_switch) {
                $global_variant = isset($options['enable_dark_switcher']) ? $options['enable_dark_switcher'] : 'classic';
                wp_enqueue_style('theme-' . $global_variant);

                // Separate mobile floating switcher (Different Switch in Mobile) — its toggler
                // style may differ from the default, so enqueue that variant's CSS as well.
                if (!empty($options['dark_switcher_switch_in_mobile'])) {
                    $mobile_variant = isset($options['enable_dark_switcher_in_mobile']) ? $options['enable_dark_switcher_in_mobile'] : 'classic';
                    wp_enqueue_style('theme-' . $this->darkify_switch_to_variant($mobile_variant));
                }
            }

            // Detect [darkify] shortcode usage in current post and enqueue its variant
            $post = get_post();
            if ($post && !empty($post->post_content)) {
                if (has_shortcode($post->post_content, 'darkify')) {
                    preg_match_all('/\[darkify\b[^\]]*\bswitch=["\']([^"\']+)["\'][^\]]*\]/i', $post->post_content, $matches);
                    $used_switches = !empty($matches[1]) ? $matches[1] : [];
                    foreach ($used_switches as $switch_val) {
                        wp_enqueue_style('theme-' . $this->darkify_switch_to_variant($switch_val));
                    }
                    // Shortcode without switch= attribute defaults to classic
                    preg_match_all('/\[darkify\b/i', $post->post_content, $all_sc);
                    if (count($all_sc[0]) > count($used_switches)) {
                        wp_enqueue_style('theme-classic');
                    }
                }

                // Detect darkify/switcher Gutenberg block usage and enqueue its variant
                if (function_exists('has_block') && has_block('darkify/switcher', $post)) {
                    foreach ($this->darkify_get_block_variants($post->post_content) as $variant) {
                        wp_enqueue_style('theme-' . $variant);
                    }
                }
            }

            // Detect switcher used in nav menus and enqueue its variant
            $show_in_menu = isset($options['show_in_menu']) ? $options['show_in_menu'] : [];
            $enable_in_menu = isset($show_in_menu['enable_switch_in_menu']) ? $show_in_menu['enable_switch_in_menu'] : false;
            if ($enable_in_menu) {
                $select_menus = isset($show_in_menu['select_menus']) ? $show_in_menu['select_menus'] : [];
                foreach ($select_menus as $select_menu) {
                    $shortcode = $select_menu['darkify_menu_shortcode_helper'] ?? '';
                    if ($shortcode) {
                        preg_match('/\bswitch=["\']([^"\']+)["\']/', $shortcode, $m);
                        $switch_val = $m[1] ?? '1';
                        wp_enqueue_style('theme-' . $this->darkify_switch_to_variant($switch_val));
                    }
                }
            }
        }
    }

    private function darkify_switch_to_variant($switch_value)
    {
        $map = [
            '1' => 'classic', '2' => 'expand', '3' => 'inner-moon',
            '4' => 'within',  '5' => 'orbit',
        ];
        if (is_numeric($switch_value)) {
            return $map[(string) $switch_value] ?? 'classic';
        }
        return $switch_value ?: 'classic';
    }

    private function darkify_get_block_variants($content)
    {
        $variants = [];
        $this->darkify_collect_block_variants(parse_blocks($content), $variants);
        return array_unique($variants);
    }

    private function darkify_collect_block_variants($blocks, &$variants)
    {
        foreach ($blocks as $block) {
            if ('darkify/switcher' === $block['blockName']) {
                $variants[] = $block['attrs']['style'] ?? 'classic';
            }
            if (!empty($block['innerBlocks'])) {
                $this->darkify_collect_block_variants($block['innerBlocks'], $variants);
            }
        }

        $options = get_option('darkify');
        $min     = (apply_filters('darkify_dev_mode', false) || WP_DEBUG) ? '' : '.min';

        // Always register all variant styles so page builders (Elementor, etc.) can reference them.
        wp_register_style('darkify-client-main', DARKIFY_DIR_URL . 'src/assets/css/client_main' . $min . '.css', array(), DARKIFY_VERSION);
        wp_register_style('theme-classic',    DARKIFY_DIR_URL . 'src/assets/css/switcher/classic'    . $min . '.css', array('darkify-client-main'), DARKIFY_VERSION, 'all');
        wp_register_style('theme-expand',     DARKIFY_DIR_URL . 'src/assets/css/switcher/expand'     . $min . '.css', array('darkify-client-main'), DARKIFY_VERSION, 'all');
        wp_register_style('theme-inner-moon', DARKIFY_DIR_URL . 'src/assets/css/switcher/inner-moon' . $min . '.css', array('darkify-client-main'), DARKIFY_VERSION, 'all');
        wp_register_style('theme-orbit',      DARKIFY_DIR_URL . 'src/assets/css/switcher/orbit'      . $min . '.css', array('darkify-client-main'), DARKIFY_VERSION, 'all');
        wp_register_style('theme-within',     DARKIFY_DIR_URL . 'src/assets/css/switcher/within'     . $min . '.css', array('darkify-client-main'), DARKIFY_VERSION, 'all');

        // Determine which variants are actually needed on this page.
        $variants = $this->darkify_collect_page_variants($options);

        if (empty($variants)) {
            return;
        }

        // Enqueue main CSS + only the required variant CSS — all resolved before wp_head fires.
        wp_enqueue_style('darkify-client-main');
        foreach ($variants as $variant) {
            wp_enqueue_style("theme-{$variant}");
        }
        wp_enqueue_script('darkify-client-main', DARKIFY_DIR_URL . 'src/assets/js/client_main' . $min . '.js', array(), DARKIFY_VERSION);
    }

    /**
     * Collects all switcher variants needed on the current page so they can be
     * enqueued during wp_enqueue_scripts (before wp_head), guaranteeing CSS loads
     * in the <head> regardless of page builder or theme.
     */
    private function darkify_collect_page_variants($options)
    {
        $all_variants = array('classic', 'expand', 'inner-moon', 'orbit', 'within');
        $num_map      = array('1' => 'classic', '2' => 'expand', '3' => 'inner-moon', '4' => 'within', '5' => 'orbit');
        $variants     = array();

        // 1. Global / floating switcher variant (always present when dark mode is active).
        $global_variant = isset($options['enable_dark_switcher']) ? $options['enable_dark_switcher'] : 'classic';
        if (in_array($global_variant, $all_variants, true)) {
            $variants[] = $global_variant;
        }

        // 2. Menu switcher — parse variant from each saved shortcode helper string.
        $show_in_menu = isset($options['show_in_menu']) ? $options['show_in_menu'] : array();
        if (!empty($show_in_menu['enable_switch_in_menu']) && !empty($show_in_menu['select_menus'])) {
            foreach ($show_in_menu['select_menus'] as $menu_item) {
                $helper = isset($menu_item['darkify_menu_shortcode_helper']) ? $menu_item['darkify_menu_shortcode_helper'] : '';
                if ($helper && preg_match('/switch=["\']?([^"\'>\s\]]+)["\']?/', $helper, $m)) {
                    $v = isset($num_map[$m[1]]) ? $num_map[$m[1]] : $m[1];
                    if (in_array($v, $all_variants, true)) {
                        $variants[] = $v;
                    }
                }
            }
        }

        // 3. Page-level detection — shortcodes, Gutenberg blocks, Elementor widget.
        global $post;
        if ($post) {
            $content = $post->post_content;

            // Shortcodes: parse switch attribute from every [darkify] tag.
            if (has_shortcode($content, 'darkify')) {
                preg_match_all('/\[darkify[^\]]*switch=["\']?([^"\'>\s\]]+)/', $content, $sc_matches);
                foreach ($sc_matches[1] as $switch_val) {
                    $v = isset($num_map[$switch_val]) ? $num_map[$switch_val] : $switch_val;
                    if (in_array($v, $all_variants, true)) {
                        $variants[] = $v;
                    } else {
                        $variants[] = 'classic'; // default when no valid variant found
                    }
                }
                // If [darkify] exists but has no switch attribute, default to classic.
                if (empty($sc_matches[1]) && has_shortcode($content, 'darkify')) {
                    $variants[] = 'classic';
                }
            }

            // Gutenberg blocks: walk the block tree and collect style attributes.
            if (function_exists('has_block') && has_block('darkify/switcher', $post)) {
                $blocks   = parse_blocks($content);
                $variants = array_merge($variants, $this->darkify_extract_block_variants($blocks, $all_variants));
            }

            // Elementor widget: if Darkify widget appears in the page's Elementor data,
            // load all variants — the specific style is stored as a control value that
            // would require deep JSON parsing to extract reliably.
            if (class_exists('\Elementor\Plugin')) {
                $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
                if ($elementor_data && strpos($elementor_data, '"widgetType":"darkify"') !== false) {
                    return $all_variants;
                }
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * Recursively walks a parsed block tree and collects darkify/darkify style values.
     */
    private function darkify_extract_block_variants($blocks, $all_variants)
    {
        $variants = array();
        foreach ($blocks as $block) {
            if ('darkify/switcher' === $block['blockName']) {
                $style = isset($block['attrs']['style']) ? $block['attrs']['style'] : 'classic';
                if (in_array($style, $all_variants, true)) {
                    $variants[] = $style;
                }
            }
            if (!empty($block['innerBlocks'])) {
                $variants = array_merge($variants, $this->darkify_extract_block_variants($block['innerBlocks'], $all_variants));
            }
        }
        return $variants;
    }
    public function darkify_is_dark_mode_allowed()
    {
        $options = get_option('darkify');

        // "Frontend Dark Mode" (enable_dark_mode_switch) is the MASTER control
        // for the whole frontend feature. When it's off, load NOTHING on the
        // front end — no CSS, no JS, no inline output — since every asset and
        // inline script path is gated through this method. (Admin dark mode is a
        // separate option handled elsewhere and is unaffected.)
        $enable_dark_mode_switch = isset($options['enable_dark_mode_switch'])
            ? $options['enable_dark_mode_switch']
            : true;
        if (! $enable_dark_mode_switch) {
            return False;
        }

        /* Disable if Oxygen Builder is Opened */
        if (isset($_GET['ct_builder'])) {
            if ($_GET['ct_builder'] == "true") {
                if (!isset($_GET['oxygen_iframe'])) {
                    return False;
                }
            }
        }

        return True;
    }


    public function darkify_client_header_script()
    {
        if ($this->darkify_is_dark_mode_allowed()) {
            include_once DarkifyUtils::darkify_locate_template('header_script.php');
        }
    }

    public function darkify_client_footer_script()
    {
        if ($this->darkify_is_dark_mode_allowed()) {
            include_once DarkifyUtils::darkify_locate_template('footer_script.php');
        }
    }

    function darkify_switch_in_menu($items, $args)
    {
        $options = get_option('darkify');
        $show_in_menu = isset($options['show_in_menu']) ? $options['show_in_menu'] : '';

        // Gate here (not at filter registration) so the live preview's unsaved
        // "Enable Menu Switch" toggle takes effect without saving.
        $enable_switcher_in_menu = isset($show_in_menu["enable_switch_in_menu"]) ? $show_in_menu["enable_switch_in_menu"] : false;
        if (! $enable_switcher_in_menu) {
            return $items;
        }

        $select_menus = isset($show_in_menu['select_menus']) ? $show_in_menu['select_menus'] : [];

        $last_shortcode = '';

        if ($this->darkify_is_dark_mode_allowed() && isset($args->menu->term_id)) {
            foreach ($select_menus as $select_menu) {

                $switch_in_menu_location = $select_menu['switch_in_menu_location'] ?? '';
                $darkify_menu_shortcode_helper = $select_menu['darkify_menu_shortcode_helper'] ?? '';
                $menu_position = isset($select_menu['select_menu_position']) ? $select_menu['select_menu_position'] : 'after';
                if ($args->menu->term_id == $switch_in_menu_location) {
                    $last_shortcode = $darkify_menu_shortcode_helper;
                }
            }
            if ($last_shortcode) {
                $items = ($menu_position == 'before') ? '<li class="menu-item">' . do_shortcode($last_shortcode) . '</li>' . $items : $items . '<li class="menu-item">' . do_shortcode($last_shortcode) . '</li>';
            }
        }

        return $items;
    }

    /**
     * Put the switch inside a block-theme or Kadence navigation.
     *
     * `wp_nav_menu_items` only fires for classic wp_nav_menu() output, so on a
     * block theme (and on any site using Kadence Navigation) the Menu Switch
     * feature had nothing to hook: the menu rendered, the setting was saved, and
     * no switch ever appeared. These navigations are blocks, so this is the
     * equivalent hook for them.
     *
     * The selection is matched on the namespaced `<post_type>:<id>` key written
     * by the picker (see AbstractRestController::darkify_block_menu_sources()),
     * which is what keeps block and Kadence menu POST ids from being confused
     * with classic menu TERM ids.
     *
     * @param string $block_content Rendered HTML for this block.
     * @param array  $block         Parsed block, including its attributes.
     * @return string
     */
    function darkify_switch_in_block_menu($block_content, $block)
    {
        // render_block fires for every block on the page, so reject as early and
        // as cheaply as possible: two string comparisons for the overwhelming
        // majority of blocks, and nothing else.
        if (empty($block['blockName'])) {
            return $block_content;
        }

        if ($block['blockName'] === 'core/navigation') {
            $source = 'wp_navigation';
            $ref    = isset($block['attrs']['ref']) ? (int) $block['attrs']['ref'] : 0;
        } elseif ($block['blockName'] === 'kadence/navigation') {
            $source = 'kadence_navigation';
            $ref    = isset($block['attrs']['id']) ? (int) $block['attrs']['id'] : 0;
        } else {
            return $block_content;
        }

        // A navigation block with no saved menu behind it (unsaved, or rendering
        // its fallback) has nothing this setting could have been pointed at.
        if ($ref <= 0) {
            return $block_content;
        }

        $options      = get_option('darkify');
        $show_in_menu = isset($options['show_in_menu']) ? $options['show_in_menu'] : '';

        // Gated here rather than at filter registration for the same reason as
        // the classic path: the live preview toggles this without saving.
        if (empty($show_in_menu['enable_switch_in_menu'])) {
            return $block_content;
        }

        if (! $this->darkify_is_dark_mode_allowed()) {
            return $block_content;
        }

        $select_menus = isset($show_in_menu['select_menus']) ? $show_in_menu['select_menus'] : [];
        $needle       = $source . ':' . $ref;
        $shortcode    = '';
        $position     = 'after';

        foreach ((array) $select_menus as $select_menu) {
            if (($select_menu['switch_in_menu_location'] ?? '') !== $needle) {
                continue;
            }
            // Last match wins, matching the classic path's behaviour. Unlike
            // that path the position is read from the row that actually
            // matched, so it cannot be taken from an unrelated row.
            $shortcode = $select_menu['darkify_menu_shortcode_helper'] ?? '';
            $position  = $select_menu['select_menu_position'] ?? 'after';
        }

        if (! $shortcode) {
            return $block_content;
        }

        $item = '<li class="menu-item">' . do_shortcode($shortcode) . '</li>';

        $injected = $this->darkify_inject_menu_item($block_content, $item, $position);

        // Unrecognised markup is left exactly as the block rendered it: a
        // navigation with no switch is a far better outcome than a broken one.
        return ($injected === null) ? $block_content : $injected;
    }

    /**
     * A `<ul>` or `</ul>` tag, with capture 1 set to "/" on a closing tag.
     *
     * The alternation skips over quoted attribute values rather than using a
     * plain `[^>]*`: an attribute may legitimately contain a `>` (`data-x="a>b"`),
     * and a bare `[^>]*` ends the match in the middle of that value. The open
     * tag would then appear to end mid-attribute, so a "before" insertion landed
     * inside the attribute and broke the markup, and a `>` inside a submenu's
     * own `<ul>` attributes miscounted the nesting depth below.
     */
    const DARKIFY_UL_TAG = '/<(\/?)ul\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/i';

    /**
     * Insert a list item into the first top-level `<ul>` of a rendered block.
     *
     * The closing tag is found by balancing `<ul>`/`</ul>` rather than by
     * searching for the last `</ul>`: navigations nest submenus, so the last one
     * closes a submenu and the switch would land inside a dropdown.
     *
     * @return string|null Null when the markup has no usable list.
     */
    private function darkify_inject_menu_item($html, $item, $position)
    {
        if (! preg_match(self::DARKIFY_UL_TAG, $html, $open, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        // A fragment that opens on a closing tag has no list to add to.
        if ($open[1][0] === '/') {
            return null;
        }

        $after_open = $open[0][1] + strlen($open[0][0]);

        if ($position === 'before') {
            return substr($html, 0, $after_open) . $item . substr($html, $after_open);
        }

        $depth  = 1;
        $offset = $after_open;
        $tag    = null;

        while ($depth > 0 && preg_match(self::DARKIFY_UL_TAG, $html, $tag, PREG_OFFSET_CAPTURE, $offset)) {
            $depth += ($tag[1][0] === '/') ? -1 : 1;
            $offset = $tag[0][1] + strlen($tag[0][0]);
        }

        if ($depth !== 0 || $tag === null) {
            return null;
        }

        $before_close = $offset - strlen($tag[0][0]);

        return substr($html, 0, $before_close) . $item . substr($html, $before_close);
    }
}
