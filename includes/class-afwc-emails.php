<?php
/**
 * Main class for Affiliate Emails functionality
 *
 * @package     affiliate-for-woocommerce/includes/
 * @since       2.3.0
 * @version     1.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Emails' ) ) {

	/**
	 * Main class for Affiliate Emails functionality
	 */
	class AFWC_Emails {

		/**
		 * Variable to hold instance of AFWC_Emails
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Emails Singleton object of this class
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
		public function __construct() {

			// Filter to register email classes from this plugin.
			add_filter( 'woocommerce_email_classes', array( $this, 'register_email_classes' ) );

			// Filter to display correct email override path.
			add_filter( 'woocommerce_template_directory', array( $this, 'get_afwc_email_template_dir_for_override_by_theme' ), 10, 2 );

			// Action to add email template override description.
			add_action( 'woocommerce_email_settings_before', array( $this, 'add_email_template_override_description' ) );

			// Filter to manage merge tags in email content.
			add_filter( 'woocommerce_email_format_string', array( $this, 'parse_email_content_for_merge_tags' ), 20, 2 );
		}

		/**
		 * Register email classes from this plugin to WooCommerce's emails class list.
		 *
		 * @param array $email_classes available email classes list.
		 * @return array $email_classes modified email classes list
		 */
		public function register_email_classes( $email_classes = array() ) {

			$afwc_email_classes = glob( AFWC_PLUGIN_DIRPATH . '/includes/emails/*.php' );

			foreach ( $afwc_email_classes as $email_class ) {
				if ( is_file( $email_class ) ) {
					include_once $email_class;

					$base_class = basename( $email_class, '.php' );

					if ( empty( $base_class ) ) {
						continue;
					}

					$class_name = ucwords(
						str_replace(
							array( 'class-', '-', 'afwc' ),
							array( '', '_', 'AFWC' ),
							$base_class
						),
						'_'
					);

					if ( ! class_exists( $class_name ) ) {
						continue;
					}

					$email_classes[ $class_name ] = new $class_name();
				}
			}

			return $email_classes;
		}

		/**
		 * Check whether an email is enabled or not based on the given action.
		 *
		 * @param string $action The email action name.
		 * @return bool Return true whether the email is enabled otherwise false.
		 */
		public static function is_afwc_mailer_enabled( $action = '' ) {
			if ( empty( $action ) ) {
				return false;
			}

			$action_without_prefix = str_replace( 'afwc_', '', $action );

			$class_name = ( ! empty( $action_without_prefix ) ) ? sprintf( 'AFWC_%1$s', ucwords( $action_without_prefix, '_' ) ) : '';

			// Return false if the class name is not found.
			if ( empty( $class_name ) ) {
				return false;
			}

			$wc_mailer = ( function_exists( 'WC' ) && is_callable( array( WC(), 'mailer' ) ) ) ? WC()->mailer() : null;

			if ( $wc_mailer instanceof WC_Emails && ! empty( $wc_mailer->emails[ $class_name ] ) && is_callable( array( $wc_mailer->emails[ $class_name ], 'is_enabled' ) ) && $wc_mailer->emails[ $class_name ]->is_enabled() ) {
				return true;
			}

			return false;
		}

		/**
		 * Method to set template directory for Affiliate For WooCommerce's templates for override by theme
		 *
		 * @see `woocommerce_template_directory` filter usage in https://woocommerce.github.io/code-reference/files/woocommerce-includes-emails-class-wc-email.html
		 *
		 * @param string $template_directory Template directory.
		 * @param string $template           Template name.
		 *
		 * @return string $template_directory Template directory.
		 */
		public function get_afwc_email_template_dir_for_override_by_theme( $template_directory = 'woocommerce', $template = '' ) {
			if ( empty( $template ) ) {
				return $template_directory;
			}

			if ( file_exists( AFWC_PLUGIN_DIRPATH . '/templates/' . $template ) ) {
				// Return 'woocommerce/affiliate-for-woocommerce' if email exist in plugin's `templates` folder.
				return 'woocommerce/' . basename( AFWC_PLUGIN_DIRPATH );
			}

			if ( file_exists( AFWC_PLUGIN_DIRPATH . '/templates/emails/' . $template ) ) {
				// Return 'woocommerce/affiliate-for-woocommerce/emails' if email exist in plugin's `template/emails` folder.
				return 'woocommerce/' . basename( AFWC_PLUGIN_DIRPATH ) . '/emails';
			}

			return $template_directory;
		}

		/**
		 * Method to add extra description explain to email template override
		 *
		 * @param  WC_Email $email Email object.
		 * @return void
		 */
		public function add_email_template_override_description( $email = null ) {
			if ( empty( $email ) || ! $email instanceof WC_Email || empty( $email->template_base ) ) {
				return;
			}

			if ( AFWC_PLUGIN_DIRPATH . '/templates/' === $email->template_base || AFWC_PLUGIN_DIRPATH . '/templates/emails/' === $email->template_base ) {
				?>
				<p><strong>
					<?php
					printf(
						/* translators: %s: Link of template override documentation */
						esc_html_x( 'To fully customize this email, use the template override feature. This allows you to tailor the email content to your needs. Learn more on %s.', 'extra description to explain email template override', 'affiliate-for-woocommerce' ),
						'<a target="_blank" href="' . esc_url( AFWC_DOC_DOMAIN . 'how-to-override-templates/' ) . '">' . esc_html_x( 'How to override templates', 'template override documentation link text', 'affiliate-for-woocommerce' ) . '</a>'
					);
					?>
				</strong></p>
				<?php
			}
		}

		/**
		 * Method to add extra description explain to email template override
		 *
		 * @param string   $email_content Email content.
		 * @param WC_Email $email Email object.
		 *
		 * @return string
		 */
		public function parse_email_content_for_merge_tags( $email_content = '', $email = null ) {
			if ( empty( $email_content ) || ! $email instanceof WC_Email || empty( $email->id ) ) {
				return $email_content;
			}

			if ( is_callable( array( 'AFWC_Merge_Tags', 'get_instance' ) ) ) {
				$affiliate_id = ! empty( $email->email_args ) && ! empty( $email->email_args['affiliate_id'] ) && 'yes' === afwc_is_user_affiliate( intval( $email->email_args['affiliate_id'] ) )
					? intval( $email->email_args['affiliate_id'] )
					: 0;

				$afwc_merge_tags = AFWC_Merge_Tags::get_instance();
				$email_content   = is_callable( array( $afwc_merge_tags, 'parse_content' ) ) ? $afwc_merge_tags->parse_content( $email_content, array( 'affiliate' => $affiliate_id ) ) : $email_content;
			}

			return $email_content;
		}

	}

}

return new AFWC_Emails();
