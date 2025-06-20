<?php
/**
 * Complete Database Class for Vefify Quiz Plugin
 * File: includes/class-database.php
 * 
 * Matches your exact SQL structure and includes sample data
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Database {
    
    private $wpdb;
    private $tables;
    private $charset_collate;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Set charset
        $this->charset_collate = $wpdb->get_charset_collate();
        
        // Define table names
        $this->tables = array(
            'campaigns' => $wpdb->prefix . 'vefify_campaigns',
            'questions' => $wpdb->prefix . 'vefify_questions',
            'question_options' => $wpdb->prefix . 'vefify_question_options',
            'gifts' => $wpdb->prefix . 'vefify_gifts',
            'participants' => $wpdb->prefix . 'vefify_participants',
            'quiz_sessions' => $wpdb->prefix . 'vefify_quiz_sessions',
            'analytics' => $wpdb->prefix . 'vefify_analytics'
        );
    }
    
    /**
     * Create all database tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        try {
            $this->create_campaigns_table();
            $this->create_questions_table();
            $this->create_question_options_table();
            $this->create_gifts_table();
            $this->create_participants_table();
            $this->create_quiz_sessions_table();
            $this->create_analytics_table();
            
            // Add foreign key constraints after all tables are created
            $this->add_foreign_key_constraints();
            
            return true;
            
        } catch (Exception $e) {
            error_log('Vefify Quiz Database Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create campaigns table
     */
    private function create_campaigns_table() {
        $table_name = $this->tables['campaigns'];
        
        $sql = "CREATE TABLE {$table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text DEFAULT NULL,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            max_participants int(11) DEFAULT NULL,
            allow_retake tinyint(1) DEFAULT 0,
            questions_per_quiz int(11) DEFAULT 5,
            time_limit int(11) DEFAULT NULL,
            pass_score int(11) DEFAULT 3,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slug (slug),
            KEY idx_active_campaigns (is_active, start_date, end_date)
        ) {$this->charset_collate};";
        
        dbDelta($sql);
        
        if ($this->wpdb->last_error) {
            throw new Exception("Error creating campaigns table: " . $this->wpdb->last_error);
        }
    }
    
    /**
     * Create questions table
     */
    private function create_questions_table() {
        $table_name = $this->tables['questions'];
        
        $sql = "CREATE TABLE {$table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) DEFAULT NULL,
            question_text text NOT NULL,
            question_type enum('multiple_choice','multiple_select','true_false') DEFAULT 'multiple_choice',
            category varchar(100) DEFAULT NULL,
            difficulty enum('easy','medium','hard') DEFAULT 'medium',
            points int(11) DEFAULT 1,
            explanation text DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            order_index int(11) DEFAULT 0,
            meta_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_campaign_questions (campaign_id, is_active),
            KEY idx_category (category),
            KEY idx_difficulty (difficulty)
        ) {$this->charset_collate};";
        
        dbDelta($sql);
        
        if ($this->wpdb->last_error) {
            throw new Exception("Error creating questions table: " . $this->wpdb->last_error);
        }
    }
    
    /**
     * Create question options table
     */
    private function create_question_options_table() {
        $table_name = $this->tables['question_options'];
        
        $sql = "CREATE TABLE {$table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            question_id int(11) NOT NULL,
            option_text text NOT NULL,
            is_correct tinyint(1) DEFAULT 0,
            explanation text DEFAULT NULL,
            order_index int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_question_options (question_id),
            KEY idx_correct_options (is_correct)
        ) {$this->charset_collate};";
        
        dbDelta($sql);
        
        if ($this->wpdb->last_error) {
            throw new Exception("Error creating question_options table: " . $this->wpdb->last_error);
        }
    }
    
    /**
     * Create gifts table
     */
    private function create_gifts_table() {
        $table_name = $this->tables['gifts'];
        
        $sql = "CREATE TABLE {$table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            gift_name varchar(255) NOT NULL,
            gift_type enum('voucher','discount','product','points') NOT NULL,
            gift_value varchar(100) NOT NULL,
            gift_description text DEFAULT NULL,
            min_score int(11) NOT NULL DEFAULT 0,
            max_score int(11) DEFAULT NULL,
            max_quantity int(11) DEFAULT NULL,
            used_count int(11) DEFAULT 0,
            gift_code_prefix varchar(20) DEFAULT NULL,
            api_endpoint varchar(255) DEFAULT NULL,
            api_params longtext DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_campaign_gifts (campaign_id, is_active),
            KEY idx_score_range (min_score, max_score)
        ) {$this->charset_collate};";
        
        dbDelta($sql);
        
        if ($this->wpdb->last_error) {
            throw new Exception("Error creating gifts table: " . $this->wpdb->last_error);
        }
    }
    
    /**
     * Create participants table - EXACT MATCH to your SQL structure
     */
    private function create_participants_table() {
        $table_name = $this->tables['participants'];
        
        $sql = "CREATE TABLE {$table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            session_id varchar(100) NOT NULL,
            participant_name varchar(255) DEFAULT NULL,
            participant_email varchar(255) DEFAULT NULL,
            participant_phone varchar(50) DEFAULT NULL,
            province varchar(100) DEFAULT NULL,
            pharmacy_code varchar(50) DEFAULT NULL,
            quiz_status enum('started','in_progress','completed','abandoned') DEFAULT 'started',
            start_time datetime DEFAULT CURRENT_TIMESTAMP,
            end_time datetime DEFAULT NULL,
            final_score int(11) DEFAULT 0,
            total_questions int(11) DEFAULT 0,
            completion_time int(11) DEFAULT NULL,
            answers_data longtext DEFAULT NULL,
            gift_id int(11) DEFAULT NULL,
            gift_code varchar(100) DEFAULT NULL,
            gift_status enum('none','assigned','claimed','expired') DEFAULT 'none',
            gift_response longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_campaign_phone (campaign_id, participant_phone),
            KEY idx_session (session_id),
            KEY idx_campaign_participants (campaign_id),
            KEY idx_quiz_status (quiz_status),
            KEY idx_participant_email (participant_email),
            KEY idx_gift_code (gift_code)
        ) {$this->charset_collate};";
        
        dbDelta($sql);
        
        if ($this->wpdb->last_error) {
            throw new Exception("Error creating participants table: " . $this->wpdb->last_error);
        }
    }
    
    /**
     * Create quiz sessions table
     */
    private function create_quiz_sessions_table() {
        $table_name = $this->tables['quiz_sessions'];
        
        $sql = "CREATE TABLE {$table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            participant_id int(11) NOT NULL,
            campaign_id int(11) NOT NULL,
            current_question int(11) DEFAULT 0,
            questions_data longtext DEFAULT NULL,
            answers_data longtext DEFAULT NULL,
            time_remaining int(11) DEFAULT NULL,
            is_completed tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_session (session_id),
            KEY idx_participant_session (participant_id, session_id),
            KEY idx_campaign_session (campaign_id)
        ) {$this->charset_collate};";
        
        dbDelta($sql);
        
        if ($this->wpdb->last_error) {
            throw new Exception("Error creating quiz_sessions table: " . $this->wpdb->last_error);
        }
    }
    
    /**
     * Create analytics table
     */
    private function create_analytics_table() {
        $table_name = $this->tables['analytics'];
        
        $sql = "CREATE TABLE {$table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            event_type enum('view','start','question_answer','complete','gift_claim') NOT NULL,
            participant_id int(11) DEFAULT NULL,
            session_id varchar(100) DEFAULT NULL,
            question_id int(11) DEFAULT NULL,
            event_data longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_campaign_analytics (campaign_id, event_type),
            KEY idx_event_tracking (event_type, created_at)
        ) {$this->charset_collate};";
        
        dbDelta($sql);
        
        if ($this->wpdb->last_error) {
            throw new Exception("Error creating analytics table: " . $this->wpdb->last_error);
        }
    }
    
    /**
     * Add foreign key constraints
     */
    private function add_foreign_key_constraints() {
        // Skip foreign keys for now to avoid dependency issues
        // They can be added later if needed
        return true;
    }
    
    /**
     * Insert sample data matching your SQL dump
     */
    public function insert_sample_data() {
        try {
            $this->insert_sample_campaigns();
            $this->insert_sample_questions();
            $this->insert_sample_question_options();
            $this->insert_sample_gifts();
            $this->insert_sample_participants();
            $this->insert_sample_analytics();
            
            return true;
            
        } catch (Exception $e) {
            error_log('Vefify Quiz Sample Data Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert sample campaigns
     */
    private function insert_sample_campaigns() {
        $campaigns = array(
            array(
                'name' => 'Test 1',
                'slug' => 'test-1',
                'description' => '',
                'start_date' => '2025-06-18 15:52:00',
                'end_date' => '2025-07-18 15:52:00',
                'is_active' => 1,
                'max_participants' => 0,
                'allow_retake' => 0,
                'questions_per_quiz' => 5,
                'time_limit' => 600,
                'pass_score' => 3,
                'meta_data' => '[]'
            ),
            array(
                'name' => 'Test 2',
                'slug' => 'test-2',
                'description' => '',
                'start_date' => '2025-06-18 15:52:00',
                'end_date' => '2025-07-18 15:52:00',
                'is_active' => 1,
                'max_participants' => 0,
                'allow_retake' => 0,
                'questions_per_quiz' => 5,
                'time_limit' => 600,
                'pass_score' => 3,
                'meta_data' => '[]'
            ),
            array(
                'name' => 'Health Knowledge Quiz 2024',
                'slug' => 'health-quiz-2024',
                'description' => 'Test your health and wellness knowledge',
                'start_date' => '2024-01-01 00:00:00',
                'end_date' => '2025-12-31 23:59:00',
                'is_active' => 1,
                'max_participants' => 1000,
                'allow_retake' => 0,
                'questions_per_quiz' => 5,
                'time_limit' => 600,
                'pass_score' => 3,
                'meta_data' => NULL
            )
        );
        
        foreach ($campaigns as $campaign) {
            $this->wpdb->insert($this->tables['campaigns'], $campaign);
        }
    }
    
    /**
     * Insert sample questions
     */
    private function insert_sample_questions() {
        $questions = array(
            array(
                'campaign_id' => 3,
                'question_text' => 'What is the recommended daily water intake for adults?',
                'question_type' => 'multiple_choice',
                'category' => 'nutrition',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'Adults should drink about 8 glasses (2 liters) of water daily.',
                'is_active' => 1,
                'order_index' => 0
            ),
            array(
                'campaign_id' => 3,
                'question_text' => 'Which vitamin is essential for bone health?',
                'question_type' => 'multiple_choice',
                'category' => 'nutrition',
                'difficulty' => 'medium',
                'points' => 2,
                'explanation' => 'Vitamin D helps the body absorb calcium for strong bones.',
                'is_active' => 1,
                'order_index' => 0
            ),
            array(
                'campaign_id' => 3,
                'question_text' => 'Exercise is important for maintaining good health.',
                'question_type' => 'true_false',
                'category' => '',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'Regular exercise is crucial for physical and mental health.',
                'is_active' => 1,
                'order_index' => 0
            )
        );
        
        foreach ($questions as $question) {
            $this->wpdb->insert($this->tables['questions'], $question);
        }
    }
    
    /**
     * Insert sample question options
     */
    private function insert_sample_question_options() {
        $options = array(
            // Question 1 options (Water intake)
            array('question_id' => 1, 'option_text' => '1 liter', 'is_correct' => 0, 'order_index' => 0),
            array('question_id' => 1, 'option_text' => '2 liter', 'is_correct' => 1, 'order_index' => 1),
            array('question_id' => 1, 'option_text' => '3 liter', 'is_correct' => 0, 'order_index' => 2),
            array('question_id' => 1, 'option_text' => '4 liter', 'is_correct' => 0, 'order_index' => 3),
            
            // Question 2 options (Vitamin for bones)
            array('question_id' => 2, 'option_text' => 'Vitamin A', 'is_correct' => 0, 'order_index' => 0),
            array('question_id' => 2, 'option_text' => 'Vitamin B', 'is_correct' => 0, 'order_index' => 1),
            array('question_id' => 2, 'option_text' => 'Vitamin C', 'is_correct' => 0, 'order_index' => 2),
            array('question_id' => 2, 'option_text' => 'Vitamin D', 'is_correct' => 1, 'order_index' => 3),
            
            // Question 3 options (True/False)
            array('question_id' => 3, 'option_text' => 'True', 'is_correct' => 1, 'order_index' => 0),
            array('question_id' => 3, 'option_text' => 'False', 'is_correct' => 0, 'order_index' => 1)
        );
        
        foreach ($options as $option) {
            $this->wpdb->insert($this->tables['question_options'], $option);
        }
    }
    
    /**
     * Insert sample gifts
     */
    private function insert_sample_gifts() {
        $gifts = array(
            array(
                'campaign_id' => 3,
                'gift_name' => '10% Discount Voucher',
                'gift_type' => 'discount',
                'gift_value' => '10%',
                'gift_description' => '10% discount on next purchase',
                'min_score' => 3,
                'max_score' => 4,
                'max_quantity' => 100,
                'used_count' => 0,
                'gift_code_prefix' => 'SAVE10',
                'is_active' => 1
            ),
            array(
                'campaign_id' => 3,
                'gift_name' => '50K VND Voucher',
                'gift_type' => 'voucher',
                'gift_value' => '50000 VND',
                'gift_description' => 'Cash voucher worth 50,000 VND',
                'min_score' => 5,
                'max_score' => NULL,
                'max_quantity' => 20,
                'used_count' => 0,
                'gift_code_prefix' => 'GIFT50K',
                'is_active' => 1
            )
        );
        
        foreach ($gifts as $gift) {
            $this->wpdb->insert($this->tables['gifts'], $gift);
        }
    }
    
    /**
     * Insert sample participants
     */
    private function insert_sample_participants() {
        $participants = array(
            array(
                'campaign_id' => 3,
                'session_id' => 'sess_1',
                'participant_name' => 'John Doe',
                'participant_email' => 'john@example.com',
                'participant_phone' => '+84901234567',
                'province' => 'Ho Chi Minh',
                'pharmacy_code' => 'PH001',
                'quiz_status' => 'completed',
                'start_time' => '2025-06-18 23:13:48',
                'end_time' => '2025-06-18 23:13:48',
                'final_score' => 4,
                'total_questions' => 5,
                'completion_time' => 180,
                'gift_status' => 'assigned',
                'completed_at' => '2025-06-18 23:13:48'
            ),
            array(
                'campaign_id' => 3,
                'session_id' => 'sess_2',
                'participant_name' => 'Jane Smith',
                'participant_email' => 'jane@example.com',
                'participant_phone' => '+84901234568',
                'province' => 'Ha Noi',
                'pharmacy_code' => 'PH002',
                'quiz_status' => 'in_progress',
                'start_time' => '2025-06-18 23:13:48',
                'final_score' => 2,
                'total_questions' => 5,
                'gift_status' => 'none',
                'completed_at' => '2025-06-25 00:00:00'
            ),
            array(
                'campaign_id' => 3,
                'session_id' => 'sess_3',
                'participant_name' => 'Mike Johnson',
                'participant_email' => 'mike@example.com',
                'participant_phone' => '+84901234569',
                'province' => 'Da Nang',
                'pharmacy_code' => 'PH003',
                'quiz_status' => 'completed',
                'start_time' => '2025-06-17 23:13:48',
                'end_time' => '2025-06-17 23:13:48',
                'final_score' => 5,
                'total_questions' => 5,
                'completion_time' => 120,
                'gift_id' => 1,
                'gift_status' => 'claimed',
                'completed_at' => '2025-06-17 23:13:48'
            )
        );
        
        foreach ($participants as $participant) {
            $this->wpdb->insert($this->tables['participants'], $participant);
        }
    }
    
    /**
     * Insert sample analytics
     */
    private function insert_sample_analytics() {
        $analytics = array(
            array(
                'campaign_id' => 3,
                'event_type' => 'complete',
                'event_data' => json_encode(array(
                    'gift_id' => 1,
                    'action' => 'updated',
                    'data' => array(
                        'gift_name' => '10% Discount Voucher',
                        'gift_type' => 'discount',
                        'gift_value' => '10%'
                    ),
                    'user_id' => 1,
                    'timestamp' => '2025-06-18 16:17:14'
                )),
                'ip_address' => '113.172.219.7',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'
            )
        );
        
        foreach ($analytics as $analytic) {
            $this->wpdb->insert($this->tables['analytics'], $analytic);
        }
    }
    
    /**
     * Get table name by key
     */
    public function get_table_name($table_key) {
        return isset($this->tables[$table_key]) ? $this->tables[$table_key] : false;
    }
    
    /**
     * Check if all tables exist
     */
    public function tables_exist() {
        foreach ($this->tables as $table_name) {
            if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Drop all tables (for cleanup)
     */
    public function drop_tables() {
        foreach (array_reverse($this->tables) as $table_name) {
            $this->wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }
    }
    
    /**
     * Get database statistics
     */
    public function get_database_stats() {
        $stats = array();
        
        foreach ($this->tables as $key => $table_name) {
            $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $stats[$key] = array(
                'table' => $table_name,
                'count' => intval($count),
                'exists' => ($this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name)
            );
        }
        
        return $stats;
    }
    
    /**
     * Execute prepared query safely
     */
    public function get_results($query, $params = array()) {
        if (empty($params)) {
            return $this->wpdb->get_results($query, ARRAY_A);
        }
        
        $prepared_query = $this->wpdb->prepare($query, $params);
        return $this->wpdb->get_results($prepared_query, ARRAY_A);
    }
    
    /**
     * Verify tables exist and have correct structure
     * Returns array of missing table names (for compatibility with validation helper)
     */
    public function verify_tables() {
        $missing_tables = array();
        
        foreach ($this->tables as $key => $table_name) {
            // Check if table exists
            if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
                $missing_tables[] = $key;
                continue;
            }
            
            // Check basic structure for participants table (most important)
            if ($key === 'participants') {
                $columns = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
                $column_names = array_map(function($col) { return $col->Field; }, $columns);
                
                $required_columns = array('completed_at', 'final_score', 'quiz_status', 'participant_name');
                $missing_columns = array_diff($required_columns, $column_names);
                
                if (!empty($missing_columns)) {
                    $missing_tables[] = $key . '_structure_invalid';
                }
            }
        }
        
        // Return simple array of missing tables (expected by validation helper)
        return $missing_tables;
    }
    
    /**
     * Detailed verification with full status info
     */
    public function verify_tables_detailed() {
        $missing_tables = array();
        $invalid_structures = array();
        
        foreach ($this->tables as $key => $table_name) {
            // Check if table exists
            if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
                $missing_tables[] = $key;
                continue;
            }
            
            // Check basic structure for participants table (most important)
            if ($key === 'participants') {
                $columns = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
                $column_names = array_map(function($col) { return $col->Field; }, $columns);
                
                $required_columns = array('completed_at', 'final_score', 'quiz_status', 'participant_name');
                $missing_columns = array_diff($required_columns, $column_names);
                
                if (!empty($missing_columns)) {
                    $invalid_structures[] = $key . ' (missing: ' . implode(', ', $missing_columns) . ')';
                }
            }
        }
        
        return array(
            'status' => empty($missing_tables) && empty($invalid_structures),
            'missing_tables' => $missing_tables,
            'invalid_structures' => $invalid_structures,
            'message' => empty($missing_tables) && empty($invalid_structures) ? 
                'All tables verified successfully' : 
                'Issues found: ' . implode(', ', array_merge($missing_tables, $invalid_structures))
        );
    }
    
    /**
     * Test database connection and queries
     */
    public function test_database() {
        $tests = array();
        
        // Test 1: Basic connection
        $tests['connection'] = array(
            'test' => 'Database Connection',
            'status' => empty($this->wpdb->last_error),
            'message' => $this->wpdb->last_error ?: 'Connected successfully'
        );
        
        // Test 2: Tables exist
        $tables_exist = $this->tables_exist();
        $tests['tables'] = array(
            'test' => 'Tables Exist',
            'status' => $tables_exist,
            'message' => $tables_exist ? 'All tables exist' : 'Some tables missing'
        );
        
        // Test 3: Sample query with completed_at
        if ($tables_exist) {
            try {
                $participants_table = $this->tables['participants'];
                $result = $this->wpdb->get_row("
                    SELECT COUNT(*) as total, 
                           COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed, 
                           COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as with_gifts, 
                           AVG(CASE WHEN final_score > 0 THEN final_score END) as avg_score 
                    FROM {$participants_table} WHERE 1=1
                ");
                
                $tests['query'] = array(
                    'test' => 'Sample Query (completed_at)',
                    'status' => ($result !== null),
                    'message' => $result ? 
                        "Query successful - Total: {$result->total}, Completed: {$result->completed}" : 
                        'Query failed: ' . $this->wpdb->last_error
                );
            } catch (Exception $e) {
                $tests['query'] = array(
                    'test' => 'Sample Query (completed_at)',
                    'status' => false,
                    'message' => 'Query error: ' . $e->getMessage()
                );
            }
        }
        
        return $tests;
    }
}

// Initialize database class
if (!function_exists('vefify_quiz_get_database')) {
    function vefify_quiz_get_database() {
        static $database = null;
        if ($database === null) {
            $database = new Vefify_Quiz_Database();
        }
        return $database;
    }
}
?>