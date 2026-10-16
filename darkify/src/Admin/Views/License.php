<?php

/**
 * License field
 *
 * @link       https://themeatelier.net
 * @since      1.0.0
 *
 * @package darkify
 * @subpackage darkify/src/Admin/Views/Control
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\Views;

use ThemeAtelier\Darkify\Admin\Schema\SchemaRegistry as Darkify;

class License
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
                'id'          => 'license',
                'title'       => esc_html__('License', 'darkify'),
                'icon'        => 'icofont-key',
                'fields'      => array(
                    array(
                        'id'   => 'license_key',
                        'type' => 'license',
                        ),
                ),
            ),
        );
    }
}
