<?php
/**
 * Question Bank Management
 * File: modules/questions/class-question-bank.php
 */

class QuestionBank {
    private $db;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }
    
    /**
     * Get all questions with pagination
     */
    public function get_questions($args = []) {
        $defaults = [
            'campaign_id' => null,
            'category' => null,
            'difficulty' => null,
            'is_active' => 1,
            'per_page' => 20,
            'page' => 1,
            'search' => null
        ];
        
        $args = array_merge($defaults, $args);
        
        $questions_table = $this->db->prefix . 'vefify_questions';
        $options_table = $this->db->prefix . 'vefify_question_options';
        
        // Build WHERE clause
        $where_conditions = ['1=1'];
        $params = [];
        
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
        $total_query = "SELECT COUNT(*) FROM {$questions_table} q {$where_clause}";
        $total = $this->db->get_var($this->db->prepare($total_query, $params));
        
        // Get questions with pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit_clause = "LIMIT %d OFFSET %d";
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        $questions_query = "
            SELECT q.*, c.name as campaign_name
            FROM {$questions_table} q
            LEFT JOIN {$this->db->prefix}vefify_campaigns c ON q.campaign_id = c.id
            {$where_clause}
            ORDER BY q.created_at DESC
            {$limit_clause}
        ";
        
        $questions = $this->db->get_results($this->db->prepare($questions_query, $params), ARRAY_A);
        
        // Get options for each question
        foreach ($questions as &$question) {
            $question['options'] = $this->db->get_results($this->db->prepare(
                "SELECT * FROM {$options_table} WHERE question_id = %d ORDER BY order_index",
                $question['id']
            ), ARRAY_A);
        }
        
        return [
            'questions' => $questions,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page']
        ];
    }
    
    /**
     * Create new question
     */
    public function create_question($data) {
        $questions_table = $this->db->prefix . 'vefify_questions';
        $options_table = $this->db->prefix . 'vefify_question_options';
        
        // Validate required fields
        if (empty($data['question_text']) || empty($data['options'])) {
            return new \WP_Error('missing_data', 'Question text and options are required');
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
            return new \WP_Error('no_correct_answer', 'At least one correct answer is required');
        }
        
        // Start transaction
        $this->db->query('START TRANSACTION');
        
        try {
            // Insert question
            $question_result = $this->db->insert($questions_table, [
                'campaign_id' => $data['campaign_id'] ?: null,
                'question_text' => sanitize_textarea_field($data['question_text']),
                'question_type' => sanitize_text_field($data['question_type']),
                'category' => sanitize_text_field($data['category']),
                'difficulty' => sanitize_text_field($data['difficulty']),
                'points' => intval($data['points'] ?: 1),
                'explanation' => sanitize_textarea_field($data['explanation'] ?: ''),
                'order_index' => intval($data['order_index'] ?: 0)
            ]);
            
            if ($question_result === false) {
                throw new Exception('Failed to create question');
            }
            
            $question_id = $this->db->insert_id;
            
            // Insert options
            foreach ($data['options'] as $index => $option) {
                if (empty($option['option_text'])) continue;
                
                $option_result = $this->db->insert($options_table, [
                    'question_id' => $question_id,
                    'option_text' => sanitize_textarea_field($option['option_text']),
                    'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                    'order_index' => $index,
                    'explanation' => sanitize_textarea_field($option['explanation'] ?: '')
                ]);
                
                if ($option_result === false) {
                    throw new Exception('Failed to create option');
                }
            }
            
            $this->db->query('COMMIT');
            return $question_id;
            
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            return new \WP_Error('db_error', $e->getMessage());
        }
    }
    
    /**
     * Update question
     */
    public function update_question($question_id, $data) {
        $questions_table = $this->db->prefix . 'vefify_questions';
        $options_table = $this->db->prefix . 'vefify_question_options';
        
        // Start transaction
        $this->db->query('START TRANSACTION');
        
        try {
            // Update question
            $question_result = $this->db->update($questions_table, [
                'question_text' => sanitize_textarea_field($data['question_text']),
                'question_type' => sanitize_text_field($data['question_type']),
                'category' => sanitize_text_field($data['category']),
                'difficulty' => sanitize_text_field($data['difficulty']),
                'points' => intval($data['points'] ?: 1),
                'explanation' => sanitize_textarea_field($data['explanation'] ?: ''),
                'order_index' => intval($data['order_index'] ?: 0),
                'updated_at' => current_time('mysql')
            ], ['id' => $question_id]);
            
            if ($question_result === false) {
                throw new Exception('Failed to update question');
            }
            
            // Delete existing options
            $this->db->delete($options_table, ['question_id' => $question_id]);
            
            // Insert new options
            foreach ($data['options'] as $index => $option) {
                if (empty($option['option_text'])) continue;
                
                $option_result = $this->db->insert($options_table, [
                    'question_id' => $question_id,
                    'option_text' => sanitize_textarea_field($option['option_text']),
                    'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                    'order_index' => $index,
                    'explanation' => sanitize_textarea_field($option['explanation'] ?: '')
                ]);
                
                if ($option_result === false) {
                    throw new Exception('Failed to create option');
                }
            }
            
            $this->db->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            return new \WP_Error('db_error', $e->getMessage());
        }
    }
    
    /**
     * Delete question
     */
    public function delete_question($question_id) {
        $questions_table = $this->db->prefix . 'vefify_questions';
        
        // Check if question is used in any completed quizzes
        $sessions_table = $this->db->prefix . 'vefify_quiz_sessions';
        $used_in_sessions = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$sessions_table} 
             WHERE JSON_CONTAINS(questions_data, %s) AND is_completed = 1",
            json_encode([$question_id])
        ));
        
        if ($used_in_sessions > 0) {
            return new \WP_Error('question_in_use', 'Cannot delete question that has been used in completed quizzes');
        }
        
        // Soft delete by setting is_active = 0
        $result = $this->db->update($questions_table, 
            ['is_active' => 0], 
            ['id' => $question_id]
        );
        
        return $result !== false;
    }
    
    /**
     * Import questions from CSV
     */
    public function import_questions_csv($file_path, $campaign_id = null) {
        if (!file_exists($file_path)) {
            return new \WP_Error('file_not_found', 'CSV file not found');
        }
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new \WP_Error('file_error', 'Cannot read CSV file');
        }
        
        $imported = 0;
        $errors = [];
        $line = 0;
        
        // Skip header row
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== false) {
            $line++;
            
            if (count($data) < 6) {
                $errors[] = "Line {$line}: Insufficient columns";
                continue;
            }
            
            // Expected format: question_text, option1, option2, option3, option4, correct_options, category, difficulty
            $question_data = [
                'campaign_id' => $campaign_id,
                'question_text' => $data[0],
                'question_type' => 'multiple_choice',
                'category' => $data[6] ?? 'general',
                'difficulty' => $data[7] ?? 'medium',
                'options' => []
            ];
            
            // Add options
            for ($i = 1; $i <= 4; $i++) {
                if (!empty($data[$i])) {
                    $question_data['options'][] = [
                        'option_text' => $data[$i],
                        'is_correct' => strpos($data[5], (string)$i) !== false
                    ];
                }
            }
            
            $result = $this->create_question($question_data);
            
            if (is_wp_error($result)) {
                $errors[] = "Line {$line}: " . $result->get_error_message();
            } else {
                $imported++;
            }
        }
        
        fclose($handle);
        
        return [
            'imported' => $imported,
            'errors' => $errors,
            'total_lines' => $line
        ];
    }
    
    /**
     * Get question categories
     */
    public function get_categories() {
        $questions_table = $this->db->prefix . 'vefify_questions';
        
        return $this->db->get_col("
            SELECT DISTINCT category 
            FROM {$questions_table} 
            WHERE category IS NOT NULL AND category != ''
            ORDER BY category
        ");
    }
    
    /**
     * Get question statistics
     */
    public function get_question_stats($campaign_id = null) {
        $questions_table = $this->db->prefix . 'vefify_questions';
        
        $where = $campaign_id ? 'WHERE campaign_id = ' . intval($campaign_id) : '';
        
        return $this->db->get_row("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy,
                COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium,
                COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard
            FROM {$questions_table}
            {$where}
        ", ARRAY_A);
    }
}