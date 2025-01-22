<?php
/**
 * Main class for Payout.
 *
 * @package    affiliate-for-woocommerce/includes/payouts/
 * @since      6.28.0
 * @version    1.1.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Payout_Handler' ) ) {

	/**
	 * Affiliate Payout Handler class.
	 */
	class AFWC_Payout_Handler {

		/**
		 * Array to hold referral records.
		 *
		 * @var array
		 */
		public $referrals = array();

		/**
		 * Array to hold payout method.
		 *
		 * @var string
		 */
		public $method = '';

		/**
		 * Array to hold payout note.
		 *
		 * @var string
		 */
		public $note = '';

		/**
		 * Array to hold the currency.
		 *
		 * @var string
		 */
		public $currency = '';

		/**
		 * Array to hold payout date.
		 *
		 * @var string
		 */
		public $date = '';

		/**
		 * Variable to check if request is for automatic payout.
		 *
		 * @var boolean
		 */
		public $is_automatic_payout = false;

		/**
		 * Commission amount to be paid, only used for automatic payouts.
		 *
		 * @var float
		 */
		public $amount = 0;

		/**
		 * From date for the referral to be paid, only used for automatic payouts.
		 *
		 * @var float
		 */
		public $from_date = '';

		/**
		 * To date for the referral to be paid, only used for automatic payouts.
		 *
		 * @var float
		 */
		public $to_date = '';

		/**
		 * Constructor.
		 *
		 * @param array $params Initialization parameters.
		 */
		public function __construct( $params = array() ) {

			// Assign the referrals.
			if ( ! empty( $params['referrals'] ) ) {
				$this->referrals = $params['referrals'];
			}

			// Assign the method.
			if ( ! empty( $params['method'] ) ) {
				$this->method = $params['method'];
			}

			// Assign the payout note.
			if ( ! empty( $params['note'] ) ) {
				$this->note = $params['note'];
			}

			// Assign the payout currency.
			if ( ! empty( $params['currency'] ) ) {
				$this->currency = $params['currency'];
			}

			// Assign the payout date.
			if ( ! empty( $params['date'] ) ) {
				$this->date = $params['date'];
			}

			// Assign the type of payout request.
			if ( ! empty( $params['is_automatic_payout'] ) ) {
				$this->is_automatic_payout = ( true === $params['is_automatic_payout'] ) ? true : false;
			}

			if ( true === $this->is_automatic_payout ) {
				$this->amount    = ( ! empty( $params['amount'] ) ) ? $params['amount'] : 0.00;
				$this->from_date = ( ! empty( $params['from_date'] ) ) ? $params['from_date'] : '';
				$this->to_date   = ( ! empty( $params['to_date'] ) ) ? $params['to_date'] : '';
			}
		}

		/**
		 * Main method to processing the payouts.
		 *
		 * @param int $requested_affiliate_id The affiliate ID for processing for the single affiliate ID.
		 *
		 * @return array The response.
		 */
		public function process_payout( $requested_affiliate_id = 0 ) {

			$requested_affiliate_id = absint( $requested_affiliate_id );

			$all_results = array();

			if ( false === $this->is_automatic_payout ) {
				// Group referrals by affiliates.
				$grouped_referrals = $this->group_referrals_by_affiliate();

				if ( empty( $grouped_referrals ) || ! is_array( $grouped_referrals ) ) {
					return $all_results;
				}

				foreach ( $grouped_referrals as $affiliate_id => $referrals ) {

					$affiliate_id = absint( $affiliate_id );

					// Continue the loop if the requested affiliate is not matched with the current affiliate.
					// Continue the loop if the referral record is empty.
					if ( ( ! empty( $requested_affiliate_id ) && $requested_affiliate_id !== $affiliate_id ) || ( empty( $referrals ) || ! is_array( $referrals ) ) ) {
						continue;
					}

					// Format the referral records for processing payout.
					$format_records = $this->format_payout_request( $affiliate_id, $referrals );

					// Process the payout for the current affiliate.
					$payout_result = ! empty( $format_records ) && is_array( $format_records ) ? $this->process_affiliate_payout( $affiliate_id, $format_records ) : array();

					$result = $this->handle_payout_results_for_post_actions( $affiliate_id, $payout_result, $referrals );

					// Assign the current affiliate's payout result.
					$all_results[ $affiliate_id ] = $result;
				}
			} else {
				// abort payout if amount is empty.
				if ( empty( $this->amount ) ) {
					Affiliate_For_WooCommerce::get_instance()->log(
						'error',
						sprintf(
							/* translators: Affiliate ID */
							_x( 'Automatic payout failed as missing amount. Aborting automatic payouts for Affiliate ID: %s.', 'Logger for process payout missing argument', 'affiliate-for-woocommerce' ),
							$requested_affiliate_id
						)
					);
					return;
				}

				// If automatic payout is enabled, format below in similar way as $format_records i.e. argument accepted by function process_affiliate_payout.
				$payout_args   = array(
					'affiliate_id' => $requested_affiliate_id,
					'amount'       => $this->amount,
					'note'         => $this->note,
				);
				$payout_result = $this->process_affiliate_payout( $requested_affiliate_id, $payout_args );

				$result = $this->handle_payout_results_for_post_actions( $requested_affiliate_id, $payout_result, $this->referrals );

				// Assign the current affiliate's payout result.
				$all_results[ $requested_affiliate_id ] = $result;
			}

			// Return the requested affiliate's result if provided otherwise return all affiliate's result.
			return ! empty( $requested_affiliate_id ) ? $all_results[ $requested_affiliate_id ] : $all_results;
		}

		/**
		 * Function to handle post-payout actions.
		 *
		 * @param int   $affiliate_id  The user_id of the affiliate.
		 * @param array $payout_result Array of payout result.
		 * @param array $referrals     Array of referrals for the payout.
		 */
		private function handle_payout_results_for_post_actions( $affiliate_id = 0, $payout_result = array(), $referrals = array() ) {
			$result = array();

			if ( is_array( $payout_result ) && ! empty( $payout_result['success'] ) ) {
				$result = array(
					'success'      => true,
					'batch_id'     => ! empty( $payout_result['batch_id'] ) ? $payout_result['batch_id'] : '',
					'amount'       => ! empty( $payout_result['amount'] ) ? floatval( $payout_result['amount'] ) : 0.00,
					'receiver'     => ! empty( $payout_result['receiver'] ) ? $payout_result['receiver'] : '',
					'affiliate_id' => $affiliate_id,
				);

				// Filter the result by post payout actions.
				$result = $this->post_payout_actions( $result, $referrals );
			} elseif ( is_wp_error( $payout_result ) ) {
				$result = array(
					'success' => false,
					'message' => is_callable( array( $payout_result, 'get_error_message' ) ) ? $payout_result->get_error_message() : _x( 'Payout failed', 'Error message for payout failed', 'affiliate-for-woocommerce' ),
				);
			} else {
				$result = array(
					'success' => false,
					'message' => _x( 'Something went wrong', 'Error message for payout failed', 'affiliate-for-woocommerce' ),
				);
			}

			return $result;
		}

		/**
		 * Format the payout requests.
		 *
		 * @param int   $affiliate_id   The affiliate Id.
		 * @param array $records The records.
		 *
		 * @return array The response.
		 */
		private function format_payout_request( $affiliate_id = 0, $records = array() ) {
			if ( empty( $affiliate_id ) || empty( $records ) || ! is_array( $records ) ) {
				return array();
			}

			$total_commissions = 0;

			foreach ( $records as $record ) {
				$total_commissions += ( ! empty( $record['commission'] ) ? floatval( $record['commission'] ) : 0 );
			}

			return array(
				'affiliate_id' => $affiliate_id,
				'amount'       => $total_commissions,
				'note'         => $this->note,
			);
		}

		/**
		 * Post payout actions.
		 * 1. Update commission status.
		 * 2. Update payout table.
		 * 3. Email for Commission paid.
		 *
		 * @param array $result   The payout result.
		 * @param array $referrals The referral records records.
		 *
		 * @throws Exception When failed to take the action.
		 * @return array The response.
		 */
		public function post_payout_actions( $result = array(), $referrals = array() ) {
			try {
				if ( empty( $result ) || empty( $result['success'] ) || true !== $result['success'] ) {
					throw new Exception( _x( 'Payout is not successful for post payout action.', 'Error message for post payout action failed', 'affiliate-for-woocommerce' ) );
				}

				if ( empty( $result['affiliate_id'] ) ) {
					throw new Exception( _x( 'Affiliate ID missing for post payout actions.', 'Error message for post payout action failed', 'affiliate-for-woocommerce' ) );
				}

				// Update commission status.
				if ( true !== $this->set_commission_status( $referrals ) ) {
					throw new Exception( _x( 'Unable to update commission status.', 'Error message for post payout action failed', 'affiliate-for-woocommerce' ) );
				}

				// Insert payout record in DB.
				$inserted_payout_id = $this->update_payout_result( $referrals, $result );

				if ( ! $inserted_payout_id ) {
					throw new Exception( _x( 'Payout data entry failed into database', 'Error message for post payout action failed', 'affiliate-for-woocommerce' ) );
				}

				// Fetch the dates.
				$dates       = is_array( $referrals ) ? array_column( $referrals, 'date' ) : array();
				$str_dates   = ( ! empty( $dates ) ) ? array_map( 'strtotime', $dates ) : array();
				$date_format = get_option( 'date_format', 'd-m-y' );

				$payout_result = array(
					'success'     => true,
					'payout_data' => array(
						'affiliate_id'   => $result['affiliate_id'],
						'amount'         => ( ! empty( $result['amount'] ) ) ? floatval( $result['amount'] ) : 0.00,
						'currency'       => ! empty( $this->currency ) ? $this->currency : '',
						'datetime'       => ! empty( $this->date ) ? gmdate( 'd-M-Y', strtotime( $this->date ) ) : '',
						// else condition takes care of automatic payouts.
						'from_date'      => ( ! empty( $str_dates ) ) ? gmdate( $date_format, min( $str_dates ) ) : ( ( ! empty( $this->from_date ) ) ? $this->from_date : '' ),
						// else condition takes care of automatic payouts.
						'to_date'        => ( ! empty( $str_dates ) ) ? gmdate( $date_format, max( $str_dates ) ) : ( ( ! empty( $this->to_date ) ) ? $this->to_date : '' ),
						'method'         => ! empty( $this->method ) ? $this->method : '',
						'referral_count' => is_array( $referrals ) && ! empty( $referrals ) ? count( $referrals ) : 0,
						'payout_id'      => $inserted_payout_id,
						'payout_notes'   => ! empty( $this->note ) ? $this->note : '',
						'receiver'       => ! empty( $result['receiver'] ) ? floatval( $result['receiver'] ) : '',
					),
				);

				$this->send_commission_paid_email( $payout_result['payout_data'] );

				do_action( 'afwc_after_payout', $payout_result );

				return $payout_result;
			} catch ( Exception $e ) {
				// Logger.
				if ( is_callable( array( $e, 'getMessage' ) ) ) {
					Affiliate_For_WooCommerce::get_instance()->log( 'error', $e->getMessage() );
				}
				return array(
					'success' => false,
					'message' => _x( 'Payout entry failed', 'Error message for payout entry failed', 'affiliate-for-woocommerce' ),
				);
			}
		}

		/**
		 * Group the referral records by affiliate.
		 *
		 * @return array The updated record.
		 */
		public function group_referrals_by_affiliate() {
			$grouped = array();

			if ( empty( $this->referrals ) || ! is_array( $this->referrals ) ) {
				return $grouped;
			}

			foreach ( $this->referrals as $referral ) {
				if ( empty( $referral['affiliate_id'] ) ) {
					// No affiliate ID found. Continue to the next.
					continue;
				}
				$grouped[ $referral['affiliate_id'] ][] = $referral;
			}

			return $grouped;
		}

		/**
		 * Handle the payout for an individual affiliate.
		 *
		 * @param int   $affiliate_id The affiliate ID.
		 * @param array $records    The payout request records.
		 *
		 * @throws Exception When failed to process the payout.
		 * @return array|WP_Error  The result.
		 */
		private function process_affiliate_payout( $affiliate_id = 0, $records = array() ) {

			try {
				$affiliate_id = absint( $affiliate_id );
				if ( empty( $affiliate_id ) || empty( $records ) || ! is_array( $records ) ) {
					// Requirements are missed for the processing the payout.
					throw new Exception( _x( 'Required parameters are missing for payout', 'Error message for payout failed', 'affiliate-for-woocommerce' ) );
				}

				$payout_method = $this->get_payout_method();

				if ( empty( $payout_method ) || ! is_callable( array( $payout_method, 'execute_payout' ) ) ) {
					// Unsupported payout method.
					throw new Exception( _x( 'Payout method is not supported', 'Error message for payout failed', 'affiliate-for-woocommerce' ) );
				}

				$result = $payout_method->execute_payout(
					array(
						'referrals'    => $records,
						'affiliate_id' => $affiliate_id,
						'currency'     => ! empty( $this->currency ) ? $this->currency : '',
					)
				);

				if ( is_wp_error( $result ) ) {
					// throw the error where the payout is failed. the detailed logger will print on the each method's class.
					/* translators: Payout method */
					throw new Exception( sprintf( _x( 'Payout failed for the method: %s', 'Error message for payout failed', 'affiliate-for-woocommerce' ), ! empty( $this->method ) ? $this->method : '' ) );
				}

				return $result;

			} catch ( Exception $e ) {
				$message = is_callable( array( $e, 'getMessage' ) ) ? $e->getMessage() : '';
				// Logger.
				if ( ! empty( $message ) ) {
					Affiliate_For_WooCommerce::get_instance()->log( 'error', $e->getMessage() );
				}
				return new WP_Error( 'afwc-payout-failed', $message );
			}
		}

		/**
		 * Retrieve the payout method based on its name.
		 *
		 * @return object|null
		 */
		private function get_payout_method() {

			if ( empty( $this->method ) ) {
				return null;
			}

			$payout_method_classes = apply_filters(
				'afwc_payout_classes',
				array(
					'paypal'        => 'AFWC_PayPal_Payout_Method',
					'paypal-manual' => 'AFWC_PayPal_Manual_Payout_Method',
					'stripe'        => 'AFWC_Stripe_Payout_Method',
					'other'         => 'AFWC_Other_Payout_Method',
				)
			);

			if ( empty( $payout_method_classes ) || empty( $payout_method_classes[ $this->method ] ) ) {
				return null;
			}

			$class_name = $payout_method_classes[ $this->method ];

			if ( ! class_exists( $class_name ) ) {
				// Include the file if any method is available inside the plugin.
				$payout_loader = AFWC_PLUGIN_DIRPATH . '/includes/payouts/class-afwc-' . str_replace( '_', '-', $this->method ) . '-payout-method.php';

				if ( file_exists( $payout_loader ) ) {
					include_once $payout_loader;
				}
			}

			// Check the class existence.
			if ( class_exists( $class_name ) ) {
				return new $class_name();
			}

			return null;
		}

		/**
		 * Update the commission status to paid.
		 *
		 * @param array $referrals The referral records.
		 *
		 * @return bool Return true if updated otherwise false.
		 */
		private function set_commission_status( $referrals = array() ) {

			if ( empty( $referrals ) ) {
				return false;
			}

			// Get all order ids.
			$referral_ids = array_map(
				function ( $obj ) {
					if ( ! empty( $obj['referral_id'] ) ) {
						return intval( $obj['referral_id'] );
					}
				},
				$referrals
			);

			if ( ! class_exists( 'AFWC_Admin_Dashboard' ) ) {
				$loader = include_once AFWC_PLUGIN_DIRPATH . '/includes/admin/class-afwc-admin-dashboard.php';

				if ( file_exists( $loader ) ) {
					include_once $loader;
				}
			}

			if ( class_exists( 'AFWC_Admin_Dashboard' ) ) {
				$admin_dashboard = AFWC_Admin_Dashboard::get_instance();
				// set commission status.
				return is_callable( array( $admin_dashboard, 'set_commission_status' ) ) ? (bool) $admin_dashboard->set_commission_status(
					array(
						'status'       => AFWC_REFERRAL_STATUS_PAID,
						'referral_ids' => $referral_ids,
					)
				) : false;
			}

			return false;
		}

		/**
		 * Update the payout result in the database.
		 * 1. payout_table
		 * 2. payout_orders_table
		 *
		 * @param array $referrals The referral records.
		 * @param array $payout_result The payout results records.
		 *
		 * @return bool Return true if updated otherwise false.
		 */
		private function update_payout_result( $referrals = array(), $payout_result = array() ) {

			if ( empty( $referrals ) || empty( $payout_result ) ) {
				return false;
			}

			$payout_details = array(
				'affiliate_id'    => ! empty( $payout_result['affiliate_id'] ) ? absint( $payout_result['affiliate_id'] ) : 0,
				'datetime'        => get_gmt_from_date( $this->date, 'Y-m-d H:i:s' ),
				'amount'          => floatval( ! empty( $payout_result['amount'] ) ? floatval( $payout_result['amount'] ) : 0.00 ),
				'currency'        => ! empty( $this->currency ) ? $this->currency : '',
				'payout_notes'    => ! empty( $this->note ) ? $this->note : '',
				'payment_gateway' => ! empty( $this->method ) ? $this->method : '',
				'receiver'        => ( ! empty( $payout_result['receiver'] ) ) ? $payout_result['receiver'] : '',
			);

			// Insert the records in the payout table.
			$insert_id = $this->update_payout_table( $payout_details );

			if ( false === $insert_id ) {
				return false;
			}

			// Insert the records in the payout order table to update the orders.
			return $this->update_payout_order_table( $insert_id, $referrals ) ? $insert_id : false;
		}

		/**
		 * Insert payout details.
		 *
		 * @param array $payout_details Payout details.
		 *
		 * @return int|bool Last inserted Id in the table if success otherwise false on error.
		 */
		public function update_payout_table( $payout_details = array() ) {

			global $wpdb;

			try {
				if ( empty( $payout_details ) || ! is_array( $payout_details ) ) {
					return false;
				}

				$records = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prefix . 'afwc_payouts',
					wp_parse_args(
						$payout_details,
						array(
							'affiliate_id'    => 0,
							'datetime'        => '',
							'amount'          => 0,
							'currency'        => '',
							'payout_notes'    => '',
							'payment_gateway' => 'other',
							'receiver'        => '',
							'type'            => '',
						)
					),
					array( '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
				);

				return is_wp_error( $records ) ? false : ( ! empty( $wpdb->insert_id ) ? intval( $wpdb->insert_id ) : false );
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * Insert payout details into payout order table.
		 *
		 * @param int   $payout_id Payout insert ID.
		 * @param array $referrals Payout details.
		 *
		 * @return int|bool Number of rows affected or boolean false on error.
		 */
		public function update_payout_order_table( $payout_id = 0, $referrals = array() ) {

			global $wpdb;

			try {

				foreach ( $referrals as $referral ) {

					if ( empty( $referral['order_id'] ) ) {
						continue;
					}

					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->prefix . 'afwc_payout_orders',
						array(
							'payout_id' => $payout_id,
							'post_id'   => absint( $referral['order_id'] ),
							'amount'    => ! empty( $referral['commission'] ) ? $referral['commission'] : 0,
						),
						array( '%d', '%d', '%f' )
					);
				}

				return true;
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * Send the email to affiliate for commission payout paid.
		 *
		 * @param array $data The Payout data.
		 *
		 * @return void.
		 */
		public function send_commission_paid_email( $data = array() ) {

			// Send commission paid email to affiliate if enabled.
			if ( true === AFWC_Emails::is_afwc_mailer_enabled( 'afwc_email_commission_paid' ) ) {
				// Trigger email.
				do_action(
					'afwc_email_commission_paid',
					array(
						'affiliate_id'          => ! empty( $data['affiliate_id'] ) ? $data['affiliate_id'] : 0,
						'amount'                => ! empty( $data['amount'] ) ? floatval( $data['amount'] ) : 0.00,
						'currency_id'           => ! empty( $data['currency'] ) ? $data['currency'] : '',
						'from_date'             => ! empty( $data['from_date'] ) ? $data['from_date'] : '',
						'to_date'               => ! empty( $data['to_date'] ) ? $data['to_date'] : '',
						'total_referrals'       => ! empty( $data['referral_count'] ) ? $data['referral_count'] : '',
						'payout_notes'          => ! empty( $data['payout_notes'] ) ? $data['payout_notes'] : '',
						'payout_method'         => ! empty( $data['method'] ) ? $data['method'] : '',
						'paypal_receiver_email' => ( ! empty( $data['receiver'] ) ) ? $data['receiver'] : '',
					)
				);
			}
		}
	}
}
