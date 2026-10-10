-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 18, 2025 at 04:31 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `iot_streetlights`
--

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `alert_type` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `sent_to` varchar(25) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `device_id` varchar(100) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `phone_number` varchar(25) DEFAULT NULL,
  `last_alert` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `street_light_data`
--

CREATE TABLE `street_light_data` (
  `id` int(11) NOT NULL,
  `device_id` varchar(100) NOT NULL,
  `current` float DEFAULT NULL,
  `brightness` float DEFAULT NULL,
  `status` enum('ON','OFF','FAIL') DEFAULT 'OFF',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `street_light_data`
--

INSERT INTO `street_light_data` (`id`, `device_id`, `current`, `brightness`, `status`, `timestamp`) VALUES
(2, 'STL002', 1.1, 5, '', '2025-10-01 06:05:00'),
(3, 'STL003', 0, 0, 'OFF', '2025-10-01 06:10:00'),
(4, 'STL004', 0.8, 75, 'ON', '2025-10-01 06:15:00'),
(5, 'STL005', 1, 18, '', '2025-10-01 06:20:00'),
(6, 'STL006', 1.3, 90, 'ON', '2025-10-01 06:25:00'),
(7, 'STL007', 0, 0, 'OFF', '2025-10-01 06:30:00'),
(8, 'STL008', 1.1, 50, 'ON', '2025-10-01 06:35:00'),
(9, 'STL009', 0.9, 15, '', '2025-10-01 06:40:00'),
(10, 'STL010', 1.2, 85, 'ON', '2025-10-01 06:45:00'),
(40, 'SL001', -0.12, 233, 'OFF', '2025-10-18 13:31:44'),
(41, 'SL001', -0.23, 230, 'OFF', '2025-10-18 13:31:52'),
(42, 'SL001', -0.3, 222, 'OFF', '2025-10-18 13:32:01'),
(43, 'SL001', 0, 228, '', '2025-10-18 13:32:09'),
(44, 'SL001', 0, 202, '', '2025-10-18 13:32:23'),
(45, 'SL001', -0.23, 190, 'OFF', '2025-10-18 13:32:35'),
(46, 'SL001', 0, 186, '', '2025-10-18 13:32:44'),
(47, 'SL001', -0.23, 189, 'OFF', '2025-10-18 13:32:56'),
(48, 'SL001', 0.12, 192, 'ON', '2025-10-18 13:33:04'),
(49, 'SL001', -0.23, 216, 'OFF', '2025-10-18 13:33:13'),
(50, 'SL001', 0, 218, '', '2025-10-18 13:33:22'),
(51, 'SL001', 0, 206, '', '2025-10-18 13:33:35'),
(52, 'SL001', 0, 197, '', '2025-10-18 13:33:47'),
(53, 'SL001', 0.32, 199, 'ON', '2025-10-18 13:34:00'),
(54, 'SL001', -0.09, 188, 'OFF', '2025-10-18 13:34:09'),
(55, 'SL001', -0.15, 186, 'OFF', '2025-10-18 13:34:18'),
(56, 'SL001', 0.14, 188, 'ON', '2025-10-18 13:34:31'),
(57, 'SL001', -0.12, 183, 'OFF', '2025-10-18 13:34:40'),
(58, 'SL001', 0.45, 194, 'ON', '2025-10-18 13:34:48'),
(59, 'SL001', -0.23, 207, 'OFF', '2025-10-18 13:34:57'),
(60, 'SL001', -0.23, 230, 'OFF', '2025-10-18 13:35:05'),
(61, 'SL001', 0, 214, '', '2025-10-18 13:39:46'),
(62, 'SL001', 0.36, 221, 'ON', '2025-10-18 13:39:59'),
(63, 'SL001', -0.33, 45, 'OFF', '2025-10-18 13:40:08'),
(64, 'SL001', 0.18, 34, 'ON', '2025-10-18 13:40:17'),
(65, 'SL001', 0, 34, '', '2025-10-18 13:40:26'),
(66, 'SL001', 0.22, 230, 'ON', '2025-10-18 13:40:39'),
(67, 'SL001', -0.26, 217, 'OFF', '2025-10-18 13:40:48'),
(68, 'SL001', -0.19, 255, 'OFF', '2025-10-18 13:40:56'),
(69, 'SL001', -0.19, 255, 'OFF', '2025-10-18 13:41:05'),
(70, 'SL001', -0.2, 22, 'OFF', '2025-10-18 13:41:14'),
(71, 'SL001', -4.21, 0, 'OFF', '2025-10-18 14:05:12'),
(72, 'SL001', -4.21, 0, 'OFF', '2025-10-18 14:05:21'),
(73, 'SL001', -0.35, 255, 'OFF', '2025-10-18 14:05:29'),
(74, 'SL001', -4.21, 2, 'OFF', '2025-10-18 14:05:38'),
(75, 'SL001', -0.65, 146, 'OFF', '2025-10-18 14:05:46'),
(76, 'SL001', -3.44, 0, 'OFF', '2025-10-18 14:05:55'),
(77, 'SL001', 0.53, 255, 'ON', '2025-10-18 14:06:03'),
(78, 'SL001', -4.21, 83, 'OFF', '2025-10-18 14:06:12'),
(79, 'SL001', -4.21, 9, 'OFF', '2025-10-18 14:06:21'),
(80, 'SL001', -3.44, 20, 'OFF', '2025-10-18 14:06:29'),
(81, 'SL001', -1.32, 255, 'OFF', '2025-10-18 14:06:38'),
(82, 'SL001', -4.21, 0, 'OFF', '2025-10-18 14:06:46'),
(83, 'SL001', 1.1, 46, 'ON', '2025-10-18 14:06:55'),
(84, 'SL001', -4.21, 255, 'OFF', '2025-10-18 14:07:03'),
(85, 'SL001', 0.55, 126, 'ON', '2025-10-18 14:07:12'),
(86, 'SL001', 0, 174, '', '2025-10-18 14:07:21'),
(87, 'SL001', -0.64, 161, 'OFF', '2025-10-18 14:07:34'),
(88, 'SL001', -0.1, 175, 'OFF', '2025-10-18 14:07:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(2, 'NIYIGENA', 'niyclem2020@gmail.com', '$2y$10$y4AZyCWJLns7OUiU1CxmsuP2GUN8lxgJNQHWt5z4d3wkga0Q/p4VG', 'user', '2025-10-03 05:31:46'),
(6, 'IRADUKUNDA', 'dskgroupltd2020@gmail.com', '$2y$10$0CDz00SaZoxVNHbXvGs1E.NQ1LeuJ75.avChO4oxDluExRPUKNIqK', 'user', '2025-10-11 13:54:36'),
(7, 'Ncuti Honore', 'honoreken2000@gmail.com', '$2y$10$myKb5Cj6IozMQEqGuNUhq.o2TQeTqlQOQpq.FoG54UdkAcAY.DFPW', 'user', '2025-10-11 16:19:18'),
(9, 'kiriku', 'niclem2020@gmail.com', '$2y$10$MCG4UIcX1fXGBegkqVhLr.t8IyxeGHMc7IaxtzZSZIddsBryUGz5W', 'user', '2025-10-15 15:18:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_id` (`device_id`);

--
-- Indexes for table `street_light_data`
--
ALTER TABLE `street_light_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `timestamp` (`timestamp`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `street_light_data`
--
ALTER TABLE `street_light_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
