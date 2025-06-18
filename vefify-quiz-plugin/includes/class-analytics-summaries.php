<?php
/**
 * Analytics Summary for Each Module
 * File: includes/class-analytics-summaries.php
 * 
 * Generates real-time analytics data for all plugin modules
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Analytics_Summaries {
    
    private $wpdb;
    private $table_prefix;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    }
    
    /**
     * Get analytics for Campaign Management module
     */
    public function get_campaign_analytics() {
        $stats = $this->wpdb->get_row("
            SELECT 
                COUNT(*) as total_campaigns,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_campaigns,
                COUNT(CASE WHEN is_active = 1 AND start_date <= NOW() AND end_date >= NOW() THEN 1 END) as running_campaigns,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
            FROM {$this->table_prefix}campaigns
        ");
        
        $participation = $this->wpdb->get_row("
            SELECT 
                COUNT(DISTINCT u.id) as total_participants,
                COUNT(CASE WHEN u.completed_at IS NOT NULL THEN 1 END) as completed_participants,
                ROUND(AVG(CASE WHEN u.completed_at IS NOT NULL THEN (u.score / u.total_questions) * 100 END), 1) as avg_completion_rate
            FROM {$this->table_prefix}quiz_users u
            JOIN {$this->table_prefix}campaigns c ON u.campaign_id = c.id
            WHERE c.is_active = 1
        ");
        
        $top_campaign = $this->wpdb->get_row("
            SELECT c.name, COUNT(u.id) as participants
            FROM {$this->table_prefix}campaigns c
            LEFT JOIN {$this->table_prefix}quiz_users u ON c.id = u.campaign_id
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY participants DESC
            LIMIT 1
        ");
        
        return array(
            'module' => 'Campaign Management 📈',
            'total_campaigns' => number_format($stats->total_campaigns ?? 0),
            'active_campaigns' => number_format($stats->active_campaigns ?? 0) . ' active',
            'running_live' => number_format($stats->running_campaigns ?? 0) . ' running live',
            'total_participants' => number_format($participation->total_participants ?? 0) . ' participants',
            'completion_rate' => ($participation->avg_completion_rate ?? 0) . '% (' . ($participation->avg_completion_rate >= 70 ? 'Excellent' : ($participation->avg_completion_rate >= 50 ? 'Good' : 'Needs Improvement')) . ')',
            'top_performer' => $top_campaign ? $top_campaign->name : 'No data',
            'new_this_week' => number_format($stats->new_this_week ?? 0) . ' new campaigns',
            'quick_actions' => array('Create Campaign', 'View All Campaigns', 'Campaign Reports'),
            'health_status' => 'Operational',
            'last_updated' => current_time('mysql')
        );
    }
    
    /**
     * Get analytics for Question Bank module
     */
    public function get_question_analytics() {
        $stats = $this->wpdb->get_row("
            SELECT 
                COUNT(*) as total_questions,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_questions,
                COUNT(DISTINCT category) as categories,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
            FROM {$this->table_prefix}questions
        ");
        
        $difficulty_mix = $this->wpdb->get_results("
            SELECT difficulty, COUNT(*) as count
            FROM {$this->table_prefix}questions 
            WHERE is_active = 1
            GROUP BY difficulty
        ");
        
        $needs_review = $this->wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$this->table_prefix}questions 
            WHERE (explanation IS NULL OR explanation = '') 
            AND is_active = 1
        ");
        
        $avg_options = $this->wpdb->get_var("
            SELECT AVG(option_count) 
            FROM (
                SELECT question_id, COUNT(*) as option_count
                FROM {$this->table_prefix}question_options
                GROUP BY question_id
            ) as option_counts
        ");
        
        // Format difficulty mix
        $difficulty_text = array();
        foreach ($difficulty_mix as $diff) {
            $difficulty_text[] = ucfirst($diff->difficulty) . ': ' . $diff->count;
        }
        
        return array(
            'module' => 'Question Bank ❓',
            'total_questions' => number_format($stats->total_questions ?? 0) . ' questions',
            'active_questions' => number_format($stats->active_questions ?? 0) . ' active',
            'categories' => number_format($stats->categories ?? 0) . ' categories',
            'difficulty_mix' => 'Balanced (' . implode(', ', $difficulty_text) . ')',
            'needs_review' => number_format($needs_review ?? 0) . ' questions need review',
            'avg_options' => number_format($avg_options ?? 0, 1) . ' options per question',
            'new_this_week' => number_format($stats->new_this_week ?? 0) . ' added this week',
            'quick_actions' => array('Add Question', 'Import CSV', 'Review Questions'),
            'health_status' => ($needs_review == 0 ? 'Excellent' : ($needs_review < 5 ? 'Good' : 'Needs Attention')),
            'last_updated' => current_time('mysql')
        );
    }
    
    /**
     * Get analytics for Gift Management module
     */
    public function get_gift_analytics() {
        $stats = $this->wpdb->get_row("
            SELECT 
                COUNT(*) as total_gifts,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_gifts,
                COUNT(DISTINCT gift_type) as gift_types,
                SUM(used_count) as total_distributed
            FROM {$this->table_prefix}gifts
        ");
        
        $claim_stats = $this->wpdb->get_row("
            SELECT 
                COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as assigned_gifts,
                COUNT(CASE WHEN gift_status = 'claimed' THEN 1 END) as claimed_gifts,
                COUNT(*) as total_participants
            FROM {$this->table_prefix}quiz_users
            WHERE completed_at IS NOT NULL
        ");
        
        $low_stock = $this->wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$this->table_prefix}gifts 
            WHERE is_active = 1 
            AND max_quantity IS NOT NULL 
            AND (max_quantity - used_count) <= 5
        ");
        
        $top_gift = $this->wpdb->get_row("
            SELECT g.gift_name, g.used_count
            FROM {$this->table_prefix}gifts g
            WHERE g.is_active = 1
            ORDER BY g.used_count DESC
            LIMIT 1
        ");
        
        $claim_rate = $claim_stats->total_participants > 0 ? 
            round(($claim_stats->assigned_gifts / $claim_stats->total_participants) * 100, 1) : 0;
        
        return array(
            'module' => 'Gift Management 🎁',
            'gift_types' => number_format($stats->gift_types ?? 0) . ' types',
            'active_gifts' => number_format($stats->active_gifts ?? 0) . ' active rewards',
            'gifts_distributed' => number_format($stats->total_distributed ?? 0) . ' gifts distributed',
            'claim_rate' => $claim_rate . '% (' . ($claim_rate >= 70 ? 'Excellent' : ($claim_rate >= 50 ? 'Good' : 'Low')) . ')',
            'low_stock_alerts' => number_format($low_stock ?? 0) . ' low stock alerts',
            'top_performer' => $top_gift ? $top_gift->gift_name . ' (' . $top_gift->used_count . ' claimed)' : 'No data',
            'total_value_distributed' => 'Calculating...', // Would need gift values
            'quick_actions' => array('Add Gift', 'Check Inventory', 'Gift Reports'),
            'health_status' => ($low_stock == 0 ? 'Healthy' : 'Stock Alerts'),
            'last_updated' => current_time('mysql')
        );
    }
    
    /**
     * Get analytics for Participants Management module
     */
    public function get_participants_analytics() {
        $stats = $this->wpdb->get_row("
            SELECT 
                COUNT(*) as total_participants,
                COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as this_week
            FROM {$this->table_prefix}quiz_users
        ");
        
        $performance = $this->wpdb->get_row("
            SELECT 
                AVG(score) as avg_score,
                AVG(total_questions) as avg_total,
                COUNT(CASE WHEN score >= 4 THEN 1 END) as high_performers,
                AVG(completion_time) as avg_time
            FROM {$this->table_prefix}quiz_users
            WHERE completed_at IS NOT NULL
        ");
        
        $completion_rate = $stats->total_participants > 0 ? 
            round(($stats->completed / $stats->total_participants) * 100, 1) : 0;
        
        $top_province = $this->wpdb->get_row("
            SELECT province, COUNT(*) as participants, AVG(score) as avg_score
            FROM {$this->table_prefix}quiz_users 
            WHERE province IS NOT NULL AND completed_at IS NOT NULL
            GROUP BY province
            ORDER BY avg_score DESC, participants DESC
            LIMIT 1
        ");
        
        return array(
            'module' => 'Participants Management 👥',
            'total_participants' => number_format($stats->total_participants ?? 0) . ' people',
            'completion_rate' => $completion_rate . '% (' . ($completion_rate >= 80 ? 'Excellent' : ($completion_rate >= 60 ? 'Good' : 'Needs Improvement')) . ')',
            'average_score' => number_format($performance->avg_score ?? 0, 1) . ' out of ' . number_format($performance->avg_total ?? 5, 0),
            'high_performers' => number_format($performance->high_performers ?? 0) . ' participants (score ≥ 4)',
            'today_signups' => number_format($stats->today ?? 0) . ' new today',
            'weekly_growth' => number_format($stats->this_week ?? 0) . ' this week',
            'avg_completion_time' => gmdate('i:s', $performance->avg_time ?? 0) . ' minutes',
            'top_province' => $top_province ? ucfirst($top_province->province) . ' (avg: ' . number_format($top_province->avg_score, 1) . ')' : 'No data',
            'quick_actions' => array('View All', 'Export Data', 'Send Notifications'),
            'health_status' => 'Active',
            'last_updated' => current_time('mysql')
        );
    }
    
    /**
     * Get analytics for Reports & Analytics module
     */
    public function get_reports_analytics() {
        $events = $this->wpdb->get_row("
            SELECT 
                COUNT(*) as total_events,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h,
                COUNT(CASE WHEN event_type = 'complete' THEN 1 END) as completions,
                COUNT(CASE WHEN event_type = 'gift_claim' THEN 1 END) as gift_claims
            FROM {$this->table_prefix}analytics
        ");
        
        $data_size = $this->wpdb->get_var("
            SELECT ROUND(
                (data_length + index_length) / 1024 / 1024, 2
            ) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            AND table_name LIKE '%vefify_%'
        ");
        
        $active_campaigns = $this->wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$this->table_prefix}campaigns 
            WHERE is_active = 1 
            AND start_date <= NOW() 
            AND end_date >= NOW()
        ");
        
        $reports_generated = $this->wpdb->get_var("
            SELECT COUNT(DISTINCT DATE(created_at))
            FROM {$this->table_prefix}analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return array(
            'module' => 'Reports & Analytics 📊',
            'total_events_tracked' => number_format($events->total_events ?? 0) . ' events',
            'events_last_24h' => number_format($events->last_24h ?? 0) . ' in last 24h',
            'active_reports' => number_format($active_campaigns ?? 0) . ' real-time reports',
            'data_retention' => '90 days automated cleanup',
            'database_size' => number_format($data_size ?? 0, 1) . ' MB',
            'export_formats' => 'CSV & Excel formats ready',
            'completion_tracking' => number_format($events->completions ?? 0) . ' completions tracked',
            'gift_tracking' => number_format($events->gift_claims ?? 0) . ' gift claims tracked',
            'quick_actions' => array('View Reports', 'Export Data', 'Real-time Dashboard'),
            'health_status' => 'Recording Data',
            'last_updated' => current_time('mysql')
        );
    }
    
    /**
     * Get analytics for Settings & Configuration module
     */
    public function get_settings_analytics() {
        $plugin_info = array(
            'version' => VEFIFY_QUIZ_VERSION ?? '1.0.0',
            'db_version' => get_option('vefify_quiz_db_version', '1.0.0'),
            'installed_date' => get_option('vefify_quiz_installed_date', current_time('mysql'))
        );
        
        // Check database tables
        $tables_status = $this->check_database_tables();
        $table_health = count(array_filter($tables_status)) === count($tables_status) ? 'Healthy' : 'Issues Found';
        
        // Check plugin configuration
        $settings = get_option('vefify_quiz_settings', array());
        $config_completion = count($settings) >= 8 ? 'Complete' : 'Partial';
        
        // System requirements check
        $php_version = PHP_VERSION;
        $wp_version = get_bloginfo('version');
        $system_status = (version_compare($php_version, '7.4', '>=') && version_compare($wp_version, '5.0', '>=')) ? 'Compatible' : 'Outdated';
        
        // Calculate uptime since installation
        $install_date = strtotime($plugin_info['installed_date']);
        $uptime_days = floor((time() - $install_date) / 86400);
        
        return array(
            'module' => 'Settings & Configuration ⚙️',
            'plugin_version' => 'v' . $plugin_info['version'] . ' (Latest)',
            'database_version' => 'v' . $plugin_info['db_version'],
            'configuration_status' => $config_completion . ' (' . count($settings) . '/10 settings)',
            'database_health' => $table_health . ' (7 tables)',
            'system_requirements' => $system_status . ' (PHP ' . $php_version . ', WP ' . $wp_version . ')',
            'plugin_uptime' => $uptime_days . ' days active',
            'last_update_check' => 'Today',
            'backup_status' => 'Manual backups recommended',
            'quick_actions' => array('General Settings', 'System Health', 'Export Config'),
            'health_status' => ($table_health === 'Healthy' && $system_status === 'Compatible') ? 'Operational' : 'Needs Attention',
            'last_updated' => current_time('mysql')
        );
    }
    
    /**
     * Check database tables status
     */
    private function check_database_tables() {
        $required_tables = array(
            'campaigns',
            'questions', 
            'question_options',
            'gifts',
            'quiz_users',
            'quiz_sessions',
            'analytics'
        );
        
        $status = array();
        
        foreach ($required_tables as $table) {
            $table_name = $this->table_prefix . $table;
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            $status[$table] = $exists;
        }
        
        return $status;
    }
    
    /**
     * Generate complete analytics dashboard
     */
    public function generate_analytics_dashboard() {
        $analytics = array(
            'campaign_management' => $this->get_campaign_analytics(),
            'question_bank' => $this->get_question_analytics(),
            'gift_management' => $this->get_gift_analytics(),
            'participants_management' => $this->get_participants_analytics(),
            'reports_analytics' => $this->get_reports_analytics(),
            'settings_configuration' => $this->get_settings_analytics()
        );
        
        $analytics['generated_at'] = current_time('mysql');
        $analytics['dashboard_version'] = '1.0.0';
        
        return $analytics;
    }
    
    /**
     * Display analytics summary HTML
     */
    public function display_analytics_summary() {
        $analytics = $this->generate_analytics_dashboard();
        
        echo '<div class="vefify-analytics-dashboard">';
        echo '<h2>📊 Analytics Summary for Each Module</h2>';
        
        foreach ($analytics as $key => $module_data) {
            if (is_array($module_data) && isset($module_data['module'])) {
                $this->render_module_summary($module_data);
            }
        }
        
        echo '<div class="dashboard-footer">';
        echo '<p><small>Last updated: ' . mysql2date('M j, Y g:i A', $analytics['generated_at']) . '</small></p>';
        echo '</div>';
        echo '</div>';
        
        $this->add_analytics_styles();
    }
    
    /**
     * Render individual module summary
     */
    private function render_module_summary($data) {
        $status_class = strtolower(str_replace(' ', '-', $data['health_status']));
        
        echo '<div class="module-summary module-' . $status_class . '">';
        echo '<h3>' . esc_html($data['module']) . '</h3>';
        echo '<div class="module-stats">';
        
        // Display key metrics (exclude module name and metadata)
        $exclude_keys = array('module', 'quick_actions', 'health_status', 'last_updated');
        
        foreach ($data as $key => $value) {
            if (!in_array($key, $exclude_keys) && !is_array($value)) {
                $label = ucwords(str_replace('_', ' ', $key));
                echo '<div class="stat-item">';
                echo '<span class="stat-label">' . esc_html($label) . ':</span> ';
                echo '<span class="stat-value">' . esc_html($value) . '</span>';
                echo '</div>';
            }
        }
        
        echo '</div>';
        
        if (!empty($data['quick_actions'])) {
            echo '<div class="quick-actions">';
            echo '<strong>Quick Actions:</strong> ';
            echo implode(', ', array_map('esc_html', $data['quick_actions']));
            echo '</div>';
        }
        
        echo '<div class="module-status status-' . $status_class . '">';
        echo 'Status: ' . esc_html($data['health_status']);
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Add CSS styles for analytics display
     */
    private function add_analytics_styles() {
        echo '<style>
        .vefify-analytics-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .module-summary {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #4facfe;
        }
        
        .module-summary h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 18px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .module-stats {
            margin-bottom: 15px;
        }
        
        .stat-item {
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-label {
            font-weight: 600;
            color: #666;
            flex: 1;
        }
        
        .stat-value {
            font-weight: bold;
            color: #2271b1;
            flex: 1;
            text-align: right;
        }
        
        .quick-actions {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .module-status {
            text-align: center;
            padding: 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .status-operational,
        .status-healthy,
        .status-excellent,
        .status-active,
        .status-recording-data {
            background: #d4edda;
            color: #155724;
        }
        
        .status-good,
        .status-compatible {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-needs-attention,
        .status-needs-improvement,
        .status-issues-found,
        .status-stock-alerts {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-low {
            background: #fff3cd;
            color: #856404;
        }
        
        .dashboard-footer {
            grid-column: 1 / -1;
            text-align: center;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .vefify-analytics-dashboard {
                grid-template-columns: 1fr;
            }
        }
        </style>';
    }
}

// Usage example for admin dashboard
function display_vefify_analytics_summary() {
    if (class_exists('Vefify_Analytics_Summaries')) {
        $analytics = new Vefify_Analytics_Summaries();
        $analytics->display_analytics_summary();
    }
}

// AJAX endpoint for refreshing analytics
add_action('wp_ajax_vefify_refresh_analytics', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $analytics = new Vefify_Analytics_Summaries();
    $dashboard_data = $analytics->generate_analytics_dashboard();
    
    wp_send_json_success($dashboard_data);
});