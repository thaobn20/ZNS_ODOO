<?php
/**
 * Campaign Model Module
 * File: modules/campaigns/class-campaign-model.php
 * 
 * PERFORMANCE OPTIMIZED but maintains original class name
 * - Original class name: Vefify_Campaign_Model (for shortcode compatibility)
 * - Optimized queries and caching
 * - Reduced memory usage
 * - Faster database operations
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
    private $cache_group = 'vefify_campaigns';
    private $cache_time = 300; // 5 minutes
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_campaigns = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'campaigns';
        $this->table_gifts = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'gifts';
        $this->table_participants = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'participants';
        $this->table_analytics = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'analytics';
    }
    
    /**
     * Get all campaigns with pagination and filtering - OPTIMIZED
     */
    public function get_campaigns($args = array()) {
        $defaults = array(
            'status' => 'all',
            'per_page' => 15, // Reduced from 20 for better performance
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
            'include_stats' => false // Disabled by default for performance
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Create cache key
        $cache_key = 'campaigns_' . md5(serialize($args));
        $cached_result = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_result !== false && !WP_DEBUG) {
            return $cached_result;
        }
        
        $where_conditions = array('1=1');
        $params = array();
        
        // Status filtering - SIMPLIFIED
        if ($args['status'] !== 'all') {
            switch ($args['status']) {
                case 'active':
                    $where_conditions[] = 'is_active = 1';
                    break;
                case 'inactive':
                    $where_conditions[] = 'is_active = 0';
                    break;
                case 'expired':
                    $where_conditions[] = 'end_date < NOW()';
                    break;
            }
        }
        
        // Search filtering - OPTIMIZED
        if (!empty($args['search'])) {
            $where_conditions[] = 'name LIKE %s';
            $search_term = '%' . $this->db->esc_like($args['search']) . '%';
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
        
        // OPTIMIZED: Select only essential fields to reduce memory
        $query = "SELECT id, name, description, start_date, end_date, is_active, 
                         questions_per_quiz, pass_score, time_limit, max_participants, created_at
                  FROM {$this->table_campaigns} 
                  {$where_clause} 
                  {$order_clause} 
                  {$limit_clause}";
        
        if (!empty($params)) {
            $campaigns = $this->db->get_results($this->db->prepare($query, $params), ARRAY_A);
        } else {
            $campaigns = $this->db->get_results($query, ARRAY_A);
        }
        
        // OPTIMIZED: Only add statistics if explicitly requested
        if ($args['include_stats'] && !empty($campaigns)) {
            foreach ($campaigns as &$campaign) {
                $campaign['stats'] = $this->get_campaign_statistics_light($campaign['id']);
            }
        }
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) FROM {$this->table_campaigns} {$where_clause}";
        if (!empty($params)) {
            $total_items = $this->db->get_var($this->db->prepare($count_query, $params));
        } else {
            $total_items = $this->db->get_var($count_query);
        }
        
        $result = array(
            'campaigns' => $campaigns ?: array(),
            'total_items' => intval($total_items),
            'total_pages' => ceil(intval($total_items) / $args['per_page']),
            'current_page' => $args['page']
        );
        
        // Cache the result
        wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_time);
        
        return $result;
    }
    
    /**
     * Get single campaign by ID - OPTIMIZED
     */
    public function get_campaign($campaign_id) {
        if (!$campaign_id) return null;
        
        $cache_key = 'campaign_' . intval($campaign_id);
        $campaign = wp_cache_get($cache_key, $this->cache_group);
        
        if ($campaign !== false && !WP_DEBUG) {
            return $campaign;
        }
        
        $campaign = $this->db->get_row(
            $this->db->prepare("SELECT * FROM {$this->table_campaigns} WHERE id = %d", $campaign_id),
            ARRAY_A
        );
        
        if ($campaign) {
            $campaign['meta_data'] = json_decode($campaign['meta_data'] ?: '{}', true);
            
            // Cache it
            wp_cache_set($cache_key, $campaign, $this->cache_group, $this->cache_time);
        }
        
        return $campaign;
    }
    
    /**
     * Create new campaign - OPTIMIZED
     */
    public function create_campaign($data) {
        $campaign_data = array(
            'name' => sanitize_text_field($data['name']),
            'slug' => $this->generate_unique_slug($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'start_date' => sanitize_text_field($data['start_date']),
            'end_date' => sanitize_text_field($data['end_date']),
            'max_participants' => intval($data['max_participants'] ?? 0),
            'questions_per_quiz' => intval($data['questions_per_quiz'] ?? 5),
            'pass_score' => intval($data['pass_score'] ?? 3),
            'time_limit' => intval($data['time_limit'] ?? 600),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'meta_data' => json_encode($data['meta_data'] ?? array()),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $result = $this->db->insert($this->table_campaigns, $campaign_data);
        
        if ($result === false) {
            return new WP_Error('create_failed', 'Failed to create campaign: ' . $this->db->last_error);
        }
        
        $campaign_id = $this->db->insert_id;
        
        // Clear cache
        wp_cache_flush_group($this->cache_group);
        
        return $campaign_id;
    }
    
    /**
     * Update campaign - OPTIMIZED
     */
    public function update_campaign($campaign_id, $data) {
        if (!$campaign_id) {
            return new WP_Error('invalid_id', 'Invalid campaign ID');
        }
        
        $update_data = array();
        
        // Only update provided fields
        $allowed_fields = array('name', 'description', 'start_date', 'end_date', 'max_participants', 
                               'questions_per_quiz', 'pass_score', 'time_limit', 'is_active');
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                switch ($field) {
                    case 'name':
                    case 'start_date':
                    case 'end_date':
                        $update_data[$field] = sanitize_text_field($data[$field]);
                        break;
                    case 'description':
                        $update_data[$field] = sanitize_textarea_field($data[$field]);
                        break;
                    case 'max_participants':
                    case 'questions_per_quiz':
                    case 'pass_score':
                    case 'time_limit':
                    case 'is_active':
                        $update_data[$field] = intval($data[$field]);
                        break;
                }
            }
        }
        
        if (!empty($update_data)) {
            $update_data['updated_at'] = current_time('mysql');
            
            $result = $this->db->update(
                $this->table_campaigns,
                $update_data,
                array('id' => $campaign_id),
                null,
                array('%d')
            );
            
            if ($result === false) {
                return new WP_Error('update_failed', 'Failed to update campaign: ' . $this->db->last_error);
            }
            
            // Clear cache
            wp_cache_flush_group($this->cache_group);
            wp_cache_delete('campaign_' . $campaign_id, $this->cache_group);
            
            return true;
        }
        
        return true;
    }
    
    /**
     * Delete campaign - OPTIMIZED
     */
    public function delete_campaign($campaign_id) {
        if (!$campaign_id) {
            return new WP_Error('invalid_id', 'Invalid campaign ID');
        }
        
        $result = $this->db->delete(
            $this->table_campaigns,
            array('id' => $campaign_id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete campaign: ' . $this->db->last_error);
        }
        
        // Clear cache
        wp_cache_flush_group($this->cache_group);
        
        return true;
    }
    
    /**
     * Get campaigns summary - OPTIMIZED (lightweight)
     */
    public function get_campaigns_summary() {
        $cache_key = 'campaigns_summary';
        $summary = wp_cache_get($cache_key, $this->cache_group);
        
        if ($summary !== false && !WP_DEBUG) {
            return $summary;
        }
        
        // Simple count queries only - no heavy JOINs
        $total = $this->db->get_var("SELECT COUNT(*) FROM {$this->table_campaigns}");
        $active = $this->db->get_var("SELECT COUNT(*) FROM {$this->table_campaigns} WHERE is_active = 1");
        
        // Check if participants table exists before querying
        $total_participants = 0;
        if ($this->db->get_var("SHOW TABLES LIKE '{$this->table_participants}'") === $this->table_participants) {
            $total_participants = $this->db->get_var("SELECT COUNT(*) FROM {$this->table_participants}");
        }
        
        $summary = array(
            'total' => intval($total),
            'active' => intval($active),
            'inactive' => intval($total) - intval($active),
            'expired' => 0, // Simplified for performance
            'total_participants' => intval($total_participants),
            'avg_completion_rate' => 0 // Simplified for performance
        );
        
        wp_cache_set($cache_key, $summary, $this->cache_group, $this->cache_time);
        return $summary;
    }
    
    /**
     * Get campaign statistics - LIGHTWEIGHT version
     */
    private function get_campaign_statistics_light($campaign_id) {
        // Only basic participant count - no complex calculations
        if ($this->db->get_var("SHOW TABLES LIKE '{$this->table_participants}'") !== $this->table_participants) {
            return array(
                'participant_count' => 0,
                'completion_rate' => 0
            );
        }
        
        $participant_count = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table_participants} WHERE campaign_id = %d",
            $campaign_id
        ));
        
        return array(
            'participant_count' => intval($participant_count),
            'completion_rate' => 0 // Calculated on-demand only
        );
    }
    
    /**
     * Get campaign statistics - FULL version (on-demand only)
     */
    public function get_campaign_statistics($campaign_id) {
        // Check if participants table exists
        if ($this->db->get_var("SHOW TABLES LIKE '{$this->table_participants}'") !== $this->table_participants) {
            return array(
                'total_participants' => 0,
                'completed_participants' => 0,
                'completion_rate' => 0,
                'average_score' => 0,
                'pass_rate' => 0,
                'gifts_distributed' => 0
            );
        }
        
        // Get participant statistics
        $participant_stats = $this->db->get_row($this->db->prepare(
            "SELECT 
                COUNT(*) as total_participants,
                COUNT(CASE WHEN quiz_status = 'completed' THEN 1 END) as completed_participants,
                AVG(CASE WHEN quiz_status = 'completed' THEN final_score END) as avg_score
             FROM {$this->table_participants} 
             WHERE campaign_id = %d",
            $campaign_id
        ));
        
        if (!$participant_stats) {
            return array(
                'total_participants' => 0,
                'completed_participants' => 0,
                'completion_rate' => 0,
                'average_score' => 0,
                'pass_rate' => 0,
                'gifts_distributed' => 0
            );
        }
        
        // Calculate rates
        $completion_rate = $participant_stats->total_participants > 0 ? 
            ($participant_stats->completed_participants / $participant_stats->total_participants) * 100 : 0;
        
        // Get campaign pass score
        $campaign = $this->get_campaign($campaign_id);
        $pass_score = $campaign ? intval($campaign['pass_score']) : 3;
        
        // Count participants who passed
        $passed_count = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table_participants} 
             WHERE campaign_id = %d AND quiz_status = 'completed' AND final_score >= %d",
            $campaign_id, $pass_score
        ));
        
        $pass_rate = $participant_stats->completed_participants > 0 ? 
            ($passed_count / $participant_stats->completed_participants) * 100 : 0;
        
        // Count gifts distributed
        $gifts_distributed = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table_participants} 
             WHERE campaign_id = %d AND gift_code IS NOT NULL",
            $campaign_id
        ));
        
        return array(
            'total_participants' => intval($participant_stats->total_participants),
            'completed_participants' => intval($participant_stats->completed_participants),
            'completion_rate' => round($completion_rate, 2),
            'average_score' => round(floatval($participant_stats->avg_score), 2),
            'pass_rate' => round($pass_rate, 2),
            'gifts_distributed' => intval($gifts_distributed)
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
        
        return intval($count) > 0;
    }
    
    /**
     * Validate campaign data
     */
    public function validate_campaign_data($data, $campaign_id = null) {
        $errors = array();
        
        if (empty($data['name'])) {
            $errors[] = 'Campaign name is required';
        }
        
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            if (strtotime($data['end_date']) <= strtotime($data['start_date'])) {
                $errors[] = 'End date must be after start date';
            }
        }
        
        if (isset($data['pass_score']) && isset($data['questions_per_quiz'])) {
            if (intval($data['pass_score']) > intval($data['questions_per_quiz'])) {
                $errors[] = 'Pass score cannot be higher than questions per quiz';
            }
        }
        
        return $errors;
    }
}