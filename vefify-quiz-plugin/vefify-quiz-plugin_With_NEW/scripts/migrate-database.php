<?php
/**
 * Database Migration Script
 * File: scripts/migrate-database.php
 * 
 * This script aligns the database structure to fix table name mismatches
 */

if (!defined('ABSPATH')) {
    // Load WordPress if running standalone
    require_once dirname(__FILE__) . '/../../../../wp-config.php';
}

class Vefify_Quiz_Database_Migration {
    
    private $wpdb;
    private $old_tables = array();
    private $new_tables = array();
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Define table mappings
        $this->setup_table_mappings();
    }
    
    private function setup_table_mappings() {
        $prefix = $this->wpdb->prefix;
        
        // Map old table names to new standard names
        $this->old_tables = array(
            'wp_vq_campaigns' => $prefix . 'vefify_campaigns',
            'wp_vq_questions' => $prefix . 'vefify_questions', 
            'wp_vq_question_options' => $prefix . 'vefify_question_options',
            'wp_vq_gifts' => $prefix . 'vefify_gifts',
            'wp_vq_quiz_users' => $prefix . 'vefify_quiz_users',
            'wp_vq_quiz_sessions' => $prefix . 'vefify_quiz_sessions',
            'wp_vq_quiz_answers' => $prefix . 'vefify_quiz_answers',
            'wp_vq_user_gifts' => $prefix . 'vefify_user_gifts',
            'wp_vq_analytics' => $prefix . 'vefify_analytics'
        );
        
        // Create participants table (missing from wp_vq structure)
        $this->new_tables['participants'] = $prefix . 'vefify_participants';
    }
    
    public function migrate() {
        echo "Starting database migration...\n";
        
        // Step 1: Create missing tables
        $this->create_missing_tables();
        
        // Step 2: Rename existing tables if they exist with old prefix
        $this->rename_existing_tables();
        
        // Step 3: Migrate data from wp_vq_quiz_users to vefify_participants
        $this->migrate_participants_data();
        
        // Step 4: Update table indexes and constraints
        $this->update_table_structure();
        
        echo "Migration completed successfully!\n";
    }
    
    private function create_missing_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Create participants table (this is what the code is looking for)
        $participants_sql = "CREATE TABLE IF NOT EXISTS {$this->new_tables['participants']} (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            participant_name varchar(255) DEFAULT NULL,
            participant_email varchar(255) DEFAULT NULL,
            participant_phone varchar(50) DEFAULT NULL,
            province varchar(100) DEFAULT NULL,
            district varchar(100) DEFAULT NULL,
            ward varchar(100) DEFAULT NULL,
            date_of_birth date DEFAULT NULL,
            quiz_status enum('started','in_progress','completed','abandoned') DEFAULT 'started',
            start_time datetime DEFAULT CURRENT_TIMESTAMP,
            end_time datetime DEFAULT NULL,
            final_score int(11) DEFAULT 0,
            total_questions int(11) DEFAULT 0,
            correct_answers int(11) DEFAULT 0,
            completion_time int(11) DEFAULT NULL,
            answers_data longtext DEFAULT NULL,
            gift_code varchar(100) DEFAULT NULL,
            gift_id int(11) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY quiz_status (quiz_status),
            KEY participant_email (participant_email),
            KEY gift_code (gift_code)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($participants_sql);
        
        echo "Created participants table\n";
    }
    
    private function rename_existing_tables() {
        foreach ($this->old_tables as $old_name => $new_name) {
            // Check if old table exists
            $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$old_name'");
            
            if ($table_exists) {
                // Check if new table already exists
                $new_table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$new_name'");
                
                if (!$new_table_exists) {
                    // Rename table
                    $result = $this->wpdb->query("RENAME TABLE `$old_name` TO `$new_name`");
                    if ($result !== false) {
                        echo "Renamed $old_name to $new_name\n";
                    } else {
                        echo "Failed to rename $old_name\n";
                    }
                } else {
                    echo "Table $new_name already exists, skipping rename\n";
                }
            }
        }
    }
    
    private function migrate_participants_data() {
        $old_users_table = $this->wpdb->prefix . 'vq_quiz_users';
        $old_sessions_table = $this->wpdb->prefix . 'vq_quiz_sessions';
        $participants_table = $this->new_tables['participants'];
        
        // Check if old users table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$old_users_table'");
        if (!$table_exists) {
            echo "Old users table doesn't exist, skipping participant migration\n";
            return;
        }
        
        // Migrate data from old structure to participants table
        $migration_sql = "
            INSERT INTO $participants_table (
                campaign_id, participant_name, participant_email, 
                participant_phone, province, district, ward, 
                date_of_birth, quiz_status, final_score, 
                total_questions, correct_answers, completion_time,
                created_at
            )
            SELECT 
                COALESCE(s.campaign_id, 1) as campaign_id,
                u.full_name as participant_name,
                u.email as participant_email,
                u.phone as participant_phone,
                u.province,
                u.district,
                u.ward,
                u.date_of_birth,
                CASE 
                    WHEN s.is_completed = 1 THEN 'completed'
                    ELSE 'started'
                END as quiz_status,
                COALESCE(s.total_points, 0) as final_score,
                COALESCE(s.total_questions, 0) as total_questions,
                COALESCE(s.correct_answers, 0) as correct_answers,
                s.completion_time,
                u.created_at
            FROM $old_users_table u
            LEFT JOIN $old_sessions_table s ON s.user_id = u.id
            WHERE NOT EXISTS (
                SELECT 1 FROM $participants_table p 
                WHERE p.participant_email = u.email 
                AND p.campaign_id = COALESCE(s.campaign_id, 1)
            )
        ";
        
        $result = $this->wpdb->query($migration_sql);
        if ($result !== false) {
            echo "Migrated $result participant records\n";
        } else {
            echo "Failed to migrate participant data\n";
        }
    }
    
    private function update_table_structure() {
        // Add any missing columns to existing tables
        $this->add_missing_columns();
        
        // Update indexes for better performance
        $this->update_indexes();
    }
    
    private function add_missing_columns() {
        $participants_table = $this->new_tables['participants'];
        
        // Check and add missing columns
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM $participants_table");
        $existing_columns = array_column($columns, 'Field');
        
        $required_columns = array(
            'gift_code' => "ALTER TABLE $participants_table ADD COLUMN gift_code varchar(100) DEFAULT NULL",
            'gift_id' => "ALTER TABLE $participants_table ADD COLUMN gift_id int(11) DEFAULT NULL",
            'ip_address' => "ALTER TABLE $participants_table ADD COLUMN ip_address varchar(45) DEFAULT NULL"
        );
        
        foreach ($required_columns as $column => $sql) {
            if (!in_array($column, $existing_columns)) {
                $this->wpdb->query($sql);
                echo "Added column $column to participants table\n";
            }
        }
    }
    
    private function update_indexes() {
        $participants_table = $this->new_tables['participants'];
        
        // Add indexes for better performance
        $indexes = array(
            "CREATE INDEX idx_campaign_status ON $participants_table (campaign_id, quiz_status)",
            "CREATE INDEX idx_gift_code ON $participants_table (gift_code)",
            "CREATE INDEX idx_created_at ON $participants_table (created_at)"
        );
        
        foreach ($indexes as $index_sql) {
            $this->wpdb->query($index_sql);
        }
        
        echo "Updated table indexes\n";
    }
    
    public function rollback() {
        echo "Rolling back migration...\n";
        
        // Drop participants table if it was created
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->new_tables['participants']}");
        
        // Rename tables back to original names
        foreach ($this->old_tables as $old_name => $new_name) {
            $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$new_name'");
            if ($table_exists) {
                $this->wpdb->query("RENAME TABLE `$new_name` TO `$old_name`");
                echo "Rolled back $new_name to $old_name\n";
            }
        }
        
        echo "Rollback completed\n";
    }
}

// Run migration if called directly
if (defined('WP_CLI') && WP_CLI) {
    // WP-CLI command
    WP_CLI::add_command('vefify migrate', function($args) {
        $migration = new Vefify_Quiz_Database_Migration();
        if (isset($args[0]) && $args[0] === 'rollback') {
            $migration->rollback();
        } else {
            $migration->migrate();
        }
    });
} elseif (isset($_GET['run_migration']) && $_GET['run_migration'] === 'true') {
    // Web interface
    $migration = new Vefify_Quiz_Database_Migration();
    $migration->migrate();
}

?>