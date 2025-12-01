-- Add category and user tracking columns to support_tickets table
-- Allows better filtering and tracking of feedback types

-- Add category column for feedback type
ALTER TABLE `wp_ppv_support_tickets`
ADD COLUMN IF NOT EXISTS `category` ENUM('support','bug','feature','question','rating') DEFAULT 'support' AFTER `id`;

-- Add user_id column for linking to users (0 = handler/store only)
ALTER TABLE `wp_ppv_support_tickets`
ADD COLUMN IF NOT EXISTS `user_id` bigint(20) UNSIGNED DEFAULT 0 AFTER `store_id`;

-- Add user_type column to distinguish handler from user
ALTER TABLE `wp_ppv_support_tickets`
ADD COLUMN IF NOT EXISTS `user_type` ENUM('handler','user') DEFAULT 'handler' AFTER `user_id`;

-- Add index for category filtering
CREATE INDEX IF NOT EXISTS `category` ON `wp_ppv_support_tickets` (`category`);

-- Add index for user_id
CREATE INDEX IF NOT EXISTS `user_id` ON `wp_ppv_support_tickets` (`user_id`);
