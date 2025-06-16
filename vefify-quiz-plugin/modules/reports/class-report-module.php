<?php
/**
 * Report Module
 * File: modules/reports/class-report-module.php
 * Comprehensive analytics and reporting across all platform aspects
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Report_Module {
    
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
        $this->load_components();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_vefify_generate_report', array($this, 'ajax_generate_report'));
        add_action('wp_ajax_vefify_schedule_report', array($this, 'ajax_schedule_report'));
        
        // Schedule automated reports
        add_action('vefify_daily_report', array($this, 'send_daily_report'));
        add_action('vefify_weekly_report', array($this, 'send_weekly_report'));
        add_action('vefify_monthly_report', array($this, 'send_monthly_report'));
    }
    
    private function load_components() {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/reports/class-report-model.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/reports/class-report-manager.php';
        
        $this->model = new Vefify_Report_Model();
        if (is_admin()) {
            $this->manager = new Vefify_Report_Manager();
        }
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'vefify-quiz',
            'Reports',
            '📊 Reports',
            'manage_options',
            'vefify-reports',
            array($this, 'admin_page_router')
        );
    }
    
    public function admin_page_router() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'dashboard';
        
        switch ($action) {
            case 'campaign':
                $this->manager->display_campaign_report();
                break;
            case 'participant':
                $this->manager->display_participant_report();
                break;
            case 'performance':
                $this->manager->display_performance_report();
                break;
            case 'custom':
                $this->manager->display_custom_report_builder();
                break;
            case 'scheduled':
                $this->manager->display_scheduled_reports();
                break;
            default:
                $this->manager->display_reports_dashboard();
                break;
        }
    }
    
    public function get_module_analytics() {
        $stats = $this->model->get_report_statistics();
        
        return array(
            'title' => 'Reports & Analytics',
            'description' => 'Comprehensive analytics and insights across all platform aspects',
            'icon' => '📊',
            'stats' => array(
                'total_reports' => array(
                    'label' => 'Generated Reports',
                    'value' => number_format($stats['total_reports']),
                    'trend' => '+12 this month'
                ),
                'scheduled_reports' => array(
                    'label' => 'Scheduled Reports',
                    'value' => $stats['scheduled_reports'],
                    'trend' => '5 active'
                ),
                'data_points' => array(
                    'label' => 'Data Points Tracked',
                    'value' => number_format($stats['data_points']),
                    'trend' => 'Real-time'
                )
            ),
            'quick_actions' => array(
                array(
                    'label' => 'View Dashboard',
                    'url' => admin_url('admin.php?page=vefify-reports'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'Create Report',
                    'url' => admin_url('admin.php?page=vefify-reports&action=custom'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    public function ajax_generate_report() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_report_ajax')) {
            wp_send_json_error('Security check failed');
        }
        
        $report_type = sanitize_text_field($_POST['report_type']);
        $parameters = $_POST['parameters'];
        
        $report_data = $this->model->generate_report($report_type, $parameters);
        
        wp_send_json_success($report_data);
    }
    
    public function send_daily_report() {
        $report_data = $this->model->generate_daily_report();
        $this->model->send_scheduled_report('daily', $report_data);
    }
    
    public function send_weekly_report() {
        $report_data = $this->model->generate_weekly_report();
        $this->model->send_scheduled_report('weekly', $report_data);
    }
    
    public function send_monthly_report() {
        $report_data = $this->model->generate_monthly_report();
        $this->model->send_scheduled_report('monthly', $report_data);
    }
    
    public function get_model() {
        return $this->model;
    }
    
    public function get_manager() {
        return $this->manager;
    }
}
