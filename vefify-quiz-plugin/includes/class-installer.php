<?php
/**
 * Plugin Installer
 * File: includes/class-installer.php
 */

class Installer {
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix . 'vefify_';
        
        // Read SQL file and execute
        $sql_file = VEFIFY_QUIZ_PLUGIN_DIR . 'database/migrations/001_create_campaigns.sql';
        $sql_content = file_get_contents($sql_file);
        
        // Replace table names with WordPress prefix
        $sql_content = str_replace('vefify_', $table_prefix, $sql_content);
        $sql_content = str_replace('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', $charset_collate, $sql_content);
        
        // Split into individual queries
        $queries = array_filter(array_map('trim', explode(';', $sql_content)));
        
        foreach ($queries as $query) {
            if (!empty($query)) {
                $wpdb->query($query);
            }
        }
        
        // Update database version
        update_option('vefify_quiz_db_version', VEFIFY_QUIZ_VERSION);
    }
    
    public function insert_sample_data() {
        global $wpdb;
        
        // Check if sample data already exists
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vefify_campaigns");
        if ($existing > 0) {
            return; // Sample data already exists
        }
        
        // Read and execute sample data SQL
        $sql_file = VEFIFY_QUIZ_PLUGIN_DIR . 'database/seeds/sample_campaigns.sql';
        if (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);
            $sql_content = str_replace('vefify_', $wpdb->prefix . 'vefify_', $sql_content);
            
            $queries = array_filter(array_map('trim', explode(';', $sql_content)));
            
            foreach ($queries as $query) {
                if (!empty($query)) {
                    $wpdb->query($query);
                }
            }
        }
    }
    
    public function upgrade() {
        $installed_version = get_option('vefify_quiz_db_version', '0');
        
        if (version_compare($installed_version, VEFIFY_QUIZ_VERSION, '<')) {
            $this->create_tables();
            update_option('vefify_quiz_db_version', VEFIFY_QUIZ_VERSION);
        }
    }
}
