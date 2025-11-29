-- ============================================================
-- PunktePass Points Table - Add Device/GPS Tracking Fields
-- Version: 1.0
-- Date: 2024-11-29
--
-- Purpose: Add device fingerprint, IP address, GPS coordinates,
-- and scanner ID to ppv_points table for audit and fraud detection
-- ============================================================

-- 1. Add device fingerprint (SHA256 hash of scanner device)
ALTER TABLE wp_ppv_points
ADD COLUMN IF NOT EXISTS device_fingerprint VARCHAR(64) NULL
COMMENT 'SHA256 hash of scanner device fingerprint' AFTER type;

-- 2. Add IP address (supports IPv4 and IPv6)
ALTER TABLE wp_ppv_points
ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL
COMMENT 'IP address of scan request' AFTER device_fingerprint;

-- 3. Add GPS latitude
ALTER TABLE wp_ppv_points
ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL
COMMENT 'GPS latitude of scan location' AFTER ip_address;

-- 4. Add GPS longitude
ALTER TABLE wp_ppv_points
ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL
COMMENT 'GPS longitude of scan location' AFTER latitude;

-- 5. Add scanner_id (employee who performed the scan)
ALTER TABLE wp_ppv_points
ADD COLUMN IF NOT EXISTS scanner_id BIGINT UNSIGNED NULL
COMMENT 'User ID of employee who scanned (from ppv_users)' AFTER longitude;

-- ============================================================
-- INDEXES for fraud detection queries
-- ============================================================

-- Index for finding scans by device
ALTER TABLE wp_ppv_points
ADD INDEX IF NOT EXISTS idx_device_fingerprint (device_fingerprint);

-- Index for finding scans by IP
ALTER TABLE wp_ppv_points
ADD INDEX IF NOT EXISTS idx_ip_address (ip_address);

-- Index for GPS-based fraud queries (scans in suspicious locations)
ALTER TABLE wp_ppv_points
ADD INDEX IF NOT EXISTS idx_gps_location (latitude, longitude);

-- Index for scanner accountability
ALTER TABLE wp_ppv_points
ADD INDEX IF NOT EXISTS idx_scanner_id (scanner_id);

-- Composite index for device + store fraud detection
ALTER TABLE wp_ppv_points
ADD INDEX IF NOT EXISTS idx_device_store (device_fingerprint, store_id, created DESC);

-- ============================================================
-- NOTE: After running this migration, update the PHP code to
-- populate these fields in:
-- - includes/traits/trait-ppv-qr-rest.php (REST API)
-- - includes/class-ppv-scan.php (AJAX handler)
-- - includes/api/ppv-pos-api.php (POS API)
-- ============================================================
