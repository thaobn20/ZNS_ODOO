<?php
/**
 * Plugin Name: Advanced Quiz Manager - Mobile Campaign System
 * Plugin URI: https://yourwebsite.com/advanced-quiz-manager
 * Description: Complete mobile-first quiz plugin with campaigns, questions, gifts, analytics and Vietnamese provinces integration. Based on vefify_quiz_mobile.html design.
 * Version: 2.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: advanced-quiz
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AQM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AQM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AQM_VERSION', '2.0.0');

// Main plugin class
class AdvancedQuizManager {
    
    private $db;
    private $admin;
    private $frontend;
    private $api;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('AdvancedQuizManager', 'uninstall'));
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('advanced-quiz', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components
        $this->init_database();
        $this->init_admin();
        $this->init_frontend();
        $this->init_api();
        $this->init_scripts();
        $this->init_shortcodes();
        
        // Add custom body classes for quiz pages
        add_filter('body_class', array($this, 'add_quiz_body_classes'));
    }
    
    public function activate() {
        $this->create_tables();
        $this->create_default_data();
        $this->set_default_options();
        flush_rewrite_rules();
        
        // Schedule cleanup events
        if (!wp_next_scheduled('aqm_cleanup_expired_sessions')) {
            wp_schedule_event(time(), 'daily', 'aqm_cleanup_expired_sessions');
        }
        
        // Add activation notice
        set_transient('aqm_activation_notice', true, 30);
    }
    
    public function deactivate() {
        flush_rewrite_rules();
        wp_clear_scheduled_hook('aqm_cleanup_expired_sessions');
    }
    
    public static function uninstall() {
        global $wpdb;
        
        // Get plugin options
        $remove_data = get_option('aqm_remove_data_on_uninstall', false);
        
        if ($remove_data) {
            // Remove all plugin tables
            $tables = array(
                $wpdb->prefix . 'aqm_campaigns',
                $wpdb->prefix . 'aqm_questions',
                $wpdb->prefix . 'aqm_responses',
                $wpdb->prefix . 'aqm_gifts',
                $wpdb->prefix . 'aqm_provinces',
                $wpdb->prefix . 'aqm_districts',
                $wpdb->prefix . 'aqm_wards'
            );
            
            foreach ($tables as $table) {
                $wpdb->query("DROP TABLE IF EXISTS $table");
            }
            
            // Remove all plugin options
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aqm_%'");
            
            // Clear any cached data
            wp_cache_flush();
        }
    }
    
    private function init_database() {
        require_once AQM_PLUGIN_PATH . 'includes/class-database.php';
        $this->db = new AQM_Database();
    }
    
    private function init_admin() {
        if (is_admin()) {
            require_once AQM_PLUGIN_PATH . 'admin/class-admin.php';
            $this->admin = new AQM_Admin();
            
            // Add admin notices
            add_action('admin_notices', array($this, 'show_admin_notices'));
        }
    }
    
    private function init_frontend() {
        if (!is_admin()) {
            require_once AQM_PLUGIN_PATH . 'public/class-frontend.php';
            $this->frontend = new AQM_Frontend();
        }
    }
    
    private function init_api() {
        require_once AQM_PLUGIN_PATH . 'includes/class-api.php';
        $this->api = new AQM_API();
    }
    
    private function init_scripts() {
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
    }
    
    private function init_shortcodes() {
        add_shortcode('quiz_form', array($this, 'quiz_shortcode'));
        add_shortcode('quiz_results', array($this, 'results_shortcode'));
        add_shortcode('quiz_stats', array($this, 'stats_shortcode'));
    }
    
    public function quiz_shortcode($atts) {
        if (!$this->frontend) {
            require_once AQM_PLUGIN_PATH . 'public/class-frontend.php';
            $this->frontend = new AQM_Frontend();
        }
        
        return $this->frontend->quiz_form_shortcode($atts);
    }
    
    public function results_shortcode($atts) {
        if (!$this->frontend) {
            require_once AQM_PLUGIN_PATH . 'public/class-frontend.php';
            $this->frontend = new AQM_Frontend();
        }
        
        return $this->frontend->quiz_results_shortcode($atts);
    }
    
    public function stats_shortcode($atts) {
        if (!$this->frontend) {
            require_once AQM_PLUGIN_PATH . 'public/class-frontend.php';
            $this->frontend = new AQM_Frontend();
        }
        
        return $this->frontend->quiz_stats_shortcode($atts);
    }
    
    public function admin_scripts($hook) {
        if (strpos($hook, 'quiz-manager') !== false) {
            wp_enqueue_script('aqm-admin-js', AQM_PLUGIN_URL . 'assets/js/admin.js', 
                array('jquery', 'jquery-ui-sortable'), AQM_VERSION, true);
            wp_enqueue_style('aqm-admin-css', AQM_PLUGIN_URL . 'assets/css/admin.css', 
                array(), AQM_VERSION);
            
            wp_localize_script('aqm-admin-js', 'aqm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqm_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'advanced-quiz'),
                    'saved' => __('Saved successfully!', 'advanced-quiz'),
                    'error' => __('An error occurred. Please try again.', 'advanced-quiz'),
                    'loading' => __('Loading...', 'advanced-quiz'),
                    'save_first' => __('Please save the campaign first.', 'advanced-quiz')
                )
            ));
        }
    }
    
    public function frontend_scripts() {
        // Only load on pages with quiz shortcodes
        global $post;
        
        $load_scripts = false;
        
        if (is_a($post, 'WP_Post')) {
            if (has_shortcode($post->post_content, 'quiz_form') || 
                has_shortcode($post->post_content, 'quiz_results') ||
                has_shortcode($post->post_content, 'quiz_stats')) {
                $load_scripts = true;
            }
        }
        
        // Also load if quiz preview
        if (isset($_GET['aqm_preview'])) {
            $load_scripts = true;
        }
        
        if ($load_scripts) {
            wp_enqueue_script('aqm-frontend-js', AQM_PLUGIN_URL . 'assets/js/frontend.js', 
                array('jquery'), AQM_VERSION, true);
            wp_enqueue_style('aqm-frontend-css', AQM_PLUGIN_URL . 'assets/css/frontend.css', 
                array(), AQM_VERSION);
            
            wp_localize_script('aqm-frontend-js', 'aqm_front', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqm_front_nonce'),
                'strings' => array(
                    'loading' => __('Loading quiz questions...', 'advanced-quiz'),
                    'submitting' => __('Submitting answers...', 'advanced-quiz'),
                    'error' => __('An error occurred. Please try again.', 'advanced-quiz'),
                    'phone_exists' => __('You have already participated in this campaign.', 'advanced-quiz'),
                    'quiz_completed' => __('Quiz completed successfully!', 'advanced-quiz'),
                    'congratulations' => __('Congratulations!', 'advanced-quiz'),
                    'name_required' => __('Please enter your full name', 'advanced-quiz'),
                    'phone_required' => __('Please enter a valid phone number', 'advanced-quiz'),
                    'province_required' => __('Please select your province/city', 'advanced-quiz'),
                    'answer_required' => __('Please select at least one answer', 'advanced-quiz')
                )
            ));
        }
    }
    
    public function add_quiz_body_classes($classes) {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'quiz_form')) {
            $classes[] = 'aqm-quiz-page';
        }
        
        return $classes;
    }
    
    private function create_tables() {
        if (!$this->db) {
            require_once AQM_PLUGIN_PATH . 'includes/class-database.php';
            $this->db = new AQM_Database();
        }
        
        $this->db->create_tables();
    }
    
    private function create_default_data() {
        // Create sample campaign if none exists
        $existing_campaigns = $this->db->get_campaigns();
        
        if (empty($existing_campaigns)) {
            $sample_campaign_id = $this->db->create_campaign(array(
                'title' => 'Health & Wellness Quiz',
                'description' => 'Test your knowledge about health and wellness topics.',
                'status' => 'draft',
                'max_participants' => 0,
                'settings' => json_encode(array(
                    'enable_timer' => false,
                    'timer_minutes' => 10,
                    'randomize_questions' => false,
                    'show_results_immediately' => true
                )),
                'created_by' => get_current_user_id() ?: 1
            ));
            
            if ($sample_campaign_id) {
                // Add sample questions
                $sample_questions = array(
                    array(
                        'question_text' => 'Which of the following are benefits of regular exercise?',
                        'question_type' => 'multiple_choice',
                        'options' => json_encode(array(
                            array('id' => 'a', 'text' => 'Improved cardiovascular health', 'correct' => true),
                            array('id' => 'b', 'text' => 'Better sleep quality', 'correct' => true),
                            array('id' => 'c', 'text' => 'Increased stress levels', 'correct' => false),
                            array('id' => 'd', 'text' => 'Enhanced mental well-being', 'correct' => true)
                        )),
                        'points' => 1,
                        'order_index' => 1
                    ),
                    array(
                        'question_text' => 'What is the recommended daily water intake for adults?',
                        'question_type' => 'single_choice',
                        'options' => json_encode(array(
                            array('id' => 'a', 'text' => '1-2 glasses', 'correct' => false),
                            array('id' => 'b', 'text' => '8-10 glasses', 'correct' => true),
                            array('id' => 'c', 'text' => '15-20 glasses', 'correct' => false),
                            array('id' => 'd', 'text' => 'As little as possible', 'correct' => false)
                        )),
                        'points' => 1,
                        'order_index' => 2
                    ),
                    array(
                        'question_text' => 'Which foods are rich in vitamin C?',
                        'question_type' => 'multiple_choice',
                        'options' => json_encode(array(
                            array('id' => 'a', 'text' => 'Oranges', 'correct' => true),
                            array('id' => 'b', 'text' => 'Bell peppers', 'correct' => true),
                            array('id' => 'c', 'text' => 'White bread', 'correct' => false),
                            array('id' => 'd', 'text' => 'Strawberries', 'correct' => true)
                        )),
                        'points' => 1,
                        'order_index' => 3
                    )
                );
                
                foreach ($sample_questions as $question_data) {
                    $this->db->create_question($sample_campaign_id, $question_data);
                }
                
                // Add sample gift
                $this->db->create_gift($sample_campaign_id, array(
                    'title' => '50,000 VND Voucher',
                    'description' => 'Use this voucher at participating pharmacies',
                    'gift_type' => 'voucher',
                    'gift_value' => 50000,
                    'code_prefix' => 'HEALTH',
                    'quantity' => 100,
                    'requirements' => json_encode(array(
                        'min_score_percentage' => 70
                    ))
                ));
            }
        }
        
        // Import Vietnam provinces data if empty
        $this->import_sample_provinces_data();
    }
    
    private function import_sample_provinces_data() {
        $provinces_count = $this->db->get_provinces();
        
        if (empty($provinces_count)) {
            // Sample provinces data (you should replace with full dataset)
            $sample_provinces = array(
                array(
                    'code' => '01',
                    'name' => 'Hà Nội',
                    'name_en' => 'Hanoi',
                    'full_name' => 'Thành phố Hà Nội',
                    'full_name_en' => 'Hanoi City',
                    'code_name' => 'ha_noi'
                ),
                array(
                    'code' => '79',
                    'name' => 'Hồ Chí Minh',
                    'name_en' => 'Ho Chi Minh',
                    'full_name' => 'Thành phố Hồ Chí Minh',
                    'full_name_en' => 'Ho Chi Minh City',
                    'code_name' => 'ho_chi_minh'
                ),
                array(
                    'code' => '48',
                    'name' => 'Đà Nẵng',
                    'name_en' => 'Da Nang',
                    'full_name' => 'Thành phố Đà Nẵng',
                    'full_name_en' => 'Da Nang City',
                    'code_name' => 'da_nang'
                ),
                array(
                    'code' => '31',
                    'name' => 'Hải Phòng',
                    'name_en' => 'Hai Phong',
                    'full_name' => 'Thành phố Hải Phòng',
                    'full_name_en' => 'Hai Phong City',
                    'code_name' => 'hai_phong'
                ),
                array(
                    'code' => '92',
                    'name' => 'Cần Thơ',
                    'name_en' => 'Can Tho',
                    'full_name' => 'Thành phố Cần Thơ',
                    'full_name_en' => 'Can Tho City',
                    'code_name' => 'can_tho'
                )
            );
            
            $this->db->import_provinces_data($sample_provinces);
        }
    }
    
    private function set_default_options() {
        // Plugin settings
        add_option('aqm_version', AQM_VERSION);
        add_option('aqm_db_version', '2.0.0');
        add_option('aqm_remove_data_on_uninstall', false);
        
        // Default styling options
        add_option('aqm_default_theme', 'mobile');
        add_option('aqm_enable_touch_gestures', true);
        add_option('aqm_enable_progress_bar', true);
        
        // Security options
        add_option('aqm_enable_captcha', false);
        add_option('aqm_rate_limit_attempts', 3);
        add_option('aqm_rate_limit_window', 3600); // 1 hour
    }
    
    public function show_admin_notices() {
        // Activation notice
        if (get_transient('aqm_activation_notice')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>🎉 Advanced Quiz Manager activated!</strong> 
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager'); ?>">Go to Dashboard</a> 
                    or <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=add'); ?>">Create your first campaign</a>.
                </p>
            </div>
            <?php
            delete_transient('aqm_activation_notice');
        }
        
        // Check if sample data was imported
        $campaigns = $this->db->get_campaigns();
        if (empty($campaigns)) {
            ?>
            <div class="notice notice-info">
                <p>
                    <strong>📝 Get started:</strong> 
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=add'); ?>">Create your first quiz campaign</a> 
                    to begin collecting leads through engaging quizzes.
                </p>
            </div>
            <?php
        }
    }
    
    // Utility methods
    
    public static function get_instance() {
        static $instance = null;
        
        if (null === $instance) {
            $instance = new self();
        }
        
        return $instance;
    }
    
    public function get_database() {
        return $this->db;
    }
    
    public function get_admin() {
        return $this->admin;
    }
    
    public function get_frontend() {
        return $this->frontend;
    }
    
    public function get_api() {
        return $this->api;
    }
}

// Initialize the plugin
function aqm_init() {
    return AdvancedQuizManager::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'aqm_init');

// Helper functions for developers

function aqm_get_campaigns($args = array()) {
    $plugin = aqm_init();
    return $plugin->get_database()->get_campaigns($args);
}

function aqm_get_campaign($campaign_id) {
    $plugin = aqm_init();
    return $plugin->get_database()->get_campaign($campaign_id);
}

function aqm_get_campaign_responses($campaign_id, $limit = 100) {
    $plugin = aqm_init();
    return $plugin->get_database()->get_campaign_responses($campaign_id, $limit);
}

function aqm_render_quiz($campaign_id, $atts = array()) {
    $atts['campaign_id'] = $campaign_id;
    return do_shortcode('[quiz_form ' . http_build_query($atts, '', ' ') . ']');
}

// Cleanup scheduled event
add_action('aqm_cleanup_expired_sessions', function() {
    // Clean up any expired temporary data
    global $wpdb;
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE 'aqm_temp_%' 
         AND option_value < " . (time() - 3600)
    );
});
?>