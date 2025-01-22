<?php
/**
 * Class for rule group
 *
 * @package     affiliate-for-woocommerce/includes/rules/
 * @since       2.7.0
 * @version     2.0.0
 */

namespace AFWC\Rules;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( Group::class ) ) {

	/**
	 * Class for Rule Group
	 */
	class Group {

		/**
		 * Variable to hold the group condition
		 *
		 * @var string
		 */
		private $condition;

		/**
		 * Variable to hold the available rule object
		 *
		 * @var Rule[]
		 */
		private $rules;

		/**
		 * Variable to hold the context
		 *
		 * @var Context
		 */
		private $context;

		/**
		 * Stores the validated values.
		 *
		 * @var array
		 */
		protected $validated_values = array();

		/**
		 * Constructor
		 *
		 * @param  array   $props The properties of rules.
		 * @param  Context $context The context.
		 */
		public function __construct( $props = array(), $context = null ) {
			$this->condition = ! empty( $props['condition'] ) ? $props['condition'] : 'AND';
			$this->context   = $context;
			if ( ! empty( $props['rules'] ) ) {
				$this->set_rules( $props['rules'] );
			}
		}

		/**
		 * Assign the rule objects of the each rule available in the group.
		 *
		 * @param  array $rules The available rules.
		 *
		 * @return void
		 */
		public function set_rules( $rules = array() ) {

			if ( empty( $rules ) ) {
				return;
			}

			$registry    = is_callable( array( Rule_Registry::class, 'get_instance' ) ) ? Rule_Registry::get_instance() : null;
			$this->rules = is_callable( array( $registry, 'get_rule_classes' ) ) ? $registry->get_rule_classes( $rules ) : array();
		}

		/**
		 * Add validated values to the internal storage.
		 *
		 * @param array $values Associative array of validated values.
		 * @return void
		 */
		public function set_validated_values( $values = array() ) {
			// Ensure input is a non-empty array.
			if ( ! is_array( $values ) || empty( $values ) ) {
				return;
			}

			$this->validated_values[] = $values;
		}

		/**
		 * Validate the group.
		 *
		 * @return bool Whether the group is validated.
		 */
		public function validate() {

			if ( empty( $this->rules ) || ! is_array( $this->rules ) ) {
				return true; // Return true if not any rule found in the group.
			}

			$res_array = array();

			foreach ( $this->rules as $rule ) {
				$rule_validation = is_callable( array( $rule, 'validate' ) ) ? $rule->validate( $this->context ) : false;
				if ( ( 'AND' === $this->condition ) && ! $rule_validation ) {
					return false;
				}
				$res_array[] = $rule_validation;

				if ( $rule_validation ) {
					$this->set_validated_values( $rule->get_validated_values() );
				}
			}

			if ( 'AND' === $this->condition ) {
				return true; // For falsy value, it handles in the previous rule validation loop.
			}

			return 'OR' === $this->condition && in_array( true, array_unique( $res_array ), true );
		}

		/**
		 * Retrieve validated values of the group.
		 *
		 * @return array An array of validated items.
		 */
		public function get_validated_values() {
			if ( empty( $this->validated_values ) || ! is_array( $this->validated_values ) ) {
				return array();
			}

			$primary_validated_rules = array();

			// Merge the all the validated rule values from all primary rules.
			foreach ( $this->validated_values as $item ) {
				if ( isset( $item['additional_rules'] ) ) {
					unset( $item['additional_rules'] );
				}
				$primary_validated_rules = array_merge_recursive( $primary_validated_rules, $item );
			}

			$result                  = $primary_validated_rules;
			$merged_additional_rules = array();

			// Merge the additional Rule values.
			foreach ( $this->validated_values as $item ) {

				if ( empty( $item['additional_rules'] ) || ! is_array( $item['additional_rules'] ) ) {
					continue;
				}

				foreach ( $item['additional_rules'] as $rule_key => $values ) {
					// Merge the additional rule from individual rules.
					$merged_additional_rules[ $rule_key ] = ! empty( $merged_additional_rules[ $rule_key ] )
						? ( ( 'AND' === $this->condition )
							? array_intersect( $merged_additional_rules[ $rule_key ], $values )
							: array_unique( array_merge( $merged_additional_rules[ $rule_key ], $values ) ) )
						: $values;
				}
			}

			// Generate result.
			if ( ! empty( $merged_additional_rules ) && is_array( $merged_additional_rules ) ) {
				// Add the primary validated rules to result with removing the non-eligible values based on additional rule values.
				foreach ( $merged_additional_rules as $rule_key => $values ) {
					if ( ! empty( $primary_validated_rules[ $rule_key ] )
						&& is_array( $primary_validated_rules[ $rule_key ] )
						&& ( 'AND' === $this->condition )
					) {
						$result[ $rule_key ] = array_intersect( $primary_validated_rules[ $rule_key ], $values );
						// Remove the additional value if it's used for the primary rule exists in the same group.
						unset( $merged_additional_rules[ $rule_key ] );
					}
				}
				$result['additional_rules'] = $merged_additional_rules;
			}

			return $result;
		}
	}
}
