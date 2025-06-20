<?php
/**
 * Question REST API Endpoints
 * File: modules/questions/question-endpoints.php
 * 
 * Handles REST API endpoints for question management and quiz functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Endpoints {
    
    private $namespace = 'vefify/v1';
    private $model;
    
    public function __construct() {
        $this->model = new Vefify_Question_Model();
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get questions for quiz
        register_rest_route($this->namespace, '/questions/quiz', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_quiz_questions'),
            'permission_callback' => '__return_true',
            'args' => array(
                'campaign_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Campaign ID'
                ),
                'count' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 5,
                    'description' => 'Number of questions to return'
                ),
                'difficulty' => array(
                    'required' => false,
                    'type' => 'string',
                    'enum' => array('easy', 'medium', 'hard'),
                    'description' => 'Filter by difficulty'
                ),
                'category' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Filter by category'
                )
            )
        ));
        
        // Get single question with options
        register_rest_route($this->namespace, '/questions/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_question'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Question ID'
                ),
                'include_correct' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Include correct answer information'
                )
            )
        ));
        
        // Get questions list (admin)
        register_rest_route($this->namespace, '/questions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_questions_list'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'campaign_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Filter by campaign ID'
                ),
                'category' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Filter by category'
                ),
                'difficulty' => array(
                    'required' => false,
                    'type' => 'string',
                    'enum' => array('easy', 'medium', 'hard'),
                    'description' => 'Filter by difficulty'
                ),
                'search' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Search in question text'
                ),
                'page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'description' => 'Page number'
                ),
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 20,
                    'description' => 'Questions per page'
                )
            )
        ));
        
        // Create question (admin)
        register_rest_route($this->namespace, '/questions', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_question'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'question_text' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Question text'
                ),
                'options' => array(
                    'required' => true,
                    'type' => 'array',
                    'description' => 'Answer options'
                ),
                'campaign_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Campaign ID (optional for global questions)'
                ),
                'question_type' => array(
                    'required' => false,
                    'type' => 'string',
                    'enum' => array('multiple_choice', 'multiple_select', 'true_false'),
                    'default' => 'multiple_choice',
                    'description' => 'Question type'
                ),
                'category' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Question category'
                ),
                'difficulty' => array(
                    'required' => false,
                    'type' => 'string',
                    'enum' => array('easy', 'medium', 'hard'),
                    'default' => 'medium',
                    'description' => 'Question difficulty'
                ),
                'points' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'description' => 'Points for correct answer'
                ),
                'explanation' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Answer explanation'
                )
            )
        ));
        
        // Update question (admin)
        register_rest_route($this->namespace, '/questions/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_question'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Question ID'
                )
                // Include same args as create_question
            )
        ));
        
        // Delete question (admin)
        register_rest_route($this->namespace, '/questions/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_question'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Question ID'
                )
            )
        ));
        
        // Validate answers
        register_rest_route($this->namespace, '/questions/validate', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_answers'),
            'permission_callback' => '__return_true',
            'args' => array(
                'answers' => array(
                    'required' => true,
                    'type' => 'object',
                    'description' => 'Question ID to answer mapping'
                ),
                'session_id' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Session ID for tracking'
                )
            )
        ));
        
        // Get question statistics (admin)
        register_rest_route($this->namespace, '/questions/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_question_statistics'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'campaign_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Filter by campaign ID'
                )
            )
        ));
        
        // Duplicate question (admin)
        register_rest_route($this->namespace, '/questions/(?P<id>\d+)/duplicate', array(
            'methods' => 'POST',
            'callback' => array($this, 'duplicate_question'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Question ID to duplicate'
                )
            )
        ));
        
        // Bulk operations (admin)
        register_rest_route($this->namespace, '/questions/bulk', array(
            'methods' => 'POST',
            'callback' => array($this, 'bulk_operations'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'action' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('activate', 'deactivate', 'delete', 'duplicate'),
                    'description' => 'Bulk action to perform'
                ),
                'question_ids' => array(
                    'required' => true,
                    'type' => 'array',
                    'description' => 'Array of question IDs'
                )
            )
        ));
        
        // Import questions from CSV (admin)
        register_rest_route($this->namespace, '/questions/import', array(
            'methods' => 'POST',
            'callback' => array($this, 'import_questions'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'campaign_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Campaign ID for imported questions'
                )
            )
        ));
        
        // Get categories
        register_rest_route($this->namespace, '/questions/categories', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_categories'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Get questions for quiz
     */
    public function get_quiz_questions($request) {
        $campaign_id = $request->get_param('campaign_id');
        $count = $request->get_param('count');
        $difficulty = $request->get_param('difficulty');
        $category = $request->get_param('category');
        
        try {
            // Build query args
            $args = array(
                'campaign_id' => $campaign_id,
                'is_active' => 1,
                'per_page' => $count * 2, // Get more than needed for randomization
                'page' => 1
            );
            
            if ($difficulty) {
                $args['difficulty'] = $difficulty;
            }
            
            if ($category) {
                $args['category'] = $category;
            }
            
            // Get questions
            $result = $this->model->get_questions($args);
            $questions = $result['questions'];
            
            // Randomize and limit
            shuffle($questions);
            $questions = array_slice($questions, 0, $count);
            
            // Format for quiz (remove correct answer info)
            $quiz_questions = array();
            foreach ($questions as $question) {
                $quiz_question = array(
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'category' => $question->category,
                    'difficulty' => $question->difficulty,
                    'points' => $question->points,
                    'options' => array()
                );
                
                // Get options without correct answer info
                global $wpdb;
                $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
                $options = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, option_text, order_index FROM {$table_prefix}question_options 
                     WHERE question_id = %d ORDER BY order_index",
                    $question->id
                ));
                
                foreach ($options as $option) {
                    $quiz_question['options'][] = array(
                        'id' => $option->id,
                        'text' => $option->option_text,
                        'order_index' => $option->order_index
                    );
                }
                
                $quiz_questions[] = $quiz_question;
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'questions' => $quiz_questions,
                'total_available' => $result['total']
            ));
            
        } catch (Exception $e) {
            return new WP_Error('quiz_questions_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get single question
     */
    public function get_question($request) {
        $question_id = $request->get_param('id');
        $include_correct = $request->get_param('include_correct');
        
        $question = $this->model->get_question($question_id);
        
        if (!$question) {
            return new WP_Error('question_not_found', 'Question not found', array('status' => 404));
        }
        
        // Format response
        $response = array(
            'id' => $question->id,
            'question_text' => $question->question_text,
            'question_type' => $question->question_type,
            'category' => $question->category,
            'difficulty' => $question->difficulty,
            'points' => $question->points,
            'explanation' => $question->explanation,
            'campaign_id' => $question->campaign_id,
            'campaign_name' => $question->campaign_name,
            'is_active' => $question->is_active,
            'created_at' => $question->created_at,
            'updated_at' => $question->updated_at,
            'options' => array()
        );
        
        // Format options
        foreach ($question->options as $option) {
            $option_data = array(
                'id' => $option->id,
                'text' => $option->option_text,
                'order_index' => $option->order_index,
                'explanation' => $option->explanation
            );
            
            // Include correct answer info if requested and user has permission
            if ($include_correct && current_user_can('manage_options')) {
                $option_data['is_correct'] = (bool) $option->is_correct;
            }
            
            $response['options'][] = $option_data;
        }
        
        return rest_ensure_response($response);
    }
    
    /**
     * Get questions list (admin)
     */
    public function get_questions_list($request) {
        $args = array(
            'campaign_id' => $request->get_param('campaign_id'),
            'category' => $request->get_param('category'),
            'difficulty' => $request->get_param('difficulty'),
            'search' => $request->get_param('search'),
            'page' => $request->get_param('page'),
            'per_page' => $request->get_param('per_page')
        );
        
        $result = $this->model->get_questions($args);
        
        return rest_ensure_response(array(
            'questions' => $result['questions'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'current_page' => $result['current_page']
        ));
    }
    
    /**
     * Create question
     */
    public function create_question($request) {
        $data = array(
            'campaign_id' => $request->get_param('campaign_id'),
            'question_text' => $request->get_param('question_text'),
            'question_type' => $request->get_param('question_type'),
            'category' => $request->get_param('category'),
            'difficulty' => $request->get_param('difficulty'),
            'points' => $request->get_param('points'),
            'explanation' => $request->get_param('explanation'),
            'options' => $request->get_param('options')
        );
        
        $result = $this->model->create_question($data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Return created question
        $question = $this->model->get_question($result);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Question created successfully',
            'question_id' => $result,
            'question' => $question
        ));
    }
    
    /**
     * Update question
     */
    public function update_question($request) {
        $question_id = $request->get_param('id');
        
        $data = array(
            'question_text' => $request->get_param('question_text'),
            'question_type' => $request->get_param('question_type'),
            'category' => $request->get_param('category'),
            'difficulty' => $request->get_param('difficulty'),
            'points' => $request->get_param('points'),
            'explanation' => $request->get_param('explanation'),
            'options' => $request->get_param('options')
        );
        
        $result = $this->model->update_question($question_id, $data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Return updated question
        $question = $this->model->get_question($question_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Question updated successfully',
            'question' => $question
        ));
    }
    
    /**
     * Delete question
     */
    public function delete_question($request) {
        $question_id = $request->get_param('id');
        
        $result = $this->model->delete_question($question_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Question deleted successfully'
        ));
    }
    
    /**
     * Validate answers
     */
    public function validate_answers($request) {
        $answers = $request->get_param('answers');
        $session_id = $request->get_param('session_id');
        
        if (!is_array($answers) || empty($answers)) {
            return new WP_Error('invalid_answers', 'Invalid answers format', array('status' => 400));
        }
        
        $results = array();
        $total_score = 0;
        $total_questions = count($answers);
        
        foreach ($answers as $question_id => $user_answers) {
            $question_id = intval($question_id);
            $question = $this->model->get_question($question_id);
            
            if (!$question) {
                continue;
            }
            
            // Get correct answers
            $correct_answers = array();
            foreach ($question->options as $option) {
                if ($option->is_correct) {
                    $correct_answers[] = $option->id;
                }
            }
            
            // Normalize user answers
            if (!is_array($user_answers)) {
                $user_answers = array($user_answers);
            }
            $user_answers = array_map('intval', $user_answers);
            
            // Check if correct
            $is_correct = (
                count($correct_answers) === count($user_answers) &&
                empty(array_diff($correct_answers, $user_answers))
            );
            
            if ($is_correct) {
                $total_score += $question->points;
            }
            
            $results[$question_id] = array(
                'question_id' => $question_id,
                'user_answers' => $user_answers,
                'correct_answers' => $correct_answers,
                'is_correct' => $is_correct,
                'points_earned' => $is_correct ? $question->points : 0,
                'max_points' => $question->points,
                'explanation' => $question->explanation
            );
        }
        
        // Calculate percentage
        $max_possible_score = array_sum(array_column($results, 'max_points'));
        $percentage = $max_possible_score > 0 ? round(($total_score / $max_possible_score) * 100, 2) : 0;
        
        // Log validation for analytics (if session_id provided)
        if ($session_id) {
            $this->log_answer_validation($session_id, $results, $total_score, $percentage);
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'results' => $results,
            'summary' => array(
                'total_questions' => $total_questions,
                'correct_answers' => count(array_filter($results, function($r) { return $r['is_correct']; })),
                'total_score' => $total_score,
                'max_possible_score' => $max_possible_score,
                'percentage' => $percentage
            )
        ));
    }
    
    /**
     * Get question statistics
     */
    public function get_question_statistics($request) {
        $campaign_id = $request->get_param('campaign_id');
        
        $stats = $this->model->get_statistics($campaign_id);
        $categories = $this->model->get_categories();
        
        return rest_ensure_response(array(
            'statistics' => $stats,
            'categories' => $categories
        ));
    }
    
    /**
     * Duplicate question
     */
    public function duplicate_question($request) {
        $question_id = $request->get_param('id');
        
        $result = $this->model->duplicate_question($question_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $new_question = $this->model->get_question($result);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Question duplicated successfully',
            'new_question_id' => $result,
            'new_question' => $new_question
        ));
    }
    
    /**
     * Bulk operations
     */
    public function bulk_operations($request) {
        $action = $request->get_param('action');
        $question_ids = $request->get_param('question_ids');
        
        if (!is_array($question_ids) || empty($question_ids)) {
            return new WP_Error('invalid_ids', 'Invalid question IDs', array('status' => 400));
        }
        
        $results = array();
        $success_count = 0;
        $error_count = 0;
        
        foreach ($question_ids as $question_id) {
            $question_id = intval($question_id);
            
            switch ($action) {
                case 'activate':
                case 'deactivate':
                    $status = ($action === 'activate') ? 1 : 0;
                    global $wpdb;
                    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
                    $result = $wpdb->update(
                        $table_prefix . 'questions',
                        array('is_active' => $status, 'updated_at' => current_time('mysql')),
                        array('id' => $question_id)
                    );
                    
                    if ($result !== false) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                    break;
                    
                case 'delete':
                    $result = $this->model->delete_question($question_id);
                    if (!is_wp_error($result)) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $results[$question_id] = $result->get_error_message();
                    }
                    break;
                    
                case 'duplicate':
                    $result = $this->model->duplicate_question($question_id);
                    if (!is_wp_error($result)) {
                        $success_count++;
                        $results[$question_id] = $result; // New question ID
                    } else {
                        $error_count++;
                        $results[$question_id] = $result->get_error_message();
                    }
                    break;
                    
                default:
                    return new WP_Error('invalid_action', 'Invalid bulk action', array('status' => 400));
            }
        }
        
        return rest_ensure_response(array(
            'success' => $error_count === 0,
            'message' => sprintf('%d operations completed successfully, %d failed', $success_count, $error_count),
            'success_count' => $success_count,
            'error_count' => $error_count,
            'results' => $results
        ));
    }
    
    /**
     * Import questions from CSV
     */
    public function import_questions($request) {
        $campaign_id = $request->get_param('campaign_id');
        
        // Check if file was uploaded
        if (empty($_FILES['csv_file']['tmp_name'])) {
            return new WP_Error('no_file', 'No CSV file uploaded', array('status' => 400));
        }
        
        $file_path = $_FILES['csv_file']['tmp_name'];
        
        // Use the QuestionBank import method
        $question_bank = new Vefify_Question_Bank();
        $result = $question_bank->import_questions_from_csv($file_path, $campaign_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'imported' => $result['imported'],
            'errors' => $result['errors'],
            'total_lines' => $result['total_lines'],
            'message' => sprintf('%d questions imported successfully', $result['imported'])
        ));
    }
    
    /**
     * Get categories
     */
    public function get_categories($request) {
        $categories = $this->model->get_categories();
        
        return rest_ensure_response(array(
            'categories' => $categories
        ));
    }
    
    /**
     * Check admin permission
     */
    public function check_admin_permission($request) {
        return current_user_can('manage_options');
    }
    
    /**
     * Log answer validation for analytics
     */
    private function log_answer_validation($session_id, $results, $total_score, $percentage) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Log to analytics table if it exists
        $analytics_table = $table_prefix . 'analytics';
        
        $wpdb->insert(
            $analytics_table,
            array(
                'campaign_id' => 0, // Will be updated if we can get it from session
                'event_type' => 'question_answer',
                'session_id' => $session_id,
                'event_data' => json_encode(array(
                    'total_score' => $total_score,
                    'percentage' => $percentage,
                    'results' => $results
                )),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Get question performance analytics
     */
    public function get_question_performance($request) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('permission_denied', 'Insufficient permissions', array('status' => 403));
        }
        
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        $campaign_id = $request->get_param('campaign_id');
        
        $where_clause = '';
        $params = array();
        
        if ($campaign_id) {
            $where_clause = 'WHERE a.campaign_id = %d';
            $params[] = $campaign_id;
        }
        
        // Get question performance data from analytics
        $query = "
            SELECT 
                JSON_EXTRACT(a.event_data, '$.results') as results_data,
                COUNT(*) as attempts
            FROM {$table_prefix}analytics a
            WHERE a.event_type = 'question_answer'
            {$where_clause}
            GROUP BY a.session_id
            ORDER BY a.created_at DESC
            LIMIT 1000
        ";
        
        $analytics_data = !empty($params) 
            ? $wpdb->get_results($wpdb->prepare($query, $params))
            : $wpdb->get_results($query);
        
        // Process analytics data to get question performance
        $question_stats = array();
        
        foreach ($analytics_data as $data) {
            $results = json_decode($data->results_data, true);
            if (!$results) continue;
            
            foreach ($results as $question_id => $result) {
                if (!isset($question_stats[$question_id])) {
                    $question_stats[$question_id] = array(
                        'question_id' => $question_id,
                        'total_attempts' => 0,
                        'correct_attempts' => 0,
                        'correct_rate' => 0
                    );
                }
                
                $question_stats[$question_id]['total_attempts']++;
                
                if ($result['is_correct']) {
                    $question_stats[$question_id]['correct_attempts']++;
                }
            }
        }
        
        // Calculate correct rates
        foreach ($question_stats as &$stats) {
            $stats['correct_rate'] = $stats['total_attempts'] > 0 
                ? round(($stats['correct_attempts'] / $stats['total_attempts']) * 100, 2)
                : 0;
        }
        
        // Sort by correct rate (ascending - most difficult first)
        usort($question_stats, function($a, $b) {
            return $a['correct_rate'] <=> $b['correct_rate'];
        });
        
        return rest_ensure_response(array(
            'question_performance' => array_values($question_stats),
            'total_sessions_analyzed' => count($analytics_data)
        ));
    }
}

// Initialize endpoints
new Vefify_Question_Endpoints();