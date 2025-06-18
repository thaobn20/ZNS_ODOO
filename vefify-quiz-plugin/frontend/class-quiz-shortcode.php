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
        <!-- Registration Form Screen -->
        <div class="quiz-screen registration-screen active">
            <div class="quiz-header">
                <h2 class="quiz-title"><?php echo esc_html($campaign['name']); ?></h2>
                <?php if (!empty($campaign['description'])): ?>
                    <p class="quiz-description"><?php echo esc_html($campaign['description']); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="registration-form-container">
                <form id="vefify-registration-form" class="vefify-registration-form">
                    <!-- Hidden campaign ID -->
                    <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                    
                    <!-- Full Name Field -->
                    <div class="form-group">
                        <label for="full_name" class="form-label">
                            <span class="required">*</span> Full Name
                        </label>
                        <input 
                            type="text" 
                            id="full_name" 
                            name="full_name"
                            class="form-input"
                            placeholder="Enter your full name"
                            required
                            autocomplete="name"
                        >
                        <div class="form-feedback" id="full_name_feedback"></div>
                    </div>
                    
                    <!-- Phone Number Field with Real-time Validation -->
                    <div class="form-group">
                        <label for="phone_number" class="form-label">
                            <span class="required">*</span> Phone Number
                        </label>
                        <input 
                            type="tel" 
                            id="phone_number" 
                            name="phone_number"
                            class="form-input"
                            placeholder="0912345678 or +84912345678"
                            required
                            autocomplete="tel"
                            data-validate="phone"
                        >
                        <div class="form-feedback" id="phone_number_feedback"></div>
                        <small class="form-help">Vietnamese mobile number (10 digits starting with 0)</small>
                    </div>
                    
                    <!-- Province Selection -->
                    <div class="form-group">
                        <label for="province" class="form-label">
                            <span class="required">*</span> Province/City
                        </label>
                        <select id="province" name="province" class="form-select" required>
                            <option value="">Select Province/City</option>
                            <?php foreach (Vefify_Quiz_Utilities::get_vietnam_provinces() as $code => $name): ?>
                                <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-feedback" id="province_feedback"></div>
                    </div>
                    
                    <!-- Pharmacist Code Field (Updated from Company/Organization) -->
                    <div class="form-group">
                        <label for="pharmacist_code" class="form-label">
                            Pharmacist License Code
                        </label>
                        <input 
                            type="text" 
                            id="pharmacist_code" 
                            name="pharmacist_code"
                            class="form-input"
                            placeholder="e.g., PH123456 (6-12 characters)"
                            pattern="[A-Z0-9]{6,12}"
                            data-validate="pharmacist"
                            autocomplete="off"
                            style="text-transform: uppercase;"
                        >
                        <div class="form-feedback" id="pharmacist_code_feedback"></div>
                        <small class="form-help">Optional: 6-12 alphanumeric characters</small>
                    </div>
                    
                    <!-- Email Field (Optional) -->
                    <div class="form-group">
                        <label for="email" class="form-label">
                            Email Address
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email"
                            class="form-input"
                            placeholder="your.email@example.com"
                            autocomplete="email"
                        >
                        <div class="form-feedback" id="email_feedback"></div>
                        <small class="form-help">Optional: For result notifications</small>
                    </div>
                    
                    <!-- Terms and Conditions -->
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="terms_agreed" name="terms_agreed" required>
                            <span class="checkmark"></span>
                            <span class="required">*</span> I agree to the terms and conditions
                        </label>
                        <div class="form-feedback" id="terms_feedback"></div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-start-quiz" disabled>
                            <span class="btn-text">🚀 Start Quiz</span>
                            <span class="btn-loader" style="display: none;">⌛ Validating...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Quiz Content Area (existing screens) -->
        <div class="quiz-content">
            <!-- Progress Bar -->
            <div class="quiz-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-text">Question <span class="current-question">0</span> of <span class="total-questions"><?php echo count($questions); ?></span></div>
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
                        <div class="gift-info" style="display: none;">
                            <div class="gift-card">
                                <h4>🎁 Congratulations!</h4>
                                <p class="gift-name"></p>
                                <div class="gift-code">
                                    <span class="code-label">Gift Code:</span>
                                    <span class="code-value"></span>
                                    <button class="copy-code-btn" onclick="copyGiftCode()">📋 Copy</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="result-actions">
                        <button class="btn btn-primary restart-btn">🔄 Try Again</button>
                        <button class="btn btn-secondary share-btn">📤 Share Results</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Timer Display -->
        <div class="quiz-timer" style="display: none;">
            <span class="timer-icon">⏰</span>
            <span class="timer-text">Time: <span class="time-remaining"><?php echo $campaign['time_limit']; ?></span>s</span>
        </div>
    </div>
    <?php
    
    return ob_get_clean();
}
	 
	 
    public function enqueue_frontend_assets() {
    // Only on pages with shortcodes
    global $post;
    
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vefify_quiz')) {
        
        wp_enqueue_script('jquery');
        
        // Enqueue form validation JavaScript (highest priority)
        wp_enqueue_script(
            'vefify-form-validation',
            VEFIFY_QUIZ_PLUGIN_URL . 'frontend/assets/js/form-validation.js',
            array('jquery'),
            VEFIFY_QUIZ_VERSION,
            true // Load in footer for better performance
        );
        
        // Enqueue quiz JavaScript
        wp_enqueue_script(
            'vefify-quiz-frontend',
            VEFIFY_QUIZ_PLUGIN_URL . 'frontend/assets/js/quiz.js',
            array('jquery', 'vefify-form-validation'),
            VEFIFY_QUIZ_VERSION,
            true
        );
        
        // Enqueue mobile-optimized CSS
        wp_enqueue_style(
            'vefify-form-styles',
            VEFIFY_QUIZ_PLUGIN_URL . 'frontend/assets/css/form-styles.css',
            array(),
            VEFIFY_QUIZ_VERSION,
            'all'
        );
        
        // Enqueue existing quiz CSS (if exists)
        if (file_exists(VEFIFY_QUIZ_PLUGIN_PATH . 'frontend/assets/css/quiz.css')) {
            wp_enqueue_style(
                'vefify-quiz-styles',
                VEFIFY_QUIZ_PLUGIN_URL . 'frontend/assets/css/quiz.css',
                array('vefify-form-styles'),
                VEFIFY_QUIZ_VERSION,
                'all'
            );
        }
        
        // Localize script with enhanced data
        wp_localize_script('vefify-form-validation', 'vefifyAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_quiz_nonce'),
            'strings' => array(
                'loading' => 'Loading...',
                'validating' => 'Validating...',
                'error' => 'An error occurred',
                'timeUp' => 'Time is up!',
                'submitting' => 'Starting quiz...',
                'phoneRequired' => 'Phone number is required',
                'phoneInvalid' => 'Please enter a valid Vietnamese phone number',
                'phoneTaken' => 'This phone number is already registered',
                'phoneAvailable' => 'Phone number is available',
                'pharmacistInvalid' => 'Invalid pharmacist code format',
                'termsRequired' => 'Please accept terms and conditions'
            ),
            'config' => array(
                'validateOnBlur' => true,
                'validateOnInput' => true,
                'debounceTime' => 300,
                'cacheValidation' => true
            )
        ));
        
        // Add mobile viewport meta if not present
        add_action('wp_head', array($this, 'add_mobile_viewport'), 1);
        
        // Preload critical resources for mobile performance
        add_action('wp_head', array($this, 'add_resource_hints'));
    }
}

 /**
 * Add mobile viewport meta tag
 */
public function add_mobile_viewport() {
    if (!has_action('wp_head', 'wp_site_icon') || !get_site_icon_url()) {
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">' . "\n";
    }
}

/**
 * Add resource hints for better mobile performance
 */
public function add_resource_hints() {
    // Preconnect to improve AJAX performance
    echo '<link rel="preconnect" href="' . admin_url() . '">' . "\n";
    
    // Prefetch province data for faster form loading
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            // Prefetch provinces for faster selection
            const provinces = ' . json_encode(Vefify_Quiz_Utilities::get_vietnam_provinces()) . ';
            sessionStorage.setItem("vefify_provinces", JSON.stringify(provinces));
        });
    </script>' . "\n";
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