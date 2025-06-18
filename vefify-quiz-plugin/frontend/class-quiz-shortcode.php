<?php
/**
 * Enhanced Quiz Shortcode Class
 * File: frontend/class-quiz-shortcode.php
 * 
 * Handles all shortcode functionality with mobile-first design
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Shortcode {
    
    private $plugin;
    private $database;
    
    public function __construct() {
        $this->plugin = vefify_quiz();
        $this->database = $this->plugin->get_database();
        
        // Register shortcode
        add_shortcode('vefify_quiz', array($this, 'render_quiz'));
        add_shortcode('vefify_campaign', array($this, 'render_campaign_info'));
        
        // Enqueue assets when shortcode is used
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
        
        // AJAX handlers for frontend
        add_action('wp_ajax_vefify_check_participation', array($this, 'ajax_check_participation'));
        add_action('wp_ajax_nopriv_vefify_check_participation', array($this, 'ajax_check_participation'));
        
        add_action('wp_ajax_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        add_action('wp_ajax_nopriv_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        
        add_action('wp_ajax_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
        add_action('wp_ajax_nopriv_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
    }
    
    /**
     * Main quiz shortcode renderer
     */
    public function render_quiz($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 1,
            'template' => 'mobile',
            'show_leaderboard' => 'false',
            'auto_start' => 'false'
        ), $atts);
        
        // Get campaign data
        if (!$this->database) {
            return $this->render_error('Database not available');
        }
        
        try {
            $campaign = $this->get_campaign_data($atts['campaign_id']);
            if (!$campaign) {
                return $this->render_error('Campaign not found or inactive', $atts['campaign_id']);
            }
            
            // Enqueue assets for this shortcode
            $this->enqueue_quiz_assets();
            
            // Render based on template
            switch ($atts['template']) {
                case 'minimal':
                    return $this->render_minimal_template($campaign, $atts);
                case 'full':
                    return $this->render_full_template($campaign, $atts);
                default:
                    return $this->render_mobile_template($campaign, $atts);
            }
            
        } catch (Exception $e) {
            error_log('Vefify Quiz Shortcode Error: ' . $e->getMessage());
            return $this->render_error('An error occurred while loading the quiz');
        }
    }
    
    /**
     * Campaign info shortcode
     */
    public function render_campaign_info($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 1,
            'show' => 'name,description,stats'
        ), $atts);
        
        if (!$this->database) {
            return '<p>Campaign information not available</p>';
        }
        
        try {
            $campaign = $this->get_campaign_data($atts['campaign_id']);
            if (!$campaign) {
                return '<p>Campaign not found</p>';
            }
            
            $show_fields = explode(',', $atts['show']);
            $output = '<div class="vefify-campaign-info">';
            
            if (in_array('name', $show_fields)) {
                $output .= '<h3>' . esc_html($campaign->name) . '</h3>';
            }
            
            if (in_array('description', $show_fields)) {
                $output .= '<p>' . esc_html($campaign->description) . '</p>';
            }
            
            if (in_array('stats', $show_fields)) {
                $stats = $this->get_campaign_stats($campaign->id);
                $output .= '<div class="campaign-stats">';
                $output .= '<span>📝 ' . $stats['questions'] . ' questions</span> | ';
                $output .= '<span>👥 ' . $stats['participants'] . ' participants</span> | ';
                $output .= '<span>🎁 ' . $stats['gifts'] . ' rewards</span>';
                $output .= '</div>';
            }
            
            $output .= '</div>';
            return $output;
            
        } catch (Exception $e) {
            return '<p>Error loading campaign info</p>';
        }
    }
    
    /**
     * Render mobile template (default)
     */
    private function render_mobile_template($campaign, $atts) {
        ob_start();
        ?>
        <!-- Vefify Quiz Mobile Interface -->
        <div class="vefify-quiz-wrapper" data-campaign-id="<?php echo esc_attr($campaign->id); ?>" data-auto-start="<?php echo esc_attr($atts['auto_start']); ?>">
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

                <!-- Registration Form -->
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
                                   placeholder="0901234567" required>
                            <div class="error-message" id="phoneError">Please enter a valid phone number</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="province">
                                <span class="label-text">Province/City *</span>
                                <span class="label-icon">📍</span>
                            </label>
                            <select id="province" name="province" class="form-select" required>
                                <option value="">Select your province/city</option>
                                <?php echo $this->render_province_options(); ?>
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
                        <?php if ($atts['show_leaderboard'] === 'true'): ?>
                        <button type="button" class="btn btn-secondary" id="showLeaderboard">
                            <span class="btn-icon">🏆</span>
                            <span class="btn-text">View Leaderboard</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($atts['show_leaderboard'] === 'true'): ?>
                <!-- Leaderboard Section -->
                <div class="vefify-section" id="leaderboardSection" style="display: none;">
                    <div class="section-header">
                        <h3>🏆 Top Performers</h3>
                        <button class="btn btn-text" onclick="document.getElementById('leaderboardSection').style.display='none'">×</button>
                    </div>
                    <div id="leaderboardContent">Loading...</div>
                </div>
                <?php endif; ?>
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
     * Render minimal template
     */
    private function render_minimal_template($campaign, $atts) {
        ob_start();
        ?>
        <div class="vefify-quiz-minimal" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
            <div class="minimal-header">
                <h3><?php echo esc_html($campaign->name); ?></h3>
            </div>
            <div class="minimal-content">
                <p><?php echo esc_html($campaign->description); ?></p>
                <button class="btn btn-primary" onclick="vefifyStartQuiz(<?php echo $campaign->id; ?>)">
                    Start Quiz
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render full template
     */
    private function render_full_template($campaign, $atts) {
        // Enhanced version with more features
        $mobile_template = $this->render_mobile_template($campaign, $atts);
        
        // Add extra features for full template
        $extra_features = '
        <div class="vefify-extra-features">
            <div class="social-share">
                <h4>Share this Quiz</h4>
                <button class="share-btn facebook">Facebook</button>
                <button class="share-btn twitter">Twitter</button>
                <button class="share-btn linkedin">LinkedIn</button>
            </div>
        </div>';
        
        return str_replace('</div>', $extra_features . '</div>', $mobile_template);
    }
    
    /**
     * Render error message
     */
    private function render_error($message, $campaign_id = null) {
        ob_start();
        ?>
        <div class="vefify-quiz-error">
            <div class="error-content">
                <div class="error-icon">❌</div>
                <h3>Quiz Not Available</h3>
                <p><?php echo esc_html($message); ?></p>
                <?php if ($campaign_id): ?>
                <p><small>Campaign ID: <?php echo esc_html($campaign_id); ?></small></p>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="location.reload()">Retry</button>
            </div>
        </div>
        <style>
        .vefify-quiz-error {
            text-align: center;
            padding: 40px 20px;
            background: #fff;
            border: 2px solid #dc3232;
            border-radius: 8px;
            margin: 20px 0;
        }
        .error-icon { font-size: 3em; margin-bottom: 15px; }
        .vefify-quiz-error h3 { color: #dc3232; margin-bottom: 10px; }
        .vefify-quiz-error p { color: #666; margin-bottom: 20px; }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get campaign data
     */
    private function get_campaign_data($campaign_id) {
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
     * Get campaign statistics
     */
    private function get_campaign_stats($campaign_id) {
        global $wpdb;
        
        $questions_table = $this->database->get_table_name('questions');
        $participants_table = $this->database->get_table_name('participants');
        $gifts_table = $this->database->get_table_name('gifts');
        
        return array(
            'questions' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$questions_table} WHERE campaign_id = %d AND is_active = 1",
                $campaign_id
            )) ?: 0,
            'participants' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$participants_table} WHERE campaign_id = %d",
                $campaign_id
            )) ?: 0,
            'gifts' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$gifts_table} WHERE campaign_id = %d AND is_active = 1",
                $campaign_id
            )) ?: 0
        );
    }
    
    /**
     * Render province options
     */
    private function render_province_options() {
        if (!class_exists('Vefify_Quiz_Utilities')) {
            return '<option value="hcm">Ho Chi Minh City</option><option value="hanoi">Hanoi</option>';
        }
        
        $provinces = Vefify_Quiz_Utilities::get_vietnam_provinces();
        $output = '';
        
        foreach ($provinces as $key => $name) {
            $output .= '<option value="' . esc_attr($key) . '">' . esc_html($name) . '</option>';
        }
        
        return $output;
    }
    
    /**
     * Enqueue quiz assets
     */
    private function enqueue_quiz_assets() {
        // CSS
        wp_enqueue_style(
            'vefify-quiz-style',
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/quiz-mobile.css',
            array(),
            VEFIFY_QUIZ_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'vefify-quiz-script',
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/quiz-mobile.js',
            array('jquery'),
            VEFIFY_QUIZ_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('vefify-quiz-script', 'vefifyQuiz', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_quiz_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'vefify-quiz'),
                'error' => __('An error occurred. Please try again.', 'vefify-quiz'),
                'success' => __('Success!', 'vefify-quiz'),
                'confirm_submit' => __('Are you sure you want to submit your answers?', 'vefify-quiz')
            )
        ));
    }
    
    /**
     * Maybe enqueue assets (only when shortcode is used)
     */
    public function maybe_enqueue_assets() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vefify_quiz')) {
            $this->enqueue_quiz_assets();
        }
    }
    
    /**
     * AJAX: Check participation
     */
    public function ajax_check_participation() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        
        if (!$phone || !$campaign_id) {
            wp_send_json_error('Invalid data');
        }
        
        try {
            // Check if already participated
            global $wpdb;
            $participants_table = $this->database->get_table_name('participants');
            
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$participants_table} WHERE campaign_id = %d AND phone_number = %s",
                $campaign_id,
                Vefify_Quiz_Utilities::format_phone_number($phone)
            ));
            
            wp_send_json_success(array(
                'can_participate' => !$existing,
                'message' => $existing ? 'You have already participated in this quiz' : 'You can participate'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Database error');
        }
    }
    
    /**
     * AJAX: Start quiz
     */
    public function ajax_start_quiz() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $user_data = $_POST['user_data'] ?? array();
        
        if (!$campaign_id || empty($user_data)) {
            wp_send_json_error('Invalid data');
        }
        
        try {
            // Validate and sanitize user data
            $sanitized_data = Vefify_Quiz_Utilities::sanitize_quiz_data($user_data);
            
            // Check participation again
            global $wpdb;
            $participants_table = $this->database->get_table_name('participants');
            
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$participants_table} WHERE campaign_id = %d AND phone_number = %s",
                $campaign_id,
                $sanitized_data['phone_number']
            ));
            
            if ($existing) {
                wp_send_json_error('Already participated');
            }
            
            // Get questions
            $questions = $this->get_quiz_questions($campaign_id);
            if (empty($questions)) {
                wp_send_json_error('No questions available');
            }
            
            // Create participant record
            $session_id = Vefify_Quiz_Utilities::generate_session_id();
            
            $participant_id = $wpdb->insert($participants_table, array(
                'campaign_id' => $campaign_id,
                'session_id' => $session_id,
                'full_name' => $sanitized_data['full_name'],
                'phone_number' => $sanitized_data['phone_number'],
                'province' => $sanitized_data['province'],
                'pharmacy_code' => $sanitized_data['pharmacy_code'],
                'quiz_status' => 'started',
                'started_at' => current_time('mysql'),
                'ip_address' => Vefify_Quiz_Utilities::get_client_ip()
            ));
            
            if (!$participant_id) {
                wp_send_json_error('Failed to create participant record');
            }
            
            wp_send_json_success(array(
                'session_id' => $session_id,
                'participant_id' => $wpdb->insert_id,
                'questions' => $questions
            ));
            
        } catch (Exception $e) {
            error_log('Start Quiz Error: ' . $e->getMessage());
            wp_send_json_error('Server error');
        }
    }
    
    /**
     * AJAX: Submit quiz
     */
    public function ajax_submit_quiz() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $answers = $_POST['answers'] ?? array();
        
        if (!$session_id || empty($answers)) {
            wp_send_json_error('Invalid submission data');
        }
        
        try {
            // Get participant
            global $wpdb;
            $participants_table = $this->database->get_table_name('participants');
            
            $participant = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$participants_table} WHERE session_id = %s AND quiz_status = 'started'",
                $session_id
            ));
            
            if (!$participant) {
                wp_send_json_error('Invalid session or quiz already completed');
            }
            
            // Get questions and calculate score
            $questions = $this->get_quiz_questions($participant->campaign_id);
            $score_result = Vefify_Quiz_Utilities::calculate_score($answers, $questions);
            
            // Update participant record
            $wpdb->update(
                $participants_table,
                array(
                    'quiz_status' => 'completed',
                    'score' => $score_result['score'],
                    'total_questions' => count($questions),
                    'completion_percentage' => $score_result['percentage'],
                    'answers_data' => json_encode($answers),
                    'completed_at' => current_time('mysql')
                ),
                array('id' => $participant->id)
            );
            
            // Check for gifts
            $gift_result = $this->assign_gift($participant->campaign_id, $participant->id, $score_result['score']);
            
            wp_send_json_success(array(
                'score' => $score_result['score'],
                'total_questions' => count($questions),
                'percentage' => $score_result['percentage'],
                'gift' => $gift_result
            ));
            
        } catch (Exception $e) {
            error_log('Submit Quiz Error: ' . $e->getMessage());
            wp_send_json_error('Submission failed');
        }
    }
    
    /**
     * Get quiz questions
     */
    private function get_quiz_questions($campaign_id) {
        global $wpdb;
        $questions_table = $this->database->get_table_name('questions');
        $options_table = $this->database->get_table_name('question_options');
        
        // Get campaign info for question limit
        $campaign = $this->get_campaign_data($campaign_id);
        $limit = $campaign->questions_per_quiz ?? 5;
        
        // Get questions
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$questions_table} 
             WHERE (campaign_id = %d OR campaign_id IS NULL) AND is_active = 1 
             ORDER BY RAND() 
             LIMIT %d",
            $campaign_id, $limit
        ));
        
        // Get options for each question
        foreach ($questions as &$question) {
            $question->options = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$options_table} WHERE question_id = %d ORDER BY order_index",
                $question->id
            ));
        }
        
        return $questions;
    }
    
    /**
     * Assign gift based on score
     */
    private function assign_gift($campaign_id, $participant_id, $score) {
        global $wpdb;
        $gifts_table = $this->database->get_table_name('gifts');
        $participants_table = $this->database->get_table_name('participants');
        
        // Find eligible gift
        $gift = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$gifts_table} 
             WHERE campaign_id = %d AND is_active = 1 
             AND min_score <= %d AND (max_score IS NULL OR max_score >= %d)
             AND (max_quantity IS NULL OR used_count < max_quantity)
             ORDER BY min_score DESC, gift_value DESC
             LIMIT 1",
            $campaign_id, $score, $score
        ));
        
        if (!$gift) {
            return array('has_gift' => false, 'message' => 'No gift available for your score');
        }
        
        // Generate gift code
        $gift_code = Vefify_Quiz_Utilities::generate_gift_code($gift->gift_code_prefix ?? 'GIFT');
        
        // Update participant with gift
        $wpdb->update(
            $participants_table,
            array(
                'gift_id' => $gift->id,
                'gift_code' => $gift_code,
                'gift_status' => 'assigned'
            ),
            array('id' => $participant_id)
        );
        
        // Update gift usage
        $wpdb->update(
            $gifts_table,
            array('used_count' => $gift->used_count + 1),
            array('id' => $gift->id)
        );
        
        return array(
            'has_gift' => true,
            'gift_name' => $gift->gift_name,
            'gift_code' => $gift_code,
            'gift_value' => $gift->gift_value,
            'gift_description' => $gift->gift_description
        );
    }
}