<?php
/**
 * Database Installer and Migration Script
 * File: includes/class-database-installer.php
 * 
 * This script handles database installation and migration for the enhanced quiz plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Database_Installer {
    
    private $wpdb;
    private $table_prefix;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'vefify_';
    }
    
    /**
     * Install all database tables
     */
    public function install() {
        $this->create_participants_table();
        $this->create_quiz_sessions_table();
        $this->create_campaigns_table();
        $this->create_questions_table();
        $this->create_question_options_table();
        $this->create_gifts_table();
        $this->create_analytics_table();
        $this->create_form_settings_table();
        
        $this->insert_sample_data();
        $this->create_indexes();
        
        // Update version
        update_option('vefify_quiz_db_version', '2.0.0');
        update_option('vefify_quiz_version', '2.0.0');
        
        return true;
    }
    
    /**
     * Check if tables need to be updated
     */
    public function needs_update() {
        $current_version = get_option('vefify_quiz_db_version', '0.0.0');
        return version_compare($current_version, '2.0.0', '<');
    }
    
    /**
     * Create participants table
     */
    private function create_participants_table() {
        $table_name = $this->table_prefix . 'participants';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create quiz sessions table
     */
    private function create_quiz_sessions_table() {
        $table_name = $this->table_prefix . 'quiz_sessions';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create campaigns table
     */
    private function create_campaigns_table() {
        $table_name = $this->table_prefix . 'campaigns';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `description` text DEFAULT NULL,
            `start_date` datetime NOT NULL,
            `end_date` datetime NOT NULL,
            `questions_per_quiz` int(11) DEFAULT 5,
            `time_limit` int(11) DEFAULT 1800,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create questions table
     */
    private function create_questions_table() {
        $table_name = $this->table_prefix . 'questions';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `campaign_id` int(11) DEFAULT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create question options table
     */
    private function create_question_options_table() {
        $table_name = $this->table_prefix . 'question_options';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create gifts table
     */
    private function create_gifts_table() {
        $table_name = $this->table_prefix . 'gifts';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create analytics table
     */
    private function create_analytics_table() {
        $table_name = $this->table_prefix . 'analytics';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `campaign_id` int(11) NOT NULL,
            `participant_id` int(11) DEFAULT NULL,
            `event_type` varchar(100) NOT NULL,
            `event_data` longtext DEFAULT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create form settings table
     */
    private function create_form_settings_table() {
        $table_name = $this->table_prefix . 'form_settings';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_name` varchar(100) NOT NULL UNIQUE,
            `setting_value` longtext DEFAULT NULL,
            `setting_type` enum('string','number','boolean','json','text') DEFAULT 'string',
            `description` text DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_name` (`setting_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Insert sample data
     */
    private function insert_sample_data() {
        // Check if sample data already exists
        $campaigns_table = $this->table_prefix . 'campaigns';
        $existing = $this->wpdb->get_var("SELECT COUNT(*) FROM {$campaigns_table}");
        
        if ($existing > 0) {
            return; // Sample data already exists
        }
        
        // Insert sample campaign
        $this->wpdb->insert(
            $campaigns_table,
            array(
                'id' => 1,
                'name' => 'Pharmacy Knowledge Quiz',
                'description' => 'Test your knowledge about pharmacy and medication',
                'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'questions_per_quiz' => 5,
                'time_limit' => 900,
                'pass_score' => 3,
                'is_active' => 1
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
        );
        
        // Insert sample questions
        $questions_table = $this->table_prefix . 'questions';
        $questions = array(
            array(1, 'What is the primary use of Aspirin?', 'multiple_choice', 'easy', 'medication'),
            array(1, 'Which vitamin is essential for bone health?', 'multiple_choice', 'medium', 'nutrition'),
            array(1, 'What is the recommended storage temperature for insulin?', 'multiple_choice', 'medium', 'medication'),
            array(1, 'Which of these is an antibiotic?', 'multiple_choice', 'easy', 'medication'),
            array(1, 'What does OTC stand for in pharmacy?', 'multiple_choice', 'easy', 'general')
        );
        
        foreach ($questions as $i => $question) {
            $this->wpdb->insert(
                $questions_table,
                array(
                    'id' => $i + 1,
                    'campaign_id' => $question[0],
                    'question_text' => $question[1],
                    'question_type' => $question[2],
                    'difficulty' => $question[3],
                    'category' => $question[4],
                    'is_active' => 1
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%d')
            );
        }
        
        // Insert sample question options
        $options_table = $this->table_prefix . 'question_options';
        $options = array(
            // Question 1 options
            array(1, 'Pain relief and fever reduction', 1, 1),
            array(1, 'Sleep aid', 0, 2),
            array(1, 'Anxiety treatment', 0, 3),
            array(1, 'Appetite suppressant', 0, 4),
            // Question 2 options
            array(2, 'Vitamin A', 0, 1),
            array(2, 'Vitamin C', 0, 2),
            array(2, 'Vitamin D', 1, 3),
            array(2, 'Vitamin E', 0, 4),
            // Question 3 options
            array(3, 'Room temperature', 0, 1),
            array(3, '2-8°C (refrigerated)', 1, 2),
            array(3, 'Frozen (-18°C)', 0, 3),
            array(3, 'Above 25°C', 0, 4),
            // Question 4 options
            array(4, 'Ibuprofen', 0, 1),
            array(4, 'Amoxicillin', 1, 2),
            array(4, 'Paracetamol', 0, 3),
            array(4, 'Aspirin', 0, 4),
            // Question 5 options
            array(5, 'Over The Counter', 1, 1),
            array(5, 'Official Treatment Center', 0, 2),
            array(5, 'Optimal Treatment Care', 0, 3),
            array(5, 'Outpatient Treatment Clinic', 0, 4)
        );
        
        foreach ($options as $option) {
            $this->wpdb->insert(
                $options_table,
                array(
                    'question_id' => $option[0],
                    'option_text' => $option[1],
                    'is_correct' => $option[2],
                    'option_order' => $option[3]
                ),
                array('%d', '%s', '%d', '%d')
            );
        }
        
        // Insert sample gifts
        $gifts_table = $this->table_prefix . 'gifts';
        $gifts = array(
            array(1, '10% Discount Voucher', 'Get 10% off your next purchase', 10.00, 3, 4, 100, 'DISC10'),
            array(1, '20% Premium Discount', 'Get 20% off premium products', 20.00, 5, 5, 50, 'PREM20'),
            array(1, 'Free Consultation', 'Free 30-minute pharmacy consultation', 50.00, 4, 5, 25, 'CONSULT')
        );
        
        foreach ($gifts as $i => $gift) {
            $this->wpdb->insert(
                $gifts_table,
                array(
                    'id' => $i + 1,
                    'campaign_id' => $gift[0],
                    'gift_name' => $gift[1],
                    'gift_description' => $gift[2],
                    'gift_value' => $gift[3],
                    'min_score' => $gift[4],
                    'max_score' => $gift[5],
                    'max_quantity' => $gift[6],
                    'gift_code_prefix' => $gift[7],
                    'is_active' => 1
                ),
                array('%d', '%d', '%s', '%s', '%f', '%d', '%d', '%d', '%s', '%d')
            );
        }
        
        // Insert form settings
        $settings_table = $this->table_prefix . 'form_settings';
        $settings = array(
            array('enable_district_selection', '1', 'boolean', 'Enable district selection dropdown'),
            array('show_pharmacist_code', '1', 'boolean', 'Show pharmacist code field'),
            array('require_pharmacist_code', '0', 'boolean', 'Make pharmacist code required'),
            array('show_email', '1', 'boolean', 'Show email field'),
            array('require_email', '1', 'boolean', 'Make email required'),
            array('show_company', '1', 'boolean', 'Show company/organization field'),
            array('require_company', '0', 'boolean', 'Make company field required'),
            array('show_gift_preview', '1', 'boolean', 'Show gift preview on registration'),
            array('gift_preview_text', 'Complete the quiz to win exciting prizes!', 'text', 'Text to show in gift preview'),
            array('form_theme', 'modern', 'string', 'Form theme (modern, classic, minimal)'),
            array('enable_phone_validation', '1', 'boolean', 'Enable real-time phone validation')
        );
        
        foreach ($settings as $setting) {
            $this->wpdb->insert(
                $settings_table,
                array(
                    'setting_name' => $setting[0],
                    'setting_value' => $setting[1],
                    'setting_type' => $setting[2],
                    'description' => $setting[3]
                ),
                array('%s', '%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Create additional indexes for performance
     */
    private function create_indexes() {
        $queries = array(
            "CREATE INDEX IF NOT EXISTS idx_participants_phone_campaign ON {$this->table_prefix}participants (phone, campaign_id)",
            "CREATE INDEX IF NOT EXISTS idx_participants_email_campaign ON {$this->table_prefix}participants (email, campaign_id)",
            "CREATE INDEX IF NOT EXISTS idx_analytics_event_date ON {$this->table_prefix}analytics (event_type, created_at)",
            "CREATE INDEX IF NOT EXISTS idx_questions_campaign_active ON {$this->table_prefix}questions (campaign_id, is_active)",
            "CREATE INDEX IF NOT EXISTS idx_gifts_campaign_score ON {$this->table_prefix}gifts (campaign_id, min_score, max_score, is_active)"
        );
        
        foreach ($queries as $query) {
            $this->wpdb->query($query);
        }
    }
    
    /**
     * Get installation status
     */
    public function get_installation_status() {
        $tables = array(
            'participants', 'quiz_sessions', 'campaigns', 'questions', 
            'question_options', 'gifts', 'analytics', 'form_settings'
        );
        
        $status = array();
        
        foreach ($tables as $table) {
            $table_name = $this->table_prefix . $table;
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            
            if ($exists) {
                $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                $status[$table] = array('exists' => true, 'records' => intval($count));
            } else {
                $status[$table] = array('exists' => false, 'records' => 0);
            }
        }
        
        return $status;
    }
    
    /**
     * Uninstall (remove all tables and data)
     */
    public function uninstall() {
        $tables = array(
            'analytics', 'quiz_sessions', 'question_options', 'questions', 
            'gifts', 'participants', 'campaigns', 'form_settings'
        );
        
        foreach ($tables as $table) {
            $table_name = $this->table_prefix . $table;
            $this->wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }
        
        // Remove options
        delete_option('vefify_quiz_db_version');
        delete_option('vefify_quiz_version');
        delete_option('vefify_quiz_settings');
        
        return true;
    }
}

// Usage example:
/*
// Install database
$installer = new Vefify_Database_Installer();

if ($installer->needs_update()) {
    $installer->install();
    echo "Database installed/updated successfully!";
}

// Check status
$status = $installer->get_installation_status();
foreach ($status as $table => $info) {
    echo "Table {$table}: " . ($info['exists'] ? "✅ Exists ({$info['records']} records)" : "❌ Missing") . "\n";
}
*/
?>