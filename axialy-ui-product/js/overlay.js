// /js/overlay.js
var OverlayModule = (function() {
    var overlayElement;

    /**
     * Shows an overlay with a loading message.
     * @param {string} message - The message to display in the overlay.
     */
    function showLoadingOverlay(message) {
        createOverlay();
        var content = overlayElement.querySelector('.overlay-content');
        content.innerHTML = '<p>' + message + '</p>';
    }

    /**
     * Displays the analysis package header data for user review and editing,
     * including a new "Organization" dropdown above "Header Title."
     *
     * @param {Object} headerData
     *    {
     *      'Header Title': '...',
     *      'Short Summary': '...',
     *      'Long Description': '...',
     *      organization_id: 'default' or numeric string,
     *    }
     * @param {Function} onSave   Callback(updatedPackageHeader)
     * @param {Function} onCancel Callback()
     */
    function showHeaderReviewOverlay(headerData, onSave, onCancel) {
        createOverlay();
        var content = overlayElement.querySelector('.overlay-content');
        content.innerHTML = '';

        var title = document.createElement('h2');
        title.textContent = 'Review Analysis Package Summary';

        var subtitle = document.createElement('p');
        subtitle.textContent = 'Please review and edit the summary details before saving.';

        var informativeMessage = document.createElement('p');
        informativeMessage.textContent =
            'Your business analysis data will be saved along with the header information detailed below.';

        // Container for all fields
        var reviewForm = document.createElement('div');
        reviewForm.className = 'review-form';

        // === 1) Organization Dropdown ===
        var orgFieldContainer = document.createElement('div');
        orgFieldContainer.className = 'field-container';

        var orgLabel = document.createElement('label');
        orgLabel.textContent = 'Organization';
        orgLabel.htmlFor = 'orgSelect';

        var orgSelect = document.createElement('select');
        orgSelect.id = 'orgSelect';

        orgFieldContainer.appendChild(orgLabel);
        orgFieldContainer.appendChild(orgSelect);
        reviewForm.appendChild(orgFieldContainer);

        // === 2) Header Title, Short Summary, Long Description Fields ===
        var fields = [
            { label: 'Header Title',     name: 'headerTitle',     value: headerData['Header Title']     },
            { label: 'Short Summary',    name: 'shortSummary',    value: headerData['Short Summary']    },
            { label: 'Long Description', name: 'longDescription', value: headerData['Long Description'] }
        ];

        fields.forEach(function(field) {
            var fieldContainer = document.createElement('div');
            fieldContainer.className = 'field-container';

            var fieldLabel = document.createElement('label');
            fieldLabel.textContent = field.label;
            fieldLabel.htmlFor = field.name;

            var fieldValue = document.createElement('div');
            fieldValue.className = 'field-value';
            fieldValue.textContent = field.value || '';

            // Make field editable on double-click
            fieldValue.addEventListener('dblclick', function() {
                makeEditable(fieldValue);
            });

            fieldContainer.appendChild(fieldLabel);
            fieldContainer.appendChild(fieldValue);
            reviewForm.appendChild(fieldContainer);
        });

        var userPromptMessage = document.createElement('p');
        userPromptMessage.textContent = 'Ready to save the analysis package?';

        // === Buttons ===
        var buttonContainer = document.createElement('div');
        buttonContainer.className = 'button-container';

        var saveButton = document.createElement('button');
        saveButton.textContent = 'Save Now';
        saveButton.className = 'overlay-button save-button';
        saveButton.addEventListener('click', function() {
            // Collect updated header data
            var updatedPackageHeader = {};

            // (A) The user-selected organization
            updatedPackageHeader['organization_id'] = orgSelect.value || 'default';

            // (B) The text fields
            var fieldValues = reviewForm.querySelectorAll('.field-value');
            updatedPackageHeader['Header Title']     = fieldValues[0].textContent;
            updatedPackageHeader['Short Summary']    = fieldValues[1].textContent;
            updatedPackageHeader['Long Description'] = fieldValues[2].textContent;

            if (typeof onSave === 'function') {
                onSave(updatedPackageHeader);
            }
            hideOverlay();
        });

        var cancelButton = document.createElement('button');
        cancelButton.textContent = 'Cancel';
        cancelButton.className = 'overlay-button cancel-button';
        cancelButton.addEventListener('click', function() {
            if (typeof onCancel === 'function') {
                onCancel();
            }
            hideOverlay();
        });

        buttonContainer.appendChild(saveButton);
        buttonContainer.appendChild(cancelButton);

        content.appendChild(title);
        content.appendChild(subtitle);
        content.appendChild(informativeMessage);
        content.appendChild(reviewForm);
        content.appendChild(userPromptMessage);
        content.appendChild(buttonContainer);

        // Populate organization dropdown
        if (typeof headerData.organization_id === 'undefined' || headerData.organization_id === null) {
            populateOrganizationDropdown(orgSelect, 'default');
        } else {
            populateOrganizationDropdown(orgSelect, headerData.organization_id);
        }
    }

    /**
     * Dynamically fetches custom organizations from /get_custom_organizations.php
     * and populates the org dropdown with "(Default Organization)" as the first option.
     */
    function populateOrganizationDropdown(selectEl, defaultOrgId) {
        console.log('Populating organization dropdown...');
        selectEl.innerHTML = '';

        fetch('/get_custom_organizations.php')
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    throw new Error(data.message || 'Failed to load custom orgs.');
                }
                // Add default first
                var defaultOption = document.createElement('option');
                defaultOption.value = 'default';
                defaultOption.textContent = '(Default Organization)';
                selectEl.appendChild(defaultOption);

                // Add each custom org
                data.organizations.forEach(function(org) {
                    var opt = document.createElement('option');
                    opt.value = String(org.id);
                    opt.textContent = org.custom_organization_name || 'Untitled Org';
                    selectEl.appendChild(opt);
                });

                // Attempt to set the dropdown value
                selectEl.value = (defaultOrgId === null ? 'default' : String(defaultOrgId));
                if (selectEl.value !== String(defaultOrgId)) {
                    console.warn('Could not set organization ID to desired value. Falling back to default.');
                    selectEl.value = 'default';
                }
            })
            .catch(err => {
                console.error('Error in populateOrganizationDropdown:', err);
                selectEl.innerHTML = '<option value="default">(Default Organization)</option>';
                selectEl.value = 'default';
            });
    }

    /**
     * Shows a simple message overlay (e.g., success or error).
     * @param {string}   message   - The message to display. If useHTML=true, interpret as HTML.
     * @param {Function} onClose   - Optional callback when the user clicks "Done."
     * @param {boolean}  [useHTML] - If true, interpret message as HTML instead of text.
     */
    function showMessageOverlay(message, onClose, useHTML) {
        createOverlay();
        var content = overlayElement.querySelector('.overlay-content');
        content.innerHTML = '';

        var messageParagraph = document.createElement('p');
        if (useHTML) {
            messageParagraph.innerHTML = message;
        } else {
            messageParagraph.textContent = message;
        }

        var doneButton = document.createElement('button');
        doneButton.textContent = 'Done';
        doneButton.className = 'overlay-button done-button';
        doneButton.addEventListener('click', function() {
            if (typeof onClose === 'function') {
                onClose();
            }
            hideOverlay();
        });

        content.appendChild(messageParagraph);
        content.appendChild(doneButton);
    }

    /**
     * Updates the overlay content (if present) with a "loading" style message (no spinner).
     * @param {string} message
     */
    function showLoadingMask(message) {
        if (!overlayElement) createOverlay();
        var content = overlayElement.querySelector('.overlay-content');
        content.innerHTML = '<p>' + message + '</p>';
    }

    /**
     * Hides (removes) the overlay from the DOM.
     */
    function hideOverlay() {
        if (overlayElement) {
            document.body.removeChild(overlayElement);
            overlayElement = null;
            document.body.style.overflow = 'auto'; // re-enable scrolling
        }
    }

    /**
     * Creates the overlay element if it doesn't exist.
     */
    function createOverlay() {
        if (overlayElement) return;

        overlayElement = document.createElement('div');
        overlayElement.className = 'overlay';

        var overlayContent = document.createElement('div');
        overlayContent.className = 'overlay-content';

        var closeButton = document.createElement('span');
        closeButton.className = 'close-overlay';
        closeButton.innerHTML = '&times;';
        closeButton.setAttribute('role', 'button');
        closeButton.setAttribute('aria-label', 'Close Overlay');
        closeButton.tabIndex = 0;
        closeButton.addEventListener('click', hideOverlay);

        // Close overlay on pressing 'Esc'
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlayElement) {
                hideOverlay();
            }
        });

        overlayContent.appendChild(closeButton);
        overlayElement.appendChild(overlayContent);
        document.body.appendChild(overlayElement);

        // Disable background scrolling
        document.body.style.overflow = 'hidden';
    }

    /**
     * Makes a <div> field editable by replacing it with a <textarea>,
     * restoring text on blur or Enter.
     */
    function makeEditable(element) {
        var textarea = document.createElement('textarea');
        textarea.value = element.textContent;
        textarea.className = 'editable-textarea';

        element.innerHTML = '';
        element.appendChild(textarea);

        textarea.focus();
        textarea.select();

        textarea.addEventListener('blur', function() {
            element.textContent = textarea.value;
        });

        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                textarea.blur();
            }
        });
    }

    // Expose public methods
    return {
        showLoadingOverlay: showLoadingOverlay,
        showHeaderReviewOverlay: showHeaderReviewOverlay,
        showMessageOverlay: showMessageOverlay,
        showLoadingMask: showLoadingMask,
        hideOverlay: hideOverlay
    };
})();
