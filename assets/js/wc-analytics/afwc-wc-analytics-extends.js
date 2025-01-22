var {addFilter} = wp.hooks,
	{_x} = wp.i18n;

const AFWCOrderReport = {
	addAffiliateDetailsToOrderReport(reportTableData) {
		const { endpoint, items } = reportTableData;
		if ('orders' !== endpoint) {
			return reportTableData;
		}
		reportTableData.headers = [
			...reportTableData.headers,
			{
				label: _x('Affiliate', 'Header for affiliate name in WooCommerce orders report', 'affiliate-for-woocommerce'),
				key: 'affiliate',
			},
		];
		reportTableData.rows = reportTableData.rows.map((row, index) => {
			const item = items.data[index] || {};
			const {affiliate = '', affiliate_id = '', date_created = ''} = item;
			const startDate = date_created ? (afwcDateFunctions?.formatDate?.(new Date(date_created)) || '') : '';
			const endDate = afwcDateFunctions?.getFullDate?.() || '';
			return  [
				...row,
				{
					display: React?.createElement?.(
						'a',
						{
							href: afwcParams.dashboardLink
								? `${afwcParams.dashboardLink}#!/dashboard/${affiliate_id}/filter/from-date=${startDate}&to-date=${endDate}`
								: '',
							target: '_blank'
						},
						affiliate
					) || affiliate,
					value: affiliate,
				},
			];
		});
		return reportTableData;
	},
	init(){
		addFilter('woocommerce_admin_report_table', 'affiliate-for-woocommerce', AFWCOrderReport.addAffiliateDetailsToOrderReport);
	},
};

AFWCOrderReport.init();
