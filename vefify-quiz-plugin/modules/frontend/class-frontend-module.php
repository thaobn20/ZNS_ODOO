<?php
/**
 * Frontend Module for AJAX handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Frontend_Module {
    
    public function __construct() {
        // AJAX handlers
        add_action('wp_ajax_vefify_submit_registration', array($this, 'handle_registration_submit'));
        add_action('wp_ajax_nopriv_vefify_submit_registration', array($this, 'handle_registration_submit'));
    }
    
    /**
     * Handle registration form submission
     */
    public function handle_registration_submit() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $participant_data = array(
            'full_name' => sanitize_text_field($_POST['full_name'] ?? ''),
            'phone_number' => sanitize_text_field($_POST['phone_number'] ?? ''),
            'province' => sanitize_text_field($_POST['province'] ?? ''),
            'pharmacist_code' => sanitize_text_field($_POST['pharmacist_code'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? '')
        );
        
        // Validate required fields
        if (empty($participant_data['full_name']) || empty($participant_data['phone_number'])) {
            wp_send_json_error('Required fields missing');
        }
        
        // Check phone uniqueness
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/participants/class-participant-model.php';
        $participant_model = new Vefify_Participant_Model();
        
        if ($participant_model->phone_exists_in_campaign($participant_data['phone_number'], $campaign_id)) {
            wp_send_json_error('Phone number already registered for this campaign');
        }
        
        // Create participant session
        $session_data = $participant_model->create_participant_session($campaign_id, $participant_data);
        
        if ($session_data) {
            wp_send_json_success($session_data);
        } else {
            wp_send_json_error('Failed to create registration');
        }
    }
}

// Initialize
new Vefify_Frontend_Module();