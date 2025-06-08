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

// Main plugin class
class AdvancedQuizManager {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        load_plugin_textdomain('advanced-quiz', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        $this->init_database();
        $this->init_admin();
        $this->init_api();
        $this->init_frontend();
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
    
    private function init_database() {
        require_once AQM_PLUGIN_PATH . 'includes/class-database.php';
        new AQM_Database();
    }
    
    private function init_admin() {
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('wp_ajax_aqm_save_campaign', array($this, 'save_campaign'));
            add_action('wp_ajax_aqm_get_districts', array($this, 'get_districts'));
            add_action('wp_ajax_aqm_get_wards', array($this, 'get_wards'));
        }
    }
    
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
            'Gifts & Rewards',
            'Gifts & Rewards',
            'manage_options',
            'quiz-manager-gifts',
            array($this, 'admin_gifts_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Analytics',
            'Analytics',
            'manage_options',
            'quiz-manager-analytics',
            array($this, 'admin_analytics_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Provinces Data',
            'Provinces Data',
            'manage_options',
            'quiz-manager-provinces',
            array($this, 'admin_provinces_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Settings',
            'Settings',
            'manage_options',
            'quiz-manager-settings',
            array($this, 'admin_settings_page')
        );
    }
    
    public function admin_dashboard_page() {
        $db = new AQM_Database();
        
        // Get overview stats
        global $wpdb;
        $total_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_campaigns");
        $total_responses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses");
        $completed_responses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE status = 'completed'");
        $completion_rate = $total_responses > 0 ? round(($completed_responses / $total_responses) * 100, 1) : 0;
        
        ?>
        <div class="wrap">
            <h1>Quiz Manager Dashboard</h1>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
                    <h3 style="margin: 0 0 10px 0; color: #2271b1;">📊 Total Campaigns</h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0;"><?php echo esc_html($total_campaigns); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
                    <h3 style="margin: 0 0 10px 0; color: #2271b1;">👥 Total Responses</h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0;"><?php echo esc_html($total_responses); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
                    <h3 style="margin: 0 0 10px 0; color: #2271b1;">✅ Completion Rate</h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0;"><?php echo esc_html($completion_rate); ?>%</p>
                </div>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px; margin: 20px 0;">
                <h2>🚀 Quick Start</h2>
                <p>Welcome to Advanced Quiz Manager! Here's how to get started:</p>
                <ol>
                    <li><strong>Create a Campaign:</strong> Go to <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns'); ?>">Campaigns</a> and click "Add New"</li>
                    <li><strong>Add Questions:</strong> Go to <a href="<?php echo admin_url('admin.php?page=quiz-manager-questions'); ?>">Questions</a> to add quiz questions</li>
                    <li><strong>Import Provinces:</strong> Go to <a href="<?php echo admin_url('admin.php?page=quiz-manager-provinces'); ?>">Provinces Data</a> to import Vietnamese provinces</li>
                    <li><strong>Display Quiz:</strong> Use shortcode <code>[quiz_form campaign_id="1"]</code> on any page</li>
                </ol>
                
                <h3>🇻🇳 Vietnamese Provinces Integration</h3>
                <p>This plugin includes full support for Vietnamese provinces, districts, and wards. You can:</p>
                <ul>
                    <li>Add province selection questions to your quizzes</li>
                    <li>Import your own province data via JSON</li>
                    <li>View geographic analytics of your participants</li>
                </ul>
                
                <h3>📋 Sample Shortcodes</h3>
                <p>Use these shortcodes to display your quizzes:</p>
                <ul>
                    <li><code>[quiz_form campaign_id="1"]</code> - Display a quiz form</li>
                    <li><code>[quiz_results campaign_id="1"]</code> - Show quiz results and stats</li>
                    <li><code>[quiz_stats campaign_id="1"]</code> - Display quick statistics</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function admin_campaigns_page() {
        $db = new AQM_Database();
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        if ($action === 'new' || $action === 'edit') {
            $this->render_campaign_form($action);
            return;
        }
        
        // List campaigns
        $campaigns = $db->get_campaigns();
        ?>
        <div class="wrap">
            <h1>Quiz Campaigns <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=new'); ?>" class="page-title-action">Add New</a></h1>
            
            <?php if (empty($campaigns)): ?>
                <div style="background: #fff; padding: 40px; text-align: center; border: 1px solid #ccc; border-radius: 8px;">
                    <h2>🎯 Create Your First Campaign</h2>
                    <p>Get started by creating your first quiz campaign!</p>
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=new'); ?>" class="button button-primary">Create Campaign</a>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Responses</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                            <?php $stats = $db->get_campaign_stats($campaign->id); ?>
                            <tr>
                                <td><strong><?php echo esc_html($campaign->title); ?></strong></td>
                                <td><?php echo esc_html(ucfirst($campaign->status)); ?></td>
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
            $db = new AQM_Database();
            $campaign = $db->get_campaign($campaign_id);
        }
        
        // Handle form submission
        if (isset($_POST['submit_campaign'])) {
            $this->handle_campaign_save();
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo $action === 'edit' ? 'Edit Campaign' : 'Create New Campaign'; ?></h1>
            
            <form method="post" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
                <?php wp_nonce_field('aqm_save_campaign', 'aqm_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="title">Campaign Title *</label></th>
                        <td>
                            <input type="text" id="title" name="title" class="regular-text" 
                                   value="<?php echo esc_attr($campaign ? $campaign->title : ''); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description">Description</label></th>
                        <td>
                            <textarea id="description" name="description" class="large-text" rows="4"><?php echo esc_textarea($campaign ? $campaign->description : ''); ?></textarea>
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
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_participants">Max Participants</label></th>
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
        echo '<div class="wrap">';
        echo '<h1>Question Management</h1>';
        echo '<p>Add and manage questions for your quiz campaigns. Support for Vietnamese provinces, multiple choice, ratings, and more!</p>';
        echo '<div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">';
        echo '<h3>🇻🇳 Vietnamese Provinces Questions</h3>';
        echo '<p>You can add questions that let users select:</p>';
        echo '<ul><li><strong>Provinces:</strong> All 63 Vietnamese provinces/cities</li>';
        echo '<li><strong>Districts:</strong> Districts that load based on province selection</li>';
        echo '<li><strong>Wards:</strong> Wards/communes that load based on district selection</li></ul>';
        echo '<p><em>Question management interface coming soon. For now, you can create basic campaigns and questions will be added via the database.</em></p>';
        echo '</div>';
        echo '</div>';
    }
    
    public function admin_responses_page() {
        echo '<div class="wrap">';
        echo '<h1>Quiz Responses</h1>';
        echo '<p>View and analyze responses from your quiz participants.</p>';
        echo '<div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">';
        echo '<h3>📊 Response Analytics</h3>';
        echo '<p>Here you can:</p>';
        echo '<ul><li>View all quiz responses</li>';
        echo '<li>Export data to CSV</li>';
        echo '<li>See geographic distribution (Vietnamese provinces)</li>';
        echo '<li>Analyze completion rates and scores</li></ul>';
        echo '<p><em>Full response management interface coming soon.</em></p>';
        echo '</div>';
        echo '</div>';
    }
    
    public function admin_gifts_page() {
        echo '<div class="wrap">';
        echo '<h1>Gifts & Rewards Management</h1>';
        echo '<p>Set up gifts and rewards for quiz participants based on their scores.</p>';
        echo '<div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">';
        echo '<h3>🎁 Reward System</h3>';
        echo '<p>Configure:</p>';
        echo '<ul><li><strong>Gift Types:</strong> Discount codes, vouchers, prizes</li>';
        echo '<li><strong>Scoring:</strong> Minimum score requirements</li>';
        echo '<li><strong>Probability:</strong> Chance of winning each gift</li>';
        echo '<li><strong>Inventory:</strong> Limited quantity management</li></ul>';
        echo '<p><em>Gift management interface coming soon.</em></p>';
        echo '</div>';
        echo '</div>';
    }
    
    public function admin_analytics_page() {
        echo '<div class="wrap">';
        echo '<h1>Quiz Analytics</h1>';
        echo '<p>Comprehensive analytics and reporting for your quiz campaigns.</p>';
        echo '<div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">';
        echo '<h3>📈 Available Reports</h3>';
        echo '<ul><li><strong>Participation Trends:</strong> Daily/weekly/monthly participation</li>';
        echo '<li><strong>Geographic Distribution:</strong> Vietnamese provinces breakdown</li>';
        echo '<li><strong>Completion Analysis:</strong> Drop-off points and completion rates</li>';
        echo '<li><strong>Score Distribution:</strong> Performance analytics</li>';
        echo '<li><strong>Gift Analytics:</strong> Reward distribution and claims</li></ul>';
        echo '<p><em>Full analytics dashboard coming soon.</em></p>';
        echo '</div>';
        echo '</div>';
    }
    
    public function admin_provinces_page() {
        echo '<div class="wrap">';
        echo '<h1>Vietnamese Provinces Data</h1>';
        echo '<p>Manage the Vietnamese provinces, districts, and wards database.</p>';
        echo '<div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">';
        echo '<h3>🇻🇳 Province Database</h3>';
        echo '<p>Current capabilities:</p>';
        echo '<ul><li><strong>63 Provinces/Cities:</strong> Complete database of Vietnamese administrative divisions</li>';
        echo '<li><strong>JSON Import:</strong> Import custom province data</li>';
        echo '<li><strong>Bilingual Support:</strong> Vietnamese and English names</li>';
        echo '<li><strong>Hierarchical Structure:</strong> Provinces → Districts → Wards</li></ul>';
        
        // Show current province count
        global $wpdb;
        $province_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_provinces");
        $district_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_districts");
        $ward_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_wards");
        
        echo '<h4>Current Database Status:</h4>';
        echo '<ul>';
        echo '<li>Provinces: ' . esc_html($province_count) . '</li>';
        echo '<li>Districts: ' . esc_html($district_count) . '</li>';
        echo '<li>Wards: ' . esc_html($ward_count) . '</li>';
        echo '</ul>';
        
        if ($province_count == 0) {
            echo '<p style="color: #d63384;"><strong>Note:</strong> No provinces data found. The sample data should be imported automatically. If you don\'t see provinces in your quiz questions, try deactivating and reactivating the plugin.</p>';
        }
        
        echo '<p><em>JSON import interface coming soon.</em></p>';
        echo '</div>';
        echo '</div>';
    }
    
    public function admin_settings_page() {
        echo '<div class="wrap">';
        echo '<h1>Quiz Settings</h1>';
        echo '<p>Global settings for the Advanced Quiz Manager plugin.</p>';
        echo '<div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">';
        echo '<h3>⚙️ Configuration Options</h3>';
        echo '<p>Settings you can configure:</p>';
        echo '<ul><li><strong>Default Styling:</strong> Quiz appearance and themes</li>';
        echo '<li><strong>Email Notifications:</strong> Automatic emails for participants</li>';
        echo '<li><strong>Data Retention:</strong> How long to keep analytics data</li>';
        echo '<li><strong>Security:</strong> Spam protection and rate limiting</li>';
        echo '<li><strong>Integrations:</strong> Third-party service connections</li></ul>';
        echo '<p><em>Settings interface coming soon.</em></p>';
        echo '</div>';
        echo '</div>';
    }
    
    private function init_api() {
        require_once AQM_PLUGIN_PATH . 'includes/class-api.php';
        new AQM_API();
    }
    
    private function init_frontend() {
        require_once AQM_PLUGIN_PATH . 'public/class-frontend.php';
        new AQM_Frontend();
    }
    
    private function init_scripts() {
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
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
    
    public function admin_scripts($hook) {
        if (strpos($hook, 'quiz-manager') !== false) {
            wp_enqueue_script('aqm-admin-js', AQM_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), AQM_VERSION, true);
            wp_enqueue_style('aqm-admin-css', AQM_PLUGIN_URL . 'assets/css/admin.css', array(), AQM_VERSION);
            
            wp_localize_script('aqm-admin-js', 'aqm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqm_nonce'),
                'provinces_data' => $this->get_vietnam_provinces_json()
            ));
        }
    }
    
    public function frontend_scripts() {
        wp_enqueue_script('aqm-frontend-js', AQM_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), AQM_VERSION, true);
        wp_enqueue_style('aqm-frontend-css', AQM_PLUGIN_URL . 'assets/css/frontend.css', array(), AQM_VERSION);
        
        wp_localize_script('aqm-frontend-js', 'aqm_front', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aqm_front_nonce'),
            'provinces_data' => $this->get_vietnam_provinces_json()
        ));
    }
    
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Campaigns table
        $campaigns_table = $wpdb->prefix . 'aqm_campaigns';
        $sql_campaigns = "CREATE TABLE $campaigns_table (
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
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Questions table
        $questions_table = $wpdb->prefix . 'aqm_questions';
        $sql_questions = "CREATE TABLE $questions_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            question_text text NOT NULL,
            question_type enum('multiple_choice','text','number','email','phone','date','provinces','districts','wards','rating','file_upload') NOT NULL,
            options longtext,
            is_required tinyint(1) DEFAULT 0,
            validation_rules longtext,
            order_index int(11) DEFAULT 0,
            points int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY order_index (order_index)
        ) $charset_collate;";
        
        // Responses table
        $responses_table = $wpdb->prefix . 'aqm_responses';
        $sql_responses = "CREATE TABLE $responses_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            user_id int(11),
            user_email varchar(255),
            user_name varchar(255),
            user_phone varchar(20),
            total_score int(11) DEFAULT 0,
            completion_time int(11),
            ip_address varchar(45),
            user_agent text,
            status enum('completed','abandoned','in_progress') DEFAULT 'in_progress',
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY user_id (user_id),
            KEY user_email (user_email),
            KEY status (status)
        ) $charset_collate;";
        
        // Answers table
        $answers_table = $wpdb->prefix . 'aqm_answers';
        $sql_answers = "CREATE TABLE $answers_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            response_id int(11) NOT NULL,
            question_id int(11) NOT NULL,
            answer_value longtext,
            answer_score int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY question_id (question_id)
        ) $charset_collate;";
        
        // Gifts table
        $gifts_table = $wpdb->prefix . 'aqm_gifts';
        $sql_gifts = "CREATE TABLE $gifts_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            image_url varchar(500),
            value decimal(10,2),
            quantity int(11) DEFAULT 0,
            min_score int(11) DEFAULT 0,
            probability decimal(5,2) DEFAULT 100.00,
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Gift awards table
        $gift_awards_table = $wpdb->prefix . 'aqm_gift_awards';
        $sql_gift_awards = "CREATE TABLE $gift_awards_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            response_id int(11) NOT NULL,
            gift_id int(11) NOT NULL,
            claim_code varchar(100),
            is_claimed tinyint(1) DEFAULT 0,
            claimed_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY gift_id (gift_id),
            KEY claim_code (claim_code)
        ) $charset_collate;";
        
        // Notifications table
        $notifications_table = $wpdb->prefix . 'aqm_notifications';
        $sql_notifications = "CREATE TABLE $notifications_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            type enum('welcome','completion','gift_won','reminder') NOT NULL,
            title varchar(255) NOT NULL,
            content text NOT NULL,
            popup_settings longtext,
            email_settings longtext,
            sms_settings longtext,
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";
        
        // Analytics table
        $analytics_table = $wpdb->prefix . 'aqm_analytics';
        $sql_analytics = "CREATE TABLE $analytics_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11),
            event_type varchar(50) NOT NULL,
            event_data longtext,
            user_id int(11),
            session_id varchar(100),
            ip_address varchar(45),
            user_agent text,
            page_url varchar(500),
            referrer varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_campaigns);
        dbDelta($sql_questions);
        dbDelta($sql_responses);
        dbDelta($sql_answers);
        dbDelta($sql_gifts);
        dbDelta($sql_gift_awards);
        dbDelta($sql_notifications);
        dbDelta($sql_analytics);
    }
    
    private function create_default_data() {
        global $wpdb;
        
        // Create sample campaign
        $campaigns_table = $wpdb->prefix . 'aqm_campaigns';
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table");
        
        if ($existing == 0) {
            $campaign_id = $wpdb->insert(
                $campaigns_table,
                array(
                    'title' => 'Sample Quiz Campaign',
                    'description' => 'This is a sample quiz campaign with Vietnamese provinces field',
                    'status' => 'active',
                    'start_date' => date('Y-m-d H:i:s'),
                    'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
                    'max_participants' => 1000,
                    'settings' => json_encode(array(
                        'allow_multiple_attempts' => false,
                        'show_results_immediately' => true,
                        'collect_email' => true,
                        'require_login' => false
                    )),
                    'created_by' => get_current_user_id()
                )
            );
            
            if ($campaign_id) {
                // Add sample questions
                $questions_table = $wpdb->prefix . 'aqm_questions';
                
                $sample_questions = array(
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
                        'is_required' => 1,
                        'order_index' => 2,
                        'options' => json_encode(array(
                            'load_districts' => true,
                            'load_wards' => true,
                            'placeholder' => 'Select your province'
                        ))
                    ),
                    array(
                        'campaign_id' => $campaign_id,
                        'question_text' => 'Rate your experience (1-5 stars)',
                        'question_type' => 'rating',
                        'is_required' => 1,
                        'order_index' => 3,
                        'options' => json_encode(array(
                            'max_rating' => 5,
                            'icon' => 'star'
                        )),
                        'points' => 10
                    )
                );
                
                foreach ($sample_questions as $question) {
                    $wpdb->insert($questions_table, $question);
                }
                
                // Add sample gifts
                $gifts_table = $wpdb->prefix . 'aqm_gifts';
                $sample_gifts = array(
                    array(
                        'campaign_id' => $campaign_id,
                        'name' => 'Discount Code 10%',
                        'description' => 'Get 10% discount on your next purchase',
                        'value' => 100000,
                        'quantity' => 100,
                        'min_score' => 0,
                        'probability' => 50.00
                    ),
                    array(
                        'campaign_id' => $campaign_id,
                        'name' => 'Free Shipping Voucher',
                        'description' => 'Free shipping for orders over 500k',
                        'value' => 50000,
                        'quantity' => 200,
                        'min_score' => 5,
                        'probability' => 30.00
                    )
                );
                
                foreach ($sample_gifts as $gift) {
                    $wpdb->insert($gifts_table, $gift);
                }
            }
        }
    }
    
    private function get_vietnam_provinces_json() {
        // This will be handled by the AQM_Database class
        // Return basic structure for JavaScript initialization
        return array(
            array(
                'code' => '01',
                'name' => 'Hà Nội',
                'name_en' => 'Hanoi',
                'full_name' => 'Thành phố Hà Nội',
                'districts' => array()
            ),
            array(
                'code' => '79',
                'name' => 'Hồ Chí Minh',
                'name_en' => 'Ho Chi Minh',
                'full_name' => 'Thành phố Hồ Chí Minh',
                'districts' => array()
            ),
            array(
                'code' => '48',
                'name' => 'Đà Nẵng',
                'name_en' => 'Da Nang',
                'full_name' => 'Thành phố Đà Nẵng',
                'districts' => array()
            ),
            array(
                'code' => '92',
                'name' => 'Cần Thơ',
                'name_en' => 'Can Tho',
                'full_name' => 'Thành phố Cần Thơ',
                'districts' => array()
            ),
            array(
                'code' => '31',
                'name' => 'Hải Phòng',
                'name_en' => 'Hai Phong',
                'full_name' => 'Thành phố Hải Phòng',
                'districts' => array()
            )
        );
    }
    
    private function render_quiz_form($campaign_id, $style) {
        if (empty($campaign_id)) {
            return '<p>Invalid campaign ID</p>';
        }
        
        $db = new AQM_Database();
        $campaign = $db->get_campaign($campaign_id);
        
        if (!$campaign) {
            return '<p>Campaign not found</p>';
        }
        
        $questions = $db->get_campaign_questions($campaign_id);
        
        ob_start();
        ?>
        <div class="aqm-quiz-container" data-campaign-id="<?php echo esc_attr($campaign_id); ?>" data-style="<?php echo esc_attr($style); ?>">
            <div class="aqm-quiz-header">
                <h2><?php echo esc_html($campaign->title); ?></h2>
                <?php if ($campaign->description): ?>
                    <p class="aqm-quiz-description"><?php echo esc_html($campaign->description); ?></p>
                <?php endif; ?>
            </div>
            
            <form class="aqm-quiz-form" id="aqm-quiz-form-<?php echo esc_attr($campaign_id); ?>">
                <?php wp_nonce_field('aqm_submit_quiz', 'aqm_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>">
                
                <?php foreach ($questions as $question): ?>
                    <div class="aqm-question-container" data-question-id="<?php echo esc_attr($question->id); ?>" data-question-type="<?php echo esc_attr($question->question_type); ?>">
                        <label class="aqm-question-label">
                            <?php echo esc_html($question->question_text); ?>
                            <?php if ($question->is_required): ?>
                                <span class="aqm-required">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php $this->render_question_field($question); ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="aqm-quiz-actions">
                    <button type="submit" class="aqm-submit-btn">Submit Quiz</button>
                </div>
            </form>
            
            <div class="aqm-quiz-result" id="aqm-quiz-result-<?php echo esc_attr($campaign_id); ?>" style="display: none;">
                <!-- Results will be loaded here -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_question_field($question) {
        $options = json_decode($question->options, true);
        
        switch ($question->question_type) {
            case 'provinces':
                echo '<select name="question_' . esc_attr($question->id) . '" class="aqm-provinces-select" ' . ($question->is_required ? 'required' : '') . '>';
                echo '<option value="">Select Province</option>';
                // Provinces will be loaded via AJAX
                echo '</select>';
                break;
                
            case 'text':
                echo '<input type="text" name="question_' . esc_attr($question->id) . '" class="aqm-text-input" ' . ($question->is_required ? 'required' : '') . '>';
                break;
                
            case 'email':
                echo '<input type="email" name="question_' . esc_attr($question->id) . '" class="aqm-email-input" ' . ($question->is_required ? 'required' : '') . '>';
                break;
                
            case 'rating':
                $max_rating = isset($options['max_rating']) ? $options['max_rating'] : 5;
                echo '<div class="aqm-rating-container">';
                for ($i = 1; $i <= $max_rating; $i++) {
                    echo '<label class="aqm-rating-label">';
                    echo '<input type="radio" name="question_' . esc_attr($question->id) . '" value="' . $i . '" ' . ($question->is_required ? 'required' : '') . '>';
                    echo '<span class="aqm-star">★</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;
                
            default:
                echo '<input type="text" name="question_' . esc_attr($question->id) . '" class="aqm-text-input" ' . ($question->is_required ? 'required' : '') . '>';
                break;
        }
    }
}
?>