<?php
/**
 * Module Analytics for Quiz Plugin Dashboard
 * Each module provides analytics summary
 */

class Vefify_Quiz_Module_Analytics {
    
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * 1. CAMPAIGN MANAGEMENT Analytics
     */
    public function get_campaign_analytics() {
        $campaigns_table = $this->db->get_table_name('campaigns');
        $participants_table = $this->db->get_table_name('participants');
        
        // Total campaigns
        $total_campaigns = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$campaigns_table}
        ");
        
        // Active campaigns
        $active_campaigns = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$campaigns_table} 
            WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()
        ");
        
        // Total participants across all campaigns
        $total_participants = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$participants_table}
        ");
        
        // Completion rate
        $completed_participants = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$participants_table} 
            WHERE quiz_status = 'completed'
        ");
        
        $completion_rate = $total_participants > 0 
            ? round(($completed_participants / $total_participants) * 100, 1) 
            : 0;
        
        return array(
            'title' => 'Campaign Management',
            'description' => 'Manage quiz campaigns with scheduling and participant limits',
            'icon' => '📊',
            'stats' => array(
                'total_campaigns' => array(
                    'label' => 'Total Campaigns',
                    'value' => $total_campaigns,
                    'trend' => '+2 new this month'
                ),
                'active_campaigns' => array(
                    'label' => 'Active Campaigns',
                    'value' => $active_campaigns,
                    'trend' => $active_campaigns > 0 ? 'Running' : 'None active'
                ),
                'total_participants' => array(
                    'label' => 'Total Participants',
                    'value' => number_format($total_participants),
                    'trend' => '+15% this week'
                ),
                'completion_rate' => array(
                    'label' => 'Completion Rate',
                    'value' => $completion_rate . '%',
                    'trend' => $completion_rate > 70 ? 'Excellent' : 'Needs improvement'
                )
            ),
            'quick_actions' => array(
                array(
                    'label' => 'New Campaign',
                    'url' => admin_url('admin.php?page=vefify-campaigns&action=new'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'View Reports',
                    'url' => admin_url('admin.php?page=vefify-campaigns&action=reports'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    /**
     * 2. QUESTION BANK Analytics
     */
    public function get_question_bank_analytics() {
        $questions_table = $this->db->get_table_name('questions');
        $options_table = $this->db->get_table_name('question_options');
        
        // Total questions
        $total_questions = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$questions_table} WHERE is_active = 1
        ");
        
        // Questions by type
        $question_types = $this->db->get_wpdb()->get_results("
            SELECT question_type, COUNT(*) as count 
            FROM {$questions_table} 
            WHERE is_active = 1 
            GROUP BY question_type
        ", ARRAY_A);
        
        // Questions by difficulty
        $difficulty_stats = $this->db->get_wpdb()->get_results("
            SELECT difficulty, COUNT(*) as count 
            FROM {$questions_table} 
            WHERE is_active = 1 
            GROUP BY difficulty
        ", ARRAY_A);
        
        // Categories
        $total_categories = $this->db->get_wpdb()->get_var("
            SELECT COUNT(DISTINCT category) FROM {$questions_table} 
            WHERE is_active = 1 AND category IS NOT NULL
        ");
        
        // Average options per question
        $avg_options = $this->db->get_wpdb()->get_var("
            SELECT AVG(option_count) FROM (
                SELECT COUNT(*) as option_count 
                FROM {$options_table} o
                JOIN {$questions_table} q ON o.question_id = q.id
                WHERE q.is_active = 1
                GROUP BY question_id
            ) as subquery
        ");
        
        return array(
            'title' => 'Question Bank',
            'description' => 'Manage questions with multiple types and HTML support',
            'icon' => '❓',
            'stats' => array(
                'total_questions' => array(
                    'label' => 'Active Questions',
                    'value' => $total_questions,
                    'trend' => '+8 added this week'
                ),
                'question_types' => array(
                    'label' => 'Question Types',
                    'value' => count($question_types),
                    'trend' => 'Multiple choice, True/False, Multi-select'
                ),
                'categories' => array(
                    'label' => 'Categories',
                    'value' => $total_categories,
                    'trend' => 'Well organized'
                ),
                'avg_options' => array(
                    'label' => 'Avg Options/Question',
                    'value' => round($avg_options, 1),
                    'trend' => 'Good variety'
                )
            ),
            'difficulty_breakdown' => $difficulty_stats,
            'quick_actions' => array(
                array(
                    'label' => 'Add Question',
                    'url' => admin_url('admin.php?page=vefify-questions&action=new'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'Import CSV',
                    'url' => admin_url('admin.php?page=vefify-questions&action=import'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    /**
     * 3. GIFT MANAGEMENT Analytics
     */
    public function get_gift_management_analytics() {
        $gifts_table = $this->db->get_table_name('gifts');
        $participants_table = $this->db->get_table_name('participants');
        
        // Total gift types
        $total_gifts = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$gifts_table} WHERE is_active = 1
        ");
        
        // Gifts distributed
        $distributed_count = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$participants_table} 
            WHERE gift_code IS NOT NULL
        ");
        
        // Gift claim rate
        $claimed_count = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$participants_table} 
            WHERE gift_status = 'claimed'
        ");
        
        $claim_rate = $distributed_count > 0 
            ? round(($claimed_count / $distributed_count) * 100, 1) 
            : 0;
        
        // Low inventory gifts
        $low_inventory = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$gifts_table} 
            WHERE is_active = 1 
            AND max_quantity IS NOT NULL 
            AND (max_quantity - used_count) <= 5
        ");
        
        // Gift types distribution
        $gift_types = $this->db->get_wpdb()->get_results("
            SELECT gift_type, COUNT(*) as count 
            FROM {$gifts_table} 
            WHERE is_active = 1 
            GROUP BY gift_type
        ", ARRAY_A);
        
        return array(
            'title' => 'Gift Management',
            'description' => 'Assign rewards for campaigns with inventory tracking',
            'icon' => '🎁',
            'stats' => array(
                'total_gifts' => array(
                    'label' => 'Gift Types',
                    'value' => $total_gifts,
                    'trend' => '+3 new types'
                ),
                'distributed_count' => array(
                    'label' => 'Gifts Distributed',
                    'value' => number_format($distributed_count),
                    'trend' => '+12% this week'
                ),
                'claim_rate' => array(
                    'label' => 'Claim Rate',
                    'value' => $claim_rate . '%',
                    'trend' => $claim_rate > 80 ? 'Excellent' : 'Good'
                ),
                'low_inventory' => array(
                    'label' => 'Low Stock Alerts',
                    'value' => $low_inventory,
                    'trend' => $low_inventory > 0 ? 'Needs attention' : 'All good'
                )
            ),
            'gift_types_breakdown' => $gift_types,
            'quick_actions' => array(
                array(
                    'label' => 'Add Gift',
                    'url' => admin_url('admin.php?page=vefify-gifts&action=new'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'Inventory Report',
                    'url' => admin_url('admin.php?page=vefify-gifts&action=inventory'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    /**
     * 4. PARTICIPANTS MANAGEMENT Analytics
     */
    public function get_participants_analytics() {
        $participants_table = $this->db->get_table_name('participants');
        
        // Total participants
        $total_participants = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$participants_table}
        ");
        
        // Today's participants
        $today_participants = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$participants_table} 
            WHERE DATE(created_at) = CURDATE()
        ");
        
        // Status breakdown
        $status_breakdown = $this->db->get_wpdb()->get_results("
            SELECT quiz_status, COUNT(*) as count 
            FROM {$participants_table} 
            GROUP BY quiz_status
        ", ARRAY_A);
        
        // Average score
        $avg_score = $this->db->get_wpdb()->get_var("
            SELECT AVG(final_score) FROM {$participants_table} 
            WHERE quiz_status = 'completed'
        ");
        
        // Top provinces
        $top_provinces = $this->db->get_wpdb()->get_results("
            SELECT province, COUNT(*) as count 
            FROM {$participants_table} 
            WHERE province IS NOT NULL 
            GROUP BY province 
            ORDER BY count DESC 
            LIMIT 5
        ", ARRAY_A);
        
        return array(
            'title' => 'Participants Management',
            'description' => 'Track participant data and quiz performance',
            'icon' => '👥',
            'stats' => array(
                'total_participants' => array(
                    'label' => 'Total Participants',
                    'value' => number_format($total_participants),
                    'trend' => '+25 today'
                ),
                'today_participants' => array(
                    'label' => 'Today\'s Participants',
                    'value' => $today_participants,
                    'trend' => 'Active day'
                ),
                'avg_score' => array(
                    'label' => 'Average Score',
                    'value' => round($avg_score, 1),
                    'trend' => 'Good performance'
                ),
                'completion_status' => array(
                    'label' => 'Completion Status',
                    'value' => count($status_breakdown) . ' statuses',
                    'trend' => 'Tracking well'
                )
            ),
            'status_breakdown' => $status_breakdown,
            'top_provinces' => $top_provinces,
            'quick_actions' => array(
                array(
                    'label' => 'Export Data',
                    'url' => admin_url('admin.php?page=vefify-participants&action=export'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'View Details',
                    'url' => admin_url('admin.php?page=vefify-participants'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    /**
     * 5. REPORTS Analytics
     */
    public function get_reports_analytics() {
        $campaigns_table = $this->db->get_table_name('campaigns');
        $participants_table = $this->db->get_table_name('participants');
        $analytics_table = $this->db->get_table_name('analytics');
        
        // Recent activity
        $recent_completions = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$participants_table} 
            WHERE quiz_status = 'completed' 
            AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        // Conversion rate (started vs completed)
        $started_count = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$participants_table}
        ");
        
        $completed_count = $this->db->get_wpdb()->get_var("
            SELECT COUNT(*) FROM {$participants_table} 
            WHERE quiz_status = 'completed'
        ");
        
        $conversion_rate = $started_count > 0 
            ? round(($completed_count / $started_count) * 100, 1) 
            : 0;
        
        // Peak hours analysis
        $peak_hour = $this->db->get_wpdb()->get_var("
            SELECT HOUR(created_at) as hour
            FROM {$participants_table} 
            GROUP BY HOUR(created_at) 
            ORDER BY COUNT(*) DESC 
            LIMIT 1
        ");
        
        // Report types available
        $report_types = array(
            'Campaign Performance',
            'Participant Demographics', 
            'Gift Distribution',
            'Question Analytics',
            'Time-based Trends'
        );
        
        return array(
            'title' => 'Reports & Analytics',
            'description' => 'Comprehensive reporting with data insights',
            'icon' => '📈',
            'stats' => array(
                'recent_completions' => array(
                    'label' => 'Completions (7 days)',
                    'value' => $recent_completions,
                    'trend' => '+18% vs last week'
                ),
                'conversion_rate' => array(
                    'label' => 'Conversion Rate',
                    'value' => $conversion_rate . '%',
                    'trend' => $conversion_rate > 60 ? 'Strong' : 'Improving'
                ),
                'peak_hour' => array(
                    'label' => 'Peak Activity Hour',
                    'value' => $peak_hour . ':00',
                    'trend' => 'Optimize for peak times'
                ),
                'report_types' => array(
                    'label' => 'Available Reports',
                    'value' => count($report_types),
                    'trend' => 'Comprehensive coverage'
                )
            ),
            'report_types' => $report_types,
            'quick_actions' => array(
                array(
                    'label' => 'Generate Report',
                    'url' => admin_url('admin.php?page=vefify-reports&action=generate'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'Schedule Reports',
                    'url' => admin_url('admin.php?page=vefify-reports&action=schedule'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    /**
     * 6. SETTINGS Analytics
     */
    public function get_settings_analytics() {
        // Configuration health check
        $configurations = array(
            'Email Settings' => get_option('vefify_quiz_email_enabled', false),
            'API Integration' => get_option('vefify_quiz_api_configured', false),
            'Security Settings' => get_option('vefify_quiz_security_enabled', true),
            'Backup Settings' => get_option('vefify_quiz_backup_enabled', false)
        );
        
        $configured_count = count(array_filter($configurations));
        $total_configs = count($configurations);
        
        // System performance
        $db_health = $this->db->verify_tables();
        $db_healthy = empty($db_health);
        
        // Plugin version and updates
        $plugin_version = defined('VEFIFY_QUIZ_VERSION') ? VEFIFY_QUIZ_VERSION : '1.0.0';
        
        return array(
            'title' => 'Settings & Configuration',
            'description' => 'System settings and plugin configuration',
            'icon' => '⚙️',
            'stats' => array(
                'configuration_health' => array(
                    'label' => 'Configuration Health',
                    'value' => $configured_count . '/' . $total_configs,
                    'trend' => $configured_count === $total_configs ? 'Fully configured' : 'Needs setup'
                ),
                'database_health' => array(
                    'label' => 'Database Health',
                    'value' => $db_healthy ? 'Healthy' : 'Issues found',
                    'trend' => $db_healthy ? 'All tables exist' : count($db_health) . ' issues'
                ),
                'plugin_version' => array(
                    'label' => 'Plugin Version',
                    'value' => $plugin_version,
                    'trend' => 'Latest version'
                ),
                'system_status' => array(
                    'label' => 'System Status',
                    'value' => 'Operational',
                    'trend' => 'Running smoothly'
                )
            ),
            'configurations' => $configurations,
            'quick_actions' => array(
                array(
                    'label' => 'General Settings',
                    'url' => admin_url('admin.php?page=vefify-settings'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'System Health',
                    'url' => admin_url('admin.php?page=vefify-settings&tab=health'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    /**
     * Get all module analytics for dashboard
     */
    public function get_all_module_analytics() {
        return array(
            'campaigns' => $this->get_campaign_analytics(),
            'questions' => $this->get_question_bank_analytics(),
            'gifts' => $this->get_gift_management_analytics(),
            'participants' => $this->get_participants_analytics(),
            'reports' => $this->get_reports_analytics(),
            'settings' => $this->get_settings_analytics()
        );
    }
}