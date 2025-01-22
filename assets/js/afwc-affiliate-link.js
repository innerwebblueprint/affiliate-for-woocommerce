/* phpcs:ignoreFile */
jQuery(function(){
	"use strict";
	const { _x } = wp.i18n;

	const productAffiliateLink = {
		singleProductAffiliateLink: '',
		init() {
			this.singleProductAffiliateLink = jQuery('.single-product-affiliate-link');
			jQuery('form.variations_form').on('hide_variation', this.resetAffiliateLink.bind(this)).on('found_variation', this.refreshAffiliateLink.bind(this));
			jQuery('.single-product-affiliate-link.afwc-click-to-copy, table.afwc_coupons td .afwc-click-to-copy').on('copied', this.afterReferralLinkCopy);
		},
		resetAffiliateLink() {
			this.singleProductAffiliateLink.attr('href', this.singleProductAffiliateLink.attr('data-product-referral-link'));
			this.singleProductAffiliateLink.show().removeClass('disabled');
		},
		refreshAffiliateLink(e, variation) {
			e.preventDefault();
			this.singleProductAffiliateLink.addClass('disabled');
			if (undefined === variation || 'object' !== typeof variation) {
				return;
			}
			if (undefined === variation.variation_id || 0 === parseInt(variation.variation_id)) {
				return;
			}
			let variationId = parseInt(variation.variation_id);
			jQuery.ajax({
				url: afwcAffiliateLinkParams.product.ajaxURL || '',
				type: 'POST',
				dataType: 'json',
				data: {
					product_id: variationId,
					security: afwcAffiliateLinkParams.product.security || '',
				},
				success: res => {
					if (res && res.success) {
						this.singleProductAffiliateLink.attr({
							'href': res.data.url || '',
							'data-ctp': res.data.url || ''
						});
						(res.data && res.data.url) ? this.singleProductAffiliateLink.show().removeClass('disabled') : this.singleProductAffiliateLink.hide();
					}
				},
				error: err => {
					console.log('Cannot get the product\'s affiliate link: ', err);
				}
			})
		},
		afterReferralLinkCopy(e){
			if(jQuery(e.target).hasClass('disabled')){
				return;
			}
			const originalText = jQuery(e.target).text();
			jQuery(e.target)
			.text(_x('Copied', 'Success message after copying', 'affiliate-for-woocommerce'))
			.addClass('disabled');
			setTimeout(() => {
				jQuery(e.target).text(originalText).removeClass('disabled');;
			}, 1000);
		}
	};

	// Initialization
	productAffiliateLink.init();
});
