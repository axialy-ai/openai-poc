// /js/settings/utils.js
var SettingsUtilsModule = (function() {
    function sanitizeHTML(str) {
        if (!str) return '';
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    }

    function createToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-bg-' + type + ' border-0';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        const innerDiv = document.createElement('div');
        innerDiv.className = 'd-flex';
        innerDiv.innerHTML = `
            <div class="toast-body">
                ${sanitizeHTML(message)}
            </div>
            <button type="button"
                    class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"
                    aria-label="Close"></button>
        `;
        toast.appendChild(innerDiv);
        return toast;
    }

    return {
        sanitizeHTML,
        createToast
    };
})();
