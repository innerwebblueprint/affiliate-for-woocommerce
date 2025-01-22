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
<table class="afwc_payout_history afwc-reports afwc-payouts-table">
	<thead>
		<?php
		if ( ! empty( $payout_headers ) && is_array( $payout_headers ) ) {
			echo '<tr>';
			foreach ( $payout_headers as $key => $payout_header ) {
				?>
				<th class="<?php esc_attr( $key ); ?>"><?php echo esc_html( $payout_header ); ?></th>
				<?php
			}
			echo '</tr>';
		}
		?>
	</thead>
	<tbody>
		<?php if ( ! empty( $payouts['payouts'] ) && is_array( $payouts['payouts'] ) && ! empty( $payout_headers ) && is_array( $payout_headers ) ) { ?>
			<?php
			foreach ( $payouts['payouts'] as $payout ) {
				echo '<tr>';
				foreach ( $payout_headers as $key => $payout_header ) {
					if ( 'invoice' === $key ) {
						?>
						<td
							class="<?php echo esc_attr( $key ); ?>"
							data-title="<?php echo esc_attr( $payout_header ); ?>"
							data-payout_id="<?php echo ! empty( $payout['payout_id'] ) ? esc_attr( $payout['payout_id'] ) : 0; ?>"
							data-affiliate_id="<?php echo esc_attr( $affiliate_id ); ?>"
							data-datetime="<?php echo ! empty( $payout['datetime'] ) ? esc_attr( $payout['datetime'] ) : ''; ?>"
							data-from_period="<?php echo ! empty( $payout['from_date'] ) ? esc_attr( $payout['from_date'] ) : ''; ?>"
							data-to_period="<?php echo ! empty( $payout['to_date'] ) ? esc_attr( $payout['to_date'] ) : ''; ?>"
							data-referral_count="<?php echo ! empty( $payout['referral_count'] ) ? esc_attr( $payout['referral_count'] ) : 0; ?>"
							data-amount="<?php echo ! empty( $payout['amount'] ) ? esc_attr( $payout['amount'] ) : 0; ?>"
							data-currency="<?php echo ! empty( $payout['currency'] ) ? esc_attr( $payout['currency'] ) : ''; ?>"
							data-method="<?php echo ! empty( $payout['method'] ) ? esc_attr( $payout['method'] ) : ''; ?>"
							data-notes="<?php echo ! empty( $payout['payout_notes'] ) ? esc_attr( $payout['payout_notes'] ) : ''; ?>"
						>
							<a class="print-invoice" title="<?php echo esc_attr_x( 'Print', 'title for printing the invoice', 'affiliate-for-woocommerce' ); ?>">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5">
									<path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"></path>
								</svg>
							</a>
						</td>
						<?php
					} else {
						?>
						<td class="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $payout_header ); ?>">
							<?php echo ! empty( $payout[ $key ] ) ? wp_kses_post( $payout[ $key ] ) : ''; ?>
						</td>
						<?php
					}
				}
				echo '</tr>';
				?>
			<?php } ?>
		<?php } else { ?>
			<tr>
				<td class="empty-table" colspan="4">
					<?php echo esc_html_x( 'No data to display', 'message to show when no payouts data', 'affiliate-for-woocommerce' ); ?>
				</td>
			</tr>
		<?php } ?>
	</tbody>
	<?php if ( ! empty( $table_footer ) && true === $table_footer ) { ?>
	<tfoot>
		<tr>
			<td colspan="4">
				<div class="afwc-table-footer-container">
					<a class="afwc-back-button-wrapper" href="<?php echo esc_attr( esc_url( $dashboard_link ) ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
							<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
						</svg>
						<span>
							<?php echo esc_html_x( 'back to dashboard', 'Link to affiliate my account dashboard', 'affiliate-for-woocommerce' ); ?>
						</span>
					</a>
					<?php if ( ! empty( $payouts['total_count'] ) && ! empty( $payouts['payouts'] ) && ( intval( $payouts['total_count'] ) > count( $payouts['payouts'] ) ) ) { ?>
						<span class="afwc-payouts-load-more-wrapper">
							<span class="afwc-loader" style="display: none;">
								<img src="<?php echo esc_url( WC()->plugin_url() ) . '/assets/images/wpspin-2x.gif'; ?>" class="afwc-table-loader" />
								<span><?php echo esc_html_x( 'Loading...', 'Payout table load more loading text', 'affiliate-for-woocommerce' ); ?></span>
							</span>
							<a id="afwc_load_more_payouts" class="afwc-load-more-text" data-max_record="<?php echo esc_attr( intval( $payouts['total_count'] ) ); ?>">
								<span><?php echo esc_html_x( 'Load more', 'Payout load more link text in my account', 'affiliate-for-woocommerce' ); ?></span>
							</a>
							<span class="afwc-no-load-more-text" style="display: none;"><?php echo esc_html_x( 'No more data to load', 'Text for no data to load', 'affiliate-for-woocommerce' ); ?></span>
						</span>
					<?php } ?>
				</div>
			</td>
		</tr>
	</tfoot>
	<?php } ?>
</table>
<?php
