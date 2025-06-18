<?php
/**
 * FIXED: Unique Shortcode Class (No Conflicts)
 * File: frontend/class-quiz-shortcode.php
 * 
 * This is the ONLY shortcode class in the system
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure no class conflicts
if (class_exists('Vefify_Quiz_Shortcode')) {
    return; // Class already exists, skip
}

class Vefify_Quiz_Shortcode {
    
    private $plugin;
    private $database;
    
    public function __construct() {
        // Get plugin instance safely
        if (function_exists('vefify_quiz')) {
            $this->plugin = vefify_quiz();
            $this->database = $this->plugin->get_database();
        }
        
        // Remove any existing shortcodes to prevent conflicts
        remove_shortcode('vefify_quiz');
        
        // Register OUR shortcode
        add_shortcode('vefify_quiz', array($this, 'render_quiz'));
        
        // Enqueue assets when needed
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
        
        // AJAX handlers
        $this->init_ajax_handlers();
    }
    
    /**
     * Initialize AJAX handlers
     */
    private function init_ajax_handlers() {
        add_action('wp_ajax_vefify_check_participation', array($this, 'ajax_check_participation'));
        add_action('wp_ajax_nopriv_vefify_check_participation', array($this, 'ajax_check_participation'));
        
        add_action('wp_ajax_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        add_action('wp_ajax_nopriv_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        
        add_action('wp_ajax_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
        add_action('wp_ajax_nopriv_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
    }
    
    /**
     * Main shortcode renderer - FIXED VERSION
     */
    public function render_quiz($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'campaign_id' => 1,
            'template' => 'mobile'
        ), $atts);
        
        // Debug mode check
        if (current_user_can('manage_options') && isset($_GET['debug'])) {
            return '<div style="background:green;color:white;padding:15px;border-radius:8px;margin:20px 0;text-align:center;">
                ✅ <strong>CORRECT Vefify Shortcode Active</strong><br>
                Campaign ID: ' . esc_html($atts['campaign_id']) . '<br>
                Template: ' . esc_html($atts['template']) . '<br>
                Database: ' . ($this->database ? '✅ Connected' : '❌ Not Connected') . '
            </div>';
        }
        
        // Get campaign data
        try {
            $campaign = $this->get_campaign_data($atts['campaign_id']);
            if (!$campaign) {
                return $this->render_error('Campaign not found or inactive', $atts['campaign_id']);
            }
            
            // Enqueue assets
            $this->enqueue_quiz_assets();
            
            // Render template
            return $this->render_mobile_template($campaign, $atts);
            
        } catch (Exception $e) {
            error_log('Vefify Quiz Shortcode Error: ' . $e->getMessage());
            return $this->render_error('An error occurred while loading the quiz');
        }
    }
    
    /**
     * Render mobile template - CORRECT VIETNAM FORM
     */
    private function render_mobile_template($campaign, $atts) {
        ob_start();
        ?>
        <!-- Vefify Quiz Mobile Interface - CORRECT VERSION -->
        <div class="vefify-quiz-wrapper" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
            <div class="vefify-container">
                <!-- Header -->
                <div class="vefify-header">
                    <h1 class="quiz-title">🎯 <?php echo esc_html($campaign->name); ?></h1>
                    <p class="quiz-description"><?php echo esc_html($campaign->description); ?></p>
                    <div class="quiz-info">
                        <span class="info-item">📝 <?php echo $campaign->questions_per_quiz; ?> questions</span>
                        <span class="info-item">🎯 <?php echo $campaign->pass_score; ?> to pass</span>
                        <?php if ($campaign->time_limit): ?>
                        <span class="info-item">⏱️ <?php echo floor($campaign->time_limit/60); ?> minutes</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div class="vefify-progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                    <div class="progress-text" id="progressText">Ready to Start</div>
                </div>

                <!-- CORRECT Registration Form -->
                <div class="vefify-section" id="registrationForm">
                    <div class="form-header">
                        <h2>📋 Your Information</h2>
                        <p>Please fill in your details to start the quiz</p>
                    </div>
                    
                    <form id="userForm" class="vefify-form">
                        <div class="form-group">
                            <label class="form-label" for="fullName">
                                <span class="label-text">Full Name *</span>
                                <span class="label-icon">👤</span>
                            </label>
                            <input type="text" id="fullName" name="full_name" class="form-input" 
                                   placeholder="Enter your full name" required>
                            <div class="error-message" id="nameError">Please enter your full name</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="phoneNumber">
                                <span class="label-text">Phone Number *</span>
                                <span class="label-icon">📱</span>
                            </label>
                            <input type="tel" id="phoneNumber" name="phone_number" class="form-input" 
                                   placeholder="0901234567" required pattern="0[3-9][0-9]{8}">
                            <div class="error-message" id="phoneError">Please enter a valid Vietnamese phone number</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="province">
                                <span class="label-text">Province/City *</span>
                                <span class="label-icon">📍</span>
                            </label>
                            <select id="province" name="province" class="form-select" required>
                                <option value="">Select your province/city</option>
                                <?php echo $this->render_vietnam_provinces(); ?>
                            </select>
                            <div class="error-message" id="provinceError">Please select your province/city</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="pharmacyCode">
                                <span class="label-text">Pharmacy Code</span>
                                <span class="label-icon">🏥</span>
                            </label>
                            <input type="text" id="pharmacyCode" name="pharmacy_code" class="form-input" 
                                   placeholder="Optional">
                        </div>

                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">
                                <span class="btn-text">Start Quiz</span>
                                <span class="btn-icon">→</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Loading State -->
                <div class="vefify-section loading-section" id="loadingState" style="display: none;">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                    </div>
                    <div class="loading-text">
                        <h3>🔄 Loading Questions...</h3>
                        <p>Please wait while we prepare your quiz</p>
                    </div>
                </div>

                <!-- Quiz Container -->
                <div class="vefify-section" id="quizContainer" style="display: none;">
                    <div class="question-header">
                        <div class="question-counter" id="questionCounter">Question 1 of <?php echo $campaign->questions_per_quiz; ?></div>
                        <?php if ($campaign->time_limit): ?>
                        <div class="question-timer" id="questionTimer">⏱️ <span id="timeRemaining">--:--</span></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="question-content">
                        <h3 class="question-title" id="questionTitle">Loading question...</h3>
                        <div class="answers-container" id="answersContainer">
                            <!-- Dynamic answers will be loaded here -->
                        </div>
                    </div>

                    <div class="quiz-navigation">
                        <button type="button" class="btn btn-secondary" id="prevBtn" disabled>
                            <span class="btn-icon">←</span>
                            <span class="btn-text">Previous</span>
                        </button>
                        <button type="button" class="btn btn-primary" id="nextBtn">
                            <span class="btn-text">Next</span>
                            <span class="btn-icon">→</span>
                        </button>
                        <button type="button" class="btn btn-success" id="submitBtn" style="display: none;">
                            <span class="btn-text">Submit Quiz</span>
                            <span class="btn-icon">✓</span>
                        </button>
                    </div>
                </div>

                <!-- Result Container -->
                <div class="vefify-section result-section" id="resultContainer" style="display: none;">
                    <div class="result-header">
                        <div class="result-icon" id="resultIcon">🎉</div>
                        <h2 class="result-title" id="resultTitle">Quiz Completed!</h2>
                    </div>
                    
                    <div class="result-content">
                        <div class="score-display">
                            <div class="score-circle">
                                <div class="score-number" id="resultScore">5/5</div>
                                <div class="score-percentage" id="resultPercentage">100%</div>
                            </div>
                        </div>
                        
                        <div class="result-message" id="resultMessage">
                            Congratulations! You've successfully completed the quiz.
                        </div>
                        
                        <!-- Gift section (if applicable) -->
                        <div class="reward-section" id="rewardSection" style="display: none;">
                            <div class="reward-card">
                                <div class="reward-header">
                                    <div class="reward-icon">🎁</div>
                                    <h3>You've Won a Prize!</h3>
                                </div>
                                <div class="reward-details">
                                    <div class="reward-name" id="rewardName">Special Gift</div>
                                    <div class="reward-code" id="rewardCode">GIFT123</div>
                                    <div class="reward-description" id="rewardDescription">Description</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="result-actions">
                        <button type="button" class="btn btn-primary" onclick="location.reload()">
                            <span class="btn-icon">🔄</span>
                            <span class="btn-text">Take Another Quiz</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Notification Popup -->
            <div class="vefify-popup" id="notificationPopup" style="display: none;">
                <div class="popup-content">
                    <div class="popup-icon" id="popupIcon">ℹ️</div>
                    <div class="popup-message" id="popupMessage">Notification message</div>
                    <div class="popup-actions">
                        <button class="btn btn-primary" onclick="this.closest('.vefify-popup').style.display='none'">OK</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Vietnam provinces - CORRECT DATA
     */
    private function render_vietnam_provinces() {
        // Use utilities if available
        if (class_exists('Vefify_Quiz_Utilities') && method_exists('Vefify_Quiz_Utilities', 'get_vietnam_provinces')) {
            $provinces = Vefify_Quiz_Utilities::get_vietnam_provinces();
            $output = '';
            
            foreach ($provinces as $key => $name) {
                $output .= '<option value="' . esc_attr($key) . '">' . esc_html($name) . '</option>';
            }
            
            return $output;
        }
        
        // Fallback with key provinces
        return '
            <option value="hanoi">Hanoi</option>
            <option value="hcm">Ho Chi Minh City</option>
            <option value="danang">Da Nang</option>
            <option value="haiphong">Hai Phong</option>
            <option value="cantho">Can Tho</option>
            <option value="angiang">An Giang</option>
            <option value="bacgiang">Bac Giang</option>
            <option value="bentre">Ben Tre</option>
            <option value="binhduong">Binh Duong</option>
            <option value="dongnai">Dong Nai</option>
        ';
    }
    
    /**
     * Get campaign data safely
     */
    private function get_campaign_data($campaign_id) {
        if (!$this->database) {
            // Return mock data for testing
            return (object) array(
                'id' => $campaign_id,
                'name' => 'Thảo Test Module 1',
                'description' => 'Test your knowledge with our interactive quiz',
                'questions_per_quiz' => 3,
                'time_limit' => 600,
                'pass_score' => 3,
                'is_active' => 1
            );
        }
        
        global $wpdb;
        $table_name = $this->database->get_table_name('campaigns');
        
        if (!$table_name) {
            return null;
        }
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d AND is_active = 1",
            $campaign_id
        ));
    }
    
    /**
     * Render error message
     */
    private function render_error($message, $campaign_id = null) {
        return '<div style="background:#f8d7da;color:#721c24;padding:20px;border-radius:8px;text-align:center;margin:20px 0;">
            <h3>❌ Quiz Not Available</h3>
            <p>' . esc_html($message) . '</p>
            ' . ($campaign_id ? '<p><small>Campaign ID: ' . esc_html($campaign_id) . '</small></p>' : '') . '
            <button onclick="location.reload()" style="background:#007cba;color:white;border:none;padding:10px 20px;border-radius:4px;cursor:pointer;">Retry</button>
        </div>';
    }
    
    /**
     * Enqueue quiz assets
     */
    private function enqueue_quiz_assets() {
        // CSS
        wp_enqueue_style(
            'vefify-quiz-mobile',
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/quiz-mobile.css',
            array(),
            VEFIFY_QUIZ_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'vefify-quiz-mobile',
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/quiz-mobile.js',
            array('jquery'),
            VEFIFY_QUIZ_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('vefify-quiz-mobile', 'vefifyQuiz', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_quiz_nonce'),
            'restUrl' => rest_url('vefify/v1/'),
            'pluginUrl' => VEFIFY_QUIZ_PLUGIN_URL,
            'strings' => array(
                'loading' => 'Loading...',
                'error' => 'An error occurred. Please try again.',
                'success' => 'Success!',
                'confirm_submit' => 'Are you sure you want to submit your answers?'
            )
        ));
    }
    
    /**
     * Maybe enqueue assets
     */
    public function maybe_enqueue_assets() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vefify_quiz')) {
            $this->enqueue_quiz_assets();
        }
    }
    
    /**
     * AJAX Handlers
     */
    public function ajax_check_participation() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        wp_send_json_success(array('can_participate' => true, 'message' => 'You can participate'));
    }
    
	public function ajax_start_quiz() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['vefify_nonce'] ?? '', 'vefify_quiz_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    try {
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        
        // Sanitize form data
        $form_data = array(
            'full_name' => sanitize_text_field($_POST['full_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone_number' => sanitize_text_field($_POST['phone_number'] ?? ''),
            'province' => sanitize_text_field($_POST['province'] ?? ''),
            'pharmacist_code' => sanitize_text_field($_POST['pharmacist_code'] ?? ''),
            'company' => sanitize_text_field($_POST['company'] ?? '')
        );
        
        // Validate required fields
        $errors = array();
        if (empty($form_data['full_name'])) {
            $errors[] = 'Full name is required';
        }
        if (empty($form_data['email']) || !is_email($form_data['email'])) {
            $errors[] = 'Valid email is required';
        }
        if (empty($form_data['phone_number'])) {
            $errors[] = 'Phone number is required';
        }
        if (empty($form_data['province'])) {
            $errors[] = 'Province selection is required';
        }
        
        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => 'Please correct the following errors:',
                'errors' => $errors
            ));
            return;
        }
        
        // Check if phone already exists for this campaign
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}vefify_participants 
             WHERE phone_number = %s AND campaign_id = %d",
            $form_data['phone_number'],
            $campaign_id
        ));
        
        if ($existing) {
            wp_send_json_error('Phone number already registered for this campaign');
            return;
        }
        
        // Create participant record
        $participant_data = array_merge($form_data, array(
            'campaign_id' => $campaign_id,
            'session_id' => wp_generate_uuid4(),
            'quiz_status' => 'registered',
            'created_at' => current_time('mysql')
        ));
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'vefify_participants',
            $participant_data
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to register. Please try again.');
            return;
        }
        
        $participant_id = $wpdb->insert_id;
        
        // Return success with quiz start data
        wp_send_json_success(array(
            'participant_id' => $participant_id,
            'session_id' => $participant_data['session_id'],
            'message' => 'Registration successful! Starting quiz...',
            'redirect_to_quiz' => true
        ));
        
    } catch (Exception $e) {
        error_log('Quiz start error: ' . $e->getMessage());
        wp_send_json_error('An error occurred. Please try again.');
    }
}	
    
    public function ajax_submit_quiz() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        wp_send_json_success(array('score' => 5, 'total_questions' => 5, 'percentage' => 100));
    }
	/**
 * Render province options for Vietnam
 */
	private function render_province_options() {
		$provinces = array(
			'Ho Chi Minh' => 'Ho Chi Minh City',
			'Ha Noi' => 'Hanoi',
			'Da Nang' => 'Da Nang',
			'Hai Phong' => 'Hai Phong',
			'Can Tho' => 'Can Tho',
			'An Giang' => 'An Giang',
			'Ba Ria Vung Tau' => 'Ba Ria - Vung Tau',
			'Bac Giang' => 'Bac Giang',
			'Bac Kan' => 'Bac Kan',
			'Bac Lieu' => 'Bac Lieu',
			'Bac Ninh' => 'Bac Ninh',
			'Ben Tre' => 'Ben Tre',
			'Binh Dinh' => 'Binh Dinh',
			'Binh Duong' => 'Binh Duong',
			'Binh Phuoc' => 'Binh Phuoc',
			'Binh Thuan' => 'Binh Thuan',
			'Ca Mau' => 'Ca Mau',
			'Cao Bang' => 'Cao Bang',
			'Dak Lak' => 'Dak Lak',
			'Dak Nong' => 'Dak Nong',
			'Dien Bien' => 'Dien Bien',
			'Dong Nai' => 'Dong Nai',
			'Dong Thap' => 'Dong Thap',
			'Gia Lai' => 'Gia Lai',
			'Ha Giang' => 'Ha Giang',
			'Ha Nam' => 'Ha Nam',
			'Ha Tinh' => 'Ha Tinh',
			'Hai Duong' => 'Hai Duong',
			'Hau Giang' => 'Hau Giang',
			'Hoa Binh' => 'Hoa Binh',
			'Hung Yen' => 'Hung Yen',
			'Khanh Hoa' => 'Khanh Hoa',
			'Kien Giang' => 'Kien Giang',
			'Kon Tum' => 'Kon Tum',
			'Lai Chau' => 'Lai Chau',
			'Lam Dong' => 'Lam Dong',
			'Lang Son' => 'Lang Son',
			'Lao Cai' => 'Lao Cai',
			'Long An' => 'Long An',
			'Nam Dinh' => 'Nam Dinh',
			'Nghe An' => 'Nghe An',
			'Ninh Binh' => 'Ninh Binh',
			'Ninh Thuan' => 'Ninh Thuan',
			'Phu Tho' => 'Phu Tho',
			'Phu Yen' => 'Phu Yen',
			'Quang Binh' => 'Quang Binh',
			'Quang Nam' => 'Quang Nam',
			'Quang Ngai' => 'Quang Ngai',
			'Quang Ninh' => 'Quang Ninh',
			'Quang Tri' => 'Quang Tri',
			'Soc Trang' => 'Soc Trang',
			'Son La' => 'Son La',
			'Tay Ninh' => 'Tay Ninh',
			'Thai Binh' => 'Thai Binh',
			'Thai Nguyen' => 'Thai Nguyen',
			'Thanh Hoa' => 'Thanh Hoa',
			'Thua Thien Hue' => 'Thua Thien Hue',
			'Tien Giang' => 'Tien Giang',
			'Tra Vinh' => 'Tra Vinh',
			'Tuyen Quang' => 'Tuyen Quang',
			'Vinh Long' => 'Vinh Long',
			'Vinh Phuc' => 'Vinh Phuc',
			'Yen Bai' => 'Yen Bai'
		);
		
		$options = '';
		foreach ($provinces as $value => $label) {
			$options .= '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
		}
		
		return $options;
	}
	// Load province and Pharmacist code
?>
<div class="vefify-form-group">
    <label for="province" class="vefify-form-label required">
        <span class="label-text">Province/City</span>
        <span class="required-mark">*</span>
    </label>
    <div class="vefify-input-wrapper">
        <select id="province" name="province" class="vefify-form-select" required>
            <option value="">Select your province/city</option>
            <?php echo $this->render_province_options(); ?>
        </select>
        <div class="vefify-select-arrow">
            <span class="dashicons dashicons-arrow-down-alt2"></span>
        </div>
    </div>
    <div class="vefify-form-feedback" id="province_feedback"></div>
</div>

<!-- Pharmacist Code field (find and replace your existing pharmacist field) -->
<div class="vefify-form-group">
    <label for="pharmacistCode" class="vefify-form-label">
        <span class="label-text">Pharmacist License Code</span>
        <span class="optional-mark">(Optional)</span>
    </label>
    <div class="vefify-input-wrapper">
        <input type="text" 
               id="pharmacistCode" 
               name="pharmacist_code" 
               class="vefify-form-input" 
               placeholder="e.g., PH123456 (6-12 characters)"
               pattern="[A-Z0-9]{6,12}"
               style="text-transform: uppercase;"
               maxlength="12">
        <div class="vefify-input-icon">
            <span class="dashicons dashicons-id-alt"></span>
        </div>
    </div>
    <div class="vefify-form-feedback" id="pharmacistCode_feedback"></div>
    <small class="vefify-form-help">Optional: Enter your 6-12 character pharmacist license code</small>
</div>

<?php
}