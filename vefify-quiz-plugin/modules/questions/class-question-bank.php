<?php
/**
 * Question Bank Management Admin Interface
 * File: modules/questions/class-question-bank.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Bank {
    
    private $model;
    private $table_prefix;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'vefify_';
        
        // Initialize question model
        $this->model = new Vefify_Question_Model();
        
        // Hook into WordPress
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Register menu with specific priority to place it between Campaigns and Gifts
        add_action('admin_menu', array($this, 'add_admin_menu'), 15);
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_action('wp_ajax_vefify_load_question_preview', array($this, 'ajax_load_question_preview'));
        add_action('wp_ajax_vefify_duplicate_question', array($this, 'ajax_duplicate_question'));
        add_action('wp_ajax_vefify_toggle_question_status', array($this, 'ajax_toggle_question_status'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Add as submenu to main Vefify Quiz menu  
        // This will appear in the correct position: Dashboard → Campaigns → Questions → Gifts
        add_submenu_page(
            'vefify-quiz',              // Parent slug
            'Question Bank',            // Page title
            'Questions',               // Menu title
            'manage_options',          // Capability
            'vefify-questions',        // Menu slug
            array($this, 'render_admin_page')  // Callback - our working function!
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Debug: Check hook name
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Question Bank Hook: ' . $hook);
        }
        
        // Check if we're on the question bank page
        // Hook format: 'vefify-quiz_page_vefify-questions'
        if ($hook !== 'vefify-quiz_page_vefify-questions') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        
        // Build file paths
        $css_path = plugin_dir_url(__FILE__) . 'assets/question-bank.css';
        $js_path = plugin_dir_url(__FILE__) . 'assets/question-bank.js';
        
        // Debug: Check file paths
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CSS Path: ' . $css_path);
            error_log('JS Path: ' . $js_path);
            error_log('CSS File Exists: ' . (file_exists(plugin_dir_path(__FILE__) . 'assets/question-bank.css') ? 'YES' : 'NO'));
            error_log('JS File Exists: ' . (file_exists(plugin_dir_path(__FILE__) . 'assets/question-bank.js') ? 'YES' : 'NO'));
        }
        
        // Enqueue external CSS and JS files
        wp_enqueue_style(
            'vefify-question-bank',
            $css_path,
            array(),
            defined('VEFIFY_QUIZ_VERSION') ? VEFIFY_QUIZ_VERSION : '1.0.0'
        );
        
        wp_enqueue_script(
            'vefify-question-bank',
            $js_path,
            array('jquery'),
            defined('VEFIFY_QUIZ_VERSION') ? VEFIFY_QUIZ_VERSION : '1.0.0',
            true
        );
        
        // Localize script for JavaScript
        wp_localize_script('vefify-question-bank', 'vefifyQuestionBank', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_question_bank'),
            'strings' => array(
                'loading' => __('Loading...', 'vefify-quiz'),
                'errorLoading' => __('Error loading preview', 'vefify-quiz'),
                'selectOne' => __('Select the correct answer', 'vefify-quiz'),
                'selectMultiple' => __('Select all correct answers', 'vefify-quiz'),
                'selectTrueFalse' => __('Select True or False', 'vefify-quiz'),
                'trueFalseMode' => __('True/False Mode - Options are automatically set', 'vefify-quiz'),
                'true' => __('True', 'vefify-quiz'),
                'false' => __('False', 'vefify-quiz')
            )
        ));
        
        // Add inline style as backup for basic styling
        wp_add_inline_style('vefify-question-bank', '
            .questions-filters {
                margin: 20px 0;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
            .questions-filters form {
                display: flex;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
            }
            .difficulty-badge {
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                color: white;
                font-weight: bold;
            }
            .difficulty-badge.difficulty-easy { background: #4caf50; }
            .difficulty-badge.difficulty-medium { background: #ff9800; }
            .difficulty-badge.difficulty-hard { background: #f44336; }
            .category-badge {
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                color: white;
                font-weight: bold;
                background: #607d8b;
            }
            .question-preview-row {
                background: #f9f9f9;
            }
            .question-preview-content {
                padding: 15px;
                background: #f5f5f5;
                border-radius: 4px;
                margin: 10px 0;
            }
        ');
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!isset($_POST['vefify_question_action']) || !current_user_can('manage_options')) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['vefify_question_nonce'], 'vefify_question_action')) {
            wp_die('Security check failed');
        }
        
        $action = sanitize_text_field($_POST['vefify_question_action']);
        
        switch ($action) {
            case 'save_question':
                $this->handle_save_question();
                break;
            case 'import_csv':
                $this->handle_csv_import();
                break;
            case 'bulk_action':
                $this->handle_bulk_action();
                break;
        }
    }
    
    /**
     * Handle save question
     */
    private function handle_save_question() {
        $question_id = !empty($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        
        // Prepare question data
        $data = array(
            'campaign_id' => !empty($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null,
            'question_text' => sanitize_textarea_field($_POST['question_text']),
            'question_type' => sanitize_text_field($_POST['question_type']),
            'category' => sanitize_text_field($_POST['category']),
            'difficulty' => sanitize_text_field($_POST['difficulty']),
            'points' => intval($_POST['points']),
            'explanation' => sanitize_textarea_field($_POST['explanation']),
            'options' => array()
        );
        
        // Handle options
        if (!empty($_POST['options'])) {
            foreach ($_POST['options'] as $index => $option) {
                if (!empty($option['text'])) {
                    $data['options'][] = array(
                        'option_text' => sanitize_textarea_field($option['text']),
                        'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                        'explanation' => sanitize_textarea_field($option['explanation'] ?? '')
                    );
                }
            }
        }
        
        // Save question
        if ($question_id > 0) {
            $result = $this->model->update_question($question_id, $data);
        } else {
            $result = $this->model->create_question($data);
        }
        
        if (is_wp_error($result)) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>Error: ' . $result->get_error_message() . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Question saved successfully!</p></div>';
            });
        }
        
        wp_redirect(admin_url('admin.php?page=vefify-questions'));
        exit;
    }
    
    /**
     * Handle CSV import
     */
    private function handle_csv_import() {
        if (empty($_FILES['csv_file']['tmp_name'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Please select a CSV file to import.</p></div>';
            });
            return;
        }
        
        $campaign_id = !empty($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null;
        
        // Simple CSV processing
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Cannot read CSV file.</p></div>';
            });
            return;
        }
        
        $imported = 0;
        $errors = array();
        $line = 0;
        
        // Skip header row
        fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            
            if (count($row) < 6) {
                $errors[] = "Line {$line}: Insufficient columns";
                continue;
            }
            
            // Expected format: question_text, option1, option2, option3, option4, correct_options, category, difficulty
            $question_data = array(
                'campaign_id' => $campaign_id,
                'question_text' => $row[0],
                'question_type' => 'multiple_choice',
                'category' => $row[6] ?? 'general',
                'difficulty' => $row[7] ?? 'medium',
                'options' => array()
            );
            
            // Process options (columns 1-4)
            for ($i = 1; $i <= 4; $i++) {
                if (!empty($row[$i])) {
                    $is_correct = false;
                    
                    // Check if this option is marked as correct
                    if (!empty($row[5])) {
                        $correct_options = explode(',', $row[5]);
                        $is_correct = in_array((string)$i, $correct_options);
                    }
                    
                    $question_data['options'][] = array(
                        'option_text' => $row[$i],
                        'is_correct' => $is_correct
                    );
                }
            }
            
            // Validate and create question
            if (empty($question_data['question_text'])) {
                $errors[] = "Line {$line}: Question text is required";
                continue;
            }
            
            if (empty($question_data['options'])) {
                $errors[] = "Line {$line}: At least one option is required";
                continue;
            }
            
            $result = $this->model->create_question($question_data);
            
            if (is_wp_error($result)) {
                $errors[] = "Line {$line}: " . $result->get_error_message();
            } else {
                $imported++;
            }
        }
        
        fclose($handle);
        
        add_action('admin_notices', function() use ($imported, $errors) {
            if ($imported > 0) {
                echo '<div class="notice notice-success"><p>' . $imported . ' questions imported successfully!</p></div>';
            }
            if (!empty($errors)) {
                echo '<div class="notice notice-warning"><p>Some errors occurred:<br>' . implode('<br>', array_slice($errors, 0, 5)) . '</p></div>';
            }
        });
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_action() {
        $action = sanitize_text_field($_POST['bulk_action']);
        $question_ids = array_map('intval', $_POST['question_ids'] ?? array());
        
        if (empty($question_ids)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Please select questions to perform bulk action.</p></div>';
            });
            return;
        }
        
        switch ($action) {
            case 'activate':
                $this->bulk_toggle_status($question_ids, 1);
                break;
            case 'deactivate':
                $this->bulk_toggle_status($question_ids, 0);
                break;
            case 'delete':
                $this->bulk_delete($question_ids);
                break;
        }
    }
    
    /**
     * Bulk toggle status
     */
    private function bulk_toggle_status($question_ids, $status) {
        global $wpdb;
        
        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        $query = $wpdb->prepare(
            "UPDATE {$this->table_prefix}questions SET is_active = %d WHERE id IN ({$placeholders})",
            array_merge([$status], $question_ids)
        );
        
        $result = $wpdb->query($query);
        
        if ($result !== false) {
            $action_text = $status ? 'activated' : 'deactivated';
            add_action('admin_notices', function() use ($result, $action_text) {
                echo '<div class="notice notice-success"><p>' . $result . ' questions ' . $action_text . '.</p></div>';
            });
        }
    }
    
    /**
     * Bulk delete
     */
    private function bulk_delete($question_ids) {
        $deleted = 0;
        foreach ($question_ids as $question_id) {
            $result = $this->model->delete_question($question_id);
            if (!is_wp_error($result)) {
                $deleted++;
            }
        }
        
        add_action('admin_notices', function() use ($deleted) {
            echo '<div class="notice notice-success"><p>' . $deleted . ' questions deleted.</p></div>';
        });
    }
    
    /**
     * AJAX: Load question preview
     */
    public function ajax_load_question_preview() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Accept both old and new nonce formats for compatibility
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_question_bank') || 
                      wp_verify_nonce($_POST['_wpnonce'] ?? '', 'vefify_question_action');
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        $question_id = intval($_POST['question_id']);
        $question = $this->model->get_question($question_id);
        
        if (!$question) {
            wp_send_json_error('Question not found');
        }
        
        ob_start();
        $this->render_question_preview($question);
        $html = ob_get_clean();
        
        wp_send_json_success($html);
    }
    
    /**
     * AJAX: Duplicate question
     */
    public function ajax_duplicate_question() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Accept both old and new nonce formats for compatibility
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_question_bank') || 
                      wp_verify_nonce($_POST['_wpnonce'] ?? '', 'vefify_question_action');
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        $question_id = intval($_POST['question_id']);
        $result = $this->model->duplicate_question($question_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array('new_id' => $result));
    }
    
    /**
     * AJAX: Toggle question status
     */
    public function ajax_toggle_question_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Accept both old and new nonce formats for compatibility
        $nonce_valid = wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_question_bank') || 
                      wp_verify_nonce($_POST['_wpnonce'] ?? '', 'vefify_question_action');
        
        if (!$nonce_valid) {
            wp_send_json_error('Security check failed');
        }
        
        $question_id = intval($_POST['question_id']);
        $status = intval($_POST['status']);
        
        global $wpdb;
        $result = $wpdb->update(
            $this->table_prefix . 'questions',
            array('is_active' => $status, 'updated_at' => current_time('mysql')),
            array('id' => $question_id)
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to update status');
        }
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $current_action = $_GET['action'] ?? 'list';
        $question_id = intval($_GET['question_id'] ?? 0);
        
        switch ($current_action) {
            case 'add':
                $this->render_question_form();
                break;
            case 'edit':
                $this->render_question_form($question_id);
                break;
            case 'import':
                $this->render_import_form();
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
        // Get questions with filters
        $args = array(
            'campaign_id' => intval($_GET['campaign_id'] ?? 0) ?: null,
            'category' => sanitize_text_field($_GET['category'] ?? ''),
            'difficulty' => sanitize_text_field($_GET['difficulty'] ?? ''),
            'search' => sanitize_text_field($_GET['search'] ?? ''),
            'page' => intval($_GET['paged'] ?? 1),
            'per_page' => 20
        );
        
        $result = $this->model->get_questions($args);
        $questions = $result['questions'];
        $total_pages = $result['pages'];
        $current_page = $result['current_page'];
        
        // Get campaigns for filter
        global $wpdb;
        $campaigns = $wpdb->get_results(
            "SELECT id, name FROM {$this->table_prefix}campaigns ORDER BY name"
        );
        
        // Get categories for filter
        $categories = $this->model->get_categories();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Question Bank</h1>
            <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=add'); ?>" class="page-title-action">Add New Question</a>
            <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=import'); ?>" class="page-title-action">Import CSV</a>
            
            <hr class="wp-header-end">
            
            <!-- Filters -->
            <div class="questions-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="vefify-questions">
                    
                    <select name="campaign_id">
                        <option value="">All Campaigns</option>
                        <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?php echo $campaign->id; ?>" <?php selected($args['campaign_id'], $campaign->id); ?>>
                                <?php echo esc_html($campaign->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category; ?>" <?php selected($args['category'], $category); ?>>
                                <?php echo esc_html($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="difficulty">
                        <option value="">All Difficulties</option>
                        <option value="easy" <?php selected($args['difficulty'], 'easy'); ?>>Easy</option>
                        <option value="medium" <?php selected($args['difficulty'], 'medium'); ?>>Medium</option>
                        <option value="hard" <?php selected($args['difficulty'], 'hard'); ?>>Hard</option>
                    </select>
                    
                    <input type="text" name="search" placeholder="Search questions..." value="<?php echo esc_attr($args['search']); ?>">
                    
                    <input type="submit" class="button" value="Filter">
                </form>
            </div>
            
            <!-- Bulk Actions -->
            <form id="vefify-bulk-form" method="post" action="">
                <?php wp_nonce_field('vefify_question_action', 'vefify_question_nonce'); ?>
                <input type="hidden" name="vefify_question_action" value="bulk_action">
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="delete">Delete</option>
                        </select>
                        <input type="submit" class="button action" value="Apply">
                    </div>
                </div>
                
                <!-- Questions Table -->
                <table class="wp-list-table widefat fixed striped questions">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th class="manage-column">Question</th>
                            <th class="manage-column">Type</th>
                            <th class="manage-column">Category</th>
                            <th class="manage-column">Difficulty</th>
                            <th class="manage-column">Campaign</th>
                            <th class="manage-column">Status</th>
                            <th class="manage-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($questions)): ?>
                            <tr>
                                <td colspan="8" class="no-items">No questions found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($questions as $question): ?>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" name="question_ids[]" value="<?php echo $question->id; ?>">
                                    </th>
                                    <td class="column-question">
                                        <strong><?php echo esc_html(wp_trim_words($question->question_text, 10)); ?></strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=edit&question_id=' . $question->id); ?>">Edit</a>
                                            </span>
                                            |
                                            <span class="preview">
                                                <a href="#" class="toggle-preview" data-question-id="<?php echo $question->id; ?>">Preview</a>
                                            </span>
                                            |
                                            <span class="duplicate">
                                                <a href="#" class="vefify-duplicate-question" data-question-id="<?php echo $question->id; ?>">Duplicate</a>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($question->question_type); ?></td>
                                    <td>
                                        <span class="category-badge category-<?php echo esc_attr(strtolower($question->category)); ?>">
                                            <?php echo esc_html($question->category); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="difficulty-badge difficulty-<?php echo esc_attr($question->difficulty); ?>">
                                            <?php echo esc_html(ucfirst($question->difficulty)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($question->campaign_name ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="vefify-status-toggle" data-question-id="<?php echo $question->id; ?>" data-status="<?php echo $question->is_active; ?>">
                                            <?php echo $question->is_active ? '✅ Active' : '❌ Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=edit&question_id=' . $question->id); ?>" class="button button-small">Edit</a>
                                    </td>
                                </tr>
                                <tr id="preview-<?php echo $question->id; ?>" class="question-preview-row" style="display: none;">
                                    <td colspan="8">
                                        <div class="question-preview-content">
                                            <!-- Preview content will be loaded here -->
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            $pagination_args = array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page
                            );
                            echo paginate_links($pagination_args);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render question form
     */
    private function render_question_form($question_id = 0) {
        $question = null;
        $options = array();
        
        if ($question_id > 0) {
            $question = $this->model->get_question($question_id);
            if (!$question) {
                echo '<div class="notice notice-error"><p>Question not found.</p></div>';
                return;
            }
            
            $options = $question->options;
        }
        
        // Get campaigns
        global $wpdb;
        $campaigns = $wpdb->get_results(
            "SELECT id, name FROM {$this->table_prefix}campaigns ORDER BY name"
        );
        
        ?>
        <div class="wrap">
            <h1><?php echo $question_id ? 'Edit Question' : 'Add New Question'; ?></h1>
            
            <form method="post" action="" id="question-form" class="vefify-question-form">
                <?php wp_nonce_field('vefify_question_action', 'vefify_question_nonce'); ?>
                <input type="hidden" name="vefify_question_action" value="save_question">
                <?php if ($question_id): ?>
                    <input type="hidden" name="question_id" value="<?php echo $question_id; ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Campaign</th>
                        <td>
                            <select name="campaign_id" class="widefat">
                                <option value="">Select Campaign (Optional)</option>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <option value="<?php echo $campaign->id; ?>" <?php selected($question->campaign_id ?? '', $campaign->id); ?>>
                                        <?php echo esc_html($campaign->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Question Text</th>
                        <td>
                            <textarea id="question_text" name="question_text" rows="4" class="widefat" required><?php echo esc_textarea($question->question_text ?? ''); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Question Type</th>
                        <td>
                            <select id="question_type" name="question_type" class="widefat">
                                <option value="single_select" <?php selected($question->question_type ?? 'single_select', 'single_select'); ?>>Single Choice</option>
                                <option value="multiple_select" <?php selected($question->question_type ?? '', 'multiple_select'); ?>>Multiple Choice</option>
                                <option value="true_false" <?php selected($question->question_type ?? '', 'true_false'); ?>>True/False</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Category</th>
                        <td>
                            <input type="text" name="category" value="<?php echo esc_attr($question->category ?? ''); ?>" class="widefat" list="categories">
                            <datalist id="categories">
                                <?php foreach ($this->model->get_categories() as $category): ?>
                                    <option value="<?php echo esc_attr($category); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Difficulty</th>
                        <td>
                            <select name="difficulty" class="widefat">
                                <option value="easy" <?php selected($question->difficulty ?? 'medium', 'easy'); ?>>Easy</option>
                                <option value="medium" <?php selected($question->difficulty ?? 'medium', 'medium'); ?>>Medium</option>
                                <option value="hard" <?php selected($question->difficulty ?? 'medium', 'hard'); ?>>Hard</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Points</th>
                        <td>
                            <input type="number" name="points" value="<?php echo esc_attr($question->points ?? 1); ?>" min="1" max="10" class="small-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Explanation</th>
                        <td>
                            <textarea name="explanation" rows="3" class="widefat"><?php echo esc_textarea($question->explanation ?? ''); ?></textarea>
                            <p class="description">Optional explanation shown after answering</p>
                        </td>
                    </tr>
                </table>
                
                <h3>Answer Options</h3>
                <div id="answer-options">
                    <?php if (empty($options)): ?>
                        <!-- Default 4 options for new question -->
                        <?php for ($i = 0; $i < 4; $i++): ?>
                            <div class="option-row" data-index="<?php echo $i; ?>">
                                <div class="option-header">
                                    <div class="option-number"><?php echo chr(65 + $i); ?></div>
                                    <div class="option-controls">
                                        <label class="option-correct">
                                            <input type="checkbox" name="options[<?php echo $i; ?>][is_correct]" value="1" class="option-correct-checkbox">
                                            <span class="checkmark"></span>
                                            Correct Answer
                                        </label>
                                        <button type="button" class="remove-option" title="Remove this option">×</button>
                                    </div>
                                </div>
                                <div class="option-content">
                                    <label class="option-label">Answer Option:</label>
                                    <input type="text" name="options[<?php echo $i; ?>][text]" placeholder="Enter answer option..." class="option-text widefat" required>
                                    <label class="option-label">Explanation (Optional):</label>
                                    <textarea name="options[<?php echo $i; ?>][explanation]" placeholder="Optional: Explain why this answer is correct/incorrect..." rows="2" class="option-explanation widefat"></textarea>
                                </div>
                            </div>
                        <?php endfor; ?>
                    <?php else: ?>
                        <!-- Existing options -->
                        <?php foreach ($options as $index => $option): ?>
                            <div class="option-row" data-index="<?php echo $index; ?>">
                                <div class="option-header">
                                    <div class="option-number"><?php echo chr(65 + $index); ?></div>
                                    <div class="option-controls">
                                        <label class="option-correct">
                                            <input type="checkbox" name="options[<?php echo $index; ?>][is_correct]" value="1" class="option-correct-checkbox" <?php checked($option->is_correct, 1); ?>>
                                            <span class="checkmark"></span>
                                            Correct Answer
                                        </label>
                                        <button type="button" class="remove-option" title="Remove this option">×</button>
                                    </div>
                                </div>
                                <div class="option-content">
                                    <label class="option-label">Answer Option:</label>
                                    <input type="text" name="options[<?php echo $index; ?>][text]" value="<?php echo esc_attr($option->option_text); ?>" class="option-text widefat" required>
                                    <label class="option-label">Explanation (Optional):</label>
                                    <textarea name="options[<?php echo $index; ?>][explanation]" rows="2" class="option-explanation widefat" placeholder="Optional: Explain why this answer is correct/incorrect..."><?php echo esc_textarea($option->explanation ?? ''); ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div id="add-option-section">
                    <button type="button" id="add-option" class="button">Add Another Option</button>
                    <small id="options-help" class="help-text">Select the correct answer(s) for this question</small>
                </div>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php echo $question_id ? 'Update Question' : 'Save Question'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render import form
     */
    private function render_import_form() {
        // Get campaigns
        global $wpdb;
        $campaigns = $wpdb->get_results(
            "SELECT id, name FROM {$this->table_prefix}campaigns ORDER BY name"
        );
        
        ?>
        <div class="wrap">
            <h1>Import Questions from CSV</h1>
            
            <div class="vefify-import-instructions">
                <h3>CSV Format Instructions</h3>
                <p>Your CSV file should have the following columns:</p>
                <ul>
                    <li><strong>question_text</strong> - The question text</li>
                    <li><strong>option1</strong> - First answer option</li>
                    <li><strong>option2</strong> - Second answer option</li>
                    <li><strong>option3</strong> - Third answer option</li>
                    <li><strong>option4</strong> - Fourth answer option</li>
                    <li><strong>correct_options</strong> - Comma-separated list of correct option numbers (e.g., "1,3")</li>
                    <li><strong>category</strong> - Question category (optional)</li>
                    <li><strong>difficulty</strong> - easy, medium, or hard (optional)</li>
                </ul>
            </div>
            
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('vefify_question_action', 'vefify_question_nonce'); ?>
                <input type="hidden" name="vefify_question_action" value="import_csv">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Campaign</th>
                        <td>
                            <select name="campaign_id">
                                <option value="">Select Campaign (Optional)</option>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <option value="<?php echo $campaign->id; ?>">
                                        <?php echo esc_html($campaign->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">CSV File</th>
                        <td>
                            <input type="file" name="csv_file" accept=".csv" required>
                            <p class="description">Select a CSV file to import questions from</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="Import Questions">
                    <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render question preview
     */
    private function render_question_preview($question) {
        $options = $question->options ?? array();
        
        ?>
        <div class="vefify-question-preview">
            <h3><?php echo esc_html($question->question_text); ?></h3>
            
            <div class="question-meta">
                <span class="type">Type: <?php echo esc_html($question->question_type); ?></span>
                <span class="category">Category: <?php echo esc_html($question->category); ?></span>
                <span class="difficulty">Difficulty: <?php echo esc_html($question->difficulty); ?></span>
                <span class="points">Points: <?php echo esc_html($question->points); ?></span>
            </div>
            
            <?php if ($question->question_type === 'true_false'): ?>
                <div class="true-false-options">
                    <label><input type="radio" name="preview_answer" value="true"> True</label>
                    <label><input type="radio" name="preview_answer" value="false"> False</label>
                </div>
            <?php else: ?>
                <div class="multiple-choice-options">
                    <?php foreach ($options as $option): ?>
                        <label class="<?php echo $option->is_correct ? 'correct-answer' : ''; ?>">
                            <input type="radio" name="preview_answer" value="<?php echo $option->id; ?>">
                            <?php echo esc_html($option->option_text); ?>
                            <?php if ($option->is_correct): ?>
                                <span class="correct-indicator">✓</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($question->explanation): ?>
                <div class="explanation">
                    <strong>Explanation:</strong> <?php echo esc_html($question->explanation); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}