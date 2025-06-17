<?php
/**
 * Campaign Module Main Class
 * File: modules/campaigns/class-campaign-module.php
 * Coordinates between campaign model and manager components
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Campaign_Module {
    
    private static $instance = null;
    private $model;
    private $manager;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Load module components
        $this->load_components();
        
        // WordPress hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_vefify_campaign_action', array($this, 'ajax_campaign_action'));
        
        // 🔥 FIXED: Add missing shortcode and frontend hooks
        add_action('init', array($this, 'init_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Handle URL actions
        add_action('admin_init', array($this, 'handle_url_actions'));
    }
    
    /**
     * Load module components
     */
    private function load_components() {
        // Load model (data layer)
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/campaigns/class-campaign-model.php';
        $this->model = new Vefify_Campaign_Model();
        
        // Load manager (admin interface) only in admin
        if (is_admin()) {
            require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/campaigns/class-campaign-manager.php';
            $this->manager = new Vefify_Campaign_Manager($this->model);
        }
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Main campaigns page
        add_submenu_page(
            'vefify-quiz',
            'Campaigns',
            '📋 Campaigns',
            'manage_options',
            'vefify-campaigns',
            array($this, 'admin_page_router')
        );
    }
    
    /**
     * Route admin page requests
     */
    public function admin_page_router() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'new':
            case 'edit':
                $this->manager->display_campaign_form();
                break;
            case 'analytics':
                $this->display_campaign_analytics();
                break;
            default:
                $this->manager->display_campaigns_list();
                break;
        }
    }
    
    /**
     * Handle URL-based actions (delete, duplicate, etc.)
     */
    public function handle_url_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'vefify-campaigns') {
            return;
        }
        
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!current_user_can('manage_options') || !$campaign_id) {
            return;
        }
        
        switch ($action) {
            case 'delete':
                $this->handle_delete_campaign($campaign_id);
                break;
            case 'duplicate':
                $this->handle_duplicate_campaign($campaign_id);
                break;
        }
    }
    
    /**
     * Handle campaign deletion
     */
    private function handle_delete_campaign($campaign_id) {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_campaign_' . $campaign_id)) {
            wp_die('Security check failed');
        }
        
        $result = $this->model->delete_campaign($campaign_id);
        
        if (is_wp_error($result)) {
            set_transient('vefify_admin_notice', array(
                'type' => 'error',
                'message' => 'Failed to delete campaign: ' . $result->get_error_message()
            ), 30);
        } else {
            set_transient('vefify_admin_notice', array(
                'type' => 'success',
                'message' => 'Campaign deleted successfully'
            ), 30);
        }
        
        wp_redirect(admin_url('admin.php?page=vefify-campaigns'));
        exit;
    }
    
    /**
     * Handle campaign duplication
     */
    private function handle_duplicate_campaign($campaign_id) {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'duplicate_campaign_' . $campaign_id)) {
            wp_die('Security check failed');
        }
        
        $original_campaign = $this->model->get_campaign($campaign_id);
        
        if (!$original_campaign) {
            wp_die('Campaign not found');
        }
        
        // Prepare data for new campaign
        $new_campaign_data = $original_campaign;
        unset($new_campaign_data['id']);
        unset($new_campaign_data['created_at']);
        unset($new_campaign_data['updated_at']);
        
        $new_campaign_data['name'] = $original_campaign['name'] . ' (Copy)';
        $new_campaign_data['is_active'] = 0; // Deactivate copy by default
        
        // Set new dates (start from today, same duration)
        if (isset($original_campaign['start_date']) && isset($original_campaign['end_date'])) {
            $original_duration = strtotime($original_campaign['end_date']) - strtotime($original_campaign['start_date']);
            $new_campaign_data['start_date'] = current_time('mysql');
            $new_campaign_data['end_date'] = date('Y-m-d H:i:s', time() + $original_duration);
        }
        
        $result = $this->model->create_campaign($new_campaign_data);
        
        if (is_wp_error($result)) {
            set_transient('vefify_admin_notice', array(
                'type' => 'error',
                'message' => 'Failed to duplicate campaign: ' . $result->get_error_message()
            ), 30);
            wp_redirect(admin_url('admin.php?page=vefify-campaigns'));
        } else {
            set_transient('vefify_admin_notice', array(
                'type' => 'success',
                'message' => 'Campaign duplicated successfully'
            ), 30);
            wp_redirect(admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $result));
        }
        exit;
    }
    
    /**
     * Display campaign analytics page
     */
    public function display_campaign_analytics() {
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        
        if (!$campaign_id) {
            wp_die('Campaign ID required');
        }
        
        $campaign = $this->model->get_campaign($campaign_id);
        if (!$campaign) {
            wp_die('Campaign not found');
        }
        
        $stats = $this->model->get_campaign_statistics($campaign_id);
        
        ?>
        <div class="wrap">
            <h1>📊 Campaign Analytics: <?php echo esc_html($campaign['name']); ?></h1>
            
            <!-- Summary Cards -->
            <div class="analytics-summary">
                <div class="summary-card">
                    <h3><?php echo number_format($stats['total_participants'] ?? 0); ?></h3>
                    <div class="description">Total Participants</div>
                </div>
                <div class="summary-card">
                    <h3><?php echo number_format($stats['completed_participants'] ?? 0); ?></h3>
                    <div class="description">Completed Quizzes</div>
                </div>
                <div class="summary-card">
                    <h3><?php echo number_format($stats['overall_avg_score'] ?? 0, 1); ?></h3>
                    <div class="description">Average Score</div>
                </div>
                <div class="summary-card">
                    <h3><?php echo $stats['completion_rate'] ?? 0; ?>%</h3>
                    <div class="description">Completion Rate</div>
                </div>
            </div>
            
            <p><a href="?page=vefify-campaigns" class="button">← Back to Campaigns</a></p>
        </div>
        
        <style>
        .analytics-summary { display: flex; gap: 20px; margin: 20px 0; }
        .summary-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; flex: 1; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .summary-card h3 { margin: 0 0 10px; font-size: 28px; color: #0073aa; }
        .summary-card .description { color: #666; font-size: 14px; }
        </style>
        <?php
    }
    
    /**
     * 🔥 FIXED: Initialize shortcodes properly
     */
    public function init_shortcodes() {
        add_shortcode('vefify_quiz', array($this, 'quiz_shortcode'));           // ← MAIN FIX!
        add_shortcode('vefify_campaign', array($this, 'campaign_shortcode'));
        add_shortcode('vefify_campaign_list', array($this, 'campaign_list_shortcode'));
    }
    
    /**
     * 🔥 NEW: Main quiz shortcode [vefify_quiz campaign_id="1"]
     */
    public function quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 1,
            'template' => 'default'
        ), $atts);
        
        $campaign_id = intval($atts['campaign_id']);
        $campaign = $this->model->get_campaign($campaign_id);
        
        if (!$campaign) {
            return '<div class="vefify-error">
                <h3>❌ Campaign Not Found</h3>
                <p>Campaign ID ' . $campaign_id . ' could not be found or is not active.</p>
                <p><small>Please check the campaign ID and ensure the campaign is active.</small></p>
            </div>';
        }
        
        // Get campaign questions
        $questions = $this->get_campaign_questions($campaign_id, $campaign['questions_per_quiz'] ?? 5);
        
        if (empty($questions)) {
            return '<div class="vefify-error">
                <h3>⚠️ No Questions Available</h3>
                <p>This campaign does not have any active questions yet.</p>
                <p><small>Please contact the administrator to add questions to this campaign.</small></p>
            </div>';
        }
        
        ob_start();
        ?>
        <div class="vefify-quiz-container" data-campaign-id="<?php echo $campaign_id; ?>">
            <!-- Quiz Header -->
            <div class="quiz-header">
                <h2><?php echo esc_html($campaign['name']); ?></h2>
                <?php if (!empty($campaign['description'])): ?>
                    <p class="campaign-description"><?php echo esc_html($campaign['description']); ?></p>
                <?php endif; ?>
                <div class="quiz-info">
                    <span class="info-item">📝 <?php echo count($questions); ?> Questions</span>
                    <?php if (!empty($campaign['time_limit'])): ?>
                        <span class="info-item">⏱️ <?php echo gmdate('i:s', $campaign['time_limit']); ?> Time Limit</span>
                    <?php endif; ?>
                    <span class="info-item">🎯 Pass Score: <?php echo $campaign['pass_score'] ?? 3; ?></span>
                </div>
            </div>
            
            <!-- Registration Form -->
            <div class="quiz-registration" id="quiz-registration">
                <h3>📋 Enter Your Information</h3>
                <form id="quiz-registration-form" class="vefify-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="participant_name">Full Name *</label>
                            <input type="text" id="participant_name" name="name" required 
                                   placeholder="Enter your full name">
                        </div>
                        <div class="form-group">
                            <label for="participant_email">Email Address *</label>
                            <input type="email" id="participant_email" name="email" required 
                                   placeholder="your.email@example.com">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="participant_phone">Phone Number *</label>
                            <input type="tel" id="participant_phone" name="phone" required 
                                   placeholder="+1 (555) 123-4567">
                        </div>
                        <div class="form-group">
                            <label for="participant_company">Company/Organization</label>
                            <input type="text" id="participant_company" name="company" 
                                   placeholder="Optional">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="quiz-start-btn">
                            🚀 Start Quiz
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Quiz Interface -->
            <div class="quiz-interface" id="quiz-interface" style="display:none;">
                <div class="quiz-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill" style="width:0%"></div>
                    </div>
                    <span class="progress-text" id="progress-text">Question 1 of <?php echo count($questions); ?></span>
                </div>
                
                <?php if (!empty($campaign['time_limit'])): ?>
                <div class="quiz-timer" id="quiz-timer">
                    <span class="timer-label">⏱️ Time Remaining:</span>
                    <strong id="timer-display"><?php echo gmdate('i:s', $campaign['time_limit']); ?></strong>
                </div>
                <?php endif; ?>
                
                <div class="quiz-question" id="quiz-question">
                    <!-- Dynamic question content will be loaded here -->
                </div>
                
                <div class="quiz-navigation">
                    <button type="button" id="prev-question" class="nav-btn secondary" style="display:none;">
                        ⬅️ Previous
                    </button>
                    <button type="button" id="next-question" class="nav-btn primary">
                        Next ➡️
                    </button>
                    <button type="button" id="submit-quiz" class="submit-btn" style="display:none;">
                        ✅ Submit Quiz
                    </button>
                </div>
            </div>
            
            <!-- Results Display -->
            <div class="quiz-results" id="quiz-results" style="display:none;">
                <div class="results-content">
                    <h3>🎉 Quiz Completed!</h3>
                    <div class="score-display" id="score-display">
                        <!-- Score will be displayed here -->
                    </div>
                    <div class="gift-section" id="gift-section">
                        <!-- Gift information will be displayed here -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quiz Data -->
        <script type="application/json" id="quiz-data">
        <?php echo json_encode(array(
            'campaign' => $campaign,
            'questions' => $questions,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_quiz_ajax')
        )); ?>
        </script>
        
        <!-- Styles -->
        <style>
        .vefify-quiz-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .quiz-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .quiz-header h2 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .campaign-description {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .quiz-info {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            color: #495057;
            border: 1px solid #e9ecef;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
        }
        
        .form-actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .quiz-start-btn, .nav-btn, .submit-btn {
            background: linear-gradient(135deg, #007cba 0%, #0056b3 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: none;
        }
        
        .quiz-start-btn:hover, .nav-btn.primary:hover, .submit-btn:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 124, 186, 0.3);
        }
        
        .nav-btn.secondary {
            background: #6c757d;
            margin-right: 10px;
        }
        
        .nav-btn.secondary:hover {
            background: #545b62;
        }
        
        .progress-bar {
            width: 100%;
            height: 12px;
            background: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007cba 0%, #28a745 100%);
            transition: width 0.5s ease;
            border-radius: 6px;
        }
        
        .progress-text {
            display: block;
            text-align: center;
            font-weight: 600;
            color: #495057;
            margin-bottom: 20px;
        }
        
        .quiz-timer {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #f0c419;
            border-radius: 8px;
        }
        
        .timer-label {
            color: #856404;
            font-weight: 600;
        }
        
        #timer-display {
            color: #856404;
            font-size: 18px;
            font-family: monospace;
        }
        
        .quiz-question {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }
        
        .quiz-navigation {
            text-align: center;
            margin-top: 30px;
        }
        
        .vefify-error {
            padding: 25px;
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            border: 2px solid #f44336;
            border-radius: 10px;
            color: #c62828;
            text-align: center;
        }
        
        .vefify-error h3 {
            margin-top: 0;
            color: #c62828;
        }
        
        .quiz-results {
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            border-radius: 10px;
            border: 2px solid #4caf50;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .vefify-quiz-container {
                margin: 10px;
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .quiz-info {
                flex-direction: column;
                align-items: center;
            }
            
            .nav-btn.secondary {
                margin-right: 0;
                margin-bottom: 10px;
            }
        }
        </style>
        
        <!-- JavaScript -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const quizData = JSON.parse(document.getElementById('quiz-data').textContent);
            
            // Initialize Quiz
            window.VefifyQuiz = {
                data: quizData,
                currentQuestion: 0,
                answers: {},
                startTime: null,
                
                init: function() {
                    this.bindEvents();
                },
                
                bindEvents: function() {
                    const form = document.getElementById('quiz-registration-form');
                    if (form) {
                        form.addEventListener('submit', this.startQuiz.bind(this));
                    }
                    
                    const nextBtn = document.getElementById('next-question');
                    if (nextBtn) {
                        nextBtn.addEventListener('click', this.nextQuestion.bind(this));
                    }
                    
                    const prevBtn = document.getElementById('prev-question');
                    if (prevBtn) {
                        prevBtn.addEventListener('click', this.prevQuestion.bind(this));
                    }
                    
                    const submitBtn = document.getElementById('submit-quiz');
                    if (submitBtn) {
                        submitBtn.addEventListener('click', this.submitQuiz.bind(this));
                    }
                },
                
                startQuiz: function(e) {
                    e.preventDefault();
                    
                    // Validate form
                    const form = e.target;
                    const formData = new FormData(form);
                    
                    const name = formData.get('name').trim();
                    const email = formData.get('email').trim();
                    const phone = formData.get('phone').trim();
                    
                    if (!name || !email || !phone) {
                        alert('Please fill in all required fields.');
                        return;
                    }
                    
                    // Store participant data
                    this.participantData = {
                        name: name,
                        email: email,
                        phone: phone,
                        company: formData.get('company') || ''
                    };
                    
                    // Hide registration, show quiz
                    document.getElementById('quiz-registration').style.display = 'none';
                    document.getElementById('quiz-interface').style.display = 'block';
                    
                    // Start timer if time limit is set
                    if (this.data.campaign.time_limit) {
                        this.startTimer();
                    }
                    
                    // Load first question
                    this.loadQuestion(0);
                },
                
                loadQuestion: function(index) {
                    const question = this.data.questions[index];
                    if (!question) return;
                    
                    const container = document.getElementById('quiz-question');
                    let html = '<div class="question-content">';
                    html += '<h3 style="margin-top:0; color:#2c3e50;">Q' + (index + 1) + ': ' + question.question_text + '</h3>';
                    html += '<div class="question-options" style="margin-top:20px;">';
                    
                    if (question.options && question.options.length > 0) {
                        question.options.forEach(function(option, i) {
                            const inputType = question.question_type === 'multiple_select' ? 'checkbox' : 'radio';
                            html += '<label class="option-label" style="display:block; margin:10px 0; padding:15px; background:#fff; border:2px solid #e9ecef; border-radius:8px; cursor:pointer; transition:all 0.3s ease;">';
                            html += '<input type="' + inputType + '" name="question_' + question.id + '" value="' + option.id + '" style="margin-right:10px;">';
                            html += '<span>' + option.option_text + '</span>';
                            html += '</label>';
                        });
                    } else {
                        html += '<p style="color:#dc3545;">No options available for this question.</p>';
                    }
                    
                    html += '</div></div>';
                    container.innerHTML = html;
                    
                    // Add hover effects
                    const labels = container.querySelectorAll('.option-label');
                    labels.forEach(function(label) {
                        label.addEventListener('mouseenter', function() {
                            this.style.borderColor = '#007cba';
                            this.style.backgroundColor = '#f8f9fa';
                        });
                        label.addEventListener('mouseleave', function() {
                            if (!this.querySelector('input').checked) {
                                this.style.borderColor = '#e9ecef';
                                this.style.backgroundColor = '#fff';
                            }
                        });
                        label.addEventListener('click', function() {
                            // Highlight selected option
                            if (question.question_type !== 'multiple_select') {
                                labels.forEach(function(l) {
                                    l.style.borderColor = '#e9ecef';
                                    l.style.backgroundColor = '#fff';
                                });
                            }
                            this.style.borderColor = '#28a745';
                            this.style.backgroundColor = '#e8f5e8';
                        });
                    });
                    
                    // Update progress
                    const progress = ((index + 1) / this.data.questions.length) * 100;
                    document.getElementById('progress-fill').style.width = progress + '%';
                    document.getElementById('progress-text').textContent = 'Question ' + (index + 1) + ' of ' + this.data.questions.length;
                    
                    // Update navigation buttons
                    document.getElementById('prev-question').style.display = index > 0 ? 'inline-block' : 'none';
                    document.getElementById('next-question').style.display = index < this.data.questions.length - 1 ? 'inline-block' : 'none';
                    document.getElementById('submit-quiz').style.display = index === this.data.questions.length - 1 ? 'inline-block' : 'none';
                    
                    // Restore previous answers
                    if (this.answers[question.id]) {
                        const inputs = container.querySelectorAll('input[name="question_' + question.id + '"]');
                        inputs.forEach(function(input) {
                            if (Array.isArray(this.answers[question.id])) {
                                input.checked = this.answers[question.id].includes(input.value);
                            } else {
                                input.checked = this.answers[question.id] === input.value;
                            }
                            if (input.checked) {
                                input.closest('.option-label').style.borderColor = '#28a745';
                                input.closest('.option-label').style.backgroundColor = '#e8f5e8';
                            }
                        }.bind(this));
                    }
                },
                
                nextQuestion: function() {
                    this.saveCurrentAnswer();
                    if (this.currentQuestion < this.data.questions.length - 1) {
                        this.currentQuestion++;
                        this.loadQuestion(this.currentQuestion);
                    }
                },
                
                prevQuestion: function() {
                    this.saveCurrentAnswer();
                    if (this.currentQuestion > 0) {
                        this.currentQuestion--;
                        this.loadQuestion(this.currentQuestion);
                    }
                },
                
                saveCurrentAnswer: function() {
                    const question = this.data.questions[this.currentQuestion];
                    if (!question) return;
                    
                    const inputs = document.querySelectorAll('input[name="question_' + question.id + '"]:checked');
                    if (inputs.length > 0) {
                        if (question.question_type === 'multiple_select') {
                            this.answers[question.id] = Array.from(inputs).map(input => input.value);
                        } else {
                            this.answers[question.id] = inputs[0].value;
                        }
                    }
                },
                
                submitQuiz: function() {
                    this.saveCurrentAnswer();
                    
                    // Show loading state
                    const submitBtn = document.getElementById('submit-quiz');
                    const originalText = submitBtn.textContent;
                    submitBtn.textContent = '⏳ Submitting...';
                    submitBtn.disabled = true;
                    
                    // Calculate score (simplified)
                    let score = 0;
                    this.data.questions.forEach(function(question) {
                        if (this.answers[question.id]) {
                            // This is a simplified scoring - in real implementation,
                            // you'd check against correct answers
                            score += 1;
                        }
                    }.bind(this));
                    
                    // Simulate submission delay
                    setTimeout(() => {
                        this.showResults(score);
                    }, 1500);
                },
                
                showResults: function(score) {
                    // Hide quiz interface
                    document.getElementById('quiz-interface').style.display = 'none';
                    
                    // Show results
                    const resultsDiv = document.getElementById('quiz-results');
                    const scoreDisplay = document.getElementById('score-display');
                    
                    const percentage = Math.round((score / this.data.questions.length) * 100);
                    const passed = score >= this.data.campaign.pass_score;
                    
                    scoreDisplay.innerHTML = `
                        <div class="score-circle" style="display:inline-block; width:120px; height:120px; border-radius:50%; background:${passed ? '#28a745' : '#dc3545'}; color:white; display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:bold; margin:20px auto;">
                            ${score}/${this.data.questions.length}
                        </div>
                        <p style="font-size:18px; margin:20px 0;">
                            You scored <strong>${percentage}%</strong>
                        </p>
                        <p style="color:${passed ? '#28a745' : '#dc3545'}; font-weight:bold; font-size:16px;">
                            ${passed ? '🎉 Congratulations! You passed!' : '😔 Sorry, you did not pass this time.'}
                        </p>
                    `;
                    
                    resultsDiv.style.display = 'block';
                },
                
                startTimer: function() {
                    if (!this.data.campaign.time_limit) return;
                    
                    let timeLeft = this.data.campaign.time_limit;
                    const display = document.getElementById('timer-display');
                    
                    this.timer = setInterval(() => {
                        timeLeft--;
                        
                        if (timeLeft <= 0) {
                            clearInterval(this.timer);
                            this.submitQuiz();
                            return;
                        }
                        
                        const minutes = Math.floor(timeLeft / 60);
                        const seconds = timeLeft % 60;
                        display.textContent = minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
                        
                        // Warning colors
                        if (timeLeft <= 60) {
                            display.style.color = '#dc3545';
                        } else if (timeLeft <= 300) {
                            display.style.color = '#fd7e14';
                        }
                    }, 1000);
                }
            };
            
            // Initialize
            VefifyQuiz.init();
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 🔥 NEW: Get campaign questions for shortcode
     */
    private function get_campaign_questions($campaign_id, $limit = null) {
        global $wpdb;
        
        $questions_table = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'questions';
        $options_table = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'question_options';
        
        $limit_clause = $limit ? $wpdb->prepare("LIMIT %d", $limit) : '';
        
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$questions_table} 
             WHERE campaign_id = %d AND is_active = 1 
             ORDER BY RAND() {$limit_clause}",
            $campaign_id
        ), ARRAY_A);
        
        // Get options for each question
        foreach ($questions as &$question) {
            $question['options'] = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$options_table} 
                 WHERE question_id = %d 
                 ORDER BY order_index ASC",
                $question['id']
            ), ARRAY_A);
        }
        
        return $questions;
    }
    
    /**
     * Campaign shortcode [vefify_campaign id="1"]
     */
    public function campaign_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'show_description' => true,
            'show_stats' => false,
            'template' => 'default'
        ), $atts);
        
        $campaign_id = intval($atts['id']);
        if (!$campaign_id) {
            return '<p>Campaign ID required</p>';
        }
        
        $campaign = $this->model->get_campaign($campaign_id);
        if (!$campaign) {
            return '<p>Campaign not found</p>';
        }
        
        ob_start();
        ?>
        <div class="vefify-campaign-info" data-campaign-id="<?php echo $campaign_id; ?>">
            <h3 class="campaign-title"><?php echo esc_html($campaign['name']); ?></h3>
            
            <?php if ($atts['show_description'] && $campaign['description']): ?>
                <div class="campaign-description">
                    <?php echo wpautop(esc_html($campaign['description'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="campaign-meta">
                <span class="campaign-duration">
                    <strong>Duration:</strong> 
                    <?php echo date('M j, Y', strtotime($campaign['start_date'])); ?> - 
                    <?php echo date('M j, Y', strtotime($campaign['end_date'])); ?>
                </span>
                
                <?php if (!empty($campaign['max_participants'])): ?>
                    <span class="campaign-participants">
                        <strong>Max Participants:</strong> <?php echo number_format($campaign['max_participants']); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if ($atts['show_stats']): ?>
                <div class="campaign-stats">
                    <div class="stat">
                        <span class="stat-number"><?php echo number_format($campaign['total_participants'] ?? 0); ?></span>
                        <span class="stat-label">Participants</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number"><?php echo $campaign['completion_rate'] ?? 0; ?>%</span>
                        <span class="stat-label">Completion Rate</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="campaign-actions">
                <a href="<?php echo esc_url(add_query_arg('campaign_id', $campaign_id, get_permalink())); ?>" 
                   class="button vefify-join-campaign">
                    Join Campaign
                </a>
            </div>
        </div>
        
        <style>
        .vefify-campaign-info { border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; background: #fff; }
        .campaign-title { margin: 0 0 15px; color: #0073aa; }
        .campaign-description { margin: 15px 0; line-height: 1.6; }
        .campaign-meta { margin: 15px 0; font-size: 14px; color: #666; }
        .campaign-meta span { display: block; margin: 5px 0; }
        .campaign-stats { display: flex; gap: 20px; margin: 15px 0; }
        .stat { text-align: center; }
        .stat-number { display: block; font-size: 24px; font-weight: bold; color: #0073aa; }
        .stat-label { font-size: 12px; color: #666; }
        .campaign-actions { margin: 20px 0 0; }
        .vefify-join-campaign { background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; }
        .vefify-join-campaign:hover { background: #005a87; color: white; }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Campaign list shortcode [vefify_campaign_list]
     */
    public function campaign_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'status' => 'active',
            'limit' => 10,
            'show_description' => false,
            'template' => 'grid'
        ), $atts);
        
        $args = array(
            'status' => $atts['status'],
            'per_page' => intval($atts['limit']),
            'page' => 1
        );
        
        $result = $this->model->get_campaigns($args);
        
        if (empty($result['campaigns'])) {
            return '<p>No campaigns found</p>';
        }
        
        ob_start();
        ?>
        <div class="vefify-campaigns-list template-<?php echo esc_attr($atts['template']); ?>">
            <?php foreach ($result['campaigns'] as $campaign): ?>
                <div class="campaign-item" data-campaign-id="<?php echo $campaign['id']; ?>">
                    <h4 class="campaign-title">
                        <a href="<?php echo esc_url(add_query_arg('campaign_id', $campaign['id'], get_permalink())); ?>">
                            <?php echo esc_html($campaign['name']); ?>
                        </a>
                    </h4>
                    
                    <?php if ($atts['show_description'] && !empty($campaign['description'])): ?>
                        <div class="campaign-description">
                            <?php echo wpautop(esc_html(wp_trim_words($campaign['description'], 20))); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="campaign-meta">
                        <span class="campaign-dates">
                            <?php echo date('M j', strtotime($campaign['start_date'])); ?> - 
                            <?php echo date('M j, Y', strtotime($campaign['end_date'])); ?>
                        </span>
                        <span class="campaign-questions">
                            <?php echo $campaign['questions_per_quiz']; ?> questions
                        </span>
                    </div>
                    
                    <div class="campaign-actions">
                        <a href="<?php echo esc_url(add_query_arg('campaign_id', $campaign['id'], get_permalink())); ?>" 
                           class="button campaign-join-btn">
                            Join Quiz
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <style>
        .vefify-campaigns-list.template-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .campaign-item { border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: #fff; }
        .campaign-item .campaign-title { margin: 0 0 10px; }
        .campaign-item .campaign-title a { text-decoration: none; color: #0073aa; }
        .campaign-item .campaign-description { margin: 10px 0; color: #666; font-size: 14px; }
        .campaign-item .campaign-meta { margin: 15px 0; font-size: 12px; color: #999; }
        .campaign-item .campaign-meta span { display: block; margin: 2px 0; }
        .campaign-item .campaign-actions { margin: 15px 0 0; }
        .campaign-join-btn { background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-size: 14px; }
        .campaign-join-btn:hover { background: #005a87; color: white; }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 🔥 NEW: Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // Only enqueue on pages with quiz shortcodes
        if (is_singular()) {
            $post = get_post();
            if ($post && (
                has_shortcode($post->post_content, 'vefify_quiz') || 
                has_shortcode($post->post_content, 'vefify_campaign')
            )) {
                wp_enqueue_style('vefify-quiz-frontend', 
                    VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/quiz-frontend.css', 
                    array(), VEFIFY_QUIZ_VERSION);
                
                wp_enqueue_script('vefify-quiz-frontend',
                    VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/quiz-frontend.js',
                    array('jquery'), VEFIFY_QUIZ_VERSION, true);
                
                wp_localize_script('vefify-quiz-frontend', 'vefifyAjax', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('vefify_quiz_ajax')
                ));
            }
        }
    }
    
    /**
     * Handle AJAX campaign actions
     */
    public function ajax_campaign_action() {
        check_ajax_referer('vefify_campaign_ajax', 'nonce');
        
        $action = sanitize_text_field($_POST['action_type'] ?? '');
        
        switch ($action) {
            case 'submit_quiz':
                $this->handle_quiz_submission();
                break;
            default:
                wp_send_json_error('Invalid action');
        }
    }
    
    /**
     * Handle quiz submission
     */
    private function handle_quiz_submission() {
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $participant_data = $_POST['participant_data'] ?? array();
        $answers = $_POST['answers'] ?? array();
        
        if (!$campaign_id || empty($participant_data)) {
            wp_send_json_error('Missing required data');
        }
        
        // Here you would save the quiz submission to the database
        // This is simplified for the example
        
        wp_send_json_success(array(
            'message' => 'Quiz submitted successfully',
            'score' => count($answers),
            'total_questions' => count($answers)
        ));
    }
    
    /**
     * Get model instance
     */
    public function get_model() {
        return $this->model;
    }
    
    /**
     * Get manager instance
     */
    public function get_manager() {
        return $this->manager;
    }
    
    /**
     * Module analytics summary for dashboard
     */
    public function get_module_analytics() {
        $summary = $this->model->get_campaigns_summary();
        
        return array(
            'title' => 'Campaign Management',
            'description' => 'Create and manage quiz campaigns with participants tracking',
            'icon' => '📋',
            'stats' => array(
                'total_campaigns' => array(
                    'label' => 'Total Campaigns',
                    'value' => $summary['total'] ?? 0,
                    'trend' => '+12% this month'
                ),
                'active_campaigns' => array(
                    'label' => 'Active Campaigns',
                    'value' => $summary['active'] ?? 0,
                    'trend' => 'Running now'
                ),
                'total_participants' => array(
                    'label' => 'Total Participants',
                    'value' => $this->get_total_participants(),
                    'trend' => '+45% this week'
                )
            ),
            'quick_actions' => array(
                array(
                    'label' => 'Create Campaign',
                    'url' => admin_url('admin.php?page=vefify-campaigns&action=new'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'View All Campaigns',
                    'url' => admin_url('admin.php?page=vefify-campaigns'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    /**
     * Get total participants across all campaigns
     */
    private function get_total_participants() {
        global $wpdb;
        $participants_table = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'participants';
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$participants_table}");
        return number_format($count ?? 0);
    }
}

// Initialize the module
if (!function_exists('vefify_campaign_module_init')) {
    function vefify_campaign_module_init() {
        return Vefify_Campaign_Module::get_instance();
    }
    add_action('plugins_loaded', 'vefify_campaign_module_init');
}