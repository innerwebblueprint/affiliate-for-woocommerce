<?php
/**
 * Class for Stripe Connect API
 *
 * @package   affiliate-for-woocommerce/includes/gateway/stripe/
 * @since     8.9.0
 * @version   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \Stripe\Stripe;
use \Stripe\Charge;
use \Stripe\Account;
use \Stripe\OAuth;
use \Stripe\Customer;
use Stripe\StripeObject;
use \Stripe\PaymentIntent;
use \Stripe\PaymentMethod;
use \Stripe\SetupIntent;
use \Stripe\Source;

if ( ! class_exists( 'AFWC_Stripe_Connect' ) ) {

	/**
	 * Main class for Stripe Connect
	 */
	class AFWC_Stripe_Connect {

		/**
		 * To hold stripe client id from the settings.
		 *
		 * @var string
		 */
		public $afwc_stripe_client_id = '';

		/**
		 * Variable to hold instance of this class
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Stripe_Connect Singleton object of this class
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
			$stripe_api                  = is_callable( array( 'AFWC_Stripe_API', 'get_instance' ) ) ? AFWC_Stripe_API::get_instance() : null;
			$this->afwc_stripe_client_id = ( ! empty( $stripe_api ) && is_callable( array( $stripe_api, 'get_stripe_client_id' ) ) ) ? $stripe_api->get_stripe_client_id() : '';
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

		/* === ACCOUNT RELATED API === */

		/**
		 * Creates a connected account on Stripe
		 *
		 * @param array $args Array of parameters to use for account creation.
		 *
		 * @return StripeObject|bool Created account or false on failure
		 */
		public function create_account( $args = array() ) {
			try {
				$acct = Account::create( $args );
			} catch ( Exception $e ) {
				return false;
			}

			return $acct;
		}

		/**
		 * Retrieves a connected account by ID
		 *
		 * @param string $id Account id.
		 *
		 * @return StripeObject|bool Retrieved account or false on failure
		 */
		public function retrieve_account( $id ) {
			try {
				$acct = Account::retrieve( $id );

				return $acct;
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * Authorizes an account for the application
		 *
		 * @param string $stripe_user_email Email used to register account on Stripe.
		 *
		 * @return StripeObject|bool Connected account or false on failure
		 */
		public function authorize_account( $stripe_user_email = '' ) {
			try {
				$client_id       = $this->afwc_stripe_client_id;
				$user_authorized = OAuth::authorizeUrl(
					array(
						'client_id'   => $client_id,
						'stripe_user' => $stripe_user_email,
					)
				);

				/* translators: 1: Client ID 2: Stripe Email */
				Affiliate_For_WooCommerce::get_instance()->log( 'info', sprintf( _x( 'Authorize Account: Account with client_id: %1$s and stripe_user_email: %2$s authorized', 'stripe authorize account success message', 'affiliate-for-woocommerce' ), $client_id, $stripe_user_email ) );

				return $user_authorized;
			} catch ( Exception $e ) {
				/* translators: %s: Error message */
				Affiliate_For_WooCommerce::get_instance()->log( 'error', sprintf( _x( 'Authorize Account: Could not be authorize account: %s', 'stripe authorize account error message', 'affiliate-for-woocommerce' ), $e->getMessage() ) );

				return false;
			}
		}

		/**
		 * Deauthorize the account
		 *
		 * @param string $stripe_user_id Id of the user to deauthorize.
		 *
		 * @return StripeObject|bool Stripe customer or false on failure
		 */
		public function deauthorize_account( $stripe_user_id = '' ) {
			try {
				$client_id         = $this->afwc_stripe_client_id;
				$user_deauthorized = OAuth::deauthorize(
					array(
						'client_id'      => $client_id,
						'stripe_user_id' => $stripe_user_id,
					),
					array()
				);

				/* translators: %s: Client ID */
				Affiliate_For_WooCommerce::get_instance()->log( 'info', sprintf( _x( 'Deauthorize Account: Account with client_id: %s deauthorized', 'stripe deauthorize account info message', 'affiliate-for-woocommerce' ), $client_id ) );
			} catch ( \Stripe\Exception\OAuth\InvalidClientException $e ) {
				/* translators: %s: Client ID */
				Affiliate_For_WooCommerce::get_instance()->log( 'warning', sprintf( _x( 'Deauthorize Account: Account with client_id: %s have been deauthorized previously', 'stripe deauthorize account warning message', 'affiliate-for-woocommerce' ), $client_id ) );

				return false;
			} catch ( Exception $e ) {
				/* translators: %s: Error message */
				Affiliate_For_WooCommerce::get_instance()->log( 'error', sprintf( _x( 'Deauthorize Account: Could not be deauthorize account: %s', 'stripe deauthorize account error message', 'affiliate-for-woocommerce' ), $e->getMessage() ) );

				return false;
			}

			return $user_deauthorized;
		}

		/**
		 * Retrieves link for OAuth connection
		 *
		 * @return string|bool Connection url or false on failure
		 */
		public function get_oauth_link() {
			try {
				$args       = apply_filters(
					'afwc_wc_stripe_oauth_link_args',
					array(
						'client_id'    => $this->afwc_stripe_client_id,
						'redirect_uri' => afwc_myaccount_dashboard_url() . '?afwc-tab=resources',
						'scope'        => 'read_write',
					)
				);
				$oauth_link = OAuth::authorizeUrl( $args );
			} catch ( Exception $e ) {
				/* translators: %s: Error message */
				Affiliate_For_WooCommerce::get_instance()->log( 'error', sprintf( _x( 'Could not generate oauth link: %s', 'stripe oauth link generate error message', 'affiliate-for-woocommerce' ), $e->getMessage() ) );

				return false;
			}

			return $oauth_link;
		}

		/**
		 * Retrieves unique token after OAuth connection
		 *
		 * @param string $code Code returned by Stripe after OAuth connection.
		 *
		 * @return string|bool Unique authorization code for the user, or false on failure
		 */
		public function get_oauth_token( $code = '' ) {
			try {
				$args  = array(
					'client_id'  => $this->afwc_stripe_client_id,
					'code'       => $code,
					'grant_type' => 'authorization_code',
				);
				$token = OAuth::token( $args );
			} catch ( Exception $e ) {
				/* translators: %s: Error message */
				Affiliate_For_WooCommerce::get_instance()->log( 'error', sprintf( _x( 'Could not generate oauth token: %s', 'stripe oauth link generate error message', 'affiliate-for-woocommerce' ), $e->getMessage() ) );

				return false;
			}

			return $token;
		}

		/* === CHARGE RELATED API === */

		/**
		 * Create a charges
		 *
		 * @param array $args Array of parameters to use for charge creation.
		 * @param array $options Array of options for the API call.
		 *
		 * @return StripeObject|bool Charge object or false on failure
		 */
		public function create_charge( $args = array(), $options = array() ) {
			try {
				return Charge::create( $args, $options );
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * Update a charge
		 *
		 * @param string $charge_id Charge id.
		 * @param array  $params Array of parameters to use for charge update.
		 * @param array  $options Array of options for the API call.
		 *
		 * @return StripeObject|bool Charge object or false on failure
		 */
		public function update_charge( $charge_id, $params = array(), $options = array() ) {
			try {
				$charge = $this->retrieve_charge( $charge_id, $options );

				// edit.
				foreach ( $params as $key => $value ) {
					$charge->{$key} = $value;
				}

				// save.
				return $charge->save();
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * Retrieves a charge object
		 *
		 * @param string $id Charge id.
		 * @param array  $options Array of options for the API call.
		 *
		 * @return StripeObject|bool Retrieved charge or false on failure
		 */
		public function retrieve_charge( $id, $options = array() ) {
			try {
				$charge = Charge::retrieve( $id, $options );

				return $charge;
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * Create a transfer
		 *
		 * @param array $args Array of parameters to use for Transfer creation.
		 *
		 * @return StripeObject|bool Transfer created or false on failure
		 */
		public function create_transfer( $args = array() ) {
			try {
				$transfer = \Stripe\Transfer::create( $args );
			} catch ( Exception $e ) {
				return array( 'error_transfer' => $e->getMessage() );
			}

			return $transfer;
		}

		/* === CUSTOMER RELATED API === */

		/**
		 * New customer
		 *
		 * @param array $params  Array of parameters for customer creation.
		 * @param array $options Array of options for the API call.
		 *
		 * @return Customer
		 * @throws \Stripe\Exception\ApiErrorException Throws this exception when there is an error with the API call.
		 */
		public function create_customer( $params, $options = array() ) {
			return Customer::create( $params, $options );
		}

		/**
		 * Retrieve customer
		 *
		 * @param string|Customer $customer Customer object or ID.
		 * @param array           $options  Array of options for the API call.
		 *
		 * @return Customer
		 * @throws \Stripe\Exception\ApiErrorException Throws this exception when there is an error with the API call.
		 */
		public function get_customer( $customer, $options = array() ) {
			if ( is_a( $customer, '\Stripe\Customer' ) ) {
				return $customer;
			}

			return Customer::retrieve(
				array(
					'id'     => $customer,
					'expand' => array( 'sources' ),
				),
				$options
			);
		}

		/**
		 * Update customer
		 *
		 * @param \Stripe\Customer|string $customer Customer object or ID.
		 * @param array                   $params   Array of parameters to update.
		 *
		 * @return \Stripe\Customer
		 * @throws \Stripe\Exception\ApiErrorException Throws error when customer can't be updated.
		 */
		public function update_customer( $customer, $params ) {
			$customer = $this->get_customer( $customer );

			// edit.
			foreach ( $params as $key => $value ) {
				$customer->{$key} = $value;
			}

			// save.
			return $customer->save();
		}

		/* === CARD RELATED API === */

		/**
		 * Create a card
		 *
		 * @param \Stripe\Customer|string $customer Customer object or ID.
		 * @param string                  $token    Token to create.
		 * @param string                  $type     Type of item to create.
		 *
		 * @return \Stripe\StripeObject
		 * @depreacted
		 *
		 * @throws \Stripe\Exception\ApiErrorException Throws exception when card cannot be created for customer.
		 */
		public function create_card( $customer, $token, $type = 'card' ) {
			$customer = $this->get_customer( $customer );

			$result = $customer->sources->create(
				array(
					$type => $token,
				)
			);

			do_action( 'afwc_wc_stripe_connect_card_created', $customer, $token, $type );

			return $result;
		}

		/**
		 * Retrieve a card object for the customer
		 *
		 * @param \Stripe\Customer| string $customer Customer object or ID.
		 * @param string                   $card_id  Card id.
		 * @param array                    $params   Additional parameters.
		 *
		 * @return \Stripe\StripeObject
		 * @depreacted
		 */
		public function get_card( $customer, $card_id, $params = array() ) {
			$card = $customer->sources->retrieve( $card_id, $params );

			return $card;
		}

		/**
		 * Se the default card for the customer
		 *
		 * @param \Stripe\Customer|string $customer Customer object or ID.
		 * @param string                  $card_id  Card to set as default.
		 *
		 * @return \Stripe\StripeObject
		 * @depreacted
		 *
		 * @throws \Stripe\Exception\ApiErrorException Throws exception when card or customer cannot be found.
		 */
		public function set_default_card( $customer, $card_id ) {
			$result = $this->update_customer(
				$customer,
				array(
					'default_source' => $card_id,
				)
			);

			do_action( 'afwc_wc_stripe_connect_card_set_default', $customer, $card_id );

			return $result;
		}

		/* === SOURCE RELATED API === */

		/**
		 * Retrieve source object
		 *
		 * @param string $source Source id.
		 *
		 * @return \Stripe\StripeObject|bool
		 */
		public function get_source( $source ) {
			try {
				return Source::retrieve( $source );
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * Create a source
		 *
		 * @param array $params Array of parameters for source creation.
		 *
		 * @return \Stripe\StripeObject
		 *
		 * @throws Exception Throws an exception when an error occurs with api handling.
		 */
		public function create_source( $params ) {
			$result = Source::create( $params );

			do_action( 'afwc_wc_stripe_connect_card_created', $result, $params );

			return $result;
		}

		/**
		 *  Remove a source from a customer.
		 *
		 * @param \Stripe\Customer|string $customer_id Customer object or ID.
		 * @param string                  $source_id   Source ID.
		 *
		 * @return \Stripe\Customer
		 *
		 * @throws Exception Throws exception when source or customer cannot be found.
		 */
		public function delete_source( $customer_id, $source_id ) {
			$customer = $this->get_customer( $customer_id );
			/**
			 * Retrieve source object from stripe
			 *
			 * @var \Stripe\Source $source
			 */
			$source = $customer->sources->retrieve( $source_id );
			$source->detach();

			return $customer;
		}

		/* === PAYMENT INTENTS METHODS === */

		/**
		 * Retrieve a payment intent object on stripe, using id passed as argument
		 *
		 * @param string $payment_intent_id Payment intent id.
		 * @param array  $options           Array of optionss to use for API call.
		 *
		 * @return \Stripe\StripeObject|bool Payment intent or false
		 */
		public function get_intent( $payment_intent_id, $options = array() ) {
			try {
				return PaymentIntent::retrieve( $payment_intent_id, $options );
			} catch ( \Stripe\Exception\ApiErrorException $e ) {
				return false;
			}
		}

		/**
		 * Create a payment intent object on stripe, using parameters passed as argument
		 *
		 * @param array $params Array of parameters used to create Payment intent.
		 * @param array $options Array of options for the API call.
		 *
		 * @return \Stripe\StripeObject|bool Brand new payment intent or false on failure
		 * @throws \Stripe\Exception\ApiErrorException Throws this exception when API error occurs.
		 */
		public function create_intent( $params, $options = array() ) {
			return PaymentIntent::create(
				$params,
				array_merge(
					$options,
					array(
						'idempotency_key' => afwc_generate_random_string( 24, $params ),
					)
				)
			);
		}

		/**
		 * Update a payment intent object on stripe, using parameters passed as argument
		 *
		 * @param string $payment_intent_id Payment Intent to update.
		 * @param array  $params            Array of parameters used to update Payment intent.
		 * @param array  $options           Array of options for the api call.
		 *
		 * @return \Stripe\StripeObject|bool Updated payment intent or false on failure
		 */
		public function update_intent( $payment_intent_id, $params, $options = array() ) {
			try {
				return PaymentIntent::update( $payment_intent_id, $params, $options );
			} catch ( \Stripe\Exception\ApiErrorException $e ) {
				return false;
			}
		}

		/**
		 * Retrieve a payment method object on stripe, using id passed as argument
		 *
		 * @param string $payment_method_id Payment method id.
		 * @param array  $options           Array of optionss to use for API call.
		 *
		 * @return \Stripe\StripeObject|bool Payment intent or false
		 */
		public function get_payment_method( $payment_method_id, $options = array() ) {
			try {
				return PaymentMethod::retrieve( $payment_method_id, $options );
			} catch ( \Stripe\Exception\ApiErrorException $e ) {
				return false;
			}
		}

		/**
		 * Detach a payment method from the customer
		 *
		 * @param string $payment_method_id Payment method id.
		 * @param array  $options           Array of optionss to use for API call.
		 *
		 * @return StripeObject|bool Detached payment method, or false on failure
		 */
		public function delete_payment_method( $payment_method_id, $options = array() ) {
			try {
				return PaymentMethod::retrieve( $payment_method_id, $options )->detach();
			} catch ( \Stripe\Exception\ApiErrorException $e ) {
				return false;
			}
		}

		/**
		 * Retrieve a setup intent object on stripe, using id passed as argument
		 *
		 * @param string $setup_intent_id Setup intent id.
		 * @param array  $options         Array of options to use for API call.
		 *
		 * @return \Stripe\StripeObject|bool Setup intent or false
		 */
		public function get_setup_intent( $setup_intent_id, $options = array() ) {
			try {
				return SetupIntent::retrieve( $setup_intent_id, $options );
			} catch ( \Stripe\Exception\ApiErrorException $e ) {
				return false;
			}
		}

		/**
		 * Create a payment intent object on stripe, using parameters passed as argument
		 *
		 * @param array $params  Array of parameters used to create Payment intent.
		 * @param array $options Array of options to use for API call.
		 *
		 * @return \Stripe\StripeObject|bool Brand new payment intent or false on failure
		 */
		public function create_setup_intent( $params, $options = array() ) {
			try {
				return SetupIntent::create(
					$params,
					array_merge(
						array( 'idempotency_key' => afwc_generate_random_string( 24, $params ) ),
						$options
					)
				);
			} catch ( \Stripe\Exception\ApiErrorException $e ) {
				return false;
			}
		}

		/**
		 * Update a setup intent object on stripe, using parameters passed as argument
		 *
		 * @param string $setup_intent_id Intent id.
		 * @param array  $params          Array of parameters used to update Payment intent.
		 * @param array  $options         Array of options to use for API call.
		 *
		 * @return \Stripe\StripeObject|bool Updated payment intent or false on failure
		 */
		public function update_setup_intent( $setup_intent_id, $params, $options = array() ) {
			try {
				return SetupIntent::update( $setup_intent_id, $params, $options );
			} catch ( \Stripe\Exception\ApiErrorException $e ) {
				return false;
			}
		}

		/**
		 * Retrieve a PaymentIntent or a SetupIntent, depending on the id that it receives
		 *
		 * @param string $id      Id of the intent that method should retrieve.
		 * @param array  $options Array of options to use for API call.
		 *
		 * @return \Stripe\StripeObject|bool Intent or false on failure
		 */
		public function get_correct_intent( $id, $options = array() ) {
			try {
				if ( strpos( $id, 'seti' ) !== false ) {
					return $this->get_setup_intent( $id, $options );
				} else {
					return $this->get_intent( $id, $options );
				}
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * Update a PaymentIntent or a SetupIntent, depending on the id that it receives
		 *
		 * @param string $id      Id of the intent that method should retrieve.
		 * @param array  $params  Array of parameters that should be used to update intent.
		 * @param array  $options Array of parameters that should be used to update intent.
		 *
		 * @return \Stripe\StripeObject|bool Intent or false on failure
		 */
		public function update_correct_intent( $id, $params, $options = array() ) {
			try {
				if ( strpos( $id, 'seti' ) !== false ) {
					return $this->update_setup_intent( $id, $params, $options );
				} else {
					return $this->update_intent( $id, $params, $options );
				}
			} catch ( Exception $e ) {
				return false;
			}
		}

		/* === BALANCE RELATED API === */

		/**
		 * Retrieve currently active balance
		 *
		 * @param array|null $options Array of options.
		 *
		 * @return \Stripe\Balance|bool Balance, or false on failure
		 * @throws \Stripe\Exception\ApiErrorException Throws exception when balance cannot be retrieved.
		 */
		public function get_balance( $options = null ) {
			try {
				return \Stripe\Balance::retrieve( $options );
			} catch ( \Stripe\Exception\ApiConnectionException $e ) {
				return false;
			}
		}

		/**
		 * Get balance transaction
		 *
		 * @param int $transaction_id Balance transaction id.
		 *
		 * @return \Stripe\BalanceTransaction|bool Object
		 * @throws \Stripe\Exception\ApiErrorException Throws exception when balance transaction cannot be retrieved.
		 */
		public function get_balance_transaction( $transaction_id ) {

			try {
				return \Stripe\BalanceTransaction::retrieve( $transaction_id );
			} catch ( \Stripe\Exception\ApiConnectionException $e ) {
				return false;
			}
		}

	}

}
