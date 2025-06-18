<?php
/**
 * COMPLETE SHORTCODE FIX - Add this to your main plugin file or create a new shortcode handler
 * File: modules/frontend/class-quiz-shortcode.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Shortcode {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    /**
     * Register all quiz shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('vefify_quiz', array($this, 'render_quiz'));
        add_shortcode('vefify_campaign', array($this, 'render_campaign_info'));
    }
    
    /**
     * Main quiz shortcode handler: [vefify_quiz campaign_id="1"]
     */
    public function render_quiz($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'template' => 'default',
            'theme' => 'light'
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
        
        // Get questions for this campaign
        $questions = $this->get_campaign_questions($campaign_id);
        
        if (empty($questions)) {
            return '<div class="vefify-error">❌ No questions found for this campaign.</div>';
        }
        
        // Generate unique quiz instance ID
        $quiz_id = 'quiz_' . $campaign_id . '_' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($quiz_id); ?>" class="vefify-quiz-container" data-campaign-id="<?php echo $campaign_id; ?>">
            <?php echo $this->render_quiz_template($campaign, $questions, $atts); ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize quiz functionality
            if (typeof VefifyQuiz !== 'undefined') {
                VefifyQuiz.init('<?php echo $quiz_id; ?>', {
                    campaignId: <?php echo $campaign_id; ?>,
                    questions: <?php echo json_encode($questions); ?>,
                    settings: <?php echo json_encode($this->get_quiz_settings($campaign)); ?>
                });
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render quiz template based on theme
     */
    private function render_quiz_template($campaign, $questions, $atts) {
        $template = $atts['template'];
        $theme = $atts['theme'];
        
        ob_start();
        ?>
        <div class="vefify-quiz-wrapper theme-<?php echo esc_attr($theme); ?>">
            <!-- Quiz Header -->
            <div class="quiz-header">
                <h2 class="quiz-title"><?php echo esc_html($campaign['name']); ?></h2>
                <?php if (!empty($campaign['description'])): ?>
                    <p class="quiz-description"><?php echo esc_html($campaign['description']); ?></p>
                <?php endif; ?>
                
                <div class="quiz-meta">
                    <span class="quiz-questions">📝 <?php echo count($questions); ?> Questions</span>
                    <span class="quiz-time">⏱️ <?php echo $campaign['time_limit']; ?> seconds</span>
                    <span class="quiz-pass-score">🎯 Pass Score: <?php echo $campaign['pass_score']; ?></span>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="quiz-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-text">Question <span class="current-question">0</span> of <span class="total-questions"><?php echo count($questions); ?></span></div>
            </div>
            
            <!-- Quiz Content Area -->
            <div class="quiz-content">
                <!-- Start Screen -->
                <div class="quiz-screen start-screen active">
                    <div class="start-content">
                        <h3>Ready to start?</h3>
                        <p>You have <strong><?php echo $campaign['time_limit']; ?> seconds</strong> to complete this quiz.</p>
                        <p>You need <strong><?php echo $campaign['pass_score']; ?> correct answers</strong> to pass.</p>
                        <button class="btn btn-primary start-quiz-btn">🚀 Start Quiz</button>
                    </div>
                </div>
                
                <!-- Question Screen -->
                <div class="quiz-screen question-screen">
                    <div class="question-container">
                        <h3 class="question-text"></h3>
                        <div class="question-options"></div>
                        <div class="question-actions">
                            <button class="btn btn-secondary prev-btn" style="display: none;">← Previous</button>
                            <button class="btn btn-primary next-btn">Next →</button>
                        </div>
                    </div>
                </div>
                
                <!-- Results Screen -->
                <div class="quiz-screen results-screen">
                    <div class="results-content">
                        <h3>Quiz Complete! 🎉</h3>
                        <div class="score-display">
                            <div class="score-circle">
                                <span class="score-number">0</span>
                                <span class="score-total">/ <?php echo count($questions); ?></span>
                            </div>
                        </div>
                        <div class="results-details">
                            <p class="result-message"></p>
                            <div class="result-breakdown"></div>
                        </div>
                        <div class="result-actions">
                            <button class="btn btn-primary restart-btn">🔄 Try Again</button>
                            <button class="btn btn-secondary share-btn">📤 Share Results</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Timer Display -->
            <div class="quiz-timer">
                <span class="timer-icon">⏰</span>
                <span class="timer-text">Time: <span class="time-remaining"><?php echo $campaign['time_limit']; ?></span>s</span>
            </div>
        </div>
        
        <style>
        .vefify-quiz-wrapper {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        .quiz-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .quiz-title {
            margin: 0 0 10px;
            font-size: 24px;
            font-weight: 600;
        }
        
        .quiz-description {
            margin: 0 0 20px;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .quiz-meta {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 13px;
        }
        
        .quiz-progress {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
            transition: width 0.3s ease;
        }
        
        .progress-text {
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        
        .quiz-content {
            min-height: 300px;
            position: relative;
        }
        
        .quiz-screen {
            display: none;
            padding: 30px 20px;
        }
        
        .quiz-screen.active {
            display: block;
        }
        
        .start-content {
            text-align: center;
        }
        
        .question-container h3 {
            margin-bottom: 20px;
            font-size: 18px;
            line-height: 1.5;
        }
        
        .question-options {
            margin-bottom: 30px;
        }
        
        .option-item {
            padding: 15px;
            margin-bottom: 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .option-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .option-item.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        
        .question-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .quiz-timer {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .results-content {
            text-align: center;
        }
        
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px auto;
            color: white;
            font-size: 24px;
            font-weight: bold;
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
        
        /* Responsive */
        @media (max-width: 600px) {
            .vefify-quiz-wrapper {
                margin: 10px;
                border-radius: 8px;
            }
            
            .quiz-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .question-actions {
                flex-direction: column;
            }
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get campaign data
     */
    private function get_campaign_data($campaign_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'vefify_campaigns';
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $campaign_id
        ), ARRAY_A);
        
        return $campaign;
    }
    
    /**
     * Check if campaign is active
     */
    private function is_campaign_active($campaign) {
        if (!$campaign['is_active']) {
            return false;
        }
        
        $now = current_time('timestamp');
        $start = strtotime($campaign['start_date']);
        $end = strtotime($campaign['end_date']);
        
        return ($now >= $start && $now <= $end);
    }
    
    /**
     * Get questions for campaign
     */
    private function get_campaign_questions($campaign_id) {
        global $wpdb;
        
        $questions_table = $wpdb->prefix . 'vefify_questions';
        $options_table = $wpdb->prefix . 'vefify_question_options';
        
        // Get questions
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$questions_table} 
             WHERE campaign_id = %d AND is_active = 1 
             ORDER BY RAND() 
             LIMIT 5",
            $campaign_id
        ), ARRAY_A);
        
        // Get options for each question
        foreach ($questions as &$question) {
            $options = $wpdb->get_results($wpdb->prepare(
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
     * Get quiz settings
     */
    private function get_quiz_settings($campaign) {
        return array(
            'timeLimit' => intval($campaign['time_limit']),
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
        // Only on pages with shortcodes
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vefify_quiz')) {
            
            wp_enqueue_script('jquery');
            
            // Enqueue quiz JavaScript
            wp_enqueue_script(
                'vefify-quiz-frontend',
                VEFIFY_QUIZ_PLUGIN_URL . 'frontend/assets/js/quiz.js',
                array('jquery'),
                VEFIFY_QUIZ_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script('vefify-quiz-frontend', 'vefifyAjax', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vefify_quiz_nonce'),
                'strings' => array(
                    'loading' => 'Loading...',
                    'error' => 'An error occurred',
                    'timeUp' => 'Time is up!',
                    'submitting' => 'Submitting...'
                )
            ));
        }
    }
    
    /**
     * Campaign info shortcode: [vefify_campaign campaign_id="1"]
     */
    public function render_campaign_info($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'show_stats' => false
        ), $atts);
        
        $campaign_id = intval($atts['campaign_id']);
        
        if (!$campaign_id) {
            return '<div class="vefify-error">Campaign ID required</div>';
        }
        
        $campaign = $this->get_campaign_data($campaign_id);
        
        if (!$campaign) {
            return '<div class="vefify-error">Campaign not found</div>';
        }
        
        ob_start();
        ?>
        <div class="vefify-campaign-info">
            <h3><?php echo esc_html($campaign['name']); ?></h3>
            <p><?php echo esc_html($campaign['description']); ?></p>
            
            <div class="campaign-details">
                <p><strong>Duration:</strong> <?php echo date('M j, Y', strtotime($campaign['start_date'])); ?> - <?php echo date('M j, Y', strtotime($campaign['end_date'])); ?></p>
                <p><strong>Questions:</strong> <?php echo $campaign['questions_per_quiz']; ?></p>
                <p><strong>Time Limit:</strong> <?php echo $campaign['time_limit']; ?> seconds</p>
                <p><strong>Pass Score:</strong> <?php echo $campaign['pass_score']; ?> correct answers</p>
            </div>
            
            <?php if ($this->is_campaign_active($campaign)): ?>
                <a href="<?php echo add_query_arg('campaign_id', $campaign_id); ?>" class="btn btn-primary">Take Quiz →</a>
            <?php else: ?>
                <p class="campaign-inactive">This campaign is not currently active.</p>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
}

// Initialize the shortcode handler
add_action('plugins_loaded', function() {
    Vefify_Quiz_Shortcode::get_instance();
});