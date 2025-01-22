<?php
/**
 * Class for subscriptions renewal commissions rule
 *
 * @package   affiliate-for-woocommerce/includes/integration/woocommerce-subscriptions/commission-rules/
 * @since     7.0.0
 * @version   2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Number_Rule;

if ( ! class_exists( 'AFWC_Subscription_Renewal_Commission' ) && class_exists( Number_Rule::class ) ) {

	/**
	 * Class for renewal subscriptions commission rules
	 */
	class AFWC_Subscription_Renewal_Commission extends Number_Rule {

		/**
		 * Constructor
		 *
		 * @param array $args props.
		 */
		public function __construct( $args = array() ) {
			$this->set_context_key( 'subscription_renewal' );
			$this->set_category( 'subscription' );
			$this->set_title(
				_x( 'Renewal', 'Title for renewal subscription commission rule', 'affiliate-for-woocommerce' )
			);
			$this->set_placeholder(
				_x( 'Set number of renewals', 'Placeholder for renewal subscription commission rule', 'affiliate-for-woocommerce' )
			);
			parent::__construct( $args );
		}

		/**
		 * Method to return possible operators.
		 *
		 * @return array Return the possible operators.
		 */
		public function get_possible_operators() {
			// Exclude the operators for this rule.
			$this->exclude_operators( array( 'in', 'nin', 'eq', 'neq' ) );

			// Re-merge the eq and neq operator to change the operator label for this rule.
			return array_merge(
				$this->possible_operators,
				array(
					array(
						'op'    => 'eq',
						'label' => _x( '=', 'Label for equal to operator of renewal subscription rule', 'affiliate-for-woocommerce' ),
						'type'  => 'single',
					),
					array(
						'op'    => 'neq',
						'label' => _x( '!=', 'Label for not equal to operator of renewal subscription rule', 'affiliate-for-woocommerce' ),
						'type'  => 'single',
					),
				)
			);
		}

		/**
		 * Method to get context value for this rule.
		 *
		 * @param array $args The arguments for context.
		 *
		 * @return int|null Return the count of renewal subscription or null if order does not contains subscription.
		 */
		public function get_context_value( $args = array() ) {

			$subscription_count = null;

			if ( empty( $args['order'] ) || ! $args['order'] instanceof WC_Order ) {
				return $subscription_count;
			}

			$order_id = is_callable( array( $args['order'], 'get_id' ) ) ? $args['order']->get_id() : 0;

			if ( empty( $order_id ) || ! function_exists( 'wcs_order_contains_renewal' ) || ! wcs_order_contains_renewal( $order_id ) ) {
				return $subscription_count;
			}

			$subscriptions = function_exists( 'wcs_get_subscriptions_for_order' )
				? wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => array( 'renewal' ) ) )
				: array();

			if ( empty( $subscriptions ) ) {
				return $subscription_count;
			}

			// Get the last subscription form the order.
			$subscription      = is_array( $subscriptions ) ? end( $subscriptions ) : $subscriptions;
			$renewal_order_ids = $subscription instanceof WC_Subscription && is_callable( array( $subscription, 'get_related_orders' ) ) ? $subscription->get_related_orders( 'ids', array( 'renewal' ) ) : 0;

			return ! empty( $renewal_order_ids ) && is_array( $renewal_order_ids ) ? count( $renewal_order_ids ) : $subscription_count;
		}

		/**
		 * Method to set validated values to this rule.
		 *
		 * @param array  $values The values.
		 * @param object $context_obj context object.
		 *
		 * @return void
		 */
		public function set_validated_values( $values = array(), $context_obj = null ) {

			parent::set_validated_values( $values );

			$context_args = is_callable( array( $context_obj, 'get_args' ) ) ? $context_obj->get_args() : array();

			if ( empty( $context_args ) || empty( $context_args['ordered_product_ids'] ) || ! is_array( $context_args['ordered_product_ids'] ) ) {
				return;
			}

			// Add the validated products to additional rules key.
			parent::set_validated_values(
				array( 'additional_rules' => array( 'product_id' => $context_args['ordered_product_ids'] ) )
			);
		}
	}

}
