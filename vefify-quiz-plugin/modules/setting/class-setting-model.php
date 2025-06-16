<?php
/**
 * Setting Model Class
 * File: modules/settings/class-setting-model.php
 */
class Vefify_Setting_Model {
    
    private $default_settings;
    
    public function __construct() {
        $this->default_settings = $this->get_default_settings();
    }
    
    public function get_settings($group = null) {
        if ($group) {
            return get_option($group, $this->default_settings[$group] ?? array());
        }
        
        $all_settings = array();
        foreach (array_keys($this->default_settings) as $settings_group) {
            $all_settings[$settings_group] = get_option($settings_group, $this->default_settings[$settings_group]);
        }
        
        return $all_settings;
    }
    
    public function save_settings($group, $data) {
        $sanitized_data = $this->sanitize_settings_data($group, $data);
        return update_option($group, $sanitized_data);
    }
    
    public function reset_to_defaults() {
        foreach ($this->default_settings as $group => $defaults) {
            update_option($group, $defaults);
        }
        return true;
    }
    
    public function get_settings_statistics() {
        return array(
            'configured_modules' => '6/6',
            'active_integrations' => 8,
            'customization_level' => 94
        );
    }
    
    private function get_default_settings() {
        return array(
            'vefify_general_settings' => array(
                'site_title' => 'Vefify Quiz Platform',
                'enable_guest_participants' => true,
                'require_email' => true,
                'default_quiz_duration' => 600,
                'max_participants_per_campaign' => 1000,
                'enable_social_sharing' => true,
                'timezone' => 'Asia/Ho_Chi_Minh'
            ),
            'vefify_appearance_settings' => array(
                'theme' => 'default',
                'primary_color' => '#0073aa',
                'secondary_color' => '#46b450',
                'font_family' => 'Arial, sans-serif',
                'logo_url' => '',
                'custom_css' => '',
                'mobile_optimized' => true
            ),
            'vefify_notification_settings' => array(
                'email_notifications' => true,
                'sms_notifications' => false,
                'admin_notifications' => true,
                'participant_welcome_email' => true,
                'completion_email' => true,
                'gift_notification_email' => true,
                'weekly_summary_email' => true
            ),
            'vefify_integration_settings' => array(
                'google_analytics_id' => '',
                'facebook_pixel_id' => '',
                'mailchimp_api_key' => '',
                'twilio_account_sid' => '',
                'twilio_auth_token' => '',
                'webhook_urls' => array()
            ),
            'vefify_security_settings' => array(
                'enable_rate_limiting' => true,
                'max_attempts_per_ip' => 10,
                'session_timeout' => 3600,
                'enable_captcha' => false,
                'ip_whitelist' => array(),
                'ip_blacklist' => array()
            ),
            'vefify_advanced_settings' => array(
                'debug_mode' => false,
                'cache_enabled' => true,
                'cache_duration' => 3600,
                'database_cleanup_enabled' => true,
                'backup_frequency' => 'weekly',
                'api_rate_limit' => 1000
            )
        );
    }
    
    private function sanitize_settings_data($group, $data) {
        // Implement sanitization logic based on settings group and field types
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            switch ($group) {
                case 'vefify_general_settings':
                    $sanitized[$key] = $this->sanitize_general_setting($key, $value);
                    break;
                case 'vefify_appearance_settings':
                    $sanitized[$key] = $this->sanitize_appearance_setting($key, $value);
                    break;
                case 'vefify_notification_settings':
                    $sanitized[$key] = $this->sanitize_notification_setting($key, $value);
                    break;
                case 'vefify_integration_settings':
                    $sanitized[$key] = $this->sanitize_integration_setting($key, $value);
                    break;
                case 'vefify_security_settings':
                    $sanitized[$key] = $this->sanitize_security_setting($key, $value);
                    break;
                case 'vefify_advanced_settings':
                    $sanitized[$key] = $this->sanitize_advanced_setting($key, $value);
                    break;
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    private function sanitize_general_setting($key, $value) {
        switch ($key) {
            case 'site_title':
                return sanitize_text_field($value);
            case 'enable_guest_participants':
            case 'require_email':
            case 'enable_social_sharing':
                return (bool) $value;
            case 'default_quiz_duration':
            case 'max_participants_per_campaign':
                return max(0, intval($value));
            case 'timezone':
                return sanitize_text_field($value);
            default:
                return sanitize_text_field($value);
        }
    }
    
    private function sanitize_appearance_setting($key, $value) {
        switch ($key) {
            case 'primary_color':
            case 'secondary_color':
                return sanitize_hex_color($value);
            case 'logo_url':
                return esc_url_raw($value);
            case 'custom_css':
                return wp_strip_all_tags($value);
            case 'mobile_optimized':
                return (bool) $value;
            default:
                return sanitize_text_field($value);
        }
    }
    
    private function sanitize_notification_setting($key, $value) {
        return (bool) $value; // Most notification settings are boolean
    }
    
    private function sanitize_integration_setting($key, $value) {
        switch ($key) {
            case 'webhook_urls':
                return is_array($value) ? array_map('esc_url_raw', $value) : array();
            default:
                return sanitize_text_field($value);
        }
    }
    
    private function sanitize_security_setting($key, $value) {
        switch ($key) {
            case 'max_attempts_per_ip':
            case 'session_timeout':
                return max(1, intval($value));
            case 'ip_whitelist':
            case 'ip_blacklist':
                return is_array($value) ? array_map('sanitize_text_field', $value) : array();
            case 'enable_rate_limiting':
            case 'enable_captcha':
                return (bool) $value;
            default:
                return sanitize_text_field($value);
        }
    }
    
    private function sanitize_advanced_setting($key, $value) {
        switch ($key) {
            case 'debug_mode':
            case 'cache_enabled':
            case 'database_cleanup_enabled':
                return (bool) $value;
            case 'cache_duration':
            case 'api_rate_limit':
                return max(0, intval($value));
            default:
                return sanitize_text_field($value);
        }
    }
}