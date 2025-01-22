<?php
/**
 * Main class for Payout Invoice
 *
 * @package     affiliate-for-woocommerce/includes/common/
 * @since       7.19.0
 * @version     1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Payout_Invoice' ) ) {

	/**
	 * Class to handle Payout invoice
	 */
	class AFWC_Payout_Invoice {

		/**
		 * Instance of AFWC_Payout_Invoice
		 *
		 * @var self $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of AFWC_Payout_Invoice
		 *
		 * @return AFWC_Payout_Invoice Singleton object of AFWC_Payout_Invoice
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private constructor to prevent direct instantiation.
		 */
		private function __construct() {
			if ( self::is_enabled_for_affiliate() ) {
				add_filter( 'afwc_my_account_get_payouts_report_header', array( $this, 'add_invoice_header_in_payout_table' ), 1 );
			}
		}

		/**
		 * Method to check whether the payout invoice is enabled globally.
		 *
		 * @return bool Return true if enabled, otherwise false.
		 */
		public static function is_enabled() {
			return 'yes' === get_option( 'afwc_enable_payout_invoice', 'no' );
		}

		/**
		 * Method to check whether the payout invoice is enabled for affiliate.
		 *
		 * @return bool Return true if enabled, otherwise false.
		 */
		public static function is_enabled_for_affiliate() {
			if ( ! self::is_enabled() ) {
				return false;
			}

			return 'yes' === get_option( 'afwc_enable_payout_invoice_for_affiliate', 'no' );
		}

		/**
		 * Method to render the payout invoice.
		 *
		 * @param array $args The arguments.
		 *
		 * @return void.
		 */
		public function render_payout_invoice( $args = array() ) {
			global $affiliate_for_woocommerce;

			$template = 'invoice/afwc-payout-invoice.php';

			// Default path of payout invoice template.
			$default_path = AFWC_PLUGIN_DIRPATH . '/templates/';

			wc_get_template(
				$template,
				$this->get_invoice_data( $args ),
				is_callable( array( $affiliate_for_woocommerce, 'get_template_base_dir' ) ) ? $affiliate_for_woocommerce->get_template_base_dir( $template ) : '',
				$default_path
			);
		}

		/**
		 * Method to get the data for invoice.
		 *
		 * @param array $args The arguments.
		 *
		 * @return array Return the array of data.
		 */
		public function get_invoice_data( $args = array() ) {

			if ( empty( $args ) || ! is_array( $args ) || empty( $args['affiliate_id'] ) ) {
				return array();
			}

			$affiliate_id         = intval( $args['affiliate_id'] );
			$store_country        = new WC_Countries();
			$payout_logo_media_id = get_option( 'afwc_payout_invoice_logo' );

			if ( ! empty( $args['method'] ) ) {
				// Sanitize the payout method.
				$args['method'] = afwc_get_payout_methods( $args['method'] );
			}

			$store_address = array(
				'address_1' => is_callable( array( $store_country, 'get_base_address' ) ) ? $store_country->get_base_address() : '',
				'address_2' => is_callable( array( $store_country, 'get_base_address_2' ) ) ? $store_country->get_base_address_2() : '',
				'city'      => is_callable( array( $store_country, 'get_base_city' ) ) ? $store_country->get_base_city() : '',
				'state'     => is_callable( array( $store_country, 'get_base_state' ) ) ? $store_country->get_base_state() : '',
				'postcode'  => is_callable( array( $store_country, 'get_base_postcode' ) ) ? $store_country->get_base_postcode() : '',
				'country'   => is_callable( array( $store_country, 'get_base_country' ) ) ? $store_country->get_base_country() : '',
			);

			/**
			 * WordPress filter for invoice data.
			 *
			 * @param array The array of invoice data
			 * @param array The array of extra data including the current class instance.
			 */
			return apply_filters(
				'afwc_get_payout_invoice_data',
				array_merge(
					array(
						'affiliate_address' => wc_get_account_formatted_address( 'billing', $affiliate_id ),
						'store_address'     => is_callable( array( $store_country, 'get_formatted_address' ) ) ? $store_country->get_formatted_address( $store_address ) : '',
						'logo_url'          => ! empty( $payout_logo_media_id ) ? wp_get_attachment_url( $payout_logo_media_id ) : '',
						'store_name'        => get_bloginfo( 'name' ),
						'date_format'       => get_option( 'date_format' ),
					),
					$args
				),
				array( 'source' => $this )
			);
		}

		/**
		 * Callback method to add the invoice header in payout table.
		 * Currently, It works for my account side as the filter does not support in admin dashboard.
		 *
		 * @param array $headers The payout table headers.
		 *
		 * @return array Return the modified headers.
		 */
		public function add_invoice_header_in_payout_table( $headers = array() ) {
			if ( ! is_array( $headers ) ) {
				return $headers;
			}

			$headers['invoice'] = _x( 'Invoice', 'Payouts table header title for invoice column', 'affiliate-for-woocommerce' );
			return $headers;
		}
	}
}

AFWC_Payout_Invoice::get_instance();
