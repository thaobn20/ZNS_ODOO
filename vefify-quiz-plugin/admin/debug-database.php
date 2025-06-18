<?php
/**
 * 🔍 Database Diagnostic Tool - Debug Save Issues
 * File: admin/debug-database.php
 * 
 * Add this to your WordPress admin to diagnose database issues
 * Access via: wp-admin/admin.php?page=vefify-database-debug
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Database_Debug {
    
    private $wpdb;
    private $centralized_db;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        add_action('admin_menu', array($this, 'add_debug_menu'));
        add_action('wp_ajax_vefify_test_gift_save', array($this, 'ajax_test_gift_save'));
        add_action('wp_ajax_vefify_fix_database', array($this, 'ajax_fix_database'));
    }
    
    public function add_debug_menu() {
        add_submenu_page(
            'vefify-quiz',
            'Database Debug',
            '🔍 Debug Database',
            'manage_options',
            'vefify-database-debug',
            array($this, 'debug_page')
        );
    }
    
    public function debug_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Run comprehensive diagnostics
        $diagnostics = $this->run_diagnostics();
        
        ?>
        <div class="wrap">
            <h1>🔍 Vefify Database Diagnostic Tool</h1>
            
            <div style="background: #f0f0f1; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h2>📊 System Status</h2>
                
                <?php foreach ($diagnostics as $test_name => $result): ?>
                    <div style="margin: 10px 0; padding: 15px; background: white; border-radius: 6px; border-left: 4px solid <?php echo $result['status'] === 'success' ? '#46b450' : ($result['status'] === 'warning' ? '#ff9800' : '#dc3232'); ?>;">
                        <strong><?php echo esc_html($test_name); ?>:</strong>
                        <span style="color: <?php echo $result['status'] === 'success' ? '#46b450' : ($result['status'] === 'warning' ? '#ff9800' : '#dc3232'); ?>;">
                            <?php echo $result['status'] === 'success' ? '✅' : ($result['status'] === 'warning' ? '⚠️' : '❌'); ?>
                            <?php echo esc_html($result['message']); ?>
                        </span>
                        <?php if (isset($result['details'])): ?>
                            <div style="margin-top: 10px; font-size: 12px; color: #666;">
                                <?php echo esc_html($result['details']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Test Gift Save -->
            <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin: 20px 0;">
                <h2>🧪 Test Gift Save Function</h2>
                <p>This will attempt to create a test gift to verify the save function works:</p>
                
                <button type="button" id="test-save-btn" class="button button-primary" onclick="testGiftSave()">
                    Test Gift Save
                </button>
                
                <div id="save-test-results" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Fix Options -->
            <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin: 20px 0;">
                <h2>🛠️ Fix Options</h2>
                
                <div style="display: flex; gap: 10px; margin: 10px 0;">
                    <button type="button" class="button" onclick="fixDatabase('recreate_tables')">
                        🔄 Recreate Tables
                    </button>
                    <button type="button" class="button" onclick="fixDatabase('add_sample_data')">
                        📝 Add Sample Data
                    </button>
                    <button type="button" class="button" onclick="fixDatabase('check_permissions')">
                        🔑 Check Permissions
                    </button>
                </div>
                
                <div id="fix-results" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Database Information -->
            <div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h2>📋 Database Information</h2>
                
                <?php $this->display_table_info(); ?>
            </div>
        </div>
        
        <script>
        function testGiftSave() {
            const btn = document.getElementById('test-save-btn');
            const results = document.getElementById('save-test-results');
            
            btn.disabled = true;
            btn.textContent = 'Testing...';
            results.innerHTML = '<div style="color: #666;">Running test...</div>';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'vefify_test_gift_save',
                    nonce: '<?php echo wp_create_nonce('vefify_debug_nonce'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                const resultClass = data.success ? 'notice-success' : 'notice-error';
                results.innerHTML = '<div class="notice ' + resultClass + '"><p>' + data.data + '</p></div>';
                
                btn.disabled = false;
                btn.textContent = 'Test Gift Save';
            })
            .catch(error => {
                results.innerHTML = '<div class="notice notice-error"><p>❌ Error: ' + error + '</p></div>';
                btn.disabled = false;
                btn.textContent = 'Test Gift Save';
            });
        }
        
        function fixDatabase(action) {
            const results = document.getElementById('fix-results');
            results.innerHTML = '<div style="color: #666;">Running fix...</div>';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'vefify_fix_database',
                    fix_action: action,
                    nonce: '<?php echo wp_create_nonce('vefify_debug_nonce'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                const resultClass = data.success ? 'notice-success' : 'notice-error';
                results.innerHTML = '<div class="notice ' + resultClass + '"><p>' + data.data + '</p></div>';
                
                if (data.success) {
                    setTimeout(() => location.reload(), 2000);
                }
            })
            .catch(error => {
                results.innerHTML = '<div class="notice notice-error"><p>❌ Error: ' + error + '</p></div>';
            });
        }
        </script>
        
        <?php
    }
    
    /**
     * Run comprehensive diagnostics
     */
    private function run_diagnostics() {
        $diagnostics = array();
        
        // 1. Check WordPress database connection
        $diagnostics['WordPress Database'] = array(
            'status' => $this->wpdb->last_error ? 'error' : 'success',
            'message' => $this->wpdb->last_error ?: 'Connected successfully',
            'details' => 'WordPress version: ' . get_bloginfo('version')
        );
        
        // 2. Check centralized database class
        try {
            $this->centralized_db = new Vefify_Quiz_Database();
            $diagnostics['Centralized Database Class'] = array(
                'status' => 'success',
                'message' => 'Class loaded successfully'
            );
        } catch (Exception $e) {
            $diagnostics['Centralized Database Class'] = array(
                'status' => 'error',
                'message' => 'Failed to load: ' . $e->getMessage()
            );
        }
        
        // 3. Check table existence
        $required_tables = array('campaigns', 'questions', 'gifts', 'participants');
        $missing_tables = array();
        
        foreach ($required_tables as $table_key) {
            if ($this->centralized_db) {
                $table_name = $this->centralized_db->get_table_name($table_key);
            } else {
                $table_name = $this->wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . $table_key;
            }
            
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SHOW TABLES LIKE %s", 
                $table_name
            ));
            
            if (!$exists) {
                $missing_tables[] = $table_key;
            }
        }
        
        $diagnostics['Required Tables'] = array(
            'status' => empty($missing_tables) ? 'success' : 'error',
            'message' => empty($missing_tables) ? 'All tables exist' : 'Missing: ' . implode(', ', $missing_tables),
            'details' => 'Required: ' . implode(', ', $required_tables)
        );
        
        // 4. Check gifts table structure
        if (empty($missing_tables) || !in_array('gifts', $missing_tables)) {
            $gifts_table = $this->centralized_db ? 
                $this->centralized_db->get_table_name('gifts') : 
                $this->wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'gifts';
                
            $columns = $this->wpdb->get_results("SHOW COLUMNS FROM {$gifts_table}");
            $required_columns = array('id', 'campaign_id', 'gift_name', 'gift_type', 'gift_value', 'min_score', 'is_active');
            $existing_columns = array_column($columns, 'Field');
            $missing_columns = array_diff($required_columns, $existing_columns);
            
            $diagnostics['Gifts Table Structure'] = array(
                'status' => empty($missing_columns) ? 'success' : 'error',
                'message' => empty($missing_columns) ? 'Proper structure' : 'Missing columns: ' . implode(', ', $missing_columns),
                'details' => 'Existing columns: ' . implode(', ', $existing_columns)
            );
        }
        
        // 5. Check write permissions
        $test_campaign_name = 'test_campaign_' . time();
        $can_write = $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$this->wpdb->prefix}vefify_campaigns (name, slug, description, start_date, end_date, created_at) 
             VALUES (%s, %s, %s, %s, %s, %s)",
            $test_campaign_name,
            $test_campaign_name,
            'Test campaign for permissions',
            current_time('mysql'),
            date('Y-m-d H:i:s', strtotime('+1 year')),
            current_time('mysql')
        ));
        
        if ($can_write) {
            $this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->prefix}vefify_campaigns WHERE name = %s",
                $test_campaign_name
            ));
        }
        
        $diagnostics['Database Write Permissions'] = array(
            'status' => $can_write ? 'success' : 'error',
            'message' => $can_write ? 'Can write to database' : 'Cannot write to database: ' . $this->wpdb->last_error,
            'details' => 'Test insert and delete performed'
        );
        
        // 6. Check constants and plugin setup
        $diagnostics['Plugin Constants'] = array(
            'status' => defined('VEFIFY_QUIZ_TABLE_PREFIX') ? 'success' : 'error',
            'message' => defined('VEFIFY_QUIZ_TABLE_PREFIX') ? 
                'Table prefix defined: ' . VEFIFY_QUIZ_TABLE_PREFIX : 
                'VEFIFY_QUIZ_TABLE_PREFIX not defined',
            'details' => 'Plugin directory: ' . (defined('VEFIFY_QUIZ_PLUGIN_DIR') ? VEFIFY_QUIZ_PLUGIN_DIR : 'Not defined')
        );
        
        return $diagnostics;
    }
    
    /**
     * Display detailed table information
     */
    private function display_table_info() {
        if (!$this->centralized_db) {
            echo '<p>❌ Centralized database not available</p>';
            return;
        }
        
        $tables = array('campaigns', 'questions', 'gifts', 'participants');
        
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Table</th><th>Exists</th><th>Row Count</th><th>Structure</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($tables as $table_key) {
            $table_name = $this->centralized_db->get_table_name($table_key);
            
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SHOW TABLES LIKE %s", 
                $table_name
            ));
            
            $row_count = 0;
            $structure = 'N/A';
            
            if ($exists) {
                $row_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                $columns = $this->wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
                $structure = count($columns) . ' columns';
            }
            
            echo '<tr>';
            echo '<td><code>' . esc_html($table_name) . '</code></td>';
            echo '<td>' . ($exists ? '✅ Yes' : '❌ No') . '</td>';
            echo '<td>' . number_format($row_count) . '</td>';
            echo '<td>' . esc_html($structure) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    /**
     * AJAX: Test gift save functionality
     */
    public function ajax_test_gift_save() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'vefify_debug_nonce')) {
            wp_die('Unauthorized');
        }
        
        try {
            // Load gift model
            require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/gifts/class-gift-model.php';
            $gift_model = new Vefify_Gift_Model();
            
            // Test data
            $test_gift_data = array(
                'campaign_id' => 1, // Assume campaign 1 exists
                'gift_name' => 'Test Gift ' . time(),
                'gift_type' => 'voucher',
                'gift_value' => '1000 VND',
                'gift_description' => 'Test gift for debugging',
                'min_score' => 1,
                'max_score' => 5,
                'max_quantity' => 10,
                'gift_code_prefix' => 'TEST',
                'is_active' => 1
            );
            
            // Attempt to save
            $result = $gift_model->save_gift($test_gift_data);
            
            if (is_array($result) && isset($result['errors'])) {
                wp_send_json_error('❌ Save failed with errors: ' . implode(', ', $result['errors']));
            } elseif ($result === false) {
                wp_send_json_error('❌ Save failed without specific error');
            } else {
                // Success - clean up test data
                global $wpdb;
                $wpdb->delete(
                    $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'gifts',
                    array('id' => $result),
                    array('%d')
                );
                
                wp_send_json_success('✅ Gift save test successful! Gift ID: ' . $result . ' (cleaned up)');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('❌ Exception during test: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Fix database issues
     */
    public function ajax_fix_database() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'vefify_debug_nonce')) {
            wp_die('Unauthorized');
        }
        
        $action = sanitize_text_field($_POST['fix_action']);
        
        try {
            switch ($action) {
                case 'recreate_tables':
                    if ($this->centralized_db) {
                        $this->centralized_db->create_tables();
                        wp_send_json_success('✅ Tables recreated successfully!');
                    } else {
                        wp_send_json_error('❌ Centralized database not available');
                    }
                    break;
                    
                case 'add_sample_data':
                    if ($this->centralized_db) {
                        $this->centralized_db->insert_sample_data();
                        wp_send_json_success('✅ Sample data added successfully!');
                    } else {
                        wp_send_json_error('❌ Centralized database not available');
                    }
                    break;
                    
                case 'check_permissions':
                    $diagnostics = $this->run_diagnostics();
                    $permission_test = $diagnostics['Database Write Permissions'];
                    
                    if ($permission_test['status'] === 'success') {
                        wp_send_json_success('✅ Database permissions are working correctly');
                    } else {
                        wp_send_json_error('❌ ' . $permission_test['message']);
                    }
                    break;
                    
                default:
                    wp_send_json_error('❌ Unknown fix action');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('❌ Error during fix: ' . $e->getMessage());
        }
    }
}

// Initialize if in admin
if (is_admin()) {
    new Vefify_Database_Debug();
}