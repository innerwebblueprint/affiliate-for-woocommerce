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
<table class="afwc_products afwc-reports afwc-products-table">
	<thead>
		<?php
		if ( ! empty( $product_headers ) && is_array( $product_headers ) ) {
			echo '<tr>';
			foreach ( $product_headers as $key => $product_header ) {
				?>
				<th class="<?php esc_attr( $key ); ?>"><?php echo esc_html( $product_header ); ?></th>
				<?php
			}
			echo '</tr>';
		}
		?>
	</thead>
	<tbody>
		<?php if ( ! empty( $products['rows'] ) && is_array( $products['rows'] ) && ! empty( $product_headers ) && is_array( $product_headers ) ) { ?>
			<?php
			foreach ( $products['rows'] as $product ) {
				echo '<tr>';
				foreach ( $product_headers as $key => $product_header ) {
					?>
					<td class="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $product_header ); ?>">
						<?php echo ! empty( $product[ $key ] ) ? wp_kses_post( $product[ $key ] ) : ''; ?>
					</td>
					<?php
				}
				echo '</tr>';
				?>
			<?php } ?>
		<?php } else { ?>
			<tr>
				<td class="empty-table" colspan="3">
					<?php echo esc_html_x( 'No data to display', 'message to show when no products data', 'affiliate-for-woocommerce' ); ?>
				</td>
			</tr>
		<?php } ?>
	</tbody>
	<?php if ( ! empty( $table_footer ) && true === $table_footer ) { ?>
	<tfoot>
		<tr>
			<td colspan="3">
				<div class="afwc-table-footer-container">
					<a class="afwc-back-button-wrapper" href="<?php echo esc_attr( esc_url( $dashboard_link ) ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
							<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
						</svg>
						<span>
							<?php echo esc_html_x( 'back to dashboard', 'Link to affiliate my account dashboard', 'affiliate-for-woocommerce' ); ?>
						</span>
					</a>
					<?php if ( ! empty( $products['rows'] ) && ! empty( $products['total_count'] ) && ( intval( $products['total_count'] ) > count( $products['rows'] ) ) ) { ?>
						<span class="afwc-products-load-more-wrapper">
							<span class="afwc-loader" style="display: none;">
								<img src="<?php echo esc_url( WC()->plugin_url() ) . '/assets/images/wpspin-2x.gif'; ?>" class="afwc-table-loader" />
								<span><?php echo esc_html_x( 'Loading...', 'Products table load more loading text', 'affiliate-for-woocommerce' ); ?></span>
							</span>
							<a id="afwc_load_more_products" class="afwc-load-more-text"  data-max_record="<?php echo esc_attr( intval( $products['total_count'] ) ); ?>">
								<span><?php echo esc_html_x( 'Load more', 'Products load more link text in my account', 'affiliate-for-woocommerce' ); ?></span>
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
