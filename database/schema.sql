-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 31, 2025 at 12:18 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `urban_farming`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_recommendations`
--

CREATE TABLE `ai_recommendations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `recommendation_type` enum('drone_type','timing','irrigation','pest_control') NOT NULL,
  `recommendation_text` text NOT NULL,
  `sensor_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sensor_data`)),
  `is_followed` tinyint(1) DEFAULT 0,
  `green_points_earned` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ai_recommendations`
--

INSERT INTO `ai_recommendations` (`id`, `user_id`, `recommendation_type`, `recommendation_text`, `sensor_data`, `is_followed`, `green_points_earned`, `created_at`) VALUES
(1, 2, 'drone_type', 'Drone request submitted for Pest_control_spraying at bashundhara. Recommended time: Sep 1, 2025 12:30 AM. Following AI recommendations earns Green Points!', NULL, 0, 0, '2025-08-31 09:45:25');

-- --------------------------------------------------------

--
-- Table structure for table `drones`
--

CREATE TABLE `drones` (
  `id` int(11) NOT NULL,
  `drone_type` enum('survey','spraying','monitoring','biological') NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('available','assigned','en_route','active','completed','maintenance') DEFAULT 'available',
  `battery_level` int(11) DEFAULT 100,
  `last_maintenance` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drone_requests`
--

CREATE TABLE `drone_requests` (
  `id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `farm_id` int(11) NOT NULL,
  `drone_id` int(11) DEFAULT NULL,
  `purpose` enum('survey','pest_control_spraying','pest_control_monitoring','pest_control_biological') NOT NULL,
  `location` varchar(255) NOT NULL,
  `preferred_time` datetime NOT NULL,
  `status` enum('pending','approved','rejected','en_route','active','completed') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `result_report` text DEFAULT NULL,
  `green_points_earned` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drone_requests`
--

INSERT INTO `drone_requests` (`id`, `farmer_id`, `farm_id`, `drone_id`, `purpose`, `location`, `preferred_time`, `status`, `approved_by`, `approved_at`, `notes`, `result_report`, `green_points_earned`, `created_at`, `updated_at`) VALUES
(1, 2, 1, NULL, 'pest_control_spraying', 'bashundhara', '2025-09-01 00:30:00', 'pending', NULL, NULL, NULL, NULL, 0, '2025-08-31 09:45:25', '2025-08-31 09:45:25');

-- --------------------------------------------------------

--
-- Table structure for table `drone_results`
--

CREATE TABLE `drone_results` (
  `id` int(11) NOT NULL,
  `drone_request_id` int(11) NOT NULL,
  `drone_id` int(11) NOT NULL,
  `operation_type` varchar(100) NOT NULL,
  `area_covered` decimal(10,2) NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `efficiency_score` decimal(5,2) NOT NULL,
  `coverage_percentage` decimal(5,2) NOT NULL,
  `issues_encountered` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `data_collected` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_collected`)),
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `farms`
--

CREATE TABLE `farms` (
  `id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `farm_type` enum('vegetable','fruit','grain','mixed') NOT NULL,
  `crops` text DEFAULT NULL,
  `soil_type` varchar(50) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `farms`
--

INSERT INTO `farms` (`id`, `farmer_id`, `name`, `location`, `farm_type`, `crops`, `soil_type`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 'Sunrise Valley Farm', 'bashundhara', 'fruit', 'm', 'Sandy', 'approved', '', '2025-08-31 09:44:19', '2025-08-31 09:45:43'),
(2, 2, 'Sunset Valley Farm m,', 'bashundhara', 'mixed', 'mm', 'Sandy', 'approved', '', '2025-08-31 10:14:09', '2025-08-31 10:14:39');

-- --------------------------------------------------------

--
-- Table structure for table `green_points_transactions`
--

CREATE TABLE `green_points_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('earned','spent','bonus') NOT NULL,
  `amount` int(11) NOT NULL,
  `description` text NOT NULL,
  `related_entity_type` enum('drone_request','seed_sale','iot_optimization','ai_recommendation') NOT NULL,
  `related_entity_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `green_points_transactions`
--

INSERT INTO `green_points_transactions` (`id`, `user_id`, `transaction_type`, `amount`, `description`, `related_entity_type`, `related_entity_id`, `created_at`) VALUES
(1, 2, 'earned', 5, 'Drone request following AI recommendations', 'drone_request', 1, '2025-08-31 09:45:25');

-- --------------------------------------------------------

--
-- Table structure for table `iot_devices`
--

CREATE TABLE `iot_devices` (
  `id` int(11) NOT NULL,
  `farm_id` int(11) NOT NULL,
  `device_type` enum('soil_moisture','temperature','humidity','light','water_flow','pump','fan','light_control') NOT NULL,
  `device_name` varchar(100) NOT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `last_reading` decimal(10,2) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `iot_devices`
--

INSERT INTO `iot_devices` (`id`, `farm_id`, `device_type`, `device_name`, `status`, `last_reading`, `last_updated`) VALUES
(1, 1, 'soil_moisture', 'Soil moisture Sensor', 'active', NULL, '2025-08-31 09:44:19'),
(2, 1, 'temperature', 'Temperature Sensor', 'active', NULL, '2025-08-31 09:44:19'),
(3, 1, 'humidity', 'Humidity Sensor', 'active', NULL, '2025-08-31 09:44:19'),
(4, 1, 'light', 'Light Sensor', 'active', NULL, '2025-08-31 09:44:19'),
(5, 1, 'water_flow', 'Water flow Sensor', 'active', NULL, '2025-08-31 09:44:19'),
(6, 1, 'pump', 'Pump Sensor', 'active', NULL, '2025-08-31 09:44:19'),
(7, 1, 'fan', 'Fan Sensor', 'active', NULL, '2025-08-31 09:44:19'),
(8, 1, 'light_control', 'Light control Sensor', 'active', NULL, '2025-08-31 09:44:19'),
(9, 2, 'soil_moisture', 'Soil moisture Sensor', 'active', NULL, '2025-08-31 10:14:09'),
(10, 2, 'temperature', 'Temperature Sensor', 'active', NULL, '2025-08-31 10:14:09'),
(11, 2, 'humidity', 'Humidity Sensor', 'active', NULL, '2025-08-31 10:14:09'),
(12, 2, 'light', 'Light Sensor', 'active', NULL, '2025-08-31 10:14:09'),
(13, 2, 'water_flow', 'Water flow Sensor', 'active', NULL, '2025-08-31 10:14:09'),
(14, 2, 'pump', 'Pump Sensor', 'active', NULL, '2025-08-31 10:14:09'),
(15, 2, 'fan', 'Fan Sensor', 'active', NULL, '2025-08-31 10:14:09'),
(16, 2, 'light_control', 'Light control Sensor', 'active', NULL, '2025-08-31 10:14:09');

-- --------------------------------------------------------

--
-- Table structure for table `iot_readings`
--

CREATE TABLE `iot_readings` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `reading_value` decimal(10,2) NOT NULL,
  `reading_type` varchar(50) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `category` enum('farm_request','drone_request','approval','system','marketplace') DEFAULT 'system',
  `related_entity_type` enum('farm','drone_request','seed_listing','system') DEFAULT 'system',
  `related_entity_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `category`, `related_entity_type`, `related_entity_id`, `is_read`, `created_at`) VALUES
(1, 1, 'New Request Submitted by Farmer', 'A farmer has submitted a new farm request \'Sunrise Valley Farm\' that is pending planner approval.', 'info', 'farm_request', 'farm', 1, 1, '2025-08-31 09:44:19'),
(2, 2, 'Your Farm Request Has Been Approved', 'Your farm request \'Sunrise Valley Farm\' has been approved by the planner.', 'success', 'approval', 'farm', 1, 1, '2025-08-31 09:45:01'),
(3, 1, 'Planner Approved a Farm Request', 'A planner has approved the farm request \'Sunrise Valley Farm\'.', 'info', 'approval', 'farm', 1, 1, '2025-08-31 09:45:01'),
(4, 3, 'New Drone Service Request', 'A new drone service request for pest_control_spraying at bashundhara requires your approval.', 'info', 'drone_request', 'drone_request', 1, 1, '2025-08-31 09:45:25'),
(5, 1, 'New Drone Request Submitted', 'A farmer has submitted a new drone service request for pest_control_spraying that is pending approval.', 'info', 'drone_request', 'drone_request', 1, 1, '2025-08-31 09:45:25'),
(6, 2, 'Your Farm Request Has Been Approved', 'Your farm request \'Sunrise Valley Farm\' has been approved by the planner.', 'success', 'approval', 'farm', 1, 1, '2025-08-31 09:45:43'),
(7, 1, 'Planner Approved a Farm Request', 'A planner has approved the farm request \'Sunrise Valley Farm\'.', 'info', 'approval', 'farm', 1, 1, '2025-08-31 09:45:43'),
(8, 3, 'New Farm Request Received', 'A new farm request \'Sunset Valley Farm m,\' has been submitted and requires your approval.', 'info', 'farm_request', 'farm', 2, 1, '2025-08-31 10:14:09'),
(9, 1, 'New Request Submitted by Farmer', 'A farmer has submitted a new farm request \'Sunset Valley Farm m,\' that is pending planner approval.', 'info', 'farm_request', 'farm', 2, 0, '2025-08-31 10:14:09'),
(10, 2, 'Your Farm Request Has Been Approved', 'Your farm request \'Sunset Valley Farm m,\' has been approved by the planner.', 'success', 'approval', 'farm', 2, 0, '2025-08-31 10:14:39'),
(11, 1, 'Planner Approved a Farm Request', 'A planner has approved the farm request \'Sunset Valley Farm m,\'.', 'info', 'approval', 'farm', 2, 0, '2025-08-31 10:14:39');

-- --------------------------------------------------------

--
-- Table structure for table `seed_listings`
--

CREATE TABLE `seed_listings` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `seed_type` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `location` varchar(255) NOT NULL,
  `is_organic` tinyint(1) DEFAULT 0,
  `is_non_gmo` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `status` enum('pending','available','sold') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `buyer_id` int(11) DEFAULT NULL,
  `green_points_earned_seller` int(11) DEFAULT 0,
  `green_points_earned_buyer` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seed_listings`
--

INSERT INTO `seed_listings` (`id`, `seller_id`, `seed_type`, `quantity`, `price`, `location`, `is_organic`, `is_non_gmo`, `description`, `status`, `approved_by`, `approved_at`, `notes`, `buyer_id`, `green_points_earned_seller`, `green_points_earned_buyer`, `created_at`, `updated_at`) VALUES
(3, 2, 'wheat', 100, 5.00, 'bashundhara', 1, 0, 'mm', 'available', 1, '2025-08-31 10:12:40', 'Approved by admin', NULL, 0, 0, '2025-08-31 10:11:15', '2025-08-31 10:12:40');

-- --------------------------------------------------------

--
-- Table structure for table `seed_sales`
--

CREATE TABLE `seed_sales` (
  `id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `green_points_earned_buyer` int(11) DEFAULT 0,
  `green_points_earned_seller` int(11) DEFAULT 0,
  `status` enum('completed','pending','cancelled') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, NULL, 'user_logout', 'User logged out (user_id not found)', '::1', '2025-08-31 09:34:51'),
(2, 2, 'user_registration', 'New user registered as farmer', '::1', '2025-08-31 09:35:07'),
(3, 2, 'user_login', 'User logged in successfully', '::1', '2025-08-31 09:35:14'),
(4, 2, 'farm_creation', 'Created farm: Sunrise Valley Farm', '::1', '2025-08-31 09:44:19'),
(5, 2, 'user_logout', 'User logged out', '::1', '2025-08-31 09:44:37'),
(6, 3, 'user_registration', 'New user registered as planner', '::1', '2025-08-31 09:44:46'),
(7, 3, 'user_login', 'User logged in successfully', '::1', '2025-08-31 09:44:50'),
(8, 3, 'farm_approved', 'Farm ID: 1 approved', NULL, '2025-08-31 09:45:01'),
(9, 2, 'drone_request', 'Submitted drone request for pest_control_spraying', '::1', '2025-08-31 09:45:25'),
(10, 3, 'farm_approved', 'Farm ID: 1 approved', NULL, '2025-08-31 09:45:43'),
(11, 3, 'user_login', 'User logged in successfully', '::1', '2025-08-31 10:08:17'),
(12, 2, 'user_login', 'User logged in successfully', '::1', '2025-08-31 10:10:30'),
(13, 2, 'seed_listing_created', 'Seed type: wheat, Quantity: 100', NULL, '2025-08-31 10:11:15'),
(14, 3, 'user_login', 'User logged in successfully', '::1', '2025-08-31 10:11:24'),
(15, 1, 'seed_listing_approved', 'Listing ID: 3', NULL, '2025-08-31 10:11:55'),
(16, 1, 'seed_listing_approved', 'Listing ID: 3', NULL, '2025-08-31 10:12:27'),
(17, 1, 'seed_listing_approved', 'Listing ID: 3', NULL, '2025-08-31 10:12:40'),
(18, 2, 'farm_creation', 'Created farm: Sunset Valley Farm m,', '::1', '2025-08-31 10:14:09'),
(19, 3, 'farm_approved', 'Farm ID: 2 approved', NULL, '2025-08-31 10:14:39'),
(20, 3, 'user_logout', 'User logged out', '::1', '2025-08-31 10:15:09'),
(21, 1, 'user_login', 'User logged in successfully', '::1', '2025-08-31 10:16:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('farmer','planner','admin') NOT NULL,
  `green_points` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `green_points`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@urbanfarming.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0, '2025-08-31 09:34:39', '2025-08-31 09:34:39'),
(2, 'farmer1', 'farmer1@gmail.com', '$2y$10$zN73J7B211.ClAeLyanm9.pnRTDe3kO.fJOq32dWonOnqyB7AifPG', 'farmer', 5, '2025-08-31 09:35:07', '2025-08-31 09:45:25'),
(3, 'planner1', 'planner1@gmail.com', '$2y$10$73zL1Cp49JhV.ErFBjW24OtSYQahhKONXwBqp3sSOD2aY1MyTpMHe', 'planner', 15, '2025-08-31 09:44:46', '2025-08-31 10:14:39');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_recommendations`
--
ALTER TABLE `ai_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `drones`
--
ALTER TABLE `drones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `drone_requests`
--
ALTER TABLE `drone_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `farmer_id` (`farmer_id`),
  ADD KEY `farm_id` (`farm_id`),
  ADD KEY `drone_id` (`drone_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `drone_results`
--
ALTER TABLE `drone_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `drone_id` (`drone_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_drone_request` (`drone_request_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `farms`
--
ALTER TABLE `farms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `farmer_id` (`farmer_id`);

--
-- Indexes for table `green_points_transactions`
--
ALTER TABLE `green_points_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `iot_devices`
--
ALTER TABLE `iot_devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `farm_id` (`farm_id`);

--
-- Indexes for table `iot_readings`
--
ALTER TABLE `iot_readings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `seed_listings`
--
ALTER TABLE `seed_listings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `seed_sales`
--
ALTER TABLE `seed_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `listing_id` (`listing_id`),
  ADD KEY `idx_buyer_id` (`buyer_id`),
  ADD KEY `idx_seller_id` (`seller_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_recommendations`
--
ALTER TABLE `ai_recommendations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `drones`
--
ALTER TABLE `drones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drone_requests`
--
ALTER TABLE `drone_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `drone_results`
--
ALTER TABLE `drone_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `farms`
--
ALTER TABLE `farms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `green_points_transactions`
--
ALTER TABLE `green_points_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `iot_devices`
--
ALTER TABLE `iot_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `iot_readings`
--
ALTER TABLE `iot_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `seed_listings`
--
ALTER TABLE `seed_listings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `seed_sales`
--
ALTER TABLE `seed_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_recommendations`
--
ALTER TABLE `ai_recommendations`
  ADD CONSTRAINT `ai_recommendations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `drone_requests`
--
ALTER TABLE `drone_requests`
  ADD CONSTRAINT `drone_requests_ibfk_1` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `drone_requests_ibfk_2` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `drone_requests_ibfk_3` FOREIGN KEY (`drone_id`) REFERENCES `drones` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `drone_requests_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `drone_results`
--
ALTER TABLE `drone_results`
  ADD CONSTRAINT `drone_results_ibfk_1` FOREIGN KEY (`drone_request_id`) REFERENCES `drone_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `drone_results_ibfk_2` FOREIGN KEY (`drone_id`) REFERENCES `drones` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `drone_results_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `farms`
--
ALTER TABLE `farms`
  ADD CONSTRAINT `farms_ibfk_1` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `green_points_transactions`
--
ALTER TABLE `green_points_transactions`
  ADD CONSTRAINT `green_points_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `iot_devices`
--
ALTER TABLE `iot_devices`
  ADD CONSTRAINT `iot_devices_ibfk_1` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `iot_readings`
--
ALTER TABLE `iot_readings`
  ADD CONSTRAINT `iot_readings_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `iot_devices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seed_listings`
--
ALTER TABLE `seed_listings`
  ADD CONSTRAINT `seed_listings_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seed_listings_ibfk_2` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `seed_listings_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `seed_sales`
--
ALTER TABLE `seed_sales`
  ADD CONSTRAINT `seed_sales_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `seed_listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seed_sales_ibfk_2` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seed_sales_ibfk_3` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
