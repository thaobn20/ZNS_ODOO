CREATE TABLE IF NOT EXISTS `vefify_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL UNIQUE,
  `description` text,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `max_participants` int(11) DEFAULT NULL,
  `allow_retake` tinyint(1) DEFAULT 0,
  `questions_per_quiz` int(11) DEFAULT 5,
  `time_limit` int(11) DEFAULT NULL, -- seconds
  `pass_score` int(11) DEFAULT 3,
  `meta_data` longtext, -- JSON for additional settings
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active_campaigns` (`is_active`, `start_date`, `end_date`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;