<?php
/**
 * Database Management Class
 * File: includes/class-database.php
 * 
 * Handles all database operations with consistent table naming
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Database {
    
    private $wpdb;
    private $table_prefix;
    private $tables;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        $this->define_tables();
    }
    
    /**
     * Define all table names
     */
    private function define_tables() {
        $this->tables = array(
            'campaigns' => $this->table_prefix . 'campaigns',
            'questions' => $this->table_prefix . 'questions',
            'question_options' => $this->table_prefix . 'question_options',
            'gifts' => $this->table_prefix . 'gifts',
            'participants' => $this->table_prefix . 'participants', // FIXED: Consistent naming
            'quiz_sessions' => $this->table_prefix . 'quiz_sessions',
            'analytics' => $this->table_prefix . 'analytics'
        );
    }
    
    /**
     * Get table name
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
     * Check if all tables exist
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
     * Insert sample data - FIXED VERSION
     */
    public function insert_sample_data() {
        // Start transaction for data integrity
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Insert sample campaign
            $campaign_id = $this->insert_sample_campaign();
            
            // Insert sample questions
            $question_ids = $this->insert_sample_questions($campaign_id);
            
            // Insert sample gifts
            $this->insert_sample_gifts($campaign_id);
            
            // Commit transaction
            $this->wpdb->query('COMMIT');
            
            error_log('Vefify Quiz: Sample data inserted successfully');
            return true;
            
        } catch (Exception $e) {
            // Rollback on error
            $this->wpdb->query('ROLLBACK');
            error_log('Vefify Quiz: Sample data insertion failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Insert sample campaign
     */
    private function insert_sample_campaign() {
        $campaign_data = array(
            'name' => 'Health Knowledge Quiz 2024',
            'slug' => 'health-quiz-2024',
            'description' => 'Test your health and wellness knowledge to win amazing prizes!',
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => '2024-12-31 23:59:59',
            'is_active' => 1,
            'max_participants' => 10000,
            'questions_per_quiz' => 5,
            'time_limit' => 600, // 10 minutes
            'pass_score' => 3,
            'meta_data' => json_encode(array(
                'welcome_message' => 'Welcome to our Health Quiz!',
                'completion_message' => 'Thank you for participating!',
                'theme' => 'health',
                'colors' => array(
                    'primary' => '#4facfe',
                    'secondary' => '#00f2fe'
                )
            ))
        );
        
        $result = $this->wpdb->insert(
            $this->tables['campaigns'],
            $campaign_data,
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s')
        );
        
        if (!$result) {
            throw new Exception('Failed to insert sample campaign: ' . $this->wpdb->last_error);
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Insert sample questions
     */
    private function insert_sample_questions($campaign_id) {
        $questions_data = array(
            array(
                'question_text' => 'What is Aspirin commonly used for?',
                'question_type' => 'multiple_select',
                'category' => 'medication',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'Aspirin is a versatile medication used primarily for pain relief and fever reduction.',
                'options' => array(
                    array('text' => 'Pain relief', 'correct' => true),
                    array('text' => 'Fever reduction', 'correct' => true),
                    array('text' => 'Sleep aid', 'correct' => false),
                    array('text' => 'Anxiety treatment', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'Which vitamin is essential for bone health?',
                'question_type' => 'multiple_choice',
                'category' => 'nutrition',
                'difficulty' => 'medium',
                'points' => 1,
                'explanation' => 'Vitamin D helps the body absorb calcium, which is crucial for bone health.',
                'options' => array(
                    array('text' => 'Vitamin A', 'correct' => false),
                    array('text' => 'Vitamin C', 'correct' => false),
                    array('text' => 'Vitamin D', 'correct' => true),
                    array('text' => 'Vitamin E', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'What should you do before taking any medication?',
                'question_type' => 'multiple_select',
                'category' => 'safety',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'Always read instructions and consult healthcare providers for safe medication use.',
                'options' => array(
                    array('text' => 'Read the instructions', 'correct' => true),
                    array('text' => 'Consult a healthcare provider', 'correct' => true),
                    array('text' => 'Take it immediately', 'correct' => false),
                    array('text' => 'Double the dose', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'How often should you wash your hands?',
                'question_type' => 'multiple_choice',
                'category' => 'hygiene',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'Regular handwashing before meals and after using the restroom is essential for health.',
                'options' => array(
                    array('text' => 'Once a day', 'correct' => false),
                    array('text' => 'Before meals and after using restroom', 'correct' => true),
                    array('text' => 'Only when dirty', 'correct' => false),
                    array('text' => 'Never', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'What is the recommended daily water intake for adults?',
                'question_type' => 'multiple_choice',
                'category' => 'nutrition',
                'difficulty' => 'medium',
                'points' => 1,
                'explanation' => 'Most adults should drink 8-10 glasses (about 2-2.5 liters) of water daily.',
                'options' => array(
                    array('text' => '1-2 glasses', 'correct' => false),
                    array('text' => '8-10 glasses (2-2.5 liters)', 'correct' => true),
                    array('text' => '15-20 glasses', 'correct' => false),
                    array('text' => 'As little as possible', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'Exercise is beneficial for mental health.',
                'question_type' => 'true_false',
                'category' => 'wellness',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'Regular exercise releases endorphins and reduces stress, benefiting mental health.',
                'options' => array(
                    array('text' => 'True', 'correct' => true),
                    array('text' => 'False', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'Which of these are symptoms of dehydration?',
                'question_type' => 'multiple_select',
                'category' => 'wellness',
                'difficulty' => 'medium',
                'points' => 2,
                'explanation' => 'Dehydration can cause multiple symptoms affecting various body systems.',
                'options' => array(
                    array('text' => 'Headache', 'correct' => true),
                    array('text' => 'Dizziness', 'correct' => true),
                    array('text' => 'Excessive energy', 'correct' => false),
                    array('text' => 'Dark urine', 'correct' => true),
                    array('text' => 'Increased appetite', 'correct' => false)
                )
            ),
            array(
                'question_text' => 'What is the normal resting heart rate for adults?',
                'question_type' => 'multiple_choice',
                'category' => 'wellness',
                'difficulty' => 'hard',
                'points' => 2,
                'explanation' => 'A normal resting heart rate for adults ranges from 60 to 100 beats per minute.',
                'options' => array(
                    array('text' => '40-60 beats per minute', 'correct' => false),
                    array('text' => '60-100 beats per minute', 'correct' => true),
                    array('text' => '100-140 beats per minute', 'correct' => false),
                    array('text' => '140-180 beats per minute', 'correct' => false)
                )
            )
        );
        
        $question_ids = array();
        
        foreach ($questions_data as $index => $question_data) {
            // Insert question
            $question = array(
                'campaign_id' => $campaign_id,
                'question_text' => $question_data['question_text'],
                'question_type' => $question_data['question_type'],
                'category' => $question_data['category'],
                'difficulty' => $question_data['difficulty'],
                'points' => $question_data['points'],
                'explanation' => $question_data['explanation'],
                'order_index' => $index + 1,
                'is_active' => 1
            );
            
            $result = $this->wpdb->insert(
                $this->tables['questions'],
                $question,
                array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d')
            );
            
            if (!$result) {
                throw new Exception('Failed to insert question: ' . $question_data['question_text']);
            }
            
            $question_id = $this->wpdb->insert_id;
            $question_ids[] = $question_id;
            
            // Insert options
            foreach ($question_data['options'] as $option_index => $option) {
                $option_data = array(
                    'question_id' => $question_id,
                    'option_text' => $option['text'],
                    'is_correct' => $option['correct'] ? 1 : 0,
                    'order_index' => $option_index + 1
                );
                
                $result = $this->wpdb->insert(
                    $this->tables['question_options'],
                    $option_data,
                    array('%d', '%s', '%d', '%d')
                );
                
                if (!$result) {
                    throw new Exception('Failed to insert option: ' . $option['text']);
                }
            }
        }
        
        return $question_ids;
    }
    
    /**
     * Insert sample gifts
     */
    private function insert_sample_gifts($campaign_id) {
        $gifts_data = array(
            array(
                'gift_name' => 'Participation Certificate',
                'gift_type' => 'product',
                'gift_value' => 'Digital Certificate',
                'gift_description' => 'Thank you for participating in our health quiz!',
                'min_score' => 1,
                'max_score' => 2,
                'max_quantity' => null,
                'gift_code_prefix' => 'CERT'
            ),
            array(
                'gift_name' => '10% Discount Voucher',
                'gift_type' => 'discount',
                'gift_value' => '10%',
                'gift_description' => '10% discount on your next purchase',
                'min_score' => 3,
                'max_score' => 4,
                'max_quantity' => 100,
                'gift_code_prefix' => 'SAVE10'
            ),
            array(
                'gift_name' => '50,000 VND Voucher',
                'gift_type' => 'voucher',
                'gift_value' => '50,000 VND',
                'gift_description' => 'Cash voucher worth 50,000 VND',
                'min_score' => 5,
                'max_score' => null,
                'max_quantity' => 20,
                'gift_code_prefix' => 'GIFT50K'
            ),
            array(
                'gift_name' => 'Health Consultation',
                'gift_type' => 'product',
                'gift_value' => 'Free 30-min consultation',
                'gift_description' => 'Free health consultation with our experts',
                'min_score' => 8,
                'max_score' => null,
                'max_quantity' => 10,
                'gift_code_prefix' => 'HEALTH'
            )
        );
        
        foreach ($gifts_data as $gift_data) {
            $gift = array(
                'campaign_id' => $campaign_id,
                'gift_name' => $gift_data['gift_name'],
                'gift_type' => $gift_data['gift_type'],
                'gift_value' => $gift_data['gift_value'],
                'gift_description' => $gift_data['gift_description'],
                'min_score' => $gift_data['min_score'],
                'max_score' => $gift_data['max_score'],
                'max_quantity' => $gift_data['max_quantity'],
                'used_count' => 0,
                'gift_code_prefix' => $gift_data['gift_code_prefix'],
                'is_active' => 1
            );
            
            $result = $this->wpdb->insert(
                $this->tables['gifts'],
                $gift,
                array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d')
            );
            
            if (!$result) {
                throw new Exception('Failed to insert gift: ' . $gift_data['gift_name']);
            }
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
     * Clean up expired data
     */
    public function cleanup_expired_data() {
        $cleaned = array();
        
        // Clean expired sessions (older than 24 hours, not completed)
        $sessions_cleaned = $this->wpdb->query("
            DELETE FROM {$this->tables['quiz_sessions']} 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) 
            AND is_completed = 0
        ");
        $cleaned['sessions'] = $sessions_cleaned;
        
        // Clean old analytics (older than 90 days)
        $analytics_cleaned = $this->wpdb->query("
            DELETE FROM {$this->tables['analytics']} 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $cleaned['analytics'] = $analytics_cleaned;
        
        // Clean abandoned participants (started but never completed, older than 48 hours)
        $participants_cleaned = $this->wpdb->query("
            DELETE FROM {$this->tables['participants']} 
            WHERE completed_at IS NULL 
            AND created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ");
        $cleaned['participants'] = $participants_cleaned;
        
        return $cleaned;
    }
    
    /**
     * Optimize database tables
     */
    public function optimize_tables() {
        $optimized = array();
        
        foreach ($this->tables as $key => $table_name) {
            $result = $this->wpdb->query("OPTIMIZE TABLE {$table_name}");
            $optimized[$key] = $result !== false;
        }
        
        return $optimized;
    }
    
    /**
     * Backup table structure (for development)
     */
    public function export_table_structure() {
        $structure = array();
        
        foreach ($this->tables as $key => $table_name) {
            $create_table = $this->wpdb->get_row("SHOW CREATE TABLE {$table_name}", ARRAY_A);
            if ($create_table) {
                $structure[$key] = $create_table['Create Table'];
            }
        }
        
        return $structure;
    }
    
    /**
     * Check database health
     */
    public function check_database_health() {
        $health = array(
            'tables_exist' => true,
            'missing_tables' => array(),
            'table_counts' => array(),
            'last_activity' => null,
            'errors' => array()
        );
        
        // Check if tables exist
        $missing = $this->verify_tables();
        if (!empty($missing)) {
            $health['tables_exist'] = false;
            $health['missing_tables'] = $missing;
        }
        
        // Get table counts
        $health['table_counts'] = $this->get_database_stats();
        
        // Check last activity
        $last_participant = $this->wpdb->get_var("
            SELECT MAX(created_at) 
            FROM {$this->tables['participants']}
        ");
        $health['last_activity'] = $last_participant;
        
        // Check for common issues
        if ($this->wpdb->last_error) {
            $health['errors'][] = $this->wpdb->last_error;
        }
        
        return $health;
    }
    
    /**
     * Get WPDB instance
     */
    public function get_wpdb() {
        return $this->wpdb;
    }
    
    /**
     * Get table prefix
     */
    public function get_table_prefix() {
        return $this->table_prefix;
    }
    
    /**
     * Execute custom query with error handling
     */
    public function execute_query($query, $params = array()) {
        if (!empty($params)) {
            $query = $this->wpdb->prepare($query, $params);
        }
        
        $result = $this->wpdb->query($query);
        
        if ($result === false) {
            error_log('Vefify Quiz Database Error: ' . $this->wpdb->last_error);
            error_log('Query: ' . $query);
            return new WP_Error('database_error', $this->wpdb->last_error);
        }
        
        return $result;
    }
    
    /**
     * Get results with error handling
     */
    public function get_results($query, $params = array(), $output = OBJECT) {
        if (!empty($params)) {
            $query = $this->wpdb->prepare($query, $params);
        }
        
        $results = $this->wpdb->get_results($query, $output);
        
        if ($this->wpdb->last_error) {
            error_log('Vefify Quiz Database Error: ' . $this->wpdb->last_error);
            error_log('Query: ' . $query);
            return new WP_Error('database_error', $this->wpdb->last_error);
        }
        
        return $results;
    }
    
    /**
     * Insert data with error handling
     */
    public function insert($table_key, $data, $format = null) {
        $table_name = $this->get_table_name($table_key);
        
        if (!$table_name) {
            return new WP_Error('invalid_table', 'Invalid table key: ' . $table_key);
        }
        
        $result = $this->wpdb->insert($table_name, $data, $format);
        
        if ($result === false) {
            error_log('Vefify Quiz Insert Error: ' . $this->wpdb->last_error);
            return new WP_Error('insert_error', $this->wpdb->last_error);
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Update data with error handling
     */
    public function update($table_key, $data, $where, $format = null, $where_format = null) {
        $table_name = $this->get_table_name($table_key);
        
        if (!$table_name) {
            return new WP_Error('invalid_table', 'Invalid table key: ' . $table_key);
        }
        
        $result = $this->wpdb->update($table_name, $data, $where, $format, $where_format);
        
        if ($result === false) {
            error_log('Vefify Quiz Update Error: ' . $this->wpdb->last_error);
            return new WP_Error('update_error', $this->wpdb->last_error);
        }
        
        return $result;
    }
    
    /**
     * Delete data with error handling
     */
    public function delete($table_key, $where, $where_format = null) {
        $table_name = $this->get_table_name($table_key);
        
        if (!$table_name) {
            return new WP_Error('invalid_table', 'Invalid table key: ' . $table_key);
        }
        
        $result = $this->wpdb->delete($table_name, $where, $where_format);
        
        if ($result === false) {
            error_log('Vefify Quiz Delete Error: ' . $this->wpdb->last_error);
            return new WP_Error('delete_error', $this->wpdb->last_error);
        }
        
        return $result;
    }
}