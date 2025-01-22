<?php
/**
 * Main class for Affiliates Admin
 *
 * @package     affiliate-for-woocommerce/includes/admin/
 * @version     1.13.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Admin_Affiliates' ) ) {

	/**
	 * Main class for Affiliates Admin
	 */
	class AFWC_Admin_Affiliates {

		/**
		 * Variable to hold affiliate ids
		 *
		 * @var array $affiliate_ids
		 */
		public $affiliate_ids = array();

		/**
		 * From date
		 *
		 * @var string $from
		 */
		public $from = '';

		/**
		 * To date
		 *
		 * @var string $to
		 */
		public $to = '';

		/**
		 * Sales post types
		 *
		 * @var array $sales_post_types
		 */
		public $sales_post_types = array();

		/**
		 * Net affiliates sales
		 *
		 * @var float $net_affiliates_sales
		 */
		public $net_affiliates_sales = 0;

		/**
		 * Unpaid commissions
		 *
		 * @var float $unpaid_commissions
		 */
		public $unpaid_commissions = 0;

		/**
		 * Visitors count
		 *
		 * @var int $visitors_count
		 */
		public $visitors_count = 0;

		/**
		 * Customers count
		 *
		 * @var int $customers_count
		 */
		public $customers_count = 0;

		/**
		 * Paid commissions
		 *
		 * @var float $paid_commissions
		 */
		public $paid_commissions = 0;

		/**
		 * Commissions earned
		 *
		 * @var float $earned_commissions
		 */
		public $earned_commissions = 0;

		/**
		 * Formatted join duration
		 *
		 * @var string $formatted_join_duration
		 */
		public $formatted_join_duration = '';

		/**
		 * Affiliates display names
		 *
		 * @var array $affiliates_display_names
		 */
		public $affiliates_display_names = array();

		/**
		 * Batch limit
		 *
		 * @var int $batch_limit
		 */
		public $batch_limit = 0;

		/**
		 * Affiliates referrals details
		 *
		 * @var array $affiliates_referrals
		 */
		public $affiliates_referrals = array();

		/**
		 * Variable to hold paid order status.
		 *
		 * @var array $paid_order_status
		 */
		public $paid_order_status = array();

		/**
		 * Start limit
		 *
		 * @var int $start_limit
		 */
		public $start_limit = 0;

		/**
		 * Affiliates details
		 *
		 * @var array $affiliates_details
		 */
		public $affiliates_details = array();

		/**
		 * Gross commissions
		 *
		 * @var float $gross_commissions
		 */
		public $gross_commissions = 0;

		/**
		 * Constructor
		 *
		 * @param  array  $affiliate_ids Affiliates ids.
		 * @param  string $from From date.
		 * @param  string $to To date.
		 * @param  int    $page Current page for batch.
		 */
		public function __construct( $affiliate_ids = array(), $from = '', $to = '', $page = 1 ) {
			$this->affiliate_ids     = ( ! is_array( $affiliate_ids ) ) ? array( $affiliate_ids ) : $affiliate_ids;
			$this->from              = ( ! empty( $from ) ) ? gmdate( 'Y-m-d H:i:s', strtotime( $from ) ) : '';
			$this->to                = ( ! empty( $to ) ) ? gmdate( 'Y-m-d H:i:s', strtotime( $to ) ) : '';
			$this->sales_post_types  = apply_filters( 'afwc_sales_post_types', array( 'shop_order' ) );
			$this->batch_limit       = $this->get_batch_limit();
			$this->start_limit       = ( ! empty( $page ) ) ? ( intval( $page ) - 1 ) * intval( $this->batch_limit ) : 0;
			$this->paid_order_status = afwc_get_prefixed_order_statuses();
		}

		/**
		 * Function to get batch limit per page load.
		 *
		 * @return int
		 */
		public function get_batch_limit() {
			$batch_limit = apply_filters( 'afwc_admin_affiliate_details_limit_per_page', intval( get_option( 'afwc_admin_affiliate_details_limit_per_page', AFWC_ADMIN_DASHBOARD_DEFAULT_BATCH_LIMIT ) ) );
			return ! empty( $batch_limit ) ? intval( $batch_limit ) : 0;
		}

		/**
		 * Function to call all functions to get all data.
		 *
		 * @return void
		 */
		public function get_all_data() {
			$this->net_affiliates_sales    = $this->get_net_affiliates_sales();
			$aggregated                    = $this->get_commissions_customers();
			$this->paid_commissions        = floatval( ( ! empty( $aggregated['paid_commissions'] ) ) ? $aggregated['paid_commissions'] : 0 );
			$this->unpaid_commissions      = floatval( ( ! empty( $aggregated['unpaid_commissions'] ) ) ? $aggregated['unpaid_commissions'] : 0 );
			$this->customers_count         = intval( ( ! empty( $aggregated['customers_count'] ) ) ? $aggregated['customers_count'] : 0 );
			$this->visitors_count          = $this->get_visitors_count();
			$this->earned_commissions      = $this->get_earned_commissions();
			$this->formatted_join_duration = $this->get_formatted_join_duration();
			$this->affiliates_details      = $this->get_affiliates_details();
			$this->gross_commissions       = floatval( ( ! empty( $aggregated['gross_commissions'] ) ) ? $aggregated['gross_commissions'] : 0 );
		}

		/**
		 * Method to get storewide sales.
		 *
		 * @throws Exception If any error during the DB query execution.
		 * @return float Return the store wide sales based on available parameters(post types and date range).
		 */
		public function get_storewide_sales() {
			if ( empty( $this->paid_order_status ) || ! is_array( $this->paid_order_status ) ) {
				// Return if paid order status are not provided.
				return 0;
			}

			global $wpdb;

			$storewide_sales = 0;

			try {
				if ( ! empty( $this->sales_post_types ) && is_array( $this->sales_post_types ) ) {
					// Block for sales post types are provided.

					if ( 1 === count( $this->sales_post_types ) ) {
						// Block for single sales post type is provided.

						if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
							// Block for single sales post type and date range is provided.

							if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
								// Block for single sales post type and date range is provided for HPOS query.
								$storewide_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT IFNULL(SUM( total_amount ), 0)
											FROM {$wpdb->prefix}wc_orders
										WHERE type = %s
											AND status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
											AND date_created_gmt BETWEEN %s AND %s',
										array_merge(
											array(
												current( $this->sales_post_types ),
											),
											$this->paid_order_status,
											array(
												$this->from,
												$this->to,
											)
										)
									)
								);
							} else {
								// Block for single sales post type and date range is provided for non-HPOS query.
								$storewide_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT IFNULL(SUM( pm.meta_value ), 0)
											FROM {$wpdb->prefix}posts AS posts
										JOIN {$wpdb->prefix}postmeta AS pm
											ON (
												posts.ID = pm.post_id 
												AND posts.post_type = %s
												AND pm.meta_key = '_order_total'
											)
										WHERE posts.post_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
											AND posts.post_date_gmt BETWEEN %s AND %s',
										array_merge(
											array(
												current( $this->sales_post_types ),
											),
											$this->paid_order_status,
											array(
												$this->from,
												$this->to,
											)
										)
									)
								);
							}
						} elseif ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
							// Block for single sales post type but no date range is provided for HPOS query.
							$storewide_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT IFNULL(SUM( total_amount ), 0)
										FROM {$wpdb->prefix}wc_orders
									WHERE type = %s
										AND status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')',
									array_merge(
										array(
											current( $this->sales_post_types ),
										),
										$this->paid_order_status
									)
								)
							);
						} else {
							// Block for single sales post type but no date range is provided for non-HPOS query.
							$storewide_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT IFNULL(SUM( pm.meta_value ), 0)
										FROM {$wpdb->prefix}posts AS posts
									JOIN {$wpdb->prefix}postmeta AS pm
										ON (
											posts.ID = pm.post_id 
											AND posts.post_type = %s
											AND pm.meta_key = '_order_total'
										)
									WHERE posts.post_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')',
									array_merge(
										array(
											current( $this->sales_post_types ),
										),
										$this->paid_order_status
									)
								)
							);
						}
					} else {
						// Block for multiple sales post type.
						if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
							// Block for multiple sales post type and date range are provided.

							if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
								// Block for multiple sales post type and date range are provided for HPOS query.
								$storewide_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT IFNULL(SUM( total_amount ), 0)
											FROM {$wpdb->prefix}wc_orders
										WHERE type IN (" . implode( ',', array_fill( 0, count( $this->sales_post_types ), '%s' ) ) . ')
											AND status IN (' . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
											AND date_created_gmt BETWEEN %s AND %s',
										array_merge(
											$this->sales_post_types,
											$this->paid_order_status,
											array(
												$this->from,
												$this->to,
											)
										)
									)
								);
							} else {
								// Block for multiple sales post type and date range are provided for non-HPOS query.
								$storewide_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT IFNULL(SUM( pm.meta_value ), 0)
											FROM {$wpdb->prefix}posts AS posts
										JOIN {$wpdb->prefix}postmeta AS pm
											ON (
												posts.ID = pm.post_id 
												AND posts.post_type IN (" . implode( ',', array_fill( 0, count( $this->sales_post_types ), '%s' ) ) . ")
												AND pm.meta_key = '_order_total'
											)
										WHERE posts.post_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
											AND posts.post_date_gmt BETWEEN %s AND %s',
										array_merge(
											$this->sales_post_types,
											$this->paid_order_status,
											array(
												$this->from,
												$this->to,
											)
										)
									)
								);
							}
						} elseif ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
							// Block for multiple sales post type but no date range are provided for HPOS query.
							$storewide_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT IFNULL(SUM( total_amount ), 0)
										FROM {$wpdb->prefix}wc_orders
									WHERE type IN (" . implode( ',', array_fill( 0, count( $this->sales_post_types ), '%s' ) ) . ')
										AND status IN (' . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')',
									array_merge(
										$this->sales_post_types,
										$this->paid_order_status
									)
								)
							);
						} else {
							// Block for multiple sales post type but no date range are provided for non-HPOS query.
							$storewide_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT IFNULL(SUM( pm.meta_value ), 0)
										FROM {$wpdb->prefix}posts AS posts
									JOIN {$wpdb->prefix}postmeta AS pm
										ON (
											posts.ID = pm.post_id 
											AND posts.post_type IN (" . implode( ',', array_fill( 0, count( $this->sales_post_types ), '%s' ) ) . ")
											AND pm.meta_key = '_order_total'
										)
									WHERE posts.post_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')',
									array_merge(
										$this->sales_post_types,
										$this->paid_order_status
									)
								)
							);
						}
					}
				} elseif ( ! empty( $this->from ) && ! empty( $this->to ) ) {
					// Block for no sales post type but date range are provided.

					if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
						// Block for no sales post type but date range are provided for HPOS query.
						$storewide_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->prepare(
								"SELECT IFNULL(SUM( total_amount ), 0)
									FROM {$wpdb->prefix}wc_orders
								WHERE status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
									AND date_created_gmt BETWEEN %s AND %s',
								array_merge(
									$this->paid_order_status,
									array(
										$this->from,
										$this->to,
									)
								)
							)
						);
					} else {
						// Block for no sales post type but date range are provided for non-HPOS query.
						$storewide_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->prepare(
								"SELECT IFNULL(SUM( pm.meta_value ), 0)
									FROM {$wpdb->prefix}posts AS posts
								JOIN {$wpdb->prefix}postmeta AS pm
									ON (
										posts.ID = pm.post_id
										AND pm.meta_key = '_order_total'
									)
								WHERE posts.post_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
									AND posts.post_date_gmt BETWEEN %s AND %s',
								array_merge(
									$this->paid_order_status,
									array(
										$this->from,
										$this->to,
									)
								)
							)
						);
					}
				} elseif ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
					// Block for no sales post type and no date range are provided for HPOS query.
					$storewide_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare(
							"SELECT IFNULL(SUM( total_amount ), 0)
								FROM {$wpdb->prefix}wc_orders
							WHERE status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')',
							$this->paid_order_status
						)
					);
				} else {
					// Block for no sales post type and no date range are provided for non-HPOS query.
					$storewide_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare(
							"SELECT IFNULL(SUM( pm.meta_value ), 0)
								FROM {$wpdb->prefix}posts AS posts
							JOIN {$wpdb->prefix}postmeta AS pm
								ON (
									posts.ID = pm.post_id 
									AND pm.meta_key = '_order_total'
								)
							WHERE posts.post_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')',
							$this->paid_order_status
						)
					);
				}

				// Throw if any error.
				if ( is_wp_error( $storewide_sales ) ) {
					throw new Exception( is_callable( array( $storewide_sales, 'get_error_message' ) ) ? $storewide_sales->get_error_message() : '' );
				}
			} catch ( Exception $e ) {
				Affiliate_For_WooCommerce::get_instance()->log_error( __METHOD__, ( is_callable( array( $e, 'getMessage' ) ) ) ? $e->getMessage() : '' );
			}

			return floatval( ! empty( $storewide_sales ) ? $storewide_sales : 0 );
		}

		/**
		 * Method to get net affiliates sales
		 *
		 * @throws Exception If any error during the DB query execution.
		 * @return float $net_affiliates_sales net affiliates sales
		 */
		public function get_net_affiliates_sales() {
			if ( empty( $this->paid_order_status ) || ! is_array( $this->paid_order_status ) ) {
				// Return if paid order status are not provided.
				return 0;
			}

			global $wpdb;

			$net_sales  = 0;
			$ref_status = array( 'paid', 'unpaid', 'rejected' );

			try {
				if ( ! empty( $this->affiliate_ids ) && is_array( $this->affiliate_ids ) ) {
					// Block for affiliate IDs are provided.

					if ( 1 === count( $this->affiliate_ids ) ) {
						// Block for single affiliate ID is provided.

						if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
							// Block for single affiliate ID and date range are provided.

							if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
								// Block for single affiliate ID and date range are provided for HPOS query.
								$net_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT IFNULL(SUM(ord.total_amount), 0)
											FROM {$wpdb->prefix}afwc_referrals AS ref
										JOIN {$wpdb->prefix}wc_orders AS ord
											ON (
												ref.post_id = ord.ID
												AND ref.affiliate_id = %d
											)
										WHERE ref.order_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
											AND ref.status IN (' . implode( ',', array_fill( 0, count( $ref_status ), '%s' ) ) . ')
											AND ref.datetime BETWEEN %s AND %s',
										array_merge(
											array(
												current( $this->affiliate_ids ),
											),
											$this->paid_order_status,
											$ref_status,
											array(
												$this->from,
												$this->to,
											)
										)
									)
								);
							} else {
								// Block for single affiliate ID and date range provided for non-HPOS query.
								$net_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT IFNULL(SUM(pm.meta_value), 0)
											FROM {$wpdb->prefix}afwc_referrals AS ref
										JOIN {$wpdb->postmeta} AS pm
											ON (
												ref.post_id = pm.post_id
												AND pm.meta_key = '_order_total'
												AND ref.affiliate_id = %d
											)
										WHERE ref.order_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
											AND ref.status IN (' . implode( ',', array_fill( 0, count( $ref_status ), '%s' ) ) . ')
											AND ref.datetime BETWEEN %s AND %s',
										array_merge(
											array(
												current( $this->affiliate_ids ),
											),
											$this->paid_order_status,
											$ref_status,
											array(
												$this->from,
												$this->to,
											)
										)
									)
								);
							}
						} else {
							// Block for single affiliate ID but no date range provided.

							if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
								// Block for single affiliate ID but no date range provided for HPOS query.
								$net_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT IFNULL(SUM(ord.total_amount), 0)
											FROM {$wpdb->prefix}afwc_referrals AS ref
										JOIN {$wpdb->prefix}wc_orders AS ord
											ON (
												ref.post_id = ord.ID
												AND ref.affiliate_id = %d
											)
										WHERE ref.order_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
											AND ref.status IN (' . implode( ',', array_fill( 0, count( $ref_status ), '%s' ) ) . ')',
										array_merge(
											array(
												current( $this->affiliate_ids ),
											),
											$this->paid_order_status,
											$ref_status
										)
									)
								);
							} else {
								// Block for single affiliate ID but no date range provided for non-HPOS query.
								$net_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT IFNULL(SUM(pm.meta_value), 0)
											FROM {$wpdb->prefix}afwc_referrals AS ref
										JOIN {$wpdb->postmeta} AS pm
											ON (
												ref.post_id = pm.post_id
												AND pm.meta_key = '_order_total'
												AND ref.affiliate_id = %d
											)
										WHERE ref.order_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
											AND ref.status IN (' . implode( ',', array_fill( 0, count( $ref_status ), '%s' ) ) . ')',
										array_merge(
											array(
												current( $this->affiliate_ids ),
											),
											$this->paid_order_status,
											$ref_status
										)
									)
								);
							}
						}
					} else {
						// Block for multiple affiliate IDs are provided.

						if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
							// Block for multiple affiliate IDs and date range are provided.

							if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
								// Block for multiple affiliate IDs and date range provided for HPOS query.
								$net_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT IFNULL(SUM(ord.total_amount), 0)
											FROM (
												SELECT DISTINCT ref.post_id
													FROM {$wpdb->prefix}afwc_referrals AS ref
												WHERE ref.order_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
													AND ref.status IN (' . implode( ',', array_fill( 0, count( $ref_status ), '%s' ) ) . ')
													AND ref.affiliate_id IN (' . implode( ',', array_fill( 0, count( $this->affiliate_ids ), '%d' ) ) . ")
													AND ref.datetime BETWEEN %s AND %s
											) AS distinct_orders
										JOIN {$wpdb->prefix}wc_orders AS ord
											ON (
												distinct_orders.post_id = ord.ID
											)",
										array_merge(
											$this->paid_order_status,
											$ref_status,
											$this->affiliate_ids,
											array(
												$this->from,
												$this->to,
											)
										)
									)
								);
							} else {
								// Block for multiple affiliate IDs and date range provided for non-HPOS query.
								$net_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT IFNULL(SUM(pm.meta_value), 0)
											FROM (
												SELECT DISTINCT ref.post_id
													FROM {$wpdb->prefix}afwc_referrals AS ref
												WHERE ref.order_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
													AND ref.status IN (' . implode( ',', array_fill( 0, count( $ref_status ), '%s' ) ) . ')
													AND ref.affiliate_id IN (' . implode( ',', array_fill( 0, count( $this->affiliate_ids ), '%d' ) ) . ")
													AND ref.datetime BETWEEN %s AND %s
											) AS distinct_orders
										JOIN {$wpdb->prefix}postmeta AS pm
											ON (
												distinct_orders.post_id = pm.post_id
												AND pm.meta_key = '_order_total'
											)",
										array_merge(
											$this->paid_order_status,
											$ref_status,
											$this->affiliate_ids,
											array(
												$this->from,
												$this->to,
											)
										)
									)
								);
							}
						} else {
							// Block for multiple affiliate IDs but no date range are provided.

							if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
								// Block for multiple affiliate IDs but no date range are provided for HPOS query.
								$net_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT IFNULL(SUM(ord.total_amount), 0)
											FROM (
												SELECT DISTINCT ref.post_id
													FROM {$wpdb->prefix}afwc_referrals AS ref
												WHERE ref.order_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
													AND ref.status IN (' . implode( ',', array_fill( 0, count( $ref_status ), '%s' ) ) . ')
													AND ref.affiliate_id IN (' . implode( ',', array_fill( 0, count( $this->affiliate_ids ), '%d' ) ) . ")
											) AS distinct_orders
										JOIN {$wpdb->prefix}wc_orders AS ord
											ON (
												distinct_orders.post_id = ord.ID
											)",
										array_merge(
											$this->paid_order_status,
											$ref_status,
											$this->affiliate_ids
										)
									)
								);
							} else {
								// Block for multiple affiliate IDs but no date range are provided for non-HPOS query.
								$net_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT IFNULL(SUM(pm.meta_value), 0)
											FROM (
												SELECT DISTINCT ref.post_id
													FROM {$wpdb->prefix}afwc_referrals AS ref
												WHERE ref.order_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
													AND ref.status IN (' . implode( ',', array_fill( 0, count( $ref_status ), '%s' ) ) . ')
													AND ref.affiliate_id IN (' . implode( ',', array_fill( 0, count( $this->affiliate_ids ), '%d' ) ) . ")
											) AS distinct_orders
										JOIN {$wpdb->prefix}postmeta AS pm
											ON (
												distinct_orders.post_id = pm.post_id
												AND pm.meta_key = '_order_total'
											)",
										array_merge(
											$this->paid_order_status,
											$ref_status,
											$this->affiliate_ids
										)
									)
								);
							}
						}
					}
				} else {
					// Block for no affiliate IDs provided.

					if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
						// Block for no affiliate IDs but date range are provided.

						if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
							// Block for no affiliate IDs but date range are provided for HPOS query.
							$net_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT IFNULL(SUM(ord.total_amount), 0)
										FROM (
											SELECT DISTINCT ref.post_id
												FROM {$wpdb->prefix}afwc_referrals AS ref
											WHERE ref.order_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
												AND ref.status IN (' . implode( ',', array_fill( 0, count( $ref_status ), '%s' ) ) . ")
												AND ref.datetime BETWEEN %s AND %s
										) AS distinct_orders
									JOIN {$wpdb->prefix}wc_orders AS ord
										ON (
											distinct_orders.post_id = ord.ID
										)",
									array_merge(
										$this->paid_order_status,
										$ref_status,
										array(
											$this->from,
											$this->to,
										)
									)
								)
							);
						} else {
							// Block for no affiliate IDs but date range are provided for non-HPOS query.
							$net_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT IFNULL(SUM(pm.meta_value), 0)
										FROM (
											SELECT DISTINCT ref.post_id
												FROM {$wpdb->prefix}afwc_referrals AS ref
											WHERE ref.order_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
												AND ref.status IN (' . implode( ',', array_fill( 0, count( $ref_status ), '%s' ) ) . ")
												AND ref.datetime BETWEEN %s AND %s
										) AS distinct_orders
									JOIN {$wpdb->prefix}postmeta AS pm
										ON (
											distinct_orders.post_id = pm.post_id
											AND pm.meta_key = '_order_total'
										)",
									array_merge(
										$this->paid_order_status,
										$ref_status,
										array(
											$this->from,
											$this->to,
										)
									)
								)
							);
						}
					} else {
						// Block for no affiliate IDs and date range are provided.

						if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
							// Block for no affiliate IDs and date range are provided for HPOS query.
							$net_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT IFNULL(SUM(ord.total_amount), 0)
										FROM (
											SELECT DISTINCT ref.post_id
												FROM {$wpdb->prefix}afwc_referrals AS ref
											WHERE ref.order_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
												AND ref.status IN (' . implode( ',', array_fill( 0, count( $ref_status ), '%s' ) ) . ")
										) AS distinct_orders
									JOIN {$wpdb->prefix}wc_orders AS ord
										ON (
											distinct_orders.post_id = ord.ID
										)",
									array_merge(
										$this->paid_order_status,
										$ref_status
									)
								)
							);
						} else {
							// Block for no affiliate IDs and date range are provided for non-HPOS query.
							$net_sales = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"SELECT IFNULL(SUM(pm.meta_value), 0)
										FROM (
											SELECT DISTINCT ref.post_id
												FROM {$wpdb->prefix}afwc_referrals AS ref
											WHERE ref.order_status IN (" . implode( ',', array_fill( 0, count( $this->paid_order_status ), '%s' ) ) . ')
												AND ref.status IN (' . implode( ',', array_fill( 0, count( $ref_status ), '%s' ) ) . ")
										) AS distinct_orders
									JOIN {$wpdb->prefix}postmeta AS pm
										ON (
											distinct_orders.post_id = pm.post_id
											AND pm.meta_key = '_order_total'
										)",
									array_merge(
										$this->paid_order_status,
										$ref_status
									)
								)
							);
						}
					}
				}

				// Throw if any error.
				if ( is_wp_error( $net_sales ) ) {
					throw new Exception( is_callable( array( $net_sales, 'get_error_message' ) ) ? $net_sales->get_error_message() : '' );
				}
			} catch ( Exception $e ) {
				Affiliate_For_WooCommerce::get_instance()->log_error( __METHOD__, ( is_callable( array( $e, 'getMessage' ) ) ) ? $e->getMessage() : '' );
			}

			return floatval( ! empty( $net_sales ) ? $net_sales : 0 );
		}

		/**
		 * Function to get visitors count
		 *
		 * @return int $visitors_count visitors count
		 */
		public function get_visitors_count() {
			global $wpdb;

			// If no affiliates, get total visitors count from all affiliates
			// If more than one affiliates, get total visitors count from all those affiliates.
			if ( ! empty( $this->affiliate_ids ) ) {
				if ( 1 === count( $this->affiliate_ids ) ) {

					if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
						$visitors_count = $wpdb->get_var( // phpcs:ignore
													$wpdb->prepare( // phpcs:ignore
														"SELECT IFNULL(COUNT( DISTINCT CONCAT_WS( ':', ip, user_id ) ), 0)
																	FROM {$wpdb->prefix}afwc_hits
																	WHERE affiliate_id = %d
																		AND datetime BETWEEN %s AND %s",
														current( $this->affiliate_ids ),
														$this->from,
														$this->to
													)
						);
					} else {
						$visitors_count = $wpdb->get_var( // phpcs:ignore
													$wpdb->prepare( // phpcs:ignore
														"SELECT IFNULL(COUNT( DISTINCT CONCAT_WS( ':', ip, user_id ) ), 0)
																	FROM {$wpdb->prefix}afwc_hits
																	WHERE affiliate_id = %d",
														current( $this->affiliate_ids )
													)
						);
					}
				} else {
					$option_nm = 'afwc_hits_affiliate_ids_' . uniqid();
					update_option( $option_nm, implode( ',', $this->affiliate_ids ), 'no' );

					if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
						$visitors_count = $wpdb->get_var( // phpcs:ignore
													$wpdb->prepare( // phpcs:ignore
														"SELECT IFNULL(COUNT( DISTINCT CONCAT_WS( ':', ip, user_id ) ), 0)
																	FROM {$wpdb->prefix}afwc_hits
																	WHERE FIND_IN_SET ( affiliate_id, ( SELECT option_value
																									FROM {$wpdb->prefix}options
																									WHERE option_name = %s ) )
																		AND datetime BETWEEN %s AND %s",
														$option_nm,
														$this->from,
														$this->to
													)
						);
					} else {
						$visitors_count = $wpdb->get_var( // phpcs:ignore
													$wpdb->prepare( // phpcs:ignore
														"SELECT IFNULL(COUNT( DISTINCT CONCAT_WS( ':', ip, user_id ) ), 0)
																	FROM {$wpdb->prefix}afwc_hits
																	WHERE FIND_IN_SET ( affiliate_id, ( SELECT option_value
																									FROM {$wpdb->prefix}options
																									WHERE option_name = %s ) )",
														$option_nm
													)
						);
					}

					delete_option( $option_nm );
				}
			} elseif ( ! empty( $this->from ) && ! empty( $this->to ) ) {

					$visitors_count = $wpdb->get_var( // phpcs:ignore 
												$wpdb->prepare( // phpcs:ignore
													"SELECT IFNULL(COUNT( DISTINCT CONCAT_WS( ':', ip, user_id ) ), 0)
																FROM {$wpdb->prefix}afwc_hits
																WHERE affiliate_id != %d
																	AND datetime BETWEEN %s AND %s",
													0,
													$this->from,
													$this->to
												)
					);
			} else {
				$visitors_count = $wpdb->get_var( // phpcs:ignore
											$wpdb->prepare( // phpcs:ignore
												"SELECT IFNULL(COUNT( DISTINCT CONCAT_WS( ':', ip, user_id ) ), 0)
																FROM {$wpdb->prefix}afwc_hits
																WHERE affiliate_id != %d",
												0
											)
				); // phpcs:ignore

			}

			return intval( $visitors_count );
		}

		/**
		 * Function to get paid commissions, unpaid commissions & customer count
		 *
		 * @param boolean $group_by_affiliate Flag for grouping the results by affiliate id or not.
		 * @return array $aggregated paid commissions, unpaid commissions, customer count
		 */
		public function get_commissions_customers( $group_by_affiliate = false ) {

			global $wpdb;

			$aggregated = array();

			$temp_option_key     = 'afwc_order_status_' . uniqid();
			$paid_order_statuses = afwc_get_paid_order_status();
			update_option( $temp_option_key, implode( ',', $paid_order_statuses ), 'no' );

			if ( ! empty( $this->affiliate_ids ) ) {

				if ( 1 === count( $this->affiliate_ids ) ) {

					if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
						$aggregated = $wpdb->get_results( // phpcs:ignore
															$wpdb->prepare( // phpcs:ignore
																"SELECT IFNULL(SUM( amount ), 0) as gross_commissions,
																					IFNULL(SUM( CASE WHEN status = 'paid' THEN amount END ), 0) as paid_commissions,
																					IFNULL(SUM( CASE WHEN status = 'unpaid' AND  FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																										FROM {$wpdb->prefix}options
																										WHERE option_name = %s )  ) THEN amount END ), 0) as unpaid_commissions,
																					IFNULL(COUNT( DISTINCT(CASE WHEN status = 'unpaid' AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																										FROM {$wpdb->prefix}options
																										WHERE option_name = %s )  ) THEN affiliate_id END) ), 0) as unpaid_affiliates,
																					IFNULL(COUNT( DISTINCT IF( user_id > 0, user_id, CONCAT_WS( ':', ip, user_id ) ) ), 0) as customers_count
																			FROM {$wpdb->prefix}afwc_referrals
																			WHERE
																				affiliate_id = %d
																				AND datetime BETWEEN %s AND %s
																				AND status != %s",
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																$temp_option_key,
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																$temp_option_key,
																current( $this->affiliate_ids ),
																$this->from,
																$this->to,
																'draft'
															),
							'ARRAY_A'
						);
					} else {
						$aggregated = $wpdb->get_results( // phpcs:ignore
															$wpdb->prepare( // phpcs:ignore
																"SELECT IFNULL(SUM( amount ), 0) as gross_commissions,
																					IFNULL(SUM( CASE WHEN status = 'paid' THEN amount END ), 0) as paid_commissions,
																					IFNULL(SUM( CASE WHEN status = 'unpaid' AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																										FROM {$wpdb->prefix}options
																										WHERE option_name = %s )  ) THEN amount END ), 0) as unpaid_commissions,
																					IFNULL(COUNT( DISTINCT(CASE WHEN status = 'unpaid' AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																										FROM {$wpdb->prefix}options
																										WHERE option_name = %s )  ) THEN affiliate_id END) ), 0) as unpaid_affiliates,
																					IFNULL(COUNT( DISTINCT IF( user_id > 0, user_id, CONCAT_WS( ':', ip, user_id ) ) ), 0) as customers_count
																			FROM {$wpdb->prefix}afwc_referrals
																			WHERE
																				affiliate_id = %d
																				AND status != %s",
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																$temp_option_key,
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																$temp_option_key,
																current( $this->affiliate_ids ),
																'draft'
															),
							'ARRAY_A'
						); // phpcs:ignore
					}
				} else {

					$option_nm = 'afwc_commission_affiliate_ids_' . uniqid();
					update_option( $option_nm, implode( ',', $this->affiliate_ids ), 'no' );

					if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
						$aggregated = $wpdb->get_results( // phpcs:ignore
															$wpdb->prepare( // phpcs:ignore
																"SELECT IFNULL(SUM( amount ), 0) as gross_commissions,
																					IFNULL(SUM( CASE WHEN status = 'paid' THEN amount END ), 0) as paid_commissions,
																					IFNULL(SUM( CASE WHEN status = 'unpaid' AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																										FROM {$wpdb->prefix}options
																										WHERE option_name = %s )  ) THEN amount END ), 0) as unpaid_commissions,
																					IFNULL(COUNT( DISTINCT(CASE WHEN status = 'unpaid' AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																										FROM {$wpdb->prefix}options
																										WHERE option_name = %s )  ) THEN affiliate_id END) ), 0) as unpaid_affiliates,
																					IFNULL(COUNT( DISTINCT IF( user_id > 0, user_id, CONCAT_WS( ':', ip, user_id ) ) ), 0) as customers_count
																			FROM {$wpdb->prefix}afwc_referrals
																			WHERE FIND_IN_SET ( affiliate_id, ( SELECT option_value
																											FROM {$wpdb->prefix}options
																											WHERE
																												option_name = %s ) )
																												AND datetime BETWEEN %s AND %s
																												AND status != %s",
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																$temp_option_key,
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																$temp_option_key,
																$option_nm,
																$this->from,
																$this->to,
																'draft'
															),
							'ARRAY_A'
						);
					} else {
						$aggregated = $wpdb->get_results( // phpcs:ignore
															$wpdb->prepare( // phpcs:ignore
																"SELECT IFNULL(SUM( amount ), 0) as gross_commissions,
																					IFNULL(SUM( CASE WHEN status = 'paid' THEN amount END ), 0) as paid_commissions,
																					IFNULL(SUM( CASE WHEN status = 'unpaid' AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																										FROM {$wpdb->prefix}options
																										WHERE option_name = %s )  ) THEN amount END ), 0) as unpaid_commissions,
																					IFNULL(COUNT( DISTINCT(CASE WHEN status = 'unpaid' AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																										FROM {$wpdb->prefix}options
																										WHERE option_name = %s )  ) THEN affiliate_id END) ), 0) as unpaid_affiliates,
																					IFNULL(COUNT( DISTINCT IF( user_id > 0, user_id, CONCAT_WS( ':', ip, user_id ) ) ), 0) as customers_count
																			FROM {$wpdb->prefix}afwc_referrals
																			WHERE FIND_IN_SET ( affiliate_id, ( SELECT option_value
																											FROM {$wpdb->prefix}options
																											WHERE
																												option_name = %s ) )
																												AND status != %s",
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																$temp_option_key,
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																AFWC_SQL_CHARSET,
																AFWC_SQL_COLLATION,
																$temp_option_key,
																$option_nm,
																'draft'
															),
							'ARRAY_A'
						);
					}

					delete_option( $option_nm );
				}
			} elseif ( ! empty( $this->from ) && ! empty( $this->to ) ) {
				// Get the paid records irrespective of the user exists or not.
				$aggregated = $wpdb->get_row( // phpcs:ignore
					$wpdb->prepare(
						"SELECT IFNULL(SUM( amount ), 0) as gross_commissions,
								IFNULL(SUM( CASE WHEN status = 'paid' THEN amount END ), 0) as paid_commissions,
								IFNULL(COUNT( DISTINCT IF( user_id > 0, user_id, CONCAT_WS( ':', ip, user_id ) ) ), 0) as customers_count
							FROM {$wpdb->prefix}afwc_referrals
								WHERE affiliate_id != %d
								AND datetime BETWEEN %s AND %s",
						0,
						$this->from,
						$this->to
					),
					'ARRAY_A'
				);

				// Get the unpaid records only if the user exists - we are not fetching records for deleted/non-existing users.
				$unpaid_data = $wpdb->get_row( // phpcs:ignore
					$wpdb->prepare(
						"SELECT IFNULL(SUM( CASE WHEN referral.status = 'unpaid' THEN referral.amount END ), 0) as unpaid_commissions,
								IFNULL(COUNT( DISTINCT(CASE WHEN referral.status = 'unpaid' THEN referral.affiliate_id END) ), 0) as unpaid_affiliates
							FROM {$wpdb->prefix}afwc_referrals as referral
								JOIN {$wpdb->prefix}users ON ( ID = referral.affiliate_id )
							WHERE referral.affiliate_id != %d
							AND referral.datetime BETWEEN %s AND %s",
						0,
						$this->from,
						$this->to
					),
					'ARRAY_A'
				);

				$aggregated = array(
					array_merge(
						( is_array( $aggregated ) && ! empty( $aggregated ) ) ? $aggregated : array(),
						( is_array( $unpaid_data ) && ! empty( $unpaid_data ) ) ? $unpaid_data : array()
					),
				);

			} else {
				// Get the paid records irrespective of the user exists or not.
				$aggregated = $wpdb->get_row( // phpcs:ignore
					$wpdb->prepare(
						"SELECT IFNULL(SUM( amount ), 0) as gross_commissions,
								IFNULL(SUM( CASE WHEN status = 'paid' THEN amount END ), 0) as paid_commissions,
								IFNULL(COUNT( DISTINCT IF( user_id > 0, user_id, CONCAT_WS( ':', ip, user_id ) ) ), 0) as customers_count
							FROM {$wpdb->prefix}afwc_referrals
								WHERE affiliate_id != %d",
						0
					),
					'ARRAY_A'
				);

				// Get the unpaid records only if the user exists - we are not fetching records for deleted/non-existing users.
				$unpaid_data = $wpdb->get_row( // phpcs:ignore
					$wpdb->prepare(
						"SELECT IFNULL(SUM( CASE WHEN referral.status = 'unpaid' THEN referral.amount END ), 0) as unpaid_commissions,
										IFNULL(COUNT( DISTINCT(CASE WHEN referral.status = 'unpaid' THEN referral.affiliate_id END) ), 0) as unpaid_affiliates
								FROM {$wpdb->prefix}afwc_referrals as referral
									JOIN {$wpdb->prefix}users ON ( ID = referral.affiliate_id )
								WHERE referral.affiliate_id != %d",
						0
					),
					'ARRAY_A'
				);

				$aggregated = array(
					array_merge(
						( is_array( $aggregated ) && ! empty( $aggregated ) ) ? $aggregated : array(),
						( is_array( $unpaid_data ) && ! empty( $unpaid_data ) ) ? $unpaid_data : array()
					),
				);
			}
			delete_option( $temp_option_key );
			return ( ( $group_by_affiliate ) ? $aggregated : ( ! empty( $aggregated[0] ) ? $aggregated[0] : array() ) );
		}

		/**
		 * Function to get commissions earned
		 *
		 * @return float $earned_commissions commissions earned
		 */
		public function get_earned_commissions() {
			global $wpdb;

			$earned_commissions = $this->paid_commissions + $this->unpaid_commissions;

			return floatval( $earned_commissions );
		}

		/**
		 * Function to get formatted join duration
		 *
		 * @return string $formatted_join_duration formatted join duration
		 */
		public function get_formatted_join_duration() {
			global $wpdb;

			// Return affiliate join duration in human readable format
			// only when count of $affiliate_ids is one
			// Return empty string otherwise.
			if ( ! empty( $this->affiliate_ids ) && 1 === count( $this->affiliate_ids ) ) {
				$affiliate               = get_userdata( $this->affiliate_ids[0] );
				$from                    = Affiliate_For_WooCommerce::get_offset_timestamp( strtotime( $affiliate->user_registered ) );
				$to                      = Affiliate_For_WooCommerce::get_offset_timestamp();
				$formatted_join_duration = human_time_diff( $from, $to );
			} else {
				$formatted_join_duration = '';
			}

			return $formatted_join_duration;
		}

		/**
		 * Method to get affiliates order details.
		 *
		 * @todo Return the affiliate count for conditionally load more show/hide.
		 *
		 * @param array $filters Array of filter to filter the queries. Currently it accepts `commission_status`.
		 *
		 * @throws Exception If any error during the DB query execution.
		 * @return array Return affiliates order details.
		 */
		public function get_affiliates_order_details( $filters = array() ) {
			global $wpdb;

			$batch_limit = intval( $this->batch_limit ) + 1;

			$affiliates_order_details = array();

			$commission_status = ! empty( $filters['commission_status'] ) ? $filters['commission_status'] : array();

			try {

				if ( ! empty( $this->affiliate_ids ) && is_array( $this->affiliate_ids ) ) {
					// Block for affiliate IDs.

					if ( 1 === count( $this->affiliate_ids ) ) {
						// Block for single affiliate ID.

						if ( ! empty( $commission_status ) && is_array( $commission_status ) ) {
							// Block for single affiliate ID and commission status.

							if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
								// Block for single affiliate ID, commission status and date range are provided.

								if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
									// Block for single affiliate ID, commission status and date range are provided for HPOS query.

									$affiliates_order_details_results  = $wpdb->get_results( // phpcs:ignore
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( wco.total_amount, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->prefix}wc_orders AS wco
													ON ( wco.id = referrals.post_id AND referrals.affiliate_id = %d )
											WHERE referrals.status IN (" . implode( ',', array_fill( 0, count( $commission_status ), '%s' ) ) . ')
												AND referrals.datetime BETWEEN %s AND %s
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d',
											array_merge(
												array(
													AFWC_TIMEZONE_STR,
													'%d-%b-%Y',
													current( $this->affiliate_ids ),
												),
												$commission_status,
												array(
													$this->from,
													$this->to,
													$this->start_limit,
													$batch_limit,
												)
											)
										),
										'ARRAY_A'
									);
								} else {
									// Block for single affiliate ID, commission status and date range are provided for non-HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( postmeta.meta_value, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->postmeta} AS postmeta
													ON ( postmeta.post_id = referrals.post_id AND postmeta.meta_key = '_order_total' AND referrals.affiliate_id = %d )
											WHERE referrals.status IN (" . implode( ',', array_fill( 0, count( $commission_status ), '%s' ) ) . ')
												AND referrals.datetime BETWEEN %s AND %s
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d',
											array_merge(
												array(
													AFWC_TIMEZONE_STR,
													'%d-%b-%Y',
													current( $this->affiliate_ids ),
												),
												$commission_status,
												array(
													$this->from,
													$this->to,
													$this->start_limit,
													$batch_limit,
												)
											)
										),
										'ARRAY_A'
									);
								}
							} else {
								// Block for single affiliate ID, commission status and date range are not provided.

								if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
									// Block for single affiliate ID, commission status but date range are not provided for HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( wco.total_amount, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->prefix}wc_orders AS wco
													ON ( wco.id = referrals.post_id AND referrals.affiliate_id = %d )
											WHERE referrals.status IN (" . implode( ',', array_fill( 0, count( $commission_status ), '%s' ) ) . ')
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d',
											array_merge(
												array(
													AFWC_TIMEZONE_STR,
													'%d-%b-%Y',
													current( $this->affiliate_ids ),
												),
												$commission_status,
												array(
													$this->start_limit,
													$batch_limit,
												)
											)
										),
										'ARRAY_A'
									);
								} else {
									// Block for single affiliate ID, commission status but date range are not provided for non-HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( postmeta.meta_value, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->postmeta} AS postmeta
													ON ( postmeta.post_id = referrals.post_id AND postmeta.meta_key = '_order_total' AND referrals.affiliate_id = %d )
											WHERE referrals.status IN (" . implode( ',', array_fill( 0, count( $commission_status ), '%s' ) ) . ')
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d',
											array_merge(
												array(
													AFWC_TIMEZONE_STR,
													'%d-%b-%Y',
													current( $this->affiliate_ids ),
												),
												$commission_status,
												array(
													$this->start_limit,
													$batch_limit,
												)
											)
										),
										'ARRAY_A'
									);
								}
							}
						} else {
							// Block for single affiliate ID but commission status is not provided.

							if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
								// Block for single affiliate ID but commission status is not provided and date range are provided.

								if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
									// Block for single affiliate ID but commission status is not provided and date range are provided for HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( wco.total_amount, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->prefix}wc_orders AS wco
													ON ( wco.id = referrals.post_id AND referrals.affiliate_id = %d )
											WHERE referrals.datetime BETWEEN %s AND %s
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d",
											AFWC_TIMEZONE_STR,
											'%d-%b-%Y',
											current( $this->affiliate_ids ),
											$this->from,
											$this->to,
											$this->start_limit,
											$batch_limit
										),
										'ARRAY_A'
									);
								} else {
									// Block for single affiliate ID and date range are provided for non-HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( postmeta.meta_value, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->postmeta} AS postmeta
													ON ( postmeta.post_id = referrals.post_id AND postmeta.meta_key = '_order_total' AND referrals.affiliate_id = %d )
											WHERE referrals.datetime BETWEEN %s AND %s
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d",
											AFWC_TIMEZONE_STR,
											'%d-%b-%Y',
											current( $this->affiliate_ids ),
											$this->from,
											$this->to,
											$this->start_limit,
											$batch_limit
										),
										'ARRAY_A'
									);
								}
							} else {
								// Block for single affiliate ID but commission status and date range are not provided.

								if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
									// Block for single affiliate ID but commission status and date range are not provided for HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( wco.total_amount, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->prefix}wc_orders AS wco
													ON ( wco.id = referrals.post_id AND referrals.affiliate_id = %d )
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d",
											AFWC_TIMEZONE_STR,
											'%d-%b-%Y',
											current( $this->affiliate_ids ),
											$this->start_limit,
											$batch_limit
										),
										'ARRAY_A'
									);
								} else {
									// Block for single affiliate ID but commission status and date range are not provided for non-HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( postmeta.meta_value, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->postmeta} AS postmeta
													ON ( postmeta.post_id = referrals.post_id AND postmeta.meta_key = '_order_total' AND referrals.affiliate_id = %d )
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d",
											AFWC_TIMEZONE_STR,
											'%d-%b-%Y',
											current( $this->affiliate_ids ),
											$this->start_limit,
											$batch_limit
										),
										'ARRAY_A'
									);
								}
							}
						}
					} else {
						// Block for multiple affiliate IDs.

						if ( ! empty( $commission_status ) && is_array( $commission_status ) ) {
							// Block for multiple affiliate IDs and commission status.

							if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
								// Block for multiple affiliate IDs, commission status and date range are provided.

								if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
									// Block for multiple affiliate IDs, commission status and date range are provided for HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( wco.total_amount, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->prefix}wc_orders AS wco
													ON ( wco.id = referrals.post_id )
											WHERE referrals.affiliate_id IN (" . implode( ',', array_fill( 0, count( $this->affiliate_ids ), '%d' ) ) . ')
												AND referrals.status IN (' . implode( ',', array_fill( 0, count( $commission_status ), '%s' ) ) . ')
												AND referrals.datetime BETWEEN %s AND %s
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d',
											array_merge(
												array(
													AFWC_TIMEZONE_STR,
													'%d-%b-%Y',
												),
												$this->affiliate_ids,
												$commission_status,
												array(
													$this->from,
													$this->to,
													$this->start_limit,
													$batch_limit,
												)
											)
										),
										'ARRAY_A'
									);
								} else {
									// Block for multiple affiliate IDs, commission status and date range are provided for non-HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( postmeta.meta_value, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->postmeta} AS postmeta
													ON ( postmeta.post_id = referrals.post_id AND postmeta.meta_key = '_order_total' )
											WHERE referrals.affiliate_id IN (" . implode( ',', array_fill( 0, count( $this->affiliate_ids ), '%d' ) ) . ')
												AND referrals.status IN (' . implode( ',', array_fill( 0, count( $commission_status ), '%s' ) ) . ')
												AND referrals.datetime BETWEEN %s AND %s
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d',
											array_merge(
												array(
													AFWC_TIMEZONE_STR,
													'%d-%b-%Y',
												),
												$this->affiliate_ids,
												$commission_status,
												array(
													$this->from,
													$this->to,
													$this->start_limit,
													$batch_limit,
												)
											)
										),
										'ARRAY_A'
									);
								}
							} else {
								// Block for multiple affiliate IDs, commission status and date range are not provided.

								if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
									// Block for multiple affiliate IDs, commission status but date range are not provided for HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( wco.total_amount, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->prefix}wc_orders AS wco
													ON ( wco.id = referrals.post_id )
											WHERE referrals.affiliate_id IN (" . implode( ',', array_fill( 0, count( $this->affiliate_ids ), '%d' ) ) . ')
												AND referrals.status IN (' . implode( ',', array_fill( 0, count( $commission_status ), '%s' ) ) . ')
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d',
											array_merge(
												array(
													AFWC_TIMEZONE_STR,
													'%d-%b-%Y',
												),
												$this->affiliate_ids,
												$commission_status,
												array(
													$this->start_limit,
													$batch_limit,
												)
											)
										),
										'ARRAY_A'
									);
								} else {
									// Block for multiple affiliate IDs, commission status but date range are not provided for non-HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( postmeta.meta_value, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->postmeta} AS postmeta
													ON ( postmeta.post_id = referrals.post_id AND postmeta.meta_key = '_order_total' )
											WHERE referrals.affiliate_id IN (" . implode( ',', array_fill( 0, count( $this->affiliate_ids ), '%d' ) ) . ')
												AND referrals.status IN (' . implode( ',', array_fill( 0, count( $commission_status ), '%s' ) ) . ')
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d',
											array_merge(
												array(
													AFWC_TIMEZONE_STR,
													'%d-%b-%Y',
												),
												$this->affiliate_ids,
												$commission_status,
												array(
													$this->start_limit,
													$batch_limit,
												)
											)
										),
										'ARRAY_A'
									);
								}
							}
						} else {
							// Block for multiple affiliate IDs no commission status are not provided.

							if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
								// Block for multiple affiliate IDs no commission status but date range are provided.

								if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
									// Block for multiple affiliate IDs no commission status but date range are provided for HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( wco.total_amount, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->prefix}wc_orders AS wco
													ON ( wco.id = referrals.post_id )
											WHERE referrals.affiliate_id IN (" . implode( ',', array_fill( 0, count( $this->affiliate_ids ), '%d' ) ) . ')
												AND referrals.datetime BETWEEN %s AND %s
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d',
											array_merge(
												array(
													AFWC_TIMEZONE_STR,
													'%d-%b-%Y',
												),
												$this->affiliate_ids,
												array(
													$this->from,
													$this->to,
													$this->start_limit,
													$batch_limit,
												)
											)
										),
										'ARRAY_A'
									);
								} else {
									// Block for multiple affiliate IDs, no commission status but date range are provided for non-HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( postmeta.meta_value, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->postmeta} AS postmeta
													ON ( postmeta.post_id = referrals.post_id AND postmeta.meta_key = '_order_total' )
											WHERE referrals.affiliate_id IN (" . implode( ',', array_fill( 0, count( $this->affiliate_ids ), '%d' ) ) . ')
												AND referrals.datetime BETWEEN %s AND %s
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d',
											array_merge(
												array(
													AFWC_TIMEZONE_STR,
													'%d-%b-%Y',
												),
												$this->affiliate_ids,
												array(
													$this->from,
													$this->to,
													$this->start_limit,
													$batch_limit,
												)
											)
										),
										'ARRAY_A'
									);
								}
							} else {
								// Block for multiple affiliate IDs, no commission status, no date range are provided.

								if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
									// Block for multiple affiliate IDs, no commission status, no date range are provided for HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( wco.total_amount, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->prefix}wc_orders AS wco
													ON ( wco.id = referrals.post_id )
											WHERE referrals.affiliate_id IN (" . implode( ',', array_fill( 0, count( $this->affiliate_ids ), '%d' ) ) . ')
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d',
											array_merge(
												array(
													AFWC_TIMEZONE_STR,
													'%d-%b-%Y',
												),
												$this->affiliate_ids,
												array(
													$this->start_limit,
													$batch_limit,
												)
											)
										),
										'ARRAY_A'
									);
								} else {
									// Block for multiple affiliate IDs, no commission status and no date range are provided for non-HPOS query.

									$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$wpdb->prepare(
											"SELECT referrals.post_id AS order_id, 
												DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
												IFNULL( postmeta.meta_value, 0.00 ) AS order_total,
												IFNULL( referrals.amount, 0.00 ) AS commission,
												IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
												referrals.status,
												referrals.type AS referral_type,
												referrals.referral_id
											FROM {$wpdb->prefix}afwc_referrals AS referrals
												JOIN {$wpdb->postmeta} AS postmeta
													ON ( postmeta.post_id = referrals.post_id AND postmeta.meta_key = '_order_total' )
											WHERE referrals.affiliate_id IN (" . implode( ',', array_fill( 0, count( $this->affiliate_ids ), '%d' ) ) . ')
											ORDER BY referrals.datetime DESC
											LIMIT %d,%d',
											array_merge(
												array(
													AFWC_TIMEZONE_STR,
													'%d-%b-%Y',
												),
												$this->affiliate_ids,
												array(
													$this->start_limit,
													$batch_limit,
												)
											)
										),
										'ARRAY_A'
									);
								}
							}
						}
					}
				} else {
					// Block for no affiliate IDs.

					if ( ! empty( $commission_status ) && is_array( $commission_status ) ) {
						// Block for no affiliate IDs but commission status is provided.

						if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
							// Block for no affiliate IDs but commission status and date range are provided.

							if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
								// Block for no affiliate IDs but commission status and date range are provided for HPOS query.

								$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT referrals.post_id AS order_id, 
											DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
											IFNULL( wco.total_amount, 0.00 ) AS order_total,
											IFNULL( referrals.amount, 0.00 ) AS commission,
											IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
											referrals.status,
											referrals.type AS referral_type,
											referrals.referral_id
										FROM {$wpdb->prefix}afwc_referrals AS referrals
											JOIN {$wpdb->prefix}wc_orders AS wco
												ON ( wco.id = referrals.post_id )
										WHERE referrals.affiliate_id != %d
											AND referrals.status IN (" . implode( ',', array_fill( 0, count( $commission_status ), '%s' ) ) . ')
											AND referrals.datetime BETWEEN %s AND %s
										ORDER BY referrals.datetime DESC
										LIMIT %d,%d',
										array_merge(
											array(
												AFWC_TIMEZONE_STR,
												'%d-%b-%Y',
												0,
											),
											$commission_status,
											array(
												$this->from,
												$this->to,
												$this->start_limit,
												$batch_limit,
											)
										)
									),
									'ARRAY_A'
								);
							} else {
								// Block for no affiliate IDs but commission status and date range are provided for non-HPOS query.

								$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT referrals.post_id AS order_id, 
											DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
											IFNULL( postmeta.meta_value, 0.00 ) AS order_total,
											IFNULL( referrals.amount, 0.00 ) AS commission,
											IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
											referrals.status,
											referrals.type AS referral_type,
											referrals.referral_id
										FROM {$wpdb->prefix}afwc_referrals AS referrals
											JOIN {$wpdb->postmeta} AS postmeta
												ON ( postmeta.post_id = referrals.post_id AND postmeta.meta_key = '_order_total' )
										WHERE referrals.affiliate_id != %d
											AND referrals.status IN (" . implode( ',', array_fill( 0, count( $commission_status ), '%s' ) ) . ')
											AND referrals.datetime BETWEEN %s AND %s
										ORDER BY referrals.datetime DESC
										LIMIT %d,%d',
										array_merge(
											array(
												AFWC_TIMEZONE_STR,
												'%d-%b-%Y',
												0,
											),
											$commission_status,
											array(
												$this->from,
												$this->to,
												$this->start_limit,
												$batch_limit,
											)
										)
									),
									'ARRAY_A'
								);
							}
						} else {
							// Block for no affiliate IDs but commission status and no date range are provided.

							if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
								// Block for no affiliate IDs but commission status and no date range are provided for HPOS query.

								$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT referrals.post_id AS order_id, 
											DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
											IFNULL( wco.total_amount, 0.00 ) AS order_total,
											IFNULL( referrals.amount, 0.00 ) AS commission,
											IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
											referrals.status,
											referrals.type AS referral_type,
											referrals.referral_id
										FROM {$wpdb->prefix}afwc_referrals AS referrals
											JOIN {$wpdb->prefix}wc_orders AS wco
												ON ( wco.id = referrals.post_id )
										WHERE referrals.affiliate_id != %d
											AND referrals.status IN (" . implode( ',', array_fill( 0, count( $commission_status ), '%s' ) ) . ')
										ORDER BY referrals.datetime DESC
										LIMIT %d,%d',
										array_merge(
											array(
												AFWC_TIMEZONE_STR,
												'%d-%b-%Y',
												0,
											),
											$commission_status,
											array(
												$this->start_limit,
												$batch_limit,
											)
										)
									),
									'ARRAY_A'
								);
							} else {
								// Block for no affiliate IDs but commission status and no date range are provided for non-HPOS query.

								$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT referrals.post_id AS order_id, 
											DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
											IFNULL( postmeta.meta_value, 0.00 ) AS order_total,
											IFNULL( referrals.amount, 0.00 ) AS commission,
											IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
											referrals.status,
											referrals.type AS referral_type,
											referrals.referral_id
										FROM {$wpdb->prefix}afwc_referrals AS referrals
											JOIN {$wpdb->postmeta} AS postmeta
												ON ( postmeta.post_id = referrals.post_id AND postmeta.meta_key = '_order_total' )
										WHERE referrals.affiliate_id != %d
											AND referrals.status IN (" . implode( ',', array_fill( 0, count( $commission_status ), '%s' ) ) . ')
										ORDER BY referrals.datetime DESC
										LIMIT %d,%d',
										array_merge(
											array(
												AFWC_TIMEZONE_STR,
												'%d-%b-%Y',
												0,
											),
											$commission_status,
											array(
												$this->start_limit,
												$batch_limit,
											)
										)
									),
									'ARRAY_A'
								);
							}
						}
					} else {
						// Block for no affiliate IDs and no commission status is provided.

						if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
							// Block for no affiliate IDs and no commission status is provided but date range are provided.

							if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
								// Block for no affiliate IDs and no commission status is provided but date range are provided for HPOS query.

								$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT referrals.post_id AS order_id, 
											DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
											IFNULL( wco.total_amount, 0.00 ) AS order_total,
											IFNULL( referrals.amount, 0.00 ) AS commission,
											IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
											referrals.status,
											referrals.type AS referral_type,
											referrals.referral_id
										FROM {$wpdb->prefix}afwc_referrals AS referrals
											JOIN {$wpdb->prefix}wc_orders AS wco
												ON ( wco.id = referrals.post_id )
										WHERE referrals.affiliate_id != %d
											AND referrals.datetime BETWEEN %s AND %s
										ORDER BY referrals.datetime DESC
										LIMIT %d,%d",
										AFWC_TIMEZONE_STR,
										'%d-%b-%Y',
										0,
										$this->from,
										$this->to,
										$this->start_limit,
										$batch_limit
									),
									'ARRAY_A'
								);
							} else {
								// Block for no affiliate IDs and no commission status is provided but date range are provided for for non-HPOS query.

								$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT referrals.post_id AS order_id, 
											DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
											IFNULL( postmeta.meta_value, 0.00 ) AS order_total,
											IFNULL( referrals.amount, 0.00 ) AS commission,
											IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
											referrals.status,
											referrals.type AS referral_type,
											referrals.referral_id
										FROM {$wpdb->prefix}afwc_referrals AS referrals
											JOIN {$wpdb->postmeta} AS postmeta
												ON ( postmeta.post_id = referrals.post_id AND postmeta.meta_key = '_order_total' )
										WHERE referrals.affiliate_id != %d
											AND referrals.datetime BETWEEN %s AND %s
										ORDER BY referrals.datetime DESC
										LIMIT %d,%d",
										AFWC_TIMEZONE_STR,
										'%d-%b-%Y',
										0,
										$this->from,
										$this->to,
										$this->start_limit,
										$batch_limit
									),
									'ARRAY_A'
								);
							}
						} else {
							// Block for no affiliate IDs, no commission status no date range are provided.

							if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
								// Block for no affiliate IDs, no commission status no date range are provided for HPOS query.

								$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT referrals.post_id AS order_id, 
											DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
											IFNULL( wco.total_amount, 0.00 ) AS order_total,
											IFNULL( referrals.amount, 0.00 ) AS commission,
											IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
											referrals.status,
											referrals.type AS referral_type,
											referrals.referral_id
										FROM {$wpdb->prefix}afwc_referrals AS referrals
											JOIN {$wpdb->prefix}wc_orders AS wco
												ON ( wco.id = referrals.post_id )
										WHERE referrals.affiliate_id != %d
										ORDER BY referrals.datetime DESC
										LIMIT %d,%d",
										AFWC_TIMEZONE_STR,
										'%d-%b-%Y',
										0,
										$this->start_limit,
										$batch_limit
									),
									'ARRAY_A'
								);
							} else {
								// Block for no affiliate IDs, no commission status no date range are provided for non-HPOS query.

								$affiliates_order_details_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT referrals.post_id AS order_id, 
											DATE_FORMAT( CONVERT_TZ( datetime, '+00:00', %s ), %s ) AS datetime,
											IFNULL( postmeta.meta_value, 0.00 ) AS order_total,
											IFNULL( referrals.amount, 0.00 ) AS commission,
											IFNULL( referrals.campaign_id, 0 ) AS campaign_id,
											referrals.status,
											referrals.type AS referral_type,
											referrals.referral_id
										FROM {$wpdb->prefix}afwc_referrals AS referrals
											JOIN {$wpdb->postmeta} AS postmeta
												ON ( postmeta.post_id = referrals.post_id AND postmeta.meta_key = '_order_total' )
										WHERE referrals.affiliate_id != %d
										ORDER BY referrals.datetime DESC
										LIMIT %d,%d",
										AFWC_TIMEZONE_STR,
										'%d-%b-%Y',
										current( $this->affiliate_ids ),
										$this->start_limit,
										$batch_limit
									),
									'ARRAY_A'
								);
							}
						}
					}
				}

				// Throw if any error.
				if ( is_wp_error( $affiliates_order_details_results ) ) {
					throw new Exception( is_callable( array( $affiliates_order_details_results, 'get_error_message' ) ) ? $affiliates_order_details_results->get_error_message() : '' );
				}
			} catch ( Exception $e ) {
				Affiliate_For_WooCommerce::get_instance()->log_error( __METHOD__, ( is_callable( array( $e, 'getMessage' ) ) ) ? $e->getMessage() : '' );
			}

			$load_more = false;

			if ( ! empty( $affiliates_order_details_results ) && is_array( $affiliates_order_details_results ) ) {
				if ( count( $affiliates_order_details_results ) === $batch_limit ) {
					array_pop( $affiliates_order_details_results );
					$load_more = true;
				}

				foreach ( $affiliates_order_details_results as $result ) {
					$current_order_id = ! empty( $result['order_id'] ) ? $result['order_id'] : '';
					if ( empty( $current_order_id ) ) {
						continue;
					}
					$order_ids[]             = $current_order_id;
					$result['referral_type'] = ucwords( ( empty( $result['referral_type'] ) ) ? 'link' : $result['referral_type'] );
					// Order edit build needs update to support HPOS. Currently WC handles but they do not have public function to extend.
					$result['order_url']        = admin_url( 'post.php?post=' . $current_order_id . '&action=edit' );
					$result['campaign_title']   = ! empty( $result['campaign_id'] ) ? afwc_get_campaign_title( intval( $result['campaign_id'] ) ) : '';
					$affiliates_order_details[] = $result;
				}
			}

			if ( ! empty( $order_ids ) && is_array( $order_ids ) ) {
				$order_ids = array_unique( $order_ids );

				try {
					if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
						$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->prepare(
								"SELECT wco.id AS order_id,
									CONCAT_WS( ' ', wcoa.first_name, wcoa.last_name ) AS billing_name,
									wco.billing_email AS billing_email,
									wco.customer_id AS customer_user,
									wco.currency AS currency,
									wco.status AS order_status,
									UNIX_TIMESTAMP(wco.date_created_gmt) AS order_date
								FROM {$wpdb->prefix}wc_orders AS wco
									LEFT JOIN {$wpdb->prefix}wc_order_addresses AS wcoa
										ON ( wco.id = wcoa.order_id AND wcoa.address_type = 'billing' AND wco.type = 'shop_order' )
								WHERE wco.id IN (" . implode( ',', array_fill( 0, count( $order_ids ), '%d' ) ) . ')
								GROUP BY order_id',
								$order_ids
							),
							'ARRAY_A'
						);
					} else {
						$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->prepare(
								"SELECT post_id AS order_id,
									GROUP_CONCAT(CASE WHEN meta_key IN ('_billing_first_name', '_billing_last_name') THEN meta_value END SEPARATOR ' ') AS billing_name,
									GROUP_CONCAT(CASE WHEN meta_key = '_billing_email' THEN meta_value END) as billing_email,
									GROUP_CONCAT(CASE WHEN meta_key = '_customer_user' THEN meta_value END) as customer_user,
									GROUP_CONCAT(CASE WHEN meta_key = '_order_currency' THEN meta_value END) as currency,
									post.post_status as order_status,
									UNIX_TIMESTAMP(post.post_date_gmt) AS order_date
								FROM {$wpdb->postmeta} AS postmeta
								JOIN {$wpdb->posts} as post ON ( post.ID = postmeta.post_id )
								WHERE meta_key IN ('_billing_first_name', '_billing_last_name', '_billing_email', '_customer_user', '_order_currency')
									AND post_id IN (" . implode( ',', array_fill( 0, count( $order_ids ), '%d' ) ) . ')
								GROUP BY order_id',
								$order_ids
							),
							'ARRAY_A'
						);
					}
					// Throw if any error.
					if ( is_wp_error( $results ) ) {
						throw new Exception( is_callable( array( $results, 'get_error_message' ) ) ? $results->get_error_message() : '' );
					}
				} catch ( Exception $e ) {
					Affiliate_For_WooCommerce::get_instance()->log_error( __METHOD__, ( is_callable( array( $e, 'getMessage' ) ) ) ? $e->getMessage() : '' );
				}

				if ( ! empty( $results ) && is_array( $results ) ) {
					global $affiliate_for_woocommerce;

					$order_statuses = wc_get_order_statuses();
					foreach ( $results as $detail ) {
						$order_filters = ! empty( $detail['customer_user'] ) ? array( '_customer_user' => $detail['customer_user'] ) : ( ( ! empty( $detail['billing_email'] ) ) ? array( 's' => $detail['billing_email'] ) : array() );

						$orders_billing_name[ $detail['order_id'] ]['customer_orders_url'] = ! empty( $order_filters ) ? add_query_arg( $order_filters, admin_url( 'edit.php?post_type=shop_order' ) ) : '';

						// New table will return billing_name column with an extra space, so setting it to NULL.
						$detail['billing_name'] = ctype_space( $detail['billing_name'] ) ? null : $detail['billing_name'];

						// Use billing_name if found, else billing_email if found, else guest.
						$orders_billing_name[ $detail['order_id'] ]['billing_name'] = ! empty( $detail['billing_name'] ) ? $detail['billing_name'] : ( ( ! empty( $detail['billing_email'] ) ) ? $detail['billing_email'] : __( 'Guest', 'affiliate-for-woocommerce' ) );

						$orders_billing_name[ $detail['order_id'] ]['currency']     = ! empty( $detail['currency'] ) ? html_entity_decode( get_woocommerce_currency_symbol( $detail['currency'] ), ENT_QUOTES ) : '';
						$orders_billing_name[ $detail['order_id'] ]['currencyCode'] = ! empty( $detail['currency'] ) ? esc_html( $detail['currency'] ) : '';
						$orders_billing_name[ $detail['order_id'] ]['order_status'] = ! empty( $detail['order_status'] ) ? esc_html( is_array( $order_statuses ) && ! empty( $order_statuses[ $detail['order_status'] ] ) ? $order_statuses[ $detail['order_status'] ] : $detail['order_status'] ) : '';

						$orders_billing_name[ $detail['order_id'] ]['days_remaining_for_refund'] = ! empty( $detail['order_date'] ) && is_callable( array( $affiliate_for_woocommerce, 'get_remaining_refund_days_for_order' ) ) ? $affiliate_for_woocommerce->get_remaining_refund_days_for_order( $detail['order_date'] ) : 0;
					}

					if ( ! empty( $affiliates_order_details && is_array( $affiliates_order_details ) ) ) {
						foreach ( $affiliates_order_details as $key => $detail ) {
							$affiliates_order_details[ $key ] = ( ! empty( $orders_billing_name[ $detail['order_id'] ] ) ) ? array_merge( $affiliates_order_details[ $key ], $orders_billing_name[ $detail['order_id'] ] ) : $affiliates_order_details[ $key ];
						}
					}
				}
			}

			return array(
				'data' => ! empty( $affiliates_order_details ) && is_array( $affiliates_order_details ) ? $affiliates_order_details : array(),
				'meta' => array( 'load_more' => $load_more ),
			);
		}

		/**
		 * Function to get affiliates payout history
		 *
		 * @return array affiliates payout history
		 */
		public function get_affiliates_payout_history() {

			$affiliates_payout_history = array();
			$args                      = array();
			$args['affiliate_ids']     = $this->affiliate_ids;
			$args['from']              = $this->from;
			$args['to']                = $this->to;
			$args['start_limit']       = $this->start_limit;
			$args['batch_limit']       = intval( $this->batch_limit ) + 1;
			$args['with_total']        = false;
			$affiliates_payout_history = Affiliate_For_WooCommerce::get_affiliates_payout_history( $args );

			$load_more = false;

			if ( ! empty( $affiliates_payout_history ) && is_array( $affiliates_payout_history ) ) {
				if ( count( $affiliates_payout_history ) === $args['batch_limit'] ) {
					array_pop( $affiliates_payout_history );
					$load_more = true;
				}
			}

			return array(
				'data' => ! empty( $affiliates_payout_history ) && is_array( $affiliates_payout_history ) ? $affiliates_payout_history : array(),
				'meta' => array(
					'load_more' => $load_more,
				),
			);
		}

		/**
		 * Function to get affiliate's display_name
		 *
		 * @return array where key is user ID & value is their display name
		 */
		public function get_affiliates_details() {
			global $wpdb;

			$affiliates_details = array();

			if ( ! empty( $this->affiliate_ids ) ) {

				if ( 1 === count( $this->affiliate_ids ) ) {
					$results = $wpdb->get_results( // phpcs:ignore
												$wpdb->prepare( // phpcs:ignore
													"SELECT ID, display_name, user_email
																FROM {$wpdb->users}
																WHERE ID = %d",
													current( $this->affiliate_ids )
												),
						'ARRAY_A'
					);

				} else {
					$option_nm = 'afwc_display_names_affiliate_ids_' . uniqid();
					update_option( $option_nm, implode( ',', $this->affiliate_ids ), 'no' );

					$results      = $wpdb->get_results( // phpcs:ignore
														$wpdb->prepare( // phpcs:ignore
															"SELECT ID, display_name, user_email
																		FROM {$wpdb->users}
																		WHERE FIND_IN_SET ( ID, ( SELECT option_value
																									FROM {$wpdb->prefix}options
																									WHERE option_name = %s ) )",
															$option_nm
														),
						'ARRAY_A'
					);

					delete_option( $option_nm );
				}
			}

			if ( $results && is_array( $results ) ) {
				foreach ( $results as $result ) {
					$affiliates_details[ $result['ID'] ] = array(
						'name'  => html_entity_decode( $result['display_name'], ENT_QUOTES ),
						'email' => $result['user_email'],
					);
				}
			}

			return $affiliates_details;
		}

		/**
		 * Function to get affiliate's coupons
		 *
		 * @return array referral_coupons
		 */
		public function get_affiliates_coupons() {
			$referral_coupons     = array();
			$use_referral_coupons = get_option( 'afwc_use_referral_coupons', 'yes' );
			if ( ! empty( $this->affiliate_ids ) && 'yes' === $use_referral_coupons ) {
				$afwc_coupon      = AFWC_Coupon::get_instance();
				$referral_coupons = $afwc_coupon->get_referral_coupon( array( 'user_id' => $this->affiliate_ids ) );
			}
			return $referral_coupons;
		}

		/**
		 * Function to get affiliate's tags
		 *
		 * @return array $user_tags
		 */
		public function get_affiliates_tags() {
			$user_tags = array();
			if ( ! empty( $this->affiliate_ids ) ) {
				$user_tags = wp_get_object_terms( $this->affiliate_ids, 'afwc_user_tags', array( 'fields' => 'id=>name' ) );
			}
			return $user_tags;
		}

		/**
		 * Function to get affiliate's products
		 *
		 * @return array $products
		 */
		public function get_affiliates_products() {

			$args = array(
				'affiliate_id' => current( $this->affiliate_ids ),
				'from'         => $this->from,
				'to'           => $this->to,
				'start_limit'  => ! empty( $this->start_limit ) ? intval( $this->start_limit ) : 0,
				'batch_limit'  => ! empty( $this->batch_limit ) ? ( intval( $this->batch_limit ) + 1 ) : 0,
				'with_total'   => false,
			);

			$products = Affiliate_For_WooCommerce::get_products_data( $args );
			// Extract rows of products.
			$products = ! empty( $products['rows'] ) ? $products['rows'] : array();

			$load_more = false;

			if ( ! empty( $products ) && is_array( $products ) ) {

				if ( count( $products ) === $args['batch_limit'] ) {
					// Remove the extra data.
					array_pop( $products );
					// Enable the load more if extra data found.
					$load_more = true;
				}

				$all_products = array();
				$i            = 0;
				foreach ( $products as $id => $product ) {
					$all_products[ $i ]['product_variation_id'] = ! empty( $id ) ? $id : 0;
					$all_products[ $i ]['sales']                = ! empty( $product['sales'] ) ? afwc_format_price( $product['sales'] ) : 0;
					$all_products[ $i ]['product']              = ! empty( $product['product'] ) ? html_entity_decode( $product['product'], ENT_QUOTES ) : null;
					// Split p_v ID.
					$product_variation_id = explode( '_', $id );
					// To get URL, get edit product link based on product ID - if available, else variation ID.
					$all_products[ $i ]['edit_product_url'] = get_edit_post_link( ( ! empty( $product_variation_id[0] ) ? $product_variation_id[0] : $product_variation_id[1] ), '&' );
					$all_products[ $i ]['quantity']         = ! empty( $product['qty'] ) ? intval( $product['qty'] ) : 0;
					$i++;
				}
			}

			return array(
				'data' => ! empty( $all_products ) && is_array( $all_products ) ? $all_products : array(),
				'meta' => array( 'load_more' => $load_more ),
			);
		}

		/**
		 * Get the linked lifetime customers by the affiliate ID for showing the list in Affiliate Dashboard.
		 *
		 * @return array Return the array of customers.
		 */
		public function get_ltc_customers() {

			$affiliate_id = ( ! empty( $this->affiliate_ids ) && is_array( $this->affiliate_ids ) ) ? intval( current( $this->affiliate_ids ) ) : 0;

			// Return empty data if the affiliate is empty.
			if ( empty( $affiliate_id ) ) {
				return array();
			}

			$affiliate_obj = new AFWC_Affiliate( $affiliate_id );
			$customers     = is_callable( array( $affiliate_obj, 'get_ltc_customers' ) ) ? $affiliate_obj->get_ltc_customers() : array();

			// Return empty data if the customer is empty.
			if ( empty( $customers ) ) {
				return array();
			}

			$customer_list = array();

			if ( is_array( $customers ) ) {
				foreach ( $customers as $customer ) {
					if ( empty( $customer ) ) {
						continue;
					}
					if ( is_numeric( $customer ) && 0 < intval( $customer ) ) {
						$user_data = get_user_by( 'id', intval( $customer ) );
						if ( ! $user_data instanceof WP_User ) {
							continue;
						}
						$customer_list[ $customer ] = array(
							'name'  => ! empty( $user_data->display_name ) ? html_entity_decode( $user_data->display_name, ENT_QUOTES ) : '',
							'email' => ! empty( $user_data->user_email ) ? $user_data->user_email : '',
							'id'    => intval( $customer ),
						);
					} elseif ( is_email( $customer ) ) {
						$customer_list[ $customer ] = array(
							'email' => sanitize_email( $customer ),
						);
					}
				}
			}

			return $customer_list;
		}

		/**
		 * Search the customers.
		 *
		 * @param string $term The terms.
		 *
		 * @return array Return the array of customers.
		 */
		public function search_ltc_customers( $term = '' ) {

			// Return empty data if search term is missing.
			if ( empty( $term ) ) {
				return array();
			}

			$search = array(
				'search'         => '*' . $term . '*',
				'search_columns' => array( 'ID', 'user_nicename', 'user_login', 'user_email' ),
			);

			$users = get_users( $search );

			$customers = array();
			if ( ! empty( $users ) && is_array( $users ) ) {
				foreach ( $users as $user ) {
					$user_data = ! empty( $user->data ) ? $user->data : null;
					if ( ! empty( $user_data ) && ! empty( $user_data->ID ) && ! empty( $user_data->user_email ) ) {
						$customers[] = array(
							'email' => $user_data->user_email,
							'name'  => ! empty( $user_data->display_name ) ? html_entity_decode( $user_data->display_name, ENT_QUOTES ) : '',
							'id'    => intval( $user_data->ID ),
							'text'  => sprintf(
								'%1$s (#%2$d &ndash; %3$s)',
								! empty( $user_data->display_name ) ? $user_data->display_name : '',
								absint( $user_data->ID ),
								$user_data->user_email
							),
						);
					}
				}
			}

			$emails = ! empty( $customers ) ? array_column( $customers, 'email' ) : array();

			// Add the message for addition of new customer if the customer is not exists.
			if ( is_email( $term ) && ! in_array( $term, $emails, true ) ) {
				array_push(
					$customers,
					array(
						'email' => sanitize_email( $term ),
						/* translators: Email address */
						'text'  => esc_html( sprintf( _x( '%s - Would you like to link this customer?', 'Confirmation message for adding the new customer as lifetime commission customer', 'affiliate-for-woocommerce' ), sanitize_email( $term ) ) ),
					)
				);
			}

			return $customers;
		}

		/**
		 * Remove the customer from the affiliate.
		 *
		 * @param string|int $customer The customer.
		 *
		 * @return bool.
		 */
		public function remove_ltc_customers( $customer = '' ) {

			// Return false if the customer is missing.
			if ( empty( $customer ) ) {
				return false;
			}

			$affiliate_id = ( ! empty( $this->affiliate_ids ) && is_array( $this->affiliate_ids ) ) ? intval( current( $this->affiliate_ids ) ) : 0;

			// Return false if the affiliate is empty.
			if ( empty( $affiliate_id ) ) {
				return false;
			}

			$affiliate_obj = new AFWC_Affiliate( $affiliate_id );

			return is_callable( array( $affiliate_obj, 'remove_ltc_customer' ) ) ? $affiliate_obj->remove_ltc_customer( $customer ) : false;
		}

		/**
		 * Add the customer to the affiliate for Lifetime commissions.
		 *
		 * @param string|int $customer The customer.
		 *
		 * @return bool.
		 */
		public function add_ltc_customers( $customer = '' ) {

			// Return false if the customer is missing.
			if ( empty( $customer ) ) {
				return false;
			}

			$affiliate_id = ( ! empty( $this->affiliate_ids ) && is_array( $this->affiliate_ids ) ) ? intval( current( $this->affiliate_ids ) ) : 0;

			// Return false if the affiliate is empty.
			if ( empty( $affiliate_id ) ) {
				return false;
			}

			$affiliate_obj = new AFWC_Affiliate( $affiliate_id );

			return is_callable( array( $affiliate_obj, 'add_ltc_customer' ) ) ? $affiliate_obj->add_ltc_customer( $customer ) : false;
		}

		/**
		 * Get the assigned landing page data.
		 *
		 * @return array Return the array of landing page data.
		 */
		public function get_landing_pages() {

			$affiliate_id = ( ! empty( $this->affiliate_ids ) && is_array( $this->affiliate_ids ) ) ? intval( current( $this->affiliate_ids ) ) : 0;

			// Return if the affiliate ID is empty.
			if ( empty( $affiliate_id ) ) {
				return array();
			}

			$affiliate_obj = new AFWC_Affiliate( $affiliate_id );
			$posts         = is_callable( array( $affiliate_obj, 'get_landing_page_links' ) ) ? $affiliate_obj->get_landing_page_links() : array();

			// Return if no assigned posts found.
			if ( empty( $posts ) ) {
				return array();
			}

			$post_list = array();

			foreach ( $posts as $post_id => $public_link ) {
				$post_list[ $post_id ] = array(
					'edit_link'   => get_edit_post_link( $post_id, '&' ),  // Admin edit link.
					'public_link' => $public_link,  // Public view link.
				);
			}

			return $post_list;
		}

		/**
		 * Method to get visitors report.
		 *
		 * @return array Visitor details for admin dashboard.
		 */
		public function get_visitor_details() {

			$args = array(
				'from'  => ! empty( $this->from ) ? $this->from : '',
				'to'    => ! empty( $this->to ) ? $this->to : '',
				'limit' => ! empty( $this->batch_limit ) ? ( intval( $this->batch_limit ) + 1 ) : 0,
				'start' => ! empty( $this->start_limit ) ? intval( $this->start_limit ) : 0,
			);

			$visitors = new AFWC_Visitors(
				! empty( $this->affiliate_ids ) ? $this->affiliate_ids : array(),
				$args
			);

			$visitor_data = is_callable( array( $visitors, 'get_reports' ) ) ? $visitors->get_reports() : array();

			// Handle for load more.
			$load_more = false;

			if ( ! empty( $visitor_data ) && is_array( $visitor_data ) && count( $visitor_data ) === $args['limit'] ) {
				// Remove the extra data.
				array_pop( $visitor_data );
				// Enable the load more if extra data found.
				$load_more = true;
			}

			return array(
				'data' => ! empty( $visitor_data ) && is_array( $visitor_data ) ? $visitor_data : array(),
				'meta' => array( 'load_more' => $load_more ),
			);
		}

		/**
		 * Method to get the customers count.
		 * Distinct the customer count combined with customer's user ID and IP address.
		 *
		 * @throws Exception If any error during the process.
		 * @return int Return the customer count.
		 */
		public function get_customers_count() {
			global $wpdb;

			try {
				if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
					$customers_count =  $wpdb->get_var( // phpcs:ignore
						$wpdb->prepare(
							"SELECT IFNULL(COUNT(DISTINCT IF(user_id > 0, user_id, CONCAT_WS(':', ip, user_id))), 0) as customers_count
								FROM {$wpdb->prefix}afwc_referrals
								WHERE datetime BETWEEN %s AND %s
								AND status != %s",
							esc_sql( $this->from ),
							esc_sql( $this->to ),
							'draft'
						)
					);
				} else {
					$customers_count = $wpdb->get_var( // phpcs:ignore
						$wpdb->prepare(
							"SELECT IFNULL(COUNT(DISTINCT IF(user_id > 0, user_id, CONCAT_WS(':', ip, user_id))), 0) as customers_count
								FROM {$wpdb->prefix}afwc_referrals
								WHERE status != %s",
							'draft'
						)
					);
				}

				// Throw if any error.
				if ( ! empty( $wpdb->last_error ) ) {
					throw new Exception( $wpdb->last_error );
				}
			} catch ( Exception $e ) {
				Affiliate_For_WooCommerce::get_instance()->log_error( __METHOD__, ( is_callable( array( $e, 'getMessage' ) ) ) ? $e->getMessage() : '' );
			}

			return ! empty( $customers_count ) ? intval( $customers_count ) : 0;
		}

		/**
		 * Method to get the referred orders count.
		 * Distinct the referred order/post_id count.
		 *
		 * @throws Exception If any error during the process.
		 * @return int Return the referrals count.
		 */
		public function get_referrals_count() {
			global $wpdb;

			try {
				if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
					$referral_count =  $wpdb->get_var( // phpcs:ignore
						$wpdb->prepare(
							"SELECT IFNULL(COUNT(DISTINCT post_id), 0) as referral_count
									FROM {$wpdb->prefix}afwc_referrals
									WHERE datetime BETWEEN %s AND %s
									AND status != %s",
							esc_sql( $this->from ),
							esc_sql( $this->to ),
							'draft'
						)
					);
				} else {
					$referral_count =  $wpdb->get_var( // phpcs:ignore
						$wpdb->prepare(
							"SELECT IFNULL(COUNT(DISTINCT post_id), 0) as referral_count
								FROM {$wpdb->prefix}afwc_referrals
								WHERE status != %s",
							'draft'
						)
					);
				}

				// Throw if any error.
				if ( ! empty( $wpdb->last_error ) ) {
					throw new Exception( $wpdb->last_error );
				}
			} catch ( Exception $e ) {
				Affiliate_For_WooCommerce::get_instance()->log_error( __METHOD__, ( is_callable( array( $e, 'getMessage' ) ) ) ? $e->getMessage() : '' );
			}

			return ! empty( $referral_count ) ? $referral_count : 0;
		}

		/**
		 * Method to get all affiliates count.
		 *
		 * @return array Return the array of all(active, pending, rejected) affiliates count.
		 */
		public function get_all_affiliates_count() {

			$affiliates_by_usermeta   = $this->get_affiliates_by_user_meta();
			$affiliates_by_user_roles = afwc_get_affiliates_by_user_roles();

			// Merge the active affiliates from user_roles and usermeta.
			$active_affiliates = array_unique(
				array_merge(
					is_array( $affiliates_by_usermeta ) && ! empty( $affiliates_by_usermeta['active_affiliates'] ) && is_array( $affiliates_by_usermeta['active_affiliates'] ) ? array_map( 'intval', $affiliates_by_usermeta['active_affiliates'] ) : array(),
					is_array( $affiliates_by_user_roles ) && ! empty( $affiliates_by_user_roles ) ? array_map( 'intval', $affiliates_by_user_roles ) : array()
				)
			);

			$pending_affiliates  = ( ! empty( $affiliates_by_usermeta['pending_affiliates'] ) && is_array( $affiliates_by_usermeta['pending_affiliates'] ) ) ? array_unique( array_map( 'intval', $affiliates_by_usermeta['pending_affiliates'] ) ) : array();
			$rejected_affiliates = ( ! empty( $affiliates_by_usermeta['rejected_affiliates'] ) && is_array( $affiliates_by_usermeta['rejected_affiliates'] ) ) ? array_unique( array_map( 'intval', $affiliates_by_usermeta['rejected_affiliates'] ) ) : array();

			// Remove pending and rejected affiliates from active affiliates as we give prior to user meta.
			$active_affiliates = array_diff( $active_affiliates, $pending_affiliates, $rejected_affiliates );

			return array(
				'active_affiliates_count'   => ! empty( $active_affiliates ) ? count( $active_affiliates ) : 0,
				'pending_affiliates_count'  => ! empty( $pending_affiliates ) ? count( $pending_affiliates ) : 0,
				'rejected_affiliates_count' => ! empty( $rejected_affiliates ) ? count( $rejected_affiliates ) : 0,
			);
		}

		/**
		 * Method to get affiliates by user meta.
		 *
		 * @throws Exception If any error during the process.
		 * @return array Return the array of affiliates grouped by the status.
		 */
		public function get_affiliates_by_user_meta() {
			global $wpdb;

			try {
				$users = $wpdb->get_row( // phpcs:ignore
					$wpdb->prepare(
						"SELECT
							DISTINCT
								GROUP_CONCAT(CASE WHEN um.meta_value = %s THEN u.ID END) AS active_affiliates,
								GROUP_CONCAT(CASE WHEN um.meta_value = %s THEN u.ID END) AS pending_affiliates,
								GROUP_CONCAT(CASE WHEN um.meta_value = %s THEN u.ID END) AS rejected_affiliates
						FROM
							{$wpdb->prefix}usermeta um
						JOIN
							{$wpdb->prefix}users u ON um.user_id = u.ID
						WHERE
							um.meta_key = %s",
						'yes',                   // Meta value for active affiliate.
						'pending',               // Meta value for pending affiliate.
						'no',                    // Meta value for rejected affiliate.
						'afwc_is_affiliate'      // Meta key.
					),
					'ARRAY_A'
				);
				// Throw if any error.
				if ( ! empty( $wpdb->last_error ) ) {
					throw new Exception( $wpdb->last_error );
				}
			} catch ( Exception $e ) {
				Affiliate_For_WooCommerce::get_instance()->log_error( __METHOD__, ( is_callable( array( $e, 'getMessage' ) ) ) ? $e->getMessage() : '' );
			}

			return array(
				'active_affiliates'   => ( ! empty( $users ) && is_array( $users ) && ! empty( $users['active_affiliates'] ) ) ? explode( ',', $users['active_affiliates'] ) : array(),
				'pending_affiliates'  => ( ! empty( $users ) && is_array( $users ) && ! empty( $users['pending_affiliates'] ) ) ? explode( ',', $users['pending_affiliates'] ) : array(),
				'rejected_affiliates' => ( ! empty( $users ) && is_array( $users ) && ! empty( $users['rejected_affiliates'] ) ) ? explode( ',', $users['rejected_affiliates'] ) : array(),
			);
		}

		/**
		 * Method to get converted URLs stats.
		 *
		 * @param array $args The arguments for getting the results.
		 *
		 * @throws Exception If any error during the process.
		 * @return array Return the array of URLs with their stats(url, referral_count, visitor_count).
		 */
		public function get_converted_url_stats( $args = array() ) {
			$limit        = ! empty( $args['limit'] ) ? intval( $args['limit'] ) : 0;
			$affiliate_id = ! empty( $this->affiliate_ids ) && is_array( $this->affiliate_ids ) ? intval( current( $this->affiliate_ids ) ) : 0;
			$urls         = array();
			// Currently it works for the affiliate ID. To work with global, adjust the below conditions.
			if ( empty( $affiliate_id ) ) {
				return $urls;
			}

			global $wpdb;

			try {
				if ( ! empty( $this->from ) && ! empty( $this->to ) ) {
					// Run the query where date range is available.
					if ( ! empty( $limit ) ) {
						// Run the query where limit and date range is available.
						$urls = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->prepare(
								"SELECT
									hit.url AS url,
									IFNULL(COUNT(DISTINCT referral.post_id), 0) AS referral_count,
									(
										SELECT IFNULL(COUNT(id), 0)
											FROM {$wpdb->prefix}afwc_hits 
										WHERE url = hit.url AND hit.url != ''
											AND affiliate_id = %d 
											AND datetime BETWEEN %s AND %s
									) AS visitor_count
								FROM {$wpdb->prefix}afwc_hits AS hit
									JOIN {$wpdb->prefix}afwc_referrals AS referral 
										ON (hit.id = referral.hit_id
											AND hit.datetime BETWEEN %s AND %s
											AND referral.status != %s
											AND referral.affiliate_id = %d)
								WHERE hit.url != ''
									AND hit.affiliate_id = %d
								GROUP BY hit.url
								ORDER BY referral_count DESC
								LIMIT %d",
								$affiliate_id,
								esc_sql( $this->from ),
								esc_sql( $this->to ),
								esc_sql( $this->from ),
								esc_sql( $this->to ),
								'draft',
								$affiliate_id,
								$affiliate_id,
								$limit
							),
							'ARRAY_A'
						);
					} else {
						// Run the query where limit not available but date range is available.
						$urls = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->prepare(
								"SELECT 
									hit.url AS url,
									IFNULL(COUNT(DISTINCT referral.post_id), 0) AS referral_count,
									(
										SELECT IFNULL(COUNT(id), 0)
											FROM {$wpdb->prefix}afwc_hits 
										WHERE url = hit.url AND hit.url != ''
											AND affiliate_id = %d 
											AND datetime BETWEEN %s AND %s
									) AS visitor_count
								FROM {$wpdb->prefix}afwc_hits AS hit
									JOIN {$wpdb->prefix}afwc_referrals AS referral 
										ON (hit.id = referral.hit_id
											AND hit.datetime BETWEEN %s AND %s
											AND referral.status != %s
											AND referral.affiliate_id = %d)
								WHERE hit.url != ''
									AND hit.affiliate_id = %d
								GROUP BY hit.url
								ORDER BY referral_count DESC",
								$affiliate_id,
								esc_sql( $this->from ),
								esc_sql( $this->to ),
								esc_sql( $this->from ),
								esc_sql( $this->to ),
								'draft',
								$affiliate_id,
								$affiliate_id
							),
							'ARRAY_A'
						);
					}
				} else {
					// Run the query where date range is not available.
					if ( ! empty( $limit ) ) {
						// Run the query where limit is available but date range is not available.
						$urls = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->prepare(
								"SELECT 
									hit.url AS url,
									IFNULL(COUNT(DISTINCT referral.post_id), 0) AS referral_count,
									(
										SELECT IFNULL(COUNT(id), 0)
											FROM {$wpdb->prefix}afwc_hits 
										WHERE url = hit.url AND hit.url != ''
											AND affiliate_id = %d 
									) AS total_visits
								FROM {$wpdb->prefix}afwc_hits AS hit
									JOIN {$wpdb->prefix}afwc_referrals AS referral 
										ON (hit.id = referral.hit_id
											AND referral.status != %s
											AND referral.affiliate_id = %d)
								WHERE hit.url != ''
									AND hit.affiliate_id = %d
								GROUP BY hit.url
								ORDER BY referral_count DESC
								LIMIT %d",
								$affiliate_id,
								'draft',
								$affiliate_id,
								$affiliate_id,
								$limit
							),
							'ARRAY_A'
						);
					} else {
						// Run the query where both limit or date range are not available.
						$urls = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->prepare(
								"SELECT 
									hit.url AS url,
									IFNULL(COUNT(DISTINCT referral.post_id), 0) AS referral_count,
									(
										SELECT IFNULL(COUNT(id), 0)
											FROM {$wpdb->prefix}afwc_hits 
										WHERE url = hit.url AND hit.url != ''
											AND affiliate_id = %d 
									) AS total_visits
								FROM {$wpdb->prefix}afwc_hits AS hit
									JOIN {$wpdb->prefix}afwc_referrals AS referral 
										ON (hit.id = referral.hit_id
											AND referral.status != %s
											AND referral.affiliate_id = %d)
								WHERE hit.url != ''
									AND hit.affiliate_id = %d
								GROUP BY hit.url
								ORDER BY referral_count DESC",
								$affiliate_id,
								'draft',
								$affiliate_id,
								$affiliate_id
							),
							'ARRAY_A'
						);
					}
				}
				// Throw if any error.
				if ( ! empty( $wpdb->last_error ) ) {
					throw new Exception( $wpdb->last_error );
				}
			} catch ( Exception $e ) {
				Affiliate_For_WooCommerce::get_instance()->log_error( __METHOD__, ( is_callable( array( $e, 'getMessage' ) ) ) ? $e->getMessage() : '' );
			}

			return $urls;
		}
	}
}
