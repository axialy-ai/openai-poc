// /js/dashboard/api.js
var DashboardAPIModule = (function() {
    /**
     * Fetches Focus Organizations for the Dashboard.
     * @returns {Promise<Object>} - Resolves with Focus Organizations data.
     */
    async function fetchDashboardFocusOrganizations() {
        try {
            const response = await fetch(window.AxiaBAConfig.app_base_url + '/get_dashboard_custom_organizations.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            if (data.status !== 'success') {
                throw new Error(data.message || 'Failed to fetch Focus Organizations');
            }
            return data;
        } catch (error) {
            console.error('DashboardAPIModule: Error fetching Focus Organizations:', error);
            throw new Error('Failed to fetch Focus Organizations');
        }
    }
    /**
     * Fetches Stakeholder Emails for the Dashboard.
     * @returns {Promise<Object>} - Resolves with Stakeholder Emails data.
     */
    async function fetchDashboardStakeholderEmails() {
        try {
            const response = await fetch(window.AxiaBAConfig.app_base_url + '/get_dashboard_stakeholder_emails.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            if (data.status !== 'success') {
                throw new Error(data.message || 'Failed to fetch Stakeholder Emails');
            }
            return data;
        } catch (error) {
            console.error('DashboardAPIModule: Error fetching Stakeholder Emails:', error);
            throw new Error('Failed to fetch Stakeholder Emails');
        }
    }
    /**
     * Fetches Analysis Packages for the Dashboard.
     * @returns {Promise<Object>} - Resolves with Analysis Packages data.
     */
    async function fetchDashboardAnalysisPackages() {
        try {
            const response = await fetch(window.AxiaBAConfig.app_base_url + '/get_dashboard_analysis_packages.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            if (data.status !== 'success') {
                throw new Error(data.message || 'Failed to fetch Analysis Packages');
            }
            return data;
        } catch (error) {
            console.error('DashboardAPIModule: Error fetching Analysis Packages:', error);
            throw new Error('Failed to fetch Analysis Packages');
        }
    }
    /**
     * Fetches Focus Areas for the Dashboard.
     * @returns {Promise<Object>} - Resolves with Focus Areas data.
     */
    async function fetchDashboardFocusAreas() {
        try {
            const response = await fetch(window.AxiaBAConfig.app_base_url + '/get_dashboard_focus_areas.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            if (data.status !== 'success') {
                throw new Error(data.message || 'Failed to fetch Focus Areas');
            }
            return data;
        } catch (error) {
            console.error('DashboardAPIModule: Error fetching Focus Areas:', error);
            throw new Error('Failed to fetch Focus Areas');
        }
    }
    /**
     * Fetches stakeholder feedback requests based on filters.
     * @param {Object} filters - The filter parameters.
     * @returns {Promise<Object>} - Resolves with feedback requests data.
     */
    async function fetchFeedbackRequests(filters) {
        try {
            const url = new URL(window.AxiaBAConfig.app_base_url + '/get_dashboard_stakeholder_feedback_requests.php');
            Object.keys(filters).forEach(key => url.searchParams.append(key, filters[key]));
    
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
    
            const data = await response.json();
            if (data.status !== 'success') {
                throw new Error(data.message || 'Failed to fetch feedback requests');
            }
    
            return data; // Return the actual data array
        } catch (error) {
            console.error('DashboardAPIModule: Error fetching feedback requests:', error);
            throw new Error('Failed to fetch feedback requests');
        }
    }

    return {
        fetchDashboardFocusOrganizations: fetchDashboardFocusOrganizations,
        fetchDashboardStakeholderEmails: fetchDashboardStakeholderEmails,
        fetchDashboardAnalysisPackages: fetchDashboardAnalysisPackages,
        fetchDashboardFocusAreas: fetchDashboardFocusAreas,
        fetchFeedbackRequests: fetchFeedbackRequests
    };
})();
