<?php
/**
 * Complete API Handler for Advanced Quiz Manager
 * File: includes/class-api.php
 */

class AQM_API {
    
    private $db;
    
    public function __construct() {
        $this->db = new AQM_Database();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Frontend AJAX (logged in and not logged in users)
        add_action('wp_ajax_aqm_submit_quiz', array($this, 'submit_quiz'));
        add_action('wp_ajax_nopriv_aqm_submit_quiz', array($this, 'submit_quiz'));
        
        add_action('wp_ajax_aqm_get_provinces', array($this, 'get_provinces'));
        add_action('wp_ajax_nopriv_aqm_get_provinces', array($this, 'get_provinces'));
        
        add_action('wp_ajax_aqm_get_districts', array($this, 'get_districts'));
        add_action('wp_ajax_nopriv_aqm_get_districts', array($this, 'get_districts'));
        
        add_action('wp_ajax_aqm_get_wards', array($this, 'get_wards'));
        add_action('wp_ajax_nopriv_aqm_get_wards', array($this, 'get_wards'));
        
        add_action('wp_ajax_aqm_track_event', array($this, 'track_event'));
        add_action('wp_ajax_nopriv_aqm_track_event', array($this, 'track_event'));
        
        add_action('wp_ajax_aqm_check_gift_eligibility', array($this, 'check_gift_eligibility'));
        add_action('wp_ajax_nopriv_aqm_check_gift_eligibility', array($this, 'check_gift_eligibility'));
        
        add_action('wp_ajax_aqm_claim_gift', array($this, 'claim_gift'));
        add_action('wp_ajax_nopriv_aqm_claim_gift', array($this, 'claim_gift'));
        
        add_action('wp_ajax_aqm_save_progress', array($this, 'save_progress'));
        add_action('wp_ajax_nopriv_aqm_save_progress', array($this, 'save_progress'));
        
        // Admin AJAX (logged in users only)
        add_action('wp_ajax_aqm_save_campaign', array($this, 'save_campaign'));
        add_action('wp_ajax_aqm_delete_campaign', array($this, 'delete_campaign'));
        add_action('wp_ajax_aqm_update_campaign_status', array($this, 'update_campaign_status'));
        add_action('wp_ajax_aqm_auto_save_campaign', array($this, 'auto_save_campaign'));
        
        add_action('wp_ajax_aqm_save_question', array($this, 'save_question'));
        add_action('wp_ajax_aqm_delete_question', array($this, 'delete_question'));
        add_action('wp_ajax_aqm_reorder_questions', array($this, 'reorder_questions'));
        
        add_action('wp_ajax_aqm_save_gift', array($this, 'save_gift'));
        add_action('wp_ajax_aqm_delete_gift', array($this, 'delete_gift'));
        
        add_action('wp_ajax_aqm_get_analytics_data', array($this, 'get_analytics_data'));
        add_action('wp_ajax_aqm_export_responses', array($this, 'export_responses'));
        add_action('wp_ajax_aqm_refresh_dashboard_stats', array($this, 'refresh_dashboard_stats'));
        
        add_action('wp_ajax_aqm_import_provinces_json', array($this, 'import_provinces_json'));
        add_action('wp_ajax_aqm_upload_file', array($this, 'upload_file'));
        
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
		
		    // ADD THESE NEW HOOKS:
		add_action('wp_ajax_aqm_get_vietnam_provinces', array($this, 'get_vietnam_provinces'));
		add_action('wp_ajax_nopriv_aqm_get_vietnam_provinces', array($this, 'get_vietnam_provinces'));
		
		add_action('wp_ajax_aqm_get_vietnam_districts', array($this, 'get_vietnam_districts'));
		add_action('wp_ajax_nopriv_aqm_get_vietnam_districts', array($this, 'get_vietnam_districts'));
		
		add_action('wp_ajax_aqm_submit_quiz_enhanced', array($this, 'submit_quiz_enhanced'));
		add_action('wp_ajax_nopriv_aqm_submit_quiz_enhanced', array($this, 'submit_quiz_enhanced'));
    }
    
    // FRONTEND AJAX HANDLERS
    
    public function submit_quiz() {
        try {
            $this->verify_nonce('aqm_front_nonce');
            
            $campaign_id = intval($_POST['campaign_id']);
            $answers = json_decode(stripslashes($_POST['answers']), true);
            $start_time = isset($_POST['start_time']) ? intval($_POST['start_time']) : time();
            
            // Validate campaign
            $campaign = $this->db->get_campaign($campaign_id);
            if (!$campaign) {
                throw new Exception('Campaign not found');
            }
            
            // Check if campaign is active
            if ($campaign->status !== 'active') {
                throw new Exception('Campaign is not active');
            }
            
            // Check date limits
            if ($campaign->start_date && strtotime($campaign->start_date) > time()) {
                throw new Exception('Campaign has not started yet');
            }
            
            if ($campaign->end_date && strtotime($campaign->end_date) < time()) {
                throw new Exception('Campaign has ended');
            }
            
            // Check participant limit
            if ($campaign->max_participants > 0) {
                $current_participants = $this->db->get_campaign_stats($campaign_id)['total_participants'];
                if ($current_participants >= $campaign->max_participants) {
                    throw new Exception('Campaign has reached maximum participants');
                }
            }
            
            // Get campaign questions
            $questions = $this->db->get_campaign_questions($campaign_id);
            $questions_map = array();
            foreach ($questions as $question) {
                $questions_map[$question->id] = $question;
            }
            
            // Validate required questions
            foreach ($questions as $question) {
                if ($question->is_required && (
                    !isset($answers[$question->id]) || 
                    empty($answers[$question->id])
                )) {
                    throw new Exception('Required question not answered: ' . $question->question_text);
                }
            }
            
            // Calculate completion time
            $completion_time = time() - $start_time;
            
            // Extract user information
            $user_email = '';
            $user_name = '';
            $user_phone = '';
            
            foreach ($answers as $question_id => $answer) {
                $question = $questions_map[$question_id] ?? null;
                if ($question) {
                    if ($question->question_type === 'email') {
                        $user_email = sanitize_email($answer);
                    } elseif ($question->question_type === 'phone') {
                        $user_phone = sanitize_text_field($answer);
                    } elseif (strpos(strtolower($question->question_text), 'name') !== false) {
                        $user_name = sanitize_text_field($answer);
                    }
                }
            }
            
            // Calculate total score
            $total_score = 0;
            $processed_answers = array();
            
            foreach ($answers as $question_id => $answer) {
                $question = $questions_map[$question_id] ?? null;
                if (!$question) continue;
                
                $answer_score = 0;
                $processed_answer = $this->process_answer($question, $answer);
                
                // Calculate score based on question type
                if ($question->question_type === 'rating') {
                    $answer_score = intval($answer) * ($question->points ?: 1);
                } elseif ($question->question_type === 'multiple_choice') {
                    $answer_score = $question->points ?: 0;
                } else {
                    $answer_score = !empty($answer) ? ($question->points ?: 0) : 0;
                }
                
                $total_score += $answer_score;
                $processed_answers[$question_id] = array(
                    'value' => $processed_answer,
                    'score' => $answer_score
                );
            }
            
            // Create response record
            $response_data = array(
                'campaign_id' => $campaign_id,
                'user_id' => get_current_user_id() ?: null,
                'user_email' => $user_email,
                'user_name' => $user_name,
                'user_phone' => $user_phone,
                'total_score' => $total_score,
                'completion_time' => $completion_time,
                'ip_address' => $this->get_user_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'status' => 'completed'
            );
            
            $response_id = $this->db->create_response($response_data);
            
            if (!$response_id) {
                throw new Exception('Failed to save response');
            }
            
            // Save individual answers
            foreach ($processed_answers as $question_id => $answer_data) {
                $this->db->save_answer(
                    $response_id, 
                    $question_id, 
                    $answer_data['value'], 
                    $answer_data['score']
                );
            }
            
            // Check for gift eligibility
            $awarded_gift = null;
            $eligible_gift = $this->db->check_gift_eligibility($campaign_id, $total_score);
            
            if ($eligible_gift) {
                $claim_code = $this->db->award_gift($response_id, $eligible_gift->id);
                if ($claim_code) {
                    $awarded_gift = array(
                        'id' => $eligible_gift->id,
                        'name' => $eligible_gift->name,
                        'description' => $eligible_gift->description,
                        'image_url' => $eligible_gift->image_url,
                        'claim_code' => $claim_code
                    );
                }
            }
            
            // Log analytics
            $this->db->log_analytics($campaign_id, 'quiz_completed', array(
                'response_id' => $response_id,
                'total_score' => $total_score,
                'completion_time' => $completion_time,
                'gift_awarded' => $awarded_gift ? $eligible_gift->id : null
            ));
            
            // Prepare response
            $response_data = array(
                'message' => 'Quiz submitted successfully!',
                'response_id' => $response_id,
                'score' => $total_score,
                'completion_time' => $completion_time
            );
            
            if ($awarded_gift) {
                $response_data['gift'] = $awarded_gift;
            }
            
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function get_provinces() {
        try {
            $search = sanitize_text_field($_GET['search'] ?? '');
            $provinces = $this->db->get_provinces(array('search' => $search));
            wp_send_json_success($provinces);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function get_districts() {
        try {
            $this->verify_nonce('aqm_nonce');
            $province_code = sanitize_text_field($_POST['province_code']);
            $districts = $this->db->get_districts($province_code);
            wp_send_json_success($districts);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function get_wards() {
        try {
            $this->verify_nonce('aqm_nonce');
            $district_code = sanitize_text_field($_POST['district_code']);
            $wards = $this->db->get_wards($district_code);
            wp_send_json_success($wards);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function track_event() {
        try {
            $this->verify_nonce('aqm_front_nonce');
            
            $event_type = sanitize_text_field($_POST['event_type']);
            $event_data = json_decode(stripslashes($_POST['event_data']), true);
            $campaign_id = isset($event_data['campaign_id']) ? intval($event_data['campaign_id']) : null;
            
            $this->db->log_analytics($campaign_id, $event_type, $event_data);
            
            wp_send_json_success();
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function check_gift_eligibility() {
        try {
            $this->verify_nonce('aqm_front_nonce');
            
            $campaign_id = intval($_POST['campaign_id']);
            $score = intval($_POST['score']);
            
            $eligible_gift = $this->db->check_gift_eligibility($campaign_id, $score);
            
            if ($eligible_gift) {
                wp_send_json_success(array(
                    'gift' => array(
                        'id' => $eligible_gift->id,
                        'name' => $eligible_gift->name,
                        'description' => $eligible_gift->description,
                        'image_url' => $eligible_gift->image_url
                    )
                ));
            } else {
                wp_send_json_success(array('gift' => null));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function claim_gift() {
        try {
            $this->verify_nonce('aqm_front_nonce');
            
            $gift_id = intval($_POST['gift_id']);
            $claim_code = sanitize_text_field($_POST['claim_code'] ?? '');
            
            if ($claim_code) {
                $result = $this->db->claim_gift($claim_code);
                if ($result) {
                    wp_send_json_success(array('message' => 'Gift claimed successfully!'));
                } else {
                    throw new Exception('Invalid claim code or gift already claimed');
                }
            } else {
                throw new Exception('Claim code is required');
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function save_progress() {
        try {
            $this->verify_nonce('aqm_front_nonce');
            
            $campaign_id = intval($_POST['campaign_id']);
            $answers = json_decode(stripslashes($_POST['answers']), true);
            
            // Save progress to session or temporary storage
            $session_key = 'aqm_progress_' . $campaign_id;
            $_SESSION[$session_key] = array(
                'answers' => $answers,
                'saved_at' => time()
            );
            
            wp_send_json_success(array('message' => 'Progress saved'));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    // ADMIN AJAX HANDLERS
    
    public function save_campaign() {
        try {
            $this->verify_nonce('aqm_save_campaign');
            $this->check_admin_permissions();
            
            $campaign_id = intval($_POST['campaign_id']);
            $title = sanitize_text_field($_POST['title']);
            $description = sanitize_textarea_field($_POST['description']);
            $status = sanitize_text_field($_POST['status']);
            $start_date = sanitize_text_field($_POST['start_date']);
            $end_date = sanitize_text_field($_POST['end_date']);
            $max_participants = intval($_POST['max_participants']);
            
            // Process settings
            $settings = array();
            if (isset($_POST['settings']) && is_array($_POST['settings'])) {
                foreach ($_POST['settings'] as $key => $value) {
                    $settings[sanitize_key($key)] = sanitize_text_field($value);
                }
            }
            
            $data = array(
                'title' => $title,
                'description' => $description,
                'status' => $status,
                'start_date' => $start_date ? date('Y-m-d H:i:s', strtotime($start_date)) : null,
                'end_date' => $end_date ? date('Y-m-d H:i:s', strtotime($end_date)) : null,
                'max_participants' => $max_participants,
                'settings' => json_encode($settings),
                'created_by' => get_current_user_id()
            );
            
            if ($campaign_id) {
                $result = $this->db->update_campaign($campaign_id, $data);
                $message = 'Campaign updated successfully!';
            } else {
                $campaign_id = $this->db->create_campaign($data);
                $result = $campaign_id !== false;
                $message = 'Campaign created successfully!';
            }
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => $message,
                    'campaign_id' => $campaign_id
                ));
            } else {
                throw new Exception('Failed to save campaign');
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function delete_campaign() {
        try {
            $this->verify_nonce('aqm_nonce');
            $this->check_admin_permissions();
            
            $campaign_id = intval($_POST['campaign_id']);
            
            $result = $this->db->delete_campaign($campaign_id);
            
            if ($result !== false) {
                wp_send_json_success(array('message' => 'Campaign deleted successfully'));
            } else {
                throw new Exception('Failed to delete campaign');
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function update_campaign_status() {
        try {
            $this->verify_nonce('aqm_nonce');
            $this->check_admin_permissions();
            
            $campaign_id = intval($_POST['campaign_id']);
            $status = sanitize_text_field($_POST['status']);
            
            $result = $this->db->update_campaign($campaign_id, array('status' => $status));
            
            if ($result !== false) {
                wp_send_json_success(array('message' => 'Status updated successfully'));
            } else {
                throw new Exception('Failed to update status');
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function auto_save_campaign() {
        try {
            $this->verify_nonce('aqm_nonce');
            $this->check_admin_permissions();
            
            $campaign_id = intval($_POST['campaign_id']);
            
            if (!$campaign_id) {
                wp_send_json_error(array('message' => 'No campaign ID provided'));
                return;
            }
            
            // Only save basic fields for auto-save
            $data = array(
                'title' => sanitize_text_field($_POST['title'] ?? ''),
                'description' => sanitize_textarea_field($_POST['description'] ?? '')
            );
            
            $result = $this->db->update_campaign($campaign_id, $data);
            
            if ($result !== false) {
                wp_send_json_success();
            } else {
                wp_send_json_error();
            }
        } catch (Exception $e) {
            wp_send_json_error();
        }
    }
    
    public function save_question() {
        try {
            $this->verify_nonce('aqm_nonce');
            $this->check_admin_permissions();
            
            $question_id = intval($_POST['question_id']);
            $campaign_id = intval($_POST['campaign_id']);
            $question_text = sanitize_textarea_field($_POST['question_text']);
            $question_type = sanitize_text_field($_POST['question_type']);
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            $points = intval($_POST['points']);
            $order_index = intval($_POST['order_index']);
            
            // Process options based on question type
            $options = array();
            if ($question_type === 'multiple_choice') {
                if (isset($_POST['option_text']) && is_array($_POST['option_text'])) {
                    $options['choices'] = array();
                    for ($i = 0; $i < count($_POST['option_text']); $i++) {
                        if (!empty($_POST['option_text'][$i])) {
                            $options['choices'][] = array(
                                'label' => sanitize_text_field($_POST['option_text'][$i]),
                                'value' => sanitize_text_field($_POST['option_value'][$i] ?? $_POST['option_text'][$i])
                            );
                        }
                    }
                }
            } elseif ($question_type === 'rating') {
                $options['max_rating'] = intval($_POST['max_rating'] ?? 5);
                $options['icon'] = sanitize_text_field($_POST['rating_icon'] ?? 'star');
            } elseif ($question_type === 'provinces') {
                $options['load_districts'] = isset($_POST['load_districts']) ? 1 : 0;
                $options['load_wards'] = isset($_POST['load_wards']) ? 1 : 0;
                $options['placeholder'] = sanitize_text_field($_POST['placeholder'] ?? 'Select your province');
            }
            
            $data = array(
                'campaign_id' => $campaign_id,
                'question_text' => $question_text,
                'question_type' => $question_type,
                'options' => json_encode($options),
                'is_required' => $is_required,
                'validation_rules' => json_encode(array()),
                'order_index' => $order_index,
                'points' => $points
            );
            
            if ($question_id) {
                $result = $this->db->update_question($question_id, $data);
                $message = 'Question updated successfully!';
            } else {
                $question_id = $this->db->create_question($data);
                $result = $question_id !== false;
                $message = 'Question created successfully!';
            }
            
            if ($result !== false) {
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
            $this->verify_nonce('aqm_nonce');
            $this->check_admin_permissions();
            
            $question_id = intval($_POST['question_id']);
            
            $result = $this->db->delete_question($question_id);
            
            if ($result !== false) {
                wp_send_json_success(array('message' => 'Question deleted successfully'));
            } else {
                throw new Exception('Failed to delete question');
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function reorder_questions() {
        try {
            $this->verify_nonce('aqm_nonce');
            $this->check_admin_permissions();
            
            $question_orders = $_POST['question_orders'] ?? array();
            
            if (empty($question_orders)) {
                throw new Exception('No question order data provided');
            }
            
            $result = $this->db->reorder_questions($question_orders);
            
            if ($result) {
                wp_send_json_success(array('message' => 'Questions reordered successfully'));
            } else {
                throw new Exception('Failed to reorder questions');
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function get_analytics_data() {
        try {
            $this->verify_nonce('aqm_nonce');
            $this->check_admin_permissions();
            
            $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null;
            $date_from = sanitize_text_field($_POST['date_from'] ?? '');
            $date_to = sanitize_text_field($_POST['date_to'] ?? '');
            
            $analytics_data = array();
            
            if ($campaign_id) {
                $analytics_data['campaign_stats'] = $this->db->get_campaign_stats($campaign_id);
                $analytics_data['response_distribution'] = $this->db->get_response_distribution($campaign_id);
                $analytics_data['province_distribution'] = $this->db->get_province_distribution($campaign_id);
            } else {
                // Global analytics
                $analytics_data['overview'] = $this->get_overview_analytics();
            }
            
            wp_send_json_success($analytics_data);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function export_responses() {
        try {
            $this->verify_nonce('aqm_nonce');
            $this->check_admin_permissions();
            
            $campaign_id = intval($_POST['campaign_id']);
            $format = sanitize_text_field($_POST['format'] ?? 'csv');
            
            if ($format === 'csv') {
                $csv_data = $this->db->export_responses_csv($campaign_id);
                
                if (!$csv_data) {
                    throw new Exception('No data to export');
                }
                
                $campaign = $this->db->get_campaign($campaign_id);
                $filename = 'quiz-responses-' . sanitize_title($campaign->title) . '-' . date('Y-m-d') . '.csv';
                
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                
                foreach ($csv_data as $row) {
                    fputcsv($output, $row);
                }
                
                fclose($output);
                exit;
            }
        } catch (Exception $e) {
            wp_die('Export failed: ' . $e->getMessage());
        }
    }
    
    public function refresh_dashboard_stats() {
        try {
            $this->verify_nonce('aqm_nonce');
            $this->check_admin_permissions();
            
            $stats = $this->get_overview_analytics();
            wp_send_json_success($stats);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function import_provinces_json() {
        try {
            $this->verify_nonce('aqm_import_provinces');
            $this->check_admin_permissions();
            
            $json_data = '';
            
            // Check if file was uploaded
            if (isset($_FILES['provinces_file']) && $_FILES['provinces_file']['error'] === UPLOAD_ERR_OK) {
                $json_data = file_get_contents($_FILES['provinces_file']['tmp_name']);
            } elseif (!empty($_POST['provinces_json'])) {
                $json_data = stripslashes($_POST['provinces_json']);
            }
            
            if (empty($json_data)) {
                throw new Exception('No JSON data provided');
            }
            
            // Validate JSON
            $provinces_data = json_decode($json_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON format: ' . json_last_error_msg());
            }
            
            if (!is_array($provinces_data)) {
                throw new Exception('JSON data should be an array of provinces');
            }
            
            // Import data
            $result = $this->import_provinces_to_database($provinces_data);
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    // REST API ENDPOINTS
    
    public function register_rest_routes() {
        register_rest_route('aqm/v1', '/campaigns', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_campaigns'),
            'permission_callback' => array($this, 'rest_check_permissions')
        ));
        
        register_rest_route('aqm/v1', '/campaigns/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_campaign'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('aqm/v1', '/campaigns/(?P<id>\d+)/questions', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_campaign_questions'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('aqm/v1', '/provinces', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_provinces'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('aqm/v1', '/provinces/(?P<code>[a-zA-Z0-9]+)/districts', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_districts'),
            'permission_callback' => '__return_true'
        ));
    }
    
    public function rest_get_campaigns($request) {
        $campaigns = $this->db->get_campaigns(array('status' => 'active'));
        
        $formatted_campaigns = array_map(function($campaign) {
            return array(
                'id' => $campaign->id,
                'title' => $campaign->title,
                'description' => $campaign->description,
                'status' => $campaign->status,
                'start_date' => $campaign->start_date,
                'end_date' => $campaign->end_date
            );
        }, $campaigns);
        
        return rest_ensure_response($formatted_campaigns);
    }
    
    public function rest_get_campaign($request) {
        $campaign_id = $request['id'];
        $campaign = $this->db->get_campaign($campaign_id);
        
        if (!$campaign || $campaign->status !== 'active') {
            return new WP_Error('campaign_not_found', 'Campaign not found', array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'id' => $campaign->id,
            'title' => $campaign->title,
            'description' => $campaign->description,
            'status' => $campaign->status,
            'start_date' => $campaign->start_date,
            'end_date' => $campaign->end_date,
            'settings' => json_decode($campaign->settings, true)
        ));
    }
    
    public function rest_get_campaign_questions($request) {
        $campaign_id = $request['id'];
        $questions = $this->db->get_campaign_questions($campaign_id);
        
        $formatted_questions = array_map(function($question) {
            return array(
                'id' => $question->id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'options' => json_decode($question->options, true),
                'is_required' => (bool)$question->is_required,
                'points' => $question->points
            );
        }, $questions);
        
        return rest_ensure_response($formatted_questions);
    }
    
    public function rest_get_provinces($request) {
        $provinces = $this->db->get_provinces();
        return rest_ensure_response($provinces);
    }
    
    public function rest_get_districts($request) {
        $province_code = $request['code'];
        $districts = $this->db->get_districts($province_code);
        return rest_ensure_response($districts);
    }
    
    public function rest_check_permissions() {
        return current_user_can('manage_options');
    }
    
    // UTILITY METHODS
    
    private function verify_nonce($action) {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $action)) {
            throw new Exception('Security check failed');
        }
    }
    
    private function check_admin_permissions() {
        if (!current_user_can('manage_options')) {
            throw new Exception('Insufficient permissions');
        }
    }
    
    private function process_answer($question, $answer) {
        switch ($question->question_type) {
            case 'provinces':
            case 'districts':
            case 'wards':
                return is_array($answer) ? json_encode($answer) : $answer;
            case 'email':
                return sanitize_email($answer);
            case 'number':
                return floatval($answer);
            case 'rating':
                return intval($answer);
            default:
                return sanitize_text_field($answer);
        }
    }
    
    private function get_user_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
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
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    private function get_overview_analytics() {
        global $wpdb;
        
        $total_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_campaigns");
        $active_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_campaigns WHERE status = 'active'");
        $total_responses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses");
        $completed_responses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE status = 'completed'");
        $gifts_awarded = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_gift_awards");
        
        $completion_rate = $total_responses > 0 ? round(($completed_responses / $total_responses) * 100, 1) : 0;
        
        return array(
            'total_campaigns' => intval($total_campaigns),
            'active_campaigns' => intval($active_campaigns),
            'total_responses' => intval($total_responses),
            'completed_responses' => intval($completed_responses),
            'completion_rate' => floatval($completion_rate),
            'gifts_awarded' => intval($gifts_awarded)
        );
    }
    
    private function import_provinces_to_database($provinces_data) {
        global $wpdb;
        
        $provinces_imported = 0;
        $districts_imported = 0;
        $wards_imported = 0;
        
        // Clear existing data
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aqm_provinces");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aqm_districts");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aqm_wards");
        
        foreach ($provinces_data as $province) {
            // Insert province
            $result = $wpdb->insert(
                $wpdb->prefix . 'aqm_provinces',
                array(
                    'code' => sanitize_text_field($province['code']),
                    'name' => sanitize_text_field($province['name']),
                    'name_en' => sanitize_text_field($province['name_en'] ?? ''),
                    'full_name' => sanitize_text_field($province['full_name'] ?? ''),
                    'full_name_en' => sanitize_text_field($province['full_name_en'] ?? ''),
                    'code_name' => sanitize_text_field($province['code_name'] ?? '')
                )
            );
            
            if ($result) {
                $provinces_imported++;
                
                // Insert districts
                if (isset($province['districts']) && is_array($province['districts'])) {
                    foreach ($province['districts'] as $district) {
                        $district_result = $wpdb->insert(
                            $wpdb->prefix . 'aqm_districts',
                            array(
                                'code' => sanitize_text_field($district['code']),
                                'name' => sanitize_text_field($district['name']),
                                'name_en' => sanitize_text_field($district['name_en'] ?? ''),
                                'full_name' => sanitize_text_field($district['full_name'] ?? ''),
                                'province_code' => sanitize_text_field($province['code'])
                            )
                        );
                        
                        if ($district_result) {
                            $districts_imported++;
                            
                            // Insert wards
                            if (isset($district['wards']) && is_array($district['wards'])) {
                                foreach ($district['wards'] as $ward) {
                                    $ward_result = $wpdb->insert(
                                        $wpdb->prefix . 'aqm_wards',
                                        array(
                                            'code' => sanitize_text_field($ward['code']),
                                            'name' => sanitize_text_field($ward['name']),
                                            'name_en' => sanitize_text_field($ward['name_en'] ?? ''),
                                            'full_name' => sanitize_text_field($ward['full_name'] ?? ''),
                                            'district_code' => sanitize_text_field($district['code'])
                                        )
                                    );
                                    
                                    if ($ward_result) {
                                        $wards_imported++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return array(
            'success' => true,
            'message' => sprintf(
                'Successfully imported %d provinces, %d districts, and %d wards.',
                $provinces_imported,
                $districts_imported,
                $wards_imported
            ),
            'stats' => array(
                'provinces' => $provinces_imported,
                'districts' => $districts_imported,
                'wards' => $wards_imported
            )
        );
    }
}
?>