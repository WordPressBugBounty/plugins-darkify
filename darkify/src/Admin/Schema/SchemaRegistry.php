<?php

/**
 * Standalone field-schema registry.
 *
 * The config classes (src/Admin/Views/*.php) keep registering their field
 * sections through the framework's `createSection()` API exactly as before —
 * those files are unchanged. That API now forwards each section into this
 * lightweight registry instead of into a rendering framework. The REST layer
 * (AbstractRestController and its SettingsRest subclass) reads from here to
 * build the schema served to the React admin, so no PHP rendering is involved.
 *
 * This is what keeps the migration backward compatible: the field ids, defaults,
 * dependencies and the option key are still the ones the Views declared, so
 * existing saved settings load and save unchanged.
 *
 * @package    darkify
 * @subpackage darkify/src/Admin/Schema
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\Schema;

if (! defined('ABSPATH')) {
    die;
}

class SchemaRegistry
{
    /**
     * Registered sections keyed by option id. Darkify stores everything under a
     * single option, so in practice this holds one key: 'darkify'.
     *
     * @var array<string,array<int,array>>
     */
    public static $sections = array();

    /**
     * Page-level args from createOptions() (menu title, slug, icon, position).
     * The admin menu is registered from these so the menu keeps its existing
     * slug, icon and position — an existing bookmark to `?page=darkify` and the
     * menu's place in the sidebar both survive the migration.
     *
     * @var array<string,array>
     */
    public static $options = array();

    /**
     * Register one section config under an option key.
     *
     * @param string $id      The option key the section belongs to.
     * @param array  $section The section config (title, icon, fields, …).
     * @return void
     */
    public static function createSection($id, $section)
    {
        if (! isset(self::$sections[$id])) {
            self::$sections[$id] = array();
        }
        self::$sections[$id][] = $section;
    }

    /**
     * Record an option page's args.
     *
     * @param string $id   The option key.
     * @param array  $args Page args (menu_title, menu_slug, menu_icon, …).
     * @return void
     */
    public static function createOptions($id, $args = array())
    {
        self::$options[$id] = is_array($args) ? $args : array();
    }

    /**
     * Page args for an option key, with sane fallbacks.
     *
     * @param string $id The option key.
     * @return array
     */
    public static function options($id)
    {
        return isset(self::$options[$id]) ? self::$options[$id] : array();
    }
}
