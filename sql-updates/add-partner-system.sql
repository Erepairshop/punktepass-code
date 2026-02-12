-- PunktePass Partner System
-- Partners (wholesalers/distributors) can refer repair shops
-- Track referrals, display partner branding, co-branded PDFs

CREATE TABLE IF NOT EXISTS `wp_ppv_partners` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `partner_code` varchar(20) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `contact_name` varchar(255) NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) NULL,
  `website` varchar(255) NULL,
  `logo_url` varchar(500) NULL,
  `address` varchar(255) NULL,
  `plz` varchar(20) NULL,
  `city` varchar(100) NULL,
  `country` varchar(5) DEFAULT 'DE',
  `partnership_model` enum('newsletter','package_insert','co_branded') DEFAULT 'package_insert',
  `commission_rate` decimal(5,2) DEFAULT 0,
  `status` enum('active','inactive','pending') DEFAULT 'pending',
  `notes` text NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `partner_code` (`partner_code`),
  KEY `email` (`email`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
