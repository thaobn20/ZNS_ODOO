<?php
/**
 * Question Bank - Admin Interface with Centralized Database
 * File: modules/questions/class-question-bank.php
 * 
 * Handles admin interface for question management
 * Integrates with centralized database system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Bank {
    
    private $model;
    private $database;
    
    public function __construct($database = null) {
        // Store database instance for use throughout class
        $this->database = $database;
        
        // Initialize model with database instance
        if (!class_exists('Vefify_Question_Model')) {
            require_once plugin_dir_path(__FILE__) . 'class-question-model.php';
        }
        
        $this->model = new Vefify_Question_Model($database);
        
        // Hook into WordPress
        $this->init_hooks();
        
        error_log('Vefify Question Bank: Initialized with ' . ($database ? 'centralized' : 'fallback') . ' database');
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Handle form submissions
        add_action('init', array($this, 'handle_form_submissions'));
        
        // AJAX handlers
        add_action('wp_ajax_save_vefify_question', array($this, 'ajax_save_question'));
        add_action('wp_ajax_delete_vefify_question', array($this, 'ajax_delete_question'));
        add_action('wp_ajax_get_question_preview', array($this, 'ajax_get_question_preview'));
        
        // Admin enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Main admin page router
     */
    public function admin_page_router() {
        $action = $_GET['action'] ?? 'list';
        
        // Handle messages
        $this->handle_admin_messages();
        
        switch ($action) {
            case 'new':
                $this->display_question_form();
                break;
                
            case 'edit':
                $question_id = intval($_GET['id'] ?? 0);
                $this->display_question_form($question_id);
                break;
                
            case 'import':
                $this->display_import_page();
                break;
                
            case 'categories':
                $this->display_categories_page();
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
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $search = sanitize_text_field($_GET['s'] ?? '');
        $category = sanitize_text_field($_GET['category'] ?? '');
        $difficulty = sanitize_text_field($_GET['difficulty'] ?? '');
        $campaign_id = intval($_GET['campaign_id'] ?? 0);
        
        // Get questions
        $args = array(
            'page' => $current_page,
            'per_page' => 20,
            'search' => $search,
            'category' => $category,
            'difficulty' => $difficulty,
            'campaign_id' => $campaign_id ?: null
        );
        
        $result = $this->model->get_questions($args);
        $questions = $result['questions'];
        $total = $result['total'];
        $pages = $result['pages'];
        
        // Get categories for filter
        $categories = $this->model->get_categories();
        
        // Get campaigns for filter (if available)
        $campaigns = $this->get_campaigns_for_filter();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">❓ Question Bank</h1>
            <a href="<?php echo add_query_arg('action', 'new'); ?>" class="page-title-action">Add New Question</a>
            <a href="<?php echo add_query_arg('action', 'import'); ?>" class="page-title-action">Import CSV</a>
            <hr class="wp-header-end">
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" class="alignleft">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                    
                    <select name="campaign_id">
                        <option value="">All Campaigns</option>
                        <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?php echo $campaign['id']; ?>" <?php selected($campaign_id, $campaign['id']); ?>>
                                <?php echo esc_html($campaign['name']); ?>
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
                    
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search questions...">
                    <input type="submit" class="button" value="Filter">
                    
                    <?php if ($search || $category || $difficulty || $campaign_id): ?>
                        <a href="<?php echo remove_query_arg(array('s', 'category', 'difficulty', 'campaign_id', 'paged')); ?>" class="button">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Questions Table -->
            <?php if (empty($questions)): ?>
                <div class="notice notice-info">
                    <p><strong>No questions found.</strong> 
                    <?php if ($search || $category || $difficulty): ?>
                        Try adjusting your filters or <a href="<?php echo add_query_arg('action', 'new'); ?>">add a new question</a>.
                    <?php else: ?>
                        <a href="<?php echo add_query_arg('action', 'new'); ?>">Add your first question</a> to get started.
                    <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 40%">Question</th>
                            <th scope="col">Type</th>
                            <th scope="col">Category</th>
                            <th scope="col">Difficulty</th>
                            <th scope="col">Points</th>
                            <th scope="col">Campaign</th>
                            <th scope="col">Created</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $question): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html(wp_trim_words($question['question_text'], 8)); ?></strong>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="<?php echo add_query_arg(array('action' => 'edit', 'id' => $question['id'])); ?>">Edit</a> |
                                        </span>
                                        <span class="view">
                                            <a href="#" class="preview-question" data-question-id="<?php echo $question['id']; ?>">Preview</a> |
                                        </span>
                                        <span class="trash">
                                            <a href="#" class="delete-question" data-question-id="<?php echo $question['id']; ?>" 
                                               onclick="return confirm('Are you sure you want to delete this question?');">Delete</a>
                                        </span>
                                    </div>
                                </td>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $question['question_type']))); ?></td>
                                <td><?php echo esc_html($question['category'] ?: '—'); ?></td>
                                <td>
                                    <span class="difficulty-<?php echo esc_attr($question['difficulty']); ?>">
                                        <?php echo esc_html(ucfirst($question['difficulty'])); ?>
                                    </span>
                                </td>
                                <td><?php echo intval($question['points']); ?></td>
                                <td><?php echo esc_html($question['campaign_name'] ?: 'General'); ?></td>
                                <td><?php echo esc_html(mysql2date('M j, Y', $question['created_at'])); ?></td>
                                <td>
                                    <a href="<?php echo add_query_arg(array('action' => 'edit', 'id' => $question['id'])); ?>" 
                                       class="button button-small">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            $page_links = paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $pages,
                                'current' => $current_page
                            ));
                            echo $page_links;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Statistics Summary -->
            <div style="margin-top: 20px;">
                <?php $this->display_question_statistics(); ?>
            </div>
        </div>
        
        <!-- Preview Modal -->
        <div id="question-preview-modal" style="display: none;">
            <div id="question-preview-content"></div>
        </div>
        <?php
    }
    
    /**
     * Display question form (add/edit)
     */
    private function display_question_form($question_id = null) {
    $question = null;
    $is_edit = false;
    
    if ($question_id) {
        $question = $this->model->get_question($question_id);
        if (!$question) {
            echo '<div class="notice notice-error"><p>Question not found.</p></div>';
            return;
        }
        $is_edit = true;
    }
    
    // Get campaigns and categories using the centralized database
    $campaigns = $this->get_campaigns_safe();
    $categories = $this->get_categories_safe();
    
    ?>
    <div class="wrap vefify-question-form">
        <h1 class="wp-heading-inline">
            <?php echo $is_edit ? 'Edit Question' : 'Add New Question'; ?>
        </h1>
        
        <?php if (!$is_edit): ?>
            <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="page-title-action">← Back to Questions</a>
        <?php endif; ?>
        
        <hr class="wp-header-end">
        
        <!-- Enhanced Form with Better Styling -->
        <div class="vefify-form-container">
            <form method="post" action="" id="question-form" class="vefify-enhanced-form">
                <?php wp_nonce_field('vefify_question_save', 'vefify_question_nonce'); ?>
                <input type="hidden" name="action" value="save_question">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                <?php endif; ?>
                
                <!-- Campaign Selection - FIXED -->
                <div class="form-section">
                    <h3>📋 Campaign Assignment</h3>
                    <div class="form-row">
                        <label for="campaign_id" class="form-label">
                            <strong>Campaign:</strong>
                            <span class="description">Select which campaign this question belongs to</span>
                        </label>
                        <div class="form-input">
                            <select name="campaign_id" id="campaign_id" class="enhanced-select">
                                <option value="">🌟 General (Available to all campaigns)</option>
                                <?php if (!empty($campaigns)): ?>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo $campaign['id']; ?>" 
                                                <?php selected($question['campaign_id'] ?? '', $campaign['id']); ?>>
                                            📋 <?php echo esc_html($campaign['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No campaigns found - Create a campaign first</option>
                                <?php endif; ?>
                            </select>
                            <?php if (empty($campaigns)): ?>
                                <p class="form-help">
                                    <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=new'); ?>" 
                                       class="button button-secondary" target="_blank">
                                        ➕ Create New Campaign
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Question Content -->
                <div class="form-section">
                    <h3>❓ Question Content</h3>
                    <div class="form-row">
                        <label for="question_text" class="form-label">
                            <strong>Question Text: *</strong>
                            <span class="description">Enter your question clearly and concisely</span>
                        </label>
                        <div class="form-input">
                            <textarea name="question_text" id="question_text" rows="4" required
                                      class="enhanced-textarea" 
                                      placeholder="Example: What is the capital of France?"><?php echo esc_textarea($question['question_text'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <label for="explanation" class="form-label">
                            <strong>Explanation:</strong>
                            <span class="description">Optional explanation shown after answering</span>
                        </label>
                        <div class="form-input">
                            <textarea name="explanation" id="explanation" rows="3"
                                      class="enhanced-textarea" 
                                      placeholder="Provide additional context or explanation..."><?php echo esc_textarea($question['explanation'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Question Properties -->
                <div class="form-section">
                    <h3>⚙️ Question Properties</h3>
                    <div class="form-grid">
                        <!-- Question Type -->
                        <div class="form-row">
                            <label for="question_type" class="form-label">
                                <strong>Question Type:</strong>
                            </label>
                            <div class="form-input">
                                <select name="question_type" id="question_type" class="enhanced-select">
                                    <option value="multiple_choice" <?php selected($question['question_type'] ?? 'multiple_choice', 'multiple_choice'); ?>>
                                        🔘 Multiple Choice (Select one)
                                    </option>
                                    <option value="multiple_select" <?php selected($question['question_type'] ?? '', 'multiple_select'); ?>>
                                        ☑️ Multiple Select (Select many)
                                    </option>
                                    <option value="true_false" <?php selected($question['question_type'] ?? '', 'true_false'); ?>>
                                        ⚖️ True/False
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Category - FIXED -->
                        <div class="form-row">
                            <label for="category" class="form-label">
                                <strong>Category:</strong>
                                <span class="description">Organize questions by topic</span>
                            </label>
                            <div class="form-input">
                                <input type="text" name="category" id="category" 
                                       value="<?php echo esc_attr($question['category'] ?? ''); ?>" 
                                       class="enhanced-input"
                                       list="categories-list"
                                       placeholder="e.g., geography, science, history">
                                
                                <datalist id="categories-list">
                                    <?php if (!empty($categories)): ?>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo esc_attr($cat); ?>">
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <!-- Common categories as suggestions -->
                                    <option value="general">
                                    <option value="geography">
                                    <option value="science">
                                    <option value="history">
                                    <option value="technology">
                                    <option value="mathematics">
                                    <option value="literature">
                                    <option value="sports">
                                    <option value="entertainment">
                                    <option value="business">
                                </datalist>
                                
                                <?php if (!empty($categories)): ?>
                                    <div class="category-suggestions">
                                        <small><strong>Existing categories:</strong> 
                                        <?php echo implode(', ', array_map('esc_html', $categories)); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Difficulty -->
                        <div class="form-row">
                            <label for="difficulty" class="form-label">
                                <strong>Difficulty:</strong>
                            </label>
                            <div class="form-input">
                                <select name="difficulty" id="difficulty" class="enhanced-select">
                                    <option value="easy" <?php selected($question['difficulty'] ?? 'medium', 'easy'); ?>>
                                        🟢 Easy
                                    </option>
                                    <option value="medium" <?php selected($question['difficulty'] ?? 'medium', 'medium'); ?>>
                                        🟡 Medium
                                    </option>
                                    <option value="hard" <?php selected($question['difficulty'] ?? 'medium', 'hard'); ?>>
                                        🔴 Hard
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Points -->
                        <div class="form-row">
                            <label for="points" class="form-label">
                                <strong>Points:</strong>
                            </label>
                            <div class="form-input">
                                <input type="number" name="points" id="points" min="1" max="10" 
                                       value="<?php echo intval($question['points'] ?? 1); ?>" 
                                       class="enhanced-input small-input">
                                <span class="input-suffix">points</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Answer Options -->
                <div class="form-section">
                    <h3>📝 Answer Options</h3>
                    <div class="options-container" id="options-container">
                        <?php
                        $options = $question['options'] ?? array();
                        if (empty($options)) {
                            // Default 4 empty options for new questions
                            $options = array_fill(0, 4, array('option_text' => '', 'is_correct' => 0));
                        }
                        
                        foreach ($options as $index => $option):
                        ?>
                            <div class="option-row enhanced-option" data-index="<?php echo $index; ?>">
                                <div class="option-number"><?php echo $index + 1; ?></div>
                                <div class="option-content">
                                    <input type="text" 
                                           name="options[<?php echo $index; ?>][option_text]" 
                                           value="<?php echo esc_attr($option['option_text']); ?>" 
                                           placeholder="Enter option <?php echo $index + 1; ?>..." 
                                           class="option-text enhanced-input" required>
                                    
                                    <label class="correct-label">
                                        <input type="checkbox" 
                                               name="options[<?php echo $index; ?>][is_correct]" 
                                               value="1" 
                                               <?php checked($option['is_correct'], 1); ?> 
                                               class="option-correct">
                                        <span class="checkmark">✓ Correct Answer</span>
                                    </label>
                                </div>
                                <div class="option-actions">
                                    <button type="button" class="button-remove remove-option" 
                                            onclick="removeOption(this)" 
                                            title="Remove this option"
                                            <?php echo count($options) <= 2 ? 'style="display:none"' : ''; ?>>
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="options-controls">
                        <button type="button" id="add-option" class="button button-secondary">
                            ➕ Add Option
                        </button>
                        <div class="options-help">
                            <small>
                                📌 <strong>Requirements:</strong> At least 2 options, at least 1 must be marked as correct
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <div class="primary-actions">
                        <button type="submit" name="submit" id="submit" class="button button-primary button-large">
                            <?php echo $is_edit ? '💾 Update Question' : '✅ Save Question'; ?>
                        </button>
                        
                        <?php if ($is_edit): ?>
                            <button type="button" id="preview-question" class="button button-secondary button-large">
                                👁️ Preview
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="secondary-actions">
                        <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" 
                           class="button button-link">
                            ← Back to Questions List
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Enhanced CSS Styles -->
    <style>
    .vefify-question-form {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
    }
    
    .vefify-form-container {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        max-width: 1000px;
    }
    
    .vefify-enhanced-form .form-section {
        margin-bottom: 40px;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 25px;
        background: #fafafa;
    }
    
    .vefify-enhanced-form .form-section h3 {
        margin: 0 0 20px 0;
        color: #0073aa;
        font-size: 18px;
        border-bottom: 2px solid #0073aa;
        padding-bottom: 8px;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .form-row {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }
    
    .form-label .description {
        display: block;
        font-weight: normal;
        font-size: 12px;
        color: #666;
        margin-top: 2px;
    }
    
    .enhanced-select, .enhanced-input, .enhanced-textarea {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
    }
    
    .enhanced-select:focus, .enhanced-input:focus, .enhanced-textarea:focus {
        border-color: #0073aa;
        box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
        outline: none;
    }
    
    .small-input {
        width: 100px;
    }
    
    .input-suffix {
        margin-left: 8px;
        color: #666;
        font-size: 12px;
    }
    
    .category-suggestions {
        margin-top: 8px;
        padding: 8px;
        background: #e7f3ff;
        border-radius: 4px;
        border-left: 4px solid #0073aa;
    }
    
    .options-container {
        margin-bottom: 20px;
    }
    
    .enhanced-option {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        margin-bottom: 15px;
        padding: 15px;
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .enhanced-option:hover {
        border-color: #0073aa;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .option-number {
        background: #0073aa;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        flex-shrink: 0;
    }
    
    .option-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .option-content input[type="text"] {
        margin: 0;
    }
    
    .correct-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        color: #46b450;
        cursor: pointer;
    }
    
    .correct-label input[type="checkbox"] {
        transform: scale(1.2);
    }
    
    .button-remove {
        background: #dc3232;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 8px 12px;
        cursor: pointer;
        transition: background 0.3s ease;
    }
    
    .button-remove:hover {
        background: #c02222;
    }
    
    .options-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        background: #f0f0f0;
        border-radius: 6px;
    }
    
    .options-help {
        color: #666;
    }
    
    .form-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #e0e0e0;
    }
    
    .primary-actions {
        display: flex;
        gap: 15px;
    }
    
    .button-large {
        padding: 12px 25px;
        font-size: 16px;
        height: auto;
    }
    
    .form-help {
        margin-top: 10px;
    }
    
    /* Responsive design */
    @media (max-width: 768px) {
        .vefify-form-container {
            padding: 15px;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .enhanced-option {
            flex-direction: column;
            align-items: stretch;
        }
        
        .form-actions {
            flex-direction: column;
            gap: 15px;
        }
    }
    </style>
    
    <!-- Enhanced JavaScript -->
    <script>
    jQuery(document).ready(function($) {
        var optionIndex = <?php echo count($options); ?>;
        
        // Add option functionality
        $('#add-option').click(function() {
            if (optionIndex >= 6) {
                alert('Maximum 6 options allowed');
                return;
            }
            
            var optionHtml = `
                <div class="option-row enhanced-option" data-index="${optionIndex}">
                    <div class="option-number">${optionIndex + 1}</div>
                    <div class="option-content">
                        <input type="text" name="options[${optionIndex}][option_text]" 
                               placeholder="Enter option ${optionIndex + 1}..." 
                               class="option-text enhanced-input" required>
                        <label class="correct-label">
                            <input type="checkbox" name="options[${optionIndex}][is_correct]" 
                                   value="1" class="option-correct">
                            <span class="checkmark">✓ Correct Answer</span>
                        </label>
                    </div>
                    <div class="option-actions">
                        <button type="button" class="button-remove remove-option" 
                                onclick="removeOption(this)" title="Remove this option">🗑️</button>
                    </div>
                </div>
            `;
            
            $('#options-container').append(optionHtml);
            optionIndex++;
            updateRemoveButtons();
            updateOptionNumbers();
        });
        
        // Update remove button visibility
        function updateRemoveButtons() {
            var optionCount = $('#options-container .option-row').length;
            $('.remove-option').toggle(optionCount > 2);
        }
        
        // Update option numbers
        function updateOptionNumbers() {
            $('#options-container .option-row').each(function(index) {
                $(this).find('.option-number').text(index + 1);
                $(this).find('input[type="text"]').attr('placeholder', 'Enter option ' + (index + 1) + '...');
            });
        }
        
        // Remove option function
        window.removeOption = function(button) {
            $(button).closest('.option-row').remove();
            updateRemoveButtons();
            updateOptionNumbers();
        };
        
        // Enhanced form validation
        $('#question-form').submit(function(e) {
            var hasCorrectAnswer = false;
            var validOptions = 0;
            
            $('.option-text').each(function() {
                if ($(this).val().trim() !== '') {
                    validOptions++;
                    var checkbox = $(this).closest('.option-content').find('.option-correct');
                    if (checkbox.is(':checked')) {
                        hasCorrectAnswer = true;
                    }
                }
            });
            
            if (validOptions < 2) {
                alert('⚠️ Please provide at least 2 options');
                e.preventDefault();
                return false;
            }
            
            if (!hasCorrectAnswer) {
                alert('⚠️ Please mark at least one option as correct');
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            $('#submit').prop('disabled', true).text('💾 Saving...');
        });
        
        // Initialize
        updateRemoveButtons();
    });
    </script>
    <?php
}

/**
 * HELPER: Get campaigns safely (with fallback)
 */
private function get_campaigns_safe() {
    global $wpdb;
    
    // Try centralized database first
    if ($this->database && method_exists($this->database, 'get_campaigns_for_dropdown')) {
        return $this->database->get_campaigns_for_dropdown();
    }
    
    // Fallback to direct query
    $table_prefix = $wpdb->prefix . (defined('VEFIFY_QUIZ_TABLE_PREFIX') ? VEFIFY_QUIZ_TABLE_PREFIX : 'vefify_');
    $campaigns_table = $table_prefix . 'campaigns';
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$campaigns_table}'");
    if (!$table_exists) {
        return array();
    }
    
    // Use is_active column (not status)
    $campaigns = $wpdb->get_results("
        SELECT id, name 
        FROM {$campaigns_table} 
        WHERE is_active = 1
        ORDER BY name
    ", ARRAY_A);
    
    return $campaigns ?: array();
}

/**
 * HELPER: Get categories safely (with fallback)
 */
private function get_categories_safe() {
    global $wpdb;
    
    // Try centralized database first
    if ($this->database && method_exists($this->database, 'get_categories_for_dropdown')) {
        return $this->database->get_categories_for_dropdown();
    }
    
    // Fallback to direct query
    $table_prefix = $wpdb->prefix . (defined('VEFIFY_QUIZ_TABLE_PREFIX') ? VEFIFY_QUIZ_TABLE_PREFIX : 'vefify_');
    $questions_table = $table_prefix . 'questions';
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$questions_table}'");
    if (!$table_exists) {
        return array();
    }
    
    $categories = $wpdb->get_col("
        SELECT DISTINCT category 
        FROM {$questions_table} 
        WHERE category IS NOT NULL AND category != '' AND is_active = 1
        ORDER BY category
    ");
    
    return $categories ?: array();
}
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'save_question') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        if (!wp_verify_nonce($_POST['vefify_question_nonce'] ?? '', 'vefify_question_save')) {
            wp_die(__('Security check failed. Please try again.'));
        }
        
        $this->save_question();
    }
    
    /**
     * Save question (handles both create and update)
     */
    private function save_question() {
        // Prepare question data
        $question_data = array(
            'campaign_id' => !empty($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null,
            'question_text' => sanitize_textarea_field($_POST['question_text']),
            'question_type' => sanitize_text_field($_POST['question_type']),
            'category' => sanitize_text_field($_POST['category']),
            'difficulty' => sanitize_text_field($_POST['difficulty']),
            'points' => intval($_POST['points']) ?: 1,
            'explanation' => sanitize_textarea_field($_POST['explanation']),
            'options' => array()
        );
        
        // Process options
        if (!empty($_POST['options']) && is_array($_POST['options'])) {
            foreach ($_POST['options'] as $option) {
                if (!empty($option['option_text'])) {
                    $question_data['options'][] = array(
                        'option_text' => sanitize_textarea_field($option['option_text']),
                        'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                        'explanation' => sanitize_textarea_field($option['explanation'] ?? '')
                    );
                }
            }
        }
        
        try {
            if (!empty($_POST['question_id'])) {
                // Update existing question
                $question_id = intval($_POST['question_id']);
                $result = $this->model->update_question($question_id, $question_data);
                $message = 'Question updated successfully!';
                $redirect_args = array('message' => 'updated');
            } else {
                // Create new question
                $result = $this->model->create_question($question_data);
                $question_id = $result;
                $message = 'Question created successfully!';
                $redirect_args = array('message' => 'created');
            }
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Redirect with success message
            wp_redirect(add_query_arg($redirect_args, remove_query_arg(array('action', 'id'))));
            exit;
            
        } catch (Exception $e) {
            // Add error notice
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
    
    /**
     * AJAX: Save question
     */
    public function ajax_save_question() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_question_ajax')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Use the same save logic as form submission
        $_POST['action'] = 'save_question';
        $_POST['vefify_question_nonce'] = $_POST['nonce'];
        
        try {
            ob_start();
            $this->save_question();
            ob_end_clean();
            
            wp_send_json_success(array(
                'message' => 'Question saved successfully!'
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Delete question
     */
    public function ajax_delete_question() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_question_ajax')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $question_id = intval($_POST['question_id'] ?? 0);
        if (!$question_id) {
            wp_send_json_error('Invalid question ID');
        }
        
        $result = $this->model->delete_question($question_id);
        
        if ($result) {
            wp_send_json_success('Question deleted successfully');
        } else {
            wp_send_json_error('Failed to delete question');
        }
    }
    
    /**
     * AJAX: Get question preview
     */
    public function ajax_get_question_preview() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_question_ajax')) {
            wp_send_json_error('Security check failed');
        }
        
        $question_id = intval($_POST['question_id'] ?? 0);
        if (!$question_id) {
            wp_send_json_error('Invalid question ID');
        }
        
        $question = $this->model->get_question($question_id);
        if (!$question) {
            wp_send_json_error('Question not found');
        }
        
        ob_start();
        ?>
        <div class="question-preview">
            <h3><?php echo esc_html($question['question_text']); ?></h3>
            <div class="question-meta">
                <span class="question-type"><?php echo esc_html(ucfirst(str_replace('_', ' ', $question['question_type']))); ?></span>
                <span class="question-difficulty"><?php echo esc_html(ucfirst($question['difficulty'])); ?></span>
                <span class="question-points"><?php echo intval($question['points']); ?> point(s)</span>
            </div>
            <div class="question-options">
                <?php foreach ($question['options'] as $index => $option): ?>
                    <div class="option <?php echo $option['is_correct'] ? 'correct' : ''; ?>">
                        <label>
                            <input type="<?php echo $question['question_type'] === 'multiple_select' ? 'checkbox' : 'radio'; ?>" 
                                   name="preview_answer" value="<?php echo $index; ?>" disabled>
                            <?php echo esc_html($option['option_text']); ?>
                            <?php if ($option['is_correct']): ?>
                                <span class="correct-indicator">✓</span>
                            <?php endif; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($question['explanation']): ?>
                <div class="question-explanation">
                    <strong>Explanation:</strong> <?php echo esc_html($question['explanation']); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Display question statistics
     */
    private function display_question_statistics() {
        $stats = $this->model->get_question_stats();
        
        ?>
        <div class="vefify-stats-summary">
            <h3>Question Bank Summary</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <strong><?php echo number_format($stats['total_questions']); ?></strong>
                    <span>Total Questions</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats['active_questions']); ?></strong>
                    <span>Active Questions</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats['total_categories']); ?></strong>
                    <span>Categories</span>
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
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle admin messages
     */
    private function handle_admin_messages() {
        $message = $_GET['message'] ?? '';
        
        switch ($message) {
            case 'created':
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>Question created successfully!</p></div>';
                });
                break;
                
            case 'updated':
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>Question updated successfully!</p></div>';
                });
                break;
                
            case 'deleted':
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>Question deleted successfully!</p></div>';
                });
                break;
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'vefify') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // Inline styles for question bank
        wp_add_inline_style('wp-admin', '
            .vefify-stats-summary { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-top: 20px; }
            .stats-grid { display: flex; gap: 20px; flex-wrap: wrap; }
            .stat-item { text-align: center; }
            .stat-item strong { display: block; font-size: 1.5em; color: #0073aa; }
            .option-row { margin-bottom: 10px; }
            .option-row input[type="text"] { width: 300px; margin-right: 10px; }
            .difficulty-easy { color: #46b450; }
            .difficulty-medium { color: #ffb900; }
            .difficulty-hard { color: #dc3232; }
            .question-preview .correct { background: #d7eddd; }
            .correct-indicator { color: #46b450; font-weight: bold; }
        ');
        
        // Localize script for AJAX
        wp_localize_script('jquery', 'vefifyQuestionAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_question_ajax'),
            'strings' => array(
                'confirmDelete' => 'Are you sure you want to delete this question?',
                'deleteSuccess' => 'Question deleted successfully',
                'deleteError' => 'Failed to delete question',
                'loading' => 'Loading...'
            )
        ));
    }
    
    /**
     * Get campaigns for filter dropdown
     */
    private function get_campaigns_for_filter() {
        global $wpdb;
        
        // Try to get campaigns table name from database instance
        if ($this->database && method_exists($this->database, 'get_table_name')) {
            $campaigns_table = $this->database->get_table_name('campaigns');
        } else {
            $table_prefix = $wpdb->prefix . (defined('VEFIFY_QUIZ_TABLE_PREFIX') ? VEFIFY_QUIZ_TABLE_PREFIX : 'vefify_');
            $campaigns_table = $table_prefix . 'campaigns';
        }
        
        // Check if campaigns table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$campaigns_table}'");
        if (!$table_exists) {
            return array();
        }
        
        // Get active campaigns
        $campaigns = $wpdb->get_results("
            SELECT id, name 
            FROM {$campaigns_table} 
            WHERE status = 'active' OR status IS NULL
            ORDER BY name
        ", ARRAY_A);
        
        return $campaigns ?: array();
    }
    
    /**
     * Display import page
     */
    private function display_import_page() {
        ?>
        <div class="wrap">
            <h1>Import Questions from CSV</h1>
            <p>Upload a CSV file to import multiple questions at once.</p>
            
            <div class="notice notice-info">
                <p><strong>CSV Format:</strong> question_text, option1, option2, option3, option4, correct_options, category, difficulty</p>
                <p><strong>Example:</strong> "What is 2+2?", "3", "4", "5", "6", "2", "math", "easy"</p>
                <p><strong>Note:</strong> correct_options should be the number(s) of correct options (e.g., "2" for option2, or "2,3" for multiple correct)</p>
            </div>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('vefify_import_questions', 'import_nonce'); ?>
                <input type="hidden" name="action" value="import_questions">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="csv_file">CSV File</label></th>
                        <td>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="import_campaign_id">Assign to Campaign</label></th>
                        <td>
                            <select name="import_campaign_id" id="import_campaign_id">
                                <option value="">No specific campaign</option>
                                <?php foreach ($this->get_campaigns_for_filter() as $campaign): ?>
                                    <option value="<?php echo $campaign['id']; ?>"><?php echo esc_html($campaign['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="Import Questions">
                    <a href="<?php echo remove_query_arg('action'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
        
        // Handle import if form submitted
        if (isset($_POST['action']) && $_POST['action'] === 'import_questions') {
            $this->handle_csv_import();
        }
    }
    
    /**
     * Handle CSV import
     */
    private function handle_csv_import() {
        if (!wp_verify_nonce($_POST['import_nonce'] ?? '', 'vefify_import_questions')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        if (empty($_FILES['csv_file']['tmp_name'])) {
            echo '<div class="notice notice-error"><p>Please select a CSV file to upload.</p></div>';
            return;
        }
        
        $file = $_FILES['csv_file']['tmp_name'];
        $campaign_id = intval($_POST['import_campaign_id']) ?: null;
        
        $handle = fopen($file, 'r');
        if (!$handle) {
            echo '<div class="notice notice-error"><p>Could not read the uploaded file.</p></div>';
            return;
        }
        
        $imported = 0;
        $errors = array();
        $line = 0;
        
        // Skip header row if present
        $first_line = fgetcsv($handle);
        if ($first_line && strtolower($first_line[0]) === 'question_text') {
            $line = 1; // Header skipped
        } else {
            fseek($handle, 0); // Reset to beginning
        }
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $line++;
            
            if (count($data) < 6) {
                $errors[] = "Line {$line}: Insufficient columns (need at least 6)";
                continue;
            }
            
            $question_data = array(
                'campaign_id' => $campaign_id,
                'question_text' => trim($data[0]),
                'question_type' => 'multiple_choice',
                'category' => trim($data[6] ?? 'general'),
                'difficulty' => trim($data[7] ?? 'medium'),
                'points' => 1,
                'explanation' => trim($data[8] ?? ''),
                'options' => array()
            );
            
            // Process options (columns 1-4)
            $correct_options = array_map('trim', explode(',', $data[5]));
            for ($i = 1; $i <= 4; $i++) {
                if (!empty($data[$i])) {
                    $question_data['options'][] = array(
                        'option_text' => trim($data[$i]),
                        'is_correct' => in_array((string)$i, $correct_options) ? 1 : 0
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
        
        // Show results
        if ($imported > 0) {
            echo '<div class="notice notice-success"><p>Successfully imported ' . $imported . ' questions.</p></div>';
        }
        
        if (!empty($errors)) {
            echo '<div class="notice notice-warning"><p>Import completed with ' . count($errors) . ' errors:</p>';
            echo '<ul><li>' . implode('</li><li>', array_slice($errors, 0, 10)) . '</li></ul>';
            if (count($errors) > 10) {
                echo '<p>... and ' . (count($errors) - 10) . ' more errors.</p>';
            }
            echo '</div>';
        }
    }
    
    /**
     * Get model instance (for external access)
     */
    public function get_model() {
        return $this->model;
    }
    
    /**
     * Display categories management page
     */
    private function display_categories_page() {
        $categories = $this->model->get_categories();
        
        ?>
        <div class="wrap">
            <h1>Question Categories</h1>
            
            <?php if (empty($categories)): ?>
                <div class="notice notice-info">
                    <p>No categories found. Categories are automatically created when you add questions.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Question Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): 
                            $count = $this->get_category_question_count($category);
                        ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst($category)); ?></td>
                                <td><?php echo number_format($count); ?> questions</td>
                                <td>
                                    <a href="<?php echo add_query_arg(array('category' => $category), remove_query_arg('action')); ?>" 
                                       class="button button-small">View Questions</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <p><a href="<?php echo remove_query_arg('action'); ?>" class="button">Back to Questions</a></p>
        </div>
        <?php
    }
    
    /**
     * Get question count for a category
     */
    private function get_category_question_count($category) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$this->model->debug_table_status()['questions_table']} 
            WHERE category = %s AND is_active = 1
        ", $category)) ?: 0;
    }
}