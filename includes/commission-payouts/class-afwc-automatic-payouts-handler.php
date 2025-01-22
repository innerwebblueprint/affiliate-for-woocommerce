<?php
/**
 * Class to handle action scheduler for automatic payouts
 *
 * @package   affiliate-for-woocommerce/includes/commission-payouts/
 * @since     8.0.0
 * @version   1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Automatic_Payouts_Handler' ) ) {

	/**
	 * Main class to get outstanding commissions for affiliates payout
	 */
	class AFWC_Automatic_Payouts_Handler {

		/**
		 * Variable to hold the group name.
		 *
		 * @var string $group
		 */
		protected $group = 'affiliate-for-woocommerce';

		/**
		 * Variable to hold the email action name - sent as first.
		 *
		 * @var string $email_1_action
		 */
		public $email_1_action = 'afwc_send_automatic_payout_email_notification_1';

		/**
		 * Variable to hold the email action name - sent as second.
		 *
		 * @var string $email_2_action
		 */
		public $email_2_action = 'afwc_send_automatic_payout_email_notification_2';

		/**
		 * Variable to hold the action name to process automatic payouts.
		 *
		 * @var string $process_payout_action
		 */
		public $process_payout_action = 'afwc_process_automatic_payout';

		/**
		 * Variable to hold instance of this class
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Automatic_Payouts_Handler Singleton object of this class
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
			// When payout settings are updated, we proceed.
			add_action( 'afwc_admin_payouts_settings_updated', array( $this, 'handle_automatic_payouts_as' ), 11, 2 );

			add_action( 'afwc_send_automatic_payout_email_notification_1', array( $this, 'send_email_notification_1' ), 10, 1 );
			add_action( 'afwc_send_automatic_payout_email_notification_2', array( $this, 'send_email_notification_2' ), 10, 1 );
			add_action( 'afwc_process_automatic_payout', array( $this, 'process_automatic_payout' ), 10, 1 );
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
		 * Method to check whether the automatic payout feature is enabled and allowed.
		 *
		 * @return yes|no Return if enabled.
		 */
		public static function is_enabled() {
			$ap_enabled = 'no';

			// Automatic payout setting depends if payout methods are enabled.
			$ap_methods = afwc_get_available_payout_methods();
			if ( empty( $ap_methods ) || ! is_array( $ap_methods ) ) {
				return $ap_enabled;
			}

			// If payout methods are enabled, check for the setting.
			$ap_enabled = ( 'yes' === get_option( 'afwc_enable_automatic_payouts', 'no' ) ? 'yes' : 'no' );

			return $ap_enabled;
		}

		/**
		 * Function to get default payout date for payouts.
		 */
		public function get_default_payout_date() {
			$payout_date = get_option( 'afwc_commission_payout_day', 15 );
			if ( empty( $payout_date ) ) {
				return;
			}

			// days in current month.
			// date is replaced with gmdate.
			$days = gmdate( 't' );

			// if option date is available in a month, pass it. Else get days in current month.
			$default_payout_date = ( $payout_date <= $days ) ? $payout_date : $days;

			return $default_payout_date;
		}

		/**
		 * Get the timestamp to start the action.
		 *
		 * @param string $datetime The date time. Default will be empty.
		 * @param string $type     The type of request. snooze | next_period | empty.
		 *
		 * @return int Return current date.
		 */
		public function get_timestamp( $datetime = '', $type = '' ) {
			// get the automatic payout date.
			$setting_payout_day = $this->get_default_payout_date();

			// get today's date, Get current date from site.
			$current_date = afwc_get_current_date( true, 'Y-m-d H:i:s' );

			// We will check if we are getting type of request to get date.
			if ( empty( $type ) ) {
				// Build the date string based on current month and year.
				// date is replaced with gmdate.
				$setting_date_string = sprintf( '%04d-%02d-%02d', gmdate( 'Y' ), gmdate( 'm' ), $setting_payout_day );
				// Create a DateTime object based on site timezone.
				$date             = new DateTime( $setting_date_string, wp_timezone() );
				$payout_full_date = $date->format( 'Y-m-d H:i:s' );

				// if date from setting is more than today's date, schedule payout for this month.
				// else for next month.
				// removed = check as we don't want to.
				$payout_date = ( $payout_full_date > $current_date ) ? $payout_full_date : $this->snooze_date( afwc_get_current_date( true ) );
			} else {
				// Snooze will always be on next month.
				if ( 'snooze' === $type ) {
					$payout_date = $this->snooze_date( $datetime );
				} elseif ( 'next_period' === $type ) {
					// Next period is used when current automatic payout is processed and new needs to be scheduled.
					$setting_date_string = sprintf( '%04d-%02d-%02d', gmdate( 'Y' ), gmdate( 'm', strtotime( 'first day of +1 month' ) ), $setting_payout_day );

					$date        = new DateTime( $setting_date_string, wp_timezone() );
					$payout_date = $date->format( 'Y-m-d H:i:s' );
				}
			}

			// Date is an object so it needs to be accessed via format.
			$timestamp = ( ! empty( $payout_date ) ) ? ( is_object( $payout_date ) ? $payout_date->format( 'Y-m-d H:i:s' ) : $payout_date ) : '';

			return $timestamp;
		}

		/**
		 * Function to schedule or unschedule automatic payouts action scheduler based on settings.
		 *
		 * @param string $datetime The date time passed.
		 *
		 * @return DateTime object
		 */
		public function snooze_date( $datetime = '' ) {
			// No need to check for empty datetime as it will take current date.

			$date = new DateTime( $datetime, wp_timezone() );
			// We are changing to next month.
			$payout_date = $date->modify( '+30 days' );
			// We are re-chaing date to make sure next month aligns with payment day setting.
			$payout_date->setDate( $payout_date->format( 'Y' ), $payout_date->format( 'm' ), $this->get_default_payout_date() );

			return $payout_date;
		}

		/**
		 * Function to schedule or unschedule automatic payouts action scheduler based on settings.
		 *
		 * @param array $settings Array of settings in the tab.
		 * @param array $args     Array of arguments.
		 */
		public function handle_automatic_payouts_as( $settings = array(), $args = array() ) {

			// Unscheduling is called earlier to handle if affiliate is removed from the includes setting or if automatic payouts are turned off.
			$this->handle_actions_automatic_payouts_to_unschedule();

			// Check if feature is enabled.
			if ( 'no' === $this->is_enabled() || empty( $this->is_enabled() ) ) {
				return;
			}

			// get list of affiliates enabled to do automatic payouts.
			$affiliate_ids = get_option( 'afwc_automatic_payout_includes', array() );
			if ( empty( $affiliate_ids ) || ! is_array( $affiliate_ids ) ) {
				return;
			}

			foreach ( $affiliate_ids as $affiliate_id ) {
				// unschedule any AS for that affiliate first.
				$this->handle_actions_automatic_payouts_to_unschedule( $affiliate_id );

				// call to schedule AS.
				$this->handle_actions_automatic_payouts_to_schedule( $affiliate_id );
			}

			return $settings;
		}

		/**
		 * Function to unschedule automatic payouts all action scheduler.
		 *
		 * @param int $affiliate_id The user_id of the affiliate.
		 */
		public function handle_actions_automatic_payouts_to_unschedule( $affiliate_id = 0 ) {
			// No need to do early exit for $affiliate_id to unschedule all.
			$affiliate_id = ( ! empty( $affiliate_id ) ) ? absint( $affiliate_id ) : 0;

			$this->unschedule_automatic_payouts_as( $this->email_1_action, array( 'affiliate_id' => $affiliate_id ) );
			$this->unschedule_automatic_payouts_as( $this->email_2_action, array( 'affiliate_id' => $affiliate_id ) );
			$this->unschedule_automatic_payouts_as( $this->process_payout_action, array( 'affiliate_id' => $affiliate_id ) );
		}

		/**
		 * Function to unschedule automatic payouts action scheduler.
		 *
		 * @param array $action The name of the unschedule action.
		 * @param array $args   Array of arguments.
		 */
		public function unschedule_automatic_payouts_as( $action = '', $args = array() ) {
			if ( empty( $action ) ) {
				return;
			}

			if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_unschedule_action' ) ) {
				return;
			}

			if ( as_has_scheduled_action( $action ) ) {
				if ( is_array( $args ) && ! empty( $args ) && empty( $args['affiliate_id'] ) ) {
					as_unschedule_all_actions( $action );
				} else {
					as_unschedule_action( $action, $args, $this->group );
				}
			}
		}

		/**
		 * Function to schedule automatic payouts all action scheduler based on args.
		 *
		 * @param int   $affiliate_id The user_id of the affiliate.
		 * @param array $args         Array of arguments.
		 */
		public function handle_actions_automatic_payouts_to_schedule( $affiliate_id = 0, $args = array() ) {
			if ( empty( $affiliate_id ) ) {
				return;
			}

			$affiliate_id = absint( $affiliate_id );

			// Check if user is an affiliate, to prevent any new AS.
			if ( 'yes' !== afwc_is_user_affiliate( $affiliate_id ) ) {
				return;
			}

			// get payout timestamp.
			// TODO: should timestamp for email be picked from func? or process_payout_AS?
			if ( ! empty( $args ) && is_array( $args ) && ! empty( $args['request'] ) ) {
				if ( 'snooze' === $args['request'] && ! empty( $args['payout_date'] ) ) {
					$timestamp = $this->get_timestamp( $args['payout_date'], 'snooze' );
				} elseif ( 'next_period' === $args['request'] ) {
					$timestamp = $this->get_timestamp( '', 'next_period' );
				}
			} else {
				$timestamp = $this->get_timestamp();
			}

			// schedule new AS for email reminder 1.
			$first_email_notification_reminder_time = $this->email_notification_timestamp( $this->email_1_action, $timestamp );
			$this->schedule_automatic_payouts_as(
				$first_email_notification_reminder_time,
				$this->email_1_action,
				array(
					'affiliate_id' => $affiliate_id,
				)
			);

			// schedule new AS for email reminder 2.
			$second_email_notification_reminder_time = $this->email_notification_timestamp( $this->email_2_action, $timestamp );
			$this->schedule_automatic_payouts_as(
				$second_email_notification_reminder_time,
				$this->email_2_action,
				array(
					'affiliate_id' => $affiliate_id,
				)
			);

			// schedule new AS for payout.
			$this->schedule_automatic_payouts_as(
				$timestamp,
				$this->process_payout_action,
				array(
					'affiliate_id' => $affiliate_id,
				)
			);
		}

		/**
		 * Function to schedule automatic payouts action scheduler.
		 *
		 * @param string $timestamp The datetime to schedule actions.
		 * @param string $action    The name of the action to schedule.
		 * @param array  $args      Array of arguments.
		 */
		public function schedule_automatic_payouts_as( $timestamp = '', $action = '', $args = array() ) {
			if ( empty( $timestamp ) ) {
				return;
			}

			if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_single_action' ) ) {
				return;
			}

			$affiliate_id = ( ! empty( $args['affiliate_id'] ) ) ? absint( $args['affiliate_id'] ) : 0;
			if ( empty( $affiliate_id ) ) {
				return;
			}

			as_schedule_single_action( $timestamp, $action, array( 'affiliate_id' => $affiliate_id ), $this->group );
		}

		/**
		 * Function to calculate date time for automatic payouts email's action scheduler.
		 *
		 * @param string $action    The name of the action to schedule.
		 * @param string $timestamp The datetime to schedule actions.
		 *
		 * @return datetime
		 */
		public function email_notification_timestamp( $action = '', $timestamp = '' ) {
			if ( empty( $timestamp ) ) {
				return;
			}

			$date = new DateTime( $timestamp );

			// Modify the date by subtracting 2 days if action 1.
			// Modify the date by subtracting 12 hours if action 2.
			// otherwise empty.
			$email_date = ( $this->email_1_action === $action ) ? $date->modify( '-2 days' ) : ( ( $this->email_2_action === $action ) ? $date->modify( '-12 hours' ) : '' );
			$mail_date  = ( ! empty( $email_date ) ) ? $email_date->format( 'Y-m-d H:i:s' ) : '';

			return $mail_date;
		}

		/**
		 * Function to trigger first email notification for automatic payouts.
		 *
		 * @param int $affiliate_id The user_id of affiliate.
		 */
		public function send_email_notification_1( $affiliate_id = 0 ) {
			if ( empty( $affiliate_id ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					_x( 'Missing Affiliate ID. Email sending failed for automatic payouts first notification email.', 'Logger for automatic payout email report', 'affiliate-for-woocommerce' )
				);
				return;
			}

			$affiliate_id = absint( $affiliate_id );

			// trigger email 1 - Notify affiliate for upcoming payout.
			// TODO: no need to check enable as email needs to be sent irrespective of check.
			if ( true === AFWC_Emails::is_afwc_mailer_enabled( 'afwc_email_automatic_payouts_reminder' ) ) {
				// Trigger email.
				do_action(
					'afwc_email_automatic_payouts_reminder',
					array(
						'affiliate_id'               => $affiliate_id,
						'automatic_payout_timestamp' => $this->get_affiliate_automatic_payout_date( $affiliate_id ),
					)
				);
			}
		}

		/**
		 * Function to trigger second email notification for automatic payouts.
		 *
		 * @param int $affiliate_id The user_id of affiliate.
		 */
		public function send_email_notification_2( $affiliate_id ) {
			if ( empty( $affiliate_id ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					_x( 'Missing Affiliate ID. Email sending failed for automatic payouts second notification email.', 'Logger for automatic payout email report', 'affiliate-for-woocommerce' )
				);
				return;
			}

			$affiliate_id = absint( $affiliate_id );

			// trigger email 2 - Notify affiliate for upcoming payout.
			// TODO: no need to check enable as email needs to be sent irrespective of check.
			if ( true === AFWC_Emails::is_afwc_mailer_enabled( 'afwc_email_automatic_payouts_reminder' ) ) {
				// Trigger email.
				do_action(
					'afwc_email_automatic_payouts_reminder',
					array(
						'affiliate_id'               => $affiliate_id,
						'automatic_payout_timestamp' => $this->get_affiliate_automatic_payout_date( $affiliate_id ),
					)
				);
			}
		}

		/**
		 * Function to trigger automatic payouts.
		 *
		 * @param int $affiliate_id The user_id of affiliate.
		 */
		public function process_automatic_payout( $affiliate_id = 0 ) {
			if ( empty( $affiliate_id ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					_x( 'Missing Affiliate ID. Automatic Payouts failed.', 'Logger for automatic payout process report', 'affiliate-for-woocommerce' )
				);
				return;
			}

			$affiliate_id  = absint( $affiliate_id );
			$affiliate_obj = new AFWC_Affiliate( $affiliate_id );

			// Check if active affiliate.
			if ( ! is_callable( array( $affiliate_obj, 'is_valid' ) ) || ! $affiliate_obj->is_valid() ) {
				return;
			}

			// Get the affiliate preferred payout method.
			$payout_method = is_callable( array( $affiliate_obj, 'get_payout_method' ) ) ? $affiliate_obj->get_payout_method() : '';

			if ( empty( $payout_method ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					sprintf(
						/* translators: 1: Affiliate's payout method  2: The affiliate ID */
						_x( 'Payout method %1$s is not available for payouts, stopping automatic payouts for the affiliate ID: %2$d.', 'Logger for automatic payout process report', 'affiliate-for-woocommerce' ),
						afwc_get_payout_methods( get_user_meta( $affiliate_id, 'afwc_payout_method', true ) ),
						$affiliate_id
					)
				);
				return;
			}

			// Check if affiliate's payout method is supported in Automatic payouts and enabled.
			$automatic_payout_methods = afwc_get_available_payout_methods();
			if ( empty( $automatic_payout_methods ) || ! is_array( $automatic_payout_methods ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					sprintf(
						/* translators: 1: Affiliate's payout method 2: The affiliate ID */
						_x( 'Payout method %1$s is disabled/not found for automatic payouts, stopping automatic payouts for the affiliate ID: %2$d.', 'Logger for automatic payout process report', 'affiliate-for-woocommerce' ),
						afwc_get_payout_methods( get_user_meta( $affiliate_id, 'afwc_payout_method', true ) ),
						$affiliate_id
					)
				);
				return;

			}
			// Check if affiliate's payout method is found automatic payout methods.
			if ( ! array_key_exists( $payout_method, $automatic_payout_methods ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					sprintf(
						/* translators: 1: Affiliate's payout method 2: The affiliate ID */
						_x( 'Payout method %1$s is not supported for automatic payouts, stopping automatic payouts for the affiliate ID: %2$d.', 'Logger for automatic payout process report', 'affiliate-for-woocommerce' ),
						afwc_get_payout_methods( get_user_meta( $affiliate_id, 'afwc_payout_method', true ) ),
						$affiliate_id
					)
				);
				return;
			}

			// Get affiliate's account/email details for payouts via preferred payout method.
			$affiliate_payout_details = is_callable( array( $affiliate_obj, 'get_payout_meta_for_payouts' ) ) ? $affiliate_obj->get_payout_meta_for_payouts( $payout_method ) : '';
			if ( empty( $affiliate_payout_details ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					sprintf(
						/* translators: 1: The affiliate IDs */
						_x( '%1$s details are missing for the affiliate ID: %2$d. Skipping them from automatic payouts.', 'Logger for automatic payout process report', 'affiliate-for-woocommerce' ),
						afwc_get_payout_methods( $payout_method ),
						$affiliate_id
					)
				);
				return;
			}

			// get data to format.
			$commission_payouts = is_callable( array( 'AFWC_Commission_Payouts', 'get_instance' ) ) ? AFWC_Commission_Payouts::get_instance() : null;
			if ( empty( $commission_payouts ) || ! is_callable( array( $commission_payouts, 'get_outstanding_commission_payouts' ) ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					sprintf(
						/* translators: 1: The affiliate IDs */
						_x( 'Cannot fetch data, skipping automatic payouts for affiliate ID: %s.', 'Logger for automatic payout process report', 'affiliate-for-woocommerce' ),
						$affiliate_id
					)
				);
				return;
			}

			// Fetch commission to pay.
			$get_outstanding_payout = $commission_payouts->get_outstanding_commission_payouts( $affiliate_id, array( 'request_type' => 'process_automatic_payouts' ) );
			if ( empty( $get_outstanding_payout ) && ! is_array( $get_outstanding_payout ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					sprintf(
						/* translators: 1: The affiliate IDs */
						_x( 'No outstanding payouts found for the affiliate ID: %d. Skipping this from automatic payout', 'Logger for automatic payout process report', 'affiliate-for-woocommerce' ),
						$affiliate_id
					)
				);
				return;
			}

			// get data of current affiliate.
			$outstanding_payouts = $get_outstanding_payout[0];

			// Convert referral_ids to array.
			$referral_ids = array();
			if ( ! empty( $outstanding_payouts['referral_ids'] ) && strpos( $outstanding_payouts['referral_ids'], ',' ) !== false ) {
				$referral_ids = explode( ',', $outstanding_payouts['referral_ids'] );
			} else {
				$referral_ids[0] = $outstanding_payouts['referral_ids'];
			}

			// Get referrals data.
			$selected_referrals = array();
			foreach ( $referral_ids as $key => $value ) {
				if ( empty( $value ) ) {
					continue;
				}

				$commission_amount_per_referral = $this->get_affiliate_commission_details( $affiliate_id, $value );
				if ( empty( $commission_amount_per_referral ) || ! is_array( $commission_amount_per_referral ) ) {
					continue;
				}

				$selected_referrals[ $key ] = array(
					'affiliate_id' => $affiliate_id,
					'referral_id'  => $value,
					'order_id'     => ( ! empty( $commission_amount_per_referral['order_id'] ) ) ? $commission_amount_per_referral['order_id'] : 0,
					'commission'   => ( ! empty( $commission_amount_per_referral['amount'] ) ) ? $commission_amount_per_referral['amount'] : 0.00,
				);
			}

			$payout_method_for_notes = ( ! empty( $payout_method ) ? afwc_get_payout_methods( $payout_method ) : '' );

			// prepare payout params.
			$payout_params = array(
				'currency'            => ( ! empty( $outstanding_payouts['currency'] ) ) ? $outstanding_payouts['currency'] : AFWC_CURRENCY_CODE,
				'referrals'           => $selected_referrals,
				'note'                => sprintf(
					/* translators: Payout method name */
					_x( 'Automatic payout processed via %s.', 'note for commission payouts when payout is processed via automatic payouts', 'affiliate-for-woocommerce' ),
					$payout_method_for_notes
				),
				'method'              => $payout_method, // Currently supported: PayPal and Stripe for automatic payouts.
				'date'                => gmdate( 'Y-m-d H:i:s' ), // current date time.
				'amount'              => ( ! empty( $outstanding_payouts['total_commission_amount'] ) ? $outstanding_payouts['total_commission_amount'] : 0.00 ),
				'from_date'           => ( ( ! empty( $outstanding_payouts['from_date'] ) ) ? $outstanding_payouts['from_date'] : '' ),
				'to_date'             => ( ( ! empty( $outstanding_payouts['to_date'] ) ) ? $outstanding_payouts['to_date'] : '' ),
				'is_automatic_payout' => true,
			);

			$payout_handler = new AFWC_Payout_Handler( $payout_params );
			$payout_result  = is_callable( array( $payout_handler, 'process_payout' ) ) ? $payout_handler->process_payout( $affiliate_id ) : array();

			if ( ! empty( $payout_result ) && is_array( $payout_result ) && ! empty( $payout_result['success'] ) && true === $payout_result['success'] ) {
				// if payout is success, add a log.
				Affiliate_For_WooCommerce::get_instance()->log(
					'info',
					sprintf(
						/* translators: 1: The affiliate ID */
						_x( 'Automatic payout successfully processed for affiliate ID: %d for this month.', 'Logger for automatic payout process report', 'affiliate-for-woocommerce' ),
						$affiliate_id
					)
				);
			} else {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					sprintf(
						/* translators: 1: The affiliate ID */
						_x( 'Automatic payout failed for affiliate ID: %d. Skipping them from automatic payout and scheduling them for next payout period', 'Logger for automatic payout process report', 'affiliate-for-woocommerce' ),
						$affiliate_id
					)
				);
			}

			// Whether successs or failure, schedule action scheduler of this affiliate for next month.
			$this->handle_actions_automatic_payouts_to_schedule( $affiliate_id, array( 'request' => 'next_period' ) );
		}

		/**
		 * Function to get Action Scheduler's date based on affiliate arg and hook name.
		 *
		 * @param int $affiliate_id The user_id of the affiliate.
		 *
		 * @return string automatic commission payout date if found.
		 */
		public function get_affiliate_automatic_payout_date( $affiliate_id = 0 ) {
			if ( empty( $affiliate_id ) ) {
				return 0;
			}

			global $wpdb;

			$query_result = $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT
										scheduled_date_local
										FROM
											{$wpdb->prefix}actionscheduler_actions
										WHERE
											args LIKE %s
											AND status = %s
											AND hook = %s",
					'%' . $wpdb->esc_like( $affiliate_id ) . '%',
					'pending',
					$this->process_payout_action
				)
			);

			return $query_result;
		}

		/**
		 * Function to get Action Scheduler's date based on affiliate arg and hook name.
		 *
		 * @param int $affiliate_id The user_id of the affiliate.
		 * @param int $referral_id  The referral ID of the referred order.
		 *
		 * @return array referral details.
		 */
		public function get_affiliate_commission_details( $affiliate_id = 0, $referral_id = 0 ) {
			if ( empty( $affiliate_id ) || empty( $referral_id ) ) {
				return 0;
			}

			global $wpdb;

			$query_result = $wpdb->get_row( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT
										referral_id,
										post_id as order_id,
										amount
										FROM
											{$wpdb->prefix}afwc_referrals
										WHERE
											referral_id = %d
											AND affiliate_id = %d",
					$referral_id,
					$affiliate_id
				),
				'ARRAY_A'
			);

			return $query_result;
		}

	}

}

AFWC_Automatic_Payouts_Handler::get_instance();
