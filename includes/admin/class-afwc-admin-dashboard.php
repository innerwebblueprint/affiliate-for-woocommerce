<?php
/**
 * Main class for Affiliates Dashboard
 *
 * @package     affiliate-for-woocommerce/includes/admin/
 * @version     1.18.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Rule_Registry;
use AFWC\Rules\Rule;

if ( ! class_exists( 'AFWC_Admin_Dashboard' ) ) {

	/**
	 * Main class for Affiliates Dashboard
	 */
	class AFWC_Admin_Dashboard {

		/**
		 * The Ajax events.
		 *
		 * @var array $ajax_events
		 */
		private $ajax_events = array(
			'update_commission_status',
			'process_payout',
			'dashboard_data',
			'export_affiliates',
			'order_details',
			'payout_details',
			'affiliate_details',
			'visitor_details',
			'update_feedback',
			'update_payout_method',
			'dashboard_kpi_data',
			'affiliate_kpi_details',
			'products',
			'profile_data',
			'affiliate_chain_data',
			'link_ltc_customers',
			'unlink_ltc_customers',
			'search_ltc_customers',
			'payout_kpi_details',
			'payout_invoice',
			'search_affiliate_tags',
			'search_for_parent_affiliate',
			'update_affiliate_profile',
		);

		/**
		 * Common Ajax events for both frontend and admin dashboard.
		 *
		 * @todo We can merge the $ajax_events and $common_ajax_events.
		 *
		 * @var array $common_ajax_events
		 */
		private $common_ajax_events = array(
			'affiliate_chain_data',
		);

		/**
		 * Variable to hold instance of this class
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Admin_Dashboard Singleton object of this class
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
			define( 'AFWC_AFFILIATES_LIMIT', 50 );
			add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_dashboard_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_dashboard_styles' ) );
			add_action( 'wp_ajax_afwc_dashboard_controller', array( $this, 'request_handler' ) );
			add_action( 'admin_print_scripts', array( $this, 'remove_admin_notices' ) );

			add_action( 'wp_ajax_afwc_update_payout_method', array( $this, 'update_payout_method' ) );
			add_action( 'wp_ajax_afwc_dismiss_plan_update_notice', array( $this, 'dismiss_plan_update_notice' ) );
		}

		/**
		 * Function to remove admin notices from affiliate dashboard page.
		 */
		public function remove_admin_notices() {
			$screen    = get_current_screen();
			$screen_id = $screen ? $screen->id : '';

			if ( strpos( $screen_id, '_affiliate-for-woocommerce' ) !== false ) {
				remove_all_actions( 'admin_notices' );
			}
		}

		/**
		 * Function to register required scripts for admin dashboard.
		 */
		public function register_admin_dashboard_scripts() {
			$screen    = get_current_screen();
			$screen_id = $screen ? $screen->id : '';

			if ( strpos( $screen_id, '_affiliate-for-woocommerce' ) !== false ) {
				$plugin_data = Affiliate_For_WooCommerce::get_plugin_data();
				$suffix      = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
				// Dashboard scripts.
				wp_register_script( 'mithril', AFWC_PLUGIN_URL . '/assets/js/mithril/mithril.min.js', array(), $plugin_data['Version'], true );
				wp_register_script( 'afwc-admin-dashboard-styles', AFWC_PLUGIN_URL . '/assets/js/styles.js', array( 'mithril' ), $plugin_data['Version'], true );
				if ( ! wp_script_is( 'accounting', 'registered' ) ) {
					wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/accounting/accounting' . $suffix . '.js', array( 'jquery' ), WC_VERSION, true );
				}

				wp_register_script( 'afwc-admin-dashboard', AFWC_PLUGIN_URL . '/assets/js/admin.js', array( 'afwc-admin-dashboard-styles', 'accounting', 'wp-i18n', 'afwc-date-functions' ), $plugin_data['Version'], true );
				if ( function_exists( 'wp_set_script_translations' ) ) {
					wp_set_script_translations( 'afwc-admin-dashboard', 'affiliate-for-woocommerce', AFWC_PLUGIN_DIR_PATH . 'languages' );
				}
				if ( ! wp_script_is( 'select2', 'registered' ) ) {
					wp_register_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full' . $suffix . '.js', array( 'jquery' ), WC_VERSION, true );
				}
				wp_enqueue_script( 'select2' );
				wp_enqueue_editor();
				wp_enqueue_media();
			}
		}

		/**
		 * Function to register required styles for admin dashboard.
		 */
		public function register_admin_dashboard_styles() {
			$plugin_data = Affiliate_For_WooCommerce::get_plugin_data();
			$suffix      = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_register_style( 'tailwind', AFWC_PLUGIN_URL . '/assets/css/admin.css', array(), $plugin_data['Version'] );
			wp_register_style( 'afwc-common-tailwind', AFWC_PLUGIN_URL . '/assets/css/common.css', array(), $plugin_data['Version'] );
			wp_register_style( 'afwc-admin-dashboard-css', AFWC_PLUGIN_URL . '/assets/css/afwc-admin-dashboard.css', array(), $plugin_data['Version'] );
			wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC_VERSION );
		}

		/**
		 * Function to show admin dashboard.
		 */
		public static function afwc_dashboard_page() {
			global $wp_roles;
			if ( ! wp_script_is( 'afwc-admin-dashboard' ) ) {
				wp_enqueue_script( 'afwc-admin-dashboard' );
			}

			if ( ! wp_style_is( 'tailwind' ) ) {
				wp_enqueue_style( 'tailwind' );
			}

			if ( ! wp_style_is( 'afwc-common-tailwind' ) ) {
				wp_enqueue_style( 'afwc-common-tailwind' );
			}

			if ( ! wp_style_is( 'afwc-admin-dashboard-css' ) ) {
				wp_enqueue_style( 'afwc-admin-dashboard-css' );
			}

			if ( ! wp_script_is( 'select2' ) ) {
				wp_enqueue_script( 'select2' );
			}

			$settings_link = add_query_arg(
				array(
					'page' => 'wc-settings',
					'tab'  => 'affiliate-for-woocommerce-settings',
				),
				admin_url( 'admin.php' )
			);

			$afwc_filters                                = array();
			$afwc_filters['affiliate_status']['pending'] = _x( 'Awaiting Approval', 'Title for pending affiliate status', 'affiliate-for-woocommerce' );
			$afwc_filters['affiliate_status']['yes']     = _x( 'Active', 'Title for active affiliate status', 'affiliate-for-woocommerce' );
			$afwc_filters['affiliate_status']['no']      = _x( 'Rejected', 'Title for rejected affiliate status', 'affiliate-for-woocommerce' );
			$afwc_filters['commission_status']           = afwc_get_commission_statuses();

			$afwc_filters['tags'] = afwc_get_user_tags_id_name_map(); // TODO:: get top 10 tags and pass.
			$afwc_filters['tags'] = ( ! empty( $afwc_filters['tags'] ) ) ? array_slice( $afwc_filters['tags'], 0, 10, true ) : $afwc_filters['tags'];

			$plan_dashboard_data = array();
			$rule_registry       = is_callable( array( Rule_Registry::class, 'get_instance' ) ) ? Rule_Registry::get_instance() : null;
			$commission_rules    = is_callable( array( $rule_registry, 'get_all_registered' ) ) ? $rule_registry->get_all_registered() : array();
			$rule_categories     = is_callable( array( $rule_registry, 'get_categories' ) ) ? $rule_registry->get_categories() : array();
			$plan_data           = array();
			if ( ! empty( $commission_rules ) || is_array( $commission_rules ) ) {
				foreach ( $commission_rules as $rule_key => $class_obj ) {

					if ( ! $class_obj instanceof Rule ) {
						continue; // Skip if the class instance is not supported the rule instance.
					}

					$plan                      = array();
					$category                  = is_callable( array( $class_obj, 'get_category' ) ) ? $class_obj->get_category() : '';
					$plan['possibleOperators'] = is_callable( array( $class_obj, 'get_possible_operators' ) ) ? $class_obj->get_possible_operators() : array();
					$plan['title']             = is_callable( array( $class_obj, 'get_title' ) ) ? $class_obj->get_title() : '';
					$plan['placeholder']       = is_callable( array( $class_obj, 'get_placeholder' ) ) ? $class_obj->get_placeholder() : '';
					$plan['options']           = is_callable( array( $class_obj, 'get_options' ) ) ? $class_obj->get_options() : array();

					if ( empty( $category ) ) {
						$category = 'global';
					}

					if ( empty( $plan_data[ $category ] ) ) {
						$plan_data[ $category ] = array();
					}
					$plan_data[ $category ]['_meta']     = array(
						'title' => esc_html(
							! empty( $rule_categories[ $category ] )
							? $rule_categories[ $category ]
							: $category
						),
					);
					$plan_data[ $category ][ $rule_key ] = $plan;
				}
			}

			$plan_dashboard_data['plan_rule_data']                   = $plan_data;
			$plan_dashboard_data['apply_to']['all']                  = __( 'all matching products in the order', 'affiliate-for-woocommerce' );
			$plan_dashboard_data['apply_to']['first']                = __( 'only the first matching product', 'affiliate-for-woocommerce' );
			$plan_dashboard_data['action_for_remaining']['continue'] = __( 'continue matching commission plans', 'affiliate-for-woocommerce' );
			$plan_dashboard_data['action_for_remaining']['default']  = __( 'use default commission', 'affiliate-for-woocommerce' );
			$plan_dashboard_data['action_for_remaining']['zero']     = __( 'apply zero commission', 'affiliate-for-woocommerce' );

			$can_ask_for_feedback = self::show_feedback();

			// migration notice.
			$migration_of_order_status_done = get_option( 'afwc_migration_for_order_status_done', false );
			$current_db_version             = get_option( '_afwc_current_db_version' );
			$show_admin_notice              = ( '1.2.7' >= $current_db_version && ! $migration_of_order_status_done ) ? true : false;
			$is_process_running             = get_option( 'afwc_is_migration_process_running', false );

			$afwc_dates_migration_done   = get_option( 'afwc_dates_migration_done', false );
			$show_admin_notice_for_dates = ( '1.2.9' >= $current_db_version && 'yes' !== $afwc_dates_migration_done ) ? true : false;
			$default_plan_id             = afwc_get_default_commission_plan_id();
			$is_action_scheduler_exists  = ( function_exists( 'as_schedule_single_action' ) ) ? true : false;
			$review_link                 = 'https://woocommerce.com/products/affiliate-for-woocommerce/?review';
			$reg_form_page               = get_page_by_path( 'affiliates', 'OBJECT' );
			$afwc_admin_affiliates       = new AFWC_Admin_Affiliates();

			wp_localize_script(
				'afwc-admin-dashboard',
				'afwcDashboardParams',
				array(
					'security'                       => array(
						'dashboard'      => array(
							'updateCommissionStatus'      => wp_create_nonce( 'afwc-admin-update-commission-status' ),
							'processPayout'               => wp_create_nonce( 'afwc-admin-process-payout' ),
							'fetchData'                   => wp_create_nonce( 'afwc-admin-dashboard-data' ),
							'exportAffiliates'            => wp_create_nonce( 'afwc-admin-export-affiliates' ),
							'orderDetails'                => wp_create_nonce( 'afwc-admin-order-details' ),
							'payoutDetails'               => wp_create_nonce( 'afwc-admin-payout-details' ),
							'affiliateDetails'            => wp_create_nonce( 'afwc-admin-affiliate-details' ),
							'updatePayoutMethod'          => wp_create_nonce( 'afwc-admin-update-payout-method' ),
							'updateFeedback'              => wp_create_nonce( 'afwc-admin-update-feedback' ),
							'kpiData'                     => wp_create_nonce( 'afwc-admin-dashboard-kpi-data' ),
							'affiliateKPIData'            => wp_create_nonce( 'afwc-admin-affiliate-kpi-data' ),
							'productsDetails'             => wp_create_nonce( 'afwc-admin-products-details' ),
							'visitorDetails'              => wp_create_nonce( 'afwc-admin-visitor-details' ),
							'profileData'                 => wp_create_nonce( 'afwc-admin-profile-data' ),
							'multiTierData'               => wp_create_nonce( 'afwc-admin-multi-tier-data' ),
							'linkLTCCustomers'            => wp_create_nonce( 'afwc-admin-link-ltc-customers' ),
							'unlinkLTCCustomers'          => wp_create_nonce( 'afwc-admin-unlink-ltc-customers' ),
							'searchLTCCustomers'          => wp_create_nonce( 'afwc-admin-search-ltc-customers' ),
							'payoutKPIData'               => wp_create_nonce( 'afwc-admin-payout-kpi-data' ),
							'payoutInvoice'               => wp_create_nonce( 'afwc-admin-payout-invoice' ),
							'searchAffiliateTags'         => wp_create_nonce( 'afwc-admin-search-affiliate-tags' ),
							'searchForParentAffiliate'    => wp_create_nonce( 'afwc-admin-search-for-parent-affiliate' ),
							'saveAffiliateProfileChanges' => wp_create_nonce( 'afwc-admin-save-profile-changes' ),
							'dismissPlanUpdateNotice'     => wp_create_nonce( 'afwc-admin-dismiss-plan-update-notice' ),
						),
						'campaign'       => array(
							'save'                  => wp_create_nonce( 'afwc-admin-save-campaign' ),
							'delete'                => wp_create_nonce( 'afwc-admin-delete-campaign' ),
							'fetchData'             => wp_create_nonce( 'afwc-admin-campaign-dashboard-data' ),
							'searchRuleDetails'     => wp_create_nonce( 'afwc-admin-campaign-search-rule-details' ),
							'fetchRuleData'         => wp_create_nonce( 'afwc-admin-campaign-rule-data' ),
							'createSampleCampaigns' => wp_create_nonce( 'afwc-admin-create-sample-campaigns' ),
						),
						'commissions'    => array(
							'save'          => wp_create_nonce( 'afwc-admin-save-commissions' ),
							'delete'        => wp_create_nonce( 'afwc-admin-delete-commissions' ),
							'fetchData'     => wp_create_nonce( 'afwc-admin-commissions-dashboard-data' ),
							'savePlanOrder' => wp_create_nonce( 'afwc-admin-save-commission-order' ),
							'extraData'     => wp_create_nonce( 'afwc-admin-extra-data' ),
							'searchPlan'    => wp_create_nonce( 'afwc-admin-search-commission-plans' ),
							'dismissRecurringSettingDeprecatedNotice' => wp_create_nonce( 'afwc-admin-dismiss-recurring-setting-deprecated-notice' ),
						),
						'pendingPayouts' => array(
							'fetchData'              => wp_create_nonce( 'afwc-admin-pending-payouts-dashboard-data' ),
							'snoozeScheduledPayouts' => wp_create_nonce( 'afwc-admin-snooze-scheduled-payouts' ),
							'payoutsKPIData'         => wp_create_nonce( 'afwc-admin-payouts-kpi-data' ),
						),
						'onboarding'     => array(
							'setupAffiliates'    => wp_create_nonce( 'afwc-admin-onboarding-setup-affiliates' ),
							'setupBasicSettings' => wp_create_nonce( 'afwc-admin-onboarding-setup-basic-settings' ),
							'setupCommissions'   => wp_create_nonce( 'afwc-admin-onboarding-setup-commissions' ),
							'setupEmails'        => wp_create_nonce( 'afwc-admin-onboarding-setup-emails' ),
						),
					),
					'settingsLink'                   => $settings_link,
					'docLink'                        => add_query_arg( array( 'page' => 'affiliate-for-woocommerce-documentation' ), admin_url( 'admin.php' ) ),
					'dashboardLink'                  => admin_url( 'admin.php?page=affiliate-for-woocommerce' ),
					'regFormSettingLink'             => admin_url( 'admin.php?page=wc-settings&tab=affiliate-for-woocommerce-settings&section=registration-form' ),
					'regFormPageLink'                => ! empty( $reg_form_page ) && $reg_form_page instanceof WP_Post ? get_permalink( $reg_form_page ) : '',
					'assetsPath'                     => AFWC_PLUGIN_URL . '/assets/',
					'currencySymbol'                 => AFWC_CURRENCY,
					'ajaxurl'                        => admin_url( 'admin-ajax.php' ),
					'home_url'                       => home_url(),
					'afwc_filters'                   => $afwc_filters,
					'plan_dashboard_data'            => $plan_dashboard_data,
					'can_ask_for_feedback'           => $can_ask_for_feedback,
					'review_link'                    => $review_link,
					'show_admin_notice'              => $show_admin_notice,
					'show_admin_notice_for_dates'    => $show_admin_notice_for_dates,
					'is_process_running'             => $is_process_running,
					'is_action_scheduler_exists'     => $is_action_scheduler_exists,
					'affiliate_list_limit'           => AFWC_AFFILIATES_LIMIT,
					'default_plan_id'                => $default_plan_id,
					'precision'                      => afwc_get_price_decimals(),
					'thousandSeperator'              => afwc_get_price_thousand_separator(),
					'decimal'                        => afwc_get_price_decimal_separator(),
					'commissionStatuses'             => afwc_get_commission_statuses(),
					'campaignStatuses'               => is_callable( array( 'AFWC_Campaign_Dashboard', 'get_statuses' ) ) ? AFWC_Campaign_Dashboard::get_statuses() : array(),
					'commissionPlanStatuses'         => is_callable( array( 'AFWC_Commission_Dashboard', 'get_statuses' ) ) ? AFWC_Commission_Dashboard::get_statuses() : array(),
					'show_masspay_deprecated_notice' => 'paypal_masspay' === get_option( 'afwc_commission_payout_method' ),
					'dashboard_data_batch_limit'     => is_callable( array( $afwc_admin_affiliates, 'get_batch_limit' ) ) ? $afwc_admin_affiliates->get_batch_limit() : AFWC_ADMIN_DASHBOARD_DEFAULT_BATCH_LIMIT,
					'payoutMethods'                  => afwc_get_payout_methods(),
					'storeCurrencyCode'              => AFWC_CURRENCY_CODE,
					'pname'                          => afwc_get_pname(),
					'isPrettyReferralEnabled'        => get_option( 'afwc_use_pretty_referral_links', 'no' ),
					'userRoles'                      => ! empty( $wp_roles->role_names ) ? $wp_roles->role_names : array(),
					'isMultiTierEnabled'             => is_callable( array( 'AFWC_Multi_Tier', 'is_enabled' ) ) && AFWC_Multi_Tier::is_enabled(),
					'subscriptionNotice'             => ( class_exists( 'WCS_AFWC_Compatibility' ) && is_callable( array( 'WCS_AFWC_Compatibility', 'plan_admin_notice' ) ) ) ? WCS_AFWC_Compatibility::plan_admin_notice() : '',
					'subscriptionDescForPlan'        => ( class_exists( 'WCS_AFWC_Compatibility' ) && is_callable( array( 'WCS_AFWC_Compatibility', 'plan_description' ) ) ) ? WCS_AFWC_Compatibility::plan_description() : '',
					'afwDocLink'                     => AFWC_DOC_DOMAIN,
					'afwManageTagsLink'              => admin_url( 'edit-tags.php?taxonomy=afwc_user_tags' ),
					'isPayoutInvoiceEnabled'         => is_callable( array( 'AFWC_Payout_Invoice', 'is_enabled' ) ) && AFWC_Payout_Invoice::is_enabled(),
					'isAdmin'                        => true,
					'minimumCommissionPayout'        => get_option( 'afwc_minimum_commission_balance', 50 ),
					'planUpdateNotice'               => 'yes' === get_option( 'afwc-commission-rule-update_affiliate_wc', 'yes' ) ? _x(
						'We have improved commission calculations in certain cases. New referral orders may have updated commissions issued (the existing referrals remain unaffected).',
						'Notice text for affiliate commission rule calculation update',
						'affiliate-for-woocommerce'
					) : '',
				)
			);

			?>
				<style type="text/css">
					#wpcontent { 
						padding-left: 0 !important;
					}
				</style>
				<div id="afw-admin-dashboard" class="afw-admin-dashboard"></div>
			<?php
		}

		/**
		 * Function to show feedback
		 */
		public static function show_feedback() {
			$feedback_option_review = get_option( 'afwc_feedback_option_review', false );
			if ( ! empty( $feedback_option_review ) ) {
				return false;
			}
			$current_date         = gmdate( 'Y-m-d H:i:s', Affiliate_For_WooCommerce::get_offset_timestamp() );
			$feedback_start_date  = get_option( 'afwc_feedback_start_date', false );
			$feedback_close_date  = get_option( 'afwc_feedback_close_date', false );
			$diff                 = ceil( abs( strtotime( $current_date ) - strtotime( $feedback_start_date ) ) / 86400 );
			$can_ask_for_feedback = ( $diff > 15 ) ? true : false;
			$close_diff           = ! empty( $feedback_close_date ) ? ceil( abs( strtotime( $feedback_close_date ) - strtotime( $current_date ) ) / 86400 ) : 0;
			$can_ask_for_feedback = ( ! empty( $close_diff ) && $close_diff < 15 ) ? false : $can_ask_for_feedback;
			return $can_ask_for_feedback;
		}

		/**
		 * Function to handle all ajax request
		 */
		public function request_handler() {
			if ( empty( $_REQUEST ) || empty( wc_clean( wp_unslash( $_REQUEST['cmd'] ) ) ) ) { // phpcs:ignore
				return;
			}

			$params = array_map(
				function ( $request_param ) {
					return wc_clean( wp_unslash( $request_param ) );
				},
				$_REQUEST // phpcs:ignore
			);

			$func_nm = ! empty( $params['cmd'] ) ? $params['cmd'] : '';
			if ( empty( $func_nm ) || ! in_array( $func_nm, $this->ajax_events, true ) || ! ( afwc_current_user_can_manage_affiliate() || in_array( $func_nm, $this->common_ajax_events, true ) ) ) {
				wp_die( esc_html_x( 'You are not allowed to use this action', 'authorization failure message', 'affiliate-for-woocommerce' ) );
			}

			$params['from'] = get_gmt_from_date( ( ( ! empty( $params['from_date'] ) ) ? $params['from_date'] : '' ) );
			$params['to']   = get_gmt_from_date( ( ( ! empty( $params['to_date'] ) ) ? $params['to_date'] : '' ) );

			$params['affiliate_id'] = ! empty( $params['affiliate_id'] ) ? $params['affiliate_id'] : 0;
			$params['page']         = ! empty( $params['page'] ) ? $params['page'] : 1;

			if ( is_callable( array( $this, $func_nm ) ) ) {
				$this->$func_nm( $params );
			}
		}

		/**
		 * Function to change commission status of an order for a single affiliate.
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function update_commission_status( $params = array() ) {

			check_admin_referer( 'afwc-admin-update-commission-status', 'security' );

			if ( empty( $params['status'] ) ) {
				wp_send_json(
					array(
						'ACK'   => 'Error',
						'error' => _x( 'Status missing', 'error message for requested parameter missing', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$records = false;

			if ( ! empty( $params['referral_ids'] ) ) {

				$referral_ids = json_decode( $params['referral_ids'], true );

				$records = $this->set_commission_status(
					array(
						'status'       => $params['status'],
						'referral_ids' => ( is_array( $referral_ids ) && ! empty( $referral_ids ) ) ? array_map( 'intval', $referral_ids ) : array(),
					)
				);

			} elseif ( ! empty( $params['order_ids'] ) && ! empty( $params['affiliate_id'] ) ) {

				$order_ids = json_decode( $params['order_ids'], true );
				// set commission status.
				$records = $this->set_commission_status(
					array(
						'status'       => $params['status'],
						'order_ids'    => ( is_array( $order_ids ) && ! empty( $order_ids ) ) ? array_map( 'intval', $order_ids ) : array(),
						'affiliate_id' => intval( $params['affiliate_id'] ),
					)
				);

			} else {
				wp_send_json(
					array(
						'ACK'   => 'Error',
						'error' => __( 'Required params missing', 'affiliate-for-woocommerce' ),
					)
				);
			}

			if ( false === $records ) {
				// Return if query execution is failed.
				wp_send_json(
					array(
						'ACK'     => 'Error',
						'message' => __( 'Failed to update commission status, please try after some time.', 'affiliate-for-woocommerce' ),
					)
				);
			}

			wp_send_json(
				array(
					'ACK'     => 'Success',
					'message' => sprintf( // translators: Number of records updated in referrals table.
						_n( 'Commission status updated for %d record', 'Commission status updated for %d records', intval( $records ), 'affiliate-for-woocommerce' ),
						intval( $records )
					),
				)
			);
		}

		/**
		 * Handler for AJAX request for processing affiliate payouts.
		 *
		 * @todo Handle the case for multiple affiliate's payouts.
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function process_payout( $params = array() ) {

			check_admin_referer( 'afwc-admin-process-payout', 'security' );

			if ( empty( $params['affiliate'] ) || empty( $params['selected_referrals'] ) || empty( $params['method'] ) ) {
				wp_send_json(
					array(
						'ACK'   => 'Error',
						'error' => __( 'Required params missing', 'affiliate-for-woocommerce' ),
					)
				);
			}

			global $wpdb;

			$affiliate          = ( ! empty( $params['affiliate'] ) ) ? json_decode( $params['affiliate'], true ) : array();
			$affiliate_id       = ( ! empty( $affiliate['id'] ) ) ? intval( $affiliate['id'] ) : '';
			$selected_referrals = ( ! empty( $params['selected_referrals'] ) ) ? json_decode( $params['selected_referrals'], true ) : array();
			$woo_currencies     = get_woocommerce_currencies();
			$currency           = ( ! empty( $params['currency'] ) && ! empty( $woo_currencies ) && in_array( $params['currency'], array_keys( $woo_currencies ), true ) ) ? $params['currency'] : AFWC_CURRENCY_CODE;

			$payout_result = array();

			$payout_params = array(
				'currency'  => $currency,
				'referrals' => $selected_referrals,
				'note'      => ( ! empty( $params['note'] ) ) ? $params['note'] : '',
				'method'    => ( ! empty( $params['method'] ) ) ? $params['method'] : '',
				'date'      => ( ! empty( $params['date'] ) ) ? $params['date'] : gmdate( 'd-M-Y' ),
			);

			$payout_handler = new AFWC_Payout_Handler( $payout_params );

			// It will group the referral records by affiliates and execute for a single affiliate.
			$payout_result = is_callable( array( $payout_handler, 'process_payout' ) ) ? $payout_handler->process_payout( $affiliate_id ) : array();

			if ( ! empty( $payout_result ) && is_array( $payout_result ) && ! empty( $payout_result['success'] ) && true === $payout_result['success'] ) {
				wp_send_json(
					array(
						'ACK'                    => 'Success',
						'last_added_payout_data' => ! empty( $payout_result['payout_data'] ) ? $payout_result['payout_data'] : array(),
					)
				);
			} else {
				$payout_method = ( ! empty( $payout_params['method'] ) ? afwc_get_payout_methods( $payout_params['method'] ) : '' );
				wp_send_json(
					array(
						'ACK'   => 'Error',
						'error' => sprintf( // translators: Name of the payout method.
							_x( '%s payout failed.', 'Payout failed error message', 'affiliate-for-woocommerce' ),
							$payout_method
						),
					)
				);
			}
		}

		/**
		 * Handler for AJAX request for getting affiliate KPI data.
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function dashboard_kpi_data( $params = array() ) {

			check_admin_referer( 'afwc-admin-dashboard-kpi-data', 'security' );

			$affiliates              = $this->affiliates_list( $params );
			$affiliate_ids           = array_map(
				function ( $affiliates ) {
					return ! empty( $affiliates['affiliate_id'] ) ? $affiliates['affiliate_id'] : 0;
				},
				$affiliates
			);
			$params['affiliate_ids'] = array_filter( $affiliate_ids );

			wp_send_json(
				array(
					'kpi' => $this->kpi_data( $params ),
				)
			);
		}

		/**
		 * Handler for AJAX request for getting affiliate list.
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function dashboard_data( $params = array() ) {

			check_admin_referer( 'afwc-admin-dashboard-data', 'security' );

			wp_send_json(
				array(
					'affiliateList' => $this->affiliates_list( $params ),
				)
			);
		}

		/**
		 * Handler for AJAX request for getting affiliate dashboard KPI data.
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function kpi_data( $params = array() ) {
			// current data as per date filters.
			// pass empty affiliate_ids so we can have KPI for all affiliate without limit.
			$search_term  = ! empty( $params['q'] ) ? $params['q'] : '';
			$afwc_filters = ( ! empty( $params['filters'] ) ) ? json_decode( $params['filters'], true ) : array();

			$affiliate_ids           = ! empty( $params['affiliate_ids'] ) ? $params['affiliate_ids'] : array();
			$affiliate_ids           = ( empty( $afwc_filters ) && empty( $search_term ) ) ? array() : $affiliate_ids;
			$filtered_affiliates_obj = new AFWC_Admin_Affiliates( $affiliate_ids, $params['from'], $params['to'] );
			$total_sales             = is_callable( array( $filtered_affiliates_obj, 'get_storewide_sales' ) ) ? floatval( $filtered_affiliates_obj->get_storewide_sales() ) : 0;
			$net_sales               = is_callable( array( $filtered_affiliates_obj, 'get_net_affiliates_sales' ) ) ? floatval( $filtered_affiliates_obj->get_net_affiliates_sales() ) : 0;
			$aggregated              = is_callable( array( $filtered_affiliates_obj, 'get_commissions_customers' ) ) ? $filtered_affiliates_obj->get_commissions_customers() : array();
			$visitor_count           = is_callable( array( $filtered_affiliates_obj, 'get_visitors_count' ) ) ? intval( $filtered_affiliates_obj->get_visitors_count() ) : 0;
			$customers_count         = ! empty( $aggregated['customers_count'] ) ? intval( $aggregated['customers_count'] ) : 0;
			$affiliates_count        = ! empty( $affiliate_ids ) ? count( $affiliate_ids ) : $this->get_affiliate_count( $params );
			$afwc                    = array(
				'net_affiliates_sales' => afwc_format_price( $net_sales ),
				'total_sales'          => $total_sales,
				'unpaid_commissions'   => afwc_format_price( $aggregated['unpaid_commissions'] ),
				'paid_commissions'     => afwc_format_price( $aggregated['paid_commissions'] - $aggregated['unpaid_commissions'] ),
				'unpaid_affiliates'    => afwc_format_price( $aggregated['unpaid_affiliates'], 0 ),
				'all_customers_count'  => afwc_format_price( $customers_count, 0 ),
				'visitors_count'       => afwc_format_price( $visitor_count, 0 ),
				'affiliates_count'     => $affiliates_count,
			);

			$afwc['percent_of_total_sales'] = afwc_format_price( ( ( $total_sales > 0 ) ? ( $net_sales * 100 ) / $total_sales : 0 ) );
			$afwc['conversion_rate']        = afwc_format_price( ( ( $visitor_count > 0 ) ? $customers_count * 100 / $visitor_count : 0 ) );
			return $afwc;
		}

		/**
		 * Get affiliate count
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function get_affiliate_count( $params = array() ) {
			$affiliate_count = 0;
			global $wpdb;

			$afwc_filters            = ( ! empty( $params['filters'] ) ) ? json_decode( $params['filters'], true ) : array();
			$params['status']        = '';
			$params['affiliate_ids'] = array();

			$is_affiliate_status_filter = false;
			if ( ! empty( $afwc_filters['affiliate_status'] ) ) {
				$is_affiliate_status_filter = true;
			}

			$default_filters['affiliate_status']  = array( 'yes', 'pending' );
			$default_filters['commission_status'] = array();
			$default_filters['tags']              = array();

			$search_term = ! empty( $params['q'] ) ? $params['q'] : '';

			foreach ( $default_filters as $filter => $filter_val ) {
				if ( ! empty( $afwc_filters[ $filter ] ) ) {
					$default_filters[ $filter ] = $afwc_filters[ $filter ];
				}
			}
			$afwc_filters = $default_filters;

			// code for filters.
			$wpdb1 = $wpdb;

			$referrals_join_cond     = " JOIN {$wpdb->prefix}afwc_referrals as ref
										ON(ref.affiliate_id = u.ID) ";
			$filters_join_cond       = ' LEFT ' . $referrals_join_cond;
			$filters_where_cond      = ' 1=1 ';
			$filters_umeta_join_cond = '';
			$filters_user_where_cond = ' 1=1 ';

			// conditions when filtered by affiliate status.
			$user_role_cond       = '';
			$affiliate_user_roles = get_option( 'affiliate_users_roles', array() );
			if ( ! empty( $affiliate_user_roles ) ) {
				$cond = array();
				foreach ( $affiliate_user_roles as $role ) {
					$cond[] = $wpdb1->prepare( // phpcs:ignore
						'um.meta_value LIKE %s',
						'%' . $wpdb->esc_like( $role ) . '%'
					);

				}
				$user_role_cond = implode( ' OR ', $cond );
			}

			$status_filters = implode( ',', $afwc_filters['affiliate_status'] );
			if ( ! empty( $is_affiliate_status_filter ) ) {
				$filters_user_where_cond .= " AND (FIND_IN_SET (um.meta_value, '" . $status_filters . "')) ";
				$filters_umeta_join_cond  = " AND um.meta_key = 'afwc_is_affiliate' ";
			} else {
				$filters_user_where_cond .= " AND (FIND_IN_SET (um.meta_value, '" . $status_filters . "') " . ( ( ! empty( $user_role_cond ) ) ? ' OR ' . $user_role_cond : '' ) . ") 
										AND um.user_id NOT IN (SELECT user_id 
																FROM {$wpdb->usermeta}
																WHERE meta_key = 'afwc_is_affiliate'
																	AND meta_value = 'no') ";
				$filters_umeta_join_cond  = " AND um.meta_key IN ('afwc_is_affiliate'" . ( ( ! empty( $user_role_cond ) ) ? ", '{$wpdb->prefix}capabilities'" : '' ) . ') ';
			}

			// Conditions when filtered by commission status.
			if ( ! empty( $afwc_filters['commission_status'] ) && is_array( $afwc_filters['commission_status'] ) ) {
				$filters_join_cond   = $referrals_join_cond;
				$filters_where_cond .= " AND (FIND_IN_SET (ref.status, '" . implode( ',', $afwc_filters['commission_status'] ) . "')) ";
			}

			// Conditions when filtered by full text searxh box.
			if ( ! empty( $search_term ) ) {
				$filters_where_cond .= $wpdb1->prepare( // phpcs:ignore
					' AND ( u.user_nicename LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s ) ',
					'%' . $wpdb->esc_like( $search_term ) . '%',
					'%' . $wpdb->esc_like( $search_term ) . '%',
					'%' . $wpdb->esc_like( $search_term ) . '%'
				);
			}

			if ( ! empty( $afwc_filters['tags'] ) ) {
				$filters_join_cond  .= " JOIN {$wpdb->prefix}term_relationships as tr
										ON(tr.object_id = u.ID) ";
				$filters_where_cond .= " AND (FIND_IN_SET(tr.term_taxonomy_id, '" . implode( ',', $afwc_filters['tags'] ) . "')) ";
			}

			$affiliate_count =  $wpdb1->get_var( // phpcs:ignore
				"SELECT COUNT( DISTINCT u.ID ) AS aff_count
												    FROM (SELECT u.ID AS ID,
															u.display_name AS display_name,
															u.user_email AS user_email,
															u.user_nicename AS user_nicename,
															(CASE WHEN um.meta_key = 'afwc_is_affiliate' THEN 1 ELSE 0 END) as priority,
															IFNULL((CASE WHEN um.meta_key = 'afwc_is_affiliate' AND um.meta_value = 'pending' THEN 1 ELSE 0 END), 0) as is_pending
															FROM $wpdb->users as u
																JOIN $wpdb->usermeta as um
																ON(um.user_id = u.ID " . $filters_umeta_join_cond . ')
															WHERE ' . $filters_user_where_cond . "
															GROUP BY ID) as u
															LEFT JOIN (SELECT IFNULL(COUNT( DISTINCT CONCAT_WS( ':', ip, user_id ) ), 0) as total_visitors,
																	affiliate_id
																FROM {$wpdb->prefix}afwc_hits
																) as hits
															ON(hits.affiliate_id = u.ID)
															" . $filters_join_cond . '
															WHERE ' . $filters_where_cond
			);
			return $affiliate_count;
		}

		/**
		 * Handler for AJAX request for getting affiliate's list
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function affiliates_list( $params = array() ) {
			global $wpdb;

			$afwc_filters            = ( ! empty( $params['filters'] ) ) ? json_decode( $params['filters'], true ) : array();
			$affiliate_ids           = array();
			$affiliates              = array();
			$params['status']        = '';
			$params['affiliate_ids'] = array();
			$params['limit']         = AFWC_AFFILIATES_LIMIT;
			$start_limit             = ( ! empty( $params['page'] ) ) ? ( intval( $params['page'] ) - 1 ) * $params['limit'] : 0;

			$params['is_export'] = ! empty( $params['is_export'] ) ? $params['is_export'] : false;

			$limit = ( empty( $params['is_export'] ) ) ? ' LIMIT ' . $start_limit . ', ' . $params['limit'] : '';

			$is_affiliate_status_filter = false;
			if ( ! empty( $afwc_filters['affiliate_status'] ) ) {
				$is_affiliate_status_filter = true;
			}

			$default_filters['affiliate_status'] = array( 'yes', 'pending' );
			$default_filters['order_status']     = array();
			$default_filters['tags']             = array();
			$default_filters['affiliate_ids']    = array();

			$search_term = ! empty( $params['q'] ) ? $params['q'] : '';

			foreach ( $default_filters as $filter => $filter_val ) {
				if ( ! empty( $afwc_filters[ $filter ] ) ) {
					$default_filters[ $filter ] = $afwc_filters[ $filter ];
				}
			}
			$afwc_filters = $default_filters;

			// code for filters.
			$wpdb1 = $wpdb;

			$referrals_join_cond     = " JOIN {$wpdb->prefix}afwc_referrals as ref
										ON(ref.affiliate_id = u.ID) ";
			$filters_join_cond       = ' LEFT ' . $referrals_join_cond;
			$filters_where_cond      = ' 1=1 ';
			$filters_umeta_join_cond = '';
			$filters_user_where_cond = ' 1=1 ';

			// conditions when filtered by affiliate status.
			$user_role_cond       = '';
			$affiliate_user_roles = get_option( 'affiliate_users_roles', array() );
			if ( ! empty( $affiliate_user_roles ) ) {
				$cond = array();
				foreach ( $affiliate_user_roles as $role ) {
					$cond[] = $wpdb1->prepare( // phpcs:ignore
						'um.meta_value LIKE %s',
						'%' . $wpdb->esc_like( $role ) . '%'
					);

				}
				$user_role_cond = implode( ' OR ', $cond );
			}

			$status_filters = implode( ',', $afwc_filters['affiliate_status'] );
			if ( ! empty( $is_affiliate_status_filter ) ) {
				$filters_user_where_cond .= " AND (FIND_IN_SET (um.meta_value, '" . $status_filters . "')) ";
				$filters_umeta_join_cond  = " AND um.meta_key = 'afwc_is_affiliate' ";
			} else {
				$filters_user_where_cond .= " AND (FIND_IN_SET (um.meta_value, '" . $status_filters . "') " . ( ( ! empty( $user_role_cond ) ) ? ' OR ' . $user_role_cond : '' ) . ") 
										AND um.user_id NOT IN (SELECT user_id 
																FROM {$wpdb->usermeta}
																WHERE meta_key = 'afwc_is_affiliate'
																	AND meta_value = 'no') ";
				$filters_umeta_join_cond  = " AND um.meta_key IN ('afwc_is_affiliate'" . ( ( ! empty( $user_role_cond ) ) ? ", '{$wpdb->prefix}capabilities'" : '' ) . ') ';
			}

			// Conditions when filtered by commission status.
			if ( ! empty( $afwc_filters['order_status'] ) ) {
				$filters_join_cond   = $referrals_join_cond;
				$filters_where_cond .= " AND (FIND_IN_SET (ref.status, '" . implode( ',', $afwc_filters['order_status'] ) . "')) ";
			}

			// Conditions when filtered by full text searxh box.
			if ( ! empty( $search_term ) ) {
				$filters_where_cond .= $wpdb1->prepare( // phpcs:ignore
					' AND ( u.user_nicename LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s ) ',
					'%' . $wpdb->esc_like( $search_term ) . '%',
					'%' . $wpdb->esc_like( $search_term ) . '%',
					'%' . $wpdb->esc_like( $search_term ) . '%'
				);
			}

			if ( ! empty( $afwc_filters['affiliate_ids'] ) && is_array( $afwc_filters['affiliate_ids'] ) ) {
				$filters_where_cond .= $wpdb1->prepare(
					' AND ( u.ID IN (' . implode( ',', array_fill( 0, count( $afwc_filters['affiliate_ids'] ), '%d' ) ) . ') )',
					$afwc_filters['affiliate_ids']
				);
			}

			if ( ! empty( $afwc_filters['tags'] ) ) {
				$filters_join_cond  .= " JOIN {$wpdb->prefix}term_relationships as tr
										ON(tr.object_id = u.ID) ";
				$filters_where_cond .= " AND (FIND_IN_SET(tr.term_taxonomy_id, '" . implode( ',', $afwc_filters['tags'] ) . "')) ";
			}

			$affiliates                   = array();
			$paid_order_statuses          = afwc_get_paid_order_status();
			$paid_order_statuses_imploded = ( ! empty( $paid_order_statuses ) ) ? implode( ',', $paid_order_statuses ) : '';

			// To get the 'total_order' count, do not eliminate referrals with the commission status as 'Draft'.
			// Why: The 'Draft' commission status is visible on the dashboard, and not including it in the 'total_order' count, will cause a mismatch in CSV and dashboard count.
			$results =  $wpdb1->get_results( // phpcs:ignore
											$wpdb1->prepare( // phpcs:ignore
												"SELECT DISTINCT u.ID AS affiliate_id,
													u.display_name AS display_name,
													u.user_email AS email,
													u.is_pending as is_pending,
													IFNULL(SUM( CASE WHEN ref.status != 'draft' AND ref.datetime BETWEEN %s AND %s THEN ref.amount ELSE 0 END), 0) as earned_commissions,
													IFNULL(SUM( CASE WHEN ref.status != 'draft' AND ref.datetime BETWEEN %s AND %s AND status = 'unpaid' AND FIND_IN_SET (ref.order_status, '" . $paid_order_statuses_imploded . "') THEN ref.amount ELSE 0 END), 0) as unpaid_commissions,
													IFNULL((CASE WHEN ref.status != 'draft' THEN ref.currency_id ELSE '' END), '') as currency,
													IFNULL(SUM( CASE WHEN ref.datetime BETWEEN %s AND %s THEN 1 ELSE 0 END), 0) as total_order,
													IFNULL(IF( ref.status != 'draft' AND ref.affiliate_id IS NOT NULL, COUNT( DISTINCT IF( ref.user_id > 0, ref.user_id, CONCAT_WS( ':', ref.ip, ref.user_id ) ) ), 0), 0) as customers_count,
													IFNULL(hits.total_visitors, 0) as total_visitors
												FROM (SELECT u.ID AS ID,
															u.display_name AS display_name,
															u.user_email AS user_email,
															u.user_nicename AS user_nicename,
															(CASE WHEN um.meta_key = 'afwc_is_affiliate' THEN 1 ELSE 0 END) as priority,
															IFNULL((CASE WHEN um.meta_key = 'afwc_is_affiliate' AND um.meta_value = 'pending' THEN 1 ELSE 0 END), 0) as is_pending
															FROM $wpdb->users as u
																JOIN $wpdb->usermeta as um
																ON(um.user_id = u.ID " . $filters_umeta_join_cond . ')
															WHERE ' . $filters_user_where_cond . "
															GROUP BY ID) as u
															LEFT JOIN (SELECT IFNULL(COUNT( DISTINCT CONCAT_WS( ':', ip, user_id ) ), 0) as total_visitors,
																	affiliate_id
																FROM {$wpdb->prefix}afwc_hits
																WHERE datetime BETWEEN %s AND %s
																GROUP BY affiliate_id) as hits
															ON(hits.affiliate_id = u.ID)
															" . $filters_join_cond . '
															WHERE ' . $filters_where_cond . '
															GROUP BY affiliate_id
															ORDER BY earned_commissions DESC, customers_count DESC, u.priority DESC, total_visitors DESC
															' . $limit,
												$params['from'],
												$params['to'],
												$params['from'],
												$params['to'],
												$params['from'],
												$params['to'],
												$params['from'],
												$params['to']
											),
				'ARRAY_A'
			);
			if ( count( $results ) > 0 ) {
				foreach ( $results as $affiliate ) {
					$id                = ( ( ! empty( $affiliate['affiliate_id'] ) ) ? $affiliate['affiliate_id'] : 0 );
					$earned_commission = ( ! empty( $affiliate['earned_commissions'] ) ) ? $affiliate['earned_commissions'] : 0;
					$unpaid_commission = ( ! empty( $affiliate['unpaid_commissions'] ) ) ? $affiliate['unpaid_commissions'] : 0;

					if ( empty( $id ) ) {
						continue;
					}

					$affiliate_ids[] = $id;
					$affiliates[]    = array(
						'affiliate_id'       => $id,
						'name'               => ( ( ! empty( $affiliate['display_name'] ) ) ? html_entity_decode( $affiliate['display_name'], ENT_QUOTES ) : '' ),
						'email'              => ( ( ! empty( $affiliate['email'] ) ) ? $affiliate['email'] : '' ),
						'earned_commissions' => ( ( empty( $params['is_export'] ) ) ? afwc_format_price( $earned_commission ) : $earned_commission ),
						'unpaid_commissions' => ( ( empty( $params['is_export'] ) ) ? afwc_format_price( $unpaid_commission ) : $unpaid_commission ),
						'currency'           => ( ( ! empty( $affiliate['currency'] ) ) ? $affiliate['currency'] : AFWC_CURRENCY_CODE ),
						'customers_count'    => ( ( ! empty( $affiliate['customers_count'] ) ) ? $affiliate['customers_count'] : 0 ),
						'total_order'        => ( ( ! empty( $affiliate['total_order'] ) ) ? $affiliate['total_order'] : 0 ),
						'total_visitors'     => ( ( ! empty( $affiliate['total_visitors'] ) ) ? $affiliate['total_visitors'] : 0 ),
						'pending'            => ( ( ! empty( $affiliate['is_pending'] ) ) ? intval( $affiliate['is_pending'] ) : 0 ),
						'paypal_email'       => get_user_meta( $id, 'afwc_paypal_email', true ),
					);
				}
			}

			return $affiliates;
		}

		/**
		 * Function to generate and export the CSV data
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function export_affiliates( $params = array() ) {
			check_admin_referer( 'afwc-admin-export-affiliates', 'security' );
			global $wpdb;

			$affiliates_data     = array();
			$params['is_export'] = true;
			$affiliates_data     = $this->affiliates_list( $params );
			$type                = ! empty( $params['type'] ) ? $params['type'] : 'standard';

			$wp_upload_path = wp_get_upload_dir();

			if ( empty( $wp_upload_path ) || empty( $wp_upload_path['basedir'] ) ) {
				Affiliate_For_WooCommerce::get_instance()->log( 'error', _x( 'WordPress upload directory is not set.', 'csv export error message', 'affiliate-for-woocommerce' ) );
				return;
			}
			$path     = $wp_upload_path['basedir'] . '/woocommerce_uploads/';
			$filename = sanitize_title( get_bloginfo( 'name' ) ) . '_' . $type . '_affiliates_' . gmdate( 'd-M-Y' ) . '.csv';
			$file     = $path . $filename;

			// open raw memory as file so no temp files needed, you might run out of memory though.
			$f = fopen( $file , 'w+' );// phpcs:ignore
			// loop over the input array.
			if ( 'standard' === $type ) {
				$headers = array( 'Name', 'Email', 'Earned Commissions', 'Unpaid Commissions', 'Total Order', 'Total Visitors' );
			} elseif ( 'mass_payment' === $type ) {
				$headers = array( 'Emails', 'Unpaid Commissions', 'Currency' );
			}
			fwrite( $f, implode( ',', $headers ) . PHP_EOL );// phpcs:ignore

			foreach ( $affiliates_data as $row ) {
				// format array.
				$line = array();
				if ( 'standard' === $type ) {
					$line['name']               = $row['name'];
					$line['email']              = $row['email'];
					$line['earned_commissions'] = $row['earned_commissions'];
					$line['unpaid_commissions'] = $row['unpaid_commissions'];
					$line['total_order']        = $row['total_order'];
					$line['total_visitors']     = $row['total_visitors'];
				} elseif ( 'mass_payment' === $type ) {
					$line['email']              = ! empty( $row['paypal_email'] ) ? $row['paypal_email'] : $row['email'];
					$line['unpaid_commissions'] = $row['unpaid_commissions'];
					$line['currency']           = $row['currency'];
				}
				// generate csv lines from the inner arrays.
				fwrite( $f, implode( ',', $line ) . PHP_EOL ); // phpcs:ignore
			}
			// reset the file pointer to the start of the file.
			fseek( $f, 0 );
			// tell the browser it's going to be a csv file.
			header( 'Content-type: text/x-csv; charset=UTF-8' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '";' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );

			// make php send the generated csv lines to the browser.
			function_exists( 'fpassthru' ) ? fpassthru( $f ) : readfile( $file ); // phpcs:ignore

			// Delete file from uploads.
			wp_delete_file( $file );
			exit();
		}


		/**
		 * Handler for AJAX request for getting affiliate order details.
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function order_details( $params = array() ) {
			check_admin_referer( 'afwc-admin-order-details', 'security' );
			$current_data = new AFWC_Admin_Affiliates(
				! empty( $params['affiliate_id'] ) ? $params['affiliate_id'] : 0,
				! empty( $params['from'] ) ? $params['from'] : '',
				! empty( $params['to'] ) ? $params['to'] : '',
				! empty( $params['page'] ) ? intval( $params['page'] ) : 1
			);
			$filters      = ! empty( $params['filters'] ) ? json_decode( $params['filters'], true ) : array();
			wp_send_json( is_callable( array( $current_data, 'get_affiliates_order_details' ) ) ? $current_data->get_affiliates_order_details( $filters ) : array() );
		}

		/**
		 * Handler for AJAX request for getting affiliate payout details.
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function payout_details( $params = array() ) {
			check_admin_referer( 'afwc-admin-payout-details', 'security' );

			if ( empty( $params['affiliate_id'] ) ) {
				wp_send_json(
					array(
						'ACK'     => 'Error',
						'message' => _x( 'Required parameter missing - Affiliate ID', 'error message when fetching payouts', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$current_data = new AFWC_Admin_Affiliates(
				$params['affiliate_id'],
				! empty( $params['from'] ) ? $params['from'] : '',
				! empty( $params['to'] ) ? $params['to'] : '',
				! empty( $params['page'] ) ? intval( $params['page'] ) : 1
			);
			wp_send_json( is_callable( array( $current_data, 'get_affiliates_payout_history' ) ) ? $current_data->get_affiliates_payout_history() : array() );
		}

		/**
		 * Handler for AJAX request for getting products.
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function products( $params = array() ) {
			check_admin_referer( 'afwc-admin-products-details', 'security' );

			if ( empty( $params['affiliate_id'] ) ) {
				wp_send_json(
					array(
						'ACK'     => 'Error',
						'message' => _x( 'Required parameter missing - Affiliate ID', 'error message when fetching products', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$affiliate_data = new AFWC_Admin_Affiliates(
				$params['affiliate_id'],
				! empty( $params['from'] ) ? $params['from'] : '',
				! empty( $params['to'] ) ? $params['to'] : '',
				! empty( $params['page'] ) ? intval( $params['page'] ) : 1
			);

			wp_send_json( is_callable( array( $affiliate_data, 'get_affiliates_products' ) ) ? $affiliate_data->get_affiliates_products() : array() );
		}

		/**
		 * Handler for AJAX request for getting profile data.
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function profile_data( $params = array() ) {
			check_admin_referer( 'afwc-admin-profile-data', 'security' );

			$affiliate_id = is_numeric( $params['affiliate_id'] ) && $params['affiliate_id'] > 0 ? absint( $params['affiliate_id'] ) : 0;

			if ( empty( $affiliate_id ) ) {
				wp_send_json(
					array(
						'ACK'     => 'Error',
						'message' => _x( 'Required parameter missing - Affiliate ID.', 'error message when fetching profile details', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$affiliate_obj  = new AFWC_Affiliate( $affiliate_id );
			$affiliate_data = new AFWC_Admin_Affiliates( array( $affiliate_id ) );

			$url_identifier                     = get_user_meta( $affiliate_id, 'afwc_ref_url_id', true );
			$allow_custom_url_identifier        = get_option( 'afwc_allow_custom_affiliate_identifier', 'yes' );
			$custom_url_identifier_setting_link = 'yes' === $allow_custom_url_identifier ? '' : admin_url( 'admin.php?page=wc-settings&tab=affiliate-for-woocommerce-settings&section=referrals#afwc_allow_custom_affiliate_identifier' );

			$referral_coupons     = array();
			$use_referral_coupons = get_option( 'afwc_use_referral_coupons', 'yes' );
			if ( 'yes' === $use_referral_coupons ) {
				$afwc_coupon = AFWC_Coupon::get_instance();
				if ( is_callable( array( $afwc_coupon, 'get_referral_coupon' ) ) ) {
					$referral_coupons = $afwc_coupon->get_referral_coupon(
						array(
							'user_id'  => $affiliate_id,
							'with_url' => true,
						)
					);
				}
			}

			$parent_affiliate_data = array();
			$multi_tier_enabled    = ( is_callable( array( 'AFWC_Multi_Tier', 'is_enabled' ) ) && AFWC_Multi_Tier::is_enabled() ) ? 'yes' : 'no';
			if ( 'yes' === $multi_tier_enabled ) {
				$afwc_multi_tier = AFWC_Multi_Tier::get_instance();
				$parent_chain    = $afwc_multi_tier->get_parents( $affiliate_id );
				$parent_id       = ! empty( $parent_chain ) && is_array( $parent_chain ) ? intval( current( $parent_chain ) ) : 0;
				if ( ! empty( $parent_id ) ) {
					$parent                = get_user_by( 'id', $parent_id );
					$parent_string         = $parent instanceof WP_User && ! empty( $parent->user_email ) ? sprintf( '%1$s (#%2$d &ndash; %3$s)', ( ! empty( $parent->display_name ) ? $parent->display_name : '' ), absint( $parent_id ), $parent->user_email ) : '';
					$parent_affiliate_data = array(
						'id'   => $parent_id,
						'text' => $parent_string,
					);
				}
			}

			$is_landing_page_enabled = ( is_callable( array( 'AFWC_Landing_Page', 'is_enabled' ) ) && AFWC_Landing_Page::is_enabled() ) ? 'yes' : 'no';

			$is_ltc_enabled = get_option( 'afwc_enable_lifetime_commissions', 'no' );

			$is_automatic_payout_enabled           = get_option( 'afwc_enable_automatic_payouts', 'no' );
			$is_selected_for_automatic_payout      = false;
			$automatic_payout_related_setting_link = '';
			if ( 'yes' === $is_automatic_payout_enabled ) {
				$afwc_automatic_payout_includes        = get_option( 'afwc_automatic_payout_includes', array() );
				$afwc_automatic_payout_includes        = is_array( $afwc_automatic_payout_includes ) ? array_map( 'intval', $afwc_automatic_payout_includes ) : array();
				$is_selected_for_automatic_payout      = in_array( $affiliate_id, $afwc_automatic_payout_includes, true );
				$automatic_payout_related_setting_link = admin_url( 'admin.php?page=wc-settings&tab=affiliate-for-woocommerce-settings&section=payouts#afwc_automatic_payout_includes' );
			}

			$affiliate_paypal_email_data = array(
				'paypal_email'            => get_user_meta( $affiliate_id, 'afwc_paypal_email', true ),
				'afwc_allow_paypal_email' => 'yes' === get_option( 'afwc_allow_paypal_email', 'no' ),
			);

			$payout_method = get_user_meta( $affiliate_id, 'afwc_payout_method', true );

			wp_send_json(
				array(
					'ACK'  => 'Success',
					'data' => array(
						'custom_url_identifier_data'   => array(
							'is_enabled'   => 'yes' === $allow_custom_url_identifier,
							'setting_link' => $custom_url_identifier_setting_link,
						),
						'identifier'                   => ! empty( $url_identifier ) ? $url_identifier : ( is_callable( array( $affiliate_obj, 'get_identifier' ) ) ? $affiliate_obj->get_identifier() : '' ),
						'is_referral_coupon_enabled'   => get_option( 'afwc_use_referral_coupons', 'yes' ),
						'coupons'                      => $referral_coupons,
						'all_available_tags'           => afwc_get_user_tags_id_name_map(),
						'tags'                         => is_callable( array( $affiliate_data, 'get_affiliates_tags' ) ) ? $affiliate_data->get_affiliates_tags() : array(),
						'parent_affiliate_data'        => $parent_affiliate_data,
						'is_landing_page_enabled'      => $is_landing_page_enabled,
						'landing_pages'                => ( 'yes' === $is_landing_page_enabled && is_callable( array( $affiliate_data, 'get_landing_pages' ) ) ) ? $affiliate_data->get_landing_pages() : array(),
						'is_ltc_enabled'               => $is_ltc_enabled,
						'is_ltc_enabled_for_affiliate' => is_callable( array( $affiliate_obj, 'is_ltc_enabled' ) ) && $affiliate_obj->is_ltc_enabled(),
						'ltc_customers'                => ( 'yes' === $is_ltc_enabled && is_callable( array( $affiliate_data, 'get_ltc_customers' ) ) ) ? $affiliate_data->get_ltc_customers() : array(),
						'paypal_email_data'            => $affiliate_paypal_email_data,
						'automatic_payout_data'        => array(
							'is_enabled'   => 'yes' === $is_automatic_payout_enabled,
							'is_selected'  => $is_selected_for_automatic_payout,
							'setting_link' => $automatic_payout_related_setting_link,
						),
						'registration_form_data'       => is_callable( array( $affiliate_obj, 'get_registration_form_data' ) ) ? $affiliate_obj->get_registration_form_data() : array(),
						'payout_method_title'          => ( ! empty( $payout_method ) ? afwc_get_payout_methods( $payout_method ) : '' ),
					),
				)
			);
		}

		/**
		 * Function to get affiliate children chain data.
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function affiliate_chain_data( $params = array() ) {
			$security = ( ! empty( $params['security'] ) ) ? wc_clean( wp_unslash( $params['security'] ) ) : ''; // phpcs:ignore

			if ( empty( $security ) ) {
				return;
			}

			if ( empty( $params['affiliate_id'] ) ) {
				wp_send_json(
					array(
						'ACK'   => 'Error',
						'error' => _x( 'Required params missing', 'error message when missing necessary parameters', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$access = false;

			// Check for admin nonce.
			if ( afwc_current_user_can_manage_affiliate() && wp_verify_nonce( $security, 'afwc-admin-multi-tier-data' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				$access = true;
			}

			// Check for frontend nonce.
			if ( ! $access && ! ( wp_verify_nonce( $security, 'afwc-multi-tier-data' ) && ( intval( $params['affiliate_id'] ) === get_current_user_id() ) ) ) {
				return wp_send_json(
					array(
						'ACK' => 'Failed',
						'msg' => _x( 'You do not have permission to fetch the multi-tier details.', 'multi tier fetching error message', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$affiliate_multi_tier = AFWC_Multi_Tier::get_instance();
			wp_send_json(
				array(
					'ACK'  => 'Success',
					'data' => array(
						'multiTierChain' => ( $affiliate_multi_tier instanceof AFWC_Multi_Tier && is_callable( array( $affiliate_multi_tier, 'get_children_tree' ) ) ) ? $affiliate_multi_tier->get_children_tree( intval( $params['affiliate_id'] ) ) : array(),
					),
				)
			);
		}

		/**
		 * Handler for AJAX request for getting affiliate KPI details.
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function affiliate_kpi_details( $params = array() ) {
			check_admin_referer( 'afwc-admin-affiliate-kpi-data', 'security' );

			if ( empty( $params['affiliate_id'] ) ) {
				wp_send_json(
					array(
						'ACK'     => 'Error',
						'message' => _x( 'Required parameter missing - Affiliate ID.', 'error message when fetching affiliate KPI details', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$affiliate_id = intval( $params['affiliate_id'] );

			$current_data = new AFWC_Admin_Affiliates(
				$affiliate_id,
				! empty( $params['from'] ) ? $params['from'] : '',
				! empty( $params['to'] ) ? $params['to'] : ''
			);

			$current_data->get_all_data();

			$earned_commission = floatval( ! empty( $current_data->earned_commissions ) ? $current_data->earned_commissions : 0 );
			$unpaid_commission = floatval( ! empty( $current_data->unpaid_commissions ) ? $current_data->unpaid_commissions : 0 );
			$visitors_count    = intval( ! empty( $current_data->visitors_count ) ? $current_data->visitors_count : 0 );
			$customers_count   = intval( ! empty( $current_data->customers_count ) ? $current_data->customers_count : 0 );

			$data = array(
				'current' => array(
					'net_affiliates_sales' => afwc_format_price( floatval( ! empty( $current_data->net_affiliates_sales ) ? $current_data->net_affiliates_sales : 0 ) ),
					'unpaid_commissions'   => afwc_format_price( $unpaid_commission ),
					'paid_commissions'     => afwc_format_price( $earned_commission - $unpaid_commission ),
					'visitors_count'       => afwc_format_price( $visitors_count, 0 ),
					'customers_count'      => afwc_format_price( $customers_count, 0 ),
					'conversion_rate'      => afwc_format_price( ( ( $visitors_count > 0 ) ? $customers_count * 100 / $visitors_count : 0 ) ),
					'earned_commissions'   => afwc_format_price( $earned_commission ),
					'gross_commissions'    => afwc_format_price( floatval( ! empty( $current_data->gross_commissions ) ? $current_data->gross_commissions : 0 ) ),
				),
			);

			if ( ! empty( $params['return_data'] ) && 'all' === $params['return_data'] ) {
				$all_time_data                  = new AFWC_Admin_Affiliates( $affiliate_id );
				$all_time_commissions_customers = is_callable( array( $all_time_data, 'get_commissions_customers' ) ) ? $all_time_data->get_commissions_customers() : array();
				$all_time_visitor_count         = intval( is_callable( array( $all_time_data, 'get_visitors_count' ) ) ? $all_time_data->get_visitors_count() : 0 );
				$all_time_paid_commissions      = floatval( ( ! empty( $all_time_commissions_customers['paid_commissions'] ) ) ? $all_time_commissions_customers['paid_commissions'] : 0 );
				$all_time_unpaid_commissions    = floatval( ( ! empty( $all_time_commissions_customers['unpaid_commissions'] ) ) ? $all_time_commissions_customers['unpaid_commissions'] : 0 );

				$data['allTime'] = array(
					'net_affiliates_sales' => afwc_format_price( floatval( is_callable( array( $all_time_data, 'get_net_affiliates_sales' ) ) ? $all_time_data->get_net_affiliates_sales() : 0 ) ),
					'unpaid_commissions'   => afwc_format_price( $all_time_unpaid_commissions ),
					'paid_commissions'     => afwc_format_price( $all_time_paid_commissions ),
					'visitors_count'       => afwc_format_price( $all_time_visitor_count, 0 ),
					'customers_count'      => afwc_format_price( ( ( ! empty( $all_time_commissions_customers['customers_count'] ) ) ? $all_time_commissions_customers['customers_count'] : 0 ), 0 ),
					'conversion_rate'      => afwc_format_price( ( ( $all_time_visitor_count > 0 ) ? $all_time_commissions_customers['customers_count'] * 100 / $all_time_visitor_count : 0 ) ),
					'earned_commissions'   => afwc_format_price( floatval( $all_time_paid_commissions + $all_time_unpaid_commissions ) ),
				);
			}

			wp_send_json(
				array(
					'stats' => $data,
				)
			);
		}

		/**
		 * Handler for AJAX request for getting affiliate details
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function affiliate_details( $params = array() ) {
			check_admin_referer( 'afwc-admin-affiliate-details', 'security' );

			if ( empty( $params['affiliate_id'] ) ) {
				wp_send_json(
					array(
						'ACK'     => 'Error',
						'message' => _x( 'Required parameter missing - Affiliate ID.', 'error message when fetching affiliate details', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$affiliate_id = intval( $params['affiliate_id'] );
			$is_affiliate = '';
			$is_affiliate = get_user_meta( $affiliate_id, 'afwc_is_affiliate', true );
			$current_data = new AFWC_Admin_Affiliates( $affiliate_id );
			$details      = is_callable( array( $current_data, 'get_affiliates_details' ) ) ? $current_data->get_affiliates_details() : array();

			if ( 'pending' === $is_affiliate ) {
				$affiliate_details = array(
					'name'         => ! empty( $details[ $affiliate_id ]['name'] ) ? $details[ $affiliate_id ]['name'] : '',
					'affiliate_id' => $affiliate_id,
					'email'        => ! empty( $details[ $affiliate_id ]['email'] ) ? sanitize_email( $details[ $affiliate_id ]['email'] ) : '',
					'edit_url'     => admin_url( 'user-edit.php?user_id=' . $affiliate_id ) . '#afwc-settings',
					'avatar_url'   => get_avatar_url( $affiliate_id, array( 'size' => 60 ) ),
					'pending'      => true,
				);
				wp_send_json( $affiliate_details );
			}

			$affiliate = new AFWC_Affiliate( $affiliate_id );

			$paypal     = is_callable( array( 'AFWC_PayPal_API', 'get_instance' ) ) ? AFWC_PayPal_API::get_instance() : null;
			$is_payable = ! empty( $paypal ) && is_callable( array( $paypal, 'is_enabled' ) ) && $paypal->is_enabled();

			// Get affiliate PayPal email address based on the show PayPal email address setting.
			$afwc_paypal_email = ( 'yes' === get_option( 'afwc_allow_paypal_email', 'no' ) ) ? get_user_meta( $affiliate_id, 'afwc_paypal_email', true ) : '';

			// Stripe details.
			$stripe            = is_callable( array( 'AFWC_Stripe_API', 'get_instance' ) ) ? AFWC_Stripe_API::get_instance() : null;
			$is_stripe_payable = ! empty( $stripe ) && is_callable( array( $stripe, 'is_enabled' ) ) && $stripe->is_enabled();
			if ( true === $is_stripe_payable ) {
				$stripe_account_id = get_user_meta( $affiliate_id, 'afwc_stripe_user_id', true );
			}

			$affiliate_id = afwc_get_affiliate_id_based_on_user_id( $affiliate_id );

			wp_send_json(
				array(
					'name'                    => ! empty( $details[ $affiliate_id ]['name'] ) ? $details[ $affiliate_id ]['name'] : '',
					'affiliate_id'            => $affiliate_id,
					'email'                   => ! empty( $details[ $affiliate_id ]['email'] ) ? sanitize_email( $details[ $affiliate_id ]['email'] ) : '',
					'edit_url'                => admin_url( 'user-edit.php?user_id=' . $affiliate_id ) . '#afwc-settings',
					'referral_url'            => is_callable( array( $affiliate, 'get_affiliate_link' ) ) ? $affiliate->get_affiliate_link() : '',
					'is_paypal_email'         => ( true === $is_payable && ! empty( $afwc_paypal_email ) ),
					'avatar_url'              => get_avatar_url( $affiliate_id, array( 'size' => 60 ) ),
					'formatted_join_duration' => $current_data->get_formatted_join_duration(),
					'signup_date'             => is_callable( array( $affiliate, 'get_signup_date' ) ) ? $affiliate->get_signup_date( 'd M Y' ) : '',
					'rejected'                => 'no' === $is_affiliate,
					'is_stripe_account'       => ( true === $is_stripe_payable && ! empty( $stripe_account_id ) ),
					'payout_method'           => get_user_meta( $affiliate_id, 'afwc_payout_method', true ),
				)
			);
		}

		/**
		 * Update feedback option
		 *
		 * @param array $params The Params.
		 */
		public function update_feedback( $params = array() ) {
			check_admin_referer( 'afwc-admin-update-feedback', 'security' );
			$update_action = ! empty( $params['update_action'] ) ? $params['update_action'] : '';
			if ( ! empty( $update_action ) ) {
				if ( 'close' === $update_action ) {
					$current_date = gmdate( 'Y-m-d', Affiliate_For_WooCommerce::get_offset_timestamp() );
					update_option( 'afwc_feedback_close_date', $current_date, 'no' );
				} else {
					update_option( 'afwc_feedback_option_' . $update_action, true, 'no' );
				}
				wp_send_json(
					array(
						'ACK' => 'Success',
						'msg' => __( 'Feedback option updated', 'affiliate-for-woocommerce' ),
					)
				);
			}
		}

		/**
		 * Set/Update Commission Status.
		 *
		 * @param array $args The arguments.
		 *
		 * @return int|bool Number of rows affected or boolean false on error.
		 */
		public function set_commission_status( $args = array() ) {

			// Return if status is not provided.
			if ( empty( $args['status'] ) ) {
				return false;
			}

			$current_user_id = get_current_user_id();

			// Return if the current user is null.
			if ( empty( $current_user_id ) ) {
				return false;
			}

			global $wpdb;

			if ( ! empty( $args['referral_ids'] ) ) {
				// Create temporary key to save referral ids.
				// TODO: check why current_user_id is used instead of uniqid.
				$temp_referral_ids_db_key = 'afwc_change_commission_status_referrals_' . $current_user_id;

				// Store referrals ids temporarily in option.
				update_option( $temp_referral_ids_db_key, implode( ',', $args['referral_ids'] ), 'no' );

				$result = $wpdb->query( // phpcs:ignore
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}afwc_referrals
							SET status = %s
							WHERE FIND_IN_SET ( referral_id, ( SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s ) )", // phpcs:ignore
						$args['status'],
						$temp_referral_ids_db_key
					)
				);

				// Delete the temporary option.
				delete_option( $temp_referral_ids_db_key );

				return $result;
			} elseif ( ! empty( $args['order_ids'] ) && ! empty( $args['affiliate_id'] ) ) {
				// Create temporary key to save order ids.
				// TODO: check why current_user_id is used instead of uniqid.
				$temp_order_ids_db_key = 'afwc_change_commission_status_order_ids_' . $current_user_id;

				// Store order ids temporarily in option.
				update_option( $temp_order_ids_db_key, implode( ',', $args['order_ids'] ), 'no' );

				$result = $wpdb->query( // phpcs:ignore
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}afwc_referrals
							SET status = %s
							WHERE affiliate_id = %d
							AND FIND_IN_SET ( post_id, ( SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s ) )", // phpcs:ignore
						$args['status'],
						intval( $args['affiliate_id'] ),
						$temp_order_ids_db_key
					)
				);

				// Delete the temporary option.
				delete_option( $temp_order_ids_db_key );

				return $result;

			}

			return false;
		}

		/**
		 * Update the payout method with ajax.
		 *
		 * @return void
		 */
		public function update_payout_method() {

			check_admin_referer( 'afwc-admin-update-payout-method', 'security' );

			$afwc_paypal = is_callable( array( 'AFWC_PayPal_API', 'get_instance' ) ) ? AFWC_PayPal_API::get_instance() : null;

			if ( ! empty( $afwc_paypal ) && is_callable( array( $afwc_paypal, 'check_for_paypal_payout' ) ) ) {
				$afwc_paypal->check_for_paypal_payout();
			}

			wp_send_json(
				array(
					'ACK' => true,
				)
			);
		}

		/**
		 * Link the Lifetime commission customer.
		 *
		 * @param array $params Params from the AJAX request.
		 *
		 * @return void
		 */
		public function link_ltc_customers( $params = array() ) {

			check_admin_referer( 'afwc-admin-link-ltc-customers', 'security' );

			if ( empty( $params['customer'] ) || empty( $params['affiliate_id'] ) ) {
				wp_send_json(
					array(
						'ACK' => 'Error',
						'msg' => _x( 'Customer or affiliate ID missing', 'error message for missing of required parameter for linking the lifetime customer', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$linked_affiliate = afwc_get_ltc_affiliate_by_customer( $params['customer'] );

			if ( ! empty( $linked_affiliate ) ) {
				wp_send_json(
					array(
						'ACK' => 'Error',
						'msg' => intval( $linked_affiliate ) === intval( $params['affiliate_id'] )
								? _x( 'Customer is already linked to this affiliate', 'error message on linking the customer due to the customer is already linked with the same affiliate', 'affiliate-for-woocommerce' )
								: _x( 'Customer is already linked with another affiliate', 'error message on linking the customer due to the customer is already linked with another affiliate', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$affiliate = new AFWC_Admin_Affiliates( $params['affiliate_id'] );

			wp_send_json(
				array(
					'ACK' => is_callable( array( $affiliate, 'add_ltc_customers' ) ) && $affiliate->add_ltc_customers( $params['customer'] ) ? 'Success' : 'Error',
				)
			);
		}

		/**
		 * Unlink the Lifetime commission customer.
		 *
		 * @param array $params Params from the AJAX request.
		 *
		 * @return void
		 */
		public function unlink_ltc_customers( $params = array() ) {

			check_admin_referer( 'afwc-admin-unlink-ltc-customers', 'security' );

			if ( empty( $params['customer'] ) || empty( $params['affiliate_id'] ) ) {
				wp_send_json(
					array(
						'ACK' => 'Error',
						'msg' => _x( 'Customer or affiliate ID missing', 'error message for missing of required parameter for unlinking the lifetime customer', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$affiliate = new AFWC_Admin_Affiliates( $params['affiliate_id'] );

			wp_send_json(
				array(
					'ACK' => ( is_callable( array( $affiliate, 'remove_ltc_customers' ) ) && $affiliate->remove_ltc_customers( $params['customer'] ) ) ? 'Success' : 'Error',
				)
			);
		}


		/**
		 * Search the customers.
		 *
		 * @param array $params Params from the AJAX request.
		 *
		 * @return void
		 */
		public function search_ltc_customers( $params = array() ) {

			check_admin_referer( 'afwc-admin-search-ltc-customers', 'security' );

			if ( empty( $params['term'] ) ) {
				wp_send_json(
					array(
						'ACK' => 'Error',
						'msg' => _x( 'Search term is missing', 'error message for missing of search term of linking lifetime customer', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$affiliate   = new AFWC_Admin_Affiliates();
			$search_list = is_callable( array( $affiliate, 'search_ltc_customers' ) ) ? $affiliate->search_ltc_customers( $params['term'] ) : array();

			wp_send_json(
				array(
					'ACK'  => 'Success',
					'data' => $search_list,
				)
			);
		}

		/**
		 * Search for affiliate tags and return
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function search_affiliate_tags( $params = array() ) {

			check_admin_referer( 'afwc-admin-search-affiliate-tags', 'security' );

			if ( ! afwc_current_user_can_manage_affiliate() ) {
				wp_die( esc_html_x( 'You are not allowed to use this action', 'authorization failure message', 'affiliate-for-woocommerce' ) );
			}

			$term = ( ! empty( $params['term'] ) ) ? (string) urldecode( stripslashes( wp_strip_all_tags( $params['term'] ) ) ) : '';
			if ( empty( $term ) ) {
				wp_die();
			}

			$tags = array();
			$args = array(
				'taxonomy'   => 'afwc_user_tags', // taxonomy name.
				'hide_empty' => false,
				'name__like' => $term,
			);

			$raw_tags = get_terms( $args );
			if ( $raw_tags ) {
				foreach ( $raw_tags as $key => $value ) {
					$tags[ $value->term_id ] = $value->name;
				}
			}
			echo wp_json_encode( $tags );
			wp_die();
		}

		/**
		 * Search for affiliate parent and return
		 *
		 * @return void
		 */
		public function search_for_parent_affiliate() {

			check_admin_referer( 'afwc-admin-search-for-parent-affiliate', 'security' );

			if ( ! afwc_current_user_can_manage_affiliate() ) {
				wp_die( esc_html_x( 'You are not allowed to use this action', 'authorization failure message', 'affiliate-for-woocommerce' ) );
			}

			global $affiliate_for_woocommerce;

			$term = ( ! empty( $_GET['term'] ) ) ? (string) urldecode( stripslashes( wp_strip_all_tags( $_GET['term'] ) ) ) : ''; // phpcs:ignore
			$user_id = ( ! empty( $_GET['user_id'] ) ) ? absint( stripslashes( wp_strip_all_tags( $_GET['user_id'] ) ) ) : 0; // phpcs:ignore
			if ( empty( $term ) ) {
				wp_die();
			}
			$excludes = apply_filters( 'afwc_exclude_parent_for_affiliate', array( $user_id ), $user_id );
			$args     = array(
				'search'  => '*' . $term . '*',
				'exclude' => $excludes,
			);
			$users    = is_callable( array( $affiliate_for_woocommerce, 'get_affiliates' ) ) ? $affiliate_for_woocommerce->get_affiliates( $args ) : array();
			$users    = ! empty( $users ) ? $users : array();

			echo wp_json_encode( $users );
			wp_die();
		}

		/**
		 * Update the affiliate profile changes.
		 *
		 * @param array $params Params from the AJAX request.
		 *
		 * @throws Exception If any error during the process.
		 */
		public function update_affiliate_profile( $params = array() ) {
			check_admin_referer( 'afwc-admin-save-profile-changes', 'security' );

			if ( ! afwc_current_user_can_manage_affiliate() ) {
				wp_send_json_error( esc_html_x( 'You are not allowed to use this action', 'authorization failure message', 'affiliate-for-woocommerce' ) );
			}

			$affiliate_id = ( ! empty( $params['affiliate_id'] ) ) ? absint( $params['affiliate_id'] ) : 0;
			if ( empty( $affiliate_id ) ) {
				wp_send_json_error( esc_html_x( 'Affiliate ID is missing', 'error message for missing affiliate ID', 'affiliate-for-woocommerce' ) );
			}

			$profile_data = ! empty( $params['profile_data'] ) ? json_decode( $params['profile_data'], true ) : array();
			$profile_data = is_array( $profile_data ) ? $profile_data : array();

			// Referral URL Identifier update.
			if ( isset( $profile_data['identifier'] ) ) {

				global $affiliate_for_woocommerce;

				try {
					if ( ! is_callable( array( $affiliate_for_woocommerce, 'save_ref_url_identifier' ) ) || ! $affiliate_for_woocommerce->save_ref_url_identifier( $affiliate_id, $profile_data['identifier'] ) ) {
						throw new Exception(
							_x(
								'The URL identifier could not updated',
								'referral url identifier updating error message',
								'affiliate-for-woocommerce'
							)
						);
					}
				} catch ( Exception $e ) {
					wp_send_json_error(
						is_callable( array( $e, 'getMessage' ) ) ? $e->getMessage() : _x(
							'Something went wrong',
							'referral url identifier updating error message',
							'affiliate-for-woocommerce'
						)
					);
				}
			}

			// PayPal Email update.
			if ( isset( $profile_data['paypalEmail'] ) ) {
				$affiliate_paypal_email = ! empty( $profile_data['paypalEmail'] ) ? $profile_data['paypalEmail'] : '';
				if ( ! empty( $affiliate_paypal_email ) ) {
					if ( is_email( $affiliate_paypal_email ) ) {
						update_user_meta( $affiliate_id, 'afwc_paypal_email', sanitize_email( $affiliate_paypal_email ) );
					} else {
						wp_send_json_error( esc_html_x( 'Invalid PayPal email address', 'error message for invalid paypal email address', 'affiliate-for-woocommerce' ) );
					}
				} else {
					delete_user_meta( $affiliate_id, 'afwc_paypal_email' );
				}
			}

			// Affiliate Tags update.
			if ( isset( $profile_data['affiliateTags'] ) ) {
				$affiliate_tags = ( ! empty( $profile_data['affiliateTags'] ) && is_array( $profile_data['affiliateTags'] ) ) ? $profile_data['affiliateTags'] : array();
				if ( ! empty( $affiliate_tags ) ) {
					foreach ( $affiliate_tags as $key => $value ) {
						if ( ctype_digit( $value ) ) {
							$term_name              = get_term( $value )->name;
							$affiliate_tags[ $key ] = $term_name;
						}
					}
				}
				wp_set_object_terms( $affiliate_id, $affiliate_tags, 'afwc_user_tags' );
			}

			// Parent Affiliate update.
			if ( isset( $profile_data['parentAffiliate'] ) ) {
				$parent_affiliate = ! empty( $profile_data['parentAffiliate'] ) ? absint( $profile_data['parentAffiliate'] ) : 0;
				$afwc_multi_tier  = AFWC_Multi_Tier::get_instance();
				if ( ! empty( $parent_affiliate ) ) {
					$afwc_multi_tier->assign_parent( $affiliate_id, $parent_affiliate );
				} else {
					$afwc_multi_tier->remove_parent( $affiliate_id );
				}
			}

			// Return success in AJAX response data.
			wp_send_json_success();
		}

		/**
		 * Handler for AJAX request for getting affiliate's visitor details.
		 *
		 * @param array $params Params from the AJAX request.
		 */
		public function visitor_details( $params = array() ) {
			check_admin_referer( 'afwc-admin-visitor-details', 'security' );

			$current_data = new AFWC_Admin_Affiliates(
				! empty( $params['affiliate_id'] ) ? $params['affiliate_id'] : 0,
				! empty( $params['from'] ) ? $params['from'] : '',
				! empty( $params['to'] ) ? $params['to'] : '',
				! empty( $params['page'] ) ? intval( $params['page'] ) : 0
			);

			wp_send_json( is_callable( array( $current_data, 'get_visitor_details' ) ) ? $current_data->get_visitor_details() : array() );
		}

		/**
		 * Handler for AJAX request for getting payout KPI details.
		 * Currently it returns the all time KPI.
		 *
		 * @param array $params Params from the AJAX request.
		 *
		 * @return void.
		 */
		public function payout_kpi_details( $params = array() ) {
			check_admin_referer( 'afwc-admin-payout-kpi-data', 'security' );

			if ( empty( $params['affiliate_id'] ) ) {
				wp_send_json(
					array(
						'ACK'     => 'Error',
						'message' => _x( 'Required parameter missing.', 'error message when fetching payout KPI details', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$affiliate_id = intval( $params['affiliate_id'] );

			$all_time_data                  = new AFWC_Admin_Affiliates( $affiliate_id );
			$all_time_commissions_customers = is_callable( array( $all_time_data, 'get_commissions_customers' ) ) ? $all_time_data->get_commissions_customers() : array();
			$all_time_paid_commissions      = floatval( ( ! empty( $all_time_commissions_customers['paid_commissions'] ) ) ? $all_time_commissions_customers['paid_commissions'] : 0 );
			$all_time_unpaid_commissions    = floatval( ( ! empty( $all_time_commissions_customers['unpaid_commissions'] ) ) ? $all_time_commissions_customers['unpaid_commissions'] : 0 );

			wp_send_json(
				array(
					'ACK'    => 'Success',
					'result' => array(
						'allTime' => array(
							'unpaid_commissions' => afwc_format_price( $all_time_unpaid_commissions ),
							'paid_commissions'   => afwc_format_price( $all_time_paid_commissions ),
							'earned_commissions' => afwc_format_price( floatval( $all_time_paid_commissions + $all_time_unpaid_commissions ) ),
						),
					),
				)
			);
		}

		/**
		 * Handler for AJAX request of getting the payout invoice template.
		 *
		 * @param array $params Params from the AJAX request.
		 *
		 * @return void.
		 */
		public function payout_invoice( $params = array() ) {
			check_admin_referer( 'afwc-admin-payout-invoice', 'security' );

			if ( ! is_callable( array( 'AFWC_Payout_Invoice', 'is_enabled' ) )
				|| ! AFWC_Payout_Invoice::is_enabled()
				|| ! is_callable( array( 'AFWC_Payout_Invoice', 'get_instance' ) )
			) {
				wp_die();
			}

			$payout_invoice = AFWC_Payout_Invoice::get_instance();

			if ( ! is_callable( array( $payout_invoice, 'render_payout_invoice' ) ) ) {
				wp_die();
			}

			$payout_invoice->render_payout_invoice(
				array(
					'payout_id'      => ( ! empty( $params['payout_id'] ) ) ? intval( wc_clean( $params['payout_id'] ) ) : 0,
					'affiliate_id'   => ( ! empty( $params['affiliate_id'] ) ) ? intval( wc_clean( $params['affiliate_id'] ) ) : 0,
					'date_time'      => ( ! empty( $params['date_time'] ) ) ? wc_clean( $params['date_time'] ) : '',
					'from_period'    => ( ! empty( $params['from_period'] ) ) ? wc_clean( $params['from_period'] ) : '',
					'to_period'      => ( ! empty( $params['to_period'] ) ) ? wc_clean( $params['to_period'] ) : '',
					'referral_count' => ( ! empty( $params['referral_count'] ) ) ? intval( wc_clean( $params['referral_count'] ) ) : 0,
					'amount'         => ( ! empty( $params['amount'] ) ) ? floatval( wc_clean( $params['amount'] ) ) : 0,
					'currency'       => ( ! empty( $params['currency'] ) ) ? wc_clean( $params['currency'] ) : '',
					'method'         => ( ! empty( $params['method'] ) ) ? wc_clean( $params['method'] ) : '',
					'notes'          => ( ! empty( $params['notes'] ) ) ? wc_clean( $params['notes'] ) : '',
				)
			);

			wp_die();
		}

		/**
		 * Method to dismiss the commission plan updated notice.
		 *
		 * @return void
		 */
		public function dismiss_plan_update_notice() {

			check_admin_referer( 'afwc-admin-dismiss-plan-update-notice', 'security' );

			update_option( 'afwc-commission-rule-update_affiliate_wc', 'no', 'no' );

			wp_send_json(
				array(
					'ACK' => true,
				)
			);
		}
	}
}

return AFWC_Admin_Dashboard::get_instance();
