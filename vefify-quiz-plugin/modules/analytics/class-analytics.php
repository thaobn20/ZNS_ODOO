<?php
/**
 * Analytics Module
 * File: modules/analytics/class-analytics.php
 */

namespace VefifyQuiz;

class Analytics {
    private $db;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }
    
    /**
     * Get campaign analytics dashboard data
     */
    public function get_campaign_analytics($campaign_id, $date_from = null, $date_to = null) {
        $date_where = '';
        $params = [$campaign_id];
        
        if ($date_from && $date_to) {
            $date_where = 'AND created_at BETWEEN %s AND %s';
            $params[] = $date_from;
            $params[] = $date_to;
        }
        
        $users_table = $this->db->prefix . 'vefify_quiz_users';
        $analytics_table = $this->db->prefix . 'vefify_analytics';
        
        // Basic participation stats
        $participation_stats = $this->db->get_row($this->db->prepare("
            SELECT 
                COUNT(*) as total_participants,
                COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed_count,
                COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as gift_recipients,
                AVG(CASE WHEN score IS NOT NULL THEN score END) as avg_score,
                AVG(CASE WHEN completion_time IS NOT NULL THEN completion_time END) as avg_completion_time
            FROM {$users_table}
            WHERE campaign_id = %d {$date_where}
        ", $params), ARRAY_A);
        
        // Score distribution
        $score_distribution = $this->db->get_results($this->db->prepare("
            SELECT score, COUNT(*) as count
            FROM {$users_table}
            WHERE campaign_id = %d AND score IS NOT NULL {$date_where}
            GROUP BY score
            ORDER BY score
        ", $params), ARRAY_A);
        
        // Daily participation trend
        $daily_trend = $this->db->get_results($this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as participants,
                COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed
            FROM {$users_table}
            WHERE campaign_id = %d {$date_where}
            GROUP BY DATE(created_at)
            ORDER BY date
        ", $params), ARRAY_A);
        
        // Province distribution
        $province_stats = $this->db->get_results($this->db->prepare("
            SELECT 
                province,
                COUNT(*) as count,
                AVG(score) as avg_score
            FROM {$users_table}
            WHERE campaign_id = %d AND province IS NOT NULL {$date_where}
            GROUP BY province
            ORDER BY count DESC
            LIMIT 10
        ", $params), ARRAY_A);
        
        // Gift distribution
        $gift_stats = $this->db->get_results($this->db->prepare("
            SELECT 
                g.gift_name,
                g.gift_type,
                g.gift_value,
                COUNT(u.id) as recipients
            FROM {$users_table} u
            JOIN {$this->db->prefix}vefify_gifts g ON u.gift_id = g.id
            WHERE u.campaign_id = %d AND u.gift_id IS NOT NULL {$date_where}
            GROUP BY g.id
            ORDER BY recipients DESC
        ", $params), ARRAY_A);
        
        // Question performance
        $question_performance = $this->get_question_performance($campaign_id, $date_from, $date_to);
        
        return [
            'participation' => $participation_stats,
            'score_distribution' => $score_distribution,
            'daily_trend' => $daily_trend,
            'province_stats' => $province_stats,
            'gift_stats' => $gift_stats,
            'question_performance' => $question_performance,
            'completion_rate' => $participation_stats['total_participants'] > 0 
                ? round(($participation_stats['completed_count'] / $participation_stats['total_participants']) * 100, 2)
                : 0
        ];
    }
    
    /**
     * Get question performance analytics
     */
    private function get_question_performance($campaign_id, $date_from = null, $date_to = null) {
        $sessions_table = $this->db->prefix . 'vefify_quiz_sessions';
        $questions_table = $this->db->prefix . 'vefify_questions';
        $users_table = $this->db->prefix . 'vefify_quiz_users';
        
        $date_where = '';
        $params = [$campaign_id];
        
        if ($date_from && $date_to) {
            $date_where = 'AND s.created_at BETWEEN %s AND %s';
            $params[] = $date_from;
            $params[] = $date_to;
        }
        
        // Get all completed sessions for this campaign
        $sessions = $this->db->get_results($this->db->prepare("
            SELECT s.questions_data, s.answers_data, s.user_id
            FROM {$sessions_table} s
            JOIN {$users_table} u ON s.user_id = u.id
            WHERE s.campaign_id = %d 
            AND s.is_completed = 1 
            AND u.completed_at IS NOT NULL
            {$date_where}
        ", $params), ARRAY_A);
        
        $question_stats = [];
        
        foreach ($sessions as $session) {
            $questions = json_decode($session['questions_data'], true);
            $answers = json_decode($session['answers_data'], true);
            
            if (!$questions || !$answers) continue;
            
            foreach ($questions as $question_id) {
                if (!isset($question_stats[$question_id])) {
                    $question_stats[$question_id] = [
                        'total_attempts' => 0,
                        'correct_answers' => 0,
                        'question_text' => ''
                    ];
                }
                
                $question_stats[$question_id]['total_attempts']++;
                
                // Check if answer was correct
                if (isset($answers[$question_id])) {
                    $is_correct = $this->check_answer_correctness($question_id, $answers[$question_id]);
                    if ($is_correct) {
                        $question_stats[$question_id]['correct_answers']++;
                    }
                }
            }
        }
        
        // Get question texts and calculate percentages
        foreach ($question_stats as $question_id => &$stats) {
            $question = $this->db->get_row($this->db->prepare(
                "SELECT question_text FROM {$questions_table} WHERE id = %d",
                $question_id
            ));
            
            $stats['question_text'] = $question ? $question->question_text : 'Unknown Question';
            $stats['correct_percentage'] = $stats['total_attempts'] > 0 
                ? round(($stats['correct_answers'] / $stats['total_attempts']) * 100, 2)
                : 0;
        }
        
        // Sort by correct percentage (ascending - most difficult first)
        uasort($question_stats, function($a, $b) {
            return $a['correct_percentage'] <=> $b['correct_percentage'];
        });
        
        return $question_stats;
    }
    
    /**
     * Check if an answer is correct
     */
    private function check_answer_correctness($question_id, $user_answer) {
        $options_table = $this->db->prefix . 'vefify_question_options';
        
        $correct_options = $this->db->get_col($this->db->prepare(
            "SELECT id FROM {$options_table} WHERE question_id = %d AND is_correct = 1",
            $question_id
        ));
        
        if (!is_array($user_answer)) {
            $user_answer = [$user_answer];
        }
        
        $user_answer = array_map('intval', $user_answer);
        
        return (
            count($correct_options) === count($user_answer) &&
            empty(array_diff($correct_options, $user_answer))
        );
    }
    
    /**
     * Export analytics data to CSV
     */
    public function export_campaign_data($campaign_id, $format = 'csv') {
        $users_table = $this->db->prefix . 'vefify_quiz_users';
        $campaigns_table = $this->db->prefix . 'vefify_campaigns';
        $gifts_table = $this->db->prefix . 'vefify_gifts';
        
        $data = $this->db->get_results($this->db->prepare("
            SELECT 
                c.name as campaign_name,
                u.full_name,
                u.phone_number,
                u.province,
                u.pharmacy_code,
                u.score,
                u.total_questions,
                u.completion_time,
                u.created_at as started_at,
                u.completed_at,
                g.gift_name,
                g.gift_type,
                g.gift_value,
                u.gift_code,
                u.gift_status
            FROM {$users_table} u
            JOIN {$campaigns_table} c ON u.campaign_id = c.id
            LEFT JOIN {$gifts_table} g ON u.gift_id = g.id
            WHERE u.campaign_id = %d
            ORDER BY u.created_at DESC
        ", $campaign_id), ARRAY_A);
        
        if ($format === 'csv') {
            return $this->generate_csv($data);
        }
        
        return $data;
    }
    
    /**
     * Generate CSV content
     */
    private function generate_csv($data) {
        if (empty($data)) {
            return "No data available\n";
        }
        
        $output = '';
        
        // Headers
        $headers = array_keys($data[0]);
        $output .= implode(',', array_map([$this, 'csv_escape'], $headers)) . "\n";
        
        // Data rows
        foreach ($data as $row) {
            $output .= implode(',', array_map([$this, 'csv_escape'], $row)) . "\n";
        }
        
        return $output;
    }
    
    /**
     * Escape CSV values
     */
    private function csv_escape($value) {
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
    
    /**
     * Get real-time dashboard stats
     */
    public function get_realtime_stats($campaign_id) {
        $users_table = $this->db->prefix . 'vefify_quiz_users';
        
        return $this->db->get_row($this->db->prepare("
            SELECT 
                COUNT(*) as total_today,
                COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed_today,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as last_hour,
                COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as gifts_today
            FROM {$users_table}
            WHERE campaign_id = %d 
            AND DATE(created_at) = CURDATE()
        ", $campaign_id), ARRAY_A);
    }
}