<?php

/**
 * Activation Process Testing
 * File: tests/test-activation.php
 */

class ActivationTest {
    
    /**
     * Test complete activation process
     */
    public static function test_activation() {
        echo "<h2>🧪 Testing Plugin Activation Process</h2>";
        
        // Test 1: System Requirements
        echo "<h3>1. System Requirements Check</h3>";
        $requirements_errors = SystemCheck::check_requirements();
        
        if (empty($requirements_errors)) {
            echo "✅ All system requirements met<br>";
        } else {
            echo "❌ System requirements issues:<br>";
            foreach ($requirements_errors as $error) {
                echo "- " . esc_html($error) . "<br>";
            }
        }
        
        // Test 2: Database Creation
        echo "<h3>2. Database Tables Test</h3>";
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $tables = ['campaigns', 'questions', 'question_options', 'gifts', 'quiz_users', 'quiz_sessions', 'analytics'];
        
        foreach ($tables as $table) {
            $table_name = $table_prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
                echo "✅ Table '$table' exists with $count records<br>";
            } else {
                echo "❌ Table '$table' missing<br>";
            }
        }
        
        // Test 3: Sample Data
        echo "<h3>3. Sample Data Test</h3>";
        $campaign_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_prefix}campaigns`");
        $question_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_prefix}questions`");
        $gift_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_prefix}gifts`");
        
        echo "📊 Sample data counts:<br>";
        echo "- Campaigns: $campaign_count<br>";
        echo "- Questions: $question_count<br>";
        echo "- Gifts: $gift_count<br>";
        
        // Test 4: API Endpoints
        echo "<h3>4. API Endpoints Test</h3>";
        $api_tests = [
            '/wp-json/vefify/v1/campaigns' => 'GET',
            '/wp-json/vefify/v1/campaigns/1' => 'GET'
        ];
        
        foreach ($api_tests as $endpoint => $method) {
            $url = home_url($endpoint);
            $response = wp_remote_get($url);
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                if ($status_code === 200) {
                    echo "✅ API endpoint '$endpoint' working<br>";
                } else {
                    echo "❌ API endpoint '$endpoint' returned status $status_code<br>";
                }
            } else {
                echo "❌ API endpoint '$endpoint' failed: " . $response->get_error_message() . "<br>";
            }
        }
        
        // Test 5: File Permissions
        echo "<h3>5. File Permissions Test</h3>";
        $upload_dir = wp_upload_dir();
        $vefify_dir = $upload_dir['basedir'] . '/vefify-quiz/';
        
        if (is_dir($vefify_dir) && is_writable($vefify_dir)) {
            echo "✅ Vefify upload directory created and writable<br>";
        } else {
            echo "❌ Vefify upload directory issue<br>";
        }
        
        echo "<h3>📋 Test Summary</h3>";
        echo "<p>Activation testing completed. Check results above for any issues.</p>";
    }
}

/**
 * Manual Database Setup (Emergency)
 * For cases where automatic activation fails
 */

class ManualSetup {
    
    /**
     * Emergency database setup if activation fails
     */
    public static function emergency_setup() {
        echo "<div class='wrap'>";
        echo "<h1>🚨 Emergency Database Setup</h1>";
        echo "<p>Use this if the automatic plugin activation failed.</p>";
        
        if (isset($_POST['run_setup'])) {
            try {
                $installer = new VefifyQuiz\Installer();
                
                echo "<h3>🔧 Running Manual Setup...</h3>";
                
                // Create tables
                echo "<p>Creating database tables...</p>";
                $installer->create_tables();
                echo "✅ Database tables created<br>";
                
                // Insert sample data
                echo "<p>Inserting sample data...</p>";
                $installer->insert_sample_data();
                echo "✅ Sample data inserted<br>";
                
                // Set options
                echo "<p>Setting default options...</p>";
                $installer->set_default_options();
                echo "✅ Default options set<br>";
                
                // Create directories
                echo "<p>Creating directories...</p>";
                $installer->create_directories();
                echo "✅ Directories created<br>";
                
                echo "<div class='notice notice-success'>";
                echo "<p><strong>✅ Manual setup completed successfully!</strong></p>";
                echo "<p><a href='" . admin_url('admin.php?page=vefify-quiz') . "' class='button button-primary'>Go to Dashboard</a></p>";
                echo "</div>";
                
            } catch (Exception $e) {
                echo "<div class='notice notice-error'>";
                echo "<p><strong>❌ Manual setup failed:</strong> " . esc_html($e->getMessage()) . "</p>";
                echo "</div>";
            }
        } else {
            ?>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th>Current Status</th>
                        <td>
                            <?php
                            global $wpdb;
                            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}vefify_campaigns'");
                            if ($table_exists) {
                                echo "🟡 Some tables may exist";
                            } else {
                                echo "🔴 Tables not found";
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Action</th>
                        <td>
                            <p>This will:</p>
                            <ul>
                                <li>Create all required database tables</li>
                                <li>Insert sample campaign and questions</li>
                                <li>Set default plugin options</li>
                                <li>Create upload directories</li>
                            </ul>
                            
                            <p><strong>Warning:</strong> This may overwrite existing data.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('🚀 Run Manual Setup', 'primary', 'run_setup'); ?>
            </form>
            <?php
        }
        
        echo "</div>";
    }
}

// Add emergency setup to admin menu (only if tables don't exist)
add_action('admin_menu', function() {
    global $wpdb;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}vefify_campaigns'");
    
    if (!$table_exists) {
        add_management_page(
            'Vefify Emergency Setup',
            'Vefify Emergency Setup',
            'manage_options',
            'vefify-emergency-setup',
            ['ManualSetup', 'emergency_setup']
        );
    }
});