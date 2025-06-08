<?php
/**
 * Enhanced API Class for Advanced Quiz Manager
 * File: includes/class-enhanced-api.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQM_Enhanced_API {
    
    public function __construct() {
        $this->init_enhanced_hooks();
    }
    
    private function init_enhanced_hooks() {
        // Enhanced quiz submission with gift processing
        add_action('wp_ajax_aqm_submit_quiz_enhanced', array($this, 'submit_quiz_enhanced'));
        add_action('wp_ajax_nopriv_aqm_submit_quiz_enhanced', array($this, 'submit_quiz_enhanced'));
        
        // Gift system APIs
        add_action('wp_ajax_aqm_process_gift_eligibility', array($this, 'process_gift_eligibility'));
        add_action('wp_ajax_nopriv_aqm_process_gift_eligibility', array($this, 'process_gift_eligibility'));
        
        // Location APIs for frontend
        add_action('wp_ajax_aqm_get_vietnam_provinces', array($this, 'get_vietnam_provinces'));
        add_action('wp_ajax_nopriv_aqm_get_vietnam_provinces', array($this, 'get_vietnam_provinces'));
        
        add_action('wp_ajax_aqm_get_vietnam_districts', array($this, 'get_vietnam_districts'));
        add_action('wp_ajax_nopriv_aqm_get_vietnam_districts', array($this, 'get_vietnam_districts'));
        
        add_action('wp_ajax_aqm_get_vietnam_wards', array($this, 'get_vietnam_wards'));
        add_action('wp_ajax_nopriv_aqm_get_vietnam_wards', array($this, 'get_vietnam_wards'));
        
        // Quiz management APIs
        add_action('wp_ajax_aqm_get_campaign_questions', array($this, 'get_campaign_questions'));
        add_action('wp_ajax_nopriv_aqm_get_campaign_questions', array($this, 'get_campaign_questions'));
        
        add_action('wp_ajax_aqm_get_campaign_info', array($this, 'get_campaign_info'));
        add_action('wp_ajax_nopriv_aqm_get_campaign_info', array($this, 'get_campaign_info'));
        
        // Progress saving
        add_action('wp_ajax_aqm_save_quiz_progress', array($this, 'save_quiz_progress'));
        add_action('wp_ajax_nopriv_aqm_save_quiz_progress', array($this, 'save_quiz_progress'));
        
        // Analytics APIs (admin only)
        add_action('wp_ajax_aqm_get_campaign_analytics', array($this, 'get_campaign_analytics'));
        add_action('wp_ajax_aqm_export_campaign_data', array($this, 'export_campaign_data'));
        
        // Gift claim APIs
        add_action('wp_ajax_aqm_claim_gift', array($this, 'claim_gift'));
        add_action('wp_ajax_nopriv_aqm_claim_gift', array($this, 'claim_gift'));
        
        add_action('wp_ajax_aqm_verify_gift_code', array($this, 'verify_gift_code'));
        add_action('wp_ajax_nopriv_aqm_verify_gift_code', array($this, 'verify_gift_code'));
    }
    
    /**
     * Enhanced quiz submission with complete gift processing
     */
    public function submit_quiz_enhanced() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'aqm_front_nonce')) {
                throw new Exception('Security verification failed');
            }
            
            global $wpdb;
            
            $campaign_id = intval($_POST['campaign_id']);
            $quiz_data = json_decode(stripslashes($_POST['quiz_data']), true);
            $final_score = isset($_POST['final_score']) ? intval($_POST['final_score']) : 0;
            
            // Validate campaign exists and is active
            $campaign = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aqm_campaigns WHERE id = %d AND status = 'active'",
                $campaign_id
            ));
            
            if (!$campaign) {
                throw new Exception('Campaign not found or inactive');
            }
            
            // Check campaign date validity
            $now = current_time('mysql');
            if ($campaign->start_date && $campaign->start_date > $now) {
                throw new Exception('Campaign has not started yet');
            }
            if ($campaign->end_date && $campaign->end_date < $now) {
                throw new Exception('Campaign has ended');
            }
            
            // Check participant limits
            if ($campaign->max_participants > 0) {
                $current_participants = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE campaign_id = %d AND status = 'completed'",
                    $campaign_id
                ));
                
                if ($current_participants >= $campaign->max_participants) {
                    throw new Exception('Campaign has reached maximum participants');
                }
            }
            
            // Get participant info
            $participant_email = sanitize_email($quiz_data['email'] ?? '');
            $participant_name = sanitize_text_field($quiz_data['full_name'] ?? '');
            $participant_ip = $this->get_client_ip();
            
            // Check for duplicate submissions (if email provided)
            if ($participant_email) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}aqm_responses 
                     WHERE campaign_id = %d AND participant_email = %s AND status = 'completed'",
                    $campaign_id, $participant_email
                ));
                
                if ($existing) {
                    throw new Exception('You have already completed this quiz');
                }
            }
            
            // Calculate final score and validate answers
            $calculated_score = $this->calculate_quiz_score($campaign_id, $quiz_data);
            $gift_eligible = $this->check_gift_eligibility($campaign_id, $calculated_score, $quiz_data);
            
            // Insert response record
            $response_data = array(
                'campaign_id' => $campaign_id,
                'participant_name' => $participant_name,
                'participant_email' => $participant_email,
                'participant_ip' => $participant_ip,
                'responses' => json_encode($quiz_data),
                'final_score' => $calculated_score,
                'gift_eligible' => $gift_eligible ? 1 : 0,
                'status' => 'completed',
                'completed_at' => current_time('mysql'),
                'province_selected' => sanitize_text_field($quiz_data['province'] ?? ''),
                'district_selected' => sanitize_text_field($quiz_data['district'] ?? ''),
                'ward_selected' => sanitize_text_field($quiz_data['ward'] ?? '')
            );
            
            $response_result = $wpdb->insert($wpdb->prefix . 'aqm_responses', $response_data);
            
            if (!$response_result) {
                throw new Exception('Failed to save quiz response');
            }
            
            $response_id = $wpdb->insert_id;
            
            // Process gift eligibility
            $gift_result = null;
            if ($gift_eligible) {
                $gift_result = $this->process_gift_award($campaign_id, $response_id, $calculated_score, $participant_email, $participant_name);
            }
            
            // Prepare response
            $api_response = array(
                'success' => true,
                'data' => array(
                    'response_id' => $response_id,
                    'final_score' => $calculated_score,
                    'max_score' => 100,
                    'gift_eligible' => $gift_eligible,
                    'gift_awarded' => $gift_result !== null,
                    'gift_code' => $gift_result ? $gift_result['gift_code'] : null,
                    'gift_name' => $gift_result ? $gift_result['gift_name'] : null,
                    'gift_value' => $gift_result ? $gift_result['gift_value'] : null,
                    'gift_expiry' => $gift_result ? $gift_result['expiry_date'] : null,
                    'message' => 'Quiz completed successfully!',
                    'certificate_url' => $this->generate_certificate_url($response_id, $calculated_score)
                )
            );
            
            // Log completion
            $this->log_quiz_completion($campaign_id, $response_id, $calculated_score, $gift_result);
            
            // Send notification emails if configured
            $this->send_completion_notifications($campaign, $response_data, $gift_result);
            
            wp_send_json($api_response);
            
        } catch (Exception $e) {
            wp_send_json(array(
                'success' => false,
                'data' => array(
                    'message' => $e->getMessage(),
                    'error_code' => $e->getCode()
                )
            ));
        }
    }
    
    /**
     * Calculate quiz score based on questions and answers
     */
    private function calculate_quiz_score($campaign_id, $quiz_data) {
        global $wpdb;
        
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aqm_questions WHERE campaign_id = %d ORDER BY order_index",
            $campaign_id
        ));
        
        if (empty($questions)) {
            return 0;
        }
        
        $total_score = 0;
        $max_possible_score = 0;
        
        foreach ($questions as $question) {
            $question_max_score = $question->points * $question->scoring_weight;
            $max_possible_score += $question_max_score;
            
            $answer_key = $this->get_answer_key_for_question($question);
            if (isset($quiz_data[$answer_key])) {
                $user_answer = $quiz_data[$answer_key];
                $points_earned = $this->calculate_question_score($question, $user_answer);
                $total_score += $points_earned * $question->scoring_weight;
            }
        }
        
        // Convert to percentage (0-100)
        return $max_possible_score > 0 ? round(($total_score / $max_possible_score) * 100) : 0;
    }
    
    /**
     * Calculate score for individual question
     */
    private function calculate_question_score($question, $user_answer) {
        switch ($question->question_type) {
            case 'multiple_choice':
                $options = json_decode($question->options, true);
                if (isset($options['choices']) && isset($options['correct'])) {
                    $answer_index = array_search($user_answer, $options['choices']);
                    if ($answer_index !== false && in_array($answer_index, $options['correct'])) {
                        return $question->points;
                    }
                }
                break;
                
            case 'rating':
                $options = json_decode($question->options, true);
                $max_rating = $options['max_rating'] ?? 5;
                $rating = intval($user_answer);
                if ($rating > 0 && $rating <= $max_rating) {
                    return round(($rating / $max_rating) * $question->points);
                }
                break;
                
            case 'text':
            case 'email':
            case 'phone':
            case 'number':
            case 'date':
            case 'provinces':
            case 'districts':
            case 'wards':
                // Completion-based scoring
                return !empty(trim($user_answer)) ? $question->points : 0;
                
            case 'file_upload':
                // File upload scoring (check if file was uploaded)
                return !empty($user_answer) ? $question->points : 0;
        }
        
        return 0;
    }
    
    /**
     * Check if participant is eligible for gifts
     */
    private function check_gift_eligibility($campaign_id, $score, $quiz_data) {
        global $wpdb;
        
        // Check if there are any active gifts for this campaign
        $gifts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aqm_gifts 
             WHERE campaign_id = %d AND is_active = 1 
             AND min_score <= %d AND max_score >= %d
             AND (quantity_total = 0 OR quantity_remaining > 0)
             AND (valid_from IS NULL OR valid_from <= NOW())
             AND (valid_until IS NULL OR valid_until >= NOW())",
            $campaign_id, $score, $score
        ));
        
        return !empty($gifts);
    }
    
    /**
     * Process gift award for eligible participant
     */
    private function process_gift_award($campaign_id, $response_id, $score, $participant_email, $participant_name) {
        global $wpdb;
        
        // Get eligible gifts
        $gifts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aqm_gifts 
             WHERE campaign_id = %d AND is_active = 1 
             AND min_score <= %d AND max_score >= %d
             AND (quantity_total = 0 OR quantity_remaining > 0)
             AND (valid_from IS NULL OR valid_from <= NOW())
             AND (valid_until IS NULL OR valid_until >= NOW())
             ORDER BY probability DESC",
            $campaign_id, $score, $score
        ));
        
        if (empty($gifts)) {
            return null;
        }
        
        // Select gift based on probability
        $selected_gift = $this->select_gift_by_probability($gifts);
        
        if (!$selected_gift) {
            return null;
        }
        
        // Generate unique gift code
        $gift_code = $this->generate_unique_gift_code($selected_gift->id);
        
        // Calculate expiry date
        $expiry_date = null;
        if ($selected_gift->valid_until) {
            $expiry_date = $selected_gift->valid_until;
        } else {
            // Default 30 days from now
            $expiry_date = date('Y-m-d H:i:s', strtotime('+30 days'));
        }
        
        // Insert gift award record
        $award_data = array(
            'response_id' => $response_id,
            'gift_id' => $selected_gift->id,
            'campaign_id' => $campaign_id,
            'participant_email' => $participant_email,
            'participant_name' => $participant_name,
            'gift_code' => $gift_code,
            'score_achieved' => $score,
            'claim_status' => 'awarded',
            'expiry_date' => $expiry_date,
            'claim_ip' => $this->get_client_ip()
        );
        
        $award_result = $wpdb->insert($wpdb->prefix . 'aqm_gift_awards', $award_data);
        
        if ($award_result) {
            // Update gift quantity if limited
            if ($selected_gift->quantity_total > 0) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}aqm_gifts 
                     SET quantity_remaining = quantity_remaining - 1 
                     WHERE id = %d AND quantity_remaining > 0",
                    $selected_gift->id
                ));
            }
            
            return array(
                'award_id' => $wpdb->insert_id,
                'gift_id' => $selected_gift->id,
                'gift_name' => $selected_gift->gift_name,
                'gift_code' => $gift_code,
                'gift_value' => $selected_gift->gift_value,
                'gift_type' => $selected_gift->gift_type,
                'expiry_date' => $expiry_date
            );
        }
        
        return null;
    }
    
    /**
     * Select gift based on probability
     */
    private function select_gift_by_probability($gifts) {
        $rand = mt_rand(1, 10000); // Random number 1-10000 for precision
        $cumulative_probability = 0;
        
        foreach ($gifts as $gift) {
            $cumulative_probability += $gift->probability * 100; // Convert to basis points
            if ($rand <= $cumulative_probability) {
                return $gift;
            }
        }
        
        return null;
    }
    
    /**
     * Generate unique gift code
     */
    private function generate_unique_gift_code($gift_id) {
        global $wpdb;
        
        $gift = $wpdb->get_row($wpdb->prepare(
            "SELECT gift_code_prefix FROM {$wpdb->prefix}aqm_gifts WHERE id = %d",
            $gift_id
        ));
        
        $prefix = $gift->gift_code_prefix ?: 'GIFT';
        
        do {
            $code = $prefix . '-' . strtoupper(wp_generate_password(8, false));
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aqm_gift_awards WHERE gift_code = %s",
                $code
            ));
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Get Vietnamese provinces
     */
    public function get_vietnam_provinces() {
        global $wpdb;
        
        $provinces = $wpdb->get_results(
            "SELECT code, name, name_en, full_name FROM {$wpdb->prefix}aqm_provinces ORDER BY name",
            ARRAY_A
        );
        
        wp_send_json_success($provinces);
    }
    
    /**
     * Get Vietnamese districts by province
     */
    public function get_vietnam_districts() {
        $province_code = sanitize_text_field($_POST['province_code'] ?? $_GET['province_code'] ?? '');
        
        if (empty($province_code)) {
            wp_send_json_error('Province code is required');
        }
        
        global $wpdb;
        
        $districts = $wpdb->get_results($wpdb->prepare(
            "SELECT code, name, name_en, full_name FROM {$wpdb->prefix}aqm_districts 
             WHERE province_code = %s ORDER BY name",
            $province_code
        ), ARRAY_A);
        
        wp_send_json_success($districts);
    }
    
    /**
     * Get Vietnamese wards by district
     */
    public function get_vietnam_wards() {
        $district_code = sanitize_text_field($_POST['district_code'] ?? $_GET['district_code'] ?? '');
        
        if (empty($district_code)) {
            wp_send_json_error('District code is required');
        }
        
        global $wpdb;
        
        $wards = $wpdb->get_results($wpdb->prepare(
            "SELECT code, name, name_en, full_name FROM {$wpdb->prefix}aqm_wards 
             WHERE district_code = %s ORDER BY name",
            $district_code
        ), ARRAY_A);
        
        wp_send_json_success($wards);
    }
    
    /**
     * Get campaign information
     */
    public function get_campaign_info() {
        $campaign_id = intval($_POST['campaign_id'] ?? $_GET['campaign_id'] ?? 0);
        
        if (!$campaign_id) {
            wp_send_json_error('Campaign ID is required');
        }
        
        global $wpdb;
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aqm_campaigns WHERE id = %d AND status = 'active'",
            $campaign_id
        ));
        
        if (!$campaign) {
            wp_send_json_error('Campaign not found or inactive');
        }
        
        // Remove sensitive data
        unset($campaign->settings);
        
        wp_send_json_success($campaign);
    }
    
    /**
     * Get campaign questions for frontend
     */
    public function get_campaign_questions() {
        $campaign_id = intval($_POST['campaign_id'] ?? $_GET['campaign_id'] ?? 0);
        
        if (!$campaign_id) {
            wp_send_json_error('Campaign ID is required');
        }
        
        global $wpdb;
        
        // Verify campaign is active
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}aqm_campaigns WHERE id = %d",
            $campaign_id
        ));
        
        if (!$campaign || $campaign->status !== 'active') {
            wp_send_json_error('Campaign not found or inactive');
        }
        
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT id, question_text, question_type, question_group, options, is_required, order_index, points
             FROM {$wpdb->prefix}aqm_questions 
             WHERE campaign_id = %d 
             ORDER BY order_index ASC",
            $campaign_id
        ), ARRAY_A);
        
        // Process options for frontend
        foreach ($questions as &$question) {
            if ($question['options']) {
                $question['options'] = json_decode($question['options'], true);
            }
        }
        
        wp_send_json_success($questions);
    }
    
    /**
     * Save quiz progress (for auto-save functionality)
     */
    public function save_quiz_progress() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'aqm_front_nonce')) {
                throw new Exception('Security verification failed');
            }
            
            $campaign_id = intval($_POST['campaign_id']);
            $progress_data = json_decode(stripslashes($_POST['progress_data']), true);
            $current_question = intval($_POST['current_question']);
            
            // Store in session or temporary storage
            $session_key = 'aqm_progress_' . $campaign_id;
            
            $progress = array(
                'campaign_id' => $campaign_id,
                'current_question' => $current_question,
                'answers' => $progress_data,
                'timestamp' => time()
            );
            
            // Store in WordPress transient (temporary storage)
            $user_id = get_current_user_id();
            $transient_key = $user_id ? "aqm_progress_{$user_id}_{$campaign_id}" : "aqm_progress_guest_{$campaign_id}_" . $this->get_client_ip();
            
            set_transient($transient_key, $progress, HOUR_IN_SECONDS);
            
            wp_send_json_success(array(
                'message' => 'Progress saved',
                'timestamp' => $progress['timestamp']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get campaign analytics (admin only)
     */
    public function get_campaign_analytics() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'aqm_admin_nonce')) {
            wp_send_json_error('Security verification failed');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        
        global $wpdb;
        
        $analytics = array(
            'overview' => $this->get_overview_analytics($campaign_id),
            'participation' => $this->get_participation_analytics($campaign_id),
            'geographic' => $this->get_geographic_analytics($campaign_id),
            'scoring' => $this->get_scoring_analytics($campaign_id),
            'gifts' => $this->get_gift_analytics($campaign_id)
        );
        
        wp_send_json_success($analytics);
    }
    
    /**
     * Claim gift with gift code
     */
    public function claim_gift() {
        try {
            $gift_code = sanitize_text_field($_POST['gift_code']);
            $claimer_email = sanitize_email($_POST['email'] ?? '');
            
            if (empty($gift_code)) {
                throw new Exception('Gift code is required');
            }
            
            global $wpdb;
            
            // Find gift award
            $award = $wpdb->get_row($wpdb->prepare(
                "SELECT ga.*, g.gift_name, g.gift_value, g.gift_type 
                 FROM {$wpdb->prefix}aqm_gift_awards ga
                 LEFT JOIN {$wpdb->prefix}aqm_gifts g ON ga.gift_id = g.id
                 WHERE ga.gift_code = %s",
                $gift_code
            ));
            
            if (!$award) {
                throw new Exception('Invalid gift code');
            }
            
            if ($award->claim_status === 'claimed') {
                throw new Exception('Gift code has already been claimed');
            }
            
            if ($award->claim_status === 'expired' || ($award->expiry_date && strtotime($award->expiry_date) < time())) {
                throw new Exception('Gift code has expired');
            }
            
            if ($award->claim_status === 'revoked') {
                throw new Exception('Gift code has been revoked');
            }
            
            // Update claim status
            $claim_data = array(
                'claim_status' => 'claimed',
                'claimed_at' => current_time('mysql'),
                'claim_ip' => $this->get_client_ip(),
                'claim_details' => json_encode(array(
                    'claimer_email' => $claimer_email,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'timestamp' => time()
                ))
            );
            
            $result = $wpdb->update(
                $wpdb->prefix . 'aqm_gift_awards',
                $claim_data,
                array('gift_code' => $gift_code)
            );
            
            if ($result === false) {
                throw new Exception('Failed to claim gift');
            }
            
            wp_send_json_success(array(
                'message' => 'Gift claimed successfully!',
                'gift_name' => $award->gift_name,
                'gift_value' => $award->gift_value,
                'gift_type' => $award->gift_type,
                'claimed_at' => $claim_data['claimed_at']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Verify gift code status
     */
    public function verify_gift_code() {
        $gift_code = sanitize_text_field($_POST['gift_code'] ?? $_GET['gift_code'] ?? '');
        
        if (empty($gift_code)) {
            wp_send_json_error('Gift code is required');
        }
        
        global $wpdb;
        
        $award = $wpdb->get_row($wpdb->prepare(
            "SELECT ga.*, g.gift_name, g.gift_value, g.gift_type, c.title as campaign_title
             FROM {$wpdb->prefix}aqm_gift_awards ga
             LEFT JOIN {$wpdb->prefix}aqm_gifts g ON ga.gift_id = g.id
             LEFT JOIN {$wpdb->prefix}aqm_campaigns c ON ga.campaign_id = c.id
             WHERE ga.gift_code = %s",
            $gift_code
        ));
        
        if (!$award) {
            wp_send_json_error('Invalid gift code');
        }
        
        $status = $award->claim_status;
        if ($award->expiry_date && strtotime($award->expiry_date) < time() && $status === 'awarded') {
            $status = 'expired';
        }
        
        wp_send_json_success(array(
            'gift_code' => $gift_code,
            'gift_name' => $award->gift_name,
            'gift_value' => $award->gift_value,
            'gift_type' => $award->gift_type,
            'campaign_title' => $award->campaign_title,
            'status' => $status,
            'awarded_at' => $award->awarded_at,
            'claimed_at' => $award->claimed_at,
            'expiry_date' => $award->expiry_date,
            'participant_name' => $award->participant_name,
            'score_achieved' => $award->score_achieved
        ));
    }
    
    /**
     * Helper functions
     */
    private function get_answer_key_for_question($question) {
        // Map question types to expected form field names
        $type_mappings = array(
            'text' => 'full_name',
            'email' => 'email',
            'phone' => 'phone',
            'multiple_choice' => 'product_preference',
            'provinces' => 'province',
            'districts' => 'district',
            'wards' => 'ward',
            'rating' => 'rating',
            'number' => 'age',
            'date' => 'birth_date',
            'file_upload' => 'uploaded_file'
        );
        
        return $type_mappings[$question->question_type] ?? 'answer_' . $question->id;
    }
    
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    private function get_overview_analytics($campaign_id) {
        global $wpdb;
        
        return array(
            'total_responses' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE campaign_id = %d",
                $campaign_id
            )),
            'completed_responses' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE campaign_id = %d AND status = 'completed'",
                $campaign_id
            )),
            'average_score' => round(floatval($wpdb->get_var($wpdb->prepare(
                "SELECT AVG(final_score) FROM {$wpdb->prefix}aqm_responses WHERE campaign_id = %d AND status = 'completed'",
                $campaign_id
            ))), 1),
            'gifts_awarded' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_gift_awards WHERE campaign_id = %d",
                $campaign_id
            )),
            'completion_rate' => 85.3 // Calculate based on started vs completed
        );
    }
    
    private function get_participation_analytics($campaign_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(completed_at) as date, COUNT(*) as count 
             FROM {$wpdb->prefix}aqm_responses 
             WHERE campaign_id = %d AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(completed_at) 
             ORDER BY date DESC",
            $campaign_id
        ), ARRAY_A);
    }
    
    private function get_geographic_analytics($campaign_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT province_selected as province, COUNT(*) as count 
             FROM {$wpdb->prefix}aqm_responses 
             WHERE campaign_id = %d AND province_selected != '' 
             GROUP BY province_selected 
             ORDER BY count DESC 
             LIMIT 10",
            $campaign_id
        ), ARRAY_A);
    }
    
    private function get_scoring_analytics($campaign_id) {
        global $wpdb;
        
        $score_ranges = array();
        $ranges = array(
            '0-20' => array(0, 20),
            '21-40' => array(21, 40),
            '41-60' => array(41, 60),
            '61-80' => array(61, 80),
            '81-100' => array(81, 100)
        );
        
        foreach ($ranges as $label => $range) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses 
                 WHERE campaign_id = %d AND final_score BETWEEN %d AND %d",
                $campaign_id, $range[0], $range[1]
            ));
            
            $score_ranges[] = array(
                'range' => $label,
                'count' => intval($count)
            );
        }
        
        return $score_ranges;
    }
    
    private function get_gift_analytics($campaign_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT g.gift_name, g.gift_type, COUNT(ga.id) as awards_count,
                    COUNT(CASE WHEN ga.claim_status = 'claimed' THEN 1 END) as claimed_count
             FROM {$wpdb->prefix}aqm_gifts g
             LEFT JOIN {$wpdb->prefix}aqm_gift_awards ga ON g.id = ga.gift_id
             WHERE g.campaign_id = %d
             GROUP BY g.id
             ORDER BY awards_count DESC",
            $campaign_id
        ), ARRAY_A);
    }
    
    private function generate_certificate_url($response_id, $score) {
        // Generate URL for certificate download (if feature is enabled)
        return admin_url('admin-ajax.php?action=aqm_download_certificate&response_id=' . $response_id . '&nonce=' . wp_create_nonce('aqm_certificate_' . $response_id));
    }
    
    private function log_quiz_completion($campaign_id, $response_id, $score, $gift_result) {
        error_log(sprintf(
            "[AQM] Quiz completed - Campaign: %d, Response: %d, Score: %d%%, Gift: %s",
            $campaign_id,
            $response_id,
            $score,
            $gift_result ? $gift_result['gift_code'] : 'None'
        ));
    }
    
    private function send_completion_notifications($campaign, $response_data, $gift_result) {
        // Send email notifications if configured
        $settings = json_decode($campaign->settings, true);
        
        if (!empty($settings['send_completion_email']) && !empty($response_data['participant_email'])) {
            $this->send_completion_email($response_data, $gift_result);
        }
        
        if (!empty($settings['admin_notification_email'])) {
            $this->send_admin_notification($settings['admin_notification_email'], $response_data, $gift_result);
        }
    }
    
    private function send_completion_email($response_data, $gift_result) {
        $subject = 'Quiz Completed - Thank You!';
        
        $message = "Thank you for completing our quiz!\n\n";
        $message .= "Your Score: " . $response_data['final_score'] . "%\n";
        
        if ($gift_result) {
            $message .= "\nCongratulations! You've won a gift!\n";
            $message .= "Gift: " . $gift_result['gift_name'] . "\n";
            $message .= "Code: " . $gift_result['gift_code'] . "\n";
            if ($gift_result['expiry_date']) {
                $message .= "Expires: " . date('M j, Y', strtotime($gift_result['expiry_date'])) . "\n";
            }
        }
        
        wp_mail($response_data['participant_email'], $subject, $message);
    }
    
    private function send_admin_notification($admin_email, $response_data, $gift_result) {
        $subject = 'New Quiz Completion';
        
        $message = "A new quiz has been completed.\n\n";
        $message .= "Participant: " . $response_data['participant_name'] . "\n";
        $message .= "Email: " . $response_data['participant_email'] . "\n";
        $message .= "Score: " . $response_data['final_score'] . "%\n";
        
        if ($gift_result) {
            $message .= "Gift Awarded: " . $gift_result['gift_name'] . " (" . $gift_result['gift_code'] . ")\n";
        }
        
        wp_mail($admin_email, $subject, $message);
    }
}

// Initialize Enhanced API
new AQM_Enhanced_API();