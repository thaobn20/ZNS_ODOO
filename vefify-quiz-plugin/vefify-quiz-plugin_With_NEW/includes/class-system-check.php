<?php
/**
 * System Requirements Check
 * File: includes/class-system-check.php
 */

class SystemCheck {
    
    /**
     * Check system requirements before activation
     */
    public static function check_requirements() {
        $errors = [];
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = 'PHP 7.4 or higher is required. Current version: ' . PHP_VERSION;
        }
        
        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.0', '<')) {
            $errors[] = 'WordPress 5.0 or higher is required. Current version: ' . $wp_version;
        }
        
        // Check MySQL version
        global $wpdb;
        $mysql_version = $wpdb->get_var('SELECT VERSION()');
        if (version_compare($mysql_version, '5.7', '<')) {
            $errors[] = 'MySQL 5.7 or higher is required. Current version: ' . $mysql_version;
        }
        
        // Check required PHP extensions
        $required_extensions = ['json', 'mbstring', 'openssl'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $errors[] = "Required PHP extension '{$ext}' is not loaded";
            }
        }
        
        // Check database permissions
        $test_table = $wpdb->prefix . 'vefify_test_' . uniqid();
        $create_result = $wpdb->query("CREATE TABLE `{$test_table}` (id INT AUTO_INCREMENT PRIMARY KEY)");
        
        if ($create_result === false) {
            $errors[] = 'Database user lacks CREATE TABLE privileges';
        } else {
            // Clean up test table
            $wpdb->query("DROP TABLE `{$test_table}`");
        }
        
        // Check file permissions
        $upload_dir = wp_upload_dir();
        if (!is_writable($upload_dir['basedir'])) {
            $errors[] = 'Upload directory is not writable: ' . $upload_dir['basedir'];
        }
        
        return $errors;
    }
    
    /**
     * Display system check results
     */
    public static function display_system_info() {
        global $wpdb;
        
        $info = [
            'PHP Version' => PHP_VERSION,
            'WordPress Version' => get_bloginfo('version'),
            'MySQL Version' => $wpdb->get_var('SELECT VERSION()'),
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . ' seconds',
            'Upload Max Size' => ini_get('upload_max_filesize'),
            'WordPress Memory Limit' => WP_MEMORY_LIMIT,
            'Multisite' => is_multisite() ? 'Yes' : 'No',
            'Plugin Directory Writable' => is_writable(VEFIFY_QUIZ_PLUGIN_DIR) ? 'Yes' : 'No',
            'Upload Directory Writable' => is_writable(wp_upload_dir()['basedir']) ? 'Yes' : 'No'
        ];
        
        echo '<div class="wrap">';
        echo '<h2>Vefify Quiz System Information</h2>';
        echo '<table class="widefat">';
        
        foreach ($info as $key => $value) {
            echo '<tr>';
            echo '<td style="font-weight: bold; width: 250px;">' . esc_html($key) . '</td>';
            echo '<td>' . esc_html($value) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
    }
}

/**
 * Database Status Check
 */
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'vefify_quiz_status',
        'Vefify Quiz Status',
        'vefify_quiz_dashboard_widget'
    );
});

function vefify_quiz_dashboard_widget() {
    global $wpdb;
    
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Check table status
    $tables_status = [];
    $required_tables = ['campaigns', 'questions', 'quiz_users', 'gifts'];
    
    foreach ($required_tables as $table) {
        $table_name = $table_prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
            $tables_status[$table] = "✅ $count records";
        } else {
            $tables_status[$table] = "❌ Missing";
        }
    }
    
    // Get quick stats
    $stats = [
        'Active Campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM `{$table_prefix}campaigns` WHERE is_active = 1"),
        'Total Participants' => $wpdb->get_var("SELECT COUNT(*) FROM `{$table_prefix}quiz_users`"),
        'Completed Quizzes' => $wpdb->get_var("SELECT COUNT(*) FROM `{$table_prefix}quiz_users` WHERE completed_at IS NOT NULL"),
        'Available Questions' => $wpdb->get_var("SELECT COUNT(*) FROM `{$table_prefix}questions` WHERE is_active = 1")
    ];
    
    ?>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
        <div>
            <h4>📊 Quick Stats</h4>
            <ul style="margin: 0;">
                <?php foreach ($stats as $label => $value): ?>
                    <li><strong><?php echo $label; ?>:</strong> <?php echo number_format($value); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div>
            <h4>🗄️ Database Status</h4>
            <ul style="margin: 0;">
                <?php foreach ($tables_status as $table => $status): ?>
                    <li><strong><?php echo ucfirst($table); ?>:</strong> <?php echo $status; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    
    <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd;">
        <a href="<?php echo admin_url('admin.php?page=vefify-quiz'); ?>" class="button button-primary">
            📈 View Full Dashboard
        </a>
        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns'); ?>" class="button">
            ⚙️ Manage Campaigns
        </a>
    </div>
    <?php
}