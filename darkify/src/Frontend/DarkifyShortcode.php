<?php

namespace ThemeAtelier\Darkify\Frontend;

use ThemeAtelier\Darkify\Includes\Darkify;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

if (!class_exists('DarkifyShortcode')) {
    class DarkifyShortcode
    {

        public $base_client;

        public function __construct($base_client)
        {
            $this->base_client = $base_client;

            add_shortcode('darkify', array($this, 'darkify_shortcode_parser'));
        }

        public function darkify_shortcode_parser($atts, $content = null)
        {
            // With Frontend Dark Mode off, client_main.css/.js are never
            // enqueued on the front end, so rendering the switch markup anyway
            // leaves an unstyled toggle behind that does nothing when clicked.
            // Gate every switch entry point here: the Gutenberg block and the
            // Elementor widget both render through this shortcode. The floating
            // switch, menu switch and inline scripts are already gated in
            // Frontend.php.
            if (! $this->base_client->darkify_is_dark_mode_allowed()) {
                return '';
            }

            $options = get_option('darkify');

            // Background
            $switch_background = isset($options['switch_background']) ? $options['switch_background'] : [];
            $switch_light_mode_bg = !empty($switch_background) ? $switch_background['switch_light_mode_bg'] : '#121116';
            $switch_dark_mode_bg = !empty($switch_background) ? $switch_background['switch_dark_mode_bg'] : '#ffffff';

            // Border
            $switch_border = isset($options['switch_border']) ? $options['switch_border'] : [];
            $switch_border_all = !empty($switch_border['all']) ? $switch_border['all'] : "0px";
            $switch_border_light_color = !empty($switch_border['color']) ? $switch_border['color'] : "#ffffff";
            $switch_border_dark_color = !empty($switch_border['color3']) ? $switch_border['color3'] : "#121116";
            $switch_border_radius = !empty($switch_border['radius']) ? $switch_border['radius'] . 'px' : "7px";

            // Color
            $switch_icon_color = isset($options['switch_icon_color']) ? $options['switch_icon_color'] : [];
            $switch_light_mode_color = !empty($switch_icon_color) ? $switch_icon_color['switch_light_mode_color'] : '#ffffff';
            $switch_dark_mode_color = !empty($switch_icon_color) ? $switch_icon_color['switch_dark_mode_color'] : '#121116';

            $atts = shortcode_atts(array(
                'switch' => '1',
                'switch_size' => '100',
                'light_mode_bg' =>  $switch_light_mode_bg,
                'dark_mode_bg' => $switch_dark_mode_bg,
                'border' =>  $switch_border_all,
                'border_light_color' => $switch_border_light_color,
                'border_dark_color' => $switch_border_dark_color,
                'border_radius' => $switch_border_radius,
                'light_mode_color' =>  $switch_light_mode_color,
                'dark_mode_color' => $switch_dark_mode_color,
            ), $atts);

            return $this->darkify_client_view_maker($atts);
        }
        public function darkify_client_view_maker($atts)
        {
            if (function_exists('wp_rand')) {
                $unique_id = wp_rand() . "_shortcode";
            }
            $switch_styles = $this->base_client->utils->generateSwitchStylesForShortcode($atts);
            ob_start();

            if (sizeof($switch_styles) > 0) { ?>
                <style type="text/css">
                    #darkify_switch_<?php echo esc_attr($unique_id); ?> {
                        <?php foreach ($switch_styles as $key => $value) { ?><?php echo esc_attr($key); ?>: <?php echo esc_attr($value); ?>;
                        <?php } ?>
                    }
                </style>
<?php }

            $switcher_number = $atts['switch'];

            $switcher_number = $atts['switch'];
            if ($switcher_number === '1') {
                $switcher_number = 'classic';
            } elseif ($switcher_number === '2') {
                $switcher_number = 'expand';
            } elseif ($switcher_number === '3') {
                $switcher_number = 'inner-moon';
            } elseif ($switcher_number === '4') {
                $switcher_number = 'within';
            } elseif ($switcher_number === '5') {
                $switcher_number = 'orbit';
            }
            $switch_size = $atts['switch_size'] / 100;
            // switcher_wrapper() escapes each dynamic value internally, so its
            // returned markup is safe to print as-is.
            echo Darkify::switcher_wrapper('shortcode', $unique_id, $switcher_number, $switch_size); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return ob_get_clean();
        }
    }
}
