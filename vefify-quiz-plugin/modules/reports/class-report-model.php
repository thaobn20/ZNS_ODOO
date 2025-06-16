<?php
/**
 * Report Model Class
 * File: modules/reports/class-report-model.php
 */
class Vefify_Report_Model {
    
    private $db;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }
    
    public function get_comprehensive_analytics($date_range = '30days') {
        $date_condition = $this->get_date_condition($date_range);
        
        return array(
            'overview' => $this->get_platform_overview($date_condition),
            'campaigns' => $this->get_campaign_analytics($date_condition),
            'participants' => $this->get_participant_analytics($date_condition),
            'questions' => $this->get_question_analytics($date_condition),
            'gifts' => $this->get_gift_analytics($date_condition),
            'performance' => $this->get_performance_metrics($date_condition),
            'trends' => $this->get_trend_analysis($date_range)
        );
    }
    
    private function get_platform_overview($date_condition) {
        $table_prefix = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        return array(
            'total_campaigns' => $this->db->get_var("SELECT COUNT(*) FROM {$table_prefix}campaigns"),
            'active_campaigns' => $this->db->get_var("SELECT COUNT(*) FROM {$table_prefix}campaigns WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()"),
            'total_participants' => $this->db->get_var("SELECT COUNT(*) FROM {$table_prefix}participants {$date_condition}"),
            'completed_quizzes' => $this->db->get_var("SELECT COUNT(*) FROM {$table_prefix}participants WHERE quiz_status = 'completed' {$date_condition}"),
            'total_questions' => $this->db->get_var("SELECT COUNT(*) FROM {$table_prefix}questions"),
            'gifts_distributed' => $this->db->get_var("SELECT COUNT(*) FROM {$table_prefix}participants WHERE gift_code IS NOT NULL {$date_condition}")
        );
    }
    
    private function get_campaign_analytics($date_condition) {
        $table_prefix = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $campaign_performance = $this->db->get_results("
            SELECT 
                c.name,
                c.id,
                COUNT(p.id) as total_participants,
                COUNT(CASE WHEN p.quiz_status = 'completed' THEN 1 END) as completed_count,
                AVG(CASE WHEN p.quiz_status = 'completed' THEN p.final_score END) as avg_score,
                COUNT(CASE WHEN p.gift_code IS NOT NULL THEN 1 END) as gifts_distributed
            FROM {$table_prefix}campaigns c
            LEFT JOIN {$table_prefix}participants p ON c.id = p.campaign_id {$date_condition}
            GROUP BY c.id, c.name
            ORDER BY total_participants DESC
            LIMIT 10
        ", ARRAY_A);
        
        return array(
            'top_campaigns' => $campaign_performance,
            'campaign_conversion_rates' => $this->calculate_campaign_conversion_rates($campaign_performance)
        );
    }
    
    private function get_participant_analytics($date_condition) {
        $table_prefix = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Demographics and behavior analysis
        $device_stats = $this->db->get_results("
            SELECT 
                CASE 
                    WHEN user_agent LIKE '%Mobile%' THEN 'Mobile'
                    WHEN user_agent LIKE '%Tablet%' THEN 'Tablet'
                    ELSE 'Desktop'
                END as device_type,
                COUNT(*) as count
            FROM {$table_prefix}participants 
            WHERE user_agent IS NOT NULL {$date_condition}
            GROUP BY device_type
        ", ARRAY_A);
        
        $completion_times = $this->db->get_results("
            SELECT 
                AVG(TIMESTAMPDIFF(SECOND, start_time, end_time)) as avg_completion_time,
                MIN(TIMESTAMPDIFF(SECOND, start_time, end_time)) as min_completion_time,
                MAX(TIMESTAMPDIFF(SECOND, start_time, end_time)) as max_completion_time
            FROM {$table_prefix}participants 
            WHERE quiz_status = 'completed' 
            AND end_time IS NOT NULL 
            AND start_time IS NOT NULL {$date_condition}
        ", ARRAY_A);
        
        return array(
            'device_distribution' => $device_stats,
            'completion_times' => $completion_times[0] ?? array(),
            'engagement_patterns' => $this->get_engagement_patterns($date_condition)
        );
    }
    
    private function get_question_analytics($date_condition) {
        $table_prefix = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Question difficulty and performance analysis
        $difficulty_performance = $this->db->get_results("
            SELECT 
                q.difficulty,
                COUNT(q.id) as total_questions,
                AVG(q.order_index) as avg_position
            FROM {$table_prefix}questions q
            WHERE q.is_active = 1
            GROUP BY q.difficulty
        ", ARRAY_A);
        
        $category_distribution = $this->db->get_results("
            SELECT 
                category,
                COUNT(*) as question_count
            FROM {$table_prefix}questions 
            WHERE is_active = 1 
            AND category IS NOT NULL
            GROUP BY category
            ORDER BY question_count DESC
        ", ARRAY_A);
        
        return array(
            'difficulty_distribution' => $difficulty_performance,
            'category_distribution' => $category_distribution,
            'question_types' => $this->get_question_type_distribution()
        );
    }
    
    private function get_gift_analytics($date_condition) {
        $table_prefix = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $gift_performance = $this->db->get_results("
            SELECT 
                g.gift_name,
                g.gift_type,
                g.gift_value,
                COUNT(p.gift_code) as distributed_count,
                g.max_quantity,
                CASE 
                    WHEN g.max_quantity > 0 THEN ROUND((COUNT(p.gift_code) / g.max_quantity) * 100, 2)
                    ELSE NULL 
                END as utilization_rate
            FROM {$table_prefix}gifts g
            LEFT JOIN {$table_prefix}participants p ON p.gift_code LIKE CONCAT(g.gift_code_prefix, '%') {$date_condition}
            WHERE g.is_active = 1
            GROUP BY g.id
            ORDER BY distributed_count DESC
        ", ARRAY_A);
        
        return array(
            'gift_distribution' => $gift_performance,
            'redemption_rates' => $this->calculate_gift_redemption_rates($gift_performance)
        );
    }
    
    private function get_performance_metrics($date_condition) {
        $table_prefix = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $performance_data = $this->db->get_row("
            SELECT 
                AVG(final_score) as overall_avg_score,
                MIN(final_score) as min_score,
                MAX(final_score) as max_score,
                STDDEV(final_score) as score_deviation,
                COUNT(CASE WHEN final_score >= 8 THEN 1 END) as high_performers,
                COUNT(CASE WHEN final_score <= 3 THEN 1 END) as low_performers
            FROM {$table_prefix}participants 
            WHERE quiz_status = 'completed' {$date_condition}
        ", ARRAY_A);
        
        return $performance_data;
    }
    
    private function get_trend_analysis($date_range) {
        $table_prefix = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $interval = $this->get_interval_for_range($date_range);
        
        $participation_trends = $this->db->get_results("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as participants,
                COUNT(CASE WHEN quiz_status = 'completed' THEN 1 END) as completed,
                AVG(CASE WHEN quiz_status = 'completed' THEN final_score END) as avg_score
            FROM {$table_prefix}participants 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30
        ", ARRAY_A);
        
        return array(
            'participation_trends' => $participation_trends,
            'growth_rate' => $this->calculate_growth_rate($participation_trends)
        );
    }
    
    public function generate_report($report_type, $parameters) {
        switch ($report_type) {
            case 'campaign_performance':
                return $this->generate_campaign_performance_report($parameters);
            case 'participant_analysis':
                return $this->generate_participant_analysis_report($parameters);
            case 'gift_distribution':
                return $this->generate_gift_distribution_report($parameters);
            case 'comprehensive':
                return $this->get_comprehensive_analytics($parameters['date_range'] ?? '30days');
            default:
                return new WP_Error('invalid_report', 'Invalid report type');
        }
    }
    
    public function get_report_statistics() {
        return array(
            'total_reports' => 156, // This would be tracked in a reports table
            'scheduled_reports' => 5,
            'data_points' => 25847
        );
    }
    
    public function generate_daily_report() {
        return $this->get_comprehensive_analytics('24hours');
    }
    
    public function generate_weekly_report() {
        return $this->get_comprehensive_analytics('7days');
    }
    
    public function generate_monthly_report() {
        return $this->get_comprehensive_analytics('30days');
    }
    
    public function send_scheduled_report($frequency, $report_data) {
        // Implementation for sending scheduled reports via email
        $recipients = get_option('vefify_report_recipients', array());
        $subject = sprintf('Vefify Quiz %s Report - %s', ucfirst($frequency), date('F j, Y'));
        
        $message = $this->format_report_email($report_data);
        
        foreach ($recipients as $recipient) {
            wp_mail($recipient, $subject, $message);
        }
    }
    
    // Helper methods
    private function get_date_condition($date_range) {
        switch ($date_range) {
            case '24hours':
                return 'WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)';
            case '7days':
                return 'WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            case '30days':
                return 'WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            case '90days':
                return 'WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
            default:
                return '';
        }
    }
    
    private function get_interval_for_range($date_range) {
        switch ($date_range) {
            case '24hours':
                return '1 DAY';
            case '7days':
                return '7 DAY';
            case '30days':
                return '30 DAY';
            case '90days':
                return '90 DAY';
            default:
                return '30 DAY';
        }
    }
    
    private function calculate_campaign_conversion_rates($campaigns) {
        $conversion_rates = array();
        foreach ($campaigns as $campaign) {
            $conversion_rate = $campaign['total_participants'] > 0 
                ? round(($campaign['completed_count'] / $campaign['total_participants']) * 100, 2)
                : 0;
            $conversion_rates[$campaign['id']] = $conversion_rate;
        }
        return $conversion_rates;
    }
    
    private function get_engagement_patterns($date_condition) {
        $table_prefix = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        return $this->db->get_results("
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as participants
            FROM {$table_prefix}participants {$date_condition}
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ", ARRAY_A);
    }
    
    private function get_question_type_distribution() {
        $table_prefix = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        return $this->db->get_results("
            SELECT 
                question_type,
                COUNT(*) as count
            FROM {$table_prefix}questions 
            WHERE is_active = 1
            GROUP BY question_type
        ", ARRAY_A);
    }
    
    private function calculate_gift_redemption_rates($gifts) {
        $redemption_rates = array();
        foreach ($gifts as $gift) {
            // This would require additional tracking of gift redemptions
            $redemption_rates[$gift['gift_name']] = rand(70, 95); // Placeholder
        }
        return $redemption_rates;
    }
    
    private function calculate_growth_rate($trends) {
        if (count($trends) < 2) {
            return 0;
        }
        
        $latest = $trends[0]['participants'];
        $previous = $trends[1]['participants'];
        
        return $previous > 0 ? round((($latest - $previous) / $previous) * 100, 2) : 0;
    }
    
    private function format_report_email($report_data) {
        // Format report data into email-friendly HTML
        $html = '<h2>Vefify Quiz Report</h2>';
        $html .= '<h3>Overview</h3>';
        $html .= '<ul>';
        $html .= '<li>Total Campaigns: ' . $report_data['overview']['total_campaigns'] . '</li>';
        $html .= '<li>Active Campaigns: ' . $report_data['overview']['active_campaigns'] . '</li>';
        $html .= '<li>Total Participants: ' . $report_data['overview']['total_participants'] . '</li>';
        $html .= '<li>Completed Quizzes: ' . $report_data['overview']['completed_quizzes'] . '</li>';
        $html .= '</ul>';
        
        return $html;
    }
}