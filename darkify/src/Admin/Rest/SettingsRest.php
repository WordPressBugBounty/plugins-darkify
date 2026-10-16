<?php

/**
 * Settings REST controller for the React admin SPA.
 *
 * Exposes the normalized settings schema (read from the SchemaRegistry) plus the
 * current values for an option key, and persists changes back into that same
 * option the frontend already reads.
 *
 * Backward compatible by design. The field ids, defaults and the `darkify` option
 * key are the ones the config classes in src/Admin/Views/*.php already declared,
 * so existing settings load and save unchanged — no migration runs, and nothing
 * about the stored data model changes. The save also still fires the same
 * `darkify_darkify_save*` filter/actions the old framework did, so any add-on or
 * snippet hooked into them keeps working.
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

class SettingsRest extends AbstractRestController
{
    public function register_routes(): void
    {
        \register_rest_route(self::NS, '/settings/(?P<key>[A-Za-z0-9_\-]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_settings'],
                'permission_callback' => [$this, 'can_manage'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'save_settings'],
                'permission_callback' => [$this, 'can_manage'],
            ],
        ]);
    }

    /**
     * GET /settings/{key} — the normalized schema tree and the current values.
     */
    public function get_settings(WP_REST_Request $request): WP_REST_Response
    {
        $key = (string) $request->get_param('key');
        if (! $this->is_valid_settings_key($key)) {
            return new WP_REST_Response(['message' => \__('Unknown settings key.', 'darkify')], 404);
        }

        $sections = $this->get_registered_sections($key);
        // `apply_pro_flags` stamps `pro` / `pro_options` from the config's
        // long-standing lock markers so the React admin renders those fields as
        // locked previews (Chat Help's ProLock contract).
        $tree     = $this->apply_pro_flags($this->normalize_sections($sections));
        $defaults = $this->collect_defaults($sections);

        $saved = \get_option($key, []);
        $saved = \is_array($saved) ? $saved : [];

        return \rest_ensure_response([
            'key'    => $key,
            'schema' => $tree,
            'values' => $this->merge_defaults($defaults, $saved),
        ]);
    }

    /**
     * POST /settings/{key} — sanitize by field type and persist to the option.
     *
     * The submitted values are merged *over* the existing option rather than
     * replacing it. That matters for backward compatibility: any key a previous
     * version wrote but the current schema no longer declares (an old field, or
     * one owned by an add-on) survives the save instead of being dropped.
     */
    public function save_settings(WP_REST_Request $request): WP_REST_Response
    {
        $key = (string) $request->get_param('key');
        if (! $this->is_valid_settings_key($key)) {
            return new WP_REST_Response(['message' => \__('Unknown settings key.', 'darkify')], 404);
        }

        $incoming = $request->get_param('values');
        if (! \is_array($incoming)) {
            return new WP_REST_Response([
                'saved'   => false,
                'message' => \__('Invalid payload.', 'darkify'),
            ], 400);
        }

        $sections  = $this->get_registered_sections($key);
        $sanitized = $this->sanitize_values($incoming, $this->collect_field_types($sections));
        // Free plugin: locked fields/choices can never be persisted, no matter
        // what the request claims (the locked UI never submits them anyway).
        $sanitized = $this->strip_pro_keys($sanitized, $sections);

        $existing = \get_option($key, []);
        $existing = \is_array($existing) ? $existing : [];
        $data     = \array_merge($existing, $sanitized);

        /**
         * The same filter/actions the retired options framework fired around a
         * save, with the same names and argument order, so anything hooked into
         * them keeps working. The second argument used to be the framework
         * instance; it is now this controller — callbacks in the wild use the
         * `$data` array, which is unchanged.
         */
        $data = \apply_filters("darkify_{$key}_save", $data, $this);

        \do_action("darkify_{$key}_save_before", $data, $this);

        \update_option($key, $data);

        \do_action("darkify_{$key}_saved", $data, $this);
        \do_action("darkify_{$key}_save_after", $data, $this);

        return \rest_ensure_response([
            'saved'  => true,
            'key'    => $key,
            'values' => $data,
        ]);
    }
}
