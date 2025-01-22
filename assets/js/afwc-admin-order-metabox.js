/* phpcs:ignoreFile */
jQuery(function(){
	jQuery(document).on('change', '#afwc_referral_order_of', function() {
		jQuery(document).find('.options_group.afwc-field > div, .options_group.afwc-field > hr').not('.afwc-link-unlink-affiliate-section').hide();
	});
});
