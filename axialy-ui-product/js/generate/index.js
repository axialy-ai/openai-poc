var GenerateIndexModule = (function() {
    /**
     * Loads the Generate tab content (generate-tab.html) into the overview panel.
     * Called by layout.js when the user selects the "Generate" tab.
     */
    function loadGenerateTab() {
        const overviewPanel = document.getElementById('overview-panel');
        if (!overviewPanel) {
            console.error('Overview panel not found in the DOM.');
            return;
        }

        fetch('content/generate-tab.html')
            .then(response => {
                if (response.status === 401) {
                    // Handle unauthorized/expired subscription
                    return response.json().then(data => {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                            throw new Error('Subscription expired');
                        }
                        throw new Error(data.message || 'Unauthorized access');
                    });
                }
                if (!response.ok) {
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.text();
            })
            .then(html => {
                overviewPanel.innerHTML = html;
                // After the Generate tab content is inserted, bind the new Save Data behavior.
                if (typeof initSaveDataEnhancement === 'function') {
                    initSaveDataEnhancement();
                } else {
                    console.warn('initSaveDataEnhancement() is not defined.');
                }
                initGenerateTab(); // Continue with other initializations
            })
            .catch(error => {
                console.error('Error loading generate-tab.html:', error);
                if (error.message !== 'Subscription expired') {
                    overviewPanel.innerHTML = '<p>Error loading content.</p>';
                }
            });
    }

    /**
     * Initializes the Generate tab functionalities by calling the various modules.
     */
    function initGenerateTab() {
        // Initialize all modules with safety checks
        if (typeof InputTextModule !== 'undefined' && typeof InputTextModule.initializeInputText === 'function') {
            InputTextModule.initializeInputText();
        } else {
            console.warn('InputTextModule or initializeInputText function is not defined.');
        }

        if (typeof FocusAreasModule !== 'undefined' && typeof FocusAreasModule.initializeFocusAreas === 'function') {
            FocusAreasModule.initializeFocusAreas();
        } else {
            console.warn('FocusAreasModule or initializeFocusAreas function is not defined.');
        }

        if (typeof ProcessFeedbackModule !== 'undefined' && typeof ProcessFeedbackModule.initializeProcessFeedback === 'function') {
            ProcessFeedbackModule.initializeProcessFeedback();
        } else {
            console.warn('ProcessFeedbackModule or initializeProcessFeedback function is not defined.');
        }

        if (typeof DynamicRibbonsModule !== 'undefined' && typeof DynamicRibbonsModule.initializeDynamicRibbons === 'function') {
            DynamicRibbonsModule.initializeDynamicRibbons();
        } else {
            console.warn('DynamicRibbonsModule or initializeDynamicRibbons function is not defined.');
        }

        if (typeof FocusAreaVersionModule !== 'undefined' && typeof FocusAreaVersionModule.initialize === 'function') {
            FocusAreaVersionModule.initialize();
        } else {
            console.warn('FocusAreaVersionModule or initialize function is not defined.');
        }

        if (typeof GenerateUIModule !== 'undefined' && typeof GenerateUIModule.setupRibbonToggles === 'function') {
            GenerateUIModule.setupRibbonToggles();
        }

        // Automatically focus the "Input Text" textarea
        const inputTextField = document.getElementById('multi-line-input');
        if (inputTextField) {
            inputTextField.focus();
            inputTextField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // Expose the loadGenerateTab function globally so layout.js can access it.
    window.loadGenerateTab = loadGenerateTab;

    return {
        loadGenerateTab: loadGenerateTab
    };
})();
