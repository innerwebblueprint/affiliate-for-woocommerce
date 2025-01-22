<?php
/**
 * Main class for Affiliates My Account
 *
 * @package   affiliate-for-woocommerce/includes/frontend/
 * @since     1.0.0
 * @version   1.14.6
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_My_Account' ) ) {

	/**
	 * Main class for Affiliates My Account
	 */
	class AFWC_My_Account {

		/**
		 * Variable to hold instance of AFWC_My_Account
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Endpoint
		 *
		 * @var $endpoint
		 */
		public $endpoint;

		/**
		 * Affiliate tab Endpoint.
		 *
		 * @var $afwc_tab_endpoint
		 */
		public $afwc_tab_endpoint;

		/**
		 * Affiliate section Endpoint.
		 *
		 * @var $afwc_section_endpoint
		 */
		public $afwc_section_endpoint = 'section';

		/**
		 * Get single instance of AFWC_My_Account
		 *
		 * @return AFWC_My_Account Singleton object of AFWC_My_Account
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

			$this->endpoint          = get_option( 'woocommerce_myaccount_afwc_dashboard_endpoint', 'afwc-dashboard' );
			$this->afwc_tab_endpoint = apply_filters( 'afwc_dashboard_tab_endpoint', get_option( 'afwc_dashboard_tab_endpoint', 'afwc-tab' ) );

			add_action( 'init', array( $this, 'endpoint' ) );

			add_action( 'wp_loaded', array( $this, 'afw_myaccount' ) );

			add_action( 'wc_ajax_afwc_reload_dashboard', array( $this, 'ajax_reload_dashboard' ) );
			add_action( 'wc_ajax_afwc_load_more_products', array( $this, 'ajax_load_more_products' ) );
			add_action( 'wc_ajax_afwc_load_more_referrals', array( $this, 'ajax_load_more_referrals' ) );
			add_action( 'wc_ajax_afwc_load_more_payouts', array( $this, 'ajax_load_more_payouts' ) );
			add_action( 'wc_ajax_afwc_payout_invoice', array( $this, 'ajax_payout_invoice' ) );
			add_action( 'wc_ajax_afwc_save_account_details', array( $this, 'afwc_save_account_details' ) );
			add_action( 'wc_ajax_afwc_save_ref_url_identifier', array( $this, 'afwc_save_ref_url_identifier' ) );

			// To provide admin setting different endpoint for affiliate.
			add_action( 'init', array( $this, 'endpoint_hooks' ) );

			// Register the shortcode for affiliate dashboard.
			add_shortcode( 'afwc_dashboard', array( $this, 'afwc_dashboard_shortcode_content' ) );

			add_filter( 'afwc_get_merge_tag_value_afwc_affiliate_coupon', array( $this, 'get_afwc_affiliate_coupon_merge_tag_value' ), 10, 2 );

			// Stripe connect's connection handle.
			add_action( 'template_redirect', array( $this, 'afwc_handle_stripe_connect' ) );
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
		 * Function to add affiliates endpoint to My Account.
		 *
		 * @see https://developer.woocommerce.com/2016/04/21/tabbed-my-account-pages-in-2-6/
		 */
		public function endpoint() {
			add_rewrite_endpoint( $this->endpoint, EP_ROOT | EP_PAGES );
		}

		/**
		 * Function to add endpoint in My Account if user is an affiliate
		 */
		public function afw_myaccount() {
			if ( ! is_user_logged_in() ) {
				return;
			}

			$user = wp_get_current_user();
			if ( ! is_object( $user ) || empty( $user->ID ) ) {
				return;
			}

			$is_affiliate = afwc_is_user_affiliate( $user );
			if ( in_array( $is_affiliate, array( 'yes', 'not_registered', 'pending', 'no' ), true ) ) {
				// Register endpoints to WordPress query vars.
				add_filter( 'query_vars', array( $this, 'register_wp_query_vars' ) );
				// Register endpoint in WooCommerce query vars.
				add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_vars' ) );
				add_filter( 'woocommerce_account_menu_items', array( $this, 'wc_my_account_menu_item' ) );
				add_action( 'woocommerce_account_' . $this->endpoint . '_endpoint', array( $this, 'endpoint_content' ) );
				// Change the My Account page title.
				add_filter( 'the_title', array( $this, 'afw_endpoint_title' ) );
				add_filter( 'woocommerce_endpoint_' . $this->endpoint . '_title', array( $this, 'get_endpoint_title' ) );
			}

			if ( 'yes' === $is_affiliate ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ) );
				add_action( 'wp_footer', array( $this, 'footer_styles_scripts' ) );
			}
		}

		/**
		 * Add new query var to WooCommerce.
		 *
		 * @param array $vars The query vars.
		 * @return array
		 */
		public function add_query_vars( $vars = array() ) {
			$vars[ $this->endpoint ] = $this->endpoint;
			return $vars;
		}

		/**
		 * Register the required endpoints in WordPress query vars.
		 *
		 * @param array $vars The query vars.
		 * .
		 * @return array The modified query vars.
		 */
		public function register_wp_query_vars( $vars = array() ) {
			$vars[] = $this->afwc_tab_endpoint;
			$vars[] = $this->afwc_section_endpoint;
			return $vars;
		}

		/**
		 * Set endpoint title.
		 *
		 * @param string $title The endpoint page title.
		 *
		 * @return string
		 */
		public function afw_endpoint_title( $title = '' ) {
			global $wp_query;

			if ( ! empty( $wp_query->query_vars ) && ! empty( $wp_query->query_vars[ $this->afwc_tab_endpoint ] ) && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
				$title = $this->get_endpoint_title( $title );
				remove_filter( 'the_title', array( $this, 'afw_endpoint_title' ) );
			}

			return $title;
		}

		/**
		 * Get the endpoint title.
		 *
		 * @param string $title    The endpoint title.
		 * @param string $endpoint The endpoint name.
		 *
		 * @return string.
		 */
		public function get_endpoint_title( $title = '', $endpoint = '' ) {
			global $wp_query;

			$endpoint = ! empty( $endpoint ) ? $endpoint : ( ! empty( $wp_query->query_vars ) && ! empty( $wp_query->query_vars[ $this->afwc_tab_endpoint ] ) ? $wp_query->query_vars[ $this->afwc_tab_endpoint ] : '' );

			$affiliate_status = afwc_is_user_affiliate( wp_get_current_user() );

			if ( 'not_registered' === $affiliate_status ) {
				$title = _x( 'Register as an affiliate', 'Affiliate my account page title when a user has not submitted a request to become an affiliate', 'affiliate-for-woocommerce' );
			} elseif ( in_array( $affiliate_status, array( 'pending', 'no' ), true ) ) {
				$title = _x( 'Affiliate request status', 'Affiliate my account page title when a user\'s request to become an affiliate is either pending or rejected', 'affiliate-for-woocommerce' );
			} elseif ( 'yes' === $affiliate_status ) {
				$title = _x( 'Affiliate Dashboard', 'Affiliate my account page title when user is an affiliate', 'affiliate-for-woocommerce' );
			}

			switch ( $endpoint ) {
				case 'resources':
					return _x( 'Affiliate Resources', 'Affiliate my account page title for resources', 'affiliate-for-woocommerce' );
				case 'campaigns':
					return _x( 'Affiliate Campaigns', 'Affiliate my account page title for campaigns', 'affiliate-for-woocommerce' );
				case 'multi-tier':
					return _x( 'Affiliate Network', 'Affiliate my account page title for multi-tier', 'affiliate-for-woocommerce' );
				default:
					return $title;
			}

		}

		/**
		 * Function to add menu items in My Account.
		 *
		 * @param array $menu_items menu items.
		 * @return array $menu_items menu items.
		 */
		public function wc_my_account_menu_item( $menu_items = array() ) {
			// Return if the affiliate endpoint does not exist.
			if ( empty( $this->endpoint ) ) {
				return $menu_items;
			}

			$user = wp_get_current_user();
			if ( is_object( $user ) && $user instanceof WP_User && ! empty( $user->ID ) ) {
				$is_affiliate              = afwc_is_user_affiliate( $user );
				$insert_at_index           = array_search( 'edit-account', array_keys( $menu_items ), true );
				$afwc_is_registration_open = get_option( 'afwc_show_registration_form_in_account', 'yes' );

				// WooCommerce uses the same on the admin side to get list of WooCommerce Endpoints under Appearance > Menus.
				// So return main endpoint name irrespective of admin's affiliate status.
				if ( is_admin() ) {
					$menu_item = array( $this->endpoint => __( 'Affiliate', 'affiliate-for-woocommerce' ) );
				} else {
					if ( 'yes' === $is_affiliate ) {
						$menu_item = array( $this->endpoint => _x( 'Affiliate', 'Affiliate my account page menu title when user is an affiliate', 'affiliate-for-woocommerce' ) );
					}
					if ( in_array( $is_affiliate, array( 'pending', 'no' ), true ) ) {
						$menu_item = array( $this->endpoint => _x( 'Affiliate request status', 'Affiliate my account page menu title when a user\'s request to become an affiliate is either pending or rejected', 'affiliate-for-woocommerce' ) );
					}
					if ( 'not_registered' === $is_affiliate && 'yes' === $afwc_is_registration_open ) {
						$menu_item = array( $this->endpoint => _x( 'Register as an affiliate', 'Affiliate my account page menu title when a user has not submitted a request to become an affiliate', 'affiliate-for-woocommerce' ) );
					}
				}

				if ( ! empty( $menu_item ) ) {
					$new_menu_items = array_merge(
						array_slice( $menu_items, 0, $insert_at_index ),
						$menu_item,
						array_slice( $menu_items, $insert_at_index, null )
					);
					return $new_menu_items;
				}
			}
			return $menu_items;
		}

		/**
		 * Function to check if current page has affiliates' endpoint.
		 */
		public function is_afwc_endpoint() {
			global $wp;

			if ( ! empty( $wp->query_vars ) && array_key_exists( $this->endpoint, $wp->query_vars ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Function to add styles.
		 */
		public function enqueue_styles_scripts() {
			if ( ! $this->is_afwc_dashboard() ) {
				return;
			}

			$plugin_data = Affiliate_For_WooCommerce::get_plugin_data();
			$suffix      = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			if ( ! wp_script_is( 'wp-i18n' ) ) {
				wp_enqueue_script( 'wp-i18n' );
			}

			if ( ! wp_style_is( 'afwc-admin-dashboard-font', 'registered' ) ) {
				wp_register_style( 'afwc-admin-dashboard-font', AFWC_PLUGIN_URL . '/assets/fontawesome/css/all' . $suffix . '.css', array(), $plugin_data['Version'] );
			}

			wp_enqueue_style( 'afwc-admin-dashboard-font' );
			wp_enqueue_style( 'afwc-my-account', AFWC_PLUGIN_URL . '/assets/css/afwc-my-account.css', array(), $plugin_data['Version'] );
		}

		/**
		 * Function to add scripts in footer.
		 */
		public function footer_styles_scripts() {
			if ( ! $this->is_afwc_dashboard() ) {
				return;
			}

			global $wp;

			if ( ! wp_script_is( 'jquery' ) ) {
				wp_enqueue_script( 'jquery' );
			}
			if ( ! class_exists( 'WC_AJAX' ) ) {
				include_once WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-ajax.php';
			}

			$user = wp_get_current_user();

			if ( ! is_object( $user ) || empty( $user->ID ) ) {
				return;
			}

			$affiliate_id = afwc_get_affiliate_id_based_on_user_id( $user->ID );

			if ( ( ! empty( $wp->query_vars ) && ! empty( $wp->query_vars[ $this->afwc_tab_endpoint ] ) ) && ( 'campaigns' === $wp->query_vars[ $this->afwc_tab_endpoint ] || 'multi-tier' === $wp->query_vars[ $this->afwc_tab_endpoint ] ) ) {
				$plugin_data = Affiliate_For_WooCommerce::get_plugin_data();
				// Dashboard scripts.
				wp_register_script( 'mithril', AFWC_PLUGIN_URL . '/assets/js/mithril/mithril.min.js', array(), $plugin_data['Version'], true );
				wp_register_script( 'afwc-frontend-styles', AFWC_PLUGIN_URL . '/assets/js/styles.js', array( 'mithril' ), $plugin_data['Version'], true );
				wp_register_script( 'afwc-frontend-dashboard', AFWC_PLUGIN_URL . '/assets/js/frontend.js', array( 'afwc-frontend-styles', 'wp-i18n', 'afwc-click-to-copy' ), $plugin_data['Version'], true );
				if ( function_exists( 'wp_set_script_translations' ) ) {
					wp_set_script_translations( 'afwc-frontend-dashboard', 'affiliate-for-woocommerce', AFWC_PLUGIN_DIR_PATH . 'languages' );
				}
				if ( ! wp_script_is( 'afwc-frontend-dashboard' ) ) {
					wp_enqueue_script( 'afwc-frontend-dashboard' );
				}

				$affiliate_id  = afwc_get_affiliate_id_based_on_user_id( $user->ID );
				$affiliate_obj = new AFWC_Affiliate( $affiliate_id );

				wp_localize_script(
					'afwc-frontend-dashboard',
					'afwcDashboardParams',
					array(
						'security'                => array(
							'campaign'  => array(
								'fetchData' => wp_create_nonce( 'afwc-fetch-campaign' ),
							),
							'dashboard' => array(
								'multiTierData' => wp_create_nonce( 'afwc-multi-tier-data' ),
							),
						),
						'currencySymbol'          => AFWC_CURRENCY,
						'pname'                   => afwc_get_pname(),
						'affiliate_id'            => $affiliate_id,
						'affiliateIdentifier'     => ( $affiliate_obj instanceof AFWC_Affiliate && is_callable( array( $affiliate_obj, 'get_identifier' ) ) ) ? $affiliate_obj->get_identifier() : '',
						'ajaxurl'                 => admin_url( 'admin-ajax.php' ),
						'campaign_status'         => 'Active',
						'isPrettyReferralEnabled' => get_option( 'afwc_use_pretty_referral_links', 'no' ),
						'wcSpinLoader'            => WC()->plugin_url() . '/assets/images/wpspin-2x.gif',
					)
				);
				$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

				wp_register_style( 'afwc_frontend', AFWC_PLUGIN_URL . '/assets/css/frontend.css', array(), $plugin_data['Version'] );
				if ( ! wp_style_is( 'afwc_frontend' ) ) {
					wp_enqueue_style( 'afwc_frontend' );
				}

				wp_register_style( 'afwc-common-tailwind', AFWC_PLUGIN_URL . '/assets/css/common.css', array(), $plugin_data['Version'] );

				if ( ! wp_style_is( 'afwc-common-tailwind' ) ) {
					wp_enqueue_style( 'afwc-common-tailwind' );
				}
			}
		}

		/**
		 * Function to retrieve more products.
		 */
		public function ajax_reload_dashboard() {
			check_ajax_referer( 'afwc-reload-dashboard', 'security' );

			$user_id = ( ! empty( $_POST['user_id'] ) ) ? absint( $_POST['user_id'] ) : 0;

			$user = get_user_by( 'id', $user_id );

			$this->dashboard_content( $user );

			die();
		}

		/**
		 * Function to retrieve more products.
		 */
		public function ajax_load_more_products() {
			check_ajax_referer( 'afwc-load-more-products', 'security' );

			$args = apply_filters(
				'afwc_ajax_load_more_products',
				array(
					'from'         => ( ! empty( $_POST['from'] ) ) ? $this->gmt_from_date( wc_clean( wp_unslash( $_POST['from'] ) ) ) : '', // phpcs:ignore
					'to'           => ( ! empty( $_POST['to'] ) ) ? $this->gmt_from_date( wc_clean( wp_unslash( $_POST['to'] ) ) ) : '', // phpcs:ignore
					'search'       => ( ! empty( $_POST['search'] ) ) ? wc_clean( wp_unslash( $_POST['search'] ) ) : '', // phpcs:ignore
					// To use same for the admin side.
					'start_limit'  => ( ! empty( $_POST['offset'] ) ) ? wc_clean( wp_unslash( $_POST['offset'] ) ) : 0, // phpcs:ignore
					'affiliate_id' => ( ! empty( $_POST['affiliate'] ) ) ? wc_clean( wp_unslash( $_POST['affiliate'] ) ) : 0, // phpcs:ignore
				)
			);

			$product_headers = $this->get_products_report_headers();

			if ( empty( $product_headers ) || ! is_array( $product_headers ) ) {
				wp_die();
			}

			$products = $this->get_products_data( $args );

			if ( empty( $products ) || empty( $products['rows'] ) || ! is_array( $products['rows'] ) ) {
				wp_die();
			}

			do_action( 'afwc_before_ajax_load_more_products', $products, $args, $this );

			foreach ( $products['rows'] as $product ) {
				echo '<tr>';
				foreach ( $product_headers as $key => $product_header ) { ?>
					<td class="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $product_header ); ?>"><?php echo ! empty( $product[ $key ] ) ? wp_kses_post( $product[ $key ] ) : ''; ?></td>
					<?php
				}
				echo '</tr>';
			}

			do_action( 'afwc_after_ajax_load_more_products', $products, $args, $this );

			wp_die();
		}

		/**
		 * Function to retrieve more referrals.
		 */
		public function ajax_load_more_referrals() {
			check_ajax_referer( 'afwc-load-more-referrals', 'security' );

			$date_format = get_option( 'date_format' );

			$args = apply_filters(
				'afwc_ajax_load_more_referrals',
				array(
					'from'         => ( ! empty( $_POST['from'] ) ) ? $this->gmt_from_date( wc_clean( wp_unslash( $_POST['from'] ) ) ) : '', // phpcs:ignore
					'to'           => ( ! empty( $_POST['to'] ) ) ? $this->gmt_from_date( wc_clean( wp_unslash( $_POST['to'] ) ) ) : '', // phpcs:ignore
					'search'       => ( ! empty( $_POST['search'] ) ) ? wc_clean( wp_unslash( $_POST['search'] ) ) : '', // phpcs:ignore
					'offset'       => ( ! empty( $_POST['offset'] ) ) ? wc_clean( wp_unslash( $_POST['offset'] ) ) : 0, // phpcs:ignore
					'affiliate_id' => ( ! empty( $_POST['affiliate'] ) ) ? wc_clean( wp_unslash( $_POST['affiliate'] ) ) : 0, // phpcs:ignore
					'current_url'  => ( ! empty( $_POST['current_url'] ) ) ? wc_clean( wp_unslash( $_POST['current_url'] ) ) : afwc_get_current_url(), // phpcs:ignore
				)
			);

			$referral_headers = $this->get_referrals_report_headers();

			if ( empty( $referral_headers ) || ! is_array( $referral_headers ) ) {
				wp_die();
			}

			$referrals     = $this->get_referrals_data( $args );
			$campaign_link = $this->get_tab_link( 'campaigns', ! empty( $args['current_url'] ) ? $args['current_url'] : '' ) . '#!/'; // To support Mithril routing of individual campaign URL.

			if ( empty( $referrals ) || empty( $referrals['rows'] ) || ! is_array( $referrals['rows'] ) ) {
				wp_die();
			}

			do_action( 'afwc_before_ajax_load_more_referrals', $referrals, $args, $this );

			foreach ( $referrals['rows'] as $referral ) {
				echo '<tr>';
				foreach ( $referral_headers as $key => $referral_header ) {
					if ( 'customer_name' === $key ) {
						$customer_name = ! empty( $referral[ $key ] ) ? ( ( mb_strlen( $referral[ $key ] ) > 20 ) ? mb_substr( $referral[ $key ], 0, 19 ) . '...' : $referral[ $key ] ) : '';
						?>
							<td class="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $referral_header ); ?>" title="<?php echo ( ! empty( $referral[ $key ] ) ) ? esc_html( $referral[ $key ] ) : ''; ?>"><?php echo esc_html( $customer_name ); ?></td>
						<?php
					} elseif ( 'status' === $key ) {
						$referral_status = ( ! empty( $referral[ $key ] ) ) ? $referral[ $key ] : '';
						?>
						<td class="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $referral_header ); ?>"><div class="<?php echo esc_attr( 'text_' . ( ! empty( $referral_status ) ? afwc_get_commission_status_colors( $referral_status ) : '' ) ); ?>"><?php echo esc_html( ( ! empty( $referral_status ) ) ? afwc_get_commission_statuses( $referral_status ) : '' ); ?></div></td>
						<?php
					} elseif ( 'campaign' === $key ) {
						$campaign_id = ( ! empty( $referral[ $key ] ) ) ? $referral[ $key ] : '';
						?>
						<td class="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $referral_header ); ?>">
							<?php
							if ( ! empty( $referral['campaign_title'] ) ) {
								?>
									<a href="<?php echo esc_attr( ! empty( $campaign_link ) ? ( $campaign_link . $campaign_id ) : '#' ); ?>" title="<?php echo esc_html( $referral['campaign_title'] ); ?>" target="_blank">
										<?php echo esc_html( '#' . $campaign_id ); ?>
									</a>
								<?php } elseif ( ! empty( $campaign_id ) ) { ?>
									<span title="<?php echo ! empty( $referral['is_campaign_deleted'] ) ? esc_attr_x( 'Deleted', 'Deleted campaign ID in referral table', 'affiliate-for-woocommerce' ) : ''; ?>"><?php echo esc_html( '#' . $campaign_id ); ?> </span>
								<?php } else { ?>
									- 
								<?php } ?>
						</td>
					<?php } else { ?>
					<td class="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $referral_header ); ?>"><?php echo ! empty( $referral[ $key ] ) ? wp_kses_post( $referral[ $key ] ) : ''; ?></td>
						<?php
					}
				}
				echo '</tr>';
			}

			do_action( 'afwc_after_ajax_load_more_referrals', $referrals, $args, $this );

			wp_die();
		}

		/**
		 * Function to retrieve more payouts.
		 */
		public function ajax_load_more_payouts() {
			check_ajax_referer( 'afwc-load-more-payouts', 'security' );

			$date_format = get_option( 'date_format' );

			$args = apply_filters(
				'afwc_ajax_load_more_payouts',
				array(
					'from'         => ( ! empty( $_POST['from'] ) ) ? $this->gmt_from_date( wc_clean( wp_unslash( $_POST['from'] ) ) ) : '', // phpcs:ignore
					'to'           => ( ! empty( $_POST['to'] ) ) ? $this->gmt_from_date( wc_clean( wp_unslash( $_POST['to'] ) ) ) : '', // phpcs:ignore
					'search'       => ( ! empty( $_POST['search'] ) ) ? wc_clean( wp_unslash( $_POST['search'] ) ) : '', // phpcs:ignore
					'start_limit'  => ( ! empty( $_POST['offset'] ) ) ? wc_clean( wp_unslash( $_POST['offset'] ) ) : 0, // phpcs:ignore
					'affiliate_id' => ( ! empty( $_POST['affiliate'] ) ) ? wc_clean( wp_unslash( $_POST['affiliate'] ) ) : 0, // phpcs:ignore
				)
			);

			$payout_headers = $this->get_payouts_report_headers();

			if ( empty( $payout_headers ) || ! is_array( $payout_headers ) ) {
				wp_die();
			}

			$payouts = $this->get_payouts_data( $args );

			if ( empty( $payouts ) || empty( $payouts['payouts'] ) || ! is_array( $payouts['payouts'] ) ) {
				wp_die();
			}

			do_action( 'afwc_before_ajax_load_more_payouts', $payouts, $args, $this );

			foreach ( $payouts['payouts'] as $payout ) {
				echo '<tr>';
				foreach ( $payout_headers as $key => $payout_header ) {
					if ( 'invoice' === $key ) {
						?>
							<td
								class="<?php echo esc_attr( $key ); ?>"
								data-title="<?php echo esc_attr( $payout_header ); ?>"
								data-payout_id="<?php echo ! empty( $payout['payout_id'] ) ? esc_attr( $payout['payout_id'] ) : 0; ?>"
								data-affiliate_id=<?php echo ! empty( $args['affiliate_id'] ) ? esc_attr( $args['affiliate_id'] ) : 0; ?>"
								data-datetime="<?php echo ! empty( $payout['datetime'] ) ? esc_attr( $payout['datetime'] ) : ''; ?>"
								data-from_period="<?php echo ! empty( $payout['from_date'] ) ? esc_attr( $payout['from_date'] ) : ''; ?>"
								data-to_period="<?php echo ! empty( $payout['to_date'] ) ? esc_attr( $payout['to_date'] ) : ''; ?>"
								data-referral_count="<?php echo ! empty( $payout['referral_count'] ) ? esc_attr( $payout['referral_count'] ) : 0; ?>"
								data-amount="<?php echo ! empty( $payout['amount'] ) ? esc_attr( $payout['amount'] ) : 0; ?>"
								data-currency="<?php echo ! empty( $payout['currency'] ) ? esc_attr( $payout['currency'] ) : ''; ?>"
								data-method="<?php echo ! empty( $payout['method'] ) ? esc_attr( $payout['method'] ) : ''; ?>"
								data-notes="<?php echo ! empty( $payout['payout_notes'] ) ? esc_attr( $payout['payout_notes'] ) : ''; ?>"
							>
								<a class="print-invoice" title="<?php echo esc_attr_x( 'Print', 'title for printing the invoice', 'affiliate-for-woocommerce' ); ?>">
									<?php
									$template = is_callable( array( 'AFWC_Templates', 'get_instance' ) ) ? AFWC_Templates::get_instance() : null;
									if ( is_callable( array( $template, 'get_version' ) ) && version_compare( $template->get_version( 'my-account/affiliate-reports.php' ), '2.0', '>=' ) ) {
										?>
											<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5">
												<path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"></path>
											</svg>
										<?php
									} else {
										echo esc_html_x( 'Print', 'Label for printing the invoice', 'affiliate-for-woocommerce' );
									}
									?>
								</a>
							</td>
						<?php
					} else {
						?>
						<td class="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $payout_header ); ?>"><?php echo ! empty( $payout[ $key ] ) ? wp_kses_post( $payout[ $key ] ) : ''; ?></td>
						<?php
					}
				}
				echo '</tr>';
			}

			do_action( 'afwc_after_ajax_load_more_payouts', $payouts, $args, $this );

			wp_die();
		}

		/**
		 * Function to display endpoint content
		 */
		public function endpoint_content() {

			$user = wp_get_current_user();
			if ( ! is_object( $user ) || empty( $user->ID ) ) {
				return;
			}

			$is_affiliate = afwc_is_user_affiliate( $user );

			if ( 'not_registered' === $is_affiliate ) {
				do_action(
					'afwc_before_registration_form',
					array(
						'user_id' => $user->ID,
						'source'  => $this,
					)
				);
				echo do_shortcode( '[afwc_registration_form]' );
				do_action(
					'afwc_after_registration_form',
					array(
						'user_id' => $user->ID,
						'source'  => $this,
					)
				);
			} else {
				$this->afwc_dashboard_content( $user );
			}
		}

		/**
		 * Function to display the affiliate dashboard.
		 *
		 * @param WP_User $user The user object.
		 *
		 * @return void
		 */
		public function afwc_dashboard_content( $user = null ) {
			if ( empty( $user ) || ! $user instanceof WP_User ) {
				$user = wp_get_current_user();
			}

			$is_affiliate = $user instanceof WP_User ? afwc_is_user_affiliate( $user ) : '';

			if ( ! empty( $is_affiliate ) && 'yes' === $is_affiliate ) {
				$this->affiliate_card( $user );
				$this->tabs( $user );
				$this->tab_content( $user );
			}

			if ( in_array( $is_affiliate, array( 'pending', 'no' ), true ) ) {
				$afwc_registration = AFWC_Registration_Submissions::get_instance();
				if ( is_callable( array( $afwc_registration, 'get_message' ) ) ) {
					// Show message for pending and rejected affiliates.
					echo '<p>' . wp_kses_post( $afwc_registration->get_message( $is_affiliate ) ) . '</p>';
				}
			}
		}

		/**
		 * Method to display affiliate title card
		 *
		 * @param WP_User $user The user object.
		 *
		 * @return void
		 */
		public function affiliate_card( $user = null ) {
			if ( ! $user instanceof WP_User || empty( $user->ID ) ) {
				return;
			}

			global $affiliate_for_woocommerce;

			$affiliate_id  = intval( $user->ID );
			$affiliate_obj = new AFWC_Affiliate( $affiliate_id );

			$affiliate_identifier  = is_callable( array( $affiliate_obj, 'get_identifier' ) ) ? $affiliate_obj->get_identifier() : '';
			$affiliate_redirection = apply_filters( 'afwc_referral_redirection_url', trailingslashit( home_url() ), $affiliate_id, array( 'source' => $affiliate_for_woocommerce ) );

			$template = 'my-account/affiliate-title-card.php';

			wc_get_template(
				$template,
				array(
					'affiliate_id'                   => $affiliate_id,
					'affiliate_display_name'         => ! empty( $user->display_name ) ? $user->display_name : '',
					'affiliate_signup_date'          => is_callable( array( $affiliate_obj, 'get_signup_date' ) ) ? $affiliate_obj->get_signup_date() : '',
					'affiliate_avatar_url'           => get_avatar_url( $affiliate_id ),
					'affiliate_redirection'          => $affiliate_redirection,
					'affiliate_url_with_redirection' => afwc_get_affiliate_url( $affiliate_redirection, '', $affiliate_identifier ),
				),
				is_callable( array( $affiliate_for_woocommerce, 'get_template_base_dir' ) ) ? $affiliate_for_woocommerce->get_template_base_dir( $template ) : '',
				AFWC_PLUGIN_DIRPATH . '/templates/'
			);

		}

		/**
		 * Function to display tabs headers
		 *
		 * @param WP_User $user The user object.
		 */
		public function tabs( $user = null ) {
			if ( ! $user instanceof WP_User || empty( $user->ID ) ) {
				return;
			}

			global $wp;
			$tabs = array();
			$tabs = array(
				'reports'   => esc_html_x( 'Reports', 'Affiliate my account tab title for report', 'affiliate-for-woocommerce' ),
				'resources' => esc_html_x( 'Profile', 'Affiliate my account tab title for profile', 'affiliate-for-woocommerce' ),
			);

			$afwc_multi_tier = AFWC_Multi_Tier::get_instance();
			// Add network tab only if multi tier is enabled and found any child for the current affiliate.
			if ( ! empty( $afwc_multi_tier->is_enabled ) && is_callable( array( $afwc_multi_tier, 'get_children' ) ) && ! empty( $afwc_multi_tier->get_children( intval( $user->ID ) ) ) ) {
				$tabs['multi-tier'] = esc_html_x( 'Network', 'Affiliate my account tab title for multi-tier', 'affiliate-for-woocommerce' );
			}

			// Add campaigns tab only if we find any active campaigns on the store for the current affiliate.
			if ( afwc_is_campaign_active( true ) ) {
				$tabs['campaigns'] = esc_html_x( 'Campaigns', 'Affiliate my account tab title for campaigns', 'affiliate-for-woocommerce' );
			}

			$tabs       = apply_filters( 'afwc_myaccount_tabs', $tabs );
			$active_tab = ! empty( $wp->query_vars ) && ! empty( $wp->query_vars[ $this->afwc_tab_endpoint ] ) ? $wp->query_vars[ $this->afwc_tab_endpoint ] : 'reports';
			?>

			<nav class="nav-tab-wrapper">
				<?php
				if ( ! empty( $tabs ) ) {
					foreach ( $tabs as $id => $name ) {
						?>
						<a href="<?php echo esc_url( $this->get_tab_link( $id ) ); ?>" class="nav-tab <?php echo ( $id === $active_tab ) ? esc_attr( 'nav-tab-active' ) : ''; ?>"><?php echo esc_attr( $name ); ?></a>
						<?php
					}
				}
				?>
			</nav>
			<?php
		}

		/**
		 * Function to display tabs content on my account.
		 *
		 * @param WP_User $user The user object.
		 *
		 * @return void.
		 */
		public function tab_content( $user = null ) {
			if ( ! $user instanceof WP_User || empty( $user->ID ) ) {
				return;
			}
			global $wp;

			if ( ! empty( $wp->query_vars ) && ! empty( $wp->query_vars[ $this->afwc_tab_endpoint ] ) && 'resources' === $wp->query_vars[ $this->afwc_tab_endpoint ] ) {
				$this->profile_resources_content( $user );
			} elseif ( ! empty( $wp->query_vars ) && ! empty( $wp->query_vars[ $this->afwc_tab_endpoint ] ) && 'campaigns' === $wp->query_vars[ $this->afwc_tab_endpoint ] && afwc_is_campaign_active() ) {
				$this->campaigns_content( $user );
			} elseif ( ! empty( $wp->query_vars ) && ! empty( $wp->query_vars[ $this->afwc_tab_endpoint ] ) && 'multi-tier' === $wp->query_vars[ $this->afwc_tab_endpoint ] ) {
				$afwc_multi_tier = AFWC_Multi_Tier::get_instance();
				// Check if multi tier is enabled and affiliate has some children.
				if ( ! empty( $afwc_multi_tier->is_enabled ) && is_callable( array( $afwc_multi_tier, 'get_children' ) ) && ! empty( $afwc_multi_tier->get_children( intval( $user->ID ) ) ) ) {
					$this->multi_tier_content( $user );
				}
			} else {
				$this->dashboard_content( $user );
			}
		}

		/**
		 * Function to display dashboard content on my account.
		 * Default: Reports tab.
		 *
		 * @param WP_User $user The user object.
		 */
		public function dashboard_content( $user = null ) {
			if ( ! is_object( $user ) || empty( $user->ID ) ) {
				return;
			}

			global $wpdb, $affiliate_for_woocommerce, $wp;

			if ( defined( 'WC_DOING_AJAX' ) && true === WC_DOING_AJAX ) {
				check_ajax_referer( 'afwc-reload-dashboard', 'security' );
			}

			$affiliate_id = afwc_get_affiliate_id_based_on_user_id( $user->ID );

			$from         = ( ! empty( $_REQUEST['from-date'] ) ) ? wc_clean( wp_unslash( $_REQUEST['from-date'] ) ) : ''; // phpcs:ignore
			$to           = ( ! empty( $_REQUEST['to-date'] ) ) ? wc_clean( wp_unslash( $_REQUEST['to-date'] ) ) : ''; // phpcs:ignore
			$section      = ( ! empty( $_POST['section'] ) ) ? wc_clean( wp_unslash( $_POST['section'] ) ) : ''; // phpcs:ignore
			$current_url  = ( ! empty( $_POST['current_url'] ) ) ? wc_clean( wp_unslash( $_POST['current_url'] ) ) : afwc_get_current_url(); // phpcs:ignore

			$plugin_data = is_callable( array( $affiliate_for_woocommerce, 'get_plugin_data' ) ) ? $affiliate_for_woocommerce->get_plugin_data() : array();

			if ( is_callable( array( 'AFWC_Payout_Invoice', 'is_enabled_for_affiliate' ) ) && AFWC_Payout_Invoice::is_enabled_for_affiliate() ) {
				// Register the scripts for payout invoice.
				if ( ! wp_style_is( 'afwc-report-payout-invoice' ) ) {
					wp_enqueue_style( 'afwc-report-payout-invoice', AFWC_PLUGIN_URL . '/assets/css/my-account/afwc-report-payout-invoice.css', array(), $plugin_data['Version'], 'all' );
				}

				if ( ! wp_script_is( 'afwc-print-invoice', 'registered' ) ) {
					global $wp_version;
					wp_register_script(
						'afwc-print-invoice',
						AFWC_PLUGIN_URL . '/assets/js/my-account/afwc-print-invoice.js',
						array(),
						$plugin_data['Version'],
						version_compare( $wp_version, '6.3', '>=' ) ? array( 'strategy' => 'defer' ) : true
					);
				}
				wp_enqueue_script( 'afwc-print-invoice' );
			}

			if ( ! wp_script_is( 'afwc-reports' ) ) {
				wp_register_script( 'afwc-reports', AFWC_PLUGIN_URL . '/assets/js/my-account/affiliate-reports.js', array_filter( array( 'jquery', 'wp-i18n', 'wp-url', 'afwc-date-functions', 'afwc-click-to-copy', wp_script_is( 'afwc-print-invoice', 'registered' ) ? 'afwc-print-invoice' : '' ), 'strlen' ), $plugin_data['Version'], true );
				if ( function_exists( 'wp_set_script_translations' ) ) {
					wp_set_script_translations( 'afwc-reports', 'affiliate-for-woocommerce', AFWC_PLUGIN_DIR_PATH . 'languages' );
				}
			}

			wp_localize_script(
				'afwc-reports',
				'afwcDashboardParams',
				array(
					'products'        => array(
						'ajaxURL' => esc_url_raw( WC_AJAX::get_endpoint( 'afwc_load_more_products' ) ),
						'nonce'   => esc_js( wp_create_nonce( 'afwc-load-more-products' ) ),
					),
					'referrals'       => array(
						'ajaxURL' => esc_url_raw( WC_AJAX::get_endpoint( 'afwc_load_more_referrals' ) ),
						'nonce'   => esc_js( wp_create_nonce( 'afwc-load-more-referrals' ) ),
					),
					'payouts'         => array(
						'ajaxURL' => esc_url_raw( WC_AJAX::get_endpoint( 'afwc_load_more_payouts' ) ),
						'nonce'   => esc_js( wp_create_nonce( 'afwc-load-more-payouts' ) ),
					),
					'loadAllData'     => array(
						'ajaxURL' => esc_url_raw( WC_AJAX::get_endpoint( 'afwc_reload_dashboard' ) ),
						'nonce'   => esc_js( wp_create_nonce( 'afwc-reload-dashboard' ) ),
					),
					'invoiceTemplate' => array(
						'ajaxURL' => esc_url_raw( WC_AJAX::get_endpoint( 'afwc_payout_invoice' ) ),
						'nonce'   => esc_js( wp_create_nonce( 'afwc-payout-invoice' ) ),
					),
					'affiliateId'     => $affiliate_id,
				)
			);

			wp_enqueue_script( 'afwc-reports' );

			$template_key = ! empty( $section ) ? $section : ( ! empty( $wp->query_vars[ $this->afwc_section_endpoint ] ) ? $wp->query_vars[ $this->afwc_section_endpoint ] : 'reports' );

			do_action(
				"afwc_{$template_key}_dashboard",
				array(
					'affiliate_id' => $affiliate_id,
					'current_url'  => $current_url,
					'date_range'   => array(
						'from' => $from,
						'to'   => $to,
					),
				)
			);
		}

		/**
		 * Function to get visitors data
		 *
		 * @param array $args arguments.
		 * @return array visitors data
		 */
		public function get_visitors_data( $args = array() ) {
			global $wpdb;

			$from         = ( ! empty( $args['from'] ) ) ? $args['from'] : '';
			$to           = ( ! empty( $args['to'] ) ) ? $args['to'] : '';
			$affiliate_id = ( ! empty( $args['affiliate_id'] ) ) ? $args['affiliate_id'] : 0;

			if ( ! empty( $from ) && ! empty( $to ) ) {
				$visitors_result = $wpdb->get_var( // phpcs:ignore
												$wpdb->prepare( // phpcs:ignore
													"SELECT IFNULL(COUNT( DISTINCT CONCAT_WS( ':', ip, user_id ) ), 0)
																FROM {$wpdb->prefix}afwc_hits
																WHERE affiliate_id = %d
																	AND (datetime BETWEEN %s AND %s)",
													$affiliate_id,
													$from,
													$to
												)
				);
			} else {
				$visitors_result = $wpdb->get_var( // phpcs:ignore
												$wpdb->prepare( // phpcs:ignore
													"SELECT IFNULL(COUNT( DISTINCT CONCAT_WS( ':', ip, user_id ) ), 0)
																FROM {$wpdb->prefix}afwc_hits
																WHERE affiliate_id = %d",
													$affiliate_id
												)
				);
			}

			return apply_filters( 'afwc_my_account_clicks_result', array( 'visitors' => $visitors_result ), $args );
		}

		/**
		 * Function to get customers data
		 *
		 * @param array $args arguments.
		 * @return array customers data
		 */
		public function get_customers_data( $args = array() ) {
			global $wpdb;

			$from         = ( ! empty( $args['from'] ) ) ? $args['from'] : '';
			$to           = ( ! empty( $args['to'] ) ) ? $args['to'] : '';
			$affiliate_id = ( ! empty( $args['affiliate_id'] ) ) ? $args['affiliate_id'] : 0;

			if ( ! empty( $from ) && ! empty( $to ) ) {
				$customers_result = $wpdb->get_var( // phpcs:ignore
												$wpdb->prepare( // phpcs:ignore
													"SELECT IFNULL(COUNT( DISTINCT IF( user_id > 0, user_id, CONCAT_WS( ':', ip, user_id ) ) ), 0) as customers_count
																FROM {$wpdb->prefix}afwc_referrals
																WHERE affiliate_id = %d
																	AND (datetime BETWEEN %s AND %s)",
													$affiliate_id,
													$from,
													$to
												)
				);
			} else {
				$customers_result = $wpdb->get_var( // phpcs:ignore
												$wpdb->prepare( // phpcs:ignore
													"SELECT IFNULL(COUNT( DISTINCT IF( user_id > 0, user_id, CONCAT_WS( ':', ip, user_id ) ) ), 0) as customers_count
																FROM {$wpdb->prefix}afwc_referrals
																WHERE affiliate_id = %d",
													$affiliate_id
												)
				);
			}

			return apply_filters( 'afwc_my_account_customers_result', array( 'customers' => $customers_result ), $args );
		}

		/**
		 * Function to get kpis data
		 *
		 * @param array $args arguments.
		 * @return array $kpis kpis data
		 */
		public function get_kpis_data( $args = array() ) {

			if ( ! afwc_is_valid_date_range( $args ) ) {
				return array();
			}

			global $wpdb;

			$from         = ( ! empty( $args['from'] ) ) ? $args['from'] : '';
			$to           = ( ! empty( $args['to'] ) ) ? $args['to'] : '';
			$affiliate_id = ( ! empty( $args['affiliate_id'] ) ) ? $args['affiliate_id'] : 0;

			$prefixed_statuses   = afwc_get_prefixed_order_statuses();
			$option_order_status = 'afwc_order_statuses_' . uniqid();
			update_option( $option_order_status, implode( ',', $prefixed_statuses ), 'no' );

			$temp_option_key     = 'afwc_order_status_' . uniqid();
			$paid_order_statuses = afwc_get_paid_order_status();
			update_option( $temp_option_key, implode( ',', $paid_order_statuses ), 'no' );

			if ( ! empty( $from ) && ! empty( $to ) ) {
				// Need to consider all order_statuses to get correct rejected_commission and hence not passing order_statuses.
				if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
					$kpis_result = $wpdb->get_results( // phpcs:ignore
														$wpdb->prepare( // phpcs:ignore
															"SELECT IFNULL(count(DISTINCT wco.id), 0) AS number_of_orders,
																		IFNULL(SUM( afwcr.amount ), 0) AS gross_commissions,
																		IFNULL(SUM(CASE WHEN afwcr.status = %s THEN afwcr.amount END), 0) AS paid_commission,
																		IFNULL(SUM(CASE WHEN afwcr.status = %s AND FIND_IN_SET ( CONVERT( afwcr.order_status USING %s ) COLLATE %s, ( SELECT CONVERT( option_value USING %s ) COLLATE %s
																											FROM {$wpdb->prefix}options
																											WHERE option_name = %s )  ) THEN afwcr.amount END), 0) AS unpaid_commission,
																		IFNULL(SUM(CASE WHEN afwcr.status = %s THEN afwcr.amount END), 0) AS rejected_commission,
																		IFNULL(COUNT(CASE WHEN afwcr.status = %s THEN 1 END), 0) AS paid_count,
																		IFNULL(COUNT(CASE WHEN afwcr.status = %s AND FIND_IN_SET ( CONVERT( afwcr.order_status USING %s ) COLLATE %s, ( SELECT CONVERT( option_value USING %s ) COLLATE %s
																											FROM {$wpdb->prefix}options
																											WHERE option_name = %s )  )  THEN 1 END), 0) AS unpaid_count,
																		IFNULL(COUNT(CASE WHEN afwcr.status = %s THEN 1 END), 0) AS rejected_count
																	FROM {$wpdb->prefix}afwc_referrals AS afwcr
																		JOIN {$wpdb->prefix}wc_orders AS wco
																			ON (afwcr.post_id = wco.id
																				AND wco.type = %s
																				AND afwcr.affiliate_id = %d)
																	WHERE afwcr.status != %s AND (afwcr.datetime BETWEEN %s AND %s)",
															AFWC_REFERRAL_STATUS_PAID,
															AFWC_REFERRAL_STATUS_UNPAID,
															AFWC_SQL_CHARSET,
															AFWC_SQL_COLLATION,
															AFWC_SQL_CHARSET,
															AFWC_SQL_COLLATION,
															$temp_option_key,
															AFWC_REFERRAL_STATUS_REJECTED,
															AFWC_REFERRAL_STATUS_PAID,
															AFWC_REFERRAL_STATUS_UNPAID,
															AFWC_SQL_CHARSET,
															AFWC_SQL_COLLATION,
															AFWC_SQL_CHARSET,
															AFWC_SQL_COLLATION,
															$temp_option_key,
															AFWC_REFERRAL_STATUS_REJECTED,
															'shop_order',
															$affiliate_id,
															AFWC_REFERRAL_STATUS_DRAFT,
															$from,
															$to
														),
						'ARRAY_A'
					);

					$order_total = $wpdb->get_results( // phpcs:ignore
										$wpdb->prepare( // phpcs:ignore
											"SELECT IFNULL(SUM(wco.total_amount), 0) AS order_total
													FROM {$wpdb->prefix}afwc_referrals AS afwcr
													JOIN {$wpdb->prefix}wc_orders AS wco
													ON (afwcr.post_id = wco.id
														AND afwcr.affiliate_id = %d)
													WHERE afwcr.status != %s
													   	AND FIND_IN_SET ( CONVERT( afwcr.order_status USING %s ) COLLATE %s, ( SELECT CONVERT( option_value using %s ) COLLATE %s
																							FROM {$wpdb->prefix}options
																							WHERE option_name = %s ) )
														AND (afwcr.datetime BETWEEN %s AND %s)",
											$affiliate_id,
											AFWC_REFERRAL_STATUS_DRAFT,
											AFWC_SQL_CHARSET,
											AFWC_SQL_COLLATION,
											AFWC_SQL_CHARSET,
											AFWC_SQL_COLLATION,
											$option_order_status,
											$from,
											$to
										),
						'ARRAY_A'
					);

				} else {
					$kpis_result = $wpdb->get_results( // phpcs:ignore
														$wpdb->prepare( // phpcs:ignore
															"SELECT IFNULL(count(DISTINCT pm.post_id), 0) AS number_of_orders,
																		IFNULL(SUM( afwcr.amount ), 0) as gross_commissions,
																		IFNULL(SUM(CASE WHEN afwcr.status = %s THEN afwcr.amount END), 0) AS paid_commission,
																		IFNULL(SUM(CASE WHEN afwcr.status = %s AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																											FROM {$wpdb->prefix}options
																											WHERE option_name = %s )  ) THEN afwcr.amount END), 0) AS unpaid_commission,
																		IFNULL(SUM(CASE WHEN afwcr.status = %s THEN afwcr.amount END), 0) AS rejected_commission,
																		IFNULL(COUNT(CASE WHEN afwcr.status = %s THEN 1 END), 0) AS paid_count,
																		IFNULL(COUNT(CASE WHEN afwcr.status = %s AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																											FROM {$wpdb->prefix}options
																											WHERE option_name = %s )  )  THEN 1 END), 0) AS unpaid_count,
																		IFNULL(COUNT(CASE WHEN afwcr.status = %s THEN 1 END), 0) AS rejected_count
																	FROM {$wpdb->prefix}afwc_referrals AS afwcr
																		JOIN {$wpdb->postmeta} AS pm
																			ON (afwcr.post_id = pm.post_id
																					AND pm.meta_key = %s
																					AND afwcr.affiliate_id = %d)
																	WHERE afwcr.status != %s AND (afwcr.datetime BETWEEN %s AND %s)",
															AFWC_REFERRAL_STATUS_PAID,
															AFWC_REFERRAL_STATUS_UNPAID,
															AFWC_SQL_CHARSET,
															AFWC_SQL_COLLATION,
															AFWC_SQL_CHARSET,
															AFWC_SQL_COLLATION,
															$temp_option_key,
															AFWC_REFERRAL_STATUS_REJECTED,
															AFWC_REFERRAL_STATUS_PAID,
															AFWC_REFERRAL_STATUS_UNPAID,
															AFWC_SQL_CHARSET,
															AFWC_SQL_COLLATION,
															AFWC_SQL_CHARSET,
															AFWC_SQL_COLLATION,
															$temp_option_key,
															AFWC_REFERRAL_STATUS_REJECTED,
															'_order_total',
															$affiliate_id,
															AFWC_REFERRAL_STATUS_DRAFT,
															$from,
															$to
														),
						'ARRAY_A'
					);

					$order_total =  $wpdb->get_results( // phpcs:ignore
										$wpdb->prepare( // phpcs:ignore
											"SELECT IFNULL(SUM(pm.meta_value), 0) AS order_total
													FROM {$wpdb->prefix}afwc_referrals AS afwcr
													JOIN {$wpdb->postmeta} AS pm
													ON (afwcr.post_id = pm.post_id
														AND pm.meta_key = %s
														AND afwcr.affiliate_id = %d)
													WHERE afwcr.status != %s
	                                                    AND FIND_IN_SET ( CONVERT(afwcr.order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																							FROM {$wpdb->prefix}options
																							WHERE option_name = %s ) )
	                                                    AND (afwcr.datetime BETWEEN %s AND %s)",
											'_order_total',
											$affiliate_id,
											AFWC_REFERRAL_STATUS_DRAFT,
											AFWC_SQL_CHARSET,
											AFWC_SQL_COLLATION,
											AFWC_SQL_CHARSET,
											AFWC_SQL_COLLATION,
											$option_order_status,
											$from,
											$to
										),
						'ARRAY_A'
					);

				}
			} elseif ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
					$kpis_result = $wpdb->get_results( // phpcs:ignore
														$wpdb->prepare( // phpcs:ignore
															"SELECT IFNULL(count(DISTINCT wco.id), 0) AS number_of_orders,
																				IFNULL(SUM( afwcr.amount ), 0) as gross_commissions,
																				IFNULL(SUM(CASE WHEN afwcr.status = %s THEN afwcr.amount END), 0) AS paid_commission,
																				IFNULL(SUM(CASE WHEN afwcr.status = %s AND FIND_IN_SET ( CONVERT( order_status USING %s ) COLLATE %s, ( SELECT CONVERT( option_value USING %s ) COLLATE %s
																											FROM {$wpdb->prefix}options
																											WHERE option_name = %s )  ) THEN afwcr.amount END), 0) AS unpaid_commission,
																				IFNULL(SUM(CASE WHEN afwcr.status = %s THEN afwcr.amount END), 0) AS rejected_commission,
																				IFNULL(COUNT(CASE WHEN afwcr.status = %s THEN 1 END), 0) AS paid_count,
																				IFNULL(COUNT(CASE WHEN afwcr.status = %s AND FIND_IN_SET ( CONVERT( order_status USING %s ) COLLATE %s, ( SELECT CONVERT( option_value USING %s ) COLLATE %s
																											FROM {$wpdb->prefix}options
																											WHERE option_name = %s )  ) THEN 1 END), 0) AS unpaid_count,
																				IFNULL(COUNT(CASE WHEN afwcr.status = %s THEN 1 END), 0) AS rejected_count
																		FROM {$wpdb->prefix}afwc_referrals AS afwcr
																			JOIN {$wpdb->prefix}wc_orders AS wco
																				ON (afwcr.post_id = wco.id
																						AND wco.type = %s
																						AND afwcr.affiliate_id = %d)
																						WHERE afwcr.status != %s",
															AFWC_REFERRAL_STATUS_PAID,
															AFWC_REFERRAL_STATUS_UNPAID,
															AFWC_SQL_CHARSET,
															AFWC_SQL_COLLATION,
															AFWC_SQL_CHARSET,
															AFWC_SQL_COLLATION,
															$temp_option_key,
															AFWC_REFERRAL_STATUS_REJECTED,
															AFWC_REFERRAL_STATUS_PAID,
															AFWC_REFERRAL_STATUS_UNPAID,
															AFWC_SQL_CHARSET,
															AFWC_SQL_COLLATION,
															AFWC_SQL_CHARSET,
															AFWC_SQL_COLLATION,
															$temp_option_key,
															AFWC_REFERRAL_STATUS_REJECTED,
															'shop_order',
															$affiliate_id,
															AFWC_REFERRAL_STATUS_DRAFT
														),
						'ARRAY_A'
					);

					$order_total = $wpdb->get_results( // phpcs:ignore
										$wpdb->prepare( // phpcs:ignore
											"SELECT IFNULL(SUM(wco.total_amount), 0) AS order_total
													FROM {$wpdb->prefix}afwc_referrals AS afwcr
													JOIN {$wpdb->prefix}wc_orders AS wco
													ON (afwcr.post_id = wco.id
														AND afwcr.affiliate_id = %d)
													WHERE afwcr.status != %s
													   	AND FIND_IN_SET ( CONVERT( afwcr.order_status using %s ) COLLATE %s, ( SELECT CONVERT( option_value using %s ) COLLATE %s
																							FROM {$wpdb->prefix}options
																							WHERE option_name = %s ) )",
											$affiliate_id,
											AFWC_REFERRAL_STATUS_DRAFT,
											AFWC_SQL_CHARSET,
											AFWC_SQL_COLLATION,
											AFWC_SQL_CHARSET,
											AFWC_SQL_COLLATION,
											$option_order_status
										),
						'ARRAY_A'
					);
			} else {
				$kpis_result = $wpdb->get_results( // phpcs:ignore
													$wpdb->prepare( // phpcs:ignore
														"SELECT IFNULL(count(DISTINCT pm.post_id), 0) AS number_of_orders,
																				IFNULL(SUM( afwcr.amount ), 0) as gross_commissions,
																				IFNULL(SUM(CASE WHEN afwcr.status = %s THEN afwcr.amount END), 0) AS paid_commission,
																				IFNULL(SUM(CASE WHEN afwcr.status = %s AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																											FROM {$wpdb->prefix}options
																											WHERE option_name = %s )  ) THEN afwcr.amount END), 0) AS unpaid_commission,
																				IFNULL(SUM(CASE WHEN afwcr.status = %s THEN afwcr.amount END), 0) AS rejected_commission,
																				IFNULL(COUNT(CASE WHEN afwcr.status = %s THEN 1 END), 0) AS paid_count,
																				IFNULL(COUNT(CASE WHEN afwcr.status = %s AND FIND_IN_SET ( CONVERT(order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																											FROM {$wpdb->prefix}options
																											WHERE option_name = %s )  ) THEN 1 END), 0) AS unpaid_count,
																				IFNULL(COUNT(CASE WHEN afwcr.status = %s THEN 1 END), 0) AS rejected_count
																		FROM {$wpdb->prefix}afwc_referrals AS afwcr
																			JOIN {$wpdb->postmeta} AS pm
																				ON (afwcr.post_id = pm.post_id
																						AND pm.meta_key = %s
																						AND afwcr.affiliate_id = %d)
																						WHERE afwcr.status != %s",
														AFWC_REFERRAL_STATUS_PAID,
														AFWC_REFERRAL_STATUS_UNPAID,
														AFWC_SQL_CHARSET,
														AFWC_SQL_COLLATION,
														AFWC_SQL_CHARSET,
														AFWC_SQL_COLLATION,
														$temp_option_key,
														AFWC_REFERRAL_STATUS_REJECTED,
														AFWC_REFERRAL_STATUS_PAID,
														AFWC_REFERRAL_STATUS_UNPAID,
														AFWC_SQL_CHARSET,
														AFWC_SQL_COLLATION,
														AFWC_SQL_CHARSET,
														AFWC_SQL_COLLATION,
														$temp_option_key,
														AFWC_REFERRAL_STATUS_REJECTED,
														'_order_total',
														$affiliate_id,
														AFWC_REFERRAL_STATUS_DRAFT
													),
					'ARRAY_A'
				);

				$order_total =  $wpdb->get_results( // phpcs:ignore
									$wpdb->prepare( // phpcs:ignore
										"SELECT IFNULL(SUM(pm.meta_value), 0) AS order_total
													FROM {$wpdb->prefix}afwc_referrals AS afwcr
													JOIN {$wpdb->postmeta} AS pm
													ON (afwcr.post_id = pm.post_id
														AND pm.meta_key = %s
														AND afwcr.affiliate_id = %d)
													WHERE afwcr.status != %s
	                                                    AND FIND_IN_SET ( CONVERT(afwcr.order_status USING %s) COLLATE %s, ( SELECT CONVERT(option_value USING %s) COLLATE %s
																							FROM {$wpdb->prefix}options
																							WHERE option_name = %s ) )",
										'_order_total',
										$affiliate_id,
										AFWC_REFERRAL_STATUS_DRAFT,
										AFWC_SQL_CHARSET,
										AFWC_SQL_COLLATION,
										AFWC_SQL_CHARSET,
										AFWC_SQL_COLLATION,
										$option_order_status
									),
					'ARRAY_A'
				);
			}
			delete_option( $option_order_status );
			delete_option( $temp_option_key );

			$kpis_result[0]['order_total'] = ( ! empty( $order_total[0]['order_total'] ) ) ? $order_total[0]['order_total'] : 0;

			return apply_filters(
				'afwc_my_account_kpis_result',
				array(
					'sales'               => ( ! empty( $kpis_result[0]['order_total'] ) ) ? $kpis_result[0]['order_total'] : 0,
					'number_of_orders'    => ( ! empty( $kpis_result[0]['number_of_orders'] ) ) ? $kpis_result[0]['number_of_orders'] : 0,
					'paid_commission'     => ( ! empty( $kpis_result[0]['paid_commission'] ) ) ? $kpis_result[0]['paid_commission'] : 0,
					'unpaid_commission'   => ( ! empty( $kpis_result[0]['unpaid_commission'] ) ) ? $kpis_result[0]['unpaid_commission'] : 0,
					'rejected_commission' => ( ! empty( $kpis_result[0]['rejected_commission'] ) ) ? $kpis_result[0]['rejected_commission'] : 0,
					'paid_count'          => ( ! empty( $kpis_result[0]['paid_count'] ) ) ? $kpis_result[0]['paid_count'] : 0,
					'unpaid_count'        => ( ! empty( $kpis_result[0]['unpaid_count'] ) ) ? $kpis_result[0]['unpaid_count'] : 0,
					'rejected_count'      => ( ! empty( $kpis_result[0]['rejected_count'] ) ) ? $kpis_result[0]['rejected_count'] : 0,
					'gross_commission'    => ( ! empty( $kpis_result[0]['gross_commissions'] ) ) ? $kpis_result[0]['gross_commissions'] : 0,
				),
				array(
					'source'      => $this,
					'kpis_result' => $kpis_result,
				)
			);
		}

		/**
		 * Get payout KPIs data
		 *
		 * @param array $args arguments.
		 * @return array $kpis.
		 */
		public function get_payout_kpis( $args = array() ) {
			if ( ! afwc_is_valid_date_range( $args ) ) {
				return array();
			}

			global $wpdb;

			$from         = ( ! empty( $args['from'] ) ) ? $args['from'] : '';
			$to           = ( ! empty( $args['to'] ) ) ? $args['to'] : '';
			$affiliate_id = ( ! empty( $args['affiliate_id'] ) ) ? intval( $args['affiliate_id'] ) : 0;

			$paid_order_statuses = afwc_get_paid_order_status(); // Assume this returns an array of statuses.

			if ( ! empty( $from ) && ! empty( $to ) ) {

				$kpis = $wpdb->get_results( // phpcs:ignore
					$wpdb->prepare(
						"SELECT IFNULL(SUM( CASE WHEN status = 'paid' THEN amount END ), 0) as paid_commission,
								IFNULL(SUM( CASE WHEN status = 'unpaid' AND order_status IN ( " . implode( ',', array_fill( 0, count( $paid_order_statuses ), '%s' ) ) . " ) THEN amount END ), 0) as unpaid_commission
						FROM {$wpdb->prefix}afwc_referrals
						WHERE
							affiliate_id = %d
							AND datetime BETWEEN %s AND %s
							AND status != %s",
						array_merge(
							$paid_order_statuses,
							array(
								$affiliate_id,
								$from,
								$to,
								AFWC_REFERRAL_STATUS_DRAFT,
							)
						)
					),
					'ARRAY_A'
				);
			} else {
				$kpis = $wpdb->get_results( // phpcs:ignore
					$wpdb->prepare(
						"SELECT IFNULL(SUM( CASE WHEN status = 'paid' THEN amount END ), 0) as paid_commission,
								IFNULL(SUM( CASE WHEN status = 'unpaid' AND order_status IN ( " . implode( ',', array_fill( 0, count( $paid_order_statuses ), '%s' ) ) . " ) THEN amount END ), 0) as unpaid_commission
						FROM {$wpdb->prefix}afwc_referrals
						WHERE
							affiliate_id = %d
							AND status != %s",
						array_merge(
							$paid_order_statuses,
							array(
								$affiliate_id,
								AFWC_REFERRAL_STATUS_DRAFT,
							)
						)
					),
					'ARRAY_A'
				);
			}

			return is_array( $kpis ) ? current( $kpis ) : array();
		}

		/**
		 * Function to get refunds data
		 *
		 * @param array $args arguments.
		 * @return array $refunds refunds.
		 */
		public function get_refunds_data( $args = array() ) {
			global $wpdb;

			$from         = ( ! empty( $args['from'] ) ) ? $args['from'] : '';
			$to           = ( ! empty( $args['to'] ) ) ? $args['to'] : '';
			$affiliate_id = ( ! empty( $args['affiliate_id'] ) ) ? $args['affiliate_id'] : 0;

			if ( ! empty( $from ) && ! empty( $to ) ) {
				if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
					$refunds_result = $wpdb->get_results( // phpcs:ignore
														$wpdb->prepare( // phpcs:ignore
															"SELECT IFNULL(SUM(ABS(wco.total_amount)), 0) AS refund_amount,
																				IFNULL(COUNT(DISTINCT wco.parent_order_id), 0) AS refund_order_count
																		FROM {$wpdb->prefix}wc_orders AS wco
																			JOIN {$wpdb->prefix}afwc_referrals AS afwcr
																				ON (afwcr.post_id = wco.parent_order_id
																					AND wco.type = %s
																					AND afwcr.affiliate_id = %d)
																			WHERE afwcr.datetime BETWEEN %s AND %s",
															'shop_order_refund',
															$affiliate_id,
															$from,
															$to
														),
						'ARRAY_A'
					);
				} else {
					$refunds_result = $wpdb->get_results( // phpcs:ignore
														$wpdb->prepare( // phpcs:ignore
															"SELECT IFNULL(SUM(pm.meta_value), 0) AS refund_amount,
																				IFNULL(COUNT(DISTINCT p.post_parent), 0) AS refund_order_count
																		FROM {$wpdb->posts} AS p
																			JOIN {$wpdb->postmeta} AS pm
																				ON (pm.post_id = p.ID
																						AND pm.meta_key = %s
																						AND p.post_type = %s)
																			JOIN {$wpdb->prefix}afwc_referrals AS afwcr
																				ON (afwcr.post_id = p.post_parent)
																		WHERE afwcr.affiliate_id = %d
																			AND (afwcr.datetime BETWEEN %s AND %s) ",
															'_refund_amount',
															'shop_order_refund',
															$affiliate_id,
															$from,
															$to
														),
						'ARRAY_A'
					);
				}
			} elseif ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
					$refunds_result = $wpdb->get_results( // phpcs:ignore
														$wpdb->prepare( // phpcs:ignore
															"SELECT IFNULL(SUM(ABS(wco.total_amount)), 0) AS refund_amount,
																				IFNULL(COUNT(DISTINCT wco.parent_order_id), 0) AS refund_order_count
																		FROM {$wpdb->prefix}wc_orders AS wco
																			JOIN {$wpdb->prefix}afwc_referrals AS afwcr
																				ON (afwcr.post_id = wco.parent_order_id
																					AND wco.type = %s
																					AND afwcr.affiliate_id = %d)",
															'shop_order_refund',
															$affiliate_id
														),
						'ARRAY_A'
					);
			} else {
				$refunds_result = $wpdb->get_results( // phpcs:ignore
													$wpdb->prepare( // phpcs:ignore
														"SELECT IFNULL(SUM(pm.meta_value), 0) AS refund_amount,
																				IFNULL(COUNT(DISTINCT p.post_parent), 0) AS refund_order_count
																		FROM {$wpdb->posts} AS p
																			JOIN {$wpdb->postmeta} AS pm
																				ON (pm.post_id = p.ID
																						AND pm.meta_key = %s
																						AND p.post_type = %s)
																			JOIN {$wpdb->prefix}afwc_referrals AS afwcr
																				ON (afwcr.post_id = p.post_parent)
																		WHERE afwcr.affiliate_id = %d",
														'_refund_amount',
														'shop_order_refund',
														$affiliate_id
													),
					'ARRAY_A'
				);
			}
			$refunds = array(
				'refund_amount'      => ( isset( $refunds_result[0]['refund_amount'] ) ) ? $refunds_result[0]['refund_amount'] : 0,
				'refund_order_count' => ( isset( $refunds_result[0]['refund_order_count'] ) ) ? $refunds_result[0]['refund_order_count'] : 0,
			);

			return apply_filters( 'afwc_my_account_refunds_result', $refunds, $args );
		}

		/**
		 * Function to get referrals data
		 *
		 * @param array $args arguments.
		 * @return array $referrals referrals data
		 */
		public function get_referrals_data( $args = array() ) {

			if ( ! afwc_is_valid_date_range( $args ) ) {
				return array();
			}

			global $wpdb;

			$from         = ( ! empty( $args['from'] ) ) ? $args['from'] : '';
			$to           = ( ! empty( $args['to'] ) ) ? $args['to'] : '';
			$affiliate_id = ( ! empty( $args['affiliate_id'] ) ) ? $args['affiliate_id'] : 0;
			$limit        = ( ! empty( $args['limit'] ) ) ? $args['limit'] : apply_filters(
				'afwc_my_account_referrals_per_page',
				get_option( 'afwc_my_account_referrals_per_page', AFWC_MY_ACCOUNT_DEFAULT_BATCH_LIMIT, array( 'source' => $this ) )
			);
			$offset       = ( ! empty( $args['offset'] ) ) ? $args['offset'] : 0;

			$args['limit']         = $limit;
			$args['offset']        = $offset;
			$referrals_result      = array();
			$referrals_total_count = 0;
			$customer_name_map     = array();

			if ( ! empty( $from ) && ! empty( $to ) ) {
				// Queries if the date range is provided.
				$referrals_result = $wpdb->get_results( // phpcs:ignore
					$wpdb->prepare(
						"SELECT CONVERT_TZ(afwcr.datetime, '+00:00', %s) as datetime,
							   afwcr.amount,
							   afwcr.currency_id,
							   afwcr.status,
							   IFNULL( afwcr.campaign_id, 0 ) AS campaign,
							   afwcr.post_id AS order_id
						FROM {$wpdb->prefix}afwc_referrals AS afwcr
						WHERE afwcr.affiliate_id = %d
						AND (afwcr.datetime BETWEEN %s AND %s)
						ORDER BY afwcr.datetime DESC
						LIMIT %d OFFSET %d",
						AFWC_TIMEZONE_STR,
						$affiliate_id,
						$from,
						$to,
						$limit,
						$offset
					),
					'ARRAY_A'
				);

				$referrals_total_count = $wpdb->get_var( // phpcs:ignore
					$wpdb->prepare(
						"SELECT COUNT(*)
						FROM {$wpdb->prefix}afwc_referrals AS afwcr
						LEFT JOIN {$wpdb->users} AS u
							ON (afwcr.user_id = u.ID)
							WHERE afwcr.affiliate_id = %d
							AND (afwcr.datetime BETWEEN %s AND %s)",
						$affiliate_id,
						$from,
						$to
					)
				);

			} else {
				// Queries if the date range is not provided.
				$referrals_result = $wpdb->get_results( // phpcs:ignore
					$wpdb->prepare(
						"SELECT CONVERT_TZ(afwcr.datetime, '+00:00', %s) as datetime,
							   afwcr.amount,
							   afwcr.currency_id,
							   afwcr.status,
							   IFNULL( afwcr.campaign_id, 0 ) AS campaign,
							   afwcr.post_id as order_id
						FROM {$wpdb->prefix}afwc_referrals AS afwcr
						WHERE afwcr.affiliate_id = %d
						ORDER BY afwcr.datetime DESC
						LIMIT %d OFFSET %d",
						AFWC_TIMEZONE_STR,
						$affiliate_id,
						$limit,
						$offset
					),
					'ARRAY_A'
				);

				$referrals_total_count = $wpdb->get_var( // phpcs:ignore
					$wpdb->prepare(
						"SELECT COUNT(*)
						FROM {$wpdb->prefix}afwc_referrals AS afwcr
						LEFT JOIN {$wpdb->users} AS u
						ON (afwcr.user_id = u.ID)
						WHERE afwcr.affiliate_id = %d",
						$affiliate_id
					)
				);
			}

			if ( ! empty( $referrals_result ) && is_array( $referrals_result ) ) {
				// Get the order Ids.
				$order_ids         = array_filter(
					array_map(
						function ( $referral ) {
							return ! empty( $referral['order_id'] ) ? absint( $referral['order_id'] ) : 0;
						},
						$referrals_result
					)
				);
				$referrals_details = array();

				if ( ! empty( $order_ids ) ) {
					// Create a temporary option.
					$option_nm = 'afwc_order_ids_' . uniqid();
					update_option( $option_nm, implode( ',', array_unique( $order_ids ) ), 'no' );

					if ( is_callable( 'afwc_is_hpos_enabled' ) && afwc_is_hpos_enabled() ) {
						$referrals_details = $wpdb->get_results( // phpcs:ignore
							$wpdb->prepare(
								"SELECT order_id,
										   CONCAT_WS(' ', first_name, last_name) AS display_name
									FROM {$wpdb->prefix}wc_order_addresses
									WHERE address_type = %s
									AND FIND_IN_SET( order_id , ( SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s ) )",
								'billing',
								$option_nm
							),
							'ARRAY_A'
						);
					} else {
						$referrals_details = $wpdb->get_results( // phpcs:ignore
							$wpdb->prepare(
								"SELECT post_id AS order_id,
									   GROUP_CONCAT(CASE WHEN meta_key IN ('_billing_first_name', '_billing_last_name') THEN meta_value END SEPARATOR ' ') AS display_name
								FROM {$wpdb->postmeta} AS postmeta
								WHERE meta_key IN ('_billing_first_name', '_billing_last_name')
								AND FIND_IN_SET(post_id, (SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s))
								GROUP BY order_id",
								$option_nm
							),
							'ARRAY_A'
						);
					}

					// Delete temporary option.
					delete_option( $option_nm );
				}

				// Format the customer name for fetched orders.
				if ( ! empty( $referrals_details ) && is_array( $referrals_details ) ) {
					foreach ( $referrals_details as $referral ) {
						if ( empty( $referral['order_id'] ) ) {
							continue;
						}
						$customer_name_map[ $referral['order_id'] ] = $referral['display_name'];
					}
				}

				$date_format = get_option( 'date_format' );

				// Format the referral result.
				foreach ( $referrals_result as $key => $ref ) {
					$campaign_title     = ! empty( $ref['campaign'] ) ? afwc_get_campaign_title( intval( $ref['campaign'] ) ) : '';
					$is_campaign_active = ! empty( $ref['campaign'] ) && afwc_is_campaign_active( true, $ref['campaign'] );

					$referrals_result[ $key ]['customer_name']       = ( ! empty( $ref['order_id'] ) && ! empty( $customer_name_map[ $ref['order_id'] ] ) ) ? $customer_name_map[ $ref['order_id'] ] : _x( 'Guest', 'Default value for customer name in my account referral reports', 'affiliate-for-woocommerce' );
					$referrals_result[ $key ]['commission']          = wc_price( ( ! empty( $ref['amount'] ) ? floatval( $ref['amount'] ) : 0 ), array( 'currency' => ! empty( $ref['currency_id'] ) ? $ref['currency_id'] : '' ) );
					$referrals_result[ $key ]['date']                = ! empty( $ref['datetime'] ) && ! empty( $date_format ) ? gmdate( $date_format, strtotime( $ref['datetime'] ) ) : $ref['datetime'];
					$referrals_result[ $key ]['campaign']            = ! empty( $ref['campaign'] ) ? intval( $ref['campaign'] ) : 0;
					$referrals_result[ $key ]['campaign_title']      = ! empty( $is_campaign_active ) ? $campaign_title : ''; // Title should show if campaign is active for the affiliate.
					$referrals_result[ $key ]['is_campaign_deleted'] = empty( $campaign_title ); // Check whether deleted or not based on campaign title as campaign could not exists without title.
				}
			}

			$referrals = array(
				'rows'        => $referrals_result,
				'total_count' => $referrals_total_count,
			);

			return apply_filters( 'afwc_my_account_referrals_result', $referrals, $args );
		}

		/**
		 * Function to show content in affiliate profile tab.
		 *
		 * @param WP_User $user The user object.
		 */
		public function profile_resources_content( $user = null ) {
			if ( ! is_object( $user ) || empty( $user->ID ) ) {
				return;
			}

			if ( ! wp_script_is( 'jquery' ) ) {
				wp_enqueue_script( 'jquery' );
			}

			if ( ! class_exists( 'WC_AJAX' ) ) {
				include_once WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-ajax.php';
			}

			global $affiliate_for_woocommerce;

			// Data.
			$user_id                                = intval( $user->ID );
			$affiliate                              = new AFWC_Affiliate( $user_id );
			$pname                                  = afwc_get_pname();
			$affiliate_id                           = afwc_get_affiliate_id_based_on_user_id( $user_id );
			$affiliate_identifier                   = is_callable( array( $affiliate, 'get_identifier' ) ) ? $affiliate->get_identifier() : '';
			$afwc_allow_custom_affiliate_identifier = get_option( 'afwc_allow_custom_affiliate_identifier', 'yes' );
			$afwc_use_pretty_referral_links         = get_option( 'afwc_use_pretty_referral_links', 'no' );
			$use_referral_coupon                    = get_option( 'afwc_use_referral_coupons', 'yes' );
			$afwc_enable_stripe_payout              = get_option( 'afwc_enable_stripe_payout', 'no' );
			$affiliate_payout_method                = get_user_meta( $user_id, 'afwc_payout_method', true );

			$plugin_data = $affiliate_for_woocommerce->get_plugin_data();
			if ( ! wp_script_is( 'afwc-profile-js' ) ) {
				wp_register_script( 'afwc-profile-js', AFWC_PLUGIN_URL . '/assets/js/my-account/affiliate-profile.js', array( 'jquery', 'wp-i18n', 'afwc-affiliate-link', 'afwc-click-to-copy' ), $plugin_data['Version'], true );
				if ( function_exists( 'wp_set_script_translations' ) ) {
					wp_set_script_translations( 'afwc-profile-js', 'affiliate-for-woocommerce', AFWC_PLUGIN_DIR_PATH . 'languages' );
				}
			}
			wp_enqueue_script( 'afwc-profile-js' );

			$localize_params = array(
				'pName'                    => $pname,
				'homeURL'                  => esc_url( trailingslashit( home_url() ) ),
				'saveAccountDetailsURL'    => esc_url_raw( WC_AJAX::get_endpoint( 'afwc_save_account_details' ) ),
				'saveAccountSecurity'      => wp_create_nonce( 'afwc-save-account-details' ),
				'isPrettyReferralEnabled'  => $afwc_use_pretty_referral_links,
				'savedAffiliateIdentifier' => $affiliate_identifier,
				'stripeJustConnected'      => 'no',
			);

			if ( 'yes' === $afwc_allow_custom_affiliate_identifier ) {
				$localize_params['identifierRegexPattern']                  = afwc_affiliate_identifier_regex_pattern();
				$localize_params['identifierPatternValidationErrorMessage'] = apply_filters( 'afwc_affiliate_identifier_regex_pattern_error_message', _x( 'Invalid identifier. It should be a combination of alphabets and numbers, but the number should not be in the first position.', 'referral identifier pattern validation error message', 'affiliate-for-woocommerce' ) );
				$localize_params['saveReferralURLIdentifier']               = esc_url_raw( WC_AJAX::get_endpoint( 'afwc_save_ref_url_identifier' ) );
				$localize_params['saveIdentifierSecurity']                  = wp_create_nonce( 'afwc-save-ref-url-identifier' );
			}

			if ( 'yes' === get_option( 'afwc_enable_stripe_payout', 'no' ) ) {
				$stripe_connect_api = is_callable( array( 'AFWC_Stripe_Connect', 'get_instance' ) ) ? AFWC_Stripe_Connect::get_instance() : null;
				$oauth_link         = ( ! empty( $stripe_connect_api ) && is_callable( array( $stripe_connect_api, 'get_oauth_link' ) ) ) ? $stripe_connect_api->get_oauth_link() : '';

				$stripe_functions = is_callable( array( 'AFWC_Stripe_Functions', 'get_instance' ) ) ? AFWC_Stripe_Functions::get_instance() : null;
				$current_status   = ( ! empty( $stripe_functions ) && is_callable( array( $stripe_functions, 'afwc_get_stripe_user_status' ) ) ) ? $stripe_functions->afwc_get_stripe_user_status( $user_id ) : 'disconnect';

				$localize_params['ajaxURL']                       = admin_url( 'admin-ajax.php' );
				$localize_params['disconnectStripeConnectAction'] = 'disconnect_stripe_connect';
				$localize_params['oauthLink']                     = $oauth_link;
				$localize_params['stripeJustConnected']           = ( 'connect' === $current_status && 'stripe' === $affiliate_payout_method && isset( $_GET['scope'], $_GET['code'] ) ? 'yes' : 'no' ); // phpcs:ignore
			}

			wp_localize_script( 'afwc-profile-js', 'afwcProfileParams', $localize_params );

			wp_register_style( 'afwc-profile-css', AFWC_PLUGIN_URL . '/assets/css/my-account/affiliate-profile.css', array(), $plugin_data['Version'], 'all' );
			if ( ! wp_style_is( 'afwc-profile-css', 'enqueued' ) ) {
				wp_enqueue_style( 'afwc-profile-css' );
			}

			// Template name.
			$template = 'my-account/affiliate-profile.php';
			// Default path of above template.
			$default_path = AFWC_PLUGIN_DIRPATH . '/templates/';
			// Pick from another location if found.
			$template_path = $affiliate_for_woocommerce->get_template_base_dir( $template );

			wc_get_template(
				$template,
				array(
					'user'                            => $user,
					'user_id'                         => $user_id,
					'pname'                           => $pname,
					'affiliate_url'                   => is_callable( array( $affiliate, 'get_affiliate_link' ) ) ? $affiliate->get_affiliate_link() : '',
					'affiliate_id'                    => $affiliate_id,
					'affiliate_identifier'            => $affiliate_identifier,
					'affiliate_manager_contact_email' => get_option( 'afwc_contact_admin_email_address', '' ),
					'afwc_use_referral_coupons'       => $use_referral_coupon,
					'afwc_landings_pages'             => is_callable( array( $affiliate, 'get_landing_page_links' ) ) ? $affiliate->get_landing_page_links() : array(),
					'afwc_allow_custom_affiliate_identifier' => $afwc_allow_custom_affiliate_identifier,
					'afwc_use_pretty_referral_links'  => $afwc_use_pretty_referral_links,
					'show_coupon_url'                 => apply_filters( 'afwc_show_coupon_url_in_my_account_profile', ( afwc_is_plugin_active( 'woocommerce-smart-coupons/woocommerce-smart-coupons.php' ) && 'yes' === $use_referral_coupon ) ? 'yes' : 'no' ),
					'afwc_enable_stripe_payout'       => $afwc_enable_stripe_payout,
					'payout_method'                   => $affiliate_payout_method,
					'available_payout_methods'        => afwc_get_available_payout_methods_for_affiliate( $user_id ),
					'current_status'                  => ( ! empty( $current_status ) ? $current_status : '' ),
				),
				$template_path,
				$default_path
			);
		}

		/**
		 * Function to save account details
		 */
		public function afwc_save_account_details() {
			check_ajax_referer( 'afwc-save-account-details', 'security' );

			$user_id = get_current_user_id();
			if ( empty( $user_id ) ) {
				wp_send_json(
					array(
						'success' => 'no',
						'message' => _x( 'Invalid user', 'account details updating error message', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$form_data = ( ! empty( $_POST['form_data'] ) ) ? sanitize_text_field( wp_unslash( $_POST['form_data'] ) ) : '';
			if ( empty( $form_data ) ) {
				wp_send_json(
					array(
						'success' => 'no',
						'message' => _x(
							'Missing data',
							'account details updating error message',
							'affiliate-for-woocommerce'
						),
					)
				);
			}

			if ( ! empty( $form_data ) ) {
				parse_str( $form_data, $data );
			}

			$payout_method = ! empty( $data['afwc_payout_method'] ) ? $data['afwc_payout_method'] : '';
			if ( empty( $payout_method ) ) {
				delete_user_meta( $user_id, 'afwc_payout_method' );
				wp_send_json( array( 'success' => 'yes' ) );
			} else {
				update_user_meta( $user_id, 'afwc_payout_method', $payout_method );
			}

			$paypal_email = ! empty( $data['afwc_affiliate_paypal_email'] ) ? $data['afwc_affiliate_paypal_email'] : '';

			// Send success and delete the user meta if PayPal email is empty.
			if ( empty( $paypal_email ) ) {
				delete_user_meta( $user_id, 'afwc_paypal_email' );
				wp_send_json( array( 'success' => 'yes' ) );
			}

			// Send failure message if the email address is not valid.
			if ( false === is_email( $paypal_email ) ) {
				wp_send_json(
					array(
						'success' => 'no',
						'message' => _x( 'The PayPal email address is incorrect.', 'Affiliate My Account page: PayPal email validation', 'affiliate-for-woocommerce' ),
					)
				);
			}

			// Send success and update the PayPal email.
			update_user_meta( $user_id, 'afwc_paypal_email', sanitize_email( $paypal_email ) );
			wp_send_json( array( 'success' => 'yes' ) );
		}

		/**
		 * Function to save referral URL identifier
		 *
		 * @throws Exception If any error during the process.
		 */
		public function afwc_save_ref_url_identifier() {
			check_ajax_referer( 'afwc-save-ref-url-identifier', 'security' );

			$ref_url_id = ( ! empty( $_POST['ref_url_id'] ) ) ? wc_clean( wp_unslash( $_POST['ref_url_id'] ) ) : ''; // phpcs:ignore

			if ( empty( $ref_url_id ) ) {
				wp_send_json(
					array(
						'success' => 'no',
						'message' => _x(
							'Missing data',
							'referral url identifier updating error message',
							'affiliate-for-woocommerce'
						),
					)
				);
			}

			global $affiliate_for_woocommerce;

			try {

				if ( 'yes' !== get_option( 'afwc_allow_custom_affiliate_identifier', 'yes' ) ) {
					throw new Exception(
						_x(
							'Custom affiliate URL identifier not allowed.',
							'referral url identifier updating error message',
							'affiliate-for-woocommerce'
						)
					);
				}

				if ( ! is_callable( array( $affiliate_for_woocommerce, 'save_ref_url_identifier' ) ) || ! $affiliate_for_woocommerce->save_ref_url_identifier( get_current_user_id(), $ref_url_id ) ) {
					throw new Exception(
						_x(
							'The URL identifier could not updated',
							'referral url identifier updating error message',
							'affiliate-for-woocommerce'
						)
					);
				}

				wp_send_json(
					array(
						'success' => 'yes',
						'message' => _x(
							'Identifier saved successfully.',
							'referral url identifier updated message',
							'affiliate-for-woocommerce'
						),
					)
				);
			} catch ( Exception $e ) {

				wp_send_json(
					array(
						'success' => 'no',
						'message' => is_callable( array( $e, 'getMessage' ) ) ? $e->getMessage() : _x(
							'Something went wrong',
							'referral url identifier updating error message',
							'affiliate-for-woocommerce'
						),
					)
				);
			}
		}

		/**
		 * Hooks for endpoint
		 */
		public function endpoint_hooks() {
			if ( is_callable( array( $this, 'is_wc_gte_34' ) ) && $this->is_wc_gte_34() ) {
				add_filter( 'woocommerce_get_settings_advanced', array( $this, 'add_endpoint_account_settings' ) );
			} else {
				add_filter( 'woocommerce_account_settings', array( $this, 'add_endpoint_account_settings' ) );
			}
		}

		/**
		 * Add UI option for changing Affiliate endpoints in WC settings
		 *
		 * @param mixed $settings Existing settings.
		 * @return mixed $settings
		 */
		public function add_endpoint_account_settings( $settings ) {
			$affiliate_endpoint_setting = array(
				'title'    => __( 'Affiliate', 'affiliate-for-woocommerce' ),
				'desc'     => __( 'Endpoint for the My Account &rarr; Affiliate page', 'affiliate-for-woocommerce' ),
				'id'       => 'woocommerce_myaccount_afwc_dashboard_endpoint',
				'type'     => 'text',
				'default'  => 'afwc-dashboard',
				'desc_tip' => true,
			);

			$after_key = 'woocommerce_myaccount_view_order_endpoint';

			$after_key = apply_filters(
				'afwc_endpoint_account_settings_after_key',
				$after_key,
				array(
					'settings' => $settings,
					'source'   => $this,
				)
			);

			Affiliate_For_WooCommerce::insert_setting_after( $settings, $after_key, $affiliate_endpoint_setting );

			return $settings;
		}

		/**
		 * Function to show campaigns content resources
		 *
		 * @param WP_User $user The user object.
		 */
		public function campaigns_content( $user = null ) {
			if ( ! is_object( $user ) || empty( $user->ID ) ) {
				return;
			}
			?>
			<div class="afw-campaigns"></div>

			<?php
		}

		/**
		 * Function to show multi tier content resources.
		 *
		 * @param WP_User $user The user object.
		 */
		public function multi_tier_content( $user = null ) {
			if ( ! is_object( $user ) || empty( $user->ID ) ) {
				return;
			}
			?>
			<div class="afw-multi-tier"></div>

			<?php
		}

		/**
		 * Get gmt date time.
		 *
		 * @param string $date The date.
		 * @param string $format The date format.
		 *
		 * @return string Return the date with gmt formatted if date is provided otherwise empty string.
		 */
		public function gmt_from_date( $date = '', $format = 'Y-m-d H:m:s' ) {
			if ( empty( $date ) ) {
				return '';
			}

			return get_gmt_from_date( $date, $format );
		}

		/**
		 * Method to check whether the current page having affiliate dashboard.
		 *
		 * @return bool.
		 */
		public function is_afwc_dashboard() {
			return $this->is_afwc_endpoint() || ( is_callable( 'wc_post_content_has_shortcode' ) && wc_post_content_has_shortcode( 'afwc_dashboard' ) );
		}

		/**
		 * Method to render the affiliate dashboard by shortcode.
		 *
		 * @return string Show the Affiliate dashboard screen for logged in user otherwise WooCommerce login form.
		 */
		public function afwc_dashboard_shortcode_content() {
			ob_start();

			$current_user = wp_get_current_user();
			if ( ! $current_user instanceof WP_User || empty( $current_user->ID ) ) {
				// Show the WooCommerce login form if user is not logged in.
				woocommerce_login_form();
			} else {
				$affiliate_status = afwc_is_user_affiliate( $current_user );

				if ( ! empty( $affiliate_status ) ) {

					$afwc_registration = AFWC_Registration_Submissions::get_instance();

					if ( in_array( $affiliate_status, array( 'not_registered', 'pending', 'no' ), true ) && is_callable( array( $afwc_registration, 'get_message' ) ) ) {
						// Show message for not registered, pending and rejected affiliates.
						echo wp_kses_post( $afwc_registration->get_message( $affiliate_status ) );
					} elseif ( 'yes' === $affiliate_status ) {
						// Render the dashboard for approved affiliate.
						$this->afwc_dashboard_content( $current_user );
					}
				}
			}

			return ob_get_clean();
		}

		/**
		 * Method to retrieve the products to display in my account.
		 *
		 * @param array $args The arguments.
		 *
		 * @return array Return the array of products.
		 */
		public function get_products_data( $args = array() ) {

			$args = is_array( $args ) ? $args : array();

			if ( ! afwc_is_valid_date_range( $args ) ) {
				return array();
			}

			$args['batch_limit'] = ( ! empty( $args['limit'] ) ) ? $args['limit'] : apply_filters(
				'afwc_my_account_products_per_page',
				get_option( 'afwc_my_account_products_per_page', AFWC_MY_ACCOUNT_DEFAULT_BATCH_LIMIT, array( 'source' => $this ) )
			);

			// To use same for the admin side.
			$products_result = ( is_callable( array( 'Affiliate_For_WooCommerce', 'get_products_data' ) ) ) ? Affiliate_For_WooCommerce::get_products_data( $args ) : array();
			if ( empty( $products_result ) ) {
				return array();
			}

			$products = ( ! empty( $products_result['rows'] ) && is_array( $products_result['rows'] ) ) ? $products_result['rows'] : array();
			if ( empty( $products ) || ! is_array( $products ) ) {
				return $products_result;
			}

			foreach ( $products as $key => $product ) {
				$products[ $key ]['sales'] = wc_price( ( ! empty( $product['sales'] ) ? floatval( $product['sales'] ) : 0 ) );
				// Get product variation ID to generate viewable link.
				$product_variation_id = explode( '_', $key );
				// Get view product link based on variation ID - if available, else product ID.
				$view_product_url = get_permalink( ( ! empty( $product_variation_id[1] ) ) ? $product_variation_id[1] : $product_variation_id[0] );
				// Replace product name with product link and name.
				$products[ $key ]['product'] = '<a href="' . esc_url( $view_product_url ) . '" target="_blank">' . $product['product'] . '</a>';
			}

			$products_result['rows'] = $products;

			return apply_filters( 'afwc_my_account_products_result', $products_result, array( 'source' => $this ) );
		}

		/**
		 * Method to retrieve the payout data to display in my account.
		 *
		 * @param array $args The arguments.
		 *
		 * @return array Return the array of payout data.
		 */
		public function get_payouts_data( $args = array() ) {

			$args = is_array( $args ) ? $args : array();

			if ( ! afwc_is_valid_date_range( $args ) ) {
				return array();
			}

			$args['batch_limit'] = ( ! empty( $args['limit'] ) ) ? $args['limit'] : apply_filters(
				'afwc_my_account_payouts_per_page',
				get_option( 'afwc_my_account_payouts_per_page', AFWC_MY_ACCOUNT_DEFAULT_BATCH_LIMIT, array( 'source' => $this ) )
			);

			$payouts_result = ( is_callable( array( 'Affiliate_For_WooCommerce', 'get_affiliates_payout_history' ) ) ) ? Affiliate_For_WooCommerce::get_affiliates_payout_history( $args ) : array();
			if ( empty( $payouts_result ) ) {
				return array();
			}

			$payouts = ! empty( $payouts_result['payouts'] ) && is_array( $payouts_result['payouts'] ) ? $payouts_result['payouts'] : array();
			if ( empty( $payouts ) || ! is_array( $payouts ) ) {
				return $payouts_result;
			}

			$date_format = get_option( 'date_format' );

			foreach ( $payouts as $key => $payout ) {
				$payouts[ $key ]['payout_amount'] = wc_price( ( ! empty( $payout['amount'] ) ? floatval( $payout['amount'] ) : 0 ), array( 'currency' => ! empty( $payout['currency'] ) ? $payout['currency'] : '' ) );
				$payouts[ $key ]['method']        = ! empty( $payout['method'] ) ? afwc_get_payout_methods( $payout['method'] ) : '';
				$payouts[ $key ]['date']          = ! empty( $payout['datetime'] ) && ! empty( $date_format ) ? gmdate( $date_format, strtotime( $payout['datetime'] ) ) : $payout['datetime'];
			}

			$payouts_result['payouts'] = $payouts;

			return apply_filters( 'afwc_my_account_payouts_result', $payouts_result, array( 'source' => $this ) );
		}

		/**
		 * Method to get the referral report headers.
		 *
		 * @return array Return the array header data.
		 */
		public function get_referrals_report_headers() {
			$headers = array(
				'date'       => _x( 'Date', 'Referrals table header title for date column', 'affiliate-for-woocommerce' ),
				'order_id'   => _x( 'Order ID', 'Referrals table header title for order id column', 'affiliate-for-woocommerce' ),
				'commission' => _x( 'Commission', 'Referrals table header title for commission column', 'affiliate-for-woocommerce' ),
				'status'     => _x( 'Payout status', 'Referrals table header title for payout status column', 'affiliate-for-woocommerce' ),
			);

			if ( apply_filters( 'afwc_account_show_customer_column', false, array( 'source' => $this ) ) ) {
				$headers['customer_name'] = _x( 'Customer', 'Referrals table header title for customer column', 'affiliate-for-woocommerce' );
			}

			$headers['campaign'] = _x( 'Campaign', 'Referrals table header title for campaign column', 'affiliate-for-woocommerce' );

			return apply_filters( 'afwc_my_account_get_referral_report_header', $headers, array( 'source' => $this ) );
		}

		/**
		 * Method to get the product report headers.
		 *
		 * @return array Return the array header data.
		 */
		public function get_products_report_headers() {
			return apply_filters(
				'afwc_my_account_get_products_report_header',
				array(
					'product' => _x( 'Product', 'Products table header title for product name column', 'affiliate-for-woocommerce' ),
					'qty'     => _x( 'Quantity', 'Products table header title for quantity column', 'affiliate-for-woocommerce' ),
					'sales'   => _x( 'Sales', 'Products table header title for sales column', 'affiliate-for-woocommerce' ),
				),
				array( 'source' => $this )
			);
		}

		/**
		 * Method to get the payout report headers.
		 *
		 * @return array Return the array header data.
		 */
		public function get_payouts_report_headers() {
			return apply_filters(
				'afwc_my_account_get_payouts_report_header',
				array(
					'date'          => _x( 'Date', 'Payouts table header title for date column', 'affiliate-for-woocommerce' ),
					'payout_amount' => _x( 'Amount', 'Payouts table header title for payout amount column', 'affiliate-for-woocommerce' ),
					'method'        => _x( 'Method', 'Payouts table header title for payout method column', 'affiliate-for-woocommerce' ),
					'payout_notes'  => _x( 'Notes', 'Payouts table header title for payout notes column', 'affiliate-for-woocommerce' ),
				),
				array( 'source' => $this )
			);
		}

		/**
		 * Ajax callback method to return the invoice with HTML template.
		 */
		public function ajax_payout_invoice() {
			check_ajax_referer( 'afwc-payout-invoice', 'security' );

			if (
				! is_callable( array( 'AFWC_Payout_Invoice', 'is_enabled_for_affiliate' ) )
				|| ! AFWC_Payout_Invoice::is_enabled_for_affiliate()
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
					'payout_id'         => ( ! empty( $_POST['payout_id'] ) ) ? intval( wc_clean( $_POST['payout_id'] ) ) : 0, // phpcs:ignore
					'affiliate_id'           => ( ! empty( $_POST['affiliate_id'] ) ) ? intval( wc_clean( $_POST['affiliate_id'] ) ) : 0, // phpcs:ignore
					'date_time'       => ( ! empty( $_POST['date_time'] ) ) ? wc_clean( $_POST['date_time']  ) : '', // phpcs:ignore
					'from_period'  => ( ! empty( $_POST['from_period'] ) ) ? wc_clean( $_POST['from_period']  ) : '', // phpcs:ignore
					'to_period' => ( ! empty( $_POST['to_period'] ) ) ? wc_clean( $_POST['to_period']  ) : '', // phpcs:ignore
					'referral_count' => ( ! empty( $_POST['referral_count'] ) ) ? intval( wc_clean( $_POST['referral_count'] ) ) : 0, // phpcs:ignore
					'amount' => ( ! empty( $_POST['amount'] ) ) ? floatval( wc_clean( $_POST['amount'] ) ) : 0, // phpcs:ignore
					'currency' => ( ! empty( $_POST['currency'] ) ) ? wc_clean( $_POST['currency']  ) : '', // phpcs:ignore
					'method' => ( ! empty( $_POST['method'] ) ) ? wc_clean( $_POST['method']  ) : '', // phpcs:ignore
					'notes' => ( ! empty( $_POST['notes'] ) ) ? wc_clean( $_POST['notes'] )  : '', // phpcs:ignore
				)
			);

			wp_die();
		}

		/**
		 * Method to get the tab link.
		 *
		 * @param string $tab The tab name.
		 * @param string $current_url The current URL.
		 * @param array  $query_vars The query variables.
		 *
		 * @return string Return the tab link.
		 */
		public function get_tab_link( $tab = '', $current_url = '', $query_vars = array() ) {
			if ( empty( $tab ) ) {
				return '';
			}

			$current_url = remove_query_arg(
				array( $this->afwc_tab_endpoint, $this->afwc_section_endpoint, 'from-date', 'to-date' ),
				! empty( $current_url ) ? $current_url : afwc_get_current_url()
			);
			return add_query_arg( array_filter( array_merge( array( $this->afwc_tab_endpoint => $tab ), $query_vars ) ), $current_url );
		}

		/**
		 * Get the value of the {afwc_affiliate_coupon} merge tag.
		 * The value will be available for Profile tab only.
		 *
		 * @param string $value The value.
		 * @param array  $args An array of arguments.
		 *
		 * @return string The value.
		 */
		public function get_afwc_affiliate_coupon_merge_tag_value( $value = '', $args = array() ) {
			global $wp;

			if ( empty( $wp->query_vars ) || empty( $wp->query_vars[ $this->afwc_tab_endpoint ] ) || 'resources' !== $wp->query_vars[ $this->afwc_tab_endpoint ] ) { // Check whether profile tab is active.
				return $value;
			}

			$attrs = ! empty( $args['attrs'] ) && is_array( $args['attrs'] ) ? $args['attrs'] : array();

			if ( empty( $attrs ) || empty( $attrs['code'] ) ) { // Check whether the coupon code is provided.
				return $value;
			}

			$afwc_coupon = is_callable( array( 'AFWC_Coupon', 'get_instance' ) ) ? AFWC_Coupon::get_instance() : null;
			$coupon_url  = is_callable( array( $afwc_coupon, 'get_coupon_url' ) ) ? $afwc_coupon->get_coupon_url( $attrs['code'] ) : '';
			return ! empty( $coupon_url ) ? afwc_get_click_to_copy_html( $coupon_url ) : '';
		}

		/**
		 * Method to handle the Stripe Connect's connection.
		 */
		public function afwc_handle_stripe_connect() {
			if ( ! is_user_logged_in() ) {
				return;
			}

			if ( ! $this->is_afwc_dashboard() ) {
				return;
			}

			$user_id = get_current_user_id();
			if ( empty( $user_id ) ) {
				return;
			}

			global $wp;

			if ( ! empty( $wp->query_vars ) && ! empty( $wp->query_vars[ $this->afwc_tab_endpoint ] ) && 'resources' === $wp->query_vars[ $this->afwc_tab_endpoint ] ) {
				$stripe_functions = is_callable( array( 'AFWC_Stripe_Functions', 'get_instance' ) ) ? AFWC_Stripe_Functions::get_instance() : null;
				if ( empty( $stripe_functions ) ) {
					return;
				}

				// The page has loaded from Stripe Platform, some user want connect with us.
				if ( isset( $_GET['scope'] ) && isset( $_GET['code'] ) ) { // phpcs:ignore
					$code = sanitize_text_field( wp_unslash( $_GET['code'] ) ); // phpcs:ignore

					if ( is_callable( array( $stripe_functions, 'afwc_get_stripe_user_status' ) ) ) {
						$just_connected = $stripe_functions->connect_by_user_id_and_access_code( $user_id, $code );
					}
				}
			}
		}
	}
}

AFWC_My_Account::get_instance();
