-- ============================================================
-- PunktePass Scan Race Condition Protection - Index Migration
-- Version: 1.0
-- Date: 2024-11-29
--
-- Purpose: Add indexes to improve duplicate scan detection
-- and race condition prevention queries performance
-- ============================================================

-- Index for duplicate scan detection query (user_id, store_id, created)
-- Used by: recent scan check within 5 seconds
ALTER TABLE wp_ppv_points
ADD INDEX IF NOT EXISTS idx_scan_duplicate_check (user_id, store_id, created DESC, type);

-- Index for streak bonus count query
-- Used by: counting qr_scan type scans per user per store
ALTER TABLE wp_ppv_points
ADD INDEX IF NOT EXISTS idx_scan_count (user_id, store_id, type);

-- ============================================================
-- NOTE: We intentionally do NOT add a UNIQUE constraint on
-- (user_id, store_id) because users CAN and SHOULD be able to
-- scan multiple times at the same store. The race condition
-- protection is handled by:
-- 1. Transaction wrapping the insert
-- 2. Duplicate check within 5-second window
-- 3. Rate limiting per store per day
-- ============================================================
