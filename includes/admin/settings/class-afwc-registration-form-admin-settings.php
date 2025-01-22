<?php
/**
 * Class to handle registration form settings
 *
 * @package     affiliate-for-woocommerce/includes/admin/settings/
 * @since       7.18.0
 * @version     1.0.6
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Registration_Form_Admin_Settings' ) ) {

	/**
	 * Main class for registration form settings
	 */
	class AFWC_Registration_Form_Admin_Settings {

		/**
		 * Variable to hold instance of AFWC_Registration_Form_Admin_Settings
		 *
		 * @var self $instance
		 */
		private static $instance = null;

		/**
		 * Section name
		 *
		 * @var string $section
		 */
		private $section = 'registration-form';

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Registration_Form_Admin_Settings Singleton object of this class
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
			add_action( 'woocommerce_admin_field_afwc_affiliate_registration_form', array( $this, 'render_afwc_affiliate_registration_form' ) );
			add_filter( 'woocommerce_admin_settings_sanitize_option_afwc_form_fields', array( $this, 'sanitize_afwc_reg_form_settings' ), 10, 3 );
		}

		/**
		 * Method to get registration form settings
		 *
		 * @return array
		 */
		public function get_section_settings() {
			$affiliate_form_desc              = '';
			$affiliate_registration_page_link = ! empty( get_permalink( get_page_by_path( 'afwc_registration_form' ) ) ) ? get_permalink( get_page_by_path( 'afwc_registration_form' ) ) : get_permalink( get_page_by_path( 'affiliates' ) );
			if ( ! empty( $affiliate_registration_page_link ) ) {
				$affiliate_form_desc = sprintf(
					/* translators: Link to the affiliate registration form page */
					esc_html_x( '%s | ', 'affiliate registration form link', 'affiliate-for-woocommerce' ),
					'<a target="_blank" href="' . esc_url( $affiliate_registration_page_link ) . '">' . esc_html_x( 'Review and publish form', 'registration form link text', 'affiliate-for-woocommerce' ) . '</a>'
				);
			}
			$affiliate_form_desc .= sprintf(
				/* translators: shortcode for affiliate registration form */
				esc_html_x( 'Use %s shortcode on any page', 'registration form shortcode usage text', 'affiliate-for-woocommerce' ),
				'<code>[afwc_registration_form]</code>'
			);
			$affiliate_form_desc .= sprintf(
				/* translators: Link to registration form documentation page */
				esc_html_x( ' | %s', 'registration form documentation link', 'affiliate-for-woocommerce' ),
				'<a target="_blank" href="' . esc_url( AFWC_DOC_DOMAIN . 'how-to-create-affiliate-registration-form-to-let-users-sign-up-for-your-affiliate-program/' ) . '">' . esc_html_x( 'All about affiliate registration form', 'registration form documentation link text', 'affiliate-for-woocommerce' ) . '</a>'
			);

			$afwc_registration_form_admin_settings = array(
				array(
					'title' => _x( 'Registration Form', 'Registration Form setting section title', 'affiliate-for-woocommerce' ),
					'desc'  => $affiliate_form_desc,
					'type'  => 'title',
					'id'    => 'afwc_registration_form_admin_settings',
				),
				array(
					'name' => _x( 'Affiliate registration form', 'Affiliate registration form', 'affiliate-for-woocommerce' ),
					'id'   => 'afwc_form_fields',
					'type' => 'afwc_affiliate_registration_form',
				),
				array(
					'type' => 'sectionend',
					'id'   => "afwc_{$this->section}_admin_settings",
				),
				array(
					'title' => _x( 'Add custom fields to the registration form', 'title of integration section at registration form setting', 'affiliate-for-woocommerce' ),
					'desc'  => '<p>' . esc_html_x( 'To customize the registration form and add custom fields, you can use any of the below available integrations.', 'available integration text', 'affiliate-for-woocommerce' ) . '</p>'
						. '<ol>'
						. sprintf(
							/* translators: 1: Elementer integration doc url 2: Elementer integration doc url title */
							'<li><a href="%1$s" target="_blank"><u>%2$s</u></a></li>',
							esc_url( AFWC_DOC_DOMAIN . 'how-to-create-affiliate-registration-forms-with-elementor-form-builder/' ),
							esc_html_x( 'How to create affiliate registration forms with Elementor form builder', 'Elementor documentation title', 'affiliate-for-woocommerce' )
						)
						. sprintf(
							/* translators: 1: Contact Form 7 integration doc url 2: Contact Form 7 integration doc url title */
							'<li><a href="%1$s" target="_blank"><u>%2$s</u></a> (%3$s <a href="%4$s" class="thickbox" title="More about the plugin">%5$s</a>)</li>',
							esc_url( AFWC_DOC_DOMAIN . 'how-to-create-affiliate-registration-forms-with-contact-form-7/' ),
							esc_html_x( 'How to create affiliate registration forms with Contact Form 7', 'Contact Form 7 documentation title', 'affiliate-for-woocommerce' ),
							esc_html_x( 'Requires free WordPress plugin: ', 'required plugin mention', 'affiliate-for-woocommerce' ),
							esc_url( admin_url() . 'plugin-install.php?tab=plugin-information&plugin=affiliate-contact-form-7-integration-for-woocommerce&TB_iframe=true&width=1150&height=800' ),
							esc_html_x( 'Affiliate Contact Form 7 Integration For WooCommerce', 'plugin name', 'affiliate-for-woocommerce' )
						)
						. '</ol>',
					'type'  => 'title',
				),
				array(
					'type' => 'sectionend',
				),
			);

			return $afwc_registration_form_admin_settings;
		}

		/**
		 * Method to rendering Registration Form setting.
		 *
		 * @param array $value The value.
		 *
		 * @return void
		 */
		public function render_afwc_affiliate_registration_form( $value = array() ) {
			if ( empty( $value ) || ! is_array( $value ) ) {
				return;
			}

			global $affiliate_for_woocommerce;
			$afwc_reg_form_settings_initial_values = afwc_reg_form_settings_initial_values();
			if ( empty( $afwc_reg_form_settings_initial_values ) || ! is_array( $afwc_reg_form_settings_initial_values ) ) {
				return;
			}

			$afwc_form_fields = get_option( 'afwc_form_fields', true );
			?>
			<tr valign="top">
				<td class="afwc_affiliate_registration_form_wrapper" colspan="2">
					<table class="afwc_registration_form_table widefat" cellspacing="0" aria-describedby="">
						<thead>
							<tr>
								<th class="name">
									<div class="afwc-label-tooltip-container">
										<span><?php echo esc_html_x( 'Field', 'registration form setting table column title', 'affiliate-for-woocommerce' ); ?></span>
										<?php echo wc_help_tip( esc_html_x( 'Name of the field in the registration form.', 'heading tool-tip info at registration form setting', 'affiliate-for-woocommerce' ) ); // phpcs:ignore ?>
									</div>
								</th>
								<th class="value">
									<div class="afwc-label-tooltip-container">
										<span><?php echo esc_html_x( 'Label', 'registration form setting table column title', 'affiliate-for-woocommerce' ); ?></span>
										<?php echo wc_help_tip( esc_html_x( 'Label for the field in the registration form.', 'heading tool-tip info at registration form setting', 'affiliate-for-woocommerce' ) ); // phpcs:ignore ?>
									</div>
								</th>
								<th class="status"><?php echo esc_html_x( 'Enabled', 'registration form setting table column title', 'affiliate-for-woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $afwc_reg_form_settings_initial_values as $key => $field_data ) {
								?>
								<tr>
									<td class="name"><?php echo esc_html( ! empty( $field_data['field'] ) ? $field_data['field'] : '' ); ?></td>
									<td class="value">
										<?php if ( 'afwc_reg_terms' === $key ) { ?>
											<textarea name="afwc_form_fields[<?php echo esc_attr( $key ); ?>][label]"><?php echo esc_html( ! empty( $afwc_form_fields[ $key ]['label'] ) ? $afwc_form_fields[ $key ]['label'] : '' ); ?></textarea>
										<?php } else { ?>
											<input type="text"
												name="afwc_form_fields[<?php echo esc_attr( $key ); ?>][label]"
												value="<?php echo esc_attr( ! empty( $afwc_form_fields[ $key ]['label'] ) ? $afwc_form_fields[ $key ]['label'] : '' ); ?>" />
										<?php } ?>
									</td>
									<td class="status">
										<?php
										if ( ! empty( $afwc_form_fields[ $key ] ) && 'required' !== $afwc_form_fields[ $key ]['required'] ) {
											$show  = ! empty( $afwc_form_fields[ $key ]['show'] ) ? $afwc_form_fields[ $key ]['show'] : '';
											$class = empty( $show ) ? 'woocommerce-input-toggle--disabled' : 'woocommerce-input-toggle--enabled';
											?>
											<span class="woocommerce-input-toggle <?php echo esc_attr( $class ); ?>">
												<input type="checkbox" value="1" name="afwc_form_fields[<?php echo esc_attr( $key ); ?>][show]" <?php checked( $show, 1 ); ?> />
											</span>
											<?php
										} else {
											?>
											<div class="afwc-status-wrapper">
												<span class="status-enabled"><input type="radio" value="1" name="afwc_form_fields[<?php echo esc_attr( $key ); ?>][show]" checked="checked" tabindex="-1"></span>
												<?php
												$status_html                      = '';
												$status_html                     .= '<span class="afwc-status-info tips" data-tip="';
												$status_html                     .= esc_attr( '<strong>' . _x( "This field is mandatory. You can't disable it.", 'registration form field is mandatory notice tooltip content', 'affiliate-for-woocommerce' ) . '</strong>' );
												$status_html                     .= '"></span>';
												$allowed_html                     = wp_kses_allowed_html( 'post' );
												$allowed_html['span']['data-tip'] = true;
												echo wp_kses( $status_html, $allowed_html );
												?>
											</div>
											<?php
										}
										?>
									</td>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
				</td>
			</tr>
			<?php
		}

		/**
		 * Method to sanitize and format the value for registration form setting.
		 *
		 * In this method, we used $raw_value to get data rather than $value because $value will not have HTML data.
		 * However, we need HTML data as users could enter HTML into the registration form input in earlier versions.
		 * So this change will make it backward compatible.
		 *
		 * @param array $value Value with sanitization.
		 * @param array $option Option data.
		 * @param array $raw_value Raw value without any sanitization.
		 *
		 * @return array Sanitized data but may have valid HTML.
		 */
		public function sanitize_afwc_reg_form_settings( $value = array(), $option = array(), $raw_value = array() ) {
			if ( ! is_array( $value ) || ! is_array( $raw_value ) ) {
				return $value;
			}

			global $affiliate_for_woocommerce;
			$afwc_reg_form_settings = afwc_reg_form_settings_initial_values();
			if ( empty( $afwc_reg_form_settings ) || ! is_array( $afwc_reg_form_settings ) ) {
				return $value;
			}

			foreach ( $raw_value as $key => $field_data ) {
				$afwc_reg_form_settings[ $key ]['label'] = wp_kses_post( trim( $field_data['label'] ) );
				$afwc_reg_form_settings[ $key ]['show']  = ! empty( $field_data['show'] ) ? $field_data['show'] : '';
			}
			return wp_unslash( $afwc_reg_form_settings );
		}

	}

}

AFWC_Registration_Form_Admin_Settings::get_instance();
