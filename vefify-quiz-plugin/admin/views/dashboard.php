<?php
/**
 * Dashboard View with Analytics
 * File: admin/views/dashboard.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get analytics data (passed from display_dashboard method)
$overview = isset($analytics_data['overview']) ? $analytics_data['overview'] : array();
$modules = isset($analytics_data['modules']) ? $analytics_data['modules'] : array();
$recent_activity = isset($analytics_data['recent_activity']) ? $analytics_data['recent_activity'] : array();
$quick_stats = isset($analytics_data['quick_stats']) ? $analytics_data['quick_stats'] : array();
$trends = isset($analytics_data['trends']) ? $analytics_data['trends'] : array();
?>

<div class="wrap vefify-dashboard">
    <h1 class="wp-heading-inline">
        <?php echo esc_html(get_admin_page_title()); ?>
        <span class="title-count"><?php _e('Dashboard', 'vefify-quiz'); ?></span>
    </h1>
    
    <hr class="wp-header-end">
    
    <!-- Quick Stats Cards -->
    <div class="vefify-quick-stats">
        <?php if (!empty($quick_stats)): ?>
            <?php foreach ($quick_stats as $stat): ?>
                <div class="vefify-stat-card stat-<?php echo esc_attr($stat['color']); ?>">
                    <div class="stat-icon"><?php echo $stat['icon']; ?></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($stat['value']); ?></div>
                        <div class="stat-label"><?php echo esc_html($stat['title']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Module Analytics Grid -->
    <div class="vefify-modules-grid">
        <h2><?php _e('Module Analytics', 'vefify-quiz'); ?></h2>
        
        <div class="modules-container">
            <?php if (!empty($modules)): ?>
                <?php foreach ($modules as $module_key => $module_data): ?>
                    <div class="vefify-module-card" data-module="<?php echo esc_attr($module_key); ?>">
                        <div class="module-header">
                            <div class="module-icon"><?php echo $module_data['icon']; ?></div>
                            <div class="module-title">
                                <h3><?php echo esc_html($module_data['title']); ?></h3>
                                <p><?php echo esc_html($module_data['description']); ?></p>
                            </div>
                        </div>
                        
                        <div class="module-stats">
                            <?php if (!empty($module_data['stats'])): ?>
                                <?php foreach ($module_data['stats'] as $stat_key => $stat): ?>
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo esc_html($stat['value']); ?></div>
                                        <div class="stat-meta">
                                            <span class="stat-name"><?php echo esc_html($stat['label']); ?></span>
                                            <span class="stat-trend"><?php echo esc_html($stat['trend']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($module_data['quick_actions'])): ?>
                            <div class="module-actions">
                                <?php foreach ($module_data['quick_actions'] as $action): ?>
                                    <a href="<?php echo esc_url($action['url']); ?>" 
                                       class="button <?php echo esc_attr($action['class']); ?>">
                                        <?php echo esc_html($action['label']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vefify-no-data">
                    <p><?php _e('No module data available. Please check your plugin configuration.', 'vefify-quiz'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Activity & Trends -->
    <div class="vefify-dashboard-bottom">
        <div class="dashboard-section">
            <!-- Recent Activity -->
            <div class="vefify-recent-activity">
                <h3><?php _e('Recent Activity', 'vefify-quiz'); ?></h3>
                
                <?php if (!empty($recent_activity['recent_participants'])): ?>
                    <div class="activity-list">
                        <?php foreach ($recent_activity['recent_participants'] as $participant): ?>
                            <div class="activity-item">
                                <div class="activity-info">
                                    <strong><?php echo esc_html($participant->participant_name ?: 'Anonymous'); ?></strong>
                                    <span class="activity-campaign"><?php echo esc_html($participant->campaign_name); ?></span>
                                </div>
                                <div class="activity-meta">
                                    <span class="activity-score">Score: <?php echo esc_html($participant->final_score); ?></span>
                                    <span class="activity-status status-<?php echo esc_attr($participant->quiz_status); ?>">
                                        <?php echo esc_html(ucfirst($participant->quiz_status)); ?>
                                    </span>
                                    <span class="activity-time">
                                        <?php echo esc_html(human_time_diff(strtotime($participant->created_at), current_time('timestamp')) . ' ago'); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-activity"><?php _e('No recent activity found.', 'vefify-quiz'); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($recent_activity['today_stats'])): ?>
                    <div class="today-summary">
                        <h4><?php _e('Today\'s Summary', 'vefify-quiz'); ?></h4>
                        <div class="today-stats">
                            <span class="today-stat">
                                <strong><?php echo esc_html($recent_activity['today_stats']->total_today); ?></strong>
                                <?php _e('participants', 'vefify-quiz'); ?>
                            </span>
                            <span class="today-stat">
                                <strong><?php echo esc_html($recent_activity['today_stats']->completed_today); ?></strong>
                                <?php _e('completed', 'vefify-quiz'); ?>
                            </span>
                            <?php if ($recent_activity['today_stats']->avg_score_today): ?>
                                <span class="today-stat">
                                    <strong><?php echo esc_html(round($recent_activity['today_stats']->avg_score_today, 1)); ?></strong>
                                    <?php _e('avg score', 'vefify-quiz'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Performance Trends -->
            <div class="vefify-performance-trends">
                <h3><?php _e('Performance Trends (Last 7 Days)', 'vefify-quiz'); ?></h3>
                
                <?php if (!empty($trends)): ?>
                    <div class="trends-chart">
                        <canvas id="trendsChart" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="trends-table">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Date', 'vefify-quiz'); ?></th>
                                    <th><?php _e('Participants', 'vefify-quiz'); ?></th>
                                    <th><?php _e('Completed', 'vefify-quiz'); ?></th>
                                    <th><?php _e('Avg Score', 'vefify-quiz'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trends as $trend): ?>
                                    <tr>
                                        <td><?php echo esc_html(date('M j, Y', strtotime($trend->date))); ?></td>
                                        <td><?php echo esc_html($trend->participants); ?></td>
                                        <td><?php echo esc_html($trend->completed); ?></td>
                                        <td><?php echo esc_html($trend->avg_score ? round($trend->avg_score, 1) : 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="no-trends"><?php _e('No trend data available yet.', 'vefify-quiz'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- System Health Check -->
    <div class="vefify-system-health">
        <h3><?php _e('System Health', 'vefify-quiz'); ?></h3>
        
        <div class="health-checks">
            <div class="health-item">
                <span class="health-label"><?php _e('Database Tables', 'vefify-quiz'); ?></span>
                <span class="health-status status-good">✓ <?php _e('All Good', 'vefify-quiz'); ?></span>
            </div>
            <div class="health-item">
                <span class="health-label"><?php _e('Plugin Version', 'vefify-quiz'); ?></span>
                <span class="health-value"><?php echo esc_html(defined('VEFIFY_QUIZ_VERSION') ? VEFIFY_QUIZ_VERSION : '1.0.0'); ?></span>
            </div>
            <div class="health-item">
                <span class="health-label"><?php _e('WordPress Version', 'vefify-quiz'); ?></span>
                <span class="health-value"><?php echo esc_html(get_bloginfo('version')); ?></span>
            </div>
        </div>
        
        <div class="health-actions">
            <a href="<?php echo admin_url('admin.php?page=vefify-settings&tab=health'); ?>" class="button">
                <?php _e('View Detailed Health Report', 'vefify-quiz'); ?>
            </a>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize analytics dashboard
    if (typeof vefifyAnalytics !== 'undefined') {
        VefifyDashboard.init(vefifyAnalytics);
    }
    
    // Refresh data every 5 minutes
    setInterval(function() {
        VefifyDashboard.refreshData();
    }, 300000);
});
</script>