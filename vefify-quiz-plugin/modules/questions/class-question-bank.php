<?php
/**
 * Streamlined Question Bank Admin
 * File: modules/questions/class-question-bank.php
 * 
 * Handles admin interface with unified form for add/edit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Bank {
    
    private $model;
    
    public function __construct() {
        $this->model = new Vefify_Question_Model();
        
        // Hook into WordPress admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_action('wp_ajax_vefify_delete_question', array($this, 'ajax_delete_question'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on question pages
        if (strpos($hook, 'vefify-questions') === false) {
            return;
        }
        
        wp_enqueue_script(
            'vefify-questions-admin',
            plugin_dir_url(__FILE__) . 'assets/questions-admin.js',
            array('jquery'),
            VEFIFY_QUIZ_VERSION,
            true
        );
        
        wp_localize_script('vefify-questions-admin', 'vefifyQuestions', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_questions_admin'),
            'strings' => array(
                'deleteConfirm' => __('Are you sure you want to delete this question?', 'vefify-quiz'),
                'addOption' => __('Add Option', 'vefify-quiz'),
                'removeOption' => __('Remove Option', 'vefify-quiz'),
                'questionRequired' => __('Question text is required', 'vefify-quiz'),
                'optionsRequired' => __('At least 2 options are required', 'vefify-quiz'),
                'correctRequired' => __('At least one correct answer is required', 'vefify-quiz')
            )
        ));
        
        wp_enqueue_style(
            'vefify-questions-admin',
            plugin_dir_url(__FILE__) . 'assets/questions-admin.css',
            array(),
            VEFIFY_QUIZ_VERSION
        );
    }
    
    /**
     * Main admin page router
     */
    public function admin_page() {
        $action = $_GET['action'] ?? 'list';
        $question_id = intval($_GET['question_id'] ?? 0);
        
        echo '<div class="wrap vefify-questions-wrap">';
        
        switch ($action) {
            case 'new':
            case 'edit':
                $this->render_question_form($question_id);
                break;
            default:
                $this->render_questions_list();
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Render questions list page
     */
    private function render_questions_list() {
        // Get filters
        $campaign_id = intval($_GET['campaign_id'] ?? 0);
        $category = sanitize_text_field($_GET['category'] ?? '');
        $difficulty = sanitize_text_field($_GET['difficulty'] ?? '');
        $search = sanitize_text_field($_GET['search'] ?? '');
        $page = max(1, intval($_GET['paged'] ?? 1));
        
        // Get questions
        $args = array(
            'page' => $page,
            'per_page' => 20,
            'search' => $search
        );
        
        if ($campaign_id) $args['campaign_id'] = $campaign_id;
        if ($category) $args['category'] = $category;
        if ($difficulty) $args['difficulty'] = $difficulty;
        
        $result = $this->model->get_questions($args);
        $questions = $result['questions'];
        
        // Get filter options
        $campaigns = $this->model->get_campaigns();
        $categories = $this->model->get_categories();
        $stats = $this->model->get_statistics();
        
        ?>
        <h1 class="wp-heading-inline">
            ❓ Question Bank 
            <span class="count">(<?php echo number_format($result['total']); ?> questions)</span>
        </h1>
        <a href="<?php echo esc_url(add_query_arg('action', 'new')); ?>" class="page-title-action">
            Add New Question
        </a>
        <hr class="wp-header-end">
        
        <!-- Quick Stats -->
        <div class="vefify-stats">
            <div class="stat-item">
                <strong><?php echo $stats['active_questions'] ?? 0; ?></strong>
                <span>Active Questions</span>
            </div>
            <div class="stat-item">
                <strong><?php echo $stats['total_categories'] ?? 0; ?></strong>
                <span>Categories</span>
            </div>
            <div class="stat-item">
                <strong><?php echo ($stats['easy_questions'] ?? 0) + ($stats['medium_questions'] ?? 0) + ($stats['hard_questions'] ?? 0); ?></strong>
                <span>Total Questions</span>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="tablenav top">
            <form method="get" class="vefify-filters">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                
                <select name="campaign_id">
                    <option value="">All Campaigns</option>
                    <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?php echo $campaign->id; ?>" <?php selected($campaign_id, $campaign->id); ?>>
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
                
                <input type="search" name="search" value="<?php echo esc_attr($search); ?>" 
                       placeholder="Search questions...">
                
                <button type="submit" class="button">Filter</button>
                
                <?php if ($campaign_id || $category || $difficulty || $search): ?>
                    <a href="<?php echo esc_url(remove_query_arg(array('campaign_id', 'category', 'difficulty', 'search', 'paged'))); ?>" 
                       class="button">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Questions Table -->
        <?php if ($questions): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-question" style="width: 40%;">Question</th>
                        <th class="column-type" style="width: 15%;">Type</th>
                        <th class="column-category" style="width: 15%;">Category</th>
                        <th class="column-difficulty" style="width: 10%;">Difficulty</th>
                        <th class="column-campaign" style="width: 15%;">Campaign</th>
                        <th class="column-actions" style="width: 5%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $question): ?>
                        <tr>
                            <td class="column-question">
                                <strong>
                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'question_id' => $question->id))); ?>">
                                        <?php echo esc_html(wp_trim_words($question->question_text, 10)); ?>
                                    </a>
                                </strong>
                                <div class="question-meta">
                                    <?php echo $question->points; ?> point<?php echo $question->points != 1 ? 's' : ''; ?> • 
                                    <?php echo count($question->options); ?> options •
                                    <?php echo $question->is_active ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Inactive</span>'; ?>
                                </div>
                            </td>
                            <td class="column-type">
                                <span class="type-badge type-<?php echo esc_attr($question->question_type); ?>">
                                    <?php echo esc_html($this->format_question_type($question->question_type)); ?>
                                </span>
                            </td>
                            <td class="column-category">
                                <?php if ($question->category): ?>
                                    <span class="category-badge"><?php echo esc_html(ucfirst($question->category)); ?></span>
                                <?php else: ?>
                                    <span class="no-category">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-difficulty">
                                <span class="difficulty-badge difficulty-<?php echo esc_attr($question->difficulty); ?>">
                                    <?php echo esc_html(ucfirst($question->difficulty)); ?>
                                </span>
                            </td>
                            <td class="column-campaign">
                                <?php if ($question->campaign_name): ?>
                                    <?php echo esc_html($question->campaign_name); ?>
                                <?php else: ?>
                                    <span class="no-campaign">General</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-actions">
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'question_id' => $question->id))); ?>">
                                            Edit
                                        </a> |
                                    </span>
                                    <span class="delete">
                                        <a href="#" class="delete-question submitdelete" data-question-id="<?php echo $question->id; ?>">
                                            Delete
                                        </a>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($result['pages'] > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo; Previous',
                            'next_text' => 'Next &raquo;',
                            'total' => $result['pages'],
                            'current' => $page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="vefify-empty-state">
                <h3>No questions found</h3>
                <p>Start building your question bank by adding your first question.</p>
                <a href="<?php echo esc_url(add_query_arg('action', 'new')); ?>" class="button button-primary">
                    Add First Question
                </a>
            </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render unified question form (for both add and edit)
     */
    private function render_question_form($question_id = null) {
        $is_edit = (bool) $question_id;
        $question = null;
        
        if ($is_edit) {
            $question = $this->model->get_question($question_id);
            if (!$question) {
                echo '<div class="notice notice-error"><p>Question not found.</p></div>';
                return;
            }
        }
        
        $campaigns = $this->model->get_campaigns();
        $categories = $this->model->get_categories();
        
        ?>
        <h1 class="wp-heading-inline">
            <?php echo $is_edit ? 'Edit Question' : 'Add New Question'; ?>
        </h1>
        <a href="<?php echo esc_url(remove_query_arg(array('action', 'question_id'))); ?>" class="page-title-action">
            ← Back to Questions
        </a>
        <hr class="wp-header-end">
        
        <form method="post" action="" class="vefify-question-form" id="question-form">
            <?php wp_nonce_field('vefify_save_question', 'vefify_question_nonce'); ?>
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'update_question' : 'create_question'; ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="question_id" value="<?php echo $question->id; ?>">
            <?php endif; ?>
            
            <div class="form-layout">
                <!-- Main Content -->
                <div class="form-main">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="question_text">Question Text *</label>
                            </th>
                            <td>
                                <textarea id="question_text" name="question_text" rows="4" class="large-text" required 
                                         placeholder="Enter your question here..."><?php echo $is_edit ? esc_textarea($question->question_text) : ''; ?></textarea>
                                <p class="description">The main question that participants will answer.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="question_type">Question Type *</label>
                            </th>
                            <td>
                                <select id="question_type" name="question_type" required>
                                    <option value="multiple_choice" <?php echo $is_edit && $question->question_type === 'multiple_choice' ? 'selected' : ''; ?>>
                                        Multiple Choice (Single Answer)
                                    </option>
                                    <option value="multiple_select" <?php echo $is_edit && $question->question_type === 'multiple_select' ? 'selected' : ''; ?>>
                                        Multiple Select (Multiple Answers)
                                    </option>
                                    <option value="true_false" <?php echo $is_edit && $question->question_type === 'true_false' ? 'selected' : ''; ?>>
                                        True/False
                                    </option>
                                </select>
                                <p class="description">Choose how participants can answer this question.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label>Answer Options *</label>
                            </th>
                            <td>
                                <div id="question-options" class="question-options">
                                    <?php if ($is_edit && $question->options): ?>
                                        <?php foreach ($question->options as $index => $option): ?>
                                            <div class="option-row" data-index="<?php echo $index; ?>">
                                                <label class="option-correct-label">
                                                    <input type="checkbox" name="options[<?php echo $index; ?>][is_correct]" 
                                                           value="1" <?php checked($option->is_correct, 1); ?> 
                                                           class="option-correct">
                                                    Correct
                                                </label>
                                                <input type="text" name="options[<?php echo $index; ?>][option_text]" 
                                                       value="<?php echo esc_attr($option->option_text); ?>" 
                                                       class="option-text regular-text" placeholder="Option text..." required>
                                                <button type="button" class="remove-option button">Remove</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="option-row" data-index="0">
                                            <label class="option-correct-label">
                                                <input type="checkbox" name="options[0][is_correct]" value="1" class="option-correct">
                                                Correct
                                            </label>
                                            <input type="text" name="options[0][option_text]" class="option-text regular-text" 
                                                   placeholder="Option text..." required>
                                            <button type="button" class="remove-option button">Remove</button>
                                        </div>
                                        <div class="option-row" data-index="1">
                                            <label class="option-correct-label">
                                                <input type="checkbox" name="options[1][is_correct]" value="1" class="option-correct">
                                                Correct
                                            </label>
                                            <input type="text" name="options[1][option_text]" class="option-text regular-text" 
                                                   placeholder="Option text..." required>
                                            <button type="button" class="remove-option button">Remove</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button type="button" id="add-option" class="button">Add Option</button>
                                <p class="description">
                                    Check the box next to correct answer(s). For multiple choice, select one. 
                                    For multiple select, you can select several.
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="explanation">Explanation</label>
                            </th>
                            <td>
                                <textarea id="explanation" name="explanation" rows="3" class="large-text"
                                         placeholder="Optional explanation shown after answering..."><?php echo $is_edit ? esc_textarea($question->explanation) : ''; ?></textarea>
                                <p class="description">Optional explanation displayed to participants after they answer.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Sidebar -->
                <div class="form-sidebar">
                    <div class="submitdiv">
                        <div class="submitbox">
                            <div class="major-publishing-actions">
                                <div class="publishing-action">
                                    <input type="submit" name="save_question" id="save-question" 
                                           class="button button-primary button-large" 
                                           value="<?php echo $is_edit ? 'Update Question' : 'Save Question'; ?>">
                                </div>
                                <div class="clear"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Question Settings -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2>Question Settings</h2>
                        </div>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row">
                                        <label for="campaign_id">Campaign</label>
                                    </th>
                                    <td>
                                        <select id="campaign_id" name="campaign_id">
                                            <option value="">General (No Campaign)</option>
                                            <?php foreach ($campaigns as $campaign): ?>
                                                <option value="<?php echo $campaign->id; ?>" 
                                                       <?php echo $is_edit && $question->campaign_id == $campaign->id ? 'selected' : ''; ?>>
                                                    <?php echo esc_html($campaign->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="category">Category</label>
                                    </th>
                                    <td>
                                        <input type="text" id="category" name="category" list="category-list"
                                               value="<?php echo $is_edit ? esc_attr($question->category) : ''; ?>" 
                                               placeholder="e.g., Health, Science">
                                        <datalist id="category-list">
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo esc_attr($cat); ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="difficulty">Difficulty</label>
                                    </th>
                                    <td>
                                        <select id="difficulty" name="difficulty">
                                            <option value="easy" <?php echo $is_edit && $question->difficulty === 'easy' ? 'selected' : ''; ?>>Easy</option>
                                            <option value="medium" <?php echo $is_edit && $question->difficulty === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="hard" <?php echo $is_edit && $question->difficulty === 'hard' ? 'selected' : ''; ?>>Hard</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="points">Points</label>
                                    </th>
                                    <td>
                                        <input type="number" id="points" name="points" min="1" max="10" 
                                               value="<?php echo $is_edit ? $question->points : 1; ?>">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Status</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="is_active" value="1" 
                                                   <?php echo !$is_edit || $question->is_active ? 'checked' : ''; ?>>
                                            Active (question will appear in quizzes)
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Option Template -->
        <script type="text/template" id="option-template">
            <div class="option-row" data-index="{{index}}">
                <label class="option-correct-label">
                    <input type="checkbox" name="options[{{index}}][is_correct]" value="1" class="option-correct">
                    Correct
                </label>
                <input type="text" name="options[{{index}}][option_text]" class="option-text regular-text" 
                       placeholder="Option text..." required>
                <button type="button" class="remove-option button">Remove</button>
            </div>
        </script>
        <?php
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!isset($_POST['action']) || !wp_verify_nonce($_POST['vefify_question_nonce'] ?? '', 'vefify_save_question')) {
            return;
        }
        
        $action = $_POST['action'];
        
        switch ($action) {
            case 'create_question':
                $this->handle_create_question();
                break;
            case 'update_question':
                $this->handle_update_question();
                break;
        }
    }
    
    /**
     * Handle create question
     */
    private function handle_create_question() {
        $data = $this->sanitize_question_data($_POST);
        $result = $this->model->create_question($data);
        
        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $this->add_admin_notice('Question created successfully!', 'success');
            
            $redirect_url = add_query_arg(array(
                'action' => 'edit',
                'question_id' => $result,
                'message' => 'created'
            ), remove_query_arg(array('action')));
            
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Handle update question
     */
    private function handle_update_question() {
        $question_id = intval($_POST['question_id']);
        $data = $this->sanitize_question_data($_POST);
        $result = $this->model->update_question($question_id, $data);
        
        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $this->add_admin_notice('Question updated successfully!', 'success');
            
            wp_redirect(add_query_arg('message', 'updated'));
            exit;
        }
    }
    
    /**
     * Sanitize question data from form
     */
    private function sanitize_question_data($data) {
        $sanitized = array(
            'question_text' => sanitize_textarea_field($data['question_text'] ?? ''),
            'question_type' => sanitize_text_field($data['question_type'] ?? 'multiple_choice'),
            'category' => sanitize_text_field($data['category'] ?? ''),
            'difficulty' => sanitize_text_field($data['difficulty'] ?? 'medium'),
            'points' => intval($data['points'] ?? 1),
            'explanation' => sanitize_textarea_field($data['explanation'] ?? ''),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'campaign_id' => !empty($data['campaign_id']) ? intval($data['campaign_id']) : null,
            'options' => array()
        );
        
        // Sanitize options
        if (!empty($data['options']) && is_array($data['options'])) {
            foreach ($data['options'] as $option) {
                if (!empty($option['option_text'])) {
                    $sanitized['options'][] = array(
                        'option_text' => sanitize_textarea_field($option['option_text']),
                        'is_correct' => !empty($option['is_correct']) ? 1 : 0
                    );
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX: Delete question
     */
    public function ajax_delete_question() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_questions_admin')) {
            wp_send_json_error('Security check failed');
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
     * Add admin notice
     */
    private function add_admin_notice($message, $type = 'info') {
        set_transient('vefify_admin_notice', array(
            'message' => $message,
            'type' => $type
        ), 30);
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        $notice = get_transient('vefify_admin_notice');
        
        if ($notice) {
            delete_transient('vefify_admin_notice');
            
            $class = 'notice notice-' . $notice['type'] . ' is-dismissible';
            echo '<div class="' . $class . '"><p>' . esc_html($notice['message']) . '</p></div>';
        }
    }
    
    /**
     * Format question type for display
     */
    private function format_question_type($type) {
        switch ($type) {
            case 'multiple_choice':
                return 'Multiple Choice';
            case 'multiple_select':
                return 'Multiple Select';
            case 'true_false':
                return 'True/False';
            default:
                return ucfirst(str_replace('_', ' ', $type));
        }
    }
    
    /**
     * Get analytics summary for centralized dashboard
     */
    public function get_analytics_summary() {
        $stats = $this->model->get_statistics();
        
        return array(
            'title' => 'Question Bank',
            'icon' => '❓',
            'stats' => array(
                'total_questions' => array(
                    'label' => 'Total Questions',
                    'value' => $stats['total_questions'] ?? 0,
                    'trend' => 'Active: ' . ($stats['active_questions'] ?? 0)
                ),
                'categories' => array(
                    'label' => 'Categories',
                    'value' => $stats['total_categories'] ?? 0,
                    'trend' => 'Well organized'
                ),
                'difficulty_mix' => array(
                    'label' => 'Difficulty Balance',
                    'value' => 'Mixed',
                    'trend' => sprintf(
                        'Easy: %d, Medium: %d, Hard: %d',
                        $stats['easy_questions'] ?? 0,
                        $stats['medium_questions'] ?? 0,
                        $stats['hard_questions'] ?? 0
                    )
                )
            ),
            'quick_actions' => array(
                array(
                    'label' => 'Add Question',
                    'url' => admin_url('admin.php?page=vefify-questions&action=new'),
                    'icon' => '➕'
                ),
                array(
                    'label' => 'View All',
                    'url' => admin_url('admin.php?page=vefify-questions'),
                    'icon' => '👁️'
                )
            )
        );
    }
}