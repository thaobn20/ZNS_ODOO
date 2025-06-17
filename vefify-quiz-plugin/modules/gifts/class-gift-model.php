<?php
/**
 * Gift Model Class
 * File: modules/gifts/class-gift-model.php
 */
class Vefify_Gift_Model {
    
    private $db;
    private $table_gifts;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_gifts = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'gifts';
    }
    
    public function get_gifts($args = array()) {
        $defaults = array(
            'campaign_id' => null,
            'status' => 'active',
            'per_page' => 20,
            'page' => 1
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
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['per_page'], $offset);
        
        $query = "SELECT * FROM {$this->table_gifts} {$where_clause} ORDER BY created_at DESC {$limit_clause}";
        
        if (!empty($params)) {
            return $this->db->get_results($this->db->prepare($query, $params), ARRAY_A);
        } else {
            return $this->db->get_results($query, ARRAY_A);
        }
    }
    
    public function get_eligible_gifts($campaign_id, $score) {
        return $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->table_gifts} 
             WHERE campaign_id = %d 
             AND is_active = 1 
             AND min_score <= %d 
             AND max_score >= %d 
             AND (max_quantity IS NULL OR current_quantity < max_quantity)
             ORDER BY min_score DESC",
            $campaign_id, $score, $score
        ), ARRAY_A);
    }
    
    public function generate_gift_code($gift_id, $participant_id) {
        $gift = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->table_gifts} WHERE id = %d",
            $gift_id
        ), ARRAY_A);
        
        if (!$gift) {
            return false;
        }
        
        $prefix = $gift['gift_code_prefix'] ?: 'GIFT';
        $unique_part = strtoupper(substr(uniqid(), -6));
        $gift_code = $prefix . $unique_part;
        
        // Ensure uniqueness
        $exists = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->db->prefix}vefify_participants WHERE gift_code = %s",
            $gift_code
        ));
        
        if ($exists) {
            // Recursive call to generate another code
            return $this->generate_gift_code($gift_id, $participant_id);
        }
        
        return $gift_code;
    }
    
    public function generate_unique_gift_code($gift_id) {
        return $this->generate_gift_code($gift_id, 0);
    }
    
    public function get_gift_inventory($gift_id) {
        $gift = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->table_gifts} WHERE id = %d",
            $gift_id
        ), ARRAY_A);
        
        if (!$gift) {
            return false;
        }
        
        $distributed = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->db->prefix}vefify_participants 
             WHERE gift_code LIKE %s",
            $gift['gift_code_prefix'] . '%'
        ));
        
        return array(
            'gift_name' => $gift['gift_name'],
            'max_quantity' => $gift['max_quantity'],
            'distributed' => $distributed,
            'remaining' => $gift['max_quantity'] ? ($gift['max_quantity'] - $distributed) : 'Unlimited',
            'status' => $this->get_inventory_status($gift, $distributed)
        );
    }
    
    public function update_gift_inventory($gift_id, $quantity_used) {
        return $this->db->query($this->db->prepare(
            "UPDATE {$this->table_gifts} 
             SET current_quantity = current_quantity + %d 
             WHERE id = %d",
            $quantity_used, $gift_id
        ));
    }
    
    public function get_gift_statistics() {
        $total_gifts = $this->db->get_var("SELECT COUNT(*) FROM {$this->table_gifts}");
        
        $distributed_count = $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->db->prefix}vefify_participants WHERE gift_code IS NOT NULL"
        );
        
        $claimed_count = $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->db->prefix}vefify_participants 
             WHERE gift_code IS NOT NULL AND quiz_status = 'completed'"
        );
        
        $claim_rate = $distributed_count > 0 ? round(($claimed_count / $distributed_count) * 100, 1) : 0;
        
        return array(
            'total_gifts' => $total_gifts,
            'distributed_count' => $distributed_count,
            'claimed_count' => $claimed_count,
            'claim_rate' => $claim_rate
        );
    }
    
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