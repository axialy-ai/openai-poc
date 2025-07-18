// /js/ribbon-toggles.js

/**
 * Function to toggle visibility of common ribbons.
 * @param {boolean} callUpdateSaveButtonState - Whether to call ProcessFeedbackModule.updateSaveButtonState().
 */
function setupRibbonToggles(callUpdateSaveButtonState = false) {
    document.querySelectorAll('.input-ribbon, .feedback-ribbon').forEach(ribbon => {
        const toggleIcon = ribbon.querySelector('.toggle-icon');
        const contentContainer = ribbon.nextElementSibling;
        if (toggleIcon && contentContainer) {
            toggleIcon.addEventListener('click', (event) => {
                // Prevent click from propagating to the entire ribbon container
                event.stopPropagation();
                
                const computedStyle = window.getComputedStyle(contentContainer);
                if (computedStyle.display === 'none' || computedStyle.visibility === 'hidden') {
                    contentContainer.style.display = 'block';
                    toggleIcon.innerHTML = '⬆️';  // Change to up arrow
                } else {
                    contentContainer.style.display = 'none';
                    toggleIcon.innerHTML = '⬇️';  // Change to down arrow
                }
                // Conditionally update the Save Data button state
                if (callUpdateSaveButtonState &&
                    typeof ProcessFeedbackModule !== 'undefined' &&
                    typeof ProcessFeedbackModule.updateSaveButtonState === 'function') {
                    ProcessFeedbackModule.updateSaveButtonState();
                }
            });
        } else {
            console.warn('Toggle icon or content container not found for ribbon:', ribbon);
        }
    });
}

/**
 * Sets up a click listener on the "Analysis Packages" ribbon toggle icon 
 * to collapse/expand its content. 
 * Only the icon is clickable, not the entire ribbon header.
 */
function setupPackagesRibbonToggle() {
    const packagesRibbon = document.querySelector('.packages-ribbon');
    const packagesContent = document.querySelector('.packages-content');
    if (!packagesRibbon || !packagesContent) {
        return;
    }
    // The toggle icon is the <span class="toggle-icon"> inside .packages-ribbon
    const toggleIcon = packagesRibbon.querySelector('.toggle-icon');

    if (!toggleIcon) {
        return;
    }

    // Listen for clicks only on the toggle icon 
    toggleIcon.addEventListener('click', (event) => {
        // Stop the click from affecting the entire ribbon or the dropdown
        event.stopPropagation();

        // Toggle the 'hidden' class on the content container
        const isCollapsed = packagesContent.classList.toggle('hidden');

        // Swap the icon’s arrow (point right when collapsed, down when expanded)
        toggleIcon.innerHTML = isCollapsed ? '&#9654;' : '&#9660;';
    });
}
