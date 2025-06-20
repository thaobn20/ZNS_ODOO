<?php
/**
 * FIXED Question Model with Centralized Database Connection
 * File: modules/questions/class-question-model.php
 * 
 * Handles all question data operations with proper database connection
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Model {
    
    private $database;
    private $wpdb;
    private $questions_table;
    private $options_table;
    private $campaigns_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // FIXED: Use centralized database class
        $this->database = new Vefify_Quiz_Database();
        
        // FIXED: Get correct table names from centralized database
        $this->questions_table = $this->database->get_table_name('questions');
        $this->options_table = $this->database->get_table_name('question_options');
        $this->campaigns_table = $this->database->get_table_name('campaigns');
        
        // Verify tables exist
        if (!$this->questions_table || !$this->options_table) {
            error_log('Vefify Quiz: Question tables not found. Database may not be initialized.');
        }
    }
    
    /**
     * FIXED: Get single question with options using correct database connection
     */
    public function get_question($question_id) {
        if (!$this->questions_table || !$this->options_table) {
            return new WP_Error('database_error', 'Database tables not found');
        }
        
        $question_id = intval($question_id);
        if (!$question_id) {
            return new WP_Error('invalid_id', 'Invalid question ID');
        }
        
        // Get question data
        $question = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT q.*, c.name as campaign_name 
             FROM {$this->questions_table} q
             LEFT JOIN {$this->campaigns_table} c ON q.campaign_id = c.id
             WHERE q.id = %d AND q.is_active = 1",
            $question_id
        ));
        
        if (!$question) {
            return new WP_Error('not_found', 'Question not found');
        }
        
        // Get question options
        $options = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->options_table} 
             WHERE question_id = %d 
             ORDER BY order_index ASC",
            $question_id
        ));
        
        $question->options = $options ?: array();
        
        return $question;
    }
    
    /**
     * FIXED: Get multiple questions with filters
     */
    public function get_questions($args = array()) {
        if (!$this->questions_table) {
            return array('questions' => array(), 'total' => 0);
        }
        
        $defaults = array(
            'campaign_id' => null,
            'category' => null,
            'difficulty' => null,
            'question_type' => null,
            'is_active' => 1,
            'search' => null,
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_conditions = array();
        $params = array();
        
        if ($args['is_active'] !== null) {
            $where_conditions[] = 'q.is_active = %d';
            $params[] = intval($args['is_active']);
        }
        
        if ($args['campaign_id']) {
            $where_conditions[] = 'q.campaign_id = %d';
            $params[] = intval($args['campaign_id']);
        }
        
        if ($args['category']) {
            $where_conditions[] = 'q.category = %s';
            $params[] = sanitize_text_field($args['category']);
        }
        
        if ($args['difficulty']) {
            $where_conditions[] = 'q.difficulty = %s';
            $params[] = sanitize_text_field($args['difficulty']);
        }
        
        if ($args['question_type']) {
            $where_conditions[] = 'q.question_type = %s';
            $params[] = sanitize_text_field($args['question_type']);
        }
        
        if ($args['search']) {
            $where_conditions[] = 'q.question_text LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$this->questions_table} q {$where_clause}";
        $total = $this->wpdb->get_var($this->wpdb->prepare($total_query, $params));
        
        // Get questions with pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby']);
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $questions_query = "
            SELECT q.*, c.name as campaign_name
            FROM {$this->questions_table} q
            LEFT JOIN {$this->campaigns_table} c ON q.campaign_id = c.id
            {$where_clause}
            ORDER BY q.{$orderby} {$order}
            LIMIT %d OFFSET %d
        ";
        
        $params[] = intval($args['per_page']);
        $params[] = intval($offset);
        
        $questions = $this->wpdb->get_results($this->wpdb->prepare($questions_query, $params));
        
        // Get options for each question if needed
        foreach ($questions as &$question) {
            $question->options = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->options_table} 
                 WHERE question_id = %d 
                 ORDER BY order_index ASC",
                $question->id
            ));
        }
        
        return array(
            'questions' => $questions ?: array(),
            'total' => intval($total),
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page']
        );
    }
    
    /**
     * FIXED: Create new question with proper validation
     */
    public function create_question($data) {
        if (!$this->questions_table || !$this->options_table) {
            return new WP_Error('database_error', 'Database tables not found');
        }
        
        // Validate input data
        $validation = $this->validate_question_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Prepare question data
        $question_data = array(
            'campaign_id' => !empty($data['campaign_id']) ? intval($data['campaign_id']) : null,
            'question_text' => sanitize_textarea_field($data['question_text']),
            'question_type' => sanitize_text_field($data['question_type'] ?: 'multiple_choice'),
            'category' => sanitize_text_field($data['category'] ?: ''),
            'difficulty' => sanitize_text_field($data['difficulty'] ?: 'medium'),
            'points' => intval($data['points'] ?: 1),
            'explanation' => sanitize_textarea_field($data['explanation'] ?: ''),
            'order_index' => intval($data['order_index'] ?: 0),
            'is_active' => 1,
            'created_at' => current_time('mysql')
        );
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Insert question
            $result = $this->wpdb->insert($this->questions_table, $question_data);
            
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
                        'explanation' => sanitize_textarea_field($option['explanation'] ?: ''),
                        'order_index' => intval($index),
                        'created_at' => current_time('mysql')
                    );
                    
                    $result = $this->wpdb->insert($this->options_table, $option_data);
                    
                    if ($result === false) {
                        throw new Exception('Failed to insert option: ' . $this->wpdb->last_error);
                    }
                }
            }
            
            $this->wpdb->query('COMMIT');
            
            return $question_id;
            
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            return new WP_Error('db_error', $e->getMessage());
        }
    }
    
    /**
     * FIXED: Update existing question
     */
    public function update_question($question_id, $data) {
        if (!$this->questions_table || !$this->options_table) {
            return new WP_Error('database_error', 'Database tables not found');
        }
        
        $question_id = intval($question_id);
        if (!$question_id) {
            return new WP_Error('invalid_id', 'Invalid question ID');
        }
        
        // Check if question exists
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->questions_table} WHERE id = %d",
            $question_id
        ));
        
        if (!$existing) {
            return new WP_Error('not_found', 'Question not found');
        }
        
        // Validate input data
        $validation = $this->validate_question_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Prepare question data
        $question_data = array(
            'campaign_id' => !empty($data['campaign_id']) ? intval($data['campaign_id']) : null,
            'question_text' => sanitize_textarea_field($data['question_text']),
            'question_type' => sanitize_text_field($data['question_type'] ?: 'multiple_choice'),
            'category' => sanitize_text_field($data['category'] ?: ''),
            'difficulty' => sanitize_text_field($data['difficulty'] ?: 'medium'),
            'points' => intval($data['points'] ?: 1),
            'explanation' => sanitize_textarea_field($data['explanation'] ?: ''),
            'order_index' => intval($data['order_index'] ?: 0),
            'updated_at' => current_time('mysql')
        );
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Update question
            $result = $this->wpdb->update(
                $this->questions_table,
                $question_data,
                array('id' => $question_id)
            );
            
            if ($result === false) {
                throw new Exception('Failed to update question: ' . $this->wpdb->last_error);
            }
            
            // Delete existing options
            $this->wpdb->delete($this->options_table, array('question_id' => $question_id));
            
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
                        'explanation' => sanitize_textarea_field($option['explanation'] ?: ''),
                        'order_index' => intval($index),
                        'created_at' => current_time('mysql')
                    );
                    
                    $result = $this->wpdb->insert($this->options_table, $option_data);
                    
                    if ($result === false) {
                        throw new Exception('Failed to insert option: ' . $this->wpdb->last_error);
                    }
                }
            }
            
            $this->wpdb->query('COMMIT');
            
            return true;
            
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            return new WP_Error('db_error', $e->getMessage());
        }
    }
    
    /**
     * FIXED: Delete question (soft delete)
     */
    public function delete_question($question_id) {
        if (!$this->questions_table) {
            return new WP_Error('database_error', 'Database tables not found');
        }
        
        $question_id = intval($question_id);
        if (!$question_id) {
            return new WP_Error('invalid_id', 'Invalid question ID');
        }
        
        // Check if question exists
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->questions_table} WHERE id = %d",
            $question_id
        ));
        
        if (!$existing) {
            return new WP_Error('not_found', 'Question not found');
        }
        
        // Check if question is used in any completed sessions
        $sessions_table = $this->database->get_table_name('quiz_sessions');
        if ($sessions_table) {
            $used_in_sessions = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$sessions_table} 
                 WHERE questions_data LIKE %s AND is_completed = 1",
                '%"' . $question_id . '"%'
            ));
            
            if ($used_in_sessions > 0) {
                return new WP_Error('question_in_use', 'Cannot delete question that has been used in completed quizzes');
            }
        }
        
        // Soft delete by setting is_active = 0
        $result = $this->wpdb->update(
            $this->questions_table,
            array('is_active' => 0, 'updated_at' => current_time('mysql')),
            array('id' => $question_id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete question: ' . $this->wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * FIXED: Validate question data before save
     */
    public function validate_question_data($data) {
        $errors = array();
        
        // Required fields
        if (empty($data['question_text'])) {
            $errors[] = 'Question text is required';
        }
        
        // Validate question type
        $valid_types = array('multiple_choice', 'multiple_select', 'true_false');
        if (!empty($data['question_type']) && !in_array($data['question_type'], $valid_types)) {
            $errors[] = 'Invalid question type';
        }
        
        // Validate difficulty
        $valid_difficulties = array('easy', 'medium', 'hard');
        if (!empty($data['difficulty']) && !in_array($data['difficulty'], $valid_difficulties)) {
            $errors[] = 'Invalid difficulty level';
        }
        
        // Validate points
        if (!empty($data['points']) && (!is_numeric($data['points']) || intval($data['points']) < 1)) {
            $errors[] = 'Points must be a positive number';
        }
        
        // Validate options
        if (!empty($data['options']) && is_array($data['options'])) {
            $valid_options = array_filter($data['options'], function($option) {
                return !empty($option['option_text']);
            });
            
            if (count($valid_options) < 2) {
                $errors[] = 'At least 2 answer options are required';
            }
            
            // Check for at least one correct answer
            $has_correct = false;
            foreach ($valid_options as $option) {
                if (!empty($option['is_correct'])) {
                    $has_correct = true;
                    break;
                }
            }
            
            if (!$has_correct) {
                $errors[] = 'At least one correct answer is required';
            }
            
            // For true/false questions, limit to 2 options
            if (!empty($data['question_type']) && $data['question_type'] === 'true_false' && count($valid_options) > 2) {
                $errors[] = 'True/False questions can only have 2 options';
            }
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }
        
        return true;
    }
    
    /**
     * Get question statistics
     */
    public function get_question_statistics($campaign_id = null) {
        if (!$this->questions_table) {
            return array();
        }
        
        $where_clause = 'WHERE q.is_active = 1';
        $params = array();
        
        if ($campaign_id) {
            $where_clause .= ' AND q.campaign_id = %d';
            $params[] = intval($campaign_id);
        }
        
        $query = "
            SELECT 
                COUNT(*) as total_questions,
                COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy_questions,
                COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium_questions,
                COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard_questions,
                COUNT(CASE WHEN question_type = 'multiple_choice' THEN 1 END) as multiple_choice,
                COUNT(CASE WHEN question_type = 'multiple_select' THEN 1 END) as multiple_select,
                COUNT(CASE WHEN question_type = 'true_false' THEN 1 END) as true_false,
                COUNT(DISTINCT category) as total_categories
            FROM {$this->questions_table} q
            {$where_clause}
        ";
        
        return $this->wpdb->get_row($this->wpdb->prepare($query, $params), ARRAY_A);
    }
    
    /**
     * Get random questions for a quiz
     */
    public function get_random_questions($campaign_id, $count = 5, $difficulty = null) {
        if (!$this->questions_table) {
            return array();
        }
        
        $where_conditions = array('q.is_active = 1');
        $params = array();
        
        if ($campaign_id) {
            $where_conditions[] = '(q.campaign_id = %d OR q.campaign_id IS NULL)';
            $params[] = intval($campaign_id);
        }
        
        if ($difficulty) {
            $where_conditions[] = 'q.difficulty = %s';
            $params[] = sanitize_text_field($difficulty);
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "
            SELECT q.*, c.name as campaign_name
            FROM {$this->questions_table} q
            LEFT JOIN {$this->campaigns_table} c ON q.campaign_id = c.id
            {$where_clause}
            ORDER BY RAND()
            LIMIT %d
        ";
        
        $params[] = intval($count);
        
        $questions = $this->wpdb->get_results($this->wpdb->prepare($query, $params));
        
        // Get options for each question
        foreach ($questions as &$question) {
            $question->options = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->options_table} 
                 WHERE question_id = %d 
                 ORDER BY order_index ASC",
                $question->id
            ));
        }
        
        return $questions ?: array();
    }
    
    /**
     * Get question categories
     */
    public function get_categories() {
        if (!$this->questions_table) {
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
     * Duplicate question
     */
    public function duplicate_question($question_id) {
        $original = $this->get_question($question_id);
        
        if (is_wp_error($original)) {
            return $original;
        }
        
        // Prepare data for new question
        $new_data = array(
            'campaign_id' => $original->campaign_id,
            'question_text' => $original->question_text . ' (Copy)',
            'question_type' => $original->question_type,
            'category' => $original->category,
            'difficulty' => $original->difficulty,
            'points' => $original->points,
            'explanation' => $original->explanation,
            'options' => array()
        );
        
        // Copy options
        foreach ($original->options as $option) {
            $new_data['options'][] = array(
                'option_text' => $option->option_text,
                'is_correct' => $option->is_correct,
                'explanation' => $option->explanation
            );
        }
        
        return $this->create_question($new_data);
    }
    
    /**
     * Check if tables exist and are accessible
     */
    public function verify_database() {
        $issues = array();
        
        if (!$this->questions_table) {
            $issues[] = 'Questions table not found';
        } else {
            $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->questions_table}'");
            if (!$table_exists) {
                $issues[] = 'Questions table does not exist in database';
            }
        }
        
        if (!$this->options_table) {
            $issues[] = 'Options table not found';
        } else {
            $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->options_table}'");
            if (!$table_exists) {
                $issues[] = 'Options table does not exist in database';
            }
        }
        
        return empty($issues) ? true : $issues;
    }
}