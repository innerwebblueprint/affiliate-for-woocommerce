<?php
/**
 * Main class for Reminder to store admin for upcoming automatic payout
 *
 * @package     affiliate-for-woocommerce/includes/emails/
 * @since       8.0.0
 * @version     1.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Email_Automatic_Payouts_Reminder' ) ) {

	/**
	 * Email class for automatic payout reminder email.
	 *
	 * @extends \WC_Email
	 */
	class AFWC_Email_Automatic_Payouts_Reminder extends WC_Email {

		/**
		 * Email parameters
		 *
		 * @var array $email_args
		 */
		public $email_args = array();

		/**
		 * Set email defaults
		 */
		public function __construct() {

			// Set ID, this simply needs to be a unique name.
			$this->id = 'afwc_automatic_payouts_reminder';

			// This is the title in WooCommerce email settings.
			$this->title = 'Affiliate Manager - Automatic Payouts Reminder';

			// This is the description in WooCommerce email settings.
			$this->description = _x( 'This email will be sent to the affiliate manager (or store admin) when automatic payouts are due. It is automatically enabled or disabled based on the Automatic Payout setting. Do not turn off this email manually.', 'Description for Automatic Payouts Reminder email', 'affiliate-for-woocommerce' );

			// These are the default heading and subject lines that can be overridden using the settings.
			$this->subject = '{site_title} Affiliate Commission will be processed';
			$this->heading = 'Outstanding affiliate commission will be automatically paid';

			// Email template location.
			$this->template_html  = 'afwc-automatic-payouts-reminder.php';
			$this->template_plain = 'plain/afwc-automatic-payouts-reminder.php';

			// Use our plugin templates directory as the template base.
			$this->template_base = AFWC_PLUGIN_DIRPATH . '/templates/emails/';

			$this->placeholders = array();

			// Trigger this email.
			add_action( 'afwc_email_automatic_payouts_reminder', array( $this, 'trigger' ), 10, 1 );

			// Call parent constructor to load any other defaults not explicity defined here.
			parent::__construct();

			// Send the email to affiliate manager if available, else to store admin.
			$this->recipient = get_option( 'afwc_contact_admin_email_address', '' );
			if ( ! $this->recipient ) {
				$this->recipient = get_option( 'admin_email' );
			}

			add_filter( 'woocommerce_email_enabled_afwc_automatic_payouts_reminder', array( $this, 'enable_email' ), 11, 3 );

		}

		/**
		 * Function to set email forcefully enabled.
		 *
		 * @param string $enabled Whether email is enabled or not.
		 *
		 * @return string whether email is enabled or not.
		 */
		public function enable_email( $enabled = '' ) {
			$enabled = ( is_callable( 'AFWC_Automatic_Payouts_Handler', 'is_enabled' ) && AFWC_Automatic_Payouts_Handler::is_enabled() && 'yes' === AFWC_Automatic_Payouts_Handler::is_enabled() ) ? 'yes' : 'no';

			return $enabled;
		}

		/**
		 * Determine if the email should actually be sent and setup email merge variables.
		 *
		 * @param array $args Email arguments.
		 */
		public function trigger( $args = array() ) {

			if ( empty( $args ) ) {
				return;
			}

			$this->email_args = array();
			$this->email_args = wp_parse_args( $args, $this->email_args );

			$admin                          = get_user_by( 'email', $this->recipient );
			$this->email_args['admin_name'] = ! empty( $admin->user_login ) ? $admin->user_login : _x( 'there', 'Greeting for admin', 'affiliate-for-woocommerce' );
			$this->email_args['pending_payouts_dashboard_url'] = admin_url( 'admin.php?page=affiliate-for-woocommerce#!/pending-payouts' );
			// Get timestamp to process automatic payout.
			$this->email_args['timestamp'] = ! empty( $this->email_args['automatic_payout_timestamp'] ) ? $this->email_args['automatic_payout_timestamp'] : '';

			// Affiliate details.
			$affiliate_id = ! empty( $this->email_args['affiliate_id'] ) ? intval( $this->email_args['affiliate_id'] ) : 0;
			if ( empty( $affiliate_id ) ) {
				return;
			}
			// Set the affiliate user data to get their name.
			$affiliate_user = get_userdata( $affiliate_id );
			if ( $affiliate_user instanceof WP_User ) {
				$this->email_args['affiliate_name'] = ! empty( $affiliate_user->first_name ) ? $affiliate_user->first_name : _x( 'there', 'Greeting for the affiliate', 'affiliate-for-woocommerce' );
			}

			// Set the locale to the store locale for customer emails to make sure emails are in the store language.
			$this->setup_locale();

			// For any email placeholders.
			$this->set_placeholders();

			$email_content = $this->get_content();
			// Replace placeholders with values in the email content.
			$email_content = ( is_callable( array( $this, 'format_string' ) ) ) ? $this->format_string( $email_content ) : $email_content;

			// Send email.
			if ( ! empty( $email_content ) && $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $email_content, $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();

		}

		/**
		 * Function to set placeholder variables used in email.
		 */
		public function set_placeholders() {
			// For any email placeholders.
			$this->placeholders = array(
				'{site_title}' => $this->get_blogname(),
			);
		}

		/**
		 * Function to load email html content.
		 *
		 * @return string Email content html.
		 */
		public function get_content_html() {
			global $affiliate_for_woocommerce;

			$email_arguments = $this->get_template_args();

			if ( ! empty( $email_arguments ) ) {
				ob_start();

				wc_get_template(
					$this->template_html,
					$email_arguments,
					is_callable( array( $affiliate_for_woocommerce, 'get_template_base_dir' ) ) ? $affiliate_for_woocommerce->get_template_base_dir( $this->template_html ) : '',
					$this->template_base
				);

				return ob_get_clean();
			}

			return '';
		}

		/**
		 * Function to load email plain content.
		 *
		 * @return string Email plain content.
		 */
		public function get_content_plain() {
			global $affiliate_for_woocommerce;

			$email_arguments = $this->get_template_args();

			if ( ! empty( $email_arguments ) ) {
				ob_start();

				wc_get_template(
					$this->template_plain,
					$email_arguments,
					is_callable( array( $affiliate_for_woocommerce, 'get_template_base_dir' ) ) ? $affiliate_for_woocommerce->get_template_base_dir( $this->template_plain ) : '',
					$this->template_base
				);

				return ob_get_clean();
			}

			return '';
		}

		/**
		 * Function to return the required email arguments for this email template.
		 *
		 * @return array Email arguments.
		 */
		public function get_template_args() {
			return array(
				'email'                         => $this,
				'email_heading'                 => is_callable( array( $this, 'get_heading' ) ) ? $this->get_heading() : '',
				'admin_name'                    => $this->email_args['admin_name'],
				'pending_payouts_dashboard_url' => $this->email_args['pending_payouts_dashboard_url'],
				'timestamp'                     => $this->email_args['timestamp'],
				'affiliate_name'                => $this->email_args['affiliate_name'],
				'additional_content'            => is_callable( array( $this, 'get_additional_content' ) ) ? $this->get_additional_content() : '',
			);
		}

		/**
		 * Initialize Settings Form Fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				// No need to pass enabled here as email be always enabled.
				'subject'            => array(
					'title'       => _x( 'Subject', 'Title for the subject field in the automatic payouts reminder email', 'affiliate-for-woocommerce' ),
					'type'        => 'text',
					/* translators: %s Email subject. */
					'description' => sprintf( _x( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'Description for email subject for automatic payouts reminder email', 'affiliate-for-woocommerce' ), $this->subject ),
					'placeholder' => $this->subject,
					'default'     => '',
				),
				'heading'            => array(
					'title'       => _x( 'Email Heading', 'Title for email heading field in the automatic payouts reminder email', 'affiliate-for-woocommerce' ),
					'type'        => 'text',
					/* translators: %s Email heading. */
					'description' => sprintf( _x( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'Description for email heading in the automatic payouts reminder email', 'affiliate-for-woocommerce' ), $this->heading ),
					'placeholder' => $this->heading,
					'default'     => '',
				),
				'additional_content' => array(
					'title'       => _x( 'Additional content', 'Title for additional content field in the automatic payouts reminder email', 'affiliate-for-woocommerce' ),
					'description' => _x( 'Text to appear below the main email content.', 'Description for additional content in the automatic payouts reminder email', 'affiliate-for-woocommerce' ),
					'css'         => 'width:400px; height: 75px;',
					'placeholder' => _x( 'N/A', 'not applicable text', 'affiliate-for-woocommerce' ),
					'type'        => 'textarea',
					'default'     => is_callable( array( $this, 'get_additional_content' ) ) ? $this->get_additional_content() : '', // WC 3.7 introduced an additional content field for all emails.
				),
				'email_type'         => array(
					'title'       => _x( 'Email type', 'Title for email type field in the automatic payouts reminder email', 'affiliate-for-woocommerce' ),
					'type'        => 'select',
					'description' => _x( 'Choose which format of email to send.', 'Description to select email type in the automatic payouts reminder email', 'affiliate-for-woocommerce' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
				),
			);
		}

	}

}
