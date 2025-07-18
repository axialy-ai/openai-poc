/*
 * /js/refine/augment-focus-area.js
 *
 */

window.FocusAreaAugmentationModule = (function() {
    let focusAreaNameGlobal   = '';
    let packageNameGlobal     = '';
    let packageIdGlobal       = 0;
    let focusAreaVersionNum   = 0;
    let augmentedRecords      = [];

    /**
     * Begins the “Augment Focus Area” flow by showing an overlay for AI instructions.
     */
    function init(focusAreaName, packageName, packageId, focusAreaVersionNumber) {
        focusAreaNameGlobal  = focusAreaName;
        packageNameGlobal    = packageName;
        packageIdGlobal      = packageId;
        focusAreaVersionNum  = focusAreaVersionNumber;
        showAugmentationOverlay();
    }

    /**
     * Overlay #1: user types instructions for AI augmentation.
     */
    function showAugmentationOverlay() {
        removeIfExists('augment-content-overlay');

        const overlay = document.createElement('div');
        overlay.id = 'augment-content-overlay';
        overlay.className = 'overlay';

        const content = document.createElement('div');
        content.className = 'overlay-content';

        const closeBtn = document.createElement('span');
        closeBtn.className = 'close-overlay';
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', () => overlay.remove());

        const title = document.createElement('h2');
        title.textContent = 'Augment Focus Area';

        const instructions = document.createElement('p');
        instructions.textContent = `Provide instructions for AI to create new records in the “${focusAreaNameGlobal}” focus area of package #${packageIdGlobal}.`;

        const textArea = document.createElement('textarea');
        textArea.rows = 6;
        textArea.style.width = '100%';

        const sendBtn = document.createElement('button');
        sendBtn.textContent = 'Send to AI';
        sendBtn.addEventListener('click', () => {
            const userInput = textArea.value.trim();
            if (!userInput) {
                alert('Please provide some input text for augmentation.');
                return;
            }
            overlay.remove();
            callAiForAugmentation(userInput);
        });

        content.appendChild(closeBtn);
        content.appendChild(title);
        content.appendChild(instructions);
        content.appendChild(textArea);
        content.appendChild(sendBtn);

        overlay.appendChild(content);
        document.body.appendChild(overlay);
    }

    /**
     * Gathers existing records + user instructions => sends to AI => gets new ones back.
     */
    function callAiForAugmentation(userText) {
        RefineUtilsModule.showPageMaskSpinner('Sending augmentation request to AI...');

        fetchExistingRecords()
            .then(existing => {
                const payload = {
                    existingRecords: existing,
                    userText,
                    packageId: packageIdGlobal,
                    focusAreaVersionNumber: focusAreaVersionNum
                };
                const endpoint = window.AxiaBAConfig.api_base_url + '/ai_helper.php';
                return fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': window.AxiaBAConfig.api_key
                    },
                    body: JSON.stringify({
                        template: 'Focus_Area_Augmentation',
                        text: JSON.stringify(payload)
                    })
                })
                .then(resp => resp.json());
            })
            .then(data => {
                RefineUtilsModule.hidePageMaskSpinner();
                if (data.status !== 'success') {
                    throw new Error(data.message || 'Failed to augment focus area content.');
                }
                const newItems = data.data && data.data['Focus_Area_Augmentation'];
                if (!Array.isArray(newItems)) {
                    throw new Error('AI did not return any new records.');
                }
                augmentedRecords = newItems.map(splitEphemeralAndUserFields);
                showAugmentedOverlay();
            })
            .catch(err => {
                RefineUtilsModule.hidePageMaskSpinner();
                console.error('callAiForAugmentation error:', err);
                alert(`Error: ${err.message}`);
            });
    }

    /**
     * Loads existing records from fetch_analysis_package_focus_area_records.php (non-deleted).
     */
    function fetchExistingRecords() {
        const url = `fetch_analysis_package_focus_area_records.php?package_id=${encodeURIComponent(packageIdGlobal)}&show_deleted=0`;
        return fetch(url)
            .then(resp => resp.ok ? resp.json() : Promise.reject('Error fetching existing focus-area records.'))
            .then(json => {
                const focusAreas = json.focusAreas || {};
                if (focusAreas[focusAreaNameGlobal]) {
                    return focusAreas[focusAreaNameGlobal];
                }
                return [];
            });
    }

    /**
     * Splits ephemeral fields (like id, grid_index, is_deleted) from user fields (properties).
     */
    function splitEphemeralAndUserFields(record) {
        const ephemeralKeys = new Set([
            'id','grid_index','focus_area_name','is_deleted',
            'input_text_summaries_id','input_text_title','input_text_summary','input_text'
        ]);
        const ephemeral = {};
        const userFields = {};

        if (record.properties && typeof record.properties === 'object') {
            Object.assign(userFields, record.properties);
        }

        for (const [k,v] of Object.entries(record)) {
            if (k === 'properties') continue;
            if (ephemeralKeys.has(k)) {
                ephemeral[k] = v;
            } else {
                userFields[k] = v;
            }
        }
        return { ephemeral, userFields };
    }

    /**
     * Overlay #2: shows the newly generated records => user can edit => commit.
     */
    function showAugmentedOverlay() {
        removeIfExists('augmented-content-overlay');

        const overlay = document.createElement('div');
        overlay.id = 'augmented-content-overlay';
        overlay.className = 'overlay';

        const content = document.createElement('div');
        content.className = 'overlay-content';

        const closeBtn = document.createElement('span');
        closeBtn.className = 'close-overlay';
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', () => overlay.remove());

        const title = document.createElement('h2');
        title.textContent = 'Augmented Records';

        const intro = document.createElement('p');
        intro.textContent = 'Review these new records from AI, adjust them if needed, then commit.';

        const container = document.createElement('div');
        container.className = 'revisions-list';

        augmentedRecords.forEach((rec, idx) => {
            const recDiv = document.createElement('div');
            recDiv.className = 'revision-record';

            const heading = document.createElement('strong');
            heading.textContent = `NEW RECORD #${idx + 1}`;
            recDiv.appendChild(heading);

            for (const [k, v] of Object.entries(rec.userFields)) {
                const row = document.createElement('div');
                row.style.marginBottom = '6px';

                const label = document.createElement('label');
                label.style.fontWeight = 'bold';
                label.textContent = `${k}: `;

                const input = document.createElement('input');
                input.type = 'text';
                input.value = v || '';
                input.dataset.idx   = idx;
                input.dataset.field = k;
                input.addEventListener('input', e => {
                    const i = parseInt(e.target.dataset.idx, 10);
                    const f = e.target.dataset.field;
                    augmentedRecords[i].userFields[f] = e.target.value;
                });

                row.appendChild(label);
                row.appendChild(input);
                recDiv.appendChild(row);
            }

            container.appendChild(recDiv);
        });

        const commitBtn = document.createElement('button');
        commitBtn.textContent = 'Commit New Records';
        commitBtn.style.marginTop = '15px';
        commitBtn.addEventListener('click', () => {
            overlay.remove();
            commitAugmentedRecords();
        });

        content.appendChild(closeBtn);
        content.appendChild(title);
        content.appendChild(intro);
        content.appendChild(container);
        content.appendChild(commitBtn);

        overlay.appendChild(content);
        document.body.appendChild(overlay);
    }

    /**
     * Commits the newly augmented records to /save_revised_records.php along with existing ones.
     */
    function commitAugmentedRecords() {
        RefineUtilsModule.showPageMaskSpinner('Saving augmented records...');

        fetchExistingRecords()
            .then(existingRaw => {
                const existingMapped = existingRaw.map(splitEphemeralAndUserFields);

                // gather known IDs
                const knownIds = new Set(
                    existingMapped.map(r => parseInt(r.ephemeral.id, 10)).filter(x => !isNaN(x))
                );

                const merged = [...existingMapped, ...augmentedRecords];
                merged.forEach(obj => {
                    const epId = parseInt(obj.ephemeral.id, 10);
                    if (isNaN(epId) || !knownIds.has(epId)) {
                        delete obj.ephemeral.id;
                    }
                });

                const finalRecords = merged.map(obj => {
                    const rec = {
                        grid_index:      obj.ephemeral.grid_index || '',
                        focus_area_name: obj.ephemeral.focus_area_name || focusAreaNameGlobal,
                        is_deleted:      obj.ephemeral.is_deleted || '0',
                        properties:      { ...obj.userFields }
                    };
                    if (obj.ephemeral.id) {
                        rec.id = obj.ephemeral.id;
                    }
                    if (obj.ephemeral.input_text_summaries_id) {
                        rec.input_text_summaries_id = obj.ephemeral.input_text_summaries_id;
                    }
                    if (obj.ephemeral.input_text_title) {
                        rec.input_text_title = obj.ephemeral.input_text_title;
                    }
                    if (obj.ephemeral.input_text_summary) {
                        rec.input_text_summary = obj.ephemeral.input_text_summary;
                    }
                    if (obj.ephemeral.input_text) {
                        rec.input_text = obj.ephemeral.input_text;
                    }
                    return rec;
                });

                const payload = {
                    package_id:              packageIdGlobal,
                    package_name:            packageNameGlobal,
                    focus_area_name:         focusAreaNameGlobal,
                    focus_area_version_number: focusAreaVersionNum,
                    records:                 finalRecords
                };

                return fetch('save_revised_records.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(r => r.json());
            })
            .then(resp => {
                RefineUtilsModule.hidePageMaskSpinner();
                if (resp.status === 'success') {
                    alert('Augmented records saved successfully. A new version may have been created.');
                    if (typeof reloadRefineTabAndOpenPackage === 'function') {
                        reloadRefineTabAndOpenPackage(packageIdGlobal, focusAreaNameGlobal);
                    } else {
                        window.location.reload();
                    }
                } else {
                    throw new Error(resp.message || 'Error saving augmented records');
                }
            })
            .catch(err => {
                RefineUtilsModule.hidePageMaskSpinner();
                console.error('commitAugmentedRecords error:', err);
                alert(`Failed to save augmented records: ${err.message}`);
            });
    }

    function removeIfExists(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    return { init };
})();
