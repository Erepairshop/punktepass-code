-- Add payment method columns to wp_ppv_stores table
-- For PayPal and Bank Transfer subscription support

-- Payment method (paypal, bank_transfer, stripe)
ALTER TABLE `wp_ppv_stores`
ADD COLUMN IF NOT EXISTS `payment_method` VARCHAR(50) NULL DEFAULT NULL AFTER `subscription_status`;

-- PayPal subscription ID
ALTER TABLE `wp_ppv_stores`
ADD COLUMN IF NOT EXISTS `paypal_subscription_id` VARCHAR(100) NULL DEFAULT NULL AFTER `payment_method`;

-- Bank transfer reference number
ALTER TABLE `wp_ppv_stores`
ADD COLUMN IF NOT EXISTS `bank_transfer_reference` VARCHAR(50) NULL DEFAULT NULL AFTER `paypal_subscription_id`;

-- Bank transfer request timestamp
ALTER TABLE `wp_ppv_stores`
ADD COLUMN IF NOT EXISTS `bank_transfer_requested_at` DATETIME NULL DEFAULT NULL AFTER `bank_transfer_reference`;

-- Bank transfer confirmation timestamp
ALTER TABLE `wp_ppv_stores`
ADD COLUMN IF NOT EXISTS `bank_transfer_confirmed_at` DATETIME NULL DEFAULT NULL AFTER `bank_transfer_requested_at`;

-- Index for PayPal subscription lookups
CREATE INDEX IF NOT EXISTS `idx_paypal_subscription` ON `wp_ppv_stores` (`paypal_subscription_id`);

-- Index for payment method filtering
CREATE INDEX IF NOT EXISTS `idx_payment_method` ON `wp_ppv_stores` (`payment_method`);
