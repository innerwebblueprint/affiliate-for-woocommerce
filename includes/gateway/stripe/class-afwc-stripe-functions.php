<?php
/**
 * Main class for Stripe functions and settings.
 *
 * @package   affiliate-for-woocommerce/includes/gateway/stripe/
 * @since     8.9.0
 * @version   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Stripe_Functions' ) ) {

	/**
	 * Main class for Affiliate Stripe Functions
	 */
	class AFWC_Stripe_Functions {

		/**
		 * Variable to hold instance of this class
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Stripe_Functions Singleton object of this class
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
			// add new settings.
			add_filter( 'afwc_payouts_section_admin_settings', array( $this, 'add_settings' ) );
			// Ajax calls.
			add_action( 'wp_ajax_disconnect_stripe_connect', array( $this, 'disconnect_account' ) );
		}

		/**
		 * Function to handle WC compatibility related function call from appropriate class
		 *
		 * @param string $function_name Function to call.
		 * @param array  $arguments     Array of arguments passed while calling $function_name.
		 *
		 * @return mixed Result of function call.
		 */
		public function __call( $function_name = '', $arguments = array() ) {

			if ( empty( $function_name ) || ! is_callable( 'SA_WC_Compatibility', $function_name ) ) {
				return;
			}

			if ( ! empty( $arguments ) ) {
				return call_user_func_array( 'SA_WC_Compatibility::' . $function_name, $arguments );
			} else {
				return call_user_func( 'SA_WC_Compatibility::' . $function_name );
			}
		}

		/**
		 * Function to add Stripe specific settings
		 *
		 * @param  array $settings Existing settings.
		 * @return array $settings
		 */
		public function add_settings( $settings = array() ) {
			$redirect_uri = afwc_myaccount_dashboard_url() . '?afwc-tab=resources';

			$get_stripe_connect_details = 'https://dashboard.stripe.com/settings/connect/onboarding-options/oauth';

			$stripe_settings = array(
				// Stripe settings
				// 1. allow enabling + same will show connect on frontend.
				array(
					'name'     => _x( 'Payout via Stripe', 'setting name', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Pay commissions via Stripe by allowing affiliates to link their Stripe accounts to your store', 'setting description', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_enable_stripe_payout',
					'type'     => 'checkbox',
					'default'  => 'no',
					'autoload' => false,
					'desc_tip' => _x( 'Disabling this will stop payouts through Stripe.', 'setting description tip', 'affiliate-for-woocommerce' ),
				),
				// 2. to accept client_id.
				array(
					'name'              => _x( 'Stripe client ID', 'setting name', 'affiliate-for-woocommerce' ),
					'desc'              => sprintf(
						/* translators: 1: Link to Stripe dashboard 2: Stripe Connect documentation for testing with OAuth  */
						_x( 'To locate, go to <a href="%1$s" target="_blank">Stripe Dashboard > Settings > Connect > Onboarding options > OAuths ></a> <strong>Live mode client ID</strong>. More info: <a href="%2$s" target="_blank">%2$s</a>', 'setting description', 'affiliate-for-woocommerce' ),
						$get_stripe_connect_details,
						'https://docs.stripe.com/connect/testing#using-oauth'
					),
					'id'                => 'afwc_stripe_connect_live_client_id',
					'type'              => 'text',
					'placeholder'       => _x( 'Account ID - minimum 35 characters', 'Placeholder for Affiliate manager email setting', 'affiliate-for-woocommerce' ),
					'autoload'          => false,
					'desc_tip'          => false,
					'custom_attributes' => array(
						'data-afwc-hide-if' => 'afwc_enable_stripe_payout',
					),
				),
				// 3. to set redirect URI.
				array(
					'name'              => _x( 'Add redirect URIs', 'setting name', 'affiliate-for-woocommerce' ),
					'desc'              => sprintf(
						/* translators: 1: Link to Stripe dashboard 2: current site's affiliate's my account endpoint for resources/profile tab  */
						_x( 'A <strong>Redirection URI is required</strong> when users connect their account to your site.<br><br>Go to <a href="%1$s" target="_blank">Stripe Dashboard > Settings > Connect > Onboarding options > OAuths ></a> <strong>Redirects</strong> section and add the following URl to redirect: <code>%2$s</code><br><br>Redirects URI can be defined on test and live mode, we would recommend to test both scenarios.', 'setting description', 'affiliate-for-woocommerce' ),
						$get_stripe_connect_details,
						$redirect_uri
					),
					'id'                => 'afwc_stripe_add_redirect_uris',
					'type'              => 'checkbox',
					'default'           => 'no',
					'autoload'          => false,
					'desc_tip'          => _x( 'It is mandatory to set this in your Stripe account to process commission payouts. Otherwise, payouts won\'t be processed.', 'setting description tip', 'affiliate-for-woocommerce' ),
					// We do not want to allow selecting checkbox of this setting. So hide it for now.
					'custom_attributes' => array(
						'data-afwc-hide-if' => 'afwc_enable_stripe_payout',
						'style'             => 'display: none;',
					),
				),
			);

			array_splice( $settings, ( count( $settings ) - 1 ), 0, $stripe_settings );

			return $settings;
		}

		/**
		 * Get stripe connection status for the affiliate user.
		 *
		 * @param int $user_id User id.
		 * @return string
		 */
		public function afwc_get_stripe_user_status( $user_id = 0 ) {
			$connection_status = 'disconnect';
			if ( empty( $user_id ) ) {
				return $connection_status;
			}

			$stripe_user_id    = get_user_meta( $user_id, 'afwc_stripe_user_id', true );
			$connection_status = ( ! empty( $stripe_user_id ) ) ? 'connect' : $connection_status;

			return $connection_status;
		}

		/**
		 * Method to connect stripe by user ID.
		 *
		 * @param int    $user_id User ID.
		 * @param string $code Code for authentication.
		 *
		 * @return bool|void
		 */
		public function connect_by_user_id_and_access_code( $user_id = 0, $code = '' ) {
			if ( empty( $user_id ) ) {
				return;
			}

			$current_status = $this->afwc_get_stripe_user_status( $user_id );

			$stripe_connect_api = is_callable( array( 'AFWC_Stripe_Connect', 'get_instance' ) ) ? AFWC_Stripe_Connect::get_instance() : null;
			$stripe_token       = ( 'disconnect' === $current_status ) ? ( ( ! empty( $stripe_connect_api ) && is_callable( array( $stripe_connect_api, 'get_oauth_token' ) ) ) ? $stripe_connect_api->get_oauth_token( $code ) : false ) : false;

			if ( $stripe_token ) {
				$stripe_serialized = $stripe_token->jsonSerialize();
				update_user_meta( $user_id, 'afwc_stripe_user_id', $stripe_serialized['stripe_user_id'] );
				update_user_meta( $user_id, 'afwc_stripe_access_token', $stripe_serialized['access_token'] );
				$receiver = array(
					'user_id'         => $user_id,
					'status_receiver' => 'connect',
					'stripe_id'       => $stripe_serialized['stripe_user_id'],
				);
				update_user_meta( $user_id, 'afwc_payout_method', 'stripe' );

				do_action( 'afwc_after_connect_with_stripe', $user_id, $code, $stripe_serialized );

				return true;
			}

			return false;
		}

		/**
		 * Method disconnect Stripe connect account.
		 *
		 * @param int $user_id User ID.
		 *
		 * @return array|void array with status and message or void if user is empty.
		 */
		public function disconnect_by_user_id( $user_id = 0 ) {
			if ( empty( $user_id ) ) {
				return;
			}

			$stripe_user_id               = get_user_meta( $user_id, 'afwc_stripe_user_id', true );
			$is_account_disconnected      = false;
			$is_account_deleted_from_site = false;

			$result = array(
				'disconnected' => false,
				'message'      => '',
			);

			if ( ! empty( $stripe_user_id ) ) {
				$stripe_connect_api = is_callable( array( 'AFWC_Stripe_Connect', 'get_instance' ) ) ? AFWC_Stripe_Connect::get_instance() : null;
				$stripe_object      = ( ! empty( $stripe_connect_api ) && is_callable( array( $stripe_connect_api, 'deauthorize_account' ) ) ) ? $stripe_connect_api->deauthorize_account( $stripe_user_id ) : null;

				if ( ! is_null( $stripe_object ) && ( $stripe_object instanceof Stripe\StripeObject || $stripe_object instanceof \Stripe\Exception\OAuth\InvalidClientException ) ) {
					$is_account_disconnected = true;
				} else {
					$result['message'] = _x( 'A problem occurred while trying to disconnect from the Stripe Connect.', 'Error message when trying to disconnect account from Stripe Connect', 'affiliate-for-woocommerce' );
				}
			}

			if ( $is_account_disconnected ) {
				$is_account_deleted_from_site = delete_user_meta( $user_id, 'afwc_stripe_user_id' );
				$account_deleted_access_token = delete_user_meta( $user_id, 'afwc_stripe_access_token' );
				$receiver                     = array(
					'user_id'         => $user_id,
					'status_receiver' => 'disconnect',
					'stripe_id'       => '',
				);

				if ( ! $is_account_deleted_from_site && ! $account_deleted_access_token ) {
					$result['message'] = _x( 'A problem occurred while trying to disconnect.', 'Error message when trying to disconnect from page', 'affiliate-for-woocommerce' );
				}
			}

			if ( $is_account_deleted_from_site && $is_account_disconnected ) {
				$result['disconnected'] = true;
				$result['message']      = _x( 'Your account has been disconnected', 'Success message when Stripe account connect is successful', 'affiliate-for-woocommerce' );
			}

			do_action( 'afwc_after_disconnect_with_stripe', $user_id, $stripe_user_id, $result );

			return $result;
		}

		/**
		 * Disconnect user from My Account page
		 *
		 * @return void
		 */
		public function disconnect_account() {
			$user_id = get_current_user_id();

			$result = $this->disconnect_by_user_id( $user_id );

			wp_send_json( $result );
		}

	}

}

AFWC_Stripe_Functions::get_instance();
