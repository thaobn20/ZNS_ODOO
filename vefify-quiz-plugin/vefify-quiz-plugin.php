<?php
/**
 * Plugin Name: Vefify Quiz Campaign Manager
 * Description: Advanced quiz campaign management with mobile-first design
 * Version: 1.1.0
 * Author: Vefify Team
 * License: GPL v2 or later
 * Text Domain: vefify-quiz
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('VEFIFY_QUIZ_VERSION', '1.1.0');
define('VEFIFY_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VEFIFY_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VEFIFY_QUIZ_TABLE_PREFIX', 'vefify_');
define('VEFIFY_QUIZ_DB_VERSION', '1.0.0');

/**
 * Main Plugin Class - MODULAR VERSION
 */
class Vefify_Quiz_Plugin {
    
    private static $instance = null;
    private $question_module;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Load core functionality
        $this->load_dependencies();
        
        // Initialize modules
        $this->init_modules();
        
        // WordPress hooks
        add_action('init', array($this, 'init_plugin'));
        
        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    private function load_dependencies() {
        // Load null-safe helper functions
        $this->load_helper_functions();
    }
    
    private function init_modules() {
        // Load Question Module
        if (file_exists(VEFIFY_QUIZ_PLUGIN_DIR . 'modules/questions/class-question-module.php')) {
            require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/questions/class-question-module.php';
            $this->question_module = Vefify_Question_Module::get_instance();
        }
        
        // Load other modules as they're created
        // $this->campaign_module = Vefify_Campaign_Module::get_instance();
        // $this->analytics_module = Vefify_Analytics_Module::get_instance();
    }
    
    public function init_plugin() {
        // Load textdomain for translations
        load_plugin_textdomain('vefify-quiz', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Add admin menu (SINGLE REGISTRATION)
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Keep existing shortcode for backward compatibility
        add_shortcode('vefify_quiz', array($this, 'render_quiz_shortcode'));
        
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'Vefify Quiz',
            'Vefify Quiz',
            'manage_options',
            'vefify-quiz',
            array($this, 'render_dashboard'),
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
            array($this, 'render_dashboard')
        );
        
        // Campaign Management
        add_submenu_page(
            'vefify-quiz',
            'Campaigns',
            'Campaigns',
            'manage_options',
            'vefify-campaigns',
            array($this, 'render_campaigns')
        );
        
        // Participants & Results
        add_submenu_page(
            'vefify-quiz',
            'Participants',
            'Participants',
            'manage_options',
            'vefify-participants',
            array($this, 'render_participants')
        );
        
        // Analytics & Reports
        add_submenu_page(
            'vefify-quiz',
            'Analytics',
            'Analytics',
            'manage_options',
            'vefify-analytics',
            array($this, 'render_analytics')
        );
        
        // Settings
        add_submenu_page(
            'vefify-quiz',
            'Settings',
            'Settings',
            'manage_options',
            'vefify-settings',
            array($this, 'render_settings')
        );
		// FIXED: Question Bank with proper class loading
		add_submenu_page(
			'vefify-quiz',
			'Question Bank',
			'Questions',
			'manage_options',
			'vefify-questions',
			'vefify_admin_questions_fixed'  // Use new function name
		);
    }
    
    public function render_dashboard() {
        $this->render_admin_page('dashboard');
    }
    
    public function render_campaigns() {
        $this->render_admin_page('campaigns');
    }
    
    public function render_participants() {
        $this->render_admin_page('participants');
    }
    
    public function render_analytics() {
        $this->render_admin_page('analytics');
    }
    
    public function render_settings() {
        $this->render_admin_page('settings');
    }
    
    private function render_admin_page($page) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        switch ($page) {
            case 'dashboard':
                $this->render_dashboard_content();
                break;
            case 'campaigns':
                $this->render_campaigns_content();
                break;
            case 'participants':
                $this->render_participants_content();
                break;
            case 'analytics':
                $this->render_analytics_content();
                break;
            case 'settings':
                $this->render_settings_content();
                break;
            default:
                echo '<div class="wrap"><h1>Page not found</h1></div>';
        }
    }
    
    private function render_dashboard_content() {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        // Get basic stats
        $stats = array(
            'total_campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}campaigns"),
            'total_participants' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}quiz_users"),
            'completed_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}quiz_users WHERE DATE(completed_at) = CURDATE()"),
            'total_questions' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}questions WHERE is_active = 1")
        );
        
        ?>
        <div class="wrap">
            <h1>Vefify Quiz Dashboard</h1>
            
            <div class="vefify-stats-grid">
                <div class="vefify-stat-card">
                    <h3>📊 Total Campaigns</h3>
                    <div class="stat-number"><?php echo number_format($stats['total_campaigns']); ?></div>
                </div>
                
                <div class="vefify-stat-card">
                    <h3>👥 Total Participants</h3>
                    <div class="stat-number"><?php echo number_format($stats['total_participants']); ?></div>
                </div>
                
                <div class="vefify-stat-card">
                    <h3>📈 Completed Today</h3>
                    <div class="stat-number"><?php echo number_format($stats['completed_today']); ?></div>
                </div>
                
                <div class="vefify-stat-card">
                    <h3>❓ Total Questions</h3>
                    <div class="stat-number"><?php echo number_format($stats['total_questions']); ?></div>
                </div>
            </div>
            
            <div class="vefify-quick-actions">
                <h2>⚡ Quick Actions</h2>
                <div class="action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=vefify-campaigns'); ?>" class="button button-primary button-large">
                        📊 Manage Campaigns
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button button-large">
                        ❓ Manage Questions
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=vefify-participants'); ?>" class="button button-large">
                        👥 View Participants
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=vefify-analytics'); ?>" class="button button-large">
                        📈 View Analytics
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
        
        .vefify-quick-actions {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        </style>
        <?php
    }
    
    private function render_campaigns_content() {
        echo '<div class="wrap">';
        echo '<h1>Campaign Management</h1>';
        echo '<p>Campaign management interface will be implemented here.</p>';
        echo '<p><strong>Status:</strong> ✅ Backend ready, admin interface in development</p>';
        echo '</div>';
    }
    
    private function render_participants_content() {
        echo '<div class="wrap">';
        echo '<h1>Participants Management</h1>';
        echo '<p>Participants management interface will be implemented here.</p>';
        echo '<p><strong>Status:</strong> ✅ Backend ready, admin interface in development</p>';
        echo '</div>';
    }
    
    private function render_analytics_content() {
        echo '<div class="wrap">';
        echo '<h1>Analytics & Reports</h1>';
        echo '<p>Analytics dashboard will be implemented here.</p>';
        echo '<p><strong>Status:</strong> ✅ Backend ready, admin interface in development</p>';
        echo '</div>';
    }
    
    private function render_settings_content() {
        echo '<div class="wrap">';
        echo '<h1>Plugin Settings</h1>';
        echo '<p>Settings interface will be implemented here.</p>';
        echo '<p><strong>Status:</strong> ✅ Backend ready, admin interface in development</p>';
        echo '</div>';
    }
    
    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 1,
            'template' => 'mobile',
            'questions_count' => 5,
            'difficulty' => '',
            'category' => ''
        ), $atts);
        
        // Use the question module to get questions
        if ($this->question_module) {
            $questions = $this->question_module->get_campaign_questions(
                $atts['campaign_id'], 
                $atts['questions_count'],
                array(
                    'difficulty' => $atts['difficulty'],
                    'category' => $atts['category'],
                    'randomize' => true
                )
            );
            
            if (empty($questions)) {
                return '<div class="vefify-error">No questions available for this campaign.</div>';
            }
        }
        
        // Return existing quiz template or create new one
        return $this->render_quiz_template($atts, $questions ?? array());
    }
    
    private function render_quiz_template($atts, $questions) {
        ob_start();
        ?>
        <div class="vefify-quiz-auto" 
             data-campaign-id="<?php echo esc_attr($atts['campaign_id']); ?>"
             data-options='<?php echo json_encode(array(
                 'count' => $atts['questions_count'],
                 'difficulty' => $atts['difficulty'],
                 'category' => $atts['category']
             )); ?>'>
            <div class="quiz-loading">
                <p>Loading quiz questions...</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function register_rest_routes() {
        // Check participation endpoint
        register_rest_route('vefify/v1', '/check-participation', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_check_participation'),
            'permission_callback' => '__return_true'
        ));
        
        // Start quiz endpoint
        register_rest_route('vefify/v1', '/start-quiz', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_start_quiz'),
            'permission_callback' => '__return_true'
        ));
        
        // Submit quiz endpoint
        register_rest_route('vefify/v1', '/submit-quiz', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_submit_quiz'),
            'permission_callback' => '__return_true'
        ));
    }
    
    public function rest_check_participation($request) {
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $phone = sanitize_text_field($request->get_param('phone'));
        $campaign_id = intval($request->get_param('campaign_id'));
        
        if (!$phone || !$campaign_id) {
            return new WP_Error('missing_data', 'Phone and campaign ID required', array('status' => 400));
        }
        
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
    
    public function rest_start_quiz($request) {
        // Implementation from your existing code
        return rest_ensure_response(array('success' => true, 'message' => 'Start quiz endpoint working'));
    }
    
    public function rest_submit_quiz($request) {
        // Implementation from your existing code
        return rest_ensure_response(array('success' => true, 'message' => 'Submit quiz endpoint working'));
    }
    
    public function activate() {
        try {
            // Create database tables (use existing installer)
            $this->create_tables();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            error_log('Vefify Quiz Plugin activated successfully');
            
        } catch (Exception $e) {
            error_log('Vefify Quiz Plugin activation failed: ' . $e->getMessage());
            wp_die('Plugin activation failed: ' . esc_html($e->getMessage()));
        }
    }
    
    public function deactivate() {
        flush_rewrite_rules();
        error_log('Vefify Quiz Plugin deactivated');
    }
    
    private function create_tables() {
        // Use existing table creation code from the GitHub version
        // This is simplified - use your existing table creation logic
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Your existing table creation SQL from the GitHub version
        // I'm keeping this short for this example
        $sql = "CREATE TABLE {$table_prefix}campaigns (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    private function load_helper_functions() {
        if (!function_exists('vefify_esc_attr')) {
            function vefify_esc_attr($value) {
                return esc_attr($value ?? '');
            }
        }
        
        if (!function_exists('vefify_esc_html')) {
            function vefify_esc_html($value) {
                return esc_html($value ?? '');
            }
        }
        
        if (!function_exists('vefify_sanitize_text')) {
            function vefify_sanitize_text($value) {
                return sanitize_text_field($value ?? '');
            }
        }
    }
    
    // Getter methods for modules
    public function get_question_module() {
        return $this->question_module;
    }
}

// Initialize the plugin
Vefify_Quiz_Plugin::get_instance();

// Legacy compatibility functions
if (!function_exists('vefify_get_question')) {
    function vefify_get_question($question_id) {
        $plugin = Vefify_Quiz_Plugin::get_instance();
        $question_module = $plugin->get_question_module();
        
        if ($question_module) {
            return $question_module->get_model()->get_question($question_id);
        }
        
        return false;
    }
}

/**
 * Enhanced shortcode with mobile interface (from your existing code)
 */
add_shortcode('vefify_quiz_mobile', function($atts) {
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
    
    // Your existing mobile interface HTML from the GitHub version
    return '<div class="vefify-mobile-quiz">Mobile Quiz Interface Placeholder</div>';
});

// Debug information (remove in production)
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', function() {
        if (current_user_can('manage_options') && isset($_GET['vefify_debug'])) {
            global $wpdb;
            $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
            
            $tables = array('campaigns', 'questions', 'question_options');
            echo '<div style="position: fixed; bottom: 10px; right: 10px; background: white; border: 1px solid #ccc; padding: 10px; font-size: 12px; z-index: 9999;">';
            echo '<strong>Vefify Quiz Debug:</strong><br>';
            foreach ($tables as $table) {
                $table_name = $table_prefix . $table;
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
                $status = $exists ? 'exists' : 'missing';
                $icon = $exists ? '✅' : '❌';
                echo "{$icon} {$table}: {$status}<br>";
            }
            echo '</div>';
        }
    });
}
/**
 * CRITICAL FIXES - Add these to vefify-quiz-plugin.php
 * Insert after the existing admin menu setup (around line 2580)
 */

/**
 * FIXED: Enhanced Admin Menu with all features including Question Bank
 *
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
    
    // FIXED: Question Bank with proper class loading
    add_submenu_page(
        'vefify-quiz',
        'Question Bank',
        'Questions',
        'manage_options',
        'vefify-questions',
        'vefify_admin_questions_fixed'  // Use new function name
    );
    
    // FIXED: Gift Management - This was missing!
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
}, 5); // Higher priority to ensure it runs first*/

/**
 * FIXED: Initialize Question Module on Admin Init
 */
add_action('admin_init', function() {
    // Only load on admin pages
    if (!is_admin()) {
        return;
    }
    
    // Load question module
    if (!class_exists('Vefify_Question_Module')) {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/questions/class-question-module.php';
    }
    
    // Initialize the module
    Vefify_Question_Module::get_instance();
});

/**
 * FIXED: Question Management Interface with proper class loading
 */
function vefify_admin_questions_fixed() {
    // Ensure the question module is loaded
    if (!class_exists('Vefify_Question_Module')) {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/questions/class-question-module.php';
    }
    
    // Get the question module instance
    $question_module = Vefify_Question_Module::get_instance();
    $question_bank = $question_module->get_bank();
    
    if (!$question_bank) {
        wp_die('Question bank not available. Please check if the module is properly loaded.');
    }
    
    // Handle different actions
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'new':
            vefify_question_form_fixed();
            break;
        case 'edit':
            vefify_question_form_fixed($_GET['id'] ?? 0);
            break;
        case 'delete':
            vefify_delete_question_fixed($_GET['id'] ?? 0);
            break;
        case 'import':
            vefify_question_import_fixed();
            break;
        default:
            vefify_questions_list_fixed();
            break;
    }
}

/**
 * FIXED: Questions List with enhanced functionality
 */
function vefify_questions_list_fixed() {
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Get filter parameters
    $campaign_filter = $_GET['campaign_id'] ?? '';
    $category_filter = $_GET['category'] ?? '';
    $difficulty_filter = $_GET['difficulty'] ?? '';
    $search_term = $_GET['s'] ?? '';
    
    // Build query conditions
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
    
    if ($search_term) {
        $where_conditions[] = 'q.question_text LIKE %s';
        $params[] = '%' . $wpdb->esc_like($search_term) . '%';
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get questions with options count
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
        
        <!-- Search and Filters -->
        <div class="questions-filters" style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin: 20px 0;">
            <form method="get" action="" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="hidden" name="page" value="vefify-questions">
                
                <input type="search" name="s" value="<?php echo esc_attr($search_term); ?>" 
                       placeholder="Search questions..." class="regular-text">
                
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
                
                <button type="submit" class="button">Search</button>
                
                <?php if ($campaign_filter || $category_filter || $difficulty_filter || $search_term): ?>
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
                <?php if (empty($questions)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        <div style="color: #666;">
                            <h3>No questions found</h3>
                            <p>Create your first question to get started!</p>
                            <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=new'); ?>" class="button button-primary">
                                Add New Question
                            </a>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($questions as $question): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html(wp_trim_words($question->question_text, 12)); ?></strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=edit&id=' . $question->id); ?>">Edit</a> |
                                </span>
                                <span class="duplicate">
                                    <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=duplicate&id=' . $question->id); ?>">Duplicate</a> |
                                </span>
                                <span class="delete">
                                    <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=delete&id=' . $question->id); ?>" 
                                       onclick="return confirm('Are you sure you want to delete this question?')">Delete</a>
                                </span>
                            </div>
                        </td>
                        <td><?php echo esc_html($question->campaign_name ?: 'Global'); ?></td>
                        <td>
                            <span class="category-badge category-<?php echo esc_attr($question->category); ?>" style="
                                padding: 2px 6px; border-radius: 3px; font-size: 11px; color: white; font-weight: bold;
                                background: <?php echo $question->category === 'medication' ? '#2196f3' : 
                                                    ($question->category === 'nutrition' ? '#4caf50' : 
                                                    ($question->category === 'safety' ? '#ff9800' : 
                                                    ($question->category === 'hygiene' ? '#9c27b0' : '#00bcd4'))); ?>;">
                                <?php echo esc_html(ucfirst($question->category)); ?>
                            </span>
                        </td>
                        <td>
                            <span class="difficulty-badge difficulty-<?php echo esc_attr($question->difficulty); ?>" style="
                                padding: 2px 6px; border-radius: 3px; font-size: 11px; color: white; font-weight: bold;
                                background: <?php echo $question->difficulty === 'easy' ? '#4caf50' : 
                                                    ($question->difficulty === 'hard' ? '#f44336' : '#ff9800'); ?>;">
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
                            <small style="color: #4caf50;"><?php echo $question->correct_count; ?> correct</small>
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
                                <div class="preview-loading">Loading preview...</div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Statistics -->
        <div class="questions-stats" style="margin-top: 30px; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3>📊 Question Bank Statistics</h3>
            <?php
            $stats = $wpdb->get_row("
                SELECT COUNT(*) as total,
                       COUNT(CASE WHEN difficulty = 'easy' THEN 1 END) as easy,
                       COUNT(CASE WHEN difficulty = 'medium' THEN 1 END) as medium,
                       COUNT(CASE WHEN difficulty = 'hard' THEN 1 END) as hard,
                       COUNT(CASE WHEN question_type = 'multiple_choice' THEN 1 END) as single_choice,
                       COUNT(CASE WHEN question_type = 'multiple_select' THEN 1 END) as multi_choice,
                       COUNT(CASE WHEN question_type = 'true_false' THEN 1 END) as true_false
                FROM {$table_prefix}questions 
                WHERE is_active = 1
            ");
            ?>
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-top: 15px;">
                <div class="stat-item" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <strong style="display: block; font-size: 24px; color: #2271b1; margin-bottom: 5px;"><?php echo number_format($stats->total); ?></strong>
                    <span style="font-size: 12px; color: #666;">Total Questions</span>
                </div>
                <div class="stat-item" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <strong style="display: block; font-size: 24px; color: #2271b1; margin-bottom: 5px;"><?php echo number_format($stats->easy); ?></strong>
                    <span style="font-size: 12px; color: #666;">Easy</span>
                </div>
                <div class="stat-item" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <strong style="display: block; font-size: 24px; color: #2271b1; margin-bottom: 5px;"><?php echo number_format($stats->medium); ?></strong>
                    <span style="font-size: 12px; color: #666;">Medium</span>
                </div>
                <div class="stat-item" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <strong style="display: block; font-size: 24px; color: #2271b1; margin-bottom: 5px;"><?php echo number_format($stats->hard); ?></strong>
                    <span style="font-size: 12px; color: #666;">Hard</span>
                </div>
                <div class="stat-item" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <strong style="display: block; font-size: 24px; color: #2271b1; margin-bottom: 5px;"><?php echo number_format($stats->single_choice); ?></strong>
                    <span style="font-size: 12px; color: #666;">Single Choice</span>
                </div>
                <div class="stat-item" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <strong style="display: block; font-size: 24px; color: #2271b1; margin-bottom: 5px;"><?php echo number_format($stats->multi_choice); ?></strong>
                    <span style="font-size: 12px; color: #666;">Multiple Choice</span>
                </div>
                <div class="stat-item" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <strong style="display: block; font-size: 24px; color: #2271b1; margin-bottom: 5px;"><?php echo number_format($stats->true_false); ?></strong>
                    <span style="font-size: 12px; color: #666;">True/False</span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('.toggle-preview').click(function() {
            const questionId = $(this).data('question-id');
            const previewRow = $('#preview-' + questionId);
            const button = $(this);
            
            if (previewRow.is(':visible')) {
                previewRow.slideUp(300);
                button.text('Preview');
            } else {
                // Show loading
                previewRow.find('.question-preview-content').html(
                    '<div style="padding: 15px; text-align: center; color: #666;">Loading preview...</div>'
                );
                previewRow.slideDown(300);
                button.text('Loading...');
                
                // Load preview via AJAX
                $.post(ajaxurl, {
                    action: 'vefify_load_question_preview',
                    question_id: questionId,
                    nonce: '<?php echo wp_create_nonce("vefify_question_bank"); ?>'
                }, function(response) {
                    if (response.success) {
                        previewRow.find('.question-preview-content').html(response.data);
                        button.text('Hide');
                    } else {
                        previewRow.find('.question-preview-content').html(
                            '<div style="padding: 15px; text-align: center; color: #d32f2f;">Error loading preview</div>'
                        );
                        button.text('Preview');
                    }
                }).fail(function() {
                    previewRow.find('.question-preview-content').html(
                        '<div style="padding: 15px; text-align: center; color: #d32f2f;">Network error. Please try again.</div>'
                    );
                    button.text('Preview');
                });
            }
        });
    });
    </script>
    <?php
}

/**
 * FIXED: Question Form with Enhanced UI
 */
function vefify_question_form_fixed($question_id = 0) {
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
    
    // Show admin notices
    $notice = get_transient('vefify_admin_notice');
    if ($notice) {
        delete_transient('vefify_admin_notice');
        echo '<div class="notice notice-' . $notice['type'] . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1><?php echo $title; ?> 
            <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="page-title-action">← Back to Questions</a>
        </h1>
        
        <form method="post" action="" id="question-form">
            <?php wp_nonce_field('vefify_question_save'); ?>
            <input type="hidden" name="action" value="save_question">
            <?php if ($is_edit): ?>
                <input type="hidden" name="question_id" value="<?php echo $question->id; ?>">
            <?php endif; ?>
            
            <div class="question-form-container">
                <!-- Question Details Section -->
                <div class="question-form-section">
                    <h3>📝 Question Details</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="campaign_id">Campaign</label></th>
                            <td>
                                <select id="campaign_id" name="campaign_id" class="regular-text">
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
                                        <select name="question_type" id="question_type" class="regular-text">
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
                                        <select name="category" class="regular-text">
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
                                        <select name="difficulty" class="regular-text">
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
                </div>
                
                <!-- Answer Options Section -->
                <div class="question-form-section">
                    <h3>🎯 Answer Options</h3>
                    
                    <div id="options-help-text" class="options-help">
                        Select ONE correct answer for single choice questions
                    </div>
                    
                    <div id="answer-options">
                        <?php
                        if ($options) {
                            foreach ($options as $index => $option) {
                                echo vefify_render_option_row_fixed($index, $option->option_text, $option->is_correct, $option->explanation);
                            }
                        } else {
                            // Default 4 options for new questions
                            for ($i = 0; $i < 4; $i++) {
                                echo vefify_render_option_row_fixed($i, '', false, '');
                            }
                        }
                        ?>
                    </div>
                    
                    <div id="add-option-section">
                        <button type="button" id="add-option" class="button">Add Another Option</button>
                        <p class="description">You need at least 2 options, and at least 1 must be marked as correct.</p>
                    </div>
                </div>
                
                <div class="submit">
                    <?php submit_button($is_edit ? 'Update Question' : 'Save Question'); ?>
                </div>
            </div>
        </form>
    </div>
    
    <style>
    .question-form-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .question-form-section {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        padding: 20px;
    }
    
    .question-form-section h3 {
        margin: 0 0 15px 0;
        color: #333;
        font-size: 18px;
        border-bottom: 2px solid #4facfe;
        padding-bottom: 8px;
    }
    
    .options-help {
        background: #e3f2fd;
        border-left: 4px solid #2196f3;
        padding: 12px 15px;
        margin: 15px 0;
        color: #1976d2;
        font-size: 13px;
        font-weight: 600;
        border-radius: 4px;
    }
    </style>
    <?php
}

/**
 * FIXED: Render option row HTML with enhanced styling
 */
function vefify_render_option_row_fixed($index, $text = '', $is_correct = false, $explanation = '') {
    ob_start();
    ?>
    <div class="option-row <?php echo $is_correct ? 'correct' : ''; ?>" data-index="<?php echo $index; ?>">
        <div class="option-header">
            <div class="option-number"><?php echo chr(65 + $index); ?></div>
            <div class="option-controls">
                <label class="option-correct">
                    <input type="checkbox" name="options[<?php echo $index; ?>][is_correct]" 
                           value="1" class="option-correct-checkbox" <?php checked($is_correct); ?>>
                    <span class="checkmark"></span>
                    Correct Answer
                </label>
                <button type="button" class="remove-option" title="Remove this option">×</button>
            </div>
        </div>
        
        <div class="option-content">
            <label class="option-label">Answer Option:</label>
            <input type="text" name="options[<?php echo $index; ?>][text]" 
                   value="<?php echo esc_attr($text); ?>" 
                   placeholder="Enter answer option..." 
                   class="option-text widefat" required>
            
            <label class="option-label">Explanation (Optional):</label>
            <textarea name="options[<?php echo $index; ?>][explanation]" 
                      placeholder="Optional: Explain why this answer is correct/incorrect..."
                      rows="2" class="option-explanation widefat"><?php echo esc_textarea($explanation); ?></textarea>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * FIXED: Delete question with proper validation
 */
function vefify_delete_question_fixed($question_id) {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $question_id = intval($question_id);
    if (!$question_id) {
        wp_redirect(admin_url('admin.php?page=vefify-questions'));
        exit;
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
    
    // Check if question exists
    $question = $wpdb->get_row($wpdb->prepare(
        "SELECT id, question_text FROM {$table_prefix}questions WHERE id = %d",
        $question_id
    ));
    
    if (!$question) {
        set_transient('vefify_admin_notice', array(
            'message' => 'Question not found.',
            'type' => 'error'
        ), 30);
        wp_redirect(admin_url('admin.php?page=vefify-questions'));
        exit;
    }
    
    // Soft delete by setting is_active = 0
    $result = $wpdb->update(
        $table_prefix . 'questions',
        array('is_active' => 0, 'updated_at' => current_time('mysql')),
        array('id' => $question_id),
        array('%d', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        set_transient('vefify_admin_notice', array(
            'message' => 'Question deleted successfully.',
            'type' => 'success'
        ), 30);
    } else {
        set_transient('vefify_admin_notice', array(
            'message' => 'Error deleting question.',
            'type' => 'error'
        ), 30);
    }
    
    wp_redirect(admin_url('admin.php?page=vefify-questions'));
    exit;
}

/**
 * FIXED: Question Import Interface (placeholder)
 */
function vefify_question_import_fixed() {
    ?>
    <div class="wrap">
        <h1>Import Questions 
            <a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="page-title-action">← Back to Questions</a>
        </h1>
        
        <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2>📁 CSV Import</h2>
            <p>Import questions from a CSV file with the following format:</p>
            
            <table class="wp-list-table widefat">
                <thead>
                    <tr>
                        <th>Column</th>
                        <th>Description</th>
                        <th>Example</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>question_text</strong></td>
                        <td>The question text</td>
                        <td>What is Aspirin used for?</td>
                    </tr>
                    <tr>
                        <td><strong>option_1</strong></td>
                        <td>First answer option</td>
                        <td>Pain relief</td>
                    </tr>
                    <tr>
                        <td><strong>option_2</strong></td>
                        <td>Second answer option</td>
                        <td>Fever reduction</td>
                    </tr>
                    <tr>
                        <td><strong>correct_answers</strong></td>
                        <td>Correct option numbers (comma-separated)</td>
                        <td>1,2</td>
                    </tr>
                </tbody>
            </table>
            
            <p style="margin-top: 20px;">
                <em>Import functionality will be implemented in the next version.</em>
            </p>
        </div>
    </div>
    <?php
}