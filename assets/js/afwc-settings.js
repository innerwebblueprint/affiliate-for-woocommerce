/* phpcs:ignoreFile */
jQuery(
	function(){

		const { _x } = wp.i18n;

		jQuery( 'form' ).on(
			'click',
			'.woocommerce-save-button',
			function(e){
				let newPname = jQuery( '#afwc_pname' ).val() || '';
				let oldPname = afwcSettingParams.oldPname || '';
				// Return if default param and old & new param is same.
				if ( 'ref' === oldPname && oldPname === newPname ) {
					return;
				}
				// Return if new param is empty or old & new param is same.
				if ( '' == newPname || oldPname === newPname ) {
					return;
				}
				if ( jQuery( 'form' ).find( '#afwc_admin_settings_security' ).length > 0 ) {
					return confirm( _x( 'Changing tracking param name will stop affiliate tracking for the existing URL with the current tracking param name. Are you sure you want to continue?', 'alert when changing affiliate tracking param name', 'affiliate-for-woocommerce' ) );
				}
			}
		);
		jQuery( 'form' ).on(
			'change, keyup',
			'#afwc_pname',
			function( event ){
				let newPname = jQuery( this ).val();
				jQuery( '#afwc_pname_span' ).text( newPname );
			}
		);
		jQuery( 'form' ).on(
			'keydown',
			'#afwc_pname',
			function( event ){
				let key = event.which;
				if ( ! ( ( key == 8 ) || ( key == 46 ) || ( key >= 35 && key <= 40 ) || ( key >= 65 && key <= 90 ) ) ) {
					event.preventDefault();
				}
			}
		);

		let ltcExcludesSelect2Args = {
			minimumInputLength: 3,
			escapeMarkup: function(m) {
				return m;
			},
			ajax: {
				url:         afwcSettingParams.ajaxURL,
				dataType:    'json',
				delay:       1000,
				data:        function(params = {}) {
					return {
						term:     params.term || '',
						action:   'afwc_search_ltc_excludes_list',
						security: afwcSettingParams.security.searchExcludeLTC || ''
					};
				},
				processResults: function(data = {}) {
					let results = [];
					if ( data ) {
						jQuery.each( data, function(key, {title, children, group}) {
							let groupChildren = []
							jQuery.each(children, function(id, text){
								groupChildren.push({id: group + '-' + parseInt(id), text})
							})
							results.push({text: title, children: groupChildren});
						});
					}
					return {results};
				},
				error: function (jqXHR, status, error) {
					console.log(error + ": " + jqXHR.responseText);
					return { results: [] }; // Return dataset to load after error
				},
				cache: true
			}
		};
		jQuery('.afwc-lifetime-commission-excludes-search').select2(ltcExcludesSelect2Args);

		let apIncludesSelect2Args = {
			minimumInputLength: 3,
			maximumSelectionLength: 10, // TODO: test this if it applies 10 limit in search.
			escapeMarkup: function(m) {
				return m;
			},
			ajax: {
				url:         afwcSettingParams.ajaxURL,
				dataType:    'json',
				delay:       1000,
				data:        function(params = {}) {
					return {
						term:     params.term || '',
						action:   'afwc_search_ap_includes_list',
						security: afwcSettingParams.security.searchIncludeAP || ''
					};
				},
				processResults: function(data = {}) {
					let results = [];
					if ( data ) {
						jQuery.each( data, function(id, text) {
							results.push({id, text});
						});
					}
					return {results};
				},
				error: function (jqXHR, status, error) {
					console.log(error + ": " + jqXHR.responseText);
					return { results: [] }; // Return dataset to load after error
				},
				cache: true
			}
		};
		jQuery('.afwc-automatic-payouts-includes-search').select2(apIncludesSelect2Args);

		let afwcFieldVisibility = {
			inputFields: [],
			parentSelector: 'table.form-table',
			attrName: 'data-afwc-hide-if',
			hideClass: 'wc-settings-row-afwc-hide',
			init() {
				this.setInputFields();
				this.inputFields.length > 0 && this.inputFields.each( ( _, elem ) => {
					this.handleDependentFieldUpdate( jQuery( elem ) );
					this.toggleField( jQuery( elem ), false);
					jQuery(elem)?.closest?.('tr')?.find?.(".select2-search__field")?.css?.('width', '');
				});
			},
			handleDependentFieldUpdate( $fieldElem = [] ) {
				this.getDependentField( $fieldElem )?.on( 'change', () => {
					this.toggleField( $fieldElem );
				});
			},
			toggleField( $fieldElem = [] ) {
				let dependentField = this.getDependentField($fieldElem);
				let currentSection = $fieldElem.closest('tr');
				currentSection?.toggleClass?.(
					this.hideClass,
					!dependentField?.is?.(':checked')
				)
			},
			getDependentField( $elem = [] ) {
				let dependentFieldId = $elem.attr( this.attrName ) || '';
				return dependentFieldId ? jQuery( `#${dependentFieldId}` ) : [];
			},
			setInputFields() {
				this.inputFields = jQuery(this.parentSelector).find(`[${this.attrName}]`);
			},
		};
		afwcFieldVisibility.init();

		document.addEventListener('click', function(event) {
			const toggle = event.target.closest('.woocommerce-input-toggle');
			if(null === toggle) {
				return;
			}
			toggle.classList.toggle('woocommerce-input-toggle--disabled');
			toggle.classList.toggle('woocommerce-input-toggle--enabled');
			const checkbox = toggle.querySelector('input[type="checkbox"]');
			if(null === checkbox) {
				return;
			}
			checkbox.checked = toggle.classList.contains('woocommerce-input-toggle--enabled');
			checkbox.dispatchEvent(new Event('change'));
		});

		const afwcMediaUploader = {
			previewSelector: '.afwc-media-preview',
			selectMediaSelector: '#afwc-select-media-btn',
			addButtonClass: 'afwc-media-uploader',
			changeButtonClass: 'button',
			inputSelector: '.afwc-media-uploader-value',
			removeSelector: '.afwc-media-remove',
			hideClass: 'afwc-hide',
			section: '',
			init(section = null) {
				if(!section){
					return;
				}
				this.section = section;
				this.handleUploadButtonClick();
				this.handleRemoveButtonClick();
				this.toggleManageSection();
			},
			handleUploadButtonClick() {
				this.getElement(this.selectMediaSelector)?.addEventListener?.('click', (e) => {
					e.preventDefault?.();
					this.openUploader({
						title: e?.target?.getAttribute?.('data-uploader-title') || _x('Select image', 'Media uploader title', 'affiliate-for-woocommerce'),
						buttonText: e?.target?.getAttribute?.('data-uploader-button-text') || _x('Select', 'Media select button', 'affiliate-for-woocommerce')
					});
				});
			},
			openUploader({title = '',buttonText = ''} = {}) {
				let customUploader = wp?.media({
					title,
					button: { text: buttonText },
					multiple: false,
					library: { type: 'image' },
				});
				customUploader
					?.on?.('select', () => this.handleSelection(customUploader.state?.()?.get?.('selection')))
					?.open?.();
			},
			handleSelection(selection = {}) {
				let attachment = selection?.first?.()?.toJSON?.() || {}; 
				attachment?.id && this.updateAttachmentData(attachment);
			},
			updateAttachmentData({id = '', url = ''} = {}) {
				let inputElement = this.getElement(this.inputSelector) || null;
				if(!!inputElement){
					inputElement.value = id;
				}
				let imgElement = this.getElement(this.previewSelector)?.querySelector?.('img') || null;
				if (!!imgElement){
					imgElement.src = url;
				}
				this.updateSelectMediaText();
				this.toggleManageSection();
			},
			updateSelectMediaText(){
				let btn = this.getElement(this.selectMediaSelector) || null;
				if(!btn){
					return;
				}
				btn.innerText = btn.getAttribute?.(!!this.getSelectedAttachmentID() ? 'data-change-button-text' : 'data-upload-button-text') || _x('Upload image', 'Media uploader button text', 'affiliate-for-woocommerce');
			},
			toggleManageSection() {
				let isMediaExists = !!this.getSelectedAttachmentID();
				this.getElement(this.previewSelector)?.classList?.toggle?.(this.hideClass, !isMediaExists);
				this.getElement(this.removeSelector)?.classList?.toggle?.(this.hideClass, !isMediaExists);
				this.getElement(this.selectMediaSelector)?.classList?.toggle?.(this.addButtonClass, !isMediaExists);
				this.getElement(this.selectMediaSelector)?.classList?.toggle?.(this.changeButtonClass, isMediaExists);
			},
			getSelectedAttachmentID() {
				return this.getElement(this.inputSelector)?.value || '';
			},
			handleRemoveButtonClick() {
				this.getElement(this.removeSelector)?.addEventListener?.('click', () => this.removeAttachment());
			},
			removeAttachment() {
				this.updateAttachmentData();
			},
			getElement(selector = ''){
				return !!selector ? this.section?.querySelector(selector) : null
			}
		};
		
		document?.querySelectorAll('.afwc-media-upload-section')?.forEach(section => {
			try{
				// Initialize the Media uploader events.	
				afwcMediaUploader.init(section);
			}catch(e) {
				console.log('Error in Media uploader event registration: ',e);
			}
		});
	}
);
