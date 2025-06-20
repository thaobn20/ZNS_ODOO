<?php
/**
 * Admin Dashboard
 * File: admin/views/dashboard.php
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Get statistics
global $wpdb;
$prefix = $wpdb->prefix . 'vefify_';

$stats = [
    'total_campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}campaigns"),
    'active_campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}campaigns WHERE is_active = 1"),
    'total_participants' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}quiz_users"),
    'completed_quizzes' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}quiz_users WHERE completed_at IS NOT NULL"),
    'total_gifts_claimed' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}quiz_users WHERE gift_status = 'claimed'")
];
?>

<div class="wrap">
    <h1>Vefify Quiz Dashboard</h1>
    
    <div class="vefify-dashboard">
        <div class="vefify-stats-grid">
            <div class="vefify-stat-card">
                <h3>Total Campaigns</h3>
                <div class="stat-number"><?php echo $stats['total_campaigns']; ?></div>
            </div>
            
            <div class="vefify-stat-card">
                <h3>Active Campaigns</h3>
                <div class="stat-number"><?php echo $stats['active_campaigns']; ?></div>
            </div>
            
            <div class="vefify-stat-card">
                <h3>Total Participants</h3>
                <div class="stat-number"><?php echo $stats['total_participants']; ?></div>
            </div>
            
            <div class="vefify-stat-card">
                <h3>Completed Quizzes</h3>
                <div class="stat-number"><?php echo $stats['completed_quizzes']; ?></div>
            </div>
            
            <div class="vefify-stat-card">
                <h3>Gifts Claimed</h3>
                <div class="stat-number"><?php echo $stats['total_gifts_claimed']; ?></div>
            </div>
        </div>
        
        <div class="vefify-quick-actions">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=create'); ?>" class="button button-primary">
                    Create New Campaign
                </a>
                <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">
                    Manage Questions
                </a>
                <a href="<?php echo admin_url('admin.php?page=vefify-gifts'); ?>" class="button">
                    Manage Gifts
                </a>
                <a href="<?php echo admin_url('admin.php?page=vefify-analytics'); ?>" class="button">
                    View Analytics
                </a>
            </div>
        </div>
        
        <div class="vefify-recent-activity">
            <h2>Recent Activity</h2>
            <?php
            $recent_users = $wpdb->get_results("
                SELECT u.full_name, u.phone_number, u.score, u.completed_at, c.name as campaign_name
                FROM {$prefix}quiz_users u
                JOIN {$prefix}campaigns c ON u.campaign_id = c.id
                WHERE u.completed_at IS NOT NULL
                ORDER BY u.completed_at DESC
                LIMIT 10
            ");
            ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Campaign</th>
                        <th>Score</th>
                        <th>Completed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_users as $user): ?>
                    <tr>
                        <td><?php echo esc_html($user->full_name); ?></td>
                        <td><?php echo esc_html($user->campaign_name); ?></td>
                        <td><?php echo esc_html($user->score); ?></td>
                        <td><?php echo esc_html(mysql2date('M j, Y g:i A', $user->completed_at)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.vefify-dashboard {
    max-width: 1200px;
}

.vefify-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.vefify-stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.vefify-stat-card h3 {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 14px;
    text-transform: uppercase;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #2271b1;
}

.vefify-quick-actions,
.vefify-recent-activity {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-buttons .button {
    padding: 10px 20px;
}
</style>
