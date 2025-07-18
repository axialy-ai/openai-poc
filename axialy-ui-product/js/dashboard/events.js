// /js/dashboard/events.js
var DashboardEventsModule = (function() {
    /**
     * Sets up all event listeners for the Dashboard tab.
     */
    function setupEventListeners() {
        const filtersForm = document.getElementById('feedback-filters-form');
        const resetFiltersBtn = document.getElementById('reset-filters-btn');
        const pagination = document.getElementById('feedback-pagination');
        const recordsPerPageSelect = document.getElementById('recordsPerPage'); // Added for Records per Page

        // Event Listener for Filters Form Submission
        if (filtersForm) {
            filtersForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                DashboardStateModule.setCurrentPage(1); // Reset to first page on new filter
                fetchAndRenderFeedbackRequests();
            });
        }

        // Event Listener for Reset Filters Button
        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', async () => {
                const filtersForm = document.getElementById('feedback-filters-form');
                if (filtersForm) {
                    filtersForm.reset();
                    // Reset Focus Organization to "Default (All Organizations)"
                    const focusOrgSelect = document.getElementById('filter-focus-organization');
                    if (focusOrgSelect) {
                        focusOrgSelect.value = 'default';
                    }
                    // Reset Stakeholder Email to "All"
                    const stakeholderEmailSelect = document.getElementById('filter-stakeholder-email');
                    if (stakeholderEmailSelect) {
                        stakeholderEmailSelect.value = 'all';
                    }
                    // Reset Analysis Package to "All"
                    const analysisPackageSelect = document.getElementById('filter-analysis-package');
                    if (analysisPackageSelect) {
                        analysisPackageSelect.value = 'all';
                    }
                    // Reset Focus Area to "All"
                    const focusAreaSelect = document.getElementById('filter-focus-area');
                    if (focusAreaSelect) {
                        focusAreaSelect.value = 'all';
                    }
                    // Reset Response Received to "All"
                    const responseReceivedSelect = document.getElementById('filter-response-received');
                    if (responseReceivedSelect) {
                        responseReceivedSelect.value = 'all';
                    }
                }
                DashboardStateModule.setCurrentPage(1); // Reset to first page on reset
                DashboardStateModule.setLimit(10); // Reset to default limit if desired
                if (recordsPerPageSelect) {
                    recordsPerPageSelect.value = '10'; // Reset the dropdown to default value
                }
                fetchAndRenderFeedbackRequests();
            });
        }

        // Event Listener for Pagination Controls
        if (pagination) {
            pagination.addEventListener('click', async (e) => {
                e.preventDefault();
                const target = e.target.closest('a');
                if (!target) return;
                const currentPage = DashboardStateModule.getCurrentPage();
                const totalCount = DashboardStateModule.getTotalCount();
                const limit = DashboardStateModule.getLimit();
                const totalPages = Math.ceil(totalCount / limit);

                if (target.parentElement.id === 'prev-page' && currentPage > 1) {
                    DashboardStateModule.setCurrentPage(currentPage - 1);
                } else if (target.parentElement.id === 'next-page' && currentPage < totalPages) {
                    DashboardStateModule.setCurrentPage(currentPage + 1);
                } else if (target.dataset.page) {
                    DashboardStateModule.setCurrentPage(parseInt(target.dataset.page));
                } else {
                    return; // Clicked on a non-paginated link
                }
                fetchAndRenderFeedbackRequests();
            });
        }

        // Event Listener for Records Per Page Dropdown
        if (recordsPerPageSelect) {
            recordsPerPageSelect.addEventListener('change', async (e) => {
                const newLimit = parseInt(e.target.value, 10);
                DashboardStateModule.setLimit(newLimit);
                DashboardStateModule.setCurrentPage(1); // Reset to first page on limit change
                fetchAndRenderFeedbackRequests();
            });
        }

        // Initialize the toggle functionality for the "Hide/Show Filters" button
        setupFiltersToggle();
    }

    /**
     * Sets up event listeners for the "Hide/Show Filters" toggle button.
     */
    function setupFiltersToggle() {
        const filtersCollapse = document.getElementById('filtersCollapse');
        const toggleButton = document.querySelector('[data-bs-target="#filtersCollapse"]'); // The toggle button
        const toggleText = document.getElementById('filtersToggleText');
        const toggleIcon = document.getElementById('filtersToggleIcon');

        if (filtersCollapse && toggleButton && toggleText && toggleIcon) {
            // Listen for collapse show event
            filtersCollapse.addEventListener('show.bs.collapse', function () {
                toggleText.textContent = 'Hide Filters';
                toggleIcon.classList.remove('bi-chevron-down');
                toggleIcon.classList.add('bi-chevron-up');
            });

            // Listen for collapse hide event
            filtersCollapse.addEventListener('hide.bs.collapse', function () {
                toggleText.textContent = 'Show Filters';
                toggleIcon.classList.remove('bi-chevron-up');
                toggleIcon.classList.add('bi-chevron-down');
            });
        }
    }

    /**
     * Fetches and populates all filter options.
     */
    async function fetchAndPopulateFilters() {
        try {
            // Fetch Focus Organizations
            const focusOrgData = await DashboardAPIModule.fetchDashboardFocusOrganizations();
            DashboardStateModule.setFocusOrganizations(focusOrgData.organizations);
            // Fetch Stakeholder Emails
            const stakeholderEmailsData = await DashboardAPIModule.fetchDashboardStakeholderEmails();
            DashboardStateModule.setStakeholderEmails(stakeholderEmailsData.stakeholder_emails);
            // Fetch Analysis Packages
            const analysisPackagesData = await DashboardAPIModule.fetchDashboardAnalysisPackages();
            DashboardStateModule.setAnalysisPackages(analysisPackagesData.analysis_packages);
            // Fetch Focus Areas
            const focusAreasData = await DashboardAPIModule.fetchDashboardFocusAreas();
            DashboardStateModule.setFocusAreas(focusAreasData.focus_areas);
            // Populate filter dropdowns
            DashboardUIModule.populateFilterOptions(
                DashboardStateModule.getFocusOrganizations(),
                DashboardStateModule.getStakeholderEmails(),
                DashboardStateModule.getAnalysisPackages(),
                DashboardStateModule.getFocusAreas()
            );
            // Removed the success toast message
            // DashboardUIModule.showToast('Filters loaded successfully.', 'success');
        } catch (error) {
            DashboardUIModule.showToast(error.message, 'danger');
            console.error('DashboardEventsModule: Error fetching and populating filters:', error);
        }
    }

    /**
     * Fetches feedback requests based on current filters and renders them.
     */
    async function fetchAndRenderFeedbackRequests() {
        const filtersForm = document.getElementById('feedback-filters-form');
        const formData = new FormData(filtersForm);
        const filters = {
            stakeholder_email: formData.get('stakeholder_email') || 'all',
            analysis_package_id: formData.get('analysis_package_id') || 'all',
            focus_area_name: formData.get('focus_area_name') || 'all',
            custom_organization_id: formData.get('custom_organization_id') || 'default',
            response_received: formData.get('response_received') || 'all',
            limit: DashboardStateModule.getLimit(),
            page: DashboardStateModule.getCurrentPage()
        };
        try {
            const response = await DashboardAPIModule.fetchFeedbackRequests(filters);
            DashboardStateModule.setFeedbackRequests(response.data);
            DashboardStateModule.setTotalCount(response.total_count);
            DashboardUIModule.renderFeedbackRequests(response.data);
            DashboardUIModule.renderPagination(DashboardStateModule.getCurrentPage(), response.total_count, DashboardStateModule.getLimit());
            // Removed the success toast message
            // DashboardUIModule.showToast('Feedback requests updated successfully.', 'success');
        } catch (error) {
            DashboardUIModule.showToast(error.message, 'danger');
            console.error('DashboardEventsModule: Error fetching feedback requests:', error);
        }
    }

    /**
     * Fetches and populates filter options, then fetches feedback requests.
     */
    async function initializeFiltersAndData() {
        await fetchAndPopulateFilters();
        await fetchAndRenderFeedbackRequests();
    }

    return {
        setupEventListeners: setupEventListeners,
        fetchAndRenderFeedbackRequests: fetchAndRenderFeedbackRequests,
        initializeFiltersAndData: initializeFiltersAndData
    };
})();
