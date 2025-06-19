<?php
/**
 * 🗄️ ENHANCED DATABASE SCHEMA - FIXED VERSION
 * File: includes/class-enhanced-database.php
 * 
 * Fixed version without missing constants
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Enhanced_Database extends Vefify_Quiz_Database {
    
    /**
     * 📊 ENHANCED TABLE CREATION - Complete Schema
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $sql = array();
        
        // 1. Enhanced Campaigns Table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}campaigns (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(100) NOT NULL,
            description text,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            questions_per_quiz int(11) NOT NULL DEFAULT 5,
            time_limit int(11) NOT NULL DEFAULT 0,
            pass_score int(11) NOT NULL DEFAULT 3,
            max_participants int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            allow_retries tinyint(1) NOT NULL DEFAULT 1,
            show_results tinyint(1) NOT NULL DEFAULT 1,
            show_correct_answers tinyint(1) NOT NULL DEFAULT 0,
            randomize_questions tinyint(1) NOT NULL DEFAULT 1,
            randomize_options tinyint(1) NOT NULL DEFAULT 1,
            certificate_template varchar(255) DEFAULT NULL,
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY active_campaigns (is_active, start_date, end_date),
            KEY campaign_timing (start_date, end_date)
        ) $charset_collate;";
        
        // 2. Enhanced Questions Table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}questions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            question_text text NOT NULL,
            question_type enum('single_choice', 'multiple_choice', 'true_false', 'fill_blank') NOT NULL DEFAULT 'single_choice',
            difficulty enum('easy', 'medium', 'hard') NOT NULL DEFAULT 'medium',
            category varchar(100) DEFAULT NULL,
            points int(11) NOT NULL DEFAULT 1,
            time_limit int(11) DEFAULT NULL,
            explanation text,
            correct_explanation text,
            tags varchar(500) DEFAULT NULL,
            order_index int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            usage_count int(11) NOT NULL DEFAULT 0,
            correct_count int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_questions (campaign_id, is_active),
            KEY question_difficulty (difficulty, category),
            KEY question_performance (usage_count, correct_count)
        ) $charset_collate;";
        
        // 3. Enhanced Question Options Table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}question_options (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            question_id bigint(20) unsigned NOT NULL,
            option_text text NOT NULL,
            option_value varchar(255) NOT NULL,
            is_correct tinyint(1) NOT NULL DEFAULT 0,
            option_order int(11) NOT NULL DEFAULT 0,
            explanation text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY question_options (question_id, option_order),
            KEY correct_options (question_id, is_correct)
        ) $charset_collate;";
        
        // 4. Enhanced Participants Table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}participants (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            full_name varchar(255) NOT NULL,
            email varchar(255) DEFAULT NULL,
            phone_number varchar(20) NOT NULL,
            province varchar(100) DEFAULT NULL,
            pharmacy_code varchar(50) DEFAULT NULL,
            occupation varchar(100) DEFAULT NULL,
            company varchar(255) DEFAULT NULL,
            age int(11) DEFAULT NULL,
            experience_years int(11) DEFAULT NULL,
            session_token varchar(255) DEFAULT NULL,
            quiz_session_id varchar(255) DEFAULT NULL,
            quiz_status enum('registered', 'started', 'in_progress', 'completed', 'abandoned') NOT NULL DEFAULT 'registered',
            quiz_started_at datetime DEFAULT NULL,
            quiz_completed_at datetime DEFAULT NULL,
            final_score int(11) DEFAULT NULL,
            total_questions int(11) DEFAULT NULL,
            correct_answers int(11) DEFAULT NULL,
            percentage_score decimal(5,2) DEFAULT NULL,
            time_taken int(11) DEFAULT NULL,
            gift_id bigint(20) unsigned DEFAULT NULL,
            gift_code varchar(100) DEFAULT NULL,
            gift_status enum('none', 'assigned', 'claimed', 'expired') NOT NULL DEFAULT 'none',
            gift_assigned_at datetime DEFAULT NULL,
            gift_claimed_at datetime DEFAULT NULL,
            registration_ip varchar(45) DEFAULT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_participant (campaign_id, phone_number),
            KEY participant_status (quiz_status, campaign_id),
            KEY participant_performance (final_score, percentage_score),
            KEY participant_timing (quiz_started_at, quiz_completed_at),
            KEY gift_tracking (gift_status, gift_assigned_at)
        ) $charset_collate;";
        
        // 5. NEW: Quiz Sessions Table (Track individual quiz attempts)
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}quiz_sessions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            participant_id bigint(20) unsigned NOT NULL,
            campaign_id bigint(20) unsigned NOT NULL,
            question_ids longtext NOT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            session_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_session (session_id),
            KEY session_participant (participant_id, is_active),
            KEY session_timing (started_at, completed_at)
        ) $charset_collate;";
        
        // 6. NEW: Quiz Answers Table (Track individual answers)
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}quiz_answers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            participant_id bigint(20) unsigned NOT NULL,
            question_id bigint(20) unsigned NOT NULL,
            answer_data longtext NOT NULL,
            is_correct tinyint(1) DEFAULT NULL,
            time_spent int(11) DEFAULT NULL,
            answered_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_answer (session_id, question_id),
            KEY answer_participant (participant_id, answered_at),
            KEY answer_question (question_id, is_correct),
            KEY answer_session (session_id)
        ) $charset_collate;";
        
        // 7. Enhanced Gifts Table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}gifts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            gift_name varchar(255) NOT NULL,
            gift_type enum('discount', 'voucher', 'product', 'certificate', 'digital') NOT NULL DEFAULT 'voucher',
            gift_value varchar(100) NOT NULL,
            description text,
            min_score int(11) NOT NULL DEFAULT 0,
            max_score int(11) NOT NULL DEFAULT 0,
            min_percentage decimal(5,2) NOT NULL DEFAULT 0.00,
            max_percentage decimal(5,2) NOT NULL DEFAULT 100.00,
            max_quantity int(11) NOT NULL DEFAULT 0,
            current_quantity int(11) NOT NULL DEFAULT 0,
            gift_code_prefix varchar(20) NOT NULL DEFAULT 'GIFT',
            expiry_days int(11) NOT NULL DEFAULT 30,
            terms_conditions text,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY gift_campaign (campaign_id, is_active),
            KEY gift_scores (min_score, max_score, min_percentage, max_percentage),
            KEY gift_inventory (max_quantity, current_quantity)
        ) $charset_collate;";
        
        // 8. NEW: Quiz Analytics Table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}quiz_analytics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            participant_id bigint(20) unsigned NOT NULL,
            campaign_id bigint(20) unsigned NOT NULL,
            session_id varchar(255) NOT NULL,
            final_score int(11) NOT NULL,
            total_questions int(11) NOT NULL,
            correct_answers int(11) NOT NULL,
            percentage_score decimal(5,2) NOT NULL,
            time_taken int(11) NOT NULL,
            difficulty_breakdown longtext,
            category_breakdown longtext,
            device_info longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY analytics_campaign (campaign_id, created_at),
            KEY analytics_participant (participant_id),
            KEY analytics_performance (final_score, percentage_score)
        ) $charset_collate;";
        
        // 9. NEW: System Logs Table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_prefix}system_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            log_type enum('info', 'warning', 'error', 'debug') NOT NULL DEFAULT 'info',
            component varchar(100) NOT NULL,
            message text NOT NULL,
            context longtext,
            user_id bigint(20) unsigned DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY log_type_time (log_type, created_at),
            KEY log_component (component, created_at)
        ) $charset_collate;";
        
        // Execute all SQL statements
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($sql as $query) {
            dbDelta($query);
            
            // Log any errors
            if (!empty($wpdb->last_error)) {
                error_log('Vefify Quiz DB Error: ' . $wpdb->last_error);
                error_log('Query: ' . $query);
            }
        }
        
        // Create indexes for performance
        $this->create_performance_indexes();
        
        // Insert sample data if tables are empty
        $this->maybe_insert_sample_data();
        
        // Update database version
        update_option('vefify_quiz_db_version', VEFIFY_QUIZ_VERSION);
        
        return true;
    }
    
    /**
     * 🚀 CREATE PERFORMANCE INDEXES
     */
    private function create_performance_indexes() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $indexes = array(
            "CREATE INDEX IF NOT EXISTS idx_participant_lookup ON {$table_prefix}participants(campaign_id, phone_number, quiz_status)",
            "CREATE INDEX IF NOT EXISTS idx_session_lookup ON {$table_prefix}quiz_sessions(session_id, is_active)",
            "CREATE INDEX IF NOT EXISTS idx_answer_lookup ON {$table_prefix}quiz_answers(session_id, question_id)",
            "CREATE INDEX IF NOT EXISTS idx_question_performance ON {$table_prefix}questions(campaign_id, usage_count, correct_count)",
            "CREATE INDEX IF NOT EXISTS idx_gift_eligibility ON {$table_prefix}gifts(campaign_id, min_score, max_score, is_active)",
            "CREATE INDEX IF NOT EXISTS idx_analytics_reporting ON {$table_prefix}quiz_analytics(campaign_id, created_at, percentage_score)"
        );
        
        foreach ($indexes as $index) {
            $wpdb->query($index);
        }
    }
    
    /**
     * 📊 INSERT SAMPLE DATA FOR TESTING
     */
    private function maybe_insert_sample_data() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Check if sample campaign exists
        $existing_campaign = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_prefix}campaigns WHERE slug = %s",
            'health-knowledge-quiz-2024'
        ));
        
        if ($existing_campaign > 0) {
            return; // Sample data already exists
        }
        
        // Insert sample campaign
        $campaign_data = array(
            'name' => 'Health Knowledge Quiz 2024',
            'slug' => 'health-knowledge-quiz-2024',
            'description' => 'Test your health and wellness knowledge with our comprehensive quiz',
            'start_date' => date('Y-01-01 00:00:00'),
            'end_date' => date('Y-12-31 23:59:59'),
            'questions_per_quiz' => 5,
            'time_limit' => 600, // 10 minutes
            'pass_score' => 3,
            'max_participants' => 1000,
            'is_active' => 1,
            'allow_retries' => 1,
            'show_results' => 1,
            'randomize_questions' => 1,
            'randomize_options' => 1
        );
        
        $wpdb->insert($table_prefix . 'campaigns', $campaign_data);
        $campaign_id = $wpdb->insert_id;
        
        // Insert sample questions
        $sample_questions = array(
            array(
                'question_text' => 'What is the recommended daily water intake for adults?',
                'difficulty' => 'easy',
                'category' => 'Nutrition',
                'points' => 1,
                'explanation' => 'The general recommendation is about 8 glasses (64 ounces) of water per day.',
                'options' => array(
                    array('text' => '4 glasses per day', 'correct' => false),
                    array('text' => '8 glasses per day', 'correct' => true),
                    array('text' => '12 glasses per day', 'correct' => false),
                    array('text' => '16 glasses per day', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'Which vitamin is primarily produced when skin is exposed to sunlight?',
                'difficulty' => 'medium',
                'category' => 'Vitamins',
                'points' => 2,
                'explanation' => 'Vitamin D is synthesized by the skin when exposed to UVB radiation from sunlight.',
                'options' => array(
                    array('text' => 'Vitamin A', 'correct' => false),
                    array('text' => 'Vitamin C', 'correct' => false),
                    array('text' => 'Vitamin D', 'correct' => true),
                    array('text' => 'Vitamin E', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'What is the normal resting heart rate range for healthy adults?',
                'difficulty' => 'medium',
                'category' => 'Physiology',
                'points' => 2,
                'explanation' => 'A normal resting heart rate for adults ranges from 60 to 100 beats per minute.',
                'options' => array(
                    array('text' => '40-60 bpm', 'correct' => false),
                    array('text' => '60-100 bpm', 'correct' => true),
                    array('text' => '100-140 bpm', 'correct' => false),
                    array('text' => '140-180 bpm', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'Which of the following are considered healthy sources of protein? (Select all that apply)',
                'difficulty' => 'hard',
                'category' => 'Nutrition',
                'points' => 3,
                'question_type' => 'multiple_choice',
                'explanation' => 'Fish, legumes, and lean poultry are all excellent sources of healthy protein.',
                'options' => array(
                    array('text' => 'Fish', 'correct' => true),
                    array('text' => 'Processed meats', 'correct' => false),
                    array('text' => 'Legumes (beans, lentils)', 'correct' => true),
                    array('text' => 'Lean poultry', 'correct' => true)
                )
            ),
            array(
                'question_text' => 'Regular exercise can help prevent type 2 diabetes.',
                'difficulty' => 'easy',
                'category' => 'Exercise',
                'points' => 1,
                'question_type' => 'true_false',
                'explanation' => 'Regular physical activity helps control blood sugar levels and reduces the risk of type 2 diabetes.',
                'options' => array(
                    array('text' => 'True', 'correct' => true),
                    array('text' => 'False', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'What is the recommended amount of sleep for healthy adults?',
                'difficulty' => 'easy',
                'category' => 'Sleep Health',
                'points' => 1,
                'explanation' => 'Most adults need 7-9 hours of sleep per night for optimal health.',
                'options' => array(
                    array('text' => '4-6 hours', 'correct' => false),
                    array('text' => '7-9 hours', 'correct' => true),
                    array('text' => '10-12 hours', 'correct' => false),
                    array('text' => '12+ hours', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'Which mineral is essential for bone health?',
                'difficulty' => 'medium',
                'category' => 'Minerals',
                'points' => 2,
                'explanation' => 'Calcium is the primary mineral needed for strong bones and teeth.',
                'options' => array(
                    array('text' => 'Iron', 'correct' => false),
                    array('text' => 'Calcium', 'correct' => true),
                    array('text' => 'Zinc', 'correct' => false),
                    array('text' => 'Magnesium', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'What is the body mass index (BMI) range considered "normal weight"?',
                'difficulty' => 'hard',
                'category' => 'Health Metrics',
                'points' => 3,
                'explanation' => 'A BMI between 18.5 and 24.9 is considered normal weight range.',
                'options' => array(
                    array('text' => '15.0 - 18.4', 'correct' => false),
                    array('text' => '18.5 - 24.9', 'correct' => true),
                    array('text' => '25.0 - 29.9', 'correct' => false),
                    array('text' => '30.0 - 34.9', 'correct' => false)
                )
            )
        );
        
        foreach ($sample_questions as $q_data) {
            $question_data = array(
                'campaign_id' => $campaign_id,
                'question_text' => $q_data['question_text'],
                'question_type' => $q_data['question_type'] ?? 'single_choice',
                'difficulty' => $q_data['difficulty'],
                'category' => $q_data['category'],
                'points' => $q_data['points'],
                'explanation' => $q_data['explanation']
            );
            
            $wpdb->insert($table_prefix . 'questions', $question_data);
            $question_id = $wpdb->insert_id;
            
            // Insert options
            foreach ($q_data['options'] as $index => $option) {
                $option_data = array(
                    'question_id' => $question_id,
                    'option_text' => $option['text'],
                    'option_value' => ($index + 1),
                    'is_correct' => $option['correct'] ? 1 : 0,
                    'option_order' => $index + 1
                );
                
                $wpdb->insert($table_prefix . 'question_options', $option_data);
            }
        }
        
        // Insert sample gifts
        $sample_gifts = array(
            array(
                'gift_name' => '10% Health Store Discount',
                'gift_type' => 'discount',
                'gift_value' => '10%',
                'description' => 'Get 10% off your next purchase at participating health stores',
                'min_score' => 3,
                'max_score' => 4,
                'min_percentage' => 60.00,
                'max_percentage' => 79.99,
                'max_quantity' => 100,
                'gift_code_prefix' => 'HEALTH10'
            ),
            array(
                'gift_name' => '50,000 VND Wellness Voucher',
                'gift_type' => 'voucher',
                'gift_value' => '50000 VND',
                'description' => 'Voucher worth 50,000 VND for wellness products and services',
                'min_score' => 5,
                'max_score' => 0, // No upper limit
                'min_percentage' => 80.00,
                'max_percentage' => 100.00,
                'max_quantity' => 50,
                'gift_code_prefix' => 'WELLNESS50K'
            ),
            array(
                'gift_name' => 'Health Knowledge Certificate',
                'gift_type' => 'certificate',
                'gift_value' => 'Digital Certificate',
                'description' => 'Official certificate recognizing your health knowledge expertise',
                'min_score' => 4,
                'max_score' => 0,
                'min_percentage' => 70.00,
                'max_percentage' => 100.00,
                'max_quantity' => 0, // Unlimited
                'gift_code_prefix' => 'CERT'
            )
        );
        
        foreach ($sample_gifts as $gift) {
            $gift['campaign_id'] = $campaign_id;
            $wpdb->insert($table_prefix . 'gifts', $gift);
        }
        
        // Log successful sample data insertion
        $this->log_system_event('info', 'database', 'Sample data inserted successfully', array(
            'campaign_id' => $campaign_id,
            'questions_count' => count($sample_questions),
            'gifts_count' => count($sample_gifts)
        ));
    }
    
    /**
     * 📝 LOG SYSTEM EVENT
     */
    public function log_system_event($type, $component, $message, $context = null) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $log_data = array(
            'log_type' => $type,
            'component' => $component,
            'message' => $message,
            'context' => $context ? json_encode($context) : null,
            'user_id' => get_current_user_id() ?: null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        );
        
        $wpdb->insert($table_prefix . 'system_logs', $log_data);
    }
    
    /**
     * 🔄 DATABASE MIGRATION HANDLER
     */
    public function maybe_upgrade_database() {
        $current_version = get_option('vefify_quiz_db_version', '0.0.0');
        
        if (version_compare($current_version, VEFIFY_QUIZ_VERSION, '<')) {
            $this->create_tables();
            
            // Run version-specific migrations
            $this->run_migrations($current_version);
            
            // Update version
            update_option('vefify_quiz_db_version', VEFIFY_QUIZ_VERSION);
            
            $this->log_system_event('info', 'database', 'Database upgraded', array(
                'from_version' => $current_version,
                'to_version' => VEFIFY_QUIZ_VERSION
            ));
        }
    }
    
    /**
     * 🔄 RUN VERSION-SPECIFIC MIGRATIONS
     */
    private function run_migrations($from_version) {
        // Example migration logic
        if (version_compare($from_version, '1.1.0', '<')) {
            $this->migrate_to_1_1_0();
        }
        
        if (version_compare($from_version, '1.2.0', '<')) {
            $this->migrate_to_1_2_0();
        }
    }
    
    /**
     * 🔄 MIGRATION TO 1.1.0 - Add session tracking
     */
    private function migrate_to_1_1_0() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Add session columns to participants if they don't exist
        $columns = $wpdb->get_col("DESCRIBE {$table_prefix}participants");
        
        if (!in_array('quiz_session_id', $columns)) {
            $wpdb->query("ALTER TABLE {$table_prefix}participants ADD COLUMN quiz_session_id varchar(255) DEFAULT NULL AFTER session_token");
        }
        
        if (!in_array('percentage_score', $columns)) {
            $wpdb->query("ALTER TABLE {$table_prefix}participants ADD COLUMN percentage_score decimal(5,2) DEFAULT NULL AFTER correct_answers");
        }
    }
    
    /**
     * 🔄 MIGRATION TO 1.2.0 - Enhanced gift system
     */
    private function migrate_to_1_2_0() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Add percentage-based gift criteria
        $columns = $wpdb->get_col("DESCRIBE {$table_prefix}gifts");
        
        if (!in_array('min_percentage', $columns)) {
            $wpdb->query("ALTER TABLE {$table_prefix}gifts ADD COLUMN min_percentage decimal(5,2) NOT NULL DEFAULT 0.00 AFTER max_score");
            $wpdb->query("ALTER TABLE {$table_prefix}gifts ADD COLUMN max_percentage decimal(5,2) NOT NULL DEFAULT 100.00 AFTER min_percentage");
        }
    }
    
    /**
     * 🗑️ CLEAN UP OLD DATA
     */
    public function cleanup_old_data($days = 90) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Clean up old sessions
        $deleted_sessions = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_prefix}quiz_sessions 
             WHERE completed_at IS NOT NULL 
             AND completed_at < %s",
            $cutoff_date
        ));
        
        // Clean up old logs
        $deleted_logs = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_prefix}system_logs 
             WHERE created_at < %s 
             AND log_type != 'error'",
            $cutoff_date
        ));
        
        $this->log_system_event('info', 'cleanup', 'Old data cleaned up', array(
            'sessions_deleted' => $deleted_sessions,
            'logs_deleted' => $deleted_logs,
            'cutoff_date' => $cutoff_date
        ));
        
        return array(
            'sessions_deleted' => $deleted_sessions,
            'logs_deleted' => $deleted_logs
        );
    }
    
    /**
     * 📊 GET DATABASE STATISTICS
     */
    public function get_database_statistics() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $stats = array();
        
        $tables = array(
            'campaigns', 'questions', 'question_options', 'participants',
            'quiz_sessions', 'quiz_answers', 'gifts', 'quiz_analytics', 'system_logs'
        );
        
        foreach ($tables as $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}{$table}");
            $stats[$table] = intval($count);
        }
        
        // Get database size
        $size_query = $wpdb->prepare(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'db_size' 
             FROM information_schema.tables 
             WHERE table_schema = %s 
             AND table_name LIKE %s",
            DB_NAME,
            $table_prefix . '%'
        );
        
        $stats['database_size_mb'] = floatval($wpdb->get_var($size_query));
        
        return $stats;
    }
    
    /**
     * ✅ VALIDATE DATABASE INTEGRITY
     */
    public function validate_database_integrity() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $issues = array();
        
        // Check for orphaned records
        $orphaned_questions = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_prefix}questions q 
             LEFT JOIN {$table_prefix}campaigns c ON q.campaign_id = c.id 
             WHERE c.id IS NULL"
        );
        
        if ($orphaned_questions > 0) {
            $issues[] = "Found {$orphaned_questions} orphaned questions";
        }
        
        $orphaned_options = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_prefix}question_options o 
             LEFT JOIN {$table_prefix}questions q ON o.question_id = q.id 
             WHERE q.id IS NULL"
        );
        
        if ($orphaned_options > 0) {
            $issues[] = "Found {$orphaned_options} orphaned question options";
        }
        
        // Check for questions without correct answers
        $questions_no_correct = $wpdb->get_var(
            "SELECT COUNT(DISTINCT q.id) FROM {$table_prefix}questions q 
             LEFT JOIN {$table_prefix}question_options o ON q.id = o.question_id AND o.is_correct = 1
             WHERE o.id IS NULL AND q.is_active = 1"
        );
        
        if ($questions_no_correct > 0) {
            $issues[] = "Found {$questions_no_correct} active questions without correct answers";
        }
        
        return array(
            'is_valid' => empty($issues),
            'issues' => $issues
        );
    }
}

// SAFE INITIALIZATION - NO MISSING CONSTANTS
if (class_exists('Vefify_Quiz_Database')) {
    // This will be automatically loaded when the main plugin initializes
    error_log('Vefify Quiz: Enhanced Database class loaded successfully');
}