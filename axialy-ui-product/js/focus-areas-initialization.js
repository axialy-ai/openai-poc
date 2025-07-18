// /js/focus-areas-initialization.js

// Define the Focus Areas Initialization Module using IIFE for encapsulation
var FocusAreasInitializationModule = (function() {
    /**
     * Initializes the Focus Areas functionalities.
     */
    function initializeFocusAreas() {
        // Initialize the Focus Areas module
        if (typeof FocusAreasModule !== 'undefined' && FocusAreasModule.initializeFocusAreas) {
            FocusAreasModule.initializeFocusAreas();
        }

        // Ensure that ribbon toggles are set up if necessary
        if (typeof DynamicRibbonsModule !== 'undefined' && DynamicRibbonsModule.setupRibbonToggles) {
            DynamicRibbonsModule.setupRibbonToggles(false); // Pass false if no Save Data button updates are needed
        }
    }

    /**
     * Expose the initialize function.
     */
    return {
        initializeFocusAreas: initializeFocusAreas
    };
})();

// Initialize the Focus Areas when its initialization script is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (typeof FocusAreasInitializationModule !== 'undefined' && FocusAreasInitializationModule.initializeFocusAreas) {
        FocusAreasInitializationModule.initializeFocusAreas();
    }
});
