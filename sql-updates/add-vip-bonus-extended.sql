-- Migration: Extended VIP bonus settings for stores
-- Author: PunktePass / Claude
-- Date: 2025-11-22
-- Description: Adds multiple bonus types: percentage, fixed, streak, daily first scan

-- ========================================
-- 1. FIX PONT BONUS (VIP szintenként)
-- ========================================
ALTER TABLE wp_ppv_stores
ADD COLUMN IF NOT EXISTS vip_fix_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable fixed point bonus',
ADD COLUMN IF NOT EXISTS vip_fix_silver INT DEFAULT 5 COMMENT 'Fixed bonus points for Silver',
ADD COLUMN IF NOT EXISTS vip_fix_gold INT DEFAULT 10 COMMENT 'Fixed bonus points for Gold',
ADD COLUMN IF NOT EXISTS vip_fix_platinum INT DEFAULT 20 COMMENT 'Fixed bonus points for Platinum';

-- ========================================
-- 2. MINDEN X. SCAN BONUS (streak)
-- ========================================
ALTER TABLE wp_ppv_stores
ADD COLUMN IF NOT EXISTS vip_streak_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable every Xth scan bonus',
ADD COLUMN IF NOT EXISTS vip_streak_count INT DEFAULT 10 COMMENT 'Every X scans get bonus',
ADD COLUMN IF NOT EXISTS vip_streak_type VARCHAR(20) DEFAULT 'fixed' COMMENT 'Bonus type: fixed, double, triple',
ADD COLUMN IF NOT EXISTS vip_streak_silver INT DEFAULT 30 COMMENT 'Streak bonus for Silver',
ADD COLUMN IF NOT EXISTS vip_streak_gold INT DEFAULT 50 COMMENT 'Streak bonus for Gold',
ADD COLUMN IF NOT EXISTS vip_streak_platinum INT DEFAULT 100 COMMENT 'Streak bonus for Platinum';

-- ========================================
-- 3. ELSŐ NAPI SCAN BONUS
-- ========================================
ALTER TABLE wp_ppv_stores
ADD COLUMN IF NOT EXISTS vip_daily_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable first daily scan bonus',
ADD COLUMN IF NOT EXISTS vip_daily_silver INT DEFAULT 10 COMMENT 'Daily first scan bonus for Silver',
ADD COLUMN IF NOT EXISTS vip_daily_gold INT DEFAULT 20 COMMENT 'Daily first scan bonus for Gold',
ADD COLUMN IF NOT EXISTS vip_daily_platinum INT DEFAULT 30 COMMENT 'Daily first scan bonus for Platinum';

-- Create indexes for faster queries
ALTER TABLE wp_ppv_stores
ADD INDEX IF NOT EXISTS idx_vip_fix_enabled (vip_fix_enabled),
ADD INDEX IF NOT EXISTS idx_vip_streak_enabled (vip_streak_enabled),
ADD INDEX IF NOT EXISTS idx_vip_daily_enabled (vip_daily_enabled);
