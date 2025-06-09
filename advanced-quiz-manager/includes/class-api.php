<?php
/**
 * API Class for Advanced Quiz Manager - Complete AJAX Handler
 * File: includes/class-api.php
 */

class AQM_API {
    
    private $db;
    
    public function __construct() {
        $this->db = new AQM_Database();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Frontend AJAX handlers (logged in and non-logged in users)
        add_action('wp_ajax_aqm_check_phone', array($this, 'check_phone'));
        add_action('wp_ajax_nopriv_aqm_check_phone', array($this, 'check_phone'));
        
        add_action('wp_ajax_aqm_submit_quiz', array($this, 'submit_quiz'));
        add_action('wp_ajax_nopriv_aqm_submit_quiz', array($this, 'submit_quiz'));
        
        add_action('wp_ajax_aqm_get_districts', array($this, 'get_districts'));
        add_action('wp_ajax_nopriv_aqm_get_districts', array($this, 'get_districts'));
        
        add_action('wp_ajax_aqm_get_wards', array($this, 'get_wards'));
        add_action('wp_ajax_nopriv_aqm_get_wards', array($this, 'get_wards'));
        
        // Admin AJAX handlers (logged in users only)
        add_action('wp_ajax_aqm_save_campaign', array($this, 'save_campaign'));
        add_action('wp_ajax_aqm_delete_campaign', array($this, 'delete_campaign'));
        add_action('wp_ajax_aqm_duplicate_campaign', array($this, 'duplicate_campaign'));
        
        add_action('wp_ajax_aqm_save_question', array($this, 'save_question'));
        add_action('wp_ajax_aqm_delete_question', array($this, 'delete_question'));
        add_action('wp_ajax_aqm_reorder_questions', array($this, 'reorder_questions'));
        
        add_action('wp_ajax_aqm_save_gift', array($this, 'save_gift'));
        add_action('wp_ajax_aqm_delete_gift', array($this, 'delete_gift'));
        
        add_action('wp_ajax_aqm_export_responses', array($this, 'export_responses'));
        add_action('wp_ajax_aqm_import_provinces', array($this, 'import_provinces'));
        
        add_action('wp_ajax_aqm_get_analytics', array($this, 'get_analytics'));
        add_action('wp_ajax_aqm_get_dashboard_stats', array($this, 'get_dashboard_stats'));
    }
    
    // FRONTEND AJAX HANDLERS
    
    public function check_phone() {
        try {
            $this->verify_nonce('aqm_front_nonce');
            
            $phone = sanitize_text_field($_POST['phone']);
            $campaign_id = intval($_POST['campaign_id']);
            
            if (empty($phone) || empty($campaign_id)) {
                throw new Exception('Missing required parameters');
            }
            
            // Apply rate limiting
            $this->check_rate_limit('phone_check', $phone);
            
            $exists = $this->db->check_phone_participation($phone, $campaign_id);
            
            if ($exists) {
                wp_send_json_error(array(
                    'message' => 'Phone number already participated',
                    'code' => 'phone_exists'
                ));
            } else {
                wp_send_json_success(array('message' => 'Phone available'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function submit_quiz() {
        try {
            $this->verify_nonce('aqm_front_nonce');
            
            $campaign_id = intval($_POST['campaign_id']);
            $user_data = array(
                'full_name' => sanitize_text_field($_POST['full_name']),
                'phone_number' => sanitize_text_field($_POST['phone_number']),
                'province' => sanitize_text_field($_POST['province']),
                'pharmacy_code' => sanitize_text_field($_POST['pharmacy_code'])
            );
            
            $answers = json_decode(stripslashes($_POST['answers']), true);
            
            // Validate required fields
            if (empty($user_data['full_name']) || empty($user_data['phone_number']) || empty($user_data['province'])) {
                throw new Exception('Required fields missing');
            }
            
            // Validate phone format
            if (!preg_match('/^[0-9]{10,11}$/', $user_data['phone_number'])) {
                throw new Exception('Invalid phone number format');
            }
            
            // Apply rate limiting
            $this->check_rate_limit('quiz_submit', $user_data['phone_number']);
            
            // Check if already participated
            if ($this->db->check_phone_participation($user_data['phone_number'], $campaign_id)) {
                wp_send_json_error(array(
                    'message' => 'Phone already participated',
                    'code' => 'phone_exists'
                ));
                return;
            }
            
            // Get campaign and questions
            $campaign = $this->db->get_campaign($campaign_id);
            if (!$campaign || $campaign->status !== 'active') {
                throw new Exception('Campaign not available');
            }
            
            $questions = $this->db->get_campaign_questions($campaign_id);
            if (empty($questions)) {
                throw new Exception('No questions found for this campaign');
            }
            
            // Calculate score
            $score = $this->calculate_quiz_score($answers, $questions);
            $total_questions = count($questions);
            $percentage = ($score / $total_questions) * 100;
            
            // Get eligible gift
            $gift = $this->get_eligible_gift($campaign_id, $percentage);
            
            // Save response
            $response_id = $this->db->save_quiz_response(
                $campaign_id, 
                $user_data, 
                $answers, 
                $score, 
                $gift
            );
            
            if (!$response_id) {
                throw new Exception('Failed to save quiz response');
            }
            
            // Log successful submission
            $this->log_activity('quiz_submitted', array(
                'campaign_id' => $campaign_id,
                'response_id' => $response_id,
                'phone' => $user_data['phone_number'],
                'score' => $score,
                'gift_awarded' => !empty($gift)
            ));
            
            wp_send_json_success(array(
                'score' => $score,
                'total' => $total_questions,
                'percentage' => round($percentage, 1),
                'gift' => $gift,
                'message' => $this->get_score_message($percentage),
                'response_id' => $response_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function get_districts() {
        try {
            $this->verify_nonce('aqm_front_nonce');
            
            $province_code = sanitize_text_field($_POST['province_code']);
            if (empty($province_code)) {
                throw new Exception('Province code required');
            }
            
            $districts = $this->db->get_districts_by_province($province_code);
            wp_send_json_success($districts);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function get_wards() {
        try {
            $this->verify_nonce('aqm_front_nonce');
            
            $district_code = sanitize_text_field($_POST['district_code']);
            if (empty($district_code)) {
                throw new Exception('District code required');
            }
            
            $wards = $this->db->get_wards_by_district($district_code);
            wp_send_json_success($wards);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    // ADMIN AJAX HANDLERS
    
    public function save_campaign() {
        try {
            $this->verify_nonce('aqm_admin_nonce');
            $this->check_admin_permissions();
            
            $campaign_id = intval($_POST['campaign_id']);
            $data = array(
                'title' => sanitize_text_field($_POST['title']),
                'description' => sanitize_textarea_field($_POST['description']),
                'status' => sanitize_text_field($_POST['status']),
                'max_participants' => intval($_POST['max_participants']),
                'start_date' => !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null,
                'end_date' => !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null,
                'settings' => json_encode($_POST['settings'] ?? array())
            );
            
            // Validate required fields
            if (empty($data['title'])) {
                throw new Exception('Campaign title is required');
            }
            
            if ($campaign_id) {
                $result = $this->db->update_campaign($campaign_id, $data);
                $message = 'Campaign updated successfully!';
            } else {
                $data['created_by'] = get_current_user_id();
                $campaign_id = $this->db->create_campaign($data);
                $result = $campaign_id !== false;
                $message = 'Campaign created successfully!';
            }
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => $message,
                    'campaign_id' => $campaign_id,
                    'redirect_url' => admin_url('admin.php?page=quiz-manager-campaigns')
                ));
            } else {
                throw new Exception('Failed to save campaign');
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function save_question() {
        try {
            $this->verify_nonce('aqm_admin_nonce');
            $this->check_admin_permissions();
            
            $question_id = intval($_POST['question_id']);
            $campaign_id = intval($_POST['campaign_id']);
            
            // Validate campaign exists
            $campaign = $this->db->get_campaign($campaign_id);
            if (!$campaign) {
                throw new Exception('Campaign not found');
            }
            
            // Process options
            $options = array();
            if (!empty($_POST['option_text']) && is_array($_POST['option_text'])) {
                foreach ($_POST['option_text'] as $index => $text) {
                    if (!empty(trim($text))) {
                        $options[] = array(
                            'id' => chr(97 + $index), // a, b, c, d...
                            'text' => sanitize_text_field($text),
                            'correct' => isset($_POST['option_correct']) && in_array($index, $_POST['option_correct'])
                        );
                    }
                }
            }
            
            $data = array(
                'question_text' => sanitize_textarea_field($_POST['question_text']),
                'question_type' => sanitize_text_field($_POST['question_type']),
                'options' => json_encode($options),
                'points' => intval($_POST['points']) ?: 1,
                'order_index' => intval($_POST['order_index']) ?: 0
            );
            
            // Validate required fields
            if (empty($data['question_text'])) {
                throw new Exception('Question text is required');
            }
            
            if (empty($options)) {
                throw new Exception('At least one answer option is required');
            }
            
            // Check if at least one correct answer exists
            $has_correct = false;
            foreach ($options as $option) {
                if ($option['correct']) {
                    $has_correct = true;
                    break;
                }
            }
            
            if (!$has_correct) {
                throw new Exception('At least one correct answer must be selected');
            }
            
            if ($question_id) {
                $result = $this->db->update_question($question_id, $data);
                $message = 'Question updated successfully!';
            } else {
                $question_id = $this->db->create_question($campaign_id, $data);
                $result = $question_id !== false;
                $message = 'Question created successfully!';
            }
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => $message,
                    'question_id' => $question_id
                ));
            } else {
                throw new Exception('Failed to save question');
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function delete_question() {
        try {
            $this->verify_nonce('aqm_admin_nonce');
            $this->check_admin_permissions();
            
            $question_id = intval($_POST['question_id']);
            if (!$question_id) {
                throw new Exception('Question ID required');
            }
            
            $result = $this->db->delete_question($question_id);
            
            if ($result) {
                wp_send_json_success(array('message' => 'Question deleted successfully!'));
            } else {
                throw new Exception('Failed to delete question');
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function save_gift() {
        try {
            $this->verify_nonce('aqm_admin_nonce');
            $this->check_admin_permissions();
            
            $gift_id = intval($_POST['gift_id']);
            $campaign_id = intval($_POST['campaign_id']);
            
            // Validate campaign exists
            $campaign = $this->db->get_campaign($campaign_id);
            if (!$campaign) {
                throw new Exception('Campaign not found');
            }
            
            $requirements = array(
                'min_score_percentage' => floatval($_POST['min_score_percentage']) ?: 0
            );
            
            $data = array(
                'title' => sanitize_text_field($_POST['title']),
                'description' => sanitize_textarea_field($_POST['description']),
                'gift_type' => sanitize_text_field($_POST['gift_type']),
                'gift_value' => floatval($_POST['gift_value']) ?: 0,
                'code_prefix' => sanitize_text_field($_POST['code_prefix']) ?: 'GIFT',
                'quantity' => intval($_POST['quantity']) ?: 1,
                'requirements' => json_encode($requirements)
            );
            
            // Validate required fields
            if (empty($data['title'])) {
                throw new Exception('Gift title is required');
            }
            
            if ($gift_id) {
                $result = $this->db->update_gift($gift_id, $data);
                $message = 'Gift updated successfully!';
            } else {
                $gift_id = $this->db->create_gift($campaign_id, $data);
                $result = $gift_id !== false;
                $message = 'Gift created successfully!';
            }
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => $message,
                    'gift_id' => $gift_id
                ));
            } else {
                throw new Exception('Failed to save gift');
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function export_responses() {
        try {
            $this->verify_nonce('aqm_admin_nonce');
            $this->check_admin_permissions();
            
            $campaign_id = intval($_POST['campaign_id']);
            $campaign = $this->db->get_campaign($campaign_id);
            
            if (!$campaign) {
                throw new Exception('Campaign not found');
            }
            
            $responses = $this->db->get_campaign_responses($campaign_id, 0); // Get all responses
            
            // Prepare CSV data
            $csv_data = array();
            $csv_data[] = array(
                'ID',
                'Full Name',
                'Phone Number',
                'Province',
                'Pharmacy Code',
                'Score',
                'Gift Code',
                'Submitted At',
                'IP Address'
            );
            
            foreach ($responses as $response) {
                $csv_data[] = array(
                    $response->id,
                    $response->full_name,
                    $response->phone_number,
                    $response->province,
                    $response->pharmacy_code,
                    $response->score,
                    $response->gift_code ?: '',
                    $response->submitted_at,
                    $response->ip_address
                );
            }
            
            // Generate filename
            $filename = sanitize_title($campaign->title) . '-responses-' . date('Y-m-d') . '.csv';
            
            // Set headers for download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Output CSV
            $output = fopen('php://output', 'w');
            foreach ($csv_data as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            wp_die('Export failed: ' . $e->getMessage());
        }
    }
    
    public function get_dashboard_stats() {
        try {
            $this->verify_nonce('aqm_admin_nonce');
            $this->check_admin_permissions();
            
            $stats = $this->db->get_dashboard_stats();
            wp_send_json_success($stats);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    // HELPER METHODS
    
    private function verify_nonce($action) {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', $action)) {
            throw new Exception('Security verification failed');
        }
    }
    
    private function check_admin_permissions() {
        if (!current_user_can('manage_options')) {
            throw new Exception('Insufficient permissions');
        }
    }
    
    private function check_rate_limit($action, $identifier) {
        $rate_limit = get_option('aqm_rate_limit_attempts', 3);
        $window = get_option('aqm_rate_limit_window', 3600);
        
        $key = 'aqm_rate_limit_' . $action . '_' . md5($identifier . $this->get_client_ip());
        $attempts = get_transient($key) ?: 0;
        
        if ($attempts >= $rate_limit) {
            throw new Exception('Rate limit exceeded. Please try again later.');
        }
        
        set_transient($key, $attempts + 1, $window);
    }
    
    private function calculate_quiz_score($answers, $questions) {
        $score = 0;
        
        foreach ($questions as $question) {
            $question_id = $question->id;
            $options = json_decode($question->options, true) ?: array();
            
            if (!isset($answers[$question_id])) {
                continue;
            }
            
            $user_answers = (array) $answers[$question_id];
            $correct_options = array();
            
            foreach ($options as $option) {
                if (!empty($option['correct'])) {
                    $correct_options[] = $option['id'];
                }
            }
            
            if (!empty($correct_options)) {
                $correct_count = count(array_intersect($user_answers, $correct_options));
                $incorrect_count = count(array_diff($user_answers, $correct_options));
                
                // Award points only if all correct answers selected and no incorrect ones
                if ($correct_count === count($correct_options) && $incorrect_count === 0) {
                    $score += $question->points ?: 1;
                }
            }
        }
        
        return $score;
    }
    
    private function get_eligible_gift($campaign_id, $score_percentage) {
        $gifts = $this->db->get_campaign_gifts($campaign_id);
        
        foreach ($gifts as $gift) {
            $requirements = json_decode($gift->requirements, true) ?: array();
            $min_percentage = $requirements['min_score_percentage'] ?? 0;
            
            if ($score_percentage >= $min_percentage && $gift->quantity > 0) {
                // Decrease quantity
                $this->db->decrease_gift_quantity($gift->id);
                
                return array(
                    'id' => $gift->id,
                    'title' => $gift->title,
                    'description' => $gift->description,
                    'code' => $this->generate_gift_code($gift),
                    'type' => $gift->gift_type,
                    'value' => $gift->gift_value
                );
            }
        }
        
        return null;
    }
    
    private function generate_gift_code($gift) {
        $prefix = $gift->code_prefix ?: 'GIFT';
        $random = strtoupper(wp_generate_password(6, false));
        return $prefix . $random;
    }
    
    private function get_score_message($percentage) {
        if ($percentage >= 90) {
            return 'Perfect! You really know your stuff!';
        } elseif ($percentage >= 80) {
            return 'Excellent! You have great knowledge!';
        } elseif ($percentage >= 70) {
            return 'Great job! Well done!';
        } elseif ($percentage >= 60) {
            return 'Good work! Keep learning!';
        } else {
            return 'Good effort! There\'s always room to improve!';
        }
    }
    
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    private function log_activity($action, $data = array()) {
        // Simple activity logging - can be expanded
        $log_data = array(
            'action' => $action,
            'data' => $data,
            'ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => time()
        );
        
        update_option('aqm_last_activity_' . $action, $log_data);
        
        // Keep only last 100 activities per type
        $activities = get_option('aqm_activities', array());
        $activities[$action][] = $log_data;
        
        if (count($activities[$action]) > 100) {
            $activities[$action] = array_slice($activities[$action], -100);
        }
        
        update_option('aqm_activities', $activities);
    }
}
?>