<?php
/**
 * Plugin Name: Vefify Quiz Campaign Manager
 * Description: Advanced quiz campaign management with mobile-first design
 * Version: 1.0.0
 * Author: Vefify Team
 * License: GPL v2 or later
 * Text Domain: vefify-quiz
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('VEFIFY_QUIZ_VERSION', '1.0.0');
define('VEFIFY_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VEFIFY_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VEFIFY_QUIZ_TABLE_PREFIX', 'vefify_');
define('VEFIFY_QUIZ_DB_VERSION', '1.0.0');

/**
 * Main Plugin Class - Centralized Coordinator
 */
class Vefify_Quiz_Plugin {
    
    private static $instance = null;
    private $admin_menu;
    private $database;
    private $utilities;
    private $rest_api;
    private $modules = array();
    
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
     * Constructor - Initialize plugin
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->init_components();
        $this->load_modules();
    }
    
    /**
     * Load all dependency files
     */
    private function load_dependencies() {
        // Core classes - FIXED to match existing class names
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-database.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-utilities.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-installer.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-error-handler.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-shortcodes.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-analytics.php';
        
        // Admin components (only load in admin)
        if (is_admin()) {
            require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-admin-menu.php';
        }
        
        // Module files - check if they exist before loading
        $module_files = array(
            'campaigns' => 'modules/campaigns/class-campaign-module.php',
            'questions' => 'modules/questions/class-question-module.php',
            'participants' => 'modules/participants/class-participant-module.php',
            'gifts' => 'modules/gifts/class-gift-module.php'
            // Note: analytics module will be created separately
        );
        
        foreach ($module_files as $module => $file) {
            $full_path = VEFIFY_QUIZ_PLUGIN_DIR . $file;
            if (file_exists($full_path)) {
                require_once $full_path;
            } else {
                error_log("Vefify Quiz: Module file not found: {$file}");
            }
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // WordPress hooks
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'init_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('rest_api_init', array($this, 'init_rest_api'));
        
        // AJAX hooks
        add_action('wp_ajax_vefify_dashboard_data', array($this, 'ajax_dashboard_data'));
        add_action('wp_ajax_nopriv_vefify_quiz_data', array($this, 'ajax_quiz_data'));
        
        // Shortcode
        add_shortcode('vefify_quiz', array($this, 'render_quiz_shortcode'));
    }
    
    /**
     * Initialize core components - FIXED for existing classes
     */
    private function init_components() {
        // Initialize utilities first (needed by other components)
        $this->utilities = new Vefify_Quiz_Utilities();
        
        // Initialize database handler
        $this->database = new Vefify_Quiz_Database();
        
        // Initialize installer - use correct class name
        $this->installer = new Installer();
        
        // Initialize REST API - check if class exists
        if (class_exists('Vefify_Quiz_REST_API')) {
            $this->rest_api = new Vefify_Quiz_REST_API();
        }
        
        // Error handler - handle namespace issue
        if (class_exists('VefifyQuiz\\ErrorHandler')) {
            $this->error_handler = new VefifyQuiz\ErrorHandler();
        } elseif (class_exists('Vefify_Quiz_Error_Handler')) {
            $this->error_handler = new Vefify_Quiz_Error_Handler();
        }
    }
    
    /**
     * Load and initialize modules - FIXED class names
     */
    private function load_modules() {
        $module_classes = array(
            'campaigns' => 'Vefify_Campaign_Module',
            'questions' => 'Vefify_Question_Module', 
            'participants' => 'Vefify_Participant_Module',
            'gifts' => 'Vefify_Gift_Module'
            // analytics module to be added later
        );
        
        foreach ($module_classes as $module => $class) {
            if (class_exists($class)) {
                $this->modules[$module] = $class::get_instance();
                error_log("Vefify Quiz: {$class} loaded successfully");
            } else {
                error_log("Vefify Quiz: Module class not found: {$class}");
                // Create placeholder module
                $this->modules[$module] = new stdClass();
            }
        }
    }
    
    /**
     * Plugin activation - FIXED
     */
    public function activate() {
        try {
            // Use the correct installer class
            if (class_exists('Installer')) {
                $installer = new Installer();
                $installer->create_tables();
                $installer->insert_sample_data();
            } else {
                error_log('Vefify Quiz: Installer class not found');
            }
            
            // Clear any cached data
            wp_cache_flush();
            
            // Schedule events
            if (!wp_next_scheduled('vefify_quiz_daily_cleanup')) {
                wp_schedule_event(time(), 'daily', 'vefify_quiz_daily_cleanup');
            }
            
            error_log('Vefify Quiz Plugin activated successfully');
            
        } catch (Exception $e) {
            error_log('Vefify Quiz Plugin activation failed: ' . $e->getMessage());
            // Don't deactivate - just log the error
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('vefify_quiz_daily_cleanup');
        wp_clear_scheduled_hook('vefify_quiz_weekly_summary');
        
        // Clear cache
        wp_cache_flush();
        
        error_log('Vefify Quiz Plugin deactivated');
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('vefify-quiz', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize shortcodes if class exists
        if (class_exists('Vefify_Quiz_Shortcodes')) {
            new Vefify_Quiz_Shortcodes();
        }
    }
    
    /**
     * Initialize admin menu (FIXED IMPLEMENTATION)
     */
    public function init_admin_menu() {
        if (!is_admin()) {
            return;
        }
        
        // Initialize admin menu class
        $this->admin_menu = new Vefify_Quiz_Admin_Menu();
        $this->admin_menu->register_menus();
    }
    
    /**
     * Initialize REST API - FIXED
     */
    public function init_rest_api() {
        if ($this->rest_api && method_exists($this->rest_api, 'register_routes')) {
            $this->rest_api->register_routes();
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load on pages with quiz shortcode
        if (is_singular() && has_shortcode(get_post()->post_content, 'vefify_quiz')) {
            wp_enqueue_style(
                'vefify-quiz-frontend',
                VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                VEFIFY_QUIZ_VERSION
            );
            
            wp_enqueue_script(
                'vefify-quiz-frontend',
                VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                VEFIFY_QUIZ_VERSION,
                true
            );
            
            wp_localize_script('vefify-quiz-frontend', 'vefifyQuiz', array(
                'restUrl' => rest_url('vefify/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'strings' => array(
                    'loading' => __('Loading...', 'vefify-quiz'),
                    'error' => __('An error occurred. Please try again.', 'vefify-quiz'),
                    'submit_success' => __('Quiz submitted successfully!', 'vefify-quiz')
                )
            ));
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'vefify') === false) {
            return;
        }
        
        wp_enqueue_style(
            'vefify-quiz-admin',
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            VEFIFY_QUIZ_VERSION
        );
        
        wp_enqueue_script(
            'vefify-quiz-admin',
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            VEFIFY_QUIZ_VERSION,
            true
        );
        
        wp_localize_script('vefify-quiz-admin', 'vefifyAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('vefify/v1/'),
            'nonce' => wp_create_nonce('vefify_admin'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'vefify-quiz'),
                'loading' => __('Loading...', 'vefify-quiz'),
                'error' => __('An error occurred. Please try again.', 'vefify-quiz')
            )
        ));
    }
    
    /**
     * AJAX handler for dashboard data
     */
    public function ajax_dashboard_data() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_admin')) {
            wp_send_json_error('Security check failed');
        }
        
        try {
            $analytics = $this->get_module('analytics');
            $data = array(
                'campaigns' => array(
                    'stats' => array(
                        'total_campaigns' => array('value' => $this->get_campaign_count()),
                        'active_campaigns' => array('value' => $this->get_active_campaign_count())
                    )
                ),
                'participants' => array(
                    'stats' => array(
                        'total_participants' => array('value' => $this->get_participant_count())
                    )
                ),
                'questions' => array(
                    'stats' => array(
                        'total_questions' => array('value' => $this->get_question_count())
                    )
                ),
                'gifts' => array(
                    'stats' => array(
                        'distributed_gifts' => array('value' => $this->get_gift_count())
                    )
                )
            );
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to load dashboard data: ' . $e->getMessage());
        }
    }
    
    /**
     * Quiz shortcode handler - FIXED error handling
     */
    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 1,
            'template' => 'mobile'
        ), $atts);
        
        // Validate campaign exists
        global $wpdb;
        $campaign_table = $wpdb->prefix . 'vefify_campaigns';
        
        // Check if table exists first
        if ($wpdb->get_var("SHOW TABLES LIKE '{$campaign_table}'") !== $campaign_table) {
            return $this->render_error_message('Plugin not properly installed. Please deactivate and reactivate the plugin.');
        }
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$campaign_table} WHERE id = %d AND is_active = 1",
            $atts['campaign_id']
        ));
        
        if (!$campaign) {
            return $this->render_error_message('Campaign not found or inactive.');
        }
        
        // Enqueue quiz assets
        $this->enqueue_quiz_assets($campaign);
        
        // Render quiz interface
        return $this->render_quiz_interface($campaign, $atts);
    }
    
    /**
     * Render quiz interface
     */
    private function render_quiz_interface($campaign, $atts) {
        ob_start();
        include VEFIFY_QUIZ_PLUGIN_DIR . 'templates/quiz-interface.php';
        return ob_get_clean();
    }
    
    /**
     * Render error message
     */
    private function render_error_message($message) {
        return sprintf(
            '<div class="vefify-error"><h3>❌ %s</h3><p>%s</p></div>',
            __('Quiz Error', 'vefify-quiz'),
            esc_html($message)
        );
    }
    
    /**
     * Enqueue quiz-specific assets
     */
    private function enqueue_quiz_assets($campaign) {
        wp_enqueue_style('vefify-quiz-mobile', VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/quiz-mobile.css');
        wp_enqueue_script('vefify-quiz-mobile', VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/quiz-mobile.js', array('jquery'));
        
        wp_localize_script('vefify-quiz-mobile', 'vefifyQuizData', array(
            'campaign' => array(
                'id' => $campaign->id,
                'name' => $campaign->name,
                'description' => $campaign->description,
                'questions_per_quiz' => $campaign->questions_per_quiz,
                'time_limit' => $campaign->time_limit,
                'pass_score' => $campaign->pass_score
            ),
            'restUrl' => rest_url('vefify/v1/'),
            'nonce' => wp_create_nonce('wp_rest')
        ));
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
     * Get component instances
     */
    public function get_admin_menu() { return $this->admin_menu; }
    public function get_database() { return $this->database; }
    public function get_utilities() { return $this->utilities; }
    public function get_rest_api() { return $this->rest_api; }
    
    /**
     * Helper methods for dashboard stats
     */
    private function get_campaign_count() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vefify_campaigns");
    }
    
    private function get_active_campaign_count() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vefify_campaigns WHERE is_active = 1");
    }
    
    private function get_participant_count() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vefify_quiz_users");
    }
    
    private function get_question_count() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vefify_questions WHERE is_active = 1");
    }
    
    private function get_gift_count() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vefify_quiz_users WHERE gift_id IS NOT NULL");
    }
}

/**
 * Initialize the plugin
 */
function vefify_quiz_init() {
    return Vefify_Quiz_Plugin::get_instance();
}

// Start the plugin
vefify_quiz_init();

/**
 * Global helper function to get plugin instance
 */
function vefify_quiz() {
    return Vefify_Quiz_Plugin::get_instance();
}

/**
 * Scheduled cleanup tasks
 */
add_action('vefify_quiz_daily_cleanup', function() {
    global $wpdb;
    
    // Clean expired sessions
    $wpdb->query("
        DELETE FROM {$wpdb->prefix}vefify_quiz_sessions 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        AND is_completed = 0
    ");
    
    // Clean old analytics data
    $wpdb->query("
        DELETE FROM {$wpdb->prefix}vefify_analytics 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    
    error_log('Vefify Quiz: Daily cleanup completed');
});

/**
 * Plugin upgrade check
 */
add_action('plugins_loaded', function() {
    $installed_version = get_option('vefify_quiz_db_version', '0');
    if (version_compare($installed_version, VEFIFY_QUIZ_DB_VERSION, '<')) {
        $installer = new Vefify_Quiz_Installer();
        $installer->upgrade_database();
    }
});