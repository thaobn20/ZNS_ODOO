<?php
/**
 * Validation Helper for Vefify Quiz Plugin
 * File: includes/class-validation-helper.php
 * 
 * Add this temporarily to help debug and validate the setup
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Validation_Helper {
    
    public static function run_validation() {
        $results = array();
        
        // 1. Check if main plugin class exists and is loaded
        $results['main_plugin'] = array(
            'test' => 'Main Plugin Class',
            'status' => class_exists('Vefify_Quiz_Plugin') ? 'PASS' : 'FAIL',
            'details' => class_exists('Vefify_Quiz_Plugin') ? 'Plugin class loaded' : 'Plugin class not found'
        );
        
        // 2. Check plugin instance
        if (class_exists('Vefify_Quiz_Plugin')) {
            $plugin = Vefify_Quiz_Plugin::get_instance();
            $results['plugin_instance'] = array(
                'test' => 'Plugin Instance',
                'status' => $plugin ? 'PASS' : 'FAIL',
                'details' => $plugin ? 'Instance created successfully' : 'Failed to create instance'
            );
        }
        
        // 3. Check database class
        $results['database_class'] = array(
            'test' => 'Database Class',
            'status' => class_exists('Vefify_Quiz_Database') ? 'PASS' : 'FAIL',
            'details' => class_exists('Vefify_Quiz_Database') ? 'Database class available' : 'Database class missing'
        );
        
        // 4. Check analytics class
        $results['analytics_class'] = array(
            'test' => 'Analytics Class',
            'status' => class_exists('Vefify_Quiz_Module_Analytics') ? 'PASS' : 'FAIL',
            'details' => class_exists('Vefify_Quiz_Module_Analytics') ? 'Analytics class available' : 'Analytics class missing'
        );
        
        // 5. Check database tables
			if (class_exists('Vefify_Quiz_Database')) {
						$database = new Vefify_Quiz_Database();
						$missing_tables = $database->verify_tables();
						
						// Fix: Handle array properly
						if (is_array($missing_tables)) {
							$status = empty($missing_tables) ? 'PASS' : 'WARN';
							$details = empty($missing_tables) ? 'All tables exist' : 'Missing: ' . implode(', ', $missing_tables);
						} else {
							// Fallback for unexpected return type
							$status = 'WARN';
							$details = 'Unexpected return type from verify_tables()';
						}
						
						$results['database_tables'] = array(
							'test' => 'Database Tables',
							'status' => $status,
							'details' => $details
						);
					}
        
        // 6. Check module files exist
        $module_files = array(
            'questions' => 'modules/questions/class-question-module.php',
            'campaigns' => 'modules/campaigns/class-campaign-module.php',
            'gifts' => 'modules/gifts/class-gift-module.php',
            'participants' => 'modules/participants/class-participant-module.php',
            'analytics' => 'modules/analytics/class-analytics-module.php'
        );
        
        foreach ($module_files as $module => $file) {
            $full_path = VEFIFY_QUIZ_PLUGIN_DIR . $file;
            $results["module_file_{$module}"] = array(
                'test' => "Module File: {$module}",
                'status' => file_exists($full_path) ? 'PASS' : 'FAIL',
                'details' => file_exists($full_path) ? 'File exists' : 'File missing: ' . $file
            );
        }
        
        // 7. Check module classes
        $module_classes = array(
            'questions' => 'Vefify_Question_Module',
            'campaigns' => 'Vefify_Campaign_Module',
            'gifts' => 'Vefify_Gift_Module',
            'participants' => 'Vefify_Participant_Module',
            'analytics' => 'Vefify_Analytics_Module'
        );
        
        foreach ($module_classes as $module => $class) {
            $results["module_class_{$module}"] = array(
                'test' => "Module Class: {$module}",
                'status' => class_exists($class) ? 'PASS' : 'FAIL',
                'details' => class_exists($class) ? 'Class loaded' : 'Class not found: ' . $class
            );
        }
        
        // 8. Check module instances (if plugin is loaded)
        if (class_exists('Vefify_Quiz_Plugin')) {
            $plugin = Vefify_Quiz_Plugin::get_instance();
            
            foreach (array_keys($module_classes) as $module) {
                $has_module = $plugin->has_module($module);
                $results["module_instance_{$module}"] = array(
                    'test' => "Module Instance: {$module}",
                    'status' => $has_module ? 'PASS' : 'FAIL',
                    'details' => $has_module ? 'Module loaded and instantiated' : 'Module not loaded'
                );
            }
        }
        
        // 9. Check WordPress version compatibility
        $wp_version = get_bloginfo('version');
        $results['wp_version'] = array(
            'test' => 'WordPress Version',
            'status' => version_compare($wp_version, '5.0', '>=') ? 'PASS' : 'WARN',
            'details' => "WordPress {$wp_version}" . (version_compare($wp_version, '5.0', '>=') ? ' (Compatible)' : ' (May have issues)')
        );
        
        // 10. Check PHP version
        $php_version = PHP_VERSION;
        $results['php_version'] = array(
            'test' => 'PHP Version',
            'status' => version_compare($php_version, '7.4', '>=') ? 'PASS' : 'WARN',
            'details' => "PHP {$php_version}" . (version_compare($php_version, '7.4', '>=') ? ' (Compatible)' : ' (May have issues)')
        );
        
        return $results;
    }
    
    public static function display_validation_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $results = self::run_validation();
        
        echo '<div class="wrap">';
        echo '<h1>🔧 Vefify Quiz Validation Report</h1>';
        echo '<p>This page helps identify any setup issues with the plugin.</p>';
        
        // Summary
        $total_tests = count($results);
        $passed_tests = count(array_filter($results, function($r) { return $r['status'] === 'PASS'; }));
        $failed_tests = count(array_filter($results, function($r) { return $r['status'] === 'FAIL'; }));
        $warning_tests = count(array_filter($results, function($r) { return $r['status'] === 'WARN'; }));
        
        echo '<div style="background: #fff; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #0073aa;">';
        echo '<h3>📊 Summary</h3>';
        echo "<p><strong>Total Tests:</strong> {$total_tests}</p>";
        echo "<p><strong>✅ Passed:</strong> {$passed_tests}</p>";
        echo "<p><strong>⚠️ Warnings:</strong> {$warning_tests}</p>";
        echo "<p><strong>❌ Failed:</strong> {$failed_tests}</p>";
        echo '</div>';
        
        // Detailed Results
        echo '<div style="background: #fff; padding: 15px; border-radius: 5px; margin: 15px 0;">';
        echo '<h3>📋 Detailed Results</h3>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Test</th><th>Status</th><th>Details</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($results as $result) {
            $status_color = array(
                'PASS' => '#46b450',
                'FAIL' => '#dc3232', 
                'WARN' => '#ffb900'
            );
            
            $status_icon = array(
                'PASS' => '✅',
                'FAIL' => '❌',
                'WARN' => '⚠️'
            );
            
            echo '<tr>';
            echo '<td>' . esc_html($result['test']) . '</td>';
            echo '<td style="color: ' . $status_color[$result['status']] . '; font-weight: bold;">';
            echo $status_icon[$result['status']] . ' ' . $result['status'];
            echo '</td>';
            echo '<td>' . esc_html($result['details']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
        
        // Quick Actions
        echo '<div style="background: #fff; padding: 15px; border-radius: 5px; margin: 15px 0;">';
        echo '<h3>🚀 Quick Actions</h3>';
        
        if ($failed_tests > 0) {
            echo '<p><strong>Issues Found:</strong> Some components are not working properly.</p>';
            echo '<a href="' . wp_nonce_url(add_query_arg('action', 'recreate_tables'), 'recreate_tables') . '" class="button button-primary">Recreate Database Tables</a> ';
            echo '<a href="' . add_query_arg('action', 'clear_cache') . '" class="button">Clear Cache</a> ';
        } else {
            echo '<p><strong>✅ All systems operational!</strong> The plugin appears to be working correctly.</p>';
        }
        
        echo '<a href="' . admin_url('admin.php?page=vefify-dashboard') . '" class="button button-primary">Go to Dashboard</a> ';
        echo '<a href="' . add_query_arg('refresh', '1') . '" class="button">Refresh Validation</a>';
        echo '</div>';
        
        // Debug Info
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<div style="background: #f1f1f1; padding: 15px; border-radius: 5px; margin: 15px 0;">';
            echo '<h3>🔍 Debug Information</h3>';
            echo '<p><strong>Plugin Directory:</strong> ' . VEFIFY_QUIZ_PLUGIN_DIR . '</p>';
            echo '<p><strong>Plugin URL:</strong> ' . VEFIFY_QUIZ_PLUGIN_URL . '</p>';
            echo '<p><strong>Plugin Version:</strong> ' . VEFIFY_QUIZ_VERSION . '</p>';
            echo '<p><strong>Database Prefix:</strong> ' . (defined('VEFIFY_QUIZ_TABLE_PREFIX') ? VEFIFY_QUIZ_TABLE_PREFIX : 'Not defined') . '</p>';
            echo '<p><strong>WordPress Version:</strong> ' . get_bloginfo('version') . '</p>';
            echo '<p><strong>PHP Version:</strong> ' . PHP_VERSION . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Handle actions
        if (isset($_GET['action'])) {
            $action = sanitize_text_field($_GET['action']);
            
            if ($action === 'recreate_tables' && wp_verify_nonce($_GET['_wpnonce'], 'recreate_tables')) {
                if (class_exists('Vefify_Quiz_Database')) {
                    $database = new Vefify_Quiz_Database();
                    try {
                        $database->create_tables();
                        echo '<div class="notice notice-success"><p>Database tables recreated successfully!</p></div>';
                    } catch (Exception $e) {
                        echo '<div class="notice notice-error"><p>Error recreating tables: ' . esc_html($e->getMessage()) . '</p></div>';
                    }
                }
            }
            
            if ($action === 'clear_cache') {
                wp_cache_flush();
                echo '<div class="notice notice-success"><p>Cache cleared successfully!</p></div>';
            }
        }
    }
    
    /**
     * Add validation page to admin menu temporarily
     */
    public static function add_validation_menu() {
        add_submenu_page(
            'vefify-dashboard',
            'System Validation',
            '🔧 Validation',
            'manage_options',
            'vefify-validation',
            array(self::class, 'display_validation_page')
        );
    }
    
    /**
     * Quick status check for admin notices
     */
public static function check_critical_issues() {
        $issues = array();
        
        // Check if database tables exist
        if (class_exists('Vefify_Quiz_Database')) {
            $database = new Vefify_Quiz_Database();
            $missing_tables = $database->verify_tables();
            
            // Fix: Handle array properly
            if (is_array($missing_tables) && !empty($missing_tables)) {
                $issues[] = 'Missing database tables: ' . implode(', ', $missing_tables);
            }
        }
        
        // Check if core classes are loaded
        if (!class_exists('Vefify_Quiz_Plugin')) {
            $issues[] = 'Main plugin class not loaded';
        }
        
        return $issues;
    }
	/**
 * Emergency Database Cleanup Tool
 * Add this temporarily to your validation helper or run directly
 */
public static function emergency_cleanup_database() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    echo '<div class="wrap">';
    echo '<h1>🚨 Emergency Database Cleanup</h1>';
    echo '<div class="notice notice-warning"><p><strong>Warning:</strong> This will delete all existing quiz data!</p></div>';
    
    if (isset($_GET['confirm_cleanup']) && wp_verify_nonce($_GET['_wpnonce'], 'emergency_cleanup')) {
        
        echo '<h3>🔧 Cleaning up database...</h3>';
        
        // Tables to clean up
        $tables = array(
            $table_prefix . 'analytics',
            $table_prefix . 'quiz_sessions', 
            $table_prefix . 'participants',
            $table_prefix . 'question_options',
            $table_prefix . 'questions',
            $table_prefix . 'gifts',
            $table_prefix . 'campaigns'
        );
        
        // Drop all tables
        foreach ($tables as $table) {
            $result = $wpdb->query("DROP TABLE IF EXISTS {$table}");
            if ($result !== false) {
                echo "<p>✅ Dropped table: {$table}</p>";
            } else {
                echo "<p>⚠️ Could not drop table: {$table}</p>";
            }
        }
        
        echo '<div class="notice notice-success"><p>✅ Database cleanup completed!</p></div>';
        echo '<p><strong>Next step:</strong> <a href="' . admin_url('admin.php?page=vefify-emergency-validation') . '" class="button button-primary">Recreate Tables</a></p>';
        
    } else {
        echo '<h3>⚠️ Database Cleanup Required</h3>';
        echo '<p>Your database has conflicting table structures that need to be cleaned up.</p>';
        echo '<p><strong>This will:</strong></p>';
        echo '<ul>';
        echo '<li>❌ Delete all existing quiz data (campaigns, questions, participants)</li>';
        echo '<li>🗑️ Drop all quiz-related tables</li>';
        echo '<li>🔄 Allow clean recreation of tables with correct structure</li>';
        echo '</ul>';
        
        $cleanup_url = wp_nonce_url(
            add_query_arg('confirm_cleanup', '1'), 
            'emergency_cleanup'
        );
        
        echo '<p><a href="' . $cleanup_url . '" class="button button-primary" onclick="return confirm(\'Are you sure? This will delete all quiz data!\')">🚨 Confirm Database Cleanup</a></p>';
        echo '<p><a href="' . admin_url('admin.php?page=vefify-emergency-validation') . '" class="button">← Back to Validation</a></p>';
    }
    
    echo '</div>';
}
}

// Add validation page if in debug mode or if specifically requested
if ((defined('WP_DEBUG') && WP_DEBUG) || isset($_GET['vefify_validation'])) {
    add_action('admin_menu', array('Vefify_Quiz_Validation_Helper', 'add_validation_menu'), 100);
}

// Add admin notice for critical issues
add_action('admin_notices', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'vefify') !== false) {
        $issues = Vefify_Quiz_Validation_Helper::check_critical_issues();
        
        if (!empty($issues)) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Vefify Quiz:</strong> ' . implode('. ', $issues) . '.</p>';
            echo '<p><a href="' . admin_url('admin.php?page=vefify-validation') . '">Run System Validation</a></p>';
            echo '</div>';
        }
    }
});