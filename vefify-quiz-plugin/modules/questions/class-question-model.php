<?php
/**
 * Question Model - Database Operations
 * File: modules/questions/class-question-model.php
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
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE conditions
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
        $total = $this->wpdb->get_var($params ? $this->wpdb->prepare($total_query, $params) : $total_query);
        
        // Get questions with pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $questions_query = "
            SELECT q.*, c.name as campaign_name
            FROM {$this->table_prefix}questions q
            LEFT JOIN {$this->table_prefix}campaigns c ON q.campaign_id = c.id
            {$where_clause}
            ORDER BY {$orderby}
            LIMIT %d OFFSET %d
        ";
        
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        $questions = $this->wpdb->get_results($this->wpdb->prepare($questions_query, $params));
        
        // Get options for each question
        foreach ($questions as $question) {
            $question->options = $this->get_question_options($question->id);
        }
        
        return array(
            'questions' => $questions,
            'total' => (int) $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page']
        );
    }
    
    /**
     * Get single question with options
     */
    public function get_question($question_id) {
        $question = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT q.*, c.name as campaign_name 
             FROM {$this->table_prefix}questions q
             LEFT JOIN {$this->table_prefix}campaigns c ON q.campaign_id = c.id
             WHERE q.id = %d",
            $question_id
        ));
        
        if ($question) {
            $question->options = $this->get_question_options($question_id);
        }
        
        return $question;
    }
    
    /**
     * Get question options
     */
    public function get_question_options($question_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_prefix}question_options 
             WHERE question_id = %d 
             ORDER BY order_index",
            $question_id
        ));
    }
    
    /**
     * Create new question
     */
    public function create_question($data) {
        // Validate required fields
        if (empty($data['question_text'])) {
            return new WP_Error('missing_data', 'Question text is required');
        }
        
        if (empty($data['options']) || !is_array($data['options'])) {
            return new WP_Error('missing_options', 'At least two options are required');
        }
        
        // Filter valid options
        $valid_options = array_filter($data['options'], function($option) {
            return !empty($option['text']);
        });
        
        if (count($valid_options) < 2) {
            return new WP_Error('insufficient_options', 'At least two options with text are required');
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
            return new WP_Error('no_correct_answer', 'At least one correct answer is required');
        }
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Insert question
            $result = $this->wpdb->insert(
                $this->table_prefix . 'questions',
                array(
                    'campaign_id' => !empty($data['campaign_id']) ? intval($data['campaign_id']) : null,
                    'question_text' => sanitize_textarea_field($data['question_text']),
                    'question_type' => sanitize_text_field($data['question_type'] ?: 'multiple_choice'),
                    'category' => sanitize_text_field($data['category'] ?: 'general'),
                    'difficulty' => sanitize_text_field($data['difficulty'] ?: 'medium'),
                    'points' => intval($data['points'] ?: 1),
                    'explanation' => sanitize_textarea_field($data['explanation'] ?: ''),
                    'order_index' => intval($data['order_index'] ?: 0),
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
            
            if ($result === false) {
                throw new Exception('Failed to create question: ' . $this->wpdb->last_error);
            }
            
            $question_id = $this->wpdb->insert_id;
            
            // Insert options
            foreach ($valid_options as $index => $option) {
                $option_result = $this->wpdb->insert(
                    $this->table_prefix . 'question_options',
                    array(
                        'question_id' => $question_id,
                        'option_text' => sanitize_textarea_field($option['text']),
                        'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                        'order_index' => $index,
                        'explanation' => sanitize_textarea_field($option['explanation'] ?: '')
                    )
                );
                
                if ($option_result === false) {
                    throw new Exception('Failed to create option: ' . $this->wpdb->last_error);
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
     * Update existing question
     */
    public function update_question($question_id, $data) {
        // Validate question exists
        $existing = $this->get_question($question_id);
        if (!$existing) {
            return new WP_Error('not_found', 'Question not found');
        }
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Update question
            $result = $this->wpdb->update(
                $this->table_prefix . 'questions',
                array(
                    'campaign_id' => !empty($data['campaign_id']) ? intval($data['campaign_id']) : null,
                    'question_text' => sanitize_textarea_field($data['question_text']),
                    'question_type' => sanitize_text_field($data['question_type']),
                    'category' => sanitize_text_field($data['category']),
                    'difficulty' => sanitize_text_field($data['difficulty']),
                    'points' => intval($data['points']),
                    'explanation' => sanitize_textarea_field($data['explanation']),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $question_id)
            );
            
            if ($result === false) {
                throw new Exception('Failed to update question: ' . $this->wpdb->last_error);
            }
            
            // Delete existing options
            $this->wpdb->delete(
                $this->table_prefix . 'question_options',
                array('question_id' => $question_id)
            );
            
            // Insert new options
            if (!empty($data['options'])) {
                foreach ($data['options'] as $index => $option) {
                    if (empty($option['text'])) continue;
                    
                    $this->wpdb->insert(
                        $this->table_prefix . 'question_options',
                        array(
                            'question_id' => $question_id,
                            'option_text' => sanitize_textarea_field($option['text']),
                            'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                            'order_index' => $index,
                            'explanation' => sanitize_textarea_field($option['explanation'] ?: '')
                        )
                    );
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
     * Delete question (soft delete)
     */
    public function delete_question($question_id) {
        $result = $this->wpdb->update(
            $this->table_prefix . 'questions',
            array('is_active' => 0, 'updated_at' => current_time('mysql')),
            array('id' => $question_id)
        );
        
        return $result !== false;
    }
    
    /**
     * Get available categories
     */
    public function get_categories() {
        return $this->wpdb->get_col(
            "SELECT DISTINCT category 
             FROM {$this->table_prefix}questions 
             WHERE category IS NOT NULL AND category != '' AND is_active = 1
             ORDER BY category"
        );
    }
    
    /**
     * Get campaigns for dropdown
     */
    public function get_campaigns() {
        return $this->wpdb->get_results(
            "SELECT id, name FROM {$this->table_prefix}campaigns 
             WHERE is_active = 1 ORDER BY name"
        );
    }
    
    /**
     * Get question statistics
     */
    public function get_statistics($campaign_id = null) {
        $where = '';
        $params = array();
        
        if ($campaign_id) {
            $where = 'WHERE campaign_id = %d AND';
            $params[] = $campaign_id;
        } else {
            $where = 'WHERE';
        }
        
        $query = "SELECT 
            COUNT(*) as total_questions,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_questions,
            COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy_questions,
            COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium_questions,
            COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard_questions,
            COUNT(DISTINCT category) as total_categories
         FROM {$this->table_prefix}questions
         {$where} 1=1";
        
        return $this->wpdb->get_row($params ? $this->wpdb->prepare($query, $params) : $query, ARRAY_A);
    }
}