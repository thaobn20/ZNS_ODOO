<?php
if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Shortcode {
    
    private static $instance = null;
    private $shortcode_registered = false;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Prevent multiple shortcode registration
        if (!$this->shortcode_registered) {
            add_shortcode('vefify_quiz', array($this, 'render_quiz'));
            $this->shortcode_registered = true;
        }
        
        // AJAX handlers
        add_action('wp_ajax_vefify_start_quiz', array($this, 'ajax_start_quiz'));
        add_action('wp_ajax_nopriv_vefify_start_quiz', array($this, 'ajax_start_quiz'));
    }
    
    /**
     * Render quiz shortcode
     */
    public function render_quiz($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 1,
            'template' => 'mobile'
        ), $atts, 'vefify_quiz');
        
        $campaign_id = intval($atts['campaign_id']);
        
        // Get campaign data
        $campaign = $this->get_campaign_data($campaign_id);
        
        if (!$campaign) {
            return '<div style="padding: 20px; border: 2px solid #e74c3c; border-radius: 8px; background: #fdf2f2; color: #e74c3c; text-align: center;">
                <h3>❌ Campaign Not Found</h3>
                <p>Campaign ID ' . esc_html($campaign_id) . ' not found or inactive.</p>
            </div>';
        }
        
        // Enqueue assets
        $this->enqueue_assets();
        
        // Render quiz interface
        ob_start();
        $this->render_quiz_interface($campaign);
        return ob_get_clean();
    }
    
    /**
     * Get campaign data
     */
    private function get_campaign_data($campaign_id) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_prefix}campaigns WHERE id = %d AND is_active = 1",
            $campaign_id
        ), ARRAY_A);
        
        return $campaign;
    }
    
    /**
     * Render quiz interface
     */
    private function render_quiz_interface($campaign) {
        ?>
        <div class="vefify-quiz-container" data-campaign-id="<?php echo esc_attr($campaign['id']); ?>">
            <!-- Header -->
            <div class="vefify-header">
                <h2 class="campaign-title"><?php echo esc_html($campaign['name']); ?></h2>
                <p class="campaign-description"><?php echo esc_html($campaign['description']); ?></p>
                <div class="campaign-meta">
                    <span class="meta-item">📝 <?php echo intval($campaign['questions_per_quiz']); ?> Questions</span>
                    <span class="meta-item">⏰ <?php echo intval($campaign['time_limit'] / 60); ?> Min</span>
                    <span class="meta-item">🎯 Pass: <?php echo intval($campaign['pass_score']); ?></span>
                </div>
            </div>
            
            <!-- Form Section -->
            <div class="vefify-form-section">
                <h3>📋 Registration Form</h3>
                <form id="vefifyRegistrationForm" class="vefify-form">
                    <?php wp_nonce_field('vefify_quiz_nonce', 'quiz_nonce'); ?>
                    <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign['id']); ?>">
                    
                    <div class="form-row">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="form-row">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-row">
                        <label for="phone_number">Phone Number *</label>
                        <input type="tel" id="phone_number" name="phone_number" required 
                               placeholder="0901234567">
                    </div>
                    
                    <div class="form-row">
                        <label for="province">Province/City *</label>
                        <select id="province" name="province" required>
                            <option value="">Select province...</option>
                            <?php echo $this->get_vietnam_provinces(); ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label for="pharmacist_code">Pharmacist Code (Optional)</label>
                        <input type="text" id="pharmacist_code" name="pharmacist_code" 
                               placeholder="PH123456" maxlength="12">
                    </div>
                    
                    <div class="form-row">
                        <label for="company">Company (Optional)</label>
                        <input type="text" id="company" name="company">
                    </div>
                    
                    <button type="submit" class="submit-btn">🚀 Start Quiz</button>
                </form>
            </div>
            
            <!-- Loading Section -->
            <div class="vefify-loading" id="loadingSection" style="display: none;">
                <div class="spinner"></div>
                <p>Processing registration...</p>
            </div>
            
            <!-- Success Section -->
            <div class="vefify-success" id="successSection" style="display: none;">
                <div class="success-icon">✅</div>
                <h3>Registration Successful!</h3>
                <p>You have been registered for the quiz.</p>
                <button onclick="location.reload()" class="retry-btn">Take Another Quiz</button>
            </div>
            
            <!-- Error Section -->
            <div class="vefify-error" id="errorSection" style="display: none;">
                <div class="error-icon">❌</div>
                <h3>Error</h3>
                <p id="errorMessage">An error occurred.</p>
                <button onclick="showSection('form')" class="retry-btn">Try Again</button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get Vietnam provinces
     */
    private function get_vietnam_provinces() {
        $provinces = array(
            'Ho Chi Minh' => 'Ho Chi Minh City',
            'Ha Noi' => 'Hanoi',
            'Da Nang' => 'Da Nang',
            'Hai Phong' => 'Hai Phong',
            'Can Tho' => 'Can Tho',
            'An Giang' => 'An Giang',
            'Ba Ria Vung Tau' => 'Ba Ria - Vung Tau',
            'Bac Giang' => 'Bac Giang',
            'Bac Kan' => 'Bac Kan',
            'Bac Lieu' => 'Bac Lieu',
            'Bac Ninh' => 'Bac Ninh',
            'Ben Tre' => 'Ben Tre',
            'Binh Dinh' => 'Binh Dinh',
            'Binh Duong' => 'Binh Duong',
            'Binh Phuoc' => 'Binh Phuoc',
            'Binh Thuan' => 'Binh Thuan',
            'Ca Mau' => 'Ca Mau',
            'Cao Bang' => 'Cao Bang',
            'Dak Lak' => 'Dak Lak',
            'Dak Nong' => 'Dak Nong',
            'Dien Bien' => 'Dien Bien',
            'Dong Nai' => 'Dong Nai',
            'Dong Thap' => 'Dong Thap',
            'Gia Lai' => 'Gia Lai',
            'Ha Giang' => 'Ha Giang',
            'Ha Nam' => 'Ha Nam',
            'Ha Tinh' => 'Ha Tinh',
            'Hai Duong' => 'Hai Duong',
            'Hau Giang' => 'Hau Giang',
            'Hoa Binh' => 'Hoa Binh',
            'Hung Yen' => 'Hung Yen',
            'Khanh Hoa' => 'Khanh Hoa',
            'Kien Giang' => 'Kien Giang',
            'Kon Tum' => 'Kon Tum',
            'Lai Chau' => 'Lai Chau',
            'Lam Dong' => 'Lam Dong',
            'Lang Son' => 'Lang Son',
            'Lao Cai' => 'Lao Cai',
            'Long An' => 'Long An',
            'Nam Dinh' => 'Nam Dinh',
            'Nghe An' => 'Nghe An',
            'Ninh Binh' => 'Ninh Binh',
            'Ninh Thuan' => 'Ninh Thuan',
            'Phu Tho' => 'Phu Tho',
            'Phu Yen' => 'Phu Yen',
            'Quang Binh' => 'Quang Binh',
            'Quang Nam' => 'Quang Nam',
            'Quang Ngai' => 'Quang Ngai',
            'Quang Ninh' => 'Quang Ninh',
            'Quang Tri' => 'Quang Tri',
            'Soc Trang' => 'Soc Trang',
            'Son La' => 'Son La',
            'Tay Ninh' => 'Tay Ninh',
            'Thai Binh' => 'Thai Binh',
            'Thai Nguyen' => 'Thai Nguyen',
            'Thanh Hoa' => 'Thanh Hoa',
            'Thua Thien Hue' => 'Thua Thien Hue',
            'Tien Giang' => 'Tien Giang',
            'Tra Vinh' => 'Tra Vinh',
            'Tuyen Quang' => 'Tuyen Quang',
            'Vinh Long' => 'Vinh Long',
            'Vinh Phuc' => 'Vinh Phuc',
            'Yen Bai' => 'Yen Bai'
        );
        
        $options = '';
        foreach ($provinces as $value => $label) {
            $options .= '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        
        return $options;
    }
    
    /**
     * Enqueue assets
     */
    private function enqueue_assets() {
        wp_enqueue_script('jquery');
        
        // Add CSS
        wp_add_inline_style('wp-admin', '
        .vefify-quiz-container {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        
        .vefify-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .campaign-title {
            margin: 0 0 10px;
            font-size: 24px;
            font-weight: 600;
        }
        
        .campaign-description {
            margin: 0 0 20px;
            opacity: 0.9;
        }
        
        .campaign-meta {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 13px;
        }
        
        .vefify-form-section {
            padding: 30px 20px;
        }
        
        .vefify-form {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .form-row {
            margin-bottom: 20px;
        }
        
        .form-row label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-row input,
        .form-row select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        
        .form-row input:focus,
        .form-row select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .submit-btn, .retry-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .submit-btn:hover, .retry-btn:hover {
            transform: translateY(-2px);
        }
        
        .vefify-loading, .vefify-success, .vefify-error {
            padding: 40px 20px;
            text-align: center;
            display: none;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .success-icon, .error-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 600px) {
            .vefify-quiz-container {
                margin: 10px;
            }
            
            .campaign-meta {
                flex-direction: column;
                align-items: center;
            }
        }
        ');
        
        // Add JavaScript
        wp_add_inline_script('jquery', '
        function showSection(section) {
            jQuery(".vefify-form-section, .vefify-loading, .vefify-success, .vefify-error").hide();
            if (section === "form") {
                jQuery(".vefify-form-section").show();
            } else if (section === "loading") {
                jQuery(".vefify-loading").show();
            } else if (section === "success") {
                jQuery(".vefify-success").show();
            } else if (section === "error") {
                jQuery(".vefify-error").show();
            }
        }
        
        jQuery(document).ready(function($) {
            $("#vefifyRegistrationForm").on("submit", function(e) {
                e.preventDefault();
                
                showSection("loading");
                
                var formData = new FormData(this);
                formData.append("action", "vefify_start_quiz");
                
                $.ajax({
                    url: "' . admin_url('admin-ajax.php') . '",
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            showSection("success");
                        } else {
                            $("#errorMessage").text(response.data || "Registration failed");
                            showSection("error");
                        }
                    },
                    error: function() {
                        $("#errorMessage").text("Network error. Please try again.");
                        showSection("error");
                    }
                });
            });
            
            // Phone formatting
            $("#phone_number").on("input", function() {
                var value = $(this).val().replace(/\D/g, "");
                if (value.length > 11) value = value.substring(0, 11);
                $(this).val(value);
            });
            
            // Pharmacist code formatting
            $("#pharmacist_code").on("input", function() {
                var value = $(this).val().toUpperCase().replace(/[^A-Z0-9]/g, "");
                if (value.length > 12) value = value.substring(0, 12);
                $(this).val(value);
            });
        });
        ');
    }
    
    /**
     * AJAX handler for starting quiz
     */
    public function ajax_start_quiz() {
        if (!wp_verify_nonce($_POST['quiz_nonce'] ?? '', 'vefify_quiz_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        try {
            $campaign_id = intval($_POST['campaign_id'] ?? 0);
            
            $form_data = array(
                'full_name' => sanitize_text_field($_POST['full_name'] ?? ''),
                'email' => sanitize_email($_POST['email'] ?? ''),
                'phone_number' => sanitize_text_field($_POST['phone_number'] ?? ''),
                'province' => sanitize_text_field($_POST['province'] ?? ''),
                'pharmacist_code' => sanitize_text_field($_POST['pharmacist_code'] ?? ''),
                'company' => sanitize_text_field($_POST['company'] ?? '')
            );
            
            // Validate
            $errors = array();
            if (empty($form_data['full_name'])) $errors[] = 'Full name required';
            if (empty($form_data['email']) || !is_email($form_data['email'])) $errors[] = 'Valid email required';
            if (empty($form_data['phone_number'])) $errors[] = 'Phone number required';
            if (empty($form_data['province'])) $errors[] = 'Province required';
            
            if (!empty($errors)) {
                wp_send_json_error(implode(', ', $errors));
                return;
            }
            
            // Check duplicate
            global $wpdb;
            $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
            
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_prefix}quiz_users WHERE phone_number = %s AND campaign_id = %d",
                $form_data['phone_number'], $campaign_id
            ));
            
            if ($existing) {
                wp_send_json_error('Phone number already registered for this campaign');
                return;
            }
            
            // Create participant
            $participant_data = array_merge($form_data, array(
                'campaign_id' => $campaign_id,
                'session_id' => wp_generate_uuid4(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'created_at' => current_time('mysql')
            ));
            
            $result = $wpdb->insert($table_prefix . 'quiz_users', $participant_data);
            
            if ($result === false) {
                wp_send_json_error('Failed to register');
                return;
            }
            
            wp_send_json_success(array(
                'participant_id' => $wpdb->insert_id,
                'message' => 'Registration successful!'
            ));
            
        } catch (Exception $e) {
            error_log('Quiz start error: ' . $e->getMessage());
            wp_send_json_error('An error occurred');
        }
    }
}