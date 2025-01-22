<?php
/**
 * Background processing class to assign the payout method to all active affiliates where PayPal email is available.
 *
 * @package     affiliate-for-woocommerce/includes/upgrades/
 * @since       8.9.0
 * @version     1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Background_Process', false ) ) {
	include_once AFWC_PLUGIN_DIRPATH . '/includes/abstracts/class-afwc-background-process.php';
}

if ( ! class_exists( 'AFWC_PayPal_Payout_Method_Assign' ) && class_exists( 'AFWC_Background_Process' ) ) {

	/**
	 * Class to handle affiliate batch signup date assignment.
	 */
	class AFWC_PayPal_Payout_Method_Assign extends AFWC_Background_Process {

		/**
		 * Variable to hold instance of this class.
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get the single instance of this class
		 *
		 * @return AFWC_PayPal_Payout_Method_Assign
		 */
		public static function get_instance() {
			// Check if the instance already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			// Set the batch limit.
			$this->batch_limit = 50;

			// Set the action name.
			$this->action = 'afwc_paypal_payout_method_assign';

			// Initialize the parent class to execute background process.
			parent::__construct();
		}

		/**
		 * Execute the task for each batch.
		 *
		 * @param array $user_ids The affiliate IDs to run the batch.
		 *
		 * @throws Exception If any problem during the process.
		 */
		public function task( $user_ids = array() ) {
			if ( empty( $user_ids ) && ! is_array( $user_ids ) ) {
				throw new Exception(
					sprintf(
						/* translators: 1: Current task name */
						_x( 'User ID is not provided to run the task: %s', 'Error message for assigning payout method', 'affiliate-for-woocommerce' ),
						__CLASS__
					)
				);
			}

			foreach ( $user_ids as $user_id ) {
				update_user_meta( $user_id, 'afwc_payout_method', 'paypal' );
			}

			// Check process health before continuing.
			if ( ! $this->health_status() ) {
				throw new Exception(
					sprintf(
						/* translators: 1: The task class name */
						_x( 'Batch stopped due to health status in task: %s', 'Logger for batch stopped due to health status', 'affiliate-for-woocommerce' ),
						__CLASS__
					)
				);
			}
		}

		/**
		 * Get the remaining items for doing the action.
		 *
		 * @return array The array of user IDs.
		 */
		public function get_remaining_items() {
			global $wpdb;

			$limit = $this->get_batch_limit();
			$limit = ! empty( $limit ) ? intval( $limit ) : -1;

			try {
				if ( $limit > 0 ) {
					$user_ids = $wpdb->get_col( // phpcs:ignore
						$wpdb->prepare(
							"SELECT user_id
								FROM {$wpdb->usermeta}
							WHERE meta_key = 'afwc_paypal_email'
								AND ( meta_value IS NOT NULL OR meta_value != '' )
								AND user_id NOT IN (
									SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'afwc_payout_method'
								)
							LIMIT %d",
							$limit
						)
					);
				} else {
					$user_ids = $wpdb->get_col( // phpcs:ignore
						"SELECT user_id
							FROM {$wpdb->usermeta}
						WHERE meta_key = 'afwc_paypal_email'
							AND ( meta_value IS NOT NULL OR meta_value != '' )
							AND user_id NOT IN (
								SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'afwc_payout_method'
							)"
					);
				}
			} catch ( Exception $e ) {
				$user_ids = array();
				Affiliate_For_WooCommerce::get_instance()->log_error( __METHOD__, ( is_callable( array( $e, 'getMessage' ) ) ) ? $e->getMessage() : '' );
			}

			return ! empty( $user_ids ) && is_array( $user_ids ) ? array_map( 'intval', $user_ids ) : array();
		}

		/**
		 * Trigger when completed the process.
		 */
		public function completed() {
			parent::completed();
			update_option( '_afwc_current_db_version', '1.3.8', 'no' );
		}
	}
}
