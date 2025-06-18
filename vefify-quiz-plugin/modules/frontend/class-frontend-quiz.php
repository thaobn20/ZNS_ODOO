<?php
/**
 * Frontend Quiz Module
 * File: modules/frontend/class-frontend-quiz.php
 * 
 * Handles the complete frontend quiz experience:
 * - Registration form with Vietnamese provinces
 * - Phone validation and uniqueness check
 * - Quiz display and submission
 * - Gift display and tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Frontend_Quiz {
    
    private static $instance = null;
    private $participant_model;
    private $utilities;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->participant_model = new Vefify_Participant_Model();
        $this->utilities = new Vefify_Quiz_Utilities();
        $this->init();
    }
    
    private function init() {
        // Register shortcode
        add_shortcode('vefify_quiz', array($this, 'render_quiz_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_vefify_check_phone', array($this, 'ajax_check_phone'));
        add_action('wp_ajax_nopriv_vefify_check_phone', array($this, 'ajax_check_phone'));
        
        add_action('wp_ajax_vefify_submit_registration', array($this, 'ajax_submit_registration'));
        add_action('wp_ajax_nopriv_vefify_submit_registration', array($this, 'ajax_submit_registration'));
        
        add_action('wp_ajax_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
        add_action('wp_ajax_nopriv_vefify_submit_quiz', array($this, 'ajax_submit_quiz'));
        
        add_action('wp_ajax_vefify_get_districts', array($this, 'ajax_get_districts'));
        add_action('wp_ajax_nopriv_vefify_get_districts', array($this, 'ajax_get_districts'));
        
        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    /**
     * Enqueue frontend CSS and JS
     */
    public function enqueue_frontend_assets() {
        if ($this->is_quiz_page()) {
            wp_enqueue_script(
                'vefify-frontend-quiz',
                VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/frontend-quiz.js',
                array('jquery'),
                VEFIFY_QUIZ_VERSION,
                true
            );
            
            wp_enqueue_style(
                'vefify-frontend-quiz',
                VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/frontend-quiz.css',
                array(),
                VEFIFY_QUIZ_VERSION
            );
            
            // Localize script
            wp_localize_script('vefify-frontend-quiz', 'vefifyQuizAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vefify_quiz_nonce'),
                'provinces' => $this->get_vietnam_provinces_with_districts(),
                'messages' => array(
                    'phone_exists' => 'Số điện thoại này đã tham gia chiến dịch.',
                    'phone_invalid' => 'Số điện thoại không đúng định dạng Việt Nam.',
                    'required_field' => 'Vui lòng điền đầy đủ thông tin.',
                    'quiz_submitted' => 'Bài quiz đã được nộp thành công!',
                    'loading' => 'Đang xử lý...'
                )
            ));
        }
    }
    
    /**
     * Check if current page contains quiz shortcode
     */
    private function is_quiz_page() {
        global $post;
        return $post && has_shortcode($post->post_content, 'vefify_quiz');
    }
    
    /**
     * Render quiz shortcode
     */
    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'theme' => 'default',
            'show_progress' => 'true',
            'auto_submit' => 'false'
        ), $atts);
        
        if (empty($atts['campaign_id'])) {
            return '<div class="vefify-error">Campaign ID is required.</div>';
        }
        
        $campaign_id = intval($atts['campaign_id']);
        $campaign = $this->get_campaign($campaign_id);
        
        if (!$campaign || !$campaign['is_active']) {
            return '<div class="vefify-error">Campaign not found or inactive.</div>';
        }
        
        // Check if campaign is within date range
        $now = current_time('mysql');
        if ($now < $campaign['start_date'] || $now > $campaign['end_date']) {
            return '<div class="vefify-error">Campaign is not currently active.</div>';
        }
        
        ob_start();
        $this->render_quiz_interface($campaign, $atts);
        return ob_get_clean();
    }
    
    /**
     * Render the main quiz interface
     */
    private function render_quiz_interface($campaign, $options) {
        $session_id = $this->get_or_create_session($campaign['id']);
        $participant = $this->get_participant_by_session($session_id);
        
        ?>
        <div id="vefify-quiz-container" class="vefify-quiz-theme-<?php echo esc_attr($options['theme']); ?>" 
             data-campaign-id="<?php echo esc_attr($campaign['id']); ?>"
             data-session-id="<?php echo esc_attr($session_id); ?>">
            
            <?php if (!$participant): ?>
                <!-- Registration Form -->
                <?php $this->render_registration_form($campaign); ?>
                
            <?php elseif ($participant['quiz_status'] === 'started' || $participant['quiz_status'] === 'in_progress'): ?>
                <!-- Quiz Questions -->
                <?php $this->render_quiz_questions($campaign, $participant, $options); ?>
                
            <?php elseif ($participant['quiz_status'] === 'completed'): ?>
                <!-- Results and Gift -->
                <?php $this->render_quiz_results($campaign, $participant); ?>
                
            <?php else: ?>
                <div class="vefify-error">Invalid quiz state.</div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render registration form
     */
    private function render_registration_form($campaign) {
        $form_settings = $this->get_form_settings($campaign['id']);
        ?>
        <div id="vefify-registration-form" class="vefify-form-container">
            <div class="vefify-form-header">
                <h2><?php echo esc_html($campaign['name']); ?></h2>
                <?php if ($campaign['description']): ?>
                    <p class="campaign-description"><?php echo esc_html($campaign['description']); ?></p>
                <?php endif; ?>
            </div>
            
            <form id="vefify-registration" class="vefify-form">
                <?php wp_nonce_field('vefify_quiz_nonce', 'vefify_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign['id']); ?>">
                
                <!-- Name Field -->
                <div class="vefify-field-group">
                    <label for="participant_name" class="vefify-label">
                        Họ và tên <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="participant_name" 
                           name="participant_name" 
                           class="vefify-input" 
                           required 
                           placeholder="Nhập họ và tên của bạn">
                    <div class="vefify-field-error"></div>
                </div>
                
                <!-- Phone Number Field -->
                <div class="vefify-field-group">
                    <label for="participant_phone" class="vefify-label">
                        Số điện thoại <span class="required">*</span>
                    </label>
                    <input type="tel" 
                           id="participant_phone" 
                           name="participant_phone" 
                           class="vefify-input" 
                           required 
                           placeholder="0xxxxxxxxx"
                           pattern="^0[3|5|7|8|9][0-9]{8}$">
                    <div class="vefify-field-error"></div>
                    <div class="vefify-field-help">Số điện thoại Việt Nam (10 số)</div>
                </div>
                
                <!-- Province Selection -->
                <div class="vefify-field-group">
                    <label for="province_city" class="vefify-label">
                        Tỉnh/Thành phố <span class="required">*</span>
                    </label>
                    <select id="province_city" name="province_city" class="vefify-select" required>
                        <option value="">Chọn tỉnh/thành phố</option>
                        <?php foreach ($this->get_vietnam_provinces_with_districts() as $province_code => $province_data): ?>
                            <option value="<?php echo esc_attr($province_code); ?>">
                                <?php echo esc_html($province_data['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="vefify-field-error"></div>
                </div>
                
                <!-- District Selection -->
                <div class="vefify-field-group">
                    <label for="province_district" class="vefify-label">
                        Quận/Huyện <span class="required">*</span>
                    </label>
                    <select id="province_district" name="province_district" class="vefify-select" required disabled>
                        <option value="">Chọn quận/huyện</option>
                    </select>
                    <div class="vefify-field-error"></div>
                </div>
                
                <!-- Pharmacy Code -->
                <?php if ($form_settings['show_pharmacy_code']): ?>
                <div class="vefify-field-group">
                    <label for="pharmacy_code" class="vefify-label">
                        Mã nhà thuốc <?php echo $form_settings['pharmacy_code_required'] ? '<span class="required">*</span>' : ''; ?>
                    </label>
                    <input type="text" 
                           id="pharmacy_code" 
                           name="pharmacy_code" 
                           class="vefify-input" 
                           <?php echo $form_settings['pharmacy_code_required'] ? 'required' : ''; ?>
                           placeholder="Nhập mã nhà thuốc">
                    <div class="vefify-field-error"></div>
                </div>
                <?php endif; ?>
                
                <!-- Email Field -->
                <?php if ($form_settings['show_email']): ?>
                <div class="vefify-field-group">
                    <label for="participant_email" class="vefify-label">
                        Email <?php echo $form_settings['email_required'] ? '<span class="required">*</span>' : ''; ?>
                    </label>
                    <input type="email" 
                           id="participant_email" 
                           name="participant_email" 
                           class="vefify-input" 
                           <?php echo $form_settings['email_required'] ? 'required' : ''; ?>
                           placeholder="example@email.com">
                    <div class="vefify-field-error"></div>
                </div>
                <?php endif; ?>
                
                <!-- Terms and Conditions -->
                <?php if ($form_settings['show_terms']): ?>
                <div class="vefify-field-group">
                    <label class="vefify-checkbox">
                        <input type="checkbox" name="agree_terms" required>
                        <span class="checkmark"></span>
                        Tôi đồng ý với <a href="<?php echo esc_url($form_settings['terms_url']); ?>" target="_blank">điều khoản và điều kiện</a> <span class="required">*</span>
                    </label>
                    <div class="vefify-field-error"></div>
                </div>
                <?php endif; ?>
                
                <div class="vefify-form-actions">
                    <button type="submit" class="vefify-btn vefify-btn-primary" id="register-btn">
                        Bắt đầu quiz
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render quiz questions
     */
    private function render_quiz_questions($campaign, $participant, $options) {
        $questions = $this->get_campaign_questions($campaign['id']);
        
        if (empty($questions)) {
            echo '<div class="vefify-error">No questions found for this campaign.</div>';
            return;
        }
        
        ?>
        <div id="vefify-quiz-questions" class="vefify-quiz-container">
            <div class="vefify-quiz-header">
                <h2><?php echo esc_html($campaign['name']); ?></h2>
                <div class="participant-info">
                    <span>Xin chào, <?php echo esc_html($participant['participant_name']); ?>!</span>
                </div>
                
                <?php if ($options['show_progress'] === 'true'): ?>
                <div class="vefify-progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                    <span class="progress-text">Câu hỏi 1 / <?php echo count($questions); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($campaign['time_limit'] > 0): ?>
                <div class="vefify-timer">
                    <span class="timer-icon">⏱️</span>
                    <span id="time-remaining"><?php echo $this->format_time($campaign['time_limit']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <form id="vefify-quiz-form" class="vefify-quiz-form">
                <?php wp_nonce_field('vefify_quiz_nonce', 'vefify_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign['id']); ?>">
                <input type="hidden" name="participant_id" value="<?php echo esc_attr($participant['id']); ?>">
                
                <?php foreach ($questions as $index => $question): ?>
                <div class="vefify-question" 
                     data-question-id="<?php echo esc_attr($question->id); ?>"
                     style="<?php echo $index === 0 ? 'display: block;' : 'display: none;'; ?>">
                    
                    <div class="question-header">
                        <h3 class="question-title">
                            Câu <?php echo $index + 1; ?>: <?php echo esc_html($question->question_text); ?>
                        </h3>
                        
                        <?php if ($question->difficulty): ?>
                        <span class="question-difficulty difficulty-<?php echo esc_attr($question->difficulty); ?>">
                            <?php echo ucfirst($question->difficulty); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="question-options">
                        <?php foreach ($question->options as $option): ?>
                        <label class="vefify-option">
                            <input type="<?php echo $question->question_type === 'multiple_select' ? 'checkbox' : 'radio'; ?>" 
                                   name="answers[<?php echo esc_attr($question->id); ?>]<?php echo $question->question_type === 'multiple_select' ? '[]' : ''; ?>" 
                                   value="<?php echo esc_attr($option->id); ?>"
                                   class="option-input">
                            <span class="option-text"><?php echo esc_html($option->option_text); ?></span>
                            <span class="option-checkmark"></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="question-actions">
                        <?php if ($index > 0): ?>
                        <button type="button" class="vefify-btn vefify-btn-secondary prev-question">
                            Câu trước
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($index < count($questions) - 1): ?>
                        <button type="button" class="vefify-btn vefify-btn-primary next-question">
                            Câu tiếp
                        </button>
                        <?php else: ?>
                        <button type="submit" class="vefify-btn vefify-btn-success submit-quiz">
                            Nộp bài
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
        </div>
        
        <!-- Quiz Timer Script -->
        <?php if ($campaign['time_limit'] > 0): ?>
        <script>
        jQuery(document).ready(function($) {
            var timeLimit = <?php echo $campaign['time_limit']; ?>; // seconds
            var startTime = Date.now();
            
            function updateTimer() {
                var elapsed = Math.floor((Date.now() - startTime) / 1000);
                var remaining = Math.max(0, timeLimit - elapsed);
                
                if (remaining === 0) {
                    $('#vefify-quiz-form').submit();
                    return;
                }
                
                var minutes = Math.floor(remaining / 60);
                var seconds = remaining % 60;
                $('#time-remaining').text(minutes + ':' + (seconds < 10 ? '0' : '') + seconds);
            }
            
            setInterval(updateTimer, 1000);
            updateTimer();
        });
        </script>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render quiz results and gift
     */
    private function render_quiz_results($campaign, $participant) {
        $gift = $this->get_participant_gift($participant);
        ?>
        <div id="vefify-quiz-results" class="vefify-results-container">
            <div class="results-header">
                <h2>🎉 Chúc mừng!</h2>
                <p>Bạn đã hoàn thành quiz <strong><?php echo esc_html($campaign['name']); ?></strong></p>
            </div>
            
            <div class="results-summary">
                <div class="score-card">
                    <div class="score-circle">
                        <span class="score-number"><?php echo $participant['final_score']; ?></span>
                        <span class="score-total">/ <?php echo $participant['total_questions']; ?></span>
                    </div>
                    <p class="score-label">Điểm số của bạn</p>
                </div>
                
                <div class="results-details">
                    <div class="result-item">
                        <span class="label">Số câu đúng:</span>
                        <span class="value"><?php echo $participant['final_score']; ?> / <?php echo $participant['total_questions']; ?></span>
                    </div>
                    
                    <div class="result-item">
                        <span class="label">Tỷ lệ đúng:</span>
                        <span class="value">
                            <?php echo $participant['total_questions'] > 0 ? round(($participant['final_score'] / $participant['total_questions']) * 100, 1) : 0; ?>%
                        </span>
                    </div>
                    
                    <?php if ($participant['completion_time']): ?>
                    <div class="result-item">
                        <span class="label">Thời gian:</span>
                        <span class="value"><?php echo $this->utilities::seconds_to_time($participant['completion_time']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($gift): ?>
            <div class="gift-section">
                <div class="gift-header">
                    <h3>🎁 Phần thưởng của bạn</h3>
                </div>
                
                <div class="gift-card">
                    <div class="gift-info">
                        <h4><?php echo esc_html($gift['gift_name']); ?></h4>
                        <p class="gift-description"><?php echo esc_html($gift['gift_description']); ?></p>
                        <p class="gift-value"><strong>Giá trị: <?php echo esc_html($gift['gift_value']); ?></strong></p>
                    </div>
                    
                    <div class="gift-code-section">
                        <label>Mã quà tặng của bạn:</label>
                        <div class="gift-code-display">
                            <span class="gift-code" id="gift-code"><?php echo esc_html($participant['gift_code']); ?></span>
                            <button type="button" class="copy-code-btn" onclick="copyGiftCode()">
                                📋 Sao chép
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($gift['api_endpoint']): ?>
                    <div class="gift-actions">
                        <button type="button" class="vefify-btn vefify-btn-primary claim-gift-btn" 
                                data-gift-id="<?php echo esc_attr($gift['id']); ?>"
                                data-participant-id="<?php echo esc_attr($participant['id']); ?>">
                            Nhận quà ngay
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="no-gift-section">
                <h3>Cảm ơn bạn đã tham gia!</h3>
                <p>Chúc bạn may mắn lần sau.</p>
            </div>
            <?php endif; ?>
            
            <div class="results-actions">
                <button type="button" class="vefify-btn vefify-btn-secondary" onclick="window.print()">
                    🖨️ In kết quả
                </button>
                
                <button type="button" class="vefify-btn vefify-btn-primary share-results-btn">
                    📱 Chia sẻ
                </button>
            </div>
        </div>
        
        <script>
        function copyGiftCode() {
            var giftCode = document.getElementById('gift-code').textContent;
            navigator.clipboard.writeText(giftCode).then(function() {
                alert('Đã sao chép mã quà tặng: ' + giftCode);
            });
        }
        </script>
        <?php
    }
    
    /**
     * AJAX: Check phone number uniqueness
     */
    public function ajax_check_phone() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone']);
        $campaign_id = intval($_POST['campaign_id']);
        
        // Validate phone format
        if (!$this->utilities::validate_phone_number($phone)) {
            wp_send_json_error(array('message' => 'Invalid phone format'));
        }
        
        // Format phone number
        $formatted_phone = $this->utilities::format_phone_number($phone);
        
        // Check if phone exists for this campaign
        $exists = $this->participant_model->phone_exists_in_campaign($formatted_phone, $campaign_id);
        
        wp_send_json_success(array(
            'exists' => $exists,
            'formatted_phone' => $formatted_phone
        ));
    }
    
    /**
     * AJAX: Submit registration
     */
    public function ajax_submit_registration() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $campaign_id = intval($_POST['campaign_id']);
        $participant_data = array(
            'participant_name' => sanitize_text_field($_POST['participant_name']),
            'participant_phone' => $this->utilities::format_phone_number($_POST['participant_phone']),
            'province' => sanitize_text_field($_POST['province_city'] . ' - ' . $_POST['province_district']),
            'pharmacy_code' => sanitize_text_field($_POST['pharmacy_code'] ?? ''),
            'participant_email' => sanitize_email($_POST['participant_email'] ?? ''),
            'session_id' => sanitize_text_field($_POST['session_id']),
            'ip_address' => $this->utilities::get_client_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '')
        );
        
        // Validate required fields
        if (empty($participant_data['participant_name']) || empty($participant_data['participant_phone'])) {
            wp_send_json_error(array('message' => 'Required fields missing'));
        }
        
        // Check phone uniqueness
        if ($this->participant_model->phone_exists_in_campaign($participant_data['participant_phone'], $campaign_id)) {
            wp_send_json_error(array('message' => 'Phone number already registered for this campaign'));
        }
        
        // Create participant
        $participant_id = $this->participant_model->start_quiz_session($campaign_id, $participant_data);
        
        if (is_wp_error($participant_id)) {
            wp_send_json_error(array('message' => $participant_id->get_error_message()));
        }
        
        wp_send_json_success(array(
            'participant_id' => $participant_id,
            'redirect' => 'quiz'
        ));
    }
    
    /**
     * AJAX: Submit quiz answers
     */
    public function ajax_submit_quiz() {
        check_ajax_referer('vefify_quiz_nonce', 'nonce');
        
        $participant_id = intval($_POST['participant_id']);
        $answers = $_POST['answers'] ?? array();
        
        // Submit answers
        $result = $this->participant_model->submit_quiz_answers($participant_id, $answers);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Check for gift eligibility
        $participant = $this->participant_model->get_participant($participant_id);
        $gift = $this->check_gift_eligibility($participant);
        
        if ($gift) {
            $this->assign_gift_to_participant($participant_id, $gift);
        }
        
        wp_send_json_success(array(
            'result' => $result,
            'gift' => $gift,
            'redirect' => 'results'
        ));
    }
    
    /**
     * AJAX: Get districts for selected province
     */
    public function ajax_get_districts() {
        $province_code = sanitize_text_field($_POST['province_code']);
        $provinces = $this->get_vietnam_provinces_with_districts();
        
        if (isset($provinces[$province_code])) {
            wp_send_json_success($provinces[$province_code]['districts']);
        } else {
            wp_send_json_error('Province not found');
        }
    }
    
    /**
     * Get Vietnam provinces with districts
     */
    private function get_vietnam_provinces_with_districts() {
        return array(
            'hcm' => array(
                'name' => 'TP. Hồ Chí Minh',
                'districts' => array(
                    'quan1' => 'Quận 1',
                    'quan2' => 'Quận 2',
                    'quan3' => 'Quận 3',
                    'quan4' => 'Quận 4',
                    'quan5' => 'Quận 5',
                    'quan6' => 'Quận 6',
                    'quan7' => 'Quận 7',
                    'quan8' => 'Quận 8',
                    'quan9' => 'Quận 9',
                    'quan10' => 'Quận 10',
                    'quan11' => 'Quận 11',
                    'quan12' => 'Quận 12',
                    'binhtan' => 'Quận Bình Tân',
                    'binhthanh' => 'Quận Bình Thạnh',
                    'govap' => 'Quận Gò Vấp',
                    'phunhuan' => 'Quận Phú Nhuận',
                    'tanbinh' => 'Quận Tân Bình',
                    'tanphu' => 'Quận Tân Phú',
                    'thuduc' => 'Quận Thủ Đức',
                    'binhanh' => 'Huyện Bình Chánh',
                    'cuchi' => 'Huyện Củ Chi',
                    'hocmon' => 'Huyện Hóc Môn',
                    'nhabe' => 'Huyện Nhà Bè',
                    'canggio' => 'Huyện Cần Giờ'
                )
            ),
            'hanoi' => array(
                'name' => 'Hà Nội',
                'districts' => array(
                    'badinh' => 'Quận Ba Đình',
                    'hoankkiem' => 'Quận Hoàn Kiếm',
                    'tay_ho' => 'Quận Tây Hồ',
                    'long_bien' => 'Quận Long Biên',
                    'cau_giay' => 'Quận Cầu Giấy',
                    'dong_da' => 'Quận Đống Đa',
                    'hai_ba_trung' => 'Quận Hai Bà Trưng',
                    'hoang_mai' => 'Quận Hoàng Mai',
                    'thanh_xuan' => 'Quận Thanh Xuân',
                    'nam_tu_liem' => 'Quận Nam Từ Liêm',
                    'bac_tu_liem' => 'Quận Bắc Từ Liêm',
                    'ha_dong' => 'Quận Hà Đông'
                )
            ),
            'danang' => array(
                'name' => 'Đà Nẵng',
                'districts' => array(
                    'lien_chieu' => 'Quận Liên Chiểu',
                    'thanh_khe' => 'Quận Thanh Khê',
                    'hai_chau' => 'Quận Hải Châu',
                    'son_tra' => 'Quận Sơn Trà',
                    'ngu_hanh_son' => 'Quận Ngũ Hành Sơn',
                    'cam_le' => 'Quận Cẩm Lệ',
                    'hoa_vang' => 'Huyện Hòa Vang',
                    'hoang_sa' => 'Huyện Hoàng Sa'
                )
            )
            // Add more provinces as needed
        );
    }
    
    /**
     * Helper methods
     */
    private function get_campaign($campaign_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vefify_campaigns WHERE id = %d",
            $campaign_id
        ), ARRAY_A);
    }
    
    private function get_campaign_questions($campaign_id) {
        global $wpdb;
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vefify_questions WHERE campaign_id = %d AND is_active = 1 ORDER BY order_index",
            $campaign_id
        ));
        
        foreach ($questions as $question) {
            $question->options = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}vefify_question_options WHERE question_id = %d ORDER BY order_index",
                $question->id
            ));
        }
        
        return $questions;
    }
    
    private function get_or_create_session($campaign_id) {
        if (isset($_COOKIE['vefify_session_' . $campaign_id])) {
            return sanitize_text_field($_COOKIE['vefify_session_' . $campaign_id]);
        }
        
        $session_id = $this->utilities::generate_session_id();
        setcookie('vefify_session_' . $campaign_id, $session_id, time() + (7 * 24 * 60 * 60), '/');
        return $session_id;
    }
    
    private function get_participant_by_session($session_id) {
        return $this->participant_model->get_participant_by_session($session_id);
    }
    
    private function get_form_settings($campaign_id) {
        // Get centralized form settings
        $settings = get_option('vefify_form_settings', array());
        
        return wp_parse_args($settings, array(
            'show_pharmacy_code' => true,
            'pharmacy_code_required' => false,
            'show_email' => true,
            'email_required' => false,
            'show_terms' => true,
            'terms_url' => '#'
        ));
    }
    
    private function check_gift_eligibility($participant) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vefify_gifts 
             WHERE campaign_id = %d 
             AND is_active = 1 
             AND min_score <= %d 
             AND (max_score IS NULL OR max_score >= %d)
             ORDER BY min_score DESC 
             LIMIT 1",
            $participant['campaign_id'], $participant['final_score'], $participant['final_score']
        ), ARRAY_A);
    }
    
    private function assign_gift_to_participant($participant_id, $gift) {
        $gift_code = $this->utilities::generate_gift_code($gift['gift_code_prefix'] ?? 'GIFT');
        
        return $this->participant_model->add_gift_to_participant($participant_id, $gift['id'], $gift_code);
    }
    
    private function get_participant_gift($participant) {
        if (!$participant['gift_id']) {
            return null;
        }
        
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}vefify_gifts WHERE id = %d",
            $participant['gift_id']
        ), ARRAY_A);
    }
    
    private function format_time($seconds) {
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $seconds);
    }
}