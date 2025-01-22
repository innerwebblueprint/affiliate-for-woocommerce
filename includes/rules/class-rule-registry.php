<?php
/**
 * Class for rule registration
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

use AFWC\Registry;

if ( ! class_exists( Registry::class, false ) ) {
	include_once AFWC_PLUGIN_DIRPATH . '/includes/abstracts/class-registry.php';
}

if ( ! class_exists( Rule_Registry::class ) && class_exists( Registry::class ) ) {

	/**
	 * Class for registering the rules.
	 */
	class Rule_Registry extends Registry {

		/**
		 * Variable to hold instance of this class
		 *
		 * @var Rule_Registry
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return Rule_Registry Singleton object of this class
		 */
		public static function get_instance() {
			// Check if instance is already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 */
		public function __construct() {
			// Trigger the hook to register the rules.
			do_action( 'afwc_rules_registration', $this );
		}

		/**
		 * Get the required rule classes.
		 *
		 * @param array $rules Array of rules.
		 *
		 * @return array Array of rule objects.
		 */
		public function get_rule_classes( $rules = array() ) {

			if ( empty( $rules ) || ! is_array( $rules ) ) {
				return array();
			}

			$rule_classes = array();

			foreach ( $rules as $rule ) {
				if ( empty( $rule['type'] ) ) {
					// rule's key is available in the 'type' key.
					continue;
				}
				$rule_object = $this->get_registered( $rule['type'] );

				// Clone the object to create a new rule class.
				$fresh_rule_object = clone $rule_object;

				if ( is_callable( array( $fresh_rule_object, 'set_props' ) ) ) {
					$fresh_rule_object->set_props( $rule );
				}

				$rule_classes[] = $fresh_rule_object;
			}

			return $rule_classes;
		}

		/**
		 * Get the rule categories.
		 *
		 * @return array Array of rule categories.
		 */
		public function get_categories() {
			return apply_filters( 'afwc_rules_categories', array() );
		}
	}
}
