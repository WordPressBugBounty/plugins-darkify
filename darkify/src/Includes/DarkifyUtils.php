<?php

namespace ThemeAtelier\Darkify\Includes;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

if (!class_exists('DarkifyUtils')) {
    class DarkifyUtils
    {

        public $base_admin;
        public function __construct($base_admin)
        {
            $this->base_admin = $base_admin;
        }

        public function isMobile()
        {
            if (function_exists("wp_is_mobile")) {
                return wp_is_mobile();
            } else {
                return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
            }
        }
        public function is_hidden_by_user_agent($hide_on_desktop, $hide_dark_mode_on_mobile, $type_of_hide_by)
        {
            if ($this->isMobile()) {
                if ($hide_dark_mode_on_mobile) {
                    if ($type_of_hide_by == "user_agent" || $type_of_hide_by == "both") {
                        return True;
                    }
                }
            } else {
                if ($hide_on_desktop) {
                    return True;
                }
            }
            return False;
        }

        public function generateSwitchStyles($options)
        {
            $switch_background = isset($options['switch_background']) ? $options['switch_background'] : [];
            $switch_light_mode_bg = !empty($switch_background) ? $switch_background['switch_light_mode_bg'] : '#121116';
            $switch_dark_mode_bg = !empty($switch_background) ? $switch_background['switch_dark_mode_bg'] : '#ffffff';
            $switch_icon_color = isset($options['switch_icon_color']) ? $options['switch_icon_color'] : [];

            $switch_light_mode_color = !empty($switch_icon_color) ? $switch_icon_color['switch_light_mode_color'] : '#ffffff';
            $switch_dark_mode_color = !empty($switch_icon_color) ? $switch_icon_color['switch_dark_mode_color'] : '#121116';
            $switch_border = isset($options['switch_border']) ? $options['switch_border'] : [];
            $switch_border_all = !empty($switch_border['all']) ? $switch_border['all'] : "0";
            $switch_border_light_color = !empty($switch_border['color']) ? $switch_border['color'] : "#ffffff";
            $switch_border_dark_color = !empty($switch_border['color3']) ? $switch_border['color3'] : "#121116";
            $switch_border_radius = !empty($switch_border['radius']) ? $switch_border['radius'] : "7";

            $styles = array(
                "--darkify_switch_border" => $switch_border_all . "px",
                "--darkify_switch_border_light_color" => $switch_border_light_color,
                "--darkify_switch_border_dark_color" => $switch_border_dark_color,
                "--darkify_switch_border_radius" => $switch_border_radius . "px",
                "--darkify_switch_light_mode_bg" => $switch_light_mode_bg,
                "--darkify_switch_dark_mode_bg" => $switch_dark_mode_bg,
                "--darkify_switch_light_mode_color" => $switch_light_mode_color,
                "--darkify_switch_dark_mode_color" => $switch_dark_mode_color,
            );
            return $styles;
        }

        public function generateSwitchStylesForShortcode($atts)
        {
            $styles = array();
            foreach ($atts as $key => $value) {
                if ($key == "switch") {
                    continue;
                }
                $styles["--darkify_switch_" . $key] = $value;
            }
            return $styles;
        }

        public function string_contains_any($string, $words, $caseSensitive = true)
        {
            foreach ($words as $word) {
                $position = $caseSensitive ? stripos($string, $word) : strpos($string, $word);
                if ($position !== false) {
                    return true;
                }
            }
            return false;
        }

        public function extractCustomCssSelectorsForAutoExcluding($css, $custom_css_rules)
        {
            $pseudo_classes = array(":after", ":before", ":first-letter", ":first-line", ":selection", ":not-disallowed");
            $css = str_replace(array("\r", "\n"), "", $css);
            /* Convert to Array */
            $exclude_elements = array();
            $results = array();
            preg_match_all('/(.+?)\s?\{\s?(.+?)\s?\}/', $css, $matches);
            foreach ($matches[0] as $i => $original) {
                foreach (explode(';', $matches[2][$i]) as $attr) {
                    if (strlen(trim($attr)) > 0) // for missing semicolon on last element, which is legal
                    {
                        // Cap explode at 2 so legitimate CSS values containing `:`
                        // (e.g. background: url(https://…)) aren't broken, and skip
                        // malformed attributes lacking a `:` separator — both cases
                        // produced PHP 8.1+ "undefined index 1" + "trim(null)" notices.
                        if (strpos($attr, ':') === false) {
                            continue;
                        }
                        list($name, $value) = explode(':', $attr, 2);
                        $results[$matches[1][$i]][trim($name)] = trim($value);
                    }
                }
            }
            /* Array to processed css string */
            foreach ($results as $single_selector => $rules) {
                $single_selector_arr = explode(",", $single_selector);
                for ($i = 0; $i < sizeof($single_selector_arr); $i++) {
                    if (!$this->string_contains_any(trim($single_selector_arr[$i]), $pseudo_classes, false)) {
                        $exclude_elements[] = trim($single_selector_arr[$i]);
                        if ($custom_css_rules) {
                            $exclude_elements[] = trim($single_selector_arr[$i]) . " *";
                        }
                    }
                }
            }
            return $exclude_elements;
        }

        public static function parseAndProcessCustomCSS($css, $custom_css_rules)
        {
            /* Decode any escaped HTML entities like &gt;, &lt;, &quot; */
            $css = html_entity_decode($css, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            /* Replace : on https:// or http:// for detecting as background:black type */
            $css = str_replace("://", "--colon--//", $css);

            $css = str_replace(array("\r", "\n"), "", $css);

            /* Convert to Array */
            $results = array();
            preg_match_all('/(.+?)\s?\{\s?(.+?)\s?\}/', $css, $matches);
            foreach ($matches[0] as $i => $original) {
                foreach (explode(';', $matches[2][$i]) as $attr) {
                    if (strlen(trim($attr)) > 0) {
                        if (strpos($attr, ':') !== false) {
                            list($name, $value) = explode(':', $attr, 2); // FIXED
                            $results[$matches[1][$i]][trim($name)] = trim($value);
                        }
                    }
                }
            }

            /* Array to processed css string */
            $updated_css = "";
            foreach ($results as $single_selector => $rules) {
                $updated_single_selector = "";
                $single_selector_arr = explode(",", $single_selector);
                for ($i = 0; $i < sizeof($single_selector_arr); $i++) {
                    $selector = ".darkify_dark_mode_enabled " . trim($single_selector_arr[$i]);
                    if ($i > 0) {
                        $updated_single_selector .= ", ";
                    }

                    // Check if selector has :not-disallowed
                    if (strpos($selector, ':not-disallowed') !== false) {
                        $updated_single_selector .= str_replace(':not-disallowed', '', $selector);
                    } else {
                        $updated_single_selector .= $selector;
                        if ($custom_css_rules && !preg_match('/:{1,2}(after|before)\b/i', $selector)) {
                            $updated_single_selector .= ", .darkify_dark_mode_enabled " . $selector . " *";
                        }
                    }
                }

                $updated_css .= $updated_single_selector . "{";

                foreach ($rules as $key => $value) {
                    if (strpos($value, "important") == false) {
                        $value = $value . " !important";
                    }
                    $updated_css .= $key . ": " . $value . ";";
                }

                $updated_css .= "}";
            }

            /* Replace --colon--// back to https:// or http:// format */
            $updated_css = str_replace("--colon--//", "://", $updated_css);

            return $updated_css;
        }

        public static function parseAndProcessNormalCustomCSS($css)
        {
            /* Replace : on https:// or http:// for detecting as background:black type */
            $css = str_replace("://", "--colon--//", $css);

            $css = str_replace(array("\r", "\n"), "", $css);
            /* Convert to Array */
            $results = array();
            preg_match_all('/(.+?)\s?\{\s?(.+?)\s?\}/', $css, $matches);
            foreach ($matches[0] as $i => $original) {
                foreach (explode(';', $matches[2][$i]) as $attr) {
                    if (strlen(trim($attr)) > 0) // for missing semicolon on last element, which is legal
                    {
                        // PATCH (community fix): same as in
                        // extractCustomCssSelectorsForAutoExcluding above —
                        // cap explode at 2 + skip if no colon, to avoid
                        // PHP 8.1+ undefined-index + trim(null) notices on
                        // malformed CSS attributes.
                        if (strpos($attr, ':') === false) {
                            continue;
                        }

                        list($name, $value) = explode(':', $attr, 2);
                        $results[$matches[1][$i]][trim($name)] = trim($value);
                    }
                }
            }
            /* Array to processed css string */
            $updated_css = "";
            foreach ($results as $single_selector => $rules) {
                $updated_css .= $single_selector . "{";

                foreach ($rules as $key => $value) {
                    if (strpos($value, "important") == false) {
                        $value = $value . " !important";
                    }
                    $updated_css .= $key . ": " . $value . ";";
                }

                $updated_css .= "}";
            }
            /* Replace --colon--// back to https:// or http:// format */
            $updated_css = str_replace("--colon--//", "://", $updated_css);
            return $updated_css;
        }

        public function generateAllowedElementsStr($options)
        {
            $allowed_elements = isset($options["allowed_elements"]) ? self::normalize_restriction_class($options['allowed_elements']) : "";

            if (strlen(trim($allowed_elements)) > 0) {
                $allowed_elements_arr = explode(',', $allowed_elements);
                if (is_array($allowed_elements_arr)) {
                    if (sizeof($allowed_elements_arr) > 0) {
                        foreach ($allowed_elements_arr as $single_element) {
                            $allowed_elements .= ($allowed_elements != "" ? ", " : "") . trim($single_element);
                            $allowed_elements .= ($allowed_elements != "" ? ", " : "") . trim($single_element) . ' *';
                        }
                    }
                }
            }
            return $allowed_elements;
        }

        public function generateDisallowedElementsStr($options, $external_support_class_obj)
        {
            $disallowed_element = isset($options["disallowed_elements"]) ? self::normalize_restriction_class($options['disallowed_elements']) : "";
            $custom_css_rules = isset($options["custom_css_rules"]) ? $options["custom_css_rules"] : "";
            $custom_css = isset($options["custom_css"]) ? $options["custom_css"] : "";
            $disallowed_elements = "";
            if (strlen(trim($disallowed_element)) > 0) {
                $disallowed_elements_arr = explode(',', $disallowed_element);
                if (is_array($disallowed_elements_arr)) {
                    if (sizeof($disallowed_elements_arr) > 0) {
                        foreach ($disallowed_elements_arr as $single_element) {
                            $disallowed_elements .= ($disallowed_elements != "" ? ", " : "") . trim($single_element);
                            $disallowed_elements .= ($disallowed_elements != "" ? ", " : "") . trim($single_element) . ' *';
                        }
                    }
                }
            }

            // Get Disallowed Elements from External Plugins
            $disallowed_from_external = $external_support_class_obj->getDisallowedElementsByAvailablePlugins();
            if (sizeof($disallowed_from_external) > 0) {
                foreach ($disallowed_from_external as $single_element) {
                    $disallowed_elements .= ($disallowed_elements != "" ? ", " : "") . trim($single_element);
                }
            }

            // Exclude selectors specified in Custom CSS
            $custom_css_selectors = $this->extractCustomCssSelectorsForAutoExcluding($custom_css, $custom_css_rules);
            if (is_array($custom_css_selectors)) {
                if (sizeof($custom_css_selectors) > 0) {
                    foreach ($custom_css_selectors as $single_element) {
                        $disallowed_elements .= ($disallowed_elements != "" ? ", " : "") . trim($single_element);
                    }
                }
            }
            return $disallowed_elements;
        }

        public function minify_string($buffer)
        {
            $search = array(
                '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
                '/[^\S ]+\</s',     // strip whitespaces before tags, except space
                '/(\s)+/s',         // shorten multiple whitespace sequences
                '/<!--(.|\s)*?-->/' // Remove HTML comments
            );
            $replace = array(
                '>',
                '<',
                '\\1',
                ''
            );
            $buffer = preg_replace($search, $replace, $buffer);
            return $buffer;
        }

        public function isRestrictedByDisallowedAdminPages($disallowed_admin_pages)
        {
            if (strlen(trim($disallowed_admin_pages)) > 0) {
                if (function_exists("get_current_screen")) {
                    $current_screen = get_current_screen();
                    $parts = explode('_page_', $current_screen->id);
                    if (count($parts) !== 2) {
                        return False;
                    } /* means it's something like invalid page slug */
                    $page_slug = $parts[1];

                    $disallowed_admin_pages_arr = explode(',', $disallowed_admin_pages);
                    if (is_array($disallowed_admin_pages_arr)) {
                        if (sizeof($disallowed_admin_pages_arr) > 0) {
                            foreach ($disallowed_admin_pages_arr as $single_page) {
                                if ($page_slug == trim($single_page)) {
                                    return True;
                                }
                            }
                        }
                    }
                }
            }
            return False;
        }

        /**
         * Custom Template locator .
         *
         * @param  mixed $template_name template name .
         * @param  mixed $template_path template path .
         * @param  mixed $default_path default path .
         * @return string
         */
        public static function darkify_locate_template($template_name, $default_path = '')
        {
            if (!$default_path) {
                $default_path = DARKIFY_PATH . 'src/Frontend/Templates/';
            }
            $template = locate_template($template_name);
            // Get default template.
            if (!$template) {
                $template = $default_path . $template_name;
            }
            // Return what we found.
            return $template;
        }

        /**
         * Cleans up that sloppy list of URLs—tightens it up like a true warrior.
         *
         * @param  string $input A comma-separated string of URLs, possibly with extra space.
         * @return string Refined and battle-ready URL list—no excess baggage.
         *
         * @bardock No room for weakness. Clean your inputs before they get you killed.
         */
        public static function normalize_restriction_class($input)
        {
            $urls = preg_replace('/,\s+/', ',', $input);
            return $urls;
        }

        /**
         * Validate a colour from an admin field that accepts hex or rgba() —
         * a Color Override's own picker supports an alpha channel (shadows
         * and overlays are almost always semi-transparent), but
         * `sanitize_hex_color()` alone silently rejects anything that isn't
         * plain `#rrggbb`, which drops the whole override row without
         * explanation.
         *
         * @param  string $value
         * @return string The value unchanged if it's a valid colour, '' otherwise.
         */
        public static function sanitize_css_color($value)
        {
            $value = trim((string) $value);
            if ($value === '') {
                return '';
            }

            $hex = sanitize_hex_color($value);
            if ($hex) {
                return $hex;
            }

            if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(0|1|0?\.\d+)\s*)?\)$/i', $value, $matches)) {
                foreach (array($matches[1], $matches[2], $matches[3]) as $channel) {
                    if ((int) $channel > 255) {
                        return '';
                    }
                }
                return $value;
            }

            return '';
        }

        public static function normalize_image_urls($input)
        {
            $urls = preg_split('/[\s,]+/', $input, -1, PREG_SPLIT_NO_EMPTY);
            return implode(',', $urls);
        }

        /* =============== Darkify Add Button Class =============== */
        public function addButtonClassByDarkify($options)
        {
            $darkfy_button_class = [
                "elementor-button",
                "gspb-buttonbox",
                "fusion-button",
                "wp-element-button",
                "ct-header-search",
                "stk-button"
            ];
            return $darkfy_button_class;
        }
    }
}
