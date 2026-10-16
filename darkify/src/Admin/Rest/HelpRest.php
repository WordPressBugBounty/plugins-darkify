<?php

/**
 * Help-page REST controller for the React admin SPA.
 *
 * Backs the Get Help page's "Recommended" tab: lists ThemeAtelier's free
 * plugins from the WordPress.org API (cached in a transient) with live
 * install/active state, and installs or activates them in one click.
 *
 * Ports the recommended-plugins behaviour of the retired
 * `src/Admin/HelpPage/Help.php` onto this plugin's AbstractRestController
 * pattern (mirroring the Chat Help plugin's HelpRest), so the classic Help
 * page's plugin directory keeps working inside the new React admin. The
 * transient stores the full author list and the visibility filter runs at
 * response time, so the cache stays valid regardless of which plugins are
 * hidden.
 *
 * @package    darkify
 * @subpackage darkify/src/Admin/Rest
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\Rest;

use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    die;
}

class HelpRest extends AbstractRestController
{
    /**
     * Slug => main plugin file for ThemeAtelier plugins whose main file name
     * differs from the slug. Used to detect installed/active state and to
     * activate the right file.
     *
     * @var array<string,string>
     */
    const PLUGIN_FILES = [
        'greet-bubble'           => 'greet-bubble.php',
        'domain-for-sale'        => 'domain-for-sale.php',
        'ask-faq'                => 'ask-faq.php',
        'attentive-security'     => 'attentive-security.php',
        'better-chat-support'    => 'messenger-chat-support.php',
        'bizreview'              => 'bizreview.php',
        'booklet-booking-system' => 'booklet.php',
        'skype-chat'             => 'skype-chat.php',
        'chat-help'              => 'chat-whatsapp.php',
        'chat-telegram'          => 'telegram-chat.php',
        'chat-viber'             => 'chat-viber-lite.php',
        'click-to-dial'          => 'click-to-dial.php',
        'click-to-mail'          => 'click-to-mail.php',
        'darkify'                => 'darkify.php',
        'eventful'               => 'eventful.php',
        'eventful-for-elementor' => 'eventful-for-elementor.php',
        'postify'                => 'postify.php',
        'idonate'                => 'idonate.php',
    ];

    /**
     * Slugs hidden from the Recommended tab — the same set the classic Darkify
     * Help page hid (Help.php `$not_show_plugin_list`), which hides Darkify
     * itself and leaves Chat Help, Eventful, Domain For Sale, Better Chat
     * Support, Greet Bubble and Eventful for Elementor showing.
     *
     * @var array<int,string>
     */
    const HIDDEN_PLUGINS = [
        'darkify',
        'idonate',
        'bizreview',
        'chat-viber',
        'chat-telegram',
        'click-to-dial',
        'chat-skype',
        'skype-chat',
        'click-to-mail',
        'ask-faq',
        'attentive-security',
        'booklet-booking-system',
        'postify',
    ];

    /** Transient key for the WordPress.org author-plugins response. */
    const TRANSIENT = 'darkify_ta_plugins';

    public function register_routes(): void
    {
        \register_rest_route(self::NS, '/help/recommended-plugins', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_recommended_plugins'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        \register_rest_route(self::NS, '/help/install-plugin', [
            'methods'             => 'POST',
            'callback'            => [$this, 'install_plugin'],
            'permission_callback' => static function () {
                return \current_user_can('install_plugins');
            },
        ]);

        \register_rest_route(self::NS, '/help/activate-plugin', [
            'methods'             => 'POST',
            'callback'            => [$this, 'activate_plugin_handler'],
            'permission_callback' => static function () {
                return \current_user_can('activate_plugins');
            },
        ]);
    }

    /**
     * POST /help/install-plugin — install a wp.org plugin by slug.
     */
    public function install_plugin(WP_REST_Request $request): WP_REST_Response
    {
        $slug = \sanitize_key($request->get_param('slug'));
        if (! $slug) {
            return new WP_REST_Response(['success' => false, 'message' => \__('Invalid plugin slug.', 'darkify')], 400);
        }

        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

        $api = \plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
        if (\is_wp_error($api)) {
            return new WP_REST_Response(['success' => false, 'message' => $api->get_error_message()], 400);
        }

        $skin     = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result   = $upgrader->install($api->download_link);

        if (\is_wp_error($result)) {
            return new WP_REST_Response(['success' => false, 'message' => $result->get_error_message()], 400);
        }

        if (false === $result) {
            return new WP_REST_Response(['success' => false, 'message' => $skin->get_error_messages()], 400);
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * POST /help/activate-plugin — activate an installed plugin by slug.
     */
    public function activate_plugin_handler(WP_REST_Request $request): WP_REST_Response
    {
        $slug = \sanitize_key($request->get_param('slug'));
        if (! $slug) {
            return new WP_REST_Response(['success' => false, 'message' => \__('Invalid plugin slug.', 'darkify')], 400);
        }

        $file        = self::PLUGIN_FILES[$slug] ?? $slug . '.php';
        $plugin_file = $slug . '/' . $file;

        if (! \file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
            return new WP_REST_Response(['success' => false, 'message' => \__('Plugin file not found.', 'darkify')], 400);
        }

        $result = \activate_plugin($plugin_file);
        if (\is_wp_error($result)) {
            return new WP_REST_Response(['success' => false, 'message' => $result->get_error_message()], 400);
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * GET /help/recommended-plugins — ThemeAtelier's wp.org plugins with live
     * install/active state, sorted by active installs.
     */
    public function get_recommended_plugins(): WP_REST_Response
    {
        $plugins_arr = \get_transient(self::TRANSIENT);
        if (false === $plugins_arr) {
            $args    = (object) [
                'author'   => 'themeatelier',
                'per_page' => '120',
                'page'     => '1',
                'fields'   => [
                    'slug',
                    'name',
                    'version',
                    'downloaded',
                    'active_installs',
                    'last_updated',
                    'rating',
                    'num_ratings',
                    'short_description',
                    'author',
                ],
            ];
            $request = [
                'action'  => 'query_plugins',
                'timeout' => 30,
                'request' => \serialize($args),
            ];
            $response = \wp_remote_post('http://api.wordpress.org/plugins/info/1.0/', ['body' => $request]);

            if (! \is_wp_error($response)) {
                $plugins_arr = [];
                $plugins     = \unserialize($response['body']);

                if (isset($plugins->plugins) && (\count($plugins->plugins) > 0)) {
                    foreach ($plugins->plugins as $pl) {
                        $plugins_arr[] = [
                            'slug'              => $pl->slug,
                            'name'              => \html_entity_decode($pl->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                            'version'           => $pl->version,
                            'downloaded'        => $pl->downloaded,
                            'active_installs'   => $pl->active_installs,
                            'last_updated'      => \strtotime($pl->last_updated),
                            'rating'            => $pl->rating,
                            'num_ratings'       => $pl->num_ratings,
                            'short_description' => \html_entity_decode($pl->short_description, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        ];
                    }
                }

                \set_transient(self::TRANSIENT, $plugins_arr, 24 * HOUR_IN_SECONDS);
            }
        }

        if (! \is_array($plugins_arr) || \count($plugins_arr) === 0) {
            return new WP_REST_Response([], 200);
        }

        \array_multisort(\array_column($plugins_arr, 'active_installs'), SORT_DESC, $plugins_arr);

        if (! \function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $result = [];
        foreach ($plugins_arr as $plugin) {
            $plugin_slug = $plugin['slug'];
            if (\in_array($plugin_slug, self::HIDDEN_PLUGINS, true)) {
                continue;
            }

            $plugin_file = self::PLUGIN_FILES[$plugin_slug] ?? $plugin_slug . '.php';

            // A few wp.org icons aren't PNGs.
            $image_type = 'png';
            switch ($plugin_slug) {
                case 'postify':
                    $image_type = 'jpg';
                    break;
                case 'darkify':
                    $image_type = 'gif?rev=3301202';
                    break;
            }

            $is_installed = \file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug . '/' . $plugin_file);
            $is_active    = $is_installed && \is_plugin_active($plugin_slug . '/' . $plugin_file);

            $result[] = [
                'slug'              => $plugin_slug,
                'name'              => \html_entity_decode($plugin['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'version'           => $plugin['version'],
                'active_installs'   => $plugin['active_installs'],
                'rating'            => $plugin['rating'],
                'num_ratings'       => $plugin['num_ratings'],
                'short_description' => \html_entity_decode($plugin['short_description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'icon'              => 'https://ps.w.org/' . $plugin_slug . '/assets/icon-256x256.' . $image_type,
                'is_installed'      => $is_installed,
                'is_active'         => $is_active,
                'install_url'       => \wp_nonce_url(\self_admin_url('update.php?action=install-plugin&plugin=' . $plugin_slug), 'install-plugin_' . $plugin_slug),
                'activate_url'      => $is_installed ? \wp_nonce_url(\admin_url('plugins.php?action=activate&plugin=' . $plugin_slug . '/' . $plugin_file), 'activate-plugin_' . $plugin_slug . '/' . $plugin_file) : '',
                'details_url'       => \network_admin_url('plugin-install.php?tab=plugin-information&plugin=' . $plugin_slug . '&TB_iframe=true&width=600&height=550'),
            ];
        }

        return new WP_REST_Response($result, 200);
    }
}
