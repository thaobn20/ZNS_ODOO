<?php
/**
 * 🚨 Emergency Validation Helper - Fixed Syntax
 * File: includes/class-validation-helper.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Validation_Helper {
    
    private static $instance = null;
    private $wpdb;
    private $centralized_db;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Try to load centralized database
        try {
            if (class_exists('Vefify_Quiz_Database')) {
                $this->centralized_db = new Vefify_Quiz_Database();
            }
        } catch (Exception $e) {
            error_log('Validation Helper: Could not load centralized database: ' . $e->getMessage());
        }
        
        // Add AJAX handlers
        add_action('wp_ajax_vefify_emergency_test_gift_save', array($this, 'ajax_test_gift_save'));
        add_action('wp_ajax_vefify_emergency_fix_database', array($this, 'ajax_fix_database'));
    }
    
    /**
     * 🚨 MAIN DISPLAY METHOD - Called by existing menu system
     */
    public static function display_validation_page() {
        $instance = self::get_instance();
        $instance->render_validation_page();
    }
    
    /**
     * 🎨 Render the validation/diagnostic page
     */
    public function render_validation_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        // Run diagnostics
        $diagnostics = $this->run_comprehensive_diagnostics();
        $gift_test = $this->test_gift_save_capability();
        
        ?>
        <div class="wrap">
            <h1>🚨 Vefify Emergency System Validation</h1>
            
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h2 style="color: white; margin: 0 0 10px 0;">🔍 System Health Dashboard</h2>
                <p style="margin: 0; opacity: 0.9;">Comprehensive validation of database, tables, and save functionality</p>
            </div>
            
            <!-- System Status Overview -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
                
                <!-- Database Status -->
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
                    <h3 style="margin-top: 0; color: #333;">📊 Database Status</h3>
                    <?php foreach ($diagnostics['database'] as $test => $result): ?>
                        <div style="margin: 10px 0; padding: 10px; background: <?php echo $result['status'] === 'success' ? '#d4edda' : ($result['status'] === 'warning' ? '#fff3cd' : '#f8d7da'); ?>; border-radius: 4px;">
                            <strong><?php echo esc_html($test); ?>:</strong>
                            <span style="color: <?php echo $result['status'] === 'success' ? '#155724' : ($result['status'] === 'warning' ? '#856404' : '#721c24'); ?>;">
                                <?php echo $result['status'] === 'success' ? '✅' : ($result['status'] === 'warning' ? '⚠️' : '❌'); ?>
                                <?php echo esc_html($result['message']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Table Status -->
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
                    <h3 style="margin-top: 0; color: #333;">🗂️ Table Status</h3>
                    <?php foreach ($diagnostics['tables'] as $table => $result): ?>
                        <div style="margin: 10px 0; padding: 10px; background: <?php echo $result['exists'] ? '#d4edda' : '#f8d7da'; ?>; border-radius: 4px;">
                            <strong><?php echo esc_html($table); ?>:</strong>
                            <span style="color: <?php echo $result['exists'] ? '#155724' : '#721c24'; ?>;">
                                <?php echo $result['exists'] ? '✅ Exists' : '❌ Missing'; ?>
                                <?php if ($result['exists']): ?>
                                    (<?php echo number_format($result['row_count']); ?> rows)
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Gift Save Test -->
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
                    <h3 style="margin-top: 0; color: #333;">🎁 Gift Save Test</h3>
                    <div style="margin: 10px 0; padding: 10px; background: <?php echo $gift_test['status'] === 'success' ? '#d4edda' : '#f8d7da'; ?>; border-radius: 4px;">
                        <strong>Save Functionality:</strong>
                        <span style="color: <?php echo $gift_test['status'] === 'success' ? '#155724' : '#721c24'; ?>;">
                            <?php echo $gift_test['status'] === 'success' ? '✅' : '❌'; ?>
                            <?php echo esc_html($gift_test['message']); ?>
                        </span>
                    </div>
                    
                    <button type="button" class="button button-primary" onclick="runGiftSaveTest()" style="margin-top: 10px;">
                        🧪 Run Live Test
                    </button>
                    
                    <a href="<?php echo add_query_arg('run_server_test', '1', $_SERVER['REQUEST_URI']); ?>" class="button button-secondary" style="margin-top: 10px; margin-left: 5px; text-decoration: none;">
                        🖥️ Server Test
                    </a>
                    
                    <div id="live-test-results" style="margin-top: 10px;"></div>
                </div>
                
            </div>
            
            <!-- Server-Side Test Results -->
            <?php
            if (isset($_GET['run_server_test']) && $_GET['run_server_test'] == '1'):
                $server_test_result = $this->run_server_side_gift_test();
                ?>
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h2>🖥️ Server-Side Test Results</h2>
                    <div style="padding: 15px; background: <?php echo $server_test_result['status'] === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $server_test_result['status'] === 'success' ? '#155724' : '#721c24'; ?>; border-radius: 4px;">
                        <strong>Result:</strong><br>
                        <?php echo $server_test_result['status'] === 'success' ? '✅' : '❌'; ?> <?php echo esc_html($server_test_result['message']); ?>
                    </div>
                </div>
                <?php
            endif;
            ?>
            
            <!-- Emergency Fix Actions -->
            <div style="background: #fff2cc; border: 1px solid #d6b656; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h2 style="margin-top: 0; color: #856404;">🛠️ Emergency Fix Actions</h2>
                
                <p style="margin-bottom: 15px; color: #856404;">
                    <strong>⚠️ Warning:</strong> These actions will modify your database. Use with caution!
                </p>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="button button-secondary" onclick="runEmergencyFix('recreate_tables')">
                        🔄 Recreate Missing Tables
                    </button>
                    <button type="button" class="button button-secondary" onclick="runEmergencyFix('add_sample_data')">
                        📝 Add Sample Data
                    </button>
                    <button type="button" class="button button-secondary" onclick="runEmergencyFix('clear_errors')">
                        🧹 Clear Error Log
                    </button>
                </div>
                
                <div id="emergency-fix-results" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Gift Management Quick Access -->
            <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h2 style="margin-top: 0; color: #0c5460;">🎁 Gift Management Quick Access</h2>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="<?php echo admin_url('admin.php?page=vefify-gifts'); ?>" class="button button-primary">
                        🎁 Go to Gift Management
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=vefify-campaigns'); ?>" class="button button-secondary">
                        📈 View Campaigns
                    </a>
                </div>
                
                <?php if ($gift_test['status'] === 'success'): ?>
                    <p style="color: #0c5460; margin-top: 15px;">
                        ✅ <strong>Good news!</strong> Your gift save functionality is working. You can safely use the gift management interface.
                    </p>
                <?php else: ?>
                    <p style="color: #721c24; margin-top: 15px;">
                        ❌ <strong>Issue detected:</strong> Gift save functionality needs attention. Please run the emergency fixes above.
                    </p>
                <?php endif; ?>
            </div>
            
        </div>
        
        <script>
        function runGiftSaveTest() {
            var results = document.getElementById('live-test-results');
            results.innerHTML = '<div style="padding: 10px; background: #f0f0f1; border-radius: 4px;">🔄 Running test...</div>';
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        var bgColor = data.success ? '#d4edda' : '#f8d7da';
                        var textColor = data.success ? '#155724' : '#721c24';
                        var icon = data.success ? '✅' : '❌';
                        
                        results.innerHTML = '<div style="padding: 10px; background: ' + bgColor + '; color: ' + textColor + '; border-radius: 4px;">' + icon + ' ' + (data.data || 'Unknown response') + '</div>';
                    } catch (e) {
                        results.innerHTML = '<div style="padding: 10px; background: #f8d7da; color: #721c24; border-radius: 4px;">❌ Invalid response: ' + xhr.responseText + '</div>';
                    }
                } else {
                    results.innerHTML = '<div style="padding: 10px; background: #f8d7da; color: #721c24; border-radius: 4px;">❌ Request failed: ' + xhr.status + '</div>';
                }
            };
            
            xhr.send('action=vefify_emergency_test_gift_save&nonce=<?php echo wp_create_nonce('vefify_emergency_nonce'); ?>');
        }
        
        function runEmergencyFix(action) {
            var results = document.getElementById('emergency-fix-results');
            results.innerHTML = '<div style="padding: 10px; background: #f0f0f1; border-radius: 4px;">🔄 Running fix...</div>';
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        var bgColor = data.success ? '#d4edda' : '#f8d7da';
                        var textColor = data.success ? '#155724' : '#721c24';
                        var icon = data.success ? '✅' : '❌';
                        
                        results.innerHTML = '<div style="padding: 10px; background: ' + bgColor + '; color: ' + textColor + '; border-radius: 4px;">' + icon + ' ' + (data.data || 'Unknown response') + '</div>';
                        
                        if (data.success && action === 'recreate_tables') {
                            setTimeout(function() { location.reload(); }, 2000);
                        }
                    } catch (e) {
                        results.innerHTML = '<div style="padding: 10px; background: #f8d7da; color: #721c24; border-radius: 4px;">❌ Invalid response: ' + xhr.responseText + '</div>';
                    }
                } else {
                    results.innerHTML = '<div style="padding: 10px; background: #f8d7da; color: #721c24; border-radius: 4px;">❌ Request failed: ' + xhr.status + '</div>';
                }
            };
            
            xhr.send('action=vefify_emergency_fix_database&fix_action=' + action + '&nonce=<?php echo wp_create_nonce('vefify_emergency_nonce'); ?>');
        }
        </script>
        
        <?php
    }
    
    /**
     * Run comprehensive diagnostics
     */
    private function run_comprehensive_diagnostics() {
        $diagnostics = array(
            'database' => array(),
            'tables' => array()
        );
        
        // Database connection test
        $diagnostics['database']['Connection'] = array(
            'status' => $this->wpdb->last_error ? 'error' : 'success',
            'message' => $this->wpdb->last_error ? $this->wpdb->last_error : 'Connected successfully'
        );
        
        // Centralized database class test
        $diagnostics['database']['Centralized Class'] = array(
            'status' => $this->centralized_db ? 'success' : 'error',
            'message' => $this->centralized_db ? 'Loaded successfully' : 'Failed to load'
        );
        
        // Write permissions test
        $can_write = $this->test_database_write_permissions();
        $diagnostics['database']['Write Permissions'] = array(
            'status' => $can_write ? 'success' : 'error',
            'message' => $can_write ? 'Can write to database' : 'Cannot write to database'
        );
        
        // Table existence and structure
        $required_tables = array('campaigns', 'questions', 'gifts', 'participants');
        
        foreach ($required_tables as $table_key) {
            $table_name = $this->get_table_name($table_key);
            
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SHOW TABLES LIKE %s", 
                $table_name
            ));
            
            $row_count = 0;
            if ($exists) {
                $row_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            }
            
            $diagnostics['tables'][$table_key] = array(
                'exists' => (bool) $exists,
                'table_name' => $table_name,
                'row_count' => intval($row_count)
            );
        }
        
        return $diagnostics;
    }
    
    /**
     * Get table name helper
     */
    private function get_table_name($table_key) {
        if ($this->centralized_db && method_exists($this->centralized_db, 'get_table_name')) {
            return $this->centralized_db->get_table_name($table_key);
        }
        
        $prefix = defined('VEFIFY_QUIZ_TABLE_PREFIX') ? VEFIFY_QUIZ_TABLE_PREFIX : 'vefify_';
        return $this->wpdb->prefix . $prefix . $table_key;
    }
    
    /**
     * Test gift save capability
     */
    private function test_gift_save_capability() {
        try {
            // Check if plugin constants are defined
            if (!defined('VEFIFY_QUIZ_PLUGIN_DIR')) {
                return array(
                    'status' => 'error',
                    'message' => 'Plugin directory constant not defined'
                );
            }
            
            // Check if gift model file exists
            $gift_model_file = VEFIFY_QUIZ_PLUGIN_DIR . 'modules/gifts/class-gift-model.php';
            if (!file_exists($gift_model_file)) {
                return array(
                    'status' => 'error',
                    'message' => 'Gift model file not found'
                );
            }
            
            // Check if gifts table exists
            $table_name = $this->get_table_name('gifts');
            $table_exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SHOW TABLES LIKE %s", 
                $table_name
            ));
            
            if (!$table_exists) {
                return array(
                    'status' => 'error',
                    'message' => 'Gifts table does not exist'
                );
            }
            
            return array(
                'status' => 'success',
                'message' => 'All components ready for gift saving'
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Test database write permissions
     */
    private function test_database_write_permissions() {
        $test_table = $this->get_table_name('campaigns');
        
        $test_name = 'test_permission_' . time();
        $result = $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$test_table} (name, slug, description, start_date, end_date, created_at) 
             VALUES (%s, %s, %s, %s, %s, %s)",
            $test_name, $test_name, 'Permission test', 
            current_time('mysql'), date('Y-m-d H:i:s', strtotime('+1 year')), current_time('mysql')
        ));
        
        if ($result) {
            $this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM {$test_table} WHERE name = %s", 
                $test_name
            ));
            return true;
        }
        
        return false;
    }
    
    /**
     * Run server-side gift test
     */
    private function run_server_side_gift_test() {
        try {
            // Check if plugin constants are defined
            if (!defined('VEFIFY_QUIZ_PLUGIN_DIR')) {
                return array(
                    'status' => 'error',
                    'message' => 'Plugin directory constant not defined'
                );
            }
            
            // Check if gift model file exists
            $gift_model_file = VEFIFY_QUIZ_PLUGIN_DIR . 'modules/gifts/class-gift-model.php';
            if (!file_exists($gift_model_file)) {
                return array(
                    'status' => 'error',
                    'message' => 'Gift model file not found'
                );
            }
            
            // Load and test gift model
            require_once $gift_model_file;
            
            if (!class_exists('Vefify_Gift_Model')) {
                return array(
                    'status' => 'error',
                    'message' => 'Gift model class could not be loaded'
                );
            }
            
            $gift_model = new Vefify_Gift_Model();
            
            // Check if we have a campaign to test with
            $campaign_table = $this->get_table_name('campaigns');
            $campaign_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$campaign_table}");
            
            if ($campaign_count == 0) {
                return array(
                    'status' => 'error',
                    'message' => 'No campaigns available for testing. Please add sample data first.'
                );
            }
            
            // Get first campaign ID
            $campaign_id = $this->wpdb->get_var("SELECT id FROM {$campaign_table} LIMIT 1");
            
            $test_data = array(
                'campaign_id' => $campaign_id,
                'gift_name' => 'Server Test Gift ' . time(),
                'gift_type' => 'voucher',
                'gift_value' => '500 VND',
                'gift_description' => 'Server-side validation test',
                'min_score' => 1,
                'max_score' => 5,
                'max_quantity' => 1,
                'gift_code_prefix' => 'SRV',
                'is_active' => 1
            );
            
            $result = $gift_model->save_gift($test_data);
            
            if (is_array($result) && isset($result['errors'])) {
                return array(
                    'status' => 'error',
                    'message' => 'Validation failed: ' . implode(', ', $result['errors'])
                );
            } elseif ($result === false) {
                return array(
                    'status' => 'error',
                    'message' => 'Save failed. Last DB error: ' . $this->wpdb->last_error
                );
            } else {
                // Success - clean up test data
                $gifts_table = $this->get_table_name('gifts');
                $this->wpdb->delete(
                    $gifts_table,
                    array('id' => $result),
                    array('%d')
                );
                
                return array(
                    'status' => 'success',
                    'message' => 'Gift save test successful! Gift ID: ' . $result . ' (Test data cleaned up)'
                );
            }
            
        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * AJAX: Test gift save functionality
     */
    public function ajax_test_gift_save() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_emergency_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $result = $this->run_server_side_gift_test();
        
        if ($result['status'] === 'success') {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Emergency database fixes
     */
    public function ajax_fix_database() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_emergency_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $action = sanitize_text_field($_POST['fix_action']);
        
        try {
            switch ($action) {
                case 'recreate_tables':
                    if ($this->centralized_db && method_exists($this->centralized_db, 'create_tables')) {
                        $this->centralized_db->create_tables();
                        wp_send_json_success('Tables recreated successfully!');
                    } else {
                        wp_send_json_error('Centralized database not available');
                    }
                    break;
                    
                case 'add_sample_data':
                    if ($this->centralized_db && method_exists($this->centralized_db, 'insert_sample_data')) {
                        $this->centralized_db->insert_sample_data();
                        wp_send_json_success('Sample data added successfully!');
                    } else {
                        wp_send_json_error('Centralized database not available');
                    }
                    break;
                    
                case 'clear_errors':
                    $debug_log = WP_CONTENT_DIR . '/debug.log';
                    if (file_exists($debug_log) && is_writable($debug_log)) {
                        file_put_contents($debug_log, '');
                        wp_send_json_success('Error log cleared!');
                    } else {
                        wp_send_json_error('Cannot access debug log');
                    }
                    break;
                    
                default:
                    wp_send_json_error('Unknown fix action');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Fix failed: ' . $e->getMessage());
        }
    }
}

// Initialize the validation helper
add_action('init', array('Vefify_Quiz_Validation_Helper', 'get_instance'));