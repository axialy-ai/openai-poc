# /ui.axialy.ai/js/modules/

These modules provide common functionality used across multiple tabs.

## Files

- **subscription-validation-module.js**  
  `SubscriptionValidationModule.validateSubscription()`  
  - Fetches `/includes/validate_subscription.php`  
  - Redirects to `/subscription.php` if the session or subscription is invalid.

- **tab-navigation-module.js**  
  `TabNavigationModule`  
  - Handles clicks on sidebar tabs and dropdown changes in the top bar.  
  - Validates subscription before switching.  
  - Synchronizes active tab with the dropdown.  
  - Calls the appropriate `loadXTab()` function (`loadHomeTab`, `loadGenerateTab`, etc.).  
  - Logs detailed debug info for each step.

- **ui-utils-module.js**  
  `UIUtilsModule`  
  - `updatePageTitle(tabName)`: syncs the dropdown and browser title.  
  - `applyBackgroundSettings(element)`: reads data attributes for background image & opacity, applies them via CSS variables.  
  - `adjustOverviewPanel()`: resizes the overview panel to fill the viewport between header and footer.
