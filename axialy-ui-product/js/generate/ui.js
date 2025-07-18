// /js/generate/ui.js
var GenerateUIModule = (function() {
    /**
     * Sets up ribbon toggles for elements like `.input-ribbon` and `.feedback-ribbon`.
     */
    function setupRibbonToggles() {
        document.querySelectorAll('.input-ribbon, .feedback-ribbon').forEach(ribbon => {
            const toggleIcon = ribbon.querySelector('.toggle-icon');
            const contentContainer = ribbon.nextElementSibling;
            if (toggleIcon && contentContainer) {
                toggleIcon.addEventListener('click', () => {
                    const computedStyle = window.getComputedStyle(contentContainer);
                    if (computedStyle.display === 'none') {
                        contentContainer.style.display = 'block';
                        toggleIcon.innerHTML = '⬆️';  // Change to up arrow
                    } else {
                        contentContainer.style.display = 'none';
                        toggleIcon.innerHTML = '⬇️';  // Change to down arrow
                    }
                    // Update the Save Data button state after toggling
                    if (typeof ProcessFeedbackModule !== 'undefined' && typeof ProcessFeedbackModule.updateSaveButtonState === 'function') {
                        ProcessFeedbackModule.updateSaveButtonState();
                    }
                });
            }
        });

        // --- Force the "Choose AI Focus Areas" to be expanded by default ---
        const focusRibbonBody = document.getElementById('chooseFocusRibbonBody');
        const focusRibbonIcon = document.getElementById('toggleChooseFocusRibbon');
        if (focusRibbonBody && focusRibbonIcon) {
            // Show the body
            focusRibbonBody.style.display = 'block';
            // Show an up arrow
            focusRibbonIcon.innerHTML = '⬆️';
        }
    }
    return {
        setupRibbonToggles: setupRibbonToggles
    };
})();
