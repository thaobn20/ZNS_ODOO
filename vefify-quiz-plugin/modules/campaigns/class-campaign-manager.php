<?php
/**
 * Campaign Manager Module
 * File: modules/campaigns/class-campaign-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Campaign_Manager {
    private $model;
    private $db;
    
    public function __construct($model = null) {
        global $wpdb;
        $this->db = $wpdb;
        
        // Use provided model or create new instance
        if ($model) {
            $this->model = $model;
        } else {
            require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/campaigns/class-campaign-model.php';
            $this->model = new Vefify_Campaign_Model();
        }
        
        // AJAX hooks
        add_action('wp_ajax_vefify_save_campaign', array($this, 'ajax_save_campaign'));
        add_action('wp_ajax_vefify_delete_campaign', array($this, 'ajax_delete_campaign'));
        add_action('wp_ajax_vefify_get_campaign_data', array($this, 'ajax_get_campaign_data'));
    }
    
    /**
     * Display campaigns list page
     */
    public function display_campaigns_list() {
        // Performance tracking
        $start_time = microtime(true);
        $memory_start = memory_get_usage(true);
        
        echo '<div class="wrap">';
        
        // Performance indicator
        echo '<div class="performance-indicator" style="background:#d4edda; border:1px solid #c3e6cb; padding:12px; margin:15px 0; border-radius:6px; display:flex; align-items:center; justify-content:space-between;">';
        echo '<div><span style="color:#155724; font-weight:600;">🚀 Performance Mode Active</span> | Memory: ' . round($memory_start / 1024 / 1024, 2) . 'MB</div>';
        echo '<div style="font-size:12px; color:#666;">Optimized for speed</div>';
        echo '</div>';
        
        echo '<h1 style="display:flex; align-items:center; gap:10px;">📋 Campaign Management <span style="font-size:14px; background:#e3f2fd; color:#1976d2; padding:4px 8px; border-radius:12px; font-weight:normal;">Fast Mode</span></h1>';
        
        // Display admin notices
        $this->display_admin_notices();
        
        // Quick actions
        echo '<div class="campaign-actions" style="margin: 25px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">';
        echo '<div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">';
        echo '<a href="?page=vefify-campaigns&action=new" class="button button-primary" style="padding:8px 20px;">➕ Add New Campaign</a>';
        echo '<a href="?page=vefify-campaigns&action=analytics" class="button button-secondary">📊 View Analytics</a>';
        echo '<span style="color:#666; font-size:14px;">|</span>';
        echo '<span style="color:#666; font-size:14px;">Quick filters:</span>';
        echo '<a href="?page=vefify-campaigns&status=active" class="button button-small">Active Only</a>';
        echo '<a href="?page=vefify-campaigns&status=all" class="button button-small">All Campaigns</a>';
        echo '</div>';
        echo '</div>';
        
        // Get campaigns with pagination
        $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        
        $campaigns_result = $this->model->get_campaigns(array(
            'per_page' => 15,
            'page' => $current_page,
            'status' => $status_filter === 'all' ? null : $status_filter,
            'include_stats' => true
        ));
        
        $campaigns = $campaigns_result['campaigns'];
        
        // Summary stats
        $this->display_campaign_summary();
        
        if (empty($campaigns)) {
            echo '<div class="notice notice-info" style="margin:20px 0; padding:20px; text-align:center;">';
            echo '<h3 style="margin:0 0 10px;">🎯 Ready to create your first campaign?</h3>';
            echo '<p>Campaigns help you organize quiz participants and track their progress.</p>';
            echo '<a href="?page=vefify-campaigns&action=new" class="button button-primary button-large">Create Your First Campaign</a>';
            echo '</div>';
        } else {
            $this->display_campaigns_table($campaigns);
            $this->display_pagination($campaigns_result, $current_page);
        }
        
        // Performance results
        $load_time = round((microtime(true) - $start_time) * 1000, 2);
        $memory_used = round((memory_get_usage(true) - $memory_start) / 1024 / 1024, 2);
        
        echo '<div class="performance-results" style="margin-top:25px; padding:12px; background:#e8f5e8; border-radius:6px; font-size:13px; color:#2e7d32; border: 1px solid #c8e6c9;">';
        echo "⚡ Page loaded in <strong>{$load_time}ms</strong> | Memory used: <strong>{$memory_used}MB</strong> | Database queries optimized";
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Display campaign summary stats
     */
    private function display_campaign_summary() {
        $summary = $this->model->get_campaigns_summary();
        $memory_usage = round(memory_get_usage(true) / 1024 / 1024, 2);
        
        ?>
        <div class="campaign-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 25px 0;">
            <div class="summary-card" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 20px; border-radius: 10px; text-align: center; border: 1px solid #2196f3;">
                <h3 style="margin: 0 0 5px; font-size: 32px; color: #1976d2;"><?php echo number_format($summary['total'] ?? 0); ?></h3>
                <div style="color: #1565c0; font-weight: 600;">Total Campaigns</div>
                <div style="font-size: 12px; color: #1976d2; margin-top: 5px;">All time</div>
            </div>
            <div class="summary-card" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%); padding: 20px; border-radius: 10px; text-align: center; border: 1px solid #4caf50;">
                <h3 style="margin: 0 0 5px; font-size: 32px; color: #2e7d32;"><?php echo number_format($summary['active'] ?? 0); ?></h3>
                <div style="color: #388e3c; font-weight: 600;">Active Campaigns</div>
                <div style="font-size: 12px; color: #2e7d32; margin-top: 5px;">Currently running</div>
            </div>
            <div class="summary-card" style="background: linear-gradient(135deg, #fff3e0 0%, #ffcc02 100%); padding: 20px; border-radius: 10px; text-align: center; border: 1px solid #ff9800;">
                <h3 style="margin: 0 0 5px; font-size: 32px; color: #f57c00;"><?php echo number_format($summary['running'] ?? 0); ?></h3>
                <div style="color: #ef6c00; font-weight: 600;">Running Now</div>
                <div style="font-size: 12px; color: #f57c00; margin-top: 5px;">Live campaigns</div>
            </div>
            <div class="summary-card" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); padding: 20px; border-radius: 10px; text-align: center; border: 1px solid #9c27b0;">
                <h3 style="margin: 0 0 5px; font-size: 32px; color: #7b1fa2;"><?php echo $memory_usage; ?>MB</h3>
                <div style="color: #8e24aa; font-weight: 600;">Memory Usage</div>
                <div style="font-size: 12px; color: #7b1fa2; margin-top: 5px;">🚀 Optimized</div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display campaign edit/new form
     */
    public function display_campaign_form() {
        $campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $campaign = $campaign_id ? $this->model->get_campaign($campaign_id) : null;
        $is_edit = $campaign ? true : false;
        
        echo '<div class="wrap">';
        echo '<h1 style="display:flex; align-items:center; gap:10px;">';
        echo ($is_edit ? '✏️ Edit Campaign' : '✨ New Campaign');
        echo '</h1>';
        
        // Breadcrumb
        echo '<div style="margin:10px 0; color:#666; font-size:14px;">';
        echo '<a href="?page=vefify-campaigns" style="text-decoration:none; color:#0073aa;">📋 Campaigns</a>';
        echo ' → ' . ($is_edit ? 'Edit: ' . esc_html($campaign['name']) : 'New Campaign');
        echo '</div>';
        
        // Handle form submission
        if (isset($_POST['save_campaign'])) {
            $this->handle_campaign_save();
        }
        
        ?>
        <div class="campaign-form-container" style="max-width: 900px;">
            <form method="post" action="" id="campaign-form" style="background:#fff; padding:30px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                <?php wp_nonce_field('vefify_campaign_save', 'vefify_campaign_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
                
                <!-- Campaign Basic Info -->
                <div class="form-section" style="margin-bottom:40px;">
                    <h2 style="color:#2c3e50; border-bottom:2px solid #e9ecef; padding-bottom:10px; margin-bottom:25px;">📝 Basic Information</h2>
                    
                    <div class="form-row" style="display:grid; grid-template-columns: 2fr 1fr; gap:25px; margin-bottom:25px;">
                        <div class="form-group">
                            <label for="campaign_name" style="display:block; margin-bottom:8px; font-weight:600; color:#2c3e50;">Campaign Name *</label>
                            <input type="text" id="campaign_name" name="name" 
                                   value="<?php echo esc_attr($campaign['name'] ?? ''); ?>" 
                                   class="regular-text" required
                                   style="width:100%; padding:12px; border:2px solid #e9ecef; border-radius:6px; font-size:16px;"
                                   placeholder="Enter a descriptive name for your campaign">
                            <small style="color:#666; font-size:12px;">This name will be displayed to participants</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="campaign_status" style="display:block; margin-bottom:8px; font-weight:600; color:#2c3e50;">Status</label>
                            <div style="display:flex; align-items:center; gap:10px; padding:12px; background:#f8f9fa; border-radius:6px; border:2px solid #e9ecef;">
                                <input type="checkbox" id="campaign_status" name="is_active" value="1" 
                                       <?php checked(($campaign['is_active'] ?? 1), 1); ?>
                                       style="margin:0;">
                                <label for="campaign_status" style="margin:0; color:#2c3e50; font-weight:600;">Campaign is active</label>
                            </div>
                            <small style="color:#666; font-size:12px;">Inactive campaigns won't accept new participants</small>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:25px;">
                        <label for="campaign_description" style="display:block; margin-bottom:8px; font-weight:600; color:#2c3e50;">Description</label>
                        <textarea id="campaign_description" name="description" 
                                  rows="4" style="width:100%; padding:12px; border:2px solid #e9ecef; border-radius:6px; font-size:16px; resize:vertical;"
                                  placeholder="Describe what this campaign is about..."><?php echo esc_textarea($campaign['description'] ?? ''); ?></textarea>
                        <small style="color:#666; font-size:12px;">Explain the purpose and goals of this quiz campaign</small>
                    </div>
                </div>
                
                <!-- Schedule & Limits -->
                <div class="form-section" style="margin-bottom:40px;">
                    <h2 style="color:#2c3e50; border-bottom:2px solid #e9ecef; padding-bottom:10px; margin-bottom:25px;">📅 Schedule & Limits</h2>
                    
                    <div class="form-row" style="display:grid; grid-template-columns: 1fr 1fr; gap:25px; margin-bottom:25px;">
                        <div class="form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; color:#2c3e50;">Start Date & Time *</label>
                            <input type="datetime-local" name="start_date" 
                                   value="<?php echo isset($campaign['start_date']) ? date('Y-m-d\TH:i', strtotime($campaign['start_date'])) : date('Y-m-d\TH:i'); ?>" 
                                   required
                                   style="width:100%; padding:12px; border:2px solid #e9ecef; border-radius:6px; font-size:16px;">
                            <small style="color:#666; font-size:12px;">When participants can start taking the quiz</small>
                        </div>
                        
                        <div class="form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; color:#2c3e50;">End Date & Time *</label>
                            <input type="datetime-local" name="end_date" 
                                   value="<?php echo isset($campaign['end_date']) ? date('Y-m-d\TH:i', strtotime($campaign['end_date'])) : date('Y-m-d\TH:i', strtotime('+30 days')); ?>" 
                                   required
                                   style="width:100%; padding:12px; border:2px solid #e9ecef; border-radius:6px; font-size:16px;">
                            <small style="color:#666; font-size:12px;">When the campaign ends</small>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:25px;">
                        <label style="display:block; margin-bottom:8px; font-weight:600; color:#2c3e50;">Maximum Participants</label>
                        <input type="number" name="max_participants" 
                               value="<?php echo esc_attr($campaign['max_participants'] ?? ''); ?>" 
                               min="1" 
                               style="width:200px; padding:12px; border:2px solid #e9ecef; border-radius:6px; font-size:16px;"
                               placeholder="e.g. 1000">
                        <small style="color:#666; font-size:12px; margin-left:10px;">Leave empty for unlimited participants</small>
                    </div>
                </div>
                
                <!-- Quiz Settings -->
                <div class="form-section" style="margin-bottom:40px;">
                    <h2 style="color:#2c3e50; border-bottom:2px solid #e9ecef; padding-bottom:10px; margin-bottom:25px;">🎯 Quiz Configuration</h2>
                    
                    <div class="form-row" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:25px; margin-bottom:25px;">
                        <div class="form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; color:#2c3e50;">Questions per Quiz *</label>
                            <input type="number" name="questions_per_quiz" 
                                   value="<?php echo esc_attr($campaign['questions_per_quiz'] ?? 5); ?>" 
                                   min="1" max="50" required
                                   style="width:100%; padding:12px; border:2px solid #e9ecef; border-radius:6px; font-size:16px;">
                            <small style="color:#666; font-size:12px;">How many questions each participant gets</small>
                        </div>
                        
                        <div class="form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; color:#2c3e50;">Pass Score *</label>
                            <input type="number" name="pass_score" 
                                   value="<?php echo esc_attr($campaign['pass_score'] ?? 3); ?>" 
                                   min="1" required
                                   style="width:100%; padding:12px; border:2px solid #e9ecef; border-radius:6px; font-size:16px;">
                            <small style="color:#666; font-size:12px;">Minimum score to pass the quiz</small>
                        </div>
                        
                        <div class="form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; color:#2c3e50;">Time Limit (seconds)</label>
                            <input type="number" name="time_limit" 
                                   value="<?php echo esc_attr($campaign['time_limit'] ?? ''); ?>" 
                                   min="60" 
                                   style="width:100%; padding:12px; border:2px solid #e9ecef; border-radius:6px; font-size:16px;"
                                   placeholder="600">
                            <small style="color:#666; font-size:12px;">Leave empty for no time limit</small>
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions" style="padding-top:25px; border-top:2px solid #e9ecef; text-align:right;">
                    <a href="?page=vefify-campaigns" class="button button-large" style="margin-right:15px;">❌ Cancel</a>
                    <input type="submit" name="save_campaign" class="button-primary button-large" 
                           value="<?php echo $is_edit ? '💾 Update Campaign' : '✨ Create Campaign'; ?>"
                           style="padding:10px 25px;">
                </div>
            </form>
            
            <?php if ($is_edit && $campaign): ?>
            <!-- Campaign Stats -->
            <div style="margin-top:30px; background:#fff; padding:25px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                <h3 style="color:#2c3e50; margin-top:0;">📊 Campaign Statistics</h3>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:20px;">
                    <div style="text-align:center; padding:15px; background:#f8f9fa; border-radius:8px;">
                        <div style="font-size:24px; font-weight:bold; color:#007cba;"><?php echo number_format($campaign['total_participants'] ?? 0); ?></div>
                        <div style="font-size:12px; color:#666;">Total Participants</div>
                    </div>
                    <div style="text-align:center; padding:15px; background:#f8f9fa; border-radius:8px;">
                        <div style="font-size:24px; font-weight:bold; color:#28a745;"><?php echo number_format($campaign['completed_count'] ?? 0); ?></div>
                        <div style="font-size:12px; color:#666;">Completed</div>
                    </div>
                    <div style="text-align:center; padding:15px; background:#f8f9fa; border-radius:8px;">
                        <div style="font-size:24px; font-weight:bold; color:#ffc107;"><?php echo $campaign['completion_rate'] ?? 0; ?>%</div>
                        <div style="font-size:12px; color:#666;">Completion Rate</div>
                    </div>
                    <div style="text-align:center; padding:15px; background:#f8f9fa; border-radius:8px;">
                        <div style="font-size:24px; font-weight:bold; color:#6f42c1;"><?php echo number_format($campaign['avg_score'] ?? 0, 1); ?></div>
                        <div style="font-size:12px; color:#666;">Average Score</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .form-group input:focus, .form-group textarea:focus {
            border-color: #007cba !important;
            box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1) !important;
            outline: none !important;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }
        }
        </style>
        
        <?php
        echo '</div>';
    }
    
    /**
     * Handle campaign save
     */
    private function handle_campaign_save() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['vefify_campaign_nonce'], 'vefify_campaign_save')) {
            wp_die('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'max_participants' => !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : null,
            'questions_per_quiz' => intval($_POST['questions_per_quiz']),
            'pass_score' => intval($_POST['pass_score']),
            'time_limit' => !empty($_POST['time_limit']) ? intval($_POST['time_limit']) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        );
        
        if ($campaign_id) {
            $result = $this->model->update_campaign($campaign_id, $data);
            $message = 'Campaign updated successfully!';
            $redirect_id = $campaign_id;
        } else {
            $result = $this->model->create_campaign($data);
            $message = 'Campaign created successfully!';
            $redirect_id = $result;
        }
        
        if (is_wp_error($result)) {
            echo '<div class="notice notice-error is-dismissible" style="margin:20px 0; padding:15px; border-left:4px solid #dc3545;">';
            echo '<p><strong>❌ Error:</strong> ' . $result->get_error_message() . '</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-success is-dismissible" style="margin:20px 0; padding:15px; border-left:4px solid #28a745; background:#d4edda;">';
            echo '<p><strong>✅ Success:</strong> ' . $message . '</p>';
            echo '</div>';
            
            if (!$campaign_id) {
                echo '<script>
                setTimeout(function() {
                    window.location.href = "?page=vefify-campaigns&action=edit&id=' . $redirect_id . '";
                }, 2000);
                </script>';
            }
        }
    }
    
    /**
     * Display campaigns table
     */
    private function display_campaigns_table($campaigns) {
        ?>
        <div style="background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.1); margin:20px 0;">
            <table class="wp-list-table widefat fixed striped" style="border:none;">
                <thead style="background:#f8f9fa;">
                    <tr>
                        <th style="padding:15px; font-weight:600; color:#2c3e50;">Campaign</th>
                        <th style="padding:15px; font-weight:600; color:#2c3e50; text-align:center;">Status</th>
                        <th style="padding:15px; font-weight:600; color:#2c3e50; text-align:center;">Participants</th>
                        <th style="padding:15px; font-weight:600; color:#2c3e50; text-align:center;">Progress</th>
                        <th style="padding:15px; font-weight:600; color:#2c3e50; text-align:center;">Schedule</th>
                        <th style="padding:15px; font-weight:600; color:#2c3e50; text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign): ?>
                        <tr style="border-bottom:1px solid #e9ecef;">
                            <td style="padding:15px;">
                                <strong style="color:#2c3e50; font-size:16px;"><?php echo esc_html($campaign['name']); ?></strong>
                                <?php if (!empty($campaign['description'])): ?>
                                    <br><small style="color:#666; line-height:1.4;"><?php echo esc_html(wp_trim_words($campaign['description'], 12)); ?></small>
                                <?php endif; ?>
                                <div style="margin-top:5px; font-size:12px; color:#999;">ID: <?php echo $campaign['id']; ?></div>
                            </td>
                            
                            <td style="padding:15px; text-align:center;">
                                <?php echo $this->get_campaign_status_badge($campaign); ?>
                            </td>
                            
                            <td style="padding:15px; text-align:center;">
                                <div style="font-size:18px; font-weight:bold; color:#007cba;"><?php echo number_format($campaign['total_participants'] ?? 0); ?></div>
                                <small style="color:#666;">total</small>
                                <?php if (($campaign['completed_count'] ?? 0) > 0): ?>
                                    <br><small style="color:#28a745; font-weight:600;"><?php echo number_format($campaign['completed_count']); ?> completed</small>
                                <?php endif; ?>
                            </td>
                            
                            <td style="padding:15px; text-align:center;">
                                <?php 
                                $completion_rate = $campaign['completion_rate'] ?? 0;
                                $color = $completion_rate >= 80 ? '#28a745' : ($completion_rate >= 50 ? '#ffc107' : '#dc3545');
                                ?>
                                <div style="font-size:16px; font-weight:bold; color:<?php echo $color; ?>;"><?php echo $completion_rate; ?>%</div>
                                <small style="color:#666;">completion</small>
                                <div style="margin-top:5px; background:#e9ecef; height:4px; border-radius:2px; overflow:hidden;">
                                    <div style="width:<?php echo $completion_rate; ?>%; height:100%; background:<?php echo $color; ?>; transition:width 0.3s ease;"></div>
                                </div>
                            </td>
                            
                            <td style="padding:15px; text-align:center;">
                                <div style="font-size:12px; color:#666;">
                                    <div><strong>Start:</strong> <?php echo date('M j, Y', strtotime($campaign['start_date'])); ?></div>
                                    <div style="margin-top:2px;"><strong>End:</strong> <?php echo date('M j, Y', strtotime($campaign['end_date'])); ?></div>
                                </div>
                                <?php
                                $now = current_time('timestamp');
                                $start = strtotime($campaign['start_date']);
                                $end = strtotime($campaign['end_date']);
                                $total_duration = $end - $start;
                                $elapsed = min($now - $start, $total_duration);
                                $progress = $total_duration > 0 ? max(0, min(100, ($elapsed / $total_duration) * 100)) : 0;
                                ?>
                                <div style="margin-top:8px; background:#e9ecef; height:3px; border-radius:2px; overflow:hidden;">
                                    <div style="width:<?php echo $progress; ?>%; height:100%; background:#007cba; transition:width 0.3s ease;"></div>
                                </div>
                                <small style="color:#666; font-size:11px;"><?php echo round($progress); ?>% elapsed</small>
                            </td>
                            
                            <td style="padding:15px; text-align:center;">
                                <div style="display:flex; flex-direction:column; gap:5px; align-items:center;">
                                    <a href="?page=vefify-campaigns&action=edit&id=<?php echo $campaign['id']; ?>" 
                                       class="button button-small" style="padding:4px 10px; font-size:12px;">✏️ Edit</a>
                                    <a href="?page=vefify-campaigns&action=analytics&campaign_id=<?php echo $campaign['id']; ?>" 
                                       class="button button-small" style="padding:4px 10px; font-size:12px;">📊 Stats</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Get campaign status badge
     */
    private function get_campaign_status_badge($campaign) {
        if (!$campaign['is_active']) {
            return '<span style="background:#6c757d; color:white; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:600;">⏸️ Inactive</span>';
        }
        
        $now = current_time('timestamp');
        $start = strtotime($campaign['start_date']);
        $end = strtotime($campaign['end_date']);
        
        if ($now < $start) {
            return '<span style="background:#ffc107; color:#212529; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:600;">📅 Scheduled</span>';
        } elseif ($now > $end) {
            return '<span style="background:#dc3545; color:white; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:600;">⏰ Expired</span>';
        } else {
            return '<span style="background:#28a745; color:white; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:600;">✅ Active</span>';
        }
    }
    
    /**
     * Display pagination
     */
    private function display_pagination($result, $current_page) {
        if (($result['pages'] ?? 1) <= 1) {
            return;
        }
        
        echo '<div class="tablenav" style="margin:20px 0; display:flex; justify-content:space-between; align-items:center;">';
        echo '<div class="tablenav-pages">';
        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
            'total' => $result['pages'] ?? 1,
            'current' => $current_page,
            'type' => 'list'
        ));
        echo '</div>';
        echo '<div style="color:#666; font-size:14px;">';
        echo 'Showing ' . (($current_page - 1) * $result['per_page'] + 1) . '-' . min($current_page * $result['per_page'], $result['total']) . ' of ' . $result['total'] . ' campaigns';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Display admin notices
     */
    private function display_admin_notices() {
        $notice = get_transient('vefify_admin_notice');
        if ($notice) {
            delete_transient('vefify_admin_notice');
            $color = $notice['type'] === 'success' ? '#28a745' : '#dc3545';
            $bg_color = $notice['type'] === 'success' ? '#d4edda' : '#f8d7da';
            $icon = $notice['type'] === 'success' ? '✅' : '❌';
            
            printf(
                '<div class="notice notice-%s is-dismissible" style="border-left:4px solid %s; background:%s; padding:15px; margin:20px 0;"><p><strong>%s</strong> %s</p></div>',
                esc_attr($notice['type']),
                $color,
                $bg_color,
                $icon,
                esc_html($notice['message'])
            );
        }
    }
    
    /**
     * AJAX: Save campaign
     */
    public function ajax_save_campaign() {
        check_ajax_referer('vefify_campaign_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $data = $_POST['campaign_data'] ?? array();
        
        if ($campaign_id) {
            $result = $this->model->update_campaign($campaign_id, $data);
        } else {
            $result = $this->model->create_campaign($data);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => $campaign_id ? 'Campaign updated successfully!' : 'Campaign created successfully!',
                'campaign_id' => $campaign_id ?: $result
            ));
        }
    }
    
    /**
     * AJAX: Delete campaign
     */
    public function ajax_delete_campaign() {
        check_ajax_referer('vefify_campaign_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        
        if (!$campaign_id) {
            wp_send_json_error('Invalid campaign ID');
        }
        
        $result = $this->model->delete_campaign($campaign_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('Campaign deleted successfully');
        }
    }
    
    /**
     * AJAX: Get campaign data
     */
    public function ajax_get_campaign_data() {
        check_ajax_referer('vefify_campaign_ajax', 'nonce');
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        
        if (!$campaign_id) {
            wp_send_json_error('Invalid campaign ID');
        }
        
        $campaign = $this->model->get_campaign($campaign_id);
        
        if (!$campaign) {
            wp_send_json_error('Campaign not found');
        }
        
        wp_send_json_success($campaign);
    }
}