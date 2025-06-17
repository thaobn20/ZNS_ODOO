<?php
/**
 * Campaign Manager Module
 * File: modules/campaigns/class-campaign-manager.php
 * 
 * PERFORMANCE OPTIMIZED but maintains original class name
 * - Original class name: Vefify_Campaign_Manager (for compatibility)
 * - Fast admin interface
 * - Optimized form handling
 * - On-demand statistics loading
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
        
        // WordPress hooks - MINIMAL for performance
        add_action('admin_init', array($this, 'handle_campaign_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_vefify_campaign_action', array($this, 'ajax_campaign_action'));
        add_action('wp_ajax_vefify_refresh_stats', array($this, 'ajax_refresh_stats'));
    }
    
    /**
     * Display campaigns list page - OPTIMIZED
     */
    public function display_campaigns_list() {
        // Handle bulk actions first
        $this->handle_bulk_actions();
        
        // Get campaigns with OPTIMIZED parameters
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        
        $args = array(
            'page' => $current_page,
            'per_page' => 15, // Reduced for better performance
            'search' => $search,
            'status' => $status,
            'include_stats' => false // DISABLED for performance
        );
        
        $result = $this->model->get_campaigns($args);
        
        // Display admin notices
        $this->display_admin_notices();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">📋 Campaign Management <small style="color: #46b450;">(Performance Mode)</small></h1>
            <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=new'); ?>" class="page-title-action">Add New Campaign</a>
            
            <!-- OPTIMIZED Analytics Summary -->
            <?php $this->display_campaigns_summary(); ?>
            
            <!-- Search and Filter Form -->
            <form method="get" id="campaigns-filter" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="vefify-campaigns">
                
                <p class="search-box">
                    <label class="screen-reader-text" for="campaign-search-input">Search Campaigns:</label>
                    <input type="search" id="campaign-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search campaigns..." style="margin-right: 8px;">
                    <input type="submit" id="search-submit" class="button" value="Search">
                    <?php if ($search): ?>
                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns'); ?>" class="button">Clear</a>
                    <?php endif; ?>
                </p>
                
                <ul class="subsubsub">
                    <li class="all">
                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&status=all'); ?>" 
                           class="<?php echo $status === 'all' ? 'current' : ''; ?>">
                            All (<?php echo $result['total_items']; ?>)
                        </a> |
                    </li>
                    <li class="active">
                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&status=active'); ?>" 
                           class="<?php echo $status === 'active' ? 'current' : ''; ?>">
                            Active
                        </a> |
                    </li>
                    <li class="inactive">
                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&status=inactive'); ?>" 
                           class="<?php echo $status === 'inactive' ? 'current' : ''; ?>">
                            Inactive
                        </a>
                    </li>
                </ul>
            </form>
            
            <!-- Campaigns Table -->
            <form method="post">
                <?php wp_nonce_field('vefify_bulk_campaigns'); ?>
                
                <?php if (empty($result['campaigns'])): ?>
                    <div class="notice notice-info">
                        <p><strong>No campaigns found.</strong> 
                           <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=new'); ?>" class="button button-primary">Create your first campaign</a>
                        </p>
                    </div>
                <?php else: ?>
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
                                <th scope="col" class="manage-column column-settings">Settings</th>
                                <th scope="col" class="manage-column column-dates">Duration</th>
                                <th scope="col" class="manage-column column-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
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
                                        <?php if (!empty($campaign['description'])): ?>
                                            <div class="row-description">
                                                <?php echo esc_html(wp_trim_words($campaign['description'], 10)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $campaign['id']); ?>">Edit</a> |
                                            </span>
                                            <span class="duplicate">
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vefify-campaigns&action=duplicate&id=' . $campaign['id']), 'duplicate_campaign_' . $campaign['id']); ?>">Duplicate</a> |
                                            </span>
                                            <span class="trash">
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vefify-campaigns&action=delete&id=' . $campaign['id']), 'delete_campaign_' . $campaign['id']); ?>" 
                                                   onclick="return confirm('Are you sure you want to delete this campaign?')">Delete</a>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="column-status">
                                        <?php echo $this->get_campaign_status_badge($campaign); ?>
                                    </td>
                                    <td class="column-settings">
                                        <small>
                                            <?php echo intval($campaign['questions_per_quiz']); ?> questions<br>
                                            Pass score: <?php echo intval($campaign['pass_score']); ?><br>
                                            <?php if ($campaign['time_limit']): ?>
                                                Time limit: <?php echo intval($campaign['time_limit']); ?>s
                                            <?php else: ?>
                                                No time limit
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="column-dates">
                                        <small>
                                            <?php echo date('M j, Y', strtotime($campaign['start_date'])); ?><br>
                                            to<br>
                                            <?php echo date('M j, Y', strtotime($campaign['end_date'])); ?>
                                        </small>
                                    </td>
                                    <td class="column-actions">
                                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $campaign['id']); ?>" 
                                           class="button button-small">Edit</a>
                                        <button class="button button-small view-stats-btn" 
                                                data-campaign-id="<?php echo $campaign['id']; ?>"
                                                style="background: #f0f9ff; border-color: #0073aa;">
                                            📊 Stats
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="tablenav bottom">
                        <?php $this->display_pagination($result, $current_page); ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- On-demand Statistics Modal -->
        <div id="stats-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 25px; border: 1px solid #ccc; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 9999; min-width: 350px;">
            <h3 style="margin-top: 0;">📊 Campaign Statistics</h3>
            <div id="stats-content">Loading statistics...</div>
            <div style="margin-top: 20px; text-align: right;">
                <button id="close-stats" class="button">Close</button>
            </div>
        </div>
        <div id="stats-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9998;"></div>
        
        <style>
        .campaigns-summary { display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap; }
        .summary-card { background: white; border: 1px solid #ddd; border-radius: 4px; padding: 15px; min-width: 140px; flex: 1; }
        .summary-card h3 { margin: 0 0 5px 0; font-size: 20px; color: #0073aa; }
        .summary-card .description { font-size: 13px; color: #666; }
        .status-badge { padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .status-active { background: #d1ecf1; color: #0c5460; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-expired { background: #fff3cd; color: #856404; }
        .status-scheduled { background: #e2e3e5; color: #383d41; }
        .row-description { font-size: 13px; color: #666; margin-top: 3px; }
        .view-stats-btn:hover { background: #0073aa !important; color: white !important; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // On-demand statistics loading
            $('.view-stats-btn').on('click', function() {
                var campaignId = $(this).data('campaign-id');
                var $button = $(this);
                
                $button.prop('disabled', true).text('Loading...');
                $('#stats-modal, #stats-overlay').show();
                $('#stats-content').html('<div style="text-align: center; padding: 20px;">Loading statistics...</div>');
                
                $.post(ajaxurl, {
                    action: 'vefify_refresh_stats',
                    campaign_id: campaignId,
                    nonce: '<?php echo wp_create_nonce('vefify_refresh_stats'); ?>'
                }, function(response) {
                    $button.prop('disabled', false).text('📊 Stats');
                    
                    if (response.success) {
                        var stats = response.data;
                        $('#stats-content').html(
                            '<div style="line-height: 1.8;">' +
                            '<p><strong>Total Participants:</strong> ' + stats.total_participants + '</p>' +
                            '<p><strong>Completed:</strong> ' + stats.completed_participants + ' (' + stats.completion_rate + '%)</p>' +
                            '<p><strong>Average Score:</strong> ' + stats.average_score + '</p>' +
                            '<p><strong>Pass Rate:</strong> ' + stats.pass_rate + '%</p>' +
                            '<p><strong>Gifts Distributed:</strong> ' + stats.gifts_distributed + '</p>' +
                            '<p style="font-size: 12px; color: #666; margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">' +
                            'Statistics calculated in real-time</p>' +
                            '</div>'
                        );
                    } else {
                        $('#stats-content').html('<p style="color: #dc3232;">Error loading statistics. Please try again.</p>');
                    }
                }).fail(function() {
                    $button.prop('disabled', false).text('📊 Stats');
                    $('#stats-content').html('<p style="color: #dc3232;">Failed to load statistics. Please check your connection.</p>');
                });
            });
            
            // Close modal
            $('#close-stats, #stats-overlay').on('click', function() {
                $('#stats-modal, #stats-overlay').hide();
            });
            
            // Keyboard support
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#stats-modal').is(':visible')) {
                    $('#stats-modal, #stats-overlay').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Display campaign form - OPTIMIZED
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
        
        // OPTIMIZED: Set default values for new campaigns
        if (!$is_edit) {
            $campaign = array(
                'name' => '',
                'description' => '',
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+30 days')),
                'questions_per_quiz' => 5,
                'pass_score' => 3,
                'time_limit' => 600,
                'max_participants' => 100,
                'is_active' => 1
            );
        }
        
        // Display admin notices
        $this->display_admin_notices();
        
        ?>
        <div class="wrap">
            <h1><?php echo $title; ?> <small style="color: #46b450;">(Performance Mode)</small></h1>
            
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
                                               value="<?php echo esc_attr($campaign['name']); ?>" 
                                               class="regular-text" required>
                                        <p class="description">Enter a descriptive name for your campaign</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="campaign_description">Description</label></th>
                                    <td>
                                        <textarea id="campaign_description" name="campaign_description" 
                                                  rows="4" class="large-text"><?php echo esc_textarea($campaign['description']); ?></textarea>
                                        <p class="description">Brief description of the campaign purpose and goals</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Campaign Status</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="is_active" value="1" 
                                                   <?php checked($campaign['is_active'], 1); ?>>
                                            Active
                                        </label>
                                        <p class="description">Check to make this campaign active and available to participants</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Quiz Configuration -->
                    <div class="postbox">
                        <h2 class="hndle">❓ Quiz Configuration</h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="questions_per_quiz">Questions per Quiz *</label></th>
                                    <td>
                                        <input type="number" id="questions_per_quiz" name="questions_per_quiz" 
                                               value="<?php echo intval($campaign['questions_per_quiz']); ?>" 
                                               min="1" max="50" class="small-text" required>
                                        <p class="description">Number of questions to show in each quiz (1-50)</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="pass_score">Passing Score *</label></th>
                                    <td>
                                        <input type="number" id="pass_score" name="pass_score" 
                                               value="<?php echo intval($campaign['pass_score']); ?>" 
                                               min="1" class="small-text" required>
                                        <p class="description">Minimum score required to pass the quiz</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="time_limit">Time Limit (seconds)</label></th>
                                    <td>
                                        <input type="number" id="time_limit" name="time_limit" 
                                               value="<?php echo intval($campaign['time_limit']); ?>" 
                                               min="0" step="60" class="small-text">
                                        <p class="description">Time limit in seconds (0 = no limit, recommended: 600 = 10 minutes)</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Schedule & Limits -->
                    <div class="postbox">
                        <h2 class="hndle">📅 Schedule & Limits</h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="start_date">Start Date *</label></th>
                                    <td>
                                        <input type="date" id="start_date" name="start_date" 
                                               value="<?php echo esc_attr($campaign['start_date']); ?>" 
                                               class="regular-text" required>
                                        <p class="description">Campaign start date</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="end_date">End Date *</label></th>
                                    <td>
                                        <input type="date" id="end_date" name="end_date" 
                                               value="<?php echo esc_attr($campaign['end_date']); ?>" 
                                               class="regular-text" required>
                                        <p class="description">Campaign end date</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="max_participants">Max Participants</label></th>
                                    <td>
                                        <input type="number" id="max_participants" name="max_participants" 
                                               value="<?php echo intval($campaign['max_participants']); ?>" 
                                               min="0" class="small-text">
                                        <p class="description">Maximum number of participants (0 = unlimited)</p>
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
     * Handle campaign actions (create, edit, delete) - OPTIMIZED
     */
    public function handle_campaign_actions() {
        if (!isset($_POST['action']) || !current_user_can('manage_options')) {
            return;
        }
        
        // EMERGENCY: Set execution time and memory limits
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');
        
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
     * Handle saving campaign (create/update) - OPTIMIZED
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
     * Handle bulk actions - OPTIMIZED
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
     * AJAX: Refresh statistics on-demand
     */
    public function ajax_refresh_stats() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_refresh_stats')) {
            wp_die('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $stats = $this->model->get_campaign_statistics($campaign_id);
        
        wp_send_json_success($stats);
    }
    
    /**
     * Display campaigns summary cards - OPTIMIZED
     */
    private function display_campaigns_summary() {
        $summary = $this->model->get_campaigns_summary();
        $memory_usage = round(memory_get_usage(true) / 1024 / 1024, 1);
        ?>
        <div class="campaigns-summary" style="background: #e8f5e8; border: 1px solid #46b450; border-radius: 4px; padding: 15px; margin: 20px 0;">
            <div class="summary-card">
                <h3><?php echo number_format($summary['total']); ?></h3>
                <div class="description">Total Campaigns</div>
            </div>
            <div class="summary-card">
                <h3><?php echo number_format($summary['active']); ?></h3>
                <div class="description">Active Campaigns</div>
            </div>
            <div class="summary-card">
                <h3><?php echo number_format($summary['total_participants']); ?></h3>
                <div class="description">Total Participants</div>
            </div>
            <div class="summary-card">
                <h3><?php echo $memory_usage; ?>MB</h3>
                <div class="description">Memory Usage</div>
            </div>
            <div class="summary-card">
                <h3 style="color: #46b450;">⚡ Optimized</h3>
                <div class="description">Performance Mode</div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get campaign status badge HTML
     */
    private function get_campaign_status_badge($campaign) {
        if (!$campaign['is_active']) {
            return '<span class="status-badge status-inactive">⏸️ Inactive</span>';
        }
        
        $now = current_time('timestamp');
        $start = strtotime($campaign['start_date']);
        $end = strtotime($campaign['end_date']);
        
        if ($now < $start) {
            return '<span class="status-badge status-scheduled">📅 Scheduled</span>';
        } elseif ($now > $end) {
            return '<span class="status-badge status-expired">⏰ Expired</span>';
        } else {
            return '<span class="status-badge status-active">✅ Active</span>';
        }
    }
    
    /**
     * Display pagination
     */
    private function display_pagination($result, $current_page) {
        if ($result['total_pages'] <= 1) {
            return;
        }
        
        echo '<div class="tablenav-pages">';
        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
            'total' => $result['total_pages'],
            'current' => $current_page
        ));
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
    public function enqueue_admin_scripts($hook) {
        if (!str_contains($hook, 'vefify-campaigns')) {
            return;
        }
        
        wp_enqueue_script('vefify-campaign-admin', VEFIFY_QUIZ_PLUGIN_URL . 
            'assets/js/campaign-admin.js', array('jquery'), VEFIFY_QUIZ_VERSION, true);
        wp_localize_script('vefify-campaign-admin', 'vefifyAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vefify_campaign_ajax')
        ));
        
        wp_enqueue_style('vefify-campaign-admin', VEFIFY_QUIZ_PLUGIN_URL . 
            'assets/css/campaign-admin.css', array(), VEFIFY_QUIZ_VERSION);
    }
    
    /**
     * Get model instance
     */
    public function get_model() {
        return $this->model;
    }
}