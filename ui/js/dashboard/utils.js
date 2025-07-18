// /js/dashboard/utils.js
var DashboardUtilsModule = (function() {
    /**
     * Sanitizes a string to prevent XSS attacks.
     * @param {string} str - The string to sanitize.
     * @returns {string} - The sanitized string.
     */
    function sanitizeHTML(str) {
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    }

    /**
     * Creates a Bootstrap Toast element.
     * @param {string} message - The message to display.
     * @param {string} type - The type of toast ('success', 'danger', etc.).
     * @returns {HTMLElement} - The Toast element.
     */
    function createToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        const toastBody = document.createElement('div');
        toastBody.className = 'd-flex';
        toastBody.innerHTML = `
            <div class="toast-body">
                ${sanitizeHTML(message)}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        `;
        toast.appendChild(toastBody);

        return toast;
    }

    return {
        sanitizeHTML: sanitizeHTML,
        createToast: createToast
    };
})();
