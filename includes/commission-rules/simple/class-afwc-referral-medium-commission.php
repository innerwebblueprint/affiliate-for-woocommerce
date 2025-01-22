<?php
/**
 * Class for commissions rule based on referral medium
 *
 * @package   affiliate-for-woocommerce/includes/commission-rules/simple/
 * @since     6.12.0
 * @version   2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\String_Rule;

if ( ! class_exists( 'AFWC_Referral_Medium_Commission' ) && class_exists( String_Rule::class ) ) {

	/**
	 * Class for referral medium commission rule
	 */
	class AFWC_Referral_Medium_Commission extends String_Rule {

		/**
		 * Constructor
		 *
		 * @param array $args props.
		 */
		public function __construct( $args = array() ) {
			$this->set_context_key( 'referral_medium' );
			$this->set_category( 'medium' );
			$this->set_title(
				_x( 'Referral Medium', 'Title for referral medium commission rule', 'affiliate-for-woocommerce' )
			);
			$this->set_placeholder(
				_x( 'Select a medium', 'Placeholder for referral medium commission rule', 'affiliate-for-woocommerce' )
			);
			parent::__construct( $args );
		}

		/**
		 * Method to return possible operators.
		 *
		 * @return array
		 */
		public function get_possible_operators() {
			$this->exclude_operators( array( 'gt', 'gte', 'lt', 'eq', 'lte', 'neq' ) );
			return $this->possible_operators;
		}

		/**
		 * Method to return possible options.
		 *
		 * @return array Return the pre-defined options for the rule.
		 */
		public function get_options() {
			return array(
				'link'   => _x( 'Link', 'Referral medium option name for link', 'affiliate-for-woocommerce' ),
				'coupon' => _x( 'Coupon', 'Referral medium option name for coupon', 'affiliate-for-woocommerce' ),
			);
		}

		/**
		 * Method to filter values for comparison.
		 *
		 * @param array $mediums The mediums to filter.
		 *
		 * @return array Return the mediums.
		 */
		public function filter_values( $mediums = array() ) {
			return array_filter( $mediums );
		}

		/**
		 * Method to get context value for this rule.
		 *
		 * @param array $args The arguments for context.
		 *
		 * @return array Return referral medium of the order
		 */
		public function get_context_value( $args = array() ) {
			if ( empty( $args['order'] ) && ! $args['order'] instanceof WC_Order ) {
				return array();
			}

			global $affiliate_for_woocommerce;

			$affiliate_id = ! empty( $args['affiliate'] ) && $args['affiliate'] instanceof AFWC_Affiliate && ! empty( $args['affiliate']->affiliate_id )
				? $args['affiliate']->affiliate_id
				: 0;
			$order        = $args['order'];
			$used_coupons = is_callable( array( $order, 'get_coupon_codes' ) ) ? $order->get_coupon_codes() : array();

			return is_callable( array( $affiliate_for_woocommerce, 'get_referral_type' ) ) ? (array) $affiliate_for_woocommerce->get_referral_type( $affiliate_id, $used_coupons ) : array();
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
