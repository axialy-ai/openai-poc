-- Axialy UI Database Schema
-- Optimized for Digital Ocean Managed MySQL 8.0
-- Generated: 2025-01-07

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Disable foreign key checks during initial setup
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table: default_organizations
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `default_organizations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `default_organization_name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_organization_name` (`default_organization_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: ui_users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ui_users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `user_email` VARCHAR(255) NOT NULL,
  `default_organization_id` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subscription_active` TINYINT(1) NOT NULL DEFAULT 0,
  `subscription_id` VARCHAR(255) DEFAULT NULL,
  `trial_end_date` DATETIME DEFAULT NULL,
  `subscription_plan_type` VARCHAR(10) DEFAULT NULL,
  `sys_admin` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_user_email` (`user_email`),
  KEY `idx_default_organization_id` (`default_organization_id`),
  KEY `idx_subscription_active` (`subscription_active`),
  KEY `idx_subscription_id` (`subscription_id`),
  KEY `idx_trial_end_date` (`trial_end_date`),
  CONSTRAINT `fk_ui_users_default_org` 
    FOREIGN KEY (`default_organization_id`) 
    REFERENCES `default_organizations` (`id`) 
    ON DELETE RESTRICT 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: custom_organizations
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `custom_organizations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `custom_organization_name` VARCHAR(255) NOT NULL,
  `point_of_contact` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `organization_notes` TEXT DEFAULT NULL,
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `image_file` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_org_name` (`custom_organization_name`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_custom_orgs_user` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `ui_users` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: ui_user_sessions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ui_user_sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `session_token` VARCHAR(255) NOT NULL,
  `product` VARCHAR(10) NOT NULL DEFAULT 'ui',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_session_token` (`session_token`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_ui_sessions_user` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `ui_users` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: documents
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_key` VARCHAR(100) NOT NULL,
  `doc_name` VARCHAR(255) NOT NULL,
  `active_version_id` INT UNSIGNED DEFAULT NULL,
  `axia_customer_docs` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `file_pdf_data` LONGBLOB DEFAULT NULL,
  `file_docx_data` LONGBLOB DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_doc_key` (`doc_key`),
  KEY `idx_active_version` (`active_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: document_versions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `document_versions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `documents_id` INT UNSIGNED NOT NULL,
  `version_number` INT NOT NULL DEFAULT 0,
  `file_content` LONGTEXT DEFAULT NULL,
  `file_content_format` ENUM('md','html','json','xml') NOT NULL DEFAULT 'md',
  `file_pdf_data` LONGBLOB DEFAULT NULL,
  `file_docx_data` LONGBLOB DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_document_version` (`documents_id`, `version_number`),
  KEY `idx_documents_id` (`documents_id`),
  CONSTRAINT `fk_doc_versions_document` 
    FOREIGN KEY (`documents_id`) 
    REFERENCES `documents` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: input_text_summaries
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `input_text_summaries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `input_text_title` VARCHAR(255) NOT NULL,
  `input_text_summary` TEXT DEFAULT NULL,
  `input_text` LONGTEXT NOT NULL,
  `ui_datetime` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ui_datetime` (`ui_datetime`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: axialy_outputs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `axialy_outputs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `input_text_summaries_id` INT UNSIGNED NOT NULL,
  `analysis_package_headers_id` INT UNSIGNED NOT NULL,
  `axialy_scenario_title` VARCHAR(255) NOT NULL,
  `axialy_output_document` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_input_text_summaries_id` (`input_text_summaries_id`),
  KEY `idx_analysis_package_headers_id` (`analysis_package_headers_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: analysis_package_headers
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `analysis_package_headers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_name` VARCHAR(255) NOT NULL,
  `short_summary` TEXT DEFAULT NULL,
  `long_description` LONGTEXT DEFAULT NULL,
  `axialy_outputs_id` INT UNSIGNED DEFAULT NULL,
  `default_organization_id` INT NOT NULL,
  `custom_organization_id` INT DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_default_org` (`default_organization_id`),
  KEY `idx_custom_org` (`custom_organization_id`),
  KEY `idx_axialy_outputs` (`axialy_outputs_id`),
  KEY `idx_is_deleted` (`is_deleted`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_aph_default_org` 
    FOREIGN KEY (`default_organization_id`) 
    REFERENCES `default_organizations` (`id`) 
    ON DELETE RESTRICT 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_aph_custom_org` 
    FOREIGN KEY (`custom_organization_id`) 
    REFERENCES `custom_organizations` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_aph_axialy_outputs` 
    FOREIGN KEY (`axialy_outputs_id`) 
    REFERENCES `axialy_outputs` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: analysis_package_focus_areas
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `analysis_package_focus_areas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `analysis_package_headers_id` INT UNSIGNED NOT NULL,
  `current_analysis_package_focus_area_versions_id` INT UNSIGNED DEFAULT NULL,
  `focus_area_name` VARCHAR(255) NOT NULL,
  `focus_area_value` TEXT DEFAULT NULL,
  `collaboration_approach` TEXT DEFAULT NULL,
  `focus_area_abstract` TEXT DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_package_deleted` (`analysis_package_headers_id`, `is_deleted`),
  KEY `idx_current_version` (`current_analysis_package_focus_area_versions_id`),
  KEY `idx_headers_id` (`analysis_package_headers_id`),
  CONSTRAINT `fk_apfa_headers` 
    FOREIGN KEY (`analysis_package_headers_id`) 
    REFERENCES `analysis_package_headers` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: analysis_package_focus_area_versions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `analysis_package_focus_area_versions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `analysis_package_headers_id` INT UNSIGNED DEFAULT NULL,
  `analysis_package_focus_areas_id` INT UNSIGNED NOT NULL,
  `focus_area_version_number` INT UNSIGNED NOT NULL DEFAULT 0,
  `focus_area_revision_summary` TEXT DEFAULT NULL,
  `focus_area_name_override` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_area_version` (`analysis_package_focus_areas_id`, `focus_area_version_number`),
  KEY `idx_focus_areas_id` (`analysis_package_focus_areas_id`),
  KEY `idx_apfav_aph_id` (`analysis_package_headers_id`),
  CONSTRAINT `fk_apfav_focus_areas` 
    FOREIGN KEY (`analysis_package_focus_areas_id`) 
    REFERENCES `analysis_package_focus_areas` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_apfav_headers` 
    FOREIGN KEY (`analysis_package_headers_id`) 
    REFERENCES `analysis_package_headers` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key for current version after versions table exists
ALTER TABLE `analysis_package_focus_areas` 
  ADD CONSTRAINT `fk_apfa_current_version` 
  FOREIGN KEY (`current_analysis_package_focus_area_versions_id`) 
  REFERENCES `analysis_package_focus_area_versions` (`id`) 
  ON DELETE SET NULL 
  ON UPDATE CASCADE;

-- --------------------------------------------------------
-- Table: analysis_package_focus_area_records
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `analysis_package_focus_area_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `analysis_package_headers_id` INT UNSIGNED DEFAULT NULL,
  `analysis_package_focus_areas_id` INT UNSIGNED DEFAULT NULL,
  `analysis_package_focus_area_versions_id` INT UNSIGNED NOT NULL,
  `grid_index` INT DEFAULT NULL,
  `display_order` INT UNSIGNED NOT NULL DEFAULT 1,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `input_text_summaries_id` INT UNSIGNED DEFAULT NULL,
  `properties` LONGTEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_headers_id` (`analysis_package_headers_id`),
  KEY `idx_focus_areas_id` (`analysis_package_focus_areas_id`),
  KEY `idx_version_deleted` (`analysis_package_focus_area_versions_id`, `is_deleted`),
  KEY `idx_input_text_summaries` (`input_text_summaries_id`),
  CONSTRAINT `fk_apfar_headers` 
    FOREIGN KEY (`analysis_package_headers_id`) 
    REFERENCES `analysis_package_headers` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_apfar_focus_areas` 
    FOREIGN KEY (`analysis_package_focus_areas_id`) 
    REFERENCES `analysis_package_focus_areas` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_apfar_versions` 
    FOREIGN KEY (`analysis_package_focus_area_versions_id`) 
    REFERENCES `analysis_package_focus_area_versions` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_apfar_input_summaries` 
    FOREIGN KEY (`input_text_summaries_id`) 
    REFERENCES `input_text_summaries` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: promo_codes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `promo_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `code_type` ENUM('unlimited','limited') NOT NULL DEFAULT 'unlimited',
  `limited_days` INT UNSIGNED DEFAULT NULL,
  `statement_required` TINYINT(1) NOT NULL DEFAULT 0,
  `statement` TEXT DEFAULT NULL,
  `start_date` DATETIME DEFAULT NULL,
  `end_date` DATETIME DEFAULT NULL,
  `usage_limit` INT UNSIGNED DEFAULT NULL,
  `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_active` (`active`),
  KEY `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: promo_code_redemptions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `promo_code_redemptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `promo_code_id` INT UNSIGNED NOT NULL,
  `user_id` INT NOT NULL,
  `redeemed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_promo_code_id` (`promo_code_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_redeemed_at` (`redeemed_at`),
  CONSTRAINT `fk_pcr_promo_code` 
    FOREIGN KEY (`promo_code_id`) 
    REFERENCES `promo_codes` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_pcr_user` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `ui_users` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: issues
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `issues` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `issue_title` VARCHAR(255) NOT NULL,
  `issue_description` TEXT NOT NULL,
  `status` ENUM('New','Reviewed','Open','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_issues_user` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `ui_users` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: email_verifications
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_verifications` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_used` (`used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_agreement_acceptances
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_agreement_acceptances` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `document_versions_id` INT UNSIGNED NOT NULL,
  `accepted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_document_ver` (`user_id`, `document_versions_id`),
  KEY `idx_document_versions_id` (`document_versions_id`),
  KEY `idx_accepted_at` (`accepted_at`),
  CONSTRAINT `fk_uaa_user` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `ui_users` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_uaa_doc_version` 
    FOREIGN KEY (`document_versions_id`) 
    REFERENCES `document_versions` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: focus_organization
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `focus_organization` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `custom_organization_id` INT DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  KEY `idx_custom_organization_id` (`custom_organization_id`),
  CONSTRAINT `fk_focus_org_user` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `ui_users` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_focus_org_custom_org` 
    FOREIGN KEY (`custom_organization_id`) 
    REFERENCES `custom_organizations` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_focus_organizations
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_focus_organizations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `focus_org_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  KEY `idx_focus_org_id` (`focus_org_id`),
  CONSTRAINT `fk_ufo_user` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `ui_users` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_ufo_custom_org` 
    FOREIGN KEY (`focus_org_id`) 
    REFERENCES `custom_organizations` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: stakeholder_feedback_headers
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stakeholder_feedback_headers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `analysis_package_headers_id` INT UNSIGNED NOT NULL,
  `analysis_package_focus_areas_id` INT UNSIGNED NOT NULL,
  `analysis_package_focus_area_versions_id` INT UNSIGNED NOT NULL,
  `stakeholder_email` VARCHAR(255) NOT NULL,
  `email_personal_message` VARCHAR(255) NOT NULL,
  `form_type` ENUM('General','Itemized') NOT NULL DEFAULT 'General',
  `primary_response_option` VARCHAR(255) DEFAULT NULL,
  `secondary_response_option` VARCHAR(255) DEFAULT NULL,
  `stakeholder_request_grid_indexes` VARCHAR(255) NOT NULL DEFAULT '',
  `feedback_target` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `pin` INT NOT NULL,
  `responded_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aph_id` (`analysis_package_headers_id`),
  KEY `idx_apfa_id` (`analysis_package_focus_areas_id`),
  KEY `idx_apfav_id` (`analysis_package_focus_area_versions_id`),
  KEY `idx_token` (`token`),
  KEY `idx_responded_at` (`responded_at`),
  CONSTRAINT `fk_sfh_headers` 
    FOREIGN KEY (`analysis_package_headers_id`) 
    REFERENCES `analysis_package_headers` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_sfh_focus_areas` 
    FOREIGN KEY (`analysis_package_focus_areas_id`) 
    REFERENCES `analysis_package_focus_areas` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_sfh_versions` 
    FOREIGN KEY (`analysis_package_focus_area_versions_id`) 
    REFERENCES `analysis_package_focus_area_versions` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: stakeholder_feedback_header_requests
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stakeholder_feedback_header_requests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `stakeholder_email` VARCHAR(255) NOT NULL,
  `analysis_package_headers_id` INT UNSIGNED NOT NULL,
  `focus_areas_id` INT UNSIGNED NOT NULL,
  `message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aph_id` (`analysis_package_headers_id`),
  KEY `idx_focus_areas_id` (`focus_areas_id`),
  KEY `idx_email` (`stakeholder_email`),
  CONSTRAINT `fk_sfhr_headers` 
    FOREIGN KEY (`analysis_package_headers_id`) 
    REFERENCES `analysis_package_headers` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_sfhr_focus_areas` 
    FOREIGN KEY (`focus_areas_id`) 
    REFERENCES `analysis_package_focus_areas` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: stakeholder_general_feedback
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stakeholder_general_feedback` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `stakeholder_feedback_headers_id` INT NOT NULL,
  `analysis_package_headers_id` INT UNSIGNED DEFAULT NULL,
  `analysis_package_focus_areas_id` INT UNSIGNED DEFAULT NULL,
  `analysis_package_focus_area_versions_id` INT UNSIGNED NOT NULL,
  `stakeholder_feedback_text` TEXT NOT NULL,
  `resolved_action` VARCHAR(255) DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sfh_id` (`stakeholder_feedback_headers_id`),
  KEY `idx_aph_id` (`analysis_package_headers_id`),
  KEY `idx_apfa_id` (`analysis_package_focus_areas_id`),
  KEY `idx_apfav_id` (`analysis_package_focus_area_versions_id`),
  KEY `idx_resolved_at` (`resolved_at`),
  CONSTRAINT `fk_sgf_headers` 
    FOREIGN KEY (`stakeholder_feedback_headers_id`) 
    REFERENCES `stakeholder_feedback_headers` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_sgf_aph` 
    FOREIGN KEY (`analysis_package_headers_id`) 
    REFERENCES `analysis_package_headers` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_sgf_focus_areas` 
    FOREIGN KEY (`analysis_package_focus_areas_id`) 
    REFERENCES `analysis_package_focus_areas` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_sgf_versions` 
    FOREIGN KEY (`analysis_package_focus_area_versions_id`) 
    REFERENCES `analysis_package_focus_area_versions` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: stakeholder_itemized_feedback
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stakeholder_itemized_feedback` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `stakeholder_feedback_headers_id` INT NOT NULL,
  `analysis_package_headers_id` INT UNSIGNED NOT NULL,
  `analysis_package_focus_areas_id` INT UNSIGNED NOT NULL,
  `analysis_package_focus_area_versions_id` INT UNSIGNED NOT NULL,
  `grid_index` INT NOT NULL,
  `feedback_item_response` VARCHAR(255) NOT NULL,
  `stakeholder_feedback_text` TEXT DEFAULT NULL,
  `resolved_action` VARCHAR(255) DEFAULT NULL,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sfh_id` (`stakeholder_feedback_headers_id`),
  KEY `idx_aph_id` (`analysis_package_headers_id`),
  KEY `idx_apfa_id` (`analysis_package_focus_areas_id`),
  KEY `idx_apfav_id` (`analysis_package_focus_area_versions_id`),
  KEY `idx_grid_index` (`grid_index`),
  KEY `idx_resolved_at` (`resolved_at`),
  CONSTRAINT `fk_sif_headers` 
    FOREIGN KEY (`stakeholder_feedback_headers_id`) 
    REFERENCES `stakeholder_feedback_headers` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_sif_aph` 
    FOREIGN KEY (`analysis_package_headers_id`) 
    REFERENCES `analysis_package_headers` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_sif_focus_areas` 
    FOREIGN KEY (`analysis_package_focus_areas_id`) 
    REFERENCES `analysis_package_focus_areas` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_sif_versions` 
    FOREIGN KEY (`analysis_package_focus_area_versions_id`) 
    REFERENCES `analysis_package_focus_area_versions` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: stakeholder_experience_feedback
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stakeholder_experience_feedback` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `stakeholder_feedback_details_id` INT NOT NULL,
  `feedback_text` TEXT NOT NULL,
  `experience_feedback_text` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sfd_id` (`stakeholder_feedback_details_id`),
  CONSTRAINT `fk_sef_general_feedback` 
    FOREIGN KEY (`stakeholder_feedback_details_id`) 
    REFERENCES `stakeholder_general_feedback` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: stakeholder_sessions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stakeholder_sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `stakeholder_feedback_headers_id` INT NOT NULL,
  `session_token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sfh_id` (`stakeholder_feedback_headers_id`),
  KEY `idx_session_token` (`session_token`),
  CONSTRAINT `fk_ss_headers` 
    FOREIGN KEY (`stakeholder_feedback_headers_id`) 
    REFERENCES `stakeholder_feedback_headers` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
