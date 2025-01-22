<?php
/**
 * My Account > Affiliate > Reports
 *
 * @see      This template can be overridden by: https://woocommerce.com/document/affiliate-for-woocommerce/how-to-override-templates/
 * @package  affiliate-for-woocommerce/templates/my-account/
 * @since    8.5.0
 * @version  1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Note: We do not recommend removing existing id & classes in HTML.
?>
<div id="afwc_dashboard_wrapper">
	<?php
	do_action(
		'afwc_my_account_header',
		array(
			'title'          => _x( 'Referrals', 'Title for referral dashboard', 'affiliate-for-woocommerce' ),
			'dashboard_link' => ! empty( $dashboard_link ) ? $dashboard_link : '',
			'from'           => ! empty( $date_range['from'] ) ? $date_range['from'] : '',
			'to'             => ! empty( $date_range['to'] ) ? $date_range['to'] : '',
		)
	);

	do_action(
		'afwc_referral_table',
		array(
			'from'           => ! empty( $date_range['from'] ) ? $date_range['from'] : '',
			'to'             => ! empty( $date_range['to'] ) ? $date_range['to'] : '',
			'dashboard_link' => ! empty( $dashboard_link ) ? $dashboard_link : '',
			'table_footer'   => true,
		)
	);
	?>
</div>
<?php
