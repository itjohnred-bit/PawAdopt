-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Jun 19, 2026 at 07:46 PM
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
-- Database: `pawadopt`
--

-- --------------------------------------------------------

--
-- Table structure for table `adopter_profiles`
--

CREATE TABLE `adopter_profiles` (
  `adopter_id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adopter_profiles`
--

INSERT INTO `adopter_profiles` (`adopter_id`, `full_name`, `phone`, `address`, `city`, `bio`, `avatar_url`, `created_at`, `updated_at`) VALUES
(3, '', '', '', '', '', 'uploads/avatars/img_6a2cbaf569d559.58209421.png', '2026-05-30 23:24:09', '2026-06-13 02:05:41');

-- --------------------------------------------------------

--
-- Table structure for table `adoption_applications`
--

CREATE TABLE `adoption_applications` (
  `application_id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `adopter_id` int(11) NOT NULL,
  `status` enum('PENDING','Submitted','Under Review','Approved','Rejected','Cancelled','Withdrawn') NOT NULL DEFAULT 'PENDING',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `decided_by` int(11) DEFAULT NULL,
  `screening_responses` text DEFAULT NULL,
  `message_to_shelter` text DEFAULT NULL,
  `decision_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adoption_applications`
--

INSERT INTO `adoption_applications` (`application_id`, `pet_id`, `shelter_id`, `adopter_id`, `status`, `submitted_at`, `reviewed_at`, `decided_by`, `screening_responses`, `message_to_shelter`, `decision_note`) VALUES
(1, 1, 5, 3, 'PENDING', '2026-06-13 01:53:27', NULL, NULL, '{\"pet_id\":\"1\",\"app_type\":\"Individual\",\"full_name\":\"Adopter\",\"sex\":\"Male\",\"dob\":\"2005-03-01\",\"civil_status\":\"Single\",\"address\":\"agsaga\",\"residence_status\":\"Owned\",\"phone\":\"09123456789\",\"income\":\"\\u20b115,000\\u201330,000\",\"income_source\":\"asfgasg\",\"owned_before\":\"Yes\",\"sick_policy\":\"Self-medicate\",\"has_current_pets\":\"Yes\",\"current_pet_info\":\"sagasg\",\"has_vet\":\"No\",\"vet_clinic\":\"sagasg\",\"why_adopt\":\"asgasg\",\"action\":\"submit_screening\"}', 'asgasg', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `role`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(65, 3, 'Adopter', 'ADOPTER', 'login', 'Successful login from Adopter', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-19 15:46:36'),
(66, 3, 'Adopter', 'ADOPTER', 'cancel_application', 'Cancelled application #2.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-19 16:07:10'),
(67, 3, 'Adopter', 'ADOPTER', 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-19 16:09:45');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL,
  `adopter_id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `pet_id` int(11) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`conversation_id`, `adopter_id`, `shelter_id`, `pet_id`, `started_at`, `updated_at`, `created_at`) VALUES
(3, 3, 5, 3, '2026-06-12 07:37:02', '2026-06-17 18:18:50', '2026-06-12 07:37:02');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `favorite_id` int(11) NOT NULL,
  `adopter_id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`favorite_id`, `adopter_id`, `pet_id`, `created_at`) VALUES
(4, 3, 3, '2026-06-17 17:26:29');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sender_user_id` int(11) GENERATED ALWAYS AS (`sender_id`) STORED,
  `receiver_user_id` int(11) GENERATED ALWAYS AS (`receiver_id`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `conversation_id`, `sender_id`, `receiver_id`, `message_text`, `is_read`, `sent_at`, `created_at`) VALUES
(39, 3, 3, 5, 'hi', 0, '2026-06-19 16:06:24', '2026-06-19 16:06:24');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pets`
--

CREATE TABLE `pets` (
  `pet_id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `species` enum('Dog','Cat') NOT NULL DEFAULT 'Dog',
  `breed` varchar(100) DEFAULT NULL,
  `age_months` int(11) DEFAULT 0,
  `sex` enum('Male','Female','Unknown') DEFAULT 'Unknown',
  `gender` enum('Male','Female') NOT NULL,
  `size` enum('Small','Medium','Large') NOT NULL,
  `color` varchar(100) DEFAULT NULL,
  `temperament` text DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Available','Pending','Adopted','Removed') NOT NULL DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `medical_certificate` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pets`
--

INSERT INTO `pets` (`pet_id`, `shelter_id`, `name`, `species`, `breed`, `age_months`, `sex`, `gender`, `size`, `color`, `temperament`, `medical_notes`, `description`, `status`, `created_at`, `updated_at`, `medical_certificate`) VALUES
(1, 5, 'Krema', 'Dog', 'Half Retriever', 12, 'Male', 'Male', 'Medium', 'Small Ears', 'Moody', '', 'dsadas', 'Available', '2026-06-02 00:50:16', '2026-06-02 00:50:16', NULL),
(2, 5, 'red', 'Dog', 'Golden Retriever', 12, 'Male', 'Male', 'Medium', 'sfs', 'asfsaf', 'saffas', 'asfasf', 'Removed', '2026-06-12 09:13:47', '2026-06-12 09:48:55', 'uploads/certificates/cert_b9319af1237d7dc3.pdf'),
(3, 5, 'fsa', 'Dog', 'asfa', 12, 'Male', 'Male', 'Medium', 'asf', 'asf', 'asf', 'asf', 'Removed', '2026-06-14 07:05:31', '2026-06-19 15:44:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pet_photos`
--

CREATE TABLE `pet_photos` (
  `photo_id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `photo_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pet_photos`
--

INSERT INTO `pet_photos` (`photo_id`, `pet_id`, `photo_url`, `is_primary`, `created_at`) VALUES
(1, 1, 'uploads/pets/img_6a1e28c8e16b08.18339629.jpeg', 1, '2026-06-02 00:50:16'),
(2, 2, 'uploads/pets/img_6a2bcdcbf1ac18.04847367.jpg', 1, '2026-06-12 09:13:47');

-- --------------------------------------------------------

--
-- Table structure for table `shelter_profiles`
--

CREATE TABLE `shelter_profiles` (
  `shelter_id` int(11) NOT NULL,
  `shelter_name` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shelter_profiles`
--

INSERT INTO `shelter_profiles` (`shelter_id`, `shelter_name`, `phone`, `address`, `city`, `description`, `website`, `logo_url`, `is_verified`, `created_at`, `updated_at`) VALUES
(5, 'Veterinary\'s Shelter', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-06-02 00:18:07', '2026-06-02 00:18:07');

-- --------------------------------------------------------

--
-- Table structure for table `shelter_verifications`
--

CREATE TABLE `shelter_verifications` (
  `verification_id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `remarks` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shelter_verifications`
--

INSERT INTO `shelter_verifications` (`verification_id`, `shelter_id`, `status`, `remarks`, `submitted_at`, `updated_at`) VALUES
(2, 5, 'PENDING', NULL, '2026-06-02 00:18:07', '2026-06-02 00:18:07');

-- --------------------------------------------------------

--
-- Table structure for table `site_content`
--

CREATE TABLE `site_content` (
  `content_key` varchar(100) NOT NULL,
  `content_value` text NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_content`
--

INSERT INTO `site_content` (`content_key`, `content_value`, `updated_by`, `updated_at`) VALUES
('homepage_banner_subtitle', 'PUPAdopt connects loving adopters with shelters.', NULL, '2026-05-30 21:28:08'),
('homepage_banner_title', 'Find Your Forever Furry Friend', NULL, '2026-05-30 21:28:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `role` enum('ADOPTER','SHELTER','ADMIN') NOT NULL DEFAULT 'ADOPTER',
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role`, `username`, `email`, `password_hash`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'ADOPTER', 'Adopter', 'adopter@test.com', '$2y$10$PxyyujApK61kvEDHeGVHWOF/VRxb7J.d.ugOmIjqLuHHLtvJV4PF6', 1, '2026-05-30 23:24:09', '2026-05-30 23:24:09'),
(5, 'SHELTER', 'Veterinary', 'vet@tester.com', '$2y$10$UI3TpdoxmOZviJLE0C42outmbPSxWhfZBVgfRFqav/7qip2Y6CXjW', 1, '2026-06-02 00:18:07', '2026-06-19 15:32:25'),
(20, 'ADMIN', 'admin', 'admin@pupadopt.com', '$2y$10$lcpxqbjbwwAxacFUeh0ElOHaDQ4NmTJ5EjjyE6Q0qKHJywb1k56gK', 1, '2026-06-12 11:30:44', '2026-06-12 11:36:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `adopter_profiles`
--
ALTER TABLE `adopter_profiles`
  ADD PRIMARY KEY (`adopter_id`);

--
-- Indexes for table `adoption_applications`
--
ALTER TABLE `adoption_applications`
  ADD PRIMARY KEY (`application_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`conversation_id`),
  ADD UNIQUE KEY `unique_chat_pair` (`adopter_id`,`shelter_id`),
  ADD KEY `shelter_id` (`shelter_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`favorite_id`),
  ADD UNIQUE KEY `unique_favorite` (`adopter_id`,`pet_id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pets`
--
ALTER TABLE `pets`
  ADD PRIMARY KEY (`pet_id`),
  ADD KEY `shelter_id` (`shelter_id`);

--
-- Indexes for table `pet_photos`
--
ALTER TABLE `pet_photos`
  ADD PRIMARY KEY (`photo_id`),
  ADD KEY `pet_id` (`pet_id`);

--
-- Indexes for table `shelter_profiles`
--
ALTER TABLE `shelter_profiles`
  ADD PRIMARY KEY (`shelter_id`);

--
-- Indexes for table `shelter_verifications`
--
ALTER TABLE `shelter_verifications`
  ADD PRIMARY KEY (`verification_id`),
  ADD KEY `shelter_id` (`shelter_id`);

--
-- Indexes for table `site_content`
--
ALTER TABLE `site_content`
  ADD PRIMARY KEY (`content_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `adoption_applications`
--
ALTER TABLE `adoption_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `conversation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `favorite_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pets`
--
ALTER TABLE `pets`
  MODIFY `pet_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pet_photos`
--
ALTER TABLE `pet_photos`
  MODIFY `photo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `shelter_verifications`
--
ALTER TABLE `shelter_verifications`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `adopter_profiles`
--
ALTER TABLE `adopter_profiles`
  ADD CONSTRAINT `adopter_profiles_ibfk_1` FOREIGN KEY (`adopter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`adopter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`shelter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`adopter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`pet_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `pets`
--
ALTER TABLE `pets`
  ADD CONSTRAINT `pets_ibfk_1` FOREIGN KEY (`shelter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `pet_photos`
--
ALTER TABLE `pet_photos`
  ADD CONSTRAINT `pet_photos_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`pet_id`) ON DELETE CASCADE;

--
-- Constraints for table `shelter_profiles`
--
ALTER TABLE `shelter_profiles`
  ADD CONSTRAINT `shelter_profiles_ibfk_1` FOREIGN KEY (`shelter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `shelter_verifications`
--
ALTER TABLE `shelter_verifications`
  ADD CONSTRAINT `shelter_verifications_ibfk_1` FOREIGN KEY (`shelter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
