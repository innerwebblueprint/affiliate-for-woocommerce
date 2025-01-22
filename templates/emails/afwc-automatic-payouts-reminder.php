<?php
/**
 * Automatic payout reminder email content (Affiliate Manager - Automatic Payouts Reminder)
 *
 * @see      This template can be overridden by: https://woocommerce.com/document/affiliate-for-woocommerce/how-to-override-templates/
 * @package  affiliate-for-woocommerce/templates/emails/
 * @since    8.0.0
 * @version  1.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>
<p>
	<?php
	/* translators: %s: Affiliate Manager or Store admin's first name */
	printf( esc_html_x( 'Hi %s,', 'Placeholder to greet admin', 'affiliate-for-woocommerce' ), esc_html( $admin_name ) );
	?>
</p>

<p>
	<?php
	/* translators: %1$s: Affiliate name. %2$s: Date time when automatic payouts will be sent */
	printf( esc_html_x( 'Automatic payout for the affiliate: %1$s is set to be processed on: %2$s', 'description for automatic payouts affiliate details', 'affiliate-for-woocommerce' ), esc_html( $affiliate_name ), wp_kses_post( $timestamp ) );
	?>
</p>

<p>
	<?php
	/* translators: %1$s: Opening a tag for admin pending payouts dashboard link %2$s: closing a tag for admin pending payouts dashboard link */
	printf( esc_html_x( 'You can review, manage as well as cancel affiliate\'s automatic payouts from %1$shere%2$s.', 'Description on where to manage automatic payout', 'affiliate-for-woocommerce' ), '<a href="' . esc_url( $pending_payouts_dashboard_url ) . '">', '</a>' );
	?>
</p>

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
