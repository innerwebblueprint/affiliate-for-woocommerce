<?php
/**
 * Class to link and unlink affiliates from orders.
 *
 * @package  affiliate-for-woocommerce/includes/admin/
 * @since    2.1.1
 * @version  1.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

if ( ! class_exists( 'AFWC_Admin_Link_Unlink_In_Order' ) ) {

	/**
	 * Main class for Affiliate Order linking and Unlinking functionality
	 */
	class AFWC_Admin_Link_Unlink_In_Order {

		/**
		 * Variable to hold instance of AFWC_Admin_Link_Unlink_In_Order
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'woocommerce_process_shop_order_meta', array( $this, 'link_unlink_affiliate_in_order' ) );
		}

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Admin_Link_Unlink_In_Order Singleton object of this class
		 */
		public static function get_instance() {
			// Check if instance is already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Function to do database updates when linking/unlinking affiliate from the order.
		 *
		 * @param int $order_id The Order ID.
		 */
		public function link_unlink_affiliate_in_order( $order_id = 0 ) {

			if ( empty( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( wc_clean( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) { // phpcs:ignore
				return;
			}

			if ( empty( $order_id ) ) {
				return;
			}

			global $wpdb;

			$affiliate_id = ! empty( $_POST['afwc_referral_order_of'] ) ? wc_clean( wp_unslash( $_POST['afwc_referral_order_of'] ) ) : ''; // phpcs:ignore

			if ( ! empty( $affiliate_id ) ) {
				$old_affiliate_id = $wpdb->get_var( // phpcs:ignore
					$wpdb->prepare(
						"SELECT IFNULL( (CASE WHEN status = 'paid' THEN -1 ELSE affiliate_id END), 0 ) as affiliate_id
							FROM {$wpdb->prefix}afwc_referrals
							WHERE post_id = %d AND reference = ''",
						$order_id
					)
				);

				if ( ! empty( $old_affiliate_id ) ) {
					// Return if the commission status is paid.
					if ( -1 === $old_affiliate_id ) {
						return;
					}

					// Check if old affiliate id and new affiliate is different.
					if ( $old_affiliate_id !== $affiliate_id ) {

						// Unlink the old affiliate and link to the new affiliate on order.
						if ( $this->unlink_affiliate_from_order( $order_id ) ) {
							$this->link_affiliate_on_order( $order_id, $affiliate_id );
						}
					}
				} else {
					// Directly assign affiliate to order if there is no assigned affiliate.
					$this->link_affiliate_on_order( $order_id, $affiliate_id );
				}
			} else {
				// Unlinking and deleting.
				$this->unlink_affiliate_from_order( $order_id );
			}
		}

		/**
		 * Function to unlink the affiliate by order id.
		 *
		 * @param int $order_id The Order ID.
		 * @return bool.
		 */
		public function unlink_affiliate_from_order( $order_id = 0 ) {
			if ( empty( $order_id ) ) {
				return false;
			}

			global $wpdb;

			// Delete referral data of the order.
			$delete_referral = boolval(
				$wpdb->query( // phpcs:ignore
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}afwc_referrals WHERE post_id = %d AND status != %s",
						$order_id,
						esc_sql( 'paid' )
					)
				)
			);

			if ( true === $delete_referral ) {
				$order = wc_get_order( $order_id );
				if ( ! $order instanceof WC_Order ) {
					return false;
				}

				// Delete the affiliate meta data related to order id.
				$order->delete_meta_data( 'is_commission_recorded' );
				$order->delete_meta_data( 'afwc_order_valid_plans' );
				$order->delete_meta_data( 'afwc_set_commission' );
				$order->delete_meta_data( 'afwc_parent_commissions' );
				$updated_order_id = $order->save();

				// Delete the affiliate meta data related to order id in postmeta table.
				// Additionally firing due to delay in deleting via delete_meta_data causing issues of re-meta insertion from orders screen.
				if ( 'yes' === get_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' ) ) {
					$result = boolval(
						$wpdb->query( // phpcs:ignore
							$wpdb->prepare(
								"DELETE FROM {$wpdb->prefix}postmeta
									WHERE post_id = %d
									AND meta_key IN ('is_commission_recorded','afwc_order_valid_plans','afwc_set_commission','afwc_parent_commissions')",
								$order_id
							)
						)
					);
				}

				/**
				 * Here we don't know if meta is actually deleted since WooCommerce does not send any confirmation.
				 * So we are doing additional sanity checks.
				 */
				if ( empty( $updated_order_id ) || $updated_order_id !== $order_id || is_wp_error( $updated_order_id ) ) {
					return false;
				}

				return true;
			}

			return false;
		}

		/**
		 * Function to assign the affiliate to an order.
		 *
		 * @param int $order_id     The Order ID.
		 * @param int $affiliate_id The Affiliate ID.
		 * @return void.
		 */
		public function link_affiliate_on_order( $order_id = 0, $affiliate_id = 0 ) {

			if ( empty( $order_id ) || empty( $affiliate_id ) ) {
				return;
			}

			$affiliate_api = AFWC_API::get_instance();
			$affiliate_api->track_conversion( $order_id, $affiliate_id, '', array( 'is_affiliate_eligible' => true ) );
			$order      = wc_get_order( $order_id );
			$new_status = is_object( $order ) && is_callable( array( $order, 'get_status' ) ) ? $order->get_status() : '';
			$affiliate_api->update_referral_status( $order_id, '', $new_status );
		}
	}

}

AFWC_Admin_Link_Unlink_In_Order::get_instance();
