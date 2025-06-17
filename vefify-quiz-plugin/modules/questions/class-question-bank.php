<?php
/**
 * Question Bank Management
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
        
        // Initialize model
        $this->model = new Vefify_Question_Model();
        
        // Hook into WordPress admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_vefify_load_question_preview', array($this, 'load_question_preview'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    
    /**
     * Admin page router
     */
    public function admin_page_router() {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'new':
            case 'edit':
                $this->display_question_form();
                break;
            case 'import':
                $this->display_import_page();
                break;
            case 'delete':
                $this->handle_delete_question();
                break;
            default:
                $this->display_questions_list();
                break;
        }
    }
    
    /**
     * Display questions list
     */
    private function display_questions_list() {
        // Get filter parameters
        $campaign_filter = $_GET['campaign_id'] ?? '';
        $category_filter = $_GET['category'] ?? '';
        $difficulty_filter = $_GET['difficulty'] ?? '';
        $search = $_GET['search'] ?? '';
        
        // Build query args
        $args = array(
            'per_page' => 20,
            'page' => $_GET['paged'] ?? 1,
            'include_options' => false
        );
        
        if ($campaign_filter) {
            $args['campaign_id'] = $campaign_filter;
        }
        
        if ($category_filter) {
            $args['category'] = $category_filter;
        }
        
        if ($difficulty_filter) {
            $args['difficulty'] = $difficulty_filter;
        }
        
        if ($search) {
            $args['search'] = $search;
        }
        
        // Get questions
        $result = $this->model->get_questions($args);
        $questions = $result['questions'];
        $total = $result['total'];
        $total_pages = $result['pages'];
        
        // Get filter options
        $campaigns = $this->wpdb->get_results("SELECT id, name FROM {$this->table_prefix}campaigns ORDER BY name");
        $categories = $this->model->get_categories();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">❓ Question Bank</h1>
            <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=new'); ?>" class="page-title-action">Add New Question</a>
            <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=import'); ?>" class="page-title-action">Import Questions</a>
            <hr class="wp-header-end">
            
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
                    
                    <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Search questions...">
                    <button type="submit" class="button">Filter</button>
                    
                    <?php if ($campaign_filter || $category_filter || $difficulty_filter || $search): ?>
                        <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Questions Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="40%">Question</th>
                        <th>Campaign</th>
                        <th>Category</th>
                        <th>Difficulty</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($questions)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                <h3>No Questions Found</h3>
                                <p>No questions match your current filters.</p>
                                <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=new'); ?>" class="button button-primary">Create Your First Question</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($questions as $question): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html(wp_trim_words($question->question_text, 12)); ?></strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=edit&id=' . $question->id); ?>">Edit</a> |
                                    </span>
                                    <span class="view">
                                        <a href="#" class="preview-question" data-question-id="<?php echo $question->id; ?>">Preview</a> |
                                    </span>
                                    <span class="delete">
                                        <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=delete&id=' . $question->id); ?>" 
                                           onclick="return confirm('Are you sure you want to delete this question?')">Delete</a>
                                    </span>
                                </div>
                            </td>
                            <td><?php echo esc_html($question->campaign_name ?: 'Global'); ?></td>
                            <td>
                                <?php if ($question->category): ?>
                                    <span class="category-badge category-<?php echo esc_attr($question->category); ?>">
                                        <?php echo esc_html(ucfirst($question->category)); ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
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
                                <button class="button button-small toggle-preview" data-question-id="<?php echo $question->id; ?>">
                                    Preview
                                </button>
                            </td>
                        </tr>
                        <tr class="question-preview" id="preview-<?php echo $question->id; ?>" style="display: none;">
                            <td colspan="6">
                                <div class="question-preview-content">
                                    <div class="preview-loading">Loading...</div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'current' => $args['page'],
                            'total' => $total_pages,
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="questions-stats">
                <h3>📊 Question Bank Statistics</h3>
                <?php
                $stats = $this->model->get_question_stats();
                ?>
                <div class="stats-grid">
                    <div class="stat-item">
                        <strong><?php echo number_format($stats['total']); ?></strong>
                        <span>Total Questions</span>
                    </div>
                    <div class="stat-item">
                        <strong><?php echo number_format($stats['active']); ?></strong>
                        <span>Active</span>
                    </div>
                    <div class="stat-item">
                        <strong><?php echo number_format($stats['easy']); ?></strong>
                        <span>Easy</span>
                    </div>
                    <div class="stat-item">
                        <strong><?php echo number_format($stats['medium']); ?></strong>
                        <span>Medium</span>
                    </div>
                    <div class="stat-item">
                        <strong><?php echo number_format($stats['hard']); ?></strong>
                        <span>Hard</span>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .questions-filters {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
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
     * Display question form (new/edit)
     */
    private function display_question_form() {
        $question_id = $_GET['id'] ?? 0;
        $is_edit = !empty($question_id);
        $question = null;
        
        if ($is_edit) {
            $question = $this->model->get_question($question_id);
            if (!$question) {
                wp_die('Question not found');
            }
        }
        
        // Get campaigns for dropdown
        $campaigns = $this->wpdb->get_results("SELECT id, name FROM {$this->table_prefix}campaigns ORDER BY name");
        
        ?>
        <div class="wrap">
            <h1><?php echo $is_edit ? 'Edit Question' : 'Add New Question'; ?></h1>
            
            <form method="post" action="" id="question-form">
                <?php wp_nonce_field('vefify_question_save'); ?>
                <input type="hidden" name="action" value="save_question">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="question_id" value="<?php echo $question->id; ?>">
                <?php endif; ?>
                
                <div class="question-form-container">
                    <div class="form-section">
                        <h3>Question Details</h3>
                        
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
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="question_text">Question Text *</label></th>
                                <td>
                                    <textarea id="question_text" name="question_text" rows="3" class="large-text" required><?php echo $is_edit ? esc_textarea($question->question_text) : ''; ?></textarea>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Settings</th>
                                <td>
                                    <fieldset>
                                        <label>
                                            Type: 
                                            <select name="question_type" id="question_type">
                                                <option value="multiple_choice" <?php selected($is_edit ? $question->question_type : 'multiple_choice', 'multiple_choice'); ?>>
                                                    Single Choice
                                                </option>
                                                <option value="multiple_select" <?php selected($is_edit ? $question->question_type : '', 'multiple_select'); ?>>
                                                    Multiple Choice
                                                </option>
                                                <option value="true_false" <?php selected($is_edit ? $question->question_type : '', 'true_false'); ?>>
                                                    True/False
                                                </option>
                                            </select>
                                        </label><br><br>
                                        
                                        <label>
                                            Category: 
                                            <select name="category">
                                                <option value="">Select Category</option>
                                                <option value="medication" <?php selected($is_edit ? $question->category : '', 'medication'); ?>>Medication</option>
                                                <option value="nutrition" <?php selected($is_edit ? $question->category : '', 'nutrition'); ?>>Nutrition</option>
                                                <option value="safety" <?php selected($is_edit ? $question->category : '', 'safety'); ?>>Safety</option>
                                                <option value="hygiene" <?php selected($is_edit ? $question->category : '', 'hygiene'); ?>>Hygiene</option>
                                                <option value="wellness" <?php selected($is_edit ? $question->category : '', 'wellness'); ?>>Wellness</option>
                                                <option value="pharmacy" <?php selected($is_edit ? $question->category : '', 'pharmacy'); ?>>Pharmacy</option>
                                            </select>
                                        </label><br><br>
                                        
                                        <label>
                                            Difficulty: 
                                            <select name="difficulty">
                                                <option value="easy" <?php selected($is_edit ? $question->difficulty : 'medium', 'easy'); ?>>Easy</option>
                                                <option value="medium" <?php selected($is_edit ? $question->difficulty : 'medium', 'medium'); ?>>Medium</option>
                                                <option value="hard" <?php selected($is_edit ? $question->difficulty : 'medium', 'hard'); ?>>Hard</option>
                                            </select>
                                        </label><br><br>
                                        
                                        <label>
                                            Points: 
                                            <input type="number" name="points" value="<?php echo $is_edit ? $question->points : 1; ?>" 
                                                   min="1" max="10" class="small-text">
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Explanation (Optional)</th>
                                <td>
                                    <textarea name="explanation" rows="2" class="large-text"><?php echo $is_edit ? esc_textarea($question->explanation) : ''; ?></textarea>
                                    <p class="description">Explain why certain answers are correct</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="form-section">
                        <h3>Answer Options</h3>
                        <div id="answer-options">
                            <?php
                            if ($is_edit && $question->options) {
                                foreach ($question->options as $index => $option) {
                                    $this->render_option_row($index, $option->option_text, $option->is_correct, $option->explanation);
                                }
                            } else {
                                // Default 4 options for new questions
                                for ($i = 0; $i < 4; $i++) {
                                    $this->render_option_row($i, '', false, '');
                                }
                            }
                            ?>
                        </div>
                        
                        <p>
                            <button type="button" id="add-option" class="button">Add Another Option</button>
                            <span class="description">You need at least 2 options, and at least 1 must be marked as correct.</span>
                        </p>
                    </div>
                </div>
                
                <?php submit_button($is_edit ? 'Update Question' : 'Save Question'); ?>
            </form>
        </div>
        
        <style>
        .question-form-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            max-width: 1000px;
        }
        
        .form-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .form-section h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .option-row {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
            position: relative;
        }
        
        .option-row.correct {
            border-left: 4px solid #00a32a;
            background: #f0f8f0;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let optionCount = $('#answer-options .option-row').length;
            
            // Add new option
            $('#add-option').click(function() {
                // Implementation for adding options
                optionCount++;
            });
            
            // Handle correct answer selection
            $(document).on('change', '.option-correct-checkbox', function() {
                const questionType = $('#question_type').val();
                if (questionType === 'multiple_choice' && this.checked) {
                    $('.option-correct-checkbox').not(this).prop('checked', false);
                    $('.option-row').removeClass('correct');
                }
                $(this).closest('.option-row').toggleClass('correct', this.checked);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render option row HTML
     */
    private function render_option_row($index, $text = '', $is_correct = false, $explanation = '') {
        ?>
        <div class="option-row <?php echo $is_correct ? 'correct' : ''; ?>">
            <div class="option-header">
                <span class="option-number"><?php echo $index + 1; ?></span>
                <label class="option-correct">
                    <input type="checkbox" name="options[<?php echo $index; ?>][is_correct]" 
                           value="1" class="option-correct-checkbox" <?php checked($is_correct); ?>>
                    Correct Answer
                </label>
            </div>
            
            <input type="text" name="options[<?php echo $index; ?>][text]" 
                   value="<?php echo esc_attr($text); ?>" 
                   placeholder="Enter answer option..." 
                   class="option-text large-text" required>
            
            <textarea name="options[<?php echo $index; ?>][explanation]" 
                      placeholder="Optional: Explain why this answer is correct/incorrect..."
                      rows="2" class="option-explanation large-text"><?php echo esc_textarea($explanation); ?></textarea>
        </div>
        <?php
    }
    
    /**
     * Display import page
     */
    private function display_import_page() {
        ?>
        <div class="wrap">
            <h1>Import Questions</h1>
            <p>Upload a CSV file containing questions and answers.</p>
            
            <div class="import-instructions">
                <h3>CSV Format</h3>
                <p>Your CSV file should have these columns:</p>
                <ul>
                    <li><strong>question_text</strong> - The question text</li>
                    <li><strong>option_1</strong> - First answer option</li>
                    <li><strong>option_2</strong> - Second answer option</li>
                    <li><strong>option_3</strong> - Third answer option (optional)</li>
                    <li><strong>option_4</strong> - Fourth answer option (optional)</li>
                    <li><strong>correct_answers</strong> - Correct option numbers (1,2 for multiple correct)</li>
                    <li><strong>category</strong> - Question category (optional)</li>
                    <li><strong>difficulty</strong> - easy, medium, or hard (optional)</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (isset($_POST['action']) && $_POST['action'] === 'save_question') {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_question_save')) {
                wp_die('Security check failed');
            }
            
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            
            // Process form submission
            // Implementation would go here
        }
    }
    
    /**
     * Handle question deletion
     */
    private function handle_delete_question() {
        $question_id = $_GET['id'] ?? 0;
        
        if (!$question_id || !current_user_can('manage_options')) {
            wp_redirect(admin_url('admin.php?page=vefify-questions'));
            exit;
        }
        
        $result = $this->model->delete_question($question_id);
        
        if (is_wp_error($result)) {
            $message = 'Error: ' . $result->get_error_message();
            $type = 'error';
        } else {
            $message = 'Question deleted successfully.';
            $type = 'success';
        }
        
        add_settings_error('vefify_questions', 'question_deleted', $message, $type);
        wp_redirect(admin_url('admin.php?page=vefify-questions'));
        exit;
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'vefify-questions') === false) {
            return;
        }
        
        wp_enqueue_script('vefify-question-bank', 
            plugin_dir_url(__FILE__) . 'assets/question-bank.js', 
            array('jquery'), 
            '1.0.0', 
            true
        );
        
        wp_localize_script('vefify-question-bank', 'vefifyQuestionBank', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_question_bank'),
        ));
    }
    
    /**
     * AJAX handler for question preview
     */
    public function load_question_preview() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_question_bank')) {
            wp_die('Security check failed');
        }
        
        $question_id = intval($_POST['question_id']);
        $question = $this->model->get_question($question_id);
        
        if (!$question) {
            wp_send_json_error('Question not found');
        }
        
        ob_start();
        ?>
        <div class="preview-question"><?php echo esc_html($question->question_text); ?></div>
        <div class="preview-options">
            <?php foreach ($question->options as $option): ?>
                <div class="preview-option <?php echo $option->is_correct ? 'correct' : 'incorrect'; ?>">
                    <?php echo $option->is_correct ? '✓' : '✗'; ?> <?php echo esc_html($option->option_text); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        wp_send_json_success(ob_get_clean());
    }
}