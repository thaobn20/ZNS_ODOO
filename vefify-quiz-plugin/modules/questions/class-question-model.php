<?php
/**
 * Question Model Class
 * File: modules/questions/class-question-model.php
 * 
 * Handles all database operations for questions and question options
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Model {
    
    private $db;
    private $table_prefix;
    private $questions_table;
    private $options_table;
    private $campaigns_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'vefify_';
        $this->questions_table = $this->table_prefix . 'questions';
        $this->options_table = $this->table_prefix . 'question_options';
        $this->campaigns_table = $this->table_prefix . 'campaigns';
    }
    
    /**
     * Get single question with options
     */
    public function get_question($question_id) {
        if (!$question_id) {
            return null;
        }
        
        // Get question
        $question = $this->db->get_row($this->db->prepare(
            "SELECT q.*, c.name as campaign_name 
             FROM {$this->questions_table} q
             LEFT JOIN {$this->campaigns_table} c ON q.campaign_id = c.id
             WHERE q.id = %d",
            $question_id
        ));
        
        if (!$question) {
            return null;
        }
        
        // Get options
        $question->options = $this->get_question_options($question_id);
        
        return $question;
    }
    
    /**
     * Get question options
     */
    public function get_question_options($question_id) {
        return $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->options_table} 
             WHERE question_id = %d 
             ORDER BY order_index",
            $question_id
        ));
    }
    
    /**
     * Get questions with filters and pagination
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
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = array_merge($defaults, $args);
        
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
            $params[] = '%' . $this->db->esc_like($args['search']) . '%';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$this->questions_table} q {$where_clause}";
        $total = $this->db->get_var($this->db->prepare($total_query, $params));
        
        // Get questions with pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby']);
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $limit_clause = "ORDER BY q.{$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        $questions_query = "
            SELECT q.*, c.name as campaign_name
            FROM {$this->questions_table} q
            LEFT JOIN {$this->campaigns_table} c ON q.campaign_id = c.id
            {$where_clause}
            {$limit_clause}
        ";
        
        $questions = $this->db->get_results($this->db->prepare($questions_query, $params));
        
        // Get options for each question
        foreach ($questions as &$question) {
            $question->options = $this->get_question_options($question->id);
        }
        
        return array(
            'questions' => $questions,
            'total' => intval($total),
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page']
        );
    }
    
    /**
     * Create new question
     */
    public function create_question($data) {
        // Validate required fields
        if (empty($data['question_text'])) {
            return new WP_Error('missing_question_text', 'Question text is required');
        }
        
        if (empty($data['options']) || !is_array($data['options'])) {
            return new WP_Error('missing_options', 'Question options are required');
        }
        
        // Validate at least one correct answer
        $has_correct = false;
        foreach ($data['options'] as $option) {
            if (!empty($option['is_correct']) || !empty($option['option_text'])) {
                if (!empty($option['is_correct'])) {
                    $has_correct = true;
                }
            }
        }
        
        if (!$has_correct) {
            return new WP_Error('no_correct_answer', 'At least one correct answer is required');
        }
        
        // Start transaction
        $this->db->query('START TRANSACTION');
        
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
            $result = $this->db->insert($this->questions_table, $question_data);
            
            if ($result === false) {
                throw new Exception('Failed to create question: ' . $this->db->last_error);
            }
            
            $question_id = $this->db->insert_id;
            
            // Insert options
            foreach ($data['options'] as $index => $option) {
                // Skip empty options
                if (empty($option['option_text']) && empty($option['text'])) {
                    continue;
                }
                
                $option_text = !empty($option['option_text']) ? $option['option_text'] : $option['text'];
                
                $option_data = array(
                    'question_id' => $question_id,
                    'option_text' => sanitize_textarea_field($option_text),
                    'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                    'order_index' => intval($index),
                    'explanation' => sanitize_textarea_field($option['explanation'] ?? ''),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );
                
                $option_result = $this->db->insert($this->options_table, $option_data);
                
                if ($option_result === false) {
                    throw new Exception('Failed to create option: ' . $this->db->last_error);
                }
            }
            
            $this->db->query('COMMIT');
            return $question_id;
            
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            return new WP_Error('db_error', $e->getMessage());
        }
    }
    
    /**
     * Update question
     */
    public function update_question($question_id, $data) {
        if (!$question_id) {
            return new WP_Error('invalid_id', 'Invalid question ID');
        }
        
        // Check if question exists
        $existing = $this->get_question($question_id);
        if (!$existing) {
            return new WP_Error('question_not_found', 'Question not found');
        }
        
        // Start transaction
        $this->db->query('START TRANSACTION');
        
        try {
            // Update question
            $question_data = array(
                'question_text' => sanitize_textarea_field($data['question_text']),
                'question_type' => sanitize_text_field($data['question_type'] ?? 'multiple_choice'),
                'category' => sanitize_text_field($data['category'] ?? 'general'),
                'difficulty' => sanitize_text_field($data['difficulty'] ?? 'medium'),
                'points' => intval($data['points'] ?? 1),
                'explanation' => sanitize_textarea_field($data['explanation'] ?? ''),
                'order_index' => intval($data['order_index'] ?? 0),
                'updated_at' => current_time('mysql')
            );
            
            if (isset($data['campaign_id'])) {
                $question_data['campaign_id'] = !empty($data['campaign_id']) ? intval($data['campaign_id']) : null;
            }
            
            $result = $this->db->update(
                $this->questions_table,
                $question_data,
                array('id' => $question_id)
            );
            
            if ($result === false) {
                throw new Exception('Failed to update question: ' . $this->db->last_error);
            }
            
            // Delete existing options
            $delete_result = $this->db->delete(
                $this->options_table,
                array('question_id' => $question_id)
            );
            
            if ($delete_result === false) {
                throw new Exception('Failed to delete existing options: ' . $this->db->last_error);
            }
            
            // Insert new options
            if (!empty($data['options']) && is_array($data['options'])) {
                foreach ($data['options'] as $index => $option) {
                    // Skip empty options
                    if (empty($option['option_text']) && empty($option['text'])) {
                        continue;
                    }
                    
                    $option_text = !empty($option['option_text']) ? $option['option_text'] : $option['text'];
                    
                    $option_data = array(
                        'question_id' => $question_id,
                        'option_text' => sanitize_textarea_field($option_text),
                        'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                        'order_index' => intval($index),
                        'explanation' => sanitize_textarea_field($option['explanation'] ?? ''),
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    );
                    
                    $option_result = $this->db->insert($this->options_table, $option_data);
                    
                    if ($option_result === false) {
                        throw new Exception('Failed to create option: ' . $this->db->last_error);
                    }
                }
            }
            
            $this->db->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            return new WP_Error('db_error', $e->getMessage());
        }
    }
    
    /**
     * Delete question (soft delete)
     */
    public function delete_question($question_id) {
        if (!$question_id) {
            return new WP_Error('invalid_id', 'Invalid question ID');
        }
        
        // Check if question exists
        $question = $this->get_question($question_id);
        if (!$question) {
            return new WP_Error('question_not_found', 'Question not found');
        }
        
        // Soft delete by setting is_active = 0
        $result = $this->db->update(
            $this->questions_table,
            array(
                'is_active' => 0,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $question_id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete question: ' . $this->db->last_error);
        }
        
        return true;
    }
    
    /**
     * Duplicate question
     */
    public function duplicate_question($question_id) {
        $question = $this->get_question($question_id);
        
        if (!$question) {
            return new WP_Error('question_not_found', 'Question not found');
        }
        
        // Prepare data for new question
        $data = array(
            'campaign_id' => $question->campaign_id,
            'question_text' => $question->question_text . ' (Copy)',
            'question_type' => $question->question_type,
            'category' => $question->category,
            'difficulty' => $question->difficulty,
            'points' => $question->points,
            'explanation' => $question->explanation,
            'options' => array()
        );
        
        // Copy options
        foreach ($question->options as $option) {
            $data['options'][] = array(
                'option_text' => $option->option_text,
                'is_correct' => $option->is_correct,
                'explanation' => $option->explanation
            );
        }
        
        return $this->create_question($data);
    }
    
    /**
     * Get question categories
     */
    public function get_categories() {
        $categories = $this->db->get_col(
            "SELECT DISTINCT category 
             FROM {$this->questions_table} 
             WHERE category IS NOT NULL AND category != '' AND is_active = 1
             ORDER BY category"
        );
        
        return array_filter($categories);
    }
    
    /**
     * Get question statistics
     */
    public function get_statistics($campaign_id = null) {
        $where_clause = '';
        $params = array();
        
        if ($campaign_id) {
            $where_clause = 'WHERE campaign_id = %d';
            $params[] = $campaign_id;
        }
        
        $query = "
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive,
                COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy,
                COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium,
                COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard,
                COUNT(CASE WHEN question_type = 'multiple_choice' THEN 1 END) as multiple_choice,
                COUNT(CASE WHEN question_type = 'true_false' THEN 1 END) as true_false
            FROM {$this->questions_table}
            {$where_clause}
        ";
        
        $stats = !empty($params) 
            ? $this->db->get_row($this->db->prepare($query, $params), ARRAY_A)
            : $this->db->get_row($query, ARRAY_A);
        
        return $stats ?: array();
    }
    
    /**
     * Validate question data
     */
    public function validate_question_data($data) {
        $errors = array();
        
        // Check required fields
        if (empty($data['question_text'])) {
            $errors[] = 'Question text is required';
        }
        
        if (empty($data['options']) || !is_array($data['options'])) {
            $errors[] = 'At least one option is required';
        } else {
            // Check options
            $filled_options = 0;
            $correct_options = 0;
            
            foreach ($data['options'] as $option) {
                if (!empty($option['option_text']) || !empty($option['text'])) {
                    $filled_options++;
                    
                    if (!empty($option['is_correct'])) {
                        $correct_options++;
                    }
                }
            }
            
            if ($filled_options < 2) {
                $errors[] = 'At least 2 options are required';
            }
            
            if ($correct_options === 0) {
                $errors[] = 'At least one correct answer is required';
            }
            
            // Check question type specific rules
            $question_type = $data['question_type'] ?? 'multiple_choice';
            
            if ($question_type === 'true_false') {
                if ($filled_options !== 2) {
                    $errors[] = 'True/False questions must have exactly 2 options';
                }
                
                if ($correct_options !== 1) {
                    $errors[] = 'True/False questions must have exactly 1 correct answer';
                }
            }
        }
        
        // Validate other fields
        if (!empty($data['points']) && (!is_numeric($data['points']) || $data['points'] < 1)) {
            $errors[] = 'Points must be a positive number';
        }
        
        if (!empty($data['difficulty']) && !in_array($data['difficulty'], array('easy', 'medium', 'hard'))) {
            $errors[] = 'Difficulty must be easy, medium, or hard';
        }
        
        return $errors;
    }
}