<?php
/**
 * Participant Management Module
 * File: modules/participants/class-participant-module.php
 * Handles participant tracking, performance analysis, and engagement patterns
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Participant_Module {
    
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
        // Load components
        $this->load_components();
        
        // WordPress hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_vefify_participant_action', array($this, 'ajax_participant_action'));
        add_action('wp_ajax_vefify_export_participants', array($this, 'ajax_export_participants'));
        
        // Frontend hooks for participant tracking
        add_action('wp_ajax_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        add_action('wp_ajax_nopriv_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        add_action('wp_ajax_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
        add_action('wp_ajax_nopriv_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
    }
    
    private function load_components() {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/participants/class-participant-model.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/participants/class-participant-manager.php';
        
        $this->model = new Vefify_Participant_Model();
        if (is_admin()) {
            $this->manager = new Vefify_Participant_Manager();
        }
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'vefify-quiz',
            'Participants',
            '👥 Participants',
            'manage_options',
            'vefify-participants',
            array($this, 'admin_page_router')
        );
    }
    
    public function admin_page_router() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'view':
                $this->manager->display_participant_details();
                break;
            case 'analytics':
                $this->manager->display_participant_analytics();
                break;
            case 'segments':
                $this->manager->display_participant_segments();
                break;
            case 'communications':
                $this->manager->display_communication_center();
                break;
            default:
                $this->manager->display_participants_list();
                break;
        }
    }
    
    /**
     * AJAX: Start quiz session
     */
    public function ajax_start_quiz() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_frontend_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $participant_data = array(
            'participant_name' => sanitize_text_field($_POST['participant_name']),
            'participant_email' => sanitize_email($_POST['participant_email']),
            'participant_phone' => sanitize_text_field($_POST['participant_phone']),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        );
        
        $participant_id = $this->model->start_quiz_session($campaign_id, $participant_data);
        
        if (is_wp_error($participant_id)) {
            wp_send_json_error($participant_id->get_error_message());
        }
        
        // Get quiz questions
        $questions = $this->get_quiz_questions($campaign_id);
        
        wp_send_json_success(array(
            'participant_id' => $participant_id,
            'questions' => $questions,
            'session_data' => $this->model->get_participant($participant_id)
        ));
    }
    
    /**
     * AJAX: Submit quiz answers
     */
    public function ajax_submit_quiz() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_frontend_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $participant_id = intval($_POST['participant_id']);
        $answers = $_POST['answers']; // Array of question_id => answer_id(s)
        
        $result = $this->model->submit_quiz_answers($participant_id, $answers);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Trigger quiz completion hook for gift distribution
        do_action('vefify_quiz_completed', $participant_id, $result['final_score']);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Participant management actions
     */
    public function ajax_participant_action() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_participant_ajax')) {
            wp_send_json_error('Security check failed');
        }
        
        $action = sanitize_text_field($_POST['participant_action']);
        $participant_ids = array_map('intval', $_POST['participant_ids']);
        
        switch ($action) {
            case 'send_message':
                $message = sanitize_textarea_field($_POST['message']);
                $subject = sanitize_text_field($_POST['subject']);
                $result = $this->send_bulk_message($participant_ids, $subject, $message);
                break;
            case 'export_data':
                $result = $this->export_participant_data($participant_ids);
                break;
            case 'add_to_segment':
                $segment_id = intval($_POST['segment_id']);
                $result = $this->add_participants_to_segment($participant_ids, $segment_id);
                break;
            default:
                wp_send_json_error('Invalid action');
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Export participants data
     */
    public function ajax_export_participants() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_participant_ajax')) {
            wp_die('Security check failed');
        }
        
        $filters = array(
            'campaign_id' => isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null,
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all',
            'date_from' => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : null,
            'date_to' => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : null
        );
        
        $participants = $this->model->get_participants_for_export($filters);
        
        // Generate CSV
        $filename = 'participants-export-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'ID', 'Campaign', 'Name', 'Email', 'Phone', 'Status', 
            'Start Time', 'End Time', 'Final Score', 'Gift Code', 'IP Address'
        ));
        
        // CSV data
        foreach ($participants as $participant) {
            fputcsv($output, array(
                $participant['id'],
                $participant['campaign_name'],
                $participant['participant_name'],
                $participant['participant_email'],
                $participant['participant_phone'],
                $participant['quiz_status'],
                $participant['start_time'],
                $participant['end_time'],
                $participant['final_score'],
                $participant['gift_code'],
                $participant['ip_address']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get module analytics for dashboard
     */
    public function get_module_analytics() {
        $stats = $this->model->get_participant_statistics();
        
        return array(
            'title' => 'Participants Management',
            'description' => 'Track participant engagement, performance, and behavior patterns',
            'icon' => '👥',
            'stats' => array(
                'total_participants' => array(
                    'label' => 'Total Participants',
                    'value' => number_format($stats['total_participants']),
                    'trend' => '+34% this month'
                ),
                'active_participants' => array(
                    'label' => 'Active (30 days)',
                    'value' => number_format($stats['active_participants']),
                    'trend' => '58% of total'
                ),
                'completion_rate' => array(
                    'label' => 'Completion Rate',
                    'value' => $stats['completion_rate'] . '%',
                    'trend' => '+15% improvement'
                )
            ),
            'quick_actions' => array(
                array(
                    'label' => 'View Analytics',
                    'url' => admin_url('admin.php?page=vefify-participants&action=analytics'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'Export Data',
                    'url' => admin_url('admin.php?page=vefify-participants&action=export'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    // Helper methods
    private function get_quiz_questions($campaign_id) {
        $plugin = vefify_quiz_init();
        $question_module = $plugin->get_module('questions');
        if ($question_module) {
            return $question_module->get_campaign_questions($campaign_id);
        }
        return array();
    }
    
    private function send_bulk_message($participant_ids, $subject, $message) {
        $participants = $this->model->get_participants_by_ids($participant_ids);
        $sent_count = 0;
        
        foreach ($participants as $participant) {
            if ($participant['participant_email']) {
                $personalized_message = str_replace(
                    '{participant_name}', 
                    $participant['participant_name'], 
                    $message
                );
                
                if (wp_mail($participant['participant_email'], $subject, $personalized_message)) {
                    $sent_count++;
                }
            }
        }
        
        return array(
            'sent_count' => $sent_count,
            'total_count' => count($participants)
        );
    }
    
    private function export_participant_data($participant_ids) {
        return $this->model->get_participants_by_ids($participant_ids);
    }
    
    private function add_participants_to_segment($participant_ids, $segment_id) {
        // Implementation for adding participants to segments
        return $this->model->add_to_segment($participant_ids, $segment_id);
    }
    
    public function get_model() {
        return $this->model;
    }
    
    public function get_manager() {
        return $this->manager;
    }
}