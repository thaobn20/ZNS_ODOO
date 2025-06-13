<?php
/**
 * Enhanced Question Bank Management
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
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
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        
        wp_add_inline_script('jquery', $this->get_admin_javascript());
        wp_add_inline_style('wp-admin', $this->get_admin_styles());
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
            'question_text' => $_POST['question_text'] ?? '',
            'question_type' => $_POST['question_type'] ?? 'multiple_choice',
            'category' => $_POST['category'] ?? '',
            'difficulty' => $_POST['difficulty'] ?? 'medium',
            'points' => intval($_POST['points'] ?? 1),
            'explanation' => $_POST['explanation'] ?? '',
            'options' => $_POST['options'] ?? array()
        );
        
        // Save or update question
        if ($question_id) {
            $result = $this->model->update_question($question_id, $data);
            $message = 'Question updated successfully!';
            $redirect_action = 'edit';
        } else {
            $result = $this->model->create_question($data);
            $message = 'Question created successfully!';
            $redirect_action = 'edit';
            $question_id = $result;
        }
        
        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
            return;
        }
        
        $this->add_admin_notice($message, 'success');
        
        // Redirect to prevent resubmission
        $redirect_url = add_query_arg(array(
            'page' => 'vefify-questions',
            'action' => $redirect_action,
            'id' => $question_id,
            'message' => 'saved'
        ), admin_url('admin.php'));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle CSV import
     */
    private function handle_csv_import() {
        if (empty($_FILES['csv_file']['tmp_name'])) {
            $this->add_admin_notice('Please select a CSV file to import', 'error');
            return;
        }
        
        $campaign_id = !empty($_POST['import_campaign']) ? intval($_POST['import_campaign']) : null;
        $file_path = $_FILES['csv_file']['tmp_name'];
        
        $result = $this->import_questions_from_csv($file_path, $campaign_id);
        
        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $message = sprintf(
                'Import completed: %d questions imported successfully. %d errors encountered.',
                $result['imported'],
                count($result['errors'])
            );
            $this->add_admin_notice($message, $result['imported'] > 0 ? 'success' : 'warning');
        }
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'new':
                $this->render_question_form();
                break;
            case 'edit':
                $this->render_question_form($_GET['id'] ?? 0);
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
        // Get filters
        $filters = array(
            'campaign_id' => $_GET['campaign_id'] ?? '',
            'category' => $_GET['category'] ?? '',
            'difficulty' => $_GET['difficulty'] ?? '',
            'search' => $_GET['search'] ?? '',
            'page' => $_GET['paged'] ?? 1
        );
        
        // Get questions
        $result = $this->model->get_questions($filters);
        $questions = $result['questions'];
        $total_pages = $result['pages'];
        
        // Get filter options
        $campaigns = $this->wpdb->get_results("SELECT id, name FROM {$this->table_prefix}campaigns WHERE is_active = 1 ORDER BY name");
        $categories = $this->model->get_categories();
        $stats = $this->model->get_statistics();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Question Bank</h1>
            <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=new'); ?>" class="page-title-action">Add New Question</a>
            <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=import'); ?>" class="page-title-action">Import Questions</a>
            
            <!-- Statistics Overview -->
            <div class="vefify-question-stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>📊 Total Questions</h3>
                        <div class="stat-number"><?php echo number_format($stats->total); ?></div>
                        <div class="stat-subtitle"><?php echo number_format($stats->active); ?> active</div>
                    </div>
                    <div class="stat-card">
                        <h3>🎯 By Difficulty</h3>
                        <div class="difficulty-breakdown">
                            <span class="easy"><?php echo $stats->easy; ?> Easy</span>
                            <span class="medium"><?php echo $stats->medium; ?> Medium</span>
                            <span class="hard"><?php echo $stats->hard; ?> Hard</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <h3>📝 By Type</h3>
                        <div class="type-breakdown">
                            <span><?php echo $stats->single_choice; ?> Single Choice</span>
                            <span><?php echo $stats->multi_choice; ?> Multiple Choice</span>
                            <span><?php echo $stats->true_false; ?> True/False</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="vefify-question-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="vefify-questions">
                    
                    <div class="filter-row">
                        <select name="campaign_id" onchange="this.form.submit()">
                            <option value="">All Campaigns</option>
                            <?php foreach ($campaigns as $campaign): ?>
                                <option value="<?php echo $campaign->id; ?>" <?php selected($filters['campaign_id'], $campaign->id); ?>>
                                    <?php echo esc_html($campaign->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category); ?>" <?php selected($filters['category'], $category); ?>>
                                    <?php echo esc_html(ucfirst($category)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="difficulty" onchange="this.form.submit()">
                            <option value="">All Difficulties</option>
                            <option value="easy" <?php selected($filters['difficulty'], 'easy'); ?>>Easy</option>
                            <option value="medium" <?php selected($filters['difficulty'], 'medium'); ?>>Medium</option>
                            <option value="hard" <?php selected($filters['difficulty'], 'hard'); ?>>Hard</option>
                        </select>
                        
                        <input type="search" name="search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Search questions...">
                        <button type="submit" class="button">Filter</button>
                        
                        <?php if (array_filter($filters)): ?>
                            <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Questions Table -->
            <form method="post" id="bulk-action-form">
                <?php wp_nonce_field('vefify_question_action', 'vefify_question_nonce'); ?>
                <input type="hidden" name="vefify_question_action" value="bulk_action">
                
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="bulk_action">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="delete">Delete</option>
                            <option value="duplicate">Duplicate</option>
                        </select>
                        <button type="submit" class="button action">Apply</button>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="check-column"><input type="checkbox" id="cb-select-all"></td>
                            <th width="40%">Question</th>
                            <th>Campaign</th>
                            <th>Category</th>
                            <th>Difficulty</th>
                            <th>Type</th>
                            <th>Options</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $question): ?>
                        <tr class="<?php echo $question->is_active ? 'active' : 'inactive'; ?>">
                            <th class="check-column">
                                <input type="checkbox" name="question_ids[]" value="<?php echo $question->id; ?>">
                            </th>
                            <td>
                                <strong>
                                    <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=edit&id=' . $question->id); ?>">
                                        <?php echo esc_html(wp_trim_words($question->question_text, 12)); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=edit&id=' . $question->id); ?>">Edit</a> |
                                    </span>
                                    <span class="duplicate">
                                        <a href="#" class="duplicate-question" data-question-id="<?php echo $question->id; ?>">Duplicate</a> |
                                    </span>
                                    <span class="status">
                                        <a href="#" class="toggle-status" data-question-id="<?php echo $question->id; ?>" data-status="<?php echo $question->is_active; ?>">
                                            <?php echo $question->is_active ? 'Deactivate' : 'Activate'; ?>
                                        </a> |
                                    </span>
                                    <span class="delete">
                                        <a href="#" class="delete-question" data-question-id="<?php echo $question->id; ?>">Delete</a>
                                    </span>
                                </div>
                            </td>
                            <td><?php echo esc_html($question->campaign_name ?: 'Global'); ?></td>
                            <td>
                                <span class="category-badge category-<?php echo esc_attr($question->category); ?>">
                                    <?php echo esc_html(ucfirst($question->category ?: 'General')); ?>
                                </span>
                            </td>
                            <td>
                                <span class="difficulty-badge difficulty-<?php echo esc_attr($question->difficulty); ?>">
                                    <?php echo esc_html(ucfirst($question->difficulty)); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $type_labels = array(
                                    'multiple_choice' => 'Single Choice',
                                    'multiple_select' => 'Multiple Choice',
                                    'true_false' => 'True/False'
                                );
                                echo $type_labels[$question->question_type] ?? $question->question_type;
                                ?>
                            </td>
                            <td>
                                <div class="option-count">
                                    <?php echo $question->option_count; ?> options<br>
                                    <small><?php echo $question->correct_count; ?> correct</small>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $question->is_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $question->is_active ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button button-small toggle-preview" data-question-id="<?php echo $question->id; ?>">
                                    Preview
                                </button>
                            </td>
                        </tr>
                        <tr class="question-preview" id="preview-<?php echo $question->id; ?>" style="display: none;">
                            <td colspan="9">
                                <div class="question-preview-content">
                                    <div class="preview-loading">Loading preview...</div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'current' => $filters['page'],
                            'total' => $total_pages,
                        ));
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
        $question = $question_id ? $this->model->get_question($question_id) : null;
        $is_edit = !empty($question);
        $title = $is_edit ? 'Edit Question' : 'New Question';
        
        // Get campaigns for dropdown
        $campaigns = $this->wpdb->get_results("SELECT id, name FROM {$this->table_prefix}campaigns ORDER BY name");
        
        ?>
        <div class="wrap">
            <h1><?php echo $title; ?></h1>
            
            <form method="post" action="" id="vefify-question-form" class="vefify-question-form">
                <?php wp_nonce_field('vefify_question_action', 'vefify_question_nonce'); ?>
                <input type="hidden" name="vefify_question_action" value="save_question">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="question_id" value="<?php echo $question->id; ?>">
                <?php endif; ?>
                
                <div class="form-container">
                    <div class="form-main">
                        <!-- Question Details -->
                        <div class="form-section">
                            <h3>Question Details</h3>
                            
                            <div class="form-field">
                                <label for="question_text">Question Text *</label>
                                <textarea id="question_text" name="question_text" rows="3" class="large-text" required><?php echo $is_edit ? esc_textarea($question->question_text) : ''; ?></textarea>
                                <p class="description">Enter the question that participants will see</p>
                            </div>
                            
                            <div class="form-field">
                                <label for="explanation">Explanation (Optional)</label>
                                <textarea id="explanation" name="explanation" rows="2" class="large-text"><?php echo $is_edit ? esc_textarea($question->explanation) : ''; ?></textarea>
                                <p class="description">Explain the correct answer (shown after completion)</p>
                            </div>
                        </div>
                        
                        <!-- Answer Options -->
                        <div class="form-section">
                            <h3>Answer Options</h3>
                            <div id="answer-options">
                                <?php
                                if ($is_edit && $question->options) {
                                    foreach ($question->options as $index => $option) {
                                        echo $this->render_option_row($index, $option->option_text, $option->is_correct, $option->explanation);
                                    }
                                } else {
                                    // Default 4 options for new questions
                                    for ($i = 0; $i < 4; $i++) {
                                        echo $this->render_option_row($i, '', false, '');
                                    }
                                }
                                ?>
                            </div>
                            
                            <div class="option-controls">
                                <button type="button" id="add-option" class="button">Add Another Option</button>
                                <span class="description">You need at least 2 options, and at least 1 must be marked as correct.</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-sidebar">
                        <!-- Settings -->
                        <div class="form-section">
                            <h3>Settings</h3>
                            
                            <div class="form-field">
                                <label for="campaign_id">Campaign</label>
                                <select id="campaign_id" name="campaign_id">
                                    <option value="">Global (All Campaigns)</option>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo $campaign->id; ?>" 
                                                <?php selected($is_edit ? $question->campaign_id : '', $campaign->id); ?>>
                                            <?php echo esc_html($campaign->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-field">
                                <label for="question_type">Question Type</label>
                                <select id="question_type" name="question_type">
                                    <option value="multiple_choice" <?php selected($is_edit ? $question->question_type : 'multiple_choice', 'multiple_choice'); ?>>
                                        Single Choice (one correct answer)
                                    </option>
                                    <option value="multiple_select" <?php selected($is_edit ? $question->question_type : '', 'multiple_select'); ?>>
                                        Multiple Choice (multiple correct answers)
                                    </option>
                                    <option value="true_false" <?php selected($is_edit ? $question->question_type : '', 'true_false'); ?>>
                                        True/False
                                    </option>
                                </select>
                            </div>
                            
                            <div class="form-field">
                                <label for="category">Category</label>
                                <select id="category" name="category">
                                    <option value="">Select Category</option>
                                    <option value="medication" <?php selected($is_edit ? $question->category : '', 'medication'); ?>>Medication</option>
                                    <option value="nutrition" <?php selected($is_edit ? $question->category : '', 'nutrition'); ?>>Nutrition</option>
                                    <option value="safety" <?php selected($is_edit ? $question->category : '', 'safety'); ?>>Safety</option>
                                    <option value="hygiene" <?php selected($is_edit ? $question->category : '', 'hygiene'); ?>>Hygiene</option>
                                    <option value="wellness" <?php selected($is_edit ? $question->category : '', 'wellness'); ?>>Wellness</option>
                                    <option value="pharmacy" <?php selected($is_edit ? $question->category : '', 'pharmacy'); ?>>Pharmacy</option>
                                    <option value="general" <?php selected($is_edit ? $question->category : '', 'general'); ?>>General</option>
                                </select>
                            </div>
                            
                            <div class="form-field">
                                <label for="difficulty">Difficulty</label>
                                <select id="difficulty" name="difficulty">
                                    <option value="easy" <?php selected($is_edit ? $question->difficulty : 'medium', 'easy'); ?>>Easy</option>
                                    <option value="medium" <?php selected($is_edit ? $question->difficulty : 'medium', 'medium'); ?>>Medium</option>
                                    <option value="hard" <?php selected($is_edit ? $question->difficulty : 'medium', 'hard'); ?>>Hard</option>
                                </select>
                            </div>
                            
                            <div class="form-field">
                                <label for="points">Points</label>
                                <input type="number" id="points" name="points" value="<?php echo $is_edit ? $question->points : 1; ?>" 
                                       min="1" max="10" class="small-text">
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="form-section">
                            <div class="form-actions">
                                <?php submit_button($is_edit ? 'Update Question' : 'Save Question', 'primary', 'submit', false); ?>
                                <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Cancel</a>
                            </div>
                        </div>
                        
                        <?php if ($is_edit): ?>
                        <!-- Question Stats -->
                        <div class="form-section">
                            <h3>Question Statistics</h3>
                            <div class="question-meta">
                                <p><strong>Created:</strong> <?php echo mysql2date('M j, Y g:i A', $question->created_at); ?></p>
                                <?php if ($question->updated_at): ?>
                                <p><strong>Updated:</strong> <?php echo mysql2date('M j, Y g:i A', $question->updated_at); ?></p>
                                <?php endif; ?>
                                <p><strong>Status:</strong> <?php echo $question->is_active ? 'Active' : 'Inactive'; ?></p>
                                <p><strong>Options:</strong> <?php echo count($question->options); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render option row
     */
    private function render_option_row($index, $text = '', $is_correct = false, $explanation = '') {
        ob_start();
        ?>
        <div class="option-row <?php echo $is_correct ? 'correct' : ''; ?>" data-option-index="<?php echo esc_attr($index); ?>">
            <div class="option-header">
                <div class="option-number"><?php echo intval($index) + 1; ?></div>
                <label class="option-correct">
                    <input type="checkbox" 
                           name="options[<?php echo esc_attr($index); ?>][is_correct]" 
                           value="1" 
                           class="option-correct-checkbox" 
                           <?php checked($is_correct, true); ?>>
                    Correct Answer
                </label>
                <button type="button" class="remove-option" title="Remove this option">×</button>
            </div>
            
            <div class="option-content">
                <input type="text" 
                       name="options[<?php echo esc_attr($index); ?>][text]" 
                       value="<?php echo esc_attr($text); ?>" 
                       placeholder="Enter answer option..." 
                       class="option-text widefat" 
                       required>
                
                <textarea name="options[<?php echo esc_attr($index); ?>][explanation]" 
                          placeholder="Optional: Explain why this answer is correct/incorrect..."
                          rows="2" 
                          class="option-explanation widefat"><?php echo esc_textarea($explanation); ?></textarea>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render import page
     */
    private function render_import_page() {
        // Get campaigns for dropdown
        $campaigns = $this->wpdb->get_results("SELECT id, name FROM {$this->table_prefix}campaigns ORDER BY name");
        
        ?>
        <div class="wrap">
            <h1>Import Questions</h1>
            
            <div class="import-container">
                <div class="import-main">
                    <div class="form-section">
                        <h3>📁 CSV Import</h3>
                        <p>Upload a CSV file containing questions and answers.</p>
                        
                        <form method="post" enctype="multipart/form-data">
                            <?php wp_nonce_field('vefify_question_action', 'vefify_question_nonce'); ?>
                            <input type="hidden" name="vefify_question_action" value="import_csv">
                            
                            <table class="form-table">
                                <tr>
                                    <th><label for="csv_file">CSV File</label></th>
                                    <td>
                                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                        <p class="description">Select a CSV file with questions</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="import_campaign">Campaign</label></th>
                                    <td>
                                        <select name="import_campaign" id="import_campaign">
                                            <option value="">Global (All Campaigns)</option>
                                            <?php foreach ($campaigns as $campaign): ?>
                                                <option value="<?php echo $campaign->id; ?>">
                                                    <?php echo esc_html($campaign->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php submit_button('Import Questions'); ?>
                        </form>
                    </div>
                </div>
                
                <div class="import-sidebar">
                    <div class="form-section">
                        <h3>📋 CSV Format Guide</h3>
                        <p>Your CSV file should have these columns:</p>
                        
                        <table class="wp-list-table widefat">
                            <thead>
                                <tr>
                                    <th>Column</th>
                                    <th>Required</th>
                                    <th>Example</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>question_text</strong></td>
                                    <td>Yes</td>
                                    <td>What is Aspirin used for?</td>
                                </tr>
                                <tr>
                                    <td><strong>option_1</strong></td>
                                    <td>Yes</td>
                                    <td>Pain relief</td>
                                </tr>
                                <tr>
                                    <td><strong>option_2</strong></td>
                                    <td>Yes</td>
                                    <td>Fever reduction</td>
                                </tr>
                                <tr>
                                    <td><strong>option_3</strong></td>
                                    <td>No</td>
                                    <td>Sleep aid</td>
                                </tr>
                                <tr>
                                    <td><strong>option_4</strong></td>
                                    <td>No</td>
                                    <td>Anxiety treatment</td>
                                </tr>
                                <tr>
                                    <td><strong>correct_answers</strong></td>
                                    <td>Yes</td>
                                    <td>1,2 (for multiple correct)</td>
                                </tr>
                                <tr>
                                    <td><strong>category</strong></td>
                                    <td>No</td>
                                    <td>medication</td>
                                </tr>
                                <tr>
                                    <td><strong>difficulty</strong></td>
                                    <td>No</td>
                                    <td>easy, medium, hard</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <h4>📄 Sample CSV</h4>
                        <textarea readonly class="sample-csv">question_text,option_1,option_2,option_3,option_4,correct_answers,category,difficulty
"What is Aspirin used for?","Pain relief","Fever reduction","Sleep aid","Anxiety treatment","1,2","medication","easy"
"Which vitamin helps bone health?","Vitamin A","Vitamin C","Vitamin D","Vitamin E","3","nutrition","medium"</textarea>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Load question preview
     */
    public function ajax_load_question_preview() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_preview')) {
            wp_send_json_error('Security check failed');
        }
        
        $question_id = intval($_POST['question_id'] ?? 0);
        $question = $this->model->get_question($question_id);
        
        if (!$question) {
            wp_send_json_error('Question not found');
        }
        
        ob_start();
        ?>
        <div class="preview-question">
            <h4><?php echo esc_html($question->question_text); ?></h4>
            <div class="preview-meta">
                <span><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $question->question_type)); ?></span>
                <span><strong>Category:</strong> <?php echo ucfirst($question->category ?: 'General'); ?></span>
                <span><strong>Difficulty:</strong> <?php echo ucfirst($question->difficulty); ?></span>
                <span><strong>Points:</strong> <?php echo $question->points; ?></span>
            </div>
            <div class="preview-options">
                <?php foreach ($question->options as $option): ?>
                    <div class="preview-option <?php echo $option->is_correct ? 'correct' : 'incorrect'; ?>">
                        <span class="option-marker"><?php echo $option->is_correct ? '✓' : '✗'; ?></span>
                        <span class="option-text"><?php echo esc_html($option->option_text); ?></span>
                        <?php if ($option->explanation): ?>
                            <div class="option-explanation"><?php echo esc_html($option->explanation); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($question->explanation): ?>
                <div class="preview-explanation">
                    <strong>Explanation:</strong> <?php echo esc_html($question->explanation); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        wp_send_json_success(ob_get_clean());
    }
    
    /**
     * AJAX: Duplicate question
     */
    public function ajax_duplicate_question() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_duplicate')) {
            wp_send_json_error('Security check failed');
        }
        
        $question_id = intval($_POST['question_id'] ?? 0);
        $result = $this->model->duplicate_question($question_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => 'Question duplicated successfully',
            'new_question_id' => $result
        ));
    }
    
    /**
     * AJAX: Toggle question status
     */
    public function ajax_toggle_question_status() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_toggle_status')) {
            wp_send_json_error('Security check failed');
        }
        
        $question_id = intval($_POST['question_id'] ?? 0);
        $current_status = intval($_POST['current_status'] ?? 0);
        $new_status = $current_status ? 0 : 1;
        
        $result = $this->wpdb->update(
            $this->table_prefix . 'questions',
            array('is_active' => $new_status, 'updated_at' => current_time('mysql')),
            array('id' => $question_id)
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update question status');
        }
        
        wp_send_json_success(array(
            'message' => $new_status ? 'Question activated' : 'Question deactivated',
            'new_status' => $new_status
        ));
    }
    
    /**
     * Import questions from CSV
     */
    private function import_questions_from_csv($file_path, $campaign_id = null) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'CSV file not found');
        }
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_error', 'Cannot read CSV file');
        }
        
        $imported = 0;
        $errors = array();
        $line = 0;
        
        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return new WP_Error('invalid_format', 'Invalid CSV format - no headers found');
        }
        
        // Map headers to indices
        $header_map = array_flip(array_map('strtolower', $headers));
        
        // Required columns
        $required = array('question_text', 'option_1', 'option_2', 'correct_answers');
        foreach ($required as $req) {
            if (!isset($header_map[$req])) {
                fclose($handle);
                return new WP_Error('missing_column', "Required column '$req' not found in CSV");
            }
        }
        
        while (($data = fgetcsv($handle)) !== false) {
            $line++;
            
            if (empty($data[0])) continue; // Skip empty rows
            
            try {
                // Extract question data
                $question_text = $data[$header_map['question_text']] ?? '';
                if (empty($question_text)) {
                    $errors[] = "Line $line: Question text is required";
                    continue;
                }
                
                // Get options
                $options = array();
                for ($i = 1; $i <= 6; $i++) {
                    $option_key = "option_$i";
                    if (isset($header_map[$option_key]) && !empty($data[$header_map[$option_key]])) {
                        $options[] = array(
                            'text' => trim($data[$header_map[$option_key]]),
                            'is_correct' => false
                        );
                    }
                }
                
                if (count($options) < 2) {
                    $errors[] = "Line $line: At least 2 options required";
                    continue;
                }
                
                // Get correct answers
                $correct_answers = $data[$header_map['correct_answers']] ?? '';
                $correct_indices = array_map('trim', explode(',', $correct_answers));
                $correct_indices = array_filter($correct_indices, 'is_numeric');
                
                if (empty($correct_indices)) {
                    $errors[] = "Line $line: At least one correct answer required";
                    continue;
                }
                
                // Mark correct options
                foreach ($correct_indices as $correct_index) {
                    $index = intval($correct_index) - 1; // Convert to 0-based index
                    if (isset($options[$index])) {
                        $options[$index]['is_correct'] = true;
                    }
                }
                
                // Prepare question data
                $question_data = array(
                    'campaign_id' => $campaign_id,
                    'question_text' => $question_text,
                    'question_type' => count($correct_indices) > 1 ? 'multiple_select' : 'multiple_choice',
                    'category' => isset($header_map['category']) ? sanitize_text_field($data[$header_map['category']]) : 'general',
                    'difficulty' => isset($header_map['difficulty']) ? sanitize_text_field($data[$header_map['difficulty']]) : 'medium',
                    'explanation' => isset($header_map['explanation']) ? sanitize_textarea_field($data[$header_map['explanation']]) : '',
                    'points' => 1,
                    'options' => $options
                );
                
                // Create question
                $result = $this->model->create_question($question_data);
                
                if (is_wp_error($result)) {
                    $errors[] = "Line $line: " . $result->get_error_message();
                } else {
                    $imported++;
                }
                
            } catch (Exception $e) {
                $errors[] = "Line $line: " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        return array(
            'imported' => $imported,
            'errors' => $errors,
            'total_lines' => $line
        );
    }
    
    /**
     * Add admin notice
     */
    private function add_admin_notice($message, $type = 'info') {
        add_action('admin_notices', function() use ($message, $type) {
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        });
    }
    
    /**
     * Get admin JavaScript
     */
    private function get_admin_javascript() {
        return "
        jQuery(document).ready(function($) {
            let optionCount = $('.option-row').length;
            
            // Function to update option numbers and indexes
            function updateOptionNumbers() {
                $('.option-row').each(function(index) {
                    $(this).find('.option-number').text(index + 1);
                    $(this).attr('data-option-index', index);
                    
                    // Update form field names
                    $(this).find('input, textarea').each(function() {
                        const name = $(this).attr('name');
                        if (name) {
                            const newName = name.replace(/options\[\d+\]/, 'options[' + index + ']');
                            $(this).attr('name', newName);
                        }
                    });
                });
                optionCount = $('.option-row').length;
            }
            
            // Add new option
            $('#add-option').click(function() {
                const template = `
                    <div class=\"option-row\" data-option-index=\"__INDEX__\">
                        <div class=\"option-header\">
                            <div class=\"option-number\">__NUMBER__</div>
                            <label class=\"option-correct\">
                                <input type=\"checkbox\" name=\"options[__INDEX__][is_correct]\" value=\"1\" class=\"option-correct-checkbox\">
                                Correct Answer
                            </label>
                            <button type=\"button\" class=\"remove-option\" title=\"Remove this option\">×</button>
                        </div>
                        <div class=\"option-content\">
                            <input type=\"text\" name=\"options[__INDEX__][text]\" placeholder=\"Enter answer option...\" class=\"option-text widefat\" required>
                            <textarea name=\"options[__INDEX__][explanation]\" placeholder=\"Optional explanation...\" rows=\"2\" class=\"option-explanation widefat\"></textarea>
                        </div>
                    </div>
                `;
                
                const newOption = template.replace(/__INDEX__/g, optionCount).replace(/__NUMBER__/g, optionCount + 1);
                $('#answer-options').append(newOption);
                updateOptionNumbers();
            });
            
            // Remove option
            $(document).on('click', '.remove-option', function(e) {
                e.preventDefault();
                if ($('.option-row').length > 2) {
                    $(this).closest('.option-row').remove();
                    updateOptionNumbers();
                } else {
                    alert('You need at least 2 options.');
                }
            });
            
            // Handle correct answer checkbox
            $(document).on('change', '.option-correct-checkbox', function() {
                const row = $(this).closest('.option-row');
                const questionType = $('#question_type').val();
                
                if (questionType === 'multiple_choice' && this.checked) {
                    // For single choice, uncheck all other options
                    $('.option-correct-checkbox').not(this).prop('checked', false);
                    $('.option-row').removeClass('correct');
                }
                
                row.toggleClass('correct', this.checked);
            });
            
            // Question type change
            $('#question_type').change(function() {
                const type = $(this).val();
                
                if (type === 'true_false') {
                    // Limit to 2 options for true/false
                    $('.option-row:gt(1)').remove();
                    $('#add-option').hide();
                    
                    // Set default true/false options
                    $('.option-row:eq(0) .option-text').val('True');
                    $('.option-row:eq(1) .option-text').val('False');
                    updateOptionNumbers();
                } else {
                    $('#add-option').show();
                }
            });
            
            // Form validation
            $('#vefify-question-form').submit(function(e) {
                const filledOptions = $('.option-text').filter(function() {
                    return $(this).val().trim() !== '';
                });
                
                const checkedOptions = $('.option-correct-checkbox:checked');
                
                if (filledOptions.length < 2) {
                    alert('You need at least 2 answer options with text.');
                    e.preventDefault();
                    return false;
                }
                
                if (checkedOptions.length === 0) {
                    alert('You need to mark at least one correct answer.');
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
            
            // Preview toggle
            $('.toggle-preview').click(function() {
                const questionId = $(this).data('question-id');
                const previewRow = $('#preview-' + questionId);
                const button = $(this);
                
                if (previewRow.is(':visible')) {
                    previewRow.hide();
                    button.text('Preview');
                } else {
                    // Load preview via AJAX
                    $.post(ajaxurl, {
                        action: 'vefify_load_question_preview',
                        question_id: questionId,
                        nonce: '" . wp_create_nonce('vefify_preview') . "'
                    }, function(response) {
                        if (response.success) {
                            previewRow.find('.question-preview-content').html(response.data);
                            previewRow.show();
                            button.text('Hide Preview');
                        }
                    });
                }
            });
            
            // Bulk actions
            $('#cb-select-all').change(function() {
                $('input[name=\"question_ids[]\"]').prop('checked', this.checked);
            });
            
            // Quick actions
            $('.duplicate-question').click(function(e) {
                e.preventDefault();
                const questionId = $(this).data('question-id');
                
                if (confirm('Duplicate this question?')) {
                    $.post(ajaxurl, {
                        action: 'vefify_duplicate_question',
                        question_id: questionId,
                        nonce: '" . wp_create_nonce('vefify_duplicate') . "'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data);
                        }
                    });
                }
            });
            
            $('.toggle-status').click(function(e) {
                e.preventDefault();
                const questionId = $(this).data('question-id');
                const currentStatus = $(this).data('status');
                const link = $(this);
                
                $.post(ajaxurl, {
                    action: 'vefify_toggle_question_status',
                    question_id: questionId,
                    current_status: currentStatus,
                    nonce: '" . wp_create_nonce('vefify_toggle_status') . "'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            });
            
            $('.delete-question').click(function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to delete this question?')) {
                    // Handle delete - you can implement this
                    alert('Delete functionality not implemented yet');
                }
            });
            
            // Initialize
            updateOptionNumbers();
        });
        ";
    }
    
    /**
     * Get admin styles
     */
    private function get_admin_styles() {
        return "
        .vefify-question-stats {
            margin: 20px 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #4facfe;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2271b1;
            margin-bottom: 5px;
        }
        
        .stat-subtitle {
            font-size: 12px;
            color: #666;
        }
        
        .difficulty-breakdown span,
        .type-breakdown span {
            display: block;
            font-size: 12px;
            color: #666;
            margin: 2px 0;
        }
        
        .vefify-question-filters {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .filter-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .vefify-question-form .form-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .form-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .form-section h3 {
            margin: 0 0 15px 0;
            color: #333;
            border-bottom: 2px solid #4facfe;
            padding-bottom: 10px;
        }
        
        .form-field {
            margin-bottom: 20px;
        }
        
        .form-field label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .option-row {
            background: #f9f9f9;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .option-row.correct {
            border-left-color: #00a32a;
            background: #f0f8f0;
        }
        
        .option-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .option-number {
            background: #666;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .option-correct {
            flex-grow: 1;
        }
        
        .remove-option {
            background: none;
            border: none;
            color: #dc3232;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
        }
        
        .option-content {
            display: grid;
            gap: 10px;
        }
        
        .option-controls {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        
        .category-badge,
        .difficulty-badge,
        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            color: white;
        }
        
        .category-badge.category-medication { background: #2196f3; }
        .category-badge.category-nutrition { background: #4caf50; }
        .category-badge.category-safety { background: #ff9800; }
        .category-badge.category-hygiene { background: #9c27b0; }
        .category-badge.category-wellness { background: #00bcd4; }
        .category-badge.category-pharmacy { background: #795548; }
        .category-badge.category-general { background: #666; }
        
        .difficulty-badge.difficulty-easy { background: #4caf50; }
        .difficulty-badge.difficulty-medium { background: #ff9800; }
        .difficulty-badge.difficulty-hard { background: #f44336; }
        
        .status-badge.status-active { background: #00a32a; }
        .status-badge.status-inactive { background: #666; }
        
        .question-preview-content {
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .preview-question h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .preview-meta {
            margin-bottom: 15px;
            font-size: 12px;
            color: #666;
        }
        
        .preview-meta span {
            margin-right: 15px;
        }
        
        .preview-option {
            margin: 8px 0;
            padding: 8px;
            border-radius: 4px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        
        .preview-option.correct {
            background: #d4edda;
            color: #155724;
        }
        
        .preview-option.incorrect {
            background: #f8d7da;
            color: #721c24;
        }
        
        .option-marker {
            font-weight: bold;
            font-size: 14px;
        }
        
        .option-explanation {
            font-size: 12px;
            font-style: italic;
            margin-top: 4px;
            opacity: 0.8;
        }
        
        .preview-explanation {
            margin-top: 15px;
            padding: 10px;
            background: #e7f3ff;
            border-left: 4px solid #0073aa;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .import-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .sample-csv {
            width: 100%;
            height: 80px;
            font-family: monospace;
            font-size: 11px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
        }
        
        .question-meta p {
            margin: 5px 0;
            font-size: 13px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .vefify-question-form .form-container,
            .import-container {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        ";
    }
}