<?php
/**
 * Class for rule groups
 *
 * @package     affiliate-for-woocommerce/includes/rules/
 * @since       8.17.0
 * @version     1.0.0
 */

namespace AFWC\Rules;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( Groups::class ) ) {

	/**
	 * Class for Rule Groups
	 */
	class Groups {

		/**
		 * Variable to hold the outer groups condition
		 *
		 * @var string
		 */
		private $condition;

		/**
		 * Variable to hold the available group object
		 *
		 * @var Group[]
		 */
		private $groups;

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
				$this->set_groups( $props['rules'] );
			}
		}

		/**
		 * Assign the group objects of the each group available in the group.
		 *
		 * @param  array $rule_groups The available rule groups.
		 *
		 * @return void
		 */
		public function set_groups( $rule_groups = array() ) {

			if ( empty( $rule_groups ) || ! is_array( $rule_groups ) ) {
				return;
			}

			foreach ( $rule_groups as $rule_group ) {
				$this->groups[] = new Group( $rule_group, $this->context );
			}
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
		 * Validate all groups.
		 *
		 * @return bool Whether all groups are validated.
		 */
		public function validate() {

			if ( empty( $this->groups ) || ! is_array( $this->groups ) ) {
				return true; // Return true if not any group found.
			}

			$res_array = array();

			foreach ( $this->groups as $rule_group ) {
				$group_validation = is_callable( array( $rule_group, 'validate' ) ) ? $rule_group->validate() : false;
				if ( ( 'AND' === $this->condition ) && ! $group_validation ) {
					return false; // If any rule group is not valid, mark the entire groups invalid.
				}
				$res_array[] = $group_validation;
				if ( $group_validation ) {
					$this->set_validated_values( $rule_group->get_validated_values() );
				}
			}

			if ( 'AND' === $this->condition ) {
				return true; // If all rule groups validated and condition is true, Make the rule to validate. For falsy value, it handles in the previous rule validation loop.
			}

			return 'OR' === $this->condition && in_array( true, array_unique( $res_array ), true );
		}

		/**
		 * Check if a specific item is valid for the individual validated values groups based on condition.
		 *
		 * @param string|int $item_key The key of the item to validate.
		 * @param string     $rule_key The key of the rule to check.
		 * @param bool       $is_primary_rule_exists Flag indicating if there is any primary rule exists for the item.
		 *
		 * @return bool True if the item is valid; false otherwise.
		 */
		public function is_valid_item( $item_key = '', $rule_key = '', $is_primary_rule_exists = false ) {

			if ( empty( $this->validated_values ) || ! is_array( $this->validated_values ) ) {
				return array();
			}

			$found_individual_rule = false;
			$validation_results    = array(); // TO hold validation results for each group.

			foreach ( $this->validated_values as $group_values ) {

				// Check if the item exists under the primary validated rule values.
				$is_valid = ! empty( $group_values[ $rule_key ] )
					&& is_array( $group_values[ $rule_key ] )
					&& in_array( $item_key, $group_values[ $rule_key ], true );

				// Check if the item exists under additional rule values.
				if ( ! $is_valid ) {
					$is_valid = ! empty( $group_values['additional_rules'] )
						&& ! empty( $group_values['additional_rules'][ $rule_key ] )
						&& is_array( $group_values['additional_rules'][ $rule_key ] )
						&& in_array( $item_key, $group_values['additional_rules'][ $rule_key ], true );
				}

				// If condition is OR and the item is valid for any validated values group, return true immediately.
				if ( 'OR' === $this->condition && $is_valid ) {
					return true;
				}

				// Validation for AND condition: If additional rules are empty, primary rule holder item should not check with other primary rules.
				$validation_results[] = ( 'AND' === $this->condition )
					&& ( $is_valid || ( $is_primary_rule_exists && empty( $group_values['additional_rules'][ $rule_key ] ) ) );
			}

			// For AND condition, ensure no group validation result is false.
			return 'AND' === $this->condition && ! in_array( false, array_unique( $validation_results ), true );
		}

		/**
		 * Retrieve validated values based on the context items and validated rule values.
		 *
		 * @param array $context_items The items to validate against the validated rules.
		 *
		 * @return array An array of validated items.
		 */
		public function get_validated_values( $context_items = array() ) {

			if ( empty( $this->validated_values )
				|| ! is_array( $this->validated_values )
				|| empty( $context_items )
				|| ! is_array( $context_items )
			) {
				return array();
			}

			$primary_validated_items = array(); // To holds all primary validated items from all groups.

			// Merge primary rule values from all groups.
			foreach ( $this->validated_values as $group_values ) {
				if ( isset( $group_values['additional_rules'] ) ) {
					unset( $group_values['additional_rules'] ); // Exclude additional rules.
				}
				$primary_validated_items = array_merge_recursive( $primary_validated_items, $group_values );
			}

			$result = array();

			// Iterate through each context key and its items to determine validation.
			foreach ( $context_items as $context_key => $item_list ) {

				if ( empty( $item_list ) && ! is_array( $item_list ) ) {
					continue;
				}

				$result[ $context_key ] = array();

				foreach ( $item_list as $item_key ) {
					/**
					 * Flag to check if any rule item exists in the primary rule.
					 * If any rule values are exists in the primary rule of a group but the same rule is not present in the another group,
					 * It will be mark as valid.
					 *
					 * For example
					 * Groups (All)
					 * Group-1 (All)
					 * Rule-1: Product is Hat
					 * Group-2 (All)
					 * Rule-2: Product is Shoes
					 * Then both the product should valid for the plan.
					 */
					$is_item_in_primary_rule_values = ! empty( $primary_validated_items[ $context_key ] )
						&& is_array( $primary_validated_items[ $context_key ] )
						&& in_array( $item_key, $primary_validated_items[ $context_key ], true );

					// Validate the item based on the rules and conditions.
					if ( $this->is_valid_item( $item_key, $context_key, $is_item_in_primary_rule_values ) ) {
						$result[ $context_key ][] = $item_key;
					}
				}
			}

			return $result;
		}

	}
}
