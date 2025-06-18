<?php
/**
 * Complete Activation Workflow Example
 * What happens when admin activates the plugin
 */

// STEP 1: Plugin Activation Process
// When admin clicks "Activate" in WordPress admin:

/**
 * Activation Flow Visualization:
 * 
 * 1. WordPress calls register_activation_hook callback
 * 2. Installer::activate() runs
 * 3. Database tables created with sample data
 * 4. Default settings configured
 * 5. Directories created
 * 6. Admin notice shown
 * 7. Plugin ready to use!
 */

/**
 * Error Handling & Recovery
 * File: includes/class-error-handler.php
 */

namespace VefifyQuiz;

class ErrorHandler {
    
    /**
     * Handle activation errors gracefully
     */
    public static function handle_activation_error($error) {
        // Log the error
        error_log('Vefify Quiz Activation Error: ' . $error->getMessage());
        
        // Store error for admin display
        update_option('vefify_quiz_activation_error', [
            'message' => $error->getMessage(),
            'time' => current_time('mysql'),
            'trace' => $error->getTraceAsString()
        ]);
        
        // Try to clean up partially created tables
        self::cleanup_failed_installation();
        
        // Deactivate plugin
        deactivate_plugins(plugin_basename(VEFIFY_QUIZ_PLUGIN_DIR . 'vefify-quiz-plugin.php'));
        
        // Redirect with error message
        wp_redirect(admin_url('plugins.php?vefify-error=1'));
        exit;
    }
    
    /**
     * Clean up failed installation
     */
    private static function cleanup_failed_installation() {
        global $wpdb;
        
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        $tables = [
            'campaigns', 'questions', 'question_options', 
            'gifts', 'quiz_users', 'quiz_sessions', 'analytics'
        ];
        
        foreach ($tables as $table) {
            $table_name = $table_prefix . $table;
            $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");
        }
        
        // Remove options
        delete_option('vefify_quiz_db_version');
        delete_option('vefify_quiz_settings');
        delete_option('vefify_quiz_version');
    }
}

/**
 * Enhanced Admin Notices
 */
add_action('admin_notices', function() {
    // Success notice
    if (get_option('vefify_quiz_sample_data_inserted')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <h3>🎉 Vefify Quiz Plugin Activated Successfully!</h3>
            <p>Your quiz plugin is ready to use. Here's what we've set up for you:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>✅ Database tables created</li>
                <li>✅ Sample "Health Knowledge Quiz 2024" campaign</li>
                <li>✅ 5 sample health questions with multiple choice options</li>
                <li>✅ 3 gift tiers (Certificate, 10% discount, 50K voucher)</li>
                <li>✅ Upload directories and security files</li>
            </ul>
            <p>
                <strong>Next Steps:</strong>
                <a href="<?php echo admin_url('admin.php?page=vefify-quiz'); ?>" class="button button-primary">View Dashboard</a>
                <a href="<?php echo admin_url('admin.php?page=vefify-campaigns'); ?>" class="button">Manage Campaigns</a>
                <a href="<?php echo admin_url('post-new.php?post_type=page'); ?>" class="button">Create Quiz Page</a>
            </p>
            
            <h4>🚀 Quick Start Guide:</h4>
            <ol style="margin-left: 20px;">
                <li>Create a new page and add shortcode: <code>[vefify_quiz campaign_id="1"]</code></li>
                <li>Customize your campaign in <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=edit&id=1'); ?>">Campaign Settings</a></li>
                <li>Add your own questions in <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>">Question Bank</a></li>
                <li>Configure gifts and rewards in <a href="<?php echo admin_url('admin.php?page=vefify-gifts'); ?>">Gift Management</a></li>
            </ol>
        </div>
        <?php
        
        // Remove notice after first view
        delete_option('vefify_quiz_sample_data_inserted');
    }
    
    // Error notice
    if (isset($_GET['vefify-error']) && $_GET['vefify-error'] == '1') {
        $error_data = get_option('vefify_quiz_activation_error');
        if ($error_data) {
            ?>
            <div class="notice notice-error">
                <h3>❌ Vefify Quiz Plugin Activation Failed</h3>
                <p><strong>Error:</strong> <?php echo esc_html($error_data['message']); ?></p>
                <p><strong>Time:</strong> <?php echo esc_html($error_data['time']); ?></p>
                
                <details style="margin-top: 10px;">
                    <summary style="cursor: pointer; font-weight: bold;">🔍 Technical Details (for developers)</summary>
                    <pre style="background: #f1f1f1; padding: 10px; margin-top: 10px; font-size: 11px; overflow-x: auto;"><?php echo esc_html($error_data['trace']); ?></pre>
                </details>
                
                <h4>🛠️ Troubleshooting Steps:</h4>
                <ol style="margin-left: 20px;">
                    <li>Check that your MySQL user has CREATE and ALTER privileges</li>
                    <li>Ensure your WordPress database is accessible</li>
                    <li>Verify sufficient disk space and memory limits</li>
                    <li>Contact your hosting provider if issues persist</li>
                </ol>
                
                <p>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button">Return to Plugins</a>
                    <a href="#" onclick="location.reload();" class="button button-secondary">Try Again</a>
                </p>
            </div>
            <?php
            
            // Clear error after showing
            delete_option('vefify_quiz_activation_error');
        }
    }
});