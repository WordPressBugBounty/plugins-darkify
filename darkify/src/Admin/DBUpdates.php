<?php

/**
 * The admin-facing functionality of the plugin.
 *
 * @link       https://themeatelier.net
 * @since      1.0.0
 *
 * @package darkify
 * @subpackage darkify/Admin
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin;

/**
 * The admin class
 */
class DBUpdates
{
    /**
     * DB updates that need to be run
     *
     * @var array
     */
    private static $updates = array(
        '1.4.5' => 'updates/update-1.4.5.php',
    );
    
    /**
     * The class constructor.
     *
     */
    function __construct()
    {
        add_action('plugins_loaded', array($this, 'darkify_updates'));
    }



    /**
     * Check if an update is needed.
     *
     * @return bool
     */
    public function is_needs_update() {
        $installed_version = get_option('darkify_version');
        $first_version     = get_option('darkify_first_version');
        $activation_date   = get_option('darkify_activation_date');

        if (false === $installed_version) {
            update_option('darkify_version', '1.4.5');
            update_option('darkify_db_version', '1.4.5');
        }
        if (false === $first_version) {
            update_option('darkify_first_version', DARKIFY_VERSION);
        }
        if (false === $activation_date) {
            update_option('darkify_activation_date', current_time('timestamp'));
        }

        if (version_compare($installed_version, DARKIFY_VERSION, '<')) {
            return true;
        }
        return false;
    }

    /**
     * Perform all updates.
     *
     */
    public function darkify_updates() {
        if (!$this->is_needs_update()) {
            return;
        }

        $installed_version = get_option('darkify_version');

        foreach (self::$updates as $version => $path) {
            if (version_compare($installed_version, $version, '<')) {
                include $path;
                update_option('darkify_version', $version);
            }
        }

        update_option('darkify_version', DARKIFY_VERSION);
    }
}
