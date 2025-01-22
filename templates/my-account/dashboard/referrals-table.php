<?php
/**
 * My Account > Affiliate > Reports
 *
 * @see      This template can be overridden by: https://woocommerce.com/document/affiliate-for-woocommerce/how-to-override-templates/
 * @package  affiliate-for-woocommerce/templates/my-account/dashboard/
 * @since    8.5.0
 * @version  1.1.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Note: We do not recommend removing existing id & classes in HTML.

$referrals_colspan = ( true === $is_show_customer_column ) ? 4 : 3;
?>
<table class="afwc_referrals afwc-reports afwc-referrals-table">
	<thead>
		<?php
		if ( ! empty( $referral_headers ) && is_array( $referral_headers ) ) {
			echo '<tr>';
			foreach ( $referral_headers as $key => $referral_header ) {
				?>
				<th class="<?php esc_attr( $key ); ?>"><?php echo esc_html( $referral_header ); ?></th>
				<?php
			}
			echo '</tr>';
		}
		?>
	</thead>
	<tbody>
		<?php if ( ! empty( $referrals['rows'] ) && is_array( $referrals['rows'] ) && ! empty( $referral_headers ) && is_array( $referral_headers ) ) { ?>
			<?php
			foreach ( $referrals['rows'] as $referral ) {
				echo '<tr>';
				foreach ( $referral_headers as $key => $referral_header ) {
					if ( 'customer_name' === $key ) {
						$customer_name = ! empty( $referral[ $key ] ) ? ( ( mb_strlen( $referral[ $key ] ) > 20 ) ? mb_substr( $referral[ $key ], 0, 19 ) . '...' : $referral[ $key ] ) : '';
						?>
						<td class="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $referral_header ); ?>" title="<?php echo ( ! empty( $referral[ $key ] ) ) ? esc_html( $referral[ $key ] ) : ''; ?>">
							<?php echo esc_html( $customer_name ); ?>
						</td>
						<?php
					} elseif ( 'status' === $key ) {
						$referral_status = ( ! empty( $referral[ $key ] ) ) ? $referral[ $key ] : '';
						?>
						<td class="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $referral_header ); ?>">
							<div class="<?php echo esc_attr( 'text_' . ( ! empty( $referral_status ) ? afwc_get_commission_status_colors( $referral_status ) : '' ) ); ?>">
								<?php echo esc_html( ( ! empty( $referral_status ) ) ? afwc_get_commission_statuses( $referral_status ) : '' ); ?>
							</div>
						</td>
						<?php
					} elseif ( 'campaign' === $key ) {
						$campaign_id = ( ! empty( $referral[ $key ] ) ) ? $referral[ $key ] : '';
						?>
						<td class="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $referral_header ); ?>">
							<?php
							if ( ! empty( $referral['campaign_title'] ) ) {
								?>
									<a href="<?php echo esc_attr( ! empty( $campaign_link ) ? ( $campaign_link . $campaign_id ) : '#' ); ?>" title="<?php echo esc_html( $referral['campaign_title'] ); ?>" target="_blank">
										<?php echo esc_html( '#' . $campaign_id ); ?>
									</a>
								<?php } elseif ( ! empty( $campaign_id ) ) { ?>
									<span title="<?php echo ! empty( $referral['is_campaign_deleted'] ) ? esc_attr_x( 'Deleted', 'Deleted campaign ID in referral table', 'affiliate-for-woocommerce' ) : ''; ?>"><?php echo esc_html( '#' . $campaign_id ); ?> </span>
								<?php } else { ?>
									- 
								<?php } ?>
						</td>
					<?php } else { ?>
						<td class="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $referral_header ); ?>">
							<?php echo ! empty( $referral[ $key ] ) ? wp_kses_post( $referral[ $key ] ) : ''; ?>
						</td>
					<?php } ?>
					<?php
				}
				echo '</tr>';
				?>
			<?php } ?>
		<?php } else { ?>
			<tr>
				<td class="empty-table" colspan="<?php echo esc_attr( $referrals_colspan ); ?>">
					<?php echo esc_html_x( 'No data to display', 'message to show when no referrals data', 'affiliate-for-woocommerce' ); ?>
				</td>
			</tr>
		<?php } ?>
	</tbody>
	<?php if ( ! empty( $table_footer ) && true === $table_footer ) { ?>
	<tfoot>
		<tr>
			<td colspan="<?php echo esc_attr( $referrals_colspan ); ?>">
				<div class="afwc-table-footer-container">
					<a class="afwc-back-button-wrapper" href="<?php echo esc_attr( esc_url( $dashboard_link ) ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
							<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
						</svg>
						<span>
							<?php echo esc_html_x( 'back to dashboard', 'Link to affiliate my account dashboard', 'affiliate-for-woocommerce' ); ?>
						</span>
					</a>
					<?php if ( ! empty( $referrals['total_count'] ) && ! empty( $referrals['rows'] ) && ( intval( $referrals['total_count'] ) > count( $referrals['rows'] ) ) ) { ?>
						<span class="afwc-referrals-load-more-wrapper">
							<span class="afwc-loader" style="display: none;">
								<img src="<?php echo esc_url( WC()->plugin_url() ) . '/assets/images/wpspin-2x.gif'; ?>" class="afwc-table-loader" />
								<span><?php echo esc_html_x( 'Loading...', 'Referrals table load more loading text', 'affiliate-for-woocommerce' ); ?></span>
							</span>
							<a id="afwc_load_more_referrals" class="afwc-load-more-text"  data-max_record="<?php echo esc_attr( $referrals['total_count'] ); ?>">
								<span><?php echo esc_html_x( 'Load more', 'Referrals load more link text in my account', 'affiliate-for-woocommerce' ); ?></span>
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
