<?php
/**
 * Enhanced Question Bank Management - COMPLETE VERSION
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
        // Add Questions submenu to main Vefify Quiz menu
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
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
     * Main admin page router
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
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
     * Render questions list - FIXED wpdb::prepare error
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
        
        // Fixed: Only use wpdb::prepare if we have parameters
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
        
        // Get filter options - Fixed: No parameters needed
        $campaigns = $this->wpdb->get_results("SELECT id, name FROM {$this->table_prefix}campaigns WHERE is_active = 1 ORDER BY name");
        $categories = $this->wpdb->get_col("SELECT DISTINCT category FROM {$this->table_prefix}questions WHERE category IS NOT NULL ORDER BY category");
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Question Bank</h1>
            <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=new'); ?>" class="page-title-action">Add New Question</a>
            <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=import'); ?>" class="page-title-action">Import Questions</a>
            
            <!-- Filters -->
            <div class="questions-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="vefify-questions">
                    
                    <select name="campaign_id" onchange="this.form.submit()">
                        <option value="">All Campaigns</option>
                        <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?php echo $campaign->id; ?>" <?php selected($campaign_filter, $campaign->id); ?>>
                                <?php echo esc_html($campaign->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="category" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo esc_attr($category); ?>" <?php selected($category_filter, $category); ?>>
                                <?php echo esc_html(ucfirst($category)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="difficulty" onchange="this.form.submit()">
                        <option value="">All Difficulties</option>
                        <option value="easy" <?php selected($difficulty_filter, 'easy'); ?>>Easy</option>
                        <option value="medium" <?php selected($difficulty_filter, 'medium'); ?>>Medium</option>
                        <option value="hard" <?php selected($difficulty_filter, 'hard'); ?>>Hard</option>
                    </select>
                    
                    <?php if ($campaign_filter || $category_filter || $difficulty_filter): ?>
                        <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="40%">Question</th>
                        <th>Campaign</th>
                        <th>Category</th>
                        <th>Difficulty</th>
                        <th>Type</th>
                        <th>Options</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $question): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html(wp_trim_words($question->question_text, 12)); ?></strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=edit&id=' . $question->id); ?>">Edit</a> |
                                </span>
                                <span class="delete">
                                    <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=delete&id=' . $question->id); ?>" 
                                       onclick="return confirm('Are you sure you want to delete this question?')">Delete</a>
                                </span>
                            </div>
                        </td>
                        <td><?php echo esc_html($question->campaign_name ?: 'Global'); ?></td>
                        <td>
                            <span class="category-badge category-<?php echo esc_attr($question->category); ?>">
                                <?php echo esc_html(ucfirst($question->category)); ?>
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
                                'single_select' => 'Single Choice',
                                'multiple_select' => 'Multiple Choice',
                                'true_false' => 'True/False'
                            );
                            echo $type_labels[$question->question_type] ?? $question->question_type;
                            ?>
                        </td>
                        <td>
                            <?php echo $question->option_count; ?> options<br>
                            <small><?php echo $question->correct_count; ?> correct</small>
                        </td>
                        <td>
                            <button class="button button-small toggle-preview" data-question-id="<?php echo $question->id; ?>">
                                Preview
                            </button>
                        </td>
                    </tr>
                    <tr class="question-preview" id="preview-<?php echo $question->id; ?>" style="display: none;">
                        <td colspan="7">
                            <div class="question-preview-content">
                                <div class="preview-loading">Loading options...</div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="questions-stats">
                <h3>📊 Question Bank Statistics</h3>
                <?php
                // Fixed: No parameters needed
                $stats = $this->wpdb->get_row("
                    SELECT COUNT(*) as total,
                           COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy,
                           COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium,
                           COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard,
                           COUNT(CASE WHEN question_type = 'single_select' THEN 1 END) as single_choice,
                           COUNT(CASE WHEN question_type = 'multiple_select' THEN 1 END) as multi_choice,
                           COUNT(CASE WHEN question_type = 'true_false' THEN 1 END) as true_false
                    FROM {$this->table_prefix}questions 
                    WHERE is_active = 1
                ");
                ?>
                <div class="stats-grid">
                    <div class="stat-item">
                        <strong><?php echo number_format($stats->total); ?></strong>
                        <span>Total Questions</span>
                    </div>
                    <div class="stat-item">
                        <strong><?php echo number_format($stats->easy); ?></strong>
                        <span>Easy</span>
                    </div>
                    <div class="stat-item">
                        <strong><?php echo number_format($stats->medium); ?></strong>
                        <span>Medium</span>
                    </div>
                    <div class="stat-item">
                        <strong><?php echo number_format($stats->hard); ?></strong>
                        <span>Hard</span>
                    </div>
                    <div class="stat-item">
                        <strong><?php echo number_format($stats->single_choice); ?></strong>
                        <span>Single Choice</span>
                    </div>
                    <div class="stat-item">
                        <strong><?php echo number_format($stats->multi_choice); ?></strong>
                        <span>Multiple Choice</span>
                    </div>
                    <div class="stat-item">
                        <strong><?php echo number_format($stats->true_false); ?></strong>
                        <span>True/False</span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php $this->render_question_styles(); ?>
        <?php $this->render_question_scripts(); ?>
        <?php
    }
    
    /**
     * Render question form - COMPLETE IMPLEMENTATION
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
        $title = $is_edit ? 'Edit Question' : 'New Question';
        
        ?>
        <div class="wrap">
            <h1><?php echo $title; ?></h1>
            
            <?php if (isset($_GET['saved'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Question <?php echo $is_edit ? 'updated' : 'created'; ?> successfully!</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" id="question-form">
                <?php wp_nonce_field('vefify_question_save', 'vefify_question_nonce'); ?>
                <input type="hidden" name="vefify_question_action" value="save_question">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="question_id" value="<?php echo $question->id; ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="campaign_id">Campaign</label></th>
                        <td>
                            <select id="campaign_id" name="campaign_id">
                                <option value="">Global (All Campaigns)</option>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <option value="<?php echo $campaign->id; ?>" 
                                            <?php selected($is_edit ? $question->campaign_id : '', $campaign->id); ?>>
                                        <?php echo esc_html($campaign->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Leave empty to make this question available for all campaigns</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="question_text">Question Text *</label></th>
                        <td>
                            <?php
                            $question_text = $is_edit ? $question->question_text : '';
                            wp_editor($question_text, 'question_text', array(
                                'textarea_name' => 'question_text',
                                'textarea_rows' => 6,
                                'teeny' => false,
                                'media_buttons' => true,
                                'tinymce' => array(
                                    'toolbar1' => 'bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,image,media,|,code,fullscreen',
                                    'toolbar2' => 'formatselect,|,forecolor,backcolor,|,alignleft,aligncenter,alignright,|,undo,redo'
                                )
                            ));
                            ?>
                            <p class="description">Enter the question text. You can include HTML, images, videos, and audio files.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Question Settings</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Question Settings</legend>
                                
                                <label>
                                    <strong>Question Type:</strong><br>
                                    <select name="question_type" id="question_type">
                                        <option value="single_select" <?php selected($is_edit ? $question->question_type : 'single_select', 'single_select'); ?>>
                                            Single Choice (one correct answer)
                                        </option>
                                        <option value="multiple_select" <?php selected($is_edit ? $question->question_type : '', 'multiple_select'); ?>>
                                            Multiple Choice (multiple correct answers)
                                        </option>
                                        <option value="true_false" <?php selected($is_edit ? $question->question_type : '', 'true_false'); ?>>
                                            True/False
                                        </option>
                                    </select>
                                </label><br><br>
                                
                                <label>
                                    <strong>Category:</strong><br>
                                    <select name="category">
                                        <option value="general" <?php selected($is_edit ? $question->category : 'general', 'general'); ?>>General</option>
                                        <option value="medication" <?php selected($is_edit ? $question->category : '', 'medication'); ?>>Medication</option>
                                        <option value="nutrition" <?php selected($is_edit ? $question->category : '', 'nutrition'); ?>>Nutrition</option>
                                        <option value="safety" <?php selected($is_edit ? $question->category : '', 'safety'); ?>>Safety</option>
                                        <option value="hygiene" <?php selected($is_edit ? $question->category : '', 'hygiene'); ?>>Hygiene</option>
                                        <option value="wellness" <?php selected($is_edit ? $question->category : '', 'wellness'); ?>>Wellness</option>
                                        <option value="pharmacy" <?php selected($is_edit ? $question->category : '', 'pharmacy'); ?>>Pharmacy</option>
                                    </select>
                                </label><br><br>
                                
                                <label>
                                    <strong>Difficulty:</strong><br>
                                    <select name="difficulty">
                                        <option value="easy" <?php selected($is_edit ? $question->difficulty : 'medium', 'easy'); ?>>Easy</option>
                                        <option value="medium" <?php selected($is_edit ? $question->difficulty : 'medium', 'medium'); ?>>Medium</option>
                                        <option value="hard" <?php selected($is_edit ? $question->difficulty : 'medium', 'hard'); ?>>Hard</option>
                                    </select>
                                </label><br><br>
                                
                                <label>
                                    <strong>Points:</strong><br>
                                    <input type="number" name="points" value="<?php echo $is_edit ? $question->points : 1; ?>" 
                                           min="1" max="10" class="small-text">
                                    <span class="description">Points awarded for correct answer</span>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="explanation">Explanation (Optional)</label></th>
                        <td>
                            <textarea id="explanation" name="explanation" rows="3" class="large-text"><?php echo $is_edit ? esc_textarea($question->explanation) : ''; ?></textarea>
                            <p class="description">Explain why certain answers are correct (shown after quiz completion)</p>
                        </td>
                    </tr>
                </table>
                
                <h3>Answer Options</h3>
                <div id="answer-options">
                    <?php
                    if ($options) {
                        foreach ($options as $index => $option) {
                            echo $this->render_option_row($index, $option->option_text, $option->is_correct, $option->explanation ?? '');
                        }
                    } else {
                        // Default options based on question type
                        $default_count = 4; // Will be adjusted by JavaScript
                        for ($i = 0; $i < $default_count; $i++) {
                            echo $this->render_option_row($i, '', false, '');
                        }
                    }
                    ?>
                </div>
                
                <p id="add-option-section">
                    <button type="button" id="add-option" class="button">Add Another Option</button>
                    <span class="description" id="options-help">Select the correct answer(s) for this question.</span>
                </p>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php echo $is_edit ? 'Update Question' : 'Save Question'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        
        <?php $this->render_question_form_styles(); ?>
        <?php $this->render_question_form_scripts($is_edit ? $question->question_type : 'single_select'); ?>
        <?php
    }
    
    /**
     * Render option row HTML
     */
    private function render_option_row($index, $text = '', $is_correct = false, $explanation = '') {
        ob_start();
        ?>
        <div class="option-row <?php echo $is_correct ? 'correct' : ''; ?>" data-index="<?php echo $index; ?>">
            <div class="option-header">
                <div class="option-number"><?php echo chr(65 + $index); // A, B, C, D ?></div>
                <div class="option-controls">
                    <label class="option-correct">
                        <input type="checkbox" name="options[<?php echo $index; ?>][is_correct]" 
                               value="1" class="option-correct-checkbox" <?php checked($is_correct); ?>>
                        <span class="checkmark"></span>
                        Correct Answer
                    </label>
                    <button type="button" class="remove-option" title="Remove this option">×</button>
                </div>
            </div>
            
            <div class="option-content">
                <label class="option-label">Answer Option:</label>
                <input type="text" name="options[<?php echo $index; ?>][text]" 
                       value="<?php echo esc_attr($text); ?>" 
                       placeholder="Enter answer option..." 
                       class="option-text widefat" required>
                
                <label class="option-label">Explanation (Optional):</label>
                <textarea name="options[<?php echo $index; ?>][explanation]" 
                          placeholder="Optional: Explain why this answer is correct/incorrect..."
                          rows="2" class="option-explanation widefat"><?php echo esc_textarea($explanation); ?></textarea>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!isset($_POST['vefify_question_action']) || !current_user_can('manage_options')) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['vefify_question_nonce'], 'vefify_question_save')) {
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
        }
    }
    
    /**
     * Handle save question - COMPLETE IMPLEMENTATION
     */
    private function handle_save_question() {
        $question_id = !empty($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $is_edit = $question_id > 0;
        
        // Validate required fields
        if (empty($_POST['question_text'])) {
            wp_die('Question text is required');
        }
        
        // Prepare question data
        $question_data = array(
            'campaign_id' => !empty($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null,
            'question_text' => wp_kses_post($_POST['question_text']), // Allow HTML
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
                    'option_text' => sanitize_textarea_field($option['text']), // Fixed: correct column name
                    'is_correct' => $is_correct ? 1 : 0,
                    'explanation' => sanitize_textarea_field($option['explanation'] ?? ''),
                    'order_index' => count($valid_options) + 1 // Fixed: start from 1, not 0
                );
                
                if ($is_correct) {
                    $has_correct = true;
                }
            }
        }
        
        // Validation rules - Return JSON response instead of wp_die
        $question_type = $question_data['question_type'];
        $min_options = ($question_type === 'true_false') ? 2 : 2;
        $max_options = ($question_type === 'true_false') ? 2 : 6;
        
        $errors = array();
        
        if (count($valid_options) < $min_options) {
            $errors[] = "You need at least {$min_options} answer options for this question type.";
        }
        
        if (count($valid_options) > $max_options) {
            $errors[] = "You can have at most {$max_options} answer options for this question type.";
        }
        
        if (!$has_correct) {
            $errors[] = 'You need to mark at least one correct answer.';
        }
        
        // Type-specific validation
        $correct_count = array_sum(array_column($valid_options, 'is_correct'));
        if ($question_type === 'single_select' && $correct_count > 1) {
            $errors[] = 'Single choice questions can only have one correct answer.';
        }
        
        if ($question_type === 'true_false' && $correct_count !== 1) {
            $errors[] = 'True/False questions must have exactly one correct answer.';
        }
        
        // If there are validation errors, return them as JSON
        if (!empty($errors)) {
            if (wp_doing_ajax()) {
                wp_send_json_error(array('errors' => $errors));
            } else {
                // For non-AJAX requests, show error and return to form
                add_action('admin_notices', function() use ($errors) {
                    echo '<div class="notice notice-error">';
                    echo '<p><strong>Please fix the following errors:</strong></p>';
                    echo '<ul>';
                    foreach ($errors as $error) {
                        echo '<li>' . esc_html($error) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                });
                $this->render_question_form($question_id);
                return;
            }
        }
        
        // Save to database
        try {
            // Start transaction for data integrity
            $this->wpdb->query('START TRANSACTION');
            
            if ($is_edit) {
                // Update existing question
                $update_data = array(
                    'question_text' => $question_data['question_text'],
                    'question_type' => $question_data['question_type'],
                    'category' => $question_data['category'],
                    'difficulty' => $question_data['difficulty'],
                    'points' => $question_data['points'],
                    'explanation' => $question_data['explanation'],
                    'updated_at' => current_time('mysql')
                );
                
                if ($question_data['campaign_id']) {
                    $update_data['campaign_id'] = $question_data['campaign_id'];
                }
                
                $result = $this->wpdb->update(
                    $this->table_prefix . 'questions',
                    $update_data,
                    array('id' => $question_id),
                    array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d'), // format for update data
                    array('%d') // format for where clause
                );
                
                if ($result === false) {
                    throw new Exception('Failed to update question: ' . $this->wpdb->last_error);
                }
                
                // Delete existing options
                $delete_result = $this->wpdb->delete(
                    $this->table_prefix . 'question_options', 
                    array('question_id' => $question_id),
                    array('%d')
                );
                
                $message = 'Question updated successfully!';
                $redirect_id = $question_id;
            } else {
                // Create new question
                $insert_data = array(
                    'campaign_id' => $question_data['campaign_id'],
                    'question_text' => $question_data['question_text'],
                    'question_type' => $question_data['question_type'],
                    'category' => $question_data['category'],
                    'difficulty' => $question_data['difficulty'],
                    'points' => $question_data['points'],
                    'explanation' => $question_data['explanation'],
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );
                
                $result = $this->wpdb->insert(
                    $this->table_prefix . 'questions',
                    $insert_data,
                    array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s')
                );
                
                if ($result === false) {
                    throw new Exception('Failed to create question: ' . $this->wpdb->last_error);
                }
                
                $question_id = $this->wpdb->insert_id;
                $message = 'Question created successfully!';
                $redirect_id = $question_id;
            }
            
            // Save options with correct field names
            foreach ($valid_options as $option) {
                $option_data = array(
                    'question_id' => $question_id,
                    'option_text' => $option['option_text'], // Correct field name
                    'is_correct' => $option['is_correct'],
                    'explanation' => $option['explanation'],
                    'order_index' => $option['order_index'],
                    'created_at' => current_time('mysql')
                );
                
                $option_result = $this->wpdb->insert(
                    $this->table_prefix . 'question_options',
                    $option_data,
                    array('%d', '%s', '%d', '%s', '%d', '%s')
                );
                
                if ($option_result === false) {
                    throw new Exception('Failed to save option: ' . $this->wpdb->last_error);
                }
            }
            
            // Commit transaction
            $this->wpdb->query('COMMIT');
            
            // Redirect with success message
            wp_redirect(admin_url('admin.php?page=vefify-questions&action=edit&id=' . $redirect_id . '&saved=1'));
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->wpdb->query('ROLLBACK');
            wp_die('Error saving question: ' . esc_html($e->getMessage()));
        }
    }
    
    /**
     * Handle delete question
     */
    private function handle_delete_question($question_id) {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $question_id = intval($question_id);
        if ($question_id <= 0) {
            wp_redirect(admin_url('admin.php?page=vefify-questions'));
            exit;
        }
        
        // Confirm deletion
        if (!isset($_GET['confirm'])) {
            $question = $this->model->get_question($question_id);
            if (!$question) {
                wp_redirect(admin_url('admin.php?page=vefify-questions'));
                exit;
            }
            
            ?>
            <div class="wrap">
                <h1>Delete Question</h1>
                <div class="notice notice-warning">
                    <p><strong>Are you sure you want to delete this question?</strong></p>
                    <p><?php echo esc_html(wp_trim_words($question->question_text, 20)); ?></p>
                    <p>This action cannot be undone.</p>
                </div>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=delete&id=' . $question_id . '&confirm=1'); ?>" 
                       class="button button-primary">Yes, Delete Question</a>
                    <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" 
                       class="button">Cancel</a>
                </p>
            </div>
            <?php
            return;
        }
        
        // Perform deletion
        $result = $this->model->delete_question($question_id);
        
        if (is_wp_error($result)) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>Error: ' . esc_html($result->get_error_message()) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Question deleted successfully.</p></div>';
            });
        }
        
        wp_redirect(admin_url('admin.php?page=vefify-questions'));
        exit;
    }
    
    /**
     * Render import page
     */
    private function render_import_page() {
        echo '<div class="wrap">';
        echo '<h1>Import Questions</h1>';
        echo '<p>CSV import functionality will be implemented here.</p>';
        echo '<p><a href="' . admin_url('admin.php?page=vefify-questions') . '" class="button">&larr; Back to Questions</a></p>';
        echo '</div>';
    }
    
    /**
     * Handle CSV import
     */
    private function handle_csv_import() {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info"><p>CSV import functionality will be implemented.</p></div>';
        });
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'vefify-questions') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_editor();
    }
    
    /**
     * AJAX: Load question preview
     */
    public function ajax_load_question_preview() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_preview')) {
            wp_send_json_error('Security check failed');
        }
        
        $question_id = intval($_POST['question_id']);
        $question = $this->model->get_question($question_id);
        
        if (!$question) {
            wp_send_json_error('Question not found');
        }
        
        ob_start();
        ?>
        <div class="preview-question">
            <strong>Question:</strong> <?php echo wp_kses_post($question->question_text); ?>
        </div>
        <div class="preview-meta">
            <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $question->question_type)); ?> |
            <strong>Category:</strong> <?php echo ucfirst($question->category); ?> |
            <strong>Difficulty:</strong> <?php echo ucfirst($question->difficulty); ?> |
            <strong>Points:</strong> <?php echo $question->points; ?>
        </div>
        <div class="preview-options">
            <?php foreach ($question->options as $option): ?>
                <div class="preview-option <?php echo $option->is_correct ? 'correct' : 'incorrect'; ?>">
                    <?php echo $option->is_correct ? '✓' : '✗'; ?> <?php echo esc_html($option->option_text); ?>
                    <?php if ($option->explanation): ?>
                        <br><small><em><?php echo esc_html($option->explanation); ?></em></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($question->explanation): ?>
            <div class="preview-explanation">
                <strong>Explanation:</strong> <?php echo esc_html($question->explanation); ?>
            </div>
        <?php endif; ?>
        <?php
        
        wp_send_json_success(ob_get_clean());
    }
    
    /**
     * AJAX: Duplicate question
     */
    public function ajax_duplicate_question() {
        wp_send_json_success('Duplicate functionality will be implemented');
    }
    
    /**
     * AJAX: Toggle question status
     */
    public function ajax_toggle_question_status() {
        wp_send_json_success('Toggle status functionality will be implemented');
    }
    
    /**
     * Render CSS styles for questions list
     */
    private function render_question_styles() {
        ?>
        <style>
        .questions-filters {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .questions-filters form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .category-badge, .difficulty-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            color: white;
            font-weight: bold;
        }
        
        .category-badge.category-general { background: #607d8b; }
        .category-badge.category-medication { background: #2196f3; }
        .category-badge.category-nutrition { background: #4caf50; }
        .category-badge.category-safety { background: #ff9800; }
        .category-badge.category-hygiene { background: #9c27b0; }
        .category-badge.category-wellness { background: #00bcd4; }
        .category-badge.category-pharmacy { background: #795548; }
        
        .difficulty-badge.difficulty-easy { background: #4caf50; }
        .difficulty-badge.difficulty-medium { background: #ff9800; }
        .difficulty-badge.difficulty-hard { background: #f44336; }
        
        .question-preview-content {
            padding: 15px;
            background: #f5f5f5;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .preview-question {
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .preview-meta {
            margin-bottom: 15px;
            color: #666;
            font-size: 12px;
        }
        
        .preview-options {
            margin-left: 20px;
        }
        
        .preview-option {
            margin: 5px 0;
            padding: 8px;
            border-radius: 3px;
        }
        
        .preview-option.correct {
            background: #d4edda;
            color: #155724;
            font-weight: bold;
        }
        
        .preview-option.incorrect {
            background: #f8d7da;
            color: #721c24;
        }
        
        .preview-explanation {
            margin-top: 15px;
            padding: 10px;
            background: #e7f3ff;
            border-left: 4px solid #2196f3;
            border-radius: 4px;
        }
        
        .questions-stats {
            margin-top: 30px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            border-left: 4px solid #4facfe;
        }
        
        .stat-item strong {
            display: block;
            font-size: 24px;
            color: #2271b1;
            margin-bottom: 5px;
        }
        
        .stat-item span {
            font-size: 12px;
            color: #666;
        }
        </style>
        <?php
    }
    
    /**
     * Render JavaScript for questions list - FIXED
     */
    private function render_question_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Preview toggle functionality - FIXED
            $('.toggle-preview').off('click').on('click', function(e) {
                e.preventDefault();
                
                const questionId = $(this).data('question-id');
                const previewRow = $('#preview-' + questionId);
                const button = $(this);
                
                console.log('Preview clicked for question:', questionId);
                
                if (previewRow.is(':visible')) {
                    previewRow.slideUp(300);
                    button.text('Preview');
                } else {
                    // Show loading
                    previewRow.find('.question-preview-content').html('<div class="preview-loading">Loading options...</div>');
                    previewRow.slideDown(300);
                    button.text('Loading...');
                    
                    // Load question options via AJAX
                    $.post(ajaxurl, {
                        action: 'vefify_load_question_preview',
                        question_id: questionId,
                        nonce: '<?php echo wp_create_nonce("vefify_preview"); ?>'
                    })
                    .done(function(response) {
                        console.log('Preview response:', response);
                        
                        if (response.success) {
                            previewRow.find('.question-preview-content').html(response.data);
                            button.text('Hide');
                        } else {
                            previewRow.find('.question-preview-content').html('<div class="preview-error">Error loading preview: ' + (response.data || 'Unknown error') + '</div>');
                            button.text('Preview');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Preview AJAX failed:', status, error);
                        previewRow.find('.question-preview-content').html('<div class="preview-error">Network error. Please try again.</div>');
                        button.text('Preview');
                    });
                }
            });
            
            console.log('Question list initialized with', $('.toggle-preview').length, 'preview buttons');
        });
        </script>
        
        <style>
        .preview-loading {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        
        .preview-error {
            text-align: center;
            padding: 20px;
            color: #dc3232;
            background: #fdf2f2;
            border: 1px solid #dc3232;
            border-radius: 4px;
        }
        </style>
        <?php
    }
    
    /**
     * Render CSS styles for question form
     */
    private function render_question_form_styles() {
        ?>
        <style>
        .option-row {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .option-row.correct {
            border-left: 4px solid #00a32a;
            background: #f0f8f0;
        }
        
        .option-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .option-number {
            background: #2271b1;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
        }
        
        .option-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .option-correct {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }
        
        .option-correct input[type="checkbox"] {
            display: none;
        }
        
        .checkmark {
            width: 20px;
            height: 20px;
            border: 2px solid #ddd;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .option-correct input[type="checkbox"]:checked + .checkmark {
            background: #00a32a;
            border-color: #00a32a;
        }
        
        .option-correct input[type="checkbox"]:checked + .checkmark:after {
            content: "✓";
            color: white;
            font-weight: bold;
        }
        
        .remove-option {
            background: #dc3232;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .remove-option:hover {
            background: #a00;
        }
        
        .option-content {
            display: grid;
            gap: 10px;
        }
        
        .option-label {
            font-weight: 600;
            color: #333;
            display: block;
            margin-bottom: 5px;
        }
        
        .option-text, .option-explanation {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px;
        }
        
        .option-text:focus, .option-explanation:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
            outline: none;
        }
        
        #add-option-section {
            text-align: center;
            padding: 20px;
            background: #f0f8ff;
            border: 2px dashed #2271b1;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        #add-option {
            background: #2271b1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        #add-option:hover {
            background: #1e5a8a;
        }
        
        #options-help {
            display: block;
            margin-top: 10px;
            color: #666;
            font-style: italic;
        }
        
        .form-table th {
            width: 200px;
            vertical-align: top;
            padding-top: 15px;
        }
        
        .form-table td {
            padding: 10px;
        }
        
        /* Question type specific styling */
        .question-type-true-false .option-row:nth-child(n+3) {
            display: none;
        }
        
        .question-type-true-false #add-option-section {
            display: none;
        }
        
        .question-type-true-false .option-text[readonly],
        .option-text.readonly-option {
            background-color: #e7f3ff !important;
            border-color: #2271b1 !important;
            font-weight: bold !important;
            color: #2271b1 !important;
            cursor: not-allowed;
        }
        
        .question-type-true-false .remove-option {
            display: none !important;
        }
        
        .question-type-true-false #add-option-section {
            display: none !important;
        }
        
        /* Error display improvements */
        .notice.notice-error {
            border-left-color: #dc3232;
            background: #fdf2f2;
            padding: 12px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .notice.notice-error ul {
            margin: 10px 0 0 20px;
        }
        
        .notice.notice-error li {
            margin-bottom: 5px;
            color: #721c24;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .option-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .option-controls {
                width: 100%;
                justify-content: space-between;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Render JavaScript for question form - COMPLETELY REWRITTEN
     */
    private function render_question_form_scripts($current_type = 'single_select') {
        ?>
        <script>
        // Global variables
        let optionCount = 0;
        
        jQuery(document).ready(function($) {
            // Initialize option count
            optionCount = $('#answer-options .option-row').length;
            console.log('Initial option count:', optionCount);
            
            // Initialize question type on page load with delay
            setTimeout(function() {
                updateQuestionType();
            }, 200);
            
            // Add new option
            $('#add-option').off('click').on('click', function() {
                const questionType = $('#question_type').val();
                const maxOptions = questionType === 'true_false' ? 2 : 6;
                const currentOptions = $('#answer-options .option-row:visible').length;
                
                console.log('Add option clicked, current:', currentOptions, 'max:', maxOptions, 'type:', questionType);
                
                if (currentOptions >= maxOptions) {
                    showError(`You can have at most ${maxOptions} options for ${questionType.replace('_', ' ')} questions.`);
                    return;
                }
                
                const newIndex = optionCount;
                const optionHtml = createOptionHtml(newIndex);
                $('#answer-options').append(optionHtml);
                optionCount++;
                updateOptionNumbers();
                
                console.log('Option added, new count:', optionCount);
            });
            
            // Remove option
            $(document).off('click', '.remove-option').on('click', '.remove-option', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const questionType = $('#question_type').val();
                const minOptions = questionType === 'true_false' ? 2 : 2;
                const currentOptions = $('#answer-options .option-row:visible').length;
                
                console.log('Remove option clicked, current:', currentOptions, 'min:', minOptions, 'type:', questionType);
                
                if (currentOptions <= minOptions) {
                    showError(`You need at least ${minOptions} options for ${questionType.replace('_', ' ')} questions.`);
                    return;
                }
                
                const $row = $(this).closest('.option-row');
                $row.fadeOut(300, function() {
                    $row.remove();
                    updateOptionNumbers();
                });
                
                console.log('Option removed');
            });
            
            // Handle correct answer checkbox
            $(document).off('change', '.option-correct-checkbox').on('change', '.option-correct-checkbox', function() {
                const row = $(this).closest('.option-row');
                const questionType = $('#question_type').val();
                
                if (questionType === 'single_select' && this.checked) {
                    // For single choice, uncheck all other options
                    $('.option-correct-checkbox').not(this).each(function() {
                        if (this.checked) {
                            $(this).prop('checked', false);
                            $(this).closest('.option-row').removeClass('correct');
                        }
                    });
                }
                
                // Update visual state
                if (this.checked) {
                    row.addClass('correct');
                } else {
                    row.removeClass('correct');
                }
            });
            
            // Question type change handler
            $('#question_type').off('change').on('change', function() {
                console.log('Question type changed to:', $(this).val());
                updateQuestionType();
            });
            
            // Form validation
            $('#question-form').off('submit').on('submit', function(e) {
                e.preventDefault();
                
                const errors = validateForm();
                if (errors.length > 0) {
                    let errorMessage = 'Please fix the following errors:\n\n';
                    errors.forEach(error => {
                        errorMessage += '• ' + error + '\n';
                    });
                    
                    showError(errors.join('<br>'));
                    return false;
                }
                
                // If validation passes, submit normally
                this.submit();
            });
            
            console.log('Question form initialized with', optionCount, 'options');
        });
        
        // Update question type function - FIXED FOR TRUE/FALSE
        function updateQuestionType() {
            const type = $('#question_type').val();
            const $container = $('#answer-options');
            const $addSection = $('#add-option-section');
            const $helpText = $('#options-help');
            
            console.log('Updating question type to:', type);
            console.log('Current options before:', $container.find('.option-row').length);
            
            // Update help text
            let helpText = '';
            switch(type) {
                case 'single_select':
                    helpText = 'Select ONE correct answer for this question.';
                    break;
                case 'multiple_select':
                    helpText = 'Select ALL correct answers for this question.';
                    break;
                case 'true_false':
                    helpText = 'Select either True or False as the correct answer.';
                    break;
            }
            $helpText.text(helpText);
            
            // Remove styling classes first
            $container.removeClass('question-type-true-false');
            
            if (type === 'true_false') {
                console.log('Setting up True/False question - REMOVING EXTRA OPTIONS');
                
                // FORCE REMOVE extra options beyond first 2
                const $allOptions = $container.find('.option-row');
                console.log('Found options to process:', $allOptions.length);
                
                $allOptions.each(function(index) {
                    if (index >= 2) {
                        console.log('Removing option at index:', index);
                        $(this).remove();
                    }
                });
                
                // Double-check and ensure we have exactly 2 options
                let currentCount = $container.find('.option-row').length;
                console.log('Options after removal:', currentCount);
                
                // If we have less than 2, add them
                while (currentCount < 2) {
                    console.log('Adding missing option at index:', currentCount);
                    const optionHtml = createOptionHtml(currentCount);
                    $container.append(optionHtml);
                    currentCount++;
                }
                
                // If we still have more than 2, force remove them
                if (currentCount > 2) {
                    console.log('Still too many options, force removing...');
                    $container.find('.option-row:gt(1)').remove();
                }
                
                // Set True/False text and make readonly
                const $firstOption = $container.find('.option-row').eq(0);
                const $secondOption = $container.find('.option-row').eq(1);
                
                if ($firstOption.length) {
                    $firstOption.find('.option-text').val('True').prop('readonly', true).addClass('readonly-option');
                    console.log('Set first option to True');
                }
                
                if ($secondOption.length) {
                    $secondOption.find('.option-text').val('False').prop('readonly', true).addClass('readonly-option');
                    console.log('Set second option to False');
                }
                
                // Hide controls
                $container.find('.remove-option').hide();
                $addSection.hide();
                
                // Add styling class
                $container.addClass('question-type-true-false');
                
                // Update global option count
                window.optionCount = 2;
                
                console.log('True/False setup complete - Final option count:', $container.find('.option-row').length);
                
            } else {
                console.log('Setting up choice question');
                
                // Remove readonly and show controls
                $container.find('.option-text').prop('readonly', false).removeClass('readonly-option');
                $container.find('.remove-option').show();
                $addSection.show();
                
                // Clear True/False text if switching from True/False
                const $options = $container.find('.option-row');
                if ($options.eq(0).find('.option-text').val() === 'True') {
                    $options.eq(0).find('.option-text').val('');
                }
                if ($options.eq(1).find('.option-text').val() === 'False') {
                    $options.eq(1).find('.option-text').val('');
                }
                
                // Ensure minimum 4 options for choice questions
                let currentCount = $container.find('.option-row').length;
                while (currentCount < 4) {
                    console.log('Adding option for choice question at index:', currentCount);
                    const optionHtml = createOptionHtml(currentCount);
                    $container.append(optionHtml);
                    currentCount++;
                }
                
                // Update global option count
                window.optionCount = currentCount;
                
                console.log('Choice question setup complete - Final option count:', currentCount);
            }
            
            updateOptionNumbers();
        }
        
        // Create option HTML
        function createOptionHtml(index) {
            return `
                <div class="option-row" data-index="${index}">
                    <div class="option-header">
                        <div class="option-number">${String.fromCharCode(65 + index)}</div>
                        <div class="option-controls">
                            <label class="option-correct">
                                <input type="checkbox" name="options[${index}][is_correct]" value="1" class="option-correct-checkbox">
                                <span class="checkmark"></span>
                                Correct Answer
                            </label>
                            <button type="button" class="remove-option" title="Remove this option">×</button>
                        </div>
                    </div>
                    <div class="option-content">
                        <label class="option-label">Answer Option:</label>
                        <input type="text" name="options[${index}][text]" placeholder="Enter answer option..." class="option-text widefat" required>
                        <label class="option-label">Explanation (Optional):</label>
                        <textarea name="options[${index}][explanation]" placeholder="Optional: Explain why this answer is correct/incorrect..." rows="2" class="option-explanation widefat"></textarea>
                    </div>
                </div>
            `;
        }
        
        // Update option numbers and names
        function updateOptionNumbers() {
            $('#answer-options .option-row').each(function(index) {
                $(this).attr('data-index', index);
                $(this).find('.option-number').text(String.fromCharCode(65 + index));
                
                // Update input names
                $(this).find('input, textarea').each(function() {
                    const name = $(this).attr('name');
                    if (name) {
                        const newName = name.replace(/options\[\d+\]/, `options[${index}]`);
                        $(this).attr('name', newName);
                    }
                });
            });
            
            console.log('Option numbers updated, total options:', $('#answer-options .option-row').length);
        }
        
        // Form validation - FIXED TO ALLOW SAVING
        function validateForm() {
            const errors = [];
            
            // Check question text from TinyMCE editor
            const questionText = (typeof tinyMCE !== 'undefined' && tinyMCE.get('question_text')) 
                ? tinyMCE.get('question_text').getContent() 
                : $('#question_text').val();
                
            if (!questionText || questionText.trim() === '') {
                errors.push('Question text is required.');
            }
            
            // Check options - FIXED LOGIC
            const $visibleOptions = $('.option-row:visible');
            const checkedOptions = $visibleOptions.find('.option-correct-checkbox:checked').length;
            const filledOptions = $visibleOptions.find('.option-text').filter(function() {
                return $(this).val().trim() !== '';
            }).length;
            
            const questionType = $('#question_type').val();
            const minOptions = questionType === 'true_false' ? 2 : 2;
            const maxOptions = questionType === 'true_false' ? 2 : 6;
            
            console.log('Validation - Type:', questionType, 'Filled:', filledOptions, 'Checked:', checkedOptions);
            
            if (filledOptions < minOptions) {
                errors.push(`You need at least ${minOptions} answer options for this question type.`);
            }
            
            if (filledOptions > maxOptions) {
                errors.push(`You can have at most ${maxOptions} answer options for this question type.`);
            }
            
            if (checkedOptions === 0) {
                errors.push('You need to mark at least one correct answer.');
            }
            
            if (questionType === 'single_select' && checkedOptions > 1) {
                errors.push('Single choice questions can only have one correct answer.');
            }
            
            if (questionType === 'true_false' && checkedOptions !== 1) {
                errors.push('True/False questions must have exactly one correct answer.');
            }
            
            console.log('Validation errors:', errors);
            return errors;
        }
        
        // Error display function
        function showError(message) {
            // Remove existing notices
            $('.vefify-notice').remove();
            
            const errorHtml = `
                <div class="vefify-notice notice notice-error is-dismissible" style="margin: 15px 0; padding: 12px; border-left: 4px solid #dc3232; background: #fdf2f2; border-radius: 4px;">
                    <p style="margin: 0; color: #721c24;"><strong>Error:</strong> ${message}</p>
                    <button type="button" class="notice-dismiss" onclick="$(this).parent().fadeOut()" style="position: absolute; top: 5px; right: 5px; background: none; border: none; color: #721c24; cursor: pointer; padding: 5px;">
                        <span style="font-size: 16px;">×</span>
                    </button>
                </div>
            `;
            
            // Add error at top of form
            $(errorHtml).prependTo('.wrap').hide().fadeIn();
            
            // Scroll to top
            $('html, body').animate({
                scrollTop: $('.wrap').offset().top - 50
            }, 500);
            
            // Auto-hide after 8 seconds
            setTimeout(function() {
                $('.vefify-notice').fadeOut();
            }, 8000);
        }
        
        // Success display function
        function showSuccess(message) {
            $('.vefify-notice').remove();
            
            const successHtml = `
                <div class="vefify-notice notice notice-success is-dismissible" style="margin: 15px 0; padding: 12px; border-left: 4px solid #00a32a; background: #f0f8f0; border-radius: 4px;">
                    <p style="margin: 0; color: #155724;"><strong>Success:</strong> ${message}</p>
                    <button type="button" class="notice-dismiss" onclick="$(this).parent().fadeOut()" style="position: absolute; top: 5px; right: 5px; background: none; border: none; color: #155724; cursor: pointer; padding: 5px;">
                        <span style="font-size: 16px;">×</span>
                    </button>
                </div>
            `;
            
            $(successHtml).prependTo('.wrap').hide().fadeIn();
            
            setTimeout(function() {
                $('.vefify-notice').fadeOut();
            }, 5000);
        }
        
        // Make functions globally available
        window.updateQuestionType = updateQuestionType;
        window.showError = showError;
        window.showSuccess = showSuccess;
        </script>
        <?php
    }
    
    /**
     * Get model instance
     */
    public function get_model() {
        return $this->model;
    }
}