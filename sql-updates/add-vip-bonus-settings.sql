-- Migration: Add VIP bonus settings to stores table
-- Author: PunktePass / Claude
-- Date: 2025-11-22
-- Description: Enables stores to configure bonus points for different user levels

-- Add VIP bonus columns to ppv_stores table
ALTER TABLE wp_ppv_stores
ADD COLUMN IF NOT EXISTS vip_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable VIP bonus points for this store',
ADD COLUMN IF NOT EXISTS vip_silver_bonus INT DEFAULT 5 COMMENT 'Extra points % for Silver users',
ADD COLUMN IF NOT EXISTS vip_gold_bonus INT DEFAULT 10 COMMENT 'Extra points % for Gold users',
ADD COLUMN IF NOT EXISTS vip_platinum_bonus INT DEFAULT 20 COMMENT 'Extra points % for Platinum users';

-- Create index for faster VIP queries
ALTER TABLE wp_ppv_stores
ADD INDEX IF NOT EXISTS idx_vip_enabled (vip_enabled);
