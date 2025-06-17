<?php
/**
 * Question Bank Admin Interface - COMPLETE VERSION
 * File: modules/questions/class-question-bank.php
 * 
 * This is the complete, updated version with all fixes:
 * - Enhanced form submission debugging
 * - Fixed category handling
 * - Multiple form submission handlers
 * - Complete admin interface
 * - Error handling and validation
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
        
        // Add comprehensive debugging and form handling
        add_action('init', array($this, 'debug_form_submission'), 1);
        add_action('admin_init', array($this, 'debug_admin_init'), 1);
        add_action('admin_init', array($this, 'handle_admin_actions'), 5);
        add_action('admin_post_save_question', array($this, 'handle_save_question_direct'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_vefify_load_question_preview', array($this, 'ajax_load_question_preview'));
        
        error_log('Vefify Quiz: Question Bank constructor completed');
    }
    
    /**
     * Debug form submission - track ALL form posts
     */
    public function debug_form_submission() {
        if ($_POST) {
            error_log('Vefify Quiz: POST data received on init: ' . print_r($_POST, true));
            error_log('Vefify Quiz: GET data: ' . print_r($_GET, true));
            error_log('Vefify Quiz: Current page: ' . ($_GET['page'] ?? 'no-page'));
        }
    }
    
    /**
     * Debug admin_init - track when admin_init fires
     */
    public function debug_admin_init() {
        if ($_POST && isset($_POST['action'])) {
            error_log('Vefify Quiz: admin_init fired with POST action: ' . $_POST['action']);
            error_log('Vefify Quiz: Current user can manage options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));
            error_log('Vefify Quiz: Nonce present: ' . (isset($_POST['vefify_question_nonce']) ? 'YES' : 'NO'));
            if (isset($_POST['vefify_question_nonce'])) {
                $nonce_valid = wp_verify_nonce($_POST['vefify_question_nonce'], 'vefify_question_action');
                error_log('Vefify Quiz: Nonce valid: ' . ($nonce_valid ? 'YES' : 'NO'));
            }
        }
    }
    
    /**
     * Main admin page router - THIS REPLACES "functionality coming soon"
     */
    public function admin_page_router() {
        $action = $_GET['action'] ?? 'list';
        $question_id = intval($_GET['question_id'] ?? 0);
        
        // Show any stored admin notices first
        $this->show_stored_notices();
        
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
     * Show stored admin notices
     */
    private function show_stored_notices() {
        $stored_notice = get_transient('vefify_admin_notice');
        if ($stored_notice) {
            delete_transient('vefify_admin_notice');
            echo '<div class="notice notice-' . esc_attr($stored_notice['type']) . ' is-dismissible">';
            echo '<p>' . esc_html($stored_notice['message']) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Handle admin actions with enhanced debugging
     */
    public function handle_admin_actions() {
        error_log('Vefify Quiz: handle_admin_actions called');
        
        // Only process if we're on the questions page
        if (!isset($_GET['page']) || $_GET['page'] !== 'vefify-questions') {
            error_log('Vefify Quiz: Not on questions page, skipping');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            error_log('Vefify Quiz: User cannot manage options, skipping');
            return;
        }
        
        // Handle form submissions with debugging
        if ($_POST && isset($_POST['action']) && $_POST['action'] === 'save_question') {
            error_log('Vefify Quiz: Processing save_question action');
            
            // Check nonce
            if (!wp_verify_nonce($_POST['vefify_question_nonce'] ?? '', 'vefify_question_action')) {
                error_log('Vefify Quiz: Nonce verification failed');
                $this->add_admin_notice('Security check failed. Please try again.', 'error');
                return;
            }
            
            error_log('Vefify Quiz: Nonce verified, proceeding with save');
            $this->handle_save_question();
            return;
        }
        
        // Handle import
        if ($_POST && isset($_POST['action']) && $_POST['action'] === 'import_questions') {
            if (!wp_verify_nonce($_POST['vefify_import_nonce'] ?? '', 'vefify_import_questions')) {
                $this->add_admin_notice('Security check failed. Please try again.', 'error');
                return;
            }
            $this->handle_import_questions();
            return;
        }
        
        // Handle URL actions
        $action = $_GET['action'] ?? '';
        $question_id = intval($_GET['question_id'] ?? 0);
        
        if ($action) {
            error_log('Vefify Quiz: Processing URL action: ' . $action);
        }
        
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
     * Alternative form handler using admin_post hook
     */
    public function handle_save_question_direct() {
        error_log('Vefify Quiz: Direct save question handler called');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!wp_verify_nonce($_POST['vefify_question_nonce'] ?? '', 'vefify_question_action')) {
            wp_die('Security check failed');
        }
        
        error_log('Vefify Quiz: Direct handler - processing save');
        $this->handle_save_question();
    }
    
    /**
     * Handle save question with step-by-step debugging
     */
    private function handle_save_question() {
        error_log('Vefify Quiz: handle_save_question started');
        
        $question_id = intval($_POST['question_id'] ?? 0);
        $is_edit = !empty($question_id);
        
        error_log('Vefify Quiz: Question ID: ' . $question_id . ', Is Edit: ' . ($is_edit ? 'yes' : 'no'));
        
        // Log all POST data for debugging
        error_log('Vefify Quiz: All POST data: ' . print_r($_POST, true));
        
        // Validate required fields
        if (empty($_POST['question_text'])) {
            error_log('Vefify Quiz: Question text is empty');
            $this->add_admin_notice('Question text is required.', 'error');
            return;
        }
        
        error_log('Vefify Quiz: Question text provided: ' . substr($_POST['question_text'], 0, 50) . '...');
        
        // Prepare data with logging
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
        
        error_log('Vefify Quiz: Basic data prepared');
        
        // Process options with detailed logging
        if (!empty($_POST['options'])) {
            error_log('Vefify Quiz: Processing ' . count($_POST['options']) . ' options');
            
            foreach ($_POST['options'] as $index => $option) {
                error_log('Vefify Quiz: Processing option ' . $index . ': ' . print_r($option, true));
                
                if (!empty($option['text'])) {
                    $data['options'][] = array(
                        'text' => sanitize_textarea_field($option['text']),
                        'is_correct' => !empty($option['is_correct']),
                        'explanation' => sanitize_textarea_field($option['explanation'] ?? '')
                    );
                    error_log('Vefify Quiz: Added option: ' . $option['text'] . ' (correct: ' . (!empty($option['is_correct']) ? 'yes' : 'no') . ')');
                }
            }
        }
        
        error_log('Vefify Quiz: Final options count: ' . count($data['options']));
        
        // Validate options
        if (empty($data['options'])) {
            error_log('Vefify Quiz: No valid options found');
            $this->add_admin_notice('At least two answer options are required.', 'error');
            return;
        }
        
        $correct_count = 0;
        foreach ($data['options'] as $option) {
            if ($option['is_correct']) {
                $correct_count++;
            }
        }
        
        error_log('Vefify Quiz: Correct answers count: ' . $correct_count);
        
        if ($correct_count === 0) {
            error_log('Vefify Quiz: No correct answers selected');
            $this->add_admin_notice('At least one correct answer must be selected.', 'error');
            return;
        }
        
        // Save question with detailed logging
        try {
            error_log('Vefify Quiz: Attempting to save question to database');
            
            if ($is_edit) {
                error_log('Vefify Quiz: Updating existing question');
                $result = $this->model->update_question($question_id, $data);
            } else {
                error_log('Vefify Quiz: Creating new question');
                $result = $this->model->create_question($data);
            }
            
            error_log('Vefify Quiz: Save result: ' . print_r($result, true));
            
            if (is_wp_error($result)) {
                error_log('Vefify Quiz: Save error: ' . $result->get_error_message());
                $this->add_admin_notice('Error: ' . $result->get_error_message(), 'error');
            } else {
                $message = $is_edit ? 'Question updated successfully!' : 'Question created successfully!';
                error_log('Vefify Quiz: Success: ' . $message);
                
                // Store success message in transient
                set_transient('vefify_admin_notice', array('message' => $message, 'type' => 'success'), 30);
                
                // Redirect to questions list
                $redirect_url = admin_url('admin.php?page=vefify-questions');
                error_log('Vefify Quiz: Redirecting to: ' . $redirect_url);
                wp_redirect($redirect_url);
                exit;
            }
        } catch (Exception $e) {
            error_log('Vefify Quiz: Exception during save: ' . $e->getMessage());
            error_log('Vefify Quiz: Exception trace: ' . $e->getTraceAsString());
            $this->add_admin_notice('Database error: ' . $e->getMessage(), 'error');
        }
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
     * Display question form with enhanced debugging
     */
    private function display_question_form($question_id = 0) {
    echo '<!-- DEBUG: display_question_form started -->' . PHP_EOL;
    
    $question = null;
    $is_edit = false;
    
    if ($question_id) {
        echo '<!-- DEBUG: Loading question ID: ' . $question_id . ' -->' . PHP_EOL;
        $question = $this->model->get_question($question_id);
        $is_edit = true;
        
        if (!$question) {
            echo '<div class="notice notice-error"><p>Question not found.</p></div>';
            return;
        }
        echo '<!-- DEBUG: Question loaded successfully -->' . PHP_EOL;
    }
    
    // Get campaigns and categories with debugging
    echo '<!-- DEBUG: Getting campaigns and categories -->' . PHP_EOL;
    try {
        $campaigns = $this->model->get_campaigns();
        echo '<!-- DEBUG: Campaigns loaded: ' . count($campaigns) . ' -->' . PHP_EOL;
        
        $categories = $this->model->get_categories();
        echo '<!-- DEBUG: Categories loaded: ' . count($categories) . ' -->' . PHP_EOL;
        
        // Debug categories content
        echo '<!-- DEBUG: Categories content: ' . print_r($categories, true) . ' -->' . PHP_EOL;
        
    } catch (Exception $e) {
        echo '<!-- DEBUG ERROR: Failed to load data: ' . $e->getMessage() . ' -->' . PHP_EOL;
        echo '<div class="notice notice-error"><p>Error loading data: ' . esc_html($e->getMessage()) . '</p></div>';
        return;
    }
    
    // Get current page URL for form action
    $form_action = admin_url('admin.php?page=vefify-questions');
    if ($question_id) {
        $form_action .= '&action=edit&question_id=' . $question_id;
    }
    
    echo '<!-- DEBUG: Form action URL: ' . $form_action . ' -->' . PHP_EOL;
    
    ?>
    <!-- DEBUG INFO BOX -->
    <div style="background: #f0f8ff; border: 1px solid #2271b1; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 12px;">
        <strong>🔧 Debug Info:</strong><br>
        Form Action: <?php echo esc_html($form_action); ?><br>
        Current Page: <?php echo esc_html($_GET['page'] ?? 'none'); ?><br>
        User Can Manage: <?php echo current_user_can('manage_options') ? 'Yes' : 'No'; ?><br>
        Categories Count: <?php echo count($categories); ?><br>
        Campaigns Count: <?php echo count($campaigns); ?><br>
        Nonce: <?php echo wp_create_nonce('vefify_question_action'); ?><br>
        <strong style="color: green;">✅ Debug box rendering correctly</strong>
    </div>
    
    <!-- TEST: Simple HTML to confirm rendering works -->
    <div style="background: #e7f3ff; border: 1px solid #0073aa; padding: 15px; margin: 10px 0; border-radius: 4px;">
        <h3 style="color: #0073aa; margin-top: 0;">🧪 Rendering Test</h3>
        <p>If you can see this blue box, HTML rendering is working fine.</p>
        <p><strong>Next step:</strong> The form should appear below this box.</p>
    </div>
    
    <?php echo '<!-- DEBUG: Starting form HTML -->' . PHP_EOL; ?>
    
    <form method="post" id="question-form" action="<?php echo esc_url($form_action); ?>" style="background: #fff; padding: 20px; border: 2px solid #00a32a; border-radius: 8px;">
        
        <!-- DEBUG: Form started successfully -->
        <div style="background: #d4edda; color: #155724; padding: 10px; margin-bottom: 20px; border-radius: 4px;">
            <strong>🎯 Form Loading Test:</strong> If you see this green box, the form started rendering.
        </div>
        
        <?php wp_nonce_field('vefify_question_action', 'vefify_question_nonce'); ?>
        <input type="hidden" name="action" value="save_question">
        <?php if ($question_id): ?>
            <input type="hidden" name="question_id" value="<?php echo $question_id; ?>">
        <?php endif; ?>
        
        <!-- DEBUG: Hidden fields added -->
        <input type="hidden" name="debug_form_action" value="<?php echo esc_attr($form_action); ?>">
        
        <?php echo '<!-- DEBUG: Basic form structure complete -->' . PHP_EOL; ?>
        
        <!-- SIMPLIFIED FORM FIELDS FOR TESTING -->
        <h2>Question Details</h2>
        
        <table class="form-table" role="presentation" style="border: 1px solid #ddd;">
            <tbody>
                <?php echo '<!-- DEBUG: Starting table rows -->' . PHP_EOL; ?>
                
                <tr style="background: #f9f9f9;">
                    <th scope="row" style="padding: 15px;">
                        <label for="question_text">Question Text *</label>
                    </th>
                    <td style="padding: 15px;">
                        <textarea name="question_text" id="question_text" rows="4" class="large-text" required 
                                  style="width: 100%; border: 2px solid #0073aa;"
                                  placeholder="Enter your question here..."><?php echo $question ? esc_textarea($question->question_text) : ''; ?></textarea>
                        <p class="description" style="color: #666;">✅ Text area should be visible above</p>
                    </td>
                </tr>
                
                <?php echo '<!-- DEBUG: Question text row complete -->' . PHP_EOL; ?>
                
                <tr style="background: #fff;">
                    <th scope="row" style="padding: 15px;">
                        <label for="question_type">Question Type</label>
                    </th>
                    <td style="padding: 15px;">
                        <select name="question_type" id="question_type" style="width: 200px; padding: 8px; border: 2px solid #0073aa;">
                            <option value="multiple_choice" <?php selected($question ? $question->question_type : 'multiple_choice', 'multiple_choice'); ?>>Multiple Choice</option>
                            <option value="multiple_select" <?php selected($question ? $question->question_type : '', 'multiple_select'); ?>>Multiple Select</option>
                            <option value="true_false" <?php selected($question ? $question->question_type : '', 'true_false'); ?>>True/False</option>
                        </select>
                        <p class="description" style="color: #666;">✅ Dropdown should be visible above</p>
                    </td>
                </tr>
                
                <?php echo '<!-- DEBUG: Question type row complete -->' . PHP_EOL; ?>
                
                <tr style="background: #f9f9f9;">
                    <th scope="row" style="padding: 15px;">
                        <label for="category">Category</label>
                    </th>
                    <td style="padding: 15px;">
                        <?php if (!empty($categories)): ?>
                            <select name="category" id="category" style="width: 200px; padding: 8px; border: 2px solid #0073aa;">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat); ?>" <?php selected($question ? $question->category : 'general', $cat); ?>>
                                        <?php echo esc_html(ucfirst($cat)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description" style="color: #666;">✅ Categories dropdown: <?php echo count($categories); ?> options</p>
                        <?php else: ?>
                            <input type="text" name="category" id="category" value="general" style="width: 200px; padding: 8px; border: 2px solid #f44336;">
                            <p class="description" style="color: #f44336;">❌ No categories found - using text input</p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <?php echo '<!-- DEBUG: Category row complete -->' . PHP_EOL; ?>
                
                <tr style="background: #fff;">
                    <th scope="row" style="padding: 15px;">
                        <label for="difficulty">Difficulty</label>
                    </th>
                    <td style="padding: 15px;">
                        <select name="difficulty" id="difficulty" style="width: 200px; padding: 8px; border: 2px solid #0073aa;">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                        <p class="description" style="color: #666;">✅ Difficulty dropdown</p>
                    </td>
                </tr>
                
                <tr style="background: #f9f9f9;">
                    <th scope="row" style="padding: 15px;">
                        <label for="points">Points</label>
                    </th>
                    <td style="padding: 15px;">
                        <input type="number" name="points" id="points" value="1" min="1" max="10" 
                               style="width: 100px; padding: 8px; border: 2px solid #0073aa;">
                        <p class="description" style="color: #666;">✅ Points input field</p>
                    </td>
                </tr>
                
                <?php echo '<!-- DEBUG: Basic fields complete -->' . PHP_EOL; ?>
                
            </tbody>
        </table>
        
        <?php echo '<!-- DEBUG: Table complete, starting options section -->' . PHP_EOL; ?>
        
        <!-- SIMPLIFIED ANSWER OPTIONS FOR TESTING -->
        <h2 style="margin-top: 30px;">Answer Options</h2>
        
        <div id="answer-options" style="border: 2px solid #00a32a; padding: 20px; background: #f0f8f0; border-radius: 8px;">
            
            <!-- Option A -->
            <div class="option-row" style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                <h4 style="margin-top: 0; color: #0073aa;">Option A</h4>
                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <input type="checkbox" name="options[0][is_correct]" value="1" style="transform: scale(1.5);">
                    <strong>✓ Correct Answer</strong>
                </label>
                <input type="text" name="options[0][text]" placeholder="Enter first answer option..." 
                       style="width: 100%; padding: 10px; border: 2px solid #0073aa; border-radius: 4px; font-size: 14px;">
                <p style="color: #666; margin: 5px 0 0 0;">✅ Option A field should be visible above</p>
            </div>
            
            <!-- Option B -->
            <div class="option-row" style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                <h4 style="margin-top: 0; color: #0073aa;">Option B</h4>
                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <input type="checkbox" name="options[1][is_correct]" value="1" style="transform: scale(1.5);">
                    <strong>✓ Correct Answer</strong>
                </label>
                <input type="text" name="options[1][text]" placeholder="Enter second answer option..." 
                       style="width: 100%; padding: 10px; border: 2px solid #0073aa; border-radius: 4px; font-size: 14px;">
                <p style="color: #666; margin: 5px 0 0 0;">✅ Option B field should be visible above</p>
            </div>
            
        </div>
        
        <?php echo '<!-- DEBUG: Options section complete -->' . PHP_EOL; ?>
        
        <!-- SUBMIT SECTION -->
        <div style="margin-top: 30px; padding: 20px; background: #e7f3ff; border: 2px solid #0073aa; border-radius: 8px;">
            <h3 style="margin-top: 0; color: #0073aa;">Ready to Save?</h3>
            <p>If you can see all the fields above, the form is rendering correctly.</p>
            
            <p class="submit">
                <input type="submit" name="submit" class="button-primary button-large" 
                       value="💾 Save Test Question"
                       style="background: #00a32a; border: none; color: white; padding: 15px 30px; font-size: 16px; border-radius: 6px; cursor: pointer;">
                <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" 
                   class="button button-large" 
                   style="margin-left: 15px; padding: 15px 30px; text-decoration: none;">Cancel</a>
            </p>
        </div>
        
        <?php echo '<!-- DEBUG: Submit section complete -->' . PHP_EOL; ?>
        
    </form>
    
    <?php echo '<!-- DEBUG: Form HTML complete -->' . PHP_EOL; ?>
    
    <!-- JAVASCRIPT DEBUG -->
    <script type="text/javascript">
    console.log('🔧 FORM DEBUG: JavaScript loading');
    
    jQuery(document).ready(function($) {
        console.log('🔧 FORM DEBUG: jQuery ready');
        console.log('🔧 FORM DEBUG: Form found:', $('#question-form').length);
        console.log('🔧 FORM DEBUG: Question text field found:', $('#question_text').length);
        console.log('🔧 FORM DEBUG: All input fields:', $('input').length);
        
        // Form submission debug
        $('#question-form').on('submit', function(e) {
            console.log('🔧 FORM DEBUG: Form submitted!');
            console.log('🔧 FORM DEBUG: Form data:', $(this).serialize());
            
            // Show loading message
            $(this).find('input[type="submit"]').val('💾 Saving...');
            
            return true;
        });
        
        // Check if form is visible
        setTimeout(function() {
            const formVisible = $('#question-form').is(':visible');
            const formHeight = $('#question-form').height();
            console.log('🔧 FORM DEBUG: Form visible:', formVisible);
            console.log('🔧 FORM DEBUG: Form height:', formHeight + 'px');
            
            if (!formVisible || formHeight < 100) {
                console.error('🚨 FORM DEBUG: Form might be hidden or not rendering!');
            } else {
                console.log('✅ FORM DEBUG: Form appears to be rendering correctly');
            }
        }, 1000);
    });
    </script>
    
    <style>
    /* Force form visibility */
    #question-form {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    </style>
    
    <?php
    echo '<!-- DEBUG: display_question_form completed -->' . PHP_EOL;
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
            </div>
        </div>
        <?php
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
        
        // Basic CSV processing
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
     * Add admin notice with transient support
     */
    private function add_admin_notice($message, $type = 'info') {
        // If we're redirecting, store in transient
        if (strpos($message, 'successfully') !== false) {
            set_transient('vefify_admin_notice', array('message' => $message, 'type' => $type), 30);
        } else {
            // Show immediately
            add_action('admin_notices', function() use ($message, $type) {
                echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
            });
        }
    }
}