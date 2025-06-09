<?php
/**
 * Enhanced Database Operations for Advanced Quiz Manager
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
    
    // QUESTION METHODS
    
    public function get_campaign_questions($campaign_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}aqm_questions 
             WHERE campaign_id = %d 
             ORDER BY order_index ASC",
            $campaign_id
        ));
    }
    
    public function create_question($campaign_id, $data) {
        $question_data = array_merge($data, array(
            'campaign_id' => $campaign_id,
            'created_at' => current_time('mysql')
        ));
        
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'aqm_questions',
            $question_data
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
            array('id' => $question_id)
        );
    }
    
    public function delete_question($question_id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'aqm_questions',
            array('id' => $question_id),
            array('%d')
        );
    }
    
    public function reorder_questions($campaign_id, $question_orders) {
        foreach ($question_orders as $question_id => $order) {
            $this->wpdb->update(
                $this->wpdb->prefix . 'aqm_questions',
                array('order_index' => intval($order)),
                array('id' => intval($question_id), 'campaign_id' => $campaign_id),
                array('%d'),
                array('%d', '%d')
            );
        }
    }
    
    // GIFT METHODS
    
    public function get_campaign_gifts($campaign_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}aqm_gifts 
             WHERE campaign_id = %d AND quantity > 0
             ORDER BY created_at ASC",
            $campaign_id
        ));
    }
    
    public function create_gift($campaign_id, $data) {
        $gift_data = array_merge($data, array(
            'campaign_id' => $campaign_id,
            'created_at' => current_time('mysql')
        ));
        
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'aqm_gifts',
            $gift_data
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
            array('id' => $gift_id)
        );
    }
    
    public function delete_gift($gift_id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'aqm_gifts',
            array('id' => $gift_id),
            array('%d')
        );
    }
    
    public function decrease_gift_quantity($gift_id) {
        return $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->wpdb->prefix}aqm_gifts 
             SET quantity = quantity - 1 
             WHERE id = %d AND quantity > 0",
            $gift_id
        ));
    }
    
    // RESPONSE METHODS
    
    public function check_phone_participation($phone, $campaign_id) {
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_responses 
             WHERE phone_number = %s AND campaign_id = %d",
            $phone,
            $campaign_id
        ));
        
        return $count > 0;
    }
    
    public function save_quiz_response($campaign_id, $user_data, $answers, $score, $gift = null) {
        $response_data = array(
            'campaign_id' => $campaign_id,
            'full_name' => $user_data['full_name'],
            'phone_number' => $user_data['phone_number'],
            'province' => $user_data['province'],
            'pharmacy_code' => $user_data['pharmacy_code'],
            'answers' => json_encode($answers),
            'score' => $score,
            'gift_code' => $gift ? $gift['code'] : null,
            'gift_data' => $gift ? json_encode($gift) : null,
            'submitted_at' => current_time('mysql'),
            'ip_address' => $this->get_client_ip()
        );
        
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'aqm_responses',
            $response_data
        );
        
        if ($result !== false) {
            return $this->wpdb->insert_id;
        }
        
        return false;
    }
    
    public function get_campaign_responses($campaign_id, $limit = 100) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}aqm_responses 
             WHERE campaign_id = %d 
             ORDER BY submitted_at DESC 
             LIMIT %d",
            $campaign_id,
            $limit
        ));
    }
    
    public function get_response_analytics($campaign_id) {
        $total_responses = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_responses WHERE campaign_id = %d",
            $campaign_id
        ));
        
        $avg_score = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT AVG(score) FROM {$this->wpdb->prefix}aqm_responses WHERE campaign_id = %d",
            $campaign_id
        ));
        
        $gifts_claimed = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_responses 
             WHERE campaign_id = %d AND gift_code IS NOT NULL",
            $campaign_id
        ));
        
        return array(
            'total_responses' => $total_responses,
            'average_score' => round($avg_score, 2),
            'gifts_claimed' => $gifts_claimed,
            'completion_rate' => 100 // Since we only save completed responses
        );
    }
    
    // PROVINCE METHODS
    
    public function get_provinces() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->prefix}aqm_provinces ORDER BY name ASC"
        );
    }
    
    public function get_districts_by_province($province_code) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}aqm_districts 
             WHERE province_code = %s ORDER BY name ASC",
            $province_code
        ));
    }
    
    public function get_wards_by_district($district_code) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}aqm_wards 
             WHERE district_code = %s ORDER BY name ASC",
            $district_code
        ));
    }
    
    public function import_provinces_data($provinces_data) {
        $provinces_imported = 0;
        $districts_imported = 0;
        $wards_imported = 0;
        
        // Clear existing data
        $this->wpdb->query("TRUNCATE TABLE {$this->wpdb->prefix}aqm_provinces");
        $this->wpdb->query("TRUNCATE TABLE {$this->wpdb->prefix}aqm_districts");
        $this->wpdb->query("TRUNCATE TABLE {$this->wpdb->prefix}aqm_wards");
        
        foreach ($provinces_data as $province) {
            // Insert province
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'aqm_provinces',
                array(
                    'code' => sanitize_text_field($province['code']),
                    'name' => sanitize_text_field($province['name']),
                    'name_en' => sanitize_text_field($province['name_en'] ?? ''),
                    'full_name' => sanitize_text_field($province['full_name'] ?? ''),
                    'full_name_en' => sanitize_text_field($province['full_name_en'] ?? ''),
                    'code_name' => sanitize_text_field($province['code_name'] ?? '')
                )
            );
            
            if ($result) {
                $provinces_imported++;
                
                // Insert districts
                if (isset($province['districts']) && is_array($province['districts'])) {
                    foreach ($province['districts'] as $district) {
                        $district_result = $this->wpdb->insert(
                            $this->wpdb->prefix . 'aqm_districts',
                            array(
                                'code' => sanitize_text_field($district['code']),
                                'name' => sanitize_text_field($district['name']),
                                'name_en' => sanitize_text_field($district['name_en'] ?? ''),
                                'full_name' => sanitize_text_field($district['full_name'] ?? ''),
                                'province_code' => sanitize_text_field($province['code'])
                            )
                        );
                        
                        if ($district_result) {
                            $districts_imported++;
                            
                            // Insert wards
                            if (isset($district['wards']) && is_array($district['wards'])) {
                                foreach ($district['wards'] as $ward) {
                                    $ward_result = $this->wpdb->insert(
                                        $this->wpdb->prefix . 'aqm_wards',
                                        array(
                                            'code' => sanitize_text_field($ward['code']),
                                            'name' => sanitize_text_field($ward['name']),
                                            'name_en' => sanitize_text_field($ward['name_en'] ?? ''),
                                            'full_name' => sanitize_text_field($ward['full_name'] ?? ''),
                                            'district_code' => sanitize_text_field($district['code'])
                                        )
                                    );
                                    
                                    if ($ward_result) {
                                        $wards_imported++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return array(
            'provinces' => $provinces_imported,
            'districts' => $districts_imported,
            'wards' => $wards_imported
        );
    }
    
    // UTILITY METHODS
    
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
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
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    public function get_dashboard_stats() {
        $stats = array();
        
        // Total campaigns
        $stats['total_campaigns'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_campaigns"
        );
        
        // Active campaigns
        $stats['active_campaigns'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_campaigns WHERE status = 'active'"
        );
        
        // Total responses
        $stats['total_responses'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_responses"
        );
        
        // Recent responses (last 24 hours)
        $stats['recent_responses'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_responses 
             WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        // Total gifts claimed
        $stats['gifts_claimed'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_responses 
             WHERE gift_code IS NOT NULL"
        );
        
        return $stats;
    }
    
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Campaigns table
        $campaigns_table = $wpdb->prefix . 'aqm_campaigns';
        $sql_campaigns = "CREATE TABLE $campaigns_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            status enum('active','inactive','draft') DEFAULT 'draft',
            start_date datetime,
            end_date datetime,
            max_participants int(11) DEFAULT 0,
            settings longtext,
            created_by int(11),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Questions table
        $questions_table = $wpdb->prefix . 'aqm_questions';
        $sql_questions = "CREATE TABLE $questions_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            question_text text NOT NULL,
            question_type enum('multiple_choice','single_choice','text','number') DEFAULT 'multiple_choice',
            options longtext,
            is_required tinyint(1) DEFAULT 1,
            order_index int(11) DEFAULT 0,
            points int(11) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY order_index (order_index),
            FOREIGN KEY (campaign_id) REFERENCES $campaigns_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Responses table
        $responses_table = $wpdb->prefix . 'aqm_responses';
        $sql_responses = "CREATE TABLE $responses_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            full_name varchar(255) NOT NULL,
            phone_number varchar(20) NOT NULL,
            province varchar(100),
            pharmacy_code varchar(50),
            answers longtext,
            score int(11) DEFAULT 0,
            gift_code varchar(50),
            gift_data longtext,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY phone_number (phone_number),
            KEY submitted_at (submitted_at),
            UNIQUE KEY unique_phone_campaign (phone_number, campaign_id),
            FOREIGN KEY (campaign_id) REFERENCES $campaigns_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Gifts table
        $gifts_table = $wpdb->prefix . 'aqm_gifts';
        $sql_gifts = "CREATE TABLE $gifts_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            gift_type enum('voucher','discount','physical','topup') DEFAULT 'voucher',
            gift_value decimal(10,2) DEFAULT 0,
            code_prefix varchar(10) DEFAULT 'GIFT',
            quantity int(11) DEFAULT 1,
            requirements longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            FOREIGN KEY (campaign_id) REFERENCES $campaigns_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Provinces table
        $provinces_table = $wpdb->prefix . 'aqm_provinces';
        $sql_provinces = "CREATE TABLE $provinces_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            code varchar(10) NOT NULL,
            name varchar(255) NOT NULL,
            name_en varchar(255),
            full_name varchar(255),
            full_name_en varchar(255),
            code_name varchar(255),
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";
        
        // Districts table
        $districts_table = $wpdb->prefix . 'aqm_districts';
        $sql_districts = "CREATE TABLE $districts_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            code varchar(10) NOT NULL,
            name varchar(255) NOT NULL,
            name_en varchar(255),
            full_name varchar(255),
            province_code varchar(10) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY province_code (province_code)
        ) $charset_collate;";
        
        // Wards table
        $wards_table = $wpdb->prefix . 'aqm_wards';
        $sql_wards = "CREATE TABLE $wards_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            code varchar(10) NOT NULL,
            name varchar(255) NOT NULL,
            name_en varchar(255),
            full_name varchar(255),
            district_code varchar(10) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY district_code (district_code)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_campaigns);
        dbDelta($sql_questions);
        dbDelta($sql_responses);
        dbDelta($sql_gifts);
        dbDelta($sql_provinces);
        dbDelta($sql_districts);
        dbDelta($sql_wards);
    }
}
?>