<?php

/**
 * Schema default resolution.
 *
 * The default of every field lives in the config classes (src/Admin/Views/*.php)
 * and is read back out of the SchemaRegistry. Two callers need it:
 *
 * - the settings REST controller, which merges defaults under the saved values
 *   so the React admin renders a documented default for a field never saved;
 * - the install seeder (Admin::seed_default_options), which writes those same
 *   defaults into the `darkify` option the first time the plugin runs.
 *
 * The seeder is what keeps the two sides of the plugin agreeing on a fresh
 * install. Until the option row exists, the admin resolved a default from the
 * schema while the frontend fell back to whatever literal its template carried —
 * so the admin showed the Orbit switcher while the site rendered Classic. Seeding
 * makes the stored values the single source both read.
 *
 * @package    darkify
 * @subpackage darkify/src/Admin/Schema
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\Schema;

if (! defined('ABSPATH')) {
    die;
}

class SchemaDefaults
{
    /**
     * Config keys whose values are trusted markup rendered as raw HTML. They must
     * NOT be entity-decoded into text.
     */
    const HTML_LABEL_KEYS = ['desc', 'help', 'title_help', 'content', 'before', 'after'];

    /**
     * Build the id => default map for a section list, preserving each field's
     * NESTED storage shape so a fresh-install value map matches exactly what the
     * frontend templates and the React form read.
     *
     * The subtlety this handles: a `fieldset` stores its children under its OWN id
     * as a nested object — e.g. `switcher_button_position` holds
     * `['dark_mode_switch_position' => 'bottom_right', 'switch_position_top_right'
     * => ['top' => '40', 'right' => '40'], …]`, and the frontend reads them as
     * `$options['switcher_button_position']['switch_position_top_right']['top']`
     * (see Frontend/Templates/views/switch.php). Flattening those child defaults
     * to the top level would leave the nested object the UI actually reads absent,
     * so a fieldset's default is BUILT as that nested object instead.
     *
     * `group`/`repeater` stay leaves: they store a LIST of rows and carry their
     * own `default`; their sub-fields are a per-row template applied when a row
     * is added, not top-level or nested-object defaults. `section_tab` sub-tabs
     * save their fields flat, so those recurse to the top level unchanged.
     *
     * @param array $sections Registered section configs.
     * @return array
     */
    public static function collect(array $sections): array
    {
        $defaults = [];
        foreach ($sections as $section) {
            if (! empty($section['fields']) && \is_array($section['fields'])) {
                self::collect_fields($section['fields'], $defaults);
            }
        }
        return $defaults;
    }

    /**
     * Every registered section for an option key, resolved to its defaults.
     *
     * @param string $unique The option key (Darkify keeps everything under one).
     * @return array
     */
    public static function for_option(string $unique): array
    {
        $sections = SchemaRegistry::$sections[$unique] ?? [];
        return \is_array($sections) ? self::collect($sections) : [];
    }

    /**
     * Recursive worker for collect(). Writes into $out by reference, building a
     * fieldset's default as the nested object of its own children.
     *
     * @param array $fields The fields list to walk.
     * @param array $out    Accumulator, keyed by field id.
     */
    protected static function collect_fields(array $fields, array &$out): void
    {
        foreach ($fields as $field) {
            if (! \is_array($field)) {
                continue;
            }
            $id   = $field['id'] ?? '';
            $type = $field['type'] ?? '';

            // A fieldset's default is the nested object of its children's
            // defaults, stored under the fieldset's own id (recurses, so a
            // fieldset nested inside a fieldset nests correctly too).
            if (
                $type === 'fieldset'
                && $id !== ''
                && ! empty($field['fields'])
                && \is_array($field['fields'])
            ) {
                $nested = [];
                self::collect_fields($field['fields'], $nested);
                if (! empty($nested)) {
                    $out[$id] = $nested;
                }
                continue;
            }

            // An ordinary field with a declared default (this also covers
            // group/repeater, whose own `default` is a list of rows — we do NOT
            // recurse into their per-row template fields).
            if ($id !== '' && \array_key_exists('default', $field)) {
                $out[$id] = self::decode_labels($field['default']);
            }

            // section_tab sub-tabs: their fields save flat at the top level.
            if (! empty($field['tabs']) && \is_array($field['tabs'])) {
                foreach ($field['tabs'] as $tab) {
                    if (! empty($tab['fields']) && \is_array($tab['fields'])) {
                        self::collect_fields($tab['fields'], $out);
                    }
                }
            }
        }
    }

    /**
     * Entity-decode config strings so a label written as `&amp;` reaches the
     * consumer as `&`. Trusted-markup keys are left alone.
     *
     * @param mixed  $value
     * @param string $key
     * @return mixed
     */
    protected static function decode_labels($value, string $key = '')
    {
        if (\is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::decode_labels($v, (string) $k);
            }
            return $out;
        }

        if (\is_string($value) && ! \in_array($key, self::HTML_LABEL_KEYS, true)) {
            return \html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $value;
    }
}
