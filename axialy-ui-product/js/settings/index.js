// /js/settings/index.js
var SettingsIndexModule = (function() {
    /**
     * Initializes the Settings tab by loading data and setting up the UI.
     */
    async function initializeSettingsTab() {
        if (SettingsStateModule.isInitialized()) {
            console.log('SettingsModule is already initialized.');
            return;
        }

        try {
            // Fetch data
            const [orgs, focusOrg] = await Promise.all([
                SettingsAPIModule.fetchCustomOrganizations(),
                SettingsAPIModule.fetchCurrentFocusOrganization()
            ]);

            // Update state
            SettingsStateModule.setCustomOrgs(orgs);
            SettingsStateModule.setCurrentFocusOrg(focusOrg);
            SettingsStateModule.setInitialized(true);

            // Update UI
            SettingsUIModule.renderOrganizationsList(orgs);
            SettingsUIModule.updateFocusOrgDropdown(orgs);

            // Set the focus organization select value
            const focusOrgSelect = document.getElementById('focus-organization');
            if (focusOrgSelect) {
                focusOrgSelect.value = focusOrg || 'default';
            }

            // Set up event listeners
            const elements = cacheDOMElements();
            SettingsEventsModule.setupEventListeners(elements);

            console.log('SettingsModule initialized successfully.');
        } catch (error) {
            console.error('SettingsIndexModule: Initialization failed:', error);
            SettingsUIModule.showToast('Failed to initialize settings. Please try refreshing the page.', 'danger');
        }
    }

    /**
     * Caches necessary DOM elements.
     * @returns {Object} - An object containing cached DOM elements.
     */
    function cacheDOMElements() {
        return {
            focusOrgSelect: document.getElementById('focus-organization'),
            createOrgForm: document.getElementById('create-org-form'),
            orgsList: document.getElementById('orgs-list'),
            toastContainer: document.getElementById('settings-toast-container')
        };
    }

    return {
        initializeSettingsTab: initializeSettingsTab
    };
})();
