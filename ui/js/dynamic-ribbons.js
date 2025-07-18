/****************************************************************************
 * /js/dynamic-ribbons.js
 *
 ***************************************************************************/
var DynamicRibbonsModule = (function() {
    /**
     * Stores references to each ribbon displayed:
     * [
     *   {
     *     ribbonHeader: HTMLElement,
     *     ribbonType: string,
     *     promptTitle: string,
     *     ribbonContainer: HTMLElement
     *   },
     *   ...
     * ]
     */
    let ribbonsDataCollection = [];

    /**
     * Displays a dynamic ribbon for the given data. Each ribbon is identified
     * by a "type" (like "ConceptualElaboration").
     *
     * @param {Object} ribbonsData - The redacted containing the relevant array of items.
     * @param {String} promptTitle - A title or label to prepend in the ribbon's header.
     * @param {number} summaryId - The ID of the input_text_summaries record.
     */
    function displayRibbons(ribbonsData, promptTitle, summaryId) {
        const ribbonsContainer = document.getElementById('ribbons-container');
        if (!ribbonsContainer) {
            console.error("Ribbons container element not found.");
            return;
        }

        // Assume there's only one key in ribbonsData
        const ribbonType = Object.keys(ribbonsData)[0];
        if (!ribbonType || !ribbonsData[ribbonType]) {
            console.error("Invalid ribbons data:", ribbonsData);
            return;
        }

        const ribbonContent = ribbonsData[ribbonType];
        const recordCount = Array.isArray(ribbonContent) ? ribbonContent.length : 0;

        // 1) Create the ribbon header
        const ribbonHeader = document.createElement('div');
        ribbonHeader.className = 'ribbon dynamic-ribbon';

        // 2) Toggle button
        const toggleButton = document.createElement('span');
        toggleButton.className = 'toggle-icon';
        toggleButton.innerHTML = '&#9654;'; // Right arrow (collapsed)

        // 3) Title
        const title = document.createElement('span');
        title.className = 'ribbon-title';
        title.textContent = promptTitle && promptTitle.trim() !== ''
            ? `${promptTitle} - ${formatRibbonType(ribbonType)}`
            : formatRibbonType(ribbonType);

        // 4) Record count
        const recordCountSpan = document.createElement('span');
        recordCountSpan.className = 'record-count';
        recordCountSpan.textContent = ` (${recordCount})`;

        // 5) Current UTC
        const currentUtcDateTime = getCurrentUtcDatetime();

        // Append to title
        title.appendChild(recordCountSpan);
        title.appendChild(document.createTextNode(` - ${currentUtcDateTime}`));

        // 6) "Delete All" link
        const deleteAllLink = document.createElement('a');
        deleteAllLink.href = '#';
        deleteAllLink.textContent = 'Delete All';
        deleteAllLink.className = 'delete-all-link';
        deleteAllLink.addEventListener('click', (e) => {
            e.preventDefault();
            ribbonHeader.remove();
            ribbonContainer.remove();
            ProcessFeedbackModule.updateSaveButtonState();
            removeRibbonDataFromCollection(ribbonHeader);
        });

        // 7) Title container
        const titleContainer = document.createElement('div');
        titleContainer.className = 'ribbon-header-content';
        titleContainer.appendChild(toggleButton);
        titleContainer.appendChild(title);
        titleContainer.appendChild(deleteAllLink);

        ribbonHeader.appendChild(titleContainer);

        // 8) Ribbon container for the grid
        const ribbonContainer = document.createElement('div');
        ribbonContainer.className = 'ribbon-container';
        ribbonContainer.style.display = 'none'; // Collapsed initially

        // Set data attributes
        ribbonHeader.setAttribute('data-focus-area-label', formatRibbonType(ribbonType));
        ribbonHeader.setAttribute('data-grid-index', getNextGridIndex(ribbonType));
        ribbonHeader.setAttribute('data-input-text-summaries-id', summaryId);

        // Toggle event
        toggleButton.addEventListener('click', function() {
            if (ribbonContainer.style.display === 'none') {
                ribbonContainer.style.display = 'block';
                toggleButton.innerHTML = '&#9660;'; // Down arrow
                addAddRowLink(ribbonContainer);
            } else {
                ribbonContainer.style.display = 'none';
                toggleButton.innerHTML = '&#9654;'; // Right arrow
                removeAddRowLink(ribbonContainer);
            }
            ProcessFeedbackModule.updateSaveButtonState();
        });

        // If we have content, create a table
        if (Array.isArray(ribbonContent) && ribbonContent.length > 0) {
            const table = createTableFromContent(ribbonContent, ribbonHeader);
            ribbonContainer.appendChild(table);
        } else {
            ribbonContainer.innerHTML = `<p>No terms available.</p>`;
        }

        ribbonsContainer.appendChild(ribbonHeader);
        ribbonsContainer.appendChild(ribbonContainer);

        // Track in ribbonsDataCollection
        ribbonsDataCollection.push({
            ribbonHeader:     ribbonHeader,
            ribbonType:       ribbonType,
            promptTitle:      promptTitle,
            ribbonContainer:  ribbonContainer
        });

        // Update record count
        updateRecordCount(ribbonHeader, ribbonContainer.querySelector('.ribbon-table'));
    }

    /**
     * Formats the ribbon type string (removing file extension, underscores, etc.).
     */
    function formatRibbonType(ribbonType) {
        return ribbonType.replace('.json', '').replace(/_/g, ' ');
    }

    /**
     * Removes the ribbon data from the in-memory collection.
     */
    function removeRibbonDataFromCollection(ribbonHeader) {
        ribbonsDataCollection = ribbonsDataCollection.filter(
            item => item.ribbonHeader !== ribbonHeader
        );
    }

    /**
     * Adds an "Add row" link to the content area if not already present.
     */
    function addAddRowLink(ribbonContainer) {
        if (ribbonContainer.querySelector('.add-row-link')) return;

        const addRowDiv = document.createElement('div');
        addRowDiv.className = 'add-row-container';

        const addRowLink = document.createElement('a');
        addRowLink.href = '#';
        addRowLink.textContent = 'Add row';
        addRowLink.className = 'add-row-link';
        addRowLink.style.cursor = 'pointer';

        addRowLink.setAttribute('tabindex', '0');
        addRowLink.setAttribute('role', 'button');
        addRowLink.setAttribute('aria-label', 'Add a new row to the data grid');

        addRowLink.addEventListener('click', (e) => {
            e.preventDefault();
            appendNewRow(ribbonContainer);
        });
        addRowLink.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                appendNewRow(ribbonContainer);
            }
        });

        addRowDiv.appendChild(addRowLink);
        ribbonContainer.appendChild(addRowDiv);
    }

    /**
     * Removes the "Add row" link from a container if present.
     */
    function removeAddRowLink(ribbonContainer) {
        const addRowDiv = ribbonContainer.querySelector('.add-row-container');
        if (addRowDiv) {
            addRowDiv.remove();
        }
    }

    /**
     * Appends a new editable row to the table inside the given container.
     */
    function appendNewRow(ribbonContainer) {
        const tableBody = ribbonContainer.querySelector('.ribbon-table tbody');
        if (!tableBody) {
            console.error('Data grid table not found.');
            return;
        }

        const newRow = document.createElement('tr');
        const columnsCount = tableBody.querySelectorAll('tr:first-child td, tr:first-child th').length - 1; // exclude Actions

        for (let i = 0; i < columnsCount; i++) {
            const td = document.createElement('td');
            const textarea = document.createElement('textarea');
            textarea.className = 'editable-cell';
            textarea.placeholder = 'Enter value';
            textarea.rows = 1;
            textarea.style.width = '100%';

            textarea.addEventListener('input', () => {
                textarea.style.height = 'auto';
                textarea.style.height = `${textarea.scrollHeight}px`;
            });
            textarea.addEventListener('blur', () => {
                td.textContent = textarea.value;
                ProcessFeedbackModule.updateSaveButtonState();
            });
            textarea.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    td.textContent = textarea.value;
                    ProcessFeedbackModule.updateSaveButtonState();
                }
            });

            td.appendChild(textarea);
            newRow.appendChild(td);
        }

        // Actions cell
        const actionTd = document.createElement('td');
        const deleteLink = document.createElement('a');
        deleteLink.href = '#';
        deleteLink.textContent = 'Delete';
        deleteLink.addEventListener('click', (e) => {
            e.preventDefault();
            newRow.remove();
            ProcessFeedbackModule.updateSaveButtonState();
            const ribbonHeader = ribbonContainer.previousElementSibling;
            updateRecordCount(ribbonHeader, tableBody.closest('.ribbon-table'));
        });
        actionTd.appendChild(deleteLink);
        newRow.appendChild(actionTd);

        tableBody.appendChild(newRow);

        const ribbonHeader = ribbonContainer.previousElementSibling;
        updateRecordCount(ribbonHeader, tableBody.closest('.ribbon-table'));
    }

    /**
     * Builds a table from the provided array of objects, plus an "Actions" column.
     */
    function createTableFromContent(content, ribbonHeader) {
        const table = document.createElement('table');
        table.className = 'ribbon-table';

        if (!Array.isArray(content) || content.length === 0 || typeof content[0] !== 'object') {
            console.error('Invalid content structure for table creation:', content);
            return table;
        }

        const columns = Object.keys(content[0]);
        const thead   = document.createElement('thead');
        const headerRow = document.createElement('tr');

        columns.forEach(col => {
            const th = document.createElement('th');
            th.textContent = col;
            headerRow.appendChild(th);
        });
        // Add "Actions" col
        const thActions = document.createElement('th');
        thActions.textContent = 'Actions';
        headerRow.appendChild(thActions);

        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        content.forEach((record) => {
            const row = document.createElement('tr');

            columns.forEach(col => {
                const td = document.createElement('td');
                td.textContent = record[col];
                td.addEventListener('dblclick', makeCellEditable);
                row.appendChild(td);
            });

            const actionTd = document.createElement('td');
            const deleteLink = document.createElement('a');
            deleteLink.href = '#';
            deleteLink.textContent = 'Delete';
            deleteLink.addEventListener('click', (e) => {
                e.preventDefault();
                row.remove();
                updateRecordCount(ribbonHeader, table);
                ProcessFeedbackModule.updateSaveButtonState();
            });
            actionTd.appendChild(deleteLink);
            row.appendChild(actionTd);

            tbody.appendChild(row);
        });
        table.appendChild(tbody);

        return table;
    }

    /**
     * Updates the record count in the ribbon header’s .record-count element.
     */
    function updateRecordCount(ribbonHeader, table) {
        const countSpan = ribbonHeader.querySelector('.record-count');
        const recordCount = table ? table.querySelectorAll('tbody tr').length : 0;
        if (countSpan) {
            countSpan.textContent = ` (${recordCount})`;
        }
    }

    /**
     * Makes a cell editable on double-click (in-table editing).
     */
    function makeCellEditable(event) {
        const cell = event.target;
        if (cell.querySelector('textarea')) {
            return; // already editing
        }
        const oldValue = cell.textContent.trim();

        const textarea = document.createElement('textarea');
        textarea.value = oldValue;
        textarea.className = 'editable-cell';

        cell.textContent = '';
        cell.appendChild(textarea);

        textarea.style.height = 'auto';
        textarea.style.height = `${textarea.scrollHeight}px`;
        textarea.style.width = '100%';
        textarea.focus();
        textarea.select();

        textarea.addEventListener('input', () => {
            textarea.style.height = 'auto';
            textarea.style.height = `${textarea.scrollHeight}px`;
        });

        textarea.addEventListener('blur', () => {
            cell.textContent = textarea.value;
            ProcessFeedbackModule.updateSaveButtonState();
        });

        textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                cell.textContent = textarea.value;
                textarea.blur();
            }
        });
    }

    /**
     * Returns current UTC datetime as 'YYYY-MM-DD HH:MM:SS'.
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
     * Collects data from all displayed ribbons.
     * @returns {Array} The aggregated data from each ribbon’s table.
     */
    function collectRibbonsData() {
        const collectedData = [];

        ribbonsDataCollection.forEach(item => {
            const { ribbonHeader, ribbonContainer, ribbonType } = item;
            const focusAreaLabel = ribbonHeader.getAttribute('data-focus-area-label') || '';
            const gridIndex = parseInt(ribbonHeader.getAttribute('data-grid-index'), 10);
            const summaryId = parseInt(ribbonHeader.getAttribute('data-input-text-summaries-id'), 10);

            const table = ribbonContainer.querySelector('.ribbon-table');
            if (!table || !summaryId) {
                console.warn(`Ribbon for '${focusAreaLabel}' has no valid table or summaryId.`);
                return;
            }

            const rowData = extractDataFromTable(table);
            rowData.forEach((row, idx) => {
                collectedData.push({
                    input_text_summaries_id: summaryId,
                    focus_area_label: focusAreaLabel,
                    grid_index: idx + 1,
                    properties: row
                });
            });
        });
        return collectedData;
    }

    /**
     * Extracts row data from a table, ignoring the last "Actions" column.
     */
    function extractDataFromTable(table) {
        const data = [];
        const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent).slice(0, -1); // exclude "Actions"
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const rowObj = {};
            // For each header except the last
            headers.forEach((hdr, idx) => {
                rowObj[hdr] = cells[idx]?.textContent.trim() || '';
            });
            data.push(rowObj);
        });
        return data;
    }

    /**
     * Clears all ribbons from the DOM and resets the data structure.
     */
    function clearRibbons() {
        const container = document.getElementById('ribbons-container');
        if (container) {
            container.innerHTML = '';
        }
        ribbonsDataCollection = [];
    }

    /**
     * Returns the next grid index for the specified ribbon type.
     */
    function getNextGridIndex(ribbonType) {
        let maxIndex = 0;
        ribbonsDataCollection.forEach(item => {
            if (item.ribbonType === ribbonType) {
                const existingIdx = parseInt(item.ribbonHeader.getAttribute('data-grid-index'), 10);
                if (existingIdx > maxIndex) {
                    maxIndex = existingIdx;
                }
            }
        });
        return maxIndex + 1;
    }

    /**
     * Optional initialization routine (currently unused).
     */
    function initializeDynamicRibbons() {
        // No additional init logic at the moment
    }

    // Public API
    return {
        initializeDynamicRibbons: initializeDynamicRibbons,
        displayRibbons: displayRibbons,
        collectRibbonsData: collectRibbonsData,
        clearRibbons: clearRibbons
    };
})();
