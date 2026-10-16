<?php

/**
 * The admin-facing functionality of the plugin.
 *
 * @link       https://themeatelier.net
 * @since      1.0.0
 *
 * @package darkify
 * @subpackage darkify/Admin
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin;

use ThemeAtelier\Darkify\Admin\ReviewNotice\ReviewNotice;
use ThemeAtelier\Darkify\Admin\ReviewNotice\ThemeAtelier_Offer_Banner;
use ThemeAtelier\Darkify\Admin\Schema\SchemaDefaults;
use ThemeAtelier\Darkify\Admin\Views\DarkifyOptions;
use ThemeAtelier\Darkify\Includes\DarkifyExternalSupport;
use ThemeAtelier\Darkify\Includes\DarkifyUtils;
use ThemeAtelier\Darkify\Admin\DBUpdates;

/**
 * The admin class
 */
class Admin
{

    /**
     * The min of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $min   The slug of this plugin.
     */
    private $min;
    public $utils;
    public $settings;
    public $external_support;
    private $plugin_name;
    private $version;
    public $unique_id;

    /**
     * Whether enqueue_scripts() actually put the admin engine in the queue on
     * this request. Read by darkify_restore_no_conflict_scripts(), which must
     * only re-add handles Darkify itself asked for.
     *
     * @var bool
     */
    private $engine_enqueued = false;

    /**
     * The class constructor.
     *
     * @param string $plugin_name The slug of the plugin.
     * @param string $version Current version of the plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->utils = new DarkifyUtils($this);
        $this->external_support = new DarkifyExternalSupport($this);
        $this->min = (apply_filters('darkify_dev_mode', false) || WP_DEBUG) ? '' : '.min';
        if (function_exists('wp_rand')) {
            $this->unique_id = wp_rand();
        }

        add_action('current_screen', function ($screen) {
            $options = get_option('darkify');
            $block_editor_dark_mode = isset($options['block_editor_dark_mode']) ? $options['block_editor_dark_mode'] : false;
            $enable_admin_panel_dark_mode = isset($options['enable_admin_panel_dark_mode']) ? $options['enable_admin_panel_dark_mode'] : false;
            $is_block_editor = method_exists($screen, 'is_block_editor') && $screen->is_block_editor();

            // Block Editor Dark Mode is INDEPENDENT of Admin Panel Dark Mode:
            // the block editor darkens whenever Block Editor Dark Mode is on,
            // even if the rest of wp-admin stays light. Every other admin screen
            // still keys on Admin Panel Dark Mode.
            $needs_engine = $is_block_editor
                ? (bool) $block_editor_dark_mode
                : (bool) $enable_admin_panel_dark_mode;

            // Darkify's own settings/help screens additionally load it even while
            // the option is OFF, so flipping "Admin Panel Dark Mode" in the React
            // admin can show the admin-bar icon (and have it actually work)
            // immediately instead of only after a reload. Loading it here cannot
            // darken anything on its own: both the FOUC snippet in
            // header_script.php and darkify_check_preloading() in client_main.js
            // are gated on `darkify_admin_panel_dark_enabled`.
            if ($needs_engine || $this->is_darkify_spa_page()) {
                add_action('admin_bar_menu',  array($this, 'darkify_admin_bar_switch'), 9999);
                add_action('admin_print_scripts', array($this, 'darkify_admin_header_script'), 1);
                add_action('admin_footer', array($this, 'darkify_admin_footer_script'));
            }
        });

        new ReviewNotice();
        new DBUpdates();
        if (! defined('THEMEATELIER_OFFER_BANNER_LOADED')) {
            define('THEMEATELIER_OFFER_BANNER_LOADED', true);
            ThemeAtelier_Offer_Banner::instance();
        }

        // Building the schema is what evaluates every `__()` in
        // src/Admin/Views/*.php, so the text domain has to be registered first —
        // Darkify::load_textdomain() runs on this same hook at priority 1.
        add_action('after_setup_theme', array($this, 'init_components'));
        add_filter('autoptimize_filter_js_exclude', array($this, 'darkify_exclude_js_from_cache_plugins'));

        /*
         * "No-conflict mode" compatibility.
         *
         * Fluent Forms, FluentCRM, Fluent Booking, FluentSMTP, WP Social Ninja
         * and friends dequeue *every* script served from wp-content/plugins on
         * their own admin screens (Fluent Forms: app/Hooks/actions.php, on
         * `wp_print_scripts` priority 1). That runs after `admin_enqueue_scripts`,
         * so it strips Darkify's engine while the admin-bar node — printed by
         * `admin_bar_menu` and untouched by the dequeue — keeps its inline
         * onclick, producing "darkify_switch_trigger is not defined" and a dead
         * toggle on e.g. admin.php?page=fluent_forms.
         *
         * Two layers: the published allow-list filters where a plugin offers one,
         * and a generic re-enqueue after their sweep for the ones that don't.
         */
        add_filter('fluentform/exclude_js_slugs_from_dequeue', array($this, 'darkify_no_conflict_allow_slugs'));
        add_filter('fluent_crm_asset_listed_slugs', array($this, 'darkify_no_conflict_allow_slugs'));
        add_filter('fluent_booking/asset_listed_slugs', array($this, 'darkify_no_conflict_allow_slugs'));
        add_action('wp_print_scripts', array($this, 'darkify_restore_no_conflict_scripts'), 100);
        add_action('wp_print_styles', array($this, 'darkify_restore_no_conflict_styles'), 100);

        add_action('admin_init', [$this, 'load_classic_editor_scripts']);
        add_filter('admin_footer_text', array($this, 'darkify_admin_footer'), 1, 2);
        $active_plugins = get_option('active_plugins');
        foreach ($active_plugins as $active_plugin) {
            $_temp = strpos($active_plugin, 'darkify.php');
            if (false != $_temp) {
                add_filter('plugin_action_links_' . DARKIFY_BASENAME, array($this, 'add_plugin_action_links'), 10, 2);
                add_filter('plugin_row_meta', array($this, 'after_darkify_row_meta'), 10, 4);
            }
        }
    }

    public function init_components()
    {
        DarkifyOptions::options('darkify');
        $this->seed_default_options();
    }

    /**
     * Write the schema's defaults into the `darkify` option on a fresh install.
     *
     * Nothing used to create that option until the first save in the admin. Up to
     * that point the two halves of the plugin disagreed: the React admin resolved
     * each field from the schema (so the Switch Toggler showed Orbit, the declared
     * default), while the frontend read the raw option — found nothing — and fell
     * back to the literal in its own template, rendering the Classic switcher. Same
     * class of mismatch for every other field whose template fallback differs from
     * its declared default.
     *
     * Seeding makes the stored values the single source both sides read, so a site
     * ships with exactly what the admin displays before anyone touches Settings.
     *
     * Runs on `init` (every request, admin and frontend), right after
     * the config classes have registered their sections, and is a no-op once the
     * option exists — so it never overwrites a user's saved settings, and an
     * upgrade from an older version is left alone.
     */
    private function seed_default_options()
    {
        $existing = get_option('darkify');
        if (is_array($existing) && ! empty($existing)) {
            return;
        }

        $defaults = SchemaDefaults::for_option('darkify');
        if (empty($defaults)) {
            return;
        }

        // `update_option` rather than `add_option`: an install that already holds
        // an empty/corrupt value (a stray '' or false) gets seeded too.
        update_option('darkify', $defaults);
    }

    /**
     * Whether the current screen is one of Darkify's own React SPA screens
     * (Settings / Get Help) — the only place the Admin Panel Dark Mode option can
     * be toggled, and therefore the only place that needs the engine loaded up
     * front so the admin-bar icon can react instantly.
     */
    public function is_darkify_spa_page(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
        $page = isset($_GET['page']) ? \sanitize_key(\wp_unslash($_GET['page'])) : '';
        return \in_array($page, Assets::SPA_SLUGS, true);
    }

    public function darkify_exclude_js_from_cache_plugins($excludes)
    {
        // Dark Reader proxies document.styleSheets and injects its own <style>
        // elements; an optimizer that concatenates or defers it changes when it
        // sees the page's sheets, which shows up as a half-darkened admin.
        $excludes .= ',client_main.js,client_main.min.js'
            . ',darkreader.js,darkreader.min.js'
            . ',admin_darkreader.js,admin_darkreader.min.js';
        return $excludes;
    }

    public function enqueue_styles()
    {
        $options = get_option('darkify');
        $enable_admin_panel_dark_mode = isset($options["enable_admin_panel_dark_mode"]) ? $options["enable_admin_panel_dark_mode"] : false;
        // Block Editor Dark Mode darkens the editor independently of Admin Panel
        // Dark Mode, so the stylesheet must load there too.
        $block_editor_dark_mode = isset($options['block_editor_dark_mode']) ? $options['block_editor_dark_mode'] : false;
        $screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
        $is_block_editor = $screen && \method_exists($screen, 'is_block_editor') && $screen->is_block_editor();
        $allow_admin_dark = $enable_admin_panel_dark_mode || ($is_block_editor && $block_editor_dark_mode);
        // Darkify's own screens always get the stylesheet so the admin-bar icon
        // can be revealed the moment the option is switched on. It only styles
        // `.darkify_*` classes, so it is inert until the engine adds them.
        if (($allow_admin_dark || $this->is_darkify_spa_page()) && $this->darkify_is_dark_mode_allowed()) {
            if (!wp_style_is('darkify-admin-switch', 'enqueued')) {
                wp_enqueue_style('darkify-admin-switch', DARKIFY_DIR_URL . 'src/assets/css/client_main' . $this->min . '.css', array(), $this->darkify_asset_version('src/assets/css/client_main' . $this->min . '.css'), 'all');
            }
        }
        // Review notice CSS
        wp_enqueue_style('darkify-review-notice', DARKIFY_DIR_URL . 'src/Admin/ReviewNotice/assets/css/review-notice' . $this->min . '.css', array(), DARKIFY_VERSION, 'all');
    }

    public function enqueue_scripts($hook)
    {
        $options = get_option('darkify');
        $enable_admin_panel_dark_mode = isset($options["enable_admin_panel_dark_mode"]) ? $options["enable_admin_panel_dark_mode"] : false;
        $is_spa = $this->is_darkify_spa_page();
        if (! $this->darkify_is_dark_mode_allowed()) {
            return;
        }

        // Mirror the `current_screen` engine-load rule exactly, so the engine is
        // only enqueued where its inline config (header_script.php) is ALSO
        // printed. Block Editor Dark Mode is independent of Admin Panel Dark
        // Mode: the block editor loads the engine when Block Editor Dark Mode is
        // on (regardless of Admin Panel Dark Mode); every other admin screen
        // keys on Admin Panel Dark Mode. (A SPA screen always gets the config.)
        if (! $is_spa) {
            $screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
            $is_block_editor = $screen && \method_exists($screen, 'is_block_editor') && $screen->is_block_editor();
            $block_editor_dark_mode = isset($options['block_editor_dark_mode']) ? $options['block_editor_dark_mode'] : false;
            $allow = $is_block_editor ? (bool) $block_editor_dark_mode : (bool) $enable_admin_panel_dark_mode;
            if (! $allow) {
                return;
            }
        }

        /*
         * wp-admin runs the Dark Reader engine, NOT the per-element classifier in
         * client_main.js. That engine walks every element and reads
         * getComputedStyle on each, then re-walks on DOM mutation; wp-admin (the
         * block editor especially) mutates continuously, so it never settles and
         * the editor locks up. Dark Reader works per stylesheet instead, so the
         * cost does not scale with element churn at all.
         *
         * The frontend is untouched and still runs client_main.js.
         * See src/assets/js/admin_darkreader.js for the full rationale.
         */
        $darkify_lib_rel    = 'src/assets/js/vendor/darkreader.min.js';
        $darkify_engine_rel = 'src/assets/js/admin_darkreader' . $this->min . '.js';
        $darkify_lib_url    = DARKIFY_DIR_URL . $darkify_lib_rel;

        /*
         * Both in <head> (in_footer = false), deliberately. Dark Reader does not
         * need a parsed body to start, and enabling it before first paint is what
         * keeps the admin from flashing light and then darkening.
         */
        wp_enqueue_script(
            'darkify-darkreader',
            $darkify_lib_url,
            array(),
            $this->darkify_asset_version($darkify_lib_rel),
            false
        );

        /*
         * The block editor canvas and the classic editor's TinyMCE body are
         * separate documents, and Dark Reader binds the realm it was loaded into.
         * The engine injects its own copy into each same-origin frame, so it
         * needs a URL it can point a <script> at from inside that frame.
         *
         * Darkify's own React settings screens are excluded from Dark Reader:
         * they ship a full dark theme of their own (`.dark` in
         * darkify-react/src/index.css) which also covers the surrounding wp-admin
         * chrome, so darkening them again produces a double-dark UI — and because
         * Dark Reader 4.9 cannot parse the `oklch()` values those tokens use, the
         * unresolved properties settle on `--destructive` and paint card borders
         * and toggles red. The engine still loads there: it owns the toggle the
         * admin-bar icon calls, and the SPA mirrors the class onto `.dark` itself.
         */
        wp_add_inline_script(
            'darkify-darkreader',
            'window.darkifyDarkReaderSrc = ' . wp_json_encode($darkify_lib_url) . ';'
            . 'window.darkifyAdminSelfThemed = ' . ($is_spa ? 'true' : 'false') . ';',
            'before'
        );

        wp_enqueue_script(
            'darkify-admin-engine',
            DARKIFY_DIR_URL . $darkify_engine_rel,
            array('darkify-darkreader'),
            $this->darkify_asset_version($darkify_engine_rel),
            false
        );

        $this->engine_enqueued = true;
    }

    /**
     * Add Darkify to a no-conflict allow-list.
     *
     * Both filter shapes this is attached to pass an array of substrings matched
     * against the script `src`, so the plugin folder name is the right token.
     *
     * @param array $slugs Allowed slugs.
     *
     * @return array
     */
    public function darkify_no_conflict_allow_slugs($slugs)
    {
        if (! is_array($slugs)) {
            return $slugs;
        }

        $slugs[] = 'darkify';

        return array_unique($slugs);
    }

    /**
     * Put the admin engine back after a no-conflict sweep dropped it.
     *
     * Runs on `wp_print_scripts` at 100 — after the sweeps, which all hook it at
     * 1 — and before `do_items()`, so a re-enqueue here still prints. Gated on
     * `$engine_enqueued`, so it never resurrects a script Darkify itself chose
     * not to load (or dequeued in admin_dequeue_for_specefic_pages).
     */
    public function darkify_restore_no_conflict_scripts()
    {
        if (! $this->engine_enqueued || ! is_admin()) {
            return;
        }

        foreach (array('darkify-darkreader', 'darkify-admin-engine') as $handle) {
            if (wp_script_is($handle, 'registered') && ! wp_script_is($handle, 'enqueued')) {
                wp_enqueue_script($handle);
            }
        }
    }

    /**
     * Same sweep, styles side: the engine adds `.darkify_dark_mode_enabled` and
     * the switch classes, and client_main.css is what they mean. Losing only the
     * stylesheet leaves a half-styled admin bar icon.
     */
    public function darkify_restore_no_conflict_styles()
    {
        if (! $this->engine_enqueued || ! is_admin()) {
            return;
        }

        if (wp_style_is('darkify-admin-switch', 'registered') && ! wp_style_is('darkify-admin-switch', 'enqueued')) {
            wp_enqueue_style('darkify-admin-switch');
        }
    }

    /**
     * Cache-busting version for a bundled asset: the plugin version plus the
     * file's mtime, so rebuilding client_main.js/.css (which does NOT bump the
     * plugin version) still changes the URL and browsers fetch the new file
     * instead of serving a stale one. Mirrors Admin\Assets::asset_version().
     */
    public function darkify_asset_version(string $relative_path): string
    {
        $abs = DARKIFY_PATH . $relative_path;
        $mtime = \is_readable($abs) ? \filemtime($abs) : false;
        return $mtime ? DARKIFY_VERSION . '.' . $mtime : DARKIFY_VERSION;
    }

    /*
    Dequeue Darkify scripts and styles on specific admin pages (e.g., MainWP pages)
    */

    public function admin_dequeue_for_specefic_pages()
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $dequeue_pages = array(
            'mainwp_tab',
            'managesites',
            'ManageClients',
            'CostSummary',
            'InsightsOverview',
            'Extensions',
            'RESTAPI',
            'mainwp-setup',
            'ThemesManage',
            'PluginsManage',
            'InsightsManage',
            'ManageGroups',
            'UpdatesManage',
            'PluginsInstall',
            'PluginsAutoUpdate',
            'PluginsIgnore',
            'PluginsAbandoned',
            'PluginsIgnoredAbandoned',
            'ThemesInstall',
            'ThemesAutoUpdate',
            'ThemesIgnore',
            'ThemesAbandoned',
            'ThemesIgnoredAbandoned',
            'UserBulkManage',
            'UserBulkAdd',
            'BulkImportUsers',
            'UpdateAdminPasswords',
            'PostBulkManage',
            'Extensions-Mainwp-Backups',
            'Extensions-Mainwp-Security',
            'Extensions-Mainwp-Analytics',
            'Mainwp-Monitoring',
            'Extensions-Mainwp-Agency',
            'Extensions-Mainwp-Administrative',
            'Extensions-Mainwp-Development',
            'Extensions-Mainwp-Performance',
            'ClientAddNew',
            'ClientImport',
            'ManageCostTracker',
            'CostTrackerAdd',
            'AddApiKeys',
        );


        if (in_array($page, $dequeue_pages, true)) {
            wp_deregister_style('darkify-admin-switch');
            wp_dequeue_script('darkify-admin-client-main');
            wp_dequeue_script('darkify-admin-engine');
            wp_dequeue_script('darkify-darkreader');
            // Keep darkify_restore_no_conflict_scripts() from undoing this.
            $this->engine_enqueued = false;
        }
    }

    /**
     * Replace wp-admin's footer credit with Darkify's review request, on
     * Darkify's own screens only.
     *
     * Screens are matched by page slug (`is_darkify_spa_page()`) rather than by
     * a hardcoded screen id, so both the Settings and Get Help SPA screens match
     * and the check keeps working if the menu is ever retitled. The slug list is
     * the same source of truth the assets already load from.
     *
     * @param string $text Existing footer text.
     *
     * @return string
     */
    public function darkify_admin_footer($text)
    {
        if (! $this->is_darkify_spa_page()) {
            return $text;
        }

        return sprintf(
            /* translators: 1: start strong tag, 2: close strong tag. 3: start link 4: close link */
            __('<i>Enjoying %1$sDarkify?%2$s Please rate us %3$sWordPress.org%4$s. Your positive feedback will help us grow more. Thank you! 😊</i>', 'darkify'),
            '<strong>',
            '</strong>',
            '<span class="greet-footer-text-star">★★★★★</span> <a href="https://wordpress.org/support/plugin/darkify/reviews/#new-post" target="_blank" rel="noopener noreferrer">',
            '</a>'
        );
    }

    // Plugin settings in plugin list
    public function add_plugin_action_links(array $links)
    {
        $new_links = array(
            sprintf('<a href="' . esc_url(admin_url('admin.php?page=darkify')) . '">' . esc_html__('Settings', 'darkify') . '</a>'),
            sprintf('<a target="_blank" href="https://wordpress.org/support/plugin/darkify/">' . esc_html__('Support', 'darkify') . '</a>'),
        );

        $links[] = sprintf('<a style="font-weight: bold;color:#35b747" target="_blank" href="https://darkifywp.com/pricing/?utm_source=darkify_plugin&utm_medium=action_link&utm_campaign=regular">%s</a>', esc_html__('Go Pro!', 'darkify'));

        return array_merge($new_links, $links);
    }

    /**
     * Add plugin row meta link.
     *
     * @since 2.0
     *
     * @param array  $plugin_meta .
     * @param string $file .
     *
     * @return array
     */
    public function after_darkify_row_meta($plugin_meta, $file)
    {

        if (DARKIFY_BASENAME === $file) {
            $plugin_meta[] = '<a href="' . DARKIFY_DEMO_URL . '" target="_blank">' . __('Live Demo', 'darkify') . '</a>';
        }

        return $plugin_meta;
    }

    public function darkify_admin_bar_switch($wp_admin_bar)
    {
        if (! $this->darkify_is_dark_mode_allowed()) {
            return;
        }

        /*
         * The block editor already carries its own toggle in the top toolbar
         * (see Frontend/Templates/footer_script.php), firing the same
         * darkify_switch_trigger() this node would — two controls for one state,
         * side by side.
         *
         * The toolbar one is the keeper, not this one: Gutenberg hides the WP
         * admin bar in fullscreen mode, which is the default, so this node is
         * simply absent for most people editing a post. Dropping the toolbar
         * button instead would leave them no toggle at all.
         *
         * The classic editor is deliberately not included here. Its TinyMCE moon
         * toggles only the content frame, while this switch toggles the whole
         * admin — two different controls, so both belong on screen.
         */
        $screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
        if ($screen && \method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) {
            return;
        }

        $options = get_option('darkify');
        $enable_admin_panel_dark_mode = isset($options['enable_admin_panel_dark_mode']) ? $options['enable_admin_panel_dark_mode'] : false;

        // Everywhere but Darkify's own screens the node is only registered when
        // the option is on, so it is always visible. On the SPA screens it is
        // registered regardless and starts hidden while the option is off — the
        // React admin then just flips this class (see WpAdminBarBridge), which is
        // what makes the icon appear/disappear without a reload.
        $classes = 'darkify_admin_bar_switch_container';
        if (! $enable_admin_panel_dark_mode) {
            $classes .= ' darkify_admin_bar_hidden';
        }

        $wp_admin_bar->add_node(array(
            'parent' => 'top-secondary',
            'id' => 'darkify_admin_bar_switch_container',
            'meta' => array(
                'class' => $classes,
                // Guarded call: a plugin's "no-conflict mode" can still strip the
                // engine on its own screens (see the compatibility hooks in the
                // constructor). A missing function should be an inert click, not
                // an uncaught ReferenceError in every visitor's console.
                // No quotes in the expression: the admin bar escapes `meta`
                // attributes, and the escaped quotes come back as literal
                // entities inside the handler ("Invalid or unexpected token").
                'onclick' => 'window.darkify_switch_trigger&&window.darkify_switch_trigger()',
            ),
        ));
    }

    public function darkify_is_dark_mode_allowed()
    {

        $options = get_option('darkify');
        $options = is_array($options) ? $options : array();
        // Guarded read: `disallowed_admin_pages` has no schema default, so the
        // React settings save doesn't persist it until the field is used. Reading
        // it unguarded would emit an "Undefined array key" warning on every
        // dark-mode hook (see the Pro plugin's Admin.php for the full story).
        $disallowed_admin_pages = isset($options['disallowed_admin_pages']) ? $options['disallowed_admin_pages'] : '';
        /* Disable on Disallowed Admin Plugins */
        if ($this->utils->isRestrictedByDisallowedAdminPages($disallowed_admin_pages)) {
            return False;
        }
        return True;
    }

    public function darkify_admin_header_script()
    {
        if ($this->darkify_is_dark_mode_allowed()) {
            include_once DarkifyUtils::darkify_locate_template('header_script.php');
        }
    }

    public function darkify_admin_footer_script()
    {
        if ($this->darkify_is_dark_mode_allowed()) {
            include_once DarkifyUtils::darkify_locate_template('footer_script.php');
        }
    }

    /**
     * Load classic editor scripts.
     *
     * @since 5.0.0
     */
    public function load_classic_editor_scripts()
    {
        $options = get_option('darkify');
        $classic_editor_dark_mode = isset($options['classic_editor_dark_mode']) ? $options['classic_editor_dark_mode'] : '';
        if (! current_user_can('edit_posts') && ! current_user_can('edit_pages')) {
            return;
        }
        if (get_user_option('rich_editing') !== 'true') {
            return;
        }

        // Bail if admin_classic_editor is not enabled.
        if (! wp_validate_boolean($classic_editor_dark_mode)) {
            return;
        }

        add_filter('mce_external_plugins', function ($plugins) {
            // Honour the same debug switch as every other bundled asset; gulp
            // builds the .min beside it and this was the one enqueue still
            // pointing at the readable copy in production.
            $plugins['darkify_button'] = plugins_url('src/assets/js/admin-classic-editor' . $this->min . '.js', DARKIFY_FILE);
            return $plugins;
        });

        add_filter('mce_buttons', function ($buttons) {
            $buttons[] = 'darkify_button';
            return $buttons;
        });
    }
}
