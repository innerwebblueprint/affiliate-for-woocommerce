<?php
/**
 * Main class for Admin dashboard widget.
 *
 * @package     affiliate-for-woocommerce/includes/admin/
 * @since       6.36.0
 * @version     1.0.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Admin_Dashboard_Widget' ) ) {

	/**
	 * Admin dashboard widget class.
	 */
	class AFWC_Admin_Dashboard_Widget {

		/**
		 * Variable to hold instance of this class
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Admin_Dashboard_Widget Singleton object of this class
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
			add_action( 'admin_init', array( $this, 'initialize' ) );
			// Ajax for getting the dashboard data.
			add_action( 'wp_ajax_afwc_summary_widget', array( $this, 'get_summary_widget_response_data' ) );
		}

		/**
		 * Initialize widget.
		 *
		 * @return void
		 */
		public function initialize() {
			add_action( 'admin_enqueue_scripts', array( $this, 'widget_scripts' ) );
			add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
		}

		/**
		 * Load widget-specific scripts.
		 * Load them only on the admin dashboard page.
		 *
		 * @return void
		 */
		public function widget_scripts() {

			$screen = get_current_screen();

			if ( ! $screen instanceof WP_Screen || empty( $screen->id ) || 'dashboard' !== $screen->id ) {
				return;
			}

			$plugin_data = Affiliate_For_WooCommerce::get_plugin_data();
			$suffix      = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_style(
				'afwc-dashboard-widget',
				AFWC_PLUGIN_URL . '/assets/css/afwc-admin-dashboard-widget.css',
				array(),
				$plugin_data['Version']
			);

			if ( ! wp_script_is( 'accounting', 'registered' ) ) {
				wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/accounting/accounting' . $suffix . '.js', array( 'jquery' ), WC_VERSION, true );
			}

			wp_register_script(
				'afwc-dashboard-widget',
				AFWC_PLUGIN_URL . '/assets/js/afwc-dashboard-widget.js',
				array( 'jquery', 'accounting' ),
				$plugin_data['Version'],
				true
			);

			wp_localize_script(
				'afwc-dashboard-widget',
				'afwcDashboardWidgetParams',
				array(
					'ajaxArgs' => array(
						'URL'              => admin_url( 'admin-ajax.php' ),
						'summary'          => array(
							'action'   => 'afwc_summary_widget',
							'security' => wp_create_nonce( 'afwc-summary-widget-data' ),
						),
						'numberOfDecimals' => afwc_get_price_decimals(),
						'decimalSeparator' => afwc_get_price_decimal_separator(),
					),
				)
			);

			wp_enqueue_script( 'afwc-dashboard-widget' );
		}

		/**
		 * Register the widget
		 *
		 * @return void
		 */
		public function register_widget() {
			$widget_title = is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled()
				? _x( 'Affiliates summary - all time', 'Summary dashboard widget title when showing all timer referral data', 'affiliate-for-woocommerce' )
				: _x( 'Affiliates summary - this month', 'Summary dashboard widget title when showing current month\'s referral data', 'affiliate-for-woocommerce' );

			wp_add_dashboard_widget(
				'afwc_summary',
				esc_html( $widget_title ),
				array( $this, 'summary_widget_content' ),
				null,
				null,
				'normal',
				'high'
			);
		}

		/**
		 * Render summary widget.
		 *
		 * @return void
		 */
		public function summary_widget_content() {
			$affiliate_section_title = is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled()
				? _x( 'Affiliates', 'Title for affiliate count section in dashboard widget when showing all timer referral data', 'affiliate-for-woocommerce' )
				: _x( 'Affiliates (All time)', 'Title for affiliate count section in dashboard widget when showing current month\'s referral data', 'affiliate-for-woocommerce' );
			?>
			<div class="afwc-widget-container" id="afwc-summary-widget">
				<div class="afwc-widget-loader"><span class="spinner is-active"></span></div>
			</div>
			<script type="text/html" id="afwc-summary-widget-template">
				<div class="afwc-widget-section">
					<h3><?php echo esc_html( $affiliate_section_title ); ?></h3>
					<div class="afwc-stats-container">
						<div class="afwc-single-stats active-affiliates">
							<span class="afwc-value">{{affiliates.active_affiliate_count}}</span>
							<span class="afwc-label"><?php echo esc_html_x( 'Active', 'Stat label for active affiliate count in dashboard widget', 'affiliate-for-woocommerce' ); ?></span>
						</div>
						<div class="afwc-single-stats pending-affiliates">
							<span class="afwc-value">{{affiliates.pending_affiliate_count}}</span>
							<span class="afwc-label"><?php echo esc_html_x( 'Pending', 'Stat label for pending affiliate count in dashboard widget', 'affiliate-for-woocommerce' ); ?></span>
						</div>
						<div class="afwc-single-stats rejected-affiliates">
							<span class="afwc-value">{{affiliates.rejected_affiliate_count}}</span>
							<span class="afwc-label"><?php echo esc_html_x( 'Rejected', 'Stat label for rejected affiliate count in dashboard widget', 'affiliate-for-woocommerce' ); ?></span>
						</div>
					</div>
				</div>
				<div class="afwc-data-container afwc-widget-section">
					<div class="afwc-stats-container">
						<div class="afwc-single-stats">
							<span class="afwc-value">{{referrals.referral_count}}</span>
							<span class="afwc-label"><?php echo esc_html_x( 'Referrals', 'Title for referrals count in dashboard widget', 'affiliate-for-woocommerce' ); ?></span>
						</div>
						<div class="afwc-single-stats">
							<span class="afwc-value">{{affiliate_total_sales}}</span>
							<span class="afwc-label"><?php echo esc_html_x( 'Affiliates Revenue', 'Title for affiliate revenue in dashboard widget', 'affiliate-for-woocommerce' ); ?></span>
						</div>
						<div class="afwc-single-stats afwc-percentage-stats">
							<span class="afwc-value afwc-total-sales-percentage">{{percent_of_total_sales}}%</span>
							<span class="afwc-label"><?php echo esc_html_x( 'of total revenue', 'Text for total revenue stats in dashboard widget', 'affiliate-for-woocommerce' ); ?></span>
						</div>
					</div>
				</div>
				<div class="afwc-data-container afwc-widget-section">
					<div class="afwc-stats-container">
						<div class="afwc-single-stats">
							<span class="afwc-value">{{referrals.visitor_count}}</span>
							<span class="afwc-label"><?php echo esc_html_x( 'Visitors', 'Label for visitor count in dashboard  widget', 'affiliate-for-woocommerce' ); ?></span>
						</div>
						<div class="afwc-single-stats">
							<span class="afwc-value">{{referrals.customer_count}}</span>
							<span class="afwc-label"><?php echo esc_html_x( 'Customers', 'Label for referral count in dashboard  widget', 'affiliate-for-woocommerce' ); ?></span>
						</div>
						<div class="afwc-single-stats afwc-percentage-stats">
							<span class="afwc-value afwc-referrals-conversion-rate">{{referrals.conversion_rate}}%</span>
							<span class="afwc-label"><?php echo esc_html_x( 'Conversion Rate', 'Label for conversion rate in dashboard  widget', 'affiliate-for-woocommerce' ); ?></span>
						</div>
					</div>
				</div>
				<div class="afwc-dashboard-link-section">
					<p class="afwc-dashboard-links">
						<a href="{{dashboard_url}}" target="_blank"><?php echo esc_html_x( 'Dashboard', 'Affiliates dashboard link in dashboard widget', 'affiliate-for-woocommerce' ); ?><span aria-hidden="true" class="dashicons dashicons-external"></span></a>
						<a href="{{setting_url}}" target="_blank"><?php echo esc_html_x( 'Settings', 'Setting link in dashboard widget', 'affiliate-for-woocommerce' ); ?><span aria-hidden="true" class="dashicons dashicons-external"></span></a>
					</p>
				</div>
			</script>
			<?php
		}

		/**
		 * Get the data for summary widget.
		 *
		 * @return array The array of data.
		 */
		public function get_summary_widget_data() {

			$date_range = $this->get_date_range_for_summary();

			// Initialize admin affiliate.
			$admin_affiliates = new AFWC_Admin_Affiliates(
				array(),
				! empty( $date_range['from'] ) ? get_gmt_from_date( $date_range['from'] ) : '',
				! empty( $date_range['to'] ) ? get_gmt_from_date( $date_range['to'] ) : ''
			);

			// Get the data.
			$affiliates_count       = is_callable( array( $admin_affiliates, 'get_all_affiliates_count' ) ) ? $admin_affiliates->get_all_affiliates_count() : array();
			$visitors_count         = is_callable( array( $admin_affiliates, 'get_visitors_count' ) ) ? absint( $admin_affiliates->get_visitors_count() ) : 0;
			$customers_count        = is_callable( array( $admin_affiliates, 'get_customers_count' ) ) ? absint( $admin_affiliates->get_customers_count() ) : 0;
			$total_store_wide_sales = is_callable( array( $admin_affiliates, 'get_storewide_sales' ) ) ? floatval( $admin_affiliates->get_storewide_sales() ) : 0;
			$affiliate_net_sales    = is_callable( array( $admin_affiliates, 'get_net_affiliates_sales' ) ) ? floatval( $admin_affiliates->get_net_affiliates_sales() ) : 0;

			// Format the data.
			return array(
				'affiliates'             => array(
					'active_affiliate_count'   => ! empty( $affiliates_count['active_affiliates_count'] ) ? absint( $affiliates_count['active_affiliates_count'] ) : 0,
					'pending_affiliate_count'  => ! empty( $affiliates_count['pending_affiliates_count'] ) ? absint( $affiliates_count['pending_affiliates_count'] ) : 0,
					'rejected_affiliate_count' => ! empty( $affiliates_count['rejected_affiliates_count'] ) ? absint( $affiliates_count['rejected_affiliates_count'] ) : 0,
				),
				'referrals'              => array(
					'customer_count'  => $customers_count,
					'visitor_count'   => $visitors_count,
					'conversion_rate' => afwc_format_price( ( $visitors_count > 0 ? ( $customers_count * 100 / $visitors_count ) : 0 ) ),  // Fixed number with defined decimal number.
					'referral_count'  => is_callable( array( $admin_affiliates, 'get_referrals_count' ) ) ? absint( $admin_affiliates->get_referrals_count() ) : 0,
				),
				'affiliate_total_sales'  => AFWC_CURRENCY . ( ! empty( $affiliate_net_sales ) ? afwc_format_price( $affiliate_net_sales ) : 0 ),
				'percent_of_total_sales' => afwc_format_price( ( $total_store_wide_sales > 0 ? ( $affiliate_net_sales * 100 / $total_store_wide_sales ) : 0 ) ), // Fixed number with defined decimal number.
				'dashboard_url'          => admin_url( 'admin.php?page=affiliate-for-woocommerce' ),
				'setting_url'            => add_query_arg(
					array(
						'page' => 'wc-settings',
						'tab'  => 'affiliate-for-woocommerce-settings',
					),
					admin_url( 'admin.php' )
				),
			);
		}

		/**
		 * Get the date range for summary widget.
		 *
		 * Returns the date range of the current month if setup is NON-HPOS.
		 * Returns the entire available time range if setup has HPOS enabled.
		 *
		 * @return array Return the array of date range(From date and To date)
		 */
		public function get_date_range_for_summary() {

			$offset_timestamp = Affiliate_For_WooCommerce::get_offset_timestamp();
			$format           = 'd-m-Y';

			if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
				// From date: The earliest date found in the combined afwc_hits and afwc_referrals tables.
				$afwc          = is_callable( array( 'Affiliate_For_WooCommerce', 'get_instance' ) ) ? Affiliate_For_WooCommerce::get_instance() : null;
				$min_datetime  = is_callable( array( $afwc, 'get_minimum_tracking_datetime' ) ) ? $afwc->get_minimum_tracking_datetime() : '';
				$from_datetime = new DateTime( $min_datetime );
				$from          = $from_datetime->format( $format );
			} else {
				$from = gmdate( $format, mktime( 0, 0, 0, gmdate( 'n', $offset_timestamp ), 1, gmdate( 'Y', $offset_timestamp ) ) ); // From date: the start date of the current month.
			}

			return array(
				'from' => $from,
				'to'   => gmdate( $format, $offset_timestamp ) . '23:59:59', // To date: date as of today.
			);
		}

		/**
		 * Ajax callback method to send the summary widget json data.
		 *
		 * @return void
		 */
		public function get_summary_widget_response_data() {
			check_admin_referer( 'afwc-summary-widget-data', 'security' );

			$data = $this->get_summary_widget_data();

			! empty( $data ) ? wp_send_json_success( $data ) : wp_send_json_error();
		}
	}
}

return AFWC_Admin_Dashboard_Widget::get_instance();
