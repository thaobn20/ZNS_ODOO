<?php
/**
 * 🔧 FIXED CAMPAIGN MODULE - Remove conflicting shortcodes
 * File: modules/campaigns/class-campaign-module.php
 * 
 * Remove shortcode registration from campaign module to prevent conflicts
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Campaign_Module {
    
    private static $instance = null;
    private $model;
    private $manager;
    
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
        // Load module components
        $this->load_components();
        
        // WordPress hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_vefify_campaign_action', array($this, 'ajax_campaign_action'));
        
        // Handle URL actions
        add_action('admin_init', array($this, 'handle_url_actions'));
        
        // 🚫 REMOVED: Shortcode initialization - let quiz shortcode handle this
        // add_action('init', array($this, 'init_shortcodes'));
    }
    
    /**
     * Load module components
     */
    private function load_components() {
        // Load model (data layer)
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/campaigns/class-campaign-model.php';
        $this->model = new Vefify_Campaign_Model();
        
        // Load manager (admin interface) only in admin
        if (is_admin()) {
            require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/campaigns/class-campaign-manager.php';
            $this->manager = new Vefify_Campaign_Manager();
        }
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Main campaigns page
        add_submenu_page(
            'vefify-quiz',
            'Campaigns',
            '📋 Campaigns',
            'manage_options',
            'vefify-campaigns',
            array($this, 'admin_page_router')
        );
    }
    
    /**
     * Route admin page requests
     */
    public function admin_page_router() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'new':
            case 'edit':
                $this->manager->display_campaign_form();
                break;
            case 'analytics':
                $this->display_campaign_analytics();
                break;
            default:
                $this->manager->display_campaigns_list();
                break;
        }
    }
    
    /**
     * Handle URL-based actions (delete, duplicate, etc.)
     */
    public function handle_url_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'vefify-campaigns') {
            return;
        }
        
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!current_user_can('manage_options') || !$campaign_id) {
            return;
        }
        
        switch ($action) {
            case 'delete':
                $this->handle_delete_campaign($campaign_id);
                break;
            case 'duplicate':
                $this->handle_duplicate_campaign($campaign_id);
                break;
        }
    }
    
    /**
     * Handle campaign deletion
     */
    private function handle_delete_campaign($campaign_id) {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_campaign_' . $campaign_id)) {
            wp_die('Security check failed');
        }
        
        $result = $this->model->delete_campaign($campaign_id);
        
        if (is_wp_error($result)) {
            set_transient('vefify_admin_notice', array(
                'type' => 'error',
                'message' => 'Failed to delete campaign: ' . $result->get_error_message()
            ), 30);
        } else {
            set_transient('vefify_admin_notice', array(
                'type' => 'success',
                'message' => 'Campaign deleted successfully'
            ), 30);
        }
        
        wp_redirect(admin_url('admin.php?page=vefify-campaigns'));
        exit;
    }
    
    /**
     * Handle campaign duplication
     */
    private function handle_duplicate_campaign($campaign_id) {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'duplicate_campaign_' . $campaign_id)) {
            wp_die('Security check failed');
        }
        
        $original_campaign = $this->model->get_campaign($campaign_id);
        
        if (!$original_campaign) {
            wp_die('Campaign not found');
        }
        
        // Prepare data for new campaign
        $new_campaign_data = $original_campaign;
        unset($new_campaign_data['id']);
        unset($new_campaign_data['created_at']);
        unset($new_campaign_data['updated_at']);
        
        $new_campaign_data['name'] = $original_campaign['name'] . ' (Copy)';
        $new_campaign_data['is_active'] = 0; // Deactivate copy by default
        
        // Set new dates (start from today, same duration)
        $original_duration = strtotime($original_campaign['end_date']) - strtotime($original_campaign['start_date']);
        $new_campaign_data['start_date'] = current_time('mysql');
        $new_campaign_data['end_date'] = date('Y-m-d H:i:s', time() + $original_duration);
        
        $result = $this->model->create_campaign($new_campaign_data);
        
        if (is_wp_error($result)) {
            set_transient('vefify_admin_notice', array(
                'type' => 'error',
                'message' => 'Failed to duplicate campaign: ' . $result->get_error_message()
            ), 30);
            wp_redirect(admin_url('admin.php?page=vefify-campaigns'));
        } else {
            set_transient('vefify_admin_notice', array(
                'type' => 'success',
                'message' => 'Campaign duplicated successfully'
            ), 30);
            wp_redirect(admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $result));
        }
        exit;
    }
    
    /**
     * Display campaign analytics page
     */
    public function display_campaign_analytics() {
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        
        if (!$campaign_id) {
            wp_die('Campaign ID required');
        }
        
        $campaign = $this->model->get_campaign($campaign_id);
        if (!$campaign) {
            wp_die('Campaign not found');
        }
        
        $date_range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : '7days';
        $analytics = $this->model->get_campaign_analytics($campaign_id, $date_range);
        
        ?>
        <div class="wrap">
            <h1>📊 Campaign Analytics: <?php echo esc_html($campaign['name']); ?></h1>
            
            <!-- Date Range Filter -->
            <div class="analytics-filters">
                <select id="date-range-filter" onchange="location.href=this.value">
                    <option value="<?php echo admin_url('admin.php?page=vefify-campaigns&action=analytics&campaign_id=' . $campaign_id . '&range=24hours'); ?>" 
                            <?php selected($date_range, '24hours'); ?>>Last 24 Hours</option>
                    <option value="<?php echo admin_url('admin.php?page=vefify-campaigns&action=analytics&campaign_id=' . $campaign_id . '&range=7days'); ?>" 
                            <?php selected($date_range, '7days'); ?>>Last 7 Days</option>
                    <option value="<?php echo admin_url('admin.php?page=vefify-campaigns&action=analytics&campaign_id=' . $campaign_id . '&range=30days'); ?>" 
                            <?php selected($date_range, '30days'); ?>>Last 30 Days</option>
                </select>
            </div>
            
            <!-- Summary Cards -->
            <div class="analytics-summary">
                <div class="summary-card">
                    <h3><?php echo number_format($analytics['summary']['participants_count']); ?></h3>
                    <div class="description">Total Participants</div>
                    <div class="change">+12% from last period</div>
                </div>
                <div class="summary-card">
                    <h3><?php echo number_format($analytics['summary']['completed_count']); ?></h3>
                    <div class="description">Completed Quizzes</div>
                    <div class="change positive">+5% completion rate</div>
                </div>
                <div class="summary-card">
                    <h3><?php echo $analytics['summary']['average_score']; ?></h3>
                    <div class="description">Average Score</div>
                    <div class="change negative">-0.2 from last period</div>
                </div>
                <div class="summary-card">
                    <h3><?php echo $analytics['summary']['pass_rate']; ?>%</h3>
                    <div class="description">Pass Rate</div>
                    <div class="change positive">+3% improvement</div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="analytics-charts">
                <div class="chart-container">
                    <h3>📈 Daily Participation Trend</h3>
                    <canvas id="participation-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="chart-container">
                    <h3>🎯 Score Distribution</h3>
                    <canvas id="score-distribution-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <style>
        .analytics-filters { margin: 20px 0; }
        .analytics-summary { display: flex; gap: 20px; margin: 20px 0; }
        .summary-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; flex: 1; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .summary-card h3 { margin: 0 0 10px; font-size: 28px; color: #0073aa; }
        .summary-card .description { color: #666; font-size: 14px; margin-bottom: 8px; }
        .summary-card .change { font-size: 12px; font-weight: bold; }
        .summary-card .change.positive { color: #46b450; }
        .summary-card .change.negative { color: #dc3232; }
        .analytics-charts { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0; }
        .chart-container { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; }
        </style>
        <?php
    }
    
    // 🚫 REMOVED ALL SHORTCODE FUNCTIONS - Let quiz shortcode handle this
    // The quiz shortcode class will handle: vefify_quiz, vefify_campaign, vefify_campaign_list
    
    /**
     * Get model instance
     */
    public function get_model() {
        return $this->model;
    }
    
    /**
     * Get manager instance
     */
    public function get_manager() {
        return $this->manager;
    }
    
    /**
     * Module analytics summary for dashboard
     */
    public function get_module_analytics() {
        $summary = $this->model->get_campaigns_summary();
        
        return array(
            'title' => 'Campaign Management',
            'description' => 'Create and manage quiz campaigns with participants tracking',
            'icon' => '📋',
            'stats' => array(
                'total_campaigns' => array(
                    'label' => 'Total Campaigns',
                    'value' => $summary['total'],
                    'trend' => '+12% this month'
                ),
                'active_campaigns' => array(
                    'label' => 'Active Campaigns',
                    'value' => $summary['active'],
                    'trend' => 'Running now'
                ),
                'total_participants' => array(
                    'label' => 'Total Participants',
                    'value' => $this->get_total_participants(),
                    'trend' => '+45% this week'
                )
            ),
            'quick_actions' => array(
                array(
                    'label' => 'Create Campaign',
                    'url' => admin_url('admin.php?page=vefify-campaigns&action=new'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'View All Campaigns',
                    'url' => admin_url('admin.php?page=vefify-campaigns'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    /**
     * Get total participants across all campaigns
     */
    private function get_total_participants() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}" . VEFIFY_QUIZ_TABLE_PREFIX . "participants");
    }
}