/* phpcs:ignoreFile */
(function() {
    "use strict";

    const AFWClickToCopy = {
        selector: '.afwc-click-to-copy',
        init() {
            const elements = document?.querySelectorAll?.(this.selector) || null;
            !!elements && elements.forEach(element => element.addEventListener('click', this.copy.bind(this)));
        },
        async copy(e = null) {
            e.preventDefault();
            const target = e?.target || null;
            const text = target?.getAttribute?.('data-ctp') || '';
            if (!text) {
                return;
            }
            const element = document?.createElement?.('input') || null;
            if (!element) {
                return;
            }
            document?.body?.appendChild?.(element);
            element.value = text;
            element.select?.();
            try {
                if (navigator?.clipboard) {
                    await this.copyToClipboard(element.value);
                    target.dispatchEvent(new Event('copied'));
                }
                element.remove?.();
            } catch (err) {
                console.error('Failed to copy: ', err);
            }
        },
        copyToClipboard(text = '') {
            return text ? navigator.clipboard.writeText(text)
                .then(() => Promise.resolve())
                .catch(err => Promise.reject(err)) : Promise.reject();
        }
    };
    // Initialize the functionality.
    AFWClickToCopy.init();
})();
