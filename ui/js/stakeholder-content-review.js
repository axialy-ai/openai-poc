// /js/stakeholder-content-review.js

document.addEventListener('DOMContentLoaded', function() {
    const formType = window.FormType || 'General'; // 'General' or 'Itemized'

    // Overlay references (for itemized feedback)
    const primaryOverlay         = document.getElementById('itemized-primary-overlay');
    const closePrimaryOverlayBtn = document.getElementById('close-primary-overlay');
    const primaryOverlayText     = document.getElementById('primary-overlay-text');
    const donePrimaryBtn         = document.getElementById('done-primary-btn');

    // Page mask references
    const pageMask    = document.getElementById('page-mask');
    const progressMsg = document.getElementById('progress-message');

    // For "General" forms, only a single textarea is used; no itemized logic needed
    if (formType === 'General') {
        return;
    }

    // If "Itemized," we have separate primary/secondary responses
    const primaryButtons   = document.querySelectorAll('.primary-btn');
    const secondaryButtons = document.querySelectorAll('.secondary-btn');
    let currentGridIndex   = null; // We'll store the "grid_index" for the overlay

    function openPrimaryOverlay(gridIndex, existingText) {
        currentGridIndex       = gridIndex;
        primaryOverlayText.value = existingText || '';
        if (primaryOverlay) {
            primaryOverlay.style.display = 'flex';
        }
    }

    function closePrimaryOverlay() {
        if (primaryOverlay) {
            primaryOverlay.style.display = 'none';
        }
        currentGridIndex = null;
        primaryOverlayText.value = '';
    }

    // Done button in the overlay
    if (donePrimaryBtn) {
        donePrimaryBtn.addEventListener('click', function() {
            if (currentGridIndex === null) {
                closePrimaryOverlay();
                return;
            }
            // Save the overlay text to the hidden input
            const recordSelector = `.data-record[data-grid-index="${currentGridIndex}"]`;
            const recordContainer = document.querySelector(recordSelector);
            if (!recordContainer) {
                closePrimaryOverlay();
                return;
            }
            const stakeholderTextInput = recordContainer.querySelector('.stakeholder-text-input');
            stakeholderTextInput.value = primaryOverlayText.value.trim();

            // Mark the action in .action-input as primary
            const actionInput = recordContainer.querySelector('.action-input');
            actionInput.value = window.PrimaryResponse || 'Primary';

            // Highlight the primary button
            const buttonEl = recordContainer.querySelector('.primary-btn');
            if (buttonEl) {
                buttonEl.classList.add('highlighted');
            }

            // Un-highlight secondary if present
            const secondBtn = recordContainer.querySelector('.secondary-btn');
            if (secondBtn) {
                secondBtn.classList.remove('highlighted');
            }
            // e.g. green border
            recordContainer.style.borderColor = '#4CAF50';
            closePrimaryOverlay();
        });
    }

    // Close overlay on [X]
    if (closePrimaryOverlayBtn) {
        closePrimaryOverlayBtn.addEventListener('click', closePrimaryOverlay);
    }

    // ESC key closes the overlay
    document.addEventListener('keydown', function(evt) {
        if (evt.key === 'Escape') {
            closePrimaryOverlay();
        }
    });

    // Each primary button opens the overlay
    primaryButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const recordDiv = this.closest('.data-record');
            if (!recordDiv) return;
            const gridIndex    = recordDiv.getAttribute('data-grid-index');
            const existingText = recordDiv.querySelector('.stakeholder-text-input').value;
            openPrimaryOverlay(gridIndex, existingText);
        });
    });

    // Secondary button sets an action but doesn't open overlay
    secondaryButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const recordDiv = this.closest('.data-record');
            if (!recordDiv) return;

            const stakeholderTextInput = recordDiv.querySelector('.stakeholder-text-input');
            // For secondary, we assume no typed text is needed:
            stakeholderTextInput.value = '';

            const actionInput = recordDiv.querySelector('.action-input');
            actionInput.value = window.SecondaryResponse || 'Secondary';

            // highlight this button
            btn.classList.add('highlighted');
            // remove highlight from primary
            const primaryBtn = recordDiv.querySelector('.primary-btn');
            if (primaryBtn) {
                primaryBtn.classList.remove('highlighted');
            }
            // e.g. amber border
            recordDiv.style.borderColor = '#FFC107';
        });
    });

    // Restore prior selections if the page reloaded
    document.querySelectorAll('.data-record').forEach(recordDiv => {
        const initAction = recordDiv.getAttribute('data-initial-action') || '';
        const initText   = recordDiv.getAttribute('data-initial-text')   || '';
        if (!initAction) return;

        recordDiv.querySelector('.action-input').value         = initAction;
        recordDiv.querySelector('.stakeholder-text-input').value = initText;

        const primaryBtn   = recordDiv.querySelector('.primary-btn');
        const secondaryBtn = recordDiv.querySelector('.secondary-btn');

        // If it matches the PrimaryResponse, highlight the primary button
        if (primaryBtn && initAction === (window.PrimaryResponse || 'Primary')) {
            primaryBtn.classList.add('highlighted');
            recordDiv.style.borderColor = '#4CAF50';
        }
        // Or if it matches the SecondaryResponse, highlight the secondary
        else if (secondaryBtn && initAction === (window.SecondaryResponse || 'Secondary')) {
            secondaryBtn.classList.add('highlighted');
            recordDiv.style.borderColor = '#FFC107';
        }
    });
});

/**
 * Called by the form onsubmit (e.g. onsubmit="return handleFormSubmit('General')").
 */
function handleFormSubmit(formType) {
    if (formType === 'General') {
        const feedbackField = document.querySelector('textarea[name="feedback"]');
        if (feedbackField && feedbackField.value.trim() === '') {
            alert('Please enter your general feedback before submitting.');
            return false;
        }
    }
    // Confirm submission
    if (!confirm('Are you sure you want to submit your feedback?')) {
        return false;
    }
    // Show the page mask
    const pageMask   = document.getElementById('page-mask');
    const progressMsg= document.getElementById('progress-message');
    if (pageMask && progressMsg) {
        pageMask.style.display = 'flex';
        progressMsg.textContent = 'Processing your feedback...';
    }
    return true;
}
