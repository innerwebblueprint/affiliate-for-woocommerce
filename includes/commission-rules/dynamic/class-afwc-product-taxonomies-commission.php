<?php
/**
 * A dynamic commission rule class for product taxonomies.
 *
 * @package     affiliate-for-woocommerce/includes/commission-rules/dynamic/
 * @since       8.17.0
 * @version     1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Number_Rule;

if ( ! class_exists( 'AFWC_Product_Taxonomies_Commission' ) && class_exists( Number_Rule::class ) ) {

	/**
	 * Product taxonomy dynamic commission rule.
	 */
	class AFWC_Product_Taxonomies_Commission extends Number_Rule {

		/**
		 * Stores the product taxonomy term product mapping
		 *
		 * @var array
		 */
		private $term_product_map = array();

		/**
		 * Constructor
		 *
		 * @param array $args props.
		 */
		public function __construct( $args = array() ) {
			$this->set_category( 'product' ); // Set the category for all the rules.
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
		 * Method to get the dynamic rules.
		 *
		 * @return array Return the key-value pair of rule name and rule title.
		 */
		public function get_rules() {
			$taxonomies = get_taxonomies();

			if ( empty( $taxonomies ) || ! is_array( $taxonomies ) ) {
				return array();
			}

			$rules = array();

			foreach ( $taxonomies as $tax ) {

				// Allow the taxonomies which supports the WooCommerce products excluding the product attributes.
				if ( ! is_object_in_taxonomy( 'product', $tax ) || taxonomy_is_product_attribute( $tax ) ) {
					continue;
				}

				$tax_obj = get_taxonomy( $tax );

				if ( ! $tax_obj instanceof WP_Taxonomy ) {
					continue; // Skip if object is not a WordPress taxonomy.
				}

				if ( 'product_cat' === $tax ) {
					/**
					 * Support backward compatibility
					 * As the product category rules was already present before dynamic taxonomy rule registration.
					 * And the rule's name/slug was 'product_category'.
					 */
					$tax = 'product_category';
				}

				$rules[ $tax ] = esc_html( ! empty( $tax_obj->label ) ? $tax_obj->label : $tax );
			}

			return $rules;
		}

		/**
		 * Get product taxonomy terms map based on the search term.
		 *
		 * @param string|array $term     Term name for searching or array of term IDs for inclusion.
		 * @param bool         $for_ajax If true, search for terms with a name similar to the term; otherwise, include specific term IDs.
		 *
		 * @return array Array of term IDs and names. Returns an empty array if no terms match or an error occurs.
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
					'taxonomy'   => $this->get_taxonomy_slug(), // Taxonomy name for terms.
					'fields'     => 'id=>name',
					'hide_empty' => false,
					'name__like' => $for_ajax ? $term : '', // Search by term name if ajax search.
					'include'    => ! $for_ajax ? array_map( 'intval', $term ) : '',  // Include specific term IDs if not ajax search.
				)
			);

			return ! is_wp_error( $terms ) && ! empty( $terms ) && is_array( $terms ) ? $terms : array();
		}

		/**
		 * Retrieve the context value for this rule.
		 * This method fetches taxonomy term IDs associated with the products in the order
		 * And store the term-product map for future usage.
		 *
		 * @param array $args Arguments for context.
		 *
		 * @return int[] Array of unique taxonomy term IDs. Returns an empty array if no terms are found or input is invalid.
		 */
		public function get_context_value( $args = array() ) {

			if ( empty( $args ) || empty( $args['order'] ) || ! $args['order'] instanceof WC_Order ) {
				return array();
			}

			$order_items = is_callable( array( $args['order'], 'get_items' ) ) ? $args['order']->get_items() : array();

			if ( empty( $order_items ) || ! is_array( $order_items ) ) {
				return array();
			}

			$tax_terms        = array(); // To hold the collected taxonomy terms.
			$current_taxonomy = $this->get_taxonomy_slug(); // Retrieve the current context taxonomy.

			foreach ( $order_items as $item ) {

				if ( ! $item instanceof WC_Order_Item ) {
					continue; // Skip if the items is not an order item instance.
				}

				$product_id   = is_callable( array( $item, 'get_product_id' ) ) ? (int) $item->get_product_id() : 0;
				$variation_id = is_callable( array( $item, 'get_variation_id' ) ) ? (int) $item->get_variation_id() : 0;

				$item_id = ! empty( $variation_id ) ? $variation_id : $product_id;

				if ( empty( $item_id ) ) {
					continue; // Skip if item ID is not valid.
				}

				// Get taxonomy terms for the current item.
				$current_terms = $this->get_product_term_ids(
					is_object_in_taxonomy( 'product_variation', $current_taxonomy ) ? $item_id : $product_id, // Prioritize the variation ID if the taxonomy supports product variations.
					$current_taxonomy
				);

				if ( empty( $current_terms ) || ! is_array( $current_terms ) ) {
					continue; // Skip if no valid terms are found.
				}

				// Cache the term-product mapping for future use.
				$this->set_term_product_map( $item_id, $current_terms );

				// Merge terms into the collection.
				$tax_terms = array_merge( $tax_terms, $current_terms );
			}

			return array_unique( $tax_terms );
		}

		/**
		 * Retrieve all term IDs associated with a product, including ancestor terms.
		 *
		 * @param int    $product_id The product ID for which terms are fetched.
		 * @param string $tax        The taxonomy to retrieve terms from.
		 *
		 * @return array Array of term IDs, including ancestors. Returns an empty array if no terms are found.
		 */
		public function get_product_term_ids( $product_id = 0, $tax = '' ) {
			if ( empty( $product_id ) || empty( $tax ) ) {
				return array();
			}

			// Fetch the terms for the given product and taxonomy.
			$current_terms = wc_get_product_term_ids( $product_id, $tax );

			if ( empty( $current_terms ) || ! is_array( $current_terms ) ) {
				return array();
			}

			// Array to hold all terms, including ancestors.
			$all_terms = $current_terms;

			// Loop through the terms and fetch ancestor terms.
			foreach ( $current_terms as $term_id ) {
				$ancestors = get_ancestors( $term_id, $tax );
				if ( empty( $ancestors ) || ! is_array( $ancestors ) ) {
					continue;
				}
				$all_terms = array_merge( $all_terms, $ancestors );
			}

			// Return only unique terms to prevent duplicates.
			return array_unique( $all_terms );
		}


		/**
		 * Method to build category product map.
		 *
		 * @param int   $product_id The product ID.
		 * @param array $terms The terms of the product.
		 *
		 * @return void
		 */
		public function set_term_product_map( $product_id = 0, $terms = array() ) {
			if ( empty( $product_id ) || empty( $terms ) || ! is_array( $terms ) ) {
				return;
			}

			foreach ( $terms as $term_id ) {
				$this->term_product_map[ $term_id ] = array_unique(
					array_merge(
						! empty( $this->term_product_map[ $term_id ] ) ? $this->term_product_map[ $term_id ] : array(),
						array( intval( $product_id ) )
					)
				);
			}
		}

		/**
		 * Method to set additional context values.
		 *
		 * @param object $context_obj context object.
		 *
		 * @return void
		 */
		public function set_additional_context_value( $context_obj = null ) {

			if ( ! is_callable( array( $context_obj, 'set_args' ) ) ) {
				return;
			}

			$context_args = is_callable( array( $context_obj, 'get_args' ) ) ? $context_obj->get_args() : array();

			// Set the attribute product mapping in the context.
			$context_obj->set_args(
				'taxonomy_term_product_map',
				! empty( $context_args['taxonomy_term_product_map'] )
					? ( $context_args['taxonomy_term_product_map'] + $this->term_product_map )
					: $this->term_product_map
			);
		}

		/**
		 * Method to set validated values to this rule.
		 * Add related products from the validated terms as additional rules.
		 *
		 * @param array  $values The values.
		 * @param object $context_obj context object.
		 *
		 * @return void
		 */
		public function set_validated_values( $values = array(), $context_obj = null ) {

			parent::set_validated_values( $values );

			if ( empty( $values ) || empty( $values[ $this->context_key ] ) || ! is_array( $values[ $this->context_key ] ) ) {
				return;
			}

			// Fetch the current validated terms.
			$validated_term_ids = ! empty( $values[ $this->context_key ] ) ? $values[ $this->context_key ] : array();

			if ( empty( $validated_term_ids ) || ! is_array( $validated_term_ids ) ) {
				return;
			}

			$product_ids      = array();
			$context_args     = is_callable( array( $context_obj, 'get_args' ) ) ? $context_obj->get_args() : array();
			$term_product_map = ! empty( $context_args['taxonomy_term_product_map'] ) ? $context_args['taxonomy_term_product_map'] : array();

			if ( ! empty( $term_product_map ) && is_array( $term_product_map ) ) {
				foreach ( $validated_term_ids as $term_id ) {
					if ( ! empty( $term_product_map[ $term_id ] ) ) {
						$product_ids = array_merge( $product_ids, $term_product_map[ $term_id ] );
					}
				}
			}

			if ( empty( $product_ids ) ) {
				return;
			}

			// Add the validated products to additional rules key.
			parent::set_validated_values(
				array( 'additional_rules' => array( 'product_id' => array_unique( $product_ids ) ) )
			);
		}

		/**
		 * Method to get the taxonomy slug for the current rule.
		 *
		 * @return string Return the taxonomy slug.
		 */
		public function get_taxonomy_slug() {
			$tax = $this->get_context_key();
			/**
			 * Support backward compatibility
			 * As the product category rules was already present before dynamic taxonomy rule registration.
			 * And the rule's name/slug was 'product_category'.
			 */
			return 'product_category' === $tax ? 'product_cat' : $tax;
		}
	}
}
