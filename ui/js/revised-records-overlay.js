/****************************************************************************
 * /js/revised-records-overlay.js
 *
 * Displays a final overlay listing updated focus area records, letting
 * the user confirm or further edit them, then commit them to the server.
 *
 * Updated so that:
 *   1) We sort the final updatedRecords by "focusAreaRecordNumber" ascending.
 *   2) Each record tile is labeled "Record [n]" where [n] is "focusAreaRecordNumber".
 ****************************************************************************/
var RevisedRecordsOverlay = (function() {

    // We treat these fields as internal and don't show them as normal, editable columns.
    // The user specifically wants "focusAreaRecordNumber" used for the label, so we exclude it
    // from normal columns, just like we used to exclude "display_order".
    const EXCLUDED_FIELDS = [
        'stakeholderEmail',
        'stakeholder_feedback_headers_id',
        'action',
        'instructions',
        'originalContent',
        'focusAreaRecordNumber'  // We'll handle this specially in the first column
    ];

    let updatedRecordsGlobal     = [];
    let focusAreaNameGlobal      = '';
    let packageNameGlobal        = '';
    let packageIdGlobal          = 0;
    let focusAreaVersionIdGlobal = 0;
    let actionedFeedbackGlobal   = null;

    /**
     * Called externally to open the overlay with the final updatedRecords.
     * We:
     *   1) Sort updatedRecords by "focusAreaRecordNumber" ascending,
     *   2) Label each row as "Record [focusAreaRecordNumber]".
     */
    function open(
        updatedRecords,
        focusAreaName,
        packageName,
        packageId,
        focusAreaVersionId,
        actionedFeedback
    ) {
        // Store globally
        updatedRecordsGlobal     = updatedRecords || [];
        focusAreaNameGlobal      = focusAreaName;
        packageNameGlobal        = packageName;
        packageIdGlobal          = packageId;
        focusAreaVersionIdGlobal = focusAreaVersionId;
        actionedFeedbackGlobal   = actionedFeedback || null;

        // 1) Sort by "focusAreaRecordNumber" ascending (fallback to 9999 if missing or invalid)
        updatedRecordsGlobal.sort((a, b) => {
            const an = parseInt(a.focusAreaRecordNumber ?? 9999, 10);
            const bn = parseInt(b.focusAreaRecordNumber ?? 9999, 10);
            return an - bn;
        });

        // Build the overlay
        const overlay = document.createElement('div');
        overlay.id = 'revised-records-overlay';
        overlay.className = 'overlay';

        const overlayContent = document.createElement('div');
        overlayContent.className = 'overlay-content';

        // Close button
        const closeButton = document.createElement('span');
        closeButton.className = 'close-overlay';
        closeButton.innerHTML = '&times;';
        closeButton.setAttribute('aria-label', 'Close Revised Records');
        closeButton.setAttribute('role', 'button');
        closeButton.tabIndex = 0;
        closeButton.addEventListener('click', () => {
            overlay.remove();
        });

        // Title
        const title = document.createElement('h2');
        title.textContent = `Revised Records - ${focusAreaName} (Version: ${focusAreaVersionId})`;

        // Create a <form> to wrap the records table
        const form = document.createElement('form');
        form.id = 'revised-records-form';

        // Build table from updated records
        const table = buildRecordsTable(updatedRecordsGlobal);

        // "Commit Changes" button
        const commitBtn = document.createElement('button');
        commitBtn.type = 'button';
        commitBtn.className = 'overlay-button commit-btn';
        commitBtn.textContent = 'Commit Changes';
        commitBtn.addEventListener('click', () => {
            handleCommit(form);
        });

        form.appendChild(table);
        form.appendChild(commitBtn);

        // Assemble overlay
        overlayContent.appendChild(closeButton);
        overlayContent.appendChild(title);
        overlayContent.appendChild(form);
        overlay.appendChild(overlayContent);

        document.body.appendChild(overlay);
    }

    /**
     * Creates a <table> of the final updated records.
     * - We insert a first column that says "Record [focusAreaRecordNumber]"
     * - Then we build columns for everything else except EXCLUDED_FIELDS.
     */
    function buildRecordsTable(records) {
        const table = document.createElement('table');
        table.className = 'records-table';
        if (!records || records.length === 0) return table;

        // Collect all property names from the records
        let allKeys = new Set();
        records.forEach(rec => {
            Object.keys(rec).forEach(k => allKeys.add(k));
        });

        // Exclude the "internal" fields
        const headers = Array.from(allKeys).filter(k => !EXCLUDED_FIELDS.includes(k));

        // Build table head
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');

        // Insert the special "Record # (focusAreaRecordNumber)" column
        const thRecordNum = document.createElement('th');
        thRecordNum.textContent = 'Record #';
        headerRow.appendChild(thRecordNum);

        // Then build columns for the other field names
        headers.forEach(hdr => {
            const th = document.createElement('th');
            th.textContent = hdr;
            headerRow.appendChild(th);
        });

        thead.appendChild(headerRow);
        table.appendChild(thead);

        // Build table body
        const tbody = document.createElement('tbody');

        records.forEach(record => {
            const row = document.createElement('tr');

            // 1) "Record [focusAreaRecordNumber]" cell
            const tdRecNum = document.createElement('td');
            const recNumInt = parseInt(record.focusAreaRecordNumber ?? 9999, 10);
            tdRecNum.textContent = `Record ${recNumInt}`;
            row.appendChild(tdRecNum);

            // 2) Then columns for each of the remaining headers
            headers.forEach(hdr => {
                const td = document.createElement('td');
                // If it's 'focusAreaRecordID' or 'grid_index', we treat them as read-only
                if (hdr === 'focusAreaRecordID' || hdr === 'grid_index') {
                    td.textContent = record[hdr] ?? '';
                } else {
                    // Otherwise, let user edit
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = hdr;
                    input.value = record[hdr] ?? '';
                    td.appendChild(input);
                }
                row.appendChild(td);
            });

            tbody.appendChild(row);
        });

        table.appendChild(tbody);
        return table;
    }

    /**
     * Called when user clicks "Commit Changes".
     * Gathers updated data from the table and POSTs to /save_revised_records.php
     */
    function handleCommit(form) {
        const rows = form.querySelectorAll('tbody tr');
        const finalRecords = [];

        rows.forEach(row => {
            const recordObj = {};
            // The first <td> is "Record [focusAreaRecordNumber]" => we do not parse that back
            // so only gather from subsequent <input> fields
            const inputs = row.querySelectorAll('input');
            inputs.forEach(inp => {
                recordObj[inp.name] = inp.value;
            });
            finalRecords.push(recordObj);
        });

        // Show spinner
        showSpinnerOverlay('Saving revised records...');

        // Build payload
        const payload = {
            package_id:            packageIdGlobal,
            focus_area_name:       focusAreaNameGlobal,
            focus_area_version_id: focusAreaVersionIdGlobal,
            focus_area_records:    finalRecords
        };
        if (actionedFeedbackGlobal && Array.isArray(actionedFeedbackGlobal)) {
            payload.actionedFeedback = actionedFeedbackGlobal;
        }

        fetch('/save_revised_records.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(resp => resp.json())
        .then(data => {
            hideSpinnerOverlay();
            if (data.status === 'success') {
                alert('Revised records saved successfully.');
                const overlay = document.getElementById('revised-records-overlay');
                if (overlay) overlay.remove();
                // Possibly reload or do something else
                if (typeof reloadRefineTabAndOpenPackage === 'function') {
                    reloadRefineTabAndOpenPackage(packageIdGlobal, focusAreaNameGlobal);
                }
            } else {
                alert(`Failed to save revised records: ${data.error || data.message}`);
            }
        })
        .catch(err => {
            hideSpinnerOverlay();
            console.error('[revised-records-overlay] Error saving revised records:', err);
            alert('An error occurred while saving revised records.');
        });
    }

    function showSpinnerOverlay(message) {
        hideSpinnerOverlay();
        const overlay = document.createElement('div');
        overlay.id = 'spinner-overlay';
        overlay.className = 'overlay';

        const content = document.createElement('div');
        content.className = 'overlay-content';

        const spinner = document.createElement('div');
        spinner.className = 'spinner';

        const msgP = document.createElement('p');
        msgP.textContent = message;

        content.appendChild(spinner);
        content.appendChild(msgP);
        overlay.appendChild(content);
        document.body.appendChild(overlay);
    }

    function hideSpinnerOverlay() {
        const ov = document.getElementById('spinner-overlay');
        if (ov) ov.remove();
    }

    // Public API
    return {
        open
    };
})();
