<?php
/**
 * Class to display affiliate details metabox at order add/edit.
 *
 * @package  affiliate-for-woocommerce/includes/admin/
 * @since    8.0.0
 * @version  1.0.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

if ( ! class_exists( 'AFWC_Admin_Order_Affiliate_Details' ) ) {

	/**
	 * Main class for Affiliate details metabox
	 */
	class AFWC_Admin_Order_Affiliate_Details {

		/**
		 * Variable to hold instance of AFWC_Admin_Order_Affiliate_Details
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'add_meta_boxes', array( $this, 'add_afwc_custom_box' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_order_metabox_scripts' ) );
		}

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Admin_Order_Affiliate_Details Singleton object of this class
		 */
		public static function get_instance() {
			// Check if instance is already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Function to enqueue required scripts for order add/edit page
		 */
		public function enqueue_order_metabox_scripts() {

			$current_screen    = is_callable( 'get_current_screen' ) ? get_current_screen() : null;
			$current_screen_id = ( ! empty( $current_screen ) && $current_screen instanceof WP_Screen ) ? ( ! empty( $current_screen->id ) ? $current_screen->id : '' ) : '';
			if ( empty( $current_screen_id ) ) {
				return;
			}

			$wc_shop_order_screen_id = function_exists( 'wc_get_page_screen_id' ) && is_callable( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

			if ( $wc_shop_order_screen_id !== $current_screen_id ) {
				return;
			}

			if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
				if ( empty( $_REQUEST['action'] ) ) { // phpcs:ignore
					return;
				}
			} else {
				if ( 'edit-shop_order' === $current_screen_id ) {
					return;
				}
			}

			$plugin_data = Affiliate_For_WooCommerce::get_plugin_data();

			wp_enqueue_script( 'afwc-setting-js', AFWC_PLUGIN_URL . '/assets/js/afwc-admin-order-metabox.js', array( 'jquery' ), $plugin_data['Version'], true );
			wp_enqueue_style( 'afwc-setting-css', AFWC_PLUGIN_URL . '/assets/css/admin/afwc-admin-order-metabox.css', array(), $plugin_data['Version'] );

			wp_register_script( 'affiliate-user-search', AFWC_PLUGIN_URL . '/assets/js/affiliate-search.js', array( 'jquery', 'wp-i18n', 'select2' ), $plugin_data['Version'], true );
			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'affiliate-user-search', 'affiliate-for-woocommerce' );
			}
			wp_enqueue_script( 'affiliate-user-search' );

			wp_localize_script(
				'affiliate-user-search',
				'affiliateParams',
				array(
					'ajaxurl'        => admin_url( 'admin-ajax.php' ),
					'security'       => wp_create_nonce( 'afwc-search-affiliate-users' ),
					'allowSelfRefer' => afwc_allow_self_refer(),
				)
			);
		}

		/**
		 * Function to add custom meta box in order add/edit screen.
		 */
		public function add_afwc_custom_box() {
			$current_screen    = is_callable( 'get_current_screen' ) ? get_current_screen() : null;
			$current_screen_id = ( ! empty( $current_screen ) && $current_screen instanceof WP_Screen && ! empty( $current_screen->id ) ) ? $current_screen->id : '';

			if ( empty( $current_screen_id ) ) {
				return;
			}

			$wc_container     = is_callable( 'wc_get_container' ) ? wc_get_container() : null;
			$order_controller = is_object( $wc_container )
								&& is_callable( array( $wc_container, 'has' ) )
								&& $wc_container->has( CustomOrdersTableController::class )
								&& is_callable( array( $wc_container, 'get' ) )
								? $wc_container->get( CustomOrdersTableController::class ) : null;
			$screen           = is_object( $order_controller ) && is_callable( array( $order_controller, 'custom_orders_table_usage_is_enabled' ) ) && $order_controller->custom_orders_table_usage_is_enabled()
							? wc_get_page_screen_id( 'shop-order' )
							: 'shop_order';

			if ( $current_screen_id !== $screen ) {
				return;
			}

			add_meta_box( 'afwc_order', _x( 'Affiliate details', 'Affiliate\'s order meta box title', 'affiliate-for-woocommerce' ), array( $this, 'affiliate_details_in_order' ), $screen, 'side', 'low' );
		}

		/**
		 * Function to display affiliate details.
		 *
		 * @param object $post_or_order_object The post object or order object currently being edited.
		 */
		public function affiliate_details_in_order( $post_or_order_object = null ) {
			$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
			// $post_or_order_object should not be used directly below this point.

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : 0;
			if ( empty( $order_id ) ) {
				return;
			}

			$is_commission_recorded = $order->get_meta( 'is_commission_recorded', true );

			$linked_affiliate_data = $this->get_linked_affiliate_data( $order_id, $is_commission_recorded );
			$user_id               = is_array( $linked_affiliate_data ) && ! empty( $linked_affiliate_data['user_id'] ) ? $linked_affiliate_data['user_id'] : '';
			$user_string           = is_array( $linked_affiliate_data ) && ! empty( $linked_affiliate_data['user_string'] ) ? $linked_affiliate_data['user_string'] : '';
			$allow_clear           = is_array( $linked_affiliate_data ) && ! empty( $linked_affiliate_data['allow_clear'] ) ? $linked_affiliate_data['allow_clear'] : 'true';
			$disabled              = is_array( $linked_affiliate_data ) && ! empty( $linked_affiliate_data['disabled'] ) ? $linked_affiliate_data['disabled'] : '';

			$order_commission_data     = $this->get_order_commission_data( $order );
			$total_commission          = is_array( $order_commission_data ) && ! empty( $order_commission_data['total_commission'] ) ? $order_commission_data['total_commission'] : 0;
			$has_multi_tier_commission = is_array( $order_commission_data ) && ! empty( $order_commission_data['has_multi_tier_commission'] ) ? $order_commission_data['has_multi_tier_commission'] : false;
			?>
			<div class="options_group afwc-field">
				<div class="afwc-link-unlink-affiliate-section">
					<h4 class="afwc-link-unlink-affiliate-title"><label for="afwc_referral_order_of"><?php echo esc_html_x( 'Assigned to affiliate', 'Title of link/unlink affiliate in order metabox', 'affiliate-for-woocommerce' ); ?></label></h4>
					<p class="afwc-field-description"><?php echo esc_html_x( 'Search affiliate by email, username, name or user ID to assign this order to them.', 'Description for search and assign affiliate in order metabox', 'affiliate-for-woocommerce' ); ?></p>
					<select id="afwc_referral_order_of" name="afwc_referral_order_of" class="afwc-affiliate-search" data-placeholder="<?php echo esc_attr_x( 'Search by email, username or name', 'affiliate search placeholder', 'affiliate-for-woocommerce' ); ?>" data-allow-clear="<?php echo esc_attr( $allow_clear ); ?>" data-action="afwc_json_search_affiliates" <?php echo esc_attr( $disabled ); ?>>
						<?php if ( ! empty( $user_id ) ) { ?>
							<option value="<?php echo esc_attr( $user_id ); ?>" selected="selected"><?php echo esc_html( wp_kses_post( $user_string ) ); ?><option>
						<?php } ?>
					</select>
				</div>
				<?php if ( 'yes' === $is_commission_recorded ) { ?>
					<hr>
					<div class="afwc-total-commission-section">
						<div class="afwc-total-commission-wrapper">
							<h4 class="afwc-total-commission-title"><?php echo esc_html_x( 'Total commissions:', 'Title of total commission in order metabox', 'affiliate-for-woocommerce' ); ?></h4>
							<span><?php echo wp_kses_post( $total_commission ); ?></span>
						</div>
						<?php if ( $has_multi_tier_commission ) { ?>
							<p class="afwc-field-description afwc-total-commission-description"><?php echo esc_html_x( '(including multi-tier commissions)', 'Description for total commission when multi-tier setting is enable', 'affiliate-for-woocommerce' ); ?></p>
						<?php } ?>
					</div>
				<?php } ?>
			</div>
			<?php
		}

		/**
		 * Get linked affiliate data for link/unlink select2
		 *
		 * @param int    $order_id Order ID.
		 * @param string $is_commission_recorded Value 'yes' is order has any commission.
		 * @return array
		 */
		public function get_linked_affiliate_data( $order_id = 0, $is_commission_recorded = 'no' ) {
			$afwc_api       = AFWC_API::get_instance();
			$affiliate_data = is_callable( array( $afwc_api, 'get_affiliate_by_order' ) ) ? $afwc_api->get_affiliate_by_order( $order_id ) : array();

			$user_string = '';
			$user_id     = '';
			if ( 'yes' === $is_commission_recorded && ! empty( $affiliate_data ) ) {
				$user_id = afwc_get_user_id_based_on_affiliate_id( $affiliate_data['affiliate_id'] );
				if ( ! empty( $user_id ) ) {
					$user = get_user_by( 'id', $user_id );
					if ( is_object( $user ) && $user instanceof WP_User ) {
						$user_string = sprintf(
							/* translators: 1: user display name 2: user ID 3: user email */
							esc_html_x( '%1$s (#%2$s &ndash; %3$s)', 'linked affiliate info. in order metabox select2', 'affiliate-for-woocommerce' ),
							$user->display_name,
							absint( $user_id ),
							$user->user_email
						);
					}
				}
			}

			return array(
				'user_id'     => $user_id,
				'user_string' => $user_string,
				'allow_clear' => ! empty( $affiliate_data['status'] ) && 'paid' === $affiliate_data['status'] ? 'false' : 'true',
				'disabled'    => ! empty( $affiliate_data['status'] ) && 'paid' === $affiliate_data['status'] ? 'disabled' : '',
			);
		}

		/**
		 * Get commission amount and multi-tier info of the order.
		 *
		 * @param WC_Order $order Order object.
		 * @return array ['total_commission' => Formatted the amount with a currency symbol , 'has_multi_tier_commission' => bool].
		 */
		public function get_order_commission_data( $order = null ) {
			if ( ! $order instanceof WC_Order ) {
				return array();
			}

			$afwc_set_commission       = $order->get_meta( 'afwc_set_commission', true );
			$total_commission          = 0;
			$has_multi_tier_commission = false;
			if ( ! empty( $afwc_set_commission ) && is_array( $afwc_set_commission ) && ! empty( $afwc_set_commission['commissions_chain'] ) && is_array( $afwc_set_commission['commissions_chain'] ) ) {
				$order_commissions_chain   = $afwc_set_commission['commissions_chain'];
				$total_commission          = array_sum( $order_commissions_chain );
				$has_multi_tier_commission = count( $order_commissions_chain ) > 1 ? true : false;
			}

			$currency = ( is_callable( array( $order, 'get_currency' ) ) ) ? $order->get_currency() : get_woocommerce_currency();
			return array(
				'total_commission'          => wc_price( $total_commission, array( 'currency' => $currency ) ),
				'has_multi_tier_commission' => $has_multi_tier_commission,
			);
		}

	}

}

AFWC_Admin_Order_Affiliate_Details::get_instance();
