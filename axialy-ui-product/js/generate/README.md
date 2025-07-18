# /ui.axialy.ai/js/generate/

This folder contains the JavaScript modules that power the “Generate” tab in the UI—where users input free-form text, choose AI focus areas, process feedback, and save generated data.

## Files

- **index.js**  
  Entry point for the Generate tab.  
  - Exposes `loadGenerateTab()` which fetches and injects `content/generate-tab.html` into the overview panel.  
  - After injection, wires up `initSaveDataEnhancement()` and then calls `initGenerateTab()`.  
  - `initGenerateTab()` safely initializes all child modules:  
    - `InputTextModule`  
    - `FocusAreasModule`  
    - `ProcessFeedbackModule`  
    - `DynamicRibbonsModule`  
    - `FocusAreaVersionModule`  
    - `GenerateUIModule`

- **save-data-enhancement.js**  
  Overrides the default “Save” button behavior in the Generate tab to show a two-button overlay:  
  - “Save as NEW Analysis Package”  
  - “Save to EXISTING Package”  
  - Implements:  
    - Overlay creation & removal  
    - Fetching active packages list (`fetch_active_packages_for_save.php`)  
    - Finalizing save to existing package via `save_data_existing_package.php`

- **ui.js**  
  UI helper for the Generate tab, bundled as `GenerateUIModule`.  
  - `setupRibbonToggles()` for collapsing/expanding `.input-ribbon` and `.feedback-ribbon` sections.  
  - Ensures the “Choose AI Focus Areas” section is expanded by default.  
  - Updates the Save button state after toggles.
