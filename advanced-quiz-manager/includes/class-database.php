<?php
/**
 * Extended Database Operations for Advanced Quiz Manager
 * File: includes/class-database.php
 */

class AQM_Database {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    // CAMPAIGN METHODS
    public function get_campaigns($args = array()) {
        $defaults = array(
            'status' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => -1,
            'search' => '',
            'user_id' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $this->wpdb->prefix . 'aqm_campaigns';
        $where = '1=1';
        $values = array();
        
        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where .= ' AND (title LIKE %s OR description LIKE %s)';
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $values[] = $search;
            $values[] = $search;
        }
        
        if (!empty($args['user_id'])) {
            $where .= ' AND created_by = %d';
            $values[] = $args['user_id'];
        }
        
        $order = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $limit = $args['limit'] > 0 ? 'LIMIT ' . intval($args['limit']) : '';
        
        $sql = "SELECT * FROM $table WHERE $where ORDER BY $order $limit";
        
        if (!empty($values)) {
            return $this->wpdb->get_results($this->wpdb->prepare($sql, $values));
        } else {
            return $this->wpdb->get_results($sql);
        }
    }
    
    public function get_campaign($campaign_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}aqm_campaigns WHERE id = %d",
            $campaign_id
        ));
    }
    
    public function create_campaign($data) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'aqm_campaigns',
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d')
        );
        
        if ($result !== false) {
            return $this->wpdb->insert_id;
        }
        
        return false;
    }
    
    public function update_campaign($campaign_id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'aqm_campaigns',
            $data,
            array('id' => $campaign_id),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );
    }
    
    public function delete_campaign($campaign_id) {
        // Delete related data first
        $this->delete_campaign_questions($campaign_id);
        $this->delete_campaign_responses($campaign_id);
        $this->delete_campaign_gifts($campaign_id);
        $this->delete_campaign_notifications($campaign_id);
        
        // Delete campaign
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'aqm_campaigns',
            array('id' => $campaign_id),
            array('%d')
        );
    }
    
    // QUESTION METHODS
    public function get_campaign_questions($campaign_id, $args = array()) {
        $defaults = array(
            'orderby' => 'order_index',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}aqm_questions 
             WHERE campaign_id = %d 
             ORDER BY {$args['orderby']} {$args['order']}",
            $campaign_id
        ));
    }
    
    public function get_question($question_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}aqm_questions WHERE id = %d",
            $question_id
        ));
    }
    
    public function create_question($data) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'aqm_questions',
            $data,
            array('%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d')
        );
        
        if ($result !== false) {
            return $this->wpdb->insert_id;
        }
        
        return false;
    }
    
    public function update_question($question_id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'aqm_questions',
            $data,
            array('id' => $question_id),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d'),
            array('%d')
        );
    }
    
    public function delete_question($question_id) {
        // Delete related answers first
        $this->wpdb->delete(
            $this->wpdb->prefix . 'aqm_answers',
            array('question_id' => $question_id),
            array('%d')
        );
        
        // Delete question
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'aqm_questions',
            array('id' => $question_id),
            array('%d')
        );
    }
    
    public function delete_campaign_questions($campaign_id) {
        $questions = $this->get_campaign_questions($campaign_id);
        foreach ($questions as $question) {
            $this->delete_question($question->id);
        }
    }
    
    public function reorder_questions($question_orders) {
        foreach ($question_orders as $question_id => $order) {
            $this->wpdb->update(
                $this->wpdb->prefix . 'aqm_questions',
                array('order_index' => intval($order)),
                array('id' => intval($question_id)),
                array('%d'),
                array('%d')
            );
        }
        return true;
    }
    
    // RESPONSE METHODS
    public function get_campaign_responses($campaign_id, $args = array()) {
        $defaults = array(
            'status' => '',
            'orderby' => 'submitted_at',
            'order' => 'DESC',
            'limit' => -1,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = 'WHERE campaign_id = %d';
        $values = array($campaign_id);
        
        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $values[] = $args['status'];
        }
        
        $order = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $limit = '';
        
        if ($args['limit'] > 0) {
            $limit = 'LIMIT ' . intval($args['offset']) . ', ' . intval($args['limit']);
        }
        
        $sql = "SELECT * FROM {$this->wpdb->prefix}aqm_responses $where ORDER BY $order $limit";
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $values));
    }
    
    public function get_response($response_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}aqm_responses WHERE id = %d",
            $response_id
        ));
    }
    
    public function create_response($data) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'aqm_responses',
            $data,
            array('%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s')
        );
        
        if ($result !== false) {
            return $this->wpdb->insert_id;
        }
        
        return false;
    }
    
    public function update_response($response_id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'aqm_responses',
            $data,
            array('id' => $response_id),
            array('%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s'),
            array('%d')
        );
    }
    
    public function delete_response($response_id) {
        // Delete related answers and gift awards first
        $this->wpdb->delete(
            $this->wpdb->prefix . 'aqm_answers',
            array('response_id' => $response_id),
            array('%d')
        );
        
        $this->wpdb->delete(
            $this->wpdb->prefix . 'aqm_gift_awards',
            array('response_id' => $response_id),
            array('%d')
        );
        
        // Delete response
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'aqm_responses',
            array('id' => $response_id),
            array('%d')
        );
    }
    
    public function delete_campaign_responses($campaign_id) {
        $responses = $this->get_campaign_responses($campaign_id);
        foreach ($responses as $response) {
            $this->delete_response($response->id);
        }
    }
    
    // ANSWER METHODS
    public function get_response_answers($response_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT a.*, q.question_text, q.question_type 
             FROM {$this->wpdb->prefix}aqm_answers a
             LEFT JOIN {$this->wpdb->prefix}aqm_questions q ON a.question_id = q.id
             WHERE a.response_id = %d
             ORDER BY q.order_index ASC",
            $response_id
        ));
    }
    
    public function save_answer($response_id, $question_id, $answer_value, $score = 0) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'aqm_answers',
            array(
                'response_id' => $response_id,
                'question_id' => $question_id,
                'answer_value' => $answer_value,
                'answer_score' => $score
            ),
            array('%d', '%d', '%s', '%d')
        );
    }
    
    // GIFT METHODS
    public function get_campaign_gifts($campaign_id, $status = 'active') {
        $where = 'WHERE campaign_id = %d';
        $values = array($campaign_id);
        
        if ($status) {
            $where .= ' AND status = %s';
            $values[] = $status;
        }
        
        $sql = "SELECT * FROM {$this->wpdb->prefix}aqm_gifts $where ORDER BY min_score ASC";
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $values));
    }
    
    public function get_gift($gift_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}aqm_gifts WHERE id = %d",
            $gift_id
        ));
    }
    
    public function create_gift($data) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'aqm_gifts',
            $data,
            array('%d', '%s', '%s', '%s', '%f', '%d', '%d', '%f', '%s')
        );
        
        if ($result !== false) {
            return $this->wpdb->insert_id;
        }
        
        return false;
    }
    
    public function update_gift($gift_id, $data) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'aqm_gifts',
            $data,
            array('id' => $gift_id),
            array('%d', '%s', '%s', '%s', '%f', '%d', '%d', '%f', '%s'),
            array('%d')
        );
    }
    
    public function delete_gift($gift_id) {
        // Delete related awards first
        $this->wpdb->delete(
            $this->wpdb->prefix . 'aqm_gift_awards',
            array('gift_id' => $gift_id),
            array('%d')
        );
        
        // Delete gift
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'aqm_gifts',
            array('id' => $gift_id),
            array('%d')
        );
    }
    
    public function delete_campaign_gifts($campaign_id) {
        $gifts = $this->get_campaign_gifts($campaign_id, '');
        foreach ($gifts as $gift) {
            $this->delete_gift($gift->id);
        }
    }
    
    public function check_gift_eligibility($campaign_id, $score) {
        $eligible_gifts = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}aqm_gifts 
             WHERE campaign_id = %d 
             AND status = 'active' 
             AND min_score <= %d 
             AND quantity > 0
             ORDER BY min_score DESC, probability DESC",
            $campaign_id,
            $score
        ));
        
        foreach ($eligible_gifts as $gift) {
            // Check probability
            $random = mt_rand(1, 10000) / 100; // Generate 0.01 to 100.00
            if ($random <= $gift->probability) {
                return $gift;
            }
        }
        
        return null;
    }
    
    public function award_gift($response_id, $gift_id) {
        // Generate claim code
        $claim_code = $this->generate_claim_code();
        
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'aqm_gift_awards',
            array(
                'response_id' => $response_id,
                'gift_id' => $gift_id,
                'claim_code' => $claim_code,
                'is_claimed' => 0
            ),
            array('%d', '%d', '%s', '%d')
        );
        
        if ($result !== false) {
            // Decrease gift quantity
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->wpdb->prefix}aqm_gifts 
                 SET quantity = quantity - 1 
                 WHERE id = %d AND quantity > 0",
                $gift_id
            ));
            
            return $claim_code;
        }
        
        return false;
    }
    
    public function claim_gift($claim_code) {
        return $this->wpdb->update(
            $this->wpdb->prefix . 'aqm_gift_awards',
            array(
                'is_claimed' => 1,
                'claimed_at' => current_time('mysql')
            ),
            array('claim_code' => $claim_code),
            array('%d', '%s'),
            array('%s')
        );
    }
    
    private function generate_claim_code() {
        return strtoupper(wp_generate_password(12, false));
    }
    
    // NOTIFICATION METHODS
    public function get_campaign_notifications($campaign_id, $type = '') {
        $where = 'WHERE campaign_id = %d';
        $values = array($campaign_id);
        
        if ($type) {
            $where .= ' AND type = %s';
            $values[] = $type;
        }
        
        $sql = "SELECT * FROM {$this->wpdb->prefix}aqm_notifications $where ORDER BY type ASC";
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $values));
    }
    
    public function create_notification($data) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'aqm_notifications',
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    public function delete_campaign_notifications($campaign_id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'aqm_notifications',
            array('campaign_id' => $campaign_id),
            array('%d')
        );
    }
    
    // ANALYTICS METHODS
    public function log_analytics($campaign_id, $event_type, $event_data, $user_id = null) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'aqm_analytics',
            array(
                'campaign_id' => $campaign_id,
                'event_type' => $event_type,
                'event_data' => json_encode($event_data),
                'user_id' => $user_id,
                'session_id' => session_id() ?: wp_generate_uuid4(),
                'ip_address' => $this->get_user_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'page_url' => $_SERVER['REQUEST_URI'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    public function get_analytics_data($campaign_id = null, $date_from = null, $date_to = null) {
        $where = '1=1';
        $values = array();
        
        if ($campaign_id) {
            $where .= ' AND campaign_id = %d';
            $values[] = $campaign_id;
        }
        
        if ($date_from) {
            $where .= ' AND created_at >= %s';
            $values[] = $date_from;
        }
        
        if ($date_to) {
            $where .= ' AND created_at <= %s';
            $values[] = $date_to;
        }
        
        $sql = "SELECT * FROM {$this->wpdb->prefix}aqm_analytics WHERE $where ORDER BY created_at DESC";
        
        if (!empty($values)) {
            return $this->wpdb->get_results($this->wpdb->prepare($sql, $values));
        } else {
            return $this->wpdb->get_results($sql);
        }
    }
    
    public function get_campaign_stats($campaign_id) {
        // Total participants
        $total_participants = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_responses WHERE campaign_id = %d",
            $campaign_id
        ));
        
        // Completed responses
        $completed = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_responses 
             WHERE campaign_id = %d AND status = 'completed'",
            $campaign_id
        ));
        
        // Completion rate
        $completion_rate = $total_participants > 0 ? round(($completed / $total_participants) * 100, 2) : 0;
        
        // Average score
        $average_score = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT AVG(total_score) FROM {$this->wpdb->prefix}aqm_responses 
             WHERE campaign_id = %d AND status = 'completed'",
            $campaign_id
        ));
        
        // Average completion time
        $average_time = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT AVG(completion_time) FROM {$this->wpdb->prefix}aqm_responses 
             WHERE campaign_id = %d AND status = 'completed' AND completion_time > 0",
            $campaign_id
        ));
        
        // Today's responses
        $today = date('Y-m-d');
        $todays_responses = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_responses 
             WHERE campaign_id = %d AND DATE(submitted_at) = %s",
            $campaign_id,
            $today
        ));
        
        // Gifts awarded
        $gifts_awarded = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_gift_awards ga
             JOIN {$this->wpdb->prefix}aqm_responses r ON ga.response_id = r.id
             WHERE r.campaign_id = %d",
            $campaign_id
        ));
        
        return array(
            'total_participants' => intval($total_participants),
            'completed_responses' => intval($completed),
            'completion_rate' => floatval($completion_rate),
            'average_score' => round(floatval($average_score), 2),
            'average_time' => round(floatval($average_time), 2),
            'todays_responses' => intval($todays_responses),
            'gifts_awarded' => intval($gifts_awarded)
        );
    }
    
    public function get_response_distribution($campaign_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(submitted_at) as date, COUNT(*) as count 
             FROM {$this->wpdb->prefix}aqm_responses 
             WHERE campaign_id = %d 
             GROUP BY DATE(submitted_at) 
             ORDER BY date DESC 
             LIMIT 30",
            $campaign_id
        ));
    }
    
    public function get_province_distribution($campaign_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT answer_value, COUNT(*) as count 
             FROM {$this->wpdb->prefix}aqm_answers a
             JOIN {$this->wpdb->prefix}aqm_questions q ON a.question_id = q.id
             JOIN {$this->wpdb->prefix}aqm_responses r ON a.response_id = r.id
             WHERE r.campaign_id = %d AND q.question_type = 'provinces'
             GROUP BY answer_value 
             ORDER BY count DESC",
            $campaign_id
        ));
    }
    
    // UTILITY METHODS
    public function get_provinces($args = array()) {
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => -1,
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $this->wpdb->prefix . 'aqm_provinces';
        $where = '1=1';
        $values = array();
        
        if (!empty($args['search'])) {
            $where .= ' AND (name LIKE %s OR name_en LIKE %s OR code LIKE %s)';
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }
        
        $order = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $limit = $args['limit'] > 0 ? 'LIMIT ' . intval($args['limit']) : '';
        
        $sql = "SELECT * FROM $table WHERE $where ORDER BY $order $limit";
        
        if (!empty($values)) {
            return $this->wpdb->get_results($this->wpdb->prepare($sql, $values));
        } else {
            return $this->wpdb->get_results($sql);
        }
    }
    
    public function get_districts($province_code = '', $args = array()) {
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => -1
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $this->wpdb->prefix . 'aqm_districts';
        $where = '1=1';
        $values = array();
        
        if (!empty($province_code)) {
            $where .= ' AND province_code = %s';
            $values[] = $province_code;
        }
        
        $order = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $limit = $args['limit'] > 0 ? 'LIMIT ' . intval($args['limit']) : '';
        
        $sql = "SELECT * FROM $table WHERE $where ORDER BY $order $limit";
        
        if (!empty($values)) {
            return $this->wpdb->get_results($this->wpdb->prepare($sql, $values));
        } else {
            return $this->wpdb->get_results($sql);
        }
    }
    
    public function get_wards($district_code = '', $args = array()) {
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => -1
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $this->wpdb->prefix . 'aqm_wards';
        $where = '1=1';
        $values = array();
        
        if (!empty($district_code)) {
            $where .= ' AND district_code = %s';
            $values[] = $district_code;
        }
        
        $order = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $limit = $args['limit'] > 0 ? 'LIMIT ' . intval($args['limit']) : '';
        
        $sql = "SELECT * FROM $table WHERE $where ORDER BY $order $limit";
        
        if (!empty($values)) {
            return $this->wpdb->get_results($this->wpdb->prepare($sql, $values));
        } else {
            return $this->wpdb->get_results($sql);
        }
    }
    
    public function bulk_insert_provinces($provinces_data) {
        $success_count = 0;
        
        foreach ($provinces_data as $province) {
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'aqm_provinces',
                array(
                    'code' => sanitize_text_field($province['code']),
                    'name' => sanitize_text_field($province['name']),
                    'name_en' => sanitize_text_field($province['name_en'] ?? ''),
                    'full_name' => sanitize_text_field($province['full_name'] ?? ''),
                    'full_name_en' => sanitize_text_field($province['full_name_en'] ?? ''),
                    'code_name' => sanitize_text_field($province['code_name'] ?? '')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result !== false) {
                $success_count++;
            }
        }
        
        return $success_count;
    }
    
    private function get_user_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    // CLEANUP METHODS
    public function cleanup_old_analytics($days = 90) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->wpdb->prefix}aqm_analytics WHERE created_at < %s",
            $cutoff_date
        ));
    }
    
    public function cleanup_abandoned_responses($hours = 24) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        return $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->wpdb->prefix}aqm_responses 
             WHERE status = 'in_progress' AND submitted_at < %s",
            $cutoff_date
        ));
    }
    
    // EXPORT METHODS
    public function export_responses_csv($campaign_id) {
        $responses = $this->get_campaign_responses($campaign_id, array('status' => 'completed'));
        $questions = $this->get_campaign_questions($campaign_id);
        
        if (empty($responses)) {
            return false;
        }
        
        // Prepare CSV data
        $csv_data = array();
        
        // Header row
        $headers = array('Response ID', 'User Name', 'User Email', 'Score', 'Completion Time', 'Submitted At');
        foreach ($questions as $question) {
            $headers[] = $question->question_text;
        }
        $csv_data[] = $headers;
        
        // Data rows
        foreach ($responses as $response) {
            $answers = $this->get_response_answers($response->id);
            $answer_map = array();
            
            foreach ($answers as $answer) {
                $answer_map[$answer->question_id] = $answer->answer_value;
            }
            
            $row = array(
                $response->id,
                $response->user_name,
                $response->user_email,
                $response->total_score,
                $response->completion_time,
                $response->submitted_at
            );
            
            foreach ($questions as $question) {
                $row[] = $answer_map[$question->id] ?? '';
            }
            
            $csv_data[] = $row;
        }
        
        return $csv_data;
    }
	
	// Add this method to the AQM_Database class:
	public function check_missing_tables() {
		global $wpdb;
		
		$missing_tables = array();
		$required_tables = array('aqm_provinces', 'aqm_districts', 'aqm_wards', 'aqm_gifts', 'aqm_gift_awards');
		
		foreach ($required_tables as $table) {
			$table_name = $wpdb->prefix . $table;
			if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
				$missing_tables[] = $table;
			}
		}
		
		return $missing_tables;
	}
	
}
?>