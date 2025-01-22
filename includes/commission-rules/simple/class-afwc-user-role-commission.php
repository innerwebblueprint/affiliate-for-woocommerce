<?php
/**
 * Class for User/Customer role commissions rule
 *
 * @package     affiliate-for-woocommerce/includes/commission-rules/simple/
 * @since       7.16.0
 * @version     2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\String_Rule;

if ( ! class_exists( 'AFWC_User_Role_Commission' ) && class_exists( String_Rule::class ) ) {

	/**
	 * User role commission rule class
	 */
	class AFWC_User_Role_Commission extends String_Rule {

		/**
		 * Constructor
		 *
		 * @param array $args props.
		 */
		public function __construct( $args = array() ) {
			$this->set_context_key( 'user_role' );
			$this->set_category( 'user' );
			$this->set_title(
				_x( 'Role', 'Title for the user role commission rule', 'affiliate-for-woocommerce' )
			);
			$this->set_placeholder(
				_x( 'Select a user role', 'Placeholder for the user role commission rule', 'affiliate-for-woocommerce' )
			);
			parent::__construct( $args );
		}

		/**
		 * Method to return possible operators.
		 *
		 * @return array Return the array of possible operators for the rule.
		 */
		public function get_possible_operators() {
			$this->exclude_operators( array( 'gt', 'gte', 'lt', 'eq', 'lte', 'neq' ) );
			return $this->possible_operators;
		}

		/**
		 * Method to return possible options.
		 *
		 * @return array Return the pre-defined options for the rule.
		 */
		public function get_options() {
			$roles = wp_roles();
			return $roles instanceof WP_Roles && ! empty( $roles->role_names ) ? $roles->role_names : array();
		}

		/**
		 * Method to filter values for comparison.
		 *
		 * @param array $roles The user roles to filter.
		 *
		 * @return array Return the array of valid user roles.
		 */
		public function filter_values( $roles = array() ) {

			if ( empty( $roles ) || ! is_array( $roles ) ) {
				return array();
			}

			$available_roles     = $this->get_options();
			$available_role_keys = ! empty( $available_roles ) && is_array( $available_roles ) ? array_keys( $available_roles ) : array();

			if ( empty( $available_role_keys ) || ! is_array( $available_role_keys ) ) {
				return array();
			}

			return array_filter(
				$roles,
				function( $role ) use ( $available_role_keys ) {
					// Validate the provided user role is exists in the options.
					return in_array( $role, $available_role_keys, true );
				}
			);
		}

		/**
		 * Method to get context value for this rule.
		 *
		 * @param array $args The arguments for context.
		 *
		 * @return array Return the customer's user role.
		 */
		public function get_context_value( $args = array() ) {

			if ( empty( $args['order'] ) || ! $args['order'] instanceof WC_Order || ! is_callable( array( $args['order'], 'get_user' ) ) ) {
				return array();
			}

			$user = $args['order']->get_user();
			return $user instanceof WP_User && ! empty( $user->roles ) && is_array( $user->roles ) ? $user->roles : array();
		}

		/**
		 * Method to set validated values to this rule.
		 *
		 * @param array  $values The values.
		 * @param object $context_obj context object.
		 *
		 * @return void
		 */
		public function set_validated_values( $values = array(), $context_obj = null ) {

			parent::set_validated_values( $values );

			$context_args = is_callable( array( $context_obj, 'get_args' ) ) ? $context_obj->get_args() : array();

			if ( empty( $context_args ) || empty( $context_args['ordered_product_ids'] ) || ! is_array( $context_args['ordered_product_ids'] ) ) {
				return;
			}

			// Add the validated products to additional rules key.
			parent::set_validated_values(
				array( 'additional_rules' => array( 'product_id' => $context_args['ordered_product_ids'] ) )
			);
		}
	}
}
