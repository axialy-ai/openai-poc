# /ui.axialy.ai/js/dashboard/

This folder implements the “Dashboard” tab, where you can browse and filter stakeholder feedback requests, view summary stats (organizations, packages, focus areas), and paginate through results.

## Files

- **api.js**  
  Exposes `DashboardAPIModule` for all data-fetching calls:  
  - `fetchDashboardFocusOrganizations()` → `/get_dashboard_custom_organizations.php`  
  - `fetchDashboardStakeholderEmails()` → `/get_dashboard_stakeholder_emails.php`  
  - `fetchDashboardAnalysisPackages()` → `/get_dashboard_analysis_packages.php`  
  - `fetchDashboardFocusAreas()` → `/get_dashboard_focus_areas.php`  
  - `fetchFeedbackRequests(filters)` → `/get_dashboard_stakeholder_feedback_requests.php`

- **events.js**  
  Defines `DashboardEventsModule` with all UI wiring:  
  - Filters form submit & reset  
  - Pagination (prev/next + direct page links)  
  - “Records per page” selector  
  - Collapse/expand toggle for the filters panel  
  - Populating filter dropdowns on load  
  - Fetching and re-rendering feedback requests whenever filters, pagination, or limit change

- **index.js**  
  The entry point (`DashboardIndexModule`):  
  - `initializeDashboardTab()` runs on load  
  - Calls `DashboardEventsModule.initializeFiltersAndData()` to fetch filters + initial data  
  - Marks state as initialized and hooks up all event listeners

- **state.js**  
  In-memory store (`DashboardStateModule`) for:  
  - `feedbackRequests` array  
  - Lookup arrays: `focusOrganizations`, `stakeholderEmails`, `analysisPackages`, `focusAreas`  
  - Pagination state: `currentPage`, `limit`, `totalCount`  
  - `isInitialized` flag

- **ui.js**  
  UI builder (`DashboardUIModule`):  
  - `populateFilterOptions(...)` fills each dropdown  
  - `renderFeedbackRequests(...)` draws the table rows (including new columns: approve_count, revise_count, skip_count, total_count)  
  - `renderPagination(currentPage, totalCount, limit)` builds page links  
  - `showToast(message, type)` displays Bootstrap toasts  
  - `sanitizeHTML(...)` & `createToast(...)` helpers

- **utils.js**  
  Miscellaneous helpers (`DashboardUtilsModule`):  
  - `sanitizeHTML(str)` to escape user data  
  - `createToast(message, type)` to construct a Bootstrap toast element
