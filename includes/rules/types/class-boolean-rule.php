<?php
/**
 * Class for handling boolean rule
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

if ( ! class_exists( Boolean_Rule::class ) && class_exists( Rule::class ) ) {

	/**
	 * Class for Boolean Rule
	 */
	abstract class Boolean_Rule extends Rule {

		/**
		 * Constructor
		 *
		 * @param  array $props props.
		 */
		public function __construct( $props = array() ) {
			parent::__construct( $props );
			$this->possible_operators = array(
				array(
					'op'    => 'eq',
					'label' => _x( 'is', 'Equal to operator label for commission plan', 'affiliate-for-woocommerce' ),
					'type'  => 'select',
				),
			);
			$this->set_possible_values( array( 'yes', 'no' ) );
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

			switch ( $this->operator ) {
				case 'eq':
					$result = $current === $value;
					break;
				case 'neq':
					$result = $current !== $value;
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
