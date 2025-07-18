# /ui.axialy.ai/api/stakeholder_feedback

This folder provides raw feedback-by-group data for constructing charts of stakeholder participation.

## Files

- **data.php**  
  **URL:** `/api/stakeholder_feedback/data.php`  
  Returns JSON with:  
    - `labels`: stakeholder group names  
    - `values`: counts of feedback records per group  
  Used to populate pie or bar charts showing which stakeholder groups have provided feedback.
