<?php
/**
 * Gift Manager Class
 * File: modules/gifts/class-gift-manager.php
 */
class Vefify_Gift_Manager {
    
    private $model;
    
    public function __construct() {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/gifts/class-gift-model.php';
        $this->model = new Vefify_Gift_Model();
    }
    
    public function display_gifts_list() {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">🎁 Gift Management</h1>
            <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=new'); ?>" class="page-title-action">Add New Gift</a>
            
            <div class="gift-management-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="<?php echo admin_url('admin.php?page=vefify-gifts'); ?>" class="nav-tab nav-tab-active">All Gifts</a>
                    <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=inventory'); ?>" class="nav-tab">Inventory</a>
                    <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=distribution'); ?>" class="nav-tab">Distribution Report</a>
                </nav>
            </div>
            
            <!-- Gift Statistics -->
            <?php $this->display_gift_statistics(); ?>
            
            <!-- Gifts Table -->
            <?php $this->display_gifts_table(); ?>
        </div>
        
        <style>
        .gift-statistics { display: flex; gap: 20px; margin: 20px 0; }
        .stat-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; flex: 1; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #0073aa; }
        .stat-label { color: #666; font-size: 14px; margin-top: 5px; }
        .inventory-status { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .inventory-status.high_stock { background: #46b450; }
        .inventory-status.medium_stock { background: #ff9800; }
        .inventory-status.low_stock { background: #dc3232; }
        .inventory-status.out_of_stock { background: #666; }
        .inventory-status.unlimited { background: #0073aa; }
        </style>
        <?php
    }
    
    public function display_gift_form() {
        $gift_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $gift = null;
        
        if ($gift_id) {
            $gift = $this->model->get_gift($gift_id);
        }
        
        $is_edit = !empty($gift);
        $title = $is_edit ? 'Edit Gift: ' . esc_html($gift['gift_name']) : 'New Gift';
        
        ?>
        <div class="wrap">
            <h1><?php echo $title; ?></h1>
            
            <form method="post" action="" id="gift-form">
                <?php wp_nonce_field('vefify_gift_save'); ?>
                <input type="hidden" name="action" value="save_gift">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="gift_id" value="<?php echo $gift['id']; ?>">
                <?php endif; ?>
                
                <div class="gift-form-container">
                    <!-- Basic Information -->
                    <div class="postbox">
                        <h2 class="hndle">🎁 Gift Information</h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="gift_name">Gift Name *</label></th>
                                    <td>
                                        <input type="text" id="gift_name" name="gift_name" 
                                               value="<?php echo $is_edit ? esc_attr($gift['gift_name']) : ''; ?>" 
                                               class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="gift_type">Gift Type</label></th>
                                    <td>
                                        <select id="gift_type" name="gift_type">
                                            <option value="voucher" <?php selected($is_edit ? $gift['gift_type'] : '', 'voucher'); ?>>Voucher</option>
                                            <option value="discount" <?php selected($is_edit ? $gift['gift_type'] : '', 'discount'); ?>>Discount</option>
                                            <option value="physical" <?php selected($is_edit ? $gift['gift_type'] : '', 'physical'); ?>>Physical Item</option>
                                            <option value="digital" <?php selected($is_edit ? $gift['gift_type'] : '', 'digital'); ?>>Digital Content</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="gift_value">Gift Value</label></th>
                                    <td>
                                        <input type="text" id="gift_value" name="gift_value" 
                                               value="<?php echo $is_edit ? esc_attr($gift['gift_value']) : ''; ?>" 
                                               class="regular-text" placeholder="50000 VND or 10%">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Scoring Requirements -->
                    <div class="postbox">
                        <h2 class="hndle">🎯 Scoring Requirements</h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="min_score">Minimum Score</label></th>
                                    <td>
                                        <input type="number" id="min_score" name="min_score" 
                                               value="<?php echo $is_edit ? $gift['min_score'] : 0; ?>" 
                                               min="0" class="small-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="max_score">Maximum Score</label></th>
                                    <td>
                                        <input type="number" id="max_score" name="max_score" 
                                               value="<?php echo $is_edit ? $gift['max_score'] : 10; ?>" 
                                               min="0" class="small-text">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Inventory Management -->
                    <div class="postbox">
                        <h2 class="hndle">📦 Inventory Management</h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="max_quantity">Maximum Quantity</label></th>
                                    <td>
                                        <input type="number" id="max_quantity" name="max_quantity" 
                                               value="<?php echo $is_edit ? $gift['max_quantity'] : ''; ?>" 
                                               min="0" class="small-text">
                                        <p class="description">Leave empty for unlimited quantity</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="gift_code_prefix">Gift Code Prefix</label></th>
                                    <td>
                                        <input type="text" id="gift_code_prefix" name="gift_code_prefix" 
                                               value="<?php echo $is_edit ? esc_attr($gift['gift_code_prefix']) : 'GIFT'; ?>" 
                                               class="regular-text" maxlength="20">
                                        <p class="description">Prefix for generated gift codes (e.g., SAVE10)</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- API Integration (Phase 2) -->
                    <div class="postbox">
                        <h2 class="hndle">🔗 API Integration (Phase 2)</h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="api_endpoint">API Endpoint</label></th>
                                    <td>
                                        <input type="url" id="api_endpoint" name="api_endpoint" 
                                               value="<?php echo $is_edit ? esc_attr($gift['api_endpoint']) : ''; ?>" 
                                               class="regular-text" placeholder="https://api.example.com/gifts">
                                        <p class="description">External API for gift fulfillment</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="api_params">API Parameters</label></th>
                                    <td>
                                        <textarea id="api_params" name="api_params" rows="3" class="large-text"><?php echo $is_edit ? esc_textarea($gift['api_params']) : ''; ?></textarea>
                                        <p class="description">JSON parameters for API calls</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" 
                           value="<?php echo $is_edit ? 'Update Gift' : 'Create Gift'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=vefify-gifts'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        
        <style>
        .gift-form-container { max-width: 800px; }
        .postbox { margin-bottom: 20px; }
        .postbox h2.hndle { padding: 12px 20px; margin: 0; background: #f1f1f1; border-bottom: 1px solid #ddd; }
        .postbox .inside { padding: 20px; }
        </style>
        <?php
    }
    
    public function display_inventory_management() {
        $gifts = $this->model->get_gifts();
        ?>
        <div class="wrap">
            <h1>📦 Gift Inventory Management</h1>
            
            <div class="inventory-overview">
                <h2>Inventory Status Overview</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Gift Name</th>
                            <th>Type</th>
                            <th>Max Quantity</th>
                            <th>Distributed</th>
                            <th>Remaining</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gifts as $gift): ?>
                            <?php $inventory = $this->model->get_gift_inventory($gift['id']); ?>
                            <tr>
                                <td><strong><?php echo esc_html($gift['gift_name']); ?></strong></td>
                                <td><?php echo ucfirst($gift['gift_type']); ?></td>
                                <td><?php echo $gift['max_quantity'] ?: 'Unlimited'; ?></td>
                                <td><?php echo number_format($inventory['distributed']); ?></td>
                                <td><?php echo is_numeric($inventory['remaining']) ? number_format($inventory['remaining']) : $inventory['remaining']; ?></td>
                                <td><span class="inventory-status <?php echo $inventory['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $inventory['status'])); ?></span></td>
                                <td>
                                    <button type="button" class="button generate-codes" data-gift-id="<?php echo $gift['id']; ?>">Generate Codes</button>
                                    <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=edit&id=' . $gift['id']); ?>" class="button">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Bulk Code Generation -->
            <div class="bulk-code-generation">
                <h2>🔢 Bulk Code Generation</h2>
                <div class="code-generation-form">
                    <select id="bulk-gift-select">
                        <option value="">Select Gift...</option>
                        <?php foreach ($gifts as $gift): ?>
                            <option value="<?php echo $gift['id']; ?>"><?php echo esc_html($gift['gift_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" id="bulk-quantity" placeholder="Quantity" min="1" max="100" value="10">
                    <button type="button" id="generate-bulk-codes" class="button button-primary">Generate Codes</button>
                </div>
                
                <div id="generated-codes-display" style="display: none;">
                    <h3>Generated Codes:</h3>
                    <textarea id="codes-output" rows="10" cols="50" readonly></textarea>
                    <button type="button" id="copy-codes" class="button">Copy All Codes</button>
                    <button type="button" id="download-codes" class="button">Download CSV</button>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#generate-bulk-codes').on('click', function() {
                var giftId = $('#bulk-gift-select').val();
                var quantity = $('#bulk-quantity').val();
                
                if (!giftId || !quantity) {
                    alert('Please select a gift and enter quantity');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vefify_generate_gift_codes',
                        gift_id: giftId,
                        quantity: quantity,
                        nonce: '<?php echo wp_create_nonce('vefify_gift_ajax'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var codes = response.data.codes.join('\n');
                            $('#codes-output').val(codes);
                            $('#generated-codes-display').show();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            });
            
            $('#copy-codes').on('click', function() {
                $('#codes-output').select();
                document.execCommand('copy');
                alert('Codes copied to clipboard!');
            });
        });
        </script>
        <?php
    }
    
    public function display_distribution_report() {
        ?>
        <div class="wrap">
            <h1>📊 Gift Distribution Report</h1>
            
            <div class="distribution-charts">
                <div class="chart-container">
                    <canvas id="gift-distribution-chart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <div class="distribution-details">
                <h2>Distribution Details</h2>
                <?php $this->display_distribution_table(); ?>
            </div>
        </div>
        <?php
    }
    
    private function display_gift_statistics() {
        $stats = $this->model->get_gift_statistics();
        ?>
        <div class="gift-statistics">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_gifts']); ?></div>
                <div class="stat-label">Total Gift Types</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['distributed_count']); ?></div>
                <div class="stat-label">Gifts Distributed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['claim_rate']; ?>%</div>
                <div class="stat-label">Claim Rate</div>
            </div>
        </div>
        <?php
    }
    
    private function display_gifts_table() {
        $gifts = $this->model->get_gifts();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Gift Name</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Score Range</th>
                    <th>Inventory</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gifts as $gift): ?>
                    <?php $inventory = $this->model->get_gift_inventory($gift['id']); ?>
                    <tr>
                        <td><strong><?php echo esc_html($gift['gift_name']); ?></strong></td>
                        <td><?php echo ucfirst($gift['gift_type']); ?></td>
                        <td><?php echo esc_html($gift['gift_value']); ?></td>
                        <td><?php echo $gift['min_score'] . ' - ' . $gift['max_score']; ?></td>
                        <td>
                            <?php echo number_format($inventory['distributed']); ?>
                            <?php if ($gift['max_quantity']): ?>
                                / <?php echo number_format($gift['max_quantity']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="inventory-status <?php echo $inventory['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $inventory['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=edit&id=' . $gift['id']); ?>" class="button button-small">Edit</a>
                            <button type="button" class="button button-small check-inventory" data-gift-id="<?php echo $gift['id']; ?>">Check Inventory</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    private function display_distribution_table() {
        global $wpdb;
        
        $distribution_data = $wpdb->get_results(
            "SELECT g.gift_name, g.gift_type, COUNT(p.gift_code) as distributed_count,
                    AVG(p.final_score) as avg_score
             FROM {$wpdb->prefix}vefify_gifts g
             LEFT JOIN {$wpdb->prefix}vefify_participants p ON p.gift_code LIKE CONCAT(g.gift_code_prefix, '%')
             GROUP BY g.id
             ORDER BY distributed_count DESC",
            ARRAY_A
        );
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Gift Name</th>
                    <th>Type</th>
                    <th>Distributed</th>
                    <th>Avg Recipient Score</th>
                    <th>Performance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($distribution_data as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['gift_name']); ?></td>
                        <td><?php echo ucfirst($row['gift_type']); ?></td>
                        <td><?php echo number_format($row['distributed_count']); ?></td>
                        <td><?php echo $row['avg_score'] ? round($row['avg_score'], 1) : '-'; ?></td>
                        <td>
                            <?php
                            $performance = $row['distributed_count'] > 10 ? 'High' : ($row['distributed_count'] > 5 ? 'Medium' : 'Low');
                            echo $performance;
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}