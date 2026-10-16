<?php

/**
 * The Darkify admin menu.
 *
 * Registers the top-level menu page that hosts the React SPA, plus a "Get Help"
 * submenu entry of its own. Both render the same SPA root node; which page the
 * app opens on is derived from the `?page=` slug (see resolveInitialRoute in
 * darkify-react/src/main.jsx), so "Get Help" lands directly on the dedicated Help
 * page while the main entry lands on the settings sections.
 *
 * The menu title, slug, icon and position all come from the args the config class
 * already declared via `DarkifyOptions::options()`
 * (src/Admin/Views/DarkifyOptions.php), so the menu keeps its existing slug
 * (`darkify`), icon and place in the sidebar — an existing bookmark to
 * `?page=darkify` still lands on the settings screen, it is simply a React one now.
 *
 * @package    darkify
 * @subpackage darkify/src/Admin
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin;

use ThemeAtelier\Darkify\Admin\Schema\SchemaRegistry;

if (! defined('ABSPATH')) {
    die;
}

class Menu
{
    /**
     * The option key whose page args drive the menu.
     */
    const OPTION_KEY = 'darkify';

    /**
     * The main SPA page slug (unchanged from the old options screen).
     */
    const PAGE_SLUG = 'darkify';

    /**
     * The Help page's own slug. It is a separate WordPress menu entry — not a tab,
     * modal or panel — so Help is reachable in one click from anywhere in wp-admin.
     */
    const HELP_SLUG = 'darkify-help';

    public function __construct()
    {
        \add_action('admin_menu', [$this, 'register']);
        \add_action('admin_head', [$this, 'menu_icon_style']);
        \add_action('admin_footer', [$this, 'upgrade_link_new_tab']);
        // Old bookmarks / links to the retired "Get Help" screen are forwarded to
        // the SPA's own Help route (see redirect_legacy_help_page()).
        \add_action('admin_page_access_denied', [$this, 'redirect_legacy_help_page']);
    }

    /**
     * Register the top-level menu page, plus its submenu entries: an explicit
     * "Darkify" entry, "Get Help", and the free plugin's existing
     * "Upgrade To Pro! 👑" link.
     *
     * Every settings section is reached from the React admin's own header nav
     * instead of WP submenus, but Get Help gets its own submenu entry (matching
     * Pro's Menu.php) so it is reachable in one click from anywhere in wp-admin,
     * not only from inside the settings screen. The upgrade submenu is the same
     * external pricing link the free plugin has always registered — the
     * upgrade flow is deliberately unchanged by the React migration.
     */
    public function register(): void
    {
        $args = SchemaRegistry::options(self::OPTION_KEY);

        $menu_title = $args['menu_title'] ?? \esc_html__('Darkify', 'darkify');
        $capability = \apply_filters('darkify_ui_permission', $args['menu_capability'] ?? 'manage_options');
        $position   = $args['menu_position'] ?? null;
        $icon       = $args['menu_icon'] ?? 'dashicons-lightbulb';

        \add_menu_page(
            $menu_title,
            $menu_title,
            $capability,
            self::PAGE_SLUG,
            [$this, 'render'],
            $icon,
            $position
        );

        // Registered explicitly with the parent's own slug so it reads "Darkify"
        // instead of WordPress auto-duplicating the top-level menu title as the
        // first submenu item — that auto-duplication only happens when nothing
        // else claims the parent slug as a submenu (see Pro's Menu.php, same fix).
        \add_submenu_page(
            self::PAGE_SLUG,
            $menu_title,
            \esc_html__('Darkify', 'darkify'),
            $capability,
            self::PAGE_SLUG,
            [$this, 'render']
        );

        // Get Help is no longer a WP submenu — it lives in the SPA rail's footer
        // (see AppSidebar). The retired `?page=darkify-help` still forwards to the
        // SPA's /help route (see below).

        \add_submenu_page(
            self::PAGE_SLUG,
            \__('Upgrade To Pro! 👑', 'darkify'),
            \sprintf('<span style="color: #35b747;font-weight:600;" class="darkify-get-pro-text">%s</span>', \__('Upgrade To Pro! 👑', 'darkify')),
            'manage_options',
            'https://darkifywp.com/pricing/?utm_source=darkify_plugin&utm_medium=submenu_page&utm_campaign=regular'
        );
    }

    /**
     * Open the "Upgrade To Pro!" submenu link in a new tab.
     *
     * That submenu is registered with an external URL as its slug, so WordPress
     * renders it as an ordinary anchor into wp-admin's menu. Leaving wp-admin
     * entirely is not what a menu click should do — someone half-way through
     * configuring the plugin loses the screen they were on, and the back button
     * is their only way back.
     *
     * There is no filter for the attributes of that anchor: wp-admin builds the
     * markup itself in wp-admin/menu-header.php, and the only thing under our
     * control is the title string, which is escaped into the link text. So the
     * attributes are set client-side instead, keyed off the class the title
     * already carries.
     *
     * `rel` is not optional here. A `target="_blank"` link hands the opened page
     * a `window.opener` reference to this one; `noopener` severs it, and
     * `noreferrer` keeps the admin URL (which can carry screen and query
     * context) out of the referrer header sent to the marketing site.
     */
    public function upgrade_link_new_tab(): void
    {
        ?>
        <script>
            (function () {
                var label = document.querySelector('#adminmenu .darkify-get-pro-text');
                var link = label && label.closest('a');
                if (!link) {
                    return;
                }
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            })();
        </script>
        <?php
    }

    /**
     * Forward the retired `?page=darkify-help` screen to the SPA's Help route.
     *
     * "Get Help" used to be its own menu page; it is now a route inside the one
     * SPA page. Without this, an existing bookmark (or any third-party link) to
     * the old slug would hit WordPress's "you are not allowed to access this
     * page" wall, since the slug is no longer a registered page.
     *
     * Hooked on `admin_page_access_denied` — which wp-admin/includes/menu.php
     * fires immediately before that `wp_die( …, 403 )` — because that check runs
     * while menu.php is being required, i.e. BEFORE `admin_init`; an admin_init
     * hook would never get the chance to redirect. The slug guard keeps this
     * scoped to our own retired page, so every other denied page still dies
     * normally.
     */
    public function redirect_legacy_help_page(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check, no state is changed.
        $page = isset($_GET['page']) ? \sanitize_key(\wp_unslash($_GET['page'])) : '';
        if ($page !== self::HELP_SLUG) {
            return;
        }

        \wp_safe_redirect(\admin_url('admin.php?page=' . self::PAGE_SLUG . '#/help'));
        exit;
    }

    /**
     * Make the menu icon take its colour from the menu, like every other icon.
     *
     * WordPress renders a `data:` menu icon as a CSS `background-image` on
     * `div.wp-menu-image.svg`, and ours has `fill="white"` baked into the SVG. A
     * background-image cannot inherit `color`, so while WordPress's own icons are
     * dashicons — font glyphs that follow the menu's text colour and dim to 0.6
     * opacity — ours painted flat white and read as brighter than everything
     * around it. Dark mode made it obvious, but it was never matching; the dark
     * sidebar just hid it.
     *
     * Switching from background-image to a `mask` inverts the relationship: the
     * SVG becomes a stencil and `background-color: currentColor` supplies the
     * paint, so the icon follows the menu's colour exactly as a dashicon does —
     * under any admin colour scheme, under any Darkify palette, with no colour
     * hardcoded here.
     *
     * Printed from the same schema value `register()` passes to add_menu_page(),
     * so there is only ever one copy of the SVG to keep correct.
     */
    public function menu_icon_style(): void
    {
        $args = SchemaRegistry::options(self::OPTION_KEY);
        $icon = $args['menu_icon'] ?? '';

        /*
         * Dashicon slugs and image files are already coloured (or already correct)
         * by WordPress; only a base64 inline SVG needs this treatment.
         *
         * Matched against a strict pattern rather than escaped with esc_url():
         * `data:` is not in wp_allowed_protocols(), so esc_url() strips the whole
         * value and the icon disappears. The pattern is what makes it safe to
         * print raw — base64 cannot contain the `<` that would be needed to close
         * the surrounding <style> element, and nothing outside the alphabet gets
         * through.
         */
        if (! \is_string($icon) || ! \preg_match('#^data:image/svg\+xml;base64,[A-Za-z0-9+/]+={0,2}$#', $icon)) {
            return;
        }

        $selector = '#adminmenu .toplevel_page_' . self::PAGE_SLUG . ' div.wp-menu-image.svg';
        $url      = "url('" . $icon . "')";

        $css = $selector . '{'
            . 'background-image:none!important;'
            . '-webkit-mask:' . $url . ' no-repeat center;'
            . 'mask:' . $url . ' no-repeat center;'
            . '-webkit-mask-size:20px auto;'
            . 'mask-size:20px auto;'
            . 'background-color:currentColor!important;'
            // Match the 0.6 dashicons rest at, and the full opacity they take on
            // hover and while current.
            . 'opacity:.6;'
            . '}'
            . '#adminmenu .toplevel_page_' . self::PAGE_SLUG . ':hover div.wp-menu-image.svg,'
            . '#adminmenu .toplevel_page_' . self::PAGE_SLUG . '.current div.wp-menu-image.svg,'
            . '#adminmenu .toplevel_page_' . self::PAGE_SLUG . '.wp-has-current-submenu div.wp-menu-image.svg'
            . '{opacity:1;}';

        echo '<style id="darkify-menu-icon">' . $css . '</style>';
    }

    /**
     * The page body: just the node React mounts on. Every menu entry renders this
     * same root — the SPA reads the `?page=` slug to decide which route to open.
     *
     * The `darkify_ignore` class opts the whole React admin out of Darkify's own
     * dark-mode engine. When "Admin Panel Dark Mode" is on, that engine runs on
     * every admin screen and force-rewrites element backgrounds/colors with
     * `!important` (classifying nodes as `darkify_style_bg` / `darkify_style_button`
     * etc. — see src/assets/css/client_main.css). On our settings screen that
     * stomped the app's own theme — most visibly the colour-preset swatches, whose
     * inline background was overwritten so every preview looked empty. The React
     * admin already themes itself via the `.dark` class, so the engine must leave
     * its subtree alone; `.darkify_ignore` / `.darkify_ignore *` is that engine's
     * built-in exclusion hook.
     *
     * `translate="no"` / `.notranslate` opts the subtree out of browser page
     * translation (Chrome, Edge). Those translators replace each text node with
     * a `<font>` wrapper; React later tries to remove the original node, hits
     * "Failed to execute 'removeChild' on 'Node'", and the whole app unmounts.
     * Because saving re-renders (spinner, then toast), the crash landed exactly
     * on Save and looked like settings failing to save. The panel ships real
     * translations instead — see languages/ — so nothing is lost by declining
     * the browser's.
     */
    public function render(): void
    {
        echo '<div id="darkify_react" class="darkify_ignore notranslate" translate="no"></div>';
    }
}
