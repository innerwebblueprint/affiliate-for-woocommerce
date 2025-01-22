<?php
/**
 * Main class for the background emailer for affiliate report.
 * This class schedules when to send the emails and parent class handles the email sending process with asynchronous process.
 *
 * @package     affiliate-for-woocommerce/includes/
 * @since       7.5.0
 * @version     2.1.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'AFWC_Background_Process', false ) ) {
	include_once AFWC_PLUGIN_DIRPATH . '/includes/abstracts/class-afwc-background-process.php';
}

if ( ! class_exists( 'AFWC_Report_Background_Emailer' ) && class_exists( 'AFWC_Background_Process' ) ) {

	/**
	 * Class for Report Background Emailer.
	 */
	class AFWC_Report_Background_Emailer extends AFWC_Background_Process {

		/**
		 * Variable to hold email schedule action name.
		 *
		 * @var string $schedule_action
		 */
		public $schedule_action = 'afwc_schedule_summary_emails';

		/**
		 * Variable to hold instance of this class.
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get the single instance of this class
		 *
		 * @return AFWC_Report_Background_Emailer
		 */
		public static function get_instance() {
			// Check if the instance already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			// Set the action name.
			$this->action = 'afwc_send_summary_emails';

			// Trigger to schedule the action based on setting update.
			add_action( 'updated_option', array( $this, 'trigger_email_scheduler' ), 99, 3 );
			// Try attempting schedule the action if not scheduled in action scheduler.
			add_action( 'init', array( $this, 'attempt_schedule_emailer' ) );

			// Register to run the background process when triggered scheduled action.
			add_action( $this->schedule_action, array( $this, 'run_scheduled_action' ) );

			// Handle failed action.
			add_action( 'action_scheduler_failed_action', array( $this, 'restart_failed_action' ) );

			// Initialize the parent class to execute background process.
			parent::__construct();
		}

		/**
		 * Trigger the background process based on email setting.
		 *
		 * @param  string $option         The option key.
		 * @param  array  $old_values     The old option values.
		 * @param  array  $new_values     The new option values.
		 *
		 * @return void.
		 */
		public function trigger_email_scheduler( $option = '', $old_values = array(), $new_values = array() ) {

			// We have to keep the below option name based on summary report email ID.
			if ( empty( $option ) || 'woocommerce_afwc_summary_email_reports_settings' !== $option ) {
				return;
			}

			if ( is_array( $new_values ) && ! empty( $new_values['enabled'] ) && 'yes' === $new_values['enabled'] ) {
				$this->schedule_emailer();
			} else {
				$this->unschedule_emailer();
			}
		}

		/**
		 * Method to to attempt schedule emailer incase the next As could not scheduled.
		 * It will help to schedule the action if the setting is enabled but the site is logged after 3-4 month or
		 * incase deleted all the action from the action scheduler.
		 */
		public function attempt_schedule_emailer() {
			if ( $this->is_enabled() ) {
				$this->schedule_emailer();
			}
		}

		/**
		 * Schedule the process.
		 */
		public function schedule_emailer() {
			if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_single_action' ) || as_has_scheduled_action( $this->schedule_action ) ) {
				return;
			}

			$schedule_timestamp = $this->get_as_timestamp();

			if ( ! empty( $schedule_timestamp ) ) {
				// Schedule the affiliate summary report emails if not scheduled.
				as_schedule_single_action( $schedule_timestamp, $this->schedule_action, array(), $this->group );
			}
		}

		/**
		 * Unschedule the process.
		 */
		public function unschedule_emailer() {
			if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_unschedule_action' ) || ! as_has_scheduled_action( $this->schedule_action ) ) {
				return;
			}
			as_unschedule_action( $this->schedule_action );
		}

		/**
		 * Run the process.
		 */
		public function run_scheduled_action() {

			// Do not initiate the task if email is disabled.
			if ( ! $this->is_enabled() ) {
				return;
			}
			// Call parent's init function to start the sending email process.
			$this->init();
		}

		/**
		 * Callback method to execute the sending of summary emails.
		 *
		 * @param array $affiliates The affiliate list.
		 *
		 * @throws Exception If any problem during the process.
		 */
		public function task( $affiliates = array() ) {

			if ( ! is_array( $affiliates ) || empty( $affiliates ) ) {
				throw new Exception(
					sprintf(
						/* translators: 1: The current task class name */
						_x( 'Data are not available to run task in %s', 'Error message for data unavailable', 'affiliate-for-woocommerce' )
					),
					__CLASS__
				);
			}

			foreach ( $affiliates as $affiliate_id ) {

				$affiliate_id = intval( $affiliate_id );
				// Trigger the email.
				$result = $this->trigger_email(
					array(
						'affiliate_id' => $affiliate_id,
						'date_range'   => $this->get_date_range_for_summary(),
					)
				);

				if ( empty( $result ) ) {
					Affiliate_For_WooCommerce::get_instance()->log(
						'warning',
						sprintf(
							/* translators: 1: The affiliate IDs */
							_x( 'AFWC summary email report failed to send for Affiliate ID: %d', 'Logger for summary email report', 'affiliate-for-woocommerce' ),
							$affiliate_id
						)
					);
				} else {
					Affiliate_For_WooCommerce::get_instance()->log(
						'info',
						sprintf(
							/* translators: 1: The affiliate IDs */
							_x( 'AFWC summary email report sent successfully for Affiliate ID: %d', 'Logger for summary email report', 'affiliate-for-woocommerce' ),
							$affiliate_id
						)
					);
				}

				// If health status is not good, stop the batch.
				if ( ! $this->health_status() ) {
					throw new Exception(
						sprintf(
							/* translators: 1: The affiliate ID 2: The task class name */
							_x( 'Batch stopped due to health status after Affiliate ID : %1$d in task: %2$s', 'Logger for batch stopped due to health status', 'affiliate-for-woocommerce' ),
							$affiliate_id,
							__CLASS__
						)
					);
				}
			}
		}

		/**
		 * Get the remaining items(affiliate IDs) for doing the action.
		 *
		 * @return array The array of affiliate IDs.
		 */
		public function get_remaining_items() {
			global $wpdb;

			$limit        = $this->get_batch_limit();
			$limit        = ! empty( $limit ) ? intval( $limit ) : -1;
			$current_date = afwc_get_current_date(); // Get the site's current date.

			if ( $limit > 0 ) {
				$user_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT DISTINCT user.ID
						FROM {$wpdb->prefix}usermeta AS um1
							JOIN {$wpdb->prefix}users AS user ON um1.user_id = user.ID
							LEFT JOIN {$wpdb->prefix}usermeta um2 ON um1.user_id = um2.user_id AND um2.meta_key = %s
						WHERE um1.meta_key = %s
							AND um1.meta_value = %s
							AND (um2.meta_key IS NULL OR um2.meta_value != %s)
						LIMIT %d",
						'afwc_last_summary_email_date',
						'afwc_is_affiliate',
						'yes',
						$current_date,
						$limit
					)
				);
			} else {
				$user_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT DISTINCT user.ID
						FROM {$wpdb->prefix}usermeta AS um1
							JOIN {$wpdb->prefix}users AS user ON um1.user_id = user.ID
							LEFT JOIN {$wpdb->prefix}usermeta AS um2 ON um1.user_id = um2.user_id AND um2.meta_key = %s
						WHERE um1.meta_key = %s
							AND um1.meta_value = %s
							AND (um2.meta_key IS NULL OR um2.meta_value != %s)",
						'afwc_last_summary_email_date',
						'afwc_is_affiliate',
						'yes',
						$current_date
					)
				);
			}

			$user_count = is_array( $user_ids ) ? count( $user_ids ) : 0;

			if ( ( $limit < 0 ) || ( $user_count < $limit ) ) {
				$affiliate_roles = get_option( 'affiliate_users_roles', array() );

				if ( empty( $affiliate_roles ) ) {
					return $user_ids;
				}

				$additional_affiliates = get_users(
					array(
						'role__in'   => $affiliate_roles,
						'fields'     => 'ID',
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'relation' => 'AND',
							array(
								'key'     => 'afwc_is_affiliate',
								'compare' => 'NOT EXISTS',
							),
							array(
								'relation' => 'OR',
								array(
									'key'     => 'afwc_last_summary_email_date',
									'compare' => 'NOT EXISTS',
								),
								array(
									'key'     => 'afwc_last_summary_email_date',
									'value'   => $current_date,
									'type'    => 'DATE',
									'compare' => '!=',
								),
							),
						),
						'number'     => ( $limit >= 0 ) ? ( $limit - $user_count ) : $limit,
					)
				);
				if ( ! empty( $additional_affiliates ) && is_array( $additional_affiliates ) ) {
					$user_ids = array_merge( $user_ids, $additional_affiliates );
				}
			}

			return ! empty( $user_ids ) && is_array( $user_ids ) ? array_map( 'intval', $user_ids ) : array();
		}

		/**
		 * Trigger when completed the process.
		 */
		public function completed() {
			parent::completed();
			$this->schedule_emailer();
		}

		/**
		 * Restart the process to re-schedule again if it fails anyway.
		 *
		 * @param  int $action_id Id of the failed action.
		 *
		 * @return void.
		 */
		public function restart_failed_action( $action_id = 0 ) {
			if ( empty( $action_id ) || ! is_callable( 'ActionScheduler', 'store' ) ) {
				return;
			}

			$scheduler = ActionScheduler::store();

			if ( ! is_callable( $scheduler, 'fetch_action' ) ) {
				return;
			}

			$action = $scheduler->fetch_action( $action_id );

			if ( empty( $action ) || ! is_object( $action ) || ! is_callable( array( $action, 'get_hook' ) ) ) {
				return;
			}
			$action_hook = $action->get_hook();

			if ( empty( $action_hook ) ) {
				return;
			}

			// Restart the task if the failed action ID matches current schedule action.
			if ( $this->schedule_action === $action_hook ) {
				$this->run_scheduled_action();
			}
		}

		/**
		 * Check the email status.
		 *
		 * @return bool Return true if required email is enabled otherwise false.
		 */
		public function is_enabled() {
			return class_exists( 'AFWC_Emails' )
				&& is_callable( array( 'AFWC_Emails', 'is_afwc_mailer_enabled' ) )
				&& true === AFWC_Emails::is_afwc_mailer_enabled( 'afwc_email_affiliate_summary_reports' );
		}

		/**
		 * Trigger the email.
		 *
		 * @param array $data The data for email.
		 *
		 * @return bool Return true if successfully sent otherwise false.
		 */
		public function trigger_email( $data = array() ) {

			if ( ! $this->is_enabled() || empty( $data['affiliate_id'] ) ) {
				return false;
			}

			// Trigger email.
			do_action( 'afwc_email_affiliate_summary_reports', $data );
			// Update the last email date in affiliate user meta.
			return update_user_meta( intval( $data['affiliate_id'] ), 'afwc_last_summary_email_date', afwc_get_current_date() );
		}

		/**
		 * Get the data range for summary.
		 * Currently it returns the date range of this month.
		 *
		 * @return array Return the array of date range(From date and To date)
		 */
		public function get_date_range_for_summary() {
			$offset_timestamp = is_callable( array( 'Affiliate_For_WooCommerce', 'get_offset_timestamp' ) ) ? intval( Affiliate_For_WooCommerce::get_offset_timestamp() ) : 0;
			$format           = 'd-m-Y';
			// Get the first day of the previous month.
			$first_day_previous_month = gmdate( $format, mktime( 0, 0, 0, gmdate( 'n', $offset_timestamp ) - 1, 1, gmdate( 'Y', $offset_timestamp ) ) );

			// Get the last day of the previous month.
			$last_day_previous_month = gmdate( $format, mktime( 0, 0, 0, gmdate( 'n', $offset_timestamp ), 0, gmdate( 'Y', $offset_timestamp ) ) );

			return array(
				'from' => $first_day_previous_month . ' 00:00:00',   // From date: the start date of the previous month.
				'to'   => $last_day_previous_month . ' 23:59:59', // To date: the end date of the previous month.
			);
		}

		/**
		 * Get the timestamp for action scheduler to start the action.
		 *
		 * @return int Return the timestamp.
		 */
		private function get_as_timestamp() {
			$gmt_timestamp = $this->get_timestamp();

			if ( empty( $gmt_timestamp ) ) {
				return 0;
			}

			$gmt_offset = is_callable( array( 'Affiliate_For_WooCommerce', 'get_gmt_offset' ) ) ? intval( Affiliate_For_WooCommerce::get_gmt_offset() ) : 0;
			// Adjust the timestamp to start the action with store timezone.
			return intval( $gmt_timestamp ) - $gmt_offset;
		}

		/**
		 * Get the GMT timestamp to start the action.
		 *
		 * @return int Return the timestamp for midnight of the first day of the next month.
		 */
		public function get_timestamp() {
			return intval( strtotime( 'midnight first day of +1 month' ) );
		}

	}
}

// Auto initialize the class.
if ( class_exists( 'AFWC_Report_Background_Emailer' ) && is_callable( array( 'AFWC_Report_Background_Emailer', 'get_instance' ) ) ) {
	AFWC_Report_Background_Emailer::get_instance();
}
