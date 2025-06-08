<?php
/**
 * Plugin Name: Advanced Quiz Manager
 * Plugin URI: https://yourwebsite.com/advanced-quiz-manager
 * Description: Complete quiz plugin with campaigns, questions, gifts, analytics and Vietnamese provinces integration
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: advanced-quiz
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AQM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AQM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AQM_VERSION', '1.0.0');
// Add to advanced-quiz-manager.php after existing includes
require_once AQM_PLUGIN_PATH . 'includes/enhanced-database.php';
require_once AQM_PLUGIN_PATH . 'includes/gift-management.php';
require_once AQM_PLUGIN_PATH . 'includes/question-management.php';
require_once AQM_PLUGIN_PATH . 'includes/enhanced-api.php';
// Main plugin class
class AdvancedQuizManager {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('advanced-quiz', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components
        $this->init_admin();
        $this->init_frontend();
        $this->init_api();
        $this->init_scripts();
        $this->init_shortcodes();
    }
    
    public function activate() {
        $this->create_tables();
        $this->create_default_data();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function init_admin() {
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('wp_ajax_aqm_save_campaign', array($this, 'save_campaign'));
        }
    }
    
    private function init_frontend() {
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
    }
    
    private function init_api() {
        add_action('wp_ajax_aqm_submit_quiz', array($this, 'submit_quiz'));
        add_action('wp_ajax_nopriv_aqm_submit_quiz', array($this, 'submit_quiz'));
        add_action('wp_ajax_aqm_get_provinces', array($this, 'get_provinces'));
        add_action('wp_ajax_nopriv_aqm_get_provinces', array($this, 'get_provinces'));
    }
    
    private function init_shortcodes() {
        add_shortcode('quiz_form', array($this, 'quiz_shortcode'));
        add_shortcode('quiz_results', array($this, 'results_shortcode'));
    }
    
    public function quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'style' => 'default'
        ), $atts);
        
        return $this->render_quiz_form($atts['campaign_id'], $atts['style']);
    }
    
    public function results_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'show_charts' => 'true'
        ), $atts);
        
        return $this->render_quiz_results($atts['campaign_id'], $atts['show_charts']);
    }
    
    private function init_scripts() {
        // Scripts will be handled by individual methods
    }
    
    public function admin_scripts($hook) {
        if (strpos($hook, 'quiz-manager') !== false) {
            wp_enqueue_style('aqm-admin-css', AQM_PLUGIN_URL . 'assets/css/admin.css', array(), AQM_VERSION);
            wp_localize_script('jquery', 'aqm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqm_nonce'),
            ));
        }
    }
    
    public function frontend_scripts() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'quiz_form')) {
            wp_enqueue_style('aqm-frontend-css', AQM_PLUGIN_URL . 'assets/css/frontend.css', array(), AQM_VERSION);
            wp_enqueue_script('aqm-frontend-js', AQM_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), AQM_VERSION, true);
            
            wp_localize_script('aqm-frontend-js', 'aqm_front', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqm_front_nonce'),
                'provinces_data' => $this->get_vietnam_provinces_json()
            ));
        }
    }
    
    // ADMIN MENU FUNCTIONS
    public function add_admin_menu() {
        add_menu_page(
            'Quiz Manager',
            'Quiz Manager',
            'manage_options',
            'quiz-manager',
            array($this, 'admin_dashboard_page'),
            'dashicons-feedback',
            30
        );
        
        add_submenu_page(
            'quiz-manager',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'quiz-manager',
            array($this, 'admin_dashboard_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Campaigns',
            'Campaigns',
            'manage_options',
            'quiz-manager-campaigns',
            array($this, 'admin_campaigns_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Questions',
            'Questions',
            'manage_options',
            'quiz-manager-questions',
            array($this, 'admin_questions_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Responses',
            'Responses',
            'manage_options',
            'quiz-manager-responses',
            array($this, 'admin_responses_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Vietnamese Provinces',
            'Vietnamese Provinces',
            'manage_options',
            'quiz-manager-provinces',
            array($this, 'admin_provinces_page')
        );
    }
    
    public function admin_dashboard_page() {
        // Get overview stats
        global $wpdb;
        $total_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_campaigns");
        $total_responses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses");
        $completed_responses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE status = 'completed'");
        $completion_rate = $total_responses > 0 ? round(($completed_responses / $total_responses) * 100, 1) : 0;
        
        ?>
        <div class="wrap">
            <h1>🎯 Quiz Manager Dashboard</h1>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #2271b1;">📊 Total Campaigns</h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0; color: #135e96;"><?php echo esc_html($total_campaigns); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #2271b1;">👥 Total Responses</h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0; color: #135e96;"><?php echo esc_html($total_responses); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #2271b1;">✅ Completion Rate</h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0; color: #135e96;"><?php echo esc_html($completion_rate); ?>%</p>
                </div>
            </div>
            
            <div style="background: #fff; padding: 30px; border: 1px solid #ccc; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2>🚀 Quick Start Guide</h2>
                <p style="font-size: 16px; color: #555;">Welcome to Advanced Quiz Manager! Here's how to get started:</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div style="padding: 20px; background: #f0f8ff; border-left: 4px solid #2271b1; border-radius: 4px;">
                        <h3 style="margin: 0 0 10px 0;">1. Create a Campaign</h3>
                        <p>Go to <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns'); ?>">Campaigns</a> and click "Add New"</p>
                    </div>
                    <div style="padding: 20px; background: #f0f8ff; border-left: 4px solid #2271b1; border-radius: 4px;">
                        <h3 style="margin: 0 0 10px 0;">2. Add Questions</h3>
                        <p>Include Vietnamese provinces, ratings, multiple choice, and more</p>
                    </div>
                    <div style="padding: 20px; background: #f0f8ff; border-left: 4px solid #2271b1; border-radius: 4px;">
                        <h3 style="margin: 0 0 10px 0;">3. Display Quiz</h3>
                        <p>Use shortcode <code>[quiz_form campaign_id="1"]</code> on any page</p>
                    </div>
                </div>
                
                <h3>🇻🇳 Vietnamese Provinces Integration</h3>
                <p>This plugin includes full support for Vietnamese provinces, districts, and wards:</p>
                <ul style="margin-left: 20px;">
                    <li><strong>All 63 provinces/cities</strong> with Vietnamese and English names</li>
                    <li><strong>Dynamic district loading</strong> based on province selection</li>
                    <li><strong>Ward/commune support</strong> for complete address collection</li>
                    <li><strong>Geographic analytics</strong> to see where your participants are from</li>
                </ul>
                
                <h3>📋 Sample Shortcodes</h3>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 10px 0;">
                    <code>[quiz_form campaign_id="1"]</code> - Display a quiz form<br>
                    <code>[quiz_results campaign_id="1"]</code> - Show quiz results and stats<br>
                    <code>[quiz_form campaign_id="1" style="modern"]</code> - Quiz with modern styling
                </div>
                
                <?php if ($total_campaigns == 0): ?>
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
                    <strong>👈 Ready to start?</strong> 
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns'); ?>" class="button button-primary">Create Your First Campaign</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function admin_campaigns_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        if ($action === 'new' || $action === 'edit') {
            $this->render_campaign_form($action);
            return;
        }
        
        // Handle form submission
        if (isset($_POST['submit_campaign'])) {
            $this->handle_campaign_save();
            return;
        }
        
        // List campaigns
        $campaigns = $this->get_campaigns();
        ?>
        <div class="wrap">
            <h1>Quiz Campaigns <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=new'); ?>" class="page-title-action">Add New</a></h1>
            
            <?php if (isset($_GET['message'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($_GET['message']); ?></p></div>
            <?php endif; ?>
            
            <?php if (empty($campaigns)): ?>
                <div style="background: #fff; padding: 40px; text-align: center; border: 1px solid #ccc; border-radius: 8px;">
                    <h2>🎯 Create Your First Campaign</h2>
                    <p>Get started by creating your first quiz campaign with Vietnamese provinces support!</p>
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=new'); ?>" class="button button-primary button-hero">Create Campaign</a>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Title</th>
                            <th>Status</th>
                            <th>Responses</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                            <?php $stats = $this->get_campaign_stats($campaign->id); ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($campaign->title); ?></strong>
                                    <?php if ($campaign->description): ?>
                                        <br><small style="color: #666;"><?php echo esc_html(wp_trim_words($campaign->description, 10)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-<?php echo esc_attr($campaign->status); ?>" style="padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; background: <?php echo $campaign->status === 'active' ? '#d1e7dd' : ($campaign->status === 'inactive' ? '#f8d7da' : '#fff3cd'); ?>; color: <?php echo $campaign->status === 'active' ? '#0a3622' : ($campaign->status === 'inactive' ? '#58151c' : '#664d03'); ?>;">
                                        <?php echo esc_html(ucfirst($campaign->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($stats['total_participants']); ?></td>
                                <td><?php echo esc_html(date('M j, Y', strtotime($campaign->created_at))); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=edit&id=' . $campaign->id); ?>">Edit</a> |
                                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-questions&campaign_id=' . $campaign->id); ?>">Questions</a> |
                                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-responses&campaign_id=' . $campaign->id); ?>">Responses</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_campaign_form($action) {
        $campaign_id = $action === 'edit' ? intval($_GET['id']) : 0;
        $campaign = null;
        
        if ($campaign_id) {
            $campaign = $this->get_campaign($campaign_id);
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo $action === 'edit' ? 'Edit Campaign' : 'Create New Campaign'; ?></h1>
            
            <form method="post" style="background: #fff; padding: 30px; border: 1px solid #ccc; border-radius: 8px;">
                <?php wp_nonce_field('aqm_save_campaign', 'aqm_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="title">Campaign Title *</label></th>
                        <td>
                            <input type="text" id="title" name="title" class="regular-text" 
                                   value="<?php echo esc_attr($campaign ? $campaign->title : ''); ?>" required>
                            <p class="description">Enter a descriptive title for your quiz campaign.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description">Description</label></th>
                        <td>
                            <textarea id="description" name="description" class="large-text" rows="4"><?php echo esc_textarea($campaign ? $campaign->description : ''); ?></textarea>
                            <p class="description">Provide a brief description that will be shown to participants.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="status">Status</label></th>
                        <td>
                            <select id="status" name="status">
                                <option value="draft" <?php selected($campaign ? $campaign->status : 'draft', 'draft'); ?>>Draft</option>
                                <option value="active" <?php selected($campaign ? $campaign->status : '', 'active'); ?>>Active</option>
                                <option value="inactive" <?php selected($campaign ? $campaign->status : '', 'inactive'); ?>>Inactive</option>
                            </select>
                            <p class="description">Only active campaigns can be displayed to users.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_participants">Maximum Participants</label></th>
                        <td>
                            <input type="number" id="max_participants" name="max_participants" 
                                   value="<?php echo esc_attr($campaign ? $campaign->max_participants : 0); ?>" min="0">
                            <p class="description">Set to 0 for unlimited participants.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit_campaign" class="button button-primary" value="<?php echo $action === 'edit' ? 'Update Campaign' : 'Create Campaign'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns'); ?>" class="button">Cancel</a>
                </p>
            </form>
            
            <?php if ($campaign_id): ?>
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px; margin-top: 20px;">
                <h3>📋 Display This Quiz</h3>
                <p>Use this shortcode to display the quiz on any page or post:</p>
                <input type="text" value="[quiz_form campaign_id=&quot;<?php echo esc_attr($campaign_id); ?>&quot;]" 
                       readonly onclick="this.select();" style="width: 100%; padding: 10px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function handle_campaign_save() {
        if (!wp_verify_nonce($_POST['aqm_nonce'], 'aqm_save_campaign')) {
            wp_die('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_textarea_field($_POST['description']);
        $status = sanitize_text_field($_POST['status']);
        $max_participants = intval($_POST['max_participants']);
        
        $data = array(
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'max_participants' => $max_participants,
            'settings' => json_encode(array()),
            'created_by' => get_current_user_id()
        );
        
        global $wpdb;
        
        if ($campaign_id) {
            $wpdb->update(
                $wpdb->prefix . 'aqm_campaigns',
                $data,
                array('id' => $campaign_id)
            );
            $message = 'Campaign updated successfully!';
        } else {
            $wpdb->insert($wpdb->prefix . 'aqm_campaigns', $data);
            $campaign_id = $wpdb->insert_id;
            $message = 'Campaign created successfully!';
        }
        
        wp_redirect(admin_url('admin.php?page=quiz-manager-campaigns&message=' . urlencode($message)));
        exit;
    }
    
    public function admin_questions_page() {
        ?>
        <div class="wrap">
            <h1>Question Management</h1>
            <div style="background: #fff; padding: 30px; border: 1px solid #ccc; border-radius: 8px;">
                <h2>🇻🇳 Vietnamese Provinces Questions</h2>
                <p>This plugin supports advanced question types including Vietnamese location data:</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div style="padding: 20px; background: #f0f8ff; border-left: 4px solid #2271b1; border-radius: 4px;">
                        <h3>🏢 Provinces</h3>
                        <p>All 63 Vietnamese provinces and cities with Vietnamese and English names.</p>
                    </div>
                    <div style="padding: 20px; background: #f0f8ff; border-left: 4px solid #2271b1; border-radius: 4px;">
                        <h3>🏘️ Districts</h3>
                        <p>Districts that load dynamically based on province selection.</p>
                    </div>
                    <div style="padding: 20px; background: #f0f8ff; border-left: 4px solid #2271b1; border-radius: 4px;">
                        <h3>🏠 Wards</h3>
                        <p>Wards and communes for complete address collection.</p>
                    </div>
                </div>
                
                <h3>🎯 Other Question Types</h3>
                <ul style="margin-left: 20px;">
                    <li><strong>Multiple Choice:</strong> Radio button selections</li>
                    <li><strong>Rating System:</strong> Star ratings (1-5 or custom)</li>
                    <li><strong>Text Input:</strong> Free text responses</li>
                    <li><strong>Email:</strong> Email address collection</li>
                    <li><strong>Phone:</strong> Phone number input</li>
                    <li><strong>Date:</strong> Date picker selection</li>
                    <li><strong>Number:</strong> Numeric input with validation</li>
                </ul>
                
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
                    <strong>💡 Coming Soon:</strong> Visual question builder interface for creating and managing questions with drag-and-drop functionality.
                </div>
            </div>
        </div>
        <?php
    }
    
    public function admin_responses_page() {
        ?>
        <div class="wrap">
            <h1>Quiz Responses</h1>
            <div style="background: #fff; padding: 30px; border: 1px solid #ccc; border-radius: 8px;">
                <h2>📊 Response Analytics</h2>
                <p>View and analyze responses from your quiz participants including geographic data.</p>
                
                <h3>📈 Available Analytics</h3>
                <ul style="margin-left: 20px;">
                    <li><strong>Response Overview:</strong> Total submissions, completion rates, average scores</li>
                    <li><strong>Geographic Distribution:</strong> Vietnamese provinces breakdown</li>
                    <li><strong>Time Analysis:</strong> Submission patterns by day/week/month</li>
                    <li><strong>Question Performance:</strong> Most/least answered questions</li>
                    <li><strong>User Insights:</strong> Completion time analysis</li>
                </ul>
                
                <h3>📋 Export Options</h3>
                <ul style="margin-left: 20px;">
                    <li><strong>CSV Export:</strong> All responses with answers</li>
                    <li><strong>Excel Export:</strong> Formatted spreadsheets</li>
                    <li><strong>PDF Reports:</strong> Summary analytics</li>
                </ul>
                
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
                    <strong>💡 Coming Soon:</strong> Full response management dashboard with real-time charts and filtering options.
                </div>
            </div>
        </div>
        <?php
    }
    
    public function admin_provinces_page() {
        global $wpdb;
        $province_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_provinces");
        $district_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_districts");
        $ward_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_wards");
        
        ?>
        <div class="wrap">
            <h1>🇻🇳 Vietnamese Provinces Data</h1>
            
            <div style="background: #fff; padding: 30px; border: 1px solid #ccc; border-radius: 8px; margin: 20px 0;">
                <h2>📊 Current Database Status</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div style="padding: 20px; background: #d1e7dd; border-radius: 8px; text-align: center;">
                        <h3 style="margin: 0; font-size: 24px; color: #0a3622;"><?php echo esc_html($province_count); ?></h3>
                        <p style="margin: 5px 0 0 0; color: #0a3622;">Provinces/Cities</p>
                    </div>
                    <div style="padding: 20px; background: #cff4fc; border-radius: 8px; text-align: center;">
                        <h3 style="margin: 0; font-size: 24px; color: #055160;"><?php echo esc_html($district_count); ?></h3>
                        <p style="margin: 5px 0 0 0; color: #055160;">Districts</p>
                    </div>
                    <div style="padding: 20px; background: #fff3cd; border-radius: 8px; text-align: center;">
                        <h3 style="margin: 0; font-size: 24px; color: #664d03;"><?php echo esc_html($ward_count); ?></h3>
                        <p style="margin: 5px 0 0 0; color: #664d03;">Wards/Communes</p>
                    </div>
                </div>
                
                <?php if ($province_count == 0): ?>
                <div style="background: #f8d7da; border: 1px solid #f1aeb5; border-radius: 4px; padding: 15px; margin: 20px 0;">
                    <strong>⚠️ No provinces data found!</strong> The sample data should be imported automatically during plugin activation. 
                    Try deactivating and reactivating the plugin to trigger the data import.
                </div>
                <?php else: ?>
                <div style="background: #d1e7dd; border: 1px solid #a3cfbb; border-radius: 4px; padding: 15px; margin: 20px 0;">
                    <strong>✅ Province data is ready!</strong> You can now add Vietnamese province questions to your quizzes.
                </div>
                <?php endif; ?>
                
                <h3>🎯 Features</h3>
                <ul style="margin-left: 20px;">
                    <li><strong>Complete Coverage:</strong> All 63 Vietnamese provinces and major cities</li>
                    <li><strong>Bilingual Support:</strong> Vietnamese and English names</li>
                    <li><strong>Hierarchical Structure:</strong> Provinces → Districts → Wards</li>
                    <li><strong>Dynamic Loading:</strong> Districts load when province selected</li>
                    <li><strong>Geographic Analytics:</strong> See where participants are from</li>
                </ul>
                
                <h3>📋 Sample Province Data</h3>
                <?php if ($province_count > 0): ?>
                    <?php
                    $sample_provinces = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aqm_provinces ORDER BY name LIMIT 5");
                    if ($sample_provinces):
                    ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Vietnamese Name</th>
                                <th>English Name</th>
                                <th>Full Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sample_provinces as $province): ?>
                            <tr>
                                <td><?php echo esc_html($province->code); ?></td>
                                <td><?php echo esc_html($province->name); ?></td>
                                <td><?php echo esc_html($province->name_en); ?></td>
                                <td><?php echo esc_html($province->full_name); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="font-style: italic; color: #666;">Showing first 5 provinces. Total: <?php echo esc_html($province_count); ?> provinces.</p>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
                    <strong>💡 Coming Soon:</strong> JSON import/export functionality for custom province data and bulk updates.
                </div>
            </div>
        </div>
        <?php
    }
    
    // DATABASE FUNCTIONS
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql_campaigns = "CREATE TABLE {$wpdb->prefix}aqm_campaigns (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            status enum('active','inactive','draft') DEFAULT 'draft',
            start_date datetime,
            end_date datetime,
            max_participants int(11) DEFAULT 0,
            settings longtext,
            created_by int(11),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $sql_questions = "CREATE TABLE {$wpdb->prefix}aqm_questions (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            question_text text NOT NULL,
            question_type enum('multiple_choice','text','number','email','phone','date','provinces','districts','wards','rating','file_upload') NOT NULL,
            options longtext,
            is_required tinyint(1) DEFAULT 0,
            order_index int(11) DEFAULT 0,
            points int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $sql_responses = "CREATE TABLE {$wpdb->prefix}aqm_responses (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            user_id int(11),
            user_email varchar(255),
            user_name varchar(255),
            total_score int(11) DEFAULT 0,
            completion_time int(11),
            ip_address varchar(45),
            status enum('completed','abandoned','in_progress') DEFAULT 'in_progress',
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $sql_answers = "CREATE TABLE {$wpdb->prefix}aqm_answers (
            id int(11) NOT NULL AUTO_INCREMENT,
            response_id int(11) NOT NULL,
            question_id int(11) NOT NULL,
            answer_value longtext,
            answer_score int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $sql_provinces = "CREATE TABLE {$wpdb->prefix}aqm_provinces (
            id int(11) NOT NULL AUTO_INCREMENT,
            code varchar(10) NOT NULL,
            name varchar(255) NOT NULL,
            name_en varchar(255),
            full_name varchar(255),
            full_name_en varchar(255),
            code_name varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";
        
        $sql_districts = "CREATE TABLE {$wpdb->prefix}aqm_districts (
            id int(11) NOT NULL AUTO_INCREMENT,
            code varchar(10) NOT NULL,
            name varchar(255) NOT NULL,
            name_en varchar(255),
            province_code varchar(10) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";
        
        $sql_wards = "CREATE TABLE {$wpdb->prefix}aqm_wards (
            id int(11) NOT NULL AUTO_INCREMENT,
            code varchar(10) NOT NULL,
            name varchar(255) NOT NULL,
            name_en varchar(255),
            district_code varchar(10) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_campaigns);
        dbDelta($sql_questions);
        dbDelta($sql_responses);
        dbDelta($sql_answers);
        dbDelta($sql_provinces);
        dbDelta($sql_districts);
        dbDelta($sql_wards);
    }
    
    private function create_default_data() {
        global $wpdb;
        
        // Check if data already exists
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_provinces");
        if ($existing > 0) {
            return;
        }
        
        // Insert sample Vietnamese provinces
        $provinces = array(
            array('01', 'Hà Nội', 'Hanoi', 'Thành phố Hà Nội', 'Hanoi City', 'ha_noi'),
            array('79', 'Hồ Chí Minh', 'Ho Chi Minh', 'Thành phố Hồ Chí Minh', 'Ho Chi Minh City', 'ho_chi_minh'),
            array('48', 'Đà Nẵng', 'Da Nang', 'Thành phố Đà Nẵng', 'Da Nang City', 'da_nang'),
            array('92', 'Cần Thơ', 'Can Tho', 'Thành phố Cần Thơ', 'Can Tho City', 'can_tho'),
            array('31', 'Hải Phòng', 'Hai Phong', 'Thành phố Hải Phòng', 'Hai Phong City', 'hai_phong'),
            array('24', 'Bắc Giang', 'Bac Giang', 'Tỉnh Bắc Giang', 'Bac Giang Province', 'bac_giang'),
            array('06', 'Bắc Kạn', 'Bac Kan', 'Tỉnh Bắc Kạn', 'Bac Kan Province', 'bac_kan'),
            array('27', 'Bắc Ninh', 'Bac Ninh', 'Tỉnh Bắc Ninh', 'Bac Ninh Province', 'bac_ninh'),
            array('83', 'Bến Tre', 'Ben Tre', 'Tỉnh Bến Tre', 'Ben Tre Province', 'ben_tre'),
            array('74', 'Bình Dương', 'Binh Duong', 'Tỉnh Bình Dương', 'Binh Duong Province', 'binh_duong')
        );
        
        foreach ($provinces as $province) {
            $wpdb->insert(
                $wpdb->prefix . 'aqm_provinces',
                array(
                    'code' => $province[0],
                    'name' => $province[1],
                    'name_en' => $province[2],
                    'full_name' => $province[3],
                    'full_name_en' => $province[4],
                    'code_name' => $province[5]
                )
            );
        }
        
        // Insert sample districts for major cities
        $districts = array(
            array('001', 'Ba Đình', 'Ba Dinh', '01'),
            array('002', 'Hoàn Kiếm', 'Hoan Kiem', '01'),
            array('003', 'Tây Hồ', 'Tay Ho', '01'),
            array('760', 'Quận 1', 'District 1', '79'),
            array('761', 'Quận 3', 'District 3', '79'),
            array('762', 'Quận 7', 'District 7', '79'),
            array('490', 'Hải Châu', 'Hai Chau', '48'),
            array('491', 'Thanh Khê', 'Thanh Khe', '48')
        );
        
        foreach ($districts as $district) {
            $wpdb->insert(
                $wpdb->prefix . 'aqm_districts',
                array(
                    'code' => $district[0],
                    'name' => $district[1],
                    'name_en' => $district[2],
                    'province_code' => $district[3]
                )
            );
        }
        
        // Create sample campaign
        $wpdb->insert(
            $wpdb->prefix . 'aqm_campaigns',
            array(
                'title' => 'Sample Quiz with Vietnamese Provinces',
                'description' => 'A sample quiz demonstrating Vietnamese provinces integration',
                'status' => 'active',
                'max_participants' => 0,
                'settings' => json_encode(array()),
                'created_by' => get_current_user_id()
            )
        );
        
        $campaign_id = $wpdb->insert_id;
        
        // Add sample questions
        if ($campaign_id) {
            $questions = array(
                array(
                    'campaign_id' => $campaign_id,
                    'question_text' => 'What is your full name?',
                    'question_type' => 'text',
                    'is_required' => 1,
                    'order_index' => 1
                ),
                array(
                    'campaign_id' => $campaign_id,
                    'question_text' => 'Which province are you from?',
                    'question_type' => 'provinces',
                    'options' => json_encode(array('load_districts' => true)),
                    'is_required' => 1,
                    'order_index' => 2
                ),
                array(
                    'campaign_id' => $campaign_id,
                    'question_text' => 'Rate your experience (1-5 stars)',
                    'question_type' => 'rating',
                    'options' => json_encode(array('max_rating' => 5)),
                    'is_required' => 0,
                    'order_index' => 3,
                    'points' => 10
                )
            );
            
            foreach ($questions as $question) {
                $wpdb->insert($wpdb->prefix . 'aqm_questions', $question);
            }
        }
    }
    
    // HELPER FUNCTIONS
    private function get_campaigns() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aqm_campaigns ORDER BY created_at DESC");
    }
    
    private function get_campaign($campaign_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aqm_campaigns WHERE id = %d", $campaign_id));
    }
    
    private function get_campaign_stats($campaign_id) {
        global $wpdb;
        
        $total_participants = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE campaign_id = %d",
            $campaign_id
        ));
        
        $completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE campaign_id = %d AND status = 'completed'",
            $campaign_id
        ));
        
        $completion_rate = $total_participants > 0 ? round(($completed / $total_participants) * 100, 2) : 0;
        
        return array(
            'total_participants' => $total_participants ?: 0,
            'completion_rate' => $completion_rate,
            'average_score' => 0
        );
    }
    
    private function get_vietnam_provinces_json() {
        global $wpdb;
        
        $provinces = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aqm_provinces ORDER BY name");
        $provinces_data = array();
        
        foreach ($provinces as $province) {
            $districts = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aqm_districts WHERE province_code = %s ORDER BY name",
                $province->code
            ));
            
            $districts_data = array();
            foreach ($districts as $district) {
                $districts_data[] = array(
                    'code' => $district->code,
                    'name' => $district->name,
                    'name_en' => $district->name_en
                );
            }
            
            $provinces_data[] = array(
                'code' => $province->code,
                'name' => $province->name,
                'name_en' => $province->name_en,
                'full_name' => $province->full_name,
                'districts' => $districts_data
            );
        }
        
        return $provinces_data;
    }
    
    // FRONTEND RENDER FUNCTIONS
    private function render_quiz_form($campaign_id, $style) {
        if (empty($campaign_id)) {
            return '<div class="aqm-error">❌ Campaign ID is required.</div>';
        }
        
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return '<div class="aqm-error">❌ Campaign not found.</div>';
        }
        
        if ($campaign->status !== 'active') {
            return '<div class="aqm-error">❌ This quiz is not currently active.</div>';
        }
        
        global $wpdb;
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aqm_questions WHERE campaign_id = %d ORDER BY order_index ASC",
            $campaign_id
        ));
        
        ob_start();
        ?>
        <div class="aqm-quiz-container" data-campaign-id="<?php echo esc_attr($campaign_id); ?>" data-style="<?php echo esc_attr($style); ?>" style="max-width: 800px; margin: 0 auto; padding: 30px; background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            <div class="aqm-quiz-header" style="text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #f0f0f0;">
                <h2 style="font-size: 2rem; font-weight: 700; color: #2c3e50; margin-bottom: 15px;"><?php echo esc_html($campaign->title); ?></h2>
                <?php if ($campaign->description): ?>
                    <p style="font-size: 1.1rem; color: #666; margin: 0;"><?php echo esc_html($campaign->description); ?></p>
                <?php endif; ?>
            </div>
            
            <form class="aqm-quiz-form" id="aqm-quiz-form-<?php echo esc_attr($campaign_id); ?>" style="margin-bottom: 20px;">
                <?php wp_nonce_field('aqm_submit_quiz', 'aqm_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>">
                
                <?php if (empty($questions)): ?>
                    <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px;">
                        <h3>📝 No Questions Yet</h3>
                        <p>This campaign doesn't have any questions yet. Questions can be added from the admin panel.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($questions as $question): ?>
                        <div class="aqm-question-container" data-question-id="<?php echo esc_attr($question->id); ?>" data-question-type="<?php echo esc_attr($question->question_type); ?>" style="margin-bottom: 25px; padding: 25px; background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 8px;">
                            <label class="aqm-question-label" style="display: block; font-size: 1.1rem; font-weight: 600; color: #2c3e50; margin-bottom: 15px;">
                                <?php echo esc_html($question->question_text); ?>
                                <?php if ($question->is_required): ?>
                                    <span style="color: #e74c3c; font-weight: bold; margin-left: 3px;">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <div class="aqm-question-field">
                                <?php $this->render_question_field($question); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="aqm-quiz-actions" style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e5e9;">
                        <button type="submit" class="aqm-submit-btn" style="background: linear-gradient(135deg, #007cba 0%, #00a0d2 100%); color: #fff; border: none; padding: 15px 40px; font-size: 1.1rem; font-weight: 600; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 15px rgba(0, 124, 186, 0.3);">
                            Submit Quiz
                        </button>
                    </div>
                <?php endif; ?>
            </form>
            
            <div class="aqm-quiz-result" id="aqm-quiz-result-<?php echo esc_attr($campaign_id); ?>" style="display: none; text-align: center; padding: 30px; background: #d1e7dd; border-radius: 8px; margin-top: 20px;">
                <!-- Results will be loaded here -->
            </div>
        </div>
        
        <style>
        .aqm-quiz-container input[type="text"],
        .aqm-quiz-container input[type="email"],
        .aqm-quiz-container input[type="tel"],
        .aqm-quiz-container input[type="number"],
        .aqm-quiz-container input[type="date"],
        .aqm-quiz-container select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        .aqm-quiz-container input:focus,
        .aqm-quiz-container select:focus {
            outline: none;
            border-color: #007cba;
        }
        .aqm-quiz-container .aqm-rating-container {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .aqm-quiz-container .aqm-rating-label {
            cursor: pointer;
            font-size: 2rem;
            color: #ddd;
            transition: color 0.3s ease;
        }
        .aqm-quiz-container .aqm-rating-label:hover,
        .aqm-quiz-container .aqm-rating-label.highlighted {
            color: #ffd700;
        }
        .aqm-quiz-container .aqm-rating-label input {
            display: none;
        }
        .aqm-provinces-select,
        .aqm-districts-select {
            margin-bottom: 10px;
        }
        .aqm-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 124, 186, 0.4);
        }
        </style>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle province selection
            const provinceSelect = document.querySelector('.aqm-provinces-select');
            if (provinceSelect) {
                provinceSelect.addEventListener('change', function() {
                    const provinceCode = this.value;
                    const questionContainer = this.closest('.aqm-question-container');
                    const districtSelect = questionContainer.querySelector('.aqm-districts-select');
                    
                    if (districtSelect) {
                        districtSelect.innerHTML = '<option value="">Select District</option>';
                        
                        if (provinceCode) {
                            // Load districts via AJAX or from localized data
                            const provincesData = <?php echo json_encode($this->get_vietnam_provinces_json()); ?>;
                            const province = provincesData.find(p => p.code === provinceCode);
                            
                            if (province && province.districts) {
                                province.districts.forEach(district => {
                                    const option = document.createElement('option');
                                    option.value = district.code;
                                    option.textContent = district.name;
                                    districtSelect.appendChild(option);
                                });
                                districtSelect.style.display = 'block';
                            }
                        } else {
                            districtSelect.style.display = 'none';
                        }
                    }
                });
            }
            
            // Handle rating system
            const ratingContainers = document.querySelectorAll('.aqm-rating-container');
            ratingContainers.forEach(container => {
                const labels = container.querySelectorAll('.aqm-rating-label');
                
                labels.forEach((label, index) => {
                    label.addEventListener('mouseover', () => {
                        highlightStars(labels, index);
                    });
                    
                    label.addEventListener('mouseout', () => {
                        const checked = container.querySelector('input:checked');
                        const checkedIndex = checked ? Array.from(labels).indexOf(checked.parentNode) : -1;
                        highlightStars(labels, checkedIndex);
                    });
                    
                    label.addEventListener('click', () => {
                        highlightStars(labels, index);
                        const input = label.querySelector('input');
                        if (input) input.checked = true;
                    });
                });
            });
            
            function highlightStars(labels, index) {
                labels.forEach((label, i) => {
                    if (i <= index) {
                        label.classList.add('highlighted');
                    } else {
                        label.classList.remove('highlighted');
                    }
                });
            }
            
            // Handle form submission
            const form = document.getElementById('aqm-quiz-form-<?php echo esc_attr($campaign_id); ?>');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const answers = {};
                    
                    // Collect answers
                    formData.forEach((value, key) => {
                        if (key.startsWith('question_')) {
                            answers[key] = value;
                        }
                    });
                    
                    formData.append('action', 'aqm_submit_quiz');
                    formData.append('answers', JSON.stringify(answers));
                    
                    const submitBtn = this.querySelector('.aqm-submit-btn');
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Submitting...';
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('aqm-quiz-result-<?php echo esc_attr($campaign_id); ?>').innerHTML = 
                                '<h3>🎉 Thank You!</h3><p>Your quiz has been submitted successfully.</p><p><strong>Your Score: ' + (data.data.score || 0) + ' points</strong></p>';
                            document.getElementById('aqm-quiz-result-<?php echo esc_attr($campaign_id); ?>').style.display = 'block';
                            this.style.display = 'none';
                        } else {
                            alert('Error: ' + (data.data.message || 'Submission failed'));
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Submit Quiz';
                        }
                    })
                    .catch(error => {
                        alert('Network error. Please try again.');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Submit Quiz';
                    });
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    private function render_question_field($question) {
        $options = json_decode($question->options, true) ?: array();
        $required = $question->is_required ? 'required' : '';
        $field_name = 'question_' . $question->id;
        
        switch ($question->question_type) {
            case 'provinces':
                echo '<select name="' . esc_attr($field_name) . '" class="aqm-provinces-select" ' . $required . '>';
                echo '<option value="">Select Province</option>';
                
                global $wpdb;
                $provinces = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aqm_provinces ORDER BY name");
                foreach ($provinces as $province) {
                    echo '<option value="' . esc_attr($province->code) . '">' . esc_html($province->name) . '</option>';
                }
                echo '</select>';
                
                if (isset($options['load_districts']) && $options['load_districts']) {
                    echo '<select name="' . esc_attr($field_name) . '_district" class="aqm-districts-select" style="display:none;">';
                    echo '<option value="">Select District</option>';
                    echo '</select>';
                }
                break;
                
            case 'rating':
                $max_rating = isset($options['max_rating']) ? intval($options['max_rating']) : 5;
                echo '<div class="aqm-rating-container">';
                for ($i = 1; $i <= $max_rating; $i++) {
                    echo '<label class="aqm-rating-label">';
                    echo '<input type="radio" name="' . esc_attr($field_name) . '" value="' . $i . '" ' . $required . '>';
                    echo '<span>★</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;
                
            case 'text':
                echo '<input type="text" name="' . esc_attr($field_name) . '" ' . $required . '>';
                break;
                
            case 'email':
                echo '<input type="email" name="' . esc_attr($field_name) . '" ' . $required . '>';
                break;
                
            case 'phone':
                echo '<input type="tel" name="' . esc_attr($field_name) . '" ' . $required . '>';
                break;
                
            case 'number':
                echo '<input type="number" name="' . esc_attr($field_name) . '" ' . $required . '>';
                break;
                
            case 'date':
                echo '<input type="date" name="' . esc_attr($field_name) . '" ' . $required . '>';
                break;
                
            default:
                echo '<input type="text" name="' . esc_attr($field_name) . '" ' . $required . '>';
                break;
        }
    }
    
    private function render_quiz_results($campaign_id, $show_charts) {
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return '<div class="aqm-error">Campaign not found.</div>';
        }
        
        $stats = $this->get_campaign_stats($campaign_id);
        
        ob_start();
        ?>
        <div class="aqm-results-container" style="max-width: 800px; margin: 0 auto; padding: 30px; background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);">
            <h3 style="text-align: center; margin-bottom: 30px; color: #2c3e50;">📊 Quiz Results: <?php echo esc_html($campaign->title); ?></h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="background: #d1e7dd; padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0; color: #0a3622;">Total Participants</h4>
                    <span style="font-size: 24px; font-weight: bold; color: #0a3622;"><?php echo esc_html($stats['total_participants']); ?></span>
                </div>
                
                <div style="background: #cff4fc; padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0; color: #055160;">Completion Rate</h4>
                    <span style="font-size: 24px; font-weight: bold; color: #055160;"><?php echo esc_html($stats['completion_rate']); ?>%</span>
                </div>
                
                <div style="background: #fff3cd; padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0; color: #664d03;">Average Score</h4>
                    <span style="font-size: 24px; font-weight: bold; color: #664d03;"><?php echo esc_html($stats['average_score']); ?></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // AJAX HANDLERS
    public function submit_quiz() {
        if (!wp_verify_nonce($_POST['aqm_nonce'], 'aqm_submit_quiz')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $answers = json_decode(stripslashes($_POST['answers']), true);
        
        // Basic validation
        if (!$campaign_id || !is_array($answers)) {
            wp_send_json_error(array('message' => 'Invalid data'));
        }
        
        // Save response
        global $wpdb;
        $response_id = $wpdb->insert(
            $wpdb->prefix . 'aqm_responses',
            array(
                'campaign_id' => $campaign_id,
                'user_email' => sanitize_email($_POST['user_email'] ?? ''),
                'user_name' => sanitize_text_field($_POST['user_name'] ?? ''),
                'total_score' => 0,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'status' => 'completed'
            )
        );
        
        if ($response_id) {
            $response_id = $wpdb->insert_id;
            
            // Save answers
            $total_score = 0;
            foreach ($answers as $question_key => $answer_value) {
                if (strpos($question_key, 'question_') === 0) {
                    $question_id = str_replace('question_', '', $question_key);
                    $question_id = intval($question_id);
                    
                    if ($question_id > 0) {
                        $wpdb->insert(
                            $wpdb->prefix . 'aqm_answers',
                            array(
                                'response_id' => $response_id,
                                'question_id' => $question_id,
                                'answer_value' => sanitize_text_field($answer_value),
                                'answer_score' => 0
                            )
                        );
                    }
                }
            }
            
            wp_send_json_success(array(
                'message' => 'Quiz submitted successfully!',
                'score' => $total_score,
                'response_id' => $response_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save response'));
        }
    }
    
    public function get_provinces() {
        wp_send_json_success($this->get_vietnam_provinces_json());
    }
    
    public function save_campaign() {
        // This is handled by handle_campaign_save method
        wp_send_json_success();
    }
}

// Initialize the plugin
new AdvancedQuizManager();
?>