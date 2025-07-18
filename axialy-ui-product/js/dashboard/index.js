// /js/dashboard/index.js
var DashboardIndexModule = (function() {
    /**
     * Initializes the Dashboard tab by loading filter options and feedback requests.
     */
    async function initializeDashboardTab() {
        if (DashboardStateModule.isInitialized()) {
            console.log('DashboardModule is already initialized.');
            return;
        }
        try {
            // Initialize filter options and fetch feedback requests
            await DashboardEventsModule.initializeFiltersAndData();
            // Set state as initialized
            DashboardStateModule.setInitialized(true);
            // Set up event listeners
            DashboardEventsModule.setupEventListeners();
            console.log('DashboardModule initialized successfully.');
        } catch (error) {
            console.error('DashboardIndexModule: Initialization failed:', error);
            DashboardUIModule.showToast('Failed to initialize dashboard. Please try refreshing the page.', 'danger');
        }
    }
    return {
        initializeDashboardTab: initializeDashboardTab
    };
})();

// Initialize the Dashboard tab when the script is loaded
DashboardIndexModule.initializeDashboardTab();
