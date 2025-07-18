// /js/dashboard/ui.js
var DashboardUIModule = (function() {
    /**
     * Populates filter dropdowns with options.
     * @param {Array} focusOrganizations - Array of focus organizations.
     * @param {Array} stakeholderEmails - Array of stakeholder emails.
     * @param {Array} analysisPackages - Array of analysis packages.
     * @param {Array} focusAreas - Array of focus areas.
     */
    function populateFilterOptions(focusOrganizations, stakeholderEmails, analysisPackages, focusAreas) {
        const focusOrganizationSelect = document.getElementById('filter-focus-organization');
        const stakeholderEmailSelect = document.getElementById('filter-stakeholder-email');
        const analysisPackageSelect = document.getElementById('filter-analysis-package');
        const focusAreaSelect = document.getElementById('filter-focus-area');

        // Populate Focus Organization Dropdown
        if (focusOrganizationSelect) {
            // Clear existing options except the first one ("Default (All Organizations)")
            focusOrganizationSelect.innerHTML = `<option value="default" selected>Default (All Organizations)</option>`;
            if (focusOrganizations && focusOrganizations.length > 0) {
                focusOrganizations.forEach(org => {
                    const option = document.createElement('option');
                    option.value = org.id;
                    option.textContent = org.custom_organization_name;
                    focusOrganizationSelect.appendChild(option);
                });
            }
        }

        // Populate Stakeholder Email Dropdown
        if (stakeholderEmailSelect) {
            // Clear existing options except the first one ("All")
            stakeholderEmailSelect.innerHTML = `<option value="all" selected>All</option>`;
            if (stakeholderEmails && stakeholderEmails.length > 0) {
                stakeholderEmails.forEach(email => {
                    const option = document.createElement('option');
                    option.value = email;
                    option.textContent = email;
                    stakeholderEmailSelect.appendChild(option);
                });
            }
        }

        // Populate Analysis Package Dropdown
        if (analysisPackageSelect) {
            // Clear existing options except the first one ("All")
            analysisPackageSelect.innerHTML = `<option value="all" selected>All</option>`;
            if (analysisPackages && analysisPackages.length > 0) {
                analysisPackages.forEach(pkg => {
                    const option = document.createElement('option');
                    option.value = pkg.id;
                    option.textContent = `${pkg.id} - ${pkg.package_name}`;
                    analysisPackageSelect.appendChild(option);
                });
            }
        }

        // Populate Focus Area Dropdown
        if (focusAreaSelect) {
            // Clear existing options except the first one ("All")
            focusAreaSelect.innerHTML = `<option value="all" selected>All</option>`;
            if (focusAreas && focusAreas.length > 0) {
                focusAreas.forEach(area => {
                    const option = document.createElement('option');
                    option.value = area;
                    option.textContent = area;
                    focusAreaSelect.appendChild(option);
                });
            }
        }
    }

    /**
     * Renders the feedback requests table.
     * @param {Array} feedbackRequests - Array of feedback request objects.
     */
    function renderFeedbackRequests(feedbackRequests) {
        const tableBody = document.querySelector('#feedback-requests-table tbody');
        if (!tableBody) return;
    
        if (feedbackRequests.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center">No feedback requests found.</td></tr>';
            return;
        }
    
        tableBody.innerHTML = feedbackRequests.map(req => `
            <tr>
                <td>${sanitizeHTML(req.stakeholder_email)}</td>
                <td>${sanitizeHTML(`${req.analysis_package_id} - ${req.analysis_package}`)}</td>
                <td>${sanitizeHTML(req.focus_area)}</td>
                <td>${sanitizeHTML(req.focus_organization)}</td>
                <td>${sanitizeHTML(new Date(req.sent_date).toLocaleString())}</td>
                <td>${
                    req.response_date && req.response_date !== 'Pending'
                        ? sanitizeHTML(new Date(req.response_date).toLocaleString())
                        : 'Pending'
                }</td>
                <!-- NEW COLUMNS -->
                <td>${sanitizeHTML(String(req.approve_count ?? 0))}</td>
                <td>${sanitizeHTML(String(req.revise_count ?? 0))}</td>
                <td>${sanitizeHTML(String(req.skip_count ?? 0))}</td>
                <td>${sanitizeHTML(String(req.total_count ?? 0))}</td>
            </tr>
        `).join('');
    }

    /**
     * Renders pagination controls based on current page and total count.
     * @param {number} currentPage - The current page number.
     * @param {number} totalCount - The total number of feedback requests.
     * @param {number} limit - Number of records per page.
     */
    function renderPagination(currentPage, totalCount, limit) {
        const pagination = document.getElementById('feedback-pagination');
        if (!pagination) return;
        const totalPages = Math.ceil(totalCount / limit);
        // Clear existing pagination except Previous and Next
        pagination.innerHTML = `
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}" id="prev-page">
                <a class="page-link" href="#" tabindex="-1">Previous</a>
            </li>
            <!-- Page numbers will be dynamically inserted here -->
            <li class="page-item ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}" id="next-page">
                <a class="page-link" href="#">Next</a>
            </li>
        `;
        // Insert page numbers (limit the number of page buttons for better UX)
        const maxPageButtons = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxPageButtons / 2));
        let endPage = startPage + maxPageButtons - 1;
        if (endPage > totalPages) {
            endPage = totalPages;
            startPage = Math.max(1, endPage - maxPageButtons + 1);
        }
        for (let i = startPage; i <= endPage; i++) {
            const pageItem = document.createElement('li');
            pageItem.className = `page-item ${i === currentPage ? 'active' : ''}`;
            const pageLink = document.createElement('a');
            pageLink.className = 'page-link';
            pageLink.href = '#';
            pageLink.textContent = i;
            pageLink.dataset.page = i;
            pageItem.appendChild(pageLink);
            // Insert before the Next button
            pagination.insertBefore(pageItem, pagination.querySelector('#next-page'));
        }
        // Disable Previous if on first page
        if (currentPage === 1) {
            pagination.querySelector('#prev-page').classList.add('disabled');
        } else {
            pagination.querySelector('#prev-page').classList.remove('disabled');
        }
        // Disable Next if on last page
        if (currentPage === totalPages || totalPages === 0) {
            pagination.querySelector('#next-page').classList.add('disabled');
        } else {
            pagination.querySelector('#next-page').classList.remove('disabled');
        }
    }

    /**
     * Sanitizes HTML to prevent XSS attacks.
     * @param {string} str - The string to sanitize.
     * @returns {string} - Sanitized string.
     */
    function sanitizeHTML(str) {
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    }

    /**
     * Displays a toast notification.
     * @param {string} message - The message to display.
     * @param {string} type - The type of toast ('success', 'danger', etc.).
     */
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('dashboard-toast-container');
        if (!toastContainer) return;
        const toastElement = createToast(message, type);
        toastContainer.appendChild(toastElement);
        const bsToast = new bootstrap.Toast(toastElement);
        bsToast.show();
        // Remove the toast from DOM after it hides
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }

    /**
     * Creates a toast element.
     * @param {string} message - The message to display.
     * @param {string} type - The type of toast ('success', 'danger', etc.).
     * @returns {HTMLElement} - The toast element.
     */
    function createToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        return toast;
    }

    return {
        populateFilterOptions: populateFilterOptions,
        renderFeedbackRequests: renderFeedbackRequests,
        renderPagination: renderPagination,
        showToast: showToast,
        sanitizeHTML: sanitizeHTML,
        createToast: createToast
    };
})();
