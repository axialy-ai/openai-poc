# /ui.axialy.ai/js/

This directory contains the core JavaScript modules driving the Axialy UI. Files are organized by feature: global account actions, feedback flows, content review, focus-area management, dynamic ribbons, export utilities, support tickets, and tab loaders.

## Files

- **`account-actions.js`**  
  Handles top-bar account actions: opening feedback/report forms, toggling light/dark mode, logging out, ending a demo, and initializing theme from `localStorage`.

- **`apply-revisions-handler.js`**  
  Key logic for the “Revisions Summary” overlay and final AI submission: merges feedback, builds summary and revised-records overlays, and posts final changes to the server.

- **`collate-feedback.js`**  
  Fetches and displays stakeholder feedback in a modal with collapsible sections. Lets users pick “Apply”, “Add Instruction” or “Ignore” before handing off to `ApplyRevisionsHandler`.

- **`content-review-overlay.js`**  
  Renders the initial “Content Review Plan” overlay: select stakeholders, enter ad-hoc emails, and advance to a feedback input step.

- **`content-review.js`**  
  Two-step flow: gather stakeholder emails and personal message, then show a preview overlay of focus-area records to include/exclude before sending review requests.

- **`content-revision.js`**  
  Opens an overlay for editing existing focus-area records (including soft-deleted/new), allows add/remove, and commits to create a new version.

- **`dynamic-ribbons.js`**  
  Displays AI-generated “ribbons” for a focus area, each with expand/collapse toggles, delete-all links, editable grids, and collects ribbon data for saving.

- **`enhance-content.js`**  
  Implements an “Enhance Focus Area” feature: takes a single user instruction, calls AI to transform records, shows summary and revised overlays, then saves.

- **`export-csv.js`**  
  Converts focus-area record JSON to CSV (flattening properties) and triggers a client-side download when users click export buttons.

- **`feedback-confirmation.js`**  
  Manages simple overlays for “Provide Experience Feedback” and “View Pending Requests” and closes them on button click or ESC.

- **`focus-area-version.js`**  
  Tracks focus-area version numbers: lets you create a new version and updates the UI display.

- **`focus-areas-initialization.js`**  
  On DOM load, initializes focus area modules and optional ribbon toggles.

- **`focus-areas-module.js`**  
  Placeholder for additional Focus Areas features—currently logs initialization and provides an API for future enhancements.

- **`focus-areas.js`**  
  Populates the “Choose Focus Areas” form by fetching JSON templates, sorting files/directories, handling nested checkboxes, expand/collapse, and select-all/unselect-all logic.

- **`input-text.js`**  
  Manages the multi-line input field’s character count, visual warnings for length limits, and listens for summary save events to update the input summary display. Exposes initialization and update functions.

- **`layout.js`**  
  Orchestrates UI layout: synchronizes control panel tabs and dropdown menu, updates page title and background settings, adjusts the overview panel height, handles control panel pinning, dropdown toggles, settings/help dropdowns, error logging, and dynamic loading of CSS/JS per tab.

- **`new-focus-area-overlay.js`**  
  Implements the overlay flow for creating a new focus area: collects focus area name and properties, optionally gathers initial records, and posts to the server to persist new focus areas and records.

- **`overlay.js`**  
  Provides a generic overlay module for showing loading messages, review headers (with organization dropdown), message overlays, masks, and utility functions for making fields editable and handling overlay lifecycle.

- **`process-feedback.js`**  
  Drives the Generate tab’s AI feedback workflow: manages button states and spinners, summarizes input text, sends AI requests per template, processes focus-area records, and coordinates saving workflows including error handling.

- **`ribbon-handler.js`**  
  Handles static ribbons (e.g. “Notifications”) separately from dynamic ribbons: toggles visibility and injects example content.

- **`ribbon-toggles.js`**  
  Provides a utility to wire up expand/collapse icons on common ribbons (`.input-ribbon`, `.feedback-ribbon`, `.packages-ribbon`) and optionally refresh save-button state.

- **`revised-records-overlay.js`**  
  Shows the final overlay of updated records (sorted by `focusAreaRecordNumber`), labeled “Record [n]”, with editable inputs before committing.

- **`save-analysis-package.js`**  
  Orchestrates “Save Data”: collects ribbon data, requests an AI-generated package header, shows a header-review overlay, and saves the final package and records.

- **`stakeholder-content-review.js`**  
  For itemized feedback forms: opens a primary-feedback overlay, handles primary/secondary button flows, and restores prior selections on reload.

- **`support-tickets.js`**  
  Provides a full “Support Tickets” overlay: list open/closed tickets, view details, and create new tickets via AJAX.

- **`update-overview-panel.js`**  
  Dynamically loads HTML, CSS, and JS for the Dashboard and Settings tabs into the overview panel and initializes their modules.

## Subdirectories

- **`dashboard/`** &mdash; Feedback Dashboard components (API, state, UI, events, index).  
- **`publish/`** &mdash; Placeholder for Publish-tab logic.  
- **`settings/`** &mdash; Settings tab modules (API, state, UI, events, utils).
