-- Migration: Add Google Review Request tracking table
-- Author: PunktePass / Claude
-- Date: 2025-11-27
-- Description: Tracks when Google review requests are sent to users

-- ============================================================
-- GOOGLE REVIEW REQUEST TRACKING TABLE
-- ============================================================
-- This table tracks when review requests are sent to prevent
-- duplicate requests and enforce frequency limits (once/monthly/quarterly)

CREATE TABLE IF NOT EXISTS wp_ppv_google_review_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL COMMENT 'Store that sent the request',
    user_id INT NOT NULL COMMENT 'User who received the request',
    last_request_at DATETIME NOT NULL COMMENT 'When the last request was sent',
    request_count INT DEFAULT 1 COMMENT 'Total number of requests sent to this user for this store',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation time',

    -- Unique constraint to prevent duplicate records
    UNIQUE KEY unique_store_user (store_id, user_id),

    -- Indexes for efficient querying
    INDEX idx_store_id (store_id),
    INDEX idx_user_id (user_id),
    INDEX idx_last_request_at (last_request_at),

    -- Foreign keys (commented out for compatibility with different setups)
    -- FOREIGN KEY (store_id) REFERENCES wp_ppv_stores(id) ON DELETE CASCADE,
    -- FOREIGN KEY (user_id) REFERENCES wp_ppv_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
