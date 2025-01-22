<?php
/**
 * Main class for Affiliates Registration
 *
 * @package     affiliate-for-woocommerce/includes/frontend/
 * @since       1.8.0
 * @version     1.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Registration_Form' ) ) {

	/**
	 * Main class for Affiliates Registration
	 */
	class AFWC_Registration_Form {

		/**
		 * Variable to hold instance of AFWC_Registration_Form
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Form fields
		 *
		 * @var $form_fields
		 */
		public $form_fields;

		/**
		 * Constructor
		 */
		private function __construct() {
			add_shortcode( 'afwc_registration_form', array( $this, 'render_registration_form' ) );
			add_action( 'wp_ajax_afwc_register_user', array( $this, 'register_user' ) );
			add_action( 'wp_ajax_nopriv_afwc_register_user', array( $this, 'register_user' ) );
		}

		/**
		 * Get single instance of AFWC_Registration_Form
		 *
		 * @return AFWC_Registration_Form Singleton object of AFWC_Registration_Form
		 */
		public static function get_instance() {
			// Check if instance is already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Render AFWC_Registration_Form
		 *
		 * @return string|void $afwc_reg_form_html form HTML | nothing
		 */
		public function render_registration_form() {

			// Return if block editor/Gutenberg to prevent printing response messages on the admin side.
			$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : '';
			if ( is_admin() && ! empty( $current_screen ) && $current_screen->is_block_editor() ) {
				return;
			}

			ob_start();

			$plugin_data = Affiliate_For_WooCommerce::get_plugin_data();
			wp_enqueue_style( 'afwc-reg-form-style', AFWC_PLUGIN_URL . '/assets/css/afwc-reg-form.css', array(), $plugin_data['Version'] );
			wp_enqueue_script( 'afwc-reg-form-js', AFWC_PLUGIN_URL . '/assets/js/afwc-reg-form.js', array( 'jquery', 'wp-i18n' ), $plugin_data['Version'], true );
			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'afwc-reg-form-js', 'affiliate-for-woocommerce', AFWC_PLUGIN_DIR_PATH . 'languages' );
			}

			wp_localize_script( 'afwc-reg-form-js', 'afwcRegistrationFormParams', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );

			$afwc_reg_form_html = '';
			$afwc_user_values   = array();
			$affiliate_status   = '';

			$user = wp_get_current_user();
			if ( is_object( $user ) && ! empty( $user->ID ) ) {
				$afwc_user_values['afwc_reg_email'] = ! empty( $user->user_email ) ? $user->user_email : '';
				$affiliate_status                   = afwc_is_user_affiliate( $user );
			}

			if ( ! empty( $affiliate_status ) && in_array( $affiliate_status, array( 'yes', 'pending', 'no' ), true ) ) {
				$afwc_registration = AFWC_Registration_Submissions::get_instance();
				// Message for registered, pending and rejected affiliates.
				if ( is_callable( array( $afwc_registration, 'get_message' ) ) ) {
					$afwc_reg_form_html = '<div class="afwc-reg-form-msg">' . wp_kses_post( $afwc_registration->get_message( $affiliate_status ) ) . '</div>';
				}
			} else {
				// Registration form fields filter.
				$this->form_fields = get_option( 'afwc_form_fields', true );

				if ( empty( $this->form_fields ) || ! is_array( $this->form_fields ) ) {
					return;
				}

				// fill up values.
				foreach ( $this->form_fields as $key => $field ) {
					if ( ! empty( $afwc_user_values[ $key ] ) ) {
						$this->form_fields[ $key ]['value'] = $afwc_user_values[ $key ];
					}
				}

				$afwc_reg_form_html = '<div class="afwc_reg_form_wrapper"><form action="#" id="afwc_registration_form">';
				// render fields.
				foreach ( $this->form_fields as $id => $field ) {
					$afwc_reg_form_html .= $this->field_callback( $id, $field );
				}

				// nonce for security.
				$nonce               = wp_create_nonce( 'afwc-register-affiliate' );
				$afwc_reg_form_html .= '<input type="hidden" name="afwc_registration" id="afwc_registration" value="' . $nonce . '"/>';
				// honyepot field.
				$hp_style            = 'position:absolute;top:-99999px;' . ( is_rtl() ? 'right' : 'left' ) . ':-99999px;z-index:-99;';
				$afwc_reg_form_html .= '<label style="' . $hp_style . '"><input type="text" name="afwc_hp_email"  tabindex="-1" autocomplete="-1" value=""/></label>';
				// loader.
				$loader_image = WC()->plugin_url() . '/assets/images/wpspin-2x.gif';
				// submit button.
				$afwc_reg_form_html .= '<div class="afwc_reg_field_wrapper"><input type="submit" name="submit" class="afwc_registration_form_submit" id="afwc_registration_form_submit" value="' . __( 'Submit', 'affiliate-for-woocommerce' ) . '"/><div class="afwc_reg_loader"><img src="' . esc_url( $loader_image ) . '" /></div></div>';
				// message.
				$afwc_reg_form_html .= '<div class="afwc_reg_message"></div>';
				$afwc_reg_form_html .= '</form></div>';
			}

			ob_get_clean();
			return $afwc_reg_form_html;
		}

		/**
		 * Function to render field
		 *
		 * @param int   $id Form ID.
		 * @param array $field Form field.
		 * @return string $field_html
		 */
		public function field_callback( $id, $field ) {
			$field_html = '';
			$required   = ! empty( $field['required'] ) ? $field['required'] : '';
			$class      = ! empty( $field['class'] ) ? $field['class'] : '';
			$show       = ! empty( $field['show'] ) ? $field['show'] : '';
			$readonly   = '';
			$value      = '';
			$user       = wp_get_current_user();
			if ( $user instanceof WP_User && ! empty( $user->ID ) ) {
				$affiliate_registration = AFWC_Registration_Submissions::get_instance();

				$field_key = str_replace( 'reg_', '', $id ); // TODO: code can be removed after DB migration to update the field id.

				$readonly = ! empty( $affiliate_registration->readonly_fields ) && is_array( $affiliate_registration->readonly_fields ) && in_array( $field_key, $affiliate_registration->readonly_fields, true ) ? 'readonly' : '';
				$value    = ! empty( $field['value'] ) ? $field['value'] : '';
				if ( ! empty( $affiliate_registration->hide_fields ) && is_array( $affiliate_registration->hide_fields ) && in_array( $field_key, $affiliate_registration->hide_fields, true ) ) {
					$class        .= ' afwc_hide_form_field';
					$required      = '';
					$field['type'] = 'hidden';
				}
			}

			$class .= ( ! $show && empty( $required ) && ! strpos( $class, 'afwc_hide_form_field' ) ) ? ' afwc_hide_form_field' : '';
			switch ( $field['type'] ) {
				case 'text':
				case 'email':
				case 'password':
				case 'tel':
				case 'checkbox':
				case 'hidden':
					$field_html = sprintf( '<input type="%1$s" id="%2$s" name="%2$s" %3$s class="afwc_reg_form_field" %4$s value="%5$s"/>', $field['type'], $id, $required, $readonly, $value );
					break;
				case 'textarea':
					$field_html = sprintf( '<textarea name="%1$s" id="%1$s" %2$s size="100" rows="5" cols="58" class="afwc_reg_form_field"></textarea>', $id, $required );
					break;
				default:
					$field_html = '';
					break;
			}
			if ( 'checkbox' === $field['type'] ) {
				$field_html = '<div class="afwc_reg_field_wrapper ' . $id . ' ' . $class . '"><label for="' . $id . '" class="afwc_' . $field['required'] . '">' . $field_html . wp_kses_post( $field['label'] ) . '</label></div>';
			} else {
				$field_html = '<div class="afwc_reg_field_wrapper ' . $id . ' ' . $class . '"><label for="' . $id . '" class="afwc_' . $field['required'] . '">' . $field['label'] . '</label>' . $field_html . '</div>';
			}
			return $field_html;
		}

		/**
		 * Function to register affiliate user.
		 */
		public function register_user() {

			check_ajax_referer( 'afwc-register-affiliate', 'security' );

			$response = array();

			$params = array_map(
				function ( $request_param ) {
					return wc_clean( wp_unslash( $request_param ) );
				},
				$_REQUEST
			);

			// Honeypot validation.
			$hp_key = 'afwc_hp_email';
			if ( ! isset( $params[ $hp_key ] ) || ! empty( $params[ $hp_key ] ) ) {
				wp_send_json(
					array(
						'status'  => 'success',
						'message' => _x( 'You are successfully registered.', 'affiliate registration message', 'affiliate-for-woocommerce' ),
					)
				);
			}

			$saving_fields = array( 'afwc_reg_email', 'afwc_reg_first_name', 'afwc_reg_last_name', 'afwc_reg_contact', 'afwc_reg_website', 'afwc_reg_password', 'afwc_reg_desc' );

			$additional_fields_title = array(
				'afwc_reg_contact' => esc_html_x( 'Way To Contact', 'label for registration contact field', 'affiliate-for-woocommerce' ),
				'afwc_reg_desc'    => esc_html_x( 'About Affiliate', 'label for registration description field', 'affiliate-for-woocommerce' ),
			);
			// Normalize form data.
			$fields = array();
			foreach ( $params as $id => $field ) {

				if ( ! in_array( $id, $saving_fields, true ) ) {
					continue;
				}

				$fields[] = array(
					'key'   => str_replace( 'reg_', '', $id ),
					'value' => $field,
					'label' => ! empty( $additional_fields_title[ $id ] ) ? $additional_fields_title[ $id ] : '',
				);
			}

			$affiliate_registration = AFWC_Registration_Submissions::get_instance();
			$response               = is_callable( array( $affiliate_registration, 'register_user' ) ) ? $affiliate_registration->register_user( $fields ) : array();

			wp_send_json(
				array(
					'status'         => ! empty( $response['status'] ) ? $response['status'] : 'error',
					'invalidFieldId' => ! empty( $response['invalid_field_id'] ) ? $response['invalid_field_id'] : '',
					'message'        => ! empty( $response['message'] ) ? $response['message'] : _x( 'Something went wrong.', 'affiliate registration message', 'affiliate-for-woocommerce' ),
				)
			);
		}

	}

}

AFWC_Registration_Form::get_instance();
