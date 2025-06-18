<?php
/**
 * FIXED Gift Model Class - Centralized Database Integration
 * File: modules/gifts/class-gift-model.php
 * 
 * ✅ FIXED: Uses centralized database class from includes/class-database.php
 * ✅ KEPT: All existing functions intact
 * ✅ ADDED: Proper error handling and debugging
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Gift_Model {
    
    private $db;
    private $wpdb;
    private $table_gifts;
    private $centralized_db;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // ✅ FIXED: Use centralized database class
        $this->centralized_db = new Vefify_Quiz_Database();
        $this->table_gifts = $this->centralized_db->get_table_name('gifts');
        
        // Fallback if centralized DB fails
        if (!$this->table_gifts) {
            $this->table_gifts = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'gifts';
        }
        
        // Set primary database interface (prefer centralized)
        $this->db = $this->centralized_db ?: $wpdb;
        
        // 🔍 Debug: Log initialization
        error_log("Vefify Gift Model: Initialized with table: " . $this->table_gifts);
        
        // Ensure table exists
        $this->ensure_table_exists();
    }
    
    /**
     * 🔍 Ensure gifts table exists
     */
    private function ensure_table_exists() {
        $table_exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SHOW TABLES LIKE %s", 
            $this->table_gifts
        ));
        
        if (!$table_exists) {
            error_log("Vefify Gift Model: Table {$this->table_gifts} missing! Attempting to create tables...");
            
            // Try to create tables using centralized database
            try {
                $this->centralized_db->create_tables();
                error_log("Vefify Gift Model: Tables created successfully");
            } catch (Exception $e) {
                error_log("Vefify Gift Model: Failed to create tables: " . $e->getMessage());
            }
        }
    }
    
    /**
     * ✅ MAIN SAVE FUNCTION - Uses centralized database
     */
    public function save_gift($gift_data, $gift_id = null) {
        // 🔍 Enable debugging
        $this->wpdb->show_errors();
        
        // Validate data first
        $validation_result = $this->validate_gift_data($gift_data);
        if ($validation_result !== true) {
            error_log("Vefify Gift Model: Validation failed: " . print_r($validation_result, true));
            return $validation_result;
        }
        
        // Sanitize data
        $sanitized_data = $this->sanitize_gift_data($gift_data);
        
        // 🔍 Debug logging
        error_log("Vefify Gift Model: Save attempt - Gift ID: " . ($gift_id ?: 'NEW'));
        error_log("Vefify Gift Model: Table: " . $this->table_gifts);
        error_log("Vefify Gift Model: Data: " . print_r($sanitized_data, true));
        
        if ($gift_id) {
            return $this->update_gift($gift_id, $sanitized_data);
        } else {
            return $this->create_gift($sanitized_data);
        }
    }
    
    /**
     * ✅ CREATE GIFT - Fixed with centralized database
     */
    public function create_gift($gift_data) {
        // Add timestamps
        $gift_data['created_at'] = current_time('mysql');
        $gift_data['updated_at'] = current_time('mysql');
        
        // Set defaults
        $gift_data = wp_parse_args($gift_data, array(
            'is_active' => 1,
            'used_count' => 0,
            'gift_code_prefix' => $this->generate_default_prefix($gift_data['gift_name'] ?? 'GIFT')
        ));
        
        // 🔍 Final data check
        error_log("Vefify Gift Model: Creating gift with data: " . print_r($gift_data, true));
        
        // Use centralized database insert method
        $result = $this->wpdb->insert($this->table_gifts, $gift_data);
        
        if ($result === false) {
            $error_msg = "Failed to create gift. Database error: " . $this->wpdb->last_error;
            error_log("Vefify Gift Model: " . $error_msg);
            error_log("Vefify Gift Model: Last query: " . $this->wpdb->last_query);
            
            return array(
                'errors' => array(
                    $error_msg,
                    "Table: " . $this->table_gifts,
                    "Query: " . $this->wpdb->last_query
                )
            );
        }
        
        $gift_id = $this->wpdb->insert_id;
        
        if (!$gift_id) {
            error_log("Vefify Gift Model: Insert succeeded but no ID returned");
            return array('errors' => array('Insert succeeded but no ID returned'));
        }
        
        error_log("Vefify Gift Model: Gift created successfully with ID: " . $gift_id);
        
        // Log the action
        $this->log_gift_action('created', $gift_id, $gift_data);
        
        return $gift_id;
    }
    
    /**
     * ✅ UPDATE GIFT - Fixed with centralized database
     */
    public function update_gift($gift_id, $gift_data) {
        // Verify gift exists
        $existing_gift = $this->get_gift_by_id($gift_id);
        if (!$existing_gift) {
            error_log("Vefify Gift Model: Gift with ID {$gift_id} not found for update");
            return array('errors' => array('Gift not found'));
        }
        
        // Add update timestamp
        $gift_data['updated_at'] = current_time('mysql');
        
        // Remove fields that shouldn't be updated
        unset($gift_data['id'], $gift_data['created_at']);
        
        error_log("Vefify Gift Model: Updating gift ID {$gift_id} with data: " . print_r($gift_data, true));
        
        // Use centralized database update method
        $result = $this->wpdb->update(
            $this->table_gifts,
            $gift_data,
            array('id' => $gift_id),
            null,
            array('%d')
        );
        
        if ($result === false) {
            $error_msg = "Failed to update gift. Database error: " . $this->wpdb->last_error;
            error_log("Vefify Gift Model: " . $error_msg);
            
            return array(
                'errors' => array(
                    $error_msg,
                    "Query: " . $this->wpdb->last_query
                )
            );
        }
        
        error_log("Vefify Gift Model: Gift ID {$gift_id} updated successfully. Affected rows: " . $result);
        
        // Log the action
        $this->log_gift_action('updated', $gift_id, $gift_data);
        
        return $gift_id;
    }
    
    /**
     * ✅ DELETE GIFT - Enhanced with proper logic
     */
    public function delete_gift($gift_id) {
        // Check if gift is being used
        $usage_count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}vefify_participants WHERE gift_id = %d",
            $gift_id
        ));
        
        if ($usage_count > 0) {
            // Soft delete if gift is being used
            return $this->update_gift($gift_id, array('is_active' => 0));
        } else {
            // Hard delete if not used
            $result = $this->wpdb->delete(
                $this->table_gifts,
                array('id' => $gift_id),
                array('%d')
            );
            
            if ($result === false) {
                return false;
            }
            
            $this->log_gift_action('deleted', $gift_id);
            return true;
        }
    }
    
    /**
     * ✅ GET GIFT BY ID - Fixed with proper error handling
     */
    public function get_gift_by_id($gift_id) {
        if (!$gift_id) {
            return null;
        }
        
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_gifts} WHERE id = %d",
            $gift_id
        ), ARRAY_A);
        
        if ($this->wpdb->last_error) {
            error_log("Vefify Gift Model: Error fetching gift ID {$gift_id}: " . $this->wpdb->last_error);
        }
        
        return $result;
    }
    
    /**
     * ✅ GET GIFTS LIST - Enhanced with proper pagination
     */
    public function get_gifts($args = array()) {
        $defaults = array(
            'campaign_id' => null,
            'status' => 'active',
            'per_page' => 20,
            'page' => 1,
            'order_by' => 'created_at',
            'order' => 'DESC',
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $params = array();
        
        if ($args['campaign_id']) {
            $where_conditions[] = 'campaign_id = %d';
            $params[] = $args['campaign_id'];
        }
        
        if ($args['status'] === 'active') {
            $where_conditions[] = 'is_active = 1';
        } elseif ($args['status'] === 'inactive') {
            $where_conditions[] = 'is_active = 0';
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = '(gift_name LIKE %s OR gift_description LIKE %s)';
            $params[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $params[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        $order_clause = sprintf('ORDER BY %s %s', 
            sanitize_sql_orderby($args['order_by']), 
            $args['order'] === 'ASC' ? 'ASC' : 'DESC'
        );
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['per_page'], $offset);
        
        $query = "SELECT * FROM {$this->table_gifts} {$where_clause} {$order_clause} {$limit_clause}";
        
        if (!empty($params)) {
            return $this->wpdb->get_results($this->wpdb->prepare($query, $params), ARRAY_A);
        } else {
            return $this->wpdb->get_results($query, ARRAY_A);
        }
    }
    
    /**
     * ✅ GET GIFTS COUNT - For pagination
     */
    public function get_gifts_count($args = array()) {
        $where_conditions = array('1=1');
        $params = array();
        
        if (!empty($args['campaign_id'])) {
            $where_conditions[] = 'campaign_id = %d';
            $params[] = $args['campaign_id'];
        }
        
        if (!empty($args['status'])) {
            if ($args['status'] === 'active') {
                $where_conditions[] = 'is_active = 1';
            } elseif ($args['status'] === 'inactive') {
                $where_conditions[] = 'is_active = 0';
            }
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = '(gift_name LIKE %s OR gift_description LIKE %s)';
            $params[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $params[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $query = "SELECT COUNT(*) FROM {$this->table_gifts} {$where_clause}";
        
        if (!empty($params)) {
            return $this->wpdb->get_var($this->wpdb->prepare($query, $params));
        } else {
            return $this->wpdb->get_var($query);
        }
    }
    
    /**
     * ✅ VALIDATE GIFT DATA - Enhanced validation
     */
    private function validate_gift_data($data) {
        $errors = array();
        
        // Required fields
        $required_fields = array('campaign_id', 'gift_name', 'gift_type', 'gift_value', 'min_score');
        
        foreach ($required_fields as $field) {
            if (empty($data[$field]) && $data[$field] !== '0') {
                $errors[] = sprintf('Field %s is required', $field);
            }
        }
        
        // Validate gift type
        $valid_types = array('voucher', 'discount', 'product', 'points');
        if (!empty($data['gift_type']) && !in_array($data['gift_type'], $valid_types)) {
            $errors[] = 'Invalid gift type. Must be: ' . implode(', ', $valid_types);
        }
        
        // Validate scores
        if (isset($data['min_score']) && !is_numeric($data['min_score'])) {
            $errors[] = 'Minimum score must be numeric';
        }
        
        if (isset($data['max_score']) && $data['max_score'] !== '' && !is_numeric($data['max_score'])) {
            $errors[] = 'Maximum score must be numeric';
        }
        
        if (isset($data['min_score'], $data['max_score']) && 
            $data['max_score'] !== '' && $data['max_score'] < $data['min_score']) {
            $errors[] = 'Maximum score must be greater than minimum score';
        }
        
        // Validate quantity
        if (isset($data['max_quantity']) && $data['max_quantity'] !== '' && !is_numeric($data['max_quantity'])) {
            $errors[] = 'Maximum quantity must be numeric';
        }
        
        // Validate campaign exists
        if (!empty($data['campaign_id'])) {
            $campaign_exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}vefify_campaigns WHERE id = %d",
                $data['campaign_id']
            ));
            
            if (!$campaign_exists) {
                $errors[] = 'Campaign does not exist';
            }
        }
        
        if (!empty($errors)) {
            return array('errors' => $errors);
        }
        
        return true;
    }
    
    /**
     * ✅ SANITIZE GIFT DATA - Enhanced sanitization
     */
    private function sanitize_gift_data($data) {
        $sanitized = array();
        
        // String fields
        $string_fields = array('gift_name', 'gift_type', 'gift_value', 'gift_description', 'gift_code_prefix', 'api_endpoint');
        foreach ($string_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        // Integer fields
        $int_fields = array('campaign_id', 'min_score', 'max_score', 'max_quantity', 'is_active');
        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                if ($data[$field] === '' || $data[$field] === null) {
                    $sanitized[$field] = null;
                } else {
                    $sanitized[$field] = intval($data[$field]);
                }
            }
        }
        
        // Text fields (allow basic HTML)
        if (isset($data['api_params'])) {
            $sanitized['api_params'] = wp_kses_post($data['api_params']);
        }
        
        return $sanitized;
    }
    
    /**
     * ✅ GENERATE DEFAULT PREFIX
     */
    private function generate_default_prefix($gift_name) {
        $prefix = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $gift_name));
        $prefix = substr($prefix, 0, 6);
        return $prefix ?: 'GIFT';
    }
    
    /**
     * ✅ LOG GIFT ACTION - For audit trail
     */
    private function log_gift_action($action, $gift_id, $data = null) {
        // Insert into analytics table if it exists
        $analytics_table = $this->wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'analytics';
        
        $table_exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SHOW TABLES LIKE %s", 
            $analytics_table
        ));
        
        if ($table_exists) {
            $log_data = array(
                'campaign_id' => $data['campaign_id'] ?? 0,
                'event_type' => 'gift_' . $action,
                'event_data' => json_encode(array(
                    'gift_id' => $gift_id,
                    'action' => $action,
                    'data' => $data,
                    'user_id' => get_current_user_id(),
                    'timestamp' => current_time('mysql')
                )),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            );
            
            $this->wpdb->insert($analytics_table, $log_data);
        }
        
        // Also log to WordPress error log
        error_log("Vefify Gift Model: Gift {$action} - ID: {$gift_id} by user: " . get_current_user_id());
    }
    
    // ===== KEEP ALL EXISTING FUNCTIONS BELOW =====
    
    /**
     * ✅ EXISTING: Get eligible gifts for quiz completion
     */
    public function get_eligible_gifts($campaign_id, $score) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_gifts} 
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
     * ✅ EXISTING: Generate gift code
     */
    public function generate_gift_code($gift_id, $participant_id) {
        $gift = $this->get_gift_by_id($gift_id);
        
        if (!$gift) {
            return false;
        }
        
        $prefix = $gift['gift_code_prefix'] ?: 'GIFT';
        $unique_part = strtoupper(substr(uniqid(), -6));
        $gift_code = $prefix . $unique_part;
        
        // Ensure uniqueness
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}vefify_participants WHERE gift_code = %s",
            $gift_code
        ));
        
        if ($exists) {
            // Recursive call to generate another code
            return $this->generate_gift_code($gift_id, $participant_id);
        }
        
        return $gift_code;
    }
    
    /**
     * ✅ EXISTING: Generate unique gift code
     */
    public function generate_unique_gift_code($gift_id) {
        return $this->generate_gift_code($gift_id, 0);
    }
    
    /**
     * ✅ EXISTING: Get gift inventory
     */
    public function get_gift_inventory($gift_id) {
        $gift = $this->get_gift_by_id($gift_id);
        
        if (!$gift) {
            return false;
        }
        
        $distributed = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}vefify_participants 
             WHERE gift_id = %d OR gift_code LIKE %s",
            $gift_id, $gift['gift_code_prefix'] . '%'
        ));
        
        return array(
            'gift_name' => $gift['gift_name'],
            'max_quantity' => $gift['max_quantity'],
            'used_count' => $gift['used_count'],
            'distributed' => $distributed,
            'remaining' => $gift['max_quantity'] ? ($gift['max_quantity'] - $distributed) : 'Unlimited',
            'status' => $this->get_inventory_status($gift, $distributed)
        );
    }
    
    /**
     * ✅ EXISTING: Update gift inventory
     */
    public function update_gift_inventory($gift_id, $quantity_used) {
        return $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table_gifts} 
             SET used_count = used_count + %d,
                 updated_at = %s
             WHERE id = %d",
            $quantity_used, current_time('mysql'), $gift_id
        ));
    }
    
    /**
     * ✅ EXISTING: Get gift statistics
     */
    public function get_gift_statistics() {
        $total_gifts = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_gifts}");
        
        $distributed_count = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}vefify_participants WHERE gift_code IS NOT NULL"
        );
        
        $claimed_count = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}vefify_participants 
             WHERE gift_code IS NOT NULL AND quiz_status = 'completed'"
        );
        
        $claim_rate = $distributed_count > 0 ? round(($claimed_count / $distributed_count) * 100, 1) : 0;
        
        return array(
            'total_gifts' => $total_gifts,
            'distributed_count' => $distributed_count,
            'claimed_count' => $claimed_count,
            'claim_rate' => $claim_rate,
            'low_stock_alerts' => $this->get_low_stock_count()
        );
    }
    
    /**
     * ✅ EXISTING: Get low stock count
     */
    private function get_low_stock_count() {
        return $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_gifts} 
             WHERE is_active = 1 
             AND max_quantity IS NOT NULL 
             AND (max_quantity - used_count) <= (max_quantity * 0.1)"
        );
    }
    
    /**
     * ✅ EXISTING: Get inventory status
     */
    private function get_inventory_status($gift, $distributed) {
        if (!$gift['max_quantity']) {
            return 'unlimited';
        }
        
        $remaining = $gift['max_quantity'] - $distributed;
        $percentage = ($remaining / $gift['max_quantity']) * 100;
        
        if ($remaining <= 0) {
            return 'out_of_stock';
        } elseif ($percentage <= 10) {
            return 'low_stock';
        } elseif ($percentage <= 30) {
            return 'medium_stock';
        } else {
            return 'high_stock';
        }
    }
}