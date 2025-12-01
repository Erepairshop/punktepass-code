-- Add subscription renewal history tracking to wp_ppv_stores
-- This allows viewing completed/processed renewal requests

ALTER TABLE `wp_ppv_stores`
ADD COLUMN IF NOT EXISTS `subscription_renewal_processed_at` DATETIME NULL DEFAULT NULL
AFTER `renewal_phone`;

-- Index for filtering processed renewals
CREATE INDEX IF NOT EXISTS `subscription_renewal_processed_at` ON `wp_ppv_stores` (`subscription_renewal_processed_at`);
