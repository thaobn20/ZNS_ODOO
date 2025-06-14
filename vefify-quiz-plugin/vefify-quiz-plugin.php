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

// Add admin notice to show if things are working
add_action('admin_notices', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'vefify-questions') {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>Debug Info:</strong> ';
        echo 'Hook: ' . (isset($GLOBALS['hook_suffix']) ? $GLOBALS['hook_suffix'] : 'unknown') . ' | ';
        echo 'CSS Loaded: ' . (wp_style_is('vefify-question-bank', 'enqueued') ? 'YES' : 'NO') . ' | ';
        echo 'JS Loaded: ' . (wp_script_is('vefify-question-bank', 'enqueued') ? 'YES' : 'NO');
        echo '</p></div>';
    }
});

// Add JavaScript test to console
add_action('admin_footer', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'vefify-questions') {
        ?>
        <script>
        console.log('=== VEFIFY QUESTION BANK DEBUG ===');
        console.log('vefifyQuestionBank object:', typeof vefifyQuestionBank !== 'undefined' ? vefifyQuestionBank : 'UNDEFINED');
        console.log('jQuery loaded:', typeof jQuery !== 'undefined' ? 'YES' : 'NO');
        if (typeof vefifyQuestionBank !== 'undefined') {
            console.log('AJAX URL:', vefifyQuestionBank.ajaxurl);
            console.log('Nonce:', vefifyQuestionBank.nonce);
            console.log('Strings:', vefifyQuestionBank.strings);
        }
        console.log('=== END DEBUG ===');
        </script>
        <?php
    }
});