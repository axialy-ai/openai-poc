# /ui.axialy.ai/js/settings/

This folder powers the “Settings” tab, where administrators manage custom organizations and select the active focus organization.

## Files

- **api.js**  
  `SettingsAPIModule` for AJAX calls:  
  - `fetchCustomOrganizations()` → `/get_custom_organizations.php`  
  - `fetchCurrentFocusOrganization()` → `/get_focus_organization.php`  
  - `updateFocusOrganization(orgId)` → `/update_focus_organization.php`  
  - `createCustomOrganization(formData)` → `/create_custom_organization.php`  
  - `updateCustomOrganization(orgId, formData)` → `/update_custom_organization.php`

- **events.js**  
  `SettingsEventsModule.setupEventListeners(elements)` binds:  
  - Changing the focus-org dropdown → updates server & state  
  - Submitting the “create organization” form → POSTs, updates state/UI  
  - Clicking “Edit” on a card → toggles inline edit form  
  - Submitting the inline edit form → POSTs update, refreshes the card  
  - Canceling an inline edit

- **index.js**  
  `SettingsIndexModule.initializeSettingsTab()`  
  - Fetches org list & current focus org in parallel  
  - Updates `SettingsStateModule`  
  - Renders the org cards and focus-org dropdown (via `SettingsUIModule`)  
  - Caches key DOM elements and calls `SettingsEventsModule`

- **state.js**  
  `SettingsStateModule` holds:  
  - `currentFocusOrg`  
  - `customOrgs` array  
  - `isInitialized` flag  

- **ui.js**  
  `SettingsUIModule` for all DOM updates:  
  - `renderOrganizationsList(orgs)` → builds Bootstrap cards for each org  
  - `getEditOrgFormHTML(org)` → returns the inline edit form markup  
  - `updateFocusOrgDropdown(orgs)` → rebuilds the focus-org <select>  
  - `showToast(msg, type)` → Bootstrap toast notifications  
  - `showLoadingOverlay()` / `hideLoadingOverlay()` → spinner overlay

- **utils.js**  
  `SettingsUtilsModule` helpers:  
  - `sanitizeHTML(str)` to escape text  
  - `createToast(message, type)` to construct a toast element
