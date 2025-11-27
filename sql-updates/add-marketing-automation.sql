-- Migration: Add Marketing Automation settings to stores table
-- Author: PunktePass / Claude
-- Date: 2025-11-27
-- Description: Enables stores to configure automated marketing features

-- ============================================================
-- GOOGLE REVIEW REQUEST
-- ============================================================
ALTER TABLE wp_ppv_stores
ADD COLUMN IF NOT EXISTS google_review_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable automatic Google review requests',
ADD COLUMN IF NOT EXISTS google_review_url VARCHAR(500) DEFAULT NULL COMMENT 'Google Review URL for the store',
ADD COLUMN IF NOT EXISTS google_review_threshold INT DEFAULT 100 COMMENT 'Points threshold to trigger review request',
ADD COLUMN IF NOT EXISTS google_review_frequency ENUM('once', 'monthly', 'quarterly') DEFAULT 'once' COMMENT 'How often to ask for reviews';

-- ============================================================
-- BIRTHDAY BONUS
-- ============================================================
ALTER TABLE wp_ppv_stores
ADD COLUMN IF NOT EXISTS birthday_bonus_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable birthday bonus automation',
ADD COLUMN IF NOT EXISTS birthday_bonus_type ENUM('double_points', 'fixed_points', 'free_product') DEFAULT 'double_points' COMMENT 'Type of birthday bonus',
ADD COLUMN IF NOT EXISTS birthday_bonus_value INT DEFAULT 0 COMMENT 'Value for fixed points bonus',
ADD COLUMN IF NOT EXISTS birthday_bonus_message VARCHAR(500) DEFAULT NULL COMMENT 'Custom birthday message';

-- ============================================================
-- COMEBACK CAMPAIGN (Inactive Customer)
-- ============================================================
ALTER TABLE wp_ppv_stores
ADD COLUMN IF NOT EXISTS comeback_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable comeback campaign for inactive customers',
ADD COLUMN IF NOT EXISTS comeback_days INT DEFAULT 30 COMMENT 'Days of inactivity before triggering',
ADD COLUMN IF NOT EXISTS comeback_bonus_type ENUM('double_points', 'fixed_points') DEFAULT 'double_points' COMMENT 'Type of comeback bonus',
ADD COLUMN IF NOT EXISTS comeback_bonus_value INT DEFAULT 50 COMMENT 'Value for fixed points comeback bonus',
ADD COLUMN IF NOT EXISTS comeback_message VARCHAR(500) DEFAULT NULL COMMENT 'Custom comeback message';

-- ============================================================
-- INDEXES
-- ============================================================
ALTER TABLE wp_ppv_stores
ADD INDEX IF NOT EXISTS idx_google_review_enabled (google_review_enabled),
ADD INDEX IF NOT EXISTS idx_birthday_bonus_enabled (birthday_bonus_enabled),
ADD INDEX IF NOT EXISTS idx_comeback_enabled (comeback_enabled);
