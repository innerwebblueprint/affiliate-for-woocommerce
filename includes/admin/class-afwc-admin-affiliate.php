<?php
/**
 * Main class for Affiliate settings under user profile
 *
 * @package     affiliate-for-woocommerce/includes/admin/
 * @since       1.0.0
 * @version     1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Admin_Affiliate' ) ) {

	/**
	 * Class for Admin Affiliate
	 */
	class AFWC_Admin_Affiliate {

		/**
		 * Variable to hold instance of this class
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Admin_Affiliate Singleton object of this class
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
			global $wpdb;

			add_action( 'show_user_profile', array( $this, 'afwc_can_be_affiliate' ) );
			add_action( 'edit_user_profile', array( $this, 'afwc_can_be_affiliate' ) );

			// Validate the affiliate fields.
			add_action( 'user_profile_update_errors', array( $this, 'validate_fields' ) );

			add_action( 'personal_options_update', array( $this, 'save_afwc_can_be_affiliate' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_afwc_can_be_affiliate' ) );

			add_action( 'admin_footer', array( $this, 'styles_scripts' ) );
		}

		/**
		 * Validate the fields.
		 *
		 * @param  WP_Error $errors WP_Error object.
		 * @return void.
		 */
		public function validate_fields( $errors = null ) {
			// prevent processing requests external of the site.
			if ( empty( $_POST['afwc_affiliate_settings_security'] ) || ! wp_verify_nonce( wc_clean( wp_unslash( $_POST['afwc_affiliate_settings_security'] ) ), 'afwc_affiliate_settings_security' )  ) { // phpcs:ignore
				return;
			}

			if ( ! empty( $_POST['afwc_paypal_email'] ) && false === is_email( wc_clean( wp_unslash( $_POST['afwc_paypal_email'] ) ) ) ) { // phpcs:ignore
				if ( $errors instanceof WP_Error && is_callable( array( $errors, 'add' ) ) ) {
					$errors->add( 'paypal_email_validation', _x( '<strong>Error</strong>: The PayPal email address is incorrect.', 'WP Users page: PayPal email validation', 'affiliate-for-woocommerce' ), array( 'form-field' => 'afwc_paypal_email' ) );
				}
			}
		}

		/**
		 * Can user be affiliate?
		 * Add settings if user is affiliate
		 *
		 * @param  WP_User $user The user object.
		 */
		public function afwc_can_be_affiliate( $user ) {
			$user_id = ( ! empty( $user->ID ) ) ? $user->ID : '';

			if ( empty( $user_id ) ) {
				return;
			}

			$is_affiliate = afwc_is_user_affiliate( $user );

			$plugin_data = Affiliate_For_WooCommerce::get_plugin_data();
			wp_enqueue_style( 'afwc-admin-affiliate-style', AFWC_PLUGIN_URL . '/assets/css/afwc-admin-affiliate.css', array(), $plugin_data['Version'] );
			// Register script.
			wp_register_script( 'afwc-user-profile-js', AFWC_PLUGIN_URL . '/assets/js/afwc-user-profile.js', array( 'jquery', 'wp-i18n', 'select2' ), $plugin_data['Version'], true );
			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'afwc-user-profile-js', 'affiliate-for-woocommerce', AFWC_PLUGIN_DIR_PATH . 'languages' );
			}

			wp_localize_script(
				'afwc-user-profile-js',
				'afwcProfileParams',
				array(
					'ajaxurl'              => admin_url( 'admin-ajax.php' ),
					'searchTagsSecurity'   => wp_create_nonce( 'afwc-search-tags' ),
					'searchParentSecurity' => wp_create_nonce( 'afwc-search-parent' ),
				)
			);
			wp_enqueue_script( 'afwc-user-profile-js' );
			wp_nonce_field( 'afwc_affiliate_settings_security', 'afwc_affiliate_settings_security', false );

			?>
			<div class="afwc-settings-wrap">
				<h2 id="afwc-settings"><?php echo esc_html__( 'Affiliate For WooCommerce settings', 'affiliate-for-woocommerce' ); ?></h2>
				<table class="form-table" id="afwc">
					<?php if ( in_array( $is_affiliate, array( 'pending', 'no' ), true ) ) { ?>
						<tr id="afwc_action_row">
							<th><label><?php echo esc_html_x( 'Action', 'label text for affiliate actions on user edit page', 'affiliate-for-woocommerce' ); ?></label></th>
							<td>
								<?php if ( 'pending' === $is_affiliate ) { ?>
									<span class="afwc-approve afwc-actions-wrap"><i class="dashicons dashicons-yes"></i><a href="#" id="afwc_actions" data-affiliate-status="<?php echo esc_attr( 'yes' ); ?>"> <?php echo esc_html_x( 'Approve affiliate', 'Affiliate action', 'affiliate-for-woocommerce' ); ?></a></span>
									<span class="afwc-disapprove afwc-actions-wrap"><i class="dashicons dashicons-no-alt"></i><a href="#" id="afwc_actions" data-affiliate-status="<?php echo esc_attr( 'no' ); ?>"> <?php echo esc_html_x( 'Reject affiliate', 'Affiliate action', 'affiliate-for-woocommerce' ); ?></a></span>
								<?php } elseif ( 'no' === $is_affiliate ) { ?>
									<a href="#" id="afwc_actions" data-affiliate-status="<?php echo esc_attr( 'not_registered' ); ?>"> <?php echo esc_html_x( 'Allow this user to signup via affiliate form', 'Affiliate action', 'affiliate-for-woocommerce' ); ?></a> |
									<a href="#" id="afwc_actions" data-affiliate-status="<?php echo esc_attr( 'yes' ); ?>"> <?php echo esc_html_x( 'Make this user an Affiliate', 'Affiliate action', 'affiliate-for-woocommerce' ); ?></a>
								<?php } ?>
							</td> 
						</tr>
					<?php } ?>
					<tr id="afwc_is_affiliate_row">
						<th><label for="afwc_affiliate_link"><?php echo esc_html_x( 'Is affiliate?', 'label for affiliate status checkbox at user edit page', 'affiliate-for-woocommerce' ); ?></label></th>
						<td><input type="checkbox" name="<?php echo esc_attr( 'afwc_is_affiliate' ); ?>" value="<?php echo esc_attr( 'yes' ); ?>" <?php checked( $is_affiliate, 'yes' ); ?>></td>
					</tr>
					<?php
					if ( 'pending' === $is_affiliate ) {
						$afwc_affiliate_desc = get_user_meta( $user_id, 'afwc_affiliate_desc', true );
						if ( ! empty( $afwc_affiliate_desc ) ) {
							?>
							<tr>
								<th><label><?php echo esc_html_x( 'About Affiliate', 'label for affiliate description at user edit page', 'affiliate-for-woocommerce' ); ?></label></th>
								<td><div class="afwc_affiliate_desc"><?php echo esc_html( $afwc_affiliate_desc ); ?></div></td>
							</tr>
							<?php
						}

						$afwc_affiliate_skype = get_user_meta( $user_id, 'afwc_affiliate_skype', true );
						if ( ! empty( $afwc_affiliate_skype ) ) {
							?>
							<tr>
								<th><label><?php echo esc_html_x( 'Skype ID', 'label for skype id of affiliate in user edit page', 'affiliate-for-woocommerce' ); ?></label></th>
								<td><div><?php echo esc_html( $afwc_affiliate_skype ); ?></div></td>
							</tr>
							<?php
						}

						$afwc_affiliate_contact = get_user_meta( $user_id, 'afwc_affiliate_contact', true );
						if ( ! empty( $afwc_affiliate_contact ) ) {
							?>
							<tr>
								<th><label><?php echo esc_html_x( 'Way To Contact', 'label for contact info of affiliate in user edit page', 'affiliate-for-woocommerce' ); ?></label></th>
								<td><div><?php echo esc_html( $afwc_affiliate_contact ); ?></div></td>
							</tr>
							<?php
						}

						$additional_data = get_user_meta( $user_id, 'afwc_additional_fields', true );
						if ( ! empty( $additional_data ) ) {
							foreach ( $additional_data as $field ) {
								if ( isset( $field['value'] ) && '' !== $field['value'] ) {
									?>
									<tr>
										<th><label><?php echo ! empty( $field['label'] ) ? esc_html( $field['label'] ) : ''; ?></label></th>
										<td>
											<div>
												<?php
												if ( ! empty( $field['type'] ) && ( 'file' === $field['type'] || 'url' === $field['type'] ) ) {
													$data_urls = ! empty( $field['value'] ) ? explode( ',', $field['value'] ) : array();
													if ( ! empty( $data_urls ) ) {
														$separator = '';
														foreach ( $data_urls as $url ) {
															echo wp_kses_post( sprintf( '%1$s<a href="%2$s" target="_blank"> %2$s </a>', $separator, $url ) );
															$separator = ', ';
														}
													}
												} else {
													echo esc_html( $field['value'] );
												}
												?>
											</div>
										</td>
									</tr>
									<?php
								}
							}
						}
					}
					?>
					<?php
					do_action(
						'afwc_admin_edit_user_profile_section',
						$user_id,
						array(
							'status' => $is_affiliate,
							'source' => $this,
						)
					);
					?>
				</table>
				<?php if ( 'pending' !== $is_affiliate ) { ?>
					<p>
						<?php
						echo sprintf(
							/* translators: Highlighted note */
							esc_html_x( '%s All affiliate details are now in the Affiliates dashboard under the Affiliate\'s Profile. You can view and manage them from there.', 'notice that show profile data has been moved', 'affiliate-for-woocommerce' ),
							'<strong>Note:</strong>'
						)
						?>
					</p>
				<?php } ?>
				<p class="afwc-form-description afwc-update-desc">
					<?php echo esc_html_x( 'Note: Click on Update User button to save changes.', 'notice to save user edit page first before leaving to get correct output', 'affiliate-for-woocommerce' ); ?>
				</p>
			</div>
			<?php
		}

		/**
		 * Save can be affiliate data
		 *
		 * @param int $user_id User ID of the user being saved.
		 */
		public function save_afwc_can_be_affiliate( $user_id = 0 ) {

			if ( empty( $user_id ) ) {
				return;
			}

			if ( ! isset( $_POST['afwc_affiliate_settings_security'] ) || ! wp_verify_nonce( wc_clean( wp_unslash( $_POST['afwc_affiliate_settings_security'] ) ), 'afwc_affiliate_settings_security' )  ) { // phpcs:ignore
				return;
			}

			$post_afwc_is_affiliate = ( isset( $_POST['afwc_is_affiliate'] ) ) ? wc_clean( wp_unslash( $_POST['afwc_is_affiliate'] ) ) : ''; // phpcs:ignore
			$old_is_affiliate       = afwc_is_user_affiliate( intval( $user_id ) );

			if ( 'yes' === $post_afwc_is_affiliate ) {
				$afwc_registration = AFWC_Registration_Submissions::get_instance();
				if ( is_callable( array( $afwc_registration, 'approve_affiliate' ) ) ) {
					$afwc_registration->approve_affiliate( $user_id );
				}

				if ( 'pending' === $old_is_affiliate ) {
					// Send welcome email to affiliate.
					if ( true === AFWC_Emails::is_afwc_mailer_enabled( 'afwc_email_welcome_affiliate' ) ) {
						// Trigger email.
						do_action(
							'afwc_email_welcome_affiliate',
							array(
								'affiliate_id'     => $user_id,
								'is_auto_approved' => get_option( 'afwc_auto_add_affiliate', 'no' ),
							)
						);
					}
				}
			} else {

				// Set 'no'(reject) if posted affiliate status is empty and user had assigned to affiliate.
				$post_afwc_is_affiliate = ( empty( $post_afwc_is_affiliate ) && 'yes' === $old_is_affiliate ) ? 'no' : $post_afwc_is_affiliate;

				// Prevent the action if there is not triggered any action.
				if ( ! empty( $post_afwc_is_affiliate ) ) {

					if ( 'not_registered' === $post_afwc_is_affiliate ) {
						delete_user_meta( $user_id, 'afwc_is_affiliate' );
					} else {
						// Update affiliate status.
						update_user_meta( $user_id, 'afwc_is_affiliate', $post_afwc_is_affiliate );
					}
				}

				// Remove Affiliate tags for rejected affiliate.
				if ( 'no' === $post_afwc_is_affiliate && 'yes' === $old_is_affiliate ) {
					wp_set_object_terms( $user_id, array(), 'afwc_user_tags' );
				}
			}

			do_action(
				'afwc_admin_affiliate_profile_update',
				$user_id,
				$_POST,
				array(
					'new_status' => $post_afwc_is_affiliate,
					'old_status' => $old_is_affiliate,
					'source'     => $this,
				)
			);
		}

		/**
		 * Styles & scripts
		 */
		public function styles_scripts() {
			global $pagenow;

			if ( 'profile.php' === $pagenow || 'user-edit.php' === $pagenow ) {

				if ( ! wp_script_is( 'jquery' ) ) {
					wp_enqueue_script( 'jquery' );
				}

				$get_affiliate_roles = get_option( 'affiliate_users_roles' );
				?>
				<script type="text/javascript">
					jQuery(function() {
						jQuery('body').on('change', 'select#role', function(){
							let selectedRole = jQuery(this).find(':selected').val();
							let isAffiliate = jQuery('input[name="afwc_is_affiliate"]').is(':checked');
							let roles = '<?php echo wp_json_encode( $get_affiliate_roles ); ?>';
							affiliate_roles = jQuery.parseJSON( roles );
							if ( false === isAffiliate && -1 !== jQuery.inArray( selectedRole, affiliate_roles ) ) {
								jQuery('input[name="afwc_is_affiliate"]').attr( 'checked', true );
							}
						});
					});
				</script>
				<?php
			}
		}

	}
}

return AFWC_Admin_Affiliate::get_instance();
