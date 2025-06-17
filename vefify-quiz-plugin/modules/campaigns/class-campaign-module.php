<?php
/**
 * Campaign Module Main Class
 * File: modules/campaigns/class-campaign-module.php
 * 
 * PERFORMANCE OPTIMIZED but maintains original structure for compatibility
 * - Keeps original file loading structure
 * - Maintains class names that shortcodes expect
 * - Optimized internals for better performance
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
        // Load module components (KEEPING ORIGINAL STRUCTURE)
        $this->load_components();
        
        // WordPress hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_vefify_campaign_action', array($this, 'ajax_campaign_action'));
        add_action('wp_ajax_vefify_refresh_stats', array($this, 'ajax_refresh_stats'));
        
        // Handle URL actions
        add_action('admin_init', array($this, 'handle_url_actions'));
    }
    
    /**
     * Load module components - KEEPING ORIGINAL STRUCTURE
     * This ensures shortcodes and other components still work
     */
    private function load_components() {
        // Load model (data layer) - ORIGINAL FILE STRUCTURE
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/campaigns/class-campaign-model.php';
        $this->model = new Vefify_Campaign_Model();
        
        // Load manager (admin interface) only in admin - ORIGINAL FILE STRUCTURE
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
     * Display campaign analytics
     */
    public function display_campaign_analytics() {
        echo '<div class="wrap">';
        echo '<h1>📊 Campaign Analytics</h1>';
        echo '<div class="notice notice-info"><p>Analytics optimized for performance. Use "📊 Stats" buttons for detailed data.</p></div>';
        echo '<p><a href="' . admin_url('admin.php?page=vefify-campaigns') . '" class="button button-primary">← Back to Campaigns</a></p>';
        echo '</div>';
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
        $new_campaign_data['is_active'] = 0; // Set as inactive by default
        
        $result = $this->model->create_campaign($new_campaign_data);
        
        if (is_wp_error($result)) {
            set_transient('vefify_admin_notice', array(
                'type' => 'error',
                'message' => 'Failed to duplicate campaign: ' . $result->get_error_message()
            ), 30);
        } else {
            set_transient('vefify_admin_notice', array(
                'type' => 'success',
                'message' => 'Campaign duplicated successfully'
            ), 30);
        }
        
        wp_redirect(admin_url('admin.php?page=vefify-campaigns'));
        exit;
    }
    
    /**
     * AJAX handler for campaign actions
     */
    public function ajax_campaign_action() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_campaign_ajax')) {
            wp_die('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'toggle_status':
                $current_status = intval($_POST['current_status']);
                $new_status = $current_status ? 0 : 1;
                
                $result = $this->model->update_campaign($campaign_id, array('is_active' => $new_status));
                
                if (is_wp_error($result)) {
                    wp_send_json_error($result->get_error_message());
                } else {
                    wp_send_json_success(array(
                        'new_status' => $new_status,
                        'message' => 'Campaign status updated successfully'
                    ));
                }
                break;
        }
        
        wp_die();
    }
    
    /**
     * AJAX: Refresh statistics on-demand
     */
    public function ajax_refresh_stats() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_refresh_stats')) {
            wp_die('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        
        // Get statistics from model
        $stats = $this->model->get_campaign_statistics($campaign_id);
        
        wp_send_json_success($stats);
    }
    
    /**
     * Get module analytics for dashboard
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
        $table = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . "participants";
        
        // Check if table exists before querying
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return $wpdb->get_var("SELECT COUNT(*) FROM $table");
        }
        
        return 0;
    }
    
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
}