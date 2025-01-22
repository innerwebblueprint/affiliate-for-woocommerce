<?php
/**
 * Class for First order by the customer commissions rule
 *
 * @package   affiliate-for-woocommerce/includes/commission-rules/simple/
 * @since     7.0.0
 * @version   2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Boolean_Rule;

if ( ! class_exists( 'AFWC_User_First_Order_Commission' ) && class_exists( Boolean_Rule::class ) ) {

	/**
	 * Class for user first order commission rule
	 */
	class AFWC_User_First_Order_Commission extends Boolean_Rule {

		/**
		 * Constructor
		 *
		 * @param array $args props.
		 */
		public function __construct( $args = array() ) {
			$this->set_context_key( 'user_first_order' );
			$this->set_category( 'user' );
			$this->set_title(
				_x( 'First Order', 'Title for user first order commission rule', 'affiliate-for-woocommerce' )
			);
			$this->set_placeholder(
				_x( 'Select yes/no', 'Placeholder for user first order commission rule', 'affiliate-for-woocommerce' )
			);
			parent::__construct( $args );
		}

		/**
		 * Method to return possible operators.
		 *
		 * @return array $possible_operators
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
				'yes' => _x( 'Yes', 'Positive value title for user first order commission rule', 'affiliate-for-woocommerce' ),
				'no'  => _x( 'No', 'Negative value title for user first order commission rule', 'affiliate-for-woocommerce' ),
			);
		}

		/**
		 * Method to get context value for this rule.
		 *
		 * @param array $args The arguments for context.
		 *
		 * @return string Return referral medium of the order
		 */
		public function get_context_value( $args = array() ) {
			return ! empty( $args['order'] ) && $args['order'] instanceof WC_Order && $this->is_user_first_purchaser( $args['order'] ) ? 'yes' : 'no';
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

		/**
		 * Method to check if the given order's customer is a first purchaser.
		 *
		 * @param WC_Order $order Order object or order ID.
		 *
		 * @return bool True if the user is a first-time purchaser, false otherwise.
		 */
		public function is_user_first_purchaser( $order = null ) {

			if ( ! $order instanceof WC_Order ) {
				return false;
			}

			$current_user_id = get_current_user_id();
			$billing_email   = is_callable( array( $order, 'get_billing_email' ) ) ? $order->get_billing_email() : '';

			if ( empty( $current_user_id ) && empty( $billing_email ) ) {
				return false;
			}

			$afwc_api        = is_callable( array( 'AFWC_API', 'get_instance' ) ) ? AFWC_API::get_instance() : null;
			$user_order_list = is_callable( array( $afwc_api, 'get_orders_by_customer' ) )
				? $afwc_api->get_orders_by_customer(
					array(
						'customer_id'   => ! empty( $current_user_id ) ? intval( $current_user_id ) : 0,
						'billing_email' => ! empty( $billing_email ) ? $billing_email : '',
					)
				)
				: array();

			if ( empty( $user_order_list ) || ! is_array( $user_order_list ) ) {
				// Return true if user does not have any order.
				return true;
			}

			$order_id = is_callable( array( $order, 'get_id' ) ) ? intval( $order->get_id() ) : 0;

			// Remove the current order_id from the user order list.
			$key = array_search( $order_id, $user_order_list, true );
			if ( false !== $key ) {
				unset( $user_order_list[ $key ] );
			}

			/**
			 * Developers can modify the user order list to change the logic for checking the user's first order.
			 *
			 * @since 8.0.0
			 *
			 * @param array $user_order_list User order history as an array of order ids; this does not include the current order id.
			 * @param int   $order_id        Current order id.
			 * @param array [source => AFWC_User_First_Order_Commission].
			 */
			$user_order_list = apply_filters( 'afwc_user_order_list_to_check_first_order', $user_order_list, $order_id, array( 'source' => $this ) );

			// Return true if does not have any old order otherwise false.
			return empty( $user_order_list );
		}

	}
}
