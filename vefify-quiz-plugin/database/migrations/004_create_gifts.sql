CREATE TABLE IF NOT EXISTS `vefify_gifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `gift_name` varchar(255) NOT NULL,
  `gift_type` enum('voucher', 'discount', 'product', 'points') NOT NULL,
  `gift_value` varchar(100) NOT NULL, -- 50K VND, 20%, etc.
  `gift_description` text,
  `min_score` int(11) NOT NULL DEFAULT 0,
  `max_score` int(11) DEFAULT NULL,
  `max_quantity` int(11) DEFAULT NULL, -- NULL for unlimited
  `used_count` int(11) DEFAULT 0,
  `gift_code_prefix` varchar(20) DEFAULT NULL,
  `api_endpoint` varchar(255) DEFAULT NULL, -- For Phase 2
  `api_params` longtext, -- JSON for API parameters
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_gifts` (`campaign_id`, `is_active`),
  KEY `idx_score_range` (`min_score`, `max_score`),
  FOREIGN KEY (`campaign_id`) REFERENCES `vefify_campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;