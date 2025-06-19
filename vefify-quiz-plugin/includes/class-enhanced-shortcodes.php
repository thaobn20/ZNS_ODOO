<?php
/**
 * 🚀 COMPLETE ENHANCED SHORTCODES FIX
 * File: includes/class-enhanced-shortcodes.php
 * 
 * FIXES:
 * 1. ✅ Error resolved (visibility fix)
 * 2. ✅ CSS loading (proper enqueuing + inline fallback)
 * 3. ✅ Form submission (fixed GET/POST processing)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Enhanced_Shortcodes extends Vefify_Quiz_Shortcodes {
    
    private static $css_loaded = false;
    
    public function __construct() {
        parent::__construct();
        add_action('wp_enqueue_scripts', array($this, 'enqueue_quiz_styles'));
    }
    
    /**
     * 🎨 FIX #2: ENSURE CSS LOADING
     */
    public function enqueue_quiz_styles() {
        if (!self::$css_loaded) {
            wp_add_inline_style('wp-block-library', $this->get_quiz_css());
            self::$css_loaded = true;
        }
    }
    
    /**
     * 🎯 MAIN QUIZ SHORTCODE - COMPLETELY FIXED
     */
    public function render_quiz($atts) {
        // Force load CSS if not already loaded
        if (!self::$css_loaded) {
            add_action('wp_footer', array($this, 'output_css_inline'));
            self::$css_loaded = true;
        }
        
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'fields' => 'name,phone,province,pharmacy_code',
            'style' => 'default',
            'title' => '',
            'description' => '',
            'theme' => 'light'
        ), $atts);
        
        if (empty($atts['campaign_id'])) {
            return $this->render_error('Campaign ID is required. Usage: [vefify_quiz campaign_id="1"]');
        }
        
        $campaign_id = intval($atts['campaign_id']);
        
        // Get campaign data using the protected method (now accessible)
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return $this->render_error('Campaign not found (ID: ' . $campaign_id . ')');
        }
        
        // Check if campaign is active using the protected method
        if (!$this->is_campaign_active($campaign)) {
            $start_date = date('M j, Y', strtotime($campaign->start_date));
            $end_date = date('M j, Y', strtotime($campaign->end_date));
            return $this->render_notice('This campaign is not currently active. Active period: ' . $start_date . ' - ' . $end_date);
        }
        
        // FIX #3: PROPER FORM SUBMISSION HANDLING
        $registration_result = $this->handle_form_submission($campaign_id);
        
        if ($registration_result) {
            if ($registration_result['success']) {
                // Registration successful - show quiz interface
                return $this->render_quiz_interface($campaign, $registration_result['data']);
            } else {
                // Registration failed - show form with error
                return $this->render_registration_form($campaign, $atts, $registration_result['error']);
            }
        }
        
        // No submission - show registration form
        return $this->render_registration_form($campaign, $atts);
    }
    
    /**
     * 🔄 FIX #3: PROPER FORM SUBMISSION HANDLING
     */
    private function handle_form_submission($campaign_id) {
        // Check if this is a form submission
        if (!isset($_GET['campaign_id']) || !isset($_GET['name']) || !isset($_GET['phone'])) {
            return null; // No submission
        }
        
        // Verify this is for the correct campaign
        if (intval($_GET['campaign_id']) !== $campaign_id) {
            return null; // Different campaign
        }
        
        // Verify nonce
        if (!isset($_GET['vefify_nonce']) || !wp_verify_nonce($_GET['vefify_nonce'], 'vefify_quiz_nonce')) {
            return array('success' => false, 'error' => 'Security check failed. Please try again.');
        }
        
        // Sanitize form data
        $form_data = array(
            'campaign_id' => $campaign_id,
            'name' => sanitize_text_field($_GET['name']),
            'email' => sanitize_email($_GET['email'] ?? ''),
            'phone' => sanitize_text_field($_GET['phone']),
            'company' => sanitize_text_field($_GET['company'] ?? ''),
            'province' => sanitize_text_field($_GET['province'] ?? ''),
            'pharmacy_code' => sanitize_text_field($_GET['pharmacy_code'] ?? ''),
            'occupation' => sanitize_text_field($_GET['occupation'] ?? ''),
            'age' => intval($_GET['age'] ?? 0)
        );
        
        // Validate required fields
        if (empty($form_data['name'])) {
            return array('success' => false, 'error' => 'Please enter your full name.');
        }
        
        if (empty($form_data['phone'])) {
            return array('success' => false, 'error' => 'Please enter your phone number.');
        }
        
        // Validate phone format
        $phone_clean = preg_replace('/[^0-9]/', '', $form_data['phone']);
        if (strlen($phone_clean) < 10) {
            return array('success' => false, 'error' => 'Please enter a valid phone number.');
        }
        
        // Validate email if provided
        if (!empty($form_data['email']) && !is_email($form_data['email'])) {
            return array('success' => false, 'error' => 'Please enter a valid email address.');
        }
        
        // Register participant
        return $this->register_participant($form_data);
    }
    
    /**
     * 📝 REGISTER PARTICIPANT IN DATABASE
     */
    private function register_participant($form_data) {
        global $wpdb;
        
        try {
            // Check if database connection exists
            if (!$this->database) {
                return array('success' => false, 'error' => 'Database connection failed.');
            }
            
            $table_name = $wpdb->prefix . 'vefify_quiz_users';
            
            // Check for existing registration
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE campaign_id = %d AND phone = %s",
                $form_data['campaign_id'],
                $form_data['phone']
            ));
            
            if ($existing) {
                return array('success' => false, 'error' => 'This phone number is already registered for this campaign.');
            }
            
            // Prepare participant data
            $participant_data = array(
                'campaign_id' => $form_data['campaign_id'],
                'name' => $form_data['name'],
                'email' => $form_data['email'],
                'phone' => $form_data['phone'],
                'company' => $form_data['company'],
                'province' => $form_data['province'],
                'pharmacy_code' => $form_data['pharmacy_code'],
                'occupation' => $form_data['occupation'],
                'age' => $form_data['age'],
                'registered_at' => current_time('mysql'),
                'quiz_status' => 'registered'
            );
            
            // Insert participant
            $result = $wpdb->insert($table_name, $participant_data);
            
            if ($result === false) {
                return array('success' => false, 'error' => 'Registration failed. Please try again.');
            }
            
            $participant_id = $wpdb->insert_id;
            $participant_data['id'] = $participant_id;
            
            return array(
                'success' => true,
                'data' => array(
                    'participant_id' => $participant_id,
                    'participant_data' => $participant_data
                )
            );
            
        } catch (Exception $e) {
            error_log('Vefify Quiz Registration Error: ' . $e->getMessage());
            return array('success' => false, 'error' => 'Registration failed due to a system error.');
        }
    }
    
    /**
     * 📝 RENDER REGISTRATION FORM
     */
    private function render_registration_form($campaign, $atts, $error_message = '') {
        $fields = explode(',', $atts['fields']);
        $form_fields = $this->get_form_field_definitions();
        
        ob_start();
        ?>
        <div class="vefify-quiz-container">
            <div class="vefify-quiz-header">
                <h2><?php echo esc_html($atts['title'] ?: $campaign->name); ?></h2>
                <?php if ($atts['description'] || $campaign->description): ?>
                    <p class="vefify-description"><?php echo esc_html($atts['description'] ?: $campaign->description); ?></p>
                <?php endif; ?>
                
                <div class="vefify-quiz-meta">
                    <span class="vefify-meta-item">📝 <?php echo $campaign->questions_per_quiz; ?> questions</span>
                    <?php if ($campaign->time_limit): ?>
                        <span class="vefify-meta-item">⏱️ <?php echo round($campaign->time_limit / 60); ?> minutes</span>
                    <?php endif; ?>
                    <span class="vefify-meta-item">🎯 Pass score: <?php echo $campaign->pass_score; ?></span>
                </div>
            </div>
            
            <?php if ($error_message): ?>
                <div class="vefify-error"><?php echo esc_html($error_message); ?></div>
            <?php endif; ?>
            
            <div class="vefify-form-container">
                <h3>📝 Please fill in your information to start the quiz:</h3>
                
                <form method="GET" action="" class="vefify-form">
                    <?php wp_nonce_field('vefify_quiz_nonce', 'vefify_nonce'); ?>
                    <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->id); ?>">
                    
                    <?php foreach ($fields as $field_key): ?>
                        <?php $field_key = trim($field_key); ?>
                        <?php if (isset($form_fields[$field_key])): ?>
                            <?php $field = $form_fields[$field_key]; ?>
                            <div class="vefify-field">
                                <label for="<?php echo esc_attr($field_key); ?>">
                                    <?php echo esc_html($field['label']); ?>
                                    <?php if ($field['required']): ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                </label>
                                
                                <?php if ($field['type'] === 'select'): ?>
                                    <select name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" <?php echo $field['required'] ? 'required' : ''; ?>>
                                        <option value="">-- Select <?php echo esc_html($field['label']); ?> --</option>
                                        <?php foreach ($field['options'] as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($_GET[$field_key] ?? '', $value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input 
                                        type="<?php echo esc_attr($field['type']); ?>"
                                        name="<?php echo esc_attr($field_key); ?>"
                                        id="<?php echo esc_attr($field_key); ?>"
                                        value="<?php echo esc_attr($_GET[$field_key] ?? ''); ?>"
                                        placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
                                        <?php echo isset($field['pattern']) ? 'pattern="' . esc_attr($field['pattern']) . '"' : ''; ?>
                                        <?php echo $field['required'] ? 'required' : ''; ?>
                                    >
                                <?php endif; ?>
                                
                                <?php if (isset($field['help'])): ?>
                                    <small class="field-help"><?php echo esc_html($field['help']); ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div class="vefify-form-actions">
                        <button type="submit" class="vefify-btn vefify-btn-primary">
                            🚀 Start Quiz
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 🎮 RENDER QUIZ INTERFACE (After successful registration)
     */
    private function render_quiz_interface($campaign, $registration_data) {
        // Get questions using the protected method (now accessible)
        $questions = $this->get_quiz_questions($campaign->id, $campaign->questions_per_quiz);
        
        if (empty($questions)) {
            return $this->render_error('No questions available for this campaign.');
        }
        
        $participant = $registration_data['participant_data'];
        
        ob_start();
        ?>
        <div class="vefify-quiz-container vefify-quiz-active">
            <div class="vefify-quiz-header">
                <h2>🎯 <?php echo esc_html($campaign->name); ?></h2>
                <div class="vefify-participant-welcome">
                    <p><strong>Welcome, <?php echo esc_html($participant['name']); ?>!</strong></p>
                    <p>You are now ready to start the quiz. Please read each question carefully.</p>
                </div>
                
                <div class="vefify-quiz-info">
                    <div class="vefify-info-grid">
                        <div class="info-item">
                            <span class="info-label">Questions:</span>
                            <span class="info-value"><?php echo count($questions); ?></span>
                        </div>
                        <?php if ($campaign->time_limit): ?>
                        <div class="info-item">
                            <span class="info-label">Time Limit:</span>
                            <span class="info-value"><?php echo round($campaign->time_limit / 60); ?> minutes</span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label">Pass Score:</span>
                            <span class="info-value"><?php echo $campaign->pass_score; ?> points</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="vefify-quiz-content">
                <div class="vefify-quiz-progress">
                    <div class="progress-header">
                        <span>Question <span id="current-question">1</span> of <?php echo count($questions); ?></span>
                        <?php if ($campaign->time_limit): ?>
                            <span id="quiz-timer" class="quiz-timer"><?php echo gmdate('i:s', $campaign->time_limit); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo (1 / count($questions)) * 100; ?>%"></div>
                    </div>
                </div>
                
                <div class="vefify-questions-container">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="vefify-question <?php echo $index === 0 ? 'active' : 'hidden'; ?>" 
                             data-question-index="<?php echo $index; ?>" 
                             data-question-id="<?php echo $question['id']; ?>">
                             
                            <div class="question-header">
                                <h3>Question <?php echo ($index + 1); ?></h3>
                            </div>
                            
                            <div class="question-text">
                                <?php echo esc_html($question['question_text']); ?>
                            </div>
                            
                            <div class="question-options">
                                <?php foreach ($question['options'] as $option_index => $option): ?>
                                    <label class="option-label">
                                        <input type="radio" 
                                               name="question_<?php echo $question['id']; ?>" 
                                               value="<?php echo esc_attr($option['option_value']); ?>"
                                               data-option-text="<?php echo esc_attr($option['option_text']); ?>">
                                        <span class="option-text"><?php echo esc_html($option['option_text']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="vefify-quiz-navigation">
                    <button type="button" id="prev-question" class="vefify-btn vefify-btn-secondary" disabled>
                        ← Previous
                    </button>
                    <button type="button" id="next-question" class="vefify-btn vefify-btn-primary">
                        Next →
                    </button>
                    <button type="button" id="submit-quiz" class="vefify-btn vefify-btn-success" style="display: none;">
                        🎯 Submit Quiz
                    </button>
                </div>
            </div>
            
            <div class="vefify-debug-info" style="display: none;">
                <h4>Debug Information:</h4>
                <p><strong>Participant ID:</strong> <?php echo $registration_data['participant_id']; ?></p>
                <p><strong>Campaign ID:</strong> <?php echo $campaign->id; ?></p>
                <p><strong>Questions loaded:</strong> <?php echo count($questions); ?></p>
            </div>
        </div>
        
        <script>
        // Simple quiz navigation
        document.addEventListener('DOMContentLoaded', function() {
            let currentQuestion = 0;
            const questions = document.querySelectorAll('.vefify-question');
            const totalQuestions = questions.length;
            const prevBtn = document.getElementById('prev-question');
            const nextBtn = document.getElementById('next-question');
            const submitBtn = document.getElementById('submit-quiz');
            
            function updateQuestionDisplay() {
                // Hide all questions
                questions.forEach(q => q.classList.add('hidden'));
                questions.forEach(q => q.classList.remove('active'));
                
                // Show current question
                questions[currentQuestion].classList.remove('hidden');
                questions[currentQuestion].classList.add('active');
                
                // Update progress
                document.getElementById('current-question').textContent = currentQuestion + 1;
                const progressPercent = ((currentQuestion + 1) / totalQuestions) * 100;
                document.querySelector('.progress-fill').style.width = progressPercent + '%';
                
                // Update navigation buttons
                prevBtn.disabled = currentQuestion === 0;
                
                if (currentQuestion === totalQuestions - 1) {
                    nextBtn.style.display = 'none';
                    submitBtn.style.display = 'inline-block';
                } else {
                    nextBtn.style.display = 'inline-block';
                    submitBtn.style.display = 'none';
                }
            }
            
            prevBtn.addEventListener('click', function() {
                if (currentQuestion > 0) {
                    currentQuestion--;
                    updateQuestionDisplay();
                }
            });
            
            nextBtn.addEventListener('click', function() {
                if (currentQuestion < totalQuestions - 1) {
                    currentQuestion++;
                    updateQuestionDisplay();
                }
            });
            
            submitBtn.addEventListener('click', function() {
                const answers = {};
                questions.forEach(function(question) {
                    const questionId = question.getAttribute('data-question-id');
                    const selected = question.querySelector('input[type="radio"]:checked');
                    if (selected) {
                        answers[questionId] = {
                            value: selected.value,
                            text: selected.getAttribute('data-option-text')
                        };
                    }
                });
                
                // For now, just show results
                alert('Quiz submitted! Answers: ' + Object.keys(answers).length + ' of ' + totalQuestions);
                console.log('Quiz answers:', answers);
            });
            
            // Initialize display
            updateQuestionDisplay();
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 🎨 GET COMPLETE CSS STYLES
     */
    private function get_quiz_css() {
        return '
        .vefify-quiz-container {
            max-width: 800px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .vefify-quiz-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .vefify-quiz-header h2 {
            margin: 0 0 15px 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .vefify-description {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .vefify-quiz-meta {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .vefify-meta-item {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            backdrop-filter: blur(10px);
        }
        
        .vefify-form-container {
            padding: 30px;
        }
        
        .vefify-form-container h3 {
            color: #333;
            margin-bottom: 25px;
            font-size: 20px;
        }
        
        .vefify-field {
            margin-bottom: 20px;
        }
        
        .vefify-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .vefify-field input,
        .vefify-field select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .vefify-field input:focus,
        .vefify-field select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .field-help {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }
        
        .vefify-form-actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .vefify-btn {
            display: inline-block;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .vefify-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .vefify-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.3);
        }
        
        .vefify-btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .vefify-btn-success {
            background: #28a745;
            color: white;
        }
        
        .vefify-error {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 20px;
        }
        
        .vefify-notice {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin: 20px;
        }
        
        /* Quiz Interface Styles */
        .vefify-quiz-active {
            background: #f8f9fa;
        }
        
        .vefify-participant-welcome {
            background: rgba(255,255,255,0.2);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            backdrop-filter: blur(10px);
        }
        
        .vefify-quiz-info {
            margin-top: 20px;
        }
        
        .vefify-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        
        .info-label {
            display: block;
            font-size: 12px;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        
        .info-value {
            display: block;
            font-size: 18px;
            font-weight: 600;
        }
        
        .vefify-quiz-content {
            padding: 30px;
        }
        
        .vefify-quiz-progress {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-weight: 600;
            color: #333;
        }
        
        .quiz-timer {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
        }
        
        .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }
        
        .vefify-questions-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .vefify-question {
            padding: 30px;
        }
        
        .vefify-question.hidden {
            display: none;
        }
        
        .question-header h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
        }
        
        .question-text {
            font-size: 18px;
            color: #333;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .question-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .option-label {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fafbfc;
        }
        
        .option-label:hover {
            border-color: #667eea;
            background: #f0f3ff;
        }
        
        .option-label input[type="radio"] {
            margin-right: 15px;
            transform: scale(1.2);
        }
        
        .option-text {
            flex: 1;
            font-size: 16px;
            color: #333;
        }
        
        .vefify-quiz-navigation {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }
        
        .vefify-quiz-navigation .vefify-btn {
            flex: 1;
            max-width: 200px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .vefify-quiz-container {
                margin: 10px;
                border-radius: 8px;
            }
            
            .vefify-quiz-header {
                padding: 20px;
            }
            
            .vefify-quiz-header h2 {
                font-size: 24px;
            }
            
            .vefify-quiz-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .vefify-form-container,
            .vefify-quiz-content {
                padding: 20px;
            }
            
            .vefify-info-grid {
                grid-template-columns: 1fr;
            }
            
            .vefify-quiz-navigation {
                flex-direction: column;
            }
            
            .vefify-quiz-navigation .vefify-btn {
                max-width: none;
            }
        }
        ';
    }
    
    /**
     * 🎨 OUTPUT CSS INLINE (Fallback)
     */
    public function output_css_inline() {
        echo '<style>' . $this->get_quiz_css() . '</style>';
    }
    
    /**
     * 📝 GET FORM FIELD DEFINITIONS
     */
    private function get_form_field_definitions() {
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
                'required' => true
            ),
            'company' => array(
                'label' => 'Company/Organization',
                'type' => 'text',
                'placeholder' => 'Enter company name',
                'required' => false
            ),
            'province' => array(
                'label' => 'Province',
                'type' => 'select',
                'required' => false,
                'options' => array(
                    'hanoi' => 'Ha Noi',
                    'hcm' => 'Ho Chi Minh City',
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
            'age' => array(
                'label' => 'Age',
                'type' => 'number',
                'placeholder' => '25',
                'required' => false
            )
        );
    }
    
    /**
     * 🚨 RENDER ERROR MESSAGE
     */
    private function render_error($message) {
        return '<div class="vefify-error">❌ ' . esc_html($message) . '</div>';
    }
    
    /**
     * 📢 RENDER NOTICE MESSAGE
     */
    private function render_notice($message) {
        return '<div class="vefify-notice">📅 ' . esc_html($message) . '</div>';
    }
}
?>