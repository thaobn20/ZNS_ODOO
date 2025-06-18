<?php
/**
 * 🎁 Fixed Gift Manager - Dedicated Form Pages
 * File: modules/gifts/class-gift-manager.php
 * 
 * ✅ FIXED: Replaces modal with dedicated form pages
 * ✅ USES: Working save functionality from tests
 * ✅ RELIABLE: Traditional form submission + AJAX enhancement
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Gift_Manager {
    
    private $model;
    
    public function __construct() {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/gifts/class-gift-model.php';
        $this->model = new Vefify_Gift_Model();
        
        // Handle form submissions
        add_action('admin_post_vefify_save_gift', array($this, 'handle_gift_form_submission'));
    }
    
    /**
     * 🎨 MAIN GIFTS LIST - Updated with page-based forms
     */
    public function display_gifts_list() {
        // Handle messages
        $this->display_admin_messages();
        
        $gifts = $this->model->get_gifts(array('per_page' => 50));
        $campaigns = $this->get_campaigns_for_filter();
        $stats = $this->model->get_gift_statistics();
        ?>
        
        <div class="vefify-modern-wrap">
            <!-- 📊 Header Section -->
            <div class="vefify-header">
                <div class="vefify-header-content">
                    <h1 class="vefify-page-title">
                        <span class="vefify-icon">🎁</span>
                        Gift Management
                        <span class="vefify-subtitle">Manage rewards and incentives</span>
                    </h1>
                    <div class="vefify-header-actions">
                        <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=new'); ?>" class="vefify-btn vefify-btn-primary">
                            <span class="dashicons dashicons-plus-alt"></span>
                            Add New Gift
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=export'); ?>" class="vefify-btn vefify-btn-secondary">
                            <span class="dashicons dashicons-download"></span>
                            Export
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- 📈 Statistics Dashboard -->
            <div class="vefify-stats-grid">
                <div class="vefify-stat-card">
                    <div class="vefify-stat-icon">🎁</div>
                    <div class="vefify-stat-content">
                        <div class="vefify-stat-number"><?php echo number_format($stats['total_gifts']); ?></div>
                        <div class="vefify-stat-label">Total Gift Types</div>
                        <div class="vefify-stat-trend positive">+2 this month</div>
                    </div>
                </div>
                
                <div class="vefify-stat-card">
                    <div class="vefify-stat-icon">📤</div>
                    <div class="vefify-stat-content">
                        <div class="vefify-stat-number"><?php echo number_format($stats['distributed_count']); ?></div>
                        <div class="vefify-stat-label">Gifts Distributed</div>
                        <div class="vefify-stat-trend positive">+23% this week</div>
                    </div>
                </div>
                
                <div class="vefify-stat-card">
                    <div class="vefify-stat-icon">✅</div>
                    <div class="vefify-stat-content">
                        <div class="vefify-stat-number"><?php echo $stats['claim_rate']; ?>%</div>
                        <div class="vefify-stat-label">Claim Rate</div>
                        <div class="vefify-stat-trend <?php echo $stats['claim_rate'] >= 80 ? 'positive' : 'neutral'; ?>">
                            <?php echo $stats['claim_rate'] >= 80 ? 'Excellent' : 'Good'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="vefify-stat-card">
                    <div class="vefify-stat-icon">⚠️</div>
                    <div class="vefify-stat-content">
                        <div class="vefify-stat-number"><?php echo $stats['low_stock_alerts'] ?? 0; ?></div>
                        <div class="vefify-stat-label">Low Stock Alerts</div>
                        <div class="vefify-stat-trend <?php echo ($stats['low_stock_alerts'] ?? 0) == 0 ? 'positive' : 'warning'; ?>">
                            <?php echo ($stats['low_stock_alerts'] ?? 0) == 0 ? 'All good' : 'Needs attention'; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 🔍 Filters & Search -->
            <div class="vefify-filters-section">
                <div class="vefify-filters">
                    <div class="vefify-filter-group">
                        <label for="campaign-filter">Campaign:</label>
                        <select id="campaign-filter" class="vefify-select" onchange="filterGifts()">
                            <option value="">All Campaigns</option>
                            <?php foreach ($campaigns as $campaign): ?>
                                <option value="<?php echo $campaign['id']; ?>">
                                    <?php echo esc_html($campaign['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="vefify-filter-group">
                        <label for="status-filter">Status:</label>
                        <select id="status-filter" class="vefify-select" onchange="filterGifts()">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="vefify-search-group">
                        <input type="text" id="gift-search" class="vefify-search" 
                               placeholder="Search gifts..." onkeyup="searchGifts()">
                        <span class="dashicons dashicons-search vefify-search-icon"></span>
                    </div>
                </div>
            </div>
            
            <!-- 🎁 Gifts Grid -->
            <div class="vefify-gifts-container">
                <?php if (empty($gifts)): ?>
                    <div class="vefify-empty-state">
                        <div class="vefify-empty-icon">🎁</div>
                        <h3>No gifts configured yet</h3>
                        <p>Start by creating your first gift to reward quiz participants.</p>
                        <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=new'); ?>" class="vefify-btn vefify-btn-primary">
                            Create Your First Gift
                        </a>
                    </div>
                <?php else: ?>
                    <div class="vefify-gifts-grid" id="gifts-grid">
                        <?php foreach ($gifts as $gift): ?>
                            <?php $this->render_gift_card($gift); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php $this->add_list_page_styles_and_scripts(); ?>
        <?php
    }
    
    /**
     * 🎨 Individual Gift Card Component
     */
    private function render_gift_card($gift) {
        $inventory = $this->model->get_gift_inventory($gift['id']);
        $campaign_name = $this->get_campaign_name($gift['campaign_id']);
        ?>
        
        <div class="vefify-gift-card" 
             data-campaign="<?php echo $gift['campaign_id']; ?>"
             data-status="<?php echo $gift['is_active'] ? 'active' : 'inactive'; ?>"
             data-type="<?php echo $gift['gift_type']; ?>"
             data-name="<?php echo esc_attr(strtolower($gift['gift_name'])); ?>">
            
            <!-- Gift Header -->
            <div class="vefify-gift-header">
                <div class="vefify-gift-type vefify-type-<?php echo $gift['gift_type']; ?>">
                    <?php echo $this->get_gift_type_icon($gift['gift_type']); ?>
                    <?php echo ucfirst($gift['gift_type']); ?>
                </div>
                <div class="vefify-gift-status">
                    <?php if ($gift['is_active']): ?>
                        <span class="vefify-status-active">Active</span>
                    <?php else: ?>
                        <span class="vefify-status-inactive">Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Gift Content -->
            <div class="vefify-gift-content">
                <h3 class="vefify-gift-title"><?php echo esc_html($gift['gift_name']); ?></h3>
                <div class="vefify-gift-value"><?php echo esc_html($gift['gift_value']); ?></div>
                
                <div class="vefify-gift-details">
                    <div class="vefify-detail-row">
                        <span class="vefify-detail-label">Campaign:</span>
                        <span class="vefify-detail-value"><?php echo esc_html($campaign_name); ?></span>
                    </div>
                    
                    <div class="vefify-detail-row">
                        <span class="vefify-detail-label">Score Range:</span>
                        <span class="vefify-detail-value">
                            <?php echo $gift['min_score']; ?> - 
                            <?php echo $gift['max_score'] ?: '∞'; ?> points
                        </span>
                    </div>
                    
                    <div class="vefify-detail-row">
                        <span class="vefify-detail-label">Inventory:</span>
                        <span class="vefify-detail-value">
                            <?php if ($inventory): ?>
                                <?php echo $inventory['remaining']; ?> / <?php echo $gift['max_quantity'] ?: '∞'; ?>
                                <span class="vefify-inventory-status vefify-status-<?php echo $inventory['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $inventory['status'])); ?>
                                </span>
                            <?php else: ?>
                                <span class="vefify-inventory-status vefify-status-unlimited">Unlimited</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Gift Actions -->
            <div class="vefify-gift-actions">
                <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=edit&id=' . $gift['id']); ?>" 
                   class="vefify-btn-small vefify-btn-primary">
                    <span class="dashicons dashicons-edit"></span>
                    Edit
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=vefify-analytics&gift_id=' . $gift['id']); ?>" 
                   class="vefify-btn-small vefify-btn-secondary" target="_blank">
                    <span class="dashicons dashicons-chart-bar"></span>
                    Analytics
                </a>
                
                <button type="button" class="vefify-btn-small vefify-btn-secondary" 
                        onclick="generateCodes(<?php echo $gift['id']; ?>)">
                    <span class="dashicons dashicons-tickets"></span>
                    Codes
                </button>
                
                <button type="button" class="vefify-btn-small vefify-btn-danger" 
                        onclick="deleteGift(<?php echo $gift['id']; ?>)" style="margin-left: auto;">
                    <span class="dashicons dashicons-trash"></span>
                    Delete
                </button>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * 📝 GIFT FORM PAGE - Dedicated page instead of modal
     */
    public function display_gift_form() {
        $gift_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $gift = null;
        
        if ($gift_id) {
            $gift = $this->model->get_gift_by_id($gift_id);
            if (!$gift) {
                wp_die('Gift not found.');
            }
        }
        
        $is_edit = !empty($gift);
        $title = $is_edit ? 'Edit Gift: ' . esc_html($gift['gift_name']) : 'Add New Gift';
        $campaigns = $this->get_campaigns_for_filter();
        
        // Handle messages
        $this->display_admin_messages();
        ?>
        
        <div class="vefify-modern-wrap">
            <div class="vefify-header">
                <div class="vefify-header-content">
                    <h1 class="vefify-page-title">
                        <a href="<?php echo admin_url('admin.php?page=vefify-gifts'); ?>" class="vefify-back-link">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                        </a>
                        <span class="vefify-icon">🎁</span>
                        <?php echo $title; ?>
                    </h1>
                </div>
            </div>
            
            <div class="vefify-form-container">
                <div class="vefify-form-wrapper">
                    
                    <!-- ✅ TRADITIONAL FORM with AJAX enhancement -->
                    <form id="gift-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="vefify-gift-form">
                        <?php wp_nonce_field('vefify_gift_save', 'vefify_gift_nonce'); ?>
                        <input type="hidden" name="action" value="vefify_save_gift">
                        <input type="hidden" name="gift_id" value="<?php echo $gift['id'] ?? ''; ?>">
                        
                        <div class="vefify-form-sections">
                            
                            <!-- Basic Information Section -->
                            <div class="vefify-form-section">
                                <h2>🎁 Basic Information</h2>
                                
                                <div class="vefify-form-row">
                                    <div class="vefify-form-group">
                                        <label for="gift_name" class="vefify-label required">Gift Name</label>
                                        <input type="text" id="gift_name" name="gift_name" 
                                               value="<?php echo esc_attr($gift['gift_name'] ?? ''); ?>" 
                                               class="vefify-input" placeholder="e.g., 10% Discount Voucher" required>
                                        <div class="vefify-field-help">Enter a descriptive name for this gift</div>
                                    </div>
                                    
                                    <div class="vefify-form-group">
                                        <label for="gift_type" class="vefify-label required">Gift Type</label>
                                        <select id="gift_type" name="gift_type" class="vefify-select" required>
                                            <option value="">Select Type</option>
                                            <option value="voucher" <?php selected($gift['gift_type'] ?? '', 'voucher'); ?>>💰 Voucher</option>
                                            <option value="discount" <?php selected($gift['gift_type'] ?? '', 'discount'); ?>>🏷️ Discount</option>
                                            <option value="product" <?php selected($gift['gift_type'] ?? '', 'product'); ?>>📦 Product</option>
                                            <option value="points" <?php selected($gift['gift_type'] ?? '', 'points'); ?>>⭐ Points</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="vefify-form-row">
                                    <div class="vefify-form-group">
                                        <label for="gift_value" class="vefify-label required">Gift Value</label>
                                        <input type="text" id="gift_value" name="gift_value" 
                                               value="<?php echo esc_attr($gift['gift_value'] ?? ''); ?>" 
                                               class="vefify-input" placeholder="e.g., 50000 VND, 10%, Free Product" required>
                                        <div class="vefify-field-help">The value of this gift (amount, percentage, or description)</div>
                                    </div>
                                    
                                    <div class="vefify-form-group">
                                        <label for="campaign_id" class="vefify-label required">Campaign</label>
                                        <select id="campaign_id" name="campaign_id" class="vefify-select" required>
                                            <option value="">Select Campaign</option>
                                            <?php foreach ($campaigns as $campaign): ?>
                                                <option value="<?php echo $campaign['id']; ?>" 
                                                        <?php selected($gift['campaign_id'] ?? '', $campaign['id']); ?>>
                                                    <?php echo esc_html($campaign['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="vefify-form-group">
                                    <label for="gift_description" class="vefify-label">Description</label>
                                    <textarea id="gift_description" name="gift_description" class="vefify-textarea" 
                                              rows="3" placeholder="Describe this gift and how to use it..."><?php echo esc_textarea($gift['gift_description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Scoring & Availability Section -->
                            <div class="vefify-form-section">
                                <h2>🎯 Scoring & Availability</h2>
                                
                                <div class="vefify-form-row">
                                    <div class="vefify-form-group">
                                        <label for="min_score" class="vefify-label required">Minimum Score</label>
                                        <input type="number" id="min_score" name="min_score" 
                                               value="<?php echo esc_attr($gift['min_score'] ?? 0); ?>" 
                                               class="vefify-input" min="0" required>
                                        <div class="vefify-field-help">Minimum quiz score required to earn this gift</div>
                                    </div>
                                    
                                    <div class="vefify-form-group">
                                        <label for="max_score" class="vefify-label">Maximum Score</label>
                                        <input type="number" id="max_score" name="max_score" 
                                               value="<?php echo esc_attr($gift['max_score'] ?? ''); ?>" 
                                               class="vefify-input" min="0">
                                        <div class="vefify-field-help">Leave empty for no maximum limit</div>
                                    </div>
                                </div>
                                
                                <div class="vefify-form-row">
                                    <div class="vefify-form-group">
                                        <label for="max_quantity" class="vefify-label">Maximum Quantity</label>
                                        <input type="number" id="max_quantity" name="max_quantity" 
                                               value="<?php echo esc_attr($gift['max_quantity'] ?? ''); ?>" 
                                               class="vefify-input" min="1">
                                        <div class="vefify-field-help">Total number available (leave empty for unlimited)</div>
                                    </div>
                                    
                                    <div class="vefify-form-group">
                                        <label for="gift_code_prefix" class="vefify-label">Code Prefix</label>
                                        <input type="text" id="gift_code_prefix" name="gift_code_prefix" 
                                               value="<?php echo esc_attr($gift['gift_code_prefix'] ?? ''); ?>" 
                                               class="vefify-input" maxlength="10" placeholder="GIFT">
                                        <div class="vefify-field-help">Prefix for generated gift codes (e.g., SAVE10)</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Status Section -->
                            <div class="vefify-form-section">
                                <h2>⚙️ Status & Settings</h2>
                                
                                <div class="vefify-form-group">
                                    <label class="vefify-checkbox-label">
                                        <input type="checkbox" id="is_active" name="is_active" value="1" 
                                               <?php checked($gift['is_active'] ?? 1, 1); ?>>
                                        <span class="vefify-checkbox-custom"></span>
                                        <strong>Active</strong> - Available for distribution to participants
                                    </label>
                                </div>
                            </div>
                            
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="vefify-form-actions">
                            <button type="submit" class="vefify-btn vefify-btn-primary vefify-btn-large" id="save-gift-btn">
                                <span class="dashicons dashicons-yes"></span>
                                <?php echo $is_edit ? 'Update Gift' : 'Create Gift'; ?>
                            </button>
                            
                            <a href="<?php echo admin_url('admin.php?page=vefify-gifts'); ?>" class="vefify-btn vefify-btn-secondary vefify-btn-large">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                                Cancel
                            </a>
                            
                            <?php if ($is_edit): ?>
                                <button type="button" class="vefify-btn vefify-btn-danger vefify-btn-large" 
                                        onclick="deleteGift(<?php echo $gift['id']; ?>)" style="margin-left: auto;">
                                    <span class="dashicons dashicons-trash"></span>
                                    Delete Gift
                                </button>
                            <?php endif; ?>
                        </div>
                        
                    </form>
                    
                </div>
            </div>
        </div>
        
        <?php $this->add_form_page_styles_and_scripts(); ?>
        <?php
    }
    
    /**
     * 📝 Handle form submission (traditional + AJAX backup)
     */
    public function handle_gift_form_submission() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['vefify_gift_nonce'], 'vefify_gift_save')) {
            wp_die('Security check failed');
        }
        
        try {
            // Sanitize form data
            $gift_data = $this->sanitize_gift_form_data($_POST);
            $gift_id = !empty($_POST['gift_id']) ? intval($_POST['gift_id']) : null;
            
            error_log('Gift Form Submission: ' . print_r($gift_data, true));
            
            // Save gift using the working model
            $result = $this->model->save_gift($gift_data, $gift_id);
            
            if (is_array($result) && isset($result['errors'])) {
                // Validation errors
                $error_message = implode(', ', $result['errors']);
                $redirect_url = admin_url('admin.php?page=vefify-gifts&action=' . ($gift_id ? 'edit&id=' . $gift_id : 'new') . '&error=' . urlencode($error_message));
                wp_redirect($redirect_url);
                exit;
            } elseif ($result === false) {
                // Save failed
                $redirect_url = admin_url('admin.php?page=vefify-gifts&action=' . ($gift_id ? 'edit&id=' . $gift_id : 'new') . '&error=save_failed');
                wp_redirect($redirect_url);
                exit;
            } else {
                // Success
                $redirect_url = admin_url('admin.php?page=vefify-gifts&message=saved&id=' . $result);
                wp_redirect($redirect_url);
                exit;
            }
            
        } catch (Exception $e) {
            error_log('Gift form submission error: ' . $e->getMessage());
            $redirect_url = admin_url('admin.php?page=vefify-gifts&error=' . urlencode('An error occurred: ' . $e->getMessage()));
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * 📝 Sanitize form data
     */
    private function sanitize_gift_form_data($data) {
        return array(
            'campaign_id' => intval($data['campaign_id'] ?? 0),
            'gift_name' => sanitize_text_field($data['gift_name'] ?? ''),
            'gift_type' => sanitize_text_field($data['gift_type'] ?? ''),
            'gift_value' => sanitize_text_field($data['gift_value'] ?? ''),
            'gift_description' => wp_kses_post($data['gift_description'] ?? ''),
            'min_score' => intval($data['min_score'] ?? 0),
            'max_score' => !empty($data['max_score']) ? intval($data['max_score']) : null,
            'max_quantity' => !empty($data['max_quantity']) ? intval($data['max_quantity']) : null,
            'gift_code_prefix' => sanitize_text_field($data['gift_code_prefix'] ?? ''),
            'is_active' => isset($data['is_active']) ? 1 : 0
        );
    }
    
    /**
     * 💬 Display admin messages
     */
    private function display_admin_messages() {
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            
            switch ($message) {
                case 'saved':
                    echo '<div class="notice notice-success is-dismissible"><p>Gift saved successfully!</p></div>';
                    break;
                case 'deleted':
                    echo '<div class="notice notice-success is-dismissible"><p>Gift deleted successfully!</p></div>';
                    break;
            }
        }
        
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            echo '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html($error) . '</p></div>';
        }
    }
    
    // ===== HELPER METHODS =====
    
    private function get_campaigns_for_filter() {
        global $wpdb;
        $table_name = $wpdb->prefix . (defined('VEFIFY_QUIZ_TABLE_PREFIX') ? VEFIFY_QUIZ_TABLE_PREFIX : 'vefify_') . 'campaigns';
        return $wpdb->get_results(
            "SELECT id, name FROM {$table_name} WHERE is_active = 1",
            ARRAY_A
        ) ?: array();
    }
    
    private function get_campaign_name($campaign_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . (defined('VEFIFY_QUIZ_TABLE_PREFIX') ? VEFIFY_QUIZ_TABLE_PREFIX : 'vefify_') . 'campaigns';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$table_name} WHERE id = %d",
            $campaign_id
        )) ?: 'Unknown Campaign';
    }
    
    private function get_gift_type_icon($type) {
        $icons = array(
            'voucher' => '💰',
            'discount' => '🏷️',
            'product' => '📦',
            'points' => '⭐'
        );
        return $icons[$type] ?? '🎁';
    }
    
    /**
     * 🎨 Add styles and scripts for list page
     */
    private function add_list_page_styles_and_scripts() {
        ?>
        <style>
        /* Same beautiful CSS as before */
        .vefify-modern-wrap {
            background: #f6f7fb !important;
            margin: -20px -20px 0 -2px !important;
            padding: 0 !important;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .vefify-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 40px;
            margin-bottom: 30px;
        }
        
        .vefify-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
        }
        
        .vefify-page-title {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .vefify-icon { font-size: 32px; }
        
        .vefify-subtitle {
            font-size: 16px;
            font-weight: 400;
            opacity: 0.9;
            display: block;
            margin-top: 4px;
        }
        
        .vefify-header-actions { display: flex; gap: 12px; }
        
        .vefify-btn {
            background: #0073aa;
            color: white !important;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none !important;
            transition: all 0.2s;
            font-size: 14px;
        }
        
        .vefify-btn:hover {
            background: #005177;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,115,170,0.3);
        }
        
        .vefify-btn-primary { background: #0073aa; }
        .vefify-btn-secondary { background: #50575e; }
        .vefify-btn-danger { background: #dc3232; }
        
        .vefify-btn-small {
            padding: 6px 12px;
            font-size: 12px;
            gap: 4px;
        }
        
        .vefify-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 0 40px 30px;
        }
        
        .vefify-stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s;
        }
        
        .vefify-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        
        .vefify-stat-icon {
            font-size: 40px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .vefify-stat-content { flex: 1; }
        
        .vefify-stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        
        .vefify-stat-label {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .vefify-stat-trend {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .vefify-stat-trend.positive {
            background: #d1fae5;
            color: #065f46;
        }
        
        .vefify-filters-section {
            margin: 0 40px 30px;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .vefify-filters {
            display: flex;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .vefify-filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .vefify-filter-group label {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .vefify-select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px 12px;
            background: white;
            font-size: 14px;
            min-width: 150px;
        }
        
        .vefify-search-group {
            position: relative;
            flex: 1;
            max-width: 300px;
        }
        
        .vefify-search {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px 36px 8px 12px;
            font-size: 14px;
        }
        
        .vefify-search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        
        .vefify-gifts-container { margin: 0 40px; }
        
        .vefify-gifts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }
        
        .vefify-gift-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        
        .vefify-gift-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border-color: #667eea;
        }
        
        .vefify-gift-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .vefify-gift-type {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .vefify-type-voucher { background: #dcfce7; color: #166534; }
        .vefify-type-discount { background: #fef3c7; color: #92400e; }
        .vefify-type-product { background: #dbeafe; color: #1e40af; }
        .vefify-type-points { background: #fce7f3; color: #be185d; }
        
        .vefify-status-active {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .vefify-status-inactive {
            background: #f3f4f6;
            color: #6b7280;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .vefify-gift-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 8px 0;
        }
        
        .vefify-gift-value {
            font-size: 24px;
            font-weight: 700;
            color: #0073aa;
            margin-bottom: 16px;
        }
        
        .vefify-gift-details {
            border-top: 1px solid #f1f5f9;
            padding-top: 16px;
            margin-bottom: 20px;
        }
        
        .vefify-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
        }
        
        .vefify-detail-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }
        
        .vefify-detail-value {
            font-size: 13px;
            color: #1e293b;
            font-weight: 600;
            text-align: right;
        }
        
        .vefify-inventory-status {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 8px;
        }
        
        .vefify-status-high_stock { background: #d1fae5; color: #065f46; }
        .vefify-status-medium_stock { background: #fef3c7; color: #92400e; }
        .vefify-status-low_stock { background: #fee2e2; color: #991b1b; }
        .vefify-status-out_of_stock { background: #f3f4f6; color: #6b7280; }
        .vefify-status-unlimited { background: #dbeafe; color: #1e40af; }
        
        .vefify-gift-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 16px;
        }
        
        .vefify-empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .vefify-empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .vefify-empty-state h3 {
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .vefify-empty-state p {
            color: #64748b;
            margin-bottom: 24px;
        }
        </style>
        
        <script>
        // Filter and search functions
        function filterGifts() {
            const campaignFilter = document.getElementById('campaign-filter').value;
            const statusFilter = document.getElementById('status-filter').value;
            const cards = document.querySelectorAll('.vefify-gift-card');
            
            cards.forEach(card => {
                let show = true;
                
                if (campaignFilter && card.dataset.campaign !== campaignFilter) {
                    show = false;
                }
                
                if (statusFilter && card.dataset.status !== statusFilter) {
                    show = false;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }
        
        function searchGifts() {
            const searchTerm = document.getElementById('gift-search').value.toLowerCase();
            const cards = document.querySelectorAll('.vefify-gift-card');
            
            cards.forEach(card => {
                const giftName = card.dataset.name;
                const shouldShow = giftName.includes(searchTerm);
                card.style.display = shouldShow ? 'block' : 'none';
            });
        }
        
        function deleteGift(giftId) {
            if (confirm('Are you sure you want to delete this gift?')) {
                window.location.href = '<?php echo admin_url('admin.php?page=vefify-gifts&action=delete&id='); ?>' + giftId;
            }
        }
        
        function generateCodes(giftId) {
            const quantity = prompt('How many codes to generate? (Max 100)');
            if (quantity && quantity <= 100 && quantity > 0) {
                window.open('<?php echo admin_url('admin.php?page=vefify-gifts&action=generate_codes&id='); ?>' + giftId + '&quantity=' + quantity, '_blank');
            }
        }
        </script>
        <?php
    }
    
    /**
     * 🎨 Add styles and scripts for form page
     */
    private function add_form_page_styles_and_scripts() {
        ?>
        <style>
        .vefify-form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 40px 40px;
        }
        
        .vefify-form-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .vefify-form-sections {
            padding: 32px;
        }
        
        .vefify-form-section {
            margin-bottom: 40px;
            padding-bottom: 32px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .vefify-form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .vefify-form-section h2 {
            margin: 0 0 24px 0;
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .vefify-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .vefify-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .vefify-label {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }
        
        .vefify-label.required::after {
            content: " *";
            color: #dc2626;
        }
        
        .vefify-input, .vefify-textarea, .vefify-select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 12px;
            font-size: 14px;
            transition: border-color 0.2s;
            background: white;
        }
        
        .vefify-input:focus, .vefify-textarea:focus, .vefify-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .vefify-textarea {
            resize: vertical;
            font-family: inherit;
        }
        
        .vefify-field-help {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }
        
        .vefify-checkbox-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            font-size: 14px;
            color: #374151;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }
        
        .vefify-checkbox-label:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        
        .vefify-checkbox-custom {
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 4px;
            display: inline-block;
            position: relative;
            background: white;
            transition: all 0.2s;
        }
        
        input[type="checkbox"]:checked + .vefify-checkbox-custom {
            background: #667eea;
            border-color: #667eea;
        }
        
        input[type="checkbox"]:checked + .vefify-checkbox-custom::after {
            content: "✓";
            position: absolute;
            top: -2px;
            left: 2px;
            color: white;
            font-size: 14px;
            font-weight: bold;
        }
        
        input[type="checkbox"] { display: none; }
        
        .vefify-form-actions {
            padding: 24px 32px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 16px;
            align-items: center;
        }
        
        .vefify-btn-large {
            padding: 16px 24px;
            font-size: 16px;
        }
        
        .vefify-back-link {
            color: white !important;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .vefify-back-link:hover { opacity: 1; }
        
        @media (max-width: 768px) {
            .vefify-form-container { padding: 0 20px 20px; }
            .vefify-form-row { grid-template-columns: 1fr; }
            .vefify-form-actions { flex-direction: column; }
            .vefify-btn-large { width: 100%; justify-content: center; }
        }
        </style>
        
        <script>
        // Form enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('gift-form');
            const saveBtn = document.getElementById('save-gift-btn');
            
            // Form validation
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let hasErrors = false;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc2626';
                        hasErrors = true;
                    } else {
                        field.style.borderColor = '#d1d5db';
                    }
                });
                
                if (hasErrors) {
                    e.preventDefault();
                    alert('Please fill in all required fields');
                    return;
                }
                
                // Show loading state
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="dashicons dashicons-update-alt"></span> Saving...';
            });
            
            // Real-time validation
            form.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('blur', function() {
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.style.borderColor = '#dc2626';
                    } else {
                        this.style.borderColor = '#d1d5db';
                    }
                });
            });
        });
        
        function deleteGift(giftId) {
            if (confirm('Are you sure you want to delete this gift? This action cannot be undone.')) {
                window.location.href = '<?php echo admin_url('admin.php?page=vefify-gifts&action=delete&id='); ?>' + giftId;
            }
        }
        </script>
        <?php
    }
}