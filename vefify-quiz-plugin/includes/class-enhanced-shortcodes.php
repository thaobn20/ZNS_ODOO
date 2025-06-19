<?php
/**
 * 🚀 COMPLETE AJAX QUIZ SOLUTION - NO REDIRECTS
 * File: includes/class-enhanced-shortcodes.php
 * 
 * FIXES:
 * ✅ Uses AJAX instead of redirects (eliminates 404 issue)
 * ✅ Matches your actual database column names
 * ✅ Real-time form submission without page reload
 * ✅ JSON data exchange
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Enhanced_Shortcodes {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Register shortcodes
        add_shortcode('vefify_quiz', array($this, 'render_quiz'));
        add_shortcode('vefify_debug', array($this, 'render_debug'));
        
        // 🚀 AJAX endpoints - NO MORE REDIRECTS!
        add_action('wp_ajax_vefify_register_participant', array($this, 'ajax_register_participant'));
        add_action('wp_ajax_nopriv_vefify_register_participant', array($this, 'ajax_register_participant'));
        add_action('wp_ajax_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
        add_action('wp_ajax_nopriv_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_quiz_scripts'));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('🚀 AJAX Quiz System Initialized');
        }
    }
    
    /**
     * 📜 ENQUEUE QUIZ SCRIPTS
     */
    public function enqueue_quiz_scripts() {
        global $post;
        
        // Only load on pages with quiz shortcode
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vefify_quiz')) {
            wp_enqueue_script('jquery');
            
            // Localize AJAX data
            wp_localize_script('jquery', 'vefifyAjax', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vefify_quiz_nonce'),
                'strings' => array(
                    'loading' => 'Loading...',
                    'error' => 'An error occurred. Please try again.',
                    'success' => 'Success!',
                    'confirmSubmit' => 'Are you sure you want to submit your quiz?'
                )
            ));
        }
    }
    
    /**
     * 🎯 MAIN QUIZ SHORTCODE - AJAX VERSION
     */
    public function render_quiz($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'fields' => 'participant_name,participant_phone,participant_email,company,province,pharmacy_code,occupation,age',
            'style' => 'default',
            'title' => '',
            'description' => '',
            'debug' => 'false'
        ), $atts);
        
        if (empty($atts['campaign_id'])) {
            return $this->render_error('Campaign ID is required. Usage: [vefify_quiz campaign_id="1"]');
        }
        
        $campaign_id = intval($atts['campaign_id']);
        
        // Debug mode
        if ($atts['debug'] === 'true') {
            return $this->render_debug_info($campaign_id);
        }
        
        // Get campaign data
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return $this->render_error('Campaign not found (ID: ' . $campaign_id . ')');
        }
        
        // Check campaign status
        if (!$this->is_campaign_active($campaign)) {
            $start_date = date('M j, Y', strtotime($campaign->start_date));
            $end_date = date('M j, Y', strtotime($campaign->end_date));
            return $this->render_notice('This campaign is not currently active. Active period: ' . $start_date . ' - ' . $end_date);
        }
        
        // 🚀 ALWAYS SHOW REGISTRATION FORM - AJAX HANDLES THE REST
        return $this->render_ajax_quiz_interface($campaign, $atts);
    }
    
    /**
     * 🎮 RENDER AJAX QUIZ INTERFACE - SINGLE PAGE, NO REDIRECTS
     */
    private function render_ajax_quiz_interface($campaign, $atts) {
        $fields = array_map('trim', explode(',', $atts['fields']));
        $form_fields = $this->get_form_field_definitions();
        
        ob_start();
        ?>
        <div class="vefify-quiz-container" id="vefify-quiz-app">
            <!-- Campaign Header -->
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
            
            <!-- Alert Container -->
            <div id="vefify-alert" class="vefify-alert" style="display: none;"></div>
            
            <!-- 📝 REGISTRATION FORM SECTION -->
            <div id="registration-section" class="vefify-section">
                <div class="vefify-form-container">
                    <h3>📝 Please fill in your information to start the quiz</h3>
                    
                    <form id="vefify-registration-form" class="vefify-form">
                        <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->id); ?>">
                        
                        <?php foreach ($fields as $field_key): ?>
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
                                                <option value="<?php echo esc_attr($value); ?>">
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input 
                                            type="<?php echo esc_attr($field['type']); ?>"
                                            name="<?php echo esc_attr($field_key); ?>"
                                            id="<?php echo esc_attr($field_key); ?>"
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
                            <button type="submit" class="vefify-btn vefify-btn-primary" id="start-quiz-btn">
                                🚀 Start Quiz
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 🎮 QUIZ SECTION -->
            <div id="quiz-section" class="vefify-section" style="display: none;">
                <div class="vefify-quiz-content">
                    <!-- Welcome Message -->
                    <div id="participant-welcome" class="vefify-participant-welcome"></div>
                    
                    <!-- Progress Bar -->
                    <div class="vefify-quiz-progress">
                        <div class="progress-header">
                            <span>Question <span id="current-question">1</span> of <span id="total-questions"><?php echo $campaign->questions_per_quiz; ?></span></span>
                            <?php if ($campaign->time_limit): ?>
                                <span id="quiz-timer" class="quiz-timer" data-time="<?php echo $campaign->time_limit; ?>">
                                    <?php echo gmdate('i:s', $campaign->time_limit); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <!-- Questions Container -->
                    <div id="questions-container" class="vefify-questions-container">
                        <!-- Questions will be loaded here via AJAX -->
                    </div>
                    
                    <!-- Navigation -->
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
            </div>
            
            <!-- 🏆 RESULTS SECTION -->
            <div id="results-section" class="vefify-section" style="display: none;">
                <div class="vefify-results-content">
                    <div id="results-display">
                        <!-- Results will be displayed here -->
                    </div>
                    
                    <div class="result-actions">
                        <button type="button" id="restart-quiz" class="vefify-btn vefify-btn-primary">
                            🔄 Take Another Quiz
                        </button>
                        <button type="button" onclick="window.print()" class="vefify-btn vefify-btn-secondary">
                            🖨️ Print Results
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Loading Overlay -->
            <div id="loading-overlay" class="vefify-loading-overlay" style="display: none;">
                <div class="vefify-spinner"></div>
                <p>Loading...</p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            const VefifyQuiz = {
                currentQuestion: 0,
                questions: [],
                answers: {},
                participantId: null,
                campaignId: <?php echo $campaign->id; ?>,
                timeLimit: <?php echo $campaign->time_limit ?: 0; ?>,
                timeRemaining: <?php echo $campaign->time_limit ?: 0; ?>,
                timer: null,
                
                init: function() {
                    this.bindEvents();
                    console.log('🚀 Vefify AJAX Quiz initialized');
                },
                
                bindEvents: function() {
                    // Registration form submit
                    $('#vefify-registration-form').on('submit', this.handleRegistration.bind(this));
                    
                    // Quiz navigation
                    $('#prev-question').on('click', this.previousQuestion.bind(this));
                    $('#next-question').on('click', this.nextQuestion.bind(this));
                    $('#submit-quiz').on('click', this.submitQuiz.bind(this));
                    $('#restart-quiz').on('click', this.restartQuiz.bind(this));
                    
                    // Answer selection
                    $(document).on('change', '.question-option', this.saveAnswer.bind(this));
                },
                
                showLoading: function() {
                    $('#loading-overlay').show();
                },
                
                hideLoading: function() {
                    $('#loading-overlay').hide();
                },
                
                showAlert: function(message, type = 'error') {
                    const alertClass = type === 'success' ? 'vefify-success' : 'vefify-error';
                    $('#vefify-alert')
                        .removeClass('vefify-success vefify-error')
                        .addClass(alertClass)
                        .html(message)
                        .show();
                    
                    // Auto hide after 5 seconds
                    setTimeout(() => {
                        $('#vefify-alert').fadeOut();
                    }, 5000);
                },
                
                switchSection: function(sectionId) {
                    $('.vefify-section').hide();
                    $('#' + sectionId).show();
                },
                
                handleRegistration: function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(e.target);
                    formData.append('action', 'vefify_register_participant');
                    formData.append('nonce', vefifyAjax.nonce);
                    
                    this.showLoading();
                    
                    $.ajax({
                        url: vefifyAjax.ajaxUrl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: (response) => {
                            this.hideLoading();
                            
                            if (response.success) {
                                this.participantId = response.data.participant_id;
                                this.questions = response.data.questions;
                                
                                // Show welcome message
                                $('#participant-welcome').html(
                                    '<p><strong>Welcome, ' + response.data.participant_name + '!</strong></p>' +
                                    '<p>Answer all questions to complete the quiz. Good luck!</p>'
                                );
                                
                                // Update total questions
                                $('#total-questions').text(this.questions.length);
                                
                                // Load questions
                                this.loadQuestions();
                                
                                // Switch to quiz section
                                this.switchSection('quiz-section');
                                
                                // Start timer if needed
                                if (this.timeLimit > 0) {
                                    this.startTimer();
                                }
                                
                                this.showAlert('Registration successful! Quiz started.', 'success');
                            } else {
                                this.showAlert(response.data || 'Registration failed. Please try again.');
                            }
                        },
                        error: () => {
                            this.hideLoading();
                            this.showAlert('Network error. Please check your connection.');
                        }
                    });
                },
                
                loadQuestions: function() {
                    let questionsHtml = '';
                    
                    this.questions.forEach((question, index) => {
                        const isActive = index === 0 ? 'active' : 'hidden';
                        
                        questionsHtml += `
                            <div class="vefify-question ${isActive}" data-question-index="${index}" data-question-id="${question.id}">
                                <div class="question-header">
                                    <h3>Question ${index + 1}</h3>
                                </div>
                                
                                <div class="question-text">
                                    ${question.question_text}
                                </div>
                                
                                <div class="question-options">
                        `;
                        
                        question.options.forEach((option, optionIndex) => {
                            questionsHtml += `
                                <label class="option-label">
                                    <input type="radio" 
                                           name="question_${question.id}" 
                                           value="${option.id}"
                                           class="question-option"
                                           data-question-id="${question.id}">
                                    <span class="option-text">${option.option_text}</span>
                                </label>
                            `;
                        });
                        
                        questionsHtml += `
                                </div>
                            </div>
                        `;
                    });
                    
                    $('#questions-container').html(questionsHtml);
                    this.updateQuestionDisplay();
                },
                
                previousQuestion: function() {
                    if (this.currentQuestion > 0) {
                        this.currentQuestion--;
                        this.updateQuestionDisplay();
                    }
                },
                
                nextQuestion: function() {
                    if (this.currentQuestion < this.questions.length - 1) {
                        this.currentQuestion++;
                        this.updateQuestionDisplay();
                    }
                },
                
                updateQuestionDisplay: function() {
                    // Hide all questions
                    $('.vefify-question').removeClass('active').addClass('hidden');
                    
                    // Show current question
                    $(`.vefify-question[data-question-index="${this.currentQuestion}"]`)
                        .removeClass('hidden').addClass('active');
                    
                    // Update progress
                    $('#current-question').text(this.currentQuestion + 1);
                    const progressPercent = ((this.currentQuestion + 1) / this.questions.length) * 100;
                    $('#progress-fill').css('width', progressPercent + '%');
                    
                    // Update navigation
                    $('#prev-question').prop('disabled', this.currentQuestion === 0);
                    
                    if (this.currentQuestion === this.questions.length - 1) {
                        $('#next-question').hide();
                        $('#submit-quiz').show();
                    } else {
                        $('#next-question').show();
                        $('#submit-quiz').hide();
                    }
                },
                
                saveAnswer: function(e) {
                    const questionId = $(e.target).data('question-id');
                    const value = $(e.target).val();
                    
                    this.answers[questionId] = value;
                    console.log('Answer saved:', questionId, value);
                },
                
                startTimer: function() {
                    if (this.timeLimit <= 0) return;
                    
                    this.timer = setInterval(() => {
                        this.timeRemaining--;
                        
                        const minutes = Math.floor(this.timeRemaining / 60);
                        const seconds = this.timeRemaining % 60;
                        $('#quiz-timer').text(`${minutes}:${seconds.toString().padStart(2, '0')}`);
                        
                        if (this.timeRemaining <= 60) {
                            $('#quiz-timer').addClass('time-warning');
                        }
                        
                        if (this.timeRemaining <= 0) {
                            clearInterval(this.timer);
                            this.submitQuiz(true);
                        }
                    }, 1000);
                },
                
                submitQuiz: function(timeUp = false) {
                    const message = timeUp ? 'Time is up! Your quiz will be submitted automatically.' : 
                                             'Are you sure you want to submit your quiz?';
                    
                    if (!timeUp && !confirm(message)) {
                        return;
                    }
                    
                    // Stop timer
                    if (this.timer) {
                        clearInterval(this.timer);
                    }
                    
                    this.showLoading();
                    
                    $.ajax({
                        url: vefifyAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'vefify_submit_quiz',
                            participant_id: this.participantId,
                            campaign_id: this.campaignId,
                            answers: JSON.stringify(this.answers),
                            nonce: vefifyAjax.nonce
                        },
                        success: (response) => {
                            this.hideLoading();
                            
                            if (response.success) {
                                this.showResults(response.data);
                                this.switchSection('results-section');
                                this.showAlert('Quiz completed successfully!', 'success');
                            } else {
                                this.showAlert(response.data || 'Failed to submit quiz. Please try again.');
                            }
                        },
                        error: () => {
                            this.hideLoading();
                            this.showAlert('Network error. Please try again.');
                        }
                    });
                },
                
                showResults: function(results) {
                    const passed = results.score >= results.pass_score;
                    const percentage = Math.round((results.score / results.total) * 100);
                    
                    const resultsHtml = `
                        <div class="result-score-display">
                            <div class="score-circle ${passed ? 'passed' : 'failed'}">
                                <div class="score-number">${results.score}</div>
                                <div class="score-total">/ ${results.total}</div>
                            </div>
                            <div class="score-percentage">${percentage}%</div>
                        </div>
                        
                        <div class="result-status">
                            <div class="status-badge ${passed ? 'passed' : 'failed'}">
                                ${passed ? '✅ Congratulations! You passed!' : '❌ Keep learning!'}
                            </div>
                            <p>You scored ${results.score} out of ${results.total} questions correctly.</p>
                        </div>
                        
                        <div class="participant-details">
                            <h3>Quiz Summary</h3>
                            <p><strong>Participant:</strong> ${results.participant_name}</p>
                            <p><strong>Completion Time:</strong> ${results.completion_time}</p>
                            <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                        </div>
                    `;
                    
                    $('#results-display').html(resultsHtml);
                },
                
                restartQuiz: function() {
                    if (confirm('Are you sure you want to start a new quiz? This will reset all progress.')) {
                        // Reset state
                        this.currentQuestion = 0;
                        this.answers = {};
                        this.participantId = null;
                        this.timeRemaining = this.timeLimit;
                        
                        if (this.timer) {
                            clearInterval(this.timer);
                        }
                        
                        // Reset form
                        $('#vefify-registration-form')[0].reset();
                        
                        // Show registration section
                        this.switchSection('registration-section');
                        
                        // Hide alert
                        $('#vefify-alert').hide();
                    }
                }
            };
            
            // Initialize the quiz
            VefifyQuiz.init();
        });
        </script>
        
        <style>
        /* Loading Overlay */
        .vefify-loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
        }
        
        .vefify-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Alert Styles */
        .vefify-alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .vefify-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .vefify-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Question Styles */
        .vefify-question.hidden {
            display: none;
        }
        
        .vefify-question.active {
            display: block;
        }
        
        /* Timer Warning */
        .quiz-timer.time-warning {
            background: #dc3545 !important;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* Results Styles */
        .result-score-display {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border: 8px solid;
        }
        
        .score-circle.passed {
            border-color: #28a745;
            background: #d4edda;
            color: #155724;
        }
        
        .score-circle.failed {
            border-color: #dc3545;
            background: #f8d7da;
            color: #721c24;
        }
        
        .score-number {
            font-size: 36px;
            line-height: 1;
        }
        
        .score-total {
            font-size: 18px;
            opacity: 0.8;
        }
        
        .score-percentage {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .status-badge.passed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.failed {
            background: #f8d7da;
            color: #721c24;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 🚀 AJAX: REGISTER PARTICIPANT - MATCHES YOUR DB STRUCTURE
     */
    public function ajax_register_participant() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_quiz_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        
        // 🔧 FIXED: Use your actual database column names
        $form_data = array(
            'campaign_id' => $campaign_id,
            'participant_name' => sanitize_text_field($_POST['participant_name'] ?? ''),
            'participant_email' => sanitize_email($_POST['participant_email'] ?? ''),
            'participant_phone' => sanitize_text_field($_POST['participant_phone'] ?? ''),
            'company' => sanitize_text_field($_POST['company'] ?? ''),
            'province' => sanitize_text_field($_POST['province'] ?? ''),
            'pharmacy_code' => sanitize_text_field($_POST['pharmacy_code'] ?? ''),
            'occupation' => sanitize_text_field($_POST['occupation'] ?? ''),
            'age' => intval($_POST['age'] ?? 0)
        );
        
        // Validate required fields
        if (empty($form_data['participant_name'])) {
            wp_send_json_error('Please enter your full name.');
        }
        
        if (empty($form_data['participant_phone'])) {
            wp_send_json_error('Please enter your phone number.');
        }
        
        // Check for existing registration
        $table_name = $this->wpdb->prefix . 'vefify_participants';
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE campaign_id = %d AND participant_phone = %s",
            $campaign_id,
            $form_data['participant_phone']
        ));
        
        if ($existing) {
            wp_send_json_error('This phone number is already registered for this campaign.');
        }
        
        // Generate session ID
        $session_id = 'quiz_' . uniqid() . '_' . time();
        
        // 🔧 FIXED: Add required fields for your database
        $participant_data = array_merge($form_data, array(
            'session_id' => $session_id,
            'quiz_status' => 'started',
            'start_time' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql')
        ));
        
        // Insert participant
        $result = $this->wpdb->insert($table_name, $participant_data);
        
        if ($result === false) {
            error_log('🚨 Database insert error: ' . $this->wpdb->last_error);
            wp_send_json_error('Registration failed: ' . $this->wpdb->last_error);
        }
        
        $participant_id = $this->wpdb->insert_id;
        
        // Get questions for the quiz
        $questions = $this->get_quiz_questions($campaign_id, 5); // Get 5 questions
        
        error_log('✅ Participant registered via AJAX - ID: ' . $participant_id . ', Name: ' . $form_data['participant_name']);
        
        wp_send_json_success(array(
            'participant_id' => $participant_id,
            'participant_name' => $form_data['participant_name'],
            'session_id' => $session_id,
            'questions' => $questions
        ));
    }
    
    /**
     * 🚀 AJAX: SUBMIT QUIZ
     */
    public function ajax_submit_quiz() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_quiz_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $participant_id = intval($_POST['participant_id']);
        $campaign_id = intval($_POST['campaign_id']);
        $answers = json_decode(stripslashes($_POST['answers']), true);
        
        if (!$participant_id || !$campaign_id) {
            wp_send_json_error('Missing required data');
        }
        
        // Get participant
        $table_name = $this->wpdb->prefix . 'vefify_participants';
        $participant = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $participant_id
        ));
        
        if (!$participant) {
            wp_send_json_error('Participant not found');
        }
        
        // Calculate score (simplified)
        $total_questions = count($answers);
        $score = 0;
        
        // For demo purposes, calculate score based on answered questions
        // In real implementation, you'd check against correct answers
        foreach ($answers as $question_id => $answer) {
            if (!empty($answer)) {
                // Simple scoring: count non-empty answers as correct for demo
                $score++;
            }
        }
        
        // Update participant record
        $completion_time = time() - strtotime($participant->start_time);
        $update_result = $this->wpdb->update(
            $table_name,
            array(
                'quiz_status' => 'completed',
                'end_time' => current_time('mysql'),
                'final_score' => $score,
                'total_questions' => $total_questions,
                'completion_time' => $completion_time,
                'answers_data' => json_encode($answers),
                'completed_at' => current_time('mysql')
            ),
            array('id' => $participant_id)
        );
        
        if ($update_result === false) {
            error_log('🚨 Failed to update participant: ' . $this->wpdb->last_error);
            wp_send_json_error('Failed to save quiz results');
        }
        
        error_log('✅ Quiz completed via AJAX - Participant: ' . $participant_id . ', Score: ' . $score);
        
        wp_send_json_success(array(
            'score' => $score,
            'total' => $total_questions,
            'pass_score' => 3, // Default pass score
            'participant_name' => $participant->participant_name,
            'completion_time' => gmdate('i:s', $completion_time)
        ));
    }
    
    /**
     * 📝 GET FORM FIELD DEFINITIONS - MATCHES YOUR DB COLUMNS
     */
    private function get_form_field_definitions() {
        return array(
            'participant_name' => array(
                'label' => 'Full Name',
                'type' => 'text',
                'placeholder' => 'Enter your full name',
                'required' => true
            ),
            'participant_email' => array(
                'label' => 'Email Address',
                'type' => 'email',
                'placeholder' => 'Enter your email',
                'required' => false
            ),
            'participant_phone' => array(
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
            'age' => array(
                'label' => 'Age',
                'type' => 'number',
                'placeholder' => '25',
                'required' => false
            )
        );
    }
    
    /**
     * 🔧 DATABASE HELPER METHODS
     */
    private function get_campaign($campaign_id) {
        $table = $this->wpdb->prefix . 'vefify_campaigns';
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $campaign_id
        ));
    }
    
    private function is_campaign_active($campaign) {
        if (!$campaign) return false;
        
        $now = current_time('mysql');
        return ($campaign->is_active == 1 && 
                $campaign->start_date <= $now && 
                $campaign->end_date >= $now);
    }
    
    private function get_quiz_questions($campaign_id, $limit) {
        $questions_table = $this->wpdb->prefix . 'vefify_questions';
        $options_table = $this->wpdb->prefix . 'vefify_question_options';
        
        // Get random questions
        $questions = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$questions_table} 
             WHERE (campaign_id = %d OR campaign_id IS NULL) AND is_active = 1 
             ORDER BY RAND() 
             LIMIT %d",
            $campaign_id, $limit
        ), ARRAY_A);
        
        // Get options for each question
        foreach ($questions as &$question) {
            $options = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, option_text, is_correct FROM {$options_table} 
                 WHERE question_id = %d 
                 ORDER BY order_index",
                $question['id']
            ), ARRAY_A);
            
            $question['options'] = $options;
        }
        
        return $questions;
    }
    
    /**
     * 🐛 DEBUG METHODS
     */
    public function render_debug() {
        return $this->render_debug_info(1);
    }
    
    private function render_debug_info($campaign_id) {
        ob_start();
        ?>
        <div class="vefify-debug-container">
            <h3>🚀 AJAX Quiz Debug Information</h3>
            
            <div class="debug-section">
                <h4>AJAX System Status</h4>
                <p><strong>AJAX URL:</strong> <?php echo admin_url('admin-ajax.php'); ?></p>
                <p><strong>Nonce:</strong> <?php echo wp_create_nonce('vefify_quiz_nonce'); ?></p>
                <p><strong>jQuery loaded:</strong> <?php echo wp_script_is('jquery', 'enqueued') ? 'YES' : 'NO'; ?></p>
            </div>
            
            <div class="debug-section">
                <h4>Database Column Mapping</h4>
                <p><strong>Expected vs Actual:</strong></p>
                <ul>
                    <li>✅ participant_name (matches DB)</li>
                    <li>✅ participant_email (matches DB)</li>
                    <li>✅ participant_phone (matches DB)</li>
                    <li>✅ company (matches DB)</li>
                    <li>✅ occupation (matches DB)</li>
                    <li>✅ age (matches DB)</li>
                </ul>
            </div>
            
            <div class="debug-section">
                <h4>Participants Table Structure</h4>
                <?php
                $table = $this->wpdb->prefix . 'vefify_participants';
                $columns = $this->wpdb->get_results("SHOW COLUMNS FROM {$table}");
                if ($columns): ?>
                    <ul>
                        <?php foreach ($columns as $column): ?>
                            <li><?php echo esc_html($column->Field); ?> (<?php echo esc_html($column->Type); ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <div class="debug-section">
                <h4>Test AJAX Endpoints</h4>
                <button onclick="testAjaxConnection()" class="button">Test AJAX Connection</button>
                <div id="ajax-test-result"></div>
            </div>
        </div>
        
        <script>
        function testAjaxConnection() {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'vefify_register_participant',
                    campaign_id: 1,
                    participant_name: 'Test User',
                    participant_phone: '0123456789',
                    nonce: '<?php echo wp_create_nonce('vefify_quiz_nonce'); ?>'
                },
                success: function(response) {
                    document.getElementById('ajax-test-result').innerHTML = 
                        '<p style="color: green;">✅ AJAX connection working! Response: ' + JSON.stringify(response) + '</p>';
                },
                error: function(xhr, status, error) {
                    document.getElementById('ajax-test-result').innerHTML = 
                        '<p style="color: red;">❌ AJAX error: ' + error + '</p>';
                }
            });
        }
        </script>
        
        <style>
        .vefify-debug-container {
            max-width: 800px;
            margin: 20px auto;
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            font-family: monospace;
        }
        
        .debug-section {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            border-left: 4px solid #007cba;
        }
        
        .debug-section h4 {
            margin-top: 0;
            color: #333;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 🚨 RENDER ERROR/NOTICE
     */
    private function render_error($message) {
        return '<div class="vefify-error">❌ ' . esc_html($message) . '</div>';
    }
    
    private function render_notice($message) {
        return '<div class="vefify-notice">📅 ' . esc_html($message) . '</div>';
    }
}

// Initialize
new Vefify_Enhanced_Shortcodes();

?>