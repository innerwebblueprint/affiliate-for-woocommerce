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
<div id="afwc_top_row_container" class="afwc-top-row-wrapper">
	<?php if ( ! empty( $title ) ) { ?>
	<div class="afwc-report-heading-container">
		<div class="afwc-report-heading">
			<?php echo esc_html( $title ); ?>
		</div>
		<a class="afwc-back-button-wrapper" href="<?php echo esc_attr( esc_url( $dashboard_link ) ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
				<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
			</svg>
			<span>
				<?php echo esc_html_x( 'back to dashboard', 'Link to affiliate my account dashboard', 'affiliate-for-woocommerce' ); ?>
			</span>
		</a>
	</div>
	<?php } ?>
	<div id="afwc_date_range_container">
		<div id="afwc_datepicker_from">
			<input type="date" id="afwc_from" name="afwc_from" min="2000-01-01" max="9999-12-31"
				value="<?php echo ( ! empty( $from ) ) ? esc_attr( $from ) : ''; ?>"
				placeholder="<?php echo esc_attr_x( 'From', 'Start date for date field of my account', 'affiliate-for-woocommerce' ); ?>" />
		</div>
		<div>
			<?php echo esc_html_x( 'to', 'Separator for date field of my account', 'affiliate-for-woocommerce' ); ?>
		</div>
		<div id="afwc_datepicker_to">
			<input type="date" id="afwc_to" name="afwc_to" min="2000-01-01" max="9999-12-31"
				value="<?php echo ( ! empty( $to ) ) ? esc_attr( $to ) : ''; ?>"
				placeholder="<?php echo esc_attr_x( 'To', 'End date for date field of my account', 'affiliate-for-woocommerce' ); ?>" />
		</div>
		<div id="afwc-smart-dates-dropdown-wrapper">
			<svg id="afwc-smart-dates-dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
				<path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
			</svg>
			<?php if ( ! empty( $date_filters ) && is_array( $date_filters ) ) { ?>
				<ul id="afwc-smart-dates-dropdown-list" class="afwc-hidden">
					<?php foreach ( $date_filters as $group ) { ?>
						<?php if ( ! empty( $group ) && is_array( $group ) ) { ?>
							<?php foreach ( $group as $group_id => $label ) { ?>
								<li id="<?php echo esc_attr( "afwc_$group_id" ); ?>"><span><?php echo esc_html( $label ); ?></span></li>
							<?php } ?>
							<?php if ( end( $date_filters ) !== $group ) { ?>
								<li class="afwc-separator"></li>
							<?php } ?>
						<?php } ?>
					<?php } ?>
				</ul>
			<?php } ?>
		</div>
	</div>
</div>
<?php
