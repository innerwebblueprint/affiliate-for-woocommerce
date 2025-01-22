<?php
/**
 * Class to handle general settings
 *
 * @package     affiliate-for-woocommerce/includes/admin/settings/
 * @since       7.18.0
 * @version     1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_General_Admin_Settings' ) ) {

	/**
	 * Main class for general section setting
	 */
	class AFWC_General_Admin_Settings {

		/**
		 * Variable to hold instance of AFWC_General_Admin_Settings
		 *
		 * @var self $instance
		 */
		private static $instance = null;

		/**
		 * Section name
		 *
		 * @var string $section
		 */
		private $section = 'general';

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_General_Admin_Settings Singleton object of this class
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
		 * Method to get general section settings
		 *
		 * @return array
		 */
		public function get_section_settings() {
			global $wp_roles;

			$product_id_to_name = array();
			$excluded_products  = get_option( 'afwc_storewide_excluded_products', array() );
			if ( ! empty( $excluded_products ) && is_array( $excluded_products ) ) {
				foreach ( $excluded_products as $index => $product_id ) {
					$product_id_to_name[ $product_id ] = get_the_title( $product_id );
				}
			}

			$afwc_general_admin_settings = array(
				array(
					'title' => _x( 'General', 'General setting section title', 'affiliate-for-woocommerce' ),
					'type'  => 'title',
					'id'    => 'afwc_general_admin_settings',
				),
				array(
					'name'              => _x( 'Affiliate users roles', 'Affiliate users roles setting name', 'affiliate-for-woocommerce' ),
					'desc'              => _x( 'Users with these roles automatically become affiliates.', 'Description for Affiliate users roles setting', 'affiliate-for-woocommerce' ),
					'id'                => 'affiliate_users_roles',
					'type'              => 'multiselect',
					'class'             => 'wc-enhanced-select',
					'desc_tip'          => false,
					'options'           => ! empty( $wp_roles->role_names ) && is_array( $wp_roles->role_names ) ? $wp_roles->role_names : array(),
					'autoload'          => false,
					'custom_attributes' => array(
						'data-placeholder' => _x( 'Select user roles', 'placeholder for excluded products setting', 'affiliate-for-woocommerce' ),
					),
				),
				array(
					'name'              => _x( 'Excluded products', 'Excluded products setting name', 'affiliate-for-woocommerce' ),
					'desc'              => _x( 'All products are eligible for affiliate commission by default. If you want to exclude some, list them here.', 'Description for Excluded products setting', 'affiliate-for-woocommerce' ),
					'id'                => 'afwc_storewide_excluded_products',
					'type'              => 'multiselect',
					'class'             => 'wc-product-search',
					'desc_tip'          => false,
					'options'           => $product_id_to_name,
					'autoload'          => false,
					'custom_attributes' => array(
						'data-placeholder'  => _x( 'Search by product name or ID', 'placeholder for excluded products setting', 'affiliate-for-woocommerce' ),
						'data-exclude_type' => 'grouped, external',
					),
				),
				array(
					'name'     => _x( 'Approval method', 'Approval method setting name', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Automatically approve all submissions via Affiliate Registration Form - no manual review needed', 'Description for Approval method setting', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_auto_add_affiliate',
					'type'     => 'checkbox',
					'default'  => 'no',
					'desc_tip' => _x( 'Disabling this will require you to review and approve affiliates yourself. They won\'t become affiliates until you approve.', 'Description for Approval method setting tooltip', 'affiliate-for-woocommerce' ),
					'autoload' => false,
				),
				array(
					'name'              => _x( 'Cookie duration (in days)', 'Cookie duration setting name', 'affiliate-for-woocommerce' ),
					'desc'              => _x( 'Use 0 for "session only" referrals. Use 36500 for 100 year / lifetime referrals. If someone makes a purchase within these many days of their first referred visit, affiliate will be credited for the sale.', 'Description for Cookie duration setting', 'affiliate-for-woocommerce' ),
					'id'                => 'afwc_cookie_expiration',
					'type'              => 'number',
					'default'           => 60,
					'autoload'          => false,
					'desc_tip'          => false,
					'custom_attributes' => array(
						'min' => 0,
					),
				),
				array(
					'name'        => _x( 'Affiliate manager email', 'Affiliate manager email setting name', 'affiliate-for-woocommerce' ),
					'desc'        => _x( 'Affiliates will see a link to contact you in their dashboard - and the link will point to this email address. Leave this field blank to hide the contact link.', 'Description for Affiliate manager email setting', 'affiliate-for-woocommerce' ),
					'id'          => 'afwc_contact_admin_email_address',
					'type'        => 'text',
					'placeholder' => _x( 'Enter email address', 'Placeholder for Affiliate manager email setting', 'affiliate-for-woocommerce' ),
					'autoload'    => false,
					'desc_tip'    => false,
				),
				array(
					'name'     => _x( 'Affiliate landing pages', 'Affiliate landing pages setting name', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Enable this to allow assigning landing pages, posts, or products to an affiliate', 'Description for Affiliate landing pages setting', 'affiliate-for-woocommerce' ),
					'desc_tip' => _x( 'Use the <code>Affiliate Landing Page</code> meta box on a single page, post, or product to assign it to an affiliate which they can promote without using an affiliate link. The commission will be calculated based on the commission plan.', 'Description tip for Affiliate landing pages setting', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_enable_landing_pages',
					'type'     => 'checkbox',
					'default'  => 'no',
					'autoload' => false,
				),
				// phpcs:disable
				// array(
				//  'title'    => __( 'Approve commission', 'affiliate-for-woocommerce' ),
				//  'id'       => 'afwc_approve_commissions',
				//  'default'  => 'instant',
				//  'type'     => 'radio',
				//  'options'  => array(
				//      'instant' => __( 'Immediately after order completes', 'affiliate-for-woocommerce' ),
				//  ),
				//  'autoload' => false,
				// ),
				// phpcs:enable
				array(
					'type' => 'sectionend',
					'id'   => "afwc_{$this->section}_admin_settings",
				),
			);

			return $afwc_general_admin_settings;
		}

	}

}

AFWC_General_Admin_Settings::get_instance();
