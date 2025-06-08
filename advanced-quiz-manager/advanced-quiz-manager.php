<?php
/**
 * Plugin Name: Advanced Quiz Manager
 * Plugin URI: https://yourwebsite.com/advanced-quiz-manager
 * Description: Complete quiz plugin with campaigns, questions, gifts, analytics and Vietnamese provinces integration
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: advanced-quiz
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AQM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AQM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AQM_VERSION', '1.0.0');

// Main plugin class
class AdvancedQuizManager {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        load_plugin_textdomain('advanced-quiz', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        $this->init_database();
        $this->init_admin();
        $this->init_api();
        $this->init_frontend();
        $this->init_scripts();
        $this->init_shortcodes();
    }
    
    public function activate() {
        $this->create_tables();
        $this->create_default_data();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function init_database() {
        require_once AQM_PLUGIN_PATH . 'includes/class-database.php';
        new AQM_Database();
    }
    
    private function init_admin() {
        if (is_admin()) {
            require_once AQM_PLUGIN_PATH . 'admin/class-admin.php';
            new AQM_Admin();
        }
    }
    
    private function init_api() {
        require_once AQM_PLUGIN_PATH . 'includes/class-api.php';
        new AQM_API();
    }
    
    private function init_frontend() {
        require_once AQM_PLUGIN_PATH . 'public/class-frontend.php';
        new AQM_Frontend();
    }
    
    private function init_scripts() {
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
    }
    
    private function init_shortcodes() {
        add_shortcode('quiz_form', array($this, 'quiz_shortcode'));
        add_shortcode('quiz_results', array($this, 'results_shortcode'));
    }
    
    public function quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'style' => 'default'
        ), $atts);
        
        return $this->render_quiz_form($atts['campaign_id'], $atts['style']);
    }
    
    public function results_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'show_charts' => 'true'
        ), $atts);
        
        return $this->render_quiz_results($atts['campaign_id'], $atts['show_charts']);
    }
    
    public function admin_scripts($hook) {
        if (strpos($hook, 'quiz-manager') !== false) {
            wp_enqueue_script('aqm-admin-js', AQM_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), AQM_VERSION, true);
            wp_enqueue_style('aqm-admin-css', AQM_PLUGIN_URL . 'assets/css/admin.css', array(), AQM_VERSION);
            
            wp_localize_script('aqm-admin-js', 'aqm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqm_nonce'),
                'provinces_data' => $this->get_vietnam_provinces_json()
            ));
        }
    }
    
    public function frontend_scripts() {
        wp_enqueue_script('aqm-frontend-js', AQM_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), AQM_VERSION, true);
        wp_enqueue_style('aqm-frontend-css', AQM_PLUGIN_URL . 'assets/css/frontend.css', array(), AQM_VERSION);
        
        wp_localize_script('aqm-frontend-js', 'aqm_front', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aqm_front_nonce'),
            'provinces_data' => $this->get_vietnam_provinces_json()
        ));
    }
    
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Campaigns table
        $campaigns_table = $wpdb->prefix . 'aqm_campaigns';
        $sql_campaigns = "CREATE TABLE $campaigns_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            status enum('active','inactive','draft') DEFAULT 'draft',
            start_date datetime,
            end_date datetime,
            max_participants int(11) DEFAULT 0,
            settings longtext,
            created_by int(11),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Questions table
        $questions_table = $wpdb->prefix . 'aqm_questions';
        $sql_questions = "CREATE TABLE $questions_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            question_text text NOT NULL,
            question_type enum('multiple_choice','text','number','email','phone','date','provinces','districts','wards','rating','file_upload') NOT NULL,
            options longtext,
            is_required tinyint(1) DEFAULT 0,
            validation_rules longtext,
            order_index int(11) DEFAULT 0,
            points int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY order_index (order_index)
        ) $charset_collate;";
        
        // Responses table
        $responses_table = $wpdb->prefix . 'aqm_responses';
        $sql_responses = "CREATE TABLE $responses_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            user_id int(11),
            user_email varchar(255),
            user_name varchar(255),
            user_phone varchar(20),
            total_score int(11) DEFAULT 0,
            completion_time int(11),
            ip_address varchar(45),
            user_agent text,
            status enum('completed','abandoned','in_progress') DEFAULT 'in_progress',
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY user_id (user_id),
            KEY user_email (user_email),
            KEY status (status)
        ) $charset_collate;";
        
        // Answers table
        $answers_table = $wpdb->prefix . 'aqm_answers';
        $sql_answers = "CREATE TABLE $answers_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            response_id int(11) NOT NULL,
            question_id int(11) NOT NULL,
            answer_value longtext,
            answer_score int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY question_id (question_id)
        ) $charset_collate;";
        
        // Gifts table
        $gifts_table = $wpdb->prefix . 'aqm_gifts';
        $sql_gifts = "CREATE TABLE $gifts_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            image_url varchar(500),
            value decimal(10,2),
            quantity int(11) DEFAULT 0,
            min_score int(11) DEFAULT 0,
            probability decimal(5,2) DEFAULT 100.00,
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Gift awards table
        $gift_awards_table = $wpdb->prefix . 'aqm_gift_awards';
        $sql_gift_awards = "CREATE TABLE $gift_awards_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            response_id int(11) NOT NULL,
            gift_id int(11) NOT NULL,
            claim_code varchar(100),
            is_claimed tinyint(1) DEFAULT 0,
            claimed_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY gift_id (gift_id),
            KEY claim_code (claim_code)
        ) $charset_collate;";
        
        // Notifications table
        $notifications_table = $wpdb->prefix . 'aqm_notifications';
        $sql_notifications = "CREATE TABLE $notifications_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            type enum('welcome','completion','gift_won','reminder') NOT NULL,
            title varchar(255) NOT NULL,
            content text NOT NULL,
            popup_settings longtext,
            email_settings longtext,
            sms_settings longtext,
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";
        
        // Analytics table
        $analytics_table = $wpdb->prefix . 'aqm_analytics';
        $sql_analytics = "CREATE TABLE $analytics_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11),
            event_type varchar(50) NOT NULL,
            event_data longtext,
            user_id int(11),
            session_id varchar(100),
            ip_address varchar(45),
            user_agent text,
            page_url varchar(500),
            referrer varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_campaigns);
        dbDelta($sql_questions);
        dbDelta($sql_responses);
        dbDelta($sql_answers);
        dbDelta($sql_gifts);
        dbDelta($sql_gift_awards);
        dbDelta($sql_notifications);
        dbDelta($sql_analytics);
    }
    
    private function create_default_data() {
        global $wpdb;
        
        // Create sample campaign
        $campaigns_table = $wpdb->prefix . 'aqm_campaigns';
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table");
        
        if ($existing == 0) {
            $campaign_id = $wpdb->insert(
                $campaigns_table,
                array(
                    'title' => 'Sample Quiz Campaign',
                    'description' => 'This is a sample quiz campaign with Vietnamese provinces field',
                    'status' => 'active',
                    'start_date' => date('Y-m-d H:i:s'),
                    'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
                    'max_participants' => 1000,
                    'settings' => json_encode(array(
                        'allow_multiple_attempts' => false,
                        'show_results_immediately' => true,
                        'collect_email' => true,
                        'require_login' => false
                    )),
                    'created_by' => get_current_user_id()
                )
            );
            
            if ($campaign_id) {
                // Add sample questions
                $questions_table = $wpdb->prefix . 'aqm_questions';
                
                $sample_questions = array(
                    array(
                        'campaign_id' => $campaign_id,
                        'question_text' => 'What is your full name?',
                        'question_type' => 'text',
                        'is_required' => 1,
                        'order_index' => 1
                    ),
                    array(
                        'campaign_id' => $campaign_id,
                        'question_text' => 'Which province are you from?',
                        'question_type' => 'provinces',
                        'is_required' => 1,
                        'order_index' => 2,
                        'options' => json_encode(array(
                            'load_districts' => true,
                            'load_wards' => true,
                            'placeholder' => 'Select your province'
                        ))
                    ),
                    array(
                        'campaign_id' => $campaign_id,
                        'question_text' => 'Rate your experience (1-5 stars)',
                        'question_type' => 'rating',
                        'is_required' => 1,
                        'order_index' => 3,
                        'options' => json_encode(array(
                            'max_rating' => 5,
                            'icon' => 'star'
                        )),
                        'points' => 10
                    )
                );
                
                foreach ($sample_questions as $question) {
                    $wpdb->insert($questions_table, $question);
                }
                
                // Add sample gifts
                $gifts_table = $wpdb->prefix . 'aqm_gifts';
                $sample_gifts = array(
                    array(
                        'campaign_id' => $campaign_id,
                        'name' => 'Discount Code 10%',
                        'description' => 'Get 10% discount on your next purchase',
                        'value' => 100000,
                        'quantity' => 100,
                        'min_score' => 0,
                        'probability' => 50.00
                    ),
                    array(
                        'campaign_id' => $campaign_id,
                        'name' => 'Free Shipping Voucher',
                        'description' => 'Free shipping for orders over 500k',
                        'value' => 50000,
                        'quantity' => 200,
                        'min_score' => 5,
                        'probability' => 30.00
                    )
                );
                
                foreach ($sample_gifts as $gift) {
                    $wpdb->insert($gifts_table, $gift);
                }
            }
        }
    }
    
    private function get_vietnam_provinces_json() {
        // Vietnamese provinces data
        return array(
            array(
                'code' => '01',
                'name' => 'Hà Nội',
                'name_en' => 'Hanoi',
                'full_name' => 'Thành phố Hà Nội',
                'districts' => array(
                    array('code' => '001', 'name' => 'Ba Đình', 'name_en' => 'Ba Dinh'),
                    array('code' => '002', 'name' => 'Hoàn Kiếm', 'name_en' => 'Hoan Kiem'),
                    array('code' => '003', 'name' => 'Tây Hồ', 'name_en' => 'Tay Ho'),
                    array('code' => '004', 'name' => 'Long Biên', 'name_en' => 'Long Bien'),
                    array('code' => '005', 'name' => 'Cầu Giấy', 'name_en' => 'Cau Giay'),
                    array('code' => '006', 'name' => 'Đống Đa', 'name_en' => 'Dong Da'),
                    array('code' => '007', 'name' => 'Hai Bà Trưng', 'name_en' => 'Hai Ba Trung'),
                    array('code' => '008', 'name' => 'Hoàng Mai', 'name_en' => 'Hoang Mai'),
                    array('code' => '009', 'name' => 'Thanh Xuân', 'name_en' => 'Thanh Xuan')
                )
            ),
            array(
                'code' => '79',
                'name' => 'Hồ Chí Minh',
                'name_en' => 'Ho Chi Minh',
                'full_name' => 'Thành phố Hồ Chí Minh',
                'districts' => array(
                    array('code' => '760', 'name' => 'Quận 1', 'name_en' => 'District 1'),
                    array('code' => '761', 'name' => 'Quận 2', 'name_en' => 'District 2'),
                    array('code' => '762', 'name' => 'Quận 3', 'name_en' => 'District 3'),
                    array('code' => '763', 'name' => 'Quận 4', 'name_en' => 'District 4'),
                    array('code' => '764', 'name' => 'Quận 5', 'name_en' => 'District 5'),
                    array('code' => '765', 'name' => 'Quận 6', 'name_en' => 'District 6'),
                    array('code' => '766', 'name' => 'Quận 7', 'name_en' => 'District 7'),
                    array('code' => '767', 'name' => 'Quận 8', 'name_en' => 'District 8'),
                    array('code' => '768', 'name' => 'Quận 9', 'name_en' => 'District 9'),
                    array('code' => '769', 'name' => 'Quận 10', 'name_en' => 'District 10'),
                    array('code' => '770', 'name' => 'Quận 11', 'name_en' => 'District 11'),
                    array('code' => '771', 'name' => 'Quận 12', 'name_en' => 'District 12'),
                    array('code' => '772', 'name' => 'Thủ Đức', 'name_en' => 'Thu Duc'),
                    array('code' => '773', 'name' => 'Gò Vấp', 'name_en' => 'Go Vap'),
                    array('code' => '774', 'name' => 'Bình Thạnh', 'name_en' => 'Binh Thanh'),
                    array('code' => '775', 'name' => 'Tân Bình', 'name_en' => 'Tan Binh'),
                    array('code' => '776', 'name' => 'Tân Phú', 'name_en' => 'Tan Phu'),
                    array('code' => '777', 'name' => 'Phú Nhuận', 'name_en' => 'Phu Nhuan')
                )
            ),
            array(
                'code' => '48',
                'name' => 'Đà Nẵng',
                'name_en' => 'Da Nang',
                'full_name' => 'Thành phố Đà Nẵng',
                'districts' => array(
                    array('code' => '490', 'name' => 'Liên Chiểu', 'name_en' => 'Lien Chieu'),
                    array('code' => '491', 'name' => 'Thanh Khê', 'name_en' => 'Thanh Khe'),
                    array('code' => '492', 'name' => 'Hải Châu', 'name_en' => 'Hai Chau'),
                    array('code' => '493', 'name' => 'Sơn Trà', 'name_en' => 'Son Tra'),
                    array('code' => '494', 'name' => 'Ngũ Hành Sơn', 'name_en' => 'Ngu Hanh Son'),
                    array('code' => '495', 'name' => 'Cẩm Lệ', 'name_en' => 'Cam Le')
                )
            ),
            array(
                'code' => '92',
                'name' => 'Cần Thơ',
                'name_en' => 'Can Tho',
                'full_name' => 'Thành phố Cần Thơ',
                'districts' => array(
                    array('code' => '916', 'name' => 'Ninh Kiều', 'name_en' => 'Ninh Kieu'),
                    array('code' => '917', 'name' => 'Ô Môn', 'name_en' => 'O Mon'),
                    array('code' => '918', 'name' => 'Bình Thuỷ', 'name_en' => 'Binh Thuy'),
                    array('code' => '919', 'name' => 'Cái Răng', 'name_en' => 'Cai Rang'),
                    array('code' => '923', 'name' => 'Thốt Nốt', 'name_en' => 'Thot Not')
                )
            ),
            array(
                'code' => '31',
                'name' => 'Hải Phòng',
                'name_en' => 'Hai Phong',
                'full_name' => 'Thành phố Hải Phòng',
                'districts' => array(
                    array('code' => '303', 'name' => 'Lê Chân', 'name_en' => 'Le Chan'),
                    array('code' => '304', 'name' => 'Ngô Quyền', 'name_en' => 'Ngo Quyen'),
                    array('code' => '305', 'name' => 'Hồng Bàng', 'name_en' => 'Hong Bang'),
                    array('code' => '306', 'name' => 'Kiến An', 'name_en' => 'Kien An'),
                    array('code' => '307', 'name' => 'Hải An', 'name_en' => 'Hai An'),
                    array('code' => '308', 'name' => 'Đồ Sơn', 'name_en' => 'Do Son')
                )
            ),
            array(
                'code' => '24',
                'name' => 'Bắc Giang',
                'name_en' => 'Bac Giang',
                'full_name' => 'Tỉnh Bắc Giang',
                'districts' => array()
            ),
            array(
                'code' => '06',
                'name' => 'Bắc Kạn',
                'name_en' => 'Bac Kan',
                'full_name' => 'Tỉnh Bắc Kạn',
                'districts' => array()
            ),
            array(
                'code' => '27',
                'name' => 'Bắc Ninh',
                'name_en' => 'Bac Ninh',
                'full_name' => 'Tỉnh Bắc Ninh',
                'districts' => array()
            ),
            array(
                'code' => '83',
                'name' => 'Bến Tre',
                'name_en' => 'Ben Tre',
                'full_name' => 'Tỉnh Bến Tre',
                'districts' => array()
            ),
            array(
                'code' => '74',
                'name' => 'Bình Dương',
                'name_en' => 'Binh Duong',
                'full_name' => 'Tỉnh Bình Dương',
                'districts' => array()
            )
        );
    }
    
    private function render_quiz_form($campaign_id, $style) {
        if (empty($campaign_id)) {
            return '<p>Invalid campaign ID</p>';
        }
        
        $db = new AQM_Database();
        $campaign = $db->get_campaign($campaign_id);
        
        if (!$campaign) {
            return '<p>Campaign not found</p>';
        }
        
        $questions = $db->get_campaign_questions($campaign_id);
        
        ob_start();
        ?>
        <div class="aqm-quiz-container" data-campaign-id="<?php echo esc_attr($campaign_id); ?>" data-style="<?php echo esc_attr($style); ?>">
            <div class="aqm-quiz-header">
                <h2><?php echo esc_html($campaign->title); ?></h2>
                <?php if ($campaign->description): ?>
                    <p class="aqm-quiz-description"><?php echo esc_html($campaign->description); ?></p>
                <?php endif; ?>
            </div>
            
            <form class="aqm-quiz-form" id="aqm-quiz-form-<?php echo esc_attr($campaign_id); ?>">
                <?php wp_nonce_field('aqm_submit_quiz', 'aqm_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>">
                
                <?php foreach ($questions as $question): ?>
                    <div class="aqm-question-container" data-question-id="<?php echo esc_attr($question->id); ?>" data-question-type="<?php echo esc_attr($question->question_type); ?>">
                        <label class="aqm-question-label">
                            <?php echo esc_html($question->question_text); ?>
                            <?php if ($question->is_required): ?>
                                <span class="aqm-required">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php $this->render_question_field($question); ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="aqm-quiz-actions">
                    <button type="submit" class="aqm-submit-btn">Submit Quiz</button>
                </div>
            </form>
            
            <div class="aqm-quiz-result" id="aqm-quiz-result-<?php echo esc_attr($campaign_id); ?>" style="display: none;">
                <!-- Results will be loaded here -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_question_field($question) {
        $options = json_decode($question->options, true);
        
        switch ($question->question_type) {
            case 'provinces':
                echo '<select name="question_' . esc_attr($question->id) . '" class="aqm-provinces-select" ' . ($question->is_required ? 'required' : '') . '>';
                echo '<option value="">Select Province</option>';
                $provinces = $this->get_vietnam_provinces_json();
                foreach ($provinces as $province) {
                    echo '<option value="' . esc_attr($province['code']) . '">' . esc_html($province['name']) . '</option>';
                }
                echo '</select>';
                
                if (isset($options['load_districts']) && $options['load_districts']) {
                    echo '<select name="question_' . esc_attr($question->id) . '_district" class="aqm-districts-select" style="display:none;">';
                    echo '<option value="">Select District</option>';
                    echo '</select>';
                }
                
                if (isset($options['load_wards']) && $options['load_wards']) {
                    echo '<select name="question_' . esc_attr($question->id) . '_ward" class="aqm-wards-select" style="display:none;">';
                    echo '<option value="">Select Ward</option>';
                    echo '</select>';
                }
                break;
                
            case 'multiple_choice':
                if ($options && isset($options['choices'])) {
                    foreach ($options['choices'] as $choice) {
                        echo '<label class="aqm-radio-label">';
                        echo '<input type="radio" name="question_' . esc_attr($question->id) . '" value="' . esc_attr($choice['value']) . '" ' . ($question->is_required ? 'required' : '') . '>';
                        echo '<span>' . esc_html($choice['label']) . '</span>';
                        echo '</label>';
                    }
                }
                break;
                
            case 'rating':
                $max_rating = isset($options['max_rating']) ? $options['max_rating'] : 5;
                echo '<div class="aqm-rating-container">';
                for ($i = 1; $i <= $max_rating; $i++) {
                    echo '<label class="aqm-rating-label">';
                    echo '<input type="radio" name="question_' . esc_attr($question->id) . '" value="' . $i . '" ' . ($question->is_required ? 'required' : '') . '>';
                    echo '<span class="aqm-star">★</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;
                
            case 'text':
                echo '<input type="text" name="question_' . esc_attr($question->id) . '" class="aqm-text-input" ' . ($question->is_required ? 'required' : '') . '>';
                break;
                
            case 'email':
                echo '<input type="email" name="question_' . esc_attr($question->id) . '" class="aqm-email-input" ' . ($question->is_required ? 'required' : '') . '>';
                break;
                
            case 'phone':
                echo '<input type="tel" name="question_' . esc_attr($question->id) . '" class="aqm-phone-input" ' . ($question->is_required ? 'required' : '') . '>';
                break;
                
            case 'number':
                echo '<input type="number" name="question_' . esc_attr($question->id) . '" class="aqm-number-input" ' . ($question->is_required ? 'required' : '') . '>';
                break;
                
            case 'date':
                echo '<input type="date" name="question_' . esc_attr($question->id) . '" class="aqm-date-input" ' . ($question->is_required ? 'required' : '') . '>';
                break;
                
            default:
                echo '<input type="text" name="question_' . esc_attr($question->id) . '" class="aqm-text-input" ' . ($question->is_required ? 'required' : '') . '>';
                break;
        }
    }
    
    private function render_quiz_results($campaign_id, $show_charts) {
        // Implementation for displaying quiz results and analytics
        $db = new AQM_Database();
        $stats = $db->get_campaign_stats($campaign_id);
        
        ob_start();
        ?>
        <div class="aqm-results-container">
            <h3>Quiz Results</h3>
            <div class="aqm-stats-grid">
                <div class="aqm-stat-box">
                    <h4>Total Participants</h4>
                    <span class="aqm-stat-number"><?php echo esc_html($stats['total_participants']); ?></span>
                </div>
                <div class="aqm-stat-box">
                    <h4>Completion Rate</h4>
                    <span class="aqm-stat-number"><?php echo esc_html($stats['completion_rate']); ?>%</span>
                </div>
                <div class="aqm-stat-box">
                    <h4>Average Score</h4>
                    <span class="aqm-stat-number"><?php echo esc_html($stats['average_score']); ?></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Database class
class AQM_Database {
    
    public function get_campaign($campaign_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aqm_campaigns WHERE id = %d",
            $campaign_id
        ));
    }
    
    public function get_campaign_questions($campaign_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aqm_questions WHERE campaign_id = %d ORDER BY order_index ASC",
            $campaign_id
        ));
    }
    
    public function get_campaign_stats($campaign_id) {
        global $wpdb;
        
        $total_participants = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE campaign_id = %d",
            $campaign_id
        ));
        
        $completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE campaign_id = %d AND status = 'completed'",
            $campaign_id
        ));
        
        $completion_rate = $total_participants > 0 ? round(($completed / $total_participants) * 100, 2) : 0;
        
        $average_score = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(total_score) FROM {$wpdb->prefix}aqm_responses WHERE campaign_id = %d AND status = 'completed'",
            $campaign_id
        ));
        
        return array(
            'total_participants' => $total_participants,
            'completion_rate' => $completion_rate,
            'average_score' => round($average_score, 2)
        );
    }
    
    public function save_response($data) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'aqm_responses',
            $data,
            array('%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s')
        );
    }
    
    public function save_answer($response_id, $question_id, $answer_value, $score = 0) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'aqm_answers',
            array(
                'response_id' => $response_id,
                'question_id' => $question_id,
                'answer_value' => $answer_value,
                'answer_score' => $score
            ),
            array('%d', '%d', '%s', '%d')
        );
    }
    
    public function log_analytics($campaign_id, $event_type, $event_data, $user_id = null) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'aqm_analytics',
            array(
                'campaign_id' => $campaign_id,
                'event_type' => $event_type,
                'event_data' => json_encode($event_data),
                'user_id' => $user_id,
                'session_id' => session_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'page_url' => $_SERVER['REQUEST_URI']
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }
}

// Initialize the plugin
new AdvancedQuizManager();

// Admin class
class AQM_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_aqm_save_campaign', array($this, 'save_campaign'));
        add_action('wp_ajax_aqm_get_districts', array($this, 'get_districts'));
        add_action('wp_ajax_aqm_get_wards', array($this, 'get_wards'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Quiz Manager',
            'Quiz Manager',
            'manage_options',
            'quiz-manager',
            array($this, 'admin_page'),
            'dashicons-feedback',
            30
        );
        
        add_submenu_page(
            'quiz-manager',
            'Campaigns',
            'Campaigns',
            'manage_options',
            'quiz-manager-campaigns',
            array($this, 'campaigns_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Analytics',
            'Analytics',
            'manage_options',
            'quiz-manager-analytics',
            array($this, 'analytics_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Gifts',
            'Gifts',
            'manage_options',
            'quiz-manager-gifts',
            array($this, 'gifts_page')
        );
    }
    
    public function admin_page() {
        echo '<div class="wrap"><h1>Quiz Manager Dashboard</h1>';
        echo '<p>Welcome to the Quiz Manager plugin. Use the menu to manage campaigns, view analytics, and configure gifts.</p>';
        echo '</div>';
    }
    
    public function campaigns_page() {
        echo '<div class="wrap"><h1>Campaign Management</h1>';
        echo '<p>Create and manage quiz campaigns with Vietnamese provinces integration.</p>';
        echo '</div>';
    }
    
    public function analytics_page() {
        echo '<div class="wrap"><h1>Quiz Analytics</h1>';
        echo '<p>View detailed analytics and reports for your quiz campaigns.</p>';
        echo '</div>';
    }
    
    public function gifts_page() {
        echo '<div class="wrap"><h1>Gift Management</h1>';
        echo '<p>Manage gifts and rewards for quiz participants.</p>';
        echo '</div>';
    }
    
    public function get_districts() {
        check_ajax_referer('aqm_nonce', 'nonce');
        
        $province_code = sanitize_text_field($_POST['province_code']);
        $aqm = new AdvancedQuizManager();
        $provinces_data = $aqm->get_vietnam_provinces_json();
        
        $districts = array();
        foreach ($provinces_data as $province) {
            if ($province['code'] === $province_code && isset($province['districts'])) {
                $districts = $province['districts'];
                break;
            }
        }
        
        wp_send_json_success($districts);
    }
    
    public function get_wards() {
        check_ajax_referer('aqm_nonce', 'nonce');
        
        $district_code = sanitize_text_field($_POST['district_code']);
        // You would implement ward loading logic here
        
        wp_send_json_success(array());
    }
}

// API class for frontend AJAX
class AQM_API {
    
    public function __construct() {
        add_action('wp_ajax_aqm_submit_quiz', array($this, 'submit_quiz'));
        add_action('wp_ajax_nopriv_aqm_submit_quiz', array($this, 'submit_quiz'));
        add_action('wp_ajax_aqm_get_provinces', array($this, 'get_provinces'));
        add_action('wp_ajax_nopriv_aqm_get_provinces', array($this, 'get_provinces'));
    }
    
    public function submit_quiz() {
        check_ajax_referer('aqm_front_nonce', 'nonce');
        
        $campaign_id = intval($_POST['campaign_id']);
        $answers = $_POST['answers'];
        
        // Process quiz submission
        $db = new AQM_Database();
        
        // Save response
        $response_id = $db->save_response(array(
            'campaign_id' => $campaign_id,
            'user_email' => sanitize_email($_POST['user_email'] ?? ''),
            'user_name' => sanitize_text_field($_POST['user_name'] ?? ''),
            'status' => 'completed',
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ));
        
        // Save answers
        $total_score = 0;
        foreach ($answers as $question_id => $answer_value) {
            $score = 0; // Calculate score based on question type
            $db->save_answer($response_id, $question_id, $answer_value, $score);
            $total_score += $score;
        }
        
        // Log analytics
        $db->log_analytics($campaign_id, 'quiz_completed', array(
            'response_id' => $response_id,
            'total_score' => $total_score
        ));
        
        wp_send_json_success(array(
            'message' => 'Quiz submitted successfully!',
            'score' => $total_score,
            'response_id' => $response_id
        ));
    }
    
    public function get_provinces() {
        $aqm = new AdvancedQuizManager();
        wp_send_json_success($aqm->get_vietnam_provinces_json());
    }
}

// Frontend class
class AQM_Frontend {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function enqueue_scripts() {
        // Frontend scripts are handled in the main class
    }
}
?>