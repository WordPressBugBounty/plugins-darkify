<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://themeatelier.net/
 * @since             1.0.0
 * @package           Darkify
 *
 * @wordpress-plugin
 * Plugin Name:       Darkify
 * Plugin URI:        https://darkifywp.com/
 * Description:       Darkify is for a stylish, modern darkmode look that people love.
 * Requires at least: 5.5
 * Requires PHP:      7.1
 * Version:           2.2.3
 * Author:            ThemeAtelier
 * Author URI:        https://themeatelier.net/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       darkify
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

require_once __DIR__ . '/vendor/autoload.php';

use ThemeAtelier\Darkify\Includes\Darkify;

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
if (!defined('DARKIFY_VERSION')) {
    define('DARKIFY_VERSION', '2.2.3');
}
if (!defined('DARKIFY_FILE')) {
    define('DARKIFY_FILE', __FILE__);
}
if (!defined('DARKIFY_DIR_URL')) {
    define('DARKIFY_DIR_URL', plugin_dir_url(__FILE__));
}
if (!defined('DARKIFY_BASENAME')) {
    define('DARKIFY_BASENAME', plugin_basename(__FILE__));
}
if (!defined('DARKIFY_PATH')) {
    define('DARKIFY_PATH', plugin_dir_path(__FILE__));
}
if (!defined('DARKIFY_DIR_NAME')) {
    define('DARKIFY_DIR_NAME', dirname(__FILE__));
}
if (!defined('DARKIFY_DEMO_URL')) {
    define('DARKIFY_DEMO_URL', 'https://darkifywp.com/');
}
if (!defined('DARKIFY_DOCS_URL')) {
    define('DARKIFY_DOCS_URL', 'https://darkifywp.com/docs/');
}

function darkify_run()
{
    // Launch the plugin.
    $darkify = new Darkify();
    $darkify->run();
}

/**
 * Pro version check.
 *
 * @return boolean
 */
include_once ABSPATH . 'wp-admin/includes/plugin.php';
if (! (is_plugin_active('darkify-pro/darkify-pro.php') || is_plugin_active_for_network('darkify-pro/darkify-pro.php'))) {
    darkify_run();
}


/**
 * Initialize the plugin tracker
 *
 * @return void
 */
function darkify_appsero_init()
{
    if (!class_exists('DarkifyAppSero\Insights')) {
        require_once DARKIFY_PATH . 'src/Admin/appsero/Client.php';
    }

    $client = new DarkifyAppSero\Client('16b2d91f-fd42-42d1-b199-5ae98213496a', 'Darkify Dark Mode', __FILE__);
    // Active insights
    $client->insights()->init();
}

add_action('plugins_loaded', 'darkify_appsero_init');