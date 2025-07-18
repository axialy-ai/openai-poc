// /js/refine-tab-initialization.js

// Define the Refine Tab Initialization Module using IIFE for encapsulation
var RefineTabInitializationModule = (function() {
    /**
     * Initializes the Refine Tab functionalities.
     */
    function initializeRefineTab() {
        // REMOVE calls to InputTextModule, FocusAreasModule, ProcessFeedbackModule, 
        // because they are “Generate” features not used in the Refine tab.

        // If the Refine tab actually needs dynamic ribbons toggles, keep them:
        if (typeof DynamicRibbonsModule !== 'undefined' && DynamicRibbonsModule.setupRibbonToggles) {
            // Pass false if no “Save Data” button logic is needed
            DynamicRibbonsModule.setupRibbonToggles(false);
        }

        // Also remove the “auto-focus multi-line-input,” as that's for Generate
        // So we do not focus #multi-line-input here.
    }

    /**
     * Expose the initialize function.
     */
    return {
        initializeRefineTab: initializeRefineTab
    };
})();

// We do NOT actually run “initializeRefineTab()” on DOMContentLoaded 
// because layout.js or the user’s code triggers it only if desired:
document.addEventListener('DOMContentLoaded', function() {
    if (typeof RefineTabInitializationModule !== 'undefined' 
        && RefineTabInitializationModule.initializeRefineTab) {
        // Optionally remove this auto-run if layout.js calls it itself. 
        // Or leave it out entirely.
        // RefineTabInitializationModule.initializeRefineTab();
    }
});
