<?php
/**
 * Admin Integration for Frontend Quiz
 * File: modules/admin/class-admin-frontend-integration.php
 * 
 * Integrates frontend quiz with existing admin modules
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Admin_Frontend_Integration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Add frontend options to campaign form
        add_action('vefify_campaign_form_after_basic', array($this, 'add_frontend_settings_to_campaign'));
        add_action('vefify_save_campaign', array($this, 'save_campaign_frontend_settings'), 10, 2);
        
        // Add shortcode generator to campaign list
        add_filter('vefify_campaign_list_actions', array($this, 'add_shortcode_action'), 10, 2);
        
        // Add frontend analytics to campaign details
        add_action('vefify_campaign_analytics_after_basic', array($this, 'add_frontend_analytics'));
        
        // Add frontend participant tracking
        add_filter('vefify_participant_list_columns', array($this, 'add_frontend_columns'));
        add_action('vefify_participant_list_column_content', array($this, 'render_frontend_column_content'), 10, 3);
        
        // Add admin notices for frontend setup
        add_action('admin_notices', array($this, 'show_setup_notices'));
        
        // Add quick setup wizard
        add_action('wp_ajax_vefify_quick_setup_wizard', array($this, 'ajax_quick_setup_wizard'));
    }
    
    /**
     * Add frontend settings to campaign form
     */
    public function add_frontend_settings_to_campaign($campaign) {
        $campaign_id = $campaign['id'] ?? 0;
        $frontend_settings = get_post_meta($campaign_id, '_vefify_frontend_settings', true) ?: array();
        
        $defaults = array(
            'enable_frontend' => 1,
            'theme' => 'default',
            'show_progress' => 1,
            'show_timer' => 1,
            'allow_navigation' => 1,
            'auto_submit' => 0,
            'show_leaderboard' => 1,
            'enable_certificates' => 1,
            'enable_sharing' => 1,
            'custom_css' => '',
            'success_message' => 'Congratulations on completing the quiz!',
            'redirect_url' => ''
        );
        
        $settings = wp_parse_args($frontend_settings, $defaults);
        ?>
        
        <div class="postbox">
            <h2 class="hndle"><span>🎮 Frontend Quiz Settings</span></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_frontend">Enable Frontend Quiz</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="enable_frontend" name="frontend_settings[enable_frontend]" value="1" <?php checked($settings['enable_frontend'], 1); ?>>
                                Allow participants to take this quiz via frontend shortcode
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="quiz_theme">Theme</label>
                        </th>
                        <td>
                            <select id="quiz_theme" name="frontend_settings[theme]">
                                <option value="default" <?php selected($settings['theme'], 'default'); ?>>Default</option>
                                <option value="modern" <?php selected($settings['theme'], 'modern'); ?>>Modern</option>
                                <option value="minimal" <?php selected($settings['theme'], 'minimal'); ?>>Minimal</option>
                                <option value="colorful" <?php selected($settings['theme'], 'colorful'); ?>>Colorful</option>
                            </select>
                            <p class="description">Choose the visual theme for this campaign's quiz interface.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Quiz Features</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="frontend_settings[show_progress]" value="1" <?php checked($settings['show_progress'], 1); ?>>
                                    Show progress bar
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="frontend_settings[show_timer]" value="1" <?php checked($settings['show_timer'], 1); ?>>
                                    Show countdown timer
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="frontend_settings[allow_navigation]" value="1" <?php checked($settings['allow_navigation'], 1); ?>>
                                    Allow question navigation (next/previous)
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="frontend_settings[auto_submit]" value="1" <?php checked($settings['auto_submit'], 1); ?>>
                                    Auto-submit when time expires
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Results Features</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="frontend_settings[show_leaderboard]" value="1" <?php checked($settings['show_leaderboard'], 1); ?>>
                                    Show leaderboard on results page
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="frontend_settings[enable_certificates]" value="1" <?php checked($settings['enable_certificates'], 1); ?>>
                                    Enable certificate generation
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="frontend_settings[enable_sharing]" value="1" <?php checked($settings['enable_sharing'], 1); ?>>
                                    Enable social sharing of results
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="success_message">Success Message</label>
                        </th>
                        <td>
                            <textarea id="success_message" name="frontend_settings[success_message]" rows="3" class="large-text"><?php echo esc_textarea($settings['success_message']); ?></textarea>
                            <p class="description">Message shown to participants after completing the quiz.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="redirect_url">Redirect URL (Optional)</label>
                        </th>
                        <td>
                            <input type="url" id="redirect_url" name="frontend_settings[redirect_url]" value="<?php echo esc_url($settings['redirect_url']); ?>" class="regular-text">
                            <p class="description">Redirect participants to this URL after quiz completion.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="custom_css">Custom CSS</label>
                        </th>
                        <td>
                            <textarea id="custom_css" name="frontend_settings[custom_css]" rows="5" class="large-text code"><?php echo esc_textarea($settings['custom_css']); ?></textarea>
                            <p class="description">Add custom CSS to style this campaign's quiz interface.</p>
                        </td>
                    </tr>
                </table>
                
                <?php if ($campaign_id): ?>
                <div class="shortcode-generator" style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-top: 20px;">
                    <h4>🔗 Shortcode for this Campaign</h4>
                    <input type="text" readonly onclick="this.select()" value="[vefify_quiz campaign_id=&quot;<?php echo $campaign_id; ?>&quot;]" class="large-text">
                    <p class="description">Copy and paste this shortcode into any page or post to display this quiz.</p>
                    
                    <h5>Advanced Shortcode Options:</h5>
                    <code>[vefify_quiz campaign_id="<?php echo $campaign_id; ?>" theme="<?php echo $settings['theme']; ?>" show_progress="<?php echo $settings['show_progress'] ? 'true' : 'false'; ?>"]</code>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        // Toggle frontend settings based on enable checkbox
        jQuery(document).ready(function($) {
            function toggleFrontendSettings() {
                const enabled = $('#enable_frontend').is(':checked');
                $('#enable_frontend').closest('tr').siblings().toggle(enabled);
            }
            
            $('#enable_frontend').change(toggleFrontendSettings);
            toggleFrontendSettings();
        });
        </script>
        <?php
    }
    
    /**
     * Save campaign frontend settings
     */
    public function save_campaign_frontend_settings($campaign_id, $campaign_data) {
        if (isset($_POST['frontend_settings'])) {
            $frontend_settings = $_POST['frontend_settings'];
            
            // Sanitize settings
            $sanitized_settings = array(
                'enable_frontend' => isset($frontend_settings['enable_frontend']) ? 1 : 0,
                'theme' => sanitize_text_field($frontend_settings['theme'] ?? 'default'),
                'show_progress' => isset($frontend_settings['show_progress']) ? 1 : 0,
                'show_timer' => isset($frontend_settings['show_timer']) ? 1 : 0,
                'allow_navigation' => isset($frontend_settings['allow_navigation']) ? 1 : 0,
                'auto_submit' => isset($frontend_settings['auto_submit']) ? 1 : 0,
                'show_leaderboard' => isset($frontend_settings['show_leaderboard']) ? 1 : 0,
                'enable_certificates' => isset($frontend_settings['enable_certificates']) ? 1 : 0,
                'enable_sharing' => isset($frontend_settings['enable_sharing']) ? 1 : 0,
                'success_message' => sanitize_textarea_field($frontend_settings['success_message'] ?? ''),
                'redirect_url' => esc_url_raw($frontend_settings['redirect_url'] ?? ''),
                'custom_css' => sanitize_textarea_field($frontend_settings['custom_css'] ?? '')
            );
            
            update_post_meta($campaign_id, '_vefify_frontend_settings', $sanitized_settings);
        }
    }
    
    /**
     * Add shortcode action to campaign list
     */
    public function add_shortcode_action($actions, $campaign) {
        $frontend_settings = get_post_meta($campaign->id, '_vefify_frontend_settings', true);
        
        if ($frontend_settings['enable_frontend'] ?? 1) {
            $actions['shortcode'] = sprintf(
                '<a href="#" class="copy-shortcode" data-shortcode="[vefify_quiz campaign_id=&quot;%d&quot;]" title="Copy Shortcode">🔗 Shortcode</a>',
                $campaign->id
            );
            
            $actions['preview'] = sprintf(
                '<a href="%s" target="_blank" title="Preview Frontend">👁️ Preview</a>',
                admin_url('admin.php?page=vefify-frontend-preview&campaign_id=' . $campaign->id)
            );
        }
        
        return $actions;
    }
    
    /**
     * Add frontend analytics to campaign details
     */
    public function add_frontend_analytics($campaign_id) {
        global $wpdb;
        
        // Get frontend-specific analytics
        $frontend_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_sessions,
                COUNT(CASE WHEN quiz_status = 'started' THEN 1 END) as started_sessions,
                COUNT(CASE WHEN quiz_status = 'in_progress' THEN 1 END) as in_progress_sessions,
                COUNT(CASE WHEN quiz_status = 'completed' THEN 1 END) as completed_sessions,
                COUNT(CASE WHEN quiz_status = 'abandoned' THEN 1 END) as abandoned_sessions,
                AVG(CASE WHEN quiz_status = 'completed' AND completion_time IS NOT NULL THEN completion_time END) as avg_completion_time
            FROM {$wpdb->prefix}vefify_participants
            WHERE campaign_id = %d",
            $campaign_id
        ), ARRAY_A);
        
        $conversion_rate = $frontend_stats['total_sessions'] > 0 ? 
            round(($frontend_stats['completed_sessions'] / $frontend_stats['total_sessions']) * 100, 1) : 0;
        
        ?>
        <div class="postbox">
            <h2 class="hndle"><span>🎮 Frontend Analytics</span></h2>
            <div class="inside">
                <div class="frontend-analytics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    
                    <div class="analytics-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                        <h3 style="margin: 0; color: #0073aa; font-size: 24px;"><?php echo number_format($frontend_stats['total_sessions']); ?></h3>
                        <p style="margin: 5px 0 0; color: #666;">Total Sessions</p>
                    </div>
                    
                    <div class="analytics-card" style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
                        <h3 style="margin: 0; color: #1976d2; font-size: 24px;"><?php echo number_format($frontend_stats['completed_sessions']); ?></h3>
                        <p style="margin: 5px 0 0; color: #666;">Completed</p>
                    </div>
                    
                    <div class="analytics-card" style="background: #f3e5f5; padding: 15px; border-radius: 8px; text-align: center;">
                        <h3 style="margin: 0; color: #7b1fa2; font-size: 24px;"><?php echo $conversion_rate; ?>%</h3>
                        <p style="margin: 5px 0 0; color: #666;">Conversion Rate</p>
                    </div>
                    
                    <div class="analytics-card" style="background: #e8f5e8; padding: 15px; border-radius: 8px; text-align: center;">
                        <h3 style="margin: 0; color: #2e7d32; font-size: 24px;">
                            <?php echo $frontend_stats['avg_completion_time'] ? 
                                Vefify_Quiz_Utilities::seconds_to_time($frontend_stats['avg_completion_time']) : 'N/A'; ?>
                        </h3>
                        <p style="margin: 5px 0 0; color: #666;">Avg. Time</p>
                    </div>
                    
                </div>
                
                <div class="session-breakdown" style="margin-top: 20px;">
                    <h4>Session Breakdown</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 5px 0; border-bottom: 1px solid #eee;">
                            <span style="color: #0073aa;">●</span> Started: <?php echo number_format($frontend_stats['started_sessions']); ?>
                        </li>
                        <li style="padding: 5px 0; border-bottom: 1px solid #eee;">
                            <span style="color: #ff9800;">●</span> In Progress: <?php echo number_format($frontend_stats['in_progress_sessions']); ?>
                        </li>
                        <li style="padding: 5px 0; border-bottom: 1px solid #eee;">
                            <span style="color: #4caf50;">●</span> Completed: <?php echo number_format($frontend_stats['completed_sessions']); ?>
                        </li>
                        <li style="padding: 5px 0;">
                            <span style="color: #f44336;">●</span> Abandoned: <?php echo number_format($frontend_stats['abandoned_sessions']); ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <script>
        // Add copy shortcode functionality
        jQuery(document).ready(function($) {
            $('.copy-shortcode').click(function(e) {
                e.preventDefault();
                const shortcode = $(this).data('shortcode');
                navigator.clipboard.writeText(shortcode).then(() => {
                    alert('Shortcode copied to clipboard!');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add frontend columns to participant list
     */
    public function add_frontend_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            
            // Add frontend-specific columns after existing ones
            if ($key === 'status') {
                $new_columns['session_type'] = 'Session Type';
                $new_columns['device_type'] = 'Device';
                $new_columns['completion_time'] = 'Time Taken';
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render frontend column content
     */
    public function render_frontend_column_content($column, $participant, $campaign) {
        switch ($column) {
            case 'session_type':
                // Determine if this was a frontend session
                $is_frontend = !empty($participant['session_id']);
                echo $is_frontend ? '<span style="color: #0073aa;">🎮 Frontend</span>' : '<span style="color: #666;">⚙️ Admin</span>';
                break;
                
            case 'device_type':
                // Detect device type from user agent
                $user_agent = $participant['user_agent'] ?? '';
                $device = $this->detect_device_type($user_agent);
                $icons = array(
                    'mobile' => '📱',
                    'tablet' => '📱',
                    'desktop' => '💻',
                    'unknown' => '❓'
                );
                echo '<span title="' . esc_attr($user_agent) . '">' . $icons[$device] . ' ' . ucfirst($device) . '</span>';
                break;
                
            case 'completion_time':
                if ($participant['completion_time']) {
                    echo Vefify_Quiz_Utilities::seconds_to_time($participant['completion_time']);
                } else {
                    echo '—';
                }
                break;
        }
    }
    
    /**
     * Show setup notices
     */
    public function show_setup_notices() {
        $screen = get_current_screen();
        
        if (strpos($screen->id, 'vefify') === false) {
            return;
        }
        
        // Check if frontend is properly configured
        $form_settings = get_option('vefify_form_settings');
        $has_active_campaigns = $this->has_active_frontend_campaigns();
        
        if (!$form_settings) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong>Frontend Quiz Setup:</strong> 
                    Configure your form settings to enable frontend quiz functionality.
                    <a href="<?php echo admin_url('admin.php?page=vefify-form-settings'); ?>" class="button button-small">Configure Now</a>
                </p>
            </div>
            <?php
        }
        
        if (!$has_active_campaigns) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>Ready to Go Live:</strong> 
                    Your frontend quiz system is configured! Enable frontend quiz on your campaigns to start accepting participants.
                    <a href="<?php echo admin_url('admin.php?page=vefify-campaigns'); ?>" class="button button-small">Enable Frontend</a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Quick setup wizard AJAX handler
     */
    public function ajax_quick_setup_wizard() {
        check_ajax_referer('vefify_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $step = sanitize_text_field($_POST['step']);
        
        switch ($step) {
            case 'form_settings':
                $this->setup_default_form_settings();
                wp_send_json_success('Form settings configured');
                break;
                
            case 'sample_campaign':
                $campaign_id = $this->create_sample_campaign();
                wp_send_json_success(array(
                    'campaign_id' => $campaign_id,
                    'message' => 'Sample campaign created'
                ));
                break;
                
            case 'test_page':
                $page_id = $this->create_test_page($_POST['campaign_id']);
                wp_send_json_success(array(
                    'page_id' => $page_id,
                    'page_url' => get_permalink($page_id),
                    'message' => 'Test page created'
                ));
                break;
                
            default:
                wp_send_json_error('Invalid step');
        }
    }
    
    /**
     * Helper methods
     */
    private function detect_device_type($user_agent) {
        if (preg_match('/Mobile|Android|iPhone|iPad/', $user_agent)) {
            if (preg_match('/iPad/', $user_agent)) {
                return 'tablet';
            }
            return 'mobile';
        }
        
        if (preg_match('/Windows|Mac|Linux/', $user_agent)) {
            return 'desktop';
        }
        
        return 'unknown';
    }
    
    private function has_active_frontend_campaigns() {
        global $wpdb;
        
        $count = $wpdb->get_var("
            SELECT COUNT(c.id) 
            FROM {$wpdb->prefix}vefify_campaigns c
            JOIN {$wpdb->postmeta} pm ON c.id = pm.post_id
            WHERE c.is_active = 1 
            AND pm.meta_key = '_vefify_frontend_settings'
            AND pm.meta_value LIKE '%enable_frontend\";i:1%'
        ");
        
        return $count > 0;
    }
    
    private function setup_default_form_settings() {
        $default_settings = Vefify_Form_Settings::get_default_settings();
        update_option('vefify_form_settings', $default_settings);
    }
    
    private function create_sample_campaign() {
        // Create a sample campaign with questions
        // This is a simplified version - you'd implement this based on your campaign creation logic
        return 1; // Return sample campaign ID
    }
    
    private function create_test_page($campaign_id) {
        $page_data = array(
            'post_title' => 'Quiz Test Page',
            'post_content' => '[vefify_quiz campaign_id="' . intval($campaign_id) . '"]',
            'post_status' => 'publish',
            'post_type' => 'page'
        );
        
        return wp_insert_post($page_data);
    }
}

// Initialize admin integration
if (is_admin()) {
    Vefify_Admin_Frontend_Integration::get_instance();
}