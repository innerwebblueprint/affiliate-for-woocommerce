<?php
/**
 * Main rule class
 *
 * @package     affiliate-for-woocommerce/includes/rules/
 * @since       2.5.0
 * @version     2.0.0
 */

namespace AFWC\Rules;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( Rule::class ) ) {

	/**
	 * Abstract Rule class
	 */
	abstract class Rule {

		/**
		 * Variable to hold rule title
		 *
		 * @var string
		 */
		protected $title = '';

		/**
		 * Variable to hold context key
		 *
		 * @var string
		 */
		protected $context_key = '';

		/**
		 * Variable to hold rule category
		 *
		 * @var string
		 */
		protected $category = '';

		/**
		 * Variable to hold input placeholder for the rule
		 *
		 * @var string
		 */
		protected $input_placeholder = '';

		/**
		 * Variable to hold list of possible operators
		 *
		 * @var array
		 */
		protected $possible_operators = array();

		/**
		 * Variable to hold operator for validation
		 *
		 * @var string
		 */
		protected $operator = '';

		/**
		 * Variable to hold value to validate.
		 *
		 * @var mixed
		 */
		protected $value = '';

		/**
		 * Variable to hold list of possible values.
		 *
		 * @var array
		 */
		protected $possible_values = array();

		/**
		 * Variable to hold validated values, stored as key-value pairs.
		 *
		 * @var array
		 */
		private $validated_values = array();

		/**
		 * Constructor
		 *
		 * @param array $props Properties for initializing the rule.
		 */
		public function __construct( $props = array() ) {
			$this->set_props( $props );
		}

		/**
		 * Sets the properties for the rule.
		 *
		 * @param array $props Properties to set.
		 * @return void
		 */
		public function set_props( $props = array() ) {
			$this->operator           = isset( $props['operator'] ) ? $props['operator'] : '';
			$this->value              = isset( $props['value'] ) ? $props['value'] : '';
			$this->possible_operators = array(
				array(
					'op'    => 'eq',
					'label' => _x( 'is', 'Equal to operator in rule', 'affiliate-for-woocommerce' ),
					'type'  => 'single',
				),
				array(
					'op'    => 'neq',
					'label' => _x( 'is not', 'Not operator in rule', 'affiliate-for-woocommerce' ),
					'type'  => 'single',
				),
				array(
					'op'    => 'in',
					'label' => _x( 'any of', 'Any of operator in rule', 'affiliate-for-woocommerce' ),
					'type'  => 'multi',
				),
				array(
					'op'    => 'nin',
					'label' => _x( 'none of', 'None of operator in rule', 'affiliate-for-woocommerce' ),
					'type'  => 'multi',
				),
			);
		}

		/**
		 * Sets the context key for the rule.
		 *
		 * @param string $key Context key.
		 * @return void
		 */
		public function set_context_key( $key = '' ) {
			$this->context_key = $key;
		}

		/**
		 * Sets the rule category.
		 *
		 * @param string $cat Category slug.
		 * @return void
		 */
		public function set_category( $cat = '' ) {
			$this->category = $cat;
		}

		/**
		 * Sets the rule title.
		 *
		 * @param string $title Title of the rule.
		 * @return void
		 */
		public function set_title( $title = '' ) {
			$this->title = $title;
		}

		/**
		 * Sets the input placeholder.
		 *
		 * @param string $placeholder Placeholder text.
		 * @return void
		 */
		public function set_placeholder( $placeholder = '' ) {
			$this->input_placeholder = $placeholder;
		}

		/**
		 * Sets possible values for the rule.
		 *
		 * @param array $values Possible values.
		 * @return void
		 */
		public function set_possible_values( $values = array() ) {
			$this->possible_values = $values;
		}

		/**
		 * Gets the context key.
		 *
		 * @return string Context key.
		 */
		public function get_context_key() {
			return $this->context_key;
		}

		/**
		 * Gets the category slug.
		 *
		 * @return string Category.
		 */
		public function get_category() {
			return $this->category;
		}

		/**
		 * Gets the title of the rule.
		 *
		 * @return string Title.
		 */
		public function get_title() {
			return $this->title;
		}

		/**
		 * Gets the input placeholder.
		 *
		 * @return string Input placeholder.
		 */
		public function get_placeholder() {
			return $this->input_placeholder;
		}

		/**
		 * Gets the possible values.
		 *
		 * @return array Possible values.
		 */
		public function get_possible_values() {
			return $this->possible_values;
		}

		/**
		 * Gets the possible operators.
		 *
		 * @return array Possible operators.
		 */
		public function get_possible_operators() {
			return $this->possible_operators;
		}

		/**
		 * Gets the value to validate.
		 *
		 * @return mixed Value.
		 */
		public function get_value() {
			return $this->value;
		}

		/**
		 * Excludes operators from the possible operators.
		 *
		 * @param array $operators Operators to exclude.
		 * @return void
		 */
		public function exclude_operators( $operators = array() ) {
			if ( empty( $operators ) || ! is_array( $operators ) ) {
				return;
			}
			$this->possible_operators = array_values(
				array_filter(
					$this->possible_operators,
					function ( $item ) use ( $operators ) {
						return ! empty( $item['op'] ) && ! in_array( $item['op'], $operators, true );
					}
				)
			);
		}

		/**
		 * Retrieves the context value from the base or child context.
		 *
		 * @param \AFWC\Rules\Context $rule_context Rule context object.
		 * @return mixed Context value.
		 */
		public function context_value( $rule_context = null ) {
			$context_key = $this->get_context_key();

			if ( empty( $context_key ) ) {
				return array();
			}

			if ( empty( $rule_context->base_context[ $context_key ] ) ) {
				// Fetch fresh value from child class if not in base context.
				$rule_context->base_context[ $context_key ] = $this->get_context_value(
					is_callable( array( $rule_context, 'get_args' ) ) ? $rule_context->get_args() : array()
				);

				// Set the additional context value if required for any rule.
				if ( is_callable( array( $this, 'set_additional_context_value' ) ) ) {
					$this->set_additional_context_value( $rule_context );
				}
			}

			return $rule_context->base_context[ $context_key ];
		}

		/**
		 * Adds validated values to the internal storage as key-value pairs.
		 *
		 * @param array $values Validated values.
		 * @return void
		 */
		public function set_validated_values( $values = array() ) {
			if ( is_array( $values ) && ! empty( $values ) ) {
				$this->validated_values += $values;
			}
		}

		/**
		 * Retrieves all validated values.
		 *
		 * @return array Validated values.
		 */
		public function get_validated_values() {
			return $this->validated_values;
		}

		/**
		 * Abstract method to validate the rule.
		 *
		 * @param object $context_obj Context object.
		 */
		abstract protected function validate( $context_obj = null );

		/**
		 * Abstract method to fetch the context value.
		 *
		 * @param array $args Arguments for fetching the context value.
		 */
		abstract protected function get_context_value( $args = array() );
	}
}
