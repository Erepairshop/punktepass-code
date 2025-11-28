-- Migration: WhatsApp Cloud API Integration
-- Author: PunktePass
-- Date: 2025-11-28
-- Description: Enables WhatsApp Business messaging for marketing and support

-- ============================================================
-- WHATSAPP CONFIG IN STORES TABLE
-- ============================================================
ALTER TABLE wp_ppv_stores
ADD COLUMN IF NOT EXISTS whatsapp_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable WhatsApp Cloud API integration',
ADD COLUMN IF NOT EXISTS whatsapp_phone_id VARCHAR(50) DEFAULT NULL COMMENT 'WhatsApp Business Phone Number ID',
ADD COLUMN IF NOT EXISTS whatsapp_business_id VARCHAR(50) DEFAULT NULL COMMENT 'WhatsApp Business Account ID',
ADD COLUMN IF NOT EXISTS whatsapp_access_token TEXT DEFAULT NULL COMMENT 'WhatsApp Cloud API Access Token (encrypted)',
ADD COLUMN IF NOT EXISTS whatsapp_verify_token VARCHAR(100) DEFAULT NULL COMMENT 'Webhook verification token',
ADD COLUMN IF NOT EXISTS whatsapp_marketing_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable marketing messages (birthday, comeback)',
ADD COLUMN IF NOT EXISTS whatsapp_support_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable support chat via WhatsApp';

-- ============================================================
-- WHATSAPP MESSAGE TEMPLATES
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_ppv_whatsapp_templates` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `template_name` varchar(100) NOT NULL COMMENT 'Meta template name',
  `template_type` enum('birthday', 'comeback', 'welcome', 'reward_reminder', 'points_update', 'support', 'custom') NOT NULL,
  `language` varchar(10) DEFAULT 'de' COMMENT 'Template language code',
  `status` enum('pending', 'approved', 'rejected') DEFAULT 'pending',
  `meta_template_id` varchar(100) DEFAULT NULL COMMENT 'Template ID from Meta',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `store_id` (`store_id`),
  KEY `template_type` (`template_type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WHATSAPP MESSAGE LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_ppv_whatsapp_messages` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'PunktePass user ID if linked',
  `phone_number` varchar(20) NOT NULL COMMENT 'Recipient phone number',
  `direction` enum('outbound', 'inbound') NOT NULL DEFAULT 'outbound',
  `message_type` enum('template', 'text', 'image', 'document', 'interactive') NOT NULL,
  `template_name` varchar(100) DEFAULT NULL COMMENT 'Template name if template message',
  `message_content` text DEFAULT NULL COMMENT 'Message content or template parameters',
  `wa_message_id` varchar(100) DEFAULT NULL COMMENT 'WhatsApp message ID',
  `status` enum('pending', 'sent', 'delivered', 'read', 'failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL COMMENT 'Error details if failed',
  `campaign_type` varchar(50) DEFAULT NULL COMMENT 'birthday, comeback, support, etc.',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `delivered_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `store_id` (`store_id`),
  KEY `user_id` (`user_id`),
  KEY `phone_number` (`phone_number`),
  KEY `status` (`status`),
  KEY `campaign_type` (`campaign_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WHATSAPP CONVERSATIONS (for support)
-- ============================================================
CREATE TABLE IF NOT EXISTS `wp_ppv_whatsapp_conversations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `status` enum('active', 'resolved', 'pending') DEFAULT 'active',
  `last_message_at` datetime DEFAULT NULL,
  `last_message_preview` varchar(255) DEFAULT NULL,
  `unread_count` int(11) DEFAULT 0,
  `assigned_to` varchar(100) DEFAULT NULL COMMENT 'Staff member handling this',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_phone` (`store_id`, `phone_number`),
  KEY `status` (`status`),
  KEY `last_message_at` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USER PHONE NUMBERS (for marketing consent)
-- ============================================================
ALTER TABLE wp_ppv_users
ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) DEFAULT NULL COMMENT 'User phone number for WhatsApp',
ADD COLUMN IF NOT EXISTS whatsapp_consent TINYINT(1) DEFAULT 0 COMMENT 'User consented to WhatsApp messages',
ADD COLUMN IF NOT EXISTS whatsapp_consent_at DATETIME DEFAULT NULL COMMENT 'When consent was given';

-- ============================================================
-- INDEXES
-- ============================================================
ALTER TABLE wp_ppv_stores
ADD INDEX IF NOT EXISTS idx_whatsapp_enabled (whatsapp_enabled);

ALTER TABLE wp_ppv_users
ADD INDEX IF NOT EXISTS idx_phone_number (phone_number),
ADD INDEX IF NOT EXISTS idx_whatsapp_consent (whatsapp_consent);
