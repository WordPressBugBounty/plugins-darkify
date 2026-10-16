<?php

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @link       https://themeatelier.net
 * @since      1.0.0
 *
 * @package darkify
 * @subpackage darkify/src/Helpers
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */

namespace ThemeAtelier\Darkify\Admin\ReviewNotice;

/**
 * The Helpers class to manage all public facing stuffs.
 *
 * @since 1.0.0
 */
class ReviewNotice
{
    public function __construct()
    {
        add_action('admin_notices', [$this, 'display_admin_notice']);
        add_action('wp_ajax_darkify-never-show-review-notice', [$this, 'dismiss_review_notice']);
    }
    public function display_admin_notice()
    {
        // Show only to Admins.
        if (! current_user_can('manage_options')) {
            return;
        }

        // Variable default value.
        $review = get_option('darkify_review_notice_dismiss');
        $time   = time();
        $load   = false;

        if (! $review) {
            $review = array(
                'time'      => $time,
                'dismissed' => false,
            );
            add_option('darkify_review_notice_dismiss', $review);
        } else {
            // Check if it has been dismissed or not.
            if ((isset($review['dismissed']) && ! $review['dismissed']) && (isset($review['time']) && (($review['time'] + (DAY_IN_SECONDS * 3)) <= $time))) {
                $load = true;
            }
        }

        // If we cannot load, return early.
        if (! $load) {
            return;
        }
?>
        <div id="darkify-review-notice" class="darkify-review-notice">
            <div class="darkify-plugin-icon">
                <img src="<?php echo esc_url(DARKIFY_DIR_URL . 'src/Admin/assets/images/darkify-icon.svg'); ?>" alt="Darkify dark mode plugin">
            </div>
            <div class="darkify-notice-text">
                <?php
                $review    = get_option('darkify_review_notice_dismiss');
             
                ?>
                <h3><?php echo esc_html__('Enjoying', 'darkify') ?> <strong><?php echo esc_html__('Darkify', 'darkify') ?></strong>?</h3>
                <p><?php echo esc_html__('We hope you had a wonderful experience using', 'darkify') ?> <strong><?php echo esc_html__('Darkify', 'darkify') ?></strong>. <?php echo esc_html__('Please take a moment to leave a review on', 'darkify') ?> <a href="https://wordpress.org/support/plugin/darkify/reviews/#new-post" target="_blank"><strong><?php echo esc_html__('WordPress.org', 'darkify') ?></strong></a>.
                    <?php echo esc_html__('Your positive review will help us improve. Thank you!', 'darkify') ?> 😊</p>

                <p class="darkify-review-actions">
                    <a href="https://wordpress.org/support/plugin/darkify/reviews/#new-post" target="_blank" class="button button-primary notice-dismissed rate-darkify"><?php echo esc_html__('Ok, you deserve', 'darkify') ?> ★★★★★</a>
                    <a href="#" class="notice-dismissed remind-me-later"><span class="dashicons dashicons-clock"></span><?php echo esc_html__('Nope, maybe later', 'darkify') ?>
                    </a>
                    <a href="#" class="notice-dismissed never-show-again"><span class="dashicons dashicons-dismiss"></span><?php echo esc_html__('Never show again', 'darkify') ?></a>
                </p>
            </div>
        </div>

        <script type='text/javascript'>
            jQuery(document).ready(function($) {
                $(document).on('click', '#darkify-review-notice.darkify-review-notice .notice-dismissed', function(event) {
                    if ($(this).hasClass('rate-darkify')) {
                        var notice_dismissed_value = "1";
                    }
                    if ($(this).hasClass('remind-me-later')) {
                        var notice_dismissed_value = "2";
                        event.preventDefault();
                    }
                    if ($(this).hasClass('never-show-again')) {
                        var notice_dismissed_value = "3";
                        event.preventDefault();
                    }

                    $.post(ajaxurl, {
                        action: 'darkify-never-show-review-notice',
                        notice_dismissed_data: notice_dismissed_value,
                        nonce: '<?php echo esc_attr(wp_create_nonce('darkify_review_notice')); ?>'
                    });

                    $('#darkify-review-notice.darkify-review-notice').hide();
                });
            });
        </script>
<?php
    }

    /**
     * Dismiss review notice
     *
     * @since  2.0.4
     *
     * @return void
     **/
    public function dismiss_review_notice()
    {
        $post_data = wp_unslash($_POST);
        $review    = get_option('darkify_review_notice_dismiss');

        if (! isset($post_data['nonce']) || ! wp_verify_nonce(sanitize_key($post_data['nonce']), 'darkify_review_notice')) {
            return;
        }

        if (! $review) {
            $review = array();
        }
        switch (isset($post_data['notice_dismissed_data']) ? $post_data['notice_dismissed_data'] : '') {
            case '1':
                $review['time']      = time();
                $review['dismissed'] = true;
                break;
            case '2':
                $review['time']      = time();
                $review['dismissed'] = false;
                break;
            case '3':
                $review['time']      = time();
                $review['dismissed'] = true;
                break;
        }
        update_option('darkify_review_notice_dismiss', $review);
        die;
    }
}
