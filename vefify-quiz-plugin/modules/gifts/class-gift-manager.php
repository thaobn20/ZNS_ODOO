<?php
/**
 * Gift Manager Module (PHP)
 * File: modules/gifts/class-gift-manager.php
 */
namespace VefifyQuiz;

class GiftManager {
    private $db;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }
    
    /**
     * Assign gift based on score
     */
    public function assign_gift($campaign_id, $user_id, $score) {
        // Get available gifts for this score
        $available_gifts = $this->get_available_gifts($campaign_id, $score);
        
        if (empty($available_gifts)) {
            return [
                'has_gift' => false,
                'message' => 'No gifts available for your score'
            ];
        }
        
        // Select best gift (highest value for score range)
        $selected_gift = $available_gifts[0];
        
        // Check inventory
        if (!$this->check_gift_inventory($selected_gift['id'])) {
            return [
                'has_gift' => false,
                'message' => 'Gift inventory exhausted',
                'gift_name' => $selected_gift['gift_name']
            ];
        }
        
        // Generate gift code
        $gift_code = $this->generate_gift_code($selected_gift);
        
        // Update user record
        $this->assign_gift_to_user($user_id, $selected_gift['id'], $gift_code);
        
        // Update gift usage count
        $this->increment_gift_usage($selected_gift['id']);
        
        return [
            'has_gift' => true,
            'gift_id' => $selected_gift['id'],
            'gift_name' => $selected_gift['gift_name'],
            'gift_type' => $selected_gift['gift_type'],
            'gift_value' => $selected_gift['gift_value'],
            'gift_code' => $gift_code,
            'gift_description' => $selected_gift['gift_description'],
            'message' => 'Congratulations! You have earned a gift!'
        ];
    }
    
    /**
     * Get available gifts for score
     */
    private function get_available_gifts($campaign_id, $score) {
        $table = $this->db->prefix . 'vefify_gifts';
        
        return $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$table} 
             WHERE campaign_id = %d 
             AND is_active = 1 
             AND min_score <= %d 
             AND (max_score IS NULL OR max_score >= %d)
             AND (max_quantity IS NULL OR used_count < max_quantity)
             ORDER BY min_score DESC, gift_value DESC",
            $campaign_id, $score, $score
        ), ARRAY_A);
    }
    
    /**
     * Check gift inventory
     */
    private function check_gift_inventory($gift_id) {
        $table = $this->db->prefix . 'vefify_gifts';
        
        $gift = $this->db->get_row($this->db->prepare(
            "SELECT max_quantity, used_count FROM {$table} WHERE id = %d",
            $gift_id
        ));
        
        // Unlimited if max_quantity is null
        if ($gift->max_quantity === null) {
            return true;
        }
        
        return $gift->used_count < $gift->max_quantity;
    }
    
    /**
     * Generate gift code
     */
    private function generate_gift_code($gift) {
        $prefix = $gift['gift_code_prefix'] ?: 'GIFT';
        $random_part = strtoupper(wp_generate_password(6, false));
        return $prefix . $random_part;
    }
    
    /**
     * Assign gift to user
     */
    private function assign_gift_to_user($user_id, $gift_id, $gift_code) {
        $table = $this->db->prefix . 'vefify_quiz_users';
        
        $this->db->update($table, [
            'gift_id' => $gift_id,
            'gift_code' => $gift_code,
            'gift_status' => 'assigned'
        ], ['id' => $user_id]);
    }
    
    /**
     * Increment gift usage count
     */
    private function increment_gift_usage($gift_id) {
        $table = $this->db->prefix . 'vefify_gifts';
        
        $this->db->query($this->db->prepare(
            "UPDATE {$table} SET used_count = used_count + 1 WHERE id = %d",
            $gift_id
        ));
    }
    
    /**
     * Claim gift (for API integration in Phase 2)
     */
    public function claim_gift($request) {
        $user_id = intval($request->get_param('user_id'));
        $gift_code = sanitize_text_field($request->get_param('gift_code'));
        
        if (!$user_id || !$gift_code) {
            return new \WP_Error('missing_data', 'User ID and gift code required', ['status' => 400]);
        }
        
        // Get user and gift data
        $user_data = $this->get_user_gift_data($user_id, $gift_code);
        
        if (!$user_data) {
            return new \WP_Error('invalid_gift', 'Invalid gift code or user', ['status' => 404]);
        }
        
        if ($user_data['gift_status'] === 'claimed') {
            return new \WP_Error('already_claimed', 'Gift already claimed', ['status' => 409]);
        }
        
        // For Phase 2: Make API call to external service
        if ($user_data['api_endpoint']) {
            $api_result = $this->call_external_gift_api($user_data);
            
            if (is_wp_error($api_result)) {
                return $api_result;
            }
            
            // Update with API response
            $this->update_gift_claim_status($user_id, 'claimed', $api_result);
            
            return rest_ensure_response([
                'success' => true,
                'message' => 'Gift claimed successfully',
                'external_response' => $api_result
            ]);
        }
        
        // Phase 1: Simple gift claim
        $this->update_gift_claim_status($user_id, 'claimed');
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Gift claimed successfully',
            'gift_data' => $user_data
        ]);
    }
    
    /**
     * Get user gift data
     */
    private function get_user_gift_data($user_id, $gift_code) {
        $users_table = $this->db->prefix . 'vefify_quiz_users';
        $gifts_table = $this->db->prefix . 'vefify_gifts';
        
        return $this->db->get_row($this->db->prepare(
            "SELECT u.*, g.gift_name, g.gift_type, g.gift_value, g.api_endpoint, g.api_params
             FROM {$users_table} u
             JOIN {$gifts_table} g ON u.gift_id = g.id
             WHERE u.id = %d AND u.gift_code = %s",
            $user_id, $gift_code
        ), ARRAY_A);
    }
    
    /**
     * Update gift claim status
     */
    private function update_gift_claim_status($user_id, $status, $api_response = null) {
        $table = $this->db->prefix . 'vefify_quiz_users';
        
        $update_data = ['gift_status' => $status];
        
        if ($api_response) {
            $update_data['gift_response'] = json_encode($api_response);
        }
        
        $this->db->update($table, $update_data, ['id' => $user_id]);
    }
    
    /**
     * Call external gift API (Phase 2)
     */
    private function call_external_gift_api($user_data) {
        if (!$user_data['api_endpoint']) {
            return new \WP_Error('no_api', 'No API endpoint configured');
        }
        
        $api_params = json_decode($user_data['api_params'], true) ?: [];
        
        $request_data = array_merge($api_params, [
            'user_name' => $user_data['full_name'],
            'phone' => $user_data['phone_number'],
            'gift_type' => $user_data['gift_type'],
            'gift_value' => $user_data['gift_value'],
            'gift_code' => $user_data['gift_code']
        ]);
        
        $response = wp_remote_post($user_data['api_endpoint'], [
            'body' => json_encode($request_data),
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new \WP_Error('api_error', $data['message'] ?? 'API call failed');
        }
        
        return $data;
    }
}