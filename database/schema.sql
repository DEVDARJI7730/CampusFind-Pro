-- CampusFind Pro Lost & Found System Database Schema

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `reports`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `claims`;
DROP TABLE IF EXISTS `found_items`;
DROP TABLE IF EXISTS `lost_items`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table (Supports Students and Admins)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` VARCHAR(50) UNIQUE DEFAULT NULL,
  `google_id` VARCHAR(255) UNIQUE DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT 'default-avatar.png',
  `role` ENUM('student', 'admin') DEFAULT 'student',
  `status` ENUM('pending', 'active', 'suspended') DEFAULT 'active',
  `is_verified` TINYINT(1) DEFAULT 0,
  `verification_code` VARCHAR(10) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Admins Table (Admin specific details, references Users)
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `admin_level` ENUM('superadmin', 'moderator') DEFAULT 'moderator',
  `department` VARCHAR(100) DEFAULT 'Student Affairs',
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Categories Table
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) UNIQUE NOT NULL,
  `icon` VARCHAR(50) DEFAULT 'fa-box',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Lost Items Table
CREATE TABLE `lost_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `location` VARCHAR(100) NOT NULL,
  `lost_date` DATE NOT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `reward` DECIMAL(10,2) DEFAULT 0.00,
  `status` ENUM('lost', 'claimed', 'cancelled') DEFAULT 'lost',
  `qr_token` VARCHAR(100) UNIQUE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Found Items Table
CREATE TABLE `found_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `location` VARCHAR(100) NOT NULL,
  `found_date` DATE NOT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('found', 'claimed', 'returned') DEFAULT 'found',
  `qr_token` VARCHAR(100) UNIQUE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Claims Table (Claim Module)
CREATE TABLE `claims` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_type` ENUM('lost', 'found') NOT NULL,
  `item_id` INT NOT NULL,
  `claimer_id` INT NOT NULL,
  `proof_description` TEXT NOT NULL,
  `proof_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `admin_notes` TEXT DEFAULT NULL,
  `processed_by` INT DEFAULT NULL,
  `processed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`claimer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Notifications Table
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Activity Logs Table (Security / Audit Log)
CREATE TABLE `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. App Settings Table
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) UNIQUE NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Reports Table (Saved generated reports history)
CREATE TABLE `reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `report_type` VARCHAR(50) NOT NULL,
  `format` ENUM('pdf', 'xlsx') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- SEED DATA
-- ==========================================

-- Seed Categories
INSERT INTO `categories` (`name`, `icon`) VALUES
('Electronics', 'fa-laptop'),
('Documents & Cards', 'fa-id-card'),
('Keys & Wallets', 'fa-wallet'),
('Books & Stationery', 'fa-book'),
('Clothing & Accessories', 'fa-tshirt'),
('Bags & Backpacks', 'fa-shopping-bag'),
('Others', 'fa-box');

-- Seed Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'CampusFind Pro'),
('contact_email', 'support@campusfindpro.edu'),
('max_file_size', '5242880'), -- 5MB
('allowed_file_types', 'jpg,jpeg,png'),
('require_verification', '0'), -- Set to 1 if we actually require verification
('session_timeout', '1800'); -- 30 minutes

-- Seed Admin User (password is: Admin123!)
-- Hash of 'Admin123!': $2y$10$wKz0bB41tPjN7R.3JlhPquV0wN2m/cO6Tse8aWl/aGzI/2FjP8.sS
INSERT INTO `users` (`student_id`, `name`, `email`, `password`, `role`, `is_verified`, `status`) VALUES
('ADMIN001', 'CampusFind Admin', 'admin@campusfindpro.edu', '$2y$10$wKz0bB41tPjN7R.3JlhPquV0wN2m/cO6Tse8aWl/aGzI/2FjP8.sS', 'admin', 1, 'active');

INSERT INTO `admins` (`user_id`, `admin_level`, `department`) VALUES
(1, 'superadmin', 'Campus Security');
