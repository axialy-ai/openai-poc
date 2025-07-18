/****************************************************************************
 * /js/refine/new-record-overlay.js
 *
 * Creates an overlay to define a new focus-area record, separating ephemeral
 * fields from user-defined fields, and optionally using AI to enhance
 * the user-defined fields.
 ***************************************************************************/
const NewRecordOverlayModule = (function() {
    let overlayElement = null;
    let newRecord = {
        ephemeral: {},
        axia_properties: {}
    };
    let originalSnapshot = null;

    const skipColumns = [
        'is_deleted', 'focusAreaRecordID', 'grid_index',
        'input_text_summaries_id','input_text_title','input_text_summary','input_text',
        '_dbOriginalProps','_changedKeys','_newlyCreated','_originalIsDeleted',
        'focusAreaName'
    ];

    /**
     * Opens the overlay to create a new record.
     * @param {Function} onCommit - Callback receiving the final record object if user saves.
     * @param {Object} defaultProps - optional initial user-defined fields.
     */
    function openNewOverlay(onCommit, defaultProps = {}) {
        closeNewOverlay();
        newRecord = {
            ephemeral: { focusAreaRecordID: 'new', is_deleted: 0 },
            axia_properties: {}
        };
        for (const [k, v] of Object.entries(defaultProps)) {
            if (!skipColumns.includes(k)) {
                newRecord.axia_properties[k] = v;
            }
        }
        originalSnapshot = JSON.parse(JSON.stringify(newRecord));

        // Build overlay
        overlayElement = document.createElement('div');
        overlayElement.className = 'overlay';
        overlayElement.innerHTML = `
            <div class="overlay-content">
                <span class="close-overlay">&times;</span>
                <div class="record-overlay-header">
                    <h2>New Record Details</h2>
                    <p class="overlay-subtitle">
                        Enter property values for the new focus-area record.
                        Optionally apply AI enhancements or reset fields.
                    </p>
                </div>
                <div class="record-overlay-body">
                    <div id="new-record-properties-container" class="record-properties-container"></div>
                    <hr class="section-divider" />
                    <div class="overlay-form-group ai-enhance-section">
                        <label for="new-record-ai-instructions">AI Enhancement Instructions (optional):</label>
                        <textarea 
                          id="new-record-ai-instructions" 
                          placeholder="Enter instructions for AI to enhance the data"
                        ></textarea>
                        <button 
                          id="new-record-ai-btn" 
                          class="ai-enhance-btn"
                        >Enhance Data (AI)</button>
                    </div>
                </div>
                <div class="record-overlay-actions">
                    <div class="action-left-group">
                        <button id="new-record-delete-restore-btn" class="delete-record-btn">Delete Record</button>
                        <button id="new-record-reset-btn" class="reset-record-btn">Reset</button>
                    </div>
                    <div class="action-right-group">
                        <button id="new-record-save-btn" class="save-record-btn">Save New Record</button>
                        <button id="new-record-cancel-btn" class="cancel-record-btn">Cancel</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(overlayElement);

        populatePropertiesUI(
            document.getElementById('new-record-properties-container'),
            newRecord.axia_properties
        );

        const delRestoreBtn = document.getElementById('new-record-delete-restore-btn');
        updateDeleteRestoreButtonText(delRestoreBtn, newRecord.ephemeral.is_deleted);

        overlayElement.querySelector('.close-overlay').onclick = () => closeNewOverlay();
        document.getElementById('new-record-cancel-btn').onclick = () => closeNewOverlay();

        document.getElementById('new-record-reset-btn').onclick = () => {
            newRecord = JSON.parse(JSON.stringify(originalSnapshot));
            const container = document.getElementById('new-record-properties-container');
            if (container) {
                container.innerHTML = '';
                populatePropertiesUI(container, newRecord.axia_properties);
            }
            updateDeleteRestoreButtonText(delRestoreBtn, newRecord.ephemeral.is_deleted);
        };

        document.getElementById('new-record-delete-restore-btn').onclick = () => {
            toggleRecordDeletion(delRestoreBtn);
        };

        document.getElementById('new-record-ai-btn').onclick = () => handleEnhanceDataAi();

        document.getElementById('new-record-save-btn').onclick = () => {
            collectPropertiesFromUI(newRecord.axia_properties, 'new-record-properties-container');
            if (!Array.isArray(newRecord._changedKeys)) {
                newRecord._changedKeys = [];
            }
            if (typeof onCommit === 'function') {
                onCommit(newRecord);
            }
            closeNewOverlay();
        };
    }

    function closeNewOverlay() {
        if (overlayElement) {
            overlayElement.remove();
            overlayElement = null;
        }
    }

    function updateDeleteRestoreButtonText(btn, isDeleted) {
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

    function toggleRecordDeletion(delRestoreBtn) {
        newRecord.ephemeral.is_deleted = newRecord.ephemeral.is_deleted === 1 ? 0 : 1;
        updateDeleteRestoreButtonText(delRestoreBtn, newRecord.ephemeral.is_deleted);
    }

    function populatePropertiesUI(container, propsObj) {
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
            const k = input.dataset.propertyKey;
            if (k) {
                propsObj[k] = input.value;
            }
        });
    }

    function handleEnhanceDataAi() {
        showOverlaySpinner('Enhancing data with AI...');
        collectPropertiesFromUI(newRecord.axia_properties, 'new-record-properties-container');
        const userInstructions = (document.getElementById('new-record-ai-instructions')?.value || '').trim();

        const postData = {
            template: 'Compile_Mixed_Feedback',
            text: JSON.stringify({
                axia_data: {
                    packageId: "0",
                    focusAreaVersion: "0",
                    axia_input: {
                        input_records: [
                            {
                                focusAreaRecordID: "new",
                                grid_index: "new",
                                is_deleted: "0",
                                axia_properties: newRecord.axia_properties,
                                axia_item_instructions: userInstructions ? [ userInstructions ] : []
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
                throw new Error(data.message || 'AI Enhancement failed for new record.');
            }
            if (!data.axia_output || !Array.isArray(data.axia_output.output_records)) {
                throw new Error('No output_records found in AI response.');
            }
            const outRecs = data.axia_output.output_records;
            if (outRecs.length === 0) {
                alert('AI returned no revised properties for the new record.');
                return;
            }
            const revisedProps = filterOutEphemeral(outRecs[0].axia_properties || {});
            newRecord.axia_properties = revisedProps;

            const container = document.getElementById('new-record-properties-container');
            if (container) {
                container.innerHTML = '';
                populatePropertiesUI(container, newRecord.axia_properties);
            }
            alert('AI Enhancement completed. The new record fields have been updated.');
        })
        .catch(err => {
            hideOverlaySpinner();
            console.error('[NewRecordOverlay] AI error:', err);
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

    function showOverlaySpinner(message) {
        removeSpinnerOverlay();
        const ov = document.createElement('div');
        ov.id = 'new-record-ai-spinner';
        ov.className = 'page-mask';

        const spin = document.createElement('div');
        spin.className = 'spinner';

        const msg = document.createElement('div');
        msg.className = 'spinner-message';
        msg.textContent = message;

        ov.appendChild(spin);
        ov.appendChild(msg);
        document.body.appendChild(ov);
    }

    function hideOverlaySpinner() {
        removeSpinnerOverlay();
    }

    function removeSpinnerOverlay() {
        const existing = document.getElementById('new-record-ai-spinner');
        if (existing) existing.remove();
    }

    return {
        openNewOverlay,
        closeNewOverlay
    };
})();
window.NewRecordOverlayModule = NewRecordOverlayModule;
