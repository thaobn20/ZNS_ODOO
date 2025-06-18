<?php
/**
 * Advanced AJAX Handlers for Frontend Quiz
 * File: modules/frontend/class-quiz-ajax-handlers.php
 * 
 * Additional AJAX endpoints for enhanced functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Ajax_Handlers {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_ajax_handlers();
    }
    
    private function init_ajax_handlers() {
        // Gift claiming
        add_action('wp_ajax_vefify_claim_gift', array($this, 'ajax_claim_gift'));
        add_action('wp_ajax_nopriv_vefify_claim_gift', array($this, 'ajax_claim_gift'));
        
        // Save progress
        add_action('wp_ajax_vefify_save_progress', array($this, 'ajax_save_progress'));
        add_action('wp_ajax_nopriv_vefify_save_progress', array($this, 'ajax_save_progress'));
        
        // Get leaderboard
        add_action('wp_ajax_vefify_get_leaderboard', array($this, 'ajax_get_leaderboard'));
        add_action('wp_ajax_nopriv_vefify_get_leaderboard', array($this, 'ajax_get_leaderboard'));
        
        // Generate certificate
        add_action('wp_ajax_vefify_generate_certificate', array($this, 'ajax_generate_certificate'));
        add_action('wp_ajax_nopriv_vefify_generate_certificate', array($this, 'ajax_generate_certificate'));
        
        // Email results
        add_action('wp_ajax_vefify_email_results', array($this, 'ajax_email_results'));
        add_action('wp_ajax_nopriv_vefify_email_results', array($this, 'ajax_email_results'));
        
        // Get quiz statistics
        add_action('wp_ajax_vefify_get_quiz_stats', array($this, 'ajax_get_quiz_stats'));
        add_action('wp_ajax_nopriv_vefify_get_quiz_stats', array($this, 'ajax_get_quiz_stats'));
        
        // Validate pharmacy code
        add_action('wp_ajax_vefify_validate_pharmacy', array($this, 'ajax_validate_pharmacy'));
        add_action('wp_ajax_nopriv_vefify_validate_pharmacy', array($this, 'ajax_validate_pharmacy'));
    }
    
    /**
     * AJAX: Claim gift with API integration
     */
    public function ajax_claim_gift() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $gift_id = intval($_POST['gift_id']);
        $participant_id = intval($_POST['participant_id']);
        
        // Get participant and gift details
        global $wpdb;
        $participant = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vefify_participants WHERE id = %d",
            $participant_id
        ), ARRAY_A);
        
        $gift = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vefify_gifts WHERE id = %d",
            $gift_id
        ), ARRAY_A);
        
        if (!$participant || !$gift) {
            wp_send_json_error(array('message' => 'Invalid participant or gift'));
        }
        
        // Check if already claimed
        if ($participant['gift_status'] === 'claimed') {
            wp_send_json_error(array('message' => 'Gift already claimed'));
        }
        
        // Call external API if configured
        $api_response = null;
        if (!empty($gift['api_endpoint'])) {
            $api_response = $this->call_gift_api($gift, $participant);
            
            if (is_wp_error($api_response)) {
                wp_send_json_error(array('message' => $api_response->get_error_message()));
            }
        }
        
        // Update participant status
        $update_result = $wpdb->update(
            $wpdb->prefix . 'vefify_participants',
            array(
                'gift_status' => 'claimed',
                'gift_response' => json_encode($api_response),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $participant_id)
        );
        
        if ($update_result !== false) {
            // Log the event
            $this->log_gift_claim($participant_id, $gift_id, $api_response);
            
            // Send confirmation email if enabled
            $this->send_gift_confirmation_email($participant, $gift, $api_response);
            
            wp_send_json_success(array(
                'message' => 'Gift claimed successfully!',
                'api_response' => $api_response
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to update gift status'));
        }
    }
    
    /**
     * AJAX: Save quiz progress
     */
    public function ajax_save_progress() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $participant_id = intval($_POST['participant_id']);
        $current_question = intval($_POST['current_question']);
        $answers = $_POST['answers'] ?? array();
        
        global $wpdb;
        
        // Update participant progress
        $result = $wpdb->update(
            $wpdb->prefix . 'vefify_participants',
            array(
                'quiz_status' => 'in_progress',
                'answers_data' => json_encode($answers),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $participant_id)
        );
        
        // Update session data
        $wpdb->update(
            $wpdb->prefix . 'vefify_quiz_sessions',
            array(
                'current_question' => $current_question,
                'answers_data' => json_encode($answers),
                'updated_at' => current_time('mysql')
            ),
            array('participant_id' => $participant_id)
        );
        
        wp_send_json_success(array('message' => 'Progress saved'));
    }
    
    /**
     * AJAX: Get campaign leaderboard
     */
    public function ajax_get_leaderboard() {
        $campaign_id = intval($_POST['campaign_id']);
        $limit = intval($_POST['limit'] ?? 10);
        
        global $wpdb;
        
        $leaderboard = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                participant_name,
                final_score,
                total_questions,
                ROUND((final_score / total_questions) * 100, 1) as percentage,
                end_time,
                DATEDIFF(NOW(), end_time) as days_ago
            FROM {$wpdb->prefix}vefify_participants
            WHERE campaign_id = %d 
            AND quiz_status = 'completed'
            ORDER BY final_score DESC, end_time ASC
            LIMIT %d",
            $campaign_id, $limit
        ), ARRAY_A);
        
        // Mask participant names for privacy
        foreach ($leaderboard as &$entry) {
            $name = $entry['participant_name'];
            if (strlen($name) > 3) {
                $entry['participant_name'] = substr($name, 0, 1) . str_repeat('*', strlen($name) - 2) . substr($name, -1);
            }
        }
        
        wp_send_json_success($leaderboard);
    }
    
    /**
     * AJAX: Generate certificate
     */
    public function ajax_generate_certificate() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $participant_id = intval($_POST['participant_id']);
        
        $participant_model = new Vefify_Participant_Model();
        $certificate_data = $participant_model->get_certificate_data($participant_id);
        
        if (!$certificate_data) {
            wp_send_json_error(array('message' => 'Certificate not available'));
        }
        
        // Generate certificate HTML
        $certificate_html = $this->generate_certificate_html($certificate_data);
        
        wp_send_json_success(array(
            'html' => $certificate_html,
            'download_url' => $this->generate_certificate_pdf($certificate_data)
        ));
    }
    
    /**
     * AJAX: Email results to participant
     */
    public function ajax_email_results() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $participant_id = intval($_POST['participant_id']);
        $email = sanitize_email($_POST['email']);
        
        if (!$email) {
            wp_send_json_error(array('message' => 'Valid email required'));
        }
        
        global $wpdb;
        $participant = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, c.name as campaign_name 
             FROM {$wpdb->prefix}vefify_participants p
             JOIN {$wpdb->prefix}vefify_campaigns c ON p.campaign_id = c.id
             WHERE p.id = %d",
            $participant_id
        ), ARRAY_A);
        
        if (!$participant) {
            wp_send_json_error(array('message' => 'Participant not found'));
        }
        
        // Generate email content
        $email_content = $this->generate_results_email($participant);
        
        // Send email
        $sent = wp_mail(
            $email,
            'Quiz Results - ' . $participant['campaign_name'],
            $email_content,
            array('Content-Type: text/html; charset=UTF-8')
        );
        
        if ($sent) {
            wp_send_json_success(array('message' => 'Results sent successfully!'));
        } else {
            wp_send_json_error(array('message' => 'Failed to send email'));
        }
    }
    
    /**
     * AJAX: Get quiz statistics
     */
    public function ajax_get_quiz_stats() {
        $campaign_id = intval($_POST['campaign_id']);
        
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_participants,
                COUNT(CASE WHEN quiz_status = 'completed' THEN 1 END) as completed,
                AVG(CASE WHEN quiz_status = 'completed' THEN final_score END) as avg_score,
                MAX(final_score) as max_score,
                COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as gifts_awarded
            FROM {$wpdb->prefix}vefify_participants
            WHERE campaign_id = %d",
            $campaign_id
        ), ARRAY_A);
        
        $completion_rate = $stats['total_participants'] > 0 ? 
            round(($stats['completed'] / $stats['total_participants']) * 100, 1) : 0;
        
        wp_send_json_success(array(
            'total_participants' => intval($stats['total_participants']),
            'completed' => intval($stats['completed']),
            'completion_rate' => $completion_rate,
            'avg_score' => round($stats['avg_score'], 1),
            'max_score' => intval($stats['max_score']),
            'gifts_awarded' => intval($stats['gifts_awarded'])
        ));
    }
    
    /**
     * AJAX: Validate pharmacy code
     */
    public function ajax_validate_pharmacy() {
        $pharmacy_code = sanitize_text_field($_POST['pharmacy_code']);
        
        // You can implement custom pharmacy code validation here
        // For now, we'll just check format
        $is_valid = !empty($pharmacy_code) && strlen($pharmacy_code) >= 3;
        
        // Optional: Call external API to validate pharmacy code
        $api_validation = $this->validate_pharmacy_code_api($pharmacy_code);
        
        wp_send_json_success(array(
            'valid' => $is_valid && $api_validation,
            'message' => $is_valid ? 'Valid pharmacy code' : 'Invalid pharmacy code format'
        ));
    }
    
    /**
     * Call external gift API
     */
    private function call_gift_api($gift, $participant) {
        if (empty($gift['api_endpoint'])) {
            return null;
        }
        
        $api_params = json_decode($gift['api_params'], true) ?: array();
        
        // Prepare API data
        $api_data = array_merge($api_params, array(
            'gift_code' => $participant['gift_code'],
            'participant_name' => $participant['participant_name'],
            'participant_phone' => $participant['participant_phone'],
            'participant_email' => $participant['participant_email'],
            'gift_value' => $gift['gift_value'],
            'campaign_id' => $participant['campaign_id']
        ));
        
        // Make API call
        $response = wp_remote_post($gift['api_endpoint'], array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Vefify-Quiz-Plugin/1.0'
            ),
            'body' => json_encode($api_data)
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', 'API returned error: ' . $response_code);
        }
        
        return json_decode($response_body, true);
    }
    
    /**
     * Log gift claim event
     */
    private function log_gift_claim($participant_id, $gift_id, $api_response) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'vefify_analytics',
            array(
                'campaign_id' => 0, // Will be updated with actual campaign_id
                'event_type' => 'gift_claim',
                'participant_id' => $participant_id,
                'event_data' => json_encode(array(
                    'gift_id' => $gift_id,
                    'api_response' => $api_response,
                    'timestamp' => current_time('mysql')
                )),
                'ip_address' => Vefify_Quiz_Utilities::get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Send gift confirmation email
     */
    private function send_gift_confirmation_email($participant, $gift, $api_response) {
        if (empty($participant['participant_email'])) {
            return;
        }
        
        $subject = 'Gift Confirmation - ' . $gift['gift_name'];
        
        $message = "
        <html>
        <body>
            <h2>Congratulations!</h2>
            <p>Dear {$participant['participant_name']},</p>
            <p>Your gift has been successfully claimed!</p>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>Gift Details:</h3>
                <p><strong>Gift:</strong> {$gift['gift_name']}</p>
                <p><strong>Value:</strong> {$gift['gift_value']}</p>
                <p><strong>Code:</strong> {$participant['gift_code']}</p>
            </div>
            
            <p>Thank you for participating in our quiz!</p>
        </body>
        </html>
        ";
        
        wp_mail(
            $participant['participant_email'],
            $subject,
            $message,
            array('Content-Type: text/html; charset=UTF-8')
        );
    }
    
    /**
     * Generate certificate HTML
     */
    private function generate_certificate_html($data) {
        ob_start();
        ?>
        <div class="vefify-certificate" style="width: 800px; height: 600px; border: 5px solid #2c3e50; padding: 40px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-family: serif;">
            <h1 style="font-size: 36px; margin-bottom: 30px;">CERTIFICATE OF COMPLETION</h1>
            
            <div style="margin: 40px 0;">
                <p style="font-size: 18px;">This is to certify that</p>
                <h2 style="font-size: 32px; margin: 20px 0; text-transform: uppercase; border-bottom: 2px solid white; padding-bottom: 10px;"><?php echo esc_html($data['participant_name']); ?></h2>
                <p style="font-size: 18px;">has successfully completed</p>
                <h3 style="font-size: 24px; margin: 20px 0;"><?php echo esc_html($data['campaign_name']); ?></h3>
            </div>
            
            <div style="margin: 40px 0;">
                <p style="font-size: 16px;">Score: <strong><?php echo $data['score']; ?>/<?php echo $data['total_questions']; ?> (<?php echo $data['percentage']; ?>%)</strong></p>
                <p style="font-size: 16px;">Completed on: <strong><?php echo date('F j, Y', strtotime($data['completion_date'])); ?></strong></p>
                <?php if ($data['ranking']): ?>
                    <p style="font-size: 16px;">Ranking: <strong><?php echo $data['ranking']['ranking']; ?> of <?php echo $data['ranking']['total']; ?></strong></p>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 60px;">
                <p style="font-size: 14px; opacity: 0.8;">Certificate ID: <?php echo esc_html($data['certificate_id']); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate certificate PDF (placeholder)
     */
    private function generate_certificate_pdf($data) {
        // This would integrate with a PDF library like TCPDF or DOMPDF
        // For now, return a placeholder URL
        return '#'; // Implementation depends on your PDF library choice
    }
    
    /**
     * Generate results email content
     */
    private function generate_results_email($participant) {
        $percentage = $participant['total_questions'] > 0 ? 
            round(($participant['final_score'] / $participant['total_questions']) * 100, 1) : 0;
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #2c3e50; text-align: center;'>Quiz Results</h2>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3>Dear {$participant['participant_name']},</h3>
                    <p>Thank you for participating in <strong>{$participant['campaign_name']}</strong>!</p>
                </div>
                
                <div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0;'>Your Results:</h3>
                    <ul style='list-style: none; padding: 0;'>
                        <li style='padding: 5px 0;'><strong>Score:</strong> {$participant['final_score']} out of {$participant['total_questions']}</li>
                        <li style='padding: 5px 0;'><strong>Percentage:</strong> {$percentage}%</li>
                        <li style='padding: 5px 0;'><strong>Completed:</strong> " . date('F j, Y g:i A', strtotime($participant['end_time'])) . "</li>
                    </ul>
                </div>
                
                " . ($participant['gift_code'] ? "
                <div style='background: #f1f8e9; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #2e7d32;'>🎁 Congratulations!</h3>
                    <p>You've earned a gift! Your gift code is: <strong style='font-family: monospace; background: #fff; padding: 4px 8px; border-radius: 4px;'>{$participant['gift_code']}</strong></p>
                </div>
                " : "") . "
                
                <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>
                    <p style='color: #666; font-size: 14px;'>Thank you for your participation!</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Validate pharmacy code via API (placeholder)
     */
    private function validate_pharmacy_code_api($pharmacy_code) {
        // Implement your pharmacy validation API call here
        // For now, return true for codes longer than 3 characters
        return strlen($pharmacy_code) >= 3;
    }
}

// Initialize AJAX handlers
Vefify_Quiz_Ajax_Handlers::get_instance();