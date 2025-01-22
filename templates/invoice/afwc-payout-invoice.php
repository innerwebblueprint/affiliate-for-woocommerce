<?php
/**
 * Affiliate Payout invoice template
 *
 * @see      This template can be overridden by: https://woocommerce.com/document/affiliate-for-woocommerce/how-to-override-templates/
 * @package  affiliate-for-woocommerce/templates/invoice/
 * @since    7.19.0
 * @version  1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html_x( 'Payout Invoice', 'Title for payout invoice print window', 'affiliate-for-woocommerce' ); ?></title>
	<style type="text/css">
		body {
			margin: 0;
			padding: 1.25em;
			font-family: Arial, sans-serif;
			color: #333;
		}
		.container {
			max-width: 90%;
			margin: 0 auto;
			padding: 1.25em;
			border: 1px solid #ddd;
			background-color: #fff;
		}
		.header {
			text-align: center;
			margin-bottom: 2.5em; 
		}
		.header img {
			height: 4em;
		}
		.address-details {
			display: flex;
			justify-content: space-between;
			margin-bottom: 2.5em;
		}
		.address-list {
			line-height: 1.5;
			padding: 0;
			margin: 0;
		}
		.payout-details table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 2.5em;
		}
		.payout-details table, .payout-details th, .payout-details td {
			border: 0.06em solid #ddd;
		}
		.payout-details th, .payout-details td {
			padding: 0.5em;
			text-align: left;
		}
		.payout-details th {
			font-weight: bold;
			width: 14em;
		}
		.footer {
			text-align: center;
			font-size: 0.9em;
			margin-top: 2.5em;
		}
		@page {
			size: A4 landscape;
			margin: 0;
		}
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<?php if ( ! empty( $logo_url ) ) { ?>
				<img src="<?php echo esc_attr( $logo_url ); ?>" alt="<?php echo esc_html( $store_name ); ?>">
			<?php } ?>
		</div>
		<h4><?php echo esc_html( gmdate( $date_format, strtotime( $date_time ) ) ); ?></h4>
		<div class="address-details">
			<div class="store-details">
				<h2>
				<?php
				echo esc_html(
					/* translators: 1: The invoice ID */
					sprintf( _x( 'Invoice #%s', 'Invoice ID in payout invoice template', 'affiliate-for-woocommerce' ), $payout_id )
				);
				?>
				</h2>
				<ul class="address-list">
				<?php echo wp_kses_post( $store_address ); ?>
				</ul>
			</div>
			<div class="affiliate-details">
				<h2><?php echo esc_html_x( 'Affiliate Details', 'Heading for affiliate details in payout invoice template', 'affiliate-for-woocommerce' ); ?></h2>
				<ul class="address-list">
				<?php echo wp_kses_post( $affiliate_address ); ?>
				</ul>
			</div>
		</div>
		<div class="payout-details">
			<h2><?php echo esc_html_x( 'Payout Details:', 'Heading for payout details in payout invoice template', 'affiliate-for-woocommerce' ); ?></h2>
			<table>
				<tbody>
					<tr>
						<th><?php echo esc_html_x( 'Period', 'Label for invoice period in invoice template', 'affiliate-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( gmdate( $date_format, strtotime( $from_period ) ) ) . ' ' . esc_html_x( 'to', 'Payout period separator in invoice template', 'affiliate-for-woocommerce' ) . ' ' . esc_html( gmdate( $date_format, strtotime( $to_period ) ) ); ?></td>
					</tr>
					<tr>
						<th><?php echo esc_html_x( 'Successful referrals', 'Label for successful referral count in invoice template', 'affiliate-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $referral_count ); ?></td>
					</tr>
					<tr>
						<th><?php echo esc_html_x( 'Amount', 'Label for amount in invoice template', 'affiliate-for-woocommerce' ); ?></th>
						<td><?php echo wp_kses_post( wc_price( $amount, array( 'currency' => $currency ) ) ); ?></td>
					</tr>
					<tr>
						<th><?php echo esc_html_x( 'Method', 'Label for payout method in invoice template', 'affiliate-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $method ); ?></td>
					</tr>
					<tr>
						<th><?php echo esc_html_x( 'Notes', 'Label for payout notes in invoice template', 'affiliate-for-woocommerce' ); ?></th>
						<td><?php echo wp_kses_post( $notes ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="footer">
			<?php echo esc_html_x( 'Thank you for being our high valued affiliate!', 'Thanks message in payout invoice template', 'affiliate-for-woocommerce' ); ?>
		</div>
	</div>
</body>
</html>
