<?php
/**
 * My Account > Affiliate > Reports
 *
 * @see      This template can be overridden by: https://woocommerce.com/document/affiliate-for-woocommerce/how-to-override-templates/
 * @package  affiliate-for-woocommerce/templates/my-account/dashboard/
 * @since    8.5.0
 * @version  1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Note: We do not recommend removing existing id & classes in HTML.
?>
<div id="afwc_kpi_section_wrapper">
	<div class="afwc-kpi-row">
		<div class="afwc-kpi-box">
			<div class="afwc-kpi-number">
				<?php echo ! empty( $visitors['visitors'] ) ? esc_html( $visitors['visitors'] ) : 0; ?>
			</div>
			<div class="afwc-kpi-title">
				<?php echo esc_html_x( 'Visitors', 'Label for visitor count in my account', 'affiliate-for-woocommerce' ); ?>
			</div>
		</div>
		<svg class="afwc-kpi-separator" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
			<path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"></path>
		</svg>
		<div class="afwc-kpi-box">
			<div class="afwc-kpi-number">
				<?php echo ! empty( $customers_count['customers'] ) ? esc_html( $customers_count['customers'] ) : 0; ?>
			</div>
			<div class="afwc-kpi-title">
				<?php echo esc_html_x( 'Customers', 'Label for number of customers in my account', 'affiliate-for-woocommerce' ); ?>
			</div>
		</div>
		<svg class="afwc-kpi-separator" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
			<path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"></path>
		</svg>
		<div class="afwc-kpi-box">
			<div class="afwc-kpi-number">
				<?php echo esc_html( number_format( ( ( ! empty( $visitors['visitors'] ) ) ? ( intval( $customers_count['customers'] ) * 100 / intval( $visitors['visitors'] ) ) : 0 ), 2 ) ) . '%'; ?>
			</div>
			<div class="afwc-kpi-title">
				<?php echo esc_html_x( 'Conversion', 'Label for conversion in my account', 'affiliate-for-woocommerce' ); ?>
			</div>
		</div>
	</div>
	<div class="afwc-kpi-row">
		<div class="afwc-kpi-box">
			<div class="afwc-kpi-number">
				<?php echo wp_kses_post( wc_price( ! empty( $kpis['sales'] ) ? floatval( $kpis['sales'] ) : 0 ) ); ?>
			</div>
			<div class="afwc-kpi-title">
				<?php echo esc_html_x( 'Revenue', 'Label for number of sales in my account', 'affiliate-for-woocommerce' ); ?>
			</div>
		</div>
		<svg class="afwc-kpi-separator" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
			<path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"></path>
		</svg>
		<div class="afwc-kpi-box">
			<div class="afwc-kpi-number">
				<?php echo wp_kses_post( wc_price( $gross_commission ) ); ?>
			</div>
			<div class="afwc-kpi-title">
				<?php echo esc_html_x( 'Gross Commission', 'Label for gross commissions in my account', 'affiliate-for-woocommerce' ); ?>
			</div>
		</div>
		<svg class="afwc-kpi-separator" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
			<path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"></path>
		</svg>
		<div class="afwc-kpi-box">
			<div class="afwc-kpi-number">
				<?php echo wp_kses_post( wc_price( $net_commission ) ); ?>
			</div>
			<div class="afwc-kpi-title">
				<?php echo esc_html_x( 'Net Commission', 'Label for net commission in my account', 'affiliate-for-woocommerce' ); ?>
			</div>
		</div>
	</div>
	<div class="afwc-kpi-row">
		<div class="afwc-kpi-box">
			<div class="afwc-kpi-number">
				<?php echo wp_kses_post( wc_price( ! empty( $kpis['paid_commission'] ) ? floatval( $kpis['paid_commission'] ) : 0 ) ); ?>
			</div>
			<div class="afwc-kpi-title">
				<?php echo esc_html_x( 'Paid Commissions', 'Label for paid commissions in my account', 'affiliate-for-woocommerce' ); ?>
			</div>
		</div>
		<svg class="afwc-kpi-separator" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
			<path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"></path>
		</svg>
		<div class="afwc-kpi-box">
			<div class="afwc-kpi-number">
				<?php echo wp_kses_post( wc_price( ! empty( $kpis['unpaid_commission'] ) ? floatval( $kpis['unpaid_commission'] ) : 0 ) ); ?>
			</div>
			<div class="afwc-kpi-title">
				<?php echo esc_html_x( 'Unpaid Commissions', 'Label for unpaid commissions in my account', 'affiliate-for-woocommerce' ); ?>
			</div>
		</div>
	</div>
</div>
<?php
