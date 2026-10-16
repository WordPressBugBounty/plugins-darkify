<?php
/**
 *  The admin offer banner.
 *
 * @since        2.1.11
 * @version      2.1.11
 *
 * @package    Darkify
 * @subpackage Darkify/src/Admin/Helpers
 * @author     ThemeAtelier<themeatelierbd@gmail.com>
 */
namespace ThemeAtelier\Darkify\Admin\ReviewNotice;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The class for all admin notices on the backend.
 */
class ThemeAtelier_Offer_Banner {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since
	 */
	private static $instance = null;

	/**
	 * Class constructor.
	 */
	private function __construct() {
		add_action( 'admin_notices', array( $this, 'render_offer_banner' ) );
		add_action( 'wp_ajax_themeatelier_dismiss_offer_banner', array( $this, 'dismiss_offer_banner' ) );
	}

	/**
	 * Retrieves the singleton instance of the class.
	 *
	 * This method ensures that only one instance of the class is created (singleton pattern).
	 *
	 * @return self The singleton instance of the class.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Retrieves the active offers.
	 *
	 * @return array The list of active offers.
	 */
	public function get_active_offers() {
		$now = time();

		// Define offer durations.
		$offers = array(
			'halloween_deal'     => array(
				'id'    => 'halloween_deal_2026',
				'start' => strtotime( '2026-10-19 00:00:00' ),
				'end'   => strtotime( '2026-11-05 23:59:59' ),
				'image' => DARKIFY_DIR_URL . 'src/Admin/ReviewNotice/assets/img/halloween-sale.svg',
				'link'  => 'https://darkifywp.com/pricing/?utm_source=darkify_plugin&utm_medium=offer_banner&utm_campaign=halloween_deal_2026',
			),

			'black_friday_deal'     => array(
				'id'    => 'black_friday_deal_2026',
				'start' => strtotime( '2026-11-06 00:00:00' ),
				'end'   => strtotime( '2026-12-15 23:59:59' ),
				'image' => DARKIFY_DIR_URL . 'src/Admin/ReviewNotice/assets/img/black-friday-sale.svg',
				'link'  => 'https://darkifywp.com/pricing/?utm_source=darkify_plugin&utm_medium=offer_banner&utm_campaign=black_friday_deal_2026',
			),

			'christmas_deal'     => array(
				'id'    => 'christmas_deal_2026',
				'start' => strtotime( '2026-12-20 00:00:00' ),
				'end'   => strtotime( '2027-01-15 23:59:59' ),
				'image' => DARKIFY_DIR_URL . 'src/Admin/ReviewNotice/assets/img/christmas-sale.svg',
				'link'  => 'https://darkifywp.com/pricing/?utm_source=darkify_plugin&utm_medium=offer_banner&utm_campaign=christmas_deal_2026',
			),

		);

		$active_offers = array();

		foreach ( $offers as $key => $offer ) {
			if ( $now >= $offer['start'] && $now <= $offer['end'] ) {
				$active_offers[ $key ] = $offer;
			}
		}

		return $active_offers;
	}

	/**
	 * Renders the offer banner on the page.
	 *
	 * @return void
	 */
	public function render_offer_banner() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_offers = $this->get_active_offers();

		if ( empty( $active_offers ) ) {
			return;
		}

		foreach ( $active_offers as $offer ) {
			$option_key = 'themeatelier_offer_banner_dismissed_' . $offer['id'];

			// Check dismissed status.
			if ( get_option( $option_key ) ) {
				continue;
			}

			$nonce = wp_create_nonce( 'darkify_offer_dismiss' );
			?>
			<div id="themeatelier-offer-banner" class="notice notice-info is-dismissible">
				<a href="<?php echo esc_url( $offer['link'] ); ?>" target="_blank">
					<img src="<?php echo esc_url( $offer['image'] ); ?>" alt="ThemeAtelier Offer" style="width:100%;height:auto;">
				</a>
			
				<button type="button"
					class="notice-dismiss themeatelier-offer-banner-dismiss"
					data-offer-id="<?php echo esc_attr( $offer['id'] ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
				</button>
			</div>
				<?php
		}
		?>
		<script type="text/javascript">
			(function($){
				$(document).on('click', '#themeatelier-offer-banner .notice-dismiss', function(e){
					e.preventDefault();
					const nonce = $(this).data('nonce');
					const offerID = $(this).data('offer-id')
					$.post(ajaxurl, {
						action: 'themeatelier_dismiss_offer_banner',
						offer_id: offerID,
						nonce: nonce
					});
					$('#themeatelier-offer-banner').fadeOut(300);
				});
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * Handles the AJAX request to dismiss the offer banner.
	 *
	 * @return void
	 */
	public function dismiss_offer_banner() {
		check_ajax_referer( 'darkify_offer_dismiss', 'nonce' );
		$offer_id = isset( $_POST['offer_id'] ) ? sanitize_text_field( wp_unslash( $_POST['offer_id'] ) ) : '';

		update_option( 'themeatelier_offer_banner_dismissed_' . $offer_id, true );

		wp_send_json_success();
	}
}