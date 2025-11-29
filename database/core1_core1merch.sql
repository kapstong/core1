-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 28, 2025 at 06:22 AM
-- Server version: 10.11.14-MariaDB-ubu2204
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `core1_core1merch`
--

-- --------------------------------------------------------

--
-- Table structure for table `2fa_bypass_records`
--

CREATE TABLE `2fa_bypass_records` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_fingerprint` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(341, 5, 'login', 'user', 5, '{\"success\":true}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 00:44:45'),
(342, 5, 'login', 'user', 5, '{\"success\":true}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 05:07:44'),
(343, 5, 'login', 'user', 5, '{\"success\":true}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 05:59:54'),
(340, 5, 'login', 'user', 5, '{\"success\":true}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 00:25:25'),
(337, 1, 'logout', 'user', 1, '[]', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 14:10:04'),
(338, 5, 'login', 'user', 5, '{\"success\":true}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 14:10:11'),
(339, 5, 'logout', 'user', 5, '[]', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 15:09:19'),
(336, 1, 'users_query_debug', 'debug', NULL, '{\"total_users\":5,\"role_counts\":[{\"role\":\"admin\",\"count\":1},{\"role\":\"inventory_manager\",\"count\":1},{\"role\":\"purchasing_officer\",\"count\":1},{\"role\":\"supplier\",\"count\":1},{\"role\":\"staff\",\"count\":1}],\"query\":\"SELECT\\n                    u.id,\\n                    u.username,\\n                    u.full_name,\\n                    u.email,\\n                    u.role,\\n                    u.is_active,\\n                    u.last_login,\\n                    u.created_at\\n                  FROM users u\\n                  WHERE\\n                    -- EXCLUDE ALL SUPPLIERS (both pending and approved)\\n                    u.role != \'supplier\'\\n                  ORDER BY\\n                    u.created_at DESC\",\"results_count\":4,\"user_role\":\"admin\"}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 13:58:10'),
(335, 1, 'users_query_debug', 'debug', NULL, '{\"total_users\":5,\"role_counts\":[{\"role\":\"admin\",\"count\":1},{\"role\":\"inventory_manager\",\"count\":1},{\"role\":\"purchasing_officer\",\"count\":1},{\"role\":\"supplier\",\"count\":1},{\"role\":\"staff\",\"count\":1}],\"query\":\"SELECT\\n                    u.id,\\n                    u.username,\\n                    u.full_name,\\n                    u.email,\\n                    u.role,\\n                    u.is_active,\\n                    u.last_login,\\n                    u.created_at\\n                  FROM users u\\n                  WHERE\\n                    -- EXCLUDE ALL SUPPLIERS (both pending and approved)\\n                    u.role != \'supplier\'\\n                  ORDER BY\\n                    u.created_at DESC\",\"results_count\":4,\"user_role\":\"admin\"}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 13:58:06'),
(334, 1, 'users_query_debug', 'debug', NULL, '{\"total_users\":5,\"role_counts\":[{\"role\":\"admin\",\"count\":1},{\"role\":\"inventory_manager\",\"count\":1},{\"role\":\"purchasing_officer\",\"count\":1},{\"role\":\"supplier\",\"count\":1},{\"role\":\"staff\",\"count\":1}],\"query\":\"SELECT\\n                    u.id,\\n                    u.username,\\n                    u.full_name,\\n                    u.email,\\n                    u.role,\\n                    u.is_active,\\n                    u.last_login,\\n                    u.created_at\\n                  FROM users u\\n                  WHERE\\n                    -- EXCLUDE ALL SUPPLIERS (both pending and approved)\\n                    u.role != \'supplier\'\\n                  ORDER BY\\n                    u.created_at DESC\",\"results_count\":4,\"user_role\":\"admin\"}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 13:56:27'),
(333, 1, 'users_query_debug', 'debug', NULL, '{\"total_users\":5,\"role_counts\":[{\"role\":\"admin\",\"count\":1},{\"role\":\"inventory_manager\",\"count\":1},{\"role\":\"purchasing_officer\",\"count\":1},{\"role\":\"supplier\",\"count\":1},{\"role\":\"staff\",\"count\":1}],\"query\":\"SELECT\\n                    u.id,\\n                    u.username,\\n                    u.full_name,\\n                    u.email,\\n                    u.role,\\n                    u.is_active,\\n                    u.last_login,\\n                    u.created_at\\n                  FROM users u\\n                  WHERE\\n                    -- EXCLUDE ALL SUPPLIERS (both pending and approved)\\n                    u.role != \'supplier\'\\n                  ORDER BY\\n                    u.created_at DESC\",\"results_count\":4,\"user_role\":\"admin\"}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 13:56:27'),
(332, 1, 'login', 'user', 1, '{\"success\":true}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 13:55:52'),
(331, 1, 'logout', 'user', 1, '[]', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 11:13:24'),
(330, 1, 'login', 'user', 1, '{\"success\":true}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 11:10:57'),
(329, 2, 'logout', 'user', 2, '[]', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 11:10:51'),
(328, 2, 'login', 'user', 2, '{\"success\":true}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 10:24:36'),
(327, 2, 'logout', 'user', 2, '[]', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 10:16:55'),
(326, 2, 'login', 'user', 2, '{\"success\":true}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 09:44:31');

-- --------------------------------------------------------

--
-- Table structure for table `analytics_events`
--

CREATE TABLE `analytics_events` (
  `id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `referrer_url` varchar(500) DEFAULT NULL,
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`event_data`)),
  `duration` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL COMMENT 'Type of entity (user, product, order, etc.)',
  `entity_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID of the affected entity',
  `description` text NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Previous values before change' CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'New values after change' CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `action`, `entity_type`, `entity_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(137, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:16:42'),
(138, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:18:05'),
(139, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:20:55'),
(140, 1, 'admin', 'logout', 'user', 1, 'User logged out', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:23:14'),
(141, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:25:49'),
(142, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:29:28'),
(143, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:32:24'),
(144, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:35:37'),
(145, 1, 'admin', 'logout', 'user', 1, 'User logged out', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:35:49'),
(146, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:41:35'),
(147, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:43:05'),
(148, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:49:38'),
(149, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 13:57:30'),
(150, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 14:05:59'),
(151, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 14:21:05'),
(152, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 14:25:14'),
(153, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 14:31:05'),
(154, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 14:36:44'),
(155, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:32:56'),
(156, 1, 'admin', 'create', 'product', 27, 'Product \'TEST\' created', NULL, '{\"sku\":\"TEST-TEST-TEST\",\"name\":\"TEST\",\"category_id\":7,\"cost_price\":\"122.00\",\"selling_price\":\"1222.00\"}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:33:24'),
(157, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:39:47'),
(158, 1, 'admin', 'create', 'product', 28, 'Product \'asadad\' created', NULL, '{\"sku\":\"asdawdada\",\"name\":\"asadad\",\"category_id\":7,\"cost_price\":\"122.00\",\"selling_price\":\"12222.00\"}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:40:10'),
(159, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:46:18'),
(160, 1, 'admin', 'create', 'product', 29, 'Product \'asadadadad\' created', NULL, '{\"sku\":\"adadadad\",\"name\":\"asadadadad\",\"category_id\":9,\"cost_price\":\"122.00\",\"selling_price\":\"12222.00\"}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:46:51'),
(161, 1, 'admin', 'delete', 'product', 28, 'Product \'asdawdada\' deleted', '{\"sku\":\"asdawdada\",\"name\":\"asadad\",\"category_id\":7,\"cost_price\":\"122.00\",\"selling_price\":\"12222.00\",\"deleted_by\":\"System Administrator\"}', NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:47:00'),
(162, 1, 'admin', 'delete', 'product', 27, 'Product \'TEST-TEST-TEST\' deleted', '{\"sku\":\"TEST-TEST-TEST\",\"name\":\"TEST\",\"category_id\":7,\"cost_price\":\"122.00\",\"selling_price\":\"1222.00\",\"deleted_by\":\"System Administrator\"}', NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:47:02'),
(163, 1, 'admin', 'update', 'product', 29, 'Product \'adadadad\' updated', '{\"id\":29,\"sku\":\"adadadad\",\"name\":\"asadadadad\",\"category_id\":9,\"description\":\"asdasda\",\"brand\":null,\"specifications\":null,\"cost_price\":\"122.00\",\"selling_price\":\"12222.00\",\"reorder_level\":5,\"is_active\":1,\"image_url\":\"\\/public\\/assets\\/img\\/products\\/temp_23057nb23nvncc8ke1pq5pl0be_1764208009_5213.png?t=1764208011\",\"warranty_months\":12,\"created_at\":\"2025-11-27 01:46:51\",\"updated_at\":\"2025-11-27 01:46:51\",\"category_name\":\"Accessories\",\"quantity_on_hand\":22,\"quantity_reserved\":0,\"quantity_available\":22,\"warehouse_location\":null}', '{\"id\":29,\"sku\":\"adadadad\",\"name\":\"borat\",\"category_id\":9,\"description\":\"asdasda\",\"brand\":null,\"specifications\":null,\"cost_price\":\"122.00\",\"selling_price\":\"12222.00\",\"reorder_level\":5,\"is_active\":1,\"image_url\":\"\\/public\\/assets\\/img\\/products\\/temp_23057nb23nvncc8ke1pq5pl0be_1764208009_5213.png?t=1764208011\",\"warranty_months\":12,\"created_at\":\"2025-11-27 01:46:51\",\"updated_at\":\"2025-11-27 01:47:48\",\"category_name\":\"Accessories\",\"quantity_on_hand\":22,\"quantity_reserved\":0,\"quantity_available\":22,\"warehouse_location\":null}', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:47:48'),
(164, 1, 'admin', 'delete', 'product', 29, 'Product \'adadadad\' deleted', '{\"sku\":\"adadadad\",\"name\":\"borat\",\"category_id\":9,\"cost_price\":\"122.00\",\"selling_price\":\"12222.00\",\"deleted_by\":\"System Administrator\"}', NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:48:12'),
(165, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:49:55'),
(166, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:56:42'),
(167, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 01:59:23'),
(168, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 02:04:24'),
(169, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 02:06:25'),
(170, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 02:13:10'),
(171, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 02:17:50'),
(172, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 02:22:44'),
(173, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 02:28:18'),
(174, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 02:35:30'),
(175, 1, 'admin', 'logout', 'user', 1, 'User logged out', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 02:37:56'),
(176, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 02:38:03'),
(177, 1, 'admin', 'logout', 'user', 1, 'User logged out', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 02:43:47'),
(178, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 02:43:52'),
(179, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 08:05:03'),
(180, 1, 'admin', 'logout', 'user', 1, 'User logged out', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 08:53:19'),
(181, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 08:54:25'),
(182, 1, 'admin', 'logout', 'user', 1, 'User logged out', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 08:59:44'),
(183, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 09:02:33'),
(184, 1, 'admin', 'login_failed', 'user', NULL, 'Failed login attempt for username: karldc', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 09:44:28'),
(185, 2, 'karldc', 'login', 'user', 2, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 09:44:31'),
(186, 2, 'karldc', 'logout', 'user', 2, 'User logged out', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 10:16:55'),
(187, 2, 'karldc', 'login', 'user', 2, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 10:24:36'),
(188, 2, 'karldc', 'logout', 'user', 2, 'User logged out', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 11:10:51'),
(189, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 11:10:57'),
(190, 1, 'admin', 'logout', 'user', 1, 'User logged out', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 11:13:24'),
(191, 1, 'admin', 'login', 'user', 1, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 13:55:52'),
(192, 1, 'admin', 'logout', 'user', 1, 'User logged out', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 14:10:04'),
(193, 5, 'alyssaf', 'login', 'user', 5, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 14:10:11'),
(194, 5, 'alyssaf', 'logout', 'user', 5, 'User logged out', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 15:09:19'),
(195, 5, 'alyssaf', 'login', 'user', 5, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 00:25:25'),
(196, 5, 'alyssaf', 'login', 'user', 5, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 00:44:45'),
(197, 5, 'alyssaf', 'login', 'user', 5, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 05:07:44'),
(198, 5, 'alyssaf', 'login', 'user', 5, 'User logged in successfully', NULL, NULL, '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 05:59:54');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `date_of_birth`, `gender`, `email_verification_token`, `email_verified`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'jereckopaul90@gmail.com', '$2y$10$v6s5T91FB/p.EhoXvsTW7.62ZWvcTGzTFU6EYOSqeIPb2Ghrom5dG', 'Jerecko Paul', 'Catalan', '09999922933', NULL, NULL, NULL, 1, '2025-11-28 00:24:53', '2025-11-26 07:11:28', '2025-11-28 00:24:53'),
(2, 'ilovekarldecastro@gmail.com', '$2y$10$piTcBx7MPsxOKYYCrAq1OeTCPl3iV9r70Weug7lpydNpjhvR3WAVK', 'Karl', 'Franciz De Castro', '+639776952033', NULL, NULL, NULL, 1, NULL, '2025-11-27 13:52:20', '2025-11-27 13:53:05');

-- --------------------------------------------------------

--
-- Table structure for table `customer_addresses`
--

CREATE TABLE `customer_addresses` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `address_type` enum('shipping','billing') NOT NULL DEFAULT 'shipping',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_addresses`
--

INSERT INTO `customer_addresses` (`id`, `customer_id`, `address_type`, `is_default`, `first_name`, `last_name`, `company`, `address_line_1`, `address_line_2`, `city`, `state`, `postal_code`, `country`, `phone`, `created_at`, `updated_at`) VALUES
(1, 1, 'shipping', 1, 'Jerecko', 'Catalan', '', '123', '123', '123', '123', '123', 'PH', '123123123', '2025-11-26 07:24:39', '2025-11-26 07:24:39'),
(2, 1, 'billing', 1, 'Jerecko', 'Catalan', '', '123', '123', '123', '123', '123', 'PH', '123123123', '2025-11-26 07:24:39', '2025-11-26 07:24:39'),
(3, 1, 'shipping', 0, 'Jerecko Paul', 'Catalan', NULL, 'test', 'test', 'test', 'test', '1121', 'PH', 'test', '2025-11-27 13:50:02', '2025-11-27 13:50:02'),
(4, 1, 'billing', 0, 'Jerecko Paul', 'Catalan', NULL, 'test', 'test', 'test', 'test', '1121', 'PH', 'test', '2025-11-27 13:50:02', '2025-11-27 13:50:02'),
(5, 1, 'shipping', 0, 'Jerecko Paul', 'Catalan', NULL, 'Block 1 Lot 1 Kilyawan Street', 'Commonwealth', 'Quezon City', 'Metro Manila', '1121', 'PH', '09632225541', '2025-11-28 05:21:07', '2025-11-28 05:21:07'),
(6, 1, 'billing', 0, 'Jerecko Paul', 'Catalan', NULL, 'Block 1 Lot 1 Kilyawan Street', 'Commonwealth', 'Quezon City', 'Metro Manila', '1121', 'PH', '09632225541', '2025-11-28 05:21:07', '2025-11-28 05:21:07'),
(7, 1, 'shipping', 0, 'test', 'test', NULL, 'test', 'test', 'tes', 'test', '1121', 'PH', '09632225541', '2025-11-28 05:57:52', '2025-11-28 05:57:52'),
(8, 1, 'billing', 0, 'test', 'test', NULL, 'test', 'test', 'tes', 'test', '1121', 'PH', '09632225541', '2025-11-28 05:57:52', '2025-11-28 05:57:52');

-- --------------------------------------------------------

--
-- Table structure for table `customer_orders`
--

CREATE TABLE `customer_orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `status` enum('pending','confirmed','processing','shipped','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  `order_date` timestamp NULL DEFAULT current_timestamp(),
  `shipping_address_id` int(11) DEFAULT NULL,
  `billing_address_id` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `shipping_method` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_orders`
--

INSERT INTO `customer_orders` (`id`, `order_number`, `customer_id`, `status`, `order_date`, `shipping_address_id`, `billing_address_id`, `subtotal`, `tax_amount`, `shipping_amount`, `discount_amount`, `total_amount`, `payment_method`, `payment_status`, `shipping_method`, `tracking_number`, `notes`, `created_at`, `updated_at`) VALUES
(2, 'ORD-2025-000001', 1, 'cancelled', '2025-11-27 12:36:59', NULL, NULL, 175148.88, 14011.91, 0.00, 0.00, 189160.79, 'cash_on_delivery', 'pending', NULL, NULL, 'test', '2025-11-27 12:36:59', '2025-11-28 05:15:35'),
(3, 'ORD-2025-000002', 1, 'cancelled', '2025-11-27 12:37:11', NULL, NULL, 175148.88, 14011.91, 0.00, 0.00, 189160.79, 'cash_on_delivery', 'pending', NULL, NULL, 'test', '2025-11-27 12:37:11', '2025-11-28 05:15:26'),
(4, 'ORD-2025-000003', 1, 'cancelled', '2025-11-27 13:38:08', NULL, NULL, 175148.88, 14011.91, 0.00, 0.00, 189160.79, 'cash_on_delivery', 'pending', NULL, NULL, 'test', '2025-11-27 13:38:08', '2025-11-28 05:15:18'),
(5, 'ORD-2025-000004', 1, 'cancelled', '2025-11-27 13:38:19', NULL, NULL, 175148.88, 14011.91, 0.00, 0.00, 189160.79, 'cash_on_delivery', 'pending', NULL, NULL, 'test', '2025-11-27 13:38:19', '2025-11-28 05:15:10'),
(6, 'ORD-2025-000005', 1, 'cancelled', '2025-11-27 13:47:17', NULL, NULL, 175148.88, 14011.91, 0.00, 0.00, 189160.79, 'cash_on_delivery', 'pending', NULL, NULL, '', '2025-11-27 13:47:17', '2025-11-28 05:14:58'),
(7, 'ORD-2025-000006', 1, 'confirmed', '2025-11-27 13:50:02', 3, 4, 175148.88, 14011.91, 0.00, 0.00, 189160.79, 'cash_on_delivery', 'paid', NULL, NULL, '', '2025-11-27 13:50:02', '2025-11-27 13:50:02'),
(8, 'ORD-2025-000007', 1, 'confirmed', '2025-11-28 05:21:07', 5, 6, 305094.96, 24407.60, 0.00, 0.00, 329502.56, 'cash_on_delivery', 'paid', NULL, NULL, '', '2025-11-28 05:21:07', '2025-11-28 05:37:00'),
(9, 'ORD-2025-000008', 1, 'confirmed', '2025-11-28 05:41:31', 5, 6, 237296.64, 18983.73, 0.00, 0.00, 256280.37, 'cash_on_delivery', 'paid', NULL, NULL, '', '2025-11-28 05:41:31', '2025-11-28 05:41:44'),
(10, 'ORD-2025-000009', 1, 'confirmed', '2025-11-28 05:56:55', 5, 6, 389847.76, 31187.82, 0.00, 0.00, 421035.58, 'cash_on_delivery', 'paid', NULL, NULL, '', '2025-11-28 05:56:55', '2025-11-28 05:57:05'),
(11, 'ORD-2025-000010', 1, 'confirmed', '2025-11-28 05:57:52', 7, 8, 271197.76, 21695.82, 0.00, 0.00, 292893.58, 'cash_on_delivery', 'paid', NULL, NULL, '', '2025-11-28 05:57:52', '2025-11-28 05:58:02'),
(12, 'ORD-2025-000011', 1, 'confirmed', '2025-11-28 06:01:01', 7, 8, 18643.32, 1491.47, 0.00, 0.00, 20134.79, 'cash_on_delivery', 'paid', NULL, NULL, '', '2025-11-28 06:01:01', '2025-11-28 06:01:14'),
(13, 'ORD-2025-000012', 1, 'confirmed', '2025-11-28 06:04:32', 7, 8, 197747.20, 15819.78, 0.00, 0.00, 213566.98, 'cash_on_delivery', 'paid', NULL, NULL, '', '2025-11-28 06:04:32', '2025-11-28 06:04:38'),
(14, 'ORD-2025-000013', 1, 'confirmed', '2025-11-28 06:19:43', 7, 8, 13559.44, 1084.76, 0.00, 0.00, 14644.20, 'cash_on_delivery', 'paid', NULL, NULL, '', '2025-11-28 06:19:43', '2025-11-28 06:19:56'),
(15, 'ORD-2025-000014', 1, 'confirmed', '2025-11-28 06:22:07', 7, 8, 18643.32, 1491.47, 0.00, 0.00, 20134.79, 'cash_on_delivery', 'paid', NULL, NULL, '', '2025-11-28 06:22:07', '2025-11-28 06:22:19');

-- --------------------------------------------------------

--
-- Table structure for table `customer_order_items`
--

CREATE TABLE `customer_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_sku` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_order_items`
--

INSERT INTO `customer_order_items` (`id`, `order_id`, `product_id`, `product_name`, `product_sku`, `quantity`, `unit_price`, `created_at`) VALUES
(3, 2, 3, 'NVIDIA GeForce RTX 4090 24GB', 'NVIDIA-RTX4090', 1, 107349.44, '2025-11-27 12:36:59'),
(4, 2, 4, 'AMD Radeon RX 7900 XTX 24GB', 'AMD-RX7900XTX', 1, 67799.44, '2025-11-27 12:36:59'),
(5, 3, 3, 'NVIDIA GeForce RTX 4090 24GB', 'NVIDIA-RTX4090', 1, 107349.44, '2025-11-27 12:37:11'),
(6, 3, 4, 'AMD Radeon RX 7900 XTX 24GB', 'AMD-RX7900XTX', 1, 67799.44, '2025-11-27 12:37:11'),
(7, 4, 3, 'NVIDIA GeForce RTX 4090 24GB', 'NVIDIA-RTX4090', 1, 107349.44, '2025-11-27 13:38:08'),
(8, 4, 4, 'AMD Radeon RX 7900 XTX 24GB', 'AMD-RX7900XTX', 1, 67799.44, '2025-11-27 13:38:08'),
(9, 5, 3, 'NVIDIA GeForce RTX 4090 24GB', 'NVIDIA-RTX4090', 1, 107349.44, '2025-11-27 13:38:19'),
(10, 5, 4, 'AMD Radeon RX 7900 XTX 24GB', 'AMD-RX7900XTX', 1, 67799.44, '2025-11-27 13:38:19'),
(11, 6, 3, 'NVIDIA GeForce RTX 4090 24GB', 'NVIDIA-RTX4090', 1, 107349.44, '2025-11-27 13:47:17'),
(12, 6, 4, 'AMD Radeon RX 7900 XTX 24GB', 'AMD-RX7900XTX', 1, 67799.44, '2025-11-27 13:47:17'),
(13, 7, 3, 'NVIDIA GeForce RTX 4090 24GB', 'NVIDIA-RTX4090', 1, 107349.44, '2025-11-27 13:50:02'),
(14, 7, 4, 'AMD Radeon RX 7900 XTX 24GB', 'AMD-RX7900XTX', 1, 67799.44, '2025-11-27 13:50:02'),
(15, 8, 1, 'AMD Ryzen 9 7950X 16-Core Processor', 'AMD-RYZEN9-7950X', 9, 33899.44, '2025-11-28 05:21:07'),
(16, 9, 2, 'Intel Core i9-13900K 24-Core Processor', 'INTEL-I9-13900K', 6, 39549.44, '2025-11-28 05:41:31'),
(17, 10, 4, 'AMD Radeon RX 7900 XTX 24GB', 'AMD-RX7900XTX', 1, 67799.44, '2025-11-28 05:56:55'),
(18, 10, 3, 'NVIDIA GeForce RTX 4090 24GB', 'NVIDIA-RTX4090', 3, 107349.44, '2025-11-28 05:56:55'),
(19, 11, 4, 'AMD Radeon RX 7900 XTX 24GB', 'AMD-RX7900XTX', 4, 67799.44, '2025-11-28 05:57:52'),
(20, 12, 12, 'NZXT H510 Compact ATX Mid-Tower Case', 'NZXT-H510-COMPACT', 3, 6214.44, '2025-11-28 06:01:01'),
(21, 13, 2, 'Intel Core i9-13900K 24-Core Processor', 'INTEL-I9-13900K', 5, 39549.44, '2025-11-28 06:04:32'),
(22, 14, 6, 'MSI MAG B650 TOMAHAWK WiFi', 'MSI-B650-TOMAHAWK', 1, 13559.44, '2025-11-28 06:19:43'),
(23, 15, 12, 'NZXT H510 Compact ATX Mid-Tower Case', 'NZXT-H510-COMPACT', 3, 6214.44, '2025-11-28 06:22:07');

-- --------------------------------------------------------

--
-- Table structure for table `customer_returns`
--

CREATE TABLE `customer_returns` (
  `id` int(11) NOT NULL,
  `return_number` varchar(50) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `return_reason` text NOT NULL,
  `return_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('requested','approved','received','processed','rejected') NOT NULL DEFAULT 'requested',
  `refund_method` varchar(50) DEFAULT NULL,
  `refund_status` enum('pending','processed','failed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_return_items`
--

CREATE TABLE `customer_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_sku` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `return_reason` varchar(255) DEFAULT NULL,
  `condition_status` enum('new','opened','used','damaged') NOT NULL DEFAULT 'new',
  `refund_amount` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_wishlists`
--

CREATE TABLE `customer_wishlists` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `goods_received_notes`
--

CREATE TABLE `goods_received_notes` (
  `id` int(11) NOT NULL,
  `grn_number` varchar(50) NOT NULL,
  `po_id` int(11) NOT NULL,
  `received_by` int(11) NOT NULL,
  `received_date` date NOT NULL,
  `inspection_status` enum('pending','passed','failed','partial') NOT NULL DEFAULT 'pending',
  `total_items_received` int(11) NOT NULL DEFAULT 0,
  `total_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `grn_items` (
  `id` int(11) NOT NULL,
  `grn_id` int(11) NOT NULL,
  `po_item_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_received` int(11) NOT NULL,
  `quantity_accepted` int(11) NOT NULL DEFAULT 0,
  `quantity_rejected` int(11) GENERATED ALWAYS AS (`quantity_received` - `quantity_accepted`) STORED,
  `unit_cost` decimal(10,2) NOT NULL,
  `condition_status` enum('good','damaged','expired') NOT NULL DEFAULT 'good',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_on_hand` int(11) NOT NULL DEFAULT 0,
  `quantity_reserved` int(11) NOT NULL DEFAULT 0,
  `quantity_available` int(11) GENERATED ALWAYS AS (`quantity_on_hand` - `quantity_reserved`) STORED,
  `warehouse_location` varchar(255) DEFAULT NULL,
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `product_id`, `quantity_on_hand`, `quantity_reserved`, `warehouse_location`, `last_updated`) VALUES
(1, 1, 20, 19, 'Warehouse A - Shelf 1', '2025-11-28 05:21:07'),
(2, 2, 28, 23, 'Warehouse A - Shelf 1', '2025-11-28 06:04:32'),
(3, 3, 7, 6, 'Warehouse A - Shelf 2', '2025-11-28 05:56:55'),
(4, 4, 10, 10, 'Warehouse A - Shelf 2', '2025-11-28 05:57:52'),
(5, 5, 6, 0, 'Warehouse A - Shelf 3', '2025-11-12 06:35:16'),
(6, 6, 12, 2, 'Warehouse A - Shelf 3', '2025-11-28 06:19:43'),
(7, 7, 15, 0, 'Warehouse A - Shelf 4', '2025-11-12 06:35:16'),
(8, 8, 8, 0, 'Warehouse A - Shelf 4', '2025-11-12 06:35:16'),
(9, 9, 10, 0, 'Warehouse A - Shelf 5', '2025-11-12 06:35:16'),
(10, 10, 12, 0, 'Warehouse A - Shelf 5', '2025-11-12 06:35:16'),
(11, 11, 8, 0, 'Warehouse A - Shelf 6', '2025-11-12 06:35:16'),
(12, 12, 14, 12, 'Warehouse A - Shelf 6', '2025-11-28 06:22:07'),
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
(26, 26, 15, 0, NULL, '2025-11-26 08:36:20'),
(27, 27, 20, 0, NULL, '2025-11-27 01:33:24'),
(28, 28, 10, 0, NULL, '2025-11-27 01:40:10'),
(29, 29, 22, 0, NULL, '2025-11-27 01:46:51');

-- --------------------------------------------------------

--
-- Table structure for table `login_sessions`
--

CREATE TABLE `login_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `device_fingerprint` varchar(64) DEFAULT NULL COMMENT 'SHA256 hash of device characteristics',
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `login_time` timestamp NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_sessions`
--

INSERT INTO `login_sessions` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `device_fingerprint`, `country`, `city`, `login_time`, `logout_time`, `is_active`) VALUES
(26, 5, 't1j8u0k1uqssr1rq53vhqf4g6n', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '055b39690c55744270c2b3a43ef8e5941064c6eeb0ee42c464aa8c9e6ecb2386', 'Philippines', 'Unknown', '2025-11-26 03:35:51', NULL, 1),
(27, 2, 'gd7u7fv6v819cbkdmcaijulpkp', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '055b39690c55744270c2b3a43ef8e5941064c6eeb0ee42c464aa8c9e6ecb2386', 'Philippines', 'Unknown', '2025-11-26 03:36:11', NULL, 1),
(28, 1, '2j2qb1jju1kgjdv15a6ct6anv3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 03:38:40', NULL, 1),
(29, 3, 'uo31e74h1upia3pvg7av502mmc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '54a0c9dc6a657627a316d0ed72de8c087a0a7ec2f0566cb691d8dc1bce7ba01c', 'Philippines', 'Unknown', '2025-11-26 03:39:57', NULL, 1),
(30, 3, '2t701d9v63dob3u85noaj69gjq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '54a0c9dc6a657627a316d0ed72de8c087a0a7ec2f0566cb691d8dc1bce7ba01c', 'Philippines', 'Unknown', '2025-11-26 03:42:34', NULL, 1),
(31, 1, '1t472mo8hj4l1gaetfta21t1ld', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 03:43:11', NULL, 1),
(32, 3, '06ladimogr1pmoctn9ojtauls6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 04:03:41', NULL, 1),
(33, 2, 'otc77ommo3ktd7b4ds7e7pn971', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'b80fdfb8798551012876546b957b5f480c92e93cfbd6b0e72c12480f766c4abc', 'Philippines', 'Unknown', '2025-11-26 04:04:11', NULL, 1),
(34, 1, 'gkbf2e8o3mji25st6dkbj2129n', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 13:16:42', NULL, 1),
(35, 1, 'k9c818k7ogr872mdihh4feof69', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 13:18:05', NULL, 1),
(36, 1, '748699pd5372sduurvou6mhkn2', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 13:20:55', NULL, 1),
(37, 1, 'hn7sfr7s9bmo6e7c068bnub42q', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 13:25:49', NULL, 1),
(38, 1, 'm8rlsvgr9vtlkrrpkr7bmeva7e', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 13:29:28', NULL, 1),
(39, 1, 'cko3k41i5euo3t1vonvtgte3lj', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 13:32:24', NULL, 1),
(40, 1, 'il9148u40fqokvli9ql7em81bu', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 13:35:37', NULL, 1),
(41, 1, 'b9en5t8fs8adniapv1bi3i9p0i', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 13:41:35', NULL, 1),
(42, 1, 'mpfbe555oj7qrugkr592p2r49t', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 13:43:05', NULL, 1),
(43, 1, 'aih394ja4g4nnhmbgd9g3jqeqg', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 13:49:38', NULL, 1),
(44, 1, '6kh7apo798a2ei7nkt8jmog4lj', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 13:57:30', NULL, 1),
(45, 1, '7u6us3dog3ht7l9uf3ejqb29a3', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 14:05:59', NULL, 1),
(46, 1, 'dmjnq4eqdkp99f45p1d1dsj32b', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 14:21:05', NULL, 1),
(47, 1, 'oo8qj7lduj19vujmj26512ulej', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 14:25:14', NULL, 1),
(48, 1, 'a4numr9ikflbqjqghgn5ptfgu3', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 14:31:05', NULL, 1),
(49, 1, 'mjpa9vkhrbf4qq94pr45la6ltg', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-26 14:36:44', NULL, 1),
(50, 1, '67b2giblcnag7he8rotpirshlm', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 01:32:56', NULL, 1),
(51, 1, 'uofoqsa0fvomrq1ghtsnmv52kp', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 01:39:47', NULL, 1),
(52, 1, '23057nb23nvncc8ke1pq5pl0be', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 01:46:18', NULL, 1),
(53, 1, 'lbfefkk5b76itipf0dq5anrmvo', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 01:49:55', NULL, 1),
(54, 1, 'b0gfbnuluio8r7gc8i4fdcjm2m', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 01:56:42', NULL, 1),
(55, 1, 'qal2qnjs8939lm5049n9d440si', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 01:59:23', NULL, 1),
(56, 1, 'q6d6ufq7ek26n2kpcvh7201gs6', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 02:04:24', NULL, 1),
(57, 1, '81n4dh9vrqac4av81f55539ros', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 02:06:25', NULL, 1),
(58, 1, 'qqmp98tcqpdkajhjmih4t4jame', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 02:13:10', NULL, 1),
(59, 1, 'suhjlev7h3b09uf61gdlhus3sc', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 02:17:50', NULL, 1),
(60, 1, '2ts613pdr04r2jed9gs00ki5fv', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 02:22:44', NULL, 1),
(61, 1, 'gv3dquv0fp5dmor34sstm5t0b6', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 02:28:18', NULL, 1),
(62, 1, 'ej2ubbnr27r3tumeuqnfbqgekl', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 02:35:30', NULL, 1),
(63, 1, 'f6vejsf5mv50i080u6p0hj8ptb', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 02:38:03', NULL, 1),
(64, 1, 'rhampmhmk0lmrv5ooremgfocn9', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 02:43:52', NULL, 1),
(65, 1, '8phprl0u5pbkpefm2n0hr0icle', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 08:05:03', NULL, 1),
(66, 1, '16jn54os1ubvp2hbf14au1uaeu', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 08:54:25', NULL, 1),
(67, 1, 'ihsp57hbirab524beha2109lkk', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 09:02:33', NULL, 1),
(68, 2, 'murs00l0gou3ks4r2alter51nf', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 09:44:31', NULL, 1),
(69, 2, 'c5tnnm1u1nbuhidmt0t706p0e4', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 10:24:36', NULL, 1),
(70, 1, 'f0r0m5qsdj1m2s57t3oe9ne8l4', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'bdb776578b42d74da5a6d31da56ebdb19036a00d7a5edc3e7bd35bfbf5480255', 'Philippines', 'Unknown', '2025-11-27 11:10:57', NULL, 1),
(71, 1, 'rlqttugu4depo4kh2884dj67b9', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'a5d73d67ebbe65eb4cc4943e2245adb47700343d1081c7601df05814784fe5bd', 'Philippines', 'Unknown', '2025-11-27 13:55:52', NULL, 1),
(72, 5, 'c1obrdstp177ubvutksno97ait', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'a5d73d67ebbe65eb4cc4943e2245adb47700343d1081c7601df05814784fe5bd', 'Philippines', 'Unknown', '2025-11-27 14:10:11', NULL, 1),
(73, 5, 't9c1k428l6kmrrpbuphgqa9d6k', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'f3cad0413ab306a5904cf413989359f091c5b821f4e53f6e48fa0a773e2b15a0', 'Philippines', 'Unknown', '2025-11-28 00:25:25', NULL, 1),
(74, 5, 'qj4q7pnkskstkjndhfi23qp6dp', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '8dd893d80a3777942da8fa7bbe9ba82a985e1d1dd2d57212af2de9c386f59936', 'Philippines', 'Unknown', '2025-11-28 00:44:45', NULL, 1),
(75, 5, 'rjcqp3et8rmd6u4cesd41h01qc', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '8dd893d80a3777942da8fa7bbe9ba82a985e1d1dd2d57212af2de9c386f59936', 'Philippines', 'Unknown', '2025-11-28 05:07:44', NULL, 1),
(76, 5, '6hilqrh16qkf4sooedchkoom25', '112.202.255.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '8dd893d80a3777942da8fa7bbe9ba82a985e1d1dd2d57212af2de9c386f59936', 'Philippines', 'Unknown', '2025-11-28 05:59:54', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Recipient user ID',
  `type` enum('info','warning','danger','success') NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'grn, po, product, etc',
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID of related record',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `action_required` tinyint(1) NOT NULL DEFAULT 0,
  `action_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `specifications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specifications`)),
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reorder_level` int(11) NOT NULL DEFAULT 10,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `image_url` varchar(500) DEFAULT NULL,
  `warranty_months` int(11) NOT NULL DEFAULT 12,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(500) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `review_text` text NOT NULL,
  `is_verified_purchase` tinyint(1) NOT NULL DEFAULT 0,
  `helpful_votes` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `moderated_by` int(11) DEFAULT NULL,
  `moderated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percentage','fixed','free_shipping') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `minimum_order_amount` decimal(10,2) DEFAULT NULL,
  `applicable_products` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`applicable_products`)),
  `applicable_categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`applicable_categories`)),
  `usage_limit` int(11) NOT NULL DEFAULT 0,
  `usage_count` int(11) NOT NULL DEFAULT 0,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('draft','pending_supplier','approved','rejected','ordered','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_ordered` int(11) NOT NULL,
  `quantity_received` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) GENERATED ALWAYS AS (`quantity_ordered` * `unit_cost`) STORED,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `review_helpful_votes` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `vote_type` enum('helpful','not_helpful') NOT NULL DEFAULT 'helpful',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','card','bank_transfer','digital_wallet') NOT NULL,
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `sale_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `category`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 'maintenance_mode', 'false', 'Enable/disable maintenance mode', 'shop', 0, '2025-11-26 07:28:50', '2025-11-27 11:11:16'),
(2, 'inactivity_timeout', '0', NULL, 'general', 0, '2025-11-27 02:04:42', '2025-11-27 09:02:44');

-- --------------------------------------------------------

--
-- Table structure for table `shopping_cart`
--

CREATE TABLE `shopping_cart` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shopping_cart`
--

INSERT INTO `shopping_cart` (`id`, `customer_id`, `session_id`, `product_id`, `quantity`, `created_at`, `updated_at`) VALUES
(6, 2, NULL, 2, 1, '2025-11-27 13:54:04', '2025-11-27 13:54:04'),
(5, 2, NULL, 1, 1, '2025-11-27 13:54:02', '2025-11-27 13:54:02');

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

CREATE TABLE `stock_adjustments` (
  `id` int(11) NOT NULL,
  `adjustment_number` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `adjustment_type` enum('add','remove','recount') NOT NULL,
  `quantity_before` int(11) NOT NULL,
  `quantity_adjusted` int(11) NOT NULL,
  `quantity_after` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `adjustment_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('sale','purchase','adjustment','return','transfer','customer_order','customer_return','supplier_return') NOT NULL,
  `quantity` int(11) NOT NULL,
  `quantity_before` int(11) NOT NULL,
  `quantity_after` int(11) NOT NULL,
  `reference_type` enum('SALE','PURCHASE_ORDER','GRN','CUSTOMER_ORDER','CUSTOMER_RETURN','ADJUSTMENT') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(22, 2, 'purchase', 8, 20, 28, 'GRN', 12, 3, 'GRN: GRN-2025-00011', '2025-11-26 10:09:04'),
(23, 3, 'sale', -1, 3, 2, 'CUSTOMER_ORDER', 7, 1, 'Customer Order - NVIDIA GeForce RTX 4090 24GB', '2025-11-27 13:50:02'),
(24, 4, 'sale', -1, 6, 5, 'CUSTOMER_ORDER', 7, 1, 'Customer Order - AMD Radeon RX 7900 XTX 24GB', '2025-11-27 13:50:02'),
(25, 3, 'customer_order', 1, 2, 3, 'CUSTOMER_ORDER', 6, NULL, 'Order cancellation ORD-2025-000005', '2025-11-28 05:14:58'),
(26, 4, 'customer_order', 1, 5, 6, 'CUSTOMER_ORDER', 6, NULL, 'Order cancellation ORD-2025-000005', '2025-11-28 05:14:58'),
(27, 3, 'customer_order', 1, 3, 4, 'CUSTOMER_ORDER', 5, NULL, 'Order cancellation ORD-2025-000004', '2025-11-28 05:15:10'),
(28, 4, 'customer_order', 1, 6, 7, 'CUSTOMER_ORDER', 5, NULL, 'Order cancellation ORD-2025-000004', '2025-11-28 05:15:10'),
(29, 3, 'customer_order', 1, 4, 5, 'CUSTOMER_ORDER', 4, NULL, 'Order cancellation ORD-2025-000003', '2025-11-28 05:15:18'),
(30, 4, 'customer_order', 1, 7, 8, 'CUSTOMER_ORDER', 4, NULL, 'Order cancellation ORD-2025-000003', '2025-11-28 05:15:18'),
(31, 3, 'customer_order', 1, 5, 6, 'CUSTOMER_ORDER', 3, NULL, 'Order cancellation ORD-2025-000002', '2025-11-28 05:15:26'),
(32, 4, 'customer_order', 1, 8, 9, 'CUSTOMER_ORDER', 3, NULL, 'Order cancellation ORD-2025-000002', '2025-11-28 05:15:26'),
(33, 3, 'customer_order', 1, 6, 7, 'CUSTOMER_ORDER', 2, NULL, 'Order cancellation ORD-2025-000001', '2025-11-28 05:15:35'),
(34, 4, 'customer_order', 1, 9, 10, 'CUSTOMER_ORDER', 2, NULL, 'Order cancellation ORD-2025-000001', '2025-11-28 05:15:35');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `supplier_code` varchar(50) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Philippines',
  `tax_id` varchar(50) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT 'Net 30',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `user_id`, `company_name`, `supplier_code`, `contact_person`, `phone`, `email`, `address`, `city`, `state`, `postal_code`, `country`, `tax_id`, `payment_terms`, `notes`, `created_at`, `updated_at`) VALUES
(5, 7, 'Shopee Co.s', 'SUP-00007', 'Shopee Co.', NULL, 'eckocatalan@gmail.com', '', '', '', '', 'Philippines', '', 'Net 30', 'Created by data integrity fix', '2025-11-26 04:28:47', '2025-11-26 08:14:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','inventory_manager','purchasing_officer','supplier','staff') NOT NULL DEFAULT 'staff',
  `full_name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `password_hash`, `role`, `full_name`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'catalan.jereckopaul@gmail.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 1, '2025-11-27 13:55:52', '2025-11-12 06:02:50', '2025-11-27 13:55:52'),
(2, 'karldc', 'johnlukepolancos4@gmail.com', NULL, '$2y$10$ie/fOh3oSAJz75n5r884PuvpP9dFhLfVDkUFFXz8qXMx/b9ed2p3C', 'inventory_manager', 'Karl De Castro', 1, '2025-11-27 10:24:36', '2025-11-12 06:02:50', '2025-11-27 10:24:36'),
(3, 'kevinc', 'jhoncarlopaladin2@gmail.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'purchasing_officer', 'Kevin Calura', 1, '2025-11-26 20:03:41', '2025-11-12 06:02:50', '2025-11-26 12:03:41'),
(7, 'jerexc', 'eckocatalan@gmail.com', '09093333333', '$2y$10$OuF/t2z3P5vtKEyaVGHiO.5pPQF/ks2BdaJXxIFg885LsNNTM8l.6', 'supplier', 'Shopee Co.', 1, '2025-11-26 18:10:08', '2025-11-25 12:12:39', '2025-11-26 10:10:08'),
(5, 'alyssaf', 'jerexko90@gmail.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'Alyssa Flores', 1, '2025-11-28 05:59:54', '2025-11-12 06:02:50', '2025-11-28 05:59:54');

-- --------------------------------------------------------

--
-- Table structure for table `verification_codes`
--

CREATE TABLE `verification_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code` varchar(255) NOT NULL,
  `code_type` varchar(50) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `is_used` tinyint(4) DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL,
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 5,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `verification_codes`
--

INSERT INTO `verification_codes` (`id`, `user_id`, `code`, `code_type`, `expires_at`, `is_used`, `used_at`, `attempts`, `max_attempts`, `created_at`) VALUES
(5, 1, 'fe569600c2a1fd2613e4e747036b278384dbe76de3826810bf1a7d50fe6e63bf', 'password_reset', '2025-11-27 12:17:35', 0, NULL, 0, 5, '2025-11-27 11:17:35'),
(6, 1, '6242786cc9abd4ae3534c28b52a8ba5ae1a092a35e7ceb005c5efd78ce950e38', 'password_reset', '2025-11-27 12:22:08', 0, NULL, 0, 5, '2025-11-27 11:22:08'),
(7, 1, '63f32736e27a8797878566cf0d1b01b15cff79c3035b9b1018b168b0a39b7b6b', 'password_reset', '2025-11-27 12:25:31', 0, NULL, 0, 5, '2025-11-27 11:25:31'),
(8, 1, 'a401a4fcc80eb61d4d951917e60fb8661c0550ea9b8d465c93033a1071671aec', 'password_reset', '2025-11-27 12:30:41', 0, NULL, 0, 5, '2025-11-27 11:30:41'),
(9, 1, '71cd018863ad06f66a1748831490c59dc7415495e96867f07df2ba5cbfd85f55', 'password_reset', '2025-11-27 13:13:49', 0, NULL, 0, 5, '2025-11-27 12:13:49'),
(10, 1, 'cfb4300a9b98a7210df9d6c06a3e3f4596774dd8d420d1276135a6cab477f05f', 'password_reset', '2025-11-27 13:18:35', 0, NULL, 0, 5, '2025-11-27 12:18:35'),
(11, 1, 'ead70c267404f6c42c52c19028072b20476b4df543ae86d62fed31643468d31f', 'password_reset', '2025-11-27 13:20:24', 1, '2025-11-27 12:20:39', 0, 5, '2025-11-27 12:20:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `2fa_bypass_records`
--
ALTER TABLE `2fa_bypass_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_device_user` (`user_id`,`device_fingerprint`),
  ADD KEY `user_id_idx` (`user_id`),
  ADD KEY `device_fingerprint_idx` (`device_fingerprint`),
  ADD KEY `expires_at_idx` (`expires_at`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_entity_type` (`entity_type`),
  ADD KEY `idx_entity_id` (`entity_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `analytics_events`
--
ALTER TABLE `analytics_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_page_url` (`page_url`(250)),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_sort_order` (`sort_order`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_email_verified` (`email_verified`);

--
-- Indexes for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_address_type` (`address_type`),
  ADD KEY `idx_is_default` (`is_default`);

--
-- Indexes for table `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `shipping_address_id` (`shipping_address_id`),
  ADD KEY `billing_address_id` (`billing_address_id`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_order_date` (`order_date`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Indexes for table `customer_order_items`
--
ALTER TABLE `customer_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `customer_returns`
--
ALTER TABLE `customer_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_number` (`return_number`),
  ADD KEY `idx_return_number` (`return_number`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `customer_return_items`
--
ALTER TABLE `customer_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_return_id` (`return_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `customer_wishlists`
--
ALTER TABLE `customer_wishlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer_product` (`customer_id`,`product_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `goods_received_notes`
--
ALTER TABLE `goods_received_notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grn_number` (`grn_number`),
  ADD KEY `idx_grn_number` (`grn_number`),
  ADD KEY `idx_po_id` (`po_id`),
  ADD KEY `idx_received_by` (`received_by`),
  ADD KEY `idx_inspection_status` (`inspection_status`),
  ADD KEY `idx_received_date` (`received_date`);

--
-- Indexes for table `grn_items`
--
ALTER TABLE `grn_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grn_id` (`grn_id`),
  ADD KEY `idx_po_item_id` (`po_item_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_condition_status` (`condition_status`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_id` (`product_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_quantity_available` (`quantity_available`),
  ADD KEY `idx_warehouse_location` (`warehouse_location`(250));

--
-- Indexes for table `login_sessions`
--
ALTER TABLE `login_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_device_fingerprint` (`device_fingerprint`),
  ADD KEY `idx_login_time` (`login_time`),
  ADD KEY `idx_session_id` (`session_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `idx_sku` (`sku`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_brand` (`brand`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_selling_price` (`selling_price`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_sort_order` (`sort_order`),
  ADD KEY `idx_is_primary` (`is_primary`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `moderated_by` (`moderated_by`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_discount_type` (`discount_type`),
  ADD KEY `idx_start_date` (`start_date`),
  ADD KEY `idx_end_date` (`end_date`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_po_number` (`po_number`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_order_date` (`order_date`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po_id` (`po_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `review_helpful_votes`
--
ALTER TABLE `review_helpful_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review_customer` (`review_id`,`customer_id`),
  ADD KEY `idx_review_id` (`review_id`),
  ADD KEY `idx_customer_id` (`customer_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `idx_invoice_number` (`invoice_number`),
  ADD KEY `idx_cashier_id` (`cashier_id`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_sale_date` (`sale_date`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale_id` (`sale_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_is_system` (`is_system`);

--
-- Indexes for table `shopping_cart`
--
ALTER TABLE `shopping_cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer_product` (`customer_id`,`product_id`),
  ADD UNIQUE KEY `unique_session_product` (`session_id`,`product_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `adjustment_number` (`adjustment_number`),
  ADD KEY `idx_adjustment_number` (`adjustment_number`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_adjustment_type` (`adjustment_type`),
  ADD KEY `idx_performed_by` (`performed_by`),
  ADD KEY `idx_adjustment_date` (`adjustment_date`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_movement_type` (`movement_type`),
  ADD KEY `idx_reference_type` (`reference_type`),
  ADD KEY `idx_reference_id` (`reference_id`),
  ADD KEY `idx_performed_by` (`performed_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_supplier_code` (`supplier_code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `verification_codes`
--
ALTER TABLE `verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_type` (`user_id`,`code_type`),
  ADD KEY `idx_code` (`code`(250)),
  ADD KEY `idx_expires` (`expires_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `2fa_bypass_records`
--
ALTER TABLE `2fa_bypass_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=344;

--
-- AUTO_INCREMENT for table `analytics_events`
--
ALTER TABLE `analytics_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=199;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `customer_orders`
--
ALTER TABLE `customer_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `customer_order_items`
--
ALTER TABLE `customer_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `customer_returns`
--
ALTER TABLE `customer_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_return_items`
--
ALTER TABLE `customer_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_wishlists`
--
ALTER TABLE `customer_wishlists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `goods_received_notes`
--
ALTER TABLE `goods_received_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `grn_items`
--
ALTER TABLE `grn_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `login_sessions`
--
ALTER TABLE `login_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `review_helpful_votes`
--
ALTER TABLE `review_helpful_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `shopping_cart`
--
ALTER TABLE `shopping_cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `verification_codes`
--
ALTER TABLE `verification_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
