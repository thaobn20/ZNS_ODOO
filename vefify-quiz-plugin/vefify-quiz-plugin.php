<?php
/**
 * Plugin Name: Vefify Quiz Campaign Manager
 * Description: Advanced quiz campaign management with mobile-first design
 * Version: 1.0.0
 * Author: Vefify Team
 * License: GPL v2 or later
 * Text Domain: vefify-quiz
 * 
 * EMERGENCY FIXED VERSION - NO DUPLICATE CLASSES
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
 * Main Plugin Class - EMERGENCY FIXED VERSION
 */
class Vefify_Quiz_Plugin {
    
    private static $instance = null;
    private $database;
    private $analytics;
    private $modules = array();
    private $admin_initialized = false;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
        $this->init_components();
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
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX hooks
        add_action('wp_ajax_vefify_refresh_dashboard', array($this, 'ajax_refresh_dashboard'));
        add_action('wp_ajax_vefify_health_check', array($this, 'ajax_health_check'));
        
        // Emergency validation helper
        add_action('admin_menu', array($this, 'add_emergency_validation_menu'), 100);
    }

    /**
     * Load dependencies in correct order
     */
    private function load_dependencies() {
        // Core classes first
        $core_files = array(
            'includes/class-database.php',
            'includes/class-utilities.php', 
            'includes/class-analytics.php',
            'includes/class-validation-helper.php',
            'includes/class-analytics-summaries.php',
           # 'includes/class-database-migration.php'
            'includes/admin/debug-database.php'
           # 'modules/settings/class-form-settings.php'
        );
        
        foreach ($core_files as $file) {
            $path = VEFIFY_QUIZ_PLUGIN_DIR . $file;
            if (file_exists($path)) {
                try {
                    require_once $path;
                    error_log("Vefify Quiz: Loaded {$file}");
                } catch (Exception $e) {
                    error_log("Vefify Quiz: Error loading {$file}: " . $e->getMessage());
                }
            } else {
                error_log("Vefify Quiz: Core file missing: {$file}");
            }
        }
        
        // Load modules
        $this->load_modules();
    }
    
    /**
     * Load modules with proper error handling
     */
    private function load_modules() {
        $modules = array(
            'questions' => 'modules/questions/class-question-module.php',
            'campaigns' => 'modules/campaigns/class-campaign-module.php',
            'gifts' => 'modules/gifts/class-gift-module.php',
            'participants' => 'modules/participants/class-participant-module.php',
            'analytics' => 'modules/analytics/class-analytics-module.php'
        );
        
        foreach ($modules as $module_key => $file_path) {
            $full_path = VEFIFY_QUIZ_PLUGIN_DIR . $file_path;
            
            if (file_exists($full_path)) {
                try {
                    require_once $full_path;
                    
                    // Map to correct class names
                    $class_map = array(
                        'questions' => 'Vefify_Question_Module',
                        'campaigns' => 'Vefify_Campaign_Module', 
                        'gifts' => 'Vefify_Gift_Module',
                        'participants' => 'Vefify_Participant_Module',
                        'analytics' => 'Vefify_Analytics_Module'
                    );
                    
                    if (isset($class_map[$module_key])) {
                        $class_name = $class_map[$module_key];
                        
                        if (class_exists($class_name)) {
                            $this->modules[$module_key] = $class_name::get_instance();
                            error_log("Vefify Quiz: Module {$module_key} loaded successfully");
                        } else {
                            error_log("Vefify Quiz: Class {$class_name} not found for module {$module_key}");
                            $this->modules[$module_key] = null;
                        }
                    }
                    
                } catch (Exception $e) {
                    error_log("Vefify Quiz: Error loading module {$module_key}: " . $e->getMessage());
                    $this->modules[$module_key] = null;
                }
            } else {
                error_log("Vefify Quiz: Module file not found: {$file_path}");
                $this->modules[$module_key] = null;
            }
        }
    }
    
    /**
     * Initialize core components
     */
    private function init_components() {
        // Initialize database with error handling
        if (class_exists('Vefify_Quiz_Database')) {
            try {
                $this->database = new Vefify_Quiz_Database();
                error_log("Vefify Quiz: Database initialized successfully");
            } catch (Exception $e) {
                error_log("Vefify Quiz: Database initialization failed: " . $e->getMessage());
                $this->database = null;
            }
        } else {
            error_log("Vefify Quiz: Database class not found");
            $this->database = null;
        }
        
        // Initialize analytics with error handling
        if (class_exists('Vefify_Quiz_Module_Analytics')) {
            try {
                $this->analytics = new Vefify_Quiz_Module_Analytics($this->database);
                error_log("Vefify Quiz: Analytics initialized successfully");
            } catch (Exception $e) {
                error_log("Vefify Quiz: Analytics initialization failed: " . $e->getMessage());
                $this->analytics = null;
            }
        } else {
            error_log("Vefify Quiz: Analytics class not found");
            $this->analytics = null;
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        try {
            error_log('Vefify Quiz: Starting activation...');
            
            // Create database tables
            if ($this->database) {
                $this->database->create_tables();
                error_log('Vefify Quiz: Database tables created');
                
                // Insert sample data
                $this->database->insert_sample_data();
                error_log('Vefify Quiz: Sample data inserted');
            } else {
                error_log('Vefify Quiz: Database not available during activation');
            }
            
            // Set activation flag
            update_option('vefify_quiz_activated', true);
            update_option('vefify_quiz_version', VEFIFY_QUIZ_VERSION);
            
            // Clear cache
            wp_cache_flush();
            
            error_log('Vefify Quiz: Activation completed successfully');
            
        } catch (Exception $e) {
            error_log('Vefify Quiz: Activation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        delete_option('vefify_quiz_activated');
        wp_cache_flush();
        error_log('Vefify Quiz: Plugin deactivated');
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('vefify-quiz', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Check if database needs update
        $this->maybe_update_database();
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Check if database tables exist (with null check)
        if ($this->database) {
            try {
                $missing_tables = $this->database->verify_tables();
                if (!empty($missing_tables)) {
                    add_action('admin_notices', function() use ($missing_tables) {
                        echo '<div class="notice notice-error"><p>';
                        echo 'Vefify Quiz: Missing database tables: ' . implode(', ', $missing_tables);
                        echo ' <a href="' . admin_url('admin.php?page=vefify-emergency-validation&recreate_tables=1') . '">Recreate Tables</a>';
                        echo '</p></div>';
                    });
                }
            } catch (Exception $e) {
                error_log('Vefify Quiz: Error checking database tables: ' . $e->getMessage());
            }
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo 'Vefify Quiz: Database not initialized. ';
                echo '<a href="' . admin_url('admin.php?page=vefify-emergency-validation') . '">Check System Status</a>';
                echo '</p></div>';
            });
        }
        
        // Handle table recreation
        if (isset($_GET['recreate_tables']) && current_user_can('manage_options')) {
            if ($this->database) {
                try {
                    $this->database->create_tables();
                    wp_redirect(admin_url('admin.php?page=vefify-dashboard'));
                    exit;
                } catch (Exception $e) {
                    error_log('Vefify Quiz: Error recreating tables: ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * CENTRALIZED ADMIN MENU - SAFE VERSION
     */
    public function add_admin_menu() {
        if ($this->admin_initialized) {
            return;
        }
        
        try {
            // Main menu page - Dashboard
            add_menu_page(
                'Vefify Quiz',
                'Vefify Quiz',
                'manage_options',
                'vefify-dashboard',
                array($this, 'display_dashboard'),
                'dashicons-forms',
                30
            );
            
            // Sub-menu pages for each module
            $submenus = array(
                array(
                    'page_title' => 'Dashboard',
                    'menu_title' => '📊 Dashboard', 
                    'slug' => 'vefify-dashboard',
                    'callback' => array($this, 'display_dashboard')
                ),
                array(
                    'page_title' => 'Campaigns',
                    'menu_title' => '📋 Campaigns',
                    'slug' => 'vefify-campaigns', 
                    'callback' => array($this, 'display_campaigns')
                ),
                array(
                    'page_title' => 'Questions',
                    'menu_title' => '❓ Questions',
                    'slug' => 'vefify-questions',
                    'callback' => array($this, 'display_questions')
                ),
                array(
                    'page_title' => 'Gifts',
                    'menu_title' => '🎁 Gifts',
                    'slug' => 'vefify-gifts',
                    'callback' => array($this, 'display_gifts')
                ),
                array(
                    'page_title' => 'Participants', 
                    'menu_title' => '👥 Participants',
                    'slug' => 'vefify-participants',
                    'callback' => array($this, 'display_participants')
                ),
                array(
                    'page_title' => 'Analytics',
                    'menu_title' => '📈 Analytics', 
                    'slug' => 'vefify-analytics',
                    'callback' => array($this, 'display_analytics')
                ),
                array(
                    'page_title' => 'Settings',
                    'menu_title' => '⚙️ Settings',
                    'slug' => 'vefify-settings', 
                    'callback' => array($this, 'display_settings')
                )
            );
            
            foreach ($submenus as $submenu) {
                add_submenu_page(
                    'vefify-dashboard',
                    $submenu['page_title'],
                    $submenu['menu_title'],
                    'manage_options',
                    $submenu['slug'],
                    $submenu['callback']
                );
            }
            
            $this->admin_initialized = true;
            
        } catch (Exception $e) {
            error_log('Vefify Quiz: Error adding admin menu: ' . $e->getMessage());
        }
    }
    
    /**
     * Add emergency validation menu (uses external class)
     */
    public function add_emergency_validation_menu() {
        if (class_exists('Vefify_Quiz_Validation_Helper')) {
            add_submenu_page(
                'vefify-dashboard',
                'Emergency Validation',
                '🚨 System Check',
                'manage_options',
                'vefify-emergency-validation',
                array('Vefify_Quiz_Validation_Helper', 'display_validation_page')
            );
        }
    }
    
    /**
     * Display dashboard with analytics - SAFE VERSION
     */
    public function display_dashboard() {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">📊 Vefify Quiz Dashboard</h1>';
        echo '<hr class="wp-header-end">';
        
        // System status check
        $status_messages = array();
        
        if (!$this->database) {
            $status_messages[] = array(
                'type' => 'error',
                'message' => 'Database not initialized. <a href="' . admin_url('admin.php?page=vefify-emergency-validation') . '">Check System Status</a>'
            );
        }
        
        if (!$this->analytics) {
            $status_messages[] = array(
                'type' => 'warning', 
                'message' => 'Analytics not initialized. Some features may not work properly.'
            );
        }
        
        // Display status messages
        foreach ($status_messages as $message) {
            echo '<div class="notice notice-' . $message['type'] . '"><p>' . $message['message'] . '</p></div>';
        }
        
        // Get analytics data safely
        $analytics_data = array();
        
        if ($this->analytics) {
            try {
                // Get module analytics
                $analytics_data['modules'] = $this->analytics->get_all_module_analytics();
                
                // Get quick stats
                $analytics_data['quick_stats'] = $this->get_quick_stats_safe();
                
                // Get recent activity
                $analytics_data['recent_activity'] = $this->get_recent_activity_safe();
                
                // Get trends
                $analytics_data['trends'] = $this->get_trends_data_safe();
                
            } catch (Exception $e) {
                error_log('Vefify Quiz: Error getting analytics data: ' . $e->getMessage());
                $analytics_data = $this->get_fallback_analytics();
            }
        } else {
            $analytics_data = $this->get_fallback_analytics();
        }
        
        // Simple dashboard display
        $this->display_simple_dashboard($analytics_data);
        
        echo '</div>';
    }
    
    /**
     * SAFE Get quick stats for dashboard
     */
    private function get_quick_stats_safe() {
        if (!$this->database) {
            return $this->get_fallback_quick_stats();
        }
        
        try {
            global $wpdb;
            
            $campaigns_table = $this->database->get_table_name('campaigns');
            $participants_table = $this->database->get_table_name('participants');
            $questions_table = $this->database->get_table_name('questions');
            
            // Check if tables exist before querying
            $tables_exist = true;
            foreach (array($campaigns_table, $participants_table, $questions_table) as $table) {
                if (!$table || !$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
                    $tables_exist = false;
                    break;
                }
            }
            
            if (!$tables_exist) {
                return $this->get_fallback_quick_stats();
            }
            
            return array(
                array(
                    'title' => 'Total Campaigns',
                    'value' => $wpdb->get_var("SELECT COUNT(*) FROM {$campaigns_table}") ?: 0,
                    'icon' => '📋',
                    'color' => 'blue'
                ),
                array(
                    'title' => 'Active Questions', 
                    'value' => $wpdb->get_var("SELECT COUNT(*) FROM {$questions_table} WHERE is_active = 1") ?: 0,
                    'icon' => '❓',
                    'color' => 'green'
                ),
                array(
                    'title' => 'Total Participants',
                    'value' => $wpdb->get_var("SELECT COUNT(*) FROM {$participants_table}") ?: 0,
                    'icon' => '👥', 
                    'color' => 'purple'
                ),
                array(
                    'title' => 'Completed Today',
                    'value' => $wpdb->get_var("SELECT COUNT(*) FROM {$participants_table} WHERE DATE(created_at) = CURDATE() AND quiz_status = 'completed'") ?: 0,
                    'icon' => '✅',
                    'color' => 'orange'
                )
            );
            
        } catch (Exception $e) {
            error_log('Vefify Quiz: Error getting quick stats: ' . $e->getMessage());
            return $this->get_fallback_quick_stats();
        }
    }
    
    /**
     * Get fallback quick stats
     */
    private function get_fallback_quick_stats() {
        return array(
            array(
                'title' => 'System Status',
                'value' => 'Initializing...',
                'icon' => '⚙️',
                'color' => 'blue'
            ),
            array(
                'title' => 'Database',
                'value' => $this->database ? 'Connected' : 'Not Ready',
                'icon' => '🗄️',
                'color' => $this->database ? 'green' : 'orange'
            ),
            array(
                'title' => 'Analytics',
                'value' => $this->analytics ? 'Active' : 'Loading...',
                'icon' => '📊',
                'color' => $this->analytics ? 'green' : 'orange'
            ),
            array(
                'title' => 'Plugin Version',
                'value' => VEFIFY_QUIZ_VERSION,
                'icon' => '🔧',
                'color' => 'purple'
            )
        );
    }
    
    /**
     * Display simple dashboard
     */
    private function display_simple_dashboard($analytics_data) {
        // Quick Stats Cards
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">';
        
        $quick_stats = $analytics_data['quick_stats'] ?? array();
        foreach ($quick_stats as $stat) {
            $color_map = array(
                'blue' => '#007cba',
                'green' => '#46b450', 
                'purple' => '#826eb4',
                'orange' => '#f56e28'
            );
            
            $border_color = $color_map[$stat['color']] ?? '#007cba';
            
            echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid ' . $border_color . ';">';
            echo '<div style="display: flex; align-items: center;">';
            echo '<div style="font-size: 2em; margin-right: 15px;">' . $stat['icon'] . '</div>';
            echo '<div>';
            echo '<div style="font-size: 2em; font-weight: bold; margin-bottom: 5px;">' . esc_html($stat['value']) . '</div>';
            echo '<div style="color: #666; font-size: 0.9em;">' . esc_html($stat['title']) . '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Module Analytics Grid
        echo '<h2>Module Status</h2>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">';
        
        $modules = $analytics_data['modules'] ?? array();
        foreach ($modules as $module_key => $module_data) {
            echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 1px solid #e1e1e1;">';
            echo '<div style="display: flex; align-items: center; margin-bottom: 15px;">';
            echo '<div style="font-size: 2em; margin-right: 15px;">' . $module_data['icon'] . '</div>';
            echo '<div>';
            echo '<h3 style="margin: 0; font-size: 1.2em;">' . esc_html($module_data['title']) . '</h3>';
            echo '<p style="margin: 5px 0 0 0; color: #666; font-size: 0.9em;">' . esc_html($module_data['description']) . '</p>';
            echo '</div>';
            echo '</div>';
            
            // Stats
            if (!empty($module_data['stats'])) {
                echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin: 15px 0;">';
                foreach ($module_data['stats'] as $stat) {
                    echo '<div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 6px;">';
                    echo '<div style="font-size: 1.2em; font-weight: bold; color: #007cba;">' . esc_html($stat['value']) . '</div>';
                    echo '<div style="font-size: 0.8em; color: #666; margin-top: 5px;">' . esc_html($stat['label']) . '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
            
            // Quick Actions
            if (!empty($module_data['quick_actions'])) {
                echo '<div style="margin-top: 15px; text-align: right;">';
                foreach ($module_data['quick_actions'] as $action) {
                    $button_class = strpos($action['class'], 'primary') !== false ? 'button-primary' : 'button-secondary';
                    echo '<a href="' . esc_url($action['url']) . '" class="button ' . $button_class . '" style="margin-left: 8px;">' . esc_html($action['label']) . '</a>';
                }
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        
        // Emergency actions
        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f56e28;">';
        echo '<h3>🚨 Emergency Actions</h3>';
        echo '<p>If you\'re experiencing issues, use these emergency tools:</p>';
        echo '<a href="' . admin_url('admin.php?page=vefify-emergency-validation') . '" class="button button-primary">🔧 System Validation</a> ';
        
        if ($this->database) {
            echo '<a href="' . wp_nonce_url(add_query_arg('recreate_tables', '1'), 'recreate_tables') . '" class="button">🗄️ Recreate Database</a> ';
        }
        
        echo '<a href="' . add_query_arg('clear_cache', '1') . '" class="button">🗑️ Clear Cache</a>';
        echo '</div>';
    }
    
    /**
     * Safe helper methods
     */
    private function get_recent_activity_safe() {
        return array();
    }
    
    private function get_trends_data_safe() {
        return array();
    }
    
    /**
     * Get fallback analytics data
     */
    private function get_fallback_analytics() {
        return array(
            'modules' => array(),
            'quick_stats' => $this->get_fallback_quick_stats(),
            'recent_activity' => array(),
            'trends' => array()
        );
    }
    
    /**
     * Module display methods - route to appropriate modules
     */
    public function display_campaigns() {
        if ($this->has_module('campaigns')) {
            try {
                $this->modules['campaigns']->admin_page_router();
            } catch (Exception $e) {
                $this->display_module_error('Campaigns', $e->getMessage());
            }
        } else {
            $this->display_module_placeholder('Campaigns', 'campaigns');
        }
    }
    
    public function display_questions() {
        if ($this->has_module('questions')) {
            try {
                if (method_exists($this->modules['questions'], 'get_bank')) {
                    $bank = $this->modules['questions']->get_bank();
                    if ($bank && method_exists($bank, 'admin_page_router')) {
                        $bank->admin_page_router();
                    } else {
                        $this->display_module_placeholder('Questions', 'questions');
                    }
                } else {
                    $this->display_module_placeholder('Questions', 'questions');  
                }
            } catch (Exception $e) {
                $this->display_module_error('Questions', $e->getMessage());
            }
        } else {
            $this->display_module_placeholder('Questions', 'questions');
        }
    }
    
    public function display_gifts() {
        if ($this->has_module('gifts')) {
            try {
                $this->modules['gifts']->admin_page_router();
            } catch (Exception $e) {
                $this->display_module_error('Gifts', $e->getMessage());
            }
        } else {
            $this->display_module_placeholder('Gifts', 'gifts');
        }
    }
    
    public function display_participants() {
        if ($this->has_module('participants')) {
            try {
                $this->modules['participants']->admin_page_router();
            } catch (Exception $e) {
                $this->display_module_error('Participants', $e->getMessage());
            }
        } else {
            $this->display_module_placeholder('Participants', 'participants');
        }
    }
    
    public function display_analytics() {
        if ($this->has_module('analytics')) {
            try {
                $this->modules['analytics']->admin_page_router();
            } catch (Exception $e) {
                $this->display_module_error('Analytics', $e->getMessage());
            }
        } else {
            $this->display_module_placeholder('Analytics', 'analytics');
        }
    }
    
    public function display_settings() {
        // Settings page - always available
        echo '<div class="wrap">';
        echo '<h1>⚙️ Settings</h1>';
        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        echo '<h3>Plugin Information</h3>';
        echo '<p><strong>Version:</strong> ' . VEFIFY_QUIZ_VERSION . '</p>';
        echo '<p><strong>Database Status:</strong> ' . ($this->database ? '✅ Connected' : '❌ Not Connected') . '</p>';
        echo '<p><strong>Analytics Status:</strong> ' . ($this->analytics ? '✅ Active' : '❌ Not Active') . '</p>';
        echo '<h3>Emergency Actions</h3>';
        echo '<a href="' . admin_url('admin.php?page=vefify-emergency-validation') . '" class="button button-primary">Run System Validation</a>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Display placeholder for inactive modules
     */
    private function display_module_placeholder($module_name, $module_key) {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($module_name) . '</h1>';
        echo '<div class="notice notice-warning"><p>';
        echo "The {$module_name} module is not loaded. ";
        
        if (isset($this->modules[$module_key]) && $this->modules[$module_key] === null) {
            echo 'There was an error loading this module. Check the error log for details.';
        } else {
            echo 'The module file may be missing.';
        }
        
        echo '</p></div>';
        echo '<p><a href="' . admin_url('admin.php?page=vefify-emergency-validation') . '" class="button">Check System Status</a></p>';
        echo '</div>';
    }
    
    /**
     * Display module error
     */
    private function display_module_error($module_name, $error_message) {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($module_name) . '</h1>';
        echo '<div class="notice notice-error"><p>';
        echo "Error in {$module_name} module: " . esc_html($error_message);
        echo '</p></div>';
        echo '<p><a href="' . admin_url('admin.php?page=vefify-emergency-validation') . '" class="button">Check System Status</a></p>';
        echo '</div>';
    }
    
    /**
     * Utility methods
     */
    public function has_module($module_key) {
        return isset($this->modules[$module_key]) && $this->modules[$module_key] !== null;
    }
    
    public function get_module($module_key) {
        return $this->modules[$module_key] ?? null;
    }
    
    public function get_database() {
        return $this->database;
    }
    
    public function get_analytics() {
        return $this->analytics;
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'vefify') === false) {
            return;
        }
        
        // Basic styles
        wp_add_inline_style('admin-menu', '
            .vefify-dashboard .button-primary { background: #007cba; }
            .vefify-dashboard .notice { margin: 15px 0; }
        ');
    }
    
    /**
     * AJAX: Refresh dashboard data
     */
    public function ajax_refresh_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        try {
            $data = array(
                'quick_stats' => $this->get_quick_stats_safe(),
                'modules' => $this->analytics ? $this->analytics->get_all_module_analytics() : array()
            );
            
            wp_send_json_success($data);
        } catch (Exception $e) {
            wp_send_json_error('Failed to refresh data: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Health check
     */
    public function ajax_health_check() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $health_data = array(
            'Database' => array(
                'status' => $this->database ? 'good' : 'error',
                'message' => $this->database ? '✓ Connected' : '❌ Not Connected'
            ),
            'Analytics' => array(
                'status' => $this->analytics ? 'good' : 'warning',
                'message' => $this->analytics ? '✓ Active' : '⚠️ Not Active'
            )
        );
        
        wp_send_json_success($health_data);
    }
    
    /**
     * Maybe update database
     */
    private function maybe_update_database() {
        $current_version = get_option('vefify_quiz_db_version', '0.0.0');
        
        if (version_compare($current_version, VEFIFY_QUIZ_DB_VERSION, '<')) {
            if ($this->database) {
                try {
                    $this->database->create_tables();
                    update_option('vefify_quiz_db_version', VEFIFY_QUIZ_DB_VERSION);
                } catch (Exception $e) {
                    error_log('Vefify Quiz: Database update failed: ' . $e->getMessage());
                }
            }
        }
    }
}
// Include form settings
if (is_admin()) {
    require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/settings/class-form-settings.php';
    new Vefify_Form_Settings();
}

// Initialize the plugin
function vefify_quiz_init() {
    return Vefify_Quiz_Plugin::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'vefify_quiz_init');

// Global functions for backward compatibility
function vefify_quiz() {
    return Vefify_Quiz_Plugin::get_instance();
}

function vefify_quiz_get_database() {
    $plugin = Vefify_Quiz_Plugin::get_instance();
    return $plugin->get_database();
}

function vefify_quiz_get_module($module_name) {
    $plugin = Vefify_Quiz_Plugin::get_instance();
    return $plugin->get_module($module_name);
}

add_action('init', function() {
	remove_shortcode('vefify_quiz');
    // Admin form settings
    if (is_admin()) {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/settings/class-form-settings.php';
        if (class_exists('Vefify_Form_Settings')) {
            new Vefify_Form_Settings();
        }
    }
    
    // Frontend enhancements
    if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'frontend/class-quiz-shortcode.php';
        if (class_exists('Vefify_Quiz_Shortcode')) {
            new Vefify_Quiz_Shortcode();
        }
        
        // Frontend AJAX module
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/frontend/class-frontend-module.php';
    }
});