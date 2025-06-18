<?php
/**
 * FIXED Database Management Class
 * File: includes/class-database.php
 * Updated to match analytics expected column names
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Database {
    
    private $wpdb;
    private $table_prefix;
    private $tables;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        $this->define_tables();
    }
    
    private function define_tables() {
        $this->tables = array(
            'campaigns' => $this->table_prefix . 'campaigns',
            'questions' => $this->table_prefix . 'questions',
            'question_options' => $this->table_prefix . 'question_options',
            'gifts' => $this->table_prefix . 'gifts',
            'participants' => $this->table_prefix . 'participants',
            'quiz_sessions' => $this->table_prefix . 'quiz_sessions',
            'analytics' => $this->table_prefix . 'analytics'
        );
    }
    
    /**
     * FIXED: Create all database tables with correct column names
     */
    public function create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $charset_collate = $this->wpdb->get_charset_collate();
    $table_prefix = $this->table_prefix;
    
    // First, let's check and drop existing tables to avoid conflicts
    $this->drop_existing_tables_if_needed();
    
    // IMPORTANT: Create tables in correct order to avoid foreign key issues
    $tables = array();
    
    // 1. Campaigns table (referenced by others)
    $tables['campaigns'] = "CREATE TABLE {$table_prefix}campaigns (
        id int(11) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        description text,
        start_date datetime NOT NULL,
        end_date datetime NOT NULL,
        is_active tinyint(1) DEFAULT 1,
        max_participants int(11) DEFAULT NULL,
        allow_retake tinyint(1) DEFAULT 0,
        questions_per_quiz int(11) DEFAULT 5,
        time_limit int(11) DEFAULT NULL,
        pass_score int(11) DEFAULT 3,
        meta_data longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_slug (slug),
        KEY idx_active_campaigns (is_active, start_date, end_date)
    ) $charset_collate;";
    
    // 2. Questions table (referenced by question_options)
    $tables['questions'] = "CREATE TABLE {$table_prefix}questions (
        id int(11) NOT NULL AUTO_INCREMENT,
        campaign_id int(11) DEFAULT NULL,
        question_text text NOT NULL,
        question_type enum('multiple_choice', 'multiple_select', 'true_false') DEFAULT 'multiple_choice',
        category varchar(100) DEFAULT NULL,
        difficulty enum('easy', 'medium', 'hard') DEFAULT 'medium',
        points int(11) DEFAULT 1,
        explanation text,
        is_active tinyint(1) DEFAULT 1,
        order_index int(11) DEFAULT 0,
        meta_data longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_campaign_questions (campaign_id, is_active),
        KEY idx_category (category),
        KEY idx_difficulty (difficulty)
    ) $charset_collate;";
    
    // 3. Question Options table
    $tables['question_options'] = "CREATE TABLE {$table_prefix}question_options (
        id int(11) NOT NULL AUTO_INCREMENT,
        question_id int(11) NOT NULL,
        option_text text NOT NULL,
        is_correct tinyint(1) DEFAULT 0,
        explanation text,
        order_index int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_question_options (question_id),
        KEY idx_correct_options (is_correct)
    ) $charset_collate;";
    
    // 4. Gifts table
    $tables['gifts'] = "CREATE TABLE {$table_prefix}gifts (
        id int(11) NOT NULL AUTO_INCREMENT,
        campaign_id int(11) NOT NULL,
        gift_name varchar(255) NOT NULL,
        gift_type enum('voucher', 'discount', 'product', 'points') NOT NULL,
        gift_value varchar(100) NOT NULL,
        gift_description text,
        min_score int(11) NOT NULL DEFAULT 0,
        max_score int(11) DEFAULT NULL,
        max_quantity int(11) DEFAULT NULL,
        used_count int(11) DEFAULT 0,
        gift_code_prefix varchar(20) DEFAULT NULL,
        api_endpoint varchar(255) DEFAULT NULL,
        api_params longtext,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_campaign_gifts (campaign_id, is_active),
        KEY idx_score_range (min_score, max_score)
    ) $charset_collate;";
    
    // 5. Participants table - FIXED WITH CORRECT COLUMN NAMES
    $tables['participants'] = "CREATE TABLE {$table_prefix}participants (
        id int(11) NOT NULL AUTO_INCREMENT,
        campaign_id int(11) NOT NULL,
        session_id varchar(100) NOT NULL,
        participant_name varchar(255),
        participant_email varchar(255),
        participant_phone varchar(50),
        province varchar(100) DEFAULT NULL,
        pharmacy_code varchar(50) DEFAULT NULL,
        quiz_status enum('started','in_progress','completed','abandoned') DEFAULT 'started',
        start_time datetime DEFAULT CURRENT_TIMESTAMP,
        end_time datetime DEFAULT NULL,
        final_score int(11) DEFAULT 0,
        total_questions int(11) DEFAULT 0,
        completion_time int(11) DEFAULT NULL,
        answers_data longtext,
        gift_id int(11) DEFAULT NULL,
        gift_code varchar(100) DEFAULT NULL,
        gift_status enum('none', 'assigned', 'claimed', 'expired') DEFAULT 'none',
        gift_response longtext,
        ip_address varchar(45),
        user_agent text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_campaign_phone (campaign_id, participant_phone),
        KEY idx_session (session_id),
        KEY idx_campaign_participants (campaign_id),
        KEY idx_quiz_status (quiz_status),
        KEY idx_participant_email (participant_email),
        KEY idx_gift_code (gift_code)
    ) $charset_collate;";
    
    // 6. Quiz Sessions table
    $tables['quiz_sessions'] = "CREATE TABLE {$table_prefix}quiz_sessions (
        id int(11) NOT NULL AUTO_INCREMENT,
        session_id varchar(100) NOT NULL,
        participant_id int(11) NOT NULL,
        campaign_id int(11) NOT NULL,
        current_question int(11) DEFAULT 0,
        questions_data longtext,
        answers_data longtext,
        time_remaining int(11) DEFAULT NULL,
        is_completed tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_session (session_id),
        KEY idx_participant_session (participant_id, session_id),
        KEY idx_campaign_session (campaign_id)
    ) $charset_collate;";
    
    // 7. Analytics table
    $tables['analytics'] = "CREATE TABLE {$table_prefix}analytics (
        id int(11) NOT NULL AUTO_INCREMENT,
        campaign_id int(11) NOT NULL,
        event_type enum('view', 'start', 'question_answer', 'complete', 'gift_claim') NOT NULL,
        participant_id int(11) DEFAULT NULL,
        session_id varchar(100) DEFAULT NULL,
        question_id int(11) DEFAULT NULL,
        event_data longtext,
        ip_address varchar(45) DEFAULT NULL,
        user_agent text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_campaign_analytics (campaign_id, event_type),
        KEY idx_event_tracking (event_type, created_at)
    ) $charset_collate;";
    
    // Create tables using dbDelta (WordPress way)
    foreach ($tables as $table_name => $sql) {
        error_log("Creating table: {$table_name}");
        $result = dbDelta($sql);
        
        if ($this->wpdb->last_error) {
            error_log("Vefify Quiz: Error creating table {$table_name}: " . $this->wpdb->last_error);
            // Don't throw exception, continue with other tables
        }
    }
    
    return true;
}

/**
 * Helper method to drop existing tables safely
 */
    private function drop_existing_tables_if_needed() {
    // Check if we need to recreate due to structure conflicts
    $participants_table = $this->tables['participants'];
    
    // Check if table exists and has wrong structure
    $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$participants_table}'");
    
    if ($table_exists) {
        // Check if it has the old column structure
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM {$participants_table}");
        $has_old_structure = false;
        
        foreach ($columns as $column) {
            if ($column->Field === 'full_name' || $column->Field === 'phone_number') {
                $has_old_structure = true;
                break;
            }
        }
        
        if ($has_old_structure) {
            error_log("Vefify Quiz: Dropping existing tables with old structure");
            
            // Drop tables in reverse order to handle foreign keys
            $tables_to_drop = array_reverse($this->tables);
            
            foreach ($tables_to_drop as $table_name) {
                $this->wpdb->query("DROP TABLE IF EXISTS {$table_name}");
            }
        }
    }
}
    /**
     * FIXED: Add foreign key constraints separately to avoid dependency issues
     */
    private function add_foreign_key_constraints() {
        $constraints = array();
        
        // Only add constraints if they don't already exist
        $existing_constraints = $this->get_existing_foreign_keys();
        
        // Questions -> Campaigns
        if (!in_array('fk_questions_campaign', $existing_constraints)) {
            $constraints[] = "ALTER TABLE {$this->tables['questions']} 
                           ADD CONSTRAINT fk_questions_campaign 
                           FOREIGN KEY (campaign_id) REFERENCES {$this->tables['campaigns']}(id) 
                           ON DELETE CASCADE";
        }
        
        // Question Options -> Questions  
        if (!in_array('fk_options_question', $existing_constraints)) {
            $constraints[] = "ALTER TABLE {$this->tables['question_options']} 
                           ADD CONSTRAINT fk_options_question 
                           FOREIGN KEY (question_id) REFERENCES {$this->tables['questions']}(id) 
                           ON DELETE CASCADE";
        }
        
        // Gifts -> Campaigns
        if (!in_array('fk_gifts_campaign', $existing_constraints)) {
            $constraints[] = "ALTER TABLE {$this->tables['gifts']} 
                           ADD CONSTRAINT fk_gifts_campaign 
                           FOREIGN KEY (campaign_id) REFERENCES {$this->tables['campaigns']}(id) 
                           ON DELETE CASCADE";
        }
        
        // Participants -> Campaigns
        if (!in_array('fk_participants_campaign', $existing_constraints)) {
            $constraints[] = "ALTER TABLE {$this->tables['participants']} 
                           ADD CONSTRAINT fk_participants_campaign 
                           FOREIGN KEY (campaign_id) REFERENCES {$this->tables['campaigns']}(id) 
                           ON DELETE CASCADE";
        }
        
        // Participants -> Gifts (optional)
        if (!in_array('fk_participants_gift', $existing_constraints)) {
            $constraints[] = "ALTER TABLE {$this->tables['participants']} 
                           ADD CONSTRAINT fk_participants_gift 
                           FOREIGN KEY (gift_id) REFERENCES {$this->tables['gifts']}(id) 
                           ON DELETE SET NULL";
        }
        
        // Quiz Sessions -> Participants
        if (!in_array('fk_sessions_participant', $existing_constraints)) {
            $constraints[] = "ALTER TABLE {$this->tables['quiz_sessions']} 
                           ADD CONSTRAINT fk_sessions_participant 
                           FOREIGN KEY (participant_id) REFERENCES {$this->tables['participants']}(id) 
                           ON DELETE CASCADE";
        }
        
        // Quiz Sessions -> Campaigns
        if (!in_array('fk_sessions_campaign', $existing_constraints)) {
            $constraints[] = "ALTER TABLE {$this->tables['quiz_sessions']} 
                           ADD CONSTRAINT fk_sessions_campaign 
                           FOREIGN KEY (campaign_id) REFERENCES {$this->tables['campaigns']}(id) 
                           ON DELETE CASCADE";
        }
        
        // Analytics -> Campaigns
        if (!in_array('fk_analytics_campaign', $existing_constraints)) {
            $constraints[] = "ALTER TABLE {$this->tables['analytics']} 
                           ADD CONSTRAINT fk_analytics_campaign 
                           FOREIGN KEY (campaign_id) REFERENCES {$this->tables['campaigns']}(id) 
                           ON DELETE CASCADE";
        }
        
        // Execute constraints
        foreach ($constraints as $constraint_sql) {
            $result = $this->wpdb->query($constraint_sql);
            
            if ($result === false) {
                error_log("Vefify Quiz: Foreign key constraint failed: " . $this->wpdb->last_error);
                error_log("SQL: " . $constraint_sql);
                // Don't throw exception for foreign keys - they're optional for functionality
            }
        }
    }
    
    /**
     * Get existing foreign key constraints
     */
    private function get_existing_foreign_keys() {
        $constraints = array();
        
        foreach ($this->tables as $table_name) {
            $results = $this->wpdb->get_results("
                SELECT CONSTRAINT_NAME
                FROM information_schema.REFERENTIAL_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$table_name}'
            ");
            
            foreach ($results as $row) {
                $constraints[] = $row->CONSTRAINT_NAME;
            }
        }
        
        return $constraints;
    }
    
    /**
     * Get table name by key
     */
    public function get_table_name($table_key) {
        return isset($this->tables[$table_key]) ? $this->tables[$table_key] : null;
    }
    
    /**
     * Get all table names
     */
    public function get_all_tables() {
        return $this->tables;
    }
    
    /**
     * Check if tables exist
     */
    public function verify_tables() {
        $missing_tables = array();
        
        foreach ($this->tables as $key => $table_name) {
            $table_exists = $this->wpdb->get_var(
                $this->wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
            );
            
            if (!$table_exists) {
                $missing_tables[] = $key;
            }
        }
        
        return $missing_tables;
    }
    
    /**
     * Get WordPress database instance
     */
    public function get_wpdb() {
        return $this->wpdb;
    }
    
    /**
     * Insert sample data after tables are created
     */
    public function insert_sample_data() {
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Insert sample campaign
            $campaign_id = $this->insert_sample_campaign();
            
            // Insert sample questions with options
            $question_ids = $this->insert_sample_questions($campaign_id);
            
            // Insert sample gifts
            $this->insert_sample_gifts($campaign_id);
            
            // Insert sample participants - USING CORRECT COLUMN NAMES
            $this->insert_sample_participants($campaign_id);
            
            $this->wpdb->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('Sample data insertion failed: ' . $e->getMessage());
            return false;
        }
    }
    
    private function insert_sample_campaign() {
        $campaign_data = array(
            'name' => 'Health Knowledge Quiz 2024',
            'slug' => 'health-quiz-2024',
            'description' => 'Test your health and wellness knowledge',
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => '2024-12-31 23:59:59',
            'is_active' => 1,
            'max_participants' => 1000,
            'questions_per_quiz' => 5,
            'time_limit' => 600,
            'pass_score' => 3,
            'created_at' => current_time('mysql')
        );
        
        $result = $this->wpdb->insert($this->tables['campaigns'], $campaign_data);
        
        if ($result === false) {
            throw new Exception('Failed to insert sample campaign: ' . $this->wpdb->last_error);
        }
        
        return $this->wpdb->insert_id;
    }
    
    private function insert_sample_questions($campaign_id) {
        $questions = array(
            array(
                'question_text' => 'What is the recommended daily water intake for adults?',
                'question_type' => 'multiple_choice',
                'category' => 'nutrition',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'Adults should drink about 8 glasses (2 liters) of water daily.',
                'options' => array(
                    array('option_text' => '1 liter', 'is_correct' => 0),
                    array('option_text' => '2 liters', 'is_correct' => 1),
                    array('option_text' => '3 liters', 'is_correct' => 0),
                    array('option_text' => '4 liters', 'is_correct' => 0)
                )
            ),
            array(
                'question_text' => 'Which vitamin is essential for bone health?',
                'question_type' => 'multiple_choice', 
                'category' => 'nutrition',
                'difficulty' => 'medium',
                'points' => 2,
                'explanation' => 'Vitamin D helps the body absorb calcium for strong bones.',
                'options' => array(
                    array('option_text' => 'Vitamin A', 'is_correct' => 0),
                    array('option_text' => 'Vitamin C', 'is_correct' => 0),
                    array('option_text' => 'Vitamin D', 'is_correct' => 1),
                    array('option_text' => 'Vitamin E', 'is_correct' => 0)
                )
            ),
            array(
                'question_text' => 'Exercise is important for maintaining good health.',
                'question_type' => 'true_false',
                'category' => 'fitness',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'Regular exercise is crucial for physical and mental health.',
                'options' => array(
                    array('option_text' => 'True', 'is_correct' => 1),
                    array('option_text' => 'False', 'is_correct' => 0)
                )
            )
        );
        
        $question_ids = array();
        
        foreach ($questions as $question_data) {
            $options = $question_data['options'];
            unset($question_data['options']);
            
            $question_data['campaign_id'] = $campaign_id;
            $question_data['is_active'] = 1;
            $question_data['created_at'] = current_time('mysql');
            
            $result = $this->wpdb->insert($this->tables['questions'], $question_data);
            
            if ($result === false) {
                throw new Exception('Failed to insert question: ' . $this->wpdb->last_error);
            }
            
            $question_id = $this->wpdb->insert_id;
            $question_ids[] = $question_id;
            
            // Insert options
            foreach ($options as $index => $option) {
                $option['question_id'] = $question_id;
                $option['order_index'] = $index;
                $option['created_at'] = current_time('mysql');
                
                $result = $this->wpdb->insert($this->tables['question_options'], $option);
                
                if ($result === false) {
                    throw new Exception('Failed to insert option: ' . $this->wpdb->last_error);
                }
            }
        }
        
        return $question_ids;
    }
    
    private function insert_sample_gifts($campaign_id) {
        $gifts = array(
            array(
                'campaign_id' => $campaign_id,
                'gift_name' => '10% Discount Voucher',
                'gift_type' => 'discount',
                'gift_value' => '10%',
                'gift_description' => '10% discount on next purchase',
                'min_score' => 3,
                'max_score' => 4,
                'max_quantity' => 100,
                'gift_code_prefix' => 'SAVE10',
                'is_active' => 1,
                'created_at' => current_time('mysql')
            ),
            array(
                'campaign_id' => $campaign_id,
                'gift_name' => '50K VND Voucher',
                'gift_type' => 'voucher',
                'gift_value' => '50000 VND',
                'gift_description' => 'Cash voucher worth 50,000 VND',
                'min_score' => 5,
                'max_score' => null,
                'max_quantity' => 20,
                'gift_code_prefix' => 'GIFT50K',
                'is_active' => 1,
                'created_at' => current_time('mysql')
            )
        );
        
        foreach ($gifts as $gift_data) {
            $result = $this->wpdb->insert($this->tables['gifts'], $gift_data);
            
            if ($result === false) {
                throw new Exception('Failed to insert gift: ' . $this->wpdb->last_error);
            }
        }
    }
    
    /**
     * Insert sample participants - USING CORRECT COLUMN NAMES
     */
    private function insert_sample_participants($campaign_id) {
        $participants = array(
            array(
                'campaign_id' => $campaign_id,
                'session_id' => 'sess_' . uniqid(),
                'participant_name' => 'John Doe',
                'participant_email' => 'john@example.com',
                'participant_phone' => '+84901234567',
                'province' => 'Ho Chi Minh',
                'pharmacy_code' => 'PH001',
                'quiz_status' => 'completed',
                'start_time' => current_time('mysql'),
                'end_time' => current_time('mysql'),
                'final_score' => 4,
                'total_questions' => 5,
                'completion_time' => 180,
                'gift_status' => 'assigned',
                'created_at' => current_time('mysql')
            ),
            array(
                'campaign_id' => $campaign_id,
                'session_id' => 'sess_' . uniqid(),
                'participant_name' => 'Jane Smith',
                'participant_email' => 'jane@example.com',
                'participant_phone' => '+84901234568',
                'province' => 'Ha Noi',
                'pharmacy_code' => 'PH002',
                'quiz_status' => 'in_progress',
                'start_time' => current_time('mysql'),
                'final_score' => 2,
                'total_questions' => 5,
                'gift_status' => 'none',
                'created_at' => current_time('mysql')
            ),
            array(
                'campaign_id' => $campaign_id,
                'session_id' => 'sess_' . uniqid(),
                'participant_name' => 'Mike Johnson',
                'participant_email' => 'mike@example.com',
                'participant_phone' => '+84901234569',
                'province' => 'Da Nang',
                'pharmacy_code' => 'PH003',
                'quiz_status' => 'completed',
                'start_time' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'end_time' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'final_score' => 5,
                'total_questions' => 5,
                'completion_time' => 120,
                'gift_status' => 'claimed',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            )
        );
        
        foreach ($participants as $participant_data) {
            $result = $this->wpdb->insert($this->tables['participants'], $participant_data);
            
            if ($result === false) {
                throw new Exception('Failed to insert participant: ' . $this->wpdb->last_error);
            }
        }
    }
    
    /**
     * Clean database
     */
    public function drop_all_tables() {
        // Drop in reverse order to handle foreign keys
        $tables_to_drop = array_reverse($this->tables);
        
        foreach ($tables_to_drop as $table_name) {
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
                'count' => intval($count)
            );
        }
        
        return $stats;
    }
	/**
     * Database query methods for module compatibility
     */
    
    /**
 * Get results from database - FIXED
 */
public function get_results($query, $output_type = OBJECT) {
    // Ensure $output_type is a valid constant, not an array
    if (is_array($output_type)) {
        $output_type = OBJECT;
    }
    
    // Valid output types
    $valid_types = array(OBJECT, OBJECT_K, ARRAY_A, ARRAY_N);
    if (!in_array($output_type, $valid_types)) {
        $output_type = OBJECT;
    }
    
    return $this->wpdb->get_results($query, $output_type);
}

/**
 * Get single row from database - FIXED
 */
public function get_row($query, $output_type = OBJECT, $row_offset = 0) {
    // Ensure $output_type is a valid constant
    if (is_array($output_type)) {
        $output_type = OBJECT;
    }
    
    $valid_types = array(OBJECT, ARRAY_A, ARRAY_N);
    if (!in_array($output_type, $valid_types)) {
        $output_type = OBJECT;
    }
    
    return $this->wpdb->get_row($query, $output_type, $row_offset);
}

/**
 * Get single variable from database
 */
public function get_var($query, $column_offset = 0, $row_offset = 0) {
    return $this->wpdb->get_var($query, $column_offset, $row_offset);
}

/**
 * Insert data into table
 */
public function insert($table, $data, $format = null) {
    return $this->wpdb->insert($table, $data, $format);
}

/**
 * Update data in table
 */
public function update($table, $data, $where, $format = null, $where_format = null) {
    return $this->wpdb->update($table, $data, $where, $format, $where_format);
}

/**
 * Delete data from table
 */
public function delete($table, $where, $where_format = null) {
    return $this->wpdb->delete($table, $where, $where_format);
}

/**
 * Prepare SQL query
 */
public function prepare($query, ...$args) {
    return $this->wpdb->prepare($query, ...$args);
}

/**
 * Execute a query
 */
public function query($query) {
    return $this->wpdb->query($query);
}

/**
 * Get last insert ID
 */
public function get_insert_id() {
    return $this->wpdb->insert_id;
}

/**
 * Get last error
 */
public function get_last_error() {
    return $this->wpdb->last_error;
}


}