<?php
/**
 * Enhanced Question Bank Management  
 * File: modules/questions/class-question-bank.php
 * 
 * FIXED VERSION - Handles admin interface and business logic for question management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Bank {
    
    private $model;
    private $wpdb;
    private $table_prefix;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Initialize model
        $this->model = new Vefify_Question_Model();
        
        // Hook into WordPress admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_vefify_load_question_preview', array($this, 'load_question_preview'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    
    /**
     * FIXED: Properly enqueue admin scripts with localization
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on question pages
        if (strpos($hook, 'vefify-questions') === false) {
            return;
        }
        
        // Enqueue the JavaScript file
        wp_enqueue_script(
            'vefify-question-bank',
            plugin_dir_url(__FILE__) . 'assets/question-bank.js',
            array('jquery'),
            '1.1.0',
            true
        );
        
        // FIXED: Add the missing localization
        wp_localize_script('vefify-question-bank', 'vefifyQuestionBank', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_question_bank'),
            'strings' => array(
                'selectOne' => __('Select ONE correct answer for single choice questions', 'vefify-quiz'),
                'selectMultiple' => __('Select ALL correct answers for multiple choice questions', 'vefify-quiz'),
                'selectTrueFalse' => __('Select either True OR False', 'vefify-quiz'),
                'trueFalseMode' => __('True/False Mode: Only 2 options allowed', 'vefify-quiz'),
                'true' => __('True', 'vefify-quiz'),
                'false' => __('False', 'vefify-quiz'),
                'loading' => __('Loading...', 'vefify-quiz'),
                'errorLoading' => __('Error loading preview', 'vefify-quiz'),
                'confirmDelete' => __('Are you sure you want to delete this question?', 'vefify-quiz'),
                'minOptions' => __('You need at least 2 options', 'vefify-quiz'),
                'maxOptions' => __('Maximum 6 options allowed', 'vefify-quiz'),
                'noCorrectAnswer' => __('Please mark at least one correct answer', 'vefify-quiz'),
                'questionRequired' => __('Question text is required', 'vefify-quiz')
            )
        ));
        
        // Enqueue admin styles
        wp_enqueue_style(
            'vefify-question-bank',
            plugin_dir_url(__FILE__) . 'assets/question-bank.css',
            array(),
            '1.1.0'
        );
    }
    
    /**
     * FIXED: Handle form submissions properly
     */
    public function handle_form_submissions() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'save_question') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_question_save')) {
            wp_die(__('Security check failed'));
        }
        
        $this->save_question();
    }
    
    /**
     * FIXED: Save question with proper validation
     */
    private function save_question() {
        // Get question data
        $question_data = array(
            'campaign_id' => !empty($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null,
            'question_text' => sanitize_textarea_field($_POST['question_text']),
            'question_type' => sanitize_text_field($_POST['question_type']),
            'category' => sanitize_text_field($_POST['category']),
            'difficulty' => sanitize_text_field($_POST['difficulty']),
            'points' => intval($_POST['points']) ?: 1,
            'explanation' => sanitize_textarea_field($_POST['explanation'])
        );
        
        // FIXED: Properly validate and process options
        $options_data = $this->process_options($_POST['options'] ?? array());
        
        if (is_wp_error($options_data)) {
            $this->add_admin_notice($options_data->get_error_message(), 'error');
            return;
        }
        
        // Combine data
        $question_data['options'] = $options_data;
        
        try {
            if (!empty($_POST['question_id'])) {
                // Update existing question
                $question_id = intval($_POST['question_id']);
                $result = $this->model->update_question($question_id, $question_data);
                $message = __('Question updated successfully!', 'vefify-quiz');
            } else {
                // Create new question
                $result = $this->model->create_question($question_data);
                $question_id = $result;
                $message = __('Question created successfully!', 'vefify-quiz');
            }
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            $this->add_admin_notice($message, 'success');
            
            // Redirect to avoid resubmission
            wp_redirect(admin_url('admin.php?page=vefify-questions&action=edit&id=' . $question_id));
            exit;
            
        } catch (Exception $e) {
            $this->add_admin_notice('Error saving question: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * FIXED: Process and validate options data
     */
    private function process_options($raw_options) {
        if (empty($raw_options) || !is_array($raw_options)) {
            return new WP_Error('no_options', __('No options provided', 'vefify-quiz'));
        }
        
        $processed_options = array();
        $has_correct = false;
        
        foreach ($raw_options as $index => $option) {
            // Skip empty options
            if (empty($option['text'])) {
                continue;
            }
            
            $processed_option = array(
                'option_text' => sanitize_textarea_field($option['text']),
                'is_correct' => !empty($option['is_correct']),
                'explanation' => sanitize_textarea_field($option['explanation'] ?? ''),
                'order_index' => count($processed_options) + 1
            );
            
            if ($processed_option['is_correct']) {
                $has_correct = true;
            }
            
            $processed_options[] = $processed_option;
        }
        
        // Validation
        if (count($processed_options) < 2) {
            return new WP_Error('insufficient_options', __('At least 2 options are required', 'vefify-quiz'));
        }
        
        if (!$has_correct) {
            return new WP_Error('no_correct_answer', __('At least one option must be marked as correct', 'vefify-quiz'));
        }
        
        return $processed_options;
    }
    
    /**
     * FIXED: AJAX handler for question preview
     */
    public function load_question_preview() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_question_bank')) {
            wp_send_json_error(__('Security check failed', 'vefify-quiz'));
        }
        
        $question_id = intval($_POST['question_id']);
        
        if (!$question_id) {
            wp_send_json_error(__('Invalid question ID', 'vefify-quiz'));
        }
        
        try {
            $question = $this->model->get_question($question_id);
            
            if (!$question) {
                wp_send_json_error(__('Question not found', 'vefify-quiz'));
            }
            
            $html = $this->render_question_preview($question);
            wp_send_json_success($html);
            
        } catch (Exception $e) {
            wp_send_json_error(__('Error loading preview: ', 'vefify-quiz') . $e->getMessage());
        }
    }
    
    /**
     * FIXED: Render question preview HTML
     */
    private function render_question_preview($question) {
        ob_start();
        ?>
        <div class="question-preview-wrapper">
            <div class="preview-question">
                <strong><?php echo esc_html($question->question_text); ?></strong>
            </div>
            
            <div class="preview-meta">
                <span class="type-badge"><?php echo esc_html(ucfirst(str_replace('_', ' ', $question->question_type))); ?></span>
                <span class="category-badge"><?php echo esc_html(ucfirst($question->category)); ?></span>
                <span class="difficulty-badge difficulty-<?php echo esc_attr($question->difficulty); ?>">
                    <?php echo esc_html(ucfirst($question->difficulty)); ?>
                </span>
                <span class="points-badge"><?php echo $question->points; ?> pts</span>
            </div>
            
            <div class="preview-options">
                <?php foreach ($question->options as $index => $option): ?>
                    <div class="preview-option <?php echo $option->is_correct ? 'correct-option' : 'incorrect-option'; ?>">
                        <span class="option-marker"><?php echo chr(65 + $index); ?>.</span>
                        <span class="option-text"><?php echo esc_html($option->option_text); ?></span>
                        <?php if ($option->is_correct): ?>
                            <span class="correct-indicator">✓</span>
                        <?php endif; ?>
                        
                        <?php if ($option->explanation): ?>
                            <div class="option-explanation">
                                <em><?php echo esc_html($option->explanation); ?></em>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($question->explanation): ?>
                <div class="preview-explanation">
                    <strong><?php _e('Explanation:', 'vefify-quiz'); ?></strong>
                    <p><?php echo esc_html($question->explanation); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .question-preview-wrapper {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .preview-question {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 15px;
            color: #333;
        }
        
        .preview-meta {
            margin-bottom: 15px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .preview-meta span {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .type-badge { background: #e3f2fd; color: #1976d2; }
        .category-badge { background: #f3e5f5; color: #7b1fa2; }
        .difficulty-badge.difficulty-easy { background: #e8f5e8; color: #2e7d32; }
        .difficulty-badge.difficulty-medium { background: #fff3e0; color: #f57c00; }
        .difficulty-badge.difficulty-hard { background: #ffebee; color: #d32f2f; }
        .points-badge { background: #fafafa; color: #666; }
        
        .preview-options {
            margin: 15px 0;
        }
        
        .preview-option {
            display: flex;
            align-items: flex-start;
            margin: 8px 0;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }
        
        .preview-option.correct-option {
            background: #e8f5e8;
            border-color: #4caf50;
        }
        
        .preview-option.incorrect-option {
            background: #fafafa;
        }
        
        .option-marker {
            font-weight: bold;
            margin-right: 8px;
            color: #666;
            min-width: 20px;
        }
        
        .option-text {
            flex: 1;
            color: #333;
        }
        
        .correct-indicator {
            color: #4caf50;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .option-explanation {
            margin-top: 5px;
            padding-left: 28px;
            color: #666;
            font-size: 13px;
        }
        
        .preview-explanation {
            margin-top: 15px;
            padding: 12px;
            background: #f5f5f5;
            border-left: 3px solid #2196f3;
            border-radius: 4px;
        }
        
        .preview-explanation strong {
            color: #1976d2;
        }
        
        .preview-explanation p {
            margin: 5px 0 0 0;
            color: #555;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Helper function to add admin notices
     */
    private function add_admin_notice($message, $type = 'info') {
        set_transient('vefify_admin_notice', array(
            'message' => $message,
            'type' => $type
        ), 30);
    }
    
    /**
     * Get questions with pagination and filtering
     */
    public function get_questions($args = array()) {
        return $this->model->get_questions($args);
    }
    
    /**
     * Get question by ID
     */
    public function get_question($question_id) {
        return $this->model->get_question($question_id);
    }
    
    /**
     * Delete question
     */
    public function delete_question($question_id) {
        return $this->model->delete_question($question_id);
    }
    
    /**
     * Get available categories
     */
    public function get_categories() {
        return $this->wpdb->get_col("
            SELECT DISTINCT category 
            FROM {$this->table_prefix}questions 
            WHERE category IS NOT NULL AND category != '' AND is_active = 1
            ORDER BY category ASC
        ");
    }
    
    /**
     * Get question statistics
     */
    public function get_statistics($campaign_id = null) {
        $where = $campaign_id ? $this->wpdb->prepare('WHERE campaign_id = %d', $campaign_id) : '';
        
        return $this->wpdb->get_row("
            SELECT 
                COUNT(*) as total_questions,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_questions,
                COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy_questions,
                COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium_questions,
                COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard_questions,
                COUNT(CASE WHEN question_type = 'multiple_choice' THEN 1 END) as single_choice,
                COUNT(CASE WHEN question_type = 'multiple_select' THEN 1 END) as multi_choice,
                COUNT(CASE WHEN question_type = 'true_false' THEN 1 END) as true_false
            FROM {$this->table_prefix}questions 
            {$where}
        ", ARRAY_A);
    }
}