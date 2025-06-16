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

/**
 * Settings Page Placeholder
 */
function vefify_admin_settings() {
    ?>
    <div class="wrap">
        <h1>Settings</h1>
        <p>Settings page coming soon...</p>
    </div>
    <?php
}

/**
 * Plugin upgrade check and database repair
 */
add_action('admin_init', function() {
    // Check if we need to fix database issues
    $db_status = vefify_quiz_check_database();
    $missing_tables = array_filter($db_status, function($status) {
        return $status === 'missing';
    });
    
    if (!empty($missing_tables)) {
        // Try to recreate missing tables
        try {
            $installer = new Vefify_Quiz_Installer();
            $installer->create_tables();
        } catch (Exception $e) {
            error_log('Vefify Quiz: Failed to recreate missing tables: ' . $e->getMessage());
        }
    }
});

// FIXED: All functions and classes are now properly closed with braces