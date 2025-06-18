<?php
/**
 * Gift Management
 * File: admin/views/gifts/manage.php
 */
?>

<div class="wrap">
    <h1>Gift Management</h1>
    
    <div class="vefify-gift-filters">
        <select id="campaignFilter" onchange="filterGifts()">
            <option value="">All Campaigns</option>
            <?php
            $campaigns = $wpdb->get_results("SELECT id, name FROM {$prefix}campaigns ORDER BY name");
            foreach ($campaigns as $campaign) {
                echo '<option value="' . $campaign->id . '">' . esc_html($campaign->name) . '</option>';
            }
            ?>
        </select>
        
        <button type="button" class="button button-primary" onclick="openGiftModal()">Add New Gift</button>
    </div>
    
    <div class="vefify-gifts-grid">
        <?php
        $gifts = $wpdb->get_results("
            SELECT g.*, c.name as campaign_name,
                   COALESCE(g.max_quantity - g.used_count, 999999) as remaining_quantity
            FROM {$prefix}gifts g
            JOIN {$prefix}campaigns c ON g.campaign_id = c.id
            ORDER BY c.name, g.min_score
        ");
        
        foreach ($gifts as $gift):
        ?>
        <div class="vefify-gift-card" data-campaign="<?php echo $gift->campaign_id; ?>">
            <div class="gift-header">
                <h3><?php echo esc_html($gift->gift_name); ?></h3>
                <span class="gift-type"><?php echo esc_html($gift->gift_type); ?></span>
            </div>
            
            <div class="gift-details">
                <p><strong>Campaign:</strong> <?php echo esc_html($gift->campaign_name); ?></p>
                <p><strong>Value:</strong> <?php echo esc_html($gift->gift_value); ?></p>
                <p><strong>Score Range:</strong> <?php echo $gift->min_score; ?><?php echo $gift->max_score ? '-' . $gift->max_score : '+'; ?></p>
                <p><strong>Inventory:</strong> 
                    <?php if ($gift->max_quantity): ?>
                        <?php echo $gift->remaining_quantity; ?> / <?php echo $gift->max_quantity; ?>
                    <?php else: ?>
                        Unlimited
                    <?php endif; ?>
                </p>
                <p><strong>Used:</strong> <?php echo $gift->used_count; ?></p>
            </div>
            
            <div class="gift-actions">
                <button class="button button-small" onclick="editGift(<?php echo $gift->id; ?>)">Edit</button>
                <button class="button button-small" onclick="viewGiftUsers(<?php echo $gift->id; ?>)">View Users</button>
                <?php if ($gift->remaining_quantity <= 5 && $gift->max_quantity): ?>
                    <span class="low-inventory">Low Stock!</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.vefify-gift-filters {
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    align-items: center;
}

.vefify-gifts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.vefify-gift-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.gift-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.gift-header h3 {
    margin: 0;
    color: #333;
}

.gift-type {
    background: #0073aa;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    text-transform: uppercase;
}

.gift-details p {
    margin: 8px 0;
    font-size: 14px;
}

.gift-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
    align-items: center;
}

.low-inventory {
    background: #d63638;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}
</style>

<script>
function filterGifts() {
    const campaignId = document.getElementById('campaignFilter').value;
    const cards = document.querySelectorAll('.vefify-gift-card');
    
    cards.forEach(card => {
        if (!campaignId || card.dataset.campaign === campaignId) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function openGiftModal() {
    // Implementation for gift creation modal
    alert('Gift creation modal would open here');
}

function editGift(giftId) {
    // Implementation for gift editing
    window.location.href = `admin.php?page=vefify-gifts&action=edit&id=${giftId}`;
}

function viewGiftUsers(giftId) {
    // Implementation for viewing gift recipients
    window.location.href = `admin.php?page=vefify-analytics&gift_id=${giftId}`;
}
</script>