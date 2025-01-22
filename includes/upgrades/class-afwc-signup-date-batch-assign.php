<?php
/**
 * Background processing class to assign the signup date for all active affiliates.
 *
 * @package     affiliate-for-woocommerce/includes/upgrades/
 * @since       8.1.0
 * @version     1.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Background_Process', false ) ) {
	include_once AFWC_PLUGIN_DIRPATH . '/includes/abstracts/class-afwc-background-process.php';
}

if ( ! class_exists( 'AFWC_Signup_Date_Batch_Assign' ) && class_exists( 'AFWC_Background_Process' ) ) {

	/**
	 * Class to handle affiliate batch signup date assignment.
	 */
	class AFWC_Signup_Date_Batch_Assign extends AFWC_Background_Process {

		/**
		 * Variable to hold instance of this class.
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get the single instance of this class
		 *
		 * @return AFWC_Signup_Date_Batch_Assign
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
			$this->batch_limit = 20;

			// Set the action name.
			$this->action = 'afwc_signup_date_assign';

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
						_x( 'User ID is not provided to run the task: %s', 'Error message for signup date', 'affiliate-for-woocommerce' ),
						__CLASS__
					)
				);
			}

			foreach ( $user_ids as $user_id ) {
				if ( ! $this->set_signup_date( $user_id ) ) {
					throw new Exception(
						sprintf(
							/* translators: 1: The user ID */
							_x( 'Signup date could not assigned to user ID: %d', 'Error message for issue in adding signup date', 'affiliate-for-woocommerce' ),
							$user_id
						)
					);
				}
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

			$wpdb1 = $wpdb;

			if ( $limit > 0 ) {
				$user_ids = $wpdb1->get_col(
					$wpdb1->prepare(
						"SELECT user_id
							FROM {$wpdb1->usermeta}
						WHERE meta_key = 'afwc_is_affiliate'
							AND meta_value = 'yes'
							AND user_id NOT IN (
								SELECT user_id FROM {$wpdb1->usermeta} WHERE meta_key = 'afwc_signup_date'
							)
						LIMIT %d",
						$limit
					)
				);
			} else {
				$user_ids = $wpdb1->get_col(
					"SELECT user_id
						FROM {$wpdb1->usermeta}
					WHERE meta_key = 'afwc_is_affiliate'
						AND meta_value = 'yes'
						AND user_id NOT IN (
							SELECT user_id FROM {$wpdb1->usermeta} WHERE meta_key = 'afwc_signup_date'
						)"
				);
			}

			$user_count = is_array( $user_ids ) ? count( $user_ids ) : 0;

			if ( ( $limit < 0 ) || ( $user_count < $limit ) ) {
				$affiliate_roles = get_option( 'affiliate_users_roles', array() );

				if ( empty( $affiliate_roles ) || ! is_array( $affiliate_roles ) ) {
					return $user_ids;
				}

				$sql_role_conditions = array();
				$user_roles          = array();

				foreach ( $affiliate_roles as $role ) {
					$sql_role_conditions[] = 'meta_value LIKE %s';
					$user_roles[]          = '%' . $wpdb1->esc_like( $role ) . '%';
				}

				if ( empty( $sql_role_conditions ) ) {
					return $user_ids;
				}

				$sql_role_condition_str = implode( ' OR ', $sql_role_conditions );

				if ( $limit > 0 ) {
					$additional_affiliates = $wpdb1->get_col(
						$wpdb1->prepare(
							"SELECT user_id
								FROM {$wpdb1->usermeta}
							WHERE meta_key LIKE '{$wpdb1->prefix}capabilities'
								AND ( " . $sql_role_condition_str . ")
								AND user_id NOT IN (
									SELECT user_id FROM {$wpdb1->usermeta} WHERE meta_key = 'afwc_signup_date' AND (meta_value IS NOT NULL OR meta_value != '')
								)
								AND user_id NOT IN (
									SELECT user_id FROM {$wpdb1->usermeta} WHERE meta_key = 'afwc_is_affiliate'
								)
							LIMIT %d",
							array_merge(
								$user_roles,
								array( $limit )
							)
						)
					);
				} else {
					$additional_affiliates = $wpdb1->get_col(
						$wpdb1->prepare(
							"SELECT user_id
								FROM {$wpdb1->usermeta}
							WHERE meta_key LIKE '{$wpdb1->prefix}capabilities'
								AND ( " . $sql_role_condition_str . ")
								AND user_id NOT IN (
									SELECT user_id FROM {$wpdb1->usermeta} WHERE meta_key = 'afwc_signup_date' AND (meta_value IS NOT NULL OR meta_value != '')
								)
								AND user_id NOT IN (
									SELECT user_id FROM {$wpdb1->usermeta} WHERE meta_key = 'afwc_is_affiliate'
								)",
							$user_roles
						)
					);
				}

				if ( ! empty( $additional_affiliates ) && is_array( $additional_affiliates ) ) {
					$user_ids = array_merge( $user_ids, $additional_affiliates );
				}
			}

			return ! empty( $user_ids ) && is_array( $user_ids ) ? array_map( 'intval', $user_ids ) : array();
		}

		/**
		 * Method to set signup date in the user meta.
		 * It will forcefully update the signup date without checking correct affiliate due to prevent the retrying this action
		 * when affiliate records are exists in the usermeta, hits, referral table but deleted from `users` table.
		 *
		 * @param int $user_id The user ID.
		 *
		 * @return bool Return true otherwise false false if user ID is not provided.
		 */
		public function set_signup_date( $user_id = 0 ) {

			if ( empty( $user_id ) ) {
				return false;
			}

			$affiliate_obj = new AFWC_Affiliate( $user_id );

			// Get the minimum tracking date time based on hit and referrals.
			$afwc         = is_callable( array( 'Affiliate_For_WooCommerce', 'get_instance' ) ) ? Affiliate_For_WooCommerce::get_instance() : null;
			$min_datetime = is_callable( array( $afwc, 'get_minimum_tracking_datetime' ) ) ? $afwc->get_minimum_tracking_datetime( 'Y-m-d H:i:s', true, $user_id ) : '';

			if ( ! empty( $min_datetime ) ) {
				return (bool) update_user_meta( $user_id, 'afwc_signup_date', $min_datetime );
			}

			// Find and set the signup date by user registered.
			if ( ! empty( $affiliate_obj->user_registered ) ) {
				return (bool) update_user_meta( $user_id, 'afwc_signup_date', $affiliate_obj->user_registered );
			}

			/**
			 * If above checks could not set the signup date, forcefully update the signup date as current GMT date.
			 * It will prevent to re-try the operation of `user_registered` date is not present for any affiliate/user.
			*/
			return (bool) update_user_meta( $user_id, 'afwc_signup_date', current_time( 'Y-m-d H:i:s', true ) );
		}

		/**
		 * Trigger when completed the process.
		 */
		public function completed() {
			parent::completed();
			update_option( '_afwc_current_db_version', '1.3.7', 'no' );
		}
	}
}
