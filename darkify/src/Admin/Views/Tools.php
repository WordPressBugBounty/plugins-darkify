<?php

/**
 * Tools class for Tools options.
 *
 * @link       https://themeatelier.net
 * @since      1.0.0
 *
 * @package darkify
 * @subpackage darkify/src/Admin/Views/Tools
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\Views;

use ThemeAtelier\Darkify\Admin\Schema\SchemaRegistry;

class Tools
{
    /**
     * Create Option fields for the setting options.
     *
     * @param string $prefix Option setting key prefix.
     * @return void
     */
    public static function options($prefix)
    {
        SchemaRegistry::createSection(
            $prefix,
            array(
                'id'     => 'tools',
                'title'  => esc_html__('Tools', 'darkify'),
                'icon'   => 'icofont-holding-hands',
                'fields' => array(
                    // Intro line, rendered plain above the section cards.
                    array(
                        'type'    => 'page_hint',
                        'content' => esc_html__('Maintenance, backup, and restore utilities.', 'darkify'),
                    ),
                    // ===== DATA =====
                    array(
                        'type'          => 'heading',
                        'title'         => esc_html__('Data', 'darkify'),
                        'section_start' => true,
                    ),
                    array(
                        'id'    => 'darkify_data_remove',
                        'type'  => 'switcher',
                        'title' => esc_html__('Clean-up Data on Deletion', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                            esc_html__('Enable this option to completely remove all Darkify data when the plugin is deleted.', 'darkify') .
                            '</div>',
                        'text_on'    => esc_html__('Enabled', 'darkify'),
                        'text_off'   => esc_html__('Disabled', 'darkify'),
                        'text_width' => 100,
                    ),
                    // ===== BACKUP & RESTORE =====
                    array(
                        'type'          => 'heading',
                        'title'         => esc_html__('Backup & Restore', 'darkify'),
                        'section_start' => true,
                    ),
                    array(
                        'title' => esc_html__('Backup and Restore Settings', 'darkify'),
                        'title_help' =>
                        '<div class="darkify-info-label">' .
                            esc_html__('Use these options to back up your settings for safekeeping or restore them when needed. You can export your current settings as a .json file and import it anytime to restore your configuration.', 'darkify') .
                            '</div>',
                        'type'  => 'backup',
                    ),
                ),
            )
        );
    }
}
