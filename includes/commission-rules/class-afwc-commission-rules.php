<?php
/**
 * Class to register commission rules dynamically by scanning current folder classes.
 *
 * @package     affiliate-for-woocommerce/includes/commissions-rules/
 * @since       8.17.0
 * @version     2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Rule_Registry;
use AFWC\Rules\Rule;

if ( ! class_exists( 'AFWC_Commission_Rules' ) ) {

	/**
	 * Class for Commission rules registerer.
	 */
	class AFWC_Commission_Rules {

		/**
		 * Variable to hold instance of this class.
		 *
		 * @var AFWC_Commission_Rules
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class.
		 *
		 * @return AFWC_Commission_Rules Singleton object of this class.
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		private function __construct() {
			add_filter( 'afwc_rules_categories', array( $this, 'get_categories' ) );
			add_action( 'afwc_rules_registration', array( $this, 'get_rules' ) );
		}

		/**
		 * Registers the categories.
		 *
		 * @param array $categories The registered categories.
		 *
		 * @return array The updated categories.
		 */
		public function get_categories( $categories = array() ) {

			if ( empty( $categories ) || ! is_array( $categories ) ) {
				$categories = array();
			}

			return array_merge(
				$categories,
				array(
					'affiliate' => _x( 'Affiliate', 'Commission group title for affiliate rules', 'affiliate-for-woocommerce' ),
					'product'   => _x( 'Product', 'Commission group title for product rules', 'affiliate-for-woocommerce' ),
					'medium'    => _x( 'Medium', 'Commission group title for medium rules', 'affiliate-for-woocommerce' ),
					'user'      => _x( 'User', 'Commission group title for user rules', 'affiliate-for-woocommerce' ),
				)
			);
		}

		/**
		 * Registers all commission rules classes within the same folder.
		 *
		 * @param Rule_Registry $registry The registry class responsible for managing rule registrations.
		 *
		 * @return void
		 */
		public function get_rules( $registry = null ) {
			if ( ! $registry instanceof Rule_Registry
				|| ! is_callable( array( $registry, 'is_registered' ) )
				|| ! is_callable( array( $registry, 'register' ) )
			) {
				return;
			}

			// Get the classes from both 'simple' and 'dynamic' folders.
			$rule_classes = array_merge(
				$this->get_rule_classes_from_folder( 'simple' ),
				$this->get_rule_classes_from_folder( 'dynamic' )
			);

			if ( empty( $rule_classes ) || ! is_array( $rule_classes ) ) {
				return;
			}

			foreach ( $rule_classes as $slug => $class_name ) {

				if ( $registry->is_registered( $slug ) || ! class_exists( $class_name ) ) {
					continue;
				}

				$rule_instance = new $class_name();

				if ( ! $rule_instance instanceof Rule ) {
					continue; // Skip if the provided instance is not a rule.
				}

				if ( is_callable( array( $rule_instance, 'get_rules' ) ) ) {
					// Register the dynamic rules.
					$this->register_dynamic_rules( $registry, $rule_instance );
				} else {
					// Register the simple rules.
					$registry->register( $slug, $rule_instance );
				}
			}
		}

		/**
		 * Register dynamic rules.
		 *
		 * @param Rule_Registry $registry The registry to register rules in.
		 * @param object        $dynamic_class_instance The dynamic rule class instance.
		 *
		 * @return void
		 */
		public function register_dynamic_rules( $registry = null, $dynamic_class_instance = null ) {

			if ( ! $registry instanceof Rule_Registry
				|| ! is_callable( array( $registry, 'is_registered' ) )
				|| ! is_callable( array( $registry, 'register' ) )
				|| ! is_object( $dynamic_class_instance )
				|| ! is_callable( array( $dynamic_class_instance, 'get_rules' ) )
			) {
				return;
			}

			$rules = $dynamic_class_instance->get_rules();

			if ( empty( $rules ) || ! is_array( $rules ) ) {
				return;
			}

			foreach ( $rules as $slug => $rule_title ) {

				if ( $registry->is_registered( $slug ) ) {
					continue;
				}

				$rule_instance = clone $dynamic_class_instance;

				if ( is_callable( array( $rule_instance, 'set_context_key' ) ) ) {
					$rule_instance->set_context_key( $slug );
				}

				if ( is_callable( array( $rule_instance, 'set_title' ) ) ) {
					$rule_instance->set_title( $rule_title );
				}

				if ( is_callable( array( $rule_instance, 'set_placeholder' ) ) ) {
					$rule_instance->set_placeholder(
						sprintf(
							// translators: Rule title.
							_x( 'Search for a %s', 'Placeholder for commission rules', 'affiliate-for-woocommerce' ),
							strtolower( $rule_title )
						)
					);
				}

				$registry->register( $slug, $rule_instance );
			}
		}

		/**
		 * Retrieve all rule classes from a specific folder.
		 *
		 * @param string $folder Folder name (e.g., 'simple' or 'dynamic').
		 * @return array Associative array of rule slugs and class names.
		 */
		private function get_rule_classes_from_folder( $folder = '' ) {
			$classes   = array();
			$directory = __DIR__ . '/' . $folder;
			$files     = glob( $directory . '/class-afwc-*-commission.php' );

			if ( empty( $files ) || ! is_array( $files ) ) {
				return array();
			}

			foreach ( $files as $file ) {
				if ( ! is_file( $file ) ) {
					continue;
				}

				include_once $file;

				$base_class = basename( $file, '.php' );
				$rule_slug  = str_replace( '-', '_', substr( $base_class, strlen( 'class-afwc-' ), -strlen( '-commission' ) ) );

				$class_name = ucwords(
					str_replace(
						array( 'class-', '-', 'afwc' ),
						array( '', '_', 'AFWC' ),
						$base_class
					),
					'_'
				);

				if ( class_exists( $class_name ) ) {
					$classes[ $rule_slug ] = $class_name;
				}
			}

			if ( ! empty( $classes ) ) {
				ksort( $classes );
			}

			return $classes;
		}
	}
}

AFWC_Commission_Rules::get_instance();
