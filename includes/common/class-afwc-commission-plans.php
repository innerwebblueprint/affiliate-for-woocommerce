<?php
/**
 * Main class for Commission plans.
 *
 * @package     affiliate-for-woocommerce/includes/common/
 * @since       7.11.0
 * @version     1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Commission_Plans' ) ) {

	/**
	 * Affiliate Commission plan.
	 */
	class AFWC_Commission_Plans {

		/**
		 * Variable to hold instance of this class
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Commission_Plans Singleton object of this class
		 */
		public static function get_instance() {
			// Check if instance is already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Get available commission plans.
		 *
		 * @param array $args The arguments to filter plans.
		 *
		 * @throws Exception If any error during the process.
		 * @return array Return the array of plans.
		 */
		public function get_plans( $args = array() ) {
			global $wpdb;

			$plan_status   = ucfirst( ! empty( $args['status'] ) ? $args['status'] : '' );
			$commission_id = ! empty( $args['id'] ) ? intval( $args['id'] ) : 0;
			$plans         = array();

			try {
				if ( ! empty( $plan_status ) ) {
					$plans = $wpdb->get_results( // phpcs:ignore
						$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}afwc_commission_plans WHERE status = %s", $plan_status ),
						'ARRAY_A'
					);
				} elseif ( ! empty( $commission_id ) ) {
					$plans = $wpdb->get_results( // phpcs:ignore
						$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}afwc_commission_plans WHERE id = %d", intval( $commission_id ) ),
						'ARRAY_A'
					);
				} else {
					$plans = $wpdb->get_results( // phpcs:ignore
						"SELECT * FROM {$wpdb->prefix}afwc_commission_plans",
						'ARRAY_A'
					);
				}

				// Throw if any error.
				if ( ! empty( $wpdb->last_error ) ) {
					throw new Exception( $wpdb->last_error );
				}
			} catch ( Exception $e ) {
				Affiliate_For_WooCommerce::get_instance()->log_error( __METHOD__, ( is_callable( array( $e, 'getMessage' ) ) ) ? $e->getMessage() : '' );
				return array();
			}

			if ( empty( $plans ) || ! is_array( $plans ) ) {
				return array();
			}

			$commissions = array();

			foreach ( $plans as $plan ) {
				$commissions[] = array(
					'id'                   => ! empty( $plan['id'] ) ? intval( $plan['id'] ) : '',
					'name'                 => ! empty( $plan['name'] ) ? sanitize_text_field( $plan['name'] ) : '',
					'rules'                => ! empty( $plan['rules'] ) ? json_decode( $plan['rules'], true ) : '',
					'amount'               => ! empty( $plan['amount'] ) ? floatval( $plan['amount'] ) : '',
					'type'                 => ! empty( $plan['type'] ) ? sanitize_text_field( $plan['type'] ) : '',
					'status'               => ! empty( $plan['status'] ) ? sanitize_text_field( $plan['status'] ) : '',
					'apply_to'             => ! empty( $plan['apply_to'] ) ? sanitize_text_field( $plan['apply_to'] ) : 'all',
					'action_for_remaining' => ! empty( $plan['action_for_remaining'] ) ? sanitize_text_field( $plan['action_for_remaining'] ) : 'continue',
					'no_of_tiers'          => ! empty( $plan['no_of_tiers'] ) ? intval( $plan['no_of_tiers'] ) : 1,
					'distribution'         => ! empty( $plan['distribution'] ) ? explode( '|', $plan['distribution'] ) : array(),
				);
			}

			return $this->filter( $commissions, $args );
		}

		/**
		 * Filter the commission plans.
		 *
		 * @param array $plans The array of plans.
		 * @param array $args The arguments for filter.
		 *
		 * @return array Return the filtered array of plans.
		 */
		public function filter( $plans = array(), $args = array() ) {

			if ( empty( $plans ) ) {
				return array();
			}

			// Filter for multi-tier handling.
			$multi_tier = is_callable( array( 'AFWC_Multi_Tier', 'get_instance' ) ) ? AFWC_Multi_Tier::get_instance() : null;
			if (
				$multi_tier instanceof AFWC_Multi_Tier &&
				empty( $multi_tier->is_enabled ) &&
				is_callable( array( $multi_tier, 'remove_multi_tier_data_from_plans' ) )
			) {
				// Remove multi-tier data from the plan if the multi-tier setting is disabled for the store.
				$plans = $multi_tier->remove_multi_tier_data_from_plans( $plans );
			}

			// Sorting the plans if requested.
			if ( ! empty( $args['sorting'] ) ) {
				$plans = $this->apply_sorting( $plans );
			}

			// Remove the storewide plans if requested.
			if ( ! empty( $args['exclude_storewide'] ) ) {
				$default_plan_id = afwc_get_default_commission_plan_id();
				$plans           = array_filter(
					$plans,
					function ( $p ) use ( $default_plan_id ) {
						if ( intval( $p['id'] ) !== intval( $default_plan_id ) ) {
							return $p;
						}
					}
				);
			}

			// Return plan data without allowing third party developer to modify if the request comes from backend.
			return afwc_current_user_can_manage_affiliate() && doing_action( 'wp_ajax_afwc_commission_controller' )
				? $plans
				: apply_filters( 'afwc_commission_plans_details', $plans, array( 'source' => $this ) );
		}

		/**
		 * Get the commission plans order.
		 *
		 * @return array The array of commission IDs based on plan sorting.
		 */
		public function get_plans_order() {

			$plan_order     = get_option( 'afwc_plan_order', array() );
			$old_plan_order = $plan_order;

			// Set plan order if plan order is empty.
			if ( empty( $plan_order ) || ! is_array( $plan_order ) ) {
				$commission_plans = $this->get_plans();
				$plan_order       = ( is_array( $commission_plans ) && ! empty( $commission_plans ) ) ? array_filter(
					array_map(
						function ( $x ) {
								return ! empty( $x['id'] ) ? absint( $x['id'] ) : 0;
						},
						$commission_plans
					)
				) : array();
			}

			$default_plan_id = afwc_get_default_commission_plan_id();
			if ( ! empty( $default_plan_id ) && is_array( $plan_order ) ) {
				$key = is_array( $plan_order ) ? array_search( intval( $default_plan_id ), $plan_order, true ) : false;
				// Unset the default plan id if exists in $plan_order.
				if ( false !== $key ) {
					unset( $plan_order[ $key ] );
				}

				// Assign default plan at last position of the array.
				$plan_order[] = $default_plan_id;
			}

			$plan_order = is_array( $plan_order ) ? array_values( $plan_order ) : array();

			if ( $old_plan_order !== $plan_order ) {
				update_option( 'afwc_plan_order', $plan_order, 'no' );
			}

			return $plan_order;
		}

		/**
		 * Apply sorting the commission plans based on order.
		 *
		 * @param array $plans The array of plans.
		 *
		 * @return array Return the sorted array.
		 */
		public function apply_sorting( $plans = array() ) {

			if ( empty( $plans ) ) {
				return array();
			}

			$plan_order = $this->get_plans_order();

			if ( empty( $plan_order ) || ! is_array( $plan_order ) ) {
				return $plans;
			}

			$ordered_plans = array();

			foreach ( $plan_order as $plan_id ) {
				$current_plan = array_filter(
					$plans,
					function ( $plan ) use ( $plan_id ) {
						return ! empty( $plan['id'] ) && ( intval( $plan['id'] ) === intval( $plan_id ) );
					}
				);
				if ( count( $current_plan ) > 0 ) {
					$current_plan = reset( $current_plan );
				}
				if ( ! empty( $current_plan ) ) {
					$ordered_plans[] = $current_plan;
				}
			}

			return $ordered_plans;
		}
	}
}
