<?php
/**
 * Class for Rule context
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

if ( ! class_exists( Context::class ) ) {

	/**
	 * Context class for the rule
	 */
	class Context {

		/**
		 * Variable to hold base context
		 *
		 * @var array
		 */
		public $base_context = array();

		/**
		 * Variable to hold arguments
		 *
		 * @var array
		 */
		private $args = array();

		/**
		 * Constructor
		 *
		 * @param  array $args The arguments.
		 */
		public function __construct( $args = array() ) {
			$this->args = $args;
		}

		/**
		 * Get the available arguments
		 *
		 * @return array Return the array of arguments.
		 */
		public function get_args() {
			return $this->args;
		}

		/**
		 * Set the arguments.
		 *
		 * @param string|int $key The argument key.
		 * @param mixed      $value The values to add.
		 *
		 * @return void.
		 */
		public function set_args( $key = '', $value = '' ) {
			$this->args[ $key ] = $value;
		}
	}
}
