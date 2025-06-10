CREATE TABLE IF NOT EXISTS `vefify_quiz_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `province` varchar(100) DEFAULT NULL,
  `pharmacy_code` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `score` int(11) DEFAULT 0,
  `total_questions` int(11) DEFAULT 0,
  `completion_time` int(11) DEFAULT NULL, -- seconds
  `gift_id` int(11) DEFAULT NULL,
  `gift_code` varchar(100) DEFAULT NULL,
  `gift_status` enum('none', 'assigned', 'claimed', 'expired') DEFAULT 'none',
  `gift_response` longtext, -- JSON from API response
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_campaign_phone` (`campaign_id`, `phone_number`),
  KEY `idx_session` (`session_id`),
  KEY `idx_phone_lookup` (`phone_number`),
  KEY `idx_completion` (`completed_at`),
  FOREIGN KEY (`campaign_id`) REFERENCES `vefify_campaigns`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`gift_id`) REFERENCES `vefify_gifts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;