/* phpcs:ignoreFile */
const afwcPrintInvoice = {
    actionBtnSelector: 'a.print-invoice',
    dataContainer: 'td.invoice',
    init() {
        if (!this.boundHandlePrintButtonClick) {
            this.boundHandlePrintButtonClick = this.handlePrintButtonClick.bind(this);
        }
        document?.querySelectorAll?.(this.actionBtnSelector)?.forEach(row => {
            row.removeEventListener?.('click', this.boundHandlePrintButtonClick);
            row.addEventListener?.('click', this.boundHandlePrintButtonClick);
        });
    },
    handlePrintButtonClick(e){
        e.preventDefault();
        this.printInvoice(e?.target?.closest?.(this.dataContainer) || null);
    },
    async printInvoice(htmlElem = null) {
        if(!htmlElem){
            return;
        }
        const invoiceData = this.getData(htmlElem) || null;
        if(!invoiceData){
            return;
        }
        let invoiceTemplate;
        try{
            invoiceTemplate = await this.getInvoiceTemplate(invoiceData) || '';
        }catch(e){
            console.log(`Error while fetching payout invoice template: ${e}` );
            return;
        }
        invoiceTemplate ? this.doPrint(invoiceTemplate) : console.warn('Not allowed to print invoice');
    },
    getData(elem = null) {
        return elem ? {
            payout_id: elem.getAttribute('data-payout_id') || 0,
            affiliate_id: elem.getAttribute('data-affiliate_id') || 0,
            date_time: elem.getAttribute('data-datetime') || '',
            from_period: elem.getAttribute('data-from_period') || '',
            to_period: elem.getAttribute('data-to_period') || '',
            referral_count: elem.getAttribute('data-referral_count') || 0,
            amount: elem.getAttribute('data-amount') || 0,
            currency: elem.getAttribute('data-currency') || '',
            method: elem.getAttribute('data-method') || '',
            notes: elem.getAttribute('data-notes') || ''
        } : null;
    },
    getInvoiceTemplate(data = {}) {
        const {invoiceTemplate = {}} = afwcDashboardParams || {};
        return this.ajaxCall({
            url: invoiceTemplate?.ajaxURL || '',
            type: 'post',
            dataType: 'html',
            data: {
                security:invoiceTemplate?.nonce || '',
                ...data
            },
            success: (res = '') => res,
            error: (err) => err
        });
    },
    doPrint(html = '') {
        if(!html){
            return;
        }
        const printWindow = window?.open?.('', '', 'height=600,width=800');
        if(!printWindow){
            return;
        }
        printWindow.document?.write?.(html);
        printWindow.document?.close?.();
        printWindow.focus?.();

        const images = printWindow.document?.images;
        if (images && images.length > 0) {
            let loadedCount = 0;
            for (let img of images) {
                img.onload = img.onerror = function(){
                    loadedCount++;
                    if (loadedCount === images.length) {
                        printWindow.print?.();
                    }
                };
            }
        } else {
            printWindow.print?.();
        }
    },
    ajaxCall: function (options = {}) {
        return jQuery.ajax(options);
    }
};

function afwcRegisterInvoicePrint(){
    try{
        // Initialize the invoice print events.
        afwcPrintInvoice.init();
    }catch(e) {
        console.log('Error in print invoice: ',e);
    }
}

document?.addEventListener?.('DOMContentLoaded', function() {
    afwcRegisterInvoicePrint();
});
