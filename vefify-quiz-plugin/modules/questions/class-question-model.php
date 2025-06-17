<?php
/**
 * Streamlined Question Model
 * File: modules/questions/class-question-model.php
 * 
 * Handles all question database operations using centralized database
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Model {
    
    private $database;
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Use centralized database
        $this->database = new Vefify_Quiz_Database();
    }
    
    /**
     * Get questions with filtering and pagination
     */
    public function get_questions($args = array()) {
        $defaults = array(
            'campaign_id' => null,
            'category' => null,
            'difficulty' => null,
            'is_active' => 1,
            'per_page' => 20,
            'page' => 1,
            'search' => null,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $questions_table = $this->database->get_table_name('questions');
        $options_table = $this->database->get_table_name('question_options');
        $campaigns_table = $this->database->get_table_name('campaigns');
        
        // Build WHERE conditions
        $where_conditions = array('q.is_active = %d');
        $params = array($args['is_active']);
        
        if ($args['campaign_id']) {
            $where_conditions[] = 'q.campaign_id = %d';
            $params[] = $args['campaign_id'];
        }
        
        if ($args['category']) {
            $where_conditions[] = 'q.category = %s';
            $params[] = $args['category'];
        }
        
        if ($args['difficulty']) {
            $where_conditions[] = 'q.difficulty = %s';
            $params[] = $args['difficulty'];
        }
        
        if ($args['search']) {
            $where_conditions[] = 'q.question_text LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$questions_table} q {$where_clause}";
        $total = $this->wpdb->get_var($this->wpdb->prepare($total_query, $params));
        
        // Get questions with pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $order_clause = sprintf('ORDER BY q.%s %s', 
            sanitize_sql_orderby($args['order_by']), 
            $args['order'] === 'DESC' ? 'DESC' : 'ASC'
        );
        
        $limit_params = $params;
        $limit_params[] = $args['per_page'];
        $limit_params[] = $offset;
        
        $questions_query = "
            SELECT q.*, c.name as campaign_name
            FROM {$questions_table} q
            LEFT JOIN {$campaigns_table} c ON q.campaign_id = c.id
            {$where_clause}
            {$order_clause}
            LIMIT %d OFFSET %d
        ";
        
        $questions = $this->wpdb->get_results($this->wpdb->prepare($questions_query, $limit_params));
        
        // Get options for each question
        foreach ($questions as &$question) {
            $question->options = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$options_table} WHERE question_id = %d ORDER BY order_index",
                $question->id
            ));
        }
        
        return array(
            'questions' => $questions,
            'total' => intval($total),
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page']
        );
    }
    
    /**
     * Get single question with options
     */
    public function get_question($question_id) {
        $questions_table = $this->database->get_table_name('questions');
        $options_table = $this->database->get_table_name('question_options');
        $campaigns_table = $this->database->get_table_name('campaigns');
        
        $question = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT q.*, c.name as campaign_name
             FROM {$questions_table} q
             LEFT JOIN {$campaigns_table} c ON q.campaign_id = c.id
             WHERE q.id = %d",
            $question_id
        ));
        
        if ($question) {
            $question->options = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$options_table} WHERE question_id = %d ORDER BY order_index",
                $question_id
            ));
        }
        
        return $question;
    }
    
    /**
     * Create new question
     */
    public function create_question($data) {
        // Validate data
        $validation = $this->validate_question_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $questions_table = $this->database->get_table_name('questions');
        $options_table = $this->database->get_table_name('question_options');
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Insert question
            $question_data = array(
                'campaign_id' => !empty($data['campaign_id']) ? intval($data['campaign_id']) : null,
                'question_text' => sanitize_textarea_field($data['question_text']),
                'question_type' => sanitize_text_field($data['question_type']),
                'category' => sanitize_text_field($data['category'] ?? ''),
                'difficulty' => sanitize_text_field($data['difficulty'] ?? 'medium'),
                'points' => intval($data['points'] ?? 1),
                'explanation' => sanitize_textarea_field($data['explanation'] ?? ''),
                'is_active' => isset($data['is_active']) ? 1 : 0,
                'order_index' => intval($data['order_index'] ?? 0),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );
            
            $result = $this->wpdb->insert($questions_table, $question_data);
            
            if ($result === false) {
                throw new Exception('Failed to create question: ' . $this->wpdb->last_error);
            }
            
            $question_id = $this->wpdb->insert_id;
            
            // Insert options
            if (!empty($data['options'])) {
                $this->save_question_options($question_id, $data['options']);
            }
            
            $this->wpdb->query('COMMIT');
            return $question_id;
            
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            return new WP_Error('db_error', $e->getMessage());
        }
    }
    
    /**
     * Update existing question
     */
    public function update_question($question_id, $data) {
        // Validate data
        $validation = $this->validate_question_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $questions_table = $this->database->get_table_name('questions');
        $options_table = $this->database->get_table_name('question_options');
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Update question
            $question_data = array(
                'campaign_id' => !empty($data['campaign_id']) ? intval($data['campaign_id']) : null,
                'question_text' => sanitize_textarea_field($data['question_text']),
                'question_type' => sanitize_text_field($data['question_type']),
                'category' => sanitize_text_field($data['category'] ?? ''),
                'difficulty' => sanitize_text_field($data['difficulty'] ?? 'medium'),
                'points' => intval($data['points'] ?? 1),
                'explanation' => sanitize_textarea_field($data['explanation'] ?? ''),
                'is_active' => isset($data['is_active']) ? 1 : 0,
                'order_index' => intval($data['order_index'] ?? 0),
                'updated_at' => current_time('mysql')
            );
            
            $result = $this->wpdb->update(
                $questions_table,
                $question_data,
                array('id' => $question_id)
            );
            
            if ($result === false) {
                throw new Exception('Failed to update question: ' . $this->wpdb->last_error);
            }
            
            // Delete existing options
            $this->wpdb->delete($options_table, array('question_id' => $question_id));
            
            // Insert new options
            if (!empty($data['options'])) {
                $this->save_question_options($question_id, $data['options']);
            }
            
            $this->wpdb->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            return new WP_Error('db_error', $e->getMessage());
        }
    }
    
    /**
     * Delete question (soft delete)
     */
    public function delete_question($question_id) {
        $questions_table = $this->database->get_table_name('questions');
        
        $result = $this->wpdb->update(
            $questions_table,
            array('is_active' => 0, 'updated_at' => current_time('mysql')),
            array('id' => $question_id)
        );
        
        return $result !== false;
    }
    
    /**
     * Save question options
     */
    private function save_question_options($question_id, $options) {
        $options_table = $this->database->get_table_name('question_options');
        
        foreach ($options as $index => $option) {
            if (empty($option['option_text'])) {
                continue;
            }
            
            $option_data = array(
                'question_id' => $question_id,
                'option_text' => sanitize_textarea_field($option['option_text']),
                'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                'explanation' => sanitize_textarea_field($option['explanation'] ?? ''),
                'order_index' => $index,
                'created_at' => current_time('mysql')
            );
            
            $result = $this->wpdb->insert($options_table, $option_data);
            
            if ($result === false) {
                throw new Exception('Failed to create option: ' . $this->wpdb->last_error);
            }
        }
    }
    
    /**
     * Validate question data
     */
    public function validate_question_data($data) {
        $errors = array();
        
        // Required fields
        if (empty($data['question_text'])) {
            $errors[] = 'Question text is required';
        }
        
        if (empty($data['question_type'])) {
            $errors[] = 'Question type is required';
        }
        
        // Valid question types
        $valid_types = array('multiple_choice', 'multiple_select', 'true_false');
        if (!empty($data['question_type']) && !in_array($data['question_type'], $valid_types)) {
            $errors[] = 'Invalid question type';
        }
        
        // Valid difficulty
        $valid_difficulties = array('easy', 'medium', 'hard');
        if (!empty($data['difficulty']) && !in_array($data['difficulty'], $valid_difficulties)) {
            $errors[] = 'Invalid difficulty level';
        }
        
        // Validate options
        if (!empty($data['options'])) {
            $valid_options = array_filter($data['options'], function($option) {
                return !empty($option['option_text']);
            });
            
            if (count($valid_options) < 2) {
                $errors[] = 'At least 2 options are required';
            }
            
            // Check for correct answers
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
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }
        
        return true;
    }
    
    /**
     * Get available campaigns for dropdown
     */
    public function get_campaigns() {
        $campaigns_table = $this->database->get_table_name('campaigns');
        
        return $this->wpdb->get_results("
            SELECT id, name 
            FROM {$campaigns_table} 
            WHERE is_active = 1 
            ORDER BY name ASC
        ");
    }
    
    /**
     * Get available categories
     */
    public function get_categories() {
        $questions_table = $this->database->get_table_name('questions');
        
        return $this->wpdb->get_col("
            SELECT DISTINCT category 
            FROM {$questions_table} 
            WHERE category IS NOT NULL AND category != '' AND is_active = 1
            ORDER BY category ASC
        ");
    }
    
    /**
     * Get question statistics for analytics
     */
    public function get_statistics($campaign_id = null) {
        $questions_table = $this->database->get_table_name('questions');
        
        $where = $campaign_id ? $this->wpdb->prepare('WHERE campaign_id = %d', $campaign_id) : '';
        
        return $this->wpdb->get_row("
            SELECT 
                COUNT(*) as total_questions,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_questions,
                COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy_questions,
                COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium_questions,
                COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard_questions,
                COUNT(DISTINCT category) as total_categories
            FROM {$questions_table} 
            {$where}
        ", ARRAY_A);
    }
}