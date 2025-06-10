CREATE TABLE IF NOT EXISTS `vefify_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) DEFAULT NULL, -- NULL for global questions
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice', 'multiple_select', 'true_false') DEFAULT 'multiple_choice',
  `category` varchar(100) DEFAULT NULL,
  `difficulty` enum('easy', 'medium', 'hard') DEFAULT 'medium',
  `points` int(11) DEFAULT 1,
  `explanation` text,
  `is_active` tinyint(1) DEFAULT 1,
  `order_index` int(11) DEFAULT 0,
  `meta_data` longtext, -- JSON for additional data
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_questions` (`campaign_id`, `is_active`),
  KEY `idx_category` (`category`),
  FOREIGN KEY (`campaign_id`) REFERENCES `vefify_campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;