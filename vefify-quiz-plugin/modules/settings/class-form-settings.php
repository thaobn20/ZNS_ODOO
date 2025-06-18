<?php
/**
 * Form Settings Page
 * File: modules/settings/class-form-settings.php
 * 
 * Centralized settings for frontend form configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Form_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'vefify-quiz',
            'Form Settings',
            '⚙️ Form Settings',
            'manage_options',
            'vefify-form-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('vefify_form_settings', 'vefify_form_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        // Form Configuration Section
        add_settings_section(
            'vefify_form_config',
            'Form Configuration',
            array($this, 'form_config_section_callback'),
            'vefify_form_settings'
        );
        
        // Form Fields Settings
        add_settings_field(
            'show_pharmacy_code',
            'Show Pharmacy Code Field',
            array($this, 'checkbox_field_callback'),
            'vefify_form_settings',
            'vefify_form_config',
            array(
                'name' => 'show_pharmacy_code',
                'description' => 'Display pharmacy code field in registration form'
            )
        );
        
        add_settings_field(
            'pharmacy_code_required',
            'Pharmacy Code Required',
            array($this, 'checkbox_field_callback'),
            'vefify_form_settings',
            'vefify_form_config',
            array(
                'name' => 'pharmacy_code_required',
                'description' => 'Make pharmacy code field required'
            )
        );
        
        add_settings_field(
            'show_email',
            'Show Email Field',
            array($this, 'checkbox_field_callback'),
            'vefify_form_settings',
            'vefify_form_config',
            array(
                'name' => 'show_email',
                'description' => 'Display email field in registration form'
            )
        );
        
        add_settings_field(
            'email_required',
            'Email Required',
            array($this, 'checkbox_field_callback'),
            'vefify_form_settings',
            'vefify_form_config',
            array(
                'name' => 'email_required',
                'description' => 'Make email field required'
            )
        );
        
        add_settings_field(
            'show_terms',
            'Show Terms & Conditions',
            array($this, 'checkbox_field_callback'),
            'vefify_form_settings',
            'vefify_form_config',
            array(
                'name' => 'show_terms',
                'description' => 'Display terms and conditions checkbox'
            )
        );
        
        add_settings_field(
            'terms_url',
            'Terms & Conditions URL',
            array($this, 'text_field_callback'),
            'vefify_form_settings',
            'vefify_form_config',
            array(
                'name' => 'terms_url',
                'description' => 'URL to terms and conditions page'
            )
        );
        
        // Validation Settings Section
        add_settings_section(
            'vefify_validation_config',
            'Validation Settings',
            array($this, 'validation_config_section_callback'),
            'vefify_form_settings'
        );
        
        add_settings_field(
            'phone_validation_strict',
            'Strict Phone Validation',
            array($this, 'checkbox_field_callback'),
            'vefify_form_settings',
            'vefify_validation_config',
            array(
                'name' => 'phone_validation_strict',
                'description' => 'Use strict Vietnamese phone number validation'
            )
        );
        
        add_settings_field(
            'allow_duplicate_emails',
            'Allow Duplicate Emails',
            array($this, 'checkbox_field_callback'),
            'vefify_form_settings',
            'vefify_validation_config',
            array(
                'name' => 'allow_duplicate_emails',
                'description' => 'Allow same email to participate in multiple campaigns'
            )
        );
        
        // UI/UX Settings Section
        add_settings_section(
            'vefify_ui_config',
            'UI/UX Settings',
            array($this, 'ui_config_section_callback'),
            'vefify_form_settings'
        );
        
        add_settings_field(
            'form_theme',
            'Form Theme',
            array($this, 'select_field_callback'),
            'vefify_form_settings',
            'vefify_ui_config',
            array(
                'name' => 'form_theme',
                'options' => array(
                    'default' => 'Default',
                    'modern' => 'Modern',
                    'minimal' => 'Minimal',
                    'colorful' => 'Colorful'
                ),
                'description' => 'Choose the visual theme for forms'
            )
        );
        
        add_settings_field(
            'show_progress_bar',
            'Show Progress Bar',
            array($this, 'checkbox_field_callback'),
            'vefify_form_settings',
            'vefify_ui_config',
            array(
                'name' => 'show_progress_bar',
                'description' => 'Display progress bar during quiz'
            )
        );
        
        add_settings_field(
            'enable_timer',
            'Enable Quiz Timer',
            array($this, 'checkbox_field_callback'),
            'vefify_form_settings',
            'vefify_ui_config',
            array(
                'name' => 'enable_timer',
                'description' => 'Show countdown timer during quiz'
            )
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>🎮 Frontend Form Settings</h1>
            
            <div class="notice notice-info">
                <p><strong>Form Configuration:</strong> These settings control how the frontend quiz registration form appears and behaves for participants.</p>
            </div>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('vefify_form_settings');
                do_settings_sections('vefify_form_settings');
                submit_button('Save Settings');
                ?>
            </form>
            
            <!-- Shortcode Usage -->
            <div class="postbox" style="margin-top: 30px;">
                <h2 class="hndle"><span>📋 Shortcode Usage</span></h2>
                <div class="inside">
                    <p><strong>Basic Usage:</strong></p>
                    <code>[vefify_quiz campaign_id="1"]</code>
                    
                    <p><strong>With Options:</strong></p>
                    <code>[vefify_quiz campaign_id="1" theme="modern" show_progress="true" auto_submit="false"]</code>
                    
                    <h4>Available Parameters:</h4>
                    <ul>
                        <li><strong>campaign_id</strong> (required) - The campaign ID to display</li>
                        <li><strong>theme</strong> - Visual theme: default, modern, minimal, colorful</li>
                        <li><strong>show_progress</strong> - Show progress bar: true, false</li>
                        <li><strong>auto_submit</strong> - Auto-submit on completion: true, false</li>
                    </ul>
                </div>
            </div>
            
            <!-- Preview -->
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle"><span>👁️ Form Preview</span></h2>
                <div class="inside">
                    <div id="form-preview">
                        <p><em>Form preview will be displayed here based on current settings...</em></p>
                        <button type="button" class="button" onclick="generatePreview()">Generate Preview</button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function generatePreview() {
            // Simple preview generation
            const formSettings = <?php echo json_encode(get_option('vefify_form_settings', array())); ?>;
            const preview = document.getElementById('form-preview');
            
            let html = '<div class="form-preview-container" style="border: 1px solid #ddd; padding: 20px; background: #f9f9f9; border-radius: 8px;">';
            html += '<h3>Registration Form Preview</h3>';
            html += '<div class="form-field"><label>Name *</label><input type="text" placeholder="Full name" style="width: 100%; padding: 8px;"></div>';
            html += '<div class="form-field"><label>Phone Number *</label><input type="tel" placeholder="0xxxxxxxxx" style="width: 100%; padding: 8px;"></div>';
            html += '<div class="form-field"><label>Province *</label><select style="width: 100%; padding: 8px;"><option>Select province...</option></select></div>';
            
            if (formSettings.show_pharmacy_code) {
                const required = formSettings.pharmacy_code_required ? ' *' : '';
                html += '<div class="form-field"><label>Pharmacy Code' + required + '</label><input type="text" placeholder="Pharmacy code" style="width: 100%; padding: 8px;"></div>';
            }
            
            if (formSettings.show_email) {
                const required = formSettings.email_required ? ' *' : '';
                html += '<div class="form-field"><label>Email' + required + '</label><input type="email" placeholder="email@example.com" style="width: 100%; padding: 8px;"></div>';
            }
            
            if (formSettings.show_terms) {
                html += '<div class="form-field"><label><input type="checkbox"> I agree to terms and conditions *</label></div>';
            }
            
            html += '<button type="button" style="background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 4px;">Start Quiz</button>';
            html += '</div>';
            
            preview.innerHTML = html;
        }
        </script>
        
        <style>
        .form-field {
            margin-bottom: 15px;
        }
        .form-field label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .form-preview-container {
            max-width: 400px;
        }
        </style>
        <?php
    }
    
    /**
     * Section callbacks
     */
    public function form_config_section_callback() {
        echo '<p>Configure which fields to show in the registration form and their requirements.</p>';
    }
    
    public function validation_config_section_callback() {
        echo '<p>Set validation rules for form fields.</p>';
    }
    
    public function ui_config_section_callback() {
        echo '<p>Customize the appearance and user experience of the quiz interface.</p>';
    }
    
    /**
     * Field callbacks
     */
    public function checkbox_field_callback($args) {
        $settings = get_option('vefify_form_settings', array());
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : 0;
        
        echo '<label>';
        echo '<input type="checkbox" name="vefify_form_settings[' . $args['name'] . ']" value="1" ' . checked(1, $value, false) . '>';
        echo ' ' . $args['description'];
        echo '</label>';
    }
    
    public function text_field_callback($args) {
        $settings = get_option('vefify_form_settings', array());
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : '';
        
        echo '<input type="text" name="vefify_form_settings[' . $args['name'] . ']" value="' . esc_attr($value) . '" class="regular-text">';
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }
    
    public function select_field_callback($args) {
        $settings = get_option('vefify_form_settings', array());
        $value = isset($settings[$args['name']]) ? $settings[$args['name']] : '';
        
        echo '<select name="vefify_form_settings[' . $args['name'] . ']">';
        foreach ($args['options'] as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>';
            echo esc_html($option_label);
            echo '</option>';
        }
        echo '</select>';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Boolean fields
        $boolean_fields = array(
            'show_pharmacy_code',
            'pharmacy_code_required',
            'show_email',
            'email_required',
            'show_terms',
            'phone_validation_strict',
            'allow_duplicate_emails',
            'show_progress_bar',
            'enable_timer'
        );
        
        foreach ($boolean_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? 1 : 0;
        }
        
        // Text fields
        $sanitized['terms_url'] = isset($input['terms_url']) ? esc_url_raw($input['terms_url']) : '';
        
        // Select fields
        $valid_themes = array('default', 'modern', 'minimal', 'colorful');
        $sanitized['form_theme'] = isset($input['form_theme']) && in_array($input['form_theme'], $valid_themes) 
            ? $input['form_theme'] : 'default';
        
        return $sanitized;
    }
    
    /**
     * Get default settings
     */
    public static function get_default_settings() {
        return array(
            'show_pharmacy_code' => 1,
            'pharmacy_code_required' => 0,
            'show_email' => 1,
            'email_required' => 0,
            'show_terms' => 1,
            'terms_url' => '#',
            'phone_validation_strict' => 1,
            'allow_duplicate_emails' => 0,
            'form_theme' => 'default',
            'show_progress_bar' => 1,
            'enable_timer' => 1
        );
    }
    
    /**
     * Get current settings with defaults
     */
    public static function get_settings() {
        $defaults = self::get_default_settings();
        $settings = get_option('vefify_form_settings', array());
        
        return wp_parse_args($settings, $defaults);
    }
}

// Initialize settings
Vefify_Form_Settings::get_instance();