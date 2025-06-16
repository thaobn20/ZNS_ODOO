<?php
/**
 * Setting Manager Class
 * File: modules/settings/class-setting-manager.php
 */
class Vefify_Setting_Manager {
    
    private $model;
    
    public function __construct() {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/settings/class-setting-model.php';
        $this->model = new Vefify_Setting_Model();
    }
    
    public function display_settings_page($active_tab = 'general') {
        $settings = $this->model->get_settings();
        $tabs = $this->get_settings_tabs();
        
        ?>
        <div class="wrap">
            <h1>⚙️ Vefify Quiz Settings</h1>
            
            <!-- Settings Tabs -->
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_key => $tab_label): ?>
                    <a href="<?php echo admin_url('admin.php?page=vefify-settings&tab=' . $tab_key); ?>" 
                       class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo $tab_label; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <!-- Settings Form -->
            <form method="post" action="options.php" id="vefify-settings-form">
                <?php
                $settings_group = 'vefify_' . $active_tab . '_settings';
                settings_fields($settings_group);
                do_settings_sections($settings_group);
                ?>
                
                <div class="settings-content">
                    <?php $this->display_tab_content($active_tab, $settings[$settings_group] ?? array()); ?>
                </div>
                
                <p class="submit">
                    <?php submit_button('Save Settings', 'primary', 'submit', false); ?>
                    <button type="button" id="reset-settings" class="button button-secondary">Reset to Defaults</button>
                    <button type="button" id="export-settings" class="button">Export Settings</button>
                    <button type="button" id="import-settings" class="button">Import Settings</button>
                </p>
            </form>
        </div>
        
        <style>
        .settings-content { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .settings-section { margin-bottom: 30px; }
        .settings-section h3 { margin: 0 0 15px; color: #0073aa; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .form-table th { width: 200px; }
        .setting-description { font-size: 13px; color: #666; margin-top: 5px; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Reset settings confirmation
            $('#reset-settings').on('click', function() {
                if (confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'vefify_reset_settings',
                            nonce: '<?php echo wp_create_nonce('vefify_settings_ajax'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + response.data);
                            }
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    private function get_settings_tabs() {
        return array(
            'general' => '🏠 General',
            'appearance' => '🎨 Appearance',
            'notification' => '📧 Notifications',
            'integration' => '🔗 Integrations',
            'security' => '🔒 Security',
            'advanced' => '⚡ Advanced'
        );
    }
    
    private function display_tab_content($tab, $settings) {
        switch ($tab) {
            case 'general':
                $this->display_general_settings($settings);
                break;
            case 'appearance':
                $this->display_appearance_settings($settings);
                break;
            case 'notification':
                $this->display_notification_settings($settings);
                break;
            case 'integration':
                $this->display_integration_settings($settings);
                break;
            case 'security':
                $this->display_security_settings($settings);
                break;
            case 'advanced':
                $this->display_advanced_settings($settings);
                break;
        }
    }
    
    private function display_general_settings($settings) {
        ?>
        <div class="settings-section">
            <h3>🏠 General Configuration</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Site Title</th>
                    <td>
                        <input type="text" name="vefify_general_settings[site_title]" 
                               value="<?php echo esc_attr($settings['site_title'] ?? ''); ?>" class="regular-text">
                        <p class="setting-description">The title displayed on quiz pages</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Participant Options</th>
                    <td>
                        <label>
                            <input type="checkbox" name="vefify_general_settings[enable_guest_participants]" value="1" 
                                   <?php checked($settings['enable_guest_participants'] ?? false); ?>>
                            Allow guest participants (no registration required)
                        </label><br>
                        <label>
                            <input type="checkbox" name="vefify_general_settings[require_email]" value="1" 
                                   <?php checked($settings['require_email'] ?? false); ?>>
                            Require email address
                        </label><br>
                        <label>
                            <input type="checkbox" name="vefify_general_settings[enable_social_sharing]" value="1" 
                                   <?php checked($settings['enable_social_sharing'] ?? false); ?>>
                            Enable social sharing buttons
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Default Quiz Duration</th>
                    <td>
                        <input type="number" name="vefify_general_settings[default_quiz_duration]" 
                               value="<?php echo esc_attr($settings['default_quiz_duration'] ?? 600); ?>" min="60" class="small-text">
                        <span>seconds</span>
                        <p class="setting-description">Default time limit for new campaigns</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Max Participants</th>
                    <td>
                        <input type="number" name="vefify_general_settings[max_participants_per_campaign]" 
                               value="<?php echo esc_attr($settings['max_participants_per_campaign'] ?? 1000); ?>" min="1" class="small-text">
                        <p class="setting-description">Default maximum participants per campaign</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    private function display_appearance_settings($settings) {
        ?>
        <div class="settings-section">
            <h3>🎨 Theme & Appearance</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Color Scheme</th>
                    <td>
                        <label>Primary Color: 
                            <input type="color" name="vefify_appearance_settings[primary_color]" 
                                   value="<?php echo esc_attr($settings['primary_color'] ?? '#0073aa'); ?>">
                        </label><br>
                        <label>Secondary Color: 
                            <input type="color" name="vefify_appearance_settings[secondary_color]" 
                                   value="<?php echo esc_attr($settings['secondary_color'] ?? '#46b450'); ?>">
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Logo</th>
                    <td>
                        <input type="url" name="vefify_appearance_settings[logo_url]" 
                               value="<?php echo esc_attr($settings['logo_url'] ?? ''); ?>" class="regular-text">
                        <button type="button" class="button" id="upload-logo">Upload Logo</button>
                        <p class="setting-description">URL to your logo image</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Custom CSS</th>
                    <td>
                        <textarea name="vefify_appearance_settings[custom_css]" rows="8" class="large-text code"><?php echo esc_textarea($settings['custom_css'] ?? ''); ?></textarea>
                        <p class="setting-description">Additional CSS for customization</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    private function display_notification_settings($settings) {
        ?>
        <div class="settings-section">
            <h3>📧 Email & SMS Notifications</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Email Notifications</th>
                    <td>
                        <label>
                            <input type="checkbox" name="vefify_notification_settings[email_notifications]" value="1" 
                                   <?php checked($settings['email_notifications'] ?? false); ?>>
                            Enable email notifications
                        </label><br>
                        <label>
                            <input type="checkbox" name="vefify_notification_settings[participant_welcome_email]" value="1" 
                                   <?php checked($settings['participant_welcome_email'] ?? false); ?>>
                            Send welcome email to participants
                        </label><br>
                        <label>
                            <input type="checkbox" name="vefify_notification_settings[completion_email]" value="1" 
                                   <?php checked($settings['completion_email'] ?? false); ?>>
                            Send completion confirmation email
                        </label><br>
                        <label>
                            <input type="checkbox" name="vefify_notification_settings[gift_notification_email]" value="1" 
                                   <?php checked($settings['gift_notification_email'] ?? false); ?>>
                            Send gift notification email
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Admin Notifications</th>
                    <td>
                        <label>
                            <input type="checkbox" name="vefify_notification_settings[admin_notifications]" value="1" 
                                   <?php checked($settings['admin_notifications'] ?? false); ?>>
                            Notify admins of new participants
                        </label><br>
                        <label>
                            <input type="checkbox" name="vefify_notification_settings[weekly_summary_email]" value="1" 
                                   <?php checked($settings['weekly_summary_email'] ?? false); ?>>
                            Send weekly summary reports
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    private function display_integration_settings($settings) {
        ?>
        <div class="settings-section">
            <h3>🔗 Third-Party Integrations</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Analytics</th>
                    <td>
                        <input type="text" name="vefify_integration_settings[google_analytics_id]" 
                               value="<?php echo esc_attr($settings['google_analytics_id'] ?? ''); ?>" 
                               placeholder="GA-XXXXXXXX-X" class="regular-text">
                        <p class="setting-description">Google Analytics tracking ID</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">SMS Service (Twilio)</th>
                    <td>
                        <input type="text" name="vefify_integration_settings[twilio_account_sid]" 
                               value="<?php echo esc_attr($settings['twilio_account_sid'] ?? ''); ?>" 
                               placeholder="Account SID" class="regular-text"><br>
                        <input type="password" name="vefify_integration_settings[twilio_auth_token]" 
                               value="<?php echo esc_attr($settings['twilio_auth_token'] ?? ''); ?>" 
                               placeholder="Auth Token" class="regular-text">
                        <p class="setting-description">Twilio credentials for SMS notifications</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    private function display_security_settings($settings) {
        ?>
        <div class="settings-section">
            <h3>🔒 Security & Access Control</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Rate Limiting</th>
                    <td>
                        <label>
                            <input type="checkbox" name="vefify_security_settings[enable_rate_limiting]" value="1" 
                                   <?php checked($settings['enable_rate_limiting'] ?? false); ?>>
                            Enable rate limiting
                        </label><br>
                        <input type="number" name="vefify_security_settings[max_attempts_per_ip]" 
                               value="<?php echo esc_attr($settings['max_attempts_per_ip'] ?? 10); ?>" 
                               min="1" class="small-text">
                        <span>maximum attempts per IP per hour</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Session Security</th>
                    <td>
                        <input type="number" name="vefify_security_settings[session_timeout]" 
                               value="<?php echo esc_attr($settings['session_timeout'] ?? 3600); ?>" 
                               min="300" class="small-text">
                        <span>seconds session timeout</span>
                        <p class="setting-description">How long quiz sessions remain active</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    private function display_advanced_settings($settings) {
        ?>
        <div class="settings-section">
            <h3>⚡ Advanced Configuration</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Performance</th>
                    <td>
                        <label>
                            <input type="checkbox" name="vefify_advanced_settings[cache_enabled]" value="1" 
                                   <?php checked($settings['cache_enabled'] ?? false); ?>>
                            Enable caching
                        </label><br>
                        <input type="number" name="vefify_advanced_settings[cache_duration]" 
                               value="<?php echo esc_attr($settings['cache_duration'] ?? 3600); ?>" 
                               min="300" class="small-text">
                        <span>seconds cache duration</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Maintenance</th>
                    <td>
                        <label>
                            <input type="checkbox" name="vefify_advanced_settings[database_cleanup_enabled]" value="1" 
                                   <?php checked($settings['database_cleanup_enabled'] ?? false); ?>>
                            Enable automatic database cleanup
                        </label><br>
                        <select name="vefify_advanced_settings[backup_frequency]">
                            <option value="daily" <?php selected($settings['backup_frequency'] ?? 'weekly', 'daily'); ?>>Daily</option>
                            <option value="weekly" <?php selected($settings['backup_frequency'] ?? 'weekly', 'weekly'); ?>>Weekly</option>
                            <option value="monthly" <?php selected($settings['backup_frequency'] ?? 'weekly', 'monthly'); ?>>Monthly</option>
                        </select>
                        <span>backup frequency</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Debug Mode</th>
                    <td>
                        <label>
                            <input type="checkbox" name="vefify_advanced_settings[debug_mode]" value="1" 
                                   <?php checked($settings['debug_mode'] ?? false); ?>>
                            Enable debug mode
                        </label>
                        <p class="setting-description">⚠️ Only enable for troubleshooting</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}