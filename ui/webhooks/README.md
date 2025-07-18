# /ui.axialy.ai/webhooks/

This directory implements the server-side endpoint for handling Stripe webhook events.

## Files

- **README.md**  
  This documentation file.

- **.htaccess**  
  Apache rewrite rules that map requests to `/webhooks/stripe` → `stripe.php`.

- **stripe.php**  
  Validates and processes incoming Stripe webhook payloads:
  - Uses Stripe’s official PHP SDK (`vendor/autoload.php`) and the configured webhook secret.  
  - Handles subscription events (`created`, `updated`, `deleted`) and invoice/payment failures.  
  - Updates the `ui_users` table in the database to reflect subscription status changes.  
  - Logs each event and any errors to the PHP error log.
