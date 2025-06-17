<?php
/**
 * Participant Model Class
 * File: modules/participants/class-participant-model.php
 */
class Vefify_Participant_Model {
    
    private $db;
    private $table_participants;
    private $table_campaigns;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_participants = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'participants';
        $this->table_campaigns = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'campaigns';
    }
    
    public function get_participants($args = array()) {
        $defaults = array(
            'campaign_id' => null,
            'status' => 'all',
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
            'date_from' => null,
            'date_to' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $params = array();
        
        if ($args['campaign_id']) {
            $where_conditions[] = 'p.campaign_id = %d';
            $params[] = $args['campaign_id'];
        }
        
        if ($args['status'] !== 'all') {
            $where_conditions[] = 'p.quiz_status = %s';
            $params[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = '(p.participant_name LIKE %s OR p.participant_email LIKE %s)';
            $search_term = '%' . $this->db->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        if ($args['date_from']) {
            $where_conditions[] = 'p.created_at >= %s';
            $params[] = $args['date_from'];
        }
        
        if ($args['date_to']) {
            $where_conditions[] = 'p.created_at <= %s';
            $params[] = $args['date_to'];
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $order_clause = sprintf(
            'ORDER BY p.%s %s',
            sanitize_sql_orderby($args['orderby']),
            strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC'
        );
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['per_page'], $offset);
        
        $query = "SELECT p.*, c.name as campaign_name 
                  FROM {$this->table_participants} p 
                  LEFT JOIN {$this->table_campaigns} c ON p.campaign_id = c.id 
                  {$where_clause} {$order_clause} {$limit_clause}";
        
        if (!empty($params)) {
            $participants = $this->db->get_results($this->db->prepare($query, $params), ARRAY_A);
        } else {
            $participants = $this->db->get_results($query, ARRAY_A);
        }
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$this->table_participants} p {$where_clause}";
        if (!empty($params)) {
            $total_items = $this->db->get_var($this->db->prepare($count_query, $params));
        } else {
            $total_items = $this->db->get_var($count_query);
        }
        
        return array(
            'participants' => $participants,
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / $args['per_page']),
            'current_page' => $args['page']
        );
    }
    
    public function get_participant($participant_id) {
        return $this->db->get_row($this->db->prepare(
            "SELECT p.*, c.name as campaign_name 
             FROM {$this->table_participants} p 
             LEFT JOIN {$this->table_campaigns} c ON p.campaign_id = c.id 
             WHERE p.id = %d",
            $participant_id
        ), ARRAY_A);
    }
    
    public function start_quiz_session($campaign_id, $participant_data) {
        // Check if campaign is active
        $campaign = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->table_campaigns} 
             WHERE id = %d AND is_active = 1 AND start_date <= NOW() AND end_date >= NOW()",
            $campaign_id
        ), ARRAY_A);
        
        if (!$campaign) {
            return new WP_Error('invalid_campaign', 'Campaign is not active or not found');
        }
        
        // Check participant limit
        if ($campaign['max_participants'] > 0) {
            $current_participants = $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table_participants} WHERE campaign_id = %d",
                $campaign_id
            ));
            
            if ($current_participants >= $campaign['max_participants']) {
                return new WP_Error('participant_limit', 'Campaign participant limit reached');
            }
        }
        
        // Check for existing participant with same email
        if (!empty($participant_data['participant_email'])) {
            $existing = $this->db->get_var($this->db->prepare(
                "SELECT id FROM {$this->table_participants} 
                 WHERE campaign_id = %d AND participant_email = %s",
                $campaign_id, $participant_data['participant_email']
            ));
            
            if ($existing) {
                return new WP_Error('duplicate_participant', 'You have already participated in this campaign');
            }
        }
        
        // Create participant record
        $participant_data['campaign_id'] = $campaign_id;
        $participant_data['quiz_status'] = 'started';
        $participant_data['start_time'] = current_time('mysql');
        
        $result = $this->db->insert($this->table_participants, $participant_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create participant record');
        }
        
        return $this->db->insert_id;
    }
    
    public function submit_quiz_answers($participant_id, $answers) {
        $participant = $this->get_participant($participant_id);
        if (!$participant) {
            return new WP_Error('participant_not_found', 'Participant not found');
        }
        
        if ($participant['quiz_status'] === 'completed') {
            return new WP_Error('already_completed', 'Quiz already completed');
        }
        
        // Calculate score
        $score_result = $this->calculate_quiz_score($participant['campaign_id'], $answers);
        
        // Update participant record
        $update_data = array(
            'quiz_status' => 'completed',
            'end_time' => current_time('mysql'),
            'final_score' => $score_result['score'],
            'answers_data' => json_encode($answers)
        );
        
        $result = $this->db->update(
            $this->table_participants,
            $update_data,
            array('id' => $participant_id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update participant record');
        }
        
        return array(
            'final_score' => $score_result['score'],
            'max_score' => $score_result['max_score'],
            'percentage' => $score_result['percentage'],
            'passed' => $score_result['passed'],
            'answers_breakdown' => $score_result['breakdown']
        );
    }
    
    public function calculate_quiz_score($campaign_id, $answers) {
        $questions_table = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'questions';
        $options_table = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'question_options';
        
        $score = 0;
        $max_score = 0;
        $breakdown = array();
        
        foreach ($answers as $question_id => $answer_ids) {
            $question = $this->db->get_row($this->db->prepare(
                "SELECT * FROM {$questions_table} WHERE id = %d AND campaign_id = %d",
                $question_id, $campaign_id
            ), ARRAY_A);
            
            if (!$question) {
                continue;
            }
            
            $max_score++;
            
            // Get correct answers for this question
            $correct_options = $this->db->get_results($this->db->prepare(
                "SELECT id FROM {$options_table} WHERE question_id = %d AND is_correct = 1",
                $question_id
            ), ARRAY_A);
            
            $correct_option_ids = array_column($correct_options, 'id');
            
            // Check if answer is correct
            $is_correct = false;
            if (is_array($answer_ids)) {
                // Multiple choice question
                sort($answer_ids);
                sort($correct_option_ids);
                $is_correct = ($answer_ids === $correct_option_ids);
            } else {
                // Single choice question
                $is_correct = in_array($answer_ids, $correct_option_ids);
            }
            
            if ($is_correct) {
                $score++;
            }
            
            $breakdown[$question_id] = array(
                'question_text' => $question['question_text'],
                'user_answer' => $answer_ids,
                'correct_answer' => $correct_option_ids,
                'is_correct' => $is_correct
            );
        }
        
        // Get campaign pass score
        $campaign = $this->db->get_row($this->db->prepare(
            "SELECT pass_score FROM {$this->table_campaigns} WHERE id = %d",
            $campaign_id
        ), ARRAY_A);
        
        $percentage = $max_score > 0 ? round(($score / $max_score) * 100, 2) : 0;
        $passed = $score >= ($campaign['pass_score'] ?? 0);
        
        return array(
            'score' => $score,
            'max_score' => $max_score,
            'percentage' => $percentage,
            'passed' => $passed,
            'breakdown' => $breakdown
        );
    }
    
    public function get_participant_statistics() {
        $total_participants = $this->db->get_var("SELECT COUNT(*) FROM {$this->table_participants}");
        
        $active_participants = $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->table_participants} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        $completed_quizzes = $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->table_participants} WHERE quiz_status = 'completed'"
        );
        
        $completion_rate = $total_participants > 0 ? round(($completed_quizzes / $total_participants) * 100, 1) : 0;
        
        $average_score = $this->db->get_var(
            "SELECT AVG(final_score) FROM {$this->table_participants} WHERE quiz_status = 'completed'"
        );
        
        return array(
            'total_participants' => $total_participants,
            'active_participants' => $active_participants,
            'completed_quizzes' => $completed_quizzes,
            'completion_rate' => $completion_rate,
            'average_score' => round($average_score, 2)
        );
    }
    
    public function get_participants_by_ids($participant_ids) {
        if (empty($participant_ids)) {
            return array();
        }
        
        $placeholders = implode(',', array_fill(0, count($participant_ids), '%d'));
        
        return $this->db->get_results($this->db->prepare(
            "SELECT p.*, c.name as campaign_name 
             FROM {$this->table_participants} p 
             LEFT JOIN {$this->table_campaigns} c ON p.campaign_id = c.id 
             WHERE p.id IN ({$placeholders})",
            $participant_ids
        ), ARRAY_A);
    }
    
    public function get_participants_for_export($filters) {
        $where_conditions = array('1=1');
        $params = array();
        
        if ($filters['campaign_id']) {
            $where_conditions[] = 'p.campaign_id = %d';
            $params[] = $filters['campaign_id'];
        }
        
        if ($filters['status'] !== 'all') {
            $where_conditions[] = 'p.quiz_status = %s';
            $params[] = $filters['status'];
        }
        
        if ($filters['date_from']) {
            $where_conditions[] = 'p.created_at >= %s';
            $params[] = $filters['date_from'];
        }
        
        if ($filters['date_to']) {
            $where_conditions[] = 'p.created_at <= %s';
            $params[] = $filters['date_to'];
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT p.*, c.name as campaign_name 
                  FROM {$this->table_participants} p 
                  LEFT JOIN {$this->table_campaigns} c ON p.campaign_id = c.id 
                  {$where_clause} 
                  ORDER BY p.created_at DESC";
        
        if (!empty($params)) {
            return $this->db->get_results($this->db->prepare($query, $params), ARRAY_A);
        } else {
            return $this->db->get_results($query, ARRAY_A);
        }
    }
    
    public function get_participant_analytics($participant_id) {
        $participant = $this->get_participant($participant_id);
        if (!$participant) {
            return false;
        }
        
        // Parse answers data
        $answers_data = json_decode($participant['answers_data'], true) ?: array();
        
        // Get detailed answer analysis
        $answer_analysis = array();
        if (!empty($answers_data)) {
            $score_result = $this->calculate_quiz_score($participant['campaign_id'], $answers_data);
            $answer_analysis = $score_result['breakdown'];
        }
        
        // Calculate time spent
        $time_spent = null;
        if ($participant['start_time'] && $participant['end_time']) {
            $start = strtotime($participant['start_time']);
            $end = strtotime($participant['end_time']);
            $time_spent = $end - $start; // seconds
        }
        
        return array(
            'participant' => $participant,
            'answer_analysis' => $answer_analysis,
            'time_spent' => $time_spent,
            'performance_metrics' => array(
                'score' => $participant['final_score'],
                'percentage' => $participant['final_score'] ? round(($participant['final_score'] / count($answer_analysis)) * 100, 2) : 0,
                'time_per_question' => $time_spent && count($answer_analysis) ? round($time_spent / count($answer_analysis), 2) : 0
            )
        );
    }
    
    public function add_to_segment($participant_ids, $segment_id) {
        // Placeholder for segment functionality
        // This would be implemented when segment feature is added
        return array('added_count' => count($participant_ids));
    }
}