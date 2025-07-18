/****************************************************************************
 * /js/new-focus-area-overlay.js
 *
 * Displays an overlay to define a new focus area within a package:
 *  1) User enters the focus area name and optionally a list of property names.
 *  2) If the user chooses zero properties and confirms "Yes", we immediately
 *     create the new focus area with one blank record (no second overlay).
 *  3) Otherwise, user enters multiple records, filling those properties in
 *     the "New Focus Area Records" overlay, then commits.
 *  4) On commit, we call "process_new_focus_area.php" to create:
 *     - A new row in analysis_package_focus_areas
 *     - An initial row in analysis_package_focus_area_versions (version #0)
 *     - Associated rows in analysis_package_focus_area_records
 ****************************************************************************/
(function () {
    let packageId   = 0;
    let packageName = '';
    // We'll collect user-defined property names in an array => "focusAreaProperties"
    let focusAreaProperties = [];
    // We'll collect user-defined record data => "focusAreaRecords"
    let focusAreaRecords = [];

    /**
     * Initializes the New Focus Area Overlay module.
     */
    function init() {
        // If there’s any needed initialization, do it here.
    }

    /**
     * Opens the overlay for creating a new focus area within the specified package.
     * @param {number} selectedPackageId - The ID of the selected package.
     * @param {string} selectedPackageName - The name of the selected package.
     */
    function open(selectedPackageId, selectedPackageName) {
        packageId   = selectedPackageId;
        packageName = selectedPackageName;
        showFocusAreaDetailsOverlay();
    }

    /**
     * Renders the overlay that collects the new focus area name and property list.
     */
    function showFocusAreaDetailsOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'overlay';
        overlay.innerHTML = `
            <div class="overlay-content">
                <span class="close-overlay" tabindex="0" role="button" aria-label="Close">&times;</span>
                <h2>New Focus Area Details</h2>
                <div class="field-container">
                    <label for="focus-area-name">Focus Area Name:</label>
                    <input type="text" id="focus-area-name" placeholder="Enter Focus Area Name" />
                </div>
                <div id="properties-container">
                    <h3>Properties</h3>
                    <!-- property inputs will be added here -->
                </div>
                <button id="add-property-btn" class="add-property-btn">Add Property</button>
                <div class="button-container">
                    <button class="overlay-button cancel-btn">Cancel</button>
                    <button class="overlay-button continue-btn">Continue</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        const closeButton = overlay.querySelector('.close-overlay');
        closeButton.addEventListener('click', () => overlay.remove());
        closeButton.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') overlay.remove();
        });

        // Cancel => remove overlay
        overlay.querySelector('.cancel-btn').addEventListener('click', () => {
            overlay.remove();
        });

        // Add new property input
        overlay.querySelector('#add-property-btn').addEventListener('click', addPropertyField);

        // Continue => either show the second overlay or create 1 blank record
        overlay.querySelector('.continue-btn').addEventListener('click', () => {
            const focusAreaNameVal = document.getElementById('focus-area-name').value.trim();
            if (!focusAreaNameVal) {
                alert('Please enter a Focus Area Name.');
                return;
            }
            // Gather property names
            focusAreaProperties = Array.from(document.querySelectorAll('.property-name'))
                .map(input => input.value.trim())
                .filter(name => name);

            if (focusAreaProperties.length === 0) {
                // Show a confirm alert
                const userConfirmed = window.confirm(
                    'Do you want to create this new Focus Area with no initial properties?'
                );
                if (!userConfirmed) {
                    // "No" => stay on this overlay
                    return;
                }
                // "Yes" => immediately save new focus area with 1 blank record
                overlay.remove();
                createNewFocusAreaWithOneBlankRecord(focusAreaNameVal);
                return;
            }
            // If we get here, user has at least 1 property => proceed
            overlay.remove();
            showFocusAreaRecordsOverlay(focusAreaNameVal);
        });
    }

    /**
     * Adds a new property input field for the user to define a property name.
     */
    function addPropertyField() {
        const container = document.getElementById('properties-container');
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'property-name';
        input.placeholder = 'Enter Property Name';
        container.appendChild(input);
    }

    /**
     * If user chooses to have no properties, we skip the second overlay
     * and just create one blank record (empty object), then save.
     */
    function createNewFocusAreaWithOneBlankRecord(focusAreaName) {
        // We'll have 0 properties, so 1 empty record means => {}
        focusAreaProperties = [];
        focusAreaRecords = [ {} ];  // a single blank record

        saveNewFocusAreaData(focusAreaName);
    }

    /**
     * Renders the overlay that collects multiple records for the new focus area.
     * @param {string} focusAreaName - The name of the focus area being created.
     */
    function showFocusAreaRecordsOverlay(focusAreaName) {
        const overlay = document.createElement('div');
        overlay.className = 'overlay';

        overlay.innerHTML = `
            <div class="overlay-content">
                <span class="close-overlay" tabindex="0" role="button" aria-label="Close">&times;</span>
                <h2>New Focus Area Records</h2>
                <div class="records-container"></div>
                <button id="add-record-btn" class="add-record-btn">Add Record</button>
                <div class="button-container">
                    <button class="overlay-button cancel-btn">Cancel</button>
                    <button class="overlay-button commit-btn">Commit</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        // Close / Cancel
        const closeButton = overlay.querySelector('.close-overlay');
        closeButton.addEventListener('click', () => overlay.remove());
        closeButton.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') overlay.remove();
        });
        overlay.querySelector('.cancel-btn').addEventListener('click', () => {
            overlay.remove();
        });

        // Add record
        const addRecordBtn = overlay.querySelector('#add-record-btn');
        addRecordBtn.addEventListener('click', addRecordField);

        // Commit => collect data & save
        const commitBtn = overlay.querySelector('.commit-btn');
        commitBtn.addEventListener('click', () => {
            collectRecordsData();
            overlay.remove();
            saveNewFocusAreaData(focusAreaName);
        });

        // Start with one empty record row
        addRecordField();
    }

    /**
     * Dynamically adds a record input section based on the previously defined property names.
     */
    function addRecordField() {
        const container = document.querySelector('.records-container');
        const recordDiv = document.createElement('div');
        recordDiv.className = 'record-fieldset';

        focusAreaProperties.forEach(prop => {
            const fieldContainer = document.createElement('div');
            fieldContainer.className = 'field-container';

            const label = document.createElement('label');
            label.textContent = prop;

            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'record-property';
            input.dataset.propertyName = prop;

            fieldContainer.appendChild(label);
            fieldContainer.appendChild(input);
            recordDiv.appendChild(fieldContainer);
        });

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = 'Remove Record';
        removeBtn.classList.add('remove-record-btn');
        removeBtn.addEventListener('click', () => {
            container.removeChild(recordDiv);
        });

        recordDiv.appendChild(removeBtn);
        container.appendChild(recordDiv);
    }

    /**
     * Gathers the user-entered record data from the .record-fieldset elements
     * and populates focusAreaRecords array as a list of { <propertyName>: <value>, ... }.
     */
    function collectRecordsData() {
        focusAreaRecords = [];
        const recordDivs = document.querySelectorAll('.record-fieldset');
        recordDivs.forEach(div => {
            const singleRec = {};
            const inputs = div.querySelectorAll('.record-property');
            inputs.forEach(inp => {
                singleRec[inp.dataset.propertyName] = inp.value.trim();
            });
            focusAreaRecords.push(singleRec);
        });
    }

    /**
     * Sends the new focus area data to the server.
     * @param {string} focusAreaName - The new focus area name
     */
    function saveNewFocusAreaData(focusAreaName) {
        // We pass them as "focus_area_properties" and "focus_area_records"
        const payload = {
            package_id:            packageId,
            package_name:          packageName, // optional
            focus_area_name:       focusAreaName,
            focus_area_properties: focusAreaProperties,
            focus_area_records:    focusAreaRecords
        };

        console.log('[new-focus-area-overlay] Sending payload:', payload);
        showPageMaskSpinner('Saving new focus area...');

        fetch('process_new_focus_area.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(resp => resp.json())
        .then(data => {
            hidePageMaskSpinner();
            if (data.status === 'success') {
                alert('New focus area created successfully.');
                // Refresh or reload the UI to show the new focus area
                if (typeof reloadRefineTabAndOpenPackage === 'function') {
                    reloadRefineTabAndOpenPackage(packageId, focusAreaName);
                } else if (typeof openPackage === 'function') {
                    openPackage(packageId);
                } else if (window.loadRefineTab) {
                    window.loadRefineTab();
                }
            } else {
                alert(data.message || 'Failed to save the new focus area.');
            }
        })
        .catch(err => {
            hidePageMaskSpinner();
            console.error('[new-focus-area-overlay] Error:', err);
            alert('An error occurred while saving the new focus area.');
        });
    }

    /**
     * Shows a page-mask spinner with a given message.
     */
    function showPageMaskSpinner(msg) {
        const existingMask = document.getElementById('page-mask');
        if (existingMask) return;

        const pageMask = document.createElement('div');
        pageMask.id = 'page-mask';
        pageMask.classList.add('page-mask');

        const spinner = document.createElement('div');
        spinner.classList.add('spinner');

        const text = document.createElement('div');
        text.classList.add('spinner-message');
        text.textContent = msg;

        pageMask.appendChild(spinner);
        pageMask.appendChild(text);
        document.body.appendChild(pageMask);
    }

    /**
     * Hides the page-mask spinner, if present.
     */
    function hidePageMaskSpinner() {
        const pm = document.getElementById('page-mask');
        if (pm) pm.remove();
    }

    // Expose as a global for usage in the app
    window.NewFocusAreaOverlay = {
        init,
        open
    };
})();
