// /js/focus-areas.js

var FocusAreasModule = (function() {
    /**
     * Initializes the Focus Areas functionalities.
     */
    function initializeFocusAreas() {
        const chooseFocusForm = document.getElementById('chooseFocusForm');
        if (!chooseFocusForm) {
            console.warn('FocusAreasModule: "chooseFocusForm" not found in the DOM. Skipping focus area init.');
            return;
        }
        // Fetch and populate template checkboxes
        loadTemplates();
    }

    /**
     * Fetches templates and populates the Choose Focus Areas form.
     */
    function loadTemplates() {
        fetch(window.AxiaBAConfig.api_base_url + '/get_templates.php', {
            method: 'GET',
            headers: {
                // Security Key
                'X-API-Key': window.AxiaBAConfig.api_key
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Fetched focus areas data:', data);
            if (data && Array.isArray(data.templates)) {
                const sortedTemplates = sortTopLevelAndDirectories(data.templates);
                populateTemplateCheckboxes(
                    sortedTemplates,
                    document.getElementById('chooseFocusForm'),
                    ''
                );
                setupChooseFocusFormListeners();
                setupGlobalControls();
                finalizeDirectoryStyling();

                // Update button states (Send/Save) if ProcessFeedbackModule is available
                if (typeof ProcessFeedbackModule !== 'undefined') {
                    if (typeof ProcessFeedbackModule.updateSendButtonState === 'function') {
                        ProcessFeedbackModule.updateSendButtonState();
                    }
                    if (typeof ProcessFeedbackModule.updateSaveButtonState === 'function') {
                        ProcessFeedbackModule.updateSaveButtonState();
                    }
                }
            } else {
                console.error('Error: Expected data.templates to be an array.', data);
                updateFocusAreaEnhancementsMessage('Error: Invalid data format for templates.');
                if (typeof ProcessFeedbackModule !== 'undefined') {
                    if (typeof ProcessFeedbackModule.updateSendButtonState === 'function') {
                        ProcessFeedbackModule.updateSendButtonState();
                    }
                    if (typeof ProcessFeedbackModule.updateSaveButtonState === 'function') {
                        ProcessFeedbackModule.updateSaveButtonState();
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error fetching templates:', error);
            updateFocusAreaEnhancementsMessage(`Error fetching templates: ${error.message || 'Unknown error.'}`);

            // Disable buttons due to fetch error
            if (typeof ProcessFeedbackModule !== 'undefined') {
                if (typeof ProcessFeedbackModule.updateSendButtonState === 'function') {
                    ProcessFeedbackModule.updateSendButtonState();
                }
                if (typeof ProcessFeedbackModule.updateSaveButtonState === 'function') {
                    ProcessFeedbackModule.updateSaveButtonState();
                }
            }
        });
    }

    /**
     * Sort top-level JSON templates first, then directories.
     */
    function sortTopLevelAndDirectories(templates) {
        const topLevelFiles = [];
        const directories   = [];

        templates.forEach(tpl => {
            if (tpl.subtemplates && Array.isArray(tpl.subtemplates)) {
                directories.push(tpl);
            } else {
                topLevelFiles.push(tpl);
            }
        });
        return [...topLevelFiles, ...directories];
    }

    /**
     * Populates the Choose Focus Areas form with template checkboxes
     * (supports nested subtemplates).
     */
    function populateTemplateCheckboxes(templates, container, currentPath) {
        templates.forEach(template => {
            if (template.subtemplates && Array.isArray(template.subtemplates)) {
                // Directory with subtemplates
                const directoryDiv = document.createElement('div');
                directoryDiv.className = 'template-directory';

                const directoryHeader = document.createElement('div');
                directoryHeader.className = 'directory-header';

                const toggleButton = document.createElement('span');
                toggleButton.className = 'toggle-button';
                toggleButton.textContent = '➕'; // collapsed initially

                const directoryLabel = document.createElement('span');
                directoryLabel.className = 'directory-label';
                directoryLabel.textContent = template.name.replace(/_/g, ' ');

                const selectAllLink = document.createElement('a');
                selectAllLink.href = '#';
                selectAllLink.className = 'select-all-link';
                selectAllLink.textContent = 'Select All';
                selectAllLink.style.marginLeft = '10px';

                const unselectAllLink = document.createElement('a');
                unselectAllLink.href = '#';
                unselectAllLink.className = 'unselect-all-link';
                unselectAllLink.textContent = 'Unselect All';
                unselectAllLink.style.marginLeft = '5px';

                const subtemplatesContainer = document.createElement('div');
                subtemplatesContainer.className = 'subtemplates-container hidden';

                const newPath = currentPath
                    ? `${currentPath}/${template.name}`
                    : template.name;

                // Recursively populate subtemplates
                populateTemplateCheckboxes(template.subtemplates, subtemplatesContainer, newPath);

                directoryHeader.appendChild(toggleButton);
                directoryHeader.appendChild(directoryLabel);
                directoryHeader.appendChild(selectAllLink);
                directoryHeader.appendChild(unselectAllLink);

                directoryDiv.appendChild(directoryHeader);
                directoryDiv.appendChild(subtemplatesContainer);
                container.appendChild(directoryDiv);

                // Expand/collapse logic
                toggleButton.addEventListener('click', () => {
                    if (subtemplatesContainer.classList.contains('hidden')) {
                        subtemplatesContainer.classList.remove('hidden');
                        toggleButton.textContent = '➖';
                    } else {
                        subtemplatesContainer.classList.add('hidden');
                        toggleButton.textContent = '➕';
                    }
                    updateButtonStates();
                });

                // “Select All”
                selectAllLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    const checkboxes = subtemplatesContainer.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(cb => (cb.checked = true));
                    updateButtonStates();
                    updateSelectAllUnselectAllStyling(
                        subtemplatesContainer,
                        selectAllLink,
                        unselectAllLink
                    );
                });

                // “Unselect All”
                unselectAllLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    const checkboxes = subtemplatesContainer.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(cb => (cb.checked = false));
                    updateButtonStates();
                    updateSelectAllUnselectAllStyling(
                        subtemplatesContainer,
                        selectAllLink,
                        unselectAllLink
                    );
                });
            } else if (template.name) {
                // Single .json file
                const checkboxWrapper = document.createElement('div');
                checkboxWrapper.className = 'form-check';

                const checkbox = document.createElement('input');
                checkbox.type     = 'checkbox';
                checkbox.className= 'form-check-input';

                const templatePathWithoutJson = currentPath
                    ? `${currentPath}/${template.name.replace('.json', '')}`
                    : template.name.replace('.json', '');

                checkbox.id    = `template-${templatePathWithoutJson.replace(/\//g, '-')}`;
                checkbox.name  = templatePathWithoutJson;
                checkbox.value = templatePathWithoutJson;

                checkbox.checked = false;

                const label = document.createElement('label');
                label.className = 'form-check-label';
                label.htmlFor   = checkbox.id;
                label.textContent = templatePathWithoutJson
                    .split('/')
                    .pop()
                    .replace(/_/g, ' ');

                checkboxWrapper.appendChild(checkbox);
                checkboxWrapper.appendChild(label);
                container.appendChild(checkboxWrapper);
            }
        });
    }

    /**
     * Called after populating directories to fix “Select All” coloring initially.
     */
    function finalizeDirectoryStyling() {
        const directoryWrappers = document.querySelectorAll('.template-directory');
        directoryWrappers.forEach(dirWrap => {
            const selectAllLink       = dirWrap.querySelector('.select-all-link');
            const unselectAllLink     = dirWrap.querySelector('.unselect-all-link');
            const subtemplatesContainer = dirWrap.querySelector('.subtemplates-container');
            if (selectAllLink && unselectAllLink && subtemplatesContainer) {
                updateSelectAllUnselectAllStyling(
                    subtemplatesContainer,
                    selectAllLink,
                    unselectAllLink
                );
            }
        });
    }

    /**
     * Updates “Select All” / “Unselect All” link colors (grey/blue) 
     * based on how many items are selected.
     */
    function updateSelectAllUnselectAllStyling(subtemplatesContainer, selectAllLink, unselectAllLink) {
        const checkboxes = subtemplatesContainer.querySelectorAll('input[type="checkbox"]');
        const total = checkboxes.length;
        let selectedCount = 0;
        checkboxes.forEach(cb => {
            if (cb.checked) selectedCount++;
        });

        selectAllLink.classList.remove('select-all-grey', 'select-all-blue');
        unselectAllLink.classList.remove('select-all-grey', 'select-all-blue');

        if (selectedCount === total) {
            // All => “Select All” grey, “Unselect All” blue
            selectAllLink.classList.add('select-all-grey');
            unselectAllLink.classList.add('select-all-blue');
        } else if (selectedCount === 0) {
            // None => “Select All” blue, “Unselect All” grey
            selectAllLink.classList.add('select-all-blue');
            unselectAllLink.classList.add('select-all-grey');
        } else {
            // Mixed => both blue
            selectAllLink.classList.add('select-all-blue');
            unselectAllLink.classList.add('select-all-blue');
        }
    }

    /**
     * For “Stakeholders.json” only, we might prepend user email to the text if needed.
     */
    function computePrependedText(userEmail, originalText) {
        const lineToPrepend = (
            `The facilitating stakeholder for this evolution has an email address of ${userEmail} - ` +
            `this is the AxiaBA User who is responsible to create and manage a collection of analysis packages ` +
            `for enabling stakeholder collaborations, collating stakeholder feedback, and publishing finalized ` +
            `analysis outputs and collaboration artifacts.\n\n`
        );
        if (!originalText.startsWith(lineToPrepend.trim())) {
            return lineToPrepend + originalText;
        }
        return originalText;
    }

    /**
     * Gets array of all selected template names.
     */
    function getSelectedTemplates() {
        const checkboxes = document.querySelectorAll('#chooseFocusForm input[type="checkbox"]:checked');
        return Array.from(checkboxes).map(cb => cb.value);
    }

    /**
     * Listen for user changes in the Choose Focus Areas form and update button states.
     */
    function setupChooseFocusFormListeners() {
        const chooseFocusForm = document.getElementById('chooseFocusForm');
        if (chooseFocusForm) {
            chooseFocusForm.addEventListener('change', () => {
                updateButtonStates();
            });
        } else {
            console.warn('FocusAreasModule: "chooseFocusForm" not found when setting up listeners.');
        }
    }

    /**
     * Sets up Expand All / Collapse All controls at the top of the Choose AI Focus Areas section
     */
    function setupGlobalControls() {
        const chooseFocusRibbonBody = document.getElementById('chooseFocusRibbonBody');
        if (!chooseFocusRibbonBody) {
            console.warn('FocusAreasModule: "chooseFocusRibbonBody" not found in the DOM.');
            return;
        }

        const controlsContainer = document.createElement('div');
        controlsContainer.className = 'global-controls';

        const expandAllButton = document.createElement('button');
        expandAllButton.type = 'button';
        expandAllButton.id   = 'expand-all-button';
        expandAllButton.textContent = 'Expand All';
        expandAllButton.style.marginRight = '10px';

        const collapseAllButton = document.createElement('button');
        collapseAllButton.type = 'button';
        collapseAllButton.id   = 'collapse-all-button';
        collapseAllButton.textContent = 'Collapse All';

        controlsContainer.appendChild(expandAllButton);
        controlsContainer.appendChild(collapseAllButton);

        chooseFocusRibbonBody.insertBefore(controlsContainer, chooseFocusRibbonBody.firstChild);

        // Expand All
        expandAllButton.addEventListener('click', () => {
            const toggleButtons = chooseFocusRibbonBody.querySelectorAll('.toggle-button');
            toggleButtons.forEach(toggleButton => {
                const subtemplatesContainer = toggleButton.parentElement.nextElementSibling;
                if (subtemplatesContainer.classList.contains('hidden')) {
                    subtemplatesContainer.classList.remove('hidden');
                    toggleButton.textContent = '➖';
                }
            });
            updateButtonStates();
        });

        // Collapse All
        collapseAllButton.addEventListener('click', () => {
            const toggleButtons = chooseFocusRibbonBody.querySelectorAll('.toggle-button');
            toggleButtons.forEach(toggleButton => {
                const subtemplatesContainer = toggleButton.parentElement.nextElementSibling;
                if (!subtemplatesContainer.classList.contains('hidden')) {
                    subtemplatesContainer.classList.add('hidden');
                    toggleButton.textContent = '➕';
                }
            });
            updateButtonStates();
        });
    }

    /**
     * Updates the status message for focus-area enhancements (replaces old “[redacted]”).
     */
    function updateFocusAreaEnhancementsMessage(message) {
        const enhancementsParagraph = document.getElementById('focus-area-ai-message');
        if (enhancementsParagraph) {
            enhancementsParagraph.textContent = message;
        } else {
            console.warn('FocusAreaModule: focus-area-ai-message element not found in the DOM.');
        }
    }

    /**
     * Re-checks button states for “Send Selected” & “Save Data,” plus link styling.
     */
    function updateButtonStates() {
        if (typeof ProcessFeedbackModule !== 'undefined') {
            if (typeof ProcessFeedbackModule.updateSendButtonState === 'function') {
                ProcessFeedbackModule.updateSendButtonState();
            }
            if (typeof ProcessFeedbackModule.updateSaveButtonState === 'function') {
                ProcessFeedbackModule.updateSaveButtonState();
            }
        }
        // Re-check “Select All” / “Unselect All” styling
        const directoryWrappers = document.querySelectorAll('.template-directory');
        directoryWrappers.forEach(dirWrap => {
            const selectAllLink   = dirWrap.querySelector('.select-all-link');
            const unselectAllLink = dirWrap.querySelector('.unselect-all-link');
            const subtemplatesContainer = dirWrap.querySelector('.subtemplates-container');
            if (selectAllLink && unselectAllLink && subtemplatesContainer) {
                updateSelectAllUnselectAllStyling(
                    subtemplatesContainer,
                    selectAllLink,
                    unselectAllLink
                );
            }
        });
    }

    return {
        initializeFocusAreas: initializeFocusAreas,
        getSelectedTemplates: getSelectedTemplates,
        computePrependedText: computePrependedText
    };
})();
