-- Add onboarding tracking columns to ppv_stores table
-- Run this SQL on your database to enable onboarding system
-- These columns track onboarding progress for handlers/stores

ALTER TABLE wp_ppv_stores
ADD COLUMN IF NOT EXISTS onboarding_completed TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS onboarding_dismissed TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS onboarding_sticky_hidden TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS onboarding_welcome_shown TINYINT(1) DEFAULT 0;
