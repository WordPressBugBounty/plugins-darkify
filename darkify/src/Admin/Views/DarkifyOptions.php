<?php

namespace ThemeAtelier\Darkify\Admin\Views;

use ThemeAtelier\Darkify\Admin\Schema\SchemaRegistry as Darkify;
use ThemeAtelier\Darkify\Admin\Views\SwitcherStyle;
use ThemeAtelier\Darkify\Admin\Views\Control;
use ThemeAtelier\Darkify\Admin\Views\AdvancedSettings;
use ThemeAtelier\Darkify\Admin\Views\Tools;

if (!defined('ABSPATH')) {
  die;
} // Cannot access directly.

class DarkifyOptions
{
  /**
   * Create Option fields for the setting options.
   *
   * @param string $prefix Option setting key prefix.
   * @return void
   */
  public static function options($prefix)
  {
    //
    // Create options
    //
    Darkify::createOptions($prefix, array(
      'menu_title' => esc_html__('Darkify', 'darkify'),
      'menu_slug'  => 'darkify',
      'menu_position' => 25,
      'menu_icon'  => 'data:image/svg+xml;base64,' . base64_encode('<svg width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M23.5 1.5C23.5 0.671573 22.8284 0 22 0C21.1716 0 20.5 0.671573 20.5 1.5V4.5C20.5 5.32843 21.1716 6 22 6C22.8284 6 23.5 5.32843 23.5 4.5V1.5Z" fill="white"/><path d="M34 22C34 28.6274 28.6274 34 22 34C15.3726 34 10 28.6274 10 22C10 15.3726 15.3726 10 22 10C28.6274 10 34 15.3726 34 22Z" fill="white"/><path d="M22 38C22.8284 38 23.5 38.6716 23.5 39.5V42.5C23.5 43.3284 22.8284 44 22 44C21.1716 44 20.5 43.3284 20.5 42.5V39.5C20.5 38.6716 21.1716 38 22 38Z" fill="white"/><path d="M10.6863 10.6863C10.1005 11.2721 9.15074 11.2721 8.56495 10.6863L6.44363 8.56498C5.85784 7.97919 5.85784 7.02945 6.44363 6.44366C7.02942 5.85787 7.97916 5.85787 8.56495 6.44366L10.6863 8.56498C11.2721 9.15077 11.2721 10.1005 10.6863 10.6863Z" fill="white"/><path d="M35.435 37.5564C36.0208 38.1421 36.9705 38.1421 37.5563 37.5564C38.1421 36.9706 38.1421 36.0208 37.5563 35.435L35.435 33.3137C34.8492 32.7279 33.8995 32.7279 33.3137 33.3137C32.7279 33.8995 32.7279 34.8493 33.3137 35.435L35.435 37.5564Z" fill="white"/><path d="M10.6863 33.3137C11.2721 33.8995 11.2721 34.8492 10.6863 35.435L8.56495 37.5563C7.97916 38.1421 7.02942 38.1421 6.44363 37.5563C5.85784 36.9706 5.85784 36.0208 6.44363 35.435L8.56495 33.3137C9.15074 32.7279 10.1005 32.7279 10.6863 33.3137Z" fill="white"/><path d="M37.5563 8.56496C38.1421 7.97918 38.1421 7.02943 37.5563 6.44364C36.9705 5.85786 36.0208 5.85786 35.435 6.44364L33.3137 8.56496C32.7279 9.15075 32.7279 10.1005 33.3137 10.6863C33.8995 11.2721 34.8492 11.2721 35.435 10.6863L37.5563 8.56496Z" fill="white"/><path d="M6 22C6 22.8284 5.32843 23.5 4.5 23.5H1.5C0.671573 23.5 0 22.8284 0 22C0 21.1716 0.671573 20.5 1.5 20.5H4.5C5.32843 20.5 6 21.1716 6 22Z" fill="white"/><path d="M42.5 23.5C43.3284 23.5 44 22.8284 44 22C44 21.1716 43.3284 20.5 42.5 20.5H39.5C38.6716 20.5 38 21.1716 38 22C38 22.8284 38.6716 23.5 39.5 23.5H42.5Z" fill="white"/></svg>'),
      'framework_title'       => esc_html__('Darkify', 'darkify'),
      'footer_text'           => esc_html__('Thank you for using our plugin', 'darkify'),
      'theme'                 => 'light',
      'nav'                   => 'inline',
      'show_search'           => false,
      'show_reset_all'        => false,
      'show_sub_menu'         => false,
      'show_bar_menu'         => false,
      'sticky_header'         => false,
      'show_footer'			      => false,
      'show_all_options'		  => false,
      'framework_class'       => 'darkify-setting-admin',
      'class'              	  => 'darkify-preloader',
      'menu_type'             => 'submenu',
    ));

    //
    // control section
    //
    Control::options($prefix);
    SwitcherStyle::options($prefix);
    ColorControl::options($prefix);
    Media::options($prefix);
    AdvancedSettings::options($prefix);
    Tools::options($prefix);
    License::options($prefix);
    // Help is no longer a schema section: it is a dedicated page inside the
    // React admin (darkify-react/src/pages/Help.jsx), routed via #/help — the
    // same structure the Pro plugin uses.
  }
}
