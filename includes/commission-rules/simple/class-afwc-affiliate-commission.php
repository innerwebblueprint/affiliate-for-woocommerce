<?php
/**
 * Class for Affiliate commissions rule
 *
 * @package     affiliate-for-woocommerce/includes/commission-rules/simple/
 * @since       2.5.0
 * @version     2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Number_Rule;

if ( ! class_exists( 'AFWC_Affiliate_Commission' ) && class_exists( Number_Rule::class ) ) {

	/**
	 * Affiliate commission rule class
	 */
	class AFWC_Affiliate_Commission extends Number_Rule {

		/**
		 * Constructor
		 *
		 * @param array $args props.
		 */
		public function __construct( $args = array() ) {
			$this->set_context_key( 'affiliate_id' );
			$this->set_category( 'affiliate' );
			$this->set_title(
				_x( 'Affiliate', 'Commission rule title', 'affiliate-for-woocommerce' )
			);
			$this->set_placeholder(
				_x( 'Search for an affiliate', 'commission rule placeholder', 'affiliate-for-woocommerce' )
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
		 * Get user id name map
		 *
		 * @param string|array $term The searched term.
		 * @param bool         $for_ajax Check if call with ajax.
		 *
		 * @return $rule_values array
		 */
		public function search_values( $term = '', $for_ajax = true ) {

			$rule_values = array();

			if ( empty( $term ) ) {
				return $rule_values;
			}

			global $affiliate_for_woocommerce;

			if ( true === $for_ajax ) {
				$search = array(
					'search'         => '*' . $term . '*',
					'search_columns' => array( 'ID', 'user_nicename', 'user_login', 'user_email' ),
				);
			} else {
				// Fetch affiliates by user ids.
				if ( ! is_array( $term ) ) {
					$term = (array) $term;
				}
				$search = array(
					'include' => $term,
				);
			}

			$rule_values = is_callable( array( $affiliate_for_woocommerce, 'get_affiliates' ) ) ? $affiliate_for_woocommerce->get_affiliates( $search ) : $rule_values;

			return $rule_values;
		}

		/**
		 * Method to get context value for this rule.
		 *
		 * @param array $args The arguments for context.
		 *
		 * @return int Return the affiliate ID.
		 */
		public function get_context_value( $args = array() ) {
			return ! empty( $args['affiliate'] ) && $args['affiliate'] instanceof AFWC_Affiliate && ! empty( $args['affiliate']->affiliate_id )
				? (int) $args['affiliate']->affiliate_id
				: 0;
		}

		/**
		 * Method to set validated values to this rule.
		 * Add related products from the validated categories as additional rules.
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
