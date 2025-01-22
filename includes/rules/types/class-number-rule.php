<?php
/**
 * Class for handling number rule
 *
 * @package     affiliate-for-woocommerce/includes/rules/types/
 * @since       2.5.0
 * @version     2.0.0
 */

namespace AFWC\Rules;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( Number_Rule::class ) && class_exists( Rule::class ) ) {

	/**
	 * Class for Number Rule
	 */
	abstract class Number_Rule extends Rule {

		/**
		 * Constructor
		 *
		 * @param  array $props props.
		 */
		public function __construct( $props = array() ) {
			parent::__construct( $props );
			$this->possible_operators = array_merge(
				$this->possible_operators,
				array(
					array(
						'op'    => 'gt',
						'label' => _x( '>', 'Greater than operator label for commission plan', 'affiliate-for-woocommerce' ),
						'type'  => 'single',
					),
					array(
						'op'    => 'gte',
						'label' => _x( '>=', 'Greater than or equal to operator label for commission plan', 'affiliate-for-woocommerce' ),
						'type'  => 'single',
					),
					array(
						'op'    => 'lt',
						'label' => _x( '<', 'Less than operator label for commission plan', 'affiliate-for-woocommerce' ),
						'type'  => 'single',
					),
					array(
						'op'    => 'lte',
						'label' => _x( '<=', 'Less than or equal to operator label for commission plan', 'affiliate-for-woocommerce' ),
						'type'  => 'single',
					),
				)
			);
		}

		/**
		 * Function to validate rule
		 *
		 * @param object $context_obj The context Object.
		 *
		 * @return bool Return true if validated, otherwise false.
		 */
		public function validate( $context_obj = null ) {
			$result = false;

			if ( empty( $context_obj ) ) {
				return $result;
			}

			$current = is_callable( array( $this, 'context_value' ) ) ? $this->context_value( $context_obj ) : null;

			// Return if the values does not exists in the context.
			if ( is_null( $current ) ) {
				return false;
			}

			$value = $this->get_value();

			if ( ! empty( $value ) && is_callable( array( $this, 'filter_values' ) ) ) {
				// Filter the values for comparison.
				$value = $this->filter_values( $value );
			}

			if ( ! empty( $current ) && is_array( $current ) ) {
				$current = array_map(
					function ( $c ) {
						return ( '' !== $c ) ? intval( $c ) : 0;
					},
					$current
				);
			} else {
				$current = ( '' !== $current ) ? intval( $current ) : 0;
			}

			if ( ! empty( $value ) && is_array( $value ) ) {
				$value = array_map(
					function ( $v ) {
						return ( '' !== $v ) ? intval( $v ) : 0;
					},
					$value
				);
			} else {
				$value = ( '' !== $value ) ? intval( $value ) : 0;
			}

			switch ( $this->operator ) {
				case 'eq':
					$result = ( is_array( $current ) ) ? in_array( $current, $value, true ) : ( $current === $value );
					break;
				case 'neq':
					$result = ( is_array( $current ) ) ? ! in_array( $current, $value, true ) : ( $current !== $value );
					break;
				case 'in':
					if ( is_array( $value ) && is_array( $current ) ) {
						$intersection = array_intersect( $value, $current );
						$result       = ( count( $intersection ) >= 1 );
						$current      = $intersection;
					} else {
						$result = ( is_array( $value ) ) ? in_array( $current, $value, true ) : false;
					}
					break;

				case 'nin':
					if ( is_array( $value ) && is_array( $current ) ) {
						$intersection = array_intersect( $value, $current );
						$result       = ( count( $intersection ) <= 0 );
						$current      = array_diff( $current, $value );
					} else {
						$result = ( is_array( $value ) ) ? ! in_array( $current, $value, true ) : false;
					}

					break;
				case 'gt':
					$result = ( $current > $value );
					break;
				case 'gte':
					$result = ( $current >= $value );
					break;
				case 'lt':
					$result = ( $current < $value );
					break;
				case 'lte':
					$result = ( $current <= $value );
					break;
			}

			if ( $result ) {
				$validated_values = (array) $current;
				$context_key      = is_callable( array( $this, 'get_context_key' ) ) ? $this->get_context_key() : '';

				$this->set_validated_values(
					array( $context_key => $validated_values ),
					$context_obj,
					$this->operator
				);
			}
			return $result;
		}
	}
}
