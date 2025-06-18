<?php
/**
 * Participant Manager Class
 * File: modules/participants/class-participant-manager.php
 */
class Vefify_Participant_Manager {
    
    private $model;
    
    public function __construct() {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/participants/class-participant-model.php';
        $this->model = new Vefify_Participant_Model();
    }
    
    public function display_participants_list() {
        // Get participants with filters
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : null;
        
        $args = array(
            'page' => $current_page,
            'per_page' => 20,
            'search' => $search,
            'status' => $status,
            'campaign_id' => $campaign_id
        );
        
        $result = $this->model->get_participants($args);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">👥 Participants Management</h1>
            
            <!-- Participant Statistics -->
            <?php $this->display_participant_statistics(); ?>
            
            <!-- Filters -->
            <form method="get" id="participants-filter">
                <input type="hidden" name="page" value="vefify-participants">
                
                <div class="filters-row">
                    <p class="search-box">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search participants...">
                        <input type="submit" class="button" value="Search">
                    </p>
                    
                    <select name="status">
                        <option value="all" <?php selected($status, 'all'); ?>>All Status</option>
                        <option value="started" <?php selected($status, 'started'); ?>>Started</option>
                        <option value="in_progress" <?php selected($status, 'in_progress'); ?>>In Progress</option>
                        <option value="completed" <?php selected($status, 'completed'); ?>>Completed</option>
                        <option value="abandoned" <?php selected($status, 'abandoned'); ?>>Abandoned</option>
                    </select>
                    
                    <select name="campaign_id">
                        <option value="">All Campaigns</option>
                        <?php
                        global $wpdb;
                        $campaigns = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}vefify_campaigns ORDER BY name");
                        foreach ($campaigns as $campaign) {
                            echo '<option value="' . $campaign->id . '" ' . selected($campaign_id, $campaign->id, false) . '>' . esc_html($campaign->name) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <input type="submit" class="button" value="Filter">
                </div>
            </form>
            
            <!-- Participants Table -->
            <form method="post" id="participants-form">
                <?php wp_nonce_field('vefify_participant_bulk'); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action">
                            <option value="-1">Bulk Actions</option>
                            <option value="send_message">Send Message</option>
                            <option value="export_data">Export Data</option>
                            <option value="add_to_segment">Add to Segment</option>
                        </select>
                        <input type="submit" class="button action" value="Apply">
                    </div>
                    
                    <div class="alignright actions">
                        <button type="button" id="export-all" class="button">Export All</button>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped participants">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th class="manage-column column-participant">Participant</th>
                            <th class="manage-column column-campaign">Campaign</th>
                            <th class="manage-column column-status">Status</th>
                            <th class="manage-column column-score">Score</th>
                            <th class="manage-column column-time">Time</th>
                            <th class="manage-column column-gift">Gift</th>
                            <th class="manage-column column-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($result['participants'])): ?>
                            <?php foreach ($result['participants'] as $participant): ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="participant_ids[]" value="<?php echo $participant['id']; ?>">
                                    </th>
                                    <td class="column-participant">
                                        <strong><?php echo esc_html($participant['participant_name']); ?></strong>
                                        <div class="participant-meta">
                                            <?php if ($participant['participant_email']): ?>
                                                <div><?php echo esc_html($participant['participant_email']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($participant['participant_phone']): ?>
                                                <div><?php echo esc_html($participant['participant_phone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="column-campaign">
                                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $participant['campaign_id']); ?>">
                                            <?php echo esc_html($participant['campaign_name']); ?>
                                        </a>
                                    </td>
                                    <td class="column-status">
                                        <?php echo $this->get_status_badge($participant['quiz_status']); ?>
                                    </td>
                                    <td class="column-score">
                                        <?php if ($participant['final_score'] !== null): ?>
                                            <strong><?php echo $participant['final_score']; ?></strong>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-time">
                                        <div class="time-info">
                                            <div><strong>Started:</strong> <?php echo date('M j, Y H:i', strtotime($participant['start_time'])); ?></div>
                                            <?php if ($participant['end_time']): ?>
                                                <div><strong>Completed:</strong> <?php echo date('M j, Y H:i', strtotime($participant['end_time'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="column-gift">
                                        <?php if ($participant['gift_code']): ?>
                                            <span class="gift-code"><?php echo esc_html($participant['gift_code']); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-actions">
                                        <a href="<?php echo admin_url('admin.php?page=vefify-participants&action=view&id=' . $participant['id']); ?>" 
                                           class="button button-small">View Details</a>
                                        <?php if ($participant['participant_email']): ?>
                                            <button type="button" class="button button-small send-message" 
                                                    data-participant-id="<?php echo $participant['id']; ?>"
                                                    data-email="<?php echo esc_attr($participant['participant_email']); ?>">
                                                Send Message
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-participants">
                                    <div class="no-participants-message">
                                        <h3>No participants found</h3>
                                        <p>Participants will appear here once they start taking quizzes.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        
        <style>
        .participant-statistics { display: flex; gap: 20px; margin: 20px 0; }
        .stat-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; flex: 1; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #0073aa; }
        .stat-label { color: #666; font-size: 14px; margin-top: 5px; }
        .filters-row { display: flex; gap: 10px; align-items: center; margin: 15px 0; }
        .participant-meta { margin-top: 5px; font-size: 12px; color: #666; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .status-badge.started { background: #0073aa; }
        .status-badge.in_progress { background: #ff9800; }
        .status-badge.completed { background: #46b450; }
        .status-badge.abandoned { background: #dc3232; }
        .gift-code { background: #333; color: #0f0; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 11px; }
        .time-info { font-size: 12px; }
        .time-info div { margin: 2px 0; }
        .no-participants-message { text-align: center; padding: 40px 20px; }
        </style>
        <?php
    }
    
    public function display_participant_details() {
        $participant_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$participant_id) {
            wp_die('Participant ID required');
        }
        
        $analytics = $this->model->get_participant_analytics($participant_id);
        if (!$analytics) {
            wp_die('Participant not found');
        }
        
        $participant = $analytics['participant'];
        ?>
        <div class="wrap">
            <h1>👤 Participant Details: <?php echo esc_html($participant['participant_name']); ?></h1>
            
            <div class="participant-details-container">
                <!-- Basic Information -->
                <div class="postbox">
                    <h2 class="hndle">📋 Basic Information</h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th>Name:</th>
                                <td><?php echo esc_html($participant['participant_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo esc_html($participant['participant_email']); ?></td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td><?php echo esc_html($participant['participant_phone']); ?></td>
                            </tr>
                            <tr>
                                <th>Campaign:</th>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $participant['campaign_id']); ?>">
                                        <?php echo esc_html($participant['campaign_name']); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td><?php echo $this->get_status_badge($participant['quiz_status']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Performance Metrics -->
                <div class="postbox">
                    <h2 class="hndle">📊 Performance Metrics</h2>
                    <div class="inside">
                        <div class="performance-grid">
                            <div class="metric-card">
                                <div class="metric-number"><?php echo $analytics['performance_metrics']['score']; ?></div>
                                <div class="metric-label">Final Score</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-number"><?php echo $analytics['performance_metrics']['percentage']; ?>%</div>
                                <div class="metric-label">Percentage</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-number"><?php echo $analytics['time_spent'] ? gmdate("i:s", $analytics['time_spent']) : '-'; ?></div>
                                <div class="metric-label">Time Spent</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-number"><?php echo $analytics['performance_metrics']['time_per_question'] ?: '-'; ?>s</div>
                                <div class="metric-label">Avg Time/Question</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Answer Analysis -->
                <?php if (!empty($analytics['answer_analysis'])): ?>
                    <div class="postbox">
                        <h2 class="hndle">✅ Answer Analysis</h2>
                        <div class="inside">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Question</th>
                                        <th>User Answer</th>
                                        <th>Correct Answer</th>
                                        <th>Result</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analytics['answer_analysis'] as $question_id => $analysis): ?>
                                        <tr>
                                            <td><?php echo esc_html($analysis['question_text']); ?></td>
                                            <td><?php echo is_array($analysis['user_answer']) ? implode(', ', $analysis['user_answer']) : $analysis['user_answer']; ?></td>
                                            <td><?php echo implode(', ', $analysis['correct_answer']); ?></td>
                                            <td>
                                                <?php if ($analysis['is_correct']): ?>
                                                    <span class="correct-answer">✅ Correct</span>
                                                <?php else: ?>
                                                    <span class="incorrect-answer">❌ Incorrect</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Gift Information -->
                <?php if ($participant['gift_code']): ?>
                    <div class="postbox">
                        <h2 class="hndle">🎁 Gift Information</h2>
                        <div class="inside">
                            <p><strong>Gift Code:</strong> <span class="gift-code"><?php echo esc_html($participant['gift_code']); ?></span></p>
                            <p><strong>Status:</strong> Awarded</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .participant-details-container { max-width: 800px; }
        .performance-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .metric-card { text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px; }
        .metric-number { font-size: 24px; font-weight: bold; color: #0073aa; }
        .metric-label { font-size: 12px; color: #666; margin-top: 5px; }
        .correct-answer { color: #46b450; font-weight: bold; }
        .incorrect-answer { color: #dc3232; font-weight: bold; }
        </style>
        <?php
    }
    
    public function display_participant_analytics() {
        ?>
        <div class="wrap">
            <h1>📈 Participant Analytics</h1>
            
            <div class="analytics-dashboard">
                <!-- Overview Stats -->
                <?php $this->display_participant_statistics(); ?>
                
                <!-- Charts -->
                <div class="analytics-charts">
                    <div class="chart-container">
                        <h3>📊 Participation Trends</h3>
                        <canvas id="participation-trends-chart" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3>🎯 Score Distribution</h3>
                        <canvas id="score-distribution-chart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Detailed Reports -->
                <div class="detailed-reports">
                    <h3>📋 Detailed Reports</h3>
                    <?php $this->display_analytics_table(); ?>
                </div>
            </div>
        </div>
        
        <style>
        .analytics-dashboard { }
        .analytics-charts { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0; }
        .chart-container { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; }
        .detailed-reports { margin: 30px 0; }
        </style>
        <?php
    }
    
    private function display_participant_statistics() {
        $stats = $this->model->get_participant_statistics();
        ?>
        <div class="participant-statistics">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_participants']); ?></div>
                <div class="stat-label">Total Participants</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['active_participants']); ?></div>
                <div class="stat-label">Active (30 days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['completed_quizzes']); ?></div>
                <div class="stat-label">Completed Quizzes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['completion_rate']; ?>%</div>
                <div class="stat-label">Completion Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['average_score']; ?></div>
                <div class="stat-label">Average Score</div>
            </div>
        </div>
        <?php
    }
    
    private function get_status_badge($status) {
        $badges = array(
            'started' => '<span class="status-badge started">Started</span>',
            'in_progress' => '<span class="status-badge in_progress">In Progress</span>',
            'completed' => '<span class="status-badge completed">Completed</span>',
            'abandoned' => '<span class="status-badge abandoned">Abandoned</span>'
        );
        
        return isset($badges[$status]) ? $badges[$status] : '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
    
    private function display_analytics_table() {
        // Implementation for detailed analytics table
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                    <th>Trend</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Daily New Participants</td>
                    <td>45</td>
                    <td><span style="color: green;">+23%</span></td>
                </tr>
                <tr>
                    <td>Average Session Duration</td>
                    <td>8.5 minutes</td>
                    <td><span style="color: red;">-2%</span></td>
                </tr>
                <tr>
                    <td>Mobile vs Desktop</td>
                    <td>73% / 27%</td>
                    <td><span style="color: green;">+5% mobile</span></td>
                </tr>
            </tbody>
        </table>
        <?php
    }
}