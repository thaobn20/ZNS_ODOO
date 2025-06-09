<?php
/**
 * Frontend Class for Advanced Quiz Manager - Mobile Implementation
 * File: public/class-frontend.php
 */

class AQM_Frontend {
    
    private $db;
    
    public function __construct() {
        $this->db = new AQM_Database();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Shortcode registration
        add_shortcode('quiz_form', array($this, 'quiz_form_shortcode'));
        add_shortcode('quiz_results', array($this, 'quiz_results_shortcode'));
        
        // Frontend scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_aqm_check_phone', array($this, 'ajax_check_phone'));
        add_action('wp_ajax_nopriv_aqm_check_phone', array($this, 'ajax_check_phone'));
        add_action('wp_ajax_aqm_submit_quiz', array($this, 'ajax_submit_quiz'));
        add_action('wp_ajax_nopriv_aqm_submit_quiz', array($this, 'ajax_submit_quiz'));
        add_action('wp_ajax_aqm_get_districts', array($this, 'ajax_get_districts'));
        add_action('wp_ajax_nopriv_aqm_get_districts', array($this, 'ajax_get_districts'));
        add_action('wp_ajax_aqm_get_wards', array($this, 'ajax_get_wards'));
        add_action('wp_ajax_nopriv_aqm_get_wards', array($this, 'ajax_get_wards'));
    }
    
    public function enqueue_scripts() {
        if ($this->should_enqueue_scripts()) {
            wp_enqueue_script(
                'aqm-frontend-js', 
                AQM_PLUGIN_URL . 'assets/js/frontend.js', 
                array('jquery'), 
                AQM_VERSION, 
                true
            );
            
            wp_enqueue_style(
                'aqm-frontend-css', 
                AQM_PLUGIN_URL . 'assets/css/frontend.css', 
                array(), 
                AQM_VERSION
            );
            
            // Localize script with data
            wp_localize_script('aqm-frontend-js', 'aqm_front', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqm_front_nonce'),
                'strings' => array(
                    'loading' => __('Loading quiz questions...', 'advanced-quiz'),
                    'submitting' => __('Submitting answers...', 'advanced-quiz'),
                    'error' => __('An error occurred. Please try again.', 'advanced-quiz'),
                    'phone_exists' => __('You have already participated in this campaign.', 'advanced-quiz'),
                    'quiz_completed' => __('Quiz completed successfully!', 'advanced-quiz'),
                    'congratulations' => __('Congratulations!', 'advanced-quiz'),
                    'name_required' => __('Please enter your full name', 'advanced-quiz'),
                    'phone_required' => __('Please enter a valid phone number', 'advanced-quiz'),
                    'province_required' => __('Please select your province/city', 'advanced-quiz')
                )
            ));
        }
    }
    
    private function should_enqueue_scripts() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'quiz_form')) {
            return true;
        }
        
        return false;
    }
    
    // SHORTCODE HANDLERS
    
    public function quiz_form_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'style' => 'mobile',
            'class' => ''
        ), $atts, 'quiz_form');
        
        $campaign_id = intval($atts['campaign_id']);
        
        if (!$campaign_id) {
            return $this->render_error(__('Campaign ID is required.', 'advanced-quiz'));
        }
        
        $campaign = $this->db->get_campaign($campaign_id);
        
        if (!$campaign || $campaign->status !== 'active') {
            return $this->render_error(__('This quiz campaign is not available.', 'advanced-quiz'));
        }
        
        return $this->render_mobile_quiz($campaign, $atts);
    }
    
    // MAIN RENDERING METHOD
    
    private function render_mobile_quiz($campaign, $atts) {
        $questions = $this->db->get_campaign_questions($campaign->id);
        $provinces = $this->get_provinces_data();
        
        ob_start();
        ?>
        <div class="aqm-quiz-container <?php echo esc_attr($atts['class']); ?>" 
             data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
            
            <!-- Header -->
            <div class="aqm-quiz-header">
                <h1>🎯 <?php echo esc_html($campaign->title); ?></h1>
                <p><?php echo esc_html($campaign->description ?: 'Complete the quiz and win amazing rewards!'); ?></p>
            </div>
            
            <!-- Progress Bar -->
            <div class="aqm-progress-bar">
                <div class="aqm-progress-fill" id="aqm-progress-fill"></div>
            </div>

            <!-- Registration Form -->
            <div class="aqm-quiz-content" id="aqm-registration-form">
                <form id="aqm-user-form">
                    <?php wp_nonce_field('aqm_front_nonce', 'aqm_nonce'); ?>
                    <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->id); ?>">
                    
                    <div class="aqm-form-group">
                        <label class="aqm-form-label" for="aqm-full-name">Full Name *</label>
                        <input type="text" id="aqm-full-name" name="full_name" class="aqm-form-input" 
                               placeholder="Enter your full name" required>
                        <div class="aqm-error-message" id="aqm-name-error">Please enter your full name</div>
                    </div>

                    <div class="aqm-form-group">
                        <label class="aqm-form-label" for="aqm-phone-number">Phone Number *</label>
                        <input type="tel" id="aqm-phone-number" name="phone_number" class="aqm-form-input" 
                               placeholder="0901234567" required>
                        <div class="aqm-error-message" id="aqm-phone-error">Please enter a valid phone number</div>
                    </div>

                    <div class="aqm-form-group">
                        <label class="aqm-form-label" for="aqm-province">Province/City *</label>
                        <select id="aqm-province" name="province" class="aqm-form-select" required>
                            <option value="">Select your province/city</option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?php echo esc_attr($province->code); ?>">
                                    <?php echo esc_html($province->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="aqm-error-message" id="aqm-province-error">Please select your province/city</div>
                    </div>

                    <div class="aqm-form-group">
                        <label class="aqm-form-label" for="aqm-pharmacy-code">Pharmacy Code</label>
                        <input type="text" id="aqm-pharmacy-code" name="pharmacy_code" class="aqm-form-input" 
                               placeholder="Optional">
                    </div>

                    <div class="aqm-button-group">
                        <button type="submit" class="aqm-btn aqm-btn-primary">Continue →</button>
                    </div>
                </form>
            </div>

            <!-- Loading State -->
            <div class="aqm-loading" id="aqm-loading-state">
                <div class="aqm-spinner"></div>
                <p>Loading quiz questions...</p>
            </div>

            <!-- Quiz Container -->
            <div class="aqm-question-container" id="aqm-quiz-container">
                <div class="aqm-quiz-content">
                    <div class="aqm-question-header">
                        <div class="aqm-question-counter" id="aqm-question-counter">Question 1 of <?php echo count($questions); ?></div>
                        <div class="aqm-question-title" id="aqm-question-title">Loading question...</div>
                    </div>

                    <div class="aqm-answers-container" id="aqm-answers-container">
                        <!-- Answers will be loaded here -->
                    </div>

                    <div class="aqm-button-group">
                        <button type="button" class="aqm-btn aqm-btn-secondary" id="aqm-prev-btn" disabled>← Previous</button>
                        <button type="button" class="aqm-btn aqm-btn-primary" id="aqm-next-btn">Next →</button>
                    </div>
                </div>
            </div>

            <!-- Result Container -->
            <div class="aqm-result-container" id="aqm-result-container">
                <div class="aqm-result-icon">🎉</div>
                <div class="aqm-result-score" id="aqm-result-score">5/5</div>
                <div class="aqm-result-message" id="aqm-result-message">Congratulations!</div>
                
                <div class="aqm-reward-card" id="aqm-reward-card">
                    <div class="aqm-reward-title">🎁 You've won a reward!</div>
                    <div class="aqm-reward-code" id="aqm-reward-code">REWARD123</div>
                </div>
                
                <div class="aqm-button-group">
                    <button type="button" class="aqm-btn aqm-btn-primary" onclick="location.reload()">Take Another Quiz</button>
                </div>
            </div>

        </div>

        <!-- Popup for Already Participated -->
        <div class="aqm-popup" id="aqm-already-participated-popup">
            <div class="aqm-popup-content">
                <div class="aqm-popup-icon">⚠️</div>
                <div class="aqm-popup-message">You have already participated in this campaign.</div>
                <div class="aqm-button-group">
                    <button type="button" class="aqm-btn aqm-btn-primary" onclick="aqmClosePopup()">OK</button>
                </div>
            </div>
        </div>

        <!-- Quiz Questions Data -->
        <script type="application/json" id="aqm-quiz-data">
        {
            "campaign_id": <?php echo esc_js($campaign->id); ?>,
            "questions": <?php echo json_encode($this->format_questions_for_frontend($questions)); ?>
        }
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    // AJAX HANDLERS
    
    public function ajax_check_phone() {
        check_ajax_referer('aqm_front_nonce', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone']);
        $campaign_id = intval($_POST['campaign_id']);
        
        if (empty($phone) || empty($campaign_id)) {
            wp_send_json_error(array('message' => 'Invalid data'));
        }
        
        // Check if phone already exists for this campaign
        $exists = $this->db->check_phone_participation($phone, $campaign_id);
        
        if ($exists) {
            wp_send_json_error(array('message' => 'Phone already exists', 'code' => 'phone_exists'));
        } else {
            wp_send_json_success(array('message' => 'Phone available'));
        }
    }
    
    public function ajax_submit_quiz() {
        check_ajax_referer('aqm_front_nonce', 'nonce');
        
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
            wp_send_json_error(array('message' => 'Required fields missing'));
        }
        
        // Check phone again
        if ($this->db->check_phone_participation($user_data['phone_number'], $campaign_id)) {
            wp_send_json_error(array('message' => 'Phone already exists', 'code' => 'phone_exists'));
        }
        
        // Calculate score
        $questions = $this->db->get_campaign_questions($campaign_id);
        $score = $this->calculate_score($answers, $questions);
        $total_questions = count($questions);
        
        // Get gift if eligible
        $gift = $this->get_eligible_gift($campaign_id, $score, $total_questions);
        
        // Save response
        $response_id = $this->db->save_quiz_response($campaign_id, $user_data, $answers, $score, $gift);
        
        if ($response_id) {
            wp_send_json_success(array(
                'score' => $score,
                'total' => $total_questions,
                'percentage' => round(($score / $total_questions) * 100),
                'gift' => $gift,
                'message' => $this->get_score_message($score, $total_questions)
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save response'));
        }
    }
    
    public function ajax_get_districts() {
        check_ajax_referer('aqm_front_nonce', 'nonce');
        
        $province_code = sanitize_text_field($_POST['province_code']);
        $districts = $this->db->get_districts_by_province($province_code);
        
        wp_send_json_success($districts);
    }
    
    public function ajax_get_wards() {
        check_ajax_referer('aqm_front_nonce', 'nonce');
        
        $district_code = sanitize_text_field($_POST['district_code']);
        $wards = $this->db->get_wards_by_district($district_code);
        
        wp_send_json_success($wards);
    }
    
    // HELPER METHODS
    
    private function get_provinces_data() {
        return $this->db->get_provinces();
    }
    
    private function format_questions_for_frontend($questions) {
        $formatted = array();
        
        foreach ($questions as $question) {
            $options = json_decode($question->options, true) ?: array();
            
            $formatted[] = array(
                'id' => $question->id,
                'question' => $question->question_text,
                'type' => $question->question_type,
                'options' => $options,
                'points' => $question->points ?: 1
            );
        }
        
        return $formatted;
    }
    
    private function calculate_score($answers, $questions) {
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
                if (isset($option['correct']) && $option['correct']) {
                    $correct_options[] = $option['id'];
                }
            }
            
            // Check if user answered correctly
            if (!empty($correct_options)) {
                $correct_count = count(array_intersect($user_answers, $correct_options));
                $incorrect_count = count(array_diff($user_answers, $correct_options));
                
                if ($correct_count > 0 && $incorrect_count === 0) {
                    $score += $question->points ?: 1;
                }
            }
        }
        
        return $score;
    }
    
    private function get_eligible_gift($campaign_id, $score, $total_questions) {
        $gifts = $this->db->get_campaign_gifts($campaign_id);
        $percentage = ($score / $total_questions) * 100;
        
        foreach ($gifts as $gift) {
            $requirements = json_decode($gift->requirements, true) ?: array();
            $min_score = $requirements['min_score_percentage'] ?? 0;
            
            if ($percentage >= $min_score && $gift->quantity > 0) {
                // Decrease quantity
                $this->db->decrease_gift_quantity($gift->id);
                
                return array(
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
        return strtoupper($gift->code_prefix . wp_generate_password(6, false));
    }
    
    private function get_score_message($score, $total) {
        $percentage = ($score / $total) * 100;
        
        if ($percentage >= 80) {
            return 'Excellent! You really know your health facts!';
        } elseif ($percentage >= 60) {
            return 'Great job! You have good health knowledge.';
        } else {
            return 'Good effort! Keep learning about health and wellness.';
        }
    }
    
    private function render_error($message) {
        return '<div class="aqm-error-message"><p class="error">' . esc_html($message) . '</p></div>';
    }
}
?>