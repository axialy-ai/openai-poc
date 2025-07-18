// /js/update-overview-panel.js
// Function to load Dashboard tab content
function loadDashboardTab() {
    const overviewPanel = document.getElementById('overview-panel');
    fetch('content/dashboard-tab.html')
        .then(response => response.ok ? response.text() : Promise.reject('Error loading Dashboard tab.'))
        .then(html => {
            overviewPanel.innerHTML = html;
            // Dynamically load CSS for the Dashboard tab
            loadCSS('assets/css/dashboard-tab.css', 'dashboard-css');
            loadJS('js/dashboard/dashboard.js', 'dashboard-js'); // Adjust path as per new subdirectory
        })
        .catch(error => overviewPanel.innerHTML = `<p>${error}</p>`);
}

// Function to load Dashboards tab content
/*function loadDashboardsTab() {
    const overviewPanel = document.getElementById('overview-panel');
    fetch('content/dashboards/dashboards-tab.html')
        .then(response => response.ok ? response.text() : Promise.reject('Error loading Dashboards tab.'))
        .then(html => {
            overviewPanel.innerHTML = html;
            // Dynamically load CSS for the Dashboards tab
            loadCSS('assets/css/dashboards/dashboards-tab.css', 'dashboards-css');
            loadJS('js/dashboards/dashboards.js', 'dashboards-js');
        })
        .catch(error => overviewPanel.innerHTML = `<p>${error}</p>`);
}*/

// Function to load Settings tab content
function loadSettingsTab() {
    const overviewPanel = document.getElementById('overview-panel');
    fetch('content/settings-tab.html')
        .then(response => response.ok ? response.text() : Promise.reject('Error loading Settings tab.'))
        .then(html => {
            overviewPanel.innerHTML = html;
            // Load CSS if not already loaded
            loadCSS('assets/css/settings-tab.css', 'settings-css');
            
            // Initialize the Settings module after content is loaded
            if (window.SettingsModule && !window.SettingsModule.isInitialized()) {
                window.SettingsModule.initialize();
            }
        })
        .catch(error => overviewPanel.innerHTML = `<p>${error}</p>`);
}

// Helper function to load CSS dynamically
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
}

// Helper function to load JS dynamically
function loadJS(src, id) {
    // Skip loading if script is settings-tab.js since it's already loaded
    if (src === 'js/settings-tab.js') {
        return Promise.resolve();
    }
    let existingScript = document.getElementById(id);
    if (existingScript) {
        existingScript.remove(); // Remove if already exists
    }
    const script = document.createElement('script');
    script.src = src;
    script.id = id;
    script.defer = true;
    document.body.appendChild(script);
}
