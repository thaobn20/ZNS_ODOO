<?php
/**
 * UPDATED Gift Module Class - Fixed Routing & Asset Loading
 * File: modules/gifts/class-gift-module.php
 * 
 * ✅ FIXES: Wrong layout loading, asset management, routing issues
 * ✅ ADDS: Proper CSS/JS enqueuing, modern UI support
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Gift_Module {
    
    private static $instance = null;
    private $model;
    private $manager;
    private $ajax;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    /**
     * ✅ FIXED: Initialize with proper asset loading
     */
    private function init() {
        // Load components
        $this->load_components();
        
        // WordPress hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_vefify_save_gift', array($this, 'ajax_save_gift'));
        add_action('wp_ajax_vefify_delete_gift', array($this, 'ajax_delete_gift'));
        add_action('wp_ajax_vefify_get_gift', array($this, 'ajax_get_gift'));
        add_action('wp_ajax_vefify_toggle_gift_status', array($this, 'ajax_toggle_gift_status'));
        add_action('wp_ajax_vefify_generate_gift_codes', array($this, 'ajax_generate_gift_codes'));
        add_action('wp_ajax_vefify_check_gift_inventory', array($this, 'ajax_check_inventory'));
        
        // Hooks for automatic gift distribution
        add_action('vefify_quiz_completed', array($this, 'distribute_gift_on_completion'), 10, 2);
    }
    
    /**
     * ✅ FIXED: Load components with error handling
     */
    private function load_components() {
        try {
            require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/gifts/class-gift-model.php';
            require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/gifts/class-gift-manager.php';
            
            $this->model = new Vefify_Gift_Model();
            
            if (is_admin()) {
                $this->manager = new Vefify_Gift_Manager();
            }
            
        } catch (Exception $e) {
            error_log('Vefify Gift Module: Error loading components - ' . $e->getMessage());
            
            // Show admin notice
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Vefify Gift Module Error:</strong> ' . esc_html($e->getMessage()) . '</p>';
                echo '</div>';
            });
        }
    }
    
    /**
     * ✅ FIXED: Proper menu registration
     */
    public function add_admin_menu() {
        $page_hook = add_submenu_page(
            'vefify-quiz',
            'Gift Management',
            '🎁 Gifts',
            'manage_options',
            'vefify-gifts',
            array($this, 'admin_page_router')
        );
        
        // Add help tab for the page
        add_action('load-' . $page_hook, array($this, 'add_help_tab'));
    }
    
    /**
     * ✅ NEW: Add help tab for better UX
     */
    public function add_help_tab() {
        $screen = get_current_screen();
        
        $screen->add_help_tab(array(
            'id' => 'vefify-gifts-overview',
            'title' => 'Overview',
            'content' => '
                <h3>Gift Management</h3>
                <p>This page allows you to manage gifts and rewards for quiz participants.</p>
                <ul>
                    <li><strong>Add Gifts:</strong> Create new rewards for different score ranges</li>
                    <li><strong>Manage Inventory:</strong> Track distribution and remaining quantities</li>
                    <li><strong>Analytics:</strong> Monitor gift performance and claim rates</li>
                </ul>
            '
        ));
        
        $screen->set_help_sidebar('
            <p><strong>For more information:</strong></p>
            <p><a href="#" target="_blank">Gift Management Guide</a></p>
            <p><a href="#" target="_blank">Troubleshooting</a></p>
        ');
    }
    
    /**
     * ✅ FIXED: Proper asset enqueuing for gift pages only
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on gift management pages
        if (strpos($hook_suffix, 'vefify-gifts') === false) {
            return;
        }
        
        // WordPress core scripts we need
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('wp-util');
        
        // WordPress core styles
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        // Enqueue media library for any image uploads
        wp_enqueue_media();
        
        // Register our custom script
        wp_register_script(
            'vefify-gifts-admin',
            plugins_url('assets/js/gift-admin.js', __FILE__),
            array('jquery', 'wp-util'),
            '1.0.0',
            true
        );
        
        // Localize script with necessary data
        wp_localize_script('vefify-gifts-admin', 'vefifyGifts', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_gift_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this gift?', 'vefify-quiz'),
                'saving' => __('Saving...', 'vefify-quiz'),
                'save_gift' => __('Save Gift', 'vefify-quiz'),
                'delete_success' => __('Gift deleted successfully!', 'vefify-quiz'),
                'save_success' => __('Gift saved successfully!', 'vefify-quiz'),
                'error_occurred' => __('An error occurred. Please try again.', 'vefify-quiz'),
                'invalid_quantity' => __('Please enter a valid quantity (1-100)', 'vefify-quiz'),
                'codes_generated' => __('codes generated successfully!', 'vefify-quiz')
            ),
            'urls' => array(
                'gifts_page' => admin_url('admin.php?page=vefify-gifts'),
                'analytics_page' => admin_url('admin.php?page=vefify-analytics')
            )
        ));
        
        // Enqueue the script
        wp_enqueue_script('vefify-gifts-admin');
        
        // Add inline styles (modern CSS from the manager class)
        $this->add_inline_styles();
    }
    
    /**
     * ✅ Add modern CSS inline
     */
    private function add_inline_styles() {
        $css = '
        /* Reset WordPress admin styles that interfere */
        .vefify-modern-wrap * {
            box-sizing: border-box;
        }
        
        /* Override WordPress admin container */
        .vefify-modern-wrap {
            background: #f6f7fb !important;
            margin: -20px -20px 0 -2px !important;
            padding: 0 !important;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        /* Hide WordPress notices in our modern interface */
        .vefify-modern-wrap .notice,
        .vefify-modern-wrap .error,
        .vefify-modern-wrap .updated {
            display: none !important;
        }
        
        /* Custom notices for our interface */
        .vefify-notification {
            position: fixed;
            top: 32px;
            right: 20px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 16px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 100001;
            max-width: 400px;
            animation: slideInRight 0.3s ease;
        }
        
        .vefify-notification.success {
            border-left: 4px solid #46b450;
        }
        
        .vefify-notification.error {
            border-left: 4px solid #dc3232;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        /* Loading states */
        .vefify-btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .vefify-btn.loading .dashicons {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Enhanced responsive design */
        @media (max-width: 1200px) {
            .vefify-gifts-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)) !important;
            }
        }
        
        @media (max-width: 600px) {
            .vefify-modern-wrap {
                margin: -10px -10px 0 -10px !important;
            }
            
            .vefify-header {
                padding: 15px !important;
            }
            
            .vefify-page-title {
                font-size: 22px !important;
            }
            
            .vefify-stats-grid {
                margin: 0 15px 15px !important;
            }
            
            .vefify-gifts-container {
                margin: 0 15px !important;
            }
        }
        ';
        
        wp_add_inline_style('wp-admin', $css);
    }
    
    /**
     * ✅ FIXED: Improved admin page routing with error handling
     */
    public function admin_page_router() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Verify manager is loaded
        if (!$this->manager) {
            echo '<div class="notice notice-error"><p>Gift Manager could not be loaded. Please check your installation.</p></div>';
            return;
        }
        
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        // Display any admin messages
        $this->display_admin_messages();
        
        try {
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
                    
                case 'export':
                    $this->handle_export();
                    break;
                    
                default:
                    $this->manager->display_gifts_list();
                    break;
            }
            
        } catch (Exception $e) {
            error_log('Vefify Gift Module: Page routing error - ' . $e->getMessage());
            
            echo '<div class="vefify-modern-wrap">';
            echo '<div class="vefify-error-container">';
            echo '<h2>Oops! Something went wrong</h2>';
            echo '<p>We encountered an error while loading this page. Please try refreshing or contact support if the problem persists.</p>';
            echo '<a href="' . admin_url('admin.php?page=vefify-gifts') . '" class="button button-primary">Return to Gifts</a>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * ✅ Display admin messages (success/error)
     */
    private function display_admin_messages() {
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            
            switch ($message) {
                case 'saved':
                    echo '<div class="vefify-notification success">Gift saved successfully!</div>';
                    break;
                case 'deleted':
                    echo '<div class="vefify-notification success">Gift deleted successfully!</div>';
                    break;
            }
        }
        
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            echo '<div class="vefify-notification error">' . esc_html($error) . '</div>';
        }
    }
    
    /**
     * ✅ Handle gift export
     */
    private function handle_export() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'vefify_gift_nonce')) {
            wp_die('Security check failed');
        }
        
        $gifts = $this->model->get_gifts(array('per_page' => -1));
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="vefify-gifts-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, array(
            'ID', 'Gift Name', 'Type', 'Value', 'Campaign', 'Min Score', 
            'Max Score', 'Max Quantity', 'Used Count', 'Status', 'Created'
        ));
        
        // CSV Data
        foreach ($gifts as $gift) {
            fputcsv($output, array(
                $gift['id'],
                $gift['gift_name'],
                $gift['gift_type'],
                $gift['gift_value'],
                $this->get_campaign_name($gift['campaign_id']),
                $gift['min_score'],
                $gift['max_score'] ?: 'Unlimited',
                $gift['max_quantity'] ?: 'Unlimited',
                $gift['used_count'],
                $gift['is_active'] ? 'Active' : 'Inactive',
                $gift['created_at']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    // ===== AJAX HANDLERS =====
    
    /**
     * ✅ AJAX: Save gift
     */
    public function ajax_save_gift() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_gift_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $gift_data = $this->sanitize_gift_form_data($_POST);
            $gift_id = !empty($_POST['gift_id']) ? intval($_POST['gift_id']) : null;
            
            $result = $this->model->save_gift($gift_data, $gift_id);
            
            if (is_array($result) && isset($result['errors'])) {
                wp_send_json_error(array(
                    'message' => 'Validation failed',
                    'errors' => $result['errors']
                ));
            } elseif ($result === false) {
                wp_send_json_error(array(
                    'message' => 'Failed to save gift. Please try again.'
                ));
            } else {
                wp_send_json_success(array(
                    'message' => $gift_id ? 'Gift updated successfully!' : 'Gift created successfully!',
                    'gift_id' => $result,
                    'redirect' => admin_url('admin.php?page=vefify-gifts&message=saved')
                ));
            }
            
        } catch (Exception $e) {
            error_log('Gift save error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'An error occurred while saving the gift.'
            ));
        }
    }
    
    /**
     * ✅ AJAX: Delete gift
     */
    public function ajax_delete_gift() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_gift_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $gift_id = intval($_POST['gift_id'] ?? 0);
        
        if (!$gift_id) {
            wp_send_json_error('Invalid gift ID');
        }
        
        $result = $this->model->delete_gift($gift_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Gift deleted successfully!'
            ));
        } else {
            wp_send_json_error('Failed to delete gift');
        }
    }
    
    /**
     * ✅ AJAX: Get gift data
     */
    public function ajax_get_gift() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'vefify_gift_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $gift_id = intval($_GET['gift_id'] ?? 0);
        $gift = $this->model->get_gift_by_id($gift_id);
        
        if ($gift) {
            wp_send_json_success($gift);
        } else {
            wp_send_json_error('Gift not found');
        }
    }
    
    /**
     * ✅ AJAX: Toggle gift status
     */
    public function ajax_toggle_gift_status() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_gift_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $gift_id = intval($_POST['gift_id'] ?? 0);
        $gift = $this->model->get_gift_by_id($gift_id);
        
        if (!$gift) {
            wp_send_json_error('Gift not found');
        }
        
        $new_status = $gift['is_active'] ? 0 : 1;
        $result = $this->model->update_gift($gift_id, array('is_active' => $new_status));
        
        if ($result) {
            wp_send_json_success(array(
                'message' => $new_status ? 'Gift activated' : 'Gift deactivated',
                'new_status' => $new_status
            ));
        } else {
            wp_send_json_error('Failed to update gift status');
        }
    }
    
    /**
     * ✅ AJAX: Generate gift codes
     */
    public function ajax_generate_gift_codes() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_gift_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $gift_id = intval($_POST['gift_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        
        if ($quantity > 100 || $quantity < 1) {
            wp_send_json_error('Quantity must be between 1 and 100');
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
            'count' => count($codes),
            'message' => count($codes) . ' codes generated successfully!'
        ));
    }
    
    /**
     * ✅ AJAX: Check inventory
     */
    public function ajax_check_inventory() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_gift_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $gift_id = intval($_POST['gift_id'] ?? 0);
        $inventory = $this->model->get_gift_inventory($gift_id);
        
        wp_send_json_success($inventory);
    }
    
    // ===== HELPER METHODS =====
    
    /**
     * ✅ Sanitize form data
     */
    private function sanitize_gift_form_data($data) {
        return array(
            'campaign_id' => intval($data['campaign_id'] ?? 0),
            'gift_name' => sanitize_text_field($data['gift_name'] ?? ''),
            'gift_type' => sanitize_text_field($data['gift_type'] ?? ''),
            'gift_value' => sanitize_text_field($data['gift_value'] ?? ''),
            'gift_description' => wp_kses_post($data['gift_description'] ?? ''),
            'min_score' => intval($data['min_score'] ?? 0),
            'max_score' => !empty($data['max_score']) ? intval($data['max_score']) : null,
            'max_quantity' => !empty($data['max_quantity']) ? intval($data['max_quantity']) : null,
            'gift_code_prefix' => sanitize_text_field($data['gift_code_prefix'] ?? ''),
            'api_endpoint' => esc_url_raw($data['api_endpoint'] ?? ''),
            'api_params' => wp_kses_post($data['api_params'] ?? ''),
            'is_active' => isset($data['is_active']) ? 1 : 0
        );
    }
    
    /**
     * ✅ Get campaign name helper
     */
    private function get_campaign_name($campaign_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}vefify_campaigns WHERE id = %d",
            $campaign_id
        )) ?: 'Unknown Campaign';
    }
    
    /**
     * ✅ Legacy gift distribution (keep existing functionality)
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
        // Implementation for sending gift notification
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
    
    // ===== PUBLIC GETTERS =====
    
    public function get_model() {
        return $this->model;
    }
    
    public function get_manager() {
        return $this->manager;
    }
}