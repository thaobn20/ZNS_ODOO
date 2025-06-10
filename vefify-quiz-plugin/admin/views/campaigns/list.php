<?php
/**
 * Campaign Management
 * File: admin/views/campaigns/list.php
 */
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Campaigns</h1>
    <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=create'); ?>" class="page-title-action">Add New</a>
    
    <div class="vefify-campaigns-list">
        <?php
        $campaigns = $wpdb->get_results("
            SELECT c.*, 
                   COUNT(u.id) as total_participants,
                   COUNT(CASE WHEN u.completed_at IS NOT NULL THEN 1 END) as completed_count
            FROM {$prefix}campaigns c
            LEFT JOIN {$prefix}quiz_users u ON c.id = u.campaign_id
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Campaign Name</th>
                    <th>Status</th>
                    <th>Participants</th>
                    <th>Completed</th>
                    <th>Date Range</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($campaign->name); ?></strong>
                        <div class="row-actions">
                            <span class="view">
                                <a href="<?php echo vefify_get_campaign_url($campaign->id); ?>" target="_blank">View</a> |
                            </span>
                            <span class="edit">
                                <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $campaign->id); ?>">Edit</a> |
                            </span>
                            <span class="delete">
                                <a href="#" onclick="deleteCampaign(<?php echo $campaign->id; ?>)">Delete</a>
                            </span>
                        </div>
                    </td>
                    <td>
                        <?php if ($campaign->is_active): ?>
                            <span class="vefify-status active">Active</span>
                        <?php else: ?>
                            <span class="vefify-status inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo intval($campaign->total_participants); ?></td>
                    <td><?php echo intval($campaign->completed_count); ?></td>
                    <td>
                        <?php echo mysql2date('M j, Y', $campaign->start_date); ?> - 
                        <?php echo mysql2date('M j, Y', $campaign->end_date); ?>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=vefify-analytics&campaign_id=' . $campaign->id); ?>" class="button button-small">
                            Analytics
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.vefify-status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.vefify-status.active {
    background: #00a32a;
    color: white;
}

.vefify-status.inactive {
    background: #ddd;
    color: #666;
}
</style>