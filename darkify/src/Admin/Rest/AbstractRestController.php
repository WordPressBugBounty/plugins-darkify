<?php

/**
 * Base controller for the React admin SPA's REST endpoints.
 *
 * Holds the shared namespace, the capability gate, and the schema/sanitization
 * helpers that read the SchemaRegistry (which the config classes in
 * src/Admin/Views/*.php register into, unchanged) and normalize it for React.
 * Concrete controllers (SettingsRest, LicenseRest) extend this and register
 * their own routes.
 *
 * @package    darkify
 * @subpackage darkify/src/Admin/Rest
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\Rest;

use ThemeAtelier\Darkify\Admin\Schema\SchemaDefaults;
use ThemeAtelier\Darkify\Admin\Schema\SchemaRegistry;

if (! defined('ABSPATH')) {
    die;
}

abstract class AbstractRestController
{
    /**
     * REST namespace for the admin SPA.
     */
    const NS = 'darkify/v1';

    /**
     * Option keys the settings API may read/write. Darkify keeps every setting in
     * a single option, so this is a one-entry allowlist — but it is still an
     * allowlist, so the `{key}` route parameter can never be pointed at an
     * arbitrary option.
     */
    const SETTINGS_KEYS = [
        'darkify',
    ];

    /**
     * Config keys whose values React renders as raw HTML (the schema's own
     * trusted markup — descriptions, tooltips, notices). They must NOT be
     * entity-decoded into text.
     */
    const HTML_LABEL_KEYS = ['desc', 'help', 'title_help', 'content', 'before', 'after'];

    public function __construct()
    {
        \add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register this controller's routes. Called on `rest_api_init`.
     */
    abstract public function register_routes(): void;

    /**
     * Permission gate for every admin route.
     *
     * `manage_options` is the capability the old options screen was registered
     * with, so this neither widens nor narrows who can change Darkify's settings.
     */
    public function can_manage(): bool
    {
        return \current_user_can('manage_options');
    }

    /**
     * Whether an option key is one the settings API may touch.
     */
    protected function is_valid_settings_key(string $key): bool
    {
        return \in_array($key, self::SETTINGS_KEYS, true);
    }

    // ─── Schema helpers ─────────────────────────────────────────────────────────

    /**
     * The raw registered section arrays for an option key.
     *
     * Registration happens on `after_setup_theme` (Admin::init_components), which
     * runs on every request including REST — so the registry is already populated
     * by the time a route callback runs.
     */
    public function get_registered_sections(string $unique): array
    {
        $sections = SchemaRegistry::$sections[$unique] ?? [];
        return \is_array($sections) ? $sections : [];
    }

    /**
     * Normalize the registered sections into the tree React consumes.
     *
     * Section ids are `sanitize_title($title)` — the same slug the old framework
     * used for its `#tab=` deep links, which is what lets an old bookmark like
     * `?page=darkify#tab=license` map straight onto the new `#/license` route.
     *
     * @return array<int,array>
     */
    public function normalize_sections(array $sections): array
    {
        $tabs = [];

        foreach ($sections as $section) {
            $title = $section['title'] ?? '';
            $tabs[] = [
                'id'     => $section['id'] ?? \sanitize_title($title !== '' ? $title : \uniqid('sec_')),
                'title'  => $title,
                'icon'   => $section['icon'] ?? '',
                'fields' => $this->clean_fields($section['fields'] ?? []),
            ];
        }

        return $this->decode_labels($tabs);
    }

    /**
     * Recursively HTML-entity-decode plain-text label strings in a config tree,
     * skipping the keys that hold trusted HTML markup. The configs come from PHP
     * (a trusted source) and React escapes text nodes on render, so decoding here
     * adds no XSS surface — it just stops `&amp;` showing up literally in a label.
     *
     * @param mixed  $value The value to process.
     * @param string $key   The array key $value was found under.
     * @return mixed
     */
    protected function decode_labels($value, string $key = '')
    {
        if (\is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->decode_labels($v, (string) $k);
            }
            return $out;
        }

        if (\is_string($value) && ! \in_array($key, self::HTML_LABEL_KEYS, true)) {
            return \html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $value;
    }

    /**
     * Strip non-serializable bits from field configs (callbacks etc.) and keep the
     * presentation/behavior keys React needs.
     */
    protected function clean_fields(array $fields): array
    {
        $keep = [
            'id', 'type', 'title', 'subtitle', 'desc', 'help', 'title_help', 'default',
            'options', 'placeholder', 'dependency', 'text_on', 'text_off', 'text_width',
            'inline', 'content', 'style', 'attributes', 'unit', 'min', 'max', 'step',
            'class', 'library', 'preview', 'multiple', 'fields', 'button_title',
            'add_button', 'settings', 'before', 'after', 'prepend', 'append',
            'all', 'units', 'max_items', 'min_items', 'columns', 'chosen', 'from_to',
            'label', 'image', 'remove_title', 'preview_width', 'preview_height',
            // `border` field toggles: which controls it offers. Without these the
            // renderer can't tell that e.g. Switch Border wants a radius and a
            // dark colour but no style dropdown, and falls back to a hardcoded
            // width/style/colour trio. ('style' and 'all' are already kept above.)
            'color', 'color2', 'color3', 'radius',
            // Narrows a field's options by another field's value (Tooltip Position
            // by Switch Position) — see limitOptionsBy() in FieldRenderer.jsx.
            'options_by',
            // `group`/`repeater` accordion row title (see build/.../fields/group/
            // group.php): without these the React renderer has no way to know a
            // row should be titled by e.g. its "select menu" sub-field, and falls
            // back to whichever sub-field happens to have a value first.
            'accordion_title_prefix', 'accordion_title_number', 'accordion_title_auto',
            'accordion_title_by', 'accordion_title_by_prefix',
            // `spacing` field per-side toggles (`'left' => false`). Without these the
            // renderer cannot tell that e.g. "Margin From Top Right" only wants top and
            // right, so it drew all four sides — two of them permanently blank inputs
            // that saved a value the frontend then ignored.
            'top', 'right', 'bottom', 'left',
            // `accordion_title_require`: a sub-field id that must have a value before
            // any composed title is shown, so a row stays "Switch #1" until the row is
            // actually configured rather than showing an always-present sibling
            // default alone. `row_title` is the singular label that numbered fallback
            // uses, separate from the group's own (plural) `title`.
            'accordion_title_require', 'row_title',
            // `heading` field flag marking the start of one of a merged page's former
            // tabs (e.g. Advanced's HTML/CSS Restriction / Custom CSS). Without it
            // FieldRenderer can't tell those headings apart from an ordinary
            // sub-heading, and never splits the page into separate cards with a real
            // gap between them.
            'section_start',
            // `repeater`/`group` empty-state copy. Without these the renderer falls
            // back to a generic one-line "No items yet." for every repeater alike.
            'empty_title', 'empty_desc',
            // `text` field opt-in validation mode (e.g. 'css_selectors' for
            // Alternative Switch Selectors). Without it the renderer can't tell a
            // plain text field wants live selector validation with an inline error,
            // and falls back to an unvalidated input.
            'validate',
            // Field opt-in: render this row on its own tinted, inset sub-card
            // (e.g. dependent "Level" sliders under Image/Video Controls).
            'panel',
            // `switcher` opt-in: commit instantly (auto-save) even though other
            // fields depend on it. See the eligibility check in FieldRenderer.
            'auto_save',
        ];

        $out = [];
        foreach ($fields as $field) {
            if (! \is_array($field)) {
                continue;
            }

            $clean = [];
            foreach ($keep as $k) {
                if (\array_key_exists($k, $field)) {
                    $clean[$k] = $field[$k];
                }
            }

            // Resolve dynamic option sources ('pages', 'posts', …) into a real
            // {value => label} map so React can render the choices.
            if (isset($clean['options']) && \is_string($clean['options'])) {
                $clean['options'] = $this->resolve_options($field);
            }

            // Serialize `options` as an ordered list of [key, label] pairs
            // rather than a plain {key: label} map. A PHP associative array
            // with a canonical integer-looking key (e.g. Switch Size's "1"
            // for "M", alongside "0.6" and "custom") round-trips through JSON
            // as a plain JS object — and JS hoists integer-index-like keys to
            // the front of a plain object's own-property order ahead of
            // non-numeric ones, REGARDLESS of insertion order. That silently
            // reordered "M" in front of "XS"/"S", and would do the same to
            // any numeric-keyed dropdown (pages/posts by ID, sizes, …). A
            // list of pairs has no such ambiguity: order is exactly what PHP
            // declared (see FieldRenderer.jsx's `optionEntries`, which reads
            // this shape).
            if (isset($clean['options']) && \is_array($clean['options'])) {
                $clean['options'] = \array_map(
                    function ($k, $v) {
                        return [$k, $v];
                    },
                    \array_keys($clean['options']),
                    \array_values($clean['options'])
                );
            }

            // The two shortcode fields are read-only helpers: the old framework
            // hard-coded their template into the field renderer. Now that the
            // renderer is gone, PHP still owns the string — it travels in the
            // schema instead of being duplicated in JS.
            if (isset($clean['type']) && isset(self::SHORTCODE_TEMPLATES[$clean['type']])) {
                $clean['shortcode'] = self::SHORTCODE_TEMPLATES[$clean['type']];
            }

            // Recurse into nested fields (fieldset / repeater / group).
            if (! empty($field['fields']) && \is_array($field['fields'])) {
                $clean['fields'] = $this->clean_fields($field['fields']);
            }

            // Recurse into section_tab sub-tabs. Each tab is a {title, icon,
            // fields} group whose fields keep their own top-level ids, so they
            // still save flat into the option.
            if (! empty($field['tabs']) && \is_array($field['tabs'])) {
                $clean['tabs'] = \array_map(function ($tab) {
                    $title = $tab['title'] ?? '';
                    return [
                        'id'         => $tab['id'] ?? \sanitize_title($title !== '' ? $title : \uniqid('tab_')),
                        'title'      => $title,
                        'icon'       => $tab['icon'] ?? '',
                        'fields'     => $this->clean_fields($tab['fields'] ?? []),
                        // Lets a whole tab hide conditionally, not just individual
                        // fields within it.
                        'dependency' => $tab['dependency'] ?? null,
                    ];
                }, \array_values($field['tabs']));
            }

            $out[] = $clean;
        }

        return $out;
    }

    /**
     * Navigation sources that are stored as posts rather than `nav_menu` terms.
     *
     * Keyed by post type, valued by the short source label shown after the menu
     * name in the picker. `kadence_navigation` is only registered when Kadence
     * Blocks is active, which is why every caller checks post_type_exists()
     * before querying — the list itself is deliberately static so it costs
     * nothing to ask for.
     */
    protected static function darkify_block_menu_sources()
    {
        return [
            'wp_navigation'      => \__('Block menu', 'darkify'),
            'kadence_navigation' => \__('Kadence', 'darkify'),
        ];
    }

    /**
     * The read-only shortcode templates for the `switcher_shortcode` /
     * `switcher_shortcode_v2` fields, byte-for-byte as the old framework's field
     * renderers printed them.
     */
    const SHORTCODE_TEMPLATES = [
        'switcher_shortcode'    => '[darkify switch="1" light_mode_bg="#121116" dark_mode_bg="#ffffff" light_mode_color="#ffffff" dark_mode_color="#121116" border="0px" border_light_color="#121119" border_dark_color="#ffffff" border_radius="7px" switch_size="100"]',
        'switcher_shortcode_v2' => '[darkify switch="5" light_mode_bg="#121116" dark_mode_bg="#ffffff" light_mode_color="#ffffff" dark_mode_color="#121116" border="0px" border_light_color="#121119" border_dark_color="#ffffff" border_radius="30px"]',
    ];

    /**
     * Resolve a dynamic-options token (a string like 'pages') into an id => label
     * map. An unknown token resolves to an empty list rather than leaking the
     * token itself into the UI as a bogus choice.
     *
     * @param array $field The raw field config (may carry `query_args`).
     * @return array<string,string>
     */
    protected function resolve_options(array $field): array
    {
        $token = $field['options'];
        $out   = [];

        switch ($token) {
            case 'posts': {
                $args = (isset($field['query_args']) && \is_array($field['query_args']))
                    ? $field['query_args']
                    : ['post_type' => 'post'];
                $args['posts_per_page'] = $args['posts_per_page'] ?? -1;
                $args['post_status']    = $args['post_status'] ?? 'publish';
                foreach (\get_posts($args) as $post) {
                    $out[(string) $post->ID] = ($post->post_title !== '')
                        ? $post->post_title
                        /* translators: %d: post ID. */
                        : \sprintf(\__('(no title) #%d', 'darkify'), $post->ID);
                }
                break;
            }

            case 'pages': {
                // The theme-rendered screens come first — they have no Page post
                // behind them, so get_pages() can never surface them.
                $out = $this->virtual_page_options();

                // get_pages() returns false (not an array) if it rejects its
                // arguments, which would otherwise warn on the foreach.
                $pages = \get_pages(['post_status' => 'publish']);
                foreach (\is_array($pages) ? $pages : [] as $page) {
                    $out[(string) $page->ID] = ($page->post_title !== '')
                        ? $page->post_title
                        /* translators: %d: page ID. */
                        : \sprintf(\__('(no title) #%d', 'darkify'), $page->ID);
                }
                break;
            }

            case 'post_type': {
                foreach (\get_post_types(['public' => true], 'objects') as $slug => $obj) {
                    $out[(string) $slug] = $obj->labels->singular_name ?? $slug;
                }
                break;
            }

            // WordPress menus are `nav_menu` taxonomy terms, keyed by term_id —
            // the same id the old framework's WP_Term_Query-based resolver used
            // (see build/.../fields.class.php), so an existing saved
            // `switch_in_menu_location` term_id still resolves to the right menu.
            case 'menu':
            case 'menus': {
                foreach (\wp_get_nav_menus() as $menu) {
                    $out[(string) $menu->term_id] = $menu->name;
                }

                /*
                 * Block-theme and Kadence navigations are POSTS, not `nav_menu`
                 * terms, so wp_get_nav_menus() above cannot see them. On a block
                 * theme — or any site whose menus were built with Kadence
                 * Navigation — this field came back completely empty even though
                 * the site had menus, because there were no classic menus left to
                 * list.
                 *
                 * Their ids are POST ids and would collide with the term ids
                 * above (post 5 and term 5 are different menus), so they are
                 * namespaced `<post_type>:<id>`. Bare numeric keys stay reserved
                 * for classic menus, which is what keeps every already-saved
                 * `switch_in_menu_location` resolving to exactly the menu it
                 * always did.
                 */
                foreach (self::darkify_block_menu_sources() as $post_type => $label) {
                    if (! \post_type_exists($post_type)) {
                        continue;
                    }

                    $navs = \get_posts([
                        'post_type'        => $post_type,
                        'post_status'      => 'publish',
                        'numberposts'      => -1,
                        'orderby'          => 'title',
                        'order'            => 'ASC',
                        'suppress_filters' => false,
                    ]);

                    foreach (\is_array($navs) ? $navs : [] as $nav) {
                        $title = ($nav->post_title !== '')
                            ? $nav->post_title
                            /* translators: %d: navigation menu post ID. */
                            : \sprintf(\__('(no title) #%d', 'darkify'), $nav->ID);

                        // The source is shown because a site can legitimately
                        // have a classic menu, a block menu and a Kadence menu
                        // all called "Primary Menu".
                        $out[$post_type . ':' . $nav->ID] = $title . ' — ' . $label;
                    }
                }
                break;
            }
        }

        return $out;
    }

    /**
     * The theme-rendered screens offered alongside real pages by the `pages`
     * token, keyed by the id the frontend matches on.
     *
     * These screens are produced by the theme and have no Page post behind them,
     * so `get_pages()` cannot return them and they were unselectable — even
     * though DarkifyUtils::isRestrictedByAllowedPages() and
     * ::isRestrictedByDisallowedPages() already test for exactly these ids.
     *
     * The KEYS ARE A CONTRACT with those two methods and must not change. Note
     * `'0'`: on a static front page the frontend deliberately matches "0"
     * instead of the assigned page's real ID, so selecting that page in the list
     * does NOT cover the front page — this entry is the only way to reach it.
     * None of the keys can collide with a page ID (0 is never a post ID, and the
     * rest are non-numeric).
     *
     * @return array<string,string>
     */
    protected function virtual_page_options(): array
    {
        return [
            '0'             => \__('Front Page', 'darkify'),
            'post_page'     => \__('Blog Page (Posts Index)', 'darkify'),
            'post_archive'  => \__('Category & Tag Archives', 'darkify'),
            'search_search' => \__('Search Results', 'darkify'),
            '404_page'      => \__('404 Page', 'darkify'),
            'lr'            => \__('Login & Register', 'darkify'),
        ];
    }

    /**
     * Build the id => default map for a section list, preserving each field's
     * NESTED storage shape so a fresh-install value map matches exactly what the
     * frontend templates and the React form read.
     *
     * The subtlety this fixes: a `fieldset` stores its children under its OWN id
     * as a nested object — e.g. `switcher_button_position` holds
     * `['dark_mode_switch_position' => 'bottom_right', 'switch_position_top_right'
     * => ['top' => '40', 'right' => '40'], …]`, and the frontend reads them as
     * `$options['switcher_button_position']['switch_position_top_right']['top']`
     * (see Frontend/templates/views/switch.php). Flattening those child defaults
     * to the top level (`$defaults['switch_position_top_right']`) — the old
     * behaviour — left the nested object the UI actually reads absent, so on a
     * new install every fieldset control (Positioning, Tooltip, Hide-on-Mobile,
     * the image/video filters, Different-Switch-in-Mobile…) rendered empty. Here
     * a fieldset's default is BUILT as that nested object instead.
     *
     * `group`/`repeater` stay leaves: they store a LIST of rows and carry their
     * own `default`; their sub-fields are a per-row template applied when a row
     * is added, not top-level or nested-object defaults. `section_tab` sub-tabs
     * save their fields flat, so those recurse to the top level unchanged.
     */
    public function collect_defaults(array $sections): array
    {
        // The walk itself lives in SchemaDefaults so the install seeder
        // (Admin::seed_default_options) resolves defaults through exactly the
        // same code path this controller does.
        return SchemaDefaults::collect($sections);
    }

    /**
     * Merge saved values over schema defaults.
     *
     * Unlike a plain `array_merge($defaults, $saved)`, an empty-string saved value
     * does NOT clobber a non-empty default. In this data model there is no null —
     * an empty string is the "never meaningfully set" marker that a partial or
     * legacy save leaves behind for fields the old form never submitted (a
     * switcher or button_set hidden behind a dependency, say). Letting `''` win
     * would silently blank out documented defaults for those users.
     *
     * Real choices are preserved: `'0'`/`false` (a switcher turned off), a
     * selected option string, an explicit empty array (a deliberately cleared
     * repeater) and any non-empty string all still override the default. Only the
     * ambiguous empty string yields.
     *
     * Nested fieldset objects are merged RECURSIVELY: a saved
     * `switcher_button_position` that only carries the sub-keys the form actually
     * submitted (an older save, or a sub-field hidden behind a dependency) still
     * resolves its missing sub-keys to their documented defaults, while every
     * saved sub-value wins. This makes the form resolve nested defaults the same
     * way the frontend already does (each nested read there has its own
     * fallback). The recursion is gated on BOTH sides being associative
     * (string-keyed) arrays, so repeater/group LISTS and scalars keep the plain
     * replace — a deliberately emptied or reordered list is honoured exactly as
     * before.
     */
    public function merge_defaults(array $defaults, array $saved): array
    {
        $values = $defaults;
        foreach ($saved as $key => $value) {
            if (
                $value === ''
                && \array_key_exists($key, $defaults)
                && $defaults[$key] !== ''
                && $defaults[$key] !== null
            ) {
                continue; // keep the non-empty default
            }
            if (
                \is_array($value)
                && isset($defaults[$key])
                && \is_array($defaults[$key])
                && $this->is_assoc_array($defaults[$key])
                && $this->is_assoc_array($value)
            ) {
                $values[$key] = $this->merge_defaults($defaults[$key], $value);
                continue;
            }
            $values[$key] = $value;
        }
        return $values;
    }

    /**
     * Whether an array is associative (string-keyed) rather than a plain 0..n
     * list. Used to tell a fieldset's nested object (deep-merge) apart from a
     * repeater/group's list of rows (plain replace). An empty array is treated
     * as a list, so an intentionally cleared value replaces rather than merges.
     */
    protected function is_assoc_array(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return \array_keys($arr) !== \range(0, \count($arr) - 1);
    }

    /**
     * Flatten id => type across a section list.
     */
    public function collect_field_types(array $sections): array
    {
        $types = [];
        $this->walk_fields($sections, function ($field) use (&$types) {
            if (! empty($field['id']) && ! empty($field['type'])) {
                $types[$field['id']] = $field['type'];
            }
        });
        return $types;
    }

    /**
     * Recursively visit every field across sections (including nested `fields`
     * and `section_tab` tabs).
     */
    protected function walk_fields(array $sections, callable $cb): void
    {
        foreach ($sections as $section) {
            if (empty($section['fields']) || ! \is_array($section['fields'])) {
                continue;
            }
            foreach ($section['fields'] as $field) {
                if (! \is_array($field)) {
                    continue;
                }
                $cb($field);
                if (! empty($field['fields']) && \is_array($field['fields'])) {
                    $this->walk_fields([['fields' => $field['fields']]], $cb);
                }
                if (! empty($field['tabs']) && \is_array($field['tabs'])) {
                    foreach ($field['tabs'] as $tab) {
                        if (! empty($tab['fields']) && \is_array($tab['fields'])) {
                            $this->walk_fields([['fields' => $tab['fields']]], $cb);
                        }
                    }
                }
            }
        }
    }

    // ─── Sanitization ───────────────────────────────────────────────────────────

    /**
     * Type-aware sanitization of an incoming values map.
     *
     * `$type_map` is the FLAT id => type map produced by collect_field_types(),
     * which walks nested fields too — so a field's type is found by id no matter
     * how deeply it is nested. That matters: 26 of Darkify's value-bearing fields
     * live inside a `fieldset` or a `repeater` row (the brightness/grayscale
     * toggles and sliders, the image/video exclusion lists, the replacement
     * uploads…). Sanitizing those by position rather than by type would coerce
     * `true` to `'1'`, `100` to `'100'`, and — worst — strip the newlines out of
     * the multi-line exclusion lists.
     */
    public function sanitize_values(array $values, array $type_map): array
    {
        $clean = [];
        foreach ($values as $key => $value) {
            $clean[$key] = $this->sanitize_value($value, $type_map[$key] ?? '', $type_map);
        }
        return $clean;
    }

    /**
     * Sanitize a single value by field type.
     *
     * Note the deliberate holes: `code_editor` and `textarea` carry the user's own
     * CSS and their newline-separated selector lists (Dark Mode CSS, Normal Mode
     * CSS, the element allow/deny lists). Running those through
     * `sanitize_text_field()` would collapse newlines and mangle the rules, so they
     * get the type-appropriate treatment instead.
     *
     * @param mixed  $value    The value to sanitize.
     * @param string $type     The field type, if known.
     * @param array  $type_map Flat id => type map, used to keep nested values
     *                         (fieldset children, repeater rows) type-aware.
     */
    protected function sanitize_value($value, string $type = '', array $type_map = [])
    {
        // Preserved as-is rather than stringified to "": a cleared media field
        // sends null, and casting that to a string would put "" into the option
        // where the frontend expects either a URL or nothing.
        if ($value === null) {
            return null;
        }

        if (\is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                // A repeater's rows are a numeric list, so `$k` is an index and
                // resolves to no type — recursion then reaches the row object,
                // whose keys ARE field ids and do resolve. A fieldset's keys are
                // field ids directly. Either way the leaf is typed correctly.
                $key        = \sanitize_text_field((string) $k);
                $out[$key]  = $this->sanitize_value($v, $type_map[$key] ?? '', $type_map);
            }
            return $out;
        }

        switch ($type) {
            case 'textarea':
                return \sanitize_textarea_field((string) $value);

            case 'code_editor':
                // Raw CSS/JS the user authored. `wp_kses_post` would eat `>` in a
                // child selector (`.a > .b`), so the value is stored verbatim —
                // it is written only by `manage_options` users and is never
                // executed as PHP.
                return (string) $value;

            case 'switcher':
            case 'checkbox':
                return $value; // booleans / arrays already handled above

            case 'number':
            case 'slider':
            case 'spinner':
                return \is_numeric($value) ? $value + 0 : \sanitize_text_field((string) $value);

            case 'upload':
                return \esc_url_raw((string) $value);

            default:
                return \sanitize_text_field((string) $value);
        }
    }

    // ─── Pro-feature locking (free plugin) ──────────────────────────────────────

    /**
     * Whether a field config is Pro-locked in the free plugin.
     *
     * The free config files have always marked Pro-only fields with a CSS class
     * (`only_pro`, `switcher_pro_only`, `repeater_pro_only`) — the retired options
     * framework rendered those rows dimmed with an upgrade overlay. The class
     * markers remain the single source of truth, so the set of locked fields is
     * exactly the set the old admin locked; here they are translated into the
     * `pro: true` flag the React admin's ProLock UI consumes (same contract as
     * Chat Help).
     *
     * The marker convention is a class token that is `only_pro` or ends in
     * `_pro_only` (`switcher_pro_only`, `repeater_pro_only`, …). Matching the
     * whole family — rather than an explicit list — is what fixes the Replace
     * Images / Replace Videos repeaters: their `repeater_pro_only` marker was
     * silently ignored after the React migration (an earlier, narrower pattern
     * only matched a bare `pro_only` token), so those Pro-only repeaters wrongly
     * rendered as editable free fields even though the free frontend never had
     * any replacement logic to honour them.
     */
    protected function is_pro_field(array $field): bool
    {
        $class = isset($field['class']) && \is_string($field['class']) ? $field['class'] : '';
        return (bool) \preg_match('/(?:^|\s)(?:only_pro|[A-Za-z0-9_-]*pro_only)(?:\s|$)/', $class);
    }

    /**
     * Option keys inside a field's `options` map that are Pro-locked — the
     * config marks them with `'pro_only' => true` on the option row (e.g. the
     * Pro switch styles in SwitcherStyle.php).
     *
     * @return array<int,string>
     */
    protected function locked_option_keys(array $field): array
    {
        $locked = [];
        if (! empty($field['options']) && \is_array($field['options'])) {
            foreach ($field['options'] as $key => $opt) {
                if (\is_array($opt) && ! empty($opt['pro_only'])) {
                    $locked[] = (string) $key;
                }
            }
        }
        return $locked;
    }

    /**
     * Stamp `pro`/`pro_options` flags onto a normalized schema tree so the React
     * admin can render Pro-only fields and choices as locked previews — the same
     * flags Chat Help's free admin uses (see chat-help-react's ProLock).
     */
    public function apply_pro_flags(array $tree): array
    {
        foreach ($tree as &$section) {
            if (! empty($section['fields']) && \is_array($section['fields'])) {
                $section['fields'] = $this->mark_pro_fields($section['fields']);
            }
        }
        unset($section);
        return $tree;
    }

    /**
     * Recursively add `pro`/`pro_options` flags to a field list (including
     * nested fieldset/group fields and section_tab tabs).
     *
     * @param array $fields Normalized field list.
     * @param bool  $force  Inherit a Pro lock from an enclosing field.
     */
    protected function mark_pro_fields(array $fields, bool $force = false): array
    {
        foreach ($fields as &$field) {
            if (! \is_array($field)) {
                continue;
            }

            $field_pro = $force || $this->is_pro_field($field);
            if ($field_pro) {
                $field['pro'] = true;
            }

            $locked = $this->locked_option_keys($field);
            if (! empty($locked)) {
                $field['pro_options'] = $locked;
            }

            if (! empty($field['fields']) && \is_array($field['fields'])) {
                $field['fields'] = $this->mark_pro_fields($field['fields'], $field_pro);
            }
            if (! empty($field['tabs']) && \is_array($field['tabs'])) {
                foreach ($field['tabs'] as &$tab) {
                    if (! empty($tab['fields']) && \is_array($tab['fields'])) {
                        $tab['fields'] = $this->mark_pro_fields($tab['fields'], $force);
                    }
                }
                unset($tab);
            }
        }
        unset($field);
        return $fields;
    }

    /**
     * Drop Pro-locked keys from an incoming values map before persisting, so a
     * crafted request can never flip a Pro-only setting in the free plugin. The
     * locked UI never submits these (its onChange is neutered) — this is
     * defence-in-depth, mirroring Chat Help's strip_pro_keys().
     *
     * Locked fields are addressed by PATH, not just by top-level id: a fieldset
     * stores its children NESTED under its own id (see collect_defaults), so a
     * locked child inside an unlocked fieldset must be stripped inside that
     * nested object. Dropping the key (rather than blanking it) means the
     * caller's array_merge keeps whatever value is already stored.
     *
     * @param array $values   Sanitized incoming values.
     * @param array $sections The registered sections for the option key.
     */
    public function strip_pro_keys(array $values, array $sections): array
    {
        $tree        = $this->apply_pro_flags($this->normalize_sections($sections));
        $pro_paths   = [];
        $locked_opts = [];
        foreach ($tree as $section) {
            if (! empty($section['fields']) && \is_array($section['fields'])) {
                $this->collect_pro_paths($section['fields'], [], $pro_paths, $locked_opts);
            }
        }

        foreach ($pro_paths as $path) {
            $this->unset_path($values, $path);
        }

        foreach ($locked_opts as $entry) {
            $current = $this->get_path($values, $entry['path'], $exists);
            if (! $exists) {
                continue;
            }
            if (\is_array($current)) {
                // Multi-value field: drop only the Pro-locked choices, keep the
                // free ones.
                $filtered = \array_values(\array_filter(
                    $current,
                    static function ($v) use ($entry) {
                        return ! \in_array((string) $v, $entry['options'], true);
                    }
                ));
                $this->set_path($values, $entry['path'], $filtered);
                continue;
            }
            if (\is_scalar($current) && \in_array((string) $current, $entry['options'], true)) {
                $this->unset_path($values, $entry['path']);
            }
        }

        return $values;
    }

    /**
     * Recursively gather the storage paths of Pro-locked fields and of fields
     * with Pro-locked option choices, from a flagged schema tree.
     *
     * A `fieldset` pushes its own id onto the path (its children save nested
     * under it); `section_tab` tabs save flat, so the path passes through.
     * `group`/`repeater` sub-fields never save as flat keys — their values nest
     * per-row inside the parent's own list, and a locked parent is stripped by
     * its own path — so their sub-fields are not descended into (same rationale
     * as Chat Help's collect_pro_ids).
     *
     * @param array $fields      Flagged field list.
     * @param array $path        Ids of the enclosing fieldsets.
     * @param array $pro_paths   Out: paths of fully-locked fields.
     * @param array $locked_opts Out: list of ['path' => …, 'options' => …].
     */
    protected function collect_pro_paths(array $fields, array $path, array &$pro_paths, array &$locked_opts): void
    {
        foreach ($fields as $field) {
            if (! \is_array($field)) {
                continue;
            }
            $id   = (string) ($field['id'] ?? '');
            $type = (string) ($field['type'] ?? '');

            if ($id !== '' && ! empty($field['pro'])) {
                $pro_paths[] = \array_merge($path, [$id]);
            }
            if ($id !== '' && ! empty($field['pro_options']) && \is_array($field['pro_options'])) {
                $locked_opts[] = [
                    'path'    => \array_merge($path, [$id]),
                    'options' => \array_map('strval', $field['pro_options']),
                ];
            }

            if (! empty($field['tabs']) && \is_array($field['tabs'])) {
                foreach ($field['tabs'] as $tab) {
                    if (! empty($tab['fields']) && \is_array($tab['fields'])) {
                        $this->collect_pro_paths($tab['fields'], $path, $pro_paths, $locked_opts);
                    }
                }
            }

            if (! empty($field['fields']) && \is_array($field['fields'])) {
                if ($type === 'fieldset' && $id !== '') {
                    $this->collect_pro_paths($field['fields'], \array_merge($path, [$id]), $pro_paths, $locked_opts);
                } elseif (! \in_array($type, ['group', 'repeater'], true)) {
                    $this->collect_pro_paths($field['fields'], $path, $pro_paths, $locked_opts);
                }
            }
        }
    }

    /** Unset a nested key addressed by a path of ids. */
    protected function unset_path(array &$values, array $path): void
    {
        $last = \array_pop($path);
        $ref  = &$values;
        foreach ($path as $step) {
            if (! isset($ref[$step]) || ! \is_array($ref[$step])) {
                return;
            }
            $ref = &$ref[$step];
        }
        unset($ref[$last]);
    }

    /**
     * Read a nested value addressed by a path of ids.
     *
     * @param bool $exists Out: whether the full path resolved.
     * @return mixed
     */
    protected function get_path(array $values, array $path, &$exists)
    {
        $exists = false;
        $cur    = $values;
        foreach ($path as $step) {
            if (! \is_array($cur) || ! \array_key_exists($step, $cur)) {
                return null;
            }
            $cur = $cur[$step];
        }
        $exists = true;
        return $cur;
    }

    /** Write a nested value addressed by a path of ids (path must resolve). */
    protected function set_path(array &$values, array $path, $value): void
    {
        $last = \array_pop($path);
        $ref  = &$values;
        foreach ($path as $step) {
            if (! isset($ref[$step]) || ! \is_array($ref[$step])) {
                return;
            }
            $ref = &$ref[$step];
        }
        $ref[$last] = $value;
    }
}
