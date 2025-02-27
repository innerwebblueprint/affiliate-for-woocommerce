<?php
/**
 * Affiliate Payout Sent Email Content (Affiliate - Commission Paid)
 *
 * @see      This template can be overridden by: https://woocommerce.com/document/affiliate-for-woocommerce/how-to-override-templates/
 * @package  affiliate-for-woocommerce/templates/
 * @since    2.4.1
 * @version  1.1.6
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
	<?php
	/* translators: %s: Affiliate's first name */
	printf( esc_html_x( 'Hi %s,', 'greeting message for the affiliate', 'affiliate-for-woocommerce' ), esc_html( $affiliate_name ) );
	?>
</p>

<p><?php echo esc_html_x( 'Congratulations on your successful referrals. We just processed your commission payout.', 'congratulating affiliate for successful referrals and payouts', 'affiliate-for-woocommerce' ); ?></p>

<p><i><?php echo esc_html_x( 'Period: ', 'title for the period of commission payout', 'affiliate-for-woocommerce' ); ?></i><?php echo esc_html( $start_date ) . esc_html__( ' to ', 'affiliate-for-woocommerce' ) . esc_html( $end_date ); ?></p>

<p><i><?php echo esc_html_x( 'Successful referrals: ', 'title for the successful referral records', 'affiliate-for-woocommerce' ); ?></i><?php echo esc_html( $total_referrals ); ?></p>

<p><i><?php echo esc_html_x( 'Commission: ', 'title for the commission amount', 'affiliate-for-woocommerce' ); ?></i><?php echo wp_kses_post( $currency_symbol . '' . $commission_amount ); ?></p>

<p><i><?php echo esc_html_x( 'Payout method: ', 'title for the payout method', 'affiliate-for-woocommerce' ); ?></i><?php echo esc_html( afwc_get_payout_methods( $payout_method ) ); ?></p>

<?php
if ( ! empty( $paypal_receiver_email ) ) {
	if ( 'paypal' === $payout_method ) {
		?>
		<p><i><?php echo esc_html_x( 'PayPal email address: ', 'title for the PayPal email address', 'affiliate-for-woocommerce' ); ?></i><?php echo esc_html( $paypal_receiver_email ); ?></p>
		<?php
	}
	if ( 'stripe' === $payout_method ) {
		?>
		<p><i><?php echo esc_html_x( 'Stripe receiver account: ', 'title for the Stripe receiver account', 'affiliate-for-woocommerce' ); ?></i><?php echo esc_html( $paypal_receiver_email ); ?></p>
		<?php
	}
}

if ( ! empty( $payout_notes ) ) {
	?>
	<p><i><?php echo esc_html_x( 'Additional notes: ', 'title for the additional note', 'affiliate-for-woocommerce' ); ?></i><?php echo esc_html( $payout_notes ); ?></p>
	<?php
}
?>

<p>
	<?php
		printf(
			/* translators: 1: Text for invoice 2: Opening a tag for affiliate my account link 3: closing a tag for affiliate my account link */
			esc_html_x( 'We have already updated your account with this info%1$s. You can %2$slogin to your affiliate dashboard%3$s to track all referrals, payouts and campaigns.', 'message for affiliate to find payout and other information in their account', 'affiliate-for-woocommerce' ),
			! empty( $show_invoice ) ? esc_html_x( ' and generated the invoice', 'Text for showing invoice', 'affiliate-for-woocommerce' ) : '',
			'<a href="' . esc_url( $my_account_afwc_url ) . '" class="button alt link">',
			'</a>'
		);
		?>
</p>

<p><?php echo esc_html_x( 'We look forward to sending bigger payouts to you next time. Keep promoting more and keep living a life you love.', 'closing remark for affiliate', 'affiliate-for-woocommerce' ); ?></p>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/**
 * Output the email footer
 *
 * @hooked WC_Emails::email_footer() Output the email footer.
 * @param string $email.
 */
do_action( 'woocommerce_email_footer', $email );
