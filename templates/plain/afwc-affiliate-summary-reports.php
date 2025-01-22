<?php
/**
 * Affiliate summary email report plain content (Affiliate summary report)
 *
 * @see      This template can be overridden by: https://woocommerce.com/document/affiliate-for-woocommerce/how-to-override-templates/
 * @package  affiliate-for-woocommerce/templates/plain/
 * @since    7.5.0
 * @version  1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Affiliate's first name */
printf( esc_html_x( 'Hi %s,', 'Greeting message for the affiliate user on affiliate summary email', 'affiliate-for-woocommerce' ), esc_html( $affiliate_name ) );
echo "\n\n";

printf(
	/* translators: %s: Site address */
	esc_html_x(
		"Here's how you've performed on %s in the last month.",
		'Introduction to the performance summary in the email',
		'affiliate-for-woocommerce'
	),
	esc_html( $site_address )
);

echo "\n\n----------------------------------------\n\n";

printf( '%s: %s', esc_html_x( 'Total Earnings', 'Label for total earnings in the affiliate summary email', 'affiliate-for-woocommerce' ), wp_kses_post( $total_earning ) );
echo "\n";
printf( '%s: %s', esc_html_x( 'Visitors', 'Label for total visitors in the affiliate summary email', 'affiliate-for-woocommerce' ), esc_html( $total_visits ) );
echo "\n";
printf( '%s: %s', esc_html_x( 'Customers', 'Label for total customers in the affiliate summary email', 'affiliate-for-woocommerce' ), esc_html( $total_customers ) );
echo "\n";
printf( '%s: %s', esc_html_x( 'Conversion', 'Label for conversion rate in the affiliate summary email', 'affiliate-for-woocommerce' ), esc_html( $conversion_rate ) );
echo "\n";

echo "\n----------------------------------------\n\n";

if ( ! empty( $converted_urls ) && is_array( $converted_urls ) ) {

	echo esc_html_x( 'Highest Converting URLs:', 'Label for highest converting URLs in the affiliate summary email', 'affiliate-for-woocommerce' ) . "\n";

	foreach ( $converted_urls as $url_stats ) {
		printf(
			/* translators: 1: The URL 2: Referral count 3: Visitor count */
			esc_html_x( '%1$s (Referrals: %2$s, Total Visits: %3$s)', 'Stats for top 10 converting URLs in summary email report', 'affiliate-for-woocommerce' ),
			esc_html( ! empty( $url_stats['url'] ) ? esc_url( $url_stats['url'] ) : '' ),
			esc_html( ! empty( $url_stats['referral_count'] ) ? $url_stats['referral_count'] : '' ),
			esc_html( ! empty( $url_stats['visitor_count'] ) ? $url_stats['visitor_count'] : '' )
		);
		echo "\n";
	}
}

echo "\n----------------------------------------\n\n";

/* translators: %s: Affiliate my account link */
printf( esc_html_x( 'You can find this information updated in your affiliate dashboard: %s.', 'Message to view the affiliate dashboard', 'affiliate-for-woocommerce' ), esc_url( $my_account_afwc_url ) ) . "\n\n";

echo "\n----------------------------------------\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
	echo "----------------------------------------\n\n";
}

// Output the email footer.
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) . "\n";
