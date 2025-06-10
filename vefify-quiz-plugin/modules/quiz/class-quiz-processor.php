<?php
/**
 * Quiz Processor Module
 * File: modules/quiz/class-quiz-processor.php
 */

class QuizProcessor {
    private $db;
    private $campaign_manager;
    private $gift_manager;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->campaign_manager = new CampaignManager();
        $this->gift_manager = new GiftManager();
    }
    
    /**
     * Check if phone number already participated
     */
    public function check_participation($request) {
        $phone = sanitize_text_field($request->get_param('phone'));
        $campaign_id = intval($request->get_param('campaign_id'));
        
        if (!$phone || !$campaign_id) {
            return new \WP_Error('missing_data', 'Phone and campaign ID required', ['status' => 400]);
        }
        
        $phone = vefify_format_phone($phone);
        $table = $this->db->prefix . 'vefify_quiz_users';
        
        $exists = $this->db->get_var($this->db->prepare(
            "SELECT id FROM {$table} WHERE campaign_id = %d AND phone_number = %s",
            $campaign_id, $phone
        ));
        
        return rest_ensure_response([
            'success' => true,
            'participated' => !empty($exists),
            'message' => $exists ? 'You have already participated in this campaign' : 'You can participate'
        ]);
    }
    
    /**
     * Start quiz session
     */
    public function start_quiz($request) {
        $campaign_id = intval($request->get_param('campaign_id'));
        $user_data = $request->get_param('user_data');
        
        // Validate input
        if (!$campaign_id || !$user_data) {
            return new \WP_Error('missing_data', 'Campaign ID and user data required', ['status' => 400]);
        }
        
        // Validate user data
        $validation = $this->validate_user_data($user_data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Check campaign exists and is active
        $campaign = $this->campaign_manager->get_campaign_data($campaign_id);
        if (!$campaign) {
            return new \WP_Error('invalid_campaign', 'Campaign not found or inactive', ['status' => 404]);
        }
        
        // Check if already participated
        $phone = vefify_format_phone($user_data['phone_number']);
        $participation_check = $this->check_existing_participation($campaign_id, $phone);
        if ($participation_check['participated']) {
            return new \WP_Error('already_participated', $participation_check['message'], ['status' => 409]);
        }
        
        // Create user record
        $user_id = $this->create_quiz_user($campaign_id, $user_data);
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Generate session
        $session_id = $this->generate_session_id();
        
        // Get quiz questions
        $questions = $this->campaign_manager->get_campaign_questions($campaign_id, $campaign['questions_per_quiz']);
        
        if (empty($questions)) {
            return new \WP_Error('no_questions', 'No questions available for this campaign', ['status' => 500]);
        }
        
        // Create quiz session
        $session_data = $this->create_quiz_session($session_id, $user_id, $campaign_id, $questions);
        
        // Track analytics
        $this->track_event('start', $campaign_id, $user_id, $session_id);
        
        return rest_ensure_response([
            'success' => true,
            'data' => [
                'session_id' => $session_id,
                'user_id' => $user_id,
                'campaign' => $campaign,
                'questions' => $this->prepare_questions_for_frontend($questions),
                'time_limit' => $campaign['time_limit']
            ]
        ]);
    }
    
    /**
     * Submit quiz answers
     */
    public function submit_quiz($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $answers = $request->get_param('answers');
        
        if (!$session_id || !is_array($answers)) {
            return new \WP_Error('missing_data', 'Session ID and answers required', ['status' => 400]);
        }
        
        // Get session data
        $session = $this->get_quiz_session($session_id);
        if (!$session) {
            return new \WP_Error('invalid_session', 'Quiz session not found', ['status' => 404]);
        }
        
        if ($session['is_completed']) {
            return new \WP_Error('already_completed', 'Quiz already completed', ['status' => 409]);
        }
        
        // Calculate score
        $score_data = $this->calculate_quiz_score($session, $answers);
        
        // Update user record with results
        $this->complete_quiz_user($session['user_id'], $score_data, $answers);
        
        // Mark session as completed
        $this->complete_quiz_session($session_id);
        
        // Assign gift based on score
        $gift_result = $this->gift_manager->assign_gift($session['campaign_id'], $session['user_id'], $score_data['score']);
        
        // Track completion
        $this->track_event('complete', $session['campaign_id'], $session['user_id'], $session_id, [
            'score' => $score_data['score'],
            'total_questions' => $score_data['total_questions'],
            'completion_time' => $score_data['completion_time']
        ]);
        
        return rest_ensure_response([
            'success' => true,
            'data' => [
                'score' => $score_data['score'],
                'total_questions' => $score_data['total_questions'],
                'percentage' => round(($score_data['score'] / $score_data['total_questions']) * 100),
                'completion_time' => $score_data['completion_time'],
                'gift' => $gift_result,
                'detailed_results' => $score_data['detailed_results']
            ]
        ]);
    }
    
    /**
     * Validate user data
     */
    private function validate_user_data($user_data) {
        $required_fields = ['full_name', 'phone_number', 'province'];
        
        foreach ($required_fields as $field) {
            if (empty($user_data[$field])) {
                return new \WP_Error('missing_field', "Field {$field} is required", ['status' => 400]);
            }
        }
        
        // Validate phone number
        if (!vefify_validate_vietnamese_phone($user_data['phone_number'])) {
            return new \WP_Error('invalid_phone', 'Invalid Vietnamese phone number', ['status' => 400]);
        }
        
        return true;
    }
    
    /**
     * Check existing participation
     */
    private function check_existing_participation($campaign_id, $phone) {
        $table = $this->db->prefix . 'vefify_quiz_users';
        
        $existing = $this->db->get_row($this->db->prepare(
            "SELECT id, completed_at FROM {$table} WHERE campaign_id = %d AND phone_number = %s",
            $campaign_id, $phone
        ));
        
        return [
            'participated' => !empty($existing),
            'message' => $existing ? 'You have already participated in this campaign' : 'You can participate',
            'data' => $existing
        ];
    }
    
    /**
     * Create quiz user record
     */
    private function create_quiz_user($campaign_id, $user_data) {
        $table = $this->db->prefix . 'vefify_quiz_users';
        
        $result = $this->db->insert($table, [
            'campaign_id' => $campaign_id,
            'session_id' => '', // Will be updated later
            'full_name' => sanitize_text_field($user_data['full_name']),
            'phone_number' => vefify_format_phone($user_data['phone_number']),
            'province' => sanitize_text_field($user_data['province']),
            'pharmacy_code' => sanitize_text_field($user_data['pharmacy_code'] ?? ''),
            'email' => sanitize_email($user_data['email'] ?? ''),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'started_at' => current_time('mysql')
        ]);
        
        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to create user record');
        }
        
        return $this->db->insert_id;
    }
    
    /**
     * Generate unique session ID
     */
    private function generate_session_id() {
        return 'vq_' . uniqid() . '_' . wp_generate_password(8, false);
    }
    
    /**
     * Create quiz session
     */
    private function create_quiz_session($session_id, $user_id, $campaign_id, $questions) {
        $sessions_table = $this->db->prefix . 'vefify_quiz_sessions';
        $users_table = $this->db->prefix . 'vefify_quiz_users';
        
        // Prepare questions data (remove correct answers for security)
        $questions_data = array_map(function($q) {
            return $q['id'];
        }, $questions);
        
        $result = $this->db->insert($sessions_table, [
            'session_id' => $session_id,
            'user_id' => $user_id,
            'campaign_id' => $campaign_id,
            'questions_data' => json_encode($questions_data),
            'answers_data' => json_encode([])
        ]);
        
        // Update user record with session ID
        $this->db->update($users_table, 
            ['session_id' => $session_id],
            ['id' => $user_id]
        );
        
        return $result !== false;
    }
    
    /**
     * Prepare questions for frontend (remove correct answers)
     */
    private function prepare_questions_for_frontend($questions) {
        return array_map(function($question) {
            // Remove correct answer indicators
            $question['options'] = array_map(function($option) {
                unset($option['is_correct']);
                return $option;
            }, $question['options']);
            
            return $question;
        }, $questions);
    }
    
    /**
     * Calculate quiz score
     */
    private function calculate_quiz_score($session, $answers) {
        $questions_ids = json_decode($session['questions_data'], true);
        $questions_table = $this->db->prefix . 'vefify_questions';
        $options_table = $this->db->prefix . 'vefify_question_options';
        
        $score = 0;
        $detailed_results = [];
        $start_time = strtotime($session['created_at']);
        $completion_time = time() - $start_time;
        
        foreach ($questions_ids as $question_id) {
            // Get correct answers for this question
            $correct_options = $this->db->get_col($this->db->prepare(
                "SELECT id FROM {$options_table} WHERE question_id = %d AND is_correct = 1",
                $question_id
            ));
            
            $user_answers = $answers[$question_id] ?? [];
            if (!is_array($user_answers)) {
                $user_answers = [$user_answers];
            }
            
            // Check if answer is correct
            $is_correct = (
                count($correct_options) === count($user_answers) &&
                empty(array_diff($correct_options, array_map('intval', $user_answers)))
            );
            
            if ($is_correct) {
                $score++;
            }
            
            $detailed_results[$question_id] = [
                'user_answers' => $user_answers,
                'correct_answers' => $correct_options,
                'is_correct' => $is_correct
            ];
        }
        
        return [
            'score' => $score,
            'total_questions' => count($questions_ids),
            'completion_time' => $completion_time,
            'detailed_results' => $detailed_results
        ];
    }
    
    /**
     * Complete quiz user record
     */
    private function complete_quiz_user($user_id, $score_data, $answers) {
        $table = $this->db->prefix . 'vefify_quiz_users';
        
        $this->db->update($table, [
            'score' => $score_data['score'],
            'total_questions' => $score_data['total_questions'],
            'completion_time' => $score_data['completion_time'],
            'completed_at' => current_time('mysql')
        ], ['id' => $user_id]);
    }
    
    /**
     * Complete quiz session
     */
    private function complete_quiz_session($session_id) {
        $table = $this->db->prefix . 'vefify_quiz_sessions';
        
        $this->db->update($table, [
            'is_completed' => 1,
            'updated_at' => current_time('mysql')
        ], ['session_id' => $session_id]);
    }
    
    /**
     * Get quiz session
     */
    private function get_quiz_session($session_id) {
        $table = $this->db->prefix . 'vefify_quiz_sessions';
        
        return $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$table} WHERE session_id = %s",
            $session_id
        ), ARRAY_A);
    }
    
    /**
     * Track analytics event
     */
    private function track_event($event_type, $campaign_id, $user_id = null, $session_id = null, $event_data = []) {
        $table = $this->db->prefix . 'vefify_analytics';
        
        $this->db->insert($table, [
            'campaign_id' => $campaign_id,
            'event_type' => $event_type,
            'user_id' => $user_id,
            'session_id' => $session_id,
            'event_data' => json_encode($event_data),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '')
        ]);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}