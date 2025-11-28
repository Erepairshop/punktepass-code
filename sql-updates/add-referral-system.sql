-- ============================================================
-- PunktePass Referral System - Database Structure
-- Version: 1.0
-- Date: 2024-11-28
-- ============================================================

-- Add referral columns to stores table
ALTER TABLE wp_ppv_stores
ADD COLUMN IF NOT EXISTS referral_enabled TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS referral_grace_days INT DEFAULT 60,
ADD COLUMN IF NOT EXISTS referral_activated_at DATETIME DEFAULT NULL,
ADD COLUMN IF NOT EXISTS referral_reward_type ENUM('points', 'euro', 'gift') DEFAULT 'points',
ADD COLUMN IF NOT EXISTS referral_reward_value INT DEFAULT 50,
ADD COLUMN IF NOT EXISTS referral_reward_gift VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS referral_manual_approval TINYINT(1) DEFAULT 0;

-- Referrals table - tracks who invited whom to which store
CREATE TABLE IF NOT EXISTS wp_ppv_referrals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Who invited
    referrer_user_id BIGINT UNSIGNED NOT NULL,

    -- Who was invited
    referred_user_id BIGINT UNSIGNED NOT NULL,

    -- Which store (store-specific referral)
    store_id BIGINT UNSIGNED NOT NULL,

    -- Unique referral code used
    referral_code VARCHAR(32) NOT NULL,

    -- Status tracking
    status ENUM('pending', 'completed', 'approved', 'rejected', 'expired') DEFAULT 'pending',

    -- Reward details (snapshot at time of referral)
    reward_type ENUM('points', 'euro', 'gift') NOT NULL,
    reward_value INT NOT NULL,
    reward_gift VARCHAR(255) DEFAULT NULL,

    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,

    -- Tracking
    referrer_rewarded TINYINT(1) DEFAULT 0,
    referred_rewarded TINYINT(1) DEFAULT 0,

    -- Indexes
    INDEX idx_referrer (referrer_user_id),
    INDEX idx_referred (referred_user_id),
    INDEX idx_store (store_id),
    INDEX idx_code (referral_code),
    INDEX idx_status (status),

    -- Foreign keys (optional, depends on your setup)
    -- FOREIGN KEY (referrer_user_id) REFERENCES wp_ppv_users(id),
    -- FOREIGN KEY (referred_user_id) REFERENCES wp_ppv_users(id),
    -- FOREIGN KEY (store_id) REFERENCES wp_ppv_stores(id)

    UNIQUE KEY unique_referral (referred_user_id, store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User referral codes table (one code per user, reusable across stores)
CREATE TABLE IF NOT EXISTS wp_ppv_user_referral_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    referral_code VARCHAR(8) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_code (referral_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Example queries for reference:
-- ============================================================

-- Check if grace period is over for a store:
-- SELECT * FROM wp_ppv_stores
-- WHERE referral_enabled = 1
-- AND referral_activated_at IS NOT NULL
-- AND DATEDIFF(NOW(), referral_activated_at) >= referral_grace_days;

-- Get referral stats for a store:
-- SELECT
--     COUNT(*) as total_referrals,
--     SUM(CASE WHEN status = 'completed' OR status = 'approved' THEN 1 ELSE 0 END) as successful,
--     SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
--     SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
-- FROM wp_ppv_referrals WHERE store_id = ?;
