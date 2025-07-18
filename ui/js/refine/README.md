# /ui.axialy.ai/js/refine/

This folder implements the “Refine” tab, where users review and refine AI-generated focus-area records, request or collate feedback, and manage package versions.

## Files

- **actions.js**  
  `RefineActionsModule.handleActivitySelection()`  
  - Maps each “Refine Activity” (Enhance Content, Request Feedback, Compile Feedback, Delete Focus Area, Recover Versions, Edit Content) to its corresponding module call.

- **api.js**  
  `RefineApiModule`  
  - `fetchPackages(searchTerm, showDeleted)` → `/api/get_analysis_packages_with_metrics.php`  
  - `fetchFocusAreaRecords(packageId, showDeleted, focusAreaVersionNumber)`  
  - `fetchRefineActivities()` → `config/refine-activities.json`  
  - `fetchStakeholderFeedback(...)`, `deleteFocusAreaData(data)`

- **augment-focus-area.js**  
  `FocusAreaAugmentationModule`  
  - Overlay to collect user instructions, send existing records + instructions to AI (`Focus_Area_Augmentation`), display & allow editing of new records, then commit via `save_revised_records.php`.

- **axialyAssessmentModule.js**  
  `AxialyAssessmentModule`  
  - Multi-package “Axialy Advisor”  
  - Fetches all packages, strips long descriptions, calls `axialy_analysis_package_assessor.php`, displays ranked package tiles in an overlay, supports “Reconsider” and double-click to open a package in Refine.

- **axialyPackageAdvisorModule.js**  
  `AxialyPackageAdvisorModule`  
  - Single-package “Axialy Advisor”  
  - Gathers the open package (with focus areas & records), calls `axialy_analysis_package_advisor.php`, displays actionable advisements, double-click a tile to invoke a refine action (e.g., request feedback on a focus area).

- **edit-record-overlay.js**  
  `EditRecordOverlayModule`  
  - Overlay for editing a single focus-area record: separates ephemeral fields from user fields, supports AI enhancement, reset, delete/restore, and tracks changed keys.

- **events.js**  
  `RefineEventsModule`  
  - Core event wiring:  
    - Package search & selection  
    - Show-Deleted toggle button  
    - “Actions…” dropdown  
    - Focus-area “Refine Data” dropdown  
    - Package-level actions (New Focus Area, Refresh, Edit Header, Remove/Recover)

- **index.js**  
  `RefineIndexModule.initRefineTab()`  
  - Bootstraps the Refine tab on load:  
    1. Fetch refine activities  
    2. Load packages  
    3. Wire up events  
    4. Initialize Axialy multi/package advisors  
    5. Expose `expandFocusAreaInRefine()` and `reloadRefineTabAndOpenPackage()`

- **new-record-overlay.js**  
  `NewRecordOverlayModule`  
  - Overlay for creating a new focus-area record, mirrors edit-overlay logic, supports AI enhancement, reset, and commit.

- **recover-focus-area.js**  
  `RecoverFocusAreaModule`  
  - Overlay to list past focus-area versions (`fetch_past_versions.php`), select one, then restore via `process_recover_focus_area.php`.

- **state.js**  
  `RefineStateModule`  
  - In-memory store for:  
    - `selectedPackageId`  
    - `currentPackageName`  
    - `currentVersion`  
    - `currentStakeholders`  
    - `activeRefineActivities`

- **ui.js**  
  `RefineUIModule`  
  - Renders package summary tiles and focus-area record cards.  
  - Manages expand/collapse, record coloring for new/changed/deleted, CSV export buttons, and feedback indicators.

- **utils.js**  
  `RefineUtilsModule`  
  - `debounce()`  
  - `showPageMaskSpinner()` / `hidePageMaskSpinner()`  
  - `removeExistingDropdown()`
