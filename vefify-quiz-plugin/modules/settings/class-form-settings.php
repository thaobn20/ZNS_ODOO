<?php
/**
 * Form Settings Admin Page
 * File: modules/settings/class-form-settings.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Form_Settings {
    
    private $options_key = 'vefify_form_settings';
    
    public function __construct() {
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Add settings page to admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'vefify-quiz',
            'Form Settings',
            'Form Settings',
            'manage_options',
            'vefify-form-settings',
            array($this, 'display_settings_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('vefify_form_settings_group', $this->options_key);
        
        // Form Fields Section
        add_settings_section(
            'form_fields_section',
            'Form Fields Configuration',
            array($this, 'form_fields_section_callback'),
            'vefify_form_settings'
        );
        
        // Gift Display Section
        add_settings_section(
            'gift_display_section',
            'Gift Display Settings',
            array($this, 'gift_display_section_callback'),
            'vefify_form_settings'
        );
        
        // Form Theme Section
        add_settings_section(
            'form_theme_section',
            'Form Appearance',
            array($this, 'form_theme_section_callback'),
            'vefify_form_settings'
        );
        
        // Add individual settings
        $this->add_form_field_settings();
        $this->add_gift_display_settings();
        $this->add_form_theme_settings();
    }
    
    /**
     * Add form field settings
     */
    private function add_form_field_settings() {
        $fields = array(
            'show_pharmacy_code' => 'Show Pharmacist Code Field',
            'require_pharmacy_code' => 'Require Pharmacist Code',
            'show_email' => 'Show Email Field',
            'require_email' => 'Require Email',
            'show_district' => 'Show District Selection',
            'require_district' => 'Require District',
            'show_terms' => 'Show Terms & Conditions',
            'require_terms' => 'Require Terms Acceptance'
        );
        
        foreach ($fields as $field_key => $field_label) {
            add_settings_field(
                $field_key,
                $field_label,
                array($this, 'checkbox_field_callback'),
                'vefify_form_settings',
                'form_fields_section',
                array('field_key' => $field_key)
            );
        }
        
        // Terms URL field
        add_settings_field(
            'terms_url',
            'Terms & Conditions URL',
            array($this, 'text_field_callback'),
            'vefify_form_settings',
            'form_fields_section',
            array('field_key' => 'terms_url', 'placeholder' => 'https://yoursite.com/terms')
        );
    }
    
    /**
     * Add gift display settings
     */
    private function add_gift_display_settings() {
        $fields = array(
            'show_gift_preview' => 'Show Gift Preview in Form',
            'show_gift_value' => 'Show Gift Value',
            'show_gift_requirements' => 'Show Gift Requirements',
            'show_gift_countdown' => 'Show Gift Countdown Timer',
            'enable_gift_motivation' => 'Enable Gift Motivation Messages'
        );
        
        foreach ($fields as $field_key => $field_label) {
            add_settings_field(
                $field_key,
                $field_label,
                array($this, 'checkbox_field_callback'),
                'vefify_form_settings',
                'gift_display_section',
                array('field_key' => $field_key)
            );
        }
    }
    
    /**
     * Add form theme settings
     */
    private function add_form_theme_settings() {
        add_settings_field(
            'form_theme',
            'Form Theme',
            array($this, 'select_field_callback'),
            'vefify_form_settings',
            'form_theme_section',
            array(
                'field_key' => 'form_theme',
                'options' => array(
                    'default' => 'Default',
                    'modern' => 'Modern',
                    'minimal' => 'Minimal',
                    'colorful' => 'Colorful',
                    'pharmacy' => 'Pharmacy Theme'
                )
            )
        );
        
        add_settings_field(
            'primary_color',
            'Primary Color',
            array($this, 'color_field_callback'),
            'vefify_form_settings',
            'form_theme_section',
            array('field_key' => 'primary_color', 'default' => '#007cba')
        );
        
        add_settings_field(
            'enable_animations',
            'Enable Form Animations',
            array($this, 'checkbox_field_callback'),
            'vefify_form_settings',
            'form_theme_section',
            array('field_key' => 'enable_animations')
        );
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-admin-settings"></span>
                Quiz Form Settings
            </h1>
            
            <div class="vefify-settings-container">
                <div class="vefify-settings-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('vefify_form_settings_group');
                        do_settings_sections('vefify_form_settings');
                        submit_button('Save Form Settings');
                        ?>
                    </form>
                </div>
                
                <div class="vefify-settings-sidebar">
                    <div class="vefify-settings-box">
                        <h3>📝 Form Preview</h3>
                        <p>Use this shortcode to display the quiz form:</p>
                        <code>[vefify_quiz campaign_id="1"]</code>
                        
                        <h4>Available Parameters:</h4>
                        <ul>
                            <li><code>campaign_id</code> - Campaign ID (required)</li>
                            <li><code>theme</code> - Form theme override</li>
                            <li><code>show_gifts</code> - Show/hide gifts (true/false)</li>
                        </ul>
                    </div>
                    
                    <div class="vefify-settings-box">
                        <h3>🎁 Gift Integration</h3>
                        <p>Configure how gifts are displayed and when they're shown to participants.</p>
                        <p><strong>Tip:</strong> Enable "Gift Preview" to motivate more sign-ups!</p>
                    </div>
                    
                    <div class="vefify-settings-box">
                        <h3>📱 Mobile Optimization</h3>
                        <p>All themes are mobile-responsive. The "Modern" theme works best on mobile devices.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .vefify-settings-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        
        .vefify-settings-main {
            flex: 2;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .vefify-settings-sidebar {
            flex: 1;
        }
        
        .vefify-settings-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .vefify-settings-box h3 {
            margin-top: 0;
            color: #1e293b;
        }
        
        .vefify-settings-box code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .form-table th {
            font-weight: 600;
            color: #374151;
        }
        
        .form-table td {
            padding: 15px 10px;
        }
        
        .form-table input[type="checkbox"] {
            transform: scale(1.2);
            margin-right: 8px;
        }
        
        .form-table input[type="color"] {
            width: 80px;
            height: 40px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        </style>
        <?php
    }
    
    /**
     * Section callbacks
     */
    public function form_fields_section_callback() {
        echo '<p>Configure which fields to show in the registration form and whether they are required.</p>';
    }
    
    public function gift_display_section_callback() {
        echo '<p>Control how gifts are displayed to participants during the quiz process.</p>';
    }
    
    public function form_theme_section_callback() {
        echo '<p>Customize the appearance and styling of your quiz forms.</p>';
    }
    
    /**
     * Field callbacks
     */
    public function checkbox_field_callback($args) {
        $options = get_option($this->options_key, array());
        $field_key = $args['field_key'];
        $value = isset($options[$field_key]) ? $options[$field_key] : false;
        
        printf(
            '<input type="checkbox" id="%s" name="%s[%s]" value="1" %s>',
            $field_key,
            $this->options_key,
            $field_key,
            checked(1, $value, false)
        );
    }
    
    public function text_field_callback($args) {
        $options = get_option($this->options_key, array());
        $field_key = $args['field_key'];
        $value = isset($options[$field_key]) ? $options[$field_key] : '';
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        
        printf(
            '<input type="text" id="%s" name="%s[%s]" value="%s" placeholder="%s" class="regular-text">',
            $field_key,
            $this->options_key,
            $field_key,
            esc_attr($value),
            esc_attr($placeholder)
        );
    }
    
    public function select_field_callback($args) {
        $options = get_option($this->options_key, array());
        $field_key = $args['field_key'];
        $current_value = isset($options[$field_key]) ? $options[$field_key] : '';
        $select_options = $args['options'];
        
        printf('<select id="%s" name="%s[%s]">', $field_key, $this->options_key, $field_key);
        
        foreach ($select_options as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($current_value, $value, false),
                esc_html($label)
            );
        }
        
        echo '</select>';
    }
    
    public function color_field_callback($args) {
        $options = get_option($this->options_key, array());
        $field_key = $args['field_key'];
        $value = isset($options[$field_key]) ? $options[$field_key] : $args['default'];
        
        printf(
            '<input type="color" id="%s" name="%s[%s]" value="%s">',
            $field_key,
            $this->options_key,
            $field_key,
            esc_attr($value)
        );
    }
    
    /**
     * Get setting value
     */
    public static function get_setting($key, $default = false) {
        $options = get_option('vefify_form_settings', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    /**
     * Check if field should be shown
     */
    public static function should_show_field($field_name) {
        return self::get_setting('show_' . $field_name, true);
    }
    
    /**
     * Check if field is required
     */
    public static function is_field_required($field_name) {
        return self::get_setting('require_' . $field_name, false);
    }
}