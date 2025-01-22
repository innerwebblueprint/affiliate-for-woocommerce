<?php
/**
 * Main class for Affiliates Admin Settings
 *
 * @package     affiliate-for-woocommerce/includes/admin/
 * @since       1.0.0
 * @version     1.6.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Admin_Settings' ) ) {

	/**
	 * Main class for Affiliate Admin Settings
	 */
	class AFWC_Admin_Settings {

		/**
		 * Affiliate For WooCommerce settings tab name
		 *
		 * @var string
		 */
		public $tab_slug = 'affiliate-for-woocommerce-settings';

		/**
		 * Variable to hold instance of AFWC_Admin_Settings
		 *
		 * @var self $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Admin_Settings Singleton object of this class
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
			add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
			add_action( 'admin_enqueue_scripts', array( $this, 'add_settings_style_and_scripts' ) );
			add_action( 'woocommerce_sections_' . $this->tab_slug, array( $this, 'display_settings_sections' ) );
			add_action( 'woocommerce_settings_' . $this->tab_slug, array( $this, 'display_settings_tab' ) );
			add_action( 'woocommerce_update_options_' . $this->tab_slug, array( $this, 'save_admin_settings' ) );
			add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'sanitize_options' ), 10, 2 );
		}

		/**
		 * Function to add setting tab for Affiliate For WooCommerce
		 *
		 * @param array $settings_tabs Existing tabs.
		 * @return array $settings_tabs New settings tabs.
		 */
		public function add_settings_tab( $settings_tabs = array() ) {

			$settings_tabs[ $this->tab_slug ] = _x( 'Affiliate', 'affiliate setting tab name', 'affiliate-for-woocommerce' );

			return $settings_tabs;
		}

		/**
		 * Function to register required scripts for plugin setting page
		 *
		 * @param string $admin_page The current admin page.
		 *
		 * @return void
		 */
		public function add_settings_style_and_scripts( $admin_page = '' ) {
			if ( empty( $admin_page ) || 'woocommerce_page_wc-settings' !== $admin_page || empty( $_GET ) || empty( $_GET['tab'] ) || $this->tab_slug !== $_GET['tab'] ) { // phpcs:ignore
				return;
			}

			$plugin_data = Affiliate_For_WooCommerce::get_plugin_data();
			wp_enqueue_script( 'afwc-setting-js', AFWC_PLUGIN_URL . '/assets/js/afwc-settings.js', array( 'jquery', 'wp-i18n' ), $plugin_data['Version'], true );
			wp_enqueue_style( 'afwc-setting-css', AFWC_PLUGIN_URL . '/assets/css/admin/afwc-admin-settings.css', array(), $plugin_data['Version'] );

			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'afwc-setting-js', 'affiliate-for-woocommerce' );
			}

			wp_localize_script(
				'afwc-setting-js',
				'afwcSettingParams',
				array(
					'oldPname' => afwc_get_pname(),
					'ajaxURL'  => admin_url( 'admin-ajax.php' ),
					'security' => array(
						'searchExcludeLTC' => wp_create_nonce( 'afwc-search-exclude-ltc-list' ),
						'searchIncludeAP'  => wp_create_nonce( 'afwc-search-include-ap-list' ),
					),
				)
			);

			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			if ( ! wp_script_is( 'select2' ) ) {
				wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full' . $suffix . '.js', array( 'jquery' ), WC_VERSION, true );
			}
			if ( ! wp_style_is( 'select2' ) ) {
				wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC_VERSION );
			}

			// We need thickbox on Registration form setting tab/section for now.
			if ( ! empty( $_GET['section'] ) && 'registration-form' === $_GET['section'] ) { // phpcs:ignore
				add_thickbox();
			}
		}

		/**
		 * Function to add sections for Affiliate For WooCommerce setting tab
		 *
		 * @return void print sections
		 */
		public function display_settings_sections() {
			global $current_section;

			$sections = $this->get_settings_sections();

			if ( ! empty( $sections ) && is_array( $sections ) ) {
				echo '<ul class="subsubsub">';
				$array_keys = array_keys( $sections );
				$last_key   = end( $array_keys );
				foreach ( $sections as $id => $label ) {
					$url       = admin_url( 'admin.php?page=wc-settings&tab=' . $this->tab_slug . '&section=' . sanitize_title( $id ) );
					$class     = $current_section === $id || ( 'general' === $current_section && '' === $id ) ? 'current' : '';
					$separator = $last_key === $id ? '' : '|';
					echo "<li><a href='" . esc_url( $url ) . "' class='" . esc_attr( $class ) . "'>" . esc_html( $label ) . '</a>' . esc_html( $separator ) . '</li>';
				}
				echo '</ul>';
			}

			echo '<ul class="subsubsub documentation">' .
				sprintf(
					/* translators: 1: Affiliate tag page url 2: Title of manage affiliate page link */
					'<li><a href="%1$s" target="_blank"><u>%2$s</u></a>|</li>',
					esc_url( admin_url( 'edit-tags.php?taxonomy=afwc_user_tags' ) ),
					esc_html_x( 'Manage tags', 'Affiliate tag create/edit page link text', 'affiliate-for-woocommerce' )
				) .
				sprintf(
					/* translators: 1: Documentation url 2: Documentation url title */
					'<li><a href="%1$s" target="_blank"><u>%2$s</u></a></li>',
					esc_url( AFWC_DOC_DOMAIN ),
					esc_html_x( 'Documentation', 'Plugin documentation link text at setting page', 'affiliate-for-woocommerce' )
				) .
				'</ul><br class="clear" />';
		}

		/**
		 * Get setting sections
		 *
		 * @return array
		 */
		public function get_settings_sections() {
			return array(
				''                  => _x( 'General', 'general setting section title', 'affiliate-for-woocommerce' ),
				'registration-form' => _x( 'Registration Form', 'registration form setting section title', 'affiliate-for-woocommerce' ),
				'referrals'         => _x( 'Referrals', 'referrals setting section title', 'affiliate-for-woocommerce' ),
				'commissions'       => _x( 'Commissions', 'commissions setting section title', 'affiliate-for-woocommerce' ),
				'payouts'           => _x( 'Payouts', 'payouts setting section title', 'affiliate-for-woocommerce' ),
				'payout_invoice'    => _x( 'Payout Invoice', 'payout invoice setting section title', 'affiliate-for-woocommerce' ),
				'affiliate-account' => _x( 'Affiliate\'s Account', 'affiliate account setting section title', 'affiliate-for-woocommerce' ),
			);
		}

		/**
		 * Function to display Affiliate For WooCommerce settings' tab
		 */
		public function display_settings_tab() {

			$afwc_admin_settings = $this->get_settings();
			if ( ! is_array( $afwc_admin_settings ) || empty( $afwc_admin_settings ) ) {
				return;
			}

			woocommerce_admin_fields( $afwc_admin_settings );
			wp_nonce_field( 'afwc_admin_settings_security', 'afwc_admin_settings_security', false );
		}

		/**
		 * Function to get Affiliate For WooCommerce admin settings
		 *
		 * @return array $afwc_admin_settings Affiliate For WooCommerce admin settings.
		 */
		public function get_settings() {
			global $current_section;

			$active_section      = ! empty( $current_section ) ? $current_section : 'general';
			$afwc_admin_settings = apply_filters( "afwc_{$active_section}_section_admin_settings", array() );

			return apply_filters( 'afwc_admin_settings', $afwc_admin_settings, array( 'source' => $this ) );
		}

		/**
		 * Function for saving settings for Affiliate For WooCommerce
		 */
		public function save_admin_settings() {
			if ( ! isset( $_POST['afwc_admin_settings_security'] ) || ! wp_verify_nonce( wc_clean( wp_unslash( $_POST['afwc_admin_settings_security'] ) ), 'afwc_admin_settings_security' )  ) { // phpcs:ignore
				return;
			}

			global $current_section;

			$afwc_admin_settings = $this->get_settings();
			if ( ! is_array( $afwc_admin_settings ) || empty( $afwc_admin_settings ) ) {
				return;
			}

			woocommerce_update_options( $afwc_admin_settings );

			// Fire hook once section settings are updated.
			do_action( "afwc_admin_{$current_section}_settings_updated", $afwc_admin_settings, array( 'source' => $this ) );
		}

		/**
		 * Method to sanitize the options .
		 *
		 * @param mixed $value The value.
		 * @param array $option The option.
		 *
		 * @return mixed the sanitized value.
		 */
		public function sanitize_options( $value = '', $option = array() ) {

			$id = ( ! empty( $option ) && ! empty( $option['id'] ) ) ? $option['id'] : '';

			// Enable to flush the rewrite rules if tracking param name and pretty URL option is updated.
			if ( ! empty( $id ) && in_array( $id, array( 'afwc_pname', 'afwc_use_pretty_referral_links' ), true ) && true === $this->is_updated( $id, $value ) ) {
				update_option( 'afwc_flushed_rules', 1, 'no' );
			}

			return $value;
		}

		/**
		 * Method to check if option is updated.
		 *
		 * @param string $option The option name.
		 * @param mixed  $value The value.
		 *
		 * @return bool Return true if new value is updated otherwise false.
		 */
		public function is_updated( $option = '', $value = '' ) {
			return ! empty( $option ) && ( get_option( $option ) !== $value );
		}

		/**
		 * Method to convert the HTML attribute string from an array.
		 *
		 * @param array $value The array of values.
		 *
		 * @return string Return the attributes.
		 */
		public static function get_html_attributes_string( $value = array() ) {

			if ( empty( $value ) || ! is_array( $value ) || empty( $value['custom_attributes'] ) || ! is_array( $value['custom_attributes'] ) ) {
				return '';
			}

			$result = array();

			foreach ( $value['custom_attributes'] as $key => $attr_value ) {
				$result[] = esc_attr( $key ) . '="' . esc_attr( $attr_value ) . '"';
			}

			return implode( ' ', $result );
		}
	}
}

AFWC_Admin_Settings::get_instance();
