<?php
/**
 * Campaign Manager Module
 * File: modules/campaigns/class-campaign-manager.php
 * Handles admin interface and user interactions for campaigns
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Campaign_Manager {
    
    private $model;
    
    public function __construct() {
        // Load the campaign model
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/campaigns/class-campaign-model.php';
        $this->model = new Vefify_Campaign_Model();
        
        // WordPress hooks
        add_action('admin_init', array($this, 'handle_campaign_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_vefify_campaign_action', array($this, 'ajax_campaign_action'));
    }
    
    /**
     * Display campaigns list page
     */
    public function display_campaigns_list() {
        // Handle bulk actions
        $this->handle_bulk_actions();
        
        // Get campaigns with pagination
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        
        $args = array(
            'page' => $current_page,
            'per_page' => 20,
            'search' => $search,
            'status' => $status,
            'include_stats' => true
        );
        
        $result = $this->model->get_campaigns($args);
        
        // Display admin notices
        $this->display_admin_notices();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">📋 Campaign Management</h1>
            <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=new'); ?>" class="page-title-action">Add New Campaign</a>
            
            <!-- Analytics Summary -->
            <?php $this->display_campaigns_summary(); ?>
            
            <!-- Search and Filter Form -->
            <form method="get" id="campaigns-filter">
                <input type="hidden" name="page" value="vefify-campaigns">
                
                <p class="search-box">
                    <label class="screen-reader-text" for="campaign-search-input">Search Campaigns:</label>
                    <input type="search" id="campaign-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search campaigns...">
                    <input type="submit" id="search-submit" class="button" value="Search">
                </p>
                
                <ul class="subsubsub">
                    <li class="all">
                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&status=all'); ?>" 
                           class="<?php echo $status === 'all' ? 'current' : ''; ?>">
                            All <span class="count">(<?php echo $result['total_items']; ?>)</span>
                        </a> |
                    </li>
                    <li class="active">
                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&status=active'); ?>" 
                           class="<?php echo $status === 'active' ? 'current' : ''; ?>">
                            Active
                        </a> |
                    </li>
                    <li class="expired">
                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&status=expired'); ?>" 
                           class="<?php echo $status === 'expired' ? 'current' : ''; ?>">
                            Expired
                        </a>
                    </li>
                </ul>
            </form>
            
            <!-- Campaigns Table -->
            <form method="post">
                <?php wp_nonce_field('vefify_bulk_campaigns'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1">Bulk Actions</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="delete">Delete</option>
                        </select>
                        <input type="submit" id="doaction" class="button action" value="Apply">
                    </div>
                    
                    <?php $this->display_pagination($result, $current_page); ?>
                </div>
                
                <table class="wp-list-table widefat fixed striped campaigns">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column">
                                <input id="cb-select-all-1" type="checkbox">
                            </td>
                            <th scope="col" class="manage-column column-name column-primary">Campaign</th>
                            <th scope="col" class="manage-column column-status">Status</th>
                            <th scope="col" class="manage-column column-participants">Participants</th>
                            <th scope="col" class="manage-column column-completion">Completion Rate</th>
                            <th scope="col" class="manage-column column-dates">Duration</th>
                            <th scope="col" class="manage-column column-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($result['campaigns'])): ?>
                            <?php foreach ($result['campaigns'] as $campaign): ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="campaign[]" value="<?php echo $campaign['id']; ?>">
                                    </th>
                                    <td class="column-name column-primary">
                                        <strong>
                                            <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $campaign['id']); ?>">
                                                <?php echo esc_html($campaign['name']); ?>
                                            </a>
                                        </strong>
                                        <div class="campaign-description">
                                            <?php echo esc_html(wp_trim_words($campaign['description'], 15)); ?>
                                        </div>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $campaign['id']); ?>">Edit</a> |
                                            </span>
                                            <span class="view">
                                                <a href="<?php echo admin_url('admin.php?page=vefify-reports&campaign_id=' . $campaign['id']); ?>">View Report</a> |
                                            </span>
                                            <span class="duplicate">
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vefify-campaigns&action=duplicate&id=' . $campaign['id']), 'duplicate_campaign_' . $campaign['id']); ?>">Duplicate</a> |
                                            </span>
                                            <span class="delete">
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vefify-campaigns&action=delete&id=' . $campaign['id']), 'delete_campaign_' . $campaign['id']); ?>" 
                                                   onclick="return confirm('Are you sure you want to delete this campaign?')">Delete</a>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="column-status">
                                        <?php echo $this->get_campaign_status_badge($campaign); ?>
                                    </td>
                                    <td class="column-participants">
                                        <strong><?php echo number_format($campaign['stats']['participants_count']); ?></strong>
                                        <br>
                                        <small><?php echo number_format($campaign['stats']['completed_count']); ?> completed</small>
                                    </td>
                                    <td class="column-completion">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $campaign['stats']['completion_rate']; ?>%"></div>
                                        </div>
                                        <small><?php echo $campaign['stats']['completion_rate']; ?>%</small>
                                    </td>
                                    <td class="column-dates">
                                        <div class="campaign-dates">
                                            <strong>Start:</strong> <?php echo date('M j, Y', strtotime($campaign['start_date'])); ?><br>
                                            <strong>End:</strong> <?php echo date('M j, Y', strtotime($campaign['end_date'])); ?>
                                        </div>
                                    </td>
                                    <td class="column-actions">
                                        <div class="campaign-quick-actions">
                                            <button type="button" class="button button-small toggle-campaign" 
                                                    data-campaign-id="<?php echo $campaign['id']; ?>"
                                                    data-current-status="<?php echo $campaign['is_active']; ?>">
                                                <?php echo $campaign['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                            <a href="<?php echo admin_url('admin.php?page=vefify-analytics&campaign_id=' . $campaign['id']); ?>" 
                                               class="button button-small">Analytics</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-campaigns">
                                    <div class="no-campaigns-message">
                                        <h3>No campaigns found</h3>
                                        <p>Create your first campaign to get started with quiz management.</p>
                                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=new'); ?>" class="button button-primary">Create Campaign</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="tablenav bottom">
                    <?php $this->display_pagination($result, $current_page); ?>
                </div>
            </form>
        </div>
        
        <style>
        .campaigns-summary { display: flex; gap: 20px; margin: 20px 0; }
        .summary-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; flex: 1; text-align: center; }
        .summary-card h3 { margin: 0 0 10px; font-size: 24px; }
        .summary-card .description { color: #666; font-size: 14px; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .status-badge.active { background: #46b450; }
        .status-badge.expired { background: #dc3232; }
        .status-badge.inactive { background: #999; }
        .progress-bar { width: 100%; height: 10px; background: #f0f0f0; border-radius: 5px; overflow: hidden; margin-bottom: 4px; }
        .progress-fill { height: 100%; background: #0073aa; transition: width 0.3s ease; }
        .campaign-dates { font-size: 12px; }
        .campaign-quick-actions { display: flex; gap: 4px; flex-direction: column; }
        .no-campaigns-message { text-align: center; padding: 40px 20px; }
        </style>
        <?php
    }
    
    /**
     * Display campaign form (new/edit)
     */
    public function display_campaign_form() {
        $campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $campaign = null;
        
        if ($campaign_id) {
            $campaign = $this->model->get_campaign($campaign_id);
            if (!$campaign) {
                wp_die('Campaign not found.');
            }
        }
        
        $is_edit = !empty($campaign);
        $title = $is_edit ? 'Edit Campaign: ' . esc_html($campaign['name']) : 'New Campaign';
        
        // Display admin notices
        $this->display_admin_notices();
        
        ?>
        <div class="wrap">
            <h1><?php echo $title; ?></h1>
            
            <form method="post" action="" id="campaign-form">
                <?php wp_nonce_field('vefify_campaign_save'); ?>
                <input type="hidden" name="action" value="save_campaign">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                <?php endif; ?>
                
                <div class="campaign-form-container">
                    <!-- Basic Information -->
                    <div class="postbox">
                        <h2 class="hndle">📋 Basic Information</h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="campaign_name">Campaign Name *</label></th>
                                    <td>
                                        <input type="text" id="campaign_name" name="campaign_name" 
                                               value="<?php echo $is_edit ? esc_attr($campaign['name']) : ''; ?>" 
                                               class="regular-text" required>
                                        <p class="description">Enter a descriptive name for your campaign</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="campaign_description">Description</label></th>
                                    <td>
                                        <textarea id="campaign_description" name="campaign_description" 
                                                  rows="4" class="large-text"><?php echo $is_edit ? esc_textarea($campaign['description']) : ''; ?></textarea>
                                        <p class="description">Brief description of the campaign purpose and goals</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Campaign Status</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="is_active" value="1" 
                                                   <?php checked($is_edit ? $campaign['is_active'] : 1); ?>>
                                            Active campaign (participants can join)
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Schedule Settings -->
                    <div class="postbox">
                        <h2 class="hndle">⏰ Schedule Settings</h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="start_date">Start Date *</label></th>
                                    <td>
                                        <input type="datetime-local" id="start_date" name="start_date" 
                                               value="<?php echo $is_edit ? date('Y-m-d\TH:i', strtotime($campaign['start_date'])) : date('Y-m-d\TH:i'); ?>" 
                                               required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="end_date">End Date *</label></th>
                                    <td>
                                        <input type="datetime-local" id="end_date" name="end_date" 
                                               value="<?php echo $is_edit ? date('Y-m-d\TH:i', strtotime($campaign['end_date'])) : date('Y-m-d\TH:i', strtotime('+30 days')); ?>" 
                                               required>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Quiz Configuration -->
                    <div class="postbox">
                        <h2 class="hndle">🎯 Quiz Configuration</h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="questions_per_quiz">Questions per Quiz *</label></th>
                                    <td>
                                        <input type="number" id="questions_per_quiz" name="questions_per_quiz" 
                                               value="<?php echo $is_edit ? $campaign['questions_per_quiz'] : 5; ?>" 
                                               min="1" max="50" class="small-text" required>
                                        <p class="description">Number of questions shown to each participant</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="pass_score">Pass Score *</label></th>
                                    <td>
                                        <input type="number" id="pass_score" name="pass_score" 
                                               value="<?php echo $is_edit ? $campaign['pass_score'] : 3; ?>" 
                                               min="1" class="small-text" required>
                                        <p class="description">Minimum score required to pass the quiz</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="time_limit">Time Limit (seconds)</label></th>
                                    <td>
                                        <input type="number" id="time_limit" name="time_limit" 
                                               value="<?php echo $is_edit ? $campaign['time_limit'] : 600; ?>" 
                                               min="60" class="small-text">
                                        <p class="description">Maximum time allowed for quiz completion (0 = no limit)</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="max_participants">Max Participants</label></th>
                                    <td>
                                        <input type="number" id="max_participants" name="max_participants" 
                                               value="<?php echo $is_edit ? $campaign['max_participants'] : 1000; ?>" 
                                               min="1" class="small-text">
                                        <p class="description">Maximum number of participants (0 = unlimited)</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Advanced Settings -->
                    <div class="postbox">
                        <h2 class="hndle">⚙️ Advanced Settings</h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Quiz Options</th>
                                    <td>
                                        <?php 
                                        $meta_data = $is_edit ? $campaign['meta_data'] : array();
                                        ?>
                                        <label>
                                            <input type="checkbox" name="meta_data[shuffle_questions]" value="1" 
                                                   <?php checked(!empty($meta_data['shuffle_questions'])); ?>>
                                            Shuffle questions order
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="meta_data[shuffle_options]" value="1" 
                                                   <?php checked(!empty($meta_data['shuffle_options'])); ?>>
                                            Shuffle answer options
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="meta_data[show_results]" value="1" 
                                                   <?php checked(!empty($meta_data['show_results'])); ?>>
                                            Show results immediately after completion
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="meta_data[allow_retake]" value="1" 
                                                   <?php checked(!empty($meta_data['allow_retake'])); ?>>
                                            Allow participants to retake quiz
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" 
                           value="<?php echo $is_edit ? 'Update Campaign' : 'Create Campaign'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=vefify-campaigns'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        
        <style>
        .campaign-form-container { max-width: 800px; }
        .postbox { margin-bottom: 20px; }
        .postbox h2.hndle { padding: 12px 20px; margin: 0; background: #f1f1f1; border-bottom: 1px solid #ddd; }
        .postbox .inside { padding: 20px; }
        .form-table th { width: 200px; }
        .regular-text { width: 25em; }
        .large-text { width: 50em; }
        .small-text { width: 8em; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Form validation
            $('#campaign-form').on('submit', function(e) {
                var startDate = new Date($('#start_date').val());
                var endDate = new Date($('#end_date').val());
                
                if (endDate <= startDate) {
                    alert('End date must be after start date');
                    e.preventDefault();
                    return false;
                }
                
                var passScore = parseInt($('#pass_score').val());
                var questionsPerQuiz = parseInt($('#questions_per_quiz').val());
                
                if (passScore > questionsPerQuiz) {
                    alert('Pass score cannot be higher than questions per quiz');
                    e.preventDefault();
                    return false;
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle campaign actions (create, edit, delete)
     */
    public function handle_campaign_actions() {
        if (!isset($_POST['action']) || !current_user_can('manage_options')) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_campaign_save')) {
            wp_die('Security check failed');
        }
        
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'save_campaign':
                $this->handle_save_campaign();
                break;
        }
    }
    
    /**
     * Handle saving campaign (create/update)
     */
    private function handle_save_campaign() {
        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        
        $campaign_data = array(
            'name' => sanitize_text_field($_POST['campaign_name']),
            'description' => sanitize_textarea_field($_POST['campaign_description']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'questions_per_quiz' => intval($_POST['questions_per_quiz']),
            'pass_score' => intval($_POST['pass_score']),
            'time_limit' => intval($_POST['time_limit']),
            'max_participants' => intval($_POST['max_participants']),
            'is_active' => isset($_POST['is_active']),
            'meta_data' => isset($_POST['meta_data']) ? $_POST['meta_data'] : array()
        );
        
        // Validate data
        $errors = $this->model->validate_campaign_data($campaign_data, $campaign_id);
        if (!empty($errors)) {
            set_transient('vefify_admin_notice', array(
                'type' => 'error',
                'message' => 'Validation errors: ' . implode(', ', $errors)
            ), 30);
            return;
        }
        
        if ($campaign_id) {
            // Update existing campaign
            $result = $this->model->update_campaign($campaign_id, $campaign_data);
            $message = 'Campaign updated successfully';
            $redirect_id = $campaign_id;
        } else {
            // Create new campaign
            $result = $this->model->create_campaign($campaign_data);
            $message = 'Campaign created successfully';
            $redirect_id = $result;
        }
        
        if (is_wp_error($result)) {
            set_transient('vefify_admin_notice', array(
                'type' => 'error',
                'message' => $result->get_error_message()
            ), 30);
        } else {
            set_transient('vefify_admin_notice', array(
                'type' => 'success',
                'message' => $message
            ), 30);
            
            wp_redirect(admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $redirect_id));
            exit;
        }
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if (!isset($_POST['action']) || $_POST['action'] === '-1') {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_bulk_campaigns')) {
            wp_die('Security check failed');
        }
        
        $action = sanitize_text_field($_POST['action']);
        $campaign_ids = isset($_POST['campaign']) ? array_map('intval', $_POST['campaign']) : array();
        
        if (empty($campaign_ids)) {
            return;
        }
        
        $count = 0;
        foreach ($campaign_ids as $campaign_id) {
            switch ($action) {
                case 'activate':
                    $this->model->update_campaign($campaign_id, array('is_active' => 1));
                    $count++;
                    break;
                case 'deactivate':
                    $this->model->update_campaign($campaign_id, array('is_active' => 0));
                    $count++;
                    break;
                case 'delete':
                    $this->model->delete_campaign($campaign_id);
                    $count++;
                    break;
            }
        }
        
        set_transient('vefify_admin_notice', array(
            'type' => 'success',
            'message' => sprintf('%d campaigns %s successfully', $count, $action === 'delete' ? 'deleted' : $action . 'd')
        ), 30);
    }
    
    /**
     * AJAX handler for campaign actions
     */
    public function ajax_campaign_action() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_campaign_ajax')) {
            wp_die('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'toggle_status':
                $current_status = intval($_POST['current_status']);
                $new_status = $current_status ? 0 : 1;
                
                $result = $this->model->update_campaign($campaign_id, array('is_active' => $new_status));
                
                if (is_wp_error($result)) {
                    wp_send_json_error($result->get_error_message());
                } else {
                    wp_send_json_success(array(
                        'new_status' => $new_status,
                        'message' => 'Campaign status updated successfully'
                    ));
                }
                break;
        }
        
        wp_die();
    }
    
    /**
     * Display campaigns summary cards
     */
    private function display_campaigns_summary() {
        $summary = $this->model->get_campaigns_summary();
        ?>
        <div class="campaigns-summary">
            <div class="summary-card">
                <h3><?php echo number_format($summary['total']); ?></h3>
                <div class="description">Total Campaigns</div>
            </div>
            <div class="summary-card">
                <h3><?php echo number_format($summary['active']); ?></h3>
                <div class="description">Active Campaigns</div>
            </div>
            <div class="summary-card">
                <h3><?php echo number_format($summary['expired']); ?></h3>
                <div class="description">Expired Campaigns</div>
            </div>
            <div class="summary-card">
                <h3><?php echo number_format($summary['inactive']); ?></h3>
                <div class="description">Inactive Campaigns</div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get campaign status badge HTML
     */
    private function get_campaign_status_badge($campaign) {
        if (!$campaign['is_active']) {
            return '<span class="status-badge inactive">Inactive</span>';
        }
        
        $now = current_time('timestamp');
        $start = strtotime($campaign['start_date']);
        $end = strtotime($campaign['end_date']);
        
        if ($now < $start) {
            return '<span class="status-badge inactive">Scheduled</span>';
        } elseif ($now > $end) {
            return '<span class="status-badge expired">Expired</span>';
        } else {
            return '<span class="status-badge active">Active</span>';
        }
    }
    
    /**
     * Display pagination
     */
    private function display_pagination($result, $current_page) {
        if ($result['total_pages'] <= 1) {
            return;
        }
        
        $pagination_args = array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
            'total' => $result['total_pages'],
            'current' => $current_page
        );
        
        echo '<div class="tablenav-pages">';
        echo paginate_links($pagination_args);
        echo '</div>';
    }
    
    /**
     * Display admin notices
     */
    private function display_admin_notices() {
        $notice = get_transient('vefify_admin_notice');
        if ($notice) {
            delete_transient('vefify_admin_notice');
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook_suffix) {
        if (strpos($hook_suffix, 'vefify-campaigns') === false) {
            return;
        }
        
        wp_enqueue_script('vefify-campaign-admin', VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/campaign-admin.js', array('jquery'), VEFIFY_QUIZ_VERSION, true);
        wp_localize_script('vefify-campaign-admin', 'vefifyAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_campaign_ajax')
        ));
        
        wp_enqueue_style('vefify-campaign-admin', VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/campaign-admin.css', array(), VEFIFY_QUIZ_VERSION);
    }
    
    /**
     * Get model instance
     */
    public function get_model() {
        return $this->model;
    }
}