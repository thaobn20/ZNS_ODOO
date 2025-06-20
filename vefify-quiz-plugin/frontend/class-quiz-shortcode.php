<?php
/**
 * 🇻🇳 VIETNAMESE PHONE NUMBER FORMAT - Updated Shortcode
 * File: frontend/class-quiz-shortcode.php
 * 
 * Fixed to use Vietnamese phone number format and validation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Shortcode {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    /**
     * Register ALL quiz shortcodes here to prevent conflicts
     */
    public function register_shortcodes() {
        // Debug and test shortcodes
        add_shortcode('vefify_simple_test', array($this, 'simple_test'));
        add_shortcode('vefify_test', array($this, 'debug_test'));
        
        // Main quiz shortcodes
        add_shortcode('vefify_quiz', array($this, 'render_quiz'));
        add_shortcode('vefify_campaign', array($this, 'render_campaign_info'));
        add_shortcode('vefify_campaign_list', array($this, 'render_campaign_list'));
    }
    
    /**
     * 🧪 SIMPLE TEST (Keep working)
     */
    public function simple_test($atts) {
        return '<div style="padding: 20px; background: #f0f8ff; border: 2px solid #007cba; border-radius: 8px; margin: 20px 0;">✅ Simple Test Working! Current time: ' . current_time('mysql') . '</div>';
    }
    
    /**
     * 🔍 DEBUG TEST
     */
    public function debug_test($atts) {
        global $wpdb;
        $campaigns_table = $wpdb->prefix . 'vefify_campaigns';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$campaigns_table}");
        
        return '<div style="padding: 20px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; margin: 20px 0;">
            <h3>🔍 Debug Info</h3>
            <p><strong>Campaigns in database:</strong> ' . ($count ?: 'No table found') . '</p>
            <p><strong>Current time:</strong> ' . current_time('mysql') . '</p>
            <p><strong>Shortcodes registered:</strong> vefify_quiz, vefify_campaign, vefify_campaign_list</p>
            <p><strong>Phone format:</strong> Vietnamese (0xxx xxx xxx)</p>
        </div>';
    }
    
    /**
     * 🎯 MAIN QUIZ SHORTCODE
     * [vefify_quiz campaign_id="1" fields="name,email,phone,company"]
     */
    public function render_quiz($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'fields' => 'name,email,phone,company'
        ), $atts);
        
        $campaign_id = intval($atts['campaign_id']);
        
        if (!$campaign_id) {
            return '<div class="error-box">❌ Campaign ID required. Usage: [vefify_quiz campaign_id="1"]</div>';
        }
        
        // Get campaign data
        $campaign = $this->get_campaign_data($campaign_id);
        if (!$campaign) {
            return '<div class="error-box">❌ Campaign not found (ID: ' . $campaign_id . ')</div>';
        }
        
        // Parse fields
        $requested_fields = array_map('trim', explode(',', $atts['fields']));
        
        ob_start();
        ?>
        
        <!-- SIMPLE LAYOUT WITH VIETNAMESE PHONE -->
        <div class="simple-quiz-container">
            
            <!-- Title -->
            <h2 class="quiz-title"><?php echo esc_html($campaign['name']); ?></h2>
            
            <!-- Info Pills -->
            <div class="quiz-info">
                <span class="info-pill">📝 <?php echo $campaign['questions_per_quiz']; ?> Câu hỏi</span>
                <span class="info-pill">⏱️ <?php echo round($campaign['time_limit'] / 60); ?> phút</span>
                <span class="info-pill">🎯 Điểm đậu: <?php echo $campaign['pass_score']; ?></span>
            </div>
            
            <!-- Registration Form -->
            <div class="registration-box">
                <h3>📋 Nhập thông tin của bạn</h3>
                
                <form id="quiz-form" class="simple-form">
                    <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
                    
                    <?php foreach ($requested_fields as $field): ?>
                        <?php echo $this->render_vietnamese_field($field); ?>
                    <?php endforeach; ?>
                    
                    <button type="submit" class="start-btn">🚀 Bắt đầu làm bài</button>
                </form>
            </div>
            
            <!-- Debug -->
            <div class="debug-box">
                <small>
                    <strong>Debug:</strong> Campaign ID: <?php echo $campaign_id; ?> | 
                    Fields: <?php echo esc_html($atts['fields']); ?> | 
                    Status: <?php echo $this->is_campaign_active($campaign) ? 'Active' : 'Inactive'; ?> |
                    Phone: Vietnamese format
                </small>
            </div>
            
        </div>
        
        <?php echo $this->get_vietnamese_styles(); ?>
        <?php echo $this->get_vietnamese_scripts(); ?>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * 📱 RENDER VIETNAMESE FIELD (with proper phone formatting)
     */
    private function render_vietnamese_field($field) {
        $field = trim($field);
        
        switch ($field) {
            case 'name':
                return '<div class="form-field">
                    <label for="name">Họ và tên <span class="required">*</span></label>
                    <input type="text" name="name" id="name" placeholder="Nhập họ và tên đầy đủ" required>
                </div>';
                
            case 'email':
                return '<div class="form-field">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" name="email" id="email" placeholder="ten@email.com" required>
                </div>';
                
            case 'phone':
                return '<div class="form-field">
                    <label for="phone">Số điện thoại <span class="required">*</span></label>
                    <input type="tel" name="phone" id="phone" placeholder="0938474356" 
                           pattern="^(0[0-9]{9}|84[0-9]{9})$" 
                           maxlength="11" 
                           data-format="vietnamese"
                           required>
                    <small class="field-help">Định dạng: 0938474356 hoặc 84938474356</small>
                </div>';
                
            case 'company':
                return '<div class="form-field">
                    <label for="company">Công ty/Tổ chức</label>
                    <input type="text" name="company" id="company" placeholder="Tên công ty (không bắt buộc)">
                </div>';
                
            case 'province':
                return '<div class="form-field">
                    <label for="province">Tỉnh/Thành phố</label>
                    <select name="province" id="province">
                        <option value="">Chọn tỉnh/thành phố</option>
                        <option value="hanoi">Hà Nội</option>
                        <option value="hcmc">TP. Hồ Chí Minh</option>
                        <option value="danang">Đà Nẵng</option>
                        <option value="haiphong">Hải Phòng</option>
                        <option value="cantho">Cần Thơ</option>
                        <option value="bacninh">Bắc Ninh</option>
                        <option value="binhduong">Bình Dương</option>
                        <option value="dongnai">Đồng Nai</option>
                        <option value="quangninh">Quảng Ninh</option>
                        <option value="khanhhoa">Khánh Hòa</option>
                        <option value="other">Khác</option>
                    </select>
                </div>';
                
            case 'pharmacy_code':
                return '<div class="form-field">
                    <label for="pharmacy_code">Mã số dược sĩ</label>
                    <input type="text" name="pharmacy_code" id="pharmacy_code" 
                           placeholder="Nhập mã số dược sĩ" 
                           data-format="pharmacy">
                    <small class="field-help">Có thể nhập chữ và số</small>
                </div>';
                
            case 'occupation':
                return '<div class="form-field">
                    <label for="occupation">Nghề nghiệp</label>
                    <select name="occupation" id="occupation">
                        <option value="">Chọn nghề nghiệp</option>
                        <option value="pharmacist">Dược sĩ</option>
                        <option value="doctor">Bác sĩ</option>
                        <option value="nurse">Y tá/Điều dưỡng</option>
                        <option value="student">Sinh viên</option>
                        <option value="teacher">Giáo viên</option>
                        <option value="business">Kinh doanh</option>
                        <option value="other">Khác</option>
                    </select>
                </div>';
                
            case 'age':
                return '<div class="form-field">
                    <label for="age">Tuổi</label>
                    <input type="number" name="age" id="age" placeholder="25" min="18" max="100">
                </div>';
                
            default:
                return '<div class="form-field">
                    <label for="' . esc_attr($field) . '">' . esc_html(ucfirst(str_replace('_', ' ', $field))) . '</label>
                    <input type="text" name="' . esc_attr($field) . '" id="' . esc_attr($field) . '" placeholder="Nhập ' . esc_attr(str_replace('_', ' ', $field)) . '">
                </div>';
        }
    }
    
    /**
     * 📊 CAMPAIGN INFO SHORTCODE (Vietnamese labels)
     */
    public function render_campaign_info($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'campaign_id' => 0,
            'show_description' => 'true',
            'show_stats' => 'false'
        ), $atts);
        
        $campaign_id = intval($atts['id'] ?: $atts['campaign_id']);
        
        if (!$campaign_id) {
            return '<div class="error-box">❌ Cần ID chiến dịch. Sử dụng: [vefify_campaign id="1"]</div>';
        }
        
        $campaign = $this->get_campaign_data($campaign_id);
        if (!$campaign) {
            return '<div class="error-box">❌ Không tìm thấy chiến dịch (ID: ' . $campaign_id . ')</div>';
        }
        
        ob_start();
        ?>
        <div class="vefify-campaign-info">
            <h3 class="campaign-title"><?php echo esc_html($campaign['name']); ?></h3>
            
            <?php if ($atts['show_description'] === 'true' && $campaign['description']): ?>
                <div class="campaign-description">
                    <?php echo wpautop(esc_html($campaign['description'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="campaign-meta">
                <span class="campaign-duration">
                    <strong>📅 Thời gian:</strong> 
                    <?php echo date('d/m/Y', strtotime($campaign['start_date'])); ?> - 
                    <?php echo date('d/m/Y', strtotime($campaign['end_date'])); ?>
                </span>
                <span class="campaign-details">
                    <strong>📝 Câu hỏi:</strong> <?php echo $campaign['questions_per_quiz']; ?> |
                    <strong>⏱️ Thời gian:</strong> <?php echo round($campaign['time_limit'] / 60); ?> phút |
                    <strong>🎯 Điểm đậu:</strong> <?php echo $campaign['pass_score']; ?>
                </span>
            </div>
            
            <div class="campaign-actions">
                <a href="?quiz=<?php echo $campaign_id; ?>" class="button campaign-join-btn">
                    🚀 Tham gia làm bài
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 📋 CAMPAIGN LIST SHORTCODE (Vietnamese)
     */
    public function render_campaign_list($atts) {
        $atts = shortcode_atts(array(
            'limit' => '10',
            'status' => 'active',
            'show_description' => 'true',
            'show_stats' => 'false',
            'style' => 'grid'
        ), $atts);
        
        $campaigns = $this->get_campaigns_list(intval($atts['limit']), $atts['status']);
        
        if (empty($campaigns)) {
            return '<div class="info-box">📋 Hiện tại chưa có chiến dịch nào khả dụng.</div>';
        }
        
        ob_start();
        ?>
        <div class="vefify-campaigns-list style-<?php echo esc_attr($atts['style']); ?>">
            <h3 class="campaigns-title">🎯 Các cuộc thi có sẵn</h3>
            
            <div class="campaigns-grid">
                <?php foreach ($campaigns as $campaign): ?>
                    <div class="campaign-card">
                        <h4 class="campaign-name">
                            <a href="?quiz=<?php echo $campaign['id']; ?>">
                                <?php echo esc_html($campaign['name']); ?>
                            </a>
                        </h4>
                        
                        <div class="campaign-info">
                            <span class="info-item">📝 <?php echo $campaign['questions_per_quiz']; ?> câu hỏi</span>
                            <span class="info-item">⏱️ <?php echo round($campaign['time_limit'] / 60); ?> phút</span>
                            <span class="info-item">🎯 Điểm đậu: <?php echo $campaign['pass_score']; ?></span>
                        </div>
                        
                        <div class="campaign-card-actions">
                            <a href="?quiz=<?php echo $campaign['id']; ?>" class="campaign-btn">
                                🚀 Bắt đầu
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 📊 GET CAMPAIGN DATA
     */
    private function get_campaign_data($campaign_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vefify_campaigns';
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $campaign_id
        ), ARRAY_A);
        
        return $campaign;
    }
    
    /**
     * 📋 GET CAMPAIGNS LIST
     */
    private function get_campaigns_list($limit = 10, $status = 'active') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vefify_campaigns';
        
        $where_clause = '';
        if ($status === 'active') {
            $where_clause = "WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()";
        } elseif ($status === 'inactive') {
            $where_clause = "WHERE is_active = 0";
        }
        
        $campaigns = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d",
            $limit
        ), ARRAY_A);
        
        return $campaigns;
    }
    
    /**
     * ✅ CHECK IF CAMPAIGN IS ACTIVE
     */
    private function is_campaign_active($campaign) {
        if (!$campaign) return false;
        
        $now = current_time('mysql');
        return ($campaign['is_active'] == 1 && 
                $campaign['start_date'] <= $now && 
                $campaign['end_date'] >= $now);
    }
    
    /**
     * 🇻🇳 GET VIETNAMESE STYLES
     */
    private function get_vietnamese_styles() {
        return '<style>
        .simple-quiz-container {
            max-width: 600px;
            margin: 30px auto;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        
        .quiz-title {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 2em;
        }
        
        .quiz-info {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .info-pill {
            background: #e8f4fd;
            color: #007cba;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .registration-box {
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .registration-box h3 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #495057;
            text-align: center;
        }
        
        .simple-form {
            display: grid;
            gap: 20px;
        }
        
        .form-field {
            display: flex;
            flex-direction: column;
        }
        
        .form-field label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        .form-field input,
        .form-field select {
            padding: 12px 16px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-field input:focus,
        .form-field select:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
        }
        
        /* Vietnamese phone input styling */
        .form-field input[data-format="vietnamese"] {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 3 2\'%3e%3cpath fill=\'%23da251d\' d=\'M0 0h3v2H0z\'/%3e%3cpolygon fill=\'%23ff0\' points=\'1.5,.5 1.8,.9 1.2,.7 1.8,.7 1.2,.9\'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: 12px center;
            background-size: 20px 13px;
            padding-left: 45px;
        }
        
        .field-help {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .start-btn {
            background: linear-gradient(135deg, #007cba 0%, #0056b3 100%);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .start-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 124, 186, 0.3);
        }
        
        .debug-box {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            text-align: center;
        }
        
        .error-box, .info-box {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            font-weight: 500;
        }
        
        .error-box {
            background: #f8d7da;
            color: #721c24;
        }
        
        .info-box {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        /* Campaign info styles */
        .vefify-campaign-info { 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            padding: 20px; 
            margin: 20px 0; 
            background: #fff; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .campaign-title { margin: 0 0 15px; color: #0073aa; }
        .campaign-description { margin: 15px 0; line-height: 1.6; color: #555; }
        .campaign-meta { margin: 15px 0; font-size: 14px; color: #666; }
        .campaign-meta span { display: block; margin: 5px 0; }
        .campaign-actions { margin: 20px 0 0; text-align: center; }
        .campaign-join-btn { 
            background: #0073aa; 
            color: white; 
            padding: 12px 24px; 
            text-decoration: none; 
            border-radius: 6px; 
            display: inline-block;
            font-weight: 600;
        }
        .campaign-join-btn:hover { background: #005a87; color: white; }
        
        /* Campaign list styles */
        .vefify-campaigns-list { margin: 20px 0; }
        .campaigns-title { text-align: center; color: #2c3e50; margin-bottom: 25px; }
        .campaigns-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .campaign-card { background: #fff; border: 1px solid #e1e5e9; border-radius: 8px; padding: 20px; transition: transform 0.2s ease, box-shadow 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .campaign-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .campaign-name { margin: 0 0 10px; }
        .campaign-name a { text-decoration: none; color: #0073aa; }
        .campaign-info { margin: 15px 0; display: flex; flex-wrap: wrap; gap: 8px; }
        .info-item { background: #f8f9fa; padding: 4px 8px; border-radius: 12px; font-size: 12px; color: #495057; }
        .campaign-card-actions { margin: 15px 0 0; text-align: center; }
        .campaign-btn { background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 600; display: inline-block; }
        .campaign-btn:hover { background: #005a87; color: white; }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .simple-quiz-container {
                margin: 20px;
                padding: 0;
            }
            
            .registration-box {
                padding: 20px;
            }
            
            .quiz-info {
                flex-direction: column;
                align-items: center;
            }
            
            .quiz-title {
                font-size: 1.6em;
            }
            
            /* Adjust phone input on mobile */
            .form-field input[data-format="vietnamese"] {
                background-size: 16px 10px;
                background-position: 10px center;
                padding-left: 35px;
            }
        }
        </style>';
    }
    
    /**
     * 🇻🇳 GET VIETNAMESE SCRIPTS (with phone formatting)
     */
    private function get_vietnamese_scripts() {
        return '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const form = document.getElementById("quiz-form");
            const phoneInput = document.querySelector("input[data-format=\"vietnamese\"]");
            const pharmacyInput = document.querySelector("input[data-format=\"pharmacy\"]");
            
            // Vietnamese phone number formatting (no spaces)
            if (phoneInput) {
                phoneInput.addEventListener("input", function(e) {
                    let value = e.target.value.replace(/\D/g, "");
                    
                    // Limit to 11 digits max
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                    
                    // No automatic formatting - keep numbers only
                    e.target.value = value;
                });
                
                // Phone validation on blur
                phoneInput.addEventListener("blur", function(e) {
                    const value = e.target.value;
                    // Accept: 0xxxxxxxxx (10 digits) or 84xxxxxxxxx (11 digits)
                    const isValid = /^(0[0-9]{9}|84[0-9]{9})$/.test(value);
                    
                    if (value && !isValid) {
                        e.target.style.borderColor = "#dc3545";
                        e.target.style.backgroundColor = "#fff5f5";
                    } else {
                        e.target.style.borderColor = "#ced4da";
                        e.target.style.backgroundColor = "#fff";
                    }
                });
            }
            
            // Pharmacy code - allow any text and numbers
            if (pharmacyInput) {
                pharmacyInput.addEventListener("input", function(e) {
                    // No formatting restrictions - allow any text and numbers
                    let value = e.target.value;
                    e.target.value = value;
                });
            }
            
            // Form validation
            if (form) {
                form.addEventListener("submit", function(e) {
                    e.preventDefault();
                    
                    // Validate required fields
                    const name = form.querySelector("[name=\"name\"]");
                    const email = form.querySelector("[name=\"email\"]");
                    const phone = form.querySelector("[name=\"phone\"]");
                    
                    if (name && !name.value.trim()) {
                        alert("Vui lòng nhập họ và tên");
                        name.focus();
                        return;
                    }
                    
                    if (email && !email.value.trim()) {
                        alert("Vui lòng nhập địa chỉ email");
                        email.focus();
                        return;
                    }
                    
                    if (phone && !phone.value.trim()) {
                        alert("Vui lòng nhập số điện thoại");
                        phone.focus();
                        return;
                    }
                    
                    // Validate Vietnamese phone number
                    if (phone && phone.value.trim()) {
                        const phoneValue = phone.value.trim();
                        // Accept: 0xxxxxxxxx (10 digits) or 84xxxxxxxxx (11 digits)
                        const isValidPhone = /^(0[0-9]{9}|84[0-9]{9})$/.test(phoneValue);
                        
                        if (!isValidPhone) {
                            alert("Số điện thoại không đúng định dạng. Vui lòng nhập: 0938474356 hoặc 84938474356");
                            phone.focus();
                            return;
                        }
                    }
                    
                    // Success message
                    alert("✅ Thông tin hợp lệ!\\n\\nBước tiếp theo: Kết nối AJAX để bắt đầu làm bài.");
                    
                    console.log("Form data:", new FormData(form));
                });
            }
        });
        </script>';
    }
    
    /**
     * 📚 ENQUEUE ASSETS (Minimal)
     */
    public function enqueue_frontend_assets() {
        global $post;
        
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'vefify_quiz') ||
            has_shortcode($post->post_content, 'vefify_campaign') ||
            has_shortcode($post->post_content, 'vefify_campaign_list')
        )) {
            wp_enqueue_script('jquery');
        }
    }
}

// Initialize
add_action('plugins_loaded', function() {
    Vefify_Quiz_Shortcode::get_instance();
});