<?php
/**
 * Main class for Commission Dashboard
 *
 * @package     affiliate-for-woocommerce/includes/admin/
 * @since       2.5.0
 * @version     1.6.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Rule_Registry;

if ( ! class_exists( 'AFWC_Commission_Dashboard' ) ) {

	/**
	 * Main class for Commission Dashboard
	 */
	class AFWC_Commission_Dashboard extends AFWC_Commission_Plans {

		/**
		 * The Ajax events.
		 *
		 * @var array $ajax_events
		 */
		private $ajax_events = array(
			'save_commission',
			'delete_commission',
			'fetch_dashboard_data',
			'save_plan_order',
			'fetch_extra_data',
		);

		/**
		 * Variable to hold instance of AFWC_Commission_Dashboard
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Commission_Dashboard Singleton object of this class
		 */
		public static function get_instance() {
			// Check if instance is already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'wp_ajax_afwc_commission_controller', array( $this, 'request_handler' ) );
			add_action( 'wp_ajax_afwc_json_search_rule_values', array( $this, 'afwc_json_search_rule_values' ), 1, 2 );
			add_action( 'wp_ajax_afwc_dismiss_recurring_setting_deprecated_notice', array( $this, 'dismiss_recurring_setting_deprecated_notice' ) );
		}

		/**
		 * Function to handle all ajax request
		 */
		public function request_handler() {
			if ( ! afwc_current_user_can_manage_affiliate() || empty( $_REQUEST ) || empty( wc_clean( wp_unslash( $_REQUEST['cmd'] ) ) ) ) { // phpcs:ignore
				return;
			}

			foreach ( $_REQUEST as $key => $value ) { // phpcs:ignore
				if ( 'commission' === $key ) {
					$params[ $key ] = wp_unslash( $value );
				} else {
					$params[ $key ] = wc_clean( wp_unslash( $value ) );
				}
			}

			$func_nm = ! empty( $params['cmd'] ) ? $params['cmd'] : '';

			if ( empty( $func_nm ) || ! in_array( $func_nm, $this->ajax_events, true ) ) {
				wp_die( esc_html_x( 'You are not allowed to use this action', 'authorization failure message', 'affiliate-for-woocommerce' ) );
			}

			if ( is_callable( array( $this, $func_nm ) ) ) {
				$this->$func_nm( $params );
			}
		}

		/**
		 * Function to handle save commission
		 *
		 * @throws RuntimeException Data Exception.
		 * @param array $params save commission params.
		 */
		public function save_commission( $params = array() ) {

			check_admin_referer( 'afwc-admin-save-commissions', 'security' );

			global $wpdb;

			$response                  = array( 'ACK' => 'Failed' );
			$afwc_storewide_commission = get_option( 'afwc_storewide_commission', true );
			if ( ! empty( $params['commission'] ) ) {
				$commission = json_decode( $params['commission'], true );
				$values     = array();

				$commission_id                  = ! empty( $commission['id'] ) ? intval( $commission['id'] ) : 0;
				$values['name']                 = ! empty( $commission['name'] ) ? sanitize_text_field( $commission['name'] ) : '';
				$values['rules']                = ! empty( $commission['rules'] ) ? wp_json_encode( $commission['rules'] ) : '';
				$values['amount']               = ! empty( $commission['amount'] ) ? floatval( $commission['amount'] ) : 0;
				$values['type']                 = ! empty( $commission['type'] ) ? sanitize_text_field( $commission['type'] ) : 'Percentage';
				$values['status']               = ! empty( $commission['status'] ) ? sanitize_text_field( $commission['status'] ) : 'Active';
				$values['apply_to']             = ! empty( $commission['apply_to'] ) ? sanitize_text_field( $commission['apply_to'] ) : 'all';
				$values['action_for_remaining'] = ! empty( $commission['action_for_remaining'] ) ? sanitize_text_field( $commission['action_for_remaining'] ) : 'continue';
				$values['no_of_tiers']          = ! empty( $commission['no_of_tiers'] ) ? intval( $commission['no_of_tiers'] ) : 1;
				$values['distribution']         = ! empty( $commission['distribution'] ) ? implode( '|', (array) $commission['distribution'] ) : '';

				$result = false;

				// Setup multi-tier values if the feature is enabled.
				$multi_tier            = is_callable( array( 'AFWC_Multi_Tier', 'get_instance' ) ) ? AFWC_Multi_Tier::get_instance() : null;
				$is_multi_tier_enabled = $multi_tier instanceof AFWC_Multi_Tier && ! empty( $multi_tier->is_enabled );

				if ( false === $is_multi_tier_enabled ) {
					if ( isset( $values['no_of_tiers'] ) ) {
						unset( $values['no_of_tiers'] );
					}
					if ( isset( $values['distribution'] ) ) {
						unset( $values['distribution'] );
					}
				}

				if ( $commission_id > 0 ) {
					$values['commission_id'] = $commission_id;
					if ( true === $is_multi_tier_enabled ) {
						// Allow multi-tier fields to be updated.
						$result                = $wpdb->query( // phpcs:ignore
							$wpdb->prepare( // phpcs:ignore
								"UPDATE {$wpdb->prefix}afwc_commission_plans SET name = %s, rules = %s, amount = %f, type = %s, status = %s, apply_to = %s, action_for_remaining = %s, no_of_tiers = %d, distribution = %s WHERE id = %s",
								$values
							)
						);
					} else {
						// Does not allow multi-tier fields to be updated.
						$result                = $wpdb->query( // phpcs:ignore
							$wpdb->prepare( // phpcs:ignore
								"UPDATE {$wpdb->prefix}afwc_commission_plans SET name = %s, rules = %s, amount = %f, type = %s, status = %s, apply_to = %s, action_for_remaining = %s WHERE id = %s",
								$values
							)
						);
					}
				} else {
					if ( true === $is_multi_tier_enabled ) {
						// Allow multi-tier fields to be inserted.
						$result       = $wpdb->query( // phpcs:ignore
							$wpdb->prepare( // phpcs:ignore
								"INSERT INTO {$wpdb->prefix}afwc_commission_plans ( name, rules, amount, type, status, apply_to, action_for_remaining, no_of_tiers, distribution ) VALUES ( %s, %s, %f, %s, %s, %s, %s, %d, %s )",
								$values
							)
						);
					} else {
						// Does not allow multi-tier fields to be inserted.
						$result       = $wpdb->query( // phpcs:ignore
							$wpdb->prepare( // phpcs:ignore
								"INSERT INTO {$wpdb->prefix}afwc_commission_plans ( name, rules, amount, type, status, apply_to, action_for_remaining ) VALUES ( %s, %s, %f, %s, %s, %s, %s )",
								$values
							)
						);
					}

					$lastid = ! empty( $wpdb->insert_id ) ? $wpdb->insert_id : 0;

					$plan_order = is_callable( array( $this, 'get_plans_order' ) ) ? $this->get_plans_order() : array();

					$len = ( ! empty( $plan_order ) && is_array( $plan_order ) ) ? count( $plan_order ) : 0;

					// add new plan id to the -2 position.

					array_splice( $plan_order, $len, 0, $lastid );
					update_option( 'afwc_plan_order', $plan_order, 'no' );
				}

				if ( false === $result ) {
					throw new RuntimeException( esc_html_x( 'Unable to save commission plan. Database error.', 'commission plan save error message', 'affiliate-for-woocommerce' ) );
				}

				$response                     = array( 'ACK' => 'Success' );
				$response['last_inserted_id'] = ! empty( $lastid ) ? $lastid : 0;
			}
			wp_send_json( $response );
		}

		/**
		 * Function to handle delete commission
		 *
		 * @param array $params delete commission params.
		 */
		public function delete_commission( $params = array() ) {

			check_admin_referer( 'afwc-admin-delete-commissions', 'security' );

			global $wpdb;

			$response = array( 'ACK' => 'Failed' );
			if ( ! empty( $params['commission_id'] ) ) {

				$default_plan = afwc_get_default_commission_plan_id();

				if ( intval( $params['commission_id'] ) === $default_plan ) {
					return wp_send_json(
						array(
							'ACK' => 'Error',
							'msg' => _x( 'Default plan can not be deleted', 'commission default plan delete error message', 'affiliate-for-woocommerce' ),
						)
					);
				}

				$result = $wpdb->query( // phpcs:ignore
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}afwc_commission_plans WHERE id = %d",
						$params['commission_id']
					)
				);
				if ( false === $result ) {
					wp_send_json(
						array(
							'ACK' => 'Error',
							'msg' => _x( 'Failed to delete commission plan', 'commission plan delete error message', 'affiliate-for-woocommerce' ),
						)
					);
				} else {
					// delete from plan order.
					$plan_order = is_callable( array( $this, 'get_plans_order' ) ) ? $this->get_plans_order() : array();
					if ( ! empty( $plan_order ) && is_array( $plan_order ) ) {
						$c          = $params['commission_id'];
						$plan_order = array_filter(
							$plan_order,
							function ( $e ) use ( $c ) {
								return ( absint( $e ) !== absint( $c ) );
							}
						);
						update_option( 'afwc_plan_order', $plan_order, 'no' );
					}
					wp_send_json(
						array(
							'ACK' => 'Success',
							'msg' => _x( 'Commission plan deleted successfully', 'commission plan delete success message', 'affiliate-for-woocommerce' ),
						)
					);
				}
			}
		}

		/**
		 * Function to handle fetch data
		 *
		 * @param array $params fetch commission dashboard data params.
		 */
		public function fetch_dashboard_data( $params = array() ) {

			check_admin_referer( 'afwc-admin-commissions-dashboard-data', 'security' );

			$commission_plans = is_callable( array( $this, 'get_plans_order' ) )
				? $this->get_plans(
					! empty( $params['commission_status'] ) ? array( 'status' => $params['commission_status'] ) : array()
				) : array();

			if ( ! empty( $commission_plans ) ) {

				wp_send_json(
					array(
						'ACK'    => 'Success',
						'result' => array(
							'commissions' => $commission_plans,
							'plan_order'  => is_callable( array( $this, 'get_plans_order' ) ) ? $this->get_plans_order() : array(),
						),
					)
				);
			}

			wp_send_json(
				array(
					'ACK' => 'Failed',
					'msg' => _x( 'No commission plans found', 'commission plans not found message', 'affiliate-for-woocommerce' ),
				)
			);
		}

		/**
		 * Function to handle save plan order
		 *
		 * @param array $params save plan order params.
		 */
		public static function save_plan_order( $params = array() ) {

			check_admin_referer( 'afwc-admin-save-commission-order', 'security' );

			$default_plan_id = afwc_get_default_commission_plan_id();
			if ( ! empty( $params['plan_order'] ) ) {
				$plan_order = (array) json_decode( $params['plan_order'], true );

				if ( ! empty( $default_plan_id ) ) {
					$key = array_search( $default_plan_id, $plan_order, true );
					if ( false !== $key ) {
						unset( $plan_order[ $key ] );
					}
					$plan_order[] = $default_plan_id;
				}
				$plan_order = array_values( $plan_order );
				update_option( 'afwc_plan_order', $plan_order, 'no' );
				wp_send_json(
					array(
						'ACK'    => 'Success',
						'result' => $plan_order,
					)
				);
			} else {
				wp_send_json(
					array(
						'msg' => _x( 'No commission plan order to save', 'no commission plan order save message', 'affiliate-for-woocommerce' ),
					)
				);
			}
		}

		/**
		 * Search for attribute values and return json
		 *
		 * @return void
		 */
		public function afwc_json_search_rule_values() {

			check_admin_referer( 'afwc-admin-search-commission-plans', 'security' );

			if ( ! afwc_current_user_can_manage_affiliate() ) {
				wp_die( esc_html_x( 'You are not allowed to use this action', 'authorization failure message', 'affiliate-for-woocommerce' ) );
			}

			$term = ( ! empty( $_GET['term'] ) ) ? (string) urldecode( wp_strip_all_tags( wp_unslash( $_GET ['term'] ) ) ) : '';
			$type = ( ! empty( $_GET['type'] ) ) ? (string) urldecode( wp_strip_all_tags( wp_unslash( $_GET ['type'] ) ) ) : 'affiliate';

			if ( empty( $term ) ) {
				wp_die();
			}

			$rule_values = array();

			if ( ! empty( $type ) ) {
				$rule_registry = is_callable( array( Rule_Registry::class, 'get_instance' ) ) ? Rule_Registry::get_instance() : null;
				$rule_obj      = is_callable( array( $rule_registry, 'get_registered' ) ) ? $rule_registry->get_registered( $type ) : null;
				$rule_values   = is_callable( array( $rule_obj, 'search_values' ) ) ? $rule_obj->search_values( $term ) : array();
			}

			echo wp_json_encode( $rule_values );
			wp_die();
		}

		/**
		 * Fetch data call
		 *
		 * @param string $params mixed.
		 */
		public function fetch_extra_data( $params ) {
			check_admin_referer( 'afwc-admin-extra-data', 'security' );
			$data = json_decode( $params['data'], true );

			if ( empty( $data ) || ! is_array( $data ) ) {
				wp_send_json(
					array(
						'ACK' => 'Failed',
						'msg' => _x( 'Data not available', 'Requirement missing message to fetch extra data for commission plans', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$rule_registry = is_callable( array( Rule_Registry::class, 'get_instance' ) ) ? Rule_Registry::get_instance() : null;
			$rule_values   = array();
			foreach ( $data as $type => $ids ) {
				$rule_obj             = is_callable( array( $rule_registry, 'get_registered' ) ) ? $rule_registry->get_registered( $type ) : null;
				$rule_values[ $type ] = is_callable( array( $rule_obj, 'search_values' ) ) ? $rule_obj->search_values( $ids, false ) : array();
			}

			if ( ! empty( $rule_values ) ) {
				wp_send_json(
					array(
						'ACK'    => 'Success',
						'result' => $rule_values,
					)
				);
			} else {
				wp_send_json(
					array(
						'ACK' => 'Success',
						'msg' => _x( 'No commission plans found', 'commission plans not found message', 'affiliate-for-woocommerce' ),
					)
				);
			}
		}

		/**
		 * Get commission plan statuses.
		 *
		 * @param string $status Plan Status.
		 *
		 * @return array|string Return the status title if the status is provided otherwise return array of all statuses.
		 */
		public static function get_statuses( $status = '' ) {
			$statuses = array(
				'Active' => _x( 'Active', 'active commission plan status', 'affiliate-for-woocommerce' ),
				'Draft'  => _x( 'Draft', 'draft commission plan status', 'affiliate-for-woocommerce' ),
			);

			return empty( $status ) ? $statuses : ( ! empty( $statuses[ $status ] ) ? $statuses[ $status ] : '' );
		}

		/**
		 * Method to dismiss the recurring setting deprecated notice.
		 *
		 * @return void
		 */
		public function dismiss_recurring_setting_deprecated_notice() {

			check_admin_referer( 'afwc-admin-dismiss-recurring-setting-deprecated-notice', 'security' );

			delete_option( 'afwc_show_subscription_admin_dashboard_notice' );

			wp_send_json(
				array(
					'ACK' => true,
				)
			);
		}
	}

}

return AFWC_Commission_Dashboard::get_instance();
