<?php
/**
 * 🚀 COMPLETE SHORTCODE SYSTEM FOR VEFIFY QUIZ
 * File: includes/class-shortcodes.php
 * 
 * This replaces/enhances your existing shortcode implementation
 * Building on your working vefify_simple_test foundation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Shortcodes {
    
    private $database;
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Vefify_Quiz_Database();
        $this->init();
    }
    
    public function init() {
        // Register all shortcodes
        add_shortcode('vefify_simple_test', array($this, 'simple_test')); // Keep your working one
        add_shortcode('vefify_test', array($this, 'debug_test')); // Enhanced debug
        add_shortcode('vefify_quiz', array($this, 'render_quiz')); // Main quiz
        add_shortcode('vefify_quiz_list', array($this, 'render_quiz_list')); // Quiz list
        add_shortcode('vefify_campaign', array($this, 'render_campaign_info')); // Campaign info
        
        // AJAX handlers
        add_action('wp_ajax_vefify_register_participant', array($this, 'ajax_register_participant'));
        add_action('wp_ajax_nopriv_vefify_register_participant', array($this, 'ajax_register_participant'));
        add_action('wp_ajax_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        add_action('wp_ajax_nopriv_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        add_action('wp_ajax_vefify_submit_answer', array($this, 'ajax_submit_answer'));
        add_action('wp_ajax_nopriv_vefify_submit_answer', array($this, 'ajax_submit_answer'));
        add_action('wp_ajax_vefify_finish_quiz', array($this, 'ajax_finish_quiz'));
        add_action('wp_ajax_nopriv_vefify_finish_quiz', array($this, 'ajax_finish_quiz'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * 🧪 KEEP YOUR WORKING SIMPLE TEST (Enhanced)
     */
    public function simple_test($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => '1',
            'debug' => 'false'
        ), $atts);
        
        $campaign_id = intval($atts['campaign_id']);
        $current_time = current_time('mysql');
        
        $output = '<div class="vefify-test-output">';
        $output .= '<h3>✅ Simple Test Working!</h3>';
        $output .= '<p><strong>Campaign ID:</strong> ' . $campaign_id . '</p>';
        $output .= '<p><strong>Current time:</strong> ' . $current_time . '</p>';
        
        // Enhanced: Check database connection
        if ($this->database) {
            $campaigns_table = $this->database->get_table_name('campaigns');
            if ($campaigns_table) {
                $campaign_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$campaigns_table}");
                $output .= '<p><strong>Campaigns in database:</strong> ' . ($campaign_count ?: '0') . '</p>';
            } else {
                $output .= '<p><strong>Database:</strong> ❌ Tables not found</p>';
            }
        } else {
            $output .= '<p><strong>Database:</strong> ❌ Not connected</p>';
        }
        
        $output .= '<p>If you see this, shortcodes are working! 🎉</p>';
        
        // Show debug info if requested
        if ($atts['debug'] === 'true') {
            $output .= '<div class="vefify-debug">';
            $output .= '<h4>🔍 Debug Information:</h4>';
            $output .= '<p><strong>Plugin URL:</strong> ' . VEFIFY_QUIZ_PLUGIN_URL . '</p>';
            $output .= '<p><strong>Plugin Dir:</strong> ' . VEFIFY_QUIZ_PLUGIN_DIR . '</p>';
            $output .= '<p><strong>WordPress Version:</strong> ' . get_bloginfo('version') . '</p>';
            $output .= '<p><strong>PHP Version:</strong> ' . phpversion() . '</p>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * 🔍 ENHANCED DEBUG TEST
     */
    public function debug_test($atts) {
        $output = '<div class="vefify-debug-test">';
        $output .= '<h3>🔍 Vefify Quiz Debug Test</h3>';
        
        // Test database connection
        if ($this->database) {
            $output .= '<p>✅ Database class loaded</p>';
            
            $tables = array('campaigns', 'questions', 'participants', 'gifts');
            foreach ($tables as $table) {
                $table_name = $this->database->get_table_name($table);
                if ($table_name) {
                    $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                    $output .= '<p>✅ Table ' . $table . ': ' . ($count ?: '0') . ' records</p>';
                } else {
                    $output .= '<p>❌ Table ' . $table . ': Not found</p>';
                }
            }
        } else {
            $output .= '<p>❌ Database class not loaded</p>';
        }
        
        // Test AJAX endpoints
        $output .= '<h4>🔗 AJAX Endpoints:</h4>';
        $output .= '<p><strong>AJAX URL:</strong> ' . admin_url('admin-ajax.php') . '</p>';
        $output .= '<p><strong>Nonce:</strong> ' . wp_create_nonce('vefify_quiz_nonce') . '</p>';
        
        // Test file includes
        $output .= '<h4>📁 File Check:</h4>';
        $files_to_check = array(
            'Database' => VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-database.php',
            'Participant Model' => VEFIFY_QUIZ_PLUGIN_DIR . 'modules/participants/class-participant-model.php',
            'Question Model' => VEFIFY_QUIZ_PLUGIN_DIR . 'modules/questions/class-question-model.php'
        );
        
        foreach ($files_to_check as $name => $file) {
            if (file_exists($file)) {
                $output .= '<p>✅ ' . $name . ': Found</p>';
            } else {
                $output .= '<p>❌ ' . $name . ': Missing (' . basename($file) . ')</p>';
            }
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * 📋 MAIN QUIZ SHORTCODE
     * Usage: [vefify_quiz campaign_id="2" fields="name,email,phone,province,pharmacy_code,occupation,company" style="modern"]
     */
    public function render_quiz($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'fields' => 'name,email,phone', // Default fields
            'style' => 'default', // default, modern, minimal
            'title' => '',
            'description' => '',
            'theme' => 'light' // light, dark, blue
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
        
        // Parse custom fields
        $requested_fields = array_map('trim', explode(',', $atts['fields']));
        $available_fields = $this->get_available_fields();
        $valid_fields = array_intersect($requested_fields, array_keys($available_fields));
        
        if (empty($valid_fields)) {
            return '<div class="vefify-error">❌ No valid fields specified. Available: ' . implode(', ', array_keys($available_fields)) . '</div>';
        }
        
        // Generate unique quiz ID
        $quiz_id = 'vefify_quiz_' . $campaign_id . '_' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($quiz_id); ?>" class="vefify-quiz-container vefify-style-<?php echo esc_attr($atts['style']); ?> vefify-theme-<?php echo esc_attr($atts['theme']); ?>" data-campaign-id="<?php echo $campaign_id; ?>">
            
            <!-- Loading overlay -->
            <div class="vefify-loading-overlay" style="display: none;">
                <div class="vefify-spinner"></div>
                <p>Loading quiz...</p>
            </div>
            
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
            
            <!-- Registration Form -->
            <div class="vefify-registration-section">
                <h3>📝 Registration Required</h3>
                <p>Please fill in your information to start the quiz:</p>
                
                <form id="vefify-registration-form" class="vefify-form">
                    <?php wp_nonce_field('vefify_quiz_nonce', 'vefify_nonce'); ?>
                    <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
                    
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
            
            <!-- Quiz Section (Hidden initially) -->
            <div class="vefify-quiz-section" style="display: none;">
                <div class="vefify-quiz-progress">
                    <div class="vefify-progress-bar">
                        <div class="vefify-progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="vefify-progress-text">Question <span class="current">1</span> of <span class="total"><?php echo $campaign->questions_per_quiz; ?></span></div>
                </div>
                
                <?php if ($campaign->time_limit): ?>
                    <div class="vefify-timer">
                        <span class="vefify-timer-icon">⏱️</span>
                        <span class="vefify-timer-text">Time remaining: <span id="vefify-time-remaining"><?php echo round($campaign->time_limit / 60); ?>:00</span></span>
                    </div>
                <?php endif; ?>
                
                <div id="vefify-question-container">
                    <!-- Questions will be loaded here via AJAX -->
                </div>
                
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
            </div>
            
            <!-- Results Section (Hidden initially) -->
            <div class="vefify-results-section" style="display: none;">
                <div class="vefify-results-content">
                    <!-- Results will be displayed here -->
                </div>
            </div>
        </div>
        
        <!-- Initialize Quiz JavaScript -->
        <script>
        jQuery(document).ready(function($) {
            // Initialize Vefify Quiz
            if (typeof VefifyQuiz !== 'undefined') {
                VefifyQuiz.init('<?php echo $quiz_id; ?>', {
                    campaignId: <?php echo $campaign_id; ?>,
                    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                    nonce: '<?php echo wp_create_nonce('vefify_quiz_nonce'); ?>',
                    timeLimit: <?php echo intval($campaign->time_limit); ?>,
                    questionsPerQuiz: <?php echo intval($campaign->questions_per_quiz); ?>,
                    passScore: <?php echo intval($campaign->pass_score); ?>
                });
            } else {
                console.error('VefifyQuiz JavaScript not loaded');
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 📋 QUIZ LIST SHORTCODE
     * Usage: [vefify_quiz_list limit="5"]
     */
    public function render_quiz_list($atts) {
        $atts = shortcode_atts(array(
            'limit' => '10',
            'style' => 'default',
            'show_description' => 'true',
            'show_stats' => 'true'
        ), $atts);
        
        $campaigns = $this->get_active_campaigns(intval($atts['limit']));
        
        if (empty($campaigns)) {
            return '<div class="vefify-notice">📋 No active quiz campaigns available at this time.</div>';
        }
        
        ob_start();
        ?>
        <div class="vefify-quiz-list vefify-style-<?php echo esc_attr($atts['style']); ?>">
            <h3>🎯 Available Quizzes</h3>
            
            <div class="vefify-quiz-grid">
                <?php foreach ($campaigns as $campaign): ?>
                    <div class="vefify-quiz-card">
                        <div class="vefify-quiz-card-header">
                            <h4 class="vefify-quiz-card-title"><?php echo esc_html($campaign->name); ?></h4>
                        </div>
                        
                        <?php if ($atts['show_description'] === 'true' && $campaign->description): ?>
                            <div class="vefify-quiz-card-description">
                                <?php echo wp_kses_post(wp_trim_words($campaign->description, 20)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($atts['show_stats'] === 'true'): ?>
                            <div class="vefify-quiz-card-stats">
                                <span class="vefify-stat">📝 <?php echo $campaign->questions_per_quiz; ?> questions</span>
                                <?php if ($campaign->time_limit): ?>
                                    <span class="vefify-stat">⏱️ <?php echo round($campaign->time_limit / 60); ?> min</span>
                                <?php endif; ?>
                                <span class="vefify-stat">🎯 Pass: <?php echo $campaign->pass_score; ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="vefify-quiz-card-actions">
                            <a href="?quiz=<?php echo $campaign->id; ?>" class="vefify-btn vefify-btn-primary">
                                🚀 Start Quiz
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 📊 CAMPAIGN INFO SHORTCODE
     * Usage: [vefify_campaign campaign_id="1"]
     */
    public function render_campaign_info($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'show_stats' => 'true'
        ), $atts);
        
        if (empty($atts['campaign_id'])) {
            return '<div class="vefify-error">❌ Campaign ID is required</div>';
        }
        
        $campaign = $this->get_campaign(intval($atts['campaign_id']));
        if (!$campaign) {
            return '<div class="vefify-error">❌ Campaign not found</div>';
        }
        
        ob_start();
        ?>
        <div class="vefify-campaign-info">
            <h3><?php echo esc_html($campaign->name); ?></h3>
            
            <?php if ($campaign->description): ?>
                <div class="vefify-campaign-description">
                    <?php echo wp_kses_post($campaign->description); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_stats'] === 'true'): ?>
                <div class="vefify-campaign-stats">
                    <div class="vefify-stat-item">
                        <span class="vefify-stat-label">Questions:</span>
                        <span class="vefify-stat-value"><?php echo $campaign->questions_per_quiz; ?></span>
                    </div>
                    
                    <?php if ($campaign->time_limit): ?>
                        <div class="vefify-stat-item">
                            <span class="vefify-stat-label">Time Limit:</span>
                            <span class="vefify-stat-value"><?php echo round($campaign->time_limit / 60); ?> minutes</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="vefify-stat-item">
                        <span class="vefify-stat-label">Pass Score:</span>
                        <span class="vefify-stat-value"><?php echo $campaign->pass_score; ?></span>
                    </div>
                    
                    <div class="vefify-stat-item">
                        <span class="vefify-stat-label">Period:</span>
                        <span class="vefify-stat-value">
                            <?php echo date('M j', strtotime($campaign->start_date)); ?> - 
                            <?php echo date('M j, Y', strtotime($campaign->end_date)); ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 🎨 RENDER FORM FIELD
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
     * 📝 GET AVAILABLE FORM FIELDS
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
                'required' => true
            ),
            'phone' => array(
                'label' => 'Phone Number',
                'type' => 'tel',
                'placeholder' => '0938474356',
                'pattern' => '^(0[0-9]{9}|84[0-9]{9})$',
                'required' => true,
                'help' => 'Định dạng: 0938474356 hoặc 84938474356'
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
    
    /**
     * 📊 GET CAMPAIGN DATA
     */
	    protected function get_campaign($campaign_id) {
        if (!$this->database) {
            return null;
        }
        
        $campaigns_table = $this->database->get_table_name('campaigns');
        if (!$campaigns_table) {
            // Fallback to direct table name
            $campaigns_table = $this->wpdb->prefix . 'vefify_campaigns';
        }
        
        $campaign = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$campaigns_table} WHERE id = %d",
            $campaign_id
        ));
        
        if ($this->wpdb->last_error) {
            error_log('Vefify Quiz: Error getting campaign: ' . $this->wpdb->last_error);
            return null;
        }
        
        return $campaign;
    }
    
    /**
     * ✅ CHECK IF CAMPAIGN IS ACTIVE
     */
    protected function is_campaign_active($campaign) {
        if (!$campaign) {
            return false;
        }
        
        $now = current_time('mysql');
        $start_date = $campaign->start_date;
        $end_date = $campaign->end_date;
        
        return ($now >= $start_date && $now <= $end_date);
    }
    
    /**
     * 📋 GET ACTIVE CAMPAIGNS
     */
    private function get_active_campaigns($limit = 10) {
        if (!$this->database) {
            return array();
        }
        
        $campaigns_table = $this->database->get_table_name('campaigns');
        if (!$campaigns_table) {
            return array();
        }
        
        $now = current_time('mysql');
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$campaigns_table} 
             WHERE is_active = 1 
             AND start_date <= %s 
             AND end_date >= %s 
             ORDER BY created_at DESC 
             LIMIT %d",
            $now, $now, $limit
        ));
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
        
        // Collect and sanitize participant data
        $participant_data = array(
		   'campaign_id' => $campaign_id,
			'session_id' => wp_generate_password(32, false),
			// CORRECT COLUMN NAMES FROM YOUR DATABASE:
			'participant_name' => sanitize_text_field($_POST['name'] ?? ''),           // NOT 'full_name'
			'participant_email' => sanitize_email($_POST['email'] ?? ''),             // NOT 'email'
			'participant_phone' => sanitize_text_field($_POST['phone'] ?? ''),        // NOT 'phone_number'
			'province' => sanitize_text_field($_POST['province'] ?? ''),
			'pharmacy_code' => sanitize_text_field($_POST['pharmacy_code'] ?? ''),
			'company' => sanitize_text_field($_POST['company'] ?? ''),
			'occupation' => sanitize_text_field($_POST['occupation'] ?? ''),
			'age' => intval($_POST['age'] ?? 0),
			'quiz_status' => 'started',
			'start_time' => current_time('mysql'),
			'ip_address' => $_SERVER['REMOTE_ADDR'],
			'user_agent' => $_SERVER['HTTP_USER_AGENT'],
			'created_at' => current_time('mysql')
        );
        
        // Validate required fields
        if (empty($participant_data['full_name']) || empty($participant_data['phone_number'])) {
            wp_send_json_error('Name and phone number are required');
        }
        
        // Check for duplicate phone in this campaign
        $existing = $this->check_existing_participant($campaign_id, $participant_data['phone_number']);
        if ($existing) {
            wp_send_json_error('Phone number already registered for this campaign');
        }
        
        // Insert participant
        $participants_table = $this->database->get_table_name('participants');
        $result = $this->wpdb->insert($participants_table, $participant_data);
        
        if ($result === false) {
            wp_send_json_error('Registration failed. Please try again.');
        }
        
        $participant_id = $this->wpdb->insert_id;
        
        // Generate session token
        $session_token = wp_generate_password(32, false);
        
        // Update with session token
        $this->wpdb->update(
            $participants_table,
            array('session_token' => $session_token),
            array('id' => $participant_id)
        );
        
        wp_send_json_success(array(
            'participant_id' => $participant_id,
            'session_token' => $session_token,
            'message' => 'Registration successful!'
        ));
    }
    
    /**
     * 🎯 AJAX: START QUIZ
     */
    public function ajax_start_quiz() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['vefify_nonce'], 'vefify_quiz_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $participant_id = intval($_POST['participant_id']);
        $session_token = sanitize_text_field($_POST['session_token']);
        
        // Verify participant session
        $participant = $this->verify_participant_session($participant_id, $session_token);
        if (!$participant) {
            wp_send_json_error('Invalid session');
        }
        
        // Get campaign
        $campaign = $this->get_campaign($participant->campaign_id);
        if (!$campaign || !$this->is_campaign_active($campaign)) {
            wp_send_json_error('Campaign not available');
        }
        
        // Get questions for this campaign
        $questions = $this->get_quiz_questions($participant->campaign_id, $campaign->questions_per_quiz);
        
        if (empty($questions)) {
            wp_send_json_error('No questions available');
        }
        
        // Update participant status to started
        $this->wpdb->update(
            $this->database->get_table_name('participants'),
            array(
                'quiz_status' => 'started',
                'quiz_started_at' => current_time('mysql')
            ),
            array('id' => $participant_id)
        );
        
        wp_send_json_success(array(
            'questions' => $questions,
            'time_limit' => intval($campaign->time_limit),
            'total_questions' => count($questions)
        ));
    }
    
    /**
     * ✅ AJAX: SUBMIT ANSWER
     */
    public function ajax_submit_answer() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['vefify_nonce'], 'vefify_quiz_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $participant_id = intval($_POST['participant_id']);
        $question_id = intval($_POST['question_id']);
        $answer = sanitize_text_field($_POST['answer']);
        
        // Store answer in session or temporary table
        // For now, we'll use session
        if (!isset($_SESSION['quiz_answers'])) {
            session_start();
            $_SESSION['quiz_answers'] = array();
        }
        
        $_SESSION['quiz_answers'][$question_id] = $answer;
        
        wp_send_json_success(array(
            'message' => 'Answer saved',
            'question_id' => $question_id
        ));
    }
    
    /**
     * 🏁 AJAX: FINISH QUIZ
     */
    public function ajax_finish_quiz() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['vefify_nonce'], 'vefify_quiz_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $participant_id = intval($_POST['participant_id']);
        $session_token = sanitize_text_field($_POST['session_token']);
        $answers = $_POST['answers'] ?? array();
        
        // Verify participant session
        $participant = $this->verify_participant_session($participant_id, $session_token);
        if (!$participant) {
            wp_send_json_error('Invalid session');
        }
        
        // Calculate score
        $score_result = $this->calculate_quiz_score($answers);
        
        // Update participant with final results
        $this->wpdb->update(
            $this->database->get_table_name('participants'),
            array(
                'quiz_status' => 'completed',
                'final_score' => $score_result['score'],
                'total_questions' => $score_result['total'],
                'correct_answers' => $score_result['correct'],
                'quiz_completed_at' => current_time('mysql'),
                'quiz_answers' => json_encode($answers)
            ),
            array('id' => $participant_id)
        );
        
        // Check for gifts
        $gift_result = $this->check_and_assign_gift($participant_id, $score_result['score']);
        
        wp_send_json_success(array(
            'score' => $score_result['score'],
            'total' => $score_result['total'],
            'correct' => $score_result['correct'],
            'percentage' => $score_result['percentage'],
            'passed' => $score_result['passed'],
            'gift' => $gift_result,
            'message' => 'Quiz completed successfully!'
        ));
    }
    
    /**
     * 🔍 CHECK EXISTING PARTICIPANT
     */
    private function check_existing_participant($campaign_id, $phone) {
        $participants_table = $this->database->get_table_name('participants');
        
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$participants_table} 
             WHERE campaign_id = %d AND phone_number = %s",
            $campaign_id, $phone
        ));
    }
    
    /**
     * ✅ VERIFY PARTICIPANT SESSION
     */
    private function verify_participant_session($participant_id, $session_token) {
        $participants_table = $this->database->get_table_name('participants');
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$participants_table} 
             WHERE id = %d AND session_token = %s",
            $participant_id, $session_token
        ));
    }
    
    /**
     * 📝 GET QUIZ QUESTIONS
     */
		protected function get_quiz_questions($campaign_id, $limit) {
        // Keep your exact existing code - it's perfect!
        if (!$this->database) {
            error_log('Vefify Quiz: Database not available');
            return array();
        }
        
        $questions_table = $this->database->get_table_name('questions');
        $options_table = $this->database->get_table_name('question_options');
        
        if (!$questions_table || !$options_table) {
            error_log('Vefify Quiz: Could not get table names');
            return array();
        }
        
        // Your original query - it's excellent!
        $questions = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$questions_table} 
             WHERE campaign_id = %d AND is_active = 1 
             ORDER BY RAND() 
             LIMIT %d",
            $campaign_id, $limit
        ), ARRAY_A);
        
        if ($this->wpdb->last_error) {
            error_log('Vefify Quiz: SQL Error: ' . $this->wpdb->last_error);
            return array();
        }
        
        // Your original options loading - also excellent!
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
     * 📊 CALCULATE QUIZ SCORE
     */
    private function calculate_quiz_score($answers) {
        $total_questions = count($answers);
        $correct_answers = 0;
        
        foreach ($answers as $question_id => $answer) {
            // Get correct answer from database
            $correct_answer = $this->get_correct_answer($question_id);
            if ($answer === $correct_answer) {
                $correct_answers++;
            }
        }
        
        $percentage = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100, 1) : 0;
        
        return array(
            'score' => $correct_answers,
            'total' => $total_questions,
            'correct' => $correct_answers,
            'percentage' => $percentage,
            'passed' => $correct_answers >= 3 // Default pass score
        );
    }
    
    /**
     * ✅ GET CORRECT ANSWER
     */
    private function get_correct_answer($question_id) {
        $options_table = $this->database->get_table_name('question_options');
        
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT option_value FROM {$options_table} 
             WHERE question_id = %d AND is_correct = 1 
             LIMIT 1",
            $question_id
        ));
    }
    
    /**
     * 🎁 CHECK AND ASSIGN GIFT
     */
    private function check_and_assign_gift($participant_id, $score) {
        // Implementation for gift assignment
        // This would check gift rules and assign appropriate gifts
        return null; // For now
    }
    
    /**
     * 📚 ENQUEUE FRONTEND SCRIPTS
     */
    public function enqueue_frontend_scripts() {
        // Only on pages with shortcodes
        global $post;
        
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'vefify_quiz') ||
            has_shortcode($post->post_content, 'vefify_simple_test') ||
            has_shortcode($post->post_content, 'vefify_test')
        )) {
            
            // Enqueue jQuery
            wp_enqueue_script('jquery');
            
            // Enqueue our quiz scripts
            wp_enqueue_script(
                'vefify-quiz-frontend',
                VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/frontend-quiz.js',
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
            
            // Localize script
            wp_localize_script('vefify-quiz-frontend', 'vefifyAjax', array(
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
        }
    }
}

// Initialize the shortcode system
new Vefify_Quiz_Shortcodes();