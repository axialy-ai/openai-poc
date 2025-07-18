/****************************************************************************
 * /js/refine/edit-record-overlay.js
 *
 * Provides an overlay to edit an existing focus-area record by separating
 * ephemeral fields (like is_deleted, focusAreaRecordID, grid_index, etc.)
 * from user-defined fields (.axia_properties).
 ***************************************************************************/
const EditRecordOverlayModule = (function() {
    let overlayElement = null;
    let ephemeralRecord = {
        ephemeral: {},
        axia_properties: {}
    };
    let preexistingChangedKeys = [];

    const skipColumns = [
        'is_deleted', 'focusAreaRecordID', 'grid_index',
        'input_text_summaries_id', 'input_text_title', 'input_text_summary', 'input_text',
        '_dbOriginalProps', '_changedKeys', '_newlyCreated', '_originalIsDeleted',
        'focusAreaName'
    ];

    function splitEphemeralFromUserFields(recordObj) {
        const ephemeral = { ...recordObj };
        delete ephemeral.axia_properties;

        let userProps = {};
        if (recordObj.axia_properties && typeof recordObj.axia_properties === 'object') {
            userProps = { ...recordObj.axia_properties };
        }
        return { ephemeral, axia_properties: userProps };
    }

    function mergeEphemeralAndUserFields(ephemeralRec) {
        const finalObj = { ...ephemeralRec.ephemeral };
        finalObj.axia_properties = { ...ephemeralRec.axia_properties };

        if (ephemeralRec._dbOriginalProps) {
            finalObj._dbOriginalProps = ephemeralRec._dbOriginalProps;
        }
        if (ephemeralRec._changedKeys) {
            finalObj._changedKeys = ephemeralRec._changedKeys;
        }
        return finalObj;
    }

    function openEditOverlay(record, onCommit) {
        closeEditOverlay();
        const splitted = splitEphemeralFromUserFields(record);
        ephemeralRecord = {
            ephemeral: splitted.ephemeral,
            axia_properties: splitted.axia_properties,
            _changedKeys: record._changedKeys || [],
            _dbOriginalProps: record._dbOriginalProps
                ? { ...record._dbOriginalProps }
                : { ...splitted.axia_properties }
        };
        ephemeralRecord._originalIsDeleted = ephemeralRecord.ephemeral.is_deleted || 0;
        preexistingChangedKeys = [...ephemeralRecord._changedKeys];

        overlayElement = document.createElement('div');
        overlayElement.className = 'overlay';
        overlayElement.innerHTML = `
            <div class="overlay-content">
                <span class="close-overlay">&times;</span>
                <div class="record-overlay-header">
                    <h2>Edit Record Details</h2>
                    <p class="overlay-subtitle">
                        Update any property fields. You can also apply AI enhancements.
                        Press "Reset" to revert changes from the DB, or "Save" to finalize.
                    </p>
                </div>
                <div class="record-overlay-body">
                    <div id="edit-record-properties-container" class="record-properties-container"></div>
                    <hr class="section-divider" />
                    <div class="overlay-form-group ai-enhance-section">
                        <label for="edit-record-ai-instructions">AI Enhancement Instructions (optional):</label>
                        <textarea
                            id="edit-record-ai-instructions"
                            placeholder="Enter instructions for AI to enhance the data"
                        ></textarea>
                        <button
                            id="edit-record-ai-btn"
                            class="ai-enhance-btn"
                        >Enhance Data (AI)</button>
                    </div>
                </div>
                <div class="record-overlay-actions">
                    <div class="action-left-group">
                        <button id="edit-record-delete-restore-btn" class="delete-record-btn">Delete Record</button>
                        <button id="edit-record-reset-btn" class="reset-record-btn">Reset</button>
                    </div>
                    <div class="action-right-group">
                        <button id="edit-record-save-btn" class="save-record-btn">Save Changes</button>
                        <button id="edit-record-cancel-btn" class="cancel-record-btn">Cancel</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(overlayElement);

        populatePropertiesUI(
            document.getElementById('edit-record-properties-container'),
            ephemeralRecord.axia_properties
        );

        const delRestoreBtn = document.getElementById('edit-record-delete-restore-btn');
        updateDeleteRestoreButton(delRestoreBtn, ephemeralRecord.ephemeral.is_deleted);

        overlayElement.querySelector('.close-overlay').onclick = () => closeEditOverlay();
        document.getElementById('edit-record-cancel-btn').onclick = () => closeEditOverlay();

        document.getElementById('edit-record-reset-btn').onclick = () => {
            resetPropertiesToDB();
        };

        document.getElementById('edit-record-delete-restore-btn').onclick = () => {
            toggleRecordDeletion(delRestoreBtn);
        };

        document.getElementById('edit-record-ai-btn').onclick = () => {
            handleEnhanceDataAi();
        };

        document.getElementById('edit-record-save-btn').onclick = () => {
            collectPropertiesFromUI(
                ephemeralRecord.axia_properties,
                'edit-record-properties-container'
            );
            recalcChangedKeys();
            if (typeof onCommit === 'function') {
                onCommit(mergeEphemeralAndUserFields(ephemeralRecord));
            }
            closeEditOverlay();
        };
    }

    function closeEditOverlay() {
        if (overlayElement) {
            overlayElement.remove();
            overlayElement = null;
        }
    }

    function updateDeleteRestoreButton(btn, isDeleted) {
        if (isDeleted === 1) {
            btn.textContent = 'Restore Record';
            btn.classList.remove('delete-record-btn');
            btn.classList.add('restore-record-btn');
        } else {
            btn.textContent = 'Delete Record';
            btn.classList.remove('restore-record-btn');
            btn.classList.add('delete-record-btn');
        }
    }

    function toggleRecordDeletion(btnElem) {
        const oldVal = ephemeralRecord.ephemeral.is_deleted;
        ephemeralRecord.ephemeral.is_deleted = (oldVal === 1 ? 0 : 1);
        updateDeleteRestoreButton(btnElem, ephemeralRecord.ephemeral.is_deleted);

        if (ephemeralRecord.ephemeral.is_deleted !== ephemeralRecord._originalIsDeleted) {
            if (!ephemeralRecord._changedKeys.includes('is_deleted')) {
                ephemeralRecord._changedKeys.push('is_deleted');
            }
        } else {
            ephemeralRecord._changedKeys = ephemeralRecord._changedKeys.filter(k => k !== 'is_deleted');
        }
    }

    function resetPropertiesToDB() {
        const dbOrig = ephemeralRecord._dbOriginalProps || {};
        ephemeralRecord.axia_properties = JSON.parse(JSON.stringify(dbOrig));
        ephemeralRecord._changedKeys = [];

        const container = document.getElementById('edit-record-properties-container');
        if (container) {
            container.innerHTML = '';
            populatePropertiesUI(container, ephemeralRecord.axia_properties);
        }
    }

    function populatePropertiesUI(container, propsObj) {
        if (!container) return;
        container.innerHTML = '';
        for (const [key, val] of Object.entries(propsObj)) {
            const multiline = String(val).includes('\n');
            const fg = document.createElement('div');
            fg.className = 'overlay-form-group';

            const label = document.createElement('label');
            label.textContent = key;

            let inputElem;
            if (multiline) {
                inputElem = document.createElement('textarea');
                inputElem.value = val;
            } else {
                inputElem = document.createElement('input');
                inputElem.type = 'text';
                inputElem.value = val;
            }
            inputElem.dataset.propertyKey = key;
            fg.appendChild(label);
            fg.appendChild(inputElem);
            container.appendChild(fg);
        }
    }

    function collectPropertiesFromUI(propsObj, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const inputs = container.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            const propKey = input.dataset.propertyKey;
            if (propKey) {
                propsObj[propKey] = input.value;
            }
        });
    }

    function recalcChangedKeys() {
        const dbOrig = ephemeralRecord._dbOriginalProps || {};
        const newVals = ephemeralRecord.axia_properties;
        const changed = new Set();

        for (const [k, v] of Object.entries(newVals)) {
            const oldVal = dbOrig[k];
            if (v !== oldVal) {
                changed.add(k);
            }
        }
        for (const k of Object.keys(dbOrig)) {
            if (!(k in newVals)) {
                changed.add(k);
            }
        }
        const ephemeralChanged = ephemeralRecord._changedKeys.includes('is_deleted');
        ephemeralRecord._changedKeys = Array.from(changed);
        if (ephemeralChanged && !ephemeralRecord._changedKeys.includes('is_deleted')) {
            ephemeralRecord._changedKeys.push('is_deleted');
        }
    }

    function handleEnhanceDataAi() {
        showOverlaySpinner('Enhancing data with AI...');
        collectPropertiesFromUI(
            ephemeralRecord.axia_properties,
            'edit-record-properties-container'
        );
        const userInstructions = (document.getElementById('edit-record-ai-instructions')?.value || '').trim();
        const postData = {
            template: 'Compile_Mixed_Feedback',
            text: JSON.stringify({
                axia_data: {
                    packageId: String(ephemeralRecord.ephemeral.packageId || 0),
                    focusAreaVersion: String(ephemeralRecord.ephemeral.focusAreaVersion || 0),
                    axia_input: {
                        input_records: [
                            {
                                focusAreaRecordID: 'temp',
                                grid_index: 'temp',
                                is_deleted: '0',
                                axia_properties: ephemeralRecord.axia_properties,
                                axia_item_instructions: userInstructions ? [userInstructions] : []
                            }
                        ],
                        axia_general_instructions: []
                    }
                }
            })
        };
        const endpoint = window.AxiaBAConfig.api_base_url + '/aii_helper.php';
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': window.AxiaBAConfig.api_key
            },
            body: JSON.stringify(postData)
        })
        .then(r => r.json())
        .then(data => {
            hideOverlaySpinner();
            if (data.status !== 'success') {
                throw new Error(data.message || 'AI Enhancement failed.');
            }
            if (!data.axia_output || !Array.isArray(data.axia_output.output_records)) {
                throw new Error('No output_records in AI response.');
            }
            const outRecs = data.axia_output.output_records;
            if (outRecs.length === 0) {
                alert('AI returned no revised properties.');
                return;
            }
            const newProps = filterOutEphemeral(outRecs[0].axia_properties || {});
            ephemeralRecord.axia_properties = newProps;

            const container = document.getElementById('edit-record-properties-container');
            if (container) {
                container.innerHTML = '';
                populatePropertiesUI(container, ephemeralRecord.axia_properties);
            }
            alert('AI Enhancement completed. Fields updated in this overlay.');
        })
        .catch(err => {
            hideOverlaySpinner();
            console.error('[EditRecordOverlay] AI error:', err);
            alert(`Enhance Data (AI) error: ${err.message}`);
        });
    }

    function filterOutEphemeral(propsObj) {
        const ret = {};
        for (const [k, v] of Object.entries(propsObj)) {
            if (!skipColumns.includes(k)) {
                ret[k] = v;
            }
        }
        return ret;
    }

    function showOverlaySpinner(msg) {
        removeSpinnerOverlay();
        const ov = document.createElement('div');
        ov.id = 'edit-record-ai-spinner';
        ov.className = 'page-mask';
        const spin = document.createElement('div');
        spin.classList.add('spinner');
        const m = document.createElement('div');
        m.className = 'spinner-message';
        m.textContent = msg;
        ov.appendChild(spin);
        ov.appendChild(m);
        document.body.appendChild(ov);
    }

    function hideOverlaySpinner() {
        removeSpinnerOverlay();
    }

    function removeSpinnerOverlay() {
        const ex = document.getElementById('edit-record-ai-spinner');
        if (ex) ex.remove();
    }

    return {
        openEditOverlay,
        closeEditOverlay
    };
})();
window.EditRecordOverlayModule = EditRecordOverlayModule;
