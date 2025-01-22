<?php
/**
 * Main class for Affiliate For WooCommerce Referral
 *
 * @package  affiliate-for-woocommerce/includes/
 * @since    1.10.0
 * @version  1.11.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_API' ) ) {

	/**
	 * Affiliate For WooCommerce Referral
	 */
	class AFWC_API {

		/**
		 * Variable to hold instance of AFWC_API
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of AFWC_API
		 *
		 * @return AFWC_API Singleton object of AFWC_API
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
			/*
			 * Used "woocommerce_checkout_update_order_meta" action instead of "woocommerce_new_order" hook. Because don't get the whole
			 * order data on "woocommerce_new_order" hook.
			 *
			 * Checked woocommerce "includes/class-wc-checkout.php" file and then after use this hook
			 *
			 * @since 7.0.0 Used "woocommerce_checkout_order_processed" action instead of "woocommerce_checkout_update_order_meta" to trigger
			 * the action after subscription is created.
			 *
			 * Track referral before completion of Order with status "Pending"
			 * When Order Completes, Change referral status from Pending to Unpaid
			 */
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_conversion' ), 110 );

			// Support for WooCommerce checkout block.
			if ( is_callable( array( $this, 'is_wc_gte_64' ) ) && $this->is_wc_gte_64() ) {
				add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'track_conversion' ), 110 );
			} elseif ( is_callable( array( $this, 'is_wc_gte_60' ) ) && $this->is_wc_gte_60() ) {
				add_action( 'woocommerce_blocks_checkout_order_processed', array( $this, 'track_conversion' ), 110 );
			}

			if ( class_exists( 'WC_Subscriptions_Core_Plugin' ) || class_exists( 'WC_Subscriptions' ) ) {
				add_filter( 'wcs_renewal_order_created', array( $this, 'handle_renewal_order_created' ) );
				if ( WCS_AFWC_Compatibility::get_instance()->is_wcs_core_gte( '2.5.0' ) ) {
					add_filter( 'wc_subscriptions_renewal_order_data', array( $this, 'do_not_copy_affiliate_meta' ) );
				} else {
					add_filter( 'wcs_renewal_order_meta_query', array( $this, 'do_not_copy_meta' ) );
				}
			}

			// Update referral when order status changes.
			add_action( 'woocommerce_order_status_changed', array( $this, 'update_referral_status' ), 11, 3 );

			add_filter( 'afwc_conversion_data', array( $this, 'get_conversion_data' ) );
		}

		/**
		 * Method to handle WC compatibility related function call from appropriate class
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
		 * Function to track visitor
		 *
		 * @param integer $affiliate_id The affiliate id.
		 * @param integer $visitor_id The visitor_id.
		 * @param string  $source The source of hit.
		 * @param mixed   $params extra params to override default params.
		 *
		 * @return int Return the id of new visitor record if successfully tracked otherwise 0.
		 */
		public function track_visitor( $affiliate_id = 0, $visitor_id = 0, $source = 'link', $params = array() ) {

			if ( empty( $affiliate_id ) ) {
				return 0;
			}

			global $wpdb;

			// prepare vars.
			$current_user_id = get_current_user_id();

			// check type of referral.
			if ( function_exists( 'WC' ) ) {
				$cart = WC()->cart;
				if ( is_object( $cart ) && is_callable( array( $cart, 'is_empty' ) ) && ! $cart->is_empty() ) {
					$afwc         = Affiliate_For_WooCommerce::get_instance();
					$used_coupons = ( is_callable( array( $cart, 'get_applied_coupons' ) ) ) ? $cart->get_applied_coupons() : array();
					if ( ! empty( $used_coupons ) && is_callable( array( $afwc, 'get_referral_type' ) ) ) {
						$source = $afwc->get_referral_type( $affiliate_id, $used_coupons );
					}
				}
			}

			$user_agent = wc_get_user_agent();

			$wpdb->insert( // phpcs:ignore
				$wpdb->prefix . 'afwc_hits',
				array(
					'affiliate_id' => intval( $affiliate_id ),
					'datetime'     => gmdate( 'Y-m-d H:i:s' ),
					'ip'           => is_callable( array( 'WC_Geolocation', 'get_ip_address' ) ) ? WC_Geolocation::get_ip_address() : ( ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) ? wc_clean( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ), // phpcs:ignore
					'user_id'      => ! empty( $current_user_id ) ? $current_user_id : 0,
					'type'         => $source,
					'campaign_id'  => ! empty( $params['campaign_id'] ) ? intval( $params['campaign_id'] ) : 0,
					'user_agent'   => ! empty( $user_agent ) ? $user_agent : '',
					'url'          => afwc_get_current_url(),
				),
				array( '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
			);

			return ! empty( $wpdb->insert_id ) ? $wpdb->insert_id : 0;
		}

		/**
		 * Function to track conversion (referral)
		 *
		 * @param integer|WC_Order $order           WooCommerce order object or order ID.
		 * @param integer          $affiliate_id    The affiliate ID.
		 * @param string           $type            The type of conversion e.g order, pageview etc.
		 * @param mixed            $params          Extra params to override default params.
		 */
		public function track_conversion( $order = 0, $affiliate_id = 0, $type = 'order', $params = array() ) {

			global $wpdb;

			$oid = 0;

			if ( is_object( $order ) && $order instanceof WC_Order ) {
				$oid = is_callable( array( $order, 'get_id' ) ) ? intval( $order->get_id() ) : 0;
			} elseif ( is_numeric( $order ) ) {
				$oid   = intval( $order );
				$order = wc_get_order( $oid );
			}

			if ( 0 !== $oid ) {

				$customer = get_current_user_id();

				if ( empty( $customer ) ) {
					$customer = $order instanceof WC_Order && is_callable( array( $order, 'get_billing_email' ) ) ? $order->get_billing_email() : '';
				}

				$conversion_data['affiliate_id'] = apply_filters(
					'afwc_id_for_order',
					! empty( $affiliate_id ) ? $affiliate_id : afwc_get_referrer_id( $customer ),
					array(
						'order_id' => $oid,
						'source'   => $this,
					)
				);

				$conversion_data['oid']         = $oid;
				$conversion_data['datetime']    = gmdate( 'Y-m-d H:i:s' );
				$conversion_data['description'] = ! empty( $params['description'] ) ? $params['description'] : '';
				$conversion_data['ip']          = ! empty( $params['ip'] ) ? $params['ip'] : ( is_callable( array( 'WC_Geolocation', 'get_ip_address' ) ) ? WC_Geolocation::get_ip_address() : ( ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) ? wc_clean( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) ); // phpcs:ignore
				$conversion_data['params']      = $params;

				$is_valid_for_tracking = $this->is_eligible_for_commission( $oid, $conversion_data['affiliate_id'], $params );
				if ( empty( $is_valid_for_tracking ) ) {
					return;
				}

				$conversion_data = apply_filters( 'afwc_conversion_data', $conversion_data );

				// Return if the affiliate id is empty.
				if ( empty( $conversion_data['affiliate_id'] ) ) {
					return;
				}

				$affiliate = new AFWC_Affiliate( $conversion_data['affiliate_id'] );
				// Check for valid affiliate.
				if ( $affiliate->is_valid() ) {

					// Link the customer for lifetime commission.
					if ( ! is_admin() && ! empty( $customer ) && 'yes' === get_option( 'afwc_enable_lifetime_commissions', 'no' ) ) {
						$affiliate_obj = new AFWC_Affiliate( $conversion_data['affiliate_id'] );
						if ( is_callable( array( $affiliate_obj, 'add_ltc_customer' ) ) ) {
							$affiliate_obj->add_ltc_customer( $customer );
						}
					}

					$values = array(
						'affiliate_id' => intval( $conversion_data['affiliate_id'] ),
						'post_id'      => $conversion_data['oid'],
						'datetime'     => $conversion_data['datetime'],
						'description'  => ! empty( $conversion_data['description'] ) ? $conversion_data['description'] : '',
						'ip'           => $conversion_data['ip'],
						'user_id'      => $conversion_data['user_id'],
						'amount'       => $conversion_data['amount'],
						'currency_id'  => $conversion_data['currency_id'],
						'data'         => ! empty( $conversion_data['data'] ) ? $conversion_data['data'] : '',
						'status'       => $conversion_data['status'],
						'type'         => $conversion_data['type'],
						'reference'    => ! empty( $conversion_data['reference'] ) ? $conversion_data['reference'] : '',
						'campaign_id'  => $conversion_data['campaign_id'],
						'hit_id'       => ! empty( $conversion_data['hit_id'] ) ? intval( $conversion_data['hit_id'] ) : 0,
					);

					$placeholders = array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

					$referral_added = $wpdb->insert( $wpdb->prefix . 'afwc_referrals', $values, $placeholders );  // phpcs:ignore

					$main_referral_id = ! empty( $wpdb->insert_id ) ? intval( $wpdb->insert_id ) : 0;

					// track parent commissions.
					if ( ! empty( $conversion_data['commissions'] ) ) {
						foreach ( $conversion_data['commissions'] as $affiliate_chain_id => $commission_amt ) {
							$values['affiliate_id'] = $affiliate_chain_id;
							$values['amount']       = $commission_amt;
							$values['reference']    = $main_referral_id;
							$referral_added = $wpdb->insert( $wpdb->prefix . 'afwc_referrals', $values, $placeholders ); // phpcs:ignore
						}
					}

					if ( ! empty( $referral_added ) ) {
						$order = wc_get_order( $conversion_data['oid'] );
						if ( $order instanceof WC_Order ) {
							$order->update_meta_data( 'is_commission_recorded', 'yes' );
							$order->save();
						}

						// Send new conversion email to affiliate if enabled.
						if ( true === AFWC_Emails::is_afwc_mailer_enabled( 'afwc_email_new_conversion_received' ) ) {
							// Trigger email.
							do_action(
								'afwc_email_new_conversion_received',
								array(
									'affiliate_id' => $conversion_data['affiliate_id'],
									'order_commission_amount' => $conversion_data['amount'],
									'currency_id'  => $conversion_data['currency_id'],
									'order_id'     => $conversion_data['oid'],
								)
							);
						}

						/**
						* Fires after conversion is successfully tracked.
						*
						* @param array $conversion_data  The conversion data.
						* @param array $this             Instance of this class.
						*/
						do_action(
							'afwc_conversion_tracked',
							array(
								'conversion_data' => $conversion_data,
								'source'          => $this,
							)
						);
					}
				}
			}
		}

		/**
		 * Function to calculate commission
		 *
		 * @param integer $order_id The order id.
		 * @param integer $affiliate_id The affiliate id.
		 * @return array|false $commissions The commission data after calculation.
		 */
		public function calculate_commission( $order_id = 0, $affiliate_id = 0 ) {
			$order         = wc_get_order( $order_id );
			$affiliate_obj = new AFWC_Affiliate( $affiliate_id );

			$plans = new AFWC_Plans( $order, $affiliate_obj );
			return $plans->track_commission();
		}

		/**
		 * Record referral when renewal order created
		 *
		 * @param  WC_Order $renewal_order The renewal order.
		 *
		 * @return WC_Order
		 */
		public function handle_renewal_order_created( $renewal_order = null ) {
			$this->track_conversion( $renewal_order );
			return $renewal_order;
		}

		/**
		 * Record referral
		 *
		 * @param mixed $conversion_data .
		 */
		public function get_conversion_data( $conversion_data ) {
			global $wpdb;

			$order_id = ( ! empty( $conversion_data['oid'] ) ) ? $conversion_data['oid'] : 0;
			if ( empty( $order_id ) ) {
				return $conversion_data;
			}

			$affiliate_id = ( ! empty( $conversion_data['affiliate_id'] ) ) ? $conversion_data['affiliate_id'] : 0;

			// Assign referer's id if affiliate id is empty and order should not be renewal.
			if ( empty( $affiliate_id ) && false === $this->is_wc_subscriptions_renewal_order( $order_id ) ) {
				$affiliate_id = afwc_get_referrer_id();
			}

			// Return if affiliate id is not exists.
			if ( empty( $affiliate_id ) ) {
				return $conversion_data;
			}

			$campaign_id = afwc_get_campaign_id();

			$commissions = $this->calculate_commission( $order_id, $affiliate_id );
			if ( false === $commissions ) {
				// set conversion data affiliate id 0 if commission already recorded.
				$conversion_data['affiliate_id'] = 0;
				return $conversion_data;
			}

			$amount = ( ! empty( $commissions ) && ! empty( $commissions['amount'] ) ) ? $commissions['amount'] : 0;
			unset( $commissions['amount'] );

			$description = '';
			$data        = '';
			$type        = '';
			$reference   = '';

			if ( $affiliate_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order instanceof WC_Order ) {
					return $conversion_data;
				}

				$currency_id  = ( is_callable( array( $order, 'get_currency' ) ) ) ? $order->get_currency() : AFWC_CURRENCY_CODE;
				$user_id      = ( is_callable( array( $order, 'get_customer_id' ) ) ) ? $order->get_customer_id() : 0;
				$afwc         = Affiliate_For_WooCommerce::get_instance();
				$used_coupons = ( is_callable( array( $order, 'get_coupon_codes' ) ) ) ? $order->get_coupon_codes() : array();
				$type         = $afwc->get_referral_type( $affiliate_id, $used_coupons );

				// prepare conversion_data.
				$conversion_data['user_id']      = ! empty( $user_id ) ? $user_id : 0;
				$conversion_data['amount']       = $amount;
				$conversion_data['type']         = $type;
				$conversion_data['status']       = AFWC_REFERRAL_STATUS_DRAFT;
				$conversion_data['reference']    = $reference;
				$conversion_data['data']         = $data;
				$conversion_data['currency_id']  = $currency_id;
				$conversion_data['affiliate_id'] = $affiliate_id;
				$conversion_data['campaign_id']  = $campaign_id;
				$conversion_data['hit_id']       = ! is_admin() ? afwc_get_hit_id() : 0; // To prevent hit_id incorrectly set when admin is manually assigning/unassigning an order.
				$conversion_data['commissions']  = $commissions;
			}

			return $conversion_data;
		}

		/**
		 * Update referral payout status.
		 *
		 * @param int    $order_id The order id.
		 * @param string $old_status Old order status.
		 * @param string $new_status New order status.
		 */
		public function update_referral_status( $order_id = 0, $old_status = '', $new_status = '' ) {
			if ( empty( $order_id ) ) {
				return;
			}

			global $wpdb;

			$order_status_updates = false;
			$wc_paid_statuses     = afwc_get_paid_order_status();
			$reject_statuses      = afwc_get_reject_order_status();

			$new_status = ( strpos( $new_status, 'wc-' ) === false ) ? 'wc-' . $new_status : $new_status;
			$old_status = ( strpos( $old_status, 'wc-' ) === false ) ? 'wc-' . $old_status : $old_status;

			// if order status converts from rejected status to other, then create new entry in referral.
			if (
				( ! empty( $reject_statuses ) && is_array( $reject_statuses ) &&
				in_array( $old_status, $reject_statuses, true ) ) &&
				! in_array( $new_status, $reject_statuses, true )
			) {
				// check if order is recorded in referral and if that is rejected.
				$affiliate_id =  $wpdb->get_var( $wpdb->prepare( "SELECT affiliate_id FROM {$wpdb->prefix}afwc_referrals WHERE post_id = %d AND status = %s ORDER BY referral_id", $order_id, AFWC_REFERRAL_STATUS_REJECTED ) ); // phpcs:ignore
				if ( ! empty( $affiliate_id ) ) {
					// track commission for order.
					$params                 = array();
					$params['force_record'] = true;
					$this->track_conversion( $order_id, $affiliate_id, 'order', $params );
				}
			}

			// update referral if not paid or rejected.
			if ( ! empty( $wc_paid_statuses ) && is_array( $wc_paid_statuses ) && in_array( $new_status, $wc_paid_statuses, true ) ) {

				$status = apply_filters(
					'afwc_commission_status_for_paid_orders',
					AFWC_REFERRAL_STATUS_UNPAID,
					array(
						'order_id' => $order_id,
						'source'   => $this,
					)
				);

				$wpdb->query( // phpcs:ignore
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}afwc_referrals
						SET status = %s, order_status = %s
						WHERE post_id = %d AND status NOT IN (%s, %s)",
						$status,
						$new_status,
						$order_id,
						AFWC_REFERRAL_STATUS_PAID,
						AFWC_REFERRAL_STATUS_REJECTED
					)
				);
				$order_status_updates = true;
			} elseif ( ! empty( $reject_statuses ) && is_array( $reject_statuses ) && in_array( $new_status, $reject_statuses, true ) ) {
				// reject referral if not paid.
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}afwc_referrals SET status = %s, order_status = %s WHERE post_id = %d AND status NOT IN (%s)", AFWC_REFERRAL_STATUS_REJECTED, $new_status, $order_id, AFWC_REFERRAL_STATUS_PAID ) ); // phpcs:ignore
				$order_status_updates = true;
			}

			if ( ! $order_status_updates ) {
				// set new order status in referral table.
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}afwc_referrals SET order_status = %s WHERE post_id = %d", $new_status, $order_id ) ); // phpcs:ignore
			}
		}

		/**
		 * Do not copy few affiliate meta to renewal order.
		 *
		 * @param  mixed $order_meta Order meta.
		 * @return mixed $order_meta
		 */
		public function do_not_copy_affiliate_meta( $order_meta = array() ) {
			if ( isset( $order_meta['is_commission_recorded'] ) ) {
				unset( $order_meta['is_commission_recorded'] );
			}
			if ( isset( $order_meta['afwc_order_valid_plans'] ) ) {
				unset( $order_meta['afwc_order_valid_plans'] );
			}
			if ( isset( $order_meta['afwc_set_commission'] ) ) {
				unset( $order_meta['afwc_set_commission'] );
			}
			if ( isset( $order_meta['afwc_parent_commissions'] ) ) {
				unset( $order_meta['afwc_parent_commissions'] );
			}

			return $order_meta;
		}

		/**
		 * Do not copy few affiliate meta to renewal order.
		 *
		 * @param  mixed $order_meta_query Order items.
		 *
		 * @return mixed $order_meta_query
		 */
		public function do_not_copy_meta( $order_meta_query = '' ) {
			$order_meta_query .= " AND `meta_key` NOT IN ('is_commission_recorded', 'afwc_order_valid_plans', 'afwc_set_commission', 'afwc_parent_commissions')";
			return $order_meta_query;
		}

		/**
		 * Return if the order is a WooCommerce Subscriptions renewal order by WooCommerce order id.
		 *
		 * @param  integer $order_id Order id.
		 *
		 * @return boolean $is_renewal_order
		 */
		public function is_wc_subscriptions_renewal_order( $order_id = 0 ) {
			if ( empty( $order_id ) ) {
				return false;
			}

			// Initialize renewal order.
			$is_renewal_order = false;

			// Check if WooCommerce Subscription plugin is active.
			if ( class_exists( 'WC_Subscriptions_Core_Plugin' ) || class_exists( 'WC_Subscriptions' ) ) {
				$renewal_order    = wc_get_order( $order_id );
				$renewal_order_id = ( is_object( $renewal_order ) && is_callable( array( $renewal_order, 'get_id' ) ) ) ? $renewal_order->get_id() : 0;

				// Check if the order is wc subscriptions renewal order.
				$is_renewal_order = wcs_order_contains_renewal( $renewal_order_id );
			}

			return $is_renewal_order;
		}

		/**
		 * Check if order is valid for affiliate ID.
		 *
		 * @param  integer $order_id     The Order ID.
		 * @param  integer $affiliate_id The original affiliate ID.
		 * @param  mixed   $params       The additional params.
		 * @return boolean $is_valid_order flag
		 */
		public function afwc_is_valid_order( $order_id, $affiliate_id, $params ) {

			$is_valid_order = true;

			if ( empty( $order_id ) ) {
				return false;
			}

			$force_record = ! empty( $params['force_record'] ) ? $params['force_record'] : false;
			if ( true === $force_record ) {
				return true;
			}

			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				return false;
			}

			$is_commission_recorded = $order->get_meta( 'is_commission_recorded', true );
			if ( 'yes' === $is_commission_recorded ) {
				$is_valid_order = false;
			} else {
				global $wpdb;

				// check if commission is already recorded in table but not updated in postmeta.
				$order_count = $wpdb->get_var( // phpcs:ignore
					$wpdb->prepare( // phpcs:ignore
						"SELECT COUNT(post_id)
									FROM {$wpdb->prefix}afwc_referrals
									WHERE post_id = %d AND affiliate_id = %d",
						$order_id,
						$affiliate_id
					)
				);
				if ( $order_count > 0 ) {
					$is_valid_order = false;
				}
			}

			return $is_valid_order;
		}

		/**
		 * Return if self-refer is allowed for the order.
		 *
		 * @param int   $order_id     The Order ID.
		 * @param int   $affiliate_id The Affiliate ID.
		 * @param array $params       The additional params.
		 *
		 * @return bool Return true if the order is eligible for self-refer otherwise false. Default true.
		 */
		public function allow_self_refer_for_order( $order_id = 0, $affiliate_id = 0, $params = array() ) {
			if ( empty( $order_id ) || empty( $affiliate_id ) ) {
				// return the default.
				return true;
			}

			// set true if forced affiliate to be eligible or self-refer is allowed.
			if ( ( ! empty( $params['is_affiliate_eligible'] ) && true === $params['is_affiliate_eligible'] ) || true === afwc_allow_self_refer() ) {
				return true;
			}

			$affiliate_id = intval( $affiliate_id );
			$order_id     = intval( $order_id );
			$order        = wc_get_order( $order_id );

			if ( ! $order instanceof WC_Order ) {
				return true;
			}

			// Check the self refer based on customer ID.
			if ( is_callable( array( $order, 'get_user_id' ) ) ) {
				$customer_id = $order->get_user_id();
				if ( ! empty( $customer_id ) && intval( $customer_id ) === $affiliate_id ) {
					return false;
				}
			}

			// Check the self refer based on customer billing email.
			if ( is_callable( array( $order, 'get_billing_email' ) ) ) {
				$customer_email = $order->get_billing_email();
				if ( ! empty( $customer_email ) ) {
					$affiliate_obj = new AFWC_Affiliate( $affiliate_id );
					if ( ! empty( $affiliate_obj->user_email ) && $customer_email === $affiliate_obj->user_email ) {
						return false;
					}
				}
			}

			return true;
		}

		/**
		 * Return if the order is eligible for commission.
		 *
		 * @param int   $order_id The Order id.
		 * @param int   $affiliate_id The Affiliate id.
		 * @param array $params The Params.
		 *
		 * @return bool Return true whether the order is eligible for commission otherwise false.
		 */
		public function is_eligible_for_commission( $order_id = 0, $affiliate_id = 0, $params = array() ) {
			$is_eligible = false;

			if ( empty( $order_id ) || empty( $affiliate_id ) ) {
				return $is_eligible;
			}

			// TODO: Simplify this code if in the future we want to add more checks.
			if ( true === $this->afwc_is_valid_order( $order_id, $affiliate_id, $params ) && true === $this->allow_self_refer_for_order( $order_id, $affiliate_id, $params ) ) {
				$is_eligible = true;
			}

			/**
			 * Filter for whether order is eligible for commission.
			 *
			 * @param bool  $is_eligible whether eligible for the commission or not.
			 * @param array The params
			 */
			return apply_filters(
				'afwc_is_eligible_for_commission',
				$is_eligible,
				array(
					'order_id'     => $order_id,
					'affiliate_id' => $affiliate_id,
					'source'       => $this,
				)
			);
		}

		/**
		 * Function to get affiliate data based on order_id.
		 *
		 * @param int    $order_id The Order ID.
		 * @param string $data     Whether to get all or selected records.
		 *
		 * @return array Return The array of affiliate id and status of the linked affiliate.
		 */
		public function get_affiliate_by_order( $order_id = 0, $data = '' ) {

			if ( empty( $order_id ) ) {
				return array();
			}

			global $wpdb;

			if ( empty( $data ) ) {
				$affiliate_details = $wpdb->get_row( // phpcs:ignore
					$wpdb->prepare(
						"SELECT affiliate_id, status
							FROM {$wpdb->prefix}afwc_referrals
							WHERE post_id = %d AND reference = ''",
						$order_id
					),
					'ARRAY_A'
				);
			} elseif ( 'all' === $data ) {
				$affiliate_details = $wpdb->get_row( // phpcs:ignore
					$wpdb->prepare(
						"SELECT *
							FROM {$wpdb->prefix}afwc_referrals
							WHERE post_id = %d AND reference = ''",
						$order_id
					),
					'ARRAY_A'
				);
			}

			return ! empty( $affiliate_details ) ? $affiliate_details : array();
		}

		/**
		 * Method to get a list of order IDs for a specific customer based on provided parameters.
		 *
		 * @param array $args Array of parameters.
		 *
		 * @throws Exception If any error during the process.
		 * @return array List of order IDs for the specified customer.
		 */
		public function get_orders_by_customer( $args = array() ) {
			global $wpdb;

			if ( empty( $args ) || ! is_array( $args ) ) {
				return array();
			}

			$customer_id   = ! empty( $args['customer_id'] ) ? intval( $args['customer_id'] ) : 0;
			$billing_email = ! empty( $args['billing_email'] ) ? sanitize_email( $args['billing_email'] ) : '';

			if ( empty( $customer_id ) && empty( $billing_email ) ) {
				return array();
			}

			$orders = array();

			try {
				if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
					// DB Queries for HPOS setup.
					if ( ! empty( $customer_id ) && ! empty( $billing_email ) ) {
						// DB Query if both customer ID and billing email are provided.
						$orders = $wpdb->get_results( // phpcs:ignore
							$wpdb->prepare(
								"SELECT id AS order_id
									FROM {$wpdb->prefix}wc_orders
									WHERE
										type = %s
										AND ( customer_id = %s OR billing_email = %s )",
								'shop_order',
								esc_sql( $customer_id ),
								esc_sql( $billing_email )
							),
							'ARRAY_A'
						);
					} elseif ( ! empty( $customer_id ) ) {
						// DB Queries if only customer ID is provided.
						$orders = $wpdb->get_results( // phpcs:ignore
							$wpdb->prepare(
								"SELECT id AS order_id
									FROM {$wpdb->prefix}wc_orders
									WHERE type = %s AND customer_id = %d",
								'shop_order',
								esc_sql( $customer_id )
							),
							'ARRAY_A'
						);
					} elseif ( ! empty( $billing_email ) ) {
						// DB Queries if only billing email is provided.
						$orders = $wpdb->get_results( // phpcs:ignore
							$wpdb->prepare(
								"SELECT id AS order_id
									FROM {$wpdb->prefix}wc_orders
									WHERE type = %s AND billing_email = %s",
								'shop_order',
								esc_sql( $billing_email )
							),
							'ARRAY_A'
						);
					}
				} else {
					// Queries for non-HPOS setup.
					if ( ! empty( $customer_id ) && ! empty( $billing_email ) ) {
						// DB Query if both customer ID and billing email are provided.
						$orders = $wpdb->get_results( // phpcs:ignore
							$wpdb->prepare(
								"SELECT pm.post_id AS order_id
								FROM {$wpdb->prefix}postmeta AS pm
									INNER JOIN {$wpdb->prefix}posts AS p ON pm.post_id = p.ID
								WHERE
									p.post_type = %s
									AND ( ( pm.meta_key = %s AND pm.meta_value = %d )
										OR ( pm.meta_key = %s AND pm.meta_value = %s )
									)",
								'shop_order',
								'_customer_user',
								esc_sql( $customer_id ),
								'_billing_email',
								esc_sql( $billing_email )
							),
							'ARRAY_A'
						);
					} else {
						// DB Query if any of one value is provided from customer id or billing email.
						$meta_data = array(
							array(
								'key'   => '_customer_user',
								'value' => $customer_id,
							),
							array(
								'key'   => '_billing_email',
								'value' => $billing_email,
							),
						);

						foreach ( $meta_data as $meta ) {
							if ( empty( $meta['value'] ) ) {
								continue;
							}
							$orders = $wpdb->get_results( // phpcs:ignore
								$wpdb->prepare(
									"SELECT pm.post_id AS order_id
									FROM {$wpdb->prefix}postmeta AS pm
										INNER JOIN {$wpdb->prefix}posts AS p ON pm.post_id = p.ID
										WHERE p.post_type = %s
										AND ( pm.meta_key = %s AND pm.meta_value = %s )",
									'shop_order',
									esc_sql( $meta['key'] ),
									esc_sql( $meta['value'] )
								),
								'ARRAY_A'
							);
						}
					}
				}

				// Throw if any error.
				if ( ! empty( $wpdb->last_error ) ) {
					throw new Exception( $wpdb->last_error );
				}
			} catch ( Exception $e ) {
				Affiliate_For_WooCommerce::get_instance()->log_error( __METHOD__, ( is_callable( array( $e, 'getMessage' ) ) ) ? $e->getMessage() : '' );
			}

			// Process the results and return the list of order IDs.
			$order_ids = ! empty( $orders ) && is_array( $orders ) ? array_column( $orders, 'order_id' ) : array();
			return ! empty( $order_ids ) && is_array( $order_ids ) ? array_unique( array_map( 'intval', $order_ids ) ) : array();
		}
	}
}

AFWC_API::get_instance();
