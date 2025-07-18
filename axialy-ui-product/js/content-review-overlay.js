// /js/content-review-overlay.js
var ContentReviewOverlayModule = (function() {
    let refineActivities = [];

    // Initialize the module
    function init() {
        fetchRefineActivities();
        setupActivitiesButtons();
    }

    // Fetch refine-activities.json
    function fetchRefineActivities() {
        fetch('config/refine-activities.json')
            .then(response =>
                response.ok
                    ? response.json()
                    : Promise.reject('Failed to load refine-activities.json')
            )
            .then(data => {
                if (data.refineActivities && Array.isArray(data.refineActivities)) {
                    refineActivities = data.refineActivities.filter(activity => activity.active);
                } else {
                    refineActivities = [];
                }
            })
            .catch(error => {
                console.error('[ContentReviewOverlay] Error loading refine-activities:', error);
                refineActivities = [];
            });
    }

    // Setup Activities buttons event listeners
    function setupActivitiesButtons() {
        document.querySelectorAll('.activities-btn').forEach(button => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                const focusAreaItem = button.closest('.focus-area-record-item');
                if (!focusAreaItem) {
                    console.warn('[ContentReviewOverlay] No .focus-area-record-item found for activities button.');
                    return;
                }
                const dropdown = createActivitiesDropdown(focusAreaItem);
                dropdown.classList.toggle('visible');
            });
        });
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.activities-dropdown') && !event.target.closest('.activities-btn')) {
                removeExistingDropdown();
            }
        });
    }

    // Create Activities dropdown menu
    function createActivitiesDropdown(focusAreaItem) {
        removeExistingDropdown(); // Ensure only one dropdown is open
        const dropdown = document.createElement('div');
        dropdown.classList.add('activities-dropdown');

        refineActivities.forEach(activity => {
            const item = document.createElement('div');
            item.classList.add('activities-dropdown-item');
            item.textContent = activity.label;
            item.dataset.action = activity.action;
            if (activity.active) {
                item.addEventListener('click', () => handleActivitySelection(activity));
            } else {
                item.classList.add('inactive');
            }
            dropdown.appendChild(item);
        });

        // Append the dropdown to the focusAreaItem’s header
        const itemHeader = focusAreaItem.querySelector('.focus-area-record-header');
        if (itemHeader) {
            itemHeader.appendChild(dropdown);
        }
        return dropdown;
    }

    // Remove any existing dropdowns
    function removeExistingDropdown() {
        document.querySelectorAll('.activities-dropdown').forEach(dropdown => dropdown.remove());
    }

    // Handle activity selection
    function handleActivitySelection(activity) {
        removeExistingDropdown(); // Close dropdown
        if (activity.label === 'Request Stakeholder Feedback') {
            renderContentReviewOverlay();
        }
        // Future activities can be handled here
    }

    // Render the Content Review Plan Overlay
    function renderContentReviewOverlay() {
        const overlay = document.createElement('div');
        overlay.classList.add('overlay');
        overlay.id = 'content-review-overlay';

        const overlayContent = document.createElement('div');
        overlayContent.classList.add('overlay-content');

        // Overlay Title
        const title = document.createElement('h2');
        title.textContent = 'Content Review Plan';
        overlayContent.appendChild(title);

        // Instruction Message
        const instruction = document.createElement('p');
        instruction.textContent = 'Complete and approve the Content Review plan.';
        overlayContent.appendChild(instruction);

        // Multi-Select Stakeholders List
        const stakeholdersLabel = document.createElement('label');
        stakeholdersLabel.textContent = 'Select Stakeholders:';
        overlayContent.appendChild(stakeholdersLabel);

        const stakeholdersList = document.createElement('select');
        stakeholdersList.id = 'stakeholders-list';
        stakeholdersList.multiple = true;

        const stakeholders = getStakeholdersFromPackage(); // Implement as needed
        stakeholders.forEach(email => {
            const option = document.createElement('option');
            option.value = email;
            option.textContent = email;
            stakeholdersList.appendChild(option);
        });
        overlayContent.appendChild(stakeholdersList);

        // Optional Ad-Hoc Stakeholders Field
        const adHocLabel = document.createElement('label');
        adHocLabel.textContent = 'Ad-Hoc Stakeholders (comma-separated emails):';
        overlayContent.appendChild(adHocLabel);

        const adHocInput = document.createElement('input');
        adHocInput.type = 'text';
        adHocInput.id = 'ad-hoc-emails';
        adHocInput.placeholder = 'e.g., user1@example.com, user2@example.com';
        overlayContent.appendChild(adHocInput);

        // Next Button
        const nextButton = document.createElement('button');
        nextButton.textContent = 'Next';
        nextButton.id = 'content-review-next-btn';
        overlayContent.appendChild(nextButton);

        // Cancel Button
        const cancelButton = document.createElement('button');
        cancelButton.textContent = 'Cancel';
        cancelButton.id = 'content-review-cancel-btn';
        overlayContent.appendChild(cancelButton);

        overlay.appendChild(overlayContent);
        document.body.appendChild(overlay);

        // Event Listeners for Buttons
        cancelButton.addEventListener('click', () => {
            overlay.remove();
        });
        nextButton.addEventListener('click', () => {
            handleContentReviewNext(stakeholdersList, adHocInput.value, overlayContent);
        });
    }

    // Get stakeholders from the selected package
    function getStakeholdersFromPackage() {
        // Implement based on how stakeholders are stored in your application
        return ['stakeholder1@example.com', 'stakeholder2@example.com', 'stakeholder3@example.com'];
    }

    // Handle the "Next" button in the overlay
    function handleContentReviewNext(stakeholdersList, adHocEmails, overlayContent) {
        const selectedOptions = Array.from(stakeholdersList.selectedOptions);
        const selectedEmails = selectedOptions.map(option => option.value);

        const adHocEmailArray = adHocEmails
            .split(',')
            .map(email => email.trim())
            .filter(email => email !== '');

        const allEmails = [...new Set([...selectedEmails, ...adHocEmailArray])]; // remove duplicates
        if (allEmails.length === 0) {
            alert('Please select at least one stakeholder or enter ad-hoc emails.');
            return;
        }

        const invalidEmails = allEmails.filter(email => !validateEmail(email));
        if (invalidEmails.length > 0) {
            alert('The following emails are invalid: ' + invalidEmails.join(', '));
            return;
        }

        overlayContent.innerHTML = `
            <h2>Content Review Plan</h2>
            <p>Please review the content below and provide your feedback.</p>
        `;

        // Multi-line feedback input
        const feedbackLabel = document.createElement('label');
        feedbackLabel.textContent = 'Feedback:';
        overlayContent.appendChild(feedbackLabel);

        const feedbackTextarea = document.createElement('textarea');
        feedbackTextarea.id = 'content-review-feedback';
        feedbackTextarea.rows = 5;
        feedbackTextarea.style.width = '100%';
        overlayContent.appendChild(feedbackTextarea);

        // Finish Button
        const finishButton = document.createElement('button');
        finishButton.textContent = 'Finish';
        finishButton.id = 'content-review-finish-btn';
        overlayContent.appendChild(finishButton);

        // Cancel Button
        const cancelButton = document.createElement('button');
        cancelButton.textContent = 'Cancel';
        cancelButton.id = 'content-review-cancel-btn';
        overlayContent.appendChild(cancelButton);

        cancelButton.addEventListener('click', () => {
            overlayContent.parentElement.remove();
        });
        finishButton.addEventListener('click', () => {
            const feedback = feedbackTextarea.value.trim();
            if (feedback === '') {
                alert('Please provide your feedback before finishing.');
                return;
            }
            sendContentReviewEmails(allEmails, feedback);
            overlayContent.parentElement.remove();
        });
    }

    // Utility function to validate email format
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // Send content review emails via AJAX
    function sendContentReviewEmails(emails, feedback) {
        const packageId = getCurrentPackageId();
        fetch('send_content_review_emails.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ emails: emails, feedback: feedback, package_id: packageId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Content review requests have been sent successfully.');
            } else {
                alert('An error occurred while sending emails: ' + data.message);
                console.error(data.message);
            }
        })
        .catch(error => {
            alert('An error occurred while sending emails.');
            console.error('[ContentReviewOverlay] sendContentReviewEmails error:', error);
        });
    }

    // Get the current package ID
    function getCurrentPackageId() {
        // Implement how your app stores the package ID
        return window.selectedPackageId || 1;
    }

    // Expose init
    return {
        init: init
    };
})();

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', ContentReviewOverlayModule.init);
