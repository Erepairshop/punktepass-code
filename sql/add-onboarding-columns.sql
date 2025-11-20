-- Add onboarding tracking columns to ppv_users table
-- Run this SQL on your database to enable onboarding system

ALTER TABLE wp_ppv_users
ADD COLUMN IF NOT EXISTS onboarding_completed TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS onboarding_dismissed TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS onboarding_sticky_hidden TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS onboarding_welcome_shown TINYINT(1) DEFAULT 0;
