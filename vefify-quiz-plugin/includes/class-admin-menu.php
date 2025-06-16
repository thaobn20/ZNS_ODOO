<?php
/**
 * Fixed Admin Menu Registration
 * File: includes/class-admin-menu.php
 * 
 * This centralizes all admin menu registration and ensures proper module loading
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Admin_Menu {
    
    private static $instance = null;
    private $modules = array();
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Register all admin menu items
     */
    public function register_admin_menu() {
        // Main menu page - Dashboard
        add_menu_page(
            'Vefify Quiz',
            'Vefify Quiz', 
            'manage_options',
            'vefify-quiz',
            array($this, 'display_dashboard'),
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/></svg>'),
            30
        );
        
        // 1. Campaign Management
        add_submenu_page(
            'vefify-quiz',
            'Campaign Management',
            '📋 Campaigns',
            'manage_options', 
            'vefify-campaigns',
            array($this, 'display_campaigns')
        );
        
        // 2. Question Bank - THIS WAS MISSING!
        add_submenu_page(
            'vefify-quiz',
            'Question Bank',
            '❓ Questions',
            'manage_options',
            'vefify-questions', 
            array($this, 'display_questions')
        );
        
        // 3. Gift Management
        add_submenu_page(
            'vefify-quiz',
            'Gift Management',
            '🎁 Gifts',
            'manage_options',
            'vefify-gifts',
            array($this, 'display_gifts')
        );
        
        // 4. Participants Management
        add_submenu_page(
            'vefify-quiz',
            'Participants Management', 
            '👥 Participants',
            'manage_options',
            'vefify-participants',
            array($this, 'display_participants')
        );
        
        // 5. Reports & Analytics
        add_submenu_page(
            'vefify-quiz',
            'Reports & Analytics',
            '📊 Reports',
            'manage_options',
            'vefify-reports',
            array($this, 'display_reports')
        );
        
        // 6. Settings
        add_submenu_page(
            'vefify-quiz',
            'Settings',
            '⚙️ Settings',
            'manage_options',
            'vefify-settings',
            array($this, 'display_settings')
        );
    }
    
    /**
     * Display dashboard with analytics summary
     */
    public function display_dashboard() {
        $analytics = $this->get_dashboard_analytics();
        
        ?>
        <div class="wrap">
            <h1>📊 Vefify Quiz Dashboard</h1>
            
            <div class="vefify-dashboard-grid">
                <!-- Quick Stats Cards -->
                <div class="vefify-stats-row">
                    <div class="vefify-stat-card campaigns">
                        <h3>📋 Active Campaigns</h3>
                        <div class="stat-number"><?php echo $analytics['active_campaigns']; ?></div>
                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns'); ?>" class="button">Manage Campaigns</a>
                    </div>
                    
                    <div class="vefify-stat-card questions">
                        <h3>❓ Total Questions</h3>
                        <div class="stat-number"><?php echo $analytics['total_questions']; ?></div>
                        <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Question Bank</a>
                    </div>
                    
                    <div class="vefify-stat-card participants">
                        <h3>👥 Total Participants</h3>
                        <div class="stat-number"><?php echo $analytics['total_participants']; ?></div>
                        <a href="<?php echo admin_url('admin.php?page=vefify-participants'); ?>" class="button">View Participants</a>
                    </div>
                    
                    <div class="vefify-stat-card gifts">
                        <h3>🎁 Gifts Distributed</h3>
                        <div class="stat-number"><?php echo $analytics['gifts_distributed']; ?></div>
                        <a href="<?php echo admin_url('admin.php?page=vefify-gifts'); ?>" class="button">Manage Gifts</a>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="vefify-recent-activity">
                    <h2>Recent Activity</h2>
                    <div class="activity-list">
                        <?php foreach ($analytics['recent_activity'] as $activity): ?>
                            <div class="activity-item">
                                <span class="activity-time"><?php echo $activity['time']; ?></span>
                                <span class="activity-text"><?php echo $activity['message']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="vefify-quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="action-buttons">
                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=new'); ?>" class="button button-primary">
                            ➕ Create New Campaign
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=new'); ?>" class="button">
                            ➕ Add Question
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=new'); ?>" class="button">
                            ➕ Add Gift
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=vefify-reports'); ?>" class="button">
                            📊 View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .vefify-dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .vefify-stats-row {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        
        .vefify-stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .vefify-stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #2271b1;
            margin-bottom: 10px;
        }
        
        .vefify-recent-activity,
        .vefify-quick-actions {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
        }
        
        .activity-time {
            color: #666;
            font-size: 12px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        </style>
        <?php
    }
    
    /**
     * Display campaigns page
     */
    public function display_campaigns() {
        if (class_exists('Vefify_Campaign_Module')) {
            $campaign_module = Vefify_Campaign_Module::get_instance();
            $campaign_module->admin_page_router();
        } else {
            $this->display_module_not_found('Campaigns');
        }
    }
    
    /**
     * Display questions page - FIXED TO PROPERLY LOAD
     */
    public function display_questions() {
        // Load question module if not already loaded
        if (!class_exists('Vefify_Question_Module')) {
            $question_file = VEFIFY_QUIZ_PLUGIN_DIR . 'modules/questions/class-question-module.php';
            if (file_exists($question_file)) {
                require_once $question_file;
            }
        }
        
        if (class_exists('Vefify_Question_Module')) {
            $question_module = Vefify_Question_Module::get_instance();
            $question_module->admin_page_router();
        } else {
            $this->display_module_not_found('Questions');
        }
    }
    
    /**
     * Display gifts page
     */
    public function display_gifts() {
        if (class_exists('Vefify_Gift_Module')) {
            $gift_module = Vefify_Gift_Module::get_instance();
            $gift_module->admin_page_router();
        } else {
            $this->display_module_not_found('Gifts');
        }
    }
    
    /**
     * Display participants page
     */
    public function display_participants() {
        if (class_exists('Vefify_Participant_Module')) {
            $participant_module = Vefify_Participant_Module::get_instance();
            $participant_module->admin_page_router();
        } else {
            $this->display_module_not_found('Participants');
        }
    }
    
    /**
     * Display reports page
     */
    public function display_reports() {
        if (class_exists('Vefify_Report_Module')) {
            $report_module = Vefify_Report_Module::get_instance();
            $report_module->admin_page_router();
        } else {
            $this->display_module_not_found('Reports');
        }
    }
    
    /**
     * Display settings page
     */
    public function display_settings() {
        if (class_exists('Vefify_Setting_Module')) {
            $setting_module = Vefify_Setting_Module::get_instance();
            $setting_module->admin_page_router();
        } else {
            $this->display_module_not_found('Settings');
        }
    }
    
    /**
     * Display error when module is not found
     */
    private function display_module_not_found($module_name) {
        ?>
        <div class="wrap">
            <h1><?php echo $module_name; ?> Module</h1>
            <div class="notice notice-error">
                <p><strong>Module Not Found:</strong> The <?php echo $module_name; ?> module is not properly loaded. Please check the plugin installation.</p>
                <p>Expected file: <code>modules/<?php echo strtolower($module_name); ?>/class-<?php echo strtolower($module_name); ?>-module.php</code></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get dashboard analytics data
     */
    private function get_dashboard_analytics() {
        global $wpdb;
        
        // Use the correct table names after migration
        $campaigns_table = $wpdb->prefix . 'vefify_campaigns';
        $questions_table = $wpdb->prefix . 'vefify_questions';
        $participants_table = $wpdb->prefix . 'vefify_participants';
        
        $analytics = array(
            'active_campaigns' => 0,
            'total_questions' => 0,
            'total_participants' => 0,
            'gifts_distributed' => 0,
            'recent_activity' => array()
        );
        
        // Get active campaigns count
        $active_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table WHERE is_active = 1");
        $analytics['active_campaigns'] = $active_campaigns ?: 0;
        
        // Get total questions count
        $total_questions = $wpdb->get_var("SELECT COUNT(*) FROM $questions_table WHERE is_active = 1");
        $analytics['total_questions'] = $total_questions ?: 0;
        
        // Get total participants count (check if table exists first)
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$participants_table'");
        if ($table_exists) {
            $total_participants = $wpdb->get_var("SELECT COUNT(*) FROM $participants_table");
            $analytics['total_participants'] = $total_participants ?: 0;
            
            $gifts_distributed = $wpdb->get_var("SELECT COUNT(*) FROM $participants_table WHERE gift_code IS NOT NULL");
            $analytics['gifts_distributed'] = $gifts_distributed ?: 0;
            
            // Get recent activity
            $recent_activity = $wpdb->get_results("
                SELECT participant_name, quiz_status, created_at 
                FROM $participants_table 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            
            foreach ($recent_activity as $activity) {
                $analytics['recent_activity'][] = array(
                    'time' => human_time_diff(strtotime($activity->created_at), current_time('timestamp')) . ' ago',
                    'message' => $activity->participant_name . ' ' . $activity->quiz_status . ' a quiz'
                );
            }
        }
        
        return $analytics;
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'vefify') === false) {
            return;
        }
        
        wp_enqueue_style(
            'vefify-admin-style',
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            VEFIFY_QUIZ_VERSION
        );
        
        wp_enqueue_script(
            'vefify-admin-script',
            VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            VEFIFY_QUIZ_VERSION,
            true
        );
    }
}

// Initialize the admin menu
Vefify_Quiz_Admin_Menu::get_instance();
?>