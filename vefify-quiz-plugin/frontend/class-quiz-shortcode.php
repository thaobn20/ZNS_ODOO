<?php
/**
 * Enhanced Quiz Shortcode with Complete Registration Form
 * File: frontend/class-quiz-shortcode-enhanced.php
 * 
 * This handles the complete quiz flow including:
 * - Registration form with Province/Pharmacist fields
 * - Database integration
 * - Gift configuration
 * - Analytics tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Shortcode_Enhanced {
    
    private static $instance = null;
    private $database;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize database connection
        global $wpdb;
        $this->database = $wpdb;
        
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_ajax_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        add_action('wp_ajax_nopriv_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        add_action('wp_ajax_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
        add_action('wp_ajax_nopriv_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
        add_action('wp_ajax_vefify_check_phone', array($this, 'ajax_check_phone'));
        add_action('wp_ajax_nopriv_vefify_check_phone', array($this, 'ajax_check_phone'));
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('vefify_quiz', array($this, 'render_quiz'));
    }
    
    /**
     * Main quiz shortcode: [vefify_quiz campaign_id="1"]
     */
    public function render_quiz($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'template' => 'enhanced',
            'theme' => 'modern'
        ), $atts);
        
        $campaign_id = intval($atts['campaign_id']);
        
        if (!$campaign_id) {
            return '<div class="vefify-error">❌ Campaign ID is required. Usage: [vefify_quiz campaign_id="1"]</div>';
        }
        
        // Get campaign data
        $campaign = $this->get_campaign_data($campaign_id);
        
        if (!$campaign) {
            return '<div class="vefify-error">❌ Campaign not found (ID: ' . $campaign_id . ')</div>';
        }
        
        // Check if campaign is active
        if (!$this->is_campaign_active($campaign)) {
            return '<div class="vefify-notice">📅 This campaign is not currently active.</div>';
        }
        
        // Generate unique quiz instance ID
        $quiz_id = 'quiz_' . $campaign_id . '_' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($quiz_id); ?>" class="vefify-quiz-container enhanced-version" data-campaign-id="<?php echo $campaign_id; ?>">
            <?php echo $this->render_enhanced_template($campaign, $atts); ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            VefifyQuizEnhanced.init('<?php echo $quiz_id; ?>', {
                campaignId: <?php echo $campaign_id; ?>,
                settings: <?php echo json_encode($this->get_quiz_settings($campaign)); ?>,
                ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('vefify_quiz_nonce'); ?>'
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render enhanced template with registration form
     */
    private function render_enhanced_template($campaign, $atts) {
        ob_start();
        ?>
        <div class="vefify-quiz-wrapper theme-<?php echo esc_attr($atts['theme']); ?>">
            
            <!-- Quiz Header -->
            <div class="quiz-header">
                <div class="header-gradient"></div>
                <div class="header-content">
                    <h2 class="quiz-title"><?php echo esc_html($campaign['name']); ?></h2>
                    <?php if (!empty($campaign['description'])): ?>
                        <p class="quiz-description"><?php echo esc_html($campaign['description']); ?></p>
                    <?php endif; ?>
                    
                    <div class="quiz-meta">
                        <div class="meta-item">
                            <span class="meta-icon">📝</span>
                            <span class="meta-text"><?php echo $campaign['questions_per_quiz']; ?> Questions</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">⏱️</span>
                            <span class="meta-text"><?php echo $campaign['time_limit']; ?> Minutes</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">🎯</span>
                            <span class="meta-text">Pass Score: <?php echo $campaign['pass_score']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="quiz-progress" style="display: none;">
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="progress-text">
                        <span class="step-info">Question <span class="current-step">1</span> of <span class="total-steps"><?php echo $campaign['questions_per_quiz']; ?></span></span>
                        <span class="time-remaining">Time: <span class="timer-display"></span></span>
                    </div>
                </div>
            </div>
            
            <!-- Quiz Content -->
            <div class="quiz-content">
                
                <!-- Registration Screen -->
                <div class="quiz-screen registration-screen active">
                    <div class="registration-content">
                        <div class="registration-header">
                            <h3>📋 Enter Your Information</h3>
                            <p>Please provide your details to participate in the quiz</p>
                        </div>
                        
                        <form class="registration-form" id="vefify-registration-form">
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="full_name">Full Name <span class="required">*</span></label>
                                    <input type="text" id="full_name" name="full_name" required placeholder="Enter your full name">
                                    <div class="field-error"></div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Email Address <span class="required">*</span></label>
                                    <input type="email" id="email" name="email" required placeholder="your.email@example.com">
                                    <div class="field-error"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone Number <span class="required">*</span></label>
                                    <input type="tel" id="phone" name="phone" required placeholder="0912345678" pattern="[0-9]{10,11}">
                                    <div class="field-error"></div>
                                    <div class="field-help">Vietnamese mobile number (10-11 digits)</div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="province">Province/City <span class="required">*</span></label>
                                    <select id="province" name="province" required>
                                        <option value="">Select Province/City</option>
                                        <?php echo $this->get_provinces_options(); ?>
                                    </select>
                                    <div class="field-error"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="district">District</label>
                                    <select id="district" name="district">
                                        <option value="">Select District</option>
                                    </select>
                                    <div class="field-error"></div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="pharmacist_code">Pharmacist Code</label>
                                    <input type="text" id="pharmacist_code" name="pharmacist_code" placeholder="PH123456">
                                    <div class="field-error"></div>
                                    <div class="field-help">Optional - Enter if you are a licensed pharmacist</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="company">Company/Organization</label>
                                    <input type="text" id="company" name="company" placeholder="Optional">
                                    <div class="field-error"></div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-large">
                                    <span class="btn-icon">🚀</span>
                                    <span class="btn-text">Start Quiz</span>
                                    <div class="btn-loading" style="display: none;">
                                        <span class="spinner"></span>
                                        <span>Validating...</span>
                                    </div>
                                </button>
                            </div>
                            
                            <div class="form-footer">
                                <p class="privacy-note">
                                    <span class="privacy-icon">🔒</span>
                                    Your information is secure and will only be used for this quiz.
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Quiz Screen -->
                <div class="quiz-screen question-screen">
                    <div class="question-container">
                        <div class="question-header">
                            <h3 class="question-text"></h3>
                            <div class="question-meta">
                                <span class="question-type"></span>
                                <span class="question-difficulty"></span>
                            </div>
                        </div>
                        
                        <div class="question-content">
                            <div class="question-options"></div>
                            
                            <div class="question-actions">
                                <button class="btn btn-secondary prev-btn" style="display: none;">
                                    <span>← Previous</span>
                                </button>
                                <button class="btn btn-primary next-btn">
                                    <span>Next →</span>
                                </button>
                                <button class="btn btn-success submit-btn" style="display: none;">
                                    <span>🎯 Submit Quiz</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Results Screen -->
                <div class="quiz-screen results-screen">
                    <div class="results-content">
                        <div class="results-header">
                            <h3>🎉 Quiz Complete!</h3>
                            <div class="completion-badge">
                                <span class="badge-icon">✓</span>
                                <span class="badge-text">Completed</span>
                            </div>
                        </div>
                        
                        <div class="score-display">
                            <div class="score-circle">
                                <div class="score-inner">
                                    <span class="score-number">0</span>
                                    <span class="score-divider">/</span>
                                    <span class="score-total">0</span>
                                </div>
                                <div class="score-percentage">0%</div>
                            </div>
                        </div>
                        
                        <div class="results-details">
                            <div class="result-status">
                                <span class="status-icon"></span>
                                <span class="status-text"></span>
                            </div>
                            
                            <div class="result-breakdown">
                                <div class="breakdown-item">
                                    <span class="breakdown-label">Correct Answers:</span>
                                    <span class="breakdown-value correct-count">0</span>
                                </div>
                                <div class="breakdown-item">
                                    <span class="breakdown-label">Time Taken:</span>
                                    <span class="breakdown-value time-taken">0s</span>
                                </div>
                                <div class="breakdown-item">
                                    <span class="breakdown-label">Accuracy:</span>
                                    <span class="breakdown-value accuracy">0%</span>
                                </div>
                            </div>
                            
                            <div class="gift-section" style="display: none;">
                                <div class="gift-card">
                                    <div class="gift-header">
                                        <h4>🎁 Congratulations!</h4>
                                        <p>You've earned a reward!</p>
                                    </div>
                                    <div class="gift-details">
                                        <div class="gift-name"></div>
                                        <div class="gift-code"></div>
                                        <div class="gift-instructions"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="result-actions">
                            <button class="btn btn-primary restart-btn">
                                <span>🔄 Try Again</span>
                            </button>
                            <button class="btn btn-secondary share-btn">
                                <span>📤 Share Results</span>
                            </button>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        
        <?php echo $this->get_enhanced_styles(); ?>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get provinces options for Vietnam
     */
    private function get_provinces_options() {
        $provinces = array(
            'An Giang', 'Bà Rịa - Vũng Tàu', 'Bắc Giang', 'Bắc Kạn', 'Bạc Liêu',
            'Bắc Ninh', 'Bến Tre', 'Bình Định', 'Bình Dương', 'Bình Phước',
            'Bình Thuận', 'Cà Mau', 'Cao Bằng', 'Đắk Lắk', 'Đắk Nông',
            'Điện Biên', 'Đồng Nai', 'Đồng Tháp', 'Gia Lai', 'Hà Giang',
            'Hà Nam', 'Hà Tĩnh', 'Hải Dương', 'Hậu Giang', 'Hòa Bình',
            'Hưng Yên', 'Khánh Hòa', 'Kiên Giang', 'Kon Tum', 'Lai Châu',
            'Lâm Đồng', 'Lạng Sơn', 'Lào Cai', 'Long An', 'Nam Định',
            'Nghệ An', 'Ninh Bình', 'Ninh Thuận', 'Phú Thọ', 'Phú Yên',
            'Quảng Bình', 'Quảng Nam', 'Quảng Ngãi', 'Quảng Ninh', 'Quảng Trị',
            'Sóc Trăng', 'Sơn La', 'Tây Ninh', 'Thái Bình', 'Thái Nguyên',
            'Thanh Hóa', 'Thừa Thiên Huế', 'Tiền Giang', 'Trà Vinh', 'Tuyên Quang',
            'Vĩnh Long', 'Vĩnh Phúc', 'Yên Bái', 'Hà Nội', 'Hồ Chí Minh',
            'Đà Nẵng', 'Hải Phòng', 'Cần Thơ'
        );
        
        $options = '';
        foreach ($provinces as $province) {
            $options .= '<option value="' . esc_attr($province) . '">' . esc_html($province) . '</option>';
        }
        
        return $options;
    }
    
    /**
     * AJAX: Check phone number uniqueness
     */
    public function ajax_check_phone() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone']);
        $campaign_id = intval($_POST['campaign_id']);
        
        if (!$phone || !$campaign_id) {
            wp_send_json_error('Missing required data');
        }
        
        // Check if phone already participated
        $table = $this->database->prefix . 'vefify_participants';
        $exists = $this->database->get_var($this->database->prepare(
            "SELECT id FROM {$table} WHERE phone = %s AND campaign_id = %d",
            $phone, $campaign_id
        ));
        
        if ($exists) {
            wp_send_json_error('This phone number has already participated in this campaign');
        }
        
        wp_send_json_success('Phone number is available');
    }
    
    /**
     * AJAX: Start quiz (save participant and get questions)
     */
    public function ajax_start_quiz() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $campaign_id = intval($_POST['campaign_id']);
        $participant_data = $_POST['participant_data'];
        
        if (!$campaign_id || !$participant_data) {
            wp_send_json_error('Missing required data');
        }
        
        try {
            // Validate participant data
            $validated_data = $this->validate_participant_data($participant_data);
            
            // Save participant
            $participant_id = $this->save_participant($campaign_id, $validated_data);
            
            if (!$participant_id) {
                wp_send_json_error('Failed to save participant data');
            }
            
            // Get quiz questions
            $questions = $this->get_quiz_questions($campaign_id);
            
            if (empty($questions)) {
                wp_send_json_error('No questions available for this campaign');
            }
            
            // Create quiz session
            $session_id = $this->create_quiz_session($participant_id, $campaign_id);
            
            wp_send_json_success(array(
                'participant_id' => $participant_id,
                'session_id' => $session_id,
                'questions' => $questions,
                'message' => 'Quiz started successfully'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error starting quiz: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Submit quiz answers
     */
    public function ajax_submit_quiz() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id']);
        $answers = $_POST['answers'];
        $time_taken = intval($_POST['time_taken']);
        
        if (!$session_id || !is_array($answers)) {
            wp_send_json_error('Missing required data');
        }
        
        try {
            // Get session data
            $session = $this->get_quiz_session($session_id);
            
            if (!$session) {
                wp_send_json_error('Invalid session');
            }
            
            // Calculate score
            $score_data = $this->calculate_score($answers, $session['campaign_id']);
            
            // Update participant with results
            $this->update_participant_results($session['participant_id'], $score_data, $time_taken);
            
            // Check for gift eligibility
            $gift_data = $this->check_gift_eligibility($session['campaign_id'], $session['participant_id'], $score_data['score']);
            
            // Save analytics
            $this->save_quiz_analytics($session, $score_data, $time_taken);
            
            wp_send_json_success(array(
                'score' => $score_data['score'],
                'total_questions' => $score_data['total'],
                'percentage' => $score_data['percentage'],
                'correct_answers' => $score_data['correct'],
                'time_taken' => $time_taken,
                'gift' => $gift_data,
                'passed' => $score_data['passed'],
                'message' => 'Quiz completed successfully'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error submitting quiz: ' . $e->getMessage());
        }
    }
    
    /**
     * Get campaign data
     */
    private function get_campaign_data($campaign_id) {
        $table = $this->database->prefix . 'vefify_campaigns';
        
        return $this->database->get_row($this->database->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $campaign_id
        ), ARRAY_A);
    }
    
    /**
     * Check if campaign is active
     */
    private function is_campaign_active($campaign) {
        if (!$campaign['is_active']) {
            return false;
        }
        
        $now = current_time('mysql');
        return ($now >= $campaign['start_date'] && $now <= $campaign['end_date']);
    }
    
    /**
     * Get quiz questions for campaign
     */
    private function get_quiz_questions($campaign_id) {
        $questions_table = $this->database->prefix . 'vefify_questions';
        $options_table = $this->database->prefix . 'vefify_question_options';
        
        // Get campaign info
        $campaign = $this->get_campaign_data($campaign_id);
        $limit = $campaign['questions_per_quiz'] ?? 5;
        
        // Get questions
        $questions = $this->database->get_results($this->database->prepare(
            "SELECT * FROM {$questions_table} 
             WHERE campaign_id = %d AND is_active = 1 
             ORDER BY RAND() 
             LIMIT %d",
            $campaign_id, $limit
        ), ARRAY_A);
        
        // Get options for each question
        foreach ($questions as &$question) {
            $options = $this->database->get_results($this->database->prepare(
                "SELECT * FROM {$options_table} 
                 WHERE question_id = %d 
                 ORDER BY option_order",
                $question['id']
            ), ARRAY_A);
            
            $question['options'] = $options;
        }
        
        return $questions;
    }
    
    /**
     * Validate participant data
     */
    private function validate_participant_data($data) {
        $errors = array();
        
        // Required fields
        if (empty($data['full_name'])) {
            $errors[] = 'Full name is required';
        }
        
        if (empty($data['email']) || !is_email($data['email'])) {
            $errors[] = 'Valid email is required';
        }
        
        if (empty($data['phone']) || !preg_match('/^[0-9]{10,11}$/', $data['phone'])) {
            $errors[] = 'Valid Vietnamese phone number is required';
        }
        
        if (empty($data['province'])) {
            $errors[] = 'Province/City is required';
        }
        
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        return array(
            'full_name' => sanitize_text_field($data['full_name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone']),
            'province' => sanitize_text_field($data['province']),
            'district' => sanitize_text_field($data['district'] ?? ''),
            'pharmacist_code' => sanitize_text_field($data['pharmacist_code'] ?? ''),
            'company' => sanitize_text_field($data['company'] ?? '')
        );
    }
    
    /**
     * Save participant to database
     */
    private function save_participant($campaign_id, $data) {
        $table = $this->database->prefix . 'vefify_participants';
        
        $result = $this->database->insert(
            $table,
            array(
                'campaign_id' => $campaign_id,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'province' => $data['province'],
                'district' => $data['district'],
                'pharmacist_code' => $data['pharmacist_code'],
                'company' => $data['company'],
                'registration_date' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $this->database->insert_id : false;
    }
    
    /**
     * Create quiz session
     */
    private function create_quiz_session($participant_id, $campaign_id) {
        $session_id = 'qs_' . $participant_id . '_' . uniqid();
        
        $table = $this->database->prefix . 'vefify_quiz_sessions';
        
        $this->database->insert(
            $table,
            array(
                'session_id' => $session_id,
                'participant_id' => $participant_id,
                'campaign_id' => $campaign_id,
                'start_time' => current_time('mysql'),
                'status' => 'active'
            ),
            array('%s', '%d', '%d', '%s', '%s')
        );
        
        return $session_id;
    }
    
    /**
     * Get quiz session data
     */
    private function get_quiz_session($session_id) {
        $table = $this->database->prefix . 'vefify_quiz_sessions';
        
        return $this->database->get_row($this->database->prepare(
            "SELECT * FROM {$table} WHERE session_id = %s",
            $session_id
        ), ARRAY_A);
    }
    
    /**
     * Calculate quiz score
     */
    private function calculate_score($answers, $campaign_id) {
        $questions_table = $this->database->prefix . 'vefify_questions';
        $options_table = $this->database->prefix . 'vefify_question_options';
        
        $correct_count = 0;
        $total_questions = count($answers);
        
        foreach ($answers as $question_id => $selected_option_id) {
            // Get correct answer
            $correct_option = $this->database->get_row($this->database->prepare(
                "SELECT * FROM {$options_table} 
                 WHERE question_id = %d AND is_correct = 1",
                $question_id
            ));
            
            if ($correct_option && $correct_option->id == $selected_option_id) {
                $correct_count++;
            }
        }
        
        // Get campaign pass score
        $campaign = $this->get_campaign_data($campaign_id);
        $pass_score = $campaign['pass_score'] ?? 3;
        
        $percentage = round(($correct_count / $total_questions) * 100);
        
        return array(
            'score' => $correct_count,
            'total' => $total_questions,
            'correct' => $correct_count,
            'percentage' => $percentage,
            'passed' => $correct_count >= $pass_score
        );
    }
    
    /**
     * Update participant with quiz results
     */
    private function update_participant_results($participant_id, $score_data, $time_taken) {
        $table = $this->database->prefix . 'vefify_participants';
        
        $this->database->update(
            $table,
            array(
                'score' => $score_data['score'],
                'total_questions' => $score_data['total'],
                'percentage' => $score_data['percentage'],
                'time_taken' => $time_taken,
                'completion_date' => current_time('mysql'),
                'status' => $score_data['passed'] ? 'passed' : 'failed'
            ),
            array('id' => $participant_id),
            array('%d', '%d', '%f', '%d', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Check gift eligibility
     */
    private function check_gift_eligibility($campaign_id, $participant_id, $score) {
        $gifts_table = $this->database->prefix . 'vefify_gifts';
        
        // Find eligible gift
        $gift = $this->database->get_row($this->database->prepare(
            "SELECT * FROM {$gifts_table} 
             WHERE campaign_id = %d AND is_active = 1 
             AND min_score <= %d 
             AND (max_score IS NULL OR max_score >= %d)
             AND (max_quantity IS NULL OR used_count < max_quantity)
             ORDER BY min_score DESC, gift_value DESC
             LIMIT 1",
            $campaign_id, $score, $score
        ), ARRAY_A);
        
        if (!$gift) {
            return array('has_gift' => false, 'message' => 'No gift available for your score');
        }
        
        // Generate gift code
        $gift_code = $this->generate_gift_code($gift['gift_code_prefix'] ?? 'GIFT');
        
        // Update participant with gift
        $participants_table = $this->database->prefix . 'vefify_participants';
        $this->database->update(
            $participants_table,
            array(
                'gift_id' => $gift['id'],
                'gift_code' => $gift_code,
                'gift_status' => 'assigned'
            ),
            array('id' => $participant_id),
            array('%d', '%s', '%s'),
            array('%d')
        );
        
        // Update gift usage
        $this->database->update(
            $gifts_table,
            array('used_count' => $gift['used_count'] + 1),
            array('id' => $gift['id']),
            array('%d'),
            array('%d')
        );
        
        return array(
            'has_gift' => true,
            'gift_name' => $gift['gift_name'],
            'gift_code' => $gift_code,
            'gift_value' => $gift['gift_value'],
            'gift_description' => $gift['gift_description']
        );
    }
    
    /**
     * Generate gift code
     */
    private function generate_gift_code($prefix = 'GIFT') {
        return strtoupper($prefix) . date('md') . strtoupper(wp_generate_password(6, false));
    }
    
    /**
     * Save quiz analytics
     */
    private function save_quiz_analytics($session, $score_data, $time_taken) {
        $table = $this->database->prefix . 'vefify_analytics';
        
        $this->database->insert(
            $table,
            array(
                'campaign_id' => $session['campaign_id'],
                'participant_id' => $session['participant_id'],
                'event_type' => 'quiz_completion',
                'event_data' => json_encode(array(
                    'score' => $score_data['score'],
                    'total_questions' => $score_data['total'],
                    'percentage' => $score_data['percentage'],
                    'time_taken' => $time_taken,
                    'passed' => $score_data['passed']
                )),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get quiz settings
     */
    private function get_quiz_settings($campaign) {
        return array(
            'timeLimit' => intval($campaign['time_limit']) * 60, // Convert to seconds
            'passScore' => intval($campaign['pass_score']),
            'questionsPerQuiz' => intval($campaign['questions_per_quiz']),
            'allowRestart' => true,
            'showExplanations' => true
        );
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vefify_quiz')) {
            
            wp_enqueue_script('jquery');
            
            // Enqueue enhanced quiz JavaScript
            wp_enqueue_script(
                'vefify-quiz-enhanced',
                plugin_dir_url(__FILE__) . '../assets/js/quiz-enhanced.js',
                array('jquery'),
                '1.0.0',
                true
            );
            
            // Localize script
            wp_localize_script('vefify-quiz-enhanced', 'vefifyAjax', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vefify_quiz_nonce'),
                'strings' => array(
                    'loading' => 'Loading...',
                    'error' => 'An error occurred',
                    'timeUp' => 'Time is up!',
                    'submitting' => 'Submitting...',
                    'validating' => 'Validating...',
                    'phoneExists' => 'This phone number has already participated',
                    'invalidPhone' => 'Please enter a valid Vietnamese phone number',
                    'requiredField' => 'This field is required'
                )
            ));
        }
    }
    
    /**
     * Get enhanced styles
     */
    private function get_enhanced_styles() {
        return '
        <style>
        .vefify-quiz-wrapper {
            max-width: 800px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            position: relative;
        }
        
        .quiz-header {
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            overflow: hidden;
        }
        
        .header-gradient {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
        }
        
        .header-content {
            position: relative;
            z-index: 2;
        }
        
        .quiz-title {
            margin: 0 0 15px;
            font-size: 28px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .quiz-description {
            margin: 0 0 25px;
            opacity: 0.95;
            font-size: 16px;
            line-height: 1.5;
        }
        
        .quiz-meta {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .meta-icon {
            font-size: 16px;
        }
        
        .quiz-progress {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .progress-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .progress-bar {
            flex: 1;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
            transition: width 0.3s ease;
            width: 0%;
        }
        
        .progress-text {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 14px;
            color: #666;
            white-space: nowrap;
        }
        
        .quiz-content {
            min-height: 500px;
            position: relative;
        }
        
        .quiz-screen {
            display: none;
            padding: 40px 30px;
        }
        
        .quiz-screen.active {
            display: block;
        }
        
        .registration-content {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .registration-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .registration-header h3 {
            margin: 0 0 10px;
            font-size: 24px;
            color: #333;
        }
        
        .registration-header p {
            margin: 0;
            color: #666;
            font-size: 16px;
        }
        
        .registration-form {
            background: #fff;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .form-row .form-group.full-width {
            flex: 1 1 100%;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: #fff;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group input.error,
        .form-group select.error {
            border-color: #dc3545;
        }
        
        .field-error {
            margin-top: 5px;
            color: #dc3545;
            font-size: 12px;
            display: none;
        }
        
        .field-error.show {
            display: block;
        }
        
        .field-help {
            margin-top: 5px;
            color: #6c757d;
            font-size: 12px;
        }
        
        .form-actions {
            margin: 40px 0 20px;
            text-align: center;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-large {
            padding: 16px 32px;
            font-size: 16px;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-icon {
            margin-right: 8px;
        }
        
        .btn-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .form-footer {
            text-align: center;
            margin-top: 20px;
        }
        
        .privacy-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 0;
            color: #6c757d;
            font-size: 12px;
        }
        
        .question-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .question-header {
            margin-bottom: 30px;
        }
        
        .question-text {
            margin: 0 0 15px;
            font-size: 20px;
            line-height: 1.4;
            color: #333;
        }
        
        .question-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #6c757d;
        }
        
        .question-options {
            margin-bottom: 40px;
        }
        
        .option-item {
            padding: 16px 20px;
            margin-bottom: 12px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fff;
            position: relative;
        }
        
        .option-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
            transform: translateX(4px);
        }
        
        .option-item.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
            transform: translateX(4px);
        }
        
        .question-actions {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }
        
        .results-content {
            text-align: center;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .results-header {
            margin-bottom: 30px;
        }
        
        .results-header h3 {
            margin: 0 0 15px;
            font-size: 24px;
            color: #333;
        }
        
        .completion-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #28a745;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .score-display {
            margin: 40px 0;
        }
        
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: white;
            position: relative;
            box-shadow: 0 8px 32px rgba(79, 172, 254, 0.3);
        }
        
        .score-inner {
            display: flex;
            align-items: baseline;
            gap: 2px;
        }
        
        .score-number {
            font-size: 32px;
            font-weight: bold;
        }
        
        .score-divider {
            font-size: 24px;
            opacity: 0.8;
        }
        
        .score-total {
            font-size: 24px;
            font-weight: bold;
        }
        
        .score-percentage {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .results-details {
            margin: 30px 0;
        }
        
        .result-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .result-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .breakdown-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }
        
        .breakdown-label {
            display: block;
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .breakdown-value {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .gift-section {
            margin: 30px 0;
        }
        
        .gift-card {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
        }
        
        .gift-header h4 {
            margin: 0 0 10px;
            font-size: 20px;
            color: #333;
        }
        
        .gift-header p {
            margin: 0 0 20px;
            color: #666;
        }
        
        .gift-details {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .result-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .vefify-error, .vefify-notice {
            padding: 20px;
            margin: 20px;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
        }
        
        .vefify-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .vefify-notice {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .vefify-quiz-wrapper {
                margin: 10px;
                border-radius: 12px;
            }
            
            .quiz-header {
                padding: 30px 20px;
            }
            
            .quiz-title {
                font-size: 24px;
            }
            
            .quiz-meta {
                flex-direction: column;
                gap: 15px;
            }
            
            .quiz-screen {
                padding: 30px 20px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .progress-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .progress-text {
                flex-direction: column;
                gap: 5px;
            }
            
            .question-actions {
                flex-direction: column;
            }
            
            .result-actions {
                flex-direction: column;
            }
            
            .score-circle {
                width: 120px;
                height: 120px;
            }
            
            .score-number {
                font-size: 24px;
            }
            
            .score-total {
                font-size: 18px;
            }
        }
        
        @media (max-width: 480px) {
            .quiz-header {
                padding: 25px 15px;
            }
            
            .quiz-screen {
                padding: 25px 15px;
            }
            
            .quiz-progress {
                padding: 15px 20px;
            }
        }
        </style>';
    }
}

// Initialize the enhanced shortcode
add_action('plugins_loaded', function() {
    if (class_exists('Vefify_Quiz_Shortcode_Enhanced')) {
        Vefify_Quiz_Shortcode_Enhanced::get_instance();
    }
});
?>