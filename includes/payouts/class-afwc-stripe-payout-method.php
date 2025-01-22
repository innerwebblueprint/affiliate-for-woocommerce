<?php
/**
 * Main class for Stripe Payout method.
 *
 * @package    affiliate-for-woocommerce/includes/payouts/
 * @since      8.9.0
 * @version    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Stripe_Payout_Method' ) ) {

	/**
	 * Affiliate Stripe Payout method class.
	 */
	class AFWC_Stripe_Payout_Method {

		/**
		 * Main method to process payouts.
		 *
		 * @param array $params the params to initialize the payout.
		 *
		 * @throws Exception When payout failed.
		 * @return array|WP_Error The response.
		 */
		public function execute_payout( $params = array() ) {
			try {
				// Requirements checks.
				if ( empty( $params['affiliate_id'] ) || empty( $params['currency'] ) || empty( $params['referrals'] ) ) {
					throw new Exception( _x( 'Required parameter missing for the Stripe Payout', 'Error message for missing requirements for Stripe payout', 'affiliate-for-woocommerce' ) );
				}

				// Validation.
				$result = $this->validate( $params );

				if ( is_wp_error( $result ) ) {
					throw new Exception( is_callable( array( $result, 'get_error_message' ) ) ? $result->get_error_message() : _x( 'Stripe payout validation failed', 'Error message for Stripe payout validation failed', 'affiliate-for-woocommerce' ) );
				}

				if ( true !== $result ) {
					throw new Exception( _x( 'Something went wrong during Stripe payout', 'Error message for Stripe payout failed', 'affiliate-for-woocommerce' ) );
				}

				// Format the records to payout.
				$records = $this->get_records( absint( $params['affiliate_id'] ), $params['referrals'] );

				if ( is_wp_error( $records ) ) {
					throw new Exception( is_callable( array( $records, 'get_error_message' ) ) ? $records->get_error_message() : _x( 'Stripe payout failed', 'Error message for Stripe payout failed', 'affiliate-for-woocommerce' ) );
				}

				$stripe_api    = AFWC_Stripe_API::get_instance();
				$payout_result = is_callable( array( $stripe_api, 'process_stripe_payout' ) ) ? $stripe_api->process_stripe_payout( $records, $params['currency'] ) : array( 'ACK' => 'Error' );

				if ( is_wp_error( $payout_result ) || empty( $payout_result ) || ! is_array( $payout_result ) ) {
					/* translators: Stripe response message */
					throw new Exception( sprintf( _x( 'Stripe payout failed. Message: %1$s, Response: %2$s.', 'payout failed debug message', 'affiliate-for-woocommerce' ), is_callable( array( $payout_result, 'get_error_message' ) ) ? $payout_result->get_error_message() : '', print_r( $payout_result, true ) ) ); // phpcs:ignore
				}

				if ( ! empty( $payout_result['ACK'] ) && 'Success' === $payout_result['ACK'] ) {
					return array(
						'success'             => true,
						'amount'              => ( ( ! empty( $payout_result['amount'] ) ) ? floatval( $payout_result['amount'] ) : 0.00 ),
						'transfer_id'         => ( ( ! empty( $payout_result['transfer_id'] ) ) ? $payout_result['transfer_id'] : '' ),
						'destination_payment' => ( ( ! empty( $payout_result['destination_payment'] ) ) ? $payout_result['destination_payment'] : '' ),
						'receiver'            => ( ( ! empty( $records['destination'] ) ) ? $records['destination'] : '' ),
					);
				}

				throw new Exception( _x( 'Something went wrong', 'Stripe payout failed message', 'affiliate-for-woocommerce' ) );
			} catch ( Exception $e ) {
				if ( is_callable( array( $e, 'getMessage' ) ) ) {
					Affiliate_For_WooCommerce::get_instance()->log( 'error', $e->getMessage() );
				}
				return new WP_Error( 'afwc-stripe-payout-error', is_callable( array( $e, 'getMessage' ) ) ? $e->getMessage() : '' );
			}
		}

		/**
		 * Validate the records for payout.
		 *
		 * @param array $params The params.
		 *
		 * @throws Exception When Validation failed.
		 * @return bool|WP_Error Return true if validated otherwise WP_Error instance.
		 */
		public function validate( $params = array() ) {
			try {
				// Check if affiliate account is connected to Stripe before proceeding for payout.
				$stripe_functions = is_callable( array( 'AFWC_Stripe_Functions', 'get_instance' ) ) ? AFWC_Stripe_Functions::get_instance() : null;
				$receiver_status  = ( ! empty( $stripe_functions ) && is_callable( array( $stripe_functions, 'afwc_get_stripe_user_status' ) ) ) ? $stripe_functions->afwc_get_stripe_user_status( $params['affiliate_id'] ) : '';

				// Check if affiliate's Stripe account is connected with store. Their Stripe ID must be available to proceed with the transfer.
				if ( 'disconnect' === $receiver_status ) {
					/* translators: %s: Affiliate ID */
					throw new Exception( sprintf( _x( 'Affiliate\'s Stripe account is not connected. So cannot pay commission to the affiliate: %s.', 'Stripe payout account not connected to Stripe message', 'affiliate-for-woocommerce' ), $params['affiliate_id'] ) );
				}

				if ( empty( $params['currency'] ) ) {
					throw new Exception( _x( 'Currency is not provided', 'Stripe Payout validation failed message for currency', 'affiliate-for-woocommerce' ) );
				}

				do_action( 'afwc_stripe_payout_method_validation', $params );

				return true;
			} catch ( Exception $e ) {
				return new WP_Error( 'afwc-stripe-payout-validation-error', is_callable( array( $e, 'getMessage' ) ) ? $e->getMessage() : '' );
			}
		}

		/**
		 * Filter and arrange the records for payout.
		 *
		 * @param int   $affiliate_id The affiliate ID.
		 * @param array $records The records.
		 *
		 * @return array|WP_Error The array of response otherwise WP_Error instance if failed.
		 */
		public function get_records( $affiliate_id = 0, $records = array() ) {

			$affiliate_id = absint( $affiliate_id );

			if ( empty( $affiliate_id ) || empty( $records ) || ! is_array( $records ) ) {
				return array();
			}

			// Get stripe user id from meta.
			$stripe_receiver_account_id = get_user_meta( $affiliate_id, 'afwc_stripe_user_id', true );

			if ( empty( $stripe_receiver_account_id ) ) {
				/* translators: The affiliate ID */
				return new WP_Error( 'afwc-stripe-account-not-found', sprintf( _x( 'Stripe account is missing for the affiliate ID: %d', 'Stripe Payout validation failed message for account missing', 'affiliate-for-woocommerce' ), $affiliate_id ) );
			}

			return array(
				'id'             => $affiliate_id,
				'amount'         => ! empty( $records['amount'] ) ? floatval( $records['amount'] ) : 0,
				'note'           => ! empty( $records['note'] ) ? $records['note'] : '',
				'destination'    => $stripe_receiver_account_id,
				'transfer_group' => '',
			);
		}

		/**
		 * Method to get supported currencies.
		 *
		 * @return array Supported currencies
		 */
		public function get_supported_currency() {
			return array( 'USD' );
		}

	}
}
