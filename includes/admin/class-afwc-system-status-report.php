<?php
/**
 * Main class for plugin's system status report.
 *
 * @package    affiliate-for-woocommerce/includes/admin
 * @since      8.10.0
 * @version    1.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_System_Status_Report' ) ) {

	/**
	 * Class to handle plugin's system status report.
	 */
	class AFWC_System_Status_Report {

		/**
		 * Singleton instance of AFWC_System_Status_Report.
		 *
		 * @var AFWC_System_Status_Report|null
		 */
		private static $instance = null;

		/**
		 * Get the singleton instance of this class
		 *
		 * @return AFWC_System_Status_Report Singleton instance of this class
		 */
		public static function get_instance() {
			// Check if instance already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			add_action( 'woocommerce_system_status_report', array( $this, 'add_system_status_section' ) );
		}

		/**
		 * Method to add system status section.
		 *
		 * @return void
		 */
		public function add_system_status_section() {
			?>
			<table class="wc_status_table widefat afwc-status-table" cellspacing="0">
				<thead>
					<tr>
						<th colspan="3" data-export-label="Affiliate For WooCommerce">
							<h2>
								<?php echo esc_html_x( 'Affiliate For WooCommerce', 'section title at system status report', 'affiliate-for-woocommerce' ); ?>
								<?php echo wp_kses_post( wc_help_tip( esc_html_x( 'This section shows any information about Affiliate For WooCommerce.', 'section description tooltip at system status report', 'affiliate-for-woocommerce' ) ) ); ?>
							</h2>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php $this->render_template_override_row(); ?>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Method to render template override row.
		 *
		 * @return void
		 */
		public function render_template_override_row() {
			$template_overrides_info = $this->get_template_overrides_info();

			if ( ! empty( $template_overrides_info['overrides'] ) && is_array( $template_overrides_info['overrides'] ) ) {
				?>
				<tr>
					<td data-export-label="Template overrides">
						<?php echo esc_html_x( 'Template overrides', 'title for template override list at system status report', 'affiliate-for-woocommerce' ); ?>
					</td>
					<td class="help">&nbsp;</td>
					<td>
						<?php
						$total_overrides = count( $template_overrides_info['overrides'] );
						for ( $i = 0; $i < $total_overrides; $i++ ) {
							$override = $template_overrides_info['overrides'][ $i ];
							if ( $override['core_version'] && ( empty( $override['version'] ) || version_compare( $override['version'], $override['core_version'], '<' ) ) ) {
								$current_version = $override['version'] ? $override['version'] : '-';
								printf(
									/* translators: %1$s: Template name, %2$s: Template version, %3$s: Core version. */
									esc_html_x( '%1$s version %2$s is out of date. The core version is %3$s', 'notice text to display outdated template versions with latest version', 'affiliate-for-woocommerce' ),
									'<code>' . esc_html( $override['file'] ) . '</code>',
									'<strong style="color:red">' . esc_html( $current_version ) . '</strong>',
									esc_html( $override['core_version'] )
								);
							} else {
								echo esc_html( $override['file'] );
							}

							if ( ( $total_overrides - 1 ) !== $i ) {
								echo ', ';
							}
							echo '<br />';
						}

						if ( true === $template_overrides_info['has_outdated_templates'] ) {
							?>
							<br />
							<mark class="error">
								<span class="dashicons dashicons-warning"></span>
							</mark>
							<a href="https://woocommerce.com/document/fix-outdated-templates-woocommerce/" target="_blank">
								<?php echo esc_html_x( 'Learn how to update outdated templates', 'link text for template update documentation', 'affiliate-for-woocommerce' ); ?>
							</a>
							<?php
						}
						?>
					</td>
				</tr>
				<?php
			} else {
				?>
				<tr>
					<td data-export-label="Template overrides">
						<?php echo esc_html_x( 'Template overrides', 'title for template override list at system status report', 'affiliate-for-woocommerce' ); ?>:
					</td>
					<td class="help">&nbsp;</td>
					<td>&ndash;</td>
				</tr>
				<?php
			}
		}

		/**
		 * Method to get template overrides info.
		 *
		 * @return array
		 */
		public function get_template_overrides_info() {
			$plugin_base_dir_path   = basename( AFWC_PLUGIN_DIRPATH ) . '/';
			$template_base_dir_path = 'woocommerce/' . $plugin_base_dir_path;

			$override_files     = array();
			$outdated_templates = false;

			/**
			 * Scan the theme directory for all AFW templates to see if active theme overrides any of them.
			 */
			$scan_files = WC_Admin_Status::scan_template_files( AFWC_PLUGIN_DIRPATH . '/templates/' );
			if ( ! empty( $scan_files ) && is_array( $scan_files ) ) {
				foreach ( $scan_files as $file ) {
					if ( file_exists( get_stylesheet_directory() . '/' . $template_base_dir_path . $file ) ) {
						$theme_file = get_stylesheet_directory() . '/' . $template_base_dir_path . $file;
					} elseif ( file_exists( get_template_directory() . '/' . $template_base_dir_path . $file ) ) {
						$theme_file = get_template_directory() . '/' . $template_base_dir_path . $file;
					} elseif ( file_exists( get_stylesheet_directory() . '/' . $plugin_base_dir_path . $file ) ) {
						$theme_file = get_stylesheet_directory() . '/' . $plugin_base_dir_path . $file;
					} elseif ( file_exists( get_template_directory() . '/' . $plugin_base_dir_path . $file ) ) {
						$theme_file = get_template_directory() . '/' . $plugin_base_dir_path . $file;
					} else {
						$theme_file = false;
					}

					if ( ! empty( $theme_file ) ) {
						$core_file = $file;

						$core_version  = WC_Admin_Status::get_file_version( AFWC_PLUGIN_DIRPATH . '/templates/' . $core_file );
						$theme_version = WC_Admin_Status::get_file_version( $theme_file );
						if ( strpos( $file, 'affiliate-reports.php' ) !== false ) {
							$core_version  = trim( str_replace( ':', '', $core_version ) );
							$theme_version = trim( str_replace( ':', '', $theme_version ) );
						}

						if ( $core_version && ( empty( $theme_version ) || version_compare( $theme_version, $core_version, '<' ) ) ) {
							if ( ! $outdated_templates ) {
								$outdated_templates = true;
							}
						}

						$override_files[] = array(
							'file'         => str_replace( WP_CONTENT_DIR . '/themes/', '', $theme_file ),
							'version'      => $theme_version,
							'core_version' => $core_version,
						);
					}
				}
			}

			return array(
				'has_outdated_templates' => $outdated_templates,
				'overrides'              => $override_files,
			);
		}

	}

}

AFWC_System_Status_Report::get_instance();
