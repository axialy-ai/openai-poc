# ui.axialy.ai

The **Axialy UI** is the front-end application for the Axia Business Analysis platform, providing a rich web interface for managing analysis packages, stakeholder feedback, custom organizations, and subscriptions.

---

## Table of Contents

1. [Features](#features)  
2. [Tech Stack](#tech-stack)  
3. [Prerequisites](#prerequisites)  
4. [Installation](#installation)  
5. [Configuration](#configuration)  
6. [Directory Structure](#directory-structure)  
7. [API Endpoints & Scripts](#api-endpoints--scripts)  
8. [Usage](#usage)  
9. [Deployment](#deployment)  
10. [Troubleshooting](#troubleshooting)  
11. [Contributing](#contributing)  
12. [License](#license)  

---

## Features

- **User Authentication**: Secure login, logout, email verification, and session management  
- **Terms of Service**: Enforced acceptance flow  
- **Analysis Packages**: Create, view, update, delete, and recover business analysis packages  
- **Focus Areas**: New, revision, deletion, recovery, and versioning with record-level edits  
- **Stakeholder Feedback**: Send review requests, PIN-based access, collect general & itemized feedback  
- **Custom Organizations**: Create, update, and focus organizations with logo uploads  
- **Subscription Management**: Stripe integration for trials, monthly/yearly plans, day passes, promo codes  
- **Support Tickets**: Submit and view issues  
- **Content Review & Summaries**: Tokenized review forms, enhanced/revised content flows  
- **Documentation Viewer**: In-app user documentation portal  

---

## Tech Stack

- **Backend**: PHP 7.4+ (PDO/MySQL)  
- **Frontend**: Bootstrap 5, vanilla JavaScript, Chart.js  
- **Payments**: [Stripe PHP SDK](https://github.com/stripe/stripe-php)  
- **Dependency Management**: Composer  
- **Session Storage**: MySQL (`ui_user_sessions`)  
- **SSL Certificates**: Custom `cacert.pem` for cURL/OpenSSL in `php.ini`  

---

## Prerequisites

- PHP 7.4+ with extensions:
  - `pdo_mysql`
  - `mbstring`
  - `curl`
  - `openssl`
- MySQL 5.7+ (or compatible)  
- Composer  
- A web server (Apache, Nginx, etc.) with PHP-FPM  
- Writable directories for:
  - PHP sessions (configured in `php.ini`)
  - `uploads/logos/`  

---

## Installation

1. **Clone the repository**  
   ```bash
   git clone git@github.com:your-org/axialy-ui.git ui.axialy.ai
   cd ui.axialy.ai
   ```

2. **Install PHP dependencies**  
   ```bash
   composer install
   ```

3. **Certificates**  
   Ensure your `php.ini` has:
   ```ini
   curl.cainfo = "/path/to/certs/cacert.pem"
   openssl.cafile = "/path/to/certs/cacert.pem"
   ```

4. **Database Setup**  
   - Create a MySQL database and run migrations or import the SQL schema.  
   - Create a dedicated DB user with appropriate privileges.

5. **Configuration**  
   Copy and customize your private config file (outside VCS), e.g.  
   ```php
   // private_axiaba/includes/Config.php
   return [
     'db_dsn'           => 'mysql:host=localhost;dbname=axialy',
     'db_user'          => 'axialy_user',
     'db_pass'          => 'secret',
     'stripe_api_key'   => 'sk_live_…',
     'stripe_publishable_key' => 'pk_live_…',
     'stripe_webhook_secret'  => 'whsec_…',
     'api_base_url'     => 'https://api.axialy.ai',
     'app_base_url'     => 'https://ui.axialy.ai',
     'internal_api_key' => 'YOUR_INTERNAL_KEY',
     'uploads' => [
       'logos_dir'      => '/var/www/uploads/logos'
     ],
     'app_version'      => '1.2.3'
   ];
   ```

---

## Directory Structure

```
ui.axialy.ai/
├── accepts_tos.php
├── index.php                    # Main application shell
├── login.php
├── logout.php
├── user-documentation.php       # Documentation viewer portal
├── subscription.php             # Subscription page (Stripe integration)
├── config/
│   ├── control-panel-menu.json
│   ├── account-actions.json
│   └── support-actions.json
├── assets/
│   ├── css/
│   └── img/
├── js/                          # Vanilla JS modules
├── vendor/                      # Composer dependencies
├── includes/                    # Shared includes: auth, db_connection, validation, debug_utils
├── data/                        # JSON fixtures (e.g. stakeholders_*.json)
├── process_*.php                # Handlers for content review, focus-area revision/delete/create/recover
├── save_*.php                   # Save analysis package, enhanced/revised records, summary data
├── store_*.php                  # Store focus-area version/header/feedback/summary data
├── submit_*.php                 # Submit content review and support issues
├── update_*.php                 # Update packages, custom orgs, focus org, etc.
├── redeem_promo_code.php        # Promo code redemption endpoint
├── recover_analysis_package.php # Recover deleted analysis package
├── remove_analysis_package.php  # Soft-delete analysis package
├── receive_stakeholder_feedback.php # PIN entry for external reviewers
├── serve_logo.php               # Secure logo file serving
├── start_verification.php       # Email verification initiation
├── verify_email.php             # Complete email verification & account creation
├── view_document.php            # (Deprecated) In-app document renderer
├── webhook.php                  # Stripe webhook handler
└── php.ini                      # PHP configuration (cURL/openssl certs)
```

---

## API Endpoints & Scripts

### Content Review & Feedback
- **process_content_review_request.php**: Handle review request JSON, create feedback headers, send emails.  
- **process_content_revision.php**: Apply manual revisions to focus areas with versioning.  
- **process_delete_focus_area_data.php**: Soft-delete a focus area, version and mark records.  
- **process_new_focus_area.php**: Create a new focus area (version 0) and insert records.  
- **process_recover_focus_area.php**: Recover a past focus-area version as a new current version.  
- **receive_stakeholder_feedback.php**: PIN-based login for stakeholder review forms.  
- **submit_content_review.php**: Log submission of content review forms.  
- **send_content_review_emails.php**: Batch send review invitation emails.

### Analysis Package Management
- **save_analysis_package.php**: Create/update package headers, focus areas, and link axialy_outputs.  
- **save_data_existing_package.php**: Append new focus-area data to existing package.  
- **remove_analysis_package.php**: Soft-delete a package.  
- **recover_analysis_package.php**: Restore a soft-deleted package.  
- **update_analysis_package.php**: Update package metadata (name, summary, custom org).

### Records & Summaries Storage
- **save_enhanced_records.php**: Handle “Enhance Content” flow with versioning and record updates.  
- **save_revised_records.php**: Handle full revision flow with preserve grid_index and feedback resolution.  
- **store_feedback_data.php**: Store focus-area records with `display_order`.  
- **store_summary.php**: Store single/multiple input text summaries.  
- **store_analysis_package_focus_area_version.php**: Create a new focus-area version row.  
- **store_analysis_package_header.php**: (Legacy) Insert new analysis package header.

### Subscription & Billing
- **redeem_promo_code.php**: Validate and apply promo codes with optional statement acceptance.  
- **subscription.php**: UI for Stripe subscription setup (SetupIntent & client-side).  
- **webhook.php**: Handle Stripe webhook events (e.g. payment succeeded).  

### Account & Organization Management
- **start_verification.php**: Initiate email verification for new accounts.  
- **verify_email.php**: Complete account setup with username/password.  
- **update_custom_organization.php**: Update org details and upload logos.  
- **update_focus_organization.php**: Set user’s current focus organization.  
- **serve_logo.php**: Securely serve uploaded logo files.

### Support & Issues
- **submit_issue.php**: Submit support tickets (issues) via AJAX.

### Miscellaneous
- **view_document.php**: (Deprecated) Fetch and render document content by key.

---

## Usage

1. **Start your web server** pointing its document root at `ui.axialy.ai/`.  
2. **Ensure sessions** can be written to your configured path.  
3. **Visit** `https://ui.axialy.ai/` in your browser.  
4. **Log in** or register to begin using the platform.  
5. Use the **Control Panel** to switch between tabs: Home, Generate, Refine, Dashboard, Publish, Settings.

---

## Deployment

- Copy files to a PHP-capable host (Apache/Nginx).  
- Ensure:
  - `uploads/logos/` is writable.
  - `private_axiaba/includes/Config.php` remains outside public webroot.
  - HTTPS with a valid SSL certificate is enabled.
- In production `php.ini`, set `display_errors = Off` and `log_errors = On`.

---

## Troubleshooting

- **White screen/PHP errors**: Enable `display_errors = On` temporarily; check logs.  
- **Database errors**: Verify DSN and credentials.  
- **Stripe errors**: Confirm API and webhook secrets.  
- **Assets missing**: Check document root and rewrite rules.

---

## Contributing

1. Fork the repository.  
2. Create a feature branch: `git checkout -b feature/YourFeature`.  
3. Commit your changes: `git commit -m "Add YourFeature"`.  
4. Push to your branch: `git push origin feature/YourFeature`.  
5. Open a Pull Request against `main`.

---

## License

This project is licensed under the **MIT License**. See the [LICENSE](LICENSE) file for details.
