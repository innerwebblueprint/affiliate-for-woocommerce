<?php
/**
 * Affiliate summary email report content (Affiliate summary report)
 *
 * @see      This template can be overridden by: https://woocommerce.com/document/affiliate-for-woocommerce/how-to-override-templates/
 * @package  affiliate-for-woocommerce/templates/
 * @since    7.5.0
 * @version  1.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$text_align = is_rtl() ? 'right' : 'left';

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
<?php
	/* translators: %s: Affiliate's first name */
	printf( esc_html_x( 'Hi %s,', 'Greeting message for the affiliate on affiliate summary report email', 'affiliate-for-woocommerce' ), esc_html( $affiliate_name ) );
?>
</p>

<p style="margin-bottom: 2.5rem;">
	<?php
	printf(
		/* translators: %s: Site address */
		esc_html_x(
			"Here's how you've performed on %s in the last month.",
			'Introduction to the performance summary in the email',
			'affiliate-for-woocommerce'
		),
		'<strong>' . esc_html( $site_address ) . '</strong>'
	);
	?>
</p>

<div style="margin-bottom: 40px;">
	<p style="text-align: center;font-size: 1.2rem;"><strong><?php echo wp_kses_post( $total_earning ); ?></strong></p>
	<p style="text-align: center;font-size: 1.2rem;"><strong><?php echo esc_html_x( 'Total Earnings', 'Label for total earning in the affiliate summary email', 'affiliate-for-woocommerce' ); ?><strong></p>
</div>

<div style="margin-bottom: 40px;">
	<table
		class="td"
		cellspacing="0"
		cellpadding="6"
		style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;border: none;"
	>
		<tr>
			<th style="border-style: solid solid none solid; padding: 10px; border-color: #ccc; border-width: 1px; text-align:center;width: 33.33%;"><?php echo esc_html( $total_visits ); ?></th>
			<th style="border-style: solid solid none solid; padding: 10px; border-color: #ccc; border-width: 1px; text-align:center;width: 33.33%;"><?php echo esc_html( $total_customers ); ?></th>
			<th style="border-style: solid solid none solid; padding: 10px; border-color: #ccc; border-width: 1px; text-align:center;width: 33.33%;"><?php echo esc_html( $conversion_rate ); ?></th>
		</tr>
		<tr>
			<td style="border-style: none solid solid solid; padding: 10px; border-color: #ccc; border-width: 1px; text-align:center;width: 33.33%;"><?php echo esc_html_x( 'Visitors', 'Label for total visitors in the affiliate summary email', 'affiliate-for-woocommerce' ); ?></th>
			<td style="border-style: none solid solid solid; padding: 10px; border-color: #ccc; border-width: 1px; text-align:center;width: 33.33%;"><?php echo esc_html_x( 'Customers', 'Label for total customers in the affiliate summary email', 'affiliate-for-woocommerce' ); ?></th>
			<td style="border-style: none solid solid solid; padding: 10px; border-color: #ccc; border-width: 1px; text-align:center;width: 33.33%;"><?php echo esc_html_x( 'Conversion', 'Label for conversion in the affiliate summary email', 'affiliate-for-woocommerce' ); ?></th>
		</tr>
	</table>
</div>

<?php

if ( ! empty( $converted_urls ) && is_array( $converted_urls ) ) {
	?>
	<p style="text-align: center;font-size: 1.2rem;"><strong><?php echo esc_html_x( 'Highest Converting URLs', 'Label for highest converting URLs in the affiliate summary email', 'affiliate-for-woocommerce' ); ?></strong></p>

	<div style="margin-bottom: 40px;">
		<table
			class="td"
			cellspacing="0"
			cellpadding="6"
			style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"
		>
			<tr>
				<th class="td" style="text-align:center; white-space:nowrap;"> <?php echo esc_html_x( 'URL', 'Heading for URL column of the converting URLs table in the affiliate summary email', 'affiliate-for-woocommerce' ); ?> </th>
				<th class="td" style="text-align:center; white-space:nowrap;"> <?php echo esc_html_x( 'Referrals', 'Heading for Referrals column of the converting URLs table in the affiliate summary email', 'affiliate-for-woocommerce' ); ?> </th>
				<th class="td" style="text-align:center; white-space:nowrap;"> <?php echo esc_html_x( 'Total Visits', 'Heading for Total Visits column of the converting URLs table in the affiliate summary email', 'affiliate-for-woocommerce' ); ?> </th>
			</tr>
			<?php
			foreach ( $converted_urls as $key => $url_stats ) {
				?>
				<tr>
					<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>;">
						<?php echo ! empty( $url_stats['url'] ) ? sprintf( '<a href="%1$s" target="_blank"> %2$s </a>', esc_attr( esc_url( $url_stats['url'] ) ), esc_html( $url_stats['url'] ) ) : ''; ?>
					</th>
					<td class="td" style="text-align:center;"><?php echo esc_html( ! empty( $url_stats['referral_count'] ) ? $url_stats['referral_count'] : 0 ); ?></td>
					<td class="td" style="text-align:center;"><?php echo esc_html( ! empty( $url_stats['visitor_count'] ) ? $url_stats['visitor_count'] : 0 ); ?></td>
				</tr>
				<?php
			}
			?>
		</table>
	</div>
	<?php
}
?>

<p>
	<?php
		/* translators: %1$s: Opening a tag for affiliate my account link %2$s: closing a tag for affiliate my account link */
		printf( esc_html_x( 'You can find this information updated in your %1$saffiliate dashboard%2$s.', 'Message to view the affiliate dashboard', 'affiliate-for-woocommerce' ), '<a href="' . esc_url( $my_account_afwc_url ) . '">', '</a>' );
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
