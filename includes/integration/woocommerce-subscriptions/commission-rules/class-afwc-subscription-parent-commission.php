<?php
/**
 * Class for subscriptions parent commissions rule
 *
 * @package   affiliate-for-woocommerce/includes/integration/woocommerce-subscriptions/commission-rules/
 * @since     7.0.0
 * @version   2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Boolean_Rule;

if ( ! class_exists( 'AFWC_Subscription_Parent_Commission' ) && class_exists( Boolean_Rule::class ) ) {

	/**
	 * Class for subscriptions parent commission rule
	 */
	class AFWC_Subscription_Parent_Commission extends Boolean_Rule {

		/**
		 * Constructor
		 *
		 * @param array $args props.
		 */
		public function __construct( $args = array() ) {
			$this->set_context_key( 'subscription_parent' );
			$this->set_category( 'subscription' );
			$this->set_title(
				_x( 'Parent', 'Title for parent subscription commission rule', 'affiliate-for-woocommerce' )
			);
			$this->set_placeholder(
				_x( 'Select yes/no', 'Placeholder for parent subscription commission rule', 'affiliate-for-woocommerce' )
			);
			parent::__construct( $args );
		}

		/**
		 * Method to return possible operators.
		 *
		 * @return array Return the possible operators.
		 */
		public function get_possible_operators() {
			return $this->possible_operators;
		}

		/**
		 * Method to return possible options.
		 *
		 * @return array Return the pre-defined options for the rule.
		 */
		public function get_options() {
			return array(
				'yes' => _x( 'Yes', 'Positive value title for parent subscription commission rule', 'affiliate-for-woocommerce' ),
				'no'  => _x( 'No', 'Negative value title for parent subscription commission rule', 'affiliate-for-woocommerce' ),
			);
		}

		/**
		 * Method to get context value for this rule.
		 *
		 * @param array $args The arguments for context.
		 *
		 * @return string|null Return yes if the order contains parent subscription otherwise no. Return null if order does not contains subscription.
		 */
		public function get_context_value( $args = array() ) {

			// Return an empty array if order object is not provided.
			if ( empty( $args['order'] ) || ! $args['order'] instanceof WC_Order ) {
				return null;
			}

			$order_id = is_callable( array( $args['order'], 'get_id' ) ) ? $args['order']->get_id() : 0;

			if ( empty( $order_id ) ) {
				return null;
			}

			$subscriptions = function_exists( 'wcs_get_subscriptions_for_order' )
				? wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => array( 'any' ) ) )
				: null;

			if ( empty( $subscriptions ) ) {
				return null;
			}

			return function_exists( 'wcs_order_contains_subscription' )
				? wcs_order_contains_subscription( $order_id, array( 'parent' ) ) ? 'yes' : 'no'
				: null;
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
