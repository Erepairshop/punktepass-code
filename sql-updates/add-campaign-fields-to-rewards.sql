-- Migration 1.7: Add campaign fields to ppv_rewards table
-- This unifies campaigns and rewards into a single system
-- Campaigns = time-limited rewards (have start_date and end_date)
-- Usage: Run this SQL in the database if auto-migration doesn't work

ALTER TABLE wp_ppv_rewards
ADD COLUMN IF NOT EXISTS start_date DATE NULL COMMENT 'Campaign start date (NULL = always active)' AFTER active,
ADD COLUMN IF NOT EXISTS end_date DATE NULL COMMENT 'Campaign end date (NULL = no expiry)' AFTER start_date,
ADD COLUMN IF NOT EXISTS is_campaign TINYINT(1) DEFAULT 0 COMMENT '1 = time-limited campaign, 0 = regular reward' AFTER end_date;

-- Notes:
-- start_date = NULL means the reward is always active (no start restriction)
-- end_date = NULL means the reward never expires
-- is_campaign = 1 means it's a time-limited campaign (show with special badge)
-- is_campaign = 0 means it's a regular permanent reward

-- Update migration version
UPDATE wp_options SET option_value = '1.7' WHERE option_name = 'ppv_db_migration_version';
