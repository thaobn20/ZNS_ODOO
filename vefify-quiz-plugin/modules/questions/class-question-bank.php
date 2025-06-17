<?php
/**
 * Question Bank Admin Interface
 * File: modules/questions/class-question-bank.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Bank {
    
    private $model;
    
    public function __construct() {
        // Initialize model
        if (!class_exists('Vefify_Question_Model')) {
            require_once plugin_dir_path(__FILE__) . 'class-question-model.php';
        }
        $this->model = new Vefify_Question_Model();
        
        // Admin hooks
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_vefify_load_question_preview', array($this, 'ajax_load_question_preview'));
    }
    
    /**
     * Main admin page router - THIS REPLACES "functionality coming soon"
     */
    public function admin_page_router() {
        $action = $_GET['action'] ?? 'list';
        $question_id = intval($_GET['question_id'] ?? 0);
        
        echo '<div class="wrap">';
        
        switch ($action) {
            case 'new':
                echo '<h1 class="wp-heading-inline">Add New Question</h1>';
                echo '<a href="' . admin_url('admin.php?page=vefify-questions') . '" class="page-title-action">← Back to Questions</a>';
                echo '<hr class="wp-header-end">';
                $this->display_question_form();
                break;
                
            case 'edit':
                echo '<h1 class="wp-heading-inline">Edit Question</h1>';
                echo '<a href="' . admin_url('admin.php?page=vefify-questions') . '" class="page-title-action">← Back to Questions</a>';
                echo '<hr class="wp-header-end">';
                $this->display_question_form($question_id);
                break;
                
            case 'import':
                echo '<h1 class="wp-heading-inline">Import Questions</h1>';
                echo '<a href="' . admin_url('admin.php?page=vefify-questions') . '" class="page-title-action">← Back to Questions</a>';
                echo '<hr class="wp-header-end">';
                $this->display_import_page();
                break;
                
            default:
                echo '<h1 class="wp-heading-inline">Questions</h1>';
                echo '<a href="' . admin_url('admin.php?page=vefify-questions&action=new') . '" class="page-title-action">Add New Question</a>';
                echo '<hr class="wp-header-end">';
                $this->display_questions_list();
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Display questions list with filters
     */
    private function display_questions_list() {
        // Get filter parameters
        $campaign_id = sanitize_text_field($_GET['campaign_id'] ?? '');
        $category = sanitize_text_field($_GET['category'] ?? '');
        $difficulty = sanitize_text_field($_GET['difficulty'] ?? '');
        $search = sanitize_text_field($_GET['search'] ?? '');
        $paged = max(1, intval($_GET['paged'] ?? 1));
        
        // Get questions
        $args = array(
            'campaign_id' => $campaign_id ?: null,
            'category' => $category ?: null,
            'difficulty' => $difficulty ?: null,
            'search' => $search ?: null,
            'page' => $paged,
            'per_page' => 20,
            'is_active' => 1
        );
        
        $result = $this->model->get_questions($args);
        $questions = $result['questions'];
        $total = $result['total'];
        $pages = $result['pages'];
        
        // Get filter options
        $campaigns = $this->model->get_campaigns();
        $categories = $this->model->get_categories();
        
        // Display filters
        ?>
        <div class="questions-filters" style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin: 20px 0;">
            <form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="hidden" name="page" value="vefify-questions">
                
                <select name="campaign_id">
                    <option value="">All Campaigns</option>
                    <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?php echo esc_attr($campaign->id); ?>" <?php selected($campaign_id, $campaign->id); ?>>
                            <?php echo esc_html($campaign->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat); ?>" <?php selected($category, $cat); ?>>
                            <?php echo esc_html(ucfirst($cat)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="difficulty">
                    <option value="">All Difficulties</option>
                    <option value="easy" <?php selected($difficulty, 'easy'); ?>>Easy</option>
                    <option value="medium" <?php selected($difficulty, 'medium'); ?>>Medium</option>
                    <option value="hard" <?php selected($difficulty, 'hard'); ?>>Hard</option>
                </select>
                
                <input type="text" name="search" placeholder="Search questions..." value="<?php echo esc_attr($search); ?>" style="width: 200px;">
                
                <input type="submit" class="button" value="Filter">
                
                <?php if ($campaign_id || $category || $difficulty || $search): ?>
                    <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <p style="margin: 0;">
                    Found <?php echo $total; ?> question<?php echo $total !== 1 ? 's' : ''; ?>
                </p>
            </div>
        </div>
        
        <?php if (empty($questions)): ?>
            <div style="text-align: center; padding: 60px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
                <h2>No questions found</h2>
                <p>Start building your quiz by adding your first question.</p>
                <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=new'); ?>" class="button button-primary button-large">
                    Add Your First Question
                </a>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Question</th>
                        <th style="width: 120px;">Type</th>
                        <th style="width: 100px;">Category</th>
                        <th style="width: 80px;">Difficulty</th>
                        <th style="width: 60px;">Points</th>
                        <th style="width: 120px;">Campaign</th>
                        <th style="width: 100px;">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $question): ?>
                        <tr>
                            <td><?php echo $question->id; ?></td>
                            <td>
                                <strong>
                                    <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=edit&question_id=' . $question->id); ?>">
                                        <?php echo esc_html(wp_trim_words($question->question_text, 8)); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=edit&question_id=' . $question->id); ?>">Edit</a> |
                                    </span>
                                    <span class="duplicate">
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vefify-questions&action=duplicate&question_id=' . $question->id), 'duplicate_question_' . $question->id); ?>">Duplicate</a> |
                                    </span>
                                    <span class="delete">
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vefify-questions&action=delete&question_id=' . $question->id), 'delete_question_' . $question->id); ?>" 
                                           onclick="return confirm('Are you sure you want to delete this question?')" style="color: #a00;">Delete</a> |
                                    </span>
                                    <span class="preview">
                                        <a href="#" class="toggle-preview" data-question-id="<?php echo $question->id; ?>">Preview</a>
                                    </span>
                                </div>
                                <tr id="preview-<?php echo $question->id; ?>" style="display: none;">
                                    <td colspan="8">
                                        <div class="question-preview-content" style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                            <!-- Preview content loaded via AJAX -->
                                        </div>
                                    </td>
                                </tr>
                            </td>
                            <td>
                                <span style="background: #2271b1; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                    <?php echo esc_html(str_replace('_', ' ', ucwords($question->question_type))); ?>
                                </span>
                            </td>
                            <td>
                                <span style="background: #607d8b; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                    <?php echo esc_html(ucfirst($question->category)); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $difficulty_colors = array(
                                    'easy' => '#4caf50',
                                    'medium' => '#ff9800', 
                                    'hard' => '#f44336'
                                );
                                $color = $difficulty_colors[$question->difficulty] ?? '#666';
                                ?>
                                <span style="background: <?php echo $color; ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                    <?php echo esc_html(ucfirst($question->difficulty)); ?>
                                </span>
                            </td>
                            <td style="text-align: center;"><?php echo $question->points; ?></td>
                            <td><?php echo esc_html($question->campaign_name ?: 'Global'); ?></td>
                            <td><?php echo date('M j, Y', strtotime($question->created_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo; Previous',
                            'next_text' => 'Next &raquo;',
                            'total' => $pages,
                            'current' => $paged
                        );
                        echo paginate_links($pagination_args);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Display question form (add/edit)
     */
    private function display_question_form($question_id = 0) {
        $question = null;
        $is_edit = false;
        
        if ($question_id) {
            $question = $this->model->get_question($question_id);
            $is_edit = true;
            
            if (!$question) {
                echo '<div class="notice notice-error"><p>Question not found.</p></div>';
                return;
            }
        }
        
        // Get campaigns for dropdown
        $campaigns = $this->model->get_campaigns();
        
        ?>
        <form method="post" id="question-form">
            <?php wp_nonce_field('vefify_question_action', 'vefify_question_nonce'); ?>
            <input type="hidden" name="action" value="save_question">
            <?php if ($is_edit): ?>
                <input type="hidden" name="question_id" value="<?php echo $question_id; ?>">
            <?php endif; ?>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="campaign_id">Campaign</label>
                        </th>
                        <td>
                            <select name="campaign_id" id="campaign_id" class="regular-text">
                                <option value="">Global (All Campaigns)</option>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <option value="<?php echo esc_attr($campaign->id); ?>" 
                                            <?php selected($question ? $question->campaign_id : '', $campaign->id); ?>>
                                        <?php echo esc_html($campaign->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Choose a specific campaign or leave as Global for all campaigns</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="question_text">Question Text *</label>
                        </th>
                        <td>
                            <textarea name="question_text" id="question_text" rows="4" class="large-text" required><?php echo $question ? esc_textarea($question->question_text) : ''; ?></textarea>
                            <p class="description">Enter the question text that participants will see</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="question_type">Question Type</label>
                        </th>
                        <td>
                            <select name="question_type" id="question_type">
                                <option value="multiple_choice" <?php selected($question ? $question->question_type : 'multiple_choice', 'multiple_choice'); ?>>Multiple Choice (Single Answer)</option>
                                <option value="multiple_select" <?php selected($question ? $question->question_type : '', 'multiple_select'); ?>>Multiple Select (Multiple Answers)</option>
                                <option value="true_false" <?php selected($question ? $question->question_type : '', 'true_false'); ?>>True/False</option>
                            </select>
                            <p class="description">Choose the type of question and answer format</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="category">Category</label>
                        </th>
                        <td>
                            <input type="text" name="category" id="category" value="<?php echo $question ? esc_attr($question->category) : 'general'; ?>" class="regular-text">
                            <p class="description">e.g., general, science, math, history, product-knowledge</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="difficulty">Difficulty Level</label>
                        </th>
                        <td>
                            <select name="difficulty" id="difficulty">
                                <option value="easy" <?php selected($question ? $question->difficulty : 'medium', 'easy'); ?>>Easy</option>
                                <option value="medium" <?php selected($question ? $question->difficulty : 'medium', 'medium'); ?>>Medium</option>
                                <option value="hard" <?php selected($question ? $question->difficulty : 'medium', 'hard'); ?>>Hard</option>
                            </select>
                            <p class="description">Difficulty level affects scoring and question selection</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="points">Points Value</label>
                        </th>
                        <td>
                            <input type="number" name="points" id="points" value="<?php echo $question ? $question->points : 1; ?>" min="1" max="10" class="small-text">
                            <p class="description">Points awarded for correct answer (1-10)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="explanation">Explanation</label>
                        </th>
                        <td>
                            <textarea name="explanation" id="explanation" rows="3" class="large-text"><?php echo $question ? esc_textarea($question->explanation) : ''; ?></textarea>
                            <p class="description">Optional explanation shown after answering (helps with learning)</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h2>Answer Options</h2>
            <div id="answer-options">
                <?php
                $options = $question ? $question->options : array();
                
                // Ensure at least 4 options for new questions
                if (!$question) {
                    $options = array(
                        (object)array('option_text' => '', 'is_correct' => 0, 'explanation' => ''),
                        (object)array('option_text' => '', 'is_correct' => 0, 'explanation' => ''),
                        (object)array('option_text' => '', 'is_correct' => 0, 'explanation' => ''),
                        (object)array('option_text' => '', 'is_correct' => 0, 'explanation' => '')
                    );
                }
                
                foreach ($options as $index => $option):
                ?>
                    <div class="option-row <?php echo $option->is_correct ? 'correct' : ''; ?>" data-index="<?php echo $index; ?>" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 15px; position: relative;">
                        <div class="option-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                            <div class="option-number" style="background: #2271b1; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold;">
                                <?php echo chr(65 + $index); ?>
                            </div>
                            <div class="option-controls" style="display: flex; align-items: center; gap: 15px;">
                                <label class="option-correct" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" name="options[<?php echo $index; ?>][is_correct]" value="1" class="option-correct-checkbox" <?php checked($option->is_correct, 1); ?>>
                                    <span style="color: #00a32a; font-weight: bold;">✓ Correct Answer</span>
                                </label>
                                <button type="button" class="remove-option" title="Remove this option" style="background: #dc3232; color: white; border: none; width: 24px; height: 24px; border-radius: 50%; cursor: pointer;">×</button>
                            </div>
                        </div>
                        <div class="option-content">
                            <label style="font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Answer Option:</label>
                            <input type="text" name="options[<?php echo $index; ?>][text]" placeholder="Enter answer option..." class="option-text widefat" value="<?php echo esc_attr($option->option_text); ?>" required style="margin-bottom: 10px;">
                            <label style="font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Explanation (Optional):</label>
                            <textarea name="options[<?php echo $index; ?>][explanation]" placeholder="Optional: Explain why this answer is correct/incorrect..." rows="2" class="option-explanation widefat"><?php echo esc_textarea($option->explanation); ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div id="add-option-section" style="text-align: center; padding: 20px; background: #f0f8ff; border: 2px dashed #2271b1; border-radius: 8px; margin-top: 20px;">
                <button type="button" id="add-option" class="button button-large" style="background: #2271b1; color: white; border: none; padding: 12px 24px; border-radius: 6px;">+ Add Another Option</button>
                <p class="description" id="options-help" style="margin-top: 10px;">Select one or more correct answers depending on question type</p>
            </div>
            
            <p class="submit">
                <input type="submit" name="submit" class="button-primary button-large" value="<?php echo $is_edit ? 'Update Question' : 'Save Question'; ?>">
                <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button button-large">Cancel</a>
            </p>
        </form>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            let optionCount = <?php echo count($options); ?>;
            
            // Add option functionality
            $('#add-option').on('click', function(e) {
                e.preventDefault();
                
                if (optionCount >= 6) {
                    alert('Maximum 6 options allowed');
                    return;
                }
                
                const optionHtml = `
                    <div class="option-row" data-index="${optionCount}" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 15px;">
                        <div class="option-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                            <div class="option-number" style="background: #2271b1; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold;">
                                ${String.fromCharCode(65 + optionCount)}
                            </div>
                            <div class="option-controls" style="display: flex; align-items: center; gap: 15px;">
                                <label class="option-correct" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" name="options[${optionCount}][is_correct]" value="1" class="option-correct-checkbox">
                                    <span style="color: #00a32a; font-weight: bold;">✓ Correct Answer</span>
                                </label>
                                <button type="button" class="remove-option" title="Remove this option" style="background: #dc3232; color: white; border: none; width: 24px; height: 24px; border-radius: 50%; cursor: pointer;">×</button>
                            </div>
                        </div>
                        <div class="option-content">
                            <label style="font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Answer Option:</label>
                            <input type="text" name="options[${optionCount}][text]" placeholder="Enter answer option..." class="option-text widefat" required style="margin-bottom: 10px;">
                            <label style="font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Explanation (Optional):</label>
                            <textarea name="options[${optionCount}][explanation]" placeholder="Optional: Explain why this answer is correct/incorrect..." rows="2" class="option-explanation widefat"></textarea>
                        </div>
                    </div>
                `;
                
                $('#answer-options').append(optionHtml);
                optionCount++;
                
                // Focus on new option
                $('#answer-options .option-row:last .option-text').focus();
            });
            
            // Remove option functionality
            $(document).on('click', '.remove-option', function(e) {
                e.preventDefault();
                
                if ($('.option-row').length <= 2) {
                    alert('You need at least 2 options');
                    return;
                }
                
                $(this).closest('.option-row').remove();
                updateOptionNumbers();
            });
            
            // Update option letters and numbers
            function updateOptionNumbers() {
                $('.option-row').each(function(index) {
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
            }
            
            // Question type change handling
            $('#question_type').on('change', function() {
                const type = $(this).val();
                
                if (type === 'true_false') {
                    // Limit to 2 options for True/False
                    $('.option-row').slice(2).remove();
                    
                    if ($('.option-row').length < 2) {
                        // Add options if needed
                        while ($('.option-row').length < 2) {
                            $('#add-option').click();
                        }
                    }
                    
                    // Set True/False values
                    $('.option-row').eq(0).find('.option-text').val('True');
                    $('.option-row').eq(1).find('.option-text').val('False');
                    
                    $('#add-option-section').hide();
                    $('#options-help').text('Select either True or False as the correct answer');
                } else {
                    // Show add button for other types
                    $('#add-option-section').show();
                    
                    // Clear True/False values if switching from True/False
                    $('.option-row').each(function() {
                        const $input = $(this).find('.option-text');
                        if ($input.val() === 'True' || $input.val() === 'False') {
                            $input.val('');
                        }
                    });
                    
                    if (type === 'multiple_select') {
                        $('#options-help').text('Select all correct answers (multiple selections allowed)');
                    } else {
                        $('#options-help').text('Select one correct answer');
                    }
                }
            });
            
            // Single choice validation for multiple_choice
            $(document).on('change', '.option-correct-checkbox', function() {
                const questionType = $('#question_type').val();
                
                if (questionType === 'multiple_choice' && this.checked) {
                    // Uncheck all other checkboxes for single choice
                    $('.option-correct-checkbox').not(this).prop('checked', false);
                    $('.option-row').removeClass('correct');
                }
                
                // Update visual state
                $(this).closest('.option-row').toggleClass('correct', this.checked);
            });
            
            // Form validation
            $('#question-form').on('submit', function(e) {
                const questionText = $('#question_text').val().trim();
                const checkedOptions = $('.option-correct-checkbox:checked').length;
                const filledOptions = $('.option-text').filter(function() {
                    return $(this).val().trim() !== '';
                }).length;
                
                if (!questionText) {
                    e.preventDefault();
                    alert('Question text is required');
                    $('#question_text').focus();
                    return;
                }
                
                if (filledOptions < 2) {
                    e.preventDefault();
                    alert('You need at least 2 answer options');
                    return;
                }
                
                if (checkedOptions === 0) {
                    e.preventDefault();
                    alert('Please mark at least one correct answer');
                    return;
                }
                
                // Show loading state
                $(this).find('input[type="submit"]').prop('disabled', true).val('Saving...');
            });
            
            // Initialize question type
            $('#question_type').trigger('change');
        });
        </script>
        
        <style>
        .option-row.correct {
            border-left: 4px solid #00a32a !important;
            background: #f0f8f0 !important;
        }
        .remove-option:hover {
            background: #a00 !important;
        }
        #add-option:hover {
            background: #1e5a8a !important;
        }
        </style>
        <?php
    }
    
    /**
     * Display import page
     */
    private function display_import_page() {
        ?>
        <div style="max-width: 800px;">
            <div class="card">
                <h2>Import Questions from CSV</h2>
                <p>Upload a CSV file to import multiple questions at once. This is useful for bulk question creation.</p>
                
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('vefify_import_questions', 'vefify_import_nonce'); ?>
                    <input type="hidden" name="action" value="import_questions">
                    
                    <table class="form-table">
                        <tr>
                            <th>CSV File</th>
                            <td>
                                <input type="file" name="questions_csv" accept=".csv" required>
                                <p class="description">Select a CSV file containing your questions</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Campaign</th>
                            <td>
                                <select name="campaign_id">
                                    <option value="">Global (All Campaigns)</option>
                                    <?php foreach ($this->model->get_campaigns() as $campaign): ?>
                                        <option value="<?php echo esc_attr($campaign->id); ?>">
                                            <?php echo esc_html($campaign->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button-primary" value="Import Questions">
                    </p>
                </form>
                
                <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 4px; margin-top: 30px;">
                    <h3>CSV Format Requirements</h3>
                    <p>Your CSV file should have the following columns (in this exact order):</p>
                    <ol>
                        <li><strong>question_text</strong> - The question text</li>
                        <li><strong>option_1</strong> - First answer option</li>
                        <li><strong>option_2</strong> - Second answer option</li>
                        <li><strong>option_3</strong> - Third answer option (optional)</li>
                        <li><strong>option_4</strong> - Fourth answer option (optional)</li>
                        <li><strong>correct_answers</strong> - Comma-separated list of correct option numbers (e.g., "1,3")</li>
                        <li><strong>category</strong> - Question category (optional, defaults to "general")</li>
                        <li><strong>difficulty</strong> - easy, medium, or hard (optional, defaults to "medium")</li>
                        <li><strong>points</strong> - Points value (optional, defaults to 1)</li>
                        <li><strong>explanation</strong> - Answer explanation (optional)</li>
                    </ol>
                    
                    <h4>Example CSV Content:</h4>
                    <pre style="background: white; padding: 10px; border: 1px solid #ccc; overflow-x: auto;">
question_text,option_1,option_2,option_3,option_4,correct_answers,category,difficulty,points,explanation
"What is the capital of France?",Paris,London,Berlin,Madrid,1,geography,easy,1,"Paris has been the capital of France since the 12th century"
"Which are programming languages?",JavaScript,HTML,Python,CSS,"1,3",technology,medium,2,"JavaScript and Python are programming languages, while HTML and CSS are markup/styling languages"
"The Earth is flat.",True,False,,,2,science,easy,1,"The Earth is spherical, not flat"</pre>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle admin actions (save, delete, etc.)
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle form submissions
        if ($_POST && wp_verify_nonce($_POST['vefify_question_nonce'] ?? '', 'vefify_question_action')) {
            if ($_POST['action'] === 'save_question') {
                $this->handle_save_question();
            }
        }
        
        // Handle import
        if ($_POST && wp_verify_nonce($_POST['vefify_import_nonce'] ?? '', 'vefify_import_questions')) {
            if ($_POST['action'] === 'import_questions') {
                $this->handle_import_questions();
            }
        }
        
        // Handle URL actions
        $action = $_GET['action'] ?? '';
        $question_id = intval($_GET['question_id'] ?? 0);
        
        switch ($action) {
            case 'delete':
                if (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_question_' . $question_id)) {
                    $this->handle_delete_question($question_id);
                }
                break;
                
            case 'duplicate':
                if (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'duplicate_question_' . $question_id)) {
                    $this->handle_duplicate_question($question_id);
                }
                break;
        }
    }
    
    /**
     * Handle save question
     */
    private function handle_save_question() {
        $question_id = intval($_POST['question_id'] ?? 0);
        $is_edit = !empty($question_id);
        
        // Prepare data
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
        
        // Process options
        if (!empty($_POST['options'])) {
            foreach ($_POST['options'] as $option) {
                if (!empty($option['text'])) {
                    $data['options'][] = array(
                        'text' => sanitize_textarea_field($option['text']),
                        'is_correct' => !empty($option['is_correct']),
                        'explanation' => sanitize_textarea_field($option['explanation'] ?? '')
                    );
                }
            }
        }
        
        // Save question
        if ($is_edit) {
            $result = $this->model->update_question($question_id, $data);
        } else {
            $result = $this->model->create_question($data);
        }
        
        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $message = $is_edit ? 'Question updated successfully!' : 'Question created successfully!';
            $this->add_admin_notice($message, 'success');
            
            // Redirect to questions list
            wp_redirect(admin_url('admin.php?page=vefify-questions'));
            exit;
        }
    }
    
    /**
     * Handle delete question
     */
    private function handle_delete_question($question_id) {
        $result = $this->model->delete_question($question_id);
        
        if ($result) {
            $this->add_admin_notice('Question deleted successfully!', 'success');
        } else {
            $this->add_admin_notice('Failed to delete question.', 'error');
        }
        
        wp_redirect(admin_url('admin.php?page=vefify-questions'));
        exit;
    }
    
    /**
     * Handle duplicate question
     */
    private function handle_duplicate_question($question_id) {
        $question = $this->model->get_question($question_id);
        
        if (!$question) {
            $this->add_admin_notice('Question not found.', 'error');
            wp_redirect(admin_url('admin.php?page=vefify-questions'));
            exit;
        }
        
        // Prepare data for duplication
        $data = array(
            'campaign_id' => $question->campaign_id,
            'question_text' => $question->question_text . ' (Copy)',
            'question_type' => $question->question_type,
            'category' => $question->category,
            'difficulty' => $question->difficulty,
            'points' => $question->points,
            'explanation' => $question->explanation,
            'options' => array()
        );
        
        foreach ($question->options as $option) {
            $data['options'][] = array(
                'text' => $option->option_text,
                'is_correct' => $option->is_correct,
                'explanation' => $option->explanation
            );
        }
        
        $result = $this->model->create_question($data);
        
        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $this->add_admin_notice('Question duplicated successfully!', 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=vefify-questions'));
        exit;
    }
    
    /**
     * Handle import questions
     */
    private function handle_import_questions() {
        if (empty($_FILES['questions_csv']['tmp_name'])) {
            $this->add_admin_notice('Please select a CSV file to import.', 'error');
            return;
        }
        
        $file_path = $_FILES['questions_csv']['tmp_name'];
        $campaign_id = !empty($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null;
        
        // Basic CSV processing (you can enhance this)
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            $this->add_admin_notice('Could not read the CSV file.', 'error');
            return;
        }
        
        $imported = 0;
        $errors = array();
        $line = 0;
        
        // Skip header row
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== false) {
            $line++;
            
            if (count($data) < 6) {
                $errors[] = "Line {$line}: Insufficient columns";
                continue;
            }
            
            // Parse CSV data
            $question_data = array(
                'campaign_id' => $campaign_id,
                'question_text' => $data[0],
                'question_type' => 'multiple_choice',
                'category' => $data[6] ?? 'general',
                'difficulty' => $data[7] ?? 'medium',
                'points' => intval($data[8] ?? 1),
                'explanation' => $data[9] ?? '',
                'options' => array()
            );
            
            // Add options
            for ($i = 1; $i <= 4; $i++) {
                if (!empty($data[$i])) {
                    $question_data['options'][] = array(
                        'text' => $data[$i],
                        'is_correct' => strpos($data[5], (string)$i) !== false,
                        'explanation' => ''
                    );
                }
            }
            
            $result = $this->model->create_question($question_data);
            
            if (is_wp_error($result)) {
                $errors[] = "Line {$line}: " . $result->get_error_message();
            } else {
                $imported++;
            }
        }
        
        fclose($handle);
        
        if ($imported > 0) {
            $this->add_admin_notice("Successfully imported {$imported} questions.", 'success');
        }
        
        if (!empty($errors)) {
            $this->add_admin_notice("Import completed with errors:\n" . implode("\n", $errors), 'warning');
        }
        
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
        
        // Basic styling for the interface
        wp_add_inline_style('admin-forms', '
            .option-row.correct {
                border-left: 4px solid #00a32a !important;
                background: #f0f8f0 !important;
            }
            .remove-option:hover {
                background: #a00 !important;
            }
            #add-option:hover {
                background: #1e5a8a !important;
            }
            .questions-filters {
                background: #f9f9f9;
                padding: 15px;
                border-radius: 4px;
                margin: 20px 0;
            }
        ');
    }
    
    /**
     * AJAX: Load question preview
     */
    public function ajax_load_question_preview() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_question_bank')) {
            wp_die('Security check failed');
        }
        
        $question_id = intval($_POST['question_id'] ?? 0);
        $question = $this->model->get_question($question_id);
        
        if (!$question) {
            wp_send_json_error('Question not found');
        }
        
        ob_start();
        ?>
        <div style="padding: 15px;">
            <div style="margin-bottom: 15px;">
                <strong style="font-size: 16px; color: #333;"><?php echo esc_html($question->question_text); ?></strong>
            </div>
            <div style="margin-bottom: 15px; color: #666; font-size: 12px;">
                Type: <?php echo esc_html(str_replace('_', ' ', ucwords($question->question_type))); ?> | 
                Category: <?php echo esc_html(ucfirst($question->category)); ?> | 
                Difficulty: <?php echo esc_html(ucfirst($question->difficulty)); ?> | 
                Points: <?php echo $question->points; ?>
            </div>
            <div style="margin-left: 20px;">
                <?php foreach ($question->options as $index => $option): ?>
                    <div style="margin: 8px 0; padding: 8px; border-radius: 3px; <?php echo $option->is_correct ? 'background: #d4edda; color: #155724; font-weight: bold;' : 'background: #f8f9fa; color: #333;'; ?>">
                        <?php echo chr(65 + $index); ?>. <?php echo esc_html($option->option_text); ?>
                        <?php if ($option->is_correct): ?>
                            <span style="color: green; font-weight: bold;"> ✓ Correct</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($question->explanation): ?>
                <div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #2196f3; border-radius: 4px;">
                    <strong>Explanation:</strong> <?php echo esc_html($question->explanation); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        wp_send_json_success(ob_get_clean());
    }
    
    /**
     * Add admin notice
     */
    private function add_admin_notice($message, $type = 'info') {
        add_action('admin_notices', function() use ($message, $type) {
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });
    }
}