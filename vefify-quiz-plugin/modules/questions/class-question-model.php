<?php
/**
 * Question Model - Centralized Database Integration
 * File: modules/questions/class-question-model.php
 * 
 * Integrates with includes/class-database.php centralized system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Model {
    
    private $wpdb;
    private $database;
    private $table_prefix;
    private $questions_table;
    private $options_table;
    
    public function __construct($database = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Use centralized database if provided
        if ($database && method_exists($database, 'get_table_name')) {
            $this->database = $database;
            $this->questions_table = $database->get_table_name('questions');
            $this->options_table = $database->get_table_name('question_options');
        } else {
            // Fallback to constants/direct approach
            $this->table_prefix = $wpdb->prefix . (defined('VEFIFY_QUIZ_TABLE_PREFIX') ? VEFIFY_QUIZ_TABLE_PREFIX : 'vefify_');
            $this->questions_table = $this->table_prefix . 'questions';
            $this->options_table = $this->table_prefix . 'question_options';
        }
        
        error_log('Vefify Question Model: Initialized with tables - Questions: ' . $this->questions_table . ', Options: ' . $this->options_table);
    }
    
    /**
     * Get questions with filtering and pagination
     */
    public function get_questions($args = array()) {
        $defaults = array(
            'campaign_id' => null,
            'category' => null,
            'difficulty' => null,
            'question_type' => null,
            'is_active' => 1,
            'per_page' => 20,
            'page' => 1,
            'search' => null,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = array_merge($defaults, $args);
        
        // Ensure tables exist
        if (!$this->tables_exist()) {
            return array(
                'questions' => array(),
                'total' => 0,
                'pages' => 0,
                'current_page' => 1
            );
        }
        
        // Build WHERE clause
        $where_conditions = array();
        $params = array();
        
        if ($args['campaign_id'] !== null) {
            if ($args['campaign_id'] === 0) {
                $where_conditions[] = 'q.campaign_id IS NULL';
            } else {
                $where_conditions[] = 'q.campaign_id = %d';
                $params[] = $args['campaign_id'];
            }
        }
        
        if ($args['category']) {
            $where_conditions[] = 'q.category = %s';
            $params[] = $args['category'];
        }
        
        if ($args['difficulty']) {
            $where_conditions[] = 'q.difficulty = %s';
            $params[] = $args['difficulty'];
        }
        
        if ($args['question_type']) {
            $where_conditions[] = 'q.question_type = %s';
            $params[] = $args['question_type'];
        }
        
        if ($args['is_active'] !== null) {
            $where_conditions[] = 'q.is_active = %d';
            $params[] = $args['is_active'];
        }
        
        if ($args['search']) {
            $where_conditions[] = 'q.question_text LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$this->questions_table} q {$where_clause}";
        $total = $this->wpdb->get_var(
            !empty($params) ? $this->wpdb->prepare($count_query, $params) : $count_query
        ) ?: 0;
        
        // Get questions with pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = in_array($args['orderby'], array('id', 'question_text', 'category', 'difficulty', 'created_at')) 
                   ? $args['orderby'] : 'created_at';
        $order = in_array(strtoupper($args['order']), array('ASC', 'DESC')) ? $args['order'] : 'DESC';
        
        // Build main query
        $campaigns_table = $this->get_campaigns_table();
        $questions_query = "
            SELECT q.*, c.name as campaign_name
            FROM {$this->questions_table} q
            LEFT JOIN {$campaigns_table} c ON q.campaign_id = c.id
            {$where_clause}
            ORDER BY q.{$orderby} {$order}
            LIMIT %d OFFSET %d
        ";
        
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        $questions = $this->wpdb->get_results(
            $this->wpdb->prepare($questions_query, $params),
            ARRAY_A
        );
        
        // Get options for each question
        foreach ($questions as &$question) {
            $question['options'] = $this->get_question_options($question['id']);
        }
        
        return array(
            'questions' => $questions,
            'total' => intval($total),
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page']
        );
    }
    
    /**
     * Get single question by ID
     */
    public function get_question($question_id) {
        if (!$this->tables_exist()) {
            return null;
        }
        
        $campaigns_table = $this->get_campaigns_table();
        $question = $this->wpdb->get_row($this->wpdb->prepare("
            SELECT q.*, c.name as campaign_name 
            FROM {$this->questions_table} q
            LEFT JOIN {$campaigns_table} c ON q.campaign_id = c.id
            WHERE q.id = %d AND q.is_active = 1
        ", $question_id), ARRAY_A);
        
        if ($question) {
            $question['options'] = $this->get_question_options($question_id);
        }
        
        return $question;
    }
    
    /**
     * Get question options
     */
    public function get_question_options($question_id) {
        if (!$this->tables_exist()) {
            return array();
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT * FROM {$this->options_table} 
            WHERE question_id = %d 
            ORDER BY order_index ASC, id ASC
        ", $question_id), ARRAY_A);
    }
    
    /**
     * Create new question with options
     */
    public function create_question($data) {
        if (!$this->tables_exist()) {
            return new WP_Error('tables_missing', 'Database tables do not exist');
        }
        
        // Validate input
        $validation = $this->validate_question_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Prepare question data
            $question_data = array(
                'campaign_id' => !empty($data['campaign_id']) ? intval($data['campaign_id']) : null,
                'question_text' => sanitize_textarea_field($data['question_text']),
                'question_type' => sanitize_text_field($data['question_type'] ?? 'multiple_choice'),
                'category' => sanitize_text_field($data['category'] ?? 'general'),
                'difficulty' => sanitize_text_field($data['difficulty'] ?? 'medium'),
                'points' => intval($data['points'] ?? 1),
                'explanation' => sanitize_textarea_field($data['explanation'] ?? ''),
                'order_index' => intval($data['order_index'] ?? 0),
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );
            
            // Insert question
            $result = $this->wpdb->insert(
                $this->questions_table,
                $question_data,
                array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                throw new Exception('Failed to insert question: ' . $this->wpdb->last_error);
            }
            
            $question_id = $this->wpdb->insert_id;
            
            // Insert options
            if (!empty($data['options']) && is_array($data['options'])) {
                foreach ($data['options'] as $index => $option) {
                    if (empty($option['option_text'])) {
                        continue;
                    }
                    
                    $option_data = array(
                        'question_id' => $question_id,
                        'option_text' => sanitize_textarea_field($option['option_text']),
                        'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                        'order_index' => intval($index),
                        'explanation' => sanitize_textarea_field($option['explanation'] ?? ''),
                        'created_at' => current_time('mysql')
                    );
                    
                    $option_result = $this->wpdb->insert(
                        $this->options_table,
                        $option_data,
                        array('%d', '%s', '%d', '%d', '%s', '%s')
                    );
                    
                    if ($option_result === false) {
                        throw new Exception('Failed to insert option: ' . $this->wpdb->last_error);
                    }
                }
            }
            
            $this->wpdb->query('COMMIT');
            
            error_log('Vefify Question Model: Question created successfully with ID ' . $question_id);
            return $question_id;
            
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('Vefify Question Model Error: ' . $e->getMessage());
            return new WP_Error('db_error', $e->getMessage());
        }
    }
    
    /**
     * Update existing question
     */
    public function update_question($question_id, $data) {
        if (!$this->tables_exist()) {
            return new WP_Error('tables_missing', 'Database tables do not exist');
        }
        
        if (!$question_id) {
            return new WP_Error('invalid_id', 'Invalid question ID');
        }
        
        // Validate input
        $validation = $this->validate_question_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Check if question exists
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->questions_table} WHERE id = %d",
            $question_id
        ));
        
        if (!$existing) {
            return new WP_Error('question_not_found', 'Question not found');
        }
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Prepare question data
            $question_data = array(
                'campaign_id' => !empty($data['campaign_id']) ? intval($data['campaign_id']) : null,
                'question_text' => sanitize_textarea_field($data['question_text']),
                'question_type' => sanitize_text_field($data['question_type'] ?? 'multiple_choice'),
                'category' => sanitize_text_field($data['category'] ?? 'general'),
                'difficulty' => sanitize_text_field($data['difficulty'] ?? 'medium'),
                'points' => intval($data['points'] ?? 1),
                'explanation' => sanitize_textarea_field($data['explanation'] ?? ''),
                'order_index' => intval($data['order_index'] ?? 0),
                'updated_at' => current_time('mysql')
            );
            
            // Update question
            $result = $this->wpdb->update(
                $this->questions_table,
                $question_data,
                array('id' => $question_id),
                array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s'),
                array('%d')
            );
            
            if ($result === false) {
                throw new Exception('Failed to update question: ' . $this->wpdb->last_error);
            }
            
            // Delete existing options
            $this->wpdb->delete($this->options_table, array('question_id' => $question_id), array('%d'));
            
            // Insert new options
            if (!empty($data['options']) && is_array($data['options'])) {
                foreach ($data['options'] as $index => $option) {
                    if (empty($option['option_text'])) {
                        continue;
                    }
                    
                    $option_data = array(
                        'question_id' => $question_id,
                        'option_text' => sanitize_textarea_field($option['option_text']),
                        'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                        'order_index' => intval($index),
                        'explanation' => sanitize_textarea_field($option['explanation'] ?? ''),
                        'created_at' => current_time('mysql')
                    );
                    
                    $option_result = $this->wpdb->insert(
                        $this->options_table,
                        $option_data,
                        array('%d', '%s', '%d', '%d', '%s', '%s')
                    );
                    
                    if ($option_result === false) {
                        throw new Exception('Failed to insert option: ' . $this->wpdb->last_error);
                    }
                }
            }
            
            $this->wpdb->query('COMMIT');
            
            error_log('Vefify Question Model: Question updated successfully - ID ' . $question_id);
            return true;
            
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('Vefify Question Model Error: ' . $e->getMessage());
            return new WP_Error('db_error', $e->getMessage());
        }
    }
    
    /**
     * Delete question (soft delete)
     */
    public function delete_question($question_id) {
        if (!$this->tables_exist()) {
            return new WP_Error('tables_missing', 'Database tables do not exist');
        }
        
        $result = $this->wpdb->update(
            $this->questions_table,
            array('is_active' => 0, 'updated_at' => current_time('mysql')),
            array('id' => $question_id),
            array('%d', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get question statistics
     */
    public function get_question_stats($campaign_id = null) {
        if (!$this->tables_exist()) {
            return array(
                'total_questions' => 0,
                'active_questions' => 0,
                'easy_questions' => 0,
                'medium_questions' => 0,
                'hard_questions' => 0,
                'total_categories' => 0
            );
        }
        
        $where = $campaign_id ? $this->wpdb->prepare('WHERE campaign_id = %d', $campaign_id) : '';
        
        $stats = $this->wpdb->get_row("
            SELECT 
                COUNT(*) as total_questions,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_questions,
                COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy_questions,
                COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium_questions,
                COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard_questions,
                COUNT(DISTINCT category) as total_categories
            FROM {$this->questions_table}
            {$where}
        ", ARRAY_A);
        
        return $stats ?: array(
            'total_questions' => 0,
            'active_questions' => 0,
            'easy_questions' => 0,
            'medium_questions' => 0,
            'hard_questions' => 0,
            'total_categories' => 0
        );
    }
    
    /**
     * Get question categories
     */
    public function get_categories() {
        if (!$this->tables_exist()) {
            return array();
        }
        
        return $this->wpdb->get_col("
            SELECT DISTINCT category 
            FROM {$this->questions_table} 
            WHERE category IS NOT NULL AND category != '' AND is_active = 1
            ORDER BY category
        ");
    }
    
    /**
     * Validate question data
     */
    private function validate_question_data($data) {
        if (empty($data['question_text'])) {
            return new WP_Error('missing_question', 'Question text is required');
        }
        
        if (empty($data['options']) || !is_array($data['options'])) {
            return new WP_Error('missing_options', 'Question options are required');
        }
        
        // Count valid options and check for correct answers
        $valid_options = 0;
        $has_correct = false;
        
        foreach ($data['options'] as $option) {
            if (!empty($option['option_text'])) {
                $valid_options++;
                if (!empty($option['is_correct'])) {
                    $has_correct = true;
                }
            }
        }
        
        if ($valid_options < 2) {
            return new WP_Error('insufficient_options', 'At least 2 options are required');
        }
        
        if (!$has_correct) {
            return new WP_Error('no_correct_answer', 'At least one correct answer is required');
        }
        
        return true;
    }
    
    /**
     * Check if required tables exist
     */
    private function tables_exist() {
        $questions_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->questions_table}'");
        $options_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->options_table}'");
        
        return !empty($questions_exists) && !empty($options_exists);
    }
    
    /**
     * Get campaigns table name (with fallback)
     */
    private function get_campaigns_table() {
        if ($this->database && method_exists($this->database, 'get_table_name')) {
            return $this->database->get_table_name('campaigns');
        }
        
        return $this->table_prefix . 'campaigns';
    }
    
    /**
     * Debug method to check table status
     */
    public function debug_table_status() {
        return array(
            'questions_table' => $this->questions_table,
            'questions_exists' => !empty($this->wpdb->get_var("SHOW TABLES LIKE '{$this->questions_table}'")),
            'options_table' => $this->options_table,
            'options_exists' => !empty($this->wpdb->get_var("SHOW TABLES LIKE '{$this->options_table}'")),
            'questions_count' => $this->tables_exist() ? $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->questions_table}") : 0,
            'database_instance' => $this->database ? get_class($this->database) : 'None'
        );
    }
}