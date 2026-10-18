<?php

/**
 * The file of the Darkify class.
 *
 * @link       https://themeatelier.net
 * @since      1.0.0
 *
 * @package Darkify
 */

namespace ThemeAtelier\Darkify\Includes;

use ThemeAtelier\Darkify\Admin\Admin;
use ThemeAtelier\Darkify\Admin\Assets;
use ThemeAtelier\Darkify\Admin\Menu;
use ThemeAtelier\Darkify\Admin\Rest\HelpRest;
use ThemeAtelier\Darkify\Admin\Rest\PreviewRest;
use ThemeAtelier\Darkify\Admin\Rest\SettingsRest;
use ThemeAtelier\Darkify\Frontend\Frontend;


// don't call the file directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The main class of the plugin.
 *
 * Handle all the class and methods of the plugin.
 *
 * @author     ThemeAtelier <themeatelierbd@gmail.com>
 */
class Darkify
{
    /**
     * Plugin version
     *
     * @since    1.0.0
     * @access   protected
     * @var string
     */
    protected $version;

    /**
     * Plugin slug
     *
     * @since    1.0.0
     * @access   protected
     * @var string
     */
    protected $plugin_slug;

    /**
     * Main Loader.
     *
     * The loader that's responsible for maintaining and registering all hooks that empowers
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var object
     */
    protected $loader;
    /**
     * Constructor for the Darkify class.
     *
     * Sets up all the appropriate hooks and actions within the plugin.
     */
    public function __construct()
    {
        $this->version     = DARKIFY_VERSION;
        $this->plugin_slug = 'darkify';
        $this->load_dependencies();
        $this->define_constants();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->init_action();
        add_action('plugin_loaded', array($this, 'init_plugin'));

        // Translations load on `after_setup_theme`, not alongside the boot action
        // above. `plugin_loaded` fires while plugin files are still being
        // included, before the pluggable functions exist, so `determine_locale()`
        // cannot yet see a per-user admin language. Priority 1 puts this ahead of
        // Admin::init_components (same hook, priority 10), which is where the
        // settings schema evaluates its `__()` calls.
        add_action('after_setup_theme', array($this, 'load_textdomain'), 1);

        add_action('elementor/controls/register', [$this, 'register_controls']);
        add_action('elementor/widgets/register', array($this, 'register_darkify_widget'));

        add_action('init', [$this, 'create_darkify_block_init']);
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    public function darkify_redirect_to($plugin)
    {
        if (DARKIFY_BASENAME === $plugin) {
            $redirect_url = esc_url(admin_url('admin.php?page=darkify'));
            exit(wp_kses_post(wp_safe_redirect($redirect_url)));
        }
    }

    /**
     * The slug of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_slug;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }


    public function register_controls($controls_manager)
    {
        require_once DARKIFY_PATH . 'src/Admin/elementor/controls/class-elementor-control-switch.php';

        $controls_manager->register_control(
            'darkify_switch',
            new \Elementor_Darkify_Switcher()
        );
    }
    public function register_darkify_widget($widgets_manager)
    {
        require_once DARKIFY_PATH . 'src/Admin/elementor/widgets/elementor-widget.php';
        $widgets_manager->register(new \Elementor_Darkify());
    }

    /**
     * Define the constants
     *
     * @return void
     */
    public function define_constants()
    {
        define('DARKIFY_URL', plugins_url('', DARKIFY_FILE));
        define('DARKIFY_ASSETS', DARKIFY_URL . '/src/assets/');
        define('DARKIFY_ADMIN', DARKIFY_URL . '/src/Admin');
    }

    /**
     * Fire the plugin's own boot action.
     *
     * @return void
     */
    public function init_plugin()
    {
        do_action('darkify_loaded');
    }

    /**
     * Load the plugin's translations.
     *
     * @return void
     */
    public function load_textdomain()
    {
        // `load_plugin_textdomain()` wants a path *relative to WP_PLUGIN_DIR* —
        // it does `WP_PLUGIN_DIR . '/' . $path`. DARKIFY_DIR_NAME is an absolute
        // path (`dirname(__FILE__)`), so the old call built a nonsense path like
        // `/wp-content/plugins//Users/…/plugins/darkify/languages` and the .mo
        // was never found. `wp_set_script_translations()` in Admin/Assets.php
        // does take an absolute path and got it right, which is why the React
        // strings translated and nothing from PHP ever did.
        load_plugin_textdomain(
            'darkify',
            false,
            dirname(plugin_basename(DARKIFY_FILE)) . '/languages'
        );
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Loader. Orchestrates the hooks of the plugin.
     * - Teamproi18n. Defines internationalization functionality.
     * - Admin. Defines all hooks for the admin area.
     * - Frontend. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        $this->loader = new Loader();
    }

    /**
     * Register all of the hooks related to the admin dashboard functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new Admin($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'admin_dequeue_for_specefic_pages', 999);

        // The React admin: its menu page, its bundle, and the REST endpoints it
        // talks to. The controllers hook themselves onto `rest_api_init`, so they
        // must be constructed on every request, not only in wp-admin.
        new Menu();
        new Assets();
        new SettingsRest();
        // Backs the Get Help page's Recommended tab (ThemeAtelier plugin
        // directory with one-click install/activate).
        new HelpRest();
        // Constructed unconditionally (not admin-only): its `init` hook also has
        // to fire on the frontend iframe request to swap in the preview values.
        new PreviewRest();
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {
        $plugin_public = new Frontend($this->get_plugin_name(), $this->get_version());
    }

    public static function switcher($variant, $unique_id)
    {
        if ('classic' == $variant) {
            return '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="0px" height="0px" fill="currentColor" stroke-linecap="round" class="theme-toggle__classic" viewBox="0 0 32 32"><clipPath id="theme-toggle__classic__cutout_' . esc_attr($unique_id) . '"><path d="M0-5h30a1 1 0 0 0 9 13v24H0Z" /></clipPath><g clip-path="url(#theme-toggle__classic__cutout_' . esc_attr($unique_id) . ')"><circle cx="16" cy="16" r="9.34" /><g stroke="currentColor" stroke-width="1.5"><path d="M16 5.5v-4" /><path d="M16 30.5v-4" /><path d="M1.5 16h4" /><path d="M26.5 16h4" /><path d="m23.4 8.6 2.8-2.8" /><path d="m5.7 26.3 2.9-2.9" /><path d="m5.8 5.8 2.8 2.8" /><path d="m23.4 23.4 2.9 2.9" /></g></g></svg>';
        } else if ('expand' == $variant) {
            return '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="0px" height="0px" fill="currentColor" class="theme-toggle__expand" viewBox="0 0 32 32"><clipPath id="theme-toggle__expand__cutout_' . esc_attr($unique_id) . '"><path d="M0-11h25a1 1 0 0017 13v30H0Z" /></clipPath><g clip-path="url(#theme-toggle__expand__cutout_' . esc_attr($unique_id) . ')"><circle cx="16" cy="16" r="8.4" /><path d="M18.3 3.2c0 1.3-1 2.3-2.3 2.3s-2.3-1-2.3-2.3S14.7.9 16 .9s2.3 1 2.3 2.3zm-4.6 25.6c0-1.3 1-2.3 2.3-2.3s2.3 1 2.3 2.3-1 2.3-2.3 2.3-2.3-1-2.3-2.3zm15.1-10.5c-1.3 0-2.3-1-2.3-2.3s1-2.3 2.3-2.3 2.3 1 2.3 2.3-1 2.3-2.3 2.3zM3.2 13.7c1.3 0 2.3 1 2.3 2.3s-1 2.3-2.3 2.3S.9 17.3.9 16s1-2.3 2.3-2.3zm5.8-7C9 7.9 7.9 9 6.7 9S4.4 8 4.4 6.7s1-2.3 2.3-2.3S9 5.4 9 6.7zm16.3 21c-1.3 0-2.3-1-2.3-2.3s1-2.3 2.3-2.3 2.3 1 2.3 2.3-1 2.3-2.3 2.3zm2.4-21c0 1.3-1 2.3-2.3 2.3S23 7.9 23 6.7s1-2.3 2.3-2.3 2.4 1 2.4 2.3zM6.7 23C8 23 9 24 9 25.3s-1 2.3-2.3 2.3-2.3-1-2.3-2.3 1-2.3 2.3-2.3z" /></g></svg>';
        } else if ('inner-moon' === $variant) {
            return '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="0px" height="0px" fill="currentColor" class="theme-toggle__inner-moon" viewBox="0 0 32 32"><path d="M27.5 11.5v-7h-7L16 0l-4.5 4.5h-7v7L0 16l4.5 4.5v7h7L16 32l4.5-4.5h7v-7L32 16l-4.5-4.5zM16 25.4a9.39 9.39 0 1 1 0-18.8 9.39 9.39 0 1 1 0 18.8z" /><circle cx="16" cy="16" r="8.1" /></svg>';
        } else if ('within' === $variant) {
            return '<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="theme-toggle__within" height="1em" width="1em" viewBox="0 0 32 32" fill="currentColor"><clipPath id="theme-toggle__within__clip_' . esc_attr($unique_id) . '"><path d="M0 0h32v32h-32ZM6 16A1 1 0 0026 16 1 1 0 006 16" /></clipPath><g clip-path="url(#theme-toggle__within__clip_' . esc_attr($unique_id) . ')"><path d="M30.7 21.3 27.1 16l3.7-5.3c.4-.5.1-1.3-.6-1.4l-6.3-1.1-1.1-6.3c-.1-.6-.8-.9-1.4-.6L16 5l-5.4-3.7c-.5-.4-1.3-.1-1.4.6l-1 6.3-6.4 1.1c-.6.1-.9.9-.6 1.3L4.9 16l-3.7 5.3c-.4.5-.1 1.3.6 1.4l6.3 1.1 1.1 6.3c.1.6.8.9 1.4.6l5.3-3.7 5.3 3.7c.5.4 1.3.1 1.4-.6l1.1-6.3 6.3-1.1c.8-.1 1.1-.8.7-1.4zM16 25.1c-5.1 0-9.1-4.1-9.1-9.1 0-5.1 4.1-9.1 9.1-9.1s9.1 4.1 9.1 9.1c0 5.1-4 9.1-9.1 9.1z" /></g><path class="theme-toggle__within__circle" d="M16 7.7c-4.6 0-8.2 3.7-8.2 8.2s3.6 8.4 8.2 8.4 8.2-3.7 8.2-8.2-3.6-8.4-8.2-8.4zm0 14.4c-3.4 0-6.1-2.9-6.1-6.2s2.7-6.1 6.1-6.1c3.4 0 6.1 2.9 6.1 6.2s-2.7 6.1-6.1 6.1z" />
            <path class="theme-toggle__within__inner" d="M16 9.5c-3.6 0-6.4 2.9-6.4 6.4s2.8 6.5 6.4 6.5 6.4-2.9 6.4-6.4-2.8-6.5-6.4-6.5z" /></svg>';
        } elseif ('orbit' === $variant) {
            return '<span class="toggle-track"><span class="toggle-sun"><svg aria-hidden="true" focusable="false" stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg"><path d="M277.3 32h-42.7v64h42.7V32zm129.1 43.7L368 114.1l29.9 29.9 38.4-38.4-29.9-29.9zm-300.8 0l-29.9 29.9 38.4 38.4 29.9-29.9-38.4-38.4zM256 128c-70.4 0-128 57.6-128 128s57.6 128 128 128 128-57.6 128-128-57.6-128-128-128zm224 106.7h-64v42.7h64v-42.7zm-384 0H32v42.7h64v-42.7zM397.9 368L368 397.9l38.4 38.4 29.9-29.9-38.4-38.4zm-283.8 0l-38.4 38.4 29.9 29.9 38.4-38.4-29.9-29.9zm163.2 48h-42.7v64h42.7v-64z"></path></svg></span><span class="toggle-thumb"></span><span class="toggle-moon"><svg aria-hidden="true" focusable="false" stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 384 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg"><path d="M223.5 32C100 32 0 132.3 0 256S100 480 223.5 480c60.6 0 115.5-24.2 155.8-63.4c5-4.9 6.3-12.5 3.1-18.7s-10.1-9.7-17-8.5c-9.8 1.7-19.8 2.6-30.1 2.6c-96.9 0-175.5-78.8-175.5-176c0-65.8 36-123.1 89.3-153.3c6.1-3.5 9.2-10.5 7.7-17.3s-7.3-11.9-14.3-12.5c-6.3-.5-12.6-.8-19-.8z"></path></svg></span></span>';
        }
    }

    public static function switcher_wrapper($type, $unique_id, $variant, $switch_size, $position_class = '', $floating_switch_position = '', $tooltip_classes = '', $hide_dark_mode_on_mobile_screen = '', $floating_switch_width = '', $tooltip_on_floating_switch = '')
    {

        // A single `style` attribute: this used to be emitted TWICE (once with
        // just the scale variable, then — for `shortcode` — a second `style="..."`
        // appended after `class`). HTML only honors the FIRST of two duplicate
        // attributes, so that second one (the only place `position: relative` was
        // set) was silently dropped by every browser. Without it, an absolutely-
        // positioned inner element (e.g. a pill variant's sliding thumb) has no
        // guarantee of being positioned relative to THIS wrapper — it falls back
        // to whatever positioned ancestor happens to exist (or none), which is
        // exactly the kind of thing a page's content wrapper (often already
        // `position: relative`) can accidentally paper over while a theme's nav
        // `<li>` — the Menu Switch's actual context — usually doesn't.
        $style = '--darkify-switch-scale: ' . esc_attr($switch_size) . ';' . ($type === 'shortcode' ? ' position: relative;' : '');
        $classes = 'darkify_switch darkify_switch_style switch-' . esc_attr($variant)
            . ($type !== 'shortcode' ? ' ' . esc_attr($position_class) . ' ' . esc_attr($floating_switch_position) . ' ' . esc_attr($tooltip_classes) . ' ' . esc_attr($hide_dark_mode_on_mobile_screen) : '');

        /*
         * A native <button>, so the switch is reachable with Tab and operable
         * with Enter/Space without any extra script (WCAG 2.1.1, 4.1.2).
         *
         * - The accessible name is fixed and the on/off state lives in
         *   `aria-pressed`, which client_main.js keeps in step with the
         *   `darkify_dark_mode_enabled` class. Rendered as "false" here: the
         *   real state comes from localStorage, which the server cannot see.
         * - Clicks are handled by one delegated listener in client_main.js
         *   instead of an inline `onclick`, which a strict CSP would block.
         * - Everything inside is phrasing content (<span>), as a button
         *   requires. The tooltip only repeats the label visually, so it is
         *   hidden from assistive technology to avoid announcing it twice.
         */
        $label = apply_filters('darkify_switch_label', __('Dark mode', 'darkify'), $type);
        return '<button type="button" style="' . $style . '" id="darkify_switch_' . esc_attr($unique_id) . '" class="' . $classes . '" aria-pressed="false" aria-label="' . esc_attr($label) . '"><span class="theme-toggle">' . self::switcher($variant, $unique_id) . '</span>' . ($type !== 'shortcode' && $floating_switch_width ? '<span class="darkify_tooltiptext" aria-hidden="true">' . esc_html($tooltip_on_floating_switch) . '</span>' : '') . '</button>';
    }

    private function init_action()
    {
        add_action('activated_plugin', [$this, 'darkify_redirect_to']);
    }


    public function create_darkify_block_init()
    {
        register_block_type_from_metadata(
            DARKIFY_PATH . 'src/Admin/blocks/',
            array(
                'render_callback' => [$this, 'render_darkify_block_callback'],
            )
        );

        // block.json's "textdomain" makes core register the editor script's
        // translations, but without a path, so core only looks in
        // wp-content/languages/plugins. Point it at the bundled JSON as well
        // (core still prefers a language pack there when one exists). The JSON
        // is built by bin/i18n.js, keyed to src/Admin/blocks/build/index.js.
        wp_set_script_translations('darkify-switcher-editor-script', 'darkify', DARKIFY_PATH . 'languages');

        wp_localize_script(
            'darkify-switcher-editor-script',
            'darkify',
            array(
                'dir_url' => DARKIFY_DIR_URL,
                /*
                 * How many of the block's switch styles this plugin may select,
                 * counted from the start of the list in blocks/src/edit.js.
                 * The rest are shown as locked Pro previews.
                 *
                 * Sent from here rather than decided in the editor's CSS: the
                 * old lock was a `:nth-child` rule, which a mouse respected but
                 * the keyboard walked straight past, and which silently changed
                 * meaning if the style list were ever reordered.
                 */
                'unlocked_styles' => 5,
            )
        );
    }

    /**
     * Render callback for the Darkify Pro block.
     */
    public function render_darkify_block_callback($attributes)
    {
        $style       = $attributes['style'] ?? 'classic';
        $size        = $attributes['size'] ?? '1.0';
        $custom_size = $attributes['custom_size'] ?? 100;
        $bg_light    = $attributes['switch_bg_light'] ?? '#121116';
        $bg_dark     = $attributes['switch_bg_dark'] ?? '#ffffff';
        $light_icon_color = $attributes['light_icon_color'] ?? '#ffffff';
        $dark_icon_color  = $attributes['dark_icon_color'] ?? '#121116';
        $light_text_color = $attributes['light_text_color'] ?? '#121116';
        $dark_text_color  = $attributes['dark_text_color'] ?? '#ffffff';
        $border_width     = $attributes['border_width'] ?? 0;
        $border_light_color = $attributes['border_light_color'] ?? '#121116';
        $border_dark_color  = $attributes['border_dark_color'] ?? '#ffffff';
        $border_radius     = $attributes['border_radius'] ?? 7;
        $alignment = $attributes['alignment'] ?? 'left';

        // Size logic
        if ('custom' === $size) {
            $switcher_size = $custom_size;
        } else {
            $switcher_size = (float) $size * 100;
        }

        // The shortcode returns an empty string when dark mode is off for the
        // front end; don't leave a stray flex wrapper behind in that case.
        $switch = do_shortcode(wp_sprintf(
            '[darkify switch="%s" icon_size="40px" light_mode_bg="%s" dark_mode_bg="%s" light_mode_color="%s" dark_mode_color="%s" border="%spx" border_light_color="%s" border_dark_color="%s" border_radius="%spx" text_light_mode_color="%s" text_dark_mode_color="%s" switch_size="%s"]',
            $style,
            $bg_light,
            $bg_dark,
            $light_icon_color,
            $dark_icon_color,
            $border_width,
            $border_light_color,
            $border_dark_color,
            $border_radius,
            $light_text_color,
            $dark_text_color,
            $switcher_size
        ));

        if ('' === trim($switch)) {
            return '';
        }

        return '<div style="display: flex; justify-content: ' . esc_attr($alignment) . '">' . $switch . '</div>';
    }
}
