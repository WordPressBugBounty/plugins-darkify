<?php

/**
 * Media class for Media Options.
 *
 * @link       https://themeatelier.net
 * @since      1.0.0
 *
 * @package darkify
 * @subpackage darkify/src/Admin/Views/Media
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\Views;

use ThemeAtelier\Darkify\Admin\Schema\SchemaRegistry as Darkify;

class Media
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
        'id'     => 'media',
        'title'  => esc_html__('Media', 'darkify'),
        'icon'   => 'icofont-multimedia',
        'fields' => array(

          array(
            'type' => 'section_tab',
            'tabs' => array(

              array(
                'id'    => 'image-controls',
                'title' => esc_html__('Image Controls', 'darkify'),
                'icon'  => 'icofont-file-image',
                'fields' => array(
                  // Intro line, rendered plain above the section cards.
                  array(
                    'type'    => 'page_hint',
                    'content' => esc_html__('Control how images adapt when dark mode is active.', 'darkify'),
                  ),
                  // ===== BRIGHTNESS =====
                  array(
                    'type'          => 'heading',
                    'title'         => esc_html__('Brightness', 'darkify'),
                    'section_start' => true,
                  ),
                  // Low Brightness
                  array(
                    'id'     => 'brightness',
                    'type'   => 'fieldset',
                    'fields' => array(
                      array(
                        'id' => 'enable_low_image_brightness',
                        'type'  => 'switcher',
                        'title' => esc_html__('Low Brightness Images', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                          __('Enable a low-brightness effect for images when dark mode is active.', 'darkify') .
                          '</div>',

                        'text_on'  => esc_html__('Enabled', 'darkify'),
                        'text_off' => esc_html__('Disabled', 'darkify'),
                        'text_width' => 100,
                        'default' => true,
                      ),
                      array(
                        'id' => 'low_image_brightness_label',
                        'type'  => 'slider',
                        'title' => esc_html__('Brightness Level', 'darkify'),
                        'title_help' => '<div class="darkify-info-label">' .
                          __('Adjust the brightness level for images in dark mode.', 'darkify') .
                          '</div>',

                        'min'     => 0,
                        'max'     => 100,
                        'step'    => 5,
                        'default'     => '20',
                        'unit'    => '%',
                        'panel'   => true,
                        'dependency' => array('enable_low_image_brightness', '==', 'true', 'all'),
                      ),

                      array(
                        'id' => 'allowed_low_brightness_images',
                        'type'  => 'textarea',
                        'title' => esc_html__('Include Images', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                          __('Apply brightness adjustments only to these images in dark mode. Leave empty to apply to all images.', 'darkify') .
                          '</div>',
                        'placeholder' => esc_html__(
                          'Enter image URLs to include (comma-separated or one per line). Leave empty to apply to all images.',
                          'darkify'
                        ),

                        'class' => 'only_pro',
                        'dependency' => array('enable_low_image_brightness', '==', 'true', 'all'),
                      ),

                      array(
                        'id' => 'disallowed_low_brightness_images',
                        'type'  => 'textarea',
                        'title' => esc_html__('Exclude Images', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                          __('Exclude specific images from brightness adjustments in dark mode.', 'darkify') .
                          '</div>',
                        'placeholder' => esc_html__(
                          'Enter image URLs to exclude (comma-separated or one per line).',
                          'darkify'
                        ),

                        'class' => 'only_pro',
                        // Hidden once the matching Include list is in use, as in Darkify Pro,
                        // where the two lists are alternatives.
                        'dependency' => array('enable_low_image_brightness|allowed_low_brightness_images', '==|==', 'true|'),
                      ),
                    ),
                  ),
                  // ===== GRAYSCALE =====
                  array(
                    'type'          => 'heading',
                    'title'         => esc_html__('Grayscale', 'darkify'),
                    'section_start' => true,
                  ),
                  // Grayscale Image
                  array(
                    'id'     => 'grayscale',
                    'type'   => 'fieldset',
                    'fields' => array(
                      array(
                        'id' => 'enable_image_grayscale',
                        'type'  => 'switcher',
                        'title' => esc_html__('Grayscale Images', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                          __('Enable grayscale effect for images when dark mode is enabled.', 'darkify') .
                          '</div>',

                        'text_on'  => esc_html__('Enabled', 'darkify'),
                        'text_off' => esc_html__('Disabled', 'darkify'),
                        'text_width' => 100,
                      ),
                      array(
                        'id' => 'image_grayscale_label',
                        'type'  => 'slider',
                        'title' => esc_html__('Grayscale Level', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                          __('Adjust the grayscale level for images in dark mode.', 'darkify') .
                          '</div>',

                        'min'     => 0,
                        'max'     => 100,
                        'step'    => 5,
                        'default'     => '80',
                        'unit'    => '%',
                        'panel'   => true,
                        'dependency' => array('enable_image_grayscale', '==', 'true', 'all'),
                      ),

                      array(
                        'id' => 'allowed_grayscale_images',
                        'type'  => 'textarea',
                        'title' => esc_html__('Include Images', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                          __('Apply grayscale effects only to these images in dark mode. Leave empty to apply to all images.', 'darkify') .
                          '</div>',
                        'placeholder' => esc_html__(
                          'Enter image URLs to include (comma-separated or one per line). Leave empty to apply to all images.',
                          'darkify'
                        ),

                        'class' => 'only_pro',
                        'dependency' => array('enable_image_grayscale', '==', 'true', 'all'),
                      ),

                      array(
                        'id' => 'disallowed_low_grayscale_images',
                        'type'  => 'textarea',
                        'title' => esc_html__('Exclude Images', 'darkify'),
                        'title_help' => '<div class="darkify-info-label">' .
                          __('Exclude grayscale effects for specific images.', 'darkify') .
                          '</div>',
                        'placeholder' => esc_html__(
                          'Enter image URLs (comma-separated or one per line) to exclude from grayscale effects.',
                          'darkify'
                        ),

                        'class' => 'only_pro',
                        // Hidden once the matching Include list is in use, as in Darkify Pro,
                        // where the two lists are alternatives.
                        'dependency' => array('enable_image_grayscale|allowed_grayscale_images', '==|==', 'true|'),
                      ),
                    ),
                  ),

                  // ===== BACKGROUND & INVERSION =====
                  array(
                    'type'          => 'heading',
                    'title'         => esc_html__('Background & Inversion', 'darkify'),
                    'section_start' => true,
                  ),
                  // Darken Background Image
                  array(
                    'id'     => 'darken_background',
                    'type'   => 'fieldset',
                    'fields' => array(
                      array(
                        'id' => 'enable_low_image_darken',
                        'type'  => 'switcher',
                        'title' => esc_html__('Darken Background Images', 'darkify'),
                        'title_help' => '<div class="darkify-info-label">' .
                          __('Enable background darkening effect in dark mode.', 'darkify') .
                          '</div>',

                        'text_on'  => esc_html__('Enabled', 'darkify'),
                        'text_off' => esc_html__('Disabled', 'darkify'),
                        'text_width' => 100,
                        'default' => true,
                      ),
                      array(
                        'id' => 'low_image_darken_label',
                        'type'  => 'slider',
                        'title' => esc_html__('Darkening Level', 'darkify'),
                        'title_help' => '<div class="darkify-info-label">' .
                          __('Adjust the darkening level for background images in dark mode.', 'darkify') .
                          '</div>',

                        'min'     => 0,
                        'max'     => 100,
                        'step'    => 5,
                        'default'     => '60',
                        'unit'    => '%',
                        'panel'   => true,
                        'dependency' => array('enable_low_image_darken', '==', 'true', 'all'),
                      ),
                    ),
                  ),

                  array(
                    'id' => 'enable_invert_inline_svg',
                    'type'  => 'switcher',
                    'title' => esc_html__('Invert Inline SVGs', 'darkify'),
                    'title_help' =>
                    '<div class="darkify-info-label">' .
                      __('Enable automatic inversion of inline SVG images in dark mode.', 'darkify') .
                      '</div>',

                    'text_on'  => esc_html__('Enabled', 'darkify'),
                    'text_off' => esc_html__('Disabled', 'darkify'),
                    'text_width' => 100,
                  ),

                  // Image Inversion
                  array(
                    'id'     => 'invert',
                    'type'   => 'fieldset',
                    'fields' => array(
                      array(
                        'id' => 'enable_invert_images',
                        'type'  => 'switcher',
                        'title' => esc_html__('Invert Specific Images', 'darkify'),
                        'title_help' => '<div class="darkify-info-label">' .
                          __('Enable image inversion for specific images in dark mode.', 'darkify') .
                          '</div>',

                        'text_on'  => esc_html__('Enabled', 'darkify'),
                        'text_off' => esc_html__('Disabled', 'darkify'),
                        'text_width' => 100,
                        'class' => 'switcher_pro_only',
                        'default' => false,
                      ),
                      array(
                        'id' => 'invert_images_allowed_urls',
                        'type'  => 'textarea',
                        'title' => esc_html__('Image URLs', 'darkify'),
                        'title_help' => '<div class="darkify-info-label">' .
                          __('Apply inversion to specific images, SVGs, or backgrounds.', 'darkify') .
                          '</div>',
                        'placeholder' => esc_html__(
                          'Enter URLs (comma-separated or one per line) for images, SVGs, or background images to apply inversion.',
                          'darkify'
                        ),

                        'class' => 'only_pro',
                      ),
                    ),
                  ),


                  array(
                    'type'          => 'heading',
                    'title'         => esc_html__('Replace Images', 'darkify'),
                    'section_start' => true,
                  ),
                  array(
                    'id'        => 'image_replacements',
                    'type'      => 'repeater',
                    'class' => 'replacement_repeater repeater_pro_only',
                    'title' => esc_html__('Replace Images', 'darkify'),
                    'title_help' =>
                    '<div class="darkify-info-label">' .
                      __('Replace specified images when dark mode is active.', 'darkify') .
                      '</div>' .
                      '' .
                      '',

                    'fields'    => array(
                      array(
                        'id'           => 'normal_image',
                        'type'         => 'upload',
                        'library'      => 'image',
                        'title'        => esc_html__('Normal Mode Image', 'darkify'),
                        'placeholder'  => esc_html__('Image URL', 'darkify'),
                        'button_title' => esc_html__('Add Image', 'darkify'),
                        'remove_title' => esc_html__('Remove Image', 'darkify'),
                      ),

                      array(
                        'id'           => 'dark_image',
                        'type'         => 'upload',
                        'library'      => 'image',
                        'title'        => esc_html__('Dark Mode Image', 'darkify'),
                        'placeholder'  =>   esc_html__('Image URL', 'darkify'),
                        'button_title' => esc_html__('Add Image', 'darkify'),
                        'remove_title' => esc_html__('Remove Image', 'darkify'),
                      ),

                    ),
                    'default'   => array(
                      array(
                        'normal_image' => '',
                        'dark_image' => '',
                      ),
                    )
                  ),

                )
              ),
              array(
                'id'    => 'video-controls',
                'title' => esc_html__('Video Controls', 'darkify'),
                'icon'  => 'icofont-file-video',
                'fields' => array(
                  // Intro line, rendered plain above the section cards.
                  array(
                    'type'    => 'page_hint',
                    'content' => esc_html__('Control how videos respond to dark mode.', 'darkify'),
                  ),
                  // ===== BRIGHTNESS =====
                  array(
                    'type'          => 'heading',
                    'title'         => esc_html__('Brightness', 'darkify'),
                    'section_start' => true,
                  ),
                  // Low Brightness
                  array(
                    'id'     => 'video_brightness',
                    'type'   => 'fieldset',
                    'fields' => array(
                      array(
                        'id' => 'enable_low_video_brightness',
                        'type'  => 'switcher',
                        'title' => esc_html__('Low Brightness Videos', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                          __('Enable brightness effect for videos in dark mode.', 'darkify') .
                          '</div>',

                        'text_on'  => esc_html__('Enabled', 'darkify'),
                        'text_off' => esc_html__('Disabled', 'darkify'),
                        'text_width' => 100,
                        'default' => true,
                      ),
                      array(
                        'id' => 'low_video_brightness_label',
                        'type'  => 'slider',
                        'title' => esc_html__('Brightness Level', 'darkify'),
                        'title_help' => '<div class="darkify-info-label">' .
                          __('Adjust the brightness level for videos in dark mode.', 'darkify') .
                          '</div>',

                        'min'     => 0,
                        'max'     => 100,
                        'step'    => 5,
                        'default'     => '20',
                        'unit'    => '%',
                        'panel'   => true,
                        'dependency' => array('enable_low_video_brightness', '==', 'true', 'all'),
                      ),
                      array(
                        'id' => 'allowed_low_brightness_videos',
                        'type'  => 'textarea',
                        'title' => esc_html__('Include Videos', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                          __('Apply brightness adjustments only to these videos (self-hosted, YouTube, Vimeo, or Dailymotion) in dark mode. Leave empty to apply to all videos.', 'darkify') .
                          '</div>',
                        'placeholder' => esc_html__(
                          'Enter video URLs to include (comma-separated or one per line). Leave empty to apply to all videos.',
                          'darkify'
                        ),

                        'class' => 'only_pro',
                        'dependency' => array('enable_low_video_brightness', '==', 'true', 'all'),
                      ),
                      array(
                        'id' => 'disallowed_low_brightness_videos',
                        'type'  => 'textarea',
                        'title' => esc_html__('Exclude Videos', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                          __('Exclude specific videos (self-hosted, YouTube, Vimeo, or Dailymotion) from brightness adjustments in dark mode.', 'darkify') .
                          '</div>',
                        'placeholder' => esc_html__(
                          'Enter video URLs to exclude (comma-separated or one per line).',
                          'darkify'
                        ),

                        'class' => 'only_pro',
                        'dependency' => array('enable_low_video_brightness|allowed_low_brightness_videos', '==|==', 'true|'),
                      ),
                    ),
                  ),

                  // ===== GRAYSCALE =====
                  array(
                    'type'          => 'heading',
                    'title'         => esc_html__('Grayscale', 'darkify'),
                    'section_start' => true,
                  ),
                  // Grayscale Video
                  array(
                    'id'     => 'video_grayscale',
                    'type'   => 'fieldset',
                    'fields' => array(
                      array(
                        'id' => 'enable_video_grayscale',
                        'type'  => 'switcher',
                        'title' => esc_html__('Grayscale Videos', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                          __('Enable grayscale effect for videos in dark mode.', 'darkify') .
                          '</div>',

                        'text_on'  => esc_html__('Enabled', 'darkify'),
                        'text_off' => esc_html__('Disabled', 'darkify'),
                        'text_width' => 100,
                      ),
                      array(
                        'id' => 'video_grayscale_label',
                        'type'  => 'slider',
                        'title' => esc_html__('Grayscale Level', 'darkify'),
                        'title_help' => '<div class="darkify-info-label">' .
                          __('Adjust the grayscale level for videos in dark mode.', 'darkify') .
                          '</div>',

                        'min'     => 0,
                        'max'     => 100,
                        'step'    => 5,
                        'default'     => '80',
                        'unit'    => '%',
                        'panel'   => true,
                        'dependency' => array('enable_video_grayscale', '==', 'true', 'all'),
                      ),
                      array(
                        'id' => 'allowed_grayscale_videos',
                        'type'  => 'textarea',
                        'title' => esc_html__('Include Videos', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                          __('Apply grayscale effects only to these videos (self-hosted, YouTube, Vimeo, or Dailymotion) in dark mode. Leave empty to apply to all videos.', 'darkify') .
                          '</div>',
                        'placeholder' => esc_html__(
                          'Enter video URLs to include (comma-separated or one per line). Leave empty to apply to all videos.',
                          'darkify'
                        ),

                        'class' => 'only_pro',
                        'dependency' => array('enable_video_grayscale', '==', 'true', 'all'),
                      ),
                      array(
                        'id' => 'disallowed_grayscale_videos',
                        'type'  => 'textarea',
                        'title' => esc_html__('Exclude Videos', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                          __('Exclude grayscale effects for specific videos (self-hosted, YouTube, Vimeo, or Dailymotion).', 'darkify') .
                          '</div>',
                        'placeholder' => esc_html__(
                          'Enter video URLs to exclude (comma-separated or one per line).',
                          'darkify'
                        ),

                        'class' => 'only_pro',
                        'dependency' => array('enable_video_grayscale|allowed_grayscale_videos', '==|==', 'true|'),
                      ),
                    ),
                  ),


                  array(
                    'type'          => 'heading',
                    'title'         => esc_html__('Replace Videos', 'darkify'),
                    'section_start' => true,
                  ),
                  array(
                    'id'        => 'video_replacements',
                    'type'      => 'repeater',
                    'class'      => 'replacement_repeater repeater_pro_only',
                    'title'     => esc_html__('Replace Videos', 'darkify'),
                    'title_help' => '<div class="darkify-info-label">' .
                      __('Replace specified videos (self-hosted, YouTube, Vimeo, or Dailymotion) when dark mode is active.', 'darkify') .
                      '</div>',

                    'fields'    => array(
                      array(
                        'id'           => 'normal_video',
                        'type'         => 'upload',
                        'library'      => 'video',
                        'title'        => esc_html__('Normal Mode Video', 'darkify'),
                        'placeholder'  => esc_html__('Video URL', 'darkify'),
                        'button_title' => esc_html__('Add Video', 'darkify'),
                        'remove_title' => esc_html__('Remove Video', 'darkify'),

                      ),

                      array(
                        'id'           => 'dark_video',
                        'type'         => 'upload',
                        'library'      => 'video',
                        'title'        => esc_html__('Dark Mode Video', 'darkify'),
                        'placeholder'  => esc_html__('Video URL', 'darkify'),
                        'button_title' => esc_html__('Add Video', 'darkify'),
                        'remove_title' => esc_html__('Remove Video', 'darkify'),

                      ),

                    ),
                    'default'   => array(
                      array(
                        'normal_video' => '',
                        'dark_video' => '',
                      ),
                    )
                  ),
                )
              ),
            ),
          ),
        )
      )
    );
  }
}
