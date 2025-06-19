<?php
/**
 * 🚀 FIXED ENHANCED SHORTCODES - Proper Form Handling
 * File: includes/class-enhanced-shortcodes.php
 * 
 * Fixed version that handles both AJAX and form submission properly
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Enhanced_Shortcodes extends Vefify_Quiz_Shortcodes {
    
    /**
     * 🎯 ENHANCED QUIZ SHORTCODE - FIXED FORM HANDLING
     */
    public function render_quiz($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'fields' => 'name,email,phone',
            'style' => 'default',
            'title' => '',
            'description' => '',
            'theme' => 'light'
        ), $atts);
        
        if (empty($atts['campaign_id'])) {
            return '<div class="vefify-error">❌ Error: Campaign ID is required. Usage: [vefify_quiz campaign_id="1"]</div>';
        }
        
        $campaign_id = intval($atts['campaign_id']);
        
        // Get campaign data
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return '<div class="vefify-error">❌ Campaign not found (ID: ' . $campaign_id . '). <a href="' . admin_url('admin.php?page=vefify-campaigns') . '">Check available campaigns</a></div>';
        }
        
        // Check if campaign is active
        if (!$this->is_campaign_active($campaign)) {
            $start_date = date('M j, Y', strtotime($campaign->start_date));
            $end_date = date('M j, Y', strtotime($campaign->end_date));
            return '<div class="vefify-notice">📅 This campaign is not currently active.<br>Active period: ' . $start_date . ' - ' . $end_date . '</div>';
        }
        
        // HANDLE FORM SUBMISSION - Process GET/POST data
        $registration_data = $this->process_registration_submission($campaign_id);
        if ($registration_data) {
            if ($registration_data['success']) {
                // Registration successful, show quiz
                return $this->render_quiz_interface($campaign, $registration_data['data']);
            } else {
                // Registration failed, show form with error
                return $this->render_registration_form($campaign, $atts, $registration_data['error']);
            }
        }
        
        // No submission, show registration form
        return $this->render_registration_form($campaign, $atts);
    }
    
    /**
     * 🔄 PROCESS REGISTRATION SUBMISSION - Handle GET/POST data
     */
    private function process_registration_submission($campaign_id) {
        // Check if form was submitted (via GET parameters)
        if (isset($_GET['campaign_id']) && isset($_GET['name']) && isset($_GET['phone'])) {
            
            // Verify nonce for security
            if (!wp_verify_nonce($_GET['vefify_nonce'], 'vefify_quiz_nonce')) {
                return array('success' => false, 'error' => 'Security check failed. Please try again.');
            }
            
            // Collect form data
            $form_data = array(
                'campaign_id' => intval($_GET['campaign_id']),
                'name' => sanitize_text_field($_GET['name']),
                'phone' => sanitize_text_field($_GET['phone']),
                'email' => sanitize_email($_GET['email'] ?? ''),
                'province' => sanitize_text_field($_GET['province'] ?? ''),
                'pharmacy_code' => sanitize_text_field($_GET['pharmacy_code'] ?? ''),
                'occupation' => sanitize_text_field($_GET['occupation'] ?? ''),
                'company' => sanitize_text_field($_GET['company'] ?? ''),
                'age' => intval($_GET['age'] ?? 0),
                'experience' => intval($_GET['experience'] ?? 0)
            );
            
            // Validate form data
            $validation = $this->validate_registration_data($form_data);
            if (!$validation['valid']) {
                return array('success' => false, 'error' => $validation['message']);
            }
            
            // Register participant
            $registration_result = $this->register_participant($form_data);
            
            if ($registration_result['success']) {
                return array('success' => true, 'data' => $registration_result['data']);
            } else {
                return array('success' => false, 'error' => $registration_result['error']);
            }
        }
        
        return null; // No submission
    }
    
    /**
     * 📝 REGISTER PARTICIPANT - Server-side registration
     */
    private function register_participant($form_data) {
        global $wpdb;
        
        try {
            // Check for existing participant
            $participants_table = $this->database->get_table_name('participants');
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$participants_table} 
                 WHERE campaign_id = %d AND phone_number = %s",
                $form_data['campaign_id'], $form_data['phone']
            ));
            
            if ($existing) {
                return array('success' => false, 'error' => 'Phone number already registered for this campaign');
            }
            
            // Prepare participant data
            $participant_data = array(
                'campaign_id' => $form_data['campaign_id'],
                'full_name' => $form_data['name'],
                'email' => $form_data['email'],
                'phone_number' => $form_data['phone'],
                'province' => $form_data['province'],
                'pharmacy_code' => $form_data['pharmacy_code'],
                'occupation' => $form_data['occupation'],
                'company' => $form_data['company'],
                'age' => $form_data['age'],
                'experience_years' => $form_data['experience'],
                'registration_ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => current_time('mysql')
            );
            
            // Insert participant
            $result = $wpdb->insert($participants_table, $participant_data);
            
            if ($result === false) {
                return array('success' => false, 'error' => 'Registration failed. Please try again.');
            }
            
            $participant_id = $wpdb->insert_id;
            
            // Generate session token
            $session_token = wp_generate_password(32, false);
            
            // Update with session token
            $wpdb->update(
                $participants_table,
                array('session_token' => $session_token),
                array('id' => $participant_id)
            );
            
            return array(
                'success' => true,
                'data' => array(
                    'participant_id' => $participant_id,
                    'session_token' => $session_token,
                    'participant_data' => $participant_data
                )
            );
            
        } catch (Exception $e) {
            error_log('Vefify Quiz Registration Error: ' . $e->getMessage());
            return array('success' => false, 'error' => 'Registration failed. Please try again.');
        }
    }
    
    /**
     * ✅ VALIDATE REGISTRATION DATA
     */
    private function validate_registration_data($data) {
        // Check required fields
        if (empty($data['name'])) {
            return array('valid' => false, 'message' => 'Please enter your full name');
        }
        
        if (empty($data['phone'])) {
            return array('valid' => false, 'message' => 'Please enter your phone number');
        }
        
        // Validate phone format (Vietnamese)
        $phone_clean = preg_replace('/\s/', '', $data['phone']);
        if (!preg_match('/^(0[0-9]{9}|84[0-9]{9})$/', $phone_clean)) {
            return array('valid' => false, 'message' => 'Phone number format: 0938474356 or 84938474356');
        }
        
        // Validate email if provided
        if (!empty($data['email']) && !is_email($data['email'])) {
            return array('valid' => false, 'message' => 'Please enter a valid email address');
        }
        
        // Validate pharmacy code format if provided
        if (!empty($data['pharmacy_code']) && !preg_match('/^[A-Z]{2}-[0-9]{6}$/', $data['pharmacy_code'])) {
            return array('valid' => false, 'message' => 'Pharmacy code must be in format XX-######');
        }
        
        return array('valid' => true);
    }
    
    /**
     * 📝 RENDER REGISTRATION FORM - FIXED FORM ACTION
     */
    private function render_registration_form($campaign, $atts, $error_message = null) {
        // Parse custom fields
        $requested_fields = array_map('trim', explode(',', $atts['fields']));
        $available_fields = $this->get_available_fields();
        $valid_fields = array_intersect($requested_fields, array_keys($available_fields));
        
        if (empty($valid_fields)) {
            return '<div class="vefify-error">❌ No valid fields specified. Available: ' . implode(', ', array_keys($available_fields)) . '</div>';
        }
        
        // Generate unique quiz ID
        $quiz_id = 'vefify_quiz_' . $campaign->id . '_' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($quiz_id); ?>" class="vefify-quiz-container vefify-style-<?php echo esc_attr($atts['style']); ?> vefify-theme-<?php echo esc_attr($atts['theme']); ?>" data-campaign-id="<?php echo $campaign->id; ?>">
            
            <!-- Quiz Header -->
            <div class="vefify-quiz-header">
                <h2 class="vefify-quiz-title">
                    <?php echo esc_html($atts['title'] ?: $campaign->name); ?>
                </h2>
                <?php if ($atts['description'] || $campaign->description): ?>
                    <div class="vefify-quiz-description">
                        <?php echo wp_kses_post($atts['description'] ?: $campaign->description); ?>
                    </div>
                <?php endif; ?>
                
                <div class="vefify-quiz-meta">
                    <span class="vefify-meta-item">📝 <?php echo $campaign->questions_per_quiz; ?> questions</span>
                    <?php if ($campaign->time_limit): ?>
                        <span class="vefify-meta-item">⏱️ <?php echo round($campaign->time_limit / 60); ?> minutes</span>
                    <?php endif; ?>
                    <span class="vefify-meta-item">🎯 Pass score: <?php echo $campaign->pass_score; ?></span>
                </div>
            </div>
            
            <!-- Error Message -->
            <?php if ($error_message): ?>
                <div class="vefify-error-message">
                    ❌ <?php echo esc_html($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Registration Form - FIXED: Proper form submission -->
            <div class="vefify-registration-section">
                <h3>📝 Registration Required</h3>
                <p>Please fill in your information to start the quiz:</p>
                
                <form method="get" action="" class="vefify-form">
                    <?php wp_nonce_field('vefify_quiz_nonce', 'vefify_nonce'); ?>
                    <input type="hidden" name="campaign_id" value="<?php echo $campaign->id; ?>">
                    
                    <div class="vefify-form-grid">
                        <?php foreach ($valid_fields as $field): ?>
                            <?php echo $this->render_form_field($field, $available_fields[$field]); ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="vefify-form-actions">
                        <button type="submit" class="vefify-btn vefify-btn-primary vefify-btn-large">
                            🚀 Start Quiz
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <style>
        .vefify-quiz-container {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        .vefify-quiz-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .vefify-quiz-title {
            margin: 0 0 10px;
            font-size: 24px;
            font-weight: 600;
        }
        
        .vefify-quiz-description {
            margin: 0 0 20px;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .vefify-quiz-meta {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 13px;
        }
        
        .vefify-registration-section {
            padding: 30px 20px;
        }
        
        .vefify-form-grid {
            display: grid;
            gap: 20px;
            margin: 20px 0;
        }
        
        .vefify-form-field {
            display: flex;
            flex-direction: column;
        }
        
        .vefify-field-label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .vefify-field-input {
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .vefify-field-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .vefify-field-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .vefify-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .vefify-btn-primary {
            background: #667eea;
            color: white;
        }
        
        .vefify-btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }
        
        .vefify-btn-large {
            padding: 15px 30px;
            font-size: 18px;
            width: 100%;
        }
        
        .vefify-error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin: 20px;
            border-radius: 6px;
            border: 1px solid #f5c6cb;
        }
        
        .vefify-error, .vefify-notice {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: 500;
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
        
        .required {
            color: #dc3545;
        }
        
        /* Mobile responsive */
        @media (max-width: 600px) {
            .vefify-quiz-container {
                margin: 10px;
                border-radius: 8px;
            }
            
            .vefify-quiz-meta {
                flex-direction: column;
                gap: 10px;
            }
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 🎮 RENDER QUIZ INTERFACE - After successful registration
     */
    private function render_quiz_interface($campaign, $registration_data) {
        // Get questions for this campaign
        $questions = $this->get_enhanced_quiz_questions($campaign->id, $campaign->questions_per_quiz);
        
        if (empty($questions)) {
            return '<div class="vefify-error">❌ No questions available for this campaign.</div>';
        }
        
        // Create quiz session
        $session_id = $this->create_quiz_session($registration_data['participant_id'], $questions);
        
        // Update participant status
        global $wpdb;
        $participants_table = $this->database->get_table_name('participants');
        $wpdb->update(
            $participants_table,
            array(
                'quiz_status' => 'started',
                'quiz_started_at' => current_time('mysql'),
                'quiz_session_id' => $session_id
            ),
            array('id' => $registration_data['participant_id'])
        );
        
        // Prepare questions for frontend (remove correct answers)
        $safe_questions = $this->prepare_questions_for_frontend($questions);
        
        ob_start();
        ?>
        <div class="vefify-quiz-container vefify-quiz-active">
            <!-- Quiz Header -->
            <div class="vefify-quiz-header">
                <h2>Welcome, <?php echo esc_html($registration_data['participant_data']['full_name']); ?>!</h2>
                <p>You're about to start the quiz. Good luck!</p>
            </div>
            
            <!-- Quiz Progress -->
            <div class="vefify-quiz-progress">
                <div class="vefify-progress-bar">
                    <div class="vefify-progress-fill" style="width: 0%"></div>
                </div>
                <div class="vefify-progress-text">Question <span class="current">1</span> of <span class="total"><?php echo count($safe_questions); ?></span></div>
            </div>
            
            <?php if ($campaign->time_limit): ?>
                <div class="vefify-timer">
                    <span class="vefify-timer-icon">⏱️</span>
                    <span class="vefify-timer-text">Time remaining: <span id="vefify-time-remaining"><?php echo round($campaign->time_limit / 60); ?>:00</span></span>
                </div>
            <?php endif; ?>
            
            <!-- Question Container -->
            <div id="vefify-question-container">
                <!-- Questions will be loaded here -->
            </div>
            
            <!-- Navigation -->
            <div class="vefify-quiz-navigation">
                <button type="button" id="vefify-prev-question" class="vefify-btn vefify-btn-secondary" style="display: none;">
                    ← Previous
                </button>
                <button type="button" id="vefify-next-question" class="vefify-btn vefify-btn-primary">
                    Next →
                </button>
                <button type="button" id="vefify-finish-quiz" class="vefify-btn vefify-btn-success" style="display: none;">
                    ✓ Finish Quiz
                </button>
            </div>
            
            <!-- Results Section -->
            <div class="vefify-results-section" style="display: none;">
                <!-- Results will be displayed here -->
            </div>
        </div>
        
        <script>
        // Initialize quiz with data
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof VefifyQuiz !== 'undefined') {
                VefifyQuiz.initializeWithData({
                    participantId: <?php echo json_encode($registration_data['participant_id']); ?>,
                    sessionToken: <?php echo json_encode($registration_data['session_token']); ?>,
                    sessionId: <?php echo json_encode($session_id); ?>,
                    campaignId: <?php echo json_encode($campaign->id); ?>,
                    questions: <?php echo json_encode($safe_questions); ?>,
                    timeLimit: <?php echo json_encode(intval($campaign->time_limit)); ?>,
                    passScore: <?php echo json_encode(intval($campaign->pass_score)); ?>
                });
            } else {
                console.error('VefifyQuiz JavaScript not loaded');
            }
        });
        </script>
        
        <style>
        .vefify-quiz-progress {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .vefify-progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .vefify-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
            transition: width 0.3s ease;
        }
        
        .vefify-progress-text {
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        
        .vefify-timer {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .vefify-quiz-navigation {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .vefify-btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .vefify-btn-success {
            background: #28a745;
            color: white;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    // Keep all your other existing methods from the enhanced shortcodes...
    
    /**
     * 🎨 RENDER FORM FIELD - KEEP EXISTING
     */
    private function render_form_field($field_key, $field_config) {
        $required = $field_config['required'] ? 'required' : '';
        $required_star = $field_config['required'] ? ' <span class="required">*</span>' : '';
        
        ob_start();
        ?>
        <div class="vefify-form-field vefify-field-<?php echo esc_attr($field_key); ?>">
            <label for="vefify_<?php echo esc_attr($field_key); ?>" class="vefify-field-label">
                <?php echo esc_html($field_config['label']); ?><?php echo $required_star; ?>
            </label>
            
            <?php if ($field_config['type'] === 'select'): ?>
                <select name="<?php echo esc_attr($field_key); ?>" id="vefify_<?php echo esc_attr($field_key); ?>" class="vefify-field-input" <?php echo $required; ?>>
                    <option value="">Select <?php echo esc_html($field_config['label']); ?></option>
                    <?php foreach ($field_config['options'] as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($field_config['type'] === 'textarea'): ?>
                <textarea name="<?php echo esc_attr($field_key); ?>" id="vefify_<?php echo esc_attr($field_key); ?>" class="vefify-field-input" placeholder="<?php echo esc_attr($field_config['placeholder']); ?>" <?php echo $required; ?>></textarea>
            <?php else: ?>
                <input type="<?php echo esc_attr($field_config['type']); ?>" 
                       name="<?php echo esc_attr($field_key); ?>" 
                       id="vefify_<?php echo esc_attr($field_key); ?>" 
                       class="vefify-field-input" 
                       placeholder="<?php echo esc_attr($field_config['placeholder']); ?>"
                       <?php if (isset($field_config['pattern'])): ?>pattern="<?php echo esc_attr($field_config['pattern']); ?>"<?php endif; ?>
                       <?php echo $required; ?>>
            <?php endif; ?>
            
            <?php if (isset($field_config['help'])): ?>
                <div class="vefify-field-help"><?php echo esc_html($field_config['help']); ?></div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 📝 GET AVAILABLE FORM FIELDS - KEEP EXISTING
     */
    private function get_available_fields() {
        return array(
            'name' => array(
                'label' => 'Full Name',
                'type' => 'text',
                'placeholder' => 'Enter your full name',
                'required' => true
            ),
            'email' => array(
                'label' => 'Email Address',
                'type' => 'email',
                'placeholder' => 'Enter your email',
                'required' => false
            ),
            'phone' => array(
                'label' => 'Phone Number',
                'type' => 'tel',
                'placeholder' => '0938474356',
                'pattern' => '^(0[0-9]{9}|84[0-9]{9})$',
                'required' => true,
                'help' => 'Format: 0938474356 or 84938474356'
            ),
            'province' => array(
                'label' => 'Province/City',
                'type' => 'select',
                'required' => false,
                'options' => array(
                    'hanoi' => 'Hanoi',
                    'hcmc' => 'Ho Chi Minh City',
                    'danang' => 'Da Nang',
                    'haiphong' => 'Hai Phong',
                    'cantho' => 'Can Tho',
                    'other' => 'Other'
                )
            ),
            'pharmacy_code' => array(
                'label' => 'Pharmacy Code',
                'type' => 'text',
                'placeholder' => 'XX-123456',
                'pattern' => '[A-Z]{2}-[0-9]{6}',
                'required' => false,
                'help' => 'Format: XX-######'
            ),
            'occupation' => array(
                'label' => 'Occupation',
                'type' => 'select',
                'required' => false,
                'options' => array(
                    'pharmacist' => 'Pharmacist',
                    'doctor' => 'Doctor',
                    'nurse' => 'Nurse',
                    'student' => 'Student',
                    'other' => 'Other'
                )
            ),
            'company' => array(
                'label' => 'Company/Organization',
                'type' => 'text',
                'placeholder' => 'Enter company name',
                'required' => false
            ),
            'age' => array(
                'label' => 'Age',
                'type' => 'number',
                'placeholder' => '25',
                'required' => false
            ),
            'experience' => array(
                'label' => 'Years of Experience',
                'type' => 'number',
                'placeholder' => '5',
                'required' => false
            )
        );
    }
    
    // Include all other methods from the enhanced shortcodes class...
    // (get_campaign, is_campaign_active, get_enhanced_quiz_questions, etc.)
}

// Initialize the enhanced shortcode system
if (class_exists('Vefify_Quiz_Shortcodes')) {
    // This will be loaded automatically by the plugin
    error_log('Vefify Quiz: Enhanced Shortcodes class loaded successfully');
}