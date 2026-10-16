<?php

/**
 * Centralized admin asset loader for the React SPA.
 *
 * Single owner of the React admin bundle (CSS + JS), the WordPress media library
 * and the `window.darkifyAdmin` runtime config the SPA reads. The whole admin —
 * every settings section, License and Help — is one SPA mounted on
 * `#darkify_react`, so this loads only on Darkify's own admin page.
 *
 * @package    darkify
 * @subpackage darkify/src/Admin
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin;

use ThemeAtelier\Darkify\Admin\Rest\AbstractRestController;

if (! defined('ABSPATH')) {
    die;
}

class Assets
{
    /**
     * The React admin bundle handle.
     */
    const HANDLE = 'darkify-admin';

    /**
     * The admin page slugs that host the SPA — the settings screen and the Help
     * screen, which is its own WordPress menu entry (see Admin\Menu). Both mount
     * the same bundle; only the route the app opens on differs.
     *
     * `darkify` is unchanged from the old options screen, so existing links and
     * bookmarks to `?page=darkify` still land here.
     */
    const SPA_SLUGS = [
        'darkify',
        'darkify-help',
    ];

    public function __construct()
    {
        \add_action('admin_enqueue_scripts', [$this, 'enqueue'], 100);
        \add_action('admin_head', [$this, 'hide_admin_notices']);
    }

    /**
     * Suppress every other plugin/theme/core admin notice on Darkify's own SPA
     * pages (Settings + Help), so the app reads as a clean, distraction-free
     * screen. Scoped strictly to `is_darkify_admin_page()` — every other wp-admin
     * screen keeps notices exactly as before.
     *
     * Hooked on `admin_head`, which fires after every plugin/theme has had its
     * chance to `add_action( 'admin_notices', … )` (typically done on `init` /
     * `admin_init` / their own `admin_menu` handlers, all of which run earlier)
     * but strictly before WordPress calls those hooks — `admin_notices` /
     * `all_admin_notices` fire later, while rendering `#wpbody-content`. Removing
     * *all* callbacks on those hooks (rather than filtering) is the standard
     * approach for a distraction-free admin screen; it only touches notices, not
     * the pages themselves.
     */
    public function hide_admin_notices(): void
    {
        if (! $this->is_darkify_admin_page()) {
            return;
        }
        \remove_all_actions('admin_notices');
        \remove_all_actions('all_admin_notices');
        \remove_all_actions('network_admin_notices');
    }

    /**
     * Whether the current admin screen is one of the Darkify SPA pages.
     */
    public function is_darkify_admin_page(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
        $page = isset($_GET['page']) ? \sanitize_key(\wp_unslash($_GET['page'])) : '';
        return \in_array($page, self::SPA_SLUGS, true);
    }

    /**
     * Enqueue the React bundle (CSS + JS) and print the SPA runtime config.
     */
    public function enqueue(): void
    {
        if (! $this->is_darkify_admin_page()) {
            return;
        }

        // Printed independently of the bundle handle so it is available both to
        // the built bundle and to the Vite dev server during development.
        \add_action('admin_print_scripts', function () {
            echo '<script>window.darkifyAdmin = ' . \wp_json_encode($this->config()) . ';</script>';
        });

        // Needed by the React upload/media fields.
        \wp_enqueue_media();

        // Needed by the Get Help page's Recommended tab: the "More Details"
        // links open plugin-install.php in a TB_iframe (WordPress thickbox).
        \add_thickbox();

        // No icon webfont is enqueued: every admin icon is now an inline lucide SVG
        // rendered by React (see darkify-react/src/lib/navIcons.js), matching Chat
        // Help Pro. The schema's `icon` values are still icofont class names — the
        // config files are shared with the free plugin and left untouched — but the
        // React side resolves icons from the node id and ignores them.

        $base_url = DARKIFY_DIR_URL . 'src/Admin/assets/js/';
        $base_dir = DARKIFY_PATH . 'src/Admin/assets/js/';

        // Styles in <head> so they apply before first paint. The bundle itself
        // loads in the footer; if the CSS were injected by that JS, the whole
        // admin (WP menu included) would repaint and visibly flicker.
        \wp_enqueue_style(
            self::HANDLE,
            $base_url . self::HANDLE . '.css',
            [],
            $this->asset_version($base_dir . self::HANDLE . '.css')
        );

        \wp_enqueue_script(
            self::HANDLE,
            $base_url . self::HANDLE . '.js',
            ['wp-i18n'],
            $this->asset_version($base_dir . self::HANDLE . '.js'),
            true
        );

        \wp_set_script_translations(self::HANDLE, 'darkify', DARKIFY_PATH . 'languages');
    }

    /**
     * Cache-busting version for a built asset.
     *
     * The plugin version alone is not enough during development: rebuilding the
     * bundle does not bump it, so the browser happily serves the previous
     * darkify-admin.js from cache and the new admin never appears. The file's
     * mtime changes on every build, so it always does.
     *
     * Released builds still carry the plugin version as well, so the URL changes
     * on update even if a filesystem copy preserves mtimes.
     *
     * @param string $path Absolute path to the built asset.
     */
    private function asset_version(string $path): string
    {
        $mtime = \is_readable($path) ? \filemtime($path) : false;
        return $mtime ? DARKIFY_VERSION . '.' . $mtime : DARKIFY_VERSION;
    }

    /**
     * The SPA runtime config global (window.darkifyAdmin).
     *
     * @return array<string,mixed>
     */
    private function config(): array
    {
        return [
            'restUrl'    => \esc_url_raw(\rest_url(AbstractRestController::NS)),
            'nonce'      => \wp_create_nonce('wp_rest'),
            'ajaxUrl'    => \admin_url('admin-ajax.php'),
            'dirUrl'     => DARKIFY_DIR_URL,
            'version'    => DARKIFY_VERSION,
            'adminUrl'   => \admin_url('admin.php?page=' . self::SPA_SLUGS[0]),
            'docsUrl'    => \defined('DARKIFY_DOCS_URL') ? DARKIFY_DOCS_URL : '',
            'demoUrl'    => \defined('DARKIFY_DEMO_URL') ? DARKIFY_DEMO_URL : '',
            'supportUrl' => 'https://wordpress.org/support/plugin/darkify/',
            // The free build surfaces upgrade CTAs (Pro-locked fields, License
            // page); one canonical pricing URL keeps the campaign tagging uniform.
            'upgradeUrl' => 'https://darkifywp.com/pricing/?utm_source=darkify_plugin&utm_medium=react_admin&utm_campaign=pro_lock',
            // Live preview: the site homepage, plus a nonce the frontend verifies
            // before swapping in the admin's unsaved settings (see PreviewRest).
            'homeUrl'      => \esc_url_raw(\home_url('/')),
            'previewNonce' => \wp_create_nonce(\ThemeAtelier\Darkify\Admin\Rest\PreviewRest::PREVIEW_NONCE_ACTION),
        ];
    }
}
