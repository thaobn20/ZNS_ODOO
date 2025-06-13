<?php
/**
 * Enhanced Question Bank Management - CLEAN PHP VERSION
 * File: modules/questions/class-question-bank.php
 * 
 * Handles admin interface and business logic for question management
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
        
        // Initialize question model
        $this->model = new Vefify_Question_Model();
        
        // Hook into WordPress
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_action('wp_ajax_vefify_load_question_preview', array($this, 'ajax_load_question_preview'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'vefify-quiz',
            'Question Bank',
            'Questions',
            'manage_options',
            'vefify-questions',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'vefify-questions') === false) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'vefify-question-bank-css',
            plugin_dir_url(__FILE__) . 'assets/question-bank.css',
            array(),
            VEFIFY_QUIZ_VERSION
        );
        
        // Enqueue JS
        wp_enqueue_script(
            'vefify-question-bank-js',
            plugin_dir_url(__FILE__) . 'assets/question-bank.js',
            array('jquery', 'wp-editor'),
            VEFIFY_QUIZ_VERSION,
            true
        );
        
        // Localize script with data
        wp_localize_script('vefify-question-bank-js', 'vefifyQuestionBank', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_question_bank'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this question?', 'vefify-quiz'),
                'errorLoading' => __('Error loading preview', 'vefify-quiz'),
                'loading' => __('Loading...', 'vefify-quiz'),
                'trueFalseMode' => __('TRUE/FALSE MODE - Only 2 Options Allowed', 'vefify-quiz'),
                'selectOne' => __('Select ONE correct answer for this question.', 'vefify-quiz'),
                'selectMultiple' => __('Select ALL correct answers for this question.', 'vefify-quiz'),
                'selectTrueFalse' => __('Select either True or False as the correct answer.', 'vefify-quiz'),
                'true' => __('True', 'vefify-quiz'),
                'false' => __('False', 'vefify-quiz')
            )
        ));
        
        // Enqueue WordPress editor
        wp_enqueue_editor();
    }
    
    /**
     * Main admin page router
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'vefify-quiz'));
        }
        
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'new':
                $this->render_question_form();
                break;
            case 'edit':
                $this->render_question_form($_GET['id'] ?? 0);
                break;
            case 'delete':
                $this->handle_delete_question($_GET['id'] ?? 0);
                break;
            case 'import':
                $this->render_import_page();
                break;
            default:
                $this->render_questions_list();
                break;
        }
    }
    
    /**
     * Render questions list
     */
    private function render_questions_list() {
        // Get filter parameters
        $campaign_filter = $_GET['campaign_id'] ?? '';
        $category_filter = $_GET['category'] ?? '';
        $difficulty_filter = $_GET['difficulty'] ?? '';
        
        // Build query with proper placeholders
        $where_conditions = array('q.is_active = 1');
        $params = array();
        
        if ($campaign_filter) {
            $where_conditions[] = 'q.campaign_id = %d';
            $params[] = intval($campaign_filter);
        }
        
        if ($category_filter) {
            $where_conditions[] = 'q.category = %s';
            $params[] = sanitize_text_field($category_filter);
        }
        
        if ($difficulty_filter) {
            $where_conditions[] = 'q.difficulty = %s';
            $params[] = sanitize_text_field($difficulty_filter);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get questions
        if (empty($params)) {
            $questions = $this->wpdb->get_results("
                SELECT q.*, c.name as campaign_name,
                       COUNT(qo.id) as option_count,
                       SUM(CASE WHEN qo.is_correct = 1 THEN 1 ELSE 0 END) as correct_count
                FROM {$this->table_prefix}questions q
                LEFT JOIN {$this->table_prefix}campaigns c ON q.campaign_id = c.id
                LEFT JOIN {$this->table_prefix}question_options qo ON q.id = qo.question_id
                WHERE {$where_clause}
                GROUP BY q.id
                ORDER BY q.created_at DESC
                LIMIT 50
            ");
        } else {
            $questions = $this->wpdb->get_results($this->wpdb->prepare("
                SELECT q.*, c.name as campaign_name,
                       COUNT(qo.id) as option_count,
                       SUM(CASE WHEN qo.is_correct = 1 THEN 1 ELSE 0 END) as correct_count
                FROM {$this->table_prefix}questions q
                LEFT JOIN {$this->table_prefix}campaigns c ON q.campaign_id = c.id
                LEFT JOIN {$this->table_prefix}question_options qo ON q.id = qo.question_id
                WHERE {$where_clause}
                GROUP BY q.id
                ORDER BY q.created_at DESC
                LIMIT 50
            ", $params));
        }
        
        // Get filter options
        $campaigns = $this->wpdb->get_results("SELECT id, name FROM {$this->table_prefix}campaigns WHERE is_active = 1 ORDER BY name");
        $categories = $this->wpdb->get_col("SELECT DISTINCT category FROM {$this->table_prefix}questions WHERE category IS NOT NULL ORDER BY category");
        
        // Render the list template
        include plugin_dir_path(__FILE__) . 'templates/questions-list.php';
    }
    
    /**
     * Render question form
     */
    private function render_question_form($question_id = 0) {
        $question = null;
        $options = array();
        
        if ($question_id) {
            $question = $this->model->get_question($question_id);
            if ($question) {
                $options = $question->options;
            }
        }
        
        // Get campaigns for dropdown
        $campaigns = $this->wpdb->get_results("SELECT id, name FROM {$this->table_prefix}campaigns ORDER BY name");
        
        $is_edit = !empty($question);
        
        // Render the form template
        include plugin_dir_path(__FILE__) . 'templates/question-form.php';
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!isset($_POST['vefify_question_action']) || !current_user_can('manage_options')) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['vefify_question_nonce'], 'vefify_question_save')) {
            wp_die(__('Security check failed', 'vefify-quiz'));
        }
        
        $action = sanitize_text_field($_POST['vefify_question_action']);
        
        switch ($action) {
            case 'save_question':
                $this->handle_save_question();
                break;
            case 'import_csv':
                $this->handle_csv_import();
                break;
        }
    }
    
    /**
     * Handle save question
     */
    private function handle_save_question() {
        $question_id = !empty($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $is_edit = $question_id > 0;
        
        // Validate required fields
        if (empty($_POST['question_text'])) {
            $this->redirect_with_error(__('Question text is required', 'vefify-quiz'), $question_id);
            return;
        }
        
        // Prepare question data
        $question_data = array(
            'campaign_id' => !empty($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null,
            'question_text' => wp_kses_post($_POST['question_text']),
            'question_type' => sanitize_text_field($_POST['question_type']),
            'category' => sanitize_text_field($_POST['category']),
            'difficulty' => sanitize_text_field($_POST['difficulty']),
            'points' => intval($_POST['points']),
            'explanation' => sanitize_textarea_field($_POST['explanation']),
            'is_active' => 1
        );
        
        // Validate and prepare options
        $options = $_POST['options'] ?? array();
        $valid_options = array();
        $has_correct = false;
        
        foreach ($options as $index => $option) {
            if (!empty($option['text'])) {
                $is_correct = !empty($option['is_correct']);
                $valid_options[] = array(
                    'option_text' => sanitize_textarea_field($option['text']),
                    'is_correct' => $is_correct ? 1 : 0,
                    'explanation' => sanitize_textarea_field($option['explanation'] ?? ''),
                    'order_index' => count($valid_options) + 1
                );
                
                if ($is_correct) {
                    $has_correct = true;
                }
            }
        }
        
        // Validation
        $errors = $this->validate_question_data($question_data, $valid_options);
        if (!empty($errors)) {
            $this->redirect_with_error(implode('<br>', $errors), $question_id);
            return;
        }
        
        // Save to database
        try {
            $this->wpdb->query('START TRANSACTION');
            
            if ($is_edit) {
                $this->update_existing_question($question_id, $question_data, $valid_options);
                $message = __('Question updated successfully!', 'vefify-quiz');
                $redirect_id = $question_id;
            } else {
                $redirect_id = $this->create_new_question($question_data, $valid_options);
                $message = __('Question created successfully!', 'vefify-quiz');
            }
            
            $this->wpdb->query('COMMIT');
            
            // Redirect with success message
            wp_redirect(admin_url('admin.php?page=vefify-questions&action=edit&id=' . $redirect_id . '&saved=1'));
            exit;
            
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            $this->redirect_with_error('Error saving question: ' . $e->getMessage(), $question_id);
        }
    }
    
    /**
     * Validate question data
     */
    private function validate_question_data($question_data, $valid_options) {
        $errors = array();
        $question_type = $question_data['question_type'];
        $min_options = ($question_type === 'true_false') ? 2 : 2;
        $max_options = ($question_type === 'true_false') ? 2 : 6;
        
        if (count($valid_options) < $min_options) {
            $errors[] = sprintf(__('You need at least %d answer options for this question type.', 'vefify-quiz'), $min_options);
        }
        
        if (count($valid_options) > $max_options) {
            $errors[] = sprintf(__('You can have at most %d answer options for this question type.', 'vefify-quiz'), $max_options);
        }
        
        $correct_count = array_sum(array_column($valid_options, 'is_correct'));
        if ($correct_count === 0) {
            $errors[] = __('You need to mark at least one correct answer.', 'vefify-quiz');
        }
        
        if ($question_type === 'single_select' && $correct_count > 1) {
            $errors[] = __('Single choice questions can only have one correct answer.', 'vefify-quiz');
        }
        
        if ($question_type === 'true_false' && $correct_count !== 1) {
            $errors[] = __('True/False questions must have exactly one correct answer.', 'vefify-quiz');
        }
        
        return $errors;
    }
    
    /**
     * Create new question
     */
    private function create_new_question($question_data, $valid_options) {
        $question_data['created_at'] = current_time('mysql');
        $question_data['updated_at'] = current_time('mysql');
        
        $result = $this->wpdb->insert(
            $this->table_prefix . 'questions',
            $question_data,
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            throw new Exception('Failed to create question: ' . $this->wpdb->last_error);
        }
        
        $question_id = $this->wpdb->insert_id;
        $this->save_question_options($question_id, $valid_options);
        
        return $question_id;
    }
    
    /**
     * Update existing question
     */
    private function update_existing_question($question_id, $question_data, $valid_options) {
        $question_data['updated_at'] = current_time('mysql');
        
        $result = $this->wpdb->update(
            $this->table_prefix . 'questions',
            $question_data,
            array('id' => $question_id),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            throw new Exception('Failed to update question: ' . $this->wpdb->last_error);
        }
        
        // Delete existing options
        $this->wpdb->delete($this->table_prefix . 'question_options', array('question_id' => $question_id));
        
        // Save new options
        $this->save_question_options($question_id, $valid_options);
    }
    
    /**
     * Save question options
     */
    private function save_question_options($question_id, $valid_options) {
        foreach ($valid_options as $option) {
            $option_data = array(
                'question_id' => $question_id,
                'option_text' => $option['option_text'],
                'is_correct' => $option['is_correct'],
                'explanation' => $option['explanation'],
                'order_index' => $option['order_index'],
                'created_at' => current_time('mysql')
            );
            
            $result = $this->wpdb->insert(
                $this->table_prefix . 'question_options',
                $option_data,
                array('%d', '%s', '%d', '%s', '%d', '%s')
            );
            
            if ($result === false) {
                throw new Exception('Failed to save option: ' . $this->wpdb->last_error);
            }
        }
    }
    
    /**
     * Handle delete question
     */
    private function handle_delete_question($question_id) {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'vefify-quiz'));
        }
        
        $question_id = intval($question_id);
        if ($question_id <= 0) {
            wp_redirect(admin_url('admin.php?page=vefify-questions'));
            exit;
        }
        
        $result = $this->model->delete_question($question_id);
        
        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=vefify-questions&error=' . urlencode($result->get_error_message())));
        } else {
            wp_redirect(admin_url('admin.php?page=vefify-questions&deleted=1'));
        }
        exit;
    }
    
    /**
     * Redirect with error message
     */
    private function redirect_with_error($message, $question_id = 0) {
        $url = admin_url('admin.php?page=vefify-questions');
        if ($question_id) {
            $url .= '&action=edit&id=' . $question_id;
        } else {
            $url .= '&action=new';
        }
        $url .= '&error=' . urlencode($message);
        
        wp_redirect($url);
        exit;
    }
    
    /**
     * Render import page
     */
    private function render_import_page() {
        include plugin_dir_path(__FILE__) . 'templates/import-page.php';
    }
    
    /**
     * Handle CSV import
     */
    private function handle_csv_import() {
        // Implementation for CSV import
        wp_redirect(admin_url('admin.php?page=vefify-questions&import_success=1'));
        exit;
    }
    
    /**
     * AJAX: Load question preview
     */
    public function ajax_load_question_preview() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_question_bank')) {
            wp_send_json_error(__('Security check failed', 'vefify-quiz'));
        }
        
        $question_id = intval($_POST['question_id']);
        $question = $this->model->get_question($question_id);
        
        if (!$question) {
            wp_send_json_error(__('Question not found', 'vefify-quiz'));
        }
        
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/question-preview.php';
        $html = ob_get_clean();
        
        wp_send_json_success($html);
    }
    
    /**
     * Get model instance
     */
    public function get_model() {
        return $this->model;
    }
}