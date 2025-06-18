<?php
/**
 * Form Settings Class - COMPLETE FIXED VERSION
 * File: modules/settings/class-form-settings.php
 * 
 * FIXES:
 * - Added static get_instance() method
 * - Added singleton pattern
 * - Fixed all conflicts and method calls
 * - Added analytics integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Form_Settings {
    
    // Singleton instance
    private static $instance = null;
    
    private $option_group = 'vefify_quiz_settings';
    private $option_name = 'vefify_quiz_options';
    private $settings = array();
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - private to enforce singleton
     */
    private function __construct() {
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_menu', array($this, 'add_settings_submenu'), 99);
        add_action('wp_ajax_vefify_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_vefify_reset_settings', array($this, 'ajax_reset_settings'));
        
        // Load current settings
        $this->load_settings();
    }
    
    /**
     * Load current settings
     */
    private function load_settings() {
        $defaults = $this->get_default_settings();
        $saved_settings = get_option($this->option_name, array());
        
        $this->settings = wp_parse_args($saved_settings, $defaults);
    }
    
    /**
     * Get default settings
     */
    private function get_default_settings() {
        return array(
            // General Settings
            'default_questions_per_quiz' => 5,
            'default_time_limit' => 600, // 10 minutes
            'default_pass_score' => 3,
            'enable_retakes' => false,
            'max_retakes' => 1,
            
            // User Requirements
            'phone_number_required' => true,
            'province_required' => true,
            'pharmacy_code_required' => false,
            'email_required' => false,
            'terms_required' => false,
            
            // Quiz Behavior
            'auto_advance' => true,
            'show_progress' => true,
            'show_timer' => true,
            'randomize_questions' => true,
            'randomize_answers' => true,
            
            // Results & Gifts
            'show_results_immediately' => true,
            'show_correct_answers' => false,
            'enable_leaderboard' => true,
            'leaderboard_anonymous' => true,
            'gift_system_enabled' => true,
            
            // Analytics & Tracking
            'enable_analytics' => true,
            'track_ip_addresses' => true,
            'data_retention_days' => 90,
            
            // Security
            'enable_rate_limiting' => true,
            'max_attempts_per_hour' => 3,
            'require_unique_phone' => true,
            
            // Appearance
            'form_theme' => 'default',
            'primary_color' => '#4facfe',
            'button_style' => 'rounded',
            'enable_animations' => true
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            array($this, 'sanitize_settings')
        );
        
        // General Section
        add_settings_section(
            'vefify_general_settings',
            __('General Settings', 'vefify-quiz'),
            array($this, 'general_settings_callback'),
            'vefify_quiz_settings'
        );
        
        // Quiz Behavior Section
        add_settings_section(
            'vefify_behavior_settings',
            __('Quiz Behavior', 'vefify-quiz'),
            array($this, 'behavior_settings_callback'),
            'vefify_quiz_settings'
        );
        
        // User Requirements Section
        add_settings_section(
            'vefify_requirements_settings',
            __('User Requirements', 'vefify-quiz'),
            array($this, 'requirements_settings_callback'),
            'vefify_quiz_settings'
        );
        
        // Security Section
        add_settings_section(
            'vefify_security_settings',
            __('Security & Rate Limiting', 'vefify-quiz'),
            array($this, 'security_settings_callback'),
            'vefify_quiz_settings'
        );
        
        // Appearance Section
        add_settings_section(
            'vefify_appearance_settings',
            __('Appearance', 'vefify-quiz'),
            array($this, 'appearance_settings_callback'),
            'vefify_quiz_settings'
        );
        
        // Advanced Section
        add_settings_section(
            'vefify_advanced_settings',
            __('Advanced', 'vefify-quiz'),
            array($this, 'advanced_settings_callback'),
            'vefify_quiz_settings'
        );
        
        // Add individual settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Add settings submenu (with conflict prevention)
     */
    public function add_settings_submenu() {
        // Check if settings page already exists
        global $submenu;
        if (isset($submenu['vefify-quiz'])) {
            foreach ($submenu['vefify-quiz'] as $item) {
                if (strpos($item[2], 'settings') !== false) {
                    // Settings menu already exists, skip adding
                    return;
                }
            }
        }
        
        add_submenu_page(
            'vefify-quiz',
            __('Quiz Settings', 'vefify-quiz'),
            __('⚙️ Settings', 'vefify-quiz'),
            'manage_options',
            'vefify-quiz-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Add all settings fields
     */
    private function add_settings_fields() {
        $fields = array(
            // General Settings
            array(
                'section' => 'vefify_general_settings',
                'id' => 'default_questions_per_quiz',
                'title' => __('Default Questions per Quiz', 'vefify-quiz'),
                'type' => 'number',
                'args' => array('min' => 1, 'max' => 50)
            ),
            array(
                'section' => 'vefify_general_settings',
                'id' => 'default_time_limit',
                'title' => __('Default Time Limit (seconds)', 'vefify-quiz'),
                'type' => 'number',
                'args' => array('min' => 60, 'max' => 3600)
            ),
            array(
                'section' => 'vefify_general_settings',
                'id' => 'default_pass_score',
                'title' => __('Default Pass Score', 'vefify-quiz'),
                'type' => 'number',
                'args' => array('min' => 1, 'max' => 10)
            ),
            array(
                'section' => 'vefify_general_settings',
                'id' => 'enable_retakes',
                'title' => __('Allow Retakes', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            
            // User Requirements
            array(
                'section' => 'vefify_requirements_settings',
                'id' => 'phone_number_required',
                'title' => __('Phone Number Required', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_requirements_settings',
                'id' => 'province_required',
                'title' => __('Province Required', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_requirements_settings',
                'id' => 'pharmacy_code_required',
                'title' => __('Pharmacy Code Required', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_requirements_settings',
                'id' => 'email_required',
                'title' => __('Email Required', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            
            // Quiz Behavior
            array(
                'section' => 'vefify_behavior_settings',
                'id' => 'show_progress',
                'title' => __('Show Progress Bar', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_behavior_settings',
                'id' => 'show_timer',
                'title' => __('Show Timer', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_behavior_settings',
                'id' => 'randomize_questions',
                'title' => __('Randomize Questions', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_behavior_settings',
                'id' => 'randomize_answers',
                'title' => __('Randomize Answers', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            
            // Security
            array(
                'section' => 'vefify_security_settings',
                'id' => 'enable_rate_limiting',
                'title' => __('Enable Rate Limiting', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_security_settings',
                'id' => 'max_attempts_per_hour',
                'title' => __('Max Attempts Per Hour', 'vefify-quiz'),
                'type' => 'number',
                'args' => array('min' => 1, 'max' => 10)
            ),
            
            // Appearance
            array(
                'section' => 'vefify_appearance_settings',
                'id' => 'form_theme',
                'title' => __('Form Theme', 'vefify-quiz'),
                'type' => 'select',
                'args' => array(
                    'options' => array(
                        'default' => 'Default',
                        'modern' => 'Modern',
                        'minimal' => 'Minimal',
                        'colorful' => 'Colorful'
                    )
                )
            ),
            array(
                'section' => 'vefify_appearance_settings',
                'id' => 'primary_color',
                'title' => __('Primary Color', 'vefify-quiz'),
                'type' => 'color'
            )
        );
        
        foreach ($fields as $field) {
            add_settings_field(
                $field['id'],
                $field['title'],
                array($this, 'render_field'),
                'vefify_quiz_settings',
                $field['section'],
                array(
                    'id' => $field['id'],
                    'type' => $field['type'],
                    'args' => $field['args'] ?? array()
                )
            );
        }
    }
    
    /**
     * Render settings field
     */
    public function render_field($args) {
        $id = $args['id'];
        $type = $args['type'];
        $field_args = $args['args'];
        $value = $this->settings[$id] ?? '';
        $name = $this->option_name . '[' . $id . ']';
        
        switch ($type) {
            case 'text':
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
                    esc_attr($id),
                    esc_attr($name),
                    esc_attr($value)
                );
                break;
                
            case 'number':
                $min = $field_args['min'] ?? '';
                $max = $field_args['max'] ?? '';
                printf(
                    '<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" class="small-text" />',
                    esc_attr($id),
                    esc_attr($name),
                    esc_attr($value),
                    esc_attr($min),
                    esc_attr($max)
                );
                break;
                
            case 'checkbox':
                printf(
                    '<label><input type="checkbox" id="%s" name="%s" value="1" %s /> %s</label>',
                    esc_attr($id),
                    esc_attr($name),
                    checked(1, $value, false),
                    __('Enable', 'vefify-quiz')
                );
                break;
                
            case 'color':
                printf(
                    '<input type="color" id="%s" name="%s" value="%s" />',
                    esc_attr($id),
                    esc_attr($name),
                    esc_attr($value)
                );
                break;
                
            case 'textarea':
                printf(
                    '<textarea id="%s" name="%s" rows="5" cols="50" class="large-text">%s</textarea>',
                    esc_attr($id),
                    esc_attr($name),
                    esc_textarea($value)
                );
                break;
                
            case 'select':
                $options = $field_args['options'] ?? array();
                printf('<select id="%s" name="%s">', esc_attr($id), esc_attr($name));
                foreach ($options as $option_value => $option_label) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr($option_value),
                        selected($value, $option_value, false),
                        esc_html($option_label)
                    );
                }
                echo '</select>';
                break;
        }
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer($this->option_group . '-options');
            
            $new_settings = $_POST[$this->option_name] ?? array();
            $sanitized = $this->sanitize_settings($new_settings);
            
            update_option($this->option_name, $sanitized);
            $this->settings = $sanitized;
            
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
        }
        
        ?>
        <div class="wrap vefify-settings-page">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="vefify-settings-header">
                <p><?php _e('Configure your quiz plugin settings below. Changes will be applied immediately.', 'vefify-quiz'); ?></p>
                
                <div class="settings-actions">
                    <button type="button" class="button button-secondary" id="export-settings">
                        <?php _e('Export Settings', 'vefify-quiz'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="import-settings">
                        <?php _e('Import Settings', 'vefify-quiz'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="reset-settings">
                        <?php _e('Reset to Defaults', 'vefify-quiz'); ?>
                    </button>
                </div>
            </div>
            
            <div class="vefify-settings-container">
                <div class="settings-nav">
                    <ul class="settings-tabs">
                        <li><a href="#general" class="active"><?php _e('General', 'vefify-quiz'); ?></a></li>
                        <li><a href="#behavior"><?php _e('Quiz Behavior', 'vefify-quiz'); ?></a></li>
                        <li><a href="#requirements"><?php _e('User Requirements', 'vefify-quiz'); ?></a></li>
                        <li><a href="#security"><?php _e('Security', 'vefify-quiz'); ?></a></li>
                        <li><a href="#appearance"><?php _e('Appearance', 'vefify-quiz'); ?></a></li>
                        <li><a href="#advanced"><?php _e('Advanced', 'vefify-quiz'); ?></a></li>
                    </ul>
                </div>
                
                <div class="settings-content">
                    <form method="post" action="" id="vefify-settings-form">
                        <?php
                        settings_fields($this->option_group);
                        do_settings_sections('vefify_quiz_settings');
                        ?>
                        
                        <div class="settings-footer">
                            <?php submit_button(__('Save Settings', 'vefify-quiz'), 'primary', 'submit', false); ?>
                            <button type="button" class="button button-secondary" id="preview-settings">
                                <?php _e('Preview Changes', 'vefify-quiz'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <style>
        .vefify-settings-page {
            background: #f1f1f1;
            margin: 20px 0;
        }
        
        .vefify-settings-header {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .settings-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .vefify-settings-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .settings-nav {
            background: #f9f9f9;
            padding: 0;
            border-right: 1px solid #eee;
        }
        
        .settings-tabs {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .settings-tabs li {
            margin: 0;
            padding: 0;
            border-bottom: 1px solid #eee;
        }
        
        .settings-tabs a {
            display: block;
            padding: 15px 20px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .settings-tabs a:hover,
        .settings-tabs a.active {
            background: #4facfe;
            color: #fff;
        }
        
        .settings-content {
            padding: 30px;
            min-height: 600px;
        }
        
        .form-table {
            margin-top: 20px;
        }
        
        .form-table th {
            width: 200px;
            font-weight: 600;
            color: #333;
        }
        
        .settings-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .vefify-settings-container {
                grid-template-columns: 1fr;
            }
            
            .settings-nav {
                border-right: none;
                border-bottom: 1px solid #eee;
            }
            
            .settings-tabs {
                display: flex;
                overflow-x: auto;
            }
            
            .settings-tabs li {
                border-bottom: none;
                border-right: 1px solid #eee;
                flex-shrink: 0;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.settings-tabs a').click(function(e) {
                e.preventDefault();
                
                $('.settings-tabs a').removeClass('active');
                $(this).addClass('active');
                
                var target = $(this).attr('href');
                $('.form-table').hide();
                $(target + '-table').show();
            });
            
            // Export settings
            $('#export-settings').click(function() {
                var settings = {};
                $('input, select, textarea').each(function() {
                    if ($(this).attr('name') && $(this).attr('name').startsWith('vefify_quiz_options')) {
                        settings[$(this).attr('name')] = $(this).val();
                    }
                });
                
                var blob = new Blob([JSON.stringify(settings, null, 2)], {type: 'application/json'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'vefify-quiz-settings.json';
                a.click();
            });
            
            // Reset settings
            $('#reset-settings').click(function() {
                if (confirm('Are you sure you want to reset all settings to defaults?')) {
                    $.post(ajaxurl, {
                        action: 'vefify_reset_settings',
                        nonce: '<?php echo wp_create_nonce('vefify_reset_settings'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Failed to reset settings');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Section callbacks
     */
    public function general_settings_callback() {
        echo '<p>Configure basic quiz settings and defaults.</p>';
    }
    
    public function behavior_settings_callback() {
        echo '<p>Control how quizzes behave and what features are enabled.</p>';
    }
    
    public function requirements_settings_callback() {
        echo '<p>Set which user information fields are required.</p>';
    }
    
    public function security_settings_callback() {
        echo '<p>Configure security measures and rate limiting.</p>';
    }
    
    public function appearance_settings_callback() {
        echo '<p>Customize the visual appearance of your quizzes.</p>';
    }
    
    public function advanced_settings_callback() {
        echo '<p>Advanced configuration options for power users.</p>';
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($settings) {
        $sanitized = array();
        
        foreach ($settings as $key => $value) {
            switch ($key) {
                case 'default_questions_per_quiz':
                case 'default_time_limit':
                case 'default_pass_score':
                case 'max_retakes':
                case 'max_attempts_per_hour':
                case 'data_retention_days':
                    $sanitized[$key] = intval($value);
                    break;
                    
                case 'enable_retakes':
                case 'phone_number_required':
                case 'province_required':
                case 'pharmacy_code_required':
                case 'email_required':
                case 'terms_required':
                case 'auto_advance':
                case 'show_progress':
                case 'show_timer':
                case 'randomize_questions':
                case 'randomize_answers':
                case 'show_results_immediately':
                case 'show_correct_answers':
                case 'enable_leaderboard':
                case 'leaderboard_anonymous':
                case 'gift_system_enabled':
                case 'enable_analytics':
                case 'track_ip_addresses':
                case 'enable_rate_limiting':
                case 'require_unique_phone':
                case 'enable_animations':
                    $sanitized[$key] = !empty($value);
                    break;
                    
                case 'primary_color':
                    $sanitized[$key] = sanitize_hex_color($value);
                    break;
                    
                case 'form_theme':
                case 'button_style':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                    
                default:
                    if (is_array($value)) {
                        $sanitized[$key] = array_map('sanitize_text_field', $value);
                    } else {
                        $sanitized[$key] = sanitize_text_field($value);
                    }
                    break;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_save_settings')) {
            wp_send_json_error('Security check failed');
        }
        
        parse_str($_POST['form_data'], $form_data);
        $settings_data = $form_data[$this->option_name] ?? array();
        
        $sanitized = $this->sanitize_settings($settings_data);
        
        $result = update_option($this->option_name, $sanitized);
        
        if ($result) {
            $this->settings = $sanitized;
            wp_send_json_success('Settings saved successfully');
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }
    
    /**
     * AJAX: Reset settings
     */
    public function ajax_reset_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_reset_settings')) {
            wp_send_json_error('Security check failed');
        }
        
        $defaults = $this->get_default_settings();
        $result = update_option($this->option_name, $defaults);
        
        if ($result) {
            wp_send_json_success('Settings reset to defaults');
        } else {
            wp_send_json_error('Failed to reset settings');
        }
    }
    
    /**
     * Get setting value
     */
    public function get_setting($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }
    
    /**
     * Get all settings
     */
    public function get_all_settings() {
        return $this->settings;
    }
}

// Global function to get settings
function vefify_get_setting($key, $default = null) {
    $settings_instance = Vefify_Form_Settings::get_instance();
    return $settings_instance->get_setting($key, $default);
}