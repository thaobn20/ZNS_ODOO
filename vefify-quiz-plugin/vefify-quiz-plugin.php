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
            'gifts' => 'modules/gifts/class-gift-module.php',
            'analytics' => 'modules/analytics/class-analytics-module.php'
        );
        
        foreach ($module_files as $module => $file) {
            $full_path = VEFIFY_QUIZ_PLUGIN_DIR . $file;
            if (file_exists($full_path)) {
                require_once $full_path;
                error_log("Vefify Quiz: Loaded module file {$file}");
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
        if (class_exists('Vefify_Quiz_Utilities')) {
            $this->utilities = new Vefify_Quiz_Utilities();
        }
        
        // Initialize database handler
        if (class_exists('Vefify_Quiz_Database')) {
            $this->database = new Vefify_Quiz_Database();
        }
        
        // Initialize admin menu (only in admin)
        if (is_admin() && class_exists('Vefify_Quiz_Admin_Menu')) {
            $this->admin_menu = new Vefify_Quiz_Admin_Menu();
        }
        
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
     * Load and initialize modules - FIXED class names and error handling
     */
    private function load_modules() {
        $module_classes = array(
            'campaigns' => 'Vefify_Campaign_Module',
            'questions' => 'Vefify_Question_Module', 
            'participants' => 'Vefify_Participant_Module',
            'gifts' => 'Vefify_Gift_Module',
            'analytics' => 'Vefify_Analytics_Module'
        );
        
        foreach ($module_classes as $module => $class) {
            try {
                if (class_exists($class)) {
                    $this->modules[$module] = $class::get_instance();
                    error_log("Vefify Quiz: Module {$module} ({$class}) loaded successfully");
                } else {
                    error_log("Vefify Quiz: Module class not found: {$class}");
                    // Create a placeholder to prevent errors
                    $this->modules[$module] = null;
                }
            } catch (Exception $e) {
                error_log("Vefify Quiz: Error loading module {$module}: " . $e->getMessage());
                $this->modules[$module] = null;
            }
        }
        
        error_log("Vefify Quiz: Loaded modules: " . implode(', ', array_keys($this->modules)));
    }
    
    /**
     * CRITICAL: Get module instance - This method was missing!
     */
    public function get_module($module_name) {
        if (isset($this->modules[$module_name])) {
            return $this->modules[$module_name];
        }
        
        error_log("Vefify Quiz: Module '{$module_name}' not found. Available modules: " . implode(', ', array_keys($this->modules)));
        return null;
    }
    
    /**
     * Get all loaded modules
     */
    public function get_modules() {
        return $this->modules;
    }
    
    /**
     * Check if module is loaded
     */
    public function has_module($module_name) {
        return isset($this->modules[$module_name]) && $this->modules[$module_name] !== null;
    }
    
    /**
     * Plugin activation - FIXED
     */
    public function activate() {
        try {
            // Use the installer from your current file structure
            if (class_exists('Vefify_Quiz_Installer')) {
                Vefify_Quiz_Installer::activate();
            } else {
                error_log('Vefify Quiz: Installer class not found - using manual setup');
                // Fallback activation
                $this->create_basic_tables();
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
     * Fallback table creation
     */
    private function create_basic_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Basic campaigns table
        $sql = "CREATE TABLE IF NOT EXISTS {$table_prefix}campaigns (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            questions_per_quiz int(11) DEFAULT 5,
            pass_score int(11) DEFAULT 3,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('Vefify Quiz: Basic tables created via fallback method');
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
        
        // Initialize any post-load functionality
        do_action('vefify_quiz_loaded');
    }
    
    /**
     * Initialize admin menu
     */
    public function init_admin_menu() {
        if (!is_admin()) {
            return;
        }
        
        if ($this->admin_menu && method_exists($this->admin_menu, 'init')) {
            $this->admin_menu->init();
        }
    }
    
    /**
     * Initialize REST API
     */
    public function init_rest_api() {
        if ($this->rest_api && method_exists($this->rest_api, 'init')) {
            $this->rest_api->init();
        }
        
        // Also initialize module REST endpoints
        foreach ($this->modules as $module) {
            if ($module && method_exists($module, 'init_rest_api')) {
                $module->init_rest_api();
            }
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue if shortcode is present
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vefify_quiz')) {
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
            
            // Localize script with data
            wp_localize_script('vefify-quiz-frontend', 'vefifyQuiz', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'restUrl' => rest_url('vefify/v1/'),
                'nonce' => wp_create_nonce('vefify_quiz_nonce')
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
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_admin_nonce')
        ));
    }
    
    /**
     * Render quiz shortcode
     */
    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 1,
            'template' => 'default'
        ), $atts);
        
        // Try to use the campaigns module if available
        if ($this->has_module('campaigns')) {
            $campaigns_module = $this->get_module('campaigns');
            if (method_exists($campaigns_module, 'render_frontend_quiz')) {
                return $campaigns_module->render_frontend_quiz($atts);
            }
        }
        
        // Fallback rendering
        return '<div class="vefify-quiz-placeholder">
            <h3>🎯 Quiz Loading...</h3>
            <p>Campaign ID: ' . esc_html($atts['campaign_id']) . '</p>
            <p><em>Module system is initializing. Please check that all module files are properly uploaded.</em></p>
        </div>';
    }
    
    /**
     * AJAX handler for dashboard data
     */
    public function ajax_dashboard_data() {
        check_ajax_referer('vefify_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Get basic stats
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $stats = array(
            'campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}campaigns") ?: 0,
            'participants' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}quiz_users") ?: 0,
            'questions' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}questions") ?: 0,
            'completed_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}quiz_users WHERE DATE(completed_at) = CURDATE()") ?: 0
        );
        
        wp_send_json_success($stats);
    }
    
    /**
     * Get plugin info for debugging
     */
    public function get_plugin_info() {
        return array(
            'version' => VEFIFY_QUIZ_VERSION,
            'loaded_modules' => array_keys($this->modules),
            'active_modules' => array_keys(array_filter($this->modules)),
            'has_admin_menu' => !empty($this->admin_menu),
            'has_database' => !empty($this->database)
        );
    }
}

// Initialize the plugin
function vefify_quiz_init() {
    return Vefify_Quiz_Plugin::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'vefify_quiz_init');

// Activation/Deactivation hooks - MUST be in main plugin file
register_activation_hook(__FILE__, array('Vefify_Quiz_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('Vefify_Quiz_Plugin', 'deactivate'));

// Debug helper function
if (defined('WP_DEBUG') && WP_DEBUG) {
    function vefify_debug_info() {
        if (current_user_can('manage_options') && isset($_GET['vefify_debug'])) {
            $plugin = Vefify_Quiz_Plugin::get_instance();
            $info = $plugin->get_plugin_info();
            
            echo '<div style="position: fixed; top: 50px; right: 10px; background: white; border: 2px solid #ccc; padding: 15px; z-index: 9999; font-size: 12px; max-width: 300px;">';
            echo '<h4>🔧 Vefify Quiz Debug</h4>';
            echo '<strong>Version:</strong> ' . $info['version'] . '<br>';
            echo '<strong>Loaded Modules:</strong><br>';
            foreach ($info['loaded_modules'] as $module) {
                $status = in_array($module, $info['active_modules']) ? '✅' : '❌';
                echo "&nbsp;&nbsp;{$status} {$module}<br>";
            }
            echo '<strong>Admin Menu:</strong> ' . ($info['has_admin_menu'] ? '✅' : '❌') . '<br>';
            echo '<strong>Database:</strong> ' . ($info['has_database'] ? '✅' : '❌') . '<br>';
            echo '<a href="' . add_query_arg('vefify_debug', '1') . '" style="font-size: 10px;">Refresh</a>';
            echo '</div>';
        }
    }
    add_action('wp_footer', 'vefify_debug_info');
    add_action('admin_footer', 'vefify_debug_info');
}