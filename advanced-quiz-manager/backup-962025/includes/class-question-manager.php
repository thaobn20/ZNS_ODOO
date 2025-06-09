<?php
/**
 * Question Management Class for Advanced Quiz Manager
 * File: includes/class-question-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQM_Question_Manager {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_question_menu'), 15);
        add_action('wp_ajax_aqm_save_question', array($this, 'save_question'));
        add_action('wp_ajax_aqm_delete_question', array($this, 'delete_question'));
        add_action('wp_ajax_aqm_get_question', array($this, 'get_question'));
        add_action('wp_ajax_aqm_reorder_questions', array($this, 'reorder_questions'));
        add_action('wp_ajax_aqm_duplicate_question', array($this, 'duplicate_question'));
        add_action('wp_ajax_aqm_import_questions', array($this, 'import_questions'));
        add_action('wp_ajax_aqm_export_questions', array($this, 'export_questions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_question_scripts'));
    }
    
    public function add_question_menu() {
        add_submenu_page(
            'quiz-manager',
            'Question Management',
            'Question Management',
            'manage_options',
            'quiz-manager-questions',
            array($this, 'questions_management_page')
        );
    }
    
    public function enqueue_question_scripts($hook) {
        if (strpos($hook, 'quiz-manager-questions') !== false) {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('aqm-question-management', AQM_PLUGIN_URL . 'assets/js/question-management.js', array('jquery', 'jquery-ui-sortable'), AQM_VERSION, true);
            wp_enqueue_style('aqm-question-management', AQM_PLUGIN_URL . 'assets/css/question-management.css', array(), AQM_VERSION);
            
            wp_localize_script('aqm-question-management', 'aqm_questions', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqm_question_nonce'),
                'provinces_data' => $this->get_vietnam_provinces_json(),
                'confirm_delete' => __('Are you sure you want to delete this question?', 'advanced-quiz'),
                'confirm_duplicate' => __('Duplicate this question?', 'advanced-quiz')
            ));
        }
    }
    
    public function questions_management_page() {
        global $wpdb;
        
        $selected_campaign = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        
        // Handle messages
        $message = '';
        if (isset($_GET['message'])) {
            switch ($_GET['message']) {
                case 'question_saved':
                    $message = '<div class="notice notice-success"><p>Question saved successfully!</p></div>';
                    break;
                case 'question_deleted':
                    $message = '<div class="notice notice-success"><p>Question deleted successfully!</p></div>';
                    break;
                case 'questions_reordered':
                    $message = '<div class="notice notice-success"><p>Questions reordered successfully!</p></div>';
                    break;
                case 'error':
                    $message = '<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>';
                    break;
            }
        }
        
        // Get campaigns for dropdown
        $campaigns = $wpdb->get_results("SELECT id, title, status FROM {$wpdb->prefix}aqm_campaigns ORDER BY created_at DESC");
        
        // Get questions for selected campaign
        $questions = array();
        $campaign_info = null;
        if ($selected_campaign > 0) {
            $campaign_info = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aqm_campaigns WHERE id = %d", 
                $selected_campaign
            ));
            
            $questions = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}aqm_questions 
                WHERE campaign_id = %d 
                ORDER BY order_index ASC, id ASC
            ", $selected_campaign));
        }
        
        ?>
        <div class="wrap aqm-questions-page">
            <h1 class="wp-heading-inline">Question Management</h1>
            
            <?php echo $message; ?>
            
            <!-- Campaign Selection -->
            <div class="aqm-campaign-selector">
                <div class="campaign-selector-header">
                    <h2>Select Campaign</h2>
                    <p>Choose a campaign to manage its questions</p>
                </div>
                
                <form method="GET" action="" class="campaign-selector-form">
                    <input type="hidden" name="page" value="quiz-manager-questions">
                    <div class="selector-row">
                        <select name="campaign_id" class="campaign-select" onchange="this.form.submit()">
                            <option value="">Select a campaign to manage questions</option>
                            <?php foreach ($campaigns as $campaign): ?>
                                <option value="<?php echo esc_attr($campaign->id); ?>" 
                                        <?php selected($selected_campaign, $campaign->id); ?>
                                        data-status="<?php echo esc_attr($campaign->status); ?>">
                                    <?php echo esc_html($campaign->title); ?> 
                                    (<?php echo esc_html(ucfirst($campaign->status)); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if ($selected_campaign > 0): ?>
                            <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=edit&campaign_id=' . $selected_campaign); ?>" 
                               class="button button-secondary">
                                <span class="dashicons dashicons-edit"></span>
                                Edit Campaign
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <?php if ($campaign_info): ?>
                    <div class="campaign-info">
                        <div class="campaign-details">
                            <h3><?php echo esc_html($campaign_info->title); ?></h3>
                            <p><?php echo esc_html($campaign_info->description); ?></p>
                            <div class="campaign-meta">
                                <span class="status-badge status-<?php echo esc_attr($campaign_info->status); ?>">
                                    <?php echo esc_html(ucfirst($campaign_info->status)); ?>
                                </span>
                                <span class="question-count">
                                    <?php echo count($questions); ?> questions
                                </span>
                                <?php if ($campaign_info->max_participants > 0): ?>
                                    <span class="max-participants">
                                        Max: <?php echo $campaign_info->max_participants; ?> participants
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($selected_campaign > 0): ?>
                <!-- Question Management Interface -->
                <div class="aqm-questions-interface">
                    <div class="aqm-questions-header">
                        <div class="header-left">
                            <h2>Questions</h2>
                            <span class="questions-count"><?php echo count($questions); ?> questions</span>
                        </div>
                        <div class="header-actions">
                            <button id="add-new-question" class="button button-primary">
                                <span class="dashicons dashicons-plus"></span>
                                Add Question
                            </button>
                            <button id="import-questions" class="button">
                                <span class="dashicons dashicons-upload"></span>
                                Import
                            </button>
                            <button id="export-questions" class="button">
                                <span class="dashicons dashicons-download"></span>
                                Export
                            </button>
                            <button id="preview-campaign" class="button">
                                <span class="dashicons dashicons-visibility"></span>
                                Preview
                            </button>
                        </div>
                    </div>
                    
                    <!-- Question Form Modal -->
                    <div id="question-form-modal" class="aqm-modal" style="display: none;">
                        <div class="aqm-modal-content">
                            <div class="aqm-modal-header">
                                <h2 id="question-form-title">Add New Question</h2>
                                <span class="aqm-modal-close">&times;</span>
                            </div>
                            
                            <form id="question-form" class="aqm-question-form">
                                <input type="hidden" id="question-id" name="question_id" value="">
                                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($selected_campaign); ?>">
                                
                                <div class="question-form-tabs">
                                    <ul class="tabs-nav">
                                        <li class="tab-item active">
                                            <a href="#basic-settings" class="tab-link">
                                                <span class="dashicons dashicons-admin-generic"></span>
                                                Basic Settings
                                            </a>
                                        </li>
                                        <li class="tab-item">
                                            <a href="#options-settings" class="tab-link">
                                                <span class="dashicons dashicons-list-view"></span>
                                                Options & Validation
                                            </a>
                                        </li>
                                        <li class="tab-item">
                                            <a href="#scoring-settings" class="tab-link">
                                                <span class="dashicons dashicons-awards"></span>
                                                Scoring & Behavior
                                            </a>
                                        </li>
                                    </ul>
                                    
                                    <!-- Basic Settings Tab -->
                                    <div id="basic-settings" class="tab-content active">
                                        <div class="form-section">
                                            <div class="form-row">
                                                <div class="form-group full-width">
                                                    <label for="question-text">Question Text <span class="required">*</span></label>
                                                    <textarea name="question_text" id="question-text" 
                                                              class="large-text" rows="3" required
                                                              placeholder="Enter your question here..."></textarea>
                                                    <p class="description">The main question text that users will see</p>
                                                </div>
                                            </div>
                                            
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="question-type">Question Type</label>
                                                    <select name="question_type" id="question-type" onchange="handleQuestionTypeChange()">
                                                        <option value="text">Text Input</option>
                                                        <option value="multiple_choice">Multiple Choice</option>
                                                        <option value="email">Email</option>
                                                        <option value="phone">Phone Number</option>
                                                        <option value="number">Number</option>
                                                        <option value="date">Date</option>
                                                        <option value="provinces">Vietnamese Provinces</option>
                                                        <option value="districts">Vietnamese Districts</option>
                                                        <option value="wards">Vietnamese Wards</option>
                                                        <option value="rating">Rating (Stars)</option>
                                                        <option value="file_upload">File Upload</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="question-group">Question Group</label>
                                                    <input type="text" name="question_group" id="question-group" 
                                                           class="regular-text" placeholder="e.g., Personal Info, Preferences">
                                                    <p class="description">Group questions for better organization</p>
                                                </div>
                                            </div>
                                            
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="order-index">Display Order</label>
                                                    <input type="number" name="order_index" id="order-index" 
                                                           min="1" value="<?php echo count($questions) + 1; ?>">
                                                    <p class="description">Order in which this question appears</p>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="is-required">Validation</label>
                                                    <label class="checkbox-label">
                                                        <input type="checkbox" name="is_required" id="is-required">
                                                        <span class="checkmark"></span>
                                                        Required field
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Options & Validation Tab -->
                                    <div id="options-settings" class="tab-content">
                                        <div class="form-section" id="multiple-choice-options" style="display: none;">
                                            <h3>Answer Options</h3>
                                            <div id="options-container">
                                                <div class="option-row">
                                                    <div class="option-input">
                                                        <input type="text" name="options[]" placeholder="Option 1" class="regular-text">
                                                    </div>
                                                    <div class="option-correct">
                                                        <label class="checkbox-label">
                                                            <input type="checkbox" name="correct_options[]" value="0">
                                                            <span class="checkmark"></span>
                                                            Correct
                                                        </label>
                                                    </div>
                                                    <div class="option-actions">
                                                        <button type="button" class="button button-small remove-option">
                                                            <span class="dashicons dashicons-minus"></span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" id="add-option" class="button button-secondary">
                                                <span class="dashicons dashicons-plus"></span>
                                                Add Option
                                            </button>
                                        </div>
                                        
                                        <div class="form-section" id="location-settings" style="display: none;">
                                            <h3>Location Settings</h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label class="checkbox-label">
                                                        <input type="checkbox" name="load_districts" id="load-districts">
                                                        <span class="checkmark"></span>
                                                        Load districts when province is selected
                                                    </label>
                                                </div>
                                                <div class="form-group">
                                                    <label class="checkbox-label">
                                                        <input type="checkbox" name="load_wards" id="load-wards">
                                                        <span class="checkmark"></span>
                                                        Load wards when district is selected
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-section" id="rating-settings" style="display: none;">
                                            <h3>Rating Settings</h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="max-rating">Max Rating</label>
                                                    <input type="number" name="max_rating" id="max-rating" 
                                                           min="3" max="10" value="5">
                                                </div>
                                                <div class="form-group">
                                                    <label for="rating-icon">Icon Type</label>
                                                    <select name="rating_icon" id="rating-icon">
                                                        <option value="star">Stars</option>
                                                        <option value="heart">Hearts</option>
                                                        <option value="thumb">Thumbs</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-section">
                                            <h3>Additional Settings</h3>
                                            <div class="form-row">
                                                <div class="form-group full-width">
                                                    <label for="placeholder">Placeholder Text</label>
                                                    <input type="text" name="placeholder" id="placeholder" 
                                                           class="regular-text" placeholder="Enter placeholder text">
                                                    <p class="description">Hint text shown in the input field</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Scoring & Behavior Tab -->
                                    <div id="scoring-settings" class="tab-content">
                                        <div class="form-section">
                                            <h3>Scoring</h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="points">Points</label>
                                                    <input type="number" name="points" id="points" 
                                                           min="0" value="0">
                                                    <p class="description">Points awarded for correct answer</p>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="scoring-weight">Scoring Weight</label>
                                                    <input type="number" name="scoring_weight" id="scoring-weight" 
                                                           min="0" max="10" step="0.1" value="1">
                                                    <p class="description">Weight of this question in final score calculation</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-section">
                                            <h3>Behavior</h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label class="checkbox-label">
                                                        <input type="checkbox" name="gift_eligibility" id="gift-eligibility">
                                                        <span class="checkmark"></span>
                                                        This question affects gift eligibility
                                                    </label>
                                                    <p class="description">Check if this question should be considered for gift awards</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="button button-primary">
                                        <span class="dashicons dashicons-yes"></span>
                                        Save Question
                                    </button>
                                    <button type="button" class="button button-secondary" onclick="closeQuestionModal()">
                                        <span class="dashicons dashicons-no-alt"></span>
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Questions List -->
                    <div class="aqm-questions-list">
                        <?php if (empty($questions)): ?>
                            <div class="aqm-empty-state">
                                <div class="empty-icon">❓</div>
                                <h3>No questions found</h3>
                                <p>This campaign doesn't have any questions yet.</p>
                                <button class="button button-primary" onclick="document.getElementById('add-new-question').click()">
                                    <span class="dashicons dashicons-plus"></span>
                                    Add Your First Question
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="questions-toolbar">
                                <div class="toolbar-left">
                                    <div class="bulk-actions">
                                        <select id="bulk-action">
                                            <option value="">Bulk Actions</option>
                                            <option value="delete">Delete</option>
                                            <option value="duplicate">Duplicate</option>
                                            <option value="activate">Activate</option>
                                            <option value="deactivate">Deactivate</option>
                                        </select>
                                        <button id="apply-bulk" class="button">Apply</button>
                                    </div>
                                </div>
                                <div class="toolbar-right">
                                    <div class="view-options">
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="group-by-type">
                                            <span class="checkmark"></span>
                                            Group by Type
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="show-details">
                                            <span class="checkmark"></span>
                                            Show Details
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="questions-container" class="questions-sortable">
                                <?php foreach ($questions as $index => $question): ?>
                                    <div class="question-item" data-question-id="<?php echo esc_attr($question->id); ?>">
                                        <div class="question-header">
                                            <div class="question-drag-handle" title="Drag to reorder">
                                                <span class="dashicons dashicons-menu"></span>
                                            </div>
                                            
                                            <div class="question-number">
                                                <span class="number"><?php echo ($index + 1); ?></span>
                                            </div>
                                            
                                            <div class="question-type-badge question-type-<?php echo esc_attr($question->question_type); ?>">
                                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $question->question_type))); ?>
                                            </div>
                                            
                                            <div class="question-content">
                                                <div class="question-text-preview">
                                                    <?php echo esc_html(wp_trim_words($question->question_text, 15)); ?>
                                                </div>
                                                
                                                <?php if ($question->question_group): ?>
                                                    <div class="question-group-badge">
                                                        <?php echo esc_html($question->question_group); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="question-badges">
                                                <?php if ($question->is_required): ?>
                                                    <span class="badge required-badge">Required</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($question->points > 0): ?>
                                                    <span class="badge points-badge"><?php echo esc_html($question->points); ?> pts</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($question->gift_eligibility): ?>
                                                    <span class="badge gift-badge">🎁 Gift</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="question-actions">
                                                <button class="button button-small edit-question" 
                                                        data-question-id="<?php echo esc_attr($question->id); ?>"
                                                        title="Edit Question">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </button>
                                                
                                                <button class="button button-small duplicate-question" 
                                                        data-question-id="<?php echo esc_attr($question->id); ?>"
                                                        title="Duplicate Question">
                                                    <span class="dashicons dashicons-admin-page"></span>
                                                </button>
                                                
                                                <button class="button button-small button-link-delete delete-question" 
                                                        data-question-id="<?php echo esc_attr($question->id); ?>"
                                                        title="Delete Question">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="question-details" style="display: none;">
                                            <div class="details-content">
                                                <div class="detail-section">
                                                    <h4>Question Details</h4>
                                                    <p><strong>Full Text:</strong> <?php echo esc_html($question->question_text); ?></p>
                                                    <p><strong>Type:</strong> <?php echo esc_html(str_replace('_', ' ', $question->question_type)); ?></p>
                                                    <p><strong>Order:</strong> <?php echo esc_html($question->order_index); ?></p>
                                                </div>
                                                
                                                <?php if ($question->question_type === 'multiple_choice' && $question->options): ?>
                                                    <div class="detail-section">
                                                        <h4>Multiple Choice Options</h4>
                                                        <?php 
                                                        $options = json_decode($question->options, true);
                                                        if (is_array($options) && isset($options['choices'])) {
                                                            echo '<ul class="options-list">';
                                                            foreach ($options['choices'] as $index => $choice) {
                                                                $is_correct = isset($options['correct']) && in_array($index, $options['correct']);
                                                                echo '<li class="' . ($is_correct ? 'correct-option' : '') . '">';
                                                                echo esc_html($choice);
                                                                if ($is_correct) echo ' <span class="correct-indicator">✓</span>';
                                                                echo '</li>';
                                                            }
                                                            echo '</ul>';
                                                        }
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="detail-section">
                                                    <h4>Scoring & Settings</h4>
                                                    <div class="detail-grid">
                                                        <div class="detail-item">
                                                            <span class="label">Points:</span>
                                                            <span class="value"><?php echo esc_html($question->points); ?></span>
                                                        </div>
                                                        <div class="detail-item">
                                                            <span class="label">Weight:</span>
                                                            <span class="value"><?php echo esc_html($question->scoring_weight); ?></span>
                                                        </div>
                                                        <div class="detail-item">
                                                            <span class="label">Required:</span>
                                                            <span class="value"><?php echo $question->is_required ? 'Yes' : 'No'; ?></span>
                                                        </div>
                                                        <div class="detail-item">
                                                            <span class="label">Gift Eligible:</span>
                                                            <span class="value"><?php echo $question->gift_eligibility ? 'Yes' : 'No'; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function get_vietnam_provinces_json() {
        global $wpdb;
        
        $provinces = $wpdb->get_results("
            SELECT code, name, name_en 
            FROM {$wpdb->prefix}aqm_provinces 
            ORDER BY name
        ");
        
        return json_encode($provinces);
    }
    
    public function save_question() {
        check_ajax_referer('aqm_question_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        
        try {
            $question_id = intval($_POST['question_id']);
            $campaign_id = intval($_POST['campaign_id']);
            
            // Validate required fields
            if (empty($_POST['question_text']) || empty($campaign_id)) {
                throw new Exception('Question text and campaign are required');
            }
            
            // Prepare options based on question type
            $options = array();
            $question_type = sanitize_text_field($_POST['question_type']);
            
            if ($question_type === 'multiple_choice' && isset($_POST['options'])) {
                $choices = array_map('sanitize_text_field', array_filter($_POST['options']));
                $correct = isset($_POST['correct_options']) ? array_map('intval', $_POST['correct_options']) : array();
                
                if (empty($choices)) {
                    throw new Exception('Multiple choice questions must have at least one option');
                }
                
                $options = array(
                    'choices' => $choices,
                    'correct' => $correct
                );
            } elseif (in_array($question_type, array('provinces', 'districts', 'wards'))) {
                $options = array(
                    'load_districts' => isset($_POST['load_districts']),
                    'load_wards' => isset($_POST['load_wards']),
                    'placeholder' => sanitize_text_field($_POST['placeholder'] ?? '')
                );
            } elseif ($question_type === 'rating') {
                $options = array(
                    'max_rating' => intval($_POST['max_rating'] ?? 5),
                    'icon' => sanitize_text_field($_POST['rating_icon'] ?? 'star')
                );
            } else {
                $options = array(
                    'placeholder' => sanitize_text_field($_POST['placeholder'] ?? '')
                );
            }
            
            $data = array(
                'campaign_id' => $campaign_id,
                'question_text' => sanitize_textarea_field($_POST['question_text']),
                'question_type' => $question_type,
                'question_group' => sanitize_text_field($_POST['question_group']),
                'options' => json_encode($options),
                'is_required' => isset($_POST['is_required']) ? 1 : 0,
                'order_index' => intval($_POST['order_index']),
                'points' => intval($_POST['points']),
                'scoring_weight' => floatval($_POST['scoring_weight']),
                'gift_eligibility' => isset($_POST['gift_eligibility']) ? 1 : 0
            );
            
            if ($question_id > 0) {
                // Update existing question
                $result = $wpdb->update($wpdb->prefix . 'aqm_questions', $data, array('id' => $question_id));
                $message = 'Question updated successfully';
            } else {
                // Create new question
                $result = $wpdb->insert($wpdb->prefix . 'aqm_questions', $data);
                $question_id = $wpdb->insert_id;
                $message = 'Question created successfully';
            }
            
            if ($result === false) {
                throw new Exception('Failed to save question');
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'question_id' => $question_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function get_question() {
        check_ajax_referer('aqm_question_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $question_id = intval($_POST['question_id']);
        
        global $wpdb;
        $question = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aqm_questions WHERE id = %d",
            $question_id
        ));
        
        if ($question) {
            // Parse options
            if ($question->options) {
                $question->parsed_options = json_decode($question->options, true);
            }
            
            wp_send_json_success($question);
        } else {
            wp_send_json_error('Question not found');
        }
    }
    
    public function delete_question() {
        check_ajax_referer('aqm_question_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        $question_id = intval($_POST['question_id']);
        
        try {
            $result = $wpdb->delete($wpdb->prefix . 'aqm_questions', array('id' => $question_id));
            
            if ($result !== false) {
                wp_send_json_success('Question deleted successfully');
            } else {
                wp_send_json_error('Failed to delete question');
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function duplicate_question() {
        check_ajax_referer('aqm_question_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        $question_id = intval($_POST['question_id']);
        
        try {
            // Get original question
            $original = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aqm_questions WHERE id = %d",
                $question_id
            ), ARRAY_A);
            
            if (!$original) {
                throw new Exception('Question not found');
            }
            
            // Prepare duplicate data
            unset($original['id']);
            $original['question_text'] = $original['question_text'] . ' (Copy)';
            $original['order_index'] = intval($original['order_index']) + 1;
            
            $result = $wpdb->insert($wpdb->prefix . 'aqm_questions', $original);
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => 'Question duplicated successfully',
                    'question_id' => $wpdb->insert_id
                ));
            } else {
                wp_send_json_error('Failed to duplicate question');
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function reorder_questions() {
        check_ajax_referer('aqm_question_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        $question_ids = array_map('intval', $_POST['question_ids']);
        
        try {
            foreach ($question_ids as $index => $question_id) {
                $wpdb->update(
                    $wpdb->prefix . 'aqm_questions',
                    array('order_index' => $index + 1),
                    array('id' => $question_id)
                );
            }
            
            wp_send_json_success('Questions reordered successfully');
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function import_questions() {
        check_ajax_referer('aqm_question_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        try {
            if (empty($_FILES['questions_file'])) {
                throw new Exception('No file uploaded');
            }
            
            $file = $_FILES['questions_file'];
            $file_type = wp_check_filetype($file['name']);
            
            if ($file_type['ext'] !== 'json') {
                throw new Exception('Only JSON files are supported');
            }
            
            $content = file_get_contents($file['tmp_name']);
            $questions = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON format');
            }
            
            if (!is_array($questions)) {
                throw new Exception('JSON must contain an array of questions');
            }
            
            global $wpdb;
            $imported_count = 0;
            $campaign_id = intval($_POST['campaign_id']);
            
            foreach ($questions as $question_data) {
                // Validate required fields
                if (empty($question_data['question_text']) || empty($question_data['question_type'])) {
                    continue;
                }
                
                $data = array(
                    'campaign_id' => $campaign_id,
                    'question_text' => sanitize_textarea_field($question_data['question_text']),
                    'question_type' => sanitize_text_field($question_data['question_type']),
                    'question_group' => sanitize_text_field($question_data['question_group'] ?? ''),
                    'options' => json_encode($question_data['options'] ?? array()),
                    'is_required' => intval($question_data['is_required'] ?? 0),
                    'order_index' => intval($question_data['order_index'] ?? 1),
                    'points' => intval($question_data['points'] ?? 0),
                    'scoring_weight' => floatval($question_data['scoring_weight'] ?? 1),
                    'gift_eligibility' => intval($question_data['gift_eligibility'] ?? 0)
                );
                
                if ($wpdb->insert($wpdb->prefix . 'aqm_questions', $data)) {
                    $imported_count++;
                }
            }
            
            wp_send_json_success(array(
                'message' => "Successfully imported {$imported_count} questions",
                'count' => $imported_count
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function export_questions() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!wp_verify_nonce($_GET['nonce'], 'aqm_question_nonce')) {
            wp_die('Security check failed');
        }
        
        $campaign_id = intval($_GET['campaign_id']);
        
        global $wpdb;
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aqm_questions WHERE campaign_id = %d ORDER BY order_index",
            $campaign_id
        ), ARRAY_A);
        
        // Get campaign info
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT title FROM {$wpdb->prefix}aqm_campaigns WHERE id = %d",
            $campaign_id
        ));
        
        $filename = 'questions-' . sanitize_title($campaign->title ?? 'campaign') . '-' . date('Y-m-d') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($questions, JSON_PRETTY_PRINT);
        exit;
    }
}

// Initialize Question Manager
new AQM_Question_Manager();