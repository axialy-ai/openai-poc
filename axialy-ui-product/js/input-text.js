// /js/input-text.js

var InputTextModule = (function() {
    const maxLength = 50000;

    /**
     * Initializes the input text functionalities.
     */
    function initializeInputText() {
        const inputTextField = document.getElementById('multi-line-input');

        if (inputTextField) {
            inputTextField.addEventListener('input', updateCharacterCount);

            // Initialize the character counter on page load
            updateCharacterCount();
        }

        // Listen for summary save events to update UI
        document.addEventListener('summarySaved', function(event) {
            const summary = event.detail;
            displaySummary(summary);
        });
    }

    /**
     * Updates the character count and visual indicators.
     */
    function updateCharacterCount() {
        const inputTextField = document.getElementById('multi-line-input');
        const charCounter = document.getElementById('char-counter');

        if (inputTextField && charCounter) {
            const currentLength = inputTextField.value.length;
            charCounter.textContent = `${currentLength.toLocaleString()} / ${maxLength.toLocaleString()} characters used`;

            // Update the visual indicator based on the length
            if (currentLength > maxLength) {
                charCounter.classList.add('exceeded');
                charCounter.classList.remove('warning');
            } else if (currentLength > maxLength * 0.8) {
                charCounter.classList.add('warning');
                charCounter.classList.remove('exceeded');
            } else {
                charCounter.classList.remove('warning', 'exceeded');
            }

            // Update button states
            ProcessFeedbackModule.updateSendButtonState();
            ProcessFeedbackModule.updateSaveButtonState();
        }
    }

    /**
     * Displays the saved summary in the UI.
     * @param {Object} summary - The summary data.
     */
    function displaySummary(summary) {
        document.getElementById('input-title-display').textContent = summary.input_text_title || 'N/A';
        document.getElementById('input-summary-display').textContent = summary.input_text_summary || 'N/A';
        document.getElementById('input-utc-display').textContent = summary.ui_datetime || 'N/A';
        document.getElementById('current-version').textContent = summary.version_number !== undefined ? summary.version_number : '0';
    }

    return {
        initializeInputText: initializeInputText,
        updateCharacterCount: updateCharacterCount // Expose the function
    };
})();
