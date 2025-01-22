<?php
/**
 * Class for handling string rule
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

if ( ! class_exists( String_Rule::class ) && class_exists( Rule::class ) ) {

	/**
	 * Class for String Rule
	 */
	abstract class String_Rule extends Rule {

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

			if ( is_callable( array( $this, 'filter_values' ) ) ) {
				$value   = $this->filter_values( $value );
				$current = $this->filter_values( $current );
			}

			switch ( $this->operator ) {
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
