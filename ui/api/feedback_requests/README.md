# /ui.axialy.ai/api/feedback_requests

This folder contains endpoints that return chart-ready aggregates of stakeholder feedback requests.

## Files

- **organization.php**  
  **URL:** `/api/feedback_requests/organization.php`  
  Returns JSON with two arrays:  
    - `labels`: list of custom organization names  
    - `values`: corresponding counts of feedback requests  
  Supports optional query parameter:  
    - `status=Sent|Responded` – filter counts to only Sent (no response yet) or Responded requests.

- **status.php**  
  **URL:** `/api/feedback_requests/status.php`  
  Returns JSON with two arrays:  
    - `labels`: feedback status values (`Sent`, `Responded`)  
    - `values`: counts of requests in each status  
  Supports optional query parameter:  
    - `organization=<orgName>` – restrict counts to a single custom organization.
