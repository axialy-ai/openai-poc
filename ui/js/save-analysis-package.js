// /js/save-analysis-package.js
var SaveAnalysisPackageModule = (function() {
    /**
     * Initiates the module by attaching to the "Save Data" button if present.
     */
    function init() {
        const saveDataBtn = document.getElementById('save-data-btn');
        if (saveDataBtn) {
            saveDataBtn.addEventListener('click', handleSaveData);
        }
    }

    /**
     * Handles the "Save Data" button click:
     * 1) Collect data from dynamic ribbons,
     * 2) Calls AI for analysis package header,
     * 3) Shows an overlay to let user finalize the package name/description,
     * 4) Saves the final package with focus-area records (no [redacted]).
     */
    function handleSaveData() {
        const collectedData = DynamicRibbonsModule.collectRibbonsData();
        if (!collectedData || collectedData.length === 0) {
            alert('No data available to save.');
            return;
        }

        // Show the overlay with a loading message
        OverlayModule.showLoadingOverlay("Generating analysis package header.");

        fetch(window.AxiaBAConfig.api_base_url + '/ai_helper.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': window.AxiaBAConfig.api_key
            },
            body: JSON.stringify({
                text: JSON.stringify(collectedData),
                template: 'Analysis_Package_Header'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                const headerData = data.data['Analysis Package Header'][0] || {};

                // Show the header review overlay
                OverlayModule.showHeaderReviewOverlay(
                    headerData,
                    function(updatedData) {
                        // User clicked "Save Now"
                        OverlayModule.showLoadingMask("Saving analysis package data...");
                        saveAnalysisPackageHeader(updatedData, collectedData, true);
                    },
                    function() {
                        // User clicked "Cancel"
                        saveAnalysisPackageHeader(headerData, null, false);
                    }
                );
            } else {
                const errorMessage = data.message || 'Unknown error occurred.';
                alert(`API Error: ${errorMessage}`);
                OverlayModule.hideOverlay();
            }
        })
        .catch(error => {
            alert(`Error processing data: ${error.message}`);
            OverlayModule.hideOverlay();
        });
    }

    /**
     * Saves the Analysis Package Header + (optionally) the ribbons data into the database.
     * No [redacted] is used in the new schema.
     *
     * @param {Object} headerData - The updated package header from the overlay.
     * @param {Array|null} collectedData - The ribbons data, or null if user canceled.
     * @param {boolean} showMessageOverlay - If true, we display a success message & clear ribbons.
     */
    function saveAnalysisPackageHeader(headerData, collectedData, showMessageOverlay) {
        const storeUrl = '/save_analysis_package.php';
        const payload = {
            headerData: headerData,
            collectedData: collectedData,
            input_text_summaries_id: window.inputTextSummariesId
        };

        fetch(storeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                if (showMessageOverlay) {
                    const message =
                        `Analysis Package saved successfully.\n` +
                        `ID: ${data.analysis_package_headers_id}\n` +
                        `Package Name: ${data.package_name}`;

                    OverlayModule.showMessageOverlay(message, function() {
                        // On close of the message overlay:
                        OverlayModule.hideOverlay();
                        DynamicRibbonsModule.clearRibbons();

                        // Reset input text area and summary fields
                        const inputTextArea = document.getElementById('multi-line-input');
                        if (inputTextArea) {
                            inputTextArea.value = '';
                            inputTextArea.focus();
                        }
                        InputTextModule.updateCharacterCount();

                        const titleDisplay   = document.getElementById('input-title-display');
                        const summaryDisplay = document.getElementById('input-summary-display');
                        const utcDisplay     = document.getElementById('input-utc-display');
                        if (titleDisplay)   titleDisplay.textContent   = 'N/A';
                        if (summaryDisplay) summaryDisplay.textContent = 'N/A';
                        if (utcDisplay)     utcDisplay.textContent     = 'N/A';

                        // Clear stored summary ID
                        window.inputTextSummariesId = null;

                        // Update button states
                        ProcessFeedbackModule.updateSendButtonState();
                        ProcessFeedbackModule.updateSaveButtonState();
                    });
                } else {
                    // If user canceled from the overlay
                    OverlayModule.hideOverlay();
                }
            } else {
                const errorMessage = data.message || 'Failed to save Analysis Package.';
                alert(errorMessage);
                OverlayModule.hideOverlay();
            }
        })
        .catch(error => {
            alert(`Error saving Analysis Package: ${error.message}`);
            OverlayModule.hideOverlay();
        });
    }

    return {
        init: init
    };
})();

// Initialize this module
SaveAnalysisPackageModule.init();
