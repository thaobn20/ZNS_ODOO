<?php
/**
 * Emergency Database Fix for Vefify Quiz Plugin
 * Add this as a temporary admin page to diagnose and fix database issues
 * 
 * Save as: includes/class-emergency-db-fix.php
 */

class Vefify_Emergency_DB_Fix {
    
    private $wpdb;
    private $table_prefix;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'vefify_';
        
        add_action('admin_menu', array($this, 'add_emergency_menu'));
        add_action('wp_ajax_vefify_fix_database', array($this, 'handle_fix_database'));
    }
    
    /**
     * Add emergency menu page
     */
    public function add_emergency_menu() {
        add_submenu_page(
            'vefify-dashboard',
            'Emergency DB Fix',
            '🚨 DB Fix',
            'manage_options',
            'vefify-emergency-fix',
            array($this, 'render_emergency_page')
        );
    }
    
    /**
     * Render emergency fix page
     */
    public function render_emergency_page() {
        $diagnosis = $this->diagnose_database_issues();
        ?>
        <div class="wrap">
            <h1>🚨 Emergency Database Fix</h1>
            
            <div class="notice notice-warning">
                <p><strong>This page helps diagnose and fix database connection issues.</strong></p>
            </div>
            
            <!-- Database Diagnosis -->
            <div class="card">
                <h2>📊 Database Diagnosis</h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Check</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($diagnosis as $check => $result): ?>
                        <tr>
                            <td><strong><?php echo esc_html($check); ?></strong></td>
                            <td>
                                <?php if($result['status'] === 'success'): ?>
                                    <span style="color: green;">✅ PASS</span>
                                <?php elseif($result['status'] === 'warning'): ?>
                                    <span style="color: orange;">⚠️ WARNING</span>
                                <?php else: ?>
                                    <span style="color: red;">❌ FAIL</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($result['message']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <h2>🔧 Quick Actions</h2>
                <p>Click the buttons below to attempt automatic fixes:</p>
                
                <button type="button" class="button button-primary" onclick="fixDatabase('recreate_tables')">
                    🔄 Recreate All Tables
                </button>
                
                <button type="button" class="button button-secondary" onclick="fixDatabase('add_sample_data')">
                    📝 Add Sample Data
                </button>
                
                <button type="button" class="button button-secondary" onclick="fixDatabase('check_permissions')">
                    🔒 Check Permissions
                </button>
                
                <button type="button" class="button" onclick="location.reload()">
                    🔄 Refresh Diagnosis
                </button>
            </div>
            
            <!-- Manual SQL -->
            <div class="card">
                <h2>🛠️ Manual SQL (Advanced Users)</h2>
                <p>If automatic fixes fail, run this SQL manually in phpMyAdmin:</p>
                <textarea readonly style="width: 100%; height: 200px; font-family: monospace;"><?php echo $this->generate_manual_sql(); ?></textarea>
            </div>
            
            <div id="fix-results" style="margin-top: 20px;"></div>
        </div>
        
        <script>
        function fixDatabase(action) {
            document.getElementById('fix-results').innerHTML = '<div class="notice notice-info"><p>⏳ Processing...</p></div>';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=vefify_fix_database&fix_action=' + action + '&nonce=<?php echo wp_create_nonce('vefify_emergency_fix'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                let resultClass = data.success ? 'notice-success' : 'notice-error';
                document.getElementById('fix-results').innerHTML = 
                    '<div class="notice ' + resultClass + '"><p>' + data.data + '</p></div>';
                
                if(data.success) {
                    setTimeout(() => location.reload(), 2000);
                }
            })
            .catch(error => {
                document.getElementById('fix-results').innerHTML = 
                    '<div class="notice notice-error"><p>❌ Error: ' + error + '</p></div>';
            });
        }
        </script>
        <?php
    }
    
    /**
     * Diagnose database issues
     */
    private function diagnose_database_issues() {
        $diagnosis = array();
        
        // Check WordPress database connection
        $diagnosis['WordPress DB Connection'] = array(
            'status' => $this->wpdb->last_error ? 'error' : 'success',
            'message' => $this->wpdb->last_error ?: 'Connection active'
        );
        
        // Check if tables exist
        $tables = array('campaigns', 'questions', 'question_options', 'gifts', 'participants', 'analytics');
        $missing_tables = array();
        
        foreach($tables as $table) {
            $table_name = $this->table_prefix . $table;
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if(!$exists) {
                $missing_tables[] = $table;
            }
        }
        
        $diagnosis['Required Tables'] = array(
            'status' => empty($missing_tables) ? 'success' : 'error',
            'message' => empty($missing_tables) ? 'All tables exist' : 'Missing: ' . implode(', ', $missing_tables)
        );
        
        // Check table structure
        if(empty($missing_tables)) {
            $campaigns_table = $this->table_prefix . 'campaigns';
            $columns = $this->wpdb->get_results("SHOW COLUMNS FROM $campaigns_table");
            $has_required_columns = false;
            
            foreach($columns as $column) {
                if(in_array($column->Field, array('name', 'description', 'start_date', 'end_date'))) {
                    $has_required_columns = true;
                    break;
                }
            }
            
            $diagnosis['Table Structure'] = array(
                'status' => $has_required_columns ? 'success' : 'error',
                'message' => $has_required_columns ? 'Proper structure' : 'Invalid table structure'
            );
        }
        
        // Check database permissions
        $can_insert = $this->wpdb->query("INSERT INTO {$this->table_prefix}campaigns (name, description, created_at) VALUES ('test', 'test', NOW())");
        if($can_insert) {
            $this->wpdb->query("DELETE FROM {$this->table_prefix}campaigns WHERE name = 'test'");
        }
        
        $diagnosis['Write Permissions'] = array(
            'status' => $can_insert ? 'success' : 'error',
            'message' => $can_insert ? 'Can write to database' : 'Cannot write to database'
        );
        
        // Check plugin activation
        $diagnosis['Plugin Status'] = array(
            'status' => is_plugin_active('vefify-quiz-plugin/vefify-quiz-plugin.php') ? 'success' : 'warning',
            'message' => is_plugin_active('vefify-quiz-plugin/vefify-quiz-plugin.php') ? 'Plugin active' : 'Plugin not properly activated'
        );
        
        return $diagnosis;
    }
    
    /**
     * Handle AJAX fix requests
     */
    public function handle_fix_database() {
        if(!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'vefify_emergency_fix')) {
            wp_die('Unauthorized');
        }
        
        $action = sanitize_text_field($_POST['fix_action']);
        $result = array('success' => false, 'data' => 'Unknown action');
        
        switch($action) {
            case 'recreate_tables':
                $result = $this->recreate_tables();
                break;
                
            case 'add_sample_data':
                $result = $this->add_sample_data();
                break;
                
            case 'check_permissions':
                $result = $this->check_permissions();
                break;
        }
        
        wp_send_json($result);
    }
    
    /**
     * Recreate database tables
     */
    private function recreate_tables() {
        try {
            // Drop existing tables
            $tables = array('analytics', 'participants', 'gifts', 'question_options', 'questions', 'campaigns');
            foreach($tables as $table) {
                $this->wpdb->query("DROP TABLE IF EXISTS {$this->table_prefix}{$table}");
            }
            
            // Create tables
            $this->create_tables();
            
            return array('success' => true, 'data' => '✅ Tables recreated successfully!');
            
        } catch(Exception $e) {
            return array('success' => false, 'data' => '❌ Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Campaigns table
        $campaigns_sql = "CREATE TABLE {$this->table_prefix}campaigns (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            max_participants int(11) DEFAULT 0,
            questions_per_quiz int(11) DEFAULT 5,
            pass_score int(11) DEFAULT 3,
            time_limit int(11) DEFAULT 600,
            is_active tinyint(1) DEFAULT 1,
            meta_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY idx_active_campaigns (is_active, start_date, end_date)
        ) $charset_collate;";
        
        // Questions table
        $questions_sql = "CREATE TABLE {$this->table_prefix}questions (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) DEFAULT NULL,
            question_text text NOT NULL,
            question_type enum('multiple_choice', 'multiple_select', 'true_false') DEFAULT 'multiple_choice',
            category varchar(100) DEFAULT NULL,
            difficulty enum('easy', 'medium', 'hard') DEFAULT 'medium',
            points int(11) DEFAULT 1,
            explanation text,
            is_active tinyint(1) DEFAULT 1,
            order_index int(11) DEFAULT 0,
            meta_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_campaign_questions (campaign_id, is_active)
        ) $charset_collate;";
        
        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($campaigns_sql);
        dbDelta($questions_sql);
        
        if($this->wpdb->last_error) {
            throw new Exception($this->wpdb->last_error);
        }
    }
    
    /**
     * Add sample data
     */
    private function add_sample_data() {
        try {
            // Insert sample campaign
            $campaign_data = array(
                'name' => 'Sample Health Quiz',
                'slug' => 'sample-health-quiz',
                'description' => 'A sample quiz to test the system',
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
                'max_participants' => 100,
                'questions_per_quiz' => 5,
                'pass_score' => 3,
                'time_limit' => 600,
                'is_active' => 1,
                'meta_data' => json_encode(array())
            );
            
            $this->wpdb->insert($this->table_prefix . 'campaigns', $campaign_data);
            $campaign_id = $this->wpdb->insert_id;
            
            if(!$campaign_id) {
                throw new Exception('Failed to create sample campaign');
            }
            
            return array('success' => true, 'data' => "✅ Sample data added successfully! Campaign ID: $campaign_id");
            
        } catch(Exception $e) {
            return array('success' => false, 'data' => '❌ Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Check database permissions
     */
    private function check_permissions() {
        $tests = array();
        
        // Test SELECT
        $select_test = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_prefix}campaigns");
        $tests[] = 'SELECT: ' . ($select_test !== null ? 'OK' : 'FAIL');
        
        // Test INSERT
        $insert_test = $this->wpdb->insert($this->table_prefix . 'campaigns', array(
            'name' => 'Permission Test',
            'slug' => 'permission-test-' . time(),
            'description' => 'Testing permissions',
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ));
        $tests[] = 'INSERT: ' . ($insert_test ? 'OK' : 'FAIL');
        
        // Clean up test data
        if($insert_test) {
            $this->wpdb->delete($this->table_prefix . 'campaigns', array('name' => 'Permission Test'));
        }
        
        return array('success' => true, 'data' => '🔒 Permission tests: ' . implode(', ', $tests));
    }
    
    /**
     * Generate manual SQL for advanced users
     */
    private function generate_manual_sql() {
        $prefix = $this->table_prefix;
        return "-- Manual SQL for Vefify Quiz Plugin
-- Run this in phpMyAdmin if automatic fixes fail

DROP TABLE IF EXISTS {$prefix}analytics;
DROP TABLE IF EXISTS {$prefix}participants;
DROP TABLE IF EXISTS {$prefix}gifts;
DROP TABLE IF EXISTS {$prefix}question_options;
DROP TABLE IF EXISTS {$prefix}questions;
DROP TABLE IF EXISTS {$prefix}campaigns;

CREATE TABLE {$prefix}campaigns (
    id int(11) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    description text,
    start_date datetime NOT NULL,
    end_date datetime NOT NULL,
    max_participants int(11) DEFAULT 0,
    questions_per_quiz int(11) DEFAULT 5,
    pass_score int(11) DEFAULT 3,
    time_limit int(11) DEFAULT 600,
    is_active tinyint(1) DEFAULT 1,
    meta_data longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug)
);

INSERT INTO {$prefix}campaigns (name, slug, description, start_date, end_date) 
VALUES ('Sample Quiz', 'sample-quiz', 'Test campaign', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY));";
    }
}

// Initialize the emergency fix class
new Vefify_Emergency_DB_Fix();