<?php
/**
 * Question Module Loader - Main Entry Point
 * File: modules/questions/class-question-module.php
 * 
 * This is the main file that your plugin loading system expects.
 * It integrates with your centralized database system.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Module {
    
    private $model;
    private $bank;
    private $database;
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance($database = null) {
        if (self::$instance === null) {
            self::$instance = new self($database);
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct($database = null) {
        $this->database = $database;
        $this->load_dependencies();
        $this->init_components();
        
        error_log('Vefify Question Module: Loaded successfully with ' . ($database ? 'centralized' : 'fallback') . ' database');
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        $module_path = plugin_dir_path(__FILE__);
        
        // Load model first (handles database operations)
        if (!class_exists('Vefify_Question_Model')) {
            require_once $module_path . 'class-question-model.php';
        }
        
        // Load bank (handles admin interface)
        if (!class_exists('Vefify_Question_Bank')) {
            require_once $module_path . 'class-question-bank.php';
        }
        
        error_log('Vefify Question Module: Dependencies loaded');
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize model with database instance
        $this->model = new Vefify_Question_Model($this->database);
        
        // Initialize admin interface (only in admin)
        if (is_admin()) {
            $this->bank = new Vefify_Question_Bank($this->database);
        }
        
        // Set up hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin menu integration
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        
        // AJAX handlers for frontend
        add_action('wp_ajax_vefify_get_questions', array($this, 'ajax_get_questions'));
        add_action('wp_ajax_nopriv_vefify_get_questions', array($this, 'ajax_get_questions'));
        
        // Shortcode support (for frontend quiz display)
        add_shortcode('vefify_question', array($this, 'question_shortcode'));
        
        // REST API endpoints (if needed)
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
    }
    
    /**
     * Add admin menu (integrates with main plugin menu)
     */
    public function add_admin_menu() {
        // Only add if we have a bank instance
        if (!$this->bank) {
            return;
        }
        
        // Check if main menu exists, if not create it
        global $menu;
        $main_menu_exists = false;
        foreach ($menu as $item) {
            if (isset($item[2]) && $item[2] === 'vefify-quiz') {
                $main_menu_exists = true;
                break;
            }
        }
        
        if (!$main_menu_exists) {
            add_menu_page(
                'Vefify Quiz',
                'Vefify Quiz',
                'manage_options',
                'vefify-quiz',
                array($this, 'admin_dashboard'),
                'dashicons-clipboard',
                30
            );
        }
        
        // Add Questions submenu
        add_submenu_page(
            'vefify-quiz',
            '❓ Questions',
            '❓ Questions',
            'manage_options',
            'vefify-questions',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page handler
     */
    public function admin_page() {
        if ($this->bank) {
            $this->bank->admin_page_router();
        } else {
            echo '<div class="wrap">';
            echo '<h1>❓ Questions</h1>';
            echo '<div class="notice notice-error"><p>Question Bank not available. Please check your configuration.</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Admin dashboard (fallback if main plugin doesn't provide one)
     */
    public function admin_dashboard() {
        ?>
        <div class="wrap">
            <h1>Vefify Quiz Dashboard</h1>
            <div class="notice notice-info">
                <p>Welcome to Vefify Quiz! This is a fallback dashboard. Please configure your main plugin dashboard.</p>
            </div>
            
            <div class="vefify-dashboard-widgets">
                <div class="dashboard-widget">
                    <h3>❓ Questions</h3>
                    <?php 
                    $stats = $this->model->get_question_stats();
                    ?>
                    <p><strong><?php echo number_format($stats['total_questions']); ?></strong> Total Questions</p>
                    <p><strong><?php echo number_format($stats['active_questions']); ?></strong> Active Questions</p>
                    <p><a href="<?php echo admin_url('admin.php?page=vefify-questions'); ?>" class="button button-primary">Manage Questions</a></p>
                </div>
            </div>
        </div>
        <style>
        .vefify-dashboard-widgets { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px; }
        .dashboard-widget { background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; min-width: 250px; }
        .dashboard-widget h3 { margin-top: 0; }
        </style>
        <?php
    }
    
    /**
     * AJAX: Get questions for frontend
     */
    public function ajax_get_questions() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_frontend')) {
            wp_send_json_error('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $limit = intval($_POST['limit'] ?? 5);
        $difficulty = sanitize_text_field($_POST['difficulty'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        
        $args = array(
            'campaign_id' => $campaign_id ?: null,
            'difficulty' => $difficulty ?: null,
            'category' => $category ?: null,
            'per_page' => min($limit, 20), // Max 20 questions
            'page' => 1,
            'is_active' => 1
        );
        
        $result = $this->model->get_questions($args);
        
        // Format questions for frontend
        $formatted_questions = array();
        foreach ($result['questions'] as $question) {
            $formatted_questions[] = array(
                'id' => $question['id'],
                'text' => $question['question_text'],
                'type' => $question['question_type'],
                'points' => $question['points'],
                'options' => array_map(function($option) {
                    return array(
                        'id' => $option['id'],
                        'text' => $option['option_text'],
                        'order' => $option['order_index']
                    );
                }, $question['options'])
            );
        }
        
        wp_send_json_success(array(
            'questions' => $formatted_questions,
            'total' => $result['total']
        ));
    }
    
    /**
     * Question shortcode
     */
    public function question_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'campaign_id' => 0,
            'category' => '',
            'difficulty' => '',
            'limit' => 1
        ), $atts);
        
        if ($atts['id']) {
            // Display specific question
            $question = $this->model->get_question($atts['id']);
            if (!$question) {
                return '<p>Question not found.</p>';
            }
            
            return $this->render_single_question($question);
        } else {
            // Display random question(s) based on criteria
            $args = array(
                'campaign_id' => $atts['campaign_id'] ?: null,
                'category' => $atts['category'] ?: null,
                'difficulty' => $atts['difficulty'] ?: null,
                'per_page' => intval($atts['limit']),
                'page' => 1
            );
            
            $result = $this->model->get_questions($args);
            
            if (empty($result['questions'])) {
                return '<p>No questions found.</p>';
            }
            
            $output = '<div class="vefify-questions-container">';
            foreach ($result['questions'] as $question) {
                $output .= $this->render_single_question($question);
            }
            $output .= '</div>';
            
            return $output;
        }
    }
    
    /**
     * Render single question HTML
     */
    private function render_single_question($question) {
        ob_start();
        ?>
        <div class="vefify-question" data-question-id="<?php echo $question['id']; ?>">
            <div class="question-header">
                <h3 class="question-text"><?php echo esc_html($question['question_text']); ?></h3>
                <div class="question-meta">
                    <span class="difficulty difficulty-<?php echo esc_attr($question['difficulty']); ?>">
                        <?php echo esc_html(ucfirst($question['difficulty'])); ?>
                    </span>
                    <span class="points"><?php echo intval($question['points']); ?> point(s)</span>
                </div>
            </div>
            
            <div class="question-options">
                <?php foreach ($question['options'] as $index => $option): ?>
                    <div class="option">
                        <label>
                            <input type="<?php echo $question['question_type'] === 'multiple_select' ? 'checkbox' : 'radio'; ?>" 
                                   name="question_<?php echo $question['id']; ?>" 
                                   value="<?php echo $option['id']; ?>">
                            <span class="option-text"><?php echo esc_html($option['option_text']); ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($question['explanation']): ?>
                <div class="question-explanation" style="display: none;">
                    <p><strong>Explanation:</strong> <?php echo esc_html($question['explanation']); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .vefify-question { border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .question-header { margin-bottom: 15px; }
        .question-text { margin: 0 0 10px 0; }
        .question-meta { font-size: 0.9em; color: #666; }
        .difficulty-easy { color: #46b450; }
        .difficulty-medium { color: #ffb900; }
        .difficulty-hard { color: #dc3232; }
        .question-options { margin: 15px 0; }
        .option { margin: 8px 0; }
        .option label { display: flex; align-items: center; cursor: pointer; }
        .option input { margin-right: 10px; }
        .question-explanation { margin-top: 15px; padding: 10px; background: #f9f9f9; border-radius: 3px; }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Register REST API endpoints
     */
    public function register_rest_endpoints() {
        register_rest_route('vefify/v1', '/questions', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_questions'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('vefify/v1', '/questions/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_question'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * REST: Get questions
     */
    public function rest_get_questions($request) {
        $params = $request->get_params();
        
        $args = array(
            'campaign_id' => intval($params['campaign_id'] ?? 0) ?: null,
            'category' => sanitize_text_field($params['category'] ?? ''),
            'difficulty' => sanitize_text_field($params['difficulty'] ?? ''),
            'per_page' => min(intval($params['per_page'] ?? 10), 50),
            'page' => max(1, intval($params['page'] ?? 1))
        );
        
        $result = $this->model->get_questions($args);
        
        return new WP_REST_Response($result, 200);
    }
    
    /**
     * REST: Get single question
     */
    public function rest_get_question($request) {
        $question_id = intval($request['id']);
        $question = $this->model->get_question($question_id);
        
        if (!$question) {
            return new WP_Error('question_not_found', 'Question not found', array('status' => 404));
        }
        
        return new WP_REST_Response($question, 200);
    }
    
    /**
     * Get model instance (for external access)
     */
    public function get_model() {
        return $this->model;
    }
    
    /**
     * Get bank instance (for external access)
     */
    public function get_bank() {
        return $this->bank;
    }
    
    /**
     * Check if module is properly loaded
     */
    public function is_loaded() {
        return $this->model !== null && ($this->bank !== null || !is_admin());
    }
    
    /**
     * Get module status for debugging
     */
    public function get_module_status() {
        return array(
            'model_loaded' => $this->model !== null,
            'bank_loaded' => $this->bank !== null,
            'database_connected' => $this->database !== null,
            'tables_exist' => $this->model ? $this->model->debug_table_status() : false
        );
    }
    
    /**
     * Module analytics for dashboard
     */
    public function get_module_analytics() {
        if (!$this->model) {
            return array(
                'title' => 'Question Bank',
                'description' => 'Module not loaded',
                'icon' => '❓',
                'stats' => array(),
                'quick_actions' => array()
            );
        }
        
        $stats = $this->model->get_question_stats();
        
        return array(
            'title' => 'Question Bank',
            'description' => 'Manage and organize quiz questions',
            'icon' => '❓',
            'stats' => array(
                'total_questions' => array(
                    'label' => 'Total Questions',
                    'value' => number_format($stats['total_questions']),
                    'trend' => $stats['total_questions'] > 0 ? 'Active bank' : 'Ready to add'
                ),
                'active_questions' => array(
                    'label' => 'Active Questions',
                    'value' => number_format($stats['active_questions']),
                    'trend' => $stats['active_questions'] > 0 ? 'Available for use' : 'None active'
                ),
                'categories' => array(
                    'label' => 'Categories',
                    'value' => number_format($stats['total_categories']),
                    'trend' => $stats['total_categories'] > 1 ? 'Well organized' : 'Needs organization'
                ),
                'difficulty_balance' => array(
                    'label' => 'Difficulty Mix',
                    'value' => sprintf('E:%d M:%d H:%d', $stats['easy_questions'], $stats['medium_questions'], $stats['hard_questions']),
                    'trend' => 'Balanced variety'
                )
            ),
            'quick_actions' => array(
                array(
                    'label' => 'Add Question',
                    'url' => admin_url('admin.php?page=vefify-questions&action=new'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'Import CSV',
                    'url' => admin_url('admin.php?page=vefify-questions&action=import'),
                    'class' => 'button-secondary'
                ),
                array(
                    'label' => 'View All',
                    'url' => admin_url('admin.php?page=vefify-questions'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
}