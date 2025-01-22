/* phpcs:ignoreFile */
jQuery(function(){
	const { _x } = wp.i18n;

	const afwcDashboard = {
		init: function () {
			this.dashboardWrapper = jQuery('#afwc_dashboard_wrapper');
			this.affiliateId = afwcDashboardParams.affiliateId || 0;
			window.innerWidth && jQuery('.afwc_products, .afwc_referrals, .afwc_payout_history').toggleClass( 'woocommerce-table shop_table shop_table_responsive my_account_orders', window.innerWidth < 760);
			jQuery('body').on('click', '#afwc_load_more_products', this.loadMore.bind(this, 'products', { url: afwcDashboardParams.products.ajaxURL || '', nonce: afwcDashboardParams.products.nonce }));
			jQuery('body').on('click', '#afwc_load_more_referrals', this.loadMore.bind(this, 'referrals', { url: afwcDashboardParams.referrals.ajaxURL || '', nonce: afwcDashboardParams.referrals.nonce }));
			jQuery('body').on('click', '#afwc_load_more_payouts', this.loadMore.bind(this, 'payouts', { url: afwcDashboardParams.payouts.ajaxURL || '', nonce: afwcDashboardParams.payouts.nonce }));
			jQuery('body').on('focusin', '#afwc_from, #afwc_to', (e) => this.loadDatepicker(e));

			document.body.addEventListener('change', (e) => {
				let id = e?.target?.id || ''
				if ('afwc_from' === id || 'afwc_to' === id ) {
				  this.datesOnChange();
				}
			});

			document.body.addEventListener('click', (e) => this.toggleDateFilters(
				e?.target?.id === 'afwc-smart-dates-dropdown-icon'
			));
		},
		setStartDate: (date) => document.getElementById('afwc_from').value = date,
		setEndDate: (date) => document.getElementById('afwc_to').value = date,
		getStartDate: () => document.getElementById('afwc_from').value || '',
		getEndDate: () => document.getElementById('afwc_to').value || '',
		loadMore: function (section = '', ajaxArgs = {}, e = {}) {
			e.preventDefault();
			let loadMoreSection = jQuery(`.afwc-${section}-load-more-wrapper`) || {};
			let loadMoreButton = jQuery(`#afwc_load_more_${section}`);
			let theTable = jQuery(`.afwc-${section}-section`).find('table').length > 0 ? jQuery(`.afwc-${section}-section`).find('table') : jQuery(`table.afwc-${section}-table`);
			let dateRange = this.getFormattedDateRange();
			jQuery(`#afwc_load_more_${section}`).addClass('disabled');
			theTable.addClass('afwc-loading');
			loadMoreSection.find('.afwc-load-more-text').hide();
			loadMoreSection.find('.afwc-loader').show();
			this.ajaxCall({
				url: ajaxArgs.url || '',
				type: 'post',
				dataType: 'html',
				data: {
					security: ajaxArgs.nonce || '',
					from: dateRange.from || '',
					to: dateRange.to || '',
					offset: theTable.find('tbody tr').length || 0,
					affiliate: this.affiliateId,
					current_url: window?.location?.href || ''
				},
				success: function (response) {
					if (response) {
						theTable.find('tbody').append(response);
						theTable.removeClass('afwc-loading');
						loadMoreSection.find('.afwc-load-more-text').show();
						loadMoreSection.find('.afwc-loader').hide();
						let maxRecord = parseInt( jQuery(`#afwc_load_more_${section}`).data('max_record') ) || 0;
						if (theTable.find('tbody tr').length >= maxRecord) {
							loadMoreButton.addClass('disabled');
							if(loadMoreSection.find('.afwc-no-load-more-text').length) {
								loadMoreSection.find('.afwc-no-load-more-text').show()
								loadMoreButton.hide()
							} else {
								loadMoreButton.text(_x('No more data to load', 'Text for no data to load', 'affiliate-for-woocommerce'));
							}
						} else {
							loadMoreButton.removeClass('disabled');
						}
						afwcDashboard.sectionUpdated(section);
					}
				}
			});
		},
		datesOnChange: function () {
			let startDate = this.getStartDate()
			let endDate = this.getEndDate()
			if(!startDate || !endDate){
				return;
			}
			if (!afwcDateFunctions.isValidDate([startDate, endDate],'-')) {
				document.getElementById('afwc_date_range_container').style.borderColor = "#F87171"
				return;
			}
			this.handleDateSearchChange()
		},
		handleDateFilters: function () {
			let listItems = document.getElementById('afwc-smart-dates-dropdown-list').getElementsByTagName('li')
			for (let i = 0; i < listItems.length; i++) {
				listItems[i].addEventListener('click', (e) => {
					let id = ( 0 == e.target.childElementCount ) ? e.target.parentElement.id : e.target.id
					let dates = afwcDateFunctions.getDate(id.replace("afwc_", ""))
					dates?.startDate && this.setStartDate(dates.startDate)
					dates?.endDate && this.setEndDate(dates.endDate)
					this.datesOnChange()
				})
			}
		},
		toggleDateFilters: function(show = true) {
			const hideClass = 'afwc-hidden'
			const dropDown = document.getElementById('afwc-smart-dates-dropdown-list')
			dropDown.classList.contains(hideClass) && show
				? dropDown.classList.remove(hideClass) 
				: dropDown.classList.add(hideClass)
			this.handleDateFilters();
		},
		loadDatepicker: function (e) {
			try {
				e.target.showPicker();
			}catch{
				console.warn('Cannot trigger date picker');
			}
		},
		getDatesFromDateRange: function () {
			return {
				from: this.getStartDate() || '',
				to: this.getEndDate() || '',
			}
		},
		getFormattedDateRange: function () {
			let dateRange = this.getDatesFromDateRange();
			return {
				from: dateRange.from ? afwcDateFunctions.getDateTime((dateRange.from), { dayStart: true }) : '',
				to: dateRange.to ? afwcDateFunctions.getDateTime((dateRange.to), { dayEnd: true }) : ''
			}
		},
		handleDateSearchChange: function () {
			let currentURL = window?.location?.href || '';
			this.dashboardWrapper.css('opacity', 0.5);
			let dateRange = this.getDatesFromDateRange();
			if(dateRange.from && dateRange.to) {
				let dateParams = {
					'from-date': dateRange.from,
					'to-date': dateRange.to
				}
				this.ajaxCall({
					url: afwcDashboardParams.loadAllData.ajaxURL || '',
					type: 'post',
					dataType: 'html',
					data: {
						security: afwcDashboardParams.loadAllData.nonce || '',
						user_id: this.affiliateId || 0,
						section: wp?.url?.getQueryArg?.(currentURL, 'section') || '',
						current_url: currentURL,
						...dateParams
					},
					success: function (response = '') {
						if (response) {
							this.dashboardWrapper.replaceWith(response);
							this.dashboardWrapper = jQuery('#afwc_dashboard_wrapper');
							this.dashboardWrapper.css('opacity', 1);
							this.sectionUpdated('all');
							window?.history?.replaceState?.(null, '', wp?.url?.addQueryArgs?.(currentURL, dateParams) || currentURL);
						}
					}.bind(this)
				});
			}
		},
		sectionUpdated(section = 'all'){
			if(['all', 'payouts'].includes(section) && 'function' === typeof afwcRegisterInvoicePrint){
				afwcRegisterInvoicePrint();
			}
		},
		ajaxCall: function (options = {}) {
			jQuery.ajax(options);
		}
	};

	// Initialize the dashboard object
	afwcDashboard.init();
});
