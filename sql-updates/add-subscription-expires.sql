-- Add subscription_expires_at column to wp_ppv_stores
-- This column stores the expiration date for active subscriptions (e.g., 6 months)
-- Different from trial_ends_at which is only for trial period

ALTER TABLE `wp_ppv_stores`
ADD COLUMN `subscription_expires_at` DATETIME NULL DEFAULT NULL
AFTER `subscription_status`;

-- Optional: Set initial expiration dates for existing active subscriptions
-- Uncomment and modify as needed:
-- UPDATE `wp_ppv_stores`
-- SET `subscription_expires_at` = DATE_ADD(NOW(), INTERVAL 6 MONTH)
-- WHERE `subscription_status` = 'active' AND `subscription_expires_at` IS NULL;
