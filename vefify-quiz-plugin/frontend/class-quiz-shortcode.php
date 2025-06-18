<?php
/**
 * Enhanced Quiz Shortcode Class
 * File: frontend/class-quiz-shortcode.php
 * 
 * Phase 1 Enhancements:
 * - Updated Pharmacist Code field
 * - Real-time phone validation
 * - Admin settings integration
 * - Mobile responsive improvements
 * - Loading states and animations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Shortcode {
    
    private $campaign_id;
    private $form_settings;
    
    public function __construct() {
        add_shortcode('vefify_quiz', array($this, 'render_quiz_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_vefify_check_phone_uniqueness', array($this, 'ajax_check_phone_uniqueness'));
        add_action('wp_ajax_nopriv_vefify_check_phone_uniqueness', array($this, 'ajax_check_phone_uniqueness'));
        add_action('wp_ajax_vefify_get_districts', array($this, 'ajax_get_districts'));
        add_action('wp_ajax_nopriv_vefify_get_districts', array($this, 'ajax_get_districts'));
        add_action('wp_ajax_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
        add_action('wp_ajax_nopriv_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
    }
    
    /**
     * Render quiz shortcode
     */
    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'theme' => 'default',
            'show_gifts' => 'true',
            'show_progress' => 'true'
        ), $atts, 'vefify_quiz');
        
        if (empty($atts['campaign_id'])) {
            return '<div class="vefify-error">Campaign ID is required.</div>';
        }
        
        $this->campaign_id = intval($atts['campaign_id']);
        
        // Check if campaign exists and is active
        $campaign = $this->get_campaign($this->campaign_id);
        if (!$campaign || !$campaign['is_active']) {
            return '<div class="vefify-error">Campaign not found or inactive.</div>';
        }
        
        // Load form settings
        $this->form_settings = get_option('vefify_form_settings', array());
        
        ob_start();
        $this->render_quiz_container($campaign, $atts);
        return ob_get_clean();
    }
    
    /**
     * Render main quiz container
     */
    private function render_quiz_container($campaign, $atts) {
        $theme = $atts['theme'] !== 'default' ? $atts['theme'] : ($this->form_settings['form_theme'] ?? 'default');
        $show_gifts = $atts['show_gifts'] === 'true' && $this->get_setting('show_gift_preview', true);
        ?>
        <div id="vefify-quiz-container" class="vefify-quiz-container theme-<?php echo esc_attr($theme); ?>" 
             data-campaign-id="<?php echo esc_attr($this->campaign_id); ?>"
             data-theme="<?php echo esc_attr($theme); ?>">
            
            <!-- Campaign Header -->
            <div class="vefify-campaign-header">
                <h2 class="vefify-campaign-title"><?php echo esc_html($campaign['name']); ?></h2>
                <?php if (!empty($campaign['description'])): ?>
                    <p class="vefify-campaign-description"><?php echo esc_html($campaign['description']); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($show_gifts): ?>
                <?php $this->render_gift_preview($campaign); ?>
            <?php endif; ?>
            
            <!-- Registration Form -->
            <div id="vefify-registration-step" class="vefify-step active">
                <?php $this->render_registration_form($campaign); ?>
            </div>
            
            <!-- Quiz Step -->
            <div id="vefify-quiz-step" class="vefify-step">
                <div id="vefify-quiz-content">
                    <!-- Quiz content will be loaded here -->
                </div>
            </div>
            
            <!-- Results Step -->
            <div id="vefify-results-step" class="vefify-step">
                <div id="vefify-results-content">
                    <!-- Results will be displayed here -->
                </div>
            </div>
            
            <!-- Loading Overlay -->
            <div id="vefify-loading-overlay" class="vefify-loading-overlay">
                <div class="vefify-spinner">
                    <div class="vefify-bounce1"></div>
                    <div class="vefify-bounce2"></div>
                    <div class="vefify-bounce3"></div>
                </div>
                <p class="vefify-loading-text">Processing...</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render enhanced registration form
     */
    private function render_registration_form($campaign) {
        ?>
        <div class="vefify-form-container">
            <div class="vefify-form-header">
                <h3>📝 Registration Information</h3>
                <p>Please fill in your details to start the quiz</p>
            </div>
            
            <form id="vefify-registration-form" class="vefify-registration-form" novalidate>
                <?php wp_nonce_field('vefify_quiz_registration', 'vefify_registration_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($this->campaign_id); ?>">
                
                <!-- Full Name Field -->
                <div class="vefify-form-group">
                    <label for="full_name" class="vefify-form-label required">
                        <span class="label-text">Full Name</span>
                        <span class="required-mark">*</span>
                    </label>
                    <div class="vefify-input-wrapper">
                        <input 
                            type="text" 
                            id="full_name" 
                            name="full_name"
                            class="vefify-form-input"
                            placeholder="Enter your full name"
                            required
                            autocomplete="name"
                            data-validate="name"
                        >
                        <div class="vefify-input-icon">
                            <span class="dashicons dashicons-admin-users"></span>
                        </div>
                    </div>
                    <div class="vefify-form-feedback" id="full_name_feedback"></div>
                </div>
                
                <!-- Phone Number Field with Enhanced Validation -->
                <div class="vefify-form-group">
                    <label for="phone_number" class="vefify-form-label required">
                        <span class="label-text">Phone Number</span>
                        <span class="required-mark">*</span>
                    </label>
                    <div class="vefify-input-wrapper">
                        <input 
                            type="tel" 
                            id="phone_number" 
                            name="phone_number"
                            class="vefify-form-input"
                            placeholder="0912345678 or +84912345678"
                            required
                            autocomplete="tel"
                            data-validate="phone"
                        >
                        <div class="vefify-input-icon">
                            <span class="dashicons dashicons-phone"></span>
                        </div>
                        <div class="vefify-validation-status" id="phone_validation_status"></div>
                    </div>
                    <div class="vefify-form-feedback" id="phone_number_feedback"></div>
                    <small class="vefify-form-help">Vietnamese mobile number (10 digits starting with 0)</small>
                </div>
                
                <!-- Province Selection -->
                <div class="vefify-form-group">
                    <label for="province" class="vefify-form-label required">
                        <span class="label-text">Province/City</span>
                        <span class="required-mark">*</span>
                    </label>
                    <div class="vefify-input-wrapper">
                        <select id="province" name="province" class="vefify-form-select" required>
                            <option value="">Select Province/City</option>
                            <?php foreach (Vefify_Quiz_Utilities::get_vietnam_provinces() as $code => $name): ?>
                                <option value="<?php echo esc_attr($code); ?>">
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="vefify-select-arrow">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                    </div>
                    <div class="vefify-form-feedback" id="province_feedback"></div>
                </div>
                
                <!-- District Selection (conditional) -->
                <?php if ($this->should_show_field('district')): ?>
                <div class="vefify-form-group" id="district_group" style="display: none;">
                    <label for="district" class="vefify-form-label <?php echo $this->is_field_required('district') ? 'required' : ''; ?>">
                        <span class="label-text">District</span>
                        <?php if ($this->is_field_required('district')): ?>
                            <span class="required-mark">*</span>
                        <?php endif; ?>
                    </label>
                    <div class="vefify-input-wrapper">
                        <select id="district" name="district" class="vefify-form-select" 
                                <?php echo $this->is_field_required('district') ? 'required' : ''; ?>>
                            <option value="">Select District</option>
                        </select>
                        <div class="vefify-select-arrow">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                    </div>
                    <div class="vefify-form-feedback" id="district_feedback"></div>
                </div>
                <?php endif; ?>
                
                <!-- Enhanced Pharmacist Code Field -->
                <?php if ($this->should_show_field('pharmacy_code')): ?>
                <div class="vefify-form-group">
                    <label for="pharmacist_code" class="vefify-form-label <?php echo $this->is_field_required('pharmacy_code') ? 'required' : ''; ?>">
                        <span class="label-text">Pharmacist License Code</span>
                        <?php if ($this->is_field_required('pharmacy_code')): ?>
                            <span class="required-mark">*</span>
                        <?php endif; ?>
                    </label>
                    <div class="vefify-input-wrapper">
                        <input 
                            type="text" 
                            id="pharmacist_code" 
                            name="pharmacist_code"
                            class="vefify-form-input"
                            placeholder="e.g., PH123456 (6-12 characters)"
                            pattern="[A-Z0-9]{6,12}"
                            data-validate="pharmacist"
                            autocomplete="off"
                            style="text-transform: uppercase;"
                            <?php echo $this->is_field_required('pharmacy_code') ? 'required' : ''; ?>
                        >
                        <div class="vefify-input-icon">
                            <span class="dashicons dashicons-id-alt"></span>
                        </div>
                    </div>
                    <div class="vefify-form-feedback" id="pharmacist_code_feedback"></div>
                    <small class="vefify-form-help">
                        <?php echo $this->is_field_required('pharmacy_code') ? 'Required' : 'Optional'; ?>: 
                        6-12 alphanumeric characters
                    </small>
                </div>
                <?php endif; ?>
                
                <!-- Email Field (conditional) -->
                <?php if ($this->should_show_field('email')): ?>
                <div class="vefify-form-group">
                    <label for="email" class="vefify-form-label <?php echo $this->is_field_required('email') ? 'required' : ''; ?>">
                        <span class="label-text">Email Address</span>
                        <?php if ($this->is_field_required('email')): ?>
                            <span class="required-mark">*</span>
                        <?php endif; ?>
                    </label>
                    <div class="vefify-input-wrapper">
                        <input 
                            type="email" 
                            id="email" 
                            name="email"
                            class="vefify-form-input"
                            placeholder="your.email@example.com"
                            autocomplete="email"
                            <?php echo $this->is_field_required('email') ? 'required' : ''; ?>
                        >
                        <div class="vefify-input-icon">
                            <span class="dashicons dashicons-email-alt"></span>
                        </div>
                    </div>
                    <div class="vefify-form-feedback" id="email_feedback"></div>
                    <small class="vefify-form-help">
                        <?php echo $this->is_field_required('email') ? 'Required' : 'Optional'; ?>: 
                        For result notifications
                    </small>
                </div>
                <?php endif; ?>
                
                <!-- Terms and Conditions (conditional) -->
                <?php if ($this->should_show_field('terms')): ?>
                <div class="vefify-form-group checkbox-group">
                    <label class="vefify-checkbox-label <?php echo $this->is_field_required('terms') ? 'required' : ''; ?>">
                        <input 
                            type="checkbox" 
                            id="terms_accepted" 
                            name="terms_accepted"
                            value="1"
                            class="vefify-checkbox"
                            <?php echo $this->is_field_required('terms') ? 'required' : ''; ?>
                        >
                        <span class="vefify-checkbox-custom"></span>
                        <span class="checkbox-text">
                            I agree to the 
                            <?php 
                            $terms_url = $this->get_setting('terms_url', '#');
                            if ($terms_url && $terms_url !== '#'): 
                            ?>
                                <a href="<?php echo esc_url($terms_url); ?>" target="_blank" class="terms-link">
                                    Terms & Conditions
                                </a>
                            <?php else: ?>
                                Terms & Conditions
                            <?php endif; ?>
                        </span>
                    </label>
                    <div class="vefify-form-feedback" id="terms_accepted_feedback"></div>
                </div>
                <?php endif; ?>
                
                <!-- Submit Button -->
                <div class="vefify-form-actions">
                    <button type="submit" id="vefify-registration-submit" class="vefify-btn vefify-btn-primary vefify-btn-large">
                        <span class="btn-icon">🚀</span>
                        <span class="btn-text">Start Quiz</span>
                        <span class="btn-loading">
                            <span class="vefify-spinner-small"></span>
                            Processing...
                        </span>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render gift preview section
     */
    private function render_gift_preview($campaign) {
        $gifts = $this->get_campaign_gifts($campaign['id']);
        if (empty($gifts)) return;
        
        ?>
        <div class="vefify-gift-preview">
            <div class="vefify-gift-header">
                <h3>🎁 Available Rewards</h3>
                <p>Complete the quiz to earn these amazing gifts!</p>
            </div>
            
            <div class="vefify-gift-grid">
                <?php foreach ($gifts as $gift): ?>
                    <div class="vefify-gift-card">
                        <div class="gift-icon">🎁</div>
                        <h4 class="gift-name"><?php echo esc_html($gift['gift_name']); ?></h4>
                        
                        <?php if ($this->get_setting('show_gift_value', true) && !empty($gift['gift_value'])): ?>
                            <div class="gift-value"><?php echo esc_html($gift['gift_value']); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($this->get_setting('show_gift_requirements', true)): ?>
                            <div class="gift-requirement">
                                Minimum Score: <?php echo esc_html($gift['min_score']); ?>/<?php echo esc_html($campaign['questions_per_quiz']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="gift-cta">Complete quiz to earn!</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Check phone number uniqueness
     */
    public function ajax_check_phone_uniqueness() {
        check_ajax_referer('vefify_quiz_registration', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        
        if (empty($phone) || !$campaign_id) {
            wp_send_json_error('Invalid data provided');
        }
        
        global $wpdb;
        
        // Format phone number using your existing utility
        $formatted_phone = Vefify_Quiz_Utilities::format_phone_number($phone);
        
        // Check uniqueness in this campaign
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}vefify_participants 
             WHERE participant_phone = %s AND campaign_id = %d",
            $formatted_phone, $campaign_id
        ));
        
        if ($exists > 0) {
            wp_send_json_error('This phone number is already registered for this campaign');
        }
        
        wp_send_json_success('Phone number is available');
    }
    
    /**
     * AJAX: Get districts for province
     */
    public function ajax_get_districts() {
        $province_code = sanitize_text_field($_POST['province_code'] ?? '');
        
        if (empty($province_code)) {
            wp_send_json_error('Province code required');
        }
        
        // Use your existing utility to get districts
        $districts = Vefify_Quiz_Utilities::get_vietnam_districts($province_code);
        
        wp_send_json_success($districts);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (is_admin()) return;
        
        wp_enqueue_style(
            'vefify-quiz-frontend',
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/frontend-quiz.css',
            array(),
            VEFIFY_QUIZ_VERSION
        );
        
        wp_enqueue_script(
            'vefify-quiz-frontend',
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/frontend-quiz.js',
            array('jquery'),
            VEFIFY_QUIZ_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('vefify-quiz-frontend', 'vefifyAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_quiz_nonce'),
            'strings' => array(
                'loading' => 'Loading...',
                'error' => 'An error occurred. Please try again.',
                'phoneValidating' => 'Checking phone number...',
                'phoneAvailable' => '✅ Phone number available',
                'phoneInUse' => '❌ Phone number already registered',
                'requiredField' => 'This field is required',
                'invalidPhone' => 'Please enter a valid Vietnamese phone number',
                'invalidEmail' => 'Please enter a valid email address'
            )
        ));
    }
    
    /**
     * Helper methods
     */
    private function get_campaign($campaign_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vefify_campaigns WHERE id = %d",
            $campaign_id
        ), ARRAY_A);
    }
    
    private function get_campaign_gifts($campaign_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vefify_gifts 
             WHERE campaign_id = %d AND is_active = 1 
             ORDER BY min_score ASC",
            $campaign_id
        ), ARRAY_A);
    }
    
    private function should_show_field($field_name) {
        return $this->get_setting('show_' . $field_name, true);
    }
    
    private function is_field_required($field_name) {
        return $this->get_setting('require_' . $field_name, false);
    }
    
    private function get_setting($key, $default = false) {
        return $this->form_settings[$key] ?? $default;
    }
}