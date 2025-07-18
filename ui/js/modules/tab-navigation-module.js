// /js/modules/tab-navigation-module.js
var TabNavigationModule = (function() {
    var isSyncingDropdownAndTabs = false;
    // Track the previously active tab (e.g., 'content', 'home', 'refine')
    let lastActiveTab = null;

    /**
     * Handles the click event on a tab element.
     * Validates subscription, synchronizes tabs with dropdown, updates title and background,
     * and loads the corresponding tab content.
     *
     * @param {HTMLElement} tabElement - The clicked tab element from the Control Panel.
     * @param {Function} updatePageTitle - A callback function to update the page title based on the selected tab.
     * @param {Function} applyBackgroundSettings - A callback function to apply background image and opacity.
     */
    async function handleTabClick(tabElement, updatePageTitle, applyBackgroundSettings) {
        console.log("TabNavigationModule.handleTabClick: ENTER"); // ADDED LOG

        // Validate subscription before allowing tab switch
        const subscriptionValid = await SubscriptionValidationModule.validateSubscription();
        console.log("TabNavigationModule.handleTabClick: subscriptionValid =", subscriptionValid); // ADDED LOG
        if (!subscriptionValid) {
            console.log("TabNavigationModule.handleTabClick: Subscription invalid. Aborting tab switch."); // ADDED LOG
            return;
        }

        var selectedTab = tabElement.textContent.trim();
        var selectedTabTarget = tabElement.getAttribute('data-target').toLowerCase();
        console.log("TabNavigationModule.handleTabClick: Tab clicked =>",
                    selectedTab, "(target:", selectedTabTarget + ")"); // ADDED LOG

        // Only proceed if the clicked tab is *not* already active
        if (!tabElement.classList.contains('active')) {
            console.log("TabNavigationModule.handleTabClick: Tab is not currently active. Proceeding..."); // ADDED LOG

            // If we *were* on the Generate tab ("content") and are switching to something else,
            // clear all dynamic ribbons from UI memory.
            if (lastActiveTab === 'content' && selectedTabTarget !== 'content') {
                if (typeof DynamicRibbonsModule !== 'undefined') {
                    console.log("TabNavigationModule.handleTabClick: Clearing DynamicRibbonsModule ribbons..."); // ADDED LOG
                    DynamicRibbonsModule.clearRibbons();
                }
            }

            var tabElements = document.querySelectorAll('.tab-options .list-group-item');
            tabElements.forEach(function(item) {
                item.classList.remove('active');
            });
            tabElement.classList.add('active');
            console.log("TabNavigationModule.handleTabClick: Marked tab as active:", selectedTabTarget); // ADDED LOG

            // Synchronize dropdown menu selection
            var viewsMenu = document.getElementById('views-menu');
            if (viewsMenu && viewsMenu.value.toLowerCase() !== selectedTabTarget) {
                console.log("TabNavigationModule.handleTabClick: Synchronizing dropdown with tab..."); // ADDED LOG
                isSyncingDropdownAndTabs = true;
                viewsMenu.value = selectedTabTarget;
                isSyncingDropdownAndTabs = false;
            }

            // Update the page title using the provided callback
            console.log("TabNavigationModule.handleTabClick: Calling updatePageTitle() with:", selectedTab); // ADDED LOG
            updatePageTitle(selectedTab);

            // Apply background image and opacity
            console.log("TabNavigationModule.handleTabClick: Calling applyBackgroundSettings()..."); // ADDED LOG
            applyBackgroundSettings(tabElement);

            // Load the selected tab's content
            console.log("TabNavigationModule.handleTabClick: Calling loadTabContent() for target:", selectedTabTarget); // ADDED LOG
            loadTabContent(selectedTabTarget);

            // After loading content, remember which tab is now active
            lastActiveTab = selectedTabTarget;
            console.log("TabNavigationModule.handleTabClick: lastActiveTab updated to:", lastActiveTab); // ADDED LOG
        } else {
            console.log("TabNavigationModule.handleTabClick: Tab is already active; no action taken."); // ADDED LOG
        }
        console.log("TabNavigationModule.handleTabClick: EXIT"); // ADDED LOG
    }

    /**
     * Handles the change event on the views dropdown.
     * Validates subscription, synchronizes dropdown with tabs, updates title and background,
     * and loads the corresponding tab content.
     *
     * @param {Event} event - The change event triggered by selecting an option in the dropdown.
     * @param {Function} updatePageTitle - A callback function to update the page title based on the selected view.
     * @param {Function} applyBackgroundSettings - A callback function to apply background image and opacity.
     */
    async function handleDropdownChange(event, updatePageTitle, applyBackgroundSettings) {
        console.log("TabNavigationModule.handleDropdownChange: ENTER, new value =", event.target.value); // ADDED LOG

        // Validate subscription before allowing dropdown change
        const subscriptionValid = await SubscriptionValidationModule.validateSubscription();
        console.log("TabNavigationModule.handleDropdownChange: subscriptionValid =", subscriptionValid); // ADDED LOG
        if (!subscriptionValid) {
            console.log("TabNavigationModule.handleDropdownChange: Subscription invalid. Aborting dropdown change."); // ADDED LOG
            return;
        }

        if (isSyncingDropdownAndTabs) {
            console.log("TabNavigationModule.handleDropdownChange: Currently syncing; ignoring this event."); // ADDED LOG
            return;
        }

        var selectedValue = event.target.value.toLowerCase();
        console.log("TabNavigationModule.handleDropdownChange: selectedValue =", selectedValue); // ADDED LOG

        var tabElements = document.querySelectorAll('.tab-options .list-group-item');
        tabElements.forEach(function(tabElement) {
            var tabTarget = tabElement.getAttribute('data-target').toLowerCase();
            if (tabTarget === selectedValue) {
                if (!tabElement.classList.contains('active')) {
                    console.log("TabNavigationModule.handleDropdownChange: Deactivating all tabs and activating matched tab..."); // ADDED LOG

                    // Deactivate all tabs
                    tabElements.forEach(function(item) {
                        item.classList.remove('active');
                    });
                    // Activate the matched tab
                    tabElement.classList.add('active');

                    // Update the page title
                    var tabName = tabElement.textContent.trim();
                    console.log("TabNavigationModule.handleDropdownChange: updatePageTitle =>", tabName); // ADDED LOG
                    updatePageTitle(tabName);

                    // Apply background settings
                    console.log("TabNavigationModule.handleDropdownChange: applyBackgroundSettings()..."); // ADDED LOG
                    applyBackgroundSettings(tabElement);

                    // Load the tab content
                    console.log("TabNavigationModule.handleDropdownChange: loadTabContent() =>", selectedValue); // ADDED LOG
                    loadTabContent(selectedValue);

                    // Update lastActiveTab
                    lastActiveTab = selectedValue;
                    console.log("TabNavigationModule.handleDropdownChange: lastActiveTab =", lastActiveTab); // ADDED LOG
                } else {
                    console.log("TabNavigationModule.handleDropdownChange: Tab is already active; no action taken."); // ADDED LOG
                }
            }
        });
        console.log("TabNavigationModule.handleDropdownChange: EXIT"); // ADDED LOG
    }

    /**
     * Loads the content of the selected tab into the overview panel.
     * Relies on globally available functions (e.g., window.loadDashboardTab) in update-overview-panel.js
     *
     * @param {string} target - The tab identifier (e.g., 'home', 'content', 'dashboard')
     */
    function loadTabContent(target) {
        console.log("TabNavigationModule.loadTabContent: requested target =>", target); // ADDED LOG

        var overviewPanel = document.getElementById('overview-panel');
        switch (target) {
            case 'home':
                console.log("TabNavigationModule.loadTabContent: calling loadHomeTab()"); // ADDED LOG
                window.loadHomeTab();
                break;
            case 'content':
                console.log("TabNavigationModule.loadTabContent: calling loadGenerateTab()"); // ADDED LOG
                window.loadGenerateTab();
                break;
            case 'refine':
                console.log("TabNavigationModule.loadTabContent: calling loadRefineTab()"); // ADDED LOG
                window.loadRefineTab();
                break;
            case 'analyze':
                console.log("TabNavigationModule.loadTabContent: calling loadAnalyzeTab()"); // ADDED LOG
                window.loadAnalyzeTab();
                break;
            case 'dashboard':
                console.log("TabNavigationModule.loadTabContent: calling loadDashboardTab()"); // ADDED LOG
                window.loadDashboardTab();
                break;
            case 'settings':
                console.log("TabNavigationModule.loadTabContent: calling loadSettingsTab()"); // ADDED LOG
                window.loadSettingsTab();
                break;
            case 'publish':
                console.log("TabNavigationModule.loadTabContent: calling loadPublishTab()"); // ADDED LOG
                window.loadPublishTab();
                break;
            default:
                overviewPanel.innerHTML = '<p>No content available.</p>';
                console.log("TabNavigationModule.loadTabContent: No matching tab; set 'No content available.'"); // ADDED LOG
        }
    }

    return {
        handleTabClick: handleTabClick,
        handleDropdownChange: handleDropdownChange
    };
})();
