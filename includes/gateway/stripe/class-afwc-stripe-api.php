<?php
/**
 * Main class for Stripe API to process payouts
 *
 * @package   affiliate-for-woocommerce/includes/gateway/stripe/
 * @since     8.9.0
 * @version   1.1.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \Stripe\Stripe;

if ( ! class_exists( 'AFWC_Stripe_API' ) ) {

	/**
	 * Stripe API
	 */
	class AFWC_Stripe_API {

		/**
		 * Enabled
		 *
		 * @var bool $enabled
		 */
		public $enabled = false;

		/**
		 * Testmode
		 *
		 * @var string $testmode
		 */
		public $testmode = '';

		/**
		 * The test_publishable_key
		 *
		 * @var string $test_publishable_key
		 */
		public $test_publishable_key = '';

		/**
		 * The test_secret_key
		 *
		 * @var string $test_secret_key
		 */
		public $test_secret_key = '';

		/**
		 * The publishable_key
		 *
		 * @var string $publishable_key
		 */
		public $publishable_key = '';

		/**
		 * The secret_key
		 *
		 * @var string $secret_key
		 */
		public $secret_key = '';

		/**
		 * The client_id
		 *
		 * @var string $client_id
		 */
		public $client_id = '';

		/**
		 * Variable to hold instance of this class
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Stripe_API Singleton object of this class
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
		private function __construct() {
			// Define constant.
			// Ref: https://github.com/stripe/stripe-php/wiki/Migration-guide-for-v13
			// for version 15.4.0!
			if ( ! defined( 'AFWC_STRIPE_API_VERSION' ) ) {
				define( 'AFWC_STRIPE_API_VERSION', '2023-10-16' );
			}

			// To load Stripe Connect.
			$stripe_vendor = AFWC_PLUGIN_DIRPATH . '/vendor/stripe-connect/stripe-php/init.php';
			// class_exists can load Stripe if found in any of the other active plugins.
			if ( file_exists( $stripe_vendor ) && ! class_exists( 'Stripe\Stripe' ) ) {
				require_once $stripe_vendor;
			}

			$this->set_credentials();
			$this->set_stripe_info();

			add_action( 'update_option_woocommerce_stripe_settings', array( $this, 'verify_client_id_on_updated_settings' ), 10, 2 );
		}

		/**
		 * Function to handle WC compatibility related function call from appropriate class
		 *
		 * @param string $function_name Function to call.
		 * @param array  $arguments     Array of arguments passed while calling $function_name.
		 * @return mixed Result of function call.
		 */
		public function __call( $function_name = '', $arguments = array() ) {

			if ( ! is_callable( 'SA_WC_Compatibility', $function_name ) ) {
				return;
			}

			if ( ! empty( $arguments ) ) {
				return call_user_func_array( 'SA_WC_Compatibility::' . $function_name, $arguments );
			} else {
				return call_user_func( 'SA_WC_Compatibility::' . $function_name );
			}

		}

		/**
		 * Method to check whether the Stripe API is enabled for payout.
		 * It detects based on whether the credential setup is complete or not.
		 *
		 * @return bool Return true if enabled otherwise false.
		 */
		public function is_enabled() {
			return (bool) $this->is_set_credentials();
		}

		/**
		 * Set API credentials.
		 *
		 * @param string $gateway The gateway.
		 *
		 * @return void
		 */
		public function set_credentials( $gateway = '' ) {
			if ( empty( $gateway ) ) {
				$gateway = $this->get_payment_method();
			}

			if ( ! is_scalar( $gateway ) || empty( $gateway ) ) {
				return;
			}

			$method      = 'get_' . $gateway . '_credentials';
			$credentials = is_callable( array( $this, $method ) ) ? call_user_func( array( $this, $method ) ) : array();

			if ( empty( $credentials ) || ! is_array( $credentials ) ) {
				return;
			}

			$this->testmode = ( ! empty( $credentials['testmode'] ) && true === $credentials['testmode'] ) ? true : false;

			$this->test_publishable_key = ( ! empty( $credentials['test_publishable_key'] ) ) ? $credentials['test_publishable_key'] : '';
			$this->test_secret_key      = ( ! empty( $credentials['test_secret_key'] ) ) ? $credentials['test_secret_key'] : '';

			$this->publishable_key = ( ! empty( $credentials['publishable_key'] ) ) ? $credentials['publishable_key'] : '';
			$this->secret_key      = ( ! empty( $credentials['secret_key'] ) ) ? $credentials['secret_key'] : '';

			$this->client_id = $this->get_stripe_client_id();
		}

		/**
		 * Method to get commission payout method name.
		 *
		 * @return string
		 */
		public function get_payout_method() {
			/**
			 * Filters the Stripe Payout method.
			 *
			 * @param string  $method The method will be 'woocommerce_stripe' by default.
			 * @param array   $this   The arguments.
			 */
			$method = ( ! empty( $method ) ) ? $method : 'woocommerce_stripe';
			return apply_filters( 'afwc_commission_payout_method', $method, array( 'source' => $this ) );
		}

		/**
		 * Get payment gateway method.
		 *
		 * @return string
		 */
		public function get_payment_method() {
			$method = '';

			if ( afwc_is_plugin_active( 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' ) ) {
				$method = 'woocommerce_stripe';
			}

			/**
			 * Filters the Stripe payment method.
			 *
			 * @param string $method.
			 * @param array
			 */
			return apply_filters( 'afwc_payout_stripe_payment_method', $method, array( 'source' => $this ) );
		}

		/**
		 * Get credentials from WooCommerce Stripe Gateway plugin.
		 * at present they do not have a wrapper method so calling it directly from their option.
		 *
		 * @return array
		 */
		public function get_woocommerce_stripe_credentials() {
			$credentials = array();

			if ( afwc_is_plugin_active( 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' ) ) {
				$settings = get_option( 'woocommerce_stripe_settings', array() );

				if ( ! empty( $settings ) && is_array( $settings ) ) {
					$credentials = array(
						'enabled'              => ( ! empty( $settings['enabled'] ) && 'yes' === $settings['enabled'] ) ? true : false,
						'testmode'             => ( ! empty( $settings['testmode'] ) && 'yes' === $settings['testmode'] ) ? true : false,
						'test_publishable_key' => ( ! empty( $settings['test_publishable_key'] ) ) ? $settings['test_publishable_key'] : '',
						'test_secret_key'      => ( ! empty( $settings['test_secret_key'] ) ) ? $settings['test_secret_key'] : '',
						'publishable_key'      => ( ! empty( $settings['publishable_key'] ) ) ? $settings['publishable_key'] : '',
						'secret_key'           => ( ! empty( $settings['secret_key'] ) ) ? $settings['secret_key'] : '',
					);
				}
			}

			return $credentials;
		}

		/**
		 * Check if the api credentials are available.
		 *
		 * @return bool $is_set
		 */
		public function is_set_credentials() {
			$is_set = false;

			// Check if both live keys are available.
			if ( ! empty( $this->publishable_key ) && ! empty( $this->secret_key ) ) {
				$is_set = true;
			}

			// Check if both test keys are available, only if false.
			if ( false === $is_set && ( ! empty( $this->test_publishable_key ) && ! empty( $this->test_secret_key ) ) ) {
				$is_set = true;
			}

			// check if client_id is available from our settings, only if true.
			$is_set = ( true === $is_set && ( ! empty( $this->client_id ) ) ) ? true : false;

			return $is_set;
		}

		/**
		 * Set correct API key for current configuration.
		 */
		public function set_stripe_info() {
			$api_secret_key = ( true === $this->testmode ) ? $this->test_secret_key : $this->secret_key;
			if ( empty( $api_secret_key ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					_x( 'Stripe secret key is missing, cannot set app info for commission payouts.', 'Logger for stripe payout when secret key is missing', 'affiliate-for-woocommerce' )
				);
				return;
			}

			$plugin_data = Affiliate_For_WooCommerce::get_plugin_data();

			/*
			 * Set information for Stripe App.
			 * appName = plugin name and feature
			 * appVersion = plugin version
			 * appUrl = link to sales copy
			 */
			Stripe::setAppInfo( 'Affiliate for WooCommerce - Stripe payouts via Stripe Connect', $plugin_data['Version'], 'https://woocommerce.com/products/affiliate-for-woocommerce/' );
			Stripe::setApiVersion( AFWC_STRIPE_API_VERSION );
			Stripe::setApiKey( $api_secret_key );
		}

		/**
		 * Method to get stripe live client ID.
		 *
		 * @return string
		 */
		public function get_stripe_client_id() {
			$client_id = get_option( 'afwc_stripe_connect_live_client_id', '' );

			return $client_id;
		}

		/**
		 * Method to save/update stripe live client ID.
		 *
		 * @param string $client_id Client ID.
		 *
		 * @return string
		 */
		public function set_stripe_client_id( $client_id = '' ) {
			if ( empty( $client_id ) ) {
				return false;
			}

			return (bool) update_option( 'afwc_stripe_connect_live_client_id', $client_id, 'no' );
		}

		/**
		 * Delete client ID setting, and return result.
		 *
		 * @return bool result
		 */
		public function delete_stripe_client_id() {
			return (bool) delete_option( 'afwc_stripe_connect_live_client_id' );
		}

		/**
		 * Clear client_id after Stripe settings are updated, if keys not found.
		 *
		 * @param mixed $old_value Option old value.
		 * @param mixed $new_value Current value.
		 */
		public function verify_client_id_on_updated_settings( $old_value, $new_value ) {
			if ( ! is_array( $new_value ) ) {
				return;
			}

			// if any of the key sets not found in updated settings.
			if ( ( empty( $new_value['publishable_key'] ) && empty( $new_value['secret_key'] ) ) || ( empty( $new_value['test_publishable_key'] && $new_value['test_secret_key'] ) ) ) {
				$this->delete_stripe_client_id();
			}
		}

		/**
		 * Process Stripe payout via Stripe connect.
		 *
		 * @param array  $payout_records Array of payout records.
		 * @param string $currency       Currency for payout.
		 *
		 * @return array|WP_Error|null  $response
		 */
		public function process_stripe_payout( $payout_records = array(), $currency = '' ) {

			if ( false === $this->is_set_credentials() ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					_x( 'Missing credentials for Stripe payout.', 'Logger for stripe payout', 'affiliate-for-woocommerce' )
				);
				return;
			}

			if ( empty( $currency ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					_x( 'Currency missing for Stripe payout.', 'Logger for stripe payout', 'affiliate-for-woocommerce' )
				);
				return;
			}

			if ( empty( $payout_records ) || ! is_array( $payout_records ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					_x( 'Payout records are not available for Stripe payout.', 'Logger for stripe payout', 'affiliate-for-woocommerce' )
				);
				return;
			}

			// check if we find affiliate.
			if ( empty( $payout_records['id'] ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					_x( 'Missing affiliate ID for Stripe payout.', 'Logger for stripe payout', 'affiliate-for-woocommerce' )
				);
				return;
			}

			$stripe_receiver_account_id = get_user_meta( $payout_records['id'], 'afwc_stripe_user_id', true );
			if ( empty( $stripe_receiver_account_id ) || empty( $payout_records['destination'] ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					sprintf(
						/* translators: The affiliate ID */
						_x( 'Stripe account ID is missing for the Affiliate ID: %s', 'Logger for Stripe payout when account is missing', 'affiliate-for-woocommerce' ),
						$payout_records['id']
					)
				);
				return;
			}

			if ( $payout_records['destination'] !== $stripe_receiver_account_id ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					sprintf(
						/* translators: The affiliate ID */
						_x( 'Stripe accounts do not match for the Affiliate ID: %d.', 'Logger for Stripe payout when accounts do not match', 'affiliate-for-woocommerce' ),
						$payout_records['id']
					)
				);
				return;
			}

			// Prepare Transfer args... 'amount' 'currency' and 'destination' are required.
			$args = apply_filters(
				'afwc_wc_stripe_connect_transfer_args',
				array(
					'amount'      => ( ( ! empty( $payout_records['amount'] ) ) ? $payout_records['amount'] * 100 : 0 ),
					'currency'    => strtolower( $currency ), // accepted currency is in all lower case.
					'destination' => $payout_records['destination'],
				),
				$payout_records,
				array( 'source' => $this )
			);

			// We use our api_handler controller to create the transfer.
			$stripe_connect = is_callable( array( 'AFWC_Stripe_Connect', 'get_instance' ) ) ? AFWC_Stripe_Connect::get_instance() : null;
			$transfer       = ( ! empty( $stripe_connect ) && is_callable( array( $stripe_connect, 'create_transfer' ) ) ) ? $stripe_connect->create_transfer( $args ) : '';

			// Now we handle the $transfer returns...
			$notes = array();
			$user  = get_userdata( $payout_records['id'] );

			if ( isset( $transfer['error_transfer'] ) ) {
				// Prepare message.
				/* translators: 1: Payout amount 2: Currency 3: User display name */
				$error_message = sprintf( _x( 'Cannot transfer the commissions %1$s for %2$s.', 'error message when transfer fails', 'affiliate-for-woocommerce' ), $payout_records['amount'] . $currency, $user->display_name );

				// Display messages on order note and log file.
				Affiliate_For_WooCommerce::get_instance()->log( 'error', $error_message . _x( ' Stripe Connect message: ', 'error message details from Stripe connect', 'affiliate-for-woocommerce' ) . $transfer['error_transfer'] );

				return new WP_Error( 'error_transfer', $error_message );

			} elseif ( $transfer instanceof \Stripe\Transfer ) {
				// Prepare message.
				/* translators: 1: Payout amount 2: Currency 3: Transfer Id 4: Transfer destination */
				$success_message = sprintf( _x( 'Commissions %1$s for %2$s have been transferred correctly. Transfer ID: "%3$s". Destination Payment: %4$s".', 'success message when transfer is successful', 'affiliate-for-woocommerce' ), $payout_records['amount'] . $currency, $user->display_name, $transfer->id, $transfer->destination_payment );

				// Display messages on the log file.
				Affiliate_For_WooCommerce::get_instance()->log( 'info', $success_message );

				$notes = array(
					'transfer_id'         => $transfer->id,
					'destination_payment' => $transfer->destination_payment,
				);

				$result = array();
				$result = array(
					'ACK'                 => 'Success',
					'transfer_id'         => $transfer->id,
					'destination_payment' => $transfer->destination_payment,
				);

				// Calculate total amount if 'amount' is missing in the result.
				// amount addition will be here in amount received.
				if ( ! isset( $transfer['amount'] ) ) {
					$amounts          = ! empty( $payout_records ) && is_array( $payout_records ) ? array_column( $payout_records, 'amount' ) : array();
					$result['amount'] = ! empty( $amounts ) && is_array( $amounts ) ? floatval( array_sum( $amounts ) ) : 0.00;
				} else {
					// transfer amount will be in cents always.
					// convert it back to original amount to send response back.
					$result['amount'] = $transfer['amount'] / 100;
				}

				// try to set transfer metadata.
				$destination_payment = ! empty( $transfer->destination_payment ) ? $transfer->destination_payment : false;

				if ( $destination_payment ) {
					( ! empty( $stripe_connect_api ) && is_callable( array( $stripe_connect_api, 'update_charge' ) ) ) ? $stripe_connect_api->update_charge(
						$destination_payment,
						array(
							'metadata' => apply_filters(
								'afwc_wc_stripe_connect_metadata',
								array(
									'instance' => preg_replace( '/http(s)?:\/\//', '', site_url() ),
									'order_id' => '',
								),
								'update_destination_payment'
							),
						),
						array(
							'stripe_account' => $stripe_receiver_account_id,
						)
					) : '';
				}

				return $result;
			}

		}

		/**
		 * Get affiliate's Stripe User ID - if available.
		 *
		 * @param int $affiliate_id The Affiliate ID.
		 * @return string
		 */
		public function get_payout_meta( $affiliate_id = 0 ) {
			if ( empty( $affiliate_id ) ) {
				return '';
			}

			$stripe_user_id = get_user_meta( $affiliate_id, 'afwc_stripe_user_id', true );

			return $stripe_user_id;
		}

	}

}

AFWC_Stripe_API::get_instance();
