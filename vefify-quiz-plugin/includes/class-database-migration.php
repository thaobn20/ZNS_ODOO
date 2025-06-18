<?php
/**
 * DATABASE SCHEMA FIX AND MIGRATION
 * File: includes/class-database-migration.php
 * 
 * Fixes column name mismatches and ensures consistent schema
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Database_Migration {
    
    private $wpdb;
    private $table_prefix;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    }
    
    /**
     * Run all necessary database migrations
     */
    public function run_migrations() {
        $current_version = get_option('vefify_quiz_db_version', '0');
        
        try {
            // Migration 1.0.1 - Fix participants table column names
            if (version_compare($current_version, '1.0.1', '<')) {
                $this->migrate_to_1_0_1();
                update_option('vefify_quiz_db_version', '1.0.1');
            }
            
            // Migration 1.0.2 - Add missing indexes
            if (version_compare($current_version, '1.0.2', '<')) {
                $this->migrate_to_1_0_2();
                update_option('vefify_quiz_db_version', '1.0.2');
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Vefify Quiz Migration Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Migration 1.0.1: Fix participants table column naming
     */
    private function migrate_to_1_0_1() {
        $participants_table = $this->table_prefix . 'participants';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$participants_table}'");
        if (!$table_exists) {
            // Create the table with correct schema
            $this->create_corrected_participants_table();
            return;
        }
        
        // Get current table structure
        $columns = $this->wpdb->get_results("DESCRIBE {$participants_table}");
        $column_names = array_column($columns, 'Field');
        
        // Migration rules: old_name => new_name
        $column_migrations = array(
            'participant_name' => 'full_name',
            'participant_phone' => 'phone_number', 
            'participant_email' => 'email',
            'final_score' => 'score',
            'start_time' => 'started_at',
            'end_time' => 'completed_at'
        );
        
        foreach ($column_migrations as $old_name => $new_name) {
            if (in_array($old_name, $column_names) && !in_array($new_name, $column_names)) {
                $this->wpdb->query("ALTER TABLE {$participants_table} CHANGE `{$old_name}` `{$new_name}` " . $this->get_column_definition($new_name));
                error_log("Vefify Quiz: Renamed column {$old_name} to {$new_name}");
            }
        }
        
        // Add missing columns if they don't exist
        $required_columns = array(
            'completed_at' => 'datetime DEFAULT NULL',
            'score' => 'int(11) DEFAULT 0',
            'total_questions' => 'int(11) DEFAULT 0',
            'completion_time' => 'int(11) DEFAULT NULL',
            'gift_code' => 'varchar(100) DEFAULT NULL',
            'gift_status' => "enum('none', 'assigned', 'claimed', 'expired') DEFAULT 'none'"
        );
        
        foreach ($required_columns as $column => $definition) {
            if (!in_array($column, $column_names)) {
                $this->wpdb->query("ALTER TABLE {$participants_table} ADD `{$column}` {$definition}");
                error_log("Vefify Quiz: Added missing column {$column}");
            }
        }
        
        error_log('Vefify Quiz: Migration 1.0.1 completed - Participants table schema fixed');
    }
    
    /**
     * Migration 1.0.2: Add missing indexes for performance
     */
    private function migrate_to_1_0_2() {
        $participants_table = $this->table_prefix . 'participants';
        
        // Add indexes for better performance
        $indexes = array(
            'idx_phone_lookup' => 'phone_number',
            'idx_completion_status' => 'quiz_status',
            'idx_completed_at' => 'completed_at',
            'idx_campaign_participants' => 'campaign_id',
            'idx_gift_status' => 'gift_status'
        );
        
        foreach ($indexes as $index_name => $column) {
            $existing_index = $this->wpdb->get_var("SHOW INDEX FROM {$participants_table} WHERE Key_name = '{$index_name}'");
            if (!$existing_index) {
                $this->wpdb->query("ALTER TABLE {$participants_table} ADD INDEX {$index_name} ({$column})");
                error_log("Vefify Quiz: Added index {$index_name}");
            }
        }
        
        error_log('Vefify Quiz: Migration 1.0.2 completed - Performance indexes added');
    }
    
    /**
     * Create participants table with corrected schema
     */
    private function create_corrected_participants_table() {
        $participants_table = $this->table_prefix . 'participants';
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$participants_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            session_id varchar(100) NOT NULL,
            full_name varchar(255) DEFAULT NULL,
            phone_number varchar(50) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            province varchar(100) DEFAULT NULL,
            pharmacy_code varchar(50) DEFAULT NULL,
            quiz_status enum('started','in_progress','completed','abandoned') DEFAULT 'started',
            score int(11) DEFAULT 0,
            total_questions int(11) DEFAULT 0,
            completion_time int(11) DEFAULT NULL,
            answers_data longtext,
            gift_id int(11) DEFAULT NULL,
            gift_code varchar(100) DEFAULT NULL,
            gift_status enum('none', 'assigned', 'claimed', 'expired') DEFAULT 'none',
            gift_response longtext,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_campaign_phone (campaign_id, phone_number),
            KEY idx_session (session_id),
            KEY idx_phone_lookup (phone_number),
            KEY idx_completion_status (quiz_status),
            KEY idx_completed_at (completed_at),
            KEY idx_campaign_participants (campaign_id),
            KEY idx_gift_status (gift_status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('Vefify Quiz: Created participants table with corrected schema');
    }
    
    /**
     * Get column definition for migrations
     */
    private function get_column_definition($column_name) {
        $definitions = array(
            'full_name' => 'varchar(255) DEFAULT NULL',
            'phone_number' => 'varchar(50) DEFAULT NULL',
            'email' => 'varchar(255) DEFAULT NULL',
            'score' => 'int(11) DEFAULT 0',
            'started_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
            'completed_at' => 'datetime DEFAULT NULL'
        );
        
        return $definitions[$column_name] ?? 'text';
    }
    
    /**
     * Verify database integrity after migration
     */
    public function verify_database() {
        $issues = array();
        
        // Check participants table structure
        $participants_table = $this->table_prefix . 'participants';
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$participants_table}'");
        
        if (!$table_exists) {
            $issues[] = 'Participants table does not exist';
        } else {
            $columns = $this->wpdb->get_results("DESCRIBE {$participants_table}");
            $column_names = array_column($columns, 'Field');
            
            $required_columns = array('id', 'campaign_id', 'session_id', 'full_name', 'phone_number', 'email', 'province', 'quiz_status', 'score', 'total_questions', 'completed_at', 'gift_id', 'gift_code', 'gift_status');
            
            foreach ($required_columns as $required_column) {
                if (!in_array($required_column, $column_names)) {
                    $issues[] = "Missing column: {$required_column} in participants table";
                }
            }
        }
        
        return empty($issues) ? true : $issues;
    }
    
    /**
     * Get database statistics
     */
    public function get_database_info() {
        $info = array();
        
        // Get table sizes
        $tables = array('campaigns', 'questions', 'question_options', 'gifts', 'participants', 'quiz_sessions', 'analytics');
        
        foreach ($tables as $table_key) {
            $table_name = $this->table_prefix . $table_key;
            $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
            
            if ($table_exists) {
                $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                $info[$table_key] = array(
                    'exists' => true,
                    'count' => intval($count),
                    'table_name' => $table_name
                );
            } else {
                $info[$table_key] = array(
                    'exists' => false,
                    'count' => 0,
                    'table_name' => $table_name
                );
            }
        }
        
        return $info;
    }
    
    /**
     * Clean up old/duplicate data
     */
    public function cleanup_database() {
        $cleaned = array();
        
        // Remove duplicate participants (same phone + campaign)
        $participants_table = $this->table_prefix . 'participants';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$participants_table}'")) {
            $duplicates = $this->wpdb->query("
                DELETE p1 FROM {$participants_table} p1
                INNER JOIN {$participants_table} p2 
                WHERE p1.id > p2.id 
                AND p1.campaign_id = p2.campaign_id 
                AND p1.phone_number = p2.phone_number
                AND p1.phone_number IS NOT NULL
            ");
            $cleaned['duplicate_participants'] = $duplicates;
        }
        
        // Remove incomplete sessions older than 24 hours
        $sessions_table = $this->table_prefix . 'quiz_sessions';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$sessions_table}'")) {
            $old_sessions = $this->wpdb->query("
                DELETE FROM {$sessions_table} 
                WHERE is_completed = 0 
                AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $cleaned['old_sessions'] = $old_sessions;
        }
        
        // Remove old analytics data (older than 90 days)
        $analytics_table = $this->table_prefix . 'analytics';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$analytics_table}'")) {
            $old_analytics = $this->wpdb->query("
                DELETE FROM {$analytics_table} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            ");
            $cleaned['old_analytics'] = $old_analytics;
        }
        
        return $cleaned;
    }
}

// AUTO-RUN MIGRATIONS ON PLUGIN LOAD
add_action('plugins_loaded', function() {
    // Only run migrations in admin or when specifically requested
    if (is_admin() || (defined('WP_CLI') && WP_CLI)) {
        $migration = new Vefify_Quiz_Database_Migration();
        $migration->run_migrations();
    }
});

// Add admin notice if migration is needed
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        $migration = new Vefify_Quiz_Database_Migration();
        $issues = $migration->verify_database();
        
        if ($issues !== true) {
            echo '<div class="notice notice-warning">';
            echo '<h3>⚠️ Vefify Quiz Database Issues Detected</h3>';
            echo '<p><strong>The following database issues were found:</strong></p>';
            echo '<ul>';
            foreach ($issues as $issue) {
                echo '<li>' . esc_html($issue) . '</li>';
            }
            echo '</ul>';
            echo '<p><strong>Automatic Fix:</strong> Migrations will run automatically to fix these issues.</p>';
            echo '<p><a href="' . admin_url('admin.php?page=vefify-quiz') . '" class="button button-primary">Go to Vefify Quiz Dashboard</a></p>';
            echo '</div>';
        }
    }
});

// Add migration tools to admin if needed
add_action('wp_ajax_vefify_run_migration', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'vefify_migration')) {
        wp_die('Security check failed');
    }
    
    $migration = new Vefify_Quiz_Database_Migration();
    $result = $migration->run_migrations();
    
    if ($result) {
        wp_send_json_success('Migration completed successfully');
    } else {
        wp_send_json_error('Migration failed - check error logs');
    }
});

// Add database cleanup tool
add_action('wp_ajax_vefify_cleanup_database', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'vefify_cleanup')) {
        wp_die('Security check failed');
    }
    
    $migration = new Vefify_Quiz_Database_Migration();
    $cleaned = $migration->cleanup_database();
    
    wp_send_json_success(array(
        'message' => 'Database cleanup completed',
        'details' => $cleaned
    ));
});