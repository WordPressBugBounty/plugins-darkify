<?php

/**
 * SwitcherStyle class for Switcher options.
 *
 * @link       https://themeatelier.net
 * @since      1.0.0
 *
 * @package darkify
 * @subpackage darkify/src/Admin/Views/SwitcherStyle
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\Views;

use ThemeAtelier\Darkify\Admin\Schema\SchemaRegistry as Darkify;

class SwitcherStyle
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
                // See Control.php: ids are pinned so routes never follow the
                // translated title.
                'id'            => 'switches',
                'title'         => esc_html__('Switches', 'darkify'),
                'icon'          => 'icofont-toggle-on',
                'fields' => array(

                    array(
                        'type' => 'section_tab',
                        'tabs' => array(
                            array(
                                'id'    => 'floating-switch',
                                'title' => esc_html__('Floating Switch', 'darkify'),
                                'icon'  => 'icofont-ui-theme',
                                'fields' => array(
                                    // Intro line, rendered plain above the section cards.
                                    array(
                                        'type'    => 'page_hint',
                                        'content' => sprintf(
                                            /* translators: %s: "Open the live preview" action link */
                                            esc_html__('Design the floating dark-mode switch and choose where it sits. %s to see changes instantly.', 'darkify'),
                                            '<a href="#" data-darkify-open-preview>' . esc_html__('Open the live preview', 'darkify') . '</a>'
                                        ),
                                    ),
                                    array(
                                        'title'         => esc_html__('Floating Switch Styles', 'darkify'),
                                        'type'          => 'heading',
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id' => 'enable_dark_switcher',
                                        'type' => 'image_select',
                                        'title' => esc_html__('Switch Toggler', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Choose the default floating switch toggler.', 'darkify') .
                                            '</div>',
                                        'class' => 'icon_select ',
                                        'options' => array(
                                            'orbit' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/orbit.svg',
                                                'text'            => esc_html__('Orbit', 'darkify'),
                                                'option_demo_url' => '',
                                            ),

                                            'classic' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/classic.svg',
                                                'text'            => esc_html__('Classic', 'darkify'),
                                                'option_demo_url' => '',
                                            ),

                                            'expand' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/expand.svg',
                                                'text'            => esc_html__('Expand', 'darkify'),
                                                'option_demo_url' => '',
                                            ),

                                            'inner-moon' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/inner-moon.svg',
                                                'text'            => esc_html__('Inner Moon', 'darkify'),
                                                'option_demo_url' => '',
                                            ),

                                            'within' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/within.svg',
                                                'text'            => esc_html__('Within', 'darkify'),
                                                'option_demo_url' => '',
                                            ),

                                            'around' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/around.svg',
                                                'text'            => esc_html__('Around', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'dark-side' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/dark-side.svg',
                                                'text'            => esc_html__('Dark Side', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'horizon' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/horizon.svg',
                                                'text'            => esc_html__('Horizon', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'eclipse' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/eclipse.svg',
                                                'text'            => esc_html__('Eclipse', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'lightbulb' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/lightbulb.svg',
                                                'text'            => esc_html__('Lightbulb', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'dark-inner' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/dark-inner.svg',
                                                'text'            => esc_html__('Dark Inner', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'half-sun' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/half-sun.svg',
                                                'text'            => esc_html__('Half Sun', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'simple' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/simple.svg',
                                                'text'            => esc_html__('Simple', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),
                                            'duality' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/duality.svg',
                                                'text'            => esc_html__('Duality', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),
                                            'dual' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/dual.svg',
                                                'text'            => esc_html__('Dual', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),
                                            'shift' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/shift.svg',
                                                'text'            => esc_html__('Shift', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),
                                        ),
                                        'default' => 'orbit',
                                    ),
                                    array(
                                        'id'          => 'switch_size',
                                        'type'        => 'button_set',
                                        'title'       => esc_html__('Switch Size', 'darkify'),
                                        'title_help' => '<div class="darkify-info-label">' . esc_html__('Choose the size of the floating toggle switch as you need.', 'darkify') . '</div>',
                                        'options'     => array(
                                            '0.6' => esc_html__('XS', 'darkify'),
                                            '0.8' => esc_html__('S', 'darkify'),
                                            '1' => esc_html__('M', 'darkify'),
                                            '1.2' => esc_html__('L', 'darkify'),
                                            '1.4'  => esc_html__('XL', 'darkify'),
                                            '1.6'  => esc_html__('XXL', 'darkify'),
                                            'custom'  => esc_html__('Custom', 'darkify'),
                                        ),
                                        'default' => '1',
                                    ),
                                    array(
                                        'id'          => 'switch_size_custom',
                                        'type'        => 'slider',
                                        'title'       => esc_html__('Custom Switch Size', 'darkify'),
                                        'title_help' => '<div class="darkify-info-label">' . esc_html__('Scale in percentage relative to normal size.', 'darkify') . '</div>',
                                        'default' => '100',
                                        'min'     => 40,
                                        'max'     => 300,
                                        'dependency'    => array('switch_size', '==', 'custom')
                                    ),
                                    array(
                                        'id' => 'switch_background',
                                        'type' => 'color_group',
                                        'title' => esc_html__('Switch Background', 'darkify'),
                                        'title_help' => '<div class="darkify-info-label">' .
                                            __('Customize the floating switch background to match your design.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'switch_light_mode_bg' => esc_html__('Light Mode', 'darkify'),
                                            'switch_dark_mode_bg'  => esc_html__('Dark Mode', 'darkify'),
                                        ),
                                        'default'   => array(
                                            'switch_light_mode_bg' => '#121116',
                                            'switch_dark_mode_bg' => '#ffffff',
                                        )
                                    ),
                                    array(
                                        'id' => 'switch_icon_color',
                                        'type' => 'color_group',
                                        'title' => esc_html__('Switch Icon Color', 'darkify'),
                                        'title_help' => '<div class="darkify-info-label">' .
                                            __('Customize the floating switch icon color as needed.', 'darkify') .
                                            '</div>',

                                        'options'   => array(
                                            'switch_light_mode_color' => esc_html__('Light Mode', 'darkify'),
                                            'switch_dark_mode_color' => esc_html__('Dark Mode', 'darkify'),
                                        ),
                                        'default'   => array(
                                            'switch_light_mode_color' => '#ffffff',
                                            'switch_dark_mode_color' => '#121116',
                                        ),
                                    ),
                                    array(
                                        'id'         => 'switch_border',
                                        'type'       => 'border',
                                        'title' => esc_html__('Switch Border', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Customize the floating switch border and border radius.', 'darkify') .
                                            '</div>' .
                                            '' .
                                            '',
                                        'color'      => true,
                                        'style'      => false,
                                        'color2'     => false,
                                        // The switch has a separate border colour per mode: `color` is
                                        // light, `color3` is dark. Both are read by
                                        // DarkifyUtils::generateSwitchStyles() and the [darkify]
                                        // shortcode, so the dark one needs a control of its own.
                                        'color3'     => true,
                                        'all'        => true,
                                        'radius'     => true,
                                        'default'    => array(
                                            'color'  => '#121116',
                                            'color3' => '#ffffff',
                                            'all'    => 0,
                                            'radius' => 7,
                                        ),
                                    ),
                                    array(
                                        'title'         => esc_html__('Switch Positioning', 'darkify'),
                                        'type'          => 'heading',
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id'     => 'switcher_button_position',
                                        'type'   => 'fieldset',
                                        'fields' => array(
                                            array(
                                                'id'          => 'dark_mode_switch_position',
                                                // Corner tiles rather than a button row: the choice is
                                                // spatial, so the diagram reads faster than the words.
                                                // Presentation only — the stored keys, the default and
                                                // the dependencies below are unchanged.
                                                'type'        => 'position_select',
                                                'title'       => esc_html__('Switch Position', 'darkify'),
                                                'title_help' => '<div class="darkify-info-label">' . esc_html__('Choose the screen position where the Floating Switch should be displayed.', 'darkify') . '</div>',
                                                'options' => array(
                                                    'bottom_right' => esc_html__('Bottom Right', 'darkify'),
                                                    'bottom_left'  => esc_html__('Bottom Left', 'darkify'),
                                                    'top_right'    => esc_html__('Top Right', 'darkify'),
                                                    'top_left'     => esc_html__('Top Left', 'darkify'),
                                                ),
                                                'default' => 'bottom_right',
                                            ),
                                            array(
                                                'id'          => 'switch_position_top_right',
                                                'type'        => 'spacing',
                                                'title'       => esc_html__('Margin From Top Right', 'darkify'),
                                                'title_help' => '<div class="darkify-info-label">' . esc_html__('Customize default margin from the top right of the Floating Switch.', 'darkify') . '</div>',
                                                'left'        => false,
                                                'bottom'      => false,
                                                'units'   => ['px'],
                                                'default'  => array(
                                                    'top'    => '40',
                                                    'right'  => '40',
                                                ),
                                                'dependency' => array('dark_mode_switch_position', '==', 'top_right'),
                                            ),
                                            array(
                                                'id'          => 'switch_position_top_left',
                                                'type'        => 'spacing',
                                                'title'       => esc_html__('Margin From Top Left', 'darkify'),
                                                'title_help' => '<div class="darkify-info-label">' . esc_html__('Customize default margin from the top left of the Floating Switch.', 'darkify') . '</div>',
                                                'right'       => false,
                                                'bottom'      => false,
                                                'units'   => ['px'],
                                                'default'   => array(
                                                    'top'     => '40',
                                                    'left'    => '40',
                                                ),
                                                'dependency' => array('dark_mode_switch_position', '==', 'top_left'),
                                            ),
                                            array(
                                                'id'          => 'switch_position_bottom_right',
                                                'type'        => 'spacing',
                                                'title'       => esc_html__('Margin From Bottom Right', 'darkify'),
                                                'title_help' => '<div class="darkify-info-label">' . esc_html__('Customize default margin from the bottom right of the Floating Switch.', 'darkify') . '</div>',
                                                'left'        => false,
                                                'top'         => false,
                                                'units'   => ['px'],
                                                'default'     => array(
                                                    'bottom'    => '40',
                                                    'right'     => '40',
                                                ),
                                                'dependency' => array('dark_mode_switch_position', '==', 'bottom_right'),
                                            ),
                                            array(
                                                'id'          => 'switch_position_bottom_left',
                                                'type'        => 'spacing',
                                                'title'       => esc_html__('Margin From Bottom Left', 'darkify'),
                                                'title_help' => '<div class="darkify-info-label">' . esc_html__('Customize default margin from the bottom left of the Floating Switch.', 'darkify') . '</div>',
                                                'right'       => false,
                                                'top'         => false,
                                                'units'   => ['px'],
                                                'default'  => array(
                                                    'bottom'  => '40',
                                                    'left'    => '40',
                                                ),
                                                'dependency'  => array('dark_mode_switch_position', '==', 'bottom_left'),
                                            ),
                                        ),
                                    ),
                                    array(
                                        'id'          => 'enable_absolute_position',
                                        'type'        => 'switcher',
                                        'title'       => esc_html__('Absolute Switch Position', 'darkify'),
                                        'text_on'     => esc_html__('Enabled', 'darkify'),
                                        'text_off'    => esc_html__('Disabled', 'darkify'),
                                        'text_width'  => '100',
                                        'title_help' => '<div class="darkify-info-label">' . esc_html__('Enable to make the floating switch scroll from its position with page scrolling.', 'darkify') . '</div>',
                                    ),
                                    array(
                                        'id'          => 'enable_switch_dragging',
                                        'type'        => 'switcher',
                                        'title'       => esc_html__('Draggable Position Change', 'darkify'),
                                        'text_on'     => esc_html__('Enabled', 'darkify'),
                                        'text_off'    => esc_html__('Disabled', 'darkify'),
                                        'text_width'  => '100',
                                        'title_help' => '<div class="darkify-info-label">' . esc_html__('Allow/Disallow users to change the floating switch position by dragging to where they want.', 'darkify') . '</div>',

                                        // Free-only: dragging is a Pro feature, so the row renders
                                        // locked with an upgrade prompt. Pro has no such marker.
                                        'class'     => 'switcher_pro_only',
                                    ),
                                    array(
                                        'title'         => esc_html__('Switch Extra', 'darkify'),
                                        'type'          => 'heading',
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id'          => 'enable_switch_attention',
                                        'type'        => 'switcher',
                                        'title'       => esc_html__('Switch Attention Effect', 'darkify'),
                                        'text_on'     => esc_html__('Enabled', 'darkify'),
                                        'text_off'    => esc_html__('Disabled', 'darkify'),
                                        'text_width'  => '100',
                                        'title_help'  => '<div class="darkify-info-label">' . esc_html__('Enable an animation to draw visitor attention to the floating dark mode switch.', 'darkify') . '</div>',
                                    ),
                                    array(
                                        'id'          => 'switch_attention_effect',
                                        'type'        => 'button_set',
                                        'title'       => esc_html__('Attention Effect Style', 'darkify'),
                                        'title_help'  => '<div class="darkify-info-label">' . esc_html__('Choose the animation style for the attention effect. Hover pauses the animation.', 'darkify') . '</div>',
                                        'options'     => array(
                                            'pulse'    => esc_html__('Pulse', 'darkify'),
                                            'radiance' => array('label' => esc_html__('Radiance', 'darkify'), 'disabled' => true),
                                            'leap'     => array('label' => esc_html__('Leap', 'darkify'), 'disabled' => true),
                                            'swing'    => array('label' => esc_html__('Swing', 'darkify'), 'disabled' => true),
                                            'drift'    => array('label' => esc_html__('Drift', 'darkify'), 'disabled' => true),
                                            'nudge'    => array('label' => esc_html__('Nudge', 'darkify'), 'disabled' => true),
                                        ),
                                        'default'     => 'pulse',
                                        'dependency'  => array('enable_switch_attention', '==', 'true'),
                                    ),
                                    array(
                                        'id'     => 'tooltip_on_floating_switch',
                                        'type'   => 'fieldset',
                                        'fields' => array(
                                            array(
                                                'id'          => 'floating_switch_width',
                                                'type'        => 'switcher',
                                                'title' => esc_html__('Tooltip on Floating Switch', 'darkify'),
                                                'title_help' =>
                                                '<div class="darkify-info-label">' .
                                                    __('Display a tooltip hint on the dark mode floating switch.', 'darkify') .
                                                    '</div>' .
                                                    '' .
                                                    '',
                                                'text_on'  => esc_html__('Enabled', 'darkify'),
                                                'text_off' => esc_html__('Disabled', 'darkify'),
                                                'text_width' => 100,
                                                'default'     => false,
                                            ),
                                            array(
                                                'id'          => 'tooltip_position',
                                                'type'        => 'button_set',
                                                'title' => esc_html__('Tooltip Position', 'darkify'),
                                                'title_help' =>
                                                '<div class="darkify-info-label">' .
                                                    __('Choose where the tooltip should appear relative to the floating switch.', 'darkify') .
                                                    '</div>' .
                                                    '' .
                                                    '',
                                                'options' => array(
                                                    'left'   => esc_html__('Left', 'darkify'),
                                                    'right'  => esc_html__('Right', 'darkify'),
                                                    'top'    => esc_html__('Top', 'darkify'),
                                                    'bottom' => esc_html__('Bottom', 'darkify'),
                                                ),
                                                'default'     => 'left',
                                                // Only the two sides that point into the page are offered for
                                                // each corner — a tooltip on the other two would open off the
                                                // edge of the screen. Switch Position lives in a different
                                                // fieldset, hence `options_by` rather than a `dependency`.
                                                'options_by'  => array(
                                                    'field'   => 'switcher_button_position.dark_mode_switch_position',
                                                    'default' => 'bottom_right',
                                                    'map'     => array(
                                                        'bottom_right' => array('top', 'left'),
                                                        'bottom_left'  => array('top', 'right'),
                                                        'top_right'    => array('bottom', 'left'),
                                                        'top_left'     => array('bottom', 'right'),
                                                    ),
                                                ),
                                                'dependency'  => array('floating_switch_width', '==', 'true'),
                                            ),
                                            array(
                                                'id'          => 'tooltip_text',
                                                'type'        => 'text',
                                                'title' => esc_html__('Tooltip Text', 'darkify'),
                                                'title_help' =>
                                                '<div class="darkify-info-label">' .
                                                    __('Add tooltip text for floating switch toggler.', 'darkify') .
                                                    '</div>' .
                                                    '' .
                                                    '',
                                                'default'     => esc_html__('Toggle Dark Mode', 'darkify'),
                                                'dependency'  => array('floating_switch_width', '==', 'true'),
                                            ),

                                            array(
                                                'id' => 'tooltip_color',
                                                'type' => 'color_group',
                                                'title' => esc_html__('Tooltip Color', 'darkify'),
                                                'title_help' =>
                                                '<div class="darkify-info-label">' .
                                                    __('Customize the tooltip colors and appearance.', 'darkify') .
                                                    '</div>',
                                                'options'   => array(
                                                    'tooltip_background_color' => esc_html__('Background', 'darkify'),
                                                    'tooltip_text_color' => esc_html__('Color', 'darkify'),
                                                ),
                                                'default' => array(
                                                    'tooltip_background_color' => '#142434',
                                                    'tooltip_text_color' => '#B0CBE7',
                                                ),
                                                'dependency'  => array('floating_switch_width', '==', 'true'),
                                            ),

                                        ),
                                    ),
                                    array(
                                        'title'         => esc_html__('Switch Visibility', 'darkify'),
                                        'type'          => 'heading',
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id'      => 'hide_on_desktop',
                                        'type'    => 'switcher',
                                        'title' => esc_html__('Hide Dark Mode Switch on Desktop', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Hide the dark mode floating switch when users are browsing on a desktop or laptop.', 'darkify') .
                                            '</div>' .
                                            '' .
                                            '',

                                        'text_on'  => esc_html__('Yes', 'darkify'),
                                        'text_off' => esc_html__('No', 'darkify'),

                                    ),
                                    array(
                                        'id'     => 'hide_on_mobile',
                                        'type'   => 'fieldset',
                                        'fields' => array(
                                            array(
                                                'id'      => 'hide_dark_mode_on_mobile',
                                                'type'    => 'switcher',
                                                'title' => esc_html__('Hide Dark Mode on Mobile', 'darkify'),
                                                'title_help' =>
                                                '<div class="darkify-info-label">' .
                                                    __('Hide the dark mode floating switch when users are browsing on mobile devices.', 'darkify') .
                                                    '</div>' .
                                                    '' .
                                                    '',

                                                'text_on'  => esc_html__('Yes', 'darkify'),
                                                'text_off' => esc_html__('No', 'darkify'),

                                            ),
                                            array(
                                                'id'      => 'type_of_hide_by',
                                                'type'    => 'button_set',
                                                'title' => esc_html__('Hide Detection Method', 'darkify'),

                                                'title_help' =>
                                                '<div class="darkify-info-label">' .
                                                    __('Choose how mobile devices are detected: by User Agent, by Screen Size, or use Both for maximum accuracy.', 'darkify') .
                                                    '</div>' .
                                                    '' .
                                                    '',

                                                'options' => array(
                                                    'user_agent'  => esc_html__('User Agent', 'darkify'),
                                                    'screen_size' => esc_html__('Screen Size', 'darkify'),
                                                    'both'        => esc_html__('Both', 'darkify'),
                                                ),

                                                'default' => 'both',
                                                'dependency' => array('hide_dark_mode_on_mobile', '==', 'true'),
                                            ),
                                        ),
                                    ),
                                    array(
                                        'title'         => esc_html__('Mobile Switch', 'darkify'),
                                        'type'          => 'heading',
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id'          => 'dark_switcher_switch_in_mobile',
                                        'type'        => 'switcher',
                                        'title'       => esc_html__('Different Switch in Mobile', 'darkify'),
                                        'title_help' => '<div class="darkify-info-label">' . esc_html__('Should the Floating Switch be displayed on different in mobile?', 'darkify') . '</div>',
                                        'text_on'    => esc_html__('Yes', 'darkify'),
                                        'text_off'    => esc_html__('No', 'darkify'),
                                    ),
                                    array(
                                        'id' => 'enable_dark_switcher_in_mobile',
                                        'type' => 'image_select',
                                        'title' => esc_html__('Switch Toggler', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Choose the default floating switch toggler.', 'darkify') .
                                            '</div>' .
                                            '' .
                                            '',
                                        'class' => 'icon_select ',
                                        'options' => array(
                                            'orbit' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/orbit.svg',
                                                'text'            => esc_html__('Orbit', 'darkify'),
                                                'option_demo_url' => '',
                                            ),

                                            'classic' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/classic.svg',
                                                'text'            => esc_html__('Classic', 'darkify'),
                                                'option_demo_url' => '',
                                            ),

                                            'expand' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/expand.svg',
                                                'text'            => esc_html__('Expand', 'darkify'),
                                                'option_demo_url' => '',
                                            ),

                                            'inner-moon' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/inner-moon.svg',
                                                'text'            => esc_html__('Inner Moon', 'darkify'),
                                                'option_demo_url' => '',
                                            ),

                                            'within' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/within.svg',
                                                'text'            => esc_html__('Within', 'darkify'),
                                                'option_demo_url' => '',
                                            ),

                                            'around' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/around.svg',
                                                'text'            => esc_html__('Around', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'dark-side' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/dark-side.svg',
                                                'text'            => esc_html__('Dark Side', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'horizon' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/horizon.svg',
                                                'text'            => esc_html__('Horizon', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'eclipse' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/eclipse.svg',
                                                'text'            => esc_html__('Eclipse', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'lightbulb' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/lightbulb.svg',
                                                'text'            => esc_html__('Lightbulb', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'dark-inner' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/dark-inner.svg',
                                                'text'            => esc_html__('Dark Inner', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'half-sun' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/half-sun.svg',
                                                'text'            => esc_html__('Half Sun', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),

                                            'simple' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/simple.svg',
                                                'text'            => esc_html__('Simple', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),
                                            'duality' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/duality.svg',
                                                'text'            => esc_html__('Duality', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),
                                            'dual' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/dual.svg',
                                                'text'            => esc_html__('Dual', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),
                                            'shift' => array(
                                                'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/shift.svg',
                                                'text'            => esc_html__('Shift', 'darkify'),
                                                'option_demo_url' => '',
                                                'pro_only'        => true,
                                            ),
                                        ),
                                        'default' => 'orbit',
                                        'dependency' => array('dark_switcher_switch_in_mobile', '==', 'true'),
                                    ),
                                    array(
                                        'id'          => 'switch_size_in_mobile',
                                        'type'        => 'button_set',
                                        'title'       => esc_html__('Switch Size', 'darkify'),
                                        'title_help' => '<div class="darkify-info-label">' . esc_html__('Choose the size of the floating toggle switch as you need.', 'darkify') . '</div>',
                                        'options'     => array(
                                            '0.6' => esc_html__('XS', 'darkify'),
                                            '0.8' => esc_html__('S', 'darkify'),
                                            '1' => esc_html__('M', 'darkify'),
                                            '1.2' => esc_html__('L', 'darkify'),
                                            '1.4'  => esc_html__('XL', 'darkify'),
                                            '1.6'  => esc_html__('XXL', 'darkify'),
                                            'custom'  => esc_html__('Custom', 'darkify'),
                                        ),
                                        'default' => '1',
                                        'dependency' => array('dark_switcher_switch_in_mobile', '==', 'true'),
                                    ),
                                    array(
                                        'id'          => 'switch_size_custom_in_mobile',
                                        'type'        => 'slider',
                                        'title'       => esc_html__('Custom Switch Size', 'darkify'),
                                        'title_help' => '<div class="darkify-info-label">' . esc_html__('Scale in percentage relative to normal size.', 'darkify') . '</div>',
                                        'default' => '100',
                                        'min'     => 40,
                                        'max'     => 300,
                                        'dependency'    => array('switch_size_in_mobile|dark_switcher_switch_in_mobile', '==|==', 'custom|true')
                                    ),
                                    array(
                                        'id' => 'switch_background_in_mobile',
                                        'type' => 'color_group',
                                        'title' => esc_html__('Switch Background', 'darkify'),
                                        'title_help' => '<div class="darkify-info-label">' .
                                            __('Customize the floating switch background to match your design.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'switch_light_mode_bg' => esc_html__('Light Mode', 'darkify'),
                                            'switch_dark_mode_bg'  => esc_html__('Dark Mode', 'darkify'),
                                        ),
                                        'default'   => array(
                                            'switch_light_mode_bg' => '#121116',
                                            'switch_dark_mode_bg' => '#ffffff',
                                        ),
                                        'dependency' => array('dark_switcher_switch_in_mobile', '==', 'true'),
                                    ),
                                    array(
                                        'id' => 'switch_icon_color_in_mobile',
                                        'type' => 'color_group',
                                        'title' => esc_html__('Switch Icon Color', 'darkify'),
                                        'title_help' => '<div class="darkify-info-label">' .
                                            __('Customize the floating switch icon color as needed.', 'darkify') .
                                            '</div>',

                                        'options'   => array(
                                            'switch_light_mode_color' => esc_html__('Light Mode', 'darkify'),
                                            'switch_dark_mode_color' => esc_html__('Dark Mode', 'darkify'),
                                        ),
                                        'default'   => array(
                                            'switch_light_mode_color' => '#ffffff',
                                            'switch_dark_mode_color' => '#121116',
                                        ),
                                        'dependency' => array('dark_switcher_switch_in_mobile', '==', 'true'),
                                    ),
                                    array(
                                        'id'         => 'switch_border_in_mobile',
                                        'type'       => 'border',
                                        'title' => esc_html__('Switch Border', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Customize the floating switch border and border radius.', 'darkify') .
                                            '</div>' .
                                            '' .
                                            '',
                                        'color'      => true,
                                        'style'      => false,
                                        'color2'     => false,
                                        // Light (`color`) / dark (`color3`) border colours — see the
                                        // desktop Switch Border field above.
                                        'color3'     => true,
                                        'all'        => true,
                                        'radius'     => true,
                                        'default'    => array(
                                            'color'  => '#121116',
                                            'color3' => '#ffffff',
                                            'all'    => 0,
                                            'radius' => 7,
                                        ),
                                        'dependency' => array('dark_switcher_switch_in_mobile', '==', 'true'),
                                    ),

                                    array(
                                        'id'          => 'enable_switch_attention_in_mobile',
                                        'type'        => 'switcher',
                                        'title'       => esc_html__('Switch Attention Effect', 'darkify'),
                                        'text_on'     => esc_html__('Enabled', 'darkify'),
                                        'text_off'    => esc_html__('Disabled', 'darkify'),
                                        'text_width'  => '100',
                                        'title_help'  => '<div class="darkify-info-label">' . esc_html__('Enable an animation to draw visitor attention to the floating dark mode switch.', 'darkify') . '</div>',
                                        'dependency'  => array('dark_switcher_switch_in_mobile', '==', 'true'),
                                    ),
                                    array(
                                        'id'          => 'switch_attention_effect_in_mobile',
                                        'type'        => 'button_set',
                                        'title'       => esc_html__('Attention Effect Style', 'darkify'),
                                        'title_help'  => '<div class="darkify-info-label">' . esc_html__('Choose the animation style for the attention effect. Hover pauses the animation.', 'darkify') . '</div>',
                                        'options'     => array(
                                            'pulse'    => esc_html__('Pulse', 'darkify'),
                                            'radiance' => array('label' => esc_html__('Radiance', 'darkify'), 'disabled' => true),
                                            'leap'     => array('label' => esc_html__('Leap', 'darkify'), 'disabled' => true),
                                            'swing'    => array('label' => esc_html__('Swing', 'darkify'), 'disabled' => true),
                                            'drift'    => array('label' => esc_html__('Drift', 'darkify'), 'disabled' => true),
                                            'nudge'    => array('label' => esc_html__('Nudge', 'darkify'), 'disabled' => true),
                                        ),
                                        'default'     => 'pulse',
                                        'dependency'  => array('enable_switch_attention_in_mobile|dark_switcher_switch_in_mobile', '==|==', 'true|true'),
                                    ),
                                    array(
                                        'id'     => 'different_switch_in_mobile',
                                        'type'   => 'fieldset',
                                        'fields' => array(
                                            array(
                                                'id'          => 'switch_position_different_in_mobile',
                                                'type'        => 'switcher',
                                                'title'       => esc_html__('Different Switch Position in Mobile', 'darkify'),
                                                'title_help' => '<div class="darkify-info-label">' . esc_html__('Should the Floating Switch be displayed on different position in mobile?', 'darkify') . '</div>',
                                                'text_on'    => esc_html__('Yes', 'darkify'),
                                                'text_off'    => esc_html__('No', 'darkify'),

                                            ),
                                            array(
                                                'id'          => 'switch_position_in_mobile',
                                                'type'        => 'position_select',
                                                'title'       => esc_html__('Switch Position in Mobile', 'darkify'),
                                                'title_help' => '<div class="darkify-info-label">' . esc_html__('Choose the screen position where the Floating Switch should be displayed in mobile.', 'darkify') . '</div>',
                                                'options' => array(
                                                    'bottom_right' => esc_html__('Bottom Right', 'darkify'),
                                                    'bottom_left'  => esc_html__('Bottom Left', 'darkify'),
                                                    'top_right'    => esc_html__('Top Right', 'darkify'),
                                                    'top_left'     => esc_html__('Top Left', 'darkify'),
                                                ),

                                                'default' => 'bottom_right',
                                                'dependency' => array('switch_position_different_in_mobile', '==', 'true'),
                                            ),
                                            array(
                                                'id'          => 'switch_position_top_right_in_mobile',
                                                'type'        => 'spacing',
                                                'title'       => esc_html__('Margin From Top Right in Mobile', 'darkify'),
                                                'title_help' => '<div class="darkify-info-label">' . esc_html__('Customize default margin from the top right of the Floating Switch.', 'darkify') . '</div>',
                                                'left'        => false,
                                                'bottom'      => false,
                                                'units'   => ['px'],
                                                'default'  => array(
                                                    'top'       => '40',
                                                    'right'     => '40',
                                                ),
                                                'dependency' => array('switch_position_different_in_mobile|switch_position_in_mobile', '==|==', 'true|top_right'),
                                            ),
                                            array(
                                                'id'          => 'switch_position_top_left_in_mobile',
                                                'type'        => 'spacing',
                                                'title'       => esc_html__('Margin From Top Left in Mobile', 'darkify'),
                                                'title_help' => '<div class="darkify-info-label">' . esc_html__('Customize default margin from the top left of the Floating Switch.', 'darkify') . '</div>',
                                                'right'       => false,
                                                'bottom'      => false,
                                                'units'   => ['px'],
                                                'default'  => array(
                                                    'top'     => '40',
                                                    'left'    => '40',
                                                ),
                                                'dependency' => array('switch_position_different_in_mobile|switch_position_in_mobile', '==|==', 'true|top_left'),
                                            ),
                                            array(
                                                'id'          => 'switch_position_bottom_right_in_mobile',
                                                'type'        => 'spacing',
                                                'title'       => esc_html__('Margin From Bottom Right in Mobile', 'darkify'),
                                                'title_help' => '<div class="darkify-info-label">' . esc_html__('Customize default margin from the bottom right of the Floating Switch.', 'darkify') . '</div>',
                                                'left'        => false,
                                                'top'         => false,
                                                'units'   => ['px'],
                                                'default'  => array(
                                                    'bottom'    => '40',
                                                    'right'     => '40',
                                                ),
                                                'dependency' => array('switch_position_different_in_mobile|switch_position_in_mobile', '==|==', 'true|bottom_right'),
                                            ),
                                            array(
                                                'id'          => 'switch_position_bottom_left_in_mobile',
                                                'type'        => 'spacing',
                                                'title'       => esc_html__('Margin From Bottom Left in Mobile', 'darkify'),
                                                'title_help' => '<div class="darkify-info-label">' . esc_html__('Customize default margin from the bottom left of the Floating Switch.', 'darkify') . '</div>',
                                                'right'       => false,
                                                'top'         => false,
                                                'units'   => ['px'],
                                                'default'  => array(
                                                    'bottom'    => '40',
                                                    'left'      => '40',
                                                ),
                                                'dependency'  => array('switch_position_different_in_mobile|switch_position_in_mobile', '==|==', 'true|bottom_left'),
                                            ),
                                        ),
                                    ),
                                )
                            ),
                            array(
                                'id'    => 'other-switches',
                                'title' => esc_html__('Other Switches', 'darkify'),
                                'icon'  => 'icofont-toggle-off',
                                'fields' => array(
                                    // Intro line, rendered plain above the section cards.
                                    array(
                                        'type'    => 'page_hint',
                                        'content' => esc_html__('Add dark-mode switches inside menus or anywhere on your site with a shortcode, and register custom elements as switches.', 'darkify'),
                                    ),
                                    array(
                                        'title'         => esc_html__('Menu Switch', 'darkify'),
                                        'type'          => 'heading',
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id'     => 'show_in_menu',
                                        'type'   => 'fieldset',
                                        // Divide the master toggle from the per-switch group below it.
                                        'class'  => 'fieldset-divided',
                                        'fields' => array(
                                            array(
                                                'id'      => 'enable_switch_in_menu',
                                                'type'    => 'switcher',
                                                'title' => esc_html__('Enable Menu Switch', 'darkify'),

                                                'title_help' =>
                                                '<div class="darkify-info-label">' .
                                                    __('Display a dark mode switch inside a WordPress menu.', 'darkify') .
                                                    '</div>' .
                                                    '' .
                                                    '',

                                                'text_on'  => esc_html__('Yes', 'darkify'),
                                                'text_off' => esc_html__('No', 'darkify'),

                                            ),
                                            array(
                                                'id'     => 'select_menus',
                                                'type'   => 'group',
                                                'title' => esc_html__('Menu Switches', 'darkify'),

                                                'title_help' =>
                                                '<div class="darkify-info-label">' .
                                                    __('Choose the menu and position for your switch.', 'darkify') .
                                                    '</div>' .
                                                    '' .
                                                    '',

                                                'add_more_text' => sprintf(
                                                    /* translators: %s: URL to the Darkify Pro pricing page. */
                                                    __(
                                                        'Want to use the switch in an unlimited number of menus? <a target="_blank" href="%s"><strong>Upgrade to Pro</strong></a>.',
                                                        'darkify'
                                                    ),
                                                    esc_url(DARKIFY_DEMO_URL . 'pricing/?ref=1')
                                                ),

                                                // Same accordion-title pattern as Pro (menu name + placement
                                                // once chosen, numbered "Menu Switch #1" fallback before
                                                // that) — kept in sync even though Free is capped at one row.
                                                'accordion_title_number' => false,
                                                'accordion_title_auto' => true,
                                                'accordion_title_by' => array('switch_in_menu_location', 'select_menu_position'),
                                                'accordion_title_by_prefix' => ' — ',
                                                'accordion_title_require' => 'switch_in_menu_location',
                                                'row_title' => esc_html__('Menu Switch', 'darkify'),
                                                'class' => 'select_menus',
                                                'empty_title' => esc_html__('No menu switches configured', 'darkify'),
                                                'empty_desc'  => esc_html__('Add a switch and choose the WordPress menu where it should appear.', 'darkify'),
                                                'button_title' => esc_html__('Add Menu Switch', 'darkify'),
                                                'max' => 1,

                                                'fields' => array(
                                                    array(
                                                        'id'          => 'switch_in_menu_location',
                                                        'type'        => 'select',
                                                        'class' =>    'select_menu_location',
                                                        'title' => esc_html__('Menu', 'darkify'),
                                                        'title_help' =>
                                                        '<div class="darkify-info-label">' .
                                                            esc_html__('Choose the WordPress menu where this switch should appear.', 'darkify') .
                                                            '</div>' .
                                                            '' .
                                                            '',
                                                        'placeholder' => esc_html__('Choose a menu…', 'darkify'),
                                                        'empty_message' => esc_html__(
                                                            'You have not created a menu yet. If you are using a Full Site Editing (FSE) theme, copy the shortcode below and paste it where you want the switch to appear.',
                                                            'darkify'
                                                        ),
                                                        'options' => 'menus',
                                                    ),
                                                    array(
                                                        'id'          => 'select_menu_position',
                                                        'type'        => 'button_set',
                                                        'title' => esc_html__('Switch Placement', 'darkify'),
                                                        'title_help' =>
                                                        '<div class="darkify-info-label">' .
                                                            esc_html__('Choose whether the dark mode switch appears at the beginning or end of the selected menu.', 'darkify') .
                                                            '</div>' .
                                                            '' .
                                                            '',

                                                        // Options relabeled for clarity; keys unchanged
                                                        // ('after'/'before') so existing saved rows keep
                                                        // their exact stored placement.
                                                        'options' => array(
                                                            'after'  => esc_html__('End of Menu', 'darkify'),
                                                            'before' => esc_html__('Beginning of Menu', 'darkify'),
                                                        ),

                                                        'default'   => 'after',
                                                    ),
                                                    array(
                                                        'id'          => 'darkify_menu_shortcode_helper',
                                                        'type'        => 'menu_switch_shortcode',
                                                        'title' => esc_html__('Shortcode', 'darkify'),
                                                        'title_help' =>
                                                        '<div class="darkify-info-label">' .
                                                            __('Automatically generated from the settings above.', 'darkify') .
                                                            '</div>' .
                                                            '' .
                                                            '',
                                                        'default'     => '[darkify switch="1"]',
                                                    ),
                                                ),
                                                'dependency'  => array('enable_switch_in_menu', '==', 'true'),
                                            ),
                                        ),
                                    ),
                                    array(
                                        'title'         => esc_html__('Shortcode', 'darkify'),
                                        'type'          => 'heading',
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id'      => 'shortcode_use_global_switch_settings',
                                        'type'    => 'switcher',
                                        'title'   => esc_html__('Use Global Switch Settings', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Use the same switch style, size, and colors as your site-wide floating switch. Turn this off to customize this shortcode independently, without changing the floating switch.', 'darkify') .
                                            '</div>',
                                        'text_on'    => esc_html__('Yes', 'darkify'),
                                        'text_off'    => esc_html__('No', 'darkify'),
                                        // Off by default so the customizable style controls are visible.
                                        'default' => false,
                                    ),
                                    // Mirrors the Floating Switch's own style controls (same field
                                    // ids, nested here) so `buildSwitcherShortcode()` on the React
                                    // side can generate this shortcode's styling exactly the same
                                    // way it does for the global one. `pro_only` flags on the style
                                    // options match the Floating Switch tab's own list so this panel
                                    // can't be used to bypass the tier gate on switch styles.
                                    array(
                                        'id'         => 'shortcode_custom_switch_settings',
                                        'type'       => 'fieldset',
                                        'title'      => esc_html__('Custom Switch Style', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('These settings apply only to this shortcode, not your site-wide floating switch.', 'darkify') .
                                            '</div>',
                                        'dependency' => array('shortcode_use_global_switch_settings', '==', 'false'),
                                        'fields'     => array(
                                            array(
                                                'id' => 'enable_dark_switcher',
                                                'type' => 'image_select',
                                                'title' => esc_html__('Switch Style', 'darkify'),
                                                'class' => 'icon_select ',
                                                'options' => array(
                                                    'orbit' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/orbit.svg',
                                                        'text'            => esc_html__('Orbit', 'darkify'),
                                                        'option_demo_url' => '',
                                                    ),
                                                    'classic' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/classic.svg',
                                                        'text'            => esc_html__('Classic', 'darkify'),
                                                        'option_demo_url' => '',
                                                    ),
                                                    'expand' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/expand.svg',
                                                        'text'            => esc_html__('Expand', 'darkify'),
                                                        'option_demo_url' => '',
                                                    ),
                                                    'inner-moon' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/inner-moon.svg',
                                                        'text'            => esc_html__('Inner Moon', 'darkify'),
                                                        'option_demo_url' => '',
                                                    ),
                                                    'within' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/within.svg',
                                                        'text'            => esc_html__('Within', 'darkify'),
                                                        'option_demo_url' => '',
                                                    ),
                                                    'around' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/around.svg',
                                                        'text'            => esc_html__('Around', 'darkify'),
                                                        'option_demo_url' => '',
                                                        'pro_only'        => true,
                                                    ),
                                                    'dark-side' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/dark-side.svg',
                                                        'text'            => esc_html__('Dark Side', 'darkify'),
                                                        'option_demo_url' => '',
                                                        'pro_only'        => true,
                                                    ),
                                                    'horizon' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/horizon.svg',
                                                        'text'            => esc_html__('Horizon', 'darkify'),
                                                        'option_demo_url' => '',
                                                        'pro_only'        => true,
                                                    ),
                                                    'eclipse' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/eclipse.svg',
                                                        'text'            => esc_html__('Eclipse', 'darkify'),
                                                        'option_demo_url' => '',
                                                        'pro_only'        => true,
                                                    ),
                                                    'lightbulb' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/lightbulb.svg',
                                                        'text'            => esc_html__('Lightbulb', 'darkify'),
                                                        'option_demo_url' => '',
                                                        'pro_only'        => true,
                                                    ),
                                                    'dark-inner' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/dark-inner.svg',
                                                        'text'            => esc_html__('Dark Inner', 'darkify'),
                                                        'option_demo_url' => '',
                                                        'pro_only'        => true,
                                                    ),
                                                    'half-sun' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/half-sun.svg',
                                                        'text'            => esc_html__('Half Sun', 'darkify'),
                                                        'option_demo_url' => '',
                                                        'pro_only'        => true,
                                                    ),
                                                    'simple' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/simple.svg',
                                                        'text'            => esc_html__('Simple', 'darkify'),
                                                        'option_demo_url' => '',
                                                        'pro_only'        => true,
                                                    ),
                                                    'duality' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/duality.svg',
                                                        'text'            => esc_html__('Duality', 'darkify'),
                                                        'option_demo_url' => '',
                                                        'pro_only'        => true,
                                                    ),
                                                    'dual' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/dual.svg',
                                                        'text'            => esc_html__('Dual', 'darkify'),
                                                        'option_demo_url' => '',
                                                        'pro_only'        => true,
                                                    ),
                                                    'shift' => array(
                                                        'image'           => DARKIFY_DIR_URL . 'src/Admin/assets/images/shift.svg',
                                                        'text'            => esc_html__('Shift', 'darkify'),
                                                        'option_demo_url' => '',
                                                        'pro_only'        => true,
                                                    ),
                                                ),
                                                'default' => 'orbit',
                                            ),
                                            array(
                                                'id'          => 'switch_size',
                                                'type'        => 'button_set',
                                                'title'       => esc_html__('Switch Size', 'darkify'),
                                                'options'     => array(
                                                    '0.6' => esc_html__('XS', 'darkify'),
                                                    '0.8' => esc_html__('S', 'darkify'),
                                                    '1' => esc_html__('M', 'darkify'),
                                                    '1.2' => esc_html__('L', 'darkify'),
                                                    '1.4'  => esc_html__('XL', 'darkify'),
                                                    '1.6'  => esc_html__('XXL', 'darkify'),
                                                    'custom'  => esc_html__('Custom', 'darkify'),
                                                ),
                                                'default' => '1',
                                            ),
                                            array(
                                                'id'          => 'switch_size_custom',
                                                'type'        => 'slider',
                                                'title'       => esc_html__('Custom Switch Size', 'darkify'),
                                                'title_help' => '<div class="darkify-info-desc">' . esc_html__('Scale in percentage relative to normal size.', 'darkify') . '</div>',
                                                'default' => '100',
                                                'min'     => 40,
                                                'max'     => 300,
                                                'dependency'    => array('switch_size', '==', 'custom'),
                                            ),
                                            array(
                                                'id' => 'switch_background',
                                                'type' => 'color_group',
                                                'title' => esc_html__('Switch Background', 'darkify'),
                                                'options'   => array(
                                                    'switch_light_mode_bg' => esc_html__('Light Mode', 'darkify'),
                                                    'switch_dark_mode_bg' => esc_html__('Dark Mode', 'darkify'),
                                                ),
                                                'default'   => array(
                                                    'switch_light_mode_bg' => '#121116',
                                                    'switch_dark_mode_bg' => '#ffffff',
                                                ),
                                            ),
                                            array(
                                                'id' => 'switch_icon_color',
                                                'type' => 'color_group',
                                                'title' => esc_html__('Switch Icon Color', 'darkify'),
                                                'options'   => array(
                                                    'switch_light_mode_color' => esc_html__('Light Mode', 'darkify'),
                                                    'switch_dark_mode_color' => esc_html__('Dark Mode', 'darkify'),
                                                ),
                                                'default'   => array(
                                                    'switch_light_mode_color' => '#ffffff',
                                                    'switch_dark_mode_color' => '#121116',
                                                ),
                                                'dependency'  => array('enable_dark_switcher', 'not-any', 'duality,dual,shift'),
                                            ),
                                            array(
                                                'id' => 'switch_text_color',
                                                'type' => 'color_group',
                                                'title' => esc_html__('Switch Text Color', 'darkify'),
                                                'options'   => array(
                                                    'switch_text_light_mode_color' => esc_html__('Light Mode', 'darkify'),
                                                    'switch_text_dark_mode_color' => esc_html__('Dark Mode', 'darkify'),
                                                ),
                                                'default'   => array(
                                                    'switch_text_light_mode_color' => '#121116',
                                                    'switch_text_dark_mode_color' => '#ffffff',
                                                ),
                                                'dependency'  => array('enable_dark_switcher', 'any', 'dual'),
                                            ),
                                            array(
                                                'id'         => 'switch_border',
                                                'type'       => 'border',
                                                'title' => esc_html__('Switch Border', 'darkify'),
                                                'color'      => true,
                                                'style'      => false,
                                                'color2'     => false,
                                                'color3'     => true,
                                                'all'        => true,
                                                'radius'     => true,
                                                'default'    => array(
                                                    'color'  => '#121116',
                                                    'color3' => '#ffffff',
                                                    'all'    => 0,
                                                    'radius' => 7,
                                                ),
                                            ),
                                        ),
                                    ),
                                    array(
                                        'id'    => 'switcher_shortcode',
                                        'type'    => 'switcher_shortcode',
                                        'title' => esc_html__('Dark Mode Switch Shortcode', 'darkify'),
                                        'title_help' => '<div class="darkify-info-content">' .
                                            __('Use the shortcode below to display a dark mode switch anywhere WordPress shortcodes are supported.', 'darkify') .
                                            '</div>' .
                                            '' .
                                            '',
                                        'dependency'  => array('enable_dark_switcher', 'not-any', 'orbit,duality,dual,shift'),
                                    ),
                                    array(
                                        'id'    => 'switcher_shortcode_v2',
                                        'type'    => 'switcher_shortcode_v2',
                                        'title' => esc_html__('Dark Mode Switch Shortcode', 'darkify'),
                                        'title_help' => '<div class="darkify-info-content">' .
                                            __('Use the shortcode below to display a dark mode switch anywhere WordPress shortcodes are supported.', 'darkify') .
                                            '</div>' .
                                            '' .
                                            '',
                                        'dependency'  => array('enable_dark_switcher', 'any', 'orbit,duality,dual,shift'),
                                    ),
                                    array(
                                        'title'         => esc_html__('Custom Switch Selectors', 'darkify'),
                                        'type'          => 'heading',
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id'          => 'alternative_dark_mode_switcher',
                                        'type'        => 'text',
                                        'title' => esc_html__('Alternative Switch Selectors', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Enter CSS selectors for elements that should toggle dark mode when clicked. Separate multiple selectors with commas.', 'darkify') .
                                            '</div>' .
                                            '' .
                                            '',
                                        'placeholder' => esc_html__('e.g., .my-dark-switch, #dark-mode-toggle', 'darkify'),
                                        'validate'    => 'css_selectors',
                                    ),
                                )
                            ),
                        )
                    ),
                )
            )
        );
    }
}
