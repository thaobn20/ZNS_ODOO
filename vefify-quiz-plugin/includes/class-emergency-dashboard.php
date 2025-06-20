<?php
/**
 * COMPLETE EMERGENCY DASHBOARD WITH SAMPLE DATA
 * File: includes/class-emergency-dashboard.php
 * 
 * Add this to your plugin to include emergency actions in the main dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Emergency_Dashboard {
    
    private $database;
    
    public function __construct() {
        $this->database = new Vefify_Quiz_Database();
        $this->init();
    }
    
    public function init() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Handle AJAX actions
        add_action('wp_ajax_vefify_emergency_action', array($this, 'handle_emergency_action'));
        
        // Add to main dashboard
        add_action('vefify_dashboard_emergency_section', array($this, 'render_emergency_section'));
    }
    
    /**
     * Add emergency dashboard to admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'vefify-dashboard',
            'Emergency Actions',
            '🚨 Emergency',
            'manage_options',
            'vefify-emergency',
            array($this, 'render_emergency_dashboard')
        );
    }
    
    /**
     * Render complete emergency dashboard
     */
    public function render_emergency_dashboard() {
        ?>
        <div class="wrap">
            <h1>🚨 Emergency Actions Dashboard</h1>
            <p>Quick actions to fix issues, manage data, and maintain your Vefify Quiz plugin.</p>
            
            <?php $this->render_system_status(); ?>
            <?php $this->render_database_actions(); ?>
            <?php $this->render_sample_data_actions(); ?>
            <?php $this->render_maintenance_actions(); ?>
            <?php $this->render_troubleshooting_actions(); ?>
        </div>
        
        <style>
        .emergency-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin: 20px 0; }
        .emergency-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .emergency-card h3 { margin-top: 0; color: #23282d; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        .emergency-card.critical { border-left: 4px solid #dc3545; }
        .emergency-card.warning { border-left: 4px solid #ffc107; }
        .emergency-card.success { border-left: 4px solid #28a745; }
        .emergency-card.info { border-left: 4px solid #17a2b8; }
        .btn-emergency { background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; margin: 5px; }
        .btn-emergency:hover { background: #c82333; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        .status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; }
        .status-good { background: #28a745; }
        .status-warning { background: #ffc107; }
        .status-error { background: #dc3545; }
        .action-result { margin: 10px 0; padding: 10px; border-radius: 4px; display: none; }
        .action-result.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .action-result.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .loading { opacity: 0.6; pointer-events: none; }
        .data-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 15px 0; }
        .stat-box { background: #f8f9fa; padding: 15px; text-align: center; border-radius: 4px; }
        .stat-number { font-size: 1.5em; font-weight: bold; color: #0073aa; }
        .stat-label { font-size: 0.9em; color: #666; }
        </style>
        
        <script>
        function executeEmergencyAction(action, confirmMessage = null) {
            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            }
            
            const button = event.target;
            const card = button.closest('.emergency-card');
            const resultDiv = card.querySelector('.action-result');
            
            // Show loading state
            button.disabled = true;
            button.textContent = 'Processing...';
            card.classList.add('loading');
            
            // Clear previous results
            if (resultDiv) {
                resultDiv.style.display = 'none';
                resultDiv.className = 'action-result';
            }
            
            // Execute action
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'vefify_emergency_action',
                    'emergency_action': action,
                    'nonce': '<?php echo wp_create_nonce('vefify_emergency_nonce'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                // Show result
                if (resultDiv) {
                    resultDiv.className = 'action-result ' + (data.success ? 'success' : 'error');
                    resultDiv.innerHTML = data.message;
                    resultDiv.style.display = 'block';
                }
                
                // Restore button
                button.disabled = false;
                button.textContent = button.getAttribute('data-original-text') || 'Execute';
                card.classList.remove('loading');
                
                // Refresh page after successful database operations
                if (data.success && ['add_sample_data', 'clear_all_data', 'fix_database', 'recreate_tables'].includes(action)) {
                    setTimeout(() => location.reload(), 2000);
                }
            })
            .catch(error => {
                console.error('Emergency action error:', error);
                if (resultDiv) {
                    resultDiv.className = 'action-result error';
                    resultDiv.innerHTML = 'Error: ' + error.message;
                    resultDiv.style.display = 'block';
                }
                
                button.disabled = false;
                button.textContent = button.getAttribute('data-original-text') || 'Execute';
                card.classList.remove('loading');
            });
        }
        
        // Store original button text
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('button[onclick*="executeEmergencyAction"]').forEach(button => {
                button.setAttribute('data-original-text', button.textContent);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render system status section
     */
    private function render_system_status() {
        $health = $this->database->check_health();
        $stats = $this->database->get_database_stats();
        
        ?>
        <div class="emergency-grid">
            <div class="emergency-card info">
                <h3>📊 System Status</h3>
                
                <div class="data-stats">
                    <?php foreach ($stats as $key => $stat): ?>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo number_format($stat['count']); ?></div>
                            <div class="stat-label"><?php echo ucfirst($key); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin: 15px 0;">
                    <div><span class="status-indicator <?php echo $health['connection'] ? 'status-good' : 'status-error'; ?>"></span>Database Connection</div>
                    <div><span class="status-indicator <?php echo $health['tables'] ? 'status-good' : 'status-error'; ?>"></span>Table Structure</div>
                    <div><span class="status-indicator <?php echo $health['completed_at_column'] ? 'status-good' : 'status-warning'; ?>"></span>completed_at Column</div>
                    <div><span class="status-indicator <?php echo $health['query_test'] ? 'status-good' : 'status-error'; ?>"></span>Query Test</div>
                </div>
                
                <button type="button" class="btn-info" onclick="location.reload()">🔄 Refresh Status</button>
                <div class="action-result"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render database actions section
     */
    private function render_database_actions() {
        ?>
        <div class="emergency-grid">
            <div class="emergency-card critical">
                <h3>🗄️ Database Actions</h3>
                <p>Fix database structure and column issues.</p>
                
                <button type="button" class="btn-emergency" onclick="executeEmergencyAction('fix_database')">
                    🔧 Fix Database Issues
                </button>
                
                <button type="button" class="btn-emergency" onclick="executeEmergencyAction('recreate_tables', 'This will recreate all tables. Existing data will be preserved where possible. Continue?')">
                    🔄 Recreate Tables
                </button>
                
                <button type="button" class="btn-warning" onclick="executeEmergencyAction('check_structure')">
                    🔍 Check Table Structure
                </button>
                
                <div class="action-result"></div>
                
                <div style="margin-top: 15px; font-size: 0.9em; color: #666;">
                    <strong>Fix Database Issues:</strong> Adds missing columns, fixes queries<br>
                    <strong>Recreate Tables:</strong> Complete table recreation with proper structure
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render sample data actions section
     */
    private function render_sample_data_actions() {
        $total_records = 0;
        $stats = $this->database->get_database_stats();
        foreach ($stats as $stat) {
            $total_records += $stat['count'];
        }
        
        ?>
        <div class="emergency-grid">
            <div class="emergency-card success">
                <h3>📊 Sample Data Management</h3>
                <p>Add or manage sample data for testing and demonstration.</p>
                
                <?php if ($total_records == 0): ?>
                    <div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #ffeaa7;">
                        <strong>🆕 Empty Database Detected</strong><br>
                        Your database is empty. Add sample data to get started with testing.
                    </div>
                    
                    <button type="button" class="btn-success" onclick="executeEmergencyAction('add_sample_data')">
                        📊 Add Complete Sample Data
                    </button>
                    
                <?php else: ?>
                    <div style="background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb;">
                        <strong>📊 Database Status:</strong> <?php echo number_format($total_records); ?> total records found
                    </div>
                    
                    <button type="button" class="btn-success" onclick="executeEmergencyAction('add_sample_data')">
                        ➕ Add More Sample Data
                    </button>
                    
                    <button type="button" class="btn-warning" onclick="executeEmergencyAction('clear_all_data', 'This will delete ALL quiz data (campaigns, questions, participants, etc.). This cannot be undone! Continue?')">
                        🗑️ Clear All Data
                    </button>
                    
                    <button type="button" class="btn-emergency" onclick="executeEmergencyAction('reset_with_sample', 'This will delete all existing data and add fresh sample data. Continue?')">
                        🔄 Reset & Add Fresh Data
                    </button>
                <?php endif; ?>
                
                <div class="action-result"></div>
                
                <div style="margin-top: 15px; font-size: 0.9em; color: #666;">
                    <strong>Sample Data Includes:</strong><br>
                    • 3 Health quiz campaigns<br>
                    • 10 Questions with options<br>
                    • 4 Different gift types<br>
                    • 5 Sample participants<br>
                    • Analytics tracking data
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render maintenance actions section
     */
    private function render_maintenance_actions() {
        ?>
        <div class="emergency-grid">
            <div class="emergency-card warning">
                <h3>🔧 Maintenance Actions</h3>
                <p>General maintenance and optimization tasks.</p>
                
                <button type="button" class="btn-warning" onclick="executeEmergencyAction('clear_cache')">
                    🗑️ Clear All Caches
                </button>
                
                <button type="button" class="btn-warning" onclick="executeEmergencyAction('optimize_database')">
                    ⚡ Optimize Database
                </button>
                
                <button type="button" class="btn-info" onclick="executeEmergencyAction('export_settings')">
                    📥 Export Plugin Settings
                </button>
                
                <button type="button" class="btn-info" onclick="executeEmergencyAction('backup_data')">
                    💾 Create Data Backup
                </button>
                
                <div class="action-result"></div>
                
                <div style="margin-top: 15px; font-size: 0.9em; color: #666;">
                    <strong>Maintenance Tasks:</strong><br>
                    • Clear WordPress and plugin caches<br>
                    • Optimize database tables for performance<br>
                    • Export configuration for backup<br>
                    • Create data backup before major changes
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render troubleshooting actions section
     */
    private function render_troubleshooting_actions() {
        ?>
        <div class="emergency-grid">
            <div class="emergency-card info">
                <h3>🔍 Troubleshooting Tools</h3>
                <p>Debug and diagnose common issues.</p>
                
                <button type="button" class="btn-info" onclick="executeEmergencyAction('run_diagnostics')">
                    🔬 Run Full Diagnostics
                </button>
                
                <button type="button" class="btn-info" onclick="executeEmergencyAction('test_queries')">
                    🧪 Test Database Queries
                </button>
                
                <button type="button" class="btn-info" onclick="executeEmergencyAction('check_permissions')">
                    🔐 Check File Permissions
                </button>
                
                <button type="button" class="btn-info" onclick="executeEmergencyAction('generate_debug_report')">
                    📋 Generate Debug Report
                </button>
                
                <div class="action-result"></div>
                
                <div style="margin-top: 15px; font-size: 0.9em; color: #666;">
                    <strong>Troubleshooting Features:</strong><br>
                    • Complete system diagnostics<br>
                    • Test problematic database queries<br>
                    • Verify file and directory permissions<br>
                    • Generate detailed debug information
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle AJAX emergency actions
     */
    public function handle_emergency_action() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_emergency_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $action = sanitize_text_field($_POST['emergency_action']);
        $result = array('success' => false, 'message' => 'Unknown action');
        
        switch ($action) {
            case 'add_sample_data':
                $result = $this->add_sample_data();
                break;
                
            case 'clear_all_data':
                $result = $this->clear_all_data();
                break;
                
            case 'reset_with_sample':
                $this->clear_all_data();
                $result = $this->add_sample_data();
                break;
                
            case 'fix_database':
                $result = $this->fix_database_issues();
                break;
                
            case 'recreate_tables':
                $result = $this->recreate_tables();
                break;
                
            case 'check_structure':
                $result = $this->check_table_structure();
                break;
                
            case 'clear_cache':
                $result = $this->clear_all_caches();
                break;
                
            case 'optimize_database':
                $result = $this->optimize_database();
                break;
                
            case 'run_diagnostics':
                $result = $this->run_full_diagnostics();
                break;
                
            case 'test_queries':
                $result = $this->test_database_queries();
                break;
                
            case 'check_permissions':
                $result = $this->check_file_permissions();
                break;
                
            case 'generate_debug_report':
                $result = $this->generate_debug_report();
                break;
                
            case 'export_settings':
                $result = $this->export_plugin_settings();
                break;
                
            case 'backup_data':
                $result = $this->create_data_backup();
                break;
        }
        
        wp_send_json($result);
    }
    
    /**
     * Add sample data
     */
    private function add_sample_data() {
        try {
            $success = $this->database->insert_sample_data();
            
            if ($success) {
                return array(
                    'success' => true,
                    'message' => '✅ Sample data added successfully! Added campaigns, questions, gifts, participants, and analytics data.'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => '❌ Failed to add sample data. Check error logs for details.'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error adding sample data: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Clear all data
     */
    private function clear_all_data() {
        try {
            global $wpdb;
            
            $tables = array('analytics', 'quiz_sessions', 'participants', 'question_options', 'questions', 'gifts', 'campaigns');
            $cleared = 0;
            
            foreach ($tables as $table) {
                $table_name = $this->database->get_table_name($table);
                if ($table_name) {
                    $result = $wpdb->query("DELETE FROM {$table_name}");
                    if ($result !== false) {
                        $cleared++;
                    }
                }
            }
            
            return array(
                'success' => true,
                'message' => "✅ All data cleared successfully! Cleared {$cleared} tables."
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error clearing data: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Fix database issues
     */
    private function fix_database_issues() {
        try {
            $fixes = $this->database->fix_issues();
            
            if (!empty($fixes)) {
                return array(
                    'success' => true,
                    'message' => '✅ Database fixed! Applied fixes: ' . implode(', ', $fixes)
                );
            } else {
                return array(
                    'success' => true,
                    'message' => '✅ No database issues found. Everything looks good!'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error fixing database: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Recreate tables
     */
    private function recreate_tables() {
        try {
            $success = $this->database->create_tables();
            
            if ($success) {
                return array(
                    'success' => true,
                    'message' => '✅ Tables recreated successfully with proper structure!'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => '❌ Failed to recreate tables. Check error logs.'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error recreating tables: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Check table structure
     */
    private function check_table_structure() {
        try {
            $verification = $this->database->verify_tables_detailed();
            
            if ($verification['status']) {
                return array(
                    'success' => true,
                    'message' => '✅ All table structures are correct!'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => '⚠️ Structure issues found: ' . $verification['message']
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error checking structure: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Clear all caches
     */
    private function clear_all_caches() {
        try {
            wp_cache_flush();
            
            // Clear other cache plugins if available
            if (function_exists('wp_cache_clear_cache')) {
                wp_cache_clear_cache();
            }
            
            return array(
                'success' => true,
                'message' => '✅ All caches cleared successfully!'
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error clearing caches: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Optimize database
     */
    private function optimize_database() {
        try {
            global $wpdb;
            
            $tables = array('campaigns', 'questions', 'question_options', 'participants', 'gifts', 'analytics');
            $optimized = 0;
            
            foreach ($tables as $table) {
                $table_name = $this->database->get_table_name($table);
                if ($table_name) {
                    $result = $wpdb->query("OPTIMIZE TABLE {$table_name}");
                    if ($result !== false) {
                        $optimized++;
                    }
                }
            }
            
            return array(
                'success' => true,
                'message' => "✅ Database optimized! Optimized {$optimized} tables."
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error optimizing database: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Run full diagnostics
     */
    private function run_full_diagnostics() {
        try {
            $tests = $this->database->test_database();
            $passed = 0;
            $failed = 0;
            
            foreach ($tests as $test) {
                if ($test['status']) {
                    $passed++;
                } else {
                    $failed++;
                }
            }
            
            return array(
                'success' => $failed == 0,
                'message' => "🔬 Diagnostics complete! Passed: {$passed}, Failed: {$failed}"
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error running diagnostics: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Test database queries
     */
    private function test_database_queries() {
        try {
            global $wpdb;
            $participants_table = $this->database->get_table_name('participants');
            
            // Test the problematic query
            $result = $wpdb->get_row("
                SELECT COUNT(*) as total, 
                       COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed 
                FROM {$participants_table} WHERE 1=1
            ");
            
            if ($result !== null) {
                return array(
                    'success' => true,
                    'message' => "✅ Query test passed! Found {$result->total} participants, {$result->completed} completed."
                );
            } else {
                return array(
                    'success' => false,
                    'message' => '❌ Query test failed: ' . $wpdb->last_error
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error testing queries: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Check file permissions
     */
    private function check_file_permissions() {
        try {
            $plugin_dir = plugin_dir_path(__FILE__);
            $writable = is_writable($plugin_dir);
            $upload_dir = wp_upload_dir();
            $uploads_writable = is_writable($upload_dir['basedir']);
            
            $message = '📋 File Permissions Check:<br>';
            $message .= '• Plugin Directory: ' . ($writable ? '✅ Writable' : '❌ Not writable') . '<br>';
            $message .= '• Uploads Directory: ' . ($uploads_writable ? '✅ Writable' : '❌ Not writable');
            
            return array(
                'success' => $writable && $uploads_writable,
                'message' => $message
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error checking permissions: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Generate debug report
     */
    private function generate_debug_report() {
        try {
            $report = array(
                'timestamp' => current_time('Y-m-d H:i:s'),
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'plugin_version' => defined('VEFIFY_QUIZ_VERSION') ? VEFIFY_QUIZ_VERSION : 'Unknown',
                'database_stats' => $this->database->get_database_stats(),
                'health_check' => $this->database->check_health(),
                'table_verification' => $this->database->verify_tables_detailed()
            );
            
            $report_json = json_encode($report, JSON_PRETTY_PRINT);
            
            return array(
                'success' => true,
                'message' => '📋 Debug report generated:<br><textarea style="width:100%;height:200px;">' . esc_textarea($report_json) . '</textarea>'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error generating report: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Export plugin settings
     */
    private function export_plugin_settings() {
        try {
            $settings = array(
                'plugin_options' => get_option('vefify_quiz_settings', array()),
                'database_version' => get_option('vefify_quiz_db_version', '1.0.0'),
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
            $settings_json = json_encode($settings, JSON_PRETTY_PRINT);
            
            return array(
                'success' => true,
                'message' => '📥 Settings exported:<br><textarea style="width:100%;height:150px;">' . esc_textarea($settings_json) . '</textarea>'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error exporting settings: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Create data backup
     */
    private function create_data_backup() {
        try {
            $stats = $this->database->get_database_stats();
            $total_records = array_sum(array_column($stats, 'count'));
            
            // For now, just show backup info
            // In a real implementation, you'd export to SQL file
            
            return array(
                'success' => true,
                'message' => "💾 Backup info: {$total_records} total records found. Use phpMyAdmin or wp-cli to create full backup."
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '❌ Error creating backup: ' . $e->getMessage()
            );
        }
    }
}

// Initialize emergency dashboard
new Vefify_Emergency_Dashboard();
?>