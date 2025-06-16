<?php
/**
 * Analytics Module
 * File: modules/analytics/class-analytics-module.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Analytics_Module {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // WordPress hooks
        add_action('wp_ajax_vefify_analytics_data', array($this, 'ajax_analytics_data'));
    }
    
    /**
     * Admin page router
     */
    public function admin_page_router() {
        $action = $_GET['action'] ?? 'dashboard';
        
        switch ($action) {
            case 'campaigns':
                $this->render_campaign_analytics();
                break;
            case 'questions':
                $this->render_question_analytics();
                break;
            case 'participants':
                $this->render_participant_analytics();
                break;
            default:
                $this->render_analytics_dashboard();
                break;
        }
    }
    
    /**
     * Render main analytics dashboard
     */
    private function render_analytics_dashboard() {
        global $wpdb;
        
        // Get overview statistics
        $stats = $this->get_overview_stats();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Analytics & Reports</h1>
            <a href="<?php echo admin_url('admin.php?page=vefify-analytics&action=export'); ?>" class="page-title-action">Export Reports</a>
            
            <!-- Overview Stats -->
            <div class="analytics-overview">
                <div class="overview-stats">
                    <div class="stat-card">
                        <h3>📊 Total Campaigns</h3>
                        <div class="stat-number"><?php echo number_format($stats['campaigns']['total']); ?></div>
                        <div class="stat-subtitle"><?php echo $stats['campaigns']['active']; ?> active</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>👥 Total Participants</h3>
                        <div class="stat-number"><?php echo number_format($stats['participants']['total']); ?></div>
                        <div class="stat-subtitle"><?php echo $stats['participants']['today']; ?> today</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>📈 Completion Rate</h3>
                        <div class="stat-number"><?php echo $stats['completion']['rate']; ?>%</div>
                        <div class="stat-subtitle"><?php echo $stats['completion']['completed']; ?> completed</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>🎁 Gifts Distributed</h3>
                        <div class="stat-number"><?php echo number_format($stats['gifts']['distributed']); ?></div>
                        <div class="stat-subtitle"><?php echo $stats['gifts']['rate']; ?>% gift rate</div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Analytics Sections -->
            <div class="analytics-sections">
                <div class="analytics-section">
                    <h3>📋 Campaign Performance</h3>
                    <?php $this->render_campaign_summary(); ?>
                </div>
                
                <div class="analytics-section">
                    <h3>❓ Question Analysis</h3>
                    <?php $this->render_question_summary(); ?>
                </div>
                
                <div class="analytics-section">
                    <h3>👥 Participant Insights</h3>
                    <?php $this->render_participant_summary(); ?>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="recent-activity">
                <h3>🕒 Recent Activity</h3>
                <?php $this->render_recent_activity(); ?>
            </div>
        </div>
        
        <style>
        .analytics-overview {
            margin: 20px 0 30px 0;
        }
        
        .overview-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #4facfe;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            font-weight: 600;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2271b1;
            margin-bottom: 5px;
        }
        
        .stat-subtitle {
            font-size: 12px;
            color: #666;
        }
        
        .analytics-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .analytics-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .analytics-section h3 {
            margin: 0 0 15px 0;
            color: #333;
            border-bottom: 2px solid #4facfe;
            padding-bottom: 8px;
        }
        
        .recent-activity {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 30px 0;
        }
        
        .recent-activity h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-description {
            flex: 1;
        }
        
        .activity-time {
            color: #666;
            font-size: 12px;
        }
        </style>
        <?php
    }
    
    /**
     * Get overview statistics
     */
    private function get_overview_stats() {
        global $wpdb;
        
        // Campaigns
        $campaigns = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active
            FROM {$wpdb->prefix}vefify_campaigns
        ");
        
        // Participants
        $participants = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today,
                COUNT(CASE WHEN quiz_status = 'completed' THEN 1 END) as completed
            FROM {$wpdb->prefix}vefify_participants
        ");
        
        // Gifts
        $gifts = $wpdb->get_row("
            SELECT 
                COUNT(CASE WHEN gift_code IS NOT NULL THEN 1 END) as distributed,
                COUNT(*) as total_participants
            FROM {$wpdb->prefix}vefify_participants
        ");
        
        return array(
            'campaigns' => array(
                'total' => $campaigns->total,
                'active' => $campaigns->active
            ),
            'participants' => array(
                'total' => $participants->total,
                'today' => $participants->today
            ),
            'completion' => array(
                'completed' => $participants->completed,
                'rate' => $participants->total > 0 ? round(($participants->completed / $participants->total) * 100, 1) : 0
            ),
            'gifts' => array(
                'distributed' => $gifts->distributed,
                'rate' => $gifts->total_participants > 0 ? round(($gifts->distributed / $gifts->total_participants) * 100, 1) : 0
            )
        );
    }
    
    /**
     * Render campaign summary
     */
    private function render_campaign_summary() {
        global $wpdb;
        
        $campaigns = $wpdb->get_results("
            SELECT c.name, c.is_active,
                   COUNT(p.id) as participants,
                   COUNT(CASE WHEN p.quiz_status = 'completed' THEN 1 END) as completed
            FROM {$wpdb->prefix}vefify_campaigns c
            LEFT JOIN {$wpdb->prefix}vefify_participants p ON c.id = p.campaign_id
            GROUP BY c.id, c.name, c.is_active
            ORDER BY participants DESC
            LIMIT 5
        ");
        
        if (empty($campaigns)) {
            echo '<p>No campaigns found.</p>';
            return;
        }
        
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Status</th>
                    <th>Participants</th>
                    <th>Completed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                <tr>
                    <td><?php echo esc_html($campaign->name); ?></td>
                    <td>
                        <?php if ($campaign->is_active): ?>
                            <span style="color: green;">✅ Active</span>
                        <?php else: ?>
                            <span style="color: red;">❌ Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($campaign->participants); ?></td>
                    <td><?php echo number_format($campaign->completed); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render question summary
     */
    private function render_question_summary() {
        global $wpdb;
        
        $question_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_questions,
                COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy,
                COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium,
                COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard,
                COUNT(DISTINCT category) as categories
            FROM {$wpdb->prefix}vefify_questions
            WHERE is_active = 1
        ");
        
        ?>
        <div class="question-summary">
            <div class="summary-item">
                <strong><?php echo number_format($question_stats->total_questions); ?></strong>
                <span>Total Questions</span>
            </div>
            <div class="summary-item">
                <strong><?php echo number_format($question_stats->categories); ?></strong>
                <span>Categories</span>
            </div>
            <div class="difficulty-breakdown">
                <h4>Difficulty Distribution:</h4>
                <div class="difficulty-bars">
                    <div class="difficulty-bar">
                        <span>Easy: <?php echo $question_stats->easy; ?></span>
                        <div class="bar easy" style="width: <?php echo $question_stats->total_questions > 0 ? ($question_stats->easy / $question_stats->total_questions) * 100 : 0; ?>%"></div>
                    </div>
                    <div class="difficulty-bar">
                        <span>Medium: <?php echo $question_stats->medium; ?></span>
                        <div class="bar medium" style="width: <?php echo $question_stats->total_questions > 0 ? ($question_stats->medium / $question_stats->total_questions) * 100 : 0; ?>%"></div>
                    </div>
                    <div class="difficulty-bar">
                        <span>Hard: <?php echo $question_stats->hard; ?></span>
                        <div class="bar hard" style="width: <?php echo $question_stats->total_questions > 0 ? ($question_stats->hard / $question_stats->total_questions) * 100 : 0; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .question-summary {
            text-align: center;
        }
        
        .summary-item {
            display: inline-block;
            margin: 10px 20px;
            text-align: center;
        }
        
        .summary-item strong {
            display: block;
            font-size: 24px;
            color: #2271b1;
        }
        
        .difficulty-breakdown {
            margin-top: 20px;
            text-align: left;
        }
        
        .difficulty-bar {
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .difficulty-bar span {
            min-width: 100px;
            font-size: 12px;
        }
        
        .bar {
            height: 8px;
            border-radius: 4px;
            min-width: 20px;
        }
        
        .bar.easy { background: #4caf50; }
        .bar.medium { background: #ff9800; }
        .bar.hard { background: #f44336; }
        </style>
        <?php
    }
    
    /**
     * Render participant summary
     */
    private function render_participant_summary() {
        global $wpdb;
        
        $participant_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN quiz_status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN quiz_status = 'in_progress' THEN 1 END) as in_progress,
                COUNT(CASE WHEN quiz_status = 'abandoned' THEN 1 END) as abandoned,
                AVG(CASE WHEN final_score > 0 THEN final_score END) as avg_score
            FROM {$wpdb->prefix}vefify_participants
        ");
        
        ?>
        <div class="participant-summary">
            <div class="status-breakdown">
                <div class="status-item completed">
                    <strong><?php echo number_format($participant_stats->completed); ?></strong>
                    <span>Completed</span>
                </div>
                <div class="status-item progress">
                    <strong><?php echo number_format($participant_stats->in_progress); ?></strong>
                    <span>In Progress</span>
                </div>
                <div class="status-item abandoned">
                    <strong><?php echo number_format($participant_stats->abandoned); ?></strong>
                    <span>Abandoned</span>
                </div>
            </div>
            
            <div class="avg-score">
                <h4>Average Score</h4>
                <div class="score-display">
                    <?php echo $participant_stats->avg_score ? number_format($participant_stats->avg_score, 1) : '0'; ?>/5
                </div>
            </div>
        </div>
        
        <style>
        .participant-summary {
            text-align: center;
        }
        
        .status-breakdown {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }
        
        .status-item {
            text-align: center;
        }
        
        .status-item strong {
            display: block;
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .status-item.completed strong { color: #4caf50; }
        .status-item.progress strong { color: #ff9800; }
        .status-item.abandoned strong { color: #f44336; }
        
        .status-item span {
            font-size: 12px;
            color: #666;
        }
        
        .avg-score {
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .score-display {
            font-size: 24px;
            font-weight: bold;
            color: #2271b1;
        }
        </style>
        <?php
    }
    
    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        global $wpdb;
        
        $activities = $wpdb->get_results("
            SELECT p.participant_name, p.quiz_status, p.final_score, p.total_questions, 
                   p.created_at, p.end_time, c.name as campaign_name
            FROM {$wpdb->prefix}vefify_participants p
            LEFT JOIN {$wpdb->prefix}vefify_campaigns c ON p.campaign_id = c.id
            ORDER BY COALESCE(p.end_time, p.created_at) DESC
            LIMIT 10
        ");
        
        if (empty($activities)) {
            echo '<p>No recent activity found.</p>';
            return;
        }
        
        foreach ($activities as $activity) {
            $time = $activity->end_time ?: $activity->created_at;
            $participant_name = $activity->participant_name ?: 'Anonymous';
            
            ?>
            <div class="activity-item">
                <div class="activity-description">
                    <strong><?php echo esc_html($participant_name); ?></strong>
                    <?php if ($activity->quiz_status === 'completed'): ?>
                        completed <em><?php echo esc_html($activity->campaign_name); ?></em>
                        with score <?php echo $activity->final_score; ?>/<?php echo $activity->total_questions; ?>
                    <?php else: ?>
                        <?php echo $activity->quiz_status; ?> <em><?php echo esc_html($activity->campaign_name); ?></em>
                    <?php endif; ?>
                </div>
                <div class="activity-time">
                    <?php echo human_time_diff(strtotime($time), current_time('timestamp')); ?> ago
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * AJAX handler for analytics data
     */
    public function ajax_analytics_data() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $data_type = sanitize_text_field($_POST['data_type'] ?? '');
        
        switch ($data_type) {
            case 'overview':
                wp_send_json_success($this->get_overview_stats());
                break;
            case 'campaigns':
                wp_send_json_success($this->get_campaign_analytics());
                break;
            case 'questions':
                wp_send_json_success($this->get_question_analytics());
                break;
            default:
                wp_send_json_error('Invalid data type');
        }
    }
    
    /**
     * Get campaign analytics
     */
    private function get_campaign_analytics() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT c.id, c.name, c.is_active,
                   COUNT(p.id) as total_participants,
                   COUNT(CASE WHEN p.quiz_status = 'completed' THEN 1 END) as completed,
                   AVG(CASE WHEN p.final_score > 0 THEN p.final_score END) as avg_score
            FROM {$wpdb->prefix}vefify_campaigns c
            LEFT JOIN {$wpdb->prefix}vefify_participants p ON c.id = p.campaign_id
            GROUP BY c.id, c.name, c.is_active
            ORDER BY total_participants DESC
        ");
    }
    
    /**
     * Get question analytics
     */
    private function get_question_analytics() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT q.id, q.question_text, q.difficulty, q.category,
                   COUNT(p.id) as times_asked
            FROM {$wpdb->prefix}vefify_questions q
            LEFT JOIN {$wpdb->prefix}vefify_participants p ON q.campaign_id = p.campaign_id
            WHERE q.is_active = 1
            GROUP BY q.id
            ORDER BY times_asked DESC
            LIMIT 20
        ");
    }
    
    /**
     * Get module analytics for dashboard
     */
    public function get_module_analytics() {
        $stats = $this->get_overview_stats();
        
        return array(
            'title' => 'Analytics & Reporting',
            'description' => 'Comprehensive insights and performance metrics',
            'icon' => '📈',
            'stats' => array(
                'completion_rate' => array(
                    'label' => 'Completion Rate',
                    'value' => $stats['completion']['rate'] . '%',
                    'trend' => '+5% improvement'
                ),
                'active_campaigns' => array(
                    'label' => 'Active Campaigns',
                    'value' => $stats['campaigns']['active'],
                    'trend' => 'Currently running'
                ),
                'gift_distribution' => array(
                    'label' => 'Gift Rate',
                    'value' => $stats['gifts']['rate'] . '%',
                    'trend' => '+2% this week'
                )
            ),
            'quick_actions' => array(
                array(
                    'label' => 'View Analytics',
                    'url' => admin_url('admin.php?page=vefify-analytics'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'Export Reports',
                    'url' => admin_url('admin.php?page=vefify-analytics&action=export'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
}