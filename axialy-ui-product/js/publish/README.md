# /ui.axialy.ai/js/publish/

This minimal folder handles the “Publish” tab, where users can submit new ideas or feature requests.

## Files

- **index.js**  
  `PublishIndexModule.initializePublishTab()`  
  - Looks up DOM elements: idea input, submit button, confirmation box, spinner overlay  
  - On “Submit” click: validates input, shows spinner, POSTs to `/issue_ajax_actions.php?action=createTicket`  
  - On success: hides spinner, shows confirmation message, clears the input  
  - On failure: alerts the user  
