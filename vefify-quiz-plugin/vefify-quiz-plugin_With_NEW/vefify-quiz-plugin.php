<?php
/**
 * Plugin Name: Vefify Quiz Campaign Manager
 * Description: Advanced quiz campaign management with mobile-first design
 * Version: 1.0.1
 * Author: Vefify Team
 * License: GPL v2 or later
 * Text Domain: vefify-quiz
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('VEFIFY_QUIZ_VERSION', '1.0.1');
define('VEFIFY_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VEFIFY_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VEFIFY_QUIZ_TABLE_PREFIX', 'vefify_');
define('VEFIFY_QUIZ_DB_VERSION', '1.0.1');
define('VEFIFY_QUIZ_OPTION_PREFIX', 'vefify_quiz_');

/**
 * Main Plugin Class - Centralized Architecture
 */
class Vefify_Quiz_Plugin {
    
    private static $instance = null;
    private $modules = array();
    private $admin_menu;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load core components
        $this->load_core_components();
        
        // Load modules
        $this->load_modules();
        
        // Initialize admin menu
        $this->init_admin_menu();
        
        // Register hooks
        $this->register_hooks();
        
        // Initialize shortcodes
        $this->init_shortcodes();
        
        // Load REST API
        $this->init_rest_api();
    }
    
    /**
     * Load core components
     */
    private function load_core_components() {
        // Load admin menu manager
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-admin-menu.php';
        $this->admin_menu = new Vefify_Quiz_Admin_Menu();
        
        // Load utility classes
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-database.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-utilities.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-shortcodes.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-rest-api.php';
    }
    
    /**
     * Load all modules
     */
    private function load_modules() {
        $modules = array(
            'campaigns' => 'Vefify_Campaign_Module',
            'questions' => 'Vefify_Question_Module', 
            'participants' => 'Vefify_Participant_Module',
            'gifts' => 'Vefify_Gift_Module',
            'analytics' => 'Vefify_Analytics_Module',
            'settings' => 'Vefify_Setting_Module'
        );
        
        foreach ($modules as $module_name => $class_name) {
            $module_file = VEFIFY_QUIZ_PLUGIN_DIR . "modules/{$module_name}/class-{$module_name}-module.php";
            
            if (file_exists($module_file)) {
                require_once $module_file;
                
                if (class_exists($class_name)) {
                    $this->modules[$module_name] = $class_name::get_instance();
                }
            }
        }
    }
    
    /**
     * Initialize admin menu
     */
    private function init_admin_menu() {
        if (is_admin()) {
            add_action('admin_menu', array($this->admin_menu, 'register_menus'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        }
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));
        
        // AJAX handlers
        add_action('wp_ajax_vefify_dashboard_data', array($this, 'ajax_dashboard_data'));
        
        // Cleanup hooks
        add_action('vefify_quiz_daily_cleanup', array($this, 'daily_cleanup'));
        add_action('vefify_quiz_weekly_summary', array($this, 'weekly_summary'));
    }
    
    /**
     * Initialize shortcodes
     */
    private function init_shortcodes() {
        $shortcodes = new Vefify_Quiz_Shortcodes();
        $shortcodes->register_all();
    }
    
    /**
     * Initialize REST API
     */
    private function init_rest_api() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        $rest_api = new Vefify_Quiz_Rest_API();
        $rest_api->register_routes();
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        if (strpos($hook_suffix, 'vefify') === false) {
            return;
        }
        
        wp_enqueue_style(
            'vefify-admin', 
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            VEFIFY_QUIZ_VERSION
        );
        
        wp_enqueue_script(
            'vefify-admin', 
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/admin.js', 
            array('jquery', 'wp-util'), 
            VEFIFY_QUIZ_VERSION, 
            true
        );
        
        wp_localize_script('vefify-admin', 'vefifyAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('vefify/v1/'),
            'nonce' => wp_create_nonce('vefify_admin_nonce'),
            'pluginUrl' => VEFIFY_QUIZ_PLUGIN_URL,
            'strings' => array(
                'loading' => esc_html__('Loading...', 'vefify-quiz'),
                'error' => esc_html__('An error occurred', 'vefify-quiz'),
                'success' => esc_html__('Success!', 'vefify-quiz'),
                'confirmDelete' => esc_html__('Are you sure you want to delete this item?', 'vefify-quiz')
            )
        ));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on pages with quiz shortcodes
        if (is_singular() && $this->has_quiz_shortcode()) {
            wp_enqueue_style(
                'vefify-frontend', 
                VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/frontend.css', 
                array(), 
                VEFIFY_QUIZ_VERSION
            );
            
            wp_enqueue_script(
                'vefify-frontend', 
                VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/frontend.js', 
                array('jquery'), 
                VEFIFY_QUIZ_VERSION, 
                true
            );
            
            wp_localize_script('vefify-frontend', 'vefifyFrontend', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'restUrl' => rest_url('vefify/v1/'),
                'nonce' => wp_create_nonce('vefify_frontend_nonce'),
                'strings' => array(
                    'loading' => esc_html__('Loading...', 'vefify-quiz'),
                    'error' => esc_html__('An error occurred. Please try again.', 'vefify-quiz'),
                    'networkError' => esc_html__('Network error. Please check your connection.', 'vefify-quiz'),
                    'submitSuccess' => esc_html__('Quiz submitted successfully!', 'vefify-quiz'),
                    'alreadyParticipated' => esc_html__('You have already participated in this campaign.', 'vefify-quiz')
                )
            ));
        }
    }
    
    /**
     * Check if current page has quiz shortcodes
     */
    private function has_quiz_shortcode() {
        global $post;
        
        if (!is_a($post, 'WP_Post')) {
            return false;
        }
        
        $shortcodes = array('vefify_quiz', 'vefify_campaign', 'vefify_quiz_question');
        
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Check for transient notices
        $notice = get_transient('vefify_admin_notice');
        
        if ($notice) {
            echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible">';
            echo '<p>' . esc_html($notice['message']) . '</p>';
            echo '</div>';
            
            delete_transient('vefify_admin_notice');
        }
        
        // Check for activation success
        if (get_option('vefify_quiz_activated')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<h3>🎉 ' . esc_html__('Vefify Quiz Plugin Activated!', 'vefify-quiz') . '</h3>';
            echo '<p>' . esc_html__('Database tables created successfully. You can now create your first campaign.', 'vefify-quiz') . '</p>';
            echo '<p><a href="' . admin_url('admin.php?page=vefify-campaigns&action=new') . '" class="button button-primary">' . esc_html__('Create First Campaign', 'vefify-quiz') . '</a></p>';
            echo '</div>';
            
            delete_option('vefify_quiz_activated');
        }
    }
    
    /**
     * AJAX handler for dashboard data
     */
    public function ajax_dashboard_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $data = array();
        
        // Get data from each module
        foreach ($this->modules as $module_name => $module) {
            if (method_exists($module, 'get_module_analytics')) {
                $data[$module_name] = $module->get_module_analytics();
            }
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Daily cleanup routine
     */
    public function daily_cleanup() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Clean up expired sessions
        $deleted_sessions = $wpdb->query("
            DELETE FROM {$table_prefix}quiz_sessions 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) 
            AND is_completed = 0
        ");
        
        // Clean up old analytics data
        $deleted_analytics = $wpdb->query("
            DELETE FROM {$table_prefix}analytics 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        
        error_log("Vefify Quiz: Daily cleanup - Deleted {$deleted_sessions} sessions, {$deleted_analytics} analytics");
    }
    
    /**
     * Weekly summary
     */
    public function weekly_summary() {
        // Generate weekly summary report
        $summary = $this->generate_weekly_summary();
        
        // Email to admin
        $admin_email = get_option('admin_email');
        $subject = 'Vefify Quiz - Weekly Summary';
        $message = $this->format_weekly_summary($summary);
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Generate weekly summary data
     */
    private function generate_weekly_summary() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        return $wpdb->get_row("
            SELECT 
                COUNT(*) as total_participants,
                COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed_quizzes,
                AVG(score) as average_score,
                COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as gifts_awarded
            FROM {$table_prefix}quiz_users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", ARRAY_A);
    }
    
    /**
     * Format weekly summary email
     */
    private function format_weekly_summary($summary) {
        $message = "Weekly Quiz Summary:\n\n";
        $message .= "Total Participants: " . number_format($summary['total_participants']) . "\n";
        $message .= "Completed Quizzes: " . number_format($summary['completed_quizzes']) . "\n";
        $message .= "Average Score: " . number_format($summary['average_score'], 1) . "\n";
        $message .= "Gifts Awarded: " . number_format($summary['gifts_awarded']) . "\n";
        
        return $message;
    }
    
    /**
     * Get module instance
     */
    public function get_module($module_name) {
        return isset($this->modules[$module_name]) ? $this->modules[$module_name] : null;
    }
    
    /**
     * Get all modules
     */
    public function get_modules() {
        return $this->modules;
    }
    
    /**
     * Get admin menu instance
     */
    public function get_admin_menu() {
        return $this->admin_menu;
    }
}

/**
 * Plugin Installer Class - Fixed Database Issues
 */
class Vefify_Quiz_Installer {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        try {
            $installer = new self();
            
            // Create/update database tables
            $installer->create_tables();
            
            // Insert sample data only on fresh install
            if (!get_option('vefify_quiz_installed')) {
                $installer->insert_sample_data();
                update_option('vefify_quiz_installed', true);
            }
            
            // Set default options
            $installer->set_default_options();
            
            // Create directories
            $installer->create_directories();
            
            // Schedule events
            $installer->schedule_events();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Set activation flag
            update_option('vefify_quiz_activated', true);
            
            error_log('Vefify Quiz Plugin activated successfully');
            
        } catch (Exception $e) {
            error_log('Vefify Quiz Plugin activation failed: ' . $e->getMessage());
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Plugin activation failed: ' . esc_html($e->getMessage()));
        }
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('vefify_quiz_daily_cleanup');
        wp_clear_scheduled_hook('vefify_quiz_weekly_summary');
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables - FIXED VERSION
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Check if tables need creation/update
        $installed_version = get_option('vefify_quiz_db_version', '0');
        
        if (version_compare($installed_version, VEFIFY_QUIZ_DB_VERSION, '>=')) {
            return;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // FIXED: Consistent table structure
        $tables = array(
            'campaigns' => "CREATE TABLE {$table_prefix}campaigns (
                id int(11) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                slug varchar(255) NOT NULL,
                description text,
                start_date datetime NOT NULL,
                end_date datetime NOT NULL,
                is_active tinyint(1) DEFAULT 1,
                max_participants int(11) DEFAULT NULL,
                allow_retake tinyint(1) DEFAULT 0,
                questions_per_quiz int(11) DEFAULT 5,
                time_limit int(11) DEFAULT NULL,
                pass_score int(11) DEFAULT 3,
                meta_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_active_campaigns (is_active, start_date, end_date),
                UNIQUE KEY idx_slug (slug)
            ) $charset_collate;",
            
            'questions' => "CREATE TABLE {$table_prefix}questions (
                id int(11) NOT NULL AUTO_INCREMENT,
                campaign_id int(11) DEFAULT NULL,
                question_text text NOT NULL,
                question_type enum('multiple_choice', 'multiple_select', 'true_false') DEFAULT 'multiple_choice',
                category varchar(100) DEFAULT NULL,
                difficulty enum('easy', 'medium', 'hard') DEFAULT 'medium',
                points int(11) DEFAULT 1,
                explanation text,
                is_active tinyint(1) DEFAULT 1,
                order_index int(11) DEFAULT 0,
                meta_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_campaign_questions (campaign_id, is_active),
                KEY idx_category (category)
            ) $charset_collate;",
            
            'question_options' => "CREATE TABLE {$table_prefix}question_options (
                id int(11) NOT NULL AUTO_INCREMENT,
                question_id int(11) NOT NULL,
                option_text text NOT NULL,
                is_correct tinyint(1) DEFAULT 0,
                order_index int(11) DEFAULT 0,
                explanation text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_question_options (question_id),
                FOREIGN KEY (question_id) REFERENCES {$table_prefix}questions(id) ON DELETE CASCADE
            ) $charset_collate;",
            
            'gifts' => "CREATE TABLE {$table_prefix}gifts (
                id int(11) NOT NULL AUTO_INCREMENT,
                campaign_id int(11) NOT NULL,
                gift_name varchar(255) NOT NULL,
                gift_type enum('voucher', 'discount', 'product', 'points') NOT NULL,
                gift_value varchar(100) NOT NULL,
                gift_description text,
                min_score int(11) NOT NULL DEFAULT 0,
                max_score int(11) DEFAULT NULL,
                max_quantity int(11) DEFAULT NULL,
                used_count int(11) DEFAULT 0,
                gift_code_prefix varchar(20) DEFAULT NULL,
                api_endpoint varchar(255) DEFAULT NULL,
                api_params longtext,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_campaign_gifts (campaign_id, is_active),
                KEY idx_score_range (min_score, max_score),
                FOREIGN KEY (campaign_id) REFERENCES {$table_prefix}campaigns(id) ON DELETE CASCADE
            ) $charset_collate;",
            
            // FIXED: Unified participants table (was quiz_users)
            'participants' => "CREATE TABLE {$table_prefix}participants (
                id int(11) NOT NULL AUTO_INCREMENT,
                campaign_id int(11) NOT NULL,
                session_id varchar(100) NOT NULL,
                full_name varchar(255) NOT NULL,
                phone_number varchar(20) NOT NULL,
                province varchar(100) DEFAULT NULL,
                pharmacy_code varchar(50) DEFAULT NULL,
                email varchar(255) DEFAULT NULL,
                score int(11) DEFAULT 0,
                total_questions int(11) DEFAULT 0,
                completion_time int(11) DEFAULT NULL,
                gift_id int(11) DEFAULT NULL,
                gift_code varchar(100) DEFAULT NULL,
                gift_status enum('none', 'assigned', 'claimed', 'expired') DEFAULT 'none',
                gift_response longtext,
                ip_address varchar(45) DEFAULT NULL,
                user_agent text,
                started_at datetime DEFAULT NULL,
                completed_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_campaign_phone (campaign_id, phone_number),
                KEY idx_session (session_id),
                KEY idx_phone_lookup (phone_number),
                KEY idx_completion (completed_at),
                FOREIGN KEY (campaign_id) REFERENCES {$table_prefix}campaigns(id) ON DELETE CASCADE,
                FOREIGN KEY (gift_id) REFERENCES {$table_prefix}gifts(id) ON DELETE SET NULL
            ) $charset_collate;",
            
            'quiz_sessions' => "CREATE TABLE {$table_prefix}quiz_sessions (
                id int(11) NOT NULL AUTO_INCREMENT,
                session_id varchar(100) NOT NULL,
                participant_id int(11) NOT NULL,
                campaign_id int(11) NOT NULL,
                current_question int(11) DEFAULT 0,
                questions_data longtext,
                answers_data longtext,
                time_remaining int(11) DEFAULT NULL,
                is_completed tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_session (session_id),
                KEY idx_participant_session (participant_id, session_id),
                FOREIGN KEY (participant_id) REFERENCES {$table_prefix}participants(id) ON DELETE CASCADE,
                FOREIGN KEY (campaign_id) REFERENCES {$table_prefix}campaigns(id) ON DELETE CASCADE
            ) $charset_collate;",
            
            'analytics' => "CREATE TABLE {$table_prefix}analytics (
                id int(11) NOT NULL AUTO_INCREMENT,
                campaign_id int(11) NOT NULL,
                event_type enum('view', 'start', 'question_answer', 'complete', 'gift_claim') NOT NULL,
                participant_id int(11) DEFAULT NULL,
                session_id varchar(100) DEFAULT NULL,
                question_id int(11) DEFAULT NULL,
                event_data longtext,
                ip_address varchar(45) DEFAULT NULL,
                user_agent text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_campaign_analytics (campaign_id, event_type),
                KEY idx_event_tracking (event_type, created_at),
                FOREIGN KEY (campaign_id) REFERENCES {$table_prefix}campaigns(id) ON DELETE CASCADE
            ) $charset_collate;"
        );
        
        // Create tables
        foreach ($tables as $table_name => $sql) {
            $result = dbDelta($sql);
            
            if ($wpdb->last_error) {
                throw new Exception("Failed to create table {$table_name}: " . $wpdb->last_error);
            }
        }
        
        // Update database version
        update_option('vefify_quiz_db_version', VEFIFY_QUIZ_DB_VERSION);
        
        error_log('Vefify Quiz: Database tables created/updated successfully');
    }
    
    /**
     * Insert sample data
     */
    public function insert_sample_data() {
        // Use the Database class for consistent data insertion
        $database = new Vefify_Quiz_Database();
        $database->insert_sample_data();
    }
    
    /**
     * Set default options
     */
    public function set_default_options() {
        $defaults = array(
            'vefify_quiz_version' => VEFIFY_QUIZ_VERSION,
            'vefify_quiz_db_version' => VEFIFY_QUIZ_DB_VERSION,
            'vefify_quiz_settings' => array(
                'default_questions_per_quiz' => 5,
                'default_time_limit' => 600,
                'default_pass_score' => 3,
                'enable_retakes' => false,
                'phone_required' => true,
                'province_required' => true,
                'pharmacy_code_required' => false,
                'enable_analytics' => true,
                'enable_gift_api' => false,
                'max_participants_per_campaign' => 10000
            )
        );
        
        foreach ($defaults as $option_name => $option_value) {
            if (!get_option($option_name)) {
                add_option($option_name, $option_value);
            }
        }
    }
    
    /**
     * Create directories
     */
    public function create_directories() {
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/vefify-quiz/';
        
        $directories = array(
            $plugin_dir,
            $plugin_dir . 'exports/',
            $plugin_dir . 'imports/',
            $plugin_dir . 'logs/',
            $plugin_dir . 'cache/'
        );
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
                file_put_contents($dir . '.htaccess', "deny from all\n");
                file_put_contents($dir . 'index.php', "<?php // Silence is golden");
            }
        }
    }
    
    /**
     * Schedule events
     */
    public function schedule_events() {
        if (!wp_next_scheduled('vefify_quiz_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'vefify_quiz_daily_cleanup');
        }
        
        if (!wp_next_scheduled('vefify_quiz_weekly_summary')) {
            wp_schedule_event(time(), 'weekly', 'vefify_quiz_weekly_summary');
        }
    }
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, array('Vefify_Quiz_Installer', 'activate'));
register_deactivation_hook(__FILE__, array('Vefify_Quiz_Installer', 'deactivate'));

/**
 * Initialize the plugin
 */
function vefify_quiz_init() {
    return Vefify_Quiz_Plugin::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'vefify_quiz_init');

/**
 * Plugin upgrade check
 */
add_action('admin_init', function() {
    $installed_version = get_option('vefify_quiz_db_version', '0');
    
    if (version_compare($installed_version, VEFIFY_QUIZ_DB_VERSION, '<')) {
        $installer = new Vefify_Quiz_Installer();
        $installer->create_tables();
    }
});