<?php
/**
 * COMPLETE Question Bank Management with Missing Methods 
 * File: modules/questions/class-question-bank.php
 * 
 * Fixed admin interface with all required methods for compatibility
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Bank {
    
    private $model;
    private $database;
    private $wpdb;
    private $table_prefix;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Use centralized database class
        $this->database = new Vefify_Quiz_Database();
        $this->model = new Vefify_Question_Model();
        $this->table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Hook into WordPress admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_vefify_question_preview', array($this, 'ajax_question_preview'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    
    /**
     * ADDED: Missing admin_page_router method that was being called
     */
    public function admin_page_router() {
        $this->display_questions_page();
    }
    
    /**
     * ADDED: Alternative method name for compatibility
     */
    public function display_admin_page() {
        $this->display_questions_page();
    }
    
    /**
     * Enqueue admin scripts with proper localization
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on question pages
        if (strpos($hook, 'vefify-questions') === false) {
            return;
        }
        
        wp_enqueue_script(
            'vefify-question-admin',
            plugin_dir_url(__FILE__) . 'assets/question-admin.js',
            array('jquery'),
            VEFIFY_QUIZ_VERSION,
            true
        );
        
        wp_enqueue_style(
            'vefify-question-admin',
            plugin_dir_url(__FILE__) . 'assets/question-admin.css',
            array(),
            VEFIFY_QUIZ_VERSION
        );
        
        // Localize script with proper data
        wp_localize_script('vefify-question-admin', 'vefifyQuestionAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_question_admin'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this question?', 'vefify-quiz'),
                'addOption' => __('Add Option', 'vefify-quiz'),
                'removeOption' => __('Remove Option', 'vefify-quiz'),
                'minOptions' => __('You need at least 2 options', 'vefify-quiz'),
                'selectCorrect' => __('Please select at least one correct answer', 'vefify-quiz'),
                'loading' => __('Loading...', 'vefify-quiz')
            )
        ));
    }
    
    /**
     * Main question management interface
     */
    public function display_questions_page() {
        $action = $_GET['action'] ?? 'list';
        $question_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        echo '<div class="wrap vefify-questions-wrap">';
        
        // Show any messages
        $this->display_admin_messages();
        
        switch ($action) {
            case 'new':
                $this->display_question_form();
                break;
            case 'edit':
                $this->display_question_form($question_id);
                break;
            case 'delete':
                $this->handle_delete_question($question_id);
                break;
            case 'import':
                $this->display_import_form();
                break;
            default:
                $this->display_questions_list();
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Display admin messages from URL parameters
     */
    private function display_admin_messages() {
        if (isset($_GET['message'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['message'])) . '</p></div>';
        }
        
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['error'])) . '</p></div>';
        }
    }
    
    /**
     * Display questions list with proper database connection
     */
    private function display_questions_list() {
        // Get filter parameters
        $campaign_filter = $_GET['campaign_id'] ?? '';
        $category_filter = $_GET['category'] ?? '';
        $difficulty_filter = $_GET['difficulty'] ?? '';
        $search = $_GET['search'] ?? '';
        
        // Get questions using centralized database
        $questions_table = $this->database->get_table_name('questions');
        $campaigns_table = $this->database->get_table_name('campaigns');
        $options_table = $this->database->get_table_name('question_options');
        
        if (!$questions_table || !$campaigns_table || !$options_table) {
            echo '<div class="notice notice-error"><p>Database tables not found. Please check your installation.</p></div>';
            echo '<p><a href="' . admin_url('admin.php?page=vefify-quiz') . '" class="button">Return to Dashboard</a></p>';
            return;
        }
        
        // Build WHERE clause
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
        
        if ($search) {
            $where_conditions[] = 'q.question_text LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like($search) . '%';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get questions
        $query = "
            SELECT q.*, c.name as campaign_name,
                   COUNT(qo.id) as option_count,
                   SUM(CASE WHEN qo.is_correct = 1 THEN 1 ELSE 0 END) as correct_count
            FROM {$questions_table} q
            LEFT JOIN {$campaigns_table} c ON q.campaign_id = c.id  
            LEFT JOIN {$options_table} qo ON q.id = qo.question_id
            WHERE {$where_clause}
            GROUP BY q.id
            ORDER BY q.created_at DESC
            LIMIT 50
        ";
        
        $questions = !empty($params) ? 
            $this->wpdb->get_results($this->wpdb->prepare($query, $params)) :
            $this->wpdb->get_results($query);
        
        // Get filter options
        $campaigns = $this->wpdb->get_results("SELECT id, name FROM {$campaigns_table} WHERE is_active = 1 ORDER BY name");
        $categories = $this->wpdb->get_col("SELECT DISTINCT category FROM {$questions_table} WHERE category IS NOT NULL AND category != '' ORDER BY category");
        
        ?>
        <h1 class="wp-heading-inline">Question Bank</h1>
        <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=new'); ?>" class="page-title-action">Add New Question</a>
        <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=import'); ?>" class="page-title-action">Import Questions</a>
        
        <!-- Filters -->
        <div class="question-filters">
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
                    <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Questions Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="50%">Question</th>
                    <th>Campaign</th>
                    <th>Category</th>
                    <th>Difficulty</th>
                    <th>Options</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($questions)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <div class="no-questions-found">
                                <p><strong>No questions found.</strong></p>
                                <p>Start building your question bank to power your quizzes.</p>
                                <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=new'); ?>" class="button button-primary">Add Your First Question</a>
                                <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=import'); ?>" class="button">Import from CSV</a>
                            </div>
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
                                    <span class="delete">
                                        <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=delete&id=' . $question->id); ?>" 
                                           onclick="return confirm('Are you sure you want to delete this question?')" class="delete-link">Delete</a>
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
                                <?php echo $question->option_count; ?> options<br>
                                <small><?php echo $question->correct_count; ?> correct</small>
                            </td>
                            <td>
                                <button class="button button-small question-preview" data-question-id="<?php echo $question->id; ?>">
                                    Preview
                                </button>
                            </td>
                        </tr>
                        <tr class="question-preview-row" id="preview-<?php echo $question->id; ?>" style="display: none;">
                            <td colspan="6">
                                <div class="question-preview-content">Loading...</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php $this->display_question_statistics(); ?>
        
        <style>
        .question-filters {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .question-filters form {
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
        .category-badge.category-general { background: #666; }
        
        .difficulty-badge.difficulty-easy { background: #4caf50; }
        .difficulty-badge.difficulty-medium { background: #ff9800; }
        .difficulty-badge.difficulty-hard { background: #f44336; }
        
        .question-preview-content {
            padding: 15px;
            background: #f5f5f5;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .delete-link {
            color: #a00 !important;
        }
        
        .no-questions-found {
            text-align: center;
            color: #666;
        }
        
        .no-questions-found p {
            margin: 10px 0;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.question-preview').click(function() {
                const questionId = $(this).data('question-id');
                const previewRow = $('#preview-' + questionId);
                const button = $(this);
                
                if (previewRow.is(':visible')) {
                    previewRow.hide();
                    button.text('Preview');
                } else {
                    button.text('Loading...');
                    
                    $.post(ajaxurl, {
                        action: 'vefify_question_preview',
                        question_id: questionId,
                        nonce: vefifyQuestionAdmin.nonce
                    }, function(response) {
                        if (response.success) {
                            previewRow.find('.question-preview-content').html(response.data);
                            previewRow.show();
                            button.text('Hide Preview');
                        } else {
                            previewRow.find('.question-preview-content').html('<p style="color: #dc3232;">Error loading preview</p>');
                            previewRow.show();
                            button.text('Error');
                        }
                    }).fail(function() {
                        previewRow.find('.question-preview-content').html('<p style="color: #dc3232;">Network error loading preview</p>');
                        previewRow.show();
                        button.text('Error');
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Question form without duplicate menus
     */
    private function display_question_form($question_id = 0) {
        $is_edit = $question_id > 0;
        $question = null;
        $options = array();
        
        if ($is_edit) {
            $question = $this->model->get_question($question_id);
            if (is_wp_error($question) || !$question) {
                echo '<div class="notice notice-error"><p>Question not found.</p></div>';
                echo '<p><a href="' . admin_url('admin.php?page=vefify-questions') . '" class="button">Back to Questions</a></p>';
                return;
            }
            
            // Convert options object to array format expected by form
            if (!empty($question->options)) {
                foreach ($question->options as $index => $option) {
                    $options[] = array(
                        'option_text' => $option->option_text,
                        'is_correct' => $option->is_correct,
                        'explanation' => $option->explanation ?: ''
                    );
                }
            }
        }
        
        // Get campaigns for dropdown
        $campaigns_table = $this->database->get_table_name('campaigns');
        $campaigns = $this->wpdb->get_results("SELECT id, name FROM {$campaigns_table} WHERE is_active = 1 ORDER BY name");
        
        ?>
        <h1><?php echo $is_edit ? 'Edit Question' : 'Add New Question'; ?></h1>
        
        <form method="post" action="" id="question-form" class="question-form">
            <?php wp_nonce_field('vefify_question_save', '_question_nonce'); ?>
            <input type="hidden" name="action" value="save_question">
            <?php if ($is_edit): ?>
                <input type="hidden" name="question_id" value="<?php echo $question->id; ?>">
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="campaign_id">Campaign</label>
                    </th>
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
                        <p class="description">Select a campaign or leave empty for global questions.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="question_text">Question Text *</label>
                    </th>
                    <td>
                        <textarea id="question_text" name="question_text" rows="3" class="large-text" required><?php echo $is_edit ? esc_textarea($question->question_text) : ''; ?></textarea>
                        <p class="description">Enter the question that participants will see.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Question Settings</th>
                    <td>
                        <fieldset>
                            <p>
                                <label>Type:</label>
                                <select name="question_type" id="question_type">
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
                            </p>
                            
                            <p>
                                <label>Category:</label>
                                <select name="category">
                                    <option value="">Select Category</option>
                                    <option value="medication" <?php selected($is_edit ? $question->category : '', 'medication'); ?>>Medication</option>
                                    <option value="nutrition" <?php selected($is_edit ? $question->category : '', 'nutrition'); ?>>Nutrition</option>
                                    <option value="safety" <?php selected($is_edit ? $question->category : '', 'safety'); ?>>Safety</option>
                                    <option value="hygiene" <?php selected($is_edit ? $question->category : '', 'hygiene'); ?>>Hygiene</option>
                                    <option value="wellness" <?php selected($is_edit ? $question->category : '', 'wellness'); ?>>Wellness</option>
                                    <option value="pharmacy" <?php selected($is_edit ? $question->category : '', 'pharmacy'); ?>>Pharmacy</option>
                                </select>
                            </p>
                            
                            <p>
                                <label>Difficulty:</label>
                                <select name="difficulty">
                                    <option value="easy" <?php selected($is_edit ? $question->difficulty : 'medium', 'easy'); ?>>Easy</option>
                                    <option value="medium" <?php selected($is_edit ? $question->difficulty : 'medium', 'medium'); ?>>Medium</option>
                                    <option value="hard" <?php selected($is_edit ? $question->difficulty : 'medium', 'hard'); ?>>Hard</option>
                                </select>
                            </p>
                            
                            <p>
                                <label>Points:</label>
                                <input type="number" name="points" value="<?php echo $is_edit ? $question->points : 1; ?>" 
                                       min="1" max="10" class="small-text">
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="explanation">Explanation (Optional)</label>
                    </th>
                    <td>
                        <textarea id="explanation" name="explanation" rows="2" class="large-text"><?php echo $is_edit ? esc_textarea($question->explanation) : ''; ?></textarea>
                        <p class="description">Explain why certain answers are correct (shown after completion).</p>
                    </td>
                </tr>
            </table>
            
            <h3>Answer Options</h3>
            <div id="answer-options">
                <?php
                if ($options) {
                    foreach ($options as $index => $option) {
                        $this->render_option_row($index, $option['option_text'], $option['is_correct'], $option['explanation']);
                    }
                } else {
                    // Default options for new questions
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
            
            <p class="submit">
                <input type="submit" name="submit" class="button button-primary" value="<?php echo $is_edit ? 'Update Question' : 'Save Question'; ?>">
                <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Cancel</a>
            </p>
        </form>
        
        <?php $this->add_question_form_scripts(); ?>
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
                <button type="button" class="remove-option button-link" title="Remove this option">×</button>
            </div>
            
            <input type="text" name="options[<?php echo $index; ?>][text]" 
                   value="<?php echo esc_attr($text); ?>" 
                   placeholder="Enter answer option..." 
                   class="option-text regular-text" required>
            
            <textarea name="options[<?php echo $index; ?>][explanation]" 
                      placeholder="Optional: Explain why this answer is correct/incorrect..."
                      rows="2" class="option-explanation regular-text"><?php echo esc_textarea($explanation); ?></textarea>
        </div>
        <?php
    }
    
    /**
     * Add JavaScript for question form
     */
    private function add_question_form_scripts() {
        ?>
        <style>
        .question-form {
            max-width: 800px;
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
            margin-left: auto;
            margin-right: 10px;
        }
        
        .remove-option {
            color: #dc3232;
            font-size: 18px;
            font-weight: bold;
            text-decoration: none;
            border: none;
            background: none;
            cursor: pointer;
        }
        
        .option-text, .option-explanation {
            width: 100%;
            margin-bottom: 5px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let optionCount = $('#answer-options .option-row').length;
            
            // Add new option
            $('#add-option').click(function() {
                const optionHtml = `
                    <div class="option-row">
                        <div class="option-header">
                            <span class="option-number">${optionCount + 1}</span>
                            <label class="option-correct">
                                <input type="checkbox" name="options[${optionCount}][is_correct]" value="1" class="option-correct-checkbox">
                                Correct Answer
                            </label>
                            <button type="button" class="remove-option button-link" title="Remove this option">×</button>
                        </div>
                        <input type="text" name="options[${optionCount}][text]" placeholder="Enter answer option..." class="option-text regular-text" required>
                        <textarea name="options[${optionCount}][explanation]" placeholder="Optional: Explain why this answer is correct/incorrect..." rows="2" class="option-explanation regular-text"></textarea>
                    </div>
                `;
                $('#answer-options').append(optionHtml);
                optionCount++;
                updateOptionNumbers();
            });
            
            // Remove option
            $(document).on('click', '.remove-option', function() {
                if ($('#answer-options .option-row').length > 2) {
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
                
                // Update visual state
                row.toggleClass('correct', this.checked);
            });
            
            // Question type change
            $('#question_type').change(function() {
                const type = $(this).val();
                
                if (type === 'true_false') {
                    // Limit to 2 options for true/false
                    $('#answer-options .option-row:gt(1)').remove();
                    $('#add-option').hide();
                    
                    // Set True/False text
                    $('#answer-options .option-text').eq(0).val('True');
                    $('#answer-options .option-text').eq(1).val('False');
                } else {
                    $('#add-option').show();
                }
                
                updateOptionNumbers();
            });
            
            // Update option numbers
            function updateOptionNumbers() {
                $('#answer-options .option-row').each(function(index) {
                    $(this).find('.option-number').text(index + 1);
                });
            }
            
            // Form validation
            $('#question-form').submit(function(e) {
                const checkedOptions = $('.option-correct-checkbox:checked').length;
                const filledOptions = $('.option-text').filter(function() {
                    return $(this).val().trim() !== '';
                }).length;
                
                if (filledOptions < 2) {
                    alert('You need at least 2 answer options.');
                    e.preventDefault();
                    return false;
                }
                
                if (checkedOptions === 0) {
                    alert('You need to mark at least one correct answer.');
                    e.preventDefault();
                    return false;
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle form submissions with proper database connection
     */
    public function handle_form_submissions() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'save_question') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['_question_nonce'], 'vefify_question_save')) {
            wp_die('Security check failed');
        }
        
        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $is_edit = $question_id > 0;
        
        // Prepare question data
        $question_data = array(
            'campaign_id' => $_POST['campaign_id'] ? intval($_POST['campaign_id']) : null,
            'question_text' => sanitize_textarea_field($_POST['question_text']),
            'question_type' => sanitize_text_field($_POST['question_type']),
            'category' => sanitize_text_field($_POST['category']),
            'difficulty' => sanitize_text_field($_POST['difficulty']),
            'points' => intval($_POST['points']),
            'explanation' => sanitize_textarea_field($_POST['explanation'])
        );
        
        // Prepare options data
        $options = $_POST['options'] ?? array();
        $valid_options = array();
        $has_correct = false;
        
        foreach ($options as $index => $option) {
            if (!empty($option['text'])) {
                $valid_options[] = array(
                    'option_text' => sanitize_textarea_field($option['text']),
                    'is_correct' => !empty($option['is_correct']),
                    'explanation' => sanitize_textarea_field($option['explanation'] ?? ''),
                    'order_index' => count($valid_options)
                );
                
                if (!empty($option['is_correct'])) {
                    $has_correct = true;
                }
            }
        }
        
        // Validation
        if (count($valid_options) < 2) {
            wp_die('You need at least 2 answer options.');
        }
        
        if (!$has_correct) {
            wp_die('You need at least one correct answer.');
        }
        
        // Add options to question data
        $question_data['options'] = $valid_options;
        
        // Save using model
        if ($is_edit) {
            $result = $this->model->update_question($question_id, $question_data);
            $message = 'Question updated successfully!';
        } else {
            $result = $this->model->create_question($question_data);
            $message = 'Question created successfully!';
        }
        
        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=vefify-questions&error=' . urlencode($result->get_error_message())));
        } else {
            wp_redirect(admin_url('admin.php?page=vefify-questions&message=' . urlencode($message)));
        }
        exit;
    }
    
    /**
     * AJAX handler for question preview
     */
    public function ajax_question_preview() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_question_admin')) {
            wp_send_json_error('Security check failed');
        }
        
        $question_id = intval($_POST['question_id']);
        $question = $this->model->get_question($question_id);
        
        if (is_wp_error($question) || !$question) {
            wp_send_json_error('Question not found');
        }
        
        ob_start();
        ?>
        <div class="preview-question">
            <h4><?php echo esc_html($question->question_text); ?></h4>
            <div class="preview-meta">
                <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $question->question_type)); ?> |
                <strong>Category:</strong> <?php echo ucfirst($question->category ?: 'General'); ?> |
                <strong>Difficulty:</strong> <?php echo ucfirst($question->difficulty); ?> |
                <strong>Points:</strong> <?php echo $question->points; ?>
            </div>
            <div class="preview-options">
                <?php if (!empty($question->options)): ?>
                    <?php foreach ($question->options as $option): ?>
                        <div class="preview-option <?php echo $option->is_correct ? 'correct' : 'incorrect'; ?>">
                            <?php echo $option->is_correct ? '✓' : '○'; ?> <?php echo esc_html($option->option_text); ?>
                            <?php if ($option->explanation): ?>
                                <br><small><em><?php echo esc_html($option->explanation); ?></em></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No options found for this question.</p>
                <?php endif; ?>
            </div>
            <?php if ($question->explanation): ?>
                <div class="preview-explanation">
                    <strong>Explanation:</strong> <?php echo esc_html($question->explanation); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .preview-question {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .preview-meta {
            font-size: 12px;
            color: #666;
            margin: 10px 0;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 4px;
        }
        
        .preview-options {
            margin: 15px 0;
        }
        
        .preview-option {
            padding: 8px;
            margin: 5px 0;
            border-radius: 4px;
        }
        
        .preview-option.correct {
            background: #d4edda;
            color: #155724;
            font-weight: bold;
        }
        
        .preview-option.incorrect {
            background: #f8f9fa;
            color: #495057;
        }
        
        .preview-explanation {
            margin-top: 15px;
            padding: 10px;
            background: #e3f2fd;
            border-radius: 4px;
            font-size: 14px;
        }
        </style>
        <?php
        
        wp_send_json_success(ob_get_clean());
    }
    
    /**
     * Handle question deletion
     */
    private function handle_delete_question($question_id) {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!$question_id) {
            wp_redirect(admin_url('admin.php?page=vefify-questions&error=' . urlencode('Invalid question ID')));
            exit;
        }
        
        $result = $this->model->delete_question($question_id);
        
        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=vefify-questions&error=' . urlencode($result->get_error_message())));
        } else {
            wp_redirect(admin_url('admin.php?page=vefify-questions&message=' . urlencode('Question deleted successfully')));
        }
        exit;
    }
    
    /**
     * Display question statistics
     */
    private function display_question_statistics() {
        $stats = $this->model->get_question_statistics();
        
        if (!$stats) {
            return;
        }
        
        ?>
        <div class="question-statistics">
            <h3>📊 Question Bank Statistics</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <strong><?php echo number_format($stats['total_questions']); ?></strong>
                    <span>Total Questions</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats['easy_questions']); ?></strong>
                    <span>Easy</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats['medium_questions']); ?></strong>
                    <span>Medium</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats['hard_questions']); ?></strong>
                    <span>Hard</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats['multiple_choice']); ?></strong>
                    <span>Single Choice</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats['multiple_select']); ?></strong>
                    <span>Multiple Choice</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats['true_false']); ?></strong>
                    <span>True/False</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats['total_categories']); ?></strong>
                    <span>Categories</span>
                </div>
            </div>
        </div>
        
        <style>
        .question-statistics {
            margin-top: 30px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
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
            text-transform: uppercase;
        }
        </style>
        <?php
    }
    
    /**
     * Display import form
     */
    private function display_import_form() {
        ?>
        <h1>Import Questions</h1>
        <div class="import-placeholder">
            <p>CSV import functionality will be implemented here.</p>
            <p>Expected format: question_text, option_1, option_2, option_3, option_4, correct_answers, category, difficulty</p>
            <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Back to Questions</a>
        </div>
        
        <style>
        .import-placeholder {
            padding: 40px;
            background: #f9f9f9;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        </style>
        <?php
    }
}