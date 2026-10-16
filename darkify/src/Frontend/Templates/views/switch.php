<?php

use ThemeAtelier\Darkify\Includes\Darkify;

if (!defined('ABSPATH')) {
    die;
} // Cannot access directly.

$hide_on_mobile = isset($options["hide_on_mobile"]) ? $options["hide_on_mobile"] : '';
$hide_dark_mode_on_mobile = isset($hide_on_mobile['hide_dark_mode_on_mobile']) ? $hide_on_mobile['hide_dark_mode_on_mobile'] : '';
$tooltip_on_floating_switch = isset($options["tooltip_on_floating_switch"]) ? $options["tooltip_on_floating_switch"] : '';
$tooltip_color = isset($tooltip_on_floating_switch['tooltip_color']) ? $tooltip_on_floating_switch['tooltip_color'] : '';
$switcher_button_position = isset($options["switcher_button_position"]) ? $options["switcher_button_position"] : '';
$dark_mode_switch_position = isset($switcher_button_position["dark_mode_switch_position"]) ? $switcher_button_position["dark_mode_switch_position"] : "";
$switch_position_top_right = isset($switcher_button_position["switch_position_top_right"]) ? $switcher_button_position["switch_position_top_right"] : "";
$switch_position_top_right_top = isset($switch_position_top_right["top"]) ? $switch_position_top_right["top"] : "40";
$switch_position_top_right_right = isset($switch_position_top_right["right"]) ? $switch_position_top_right["right"] : "40";
$switch_position_top_left = isset($switcher_button_position["switch_position_top_left"]) ? $switcher_button_position["switch_position_top_left"] : "";
$switch_position_top_left_top = isset($switch_position_top_left["top"]) ? $switch_position_top_left["top"] : "40";
$switch_position_top_left_left = isset($switch_position_top_left["left"]) ? $switch_position_top_left["left"] : "40";
$switch_position_bottom_right = isset($switcher_button_position["switch_position_bottom_right"]) ? $switcher_button_position["switch_position_bottom_right"] : "";
$switch_position_bottom_right_bottom = isset($switch_position_bottom_right["bottom"]) ? $switch_position_bottom_right["bottom"] : "40";
$switch_position_bottom_right_right = isset($switch_position_bottom_right["right"]) ? $switch_position_bottom_right["right"] : "40";
$switch_position_bottom_left = isset($switcher_button_position["switch_position_bottom_left"]) ? $switcher_button_position["switch_position_bottom_left"] : "";
$switch_position_bottom_left_bottom = isset($switch_position_bottom_left["bottom"]) ? $switch_position_bottom_left["bottom"] : "40";
$switch_position_bottom_left_left = isset($switch_position_bottom_left["left"]) ? $switch_position_bottom_left["left"] : "40";
$different_switch_in_mobile = isset($options["different_switch_in_mobile"]) ? $options["different_switch_in_mobile"] : '';
$switch_position_different_in_mobile = isset($different_switch_in_mobile["switch_position_different_in_mobile"]) ? $different_switch_in_mobile["switch_position_different_in_mobile"] : "";
$switch_position_top_right_in_mobile = isset($different_switch_in_mobile["switch_position_top_right_in_mobile"]) ? $different_switch_in_mobile["switch_position_top_right_in_mobile"] : "";
$switch_position_top_right_top_in_mobile = isset($switch_position_top_right_in_mobile["top"]) ? $switch_position_top_right_in_mobile["top"] : "40";
$switch_position_top_right_right_in_mobile = isset($switch_position_top_right_in_mobile["right"]) ? $switch_position_top_right_in_mobile["right"] : "40";
$switch_position_top_left_in_mobile = isset($different_switch_in_mobile["switch_position_top_left_in_mobile"]) ? $different_switch_in_mobile["switch_position_top_left_in_mobile"] : "";
$switch_position_top_left_top_in_mobile = isset($switch_position_top_left_in_mobile["top"]) ? $switch_position_top_left_in_mobile["top"] : "40";
$switch_position_top_left_left_in_mobile = isset($switch_position_top_left_in_mobile["left"]) ? $switch_position_top_left_in_mobile["left"] : "40";
$switch_position_bottom_right_in_mobile = isset($different_switch_in_mobile["switch_position_bottom_right_in_mobile"]) ? $different_switch_in_mobile["switch_position_bottom_right_in_mobile"] : "";
$switch_position_bottom_right_bottom_in_mobile = isset($switch_position_bottom_right_in_mobile["bottom"]) ? $switch_position_bottom_right_in_mobile["bottom"] : "40";
$switch_position_bottom_right_right_in_mobile = isset($switch_position_bottom_right_in_mobile["right"]) ? $switch_position_bottom_right_in_mobile["right"] : "40";
$switch_position_bottom_left_in_mobile = isset($different_switch_in_mobile["switch_position_bottom_left_in_mobile"]) ? $different_switch_in_mobile["switch_position_bottom_left_in_mobile"] : "";
$switch_position_bottom_left_bottom_in_mobile = isset($switch_position_bottom_left_in_mobile["bottom"]) ? $switch_position_bottom_left_in_mobile["bottom"] : "40";
$switch_position_bottom_left_left_in_mobile = isset($switch_position_bottom_left_in_mobile["left"]) ? $switch_position_bottom_left_in_mobile["left"] : "40";
$floating_switch_width = isset($tooltip_on_floating_switch["floating_switch_width"]) ? $tooltip_on_floating_switch["floating_switch_width"] : "";

// Different Switch in Mobile — a completely separate floating switch configuration
// (toggler, size, colors, border, attention) that overrides the default switch only
// on mobile screens (<= 600px). When enabled, a second switch element is rendered and
// the desktop/mobile elements are swapped via the same 600px breakpoint used by the
// mobile-position feature, keeping it cache-safe (no server-side device detection).
$dark_switcher_switch_in_mobile = isset($options["dark_switcher_switch_in_mobile"]) ? $options["dark_switcher_switch_in_mobile"] : '';
$darkify_mobile_unique_id       = $this->unique_id . '_m';
?>
<style type="text/css" class="darkify_inline_css">
    #darkify_switch_<?php echo esc_attr($this->unique_id); ?> {
        <?php foreach ($this->utils->generateSwitchStyles($options) as $key => $value) { ?><?php echo esc_attr($key); ?>: <?php echo esc_attr($value); ?>;
        <?php } ?>
    }
    <?php if ($dark_switcher_switch_in_mobile) {
        // Map the *_in_mobile appearance fields onto the keys generateSwitchStyles() expects,
        // so the mobile switch reuses the exact same CSS-variable pipeline as the default one.
        $mobile_style_options = array(
            'switch_background' => isset($options["switch_background_in_mobile"]) ? $options["switch_background_in_mobile"] : [],
            'switch_icon_color' => isset($options["switch_icon_color_in_mobile"]) ? $options["switch_icon_color_in_mobile"] : [],
            'switch_border'     => isset($options["switch_border_in_mobile"]) ? $options["switch_border_in_mobile"] : [],
        );
    ?>#darkify_switch_<?php echo esc_attr($darkify_mobile_unique_id); ?> {
        <?php foreach ($this->utils->generateSwitchStyles($mobile_style_options) as $key => $value) { ?><?php echo esc_attr($key); ?>: <?php echo esc_attr($value); ?>;
        <?php } ?>
    }
    <?php } ?>

    #darkify_switch_<?php echo esc_attr($this->unique_id); ?><?php if ($dark_switcher_switch_in_mobile) { ?>,
    #darkify_switch_<?php echo esc_attr($darkify_mobile_unique_id); ?><?php } ?> {
        --darkify_switch_position_from_top_right_top: <?php echo esc_attr($switch_position_top_right_top); ?>px;
        --darkify_switch_position_from_top_right_right: <?php echo esc_attr($switch_position_top_right_right); ?>px;
        --darkify_switch_position_from_bottom_right_bottom: <?php echo esc_attr($switch_position_bottom_right_bottom); ?>px;
        --darkify_switch_position_from_bottom_right_right: <?php echo esc_attr($switch_position_bottom_right_right); ?>px;
        --darkify_switch_position_from_top_left_top: <?php echo esc_attr($switch_position_top_left_top); ?>px;
        --darkify_switch_position_from_top_left_left: <?php echo esc_attr($switch_position_top_left_left); ?>px;
        --darkify_switch_position_from_bottom_left_bottom: <?php echo esc_attr($switch_position_bottom_left_bottom); ?>px;
        --darkify_switch_position_from_bottom_left_left: <?php echo esc_attr($switch_position_bottom_left_left); ?>px;
        <?php if ($switch_position_different_in_mobile) { ?>
        --darkify_switch_position_from_top_right_top_in_mobile: <?php echo esc_attr($switch_position_top_right_right_in_mobile); ?>px;
        --darkify_switch_position_from_top_right_right_in_mobile: <?php echo esc_attr($switch_position_top_right_right_in_mobile); ?>px;
        --darkify_switch_position_from_bottom_right_bottom_in_mobile: <?php echo esc_attr($switch_position_bottom_right_bottom_in_mobile); ?>px;
        --darkify_switch_position_from_bottom_right_right_in_mobile: <?php echo esc_attr($switch_position_bottom_right_right_in_mobile); ?>px;
        --darkify_switch_position_from_top_left_top_in_mobile: <?php echo esc_attr($switch_position_top_left_top_in_mobile); ?>px;
        --darkify_switch_position_from_top_left_left_in_mobile: <?php echo esc_attr($switch_position_top_left_top_in_mobile); ?>px;
        --darkify_switch_position_from_bottom_left_bottom_in_mobile: <?php echo esc_attr($switch_position_bottom_left_bottom_in_mobile); ?>px;
        --darkify_switch_position_from_bottom_left_left_in_mobile: <?php echo esc_attr($switch_position_bottom_left_left_in_mobile); ?>px;
         <?php } if ($floating_switch_width) { ?>--darkify_switch_tooltip_bg_color: <?php echo esc_attr($tooltip_color["tooltip_background_color"]); ?>;
        --darkify_switch_tooltip_text_color: <?php echo esc_attr($tooltip_color["tooltip_text_color"]); ?>;
        <?php } ?>
    }
</style>

<?php
$hide_dark_mode_on_mobile_screen = "";

if ($hide_dark_mode_on_mobile) {
    if ($hide_on_mobile["type_of_hide_by"] == "screen_size" || $hide_on_mobile["type_of_hide_by"] == "both") {
        $hide_dark_mode_on_mobile_screen = "darkify_hide_on_mobile";
    }
}

if ($dark_mode_switch_position == "") {
    $floating_switch_position = "darkify_bottom_right";
} else {
    $floating_switch_position = "darkify_" . $dark_mode_switch_position;
}
if ($switch_position_different_in_mobile) {
    $floating_switch_position .= " darkify_" . $different_switch_in_mobile["switch_position_in_mobile"] . "_in_mobile";
}

$tooltip_classes = "";
if ($floating_switch_width) {
    // Each corner only offers the two tooltip sides that point into the page
    // (the `options_by` map on the Tooltip Position field). A position saved
    // before that rule, or before the switch was moved to another corner, may
    // not be one of them — it is mirrored (left <-> right, top <-> bottom)
    // rather than left to open off the edge of the screen.
    $darkify_tooltip_position = isset($tooltip_on_floating_switch["tooltip_position"]) ? $tooltip_on_floating_switch["tooltip_position"] : 'left';
    $darkify_tooltip_sides = array(
        'bottom_right' => array('top', 'left'),
        'bottom_left'  => array('top', 'right'),
        'top_right'    => array('bottom', 'left'),
        'top_left'     => array('bottom', 'right'),
    );
    $darkify_tooltip_mirror = array('left' => 'right', 'right' => 'left', 'top' => 'bottom', 'bottom' => 'top');
    $darkify_tooltip_corner = str_replace('darkify_', '', explode(' ', $floating_switch_position)[0]);
    if (isset($darkify_tooltip_sides[$darkify_tooltip_corner]) && !in_array($darkify_tooltip_position, $darkify_tooltip_sides[$darkify_tooltip_corner], true)) {
        $darkify_tooltip_allowed = $darkify_tooltip_sides[$darkify_tooltip_corner];
        $darkify_tooltip_position = (isset($darkify_tooltip_mirror[$darkify_tooltip_position]) && in_array($darkify_tooltip_mirror[$darkify_tooltip_position], $darkify_tooltip_allowed, true))
            ? $darkify_tooltip_mirror[$darkify_tooltip_position]
            : $darkify_tooltip_allowed[0];
    }
    $tooltip_classes = "darkify_tooltip darkify_tooltip_" . $darkify_tooltip_position;
}

$darkify_absolute_position = isset($options["enable_absolute_position"]) ? $options["enable_absolute_position"] : '';
$position_class = '';
if ($darkify_absolute_position) {
    $position_class = ' darkify_absolute_position';
}
$enable_dark_switcher = isset($options["enable_dark_switcher"]) ? $options["enable_dark_switcher"] : 'classic';
$switch_size = isset($options["switch_size"]) ? $options["switch_size"] : '1';
$switch_size_custom = isset($options["switch_size_custom"]) ? $options["switch_size_custom"] : '100';
if ($switch_size === 'custom') {
    $switch_size = $switch_size_custom / 100;
}

$tooltip_text = isset($tooltip_on_floating_switch['tooltip_text']) ? $tooltip_on_floating_switch['tooltip_text'] : esc_html__('Toggle Dark Mode', 'darkify');

// Only swap in a separate mobile switch when the feature is on AND the switch is not
// already hidden on mobile by the "hide on mobile" (screen size) option.
$render_mobile_switch = $dark_switcher_switch_in_mobile && !$hide_dark_mode_on_mobile_screen;

// switcher_wrapper() returns markup with every dynamic value escaped via
// esc_attr() at assembly, so the returned string is safe to print as-is.
echo Darkify::switcher_wrapper('switcher', $this->unique_id, $enable_dark_switcher, $switch_size, $position_class, $floating_switch_position, $tooltip_classes, $hide_dark_mode_on_mobile_screen, $floating_switch_width, $tooltip_text); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

if ($render_mobile_switch) {
    $enable_dark_switcher_in_mobile = isset($options["enable_dark_switcher_in_mobile"]) ? $options["enable_dark_switcher_in_mobile"] : 'classic';
    $switch_size_in_mobile = isset($options["switch_size_in_mobile"]) ? $options["switch_size_in_mobile"] : '1';
    $switch_size_custom_in_mobile = isset($options["switch_size_custom_in_mobile"]) ? $options["switch_size_custom_in_mobile"] : '100';
    if ($switch_size_in_mobile === 'custom') {
        $switch_size_in_mobile = $switch_size_custom_in_mobile / 100;
    }

    // Attention effect for the mobile switch is applied as a class on render (the default
    // switch receives its class from JS via darkify_init_attention_effect()).
    $enable_switch_attention_in_mobile = isset($options["enable_switch_attention_in_mobile"]) ? $options["enable_switch_attention_in_mobile"] : '';
    $switch_attention_effect_in_mobile = isset($options["switch_attention_effect_in_mobile"]) ? $options["switch_attention_effect_in_mobile"] : 'pulse';
    $mobile_extra_class = $position_class;
    if ($enable_switch_attention_in_mobile && $switch_attention_effect_in_mobile && $switch_attention_effect_in_mobile !== 'none') {
        $mobile_extra_class = trim($mobile_extra_class . ' darkify_attention_' . $switch_attention_effect_in_mobile);
    }

    // See the note above: switcher_wrapper() escapes each value internally.
    echo Darkify::switcher_wrapper('switcher', $darkify_mobile_unique_id, $enable_dark_switcher_in_mobile, $switch_size_in_mobile, $mobile_extra_class, $floating_switch_position, $tooltip_classes, '', $floating_switch_width, $tooltip_text); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    ?>
    <style type="text/css" class="darkify_inline_css">
        /* Swap the default switch (desktop) and the mobile switch (<= 600px) by only ever
           toggling `display: none` — never forcing a display value on the visible element,
           so each switch keeps the flex centering defined by .darkify_switch_style (and any
           per-variant display such as inline-block) intact. */
        @media only screen and (min-width: 601px) {
            #darkify_switch_<?php echo esc_attr($darkify_mobile_unique_id); ?> {
                display: none !important;
            }
        }
        @media only screen and (max-width: 600px) {
            #darkify_switch_<?php echo esc_attr($this->unique_id); ?> {
                display: none !important;
            }
        }
    </style>
<?php }