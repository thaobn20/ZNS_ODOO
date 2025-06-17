<?php
/**
 * Question Model
 * File: modules/questions/class-question-model.php
 * 
 * Handles database operations and data management for questions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Model {
    
    private $wpdb;
    private $table_prefix;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    }
    
    /**
     * Get a single question by ID with options
     */
    public function get_question($question_id) {
        $question = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_prefix}questions WHERE id = %d AND is_active = 1",
            $question_id
        ));
        
        if (!$question) {
            return null;
        }
        
        // Get options for this question
        $question->options = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_prefix}question_options 
             WHERE question_id = %d 
             ORDER BY order_index",
            $question_id
        ));
        
        return $question;
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
            'include_options' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_conditions = array('1=1');
        $params = array();
        
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
        
        if ($args['is_active'] !== null) {
            $where_conditions[] = 'q.is_active = %d';
            $params[] = $args['is_active'];
        }
        
        if ($args['search']) {
            $where_conditions[] = 'q.question_text LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$this->table_prefix}questions q {$where_clause}";
        $total = $this->wpdb->get_var($this->wpdb->prepare($total_query, $params));
        
        // Get questions with pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit_clause = "LIMIT %d OFFSET %d";
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        $questions_query = "
            SELECT q.*, c.name as campaign_name
            FROM {$this->table_prefix}questions q
            LEFT JOIN {$this->table_prefix}campaigns c ON q.campaign_id = c.id
            {$where_clause}
            ORDER BY q.created_at DESC
            {$limit_clause}
        ";
        
        $questions = $this->wpdb->get_results($this->wpdb->prepare($questions_query, $params));
        
        // Include options if requested
        if ($args['include_options']) {
            foreach ($questions as &$question) {
                $question->options = $this->wpdb->get_results($this->wpdb->prepare(
                    "SELECT * FROM {$this->table_prefix}question_options 
                     WHERE question_id = %d 
                     ORDER BY order_index",
                    $question->id
                ));
            }
        }
        
        return array(
            'questions' => $questions,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page']
        );
    }
    
    /**
     * Create a new question
     */
    public function create_question($data) {
        // Validate required fields
        if (empty($data['question_text']) || empty($data['options'])) {
            return new WP_Error('missing_data', 'Question text and options are required');
        }
        
        // Validate at least one correct answer
        $has_correct = false;
        foreach ($data['options'] as $option) {
            if (!empty($option['is_correct'])) {
                $has_correct = true;
                break;
            }
        }
        
        if (!$has_correct) {
            return new WP_Error('no_correct_answer', 'At least one correct answer is required');
        }
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Insert question
            $question_result = $this->wpdb->insert(
                $this->table_prefix . 'questions',
                array(
                    'campaign_id' => $data['campaign_id'] ?: null,
                    'question_text' => sanitize_textarea_field($data['question_text']),
                    'question_type' => sanitize_text_field($data['question_type'] ?: 'multiple_choice'),
                    'category' => sanitize_text_field($data['category'] ?: ''),
                    'difficulty' => sanitize_text_field($data['difficulty'] ?: 'medium'),
                    'points' => intval($data['points'] ?: 1),
                    'explanation' => sanitize_textarea_field($data['explanation'] ?: ''),
                    'order_index' => intval($data['order_index'] ?: 0),
                    'is_active' => 1
                ),
                array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d')
            );
            
            if ($question_result === false) {
                throw new Exception('Failed to create question');
            }
            
            $question_id = $this->wpdb->insert_id;
            
            // Insert options
            foreach ($data['options'] as $index => $option) {
                if (empty($option['option_text'])) continue;
                
                $option_result = $this->wpdb->insert(
                    $this->table_prefix . 'question_options',
                    array(
                        'question_id' => $question_id,
                        'option_text' => sanitize_textarea_field($option['option_text']),
                        'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                        'order_index' => $index,
                        'explanation' => sanitize_textarea_field($option['explanation'] ?: '')
                    ),
                    array('%d', '%s', '%d', '%d', '%s')
                );
                
                if ($option_result === false) {
                    throw new Exception('Failed to create option');
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
     * Update an existing question
     */
    public function update_question($question_id, $data) {
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Update question
            $question_result = $this->wpdb->update(
                $this->table_prefix . 'questions',
                array(
                    'question_text' => sanitize_textarea_field($data['question_text']),
                    'question_type' => sanitize_text_field($data['question_type']),
                    'category' => sanitize_text_field($data['category']),
                    'difficulty' => sanitize_text_field($data['difficulty']),
                    'points' => intval($data['points'] ?: 1),
                    'explanation' => sanitize_textarea_field($data['explanation'] ?: ''),
                    'order_index' => intval($data['order_index'] ?: 0),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $question_id),
                array('%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s'),
                array('%d')
            );
            
            if ($question_result === false) {
                throw new Exception('Failed to update question');
            }
            
            // Delete existing options
            $this->wpdb->delete(
                $this->table_prefix . 'question_options',
                array('question_id' => $question_id),
                array('%d')
            );
            
            // Insert new options
            if (!empty($data['options'])) {
                foreach ($data['options'] as $index => $option) {
                    if (empty($option['option_text'])) continue;
                    
                    $option_result = $this->wpdb->insert(
                        $this->table_prefix . 'question_options',
                        array(
                            'question_id' => $question_id,
                            'option_text' => sanitize_textarea_field($option['option_text']),
                            'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                            'order_index' => $index,
                            'explanation' => sanitize_textarea_field($option['explanation'] ?: '')
                        ),
                        array('%d', '%s', '%d', '%d', '%s')
                    );
                    
                    if ($option_result === false) {
                        throw new Exception('Failed to create option');
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
     * Delete a question (soft delete)
     */
    public function delete_question($question_id) {
        // Check if question is used in any completed quizzes
        $used_in_sessions = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_prefix}quiz_sessions 
             WHERE JSON_CONTAINS(questions_data, %s) AND is_completed = 1",
            json_encode(array($question_id))
        ));
        
        if ($used_in_sessions > 0) {
            return new WP_Error('question_in_use', 'Cannot delete question that has been used in completed quizzes');
        }
        
        // Soft delete by setting is_active = 0
        $result = $this->wpdb->update(
            $this->table_prefix . 'questions',
            array('is_active' => 0),
            array('id' => $question_id),
            array('%d'),
            array('%d')
        );
        
        return $result !== false;
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
        
        if (empty($data['options']) || !is_array($data['options'])) {
            $errors[] = 'At least 2 options are required';
        } elseif (count($data['options']) < 2) {
            $errors[] = 'At least 2 options are required';
        } else {
            // Validate at least one correct answer
            $has_correct = false;
            $valid_options = 0;
            
            foreach ($data['options'] as $option) {
                if (!empty($option['option_text'])) {
                    $valid_options++;
                    if (!empty($option['is_correct'])) {
                        $has_correct = true;
                    }
                }
            }
            
            if ($valid_options < 2) {
                $errors[] = 'At least 2 valid options are required';
            }
            
            if (!$has_correct) {
                $errors[] = 'At least one correct answer is required';
            }
        }
        
        // Validate question type
        if (!empty($data['question_type'])) {
            $valid_types = array('multiple_choice', 'multiple_select', 'true_false');
            if (!in_array($data['question_type'], $valid_types)) {
                $errors[] = 'Invalid question type';
            }
        }
        
        // Validate difficulty
        if (!empty($data['difficulty'])) {
            $valid_difficulties = array('easy', 'medium', 'hard');
            if (!in_array($data['difficulty'], $valid_difficulties)) {
                $errors[] = 'Invalid difficulty level';
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Get question categories
     */
    public function get_categories() {
        return $this->wpdb->get_col("
            SELECT DISTINCT category 
            FROM {$this->table_prefix}questions 
            WHERE category IS NOT NULL AND category != '' AND is_active = 1
            ORDER BY category
        ");
    }
    
    /**
     * Get question statistics
     */
    public function get_question_stats($campaign_id = null) {
        $where = $campaign_id ? 'WHERE campaign_id = ' . intval($campaign_id) : '';
        
        return $this->wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy,
                COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium,
                COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard,
                COUNT(CASE WHEN question_type = 'multiple_choice' THEN 1 END) as single_choice,
                COUNT(CASE WHEN question_type = 'multiple_select' THEN 1 END) as multi_choice
            FROM {$this->table_prefix}questions
            {$where}
        ", ARRAY_A);
    }
}