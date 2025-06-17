<?php
/**
 * Question Module Loader
 * File: modules/questions/class-question-module.php
 * 
 * FIXED VERSION - Main class that loads and initializes all question-related functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Model {
    
    private $model;
    private $bank;
    private $endpoints;
    private static $instance = null;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        $module_path = plugin_dir_path(__FILE__);
        
        // Load core components - check if files exist first
        $files_to_load = array(
            'class-question-model.php',
            'class-question-bank.php', 
            'question-endpoints.php'
        );
        
        foreach ($files_to_load as $file) {
            $file_path = $module_path . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                error_log("Vefify Quiz: Loaded question module file: {$file}");
            } else {
                error_log("Vefify Quiz: Missing question module file: {$file}");
            }
        }
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        try {
            // Initialize model (data handling)
            if (class_exists('Vefify_Question_Model')) {
                $this->model = new Vefify_Question_Model();
                error_log("Vefify Quiz: Question Model loaded successfully");
            } else {
                error_log("Vefify Quiz: Question Model not available - using fallback");
                $this->model = $this->create_fallback_model();
            }
            
            // Initialize admin interface (only in admin)
            if (is_admin()) {
                if (class_exists('Vefify_Question_Bank')) {
                    $this->bank = new Vefify_Question_Bank();
                    error_log("Vefify Quiz: Question Bank loaded successfully");
                } else {
                    error_log("Vefify Quiz: Question Bank not available - using fallback");
                    $this->bank = $this->create_fallback_bank();
                }
            }
            
            // Initialize REST API endpoints
            if (class_exists('Vefify_Question_Endpoints')) {
                $this->endpoints = new Vefify_Question_Endpoints();
                error_log("Vefify Quiz: Question Endpoints loaded successfully");
            } else {
                error_log("Vefify Quiz: Question Endpoints not available - using fallback");
                $this->endpoints = $this->create_fallback_endpoints();
            }
            
            // Hook into WordPress
            $this->init_hooks();
            
            error_log("Vefify Quiz: Question module components initialized");
            
        } catch (Exception $e) {
            error_log("Vefify Quiz: Error initializing question module: " . $e->getMessage());
            
            // Create fallback components for admin
            if (is_admin()) {
                $this->bank = $this->create_fallback_bank();
            }
            $this->model = $this->create_fallback_model();
            $this->endpoints = $this->create_fallback_endpoints();
        }
    }
    
    /**
     * Create fallback model when actual model is not available
     */
    private function create_fallback_model() {
        return new class {
            public function get_question($id) { 
                global $wpdb;
                $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
                return $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_prefix}questions WHERE id = %d", 
                    $id
                ));
            }
            
            public function create_question($data) { 
                error_log("Vefify Quiz: create_question called but model not loaded");
                return false; 
            }
            
            public function update_question($id, $data) { 
                error_log("Vefify Quiz: update_question called but model not loaded");
                return false; 
            }
            
            public function delete_question($id) { 
                error_log("Vefify Quiz: delete_question called but model not loaded");
                return false; 
            }
            
            public function get_questions($args = array()) { 
                global $wpdb;
                $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
                $limit = isset($args['limit']) ? intval($args['limit']) : 20;
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table_prefix}questions WHERE is_active = 1 ORDER BY created_at DESC LIMIT %d", 
                    $limit
                ));
            }
            
            public function validate_question_data($data) { 
                return array('valid' => false, 'errors' => array('Model not fully loaded'));
            }
        };
    }
    
    /**
     * Create fallback endpoints when actual endpoints are not available
     */
    private function create_fallback_endpoints() {
        return new class {
            public function __construct() {
                error_log("Vefify Quiz: Using fallback endpoints - API functionality limited");
            }
        };
    }
    
    /**
     * Create fallback admin interface
     */
    private function create_fallback_bank() {
        return new class {
            public function admin_page_router() {
                $this->display_fallback_interface();
            }
            
            private function display_fallback_interface() {
                global $wpdb;
                $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
                
                echo '<div class="wrap">';
                echo '<h1 class="wp-heading-inline">❓ Questions</h1>';
                echo '<a href="' . admin_url('admin.php?page=vefify-questions&action=new') . '" class="page-title-action">Add New Question</a>';
                echo '<hr class="wp-header-end">';
                
                echo '<div class="notice notice-warning">';
                echo '<p><strong>⚠️ Question Module Partially Loaded</strong></p>';
                echo '<p>The question module is working with basic functionality. Some features may be limited.</p>';
                echo '</div>';
                
                // Get questions from database directly
                $questions = $wpdb->get_results("
                    SELECT q.*, c.name as campaign_name
                    FROM {$table_prefix}questions q
                    LEFT JOIN {$table_prefix}campaigns c ON q.campaign_id = c.id
                    WHERE q.is_active = 1
                    ORDER BY q.created_at DESC
                    LIMIT 20
                ");
                
                if ($questions) {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>Question</th>';
                    echo '<th>Type</th>';
                    echo '<th>Category</th>';
                    echo '<th>Difficulty</th>';
                    echo '<th>Campaign</th>';
                    echo '<th>Created</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    
                    foreach ($questions as $question) {
                        echo '<tr>';
                        echo '<td><strong>' . esc_html(wp_trim_words($question->question_text, 8)) . '</strong></td>';
                        echo '<td>' . esc_html(ucfirst(str_replace('_', ' ', $question->question_type))) . '</td>';
                        echo '<td>' . esc_html($question->category ?: 'None') . '</td>';
                        echo '<td><span class="difficulty-badge difficulty-' . esc_attr($question->difficulty) . '">' . esc_html(ucfirst($question->difficulty)) . '</span></td>';
                        echo '<td>' . esc_html($question->campaign_name ?: 'Global') . '</td>';
                        echo '<td>' . mysql2date('M j, Y', $question->created_at) . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                } else {
                    echo '<div class="no-questions">';
                    echo '<h3>No Questions Found</h3>';
                    echo '<p>No questions have been created yet. <a href="' . admin_url('admin.php?page=vefify-questions&action=new') . '">Create your first question</a></p>';
                    echo '</div>';
                }
                
                // Show module status
                echo '<div class="module-status">';
                echo '<h3>📊 Module Status</h3>';
                echo '<ul>';
                echo '<li>✅ Question Module: Loaded (Basic Mode)</li>';
                echo '<li>' . (class_exists('Vefify_Question_Model') ? '✅' : '❌') . ' Question Model: ' . (class_exists('Vefify_Question_Model') ? 'Available' : 'Missing') . '</li>';
                echo '<li>' . (class_exists('Vefify_Question_Bank') ? '✅' : '❌') . ' Question Bank: ' . (class_exists('Vefify_Question_Bank') ? 'Available' : 'Using Fallback') . '</li>';
                echo '<li>' . (class_exists('Vefify_Question_Endpoints') ? '✅' : '❌') . ' API Endpoints: ' . (class_exists('Vefify_Question_Endpoints') ? 'Available' : 'Missing') . '</li>';
                echo '</ul>';
                echo '</div>';
                
                echo '</div>';
                
                // Add basic styling
                echo '<style>
                .difficulty-badge {
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-size: 11px;
                    color: white;
                    font-weight: bold;
                }
                .difficulty-badge.difficulty-easy { background: #4caf50; }
                .difficulty-badge.difficulty-medium { background: #ff9800; }
                .difficulty-badge.difficulty-hard { background: #f44336; }
                
                .no-questions {
                    text-align: center;
                    padding: 40px 20px;
                    background: #f9f9f9;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                
                .module-status {
                    background: #fff;
                    padding: 20px;
                    border-radius: 8px;
                    margin-top: 20px;
                    border-left: 4px solid #4facfe;
                }
                
                .module-status ul {
                    list-style: none;
                    padding-left: 0;
                }
                
                .module-status li {
                    margin: 8px 0;
                    padding: 5px 0;
                }
                </style>';
            }
        };
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Legacy function compatibility
        add_action('init', array($this, 'register_legacy_functions'));
        
        // Shortcode support
        add_shortcode('vefify_quiz_question', array($this, 'render_single_question_shortcode'));
        
        // AJAX handlers for public-facing functionality
        add_action('wp_ajax_vefify_get_quiz_questions', array($this, 'ajax_get_quiz_questions'));
        add_action('wp_ajax_nopriv_vefify_get_quiz_questions', array($this, 'ajax_get_quiz_questions'));
        
        add_action('wp_ajax_vefify_validate_quiz_answers', array($this, 'ajax_validate_quiz_answers'));
        add_action('wp_ajax_nopriv_vefify_validate_quiz_answers', array($this, 'ajax_validate_quiz_answers'));
        
        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * Get bank instance for compatibility
     */
    public function get_bank() {
        return $this->bank;
    }
    
    /**
     * Get model instance
     */
    public function get_model() {
        return $this->model;
    }
    
    /**
     * Register legacy functions for backward compatibility
     */
    public function register_legacy_functions() {
        // These functions maintain compatibility with existing code
        if (!function_exists('vefify_get_question')) {
            function vefify_get_question($question_id) {
                $module = Vefify_Question_Module::get_instance();
                $model = $module->get_model();
                return $model ? $model->get_question($question_id) : null;
            }
        }
        
        if (!function_exists('vefify_create_question')) {
            function vefify_create_question($data) {
                $module = Vefify_Question_Module::get_instance();
                $model = $module->get_model();
                return $model ? $model->create_question($data) : false;
            }
        }
        
        if (!function_exists('vefify_update_question')) {
            function vefify_update_question($question_id, $data) {
                $module = Vefify_Question_Module::get_instance();
                $model = $module->get_model();
                return $model ? $model->update_question($question_id, $data) : false;
            }
        }
        
        if (!function_exists('vefify_delete_question')) {
            function vefify_delete_question($question_id) {
                $module = Vefify_Question_Module::get_instance();
                $model = $module->get_model();
                return $model ? $model->delete_question($question_id) : false;
            }
        }
        
        if (!function_exists('vefify_get_questions')) {
            function vefify_get_questions($args = array()) {
                $module = Vefify_Question_Module::get_instance();
                $model = $module->get_model();
                return $model ? $model->get_questions($args) : array();
            }
        }
        
        if (!function_exists('vefify_validate_question_data')) {
            function vefify_validate_question_data($data) {
                $module = Vefify_Question_Module::get_instance();
                $model = $module->get_model();
                return $model ? $model->validate_question_data($data) : array('valid' => false, 'errors' => array('Module not loaded'));
            }
        }
    }
    
    /**
     * Render single question shortcode
     */
    public function render_single_question_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'show_answer' => false
        ), $atts);
        
        $question_id = intval($atts['id']);
        if (!$question_id) {
            return '<div class="vefify-error">Question ID required</div>';
        }
        
        // Get question using model (fallback or real)
        $question = null;
        if ($this->model) {
            $question = $this->model->get_question($question_id);
        }
        
        if (!$question) {
            return '<div class="vefify-error">Question not found</div>';
        }
        
        return '<div class="vefify-single-question">' . esc_html($question->question_text) . '</div>';
    }
    
    /**
     * AJAX handler for getting quiz questions
     */
    public function ajax_get_quiz_questions() {
        // Basic AJAX handler
        wp_send_json_error('Questions module needs full implementation');
    }
    
    /**
     * AJAX handler for validating quiz answers
     */
    public function ajax_validate_quiz_answers() {
        // Basic AJAX handler
        wp_send_json_error('Questions module needs full implementation');
    }
    
    /**
     * Enqueue frontend scripts for quiz functionality
     */
    public function enqueue_frontend_scripts() {
        // Only enqueue on pages that might have quiz shortcodes
        global $post;
        
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'vefify_quiz') || 
            has_shortcode($post->post_content, 'vefify_quiz_question')
        )) {
            wp_enqueue_script(
                'vefify-question-frontend',
                plugin_dir_url(__FILE__) . 'assets/question-frontend.js',
                array('jquery'),
                VEFIFY_QUIZ_VERSION,
                true
            );
            
            wp_enqueue_style(
                'vefify-question-frontend',
                plugin_dir_url(__FILE__) . 'assets/question-frontend.css',
                array(),
                VEFIFY_QUIZ_VERSION
            );
        }
    }
}

// Auto-initialize if this file is loaded directly
if (!class_exists('Vefify_Quiz_Plugin')) {
    // If main plugin not loaded, create basic initialization
    add_action('init', function() {
        if (defined('VEFIFY_QUIZ_VERSION')) {
            Vefify_Question_Module::get_instance();
        }
    });
}