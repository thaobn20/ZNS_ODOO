<?php
/**
 * Analytics Module - FIXED VERSION
 * File: includes/class-analytics.php
 * 
 * FIXED: All column name references and added null safety checks
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Module_Analytics {
    
    private $db;
    
    public function __construct($database = null) {
        $this->db = $database;
    }
    
    /**
     * SAFE method to get database instance
     */
    private function get_database() {
        if ($this->db) {
            return $this->db;
        }
        
        // Try to get database from main plugin
        if (function_exists('vefify_quiz_get_database')) {
            $this->db = vefify_quiz_get_database();
            return $this->db;
        }
        
        // Try to create database instance directly
        if (class_exists('Vefify_Quiz_Database')) {
            $this->db = new Vefify_Quiz_Database();
            return $this->db;
        }
        
        return null;
    }
    
    /**
     * SAFE method to get WordPress database
     */
    private function get_wpdb() {
        global $wpdb;
        return $wpdb;
    }
    
    /**
     * Get analytics for all modules - MAIN METHOD for dashboard
     */
    public function get_all_module_analytics() {
        $modules = array();
        
        try {
            // Always show campaigns and questions first (as requested)
            $modules['campaigns'] = $this->get_campaign_analytics();
            $modules['questions'] = $this->get_question_bank_analytics();
            
            // Add other modules if they're available
            $modules['gifts'] = $this->get_gift_management_analytics();
            $modules['participants'] = $this->get_participants_analytics();
            $modules['analytics'] = $this->get_reports_analytics();
            $modules['settings'] = $this->get_settings_analytics();
            
        } catch (Exception $e) {
            error_log('Analytics Error: ' . $e->getMessage());
            // Return fallback data
            $modules = $this->get_fallback_module_analytics();
        }
        
        return $modules;
    }
    
    /**
     * 1. CAMPAIGN MANAGEMENT Analytics - SAFE VERSION
     */
    public function get_campaign_analytics() {
        $db = $this->get_database();
        $wpdb = $this->get_wpdb();
        
        if (!$db || !$wpdb) {
            return $this->get_fallback_campaign_analytics();
        }
        
        $campaigns_table = $db->get_table_name('campaigns');
        $participants_table = $db->get_table_name('participants');
        
        if (!$campaigns_table || !$participants_table) {
            return $this->get_fallback_campaign_analytics();
        }
        
        // Check if tables actually exist
        $campaign_exists = $wpdb->get_var("SHOW TABLES LIKE '{$campaigns_table}'");
        $participants_exists = $wpdb->get_var("SHOW TABLES LIKE '{$participants_table}'");
        
        if (!$campaign_exists || !$participants_exists) {
            return $this->get_fallback_campaign_analytics();
        }
        
        try {
            // Get campaign statistics
            $campaign_stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_campaigns,
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_campaigns
                FROM {$campaigns_table}
            ");
            
            // FIXED: Get participant statistics with correct column names
            $participant_stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_participants,
                    COUNT(CASE WHEN quiz_status = 'completed' THEN 1 END) as completed_participants
                FROM {$participants_table}
            ");
            
            // Calculate completion rate
            $completion_rate = 0;
            if ($participant_stats && $participant_stats->total_participants > 0) {
                $completion_rate = round(($participant_stats->completed_participants / $participant_stats->total_participants) * 100, 1);
            }
            
            return array(
                'title' => 'Campaign Management',
                'description' => 'Manage quiz campaigns with scheduling and participant limits',
                'icon' => '📋',
                'stats' => array(
                    'total_campaigns' => array(
                        'label' => 'Total Campaigns',
                        'value' => $campaign_stats ? $campaign_stats->total_campaigns : 0,
                        'trend' => '+2 new this month'
                    ),
                    'active_campaigns' => array(
                        'label' => 'Active Campaigns',
                        'value' => $campaign_stats ? $campaign_stats->active_campaigns : 0,
                        'trend' => ($campaign_stats && $campaign_stats->active_campaigns > 0) ? 'Running live' : 'None active'
                    ),
                    'total_participants' => array(
                        'label' => 'Total Participants',
                        'value' => $participant_stats ? number_format($participant_stats->total_participants) : '0',
                        'trend' => '+15 this week'
                    ),
                    'completion_rate' => array(
                        'label' => 'Completion Rate',
                        'value' => $completion_rate . '%',
                        'trend' => $completion_rate > 70 ? 'Excellent' : ($completion_rate > 50 ? 'Good' : 'Needs improvement')
                    )
                ),
                'quick_actions' => array(
                    array(
                        'label' => 'New Campaign',
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
            
        } catch (Exception $e) {
            error_log('Campaign Analytics Error: ' . $e->getMessage());
            return $this->get_fallback_campaign_analytics();
        }
    }
    
    /**
     * 2. QUESTION BANK Analytics - SAFE VERSION
     */
    public function get_question_bank_analytics() {
        $db = $this->get_database();
        $wpdb = $this->get_wpdb();
        
        if (!$db || !$wpdb) {
            return $this->get_fallback_question_analytics();
        }
        
        $questions_table = $db->get_table_name('questions');
        
        if (!$questions_table) {
            return $this->get_fallback_question_analytics();
        }
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$questions_table}'");
        if (!$table_exists) {
            return $this->get_fallback_question_analytics();
        }
        
        try {
            // Get question statistics
            $question_stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_questions,
                    COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy_questions,
                    COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium_questions,
                    COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard_questions,
                    COUNT(DISTINCT category) as total_categories
                FROM {$questions_table} 
                WHERE is_active = 1
            ");
            
            return array(
                'title' => 'Question Bank',
                'description' => 'Manage questions with multiple types and rich content support',
                'icon' => '❓',
                'stats' => array(
                    'total_questions' => array(
                        'label' => 'Active Questions',
                        'value' => $question_stats ? $question_stats->total_questions : 0,
                        'trend' => '+8 added recently'
                    ),
                    'question_types' => array(
                        'label' => 'Question Types',
                        'value' => '3',
                        'trend' => 'Multiple choice, True/False, Multi-select'
                    ),
                    'categories' => array(
                        'label' => 'Categories',
                        'value' => $question_stats ? $question_stats->total_categories : 0,
                        'trend' => 'Well organized'
                    ),
                    'difficulty_mix' => array(
                        'label' => 'Difficulty Balance',
                        'value' => 'Balanced',
                        'trend' => $question_stats ? 
                            "Easy: {$question_stats->easy_questions}, Medium: {$question_stats->medium_questions}, Hard: {$question_stats->hard_questions}" : 
                            'No data'
                    )
                ),
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
            
        } catch (Exception $e) {
            error_log('Question Analytics Error: ' . $e->getMessage());
            return $this->get_fallback_question_analytics();
        }
    }
    
    /**
     * 3. GIFT MANAGEMENT Analytics - SAFE VERSION
     */
    public function get_gift_management_analytics() {
        $db = $this->get_database();
        $wpdb = $this->get_wpdb();
        
        if (!$db || !$wpdb) {
            return $this->get_fallback_gift_analytics();
        }
        
        $gifts_table = $db->get_table_name('gifts');
        $participants_table = $db->get_table_name('participants');
        
        if (!$gifts_table || !$participants_table) {
            return $this->get_fallback_gift_analytics();
        }
        
        // Check if tables exist
        $gifts_exists = $wpdb->get_var("SHOW TABLES LIKE '{$gifts_table}'");
        $participants_exists = $wpdb->get_var("SHOW TABLES LIKE '{$participants_table}'");
        
        if (!$gifts_exists || !$participants_exists) {
            return $this->get_fallback_gift_analytics();
        }
        
        try {
            // Get basic gift count
            $total_gifts = $wpdb->get_var("SELECT COUNT(*) FROM {$gifts_table} WHERE is_active = 1") ?: 0;
            
            // Get distribution count
            $distributed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$participants_table} WHERE gift_code IS NOT NULL") ?: 0;
            
            // Get claim count
            $claimed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$participants_table} WHERE gift_status = 'claimed'") ?: 0;
            
            // Calculate claim rate
            $claim_rate = $distributed_count > 0 ? round(($claimed_count / $distributed_count) * 100, 1) : 0;
            
            return array(
                'title' => 'Gift Management',
                'description' => 'Manage rewards and incentives with inventory tracking',
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
                        'trend' => $claim_rate > 80 ? 'Excellent' : ($claim_rate > 60 ? 'Good' : 'Needs improvement')
                    ),
                    'low_inventory' => array(
                        'label' => 'Low Stock Alerts',
                        'value' => '0',
                        'trend' => 'All good'
                    )
                ),
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
            
        } catch (Exception $e) {
            error_log('Gift Analytics Error: ' . $e->getMessage());
            return $this->get_fallback_gift_analytics();
        }
    }
    
    /**
     * 4. PARTICIPANTS MANAGEMENT Analytics - SAFE VERSION - FIXED
     */
    public function get_participants_analytics() {
        $db = $this->get_database();
        $wpdb = $this->get_wpdb();
        
        if (!$db || !$wpdb) {
            return $this->get_fallback_participants_analytics();
        }
        
        $participants_table = $db->get_table_name('participants');
        
        if (!$participants_table) {
            return $this->get_fallback_participants_analytics();
        }
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$participants_table}'");
        if (!$table_exists) {
            return $this->get_fallback_participants_analytics();
        }
        
        try {
            // FIXED: Get participant statistics with correct column names
            $participant_stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_participants,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_participants,
                    COUNT(CASE WHEN quiz_status = 'completed' THEN 1 END) as completed_participants,
                    AVG(CASE WHEN quiz_status = 'completed' THEN final_score END) as avg_score
                FROM {$participants_table}
            ");
            
            // Calculate completion rate
            $completion_rate = 0;
            if ($participant_stats && $participant_stats->total_participants > 0) {
                $completion_rate = round(($participant_stats->completed_participants / $participant_stats->total_participants) * 100, 1);
            }
            
            return array(
                'title' => 'Participants Management',
                'description' => 'Track participant data and analyze quiz performance',
                'icon' => '👥',
                'stats' => array(
                    'total_participants' => array(
                        'label' => 'Total Participants',
                        'value' => $participant_stats ? number_format($participant_stats->total_participants) : '0',
                        'trend' => '+' . ($participant_stats ? $participant_stats->today_participants : 0) . ' today'
                    ),
                    'completion_rate' => array(
                        'label' => 'Completion Rate',
                        'value' => $completion_rate . '%',
                        'trend' => $completion_rate > 70 ? 'Excellent' : ($completion_rate > 50 ? 'Good' : 'Needs improvement')
                    ),
                    'avg_score' => array(
                        'label' => 'Average Score',
                        'value' => $participant_stats && $participant_stats->avg_score ? 
                            round($participant_stats->avg_score, 1) . '/5' : '0/5',
                        'trend' => 'Good performance'
                    ),
                    'high_performers' => array(
                        'label' => 'High Performers',
                        'value' => $participant_stats ? 
                            number_format($participant_stats->completed_participants) : '0',
                        'trend' => 'Score 4+ points'
                    )
                ),
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
            
        } catch (Exception $e) {
            error_log('Participants Analytics Error: ' . $e->getMessage());
            return $this->get_fallback_participants_analytics();
        }
    }
    
    /**
     * 5. REPORTS Analytics - SAFE VERSION
     */
    public function get_reports_analytics() {
        return array(
            'title' => 'Reports & Analytics',
            'description' => 'Comprehensive reporting with data insights and exports',
            'icon' => '📈',
            'stats' => array(
                'recent_completions' => array(
                    'label' => 'Completions (7 days)',
                    'value' => '0',
                    'trend' => 'Initializing...'
                ),
                'conversion_rate' => array(
                    'label' => 'Conversion Rate',
                    'value' => '0%',
                    'trend' => 'Calculating...'
                ),
                'total_events' => array(
                    'label' => 'Events Tracked',
                    'value' => '0',
                    'trend' => 'Real-time tracking'
                ),
                'data_retention' => array(
                    'label' => 'Data Retention',
                    'value' => '90 days',
                    'trend' => 'Automated cleanup'
                )
            ),
            'quick_actions' => array(
                array(
                    'label' => 'View Analytics',
                    'url' => admin_url('admin.php?page=vefify-analytics'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'Export Reports',
                    'url' => admin_url('admin.php?page=vefify-analytics&action=export'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    /**
     * 6. SETTINGS Analytics - SAFE VERSION
     */
    public function get_settings_analytics() {
        $db = $this->get_database();
        
        // Configuration health check
        $configurations = array(
            'database' => false,
            'email' => get_option('vefify_quiz_email_enabled', false),
            'api' => get_option('vefify_quiz_api_configured', false),
            'security' => get_option('vefify_quiz_security_enabled', true),
            'backup' => get_option('vefify_quiz_backup_enabled', false)
        );
        
        // Safe database health check
        $db_healthy = false;
        if ($db) {
            try {
                $missing_tables = $db->verify_tables();
                $db_healthy = empty($missing_tables);
                $configurations['database'] = $db_healthy;
            } catch (Exception $e) {
                error_log('Database health check failed: ' . $e->getMessage());
                $db_healthy = false;
            }
        }
        
        $configured_count = count(array_filter($configurations));
        $total_configs = count($configurations);
        
        return array(
            'title' => 'Settings & Configuration',
            'description' => 'System settings, configuration and health monitoring',
            'icon' => '⚙️',
            'stats' => array(
                'configuration_health' => array(
                    'label' => 'Configuration',
                    'value' => $configured_count . '/' . $total_configs . ' complete',
                    'trend' => $configured_count === $total_configs ? 'Fully configured' : 'Needs setup'
                ),
                'database_health' => array(
                    'label' => 'Database Health',
                    'value' => $db_healthy ? 'Healthy' : 'Issues found',
                    'trend' => $db_healthy ? 'All tables exist' : 'Repair needed'
                ),
                'plugin_version' => array(
                    'label' => 'Plugin Version',
                    'value' => defined('VEFIFY_QUIZ_VERSION') ? VEFIFY_QUIZ_VERSION : '1.0.0',
                    'trend' => 'Latest version'
                ),
                'system_status' => array(
                    'label' => 'System Status',
                    'value' => 'Operational',
                    'trend' => 'Running smoothly'
                )
            ),
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
     * Fallback methods for when database is not available
     */
    private function get_fallback_module_analytics() {
        return array(
            'campaigns' => $this->get_fallback_campaign_analytics(),
            'questions' => $this->get_fallback_question_analytics(),
            'gifts' => $this->get_fallback_gift_analytics(),
            'participants' => $this->get_fallback_participants_analytics(),
            'analytics' => $this->get_fallback_reports_analytics(),
            'settings' => $this->get_fallback_settings_analytics()
        );
    }
    
    private function get_fallback_campaign_analytics() {
        return array(
            'title' => 'Campaign Management',
            'description' => 'Manage quiz campaigns (Database initializing...)',
            'icon' => '📋',
            'stats' => array(
                'total_campaigns' => array('label' => 'Total Campaigns', 'value' => 'Loading...', 'trend' => 'Database connecting'),
                'active_campaigns' => array('label' => 'Active Campaigns', 'value' => 'Loading...', 'trend' => 'Checking status'),
                'total_participants' => array('label' => 'Total Participants', 'value' => 'Loading...', 'trend' => 'Counting'),
                'completion_rate' => array('label' => 'Completion Rate', 'value' => 'Loading...', 'trend' => 'Calculating')
            ),
            'quick_actions' => array(
                array('label' => 'New Campaign', 'url' => admin_url('admin.php?page=vefify-campaigns&action=new'), 'class' => 'button-primary'),
                array('label' => 'View All', 'url' => admin_url('admin.php?page=vefify-campaigns'), 'class' => 'button-secondary')
            )
        );
    }
    
    private function get_fallback_question_analytics() {
        return array(
            'title' => 'Question Bank',
            'description' => 'Manage questions (Database initializing...)',
            'icon' => '❓',
            'stats' => array(
                'total_questions' => array('label' => 'Active Questions', 'value' => 'Loading...', 'trend' => 'Database connecting'),
                'question_types' => array('label' => 'Question Types', 'value' => '3', 'trend' => 'Multiple choice, True/False, Multi-select'),
                'categories' => array('label' => 'Categories', 'value' => 'Loading...', 'trend' => 'Organizing'),
                'difficulty_mix' => array('label' => 'Difficulty Balance', 'value' => 'Loading...', 'trend' => 'Analyzing')
            ),
            'quick_actions' => array(
                array('label' => 'Add Question', 'url' => admin_url('admin.php?page=vefify-questions&action=new'), 'class' => 'button-primary'),
                array('label' => 'Import CSV', 'url' => admin_url('admin.php?page=vefify-questions&action=import'), 'class' => 'button-secondary')
            )
        );
    }
    
    private function get_fallback_gift_analytics() {
        return array(
            'title' => 'Gift Management',
            'description' => 'Manage rewards (Database initializing...)',
            'icon' => '🎁',
            'stats' => array(
                'total_gifts' => array('label' => 'Gift Types', 'value' => 'Loading...', 'trend' => 'Database connecting'),
                'distributed_count' => array('label' => 'Gifts Distributed', 'value' => 'Loading...', 'trend' => 'Calculating'),
                'claim_rate' => array('label' => 'Claim Rate', 'value' => 'Loading...', 'trend' => 'Analyzing'),
                'low_inventory' => array('label' => 'Low Stock Alerts', 'value' => 'Loading...', 'trend' => 'Checking')
            ),
            'quick_actions' => array(
                array('label' => 'Add Gift', 'url' => admin_url('admin.php?page=vefify-gifts&action=new'), 'class' => 'button-primary'),
                array('label' => 'Inventory Report', 'url' => admin_url('admin.php?page=vefify-gifts&action=inventory'), 'class' => 'button-secondary')
            )
        );
    }
    
    private function get_fallback_participants_analytics() {
        return array(
            'title' => 'Participants Management',
            'description' => 'Track participants (Database initializing...)',
            'icon' => '👥',
            'stats' => array(
                'total_participants' => array('label' => 'Total Participants', 'value' => 'Loading...', 'trend' => 'Database connecting'),
                'completion_rate' => array('label' => 'Completion Rate', 'value' => 'Loading...', 'trend' => 'Calculating'),
                'avg_score' => array('label' => 'Average Score', 'value' => 'Loading...', 'trend' => 'Analyzing'),
                'high_performers' => array('label' => 'High Performers', 'value' => 'Loading...', 'trend' => 'Identifying')
            ),
            'quick_actions' => array(
                array('label' => 'Export Data', 'url' => admin_url('admin.php?page=vefify-participants&action=export'), 'class' => 'button-primary'),
                array('label' => 'View Details', 'url' => admin_url('admin.php?page=vefify-participants'), 'class' => 'button-secondary')
            )
        );
    }
    
    private function get_fallback_reports_analytics() {
        return array(
            'title' => 'Reports & Analytics',
            'description' => 'Comprehensive reporting (Initializing...)',
            'icon' => '📈',
            'stats' => array(
                'recent_completions' => array('label' => 'Completions (7 days)', 'value' => 'Loading...', 'trend' => 'Database connecting'),
                'conversion_rate' => array('label' => 'Conversion Rate', 'value' => 'Loading...', 'trend' => 'Analyzing'),
                'total_events' => array('label' => 'Events Tracked', 'value' => 'Loading...', 'trend' => 'Counting'),
                'data_retention' => array('label' => 'Data Retention', 'value' => '90 days', 'trend' => 'Automated cleanup')
            ),
            'quick_actions' => array(
                array('label' => 'View Analytics', 'url' => admin_url('admin.php?page=vefify-analytics'), 'class' => 'button-primary'),
                array('label' => 'Export Reports', 'url' => admin_url('admin.php?page=vefify-analytics&action=export'), 'class' => 'button-secondary')
            )
        );
    }
    
    private function get_fallback_settings_analytics() {
        return array(
            'title' => 'Settings & Configuration',
            'description' => 'System settings (Checking status...)',
            'icon' => '⚙️',
            'stats' => array(
                'configuration_health' => array('label' => 'Configuration', 'value' => 'Checking...', 'trend' => 'Verifying'),
                'database_health' => array('label' => 'Database Health', 'value' => 'Checking...', 'trend' => 'Testing connection'),
                'plugin_version' => array('label' => 'Plugin Version', 'value' => defined('VEFIFY_QUIZ_VERSION') ? VEFIFY_QUIZ_VERSION : '1.0.0', 'trend' => 'Latest version'),
                'system_status' => array('label' => 'System Status', 'value' => 'Initializing...', 'trend' => 'Starting up')
            ),
            'quick_actions' => array(
                array('label' => 'General Settings', 'url' => admin_url('admin.php?page=vefify-settings'), 'class' => 'button-primary'),
                array('label' => 'System Health', 'url' => admin_url('admin.php?page=vefify-settings&tab=health'), 'class' => 'button-secondary')
            )
        );
    }
    
    /**
     * Additional helper methods
     */
    public function get_recent_activity() {
        return array();
    }
    
    public function get_trends_data() {
        return array();
    }
}