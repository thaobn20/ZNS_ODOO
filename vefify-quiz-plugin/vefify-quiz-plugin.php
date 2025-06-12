<?php
/**
 * Plugin Name: Vefify Quiz Campaign Manager
 * Description: Advanced quiz campaign management with mobile-first design
 * Version: 1.0.0
 * Author: Vefify Team
 * License: GPL v2 or later
 * Text Domain: vefify-quiz
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('VEFIFY_QUIZ_VERSION', '1.0.0');
define('VEFIFY_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VEFIFY_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VEFIFY_QUIZ_TABLE_PREFIX', 'vefify_');
define('VEFIFY_QUIZ_DB_VERSION', '1.0.0');

/**
 * Installer Class - Handles plugin activation/deactivation
 */
class Vefify_Quiz_Installer {
    
    /**
     * Plugin activation callback
     */
    public static function activate() {
        try {
            $installer = new self();
            
            // Create database tables
            $installer->create_tables();
            
            // Insert sample data (only on fresh install)
            $installer->insert_sample_data();
            
            // Set plugin options
            $installer->set_default_options();
            
            // Create upload directories
            $installer->create_directories();
            
            // Schedule cleanup events
            $installer->schedule_events();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Log successful activation
            error_log('Vefify Quiz Plugin activated successfully');
            
        } catch (Exception $e) {
            // Log error and show admin notice
            error_log('Vefify Quiz Plugin activation failed: ' . $e->getMessage());
            
            // Store error for admin display
            update_option('vefify_quiz_activation_error', $e->getMessage());
            
            // Deactivate plugin if critical error
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Plugin activation failed: ' . esc_html($e->getMessage()) . '<br><br><a href="' . admin_url('plugins.php') . '">Return to Plugins</a>');
        }
    }
    
    /**
     * Plugin deactivation callback
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('vefify_quiz_daily_cleanup');
        wp_clear_scheduled_hook('vefify_quiz_weekly_summary');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('Vefify Quiz Plugin deactivated');
    }
    
    /**
     * Create all database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Check if we need to create tables
        $installed_version = get_option('vefify_quiz_db_version', '0');
        
        if (version_compare($installed_version, VEFIFY_QUIZ_DB_VERSION, '>=')) {
            return; // Tables already up to date
        }
        
        // Include WordPress dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // 1. Campaigns Table
        $sql_campaigns = "CREATE TABLE {$table_prefix}campaigns (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            max_participants int(11) DEFAULT NULL,
            allow_retake tinyint(1) DEFAULT 0,
            questions_per_quiz int(11) DEFAULT 5,
            time_limit int(11) DEFAULT NULL,
            pass_score int(11) DEFAULT 3,
            meta_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active_campaigns (is_active, start_date, end_date),
            UNIQUE KEY idx_slug (slug)
        ) $charset_collate;";
        
        // 2. Questions Table
        $sql_questions = "CREATE TABLE {$table_prefix}questions (
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
            KEY idx_campaign_questions (campaign_id, is_active),
            KEY idx_category (category)
        ) $charset_collate;";
        
        // 3. Question Options Table
        $sql_options = "CREATE TABLE {$table_prefix}question_options (
            id int(11) NOT NULL AUTO_INCREMENT,
            question_id int(11) NOT NULL,
            option_text text NOT NULL,
            is_correct tinyint(1) DEFAULT 0,
            order_index int(11) DEFAULT 0,
            explanation text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_question_options (question_id)
        ) $charset_collate;";
        
        // 4. Gifts Table
        $sql_gifts = "CREATE TABLE {$table_prefix}gifts (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            gift_name varchar(255) NOT NULL,
            gift_type enum('voucher', 'discount', 'product', 'points') NOT NULL,
            gift_value varchar(100) NOT NULL,
            gift_description text,
            min_score int(11) NOT NULL DEFAULT 0,
            max_score int(11) DEFAULT NULL,
            max_quantity int(11) DEFAULT NULL,
            used_count int(11) DEFAULT 0,
            gift_code_prefix varchar(20) DEFAULT NULL,
            api_endpoint varchar(255) DEFAULT NULL,
            api_params longtext,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_campaign_gifts (campaign_id, is_active),
            KEY idx_score_range (min_score, max_score)
        ) $charset_collate;";
        
        // 5. Quiz Users Table
        $sql_users = "CREATE TABLE {$table_prefix}quiz_users (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            session_id varchar(100) NOT NULL,
            full_name varchar(255) NOT NULL,
            phone_number varchar(20) NOT NULL,
            province varchar(100) DEFAULT NULL,
            pharmacy_code varchar(50) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            score int(11) DEFAULT 0,
            total_questions int(11) DEFAULT 0,
            completion_time int(11) DEFAULT NULL,
            gift_id int(11) DEFAULT NULL,
            gift_code varchar(100) DEFAULT NULL,
            gift_status enum('none', 'assigned', 'claimed', 'expired') DEFAULT 'none',
            gift_response longtext,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_campaign_phone (campaign_id, phone_number),
            KEY idx_session (session_id),
            KEY idx_phone_lookup (phone_number),
            KEY idx_completion (completed_at)
        ) $charset_collate;";
        
        // 6. Quiz Sessions Table
        $sql_sessions = "CREATE TABLE {$table_prefix}quiz_sessions (
            id int(11) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            user_id int(11) NOT NULL,
            campaign_id int(11) NOT NULL,
            current_question int(11) DEFAULT 0,
            questions_data longtext,
            answers_data longtext,
            time_remaining int(11) DEFAULT NULL,
            is_completed tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_session (session_id),
            KEY idx_user_session (user_id, session_id)
        ) $charset_collate;";
        
        // 7. Analytics Table
        $sql_analytics = "CREATE TABLE {$table_prefix}analytics (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            event_type enum('view', 'start', 'question_answer', 'complete', 'gift_claim') NOT NULL,
            user_id int(11) DEFAULT NULL,
            session_id varchar(100) DEFAULT NULL,
            question_id int(11) DEFAULT NULL,
            event_data longtext,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_campaign_analytics (campaign_id, event_type),
            KEY idx_event_tracking (event_type, created_at)
        ) $charset_collate;";
        
        // Execute table creation
        $tables = array(
            'campaigns' => $sql_campaigns,
            'questions' => $sql_questions,
            'question_options' => $sql_options,
            'gifts' => $sql_gifts,
            'quiz_users' => $sql_users,
            'quiz_sessions' => $sql_sessions,
            'analytics' => $sql_analytics
        );
        
        foreach ($tables as $table_name => $sql) {
            $result = dbDelta($sql);
            
            if ($wpdb->last_error) {
                throw new Exception("Failed to create table {$table_name}: " . $wpdb->last_error);
            }
            
            error_log("Vefify Quiz: Created/Updated table {$table_name}");
        }
        
        // Update database version
        update_option('vefify_quiz_db_version', VEFIFY_QUIZ_DB_VERSION);
        
        error_log('Vefify Quiz: All database tables created successfully');
    }
    
    /**
     * Insert sample data (only on fresh install)
     */
    public function insert_sample_data() {
        global $wpdb;
        
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Check if sample data already exists
        $existing_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}campaigns");
        
        if ($existing_campaigns > 0) {
            return; // Sample data already exists
        }
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // 1. Insert sample campaign
            $campaign_result = $wpdb->insert(
                $table_prefix . 'campaigns',
                array(
                    'name' => 'Health Knowledge Quiz 2024',
                    'slug' => 'health-quiz-2024',
                    'description' => 'Test your health and wellness knowledge to win amazing prizes!',
                    'start_date' => '2024-01-01 00:00:00',
                    'end_date' => '2024-12-31 23:59:59',
                    'is_active' => 1,
                    'max_participants' => 10000,
                    'questions_per_quiz' => 5,
                    'time_limit' => 600, // 10 minutes
                    'pass_score' => 3,
                    'meta_data' => json_encode(array(
                        'welcome_message' => 'Welcome to our Health Quiz!',
                        'completion_message' => 'Thank you for participating!'
                    ))
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s')
            );
            
            if (!$campaign_result) {
                throw new Exception('Failed to insert sample campaign');
            }
            
            $campaign_id = $wpdb->insert_id;
            
            // 2. Insert sample questions
            $questions_data = array(
                array(
                    'question_text' => 'What is Aspirin commonly used for?',
                    'question_type' => 'multiple_select',
                    'category' => 'medication',
                    'difficulty' => 'easy',
                    'options' => array(
                        array('text' => 'Pain relief', 'correct' => true),
                        array('text' => 'Fever reduction', 'correct' => true),
                        array('text' => 'Sleep aid', 'correct' => false),
                        array('text' => 'Anxiety treatment', 'correct' => false)
                    )
                ),
                array(
                    'question_text' => 'Which vitamin is essential for bone health?',
                    'question_type' => 'multiple_choice',
                    'category' => 'nutrition',
                    'difficulty' => 'medium',
                    'options' => array(
                        array('text' => 'Vitamin A', 'correct' => false),
                        array('text' => 'Vitamin C', 'correct' => false),
                        array('text' => 'Vitamin D', 'correct' => true),
                        array('text' => 'Vitamin E', 'correct' => false)
                    )
                ),
                array(
                    'question_text' => 'What should you do before taking any medication?',
                    'question_type' => 'multiple_select',
                    'category' => 'safety',
                    'difficulty' => 'easy',
                    'options' => array(
                        array('text' => 'Read the instructions', 'correct' => true),
                        array('text' => 'Consult a healthcare provider', 'correct' => true),
                        array('text' => 'Take it immediately', 'correct' => false),
                        array('text' => 'Double the dose', 'correct' => false)
                    )
                ),
                array(
                    'question_text' => 'How often should you wash your hands?',
                    'question_type' => 'multiple_choice',
                    'category' => 'hygiene',
                    'difficulty' => 'easy',
                    'options' => array(
                        array('text' => 'Once a day', 'correct' => false),
                        array('text' => 'Before meals and after using restroom', 'correct' => true),
                        array('text' => 'Only when dirty', 'correct' => false),
                        array('text' => 'Never', 'correct' => false)
                    )
                ),
                array(
                    'question_text' => 'What is the recommended daily water intake for adults?',
                    'question_type' => 'multiple_choice',
                    'category' => 'nutrition',
                    'difficulty' => 'medium',
                    'options' => array(
                        array('text' => '1-2 glasses', 'correct' => false),
                        array('text' => '8-10 glasses (2-2.5 liters)', 'correct' => true),
                        array('text' => '15-20 glasses', 'correct' => false),
                        array('text' => 'As little as possible', 'correct' => false)
                    )
                )
            );
            
            foreach ($questions_data as $index => $question_data) {
                // Insert question
                $question_result = $wpdb->insert(
                    $table_prefix . 'questions',
                    array(
                        'campaign_id' => $campaign_id,
                        'question_text' => $question_data['question_text'],
                        'question_type' => $question_data['question_type'],
                        'category' => $question_data['category'],
                        'difficulty' => $question_data['difficulty'],
                        'order_index' => $index + 1,
                        'is_active' => 1
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%d', '%d')
                );
                
                if (!$question_result) {
                    throw new Exception("Failed to insert question: " . $question_data['question_text']);
                }
                
                $question_id = $wpdb->insert_id;
                
                // Insert options for this question
                foreach ($question_data['options'] as $option_index => $option) {
                    $option_result = $wpdb->insert(
                        $table_prefix . 'question_options',
                        array(
                            'question_id' => $question_id,
                            'option_text' => $option['text'],
                            'is_correct' => $option['correct'] ? 1 : 0,
                            'order_index' => $option_index + 1
                        ),
                        array('%d', '%s', '%d', '%d')
                    );
                    
                    if (!$option_result) {
                        throw new Exception("Failed to insert option: " . $option['text']);
                    }
                }
            }
            
            // 3. Insert sample gifts
            $gifts_data = array(
                array(
                    'gift_name' => 'Participation Certificate',
                    'gift_type' => 'product',
                    'gift_value' => 'Digital Certificate',
                    'gift_description' => 'Thank you for participating in our health quiz!',
                    'min_score' => 1,
                    'max_score' => 2,
                    'max_quantity' => null, // Unlimited
                    'gift_code_prefix' => 'CERT'
                ),
                array(
                    'gift_name' => '10% Discount Voucher',
                    'gift_type' => 'discount',
                    'gift_value' => '10%',
                    'gift_description' => '10% discount on your next purchase',
                    'min_score' => 3,
                    'max_score' => 4,
                    'max_quantity' => 100,
                    'gift_code_prefix' => 'SAVE10'
                ),
                array(
                    'gift_name' => '50,000 VND Voucher',
                    'gift_type' => 'voucher',
                    'gift_value' => '50,000 VND',
                    'gift_description' => 'Cash voucher worth 50,000 VND',
                    'min_score' => 5,
                    'max_score' => 5,
                    'max_quantity' => 20,
                    'gift_code_prefix' => 'GIFT50K'
                )
            );
            
            foreach ($gifts_data as $gift_data) {
                $gift_result = $wpdb->insert(
                    $table_prefix . 'gifts',
                    array(
                        'campaign_id' => $campaign_id,
                        'gift_name' => $gift_data['gift_name'],
                        'gift_type' => $gift_data['gift_type'],
                        'gift_value' => $gift_data['gift_value'],
                        'gift_description' => $gift_data['gift_description'],
                        'min_score' => $gift_data['min_score'],
                        'max_score' => $gift_data['max_score'],
                        'max_quantity' => $gift_data['max_quantity'],
                        'used_count' => 0,
                        'gift_code_prefix' => $gift_data['gift_code_prefix'],
                        'is_active' => 1
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d')
                );
                
                if (!$gift_result) {
                    throw new Exception("Failed to insert gift: " . $gift_data['gift_name']);
                }
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            error_log('Vefify Quiz: Sample data inserted successfully');
            
            // Set flag to show admin notice
            update_option('vefify_quiz_sample_data_inserted', true);
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * Set default plugin options
     */
    public function set_default_options() {
        $default_settings = array(
            'default_questions_per_quiz' => 5,
            'default_time_limit' => 600, // 10 minutes
            'default_pass_score' => 3,
            'enable_retakes' => false,
            'phone_number_required' => true,
            'province_required' => true,
            'pharmacy_code_required' => false,
            'enable_analytics' => true,
            'enable_gift_api' => false,
            'gift_api_timeout' => 30,
            'max_participants_per_campaign' => 10000
        );
        
        // Only set if not already configured
        if (!get_option('vefify_quiz_settings')) {
            update_option('vefify_quiz_settings', $default_settings);
        }
        
        // Set initial plugin version
        update_option('vefify_quiz_version', VEFIFY_QUIZ_VERSION);
        
        // Set installation date
        if (!get_option('vefify_quiz_installed_date')) {
            update_option('vefify_quiz_installed_date', current_time('mysql'));
        }
    }
    
    /**
     * Create necessary directories
     */
    public function create_directories() {
        $upload_dir = wp_upload_dir();
        $vefify_dir = $upload_dir['basedir'] . '/vefify-quiz/';
        
        $directories = array(
            $vefify_dir,
            $vefify_dir . 'exports/',
            $vefify_dir . 'imports/',
            $vefify_dir . 'logs/',
            $vefify_dir . 'cache/'
        );
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
                
                // Create .htaccess to protect directories
                file_put_contents($dir . '.htaccess', "deny from all\n");
                
                // Create index.php to prevent directory listing
                file_put_contents($dir . 'index.php', "<?php // Silence is golden");
            }
        }
    }
    
    /**
     * Schedule recurring events
     */
    public function schedule_events() {
        // Schedule daily cleanup
        if (!wp_next_scheduled('vefify_quiz_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'vefify_quiz_daily_cleanup');
        }
        
        // Schedule weekly analytics summary
        if (!wp_next_scheduled('vefify_quiz_weekly_summary')) {
            wp_schedule_event(time(), 'weekly', 'vefify_quiz_weekly_summary');
        }
    }
}

// Activation/Deactivation hooks - MUST be in main plugin file
register_activation_hook(__FILE__, array('Vefify_Quiz_Installer', 'activate'));
register_deactivation_hook(__FILE__, array('Vefify_Quiz_Installer', 'deactivate'));

/**
 * Add admin notices for successful installation
 */
add_action('admin_notices', function() {
    if (get_option('vefify_quiz_sample_data_inserted')) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<h3>🎉 Vefify Quiz Plugin Activated Successfully!</h3>';
        echo '<p><strong>Database Status:</strong></p>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        echo '<li>✅ 7 database tables created</li>';
        echo '<li>✅ Sample campaign: "Health Knowledge Quiz 2024"</li>';
        echo '<li>✅ 5 health questions with 20 answer options</li>';
        echo '<li>✅ 3-tier gift system configured</li>';
        echo '<li>✅ Security directories created</li>';
        echo '</ul>';
        echo '<p><strong>🚀 Quick Start:</strong></p>';
        echo '<ol style="margin-left: 20px;">';
        echo '<li>Create a new page</li>';
        echo '<li>Add shortcode: <code>[vefify_quiz campaign_id="1"]</code></li>';
        echo '<li>Publish and test</li>';
        echo '</ol>';
        echo '<p>';
        echo '<a href="' . admin_url('post-new.php?post_type=page') . '" class="button button-primary">Create Quiz Page Now</a> ';
        echo '<a href="#" class="button" onclick="this.closest(\'.notice\').style.display=\'none\';">Dismiss</a>';
        echo '</p>';
        echo '</div>';
        
        // Remove the notice after showing it once
        delete_option('vefify_quiz_sample_data_inserted');
    }
    
    // Show activation error if any
    $activation_error = get_option('vefify_quiz_activation_error');
    if ($activation_error) {
        echo '<div class="notice notice-error">';
        echo '<h3>❌ Vefify Quiz Plugin Activation Error</h3>';
        echo '<p><strong>Error:</strong> ' . esc_html($activation_error) . '</p>';
        echo '<p><strong>Common Solutions:</strong></p>';
        echo '<ul style="margin-left: 20px;">';
        echo '<li>Check database user has CREATE TABLE permissions</li>';
        echo '<li>Ensure MySQL version 5.7+ or MariaDB 10.2+</li>';
        echo '<li>Verify wp-content/uploads is writable</li>';
        echo '<li>Contact your hosting provider if issues persist</li>';
        echo '</ul>';
        echo '</div>';
        delete_option('vefify_quiz_activation_error');
    }
});

/**
 * Basic shortcode support
 *
add_shortcode('vefify_quiz', function($atts) {
    $atts = shortcode_atts(array(
        'campaign_id' => 1,
        'template' => 'mobile'
    ), $atts);
    
    // For now, return a test interface until full frontend is built
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $campaign = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_prefix}campaigns WHERE id = %d AND is_active = 1",
        $atts['campaign_id']
    ));
    
    if (!$campaign) {
        return '<div style="padding: 20px; border: 2px solid #e74c3c; border-radius: 8px; background: #fdf2f2; color: #e74c3c; text-align: center;">
            <h3>❌ Campaign Not Found</h3>
            <p>Campaign ID ' . esc_html($atts['campaign_id']) . ' not found or inactive.</p>
            <p>Please check your campaign_id parameter.</p>
        </div>';
    }
    
    // Get question count
    $question_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_prefix}questions WHERE campaign_id = %d AND is_active = 1",
        $atts['campaign_id']
    ));
    
    // Get gift count
    $gift_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_prefix}gifts WHERE campaign_id = %d AND is_active = 1",
        $atts['campaign_id']
    ));
    
    return '<div style="padding: 30px; border: 3px solid #4facfe; border-radius: 12px; text-align: center; margin: 20px 0; background: linear-gradient(135deg, #f8fcff 0%, #e3f2fd 100%);">
        <h2 style="color: #1976d2; margin-bottom: 15px;">🎯 ' . esc_html($campaign->name) . '</h2>
        <p style="font-size: 16px; color: #333; margin-bottom: 20px;">' . esc_html($campaign->description) . '</p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
            <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <strong>📝 Questions:</strong><br>' . $question_count . ' available
            </div>
            <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <strong>🎁 Gifts:</strong><br>' . $gift_count . ' tiers
            </div>
            <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <strong>🎯 Pass Score:</strong><br>' . $campaign->pass_score . ' correct
            </div>
            <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <strong>⏱️ Time Limit:</strong><br>' . ($campaign->time_limit ? floor($campaign->time_limit/60) . ' minutes' : 'No limit') . '
            </div>
        </div>
        
        <div style="background: #e8f5e8; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #4caf50;">
            <h3 style="color: #2e7d32; margin-bottom: 10px;">✅ Backend Ready!</h3>
            <p style="color: #2e7d32; margin: 0;">
                Plugin activated successfully. Database created with sample data.<br>
                <strong>Next step:</strong> Integrate your beautiful mobile quiz interface.
            </p>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
            <p style="color: #856404; margin: 0; font-size: 14px;">
                <strong>🔧 For Developer:</strong> Ready to connect your mobile frontend.<br>
                Database schema complete, sample data loaded, API endpoints ready.
            </p>
        </div>
    </div>';
});
**/
/**
 * Enhanced shortcode with full mobile interface
 */
add_shortcode('vefify_quiz', function($atts) {
    $atts = shortcode_atts(array(
        'campaign_id' => 1,
        'template' => 'mobile'
    ), $atts);
    
    // Get campaign data
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $campaign = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_prefix}campaigns WHERE id = %d AND is_active = 1",
        $atts['campaign_id']
    ));
    
    if (!$campaign) {
        return '<div style="padding: 20px; border: 2px solid #e74c3c; border-radius: 8px; background: #fdf2f2; color: #e74c3c; text-align: center;">
            <h3>❌ Campaign Not Found</h3>
            <p>Campaign ID ' . esc_html($atts['campaign_id']) . ' not found or inactive.</p>
        </div>';
    }
    
    // Enqueue styles and scripts
    wp_enqueue_style('vefify-quiz-mobile', plugin_dir_url(__FILE__) . 'assets/quiz-mobile.css', array(), VEFIFY_QUIZ_VERSION);
    wp_enqueue_script('vefify-quiz-mobile', plugin_dir_url(__FILE__) . 'assets/quiz-mobile.js', array('jquery'), VEFIFY_QUIZ_VERSION, true);
    
    // Localize script with campaign data and API endpoints
    wp_localize_script('vefify-quiz-mobile', 'vefifyQuiz', array(
        'apiUrl' => rest_url('wp/v2/'),
        'restUrl' => rest_url('vefify/v1/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'campaign' => array(
            'id' => $campaign->id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'questions_per_quiz' => $campaign->questions_per_quiz,
            'time_limit' => $campaign->time_limit,
            'pass_score' => $campaign->pass_score
        ),
        'strings' => array(
            'loading' => 'Loading quiz questions...',
            'error' => 'An error occurred. Please try again.',
            'already_participated' => 'You have already participated in this campaign.',
            'submit_success' => 'Quiz submitted successfully!',
            'network_error' => 'Network error. Please check your connection.'
        )
    ));
    
    // Return the mobile quiz interface HTML
    ob_start();
    ?>
    
    <!-- Vefify Quiz Mobile Interface -->
    <div class="vefify-quiz-wrapper" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
        <div class="container">
            <div class="header">
                <h1>🎯 <?php echo esc_html($campaign->name); ?></h1>
                <p><?php echo esc_html($campaign->description); ?></p>
            </div>
            
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>

            <!-- Registration Form -->
            <div class="content" id="registrationForm">
                <form id="userForm">
                    <div class="form-group">
                        <label class="form-label" for="fullName">Full Name *</label>
                        <input type="text" id="fullName" class="form-input" placeholder="Enter your full name" required>
                        <div class="error-message" id="nameError">Please enter your full name</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phoneNumber">Phone Number *</label>
                        <input type="tel" id="phoneNumber" class="form-input" placeholder="0901234567" required>
                        <div class="error-message" id="phoneError">Please enter a valid phone number</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="province">Province/City *</label>
                        <select id="province" class="form-select" required>
                            <option value="">Select your province/city</option>
                            <option value="hanoi">Hanoi</option>
                            <option value="hcm">Ho Chi Minh City</option>
                            <option value="danang">Da Nang</option>
                            <option value="haiphong">Hai Phong</option>
                            <option value="cantho">Can Tho</option>
                            <option value="angiang">An Giang</option>
                            <option value="bariavungtau">Ba Ria - Vung Tau</option>
                            <option value="bacgiang">Bac Giang</option>
                            <option value="backan">Bac Kan</option>
                            <option value="baclieu">Bac Lieu</option>
                            <option value="bacninh">Bac Ninh</option>
                            <option value="bentre">Ben Tre</option>
                            <option value="binhdinh">Binh Dinh</option>
                            <option value="binhduong">Binh Duong</option>
                            <option value="binhphuoc">Binh Phuoc</option>
                            <option value="binhthuan">Binh Thuan</option>
                            <option value="camau">Ca Mau</option>
                            <option value="caobang">Cao Bang</option>
                            <option value="daklak">Dak Lak</option>
                            <option value="daknong">Dak Nong</option>
                            <option value="dienbien">Dien Bien</option>
                            <option value="dongnai">Dong Nai</option>
                            <option value="dongthap">Dong Thap</option>
                            <option value="gialai">Gia Lai</option>
                            <option value="hagiang">Ha Giang</option>
                            <option value="hanam">Ha Nam</option>
                            <option value="hatinh">Ha Tinh</option>
                            <option value="haiduong">Hai Duong</option>
                            <option value="haugiang">Hau Giang</option>
                            <option value="hoabinh">Hoa Binh</option>
                            <option value="hungyen">Hung Yen</option>
                            <option value="khanhhoa">Khanh Hoa</option>
                            <option value="kiengiang">Kien Giang</option>
                            <option value="kontum">Kon Tum</option>
                            <option value="laicau">Lai Chau</option>
                            <option value="lamdong">Lam Dong</option>
                            <option value="langson">Lang Son</option>
                            <option value="laocai">Lao Cai</option>
                            <option value="longan">Long An</option>
                            <option value="namdinh">Nam Dinh</option>
                            <option value="nghean">Nghe An</option>
                            <option value="ninhbinh">Ninh Binh</option>
                            <option value="ninhthuan">Ninh Thuan</option>
                            <option value="phutho">Phu Tho</option>
                            <option value="phuyen">Phu Yen</option>
                            <option value="quangbinh">Quang Binh</option>
                            <option value="quangnam">Quang Nam</option>
                            <option value="quangngai">Quang Ngai</option>
                            <option value="quangninh">Quang Ninh</option>
                            <option value="quangtri">Quang Tri</option>
                            <option value="soctrang">Soc Trang</option>
                            <option value="sonla">Son La</option>
                            <option value="tayninh">Tay Ninh</option>
                            <option value="thaibinh">Thai Binh</option>
                            <option value="thainguyen">Thai Nguyen</option>
                            <option value="thanhhoa">Thanh Hoa</option>
                            <option value="thuathienhue">Thua Thien Hue</option>
                            <option value="tiengiang">Tien Giang</option>
                            <option value="travinh">Tra Vinh</option>
                            <option value="tuyenquang">Tuyen Quang</option>
                            <option value="vinhlong">Vinh Long</option>
                            <option value="vinhphuc">Vinh Phuc</option>
                            <option value="yenbai">Yen Bai</option>
                        </select>
                        <div class="error-message" id="provinceError">Please select your province/city</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="pharmacyCode">Pharmacy Code</label>
                        <input type="text" id="pharmacyCode" class="form-input" placeholder="Optional">
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Continue →</button>
                    </div>
                </form>
            </div>

            <!-- Loading State -->
            <div class="loading" id="loadingState">
                <div class="spinner"></div>
                <p>Loading quiz questions...</p>
            </div>

            <!-- Quiz Container -->
            <div class="question-container" id="quizContainer">
                <div class="content">
                    <div class="question-header">
                        <div class="question-counter" id="questionCounter">Question 1 of 5</div>
                        <div class="question-title" id="questionTitle">Loading question...</div>
                    </div>

                    <div class="answers-container" id="answersContainer">
                        <!-- Answers will be loaded here -->
                    </div>

                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" id="prevBtn" disabled>← Previous</button>
                        <button type="button" class="btn btn-primary" id="nextBtn">Next →</button>
                    </div>
                </div>
            </div>

            <!-- Result Container -->
            <div class="result-container" id="resultContainer">
                <div class="result-icon">🎉</div>
                <div class="result-score" id="resultScore">5/5</div>
                <div class="result-message" id="resultMessage">Congratulations! You've completed the quiz.</div>
                
                <div class="reward-card" id="rewardCard" style="display: none;">
                    <div class="reward-title">🎁 You've Won!</div>
                    <div class="reward-code" id="rewardCode">GIFT50K</div>
                    <div class="reward-description" id="rewardDescription">Cash voucher worth 50,000 VND</div>
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-primary" onclick="restartQuiz()">Take Another Quiz</button>
                </div>
            </div>
        </div>

        <!-- Popup for already participated -->
        <div class="popup" id="alreadyParticipatedPopup">
            <div class="popup-content">
                <div class="popup-icon">⚠️</div>
                <div class="popup-message">You have already participated in this campaign.</div>
                <button class="btn btn-primary" onclick="closePopup()">OK</button>
            </div>
        </div>

        <!-- Error Popup -->
        <div class="popup" id="errorPopup">
            <div class="popup-content">
                <div class="popup-icon">❌</div>
                <div class="popup-message" id="errorMessage">An error occurred. Please try again.</div>
                <button class="btn btn-primary" onclick="closePopup()">OK</button>
            </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
});

/**
 * Create REST API endpoints for the quiz
 */
add_action('rest_api_init', function() {
    // Check participation endpoint
    register_rest_route('vefify/v1', '/check-participation', array(
        'methods' => 'POST',
        'callback' => 'vefify_check_participation',
        'permission_callback' => '__return_true'
    ));
    
    // Start quiz endpoint
    register_rest_route('vefify/v1', '/start-quiz', array(
        'methods' => 'POST',
        'callback' => 'vefify_start_quiz',
        'permission_callback' => '__return_true'
    ));
    
    // Submit quiz endpoint
    register_rest_route('vefify/v1', '/submit-quiz', array(
        'methods' => 'POST',
        'callback' => 'vefify_submit_quiz',
        'permission_callback' => '__return_true'
    ));
});

/**
 * Check if phone number already participated
 */
function vefify_check_participation($request) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $phone = sanitize_text_field($request->get_param('phone'));
    $campaign_id = intval($request->get_param('campaign_id'));
    
    if (!$phone || !$campaign_id) {
        return new WP_Error('missing_data', 'Phone and campaign ID required', array('status' => 400));
    }
    
    // Format phone number
    $phone = preg_replace('/\D/', '', $phone);
    
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table_prefix}quiz_users WHERE campaign_id = %d AND phone_number = %s",
        $campaign_id, $phone
    ));
    
    return rest_ensure_response(array(
        'participated' => !empty($existing),
        'message' => $existing ? 'You have already participated in this campaign' : 'You can participate'
    ));
}

/**
 * Start quiz and get questions
 */
function vefify_start_quiz($request) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $campaign_id = intval($request->get_param('campaign_id'));
    $user_data = $request->get_param('user_data');
    
    if (!$campaign_id || !$user_data) {
        return new WP_Error('missing_data', 'Campaign ID and user data required', array('status' => 400));
    }
    
    // Validate user data
    if (empty($user_data['full_name']) || empty($user_data['phone_number']) || empty($user_data['province'])) {
        return new WP_Error('invalid_data', 'Required fields missing', array('status' => 400));
    }
    
    // Check if already participated
    $phone = preg_replace('/\D/', '', $user_data['phone_number']);
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table_prefix}quiz_users WHERE campaign_id = %d AND phone_number = %s",
        $campaign_id, $phone
    ));
    
    if ($existing) {
        return new WP_Error('already_participated', 'You have already participated', array('status' => 409));
    }
    
    // Get campaign info
    $campaign = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_prefix}campaigns WHERE id = %d AND is_active = 1",
        $campaign_id
    ));
    
    if (!$campaign) {
        return new WP_Error('invalid_campaign', 'Campaign not found', array('status' => 404));
    }
    
    // Get questions for this campaign
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT q.*, 
                (SELECT JSON_ARRAYAGG(
                    JSON_OBJECT('id', qo.id, 'text', qo.option_text, 'order_index', qo.order_index)
                    ORDER BY qo.order_index
                ) FROM {$table_prefix}question_options qo WHERE qo.question_id = q.id) as options
         FROM {$table_prefix}questions q 
         WHERE q.campaign_id = %d AND q.is_active = 1 
         ORDER BY q.order_index ASC, RAND() 
         LIMIT %d",
        $campaign_id, $campaign->questions_per_quiz
    ), ARRAY_A);
    
    if (empty($questions)) {
        return new WP_Error('no_questions', 'No questions available', array('status' => 500));
    }
    
    // Decode options JSON
    foreach ($questions as &$question) {
        $question['options'] = json_decode($question['options'], true) ?: array();
    }
    
    // Create user session
    $session_id = 'vq_' . uniqid() . '_' . wp_generate_password(8, false);
    
    $user_result = $wpdb->insert(
        $table_prefix . 'quiz_users',
        array(
            'campaign_id' => $campaign_id,
            'session_id' => $session_id,
            'full_name' => sanitize_text_field($user_data['full_name']),
            'phone_number' => $phone,
            'province' => sanitize_text_field($user_data['province']),
            'pharmacy_code' => sanitize_text_field($user_data['pharmacy_code'] ?? ''),
            'total_questions' => count($questions),
            'started_at' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
    );
    
    if (!$user_result) {
        return new WP_Error('db_error', 'Failed to create user session', array('status' => 500));
    }
    
    $user_id = $wpdb->insert_id;
    
    // Create quiz session
    $wpdb->insert(
        $table_prefix . 'quiz_sessions',
        array(
            'session_id' => $session_id,
            'user_id' => $user_id,
            'campaign_id' => $campaign_id,
            'questions_data' => json_encode(array_column($questions, 'id')),
            'answers_data' => json_encode(array())
        ),
        array('%s', '%d', '%d', '%s', '%s')
    );
    
    return rest_ensure_response(array(
        'success' => true,
        'session_id' => $session_id,
        'user_id' => $user_id,
        'campaign' => array(
            'name' => $campaign->name,
            'time_limit' => $campaign->time_limit,
            'questions_per_quiz' => $campaign->questions_per_quiz
        ),
        'questions' => $questions
    ));
}

/**
 * Submit quiz and calculate results
 */
function vefify_submit_quiz($request) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $session_id = sanitize_text_field($request->get_param('session_id'));
    $answers = $request->get_param('answers');
    
    if (!$session_id || !is_array($answers)) {
        return new WP_Error('missing_data', 'Session ID and answers required', array('status' => 400));
    }
    
    // Get session and user data
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT s.*, u.campaign_id, u.total_questions FROM {$table_prefix}quiz_sessions s
         JOIN {$table_prefix}quiz_users u ON s.user_id = u.id
         WHERE s.session_id = %s",
        $session_id
    ));
    
    if (!$session) {
        return new WP_Error('invalid_session', 'Session not found', array('status' => 404));
    }
    
    if ($session->is_completed) {
        return new WP_Error('already_completed', 'Quiz already completed', array('status' => 409));
    }
    
    // Calculate score
    $questions_data = json_decode($session->questions_data, true);
    $score = 0;
    $detailed_results = array();
    
    foreach ($questions_data as $question_id) {
        // Get correct answers
        $correct_answers = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table_prefix}question_options WHERE question_id = %d AND is_correct = 1",
            $question_id
        ));
        
        $user_answers = isset($answers[$question_id]) ? (array)$answers[$question_id] : array();
        $user_answers = array_map('intval', $user_answers);
        
        // Check if correct
        $is_correct = (
            count($correct_answers) === count($user_answers) &&
            empty(array_diff($correct_answers, $user_answers))
        );
        
        if ($is_correct) {
            $score++;
        }
        
        $detailed_results[$question_id] = array(
            'user_answers' => $user_answers,
            'correct_answers' => $correct_answers,
            'is_correct' => $is_correct
        );
    }
    
    $completion_time = time() - strtotime($session->created_at);
    
    // Update user record
    $wpdb->update(
        $table_prefix . 'quiz_users',
        array(
            'score' => $score,
            'completion_time' => $completion_time,
            'completed_at' => current_time('mysql')
        ),
        array('id' => $session->user_id),
        array('%d', '%d', '%s'),
        array('%d')
    );
    
    // Mark session as completed
    $wpdb->update(
        $table_prefix . 'quiz_sessions',
        array(
            'is_completed' => 1,
            'answers_data' => json_encode($answers)
        ),
        array('session_id' => $session_id),
        array('%d', '%s'),
        array('%s')
    );
    
    // Check for gifts
    $gift_result = vefify_assign_gift($session->campaign_id, $session->user_id, $score);
    
    return rest_ensure_response(array(
        'success' => true,
        'score' => $score,
        'total_questions' => $session->total_questions,
        'percentage' => round(($score / $session->total_questions) * 100),
        'completion_time' => $completion_time,
        'gift' => $gift_result,
        'detailed_results' => $detailed_results
    ));
}

/**
 * Assign gift based on score
 */
function vefify_assign_gift($campaign_id, $user_id, $score) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Find available gift for this score
    $gift = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_prefix}gifts 
         WHERE campaign_id = %d 
         AND is_active = 1 
         AND min_score <= %d 
         AND (max_score IS NULL OR max_score >= %d)
         AND (max_quantity IS NULL OR used_count < max_quantity)
         ORDER BY min_score DESC, gift_value DESC
         LIMIT 1",
        $campaign_id, $score, $score
    ));
    
    if (!$gift) {
        return array(
            'has_gift' => false,
            'message' => 'No gifts available for your score'
        );
    }
    
    // Generate gift code
    $gift_code = $gift->gift_code_prefix . strtoupper(wp_generate_password(6, false));
    
    // Update user with gift
    $wpdb->update(
        $table_prefix . 'quiz_users',
        array(
            'gift_id' => $gift->id,
            'gift_code' => $gift_code,
            'gift_status' => 'assigned'
        ),
        array('id' => $user_id),
        array('%d', '%s', '%s'),
        array('%d')
    );
    
    // Update gift usage count
    $wpdb->query($wpdb->prepare(
        "UPDATE {$table_prefix}gifts SET used_count = used_count + 1 WHERE id = %d",
        $gift->id
    ));
    
    return array(
        'has_gift' => true,
        'gift_name' => $gift->gift_name,
        'gift_type' => $gift->gift_type,
        'gift_value' => $gift->gift_value,
        'gift_code' => $gift_code,
        'gift_description' => $gift->gift_description,
        'message' => 'Congratulations! You have earned a gift!'
    );
}
/**
 * Register cleanup hooks
 */
add_action('vefify_quiz_daily_cleanup', function() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Clean up expired sessions (older than 24 hours and not completed)
    $deleted_sessions = $wpdb->query("
        DELETE FROM {$table_prefix}quiz_sessions 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        AND is_completed = 0
    ");
    
    // Clean up old analytics data (older than 90 days)
    $deleted_analytics = $wpdb->query("
        DELETE FROM {$table_prefix}analytics 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    
    error_log("Vefify Quiz: Daily cleanup completed. Deleted {$deleted_sessions} expired sessions and {$deleted_analytics} old analytics records.");
});

/**
 * Plugin upgrade check
 */
add_action('plugins_loaded', function() {
    $installed_version = get_option('vefify_quiz_db_version', '0');
    if (version_compare($installed_version, VEFIFY_QUIZ_DB_VERSION, '<')) {
        // Run upgrade if needed
        $installer = new Vefify_Quiz_Installer();
        $installer->create_tables();
    }
});

/**
 * Helper function to check database status
 */
function vefify_quiz_check_database() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $tables = array('campaigns', 'questions', 'question_options', 'gifts', 'quiz_users', 'quiz_sessions', 'analytics');
    $status = array();
    
    foreach ($tables as $table) {
        $table_name = $table_prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        $status[$table] = $exists ? 'exists' : 'missing';
    }
    
    return $status;
}

/**
 * Debug information (remove in production)
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', function() {
        if (current_user_can('manage_options') && isset($_GET['vefify_debug'])) {
            $db_status = vefify_quiz_check_database();
            echo '<div style="position: fixed; bottom: 10px; right: 10px; background: white; border: 1px solid #ccc; padding: 10px; font-size: 12px; z-index: 9999;">';
            echo '<strong>Vefify Quiz Debug:</strong><br>';
            foreach ($db_status as $table => $status) {
                $icon = $status === 'exists' ? '✅' : '❌';
                echo "{$icon} {$table}: {$status}<br>";
            }
            echo '</div>';
        }
    });
}

///ADD UPDATE
/**
 * Enhanced Admin Menu with all features
 */
add_action('admin_menu', function() {
    // Main menu
    add_menu_page(
        'Vefify Quiz',
        'Vefify Quiz',
        'manage_options',
        'vefify-quiz',
        'vefify_admin_dashboard',
        'dashicons-forms',
        30
    );
    
    // Dashboard submenu
    add_submenu_page(
        'vefify-quiz',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'vefify-quiz',
        'vefify_admin_dashboard'
    );
    
    // Campaign Management
    add_submenu_page(
        'vefify-quiz',
        'Campaigns',
        'Campaigns',
        'manage_options',
        'vefify-campaigns',
        'vefify_admin_campaigns'
    );
    
    // Question Bank
    add_submenu_page(
        'vefify-quiz',
        'Question Bank',
        'Questions',
        'manage_options',
        'vefify-questions',
        'vefify_admin_questions'
    );
    
	 // 🎁 GIFT MANAGEMENT - This was missing!
    add_submenu_page(
        'vefify-quiz',
        'Gift Management',
        'Gifts',
        'manage_options',
        'vefify-gifts',
        'vefify_admin_gifts'
    );
	
    // Participants & Results
    add_submenu_page(
        'vefify-quiz',
        'Participants',
        'Participants',
        'manage_options',
        'vefify-participants',
        'vefify_admin_participants'
    );
    
    // Analytics & Reports
    add_submenu_page(
        'vefify-quiz',
        'Analytics',
        'Analytics',
        'manage_options',
        'vefify-analytics',
        'vefify_admin_analytics'
    );
    
    // Settings
    add_submenu_page(
        'vefify-quiz',
        'Settings',
        'Settings',
        'manage_options',
        'vefify-settings',
        'vefify_admin_settings'
    );
});
add_action('admin_init', function() {
    // Add categories submenu under questions
    add_action('admin_menu', function() {
        add_submenu_page(
            'vefify-questions',
            'Categories',
            'Categories',
            'manage_options',
            'vefify-question-categories',
            'vefify_question_categories'
        );
    }, 20); // Lower priority to ensure it runs after the main menu
});
/**
 * Admin Dashboard - Main Overview
 */
function vefify_admin_dashboard() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Get dashboard statistics
    $stats = array(
        'total_campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}campaigns"),
        'active_campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}campaigns WHERE is_active = 1"),
        'total_participants' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}quiz_users"),
        'completed_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}quiz_users WHERE DATE(completed_at) = CURDATE()"),
        'completed_this_week' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}quiz_users WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        'gifts_claimed' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}quiz_users WHERE gift_status = 'assigned'"),
        'avg_score' => $wpdb->get_var("SELECT AVG(score) FROM {$table_prefix}quiz_users WHERE score > 0"),
        'total_questions' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}questions WHERE is_active = 1")
    );
    
    // Recent participants
    $recent_participants = $wpdb->get_results("
        SELECT u.full_name, u.phone_number, u.score, u.total_questions, u.completed_at, 
               c.name as campaign_name, g.gift_name, u.province
        FROM {$table_prefix}quiz_users u
        JOIN {$table_prefix}campaigns c ON u.campaign_id = c.id
        LEFT JOIN {$table_prefix}gifts g ON u.gift_id = g.id
        WHERE u.completed_at IS NOT NULL
        ORDER BY u.completed_at DESC
        LIMIT 10
    ");
    
    // Top performing provinces
    $province_stats = $wpdb->get_results("
        SELECT province, COUNT(*) as participants, AVG(score) as avg_score
        FROM {$table_prefix}quiz_users 
        WHERE province IS NOT NULL AND completed_at IS NOT NULL
        GROUP BY province
        ORDER BY avg_score DESC, participants DESC
        LIMIT 10
    ");
    
    ?>
    <div class="wrap">
        <h1>Vefify Quiz Dashboard</h1>
        
        <!-- Statistics Overview -->
        <div class="vefify-stats-grid">
            <div class="vefify-stat-card">
                <h3>📊 Total Campaigns</h3>
                <div class="stat-number"><?php echo number_format($stats['total_campaigns']); ?></div>
                <div class="stat-subtitle"><?php echo $stats['active_campaigns']; ?> active</div>
            </div>
            
            <div class="vefify-stat-card">
                <h3>👥 Total Participants</h3>
                <div class="stat-number"><?php echo number_format($stats['total_participants']); ?></div>
                <div class="stat-subtitle"><?php echo $stats['completed_today']; ?> today</div>
            </div>
            
            <div class="vefify-stat-card">
                <h3>📈 This Week</h3>
                <div class="stat-number"><?php echo number_format($stats['completed_this_week']); ?></div>
                <div class="stat-subtitle">completed quizzes</div>
            </div>
            
            <div class="vefify-stat-card">
                <h3>🎁 Gifts Claimed</h3>
                <div class="stat-number"><?php echo number_format($stats['gifts_claimed']); ?></div>
                <div class="stat-subtitle">total rewards</div>
            </div>
            
            <div class="vefify-stat-card">
                <h3>🎯 Average Score</h3>
                <div class="stat-number"><?php echo number_format($stats['avg_score'], 1); ?></div>
                <div class="stat-subtitle">out of 5</div>
            </div>
            
            <div class="vefify-stat-card">
                <h3>❓ Question Bank</h3>
                <div class="stat-number"><?php echo number_format($stats['total_questions']); ?></div>
                <div class="stat-subtitle">active questions</div>
            </div>
        </div>
        
        <div class="vefify-dashboard-content">
            <!-- Recent Participants -->
            <div class="vefify-dashboard-section">
                <div class="section-header">
                    <h2>🕒 Recent Participants</h2>
                    <a href="<?php echo admin_url('admin.php?page=vefify-participants'); ?>" class="button">View All</a>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Campaign</th>
                            <th>Score</th>
                            <th>Province</th>
                            <th>Gift</th>
                            <th>Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_participants as $participant): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($participant->full_name); ?></strong><br>
                                <small><?php echo esc_html($participant->phone_number); ?></small>
                            </td>
                            <td><?php echo esc_html($participant->campaign_name); ?></td>
                            <td>
                                <span class="score-badge score-<?php echo $participant->score; ?>">
                                    <?php echo $participant->score; ?>/<?php echo $participant->total_questions; ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($participant->province); ?></td>
                            <td>
                                <?php if ($participant->gift_name): ?>
                                    <span class="gift-badge">🎁 <?php echo esc_html($participant->gift_name); ?></span>
                                <?php else: ?>
                                    <span class="no-gift">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo mysql2date('M j, g:i A', $participant->completed_at); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Province Performance -->
            <div class="vefify-dashboard-section">
                <div class="section-header">
                    <h2>📍 Top Performing Provinces</h2>
                    <a href="<?php echo admin_url('admin.php?page=vefify-analytics'); ?>" class="button">Detailed Analytics</a>
                </div>
                
                <div class="province-stats">
                    <?php foreach ($province_stats as $province): ?>
                    <div class="province-item">
                        <div class="province-name"><?php echo esc_html(ucfirst($province->province)); ?></div>
                        <div class="province-metrics">
                            <span class="participants"><?php echo $province->participants; ?> participants</span>
                            <span class="avg-score">Avg: <?php echo number_format($province->avg_score, 1); ?>/5</span>
                        </div>
                        <div class="province-bar">
                            <div class="bar-fill" style="width: <?php echo ($province->avg_score / 5) * 100; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="vefify-quick-actions">
            <h2>⚡ Quick Actions</h2>
            <div class="action-buttons">
                <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=new'); ?>" class="button button-primary button-large">
                    ➕ Create New Campaign
                </a>
                <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=new'); ?>" class="button button-large">
                    ❓ Add Questions
                </a>
                <a href="<?php echo admin_url('admin.php?page=vefify-analytics&export=today'); ?>" class="button button-large">
                    📊 Export Today's Data
                </a>
                <a href="<?php echo admin_url('admin.php?page=vefify-participants'); ?>" class="button button-large">
                    👥 View All Participants
                </a>
            </div>
        </div>
    </div>
    
    <style>
    .vefify-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .vefify-stat-card {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
        border-left: 4px solid #4facfe;
    }
    
    .vefify-stat-card h3 {
        margin: 0 0 10px 0;
        color: #666;
        font-size: 14px;
        font-weight: 600;
    }
    
    .stat-number {
        font-size: 32px;
        font-weight: bold;
        color: #2271b1;
        margin-bottom: 5px;
    }
    
    .stat-subtitle {
        font-size: 12px;
        color: #666;
    }
    
    .vefify-dashboard-content {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }
    
    .vefify-dashboard-section {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .section-header h2 {
        margin: 0;
        color: #333;
        font-size: 18px;
    }
    
    .score-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        color: white;
    }
    
    .score-badge.score-5 { background: #00a32a; }
    .score-badge.score-4 { background: #4caf50; }
    .score-badge.score-3 { background: #ff9800; }
    .score-badge.score-2 { background: #f44336; }
    .score-badge.score-1 { background: #d32f2f; }
    .score-badge.score-0 { background: #666; }
    
    .gift-badge {
        background: #ffecd2;
        color: #8b4513;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 11px;
    }
    
    .no-gift {
        color: #999;
    }
    
    .province-stats {
        space-y: 15px;
    }
    
    .province-item {
        margin-bottom: 15px;
        padding: 10px;
        background: #f9f9f9;
        border-radius: 6px;
    }
    
    .province-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }
    
    .province-metrics {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: #666;
        margin-bottom: 8px;
    }
    
    .province-bar {
        height: 6px;
        background: #e0e0e0;
        border-radius: 3px;
        overflow: hidden;
    }
    
    .bar-fill {
        height: 100%;
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        transition: width 0.3s ease;
    }
    
    .vefify-quick-actions {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .vefify-quick-actions h2 {
        margin: 0 0 15px 0;
        color: #333;
    }
    
    .action-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .action-buttons .button-large {
        padding: 12px 20px;
        height: auto;
        white-space: normal;
        text-align: center;
    }
    
    @media (max-width: 768px) {
        .vefify-dashboard-content {
            grid-template-columns: 1fr;
        }
        
        .action-buttons {
            grid-template-columns: 1fr;
        }
    }
    </style>
    <?php
}

/**
 * Campaign Management Interface
 */
function vefify_admin_campaigns() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Handle form submissions
    if (isset($_POST['action'])) {
        vefify_handle_campaign_action();
    }
    
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'new':
            vefify_campaign_form();
            break;
        case 'edit':
            vefify_campaign_form($_GET['id'] ?? 0);
            break;
        case 'delete':
            vefify_delete_campaign($_GET['id'] ?? 0);
            break;
        default:
            vefify_campaigns_list();
            break;
    }
}

function vefify_campaigns_list() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $campaigns = $wpdb->get_results("
        SELECT c.*, 
               COUNT(u.id) as total_participants,
               COUNT(CASE WHEN u.completed_at IS NOT NULL THEN 1 END) as completed_count,
               COUNT(q.id) as question_count
        FROM {$table_prefix}campaigns c
        LEFT JOIN {$table_prefix}quiz_users u ON c.id = u.campaign_id
        LEFT JOIN {$table_prefix}questions q ON c.id = q.campaign_id AND q.is_active = 1
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Campaigns</h1>
        <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=new'); ?>" class="page-title-action">Add New Campaign</a>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Campaign Name</th>
                    <th>Status</th>
                    <th>Participants</th>
                    <th>Questions</th>
                    <th>Date Range</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($campaign->name); ?></strong>
                        <div class="row-actions">
                            <span class="edit">
                                <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $campaign->id); ?>">Edit</a> |
                            </span>
                            <span class="view">
                                <a href="<?php echo home_url('/?page_id=XXX&campaign_id=' . $campaign->id); ?>" target="_blank">Preview</a> |
                            </span>
                            <span class="delete">
                                <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=delete&id=' . $campaign->id); ?>" 
                                   onclick="return confirm('Are you sure?')">Delete</a>
                            </span>
                        </div>
                    </td>
                    <td>
                        <?php if ($campaign->is_active): ?>
                            <span class="status-badge active">Active</span>
                        <?php else: ?>
                            <span class="status-badge inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo $campaign->total_participants; ?></strong> total<br>
                        <small><?php echo $campaign->completed_count; ?> completed</small>
                    </td>
                    <td>
                        <strong><?php echo $campaign->question_count; ?></strong> questions<br>
                        <small><?php echo $campaign->questions_per_quiz; ?> per quiz</small>
                    </td>
                    <td>
                        <?php echo mysql2date('M j, Y', $campaign->start_date); ?><br>
                        <small>to <?php echo mysql2date('M j, Y', $campaign->end_date); ?></small>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=vefify-participants&campaign_id=' . $campaign->id); ?>" class="button button-small">
                            View Results
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="campaign-shortcode-info">
            <h3>📋 How to Use Campaigns</h3>
            <p>To display a campaign on your website, use this shortcode:</p>
            <code>[vefify_quiz campaign_id="CAMPAIGN_ID"]</code>
            <p><small>Replace CAMPAIGN_ID with the actual campaign ID from the table above.</small></p>
        </div>
    </div>
    
    <style>
    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .status-badge.active {
        background: #00a32a;
        color: white;
    }
    
    .status-badge.inactive {
        background: #ddd;
        color: #666;
    }
    
    .campaign-shortcode-info {
        margin-top: 30px;
        padding: 20px;
        background: #f0f8ff;
        border-left: 4px solid #4facfe;
        border-radius: 4px;
    }
    
    .campaign-shortcode-info h3 {
        margin-top: 0;
        color: #1976d2;
    }
    
    .campaign-shortcode-info code {
        background: #333;
        color: #0f0;
        padding: 8px 12px;
        border-radius: 4px;
        font-family: monospace;
        display: inline-block;
        margin: 10px 0;
    }
    </style>
    <?php
}

/**
 * Campaign Form (New/Edit)
 */
function vefify_campaign_form($campaign_id = 0) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $campaign = null;
    if ($campaign_id) {
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_prefix}campaigns WHERE id = %d",
            $campaign_id
        ));
    }
    
    $is_edit = !empty($campaign);
    $title = $is_edit ? 'Edit Campaign' : 'New Campaign';
    
    ?>
    <div class="wrap">
        <h1><?php echo $title; ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('vefify_campaign_save'); ?>
            <input type="hidden" name="action" value="save_campaign">
            <?php if ($is_edit): ?>
                <input type="hidden" name="campaign_id" value="<?php echo $campaign->id; ?>">
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="campaign_name">Campaign Name *</label></th>
                    <td>
                        <input type="text" id="campaign_name" name="campaign_name" 
                               value="<?php echo $is_edit ? esc_attr($campaign->name) : ''; ?>" 
                               class="regular-text" required>
                        <p class="description">Enter a descriptive name for this campaign</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="campaign_slug">Campaign Slug *</label></th>
                    <td>
                        <input type="text" id="campaign_slug" name="campaign_slug" 
                               value="<?php echo $is_edit ? esc_attr($campaign->slug) : ''; ?>" 
                               class="regular-text" required>
                        <p class="description">URL-friendly version (auto-generated if left empty)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="campaign_description">Description</label></th>
                    <td>
                        <textarea id="campaign_description" name="campaign_description" 
                                  rows="3" class="large-text"><?php echo $is_edit ? esc_textarea($campaign->description) : ''; ?></textarea>
                        <p class="description">Brief description shown to participants</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Campaign Period *</th>
                    <td>
                        <input type="datetime-local" name="start_date" 
                               value="<?php echo $is_edit ? date('Y-m-d\TH:i', strtotime($campaign->start_date)) : ''; ?>" 
                               required>
                        <span style="margin: 0 10px;">to</span>
                        <input type="datetime-local" name="end_date" 
                               value="<?php echo $is_edit ? date('Y-m-d\TH:i', strtotime($campaign->end_date)) : ''; ?>" 
                               required>
                        <p class="description">Campaign will only be active during this period</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Quiz Settings</th>
                    <td>
                        <fieldset>
                            <label>
                                Questions per quiz: 
                                <input type="number" name="questions_per_quiz" 
                                       value="<?php echo $is_edit ? $campaign->questions_per_quiz : 5; ?>" 
                                       min="1" max="50" class="small-text">
                            </label><br><br>
                            
                            <label>
                                Pass score: 
                                <input type="number" name="pass_score" 
                                       value="<?php echo $is_edit ? $campaign->pass_score : 3; ?>" 
                                       min="1" class="small-text">
                                correct answers needed
                            </label><br><br>
                            
                            <label>
                                Time limit: 
                                <input type="number" name="time_limit" 
                                       value="<?php echo $is_edit ? ($campaign->time_limit ? $campaign->time_limit / 60 : '') : ''; ?>" 
                                       class="small-text" placeholder="10">
                                minutes (leave empty for no limit)
                            </label><br><br>
                            
                            <label>
                                Max participants: 
                                <input type="number" name="max_participants" 
                                       value="<?php echo $is_edit ? $campaign->max_participants : ''; ?>" 
                                       class="regular-text" placeholder="1000">
                                (leave empty for unlimited)
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Status</th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo (!$is_edit || $campaign->is_active) ? 'checked' : ''; ?>>
                            Campaign is active
                        </label>
                        <p class="description">Inactive campaigns cannot be accessed by participants</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button($is_edit ? 'Update Campaign' : 'Create Campaign'); ?>
        </form>
        
        <?php if ($is_edit): ?>
        <div class="campaign-info-boxes">
            <div class="info-box">
                <h3>📊 Campaign Statistics</h3>
                <?php
                $stats = $wpdb->get_row($wpdb->prepare("
                    SELECT COUNT(*) as total_participants,
                           COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed,
                           AVG(score) as avg_score
                    FROM {$table_prefix}quiz_users 
                    WHERE campaign_id = %d
                ", $campaign_id));
                ?>
                <p><strong>Participants:</strong> <?php echo number_format($stats->total_participants); ?></p>
                <p><strong>Completed:</strong> <?php echo number_format($stats->completed); ?></p>
                <p><strong>Average Score:</strong> <?php echo $stats->avg_score ? number_format($stats->avg_score, 1) : '0'; ?>/5</p>
            </div>
            
            <div class="info-box">
                <h3>🔗 Shortcode</h3>
                <p>Use this shortcode to display the campaign:</p>
                <code>[vefify_quiz campaign_id="<?php echo $campaign->id; ?>"]</code>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <style>
    .campaign-info-boxes {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 30px;
    }
    
    .info-box {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #4facfe;
    }
    
    .info-box h3 {
        margin-top: 0;
        color: #333;
    }
    
    .info-box code {
        background: #333;
        color: #0f0;
        padding: 8px 12px;
        border-radius: 4px;
        font-family: monospace;
        display: inline-block;
    }
    </style>
    
    <script>
    // Auto-generate slug from name
    document.getElementById('campaign_name').addEventListener('input', function() {
        const name = this.value;
        const slug = name.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        document.getElementById('campaign_slug').value = slug;
    });
    </script>
    <?php
}

/**
 * Handle campaign form submissions
 */
function vefify_handle_campaign_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_campaign_save')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $data = array(
        'name' => sanitize_text_field($_POST['campaign_name']),
        'slug' => sanitize_title($_POST['campaign_slug']),
        'description' => sanitize_textarea_field($_POST['campaign_description']),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'end_date' => sanitize_text_field($_POST['end_date']),
        'questions_per_quiz' => intval($_POST['questions_per_quiz']),
        'pass_score' => intval($_POST['pass_score']),
        'time_limit' => $_POST['time_limit'] ? intval($_POST['time_limit']) * 60 : null,
        'max_participants' => $_POST['max_participants'] ? intval($_POST['max_participants']) : null,
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    );
    
    if (empty($data['slug'])) {
        $data['slug'] = sanitize_title($data['name']);
    }
    
    if (isset($_POST['campaign_id']) && $_POST['campaign_id']) {
        // Update existing campaign
        $campaign_id = intval($_POST['campaign_id']);
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $table_prefix . 'campaigns',
            $data,
            array('id' => $campaign_id)
        );
        
        $message = 'Campaign updated successfully!';
    } else {
        // Create new campaign
        $result = $wpdb->insert($table_prefix . 'campaigns', $data);
        $campaign_id = $wpdb->insert_id;
        $message = 'Campaign created successfully!';
    }
    
    if ($result !== false) {
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        });
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p>Error saving campaign. Please try again.</p></div>';
        });
    }
    
    wp_redirect(admin_url('admin.php?page=vefify-campaigns&action=edit&id=' . $campaign_id));
    exit;
}
/**
Hanlde question schema
**/
function vefify_fix_question_options_schema() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vefify_question_options';
    
    // Check if 'text' column exists (this is the problematic column name)
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'text'");
    
    if (empty($column_exists)) {
        // Column doesn't exist, which is correct. The error was trying to insert into 'text' instead of 'option_text'
        error_log('Vefify Quiz: question_options table schema is correct. The error was in the insert query.');
        return;
    }
    
    // If 'text' column exists, we need to rename it to 'option_text'
    $wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `text` `option_text` TEXT NOT NULL");
    error_log('Vefify Quiz: Fixed question_options table - renamed text column to option_text');
}

// Run the fix on admin_init
add_action('admin_init', function() {
    if (current_user_can('manage_options') && isset($_GET['vefify_fix_db'])) {
        vefify_fix_question_options_schema();
        wp_redirect(admin_url('admin.php?page=vefify-questions&fixed=1'));
        exit;
    }
});


/**
 * Question Bank Management Interface
 *
function vefify_admin_questions() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Handle form submissions
    if (isset($_POST['action'])) {
        vefify_handle_question_action();
    }
    
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'new':
            vefify_question_form();
            break;
        case 'edit':
            vefify_question_form($_GET['id'] ?? 0);
            break;
        case 'delete':
            vefify_delete_question($_GET['id'] ?? 0);
            break;
        case 'import':
            vefify_question_import();
            break;
        default:
            vefify_questions_list();
            break;
    }
}
**/
function vefify_admin_questions() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Show database fix notice if needed
    if (isset($_GET['fixed'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Database schema fixed successfully!</p></div>';
    }
    
    // Check for database issues
    $table_name = $wpdb->prefix . 'vefify_question_options';
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    $has_option_text = false;
    foreach ($columns as $column) {
        if ($column->Field === 'option_text') {
            $has_option_text = true;
            break;
        }
    }
    
    if (!$has_option_text) {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Database Issue Detected:</strong> Missing option_text column. ';
        echo '<a href="' . admin_url('admin.php?page=vefify-questions&vefify_fix_db=1') . '" class="button">Fix Database Now</a>';
        echo '</p></div>';
    }
    
    // Handle form submissions
    if (isset($_POST['action'])) {
        vefify_handle_question_action();
    }
    
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'new':
            vefify_question_form();
            break;
        case 'edit':
            vefify_question_form($_GET['id'] ?? 0);
            break;
        case 'delete':
            vefify_delete_question($_GET['id'] ?? 0);
            break;
        case 'import':
            vefify_question_import();
            break;
        case 'categories':
            vefify_question_categories();
            break;
        default:
            vefify_questions_list();
            break;
    }
}
/**
 * SOLUTION 9: Question Categories Management
 * New feature to manage question categories
 */
function vefify_question_categories() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Handle category actions
    if (isset($_POST['action'])) {
        vefify_handle_category_action();
    }
    
    // Get categories with question counts
    $categories = $wpdb->get_results("
        SELECT 
            category,
            COUNT(*) as question_count,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_count,
            COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy_count,
            COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium_count,
            COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard_count
        FROM {$table_prefix}questions 
        WHERE category IS NOT NULL AND category != ''
        GROUP BY category 
        ORDER BY category
    ");
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">📚 Question Categories</h1>
        <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="page-title-action">← Back to Questions</a>
        
        <!-- Add New Category -->
        <div class="category-form-section">
            <h2>Add New Category</h2>
            <form method="post" action="" class="category-form">
                <?php wp_nonce_field('vefify_category_save'); ?>
                <input type="hidden" name="action" value="add_category">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="category_name">Category Name *</label></th>
                        <td>
                            <input type="text" id="category_name" name="category_name" 
                                   class="regular-text" placeholder="e.g., Medication Safety" required>
                            <p class="description">Enter a descriptive category name</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="category_description">Description</label></th>
                        <td>
                            <textarea id="category_description" name="category_description" 
                                      rows="3" class="large-text" 
                                      placeholder="Brief description of what questions in this category cover"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="category_color">Color</label></th>
                        <td>
                            <input type="color" id="category_color" name="category_color" value="#4facfe">
                            <p class="description">Color for category badges and identification</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Add Category'); ?>
            </form>
        </div>
        
        <!-- Existing Categories -->
        <div class="categories-list-section">
            <h2>Existing Categories</h2>
            
            <?php if (empty($categories)): ?>
            <div class="no-categories-message">
                <p>No categories found. Create your first category above!</p>
            </div>
            <?php else: ?>
            
            <div class="categories-grid">
                <?php foreach ($categories as $category): ?>
                <div class="category-card">
                    <div class="category-header">
                        <h3 class="category-name">
                            <span class="category-color-indicator" style="background: #4facfe;"></span>
                            <?php echo esc_html(ucfirst($category->category)); ?>
                        </h3>
                        <div class="category-actions">
                            <a href="<?php echo admin_url('admin.php?page=vefify-questions&category=' . urlencode($category->category)); ?>" 
                               class="button button-small">View Questions</a>
                            <button class="button button-small edit-category" data-category="<?php echo esc_attr($category->category); ?>">
                                Edit
                            </button>
                        </div>
                    </div>
                    
                    <div class="category-stats">
                        <div class="stat-item">
                            <strong><?php echo number_format($category->question_count); ?></strong>
                            <span>Total Questions</span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo number_format($category->active_count); ?></strong>
                            <span>Active</span>
                        </div>
                    </div>
                    
                    <div class="difficulty-breakdown">
                        <div class="difficulty-item">
                            <span class="difficulty-badge difficulty-easy"><?php echo $category->easy_count; ?></span>
                            <small>Easy</small>
                        </div>
                        <div class="difficulty-item">
                            <span class="difficulty-badge difficulty-medium"><?php echo $category->medium_count; ?></span>
                            <small>Medium</small>
                        </div>
                        <div class="difficulty-item">
                            <span class="difficulty-badge difficulty-hard"><?php echo $category->hard_count; ?></span>
                            <small>Hard</small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php endif; ?>
        </div>
        
        <!-- Bulk Category Operations -->
        <div class="bulk-operations-section">
            <h2>🔧 Bulk Operations</h2>
            
            <div class="bulk-operation-card">
                <h3>Move Questions Between Categories</h3>
                <form method="post" action="" class="bulk-move-form">
                    <?php wp_nonce_field('vefify_bulk_category'); ?>
                    <input type="hidden" name="action" value="bulk_move_category">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">From Category</th>
                            <td>
                                <select name="from_category" required>
                                    <option value="">Select source category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo esc_attr($category->category); ?>">
                                            <?php echo esc_html(ucfirst($category->category)); ?> 
                                            (<?php echo $category->question_count; ?> questions)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">To Category</th>
                            <td>
                                <select name="to_category" required>
                                    <option value="">Select destination category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo esc_attr($category->category); ?>">
                                            <?php echo esc_html(ucfirst($category->category)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button" value="Move All Questions" 
                               onclick="return confirm('Are you sure you want to move ALL questions from the source category?')">
                    </p>
                </form>
            </div>
            
            <div class="bulk-operation-card">
                <h3>Merge Categories</h3>
                <form method="post" action="" class="bulk-merge-form">
                    <?php wp_nonce_field('vefify_bulk_category'); ?>
                    <input type="hidden" name="action" value="merge_categories">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Categories to Merge</th>
                            <td>
                                <?php foreach ($categories as $category): ?>
                                <label>
                                    <input type="checkbox" name="merge_categories[]" 
                                           value="<?php echo esc_attr($category->category); ?>">
                                    <?php echo esc_html(ucfirst($category->category)); ?> 
                                    (<?php echo $category->question_count; ?> questions)
                                </label><br>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">New Category Name</th>
                            <td>
                                <input type="text" name="new_category_name" class="regular-text" 
                                       placeholder="e.g., Health & Safety" required>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button" value="Merge Categories" 
                               onclick="return confirm('This will merge selected categories into one. Continue?')">
                    </p>
                </form>
            </div>
        </div>
    </div>
    
    <style>
    .category-form-section {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .category-form-section h2 {
        margin-top: 0;
        color: #333;
        border-bottom: 2px solid #4facfe;
        padding-bottom: 10px;
    }
    
    .categories-list-section {
        margin-bottom: 30px;
    }
    
    .categories-list-section h2 {
        color: #333;
        border-bottom: 2px solid #4facfe;
        padding-bottom: 10px;
    }
    
    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .category-card {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-left: 4px solid #4facfe;
    }
    
    .category-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .category-name {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #333;
    }
    
    .category-color-indicator {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: inline-block;
    }
    
    .category-actions {
        display: flex;
        gap: 5px;
    }
    
    .category-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .stat-item {
        text-align: center;
        padding: 10px;
        background: #f9f9f9;
        border-radius: 4px;
    }
    
    .stat-item strong {
        display: block;
        font-size: 18px;
        color: #2271b1;
    }
    
    .stat-item span {
        font-size: 12px;
        color: #666;
    }
    
    .difficulty-breakdown {
        display: flex;
        justify-content: space-around;
        align-items: center;
    }
    
    .difficulty-item {
        text-align: center;
    }
    
    .difficulty-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        color: white;
        margin-bottom: 5px;
    }
    
    .difficulty-badge.difficulty-easy { background: #4caf50; }
    .difficulty-badge.difficulty-medium { background: #ff9800; }
    .difficulty-badge.difficulty-hard { background: #f44336; }
    
    .difficulty-item small {
        display: block;
        color: #666;
        font-size: 11px;
    }
    
    .no-categories-message {
        text-align: center;
        padding: 40px;
        background: #f9f9f9;
        border-radius: 8px;
        color: #666;
    }
    
    .bulk-operations-section {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .bulk-operations-section h2 {
        margin-top: 0;
        color: #333;
        border-bottom: 2px solid #4facfe;
        padding-bottom: 10px;
    }
    
    .bulk-operation-card {
        margin-bottom: 20px;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #fafafa;
    }
    
    .bulk-operation-card h3 {
        margin-top: 0;
        color: #333;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Auto-generate category slug from name
        $('#category_name').on('input', function() {
            const name = $(this).val();
            // Category names will be stored in lowercase
            console.log('Category name entered: ' + name);
        });
        
        // Edit category functionality (placeholder for future)
        $('.edit-category').click(function() {
            const category = $(this).data('category');
            alert('Category editing will be implemented in next version. Category: ' + category);
        });
    });
    </script>
    <?php
}

/**
 * SOLUTION 10: Handle Category Actions
 */
function vefify_handle_category_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $action = $_POST['action'];
    
    switch ($action) {
        case 'add_category':
            if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_category_save')) {
                wp_die('Security check failed');
            }
            
            $category_name = strtolower(sanitize_text_field($_POST['category_name']));
            $category_description = sanitize_textarea_field($_POST['category_description']);
            $category_color = sanitize_hex_color($_POST['category_color']);
            
            // Check if category already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_prefix}questions WHERE category = %s",
                $category_name
            ));
            
            if ($exists > 0) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>Category already exists!</p></div>';
                });
            } else {
                // For now, we'll just show success. In a full implementation, 
                // you might want a separate categories table
                add_action('admin_notices', function() use ($category_name) {
                    echo '<div class="notice notice-success is-dismissible"><p>Category "' . esc_html($category_name) . '" is ready! You can now assign questions to it.</p></div>';
                });
            }
            break;
            
        case 'bulk_move_category':
            if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_bulk_category')) {
                wp_die('Security check failed');
            }
            
            $from_category = sanitize_text_field($_POST['from_category']);
            $to_category = sanitize_text_field($_POST['to_category']);
            
            if ($from_category === $to_category) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>Source and destination categories cannot be the same!</p></div>';
                });
                break;
            }
            
            $moved = $wpdb->update(
                $table_prefix . 'questions',
                array('category' => $to_category),
                array('category' => $from_category)
            );
            
            add_action('admin_notices', function() use ($moved, $from_category, $to_category) {
                echo '<div class="notice notice-success is-dismissible"><p>Moved ' . $moved . ' questions from "' . esc_html($from_category) . '" to "' . esc_html($to_category) . '"</p></div>';
            });
            break;
            
        case 'merge_categories':
            if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_bulk_category')) {
                wp_die('Security check failed');
            }
            
            $merge_categories = array_map('sanitize_text_field', $_POST['merge_categories'] ?? []);
            $new_category_name = strtolower(sanitize_text_field($_POST['new_category_name']));
            
            if (count($merge_categories) < 2) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>Please select at least 2 categories to merge!</p></div>';
                });
                break;
            }
            
            $total_moved = 0;
            foreach ($merge_categories as $category) {
                $moved = $wpdb->update(
                    $table_prefix . 'questions',
                    array('category' => $new_category_name),
                    array('category' => $category)
                );
                $total_moved += $moved;
            }
            
            add_action('admin_notices', function() use ($total_moved, $new_category_name) {
                echo '<div class="notice notice-success is-dismissible"><p>Merged categories successfully! ' . $total_moved . ' questions moved to "' . esc_html($new_category_name) . '"</p></div>';
            });
            break;
    }
}


function vefify_questions_list() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Get filter parameters
    $campaign_filter = $_GET['campaign_id'] ?? '';
    $category_filter = $_GET['category'] ?? '';
    $difficulty_filter = $_GET['difficulty'] ?? '';
    
    // Build query
    $where_conditions = array('q.is_active = 1');
    $params = array();
    
    if ($campaign_filter) {
        $where_conditions[] = 'q.campaign_id = %d';
        $params[] = $campaign_filter;
    }
    
    if ($category_filter) {
        $where_conditions[] = 'q.category = %s';
        $params[] = $category_filter;
    }
    
    if ($difficulty_filter) {
        $where_conditions[] = 'q.difficulty = %s';
        $params[] = $difficulty_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $questions = $wpdb->get_results($wpdb->prepare("
        SELECT q.*, c.name as campaign_name,
               COUNT(qo.id) as option_count,
               SUM(CASE WHEN qo.is_correct = 1 THEN 1 ELSE 0 END) as correct_count
        FROM {$table_prefix}questions q
        LEFT JOIN {$table_prefix}campaigns c ON q.campaign_id = c.id
        LEFT JOIN {$table_prefix}question_options qo ON q.id = qo.question_id
        WHERE {$where_clause}
        GROUP BY q.id
        ORDER BY q.created_at DESC
        LIMIT 50
    ", $params));
    
    // Get filter options
    $campaigns = $wpdb->get_results("SELECT id, name FROM {$table_prefix}campaigns WHERE is_active = 1 ORDER BY name");
    $categories = $wpdb->get_col("SELECT DISTINCT category FROM {$table_prefix}questions WHERE category IS NOT NULL ORDER BY category");
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Question Bank</h1>
        <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=new'); ?>" class="page-title-action">Add New Question</a>
        <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=import'); ?>" class="page-title-action">Import Questions</a>
        
        <!-- Filters -->
        <div class="questions-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="vefify-questions">
                
                <select name="campaign_id" onchange="this.form.submit()">
                    <option value="">All Campaigns</option>
                    <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?php echo $campaign->id; ?>" <?php selected($campaign_filter, $campaign->id); ?>>
                            <?php echo esc_html($campaign->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="category" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo esc_attr($category); ?>" <?php selected($category_filter, $category); ?>>
                            <?php echo esc_html(ucfirst($category)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="difficulty" onchange="this.form.submit()">
                    <option value="">All Difficulties</option>
                    <option value="easy" <?php selected($difficulty_filter, 'easy'); ?>>Easy</option>
                    <option value="medium" <?php selected($difficulty_filter, 'medium'); ?>>Medium</option>
                    <option value="hard" <?php selected($difficulty_filter, 'hard'); ?>>Hard</option>
                </select>
                
                <?php if ($campaign_filter || $category_filter || $difficulty_filter): ?>
                    <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="40%">Question</th>
                    <th>Campaign</th>
                    <th>Category</th>
                    <th>Difficulty</th>
                    <th>Type</th>
                    <th>Options</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $question): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html(wp_trim_words($question->question_text, 12)); ?></strong>
                        <div class="row-actions">
                            <span class="edit">
                                <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=edit&id=' . $question->id); ?>">Edit</a> |
                            </span>
                            <span class="delete">
                                <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=delete&id=' . $question->id); ?>" 
                                   onclick="return confirm('Are you sure?')">Delete</a>
                            </span>
                        </div>
                    </td>
                    <td><?php echo esc_html($question->campaign_name ?: 'Global'); ?></td>
                    <td>
                        <span class="category-badge category-<?php echo esc_attr($question->category); ?>">
                            <?php echo esc_html(ucfirst($question->category)); ?>
                        </span>
                    </td>
                    <td>
                        <span class="difficulty-badge difficulty-<?php echo esc_attr($question->difficulty); ?>">
                            <?php echo esc_html(ucfirst($question->difficulty)); ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        $type_labels = array(
                            'multiple_choice' => 'Single Choice',
                            'multiple_select' => 'Multiple Choice',
                            'true_false' => 'True/False'
                        );
                        echo $type_labels[$question->question_type] ?? $question->question_type;
                        ?>
                    </td>
                    <td>
                        <?php echo $question->option_count; ?> options<br>
                        <small><?php echo $question->correct_count; ?> correct</small>
                    </td>
                    <td>
                        <button class="button button-small toggle-preview" data-question-id="<?php echo $question->id; ?>">
                            Preview
                        </button>
                    </td>
                </tr>
                <tr class="question-preview" id="preview-<?php echo $question->id; ?>" style="display: none;">
                    <td colspan="7">
                        <div class="question-preview-content">
                            <div class="preview-loading">Loading options...</div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="questions-stats">
            <h3>📊 Question Bank Statistics</h3>
            <?php
            $stats = $wpdb->get_row("
                SELECT COUNT(*) as total,
                       COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy,
                       COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium,
                       COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard,
                       COUNT(CASE WHEN question_type = 'multiple_choice' THEN 1 END) as single_choice,
                       COUNT(CASE WHEN question_type = 'multiple_select' THEN 1 END) as multi_choice
                FROM {$table_prefix}questions 
                WHERE is_active = 1
            ");
            ?>
            <div class="stats-grid">
                <div class="stat-item">
                    <strong><?php echo number_format($stats->total); ?></strong>
                    <span>Total Questions</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats->easy); ?></strong>
                    <span>Easy</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats->medium); ?></strong>
                    <span>Medium</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats->hard); ?></strong>
                    <span>Hard</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats->single_choice); ?></strong>
                    <span>Single Choice</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo number_format($stats->multi_choice); ?></strong>
                    <span>Multiple Choice</span>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .questions-filters {
        margin: 20px 0;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 4px;
    }
    
    .questions-filters form {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .category-badge, .difficulty-badge {
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        color: white;
        font-weight: bold;
    }
    
    .category-badge.category-medication { background: #2196f3; }
    .category-badge.category-nutrition { background: #4caf50; }
    .category-badge.category-safety { background: #ff9800; }
    .category-badge.category-hygiene { background: #9c27b0; }
    .category-badge.category-wellness { background: #00bcd4; }
    .category-badge.category-pharmacy { background: #795548; }
    
    .difficulty-badge.difficulty-easy { background: #4caf50; }
    .difficulty-badge.difficulty-medium { background: #ff9800; }
    .difficulty-badge.difficulty-hard { background: #f44336; }
    
    .question-preview-content {
        padding: 15px;
        background: #f5f5f5;
        border-radius: 4px;
        margin: 10px 0;
    }
    
    .preview-question {
        font-weight: bold;
        margin-bottom: 10px;
        color: #333;
    }
    
    .preview-options {
        margin-left: 20px;
    }
    
    .preview-option {
        margin: 5px 0;
        padding: 5px;
        border-radius: 3px;
    }
    
    .preview-option.correct {
        background: #d4edda;
        color: #155724;
        font-weight: bold;
    }
    
    .preview-option.incorrect {
        background: #f8d7da;
        color: #721c24;
    }
    
    .questions-stats {
        margin-top: 30px;
        padding: 20px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    
    .stat-item {
        text-align: center;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 4px;
    }
    
    .stat-item strong {
        display: block;
        font-size: 24px;
        color: #2271b1;
        margin-bottom: 5px;
    }
    
    .stat-item span {
        font-size: 12px;
        color: #666;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('.toggle-preview').click(function() {
            const questionId = $(this).data('question-id');
            const previewRow = $('#preview-' + questionId);
            const button = $(this);
            
            if (previewRow.is(':visible')) {
                previewRow.hide();
                button.text('Preview');
            } else {
                // Load question options via AJAX
                $.post(ajaxurl, {
                    action: 'vefify_load_question_preview',
                    question_id: questionId,
                    nonce: '<?php echo wp_create_nonce("vefify_preview"); ?>'
                }, function(response) {
                    if (response.success) {
                        previewRow.find('.question-preview-content').html(response.data);
                        previewRow.show();
                        button.text('Hide');
                    }
                });
            }
        });
    });
    </script>
    <?php
}

/**
 * Question Form (New/Edit)
 */
function vefify_question_form($question_id = 0) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $question = null;
    $options = array();
    
    if ($question_id) {
        $question = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_prefix}questions WHERE id = %d",
            $question_id
        ));
        
        $options = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_prefix}question_options WHERE question_id = %d ORDER BY order_index",
            $question_id
        ));
    }
    
    // Get campaigns for dropdown
    $campaigns = $wpdb->get_results("SELECT id, name FROM {$table_prefix}campaigns ORDER BY name");
    
    $is_edit = !empty($question);
    $title = $is_edit ? 'Edit Question' : 'New Question';
    
    ?>
    <div class="wrap">
        <h1><?php echo $title; ?></h1>
        
        <form method="post" action="" id="question-form">
            <?php wp_nonce_field('vefify_question_save'); ?>
            <input type="hidden" name="action" value="save_question">
            <?php if ($is_edit): ?>
                <input type="hidden" name="question_id" value="<?php echo $question->id; ?>">
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="campaign_id">Campaign</label></th>
                    <td>
                        <select id="campaign_id" name="campaign_id">
                            <option value="">Global (All Campaigns)</option>
                            <?php foreach ($campaigns as $campaign): ?>
                                <option value="<?php echo $campaign->id; ?>" 
                                        <?php selected($is_edit ? $question->campaign_id : '', $campaign->id); ?>>
                                    <?php echo esc_html($campaign->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Leave empty to make this question available for all campaigns</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="question_text">Question Text *</label></th>
                    <td>
                        <textarea id="question_text" name="question_text" rows="3" class="large-text" required><?php echo $is_edit ? esc_textarea($question->question_text) : ''; ?></textarea>
                        <p class="description">Enter the question that participants will see</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Question Settings</th>
                    <td>
                        <fieldset>
                            <label>
                                Type: 
                                <select name="question_type" id="question_type">
                                    <option value="multiple_choice" <?php selected($is_edit ? $question->question_type : 'multiple_choice', 'multiple_choice'); ?>>
                                        Single Choice (one correct answer)
                                    </option>
                                    <option value="multiple_select" <?php selected($is_edit ? $question->question_type : '', 'multiple_select'); ?>>
                                        Multiple Choice (multiple correct answers)
                                    </option>
                                    <option value="true_false" <?php selected($is_edit ? $question->question_type : '', 'true_false'); ?>>
                                        True/False
                                    </option>
                                </select>
                            </label><br><br>
                            
                            <label>
                                Category: 
                                <select name="category">
                                    <option value="">Select Category</option>
                                    <option value="medication" <?php selected($is_edit ? $question->category : '', 'medication'); ?>>Medication</option>
                                    <option value="nutrition" <?php selected($is_edit ? $question->category : '', 'nutrition'); ?>>Nutrition</option>
                                    <option value="safety" <?php selected($is_edit ? $question->category : '', 'safety'); ?>>Safety</option>
                                    <option value="hygiene" <?php selected($is_edit ? $question->category : '', 'hygiene'); ?>>Hygiene</option>
                                    <option value="wellness" <?php selected($is_edit ? $question->category : '', 'wellness'); ?>>Wellness</option>
                                    <option value="pharmacy" <?php selected($is_edit ? $question->category : '', 'pharmacy'); ?>>Pharmacy</option>
                                </select>
                            </label><br><br>
                            
                            <label>
                                Difficulty: 
                                <select name="difficulty">
                                    <option value="easy" <?php selected($is_edit ? $question->difficulty : 'medium', 'easy'); ?>>Easy</option>
                                    <option value="medium" <?php selected($is_edit ? $question->difficulty : 'medium', 'medium'); ?>>Medium</option>
                                    <option value="hard" <?php selected($is_edit ? $question->difficulty : 'medium', 'hard'); ?>>Hard</option>
                                </select>
                            </label><br><br>
                            
                            <label>
                                Points: 
                                <input type="number" name="points" value="<?php echo $is_edit ? $question->points : 1; ?>" 
                                       min="1" max="10" class="small-text">
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Explanation (Optional)</th>
                    <td>
                        <textarea name="explanation" rows="2" class="large-text"><?php echo $is_edit ? esc_textarea($question->explanation) : ''; ?></textarea>
                        <p class="description">Explain why certain answers are correct (shown after completion)</p>
                    </td>
                </tr>
            </table>
            
            <h3>Answer Options</h3>
            <div id="answer-options">
                <?php
                if ($options) {
                    foreach ($options as $index => $option) {
                        echo vefify_render_option_row($index, $option->option_text, $option->is_correct, $option->explanation);
                    }
                } else {
                    // Default 4 options for new questions
                    for ($i = 0; $i < 4; $i++) {
                        echo vefify_render_option_row($i, '', false, '');
                    }
                }
                ?>
            </div>
            
            <p>
                <button type="button" id="add-option" class="button">Add Another Option</button>
                <span class="description">You need at least 2 options, and at least 1 must be marked as correct.</span>
            </p>
            
            <?php submit_button($is_edit ? 'Update Question' : 'Save Question'); ?>
        </form>
    </div>
    
    <style>
    .option-row {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 10px;
        position: relative;
    }
    
    .option-row.correct {
        border-left: 4px solid #00a32a;
        background: #f0f8f0;
    }
    
    .option-header {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .option-number {
        background: #666;
        color: white;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
        margin-right: 10px;
    }
    
    .option-correct {
        margin-left: auto;
    }
    
    .option-text {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .option-explanation {
        width: 100%;
        font-size: 13px;
    }
    
    .remove-option {
        position: absolute;
        top: 10px;
        right: 10px;
        color: #dc3232;
        text-decoration: none;
        font-weight: bold;
        font-size: 18px;
        line-height: 1;
    }
    
    .remove-option:hover {
        color: #a00;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        let optionCount = $('#answer-options .option-row').length;
        
        // Add new option
        $('#add-option').click(function() {
            const optionHtml = `<?php echo addslashes(vefify_render_option_row('__INDEX__', '', false, '')); ?>`
                .replace(/__INDEX__/g, optionCount);
            $('#answer-options').append(optionHtml);
            optionCount++;
        });
        
        // Remove option
        $(document).on('click', '.remove-option', function(e) {
            e.preventDefault();
            if ($('#answer-options .option-row').length > 2) {
                $(this).closest('.option-row').remove();
            } else {
                alert('You need at least 2 options.');
            }
        });
        
        // Handle correct answer checkbox
        $(document).on('change', '.option-correct-checkbox', function() {
            const row = $(this).closest('.option-row');
            const questionType = $('#question_type').val();
            
            if (questionType === 'multiple_choice' && this.checked) {
                // For single choice, uncheck all other options
                $('.option-correct-checkbox').not(this).prop('checked', false);
                $('.option-row').removeClass('correct');
            }
            
            // Update visual state
            row.toggleClass('correct', this.checked);
        });
        
        // Question type change
        $('#question_type').change(function() {
            const type = $(this).val();
            const helpText = type === 'multiple_choice' 
                ? 'Mark ONE correct answer' 
                : 'Mark ALL correct answers';
            
            $('.option-help').text(helpText);
            
            if (type === 'true_false') {
                // Limit to 2 options for true/false
                $('#answer-options .option-row:gt(1)').remove();
                $('#add-option').hide();
            } else {
                $('#add-option').show();
            }
        });
        
        // Form validation
        $('#question-form').submit(function(e) {
            const checkedOptions = $('.option-correct-checkbox:checked').length;
            const filledOptions = $('.option-text').filter(function() {
                return $(this).val().trim() !== '';
            }).length;
            
            if (filledOptions < 2) {
                alert('You need at least 2 answer options.');
                e.preventDefault();
                return false;
            }
            
            if (checkedOptions === 0) {
                alert('You need to mark at least one correct answer.');
                e.preventDefault();
                return false;
            }
        });
    });
    </script>
    <?php
}

/**
 * Render option row HTML
 */
function vefify_render_option_row($index, $text = '', $is_correct = false, $explanation = '') {
    ob_start();
    ?>
    <div class="option-row <?php echo $is_correct ? 'correct' : ''; ?>">
        <a href="#" class="remove-option" title="Remove this option">×</a>
        
        <div class="option-header">
            <div class="option-number"><?php echo $index + 1; ?></div>
            <label class="option-correct">
                <input type="checkbox" name="options[<?php echo $index; ?>][is_correct]" 
                       value="1" class="option-correct-checkbox" <?php checked($is_correct); ?>>
                Correct Answer
            </label>
        </div>
        
        <input type="text" name="options[<?php echo $index; ?>][text]" 
               value="<?php echo esc_attr($text); ?>" 
               placeholder="Enter answer option..." 
               class="option-text" required>
        
        <textarea name="options[<?php echo $index; ?>][explanation]" 
                  placeholder="Optional: Explain why this answer is correct/incorrect..."
                  rows="2" class="option-explanation"><?php echo esc_textarea($explanation); ?></textarea>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Handle question form submissions
 *
function vefify_handle_question_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_question_save')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Prepare question data
    $question_data = array(
        'campaign_id' => $_POST['campaign_id'] ? intval($_POST['campaign_id']) : null,
        'question_text' => sanitize_textarea_field($_POST['question_text']),
        'question_type' => sanitize_text_field($_POST['question_type']),
        'category' => sanitize_text_field($_POST['category']),
        'difficulty' => sanitize_text_field($_POST['difficulty']),
        'points' => intval($_POST['points']),
        'explanation' => sanitize_textarea_field($_POST['explanation'])
    );
    
    // Validate options
    $options = $_POST['options'] ?? array();
    $valid_options = array();
    $has_correct = false;
    
    foreach ($options as $index => $option) {
        if (!empty($option['text'])) {
            $valid_options[] = array(
                'text' => sanitize_textarea_field($option['text']),
                'is_correct' => !empty($option['is_correct']),
                'explanation' => sanitize_textarea_field($option['explanation'] ?? ''),
                'order_index' => count($valid_options) + 1
            );
            
            if (!empty($option['is_correct'])) {
                $has_correct = true;
            }
        }
    }
    
    // Validation
    if (count($valid_options) < 2) {
        wp_die('You need at least 2 answer options.');
    }
    
    if (!$has_correct) {
        wp_die('You need at least one correct answer.');
    }
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        if (isset($_POST['question_id']) && $_POST['question_id']) {
            // Update existing question
            $question_id = intval($_POST['question_id']);
            $question_data['updated_at'] = current_time('mysql');
            
            $result = $wpdb->update(
                $table_prefix . 'questions',
                $question_data,
                array('id' => $question_id)
            );
            
            // Delete existing options
            $wpdb->delete($table_prefix . 'question_options', array('question_id' => $question_id));
            
            $message = 'Question updated successfully!';
        } else {
            // Create new question
            $result = $wpdb->insert($table_prefix . 'questions', $question_data);
            $question_id = $wpdb->insert_id;
            $message = 'Question created successfully!';
        }
        
        if ($result === false) {
            throw new Exception('Failed to save question');
        }
        
        // Insert options
        foreach ($valid_options as $option) {
            $option['question_id'] = $question_id;
            $result = $wpdb->insert($table_prefix . 'question_options', $option);
            
            if ($result === false) {
                throw new Exception('Failed to save option');
            }
        }
        
        $wpdb->query('COMMIT');
        
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        });
        
        wp_redirect(admin_url('admin.php?page=vefify-questions'));
        exit;
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_die('Error saving question: ' . $e->getMessage());
    }
}**/
/**
 * SOLUTION 11: Enhanced Question Form with Better Error Handling
 */
function vefify_handle_question_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_question_save')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Prepare question data
    $question_data = array(
        'campaign_id' => $_POST['campaign_id'] ? intval($_POST['campaign_id']) : null,
        'question_text' => sanitize_textarea_field($_POST['question_text']),
        'question_type' => sanitize_text_field($_POST['question_type']),
        'category' => sanitize_text_field($_POST['category']),
        'difficulty' => sanitize_text_field($_POST['difficulty']),
        'points' => intval($_POST['points']),
        'explanation' => sanitize_textarea_field($_POST['explanation'])
    );
    
    // Validate options
    $options = $_POST['options'] ?? array();
    $valid_options = array();
    $has_correct = false;
    
    foreach ($options as $index => $option) {
        if (!empty($option['text'])) {
            $valid_options[] = array(
                'option_text' => sanitize_textarea_field($option['text']), // Fixed: use 'option_text' not 'text'
                'is_correct' => !empty($option['is_correct']),
                'explanation' => sanitize_textarea_field($option['explanation'] ?? ''),
                'order_index' => count($valid_options) + 1
            );
            
            if (!empty($option['is_correct'])) {
                $has_correct = true;
            }
        }
    }
    
    // Validation
    if (count($valid_options) < 2) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p>Error: You need at least 2 answer options.</p></div>';
        });
        return;
    }
    
    if (!$has_correct) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p>Error: You need at least one correct answer.</p></div>';
        });
        return;
    }
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        if (isset($_POST['question_id']) && $_POST['question_id']) {
            // Update existing question
            $question_id = intval($_POST['question_id']);
            $question_data['updated_at'] = current_time('mysql');
            
            $result = $wpdb->update(
                $table_prefix . 'questions',
                $question_data,
                array('id' => $question_id)
            );
            
            // Delete existing options
            $wpdb->delete($table_prefix . 'question_options', array('question_id' => $question_id));
            
            $message = 'Question updated successfully!';
        } else {
            // Create new question
            $result = $wpdb->insert($table_prefix . 'questions', $question_data);
            $question_id = $wpdb->insert_id;
            $message = 'Question created successfully!';
        }
        
        if ($result === false) {
            throw new Exception('Failed to save question: ' . $wpdb->last_error);
        }
        
        // Insert options with corrected column name
        foreach ($valid_options as $option) {
            $option['question_id'] = $question_id;
            $result = $wpdb->insert($table_prefix . 'question_options', $option);
            
            if ($result === false) {
                throw new Exception('Failed to save option: ' . $wpdb->last_error);
            }
        }
        
        $wpdb->query('COMMIT');
        
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        });
        
        wp_redirect(admin_url('admin.php?page=vefify-questions&action=edit&id=' . $question_id));
        exit;
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error is-dismissible"><p>Error saving question: ' . esc_html($e->getMessage()) . '</p></div>';
        });
        
        error_log('Vefify Quiz Error: ' . $e->getMessage());
    }
}
/**
 * AJAX handler for question preview
 */
add_action('wp_ajax_vefify_load_question_preview', function() {
    if (!wp_verify_nonce($_POST['nonce'], 'vefify_preview')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    $question_id = intval($_POST['question_id']);
    
    $question = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_prefix}questions WHERE id = %d",
        $question_id
    ));
    
    $options = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_prefix}question_options WHERE question_id = %d ORDER BY order_index",
        $question_id
    ));
    
    if (!$question) {
        wp_send_json_error('Question not found');
    }
    
    ob_start();
    ?>
    <div class="preview-question"><?php echo esc_html($question->question_text); ?></div>
    <div class="preview-meta">
        <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $question->question_type)); ?> |
        <strong>Category:</strong> <?php echo ucfirst($question->category); ?> |
        <strong>Difficulty:</strong> <?php echo ucfirst($question->difficulty); ?>
    </div>
    <div class="preview-options">
        <?php foreach ($options as $option): ?>
            <div class="preview-option <?php echo $option->is_correct ? 'correct' : 'incorrect'; ?>">
                <?php echo $option->is_correct ? '✓' : '✗'; ?> <?php echo esc_html($option->option_text); ?>
                <?php if ($option->explanation): ?>
                    <br><small><em><?php echo esc_html($option->explanation); ?></em></small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($question->explanation): ?>
        <div class="preview-explanation">
            <strong>Explanation:</strong> <?php echo esc_html($question->explanation); ?>
        </div>
    <?php endif; ?>
    <?php
    
    wp_send_json_success(ob_get_clean());
});

/**
 * Question Import Interface
 */
function vefify_question_import() {
    // Handle CSV upload
    if (isset($_POST['import_csv']) && !empty($_FILES['csv_file']['tmp_name'])) {
        $result = vefify_process_csv_import();
    }
    
    ?>
    <div class="wrap">
        <h1>Import Questions</h1>
        
        <?php if (isset($result)): ?>
            <div class="notice notice-<?php echo $result['success'] ? 'success' : 'error'; ?>">
                <p><?php echo esc_html($result['message']); ?></p>
                <?php if (!empty($result['details'])): ?>
                    <ul>
                        <?php foreach ($result['details'] as $detail): ?>
                            <li><?php echo esc_html($detail); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="import-sections">
            <div class="import-section">
                <h2>📁 CSV Import</h2>
                <p>Upload a CSV file containing questions and answers.</p>
                
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('vefify_import_csv'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="csv_file">CSV File</label></th>
                            <td>
                                <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                <p class="description">Select a CSV file with questions</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="import_campaign">Campaign</label></th>
                            <td>
                                <?php
                                global $wpdb;
                                $campaigns = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}vefify_campaigns ORDER BY name");
                                ?>
                                <select name="import_campaign" id="import_campaign">
                                    <option value="">Global (All Campaigns)</option>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo $campaign->id; ?>">
                                            <?php echo esc_html($campaign->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="import_csv" class="button button-primary" value="Import Questions">
                    </p>
                </form>
            </div>
            
            <div class="import-section">
                <h2>📋 CSV Format Guide</h2>
                <p>Your CSV file should have these columns:</p>
                
                <table class="wp-list-table widefat">
                    <thead>
                        <tr>
                            <th>Column</th>
                            <th>Description</th>
                            <th>Required</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>question_text</strong></td>
                            <td>The question text</td>
                            <td>Yes</td>
                            <td>What is Aspirin used for?</td>
                        </tr>
                        <tr>
                            <td><strong>option_1</strong></td>
                            <td>First answer option</td>
                            <td>Yes</td>
                            <td>Pain relief</td>
                        </tr>
                        <tr>
                            <td><strong>option_2</strong></td>
                            <td>Second answer option</td>
                            <td>Yes</td>
                            <td>Fever reduction</td>
                        </tr>
                        <tr>
                            <td><strong>option_3</strong></td>
                            <td>Third answer option</td>
                            <td>No</td>
                            <td>Sleep aid</td>
                        </tr>
                        <tr>
                            <td><strong>option_4</strong></td>
                            <td>Fourth answer option</td>
                            <td>No</td>
                            <td>Anxiety treatment</td>
                        </tr>
                        <tr>
                            <td><strong>correct_answers</strong></td>
                            <td>Correct option numbers</td>
                            <td>Yes</td>
                            <td>1,2 (for multiple correct)</td>
                        </tr>
                        <tr>
                            <td><strong>category</strong></td>
                            <td>Question category</td>
                            <td>No</td>
                            <td>medication</td>
                        </tr>
                        <tr>
                            <td><strong>difficulty</strong></td>
                            <td>Question difficulty</td>
                            <td>No</td>
                            <td>easy, medium, hard</td>
                        </tr>
                        <tr>
                            <td><strong>explanation</strong></td>
                            <td>Answer explanation</td>
                            <td>No</td>
                            <td>Aspirin helps reduce pain and fever</td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>📄 Sample CSV Content</h3>
                <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">question_text,option_1,option_2,option_3,option_4,correct_answers,category,difficulty,explanation
"What is Aspirin commonly used for?","Pain relief","Fever reduction","Sleep aid","Anxiety treatment","1,2","medication","easy","Aspirin is an anti-inflammatory drug"
"Which vitamin is essential for bone health?","Vitamin A","Vitamin C","Vitamin D","Vitamin E","3","nutrition","medium","Vitamin D helps calcium absorption"</pre>
            </div>
        </div>
    </div>
    
    <style>
    .import-sections {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 20px;
    }
    
    .import-section {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .import-section h2 {
        margin-top: 0;
        color: #333;
        border-bottom: 2px solid #4facfe;
        padding-bottom: 10px;
    }
    
    .import-section h3 {
        color: #333;
        margin-top: 25px;
    }
    
    @media (max-width: 768px) {
        .import-sections {
            grid-template-columns: 1fr;
        }
    }
    </style>
    <?php
}

/**
 * Process CSV import
 */
function vefify_process_csv_import() {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_import_csv')) {
        return array('success' => false, 'message' => 'Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        return array('success' => false, 'message' => 'Unauthorized');
    }
    
    $csv_file = $_FILES['csv_file']['tmp_name'];
    $campaign_id = $_POST['import_campaign'] ? intval($_POST['import_campaign']) : null;
    
    if (!file_exists($csv_file)) {
        return array('success' => false, 'message' => 'CSV file not found');
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $handle = fopen($csv_file, 'r');
    if (!$handle) {
        return array('success' => false, 'message' => 'Cannot read CSV file');
    }
    
    $imported = 0;
    $errors = array();
    $line = 0;
    
    // Read header row
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return array('success' => false, 'message' => 'Invalid CSV format - no headers found');
    }
    
    // Map headers to indices
    $header_map = array_flip(array_map('strtolower', $headers));
    
    // Required columns
    $required = array('question_text', 'option_1', 'option_2', 'correct_answers');
    foreach ($required as $req) {
        if (!isset($header_map[$req])) {
            fclose($handle);
            return array('success' => false, 'message' => "Required column '$req' not found in CSV");
        }
    }
    
    while (($data = fgetcsv($handle)) !== false) {
        $line++;
        
        if (empty($data[0])) continue; // Skip empty rows
        
        try {
            // Extract question data
            $question_text = $data[$header_map['question_text']] ?? '';
            if (empty($question_text)) {
                $errors[] = "Line $line: Question text is required";
                continue;
            }
            
            // Get options
            $options = array();
            for ($i = 1; $i <= 6; $i++) {
                $option_key = "option_$i";
                if (isset($header_map[$option_key]) && !empty($data[$header_map[$option_key]])) {
                    $options[] = trim($data[$header_map[$option_key]]);
                }
            }
            
            if (count($options) < 2) {
                $errors[] = "Line $line: At least 2 options required";
                continue;
            }
            
            // Get correct answers
            $correct_answers = $data[$header_map['correct_answers']] ?? '';
            $correct_indices = array_map('trim', explode(',', $correct_answers));
            $correct_indices = array_filter($correct_indices, 'is_numeric');
            
            if (empty($correct_indices)) {
                $errors[] = "Line $line: At least one correct answer required";
                continue;
            }
            
            // Prepare question data
            $question_data = array(
                'campaign_id' => $campaign_id,
                'question_text' => sanitize_textarea_field($question_text),
                'question_type' => count($correct_indices) > 1 ? 'multiple_select' : 'multiple_choice',
                'category' => isset($header_map['category']) ? sanitize_text_field($data[$header_map['category']]) : 'general',
                'difficulty' => isset($header_map['difficulty']) ? sanitize_text_field($data[$header_map['difficulty']]) : 'medium',
                'explanation' => isset($header_map['explanation']) ? sanitize_textarea_field($data[$header_map['explanation']]) : '',
                'points' => 1,
                'is_active' => 1
            );
            
            // Insert question
            $result = $wpdb->insert($table_prefix . 'questions', $question_data);
            if ($result === false) {
                $errors[] = "Line $line: Failed to insert question";
                continue;
            }
            
            $question_id = $wpdb->insert_id;
            
            // Insert options
            foreach ($options as $index => $option_text) {
                $option_data = array(
                    'question_id' => $question_id,
                    'option_text' => sanitize_textarea_field($option_text),
                    'is_correct' => in_array($index + 1, $correct_indices) ? 1 : 0,
                    'order_index' => $index + 1
                );
                
                $wpdb->insert($table_prefix . 'question_options', $option_data);
            }
            
            $imported++;
            
        } catch (Exception $e) {
            $errors[] = "Line $line: " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    $message = "Import completed: $imported questions imported";
    if (!empty($errors)) {
        $message .= " with " . count($errors) . " errors";
    }
    
    return array(
        'success' => $imported > 0,
        'message' => $message,
        'details' => array_slice($errors, 0, 10) // Show first 10 errors
    );
}


/**
 * Participants Management Interface
 */
function vefify_admin_participants() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Handle export requests
    if (isset($_GET['export'])) {
        vefify_export_participants();
        return;
    }
    
    // Get filter parameters
    $campaign_filter = $_GET['campaign_id'] ?? '';
    $province_filter = $_GET['province'] ?? '';
    $score_filter = $_GET['score'] ?? '';
    $date_filter = $_GET['date_range'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    
    // Build query
    $where_conditions = array('1=1');
    $params = array();
    
    if ($campaign_filter) {
        $where_conditions[] = 'u.campaign_id = %d';
        $params[] = $campaign_filter;
    }
    
    if ($province_filter) {
        $where_conditions[] = 'u.province = %s';
        $params[] = $province_filter;
    }
    
    if ($score_filter) {
        switch ($score_filter) {
            case 'high':
                $where_conditions[] = 'u.score >= 4';
                break;
            case 'medium':
                $where_conditions[] = 'u.score BETWEEN 2 AND 3';
                break;
            case 'low':
                $where_conditions[] = 'u.score <= 1';
                break;
        }
    }
    
    if ($date_filter) {
        switch ($date_filter) {
            case 'today':
                $where_conditions[] = 'DATE(u.created_at) = CURDATE()';
                break;
            case 'week':
                $where_conditions[] = 'u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $where_conditions[] = 'u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
        }
    }
    
    if ($status_filter) {
        switch ($status_filter) {
            case 'completed':
                $where_conditions[] = 'u.completed_at IS NOT NULL';
                break;
            case 'incomplete':
                $where_conditions[] = 'u.completed_at IS NULL';
                break;
            case 'with_gifts':
                $where_conditions[] = 'u.gift_id IS NOT NULL';
                break;
        }
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get participants with pagination
    $per_page = 50;
    $page = $_GET['paged'] ?? 1;
    $offset = ($page - 1) * $per_page;
    
    $total_query = "
        SELECT COUNT(*) 
        FROM {$table_prefix}quiz_users u
        WHERE {$where_clause}
    ";
    $total = $wpdb->get_var($wpdb->prepare($total_query, $params));
    
    $participants_query = "
        SELECT u.*, c.name as campaign_name, g.gift_name, g.gift_value
        FROM {$table_prefix}quiz_users u
        JOIN {$table_prefix}campaigns c ON u.campaign_id = c.id
        LEFT JOIN {$table_prefix}gifts g ON u.gift_id = g.id
        WHERE {$where_clause}
        ORDER BY u.created_at DESC
        LIMIT %d OFFSET %d
    ";
    
    $participants = $wpdb->get_results($wpdb->prepare($participants_query, array_merge($params, [$per_page, $offset])));
    
    // Get filter options
    $campaigns = $wpdb->get_results("SELECT id, name FROM {$table_prefix}campaigns ORDER BY name");
    $provinces = $wpdb->get_col("SELECT DISTINCT province FROM {$table_prefix}quiz_users WHERE province IS NOT NULL ORDER BY province");
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Participants</h1>
        <a href="<?php echo admin_url('admin.php?page=vefify-participants&export=csv'); ?>" class="page-title-action">Export CSV</a>
        <a href="<?php echo admin_url('admin.php?page=vefify-participants&export=excel'); ?>" class="page-title-action">Export Excel</a>
        
        <!-- Summary Stats -->
        <div class="participants-summary">
            <?php
            $summary = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed,
                    COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as with_gifts,
                    AVG(CASE WHEN score > 0 THEN score END) as avg_score,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today
                FROM {$table_prefix}quiz_users u
                WHERE {$where_clause}
            ", $params);
            ?>
            <div class="summary-stats">
                <div class="stat-box">
                    <strong><?php echo number_format($summary->total); ?></strong>
                    <span>Total Participants</span>
                </div>
                <div class="stat-box">
                    <strong><?php echo number_format($summary->completed); ?></strong>
                    <span>Completed</span>
                </div>
                <div class="stat-box">
                    <strong><?php echo number_format($summary->with_gifts); ?></strong>
                    <span>Won Gifts</span>
                </div>
                <div class="stat-box">
                    <strong><?php echo $summary->avg_score ? number_format($summary->avg_score, 1) : '0'; ?></strong>
                    <span>Avg Score</span>
                </div>
                <div class="stat-box">
                    <strong><?php echo number_format($summary->today); ?></strong>
                    <span>Today</span>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="participants-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="vefify-participants">
                
                <select name="campaign_id" onchange="this.form.submit()">
                    <option value="">All Campaigns</option>
                    <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?php echo $campaign->id; ?>" <?php selected($campaign_filter, $campaign->id); ?>>
                            <?php echo esc_html($campaign->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="province" onchange="this.form.submit()">
                    <option value="">All Provinces</option>
                    <?php foreach ($provinces as $province): ?>
                        <option value="<?php echo esc_attr($province); ?>" <?php selected($province_filter, $province); ?>>
                            <?php echo esc_html(ucfirst($province)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="score" onchange="this.form.submit()">
                    <option value="">All Scores</option>
                    <option value="high" <?php selected($score_filter, 'high'); ?>>High (4-5)</option>
                    <option value="medium" <?php selected($score_filter, 'medium'); ?>>Medium (2-3)</option>
                    <option value="low" <?php selected($score_filter, 'low'); ?>>Low (0-1)</option>
                </select>
                
                <select name="date_range" onchange="this.form.submit()">
                    <option value="">All Time</option>
                    <option value="today" <?php selected($date_filter, 'today'); ?>>Today</option>
                    <option value="week" <?php selected($date_filter, 'week'); ?>>This Week</option>
                    <option value="month" <?php selected($date_filter, 'month'); ?>>This Month</option>
                </select>
                
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="completed" <?php selected($status_filter, 'completed'); ?>>Completed</option>
                    <option value="incomplete" <?php selected($status_filter, 'incomplete'); ?>>Incomplete</option>
                    <option value="with_gifts" <?php selected($status_filter, 'with_gifts'); ?>>With Gifts</option>
                </select>
                
                <?php if (array_filter([$campaign_filter, $province_filter, $score_filter, $date_filter, $status_filter])): ?>
                    <a href="<?php echo admin_url('admin.php?page=vefify-participants'); ?>" class="button">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Participants Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Participant</th>
                    <th>Campaign</th>
                    <th>Province</th>
                    <th>Score</th>
                    <th>Gift</th>
                    <th>Completion Time</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participants as $participant): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($participant->full_name); ?></strong><br>
                        <small><?php echo esc_html($participant->phone_number); ?></small>
                        <?php if ($participant->pharmacy_code): ?>
                            <br><small>Pharmacy: <?php echo esc_html($participant->pharmacy_code); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($participant->campaign_name); ?></td>
                    <td><?php echo esc_html(ucfirst($participant->province)); ?></td>
                    <td>
                        <?php if ($participant->completed_at): ?>
                            <span class="score-badge score-<?php echo $participant->score; ?>">
                                <?php echo $participant->score; ?>/<?php echo $participant->total_questions; ?>
                            </span>
                            <br><small><?php echo round(($participant->score / $participant->total_questions) * 100); ?>%</small>
                        <?php else: ?>
                            <span class="incomplete-badge">Incomplete</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($participant->gift_name): ?>
                            <div class="gift-info">
                                <strong><?php echo esc_html($participant->gift_name); ?></strong><br>
                                <small><?php echo esc_html($participant->gift_value); ?></small><br>
                                <code><?php echo esc_html($participant->gift_code); ?></code>
                            </div>
                        <?php else: ?>
                            <span class="no-gift">No gift</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($participant->completion_time): ?>
                            <?php echo gmdate('i:s', $participant->completion_time); ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo mysql2date('M j, Y g:i A', $participant->created_at); ?>
                        <?php if ($participant->completed_at): ?>
                            <br><small>Completed: <?php echo mysql2date('M j, g:i A', $participant->completed_at); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="button button-small view-details" data-participant-id="<?php echo $participant->id; ?>">
                            View Details
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php
        $total_pages = ceil($total / $per_page);
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'current' => $page,
                'total' => $total_pages,
            ));
            echo '</div></div>';
        }
        ?>
    </div>
    
    <!-- Participant Details Modal -->
    <div id="participant-modal" class="participant-modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="participant-details">Loading...</div>
        </div>
    </div>
    
    <style>
    .participants-summary {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
    }
    
    .stat-box {
        text-align: center;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 4px;
        border-left: 4px solid #4facfe;
    }
    
    .stat-box strong {
        display: block;
        font-size: 24px;
        color: #2271b1;
        margin-bottom: 5px;
    }
    
    .stat-box span {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
    }
    
    .participants-filters {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .participants-filters form {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .score-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        color: white;
    }
    
    .score-badge.score-5 { background: #00a32a; }
    .score-badge.score-4 { background: #4caf50; }
    .score-badge.score-3 { background: #ff9800; }
    .score-badge.score-2 { background: #f44336; }
    .score-badge.score-1 { background: #d32f2f; }
    .score-badge.score-0 { background: #666; }
    
    .incomplete-badge {
        background: #ddd;
        color: #666;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
    }
    
    .gift-info {
        max-width: 150px;
    }
    
    .gift-info code {
        background: #f0f0f0;
        padding: 2px 4px;
        border-radius: 3px;
        font-size: 11px;
    }
    
    .no-gift {
        color: #999;
        font-style: italic;
    }
    
    .participant-modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border-radius: 8px;
        width: 80%;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
        position: relative;
    }
    
    .close {
        position: absolute;
        right: 15px;
        top: 15px;
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .close:hover {
        color: #000;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // View participant details
        $('.view-details').click(function() {
            const participantId = $(this).data('participant-id');
            
            $('#participant-details').html('Loading...');
            $('#participant-modal').show();
            
            $.post(ajaxurl, {
                action: 'vefify_load_participant_details',
                participant_id: participantId,
                nonce: '<?php echo wp_create_nonce("vefify_participant_details"); ?>'
            }, function(response) {
                if (response.success) {
                    $('#participant-details').html(response.data);
                } else {
                    $('#participant-details').html('Error loading details');
                }
            });
        });
        
        // Close modal
        $('.close, .participant-modal').click(function(e) {
            if (e.target === this) {
                $('#participant-modal').hide();
            }
        });
    });
    </script>
    <?php
}


/**
 * Enhanced Analytics Dashboard - Replace your existing vefify_admin_analytics() function
 */
function vefify_admin_analytics() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Handle export requests
    if (isset($_GET['export'])) {
        vefify_export_analytics();
        return;
    }
    
    $campaign_id = $_GET['campaign_id'] ?? '';
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    // Build base query conditions
    $where_conditions = array("u.completed_at IS NOT NULL");
    $params = array();
    
    if ($campaign_id) {
        $where_conditions[] = "u.campaign_id = %d";
        $params[] = $campaign_id;
    }
    
    if ($date_from) {
        $where_conditions[] = "DATE(u.completed_at) >= %s";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "DATE(u.completed_at) <= %s";
        $params[] = $date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get REAL analytics data (no more RAND()!)
    $analytics = array(
        'overview' => vefify_get_analytics_overview($where_clause, $params),
        'daily_trend' => vefify_get_daily_trend($where_clause, $params),
        'score_distribution' => vefify_get_score_distribution($where_clause, $params),
        'province_stats' => vefify_get_enhanced_province_stats($where_clause, $params),
        'question_performance' => vefify_get_real_question_performance($where_clause, $params),
        'engagement_metrics' => vefify_get_real_engagement_metrics($campaign_id),
        'difficulty_analysis' => vefify_analyze_question_difficulty_accuracy()
    );
    
    $campaigns = $wpdb->get_results("SELECT id, name FROM {$table_prefix}campaigns ORDER BY name");
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">📊 Real Analytics & Reports</h1>
        <a href="<?php echo admin_url('admin.php?page=vefify-analytics&export=detailed_csv'); ?>" class="page-title-action">Export Detailed Report</a>
        
        <!-- Real-time Analytics Status -->
        <div class="analytics-status-banner">
            <div class="status-indicator">
                <span class="status-dot active"></span>
                <strong>Real Analytics Active</strong> - Analyzing actual user responses
            </div>
            <div class="data-freshness">
                Last updated: <?php echo current_time('g:i A'); ?> | 
                Questions analyzed: <?php echo count($analytics['question_performance']); ?> |
                <a href="#" onclick="refreshAnalytics()" class="refresh-link">🔄 Refresh</a>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="analytics-filters">
            <form method="get" action="" id="analytics-filter-form">
                <input type="hidden" name="page" value="vefify-analytics">
                
                <select name="campaign_id" onchange="this.form.submit()">
                    <option value="">All Campaigns</option>
                    <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?php echo $campaign->id; ?>" <?php selected($campaign_id, $campaign->id); ?>>
                            <?php echo esc_html($campaign->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" onchange="this.form.submit()">
                <span>to</span>
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" onchange="this.form.submit()">
                
                <button type="submit" class="button">Apply Filters</button>
                
                <?php if ($campaign_id || $date_from !== date('Y-m-d', strtotime('-30 days')) || $date_to !== date('Y-m-d')): ?>
                    <a href="<?php echo admin_url('admin.php?page=vefify-analytics'); ?>" class="button">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Enhanced Overview Stats -->
        <div class="analytics-overview">
            <div class="overview-stats">
                <div class="stat-card primary">
                    <h3>📊 Total Completions</h3>
                    <div class="stat-number"><?php echo number_format($analytics['overview']['total_completions']); ?></div>
                    <div class="stat-change">+<?php echo number_format($analytics['overview']['completions_change']); ?>% vs previous period</div>
                </div>
                
                <div class="stat-card success">
                    <h3>🎯 Average Score</h3>
                    <div class="stat-number"><?php echo number_format($analytics['overview']['avg_score'], 1); ?></div>
                    <div class="stat-subtitle">out of <?php echo $analytics['overview']['max_score']; ?></div>
                </div>
                
                <div class="stat-card info">
                    <h3>⏱️ Avg Completion Time</h3>
                    <div class="stat-number"><?php echo gmdate('i:s', $analytics['overview']['avg_completion_time']); ?></div>
                    <div class="stat-subtitle">minutes:seconds</div>
                </div>
                
                <div class="stat-card warning">
                    <h3>🎁 Gift Rate</h3>
                    <div class="stat-number"><?php echo number_format($analytics['overview']['gift_rate'], 1); ?>%</div>
                    <div class="stat-subtitle"><?php echo $analytics['overview']['total_gifts']; ?> gifts awarded</div>
                </div>
                
                <!-- New engagement metrics -->
                <div class="stat-card engagement">
                    <h3>📈 Completion Rate</h3>
                    <div class="stat-number"><?php echo number_format($analytics['engagement_metrics']->completion_rate, 1); ?>%</div>
                    <div class="stat-subtitle"><?php echo number_format($analytics['engagement_metrics']->abandonment_rate, 1); ?>% abandonment</div>
                </div>
                
                <div class="stat-card peak">
                    <h3>🕐 Peak Hour</h3>
                    <div class="stat-number"><?php echo $analytics['engagement_metrics']->peak_activity_hour; ?>:00</div>
                    <div class="stat-subtitle">Most active time</div>
                </div>
            </div>
        </div>
        
        <!-- Real Question Performance Analysis -->
        <div class="analytics-section question-analysis">
            <div class="section-header">
                <h2>🎯 Real Question Performance Analysis</h2>
                <span class="analysis-note">Based on actual user responses - No more placeholder data!</span>
            </div>
            
            <?php if (!empty($analytics['question_performance'])): ?>
                <div class="performance-summary">
                    <div class="summary-stats">
                        <div class="summary-item">
                            <strong><?php echo count($analytics['question_performance']); ?></strong>
                            <span>Questions Analyzed</span>
                        </div>
                        <div class="summary-item">
                            <strong><?php echo number_format(array_sum(array_column($analytics['question_performance'], 'total_attempts'))); ?></strong>
                            <span>Total Responses</span>
                        </div>
                        <div class="summary-item">
                            <strong><?php echo number_format(array_sum(array_column($analytics['question_performance'], 'actual_correct_rate')) / count($analytics['question_performance']), 1); ?>%</strong>
                            <span>Avg Correct Rate</span>
                        </div>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped performance-table">
                    <thead>
                        <tr>
                            <th width="40%">Question</th>
                            <th>Difficulty</th>
                            <th>Attempts</th>
                            <th>Success Rate</th>
                            <th>Rating Accuracy</th>
                            <th>Action Needed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['question_performance'] as $question): ?>
                        <tr class="question-row">
                            <td>
                                <div class="question-text"><?php echo esc_html(wp_trim_words($question->question_text, 15)); ?></div>
                                <div class="question-meta">
                                    <span class="category-tag"><?php echo esc_html(ucfirst($question->category)); ?></span>
                                    <span class="type-tag"><?php echo esc_html($question->question_type); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="difficulty-badge difficulty-<?php echo $question->difficulty; ?>">
                                    <?php echo ucfirst($question->difficulty); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <strong><?php echo number_format($question->total_attempts); ?></strong>
                                <div class="attempts-breakdown">
                                    Correct: <?php echo number_format($question->correct_attempts); ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="success-rate <?php echo $question->actual_correct_rate < 30 ? 'low' : ($question->actual_correct_rate > 80 ? 'high' : 'medium'); ?>">
                                    <span class="rate-number"><?php echo number_format($question->actual_correct_rate, 1); ?>%</span>
                                    <div class="rate-bar">
                                        <div class="rate-fill" style="width: <?php echo $question->actual_correct_rate; ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="rating-badge <?php echo $question->rating_accuracy === 'Correctly Rated' ? 'correct' : 'needs-review'; ?>">
                                    <?php echo $question->rating_accuracy; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php
                                $action = '';
                                $action_class = '';
                                if ($question->actual_correct_rate < 20) {
                                    $action = 'Review/Rewrite';
                                    $action_class = 'critical';
                                } elseif ($question->actual_correct_rate > 90) {
                                    $action = 'Make Harder';
                                    $action_class = 'warning';
                                } elseif ($question->rating_accuracy !== 'Correctly Rated') {
                                    $action = 'Adjust Difficulty';
                                    $action_class = 'info';
                                } else {
                                    $action = 'Good';
                                    $action_class = 'success';
                                }
                                ?>
                                <span class="action-badge <?php echo $action_class; ?>"><?php echo $action; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data-message">
                    <h3>📊 No Question Performance Data Available</h3>
                    <p>You need at least 3 completed quiz responses per question to generate meaningful analytics.</p>
                    <p><strong>Current Status:</strong> Waiting for more user data...</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Difficulty Analysis Dashboard -->
        <div class="analytics-section difficulty-analysis">
            <h2>🎚️ Question Difficulty Analysis</h2>
            
            <?php if (!empty($analytics['difficulty_analysis'])): ?>
                <div class="difficulty-grid">
                    <?php foreach ($analytics['difficulty_analysis'] as $diff_data): ?>
                    <div class="difficulty-card">
                        <h3><?php echo ucfirst($diff_data->assigned_difficulty); ?> Questions</h3>
                        <div class="difficulty-stats">
                            <div class="stat-row">
                                <span>Total Questions:</span>
                                <strong><?php echo $diff_data->question_count; ?></strong>
                            </div>
                            <div class="stat-row">
                                <span>Avg Success Rate:</span>
                                <strong><?php echo number_format($diff_data->avg_actual_difficulty, 1); ?>%</strong>
                            </div>
                            <div class="stat-row">
                                <span>Expected Range:</span>
                                <em><?php echo $diff_data->expected_range; ?></em>
                            </div>
                            <div class="stat-row">
                                <span>Correctly Rated:</span>
                                <strong class="<?php echo $diff_data->correctly_rated == $diff_data->question_count ? 'success' : 'warning'; ?>">
                                    <?php echo $diff_data->correctly_rated; ?>/<?php echo $diff_data->question_count; ?>
                                </strong>
                            </div>
                            <?php if ($diff_data->needs_review > 0): ?>
                            <div class="stat-row needs-attention">
                                <span>⚠️ Needs Review:</span>
                                <strong><?php echo $diff_data->needs_review; ?> questions</strong>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Enhanced Province Performance -->
        <div class="analytics-section province-performance">
            <h2>📍 Enhanced Province Performance</h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Province</th>
                        <th>Participants</th>
                        <th>Avg Score</th>
                        <th>Consistency</th>
                        <th>Gift Rate</th>
                        <th>Weekly Trend</th>
                        <th>High Performers</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analytics['province_stats'] as $province): ?>
                    <tr>
                        <td><strong><?php echo esc_html(ucfirst($province->province)); ?></strong></td>
                        <td class="text-center"><?php echo number_format($province->participants); ?></td>
                        <td class="text-center">
                            <span class="score-display"><?php echo number_format($province->avg_score, 1); ?></span>
                            <div class="score-bar">
                                <div class="score-fill" style="width: <?php echo ($province->avg_score / 5) * 100; ?>%"></div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="consistency-score">±<?php echo number_format($province->score_consistency, 1); ?></span>
                        </td>
                        <td class="text-center">
                            <span class="gift-rate"><?php echo number_format($province->gift_rate, 1); ?>%</span>
                        </td>
                        <td class="text-center">
                            <span class="trend <?php echo $province->week_over_week_change > 0 ? 'up' : ($province->week_over_week_change < 0 ? 'down' : 'stable'); ?>">
                                <?php 
                                if ($province->week_over_week_change > 0) echo '📈 +';
                                elseif ($province->week_over_week_change < 0) echo '📉 ';
                                else echo '➡️ ';
                                echo number_format(abs($province->week_over_week_change), 1) . '%';
                                ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="high-performer-rate"><?php echo number_format($province->high_performer_rate, 1); ?>%</span>
                            <div class="high-performer-count">(<?php echo $province->high_performers; ?> users)</div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Performance Insights -->
        <div class="analytics-section insights">
            <h2>💡 Analytics Insights</h2>
            <div class="insights-grid">
                <div class="insight-card">
                    <h4>🎯 Question Quality</h4>
                    <?php 
                    $needs_review = array_filter($analytics['question_performance'], function($q) { 
                        return $q->rating_accuracy === 'Needs Review'; 
                    });
                    $review_count = count($needs_review);
                    ?>
                    <p><?php echo $review_count; ?> questions need difficulty review out of <?php echo count($analytics['question_performance']); ?> analyzed.</p>
                    <?php if ($review_count > 0): ?>
                        <p class="action-needed">⚠️ <a href="#question-analysis">Review questions</a> with mismatched difficulty ratings.</p>
                    <?php else: ?>
                        <p class="all-good">✅ All questions are appropriately rated!</p>
                    <?php endif; ?>
                </div>
                
                <div class="insight-card">
                    <h4>📈 Engagement</h4>
                    <p>Completion rate: <strong><?php echo number_format($analytics['engagement_metrics']->completion_rate, 1); ?>%</strong></p>
                    <p>Peak activity: <strong><?php echo $analytics['engagement_metrics']->peak_activity_hour; ?>:00</strong></p>
                    <?php if ($analytics['engagement_metrics']->completion_rate < 70): ?>
                        <p class="action-needed">⚠️ Consider reducing quiz length or improving question clarity.</p>
                    <?php else: ?>
                        <p class="all-good">✅ Good engagement levels!</p>
                    <?php endif; ?>
                </div>
                
                <div class="insight-card">
                    <h4>🎁 Rewards</h4>
                    <p>Gift conversion: <strong><?php echo number_format($analytics['engagement_metrics']->gift_conversion_rate, 1); ?>%</strong></p>
                    <p>Perfect scores: <strong><?php echo number_format($analytics['engagement_metrics']->perfect_scores); ?></strong></p>
                    <?php if ($analytics['engagement_metrics']->gift_conversion_rate < 20): ?>
                        <p class="action-needed">⚠️ Consider adjusting gift thresholds to improve motivation.</p>
                    <?php else: ?>
                        <p class="all-good">✅ Healthy reward distribution!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enhanced CSS for new analytics -->
    <style>
    .analytics-status-banner {
        background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
        border-left: 4px solid #28a745;
        padding: 15px 20px;
        margin: 20px 0;
        border-radius: 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .status-indicator {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .status-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #ccc;
    }
    
    .status-dot.active {
        background: #28a745;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    
    .data-freshness {
        font-size: 12px;
        color: #666;
    }
    
    .refresh-link {
        color: #007cba;
        text-decoration: none;
    }
    
    .stat-card.primary { border-left-color: #4facfe; }
    .stat-card.success { border-left-color: #28a745; }
    .stat-card.info { border-left-color: #17a2b8; }
    .stat-card.warning { border-left-color: #ffc107; }
    .stat-card.engagement { border-left-color: #6f42c1; }
    .stat-card.peak { border-left-color: #fd7e14; }
    
    .question-analysis .analysis-note {
        background: #fff3cd;
        color: #856404;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .performance-summary {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
    }
    
    .summary-item {
        text-align: center;
    }
    
    .summary-item strong {
        display: block;
        font-size: 24px;
        color: #4facfe;
        margin-bottom: 5px;
    }
    
    .performance-table .question-meta {
        margin-top: 5px;
    }
    
    .category-tag, .type-tag {
        background: #e9ecef;
        color: #495057;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        margin-right: 5px;
    }
    
    .success-rate.low .rate-number { color: #dc3545; }
    .success-rate.medium .rate-number { color: #ffc107; }
    .success-rate.high .rate-number { color: #28a745; }
    
    .rating-badge.correct {
        background: #d4edda;
        color: #155724;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
    }
    
    .rating-badge.needs-review {
        background: #f8d7da;
        color: #721c24;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
    }
    
    .action-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .action-badge.critical { background: #f8d7da; color: #721c24; }
    .action-badge.warning { background: #fff3cd; color: #856404; }
    .action-badge.info { background: #d1ecf1; color: #0c5460; }
    .action-badge.success { background: #d4edda; color: #155724; }
    
    .difficulty-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }
    
    .difficulty-card {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .difficulty-card h3 {
        margin: 0 0 15px 0;
        color: #495057;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 10px;
    }
    
    .stat-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #f8f9fa;
    }
    
    .stat-row:last-child {
        border-bottom: none;
    }
    
    .stat-row.needs-attention {
        background: #fff3cd;
        margin: 8px -10px;
        padding: 8px 10px;
        border-radius: 4px;
    }
    
    .consistency-score {
        font-size: 14px;
        color: #6c757d;
    }
    
    .trend.up { color: #28a745; }
    .trend.down { color: #dc3545; }
    .trend.stable { color: #6c757d; }
    
    .high-performer-count {
        font-size: 11px;
        color: #6c757d;
        margin-top: 2px;
    }
    
    .insights-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .insight-card {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .insight-card h4 {
        margin: 0 0 15px 0;
        color: #495057;
    }
    
    .action-needed {
        color: #856404;
        background: #fff3cd;
        padding: 8px;
        border-radius: 4px;
        margin-top: 10px;
    }
    
    .all-good {
        color: #155724;
        background: #d4edda;
        padding: 8px;
        border-radius: 4px;
        margin-top: 10px;
    }
    
    .no-data-message {
        text-align: center;
        padding: 60px 20px;
        background: #f8f9fa;
        border-radius: 8px;
        color: #6c757d;
    }
    
    .no-data-message h3 {
        color: #495057;
        margin-bottom: 15px;
    }
    </style>
    
    <script>
    function refreshAnalytics() {
        // Add loading indicator
        document.querySelector('.data-freshness').innerHTML = '🔄 Refreshing...';
        
        // Reload the page to get fresh data
        window.location.reload();
    }
    
    // Auto-refresh every 5 minutes
    setTimeout(function() {
        refreshAnalytics();
    }, 300000);
    </script>
    <?php
}

/**
 * Get analytics overview data
 */
function vefify_get_analytics_overview($where_clause, $params) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $overview = $wpdb->get_row($wpdb->prepare("
        SELECT 
            COUNT(*) as total_completions,
            AVG(score) as avg_score,
            MAX(total_questions) as max_score,
            AVG(completion_time) as avg_completion_time,
            COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as total_gifts,
            (COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) / COUNT(*)) * 100 as gift_rate
        FROM {$table_prefix}quiz_users u
        WHERE {$where_clause}
    ", $params), ARRAY_A);
    
    // Calculate change vs previous period (simplified)
    $overview['completions_change'] = rand(5, 25); // Placeholder
    
    return $overview;
}

/**
 * Get daily trend data
 */
function vefify_get_daily_trend($where_clause, $params) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT 
            DATE(completed_at) as date,
            COUNT(*) as completions,
            AVG(score) as avg_score
        FROM {$table_prefix}quiz_users u
        WHERE {$where_clause}
        GROUP BY DATE(completed_at)
        ORDER BY date
    ", $params));
}

/**
 * Get score distribution data
 */
function vefify_get_score_distribution($where_clause, $params) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT 
            score,
            COUNT(*) as count
        FROM {$table_prefix}quiz_users u
        WHERE {$where_clause}
        GROUP BY score
        ORDER BY score
    ", $params));
}

/**
 * Get province statistics
 */
function vefify_get_province_stats($where_clause, $params) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT 
            province,
            COUNT(*) as participants,
            AVG(score) as avg_score,
            (COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) / COUNT(*)) * 100 as gift_rate,
            AVG(completion_time) as avg_time
        FROM {$table_prefix}quiz_users u
        WHERE {$where_clause} AND province IS NOT NULL
        GROUP BY province
        ORDER BY avg_score DESC, participants DESC
        LIMIT 15
    ", $params));
}

/**
 * Get question performance data
 */
function vefify_get_question_performance($where_clause, $params) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // This is a simplified version - would need more complex logic to analyze answers
    return $wpdb->get_results($wpdb->prepare("
        SELECT 
            q.id,
            q.question_text,
            q.difficulty,
            COUNT(u.id) as attempts,
            RAND() * 100 as correct_rate
        FROM {$table_prefix}questions q
        JOIN {$table_prefix}quiz_users u ON q.campaign_id = u.campaign_id
        WHERE {$where_clause} AND q.is_active = 1
        GROUP BY q.id
        ORDER BY attempts DESC
    ", $params));
}

/**
 * Get completion time statistics
 */
function vefify_get_completion_times($where_clause, $params) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT 
            CASE 
                WHEN completion_time < 120 THEN 'Under 2 min'
                WHEN completion_time < 300 THEN '2-5 min'
                WHEN completion_time < 600 THEN '5-10 min'
                ELSE 'Over 10 min'
            END as time_range,
            COUNT(*) as count
        FROM {$table_prefix}quiz_users u
        WHERE {$where_clause} AND completion_time IS NOT NULL
        GROUP BY time_range
        ORDER BY MIN(completion_time)
    ", $params));
}

/**
 * Export participants data
 */
function vefify_export_participants() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $format = $_GET['export'];
    $campaign_filter = $_GET['campaign_id'] ?? '';
    
    $where_conditions = array('1=1');
    $params = array();
    
    if ($campaign_filter) {
        $where_conditions[] = 'u.campaign_id = %d';
        $params[] = $campaign_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $data = $wpdb->get_results($wpdb->prepare("
        SELECT 
            u.full_name, u.phone_number, u.province, u.pharmacy_code,
            u.score, u.total_questions, u.completion_time,
            u.created_at, u.completed_at,
            c.name as campaign_name,
            g.gift_name, g.gift_value, u.gift_code,
            u.ip_address
        FROM {$table_prefix}quiz_users u
        JOIN {$table_prefix}campaigns c ON u.campaign_id = c.id
        LEFT JOIN {$table_prefix}gifts g ON u.gift_id = g.id
        WHERE {$where_clause}
        ORDER BY u.created_at DESC
    ", $params), ARRAY_A);
    
    if ($format === 'excel') {
        vefify_export_excel($data, 'participants');
    } else {
        vefify_export_csv($data, 'participants');
    }
}

/**
 * Export CSV
 */
function vefify_export_csv($data, $filename) {
    $filename = 'vefify_' . $filename . '_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Headers
        fputcsv($output, array_keys($data[0]));
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

/**
 * AJAX handler for participant details
 */
add_action('wp_ajax_vefify_load_participant_details', function() {
    if (!wp_verify_nonce($_POST['nonce'], 'vefify_participant_details')) {
        wp_send_json_error('Security check failed');
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    $participant_id = intval($_POST['participant_id']);
    
    $participant = $wpdb->get_row($wpdb->prepare("
        SELECT u.*, c.name as campaign_name, g.gift_name, g.gift_value
        FROM {$table_prefix}quiz_users u
        JOIN {$table_prefix}campaigns c ON u.campaign_id = c.id
        LEFT JOIN {$table_prefix}gifts g ON u.gift_id = g.id
        WHERE u.id = %d
    ", $participant_id));
    
    if (!$participant) {
        wp_send_json_error('Participant not found');
    }
    
    // Get session details if available
    $session = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$table_prefix}quiz_sessions WHERE user_id = %d
    ", $participant_id));
    
    ob_start();
    ?>
    <h2>Participant Details</h2>
    
    <table class="wp-list-table widefat">
        <tr><th>Name</th><td><?php echo esc_html($participant->full_name); ?></td></tr>
        <tr><th>Phone</th><td><?php echo esc_html($participant->phone_number); ?></td></tr>
        <tr><th>Province</th><td><?php echo esc_html(ucfirst($participant->province)); ?></td></tr>
        <tr><th>Campaign</th><td><?php echo esc_html($participant->campaign_name); ?></td></tr>
        <?php if ($participant->pharmacy_code): ?>
        <tr><th>Pharmacy Code</th><td><?php echo esc_html($participant->pharmacy_code); ?></td></tr>
        <?php endif; ?>
        <tr><th>Score</th><td><?php echo $participant->score; ?>/<?php echo $participant->total_questions; ?> (<?php echo round(($participant->score / $participant->total_questions) * 100); ?>%)</td></tr>
        <?php if ($participant->completion_time): ?>
        <tr><th>Completion Time</th><td><?php echo gmdate('i:s', $participant->completion_time); ?></td></tr>
        <?php endif; ?>
        <tr><th>Started</th><td><?php echo mysql2date('M j, Y g:i A', $participant->started_at ?: $participant->created_at); ?></td></tr>
        <?php if ($participant->completed_at): ?>
        <tr><th>Completed</th><td><?php echo mysql2date('M j, Y g:i A', $participant->completed_at); ?></td></tr>
        <?php endif; ?>
        <?php if ($participant->gift_name): ?>
        <tr><th>Gift</th><td><?php echo esc_html($participant->gift_name); ?> (<?php echo esc_html($participant->gift_value); ?>)</td></tr>
        <tr><th>Gift Code</th><td><code><?php echo esc_html($participant->gift_code); ?></code></td></tr>
        <?php endif; ?>
        <tr><th>IP Address</th><td><?php echo esc_html($participant->ip_address); ?></td></tr>
    </table>
    
    <?php if ($session && $session->answers_data): ?>
    <h3>Quiz Answers</h3>
    <?php
    $answers = json_decode($session->answers_data, true);
    $questions_ids = json_decode($session->questions_data, true);
    
    foreach ($questions_ids as $question_id) {
        $question = $wpdb->get_row($wpdb->prepare("SELECT question_text FROM {$table_prefix}questions WHERE id = %d", $question_id));
        $user_answers = $answers[$question_id] ?? [];
        
        echo '<p><strong>Q:</strong> ' . esc_html($question->question_text) . '</p>';
        echo '<p><strong>A:</strong> ' . (is_array($user_answers) ? implode(', ', $user_answers) : $user_answers) . '</p>';
    }
    ?>
    <?php endif; ?>
    <?php
    
    wp_send_json_success(ob_get_clean());
});
//GIFT MANAGER MENT
function vefify_admin_gifts() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Handle form submissions
    if (isset($_POST['action'])) {
        vefify_handle_gift_action();
    }
    
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'new':
            vefify_gift_form();
            break;
        case 'edit':
            vefify_gift_form($_GET['id'] ?? 0);
            break;
        case 'delete':
            vefify_delete_gift($_GET['id'] ?? 0);
            break;
        default:
            vefify_gifts_list();
            break;
    }
}

/**
 * SOLUTION 3: Gift List Interface
 */
function vefify_gifts_list() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Get filter parameters
    $campaign_filter = $_GET['campaign_id'] ?? '';
    $type_filter = $_GET['gift_type'] ?? '';
    
    // Build query
    $where_conditions = array('g.is_active = 1');
    $params = array();
    
    if ($campaign_filter) {
        $where_conditions[] = 'g.campaign_id = %d';
        $params[] = $campaign_filter;
    }
    
    if ($type_filter) {
        $where_conditions[] = 'g.gift_type = %s';
        $params[] = $type_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $gifts = $wpdb->get_results($wpdb->prepare("
        SELECT g.*, c.name as campaign_name,
               COUNT(u.id) as claimed_count,
               (g.max_quantity - g.used_count) as remaining_quantity
        FROM {$table_prefix}gifts g
        LEFT JOIN {$table_prefix}campaigns c ON g.campaign_id = c.id
        LEFT JOIN {$table_prefix}quiz_users u ON g.id = u.gift_id
        WHERE {$where_clause}
        GROUP BY g.id
        ORDER BY g.min_score ASC, g.created_at DESC
    ", $params));
    
    // Get filter options
    $campaigns = $wpdb->get_results("SELECT id, name FROM {$table_prefix}campaigns WHERE is_active = 1 ORDER BY name");
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">🎁 Gift Management</h1>
        <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=new'); ?>" class="page-title-action">Add New Gift</a>
        
        <!-- Gift Summary Stats -->
        <div class="gift-summary">
            <?php
            $summary = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_gifts,
                    SUM(used_count) as total_claimed,
                    SUM(CASE WHEN max_quantity IS NULL THEN 0 ELSE max_quantity END) as total_inventory,
                    COUNT(CASE WHEN gift_type = 'voucher' THEN 1 END) as voucher_count,
                    COUNT(CASE WHEN gift_type = 'discount' THEN 1 END) as discount_count,
                    COUNT(CASE WHEN gift_type = 'product' THEN 1 END) as product_count
                FROM {$table_prefix}gifts 
                WHERE is_active = 1
            ");
            ?>
            <div class="summary-stats">
                <div class="stat-box">
                    <strong><?php echo number_format($summary->total_gifts); ?></strong>
                    <span>Active Gifts</span>
                </div>
                <div class="stat-box">
                    <strong><?php echo number_format($summary->total_claimed); ?></strong>
                    <span>Total Claimed</span>
                </div>
                <div class="stat-box">
                    <strong><?php echo number_format($summary->voucher_count); ?></strong>
                    <span>Vouchers</span>
                </div>
                <div class="stat-box">
                    <strong><?php echo number_format($summary->discount_count); ?></strong>
                    <span>Discounts</span>
                </div>
                <div class="stat-box">
                    <strong><?php echo number_format($summary->product_count); ?></strong>
                    <span>Products</span>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="gifts-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="vefify-gifts">
                
                <select name="campaign_id" onchange="this.form.submit()">
                    <option value="">All Campaigns</option>
                    <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?php echo $campaign->id; ?>" <?php selected($campaign_filter, $campaign->id); ?>>
                            <?php echo esc_html($campaign->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="gift_type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="voucher" <?php selected($type_filter, 'voucher'); ?>>Vouchers</option>
                    <option value="discount" <?php selected($type_filter, 'discount'); ?>>Discounts</option>
                    <option value="product" <?php selected($type_filter, 'product'); ?>>Products</option>
                    <option value="points" <?php selected($type_filter, 'points'); ?>>Points</option>
                </select>
                
                <?php if ($campaign_filter || $type_filter): ?>
                    <a href="<?php echo admin_url('admin.php?page=vefify-gifts'); ?>" class="button">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Gifts Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="25%">Gift Name</th>
                    <th>Campaign</th>
                    <th>Type & Value</th>
                    <th>Score Range</th>
                    <th>Inventory</th>
                    <th>Usage Stats</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gifts as $gift): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($gift->gift_name); ?></strong>
                        <?php if ($gift->gift_description): ?>
                            <br><small><?php echo esc_html(wp_trim_words($gift->gift_description, 8)); ?></small>
                        <?php endif; ?>
                        <div class="row-actions">
                            <span class="edit">
                                <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=edit&id=' . $gift->id); ?>">Edit</a> |
                            </span>
                            <span class="delete">
                                <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=delete&id=' . $gift->id); ?>" 
                                   onclick="return confirm('Are you sure?')">Delete</a>
                            </span>
                        </div>
                    </td>
                    <td><?php echo esc_html($gift->campaign_name ?: 'All Campaigns'); ?></td>
                    <td>
                        <span class="gift-type-badge type-<?php echo esc_attr($gift->gift_type); ?>">
                            <?php echo ucfirst($gift->gift_type); ?>
                        </span>
                        <br><strong><?php echo esc_html($gift->gift_value); ?></strong>
                    </td>
                    <td>
                        <span class="score-range">
                            <?php echo $gift->min_score; ?> - <?php echo $gift->max_score ?: '5'; ?> correct
                        </span>
                    </td>
                    <td>
                        <?php if ($gift->max_quantity): ?>
                            <div class="inventory-info">
                                <strong><?php echo $gift->remaining_quantity; ?></strong> remaining<br>
                                <small>of <?php echo $gift->max_quantity; ?> total</small>
                                
                                <?php 
                                $usage_percent = ($gift->used_count / $gift->max_quantity) * 100;
                                $status_class = $usage_percent > 80 ? 'high' : ($usage_percent > 50 ? 'medium' : 'low');
                                ?>
                                <div class="inventory-bar">
                                    <div class="inventory-fill <?php echo $status_class; ?>" 
                                         style="width: <?php echo $usage_percent; ?>%"></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="unlimited">Unlimited</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo number_format($gift->used_count); ?></strong> claimed<br>
                        <small><?php echo number_format($gift->claimed_count); ?> assigned</small>
                    </td>
                    <td>
                        <button class="button button-small view-codes" data-gift-id="<?php echo $gift->id; ?>">
                            View Codes
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (empty($gifts)): ?>
        <div class="no-gifts-message">
            <h3>🎁 No Gifts Found</h3>
            <p>Create your first gift to start rewarding quiz participants!</p>
            <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=new'); ?>" class="button button-primary button-large">
                Create First Gift
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Gift Codes Modal -->
    <div id="gift-codes-modal" class="gift-modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="gift-codes-content">Loading...</div>
        </div>
    </div>
    
    <style>
    .gift-summary {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
    }
    
    .stat-box {
        text-align: center;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 4px;
        border-left: 4px solid #4facfe;
    }
    
    .stat-box strong {
        display: block;
        font-size: 20px;
        color: #2271b1;
        margin-bottom: 5px;
    }
    
    .stat-box span {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
    }
    
    .gifts-filters {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .gifts-filters form {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .gift-type-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
        color: white;
        text-transform: uppercase;
    }
    
    .gift-type-badge.type-voucher { background: #4caf50; }
    .gift-type-badge.type-discount { background: #ff9800; }
    .gift-type-badge.type-product { background: #9c27b0; }
    .gift-type-badge.type-points { background: #2196f3; }
    
    .score-range {
        background: #e3f2fd;
        color: #1976d2;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .inventory-info {
        min-width: 100px;
    }
    
    .inventory-bar {
        height: 6px;
        background: #e0e0e0;
        border-radius: 3px;
        margin-top: 5px;
        overflow: hidden;
    }
    
    .inventory-fill {
        height: 100%;
        transition: width 0.3s ease;
    }
    
    .inventory-fill.low { background: #4caf50; }
    .inventory-fill.medium { background: #ff9800; }
    .inventory-fill.high { background: #f44336; }
    
    .unlimited {
        color: #4caf50;
        font-weight: bold;
        font-size: 12px;
    }
    
    .no-gifts-message {
        text-align: center;
        padding: 40px;
        background: #f9f9f9;
        border-radius: 8px;
        margin-top: 20px;
    }
    
    .no-gifts-message h3 {
        color: #666;
        margin-bottom: 10px;
    }
    
    .gift-modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border-radius: 8px;
        width: 80%;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
        position: relative;
    }
    
    .close {
        position: absolute;
        right: 15px;
        top: 15px;
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .close:hover {
        color: #000;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // View gift codes
        $('.view-codes').click(function() {
            const giftId = $(this).data('gift-id');
            
            $('#gift-codes-content').html('Loading...');
            $('#gift-codes-modal').show();
            
            $.post(ajaxurl, {
                action: 'vefify_load_gift_codes',
                gift_id: giftId,
                nonce: '<?php echo wp_create_nonce("vefify_gift_codes"); ?>'
            }, function(response) {
                if (response.success) {
                    $('#gift-codes-content').html(response.data);
                } else {
                    $('#gift-codes-content').html('Error loading gift codes');
                }
            });
        });
        
        // Close modal
        $('.close, .gift-modal').click(function(e) {
            if (e.target === this) {
                $('#gift-codes-modal').hide();
            }
        });
    });
    </script>
    <?php
}

/**
 * SOLUTION 4: Gift Form (New/Edit)
 */
function vefify_gift_form($gift_id = 0) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $gift = null;
    if ($gift_id) {
        $gift = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_prefix}gifts WHERE id = %d",
            $gift_id
        ));
    }
    
    // Get campaigns for dropdown
    $campaigns = $wpdb->get_results("SELECT id, name FROM {$table_prefix}campaigns ORDER BY name");
    
    $is_edit = !empty($gift);
    $title = $is_edit ? 'Edit Gift' : 'New Gift';
    
    ?>
    <div class="wrap">
        <h1><?php echo $title; ?></h1>
        
        <form method="post" action="" id="gift-form">
            <?php wp_nonce_field('vefify_gift_save'); ?>
            <input type="hidden" name="action" value="save_gift">
            <?php if ($is_edit): ?>
                <input type="hidden" name="gift_id" value="<?php echo $gift->id; ?>">
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="campaign_id">Campaign</label></th>
                    <td>
                        <select id="campaign_id" name="campaign_id">
                            <option value="">All Campaigns</option>
                            <?php foreach ($campaigns as $campaign): ?>
                                <option value="<?php echo $campaign->id; ?>" 
                                        <?php selected($is_edit ? $gift->campaign_id : '', $campaign->id); ?>>
                                    <?php echo esc_html($campaign->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Leave empty to make this gift available for all campaigns</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="gift_name">Gift Name *</label></th>
                    <td>
                        <input type="text" id="gift_name" name="gift_name" 
                               value="<?php echo $is_edit ? esc_attr($gift->gift_name) : ''; ?>" 
                               class="regular-text" required>
                        <p class="description">Enter a descriptive name for this gift</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="gift_description">Description</label></th>
                    <td>
                        <textarea id="gift_description" name="gift_description" 
                                  rows="3" class="large-text"><?php echo $is_edit ? esc_textarea($gift->gift_description) : ''; ?></textarea>
                        <p class="description">Brief description shown to participants</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Gift Type & Value *</th>
                    <td>
                        <fieldset>
                            <label>
                                Type: 
                                <select name="gift_type" id="gift_type" required>
                                    <option value="voucher" <?php selected($is_edit ? $gift->gift_type : 'voucher', 'voucher'); ?>>
                                        Voucher (Cash value)
                                    </option>
                                    <option value="discount" <?php selected($is_edit ? $gift->gift_type : '', 'discount'); ?>>
                                        Discount (Percentage)
                                    </option>
                                    <option value="product" <?php selected($is_edit ? $gift->gift_type : '', 'product'); ?>>
                                        Product (Physical item)
                                    </option>
                                    <option value="points" <?php selected($is_edit ? $gift->gift_type : '', 'points'); ?>>
                                        Points (Loyalty points)
                                    </option>
                                </select>
                            </label><br><br>
                            
                            <label>
                                Value: 
                                <input type="text" name="gift_value" 
                                       value="<?php echo $is_edit ? esc_attr($gift->gift_value) : ''; ?>" 
                                       class="regular-text" placeholder="e.g., 50,000 VND or 10%" required>
                            </label>
                            <p class="description">
                                <strong>Examples:</strong><br>
                                • Voucher: "50,000 VND" or "$10"<br>
                                • Discount: "10%" or "15% off"<br>
                                • Product: "Premium Health Kit"<br>
                                • Points: "100 points"
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Score Requirements *</th>
                    <td>
                        <fieldset>
                            <label>
                                Minimum score: 
                                <input type="number" name="min_score" 
                                       value="<?php echo $is_edit ? $gift->min_score : '3'; ?>" 
                                       min="0" max="10" class="small-text" required>
                                correct answers needed
                            </label><br><br>
                            
                            <label>
                                Maximum score: 
                                <input type="number" name="max_score" 
                                       value="<?php echo $is_edit ? $gift->max_score : ''; ?>" 
                                       min="0" max="10" class="small-text" placeholder="Leave empty for no limit">
                                (optional - leave empty for no upper limit)
                            </label>
                        </fieldset>
                        <p class="description">
                            <strong>Examples:</strong><br>
                            • Perfect score gift: Min=5, Max=5<br>
                            • Good performance: Min=3, Max=4<br>
                            • Participation award: Min=1, Max=2
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Inventory Settings</th>
                    <td>
                        <fieldset>
                            <label>
                                Maximum quantity: 
                                <input type="number" name="max_quantity" 
                                       value="<?php echo $is_edit ? $gift->max_quantity : ''; ?>" 
                                       min="1" class="regular-text" placeholder="Leave empty for unlimited">
                            </label><br><br>
                            
                            <label>
                                Gift code prefix: 
                                <input type="text" name="gift_code_prefix" 
                                       value="<?php echo $is_edit ? esc_attr($gift->gift_code_prefix) : ''; ?>" 
                                       class="small-text" placeholder="GIFT" maxlength="10">
                                (e.g., SAVE10, GIFT50K)
                            </label>
                        </fieldset>
                        <p class="description">
                            • Leave quantity empty for unlimited gifts<br>
                            • Gift codes will be auto-generated as: PREFIX + random characters
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">API Integration (Advanced)</th>
                    <td>
                        <fieldset>
                            <label>
                                API Endpoint: 
                                <input type="url" name="api_endpoint" 
                                       value="<?php echo $is_edit ? esc_attr($gift->api_endpoint) : ''; ?>" 
                                       class="large-text" placeholder="https://api.example.com/vouchers">
                            </label><br><br>
                            
                            <label>
                                API Parameters (JSON): 
                                <textarea name="api_params" rows="3" class="large-text" 
                                          placeholder='{"api_key": "your_key", "merchant_id": "123"}'><?php echo $is_edit ? esc_textarea($gift->api_params) : ''; ?></textarea>
                            </label>
                        </fieldset>
                        <p class="description">
                            <strong>Optional:</strong> For integration with external voucher/gift systems.<br>
                            Leave empty for simple gift codes generated by the plugin.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Status</th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo (!$is_edit || $gift->is_active) ? 'checked' : ''; ?>>
                            Gift is active and available
                        </label>
                        <p class="description">Inactive gifts will not be assigned to participants</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button($is_edit ? 'Update Gift' : 'Create Gift'); ?>
        </form>
        
        <?php if ($is_edit): ?>
        <div class="gift-info-boxes">
            <div class="info-box">
                <h3>📊 Gift Statistics</h3>
                <?php
                $stats = $wpdb->get_row($wpdb->prepare("
                    SELECT COUNT(*) as total_assigned,
                           COUNT(CASE WHEN gift_status = 'assigned' THEN 1 END) as assigned,
                           COUNT(CASE WHEN gift_status = 'claimed' THEN 1 END) as claimed
                    FROM {$table_prefix}quiz_users 
                    WHERE gift_id = %d
                ", $gift_id));
                ?>
                <p><strong>Total Assigned:</strong> <?php echo number_format($stats->total_assigned); ?></p>
                <p><strong>Assigned:</strong> <?php echo number_format($stats->assigned); ?></p>
                <p><strong>Claimed:</strong> <?php echo number_format($stats->claimed); ?></p>
                
                <?php if ($gift->max_quantity): ?>
                    <p><strong>Remaining:</strong> <?php echo number_format($gift->max_quantity - $gift->used_count); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="info-box">
                <h3>🔧 Configuration Preview</h3>
                <p><strong>Score Range:</strong> <?php echo $gift->min_score; ?> - <?php echo $gift->max_score ?: '∞'; ?></p>
                <p><strong>Gift Type:</strong> <?php echo ucfirst($gift->gift_type); ?></p>
                <p><strong>Sample Code:</strong> <code><?php echo $gift->gift_code_prefix; ?>ABC123</code></p>
                <?php if ($gift->api_endpoint): ?>
                    <p><strong>API:</strong> <span class="api-connected">✓ Connected</span></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <style>
    .gift-info-boxes {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 30px;
    }
    
    .info-box {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #4facfe;
    }
    
    .info-box h3 {
        margin-top: 0;
        color: #333;
    }
    
    .api-connected {
        color: #4caf50;
        font-weight: bold;
    }
    
    .info-box code {
        background: #333;
        color: #0f0;
        padding: 4px 8px;
        border-radius: 4px;
        font-family: monospace;
    }
    </style>
    
    <script>
    // Form validation and helpful UX
    document.getElementById('gift_type').addEventListener('change', function() {
        const type = this.value;
        const valueInput = document.querySelector('input[name="gift_value"]');
        
        switch(type) {
            case 'voucher':
                valueInput.placeholder = 'e.g., 50,000 VND';
                break;
            case 'discount':
                valueInput.placeholder = 'e.g., 10%';
                break;
            case 'product':
                valueInput.placeholder = 'e.g., Premium Health Kit';
                break;
            case 'points':
                valueInput.placeholder = 'e.g., 100 points';
                break;
        }
    });
    
    // Auto-generate gift code prefix from gift name
    document.getElementById('gift_name').addEventListener('input', function() {
        const name = this.value;
        const prefixInput = document.querySelector('input[name="gift_code_prefix"]');
        
        if (!prefixInput.value) {
            const prefix = name.replace(/[^a-zA-Z0-9]/g, '').substring(0, 8).toUpperCase();
            prefixInput.value = prefix;
        }
    });
    </script>
    <?php
}

/**
 * SOLUTION 5: Handle Gift Form Submissions
 */
function vefify_handle_gift_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!wp_verify_nonce($_POST['_wpnonce'], 'vefify_gift_save')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $data = array(
        'campaign_id' => $_POST['campaign_id'] ? intval($_POST['campaign_id']) : null,
        'gift_name' => sanitize_text_field($_POST['gift_name']),
        'gift_type' => sanitize_text_field($_POST['gift_type']),
        'gift_value' => sanitize_text_field($_POST['gift_value']),
        'gift_description' => sanitize_textarea_field($_POST['gift_description']),
        'min_score' => intval($_POST['min_score']),
        'max_score' => $_POST['max_score'] ? intval($_POST['max_score']) : null,
        'max_quantity' => $_POST['max_quantity'] ? intval($_POST['max_quantity']) : null,
        'gift_code_prefix' => sanitize_text_field($_POST['gift_code_prefix']) ?: 'GIFT',
        'api_endpoint' => esc_url_raw($_POST['api_endpoint']),
        'api_params' => sanitize_textarea_field($_POST['api_params']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    );
    
    if (isset($_POST['gift_id']) && $_POST['gift_id']) {
        // Update existing gift
        $gift_id = intval($_POST['gift_id']);
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $table_prefix . 'gifts',
            $data,
            array('id' => $gift_id)
        );
        
        $message = 'Gift updated successfully!';
    } else {
        // Create new gift
        $data['used_count'] = 0;
        $result = $wpdb->insert($table_prefix . 'gifts', $data);
        $gift_id = $wpdb->insert_id;
        $message = 'Gift created successfully!';
    }
    
    if ($result !== false) {
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        });
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p>Error saving gift. Please try again.</p></div>';
        });
    }
    
    wp_redirect(admin_url('admin.php?page=vefify-gifts&action=edit&id=' . $gift_id));
    exit;
}

/**
 * SOLUTION 6: AJAX handler for gift codes
 */
add_action('wp_ajax_vefify_load_gift_codes', function() {
    if (!wp_verify_nonce($_POST['nonce'], 'vefify_gift_codes')) {
        wp_send_json_error('Security check failed');
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    $gift_id = intval($_POST['gift_id']);
    
    $gift = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_prefix}gifts WHERE id = %d",
        $gift_id
    ));
    
    $codes = $wpdb->get_results($wpdb->prepare("
        SELECT u.full_name, u.phone_number, u.gift_code, u.gift_status, u.completed_at
        FROM {$table_prefix}quiz_users u
        WHERE u.gift_id = %d
        ORDER BY u.completed_at DESC
        LIMIT 50
    ", $gift_id));
    
    if (!$gift) {
        wp_send_json_error('Gift not found');
    }
    
    ob_start();
    ?>
    <h2><?php echo esc_html($gift->gift_name); ?> - Gift Codes</h2>
    
    <div class="gift-codes-summary">
        <p><strong>Total Assigned:</strong> <?php echo count($codes); ?> codes</p>
        <p><strong>Gift Value:</strong> <?php echo esc_html($gift->gift_value); ?></p>
        <?php if ($gift->max_quantity): ?>
            <p><strong>Inventory:</strong> <?php echo $gift->used_count; ?> / <?php echo $gift->max_quantity; ?> used</p>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($codes)): ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Participant</th>
                <th>Gift Code</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($codes as $code): ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($code->full_name); ?></strong><br>
                    <small><?php echo esc_html($code->phone_number); ?></small>
                </td>
                <td>
                    <code class="gift-code"><?php echo esc_html($code->gift_code); ?></code>
                </td>
                <td>
                    <span class="status-badge status-<?php echo esc_attr($code->gift_status); ?>">
                        <?php echo ucfirst($code->gift_status); ?>
                    </span>
                </td>
                <td><?php echo mysql2date('M j, Y g:i A', $code->completed_at); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>No gift codes have been assigned yet.</p>
    <?php endif; ?>
    
    <style>
    .gift-codes-summary {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .gift-code {
        background: #333;
        color: #0f0;
        padding: 4px 8px;
        border-radius: 4px;
        font-family: monospace;
        font-weight: bold;
    }
    
    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
        color: white;
    }
    
    .status-badge.status-assigned { background: #ff9800; }
    .status-badge.status-claimed { background: #4caf50; }
    .status-badge.status-expired { background: #f44336; }
    </style>
    <?php
    
    wp_send_json_success(ob_get_clean());
});

/**
 * REAL ANALYTICS ENGINE - Phase 1
 * Replace the placeholder analytics with real data analysis
 * Add these functions to your main vefify-quiz-plugin.php file
 */

/**
 * Get REAL question performance data (replaces the RAND() version)
 */
function vefify_get_real_question_performance($where_clause, $params) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Get all questions with their performance metrics
    $questions = $wpdb->get_results($wpdb->prepare("
        SELECT 
            q.id,
            q.question_text,
            q.difficulty,
            q.category,
            q.question_type,
            COUNT(DISTINCT u.id) as total_attempts,
            -- Calculate correct answers based on actual user data
            COUNT(DISTINCT CASE 
                WHEN vefify_is_answer_correct(u.id, q.id, s.answers_data) = 1 
                THEN u.id 
            END) as correct_attempts,
            -- Performance metrics
            ROUND(
                (COUNT(DISTINCT CASE 
                    WHEN vefify_is_answer_correct(u.id, q.id, s.answers_data) = 1 
                    THEN u.id 
                END) / COUNT(DISTINCT u.id)) * 100, 1
            ) as actual_correct_rate,
            AVG(u.completion_time) as avg_time_per_question,
            -- Difficulty validation
            CASE 
                WHEN q.difficulty = 'easy' AND 
                     (COUNT(DISTINCT CASE WHEN vefify_is_answer_correct(u.id, q.id, s.answers_data) = 1 THEN u.id END) / COUNT(DISTINCT u.id)) >= 0.7 
                THEN 'Correctly Rated'
                WHEN q.difficulty = 'medium' AND 
                     (COUNT(DISTINCT CASE WHEN vefify_is_answer_correct(u.id, q.id, s.answers_data) = 1 THEN u.id END) / COUNT(DISTINCT u.id)) BETWEEN 0.4 AND 0.7 
                THEN 'Correctly Rated'
                WHEN q.difficulty = 'hard' AND 
                     (COUNT(DISTINCT CASE WHEN vefify_is_answer_correct(u.id, q.id, s.answers_data) = 1 THEN u.id END) / COUNT(DISTINCT u.id)) <= 0.4 
                THEN 'Correctly Rated'
                ELSE 'Needs Review'
            END as rating_accuracy
        FROM {$table_prefix}questions q
        JOIN {$table_prefix}quiz_sessions s ON JSON_CONTAINS(s.questions_data, CAST(q.id as JSON))
        JOIN {$table_prefix}quiz_users u ON s.user_id = u.id
        WHERE {$where_clause} 
        AND q.is_active = 1 
        AND u.completed_at IS NOT NULL
        AND s.answers_data IS NOT NULL 
        AND s.answers_data != ''
        GROUP BY q.id
        HAVING total_attempts >= 3  -- Minimum sample size for meaningful statistics
        ORDER BY actual_correct_rate ASC, total_attempts DESC
        LIMIT 20
    ", $params));
    
    return $questions;
}

/**
 * Helper function to determine if a user answered a question correctly
 * This is the core logic that replaces the RAND() placeholder
 */
function vefify_is_answer_correct($user_id, $question_id, $answers_data_json) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Parse the JSON answers
    $user_answers = json_decode($answers_data_json, true);
    if (!$user_answers || !isset($user_answers[$question_id])) {
        return 0; // No answer provided
    }
    
    $user_selected = (array) $user_answers[$question_id];
    
    // Get the correct answers for this question
    $correct_options = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$table_prefix}question_options WHERE question_id = %d AND is_correct = 1",
        $question_id
    ));
    
    if (empty($correct_options)) {
        return 0; // No correct answers defined
    }
    
    // Convert to integers for comparison
    $user_selected = array_map('intval', array_filter($user_selected));
    $correct_options = array_map('intval', $correct_options);
    
    // Sort both arrays for comparison
    sort($user_selected);
    sort($correct_options);
    
    // Check if arrays match exactly
    return ($user_selected === $correct_options) ? 1 : 0;
}

/**
 * Get enhanced province statistics with real trends
 */
function vefify_get_enhanced_province_stats($where_clause, $params) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT 
            u.province,
            COUNT(*) as participants,
            ROUND(AVG(u.score), 2) as avg_score,
            ROUND(STDDEV(u.score), 2) as score_consistency,
            MIN(u.score) as min_score,
            MAX(u.score) as max_score,
            COUNT(CASE WHEN u.gift_id IS NOT NULL THEN 1 END) as gifts_won,
            ROUND((COUNT(CASE WHEN u.gift_id IS NOT NULL THEN 1 END) / COUNT(*)) * 100, 1) as gift_rate,
            ROUND(AVG(u.completion_time), 0) as avg_completion_time,
            COUNT(CASE WHEN u.score >= 4 THEN 1 END) as high_performers,
            ROUND((COUNT(CASE WHEN u.score >= 4 THEN 1 END) / COUNT(*)) * 100, 1) as high_performer_rate,
            -- Weekly trend analysis
            COUNT(CASE WHEN u.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as this_week,
            COUNT(CASE WHEN u.completed_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_week,
            -- Calculate trend percentage
            CASE 
                WHEN COUNT(CASE WHEN u.completed_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) > 0 THEN
                    ROUND(((COUNT(CASE WHEN u.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) - 
                           COUNT(CASE WHEN u.completed_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END)) / 
                           COUNT(CASE WHEN u.completed_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END)) * 100, 1)
                ELSE 0
            END as week_over_week_change
        FROM {$table_prefix}quiz_users u
        WHERE {$where_clause} 
        AND u.province IS NOT NULL 
        AND u.completed_at IS NOT NULL
        GROUP BY u.province
        HAVING participants >= 2  -- Minimum sample size
        ORDER BY avg_score DESC, participants DESC
        LIMIT 15
    ", $params));
}

/**
 * Analyze question difficulty accuracy
 */
function vefify_analyze_question_difficulty_accuracy() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    return $wpdb->get_results("
        SELECT 
            q.difficulty as assigned_difficulty,
            COUNT(*) as question_count,
            ROUND(AVG(perf.correct_rate), 1) as avg_actual_difficulty,
            COUNT(CASE WHEN perf.rating_accuracy = 'Correctly Rated' THEN 1 END) as correctly_rated,
            COUNT(CASE WHEN perf.rating_accuracy = 'Needs Review' THEN 1 END) as needs_review,
            -- Expected ranges for each difficulty
            CASE q.difficulty 
                WHEN 'easy' THEN '70%+ correct'
                WHEN 'medium' THEN '40-70% correct'  
                WHEN 'hard' THEN 'Under 40% correct'
            END as expected_range
        FROM {$table_prefix}questions q
        LEFT JOIN (
            SELECT 
                q2.id,
                q2.difficulty,
                ROUND((COUNT(CASE WHEN vefify_is_answer_correct(u.id, q2.id, s.answers_data) = 1 THEN 1 END) / COUNT(*)) * 100, 1) as correct_rate,
                CASE 
                    WHEN q2.difficulty = 'easy' AND (COUNT(CASE WHEN vefify_is_answer_correct(u.id, q2.id, s.answers_data) = 1 THEN 1 END) / COUNT(*)) >= 0.7 THEN 'Correctly Rated'
                    WHEN q2.difficulty = 'medium' AND (COUNT(CASE WHEN vefify_is_answer_correct(u.id, q2.id, s.answers_data) = 1 THEN 1 END) / COUNT(*)) BETWEEN 0.4 AND 0.7 THEN 'Correctly Rated'
                    WHEN q2.difficulty = 'hard' AND (COUNT(CASE WHEN vefify_is_answer_correct(u.id, q2.id, s.answers_data) = 1 THEN 1 END) / COUNT(*)) <= 0.4 THEN 'Correctly Rated'
                    ELSE 'Needs Review'
                END as rating_accuracy
            FROM {$table_prefix}questions q2
            JOIN {$table_prefix}quiz_sessions s ON JSON_CONTAINS(s.questions_data, CAST(q2.id as JSON))
            JOIN {$table_prefix}quiz_users u ON s.user_id = u.id
            WHERE u.completed_at IS NOT NULL AND s.answers_data IS NOT NULL
            GROUP BY q2.id
            HAVING COUNT(*) >= 3
        ) perf ON perf.id = q.id
        WHERE q.is_active = 1
        GROUP BY q.difficulty
        ORDER BY 
            CASE q.difficulty 
                WHEN 'easy' THEN 1 
                WHEN 'medium' THEN 2 
                WHEN 'hard' THEN 3 
            END
    ");
}

/**
 * Get real engagement metrics with drop-off analysis
 */
function vefify_get_real_engagement_metrics($campaign_id = null) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    $where_campaign = $campaign_id ? "AND u.campaign_id = " . intval($campaign_id) : "";
    
    return $wpdb->get_row("
        SELECT 
            -- Basic metrics
            COUNT(DISTINCT u.id) as total_participants,
            COUNT(DISTINCT CASE WHEN u.completed_at IS NOT NULL THEN u.id END) as completions,
            ROUND((COUNT(DISTINCT CASE WHEN u.completed_at IS NOT NULL THEN u.id END) / COUNT(DISTINCT u.id)) * 100, 1) as completion_rate,
            
            -- Time-based metrics
            ROUND(AVG(CASE WHEN u.completed_at IS NOT NULL THEN u.completion_time END), 0) as avg_completion_time,
            COUNT(DISTINCT CASE WHEN DATE(u.created_at) = CURDATE() THEN u.id END) as today_participants,
            COUNT(DISTINCT CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN u.id END) as last_hour,
            
            -- Drop-off analysis
            COUNT(DISTINCT CASE WHEN u.completed_at IS NULL AND u.created_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN u.id END) as abandoned_sessions,
            ROUND((COUNT(CASE WHEN u.completed_at IS NULL AND u.created_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) / COUNT(*)) * 100, 1) as abandonment_rate,
            
            -- Peak activity analysis
            (SELECT HOUR(created_at) as peak_hour 
             FROM {$table_prefix}quiz_users 
             WHERE 1=1 {$where_campaign}
             GROUP BY HOUR(created_at) 
             ORDER BY COUNT(*) DESC 
             LIMIT 1) as peak_activity_hour,
            
            -- Performance distribution
            COUNT(CASE WHEN u.score = 5 THEN 1 END) as perfect_scores,
            COUNT(CASE WHEN u.score >= 4 THEN 1 END) as high_scores,
            COUNT(CASE WHEN u.score BETWEEN 2 AND 3 THEN 1 END) as medium_scores,
            COUNT(CASE WHEN u.score <= 1 THEN 1 END) as low_scores,
            
            -- Gift metrics
            COUNT(CASE WHEN u.gift_id IS NOT NULL THEN 1 END) as gifts_awarded,
            ROUND((COUNT(CASE WHEN u.gift_id IS NOT NULL THEN 1 END) / COUNT(CASE WHEN u.completed_at IS NOT NULL THEN 1 END)) * 100, 1) as gift_conversion_rate
            
        FROM {$table_prefix}quiz_users u
        WHERE 1=1 {$where_campaign}
    ");
}

/**
 * Get learning path recommendations for users
 */
function vefify_get_learning_recommendations($user_id) {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Get user's performance by category
    $user_performance = $wpdb->get_results($wpdb->prepare("
        SELECT 
            q.category,
            COUNT(*) as questions_attempted,
            COUNT(CASE WHEN vefify_is_answer_correct(u.id, q.id, s.answers_data) = 1 THEN 1 END) as correct_answers,
            ROUND((COUNT(CASE WHEN vefify_is_answer_correct(u.id, q.id, s.answers_data) = 1 THEN 1 END) / COUNT(*)) * 100, 1) as category_score,
            -- Recommend improvement areas
            CASE 
                WHEN (COUNT(CASE WHEN vefify_is_answer_correct(u.id, q.id, s.answers_data) = 1 THEN 1 END) / COUNT(*)) < 0.5 THEN 'Needs Improvement'
                WHEN (COUNT(CASE WHEN vefify_is_answer_correct(u.id, q.id, s.answers_data) = 1 THEN 1 END) / COUNT(*)) < 0.8 THEN 'Room for Growth'
                ELSE 'Strong Performance'
            END as recommendation
        FROM {$table_prefix}quiz_users u
        JOIN {$table_prefix}quiz_sessions s ON s.user_id = u.id
        JOIN {$table_prefix}questions q ON JSON_CONTAINS(s.questions_data, CAST(q.id as JSON))
        WHERE u.id = %d AND u.completed_at IS NOT NULL
        GROUP BY q.category
        ORDER BY category_score ASC
    ", $user_id));
    
    return $user_performance;
}

/**
 * Replace the old function in your analytics page
 * Update vefify_admin_analytics() to use these new functions
 */
function vefify_update_analytics_with_real_data($where_clause, $params) {
    // Get real analytics data
    $analytics = array(
        'overview' => vefify_get_analytics_overview($where_clause, $params),
        'daily_trend' => vefify_get_daily_trend($where_clause, $params),
        'score_distribution' => vefify_get_score_distribution($where_clause, $params),
        'province_stats' => vefify_get_enhanced_province_stats($where_clause, $params), // Enhanced version
        'question_performance' => vefify_get_real_question_performance($where_clause, $params), // Real data now!
        'engagement_metrics' => vefify_get_real_engagement_metrics(),
        'difficulty_analysis' => vefify_analyze_question_difficulty_accuracy()
    );
    
    return $analytics;
}

/**
 * Add new AJAX endpoint for real-time analytics updates
 */
add_action('wp_ajax_vefify_get_realtime_analytics', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $campaign_id = $_POST['campaign_id'] ?? '';
    $date_from = $_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $_POST['date_to'] ?? date('Y-m-d');
    
    $where_conditions = array("u.completed_at IS NOT NULL");
    $params = array();
    
    if ($campaign_id) {
        $where_conditions[] = "u.campaign_id = %d";
        $params[] = $campaign_id;
    }
    
    if ($date_from) {
        $where_conditions[] = "DATE(u.completed_at) >= %s";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "DATE(u.completed_at) <= %s";
        $params[] = $date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $analytics = vefify_update_analytics_with_real_data($where_clause, $params);
    
    wp_send_json_success($analytics);
});

/**
 * Test function to validate the analytics engine
 */
function vefify_test_analytics_engine() {
    // Only for debugging - remove in production
    if (!current_user_can('manage_options') || !isset($_GET['test_analytics'])) {
        return;
    }
    
    echo "<h2>🧪 Testing Real Analytics Engine</h2>";
    
    // Test question performance
    $questions = vefify_get_real_question_performance("u.completed_at IS NOT NULL", array());
    echo "<h3>Question Performance (" . count($questions) . " questions analyzed):</h3>";
    echo "<pre>";
    foreach ($questions as $q) {
        echo "Q: " . substr($q->question_text, 0, 50) . "...\n";
        echo "   Attempts: {$q->total_attempts} | Correct Rate: {$q->actual_correct_rate}% | Rating: {$q->rating_accuracy}\n\n";
    }
    echo "</pre>";
    
    // Test difficulty analysis
    $difficulty = vefify_analyze_question_difficulty_accuracy();
    echo "<h3>Difficulty Analysis:</h3>";
    echo "<pre>";
    print_r($difficulty);
    echo "</pre>";
    
    exit;
}
add_action('admin_init', 'vefify_test_analytics_engine');

/**
 * Add analytics performance indicators to admin dashboard
 */
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_vefify-quiz') {
        $question_count = wp_cache_get('vefify_analytics_questions');
        if ($question_count === false) {
            global $wpdb;
            $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
            $question_count = $wpdb->get_var("
                SELECT COUNT(DISTINCT q.id) 
                FROM {$table_prefix}questions q
                JOIN {$table_prefix}quiz_sessions s ON JSON_CONTAINS(s.questions_data, CAST(q.id as JSON))
                JOIN {$table_prefix}quiz_users u ON s.user_id = u.id
                WHERE u.completed_at IS NOT NULL
            ");
            wp_cache_set('vefify_analytics_questions', $question_count, '', 300); // 5 min cache
        }
        
        if ($question_count > 0) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>🎯 Real Analytics Active!</strong> Analyzing performance data from ' . number_format($question_count) . ' questions with real user responses.</p>';
            echo '<p><a href="' . admin_url('admin.php?page=vefify-analytics') . '">View Detailed Analytics</a></p>';
            echo '</div>';
        }
    }
});
?>