<?php
/**
 * FIXED: Settings Integration with Main Menu
 * File: modules/settings/class-form-settings.php
 * 
 * Integrates with the existing 'vefify-settings' menu from main plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Form_Settings {
    
    private $option_group = 'vefify_quiz_settings';
    private $option_name = 'vefify_quiz_options';
    private $settings = array();
    
    public function __construct() {
        // Hook into WordPress admin
        add_action('admin_init', array($this, 'init_settings'));
        
        // Hook into your EXISTING settings page display
        add_action('admin_head', array($this, 'check_settings_page'));
        
        // Load current settings
        $this->load_settings();
    }
    
    /**
     * Check if we're on the settings page and enhance it
     */
    public function check_settings_page() {
        $screen = get_current_screen();
        
        // Check if we're on the main plugin's settings page
        if ($screen && $screen->id === 'vefify-quiz_page_vefify-settings') {
            // Add our enhanced settings to the existing page
            add_action('admin_footer', array($this, 'inject_enhanced_settings'));
        }
    }
    
    /**
     * Inject enhanced settings into existing settings page
     */
    public function inject_enhanced_settings() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Add enhanced settings section to existing page
            const enhancedSettingsHTML = `
                <div style="background: #fff; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #da020e;">
                    <h2>🇻🇳 Enhanced Vietnam Settings</h2>
                    <div class="notice notice-success" style="margin: 15px 0;">
                        <p><strong>✅ Enhanced Settings Module Active</strong></p>
                        <p>Vietnam-specific quiz settings are now available!</p>
                    </div>
                    
                    <form method="post" action="options.php" id="enhanced-settings-form">
                        <?php settings_fields($this->option_group); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Vietnamese Phone Validation</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="<?php echo $this->option_name; ?>[vietnam_phone_validation]" value="1" <?php checked(1, $this->get_setting('vietnam_phone_validation', true)); ?> />
                                        Enable Vietnamese phone number validation (0901234567 format)
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Province Requirement</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="<?php echo $this->option_name; ?>[province_required]" value="1" <?php checked(1, $this->get_setting('province_required', true)); ?> />
                                        Require participants to select their province
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Pharmacy Code</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="<?php echo $this->option_name; ?>[pharmacy_code_required]" value="1" <?php checked(1, $this->get_setting('pharmacy_code_required', false)); ?> />
                                        Require pharmacy code field
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Default Questions per Quiz</th>
                                <td>
                                    <input type="number" name="<?php echo $this->option_name; ?>[default_questions_per_quiz]" value="<?php echo esc_attr($this->get_setting('default_questions_per_quiz', 5)); ?>" min="1" max="20" class="small-text" />
                                    <p class="description">Number of questions to show in each quiz</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Quiz Time Limit</th>
                                <td>
                                    <input type="number" name="<?php echo $this->option_name; ?>[default_time_limit]" value="<?php echo esc_attr($this->get_setting('default_time_limit', 600)); ?>" min="60" max="1800" class="small-text" />
                                    <p class="description">Time limit in seconds (600 = 10 minutes)</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="submit" class="button button-primary" value="Save Enhanced Settings" />
                        </p>
                    </form>
                    
                    <div style="margin-top: 30px; padding: 15px; background: #f0f8ff; border-radius: 4px;">
                        <h3>📋 Quick Reference</h3>
                        <p><strong>Shortcode:</strong> <code style="background: #333; color: #0f0; padding: 4px 8px; border-radius: 3px;">[vefify_quiz campaign_id="1"]</code></p>
                        <p><strong>Debug URL:</strong> Add <code>?debug=1</code> to your quiz page to verify correct shortcode is loading</p>
                        
                        <h3>📊 Current Status</h3>
                        <ul>
                            <li>✅ Vietnam Provinces: <?php echo count(Vefify_Quiz_Utilities::get_vietnam_provinces()); ?> provinces loaded</li>
                            <li>✅ Phone Validation: <?php echo $this->get_setting('vietnam_phone_validation', true) ? 'Active' : 'Disabled'; ?></li>
                            <li>✅ Province Required: <?php echo $this->get_setting('province_required', true) ? 'Yes' : 'No'; ?></li>
                            <li>✅ Default Questions: <?php echo $this->get_setting('default_questions_per_quiz', 5); ?> questions</li>
                        </ul>
                    </div>
                </div>
            `;
            
            // Append to the existing settings page
            $('.wrap').append(enhancedSettingsHTML);
        });
        </script>
        <?php
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
     * Get default settings (Vietnam focused)
     */
    private function get_default_settings() {
        return array(
            'vietnam_phone_validation' => true,
            'province_required' => true,
            'pharmacy_code_required' => false,
            'default_questions_per_quiz' => 5,
            'default_time_limit' => 600,
            'default_pass_score' => 3,
            'primary_color' => '#da020e',
            'secondary_color' => '#ffcd00'
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            array('sanitize_callback' => array($this, 'sanitize_settings'))
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        foreach ($input as $key => $value) {
            switch ($key) {
                case 'default_questions_per_quiz':
                case 'default_time_limit':
                case 'default_pass_score':
                    $sanitized[$key] = absint($value);
                    break;
                    
                case 'vietnam_phone_validation':
                case 'province_required':
                case 'pharmacy_code_required':
                    $sanitized[$key] = (bool) $value;
                    break;
                    
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get setting value
     */
    public function get_setting($key, $default = null) {
        return $this->settings[$key] ?? $default;
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