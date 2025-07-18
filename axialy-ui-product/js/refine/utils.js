// /js/refine/utils.js
var RefineUtilsModule = (function() {
    /**
     * Debounce function to limit the rate at which a function can fire.
     * @param {Function} func - The function to debounce.
     * @param {number} wait - The debounce delay in milliseconds.
     * @returns {Function} - The debounced function.
     */
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    /**
     * Shows a page mask spinner with an informative message.
     * @param {string} message - The message to display.
     */
    function showPageMaskSpinner(message) {
        const existingMask = document.getElementById('page-mask');
        if (existingMask) return; // Prevent multiple masks
        const pageMask = document.createElement('div');
        pageMask.id = 'page-mask';
        pageMask.classList.add('page-mask');
        const spinner = document.createElement('div');
        spinner.classList.add('spinner');
        const msg = document.createElement('div');
        msg.classList.add('spinner-message');
        msg.textContent = message;
        pageMask.appendChild(spinner);
        pageMask.appendChild(msg);
        document.body.appendChild(pageMask);
    }

    /**
     * Hides the page mask spinner.
     */
    function hidePageMaskSpinner() {
        const pageMask = document.getElementById('page-mask');
        if (pageMask) {
            pageMask.remove();
        }
    }

    /**
     * Removes any existing refine dropdowns.
     */
    function removeExistingDropdown() {
        document.querySelectorAll('.refine-dropdown').forEach(dropdown => dropdown.remove());
    }

    return {
        debounce: debounce,
        showPageMaskSpinner: showPageMaskSpinner,
        hidePageMaskSpinner: hidePageMaskSpinner,
        removeExistingDropdown: removeExistingDropdown
    };
})();
