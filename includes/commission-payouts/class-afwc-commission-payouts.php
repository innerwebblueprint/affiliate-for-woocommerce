<?php
/**
 * Class to get outstanding commissions for affiliates
 *
 * @package   affiliate-for-woocommerce/includes/commission-payouts/
 * @since     8.0.0
 * @version   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Commission_Payouts' ) ) {

	/**
	 * Main class to get outstanding commissions for affiliates payout
	 */
	class AFWC_Commission_Payouts {

		/**
		 * Variable to hold instance of this class
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Commission_Payouts Singleton object of this class
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
		 * Function to get unpaid/outstanding commission payouts.
		 *
		 * @param int   $affiliate_user_id The user_id of the affiliate.
		 * @param array $args              Array of arguments.
		 *
		 * @return array Outstanding commissions based on affiliate.
		 */
		public function get_outstanding_commission_payouts( $affiliate_user_id = 0, $args = array() ) {
			if ( empty( $args ) || ! is_array( $args ) ) {
				return;
			}

			$request_type = ( ! empty( $args['request_type'] ) ) ? $args['request_type'] : '';
			if ( empty( $request_type ) ) {
				return;
			}

			$minimum_payout_amount = get_option( 'afwc_minimum_commission_balance', 50 );
			$maximum_payout_amount = get_option( 'afwc_maximum_commission_balance', 0 );
			$refund_period_window  = get_option( 'afwc_order_refund_period_in_days', 30 );

			global $wpdb;

			$temp_option_key     = 'afwc_order_status_' . uniqid();
			$paid_order_statuses = afwc_get_paid_order_status();
			update_option( $temp_option_key, implode( ',', $paid_order_statuses ), 'no' );

			if ( 'pending_payouts_dashboard' === $request_type ) {
				$get_outstanding_commissions = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT
																		affiliate_id,
																		MAX(total_commission_amount) as total_commission_amount,
																		GROUP_CONCAT(DISTINCT order_id) as order_ids,
																		GROUP_CONCAT(DISTINCT referral_id) as referral_ids,
																		MIN(datetime) as from_date,
																		MAX(datetime) as to_date
																	FROM
																		(
																		SELECT
																			@total_commission AS total_commission,
																			@total_commission := CASE WHEN @affiliate_id != t.affiliate_id THEN 0 ELSE @total_commission END,
																			@affiliate_id := t.affiliate_id as affiliate_id,
																			@valid_orders := (CASE WHEN t.amount > 0 THEN 1 ELSE 0 END),
																			@total_commission := @total_commission + (CASE WHEN @valid_orders = 1 THEN t.amount ELSE 0 END) AS total_commission_amount,
																			(CASE WHEN @valid_orders = 1 THEN t.post_id ELSE 0 END) AS order_id,
																			(CASE WHEN @valid_orders = 1 THEN t.referral_id ELSE 0 END) AS referral_id,
																			(CASE WHEN @valid_orders = 1 THEN t.datetime ELSE 0 END) AS datetime																	
																		FROM
																			(SELECT *
																				FROM
																					{$wpdb->prefix}afwc_referrals
																				WHERE
																					status = %s
																					AND currency_id = %s
																					AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																															FROM {$wpdb->prefix}options
																															WHERE option_name = %s ) )
																				ORDER BY affiliate_id
																			) as t,
																			(SELECT @total_commission := 0,
																				@affiliate_id := 0,
																				@valid_orders := 0
																			) as temp_variable
																		GROUP BY affiliate_id, t.amount, order_id, referral_id, datetime
																		HAVING order_id > 0
																				AND referral_id > 0
																				AND datetime > 0
																		) as temp
																	GROUP BY affiliate_id",
						AFWC_REFERRAL_STATUS_UNPAID,
						AFWC_CURRENCY_CODE,
						AFWC_SQL_CHARSET,
						AFWC_SQL_COLLATION,
						AFWC_SQL_CHARSET,
						AFWC_SQL_COLLATION,
						$temp_option_key
					),
					'ARRAY_A'
				);
			} elseif ( 'process_automatic_payouts' === $request_type ) {
				if ( empty( $affiliate_user_id ) ) {
					if ( ! empty( $refund_period_window ) ) {
						if ( ! empty( $maximum_payout_amount ) ) {
							$get_outstanding_commissions = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT
																					affiliate_id,
																					MAX(total_commission_amount) as total_commission_amount,
																					GROUP_CONCAT(DISTINCT order_id) as order_ids,
																					GROUP_CONCAT(DISTINCT referral_id) as referral_ids,
																					MIN(datetime) as from_date,
																					MAX(datetime) as to_date
																				FROM
																					(
																					SELECT
																						@total_commission AS total_commission,
																						@total_commission := CASE WHEN @affiliate_id != t.affiliate_id THEN 0 ELSE @total_commission END,
																						@affiliate_id := t.affiliate_id as affiliate_id,
																						@valid_orders := (CASE WHEN t.amount > 0 AND @total_commission <= %d AND @total_commission + t.amount <= %d THEN 1 ELSE 0 END),
																						@total_commission := @total_commission + (CASE WHEN @valid_orders = 1 THEN t.amount ELSE 0 END) AS total_commission_amount,
																						(CASE WHEN @valid_orders = 1 THEN t.post_id ELSE 0 END) AS order_id,
																						(CASE WHEN @valid_orders = 1 THEN t.referral_id ELSE 0 END) AS referral_id,
																						(CASE WHEN @valid_orders = 1 THEN t.datetime ELSE 0 END) AS datetime																	
																					FROM
																						(SELECT *
																							FROM
																								{$wpdb->prefix}afwc_referrals
																							WHERE
																								status = %s
																								AND currency_id = %s
																								AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																																		FROM {$wpdb->prefix}options
																																		WHERE option_name = %s ) )
																								AND datetime <= DATE_SUB(CURDATE(), INTERVAL %d DAY)
																							ORDER BY affiliate_id
																						) as t,
																						(SELECT @total_commission := 0,
																							@affiliate_id := 0,
																							@valid_orders := 0
																						) as temp_variable
																					GROUP BY affiliate_id, t.amount, order_id, referral_id, datetime
																					HAVING order_id > 0
																							AND referral_id > 0
																							AND datetime > 0
																					) as temp
																				GROUP BY affiliate_id
																				HAVING total_commission_amount >= %d",
									$maximum_payout_amount,
									$maximum_payout_amount,
									AFWC_REFERRAL_STATUS_UNPAID,
									AFWC_CURRENCY_CODE,
									AFWC_SQL_CHARSET,
									AFWC_SQL_COLLATION,
									AFWC_SQL_CHARSET,
									AFWC_SQL_COLLATION,
									$temp_option_key,
									$refund_period_window,
									$minimum_payout_amount
								),
								'ARRAY_A'
							);
						} else {
							$get_outstanding_commissions = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT
																					affiliate_id,
																					MAX(total_commission_amount) as total_commission_amount,
																					GROUP_CONCAT(DISTINCT order_id) as order_ids,
																					GROUP_CONCAT(DISTINCT referral_id) as referral_ids,
																					MIN(datetime) as from_date,
																					MAX(datetime) as to_date
																				FROM
																					(
																					SELECT
																						@total_commission AS total_commission,
																						@total_commission := CASE WHEN @affiliate_id != t.affiliate_id THEN 0 ELSE @total_commission END,
																						@affiliate_id := t.affiliate_id as affiliate_id,
																						@valid_orders := (CASE WHEN t.amount > 0 THEN 1 ELSE 0 END),
																						@total_commission := @total_commission + (CASE WHEN @valid_orders = 1 THEN t.amount ELSE 0 END) AS total_commission_amount,
																						(CASE WHEN @valid_orders = 1 THEN t.post_id ELSE 0 END) AS order_id,
																						(CASE WHEN @valid_orders = 1 THEN t.referral_id ELSE 0 END) AS referral_id,
																						(CASE WHEN @valid_orders = 1 THEN t.datetime ELSE 0 END) AS datetime																	
																					FROM
																						(SELECT *
																							FROM
																								{$wpdb->prefix}afwc_referrals
																							WHERE
																								status = %s
																								AND currency_id = %s
																								AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																																		FROM {$wpdb->prefix}options
																																		WHERE option_name = %s ) )
																								AND datetime <= DATE_SUB(CURDATE(), INTERVAL %d DAY)
																							ORDER BY affiliate_id
																						) as t,
																						(SELECT @total_commission := 0,
																							@affiliate_id := 0,
																							@valid_orders := 0
																						) as temp_variable
																					GROUP BY affiliate_id, t.amount, order_id, referral_id, datetime
																					HAVING order_id > 0
																							AND referral_id > 0
																							AND datetime > 0
																					) as temp
																				GROUP BY affiliate_id
																				HAVING total_commission_amount >= %d",
									AFWC_REFERRAL_STATUS_UNPAID,
									AFWC_CURRENCY_CODE,
									AFWC_SQL_CHARSET,
									AFWC_SQL_COLLATION,
									AFWC_SQL_CHARSET,
									AFWC_SQL_COLLATION,
									$temp_option_key,
									$refund_period_window,
									$minimum_payout_amount
								),
								'ARRAY_A'
							);
						}
					} else {
						if ( ! empty( $maximum_payout_amount ) ) {
							$get_outstanding_commissions = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT
																				affiliate_id,
																				MAX(total_commission_amount) as total_commission_amount,
																				GROUP_CONCAT(DISTINCT order_id) as order_ids,
																				GROUP_CONCAT(DISTINCT referral_id) as referral_ids,
																				MIN(datetime) as from_date,
																				MAX(datetime) as to_date
																			FROM
																				(
																				SELECT
																					@total_commission AS total_commission,
																					@total_commission := CASE WHEN @affiliate_id != t.affiliate_id THEN 0 ELSE @total_commission END,
																					@affiliate_id := t.affiliate_id as affiliate_id,
																					@valid_orders := (CASE WHEN t.amount > 0 AND @total_commission <= %d AND @total_commission + t.amount <= %d THEN 1 ELSE 0 END),
																					@total_commission := @total_commission + (CASE WHEN @valid_orders = 1 THEN t.amount ELSE 0 END) AS total_commission_amount,
																					(CASE WHEN @valid_orders = 1 THEN t.post_id ELSE 0 END) AS order_id,
																					(CASE WHEN @valid_orders = 1 THEN t.referral_id ELSE 0 END) AS referral_id,
																					(CASE WHEN @valid_orders = 1 THEN t.datetime ELSE 0 END) AS datetime
																				FROM
																					(SELECT *
																						FROM
																							{$wpdb->prefix}afwc_referrals
																						WHERE
																							status = %s
																							AND currency_id = %s
																							AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																																	FROM {$wpdb->prefix}options
																																	WHERE option_name = %s ) )
																						ORDER BY affiliate_id
																					) as t,
																					(SELECT @total_commission := 0,
																						@affiliate_id := 0,
																						 @valid_orders := 0
																					) as temp_variable
																				GROUP BY affiliate_id, t.amount, order_id, referral_id, datetime
																				HAVING order_id > 0
																						AND referral_id > 0
																						AND datetime > 0
																				) as temp
																			GROUP BY affiliate_id
																			HAVING total_commission_amount >= %d",
									$maximum_payout_amount,
									$maximum_payout_amount,
									AFWC_REFERRAL_STATUS_UNPAID,
									AFWC_CURRENCY_CODE,
									AFWC_SQL_CHARSET,
									AFWC_SQL_COLLATION,
									AFWC_SQL_CHARSET,
									AFWC_SQL_COLLATION,
									$temp_option_key,
									$minimum_payout_amount
								),
								'ARRAY_A'
							);
						} else {
							$get_outstanding_commissions = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT
																				affiliate_id,
																				MAX(total_commission_amount) as total_commission_amount,
																				GROUP_CONCAT(DISTINCT order_id) as order_ids,
																				GROUP_CONCAT(DISTINCT referral_id) as referral_ids,
																				MIN(datetime) as from_date,
																				MAX(datetime) as to_date
																			FROM
																				(
																				SELECT
																					@total_commission AS total_commission,
																					@total_commission := CASE WHEN @affiliate_id != t.affiliate_id THEN 0 ELSE @total_commission END,
																					@affiliate_id := t.affiliate_id as affiliate_id,
																					@valid_orders := (CASE WHEN t.amount > 0 THEN 1 ELSE 0 END),
																					@total_commission := @total_commission + (CASE WHEN @valid_orders = 1 THEN t.amount ELSE 0 END) AS total_commission_amount,
																					(CASE WHEN @valid_orders = 1 THEN t.post_id ELSE 0 END) AS order_id,
																					(CASE WHEN @valid_orders = 1 THEN t.referral_id ELSE 0 END) AS referral_id,
																					(CASE WHEN @valid_orders = 1 THEN t.datetime ELSE 0 END) AS datetime
																				FROM
																					(SELECT *
																						FROM
																							{$wpdb->prefix}afwc_referrals
																						WHERE
																							status = %s
																							AND currency_id = %s
																							AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																																	FROM {$wpdb->prefix}options
																																	WHERE option_name = %s ) )
																						ORDER BY affiliate_id
																					) as t,
																					(SELECT @total_commission := 0,
																						@affiliate_id := 0,
																						 @valid_orders := 0
																					) as temp_variable
																				GROUP BY affiliate_id, t.amount, order_id, referral_id, datetime
																				HAVING order_id > 0
																						AND referral_id > 0
																						AND datetime > 0
																				) as temp
																			GROUP BY affiliate_id
																			HAVING total_commission_amount >= %d",
									AFWC_REFERRAL_STATUS_UNPAID,
									AFWC_CURRENCY_CODE,
									AFWC_SQL_CHARSET,
									AFWC_SQL_COLLATION,
									AFWC_SQL_CHARSET,
									AFWC_SQL_COLLATION,
									$temp_option_key,
									$minimum_payout_amount
								),
								'ARRAY_A'
							);
						}
					}
				} else {
					// we have affiliate_ID, so fetch records for them.
					if ( ! empty( $refund_period_window ) ) {
						if ( ! empty( $maximum_payout_amount ) ) {
							$get_outstanding_commissions = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT
																				affiliate_id,
																				MAX(total_commission_amount) as total_commission_amount,
																				GROUP_CONCAT(DISTINCT order_id) as order_ids,
																				GROUP_CONCAT(DISTINCT referral_id) as referral_ids,
																				MIN(datetime) as from_date,
																				MAX(datetime) as to_date
																			FROM
																				(
																				SELECT
																					@total_commission AS total_commission,
																					@total_commission := CASE WHEN @affiliate_id != t.affiliate_id THEN 0 ELSE @total_commission END,
																					@affiliate_id := t.affiliate_id as affiliate_id,
																					@valid_orders := (CASE WHEN t.amount > 0 AND @total_commission <= %d AND @total_commission + t.amount <= %d THEN 1 ELSE 0 END),
																					@total_commission := @total_commission + (CASE WHEN @valid_orders = 1 THEN t.amount ELSE 0 END) AS total_commission_amount,
																					(CASE WHEN @valid_orders = 1 THEN t.post_id ELSE 0 END) AS order_id,
																					(CASE WHEN @valid_orders = 1 THEN t.referral_id ELSE 0 END) AS referral_id,
																					(CASE WHEN @valid_orders = 1 THEN t.datetime ELSE 0 END) AS datetime
																				FROM
																					(SELECT *
																						FROM
																							{$wpdb->prefix}afwc_referrals
																						WHERE
																							affiliate_id = %d
																							AND status = %s
																							AND currency_id = %s
																							AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																																	FROM {$wpdb->prefix}options
																																	WHERE option_name = %s ) )
																							AND datetime <= DATE_SUB(CURDATE(), INTERVAL %d DAY)
																						ORDER BY affiliate_id
																					) as t,
																					(SELECT @total_commission := 0,
																						@affiliate_id := 0,
																						@valid_orders := 0
																					) as temp_variable
																				GROUP BY affiliate_id, t.amount, order_id, referral_id, datetime
																				HAVING order_id > 0
																						AND referral_id > 0
																						AND datetime > 0
																				) as temp
																			GROUP BY affiliate_id
																			HAVING total_commission_amount >= %d",
									$maximum_payout_amount,
									$maximum_payout_amount,
									$affiliate_user_id,
									AFWC_REFERRAL_STATUS_UNPAID,
									AFWC_CURRENCY_CODE,
									AFWC_SQL_CHARSET,
									AFWC_SQL_COLLATION,
									AFWC_SQL_CHARSET,
									AFWC_SQL_COLLATION,
									$temp_option_key,
									$refund_period_window,
									$minimum_payout_amount
								),
								'ARRAY_A'
							);
						} else {
							$get_outstanding_commissions = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT
																					affiliate_id,
																					MAX(total_commission_amount) as total_commission_amount,
																					GROUP_CONCAT(DISTINCT order_id) as order_ids,
																					GROUP_CONCAT(DISTINCT referral_id) as referral_ids,
																					MIN(datetime) as from_date,
																					MAX(datetime) as to_date
																				FROM
																					(
																					SELECT
																						@total_commission AS total_commission,
																						@total_commission := CASE WHEN @affiliate_id != t.affiliate_id THEN 0 ELSE @total_commission END,
																						@affiliate_id := t.affiliate_id as affiliate_id,
																						@valid_orders := (CASE WHEN t.amount > 0 THEN 1 ELSE 0 END),
																						@total_commission := @total_commission + (CASE WHEN @valid_orders = 1 THEN t.amount ELSE 0 END) AS total_commission_amount,
																						(CASE WHEN @valid_orders = 1 THEN t.post_id ELSE 0 END) AS order_id,
																						(CASE WHEN @valid_orders = 1 THEN t.referral_id ELSE 0 END) AS referral_id,
																						(CASE WHEN @valid_orders = 1 THEN t.datetime ELSE 0 END) AS datetime
																					FROM
																						(SELECT *
																							FROM
																								{$wpdb->prefix}afwc_referrals
																							WHERE
																								affiliate_id = %d
																								AND status = %s
																								AND currency_id = %s
																								AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																																		FROM {$wpdb->prefix}options
																																		WHERE option_name = %s ) )
																								AND datetime <= DATE_SUB(CURDATE(), INTERVAL %d DAY)
																							ORDER BY affiliate_id
																						) as t,
																						(SELECT @total_commission := 0,
																							@affiliate_id := 0,
																							@valid_orders := 0
																						) as temp_variable
																					GROUP BY affiliate_id, t.amount, order_id, referral_id, datetime
																					HAVING order_id > 0
																							AND referral_id > 0
																							AND datetime > 0
																					) as temp
																				GROUP BY affiliate_id
																				HAVING total_commission_amount >= %d",
									$affiliate_user_id,
									AFWC_REFERRAL_STATUS_UNPAID,
									AFWC_CURRENCY_CODE,
									AFWC_SQL_CHARSET,
									AFWC_SQL_COLLATION,
									AFWC_SQL_CHARSET,
									AFWC_SQL_COLLATION,
									$temp_option_key,
									$refund_period_window,
									$minimum_payout_amount
								),
								'ARRAY_A'
							);
						}
					} else {
						$get_outstanding_commissions = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->prepare(
								"SELECT
																			affiliate_id,
																			MAX(total_commission_amount) as total_commission_amount,
																			GROUP_CONCAT(DISTINCT order_id) as order_ids,
																			GROUP_CONCAT(DISTINCT referral_id) as referral_ids,
																			MIN(datetime) as from_date,
																			MAX(datetime) as to_date
																		FROM
																			(
																			SELECT
																				@total_commission AS total_commission,
																				@total_commission := CASE WHEN @affiliate_id != t.affiliate_id THEN 0 ELSE @total_commission END,
																				@affiliate_id := t.affiliate_id as affiliate_id,
																				@valid_orders := (CASE WHEN t.amount > 0 AND @total_commission <= %d AND @total_commission + t.amount <= %d THEN 1 ELSE 0 END),
																				@total_commission := @total_commission + (CASE WHEN @valid_orders = 1 THEN t.amount ELSE 0 END) AS total_commission_amount,
																				(CASE WHEN @valid_orders = 1 THEN t.post_id ELSE 0 END) AS order_id,
																				(CASE WHEN @valid_orders = 1 THEN t.referral_id ELSE 0 END) AS referral_id,
																				(CASE WHEN @valid_orders = 1 THEN t.datetime ELSE 0 END) AS datetime
																			FROM
																				(SELECT *
																					FROM
																						{$wpdb->prefix}afwc_referrals
																					WHERE
																						affiliate_id = %d
																						AND status = %s
																						AND currency_id = %s
																						AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																																FROM {$wpdb->prefix}options
																																WHERE option_name = %s ) )
																					ORDER BY affiliate_id
																				) as t,
																				(SELECT @total_commission := 0,
																					@affiliate_id := 0,
																					 @valid_orders := 0
																				) as temp_variable
																			GROUP BY affiliate_id, t.amount, order_id, referral_id, datetime
																			HAVING order_id > 0
																					AND referral_id > 0
																					AND datetime > 0
																			) as temp
																		GROUP BY affiliate_id
																		HAVING total_commission_amount >= %d",
								$maximum_payout_amount,
								$maximum_payout_amount,
								$affiliate_user_id,
								AFWC_REFERRAL_STATUS_UNPAID,
								AFWC_CURRENCY_CODE,
								AFWC_SQL_CHARSET,
								AFWC_SQL_COLLATION,
								AFWC_SQL_CHARSET,
								AFWC_SQL_COLLATION,
								$temp_option_key,
								$minimum_payout_amount
							),
							'ARRAY_A'
						);
					}
				}
			}

			delete_option( $temp_option_key );

			return $get_outstanding_commissions;
		}

	}

}

AFWC_Commission_Payouts::get_instance();
