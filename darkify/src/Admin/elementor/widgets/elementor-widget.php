<?php
// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Elementor Darkify widget.
 *
 * This class handles the creation of a custom Elementor widget 
 * for displaying Switcher in the Darkify Pro plugin.
 *
 * @since 1.0
 */
class Elementor_Darkify extends \Elementor\Widget_Base
{

    /**
     * Get widget name.
     *
     * Returns the widget name for use in Elementor.
     *
     * @since 1.0
     * @return string Widget name.
     */
    public function get_name()
    {
        return 'darkify';
    }

    /**
     * Get widget title.
     *
     * Returns the display title of the widget in Elementor.
     *
     * @since 1.0
     * @return string Widget title.
     */
    public function get_title()
    {
        return esc_html__('Dark Mode Switcher', 'darkify');
    }

    /**
     * Get widget icon.
     *
     * Returns the icon for the widget that will appear in the Elementor editor.
     *
     * @since 1.0
     * @return string Widget icon class.
     */
    public function get_icon()
    {
        return 'eicon-adjust';
    }

    public function get_style_depends()
    {
        return ['theme-classic', 'theme-expand', 'theme-inner-moon', 'theme-within', 'theme-orbit'];
    }

    /**
     * Get widget categories.
     *
     * Defines the category or categories the widget belongs to in Elementor.
     *
     * @since 1.0
     * @return array Widget categories.
     */
    public function get_categories()
    {
        return ['basic'];
    }

    /**
     * Every switch style, slug => display name, in the order the picker shows
     * them. The names are what the cards are labelled with: the picker used to
     * show artwork alone, which left "Dark Side", "Dark Inner" and "Half Sun"
     * to be told apart by eye.
     */
    private function darkify_style_labels()
    {
        return [
            'classic'    => __('Classic', 'darkify'),
            'expand'     => __('Expand', 'darkify'),
            'inner-moon' => __('Inner Moon', 'darkify'),
            'within'     => __('Within', 'darkify'),
            'orbit'      => __('Orbit', 'darkify'),
            'around'     => __('Around', 'darkify'),
            'dark-side'  => __('Dark Side', 'darkify'),
            'horizon'    => __('Horizon', 'darkify'),
            'eclipse'    => __('Eclipse', 'darkify'),
            'lightbulb'  => __('Lightbulb', 'darkify'),
            'dark-inner' => __('Dark Inner', 'darkify'),
            'half-sun'   => __('Half Sun', 'darkify'),
            'simple'     => __('Simple', 'darkify'),
            'duality'    => __('Duality', 'darkify'),
            'dual'       => __('Dual', 'darkify'),
            'shift'      => __('Shift', 'darkify'),
        ];
    }

    protected function _register_controls()
    {
        $style_labels = $this->darkify_style_labels();

        // ----------------------------------------  Switch Style ------------------------------
        $this->start_controls_section(
            'darkify__settings',
            array(
                'label' => esc_html__('Switch Style', 'darkify'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );
        $this->add_control(
            'style',
            [
                'type'        => 'darkify_switch',
                'options'     => array_keys($style_labels),
                'style_labels' => $style_labels,
                /*
                 * How many of the styles above may be chosen, counted from the
                 * start of the list; 0 would mean no limit. The control renders
                 * the rest as locked Pro previews and marks their inputs
                 * `disabled`.
                 *
                 * Sent from here rather than decided in the control's template
                 * or its stylesheet: the old test was `switchId > 3` against a
                 * slug, which is a NaN comparison and so never true, leaving a
                 * `pointer-events: none` CSS rule as the only guard — and that
                 * stops a mouse but not the keyboard, since the inputs are only
                 * clip-hidden and stay focusable.
                 */
                'unlocked'    => 5,
                /*
                 * Was `1`, which matches no option, so a fresh widget showed
                 * nothing selected while still rendering Classic (the shortcode
                 * maps the legacy `1` to it). The slug shows the selection and
                 * renders exactly the same switch; widgets that already stored
                 * `1` keep working through that same mapping.
                 */
                'default'     => 'classic',
                'id'          => 'darkify-switch',
                'classes'     => 'darkify-switch-style-elementor',
                'assets_url'  => DARKIFY_DIR_URL . 'src/Admin/assets/images/',
            ]
        );
        $this->end_controls_section();

        // ----------------------------------------  Size & Shape ------------------------------
        $this->start_controls_section(
            'darkify__size',
            array(
                'label' => esc_html__('Size & Shape', 'darkify'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );
        $this->add_control(
            'size',
            [
                'label' => __('Switch Size', 'darkify'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    '0.6' => __('XS', 'darkify'),
                    '0.8' => __('S', 'darkify'),
                    '1.0' => __('M', 'darkify'),
                    '1.2' => __('L', 'darkify'),
                    '1.4' => __('XL', 'darkify'),
                    '1.6' => __('XXL', 'darkify'),
                    'custom' => __('Custom', 'darkify'),
                ],
                'default'     => '1.0',
            ]
        );
        $this->add_control(
            'custom_size',
            [
                'label' => __('Custom Switch Size', 'darkify'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 40,
                'max' => 5000,
                'step' => 10,
                'default'     => 100,
                // The value is divided by 100 into `--darkify-switch-scale`, so
                // 100 is the switch's natural size rather than a pixel measure.
                'description' => __('Percent of the default size.', 'darkify'),
                'condition'   => array(
                    'size' => 'custom',
                ),
            ]
        );
        $this->add_control(
            'switch_border_width',
            [
                'label' => __('Border Width', 'darkify'),
                'type'  => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 10,
                    ],
                ],
                'default' => [
                    'size' => 0,
                ],
                'separator' => 'before',
                'selectors' => [
                    '{{WRAPPER}} .darkify_switch_style' => 'border-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        $this->add_control(
            'switch_border_radius',
            [
                'label' => __('Border Radius', 'darkify'),
                'type'  => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 7,
                ],
                'selectors' => [
                    '{{WRAPPER}} .darkify_switch_style' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        $this->add_responsive_control(
            'align',
            [
                'label'     => __('Alignment', 'darkify'),
                'type'      => \Elementor\Controls_Manager::CHOOSE,
                'options'   => [
                    'left'   => [
                        'title' => __('Left', 'darkify'),
                        'icon'  => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'darkify'),
                        'icon'  => 'eicon-text-align-center',
                    ],
                    'right'  => [
                        'title' => __('Right', 'darkify'),
                        'icon'  => 'eicon-text-align-right',
                    ],
                ],
                'toggle'    => true,
                'default'   => 'left',
                'separator' => 'before',
                'selectors' => [
                    '{{WRAPPER}}' => 'display: flex; justify-content: {{VALUE}};',
                ],
            ]
        );
        $this->end_controls_section();

        // ----------------------------------------  Colors ------------------------------
        /*
         * The colours come in light/dark pairs — background, icon and border —
         * and used to be six controls in a flat stack, each labelled "Light
         * Mode" or "Dark Mode" under its own heading, so the two halves of one
         * part sat apart while unrelated parts sat together. Elementor's own
         * tabs put one mode on screen at a time and let each control be named
         * for the part it colours, which is the same grouping the block editor
         * panel uses. Control names, defaults, conditions and selectors are
         * unchanged, so saved widgets and the CSS they emit are untouched.
         */
        $this->start_controls_section(
            'darkify__colors',
            array(
                'label' => esc_html__('Colors', 'darkify'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );
        $this->start_controls_tabs('darkify_color_modes');

        $this->start_controls_tab(
            'darkify_colors_light',
            ['label' => esc_html__('Light Mode', 'darkify')]
        );
        $this->add_control(
            'switch_bg_light',
            [
                'label' => __('Background', 'darkify'),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'default'   => '#121116',
                'selectors'  => [
                    '{{WRAPPER}} .darkify_switch_style, {{WRAPPER}} .switch-shift .theme-toggle .toggle-thumb'   => 'background-color: {{VALUE}};',
                ]
            ]
        );
        $this->add_control(
            'switch_icon_light',
            [
                'label' => __('Icon', 'darkify'),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'condition'   => array(
                    'style!' => ['duality', 'shift', 'dual'],
                ),
                'selectors'  => [
                    '{{WRAPPER}} .darkify_switch_style svg'   => 'color: {{VALUE}}; stroke: {{VALUE}};',
                    '{{WRAPPER}} .darkify_switch_style.switch-orbit .theme-toggle svg'   => 'fill: {{VALUE}};',
                ],
            ]
        );
        $this->add_control(
            'switch_border_light',
            [
                'label' => __('Border', 'darkify'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default'   => '#121116',
                'selectors' => [
                    '{{WRAPPER}} .darkify_switch_style' => 'border-color: {{VALUE}};',
                ],
            ]
        );
        $this->end_controls_tab();

        $this->start_controls_tab(
            'darkify_colors_dark',
            ['label' => esc_html__('Dark Mode', 'darkify')]
        );
        $this->add_control(
            'switch_bg_dark',
            [
                'label' => __('Background', 'darkify'),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors'  => [
                    '.darkify_dark_mode_enabled {{WRAPPER}} .darkify_switch_style, .darkify_dark_mode_enabled {{WRAPPER}} .switch-shift .theme-toggle .toggle-thumb'   => 'background-color: {{VALUE}};',
                ],
            ]
        );
        $this->add_control(
            'switch_icon_dark',
            [
                'label' => __('Icon', 'darkify'),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'default'   => '#121116',
                'condition'   => array(
                    'style!' => ['duality', 'shift', 'dual'],
                ),
                'selectors'  => [
                    '.darkify_dark_mode_enabled {{WRAPPER}} .darkify_switch_style svg'   => 'color: {{VALUE}}; stroke: {{VALUE}};',
                    '{{WRAPPER}} .darkify_switch_style.switch-orbit .theme-toggle .toggle-sun svg'   => 'fill: {{VALUE}};',
                ],
            ]
        );
        $this->add_control(
            'switch_border_dark',
            [
                'label' => __('Border', 'darkify'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => [
                    '.darkify_dark_mode_enabled {{WRAPPER}} .darkify_switch_style' => 'border-color: {{VALUE}};',
                ],
            ]
        );
        $this->end_controls_tab();

        $this->end_controls_tabs();
        $this->end_controls_section();
    }


    protected function render()
    {
        $settings = $this->get_settings_for_display();

        $style = isset($settings['style']) ? $settings['style'] : 1;
        $size = isset($settings['size']) ? $settings['size'] : 1;
        $custom_size = isset($settings['custom_size']) ? $settings['custom_size'] : 100;
        if ('custom' === $size) {
            $switcher_size = $custom_size;
        } else {
            $switcher_size = $size * 100;
        }

        echo do_shortcode(wp_sprintf('[darkify switch="%s" icon_size="40px" light_mode_bg="#121116" dark_mode_bg="#ffffff" light_mode_color="#ffffff" dark_mode_color="#121116" border="0px" border_light_color="#121119" border_dark_color="#ffffff" border_radius="7px" switch_size="%s"]', esc_attr($style), esc_attr($switcher_size)));
    }
}
