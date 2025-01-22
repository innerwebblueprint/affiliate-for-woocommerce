/* phpcs:ignoreFile */
jQuery(function( $ ){
	const { _x } = wp.i18n;

	let affiliateUserSearch = {
		init() {
			let self = affiliateUserSearch;

			$( ':input.afwc-affiliate-search' ).filter( ':not(.enhanced)' ).each( function() {
				let select2Args = self.getSelect2Args( this );
				select2Args = $.extend( select2Args, self.getEnhancedSelectFormatString() );

				$( this )
				.select2( select2Args )
				.addClass( 'enhanced' )
				.on( 'select2:selecting', function (e) {
					if ( !e?.params?.args?.data ) {
						return;
					}
					let { data } = e.params.args;
					self.affiliateConfirmationAlert(
						e,
						self.getCustomer(),
						{id: data.id || 0, displayText: data.text || ''}
					);
				});

				if ( $( this ).data( 'sortable' ) ) {
					let $select = $( this );
					let $list   = $select.next( '.select2-container' ).find( 'ul.select2-selection__rendered' );

					$list.sortable({
						placeholder : 'ui-state-highlight select2-selection__choice',
						forcePlaceholderSize: true,
						items       : 'li:not(.select2-search__field)',
						tolerance   : 'pointer',
						stop: function() {
							$( $list.find( '.select2-selection__choice' ).get().reverse() ).each( function() {
								let id     = $select.data( 'data' ).id;
								$select.prepend( $select.find( 'option[value="' + id + '"]' )[0] || '' );
							} );
						}
					});
				}
			});

			if( $( '.woocommerce-order-data' ).length > 0 ) {
				$( '#customer_user' ).on( 'select2:selecting', function(e) {
					self.affiliateConfirmationAlert( e, {id: e?.params?.args?.data?.id || 0}, self.getAffiliate() );
				});

				document.querySelector( '.edit_address #_billing_email' )?.addEventListener( 'change', (e) => {
					self.affiliateConfirmationAlert( e, {email: e?.target?.value || ''}, self.getAffiliate());
				});
			}
		},
		getSelect2Args( elem = null ) {
			if( ! elem ) {
				return {};
			}
			return {
				placeholder: $( elem ).data( 'placeholder' ),
				minimumInputLength: $( elem ).data( 'minimum_input_length' ) || 3,
				escapeMarkup: function( m ) {
					return m;
				},
				ajax: {
					url:         affiliateParams.ajaxurl,
					dataType:    'json',
					delay:       1000,
					data:        function( params ) {
						return {
							term:     params.term || '',
							action:   'afwc_json_search_affiliates',
							security: affiliateParams.security || '',
							exclude:  $( elem ).data( 'exclude' ) || []
						};
					},
					processResults: function( data ) {
						let terms = [];
						if ( data ) {
							$.each( data, function( id, text ) {
								terms.push({ id, text });
							});
						}
						return {
							results: terms
						};
					},
					cache: true
				}
			};
		},
		getAffiliate(){
			const selectElement = document.getElementById('afwc_referral_order_of') || '';
			return {
				id: selectElement?.value || 0,
				displayText: selectElement?.options[selectElement.selectedIndex]?.text || '',
			};
		},
		getCustomer(){
			return {
				id: document.getElementById('customer_user')?.value || 0,
				email: document.querySelector('.edit_address #_billing_email')?.value || '',
			};
		},
		affiliateConfirmationAlert(
			e,
			{id:customerID = 0, email: customerEmail = ''},
			{id:affiliateID = 0, displayText: affiliateDisplayText = ''},
		) {
			if ( ( true === Boolean( affiliateParams.allowSelfRefer) ) ) {
				return;
			}

			const alertMessage = _x( 'Are you sure you want to set the affiliate same as the customer? This overrides the setting Affiliate self-refer.', 'self refer alert', 'affiliate-for-woocommerce' );

			// Check for customer ID and affiliate ID.
			if( customerID && affiliateID
				&& ( parseInt( customerID ) === parseInt( affiliateID ) ) 
				&& ( !confirm( alertMessage ) ) 
			) {
				e.preventDefault();
			}

			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			// Check for customer email and affiliate email.
			if( customerEmail && emailRegex.test(customerEmail) && affiliateDisplayText
				&& affiliateDisplayText.toLowerCase()?.includes(customerEmail.toLowerCase()?.trim() ||  '')
				&& ( !confirm( alertMessage ) ) 
			) {
				'text' === e?.target?.type ? e.target.value = '' : '';
				e.preventDefault();
			}
		},
		getEnhancedSelectFormatString() {
			return {
				'language': {
					errorLoading: function() {
						// Workaround for https://github.com/select2/select2/issues/4355 instead of i18n_ajax_error.
						return wc_enhanced_select_params.i18n_searching;
					},
					inputTooLong: function( args ) {
						var overChars = args.input.length - args.maximum;

						if ( 1 === overChars ) {
							return wc_enhanced_select_params.i18n_input_too_long_1;
						}

						return wc_enhanced_select_params.i18n_input_too_long_n.replace( '%qty%', overChars );
					},
					inputTooShort: function( args ) {
						var remainingChars = args.minimum - args.input.length;

						if ( 1 === remainingChars ) {
							return wc_enhanced_select_params.i18n_input_too_short_1;
						}

						return wc_enhanced_select_params.i18n_input_too_short_n.replace( '%qty%', remainingChars );
					},
					loadingMore: function() {
						return wc_enhanced_select_params.i18n_load_more;
					},
					maximumSelected: function( args ) {
						if ( args.maximum === 1 ) {
							return wc_enhanced_select_params.i18n_selection_too_long_1;
						}

						return wc_enhanced_select_params.i18n_selection_too_long_n.replace( '%qty%', args.maximum );
					},
					noResults: function() {
						return wc_enhanced_select_params.i18n_no_matches;
					},
					searching: function() {
						return wc_enhanced_select_params.i18n_searching;
					}
				}
			};
		}
	};

	affiliateUserSearch.init();
});
