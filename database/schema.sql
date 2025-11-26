-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 26, 2025 at 12:00 PM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `core1`
--

-- --------------------------------------------------------

--
-- Table structure for table `2fa_bypass_records`
--

DROP TABLE IF EXISTS `2fa_bypass_records`;
CREATE TABLE IF NOT EXISTS `2fa_bypass_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `device_fingerprint` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_device_user` (`user_id`,`device_fingerprint`),
  KEY `user_id_idx` (`user_id`),
  KEY `device_fingerprint_idx` (`device_fingerprint`),
  KEY `expires_at_idx` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `2fa_bypass_records`
--

INSERT INTO `2fa_bypass_records` (`id`, `user_id`, `device_fingerprint`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-22 08:30:59', '2025-11-22 08:30:59', '2025-11-22 08:30:59'),
(2, 2, '055b39690c55744270c2b3a43ef8e5941064c6eeb0ee42c464aa8c9e6ecb2386', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 08:26:45', '2025-11-26 08:26:45', '2025-11-26 08:26:45'),
(3, 3, '54a0c9dc6a657627a316d0ed72de8c087a0a7ec2f0566cb691d8dc1bce7ba01c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 08:47:24', '2025-11-26 08:47:24', '2025-11-26 08:47:24'),
(4, 5, '055b39690c55744270c2b3a43ef8e5941064c6eeb0ee42c464aa8c9e6ecb2386', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-26 08:56:38', '2025-11-26 08:56:38', '2025-11-26 08:56:38');

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity_type` (`entity_type`),
  KEY `idx_entity_id` (`entity_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=MyISAM AUTO_INCREMENT=275 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(270, 3, 'login', 'user', 3, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:39:57'),
(271, 3, 'login', 'user', 3, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:42:34'),
(272, 3, 'logout', 'user', 3, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:42:44'),
(273, 1, 'login', 'user', 1, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:43:11'),
(274, 1, 'users_query_debug', 'debug', NULL, '{\"total_users\":5,\"role_counts\":[{\"role\":\"admin\",\"count\":1},{\"role\":\"inventory_manager\",\"count\":1},{\"role\":\"purchasing_officer\",\"count\":1},{\"role\":\"supplier\",\"count\":1},{\"role\":\"staff\",\"count\":1}],\"query\":\"SELECT\\n                    u.id,\\n                    u.username,\\n                    u.full_name,\\n                    u.email,\\n                    u.role,\\n                    u.is_active,\\n                    u.last_login,\\n                    u.created_at\\n                  FROM users u\\n                  WHERE\\n                    -- EXCLUDE ALL SUPPLIERS (both pending and approved)\\n                    u.role != \'supplier\'\\n                  ORDER BY\\n                    u.created_at DESC\",\"results_count\":4,\"user_role\":\"admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:43:16'),
(269, 1, 'users_query_debug', 'debug', NULL, '{\"total_users\":5,\"role_counts\":[{\"role\":\"admin\",\"count\":1},{\"role\":\"inventory_manager\",\"count\":1},{\"role\":\"purchasing_officer\",\"count\":1},{\"role\":\"supplier\",\"count\":1},{\"role\":\"staff\",\"count\":1}],\"query\":\"SELECT\\n                    u.id,\\n                    u.username,\\n                    u.full_name,\\n                    u.email,\\n                    u.role,\\n                    u.is_active,\\n                    u.last_login,\\n                    u.created_at\\n                  FROM users u\\n                  WHERE\\n                    -- EXCLUDE ALL SUPPLIERS (both pending and approved)\\n                    u.role != \'supplier\'\\n                  ORDER BY\\n                    u.created_at DESC\",\"results_count\":4,\"user_role\":\"admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:38:45'),
(261, 1, 'login', 'user', 1, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:29:17'),
(262, 1, 'login', 'user', 1, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:31:39'),
(263, 1, 'login', 'user', 1, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:34:33'),
(264, 1, 'login', 'user', 1, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:35:39'),
(265, 3, 'login', 'user', 3, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:35:47'),
(266, 5, 'login', 'user', 5, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 11:35:51'),
(267, 2, 'login', 'user', 2, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:36:11'),
(268, 1, 'login', 'user', 1, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:38:40'),
(256, 1, 'login', 'user', 1, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 10:53:31'),
(257, 1, 'login', 'user', 1, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 10:57:25'),
(258, 1, 'logout', 'user', 1, '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 10:57:50'),
(259, 1, 'login', 'user', 1, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 10:59:15'),
(260, 1, 'login', 'user', 1, '{\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:25:44'),
(255, 1, 'users_query_debug', 'debug', NULL, '{\"total_users\":5,\"role_counts\":[{\"role\":\"admin\",\"count\":1},{\"role\":\"inventory_manager\",\"count\":1},{\"role\":\"purchasing_officer\",\"count\":1},{\"role\":\"supplier\",\"count\":1},{\"role\":\"staff\",\"count\":1}],\"query\":\"SELECT\\n                    u.id,\\n                    u.username,\\n                    u.full_name,\\n                    u.email,\\n                    u.role,\\n                    u.is_active,\\n                    u.last_login,\\n                    u.created_at\\n                  FROM users u\\n                  WHERE\\n                    -- EXCLUDE ALL SUPPLIERS (both pending and approved)\\n                    u.role != \'supplier\'\\n                  ORDER BY\\n                    u.created_at DESC\",\"results_count\":4,\"user_role\":\"admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 10:52:58');

-- --------------------------------------------------------

--
-- Table structure for table `analytics_events`
--

DROP TABLE IF EXISTS `analytics_events`;
CREATE TABLE IF NOT EXISTS `analytics_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `page_url` varchar(500) DEFAULT NULL,
  `referrer_url` varchar(500) DEFAULT NULL,
  `event_data` json DEFAULT NULL,
  `duration` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_page_url` (`page_url`(250)),
  KEY `idx_created_at` (`created_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED DEFAULT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of entity (user, product, order, etc.)',
  `entity_id` int UNSIGNED DEFAULT NULL COMMENT 'ID of the affected entity',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` json DEFAULT NULL COMMENT 'Previous values before change',
  `new_values` json DEFAULT NULL COMMENT 'New values after change',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=133 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `action`, `entity_type`, `entity_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(116, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 10:53:31'),
(117, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 10:57:25'),
(118, 1, 'admin', 'logout', 'user', 1, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 10:57:50'),
(119, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 10:59:15'),
(120, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:25:44'),
(121, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:29:17'),
(122, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:31:39'),
(123, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:34:33'),
(124, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:35:39'),
(125, 3, 'kevinc', 'login', 'user', 3, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:35:47'),
(126, 5, 'alyssaf', 'login', 'user', 5, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 11:35:51'),
(127, 2, 'karldc', 'login', 'user', 2, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:36:11'),
(128, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:38:40'),
(129, 3, 'kevinc', 'login', 'user', 3, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:39:57'),
(130, 3, 'kevinc', 'login', 'user', 3, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:42:34'),
(131, 3, 'kevinc', 'logout', 'user', 3, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:42:44'),
(132, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 11:43:11');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `icon` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Processors', 'processors', 'CPU processors for desktop and server systems', NULL, 1, 1, '2025-11-12 07:03:43', '2025-11-12 07:03:43'),
(2, 'Motherboards', 'motherboards', 'Main system boards for PC builds', NULL, 1, 2, '2025-11-12 07:03:43', '2025-11-12 07:03:43'),
(3, 'Memory', 'memory', 'RAM and memory modules', NULL, 1, 3, '2025-11-12 07:03:43', '2025-11-12 07:03:43'),
(4, 'Graphics Cards', 'graphics-cards', 'GPU and video cards', NULL, 1, 4, '2025-11-12 07:03:43', '2025-11-12 07:03:43'),
(5, 'Storage', 'storage', 'Hard drives, SSDs, and storage devices', NULL, 1, 5, '2025-11-12 07:03:43', '2025-11-12 07:03:43'),
(6, 'Power Supplies', 'power-supplies', 'PSU units for system power', NULL, 1, 6, '2025-11-12 07:03:43', '2025-11-12 07:03:43'),
(7, 'Cases', 'cases', 'PC cases and chassis', NULL, 1, 7, '2025-11-12 07:03:43', '2025-11-12 07:03:43'),
(8, 'Cooling', 'cooling', 'CPU coolers, fans, and cooling systems', NULL, 1, 8, '2025-11-12 07:03:43', '2025-11-12 07:03:43'),
(9, 'Accessories', 'accessories', 'Cables, adapters, and other accessories', 'fas fa-box', 1, 9, '2025-11-12 07:03:43', '2025-11-26 08:16:12');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_email_verified` (`email_verified`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `date_of_birth`, `gender`, `email_verification_token`, `email_verified`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'jereckopaul90@gmail.com', '$2y$10$PdcX03QEsd3ZleF61pFNde5fHCFbkdhGipZNwHq.muYFgPh8/vttm', 'Jerecko Paul', 'Catalan', '09999922933', NULL, NULL, NULL, 1, '2025-11-26 16:18:05', '2025-11-26 07:11:28', '2025-11-26 08:18:05');

-- --------------------------------------------------------

--
-- Table structure for table `customer_addresses`
--

DROP TABLE IF EXISTS `customer_addresses`;
CREATE TABLE IF NOT EXISTS `customer_addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `address_type` enum('shipping','billing') NOT NULL DEFAULT 'shipping',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `address_line_1` varchar(255) NOT NULL,
  `address_line_2` varchar(255) DEFAULT NULL,
  `city` varchar(255) NOT NULL,
  `state` varchar(255) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_address_type` (`address_type`),
  KEY `idx_is_default` (`is_default`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `customer_addresses`
--

INSERT INTO `customer_addresses` (`id`, `customer_id`, `address_type`, `is_default`, `first_name`, `last_name`, `company`, `address_line_1`, `address_line_2`, `city`, `state`, `postal_code`, `country`, `phone`, `created_at`, `updated_at`) VALUES
(1, 1, 'shipping', 1, 'Jerecko', 'Catalan', '', '123', '123', '123', '123', '123', 'PH', '123123123', '2025-11-26 07:24:39', '2025-11-26 07:24:39'),
(2, 1, 'billing', 1, 'Jerecko', 'Catalan', '', '123', '123', '123', '123', '123', 'PH', '123123123', '2025-11-26 07:24:39', '2025-11-26 07:24:39');

-- --------------------------------------------------------

--
-- Table structure for table `customer_orders`
--

DROP TABLE IF EXISTS `customer_orders`;
CREATE TABLE IF NOT EXISTS `customer_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int NOT NULL,
  `status` enum('pending','confirmed','processing','shipped','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  `order_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `shipping_address_id` int DEFAULT NULL,
  `billing_address_id` int DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `shipping_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `shipping_method` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `shipping_address_id` (`shipping_address_id`),
  KEY `billing_address_id` (`billing_address_id`),
  KEY `idx_order_number` (`order_number`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_order_date` (`order_date`),
  KEY `idx_payment_status` (`payment_status`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `customer_orders`
--

INSERT INTO `customer_orders` (`id`, `order_number`, `customer_id`, `status`, `order_date`, `shipping_address_id`, `billing_address_id`, `subtotal`, `tax_amount`, `shipping_amount`, `discount_amount`, `total_amount`, `payment_method`, `payment_status`, `shipping_method`, `tracking_number`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'ORD-2025-000001', 1, 'cancelled', '2025-11-26 07:24:39', 1, 2, 447840.64, 35827.25, 0.00, 0.00, 483667.89, 'cash_on_delivery', 'paid', NULL, NULL, '', '2025-11-26 07:24:39', '2025-11-26 07:25:46');

-- --------------------------------------------------------

--
-- Table structure for table `customer_order_items`
--

DROP TABLE IF EXISTS `customer_order_items`;
CREATE TABLE IF NOT EXISTS `customer_order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_sku` varchar(100) NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `unit_price`)) STORED,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `customer_order_items`
--

INSERT INTO `customer_order_items` (`id`, `order_id`, `product_id`, `product_name`, `product_sku`, `quantity`, `unit_price`, `created_at`) VALUES
(1, 1, 25, 'TEST', 'TEST-TEST-TEST', 2, 122222.00, '2025-11-26 07:24:39'),
(2, 1, 1, 'AMD Ryzen 9 7950X 16-Core Processor', 'AMD-RYZEN9-7950X', 6, 33899.44, '2025-11-26 07:24:39');

-- --------------------------------------------------------

--
-- Table structure for table `customer_returns`
--

DROP TABLE IF EXISTS `customer_returns`;
CREATE TABLE IF NOT EXISTS `customer_returns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `return_number` varchar(50) NOT NULL,
  `order_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `return_reason` text NOT NULL,
  `return_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('requested','approved','received','processed','rejected') NOT NULL DEFAULT 'requested',
  `refund_method` varchar(50) DEFAULT NULL,
  `refund_status` enum('pending','processed','failed') NOT NULL DEFAULT 'pending',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_number` (`return_number`),
  KEY `idx_return_number` (`return_number`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_return_items`
--

DROP TABLE IF EXISTS `customer_return_items`;
CREATE TABLE IF NOT EXISTS `customer_return_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `return_id` int NOT NULL,
  `product_id` int NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_sku` varchar(100) NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `return_reason` varchar(255) DEFAULT NULL,
  `condition_status` enum('new','opened','used','damaged') NOT NULL DEFAULT 'new',
  `refund_amount` decimal(10,2) GENERATED ALWAYS AS ((`quantity` * `unit_price`)) STORED,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_return_id` (`return_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_wishlists`
--

DROP TABLE IF EXISTS `customer_wishlists`;
CREATE TABLE IF NOT EXISTS `customer_wishlists` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `product_id` int NOT NULL,
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_customer_product` (`customer_id`,`product_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `goods_received_notes`
--

DROP TABLE IF EXISTS `goods_received_notes`;
CREATE TABLE IF NOT EXISTS `goods_received_notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grn_number` varchar(50) NOT NULL,
  `po_id` int NOT NULL,
  `received_by` int NOT NULL,
  `received_date` date NOT NULL,
  `inspection_status` enum('pending','passed','failed','partial') NOT NULL DEFAULT 'pending',
  `total_items_received` int NOT NULL DEFAULT '0',
  `total_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grn_number` (`grn_number`),
  KEY `idx_grn_number` (`grn_number`),
  KEY `idx_po_id` (`po_id`),
  KEY `idx_received_by` (`received_by`),
  KEY `idx_inspection_status` (`inspection_status`),
  KEY `idx_received_date` (`received_date`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `goods_received_notes`
--

INSERT INTO `goods_received_notes` (`id`, `grn_number`, `po_id`, `received_by`, `received_date`, `inspection_status`, `total_items_received`, `total_value`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'GRN-2025-00001', 6, 1, '2025-11-26', 'passed', 0, 0.00, 'yessir', '2025-11-26 02:34:32', '2025-11-26 02:34:32'),
(2, 'GRN-2025-00002', 4, 1, '2025-11-26', 'passed', 0, 0.00, '', '2025-11-26 02:34:52', '2025-11-26 02:34:52'),
(3, 'GRN-2025-00003', 1, 1, '2025-11-26', 'partial', 0, 0.00, '', '2025-11-26 02:35:08', '2025-11-26 02:35:08'),
(5, 'GRN-2025-00004', 5, 1, '2025-11-26', 'failed', 0, 0.00, '', '2025-11-26 03:55:01', '2025-11-26 03:55:01'),
(6, 'GRN-2025-00005', 7, 1, '2025-11-26', 'passed', 0, 0.00, '', '2025-11-26 04:30:33', '2025-11-26 04:30:33'),
(7, 'GRN-2025-00006', 8, 1, '2025-11-26', 'failed', 0, 0.00, '', '2025-11-26 04:45:12', '2025-11-26 04:45:12'),
(8, 'GRN-2025-00007', 9, 1, '2025-11-26', 'passed', 0, 0.00, '', '2025-11-26 04:46:50', '2025-11-26 04:46:50'),
(9, 'GRN-2025-00008', 10, 1, '2025-11-26', 'passed', 0, 0.00, '', '2025-11-26 05:31:31', '2025-11-26 05:31:31'),
(10, 'GRN-2025-00009', 12, 1, '2025-11-26', 'passed', 0, 0.00, 'test', '2025-11-26 07:10:50', '2025-11-26 07:10:50'),
(11, 'GRN-2025-00010', 13, 3, '2025-11-26', 'partial', 0, 0.00, '', '2025-11-26 09:57:34', '2025-11-26 09:57:34'),
(12, 'GRN-2025-00011', 14, 3, '2025-11-26', 'partial', 0, 0.00, '', '2025-11-26 10:09:04', '2025-11-26 10:09:04');

-- --------------------------------------------------------

--
-- Table structure for table `grn_items`
--

DROP TABLE IF EXISTS `grn_items`;
CREATE TABLE IF NOT EXISTS `grn_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grn_id` int NOT NULL,
  `po_item_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity_received` int NOT NULL,
  `quantity_accepted` int NOT NULL DEFAULT '0',
  `quantity_rejected` int GENERATED ALWAYS AS ((`quantity_received` - `quantity_accepted`)) STORED,
  `unit_cost` decimal(10,2) NOT NULL,
  `condition_status` enum('good','damaged','expired') NOT NULL DEFAULT 'good',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_grn_id` (`grn_id`),
  KEY `idx_po_item_id` (`po_item_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_condition_status` (`condition_status`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `grn_items`
--

INSERT INTO `grn_items` (`id`, `grn_id`, `po_item_id`, `product_id`, `quantity_received`, `quantity_accepted`, `unit_cost`, `condition_status`, `notes`, `created_at`) VALUES
(1, 1, 9, 2, 5, 5, 39549.44, 'good', NULL, '2025-11-26 02:34:32'),
(2, 2, 7, 1, 3, 3, 33899.44, 'good', NULL, '2025-11-26 02:34:52'),
(3, 3, 1, 2, 5, 2, 29380.00, 'good', NULL, '2025-11-26 02:35:08'),
(4, 3, 2, 15, 5, 3, 10170.00, 'good', NULL, '2025-11-26 02:35:08'),
(6, 5, 8, 1, 4, 0, 33899.44, 'good', NULL, '2025-11-26 03:55:01'),
(7, 6, 10, 4, 1, 1, 67799.44, 'good', NULL, '2025-11-26 04:30:33'),
(8, 7, 11, 1, 1, 0, 33899.44, 'good', NULL, '2025-11-26 04:45:12'),
(9, 8, 12, 2, 8, 8, 39549.44, 'good', NULL, '2025-11-26 04:46:50'),
(10, 9, 13, 1, 1, 1, 33899.44, 'good', NULL, '2025-11-26 05:31:31'),
(11, 10, 14, 25, 5, 5, 122222.00, 'good', NULL, '2025-11-26 07:10:50'),
(12, 11, 15, 17, 20, 15, 733.94, 'good', NULL, '2025-11-26 09:57:34'),
(13, 12, 16, 2, 12, 8, 39549.44, 'good', NULL, '2025-11-26 10:09:04');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

DROP TABLE IF EXISTS `inventory`;
CREATE TABLE IF NOT EXISTS `inventory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `quantity_on_hand` int NOT NULL DEFAULT '0',
  `quantity_reserved` int NOT NULL DEFAULT '0',
  `quantity_available` int GENERATED ALWAYS AS ((`quantity_on_hand` - `quantity_reserved`)) STORED,
  `warehouse_location` varchar(255) DEFAULT NULL,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id` (`product_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_quantity_available` (`quantity_available`),
  KEY `idx_warehouse_location` (`warehouse_location`(250))
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `product_id`, `quantity_on_hand`, `quantity_reserved`, `warehouse_location`, `last_updated`) VALUES
(1, 1, 20, 0, 'Warehouse A - Shelf 1', '2025-11-26 09:59:44'),
(2, 2, 28, 0, 'Warehouse A - Shelf 1', '2025-11-26 10:09:04'),
(3, 3, 3, 0, 'Warehouse A - Shelf 2', '2025-11-12 06:35:16'),
(4, 4, 6, 0, 'Warehouse A - Shelf 2', '2025-11-26 04:30:33'),
(5, 5, 6, 0, 'Warehouse A - Shelf 3', '2025-11-12 06:35:16'),
(6, 6, 12, 0, 'Warehouse A - Shelf 3', '2025-11-12 06:35:16'),
(7, 7, 15, 0, 'Warehouse A - Shelf 4', '2025-11-12 06:35:16'),
(8, 8, 8, 0, 'Warehouse A - Shelf 4', '2025-11-12 06:35:16'),
(9, 9, 10, 0, 'Warehouse A - Shelf 5', '2025-11-12 06:35:16'),
(10, 10, 12, 0, 'Warehouse A - Shelf 5', '2025-11-12 06:35:16'),
(11, 11, 8, 0, 'Warehouse A - Shelf 6', '2025-11-12 06:35:16'),
(12, 12, 14, 0, 'Warehouse A - Shelf 6', '2025-11-12 06:35:16'),
(13, 13, 10, 0, 'Warehouse A - Shelf 7', '2025-11-12 06:35:16'),
(14, 14, 6, 0, 'Warehouse A - Shelf 7', '2025-11-12 06:35:16'),
(15, 15, 25, 0, 'Warehouse A - Shelf 8', '2025-11-26 08:36:36'),
(16, 16, 18, 0, 'Warehouse A - Shelf 8', '2025-11-12 06:35:16'),
(17, 17, 16, 0, 'Warehouse A - Shelf 9', '2025-11-26 09:57:34'),
(18, 18, 15, 0, 'Warehouse A - Shelf 9', '2025-11-12 06:35:16'),
(19, 19, 20, 0, 'Warehouse A - Shelf 9', '2025-11-12 06:35:16'),
(20, 20, 16, 0, 'Warehouse A - Shelf 9', '2025-11-12 06:35:16'),
(21, 21, 10, 0, NULL, '2025-11-21 11:22:28'),
(22, 22, 25, 0, NULL, '2025-11-22 10:23:51'),
(23, 23, 12, 0, NULL, '2025-11-22 10:40:20'),
(24, 24, 20, 0, NULL, '2025-11-26 04:39:03'),
(25, 25, 5, 0, NULL, '2025-11-26 07:25:46'),
(26, 26, 15, 0, NULL, '2025-11-26 08:36:20');

-- --------------------------------------------------------

--
-- Table structure for table `login_sessions`
--

DROP TABLE IF EXISTS `login_sessions`;
CREATE TABLE IF NOT EXISTS `login_sessions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `session_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_fingerprint` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SHA256 hash of device characteristics',
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `logout_time` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_device_fingerprint` (`device_fingerprint`),
  KEY `idx_login_time` (`login_time`),
  KEY `idx_session_id` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_sessions`
--

INSERT INTO `login_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `device_fingerprint`, `country`, `city`, `login_time`, `logout_time`, `is_active`) VALUES
(1, 1, 'pm54u7elaml2h7us1t5ac7j484', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-22 00:31:06', NULL, 1),
(2, 1, 'g14ndonuj0nrqi0493jl5hcrt5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-22 00:31:16', NULL, 1),
(3, 1, '6g0q8iqkhcu0o67bqdk6k0be4i', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-25 00:56:20', NULL, 1),
(4, 1, 'q5s1badsm8bui9megqgpldgaml', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2aa919494b0a6d743e183661a8ac4cd3cf40583387a9fc7fd8cfcd4838f04c49', 'Philippines', 'Unknown', '2025-11-25 00:56:44', NULL, 1),
(5, 1, 'nekv6cpg5jjbjqiv715p5fpe83', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-25 04:11:44', NULL, 1),
(6, 1, 'mlpah0518cl56rmt21h7srfagm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-25 05:29:57', NULL, 1),
(7, 1, 'mv5k0i7msll5qr2jg013lff2vd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-25 06:46:51', NULL, 1),
(8, 1, 'u9r7oh3i4uvruu13tgkvejo28k', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-25 15:24:03', NULL, 1),
(9, 1, 'if7hsce1g5hf0mgqh21hcf4drk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-25 17:41:35', NULL, 1),
(10, 1, 'so9qlb620fbct3s7mpmk5tkmae', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-25 19:52:02', NULL, 1),
(11, 1, '8opvru7a69jlt98j0174051hsn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '055b39690c55744270c2b3a43ef8e5941064c6eeb0ee42c464aa8c9e6ecb2386', 'Philippines', 'Unknown', '2025-11-25 23:04:54', NULL, 1),
(12, 1, '591qhdgo0ao374miqlrbmab1gs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-25 23:05:56', NULL, 1),
(13, 2, '6aptivan07ua7pdsit59ecp07q', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '055b39690c55744270c2b3a43ef8e5941064c6eeb0ee42c464aa8c9e6ecb2386', 'Philippines', 'Unknown', '2025-11-26 00:26:50', NULL, 1),
(14, 1, '9qh0d84biio7i6teddriovm0c8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 01:44:03', NULL, 1),
(15, 3, '1ltgqjka9v5u1v2la16bpb4ekg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '54a0c9dc6a657627a316d0ed72de8c087a0a7ec2f0566cb691d8dc1bce7ba01c', 'Philippines', 'Unknown', '2025-11-26 01:44:10', NULL, 1),
(16, 5, '8ouo6jl5p7badoh9c6t0orju1c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '055b39690c55744270c2b3a43ef8e5941064c6eeb0ee42c464aa8c9e6ecb2386', 'Philippines', 'Unknown', '2025-11-26 02:39:58', NULL, 1),
(17, 1, '4cis3telu44p29idtdskcnndhp', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 02:53:31', NULL, 1),
(18, 1, '7pu37lgtnhlg382upvvodndo3t', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 02:57:25', NULL, 1),
(19, 1, '7gak9iaqi9p2k168ke95chm7va', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 02:59:15', NULL, 1),
(20, 1, '67643mri9sd9lq0le6nvaii5k7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 03:25:44', NULL, 1),
(21, 1, 'hksu763fbrbau2qreutg0ca5bf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 03:29:17', NULL, 1),
(22, 1, 'i1ra366au24q2s0riajcpn6i7q', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 03:31:39', NULL, 1),
(23, 1, '81sn6kcnofptgnhqcbaacb0t5h', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 03:34:33', NULL, 1),
(24, 1, 'eu7324h0aqj90p0gq4367brb9s', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 03:35:39', NULL, 1),
(25, 3, 'l1ts8a027f4j123gp5b2fb66p7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '54a0c9dc6a657627a316d0ed72de8c087a0a7ec2f0566cb691d8dc1bce7ba01c', 'Philippines', 'Unknown', '2025-11-26 03:35:47', NULL, 1),
(26, 5, 't1j8u0k1uqssr1rq53vhqf4g6n', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '055b39690c55744270c2b3a43ef8e5941064c6eeb0ee42c464aa8c9e6ecb2386', 'Philippines', 'Unknown', '2025-11-26 03:35:51', NULL, 1),
(27, 2, 'gd7u7fv6v819cbkdmcaijulpkp', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '055b39690c55744270c2b3a43ef8e5941064c6eeb0ee42c464aa8c9e6ecb2386', 'Philippines', 'Unknown', '2025-11-26 03:36:11', NULL, 1),
(28, 1, '2j2qb1jju1kgjdv15a6ct6anv3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 03:38:40', NULL, 1),
(29, 3, 'uo31e74h1upia3pvg7av502mmc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '54a0c9dc6a657627a316d0ed72de8c087a0a7ec2f0566cb691d8dc1bce7ba01c', 'Philippines', 'Unknown', '2025-11-26 03:39:57', NULL, 1),
(30, 3, '2t701d9v63dob3u85noaj69gjq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '54a0c9dc6a657627a316d0ed72de8c087a0a7ec2f0566cb691d8dc1bce7ba01c', 'Philippines', 'Unknown', '2025-11-26 03:42:34', NULL, 1),
(31, 1, '1t472mo8hj4l1gaetfta21t1ld', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 03:43:11', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'Recipient user ID',
  `type` enum('info','warning','danger','success') NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'grn, po, product, etc',
  `reference_id` int DEFAULT NULL COMMENT 'ID of related record',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `action_required` tinyint(1) NOT NULL DEFAULT '0',
  `action_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_reference` (`reference_type`,`reference_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `reference_type`, `reference_id`, `is_read`, `action_required`, `action_url`, `created_at`, `read_at`) VALUES
(1, 7, 'warning', '⚠️ Products Rejected - Replacement Required', 'Your delivery for PO #PO-2025-00003 (GRN #GRN-2025-00011) has been inspected. 4 item(s) were rejected out of 12 received. Please review the rejection details and arrange for replacement of the rejected items.', 'grn', 12, 1, 1, '/purchase-orders?view=14', '2025-11-26 10:09:04', '2025-11-26 10:09:25');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sku` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_id` int NOT NULL,
  `description` text,
  `brand` varchar(100) DEFAULT NULL,
  `specifications` json DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reorder_level` int NOT NULL DEFAULT '10',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `image_url` varchar(500) DEFAULT NULL,
  `warranty_months` int NOT NULL DEFAULT '12',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `idx_sku` (`sku`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_brand` (`brand`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_selling_price` (`selling_price`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `name`, `category_id`, `description`, `brand`, `specifications`, `cost_price`, `selling_price`, `reorder_level`, `is_active`, `image_url`, `warranty_months`, `created_at`, `updated_at`) VALUES
(1, 'AMD-RYZEN9-7950X', 'AMD Ryzen 9 7950X 16-Core Processor', 1, '16-core, 32-thread unlocked desktop processor with 4.5 GHz max boost', 'AMD', NULL, 25425.00, 33899.44, 5, 1, 'assets/img/AMD Ryzen 9 7950X 16-Core Processor.png', 36, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(2, 'INTEL-I9-13900K', 'Intel Core i9-13900K 24-Core Processor', 1, '24-core (8P+16E), 32-thread unlocked desktop processor with 5.8 GHz max turbo', 'Intel', NULL, 29380.00, 39549.44, 5, 1, 'assets/img/Intel Core i9-13900K 24-Core Processor.png', 36, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(3, 'NVIDIA-RTX4090', 'NVIDIA GeForce RTX 4090 24GB', 2, 'Ada Lovelace architecture with 24GB GDDR6X, 450W power requirement', 'NVIDIA', NULL, 79100.00, 107349.44, 2, 1, 'assets/img/NVIDIA GeForce RTX 4090 24GB.png', 36, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(4, 'AMD-RX7900XTX', 'AMD Radeon RX 7900 XTX 24GB', 2, 'RDNA 3 architecture with 24GB GDDR6, 355W power requirement', 'AMD', NULL, 50850.00, 67799.44, 2, 1, 'assets/img/AMD Radeon RX 7900 XTX 24GB.png', 36, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(5, 'ASUS-X670E-HERO', 'ASUS ROG Crosshair X670E Hero', 3, 'AMD X670E ATX motherboard with PCIe 5.0, WiFi 6E, 5x M.2 slots', 'ASUS', NULL, 25425.00, 33899.44, 3, 1, 'assets/img/ASUS ROG Crosshair X670E Hero.png', 36, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(6, 'MSI-B650-TOMAHAWK', 'MSI MAG B650 TOMAHAWK WiFi', 3, 'AMD B650 ATX motherboard with PCIe 4.0, WiFi 6, 4x M.2 slots', 'MSI', NULL, 10170.00, 13559.44, 3, 1, 'assets/img/MSI MAG B650 TOMAHAWK WiFi.png', 36, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(7, 'CORSAIR-VENGEANCE-DDR5-32GB', 'Corsair Vengeance DDR5 32GB (2x16GB) 6000MHz', 4, '32GB (2x16GB) DDR5-6000 CL36 memory kit with aluminum heatspreaders', 'Corsair', NULL, 6780.00, 9039.44, 5, 1, 'assets/img/Corsair Vengeance DDR5 32GB (2x16GB) 6000MHz.png', 60, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(8, 'GSKILL-TRIDENT-Z5-64GB', 'G.Skill Trident Z5 RGB DDR5 64GB (2x32GB) 5600MHz', 4, '64GB (2x32GB) DDR5-5600 CL36 RGB memory kit with aluminum heatspreaders', 'G.Skill', NULL, 12430.00, 16949.44, 3, 1, 'assets/img/G.Skill Trident Z5 RGB DDR5 64GB (2x32GB) 5600MHz.png', 60, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(9, 'CORSAIR-RM1000X', 'Corsair RM1000x 1000W 80+ Gold Fully Modular', 6, '1000W 80+ Gold certified fully modular power supply with 135mm fan', 'Corsair', NULL, 8475.00, 11299.44, 3, 1, 'assets/img/Corsair RM1000x 1000W 80+ Gold Fully Modular.png', 120, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(10, 'EVGA-SUPERNOVA-850', 'EVGA SuperNOVA 850 G6 850W 80+ Gold', 6, '850W 80+ Gold certified power supply with 120mm fan and modular cables', 'EVGA', NULL, 5650.00, 7909.44, 3, 1, 'assets/img/EVGA SuperNOVA 850 G6 850W 80+ Gold.png', 120, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(11, 'LIANLI-O11-DYNAMIC-EVO', 'Lian Li O11 Dynamic EVO Mid-Tower Case', 7, 'Mid-tower case with tempered glass, 3x 140mm fans, supports 360mm radiator', 'Lian Li', NULL, 6780.00, 9039.44, 2, 1, 'assets/img/Lian Li O11 Dynamic EVO Mid-Tower Case.png', 24, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(12, 'NZXT-H510-COMPACT', 'NZXT H510 Compact ATX Mid-Tower Case', 7, 'Compact mid-tower case with tempered glass, 2x 120mm fans included', 'NZXT', NULL, 4520.00, 6214.44, 3, 1, 'assets/img/NZXT H510 Compact ATX Mid-Tower Case.png', 24, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(13, 'NOCTUA-NH-D15', 'Noctua NH-D15 Dual Tower CPU Cooler', 8, 'Dual tower CPU cooler with 2x 140mm fans, supports AM4/AM5/LGA1700', 'Noctua', NULL, 5085.00, 6779.44, 5, 1, 'assets/img/Noctua NH-D15 Dual Tower CPU Cooler.png', 72, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(14, 'NZXT-KRAKEN-X73', 'NZXT Kraken X73 360mm AIO Liquid Cooler', 8, '360mm AIO liquid cooler with 3x 120mm RGB fans, supports AM4/AM5/LGA1700', 'NZXT', NULL, 7345.00, 10169.44, 3, 1, 'assets/img/NZXT Kraken X73 360mm AIO Liquid Cooler.png', 72, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(15, 'SAMSUNG-990-PRO-2TB', 'Samsung 990 PRO NVMe SSD 2TB', 5, '2TB PCIe 4.0 NVMe SSD with up to 7450 MB/s read speed', 'Samsung', NULL, 10170.00, 14124.44, 5, 1, 'assets/img/Samsung 990 PRO NVMe SSD 2TB.png', 60, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(16, 'WD-SN850X-1TB', 'WD Black SN850X NVMe SSD 1TB', 5, '1TB PCIe 4.0 NVMe SSD with up to 6600 MB/s read speed', 'Western Digital', NULL, 5085.00, 7344.44, 5, 1, 'assets/img/WD Black SN850X NVMe SSD 1TB.png', 60, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(17, 'ARCTIC-MX5', 'Arctic MX-5 Thermal Paste 4g', 9, '4g syringe of high-performance thermal compound for CPU/GPU cooling', 'Arctic', NULL, 452.00, 733.94, 10, 1, 'assets/img/Arctic MX-5 Thermal Paste 4g.png', 24, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(18, 'CABLEMOD-PRO-MODMESH', 'CableMod Pro ModMesh Cable Kit', 9, 'Modular cable kit with mesh sleeving for clean PC builds', 'CableMod', NULL, 2825.00, 4519.43, 5, 1, 'assets/img/CableMod Pro ModMesh Cable Kit.png', 24, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(19, 'LIANLI-STRIMER-V2', 'Lian Li Strimer Plus V2 RGB Cable', 9, 'RGB addressable LED cable kit for PC builds with 5V 3-pin connector', 'Lian Li', NULL, 1412.50, 2259.44, 5, 1, 'assets/img/Lian Li Strimer Plus V2 RGB Cable.png', 24, '2025-11-12 06:35:16', '2025-11-12 07:09:38'),
(20, 'CORSAIR-COMMANDER-CORE-XT', 'Corsair iCUE Commander Core XT', 9, 'RGB lighting and fan controller with 6x fan headers and 2x LED strips', 'Corsair', NULL, 2260.00, 3389.44, 5, 1, 'assets/img/Corsair iCUE Commander Core XT.png', 24, '2025-11-12 06:35:16', '2025-11-12 07:09:38');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `image_url` varchar(500) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `idx_is_primary` (`is_primary`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

DROP TABLE IF EXISTS `product_reviews`;
CREATE TABLE IF NOT EXISTS `product_reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `order_id` int DEFAULT NULL,
  `rating` tinyint NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `review_text` text NOT NULL,
  `is_verified_purchase` tinyint(1) NOT NULL DEFAULT '0',
  `helpful_votes` int NOT NULL DEFAULT '0',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `moderated_by` int DEFAULT NULL,
  `moderated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `moderated_by` (`moderated_by`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_rating` (`rating`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

DROP TABLE IF EXISTS `promotions`;
CREATE TABLE IF NOT EXISTS `promotions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `discount_type` enum('percentage','fixed','free_shipping') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `minimum_order_amount` decimal(10,2) DEFAULT NULL,
  `applicable_products` json DEFAULT NULL,
  `applicable_categories` json DEFAULT NULL,
  `usage_limit` int NOT NULL DEFAULT '0',
  `usage_count` int NOT NULL DEFAULT '0',
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_discount_type` (`discount_type`),
  KEY `idx_start_date` (`start_date`),
  KEY `idx_end_date` (`end_date`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int NOT NULL,
  `created_by` int NOT NULL,
  `approved_by` int DEFAULT NULL,
  `status` enum('draft','pending_supplier','approved','rejected','ordered','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_po_number` (`po_number`),
  KEY `idx_supplier_id` (`supplier_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_order_date` (`order_date`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_number`, `supplier_id`, `created_by`, `approved_by`, `status`, `order_date`, `expected_delivery_date`, `total_amount`, `notes`, `created_at`, `updated_at`) VALUES
(14, 'PO-2025-00003', 7, 3, 7, 'received', '2025-11-26', NULL, 474593.28, '', '2025-11-26 10:08:40', '2025-11-26 10:09:04'),
(13, 'PO-2025-00002', 7, 3, 7, 'received', '2025-11-26', '2025-11-30', 14678.80, '', '2025-11-26 09:55:25', '2025-11-26 09:57:34'),
(12, 'PO-2025-00001', 7, 1, 7, 'received', '2025-11-26', '2025-11-28', 611110.00, '', '2025-11-26 07:09:47', '2025-11-26 07:10:50');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `po_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity_ordered` int NOT NULL,
  `quantity_received` int NOT NULL DEFAULT '0',
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) GENERATED ALWAYS AS ((`quantity_ordered` * `unit_cost`)) STORED,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_po_id` (`po_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `po_id`, `product_id`, `quantity_ordered`, `quantity_received`, `unit_cost`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 5, 5, 29380.00, 'yess', '2025-11-25 15:05:08', '2025-11-26 02:35:08'),
(2, 1, 15, 5, 5, 10170.00, NULL, '2025-11-25 15:05:08', '2025-11-26 02:35:08'),
(3, 2, 1, 3, 0, 33899.44, 'yes', '2025-11-25 23:25:18', '2025-11-25 23:25:18'),
(4, 2, 4, 1, 0, 67799.44, 'aa', '2025-11-25 23:25:18', '2025-11-25 23:25:18'),
(5, 2, 17, 3, 0, 733.94, 'yes', '2025-11-25 23:25:18', '2025-11-25 23:25:18'),
(6, 3, 17, 1, 0, 733.94, 'yes', '2025-11-25 23:35:33', '2025-11-25 23:35:33'),
(7, 4, 1, 3, 3, 33899.44, NULL, '2025-11-25 23:42:36', '2025-11-26 02:34:52'),
(8, 5, 1, 4, 4, 33899.44, NULL, '2025-11-25 23:46:51', '2025-11-26 03:55:01'),
(9, 6, 2, 5, 5, 39549.44, NULL, '2025-11-26 00:23:24', '2025-11-26 02:34:32'),
(10, 7, 4, 1, 1, 67799.44, NULL, '2025-11-26 04:29:16', '2025-11-26 04:30:33'),
(11, 8, 1, 1, 1, 33899.44, NULL, '2025-11-26 04:34:29', '2025-11-26 04:45:12'),
(12, 9, 2, 8, 8, 39549.44, NULL, '2025-11-26 04:46:18', '2025-11-26 04:46:50'),
(13, 10, 1, 1, 1, 33899.44, NULL, '2025-11-26 04:47:56', '2025-11-26 05:31:31'),
(14, 12, 25, 5, 5, 122222.00, NULL, '2025-11-26 07:09:47', '2025-11-26 07:10:50'),
(15, 13, 17, 20, 20, 733.94, NULL, '2025-11-26 09:55:25', '2025-11-26 09:57:34'),
(16, 14, 2, 12, 12, 39549.44, NULL, '2025-11-26 10:08:40', '2025-11-26 10:09:04');

-- --------------------------------------------------------

--
-- Table structure for table `review_helpful_votes`
--

DROP TABLE IF EXISTS `review_helpful_votes`;
CREATE TABLE IF NOT EXISTS `review_helpful_votes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `review_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `vote_type` enum('helpful','not_helpful') NOT NULL DEFAULT 'helpful',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_review_customer` (`review_id`,`customer_id`),
  KEY `idx_review_id` (`review_id`),
  KEY `idx_customer_id` (`customer_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
CREATE TABLE IF NOT EXISTS `sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `cashier_id` int NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` enum('cash','card','bank_transfer','digital_wallet') NOT NULL,
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `notes` text,
  `sale_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_cashier_id` (`cashier_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_sale_date` (`sale_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sale_id` (`sale_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` varchar(500) DEFAULT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`),
  KEY `idx_category` (`category`),
  KEY `idx_is_system` (`is_system`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `category`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 'maintenance_mode', 'false', 'Enable/disable maintenance mode', 'shop', 0, '2025-11-26 07:28:50', '2025-11-26 07:39:53');

-- --------------------------------------------------------

--
-- Table structure for table `shopping_cart`
--

DROP TABLE IF EXISTS `shopping_cart`;
CREATE TABLE IF NOT EXISTS `shopping_cart` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_customer_product` (`customer_id`,`product_id`),
  UNIQUE KEY `unique_session_product` (`session_id`,`product_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

DROP TABLE IF EXISTS `stock_adjustments`;
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `adjustment_number` varchar(50) NOT NULL,
  `product_id` int NOT NULL,
  `adjustment_type` enum('add','remove','recount') NOT NULL,
  `quantity_before` int NOT NULL,
  `quantity_adjusted` int NOT NULL,
  `quantity_after` int NOT NULL,
  `reason` varchar(255) NOT NULL,
  `notes` text,
  `performed_by` int NOT NULL,
  `adjustment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `adjustment_number` (`adjustment_number`),
  KEY `idx_adjustment_number` (`adjustment_number`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_adjustment_type` (`adjustment_type`),
  KEY `idx_performed_by` (`performed_by`),
  KEY `idx_adjustment_date` (`adjustment_date`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `stock_adjustments`
--

INSERT INTO `stock_adjustments` (`id`, `adjustment_number`, `product_id`, `adjustment_type`, `quantity_before`, `quantity_adjusted`, `quantity_after`, `reason`, `notes`, `performed_by`, `adjustment_date`) VALUES
(5, 'ADJ-20251126-001', 15, 'add', 15, 10, 25, 'sale', NULL, 2, '2025-11-26 08:36:36'),
(6, 'ADJ-20251126-002', 17, 'remove', 25, 24, 1, 'counting_error', NULL, 2, '2025-11-26 09:53:41');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `movement_type` enum('sale','purchase','adjustment','return','transfer','customer_order','customer_return','supplier_return') NOT NULL,
  `quantity` int NOT NULL,
  `quantity_before` int NOT NULL,
  `quantity_after` int NOT NULL,
  `reference_type` enum('SALE','PURCHASE_ORDER','GRN','CUSTOMER_ORDER','CUSTOMER_RETURN','ADJUSTMENT') DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `performed_by` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_movement_type` (`movement_type`),
  KEY `idx_reference_type` (`reference_type`),
  KEY `idx_reference_id` (`reference_id`),
  KEY `idx_performed_by` (`performed_by`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=MyISAM AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `quantity_before`, `quantity_after`, `reference_type`, `reference_id`, `performed_by`, `notes`, `created_at`) VALUES
(1, 1, 'adjustment', 12, 10, 22, 'ADJUSTMENT', 1, 1, NULL, '2025-11-20 09:43:31'),
(2, 2, 'adjustment', 2, 8, 10, 'ADJUSTMENT', 2, 1, NULL, '2025-11-21 10:23:32'),
(3, 2, 'adjustment', 5, 10, 5, 'ADJUSTMENT', 3, 1, NULL, '2025-11-26 00:25:23'),
(4, 2, 'purchase', 5, 5, 10, 'GRN', 1, 1, 'GRN: GRN-2025-00001', '2025-11-26 02:34:32'),
(5, 1, 'purchase', 3, 22, 25, 'GRN', 2, 1, 'GRN: GRN-2025-00002', '2025-11-26 02:34:52'),
(6, 2, 'purchase', 2, 10, 12, 'GRN', 3, 1, 'GRN: GRN-2025-00003', '2025-11-26 02:35:08'),
(7, 15, 'purchase', 3, 12, 15, 'GRN', 3, 1, 'GRN: GRN-2025-00003', '2025-11-26 02:35:08'),
(8, 1, 'purchase', 4, 25, 29, 'GRN', 4, 1, 'GRN: GRN-2025-00004', '2025-11-26 03:52:37'),
(9, 1, 'adjustment', -4, 29, 25, '', 4, 1, 'GRN Deleted - GRN-2025-00004 - PO: PO-2025-00005 - Reason: Deleted by System Administrator', '2025-11-26 03:54:56'),
(10, 4, 'purchase', 1, 5, 6, 'GRN', 6, 1, 'GRN: GRN-2025-00005', '2025-11-26 04:30:33'),
(11, 2, 'purchase', 8, 12, 20, 'GRN', 8, 1, 'GRN: GRN-2025-00007', '2025-11-26 04:46:50'),
(12, 1, 'purchase', 1, 25, 26, 'GRN', 9, 1, 'GRN: GRN-2025-00008', '2025-11-26 05:31:31'),
(13, 25, 'purchase', 5, 0, 5, 'GRN', 10, 1, 'GRN: GRN-2025-00009', '2025-11-26 07:10:50'),
(14, 25, 'sale', -2, 5, 3, 'CUSTOMER_ORDER', 1, 1, 'Customer Order - TEST', '2025-11-26 07:24:39'),
(15, 1, 'sale', -6, 26, 20, 'CUSTOMER_ORDER', 1, 1, 'Customer Order - AMD Ryzen 9 7950X 16-Core Processor', '2025-11-26 07:24:39'),
(16, 25, 'customer_order', 2, 3, 5, 'CUSTOMER_ORDER', 1, NULL, 'Order cancellation ORD-2025-000001', '2025-11-26 07:25:46'),
(17, 1, 'customer_order', 6, 20, 26, 'CUSTOMER_ORDER', 1, NULL, 'Order cancellation ORD-2025-000001', '2025-11-26 07:25:46'),
(18, 1, 'adjustment', 2, 26, 28, 'ADJUSTMENT', 4, 1, NULL, '2025-11-26 08:16:56'),
(19, 15, 'adjustment', 10, 15, 25, 'ADJUSTMENT', 5, 2, NULL, '2025-11-26 08:36:36'),
(20, 17, 'adjustment', 24, 25, 1, 'ADJUSTMENT', 6, 2, NULL, '2025-11-26 09:53:41'),
(21, 17, 'purchase', 15, 1, 16, 'GRN', 11, 3, 'GRN: GRN-2025-00010', '2025-11-26 09:57:34'),
(22, 2, 'purchase', 8, 20, 28, 'GRN', 12, 3, 'GRN: GRN-2025-00011', '2025-11-26 10:09:04');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `supplier_code` varchar(50) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Philippines',
  `tax_id` varchar(50) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT 'Net 30',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_code` (`supplier_code`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_supplier_code` (`supplier_code`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `user_id`, `company_name`, `supplier_code`, `contact_person`, `phone`, `email`, `address`, `city`, `state`, `postal_code`, `country`, `tax_id`, `payment_terms`, `notes`, `created_at`, `updated_at`) VALUES
(5, 7, 'Shopee Co.s', 'SUP-00007', 'Shopee Co.', NULL, 'eckocatalan@gmail.com', '', '', '', '', 'Philippines', '', 'Net 30', 'Created by data integrity fix', '2025-11-26 04:28:47', '2025-11-26 08:14:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','inventory_manager','purchasing_officer','supplier','staff') NOT NULL DEFAULT 'staff',
  `full_name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `password_hash`, `role`, `full_name`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'catalan.jereckopaul@gmail.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 1, '2025-11-26 19:43:11', '2025-11-12 06:02:50', '2025-11-26 11:43:11'),
(2, 'karldc', 'johnlukepolancos4@gmail.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'inventory_manager', 'Karl De Castro', 1, '2025-11-26 19:36:11', '2025-11-12 06:02:50', '2025-11-26 11:36:11'),
(3, 'kevinc', 'jhoncarlopaladin2@gmail.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'purchasing_officer', 'Kevin Calura', 1, '2025-11-26 19:42:34', '2025-11-12 06:02:50', '2025-11-26 11:42:34'),
(7, 'jerexc', 'eckocatalan@gmail.com', '09093333333', '$2y$10$OuF/t2z3P5vtKEyaVGHiO.5pPQF/ks2BdaJXxIFg885LsNNTM8l.6', 'supplier', 'Shopee Co.', 1, '2025-11-26 18:10:08', '2025-11-25 12:12:39', '2025-11-26 10:10:08'),
(5, 'alyssaf', 'jerexko90@gmail.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'Alyssa Flores', 1, '2025-11-26 19:35:51', '2025-11-12 06:02:50', '2025-11-26 11:35:51');

-- --------------------------------------------------------

--
-- Table structure for table `verification_codes`
--

DROP TABLE IF EXISTS `verification_codes`;
CREATE TABLE IF NOT EXISTS `verification_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `code` varchar(255) NOT NULL,
  `code_type` varchar(50) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `is_used` tinyint DEFAULT '0',
  `used_at` timestamp NULL DEFAULT NULL,
  `attempts` int DEFAULT '0',
  `max_attempts` int DEFAULT '5',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_type` (`user_id`,`code_type`),
  KEY `idx_code` (`code`(250)),
  KEY `idx_expires` (`expires_at`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `verification_codes`
--

INSERT INTO `verification_codes` (`id`, `user_id`, `code`, `code_type`, `expires_at`, `is_used`, `used_at`, `attempts`, `max_attempts`, `created_at`) VALUES
(2, 2, 'ada548cd7a0c60e8989b88464ff81f3990fc3758bb845988ace9678516e38afd', 'password_reset', '2025-11-26 12:58:02', 0, NULL, 0, 5, '2025-11-26 11:58:02');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
