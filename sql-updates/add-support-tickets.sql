-- Create support tickets table for handler support requests
-- Allows handlers to quickly report errors and problems
-- Admin can manage tickets from PunktePass admin panel

CREATE TABLE IF NOT EXISTS `wp_ppv_support_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) UNSIGNED NOT NULL,
  `handler_email` varchar(150) NOT NULL,
  `handler_phone` varchar(50) DEFAULT NULL,
  `store_name` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `priority` enum('low','normal','urgent') DEFAULT 'normal',
  `status` enum('new','in_progress','resolved') DEFAULT 'new',
  `contact_preference` enum('email','phone','whatsapp') DEFAULT 'email',
  `page_url` varchar(500) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `store_id` (`store_id`),
  KEY `status` (`status`),
  KEY `priority` (`priority`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
