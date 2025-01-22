<?php
/**
 * Class for Product commissions rule
 *
 * @package     affiliate-for-woocommerce/includes/commission-rules/simple/
 * @since       2.6.0
 * @version     2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Number_Rule;

if ( ! class_exists( 'AFWC_Product_Commission' ) && class_exists( Number_Rule::class ) ) {

	/**
	 * Product commission rule class
	 */
	class AFWC_Product_Commission extends Number_Rule {

		/**
		 * Constructor
		 *
		 * @param array $args props.
		 */
		public function __construct( $args = array() ) {
			$this->set_context_key( 'product_id' );
			$this->set_category( 'product' );
			$this->set_title(
				_x( 'Product', 'Commission rule title', 'affiliate-for-woocommerce' )
			);
			$this->set_placeholder(
				_x( 'Search for a product', 'commission rule placeholder', 'affiliate-for-woocommerce' )
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
		 * Get product id name map
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

			$search_meta_key = ( false === $for_ajax && is_array( $term ) ) ? 'post__in' : 's';

			$products = get_posts(
				array(
					'post_type'      => array( 'product', 'product_variation' ),
					'numberposts'    => -1,
					'post_status'    => 'publish',
					'fields'         => 'ids',
					$search_meta_key => $term,
				)
			);

			if ( ! empty( $products ) ) {
				foreach ( $products as $id ) {
					$product = wc_get_product( $id );

					if ( $product instanceof WC_Product && is_callable( array( $product, 'get_formatted_name' ) ) ) {
						$rule_values[ $id ] = wp_strip_all_tags( $product->get_formatted_name() );
					}
				}
			}

			return $rule_values;
		}

		/**
		 * Method to filter the products IDs for comparison.
		 * Add all the variations if any variable product detected.
		 *
		 * @param array $product_ids The product IDs.
		 *
		 * @return array Return the product IDs.
		 */
		public function filter_values( $product_ids = array() ) {
			// Return if there is not any product IDs to filter.
			if ( empty( $product_ids ) ) {
				return array();
			}

			/*
			* The below function will return the variation IDs as well as the parent variable ID for the variable product type.
			* It may give issues when introducing the '=' operator in commission rules.
			*
			* @since 6.34.0
			*/
			return afwc_get_variable_variation_product_ids( $product_ids );
		}

		/**
		 * Method to get context value for this rule.
		 *
		 * @param array $args The arguments for context.
		 *
		 * @return array Return the product IDs from the order
		 */
		public function get_context_value( $args = array() ) {
			return ! empty( $args['ordered_product_ids'] ) ? $args['ordered_product_ids'] : array();
		}

		/**
		 * Method to set validated values to this rule.
		 * Add related products from the validated categories as additional rules.
		 *
		 * @param array  $values The values.
		 * @param object $context_obj context object.
		 * @param object $operator The rule operator.
		 *
		 * @return void
		 */
		public function set_validated_values( $values = array(), $context_obj = null, $operator = '' ) {

			if ( in_array( $operator, array( 'nin' ), true ) ) {
				$context_args = is_callable( array( $context_obj, 'get_args' ) ) ? $context_obj->get_args() : array();

				if ( empty( $context_args ) || empty( $context_args['ordered_product_ids'] ) || ! is_array( $context_args['ordered_product_ids'] ) ) {
					return;
				}
				// Add the validated products to additional rules key.
				parent::set_validated_values(
					array( 'additional_rules' => array( 'product_id' => $context_args['ordered_product_ids'] ) )
				);
			} else {
				parent::set_validated_values( $values );
			}
		}
	}
}
