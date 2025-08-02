-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 30, 2025 at 09:43 PM
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
-- Database: `sfb_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `attendees_count` int(11) NOT NULL,
  `purpose` text NOT NULL,
  `things_needed` text DEFAULT NULL,
  `additional_requirements` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled','completed') DEFAULT 'pending',
  `cancellation_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `facility_id`, `user_id`, `start_time`, `end_time`, `attendees_count`, `purpose`, `things_needed`, `additional_requirements`, `status`, `cancellation_reason`, `created_at`, `updated_at`) VALUES
(1, 1, 3, '2025-08-01 10:00:00', '2025-08-01 12:00:00', 25, 'Test Meeting - System Testing', NULL, NULL, 'approved', NULL, '2025-07-30 13:53:23', '2025-07-30 13:56:32'),
(2, 1, 3, '2025-07-31 14:00:00', '2025-07-31 16:00:00', 15, 'Student Council Meeting', NULL, NULL, 'approved', NULL, '2025-07-30 13:53:54', '2025-07-30 13:53:54'),
(3, 1, 3, '2025-08-01 09:00:00', '2025-08-01 11:00:00', 50, 'Academic Conference', NULL, NULL, 'cancelled', NULL, '2025-07-30 13:53:54', '2025-07-30 14:45:37'),
(4, 1, 3, '2025-07-29 13:00:00', '2025-07-29 15:00:00', 20, 'Faculty Workshop', NULL, NULL, 'completed', NULL, '2025-07-30 13:53:54', '2025-07-30 13:53:54'),
(5, 1, 3, '2025-08-02 13:00:00', '2025-08-02 17:00:00', 20, 'Meeting', NULL, NULL, 'approved', NULL, '2025-07-30 15:44:16', '2025-07-30 17:01:51'),
(6, 1, 3, '2025-08-04 23:00:00', '2025-08-05 01:00:00', 20, 'Meeting', NULL, NULL, 'approved', NULL, '2025-07-30 15:58:40', '2025-07-30 15:59:06'),
(7, 1, 3, '2025-08-04 07:00:00', '2025-08-04 09:00:00', 20, 'Meeting', NULL, NULL, 'approved', NULL, '2025-07-30 16:06:34', '2025-07-30 16:06:59'),
(8, 8, 3, '2025-08-06 12:00:00', '2025-08-06 14:00:00', 20, 'Test', NULL, NULL, 'approved', NULL, '2025-07-30 16:13:13', '2025-07-30 17:05:53'),
(9, 4, 3, '2025-08-07 12:00:00', '2025-08-07 14:30:00', 20, 'Meeting', NULL, NULL, 'approved', NULL, '2025-07-30 16:16:58', '2025-07-30 16:18:56'),
(10, 1, 3, '2025-08-01 10:00:00', '2025-08-01 12:00:00', 5, 'Test booking for approval testing', NULL, NULL, 'approved', NULL, '2025-07-30 16:24:59', '2025-07-30 16:25:40'),
(11, 2, 3, '2025-08-09 12:30:00', '2025-08-09 15:30:00', 20, 'Meeting', NULL, NULL, 'cancelled', NULL, '2025-07-30 16:30:14', '2025-07-30 16:57:50'),
(12, 1, 3, '2025-08-11 07:30:00', '2025-08-11 10:30:00', 10, 'Meeting', NULL, NULL, 'approved', NULL, '2025-07-30 16:39:18', '2025-07-30 16:39:32'),
(13, 1, 3, '2025-08-19 12:30:00', '2025-08-19 15:00:00', 20, 'Testing', NULL, NULL, 'approved', NULL, '2025-07-30 16:42:18', '2025-07-30 16:42:29'),
(14, 1, 3, '2025-08-12 12:45:00', '2025-08-12 16:45:00', 10, 'Testing', NULL, NULL, 'approved', NULL, '2025-07-30 16:45:57', '2025-07-30 16:46:12'),
(15, 1, 3, '2025-08-13 13:00:00', '2025-08-13 16:00:00', 10, 'Meeting', NULL, NULL, 'approved', NULL, '2025-07-30 16:49:33', '2025-07-30 16:49:42'),
(16, 3, 3, '2025-08-14 12:00:00', '2025-08-14 16:00:00', 700, 'Games', NULL, NULL, 'approved', NULL, '2025-07-30 16:56:00', '2025-07-30 16:56:13');

-- --------------------------------------------------------

--
-- Table structure for table `booking_materials`
--

CREATE TABLE `booking_materials` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(11) NOT NULL,
  `location` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `status` enum('available','maintenance','reserved') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `name`, `description`, `capacity`, `location`, `type`, `status`, `created_at`, `updated_at`) VALUES
(1, 'IT Lab', 'A space equipped with computers and related technology for educational, research, or practical purposes.', 40, 'St. Peter\'s College of Toril', 'laboratory', 'available', '2025-03-13 05:32:39', '2025-03-16 09:57:58'),
(2, 'Tech Lab', 'A space where technical projects are undertaken, often involving specialized tools and equipment.', 50, 'St. Peter\'s College of Toril', 'laboratory', 'available', '2025-03-16 09:52:11', '2025-03-16 09:57:25'),
(3, 'Gymnasium', 'A large, versatile space primarily designed for physical education, sports activities, and school events.', 800, 'St. Peter\'s College of Toril', 'gymnasium', 'available', '2025-03-16 09:57:19', '2025-03-16 09:57:51'),
(4, 'AVR-1', 'AVRs are designed to facilitate learning by providing access to a variety of audio and visual materials.', 100, 'St. Peter\'s College of Toril', 'auditorium', 'available', '2025-03-16 10:00:09', '2025-03-16 10:00:09'),
(5, 'AVR-2', 'AVRs are designed to facilitate learning by providing access to a variety of audio and visual materials.', 100, 'St. Peter\'s College of Toril', 'auditorium', 'available', '2025-03-16 10:00:44', '2025-03-30 11:52:38'),
(7, 'Science Lab', 'A controlled environment or facility for scientific research, experiments, and measurements.', 50, 'St. Peter\'s College of Toril', 'laboratory', 'available', '2025-03-26 01:12:49', '2025-03-30 12:01:29'),
(8, 'Computer Lab', 'A space equipped with computers, often networked, used for educational or research purposes.', 50, 'St. Peter\'s College of Toril', 'laboratory', 'available', '2025-03-30 12:03:05', '2025-03-30 12:03:25');

-- --------------------------------------------------------

--
-- Table structure for table `facility_materials`
--

CREATE TABLE `facility_materials` (
  `id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facility_materials`
--

INSERT INTO `facility_materials` (`id`, `facility_id`, `name`, `description`, `quantity`, `created_at`, `updated_at`) VALUES
(1, 1, 'Projector', 'An optical device that projects an image or video onto a screen or other surface.', 5, '2025-07-29 11:18:06', '2025-07-29 12:20:39'),
(2, 1, 'Projector', 'HD Projector for presentations', 2, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(3, 1, 'Microphone', 'Wireless microphone system', 1, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(4, 1, 'Whiteboard', 'Large whiteboard with markers', 1, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(5, 2, 'Projector', 'HD Projector for presentations', 1, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(6, 2, 'Sound System', 'Complete audio system with speakers', 1, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(7, 2, 'Chairs', 'Folding chairs for events', 30, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(8, 3, 'Projector', 'HD Projector for presentations', 1, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(9, 3, 'Microphone', 'Wireless microphone system', 2, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(10, 3, 'Tables', 'Portable tables for events', 5, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(11, 4, 'Computers', 'Desktop computers for lab use', 20, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(12, 4, 'Projector', 'HD Projector for presentations', 1, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(13, 4, 'Whiteboard', 'Large whiteboard with markers', 1, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(14, 5, 'Basketball Hoops', 'Portable basketball hoops', 2, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(15, 5, 'Volleyball Net', 'Volleyball net and poles', 1, '2025-07-29 12:18:56', '2025-07-29 12:18:56'),
(16, 5, 'Sound System', 'Audio system for events', 1, '2025-07-29 12:18:56', '2025-07-29 12:18:56');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` enum('booking_confirmation','booking_rejection','reminder','system') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `booking_id`, `message`, `type`, `is_read`, `created_at`) VALUES
(32, 3, 1, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 13:53:23'),
(33, 3, 2, 'Your booking has been created and is approved.', 'booking_confirmation', 0, '2025-07-30 13:53:54'),
(34, 3, 3, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 13:53:54'),
(35, 3, 4, 'Your booking has been created and is approved.', 'booking_confirmation', 0, '2025-07-30 13:53:54'),
(36, 3, 1, 'Your booking #1 for IT Lab has been rescheduled by an administrator. New schedule: Aug 01, 2025 10:00 AM to Aug 01, 2025 12:00 PM. Reason: Testing', 'system', 0, '2025-07-30 13:56:32'),
(37, 3, 5, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 15:44:16'),
(38, 3, 6, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 15:58:40'),
(39, 3, 7, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 16:06:34'),
(40, 3, 8, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 16:13:13'),
(41, 3, 9, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 16:16:58'),
(42, 3, 11, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 16:30:14'),
(43, 3, 12, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 16:39:18'),
(44, 3, 13, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 16:42:18'),
(45, 3, 14, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 16:45:57'),
(46, 3, 15, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 16:49:33'),
(47, 3, 16, 'Your booking request has been submitted and is pending approval.', 'booking_confirmation', 0, '2025-07-30 16:56:00'),
(48, 3, 5, 'Your booking #5 for IT Lab has been rescheduled by an administrator. New schedule: Aug 02, 2025 12:00 PM to Aug 02, 2025 04:00 PM. Reason: Change of Schedule', 'system', 0, '2025-07-30 16:59:04'),
(49, 3, 5, 'Your booking #5 for IT Lab has been rescheduled by an administrator. New schedule: Aug 02, 2025 01:00 PM to Aug 02, 2025 05:00 PM. Reason: Change of plan', 'system', 0, '2025-07-30 17:01:51'),
(50, 3, 8, 'Your booking #8 for Computer Lab has been rescheduled by an administrator. New schedule: Aug 06, 2025 12:00 PM to Aug 06, 2025 02:00 PM. Reason: Testing', 'system', 0, '2025-07-30 17:05:53');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `used`, `created_at`) VALUES
(1, 3, 'f4640cf548f9c6f89bfe7bbbc8653cf0c9ed50e1aae96d9759bb7c0725ca67d9', '2025-03-30 08:44:00', 0, '2025-03-30 05:44:00'),
(2, 3, '0077b926b0c8d3c970688a32a10a6bc4571accaa312e0b5d7147b801dfa4270a', '2025-03-30 08:48:29', 0, '2025-03-30 05:48:29'),
(3, 3, 'e1ef37f87ab8856e12874ebba84ca197b8e29d9564ae26a6f89cd0c56971fa78', '2025-03-30 08:48:35', 0, '2025-03-30 05:48:35'),
(4, 3, '535da1173a9277e381e13a4c60293f0b1c4297c29e5fdaef73def3efb178ad36', '2025-03-30 08:54:10', 0, '2025-03-30 05:54:10'),
(5, 3, '3c0d3112e3fda5830c728e427306d0d02d273dc5086432fe26f0c8273dc95041', '2025-07-29 13:40:51', 0, '2025-07-29 10:40:51'),
(6, 3, '298c30d720b69c00eeb7d92feb25936bfcbf389d444c73b3a5002e5ac63aeaec', '2025-07-29 15:42:36', 0, '2025-07-29 12:42:36'),
(9, 1, '99dfd6942ce12f7790149054265de954fd98433b59195f2b8c810d8a2b245b9b', '2025-07-29 15:46:30', 0, '2025-07-29 12:46:30'),
(10, 1, '7208f5f8d9826a19c7c895d0013f5b82dd9bddb1827369c18b211ec64f8484a4', '2025-07-29 21:47:03', 0, '2025-07-29 12:47:03'),
(12, 3, 'f1d34f70b10b904a252af7e8569fd74d6fbd90602f1879977f7e29e2defa8841', '2025-07-29 21:48:44', 1, '2025-07-29 12:48:44');

-- --------------------------------------------------------

--
-- Table structure for table `support_requests`
--

CREATE TABLE `support_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','in_progress','resolved','closed') DEFAULT 'pending',
  `admin_reply` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_requests`
--

INSERT INTO `support_requests` (`id`, `user_id`, `name`, `email`, `subject`, `message`, `status`, `admin_reply`, `created_at`, `updated_at`) VALUES
(1, 3, 'Raymart Dave Silvosa', 'raymartkuyah05@gmail.com', 'Booking Issues', 'Error booking', 'resolved', 'Pagpatudlo sng AI', '2025-07-30 19:21:52', '2025-07-30 19:36:33');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `action`, `details`, `created_at`) VALUES
(2, 'support_status_update', 'Support request ID 3 (Booking Issues) status updated from \'read\' to \'in_progress\'', '2025-07-30 19:16:09'),
(3, 'support_status_update', 'Support request ID 3 (Booking Issues) status updated from \'read\' to \'in_progress\'', '2025-07-30 19:19:17'),
(4, 'support_status_update', 'Support request ID 4 (Booking Issues) status updated from \'read\' to \'in_progress\'', '2025-07-30 19:19:37'),
(5, 'support_status_update', 'Support request ID 1 (Booking Issues) status updated from \'read\' to \'in_progress\'', '2025-07-30 19:19:40'),
(6, 'support_status_update', 'Support request ID 2 (Booking Issues) status updated from \'read\' to \'in_progress\'', '2025-07-30 19:19:46'),
(7, 'support_status_update', 'Support request ID 1 (Booking Issues) status updated from \'closed\' to \'closed\'', '2025-07-30 19:20:04'),
(8, 'support_status_update', 'Support request ID 1 (Booking Issues) status updated from \'read\' to \'in_progress\'', '2025-07-30 19:20:10'),
(9, 'support_request', 'Support request #5 from Raymart Dave Silvosa (raymartkuyah05@gmail.com) - Subject: Booking Issues', '2025-07-30 19:21:52'),
(10, 'support_reply', 'Reply sent to Raymart Dave Silvosa (raymartkuyah05@gmail.com) for support request ID: 5 - Subject: Booking', '2025-07-30 19:23:11'),
(11, 'support_request_deleted', 'Support request deleted - From: Raymart Dave Silvosa (raymartkuyah05@gmail.com) - Subject: Booking Issues', '2025-07-30 19:35:00'),
(12, 'support_request_deleted', 'Support request deleted - From: Raymart Dave Silvosa (raymartkuyah05@gmail.com) - Subject: Booking Issues', '2025-07-30 19:36:14'),
(13, 'support_status_update', 'Support request ID 1 (Booking Issues) status updated from \'read\' to \'in_progress\'', '2025-07-30 19:36:19'),
(14, 'support_request_deleted', 'Support request deleted - From: Raymart Dave Silvosa (raymartkuyah05@gmail.com) - Subject: Booking Issues', '2025-07-30 19:36:26'),
(15, 'support_request_deleted', 'Support request deleted - From: Raymart Dave Silvosa (raymartkuyah05@gmail.com) - Subject: Booking Issues', '2025-07-30 19:36:33'),
(16, 'support_request', 'Support request #2 from Raymart Dave Silvosa (raymartkuyah05@gmail.com) - Subject: General Inquiry', '2025-07-30 19:38:13'),
(17, 'support_status_update', 'Support request ID 2 (General Inquiry) status updated from \'read\' to \'in_progress\'', '2025-07-30 19:38:29'),
(18, 'support_request_deleted', 'Support request deleted - From: Raymart Dave Silvosa (raymartkuyah05@gmail.com) - Subject: General Inquiry', '2025-07-30 19:38:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','faculty','student') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `department` varchar(100) DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `full_name`, `role`, `status`, `phone`, `created_at`, `updated_at`, `department`, `id_number`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'schoolfacilitybooking@gmail.com', 'System Administrator', 'admin', 'active', '09965658875', '2025-03-13 05:27:13', '2025-07-30 18:45:03', 'IT Department', NULL),
(3, 'Raymart', '$2y$10$0.FdyWrG9qDzWeIIXAPJne0tuLExI59fl5Qa0.VN.NVzz9QJCTDXO', 'raymartkuyah05@gmail.com', 'Raymart Dave Silvosa', 'student', 'active', '09950315949', '2025-03-13 06:02:17', '2025-07-29 12:51:09', 'BSIT', NULL),
(6, 'teststudent', '$2y$10$XpEHdEGmL2o9rgzyksa1leKQG014IitHVpTBEs39A94F7Q3t3vQ3a', 'student@test.com', 'Test Student', 'student', 'active', NULL, '2025-07-30 15:10:04', '2025-07-30 15:10:04', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `facility_id` (`facility_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `booking_materials`
--
ALTER TABLE `booking_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facility_materials`
--
ALTER TABLE `facility_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `facility_id` (`facility_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `support_requests`
--
ALTER TABLE `support_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `booking_materials`
--
ALTER TABLE `booking_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `facility_materials`
--
ALTER TABLE `facility_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `support_requests`
--
ALTER TABLE `support_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `booking_materials`
--
ALTER TABLE `booking_materials`
  ADD CONSTRAINT `booking_materials_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_materials_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `facility_materials` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `facility_materials`
--
ALTER TABLE `facility_materials`
  ADD CONSTRAINT `facility_materials_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_requests`
--
ALTER TABLE `support_requests`
  ADD CONSTRAINT `support_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
