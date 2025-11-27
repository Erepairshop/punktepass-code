-- Add user settings columns to ppv_users table
-- Run this migration to add birthday and other settings fields

-- Birthday field
ALTER TABLE wp_ppv_users ADD COLUMN IF NOT EXISTS birthday DATE DEFAULT NULL;

-- Display name (combined name)
ALTER TABLE wp_ppv_users ADD COLUMN IF NOT EXISTS display_name VARCHAR(255) DEFAULT NULL;

-- Avatar URL
ALTER TABLE wp_ppv_users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(500) DEFAULT NULL;

-- Address fields
ALTER TABLE wp_ppv_users ADD COLUMN IF NOT EXISTS address VARCHAR(255) DEFAULT NULL;
ALTER TABLE wp_ppv_users ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT NULL;
ALTER TABLE wp_ppv_users ADD COLUMN IF NOT EXISTS zip VARCHAR(20) DEFAULT NULL;

-- Notification settings (1 = enabled, 0 = disabled)
ALTER TABLE wp_ppv_users ADD COLUMN IF NOT EXISTS email_notifications TINYINT(1) DEFAULT 1;
ALTER TABLE wp_ppv_users ADD COLUMN IF NOT EXISTS push_notifications TINYINT(1) DEFAULT 1;
ALTER TABLE wp_ppv_users ADD COLUMN IF NOT EXISTS promo_notifications TINYINT(1) DEFAULT 1;

-- Privacy settings (1 = enabled, 0 = disabled)
ALTER TABLE wp_ppv_users ADD COLUMN IF NOT EXISTS profile_visible TINYINT(1) DEFAULT 1;
ALTER TABLE wp_ppv_users ADD COLUMN IF NOT EXISTS marketing_emails TINYINT(1) DEFAULT 1;
ALTER TABLE wp_ppv_users ADD COLUMN IF NOT EXISTS data_sharing TINYINT(1) DEFAULT 0;

-- Add index for birthday (useful for birthday bonus feature)
CREATE INDEX IF NOT EXISTS idx_ppv_users_birthday ON wp_ppv_users(birthday);
