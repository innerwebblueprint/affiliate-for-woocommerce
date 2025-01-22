<?php
/**
 * Class to handle affiliate account settings
 *
 * @package     affiliate-for-woocommerce/includes/admin/settings/
 * @since       7.18.0
 * @version     1.0.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Affiliate_Account_Admin_Settings' ) ) {

	/**
	 * Main class for affiliate account admin settings
	 */
	class AFWC_Affiliate_Account_Admin_Settings {

		/**
		 * Variable to hold instance of AFWC_Affiliate_Account_Admin_Settings
		 *
		 * @var self $instance
		 */
		private static $instance = null;

		/**
		 * Section name
		 *
		 * @var string $section
		 */
		private $section = 'affiliate-account';

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Affiliate_Account_Admin_Settings Singleton object of this class
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			add_filter( "afwc_{$this->section}_section_admin_settings", array( $this, 'get_section_settings' ) );
		}

		/**
		 * Method to get affiliate account section settings
		 *
		 * @return array
		 */
		public function get_section_settings() {

			$afwc_affiliate_account_admin_settings = array(
				array(
					'title' => _x( "Affiliate's Account", "Affiliate's account setting section title", 'affiliate-for-woocommerce' ),
					'desc'  => _x( 'By default, an affiliate can see their dashboard under My Account > Affiliate. A default endpoint is already set under WooCommerce > Settings > Advanced tab > Account endpoints.', 'description text for how to change the endpoint of my account page.', 'affiliate-for-woocommerce' ),
					'type'  => 'title',
					'id'    => 'afwc_affiliate_account_admin_settings',
				),
				array(
					'name'     => _x( 'Custom page for affiliate dashboard', 'Admin setting name for affiliate dashboard page', 'affiliate-for-woocommerce' ),
					'desc'     => sprintf(
						/* translators: Affiliate dashboard shortcode. */
						_x( 'Allow affiliates to view their dashboard on any page using the %s shortcode. If no page or any non-published page is selected, the dashboard will be shown on the default My Account > Affiliate page.', 'Admin setting description for setup custom affiliate dashboard page', 'affiliate-for-woocommerce' ),
						'<code>[afwc_dashboard]</code>'
					),
					'id'       => 'afwc_custom_affiliate_dashboard_page_id',
					'type'     => 'single_select_page_with_search',
					'class'    => 'wc-page-search',
					'args'     => array(
						'exclude' =>
							array(
								wc_get_page_id( 'myaccount' ),
							),
					),
					'autoload' => false,
				),
				array(
					'name'     => _x( 'Register as an affiliate', 'setting name', 'affiliate-for-woocommerce' ),
					'desc'     => _x( "Show the Affiliate Registration Form in existing users' My Account to allow them to join your affiliate program", 'setting description', 'affiliate-for-woocommerce' ),
					'desc_tip' => _x( "Disabling this will hide the Affiliate Registration Form in the existing users' My Account.", 'setting description tip', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_show_registration_form_in_account',
					'default'  => 'yes',
					'type'     => 'checkbox',
					'autoload' => false,
				),
				array(
					'type' => 'sectionend',
					'id'   => "afwc_{$this->section}_admin_settings",
				),
			);

			return $afwc_affiliate_account_admin_settings;
		}

	}

}

AFWC_Affiliate_Account_Admin_Settings::get_instance();
