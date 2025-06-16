<?php
/**
 * Plugin Name: Vefify Quiz Campaign Manager
 * Description: Advanced quiz campaign management with modular architecture
 * Version: 1.2.0
 * Author: Vefify Team
 * License: GPL v2 or later
 * Text Domain: vefify-quiz
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('VEFIFY_QUIZ_VERSION', '1.2.0');
define('VEFIFY_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VEFIFY_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VEFIFY_QUIZ_TABLE_PREFIX', 'vefify_');
define('VEFIFY_QUIZ_DB_VERSION', '1.0.0');

/**
 * Main Plugin Class with Modular Architecture
 */
class Vefify_Quiz_Plugin {
    
    private static $instance = null;
    private $modules = array();
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // WordPress hooks
        add_action('init', array($this, 'init_plugin'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load modules
        $this->load_modules();
    }
    
    /**
     * Load all plugin modules
     */
    private function load_modules() {
        // Load Questions Module (existing)
        if (file_exists(VEFIFY_QUIZ_PLUGIN_DIR . 'modules/questions/class-question-module.php')) {
            require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/questions/class-question-module.php';
            $this->modules['questions'] = Vefify_Question_Module::get_instance();
        }
        
        // Load Campaign Module (new modular structure)
        if (file_exists(VEFIFY_QUIZ_PLUGIN_DIR . 'modules/campaigns/class-campaign-module.php')) {
            require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/campaigns/class-campaign-module.php';
            $this->modules['campaigns'] = Vefify_Campaign_Module::get_instance();
        }
        
        // Load other modules as they are created
        $this->load_additional_modules();
    }
    
    /**
     * Load additional modules (gifts, participants, reports, settings)
     */
    private function load_additional_modules() {
        $module_configs = array(
            'gifts' => array(
                'file' => 'modules/gifts/class-gift-module.php',
                'class' => 'Vefify_Gift_Module'
            ),
            'participants' => array(
                'file' => 'modules/participants/class-participant-module.php',
                'class' => 'Vefify_Participant_Module'
            ),
            'reports' => array(
                'file' => 'modules/reports/class-report-module.php',
                'class' => 'Vefify_Report_Module'
            ),
            'settings' => array(
                'file' => 'modules/settings/class-setting-module.php',
                'class' => 'Vefify_Setting_Module'
            )
        );
        
        foreach ($module_configs as $module_key => $config) {
            $file_path = VEFIFY_QUIZ_PLUGIN_DIR . $config['file'];
            if (file_exists($file_path)) {
                require_once $file_path;
                if (class_exists($config['class'])) {
                    $this->modules[$module_key] = $config['class']::get_instance();
                }
            }
        }
    }
    
    public function init_plugin() {
        // Load textdomain for translations
        load_plugin_textdomain('vefify-quiz', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Note: Modules initialize themselves in their constructors
        // We don't need to call init() on them here since they're already initialized
        // when we called get_instance() in load_modules()
    }
    
    /**
     * Add main admin menu and dashboard
     */
    public function add_admin_menu() {
        // Main dashboard page
        add_menu_page(
            'Vefify Quiz',
            'Vefify Quiz',
            'manage_options',
            'vefify-quiz',
            array($this, 'display_dashboard'),
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2c0-.55-.45-1-1-1s-1 .45-1 1v2H8V2c0-.55-.45-1-1-1s-1 .45-1 1v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/></svg>'),
            26
        );
        
        // Dashboard submenu (duplicate to show in submenu)
        add_submenu_page(
            'vefify-quiz',
            'Dashboard',
            '🏠 Dashboard',
            'manage_options',
            'vefify-quiz',
            array($this, 'display_dashboard')
        );
        
        // Note: Individual modules add their own submenus via their add_admin_menu() methods
        // This happens automatically when modules are loaded
    }
    
    /**
     * Display main dashboard
     */
    public function display_dashboard() {
        ?>
        <div class="wrap">
            <h1>🎯 Vefify Quiz Dashboard</h1>
            
            <!-- Quick Stats Overview -->
            <div class="dashboard-widgets-wrap">
                <div class="metabox-holder columns-4">
                    <?php $this->display_dashboard_widgets(); ?>
                </div>
            </div>
            
            <!-- Module Analytics -->
            <div class="dashboard-modules">
                <h2>📊 Module Analytics</h2>
                <div class="module-analytics-grid">
                    <?php $this->display_module_analytics(); ?>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="dashboard-activity">
                <h2>📈 Recent Activity</h2>
                <?php $this->display_recent_activity(); ?>
            </div>
        </div>
        
        <style>
        .dashboard-widgets-wrap { margin: 20px 0; }
        .columns-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .dashboard-widget { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .widget-number { font-size: 32px; font-weight: bold; color: #0073aa; margin-bottom: 10px; }
        .widget-label { color: #666; font-size: 14px; }
        .widget-change { font-size: 12px; margin-top: 8px; }
        .widget-change.positive { color: #46b450; }
        .widget-change.negative { color: #dc3232; }
        .module-analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
        .module-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; }
        .module-card h3 { margin: 0 0 15px; color: #0073aa; display: flex; align-items: center; gap: 8px; }
        .module-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 15px 0; }
        .module-stat { text-align: center; padding: 10px; background: #f9f9f9; border-radius: 4px; }
        .module-stat-number { font-size: 18px; font-weight: bold; color: #0073aa; }
        .module-stat-label { font-size: 12px; color: #666; }
        .module-actions { margin-top: 15px; }
        .module-actions .button { margin-right: 8px; }
        .activity-list { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; }
        .activity-item { padding: 10px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .activity-item:last-child { border-bottom: none; }
        .activity-description { flex: 1; }
        .activity-time { color: #666; font-size: 12px; }
        </style>
        <?php
    }
    
    /**
     * Display dashboard overview widgets
     */
    private function display_dashboard_widgets() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Get overview statistics with error handling
        $total_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}campaigns") ?: 0;
        $active_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}campaigns WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()") ?: 0;
        $total_participants = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}participants") ?: 0;
        $total_questions = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}questions") ?: 0;
        
        $widgets = array(
            array(
                'number' => number_format($total_campaigns),
                'label' => 'Total Campaigns',
                'change' => '+12% this month',
                'change_class' => 'positive'
            ),
            array(
                'number' => number_format($active_campaigns),
                'label' => 'Active Campaigns',
                'change' => 'Running now',
                'change_class' => ''
            ),
            array(
                'number' => number_format($total_participants),
                'label' => 'Total Participants',
                'change' => '+45% this week',
                'change_class' => 'positive'
            ),
            array(
                'number' => number_format($total_questions),
                'label' => 'Questions in Bank',
                'change' => '+8 questions today',
                'change_class' => 'positive'
            )
        );
        
        foreach ($widgets as $widget) {
            ?>
            <div class="dashboard-widget">
                <div class="widget-number"><?php echo $widget['number']; ?></div>
                <div class="widget-label"><?php echo $widget['label']; ?></div>
                <div class="widget-change <?php echo $widget['change_class']; ?>"><?php echo $widget['change']; ?></div>
            </div>
            <?php
        }
    }
    
    /**
     * Display module-specific analytics
     */
    private function display_module_analytics() {
        foreach ($this->modules as $module_key => $module) {
            if (method_exists($module, 'get_module_analytics')) {
                $analytics = $module->get_module_analytics();
                ?>
                <div class="module-card">
                    <h3><?php echo $analytics['icon']; ?> <?php echo $analytics['title']; ?></h3>
                    <p><?php echo $analytics['description']; ?></p>
                    
                    <div class="module-stats">
                        <?php foreach ($analytics['stats'] as $stat): ?>
                            <div class="module-stat">
                                <div class="module-stat-number"><?php echo $stat['value']; ?></div>
                                <div class="module-stat-label"><?php echo $stat['label']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="module-actions">
                        <?php foreach ($analytics['quick_actions'] as $action): ?>
                            <a href="<?php echo $action['url']; ?>" class="button <?php echo $action['class']; ?>"><?php echo $action['label']; ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Display recent activity feed
     */
    private function display_recent_activity() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Get recent activities from analytics table (if exists)
        $activities = $wpdb->get_results(
            "SELECT * FROM {$table_prefix}analytics 
             ORDER BY created_at DESC 
             LIMIT 10", 
            ARRAY_A
        );
        
        ?>
        <div class="activity-list">
            <?php if (!empty($activities)): ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-description">
                            <strong><?php echo esc_html($activity['action_type']); ?>:</strong>
                            <?php echo esc_html($activity['description']); ?>
                        </div>
                        <div class="activity-time">
                            <?php echo human_time_diff(strtotime($activity['created_at']), current_time('timestamp')) . ' ago'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="activity-item">
                    <div class="activity-description">
                        <em>No recent activity. Start creating campaigns and questions to see activity here.</em>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_database_tables();
        
        // Insert sample data
        $this->insert_sample_data();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook('vefify_daily_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Campaigns table
        $campaigns_table = "CREATE TABLE IF NOT EXISTS {$table_prefix}campaigns (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            max_participants int(11) DEFAULT 0,
            questions_per_quiz int(11) DEFAULT 5,
            pass_score int(11) DEFAULT 3,
            time_limit int(11) DEFAULT 600,
            is_active tinyint(1) DEFAULT 1,
            meta_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active),
            KEY date_range (start_date, end_date)
        ) $charset_collate;";
        
        // Questions table
        $questions_table = "CREATE TABLE IF NOT EXISTS {$table_prefix}questions (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) DEFAULT NULL,
            question_text text NOT NULL,
            question_type enum('single_choice','multiple_choice','true_false','text_input') DEFAULT 'single_choice',
            difficulty enum('easy','medium','hard') DEFAULT 'medium',
            category varchar(100) DEFAULT NULL,
            explanation text,
            order_index int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY is_active (is_active),
            KEY difficulty (difficulty),
            KEY category (category)
        ) $charset_collate;";
        
        // Question options table
        $options_table = "CREATE TABLE IF NOT EXISTS {$table_prefix}question_options (
            id int(11) NOT NULL AUTO_INCREMENT,
            question_id int(11) NOT NULL,
            option_text text NOT NULL,
            is_correct tinyint(1) DEFAULT 0,
            explanation text,
            order_index int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY question_id (question_id),
            KEY is_correct (is_correct)
        ) $charset_collate;";
        
        // Participants table
        $participants_table = "CREATE TABLE IF NOT EXISTS {$table_prefix}participants (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            participant_name varchar(255),
            participant_email varchar(255),
            participant_phone varchar(50),
            quiz_status enum('started','in_progress','completed','abandoned') DEFAULT 'started',
            start_time datetime DEFAULT CURRENT_TIMESTAMP,
            end_time datetime DEFAULT NULL,
            final_score int(11) DEFAULT 0,
            answers_data longtext,
            gift_code varchar(100) DEFAULT NULL,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY quiz_status (quiz_status),
            KEY participant_email (participant_email),
            KEY gift_code (gift_code)
        ) $charset_collate;";
        
        // Gifts table
        $gifts_table = "CREATE TABLE IF NOT EXISTS {$table_prefix}gifts (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            gift_name varchar(255) NOT NULL,
            gift_type enum('voucher','discount','physical','digital') DEFAULT 'voucher',
            gift_value varchar(100),
            min_score int(11) DEFAULT 0,
            max_score int(11) DEFAULT 10,
            max_quantity int(11) DEFAULT NULL,
            current_quantity int(11) DEFAULT 0,
            gift_code_prefix varchar(20) DEFAULT 'GIFT',
            api_endpoint varchar(255) DEFAULT NULL,
            api_params text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY is_active (is_active),
            KEY score_range (min_score, max_score)
        ) $charset_collate;";
        
        // Analytics table
        $analytics_table = "CREATE TABLE IF NOT EXISTS {$table_prefix}analytics (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) DEFAULT NULL,
            action_type varchar(50) NOT NULL,
            description text,
            meta_data longtext,
            user_id int(11) DEFAULT NULL,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY action_type (action_type),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($campaigns_table);
        dbDelta($questions_table);
        dbDelta($options_table);
        dbDelta($participants_table);
        dbDelta($gifts_table);
        dbDelta($analytics_table);
    }
    
    /**
     * Insert sample data
     */
    private function insert_sample_data() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Check if sample data already exists
        $existing_campaign = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}campaigns");
        if ($existing_campaign > 0) {
            return; // Sample data already exists
        }
        
        // Insert sample campaign
        $wpdb->insert(
            $table_prefix . 'campaigns',
            array(
                'name' => 'Health Knowledge Quiz 2024',
                'slug' => 'health-quiz-2024',
                'description' => 'Test your knowledge about health and wellness topics',
                'start_date' => current_time('mysql'),
                'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'max_participants' => 1000,
                'questions_per_quiz' => 5,
                'pass_score' => 3,
                'time_limit' => 600,
                'is_active' => 1,
                'meta_data' => json_encode(array(
                    'shuffle_questions' => true,
                    'shuffle_options' => true,
                    'show_results' => true,
                    'allow_retake' => false
                ))
            )
        );
        
        $campaign_id = $wpdb->insert_id;
        
        // Insert sample questions and options
        $sample_questions = array(
            array(
                'question_text' => 'What is the recommended daily water intake for adults?',
                'question_type' => 'single_choice',
                'difficulty' => 'easy',
                'category' => 'nutrition',
                'explanation' => '8 glasses (64 ounces) of water per day is the general recommendation.',
                'options' => array(
                    array('option_text' => '4 glasses', 'is_correct' => 0),
                    array('option_text' => '8 glasses', 'is_correct' => 1),
                    array('option_text' => '12 glasses', 'is_correct' => 0),
                    array('option_text' => '16 glasses', 'is_correct' => 0)
                )
            ),
            array(
                'question_text' => 'Which vitamins are fat-soluble?',
                'question_type' => 'multiple_choice',
                'difficulty' => 'medium',
                'category' => 'nutrition',
                'explanation' => 'Vitamins A, D, E, and K are fat-soluble vitamins.',
                'options' => array(
                    array('option_text' => 'Vitamin A', 'is_correct' => 1),
                    array('option_text' => 'Vitamin B', 'is_correct' => 0),
                    array('option_text' => 'Vitamin C', 'is_correct' => 0),
                    array('option_text' => 'Vitamin D', 'is_correct' => 1),
                    array('option_text' => 'Vitamin E', 'is_correct' => 1),
                    array('option_text' => 'Vitamin K', 'is_correct' => 1)
                )
            ),
            array(
                'question_text' => 'Regular exercise can help prevent heart disease.',
                'question_type' => 'true_false',
                'difficulty' => 'easy',
                'category' => 'fitness',
                'explanation' => 'Regular physical activity strengthens the heart and reduces risk of cardiovascular disease.',
                'options' => array(
                    array('option_text' => 'True', 'is_correct' => 1),
                    array('option_text' => 'False', 'is_correct' => 0)
                )
            )
        );
        
        foreach ($sample_questions as $question_data) {
            $wpdb->insert(
                $table_prefix . 'questions',
                array(
                    'campaign_id' => $campaign_id,
                    'question_text' => $question_data['question_text'],
                    'question_type' => $question_data['question_type'],
                    'difficulty' => $question_data['difficulty'],
                    'category' => $question_data['category'],
                    'explanation' => $question_data['explanation'],
                    'is_active' => 1
                )
            );
            
            $question_id = $wpdb->insert_id;
            
            foreach ($question_data['options'] as $index => $option) {
                $wpdb->insert(
                    $table_prefix . 'question_options',
                    array(
                        'question_id' => $question_id,
                        'option_text' => $option['option_text'],
                        'is_correct' => $option['is_correct'],
                        'order_index' => $index
                    )
                );
            }
        }
        
        // Insert sample gift
        $wpdb->insert(
            $table_prefix . 'gifts',
            array(
                'campaign_id' => $campaign_id,
                'gift_name' => '50K VND Voucher',
                'gift_type' => 'voucher',
                'gift_value' => '50000 VND',
                'min_score' => 3,
                'max_score' => 10,
                'max_quantity' => 100,
                'current_quantity' => 0,
                'gift_code_prefix' => 'HEALTH50K',
                'is_active' => 1
            )
        );
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'vefify_quiz_version' => VEFIFY_QUIZ_VERSION,
            'vefify_quiz_db_version' => VEFIFY_QUIZ_DB_VERSION,
            'vefify_quiz_settings' => array(
                'enable_guest_participants' => true,
                'require_email' => true,
                'enable_social_sharing' => true,
                'default_theme' => 'default',
                'email_notifications' => true
            )
        );
        
        foreach ($default_options as $option_name => $option_value) {
            if (!get_option($option_name)) {
                add_option($option_name, $option_value);
            }
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        if (strpos($hook_suffix, 'vefify') === false) {
            return;
        }
        
        wp_enqueue_style('vefify-admin', VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/admin.css', array(), VEFIFY_QUIZ_VERSION);
        wp_enqueue_script('vefify-admin', VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), VEFIFY_QUIZ_VERSION, true);
        
        wp_localize_script('vefify-admin', 'vefifyAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_ajax_nonce'),
            'plugin_url' => VEFIFY_QUIZ_PLUGIN_URL
        ));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style('vefify-frontend', VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/frontend.css', array(), VEFIFY_QUIZ_VERSION);
        wp_enqueue_script('vefify-frontend', VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), VEFIFY_QUIZ_VERSION, true);
        
        wp_localize_script('vefify-frontend', 'vefifyFrontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_frontend_nonce')
        ));
    }
    
    /**
     * Get module instance
     */
    public function get_module($module_name) {
        return isset($this->modules[$module_name]) ? $this->modules[$module_name] : null;
    }
    
    /**
     * Get all loaded modules
     */
    public function get_modules() {
        return $this->modules;
    }
}

// Initialize the plugin
function vefify_quiz_init() {
    return Vefify_Quiz_Plugin::get_instance();
}

// Start the plugin
vefify_quiz_init();

// Helper functions for backward compatibility and external access
function vefify_get_campaign($campaign_id) {
    $plugin = vefify_quiz_init();
    $campaign_module = $plugin->get_module('campaigns');
    if ($campaign_module) {
        return $campaign_module->get_model()->get_campaign($campaign_id);
    }
    return null;
}

function vefify_get_campaigns($args = array()) {
    $plugin = vefify_quiz_init();
    $campaign_module = $plugin->get_module('campaigns');
    if ($campaign_module) {
        return $campaign_module->get_model()->get_campaigns($args);
    }
    return array();
}

function vefify_display_quiz($campaign_id, $args = array()) {
    $plugin = vefify_quiz_init();
    $question_module = $plugin->get_module('questions');
    if ($question_module) {
        return $question_module->display_quiz($campaign_id, $args);
    }
    return '<p>Quiz module not available</p>';
}