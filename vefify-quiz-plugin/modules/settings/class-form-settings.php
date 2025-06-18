<?php
/**
 * Form Settings Class
 * File: modules/settings/class-form-settings.php
 * 
 * Handles plugin settings and configuration forms
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Form_Settings {
    
    private $option_group = 'vefify_quiz_settings';
    private $option_name = 'vefify_quiz_options';
    private $settings = array();
    
    public function __construct() {
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
            'enable_export' => true,
            
            // Security & Rate Limiting
            'enable_rate_limiting' => true,
            'max_attempts_per_hour' => 5,
            'enable_captcha' => false,
            'honeypot_protection' => true,
            
            // Notifications
            'admin_email_notifications' => true,
            'participant_email_notifications' => false,
            'notification_email' => get_option('admin_email'),
            
            // API & Integrations
            'enable_rest_api' => true,
            'api_rate_limit' => 100,
            'webhook_url' => '',
            'webhook_events' => array('quiz_completed', 'gift_claimed'),
            
            // Appearance
            'primary_color' => '#4facfe',
            'secondary_color' => '#667eea',
            'success_color' => '#4caf50',
            'error_color' => '#f44336',
            'border_radius' => 12,
            'enable_animations' => true,
            
            // Advanced
            'debug_mode' => false,
            'cache_enabled' => true,
            'cache_duration' => 3600,
            'cleanup_enabled' => true
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
        
        // General Settings Section
        add_settings_section(
            'vefify_general_settings',
            __('General Settings', 'vefify-quiz'),
            array($this, 'general_settings_callback'),
            'vefify_quiz_settings'
        );
        
        // Quiz Behavior Section
        add_settings_section(
            'vefify_quiz_behavior',
            __('Quiz Behavior', 'vefify-quiz'),
            array($this, 'quiz_behavior_callback'),
            'vefify_quiz_settings'
        );
        
        // User Requirements Section
        add_settings_section(
            'vefify_user_requirements',
            __('User Requirements', 'vefify-quiz'),
            array($this, 'user_requirements_callback'),
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
        if (isset($submenu['vefify-dashboard'])) {
            foreach ($submenu['vefify-dashboard'] as $item) {
                if (strpos($item[2], 'settings') !== false) {
                    // Settings menu already exists, skip adding
                    return;
                }
            }
        }
        
        add_submenu_page(
            'vefify-dashboard',
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
                'args' => array('min' => 0, 'max' => 3600)
            ),
            array(
                'section' => 'vefify_general_settings',
                'id' => 'default_pass_score',
                'title' => __('Default Pass Score', 'vefify-quiz'),
                'type' => 'number',
                'args' => array('min' => 1, 'max' => 50)
            ),
            array(
                'section' => 'vefify_general_settings',
                'id' => 'enable_retakes',
                'title' => __('Enable Retakes', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            
            // Quiz Behavior
            array(
                'section' => 'vefify_quiz_behavior',
                'id' => 'auto_advance',
                'title' => __('Auto-advance Questions', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_quiz_behavior',
                'id' => 'show_progress',
                'title' => __('Show Progress Bar', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_quiz_behavior',
                'id' => 'randomize_questions',
                'title' => __('Randomize Questions', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_quiz_behavior',
                'id' => 'show_results_immediately',
                'title' => __('Show Results Immediately', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            
            // User Requirements
            array(
                'section' => 'vefify_user_requirements',
                'id' => 'phone_number_required',
                'title' => __('Phone Number Required', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_user_requirements',
                'id' => 'province_required',
                'title' => __('Province Required', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_user_requirements',
                'id' => 'pharmacy_code_required',
                'title' => __('Pharmacy Code Required', 'vefify-quiz'),
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
                'title' => __('Max Attempts per Hour', 'vefify-quiz'),
                'type' => 'number',
                'args' => array('min' => 1, 'max' => 100)
            ),
            
            // Appearance
            array(
                'section' => 'vefify_appearance_settings',
                'id' => 'primary_color',
                'title' => __('Primary Color', 'vefify-quiz'),
                'type' => 'color'
            ),
            array(
                'section' => 'vefify_appearance_settings',
                'id' => 'secondary_color',
                'title' => __('Secondary Color', 'vefify-quiz'),
                'type' => 'color'
            ),
            array(
                'section' => 'vefify_appearance_settings',
                'id' => 'enable_animations',
                'title' => __('Enable Animations', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            
            // Advanced
            array(
                'section' => 'vefify_advanced_settings',
                'id' => 'debug_mode',
                'title' => __('Debug Mode', 'vefify-quiz'),
                'type' => 'checkbox'
            ),
            array(
                'section' => 'vefify_advanced_settings',
                'id' => 'cache_enabled',
                'title' => __('Enable Caching', 'vefify-quiz'),
                'type' => 'checkbox'
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
     * Settings page callback
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
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
                    <form method="post" action="options.php" id="vefify-settings-form">
                        <?php
                        settings_fields($this->option_group);
                        do_settings_sections('vefify_quiz_settings');
                        ?>
                        
                        <div class="settings-footer">
                            <?php submit_button(__('Save Settings', 'vefify-quiz'), 'primary', 'submit', false); ?>
                            <div class="save-status" id="save-status"></div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Import Modal -->
            <div id="import-modal" class="vefify-modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><?php _e('Import Settings', 'vefify-quiz'); ?></h2>
                        <button type="button" class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p><?php _e('Paste your exported settings JSON below:', 'vefify-quiz'); ?></p>
                        <textarea id="import-data" rows="10" cols="50" class="large-text"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="button button-primary" id="import-confirm">
                            <?php _e('Import', 'vefify-quiz'); ?>
                        </button>
                        <button type="button" class="button button-secondary close-modal">
                            <?php _e('Cancel', 'vefify-quiz'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .vefify-settings-page {
            max-width: 1200px;
        }
        
        .vefify-settings-header {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .settings-actions {
            display: flex;
            gap: 10px;
        }
        
        .vefify-settings-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
        }
        
        .settings-nav {
            background: #fff;
            border-radius: 8px;
            padding: 20px 0;
            height: fit-content;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .settings-tabs {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .settings-tabs li {
            margin: 0;
        }
        
        .settings-tabs a {
            display: block;
            padding: 12px 20px;
            text-decoration: none;
            color: #333;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .settings-tabs a:hover,
        .settings-tabs a.active {
            background: #f0f8ff;
            border-left-color: #4facfe;
            color: #4facfe;
        }
        
        .settings-content {
            background: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .settings-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e1e1;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .save-status {
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 4px;
            display: none;
        }
        
        .save-status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .save-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .vefify-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: #fff;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid #e1e1e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e1e1e1;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .vefify-settings-container {
                grid-template-columns: 1fr;
            }
            
            .vefify-settings-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
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
                
                const target = $(this).attr('href').substring(1);
                $('h2').each(function() {
                    const section = $(this).closest('table');
                    if ($(this).text().toLowerCase().includes(target) || target === 'general') {
                        section.show();
                    } else {
                        section.hide();
                    }
                });
            });
            
            // Initialize - show only general tab
            $('.settings-tabs a[href="#general"]').click();
            
            // Export settings
            $('#export-settings').click(function() {
                const settings = <?php echo json_encode($this->settings); ?>;
                const dataStr = JSON.stringify(settings, null, 2);
                const dataBlob = new Blob([dataStr], {type: 'application/json'});
                
                const link = document.createElement('a');
                link.href = URL.createObjectURL(dataBlob);
                link.download = 'vefify-quiz-settings.json';
                link.click();
            });
            
            // Import settings
            $('#import-settings').click(function() {
                $('#import-modal').show();
            });
            
            $('.close-modal').click(function() {
                $('#import-modal').hide();
            });
            
            $('#import-confirm').click(function() {
                const importData = $('#import-data').val();
                
                try {
                    const settings = JSON.parse(importData);
                    
                    // Apply settings to form
                    Object.keys(settings).forEach(function(key) {
                        const input = $('[name="<?php echo $this->option_name; ?>[' + key + ']"]');
                        if (input.attr('type') === 'checkbox') {
                            input.prop('checked', settings[key]);
                        } else {
                            input.val(settings[key]);
                        }
                    });
                    
                    $('#import-modal').hide();
                    showStatus('Settings imported successfully!', 'success');
                    
                } catch (e) {
                    alert('Invalid JSON format. Please check your import data.');
                }
            });
            
            // Reset settings
            $('#reset-settings').click(function() {
                if (confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
                    $.post(ajaxurl, {
                        action: 'vefify_reset_settings',
                        nonce: '<?php echo wp_create_nonce('vefify_reset_settings'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Failed to reset settings.');
                        }
                    });
                }
            });
            
            // Form submission with AJAX
            $('#vefify-settings-form').submit(function(e) {
                e.preventDefault();
                
                const formData = $(this).serialize();
                
                $.post(ajaxurl, {
                    action: 'vefify_save_settings',
                    nonce: '<?php echo wp_create_nonce('vefify_save_settings'); ?>',
                    form_data: formData
                }, function(response) {
                    if (response.success) {
                        showStatus('Settings saved successfully!', 'success');
                    } else {
                        showStatus('Failed to save settings.', 'error');
                    }
                });
            });
            
            function showStatus(message, type) {
                const status = $('#save-status');
                status.removeClass('success error').addClass(type);
                status.text(message).show();
                
                setTimeout(function() {
                    status.fadeOut();
                }, 3000);
            }
        });
        </script>
        <?php
    }
    
    /**
     * Section callbacks
     */
    public function general_settings_callback() {
        echo '<p>' . __('Configure basic quiz settings and defaults.', 'vefify-quiz') . '</p>';
    }
    
    public function quiz_behavior_callback() {
        echo '<p>' . __('Control how quizzes behave and appear to participants.', 'vefify-quiz') . '</p>';
    }
    
    public function user_requirements_callback() {
        echo '<p>' . __('Set which information is required from participants.', 'vefify-quiz') . '</p>';
    }
    
    public function security_settings_callback() {
        echo '<p>' . __('Configure security measures and rate limiting.', 'vefify-quiz') . '</p>';
    }
    
    public function appearance_settings_callback() {
        echo '<p>' . __('Customize the visual appearance of your quizzes.', 'vefify-quiz') . '</p>';
    }
    
    public function advanced_settings_callback() {
        echo '<p>' . __('Advanced settings for developers and power users.', 'vefify-quiz') . '</p>';
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        foreach ($input as $key => $value) {
            switch ($key) {
                case 'primary_color':
                case 'secondary_color':
                case 'success_color':
                case 'error_color':
                    $sanitized[$key] = sanitize_hex_color($value);
                    break;
                    
                case 'notification_email':
                    $sanitized[$key] = sanitize_email($value);
                    break;
                    
                case 'webhook_url':
                    $sanitized[$key] = esc_url_raw($value);
                    break;
                    
                case 'default_questions_per_quiz':
                case 'default_time_limit':
                case 'default_pass_score':
                case 'max_retakes':
                case 'max_attempts_per_hour':
                case 'data_retention_days':
                case 'api_rate_limit':
                case 'border_radius':
                case 'cache_duration':
                    $sanitized[$key] = absint($value);
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
                case 'enable_export':
                case 'enable_rate_limiting':
                case 'enable_captcha':
                case 'honeypot_protection':
                case 'admin_email_notifications':
                case 'participant_email_notifications':
                case 'enable_rest_api':
                case 'enable_animations':
                case 'debug_mode':
                case 'cache_enabled':
                case 'cleanup_enabled':
                    $sanitized[$key] = (bool) $value;
                    break;
                    
                case 'webhook_events':
                    $sanitized[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : array();
                    break;
                    
                default:
                    $sanitized[$key] = sanitize_text_field($value);
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
    static $settings_instance = null;
    
    if ($settings_instance === null) {
        $settings_instance = new Vefify_Form_Settings();
    }
    
    return $settings_instance->get_setting($key, $default);
}