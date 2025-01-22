<?php
/**
 * Class to handle reerrals settings
 *
 * @package     affiliate-for-woocommerce/includes/admin/settings/
 * @since       7.18.0
 * @version     1.0.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Referrals_Admin_Settings' ) ) {

	/**
	 * Main class for referrals settings
	 */
	class AFWC_Referrals_Admin_Settings {

		/**
		 * Variable to hold instance of AFWC_Referrals_Admin_Settings
		 *
		 * @var self $instance
		 */
		private static $instance = null;

		/**
		 * Section name
		 *
		 * @var string $section
		 */
		private $section = 'referrals';

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Referrals_Admin_Settings Singleton object of this class
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
		 * Method to get referrals settings
		 *
		 * @return array
		 */
		public function get_section_settings() {
			$pname                               = afwc_get_pname();
			$default_affiliate_link              = trailingslashit( home_url() ) . '?' . $pname . '={user_id}';
			$pretty_affiliate_link               = trailingslashit( home_url() ) . $pname . '/{user_id}';
			$affiliate_link                      = trailingslashit( home_url() ) . ( ( 'yes' === get_option( 'afwc_use_pretty_referral_links', 'no' ) ) ? '<span id="afwc_pname_span">' . $pname . '</span>/{user_id}' : '?<span id="afwc_pname_span">' . $pname . '</span>={user_id}' );
			$referral_in_admin_email_description = apply_filters( 'afwc_add_referral_in_admin_emails_setting_description', _x( 'Include affiliate referral details in the WooCommerce New order email (if enabled)', 'Admin setting description', 'affiliate-for-woocommerce' ), array( 'source' => $this ) );

			$afwc_referrals_admin_settings = array(
				array(
					'title' => _x( 'Referrals', 'Referrals setting section title', 'affiliate-for-woocommerce' ),
					'type'  => 'title',
					'id'    => 'afwc_referrals_admin_settings',
				),
				array(
					'name'        => _x( 'Tracking param name', 'setting name for tracking parameter name', 'affiliate-for-woocommerce' ),
					'desc'        => $affiliate_link,
					'id'          => 'afwc_pname',
					'type'        => 'text',
					'placeholder' => _x( 'Leaving this blank will use default value ref', 'Tracking parameter setting placeholder text', 'affiliate-for-woocommerce' ),
					'autoload'    => false,
				),
				array(
					'name'     => _x( 'Personalize affiliate identifier', 'setting name to personalize affiliate identifier', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Allow affiliates to use something other than {user_id} as referral identifier', 'setting description for personalizing affiliate identifier', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_allow_custom_affiliate_identifier',
					'type'     => 'checkbox',
					'default'  => 'yes',
					'desc_tip' => _x( 'Good idea to keep this on. This allows "friendly" looking links - because people can use their brand name instead of {user_id}.', 'setting description tip for personalizing affiliate identifier', 'affiliate-for-woocommerce' ),
					'autoload' => false,
				),
				array(
					'name'     => _x( 'Pretty affiliate links', 'setting name for pretty affiliate links', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Automatically convert default affiliate referral links to beautiful links', 'setting description for pretty affiliate links', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_use_pretty_referral_links',
					'type'     => 'checkbox',
					'default'  => 'no',
					/* translators: %1$s: Pretty affiliate link %2$s: Default affiliate link */
					'desc_tip' => sprintf( _x( 'When enabled, the affiliate links will look like <strong>%1$s</strong> instead of %2$s.', 'settinng description tip for pretty affiliate links', 'affiliate-for-woocommerce' ), $pretty_affiliate_link, $default_affiliate_link ),
					'autoload' => false,
				),
				array(
					'name'     => _x( 'Coupons for referral', 'setting name for coupons for referral', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Use coupons for referral - along with affiliated links', 'setting description of using coupons for referral', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_use_referral_coupons',
					'type'     => 'checkbox',
					'default'  => 'yes',
					'desc_tip' => _x( 'Use the <code>Assign to affiliate</code> option while creating a coupon to link the coupon with an affiliate. Whenever that coupon is used, specified affiliate will be credited for the sale.', 'setting description tip of using coupons for referral', 'affiliate-for-woocommerce' ),
					'autoload' => false,
				),
				array(
					'name'     => _x( 'Multi-tier affiliate program', 'setting name for multi-tier affiliate program', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Allow existing affiliates to invite others to join your affiliate program, earning extra commissions from their referrals', 'Description for multi-tier affiliate program', 'affiliate-for-woocommerce' ),
					'desc_tip' => _x( 'Existing (parent) affiliates will receive commissions on referrals of children affiliates - as long as active. Disabling this will remove the relationship as well as prevent commissions.', 'Description tip for multi-tier affiliate program', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_enable_multi_tier',
					'type'     => 'checkbox',
					'default'  => 'yes',
					'autoload' => true, // The autoload is enabled as this option is using in every corner of the plugin.
				),
				array(
					'name'    => _x( 'Credit first/last affiliate', 'setting name to credit first or last affiliate during referral', 'affiliate-for-woocommerce' ),
					'id'      => 'afwc_credit_affiliate',
					'type'    => 'radio',
					'options' => array(
						'first' => _x( 'First - Credit the first affiliate who referred the customer.', 'Option to credit first affiliate', 'affiliate-for-woocommerce' ),
						'last'  => _x( 'Last - Credit the last/latest affiliate who referred the customer.', 'Option to credit last affiliate', 'affiliate-for-woocommerce' ),
					),
					'default' => 'last',
				),
				array(
					'name'     => _x( 'Affiliate self-refer', 'setting name for affiliate self-refer', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Allow affiliates to earn commissions on their own orders', 'Description for affiliate self-refer setting', 'affiliate-for-woocommerce' ),
					'desc_tip' => _x( 'Disabling this will not record a commission if an affiliate uses their own referral link/coupons during orders.', 'Description tip for affiliate self-refer setting', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_allow_self_refer',
					'type'     => 'checkbox',
					'default'  => 'yes',
					'autoload' => false,
				),
				array(
					'name'     => _x( 'Show affiliate referral link for a product', 'setting name to show affiliate referral link for a product', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Allow affiliates to quickly copy their affiliate referral link for a specific product from a single product page', 'setting description of showing affiliate referral link for a product', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_show_product_referral_url',
					'type'     => 'checkbox',
					'default'  => 'no',
					'desc_tip' => _x( 'When enabled, a "Click to copy referral link" button will appear for active affiliates on all products except the ones listed under "Excluded products" and assigned to the affiliate via a "Landing page".', 'setting description tip for showing affiliate referral link for a product', 'affiliate-for-woocommerce' ),
					'autoload' => false,
				),
				array(
					'name'     => _x( 'Send referral details to admin', 'setting name to send referral details to admin', 'affiliate-for-woocommerce' ),
					'desc'     => $referral_in_admin_email_description,
					'desc_tip' => _x( 'Disabling this will not include affiliate referral details in the email to admin.', 'setting description tip for sending referral details to admin', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_add_referral_in_admin_emails',
					'type'     => 'checkbox',
					'default'  => 'no',
					'autoload' => false,
				),
				array(
					'type' => 'sectionend',
					'id'   => "afwc_{$this->section}_admin_settings",
				),
			);

			return $afwc_referrals_admin_settings;
		}

	}

}

AFWC_Referrals_Admin_Settings::get_instance();
