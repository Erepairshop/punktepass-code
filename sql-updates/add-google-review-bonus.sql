-- Migration: Add Google Review Bonus Points feature
-- Author: PunktePass / Claude
-- Date: 2025-11-28
-- Description: Adds bonus points feature for Google reviews
--              - Users get bonus points on their next scan after review request
--              - Tracks pending bonus status per user/store

-- ============================================================
-- ADD BONUS POINTS FIELD TO STORES TABLE
-- ============================================================
ALTER TABLE wp_ppv_stores
ADD COLUMN IF NOT EXISTS google_review_bonus_points INT DEFAULT 5 COMMENT 'Bonus points awarded for Google review (on next scan)';

-- ============================================================
-- ADD PENDING BONUS TRACKING TO REVIEW REQUESTS TABLE
-- ============================================================
ALTER TABLE wp_ppv_google_review_requests
ADD COLUMN IF NOT EXISTS bonus_pending TINYINT(1) DEFAULT 0 COMMENT 'User has pending bonus points to receive on next scan',
ADD COLUMN IF NOT EXISTS bonus_awarded_at DATETIME DEFAULT NULL COMMENT 'When the bonus was awarded';

-- ============================================================
-- INDEX FOR BONUS PENDING LOOKUP (used during QR scan)
-- ============================================================
ALTER TABLE wp_ppv_google_review_requests
ADD INDEX IF NOT EXISTS idx_bonus_pending (bonus_pending, user_id);
