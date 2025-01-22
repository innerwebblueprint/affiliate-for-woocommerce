<?php
/**
 * Main class for Pending Payouts Dashboard
 *
 * @package     affiliate-for-woocommerce/includes/admin/
 * @since       8.0.0
 * @version     1.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Pending_Payout_Dashboard' ) ) {

	/**
	 * Main class for Pending Payout Dashboard.
	 */
	class AFWC_Pending_Payout_Dashboard {

		/**
		 * The Ajax events.
		 *
		 * @var array $ajax_events
		 */
		private $ajax_events = array(
			'snooze_scheduled_payouts',
			'pending_payouts_details',
		);

		/**
		 * Variable to hold instance of AFWC_Pending_Payout_Dashboard.
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class.
		 *
		 * @return AFWC_Pending_Payout_Dashboard Singleton object of this class.
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
		public function __construct() {
			add_action( 'wp_ajax_afwc_pending_payout_controller', array( $this, 'request_handler' ) );
		}

		/**
		 * Function to handle all ajax request.
		 */
		public function request_handler() {
			if ( ! afwc_current_user_can_manage_affiliate() || empty( $_REQUEST ) || ! is_array( $_REQUEST ) || empty( wc_clean( wp_unslash( $_REQUEST['cmd'] ) ) ) ) { // phpcs:ignore
				return;
			}

			foreach ( $_REQUEST as $key => $value ) { // phpcs:ignore
				$params[ $key ] = wc_clean( wp_unslash( $value ) );
			}

			$func_nm = ! empty( $params['cmd'] ) ? $params['cmd'] : '';

			if ( empty( $func_nm ) || ! in_array( $func_nm, $this->ajax_events, true ) ) {
				wp_die( esc_html_x( 'You are not allowed to use this action', 'authorization failure message', 'affiliate-for-woocommerce' ) );
			}

			if ( is_callable( array( $this, $func_nm ) ) ) {
				$this->$func_nm( $params );
			}
		}

		/**
		 * Ajax callback method to handle snoozing of scheduled payout.
		 *
		 * @param array $params Array of ajax params.
		 */
		public function snooze_scheduled_payouts( $params = array() ) {

			check_admin_referer( 'afwc-admin-snooze-scheduled-payouts', 'security' );

			if ( empty( $params ) || ! is_array( $params ) || empty( $params['affiliates'] ) ) {
				wp_send_json(
					array(
						'ACK'     => 'Error',
						'message' => _x( 'Required parameter missing - Affiliate ID.', 'error message when fetching profile details', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$affiliates = json_decode( $params['affiliates'], true );
			// For now, we are only accepting single affiliate ID to process.
			if ( 1 === count( $affiliates ) ) {
				$affiliate_id = intval( $affiliates[0] );
			}

			$automatic_payout_handler = is_callable( array( 'AFWC_Automatic_Payouts_Handler', 'get_instance' ) ) ? AFWC_Automatic_Payouts_Handler::get_instance() : null;
			if ( empty( $automatic_payout_handler ) || ! is_callable( array( $automatic_payout_handler, 'handle_actions_automatic_payouts_to_unschedule' ) ) || ! is_callable( array( $automatic_payout_handler, 'handle_actions_automatic_payouts_to_schedule' ) ) || ! is_callable( array( $automatic_payout_handler, 'get_affiliate_automatic_payout_date' ) ) ) {
				wp_send_json(
					array(
						'ACK' => 'Failed',
						'msg' => _x( 'Cannot access requested action.', 'Error message when accessing payout handler class', 'affiliate-for-woocommerce' ),
					)
				);
			}

			// get current payout date for affiliate.
			// neded to pass it to new action scheduler below.
			$affiliate_automatic_current_payout_date = $automatic_payout_handler->get_affiliate_automatic_payout_date( $affiliate_id );

			// unschedule AS for affiliate.
			$automatic_payout_handler->handle_actions_automatic_payouts_to_unschedule( $affiliate_id );

			// schedule new for affiliate.
			$automatic_payout_handler->handle_actions_automatic_payouts_to_schedule(
				$affiliate_id,
				array(
					'request'     => 'snooze',
					'payout_date' => $affiliate_automatic_current_payout_date,
				)
			);

			// get new payout date for affiliate.
			$affiliate_automatic_next_payout_date = $automatic_payout_handler->get_affiliate_automatic_payout_date( $affiliate_id );

			wp_send_json_success(
				array(
					'affiliate_id'                 => $affiliate_id,
					'scheduled_date'               => $affiliate_automatic_next_payout_date,
					'is_automatic_payouts_enabled' => 'yes', // Need to send this else column disappers on snooze.
				)
			);
		}

		/**
		 * Ajax callback method to handle snoozing of scheduled payout.
		 *
		 * @param array $params Array of ajax params.
		 */
		public function pending_payouts_details( $params = array() ) {

			check_admin_referer( 'afwc-admin-pending-payouts-dashboard-data', 'security' );

			// get unpaid commissions - without any limits.
			$commission_payouts = is_callable( array( 'AFWC_Commission_Payouts', 'get_instance' ) ) ? AFWC_Commission_Payouts::get_instance() : null;
			if ( empty( $commission_payouts ) || ! is_callable( array( $commission_payouts, 'get_outstanding_commission_payouts' ) ) ) {
				wp_send_json(
					array(
						'ACK' => 'Failed',
						'msg' => _x( 'Call to fetch the data failed.', 'Error message when fetching outstanding commission payouts', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$get_outstanding_payouts = $commission_payouts->get_outstanding_commission_payouts( 0, array( 'request_type' => 'pending_payouts_dashboard' ) );

			// Send unpaid scheduled commissions.
			// check if feature is enabled then only return this.
			$is_automatic_payout_enabled = ( is_callable( array( 'AFWC_Automatic_Payouts_Handler', 'is_enabled' ) ) && AFWC_Automatic_Payouts_Handler::is_enabled() && 'yes' === AFWC_Automatic_Payouts_Handler::is_enabled() ) ? 'yes' : 'no';

			if ( 'yes' === $is_automatic_payout_enabled ) {
				$automatic_payout_handler = is_callable( array( 'AFWC_Automatic_Payouts_Handler', 'get_instance' ) ) ? AFWC_Automatic_Payouts_Handler::get_instance() : null;

				$all_outstanding_automatic_payouts = $commission_payouts->get_outstanding_commission_payouts( 0, array( 'request_type' => 'process_automatic_payouts' ) );

				// Check if affiliate is included in automatic payouts.
				$automatic_payout_includes_affiliate_ids = get_option( 'afwc_automatic_payout_includes', array() );
				if ( empty( $automatic_payout_includes_affiliate_ids ) || ! is_array( $automatic_payout_includes_affiliate_ids ) ) {
					$automatic_payout_includes_affiliate_ids = array();
				}

				$affiliate_id_to_commissions = array();
				if ( ! empty( $all_outstanding_automatic_payouts ) && is_array( $all_outstanding_automatic_payouts ) ) {
					foreach ( $all_outstanding_automatic_payouts as $outstanding_affiliate_automatic_payouts ) {
						$affiliate_id = ( ! empty( $outstanding_affiliate_automatic_payouts['affiliate_id'] ) ) ? $outstanding_affiliate_automatic_payouts['affiliate_id'] : 0;
						if ( empty( $affiliate_id ) ) {
							continue;
						}

						// Set commission to 0 if affiliate is not included in the allowed list.
						// Better to be handled at the query.
						if ( ! in_array( $affiliate_id, $automatic_payout_includes_affiliate_ids, true ) ) {
							$commissions = 0.00;
						} else {
							$commissions = ( ! empty( $outstanding_affiliate_automatic_payouts['total_commission_amount'] ) ) ? afwc_format_price( $outstanding_affiliate_automatic_payouts['total_commission_amount'] ) : 0.00;
						}
						$affiliate_id_to_commissions[ $affiliate_id ] = $commissions;
					}
				}
			}

			$data                     = array( 'details' => array() );
			$affiliate_payout_details = array();
			foreach ( $get_outstanding_payouts as $affiliate_payout_details ) {
				$affiliate_id = ( ! empty( $affiliate_payout_details['affiliate_id'] ) ) ? $affiliate_payout_details['affiliate_id'] : '0';
				if ( empty( $affiliate_id ) ) {
					continue;
				}

				// Affiliate details - name and email.
				$affiliate_data = new AFWC_Admin_Affiliates( $affiliate_id );
				$details        = is_callable( array( $affiliate_data, 'get_affiliates_details' ) ) ? $affiliate_data->get_affiliates_details() : array();
				$name           = ( ! empty( $details ) && is_array( $details ) && ! empty( $details[ $affiliate_id ]['name'] ) ) ? $details[ $affiliate_id ]['name'] : 'N/A';
				$email          = ( ! empty( $details ) && is_array( $details ) && ! empty( $details[ $affiliate_id ]['email'] ) ) ? $details[ $affiliate_id ]['email'] : 'N/A';

				$affiliate_payout_details = array(
					'affiliate_id'    => $affiliate_id,
					'affiliate_name'  => $name,
					'affiliate_email' => $email,
					'unpaid_amount'   => afwc_format_price( $affiliate_payout_details['total_commission_amount'] ),
				);

				// if automatic payouts is enabled, pass additional data.
				if ( 'yes' === $is_automatic_payout_enabled ) {
					$affiliate_payout_details['scheduled_amount'] = ( ! empty( $affiliate_id_to_commissions[ $affiliate_id ] ) ) ? afwc_format_price( $affiliate_id_to_commissions[ $affiliate_id ] ) : 0.00;
					$automatic_payout_handler                     = is_callable( array( 'AFWC_Automatic_Payouts_Handler', 'get_instance' ) ) ? AFWC_Automatic_Payouts_Handler::get_instance() : null;
					$affiliate_payout_details['scheduled_date']   = $automatic_payout_handler->get_affiliate_automatic_payout_date( $affiliate_id );
				}

				array_push( $data['details'], $affiliate_payout_details );
			}

			wp_send_json(
				array(
					'ACK'  => 'Success',
					'data' => array_merge( $data, array( 'is_automatic_payouts_enabled' => $is_automatic_payout_enabled ) ),
				)
			);
		}

	}
}

AFWC_Pending_Payout_Dashboard::get_instance();
