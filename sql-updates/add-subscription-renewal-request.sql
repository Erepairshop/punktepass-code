-- Add subscription renewal request tracking to wp_ppv_stores
-- This column stores when a handler requested subscription renewal

ALTER TABLE `wp_ppv_stores`
ADD COLUMN `subscription_renewal_requested` DATETIME NULL DEFAULT NULL
AFTER `subscription_expires_at`,
ADD COLUMN `renewal_phone` VARCHAR(50) NULL DEFAULT NULL
AFTER `subscription_renewal_requested`;
