<?php
/**
 * Enhanced Question Model
 * File: modules/questions/class-question-model.php
 * 
 * FIXED VERSION - Handles all database operations for questions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Model {
    
    private $wpdb;
    private $table_prefix;
    private $questions_table;
    private $options_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        $this->questions_table = $this->table_prefix . 'questions';
        $this->options_table = $this->table_prefix . 'question_options';
    }
    
    /**
     * FIXED: Get questions with pagination and filtering
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
        $total = $this->wpdb->get_var($this->wpdb->prepare($count_query, $params));
        
        // Get questions with pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $questions_query = "
            SELECT q.*, c.name as campaign_name
            FROM {$this->questions_table} q
            LEFT JOIN {$this->table_prefix}campaigns c ON q.campaign_id = c.id
            {$where_clause}
            ORDER BY {$orderby}
            LIMIT %d OFFSET %d
        ";
        
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        $questions = $this->wpdb->get_results($this->wpdb->prepare($questions_query, $params));
        
        // Add options to each question
        foreach ($questions as &$question) {
            $question->options = $this->get_question_options($question->id);
        }
        
        return array(
            'questions' => $questions,
            'total' => intval($total),
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
            'per_page' => $args['per_page']
        );
    }
    
    /**
     * FIXED: Get single question by ID with options
     */
    public function get_question($question_id) {
        if (!$question_id) {
            return null;
        }
        
        $question = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT q.*, c.name as campaign_name 
             FROM {$this->questions_table} q
             LEFT JOIN {$this->table_prefix}campaigns c ON q.campaign_id = c.id
             WHERE q.id = %d",
            $question_id
        ));
        
        if (!$question) {
            return null;
        }
        
        // Add options
        $question->options = $this->get_question_options($question_id);
        
        return $question;
    }
    
    /**
     * FIXED: Get question options
     */
    public function get_question_options($question_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->options_table} 
             WHERE question_id = %d 
             ORDER BY order_index ASC",
            $question_id
        ));
    }
    
    /**
     * FIXED: Create new question with options
     */
    public function create_question($data) {
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
                'campaign_id' => $data['campaign_id'],
                'question_text' => sanitize_textarea_field($data['question_text']),
                'question_type' => sanitize_text_field($data['question_type']),
                'category' => sanitize_text_field($data['category']),
                'difficulty' => sanitize_text_field($data['difficulty']),
                'points' => intval($data['points']) ?: 1,
                'explanation' => sanitize_textarea_field($data['explanation'] ?? ''),
                'order_index' => intval($data['order_index'] ?? 0),
                'is_active' => 1,
                'created_at' => current_time('mysql')
            );
            
            // Insert question
            $result = $this->wpdb->insert($this->questions_table, $question_data, array(
                '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s'
            ));
            
            if ($result === false) {
                throw new Exception('Failed to insert question: ' . $this->wpdb->last_error);
            }
            
            $question_id = $this->wpdb->insert_id;
            
            // Insert options
            if (!empty($data['options'])) {
                foreach ($data['options'] as $option) {
                    $this->insert_option($question_id, $option);
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
                'campaign_id' => $data['campaign_id'],
                'question_text' => sanitize_textarea_field($data['question_text']),
                'question_type' => sanitize_text_field($data['question_type']),
                'category' => sanitize_text_field($data['category']),
                'difficulty' => sanitize_text_field($data['difficulty']),
                'points' => intval($data['points']) ?: 1,
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
            $delete_result = $this->wpdb->delete(
                $this->options_table,
                array('question_id' => $question_id),
                array('%d')
            );
            
            if ($delete_result === false) {
                throw new Exception('Failed to delete existing options: ' . $this->wpdb->last_error);
            }
            
            // Insert new options
            if (!empty($data['options'])) {
                foreach ($data['options'] as $option) {
                    $this->insert_option($question_id, $option);
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
     * FIXED: Insert option
     */
    private function insert_option($question_id, $option_data) {
        $option = array(
            'question_id' => $question_id,
            'option_text' => sanitize_textarea_field($option_data['option_text']),
            'is_correct' => $option_data['is_correct'] ? 1 : 0,
            'order_index' => intval($option_data['order_index']),
            'explanation' => sanitize_textarea_field($option_data['explanation'] ?? ''),
            'created_at' => current_time('mysql')
        );
        
        $result = $this->wpdb->insert(
            $this->options_table,
            $option,
            array('%d', '%s', '%d', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            throw new Exception('Failed to insert option: ' . $this->wpdb->last_error);
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * FIXED: Delete question (soft delete)
     */
    public function delete_question($question_id) {
        if (!$question_id) {
            return new WP_Error('invalid_id', 'Invalid question ID');
        }
        
        // Check if question exists
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->questions_table} WHERE id = %d",
            $question_id
        ));
        
        if (!$existing) {
            return new WP_Error('question_not_found', 'Question not found');
        }
        
        // Check if question is used in completed sessions
        $sessions_table = $this->table_prefix . 'quiz_sessions';
        $in_use = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$sessions_table} 
             WHERE is_completed = 1 
             AND questions_data LIKE %s",
            '%"' . $question_id . '"%'
        ));
        
        if ($in_use > 0) {
            return new WP_Error('question_in_use', 'Cannot delete question that has been used in completed quizzes');
        }
        
        // Soft delete by setting is_active = 0
        $result = $this->wpdb->update(
            $this->questions_table,
            array(
                'is_active' => 0,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $question_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete question: ' . $this->wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * FIXED: Validate question data
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
        if (isset($data['points']) && (!is_numeric($data['points']) || intval($data['points']) < 1)) {
            $errors[] = 'Points must be a positive number';
        }
        
        // Validate options
        if (!empty($data['options'])) {
            $validation = $this->validate_options($data['options'], $data['question_type']);
            if (is_wp_error($validation)) {
                $errors[] = $validation->get_error_message();
            }
        } else {
            $errors[] = 'At least one option is required';
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }
        
        return true;
    }
    
    /**
     * FIXED: Validate options
     */
    private function validate_options($options, $question_type) {
        if (!is_array($options) || empty($options)) {
            return new WP_Error('no_options', 'Options array is required');
        }
        
        $valid_options = array();
        $correct_count = 0;
        
        foreach ($options as $option) {
            if (empty($option['option_text'])) {
                continue; // Skip empty options
            }
            
            $valid_options[] = $option;
            
            if (!empty($option['is_correct'])) {
                $correct_count++;
            }
        }
        
        // Check minimum options
        $min_options = ($question_type === 'true_false') ? 2 : 2;
        if (count($valid_options) < $min_options) {
            return new WP_Error('insufficient_options', "At least {$min_options} options are required");
        }
        
        // Check maximum options
        $max_options = ($question_type === 'true_false') ? 2 : 6;
        if (count($valid_options) > $max_options) {
            return new WP_Error('too_many_options', "Maximum {$max_options} options allowed");
        }
        
        // Check correct answers
        if ($correct_count === 0) {
            return new WP_Error('no_correct_answer', 'At least one correct answer is required');
        }
        
        if ($question_type === 'multiple_choice' && $correct_count > 1) {
            return new WP_Error('too_many_correct', 'Single choice questions can only have one correct answer');
        }
        
        if ($question_type === 'true_false' && $correct_count !== 1) {
            return new WP_Error('invalid_true_false', 'True/False questions must have exactly one correct answer');
        }
        
        return true;
    }
    
    /**
     * Get questions for a specific campaign
     */
    public function get_campaign_questions($campaign_id, $count = null, $randomize = false) {
        $args = array(
            'campaign_id' => $campaign_id,
            'is_active' => 1,
            'per_page' => $count ?: 999,
            'page' => 1
        );
        
        $result = $this->get_questions($args);
        $questions = $result['questions'];
        
        if ($randomize) {
            shuffle($questions);
            if ($count) {
                $questions = array_slice($questions, 0, $count);
            }
        }
        
        return $questions;
    }
    
    /**
     * Get question statistics
     */
    public function get_statistics($campaign_id = null) {
        $where = '';
        $params = array();
        
        if ($campaign_id !== null) {
            $where = 'WHERE campaign_id = %d';
            $params[] = $campaign_id;
        }
        
        $query = "
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy,
                COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium,
                COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard,
                COUNT(CASE WHEN question_type = 'multiple_choice' THEN 1 END) as single_choice,
                COUNT(CASE WHEN question_type = 'multiple_select' THEN 1 END) as multi_choice,
                COUNT(CASE WHEN question_type = 'true_false' THEN 1 END) as true_false
            FROM {$this->questions_table}
            {$where}
        ";
        
        if (!empty($params)) {
            return $this->wpdb->get_row($this->wpdb->prepare($query, $params), ARRAY_A);
        } else {
            return $this->wpdb->get_row($query, ARRAY_A);
        }
    }
    
    /**
     * Get available categories
     */
    public function get_categories() {
        return $this->wpdb->get_col("
            SELECT DISTINCT category 
            FROM {$this->questions_table} 
            WHERE category IS NOT NULL 
            AND category != '' 
            AND is_active = 1
            ORDER BY category ASC
        ");
    }
    
    /**
     * Duplicate question
     */
    public function duplicate_question($question_id, $new_campaign_id = null) {
        $original = $this->get_question($question_id);
        
        if (!$original) {
            return new WP_Error('question_not_found', 'Original question not found');
        }
        
        // Prepare data for new question
        $new_data = array(
            'campaign_id' => $new_campaign_id ?: $original->campaign_id,
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
                'order_index' => $option->order_index,
                'explanation' => $option->explanation
            );
        }
        
        return $this->create_question($new_data);
    }
    
    /**
     * Bulk update questions
     */
    public function bulk_update($question_ids, $updates) {
        if (empty($question_ids) || !is_array($question_ids)) {
            return new WP_Error('invalid_ids', 'Invalid question IDs');
        }
        
        $valid_fields = array('category', 'difficulty', 'is_active', 'campaign_id');
        $update_data = array();
        $format = array();
        
        foreach ($updates as $field => $value) {
            if (in_array($field, $valid_fields)) {
                $update_data[$field] = $value;
                
                if ($field === 'is_active' || $field === 'campaign_id') {
                    $format[] = '%d';
                } else {
                    $format[] = '%s';
                }
            }
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_updates', 'No valid updates provided');
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';
        
        $updated = 0;
        
        foreach ($question_ids as $question_id) {
            $result = $this->wpdb->update(
                $this->questions_table,
                $update_data,
                array('id' => intval($question_id)),
                $format,
                array('%d')
            );
            
            if ($result !== false) {
                $updated++;
            }
        }
        
        return $updated;
    }
    
    /**
     * Search questions
     */
    public function search($search_term, $args = array()) {
        $defaults = array(
            'campaign_id' => null,
            'per_page' => 20,
            'page' => 1
        );
        
        $args = array_merge($defaults, $args);
        $args['search'] = $search_term;
        
        return $this->get_questions($args);
    }
}