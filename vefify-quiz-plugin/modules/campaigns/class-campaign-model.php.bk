<?php
/**
 * Campaign Model Module
 * File: modules/campaigns/class-campaign-model.php
 * Handles all database operations and data management for campaigns
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Campaign_Model {
    
    private $db;
    private $table_campaigns;
    private $table_gifts;
    private $table_participants;
    private $table_analytics;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_campaigns = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'campaigns';
        $this->table_gifts = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'gifts';
        $this->table_participants = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'participants';
        $this->table_analytics = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'analytics';
    }
    
    /**
     * Get all campaigns with pagination and filtering
     */
    public function get_campaigns($args = array()) {
        $defaults = array(
            'status' => 'all', // all, active, inactive, expired
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
            'include_stats' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $params = array();
        
        // Status filtering
        if ($args['status'] !== 'all') {
            switch ($args['status']) {
                case 'active':
                    $where_conditions[] = 'is_active = 1 AND start_date <= NOW() AND end_date >= NOW()';
                    break;
                case 'inactive':
                    $where_conditions[] = 'is_active = 0';
                    break;
                case 'expired':
                    $where_conditions[] = 'end_date < NOW()';
                    break;
            }
        }
        
        // Search filtering
        if (!empty($args['search'])) {
            $where_conditions[] = '(name LIKE %s OR description LIKE %s)';
            $search_term = '%' . $this->db->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Order clause
        $order_clause = sprintf(
            'ORDER BY %s %s',
            sanitize_sql_orderby($args['orderby']),
            strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC'
        );
        
        // Pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['per_page'], $offset);
        
        // Main query
        $query = "SELECT * FROM {$this->table_campaigns} {$where_clause} {$order_clause} {$limit_clause}";
        
        if (!empty($params)) {
            $campaigns = $this->db->get_results($this->db->prepare($query, $params), ARRAY_A);
        } else {
            $campaigns = $this->db->get_results($query, ARRAY_A);
        }
        
        // Add statistics if requested
        if ($args['include_stats'] && !empty($campaigns)) {
            foreach ($campaigns as &$campaign) {
                $campaign['stats'] = $this->get_campaign_statistics($campaign['id']);
            }
        }
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) FROM {$this->table_campaigns} {$where_clause}";
        if (!empty($params)) {
            $total_items = $this->db->get_var($this->db->prepare($count_query, $params));
        } else {
            $total_items = $this->db->get_var($count_query);
        }
        
        return array(
            'campaigns' => $campaigns,
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / $args['per_page']),
            'current_page' => $args['page']
        );
    }
    
    /**
     * Get single campaign by ID
     */
    public function get_campaign($campaign_id) {
        $campaign = $this->db->get_row(
            $this->db->prepare("SELECT * FROM {$this->table_campaigns} WHERE id = %d", $campaign_id),
            ARRAY_A
        );
        
        if ($campaign) {
            $campaign['meta_data'] = json_decode($campaign['meta_data'], true);
            $campaign['stats'] = $this->get_campaign_statistics($campaign_id);
        }
        
        return $campaign;
    }
    
    /**
     * Create new campaign
     */
    public function create_campaign($data) {
        $campaign_data = array(
            'name' => sanitize_text_field($data['name']),
            'slug' => $this->generate_unique_slug($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'max_participants' => intval($data['max_participants']),
            'questions_per_quiz' => intval($data['questions_per_quiz']),
            'pass_score' => intval($data['pass_score']),
            'time_limit' => intval($data['time_limit']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'meta_data' => json_encode($data['meta_data'] ?? array()),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $result = $this->db->insert($this->table_campaigns, $campaign_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create campaign: ' . $this->db->last_error);
        }
        
        $campaign_id = $this->db->insert_id;
        
        // Log campaign creation
        $this->log_campaign_action($campaign_id, 'created', 'Campaign created');
        
        return $campaign_id;
    }
    
    /**
     * Update existing campaign
     */
    public function update_campaign($campaign_id, $data) {
        $campaign_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'max_participants' => intval($data['max_participants']),
            'questions_per_quiz' => intval($data['questions_per_quiz']),
            'pass_score' => intval($data['pass_score']),
            'time_limit' => intval($data['time_limit']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'meta_data' => json_encode($data['meta_data'] ?? array()),
            'updated_at' => current_time('mysql')
        );
        
        $result = $this->db->update(
            $this->table_campaigns,
            $campaign_data,
            array('id' => $campaign_id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update campaign: ' . $this->db->last_error);
        }
        
        // Log campaign update
        $this->log_campaign_action($campaign_id, 'updated', 'Campaign updated');
        
        return true;
    }
    
    /**
     * Delete campaign (soft delete)
     */
    public function delete_campaign($campaign_id) {
        $result = $this->db->update(
            $this->table_campaigns,
            array('is_active' => 0, 'updated_at' => current_time('mysql')),
            array('id' => $campaign_id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete campaign: ' . $this->db->last_error);
        }
        
        // Log campaign deletion
        $this->log_campaign_action($campaign_id, 'deleted', 'Campaign soft deleted');
        
        return true;
    }
    
    /**
     * Get campaign statistics
     */
    public function get_campaign_statistics($campaign_id) {
        // Participants count
        $participants_count = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table_participants} WHERE campaign_id = %d",
            $campaign_id
        ));
        
        // Completed quizzes count
        $completed_count = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table_participants} WHERE campaign_id = %d AND quiz_status = 'completed'",
            $campaign_id
        ));
        
        // Average score
        $avg_score = $this->db->get_var($this->db->prepare(
            "SELECT AVG(final_score) FROM {$this->table_participants} WHERE campaign_id = %d AND quiz_status = 'completed'",
            $campaign_id
        ));
        
        // Pass rate
        $campaign = $this->get_campaign($campaign_id);
        $pass_rate = 0;
        if ($completed_count > 0 && $campaign) {
            $passed_count = $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table_participants} WHERE campaign_id = %d AND final_score >= %d",
                $campaign_id,
                $campaign['pass_score']
            ));
            $pass_rate = ($passed_count / $completed_count) * 100;
        }
        
        // Gift distribution
        $gifts_distributed = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table_participants} WHERE campaign_id = %d AND gift_code IS NOT NULL",
            $campaign_id
        ));
        
        return array(
            'participants_count' => (int) $participants_count,
            'completed_count' => (int) $completed_count,
            'completion_rate' => $participants_count > 0 ? round(($completed_count / $participants_count) * 100, 2) : 0,
            'average_score' => round((float) $avg_score, 2),
            'pass_rate' => round($pass_rate, 2),
            'gifts_distributed' => (int) $gifts_distributed
        );
    }
    
    /**
     * Get campaign analytics data
     */
    public function get_campaign_analytics($campaign_id, $date_range = '7days') {
        $date_condition = '';
        switch ($date_range) {
            case '24hours':
                $date_condition = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)';
                break;
            case '7days':
                $date_condition = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case '30days':
                $date_condition = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
        }
        
        // Daily participation data
        $daily_data = $this->db->get_results($this->db->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as participants,
                SUM(CASE WHEN quiz_status = 'completed' THEN 1 ELSE 0 END) as completed,
                AVG(CASE WHEN quiz_status = 'completed' THEN final_score ELSE NULL END) as avg_score
             FROM {$this->table_participants} 
             WHERE campaign_id = %d {$date_condition}
             GROUP BY DATE(created_at)
             ORDER BY date DESC",
            $campaign_id
        ), ARRAY_A);
        
        return array(
            'daily_data' => $daily_data,
            'summary' => $this->get_campaign_statistics($campaign_id)
        );
    }
    
    /**
     * Generate unique slug for campaign
     */
    private function generate_unique_slug($name) {
        $slug = sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;
        
        while ($this->slug_exists($slug)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Check if slug exists
     */
    private function slug_exists($slug) {
        $count = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table_campaigns} WHERE slug = %s",
            $slug
        ));
        
        return $count > 0;
    }
    
    /**
     * Log campaign action for audit trail
     */
    private function log_campaign_action($campaign_id, $action, $description) {
        $this->db->insert(
            $this->table_analytics,
            array(
                'campaign_id' => $campaign_id,
                'action_type' => $action,
                'description' => $description,
                'user_id' => get_current_user_id(),
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Validate campaign data
     */
    public function validate_campaign_data($data, $campaign_id = null) {
        $errors = array();
        
        // Required fields
        if (empty($data['name'])) {
            $errors[] = 'Campaign name is required';
        }
        
        if (empty($data['start_date'])) {
            $errors[] = 'Start date is required';
        }
        
        if (empty($data['end_date'])) {
            $errors[] = 'End date is required';
        }
        
        // Date validation
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            if (strtotime($data['start_date']) >= strtotime($data['end_date'])) {
                $errors[] = 'End date must be after start date';
            }
        }
        
        // Numeric validation
        if (isset($data['max_participants']) && !is_numeric($data['max_participants'])) {
            $errors[] = 'Max participants must be a number';
        }
        
        if (isset($data['questions_per_quiz']) && (!is_numeric($data['questions_per_quiz']) || $data['questions_per_quiz'] < 1)) {
            $errors[] = 'Questions per quiz must be a positive number';
        }
        
        if (isset($data['pass_score']) && !is_numeric($data['pass_score'])) {
            $errors[] = 'Pass score must be a number';
        }
        
        return $errors;
    }
    
    /**
     * Get campaigns summary for dashboard
     */
    public function get_campaigns_summary() {
        $total_campaigns = $this->db->get_var("SELECT COUNT(*) FROM {$this->table_campaigns}");
        $active_campaigns = $this->db->get_var("SELECT COUNT(*) FROM {$this->table_campaigns} WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()");
        $expired_campaigns = $this->db->get_var("SELECT COUNT(*) FROM {$this->table_campaigns} WHERE end_date < NOW()");
        
        return array(
            'total' => (int) $total_campaigns,
            'active' => (int) $active_campaigns,
            'expired' => (int) $expired_campaigns,
            'inactive' => (int) ($total_campaigns - $active_campaigns - $expired_campaigns)
        );
    }
}