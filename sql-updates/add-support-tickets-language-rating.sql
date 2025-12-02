-- Add language and rating columns to support_tickets table
-- Allows tracking user language preference and storing ratings

-- Add language column for user's language preference
ALTER TABLE `wp_ppv_support_tickets`
ADD COLUMN IF NOT EXISTS `language` VARCHAR(5) DEFAULT 'de' AFTER `user_type`;

-- Add rating column for storing numeric rating (1-5)
ALTER TABLE `wp_ppv_support_tickets`
ADD COLUMN IF NOT EXISTS `rating` TINYINT(1) UNSIGNED DEFAULT NULL AFTER `language`;

-- Add device_info column for better debugging
ALTER TABLE `wp_ppv_support_tickets`
ADD COLUMN IF NOT EXISTS `device_info` VARCHAR(255) DEFAULT NULL AFTER `page_url`;

-- Add admin_reply column for storing admin responses
ALTER TABLE `wp_ppv_support_tickets`
ADD COLUMN IF NOT EXISTS `admin_reply` TEXT DEFAULT NULL AFTER `admin_notes`;

-- Add reply_sent_at column to track when reply was sent
ALTER TABLE `wp_ppv_support_tickets`
ADD COLUMN IF NOT EXISTS `reply_sent_at` DATETIME DEFAULT NULL AFTER `admin_reply`;

-- Add index for language filtering
CREATE INDEX IF NOT EXISTS `language` ON `wp_ppv_support_tickets` (`language`);

-- Add index for rating filtering
CREATE INDEX IF NOT EXISTS `rating` ON `wp_ppv_support_tickets` (`rating`);
