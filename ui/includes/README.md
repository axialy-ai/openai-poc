# Shared Include Scripts

This folder provides common PHP modules that are included by multiple UI pages and endpoints.

## Files

- **account_creation.php**  
  Class `AccountCreation` handles user signup flows:  
  - checkEmailExists()  
  - createVerificationToken()  
  - sendVerificationEmail()  
  - verifyToken()  
  - createAccount() (+ welcome email)

- **api_auth.php**  
  `validateApiAccess()` enforces session authentication and subscription checks for AJAX/API calls, returning JSON errors or redirects.

- **auth.php**  
  Session & subscription management for page requests:  
  - `validateSession()`  
  - `requireAuth()` (with TOS acceptance)  
  - `checkSubscriptionStatus()`  
  - secure session setup  
  - expired-session cleanup

- **db_connection.php**  
  Bootstraps database connectivity via PDO and legacy mysqli, using credentials from Config, with error handling.

- **debug_utils.php**  
  Defines `debugLog($message, $data)` for timestamped server-side logging.

- **focus_org_session.php**  
  Functions to get/set the user’s current focus organization in `user_focus_organizations`.

- **html_sanitizer.php**  
  `sanitizeHTML($html)` strips dangerous tags, whitelists safe tags/attributes, and sanitizes `<a>` href/target.

- **validate_subscription.php**  
  JSON endpoint that returns `{ isValid, message }` based on the logged-in user’s subscription status.

- **validation.php**  
  General input-validation utilities for:  
  - freeform text  
  - summary inputs  
  - analysis package headers  
  - content-review request forms
