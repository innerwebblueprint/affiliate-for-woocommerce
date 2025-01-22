/* phpcs:ignoreFile */
(function () {
    const {ajaxArgs = {}} = afwcDashboardWidgetParams || {};

    class AFWCDashboardWidget {
        
        constructor(templateID = '', containerID = '') {
            this.templateID = templateID;
            this.template = this.templateID ? ( document.getElementById(this.templateID).innerHTML || '' ) : '';
            this.container = document.getElementById(containerID) || '';
        }

        fetchAndRenderData({action = '', security = ''} = {}) {
            this.ajaxCall({
                url: ajaxArgs.URL || '',
                type: 'post',
                dataType: 'json',
                data: { action, security },
                success: ({success = false, data = {}}) => success && this.renderTemplate(data),
                error: (err) => console.error(`Error fetching ${this.templateID} template data`, err)
            });
        }

        renderTemplate(data = {}) {
            if(!this.container){
                return;
            }

            if('object' === typeof data && Object.keys(data).length) {
                this.container.innerHTML = this.compileTemplate(data);
                this.applyPercentageStyle(['.afwc-total-sales-percentage', '.afwc-referrals-conversion-rate'], data);
            }
        }

        applyPercentageStyle(selectors = [], data = {} ) {
            if(!Array.isArray(selectors) || selectors.length === 0) {
                return;
            }
            selectors.forEach(selector => {
                let condition = false;
                switch(selector) {
                    case '.afwc-total-sales-percentage':
                        condition = data?.percent_of_total_sales && this.wcFormatToNumFormat(data.percent_of_total_sales) > 0;
                        break;
                    case '.afwc-referrals-conversion-rate':
                        condition = data?.referrals?.conversion_rate && this.wcFormatToNumFormat(data.referrals.conversion_rate) > 0;
                        break;
                    default:
                        return;
                }
                jQuery(selector).css(condition ? 'color' : 'opacity', condition ? '#166534' : '0.8');
            });
        }

        /**
         * Convert a WC's number format string to normal number without thousand separator.
         * 
         * @param {string|number} num
         * @returns {number}
         */
        wcFormatToNumFormat(num = 0) {
            if(undefined === num || null === num) {
                return 0;
            }
            const numberOfDecimals = ajaxArgs.numberOfDecimals || '2';
            const decimalSeparator = ajaxArgs.decimalSeparator || '.';
            num = accounting.formatNumber(accounting.unformat(num.toString(), decimalSeparator),numberOfDecimals,'','.');
            return Number(num);
        }

        compileTemplate(data = {}) {
            return this.template.replace(/\{\{([^{}]+?)\}\}/g, (_, key) => this.getPlaceholderValue(data, key));
        }

        getPlaceholderValue(data = {}, key = '') {
            const keys = key.split('.') || '';
            let value = data;
            if( keys.length ) {
                for (const k of keys) {
                    value = value[k];
                    if (undefined === value) {
                        value = '';
                        break;
                    }
                }
            }
            return value;
        }

        ajaxCall(options = {}) {
            jQuery.ajax(options);
        }
    }

    try{
        const summaryWidget = new AFWCDashboardWidget('afwc-summary-widget-template', 'afwc-summary-widget');
        summaryWidget.fetchAndRenderData(ajaxArgs.summary || {});
    } catch (err) {
        console.error('Error initializing dashboard summary widget', err);
    }
})();
