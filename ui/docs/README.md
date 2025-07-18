# /ui.axialy.ai/docs/

# Documentation Viewer

This folder contains the script that retrieves and renders user documentation stored in the database.

## Files

- **view_document.php**  
  **URL:** `/docs/view_document.php?key=<doc_key>`  
  - Fetches the document record by `doc_key` and its active version.  
  - Renders content in the browser, converting Markdown to HTML, or embedding raw HTML/JSON/text.  
  - Provides download links for PDF and DOCX at both document-level and version-level via `?docDownload=pdf|docx` and `?download=pdf|docx`.
