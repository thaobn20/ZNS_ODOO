<?php
/**
 * Enhanced Admin Class for Advanced Quiz Manager
 * File: admin/class-admin.php
 */

class AQM_Admin {
    
    private $db;
    
    public function __construct() {
        $this->db = new AQM_Database();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_aqm_save_campaign', array($this, 'ajax_save_campaign'));
        add_action('wp_ajax_aqm_save_question', array($this, 'ajax_save_question'));
        add_action('wp_ajax_aqm_delete_question', array($this, 'ajax_delete_question'));
        add_action('wp_ajax_aqm_save_gift', array($this, 'ajax_save_gift'));
        add_action('wp_ajax_aqm_delete_gift', array($this, 'ajax_delete_gift'));
        add_action('wp_ajax_aqm_export_responses', array($this, 'ajax_export_responses'));
    }
    
    public function add_admin_menus() {
        add_menu_page(
            'Quiz Manager',
            'Quiz Manager',
            'manage_options',
            'quiz-manager',
            array($this, 'dashboard_page'),
            'dashicons-feedback',
            30
        );
        
        add_submenu_page(
            'quiz-manager',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'quiz-manager',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Campaigns',
            'Campaigns',
            'manage_options',
            'quiz-manager-campaigns',
            array($this, 'campaigns_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Questions',
            'Questions',
            'manage_options',
            'quiz-manager-questions',
            array($this, 'questions_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Gifts & Rewards',
            'Gifts & Rewards',
            'manage_options',
            'quiz-manager-gifts',
            array($this, 'gifts_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Responses',
            'Responses',
            'manage_options',
            'quiz-manager-responses',
            array($this, 'responses_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Analytics',
            'Analytics',
            'manage_options',
            'quiz-manager-analytics',
            array($this, 'analytics_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'quiz-manager') !== false) {
            wp_enqueue_script('aqm-admin-js', AQM_PLUGIN_URL . 'assets/js/admin.js', 
                array('jquery', 'jquery-ui-sortable'), AQM_VERSION, true);
            wp_enqueue_style('aqm-admin-css', AQM_PLUGIN_URL . 'assets/css/admin.css', 
                array(), AQM_VERSION);
            
            wp_localize_script('aqm-admin-js', 'aqm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqm_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'advanced-quiz'),
                    'saved' => __('Saved successfully!', 'advanced-quiz'),
                    'error' => __('An error occurred. Please try again.', 'advanced-quiz')
                )
            ));
        }
    }
    
    // PAGE HANDLERS
    
    public function dashboard_page() {
        $stats = $this->db->get_dashboard_stats();
        ?>
        <div class="wrap aqm-admin-page">
            <h1>📊 Quiz Manager Dashboard</h1>
            
            <div class="aqm-stats-grid">
                <div class="aqm-stat-card">
                    <div class="aqm-stat-icon">🎯</div>
                    <div class="aqm-stat-content">
                        <h3><?php echo esc_html($stats['total_campaigns']); ?></h3>
                        <p>Total Campaigns</p>
                    </div>
                </div>
                
                <div class="aqm-stat-card">
                    <div class="aqm-stat-icon">✅</div>
                    <div class="aqm-stat-content">
                        <h3><?php echo esc_html($stats['active_campaigns']); ?></h3>
                        <p>Active Campaigns</p>
                    </div>
                </div>
                
                <div class="aqm-stat-card">
                    <div class="aqm-stat-icon">👥</div>
                    <div class="aqm-stat-content">
                        <h3><?php echo esc_html($stats['total_responses']); ?></h3>
                        <p>Total Responses</p>
                    </div>
                </div>
                
                <div class="aqm-stat-card">
                    <div class="aqm-stat-icon">🎁</div>
                    <div class="aqm-stat-content">
                        <h3><?php echo esc_html($stats['gifts_claimed']); ?></h3>
                        <p>Gifts Claimed</p>
                    </div>
                </div>
            </div>
            
            <div class="aqm-dashboard-widgets">
                <div class="aqm-widget">
                    <h3>🎯 Recent Campaigns</h3>
                    <?php $this->render_recent_campaigns(); ?>
                </div>
                
                <div class="aqm-widget">
                    <h3>📈 Quick Actions</h3>
                    <div style="padding: 20px;">
                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=add'); ?>" 
                           class="button button-primary">Create New Campaign</a>
                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-responses'); ?>" 
                           class="button">View All Responses</a>
                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-analytics'); ?>" 
                           class="button">View Analytics</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function campaigns_page() {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'add':
            case 'edit':
                $this->render_campaign_form($action);
                break;
            default:
                $this->render_campaigns_list();
        }
    }
    
    public function questions_page() {
        $campaign_id = $_GET['campaign_id'] ?? 0;
        
        if (!$campaign_id) {
            $this->render_select_campaign_page('questions');
            return;
        }
        
        $campaign = $this->db->get_campaign($campaign_id);
        if (!$campaign) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Campaign not found.</p></div></div>';
            return;
        }
        
        $this->render_questions_management($campaign);
    }
    
    public function gifts_page() {
        $campaign_id = $_GET['campaign_id'] ?? 0;
        
        if (!$campaign_id) {
            $this->render_select_campaign_page('gifts');
            return;
        }
        
        $campaign = $this->db->get_campaign($campaign_id);
        if (!$campaign) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Campaign not found.</p></div></div>';
            return;
        }
        
        $this->render_gifts_management($campaign);
    }
    
    public function responses_page() {
        $campaign_id = $_GET['campaign_id'] ?? 0;
        
        if (!$campaign_id) {
            $this->render_select_campaign_page('responses');
            return;
        }
        
        $campaign = $this->db->get_campaign($campaign_id);
        if (!$campaign) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Campaign not found.</p></div></div>';
            return;
        }
        
        $this->render_responses_page($campaign);
    }
    
    public function analytics_page() {
        $campaign_id = $_GET['campaign_id'] ?? 0;
        
        if (!$campaign_id) {
            $this->render_select_campaign_page('analytics');
            return;
        }
        
        $campaign = $this->db->get_campaign($campaign_id);
        if (!$campaign) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Campaign not found.</p></div></div>';
            return;
        }
        
        $this->render_analytics_page($campaign);
    }
    
    // RENDERING METHODS
    
    private function render_campaigns_list() {
        $campaigns = $this->db->get_campaigns();
        ?>
        <div class="wrap aqm-admin-page">
            <h1>
                🎯 Campaigns 
                <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=add'); ?>" 
                   class="button button-primary">Add New Campaign</a>
            </h1>
            
            <?php if (!empty($campaigns)): ?>
                <div class="aqm-table-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Participants</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $campaign): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($campaign->title); ?></strong>
                                        <?php if ($campaign->description): ?>
                                            <div style="color: #666; font-size: 0.9em;">
                                                <?php echo esc_html(wp_trim_words($campaign->description, 10)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="aqm-status aqm-status-<?php echo esc_attr($campaign->status); ?>">
                                            <?php echo esc_html(ucfirst($campaign->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $analytics = $this->db->get_response_analytics($campaign->id);
                                        echo esc_html($analytics['total_responses']);
                                        if ($campaign->max_participants > 0) {
                                            echo ' / ' . esc_html($campaign->max_participants);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($campaign->created_at))); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=edit&id=' . $campaign->id); ?>" 
                                           class="button button-small">Edit</a>
                                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-questions&campaign_id=' . $campaign->id); ?>" 
                                           class="button button-small">Questions</a>
                                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-gifts&campaign_id=' . $campaign->id); ?>" 
                                           class="button button-small">Gifts</a>
                                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-responses&campaign_id=' . $campaign->id); ?>" 
                                           class="button button-small">Responses</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="aqm-empty-state">
                    <h3>🎯 No campaigns yet</h3>
                    <p>Create your first quiz campaign to get started!</p>
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=add'); ?>" 
                       class="button button-primary">Create Campaign</a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_campaign_form($action) {
        $campaign_id = $action === 'edit' ? intval($_GET['id']) : 0;
        $campaign = $campaign_id ? $this->db->get_campaign($campaign_id) : null;
        
        if ($action === 'edit' && !$campaign) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Campaign not found.</p></div></div>';
            return;
        }
        ?>
        <div class="wrap aqm-admin-page">
            <h1><?php echo $action === 'edit' ? '✏️ Edit Campaign' : '➕ Create New Campaign'; ?></h1>
            
            <form id="aqm-campaign-form" class="aqm-form">
                <?php wp_nonce_field('aqm_admin_nonce', 'aqm_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>">
                
                <div class="aqm-form-section">
                    <h3>Campaign Details</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="campaign_title">Campaign Title *</label></th>
                            <td>
                                <input type="text" id="campaign_title" name="title" class="regular-text" 
                                       value="<?php echo esc_attr($campaign ? $campaign->title : ''); ?>" required>
                                <p class="description">Enter a descriptive title for your quiz campaign.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="campaign_description">Description</label></th>
                            <td>
                                <textarea id="campaign_description" name="description" class="large-text" rows="4"><?php echo esc_textarea($campaign ? $campaign->description : ''); ?></textarea>
                                <p class="description">Provide a brief description of the quiz.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="campaign_status">Status</label></th>
                            <td>
                                <select id="campaign_status" name="status">
                                    <option value="draft" <?php selected($campaign ? $campaign->status : 'draft', 'draft'); ?>>Draft</option>
                                    <option value="active" <?php selected($campaign ? $campaign->status : '', 'active'); ?>>Active</option>
                                    <option value="inactive" <?php selected($campaign ? $campaign->status : '', 'inactive'); ?>>Inactive</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="max_participants">Max Participants</label></th>
                            <td>
                                <input type="number" id="max_participants" name="max_participants" 
                                       value="<?php echo esc_attr($campaign ? $campaign->max_participants : 0); ?>" min="0">
                                <p class="description">Set to 0 for unlimited participants.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $action === 'edit' ? 'Update Campaign' : 'Create Campaign'; ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function render_questions_management($campaign) {
        $questions = $this->db->get_campaign_questions($campaign->id);
        ?>
        <div class="wrap aqm-admin-page">
            <h1>
                ❓ Questions for "<?php echo esc_html($campaign->title); ?>"
                <button type="button" class="button button-primary" id="aqm-add-question">Add Question</button>
            </h1>
            
            <div id="aqm-questions-container">
                <?php if (!empty($questions)): ?>
                    <?php foreach ($questions as $question): ?>
                        <?php $this->render_question_editor($question); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div id="aqm-question-template" style="display: none;">
                <?php $this->render_question_editor(); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            const campaignId = <?php echo esc_js($campaign->id); ?>;
            
            $('#aqm-add-question').on('click', function() {
                const template = $('#aqm-question-template').html();
                $('#aqm-questions-container').append(template);
            });
        });
        </script>
        <?php
    }
    
    private function render_question_editor($question = null) {
        $options = $question ? json_decode($question->options, true) : array();
        ?>
        <div class="aqm-question-builder" data-question-id="<?php echo $question ? esc_attr($question->id) : '0'; ?>">
            <div class="aqm-question-header">
                <h4><?php echo $question ? 'Question #' . $question->id : 'New Question'; ?></h4>
                <div class="aqm-question-controls">
                    <button type="button" class="button button-small aqm-save-question">Save</button>
                    <button type="button" class="button button-small aqm-delete-question">Delete</button>
                </div>
            </div>
            
            <div class="aqm-question-content">
                <div class="aqm-question-field">
                    <label>Question Text *</label>
                    <textarea name="question_text" rows="3" required><?php echo esc_textarea($question ? $question->question_text : ''); ?></textarea>
                </div>
                
                <div class="aqm-question-field">
                    <label>Question Type</label>
                    <select name="question_type">
                        <option value="multiple_choice" <?php selected($question ? $question->question_type : 'multiple_choice', 'multiple_choice'); ?>>Multiple Choice (Multiple Answers)</option>
                        <option value="single_choice" <?php selected($question ? $question->question_type : '', 'single_choice'); ?>>Single Choice</option>
                    </select>
                </div>
                
                <div class="aqm-question-field">
                    <label>Points</label>
                    <input type="number" name="points" value="<?php echo esc_attr($question ? $question->points : 1); ?>" min="1">
                </div>
                
                <div class="aqm-question-options">
                    <label>Answer Options</label>
                    <div class="aqm-options-list">
                        <?php if (!empty($options)): ?>
                            <?php foreach ($options as $index => $option): ?>
                                <div class="aqm-option-item">
                                    <input type="text" name="option_text[]" value="<?php echo esc_attr($option['text']); ?>" placeholder="Option text">
                                    <label>
                                        <input type="checkbox" name="option_correct[]" value="<?php echo $index; ?>" <?php checked(!empty($option['correct'])); ?>>
                                        Correct
                                    </label>
                                    <button type="button" class="button button-small aqm-remove-option">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="aqm-option-item">
                                <input type="text" name="option_text[]" placeholder="Option text">
                                <label>
                                    <input type="checkbox" name="option_correct[]" value="0">
                                    Correct
                                </label>
                                <button type="button" class="button button-small aqm-remove-option">Remove</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button button-small aqm-add-option">Add Option</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_select_campaign_page($context) {
        $campaigns = $this->db->get_campaigns(array('status' => 'active'));
        $page_titles = array(
            'questions' => '❓ Select Campaign for Questions',
            'gifts' => '🎁 Select Campaign for Gifts',
            'responses' => '📊 Select Campaign for Responses',
            'analytics' => '📈 Select Campaign for Analytics'
        );
        ?>
        <div class="wrap aqm-admin-page">
            <h1><?php echo esc_html($page_titles[$context]); ?></h1>
            
            <?php if (!empty($campaigns)): ?>
                <div class="aqm-campaign-grid">
                    <?php foreach ($campaigns as $campaign): ?>
                        <div class="aqm-campaign-card">
                            <h3><?php echo esc_html($campaign->title); ?></h3>
                            <p><?php echo esc_html($campaign->description); ?></p>
                            <a href="<?php echo admin_url("admin.php?page=quiz-manager-{$context}&campaign_id={$campaign->id}"); ?>" 
                               class="button button-primary">Manage <?php echo ucfirst($context); ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="aqm-empty-state">
                    <h3>🎯 No active campaigns</h3>
                    <p>Create and activate a campaign first to manage <?php echo $context; ?>.</p>
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=add'); ?>" 
                       class="button button-primary">Create Campaign</a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    // AJAX HANDLERS
    
    public function ajax_save_campaign() {
        check_ajax_referer('aqm_admin_nonce', 'aqm_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $data = array(
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description']),
            'status' => sanitize_text_field($_POST['status']),
            'max_participants' => intval($_POST['max_participants']),
            'created_by' => get_current_user_id()
        );
        
        if ($campaign_id) {
            $result = $this->db->update_campaign($campaign_id, $data);
            $message = 'Campaign updated successfully!';
        } else {
            $campaign_id = $this->db->create_campaign($data);
            $result = $campaign_id !== false;
            $message = 'Campaign created successfully!';
        }
        
        if ($result) {
            wp_send_json_success(array(
                'message' => $message,
                'campaign_id' => $campaign_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save campaign.'));
        }
    }
    
    // Helper methods
    
    private function render_recent_campaigns() {
        $campaigns = $this->db->get_campaigns(array('limit' => 5));
        
        if (empty($campaigns)) {
            echo '<div style="padding: 20px; text-align: center; color: #666;">No campaigns yet.</div>';
            return;
        }
        
        echo '<ul class="aqm-recent-list">';
        foreach ($campaigns as $campaign) {
            $responses = $this->db->get_response_analytics($campaign->id);
            echo '<li>';
            echo '<a href="' . admin_url('admin.php?page=quiz-manager-campaigns&action=edit&id=' . $campaign->id) . '">';
            echo esc_html($campaign->title);
            echo '</a>';
            echo '<span class="aqm-response-count">' . esc_html($responses['total_responses']) . ' responses</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    public function handle_admin_actions() {
        // Handle form submissions and other admin actions
        if (isset($_POST['aqm_campaign_submit'])) {
            $this->handle_campaign_submission();
        }
    }
    
    private function handle_campaign_submission() {
        if (!wp_verify_nonce($_POST['aqm_nonce'], 'aqm_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        // Handle campaign form submission
        $this->ajax_save_campaign();
    }
}
?>