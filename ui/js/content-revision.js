/*
   /js/content-revision.js
   This module displays a “Content Revision” overlay for editing existing
   focus-area records within an analysis package in the AII_AxiaBA_UI schema.
   It can show both active and soft-deleted records, allow users to add new
   records, and then commit changes to create a new focus-area version.
*/
(function() {
    /**
     * Initializes the Content Revision Overlay.
     *
     * @param {string} focusAreaName - Name of the focus area to revise.
     * @param {string} packageName   - Name of the analysis package.
     * @param {number} packageId     - ID of the analysis package.
     * @param {number} focusAreaVersionNumber - The current focus-area version.
     */
    function init(focusAreaName, packageName, packageId, focusAreaVersionNumber) {
        fetchFocusAreaRecords(focusAreaName, packageId, true /*include soft-deleted*/)
            .then(records => {
                renderOverlay(focusAreaName, packageName, packageId, focusAreaVersionNumber, records);
            })
            .catch(error => {
                console.error('Error fetching focus-area records:', error);
                alert('Failed to load content for revision.');
            });
    }

    /**
     * Fetches records for the specified focus area. Optionally includes soft-deleted items.
     *
     * @param {string} focusAreaName - The focus area identifier.
     * @param {number} packageId - The analysis package ID.
     * @param {boolean} showDeleted - Whether to include soft-deleted records.
     * @returns {Promise<Array>} The records for that focus area.
     */
    function fetchFocusAreaRecords(focusAreaName, packageId, showDeleted) {
        const showDeletedParam = showDeleted ? '1' : '0';
        const url = `fetch_analysis_package_focus_area_records.php`
                  + `?package_id=${encodeURIComponent(packageId)}`
                  + `&show_deleted=${showDeletedParam}`;
        return fetch(url)
            .then(response => response.ok ? response.json() : Promise.reject('Error fetching data.'))
            .then(data => {
                // The server returns something like:
                // {
                //   "focus_area_version_number": 3,
                //   "focusAreas": {
                //       "<focusAreaName>": [ ...array of records... ],
                //       ...
                //   }
                // }
                const focusAreas = data.focusAreas || {};
                if (focusAreas[focusAreaName]) {
                    return focusAreas[focusAreaName];
                }
                return Promise.reject('Focus area not found in this package.');
            });
    }

    /**
     * Renders an overlay where users can revise record content for the focus area.
     */
    function renderOverlay(focusAreaName, packageName, packageId, focusAreaVersionNumber, records) {
        removeExistingOverlay();

        const overlay = document.createElement('div');
        overlay.classList.add('content-revision-overlay');
        overlay.id = 'content-revision-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'content-revision-title');

        const formContainer = document.createElement('div');
        formContainer.classList.add('content-revision-form');

        // Close button
        const closeButton = document.createElement('span');
        closeButton.classList.add('close-content-revision-overlay');
        closeButton.innerHTML = '&times;';
        closeButton.setAttribute('aria-label', 'Close Content Revision Form');
        closeButton.setAttribute('role', 'button');
        closeButton.tabIndex = 0;

        // Title and subtitle
        const title = document.createElement('h2');
        title.textContent = 'Content Revision';
        title.id = 'content-revision-title';

        const subtitle = document.createElement('p');
        subtitle.textContent = `Edit or add records for the “${focusAreaName}” focus area in package #${packageId} (${packageName}), version ${focusAreaVersionNumber}.`;

        // Form wrapper
        const formContentWrapper = document.createElement('div');
        formContentWrapper.classList.add('form-content-wrapper');

        // Main form
        const form = document.createElement('form');
        form.id = 'content-revision-form-element';

        // “Add Record” button
        const addRecordButton = document.createElement('button');
        addRecordButton.type = 'button';
        addRecordButton.textContent = 'Add Record';
        addRecordButton.classList.add('add-record-btn');
        addRecordButton.addEventListener('click', () => {
            addNewRecord(form, focusAreaName);
        });
        form.appendChild(addRecordButton);

        // Create a fieldset for each existing record
        records.forEach((record, index) => {
            const fieldset = createRecordFieldset(record, index, focusAreaName);
            form.appendChild(fieldset);
        });

        // Buttons
        const buttonContainer = document.createElement('div');
        buttonContainer.classList.add('button-container');

        const commitButton = document.createElement('button');
        commitButton.type = 'button';
        commitButton.textContent = 'Commit';
        commitButton.classList.add('commit-btn');

        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.textContent = 'Cancel';
        cancelButton.classList.add('cancel-btn');

        buttonContainer.appendChild(commitButton);
        buttonContainer.appendChild(cancelButton);
        form.appendChild(buttonContainer);

        formContentWrapper.appendChild(form);
        formContainer.appendChild(closeButton);
        formContainer.appendChild(title);
        formContainer.appendChild(subtitle);
        formContainer.appendChild(formContentWrapper);
        overlay.appendChild(formContainer);
        document.body.appendChild(overlay);

        // Event listeners
        closeButton.addEventListener('click', closeOverlay);
        closeButton.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                closeOverlay();
            }
        });
        cancelButton.addEventListener('click', closeOverlay);

        commitButton.addEventListener('click', () => {
            showConfirmationDialog(focusAreaVersionNumber, () => {
                handleFormSubmit(form, focusAreaName, packageName, packageId, focusAreaVersionNumber);
            });
        });

        document.addEventListener('keydown', handleEscKey);
    }

    /**
     * Creates a fieldset for a single record, handling removal/restoration.
     */
    function createRecordFieldset(record, index, focusAreaName) {
        const fieldset = document.createElement('fieldset');
        fieldset.classList.add('record-fieldset');
        fieldset.dataset.recordIndex = index;

        if (record.is_deleted && record.is_deleted == 1) {
            fieldset.classList.add('deleted-record');
        }

        const legend = document.createElement('legend');
        legend.textContent = `Record ${index + 1}`;
        fieldset.appendChild(legend);

        // Button: Remove/Restore
        const actionBtn = document.createElement('button');
        actionBtn.type = 'button';
        actionBtn.classList.add('record-action-btn');

        if (record.is_deleted && record.is_deleted == 1) {
            actionBtn.textContent = 'Restore Record';
            actionBtn.addEventListener('click', () => restoreRecord(fieldset));
        } else {
            actionBtn.textContent = 'Remove Record';
            actionBtn.addEventListener('click', () => removeRecord(fieldset));
        }
        fieldset.appendChild(actionBtn);

        // We'll not allow editing ephemeral fields directly. Only “properties” are editable text fields.
        const skipKeys = [
            'focusAreaRecordID',
            'focus_area_name',
            'is_deleted',
            'grid_index',
            'input_text_summaries_id',
            'input_text_title',
            'input_text_summary',
            'input_text',
            'packageId'
        ];

        for (const [key, val] of Object.entries(record)) {
            if (skipKeys.includes(key)) {
                continue;
            }
            // Show as a text input
            const label = document.createElement('label');
            label.textContent = key;
            label.setAttribute('for', `field-${index}-${key}`);

            const input = document.createElement('input');
            input.type = 'text';
            input.id = `field-${index}-${key}`;
            input.name = `focus_area_records[${index}][properties][${key}]`;
            input.value = val;

            const fieldContainer = document.createElement('div');
            fieldContainer.classList.add('field-container');
            fieldContainer.appendChild(label);
            fieldContainer.appendChild(input);
            fieldset.appendChild(fieldContainer);
        }

        // Hidden: focus_area_name
        const hiddenFocusArea = document.createElement('input');
        hiddenFocusArea.type = 'hidden';
        hiddenFocusArea.name = `focus_area_records[${index}][focus_area_name]`;
        hiddenFocusArea.value = focusAreaName;
        fieldset.appendChild(hiddenFocusArea);

        // Hidden: is_deleted
        const hiddenDeleted = document.createElement('input');
        hiddenDeleted.type = 'hidden';
        hiddenDeleted.name = `focus_area_records[${index}][is_deleted]`;
        hiddenDeleted.value = record.is_deleted || '0';
        fieldset.appendChild(hiddenDeleted);

        // Hidden: grid_index
        if (record.grid_index !== undefined) {
            const hiddenGridIndex = document.createElement('input');
            hiddenGridIndex.type = 'hidden';
            hiddenGridIndex.name = `focus_area_records[${index}][grid_index]`;
            hiddenGridIndex.value = record.grid_index;
            fieldset.appendChild(hiddenGridIndex);
        }

        // Hidden: focusAreaRecordID
        if (record.focusAreaRecordID !== undefined) {
            const hiddenFARecId = document.createElement('input');
            hiddenFARecId.type = 'hidden';
            hiddenFARecId.name = `focus_area_records[${index}][focusAreaRecordID]`;
            hiddenFARecId.value = record.focusAreaRecordID;
            fieldset.appendChild(hiddenFARecId);
        }

        // If input_text_summaries exist, we store them in hidden fields too
        if (record.input_text_summaries_id) {
            const hTsId = document.createElement('input');
            hTsId.type = 'hidden';
            hTsId.name = `focus_area_records[${index}][input_text_summaries_id]`;
            hTsId.value = record.input_text_summaries_id;
            fieldset.appendChild(hTsId);
        }
        if (record.input_text_title) {
            const hTitle = document.createElement('input');
            hTitle.type = 'hidden';
            hTitle.name = `focus_area_records[${index}][input_text_title]`;
            hTitle.value = record.input_text_title;
            fieldset.appendChild(hTitle);
        }
        if (record.input_text_summary) {
            const hSummary = document.createElement('input');
            hSummary.type = 'hidden';
            hSummary.name = `focus_area_records[${index}][input_text_summary]`;
            hSummary.value = record.input_text_summary;
            fieldset.appendChild(hSummary);
        }
        if (record.input_text) {
            const hFull = document.createElement('input');
            hFull.type = 'hidden';
            hFull.name = `focus_area_records[${index}][input_text]`;
            hFull.value = record.input_text;
            fieldset.appendChild(hFull);
        }

        return fieldset;
    }

    /**
     * Adds a brand-new record fieldset with default is_deleted=0.
     */
    function addNewRecord(form, focusAreaName) {
        const existingFieldsets = form.querySelectorAll('.record-fieldset');
        const newIndex = existingFieldsets.length;

        // Let user define property names if none exist
        let templateRecord = {};
        if (existingFieldsets.length > 0) {
            const sampleInputs = existingFieldsets[0].querySelectorAll(
                'input[type="text"][name^="focus_area_records["][name*="[properties]"]'
            );
            sampleInputs.forEach(input => {
                const match = input.name.match(/\[properties\]\[(.+)\]$/);
                if (match) {
                    templateRecord[match[1]] = '';
                }
            });
        } else {
            const fieldNamesStr = prompt('Enter comma-separated field names for the new record:');
            if (!fieldNamesStr) return;
            const fieldNames = fieldNamesStr.split(',').map(s => s.trim());
            fieldNames.forEach(name => {
                templateRecord[name] = '';
            });
        }
        templateRecord.is_deleted = 0;

        const fs = createRecordFieldset(templateRecord, newIndex, focusAreaName);
        const buttonContainer = form.querySelector('.button-container');
        form.insertBefore(fs, buttonContainer);
    }

    /**
     * Marks a record as soft-deleted => is_deleted=1.
     */
    function removeRecord(fieldset) {
        const isDeletedInput = fieldset.querySelector('input[name$="[is_deleted]"]');
        if (isDeletedInput) {
            isDeletedInput.value = '1';
            fieldset.classList.add('deleted-record');
            let btn = fieldset.querySelector('.record-action-btn');
            btn.textContent = 'Restore Record';
            btn.replaceWith(btn.cloneNode(true));
            btn = fieldset.querySelector('.record-action-btn');
            btn.addEventListener('click', () => restoreRecord(fieldset));
        } else {
            // If brand new => remove from DOM
            fieldset.remove();
        }
    }

    /**
     * Restores a soft-deleted record => is_deleted=0.
     */
    function restoreRecord(fieldset) {
        const isDeletedInput = fieldset.querySelector('input[name$="[is_deleted]"]');
        if (isDeletedInput) {
            isDeletedInput.value = '0';
            fieldset.classList.remove('deleted-record');
            let btn = fieldset.querySelector('.record-action-btn');
            btn.textContent = 'Remove Record';
            btn.replaceWith(btn.cloneNode(true));
            btn = fieldset.querySelector('.record-action-btn');
            btn.addEventListener('click', () => removeRecord(fieldset));
        }
    }

    /**
     * Asks for user confirmation before committing changes (creating a new version).
     */
    function showConfirmationDialog(focusAreaVersionNumber, onConfirm) {
        const proceed = confirm(
            `You are about to create a new version from version ${focusAreaVersionNumber}. Continue?`
        );
        if (proceed) {
            onConfirm();
        }
    }

    /**
     * Collects form data and sends it to process_content_revision.php for version creation.
     */
    function handleFormSubmit(form, focusAreaName, packageName, packageId, focusAreaVersionNumber) {
        const formData = new FormData(form);
        const rawObj = {};
        for (const [k, v] of formData.entries()) {
            rawObj[k] = v;
        }

        const dataToSend = {
            focus_area_name:          focusAreaName,
            package_name:             packageName,
            package_id:               packageId,
            focus_area_version_id:    focusAreaVersionNumber, // renamed in payload
            focus_area_records: []
        };

        const propertiesRegex   = /^focus_area_records\[(\d+)\]\[properties\]\[(.+)\]$/;
        const focusNameRegex    = /^focus_area_records\[(\d+)\]\[focus_area_name\]$/;
        const isDeletedRegex    = /^focus_area_records\[(\d+)\]\[is_deleted\]$/;
        const focusAreaRecIdRegex  = /^focus_area_records\[(\d+)\]\[focusAreaRecordID\]$/;
        const gridIndexRegex    = /^focus_area_records\[(\d+)\]\[grid_index\]$/;
        const inputTextRegex    = /^focus_area_records\[(\d+)\]\[(input_text_summaries_id|input_text_title|input_text_summary|input_text)\]$/;

        const recordsMap = {};

        for (const [key, val] of Object.entries(rawObj)) {
            let match = key.match(propertiesRegex);
            if (match) {
                const idx = parseInt(match[1], 10);
                const fieldKey = match[2];
                if (!recordsMap[idx]) {
                    recordsMap[idx] = { properties: {} };
                }
                recordsMap[idx].properties[fieldKey] = val;
                continue;
            }
            match = key.match(focusNameRegex);
            if (match) {
                const idx = parseInt(match[1], 10);
                if (!recordsMap[idx]) {
                    recordsMap[idx] = { properties: {} };
                }
                recordsMap[idx].focus_area_name = val;
                continue;
            }
            match = key.match(isDeletedRegex);
            if (match) {
                const idx = parseInt(match[1], 10);
                if (!recordsMap[idx]) {
                    recordsMap[idx] = { properties: {} };
                }
                recordsMap[idx].is_deleted = val;
                continue;
            }
            match = key.match(focusAreaRecIdRegex);
            if (match) {
                const idx = parseInt(match[1], 10);
                if (!recordsMap[idx]) {
                    recordsMap[idx] = { properties: {} };
                }
                recordsMap[idx].focusAreaRecordID = val;
                continue;
            }
            match = key.match(gridIndexRegex);
            if (match) {
                const idx = parseInt(match[1], 10);
                if (!recordsMap[idx]) {
                    recordsMap[idx] = { properties: {} };
                }
                recordsMap[idx].grid_index = parseInt(val, 10);
                continue;
            }
            match = key.match(inputTextRegex);
            if (match) {
                const idx = parseInt(match[1], 10);
                const groupKey = match[2];
                if (!recordsMap[idx]) {
                    recordsMap[idx] = { properties: {} };
                }
                recordsMap[idx][groupKey] = val;
                continue;
            }
        }

        for (const [idx, recData] of Object.entries(recordsMap)) {
            dataToSend.focus_area_records.push(recData);
        }

        console.log('Submitting content revision:', dataToSend);

        showPageMaskSpinner('Committing content revision...');
        fetch('process_content_revision.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dataToSend)
        })
        .then(resp => {
            console.log('Response status:', resp.status);
            return resp.json().then(data => {
                if (!resp.ok) {
                    return Promise.reject(data);
                }
                return data;
            });
        })
        .then(data => {
            if (data.status === 'success') {
                alert('Content revision saved successfully.');
                closeOverlay();
                if (typeof reloadRefineTabAndOpenPackage === 'function') {
                    reloadRefineTabAndOpenPackage(packageId, focusAreaName);
                } else {
                    window.location.reload();
                }
            } else {
                console.error('Error saving content revision:', data.message || data.error);
                alert('Failed to save content revision.');
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            if (error.message) {
                alert('Failed to save content revision: ' + error.message);
            } else if (error.error) {
                alert('Failed to save content revision: ' + error.error);
            } else {
                alert('An error occurred while submitting the form.');
            }
        })
        .finally(() => {
            hidePageMaskSpinner();
        });
    }

    function removeExistingOverlay() {
        const existing = document.getElementById('content-revision-overlay');
        if (existing) {
            existing.remove();
        }
    }

    function handleEscKey(e) {
        if (e.key === 'Escape') {
            closeOverlay();
        }
    }

    function closeOverlay() {
        const overlay = document.getElementById('content-revision-overlay');
        if (overlay) {
            overlay.remove();
        }
        document.removeEventListener('keydown', handleEscKey);
    }

    function showPageMaskSpinner(message) {
        const existingMask = document.getElementById('page-mask');
        if (existingMask) return;
        const pageMask = document.createElement('div');
        pageMask.id = 'page-mask';
        pageMask.classList.add('page-mask');
        const spinner = document.createElement('div');
        spinner.classList.add('spinner');
        const msg = document.createElement('div');
        msg.classList.add('spinner-message');
        msg.textContent = message;
        pageMask.appendChild(spinner);
        pageMask.appendChild(msg);
        document.body.appendChild(pageMask);
    }

    function hidePageMaskSpinner() {
        const existing = document.getElementById('page-mask');
        if (existing) {
            existing.remove();
        }
    }

    function reloadRefineTabAndOpenPackage(packageId, focusAreaName) {
        if (typeof window.reloadRefineTabAndOpenPackage === 'function') {
            window.reloadRefineTabAndOpenPackage(packageId, focusAreaName);
        } else {
            window.location.reload();
        }
    }

    // Expose
    window.ContentRevisionModule = {
        init
    };
})();
