<?php
/**
 * Automatic payout reminder email content (Affiliate Manager - Automatic Payouts Reminder)
 *
 * @see      This template can be overridden by: https://woocommerce.com/document/affiliate-for-woocommerce/how-to-override-templates/
 * @package  affiliate-for-woocommerce/templates/emails/plain/
 * @since    8.0.0
 * @version  1.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Affiliate Manager or Store admin's first name */
printf( esc_html_x( 'Hi %s,', 'Placeholder to greet admin', 'affiliate-for-woocommerce' ), esc_html( $admin_name ) ) . "\n\n";

/* translators: %1$s: Affiliate name. %2$s: Date time when automatic payouts will be sent */
printf( esc_html_x( 'Automatic payout for the affiliate: %1$s is set to be processed on: %2$s', 'description for automatic payouts affiliate details', 'affiliate-for-woocommerce' ), esc_html( $affiliate_name ), esc_html( $timestamp ) ) . "\n\n";

echo "\n----------------------------------------\n\n";

/* translators: %s: Admin pending payouts dashboard link */
printf( esc_html_x( 'You can review, manage as well as cancel affiliate\'s automatic payouts from here: %s', 'Description on where to manage automatic payout', 'affiliate-for-woocommerce' ), esc_url( $pending_payouts_dashboard_url ) ) . "\n\n";

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
