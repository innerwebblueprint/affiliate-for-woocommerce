<?php
/**
 * Class to handle payouts related settings
 *
 * @package     affiliate-for-woocommerce/includes/admin/settings/
 * @since       7.18.0
 * @version     1.2.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Payouts_Admin_Settings' ) ) {

	/**
	 * Main class get payouts section settings
	 */
	class AFWC_Payouts_Admin_Settings {

		/**
		 * Variable to hold instance of AFWC_Payouts_Admin_Settings
		 *
		 * @var self $instance
		 */
		private static $instance = null;

		/**
		 * Section name
		 *
		 * @var string $section
		 */
		private $section = 'payouts';

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Payouts_Admin_Settings Singleton object of this class
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			add_filter( "afwc_{$this->section}_section_admin_settings", array( $this, 'get_section_settings' ) );

			// automatic payouts.
			add_action( 'woocommerce_admin_field_afwc_ap_includes_list', array( $this, 'render_ap_include_list_input' ) );
			add_filter( 'woocommerce_admin_settings_sanitize_option_afwc_automatic_payout_includes', array( $this, 'sanitize_ap_include_list' ), 10, 2 );

			// Ajax action for automatic payouts.
			add_action( 'wp_ajax_afwc_search_ap_includes_list', array( $this, 'afwc_json_search_include_ap_list' ) );
		}

		/**
		 * Method to get payouts section settings
		 *
		 * @return array
		 */
		public function get_section_settings() {

			// Check if PayPal API is enabled.
			$paypal_api_settings = array();
			if ( is_callable( array( 'AFWC_PayPal_API', 'get_instance' ) ) ) {
				$afwc_paypal_api_instance = AFWC_PayPal_API::get_instance();
				if ( is_callable( array( $afwc_paypal_api_instance, 'get_api_setting_status' ) ) ) {
					$paypal_api_settings = $afwc_paypal_api_instance->get_api_setting_status();
				}
			}

			$afwc_payouts_admin_settings = array(
				array(
					'title' => _x( 'Payouts', 'Payouts setting section title', 'affiliate-for-woocommerce' ),
					'type'  => 'title',
					'id'    => 'afwc_payouts_admin_settings',
				),
				array(
					'name'              => _x( 'Refund period', 'setting name', 'affiliate-for-woocommerce' ),
					'desc'              => _x( 'A refund isn\'t a successful referral. Therefore, enter how many days to wait before paying commissions for successful referrals. If you don\'t have a refund period, enter 0. Each referral within the refund period will be marked to show the remaining days in the refund period. In case automatic payouts are enabled, referrals within the refund period will not be included in it.', 'setting description', 'affiliate-for-woocommerce' ),
					'id'                => 'afwc_order_refund_period_in_days',
					'type'              => 'number',
					'default'           => 30,
					'autoload'          => false,
					'desc_tip'          => false,
					'custom_attributes' => array(
						'min' => 0,
					),
					'placeholder'       => _x( 'Enter the number of days. Default is 30.', 'placeholder for refund window setting', 'affiliate-for-woocommerce' ),
				),
				array(
					'name'              => _x( 'Minimum affiliate commission for payout', 'setting name', 'affiliate-for-woocommerce' ),
					'desc'              => _x( 'An affiliate earnings must reach this minimum threshold value to qualify for commission payouts. This setting ensures that only eligible order\'s referrals are included for payouts. In case automatic payouts are enabled, order referrals below this threshold will not qualify for payouts.', 'setting description', 'affiliate-for-woocommerce' ),
					'id'                => 'afwc_minimum_commission_balance',
					'type'              => 'number',
					'default'           => 50,
					'autoload'          => false,
					'desc_tip'          => false,
					'custom_attributes' => array(
						'min' => 1,
					),
					'placeholder'       => _x( 'Enter minimum commission amount. Default is 50.', 'placeholder for payment day setting', 'affiliate-for-woocommerce' ),
				),
				array(
					'name'     => _x( 'PayPal email address', 'setting name', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Allow affiliates to enter their PayPal email address from their My Account > Affiliates > Profile for PayPal payouts', 'setting description', 'affiliate-for-woocommerce' ),
					'desc_tip' => _x( 'Disabling this will not show it to affiliates in their account.', 'setting description tip', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_allow_paypal_email',
					'type'     => 'checkbox',
					'default'  => 'no',
					'autoload' => false,
				),
				array(
					'name'              => _x( 'Payout via PayPal', 'setting name', 'affiliate-for-woocommerce' ),
					'type'              => 'checkbox',
					'default'           => 'no',
					'autoload'          => false,
					'value'             => ( ! empty( $paypal_api_settings['value'] ) ) ? $paypal_api_settings['value'] : 'no',
					'desc'              => ( ! empty( $paypal_api_settings['desc'] ) ) ? $paypal_api_settings['desc'] : '',
					'desc_tip'          => ( ! empty( $paypal_api_settings['desc_tip'] ) ) ? $paypal_api_settings['desc_tip'] : '',
					'id'                => 'afwc_paypal_payout',
					'custom_attributes' => array(
						'disabled' => 'disabled',
					),
				),
				array(
					'name'     => _x( 'Automatic payouts', 'setting name', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Enable this to automatically pay your affiliates', 'setting description', 'affiliate-for-woocommerce' ),
					'desc_tip' => _x( 'Supports PayPal and Stripe - if enabled.', 'setting description tip', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_enable_automatic_payouts',
					'type'     => 'checkbox',
					'default'  => 'no',
					'autoload' => false,
				),
				array(
					'name'              => _x( 'Automatic payouts include affiliates', 'Admin setting name for lifetime commissions excludes', 'affiliate-for-woocommerce' ),
					'desc'              => _x( 'Select upto 10 affiliates to automatically issue them commission payouts - beta launch. Only those affiliates who have added a PayPal email address to their account will qualify for automatic payouts.', 'Admin setting description for affiliates to exclude for lifetime commissions', 'affiliate-for-woocommerce' ),
					'id'                => 'afwc_automatic_payout_includes',
					'type'              => 'afwc_ap_includes_list',
					'class'             => 'afwc-automatic-payouts-includes-search wc-enhanced-select',
					'placeholder'       => _x( 'Search affiliates by email, username or name', 'Admin setting placeholder for lifetime commissions excludes', 'affiliate-for-woocommerce' ),
					'options'           => get_option( 'afwc_automatic_payout_includes', array() ),
					'row_class'         => ( 'no' === get_option( 'afwc_enable_automatic_payouts', 'no' ) ) ? 'afwc-hide' : '',
					'custom_attributes' => array(
						'data-afwc-hide-if' => 'afwc_enable_automatic_payouts',
					),
				),
				array(
					'name'              => _x( 'Maximum commission to pay an affiliate', 'setting name', 'affiliate-for-woocommerce' ),
					'desc'              => _x( 'Set the maximum commission an affiliate can receive in automatic payouts. Set it to 0 if no limit. This setting ensures automatic payouts stay within a specified limit. Referrals exceeding this limit won\'t be included in automatic payouts.', 'setting description', 'affiliate-for-woocommerce' ),
					'id'                => 'afwc_maximum_commission_balance',
					'type'              => 'number',
					'default'           => 0,
					'autoload'          => false,
					'desc_tip'          => false,
					'row_class'         => ( 'no' === get_option( 'afwc_enable_automatic_payouts', 'no' ) ) ? 'afwc-hide' : '',
					'custom_attributes' => array(
						'min'               => 0,
						'data-afwc-hide-if' => 'afwc_enable_automatic_payouts',
					),
					'placeholder'       => _x( 'Enter maximum commission amount. Default is 0.', 'placeholder for payment day setting', 'affiliate-for-woocommerce' ),
				),
				array(
					'name'              => _x( 'Commission payout day', 'setting name', 'affiliate-for-woocommerce' ),
					'desc'              => _x( 'Automatic commission payouts will be issued on this fixed day of each month you enter in the box.  Leaving it blank will set the default day to the 15th of each month. If the entered date falls between the 28th and 31st, payouts will be automatically sent on the last day of that particular month.', 'setting description', 'affiliate-for-woocommerce' ),
					'id'                => 'afwc_commission_payout_day',
					'type'              => 'number',
					'default'           => 15,
					'autoload'          => false,
					'desc_tip'          => false,
					'row_class'         => ( 'no' === get_option( 'afwc_enable_automatic_payouts', 'no' ) ) ? 'afwc-hide' : '',
					'custom_attributes' => array(
						'min'               => 1,
						'max'               => 31,
						'data-afwc-hide-if' => 'afwc_enable_automatic_payouts',
					),
					'placeholder'       => _x( 'Enter day of the month', 'placeholder for payment day setting', 'affiliate-for-woocommerce' ),
				),
				array(
					'type' => 'sectionend',
					'id'   => "afwc_{$this->section}_admin_settings",
				),
			);

			return $afwc_payouts_admin_settings;
		}

		/**
		 * Method to get the affiliates who has either PayPal or Stripe meta set - to allow in automatic payouts.
		 *
		 * @param string $term The value.
		 * @param bool   $for_search Whether the method will be used for searching or fetching the details by id.
		 *
		 * @return array The list of found affiliate users.
		 */
		public function get_affiliates_with_automatic_payout_meta_data( $term = '', $for_search = false ) {
			if ( empty( $term ) ) {
				return array();
			}

			global $affiliate_for_woocommerce;

			$values = array();

			if ( true === $for_search ) {
				$affiliate_search = array(
					'search'         => '*' . $term . '*',
					'search_columns' => array( 'ID', 'user_nicename', 'user_login', 'user_email' ),
					'number'         => 10, // We are fetching only 10 affiliates in the search - to start off.
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						// Check affiliate has either PayPal or Stripe details in their meta.
						'relation' => 'OR',
						array(
							'key'     => 'afwc_paypal_email',
							'value'   => '',
							'compare' => '!=',
						),
						array(
							'relation' => 'AND',
							array(
								'key'     => 'afwc_stripe_user_id',
								'value'   => '',
								'compare' => '!=',
							),
							array(
								'key'     => 'afwc_stripe_access_token',
								'value'   => '',
								'compare' => '!=',
							),
						),
					),
				);
			} else {
				$affiliate_search = array(
					'include' => ( ! is_array( $term ) ) ? (array) $term : $term,
				);
			}

			$values = is_callable( array( $affiliate_for_woocommerce, 'get_affiliates' ) ) ? $affiliate_for_woocommerce->get_affiliates( $affiliate_search ) : array();

			return $values;
		}

		/**
		 * Method to rendering the include list input field.
		 *
		 * @param array $value The value.
		 *
		 * @return void.
		 */
		public function render_ap_include_list_input( $value = array() ) {

			if ( empty( $value ) ) {
				return;
			}

			$id                = ! empty( $value['id'] ) ? $value['id'] : '';
			$options           = ! empty( $value['options'] ) ? $value['options'] : array();
			$field_description = is_callable( array( 'WC_Admin_Settings', 'get_field_description' ) ) ? WC_Admin_Settings::get_field_description( $value ) : array();
			?>	
				<tr valign="top" class="<?php echo ! empty( $value['row_class'] ) ? esc_attr( $value['row_class'] ) : ''; ?>">
					<th scope="row" class="titledesc"> 
						<label for="<?php echo esc_attr( $id ); ?>"> <?php echo ( ! empty( $value['title'] ) ? esc_html( $value['title'] ) : '' ); ?> </label>
					</th>
					<td class="forminp">
						<select
							name="<?php echo esc_attr( ! empty( $value['field_name'] ) ? $value['field_name'] : $id ); ?>[]"
							id="<?php echo esc_attr( $id ); ?>"
							style="<?php echo ! empty( $value['css'] ) ? esc_attr( $value['css'] ) : ''; ?>"
							class="<?php echo ! empty( $value['class'] ) ? esc_attr( $value['class'] ) : ''; ?>"
							data-placeholder="<?php echo ! empty( $value['placeholder'] ) ? esc_attr( $value['placeholder'] ) : ''; ?>"
							multiple="multiple"
							<?php echo is_callable( array( 'AFWC_Admin_Settings', 'get_html_attributes_string' ) ) ? wp_kses_post( AFWC_Admin_Settings::get_html_attributes_string( $value ) ) : ''; ?>
						>
						<?php
						foreach ( $options as $ids ) {
							$current_list = $this->get_affiliates_with_automatic_payout_meta_data( $ids );
							if ( ! empty( $current_list ) && is_array( $current_list ) ) {
								foreach ( $current_list as $id => $text ) {
									?>
										<option
											value="<?php echo esc_attr( $id ); ?>"
											selected='selected'
										><?php echo ! empty( $text ) ? esc_html( $text ) : ''; ?></option>
										<?php
								}
							}
						}
						?>
						</select> <?php echo ! empty( $field_description['description'] ) ? wp_kses_post( $field_description['description'] ) : ''; ?>
					</td>
				</tr>
			<?php
		}

		/**
		 * Method to sanitize and format the value for ltc exclude list.
		 *
		 * @param array $value The value.
		 *
		 * @return array.
		 */
		public function sanitize_ap_include_list( $value = array() ) {

			// Return empty array if the value is empty.
			if ( empty( $value ) ) {
				return array();
			}

			$list = array();
			foreach ( $value as $id ) {
				$list[] = $id;
			}

			return $list;
		}

		/**
		 * Ajax callback function to search the affiliates and affiliate tag.
		 */
		public function afwc_json_search_include_ap_list() {

			check_admin_referer( 'afwc-search-include-ap-list', 'security' );

			$term = ( ! empty( $_GET['term'] ) ) ? (string) urldecode( wp_strip_all_tags( wp_unslash( $_GET ['term'] ) ) ) : '';
			if ( empty( $term ) ) {
				wp_die();
			}

			$searched_list = $this->get_affiliates_with_automatic_payout_meta_data( $term, true );
			if ( empty( $searched_list ) || ! is_array( $searched_list ) ) {
				wp_die();
			}

			$data = array();
			foreach ( $searched_list as $affiliate_user_id => $affiliate_details ) {
				$data[ $affiliate_user_id ] = $affiliate_details;
			}

			wp_send_json( $data );
		}

	}

}

AFWC_Payouts_Admin_Settings::get_instance();
