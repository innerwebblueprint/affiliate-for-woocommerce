<?php
/**
 * Main class for Affiliates frontend templates.
 *
 * @package  affiliate-for-woocommerce/includes/frontend/
 * @since    8.5.0
 * @version  1.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Templates' ) ) {

	/**
	 * Main class for Affiliate template functionality.
	 */
	class AFWC_Templates {

		/**
		 * Property to hold the default template directory
		 *
		 * @var $default_template_dir
		 */
		public $default_template_dir = '';

		/**
		 * Property to hold instance of AFWC_Templates
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Templates Singleton object of this class
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

			$this->default_template_dir = AFWC_PLUGIN_DIRPATH . '/templates/';

			// Register hooks for templates.
			add_action( 'afwc_referral_table', array( $this, 'referral_table' ) );
			add_action( 'afwc_product_table', array( $this, 'product_table' ) );
			add_action( 'afwc_payout_table', array( $this, 'payout_table' ) );
			add_action( 'afwc_dashboard_kpi', array( $this, 'dashboard_kpi' ) );
			add_action( 'afwc_payout_kpi', array( $this, 'payout_kpi' ) );
			add_action( 'afwc_my_account_header', array( $this, 'my_account_header' ) );
			add_action( 'afwc_reports_dashboard', array( $this, 'my_account_dashboard' ) );
			add_action( 'afwc_referrals_dashboard', array( $this, 'my_account_referrals_dashboard' ) );
			add_action( 'afwc_products_dashboard', array( $this, 'my_account_products_dashboard' ) );
			add_action( 'afwc_payouts_dashboard', array( $this, 'my_account_payouts_dashboard' ) );
		}

		/**
		 * Method to show referrals table.
		 *
		 * @param array $args Arguments to be passed to the template.
		 *
		 * @return void
		 */
		public function referral_table( $args = array() ) {
			$args              = $this->filter_arguments_for_report( $args );
			$affiliate_details = is_callable( array( 'AFWC_My_Account', 'get_instance' ) ) ? AFWC_My_Account::get_instance() : null;
			$current_url       = ( ! empty( $_REQUEST['current_url'] ) ) ? wc_clean( wp_unslash( $_REQUEST['current_url'] ) ) : afwc_get_current_url(); // phpcs:ignore
			$this->get_template(
				'my-account/dashboard/referrals-table.php',
				array_merge(
					is_array( $args ) ? $args : array(),
					array(
						'referral_headers'        => is_callable( array( $affiliate_details, 'get_referrals_report_headers' ) ) ? $affiliate_details->get_referrals_report_headers() : array(),
						'referrals'               => is_callable( array( $affiliate_details, 'get_referrals_data' ) ) ? $affiliate_details->get_referrals_data( $args ) : array(),
						'is_show_customer_column' => apply_filters( 'afwc_account_show_customer_column', false, array( 'source' => $this ) ),
						'campaign_link'           => is_callable( array( $affiliate_details, 'get_tab_link' ) ) ? ( $affiliate_details->get_tab_link( 'campaigns', $current_url ) . '#!/' ) : '', // To support Mithril routing of individual campaign URL.
					)
				)
			);
		}

		/**
		 * Method to show product table.
		 *
		 * @param array $args Arguments to be passed to the template.
		 *
		 * @return void
		 */
		public function product_table( $args = array() ) {
			$args              = $this->filter_arguments_for_report( $args );
			$affiliate_details = is_callable( array( 'AFWC_My_Account', 'get_instance' ) ) ? AFWC_My_Account::get_instance() : null;

			$this->get_template(
				'my-account/dashboard/products-table.php',
				array_merge(
					is_array( $args ) ? $args : array(),
					array(
						'product_headers' => is_callable( array( $affiliate_details, 'get_products_report_headers' ) ) ? $affiliate_details->get_products_report_headers() : array(),
						'products'        => is_callable( array( $affiliate_details, 'get_products_data' ) ) ? $affiliate_details->get_products_data( $args ) : array(),
					)
				)
			);
		}

		/**
		 * Method to show payout table.
		 *
		 * @param array $args Arguments to be passed to the template.
		 *
		 * @return void
		 */
		public function payout_table( $args = array() ) {
			$args              = $this->filter_arguments_for_report( $args );
			$affiliate_details = is_callable( array( 'AFWC_My_Account', 'get_instance' ) ) ? AFWC_My_Account::get_instance() : null;

			$this->get_template(
				'my-account/dashboard/payouts-table.php',
				array_merge(
					is_array( $args ) ? $args : array(),
					array(
						'payout_headers' => is_callable( array( $affiliate_details, 'get_payouts_report_headers' ) ) ? $affiliate_details->get_payouts_report_headers() : array(),
						'payouts'        => is_callable( array( $affiliate_details, 'get_payouts_data' ) ) ? $affiliate_details->get_payouts_data( $args ) : array(),
					)
				)
			);
		}

		/**
		 * Method to show main dashboard KPIs.
		 *
		 * @param array $args Arguments to be passed to the template.
		 *
		 * @return void
		 */
		public function dashboard_kpi( $args = array() ) {
			$args              = $this->filter_arguments_for_report( $args );
			$affiliate_details = is_callable( array( 'AFWC_My_Account', 'get_instance' ) ) ? AFWC_My_Account::get_instance() : null;
			$kpis              = is_callable( array( $affiliate_details, 'get_kpis_data' ) ) ? $affiliate_details->get_kpis_data( $args ) : array();

			$paid_commission   = ! empty( $kpis['paid_commission'] ) ? floatval( $kpis['paid_commission'] ) : 0;
			$unpaid_commission = ! empty( $kpis['unpaid_commission'] ) ? floatval( $kpis['unpaid_commission'] ) : 0;

			$gross_commission = ! empty( $kpis['gross_commission'] ) ? floatval( $kpis['gross_commission'] ) : 0;
			$net_commission   = $paid_commission + $unpaid_commission;

			$this->get_template(
				'my-account/dashboard/kpi.php',
				array_merge(
					is_array( $args ) ? $args : array(),
					array(
						'kpis'             => $kpis,
						'gross_commission' => $gross_commission,
						'net_commission'   => $net_commission,
						'visitors'         => is_callable( array( $affiliate_details, 'get_visitors_data' ) ) ? $affiliate_details->get_visitors_data( $args ) : array(),
						'customers_count'  => is_callable( array( $affiliate_details, 'get_customers_data' ) ) ? $affiliate_details->get_customers_data( $args ) : array(),
					)
				)
			);
		}

		/**
		 * Method to show Payout KPIs.
		 *
		 * @param array $args Arguments to be passed to the template.
		 *
		 * @return void
		 */
		public function payout_kpi( $args = array() ) {
			$args              = $this->filter_arguments_for_report( $args );
			$affiliate_details = is_callable( array( 'AFWC_My_Account', 'get_instance' ) ) ? AFWC_My_Account::get_instance() : null;

			$this->get_template(
				'my-account/dashboard/payout-kpi.php',
				array_merge(
					is_array( $args ) ? $args : array(),
					array(
						'kpis' => is_callable( array( $affiliate_details, 'get_payout_kpis' ) ) ? $affiliate_details->get_payout_kpis( $args ) : array(),
					)
				)
			);
		}

		/**
		 * Method to show my account header.
		 *
		 * @param array $args Arguments to be passed to the template.
		 *
		 * @return void
		 */
		public function my_account_header( $args = array() ) {

			$args = ! is_array( $args ) ? array() : $args;

			$this->get_template(
				'my-account/dashboard/header.php',
				array_merge(
					$args,
					array(
						'date_filters' => afwc_get_smart_date_filters(),
					)
				)
			);
		}

		/**
		 * Method to show my account dashboard.
		 *
		 * @param array $args Arguments to be passed to the template.
		 *
		 * @return void
		 */
		public function my_account_dashboard( $args = array() ) {
			$args     = ! is_array( $args ) ? array() : $args;
			$template = 'my-account/affiliate-reports.php';

			$affiliate_details = is_callable( array( 'AFWC_My_Account', 'get_instance' ) ) ? AFWC_My_Account::get_instance() : null;
			$tab_endpoint      = ! empty( $affiliate_details->afwc_tab_endpoint ) ? $affiliate_details->afwc_tab_endpoint : '';
			$section_endpoint  = ! empty( $affiliate_details->afwc_section_endpoint ) ? $affiliate_details->afwc_section_endpoint : '';
			$from              = ! empty( $args['date_range'] ) && ! empty( $args['date_range']['from'] ) ? $args['date_range']['from'] : '';
			$to                = ! empty( $args['date_range'] ) && ! empty( $args['date_range']['to'] ) ? $args['date_range']['to'] : '';
			$current_url       = ! empty( $args['current_url'] ) && ! empty( $args['current_url'] ) ? $args['current_url'] : afwc_get_current_url();
			$report_args       = $this->filter_arguments_for_report(
				array(
					'from' => $from,
					'to'   => $to,
				) + $args
			);

			if ( version_compare( $this->get_version( $template ), '2.0', '>=' ) ) {
				$query_vars = array_filter(
					array(
						$tab_endpoint => 'reports',
						'from-date'   => $from,
						'to-date'     => $to,
					)
				);

				$template_args = array(
					'referral_dashboard_link' => add_query_arg(
						$query_vars + array(
							$section_endpoint => 'referrals',
						),
						$current_url
					),
					'product_dashboard_link'  => add_query_arg(
						$query_vars + array(
							$section_endpoint => 'products',
						),
						$current_url
					),
					'payout_dashboard_link'   => add_query_arg(
						$query_vars + array(
							$section_endpoint => 'payouts',
						),
						$current_url
					),
				);
			} else {

				$kpis = is_callable( array( $affiliate_details, 'get_kpis_data' ) ) ? $affiliate_details->get_kpis_data( $report_args ) : array();

				$paid_commission   = ! empty( $kpis['paid_commission'] ) ? floatval( $kpis['paid_commission'] ) : 0;
				$unpaid_commission = ! empty( $kpis['unpaid_commission'] ) ? floatval( $kpis['unpaid_commission'] ) : 0;

				$gross_commission = ! empty( $kpis['gross_commission'] ) ? floatval( $kpis['gross_commission'] ) : 0;
				$net_commission   = $paid_commission + $unpaid_commission;

				$paid_commission_percentage = ( ! empty( $paid_commission ) && ! empty( $net_commission ) ) ? ( $paid_commission / $net_commission ) * 100 : 0;
				$paid_commission_percentage = ! empty( $paid_commission_percentage ) ? round( $paid_commission_percentage, 2, PHP_ROUND_HALF_UP ) : 0;

				$unpaid_commission_percentage = ( ! empty( $unpaid_commission ) && ! empty( $net_commission ) ) ? ( $unpaid_commission / $net_commission ) * 100 : 0;
				$unpaid_commission_percentage = ! empty( $unpaid_commission_percentage ) ? round( $unpaid_commission_percentage, 2, PHP_ROUND_HALF_UP ) : 0;

				$template_args = array(
					'paid_commission_percentage'   => $paid_commission_percentage,
					'unpaid_commission_percentage' => $unpaid_commission_percentage,
					'gross_commission'             => $gross_commission,
					'kpis'                         => $kpis,
					'refunds'                      => is_callable( array( $affiliate_details, 'get_refunds_data' ) ) ? $affiliate_details->get_refunds_data( $report_args ) : array(),
					'net_commission'               => $net_commission,
					'visitors'                     => is_callable( array( $affiliate_details, 'get_visitors_data' ) ) ? $affiliate_details->get_visitors_data( $report_args ) : array(),
					'customers_count'              => is_callable( array( $affiliate_details, 'get_customers_data' ) ) ? $affiliate_details->get_customers_data( $report_args ) : array(),
					'is_show_customer_column'      => apply_filters( 'afwc_account_show_customer_column', false, array( 'source' => $this ) ),
					'referral_headers'             => is_callable( array( $affiliate_details, 'get_referrals_report_headers' ) ) ? $affiliate_details->get_referrals_report_headers() : array(),
					'product_headers'              => is_callable( array( $affiliate_details, 'get_products_report_headers' ) ) ? $affiliate_details->get_products_report_headers() : array(),
					'payout_headers'               => is_callable( array( $affiliate_details, 'get_payouts_report_headers' ) ) ? $affiliate_details->get_payouts_report_headers() : array(),
					'referrals'                    => is_callable( array( $affiliate_details, 'get_referrals_data' ) ) ? $affiliate_details->get_referrals_data( $report_args ) : array(),
					'products'                     => is_callable( array( $affiliate_details, 'get_products_data' ) ) ? $affiliate_details->get_products_data( $report_args ) : array(),
					'payouts'                      => is_callable( array( $affiliate_details, 'get_payouts_data' ) ) ? $affiliate_details->get_payouts_data( $report_args ) : array(),
					'date_filters'                 => afwc_get_smart_date_filters(),
				);
			}

			$this->get_template(
				$template,
				array_merge(
					array(
						'affiliate_id' => ! empty( $report_args['affiliate_id'] ) ? intval( $report_args['affiliate_id'] ) : 0,
						'date_range'   => array(
							'from' => $from,
							'to'   => $to,
						),
					),
					$template_args
				)
			);
		}

		/**
		 * Method to show referrals dashboard.
		 *
		 * @param array $args Arguments to be passed to the template.
		 *
		 * @return void
		 */
		public function my_account_referrals_dashboard( $args = array() ) {
			$args              = ! is_array( $args ) ? array() : $args;
			$affiliate_details = is_callable( array( 'AFWC_My_Account', 'get_instance' ) ) ? AFWC_My_Account::get_instance() : null;

			$this->get_template(
				'my-account/affiliate-referrals.php',
				array_merge(
					array(
						'dashboard_link' => is_callable( array( $affiliate_details, 'get_tab_link' ) ) ? $affiliate_details->get_tab_link(
							'reports',
							! empty( $args['current_url'] ) ? $args['current_url'] : '',
							array(
								'from-date' => ! empty( $args['date_range'] ) && ! empty( $args['date_range']['from'] ) ? $args['date_range']['from'] : '',
								'to-date'   => ! empty( $args['date_range'] ) && ! empty( $args['date_range']['to'] ) ? $args['date_range']['to'] : '',
							)
						) : '',
					),
					$args
				)
			);
		}

		/**
		 * Method to show products dashboard.
		 *
		 * @param array $args Arguments to be passed to the template.
		 *
		 * @return void
		 */
		public function my_account_products_dashboard( $args = array() ) {
			$args              = ! is_array( $args ) ? array() : $args;
			$affiliate_details = is_callable( array( 'AFWC_My_Account', 'get_instance' ) ) ? AFWC_My_Account::get_instance() : null;

			$this->get_template(
				'my-account/affiliate-products.php',
				array_merge(
					array(
						'dashboard_link' => is_callable( array( $affiliate_details, 'get_tab_link' ) ) ? $affiliate_details->get_tab_link(
							'reports',
							! empty( $args['current_url'] ) ? $args['current_url'] : '',
							array(
								'from-date' => ! empty( $args['date_range'] ) && ! empty( $args['date_range']['from'] ) ? $args['date_range']['from'] : '',
								'to-date'   => ! empty( $args['date_range'] ) && ! empty( $args['date_range']['to'] ) ? $args['date_range']['to'] : '',
							)
						) : '',
					),
					$args
				)
			);
		}

		/**
		 * Method to show payouts dashboard.
		 *
		 * @param array $args Arguments to be passed to the template.
		 *
		 * @return void
		 */
		public function my_account_payouts_dashboard( $args = array() ) {
			$args              = ! is_array( $args ) ? array() : $args;
			$affiliate_details = is_callable( array( 'AFWC_My_Account', 'get_instance' ) ) ? AFWC_My_Account::get_instance() : null;

			$this->get_template(
				'my-account/affiliate-payouts.php',
				array_merge(
					array(
						'dashboard_link' => is_callable( array( $affiliate_details, 'get_tab_link' ) ) ? $affiliate_details->get_tab_link(
							'reports',
							! empty( $args['current_url'] ) ? $args['current_url'] : afwc_get_current_url(),
							array(
								'from-date' => ! empty( $args['date_range'] ) && ! empty( $args['date_range']['from'] ) ? $args['date_range']['from'] : '',
								'to-date'   => ! empty( $args['date_range'] ) && ! empty( $args['date_range']['to'] ) ? $args['date_range']['to'] : '',
							)
						) : '',
					),
					$args
				)
			);
		}


		/**
		 * Method to get the template.
		 *
		 * @param string $template Template name.
		 * @param array  $args     Arguments to be passed to the template.
		 *
		 * @return void
		 */
		public function get_template( $template = '', $args = array() ) {

			if ( empty( $template ) ) {
				return;
			}

			global $affiliate_for_woocommerce;

			wc_get_template(
				$template,
				$args,
				is_callable( array( $affiliate_for_woocommerce, 'get_template_base_dir' ) ) ? $affiliate_for_woocommerce->get_template_base_dir( $template ) : '',
				$this->default_template_dir
			);
		}

		/**
		 * Method to filter arguments for report tab.
		 *
		 * @param array $args Arguments to be filtered.
		 *
		 * @return array $args Filtered arguments.
		 */
		public function filter_arguments_for_report( $args = array() ) {
			$args = ! is_array( $args ) ? array() : $args;

			$args['affiliate_id'] = ( ! empty( $args['affiliate_id'] ) ) ? $args['affiliate_id'] : get_current_user_id();
			$args['from']         = ( ! empty( $args['from'] ) ) ? get_gmt_from_date( $args['from'] . ' 00:00:00', 'Y-m-d H:m:s' ) : '';
			$args['to']           = ( ! empty( $args['to'] ) ) ? get_gmt_from_date( $args['to'] . ' 23:59:59', 'Y-m-d H:m:s' ) : '';

			return $args;
		}

		/**
		 * Method to get the version of the template.
		 * The initial file's comment should have `version:` to detect template file version.
		 *
		 * @param string $template The template name.
		 *
		 * @return string Version number.
		 */
		public function get_version( $template = '' ) {
			if ( empty( $template ) ) {
				return '';
			}

			global $affiliate_for_woocommerce;

			$base_dir = is_callable( array( $affiliate_for_woocommerce, 'get_template_base_dir' ) ) ? $affiliate_for_woocommerce->get_template_base_dir( $template ) : '';

			$file_data = get_file_data(
				( ! empty( $base_dir ) ? locate_template( array( $base_dir ) ) : $this->default_template_dir ) . $template,
				array(
					'version' => 'version',
				)
			);

			return ! empty( $file_data['version'] ) ? $file_data['version'] : '';
		}
	}
}

AFWC_Templates::get_instance();
