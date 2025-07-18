# Configuration Files

This folder contains all of the JSON-based configuration files that drive the UI menus, actions, and feedback workflows.

## Files

- **account-actions.json**  
  Defines the entries in the user’s account dropdown menu.  
  - **accountActions**: array of objects with  
    - `label` (string): text shown to the user  
    - `actionType` (link|js|overlay|confirmation)  
    - `action` (URL or JS function)  
    - `active` (bool)

- **control-panel-menu.json**  
  Drives the left-hand “Control Panel” navigation.  
  - **menuOptions**: each with  
    - `name`, `tooltip`, `target` (tab identifier)  
    - `backgroundImage`, `backgroundOpacity`  
  - **viewsDropdown**: global dropdown background settings

- **feedback-response-types.json**  
  Lists the allowed “response types” for stakeholder feedback.  
  - **feedbackResponseTypes**: each with  
    - `label`, `primaryResponse`, `secondaryResponse`  
    - `description`, `active`

- **package-actions.json**  
  Defines the actions available at the analysis-package level.  
  - **packageActions**: each with  
    - `label`, `actionType`, `action`  
    - `description`, `active`

- **refine-activities.json**  
  Enumerates the “Refine” panel activities.  
  - **refineActivities**: each with  
    - `label`, `actionType`, `action`  
    - `description`, `active`

- **support-actions.json**  
  Populates the Help & Support dropdown.  
  - **supportActions**: each with  
    - `label`, `actionType`, `action`, `active`
