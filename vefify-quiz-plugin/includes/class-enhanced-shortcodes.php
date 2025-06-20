<?php
/**
 * 🚀 COMPLETE ENHANCED VEFIFY QUIZ SHORTCODE - FIXED VERSION
 * File: includes/class-enhanced-shortcodes.php
 * 
 * COMPREHENSIVE SOLUTION that:
 * ✅ Handles both AJAX and non-AJAX form submissions
 * ✅ Uses correct database column names from your schema
 * ✅ No more 404 errors
 * ✅ Seamless quiz experience
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Enhanced_Shortcodes {
    
    private $database;
    private $wpdb;
    private static $assets_loaded = false;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Vefify_Quiz_Database();
        $this->init();
    }
    
    public function init() {
        // Register all shortcodes
        add_shortcode('vefify_simple_test', array($this, 'simple_test'));
        add_shortcode('vefify_test', array($this, 'debug_test'));
        add_shortcode('vefify_quiz', array($this, 'render_quiz'));
        
        // AJAX handlers
        add_action('wp_ajax_vefify_register_participant', array($this, 'ajax_register_participant'));
        add_action('wp_ajax_nopriv_vefify_register_participant', array($this, 'ajax_register_participant'));
        
        // Load assets when needed
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
    }
    
    /**
     * 🧪 SIMPLE TEST (Keep working)
     */
    public function simple_test($atts) {
        return '<div style="padding:20px;background:#f0f8ff;border:2px solid #007cba;border-radius:8px;margin:20px 0;">✅ Simple Test Working! Time: ' . current_time('mysql') . '</div>';
    }
    
    /**
     * 🔍 DEBUG TEST
     */
    public function debug_test($atts) {
        global $wpdb;
        $campaigns_table = $wpdb->prefix . 'vefify_campaigns';
        $participants_table = $wpdb->prefix . 'vefify_participants';
        
        $campaign_count = $wpdb->get_var("SELECT COUNT(*) FROM {$campaigns_table}");
        $participant_count = $wpdb->get_var("SELECT COUNT(*) FROM {$participants_table}");
        
        return '<div style="padding:20px;background:#fff3cd;border:2px solid #ffc107;border-radius:8px;margin:20px 0;">
            <h3>🔍 Debug Info</h3>
            <p><strong>Campaigns:</strong> ' . ($campaign_count ?: '0') . '</p>
            <p><strong>Participants:</strong> ' . ($participant_count ?: '0') . '</p>
            <p><strong>AJAX URL:</strong> ' . admin_url('admin-ajax.php') . '</p>
            <p><strong>Database:</strong> ' . ($this->database ? 'Connected' : 'Not connected') . '</p>
        </div>';
    }
    
    /**
     * 🎯 MAIN QUIZ SHORTCODE
     */
    public function render_quiz($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'fields' => 'name,email,phone',
            'style' => 'modern',
            'title' => '',
            'description' => '',
            'theme' => 'light'
        ), $atts);
        
        if (empty($atts['campaign_id'])) {
            return $this->render_error('Campaign ID is required. Usage: [vefify_quiz campaign_id="1"]');
        }
        
        $campaign_id = intval($atts['campaign_id']);
        
        // Check if this is a form submission (non-AJAX fallback)
        if (isset($_GET['action']) && $_GET['action'] === 'vefify_register_participant') {
            return $this->handle_form_submission($campaign_id, $atts);
        }
        
        // Get campaign data
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return $this->render_error('Campaign not found (ID: ' . $campaign_id . ')');
        }
        
        // Check if campaign is active
        if (!$this->is_campaign_active($campaign)) {
            return $this->render_notice('Campaign is not currently active');
        }
        
        // Parse and validate fields
        $requested_fields = array_map('trim', explode(',', $atts['fields']));
        $available_fields = $this->get_field_definitions();
        $valid_fields = array_intersect($requested_fields, array_keys($available_fields));
        
        if (empty($valid_fields)) {
            return $this->render_error('No valid fields specified. Available: ' . implode(', ', array_keys($available_fields)));
        }
        
        // Ensure assets are loaded
        $this->ensure_assets_loaded();
        
        // Generate unique container ID
        $container_id = 'vefify_quiz_' . $campaign_id . '_' . uniqid();
        
        return $this->render_quiz_form($campaign, $container_id, $valid_fields, $available_fields, $atts);
    }
    
    /**
     * 🏗️ RENDER QUIZ FORM
     */
    private function render_quiz_form($campaign, $container_id, $valid_fields, $available_fields, $atts) {
        ob_start();
        ?>
        
        <div id="<?php echo esc_attr($container_id); ?>" class="vefify-quiz-container vefify-theme-<?php echo esc_attr($atts['theme']); ?>" data-campaign-id="<?php echo $campaign->id; ?>">
            
            <!-- Campaign Header -->
            <div class="vefify-quiz-header">
                <h2 class="vefify-quiz-title"><?php echo esc_html($atts['title'] ?: $campaign->name); ?></h2>
                <?php if ($atts['description'] || $campaign->description): ?>
                    <p class="vefify-quiz-description"><?php echo esc_html($atts['description'] ?: $campaign->description); ?></p>
                <?php endif; ?>
                
                <div class="vefify-quiz-meta">
                    <span class="vefify-meta-item">📝 <?php echo $campaign->questions_per_quiz; ?> questions</span>
                    <?php if ($campaign->time_limit): ?>
                        <span class="vefify-meta-item">⏱️ <?php echo round($campaign->time_limit / 60); ?> minutes</span>
                    <?php endif; ?>
                    <span class="vefify-meta-item">🎯 Pass score: <?php echo $campaign->pass_score; ?></span>
                </div>
            </div>
            
            <!-- Messages Container -->
            <div id="vefify-messages" class="vefify-messages-container" style="display: none;"></div>
            
            <!-- Registration Section -->
            <div id="vefify-registration-section" class="vefify-registration-section">
                <h3>📝 Please fill in your information to start the quiz</h3>
                
                <!-- AJAX Form -->
                <form id="vefify-registration-form" class="vefify-form" data-campaign-id="<?php echo $campaign->id; ?>">
                    <?php wp_nonce_field('vefify_quiz_nonce', 'vefify_nonce'); ?>
                    <input type="hidden" name="action" value="vefify_register_participant">
                    <input type="hidden" name="campaign_id" value="<?php echo $campaign->id; ?>">
                    
                    <div class="vefify-form-grid">
                        <?php foreach ($valid_fields as $field_key): ?>
                            <?php if (isset($available_fields[$field_key])): ?>
                                <?php echo $this->render_form_field($field_key, $available_fields[$field_key]); ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="vefify-form-actions">
                        <button type="submit" class="vefify-btn vefify-btn-primary vefify-btn-large" id="vefify-start-btn">
                            🚀 Start Quiz
                        </button>
                    </div>
                </form>
                
                <!-- Fallback Form (for non-AJAX) -->
                <form class="vefify-fallback-form" method="GET" style="display: none;">
                    <?php wp_nonce_field('vefify_quiz_nonce', 'vefify_nonce'); ?>
                    <input type="hidden" name="action" value="vefify_register_participant">
                    <input type="hidden" name="campaign_id" value="<?php echo $campaign->id; ?>">
                    
                    <?php foreach ($valid_fields as $field_key): ?>
                        <?php if (isset($available_fields[$field_key])): ?>
                            <input type="hidden" name="<?php echo esc_attr($field_key); ?>" class="fallback-<?php echo esc_attr($field_key); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </form>
            </div>
            
            <!-- Quiz Section (Hidden initially) -->
            <div id="vefify-quiz-section" class="vefify-quiz-section" style="display: none;">
                
                <!-- Progress Bar -->
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
                    <div class="vefify-timer" id="vefify-timer" style="display: none;">
                        <span class="vefify-timer-icon">⏱️</span>
                        Time remaining: <span id="vefify-time-remaining"><?php echo round($campaign->time_limit / 60); ?>:00</span>
                    </div>
                <?php endif; ?>
                
                <!-- Question Container -->
                <div id="vefify-question-container" class="vefify-question-container">
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
                    <button type="button" id="vefify-finish-quiz" class="vefify-btn vefify-btn-success" style="display: none;" disabled>
                        ✓ Finish Quiz
                    </button>
                </div>
            </div>
            
            <!-- Results Section (Hidden initially) -->
            <div id="vefify-results-section" class="vefify-results-section" style="display: none;">
                <!-- Results will be displayed here -->
            </div>
            
            <!-- Loading Overlay -->
            <div class="vefify-loading-overlay" style="display: none;">
                <div class="vefify-spinner"></div>
                <p>Loading...</p>
            </div>
        </div>
        
        <!-- Initialize Quiz JavaScript -->
        <script>
        jQuery(document).ready(function($) {
            // Enhanced form handling with fallback
            $('#vefify-registration-form').on('submit', function(e) {
                e.preventDefault();
                
                // Try AJAX first
                if (typeof VefifyQuiz !== 'undefined' && VefifyQuiz.init) {
                    // Initialize with AJAX capability
                    VefifyQuiz.init('<?php echo $container_id; ?>', {
                        campaignId: <?php echo $campaign->id; ?>,
                        timeLimit: <?php echo intval($campaign->time_limit); ?>,
                        questionsPerQuiz: <?php echo intval($campaign->questions_per_quiz); ?>,
                        passScore: <?php echo intval($campaign->pass_score); ?>
                    });
                } else {
                    // Fallback to regular form submission
                    console.warn('VefifyQuiz not loaded, using fallback');
                    
                    // Copy data to fallback form
                    var $form = $(this);
                    var $fallback = $('.vefify-fallback-form');
                    
                    $form.find('input, select').each(function() {
                        var name = $(this).attr('name');
                        var value = $(this).val();
                        if (name && value) {
                            $fallback.find('.fallback-' + name).val(value);
                        }
                    });
                    
                    // Submit fallback form
                    $fallback.submit();
                }
            });
        });
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * 🔗 HANDLE FORM SUBMISSION (Non-AJAX fallback)
     */
    private function handle_form_submission($campaign_id, $atts) {
        // Verify nonce
        if (!wp_verify_nonce($_GET['vefify_nonce'], 'vefify_quiz_nonce')) {
            return $this->render_error('Security check failed');
        }
        
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign || !$this->is_campaign_active($campaign)) {
            return $this->render_error('Campaign not available');
        }
        
        // Process registration
        $participant_data = array(
            'campaign_id' => $campaign_id,
            'participant_name' => sanitize_text_field($_GET['name'] ?? ''),
            'participant_email' => sanitize_email($_GET['email'] ?? ''),
            'participant_phone' => sanitize_text_field($_GET['phone'] ?? ''),
            'province' => sanitize_text_field($_GET['province'] ?? ''),
            'pharmacy_code' => sanitize_text_field($_GET['pharmacy_code'] ?? ''),
            'company' => sanitize_text_field($_GET['company'] ?? ''),
            'occupation' => sanitize_text_field($_GET['occupation'] ?? ''),
            'age' => intval($_GET['age'] ?? 0),
            'session_id' => wp_generate_password(32, false),
            'quiz_status' => 'started',
            'start_time' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'created_at' => current_time('mysql')
        );
        
        // Validate
        if (empty($participant_data['participant_name'])) {
            return $this->render_quiz_with_error($campaign_id, 'Name is required', $_GET);
        }
        
        if (empty($participant_data['participant_phone'])) {
            return $this->render_quiz_with_error($campaign_id, 'Phone number is required', $_GET);
        }
        
        // Check duplicates
        $participants_table = $this->wpdb->prefix . 'vefify_participants';
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$participants_table} WHERE campaign_id = %d AND participant_phone = %s",
            $campaign_id,
            $participant_data['participant_phone']
        ));
        
        if ($existing) {
            return $this->render_quiz_with_error($campaign_id, 'Phone number already registered', $_GET);
        }
        
        // Insert participant
        $result = $this->wpdb->insert($participants_table, $participant_data);
        
        if ($result === false) {
            return $this->render_quiz_with_error($campaign_id, 'Registration failed', $_GET);
        }
        
        $participant_id = $this->wpdb->insert_id;
        
        // Get questions
        $questions = $this->get_quiz_questions($campaign_id, $campaign->questions_per_quiz);
        
        if (empty($questions)) {
            return $this->render_quiz_with_error($campaign_id, 'No questions available', $_GET);
        }
        
        // Return quiz interface
        return $this->render_quiz_interface($campaign, $participant_id, $participant_data['session_id'], $questions);
    }
    
    /**
     * 🔗 AJAX: REGISTER PARTICIPANT
     */
    public function ajax_register_participant() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['vefify_nonce'], 'vefify_quiz_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        
        // Validate campaign
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign || !$this->is_campaign_active($campaign)) {
            wp_send_json_error('Campaign not available');
        }
        
        // Map form fields to database columns
        $participant_data = array(
            'campaign_id' => $campaign_id,
            'participant_name' => sanitize_text_field($_POST['name'] ?? ''),
            'participant_email' => sanitize_email($_POST['email'] ?? ''),
            'participant_phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'province' => sanitize_text_field($_POST['province'] ?? ''),
            'pharmacy_code' => sanitize_text_field($_POST['pharmacy_code'] ?? ''),
            'company' => sanitize_text_field($_POST['company'] ?? ''),
            'occupation' => sanitize_text_field($_POST['occupation'] ?? ''),
            'age' => intval($_POST['age'] ?? 0),
            'session_id' => wp_generate_password(32, false),
            'quiz_status' => 'started',
            'start_time' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'created_at' => current_time('mysql')
        );
        
        // Validate required fields
        if (empty($participant_data['participant_name'])) {
            wp_send_json_error('Name is required');
        }
        
        if (empty($participant_data['participant_phone'])) {
            wp_send_json_error('Phone number is required');
        }
        
        // Check for duplicate registration
        $participants_table = $this->wpdb->prefix . 'vefify_participants';
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$participants_table} WHERE campaign_id = %d AND participant_phone = %s",
            $campaign_id,
            $participant_data['participant_phone']
        ));
        
        if ($existing) {
            wp_send_json_error('Phone number already registered for this campaign');
        }
        
        // Insert participant
        $result = $this->wpdb->insert($participants_table, $participant_data);
        
        if ($result === false) {
            wp_send_json_error('Registration failed. Please try again.');
        }
        
        $participant_id = $this->wpdb->insert_id;
        
        // Get questions for this campaign
        $questions = $this->get_quiz_questions($campaign_id, $campaign->questions_per_quiz);
        
        if (empty($questions)) {
            wp_send_json_error('No questions available');
        }
        
        wp_send_json_success(array(
            'participant_id' => $participant_id,
            'session_id' => $participant_data['session_id'],
            'questions' => $questions,
            'total_questions' => count($questions),
            'time_limit' => intval($campaign->time_limit),
            'pass_score' => intval($campaign->pass_score),
            'message' => 'Registration successful! Starting quiz...'
        ));
    }
    
    /**
     * 🎨 RENDER FORM FIELD
     */
    private function render_form_field($field_key, $field_config) {
        $required_attr = $field_config['required'] ? 'required' : '';
        $required_star = $field_config['required'] ? ' <span class="required">*</span>' : '';
        
        ob_start();
        ?>
        <div class="vefify-form-field vefify-field-<?php echo esc_attr($field_key); ?>">
            <label for="vefify_<?php echo esc_attr($field_key); ?>" class="vefify-field-label">
                <?php echo esc_html($field_config['label']); ?><?php echo $required_star; ?>
            </label>
            
            <?php if ($field_config['type'] === 'select'): ?>
                <select name="<?php echo esc_attr($field_key); ?>" id="vefify_<?php echo esc_attr($field_key); ?>" class="vefify-field-input" <?php echo $required_attr; ?>>
                    <option value="">Select <?php echo esc_html($field_config['label']); ?></option>
                    <?php foreach ($field_config['options'] as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="<?php echo esc_attr($field_config['type']); ?>" 
                       name="<?php echo esc_attr($field_key); ?>" 
                       id="vefify_<?php echo esc_attr($field_key); ?>" 
                       class="vefify-field-input" 
                       placeholder="<?php echo esc_attr($field_config['placeholder'] ?? ''); ?>"
                       <?php if (isset($field_config['pattern'])): ?>pattern="<?php echo esc_attr($field_config['pattern']); ?>"<?php endif; ?>
                       <?php echo $required_attr; ?>>
            <?php endif; ?>
            
            <?php if (isset($field_config['help'])): ?>
                <div class="vefify-field-help"><?php echo esc_html($field_config['help']); ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 📝 FIELD DEFINITIONS
     */
    private function get_field_definitions() {
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
                'required' => true
            ),
            'phone' => array(
                'label' => 'Phone Number',
                'type' => 'tel',
                'placeholder' => '0938474356',
                'pattern' => '^(0[0-9]{9,10}|84[0-9]{9,10})$',
                'required' => true,
                'help' => 'Format: 0938474356 or 84938474356'
            ),
            'company' => array(
                'label' => 'Company/Organization',
                'type' => 'text',
                'placeholder' => 'Enter company name',
                'required' => false
            ),
            'province' => array(
                'label' => 'Province/City',
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
                'placeholder' => 'NT-123456',
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
     * 📝 GET QUIZ QUESTIONS
     */
    private function get_quiz_questions($campaign_id, $limit) {
        $questions_table = $this->wpdb->prefix . 'vefify_questions';
        $options_table = $this->wpdb->prefix . 'vefify_question_options';
        
        // Get random questions
        $questions = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$questions_table} 
             WHERE campaign_id = %d AND is_active = 1 
             ORDER BY RAND() 
             LIMIT %d",
            $campaign_id, $limit
        ), ARRAY_A);
        
        // For each question, get options
        foreach ($questions as &$question) {
            $options = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT option_text, option_value, is_correct FROM {$options_table} 
                 WHERE question_id = %d 
                 ORDER BY option_order",
                $question['id']
            ), ARRAY_A);
            
            $question['options'] = $options ?: array();
        }
        
        return $questions;
    }
    
    /**
     * 📊 GET CAMPAIGN
     */
    private function get_campaign($campaign_id) {
        $campaigns_table = $this->wpdb->prefix . 'vefify_campaigns';
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$campaigns_table} WHERE id = %d",
            $campaign_id
        ));
    }
    
    /**
     * ✅ CHECK CAMPAIGN ACTIVE
     */
    private function is_campaign_active($campaign) {
        if (!$campaign) return false;
        
        $now = current_time('mysql');
        return ($campaign->is_active == 1 && 
                $campaign->start_date <= $now && 
                $campaign->end_date >= $now);
    }
    
    /**
     * 🚨 RENDER QUIZ WITH ERROR (Non-AJAX fallback)
     */
    private function render_quiz_with_error($campaign_id, $error_message, $form_data = array()) {
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return $this->render_error('Campaign not found');
        }
        
        ob_start();
        ?>
        <div class="vefify-quiz-container">
            <div class="vefify-quiz-header">
                <h2><?php echo esc_html($campaign->name); ?></h2>
            </div>
            
            <div class="vefify-message vefify-message-error">
                ❌ <?php echo esc_html($error_message); ?>
            </div>
            
            <div class="vefify-registration-section">
                <h3>📝 Please correct the information and try again</h3>
                
                <form class="vefify-form" method="GET">
                    <?php wp_nonce_field('vefify_quiz_nonce', 'vefify_nonce'); ?>
                    <input type="hidden" name="action" value="vefify_register_participant">
                    <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
                    
                    <div class="vefify-form-grid">
                        <div class="vefify-form-field">
                            <label class="vefify-field-label">Full Name <span class="required">*</span></label>
                            <input type="text" name="name" class="vefify-field-input" 
                                   value="<?php echo esc_attr($form_data['name'] ?? ''); ?>" 
                                   placeholder="Enter your full name" required>
                        </div>
                        
                        <div class="vefify-form-field">
                            <label class="vefify-field-label">Email Address <span class="required">*</span></label>
                            <input type="email" name="email" class="vefify-field-input" 
                                   value="<?php echo esc_attr($form_data['email'] ?? ''); ?>" 
                                   placeholder="Enter your email" required>
                        </div>
                        
                        <div class="vefify-form-field">
                            <label class="vefify-field-label">Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" class="vefify-field-input" 
                                   value="<?php echo esc_attr($form_data['phone'] ?? ''); ?>" 
                                   placeholder="0938474356" required>
                        </div>
                        
                        <div class="vefify-form-field">
                            <label class="vefify-field-label">Company/Organization</label>
                            <input type="text" name="company" class="vefify-field-input" 
                                   value="<?php echo esc_attr($form_data['company'] ?? ''); ?>" 
                                   placeholder="Enter company name">
                        </div>
                    </div>
                    
                    <div class="vefify-form-actions">
                        <button type="submit" class="vefify-btn vefify-btn-primary vefify-btn-large">
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
     * 🎮 RENDER QUIZ INTERFACE (Non-AJAX)
     */
    private function render_quiz_interface($campaign, $participant_id, $session_id, $questions) {
        ob_start();
        ?>
        <div class="vefify-quiz-container vefify-quiz-active" data-campaign-id="<?php echo $campaign->id; ?>">
            
            <div class="vefify-message vefify-message-success">
                ✅ Registration successful! Welcome to the quiz.
            </div>
            
            <div class="vefify-quiz-header">
                <h2><?php echo esc_html($campaign->name); ?></h2>
                <div class="vefify-quiz-meta">
                    <span class="vefify-meta-item">📝 <?php echo count($questions); ?> questions</span>
                    <?php if ($campaign->time_limit): ?>
                        <span class="vefify-meta-item">⏱️ <?php echo round($campaign->time_limit / 60); ?> minutes</span>
                    <?php endif; ?>
                    <span class="vefify-meta-item">🎯 Pass score: <?php echo $campaign->pass_score; ?></span>
                </div>
            </div>
            
            <div class="vefify-quiz-section">
                <div class="vefify-quiz-progress">
                    <div class="vefify-progress-bar">
                        <div class="vefify-progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="vefify-progress-text">
                        Question <span class="current">1</span> of <span class="total"><?php echo count($questions); ?></span>
                    </div>
                </div>
                
                <?php if ($campaign->time_limit): ?>
                    <div class="vefify-timer" id="vefify-timer">
                        <span class="vefify-timer-icon">⏱️</span>
                        Time remaining: <span id="vefify-time-remaining"><?php echo round($campaign->time_limit / 60); ?>:00</span>
                    </div>
                <?php endif; ?>
                
                <div id="vefify-question-container" class="vefify-question-container">
                    <?php echo $this->render_question_html($questions[0], 0); ?>
                </div>
                
                <div class="vefify-quiz-navigation">
                    <button type="button" id="vefify-prev-question" class="vefify-btn vefify-btn-secondary" style="display: none;">
                        ← Previous
                    </button>
                    <button type="button" id="vefify-next-question" class="vefify-btn vefify-btn-primary">
                        Next →
                    </button>
                    <button type="button" id="vefify-finish-quiz" class="vefify-btn vefify-btn-success" style="display: none;" disabled>
                        ✓ Finish Quiz
                    </button>
                </div>
            </div>
            
            <div id="vefify-results-section" class="vefify-results-section" style="display: none;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            if (typeof VefifyQuiz !== 'undefined') {
                VefifyQuiz.initializeWithData({
                    participantId: <?php echo $participant_id; ?>,
                    sessionToken: '<?php echo esc_js($session_id); ?>',
                    sessionId: '<?php echo esc_js($session_id); ?>',
                    campaignId: <?php echo $campaign->id; ?>,
                    questions: <?php echo json_encode($questions); ?>,
                    timeLimit: <?php echo intval($campaign->time_limit); ?>,
                    passScore: <?php echo intval($campaign->pass_score); ?>
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 🏗️ RENDER QUESTION HTML
     */
    private function render_question_html($question, $index) {
        $html = '<div class="vefify-question" data-question-id="' . $question['id'] . '">';
        $html .= '<div class="vefify-question-header">';
        $html .= '<h3>Question ' . ($index + 1) . '</h3>';
        $html .= '</div>';
        $html .= '<div class="vefify-question-text">' . esc_html($question['question_text']) . '</div>';
        $html .= '<div class="vefify-question-options">';
        
        if (!empty($question['options'])) {
            foreach ($question['options'] as $i => $option) {
                $option_id = 'option_' . $question['id'] . '_' . $i;
                $html .= '<div class="vefify-question-option">';
                $html .= '<input type="radio" id="' . $option_id . '" name="question_' . $question['id'] . '" value="' . esc_attr($option['option_value']) . '">';
                $html .= '<label for="' . $option_id . '">';
                $html .= '<span class="vefify-option-marker"></span>';
                $html .= '<span class="vefify-option-text">' . esc_html($option['option_text']) . '</span>';
                $html .= '</label>';
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 📚 MAYBE ENQUEUE ASSETS
     */
    public function maybe_enqueue_assets() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vefify_quiz')) {
            $this->ensure_assets_loaded();
        }
    }
    
    /**
     * 📦 ENSURE ASSETS LOADED
     */
    private function ensure_assets_loaded() {
        if (self::$assets_loaded) return;
        
        wp_enqueue_script('jquery');
        
        // Enqueue enhanced frontend script
        wp_enqueue_script(
            'vefify-quiz-enhanced',
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/enhanced-frontend-quiz.js',
            array('jquery'),
            VEFIFY_QUIZ_VERSION,
            true
        );
        
        // Enqueue CSS
        wp_enqueue_style(
            'vefify-quiz-frontend',
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/frontend-quiz.css',
            array(),
            VEFIFY_QUIZ_VERSION
        );
        
        // Localize script with AJAX data
        wp_localize_script('vefify-quiz-enhanced', 'vefifyAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_quiz_nonce'),
            'strings' => array(
                'loading' => 'Loading...',
                'error' => 'An error occurred',
                'success' => 'Success!',
                'timeUp' => 'Time is up!',
                'confirmFinish' => 'Are you sure you want to finish the quiz?'
            )
        ));
        
        self::$assets_loaded = true;
    }
    
    /**
     * 🚨 RENDER ERROR
     */
    private function render_error($message) {
        return '<div class="vefify-error">❌ ' . esc_html($message) . '</div>';
    }
    
    /**
     * 📢 RENDER NOTICE
     */
    private function render_notice($message) {
        return '<div class="vefify-notice">📅 ' . esc_html($message) . '</div>';
    }
}

// Initialize the enhanced shortcode system
if (!class_exists('Vefify_Enhanced_Shortcodes')) {
    new Vefify_Enhanced_Shortcodes();
}