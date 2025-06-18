<?php
/**
 * Participant Model Class - FIXED VERSION
 * File: modules/participants/class-participant-model.php
 * 
 * FIXED: All column name references to match actual database schema
 */

if (!defined('ABSPATH')) {
    exit;
}

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
    
    /**
     * FIXED: Get participants with correct column names
     */
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
        
        // FIXED: Use quiz_status instead of status
        if ($args['status'] !== 'all') {
            $where_conditions[] = 'p.quiz_status = %s';
            $params[] = $args['status'];
        }
        
        // FIXED: Use correct column names for search
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
    
    /**
     * Get single participant
     */
    public function get_participant($participant_id) {
        return $this->db->get_row($this->db->prepare(
            "SELECT p.*, c.name as campaign_name 
             FROM {$this->table_participants} p 
             LEFT JOIN {$this->table_campaigns} c ON p.campaign_id = c.id 
             WHERE p.id = %d",
            $participant_id
        ), ARRAY_A);
    }
    
    /**
     * Start quiz session for participant
     */
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
        
        // FIXED: Check for existing participant with correct column name
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
    
    /**
     * Submit quiz answers and update participant
     */
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
        
        // FIXED: Update participant record with correct column names
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
    
    /**
     * Calculate quiz score
     */
    public function calculate_quiz_score($campaign_id, $answers) {
        $questions_table = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'questions';
        $options_table = $this->db->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'question_options';
        
        // Get questions for the campaign
        $questions = $this->db->get_results($this->db->prepare(
            "SELECT id FROM {$questions_table} WHERE campaign_id = %d AND is_active = 1",
            $campaign_id
        ), ARRAY_A);
        
        $score = 0;
        $max_score = count($questions);
        $breakdown = array();
        
        foreach ($questions as $question) {
            $question_id = $question['id'];
            
            // Get correct answers
            $correct_options = $this->db->get_col($this->db->prepare(
                "SELECT id FROM {$options_table} WHERE question_id = %d AND is_correct = 1",
                $question_id
            ));
            
            $user_answers = isset($answers[$question_id]) ? $answers[$question_id] : array();
            if (!is_array($user_answers)) {
                $user_answers = array($user_answers);
            }
            
            // Check if answer is correct
            sort($correct_options);
            sort($user_answers);
            $is_correct = ($correct_options === array_map('intval', $user_answers));
            
            if ($is_correct) {
                $score++;
            }
            
            $breakdown[$question_id] = array(
                'user_answers' => $user_answers,
                'correct_answers' => $correct_options,
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
    
    /**
     * FIXED: Get participant statistics with correct column names
     */
    public function get_participant_statistics() {
        // FIXED: Use correct column references
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
    
    /**
     * Get participant analytics
     */
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
    
    /**
     * Get participants by IDs
     */
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
    
    /**
     * FIXED: Get participants for export with correct column names
     */
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
        
        // FIXED: Use correct column names for export
        $query = "SELECT p.participant_name, p.participant_email, p.participant_phone,
                         p.province, p.pharmacy_code, p.final_score, p.total_questions,
                         p.quiz_status, p.start_time, p.end_time, p.created_at,
                         c.name as campaign_name, g.gift_name, p.gift_code
                  FROM {$this->table_participants} p
                  LEFT JOIN {$this->table_campaigns} c ON p.campaign_id = c.id
                  LEFT JOIN {$this->db->prefix}" . VEFIFY_QUIZ_TABLE_PREFIX . "gifts g ON p.gift_id = g.id
                  {$where_clause}
                  ORDER BY p.created_at DESC";
        
        if (!empty($params)) {
            return $this->db->get_results($this->db->prepare($query, $params), ARRAY_A);
        } else {
            return $this->db->get_results($query, ARRAY_A);
        }
    }
    
    /**
     * Get participant by session
     */
    public function get_participant_by_session($session_id) {
        return $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->table_participants} WHERE session_id = %s",
            $session_id
        ), ARRAY_A);
    }
    
    /**
     * Update participant status
     */
    public function update_participant_status($participant_id, $status) {
        return $this->db->update(
            $this->table_participants,
            array(
                'quiz_status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $participant_id)
        );
    }
    
    /**
     * Add gift to participant
     */
    public function add_gift_to_participant($participant_id, $gift_id, $gift_code) {
        return $this->db->update(
            $this->table_participants,
            array(
                'gift_id' => $gift_id,
                'gift_code' => $gift_code,
                'gift_status' => 'assigned',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $participant_id)
        );
    }
    
    /**
     * Get participants filtered list
     */
    public function get_filtered_participants($filters = array()) {
        $where_conditions = array('1=1');
        $params = array();
        
        if (!empty($filters['campaign_id'])) {
            $where_conditions[] = 'p.campaign_id = %d';
            $params[] = $filters['campaign_id'];
        }
        
        if (!empty($filters['province'])) {
            $where_conditions[] = 'p.province = %s';
            $params[] = $filters['province'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = 'p.quiz_status = %s';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = '(p.participant_name LIKE %s OR p.participant_email LIKE %s)';
            $search_term = '%' . $this->db->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
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
    
    /**
     * Placeholder for segment functionality
     */
    public function add_to_segment($participant_ids, $segment_id) {
        // This would be implemented when segment feature is added
        return array('added_count' => count($participant_ids));
    }
	
	/** UPDATE FRONT END **/
	/**
 * Check if phone number exists in campaign
 */
public function phone_exists_in_campaign($phone, $campaign_id) {
    return $this->db->get_var($this->db->prepare(
        "SELECT COUNT(*) FROM {$this->table_participants} 
         WHERE participant_phone = %s AND campaign_id = %d",
        $phone, $campaign_id
    )) > 0;
}

/**
 * Get participant by session ID
 */
public function get_participant_by_session($session_id) {
    return $this->db->get_row($this->db->prepare(
        "SELECT * FROM {$this->table_participants} WHERE session_id = %s",
        $session_id
    ), ARRAY_A);
}

/**
 * Update participant session
 */
public function update_participant_session($participant_id, $session_data) {
    return $this->db->update(
        $this->table_participants,
        $session_data,
        array('id' => $participant_id)
    );
}

/**
 * Get campaign questions with options
 */
public function get_campaign_questions_with_options($campaign_id, $limit = null) {
    $limit_clause = $limit ? "LIMIT " . intval($limit) : "";
    
    $questions = $this->db->get_results($this->db->prepare(
        "SELECT * FROM {$this->db->prefix}vefify_questions 
         WHERE campaign_id = %d AND is_active = 1 
         ORDER BY order_index, id {$limit_clause}",
        $campaign_id
    ));
    
    foreach ($questions as $question) {
        $question->options = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}vefify_question_options 
             WHERE question_id = %d 
             ORDER BY order_index, id",
            $question->id
        ));
    }
    
    return $questions;
}

/**
 * Calculate completion time
 */
public function calculate_completion_time($participant_id) {
    $participant = $this->get_participant($participant_id);
    
    if (!$participant || !$participant['start_time'] || !$participant['end_time']) {
        return 0;
    }
    
    $start = strtotime($participant['start_time']);
    $end = strtotime($participant['end_time']);
    
    return $end - $start; // Return seconds
}

/**
 * Get eligible gifts for participant score
 */
public function get_eligible_gifts($campaign_id, $score) {
    return $this->db->get_results($this->db->prepare(
        "SELECT * FROM {$this->db->prefix}vefify_gifts 
         WHERE campaign_id = %d 
         AND is_active = 1 
         AND min_score <= %d 
         AND (max_score IS NULL OR max_score >= %d)
         AND (max_quantity IS NULL OR used_count < max_quantity)
         ORDER BY min_score DESC",
        $campaign_id, $score, $score
    ), ARRAY_A);
}

/**
 * Assign gift to participant
 */
public function assign_gift_to_participant($participant_id, $gift_id, $gift_code) {
    // Update participant with gift
    $result = $this->db->update(
        $this->table_participants,
        array(
            'gift_id' => $gift_id,
            'gift_code' => $gift_code,
            'gift_status' => 'assigned',
            'updated_at' => current_time('mysql')
        ),
        array('id' => $participant_id)
    );
    
    if ($result !== false) {
        // Update gift usage count
        $this->db->query($this->db->prepare(
            "UPDATE {$this->db->prefix}vefify_gifts 
             SET used_count = used_count + 1 
             WHERE id = %d",
            $gift_id
        ));
    }
    
    return $result;
}

/**
 * Mark gift as claimed
 */
public function claim_gift($participant_id) {
    return $this->db->update(
        $this->table_participants,
        array(
            'gift_status' => 'claimed',
            'updated_at' => current_time('mysql')
        ),
        array('id' => $participant_id)
    );
}

/**
 * Get participant statistics for dashboard
 */
public function get_campaign_participant_stats($campaign_id) {
    $stats = $this->db->get_row($this->db->prepare(
        "SELECT 
            COUNT(*) as total_participants,
            COUNT(CASE WHEN quiz_status = 'completed' THEN 1 END) as completed_participants,
            COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as participants_with_gifts,
            AVG(CASE WHEN quiz_status = 'completed' THEN final_score END) as avg_score,
            MAX(final_score) as max_score,
            MIN(CASE WHEN quiz_status = 'completed' THEN final_score END) as min_score
        FROM {$this->table_participants} 
        WHERE campaign_id = %d",
        $campaign_id
    ), ARRAY_A);
    
    return $stats ?: array(
        'total_participants' => 0,
        'completed_participants' => 0,
        'participants_with_gifts' => 0,
        'avg_score' => 0,
        'max_score' => 0,
        'min_score' => 0
    );
}

/**
 * Get participant answers breakdown
 */
public function get_participant_answers_breakdown($participant_id) {
    $participant = $this->get_participant($participant_id);
    
    if (!$participant || !$participant['answers_data']) {
        return array();
    }
    
    $answers = json_decode($participant['answers_data'], true);
    if (!$answers) {
        return array();
    }
    
    $breakdown = array();
    
    foreach ($answers as $question_id => $user_answers) {
        // Get question details
        $question = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}vefify_questions WHERE id = %d",
            $question_id
        ), ARRAY_A);
        
        if (!$question) continue;
        
        // Get correct answers
        $correct_options = $this->db->get_results($this->db->prepare(
            "SELECT id, option_text FROM {$this->db->prefix}vefify_question_options 
             WHERE question_id = %d AND is_correct = 1",
            $question_id
        ), ARRAY_A);
        
        // Get user selected options
        $user_options = array();
        if (!empty($user_answers)) {
            $placeholders = implode(',', array_fill(0, count($user_answers), '%d'));
            $user_options = $this->db->get_results($this->db->prepare(
                "SELECT id, option_text FROM {$this->db->prefix}vefify_question_options 
                 WHERE id IN ({$placeholders})",
                $user_answers
            ), ARRAY_A);
        }
        
        // Check if answer is correct
        $correct_ids = array_column($correct_options, 'id');
        $user_ids = array_column($user_options, 'id');
        sort($correct_ids);
        sort($user_ids);
        $is_correct = ($correct_ids === $user_ids);
        
        $breakdown[] = array(
            'question' => $question,
            'user_answers' => $user_options,
            'correct_answers' => $correct_options,
            'is_correct' => $is_correct
        );
    }
    
    return $breakdown;
}

/**
 * Export participants data for campaign
 */
public function export_campaign_participants($campaign_id, $format = 'csv') {
    $participants = $this->db->get_results($this->db->prepare(
        "SELECT 
            p.participant_name,
            p.participant_phone,
            p.participant_email,
            p.province,
            p.pharmacy_code,
            p.quiz_status,
            p.final_score,
            p.total_questions,
            p.start_time,
            p.end_time,
            p.created_at,
            g.gift_name,
            p.gift_code,
            p.gift_status
        FROM {$this->table_participants} p
        LEFT JOIN {$this->db->prefix}vefify_gifts g ON p.gift_id = g.id
        WHERE p.campaign_id = %d
        ORDER BY p.created_at DESC",
        $campaign_id
    ), ARRAY_A);
    
    if ($format === 'csv') {
        return Vefify_Quiz_Utilities::generate_csv($participants);
    }
    
    return $participants;
}

/**
 * Cleanup old sessions and incomplete participants
 */
public function cleanup_old_data($days = 7) {
    // Delete participants who never completed registration (stuck in 'started' status)
    $deleted_incomplete = $this->db->query($this->db->prepare(
        "DELETE FROM {$this->table_participants} 
         WHERE quiz_status = 'started' 
         AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $days
    ));
    
    // Clean up orphaned sessions
    $deleted_sessions = $this->db->query($this->db->prepare(
        "DELETE FROM {$this->db->prefix}vefify_quiz_sessions 
         WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
         AND is_completed = 0",
        $days
    ));
    
    return array(
        'deleted_participants' => $deleted_incomplete,
        'deleted_sessions' => $deleted_sessions
    );
}

/**
 * Get participants leaderboard for campaign
 */
public function get_campaign_leaderboard($campaign_id, $limit = 10) {
    return $this->db->get_results($this->db->prepare(
        "SELECT 
            participant_name,
            final_score,
            total_questions,
            CASE 
                WHEN total_questions > 0 THEN ROUND((final_score / total_questions) * 100, 1)
                ELSE 0 
            END as percentage,
            end_time
        FROM {$this->table_participants}
        WHERE campaign_id = %d 
        AND quiz_status = 'completed'
        ORDER BY final_score DESC, end_time ASC
        LIMIT %d",
        $campaign_id, $limit
    ), ARRAY_A);
}

/**
 * Get participant ranking in campaign
 */
public function get_participant_ranking($participant_id) {
    $participant = $this->get_participant($participant_id);
    
    if (!$participant || $participant['quiz_status'] !== 'completed') {
        return null;
    }
    
    $ranking = $this->db->get_var($this->db->prepare(
        "SELECT COUNT(*) + 1 as ranking
        FROM {$this->table_participants}
        WHERE campaign_id = %d 
        AND quiz_status = 'completed'
        AND (
            final_score > %d 
            OR (final_score = %d AND end_time < %s)
        )",
        $participant['campaign_id'],
        $participant['final_score'],
        $participant['final_score'],
        $participant['end_time']
    ));
    
    $total = $this->db->get_var($this->db->prepare(
        "SELECT COUNT(*) 
        FROM {$this->table_participants}
        WHERE campaign_id = %d AND quiz_status = 'completed'",
        $participant['campaign_id']
    ));
    
    return array(
        'ranking' => $ranking ?: 1,
        'total' => $total ?: 1
    );
}

/**
 * Generate participant certificate data
 */
public function get_certificate_data($participant_id) {
    $participant = $this->get_participant($participant_id);
    
    if (!$participant || $participant['quiz_status'] !== 'completed') {
        return null;
    }
    
    $campaign = $this->db->get_row($this->db->prepare(
        "SELECT name, description FROM {$this->table_campaigns} WHERE id = %d",
        $participant['campaign_id']
    ), ARRAY_A);
    
    $percentage = $participant['total_questions'] > 0 ? 
        round(($participant['final_score'] / $participant['total_questions']) * 100, 1) : 0;
    
    $ranking = $this->get_participant_ranking($participant_id);
    
    return array(
        'participant_name' => $participant['participant_name'],
        'campaign_name' => $campaign['name'],
        'score' => $participant['final_score'],
        'total_questions' => $participant['total_questions'],
        'percentage' => $percentage,
        'completion_date' => $participant['end_time'],
        'ranking' => $ranking,
        'certificate_id' => 'VQ-' . $participant['campaign_id'] . '-' . $participant['id']
    );
}
}