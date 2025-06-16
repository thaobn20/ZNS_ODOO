<?php
/**
 * Campaign Model - Data Layer
 * File: modules/campaigns/class-campaign-model.php
 * 
 * This class handles all database operations and data validation for campaigns.
 * It follows the same pattern as the questions module, providing a clean
 * separation between data operations and user interface.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Campaign_Model {
    
    private $db;
    private $table_name;
    private $questions_table;
    private $users_table;
    private $gifts_table;
    
    /**
     * Constructor - Initialize database connections and table names
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        
        // Define table names using the plugin's table prefix
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        $this->table_name = $table_prefix . 'campaigns';
        $this->questions_table = $table_prefix . 'questions';
        $this->users_table = $table_prefix . 'quiz_users';
        $this->gifts_table = $table_prefix . 'gifts';
    }
    
    /**
     * Get campaigns with optional filtering and pagination
     * This is like asking the database for a specific set of campaign records
     * based on various criteria, similar to how you might filter a list of products
     */
    public function get_campaigns($args = []) {
        $defaults = [
            'status' => null,          // 'active', 'inactive', or null for all
            'search' => null,          // Search in campaign name or description
            'date_from' => null,       // Filter by start date
            'date_to' => null,         // Filter by end date
            'per_page' => 20,          // Number of campaigns per page
            'page' => 1,               // Current page number
            'orderby' => 'created_at', // Sort column
            'order' => 'DESC',         // Sort direction
            'include_stats' => true    // Whether to include participation statistics
        ];
        
        $args = array_merge($defaults, $args);
        
        // Build the WHERE clause based on filtering criteria
        $where_conditions = ['1=1']; // Start with a condition that's always true
        $params = [];
        
        // Filter by active status if specified
        if ($args['status'] === 'active') {
            $where_conditions[] = 'c.is_active = 1';
        } elseif ($args['status'] === 'inactive') {
            $where_conditions[] = 'c.is_active = 0';
        }
        
        // Search functionality - look in both name and description
        if (!empty($args['search'])) {
            $where_conditions[] = '(c.name LIKE %s OR c.description LIKE %s)';
            $search_term = '%' . $this->db->esc_like($args['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // Date range filtering for campaign start dates
        if (!empty($args['date_from'])) {
            $where_conditions[] = 'DATE(c.start_date) >= %s';
            $params[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_conditions[] = 'DATE(c.end_date) <= %s';
            $params[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} c WHERE {$where_clause}";
        $total = empty($params) 
            ? $this->db->get_var($count_query)
            : $this->db->get_var($this->db->prepare($count_query, $params));
        
        // Build the main query with optional statistics
        $select_fields = 'c.*';
        $joins = '';
        
        if ($args['include_stats']) {
            // Add statistical data about campaign participation
            $select_fields .= ',
                COUNT(DISTINCT u.id) as total_participants,
                COUNT(DISTINCT CASE WHEN u.completed_at IS NOT NULL THEN u.id END) as completed_count,
                COUNT(DISTINCT q.id) as question_count,
                COUNT(DISTINCT g.id) as gift_count,
                AVG(CASE WHEN u.score > 0 THEN u.score END) as avg_score';
            
            $joins = "
                LEFT JOIN {$this->users_table} u ON c.id = u.campaign_id
                LEFT JOIN {$this->questions_table} q ON c.id = q.campaign_id AND q.is_active = 1
                LEFT JOIN {$this->gifts_table} g ON c.id = g.campaign_id AND g.is_active = 1
            ";
        }
        
        // Calculate pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        // Validate order by and order direction for security
        $allowed_orderby = ['id', 'name', 'start_date', 'end_date', 'created_at', 'is_active'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $group_by = $args['include_stats'] ? 'GROUP BY c.id' : '';
        
        $main_query = "
            SELECT {$select_fields}
            FROM {$this->table_name} c
            {$joins}
            WHERE {$where_clause}
            {$group_by}
            ORDER BY c.{$orderby} {$order}
            LIMIT %d OFFSET %d
        ";
        
        // Add pagination parameters
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        $campaigns = $this->db->get_results($this->db->prepare($main_query, $params), ARRAY_A);
        
        // Process the campaign data for better usability
        foreach ($campaigns as &$campaign) {
            $campaign = $this->process_campaign_data($campaign);
        }
        
        return [
            'campaigns' => $campaigns,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
            'per_page' => $args['per_page']
        ];
    }
    
    /**
     * Get a single campaign by ID with complete information
     */
    public function get_campaign($campaign_id) {
        $query = "
            SELECT c.*,
                COUNT(DISTINCT u.id) as total_participants,
                COUNT(DISTINCT CASE WHEN u.completed_at IS NOT NULL THEN u.id END) as completed_count,
                COUNT(DISTINCT q.id) as question_count,
                COUNT(DISTINCT g.id) as gift_count,
                AVG(CASE WHEN u.score > 0 THEN u.score END) as avg_score
            FROM {$this->table_name} c
            LEFT JOIN {$this->users_table} u ON c.id = u.campaign_id
            LEFT JOIN {$this->questions_table} q ON c.id = q.campaign_id AND q.is_active = 1
            LEFT JOIN {$this->gifts_table} g ON c.id = g.campaign_id AND g.is_active = 1
            WHERE c.id = %d
            GROUP BY c.id
        ";
        
        $campaign = $this->db->get_row($this->db->prepare($query, $campaign_id), ARRAY_A);
        
        if (!$campaign) {
            return false;
        }
        
        return $this->process_campaign_data($campaign);
    }
    
    /**
     * Create a new campaign with validation
     */
    public function create_campaign($data) {
        // Validate the campaign data first
        $validation_result = $this->validate_campaign_data($data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        // Prepare the data for database insertion
        $campaign_data = $this->prepare_campaign_data($data);
        
        // Check for duplicate slug
        if ($this->slug_exists($campaign_data['slug'])) {
            $campaign_data['slug'] = $this->generate_unique_slug($campaign_data['slug']);
        }
        
        // Insert the campaign into the database
        $result = $this->db->insert($this->table_name, $campaign_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create campaign: ' . $this->db->last_error);
        }
        
        $campaign_id = $this->db->insert_id;
        
        // Log the campaign creation for audit purposes
        do_action('vefify_campaign_created', $campaign_id, $campaign_data);
        
        return $campaign_id;
    }
    
    /**
     * Update an existing campaign
     */
    public function update_campaign($campaign_id, $data) {
        // First verify the campaign exists
        $existing_campaign = $this->get_campaign($campaign_id);
        if (!$existing_campaign) {
            return new WP_Error('not_found', 'Campaign not found');
        }
        
        // Validate the updated data
        $validation_result = $this->validate_campaign_data($data, $campaign_id);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        // Prepare the data for update
        $campaign_data = $this->prepare_campaign_data($data);
        $campaign_data['updated_at'] = current_time('mysql');
        
        // Check for slug conflicts (excluding current campaign)
        if (isset($campaign_data['slug']) && $this->slug_exists($campaign_data['slug'], $campaign_id)) {
            $campaign_data['slug'] = $this->generate_unique_slug($campaign_data['slug'], $campaign_id);
        }
        
        // Update the campaign
        $result = $this->db->update(
            $this->table_name,
            $campaign_data,
            ['id' => $campaign_id],
            null, // Let WordPress determine the format
            ['%d'] // ID is integer
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update campaign: ' . $this->db->last_error);
        }
        
        // Log the campaign update
        do_action('vefify_campaign_updated', $campaign_id, $campaign_data, $existing_campaign);
        
        return true;
    }
    
    /**
     * Delete a campaign with safety checks
     */
    public function delete_campaign($campaign_id) {
        // Check if campaign exists
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return new WP_Error('not_found', 'Campaign not found');
        }
        
        // Check if campaign has participants - we should be careful about deleting campaigns with data
        if ($campaign['total_participants'] > 0) {
            return new WP_Error('has_participants', 'Cannot delete campaign with existing participants. Consider deactivating instead.');
        }
        
        // Begin transaction to ensure data consistency
        $this->db->query('START TRANSACTION');
        
        try {
            // Delete related questions first (if any are campaign-specific)
            $this->db->delete($this->questions_table, ['campaign_id' => $campaign_id]);
            
            // Delete related gifts
            $this->db->delete($this->gifts_table, ['campaign_id' => $campaign_id]);
            
            // Finally delete the campaign itself
            $result = $this->db->delete($this->table_name, ['id' => $campaign_id], ['%d']);
            
            if ($result === false) {
                throw new Exception('Failed to delete campaign');
            }
            
            $this->db->query('COMMIT');
            
            // Log the campaign deletion
            do_action('vefify_campaign_deleted', $campaign_id, $campaign);
            
            return true;
            
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            return new WP_Error('db_error', 'Failed to delete campaign: ' . $e->getMessage());
        }
    }
    
    /**
     * Get campaign statistics for analytics
     */
    public function get_campaign_statistics($campaign_id = null) {
        $where_clause = $campaign_id ? 'WHERE c.id = %d' : '';
        $params = $campaign_id ? [$campaign_id] : [];
        
        $query = "
            SELECT 
                COUNT(DISTINCT c.id) as total_campaigns,
                COUNT(DISTINCT CASE WHEN c.is_active = 1 THEN c.id END) as active_campaigns,
                COUNT(DISTINCT u.id) as total_participants,
                COUNT(DISTINCT CASE WHEN u.completed_at IS NOT NULL THEN u.id END) as completed_participants,
                COUNT(DISTINCT CASE WHEN u.gift_id IS NOT NULL THEN u.id END) as participants_with_gifts,
                AVG(CASE WHEN u.score > 0 THEN u.score END) as overall_avg_score,
                COUNT(DISTINCT q.id) as total_questions,
                COUNT(DISTINCT g.id) as total_gifts
            FROM {$this->table_name} c
            LEFT JOIN {$this->users_table} u ON c.id = u.campaign_id
            LEFT JOIN {$this->questions_table} q ON c.id = q.campaign_id AND q.is_active = 1
            LEFT JOIN {$this->gifts_table} g ON c.id = g.campaign_id AND g.is_active = 1
            {$where_clause}
        ";
        
        $result = empty($params) 
            ? $this->db->get_row($query, ARRAY_A)
            : $this->db->get_row($this->db->prepare($query, $params), ARRAY_A);
        
        // Calculate additional metrics
        if ($result) {
            $result['completion_rate'] = $result['total_participants'] > 0 
                ? round(($result['completed_participants'] / $result['total_participants']) * 100, 2)
                : 0;
            
            $result['gift_rate'] = $result['completed_participants'] > 0
                ? round(($result['participants_with_gifts'] / $result['completed_participants']) * 100, 2)
                : 0;
        }
        
        return $result;
    }
    
    /**
     * Get campaigns that are currently active and accepting participants
     */
    public function get_active_campaigns() {
        $query = "
            SELECT c.*, COUNT(DISTINCT u.id) as current_participants
            FROM {$this->table_name} c
            LEFT JOIN {$this->users_table} u ON c.id = u.campaign_id
            WHERE c.is_active = 1 
            AND c.start_date <= NOW() 
            AND c.end_date >= NOW()
            GROUP BY c.id
            HAVING (c.max_participants IS NULL OR current_participants < c.max_participants)
            ORDER BY c.start_date ASC
        ";
        
        $campaigns = $this->db->get_results($query, ARRAY_A);
        
        foreach ($campaigns as &$campaign) {
            $campaign = $this->process_campaign_data($campaign);
        }
        
        return $campaigns;
    }
    
    /**
     * Duplicate an existing campaign
     */
    public function duplicate_campaign($campaign_id) {
        $original = $this->get_campaign($campaign_id);
        if (!$original) {
            return new WP_Error('not_found', 'Original campaign not found');
        }
        
        // Prepare data for the new campaign
        $new_data = [
            'name' => $original['name'] . ' (Copy)',
            'description' => $original['description'],
            'start_date' => current_time('mysql'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')), // Default to 30 days from now
            'is_active' => 0, // Start as inactive
            'max_participants' => $original['max_participants'],
            'allow_retake' => $original['allow_retake'],
            'questions_per_quiz' => $original['questions_per_quiz'],
            'time_limit' => $original['time_limit'],
            'pass_score' => $original['pass_score'],
            'meta_data' => $original['meta_data']
        ];
        
        return $this->create_campaign($new_data);
    }
    
    /**
     * Check if a campaign slug exists (excluding a specific campaign ID)
     */
    private function slug_exists($slug, $exclude_id = null) {
        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE slug = %s";
        $params = [$slug];
        
        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        return $this->db->get_var($this->db->prepare($query, $params)) > 0;
    }
    
    /**
     * Generate a unique slug for a campaign
     */
    private function generate_unique_slug($base_slug, $exclude_id = null) {
        $counter = 1;
        $new_slug = $base_slug;
        
        while ($this->slug_exists($new_slug, $exclude_id)) {
            $new_slug = $base_slug . '-' . $counter;
            $counter++;
        }
        
        return $new_slug;
    }
    
    /**
     * Validate campaign data before saving
     */
    private function validate_campaign_data($data, $campaign_id = null) {
        $errors = [];
        
        // Required fields validation
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
            $start = strtotime($data['start_date']);
            $end = strtotime($data['end_date']);
            
            if ($start >= $end) {
                $errors[] = 'End date must be after start date';
            }
        }
        
        // Numeric validations
        if (isset($data['questions_per_quiz']) && ($data['questions_per_quiz'] < 1 || $data['questions_per_quiz'] > 50)) {
            $errors[] = 'Questions per quiz must be between 1 and 50';
        }
        
        if (isset($data['pass_score']) && $data['pass_score'] < 1) {
            $errors[] = 'Pass score must be at least 1';
        }
        
        if (isset($data['time_limit']) && !empty($data['time_limit']) && $data['time_limit'] < 60) {
            $errors[] = 'Time limit must be at least 60 seconds';
        }
        
        if (isset($data['max_participants']) && !empty($data['max_participants']) && $data['max_participants'] < 1) {
            $errors[] = 'Maximum participants must be at least 1';
        }
        
        // Return errors if any exist
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors), $errors);
        }
        
        return true;
    }
    
    /**
     * Prepare campaign data for database insertion/update
     */
    private function prepare_campaign_data($data) {
        $prepared = [];
        
        // Handle required fields
        if (isset($data['name'])) {
            $prepared['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['slug'])) {
            $prepared['slug'] = sanitize_title($data['slug']);
        } elseif (isset($data['name'])) {
            $prepared['slug'] = sanitize_title($data['name']);
        }
        
        if (isset($data['description'])) {
            $prepared['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['start_date'])) {
            $prepared['start_date'] = sanitize_text_field($data['start_date']);
        }
        
        if (isset($data['end_date'])) {
            $prepared['end_date'] = sanitize_text_field($data['end_date']);
        }
        
        // Handle boolean and numeric fields
        if (isset($data['is_active'])) {
            $prepared['is_active'] = $data['is_active'] ? 1 : 0;
        }
        
        if (isset($data['allow_retake'])) {
            $prepared['allow_retake'] = $data['allow_retake'] ? 1 : 0;
        }
        
        if (isset($data['questions_per_quiz'])) {
            $prepared['questions_per_quiz'] = intval($data['questions_per_quiz']);
        }
        
        if (isset($data['pass_score'])) {
            $prepared['pass_score'] = intval($data['pass_score']);
        }
        
        if (isset($data['time_limit'])) {
            $prepared['time_limit'] = !empty($data['time_limit']) ? intval($data['time_limit']) : null;
        }
        
        if (isset($data['max_participants'])) {
            $prepared['max_participants'] = !empty($data['max_participants']) ? intval($data['max_participants']) : null;
        }
        
        // Handle metadata
        if (isset($data['meta_data'])) {
            if (is_array($data['meta_data'])) {
                $prepared['meta_data'] = json_encode($data['meta_data']);
            } else {
                $prepared['meta_data'] = $data['meta_data'];
            }
        }
        
        return $prepared;
    }
    
    /**
     * Process campaign data after retrieval from database
     */
    private function process_campaign_data($campaign) {
        if (!$campaign) {
            return $campaign;
        }
        
        // Parse metadata
        if (!empty($campaign['meta_data'])) {
            $campaign['meta_data'] = json_decode($campaign['meta_data'], true);
        }
        
        // Format dates for display
        $campaign['start_date_formatted'] = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $campaign['start_date']);
        $campaign['end_date_formatted'] = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $campaign['end_date']);
        
        // Add status information
        $now = current_time('mysql');
        if ($campaign['is_active']) {
            if ($campaign['start_date'] > $now) {
                $campaign['status'] = 'scheduled';
                $campaign['status_label'] = 'Scheduled';
            } elseif ($campaign['end_date'] < $now) {
                $campaign['status'] = 'expired';
                $campaign['status_label'] = 'Expired';
            } else {
                $campaign['status'] = 'active';
                $campaign['status_label'] = 'Active';
            }
        } else {
            $campaign['status'] = 'inactive';
            $campaign['status_label'] = 'Inactive';
        }
        
        // Calculate participation rate if we have the data
        if (isset($campaign['total_participants']) && isset($campaign['completed_count'])) {
            $campaign['completion_rate'] = $campaign['total_participants'] > 0 
                ? round(($campaign['completed_count'] / $campaign['total_participants']) * 100, 1)
                : 0;
        }
        
        return $campaign;
    }
}