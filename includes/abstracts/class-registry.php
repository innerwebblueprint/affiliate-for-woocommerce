<?php
/**
 * Abstract class for dynamic classes registrations.
 *
 * @package     affiliate-for-woocommerce/includes/abstracts
 * @since       8.17.0
 * @version     1.0.0
 */

namespace AFWC;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( Registry::class ) ) {

	/**
	 * Dynamic class registry class.
	 */
	class Registry {

		/**
		 * List of registered instances.
		 *
		 * @var array
		 */
		protected $instances = array();

		/**
		 * Registers a new instance.
		 *
		 * @param string $name        instance name.
		 * @param mixed  $instance    object/class instance.
		 *
		 * @return void
		 */
		public function register( $name = '', $instance = null ) {
			if ( empty( $name ) ) {
				return;
			}
			$this->instances[ $name ] = $instance;
		}

		/**
		 * Checks if an instance is already registered.
		 *
		 * @param string $name instance name.
		 *
		 * @return bool True if the instance is registered, false otherwise.
		 */
		public function is_registered( $name = '' ) {
			return ! empty( $name ) && isset( $this->instances[ $name ] );
		}

		/**
		 * Retrieves a registered instance by name.
		 *
		 * @param string|array $name instance name(s).
		 *
		 * @return mixed Returns the instance or an array of instances, null if not found.
		 */
		public function get_registered( $name = '' ) {
			if ( empty( $name ) ) {
				return is_array( $name ) ? array() : null;
			}

			$registry_names = is_array( $name ) ? $name : (array) $name;
			$registries     = array();

			foreach ( $registry_names as $registry ) {
				if ( ! $this->is_registered( $registry ) ) {
					continue; // Skip if the registry is not available.
				}
				$registries[ $registry ] = $this->instances[ $registry ];
			}

			return is_array( $name ) ? $registries : reset( $registries );
		}

		/**
		 * Retrieves all registered instances.
		 *
		 * @return array List of all registered instances.
		 */
		public function get_all_registered() {
			return $this->instances;
		}
	}
}
