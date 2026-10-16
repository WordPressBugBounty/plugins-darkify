<?php

/**
 * AdvancedSettings class for Advanced options.
 *
 * @link       https://themeatelier.net
 * @since      1.0.0
 *
 * @package     darkify
 * @subpackage darkify/src/Admin/Views/AdvancedSettings
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\Views;

use ThemeAtelier\Darkify\Admin\Schema\SchemaRegistry as Darkify;

class AdvancedSettings
{
    /**
     * Create Option fields for the setting options.
     *
     * @param string $prefix Option setting key prefix.
     * @return void
     */
    public static function options($prefix)
    {
        Darkify::createSection($prefix, array(
            'id'     => 'advanced',
            'title'  => esc_html__('Advanced', 'darkify'),
            'icon'   => 'icofont-settings',
            'fields' => array(
                array(
                    'type' => 'section_tab',
                    'tabs' => array(
                        array(
                            // Matches the key in darkify-react/src/lib/navIcons.js
                            // (and Pro's id for the same tab). The id used to be
                            // derived from the title, where sanitize_title() drops
                            // the slash and yields `htmlcss-restriction` — which is
                            // in neither map, so this tab always fell back to the
                            // generic gear icon.
                            'id'    => 'html-css-restriction',
                            'title' => esc_html__('HTML/CSS Restriction', 'darkify'),
                            'icon'  => 'icofont-code-alt',
                            'fields' => array(
                                // Intro line, rendered plain above the section card.
                                array(
                                    'type'    => 'page_hint',
                                    'content' => esc_html__('Restrict which HTML elements dark mode applies to, by tag, CSS class, or CSS ID.', 'darkify'),
                                ),
                                // ===== HTML/CSS RESTRICTION =====
                                array(
                                    'type'          => 'heading',
                                    'title'         => esc_html__('HTML/CSS Restriction', 'darkify'),
                                    'section_start' => true,
                                ),
                                // ALLOWED ELEMENTS
                                array(
                                    'id' => 'allowed_elements',
                                    'type'  => 'textarea',
                                    'title' => esc_html__('Allow Only Elements', 'darkify'),
                                    'title_help' =>
                                    '<div class="darkify-info-label">' .
                                        __('Dark mode will be applied only to these elements.', 'darkify') .
                                        '</div>',
                                    'placeholder' => esc_html__(
                                        'Enter comma-separated HTML tags, CSS classes, or CSS IDs. Example: div, #site-header, .my-footer',
                                        'darkify'
                                    ),


                                    'class' => 'only_pro',
                                ),

                                array(
                                    'id' => 'allowed_elements_force_to_correct',
                                    'type'  => 'switcher',
                                    'title' => esc_html__('Force Design Correction', 'darkify'),
                                    'title_help' =>
                                    '<div class="darkify-info-label">' .
                                        __('Force Darkify to preserve layout and readability when background or text colors are modified on allowed elements.', 'darkify') .
                                        '</div>',

                                    'class'   => 'switcher_pro_only',
                                    'text_on' => esc_html__('Yes', 'darkify'),
                                    'text_off' => esc_html__('No', 'darkify'),
                                    'default' => true,
                                ),

                                // DISALLOWED ELEMENTS
                                array(
                                    'id' => 'disallowed_elements',
                                    'type'  => 'textarea',
                                    'class' => 'only_pro',
                                    'title' => esc_html__('Disallowed Elements', 'darkify'),
                                    'title_help' =>
                                    '<div class="darkify-info-label">' .
                                        esc_html__('Dark mode will NOT be applied to the elements.', 'darkify') .
                                        '</div>',

                                    'placeholder' => esc_html__(
                                        'Enter comma-separated HTML tags, CSS classes, or CSS IDs. Example: div, #site-header, .my-footer',
                                        'darkify'
                                    ),

                                ),

                                array(
                                    'id' => 'disallowed_elements_force_to_correct',
                                    'type'  => 'switcher',
                                    'title' => esc_html__('Force To Correct', 'darkify'),
                                    'title_help' => '<div class="darkify-info-label">' .
                                        __('Force Darkify to keep the design correct when background or text colors do not work properly for disallowed elements.', 'darkify') .
                                        '</div>',

                                    'class'   => 'switcher_pro_only',
                                    'text_on' => esc_html__('Yes', 'darkify'),
                                    'text_off' => esc_html__('No', 'darkify'),
                                    'default' => true,
                                ),
                            )
                        ),

                        array(
                            'id'    => 'page-posts-restriction',
                            'title' => esc_html__('Page/Posts Restriction', 'darkify'),
                            'icon'  => 'icofont-eye-blocked',
                            'fields' => array(
                                // Intro line, rendered plain above the section cards.
                                array(
                                    'type'    => 'page_hint',
                                    'content' => esc_html__('Limit where dark mode and the floating switch run, by specific pages or posts.', 'darkify'),
                                ),
                                array(
                                    'type'          => 'heading',
                                    'title'         => esc_html__('Pages Restriction', 'darkify'),
                                    'section_start' => true,
                                ),
                                array(
                                    'id' => 'allowed_pages',
                                    'type'  => 'select',
                                    'title' => esc_html__('Allow Only Pages', 'darkify'),
                                    'title_help' =>
                                    '<div class="darkify-info-label">' .
                                        __('Dark mode will be applied only to the selected pages.', 'darkify') .
                                        '</div><div class="darkify-short-content">' .
                                        __('Choose only the pages where dark mode and the floating switch should work. Dark mode will not be applied to any other pages.', 'darkify') .
                                        '</div>',
                                    'placeholder' => esc_html__('Select pages', 'darkify'),

                                    'class'   => 'only_pro',
                                    'options'     => 'pages',
                                    'chosen'      => true,
                                    'multiple'    => true,
                                ),

                                array(
                                    'id' => 'disallowed_pages',
                                    'type'  => 'select',
                                    'title' => esc_html__('Disallowed Pages', 'darkify'),
                                    'title_help'  =>
                                    '<div class="darkify-info-label">' .
                                        __('Dark mode will not be applied to these pages.', 'darkify') .
                                        '</div><div class="darkify-short-content">' .
                                        __('Choose the pages where dark mode and the floating switch should not work. Dark mode will work on all other pages.', 'darkify') .
                                        '</div>',

                                    'class'   => 'only_pro',
                                    'placeholder' => esc_html__('Select pages', 'darkify'),
                                    'options'     => 'pages',
                                    'chosen'      => true,
                                    'multiple'    => true,
                                    'dependency' => array('allowed_pages', '==', ''),
                                ),

                                array(
                                    'type'          => 'heading',
                                    'title'         => esc_html__('Posts Restriction', 'darkify'),
                                    'section_start' => true,
                                ),
                                array(
                                    'id' => 'allowed_posts',
                                    'type'  => 'select',
                                    'title' => esc_html__('Allow Only Posts', 'darkify'),
                                    'title_help'  =>
                                    '<div class="darkify-info-label">' .
                                        __('Dark mode will be applied only to these posts.', 'darkify') .
                                        '</div><div class="darkify-short-content">' .
                                        __('Choose only the posts where dark mode and the floating switch should work. Dark mode will not be applied to any other posts.', 'darkify') .
                                        '</div>',

                                    'placeholder' => esc_html__('Select posts', 'darkify'),
                                    'class'   => 'only_pro',
                                    'options'     => 'posts',
                                    'chosen'      => true,
                                    'multiple'    => true,
                                ),
                                array(
                                    'id' => 'disallowed_posts',
                                    'type'  => 'select',
                                    'title' => esc_html__('Disallowed Posts', 'darkify'),
                                    'title_help'  =>
                                    '<div class="darkify-info-label">' .
                                        __('Dark mode will not be applied to these posts.', 'darkify') .
                                        '</div><div class="darkify-short-content">' .
                                        __('Choose the posts where dark mode and the floating switch cannot work. Other posts will be able to process dark mode.', 'darkify') .
                                        '</div>',
                                    'placeholder' => esc_html__('Select posts', 'darkify'),
                                    'class'   => 'only_pro',
                                    'options'     => 'posts',
                                    'chosen'      => true,
                                    'multiple'    => true,
                                    'dependency' => array('allowed_posts', '==', ''),
                                ),
                            )
                        ),
                        array(
                            'id'    => 'custom-css',
                            'title' => esc_html__('Custom CSS', 'darkify'),
                            'icon'  => 'icofont-file-css',
                            'fields' => array(
                                // Intro line, rendered plain above the section card.
                                array(
                                    'type'    => 'page_hint',
                                    'content' => esc_html__('Add your own CSS for dark mode, and CSS that applies in both modes.', 'darkify'),
                                ),
                                // ===== CUSTOM CSS =====
                                array(
                                    'type'          => 'heading',
                                    'title'         => esc_html__('Custom CSS', 'darkify'),
                                    'section_start' => true,
                                ),
                                array(
                                    'id'       => 'custom_css',
                                    'type'     => 'code_editor',
                                    'title' => esc_html__('Dark Mode CSS', 'darkify'),
                                    'title_help' =>
                                    '<div class="darkify-info-label">' .
                                        __('The CSS code will only be applied when dark mode is active.', 'darkify') .
                                        '</div>',

                                    'settings' => array(
                                        'theme'  => 'default',
                                        'mode'   => 'css',
                                    ),
                                ),
                                array(
                                    'id' => 'custom_css_rules',
                                    'type'  => 'switcher',
                                    'title' => esc_html__('CSS Rules', 'darkify'),

                                    'title_help' =>
                                    '<div class="darkify-info-label">' .
                                        __('CSS rules will be applied to all child elements of the specified CSS selectors.', 'darkify') .
                                        '</div>' .
                                        '<div class="darkify-short-content">' .
                                        __('Custom CSS selectors are automatically treated as Disallowed Elements. To override this behavior, use the :not-disallowed pseudo-class. Example: body:not-disallowed { ... }', 'darkify') .
                                        '</div>' .
                                        '' .
                                        '',

                                    'text_on'  => esc_html__('Yes', 'darkify'),
                                    'text_off' => esc_html__('No', 'darkify'),

                                    'class'   => 'switcher_pro_only',
                                ),
                                array(
                                    'id'       => 'normal_custom_css',
                                    'type'     => 'code_editor',
                                    'title' => esc_html__('Normal Mode CSS', 'darkify'),
                                    'title_help' =>
                                    '<div class="darkify-info-label">' .
                                        __('The CSS code will be applied in both normal mode and dark mode.', 'darkify') .
                                        '</div>' .
                                        '' .
                                        '',

                                    'settings' => array(
                                        'theme'  => 'default',
                                        'mode'   => 'css',
                                    ),
                                ),
                            )
                        ),
                        // "Extras" — the Frontend Iframe Dark Mode toggle lives here,
                        // matching Pro's layout (moved out of Controls → Frontend). The
                        // page's former Extras contents (Clean-up Data on Deletion +
                        // Backup and Restore) now live on their own Tools page — see
                        // Views/Tools.php.
                        array(
                            'id'    => 'extras',
                            'title' => esc_html__('Extras', 'darkify'),
                            'icon'  => 'icofont-holding-hands',
                            'fields' => array(
                                // Intro line, rendered plain above the section card.
                                array(
                                    'type'    => 'page_hint',
                                    'content' => esc_html__('Extra behaviors that don’t fit elsewhere.', 'darkify'),
                                ),
                                // ===== EXTRAS =====
                                array(
                                    'type'          => 'heading',
                                    'title'         => esc_html__('Extras', 'darkify'),
                                    'section_start' => true,
                                ),
                                array(
                                    'id'      => 'enable_frontend_iframe_dark_mode',
                                    'type'    => 'switcher',
                                    'title' => esc_html__('Frontend Iframe Dark Mode', 'darkify'),
                                    'title_help' =>
                                    '<div class="darkify-info-label">' .
                                        __('Apply dark mode to iframes when dark mode is active. Same-origin iframes receive full dark mode styling; cross-origin iframes (embedded third-party widgets) are dimmed to 60% brightness. Disable to keep all iframes at their original appearance.', 'darkify') .
                                        '</div>' .
                                        '' .
                                        '',
                                    'text_on'  => esc_html__('Enabled', 'darkify'),
                                    'text_off' => esc_html__('Disabled', 'darkify'),
                                    'text_width' => 100,
                                    'default' => true,
                                ),
                            )
                        ),
                    )
                ),
            ),
        ));
    }
}
