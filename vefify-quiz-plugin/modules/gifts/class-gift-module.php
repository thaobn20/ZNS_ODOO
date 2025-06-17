<?php
/**
 * Gift Management Module
 * File: modules/gifts/class-gift-module.php
 * Handles gift distribution, inventory management, and API integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Gift_Module {
    
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
        add_action('wp_ajax_vefify_generate_gift_codes', array($this, 'ajax_generate_gift_codes'));
        add_action('wp_ajax_vefify_check_gift_inventory', array($this, 'ajax_check_inventory'));
        
        // Hooks for automatic gift distribution
        add_action('vefify_quiz_completed', array($this, 'distribute_gift_on_completion'), 10, 2);
    }
    
    private function load_components() {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/gifts/class-gift-model.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/gifts/class-gift-manager.php';
        
        $this->model = new Vefify_Gift_Model();
        if (is_admin()) {
            $this->manager = new Vefify_Gift_Manager();
        }
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'vefify-quiz',
            'Gift Management',
            '🎁 Gifts',
            'manage_options',
            'vefify-gifts',
            array($this, 'admin_page_router')
        );
    }
    
    public function admin_page_router() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'new':
            case 'edit':
                $this->manager->display_gift_form();
                break;
            case 'inventory':
                $this->manager->display_inventory_management();
                break;
            case 'distribution':
                $this->manager->display_distribution_report();
                break;
            default:
                $this->manager->display_gifts_list();
                break;
        }
    }
    
    /**
     * Distribute gift automatically on quiz completion
     */
    public function distribute_gift_on_completion($participant_id, $final_score) {
        $participant = $this->get_participant($participant_id);
        if (!$participant) {
            return;
        }
        
        $eligible_gifts = $this->model->get_eligible_gifts($participant['campaign_id'], $final_score);
        
        if (!empty($eligible_gifts)) {
            $gift = $eligible_gifts[0]; // Get the best matching gift
            $gift_code = $this->model->generate_gift_code($gift['id'], $participant_id);
            
            if ($gift_code) {
                // Update participant with gift code
                $this->update_participant_gift($participant_id, $gift_code);
                
                // Send notification
                $this->send_gift_notification($participant, $gift, $gift_code);
                
                // Update inventory
                $this->model->update_gift_inventory($gift['id'], 1);
            }
        }
    }
    
    /**
     * AJAX: Generate gift codes in bulk
     */
    public function ajax_generate_gift_codes() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_gift_ajax')) {
            wp_die('Security check failed');
        }
        
        $gift_id = intval($_POST['gift_id']);
        $quantity = intval($_POST['quantity']);
        
        if ($quantity > 100) {
            wp_send_json_error('Maximum 100 codes per batch');
        }
        
        $codes = array();
        for ($i = 0; $i < $quantity; $i++) {
            $code = $this->model->generate_unique_gift_code($gift_id);
            if ($code) {
                $codes[] = $code;
            }
        }
        
        wp_send_json_success(array(
            'codes' => $codes,
            'count' => count($codes)
        ));
    }
    
    /**
     * AJAX: Check gift inventory status
     */
    public function ajax_check_inventory() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_gift_ajax')) {
            wp_die('Security check failed');
        }
        
        $gift_id = intval($_POST['gift_id']);
        $inventory = $this->model->get_gift_inventory($gift_id);
        
        wp_send_json_success($inventory);
    }
    
    /**
     * Get module analytics for dashboard
     */
    public function get_module_analytics() {
        $stats = $this->model->get_gift_statistics();
        
        return array(
            'title' => 'Gift Management',
            'description' => 'Manage rewards and incentives for quiz participants',
            'icon' => '🎁',
            'stats' => array(
                'total_gifts' => array(
                    'label' => 'Total Gift Types',
                    'value' => $stats['total_gifts'],
                    'trend' => '+5 new types'
                ),
                'distributed_gifts' => array(
                    'label' => 'Gifts Distributed',
                    'value' => number_format($stats['distributed_count']),
                    'trend' => '+23% this week'
                ),
                'claim_rate' => array(
                    'label' => 'Claim Rate',
                    'value' => $stats['claim_rate'] . '%',
                    'trend' => '+5% improvement'
                )
            ),
            'quick_actions' => array(
                array(
                    'label' => 'Add New Gift',
                    'url' => admin_url('admin.php?page=vefify-gifts&action=new'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'Inventory Report',
                    'url' => admin_url('admin.php?page=vefify-gifts&action=inventory'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    // Helper methods
    private function get_participant($participant_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vefify_participants WHERE id = %d",
            $participant_id
        ), ARRAY_A);
    }
    
    private function update_participant_gift($participant_id, $gift_code) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . 'vefify_participants',
            array('gift_code' => $gift_code),
            array('id' => $participant_id)
        );
    }
    
    private function send_gift_notification($participant, $gift, $gift_code) {
        // Implementation for sending gift notification email/SMS
        $subject = 'Congratulations! You\'ve earned a gift!';
        $message = sprintf(
            'Hi %s,\n\nCongratulations on completing the quiz! You\'ve earned: %s\n\nYour gift code: %s\n\nThank you for participating!',
            $participant['participant_name'],
            $gift['gift_name'],
            $gift_code
        );
        
        if ($participant['participant_email']) {
            wp_mail($participant['participant_email'], $subject, $message);
        }
    }
    
    public function get_model() {
        return $this->model;
    }
    
    public function get_manager() {
        return $this->manager;
    }
}