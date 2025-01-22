<?php
/**
 * Main class for User role handler
 *
 * @package     affiliate-for-woocommerce/includes/common/
 * @since       8.1.0
 * @version     1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_User_Roles_Handler' ) ) {

	/**
	 * Class to handle affiliate user roles.
	 */
	class AFWC_User_Roles_Handler {

		/**
		 * Instance of AFWC_User_Roles_Handler
		 *
		 * @var self $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of AFWC_User_Roles_Handler
		 *
		 * @return AFWC_User_Roles_Handler Singleton object of AFWC_User_Roles_Handler
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private constructor to prevent direct instantiation.
		 */
		private function __construct() {
			// Triggers when user role is changed of a user.
			add_action( 'set_user_role', array( $this, 'maybe_set_affiliate_signup_date' ), 10, 2 );
			// Triggers when a new user role is added to a user.
			add_action( 'add_user_role', array( $this, 'maybe_set_affiliate_signup_date' ), 10, 2 );

			if ( is_admin() ) {
				// Triggers when affiliate user role setting is going to update in the admin side.
				add_filter( 'woocommerce_admin_settings_sanitize_option_affiliate_users_roles', array( $this, 'maybe_insert_affiliate_signup_date_on_affiliate_role_update' ), 999 );
			}
		}

		/**
		 * Callback set signup date when user role is updated to an affiliate.
		 * It will not assign if user just signup by affiliate registration form.
		 *
		 * @param int    $user_id The user ID.
		 * @param string $user_role The user role.
		 *
		 * @return void
		 */
		public function maybe_set_affiliate_signup_date( $user_id = 0, $user_role = '' ) {

			if ( empty( $user_id ) || ! afwc_is_affiliate_user_role( $user_role ) ) {
				return;
			}

			if ( 'affiliate-for-woocommerce' === get_user_meta( $user_id, 'register_by', true ) ) {
				// Do not assign if the user is just signup by affiliate registration form and role is assigned.
				return;
			}

			$affiliate_obj = new AFWC_Affiliate( $user_id );

			if ( is_callable( array( $affiliate_obj, 'set_signup_date' ) ) ) {
				// It will check if affiliate is an valid affiliate, If yes, set the signup date as current date.
				$affiliate_obj->set_signup_date();
			}
		}

		/**
		 * Callback method to insert the affiliate signup date when a new user role is added into affiliate role setting.
		 *
		 * @param array $updated_roles The updated user roles.
		 *
		 * @throws Exception Error when unable to update the sign update.
		 * @return array The updated user roles.
		 */
		public function maybe_insert_affiliate_signup_date_on_affiliate_role_update( $updated_roles = array() ) {
			if ( empty( $updated_roles ) || ! is_array( $updated_roles ) ) {
				return $updated_roles;
			}

			global $wpdb;
			$wpdb1 = $wpdb;

			// Get existing roles.
			$existing_user_roles = get_option( 'affiliate_users_roles', array() );
			$sql_role_conditions = array();
			$new_roles           = array();

			foreach ( $updated_roles as $role ) {

				if ( is_array( $existing_user_roles ) && ! empty( $existing_user_roles ) && in_array( $role, $existing_user_roles, true ) ) {
					// Do not assign signup date for the existing user roles.
					continue;
				}

				$sql_role_conditions[] = 'meta_value LIKE %s';
				$user_roles[]          = '%' . $wpdb1->esc_like( $role ) . '%';
			}

			if ( empty( $sql_role_conditions ) ) {
				return $updated_roles;
			}

			$sql_role_condition_str = implode( ' OR ', $sql_role_conditions );

			global $wpdb;

			$wpdb1 = $wpdb;

			$current_time = current_time( 'Y-m-d H:i:s', true );

			try {
				$results = $wpdb1->query(
					$wpdb1->prepare(
						"INSERT INTO {$wpdb1->usermeta} (user_id, meta_key, meta_value)
							SELECT user_id, 'afwc_signup_date', %s
								FROM {$wpdb1->usermeta}
							WHERE meta_key LIKE '{$wpdb1->prefix}capabilities'
								AND ( " . $sql_role_condition_str . ")
								AND user_id NOT IN (
									SELECT user_id FROM {$wpdb1->usermeta} WHERE meta_key = 'afwc_signup_date' AND (meta_value IS NOT NULL OR meta_value != '')
								)
								AND user_id NOT IN (
									SELECT user_id FROM {$wpdb1->usermeta} WHERE meta_key = 'afwc_is_affiliate'
								)",
						array_merge(
							array( $current_time ),
							$user_roles
						)
					)
				);

				if ( false === $results ) {
					// Throw if any error.
					throw new Exception( ! empty( $wpdb1->last_error ) ? $wpdb1->last_error : '' );
				}
			} catch ( Exception $e ) {
				Affiliate_For_WooCommerce::get_instance()->log_error( __METHOD__, ( is_callable( array( $e, 'getMessage' ) ) ) ? $e->getMessage() : '' );
			}

			return $updated_roles;
		}
	}
}

AFWC_User_Roles_Handler::get_instance();
