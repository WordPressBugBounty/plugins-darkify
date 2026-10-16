<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://themeatelier.net/
 * @since      1.0.0
 *
 * @package    Darkify
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}


/**
 * Delete plugin data function.
 *
 * @return void
 */
function darkify_delete_plugin_data()
{
    // Delete plugin option settings.
    $options = [
        'darkify',
        'darkify_pro_version',
        'darkify_pro_db_version',
        'darkify_pro_first_version',
        'darkify_pro_activation_date',
        'themeatelier_offer_banner_dismissed_new_year_2026',
    ];

    foreach ($options as $option_name) {
        delete_option($option_name);
        delete_site_option($option_name);
    }
}

// Load WPTP file.
require plugin_dir_path(__FILE__) . '/darkify.php';
$darkify_plugin_settings = get_option('darkify');
$darkify_data_delete     = isset($darkify_plugin_settings['darkify_data_remove']) ? $darkify_plugin_settings['darkify_data_remove'] : false;

if ($darkify_data_delete) {
	darkify_delete_plugin_data();
}
