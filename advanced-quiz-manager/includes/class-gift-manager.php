<?php
/**
 * Gift Management Class for Advanced Quiz Manager
 * File: includes/class-gift-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQM_Gift_Manager {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_gift_menu'), 20);
        add_action('wp_ajax_aqm_save_gift', array($this, 'save_gift'));
        add_action('wp_ajax_aqm_delete_gift', array($this, 'delete_gift'));
        add_action('wp_ajax_aqm_get_gift', array($this, 'get_gift'));
        add_action('wp_ajax_aqm_generate_gift_codes', array($this, 'generate_gift_codes'));
        add_action('wp_ajax_aqm_export_gift_awards', array($this, 'export_gift_awards'));
        add_action('wp_ajax_aqm_revoke_gift_award', array($this, 'revoke_gift_award'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_gift_scripts'));
    }
    
    public function add_gift_menu() {
        add_submenu_page(
            'quiz-manager',
            'Gift Management',
            'Gift Management',
            'manage_options',
            'quiz-manager-gifts',
            array($this, 'gifts_management_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Gift Awards',
            'Gift Awards',
            'manage_options',
            'quiz-manager-gift-awards',
            array($this, 'gift_awards_page')
        );
    }
    
    public function enqueue_gift_scripts($hook) {
        if (strpos($hook, 'quiz-manager-gifts') !== false || strpos($hook, 'quiz-manager-gift-awards') !== false) {
            wp_enqueue_script('aqm-gift-management', AQM_PLUGIN_URL . 'assets/js/gift-management.js', array('jquery'), AQM_VERSION, true);
            wp_enqueue_style('aqm-gift-management', AQM_PLUGIN_URL . 'assets/css/gift-management.css', array(), AQM_VERSION);
            
            wp_localize_script('aqm-gift-management', 'aqm_gifts', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqm_gift_nonce'),
                'confirm_delete' => __('Are you sure you want to delete this gift?', 'advanced-quiz'),
                'confirm_revoke' => __('Are you sure you want to revoke this gift award?', 'advanced-quiz')
            ));
        }
    }
    
    public function gifts_management_page() {
        global $wpdb;
        
        // Handle messages
        $message = '';
        if (isset($_GET['message'])) {
            switch ($_GET['message']) {
                case 'gift_saved':
                    $message = '<div class="notice notice-success"><p>Gift saved successfully!</p></div>';
                    break;
                case 'gift_deleted':
                    $message = '<div class="notice notice-success"><p>Gift deleted successfully!</p></div>';
                    break;
                case 'error':
                    $message = '<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>';
                    break;
            }
        }
        
        // Get campaigns for dropdown
        $campaigns = $wpdb->get_results("SELECT id, title, status FROM {$wpdb->prefix}aqm_campaigns ORDER BY created_at DESC");
        
        // Get existing gifts with campaign info
        $gifts = $wpdb->get_results("
            SELECT g.*, c.title as campaign_title, c.status as campaign_status,
                   COUNT(ga.id) as awards_count
            FROM {$wpdb->prefix}aqm_gifts g 
            LEFT JOIN {$wpdb->prefix}aqm_campaigns c ON g.campaign_id = c.id 
            LEFT JOIN {$wpdb->prefix}aqm_gift_awards ga ON g.id = ga.gift_id
            GROUP BY g.id
            ORDER BY g.created_at DESC
        ");
        
        ?>
        <div class="wrap aqm-gifts-page">
            <h1 class="wp-heading-inline">Gift Management</h1>
            <a href="#" id="add-new-gift" class="page-title-action">Add New Gift</a>
            
            <?php echo $message; ?>
            
            <div class="aqm-gifts-header">
                <div class="aqm-stats-grid">
                    <div class="aqm-stat-card">
                        <div class="stat-number"><?php echo count($gifts); ?></div>
                        <div class="stat-label">Total Gifts</div>
                    </div>
                    <div class="aqm-stat-card">
                        <div class="stat-number">
                            <?php 
                            $active_gifts = array_filter($gifts, function($g) { return $g->is_active; });
                            echo count($active_gifts); 
                            ?>
                        </div>
                        <div class="stat-label">Active Gifts</div>
                    </div>
                    <div class="aqm-stat-card">
                        <div class="stat-number">
                            <?php 
                            $total_awards = array_sum(array_column($gifts, 'awards_count'));
                            echo $total_awards; 
                            ?>
                        </div>
                        <div class="stat-label">Total Awards</div>
                    </div>
                    <div class="aqm-stat-card">
                        <div class="stat-number">
                            <?php 
                            $total_remaining = array_sum(array_map(function($g) {
                                return $g->quantity_total > 0 ? $g->quantity_remaining : 0;
                            }, $gifts));
                            echo $total_remaining;
                            ?>
                        </div>
                        <div class="stat-label">Remaining Stock</div>
                    </div>
                </div>
                
                <div class="aqm-actions-toolbar">
                    <button id="bulk-generate-codes" class="button">Bulk Generate Codes</button>
                    <button id="export-awards" class="button">Export Awards</button>
                    <button id="import-gifts" class="button">Import Gifts</button>
                </div>
            </div>
            
            <!-- Gift Form Modal -->
            <div id="gift-form-modal" class="aqm-modal" style="display: none;">
                <div class="aqm-modal-content">
                    <div class="aqm-modal-header">
                        <h2 id="gift-form-title">Add New Gift</h2>
                        <span class="aqm-modal-close">&times;</span>
                    </div>
                    
                    <form id="gift-form" class="aqm-gift-form">
                        <input type="hidden" id="gift-id" name="gift_id" value="">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="campaign-id">Campaign <span class="required">*</span></label>
                                <select name="campaign_id" id="campaign-id" required>
                                    <option value="">Select Campaign</option>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo esc_attr($campaign->id); ?>" 
                                                data-status="<?php echo esc_attr($campaign->status); ?>">
                                            <?php echo esc_html($campaign->title); ?>
                                            (<?php echo esc_html(ucfirst($campaign->status)); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="gift-type">Gift Type</label>
                                <select name="gift_type" id="gift-type">
                                    <option value="voucher">Voucher</option>
                                    <option value="discount">Discount Code</option>
                                    <option value="physical">Physical Prize</option>
                                    <option value="points">Points</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="gift-name">Gift Name <span class="required">*</span></label>
                                <input type="text" name="gift_name" id="gift-name" class="regular-text" required 
                                       placeholder="e.g., Voucher 100K, Free Shipping, etc.">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="gift-value">Gift Value</label>
                                <input type="text" name="gift_value" id="gift-value" class="regular-text" 
                                       placeholder="e.g., $10, 50%, 1000 points">
                                <p class="description">Value depends on gift type</p>
                            </div>
                            
                            <div class="form-group">
                                <label for="gift-code-prefix">Code Prefix</label>
                                <input type="text" name="gift_code_prefix" id="gift-code-prefix" class="regular-text" 
                                       placeholder="e.g., GIFT, VOUCHER, PROMO">
                                <p class="description">Prefix for auto-generated codes</p>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="gift-description">Description</label>
                                <textarea name="description" id="gift-description" rows="3" class="large-text"
                                          placeholder="Describe what this gift offers..."></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Quantity & Availability</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="quantity-total">Total Quantity</label>
                                    <input type="number" name="quantity_total" id="quantity-total" min="0" value="0">
                                    <p class="description">0 = unlimited</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="is-active">Status</label>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="is_active" id="is-active" value="1" checked>
                                        <span class="toggle-slider"></span>
                                        <span class="toggle-label">Active</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Eligibility Rules</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="min-score">Min Score (%)</label>
                                    <input type="number" name="min_score" id="min-score" min="0" max="100" value="0">
                                </div>
                                
                                <div class="form-group">
                                    <label for="max-score">Max Score (%)</label>
                                    <input type="number" name="max_score" id="max-score" min="0" max="100" value="100">
                                </div>
                                
                                <div class="form-group">
                                    <label for="probability">Win Probability (%)</label>
                                    <input type="number" name="probability" id="probability" min="0" max="100" 
                                           step="0.1" value="10">
                                    <p class="description">Chance of winning this gift</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Valid Period</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="valid-from">Valid From</label>
                                    <input type="datetime-local" name="valid_from" id="valid-from">
                                </div>
                                
                                <div class="form-group">
                                    <label for="valid-until">Valid Until</label>
                                    <input type="datetime-local" name="valid_until" id="valid-until">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="button button-primary">
                                <span class="dashicons dashicons-yes"></span>
                                Save Gift
                            </button>
                            <button type="button" class="button button-secondary" onclick="closeGiftModal()">
                                <span class="dashicons dashicons-no-alt"></span>
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Gifts List -->
            <div class="aqm-gifts-list">
                <?php if (empty($gifts)): ?>
                    <div class="aqm-empty-state">
                        <div class="empty-icon">🎁</div>
                        <h3>No gifts found</h3>
                        <p>Create your first gift to start rewarding quiz participants!</p>
                        <button class="button button-primary" onclick="document.getElementById('add-new-gift').click()">
                            <span class="dashicons dashicons-plus"></span>
                            Add Your First Gift
                        </button>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped aqm-gifts-table">
                        <thead>
                            <tr>
                                <th class="column-gift">Gift Details</th>
                                <th class="column-campaign">Campaign</th>
                                <th class="column-type">Type & Value</th>
                                <th class="column-quantity">Quantity</th>
                                <th class="column-eligibility">Eligibility</th>
                                <th class="column-awards">Awards</th>
                                <th class="column-status">Status</th>
                                <th class="column-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gifts as $gift): ?>
                                <tr data-gift-id="<?php echo esc_attr($gift->id); ?>" 
                                    class="<?php echo $gift->is_active ? 'active' : 'inactive'; ?>">
                                    <td class="column-gift">
                                        <div class="gift-name">
                                            <strong><?php echo esc_html($gift->gift_name); ?></strong>
                                        </div>
                                        <?php if ($gift->description): ?>
                                            <div class="gift-description">
                                                <?php echo esc_html(wp_trim_words($gift->description, 10)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="gift-meta">
                                            <span class="gift-id">ID: <?php echo $gift->id; ?></span>
                                            <?php if ($gift->gift_code_prefix): ?>
                                                <span class="gift-prefix">Prefix: <?php echo esc_html($gift->gift_code_prefix); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="column-campaign">
                                        <div class="campaign-name">
                                            <?php echo esc_html($gift->campaign_title ?: 'Unknown Campaign'); ?>
                                        </div>
                                        <span class="campaign-status campaign-status-<?php echo esc_attr($gift->campaign_status); ?>">
                                            <?php echo esc_html(ucfirst($gift->campaign_status ?: 'unknown')); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="column-type">
                                        <div class="gift-type">
                                            <span class="gift-type-badge gift-type-<?php echo esc_attr($gift->gift_type); ?>">
                                                <?php echo esc_html(ucfirst($gift->gift_type)); ?>
                                            </span>
                                        </div>
                                        <?php if ($gift->gift_value): ?>
                                            <div class="gift-value">
                                                <?php echo esc_html($gift->gift_value); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="column-quantity">
                                        <?php if ($gift->quantity_total > 0): ?>
                                            <div class="quantity-info">
                                                <span class="quantity-numbers">
                                                    <?php echo esc_html($gift->quantity_remaining . '/' . $gift->quantity_total); ?>
                                                </span>
                                                <div class="quantity-bar">
                                                    <?php 
                                                    $percentage = ($gift->quantity_total > 0) ? 
                                                        (($gift->quantity_total - $gift->quantity_remaining) / $gift->quantity_total * 100) : 0;
                                                    ?>
                                                    <div class="quantity-progress" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <small class="quantity-used"><?php echo round($percentage, 1); ?>% used</small>
                                            </div>
                                        <?php else: ?>
                                            <span class="unlimited">Unlimited</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="column-eligibility">
                                        <div class="score-range">
                                            Score: <?php echo $gift->min_score; ?>%-<?php echo $gift->max_score; ?>%
                                        </div>
                                        <div class="probability">
                                            Chance: <?php echo $gift->probability; ?>%
                                        </div>
                                    </td>
                                    
                                    <td class="column-awards">
                                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-gift-awards&gift_id=' . $gift->id); ?>" 
                                           class="awards-link">
                                            <span class="awards-count"><?php echo $gift->awards_count; ?></span>
                                            <span class="awards-label">awards</span>
                                        </a>
                                    </td>
                                    
                                    <td class="column-status">
                                        <span class="status-indicator status-<?php echo $gift->is_active ? 'active' : 'inactive'; ?>">
                                            <?php echo $gift->is_active ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        
                                        <?php if ($gift->valid_until && strtotime($gift->valid_until) < time()): ?>
                                            <div class="status-warning">Expired</div>
                                        <?php endif; ?>
                                        
                                        <?php if ($gift->quantity_total > 0 && $gift->quantity_remaining <= 0): ?>
                                            <div class="status-warning">Out of Stock</div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="column-actions">
                                        <div class="row-actions">
                                            <button class="button button-small edit-gift" 
                                                    data-gift-id="<?php echo esc_attr($gift->id); ?>"
                                                    title="Edit Gift">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                            
                                            <button class="button button-small duplicate-gift" 
                                                    data-gift-id="<?php echo esc_attr($gift->id); ?>"
                                                    title="Duplicate Gift">
                                                <span class="dashicons dashicons-admin-page"></span>
                                            </button>
                                            
                                            <button class="button button-small generate-codes" 
                                                    data-gift-id="<?php echo esc_attr($gift->id); ?>"
                                                    title="Generate Codes">
                                                <span class="dashicons dashicons-tickets-alt"></span>
                                            </button>
                                            
                                            <button class="button button-small button-link-delete delete-gift" 
                                                    data-gift-id="<?php echo esc_attr($gift->id); ?>"
                                                    title="Delete Gift">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function gift_awards_page() {
        global $wpdb;
        
        $gift_id = isset($_GET['gift_id']) ? intval($_GET['gift_id']) : 0;
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        
        // Build WHERE clause
        $where_conditions = array();
        $params = array();
        
        if ($gift_id > 0) {
            $where_conditions[] = 'ga.gift_id = %d';
            $params[] = $gift_id;
        }
        
        if ($campaign_id > 0) {
            $where_conditions[] = 'ga.campaign_id = %d';
            $params[] = $campaign_id;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get awards with pagination
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Count total awards
        $total_awards = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_gift_awards ga $where_clause",
            $params
        ));
        
        // Get awards for current page
        $awards_query = $wpdb->prepare(
            "SELECT ga.*, g.gift_name, g.gift_type, g.gift_value, c.title as campaign_title
             FROM {$wpdb->prefix}aqm_gift_awards ga
             LEFT JOIN {$wpdb->prefix}aqm_gifts g ON ga.gift_id = g.id
             LEFT JOIN {$wpdb->prefix}aqm_campaigns c ON ga.campaign_id = c.id
             $where_clause
             ORDER BY ga.awarded_at DESC
             LIMIT %d OFFSET %d",
            array_merge($params, array($per_page, $offset))
        );
        
        $awards = $wpdb->get_results($awards_query);
        
        // Calculate pagination
        $total_pages = ceil($total_awards / $per_page);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Gift Awards</h1>
            
            <?php if ($gift_id > 0): ?>
                <?php 
                $gift = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}aqm_gifts WHERE id = %d", 
                    $gift_id
                ));
                ?>
                <div class="aqm-filter-info">
                    <strong>Filtered by Gift:</strong> <?php echo esc_html($gift->gift_name ?? 'Unknown Gift'); ?>
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-gift-awards'); ?>" class="button button-small">Clear Filter</a>
                </div>
            <?php endif; ?>
            
            <div class="aqm-awards-stats">
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $total_awards; ?></span>
                        <span class="stat-label">Total Awards</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">
                            <?php 
                            echo $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_gift_awards ga 
                                 WHERE ga.claim_status = 'claimed' $where_clause", 
                                $params
                            ));
                            ?>
                        </span>
                        <span class="stat-label">Claimed</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">
                            <?php 
                            echo $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_gift_awards ga 
                                 WHERE ga.claim_status = 'awarded' $where_clause", 
                                $params
                            ));
                            ?>
                        </span>
                        <span class="stat-label">Pending</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">
                            <?php 
                            echo $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_gift_awards ga 
                                 WHERE ga.expiry_date < NOW() AND ga.claim_status = 'awarded' $where_clause", 
                                $params
                            ));
                            ?>
                        </span>
                        <span class="stat-label">Expired</span>
                    </div>
                </div>
            </div>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <button class="button" onclick="exportAwards()">
                        <span class="dashicons dashicons-download"></span>
                        Export CSV
                    </button>
                    <button class="button" onclick="emailAwards()">
                        <span class="dashicons dashicons-email"></span>
                        Email Winners
                    </button>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo $total_awards; ?> items</span>
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        echo $page_links;
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <table class="wp-list-table widefat fixed striped aqm-awards-table">
                <thead>
                    <tr>
                        <th class="column-award">Award Details</th>
                        <th class="column-participant">Participant</th>
                        <th class="column-gift">Gift</th>
                        <th class="column-code">Gift Code</th>
                        <th class="column-score">Score</th>
                        <th class="column-status">Status</th>
                        <th class="column-dates">Dates</th>
                        <th class="column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($awards)): ?>
                        <tr>
                            <td colspan="8" class="aqm-empty-awards">
                                <div class="empty-state">
                                    <span class="dashicons dashicons-awards"></span>
                                    <p>No gift awards found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($awards as $award): ?>
                            <tr data-award-id="<?php echo esc_attr($award->id); ?>">
                                <td class="column-award">
                                    <div class="award-id">
                                        <strong>#<?php echo $award->id; ?></strong>
                                    </div>
                                    <div class="campaign-name">
                                        <?php echo esc_html($award->campaign_title); ?>
                                    </div>
                                </td>
                                
                                <td class="column-participant">
                                    <div class="participant-name">
                                        <strong><?php echo esc_html($award->participant_name ?: 'Anonymous'); ?></strong>
                                    </div>
                                    <?php if ($award->participant_email): ?>
                                        <div class="participant-email">
                                            <a href="mailto:<?php echo esc_attr($award->participant_email); ?>">
                                                <?php echo esc_html($award->participant_email); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="column-gift">
                                    <div class="gift-name">
                                        <?php echo esc_html($award->gift_name); ?>
                                    </div>
                                    <div class="gift-details">
                                        <span class="gift-type-badge gift-type-<?php echo esc_attr($award->gift_type); ?>">
                                            <?php echo esc_html(ucfirst($award->gift_type)); ?>
                                        </span>
                                        <?php if ($award->gift_value): ?>
                                            <span class="gift-value"><?php echo esc_html($award->gift_value); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td class="column-code">
                                    <div class="gift-code-container">
                                        <code class="gift-code"><?php echo esc_html($award->gift_code); ?></code>
                                        <button class="button button-small copy-code" 
                                                data-code="<?php echo esc_attr($award->gift_code); ?>"
                                                title="Copy Code">
                                            <span class="dashicons dashicons-clipboard"></span>
                                        </button>
                                    </div>
                                </td>
                                
                                <td class="column-score">
                                    <div class="score-display">
                                        <span class="score-number"><?php echo esc_html($award->score_achieved); ?>%</span>
                                    </div>
                                </td>
                                
                                <td class="column-status">
                                    <span class="status-badge status-<?php echo esc_attr($award->claim_status); ?>">
                                        <?php echo esc_html(ucfirst($award->claim_status)); ?>
                                    </span>
                                    
                                    <?php if ($award->expiry_date && strtotime($award->expiry_date) < time() && $award->claim_status === 'awarded'): ?>
                                        <div class="status-warning">Expired</div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="column-dates">
                                    <div class="date-awarded">
                                        <strong>Awarded:</strong><br>
                                        <?php echo esc_html(date('M j, Y H:i', strtotime($award->awarded_at))); ?>
                                    </div>
                                    
                                    <?php if ($award->claimed_at): ?>
                                        <div class="date-claimed">
                                            <strong>Claimed:</strong><br>
                                            <?php echo esc_html(date('M j, Y H:i', strtotime($award->claimed_at))); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($award->expiry_date): ?>
                                        <div class="date-expiry">
                                            <strong>Expires:</strong><br>
                                            <?php echo esc_html(date('M j, Y', strtotime($award->expiry_date))); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="column-actions">
                                    <div class="row-actions">
                                        <button class="button button-small view-details" 
                                                data-award-id="<?php echo esc_attr($award->id); ?>"
                                                title="View Details">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </button>
                                        
                                        <?php if ($award->claim_status === 'awarded'): ?>
                                            <button class="button button-small revoke-award" 
                                                    data-award-id="<?php echo esc_attr($award->id); ?>"
                                                    title="Revoke Award">
                                                <span class="dashicons dashicons-no"></span>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($award->participant_email): ?>
                                            <button class="button button-small resend-email" 
                                                    data-award-id="<?php echo esc_attr($award->id); ?>"
                                                    title="Resend Email">
                                                <span class="dashicons dashicons-email"></span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php echo $page_links; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function save_gift() {
        check_ajax_referer('aqm_gift_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        
        try {
            $gift_id = intval($_POST['gift_id']);
            
            // Validate required fields
            if (empty($_POST['campaign_id']) || empty($_POST['gift_name'])) {
                throw new Exception('Campaign and Gift Name are required');
            }
            
            $data = array(
                'campaign_id' => intval($_POST['campaign_id']),
                'gift_name' => sanitize_text_field($_POST['gift_name']),
                'gift_type' => sanitize_text_field($_POST['gift_type']),
                'gift_value' => sanitize_text_field($_POST['gift_value']),
                'description' => sanitize_textarea_field($_POST['description']),
                'quantity_total' => intval($_POST['quantity_total']),
                'min_score' => intval($_POST['min_score']),
                'max_score' => intval($_POST['max_score']),
                'probability' => floatval($_POST['probability']),
                'valid_from' => sanitize_text_field($_POST['valid_from']) ?: null,
                'valid_until' => sanitize_text_field($_POST['valid_until']) ?: null,
                'gift_code_prefix' => sanitize_text_field($_POST['gift_code_prefix']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            );
            
            // Convert datetime format
            if ($data['valid_from']) {
                $data['valid_from'] = date('Y-m-d H:i:s', strtotime($data['valid_from']));
            }
            if ($data['valid_until']) {
                $data['valid_until'] = date('Y-m-d H:i:s', strtotime($data['valid_until']));
            }
            
            if ($gift_id > 0) {
                // Update existing gift
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT quantity_total, quantity_remaining FROM {$wpdb->prefix}aqm_gifts WHERE id = %d",
                    $gift_id
                ));
                
                if (!$existing) {
                    throw new Exception('Gift not found');
                }
                
                // Adjust remaining quantity if total quantity changed
                if ($existing->quantity_total != $data['quantity_total']) {
                    $difference = $data['quantity_total'] - $existing->quantity_total;
                    $data['quantity_remaining'] = max(0, $existing->quantity_remaining + $difference);
                }
                
                $result = $wpdb->update($wpdb->prefix . 'aqm_gifts', $data, array('id' => $gift_id));
                
                if ($result === false) {
                    throw new Exception('Failed to update gift');
                }
                
                $message = 'Gift updated successfully';
                
            } else {
                // Create new gift
                $data['quantity_remaining'] = $data['quantity_total'];
                $result = $wpdb->insert($wpdb->prefix . 'aqm_gifts', $data);
                
                if ($result === false) {
                    throw new Exception('Failed to create gift');
                }
                
                $gift_id = $wpdb->insert_id;
                $message = 'Gift created successfully';
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'gift_id' => $gift_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function get_gift() {
        check_ajax_referer('aqm_gift_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $gift_id = intval($_POST['gift_id']);
        
        global $wpdb;
        $gift = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aqm_gifts WHERE id = %d",
            $gift_id
        ));
        
        if ($gift) {
            // Format datetime fields for HTML inputs
            if ($gift->valid_from) {
                $gift->valid_from = date('Y-m-d\TH:i', strtotime($gift->valid_from));
            }
            if ($gift->valid_until) {
                $gift->valid_until = date('Y-m-d\TH:i', strtotime($gift->valid_until));
            }
            
            wp_send_json_success($gift);
        } else {
            wp_send_json_error('Gift not found');
        }
    }
    
    public function delete_gift() {
        check_ajax_referer('aqm_gift_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        $gift_id = intval($_POST['gift_id']);
        
        try {
            // Check if gift has awards
            $awards_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_gift_awards WHERE gift_id = %d",
                $gift_id
            ));
            
            if ($awards_count > 0) {
                wp_send_json_error("Cannot delete gift with {$awards_count} existing awards. Consider deactivating instead.");
                return;
            }
            
            $result = $wpdb->delete($wpdb->prefix . 'aqm_gifts', array('id' => $gift_id));
            
            if ($result !== false) {
                wp_send_json_success('Gift deleted successfully');
            } else {
                wp_send_json_error('Failed to delete gift');
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function revoke_gift_award() {
        check_ajax_referer('aqm_gift_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        $award_id = intval($_POST['award_id']);
        
        try {
            $result = $wpdb->update(
                $wpdb->prefix . 'aqm_gift_awards',
                array('claim_status' => 'revoked'),
                array('id' => $award_id)
            );
            
            if ($result !== false) {
                wp_send_json_success('Gift award revoked successfully');
            } else {
                wp_send_json_error('Failed to revoke gift award');
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function export_gift_awards() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!wp_verify_nonce($_GET['nonce'], 'aqm_gift_nonce')) {
            wp_die('Security check failed');
        }
        
        global $wpdb;
        
        $gift_id = isset($_GET['gift_id']) ? intval($_GET['gift_id']) : 0;
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        
        $where_conditions = array();
        $params = array();
        
        if ($gift_id > 0) {
            $where_conditions[] = 'ga.gift_id = %d';
            $params[] = $gift_id;
        }
        
        if ($campaign_id > 0) {
            $where_conditions[] = 'ga.campaign_id = %d';
            $params[] = $campaign_id;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $awards = $wpdb->get_results($wpdb->prepare("
            SELECT ga.*, g.gift_name, g.gift_type, g.gift_value, c.title as campaign_title
            FROM {$wpdb->prefix}aqm_gift_awards ga
            LEFT JOIN {$wpdb->prefix}aqm_gifts g ON ga.gift_id = g.id
            LEFT JOIN {$wpdb->prefix}aqm_campaigns c ON ga.campaign_id = c.id
            $where_clause
            ORDER BY ga.awarded_at DESC
        ", $params));
        
        // Set CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="gift-awards-' . date('Y-m-d-H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV headers
        fputcsv($output, array(
            'Award ID', 'Campaign', 'Gift Name', 'Gift Type', 'Gift Value',
            'Participant Name', 'Participant Email', 'Gift Code', 'Score',
            'Status', 'Awarded Date', 'Claimed Date', 'Expiry Date'
        ));
        
        // CSV data
        foreach ($awards as $award) {
            fputcsv($output, array(
                $award->id,
                $award->campaign_title,
                $award->gift_name,
                $award->gift_type,
                $award->gift_value,
                $award->participant_name,
                $award->participant_email,
                $award->gift_code,
                $award->score_achieved,
                $award->claim_status,
                $award->awarded_at,
                $award->claimed_at,
                $award->expiry_date
            ));
        }
        
        fclose($output);
        exit;
    }
}

// Initialize Gift Manager
new AQM_Gift_Manager();