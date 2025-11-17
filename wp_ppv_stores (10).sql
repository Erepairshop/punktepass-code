-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Erstellungszeit: 17. Nov 2025 um 16:14
-- Server-Version: 11.8.3-MariaDB-log
-- PHP-Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `u660905446_RtkYR`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `wp_ppv_stores`
--

CREATE TABLE `wp_ppv_stores` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `qr_secret` varchar(64) DEFAULT NULL,
  `design_color` varchar(10) DEFAULT NULL,
  `design_logo` varchar(255) DEFAULT NULL,
  `trial_ends_at` datetime DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slogan` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `plz` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `subscription_status` varchar(50) DEFAULT 'trial',
  `website` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `categories` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `gallery` longtext DEFAULT NULL,
  `opening_hours` longtext DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `tiktok` varchar(255) DEFAULT NULL,
  `whatsapp` varchar(50) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `visible` tinyint(1) DEFAULT 1,
  `zeiten` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `store_key` varchar(64) DEFAULT NULL,
  `pos_token` varchar(64) DEFAULT NULL,
  `store_slug` varchar(255) DEFAULT '',
  `qr_color` varchar(20) DEFAULT '#000000',
  `qr_bg` varchar(20) DEFAULT '#ffffff',
  `qr_logo` varchar(255) DEFAULT NULL,
  `pos_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `pos_pin` varchar(8) DEFAULT NULL,
  `pos_api_key` varchar(64) DEFAULT NULL,
  `maintenance_mode` tinyint(1) DEFAULT 0,
  `maintenance_message` longtext DEFAULT NULL,
  `timezone` varchar(50) DEFAULT 'Europe/Berlin',
  `draft_data` longtext DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL COMMENT 'Adószám / UstIdNr / CIF',
  `is_taxable` tinyint(1) DEFAULT 1 COMMENT '1 = Áfás, 0 = Nem áfás'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `wp_ppv_stores`
--

INSERT INTO `wp_ppv_stores` (`id`, `user_id`, `qr_secret`, `design_color`, `design_logo`, `trial_ends_at`, `name`, `slogan`, `category`, `address`, `plz`, `city`, `country`, `phone`, `email`, `password`, `subscription_status`, `website`, `description`, `company_name`, `contact_person`, `categories`, `logo`, `cover`, `gallery`, `opening_hours`, `facebook`, `instagram`, `tiktok`, `whatsapp`, `latitude`, `longitude`, `active`, `visible`, `zeiten`, `created_at`, `updated_at`, `last_updated`, `store_key`, `pos_token`, `store_slug`, `qr_color`, `qr_bg`, `qr_logo`, `pos_enabled`, `pos_pin`, `pos_api_key`, `maintenance_mode`, `maintenance_message`, `timezone`, `draft_data`, `tax_id`, `is_taxable`) VALUES
(4, 9, 'cNf4jwtlN4Bc2md1oj5FxpON4Q8oH9WN', NULL, NULL, NULL, 'testfirma', NULL, NULL, 'Siedlungsring, 51', '89415', 'Lauingen a.d. Donau', 'DE', '017698479520', 'borota25@gmail.come', '$2y$10$q/0X7lAGDVsQ6b9uzvtTyeUDzqA0KqPW4cW1Y/tG/5N.hEXw1ZZfu', 'active', NULL, NULL, 'testfirma', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, '2025-10-07 16:54:18', '2025-11-03 14:26:46', '2025-11-03 14:26:46', 'yv8D8jdSajCdq3saB2wLy1XQ5u2W1q3f', 'f38a7fea972cdd138b328bff61cffd35', '', '#000000', '#ffffff', NULL, 1, '1234', '7b6e6808a91011f0bca9a33a376863b7', 0, NULL, 'Europe/Berlin', NULL, NULL, 1),
(8, 8, 'R2BSO0T3lKyZPy4QcymL3d9iU0d6thH1', NULL, NULL, NULL, 'bolt3', NULL, NULL, 'Siedlungsring, 51', '89415', 'Lauingen a.d. Donau', 'DE', '017698479520', 'info@punktepass.de', '$2y$10$sYuESM5gsuML.SH4rMGjjOhMZCqFmR9s9iDw364uBfHzdRQG7cuQa', 'active', NULL, NULL, 'bolt3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, '2025-10-11 08:53:53', '2025-11-03 14:26:46', '2025-11-03 14:26:46', 'k50j0VsbfA0tKNy1z7tG4SWrgx34oDNj', '66530ae6651fd4c0961ae8007c12e704', '', '#000000', '#ffffff', NULL, 1, '1234', '7b6e68bba91011f0bca9a33a376863b7', 0, NULL, 'Europe/Berlin', NULL, NULL, 1),
(9, 9, 'g5cKBVJNoZTvZ254EDMx4S1baiLWgSSC', NULL, NULL, NULL, 'boltos', 'erer', '', 'Siedlungsring, 51', '89415', 'Lauingen', 'DE', '017698479520', 'borota@gmail.com', '$2y$10$jbwYnefukKaZaC38BFNnaekA1Wc6TNIYgqFMbfuM1851rgutXFi4.', 'active', 'https://erepairshop.de', '', 'boltos', NULL, NULL, '', NULL, '', '{\"mo\":{\"von\":\"10:00\",\"bis\":\"18:00\",\"closed\":0},\"di\":{\"von\":\"10:00\",\"bis\":\"18:00\",\"closed\":0},\"mi\":{\"von\":\"10:00\",\"bis\":\"18:00\",\"closed\":0},\"do\":{\"von\":\"10:00\",\"bis\":\"18:00\",\"closed\":0},\"fr\":{\"von\":\"10:00\",\"bis\":\"18:00\",\"closed\":0},\"sa\":{\"von\":\"10:00\",\"bis\":\"16:00\",\"closed\":0},\"so\":{\"von\":\"\",\"bis\":\"\",\"closed\":1}}', '', '', '', '017698479520', 48.58410000, 10.43040000, 1, 1, NULL, '2025-10-11 12:00:41', '2025-11-14 12:15:29', '2025-11-14 11:15:29', '5hwJGIroEqyjQfJhJRByjo7fkmhNyYV4', 'd9da97084f8a5bb968b724cc5b885512', '', '#000000', '#ffffff', NULL, 1, '1234', '7b6e6938a91011f0bca9a33a376863b7', 0, '                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ', 'Europe/Berlin', '[]', 'DE123456', 1),
(10, 10, 'IDIkIlCXNYCL3Ej6hwwUlvl05HAKpR8M', NULL, NULL, NULL, 'boltosuj', NULL, NULL, 'Siedlungsring, 51', '89415', 'Lauingen a.d. Donau', 'DE', '017698479520', 'borota2@gmail.com', '$2y$10$8FiYnU1WFf6WxHeKidc5XuI9Zx070xbZOeCSLfz6Pakqc4HixUEBy', 'active', NULL, NULL, 'boltosuj', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, '2025-10-11 12:33:37', '2025-11-03 14:26:46', '2025-11-03 14:26:46', 'ewmdg9XpdJa8fUUfOnAfFYu0rZ1ZrEbD', '91d1d931a27479d230fac74fceab26e6', '', '#000000', '#ffffff', NULL, 1, '1234', '7b6e69b1a91011f0bca9a33a376863b7', 0, NULL, 'Europe/Berlin', NULL, NULL, 1);

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `wp_ppv_stores`
--
ALTER TABLE `wp_ppv_stores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `store_key` (`store_key`),
  ADD UNIQUE KEY `uniq_pos_api_key` (`pos_api_key`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `wp_ppv_stores`
--
ALTER TABLE `wp_ppv_stores`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
