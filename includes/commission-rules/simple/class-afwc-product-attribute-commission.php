<?php
/**
 * Class for Product attribute commissions rule
 *
 * @package     affiliate-for-woocommerce/includes/commission-rules/simple/
 * @since       8.17.0
 * @version     1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Number_Rule;

if ( ! class_exists( 'AFWC_Product_Attribute_Commission' ) && class_exists( Number_Rule::class ) ) {

	/**
	 * Product Attributes commission rule class
	 */
	class AFWC_Product_Attribute_Commission extends Number_Rule {

		/**
		 * Stores the product attribute product mapping
		 *
		 * @var array
		 */
		private $attr_term_product_map = array();

		/**
		 * Constructor
		 *
		 * @param array $args props.
		 */
		public function __construct( $args = array() ) {
			$this->set_context_key( 'product_attribute' );
			$this->set_category( 'product' );
			$this->set_title(
				_x( 'Product Attributes', 'Commission rule title', 'affiliate-for-woocommerce' )
			);
			$this->set_placeholder(
				_x( 'Search for a product attributes', 'commission rule placeholder', 'affiliate-for-woocommerce' )
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
		 * Get product attribute terms id-name map.
		 *
		 * @param string|int[] $term The searched term or array of term IDs.
		 * @param bool         $for_ajax Whether this function is called via Ajax.
		 *
		 * @return array Map of term IDs and their respective labels.
		 */
		public static function search_values( $term = '', $for_ajax = true ) {

			$rule_values = array();

			if ( empty( $term ) ) {
				return $rule_values;
			}

			if ( ! current_user_can( 'edit_products' ) ) {
				wp_die( -1 );
			}

			$attributes = wc_get_attribute_taxonomies();

			if ( empty( $attributes ) || ! is_array( $attributes ) ) {
				return $rule_values;
			}

			if ( ! $for_ajax && ! is_array( $term ) ) {
				$term = (array) $term;
			}

			$search_text        = is_string( $term ) ? strtolower( trim( $term ) ) : '';
			$term_ids           = ! $for_ajax ? array_map( 'intval', $term ) : array();
			$matched_taxonomies = array();

			if ( ! empty( $search_text ) ) {

				foreach ( $attributes as $attribute_obj ) {
					if ( empty( $attribute_obj->attribute_name ) ) {
						continue;
					}

					$attribute_name  = $attribute_obj->attribute_name;
					$attribute_label = ! empty( $attribute_obj->attribute_label ) ? $attribute_obj->attribute_label : $attribute_name;

					// Match partial.
					if ( preg_match( '/\b' . preg_quote( $search_text, '/' ) . '/i', strtolower( $attribute_label ) ) ) {
						$matched_taxonomies[] = wc_attribute_taxonomy_name( $attribute_name );
					}
				}
			}

			// Prepare query arguments for retrieving terms.
			$args = array(
				'taxonomy'   => $matched_taxonomies,
				'orderby'    => 'name',
				'hide_empty' => false,
				'number'     => 100,
			);

			if ( $for_ajax && $search_text && empty( $matched_taxonomies ) ) {
				$args['name__like'] = $search_text;
			} elseif ( ! empty( $term_ids ) ) {
				$args['include'] = $term_ids;
			}

			// Fetch the terms.
			$attribute_terms = get_terms( $args );

			if ( is_wp_error( $attribute_terms ) || empty( $attribute_terms ) || ! is_array( $attribute_terms ) ) {
				return $rule_values;
			}

			// Process the terms and map them to their labels.
			foreach ( $attribute_terms as $term_obj ) {

				if ( empty( $term_obj->term_id ) || empty( $term_obj->taxonomy ) || ! taxonomy_is_product_attribute( $term_obj->taxonomy ) ) {
					continue;
				}

				$taxonomy       = $term_obj->taxonomy;
				$taxonomy_label = wc_attribute_label( $taxonomy );

				$rule_values[ $term_obj->term_id ] = sprintf(
					'%1$s : %2$s',
					esc_html( ! empty( $taxonomy_label ) ? $taxonomy_label : $taxonomy ),
					esc_html( $term_obj->name ? $term_obj->name : $term_obj->term_id )
				);
			}

			return $rule_values;
		}

		/**
		 * Method to get context value for this rule.
		 *
		 * @param array $args The arguments for context.
		 *
		 * @return array Return the attr_terms IDs of the order
		 */
		public function get_context_value( $args = array() ) {

			// Return an empty array if order object is not provided.
			if ( empty( $args['order'] ) || ! $args['order'] instanceof WC_Order ) {
				return array();
			}

			$items = is_callable( array( $args['order'], 'get_items' ) ) ? $args['order']->get_items() : array();

			// Return an empty array if order item is not available.
			if ( empty( $items ) || ! is_array( $items ) ) {
				return array();
			}

			$attribute_term_ids = array();

			foreach ( $items as $item ) {

				if ( ! $item instanceof WC_Order_Item ) {
					continue; // Skip if the items is not an order item instance.
				}

				$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null; // Get the product object from item.

				if ( ! $product instanceof WC_Product ) {
					continue; // Skip if the items is not an order item instance.
				}

				$current_term_ids = $this->get_taxonomy_terms( $product, $item );

				if ( empty( $current_term_ids ) || ! is_array( $current_term_ids ) ) {
					continue;
				}

				$product_id   = is_array( array( $item, 'get_product_id' ) ) ? $item->get_product_id() : 0;
				$variation_id = is_array( array( $item, 'get_variation_id' ) ) ? $item->get_variation_id() : 0;

				$this->set_attr_term_product_map(
					! empty( $variation_id ) ? $variation_id : $product_id, // If variation item, set variation ID, otherwise product ID.
					$current_term_ids
				);

				$attribute_term_ids = array_merge( $attribute_term_ids, $current_term_ids );
			}

			return array_unique( $attribute_term_ids );
		}

		/**
		 * Get attribute term IDs from a product.
		 *
		 * @param WC_Product    $product The WooCommerce product object.
		 * @param WC_Order_Item $item The WooCommerce order item object.
		 *
		 * @return array Array of unique attribute term IDs.
		 */
		public function get_taxonomy_terms( $product = null, $item = null ) {
			// Initialize terms array.
			$terms = array();

			if ( empty( $product )
				|| ! $product instanceof WC_Product
				|| ! is_callable( array( $product, 'get_id' ) ) ) {
				return $terms;
			}

			// Handle variation product attributes.
			if ( is_callable( array( $product, 'is_type' ) ) && $product->is_type( 'variation' ) ) {

				$meta_data = is_callable( array( $item, 'get_meta_data' ) ) ? $item->get_meta_data() : array();

				if ( empty( $meta_data ) || ! is_array( $meta_data ) ) {
					return $terms;
				}

				foreach ( $meta_data as $meta ) {
					if ( empty( $meta->key ) || empty( $meta->value ) ) {
						continue; // Skip if either meta key or values are not exists.
					}

					$meta_key   = rawurldecode( (string) $meta->key );
					$meta_value = rawurldecode( (string) $meta->value );

					$attribute_key = str_replace( 'attribute_', '', $meta_key );

					if ( taxonomy_exists( $attribute_key ) ) {
						$term = get_term_by( 'slug', $meta_value, $attribute_key );
						if ( ! is_wp_error( $term ) && $term instanceof WP_Term && ! empty( $term->term_id ) ) {
							$terms[] = $term->term_id;
						}
					}
				}
			} elseif ( is_callable( array( $product, 'get_attributes' ) ) ) {
				// Handle non-variation product attributes.
				$attributes = $product->get_attributes();

				if ( empty( $attributes ) || ! is_array( $attributes ) ) {
					return $terms;
				}

				foreach ( $attributes as $attribute_name => $attribute ) {

					if ( empty( $attribute['is_taxonomy'] )
						|| empty( $attribute['options'] )
						|| ! is_array( $attribute['options'] )
					) {
						continue; // Skip if either attribute is not a taxonomy or no options exist.
					}

					// Merge retrieved term IDs into the main terms array.
					$terms = array_merge( $terms, $attribute['options'] );
				}
			}

			// Return a unique array of term IDs to avoid duplicates.
			return array_unique( array_map( 'intval', $terms ) );
		}


		/**
		 * Method to build product attribute term and product map.
		 *
		 * @param int   $product_id The product ID.
		 * @param array $attr_terms The attr_terms of the product.
		 *
		 * @return void
		 */
		public function set_attr_term_product_map( $product_id = 0, $attr_terms = array() ) {
			if ( empty( $product_id ) || empty( $attr_terms ) || ! is_array( $attr_terms ) ) {
				return;
			}

			foreach ( $attr_terms as $term ) {
				$this->attr_term_product_map[ $term ] = array_unique(
					array_merge(
						! empty( $this->attr_term_product_map[ $term ] ) ? $this->attr_term_product_map[ $term ] : array(),
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
				'attribute_term_product_map',
				! empty( $context_args['attribute_term_product_map'] )
					? ( $context_args['attribute_term_product_map'] + $this->attr_term_product_map )
					: $this->attr_term_product_map
			);
		}

		/**
		 * Method to set validated values to this rule.
		 * Add related products from the validated attr_terms as additional rules.
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

			$product_ids = array();

			$context_args     = is_callable( array( $context_obj, 'get_args' ) ) ? $context_obj->get_args() : array();
			$term_product_map = ! empty( $context_args['attribute_term_product_map'] ) ? $context_args['attribute_term_product_map'] : array();

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
	}
}
