/* phpcs:ignoreFile */
jQuery(function(){
	const { _x, sprintf } = wp.i18n;
	let homeURL          = afwcProfileParams.homeURL || '';
	let pName            = afwcProfileParams.pName;
	let isPrettyReferral = afwcProfileParams.isPrettyReferralEnabled || 'no';

	jQuery('#afwc_resources_wrapper').on('change, keyup', '#afwc_affiliate_link', afwcGenerateLink);
	window.innerWidth && jQuery('table.afwc_coupons').toggleClass( 'woocommerce-table shop_table shop_table_responsive my_account_orders', window.innerWidth < 760);

	jQuery('#afwc_account_form').on('submit', function(e) {
		e.preventDefault();
		if( ! afwcProfileParams.saveAccountDetailsURL ) {
			return;
		}

		jQuery('#afwc_save_account_button').css('pointer-events', 'none');
		document.querySelector('.afwc_save_account_status')?.classList.remove('afwc_status_yes', 'afwc_status_no'), document.querySelector('.afwc_save_account_status')?.classList.add('afwc_status_spinner');
		jQuery('.afwc-account-save-response-msg').html('');

		let formData = jQuery(this).serialize();

		jQuery.ajax({
			url: afwcProfileParams.saveAccountDetailsURL,
			type: 'post',
			dataType: 'json',
			data: {
				security: afwcProfileParams.saveAccountSecurity || '',
				form_data: decodeURIComponent(formData)
			},
			success: function(response) {
				if(response.success) {
					if('yes' === response.success) {
						setPayoutMethodSetMessage(response.success);
					} else if('no' === response.success) {
						setPayoutMethodSetMessage(response.success, response.message);
					}
				}
				jQuery('#afwc_save_account_button').css('pointer-events', '').trigger('focusout');
			}
		});
	});

	function setPayoutMethodSetMessage(successStatus = '', message = '', timeout = 10000) {
		let selectedPayoutMethod = document.querySelector('#afwc_payout_method')?.value || '';
		let statusElement = document.querySelector('.afwc_save_account_status');
		let responseMessageElement = document.querySelector('.afwc-account-save-response-msg');

		if(!statusElement || !responseMessageElement) {
			return;
		}

		if('yes' === successStatus) {
			statusElement.classList.remove('afwc_status_spinner');
			statusElement.classList.add('afwc_status_yes');

			let selectedMethodText = document.querySelector(`select[name="afwc_payout_method"] option[value="${selectedPayoutMethod}"]`)?.textContent || '';
			responseMessageElement.innerHTML = selectedPayoutMethod
				? sprintf(
					/* translators: %s: payout method name */
					_x('Successfully saved and set "%s" as default payout method.', 'Success message after affiliate save default payout method', 'affiliate-for-woocommerce'),
					selectedMethodText
				)
				: _x('Successfully removed the payout method', 'Success message for payout method setting update', 'affiliate-for-woocommerce');

			setTimeout(() => {
				responseMessageElement.innerHTML = '';
				statusElement.classList.remove('afwc_status_yes');
			}, timeout);
		} else if('no' === successStatus) {
			statusElement.classList.remove('afwc_status_spinner');
			statusElement.classList.add('afwc_status_no');
			responseMessageElement.innerHTML = message || _x('An error occurred while saving the payout method', 'Error message for payout method setting update', 'affiliate-for-woocommerce');
		}
	}

	document.querySelector('#afwc_payout_method')?.addEventListener('change', () => {
		document.querySelector('.afwc_save_account_status')?.classList.remove('afwc_status_yes', 'afwc_status_no');
		jQuery('.afwc-account-save-response-msg').html('');
	});

	checkStripeConnection();
	function checkStripeConnection() {
		if('yes' === afwcProfileParams.stripeJustConnected) {
			setPayoutMethodSetMessage('yes');
		}
	}

	jQuery('#afwc_resources_wrapper').on( 'click', '#afwc_change_identifier', function( e ) {
		e.preventDefault();
		jQuery('#afwc_id_change_wrap, #afwc_id_save_wrap').toggle();
	});

	// If affiliate identifier change is canceled.
	jQuery('#afwc_resources_wrapper').on( 'click', '#afwc_cancel_change_identifier', function( e ) {
		e.preventDefault();
		jQuery('#afwc_ref_url_id').val( jQuery('#afwc_id_change_wrap').find('code').text() || ''); // Revert the input value.
		jQuery('#afwc_id_change_wrap').show();
		jQuery('#afwc_id_save_wrap').hide();
		jQuery('#afwc_id_msg').hide();
	});

	jQuery('#afwc_resources_wrapper').on( 'click', '#afwc_save_identifier', function( e ) {
		e.preventDefault();
		jQuery( '#afwc_id_msg' ).hide();
		let savedIdentifier    = afwcProfileParams.savedAffiliateIdentifier;
		let referralIdentifier = jQuery('#afwc_ref_url_id').val() || '';
		let lastIdentifier     = jQuery('#afwc_ref_url_id').attr('data-last_value') || savedIdentifier;

		if ( afwcProfileParams.saveReferralURLIdentifier ) {
			if ( '' == referralIdentifier ) {
				jQuery( '#afwc_id_msg' ).html( _x( 'Identifier cannot be empty.', 'referral identifier validation message', 'affiliate-for-woocommerce' ) ).addClass( 'afwc_error' ).show();
				return;
			} else {
				if ( lastIdentifier === referralIdentifier ) {
					jQuery( '#afwc_id_msg' ).html( _x( 'You are already using this identifier.', 'referral identifier validation message', 'affiliate-for-woocommerce' ) ).addClass( 'afwc_error' ).show();
					return;
				}

				if ( false === new RegExp(afwcProfileParams.identifierRegexPattern || '', 'g').test(referralIdentifier) ) {
					jQuery('#afwc_id_msg').html(afwcProfileParams.identifierPatternValidationErrorMessage || '').addClass('afwc_error').show();
					return;
				}

				jQuery('#afwc_save_id_loader').show();
				// Ajax call to save ID.
				jQuery.ajax({
					url: afwcProfileParams.saveReferralURLIdentifier,
					type: 'post',
					dataType: 'json',
					data: {
						security: afwcProfileParams.saveIdentifierSecurity || '',
						ref_url_id: referralIdentifier
					},
					success: function( response ) {
						jQuery('#afwc_save_id_loader').hide();
						if ( response.success ) {
							if ( 'yes' === response.success ) {
								jQuery('#afwc_id_change_wrap, #afwc_id_save_wrap').toggle();
								if( response.message ) {
									jQuery( '#afwc_id_msg' ).html( response.message ).addClass( 'afwc_success' ).removeClass( 'afwc_error' ).show();
								}
								if( jQuery('#afwc_id_change_wrap').length > 0 ) {
									jQuery('#afwc_id_change_wrap').find('code').text(referralIdentifier);
								}
								if( jQuery('.afwc_ref_id_span').length > 0 ) {
									jQuery('.afwc_ref_id_span').text(referralIdentifier);
								}
								let affiliateLinkElement = jQuery('#afwc_affiliate_link_label, .afwc-affiliate-ref-url-label');
								if( affiliateLinkElement.length > 0 && homeURL ) {
									let refURL = afwcGetAffiliateURL(affiliateLinkElement.attr('data-redirect') || homeURL);
									affiliateLinkElement.text(refURL).attr('data-ctp', refURL);
								}
								afwcGenerateLink();
								jQuery('#afwc_ref_url_id').attr('data-last_value', jQuery('#afwc_ref_url_id').val());
							} else if ( 'no' === response.success && response.message ) {
								jQuery( '#afwc_id_msg' ).html( response.message ).addClass( 'afwc_error' ).removeClass( 'afwc_success' ).show();
							}
						}
						setTimeout( function(){ jQuery( '#afwc_id_msg' ).hide(); }, 10000);
					}
				});
			}
		}
	})

	// Stripe connect.
	init_connect_button();
	function init_connect_button() {
		jQuery('#afwc_stripe_connect_button').on('click', function (e) {
			if (jQuery(this).hasClass('afwc_stripe_disconnect')) {
				e.preventDefault();
				jQuery('.stripe-connect').block({message: null, overlayCSS: {background: "#fff", opacity: .6}});

				let stripeConnectLink = jQuery(this);
				let stripeConnectLinkWrapper = stripeConnectLink.parent();
				let stripeConnectLoader = jQuery('.afwc-stripe-connect-loader');

				stripeConnectLink.css('pointer-events', 'none');
				stripeConnectLinkWrapper.css('cursor', 'not-allowed');
				stripeConnectLoader.show();

				var options = {
					action: afwcProfileParams.disconnectStripeConnectAction
				};

				jQuery.post(afwcProfileParams.ajaxURL, options).done(function (data) {
					var sc = jQuery('.stripe_connect');
					sc.unblock();
					if (data['disconnected']) {
						sc.removeClass('afwc_stripe_disconnect');
						sc.attr('href', afwcProfileParams.oauthLink);

						jQuery('.message').text('');
						jQuery('.stripe_connect>span').text(_x( 'Connect with Stripe', 'Stripe Connect message', 'affiliate-for-woocommerce' ));
					} else {
						jQuery('.message').text(data['message']);
						jQuery('.stripe_connect>span').text(_x( 'Disconnect from Stripe', 'Stripe Disconnect message', 'affiliate-for-woocommerce' ));
					}

					stripeConnectLink.css('pointer-events', '');
					stripeConnectLinkWrapper.css('cursor', '');
					stripeConnectLoader.hide();
				}).fail(function (jqXHR, textStatus, errorThrown) {
					console.log(errorThrown + ": " + textStatus + ": " + jqXHR.responseText);
				});
			}
		});
	}

	function afwcGetAffiliateURL(targetURL = ''){
		if(!targetURL){
			return '';
		}
		targetURL = targetURL.replace(/\/$/, "");
		let affiliateIdentifier   = jQuery('#afwc_id_change_wrap code').text();
		let generatedLink = '';

		if ( -1 === targetURL.indexOf( '?' ) ) {
			generatedLink = targetURL + ( 'yes' == isPrettyReferral ? ( '/' + pName + '/' + affiliateIdentifier + '/' ) : ( '/?'+ pName + '=' + affiliateIdentifier ) );
		} else {
			if ( 'yes' == isPrettyReferral ) {
				generatedLink = ( targetURL.substring( 0, targetURL.indexOf('?') ) ).replace(/\/$/, "") + '/' + pName + '/' + affiliateIdentifier + '/?'+ ( targetURL.substring( targetURL.indexOf('?') + 1 ) );
			} else {
				generatedLink = targetURL + '&' + pName+'='+affiliateIdentifier;
			}
		}

		return generatedLink;
	}

	function afwcGenerateLink(){
		let path                  = jQuery('#afwc_affiliate_link').val() || '';
		affiliateReferralLink = homeURL ? afwcGetAffiliateURL( homeURL + path ) : '';
		jQuery('#afwc_generated_affiliate_link').text(affiliateReferralLink).attr('data-ctp', affiliateReferralLink);
	}

	const AFWCBlockVisibility = {
		selectElement: null,
		blocks: null,
		dataAttr: '',

		init(selectElementId = '', dataAttr = '') {
			this.dataAttr = dataAttr || ''
			this.selectElement = document.getElementById(selectElementId);
			this.blocks = document.querySelectorAll(`[${this.dataAttr}]`);
			this.selectElement.addEventListener('change', () => this.updateBlockVisibility());
			this.updateBlockVisibility();
		},

		updateBlockVisibility() {
			const selectedMethod = this.selectElement.value || '';
			this.blocks && this.blocks.forEach(block => block.style.display = 'none');
			if (selectedMethod) {
				this.showSelectedBlocks(selectedMethod);
			}
		},

		showSelectedBlocks(method = '') {
			const selectedBlocks = document.querySelectorAll(`[${this.dataAttr}="${method}"]`);
			selectedBlocks && selectedBlocks.forEach(block => block.style.display = 'block');
		}
	};

	AFWCBlockVisibility.init('afwc_payout_method', 'data-payout-method');

	const payoutSaveBtnHandler = {
		payoutMethodSelection: document.getElementById('afwc_payout_method'),
		payoutSectionSaveBtn: document.getElementById('afwc_save_account_button'),

		init() {
			if (this.payoutMethodSelection) {
				this.payoutMethodSelection.addEventListener('change', (e) => this.handlePayoutMethodChange(e));
				this.updateSaveBtn(this.payoutMethodSelection?.value || '');
			}
		},

		handlePayoutMethodChange(e) {
			this.updateSaveBtn(e?.target?.value || '');
		},

		updateSaveBtn(val = '') {
			if (!val) {
				this.payoutSectionSaveBtn.style.display = 'none';
			} else if ('stripe' === val) {
				this.payoutSectionSaveBtn.style.display = 'inline-block';
				this.payoutSectionSaveBtn.disabled = true;
			} else {
				this.payoutSectionSaveBtn.style.display = 'inline-block';
				this.payoutSectionSaveBtn.disabled = false;
			}
		}
	};

	payoutSaveBtnHandler.init();
});
