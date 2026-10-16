<?php

/**
 * ColorControl class for Color options.
 *
 * @link       https://themeatelier.net
 * @since      1.0.0
 *
 * @package darkify
 * @subpackage darkify/src/Admin/Views/ColorControl
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\Views;

use ThemeAtelier\Darkify\Admin\Schema\SchemaRegistry as Darkify;

class ColorControl
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
                'id'     => 'colors',
                'title'  => esc_html__('Colors', 'darkify'),
                'icon'   => 'icofont-paint',
                'fields' => array(

                    array(
                        'type' => 'section_tab',
                        'tabs' => array(
                            array(
                                'id'    => 'preset',
                                'title' => esc_html__('Preset', 'darkify'),
                                'icon'  => 'icofont-color-bucket',
                                'fields' => array(
                                    // Intro line, rendered plain above the section card.
                                    array(
                                        'type'    => 'page_hint',
                                        'content' => esc_html__('Pick a dark-mode color preset, then fine-tune any individual color for your site.', 'darkify'),
                                    ),
                                    // ===== CHOOSE A PRESET =====
                                    array(
                                        'type'          => 'heading',
                                        'title'         => esc_html__('Choose A Preset', 'darkify'),
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id'      => 'color_pallets',
                                        'type'    => 'palette',
                                        'class'    => 'darkify_preset',
                                        // Title lifted to the section heading above; the field keeps only its
                                        // short description (rendered above the swatch grid by PaletteField).
                                        'title_help' =>
                                            esc_html__('Choose from pre-made dark-mode color presets.', 'darkify'),

                                        'options' => array(
                                            'set1'  => array(
                                                'colors'    => array('#0F0F0F', '#4A4A4A', '#BEBEBE', '#171717', '#2D2D2D'),
                                                'name'     => esc_html__('Carbon Mist', 'darkify'),
                                            ),
                                            'set3'  => array(
                                                'colors'    => array('#211e3c', '#B1BBD8', '#302C57', '#4E478D', '#2A264D'),
                                                'name'     => esc_html__('Midnight Reverie', 'darkify'),
                                            ),
                                            'set9'  => array(
                                                'colors'    => array('#04261d', '#C1D2BB', '#021e16', '#073d2f', '#095541'),
                                                'name'     => esc_html__('Verdant Depths', 'darkify'),
                                            ),
                                            'set6'  => array(
                                                'colors'    => array('#082032', '#B5D9F3', '#061825', '#144E78', '#0E3755'),
                                                'name'     => esc_html__('Celestial Tide', 'darkify'),
                                            ),
                                            'set10'  => array(
                                                'colors'    => array('#171004', '#E0D2BD', '#211706', '#372911', '#5D4010'),
                                                'name'     => esc_html__('Emberwood', 'darkify'),
                                            ),
                                            'set2'  => array(
                                                'colors'    => array('#092635', '#BEE0F1', '#081E29', '#123d52', '#195979'),
                                                'name'     => esc_html__('Glacier Drift', 'darkify'),
                                                'pro_only'     => true,
                                            ),
                                            'set4'  => array(
                                                'colors'    => array('#1d253a', '#B1BBD8', '#2B3655', '#46598C', '#242F4C'),
                                                'name'     => esc_html__('Indigo Veil', 'darkify'),
                                                'pro_only'     => true,
                                            ),
                                            'set5'  => array(
                                                'colors'    => array('#1b1823', '#C6C1D5', '#352f44', '#403953', '#54496f'),
                                                'name'     => esc_html__('Duskmire', 'darkify'),
                                                'pro_only'     => true,
                                            ),
                                            'set7'  => array(
                                                'colors'    => array('#07161b', '#C5E6F0', '#0d242e', '#286F8D', '#19495A'),
                                                'name'     => esc_html__('Tidal Frost', 'darkify'),
                                                'pro_only'     => true,
                                            ),
                                            'set8'  => array(
                                                'colors'    => array('#15274C', '#ACC1EA', '#112244', '#244892', '#1a2f5c'),
                                                'name'     => esc_html__('Blue Bastion', 'darkify'),
                                                'pro_only'     => true,
                                            ),
                                            'set11'  => array(
                                                'colors'    => array('#19081b', '#c09dc2', '#210a24', '#440649', '#2c082f'),
                                                'name'     => esc_html__('Crimson Veil', 'darkify'),
                                                'pro_only'     => true,
                                            ),
                                        ),
                                        'default' => 'set1',
                                    ),
                                    array(
                                        'type'          => 'heading',
                                        'title'         => esc_html__('Customize Selected Preset', 'darkify'),
                                        'section_start' => true,
                                    ),

                                    // color pallate set 1
                                    array(
                                        'id'    => 'dark_mode_color_set1',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Background', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the background colors used when dark mode is enabled on your website.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'background'            => esc_html__('Primary', 'darkify'),
                                            'secondary_background'  => esc_html__('Secondary', 'darkify'),
                                        ),

                                        'dependency' => array('color_pallets', '==', 'set1', 'all'),
                                        'default' => array(
                                            'background' => '#0F0F0F',
                                            'secondary_background' => '#171717',
                                        ),
                                    ),
                                    array(
                                        'id'    => 'dark_mode_link_color_set1',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Text', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the text color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'dependency' => array('color_pallets', '==', 'set1', 'all'),
                                        'class' => 'only_pro',
                                        'options'   => array(
                                            'text' => esc_html__('Text Color', 'darkify'),
                                            'color' => esc_html__('Link Color', 'darkify'),
                                            'hover' => esc_html__('Link Hover Color', 'darkify'),
                                        ),
                                        'default' => array(
                                            'text' => '#BEBEBE',
                                            'color' => '#E7E7E7',
                                            'hover' => '#BEBEBE',
                                        ),
                                    ),
                                    array(
                                        'id'    => 'dark_mode_input_color_set1',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Input', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the input field colors of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'dependency' => array('color_pallets', '==', 'set1', 'all'),
                                        'class' => 'only_pro',
                                        'options' => array(
                                            'color'       => esc_html__('Input Text', 'darkify'),
                                            'placeholder' => esc_html__('Input Placeholder', 'darkify'),
                                            'background'  => esc_html__('Input Background', 'darkify'),
                                        ),

                                        'default' => array(
                                            'color' => '#BEBEBE',
                                            'placeholder' => '#BEBEBE',
                                            'background' => '#2D2D2D',
                                        ),
                                    ),
                                    array(
                                        'id'    => 'dark_mode_border_color_set1',
                                        'type'    => 'color',
                                        'title' => esc_html__('Border', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the border color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set1', 'all'),
                                        'default' => '#4A4A4A',
                                    ),
                                    array(
                                        'id'    => 'dark_mode_btn_color_set1',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Button', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the button colors of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'options' => array(
                                            'color'            => esc_html__('Text Color', 'darkify'),
                                            'background'       => esc_html__('Background', 'darkify'),
                                            'hover_color'      => esc_html__('Hover Text Color', 'darkify'),
                                            'hover_background' => esc_html__('Hover Background', 'darkify'),
                                            'border' => esc_html__('Border Color', 'darkify'),
                                            'hover_border' => esc_html__('Hover Border Color', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set1', 'all'),
                                        'default' => array(
                                            'color' => '#BEBEBE',
                                            'background' => '#4A4A4A',
                                            'hover_color' => '#BEBEBE',
                                            'hover_background' => '#2D2D2D',
                                            'border' => '#4A4A4A',
                                            'hover_border' => '#2D2D2D',
                                        ),
                                    ),
                                    // color pallate set 3
                                    array(
                                        'id'    => 'dark_mode_color_set3',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Background', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the background color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'options'   => array(
                                            'background' => esc_html__('Primary', 'darkify'),
                                            'secondary_background' => esc_html__('Secondary', 'darkify'),
                                        ),
                                        'dependency' => array('color_pallets', '==', 'set3', 'all'),

                                        'default' => array(
                                            'background' => '#211e3c',
                                            'secondary_background' => '#302C57',
                                        ),
                                    ),
                                    array(
                                        'id'    => 'dark_mode_link_color_set3',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Text', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the text and link colors of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'options' => array(
                                            'text'  => esc_html__('Text Color', 'darkify'),
                                            'color' => esc_html__('Link Color', 'darkify'),
                                            'hover' => esc_html__('Link Hover Color', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set3', 'all'),
                                        'default' => array(
                                            'text' => '#B1BBD8',
                                            'color' => '#8071fb',
                                            'hover' => '#B1BBD8',
                                        ),
                                    ),
                                    array(
                                        'id'    => 'dark_mode_input_color_set3',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Input', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the input field color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'options' => array(
                                            'color'       => esc_html__('Input Text', 'darkify'),
                                            'placeholder' => esc_html__('Input Placeholder', 'darkify'),
                                            'background'  => esc_html__('Input Background', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set3', 'all'),
                                        'default' => array(
                                            'color' => '#B1BBD8',
                                            'placeholder' => '#B1BBD8',
                                            'background' => '#2A264D',
                                        ),
                                    ),
                                    array(
                                        'id'    => 'dark_mode_border_color_set3',
                                        'type'    => 'color',
                                        'title' => esc_html__('Border', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the border color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set3', 'all'),
                                        'default' => '#4E478D',
                                    ),
                                    array(
                                        'id'    => 'dark_mode_btn_color_set3',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Button', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the button colors of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'color'            => esc_html__('Text Color', 'darkify'),
                                            'background'       => esc_html__('Background', 'darkify'),
                                            'hover_color'      => esc_html__('Hover Text Color', 'darkify'),
                                            'hover_background' => esc_html__('Hover Background', 'darkify'),
                                            'border' => esc_html__('Border Color', 'darkify'),
                                            'hover_border' => esc_html__('Hover Border Color', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set3', 'all'),
                                        'default' => array(
                                            'color' => '#4E478D',
                                            'background' => '#B1BBD8',
                                            'hover_color' => '#B1BBD8',
                                            'hover_background' => '#2A264D',
                                            'border' => '#B1BBD8',
                                            'hover_border' => '#2A264D',
                                        ),
                                    ),
                                    // color pallate set 6
                                    array(
                                        'id'    => 'dark_mode_color_set6',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Background', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the background color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'background'            => esc_html__('Primary', 'darkify'),
                                            'secondary_background'  => esc_html__('Secondary', 'darkify'),
                                        ),

                                        'dependency' => array('color_pallets', '==', 'set6', 'all'),
                                        'default' => array(
                                            'background' => '#082032',
                                            'secondary_background' => '#2C394B',
                                        ),
                                    ),
                                    array(
                                        'id'    => 'dark_mode_link_color_set6',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Text', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the text color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'text'  => esc_html__('Text Color', 'darkify'),
                                            'color' => esc_html__('Link Color', 'darkify'),
                                            'hover' => esc_html__('Link Hover Color', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set6', 'all'),
                                        'default' => array(
                                            'text' => '#B5D9F3',
                                            'color' => '#61bbff',
                                            'hover' => '#B5D9F3',
                                        ),
                                    ),

                                    array(
                                        'id'    => 'dark_mode_input_color_set6',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Input', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the input field color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'options' => array(
                                            'color'       => esc_html__('Input Text', 'darkify'),
                                            'placeholder' => esc_html__('Input Placeholder', 'darkify'),
                                            'background'  => esc_html__('Input Background', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set6', 'all'),
                                        'default' => array(
                                            'color' => '#B5D9F3',
                                            'placeholder' => '#B5D9F3',
                                            'background' => '#0E3755',
                                        ),
                                    ),

                                    array(
                                        'id'    => 'dark_mode_border_color_set6',
                                        'type'    => 'color',
                                        'title' => esc_html__('Border', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the border color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set6', 'all'),
                                        'default' => '#3B4D65',
                                    ),
                                    array(
                                        'id'    => 'dark_mode_btn_color_set6',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Button', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the button colors of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'color'            => esc_html__('Text Color', 'darkify'),
                                            'background'       => esc_html__('Background', 'darkify'),
                                            'hover_color'      => esc_html__('Hover Text Color', 'darkify'),
                                            'hover_background' => esc_html__('Hover Background', 'darkify'),
                                            'border' => esc_html__('Border Color', 'darkify'),
                                            'hover_border' => esc_html__('Hover Border Color', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set6', 'all'),

                                        'default' => array(
                                            'color' => '#B5D9F3',
                                            'background' => '#3B4D65',
                                            'hover_color' => '#B5D9F3',
                                            'hover_background' => '#144E78',
                                            'border' => '#3B4D65',
                                            'hover_border' => '#144E78',
                                        ),
                                    ),
                                    // color pallate set 9
                                    array(
                                        'id'    => 'dark_mode_color_set9',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Background', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the background color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'dependency' => array('color_pallets', '==', 'set9', 'all'),

                                        'options' => array(
                                            'background'            => esc_html__('Primary', 'darkify'),
                                            'secondary_background'  => esc_html__('Secondary', 'darkify'),
                                        ),

                                        'default' => array(
                                            'background' => '#04261d',
                                            'secondary_background' => '#021e16',
                                        ),
                                    ),
                                    array(
                                        'id'    => 'dark_mode_link_color_set9',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Text', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the text and link colors of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'text'  => esc_html__('Text Color', 'darkify'),
                                            'color' => esc_html__('Link Color', 'darkify'),
                                            'hover' => esc_html__('Link Hover Color', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set9', 'all'),
                                        'default' => array(
                                            'text' => '#C1D2BB',
                                            'color' => '#00d29a',
                                            'hover' => '#C1D2BB',
                                        ),
                                    ),

                                    array(
                                        'id'    => 'dark_mode_input_color_set9',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Input', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the input field colors of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'options' => array(
                                            'color'       => esc_html__('Input Text', 'darkify'),
                                            'placeholder' => esc_html__('Input Placeholder', 'darkify'),
                                            'background'  => esc_html__('Input Background', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set9', 'all'),
                                        'default' => array(
                                            'color' => '#C1D2BB',
                                            'placeholder' => '#C1D2BB',
                                            'background' => '#073d2f',
                                        ),
                                    ),

                                    array(
                                        'id'    => 'dark_mode_border_color_set9',
                                        'type'    => 'color',
                                        'title' => esc_html__('Border', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the border color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set9', 'all'),
                                        'default' => '#095541',
                                    ),
                                    array(
                                        'id'    => 'dark_mode_btn_color_set9',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Button', 'darkify'),
                                        'title_help' => '<div class="darkify-info-label">' .
                                            __('Set the button colors of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'color'            => esc_html__('Text Color', 'darkify'),
                                            'background'       => esc_html__('Background', 'darkify'),
                                            'hover_color'      => esc_html__('Hover Text Color', 'darkify'),
                                            'hover_background' => esc_html__('Hover Background', 'darkify'),
                                            'border' => esc_html__('Border Color', 'darkify'),
                                            'hover_border' => esc_html__('Hover Border Color', 'darkify'),
                                        ),
                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set9', 'all'),
                                        'default' => array(
                                            'color' => '#C1D2BB',
                                            'background' => '#095541',
                                            'hover_color' => '#C1D2BB',
                                            'hover_background' => '#073d2f',
                                            'border' => '#095541',
                                            'hover_border' => '#073d2f',
                                        ),
                                    ),
                                    // color pallate set 10
                                    array(
                                        'id'    => 'dark_mode_color_set10',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Background', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the background color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'background'            => esc_html__('Primary', 'darkify'),
                                            'secondary_background'  => esc_html__('Secondary', 'darkify'),
                                        ),

                                        'dependency' => array('color_pallets', '==', 'set10', 'all'),
                                        'default' => array(
                                            'background' => '#171004',
                                            'secondary_background' => '#211706',
                                        ),
                                    ),
                                    array(
                                        'id'    => 'dark_mode_link_color_set10',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Text', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the text color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'text'  => esc_html__('Text Color', 'darkify'),
                                            'color' => esc_html__('Link Color', 'darkify'),
                                            'hover' => esc_html__('Link Hover Color', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set10', 'all'),
                                        'default' => array(
                                            'text' => '#E0D2BD',
                                            'color' => '#e09525',
                                            'hover' => '#E0D2BD',
                                        ),
                                    ),

                                    array(
                                        'id'    => 'dark_mode_input_color_set10',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Input', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the input field colors of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'options' => array(
                                            'color'       => esc_html__('Input Text', 'darkify'),
                                            'placeholder' => esc_html__('Input Placeholder', 'darkify'),
                                            'background'  => esc_html__('Input Background', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set10', 'all'),

                                        'default' => array(
                                            'color' => '#E0D2BD',
                                            'placeholder' => '#E0D2BD',
                                            'background' => '#372911',
                                        ),
                                    ),

                                    array(
                                        'id'    => 'dark_mode_border_color_set10',
                                        'type'    => 'color',
                                        'title' => esc_html__('Border', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the border color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set10', 'all'),
                                        'default' => '#5D4010',
                                    ),
                                    array(
                                        'id'    => 'dark_mode_btn_color_set10',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Button', 'darkify'),
                                        'title' => esc_html__('Button', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the color of buttons on your website when dark mode is enabled.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'color'            => esc_html__('Button Text', 'darkify'),
                                            'background'       => esc_html__('Button Background', 'darkify'),
                                            'hover_color'      => esc_html__('Hover Text', 'darkify'),
                                            'hover_background' => esc_html__('Hover Background', 'darkify'),
                                            'border' => esc_html__('Border Color', 'darkify'),
                                            'hover_border' => esc_html__('Hover Border Color', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set10', 'all'),
                                        'default' => array(
                                            'color' => '#E0D2BD',
                                            'background' => '#5D4010',
                                            'hover_color' => '#E0D2BD',
                                            'hover_background' => '#372911',
                                            'border' => '#5D4010',
                                            'hover_border' => '#372911',
                                        ),
                                    ),
                                    // color pallate set 11
                                    array(
                                        'id'    => 'dark_mode_color_set11',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Background', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the background color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'background'           => esc_html__('Primary BG', 'darkify'),
                                            'secondary_background' => esc_html__('Secondary BG', 'darkify'),
                                        ),

                                        'dependency' => array('color_pallets', '==', 'set11', 'all'),

                                        'default' => array(
                                            'background' => '#19081b',
                                            'secondary_background' => '#210a24',
                                        ),
                                    ),
                                    array(
                                        'id'    => 'dark_mode_link_color_set11',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Text', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the text color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'text'  => esc_html__('Text Color', 'darkify'),
                                            'color' => esc_html__('Link Color', 'darkify'),
                                            'hover' => esc_html__('Link Hover Color', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set11', 'all'),

                                        'default' => array(
                                            'text' => '#c09dc2',
                                            'color' => '#c832d4',
                                            'hover' => '#c09dc2',
                                        ),
                                    ),

                                    array(
                                        'id'    => 'dark_mode_input_color_set11',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Input', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the input field colors of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',
                                        'options' => array(
                                            'color'       => esc_html__('Input Text', 'darkify'),
                                            'placeholder' => esc_html__('Input Placeholder', 'darkify'),
                                            'background'  => esc_html__('Input Background', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set11', 'all'),

                                        'default' => array(
                                            'color' => '#c09dc2',
                                            'placeholder' => '#c09dc2',
                                            'background' => '#2c082f',
                                        ),
                                    ),

                                    array(
                                        'id'    => 'dark_mode_border_color_set11',
                                        'type'    => 'color',
                                        'title' => esc_html__('Border', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the border color of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set11', 'all'),
                                        'default' => '#440649',
                                    ),
                                    array(
                                        'id'    => 'dark_mode_btn_color_set11',
                                        'type'    => 'color_group',
                                        'title' => esc_html__('Button', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the button colors of your website when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'options' => array(
                                            'color'            => esc_html__('Text Color', 'darkify'),
                                            'background'       => esc_html__('Background', 'darkify'),
                                            'hover_color'      => esc_html__('Hover Text Color', 'darkify'),
                                            'hover_background' => esc_html__('Hover Background', 'darkify'),
                                            'border' => esc_html__('Border Color', 'darkify'),
                                            'hover_border' => esc_html__('Hover Border Color', 'darkify'),
                                        ),

                                        'class' => 'only_pro',
                                        'dependency' => array('color_pallets', '==', 'set11', 'all'),

                                        'default' => array(
                                            'color' => '#c09dc2',
                                            'background' => '#440649',
                                            'hover_color' => '#c09dc2',
                                            'hover_background' => '#2c082f',
                                            'border' => '#440649',
                                            'hover_border' => '#2c082f',
                                        ),
                                    ),
                                )
                            ),

                            array(
                                'id'    => 'color-overrides',
                                'title' => esc_html__('Color Overrides', 'darkify'),
                                'icon'  => 'icofont-eye-dropper',
                                'fields' => array(
                                    // Intro line, rendered plain above the section card.
                                    array(
                                        'type'    => 'page_hint',
                                        'content' => esc_html__('Pin individual light-mode colors to an exact dark-mode replacement. Everything else keeps following your preset automatically — this is only for the handful of colors that need something different.', 'darkify'),
                                    ),

                                    array(
                                        'type'          => 'heading',
                                        'title'         => esc_html__('Replace Individual Colors', 'darkify'),
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id'    => 'color_overrides',
                                        'type'  => 'repeater',
                                        'class' => 'color_override_repeater repeater_pro_only',
                                        'title' => esc_html__('Replace Individual Colors', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Pin one light-mode color to an exact dark-mode color. Overrides are matched exactly against the color the element actually renders with — #ffffff and #fffffe are two separate rules — so a handful of colors can be pinned without changing how the rest of the site is handled.', 'darkify') .
                                            '</div>',

                                        'fields' => array(
                                            array(
                                                'id'    => 'light_color',
                                                'type'  => 'color',
                                                'title' => esc_html__('Light Mode Color', 'darkify'),
                                            ),
                                            array(
                                                'id'    => 'dark_color',
                                                'type'  => 'color',
                                                'title' => esc_html__('Dark Mode Color', 'darkify'),
                                            ),
                                            array(
                                                'id'    => 'override_scope',
                                                'type'  => 'select',
                                                'title' => esc_html__('Apply To', 'darkify'),
                                                'options' => array(
                                                    'all'        => esc_html__('Everywhere', 'darkify'),
                                                    'text'       => esc_html__('Text only', 'darkify'),
                                                    'icon'       => esc_html__('Icons only', 'darkify'),
                                                    'background' => esc_html__('Backgrounds only', 'darkify'),
                                                    'border'     => esc_html__('Borders only', 'darkify'),
                                                    'shadow'     => esc_html__('Shadows only', 'darkify'),
                                                ),
                                                'default' => 'all',
                                            ),
                                        ),

                                        'default' => array(
                                            array(
                                                'light_color'    => '',
                                                'dark_color'     => '',
                                                'override_scope' => 'all',
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                            array(
                                'id'    => 'scroll-bar',
                                'title' => esc_html__('Scroll Bar', 'darkify'),
                                'icon'  => 'icofont-scroll-double-down',
                                'fields' => array(
                                    // Intro line, rendered plain above the section card.
                                    array(
                                        'type'    => 'page_hint',
                                        'content' => esc_html__('Style the browser scrollbar to match dark mode.', 'darkify'),
                                    ),
                                    // ===== SCROLLBAR =====
                                    array(
                                        'type'          => 'heading',
                                        'title'         => esc_html__('Scrollbar', 'darkify'),
                                        'section_start' => true,
                                    ),
                                    array(
                                        'id' => 'enable_scrollbar_dark',
                                        'type'  => 'switcher',
                                        'title' => esc_html__('Enable Dark Mode on Scrollbar', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Enable dark mode styling for the website scrollbar.', 'darkify') .
                                            '</div>',

                                        'text_on'    => esc_html__('Enabled', 'darkify'),
                                        'text_off'    => esc_html__('Disabled', 'darkify'),
                                        'text_width' => 100,
                                        'default' => true,
                                    ),

                                    array(
                                        'id' => 'scrollbar_color',
                                        'type' => 'color_group',
                                        'title' => esc_html__('Scrollbar Color', 'darkify'),
                                        'title_help' =>
                                        '<div class="darkify-info-label">' .
                                            __('Set the track and thumb background colors of the scrollbar when dark mode is enabled.', 'darkify') .
                                            '</div>',

                                        'options' => array(
                                            'dark_mode_scrollbar_track_bg' => esc_html__('Track Background', 'darkify'),
                                            'dark_mode_scrollbar_thumb_bg' => esc_html__('Thumb Background', 'darkify'),
                                        ),

                                        'class' => 'only_pro',

                                        'default'   => array(
                                            'dark_mode_scrollbar_track_bg' => '#29292a',
                                            'dark_mode_scrollbar_thumb_bg' => '#52565a',
                                        ),
                                        'dependency'  => array('enable_scrollbar_dark', '==', 'true'),
                                    ),


                                )
                            ),


                        )
                    ),
                ),
            )
        );
    }
}
