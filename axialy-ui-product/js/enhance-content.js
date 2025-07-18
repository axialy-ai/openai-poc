/***********************************************************************************
 * /js/enhance-content.js
 * ***********************************************************************************/
var EnhanceContentModule = (function() {
    let lastPackageId            = 0;
    let lastPackageName          = '';
    let lastFocusAreaName        = '';
    let lastFocusAreaVersionNum  = 0;  // numeric version (for display only)
    let lastFocusAreaVersionId   = 0;  // actual row ID in analysis_package_focus_area_versions
    let lastUserInstruction      = ''; // to pre-fill the text area
    let lastSummaryOfRevisions   = '';
    let originalRecords          = [];

    /**
     * Initializes the flow for "Enhance Focus Area."
     *
     * @param {number} packageId
     * @param {string} packageName
     * @param {string} focusAreaName
     * @param {string} [defaultUserInstruction] - optional text to pre-populate instructions
     */
    function initEnhancement(packageId, packageName, focusAreaName, defaultUserInstruction = '') {
        lastPackageId     = packageId;
        lastPackageName   = packageName;
        lastFocusAreaName = focusAreaName;
        // If the caller provides "actionContent," we store it in lastUserInstruction
        lastUserInstruction = defaultUserInstruction;

        showSpinnerOverlay('Loading records for enhancement...');
        fetchNonDeletedFocusAreaRecords(packageId, focusAreaName)
            .then(result => {
                hideSpinnerOverlay();
                if (!result || !Array.isArray(result.records)) {
                    throw new Error('Failed to load focus-area records for enhancement.');
                }
                // Store numeric version for display + concurrency checks
                lastFocusAreaVersionNum = result.focusAreaVersionNumber;
                lastFocusAreaVersionId  = result.focusAreaVersionId;
                originalRecords         = result.records;
                // Show the initial overlay
                showEnhanceInputOverlay();
            })
            .catch(err => {
                hideSpinnerOverlay();
                console.error('[EnhanceContent] initEnhancement error:', err);
                alert(`Error loading focus area data: ${err.message}`);
            });
    }

    /**
     * The first overlay that asks the user to "Enter instructions for how these records should be transformed."
     * We now pre-fill the <textarea> with lastUserInstruction if present.
     */
    function showEnhanceInputOverlay() {
        removeOverlayById('enhance-input-overlay');

        const overlay = document.createElement('div');
        overlay.id = 'enhance-input-overlay';
        overlay.className = 'overlay';

        const content = document.createElement('div');
        content.className = 'overlay-content';

        const closeBtn = document.createElement('span');
        closeBtn.className = 'close-overlay';
        closeBtn.innerHTML = '&times;';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.addEventListener('click', () => removeOverlay(overlay));

        const title = document.createElement('h2');
        title.textContent = `Enhance Focus Area: ${lastFocusAreaName}`;

        const desc = document.createElement('p');
        desc.textContent = 'Enter instructions for how these records should be transformed.';

        const textarea = document.createElement('textarea');
        textarea.rows = 5;
        textarea.style.width = '100%';
        // Pre-fill from lastUserInstruction:
        textarea.value = lastUserInstruction || '';
        textarea.placeholder = 'e.g., "Add a Priority field for each record based on these rules..."';

        const enhanceBtn = document.createElement('button');
        enhanceBtn.className = 'overlay-button';
        enhanceBtn.textContent = 'Enhance Content';
        enhanceBtn.addEventListener('click', () => {
            const userInstruction = textarea.value.trim();
            if (!userInstruction) {
                alert('Please provide enhancement instructions.');
                return;
            }
            lastUserInstruction = userInstruction;
            removeOverlay(overlay);
            sendEnhancementRequest(userInstruction);
        });

        content.appendChild(closeBtn);
        content.appendChild(title);
        content.appendChild(desc);
        content.appendChild(textarea);
        content.appendChild(enhanceBtn);

        overlay.appendChild(content);
        document.body.appendChild(overlay);
    }

    /**
     * sendEnhancementRequest => calls the AI with user instructions + existing records
     */
    function sendEnhancementRequest(instruction) {
        showSpinnerOverlay('Sending request to AI...');

        // Build input_records from originalRecords where is_deleted='0'
        const inputRecords = [];
        originalRecords.forEach(rec => {
            const isDel = String(rec.is_deleted || '0');
            if (isDel === '0') {
                inputRecords.push({
                    focusAreaRecordID:      String(rec.id || ''),
                    focusAreaRecordNumber:  String(rec.display_order || '0'),
                    grid_index:             String(rec.grid_index || '0'),
                    is_deleted:             isDel,
                    axia_properties:        { ...(rec.axia_properties || {}) },
                    axia_item_instructions: []
                });
            }
        });

        const payload = {
            template: 'Compile_Mixed_Feedback',
            text: JSON.stringify({
                axia_data: {
                    packageId:        String(lastPackageId),
                    focusAreaVersion: String(lastFocusAreaVersionNum),
                    axia_focus_area:  lastFocusAreaName,
                    axia_input: {
                        input_records:             inputRecords,
                        axia_general_instructions: [ instruction ]
                    }
                }
            }),
            metadata: {
                datetime: new Date().toLocaleString(),
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
            }
        };

        const endpoint = window.AxiaBAConfig.api_base_url + '/aii_helper.php';
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': window.AxiaBAConfig.api_key
            },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            hideSpinnerOverlay();
            if (!data || data.status !== 'success') {
                throw new Error(data ? data.message : 'AI request failed.');
            }
            if (!data.axia_output) {
                throw new Error('AI response missing axia_output field.');
            }
            lastSummaryOfRevisions = data.axia_output.summary_of_revisions || '';
            const revisedRecords   = data.axia_output.output_records || [];
            showRevisedRecordsOverlay(revisedRecords);
        })
        .catch(err => {
            hideSpinnerOverlay();
            console.error('Enhancement request error:', err);
            alert(`Enhancement request failed: ${err.message}`);
            showEnhanceInputOverlay();
        });
    }

    /**
     * The second overlay that shows the revised records => user can commit or try again.
     */
    function showRevisedRecordsOverlay(finalRecords) {
        removeOverlayById('enhance-revised-overlay');

        const overlay = document.createElement('div');
        overlay.id = 'enhance-revised-overlay';
        overlay.className = 'overlay';

        const content = document.createElement('div');
        content.className = 'overlay-content';

        // Close button
        const closeBtn = document.createElement('span');
        closeBtn.className = 'close-overlay';
        closeBtn.innerHTML = '&times;';
        closeBtn.setAttribute('aria-label', 'Close Enhanced Records');
        closeBtn.addEventListener('click', () => {
            removeOverlay(overlay);
            showEnhanceInputOverlay();
        });

        const title = document.createElement('h2');
        title.textContent = 'Review Enhanced Records';

        const subtitle = document.createElement('p');
        subtitle.textContent = `Package: ${lastPackageName} (#${lastPackageId}), Focus Area: ${lastFocusAreaName} (v${lastFocusAreaVersionNum})`;

        // Summaries
        const summaryContainer = document.createElement('div');
        summaryContainer.style.margin       = '10px 0';
        summaryContainer.style.padding      = '10px';
        summaryContainer.style.background   = '#f7f7f7';
        summaryContainer.style.border       = '1px solid #ddd';
        summaryContainer.style.borderRadius = '4px';

        const summaryLabel = document.createElement('h3');
        summaryLabel.textContent = 'Summary of Revisions';
        summaryLabel.style.marginTop = '0';

        const summaryText = document.createElement('p');
        summaryText.textContent = lastSummaryOfRevisions || '(No summary)';

        summaryContainer.appendChild(summaryLabel);
        summaryContainer.appendChild(summaryText);

        // Show finalRecords
        const form = document.createElement('form');
        form.id = 'enhanced-records-form';

        finalRecords.forEach((r, idx) => {
            const fs = document.createElement('fieldset');
            fs.className = 'record-fieldset';

            const recordType = classifyRecord(r);
            fs.classList.add(`${recordType}-record`);

            // Pull the display_order from focusAreaRecordNumber, fallback to (idx+1)
            const recordNum = r.focusAreaRecordNumber && r.focusAreaRecordNumber !== '0'
                ? r.focusAreaRecordNumber
                : (idx + 1);

            const legend = document.createElement('legend');
            legend.textContent = `Record ${recordNum} (${recordType})`;
            fs.appendChild(legend);

            // Hidden fields
            createHidden(fs, `focus_area_records[${idx}][focusAreaRecordID]`,      r.focusAreaRecordID);
            createHidden(fs, `focus_area_records[${idx}][focusAreaRecordNumber]`, r.focusAreaRecordNumber || '');
            createHidden(fs, `focus_area_records[${idx}][grid_index]`,            r.grid_index);
            createHidden(fs, `focus_area_records[${idx}][is_deleted]`,            r.is_deleted || '0');

            // axia_properties => user fields
            if (r.axia_properties && typeof r.axia_properties === 'object') {
                for (const [key, val] of Object.entries(r.axia_properties)) {
                    createTextField(fs, idx, key, val);
                }
            }

            form.appendChild(fs);
        });

        // Buttons
        const btnContainer = document.createElement('div');
        btnContainer.style.marginTop = '20px';
        btnContainer.style.display   = 'flex';
        btnContainer.style.gap       = '10px';

        const commitBtn = document.createElement('button');
        commitBtn.type = 'button';
        commitBtn.className = 'overlay-button commit-btn';
        commitBtn.textContent = 'Commit Enhanced Records';
        commitBtn.addEventListener('click', () => {
            commitEnhancedRecords(form);
        });

        const tryAgainBtn = document.createElement('button');
        tryAgainBtn.type = 'button';
        tryAgainBtn.className = 'overlay-button cancel-btn';
        tryAgainBtn.textContent = 'Try Again';
        tryAgainBtn.addEventListener('click', () => {
            removeOverlay(overlay);
            showEnhanceInputOverlay();
        });

        btnContainer.appendChild(commitBtn);
        btnContainer.appendChild(tryAgainBtn);

        content.appendChild(closeBtn);
        content.appendChild(title);
        content.appendChild(subtitle);
        content.appendChild(summaryContainer);
        content.appendChild(form);
        content.appendChild(btnContainer);

        overlay.appendChild(content);
        document.body.appendChild(overlay);

        addTemporaryStyles();
    }

    function classifyRecord(r) {
        if (String(r.is_deleted) === '1') return 'deleted';
        const faId = (r.focusAreaRecordID || '').toLowerCase();
        const gIdx = (r.grid_index || '').toLowerCase();
        if (faId === 'new' || gIdx === 'new') return 'created';
        return 'updated';
    }

    function createHidden(fieldset, name, value) {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = name;
        inp.value = value || '';
        fieldset.appendChild(inp);
    }

    function createTextField(fieldset, index, propKey, propVal) {
        const container = document.createElement('div');
        container.className = 'field-container';

        const lbl = document.createElement('label');
        lbl.textContent = propKey;
        lbl.htmlFor     = `enh-rec-${index}-${propKey}`;

        const inp = document.createElement('input');
        inp.type  = 'text';
        inp.id    = `enh-rec-${index}-${propKey}`;
        inp.name  = `focus_area_records[${index}][axia_properties][${propKey}]`;
        inp.value = propVal == null ? '' : String(propVal);

        container.appendChild(lbl);
        container.appendChild(inp);
        fieldset.appendChild(container);
    }

    function commitEnhancedRecords(form) {
        showSpinnerOverlay('Saving changes...');

        const fieldsets = form.querySelectorAll('.record-fieldset');
        const recordPayload = [];

        fieldsets.forEach(fs => {
            const recObj = { axia_properties: {} };
            fs.querySelectorAll('input, textarea').forEach(input => {
                const nm = input.name;
                // match focus_area_records[index][someKey] or focus_area_records[index][axia_properties][propName]
                const match = nm.match(/^focus_area_records\[(\d+)\]\[([^\]]+)\](?:\[(.+)\])?$/);
                if (!match) return;

                const subKey   = match[2];
                const subField = match[3] || null;
                if (subKey === 'axia_properties' && subField) {
                    recObj.axia_properties[subField] = input.value;
                } else {
                    recObj[subKey] = input.value;
                }
            });
            recordPayload.push(recObj);
        });

        const payload = {
            package_id:            lastPackageId,
            focus_area_name:       lastFocusAreaName,
            focus_area_version_id: lastFocusAreaVersionId,
            focus_area_records:    recordPayload,
            summary_of_revisions:  lastSummaryOfRevisions
        };

        // We POST to /save_enhanced_records.php (distinct from /save_revised_records.php)
        fetch('/save_enhanced_records.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(resp => resp.json())
        .then(data => {
            hideSpinnerOverlay();
            if (data.status === 'success') {
                alert('Enhancements saved successfully. A new focus-area version has been created.');
                removeOverlayById('enhance-revised-overlay');
                if (typeof reloadRefineTabAndOpenPackage === 'function') {
                    reloadRefineTabAndOpenPackage(lastPackageId, lastFocusAreaName);
                } else {
                    window.location.reload();
                }
            } else {
                throw new Error(data.message || 'Error saving revised records.');
            }
        })
        .catch(err => {
            hideSpinnerOverlay();
            console.error('Error committing enhancements:', err);
            alert(`Failed to save changes: ${err.message}`);
        });
    }

    function fetchNonDeletedFocusAreaRecords(packageId, focusAreaName) {
        const url = `fetch_analysis_package_focus_area_records.php?package_id=${packageId}&show_deleted=0`;
        return fetch(url)
            .then(r => r.ok ? r.json() : Promise.reject('Error loading focus-area records.'))
            .then(data => {
                const faGroups = data.focus_areas || {};
                const faObj    = faGroups[focusAreaName];
                if (!faObj) {
                    throw new Error(`Focus area "${focusAreaName}" not found in package #${packageId}.`);
                }
                // Convert .properties => .axia_properties
                faObj.records.forEach(record => {
                    if (record.properties && typeof record.properties === 'object') {
                        record.axia_properties = record.properties;
                        delete record.properties;
                    }
                });
                return {
                    focusAreaVersionNumber: faObj.version   || 0,
                    focusAreaVersionId:     faObj.versionId || 0,
                    records: faObj.records
                };
            });
    }

    function showSpinnerOverlay(msg) {
        removeOverlayById('enhance-spinner-overlay');
        const ov = document.createElement('div');
        ov.id = 'enhance-spinner-overlay';
        ov.className = 'page-mask';

        const spinner = document.createElement('div');
        spinner.className = 'spinner';

        const text = document.createElement('div');
        text.className = 'spinner-message';
        text.textContent = msg;

        ov.appendChild(spinner);
        ov.appendChild(text);
        document.body.appendChild(ov);
    }

    function hideSpinnerOverlay() {
        removeOverlayById('enhance-spinner-overlay');
    }

    function removeOverlayById(id) {
        const existing = document.getElementById(id);
        if (existing) existing.remove();
    }

    function removeOverlay(el) {
        if (el) el.remove();
    }

    function addTemporaryStyles() {
        if (document.getElementById('enhance-styles')) return;
        const st = document.createElement('style');
        st.id = 'enhance-styles';
        st.innerHTML = `
            fieldset.created-record { background-color: #ccffd8; }
            fieldset.updated-record { background-color: #fff1b8; }
            fieldset.deleted-record { background-color: #ffcfcf; }
            .field-container { margin-bottom: 8px; }
            .field-container label {
                display: inline-block;
                width: 120px;
                font-weight: bold;
            }
            .overlay-button { margin-top: 10px; cursor: pointer; }
        `;
        document.head.appendChild(st);
    }

    // Expose our updated initEnhancement
    return {
        initEnhancement
    };
})();
