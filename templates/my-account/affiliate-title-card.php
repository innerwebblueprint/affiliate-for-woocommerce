<?php
/**
 * My Account > Affiliate
 *
 * @see      This template can be overridden by: https://woocommerce.com/document/affiliate-for-woocommerce/how-to-override-templates/
 * @package  affiliate-for-woocommerce/templates/my-account/
 * @since    8.5.0
 * @version  1.0.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Note: We do not recommend removing existing id & classes in HTML.
?>
<div class="afwc-affiliate-details-card-wrapper">
	<div class="afwc-affiliate-details-card">
		<?php if ( ! empty( $affiliate_avatar_url ) ) { ?>
		<div class="afwc-avatar-wrapper">
			<img src="<?php echo esc_url( $affiliate_avatar_url ); ?>" class="afwc-avatar-img" alt="<?php echo esc_attr_x( 'Affiliate profile image', 'affiliate profile image', 'affiliate-for-woocommerce' ); ?>" height="75" width="75" />
		</div>
		<?php } ?>
		<div class="afwc-details">
			<h3 class="afwc-display-name">
				<?php echo esc_html( $affiliate_display_name ); ?>
			</h3>
			<?php if ( ! empty( $affiliate_signup_date ) ) { ?>
			<div class="afwc-signup-date-wrapper">
				<span class="afwc-detail-title"><?php echo esc_html_x( 'Since:', 'affiliate signup date label on affiliate card', 'affiliate-for-woocommerce' ); ?></span>
				<span><?php echo esc_html( $affiliate_signup_date ); ?></span>
			</div>
			<?php } ?>
			<div class="afwc-referral-url-wrapper">
				<span class="afwc-detail-title">
					<?php echo esc_html_x( 'Referral Link:', 'affiliate referral url label on affiliate card', 'affiliate-for-woocommerce' ); ?>
				</span>
				<span title="<?php echo esc_attr_x( 'Click to copy', 'click to copy label for referral link/url', 'affiliate-for-woocommerce' ); ?>">
					<code class="afwc-click-to-copy afwc-affiliate-ref-url-label" data-ctp="<?php echo esc_url( $affiliate_url_with_redirection ); ?>" data-redirect="<?php echo esc_url( $affiliate_redirection ); ?>">
						<?php echo esc_url( $affiliate_url_with_redirection ); ?>
					</code>
				</span>
			</div>
		</div>
	</div>
</div>
<?php
