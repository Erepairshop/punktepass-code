-- Fix old handlers missing trial status and trial_ends_at
-- This updates handlers who were created before the fixes

-- Update handlers with missing subscription_status
UPDATE `wp_ppv_stores`
SET `subscription_status` = 'trial'
WHERE `subscription_status` IS NULL OR `subscription_status` = '';

-- Update handlers with missing trial_ends_at (set to already expired - 30 days ago)
UPDATE `wp_ppv_stores`
SET `trial_ends_at` = DATE_SUB(NOW(), INTERVAL 30 DAY)
WHERE `trial_ends_at` IS NULL
  AND `subscription_status` = 'trial'
  AND `subscription_expires_at` IS NULL;

-- Check results
SELECT
    id,
    name,
    company_name,
    email,
    subscription_status,
    trial_ends_at,
    subscription_expires_at,
    created_at
FROM `wp_ppv_stores`
ORDER BY id DESC
LIMIT 20;
