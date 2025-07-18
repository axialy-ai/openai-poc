// /js/layout.js

// Flag to prevent recursive updates between dropdown and tabs
let isSyncingDropdownAndTabs = false;

// NEW: We track if we've already loaded Refine scripts
window.RefineScriptsLoaded = false;

/**
 * Updates the page title based on the selected tab.
 * Also updates the dropdown to reflect the current tab as the page title.
 * @param {string} tabName - The name of the selected tab.
 */
function updatePageTitle(tabName) {
    // ADDED LOG:
    console.log("layout.js: updatePageTitle called with tabName =>", tabName);
    const viewsMenu = document.getElementById('views-menu');
    if (viewsMenu) {
        // Find and select the option that matches the tabName
        const options = viewsMenu.options;
        for (let i = 0; i < options.length; i++) {
            if (options[i].text.trim().toLowerCase() === tabName.trim().toLowerCase()) {
                viewsMenu.selectedIndex = i;
                break;
            }
        }
    }

    // Update the document title for browser/tab title
    const appName = 'Axialy'; // Define the application name
    document.title = `${appName} - ${tabName}`.trim();
    // ADDED LOG:
    console.log("layout.js: Document title set =>", document.title);
}

/**
 * Adjusts the overview panel's height based on window size.
 */
function adjustOverviewPanel() {
    console.log("layout.js: adjustOverviewPanel invoked."); // ADDED LOG
    const overviewPanel = document.querySelector('.overview-panel');
    const header = document.querySelector('.page-header');
    const footer = document.querySelector('.page-footer');
    if (overviewPanel && header && footer) {
        const headerHeight = header.offsetHeight;
        const footerHeight = footer.offsetHeight;
        const newHeight = window.innerHeight - headerHeight - footerHeight - 40; // Adjusted padding/margin
        overviewPanel.style.height = `${newHeight}px`;
    }
}

/**
 * Applies background image and opacity to the page container using CSS variables.
 * @param {HTMLElement} element - The selected tab or dropdown option element.
 */
function applyBackgroundSettings(element) {
    console.log("layout.js: applyBackgroundSettings invoked for element =>", element); // ADDED LOG
    const backgroundImage = element.getAttribute('data-background-image');
    const backgroundOpacity = element.getAttribute('data-background-opacity');

    if (backgroundImage && backgroundOpacity !== null) {
        const pageContainer = document.querySelector('.page-container');
        if (pageContainer) {
            // Ensure the path is absolute
            const absolutePath = backgroundImage.startsWith('/') ? backgroundImage : `/${backgroundImage}`;
            pageContainer.style.setProperty('--panel-background-image', `url('${absolutePath}')`);
            pageContainer.style.setProperty('--panel-background-opacity', backgroundOpacity);
        } else {
            console.warn('Page container not found.');
        }
    } else {
        console.warn('Background settings are incomplete for the selected element.');
    }
}

/**
 * Handles tab clicks from the control panel.
 * @param {HTMLElement} tabElement - The clicked tab element.
 */
function handleTabClick(tabElement) {
    console.log("layout.js: handleTabClick - clicked tab =>", tabElement.textContent.trim()); // ADDED LOG
    const selectedTab = tabElement.textContent.trim();
    const selectedTabTarget = tabElement.getAttribute('data-target').toLowerCase();
    console.log('Tab clicked:', selectedTabTarget);  // Log tab click

    if (!tabElement.classList.contains('active')) {
        const tabElements = document.querySelectorAll('.tab-options .list-group-item');
        tabElements.forEach(item => item.classList.remove('active'));
        tabElement.classList.add('active');
        // ADDED LOG
        console.log("layout.js: handleTabClick - setting tab as active =>", selectedTabTarget);
        // Synchronize dropdown menu selection
        const viewsMenu = document.getElementById('views-menu');
        if (viewsMenu && viewsMenu.value.toLowerCase() !== selectedTabTarget) {
            isSyncingDropdownAndTabs = true;
            viewsMenu.value = selectedTabTarget;
            isSyncingDropdownAndTabs = false;
        }

        // Update page title and background
        updatePageTitle(selectedTab);
        applyBackgroundSettings(tabElement);

        const overviewPanel = document.getElementById('overview-panel');
        switch (selectedTabTarget) {
            case 'home':
                console.log("layout.js: handleTabClick - loadHomeTab()"); // ADDED LOG
                loadHomeTab();
                break;
            case 'content':
                loadGenerateTab();
                break;
            case 'refine':
                loadRefineTab();
                break;
            case 'dashboard':
                loadDashboardTab();
                break;
            case 'publish':
                console.log("layout.js: handleTabClick - loadPublishTab()"); // ADDED LOG
                console.log('Publish tab is selected');
                loadPublishTab();
                break;
            case 'settings':
                loadSettingsTab();
                break;
            default:
                overviewPanel.innerHTML = '<p>No content available.</p>';
        }
    }
}

/**
 * Handles changes in the dropdown menu.
 * @param {Event} event - The change event.
 */
function handleDropdownChange(event) {
    console.log("layout.js: handleDropdownChange - new value =>", event.target.value); // ADDED LOG

    if (isSyncingDropdownAndTabs) return; // Prevent recursive updates

    const selectedValue = event.target.value.toLowerCase(); // Ensure lowercase for consistency
    const tabElements = document.querySelectorAll('.tab-options .list-group-item');

    tabElements.forEach(function(tabElement) {
        const tabTarget = tabElement.getAttribute('data-target').toLowerCase();
        if (tabTarget === selectedValue) {
            if (!tabElement.classList.contains('active')) {
                // Remove 'active' class from all tabs
                tabElements.forEach(function(item) {
                    item.classList.remove('active');
                });
                // Add 'active' class to the matched tab
                tabElement.classList.add('active');

                // Update the page title via the dropdown
                const selectedTab = tabElement.textContent.trim();
                updatePageTitle(selectedTab);

                // Apply background image and opacity
                applyBackgroundSettings(tabElement);

                // Load the appropriate tab content
                const overviewPanel = document.getElementById('overview-panel');
                switch (selectedValue) {
                    case 'home':
                        loadHomeTab();
                        break;
                    case 'content':
                        loadGenerateTab();
                        break;
                    case 'refine':
                        loadRefineTab();
                        break;
                    case 'dashboard':
                        loadDashboardTab();
                        break;
                    case 'settings':
                        loadSettingsTab();
                        break;
                    case 'publish':
                        loadPublishTab();
                        break;
                    default:
                        overviewPanel.innerHTML = '<p>No content available for the selected tab.</p>';
                }
            }
        }
    });
}

/**
 * Initializes synchronization between control panel tabs and dropdown menu.
 */
function initializeSynchronization() {
    console.log("layout.js: initializeSynchronization invoked."); // ADDED LOG

    const tabElements = document.querySelectorAll('.tab-options .list-group-item');
    const viewsMenu = document.getElementById('views-menu');

    // Add click event listeners to Control Panel tabs
    tabElements.forEach(function(tabElement) {
        tabElement.addEventListener('click', async function() {
            console.log("layout.js: tabElement click =>", tabElement.textContent.trim()); // ADDED LOG

            await TabNavigationModule.handleTabClick(
                tabElement,
                UIUtilsModule.updatePageTitle,
                UIUtilsModule.applyBackgroundSettings
            );
        });
    });

    // Add change event listener to Views Dropdown
    if (viewsMenu) {
        viewsMenu.addEventListener('change', async function(event) {
            console.log("layout.js: viewsMenu changed =>", event.target.value); // ADDED LOG

            await TabNavigationModule.handleDropdownChange(
                event,
                UIUtilsModule.updatePageTitle,
                UIUtilsModule.applyBackgroundSettings
            );
        });
    }

    // Initial synchronization on page load
    const activeTab = document.querySelector('.tab-options .list-group-item.active');
    if (activeTab && viewsMenu) {
        const activeTabTarget = activeTab.getAttribute('data-target').toLowerCase();
        if (viewsMenu.value.toLowerCase() !== activeTabTarget) {
            isSyncingDropdownAndTabs = true;
            viewsMenu.value = activeTabTarget;
            isSyncingDropdownAndTabs = false;
        }
    }
}

/**
 * Toggles the 'open' class on the views-dropdown container when the select is clicked or focused.
 */
function handleDropdownToggle() {
    const viewsDropdown = document.querySelector('.views-dropdown');
    const viewsSelect = document.getElementById('views-menu');
    console.log("layout.js: handleDropdownToggle invoked."); // ADDED LOG

    if (!viewsDropdown || !viewsSelect) return;

    // Toggle 'open' class when the select is clicked or focused
    viewsSelect.addEventListener('click', function(event) {
        event.stopPropagation();
        viewsDropdown.classList.toggle('open');
    });

    viewsSelect.addEventListener('focus', function() {
        viewsDropdown.classList.add('open');
    });

    // Remove 'open' class when clicking outside
    document.addEventListener('click', function(event) {
        if (!viewsDropdown.contains(event.target)) {
            viewsDropdown.classList.remove('open');
        }
    });
}

/**
 * Handles the Control Panel's pin toggle functionality.
 */
function handleControlPanelPinToggle() {
    const controlPanel = document.querySelector('.control-panel');
    const pinToggle = document.getElementById('pin-icon-control');
    const collapsedTitle = document.querySelector('.collapsed-title');
    console.log("layout.js: handleControlPanelPinToggle invoked."); // ADDED LOG

    if (!controlPanel || !pinToggle || !collapsedTitle) return;

    pinToggle.addEventListener('click', function() {
        const isCollapsed = controlPanel.classList.toggle('collapsed');
        controlPanel.classList.toggle('expanded', !isCollapsed);

        // Toggle the visibility of the collapsed title
        if (isCollapsed) {
            collapsedTitle.classList.remove('hidden');
        } else {
            collapsedTitle.classList.add('hidden');
        }

        // Change the toggle button appearance
        if (isCollapsed) {
            pinToggle.classList.remove('pinned');
        } else {
            pinToggle.classList.add('pinned');
        }
    });
}

/**
 * Toggles the visibility of the Settings Dropdown.
 */
function handleSettingsDropdownToggle() {
    const settingsIcon = document.querySelector('.settings-icon');
    const settingsDropdown = document.querySelector('.settings-dropdown');
    console.log("layout.js: handleSettingsDropdownToggle invoked."); // ADDED LOG

    if (!settingsIcon || !settingsDropdown) return;

    settingsIcon.addEventListener('click', function(event) {
        event.stopPropagation();
        settingsDropdown.classList.toggle('visible');
    });

    // Close the settings dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!settingsDropdown.contains(event.target) && !settingsIcon.contains(event.target)) {
            settingsDropdown.classList.remove('visible');
        }
    });
}

/**
 * Toggles the visibility of the Help & Support Dropdown
 * (mirroring the settings approach).
 */
function handleHelpDropdownToggle() {
    const helpIcon = document.querySelector('.help-icon');
    const helpDropdown = document.querySelector('.help-dropdown');
    console.log("layout.js: handleHelpDropdownToggle invoked."); // ADDED LOG

    if (!helpIcon || !helpDropdown) return;

    helpIcon.addEventListener('click', function(event) {
        event.stopPropagation();
        helpDropdown.classList.toggle('visible');
    });

    // Close the help dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!helpDropdown.contains(event.target) && !helpIcon.contains(event.target)) {
            helpDropdown.classList.remove('visible');
        }
    });
}

/**
 * Initializes all functionalities.
 */
function initializeAll() {
    console.log("layout.js: initializeAll invoked."); // ADDED LOG

    initializeSynchronization();
    handleDropdownToggle();
    handleControlPanelPinToggle();
    handleSettingsDropdownToggle();
    handleHelpDropdownToggle();  // Enable help icon toggle

    UIUtilsModule.adjustOverviewPanel();

    // Load content based on the initially active tab
    const activeTab = document.querySelector('.tab-options .list-group-item.active');
    if (activeTab) {
        const activeTabTarget = activeTab.getAttribute('data-target').toLowerCase();
        UIUtilsModule.updatePageTitle(activeTab.textContent.trim());
        UIUtilsModule.applyBackgroundSettings(activeTab);

        switch (activeTabTarget) {
            case 'home':
                console.log("layout.js: initializeAll - activeTab => home, calling loadHomeTab()"); // ADDED LOG
                loadHomeTab();
                break;
            case 'content':
                loadGenerateTab();
                break;
            case 'refine':
                loadRefineTab();
                break;
            case 'dashboard':
                loadDashboardTab();
                break;
            case 'settings':
                loadSettingsTab();
                break;
            // ADDED FOR PUBLISH TAB:
            case 'publish':
                console.log("layout.js: initializeAll - activeTab => publish, calling loadPublishTab()"); // ADDED LOG
                loadPublishTab();
                break;
            default:
                const overviewPanel = document.getElementById('overview-panel');
                if (overviewPanel) {
                    overviewPanel.innerHTML = '<p>No content available.</p>';
                }
        }
    }
}

/**
 * Logs errors to the server-side log via AJAX.
 * @param {string} message - The error message to log.
 */
function logErrorToServer(message) {
    console.log("layout.js: logErrorToServer =>", message); // ADDED LOG

    fetch('log_errors.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message=${encodeURIComponent(message)}`
    })
    .then(response => response.text())
    .then(data => {
        console.log('Error logged to server:', data);
    })
    .catch(err => {
        console.error('Failed to log error to server:', err);
    });
}

/**
 * Dynamically loads a CSS file.
 * @param {string} href - The URL of the CSS file.
 * @param {string} id - The ID to assign to the link element.
 */
function loadCSS(href, id) {
    let existingLink = document.getElementById(id);
    if (existingLink) {
        existingLink.href = href; // Update if already loaded
    } else {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.id = id;
        document.head.appendChild(link);
    }
    console.log("layout.js: loadCSS =>", href); // ADDED LOG
}

/**
 * Dynamically loads a JS file.
 * @param {string} src - The URL of the JS file.
 * @param {string} id - The ID to assign to the script element.
 * @returns {Promise} - Resolves when the script is loaded.
 */
function loadJS(src, id) {
    console.log("layout.js: loadJS =>", src); // ADDED LOG

    return new Promise((resolve, reject) => {
        let existingScript = document.getElementById(id);
        if (existingScript) {
            // Remove the old script so we can re-add if needed
            existingScript.remove();
        }
        const script = document.createElement('script');
        script.src = src;
        script.id = id;
        script.defer = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Failed to load script: ${src}`));
        document.body.appendChild(script);
    });
}

/**
 * Loads the Home tab content into the overview panel.
 */
function loadHomeTab() {
  console.log("layout.js: loadHomeTab invoked.");

  const overviewPanel = document.getElementById('overview-panel');
  fetch('content/home-tab.html')
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok: ' + response.statusText);
      }
      return response.text();
    })
    .then(html => {
      overviewPanel.innerHTML = html;

      // Dynamically load CSS and JS for the Home tab
      loadCSS('assets/css/home-tab.css', 'home-css');

      // *** This is crucial: chain a Promise so we can call initializeHomeTab after the script loads
      //return loadJS('js/home-tab.js', 'home-js');
      //return loadJS('js/home/home.js', 'home-js');
      return loadJS('js/home/home-tab.js', 'home-js');
    })
    .then(() => {
      // Now that home-tab.js is loaded, call initializeHomeTab()
      if (typeof initializeHomeTab === 'function') {
        initializeHomeTab();
      } else {
        console.warn("initializeHomeTab() not found on window. Check your home-tab.js!");
      }
    })
    .catch(error => {
      overviewPanel.innerHTML = `<p>${error.message}</p>`;
      console.error('Failed to load Home Tab:', error);
      logErrorToServer(`Failed to load Home Tab: ${error.message}`);
    });
}


/**
 * When user selects 'Generate' tab:
 */
function loadGenerateTab() {
    console.log("layout.js: loadGenerateTab invoked."); // ADDED LOG

    const overviewPanel = document.getElementById('overview-panel');
    fetch('content/generate-tab.html')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.text();
        })
        .then(html => {
            overviewPanel.innerHTML = html;
            loadCSS('assets/css/generate-tab.css', 'generate-css');

            // Load the new modular files
            return loadJS('js/generate/ui.js', 'generate-ui-js')
                .then(() => loadJS('js/generate/index.js', 'generate-index-js'));
        })
        .then(() => {
            if (window.GenerateIndexModule && typeof GenerateIndexModule.loadGenerateTab === 'function') {
                GenerateIndexModule.loadGenerateTab();
            }
        })
        .catch(error => {
            console.error('Error loading generate-tab:', error);
            overviewPanel.innerHTML = '<p>Error loading content.</p>';
        });
}

/**
 * Loads the Refine tab content into the overview panel.
 */
function loadRefineTab() {
    console.log("layout.js: loadRefineTab invoked."); // ADDED LOG

    const overviewPanel = document.getElementById('overview-panel');
    fetch('content/refine-tab.html')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok ' + response.statusText);
            }
            return response.text();
        })
        .then(html => {
            overviewPanel.innerHTML = html;
            loadCSS('assets/css/refine-tab.css', 'refine-css');

            // If we've already loaded the Refine scripts previously, skip re-loading them:
            if (window.RefineScriptsLoaded) {
                console.log("layout.js: Refine scripts already loaded; skipping script load. Just re-invoke initRefineTab.");
                // We still might want to re-init the tab so it can refresh data if needed:
                return Promise.resolve(); 
            }

            // Otherwise, load the scripts for the Refine tab in sequence
            window.RefineScriptsLoaded = true; // Mark once
            return loadJS('js/refine/utils.js', 'refine-utils-js')
                .then(() => loadJS('js/refine/state.js', 'refine-state-js'))
                .then(() => loadJS('js/refine/api.js', 'refine-api-js'))
                .then(() => loadJS('js/refine/ui.js', 'refine-ui-js'))
                .then(() => loadJS('js/refine/actions.js', 'refine-actions-js'))
                .then(() => loadJS('js/refine/axialyAssessmentModule.js', 'refine-axialyAssessmentModule-js'))
                .then(() => loadJS('js/refine/axialyPackageAdvisorModule.js', 'refine-axialyPackageAdvisorModule-js'))
                
                .then(() => loadJS('js/enhance-content.js', 'refine-augment-js'))
                .then(() => loadJS('js/refine/augment-focus-area.js', 'refine-augment-focus-js'))
                .then(() => loadJS('js/refine/events.js', 'refine-events-js'))
                .then(() => loadJS('js/refine/recover-focus-area.js', 'refine-recover-js'))
//                .then(() => loadJS('js/refine/events.js', 'refine-events-js'))
                // Overlays
                .then(() => loadJS('js/refine/edit-record-overlay.js', 'edit-record-overlay-js'))
                .then(() => loadJS('js/refine/new-record-overlay.js', 'new-record-overlay-js'))
                // Index + ribbons
                .then(() => loadJS('js/refine/index.js', 'refine-index-js'))
                .then(() => loadJS('js/ribbon-toggles.js', 'ribbon-toggles-js'));
        })
        .then(() => {
            // After scripts are loaded (or skipped if previously loaded), init the Refine tab
            if (window.RefineIndexModule && typeof RefineIndexModule.initRefineTab === 'function') {
                RefineIndexModule.initRefineTab();
            }
        })
        .catch(error => {
            console.error('Error loading refine-tab:', error);
            overviewPanel.innerHTML = '<p>Error loading content.</p>';
        });
}

/**
 * Defines a globally available function to forcibly load the Refine tab
 * and then do EXACT STEPS 1–5 (but NOT step 6).
 *
 * @param {number} packageId - The analysis_package_headers ID to open.
 * @param {string} packageName - The user-friendly package name.
 */
function openRefineTabAndSelectPackage(packageId, packageName) {
    console.log("layout.js: openRefineTabAndSelectPackage =>", packageId, packageName); // ADDED LOG

    console.log('[layout.js Solution A] openRefineTabAndSelectPackage => ID:', packageId, 'Name:', packageName);

    // Step (1) highlight the "Refine" item in the control panel
    const refineTabItem = document.querySelector('.tab-options .list-group-item[data-target="refine"]');
    if (refineTabItem) {
        // Remove 'active' from all others
        document.querySelectorAll('.tab-options .list-group-item').forEach(item => item.classList.remove('active'));
        refineTabItem.classList.add('active');
    }

    // Step (2) also set the dropdown to 'refine'
    const viewsMenu = document.getElementById('views-menu');
    if (viewsMenu) {
        viewsMenu.value = 'refine';
    }

    // Load the Refine tab
    loadRefineTab();

    // After ~600ms, fill "[ID] - [Package Name]" in the search input, call fetch
    setTimeout(() => {
        // Step (3)
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.value = `${packageId} - ${packageName}`;
        }

        // Step (4)
        if (window.RefineEventsModule && typeof window.RefineEventsModule.fetchAndDisplayPackages === 'function') {
            window.RefineEventsModule.fetchAndDisplayPackages(`${packageId} - ${packageName}`);
        } else {
            console.warn('RefineEventsModule.fetchAndDisplayPackages not defined yet.');
        }

        // Step (5)
        setTimeout(() => {
            // Step (6) optional
            const pkgEl = document.querySelector(`.package-summary[data-package-id='${packageId}']`);
            if (pkgEl && typeof window.RefineEventsModule.selectPackage === 'function') {
                console.log('[layout.js] Auto-selecting package tile =>', packageId);
                window.RefineEventsModule.selectPackage(pkgEl, packageId);
            }
        }, 900);

    }, 600);
}
window.openRefineTabAndSelectPackage = openRefineTabAndSelectPackage;

/**
 * Loads the Dashboard tab content into the overview panel.
 */
function loadDashboardTab() {
    console.log("layout.js: loadDashboardTab invoked."); // ADDED LOG

    const overviewPanel = document.getElementById('overview-panel');
    fetch('content/dashboard-tab.html')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok ' + response.statusText);
            }
            return response.text();
        })
        .then(html => {
            overviewPanel.innerHTML = html;
            loadCSS('assets/css/dashboard-tab.css', 'dashboard-css');

            return loadJS('js/dashboard/utils.js', 'dashboard-utils-js')
                .then(() => loadJS('js/dashboard/state.js', 'dashboard-state-js'))
                .then(() => loadJS('js/dashboard/api.js', 'dashboard-api-js'))
                .then(() => loadJS('js/dashboard/ui.js', 'dashboard-ui-js'))
                .then(() => loadJS('js/dashboard/events.js', 'dashboard-events-js'))
                .then(() => loadJS('js/dashboard/index.js', 'dashboard-index-js'));
        })
        .then(() => {
            if (DashboardIndexModule && typeof DashboardIndexModule.initializeDashboardTab === 'function') {
                DashboardIndexModule.initializeDashboardTab();
            }
        })
        .catch(error => {
            console.error('Error loading dashboard-tab:', error);
            overviewPanel.innerHTML = '<p>Error loading Dashboard content.</p>';
        });
}

/**
 * Loads the Settings tab content into the overview panel.
 */
function loadSettingsTab() {
    console.log("layout.js: loadSettingsTab invoked."); // ADDED LOG

    const overviewPanel = document.getElementById('overview-panel');
    fetch('content/settings-tab.html')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.text();
        })
        .then(html => {
            overviewPanel.innerHTML = html;
            loadCSS('assets/css/settings-tab.css', 'settings-css');

            return loadJS('js/settings/utils.js', 'settings-utils-js')
                .then(() => loadJS('js/settings/state.js', 'settings-state-js'))
                .then(() => loadJS('js/settings/api.js', 'settings-api-js'))
                .then(() => loadJS('js/settings/ui.js', 'settings-ui-js'))
                .then(() => loadJS('js/settings/events.js', 'settings-events-js'))
                .then(() => loadJS('js/settings/index.js', 'settings-index-js'));
        })
        .then(() => {
            if (SettingsIndexModule && typeof SettingsIndexModule.initializeSettingsTab === 'function') {
                SettingsIndexModule.initializeSettingsTab();
            }
        })
        .catch(error => {
            console.error('Error loading settings-tab:', error);
            overviewPanel.innerHTML = '<p>Error loading content.</p>';
        });
}

/**
 * Loads the Publish tab content (ADDED FOR PUBLISH TAB)
 */
function loadPublishTab() {
    console.log("layout.js: loadPublishTab invoked."); // ADDED LOG

    const overviewPanel = document.getElementById('overview-panel');
    console.log('Loading Publish Tab...');
    console.log('Attempting to load publish-tab.html...');
    fetch('content/publish-tab.html')
        .then(response => {
            console.log("layout.js: loadPublishTab fetch => status:", response.status); // ADDED LOG
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.text();
        })
        .then(html => {
            console.log("layout.js: loadPublishTab => successfully fetched publish-tab.html"); // ADDED LOG
            console.log('Publish Tab HTML loaded successfully');
            overviewPanel.innerHTML = html;
            loadCSS('assets/css/publish-tab.css', 'publish-tab-css');
            loadJS('js/publish/index.js', 'publish-index-js')
                .then(() => {
                    console.log("layout.js: loadPublishTab => publish-index.js loaded"); // ADDED LOG
                    console.log('Publish Index JS loaded');
                    if (window.PublishIndexModule && typeof PublishIndexModule.initializePublishTab === 'function') {
                        PublishIndexModule.initializePublishTab();
                    }
                })
                .catch(err => {
                    console.error('Error loading publish-index.js:', err);
                });
        })
        .catch(error => {
            console.error('Error loading publish-tab:', error);
            overviewPanel.innerHTML = '<p>Error loading Publish tab content.</p>';
        });
}

// Event listeners
window.addEventListener('resize', UIUtilsModule.adjustOverviewPanel);

document.addEventListener('DOMContentLoaded', function() {
    console.log("layout.js: DOMContentLoaded => calling initializeAll()"); // ADDED LOG

    initializeAll();
    UIUtilsModule.adjustOverviewPanel();
});

// Expose load functions globally
window.loadHomeTab = loadHomeTab;
window.loadGenerateTab = loadGenerateTab;
window.loadRefineTab = loadRefineTab;
// window.loadAnalyzeTab = loadAnalyzeTab;
window.loadDashboardTab = loadDashboardTab;
window.loadSettingsTab = loadSettingsTab;
window.loadPublishTab = loadPublishTab;
