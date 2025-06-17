<?php
/**
 * ULTRA-LIGHTWEIGHT Campaign Module
 * File: modules/campaigns/class-campaign-module-lite.php
 * 
 * MEMORY OPTIMIZATION FEATURES:
 * - Minimal data loading (only essential fields)
 * - No statistics by default (lazy loading)
 * - Smart caching (5 minutes)
 * - Simple queries (no JOINs)
 * - Memory monitoring
 * - Fast admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Campaign_Module_Lite {
    
    private static $instance = null;
    private $model;
    private $manager;
    private $memory_start;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->memory_start = memory_get_usage(true);
        $this->init();
    }
    
    private function init() {
        // Load minimal components
        $this->load_lite_model();
        $this->load_lite_manager();
        
        // Register hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_lite_scripts'));
    }
    
    /**
     * Load ultra-lightweight campaign model
     */
    private function load_lite_model() {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/campaigns/class-campaign-model-lite.php';
        $this->model = new Vefify_Campaign_Model_Lite();
    }
    
    /**
     * Load minimal campaign manager
     */
    private function load_lite_manager() {
        if (is_admin()) {
            require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/campaigns/class-campaign-manager-lite.php';
            $this->manager = new Vefify_Campaign_Manager_Lite($this->model);
        }
    }
    
    /**
     * Add lightweight admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'vefify-quiz',
            'Campaigns',
            '📋 Campaigns',
            'manage_options',
            'vefify-campaigns',
            array($this, 'admin_page_router')
        );
    }
    
    /**
     * Simple admin page router
     */
    public function admin_page_router() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'new':
            case 'edit':
                $this->manager->display_simple_form();
                break;
            default:
                $this->manager->display_simple_list();
                break;
        }
    }
    
    /**
     * Minimal script loading
     */
    public function enqueue_lite_scripts($hook) {
        if (strpos($hook, 'vefify-campaigns') === false) {
            return;
        }
        
        // Only load essential styles
        wp_enqueue_style('vefify-campaign-lite', VEFIFY_QUIZ_PLUGIN_URL . 
            'assets/css/campaign-lite.css', array(), VEFIFY_QUIZ_VERSION);
    }
    
    /**
     * Get minimal analytics for dashboard
     */
    public function get_module_analytics() {
        $cache_key = 'vefify_campaign_analytics_lite';
        $analytics = wp_cache_get($cache_key);
        
        if ($analytics === false) {
            $summary = $this->model->get_basic_summary();
            
            $analytics = array(
                'title' => 'Campaign Management',
                'description' => 'Lightweight campaign system',
                'icon' => '📋',
                'stats' => array(
                    'total_campaigns' => array(
                        'label' => 'Total Campaigns',
                        'value' => $summary['total'],
                        'trend' => 'Active system'
                    ),
                    'active_campaigns' => array(
                        'label' => 'Active Now',
                        'value' => $summary['active'],
                        'trend' => 'Running'
                    ),
                    'memory_usage' => array(
                        'label' => 'Memory Usage',
                        'value' => $this->get_memory_usage() . 'MB',
                        'trend' => 'Optimized'
                    )
                ),
                'quick_actions' => array(
                    array(
                        'label' => 'Quick Create',
                        'url' => admin_url('admin.php?page=vefify-campaigns&action=new'),
                        'class' => 'button-primary'
                    ),
                    array(
                        'label' => 'View All',
                        'url' => admin_url('admin.php?page=vefify-campaigns'),
                        'class' => 'button-secondary'
                    )
                )
            );
            
            wp_cache_set($cache_key, $analytics, '', 300); // 5 minute cache
        }
        
        return $analytics;
    }
    
    /**
     * Monitor memory usage
     */
    public function get_memory_usage() {
        $current_memory = memory_get_usage(true);
        $module_memory = $current_memory - $this->memory_start;
        return round($module_memory / 1024 / 1024, 1);
    }
    
    /**
     * Get model instance
     */
    public function get_model() {
        return $this->model;
    }
    
    /**
     * Get manager instance  
     */
    public function get_manager() {
        return $this->manager;
    }
}

// ============================================================================
// ULTRA-LIGHTWEIGHT CAMPAIGN MODEL
// ============================================================================

class Vefify_Campaign_Model_Lite {
    
    private $wpdb;
    private $table;
    private $cache_group = 'vefify_campaigns_lite';
    private $cache_time = 300; // 5 minutes
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'campaigns';
    }
    
    /**
     * Get campaigns - MINIMAL data only
     */
    public function get_campaigns_lite($page = 1, $per_page = 10) {
        $cache_key = "campaigns_lite_p{$page}";
        $result = wp_cache_get($cache_key, $this->cache_group);
        
        if ($result !== false) {
            return $result;
        }
        
        $offset = ($page - 1) * $per_page;
        
        // MINIMAL query - only essential fields
        $campaigns = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, name, is_active, start_date, end_date, created_at 
             FROM {$this->table} 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ), ARRAY_A);
        
        // Get total count (cached separately)
        $total = $this->get_total_count();
        
        $result = array(
            'campaigns' => $campaigns ?: array(),
            'total' => $total,
            'total_pages' => ceil($total / $per_page),
            'current_page' => $page
        );
        
        wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_time);
        return $result;
    }
    
    /**
     * Get single campaign - MINIMAL data
     */
    public function get_campaign_lite($id) {
        if (!$id) return null;
        
        $cache_key = "campaign_lite_{$id}";
        $campaign = wp_cache_get($cache_key, $this->cache_group);
        
        if ($campaign !== false) {
            return $campaign;
        }
        
        $campaign = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id, name, description, start_date, end_date, is_active, 
                    questions_per_quiz, pass_score, time_limit, max_participants
             FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if ($campaign) {
            wp_cache_set($cache_key, $campaign, $this->cache_group, $this->cache_time);
        }
        
        return $campaign;
    }
    
    /**
     * Create campaign - MINIMAL processing
     */
    public function create_campaign_lite($data) {
        $campaign_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'start_date' => sanitize_text_field($data['start_date']),
            'end_date' => sanitize_text_field($data['end_date']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'questions_per_quiz' => max(1, intval($data['questions_per_quiz'] ?? 5)),
            'pass_score' => max(1, intval($data['pass_score'] ?? 3)),
            'time_limit' => max(0, intval($data['time_limit'] ?? 600)),
            'max_participants' => max(0, intval($data['max_participants'] ?? 100)),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $result = $this->wpdb->insert($this->table, $campaign_data);
        
        if ($result === false) {
            return new WP_Error('create_failed', 'Failed to create campaign');
        }
        
        $this->clear_cache();
        return $this->wpdb->insert_id;
    }
    
    /**
     * Update campaign - MINIMAL processing
     */
    public function update_campaign_lite($id, $data) {
        if (!$id) return false;
        
        $update_data = array();
        $allowed_fields = array('name', 'description', 'start_date', 'end_date', 
                               'is_active', 'questions_per_quiz', 'pass_score', 
                               'time_limit', 'max_participants');
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $field === 'description' ? 
                    sanitize_textarea_field($data[$field]) : 
                    sanitize_text_field($data[$field]);
            }
        }
        
        if (empty($update_data)) {
            return true;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $result = $this->wpdb->update(
            $this->table,
            $update_data,
            array('id' => $id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            $this->clear_cache();
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete campaign
     */
    public function delete_campaign_lite($id) {
        if (!$id) return false;
        
        $result = $this->wpdb->delete(
            $this->table,
            array('id' => $id),
            array('%d')
        );
        
        if ($result !== false) {
            $this->clear_cache();
            return true;
        }
        
        return false;
    }
    
    /**
     * Get basic summary - CACHED
     */
    public function get_basic_summary() {
        $cache_key = 'campaigns_summary';
        $summary = wp_cache_get($cache_key, $this->cache_group);
        
        if ($summary !== false) {
            return $summary;
        }
        
        $total = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
        $active = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE is_active = 1");
        
        $summary = array(
            'total' => intval($total),
            'active' => intval($active),
            'inactive' => intval($total) - intval($active)
        );
        
        wp_cache_set($cache_key, $summary, $this->cache_group, $this->cache_time);
        return $summary;
    }
    
    /**
     * Get total count - CACHED
     */
    private function get_total_count() {
        $cache_key = 'campaigns_total_count';
        $total = wp_cache_get($cache_key, $this->cache_group);
        
        if ($total === false) {
            $total = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
            wp_cache_set($cache_key, intval($total), $this->cache_group, $this->cache_time);
        }
        
        return intval($total);
    }
    
    /**
     * Clear all cache
     */
    private function clear_cache() {
        wp_cache_flush_group($this->cache_group);
    }
    
    /**
     * Validate campaign data - MINIMAL
     */
    public function validate_lite($data) {
        $errors = array();
        
        if (empty($data['name'])) {
            $errors[] = 'Campaign name is required';
        }
        
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            if (strtotime($data['end_date']) <= strtotime($data['start_date'])) {
                $errors[] = 'End date must be after start date';
            }
        }
        
        return $errors;
    }
}

// ============================================================================
// MINIMAL CAMPAIGN MANAGER
// ============================================================================

class Vefify_Campaign_Manager_Lite {
    
    private $model;
    
    public function __construct($model) {
        $this->model = $model;
        
        // Only register essential hooks
        add_action('admin_init', array($this, 'handle_actions'));
    }
    
    /**
     * Display simple campaign list
     */
    public function display_simple_list() {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $result = $this->model->get_campaigns_lite($page, 10);
        
        $this->show_notices();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">📋 Campaigns</h1>
            <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=new'); ?>" class="page-title-action">Add New</a>
            
            <div class="campaign-summary">
                <?php $summary = $this->model->get_basic_summary(); ?>
                <span class="summary-item">Total: <strong><?php echo $summary['total']; ?></strong></span>
                <span class="summary-item">Active: <strong><?php echo $summary['active']; ?></strong></span>
                <span class="summary-item">Memory: <strong><?php echo round(memory_get_usage(true) / 1024 / 1024, 1); ?>MB</strong></span>
            </div>
            
            <?php if (empty($result['campaigns'])): ?>
                <div class="notice notice-info">
                    <p>No campaigns found. <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=new'); ?>">Create your first campaign</a></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Campaign Name</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['campaigns'] as $campaign): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $campaign['id']); ?>">
                                            <?php echo esc_html($campaign['name']); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <span class="status-<?php echo $campaign['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $campaign['is_active'] ? '✅ Active' : '⏸️ Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M j', strtotime($campaign['start_date'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($campaign['end_date'])); ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $campaign['id']); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vefify-campaigns&action=delete&id=' . $campaign['id']), 'delete_campaign'); ?>" 
                                       class="button button-small" onclick="return confirm('Delete this campaign?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($result['total_pages'] > 1): ?>
                    <div class="tablenav">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'current' => $result['current_page'],
                            'total' => $result['total_pages'],
                        ));
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <style>
        .campaign-summary { margin: 20px 0; padding: 10px; background: #f9f9f9; border-radius: 4px; }
        .summary-item { margin-right: 20px; }
        .status-active { color: #46b450; }
        .status-inactive { color: #dc3232; }
        </style>
        <?php
    }
    
    /**
     * Display simple campaign form
     */
    public function display_simple_form() {
        $campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $campaign = $campaign_id ? $this->model->get_campaign_lite($campaign_id) : null;
        $is_edit = !empty($campaign);
        
        // Set defaults for new campaigns
        if (!$is_edit) {
            $campaign = array(
                'name' => '',
                'description' => '',
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+30 days')),
                'questions_per_quiz' => 5,
                'pass_score' => 3,
                'time_limit' => 600,
                'max_participants' => 100,
                'is_active' => 1
            );
        }
        
        $this->show_notices();
        
        ?>
        <div class="wrap">
            <h1><?php echo $is_edit ? 'Edit Campaign' : 'New Campaign'; ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('vefify_campaign_lite'); ?>
                <input type="hidden" name="action" value="save_campaign">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="name">Campaign Name *</label></th>
                        <td><input type="text" id="name" name="name" value="<?php echo esc_attr($campaign['name']); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="description">Description</label></th>
                        <td><textarea id="description" name="description" rows="3" class="large-text"><?php echo esc_textarea($campaign['description']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="start_date">Start Date</label></th>
                        <td><input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($campaign['start_date']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="end_date">End Date</label></th>
                        <td><input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($campaign['end_date']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="questions_per_quiz">Questions per Quiz</label></th>
                        <td><input type="number" id="questions_per_quiz" name="questions_per_quiz" value="<?php echo intval($campaign['questions_per_quiz']); ?>" min="1" max="50" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="pass_score">Pass Score</label></th>
                        <td><input type="number" id="pass_score" name="pass_score" value="<?php echo intval($campaign['pass_score']); ?>" min="1" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="time_limit">Time Limit (seconds)</label></th>
                        <td><input type="number" id="time_limit" name="time_limit" value="<?php echo intval($campaign['time_limit']); ?>" min="0" step="60" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="max_participants">Max Participants</label></th>
                        <td><input type="number" id="max_participants" name="max_participants" value="<?php echo intval($campaign['max_participants']); ?>" min="0" class="small-text"></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><label><input type="checkbox" name="is_active" value="1" <?php checked($campaign['is_active'], 1); ?>> Active</label></td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php echo $is_edit ? 'Update Campaign' : 'Create Campaign'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=vefify-campaigns'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle form actions
     */
    public function handle_actions() {
        if (!isset($_POST['action']) || !current_user_can('manage_options')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['action']);
        
        if ($action === 'save_campaign') {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_campaign_lite')) {
                wp_die('Security check failed');
            }
            
            $this->save_campaign();
        }
        
        // Handle URL actions (delete)
        if (isset($_GET['action']) && $_GET['action'] === 'delete') {
            $this->delete_campaign();
        }
    }
    
    /**
     * Save campaign (create/update)
     */
    private function save_campaign() {
        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        
        $data = array(
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'questions_per_quiz' => $_POST['questions_per_quiz'] ?? 5,
            'pass_score' => $_POST['pass_score'] ?? 3,
            'time_limit' => $_POST['time_limit'] ?? 600,
            'max_participants' => $_POST['max_participants'] ?? 100,
            'is_active' => isset($_POST['is_active'])
        );
        
        // Validate
        $errors = $this->model->validate_lite($data);
        if (!empty($errors)) {
            set_transient('vefify_notice', array(
                'type' => 'error',
                'message' => implode(', ', $errors)
            ), 30);
            return;
        }
        
        if ($campaign_id) {
            // Update
            $result = $this->model->update_campaign_lite($campaign_id, $data);
            $message = 'Campaign updated successfully';
        } else {
            // Create
            $result = $this->model->create_campaign_lite($data);
            $message = 'Campaign created successfully';
            $campaign_id = $result;
        }
        
        if (is_wp_error($result)) {
            set_transient('vefify_notice', array(
                'type' => 'error',
                'message' => $result->get_error_message()
            ), 30);
        } else {
            set_transient('vefify_notice', array(
                'type' => 'success',
                'message' => $message
            ), 30);
            
            wp_redirect(admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $campaign_id));
            exit;
        }
    }
    
    /**
     * Delete campaign
     */
    private function delete_campaign() {
        $campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$campaign_id || !wp_verify_nonce($_GET['_wpnonce'], 'delete_campaign')) {
            wp_die('Invalid request');
        }
        
        $result = $this->model->delete_campaign_lite($campaign_id);
        
        if ($result) {
            set_transient('vefify_notice', array(
                'type' => 'success',
                'message' => 'Campaign deleted successfully'
            ), 30);
        } else {
            set_transient('vefify_notice', array(
                'type' => 'error',
                'message' => 'Failed to delete campaign'
            ), 30);
        }
        
        wp_redirect(admin_url('admin.php?page=vefify-campaigns'));
        exit;
    }
    
    /**
     * Show admin notices
     */
    private function show_notices() {
        $notice = get_transient('vefify_notice');
        if ($notice) {
            delete_transient('vefify_notice');
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }
}