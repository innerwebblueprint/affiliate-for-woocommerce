<?php
/**
 * Main class for WooCommerce Subscription Compatibility.
 *
 * @package     affiliate-for-woocommerce/includes/integration/woocommerce-subscriptions/
 * @since       6.1.0
 * @version     1.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Rule_Registry;
use AFWC\Rules\Rule;

if ( ! class_exists( 'WCS_AFWC_Compatibility' ) ) {

	/**
	 *  Compatibility class for WooCommerce Subscription plugin.
	 */
	class WCS_AFWC_Compatibility {

		/**
		 * Variable to hold instance of WCS_AFWC_Compatibility
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Constructor
		 */
		private function __construct() {
			add_filter( 'afwc_commissions_section_admin_settings', array( $this, 'add_settings' ) );
			add_filter( 'afwc_endpoint_account_settings_after_key', array( $this, 'endpoint_account_settings_after_key' ) );
			add_filter( 'afwc_id_for_order', array( $this, 'get_affiliate_id_for_subscription_order' ), 9, 2 );
			add_filter( 'afwc_add_referral_in_admin_emails_setting_description', array( $this, 'referral_in_admin_emails_setting_description' ), 10, 1 );
			add_filter( 'afwc_allowed_emails_for_referral_details', array( $this, 'allowed_subscription_email' ) );
			// Hook to register the commission rules.
			add_filter( 'afwc_rules_categories', array( $this, 'get_categories' ) );
			add_action( 'afwc_rules_registration', array( $this, 'get_rules' ) );
		}

		/**
		 * Get single instance of WCS_AFWC_Compatibility.
		 *
		 * @return WCS_AFWC_Compatibility Singleton object of WCS_AFWC_Compatibility.
		 */
		public static function get_instance() {
			// Check if instance is already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Function to do version compare on WooCommerce Subscriptions Core.
		 *
		 * @param  string $version The version number.
		 * @return boolean
		 */
		public static function is_wcs_core_gte( $version = '' ) {
			if ( empty( $version ) || ! class_exists( 'WC_Subscriptions_Core_Plugin' ) || ! is_callable( array( 'WC_Subscriptions_Core_Plugin', 'instance' ) ) ) {
				return false;
			}

			$wcs_core         = WC_Subscriptions_Core_Plugin::instance();
			$wcs_core_version = is_callable( array( $wcs_core, 'get_library_version' ) ) ? $wcs_core->get_library_version() : 0;

			if ( empty( $wcs_core_version ) ) {
				return false;
			}

			return version_compare( $wcs_core_version, $version, '>=' );
		}

		/**
		 * Function to do version compare on WooCommerce Subscriptions plugin version.
		 *
		 * @param  string $version The version number.
		 * @return boolean
		 */
		public static function is_wcs_gte( $version = '' ) {
			if ( empty( $version ) || ! class_exists( 'WC_Subscriptions_Plugin' ) || ! is_callable( array( 'WC_Subscriptions_Plugin', 'instance' ) ) ) {
				return false;
			}

			$wcs_plugin         = WC_Subscriptions_Plugin::instance();
			$wcs_plugin_version = is_callable( array( $wcs_plugin, 'get_plugin_version' ) ) ? $wcs_plugin->get_plugin_version() : 0;

			if ( empty( $wcs_plugin_version ) ) {
				return false;
			}

			return version_compare( $wcs_plugin_version, $version, '>=' );
		}

		/**
		 * Function to add subscription specific settings
		 *
		 * @param  array $settings Existing settings.
		 * @return array  $settings
		 */
		public function add_settings( $settings = array() ) {
			$wc_subscriptions_options = array(
				array(
					'name'              => _x( 'Issue recurring commission?', 'recurring commission setting title', 'affiliate-for-woocommerce' ),
					'desc'              => _x( 'Enable this to give affiliate commissions for subscription recurring/renewal orders', 'recurring commission setting description', 'affiliate-for-woocommerce' ),
					'desc_tip'          => 'no' === get_option( 'is_recurring_commission', 'no' ) ?
						_x(
							"We have deprecated this setting. Since you had it disabled, we have automatically created a new plan for you: 'Do not issue recurring commission as Issue recurring commission? is disabled'. Please review the plan for more details.",
							'recurring commission setting description tip',
							'affiliate-for-woocommerce'
						)
						: _x(
							'We have deprecated this setting. To stop recurring/renewal commissions, create a new commission plan and add a rule: Renewal >= 0 and set the commission = 0.',
							'recurring commission setting description tip',
							'affiliate-for-woocommerce'
						),
					'id'                => 'is_recurring_commission',
					'type'              => 'checkbox',
					'default'           => 'no',
					'checkboxgroup'     => 'start',
					'autoload'          => false,
					'custom_attributes' => array(
						'disabled' => 'disabled',
					),
				),
			);

			array_splice( $settings, ( count( $settings ) - 1 ), 0, $wc_subscriptions_options );

			return $settings;
		}

		/**
		 * Return field key after which the setting should appear
		 *
		 * @return string
		 */
		public function endpoint_account_settings_after_key() {
			return 'woocommerce_myaccount_subscription_payment_method_endpoint';
		}

		/**
		 * Return affiliate ID for subscription order.
		 *
		 * @param int   $affiliate_id The affiliate ID.
		 * @param array $args The arguments.
		 *
		 * @return int Return the affiliate ID from the parent subscription if the order type is renewal otherwise default.
		 */
		public function get_affiliate_id_for_subscription_order( $affiliate_id = 0, $args = array() ) {
			if ( empty( $args ) || empty( $args['order_id'] ) ) {
				return $affiliate_id;
			}

			$order_id = intval( $args['order_id'] );

			$sub_types = array( 'renewal' );

			if ( ! wcs_order_contains_subscription( $order_id, $sub_types ) ) {
				return $affiliate_id;
			}

			$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => $sub_types ) );

			if ( ! empty( $subscriptions ) ) {
				$subscription = is_array( $subscriptions ) ? end( $subscriptions ) : $subscriptions;
				$parent_id    = $subscription instanceof WC_Subscription && is_callable( array( $subscription, 'get_parent_id' ) ) ? $subscription->get_parent_id() : 0;

				if ( empty( $parent_id ) ) {
					return $affiliate_id;
				}

				$afwc_api          = ( ! empty( $args['source'] ) ) ? $args['source'] : AFWC_API::get_instance();
				$affiliate_details = is_callable( array( $afwc_api, 'get_affiliate_by_order' ) ) ? $afwc_api->get_affiliate_by_order( intval( $parent_id ) ) : array();
				$affiliate_id      = ( ! empty( $affiliate_details ) && ! empty( $affiliate_details['affiliate_id'] ) ) ? intval( $affiliate_details['affiliate_id'] ) : 0;
			}

			return $affiliate_id;
		}

		/**
		 * Return updated setting description.
		 *
		 * @param string $description The setting description.
		 *
		 * @return string The updated setting description.
		 */
		public function referral_in_admin_emails_setting_description( $description = '' ) {
			if ( empty( $description ) ) {
				return '';
			}

			return _x( 'Include affiliate referral details in the WooCommerce New order, WooCommerce Subscriptions New Renewal Order and Subscription Switched emails', 'Admin setting description', 'affiliate-for-woocommerce' );
		}

		/**
		 * Return new renewal order email key if recurring commission is enabled.
		 *
		 * @param array $emails The allowed emails.
		 *
		 * @return array The allowed emails.
		 */
		public function allowed_subscription_email( $emails = array() ) {

			if ( is_array( $emails ) ) {
				array_push( $emails, 'new_renewal_order', 'new_switch_order' );
			}

			return $emails;
		}

		/**
		 * Method to get subscription related description in the plans sidebar.
		 *
		 * @return string Return the description.
		 */
		public static function plan_description() {
			return _x(
				'To stop recurring commissions on subscription renewals, create a new commission plan and add a rule: Renewal >= 0 and set the commission = 0. Set this plan at the top to give priority over other plans.',
				'Plan description for renewal commission',
				'affiliate-for-woocommerce'
			);
		}

		/**
		 * Method to get subscription related admin notice of plan dashboard.
		 *
		 * @return string Return the notice text.
		 */
		public static function plan_admin_notice() {

			if ( 'no' === get_option( 'afwc_show_subscription_admin_dashboard_notice', 'no' ) ) {
				return '';
			}

			return _x(
				"We have deprecated the Issue recurring commission setting. Since you had the setting disabled, we have automatically created a new plan for you 'Do not issue recurring commission as Issue recurring commission? is disabled'. Please review the plan for more details.",
				'Admin notice for Issue recurring commission deprecated',
				'affiliate-for-woocommerce'
			);
		}

		/**
		 * Registers the rule categories.
		 *
		 * @param array $categories The registered categories.
		 *
		 * @return array The updated categories.
		 */
		public function get_categories( $categories = array() ) {

			if ( ! is_array( $categories ) ) {
				return $categories;
			}

			$categories['subscription'] = _x( 'Subscription', 'Commission group title for subscription rules', 'affiliate-for-woocommerce' );
			return $categories;
		}

		/**
		 * Registers the subscription commission rules classes.
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

			$rule_classes = $this->get_rule_classes_from_folder( 'commission-rules' );

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
		 * Retrieve all rule classes from a specific folder.
		 *
		 * @param string $folder Folder name.
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

WCS_AFWC_Compatibility::get_instance();
