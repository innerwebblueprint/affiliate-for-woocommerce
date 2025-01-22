<?php
/**
 * Class for Affiliate tag commissions rule
 *
 * @package     affiliate-for-woocommerce/includes/commission-rules/simple/
 * @since       2.7.1
 * @version     2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Number_Rule;

if ( ! class_exists( 'AFWC_Affiliate_Tag_Commission' ) && class_exists( Number_Rule::class ) ) {

	/**
	 * Affiliate tag commission rule class
	 */
	class AFWC_Affiliate_Tag_Commission extends Number_Rule {

		/**
		 * Constructor
		 *
		 * @param array $args props.
		 */
		public function __construct( $args = array() ) {
			$this->set_context_key( 'affiliate_tag' );
			$this->set_category( 'affiliate' );
			$this->set_title(
				_x( 'Affiliate Tag', 'Commission rule title', 'affiliate-for-woocommerce' )
			);
			$this->set_placeholder(
				_x( 'Search for an affiliate tag', 'commission rule placeholder', 'affiliate-for-woocommerce' )
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
		 * Get product affiliate tags based on the search term.
		 *
		 * @param string|array $term     Term name for searching or array of affiliate tag IDs for inclusion.
		 * @param bool         $for_ajax If true, search for tags; otherwise, include specific tag IDs.
		 *
		 * @return array Array of affiliate tag IDs and names. Returns an empty array if no affiliate tag match or an error occurs.
		 */
		public function search_values( $term = '', $for_ajax = true ) {

			if ( empty( $term ) ) {
				return array();
			}

			if ( ! $for_ajax && ! is_array( $term ) ) {
				$term = (array) $term;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => 'afwc_user_tags', // Taxonomy name for affiliate tag.
					'fields'     => 'id=>name',
					'hide_empty' => false,
					'name__like' => $for_ajax ? $term : '', // Search by affiliate tag name if ajax search.
					'include'    => ! $for_ajax ? array_map( 'intval', $term ) : '',  // Include specific affiliate tag IDs if not ajax search.
				)
			);

			return ! is_wp_error( $terms ) && ! empty( $terms ) && is_array( $terms ) ? $terms : array();
		}

		/**
		 * Method to get context value for this rule.
		 *
		 * @param array $args The arguments for context.
		 *
		 * @return array Array of affiliate tags
		 */
		public function get_context_value( $args = array() ) {
			$tags = ! empty( $args['affiliate'] ) && is_callable( array( $args['affiliate'], 'get_tags' ) )
				? $args['affiliate']->get_tags()
				: array();
			return ! empty( $tags ) && is_array( $tags ) ? array_keys( $tags ) : array();
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
