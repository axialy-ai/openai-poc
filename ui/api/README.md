# /ui.axialy.ai/api/

This directory exposes backend endpoints used by the UI to fetch and aggregate data.

## Root Endpoints

- **get_analysis_packages_with_metrics.php**  
  Retrieves all analysis packages available to the authenticated user, along with detailed metrics for each package:
  - Number of focus areas
  - Total records (current version)
  - Distinct data objects (focus area names)
  - Total AI input submissions
  - Feedback requests & responses counts
  - Unique responding stakeholders
  - Unreviewed feedback items
  - Respects query parameters:
    - `search` – filter by package name or ID
    - `showDeleted` – include soft-deleted packages
    - Automatically scoped to the user’s default and focus organization.

## Feedback Requests Summaries

- **feedback_requests/**  
  Endpoints to summarize stakeholder feedback by organization or by status:
  - See [feedback_requests/README.md](feedback_requests/README.md)

## Stakeholder Feedback Data

- **stakeholder_feedback/**  
  Endpoints to fetch raw stakeholder feedback metrics:
  - See [stakeholder_feedback/README.md](stakeholder_feedback/README.md)
