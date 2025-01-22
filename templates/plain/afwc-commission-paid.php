<?php
/**
 * Affiliate Payout Sent Email Content (Affiliate - Commission Paid)
 *
 * @see      This template can be overridden by: https://woocommerce.com/document/affiliate-for-woocommerce/how-to-override-templates/
 * @package  affiliate-for-woocommerce/templates/plain/
 * @since    2.4.1
 * @version  1.1.5
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Affiliate's first name */
printf( esc_html_x( 'Hi %s,', 'greeting message for the affiliate', 'affiliate-for-woocommerce' ), esc_html( $affiliate_name ) ) . "\n\n";

echo esc_html_x( 'Congratulations on your successful referrals. We just processed your commission payout.', 'congratulating affiliate for successful referrals and payouts', 'affiliate-for-woocommerce' ) . "\n\n";

echo "\n----------------------------------------\n\n";

echo esc_html_x( 'Period: ', 'title for the period of commission payout', 'affiliate-for-woocommerce' ) . "\t " . esc_html( $start_date ) . esc_html__( ' to ', 'affiliate-for-woocommerce' ) . esc_html( $end_date ) . "\n";

echo esc_html_x( 'Successful referrals: ', 'title for the successful referral records', 'affiliate-for-woocommerce' ) . "\t " . esc_html( $total_referrals ) . "\n";

echo esc_html_x( 'Commission: ', 'title for the commission amount', 'affiliate-for-woocommerce' ) . "\t " . wp_kses_post( $currency_symbol . '' . $commission_amount ) . "\n";

echo esc_html_x( 'Payout method: ', 'title for the payout method', 'affiliate-for-woocommerce' ) . "\t " . esc_html( afwc_get_payout_methods( $payout_method ) ) . "\n";

if ( ! empty( $paypal_receiver_email ) ) {
	if ( 'paypal' === $payout_method ) {
		echo esc_html_x( 'PayPal email address: ', 'title for the PayPal email address', 'affiliate-for-woocommerce' ) . "\t " . esc_html( $paypal_receiver_email ) . "\n";
	}
	if ( 'stripe' === $payout_method ) {
		echo esc_html_x( 'Stripe receiver account: ', 'title for the Stripe receiver account', 'affiliate-for-woocommerce' ) . "\t " . esc_html( $paypal_receiver_email ) . "\n";
	}
}

if ( ! empty( $payout_notes ) ) {
	echo esc_html_x( 'Additional notes: ', 'title for the additional note', 'affiliate-for-woocommerce' ) . "\t " . esc_html( $payout_notes ) . "\n\n";
}

echo "\n----------------------------------------\n\n";

printf(
	/* translators: 1: Payout invoice text 2: Affiliate my account link */
	esc_html_x( 'We have already updated your account with this info%1$s. You can login to your affiliate dashboard to track all referrals, payouts and campaigns: %2$s', 'message for affiliate to find payout and other information in their account', 'affiliate-for-woocommerce' ),
	! empty( $show_invoice ) ? esc_html_x( ' and generated the invoice', 'Text for showing invoice', 'affiliate-for-woocommerce' ) : '',
	esc_url( $my_account_afwc_url )
) . "\n\n";

echo esc_html_x( 'We look forward to sending bigger payouts to you next time. Keep promoting more and keep living a life you love.', 'closing remark for affiliate', 'affiliate-for-woocommerce' ) . "\n\n";

echo "\n\n----------------------------------------\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

// Output the email footer.
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
