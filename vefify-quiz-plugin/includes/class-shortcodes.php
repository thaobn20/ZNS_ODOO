<?php
/**
 * COMPLETE QUIZ SHORTCODE SYSTEM
 * File: includes/class-shortcodes.php
 * 
 * Provides frontend quiz display with custom field collection
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Shortcodes {
    
    private $database;
    
    public function __construct() {
        $this->database = new Vefify_Quiz_Database();
        $this->init();
    }
    
    public function init() {
        // Register shortcodes
        add_shortcode('vefify_quiz', array($this, 'render_quiz'));
        add_shortcode('vefify_quiz_list', array($this, 'render_quiz_list'));
        add_shortcode('vefify_quiz_form', array($this, 'render_quiz_form'));
        
        // AJAX handlers for frontend
        add_action('wp_ajax_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        add_action('wp_ajax_nopriv_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        add_action('wp_ajax_vefify_submit_answer', array($this, 'ajax_submit_answer'));
        add_action('wp_ajax_nopriv_vefify_submit_answer', array($this, 'ajax_submit_answer'));
        add_action('wp_ajax_vefify_finish_quiz', array($this, 'ajax_finish_quiz'));
        add_action('wp_ajax_nopriv_vefify_finish_quiz', array($this, 'ajax_finish_quiz'));
        
        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * Main quiz shortcode with custom fields
     * Usage: [vefify_quiz campaign_id="1" fields="name,email,phone,province,pharmacy_code"]
     */
    public function render_quiz($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'fields' => 'name,email,phone,province', // Default fields
            'title' => '',
            'description' => '',
            'style' => 'default' // default, modern, minimal
        ), $atts);
        
        if (empty($atts['campaign_id'])) {
            return '<div class="vefify-error">Error: Campaign ID is required</div>';
        }
        
        $campaign = $this->get_campaign($atts['campaign_id']);
        if (!$campaign) {
            return '<div class="vefify-error">Error: Campaign not found</div>';
        }
        
        // Parse custom fields
        $custom_fields = array_map('trim', explode(',', $atts['fields']));
        
        ob_start();
        ?>
        <div class="vefify-quiz-container" data-campaign-id="<?php echo esc_attr($atts['campaign_id']); ?>" data-style="<?php echo esc_attr($atts['style']); ?>">
            
            <!-- Quiz Header -->
            <div class="vefify-quiz-header">
                <h2 class="vefify-quiz-title">
                    <?php echo esc_html($atts['title'] ?: $campaign->name); ?>
                </h2>
                <?php if ($atts['description'] || $campaign->description): ?>
                    <p class="vefify-quiz-description">
                        <?php echo esc_html($atts['description'] ?: $campaign->description); ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Registration Form -->
            <div class="vefify-registration-form" id="vefify-registration-<?php echo $atts['campaign_id']; ?>">
                <h3>📝 Participant Information</h3>
                <form id="vefify-participant-form" class="vefify-form">
                    
                    <?php foreach ($custom_fields as $field): ?>
                        <?php $this->render_field($field); ?>
                    <?php endforeach; ?>
                    
                    <!-- Privacy/Terms -->
                    <div class="vefify-field">
                        <label class="vefify-checkbox">
                            <input type="checkbox" name="agree_terms" required>
                            <span class="checkmark"></span>
                            I agree to participate in this quiz and allow my data to be collected
                        </label>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="vefify-field">
                        <button type="submit" class="vefify-btn vefify-btn-primary">
                            🚀 Start Quiz
                        </button>
                    </div>
                    
                    <input type="hidden" name="campaign_id" value="<?php echo esc_attr($atts['campaign_id']); ?>">
                    <?php wp_nonce_field('vefify_start_quiz', 'vefify_nonce'); ?>
                </form>
            </div>
            
            <!-- Quiz Interface (hidden initially) -->
            <div class="vefify-quiz-interface" id="vefify-quiz-<?php echo $atts['campaign_id']; ?>" style="display: none;">
                
                <!-- Quiz Progress -->
                <div class="vefify-quiz-progress">
                    <div class="vefify-progress-bar">
                        <div class="vefify-progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="vefify-progress-text">
                        Question <span class="current">1</span> of <span class="total"><?php echo $campaign->questions_per_quiz; ?></span>
                    </div>
                </div>
                
                <!-- Timer (if enabled) -->
                <?php if ($campaign->time_limit): ?>
                <div class="vefify-quiz-timer">
                    ⏱️ Time remaining: <span id="vefify-timer"><?php echo $campaign->time_limit; ?></span> seconds
                </div>
                <?php endif; ?>
                
                <!-- Question Container -->
                <div class="vefify-question-container">
                    <div class="vefify-question">
                        <h3 class="vefify-question-text"></h3>
                        <div class="vefify-question-options"></div>
                        
                        <div class="vefify-question-nav">
                            <button type="button" class="vefify-btn vefify-btn-secondary" id="vefify-prev-btn" style="display: none;">
                                ← Previous
                            </button>
                            <button type="button" class="vefify-btn vefify-btn-primary" id="vefify-next-btn">
                                Next →
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Results Display -->
            <div class="vefify-quiz-results" id="vefify-results-<?php echo $atts['campaign_id']; ?>" style="display: none;">
                <div class="vefify-results-content">
                    <h3>🎉 Quiz Complete!</h3>
                    <div class="vefify-score-display">
                        <div class="vefify-score-circle">
                            <span class="vefify-score-number"></span>
                            <span class="vefify-score-total"></span>
                        </div>
                        <div class="vefify-score-percentage"></div>
                    </div>
                    
                    <div class="vefify-gift-info" style="display: none;">
                        <h4>🎁 Congratulations!</h4>
                        <div class="vefify-gift-details"></div>
                    </div>
                    
                    <div class="vefify-results-actions">
                        <button type="button" class="vefify-btn vefify-btn-secondary" onclick="location.reload()">
                            🔄 Take Quiz Again
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Loading States -->
            <div class="vefify-loading" style="display: none;">
                <div class="vefify-spinner"></div>
                <p>Loading...</p>
            </div>
            
        </div>
        
        <?php
        $this->render_quiz_styles($atts['style']);
        $this->render_quiz_scripts();
        
        return ob_get_clean();
    }
    
    /**
     * Render custom form fields
     */
    private function render_field($field) {
        $field = trim($field);
        
        switch ($field) {
            case 'name':
                ?>
                <div class="vefify-field">
                    <label for="participant_name">👤 Full Name *</label>
                    <input type="text" name="participant_name" id="participant_name" required 
                           placeholder="Enter your full name">
                </div>
                <?php
                break;
                
            case 'email':
                ?>
                <div class="vefify-field">
                    <label for="participant_email">📧 Email Address *</label>
                    <input type="email" name="participant_email" id="participant_email" required 
                           placeholder="Enter your email address">
                </div>
                <?php
                break;
                
            case 'phone':
                ?>
                <div class="vefify-field">
                    <label for="participant_phone">📱 Phone Number *</label>
                    <input type="tel" name="participant_phone" id="participant_phone" required 
                           placeholder="+84 901 234 567">
                </div>
                <?php
                break;
                
            case 'province':
                ?>
                <div class="vefify-field">
                    <label for="province">🌍 Province/City *</label>
                    <select name="province" id="province" required>
                        <option value="">Select your province</option>
                        <option value="Ho Chi Minh">Ho Chi Minh City</option>
                        <option value="Ha Noi">Ha Noi</option>
                        <option value="Da Nang">Da Nang</option>
                        <option value="Can Tho">Can Tho</option>
                        <option value="Hai Phong">Hai Phong</option>
                        <option value="Dong Nai">Dong Nai</option>
                        <option value="Binh Duong">Binh Duong</option>
                        <option value="Khanh Hoa">Khanh Hoa</option>
                        <option value="Nghe An">Nghe An</option>
                        <option value="Gia Lai">Gia Lai</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <?php
                break;
                
            case 'pharmacy_code':
                ?>
                <div class="vefify-field">
                    <label for="pharmacy_code">🏥 Pharmacy Code</label>
                    <input type="text" name="pharmacy_code" id="pharmacy_code" 
                           placeholder="Enter pharmacy code (optional)">
                </div>
                <?php
                break;
                
            case 'age':
                ?>
                <div class="vefify-field">
                    <label for="age">🎂 Age</label>
                    <input type="number" name="age" id="age" min="16" max="100" 
                           placeholder="Enter your age">
                </div>
                <?php
                break;
                
            case 'gender':
                ?>
                <div class="vefify-field">
                    <label>👥 Gender</label>
                    <div class="vefify-radio-group">
                        <label class="vefify-radio">
                            <input type="radio" name="gender" value="male">
                            <span class="radio-mark"></span>
                            Male
                        </label>
                        <label class="vefify-radio">
                            <input type="radio" name="gender" value="female">
                            <span class="radio-mark"></span>
                            Female
                        </label>
                        <label class="vefify-radio">
                            <input type="radio" name="gender" value="other">
                            <span class="radio-mark"></span>
                            Other
                        </label>
                    </div>
                </div>
                <?php
                break;
                
            case 'occupation':
                ?>
                <div class="vefify-field">
                    <label for="occupation">💼 Occupation</label>
                    <select name="occupation" id="occupation">
                        <option value="">Select occupation</option>
                        <option value="pharmacist">Pharmacist</option>
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="engineer">Engineer</option>
                        <option value="business">Business</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <?php
                break;
                
            case 'company':
                ?>
                <div class="vefify-field">
                    <label for="company">🏢 Company/Organization</label>
                    <input type="text" name="company" id="company" 
                           placeholder="Enter your company name">
                </div>
                <?php
                break;
                
            default:
                // Custom field - render as text input
                ?>
                <div class="vefify-field">
                    <label for="<?php echo esc_attr($field); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $field))); ?></label>
                    <input type="text" name="<?php echo esc_attr($field); ?>" id="<?php echo esc_attr($field); ?>" 
                           placeholder="Enter <?php echo esc_attr(str_replace('_', ' ', $field)); ?>">
                </div>
                <?php
                break;
        }
    }
    
    /**
     * Quiz list shortcode
     * Usage: [vefify_quiz_list limit="5" style="grid"]
     */
    public function render_quiz_list($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'style' => 'list', // list, grid, cards
            'show_description' => 'true',
            'show_stats' => 'true'
        ), $atts);
        
        global $wpdb;
        $campaigns_table = $this->database->get_table_name('campaigns');
        
        $campaigns = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$campaigns_table} 
            WHERE is_active = 1 
            AND start_date <= NOW() 
            AND end_date >= NOW()
            ORDER BY created_at DESC 
            LIMIT %d
        ", $atts['limit']));
        
        if (empty($campaigns)) {
            return '<div class="vefify-message">No active quizzes available at the moment.</div>';
        }
        
        ob_start();
        ?>
        <div class="vefify-quiz-list vefify-style-<?php echo esc_attr($atts['style']); ?>">
            <?php foreach ($campaigns as $campaign): ?>
                <div class="vefify-quiz-item">
                    <h3 class="vefify-quiz-item-title">
                        <a href="<?php echo add_query_arg('quiz', $campaign->slug, get_permalink()); ?>">
                            <?php echo esc_html($campaign->name); ?>
                        </a>
                    </h3>
                    
                    <?php if ($atts['show_description'] === 'true' && $campaign->description): ?>
                        <p class="vefify-quiz-item-description">
                            <?php echo esc_html($campaign->description); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_stats'] === 'true'): ?>
                        <div class="vefify-quiz-item-stats">
                            <span class="vefify-stat">📝 <?php echo $campaign->questions_per_quiz; ?> questions</span>
                            <?php if ($campaign->time_limit): ?>
                                <span class="vefify-stat">⏱️ <?php echo round($campaign->time_limit / 60); ?> minutes</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="vefify-quiz-item-actions">
                        <a href="#" class="vefify-btn vefify-btn-primary" 
                           onclick="startQuiz(<?php echo $campaign->id; ?>); return false;">
                            🚀 Start Quiz
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Simple quiz form shortcode
     * Usage: [vefify_quiz_form campaign_id="1"]
     */
    public function render_quiz_form($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'redirect' => '',
            'button_text' => 'Start Quiz'
        ), $atts);
        
        if (empty($atts['campaign_id'])) {
            return '<div class="vefify-error">Error: Campaign ID is required</div>';
        }
        
        ob_start();
        ?>
        <div class="vefify-simple-form">
            <form action="<?php echo esc_url($atts['redirect'] ?: '#'); ?>" method="get">
                <input type="hidden" name="vefify_quiz" value="<?php echo esc_attr($atts['campaign_id']); ?>">
                <button type="submit" class="vefify-btn vefify-btn-primary">
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
            </form>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get campaign data
     */
    private function get_campaign($campaign_id) {
        global $wpdb;
        $campaigns_table = $this->database->get_table_name('campaigns');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$campaigns_table} WHERE id = %d AND is_active = 1",
            $campaign_id
        ));
    }
    
    /**
     * AJAX: Start quiz
     */
    public function ajax_start_quiz() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['vefify_nonce'], 'vefify_start_quiz')) {
            wp_send_json_error('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $participant_data = array();
        
        // Collect all form fields
        $allowed_fields = array(
            'participant_name', 'participant_email', 'participant_phone', 
            'province', 'pharmacy_code', 'age', 'gender', 'occupation', 'company'
        );
        
        foreach ($allowed_fields as $field) {
            if (isset($_POST[$field])) {
                $participant_data[$field] = sanitize_text_field($_POST[$field]);
            }
        }
        
        // Generate session ID
        $session_id = 'quiz_' . time() . '_' . wp_generate_password(8, false);
        
        // Save participant
        $participant_data['campaign_id'] = $campaign_id;
        $participant_data['session_id'] = $session_id;
        $participant_data['quiz_status'] = 'started';
        $participant_data['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $participant_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        
        global $wpdb;
        $participants_table = $this->database->get_table_name('participants');
        
        $result = $wpdb->insert($participants_table, $participant_data);
        
        if ($result) {
            $participant_id = $wpdb->insert_id;
            
            // Get quiz questions
            $questions = $this->get_quiz_questions($campaign_id);
            
            wp_send_json_success(array(
                'participant_id' => $participant_id,
                'session_id' => $session_id,
                'questions' => $questions,
                'message' => 'Quiz started successfully!'
            ));
        } else {
            wp_send_json_error('Failed to start quiz. Please try again.');
        }
    }
    
    /**
     * Get quiz questions for campaign
     */
    private function get_quiz_questions($campaign_id) {
        global $wpdb;
        $questions_table = $this->database->get_table_name('questions');
        $options_table = $this->database->get_table_name('question_options');
        
        // Get campaign details
        $campaigns_table = $this->database->get_table_name('campaigns');
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT questions_per_quiz FROM {$campaigns_table} WHERE id = %d",
            $campaign_id
        ));
        
        $limit = $campaign ? $campaign->questions_per_quiz : 5;
        
        // Get random questions
        $questions = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$questions_table}
            WHERE campaign_id = %d AND is_active = 1
            ORDER BY RAND()
            LIMIT %d
        ", $campaign_id, $limit));
        
        // Get options for each question
        foreach ($questions as &$question) {
            $question->options = $wpdb->get_results($wpdb->prepare("
                SELECT id, option_text, explanation
                FROM {$options_table}
                WHERE question_id = %d
                ORDER BY order_index
            ", $question->id));
        }
        
        return $questions;
    }
    
    /**
     * AJAX: Submit answer
     */
    public function ajax_submit_answer() {
        $participant_id = intval($_POST['participant_id']);
        $question_id = intval($_POST['question_id']);
        $answer = sanitize_text_field($_POST['answer']);
        
        // Here you would save the answer and return feedback
        wp_send_json_success(array(
            'correct' => true, // Determine if answer is correct
            'explanation' => 'Great job!',
            'next_question' => true
        ));
    }
    
    /**
     * AJAX: Finish quiz
     */
    public function ajax_finish_quiz() {
        $participant_id = intval($_POST['participant_id']);
        $answers = $_POST['answers']; // Array of answers
        
        // Calculate score and update participant
        $score = $this->calculate_score($answers);
        
        global $wpdb;
        $participants_table = $this->database->get_table_name('participants');
        
        $wpdb->update(
            $participants_table,
            array(
                'final_score' => $score['score'],
                'total_questions' => $score['total'],
                'quiz_status' => 'completed',
                'completed_at' => current_time('mysql'),
                'answers_data' => json_encode($answers)
            ),
            array('id' => $participant_id)
        );
        
        // Check for gifts
        $gift = $this->check_for_gift($participant_id, $score['score']);
        
        wp_send_json_success(array(
            'score' => $score['score'],
            'total' => $score['total'],
            'percentage' => round(($score['score'] / $score['total']) * 100, 1),
            'gift' => $gift,
            'message' => 'Quiz completed successfully!'
        ));
    }
    
    /**
     * Calculate quiz score
     */
    private function calculate_score($answers) {
        // Implementation for score calculation
        return array('score' => 4, 'total' => 5); // Example
    }
    
    /**
     * Check for eligible gifts
     */
    private function check_for_gift($participant_id, $score) {
        // Implementation for gift checking
        return null; // Return gift data if eligible
    }
    
    /**
     * Render quiz styles
     */
    private function render_quiz_styles($style) {
        ?>
        <style>
        .vefify-quiz-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .vefify-quiz-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .vefify-quiz-title {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .vefify-quiz-description {
            color: #7f8c8d;
            font-size: 16px;
            line-height: 1.5;
        }
        
        .vefify-form {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid #e1e8ed;
        }
        
        .vefify-field {
            margin-bottom: 20px;
        }
        
        .vefify-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .vefify-field input,
        .vefify-field select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .vefify-field input:focus,
        .vefify-field select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .vefify-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .vefify-btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .vefify-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .vefify-btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .vefify-quiz-progress {
            margin-bottom: 30px;
        }
        
        .vefify-progress-bar {
            width: 100%;
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .vefify-progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            transition: width 0.5s ease;
        }
        
        .vefify-progress-text {
            text-align: center;
            margin-top: 10px;
            color: #7f8c8d;
        }
        
        .vefify-quiz-timer {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background: #fff3cd;
            border-radius: 6px;
            color: #856404;
        }
        
        .vefify-question {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .vefify-question-text {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .vefify-question-options {
            margin-bottom: 30px;
        }
        
        .vefify-option {
            display: block;
            padding: 15px 20px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .vefify-option:hover {
            background: #e3f2fd;
            border-color: #3498db;
        }
        
        .vefify-option.selected {
            background: #e3f2fd;
            border-color: #3498db;
            color: #1976d2;
        }
        
        .vefify-question-nav {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }
        
        .vefify-results-content {
            text-align: center;
            padding: 40px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .vefify-score-circle {
            width: 120px;
            height: 120px;
            border: 8px solid #3498db;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .vefify-loading {
            text-align: center;
            padding: 40px;
        }
        
        .vefify-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: vefify-spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes vefify-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .vefify-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #f5c6cb;
        }
        
        .vefify-message {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #bee5eb;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .vefify-quiz-container {
                padding: 15px;
            }
            
            .vefify-form,
            .vefify-question {
                padding: 20px;
            }
            
            .vefify-question-nav {
                flex-direction: column;
            }
        }
        
        /* Style variations */
        .vefify-quiz-container[data-style="modern"] {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            color: white;
        }
        
        .vefify-quiz-container[data-style="minimal"] {
            background: none;
            box-shadow: none;
        }
        
        .vefify-quiz-container[data-style="minimal"] .vefify-form {
            box-shadow: none;
            border: 1px solid #e1e8ed;
        }
        </style>
        <?php
    }
    
    /**
     * Render quiz JavaScript
     */
    private function render_quiz_scripts() {
        ?>
        <script>
        // Quiz JavaScript functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize quiz forms
            const quizForms = document.querySelectorAll('#vefify-participant-form');
            
            quizForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    startQuiz(this);
                });
            });
        });
        
        function startQuiz(form) {
            // Show loading
            const container = form.closest('.vefify-quiz-container');
            const loading = container.querySelector('.vefify-loading');
            loading.style.display = 'block';
            
            // Collect form data
            const formData = new FormData(form);
            formData.append('action', 'vefify_start_quiz');
            
            // Submit via AJAX
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                
                if (data.success) {
                    // Hide registration form
                    container.querySelector('.vefify-registration-form').style.display = 'none';
                    
                    // Show quiz interface
                    const quizInterface = container.querySelector('.vefify-quiz-interface');
                    quizInterface.style.display = 'block';
                    
                    // Initialize quiz with questions
                    initializeQuiz(container, data.data);
                } else {
                    alert('Error: ' + data.data);
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                alert('Error starting quiz. Please try again.');
            });
        }
        
        function initializeQuiz(container, quizData) {
            // Quiz initialization logic
            const questions = quizData.questions;
            let currentQuestion = 0;
            
            // Display first question
            displayQuestion(container, questions[currentQuestion]);
            
            // Set up navigation
            setupQuizNavigation(container, questions, quizData);
        }
        
        function displayQuestion(container, question) {
            const questionText = container.querySelector('.vefify-question-text');
            const optionsContainer = container.querySelector('.vefify-question-options');
            
            questionText.textContent = question.question_text;
            
            // Clear previous options
            optionsContainer.innerHTML = '';
            
            // Add new options
            question.options.forEach((option, index) => {
                const optionElement = document.createElement('label');
                optionElement.className = 'vefify-option';
                optionElement.innerHTML = `
                    <input type="radio" name="answer" value="${option.id}" style="display: none;">
                    ${option.option_text}
                `;
                
                optionElement.addEventListener('click', function() {
                    // Remove previous selection
                    container.querySelectorAll('.vefify-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    // Mark as selected
                    this.classList.add('selected');
                    this.querySelector('input').checked = true;
                });
                
                optionsContainer.appendChild(optionElement);
            });
        }
        
        function setupQuizNavigation(container, questions, quizData) {
            // Navigation setup logic
            let currentQuestion = 0;
            const totalQuestions = questions.length;
            
            const nextBtn = container.querySelector('#vefify-next-btn');
            const prevBtn = container.querySelector('#vefify-prev-btn');
            const progressFill = container.querySelector('.vefify-progress-fill');
            const progressText = container.querySelector('.vefify-progress-text');
            
            nextBtn.addEventListener('click', function() {
                // Handle next question or finish quiz
                if (currentQuestion < totalQuestions - 1) {
                    currentQuestion++;
                    displayQuestion(container, questions[currentQuestion]);
                    updateProgress();
                } else {
                    finishQuiz(container, quizData);
                }
            });
            
            function updateProgress() {
                const progress = ((currentQuestion + 1) / totalQuestions) * 100;
                progressFill.style.width = progress + '%';
                progressText.querySelector('.current').textContent = currentQuestion + 1;
                
                // Update buttons
                prevBtn.style.display = currentQuestion > 0 ? 'block' : 'none';
                nextBtn.textContent = currentQuestion === totalQuestions - 1 ? 'Finish Quiz' : 'Next →';
            }
            
            updateProgress();
        }
        
        function finishQuiz(container, quizData) {
            // Show results
            container.querySelector('.vefify-quiz-interface').style.display = 'none';
            container.querySelector('.vefify-quiz-results').style.display = 'block';
            
            // Here you would calculate and display the final score
            // For now, show a simple completion message
            const scoreNumber = container.querySelector('.vefify-score-number');
            const scoreTotal = container.querySelector('.vefify-score-total');
            const scorePercentage = container.querySelector('.vefify-score-percentage');
            
            // Example results
            scoreNumber.textContent = '4';
            scoreTotal.textContent = '/ 5';
            scorePercentage.textContent = '80% Score';
        }
        </script>
        <?php
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        if (is_admin()) return;
        
        wp_enqueue_script('vefify-quiz-frontend', plugin_dir_url(__FILE__) . '../assets/js/quiz-frontend.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('vefify-quiz-frontend', plugin_dir_url(__FILE__) . '../assets/css/quiz-frontend.css', array(), '1.0.0');
        
        wp_localize_script('vefify-quiz-frontend', 'vefify_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_quiz_nonce')
        ));
    }
}

// Initialize shortcodes
new Vefify_Quiz_Shortcodes();
?>