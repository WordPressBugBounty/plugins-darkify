<?php

/**
 * Control class for Control options.
 *
 * @link       https://themeatelier.net
 * @since      1.0.0
 *
 * @package darkify
 * @subpackage darkify/src/Admin/Views/Control
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\Views;

use ThemeAtelier\Darkify\Admin\Schema\SchemaRegistry as Darkify;

class Control
{
    /**
     * Create Option fields for the setting options.
     *
     * @param string $prefix Option setting key prefix.
     * @return void
     */
    public static function options($prefix)
    {
        Darkify::createSection(
            $prefix,
            array(
                // Explicit, locale-independent id. Without it the REST layer falls
                // back to sanitize_title() of the *translated* title, so the route
                // became #/sterowanie on a Polish site and every link 404'd inside
                // the SPA. Value matches the old English slug so existing
                // `#tab=` / `#/controls` bookmarks keep resolving.
                'id'          => 'controls',
                'title'       => esc_html__('Controls', 'darkify'),
                'icon'        => 'icofont-ui-settings',
                'fields'      => array(
                    array(
                        'type' => 'section_tab',
                        'tabs' => array(

                            array(
                                'id'    => 'frontend',
                                'title' => esc_html__('Frontend', 'darkify'),
                                'icon'  => 'icofont-computer',
                                'fields' => array(
                                    // Intro line with a docs link, rendered plain above the section cards.
                                    array(
                                        'type'    => 'page_hint',
                                        'content' => sprintf(
                                            /* translators: %s: documentation link */
                                            __('Decide how and when dark mode turns on for visitors on your public website. %s', 'darkify'),
                                            '<a href="https://darkifywp.com/docs/" target="_blank" rel="noopener noreferrer">' . esc_html__('View documentation', 'darkify') . '</a>'
                                        ),
                                    ),

                                    // ===== AVAILABILITY =====
                                    array(
                                        'type'          => 'heading',
                                        'title'         => esc_html__('Availability', 'darkify'),
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id'      => 'enable_dark_mode_switch',
                                        'type'    => 'switcher',
                                        'title' => esc_html__('Frontend Dark Mode', 'darkify'),
                                        'title_help' => '<div class="darkify-info-label">' .
                                            __('Show the dark mode floating switch on your website frontend. This is the master control for everything below.', 'darkify') .
                                            '</div>' . '' . '',
                                        'text_on'    => esc_html__('Enabled', 'darkify'),
                                        'text_off'    => esc_html__('Disabled', 'darkify'),
                                        'text_width' => 100,
                                        'default' => true,
                                        // Commit instantly (master toggle governs the whole frontend feature).
                                        'auto_save' => true,
                                    ),
                                    array(
                                        'id'      => 'enable_default_dark_mode',
                                        'type'    => 'switcher',
                                        'title' => esc_html__('Default Dark Mode', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Enable dark mode by default on your website.', 'darkify') .
                                            '</div>' .
                                            '' .
                                            '',
                                        'text_on'  => esc_html__('Enabled', 'darkify'),
                                        'text_off' => esc_html__('Disabled', 'darkify'),
                                        'text_width' => 100,
                                        // Commit instantly (the priority notice depends on it, which would
                                        // otherwise force the manual-Save workflow).
                                        'auto_save' => true,
                                    ),
                                    array(
                                        'id'      => 'enable_os_aware',
                                        'type'    => 'switcher',
                                        'title' => esc_html__('OS Aware Dark Mode', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Toggle dark mode based on the user’s device or operating system preference.', 'darkify') .
                                            '</div>' .
                                            '' .
                                            '',
                                        'text_on'  => esc_html__('Enabled', 'darkify'),
                                        'text_off' => esc_html__('Disabled', 'darkify'),
                                        'text_width' => 100,
                                        'default' => true,
                                        // Commit instantly (the priority notice depends on it).
                                        'auto_save' => true,
                                    ),
                                    // Priority notice: shown only when Default Dark Mode AND OS Aware are both on.
                                    array(
                                        'type'       => 'submessage',
                                        'style'      => 'info',
                                        'content'    => '<strong>' . esc_html__('OS Aware takes priority.', 'darkify') . '</strong> ' . esc_html__('Both are on, so visitors following the system “dark” setting get dark mode automatically; Default Dark Mode applies only when no system preference is detected.', 'darkify'),
                                        'dependency' => array('enable_default_dark_mode|enable_os_aware', '==|==', 'true|true'),
                                    ),

                                    // ===== ACTIVATION =====
                                    array(
                                        'type'          => 'heading',
                                        'title'         => esc_html__('Activation', 'darkify'),
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id'      => 'enable_keyboard_shortcut',
                                        'type'    => 'switcher',
                                        'title' => esc_html__('Keyboard Shortcut', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Let visitors toggle dark mode with a keyboard shortcut.', 'darkify') .
                                            '</div>' .
                                            '' .
                                            '',
                                        'text_on'  => esc_html__('Enabled', 'darkify'),
                                        'text_off' => esc_html__('Disabled', 'darkify'),
                                        'text_width' => 100,
                                        'default' => true,
                                        // Commit instantly (the shortcut-keys field depends on it).
                                        'auto_save' => true,
                                    ),
                                    // Editable shortcut combo (shown when the shortcut is enabled).
                                    array(
                                        'id'         => 'keyboard_shortcut_keys',
                                        'type'       => 'keyboard_shortcut',
                                        'default'    => 'ctrl+alt+d',
                                        'dependency' => array('enable_keyboard_shortcut', '==', 'true'),
                                    ),
                                    // URL parameter: a link such as ?darkmode=1 opens the page in dark
                                    // mode (and ?darkmode=0 in light). Read by the earliest inline
                                    // script in header_script.php, before anything paints.
                                    array(
                                        'id'      => 'enable_url_parameter',
                                        'type'    => 'switcher',
                                        'title' => esc_html__('URL Parameter', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Toggle dark mode from a link. Add <code>?darkmode=1</code> to any page URL to open it in dark mode, or <code>?darkmode=0</code> to open it in light mode. The choice is remembered for the visitor, like using the switch.', 'darkify') .
                                            '</div>',
                                        'text_on'    => esc_html__('Enabled', 'darkify'),
                                        'text_off'    => esc_html__('Disabled', 'darkify'),
                                        'text_width' => 100,
                                        'default' => false,
                                        // Commit instantly (the parameter-name field depends on it).
                                        'auto_save' => true,
                                    ),
                                    array(
                                        'id'          => 'url_parameter_name',
                                        'type'        => 'text',
                                        'title'       => esc_html__('Parameter Name', 'darkify'),
                                        'title_help'  =>
                                        '<div class="darkify-info-label">' .
                                            __('The name used in the URL. Letters, numbers, hyphens and underscores only. Accepted values: <code>1</code>, <code>on</code>, <code>true</code>, <code>dark</code> to turn dark mode on; <code>0</code>, <code>off</code>, <code>false</code>, <code>light</code> to turn it off.', 'darkify') .
                                            '</div>',
                                        'placeholder' => 'darkmode',
                                        'default'     => 'darkmode',
                                        'dependency'  => array('enable_url_parameter', '==', 'true'),
                                    ),
                                    // Enable Time Based Auto Dark Mode
                                    array(
                                        'id'     => 'enable_time',
                                        'type'   => 'fieldset',
                                        'fields' => array(
                                            array(
                                                'id'    => 'enable_time_based_dark',
                                                'type'  => 'switcher',
                                                'title' => esc_html__('Time Based Auto Dark Mode', 'darkify'),
                                                'title_help' =>
                                                '<div class="darkify-info-label">' .
                                                    __('Automatically enable dark mode based on the user’s local time.', 'darkify') .
                                                    '</div>' .
                                                    '' .
                                                    '',
                                                'text_on'  => esc_html__('Enabled', 'darkify'),
                                                'text_off' => esc_html__('Disabled', 'darkify'),
                                                'text_width' => 100,
                                            ),
                                            array(
                                                'id'     => 'enable_time_fields',
                                                'type'   => 'fieldset',
                                                'class'   => 'enable_time_fields',
                                                'dependency' => array('enable_time_based_dark', '==', 'true'),
                                                'title' => esc_html__('Pick a Time', 'darkify'),
                                                'title_help' =>
                                                '<div class="darkify-info-label">' .
                                                    __('Set the exact start and end times when dark mode should be automatically enabled or disabled.', 'darkify') .
                                                    '</div>' .
                                                    '' .
                                                    '',
                                                'fields' => array(
                                                    /*
                                                     * Which clock drives the schedule. Darkify Lite ships
                                                     * only "fixed" (the start/end window below); the
                                                     * sunrise/sunset schedule is a Pro feature, so its
                                                     * option row carries `pro_only` — the REST layer turns
                                                     * that into the `pro_options` stamp the React admin
                                                     * renders as a locked, badged preview, and
                                                     * strip_pro_keys() refuses to persist the value even
                                                     * if a crafted request sends it.
                                                     */
                                                    array(
                                                        'id'      => 'time_based_dark_mode',
                                                        'type'    => 'button_set',
                                                        'default' => 'fixed',
                                                        'options' => array(
                                                            'fixed'    => esc_html__('Fixed times', 'darkify'),
                                                            'suncycle' => array(
                                                                'text'     => esc_html__('Sunrise & sunset', 'darkify'),
                                                                'pro_only' => true,
                                                            ),
                                                        ),
                                                    ),
                                                    array(
                                                        'id'        => 'time_based_dark_start',
                                                        'type'      => 'datetime',
                                                        'after'     => esc_html__('From', 'darkify'),
                                                        'placeholder'   => esc_html__('From', 'darkify'),
                                                        'settings'  => array(
                                                            'noCalendar' => true,
                                                            'enableTime' => true,
                                                            'dateFormat' => 'H:i',
                                                            'time_24hr'  => false,
                                                        ),
                                                    ),
                                                    array(
                                                        'id'        => 'time_based_dark_end',
                                                        'type'      => 'datetime',
                                                        'after'     => esc_html__('To', 'darkify'),
                                                        'placeholder'   => esc_html__('To', 'darkify'),
                                                        'settings'  => array(
                                                            'noCalendar' => true,
                                                            'enableTime' => true,
                                                            'dateFormat' => 'H:i',
                                                            'time_24hr'  => false,
                                                        ),
                                                    ),
                                                ),
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                            array(
                                'id'    => 'admin',
                                'title' => esc_html__('Admin', 'darkify'),
                                'icon'  => 'icofont-dashboard-web',
                                'fields' => array(
                                    // Intro line with a docs link, rendered plain above the section cards.
                                    array(
                                        'type'    => 'page_hint',
                                        'content' => sprintf(
                                            /* translators: %s: documentation link */
                                            __('Bring dark mode to the WordPress admin dashboard and editors. %s', 'darkify'),
                                            '<a href="https://darkifywp.com/docs/" target="_blank" rel="noopener noreferrer">' . esc_html__('View documentation', 'darkify') . '</a>'
                                        ),
                                    ),
                                    // ===== ADMIN APPEARANCE =====
                                    array(
                                        'type'          => 'heading',
                                        'title'         => esc_html__('Admin Appearance', 'darkify'),
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id'      => 'enable_admin_panel_dark_mode',
                                        'type'    => 'switcher',
                                        'title' => esc_html__('Admin Panel Dark Mode', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Enable dark mode in the WordPress admin dashboard.', 'darkify') .
                                            '</div>' .
                                            '' .
                                            '',
                                        'text_on'   => esc_html__('Enabled', 'darkify'),
                                        'text_off'  => esc_html__('Disabled', 'darkify'),
                                        'default'   => false,
                                        'text_width' => 100,
                                        // Commit instantly even though other fields depend on it.
                                        'auto_save' => true,
                                    ),

                                    /*
                                     * Admin palettes are separate from the frontend's `color_pallets`
                                     * and from each other. wp-admin is rendered by Dark Reader, which
                                     * derives a colour per source colour; a preset overrides only the
                                     * page background and text colour and lets it derive the rest.
                                     *
                                     * "Auto" is the default and the recommended value: pinning those two
                                     * flattens distinctions the derivation would otherwise keep, which
                                     * matters most on admin screens built by other plugins.
                                     *
                                     * Only the five presets darkify_apply_palette() actually defines are
                                     * listed. The block editor's toolbar dropdown offers more than that
                                     * and silently falls back for the rest — do not copy that list here.
                                     *
                                     * The swatch colours match the toolbar dropdown (.darkify-dropdown in
                                     * src/assets/css/client_main.css) so the same preset is recognisable
                                     * in both places. "Auto" shows a gradient: it has no single colour.
                                     *
                                     * These are the site-wide default. The editor's toolbar dropdown
                                     * still writes a per-user choice to localStorage, which wins for
                                     * that user — the same relationship the toolbar has always had.
                                     */
                                    array(
                                        'id'      => 'admin_panel_palette',
                                        'type'    => 'select',
                                        'title'   => esc_html__('Admin Panel Palette', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Color preset for the WordPress admin. Auto lets Darkify derive each color from the original.', 'darkify') .
                                            '</div>',
                                        'default' => 'auto',
                                        'options' => array(
                                            'auto' => array(
                                                'text'   => esc_html__('Auto (recommended)', 'darkify'),
                                                'swatch' => 'linear-gradient(135deg, #0f0f0f 0%, #3a3a3a 50%, #8f8f8f 100%)',
                                            ),
                                            'set1' => array(
                                                'text'   => esc_html__('Carbon Mist', 'darkify'),
                                                'swatch' => '#171717',
                                            ),
                                            'set3' => array(
                                                'text'   => esc_html__('Midnight Reverie', 'darkify'),
                                                'swatch' => '#4e478d',
                                            ),
                                            'set9' => array(
                                                'text'   => esc_html__('Verdant Depths', 'darkify'),
                                                'swatch' => '#073d2f',
                                            ),
                                            'set6' => array(
                                                'text'   => esc_html__('Celestial Tide', 'darkify'),
                                                'swatch' => '#144e78',
                                            ),
                                            'set10' => array(
                                                'text'   => esc_html__('Emberwood', 'darkify'),
                                                'swatch' => '#372911',
                                            ),

                                            /*
                                             * Pro presets, listed after every one that works here so the
                                             * usable list is not diluted at the top.
                                             *
                                             * These are not new upsell surface. The block editor's toolbar
                                             * dropdown has always offered all eleven names while
                                             * darkify_apply_palette() defines only five, so picking one of
                                             * these silently rendered Carbon Mist — they looked broken
                                             * rather than gated. Marking them is what makes that honest.
                                             *
                                             * `pro_only` is the whole mechanism: locked_option_keys() turns
                                             * it into the `pro_options` stamp the React admin renders as a
                                             * disabled, badged row, and strip_pro_keys() refuses to persist
                                             * the value even if a crafted request sends it.
                                             */
                                            'set2' => array(
                                                'text'     => esc_html__('Glacier Drift', 'darkify'),
                                                'swatch'   => '#123d52',
                                                'pro_only' => true,
                                            ),
                                            'set4' => array(
                                                'text'     => esc_html__('Indigo Veil', 'darkify'),
                                                'swatch'   => '#46598c',
                                                'pro_only' => true,
                                            ),
                                            'set5' => array(
                                                'text'     => esc_html__('Duskmire', 'darkify'),
                                                'swatch'   => '#403953',
                                                'pro_only' => true,
                                            ),
                                            'set7' => array(
                                                'text'     => esc_html__('Tidal Frost', 'darkify'),
                                                'swatch'   => '#286f8d',
                                                'pro_only' => true,
                                            ),
                                            'set8' => array(
                                                'text'     => esc_html__('Blue Bastion', 'darkify'),
                                                'swatch'   => '#244892',
                                                'pro_only' => true,
                                            ),
                                            'set11' => array(
                                                'text'     => esc_html__('Crimson Veil', 'darkify'),
                                                'swatch'   => '#440649',
                                                'pro_only' => true,
                                            ),
                                        ),
                                        'dependency' => array('enable_admin_panel_dark_mode', '==', 'true'),
                                    ),

                                    array(
                                        'id'      => 'block_editor_dark_mode',
                                        'type'    => 'switcher',
                                        'title' => esc_html__('Block Editor Dark Mode', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Enable the dark mode switch in the block editor to toggle between dark and light mode.', 'darkify') .
                                            '</div>'  .
                                            '' .
                                            '',
                                        'text_on'  => esc_html__('Enabled', 'darkify'),
                                        'text_off' => esc_html__('Disabled', 'darkify'),
                                        'default' => false,
                                        'text_width' => 100,
                                    ),
                                    array(
                                        'id'      => 'show_block_editor_palette',
                                        'type'    => 'switcher',
                                        'title'   => esc_html__('Show Block Editor Palette', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Show the palette dropdown in the block editor toolbar. When hidden, the editor always uses the Block Editor Palette chosen below.', 'darkify') .
                                            '</div>',
                                        'default' => true,
                                        'text_on'    => esc_html__('Show', 'darkify'),
                                        'text_off'   => esc_html__('Hide', 'darkify'),
                                        'text_width' => 100,
                                        'dependency' => array('block_editor_dark_mode', '==', 'true'),
                                    ),
                                    array(
                                        'id'      => 'block_editor_palette',
                                        'type'    => 'select',
                                        'title'   => esc_html__('Block Editor Palette', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Color preset for the block editor, independent of the rest of the admin.', 'darkify') .
                                            '</div>',
                                        'default' => 'auto',
                                        'options' => array(
                                            'auto' => array(
                                                'text'   => esc_html__('Auto (recommended)', 'darkify'),
                                                'swatch' => 'linear-gradient(135deg, #0f0f0f 0%, #3a3a3a 50%, #8f8f8f 100%)',
                                            ),
                                            'set1' => array(
                                                'text'   => esc_html__('Carbon Mist', 'darkify'),
                                                'swatch' => '#171717',
                                            ),
                                            'set3' => array(
                                                'text'   => esc_html__('Midnight Reverie', 'darkify'),
                                                'swatch' => '#4e478d',
                                            ),
                                            'set9' => array(
                                                'text'   => esc_html__('Verdant Depths', 'darkify'),
                                                'swatch' => '#073d2f',
                                            ),
                                            'set6' => array(
                                                'text'   => esc_html__('Celestial Tide', 'darkify'),
                                                'swatch' => '#144e78',
                                            ),
                                            'set10' => array(
                                                'text'   => esc_html__('Emberwood', 'darkify'),
                                                'swatch' => '#372911',
                                            ),

                                            /*
                                             * Pro presets, listed after every one that works here so the
                                             * usable list is not diluted at the top.
                                             *
                                             * These are not new upsell surface. The block editor's toolbar
                                             * dropdown has always offered all eleven names while
                                             * darkify_apply_palette() defines only five, so picking one of
                                             * these silently rendered Carbon Mist — they looked broken
                                             * rather than gated. Marking them is what makes that honest.
                                             *
                                             * `pro_only` is the whole mechanism: locked_option_keys() turns
                                             * it into the `pro_options` stamp the React admin renders as a
                                             * disabled, badged row, and strip_pro_keys() refuses to persist
                                             * the value even if a crafted request sends it.
                                             */
                                            'set2' => array(
                                                'text'     => esc_html__('Glacier Drift', 'darkify'),
                                                'swatch'   => '#123d52',
                                                'pro_only' => true,
                                            ),
                                            'set4' => array(
                                                'text'     => esc_html__('Indigo Veil', 'darkify'),
                                                'swatch'   => '#46598c',
                                                'pro_only' => true,
                                            ),
                                            'set5' => array(
                                                'text'     => esc_html__('Duskmire', 'darkify'),
                                                'swatch'   => '#403953',
                                                'pro_only' => true,
                                            ),
                                            'set7' => array(
                                                'text'     => esc_html__('Tidal Frost', 'darkify'),
                                                'swatch'   => '#286f8d',
                                                'pro_only' => true,
                                            ),
                                            'set8' => array(
                                                'text'     => esc_html__('Blue Bastion', 'darkify'),
                                                'swatch'   => '#244892',
                                                'pro_only' => true,
                                            ),
                                            'set11' => array(
                                                'text'     => esc_html__('Crimson Veil', 'darkify'),
                                                'swatch'   => '#440649',
                                                'pro_only' => true,
                                            ),
                                        ),
                                        'dependency' => array('block_editor_dark_mode', '==', 'true'),
                                    ),

                                    array(
                                        'id'      => 'classic_editor_dark_mode',
                                        'type'    => 'switcher',
                                        'title' => esc_html__('Classic Editor Dark Mode', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Enable the dark mode switch in the classic editor to toggle between dark and light modes.', 'darkify') .
                                            '</div>'  .
                                            '' .
                                            '',
                                        'text_on'  => esc_html__('Enabled', 'darkify'),
                                        'text_off' => esc_html__('Disabled', 'darkify'),

                                        'default' => false,
                                        'text_width' => 100,
                                    ),

                                    // ===== SCOPE & CUSTOMIZATION =====
                                    array(
                                        'type'          => 'heading',
                                        'title'         => esc_html__('Scope & Customization', 'darkify'),
                                        'section_start' => true,
                                        'dependency'    => array('enable_admin_panel_dark_mode', '==', 'true'),
                                    ),
                                    array(
                                        'id'          => 'disallowed_admin_pages',
                                        'type'        => 'textarea',
                                        'title' => esc_html__('Disallowed Pages', 'darkify'),
                                        'title_help' => '<div class="darkify-info-text">' .
                                            __('If an admin page URL looks like http://example.com/wp-admin/admin.php?page=darkify, then "darkify" is the page slug.', 'darkify') .
                                            '</div>' .
                                            '' .
                                            '',
                                        'placeholder' => esc_html__('Enter comma-separated page slugs. Example: darkify-dashboard, elementor', 'darkify'),
                                        'dependency'  => array('enable_admin_panel_dark_mode', '==', 'true'),
                                        'class' => 'only_pro'
                                    ),
                                    array(
                                        'id'       => 'admin_custom_css',
                                        'type'     => 'code_editor',
                                        'title' => esc_html__('Admin Dark Mode CSS', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('The CSS code will only be applied when admin dark mode is active.', 'darkify') .
                                            '</div>',
                                        'settings' => array(
                                            'theme'  => 'mbo',
                                            'mode'   => 'css',
                                        ),
                                        // Only relevant when admin dark mode is active — hide it while off.
                                        'dependency' => array('enable_admin_panel_dark_mode', '==', 'true'),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }
}
