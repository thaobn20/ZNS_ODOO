-- Enhanced Database Migration for Quiz Plugin
-- File: database/migrations/enhanced-tables.sql
-- This ensures all required tables and fields exist

-- 1. Enhanced Participants Table
CREATE TABLE IF NOT EXISTS `{prefix}vefify_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `province` varchar(100) NOT NULL,
  `district` varchar(100) DEFAULT NULL,
  `pharmacist_code` varchar(50) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `total_questions` int(11) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `time_taken` int(11) DEFAULT NULL,
  `gift_id` int(11) DEFAULT NULL,
  `gift_code` varchar(100) DEFAULT NULL,
  `gift_status` enum('none','assigned','claimed') DEFAULT 'none',
  `status` enum('registered','started','completed','passed','failed') DEFAULT 'registered',
  `registration_date` datetime NOT NULL,
  `completion_date` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_phone_campaign` (`phone`, `campaign_id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_registration_date` (`registration_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Enhanced Quiz Sessions Table
CREATE TABLE IF NOT EXISTS `{prefix}vefify_quiz_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(100) NOT NULL UNIQUE,
  `participant_id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `status` enum('active','completed','expired','abandoned') DEFAULT 'active',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `idx_participant_id` (`participant_id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Enhanced Campaigns Table
CREATE TABLE IF NOT EXISTS `{prefix}vefify_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `questions_per_quiz` int(11) DEFAULT 5,
  `time_limit` int(11) DEFAULT 1800 COMMENT 'Time limit in seconds',
  `pass_score` int(11) DEFAULT 3,
  `max_attempts` int(11) DEFAULT 1,
  `show_results` tinyint(1) DEFAULT 1,
  `allow_restart` tinyint(1) DEFAULT 1,
  `require_registration` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Enhanced Questions Table
CREATE TABLE IF NOT EXISTS `{prefix}vefify_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) DEFAULT NULL COMMENT 'NULL means global question',
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','single_choice') DEFAULT 'multiple_choice',
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `category` varchar(100) DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_difficulty` (`difficulty`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Enhanced Question Options Table
CREATE TABLE IF NOT EXISTS `{prefix}vefify_question_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `option_order` int(11) DEFAULT 0,
  `explanation` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_question_id` (`question_id`),
  KEY `idx_correct` (`is_correct`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Enhanced Gifts Table
CREATE TABLE IF NOT EXISTS `{prefix}vefify_gifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `gift_name` varchar(255) NOT NULL,
  `gift_description` text DEFAULT NULL,
  `gift_value` decimal(10,2) DEFAULT NULL,
  `gift_type` enum('voucher','discount','product','points','cash') DEFAULT 'voucher',
  `min_score` int(11) DEFAULT 0,
  `max_score` int(11) DEFAULT NULL,
  `max_quantity` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `gift_code_prefix` varchar(20) DEFAULT 'GIFT',
  `api_endpoint` varchar(500) DEFAULT NULL,
  `api_params` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_score_range` (`min_score`, `max_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Enhanced Analytics Table
CREATE TABLE IF NOT EXISTS `{prefix}vefify_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `participant_id` int(11) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `event_data` longtext DEFAULT NULL COMMENT 'JSON data',
  `session_id` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_participant_id` (`participant_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Form Settings Table
CREATE TABLE IF NOT EXISTS `{prefix}vefify_form_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(100) NOT NULL UNIQUE,
  `setting_value` longtext DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json','text') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data for testing
-- Sample Campaign
INSERT IGNORE INTO `{prefix}vefify_campaigns` (`id`, `name`, `description`, `start_date`, `end_date`, `questions_per_quiz`, `time_limit`, `pass_score`) VALUES
(1, 'Pharmacy Knowledge Quiz', 'Test your knowledge about pharmacy and medication', 
 DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY), 5, 900, 3);

-- Sample Questions
INSERT IGNORE INTO `{prefix}vefify_questions` (`id`, `campaign_id`, `question_text`, `question_type`, `difficulty`, `category`) VALUES
(1, 1, 'What is the primary use of Aspirin?', 'multiple_choice', 'easy', 'medication'),
(2, 1, 'Which vitamin is essential for bone health?', 'multiple_choice', 'medium', 'nutrition'),
(3, 1, 'What is the recommended storage temperature for insulin?', 'multiple_choice', 'medium', 'medication'),
(4, 1, 'Which of these is an antibiotic?', 'multiple_choice', 'easy', 'medication'),
(5, 1, 'What does OTC stand for in pharmacy?', 'multiple_choice', 'easy', 'general');

-- Sample Question Options
INSERT IGNORE INTO `{prefix}vefify_question_options` (`question_id`, `option_text`, `is_correct`, `option_order`) VALUES
-- Question 1 options
(1, 'Pain relief and fever reduction', 1, 1),
(1, 'Sleep aid', 0, 2),
(1, 'Anxiety treatment', 0, 3),
(1, 'Appetite suppressant', 0, 4),
-- Question 2 options
(2, 'Vitamin A', 0, 1),
(2, 'Vitamin C', 0, 2),
(2, 'Vitamin D', 1, 3),
(2, 'Vitamin E', 0, 4),
-- Question 3 options
(3, 'Room temperature', 0, 1),
(3, '2-8°C (refrigerated)', 1, 2),
(3, 'Frozen (-18°C)', 0, 3),
(3, 'Above 25°C', 0, 4),
-- Question 4 options
(4, 'Ibuprofen', 0, 1),
(4, 'Amoxicillin', 1, 2),
(4, 'Paracetamol', 0, 3),
(4, 'Aspirin', 0, 4),
-- Question 5 options
(5, 'Over The Counter', 1, 1),
(5, 'Official Treatment Center', 0, 2),
(5, 'Optimal Treatment Care', 0, 3),
(5, 'Outpatient Treatment Clinic', 0, 4);

-- Sample Gifts
INSERT IGNORE INTO `{prefix}vefify_gifts` (`id`, `campaign_id`, `gift_name`, `gift_description`, `gift_value`, `min_score`, `max_score`, `max_quantity`, `gift_code_prefix`) VALUES
(1, 1, '10% Discount Voucher', 'Get 10% off your next purchase', 10.00, 3, 4, 100, 'DISC10'),
(2, 1, '20% Premium Discount', 'Get 20% off premium products', 20.00, 5, 5, 50, 'PREM20'),
(3, 1, 'Free Consultation', 'Free 30-minute pharmacy consultation', 50.00, 4, 5, 25, 'CONSULT');

-- Sample Form Settings
INSERT IGNORE INTO `{prefix}vefify_form_settings` (`setting_name`, `setting_value`, `setting_type`, `description`) VALUES
('enable_district_selection', '1', 'boolean', 'Enable district selection dropdown'),
('show_pharmacist_code', '1', 'boolean', 'Show pharmacist code field'),
('require_pharmacist_code', '0', 'boolean', 'Make pharmacist code required'),
('show_email', '1', 'boolean', 'Show email field'),
('require_email', '1', 'boolean', 'Make email required'),
('show_company', '1', 'boolean', 'Show company/organization field'),
('require_company', '0', 'boolean', 'Make company field required'),
('show_gift_preview', '1', 'boolean', 'Show gift preview on registration'),
('gift_preview_text', 'Complete the quiz to win exciting prizes!', 'text', 'Text to show in gift preview'),
('form_theme', 'modern', 'string', 'Form theme (modern, classic, minimal)'),
('enable_phone_validation', '1', 'boolean', 'Enable real-time phone validation'),
('max_participants_per_campaign', '1000', 'number', 'Maximum participants per campaign');

-- Add foreign key constraints (optional, for data integrity)
-- ALTER TABLE `{prefix}vefify_participants` ADD FOREIGN KEY (`campaign_id`) REFERENCES `{prefix}vefify_campaigns`(`id`) ON DELETE CASCADE;
-- ALTER TABLE `{prefix}vefify_quiz_sessions` ADD FOREIGN KEY (`participant_id`) REFERENCES `{prefix}vefify_participants`(`id`) ON DELETE CASCADE;
-- ALTER TABLE `{prefix}vefify_questions` ADD FOREIGN KEY (`campaign_id`) REFERENCES `{prefix}vefify_campaigns`(`id`) ON DELETE CASCADE;
-- ALTER TABLE `{prefix}vefify_question_options` ADD FOREIGN KEY (`question_id`) REFERENCES `{prefix}vefify_questions`(`id`) ON DELETE CASCADE;
-- ALTER TABLE `{prefix}vefify_gifts` ADD FOREIGN KEY (`campaign_id`) REFERENCES `{prefix}vefify_campaigns`(`id`) ON DELETE CASCADE;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_participants_phone_campaign` ON `{prefix}vefify_participants` (`phone`, `campaign_id`);
CREATE INDEX IF NOT EXISTS `idx_participants_email_campaign` ON `{prefix}vefify_participants` (`email`, `campaign_id`);
CREATE INDEX IF NOT EXISTS `idx_analytics_event_date` ON `{prefix}vefify_analytics` (`event_type`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_questions_campaign_active` ON `{prefix}vefify_questions` (`campaign_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_gifts_campaign_score` ON `{prefix}vefify_gifts` (`campaign_id`, `min_score`, `max_score`, `is_active`);