<?php

namespace ThemeAtelier\Darkify\Includes;

// Abort if this file is accessed directly, outside of WordPress.
if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('DarkifyExternalSupport')) {
    class DarkifyExternalSupport
    {

        public $base_admin;
        public function __construct($base_admin)
        {
            $this->base_admin = $base_admin;
        }

        public function getDisallowedElementsByAvailablePlugins()
        {
            $disallowed_elements = array();

            /* =============== Default Darkify Ignore =============== */
            $disallowed_elements = array_merge($disallowed_elements, $this->getDisallowedElementsByDarkify());

            /* =============== Logged-in as Admin =============== */
            if (function_exists("is_admin_bar_showing")) {
                if (is_admin_bar_showing()) {
                    $disallowed_elements = array_merge($disallowed_elements, $this->getDisallowedElementsByAdminLogin());
                }
            }

            /* =============== Is in Admin Panel =============== */
            if (function_exists("is_admin")) {
                if (is_admin()) {
                    $disallowed_elements = array_merge($disallowed_elements, $this->getDisallowedElementsByAdminPanel());
                }
            }

            /* =============== Elementor =============== */
            if (function_exists("is_plugin_active")) {
                if (is_plugin_active('elementor/elementor.php')) {
                    $disallowed_elements = array_merge($disallowed_elements, $this->getDisallowedElementsByElementor());
                }
            }

            /* =============== Beaver Builder =============== */
            if (class_exists('FLBuilder')) {
                $disallowed_elements = array_merge($disallowed_elements, $this->getDisallowedElementsByBeaver());
            }

            /* =============== Slider Revolution =============== */
            if (class_exists('RevSliderFront')) {
                $disallowed_elements = array_merge($disallowed_elements, $this->getDisallowedElementsByRevSlider());
            }

            /* =============== OneSignal Push Notifications =============== */
            if (class_exists('OneSignal_Public')) {
                $disallowed_elements = array_merge($disallowed_elements, $this->getDisallowedElementsByOneSignal());
            }

            /* =============== Read Meter =============== */
            if (class_exists('BSFRT_Loader')) {
                $disallowed_elements = array_merge($disallowed_elements, $this->getDisallowedElementsByReadMeter());
            }


            return $disallowed_elements;
        }

        /* =============== Default Darkify Ignore =============== */
        public function getDisallowedElementsByDarkify()
        {
            return array(
                ".darkify_ignore",
                ".darkify_ignore *",
                ".darkify_switch",
                ".darkify_switch *",
                ".wp-block-btn-button .btnButton",
                ".wp-block-btn-button .btnButton *",
            );
        }

        /* =============== Logged-in as Admin =============== */
        public function getDisallowedElementsByAdminLogin()
        {
            return array(
                "#wpadminbar",
                "#wpadminbar *",
            );
        }

        /* =============== Is in Admin Panel =============== */
        public function getDisallowedElementsByAdminPanel()
        {
            return array(
                "#wpadminbar",
                "#wpadminbar *",
                "#adminmenumain",
                "#adminmenumain *",
                ".wp-core-ui .button-primary",
                ".wp-core-ui .button-primary *",
                ".post-com-count-approved",
                ".post-com-count-approved *",
                ".darkify--switcher",
                ".darkify--switcher *",
                ".darkify-slider-ui",
                ".darkify-slider-ui *",
                ".CodeMirror",
                ".CodeMirror *",
                ".wp-picker-container",
                ".wp-picker-container *",
                ".darkify--sibling.darkify--image *",
                ".darkify_preset .darkify--sibling",
                ".darkify_preset .darkify--sibling *",
                ".button",
                ".darkify-tooltip",
                ".darkify-tooltip *",
                ".rll-youtube-player .play",
                "button.components-color-palette__custom-color-button",
                ".react-colorful__saturation",
                ".react-colorful__hue.react-colorful__last-control",
                ".block-editor-color-gradient-control__panel",
                ".block-editor-color-gradient-control__panel *",
                ".components-z-stack",
                ".components-z-stack *",
                ".theme_color",
                ".themeatelier_license_notice",
                ".themeatelier_license_notice *",
                "#themeatelier-offer-banner",
                "#themeatelier-offer-banner *",
            );
        }


        /* =============== Elementor =============== */
        public function getDisallowedElementsByElementor()
        {
            return array(
                ".elementor-background-overlay",
                ".elementor-element-overlay",
                // ".elementor-button-link",
                // ".elementor-button-link *",
                ".elementor-widget-spacer",
                ".elementor-widget-spacer *",
            );
        }



        /* =============== Beaver Builder =============== */
        public function getDisallowedElementsByBeaver()
        {
            return array(
                ".uabb-button",
                ".uabb-button *",
            );
        }

        /* =============== Slider Revolution =============== */
        public function getDisallowedElementsByRevSlider()
        {
            return array(
                "rs-fullwidth-wrap",
                "rs-fullwidth-wrap *",
            );
        }

        /* =============== OneSignal Push Notifications =============== */
        public function getDisallowedElementsByOneSignal()
        {
            return array(
                ".onesignal-slidedown-dialog",
                ".onesignal-slidedown-dialog *",
            );
        }

        /* =============== Read Meter =============== */
        public function getDisallowedElementsByReadMeter()
        {
            return array(
                "#bsf_rt_progress_bar_container",
                "#bsf_rt_progress_bar_container *",
            );
        }
    }
}
