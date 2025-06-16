<?php
/**
 * REST API Handler Class
 * File: includes/class-rest-api.php
 * 
 * Handles all REST API endpoints for the quiz
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Rest_API {
    
    private $namespace = 'vefify/v1';
    private $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new Vefify_Quiz_Database();
    }
    
    /**
     * Register all REST API routes
     */
    public function register_routes() {
        // Participant management
        register_rest_route($this->namespace, '/check-participation', array(
            'methods' => 'POST',
            'callback' => array($this, 'check_participation'),
            'permission_callback' => '__return_true',
            'args' => array(
                'phone' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'campaign_id' => array(
                    'required' => true,
                    'type' => 'integer'
                )
            )
        ));
        
        register_rest_route($this->namespace, '/start-quiz', array(
            'methods' => 'POST',
            'callback' => array($this, 'start_quiz'),
            'permission_callback' => '__return_true',
            'args' => array(
                'campaign_id' => array(
                    'required' => true,
                    'type' => 'integer'
                ),
                'user_data' => array(
                    'required' => true,
                    'type' => 'object'
                )
            )
        ));
        
        register_rest_route($this->namespace, '/submit-quiz', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_quiz'),
            'permission_callback' => '__return_true',
            'args' => array(
                'session_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'answers' => array(
                    'required' => true,
                    'type' => 'object'
                )
            )
        ));
        
        // Question management
        register_rest_route($this->namespace, '/questions/(?P<campaign_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_questions'),
            'permission_callback' => '__return_true',
            'args' => array(
                'campaign_id' => array(
                    'required' => true,
                    'type' => 'integer'
                ),
                'count' => array(
                    'default' => 5,
                    'type' => 'integer'
                )
            )
        ));
        
        // Campaign information
        register_rest_route($this->namespace, '/campaigns/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_campaign'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route($this->namespace, '/campaigns', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_campaigns'),
            'permission_callback' => '__return_true',
            'args' => array(
                'status' => array(
                    'default' => 'active',
                    'type' => 'string'
                ),
                'per_page' => array(
                    'default' => 10,
                    'type' => 'integer'
                )
            )
        ));
        
        // Analytics endpoints
        register_rest_route($this->namespace, '/analytics/(?P<campaign_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_analytics'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Status endpoint
        register_rest_route($this->namespace, '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_status'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Check if phone number has already participated
     */
    public function check_participation($request) {
        $phone = Vefify_Quiz_Utilities::format_phone_number($request['phone']);
        $campaign_id = intval($request['campaign_id']);
        
        if (!Vefify_Quiz_Utilities::validate_phone_number($phone)) {
            return new WP_Error('invalid_phone', 'Invalid phone number format', array('status' => 400));
        }
        
        // Check rate limiting
        $rate_limit_key = 'check_participation_' . Vefify_Quiz_Utilities::get_client_ip();
        if (!Vefify_Quiz_Utilities::check_rate_limit($rate_limit_key, 10, 60)) {
            return new WP_Error('rate_limit', 'Too many requests', array('status' => 429));
        }
        
        $participants_table = $this->database->get_table_name('participants');
        $existing = $this->database->get_results(
            "SELECT id, completed_at FROM {$participants_table} WHERE campaign_id = %d AND phone_number = %s",
            array($campaign_id, $phone)
        );
        
        $participated = !empty($existing);
        $completed = $participated && !empty($existing[0]->completed_at);
        
        return rest_ensure_response(array(
            'participated' => $participated,
            'completed' => $completed,
            'message' => $participated 
                ? ($completed ? 'Quiz already completed' : 'Registration found - can continue')
                : 'Can participate'
        ));
    }
    
    /**
     * Start a new quiz session
     */
    public function start_quiz($request) {
        $campaign_id = intval($request['campaign_id']);
        $user_data = $request['user_data'];
        
        // Validate campaign
        $campaign = $this->get_campaign_by_id($campaign_id);
        if (!$campaign) {
            return new WP_Error('invalid_campaign', 'Campaign not found or inactive', array('status' => 404));
        }
        
        // Validate user data
        $sanitized_data = Vefify_Quiz_Utilities::sanitize_quiz_data($user_data);
        $validation_errors = $this->validate_user_data($sanitized_data);
        
        if (!empty($validation_errors)) {
            return new WP_Error('validation_failed', 'Invalid user data', array(
                'status' => 400,
                'errors' => $validation_errors
            ));
        }
        
        // Check if already participated
        $participants_table = $this->database->get_table_name('participants');
        $existing = $this->database->get_results(
            "SELECT id FROM {$participants_table} WHERE campaign_id = %d AND phone_number = %s",
            array($campaign_id, $sanitized_data['phone_number'])
        );
        
        if (!empty($existing)) {
            return new WP_Error('already_participated', 'Phone number already registered', array('status' => 409));
        }
        
        // Get questions for the quiz
        $questions = $this->get_quiz_questions($campaign_id, $campaign->questions_per_quiz);
        if (empty($questions)) {
            return new WP_Error('no_questions', 'No questions available for this campaign', array('status' => 500));
        }
        
        // Create participant record
        $session_id = Vefify_Quiz_Utilities::generate_session_id();
        
        $participant_data = array(
            'campaign_id' => $campaign_id,
            'session_id' => $session_id,
            'full_name' => $sanitized_data['full_name'],
            'phone_number' => $sanitized_data['phone_number'],
            'province' => $sanitized_data['province'],
            'pharmacy_code' => $sanitized_data['pharmacy_code'] ?? '',
            'email' => $sanitized_data['email'] ?? '',
            'total_questions' => count($questions),
            'started_at' => current_time('mysql'),
            'ip_address' => Vefify_Quiz_Utilities::get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        $participant_id = $this->database->insert('participants', $participant_data);
        
        if (is_wp_error($participant_id)) {
            return new WP_Error('db_error', 'Failed to create participant record', array('status' => 500));
        }
        
        // Create quiz session
        $session_data = array(
            'session_id' => $session_id,
            'participant_id' => $participant_id,
            'campaign_id' => $campaign_id,
            'questions_data' => json_encode(array_column($questions, 'id')),
            'answers_data' => json_encode(array()),
            'time_remaining' => $campaign->time_limit
        );
        
        $session_result = $this->database->insert('quiz_sessions', $session_data);
        
        if (is_wp_error($session_result)) {
            return new WP_Error('db_error', 'Failed to create quiz session', array('status' => 500));
        }
        
        // Log analytics
        $this->log_analytics($campaign_id, 'start', $participant_id, $session_id);
        
        // Format questions for frontend (remove correct answers)
        $formatted_questions = array();
        foreach ($questions as $question) {
            $formatted_question = array(
                'id' => $question->id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'category' => $question->category,
                'difficulty' => $question->difficulty,
                'points' => $question->points,
                'options' => array()
            );
            
            foreach ($question->options as $option) {
                $formatted_question['options'][] = array(
                    'id' => $option->id,
                    'text' => $option->option_text,
                    'order_index' => $option->order_index
                );
            }
            
            $formatted_questions[] = $formatted_question;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'session_id' => $session_id,
            'participant_id' => $participant_id,
            'campaign' => array(
                'id' => $campaign->id,
                'name' => $campaign->name,
                'questions_per_quiz' => $campaign->questions_per_quiz,
                'time_limit' => $campaign->time_limit,
                'pass_score' => $campaign->pass_score
            ),
            'questions' => $formatted_questions
        ));
    }
    
    /**
     * Submit quiz answers and calculate results
     */
    public function submit_quiz($request) {
        $session_id = sanitize_text_field($request['session_id']);
        $answers = $request['answers'];
        
        if (!is_array($answers) || empty($answers)) {
            return new WP_Error('invalid_answers', 'Answers are required', array('status' => 400));
        }
        
        // Get session data
        $sessions_table = $this->database->get_table_name('quiz_sessions');
        $participants_table = $this->database->get_table_name('participants');
        
        $session = $this->database->get_results(
            "SELECT s.*, p.campaign_id, p.total_questions 
             FROM {$sessions_table} s 
             JOIN {$participants_table} p ON s.participant_id = p.id 
             WHERE s.session_id = %s",
            array($session_id)
        );
        
        if (empty($session)) {
            return new WP_Error('invalid_session', 'Session not found', array('status' => 404));
        }
        
        $session = $session[0];
        
        if ($session->is_completed) {
            return new WP_Error('already_completed', 'Quiz already completed', array('status' => 409));
        }
        
        // Get questions with correct answers
        $question_ids = json_decode($session->questions_data, true);
        $questions = $this->get_questions_with_answers($question_ids);
        
        // Calculate score
        $score_data = Vefify_Quiz_Utilities::calculate_score($answers, $questions);
        $completion_time = time() - strtotime($session->created_at);
        
        // Update participant record
        $participant_update = array(
            'score' => $score_data['score'],
            'completion_time' => $completion_time,
            'completed_at' => current_time('mysql')
        );
        
        $this->database->update('participants', $participant_update, array('id' => $session->participant_id));
        
        // Update session
        $session_update = array(
            'answers_data' => json_encode($answers),
            'is_completed' => 1
        );
        
        $this->database->update('quiz_sessions', $session_update, array('id' => $session->id));
        
        // Check for gifts
        $gift_result = $this->check_and_assign_gift($session->campaign_id, $session->participant_id, $score_data['score']);
        
        // Log analytics
        $this->log_analytics($session->campaign_id, 'complete', $session->participant_id, $session_id, array(
            'score' => $score_data['score'],
            'completion_time' => $completion_time
        ));
        
        return rest_ensure_response(array(
            'success' => true,
            'score' => $score_data['score'],
            'total_questions' => $session->total_questions,
            'percentage' => $score_data['percentage'],
            'completion_time' => $completion_time,
            'gift' => $gift_result,
            'passed' => $this->check_pass_status($session->campaign_id, $score_data['score'])
        ));
    }
    
    /**
     * Get questions for a campaign
     */
    public function get_questions($request) {
        $campaign_id = intval($request['campaign_id']);
        $count = intval($request['count']);
        
        $questions = $this->get_quiz_questions($campaign_id, $count);
        
        if (empty($questions)) {
            return new WP_Error('no_questions', 'No questions found', array('status' => 404));
        }
        
        return rest_ensure_response($questions);
    }
    
    /**
     * Get campaign information
     */
    public function get_campaign($request) {
        $campaign_id = intval($request['id']);
        $campaign = $this->get_campaign_by_id($campaign_id);
        
        if (!$campaign) {
            return new WP_Error('not_found', 'Campaign not found', array('status' => 404));
        }
        
        return rest_ensure_response($campaign);
    }
    
    /**
     * Get list of campaigns
     */
    public function get_campaigns($request) {
        $status = sanitize_text_field($request['status']);
        $per_page = intval($request['per_page']);
        
        $campaigns_table = $this->database->get_table_name('campaigns');
        $where_clause = '';
        $params = array();
        
        if ($status === 'active') {
            $where_clause = 'WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()';
        } elseif ($status === 'inactive') {
            $where_clause = 'WHERE is_active = 0 OR end_date < NOW()';
        }
        
        $campaigns = $this->database->get_results(
            "SELECT * FROM {$campaigns_table} {$where_clause} ORDER BY created_at DESC LIMIT %d",
            array($per_page)
        );
        
        return rest_ensure_response($campaigns);
    }
    
    /**
     * Get analytics data (admin only)
     */
    public function get_analytics($request) {
        $campaign_id = intval($request['campaign_id']);
        
        // Get basic analytics
        $participants_table = $this->database->get_table_name('participants');
        
        $stats = $this->database->get_results(
            "SELECT 
                COUNT(*) as total_participants,
                COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed,
                AVG(score) as avg_score,
                COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as gifts_awarded
             FROM {$participants_table} 
             WHERE campaign_id = %d",
            array($campaign_id)
        );
        
        return rest_ensure_response($stats[0] ?? array());
    }
    
    /**
     * Get system status
     */
    public function get_status($request) {
        $health = $this->database->check_database_health();
        $system_info = Vefify_Quiz_Utilities::get_system_info();
        
        return rest_ensure_response(array(
            'status' => 'ok',
            'timestamp' => current_time('c'),
            'database' => $health,
            'system' => $system_info,
            'plugin_version' => VEFIFY_QUIZ_VERSION
        ));
    }
    
    /**
     * Helper Methods
     */
    
    private function get_campaign_by_id($campaign_id) {
        $campaigns_table = $this->database->get_table_name('campaigns');
        $results = $this->database->get_results(
            "SELECT * FROM {$campaigns_table} WHERE id = %d AND is_active = 1",
            array($campaign_id)
        );
        
        return !empty($results) ? $results[0] : null;
    }
    
    private function get_quiz_questions($campaign_id, $count) {
        $questions_table = $this->database->get_table_name('questions');
        $options_table = $this->database->get_table_name('question_options');
        
        // Get questions
        $questions = $this->database->get_results(
            "SELECT * FROM {$questions_table} 
             WHERE (campaign_id = %d OR campaign_id IS NULL) AND is_active = 1 
             ORDER BY RAND() 
             LIMIT %d",
            array($campaign_id, $count * 2) // Get more for randomization
        );
        
        if (empty($questions)) {
            return array();
        }
        
        // Shuffle and limit
        shuffle($questions);
        $questions = array_slice($questions, 0, $count);
        
        // Get options for each question
        foreach ($questions as &$question) {
            $options = $this->database->get_results(
                "SELECT * FROM {$options_table} WHERE question_id = %d ORDER BY order_index",
                array($question->id)
            );
            
            $question->options = $options;
        }
        
        return $questions;
    }
    
    private function get_questions_with_answers($question_ids) {
        if (empty($question_ids)) {
            return array();
        }
        
        $questions_table = $this->database->get_table_name('questions');
        $options_table = $this->database->get_table_name('question_options');
        
        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        
        $questions = $this->database->get_results(
            "SELECT * FROM {$questions_table} WHERE id IN ({$placeholders})",
            $question_ids
        );
        
        foreach ($questions as &$question) {
            $options = $this->database->get_results(
                "SELECT * FROM {$options_table} WHERE question_id = %d ORDER BY order_index",
                array($question->id)
            );
            
            $question->options = $options;
        }
        
        return $questions;
    }
    
    private function validate_user_data($data) {
        $errors = array();
        
        if (empty($data['full_name'])) {
            $errors['full_name'] = 'Full name is required';
        }
        
        if (empty($data['phone_number'])) {
            $errors['phone_number'] = 'Phone number is required';
        } elseif (!Vefify_Quiz_Utilities::validate_phone_number($data['phone_number'])) {
            $errors['phone_number'] = 'Invalid phone number format';
        }
        
        if (empty($data['province'])) {
            $errors['province'] = 'Province is required';
        }
        
        return $errors;
    }
    
    private function check_and_assign_gift($campaign_id, $participant_id, $score) {
        $gifts_table = $this->database->get_table_name('gifts');
        
        // Find eligible gift
        $gift = $this->database->get_results(
            "SELECT * FROM {$gifts_table} 
             WHERE campaign_id = %d 
             AND is_active = 1 
             AND min_score <= %d 
             AND (max_score IS NULL OR max_score >= %d)
             AND (max_quantity IS NULL OR used_count < max_quantity)
             ORDER BY min_score DESC, gift_value DESC 
             LIMIT 1",
            array($campaign_id, $score, $score)
        );
        
        if (empty($gift)) {
            return array(
                'has_gift' => false,
                'message' => 'No gifts available for your score'
            );
        }
        
        $gift = $gift[0];
        
        // Generate gift code
        $gift_code = Vefify_Quiz_Utilities::generate_gift_code($gift->gift_code_prefix);
        
        // Update participant with gift
        $this->database->update('participants', array(
            'gift_id' => $gift->id,
            'gift_code' => $gift_code,
            'gift_status' => 'assigned'
        ), array('id' => $participant_id));
        
        // Update gift usage count
        $this->database->execute_query(
            "UPDATE {$gifts_table} SET used_count = used_count + 1 WHERE id = %d",
            array($gift->id)
        );
        
        return array(
            'has_gift' => true,
            'gift_id' => $gift->id,
            'gift_name' => $gift->gift_name,
            'gift_type' => $gift->gift_type,
            'gift_value' => $gift->gift_value,
            'gift_code' => $gift_code,
            'gift_description' => $gift->gift_description
        );
    }
    
    private function check_pass_status($campaign_id, $score) {
        $campaign = $this->get_campaign_by_id($campaign_id);
        return $campaign && $score >= $campaign->pass_score;
    }
    
    private function log_analytics($campaign_id, $event_type, $participant_id = null, $session_id = null, $event_data = null) {
        $analytics_table = $this->database->get_table_name('analytics');
        
        $this->database->insert('analytics', array(
            'campaign_id' => $campaign_id,
            'event_type' => $event_type,
            'participant_id' => $participant_id,
            'session_id' => $session_id,
            'event_data' => $event_data ? json_encode($event_data) : null,
            'ip_address' => Vefify_Quiz_Utilities::get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ));
    }
    
    /**
     * Permission callback for admin endpoints
     */
    public function check_admin_permission($request) {
        return current_user_can('manage_options');
    }
}