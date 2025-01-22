<?php
/**
 * Class to handle payout invoice related settings
 *
 * @package     affiliate-for-woocommerce/includes/admin/settings/
 * @since       7.19.0
 * @version     1.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Payout_Invoice_Admin_Settings' ) ) {

	/**
	 * Main class get payout invoice section settings
	 */
	class AFWC_Payout_Invoice_Admin_Settings {

		/**
		 * Variable to hold instance of AFWC_Payout_Invoice_Admin_Settings
		 *
		 * @var self $instance
		 */
		private static $instance = null;

		/**
		 * Section name
		 *
		 * @var string $section
		 */
		private $section = 'payout_invoice';

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Payout_Invoice_Admin_Settings Singleton object of this class
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
			// Filter to register the section settings.
			add_filter( "afwc_{$this->section}_section_admin_settings", array( $this, 'get_section_settings' ) );

			// Register custom media upload input. It will be removed once WooCommerce introduce any media upload field.
			add_action( 'woocommerce_admin_field_afwc_media_uploader', array( $this, 'render_image_upload_input' ) );

			// Enqueue the scripts.
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		/**
		 * Method to get payout invoice section settings.
		 *
		 * @return array Return the array of fields.
		 */
		public function get_section_settings() {
			$is_invoice_enabled = 'yes' === get_option( 'afwc_enable_payout_invoice', 'no' );
			return array(
				array(
					'title' => _x( 'Payout Invoice', 'Payout invoice setting section title', 'affiliate-for-woocommerce' ),
					'type'  => 'title',
					'desc'  => _x( "The addresses on the invoice are retrieved from your settings. The site address is taken from WooCommerce > Settings > General > Store Address. The affiliate's address is taken from their My Account > Addresses > Billing address. If any of the addresses are unavailable, that address will not be shown on the invoice.", 'Admin setting description for payout invoice title', 'affiliate-for-woocommerce' ),
					'id'    => "afwc_{$this->section}_admin_settings",
				),
				array(
					'name'     => _x( 'Payout invoice', 'Admin setting name for Payout invoice', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Enable this to generate an invoice for each commission payout', 'Admin setting description for Payout invoice', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_enable_payout_invoice',
					'type'     => 'checkbox',
					'autoload' => false,
					'default'  => 'no',
					'desc_tip' => _x( 'When enabled, you can print invoices from the Payouts tab. To allow affiliates to print their invoices, enable the "Show and allow affiliates to print their invoice" below.', 'Admin setting description tip for Payout invoice', 'affiliate-for-woocommerce' ),
				),
				array(
					'name'              => _x( 'Logo for payout invoice', 'Admin setting name for Logo for payout invoice', 'affiliate-for-woocommerce' ),
					'desc'              => _x( 'Upload a logo representing your shop or business. It will be shown on the invoice.', 'Admin setting description for Logo for payout invoice', 'affiliate-for-woocommerce' ),
					'id'                => 'afwc_payout_invoice_logo',
					'type'              => 'afwc_media_uploader',
					'autoload'          => false,
					'row_class'         => ! $is_invoice_enabled ? 'afwc-hide' : '',
					'custom_attributes' => array(
						'data-afwc-hide-if' => 'afwc_enable_payout_invoice',
					),
				),
				array(
					'name'              => _x( 'Show and allow affiliates to print their invoice', 'Admin setting name for Show and allow affiliates to print their invoice', 'affiliate-for-woocommerce' ),
					'desc'              => _x( 'Enable this to show and allow affiliates to print their invoice', 'Admin setting description for Show and allow affiliates to print their invoice', 'affiliate-for-woocommerce' ),
					'id'                => 'afwc_enable_payout_invoice_for_affiliate',
					'type'              => 'checkbox',
					'default'           => 'no',
					'desc_tip'          => _x( "When enabled, a new column to print invoices will be visible in the affiliate's account > Reports > Payout History.", 'Admin setting description tip for Show and allow affiliates to print their invoice', 'affiliate-for-woocommerce' ),
					'autoload'          => false,
					'row_class'         => ! $is_invoice_enabled ? 'afwc-hide' : '',
					'custom_attributes' => array(
						'data-afwc-hide-if' => 'afwc_enable_payout_invoice',
					),
				),
				array(
					'type' => 'sectionend',
					'id'   => "afwc_{$this->section}_admin_settings",
				),
			);
		}

		/**
		 * Method to enqueue the scripts for payout invoice section.
		 *
		 * @return void.
		 */
		public function enqueue_scripts() {
			wp_enqueue_media();
		}

		/**
		 * Method to rendering the image upload field.
		 *
		 * @param array $value The value.
		 *
		 * @return void.
		 */
		public function render_image_upload_input( $value = array() ) {
			if ( empty( $value ) ) {
				return;
			}

			$id         = ! empty( $value['id'] ) ? wc_clean( $value['id'] ) : '';
			$field_name = ! empty( $value['field_name'] ) ? wc_clean( $value['field_name'] ) : $id;
			if ( empty( $field_name ) ) {
				return;
			}
			$option_value       = get_option( $field_name, '' );
			$field_description  = is_callable( array( 'WC_Admin_Settings', 'get_field_description' ) ) ? WC_Admin_Settings::get_field_description( $value ) : array();
			$media_src          = ! empty( $option_value ) ? wp_get_attachment_url( $option_value ) : '';
			$upload_button_name = ! empty( $value['upload_button_text'] ) ? wc_clean( $value['upload_button_text'] ) : _x( 'Upload image', 'Button text for media upload button', 'affiliate-for-woocommerce' );
			$change_button_name = ! empty( $value['change_button_text'] ) ? wc_clean( $value['change_button_text'] ) : _x( 'Change image', 'Button text for media change button', 'affiliate-for-woocommerce' );
			?>
				<tr valign="top" class="<?php echo ! empty( $value['row_class'] ) ? esc_attr( $value['row_class'] ) : ''; ?>">
					<th scope="row" class="titledesc"> 
						<label for="<?php echo esc_attr( $id ); ?>"> <?php echo ( ! empty( $value['title'] ) ? esc_html( $value['title'] ) : '' ); ?> </label>
					</th>
					<td class="forminp">
						<div class="afwc-media-upload-section">
							<p class="afwc-media-preview <?php echo empty( $media_src ) ? 'afwc-hide' : ''; ?>">
								<img src="<?php echo esc_attr( $media_src ); ?>" width="200"/>
							</p>
							<input
								type="hidden"
								name="<?php echo esc_attr( $field_name ); ?>"
								id="<?php echo esc_attr( $id ); ?>"
								class="afwc-media-uploader-value"
								value="<?php echo esc_attr( $option_value ); ?>"
								<?php echo is_callable( array( 'AFWC_Admin_Settings', 'get_html_attributes_string' ) ) ? wp_kses_post( AFWC_Admin_Settings::get_html_attributes_string( $value ) ) : ''; ?>
							>
							<div class="afwc-action-buttons">
								<button
									type="button"
									id="afwc-select-media-btn"
									class="<?php echo ! empty( $media_src ) ? 'button' : 'afwc-media-uploader'; ?>"
									data-uploader-title="<?php echo ! empty( $value['uploader_title'] ) ? esc_attr( $value['uploader_title'] ) : ''; ?>"
									data-uploader-button-text="<?php echo ! empty( $value['uploader_button_text'] ) ? esc_attr( $value['uploader_button_text'] ) : ''; ?>"
									data-upload-button-text="<?php echo esc_attr( $upload_button_name ); ?>"
									data-change-button-text="<?php echo esc_attr( $change_button_name ); ?>"
								>
									<?php echo esc_html( ! empty( $media_src ) ? $change_button_name : $upload_button_name ); ?>
								</button>
								<button
									type="button" 
									class="afwc-media-remove button button-secondary <?php echo empty( $media_src ) ? 'afwc-hide' : ''; ?>"
								>
									<?php echo esc_html_x( 'Remove image', 'Button name for remove media', 'affiliate-for-woocommerce' ); ?>
								</button>
							</div>
						</div>
						<?php echo ! empty( $field_description['description'] ) ? wp_kses_post( $field_description['description'] ) : ''; ?>
					</td>
				</tr>
			<?php
		}
	}
}

AFWC_Payout_Invoice_Admin_Settings::get_instance();
