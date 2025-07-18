# /ui.axialy.ai/js/home/README.md

This folder powers the “Home” tab, where users enter their prompt, get AI-generated advice, and bootstrap a new analysis package.

## Files

- **home-tab.js**  
  - `initializeHomeTab()`: sets up the “Get Advice” form, sends the request to `axialy_helper.php`, and handles the response.  
  - `renderAxialyAdviceForm(advice)`: builds a rich, read-only form including:  
    - Scenario title, recap, advisement text  
    - Focus areas (with records, attributes, sub-forms for stakeholders)  
    - Summary and next-step sections  
  - “Yes, create this package!” flow:  
    1. Saves user input via `/store_summary.php`  
    2. Requests AI-generated package header (`Analysis_Package_Header` template)  
    3. Shows review overlay, then posts to `/save_analysis_package.php`  
    4. On success, displays a link to open the new package in the Refine tab  
  - `loadHomeTab()`: fetches and injects `content/home-tab.html` then calls `initializeHomeTab()`.
