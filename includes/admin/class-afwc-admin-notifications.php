<?php
/**
 * Affiliate For WooCommerce Admin Notifications
 *
 * @package     affiliate-for-woocommerce/includes/admin/
 * @since       1.3.4
 * @version     1.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Admin_Notifications' ) ) {

	/**
	 * Class for handling admin notifications of Affiliate For WooCommerce
	 */
	class AFWC_Admin_Notifications {

		/**
		 * Variable to hold instance of AFWC_Admin_Notifications
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Constructor
		 */
		private function __construct() {

			// Filter to add Settings link on Plugins page.
			add_filter( 'plugin_action_links_' . plugin_basename( AFWC_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );

			// To update footer text on AFW screens.
			add_filter( 'admin_footer_text', array( $this, 'afwc_footer_text' ) );
			add_filter( 'update_footer', array( $this, 'afwc_update_footer_text' ), 99 );

			// To show admin notifications.
			add_action( 'admin_init', array( $this, 'afw_dismiss_admin_notice' ) );

			// Show Admin notices.
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'admin_notices', array( $this, 'rule_update_admin_notices' ) );
		}

		/**
		 * Get single instance of AFWC_Admin_Notifications
		 *
		 * @return AFWC_Admin_Notifications Singleton object of AFWC_Admin_Notifications
		 */
		public static function get_instance() {
			// Check if instance is already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Function to add more action on plugins page
		 *
		 * @param array $links Existing links.
		 * @return array $links
		 */
		public function plugin_action_links( $links ) {

			$settings_link = add_query_arg(
				array(
					'page' => 'wc-settings',
					'tab'  => 'affiliate-for-woocommerce-settings',
				),
				admin_url( 'admin.php' )
			);

			$getting_started_link = add_query_arg( array( 'page' => 'affiliate-for-woocommerce-documentation' ), admin_url( 'admin.php' ) );

			$action_links = array(
				'getting-started' => '<a href="' . esc_url( $getting_started_link ) . '">' . esc_html( __( 'Getting started', 'affiliate-for-woocommerce' ) ) . '</a>',
				'settings'        => '<a href="' . esc_url( $settings_link ) . '">' . esc_html( __( 'Settings', 'affiliate-for-woocommerce' ) ) . '</a>',
				'docs'            => '<a target="_blank" href="' . esc_url( AFWC_DOC_DOMAIN ) . '">' . __( 'Docs', 'affiliate-for-woocommerce' ) . '</a>',
				'support'         => '<a target="_blank" href="' . esc_url( 'https://woocommerce.com/my-account/contact-support/?select=affiliate-for-woocommerce' ) . '">' . __( 'Support', 'affiliate-for-woocommerce' ) . '</a>',
				// View all the reviews link.
				'review'          => '<a target="_blank" href="' . esc_url( 'https://woocommerce.com/products/affiliate-for-woocommerce/#reviews' ) . '">' . __( 'Reviews', 'affiliate-for-woocommerce' ) . '</a>',
			);

			return array_merge( $action_links, $links );
		}

		/**
		 * Function to ask to review the plugin in footer
		 *
		 * @param  string $afw_rating_text Text in footer (left).
		 * @return string $afw_rating_text
		 */
		public function afwc_footer_text( $afw_rating_text ) {

			global $pagenow;

			if ( empty( $pagenow ) ) {
				return $afw_rating_text;
			}

			$get_page  = ( ! empty( $_GET['page'] ) ) ? wc_clean( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
			$get_tab   = ( ! empty( $_GET['tab'] ) ) ? wc_clean( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore
			$afw_pages = array( 'affiliate-for-woocommerce-documentation', 'affiliate-for-woocommerce' );

			if ( in_array( $get_page, $afw_pages, true ) || 'affiliate-for-woocommerce-settings' === $get_tab ) {
				?>
				<style type="text/css">
					#wpfooter {
						display: block !important;
					}
				</style>
				<?php
				/* translators: %1$s: Opening strong tag for plugin title %2$s: Closing strong tag for plugin title %3$s: link to review Affiliate For WooCommerce */
				$afw_rating_text = wp_kses_post( sprintf( _x( 'If you like %1$sAffiliate For WooCommerce%2$s, please give us %3$s. A huge thanks from WooCommerce & StoreApps in advance!', 'text for review request', 'affiliate-for-woocommerce' ), '<strong>', '</strong>', '<a target="_blank" href="' . esc_url( 'https://woocommerce.com/products/affiliate-for-woocommerce/?review' ) . '" style="color: #5850EC;">5-star rating</a>' ) );
			}

			return $afw_rating_text;
		}

		/**
		 * Function to ask to leave an idea on WC ideaboard
		 *
		 * @param  string $afw_text Text in footer (right).
		 * @return string $afw_text
		 */
		public function afwc_update_footer_text( $afw_text ) {

			global $pagenow;

			if ( empty( $pagenow ) ) {
				return $afw_text;
			}

			$get_page  = ( ! empty( $_GET['page'] ) ) ? wc_clean( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
			$get_tab   = ( ! empty( $_GET['tab'] ) ) ? wc_clean( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore
			$afw_pages = array( 'affiliate-for-woocommerce-documentation', 'affiliate-for-woocommerce' );

			if ( in_array( $get_page, $afw_pages, true ) || 'affiliate-for-woocommerce-settings' === $get_tab ) {
				?>
				<style type="text/css">
					#wpfooter {
						display: block !important;
					}
				</style>
				<?php
				$plugin_data = Affiliate_For_WooCommerce::get_plugin_data();
				/* translators: %1$s: Plugin version number %2$s: link to submit idea for Affiliate For WooCommerce on WooCommerce idea board */
				$afw_text = sprintf( _x( 'v%1$s | Suggest a feature request or an enhancement from  %2$s.', 'text for feature request submission on WooCommerce idea board', 'affiliate-for-woocommerce' ), $plugin_data['Version'], '<a href="' . esc_url( 'https://woocommerce.com/feature-requests/affiliate-for-woocommerce/' ) . '" target="_blank" style="color: #5850EC;">here</a>' );
			}

			return $afw_text;
		}

		/**
		 * Function to dismiss any admin notice
		 */
		public function afw_dismiss_admin_notice() {

			$afw_dismiss_admin_notice = ( ! empty( $_GET['afw_dismiss_admin_notice'] ) ) ? wc_clean( wp_unslash( $_GET['afw_dismiss_admin_notice'] ) ) : ''; // phpcs:ignore
			$afw_option_name          = ( ! empty( $_GET['option_name'] ) ) ? wc_clean( wp_unslash( $_GET['option_name'] ) ) : ''; // phpcs:ignore

			if ( ! empty( $afw_dismiss_admin_notice ) && '1' === $afw_dismiss_admin_notice && ! empty( $afw_option_name ) ) {
				update_option( $afw_option_name . '_affiliate_wc', 'no', 'no' );
				$referer = wp_get_referer();
				wp_safe_redirect( $referer );
				exit();
			}
		}

		/**
		 * Method to register the admin notices.
		 */
		public function admin_notices() {

			// Show Summary email feature notice.
			$title         = _x( '📢 Big Update:', 'Notice title for affiliate summary report', 'affiliate-for-woocommerce' );
			$message       = _x(
				"You can now send affiliates monthly summary emails of their last month's performance.",
				'Notice text for affiliate summary report',
				'affiliate-for-woocommerce'
			);
			$email_url     = add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => 'email',
					'section' => 'afwc_email_affiliate_summary_reports',
				),
				admin_url( 'admin.php' )
			);
			$action_button = sprintf( '<a href="%1$s" class="button button-primary">%2$s</a>', esc_url( $email_url ), _x( 'Enable now', 'Text for Affiliate summary report email enable', 'affiliate-for-woocommerce' ) );

			$this->show_notice( 'afwc-feature-summary-email', 'info', $title, $message, $action_button, true );
		}

		/**
		 * Method to register the admin notices.
		 */
		public function rule_update_admin_notices() {

			// Show commission rule update notice.
			$title         = _x( '📣 Big update:', 'Notice title for affiliate summary report', 'affiliate-for-woocommerce' );
			$message       = array(
				_x(
					'We have improved commission calculations in certain cases. New referral orders may have updated commissions issued (the existing referrals remain unaffected).',
					'Notice text for affiliate commission rule calculation update',
					'affiliate-for-woocommerce'
				),
				_x(
					'Please review your commission plans and place a test order to verify everything is working as expected.',
					'Notice text for affiliate commission rule calculation update',
					'affiliate-for-woocommerce'
				),
			);
			$action_url    = admin_url( 'admin.php?page=affiliate-for-woocommerce#!/plans' );
			$action_button = sprintf(
				'<a href="%1$s" class="button button-primary">%2$s</a>',
				esc_url( $action_url ),
				_x( 'Review plans', 'Link to open plan dashboard', 'affiliate-for-woocommerce' )
			);

			$this->show_notice( 'afwc-commission-rule-update', 'info', $title, $message, $action_button, true );
		}

		/**
		 * Method to render admin notice
		 *
		 * @param string       $id           Notice ID.
		 * @param string       $type         Notice type.
		 * @param string       $title        Notice title.
		 * @param string|array $message      Notice message(s).
		 * @param string       $action       Notice actions.
		 * @param bool         $dismissible  Notice dismissible.
		 * @return void.
		 */
		public function show_notice( $id = '', $type = 'info', $title = '', $message = '', $action = '', $dismissible = false ) {

			if ( empty( $id ) || 'no' === get_option( $id . '_affiliate_wc', 'yes' ) ) {
				return;
			}
			$css_classes = array(
				'notice',
				'notice-' . $type,
			);
			?>
			<div class="<?php echo esc_attr( implode( ' ', $css_classes ) ); ?>">
				<?php
				if ( ! empty( $title ) ) {
					printf( '<p><strong>%s</strong></p>', esc_html( $title ) );
				}
				if ( ! empty( $message ) ) {
					if ( is_array( $message ) ) {
						foreach ( $message as $single_msg ) {
							printf( '<p>%s</p>', esc_html( $single_msg ) );
						}
					} else {
						printf( '<p>%s</p>', esc_html( $message ) );
					}
				}
				?>
				<p>
				<?php
				if ( ! empty( $action ) ) {
					printf( '<span style="padding-right: 1rem;" class="submit">%s</span>', wp_kses_post( $action ) );
				}
				if ( ! empty( $dismissible ) ) {
					printf( '<span><a href="?afw_dismiss_admin_notice=1&option_name=%1$s">%2$s</a></span>', esc_attr( $id ), esc_html_x( 'Dismiss', 'Dismiss notice text', 'affiliate-for-woocommerce' ) );
				}
				?>
				</p>
			</div>
			<?php
		}
	}

}

AFWC_Admin_Notifications::get_instance();
