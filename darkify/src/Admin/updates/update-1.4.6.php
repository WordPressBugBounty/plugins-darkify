<?php
// don't call the file directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Update version.
 */
update_option('darkify_version', '1.4.6');
update_option('darkify_db_version', '1.4.6');

/**
 * Convert old data keys to new ones for version 1.4.6
 */
function darkify_convert_old_to_new_data_1_4_6($options)
{
    if (empty($options['show_in_menu'])) {
        return; // Nothing to migrate
    }

    $show_in_menu = $options['show_in_menu'];

    // Extract old values
    $enable_switch_in_menu       = isset($show_in_menu['enable_switch_in_menu']) ? $show_in_menu['enable_switch_in_menu'] : '';
    $switch_in_menu_location     = isset($show_in_menu['switch_in_menu_location']) ? $show_in_menu['switch_in_menu_location'] : '';
    $darkify_menu_shortcode_helper = isset($show_in_menu['darkify_menu_shortcode_helper']) ? $show_in_menu['darkify_menu_shortcode_helper'] : '';

    // Prepare new structure
    $new_show_in_menu = array(
        'enable_switch_in_menu' => $enable_switch_in_menu,
    );

    // If enabled, ensure at least one group item exists
    if ($enable_switch_in_menu) {
        $new_show_in_menu['select_menus'] = array(
            array(
                'switch_in_menu_location' => $switch_in_menu_location,
                'darkify_menu_shortcode_helper' => $darkify_menu_shortcode_helper,
            )
        );
    }

    // Replace old structure
    $options['show_in_menu'] = $new_show_in_menu;

    // Save updated options
    update_option('darkify', $options);
}

/**
 * Update old to new data on load
 */
$options = get_option('darkify');
if ($options) {
    darkify_convert_old_to_new_data_1_4_6($options);
}
