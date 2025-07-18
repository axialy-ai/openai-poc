/****************************************************************************
 * /js/process-feedback.js
 *
 ***************************************************************************/
var ProcessFeedbackModule = (function() {
    let communicationCount = 0;

    /**
     * Returns the current UTC datetime in YYYY-MM-DD HH:MM:SS format.
     */
    function getCurrentUtcDatetime() {
        const now = new Date();
        const year = now.getUTCFullYear();
        const month = String(now.getUTCMonth() + 1).padStart(2, '0');
        const day = String(now.getUTCDate()).padStart(2, '0');
        const hours = String(now.getUTCHours()).padStart(2, '0');
        const minutes = String(now.getUTCMinutes()).padStart(2, '0');
        const seconds = String(now.getUTCSeconds()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    /**
     * Updates the state of the "Send Selected" button based on user input,
     * templates selected, and whether there's an active communication in progress.
     */
    function updateSendButtonState() {
        const sendAllBtn   = document.getElementById('send-all-btn');
        const inputTextArea= document.getElementById('multi-line-input');
        const inputText    = inputTextArea ? inputTextArea.value : '';

        // Retrieve the user’s chosen templates from FocusAreasModule
        const selectedFocusAreas = (
            typeof FocusAreasModule !== 'undefined' &&
            typeof FocusAreasModule.getSelectedTemplates === 'function'
        )
            ? FocusAreasModule.getSelectedTemplates()
            : [];

        if (sendAllBtn) {
            // Enable only if input text is non-empty, at least one focus area is selected,
            // and there’s no communication in progress.
            if (inputText.trim() !== '' && selectedFocusAreas.length > 0 && communicationCount === 0) {
                sendAllBtn.disabled = false;
                sendAllBtn.style.opacity = '1';
                sendAllBtn.style.cursor  = 'pointer';
            } else {
                sendAllBtn.disabled = true;
                sendAllBtn.style.opacity = '0.5';
                sendAllBtn.style.cursor  = 'not-allowed';
            }
        } else {
            console.warn('ProcessFeedbackModule: "send-all-btn" not found in the DOM. Cannot update send-button state.');
        }
    }

    /**
     * Updates the state of the "Save Data" button based on dynamic ribbons and communication count.
     */
    function updateSaveButtonState() {
        const saveDataBtn     = document.getElementById('save-data-btn');
        const ribbonsContainer= document.getElementById('ribbons-container');
        const hasRibbons      = ribbonsContainer && ribbonsContainer.children.length > 0;

        if (saveDataBtn) {
            if (hasRibbons && communicationCount === 0) {
                saveDataBtn.disabled = false;
                saveDataBtn.style.opacity = '1';
                saveDataBtn.style.cursor  = 'pointer';
            } else {
                saveDataBtn.disabled = true;
                saveDataBtn.style.opacity = '0.5';
                saveDataBtn.style.cursor  = 'not-allowed';
            }
        } else {
            console.warn('ProcessFeedbackModule: "save-data-btn" not found in the DOM. Cannot update save-button state.');
        }
    }

    /**
     * Updates an optional UI element showing messages about the focus-area creation for the focus area records.
     */
    function updateFocusAreaRecordsMessage(message) {
        const enhancementMsg = document.getElementById('focus-area-ai-message');
        if (enhancementMsg) {
            enhancementMsg.textContent = message;
        } else {
            // If no such element, just log a warning
            console.warn('No element with ID "focus-area-ai-message" found in the DOM.');
        }
    }

    /**
     * Toggles a global spinner (if present) depending on how many communications are in progress.
     */
    function toggleSpinner() {
        const spinner = document.getElementById('spinner');
        if (spinner) {
            if (communicationCount > 0) {
                spinner.classList.add('visible');
                spinner.setAttribute('aria-hidden', 'false');
            } else {
                spinner.classList.remove('visible');
                spinner.setAttribute('aria-hidden', 'true');
            }
        } else {
            console.warn('Spinner element not found in the DOM.');
        }
    }

    /**
     * Processes multiple selected templates in sequence, requesting focus-area records from AI.
     */
    function processTemplates(templates, callback, promptTitle, summaryId) {
        if (templates.length === 0) {
            if (typeof callback === 'function') {
                callback();
            }
            return;
        }
        const templateName = templates.shift();
        updateFocusAreaRecordsMessage(`Requesting focus-area records for “${templateName.replace(/_/g, ' ')}”...`);
        sendApiRequest(templateName, templates, callback, promptTitle, summaryId);
    }

    /**
     * Returns the client local date/time + time zone for metadata usage.
     */
    function getClientDateTime() {
        const now = new Date();
        const dateTimeStr = now.toLocaleString();
        const timeZone    = Intl.DateTimeFormat().resolvedOptions().timeZone;
        return {
            datetime: dateTimeStr,
            timezone: timeZone
        };
    }

    /**
     * Sends a request to /ai_helper.php for a given template, merging the stakeholder email logic
     * if the template is "stakeholders".
     */
    function sendApiRequest(templateName, remainingTemplates, callback, promptTitle, summaryId) {
        const inputTextArea = document.getElementById('multi-line-input');
        const userVisibleText = inputTextArea ? inputTextArea.value : '';
        const dateTimeInfo = getClientDateTime();

        // This logic was in the legacy code: if the template is "Stakeholders",
        // we prepend the user’s email details to the text
        let finalText = userVisibleText;
        let userEmail = window.currentUserEmail || 'noreply@example.com';
        const lowerName = templateName.toLowerCase();

        if (
            lowerName.endsWith('stakeholders') ||
            lowerName.endsWith('stakeholders.json')
        ) {
            if (typeof FocusAreasModule !== 'undefined' &&
                typeof FocusAreasModule.computePrependedText === 'function') {
                // Prepend the user’s email details
                finalText = FocusAreasModule.computePrependedText(userEmail, userVisibleText);
            }
        }

        communicationCount++;
        updateSendButtonState();
        updateSaveButtonState();
        toggleSpinner();

        fetch(window.AxiaBAConfig.api_base_url + '/ai_helper.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': window.AxiaBAConfig.api_key
            },
            body: JSON.stringify({
                text: finalText,
                template: templateName,
                metadata: {
                    datetime: dateTimeInfo.datetime,
                    timezone: dateTimeInfo.timezone
                }
            })
        })
        .then(response => {
            console.log("Focus-area records Response Status:", response.status);
            return response.json();
        })
        .then(data => {
            console.log(`Data for template "${templateName}":`, data);
            if (data.status === 'success' && data.data) {
                // Insert the resulting records as "ribbons" in the UI
                if (window.DynamicRibbonsModule &&
                    typeof DynamicRibbonsModule.displayRibbons === 'function') {
                    DynamicRibbonsModule.displayRibbons(data.data, promptTitle, summaryId);
                } else {
                    console.error('DynamicRibbonsModule.displayRibbons is not defined.');
                }
                updateFocusAreaRecordsMessage(`Received focus-area records for “${templateName.replace(/_/g, ' ')}.”`);
            } else {
                const errMsg = data.message || 'Unknown error occurred.';
                updateFocusAreaRecordsMessage(`Error for “${templateName.replace(/_/g, ' ')}”: ${errMsg}`);
                console.error(`Error for template ${templateName}:`, errMsg);
            }

            // Proceed to the next template
            processTemplates(remainingTemplates, callback, promptTitle, summaryId);
        })
        .catch(error => {
            updateFocusAreaRecordsMessage(
                `Error generating records for “${templateName.replace(/_/g, ' ')}”: ${error.message || error}`
            );
            console.error(`Error generating data for template ${templateName}:`, error);
            // Continue to the next template anyway
            processTemplates(remainingTemplates, callback, promptTitle, summaryId);
        })
        .finally(() => {
            communicationCount--;
            updateSendButtonState();
            updateSaveButtonState();
            toggleSpinner();
        });
    }

    /**
     * Summarizes the user’s input text by calling the 'Prompt_Summary' template,
     * storing the result in input_text_summaries, for re-linking records to that summary.
     */
    function sendInputTextSummary(inputText) {
        updateFocusAreaRecordsMessage("Processing user input for summary...");
        communicationCount++;
        updateSendButtonState();
        updateSaveButtonState();
        toggleSpinner();

        return fetch(window.AxiaBAConfig.api_base_url + '/ai_helper.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': window.AxiaBAConfig.api_key
            },
            body: JSON.stringify({
                text: inputText,
                template: 'Prompt_Summary'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data && data.data['Prompt Summary']) {
                // Extract the short summary
                const summaryData  = data.data['Prompt Summary'][0];
                const inputTitle   = summaryData['Prompt Title'];
                const inputSummary = summaryData['Short Summary'];
                const apiUtc       = getCurrentUtcDatetime();

                // Populate UI fields if they exist
                const titleDisp   = document.getElementById('input-title-display');
                const summaryDisp = document.getElementById('input-summary-display');
                const utcDisp     = document.getElementById('input-utc-display');
                const summaryIdDisp = document.getElementById('input-summary-id-display');
                if (titleDisp)   titleDisp.textContent   = inputTitle   || 'N/A';
                if (summaryDisp) summaryDisp.textContent = inputSummary || 'N/A';
                if (utcDisp)     utcDisp.textContent     = apiUtc       || 'N/A';

                // Now store the summary in the DB via /store_summary.php
                return storeSummaryData(inputTitle, inputSummary, inputText, apiUtc)
                    .then(summaryId => {
                        if (summaryIdDisp) {
                            summaryIdDisp.textContent = summaryId || 'N/A';
                        }
                        return inputTitle; // Return the promptTitle to the chain
                    });
            } else {
                const errMsg = data.message || 'Failed to generate summary.';
                updateFocusAreaRecordsMessage(`Summary Error: ${errMsg}`);
                console.error('Summary Error:', errMsg);
                throw new Error(errMsg);
            }
        })
        .catch(error => {
            updateFocusAreaRecordsMessage(`Error generating summary: ${error.message || error}`);
            console.error('Summary generation error:', error);
            throw error;
        })
        .finally(() => {
            communicationCount--;
            updateSendButtonState();
            updateSaveButtonState();
            toggleSpinner();
        });
    }

    /**
     * Calls /store_summary.php to persist the new input_text_summaries row
     * for the user’s input text. The response returns the new summary ID.
     */
    function storeSummaryData(inputTitle, inputSummary, inputText, apiUtc) {
        const storeUrl = '/store_summary.php';
        return fetch(storeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                input_text_title:   inputTitle,
                input_text_summary: inputSummary,
                input_text:         inputText,
                api_utc:            apiUtc
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                console.log('Summary data stored successfully.');
                // Store the new ID in a global array (commonly used in old code)
                if (Array.isArray(data.input_text_summaries_ids)) {
                    window.inputTextSummariesId = data.input_text_summaries_ids;
                } else {
                    window.inputTextSummariesId = [data.input_text_summaries_ids];
                }
                return window.inputTextSummariesId[window.inputTextSummariesId.length - 1];
            } else {
                const errMsg = data.message || 'Failed to store summary data.';
                updateFocusAreaRecordsMessage(`Error storing summary: ${errMsg}`);
                console.error('storeSummaryData error:', errMsg);
                throw new Error(errMsg);
            }
        })
        .catch(error => {
            console.error('Error storing summary data:', error);
            updateFocusAreaRecordsMessage(`Error storing summary: ${error.message || error}`);
            throw error;
        });
    }

    /**
     * Sets up the "Send Selected" button to run the entire flow of:
     * 1) Summarize input text,
     * 2) Then generate the selected focus areas from templates.
     */
    function setupSendButton() {
        const sendAllBtn = document.getElementById('send-all-btn');
        if (!sendAllBtn) {
            console.warn('ProcessFeedbackModule: "send-all-btn" not found. Skipping initialization.');
            return;
        }

        sendAllBtn.addEventListener('click', () => {
            sendAllBtn.disabled = true;
            sendAllBtn.textContent = "Sending...";
            toggleSpinner();

            const inputTextArea = document.getElementById('multi-line-input');
            const userText      = inputTextArea ? inputTextArea.value : '';
            if (userText.trim() === '') {
                alert('Please enter some text first.');
                sendAllBtn.disabled   = false;
                sendAllBtn.textContent= "Send Selected";
                toggleSpinner();
                return;
            }

            // Summarize the user’s text first
            sendInputTextSummary(userText)
            .then(promptTitle => {
                // Once we have a summary, get its ID
                const summaryId = (
                    window.inputTextSummariesId &&
                    window.inputTextSummariesId.length > 0
                )
                    ? window.inputTextSummariesId[window.inputTextSummariesId.length - 1]
                    : null;

                if (!summaryId) {
                    alert('No input_text_summaries_id was retrieved.');
                    console.error('Missing input_text_summaries_id after summary step.');
                    sendAllBtn.disabled   = false;
                    sendAllBtn.textContent= "Send Selected";
                    toggleSpinner();
                    return;
                }

                // Now retrieve the chosen templates from FocusAreasModule
                const selectedTemplates = (
                    typeof FocusAreasModule !== 'undefined' &&
                    typeof FocusAreasModule.getSelectedTemplates === 'function'
                )
                    ? FocusAreasModule.getSelectedTemplates()
                    : [];

                // If none selected, use some default set
                const templatesToProcess = selectedTemplates.length > 0
                    ? selectedTemplates
                    : [
                        'Conceptual_Elaboration',
                        'Stakeholders',
                        'Requirements',
                        'Analysis_Functions',
                        'Analysis_Techniques',
                        'Q&A',
                        'Glossary'
                    ];

                if (templatesToProcess.length === 0) {
                    alert('No templates selected, and no defaults available.');
                    sendAllBtn.disabled   = false;
                    sendAllBtn.textContent= "Send Selected";
                    toggleSpinner();
                    return;
                }

                // Process them in sequence
                processTemplates([...templatesToProcess], () => {
                    // Done
                    sendAllBtn.disabled   = false;
                    sendAllBtn.textContent= "Send Selected";
                    updateFocusAreaRecordsMessage("Enter inputs to generate focus-area records.");
                    toggleSpinner();
                    updateSendButtonState();
                    updateSaveButtonState();
                }, promptTitle, summaryId);
            })
            .catch(error => {
                sendAllBtn.disabled   = false;
                sendAllBtn.textContent= "Send Selected";
                console.error('Error in the “Send Selected” workflow:', error);
                alert('Error occurred while processing your request. Please try again.');
            });
        });
    }

    /**
     * Normally, we would set up the "Save Data" button here. 
     * But to ensure your new overlay code can replace the #save-data-btn 
     * click handler, we will NOT rebind it a second time. 
     * The new script "save-data-enhancement.js" controls the entire
     * save process, calling handleSaveData() if user chooses
     * "Save as NEW Analysis Package".
     */
    function setupSaveButton() {
        // Original code was: 
        //   const saveDataBtn = document.getElementById('save-data-btn');
        //   saveDataBtn.addEventListener('click', () => {
        //       handleSaveData();
        //   });
        // We remove that so the "save-data-enhancement.js" overlay can do the hooking.
    }

    /**
     * Helper to unify all items that share the same 'focus_area_label' into a single item,
     * preserving the shape that /save_analysis_package.php expects (one item per focus area).
     *
     * This ensures the Generate tab doesn't create multiple single-record focus areas
     * with the same name.
     */
    function unifyFocusAreasByLabel(collectedData) {
        if (!Array.isArray(collectedData) || collectedData.length === 0) {
            return collectedData;
        }
        // group by label
        const mapByLabel = {};
        collectedData.forEach(item => {
            const label = item.focus_area_label || 'Unnamed Focus Area';
            if (!mapByLabel[label]) {
                // clone the item but remove the array fields
                mapByLabel[label] = {
                    focus_area_label:      label,
                    focus_area_value:      item.focus_area_value       || '',
                    collaboration_approach:item.collaboration_approach|| '',
                    // We'll keep these arrays and push into them
                    focusAreaRecords:   [],
                    stakeholderRecords: []
                };
            }
            // Merge the sub-arrays
            const newFARecs   = item.focusAreaRecords   || [];
            const newStakeRecs= item.stakeholderRecords || [];

            // If neither array is present but item has "properties", wrap it
            if (!newFARecs.length && !newStakeRecs.length && item.properties) {
                if (label.trim().toLowerCase() === 'analysis package stakeholders') {
                    newStakeRecs.push({
                        input_text_summaries_id: item.input_text_summaries_id || null,
                        grid_index: item.grid_index || 0,
                        properties: item.properties
                    });
                } else {
                    newFARecs.push({
                        input_text_summaries_id: item.input_text_summaries_id || null,
                        grid_index: item.grid_index || 0,
                        properties: item.properties
                    });
                }
            }

            mapByLabel[label].focusAreaRecords = mapByLabel[label].focusAreaRecords.concat(newFARecs);
            mapByLabel[label].stakeholderRecords = mapByLabel[label].stakeholderRecords.concat(newStakeRecs);
        });

        // Return one item per label
        return Object.keys(mapByLabel).map(label => {
            const base = mapByLabel[label];
            // shape the final object in the same form the Home tab expects
            return {
                focus_area_label:       label,
                focus_area_value:       base.focus_area_value,
                collaboration_approach: base.collaboration_approach,
                focusAreaRecords:       base.focusAreaRecords,
                stakeholderRecords:     base.stakeholderRecords
            };
        });
    }

    /**
     * The actual logic for "Save as NEW Analysis Package".
     * Called by the new overlay code if user picks "save new", or by legacy code.
     */
    function handleSaveData() {
        // 1) Collect from DynamicRibbonsModule
        const collectedData = (
            typeof DynamicRibbonsModule !== 'undefined' &&
            typeof DynamicRibbonsModule.collectRibbonsData === 'function'
        )
            ? DynamicRibbonsModule.collectRibbonsData()
            : [];

        if (!collectedData || collectedData.length === 0) {
            alert('No focus-area records to save.');
            return;
        }
        if (!window.inputTextSummariesId || window.inputTextSummariesId.length === 0) {
            alert('No input_text_summaries_id available for these records.');
            console.error('Missing input_text_summaries_id for focus-area records.');
            return;
        }

        // 2) Merge any multiple items that share the same label into one item
        const mergedData = unifyFocusAreasByLabel(collectedData);

        if (OverlayModule && typeof OverlayModule.showLoadingOverlay === 'function') {
            OverlayModule.showLoadingOverlay("Generating analysis package header...");
        } else {
            console.warn('OverlayModule.showLoadingOverlay not defined (non-blocking).');
        }

        communicationCount++;
        updateSendButtonState();
        updateSaveButtonState();
        toggleSpinner();

        // 3) Ask AI to propose an “Analysis_Package_Header”
        fetch(window.AxiaBAConfig.api_base_url + '/ai_helper.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': window.AxiaBAConfig.api_key
            },
            body: JSON.stringify({
                text: JSON.stringify(mergedData),
                template: 'Analysis_Package_Header'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                const headerData = data.data['Analysis Package Header'][0] || {};
                // Show overlay for user to finalize
                if (OverlayModule && typeof OverlayModule.showHeaderReviewOverlay === 'function') {
                    fetch('/get_focus_organization.php')
                        .then(orgRes => orgRes.json())
                        .then(orgData => {
                            if (orgData.status === 'success') {
                                headerData.organization_id = orgData.focus_org_id || 'default';
                            } else {
                                headerData.organization_id = 'default';
                            }
                            OverlayModule.showHeaderReviewOverlay(
                                headerData,
                                function(updatedHeader) {
                                    if (OverlayModule.showLoadingMask) {
                                        OverlayModule.showLoadingMask("Saving analysis package focus-area records...");
                                    }
                                    saveAnalysisPackageHeader(updatedHeader, mergedData, true);
                                },
                                function() {
                                    // If user cancels => pass no focus-area data
                                    saveAnalysisPackageHeader(headerData, null, false);
                                }
                            );
                        })
                        .catch(err => {
                            console.error('Unable to fetch focus org. Defaulting to "default".', err);
                            headerData.organization_id = 'default';
                            OverlayModule.showHeaderReviewOverlay(
                                headerData,
                                function(updatedHeader) {
                                    if (OverlayModule.showLoadingMask) {
                                        OverlayModule.showLoadingMask("Saving analysis package data...");
                                    }
                                    saveAnalysisPackageHeader(updatedHeader, mergedData, true);
                                },
                                function() {
                                    saveAnalysisPackageHeader(headerData, null, false);
                                }
                            );
                        });
                } else {
                    console.error('OverlayModule or showHeaderReviewOverlay function not defined.');
                }
            } else {
                const errMsg = data.message || 'Unknown error generating header.';
                alert(`API Error: ${errMsg}`);
                if (OverlayModule && OverlayModule.hideOverlay) {
                    OverlayModule.hideOverlay();
                }
            }
        })
        .catch(error => {
            alert(`Error generating package header: ${error.message}`);
            if (OverlayModule && OverlayModule.hideOverlay) {
                OverlayModule.hideOverlay();
            }
        })
        .finally(() => {
            communicationCount--;
            updateSendButtonState();
            updateSaveButtonState();
            toggleSpinner();
        });
    }

    /**
     * Calls /save_analysis_package.php with the new header plus collected data.
     * If showMessageOverlay = true, we display a success message and optionally
     * let the user open the new package in the Refine tab.
     */
    function saveAnalysisPackageHeader(headerData, mergedData, showMessageOverlay) {
        const storeUrl = '/save_analysis_package.php';
        const payload = {
            headerData: headerData,
            collectedData: mergedData,
            input_text_summaries_id: window.inputTextSummariesId
        };

        fetch(storeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                if (showMessageOverlay && OverlayModule) {
                    const messageHtml =
                        `Analysis Package saved successfully.<br>` +
                        `ID: ${data.analysis_package_headers_id}<br>` +
                        `Package Name: ${data.package_name}<br><br>` +
                        `<a href="#" id="openRefineLink" style="color:#007bff; text-decoration:underline; font-weight:bold;">Open Package in Refine Tab</a>`;
                    if (OverlayModule.showMessageOverlay) {
                        OverlayModule.showMessageOverlay(
                            messageHtml,
                            () => {
                                // Once the user closes the message overlay,
                                // clear the ribbons
                                if (typeof DynamicRibbonsModule !== 'undefined') {
                                    DynamicRibbonsModule.clearRibbons();
                                }
                            },
                            true
                        );
                        setTimeout(() => {
                            const link = document.getElementById('openRefineLink');
                            if (link) {
                                link.onclick = evt => {
                                    evt.preventDefault();
                                    if (typeof DynamicRibbonsModule !== 'undefined') {
                                        DynamicRibbonsModule.clearRibbons();
                                    }
                                    if (typeof window.openRefineTabAndSelectPackage === 'function') {
                                        window.openRefineTabAndSelectPackage(
                                            data.analysis_package_headers_id,
                                            data.package_name
                                        );
                                    } else {
                                        alert('Refine tab function not found. Please open it manually.');
                                    }
                                    if (OverlayModule.hideOverlay) {
                                        OverlayModule.hideOverlay();
                                    }
                                };
                            }
                        }, 400);
                    } else {
                        console.warn('OverlayModule.showMessageOverlay not defined.');
                    }
                } else {
                    if (OverlayModule && OverlayModule.hideOverlay) {
                        OverlayModule.hideOverlay();
                    }
                }
            } else {
                const errMsg = data.message || 'Failed to save Analysis Package.';
                alert(errMsg);
                if (OverlayModule && OverlayModule.hideOverlay) {
                    OverlayModule.hideOverlay();
                }
            }
        })
        .catch(error => {
            alert(`Error saving Analysis Package: ${error.message}`);
            if (OverlayModule && OverlayModule.hideOverlay) {
                OverlayModule.hideOverlay();
            }
        });
    }

    /**
     * Initializes this module: hooking up the “Send Selected” button,
     * but intentionally NOT re-binding #save-data-btn (the new overlay code
     * does that).
     */
    function initializeProcessFeedback() {
        setupSendButton();
        setupSaveButton();
    }

    // Expose the public methods
    return {
        initializeProcessFeedback: initializeProcessFeedback,
        updateSendButtonState: updateSendButtonState,
        updateSaveButtonState: updateSaveButtonState,
        updateFocusAreaRecordsMessage: updateFocusAreaRecordsMessage,
        handleSaveData: handleSaveData  // so the new code can call it
    };
})();
