<?php
/**
 * Plugin Name: Vefify Quiz Campaign Manager
 * Description: Advanced quiz campaign management with mobile-first design
 * Version: 1.1.0
 * Author: Vefify Team
 * License: GPL v2 or later
 * Text Domain: vefify-quiz
 * 
 * ENHANCED VERSION - Complete Question Flow Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants - FIXED: Add missing constants
define('VEFIFY_QUIZ_VERSION', '1.1.0');
define('VEFIFY_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VEFIFY_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VEFIFY_QUIZ_PLUGIN_FILE', __FILE__); // ADD: Missing constant
define('VEFIFY_QUIZ_TABLE_PREFIX', 'vefify_');
define('VEFIFY_QUIZ_DB_VERSION', '1.1.0');

/**
 * Main Plugin Class - ENHANCED VERSION
 */
class Vefify_Quiz_Plugin {
    
    private static $instance = null;
    private $database;
    private $analytics;
    private $shortcodes;
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
     * Initialize WordPress hooks - ENHANCED
     */
    private function init_hooks() {
        // Keep ALL your existing hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // WordPress hooks
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Keep your existing AJAX hooks
        add_action('wp_ajax_vefify_refresh_dashboard', array($this, 'ajax_refresh_dashboard'));
        add_action('wp_ajax_vefify_health_check', array($this, 'ajax_health_check'));
        
        // ADD: New AJAX hooks for enhanced features
        add_action('wp_ajax_vefify_get_component_status', array($this, 'ajax_get_component_status'));
        
        // Emergency validation helper (keep existing)
        add_action('admin_menu', array($this, 'add_emergency_validation_menu'), 100);
        
        // ADD: Frontend script enqueuing
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * Load dependencies in correct order - ENHANCED
     */
    private function load_dependencies() {
        // Core classes first - ENHANCED to include new files
        $core_files = array(
            'includes/class-database.php',
            'includes/class-enhanced-database.php',        // ADD: Enhanced database
            'includes/class-utilities.php', 
            'includes/class-analytics.php',
            'includes/class-validation-helper.php',
            'includes/class-shortcodes.php',               // Keep existing shortcode
            'includes/class-enhanced-shortcodes.php'       // ADD: Enhanced shortcodes
        );
        
        // Keep your EXACT existing foreach loop - it's perfect!
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
        
        // Load modules (keep your existing method exactly as is)
        $this->load_modules();
    }
    
    /**
     * Load modules with proper error handling - KEEP EXACTLY AS IS
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
     * Initialize core components - ENHANCED with fallback system
     */
    private function init_components() {
        // Initialize ENHANCED database with fallback to original
        if (class_exists('Vefify_Enhanced_Database')) {
            try {
                $this->database = new Vefify_Enhanced_Database();
                error_log("Vefify Quiz: Enhanced Database initialized successfully");
            } catch (Exception $e) {
                error_log("Vefify Quiz: Enhanced Database failed, falling back to original: " . $e->getMessage());
                $this->init_fallback_database();
            }
        } else {
            // Use your original database initialization
            $this->init_fallback_database();
        }
        
        // Keep your EXACT existing analytics initialization - it's perfect!
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
        
        // ADD: Initialize enhanced shortcodes with fallback to original
        $this->init_enhanced_shortcodes();
    }
    
    /**
     * NEW: Fallback database initialization (extracted from your existing logic)
     */
    private function init_fallback_database() {
        if (class_exists('Vefify_Quiz_Database')) {
            try {
                $this->database = new Vefify_Quiz_Database();
                error_log("Vefify Quiz: Original Database initialized successfully");
            } catch (Exception $e) {
                error_log("Vefify Quiz: Database initialization failed: " . $e->getMessage());
                $this->database = null;
            }
        } else {
            error_log("Vefify Quiz: Database class not found");
            $this->database = null;
        }
    }
    
    /**
     * NEW: Enhanced shortcodes initialization with fallback
     */
    private function init_enhanced_shortcodes() {
        // Try enhanced shortcodes first, fallback to original
        if (class_exists('Vefify_Enhanced_Shortcodes')) {
            try {
                // Enhanced shortcodes will automatically extend your original shortcodes
                $this->shortcodes = new Vefify_Enhanced_Shortcodes();
                error_log("Vefify Quiz: Enhanced Shortcodes initialized successfully");
            } catch (Exception $e) {
                error_log("Vefify Quiz: Enhanced Shortcodes failed, using original: " . $e->getMessage());
                $this->init_fallback_shortcodes();
            }
        } else {
            // Use original shortcodes
            $this->init_fallback_shortcodes();
        }
    }
    
    /**
     * NEW: Fallback shortcodes initialization
     */
    private function init_fallback_shortcodes() {
        if (class_exists('Vefify_Quiz_Shortcodes')) {
            try {
                $this->shortcodes = new Vefify_Quiz_Shortcodes();
                error_log("Vefify Quiz: Original Shortcodes initialized successfully");
            } catch (Exception $e) {
                error_log("Vefify Quiz: Shortcodes initialization failed: " . $e->getMessage());
                $this->shortcodes = null;
            }
        } else {
            error_log("Vefify Quiz: Shortcodes class not found");
            $this->shortcodes = null;
        }
    }
    
    /**
     * Plugin activation - ENHANCED
     */
    public function activate() {
        try {
            error_log('Vefify Quiz: Starting activation...');
            
            // Create database tables with enhanced version if available
            if ($this->database) {
                $this->database->create_tables();
                error_log('Vefify Quiz: Database tables created');
                
                // Insert sample data if method exists
                if (method_exists($this->database, 'insert_sample_data')) {
                    $this->database->insert_sample_data();
                    error_log('Vefify Quiz: Sample data inserted');
                }
            } else {
                error_log('Vefify Quiz: Database not available during activation');
            }
            
            // Set activation flags (keep your existing logic)
            update_option('vefify_quiz_activated', true);
            update_option('vefify_quiz_version', VEFIFY_QUIZ_VERSION);
            
            // NEW: Set enhanced version flag
            update_option('vefify_quiz_enhanced', class_exists('Vefify_Enhanced_Database') && class_exists('Vefify_Enhanced_Shortcodes'));
            
            // Clear cache (keep your existing)
            wp_cache_flush();
            
            error_log('Vefify Quiz: Activation completed successfully');
            
        } catch (Exception $e) {
            error_log('Vefify Quiz: Activation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Plugin deactivation - KEEP EXACTLY AS IS
     */
    public function deactivate() {
        delete_option('vefify_quiz_activated');
        wp_cache_flush();
        error_log('Vefify Quiz: Plugin deactivated');
    }
    
    /**
     * Initialize plugin - KEEP EXACTLY AS IS
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('vefify-quiz', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Check if database needs update
        $this->maybe_update_database();
    }
    
    /**
     * Admin initialization - KEEP EXACTLY AS IS
     */
    public function admin_init() {
        // Check if database tables exist (with null check)
        if ($this->database) {
            try {
                $tables_exist = $this->database->tables_exist();
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
     * CENTRALIZED ADMIN MENU - KEEP EXACTLY AS IS
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
     * Add emergency validation menu (uses external class) - KEEP EXACTLY AS IS
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
     * Display dashboard with analytics - ENHANCED
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
     * SAFE Get quick stats for dashboard - KEEP YOUR LOGIC, ADD ENHANCED FEATURES
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
     * Get fallback quick stats - ENHANCED with component status
     */
    private function get_fallback_quick_stats() {
        $enhanced_status = get_option('vefify_quiz_enhanced', false);
        
        return array(
            array(
                'title' => 'System Status',
                'value' => $enhanced_status ? 'Enhanced' : 'Standard',
                'icon' => $enhanced_status ? '🚀' : '⚙️',
                'color' => $enhanced_status ? 'green' : 'blue'
            ),
            array(
                'title' => 'Database',
                'value' => $this->database ? (($this->database instanceof Vefify_Enhanced_Database) ? 'Enhanced' : 'Standard') : 'Not Ready',
                'icon' => '🗄️',
                'color' => $this->database ? 'green' : 'orange'
            ),
            array(
                'title' => 'Shortcodes',
                'value' => $this->shortcodes ? (($this->shortcodes instanceof Vefify_Enhanced_Shortcodes) ? 'Enhanced' : 'Standard') : 'Not Ready',
                'icon' => '🎯',
                'color' => $this->shortcodes ? 'green' : 'orange'
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
     * Display simple dashboard - ENHANCED with status banner
     */
    private function display_simple_dashboard($analytics_data) {
        // Enhanced status banner
        $enhanced_status = get_option('vefify_quiz_enhanced', false);
        
        echo '<div style="background: ' . ($enhanced_status ? '#d4edda' : '#fff3cd') . '; border: 1px solid ' . ($enhanced_status ? '#c3e6cb' : '#ffeaa7') . '; padding: 15px; border-radius: 8px; margin: 20px 0;">';
        echo '<h3 style="margin: 0 0 10px 0;">' . ($enhanced_status ? '🚀 Enhanced Features Active' : '⚙️ Standard Mode') . '</h3>';
        echo '<p style="margin: 0;">';
        if ($enhanced_status) {
            echo 'Your quiz system is running with enhanced features including real-time progress tracking, advanced scoring, and gift management.';
        } else {
            echo 'Your quiz system is running in standard mode. Add the enhanced files to unlock advanced features.';
        }
        echo '</p>';
        echo '</div>';
        
        // Keep your EXACT existing dashboard display code below this
        
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
        
        // Module Analytics Grid (keep your existing code)
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
        
        // Enhanced Emergency actions
        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f56e28;">';
        echo '<h3>🚨 System Tools</h3>';
        echo '<p>System management and troubleshooting tools:</p>';
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
        
        // System Validation
        echo '<div>';
        echo '<a href="' . admin_url('admin.php?page=vefify-emergency-validation') . '" class="button button-primary" style="width: 100%;">🔧 System Validation</a>';
        echo '<p style="font-size: 0.9em; color: #666; margin: 5px 0 0 0;">Check system health and fix issues</p>';
        echo '</div>';
        
        // Database Tools
        if ($this->database) {
            echo '<div>';
            echo '<a href="' . wp_nonce_url(add_query_arg('recreate_tables', '1'), 'recreate_tables') . '" class="button" style="width: 100%;">🗄️ Recreate Database</a>';
            echo '<p style="font-size: 0.9em; color: #666; margin: 5px 0 0 0;">Rebuild database tables</p>';
            echo '</div>';
        }
        
        // Cache Management
        echo '<div>';
        echo '<a href="' . add_query_arg('clear_cache', '1') . '" class="button" style="width: 100%;">🗑️ Clear Cache</a>';
        echo '<p style="font-size: 0.9em; color: #666; margin: 5px 0 0 0;">Clear all plugin caches</p>';
        echo '</div>';
        
        // Component Status
        echo '<div>';
        echo '<button type="button" class="button" onclick="vefifyShowComponentStatus()" style="width: 100%;">📊 Component Status</button>';
        echo '<p style="font-size: 0.9em; color: #666; margin: 5px 0 0 0;">View detailed component info</p>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        
        // Add JavaScript for component status
        ?>
        <script>
        function vefifyShowComponentStatus() {
            jQuery.post(ajaxurl, {
                action: 'vefify_get_component_status',
                nonce: '<?php echo wp_create_nonce('vefify_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    let html = '<h3>📊 Component Status</h3><table class="wp-list-table widefat fixed striped">';
                    html += '<thead><tr><th>Component</th><th>Class</th><th>Status</th><th>Enhanced</th></tr></thead><tbody>';
                    
                    Object.keys(response.data).forEach(function(key) {
                        const component = response.data[key];
                        if (typeof component === 'object' && component.class) {
                            html += '<tr>';
                            html += '<td><strong>' + key.charAt(0).toUpperCase() + key.slice(1) + '</strong></td>';
                            html += '<td><code>' + component.class + '</code></td>';
                            html += '<td><span style="color: ' + (component.status === 'Active' ? 'green' : 'red') + ';">● ' + component.status + '</span></td>';
                            html += '<td>' + (component.enhanced ? '🚀 Yes' : '⚙️ Standard') + '</td>';
                            html += '</tr>';
                        }
                    });
                    
                    html += '</tbody></table>';
                    
                    // Show in modal-like overlay
                    jQuery('body').append('<div id="vefify-status-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999; display: flex; align-items: center; justify-content: center;"><div style="background: white; padding: 30px; border-radius: 8px; max-width: 80%; max-height: 80%; overflow: auto;">' + html + '<br><button onclick="jQuery(\'#vefify-status-modal\').remove()" class="button">Close</button></div></div>');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * NEW: Get component status for debugging
     */
    public function get_component_status() {
        return array(
            'database' => array(
                'class' => $this->database ? get_class($this->database) : 'Not initialized',
                'enhanced' => $this->database instanceof Vefify_Enhanced_Database,
                'status' => $this->database ? 'Active' : 'Failed'
            ),
            'shortcodes' => array(
                'class' => $this->shortcodes ? get_class($this->shortcodes) : 'Not initialized',
                'enhanced' => $this->shortcodes instanceof Vefify_Enhanced_Shortcodes,
                'status' => $this->shortcodes ? 'Active' : 'Failed'
            ),
            'analytics' => array(
                'class' => $this->analytics ? get_class($this->analytics) : 'Not initialized',
                'status' => $this->analytics ? 'Active' : 'Failed'
            ),
            'modules' => array_map(function($module) {
                return array(
                    'class' => $module ? get_class($module) : 'Not loaded',
                    'status' => $module ? 'Active' : 'Failed'
                );
            }, $this->modules)
        );
    }
    
    /**
     * NEW: Enhanced frontend script enqueuing
     */
    public function enqueue_frontend_scripts() {
        global $post;
        
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'vefify_quiz') ||
            has_shortcode($post->post_content, 'vefify_simple_test') ||
            has_shortcode($post->post_content, 'vefify_test')
        )) {
            
            wp_enqueue_script('jquery');
            
            // Choose JavaScript file based on available enhanced version
            $js_file = 'assets/js/frontend-quiz.js'; // Default/fallback
            
            if (file_exists(VEFIFY_QUIZ_PLUGIN_DIR . 'assets/js/enhanced-frontend-quiz.js')) {
                $js_file = 'assets/js/enhanced-frontend-quiz.js'; // Enhanced
                error_log("Vefify Quiz: Loading enhanced frontend script");
            } else {
                error_log("Vefify Quiz: Loading original frontend script");
            }
            
            wp_enqueue_script(
                'vefify-quiz-frontend',
                VEFIFY_QUIZ_PLUGIN_URL . $js_file,
                array('jquery'),
                VEFIFY_QUIZ_VERSION,
                true
            );
            
            // Enqueue CSS (keep your existing CSS)
            wp_enqueue_style(
                'vefify-quiz-frontend',
                VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/frontend-quiz.css',
                array(),
                VEFIFY_QUIZ_VERSION
            );
            
            // Enhanced localization with feature detection
            wp_localize_script('vefify-quiz-frontend', 'vefifyAjax', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vefify_quiz_nonce'),
                'isEnhanced' => $this->shortcodes instanceof Vefify_Enhanced_Shortcodes,
                'version' => VEFIFY_QUIZ_VERSION,
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
                'strings' => array(
                    'loading' => __('Loading...', 'vefify-quiz'),
                    'error' => __('An error occurred', 'vefify-quiz'),
                    'success' => __('Success!', 'vefify-quiz'),
                    'timeUp' => __('Time is up!', 'vefify-quiz'),
                    'confirmFinish' => __('Are you sure you want to finish the quiz?', 'vefify-quiz'),
                    'answerSaved' => __('Answer saved', 'vefify-quiz'),
                    'quizCompleted' => __('Quiz completed successfully!', 'vefify-quiz')
                )
            ));
        }
    }
    
    /**
     * NEW: AJAX - Get component status
     */
    public function ajax_get_component_status() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'vefify_admin_nonce')) {
            wp_send_json_error('Unauthorized');
        }
        
        wp_send_json_success($this->get_component_status());
    }
    
    // ... (KEEP ALL YOUR OTHER EXISTING METHODS EXACTLY AS IS)
    
    /**
     * Safe helper methods - KEEP EXACTLY AS IS
     */
    private function get_recent_activity_safe() {
        return array();
    }
    
    private function get_trends_data_safe() {
        return array();
    }
    
    /**
     * Get fallback analytics data - KEEP EXACTLY AS IS
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
     * Module display methods - KEEP ALL EXACTLY AS IS
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
        // Settings page - always available - ENHANCED with component info
        echo '<div class="wrap">';
        echo '<h1>⚙️ Settings</h1>';
        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        echo '<h3>Plugin Information</h3>';
        echo '<p><strong>Version:</strong> ' . VEFIFY_QUIZ_VERSION . '</p>';
        echo '<p><strong>Database Status:</strong> ' . ($this->database ? '✅ Connected (' . get_class($this->database) . ')' : '❌ Not Connected') . '</p>';
        echo '<p><strong>Shortcodes Status:</strong> ' . ($this->shortcodes ? '✅ Active (' . get_class($this->shortcodes) . ')' : '❌ Not Active') . '</p>';
        echo '<p><strong>Analytics Status:</strong> ' . ($this->analytics ? '✅ Active' : '❌ Not Active') . '</p>';
        echo '<p><strong>Enhanced Features:</strong> ' . (get_option('vefify_quiz_enhanced', false) ? '🚀 Active' : '⚙️ Standard Mode') . '</p>';
        echo '<h3>Emergency Actions</h3>';
        echo '<a href="' . admin_url('admin.php?page=vefify-emergency-validation') . '" class="button button-primary">Run System Validation</a>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Display placeholder for inactive modules - KEEP EXACTLY AS IS
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
     * Display module error - KEEP EXACTLY AS IS
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
     * Utility methods - KEEP EXACTLY AS IS + ADD SHORTCODE GETTER
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
    
    // NEW: Get shortcodes instance
    public function get_shortcodes() {
        return $this->shortcodes;
    }
    
    /**
     * Enqueue admin assets - KEEP EXACTLY AS IS
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
     * AJAX: Refresh dashboard data - KEEP EXACTLY AS IS
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
     * AJAX: Health check - KEEP EXACTLY AS IS
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
     * Maybe update database - KEEP EXACTLY AS IS
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

// Initialize the plugin - KEEP EXACTLY AS IS
function vefify_quiz_init() {
    return Vefify_Quiz_Plugin::get_instance();
}

// Start the plugin - KEEP EXACTLY AS IS
add_action('plugins_loaded', 'vefify_quiz_init');

// Global functions for backward compatibility - KEEP EXACTLY AS IS + ADD SHORTCODE GETTER
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

// NEW: Get shortcodes instance
function vefify_quiz_get_shortcodes() {
    $plugin = Vefify_Quiz_Plugin::get_instance();
    return $plugin->get_shortcodes();
}

/**
 * ENHANCED ADMIN NOTICES - Show enhancement status
 */
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        $plugin = Vefify_Quiz_Plugin::get_instance();
        
        if (method_exists($plugin, 'get_component_status')) {
            $status = $plugin->get_component_status();
            
            $enhanced_db = $status['database']['enhanced'] ?? false;
            $enhanced_sc = $status['shortcodes']['enhanced'] ?? false;
            
            if ($enhanced_db && $enhanced_sc) {
                // Show success notice only once
                if (!get_transient('vefify_enhanced_notice_shown')) {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>🚀 Vefify Quiz Enhanced:</strong> All enhanced features are active and running perfectly!</p>';
                    echo '</div>';
                    set_transient('vefify_enhanced_notice_shown', true, DAY_IN_SECONDS);
                }
            } else if ($enhanced_db || $enhanced_sc) {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p><strong>⚡ Vefify Quiz:</strong> Partially enhanced. ';
                if (!$enhanced_db) echo 'Enhanced database not loaded. ';
                if (!$enhanced_sc) echo 'Enhanced shortcodes not loaded. ';
                echo 'Check the <a href="' . admin_url('admin.php?page=vefify-emergency-validation') . '">System Status</a> page.</p>';
                echo '</div>';
            }
        }
    }
});

/**
 * ENHANCED DEBUG OUTPUT
 */
add_action('wp_footer', function() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $plugin = Vefify_Quiz_Plugin::get_instance();
        if (method_exists($plugin, 'get_component_status')) {
            $status = $plugin->get_component_status();
            echo '<script>console.log("🎯 Vefify Quiz Enhanced Status:", ' . json_encode($status) . ');</script>';
        }
    }
});

/**
 * KEEP YOUR EXISTING DEBUG FUNCTIONS - ALL EXACTLY AS IS
 */

// Keep your existing simple test shortcode
function vefify_simple_test_shortcode($atts) {
    $atts = shortcode_atts(array(
        'campaign_id' => '1'
    ), $atts);
    
    return '<div style="background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>🧪 Simple Test Working!</h3>
                <p>Campaign ID: ' . esc_html($atts['campaign_id']) . '</p>
                <p>Current time: ' . current_time('Y-m-d H:i:s') . '</p>
                <p>If you see this, shortcodes are working!</p>
                <p>Plugin File Constant: ' . (defined('VEFIFY_QUIZ_PLUGIN_FILE') ? '✅ Defined' : '❌ Not Defined') . '</p>
            </div>';
}
add_shortcode('vefify_simple_test', 'vefify_simple_test_shortcode');
?>