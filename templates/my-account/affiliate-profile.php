<?php
/**
 * My Account > Affiliate > Profile
 *
 * @see      This template can be overridden by: https://woocommerce.com/document/affiliate-for-woocommerce/how-to-override-templates/
 * @package  affiliate-for-woocommerce/templates/my-account/
 * @since    5.7.0
 * @version  1.3.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Note: We do not recommend removing existing classes in HTML.

global $affiliate_for_woocommerce;

$referral_url_pattern = ( 'yes' === $afwc_use_pretty_referral_links ) ? ( $pname . '/' ) : ( '?' . $pname . '=' );

?>
<div id="afwc_resources_wrapper" class="afwc-profile-tab-wrapper">
	<h4><?php echo esc_html_x( 'Referral details', 'referral details section title on my-account profile page', 'affiliate-for-woocommerce' ); ?></h4>
	<div id="afwc_referral_url_container">
		<?php
		echo '<p id="afwc_id_change_wrap">';
		echo esc_html_x( 'Your affiliate identifier is: ', 'label for affiliate identifier', 'affiliate-for-woocommerce' ) . '<code>' . esc_html( $affiliate_identifier ) . '</code>';
		if ( 'yes' === $afwc_allow_custom_affiliate_identifier ) {
			?>
			<a href="#" id="afwc_change_identifier" title="<?php echo esc_attr_x( 'Click to change', 'label for click action to change affiliate identifier', 'affiliate-for-woocommerce' ); ?>">
				<i class="fa fa-pencil-alt"></i>
			</a>
			</p>
			<p id="afwc_id_save_wrap" style="display: none">
				<?php echo esc_html_x( 'Change affiliate identifier: ', 'label to change affiliate identifier', 'affiliate-for-woocommerce' ); ?>
				<input type="text" id="afwc_ref_url_id" value="<?php echo esc_attr( $affiliate_identifier ); ?>" />
				<a href="#" id="afwc_save_identifier" title="<?php echo esc_html_x( 'Save', 'save button', 'affiliate-for-woocommerce' ); ?>">
					<i class="fa fa-save"></i>
				</a>
				<a href="#" id="afwc_cancel_change_identifier" title="<?php echo esc_attr_x( 'Cancel', 'label to cancel the action to change affiliate identifier', 'affiliate-for-woocommerce' ); ?>">
					<i class="fa fa-light fa-ban"></i>
				</a>
			</p>
			<p id="afwc_id_msg" style="display: none"></p>
			<p id="afwc_save_id_loader" style="display: none">
				<img src="<?php echo esc_url( WC()->plugin_url() . '/assets/images/wpspin-2x.gif' ); ?>" />
			</p>
			<p>
				<?php echo esc_html_x( 'You can change above identifier to anything like your name, brand name.', 'description for changing affiliate identifier', 'affiliate-for-woocommerce' ); ?>
			</p>
			<?php
		} else {
			echo '</p>';
		}
		?>
		<p>
			<?php
			echo esc_html_x( 'Your referral link is: ', 'affiliate referral url label', 'affiliate-for-woocommerce' );

			$affiliate_redirection          = apply_filters( 'afwc_referral_redirection_url', trailingslashit( home_url() ), $affiliate_id, array( 'source' => $affiliate_for_woocommerce ) );
			$affiliate_url_with_redirection = afwc_get_affiliate_url( $affiliate_redirection, '', $affiliate_identifier );
			?>
			<span title="<?php echo esc_attr_x( 'Click to copy', 'click to copy label for referral url', 'affiliate-for-woocommerce' ); ?>">
				<code id="afwc_affiliate_link_label" class="afwc-click-to-copy afwc-affiliate-ref-url-label" data-ctp="<?php echo esc_url( $affiliate_url_with_redirection ); ?>" data-redirect="<?php echo esc_url( $affiliate_redirection ); ?>">
					<?php echo esc_url( $affiliate_url_with_redirection ); ?>
				</code>
			</span>
		</p>
	</div>
	<div id="afwc_referral_coupon_container">
		<?php
		if ( 'yes' === $afwc_use_referral_coupons ) {
			$afwc_coupon          = is_callable( array( 'AFWC_Coupon', 'get_instance' ) ) ? AFWC_Coupon::get_instance() : null;
			$referral_coupon_code = ( ! empty( $afwc_coupon ) && is_callable( array( $afwc_coupon, 'get_referral_coupon' ) ) ) ? $afwc_coupon->get_referral_coupon( array( 'user_id' => $user_id ) ) : array();

			if ( empty( $referral_coupon_code ) ) {
				if ( ( ! empty( $affiliate_manager_contact_email ) ) ) {
					?>
					<p>
						<?php echo esc_html_x( 'Want an exclusive coupon to promote?', 'label to get affiliate coupon', 'affiliate-for-woocommerce' ); ?>
						<a href="mailto:<?php echo esc_attr( $affiliate_manager_contact_email ); ?>?subject=[Affiliate Partner] Send me an exclusive coupon&body=Hi%20there%0D%0A%0D%0APlease%20send%20me%20a%20affiliate%20coupon%20for%20running%20a%20promotion.%0D%0A%0D%0AThanks%0D%0A%0D%0A">
							<?php echo esc_html_x( 'Request affiliate manager for a coupon', 'label to request a coupon from affiliate manager', 'affiliate-for-woocommerce' ); ?>
						</a>
					</p>
					<?php
				}
			} else {
				echo esc_html_x( 'Your referral coupon details: ', 'affiliate referral coupon details', 'affiliate-for-woocommerce' );
				?>
				<table class="afwc_coupons">
					<thead>
						<tr>
							<th>
								<?php echo esc_html_x( 'Coupon code', 'coupon code/name', 'affiliate-for-woocommerce' ); ?>
							</th>
							<th>
								<?php echo esc_html_x( 'Amount', 'coupon discount amount', 'affiliate-for-woocommerce' ); ?>
							</th>
							<?php if ( ! empty( $show_coupon_url ) && 'yes' === $show_coupon_url ) { ?>
							<th data-header="coupon-url">
								<?php echo esc_html_x( 'Coupon URL', 'coupon URL', 'affiliate-for-woocommerce' ); ?>
							</th>
							<?php } ?>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( is_array( $referral_coupon_code ) || ! empty( $referral_coupon_code ) ) {
							foreach ( $referral_coupon_code as $coupon_id => $coupon_code ) {
								$coupon_params = ( ! empty( $afwc_coupon ) && is_callable( array( $afwc_coupon, 'get_coupon_params' ) ) ) ? $afwc_coupon->get_coupon_params( $coupon_code ) : array();
								if ( ! empty( $coupon_params ) ) {
									if ( isset( $coupon_params['discount_amount'] ) && ! empty( $coupon_params['discount_type'] ) ) {
										$coupon_discount_amount = $coupon_params['discount_amount'];
										$coupon_discount_type   = $coupon_params['discount_type'];
										if ( in_array( $coupon_discount_type, array( 'percent', 'sign_up_fee_percent', 'recurring_percent' ), true ) ) {
											$coupon_with_discount = wp_kses_post( $coupon_discount_amount ) . '%';
										} else {
											$coupon_with_discount = wp_kses_post( AFWC_CURRENCY ) . wc_format_decimal( $coupon_discount_amount, wc_get_price_decimals() );
										}
										?>
										<tr>
											<td data-title="<?php echo esc_attr_x( 'Coupon code', 'coupon code/name', 'affiliate-for-woocommerce' ); ?>">
												<?php echo wp_kses_post( afwc_get_click_to_copy_html( $coupon_code, array( 'id' => 'afwc_referral_coupon' ) ) ); ?>
											</td>
											<td data-title="<?php echo esc_attr_x( 'Amount', 'coupon discount amount', 'affiliate-for-woocommerce' ); ?>">
												<span><?php echo esc_attr( $coupon_with_discount ); ?></span>
											</td>
											<?php
											if ( ! empty( $show_coupon_url ) && 'yes' === $show_coupon_url ) {
												$afwc_merge_tags = AFWC_Merge_Tags::get_instance();
												?>
											<td data-header="coupon-url" data-title="<?php echo esc_attr_x( 'Coupon URL', 'coupon URL', 'affiliate-for-woocommerce' ); ?>">
												<?php echo is_callable( array( $afwc_merge_tags, 'parse_content' ) ) ? wp_kses_post( $afwc_merge_tags->parse_content( "{afwc_affiliate_coupon code='{$coupon_code}'}", array( 'affiliate' => $affiliate_id ) ) ) : ''; ?>
											</td>
											<?php } ?>
										</tr>
										<?php
									}
								}
							}
						}
						?>
					</tbody>
				</table>
				<?php
			}
		}
		?>
	</div>
	<?php if ( ! empty( $afwc_landings_pages ) ) { ?>
		<div id="afwc_landing_pages_container">
			<p><?php echo esc_html_x( 'Landing pages', 'label for landing pages', 'affiliate-for-woocommerce' ); ?></p>
			<ul class="afwc-landing-page-urls">
				<?php
				foreach ( $afwc_landings_pages as $landing_page_url ) {
					if ( empty( $landing_page_url ) ) {
						continue;
					}
					?>
					<li>
						<span title="<?php echo esc_attr_x( 'Click to copy', 'click to copy label for landing page url', 'affiliate-for-woocommerce' ); ?>">
							<code id="afwc_landing_page_link_label" class="afwc-click-to-copy" data-ctp="<?php echo esc_url( $landing_page_url ); ?>">
								<?php echo esc_url( $landing_page_url ); ?>
							</code>
						</span>
					</li>
					<?php
				}
				?>
			</ul>
		</div>
	<?php } ?>
	<hr>
	<div id="afwc_custom_referral_url_container">
		<h4><?php echo esc_html_x( 'Referral link generator', 'label to generate custom referral link/url', 'affiliate-for-woocommerce' ); ?>
		</h4>
		<div class="afwc-ref-url-generator-wrapper">
			<em class="afwc-ref-url-generator-desc">
				<?php echo esc_html_x( "To generate a unique referral link for a specific page - say a product - enter the section of the URL after the website's address into the target path below (for example, product/macbook).", 'description for custom referral url generator', 'affiliate-for-woocommerce' ); ?>
			</em>
			<p class="afwc-ref-url-generator-input-wrapper">
				<?php echo esc_html_x( 'Page Link', 'label for page link/url', 'affiliate-for-woocommerce' ); ?>:
				<span id="afwc_custom_referral_url">
					<?php echo esc_url( trailingslashit( home_url() ) ); ?>
					<input type="text" name="afwc_affiliate_link" id="afwc_affiliate_link" placeholder="<?php echo esc_html_x( 'Enter target path here...', 'label to add any site page for custom referral url', 'affiliate-for-woocommerce' ); ?>" />
					<?php echo wp_kses_post( $referral_url_pattern ); ?>
					<span class="afwc_ref_id_span">
						<?php echo esc_attr( $affiliate_identifier ); ?>
					</span>
				</span>
			</p>
			<p>
				<?php echo esc_html_x( 'Referral Link: ', 'custom referral url', 'affiliate-for-woocommerce' ); ?>
				<span title="<?php echo esc_attr_x( 'Click to copy', 'click to copy label for custom referral url', 'affiliate-for-woocommerce' ); ?>">
					<code id="afwc_generated_affiliate_link" class="afwc-click-to-copy" data-ctp="<?php echo esc_url( $affiliate_url ); ?>">
						<?php echo esc_url( $affiliate_url ); ?>
					</code>
				</span>
			</p>
		</div>
	</div>
	<?php
	if ( ! empty( $available_payout_methods ) && is_array( $available_payout_methods ) ) {
		$available_payout_method_keys = array_keys( $available_payout_methods );
		$available_payout_methods     = array_merge( array( '' => esc_html_x( 'Choose a payout method', 'Label for choosing a payout method', 'affiliate-for-woocommerce' ) ), $available_payout_methods );
		?>
		<hr>
		<div id="afwc_payout_details_container">
			<h4><?php echo esc_html_x( 'Payout setting', 'label for commission payout setting', 'affiliate-for-woocommerce' ); ?></h4>
			<form id="afwc_account_form" action="" method="post">
				<div id="afwc_payment_wrapper">
					<p class="woocommerce-form-row">
						<label for="afwc_payout_method"><?php echo esc_html_x( 'Select payout method:', 'Label for payout method selection on my account', 'affiliate-for-woocommerce' ); ?></label>
						<select name="afwc_payout_method" id="afwc_payout_method" class="woocommerce-Input woocommerce-Input--select">
							<?php foreach ( $available_payout_methods as $available_method => $available_method_title ) { ?>
								<option value="<?php echo esc_attr( $available_method ); ?>" <?php selected( $payout_method, $available_method ); ?>><?php echo esc_html( $available_method_title ); ?></option>
							<?php } ?>
						</select>
					</p>
					<?php
					if ( in_array( 'stripe', $available_payout_method_keys, true ) ) {
						$stripe_functions = is_callable( array( 'AFWC_Stripe_Functions', 'get_instance' ) ) ? AFWC_Stripe_Functions::get_instance() : null;
						$oauth_link       = '';

						if ( 'disconnect' === $current_status ) {
							$stripe_connect_api = is_callable( array( 'AFWC_Stripe_Connect', 'get_instance' ) ) ? AFWC_Stripe_Connect::get_instance() : null;
							$oauth_link         = ( ! empty( $stripe_connect_api ) && is_callable( array( $stripe_connect_api, 'get_oauth_link' ) ) ) ? $stripe_connect_api->get_oauth_link() : '';
						}

						$button_text  = '';
						$button_class = '';

						if ( 'connect' === $current_status ) {
							$button_text  = _x( 'Disconnect from Stripe', 'button text to disconnect from stripe', 'affiliate-for-woocommerce' );
							$button_class = 'afwc_stripe_disconnect';
						} elseif ( 'disconnect' === $current_status ) {
							$button_text = _x( 'Connect with Stripe', 'button text to connect stripe', 'affiliate-for-woocommerce' );
						}
						?>
						<p data-payout-method="stripe" style="display:none;">
							<span class="afwc-stripe-connect">
								<label><?php echo esc_html_x( 'Connect with your Stripe account:', 'label for stripe authorization', 'affiliate-for-woocommerce' ); ?></label>
								<span class="afwc-stripe-connect-link">
									<a id="afwc_stripe_connect_button" href="<?php echo esc_url( $oauth_link ); ?>" class="stripe_connect <?php echo esc_attr( $button_class ); ?>">
										<span><?php echo esc_html( $button_text ); ?></span>
									</a>
								</span>
								<img class="afwc-stripe-connect-loader" src="<?php echo esc_url( WC()->plugin_url() . '/assets/images/wpspin-2x.gif' ); ?>" style="display:none;" />
							</span>
							<span class="message"> </span>
						</p>
					<?php } ?>
					<?php if ( in_array( 'paypal', $available_payout_method_keys, true ) ) { ?>
						<?php $afwc_paypal_email = get_user_meta( $user_id, 'afwc_paypal_email', true ); ?>
						<div class="afwc-paypal-input-wrapper" data-payout-method="paypal" style="display:none;">
							<div class="afwc-paypal-input-label woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
								<label for="afwc_affiliate_paypal_email">
									<?php echo esc_html_x( 'PayPal email address', 'label for PayPal email address for payouts', 'affiliate-for-woocommerce' ); ?>
								</label>
								<input type="email" name="afwc_affiliate_paypal_email" id="afwc_affiliate_paypal_email" class="woocommerce-Input woocommerce-Input--text input-text" value="<?php echo esc_attr( $afwc_paypal_email ); ?>" placeholder="<?php echo esc_attr_x( 'example@domain.com', 'paypal email placeholder at my account', 'affiliate-for-woocommerce' ); ?>" />
							</div>
							<div class="afwc-paypal-input-description">
								<em>
									<?php echo esc_html_x( 'You will receive your affiliate commission on the above PayPal email address.', 'description for PayPal email address payout', 'affiliate-for-woocommerce' ); ?>
								</em>
							</div>
						</div>
					<?php } ?>
					<p>
						<button type="submit" id="afwc_save_account_button" name="afwc_save_account_button">
							<?php echo esc_html_x( 'Save', 'save button', 'affiliate-for-woocommerce' ); ?>
						</button>
						<span class="afwc_save_account_status"></span>
						<span class="afwc-account-save-response-msg"> </span>
					</p>
				</div>
			</form>
		</div>
		<?php
	}
	if ( ! empty( $affiliate_manager_contact_email ) ) {
		?>
		<div id="afwc_contact_admin_container">
			<?php echo esc_html_x( 'Have any queries?', 'label for any queries', 'affiliate-for-woocommerce' ); ?>
			<a href="mailto:<?php echo esc_attr( $affiliate_manager_contact_email ); ?>">
				<?php echo esc_html_x( 'Contact affiliate manager', 'label to contact affiliate manager', 'affiliate-for-woocommerce' ); ?>
			</a>
		</div>
		<?php
	}
	?>
</div>
<?php
