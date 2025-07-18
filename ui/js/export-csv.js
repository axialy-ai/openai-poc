// /js/export-csv.js
console.log("ExportCSVModule file is loading...");

const ExportCSVModule = (function() {
    console.log("ExportCSVModule is now defined");

    /**
     * Converts an array of focus-area records to CSV format. 
     * The `properties` field is flattened so that each key in
     * the properties object is rendered as its own CSV column.
     *
     * @param {Object|Array} data - Typically an array of objects representing focus-area records.
     * @returns {string} - The CSV string.
     */
    function convertJSONToCSV(data) {
        console.log('Converting to CSV:', data);

        // If data is a single object, wrap in an array for uniform handling.
        if (!Array.isArray(data)) {
            if (data && typeof data === 'object') {
                data = [data];
            } else {
                return '';
            }
        }

        // If there are no records, return empty CSV
        if (data.length === 0) {
            return '';
        }

        // A list of top-level keys to exclude from CSV
        // (example: we skip large text fields that might bloat the CSV)
        const excludedFields = ['input_text_summary', 'input_text'];

        // We'll gather:
        //   - all top-level fields (excluding the above list and "properties")
        //   - all property keys from within each record’s properties object
        let allTopLevelKeys = new Set();
        let allPropertyKeys = new Set();

        // Pass 1: discover all column headers from top-level + properties
        data.forEach(record => {
            // Check each top-level key
            for (const key in record) {
                if (
                    !excludedFields.includes(key) &&
                    key !== 'properties' &&
                    record.hasOwnProperty(key)
                ) {
                    allTopLevelKeys.add(key);
                }
            }
            // Now check each property from record.properties (if it exists)
            if (record.properties) {
                let propObj = {};
                try {
                    // properties can be a string or object
                    propObj = typeof record.properties === 'string'
                        ? JSON.parse(record.properties)
                        : record.properties;
                } catch (e) {
                    console.warn('Error parsing record.properties:', e);
                    propObj = {};
                }
                for (const pKey in propObj) {
                    if (propObj.hasOwnProperty(pKey)) {
                        allPropertyKeys.add(pKey);
                    }
                }
            }
        });

        // Convert the sets into arrays (the order here is up to you; 
        // you could sort them, but we’ll just keep them as discovered)
        const topLevelHeaders = Array.from(allTopLevelKeys);
        const propertyHeaders = Array.from(allPropertyKeys);

        // Combine into one final header array
        const finalHeaders = topLevelHeaders.concat(propertyHeaders);

        // Start building CSV
        let csv = '';

        // CSV header line
        csv += finalHeaders.join(',') + '\n';

        // Pass 2: build each row from the final header list
        data.forEach(record => {
            // parse record.properties if present
            let propsObj = {};
            if (record.properties) {
                try {
                    propsObj = typeof record.properties === 'string'
                        ? JSON.parse(record.properties)
                        : record.properties;
                } catch (e) {
                    console.warn('Could not parse properties JSON:', e);
                    propsObj = {};
                }
            }

            // Prepare the row’s column values in the correct order
            let rowValues = finalHeaders.map(header => {
                let cellVal;
                if (topLevelHeaders.includes(header)) {
                    // top-level field
                    cellVal = record[header] !== undefined ? record[header] : '';
                } else {
                    // property field
                    cellVal = propsObj[header] !== undefined ? propsObj[header] : '';
                }

                // Escape double quotes
                cellVal = String(cellVal).replace(/"/g, '""');
                // If the cell value contains a comma, quote, or newline,
                // wrap it in quotes
                if (/[",\n]/.test(cellVal)) {
                    cellVal = `"${cellVal}"`;
                }
                return cellVal;
            });

            // Join row cells by comma
            csv += rowValues.join(',') + '\n';
        });

        console.log('CSV conversion complete. CSV length:', csv.length);
        return csv;
    }

    /**
     * Triggers CSV download
     * @param {string} csvContent
     * @param {string} filename
     */
    function downloadCSV(csvContent, filename) {
        console.log('Downloading CSV:', filename, csvContent.length, 'bytes.');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        if (navigator.msSaveBlob) {
            // For IE 10+
            navigator.msSaveBlob(blob, filename);
        } else {
            const link = document.createElement("a");
            if (typeof link.download !== 'undefined') {
                const url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                console.log('CSV download triggered successfully.');
            } else {
                console.warn('Download attribute not supported in this browser.');
            }
        }
    }

    /**
     * Sets up event listeners for .export-csv-btn
     * When the user clicks on the button, we read the dataset JSON,
     * convert it to CSV, and trigger a download. 
     */
    function setupExportButtons() {
        console.log('setupExportButtons: Adding click listeners to .export-csv-btn...');
        const buttons = document.querySelectorAll('.export-csv-btn');
        console.log(`Found ${buttons.length} .export-csv-btn elements.`);

        buttons.forEach(button => {
            button.addEventListener('click', event => {
                event.stopPropagation();

                // The “record item” container that holds the data to export
                const focusAreaRecordItem = button.closest('.focus-area-record-item');
                if (!focusAreaRecordItem) {
                    console.warn('No parent .focus-area-record-item for this export button.');
                    return;
                }

                // Retrieve data from data attribute
                const rawJson = focusAreaRecordItem.dataset.exportData || '[]';
                let exportData = [];
                try {
                    exportData = JSON.parse(rawJson);
                } catch (e) {
                    console.warn('Invalid JSON in data-exportData:', e);
                    exportData = [];
                }
                console.log('Focus-area record data for CSV export:', exportData);

                // Derive name for file from the tile’s H3 (if found),
                // or else default “focus_area”
                const headerEl = focusAreaRecordItem.querySelector('.focus-area-record-group-header h3');
                const fallbackName = 'focus_area';
                const focusAreaName = headerEl
                    ? headerEl.textContent.trim().replace(/\s+/g, '_').toLowerCase()
                    : fallbackName;

                // Convert to CSV
                const csvContent = convertJSONToCSV(exportData);
                if (!csvContent) {
                    console.warn('Empty or invalid CSV content.');
                    return;
                }

                // Trigger the download
                downloadCSV(csvContent, `${focusAreaName}.csv`);
            });
        });

        console.log('setupExportButtons completed.');
    }

    // Publicly exposed module methods
    const module = {
        setupExportButtons: setupExportButtons
    };

    // Attach to window for broader usage
    window.ExportCSVModule = module;
    return module;
})();
