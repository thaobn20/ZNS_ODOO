<?php
/**
 * Streamlined Question Module Controller
 * File: modules/questions/class-question-module.php
 * 
 * Main controller that integrates with centralized menu and database
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Module {
    
    private $model;
    private $bank;
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
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        $module_path = plugin_dir_path(__FILE__);
        
        // Load model (always needed)
        require_once $module_path . 'class-question-model.php';
        
        // Load admin components only in admin
        if (is_admin()) {
            require_once $module_path . 'class-question-bank.php';
        }
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize model (needed for both frontend and admin)
        $this->model = new Vefify_Question_Model();
        
        // Initialize admin interface only in admin area
        if (is_admin()) {
            $this->bank = new Vefify_Question_Bank();
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Register with centralized menu system
        add_filter('vefify_quiz_admin_menu_items', array($this, 'register_menu_item'));
        
        // Register analytics with centralized dashboard
        add_filter('vefify_quiz_analytics_modules', array($this, 'register_analytics'));
        
        // Frontend AJAX handlers
        add_action('wp_ajax_vefify_get_quiz_questions', array($this, 'ajax_get_quiz_questions'));
        add_action('wp_ajax_nopriv_vefify_get_quiz_questions', array($this, 'ajax_get_quiz_questions'));
        
        add_action('wp_ajax_vefify_validate_quiz_answers', array($this, 'ajax_validate_quiz_answers'));
        add_action('wp_ajax_nopriv_vefify_validate_quiz_answers', array($this, 'ajax_validate_quiz_answers'));
        
        // Shortcode support
        add_shortcode('vefify_quiz_question', array($this, 'render_single_question_shortcode'));
        
        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * Register menu item with centralized menu system
     */
    public function register_menu_item($menu_items) {
        $menu_items[] = array(
            'page_title' => 'Question Bank',
            'menu_title' => '❓ Questions',
            'capability' => 'manage_options',
            'menu_slug' => 'vefify-questions',
            'callback' => array($this, 'admin_page_callback'),
            'position' => 20
        );
        
        return $menu_items;
    }
    
    /**
     * Register analytics with centralized dashboard
     */
    public function register_analytics($modules) {
        if ($this->bank) {
            $modules['questions'] = $this->bank->get_analytics_summary();
        }
        
        return $modules;
    }
    
    /**
     * Admin page callback
     */
    public function admin_page_callback() {
        if ($this->bank) {
            $this->bank->admin_page();
        } else {
            echo '<div class="wrap"><h1>Question Bank</h1><p>Admin interface not available.</p></div>';
        }
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // Only enqueue on pages that might have quiz shortcodes
        global $post;
        
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'vefify_quiz') || 
            has_shortcode($post->post_content, 'vefify_quiz_question')
        )) {
            wp_enqueue_script(
                'vefify-questions-frontend',
                plugin_dir_url(__FILE__) . 'assets/questions-frontend.js',
                array('jquery'),
                VEFIFY_QUIZ_VERSION,
                true
            );
            
            wp_enqueue_style(
                'vefify-questions-frontend',
                plugin_dir_url(__FILE__) . 'assets/questions-frontend.css',
                array(),
                VEFIFY_QUIZ_VERSION
            );
            
            // Localize script
            wp_localize_script('vefify-questions-frontend', 'vefifyQuestions', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vefify_questions_frontend'),
                'strings' => array(
                    'loading' => __('Loading questions...', 'vefify-quiz'),
                    'error' => __('An error occurred. Please try again.', 'vefify-quiz'),
                    'submit' => __('Submit Answer', 'vefify-quiz'),
                    'next' => __('Next Question', 'vefify-quiz'),
                    'finish' => __('Finish Quiz', 'vefify-quiz')
                )
            ));
        }
    }
    
    /**
     * AJAX: Get quiz questions for frontend
     */
    public function ajax_get_quiz_questions() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_questions_frontend')) {
            wp_send_json_error('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $count = intval($_POST['count'] ?? 5);
        $difficulty = sanitize_text_field($_POST['difficulty'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        
        if (!$campaign_id) {
            wp_send_json_error('Campaign ID is required');
        }
        
        try {
            // Get questions
            $args = array(
                'campaign_id' => $campaign_id,
                'is_active' => 1,
                'per_page' => $count * 2, // Get more for randomization
                'page' => 1
            );
            
            if ($difficulty) {
                $args['difficulty'] = $difficulty;
            }
            
            if ($category) {
                $args['category'] = $category;
            }
            
            $result = $this->model->get_questions($args);
            $questions = $result['questions'];
            
            // Randomize and limit
            shuffle($questions);
            $questions = array_slice($questions, 0, $count);
            
            // Format for frontend (remove correct answers for security)
            $formatted_questions = array();
            foreach ($questions as $question) {
                $formatted_question = array(
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'category' => $question->category,
                    'difficulty' => $question->difficulty,
                    'points' => $question->points,
                    'options' => array()
                );
                
                // Add options without correct answer information
                foreach ($question->options as $option) {
                    $formatted_question['options'][] = array(
                        'id' => $option->id,
                        'text' => $option->option_text,
                        'order_index' => $option->order_index
                    );
                }
                
                $formatted_questions[] = $formatted_question;
            }
            
            wp_send_json_success(array(
                'questions' => $formatted_questions,
                'total_available' => $result['total']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to load questions: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Validate quiz answers
     */
    public function ajax_validate_quiz_answers() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vefify_questions_frontend')) {
            wp_send_json_error('Security check failed');
        }
        
        $answers = $_POST['answers'] ?? array();
        
        if (!is_array($answers) || empty($answers)) {
            wp_send_json_error('Invalid answers format');
        }
        
        try {
            $results = array();
            $total_score = 0;
            $total_questions = count($answers);
            
            foreach ($answers as $question_id => $user_answers) {
                $question_id = intval($question_id);
                $question = $this->model->get_question($question_id);
                
                if (!$question) {
                    continue;
                }
                
                // Get correct answers
                $correct_answers = array();
                foreach ($question->options as $option) {
                    if ($option->is_correct) {
                        $correct_answers[] = $option->id;
                    }
                }
                
                // Normalize user answers
                if (!is_array($user_answers)) {
                    $user_answers = array($user_answers);
                }
                $user_answers = array_map('intval', $user_answers);
                
                // Check if correct
                $is_correct = (
                    count($correct_answers) === count($user_answers) &&
                    empty(array_diff($correct_answers, $user_answers))
                );
                
                if ($is_correct) {
                    $total_score += $question->points;
                }
                
                $results[$question_id] = array(
                    'question_id' => $question_id,
                    'question_text' => $question->question_text,
                    'user_answers' => $user_answers,
                    'correct_answers' => $correct_answers,
                    'is_correct' => $is_correct,
                    'points_earned' => $is_correct ? $question->points : 0,
                    'max_points' => $question->points,
                    'explanation' => $question->explanation
                );
            }
            
            // Calculate summary
            $max_possible_score = array_sum(array_column($results, 'max_points'));
            $correct_count = count(array_filter($results, function($r) { return $r['is_correct']; }));
            $percentage = $max_possible_score > 0 ? round(($total_score / $max_possible_score) * 100, 2) : 0;
            
            wp_send_json_success(array(
                'results' => $results,
                'summary' => array(
                    'total_questions' => $total_questions,
                    'correct_answers' => $correct_count,
                    'incorrect_answers' => $total_questions - $correct_count,
                    'total_score' => $total_score,
                    'max_possible_score' => $max_possible_score,
                    'percentage' => $percentage
                )
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to validate answers: ' . $e->getMessage());
        }
    }
    
    /**
     * Shortcode to render a single question
     */
    public function render_single_question_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'show_explanation' => false,
            'show_correct' => false,
            'interactive' => true
        ), $atts);
        
        $question_id = intval($atts['id']);
        if (!$question_id) {
            return '<div class="vefify-error">Question ID is required</div>';
        }
        
        $question = $this->model->get_question($question_id);
        if (!$question) {
            return '<div class="vefify-error">Question not found</div>';
        }
        
        ob_start();
        ?>
        <div class="vefify-single-question" data-question-id="<?php echo $question->id; ?>">
            <div class="question-header">
                <h3 class="question-text"><?php echo esc_html($question->question_text); ?></h3>
                <div class="question-meta">
                    <span class="category"><?php echo esc_html(ucfirst($question->category ?: 'General')); ?></span>
                    <span class="difficulty difficulty-<?php echo esc_attr($question->difficulty); ?>">
                        <?php echo esc_html(ucfirst($question->difficulty)); ?>
                    </span>
                    <span class="points"><?php echo $question->points; ?> point<?php echo $question->points !== 1 ? 's' : ''; ?></span>
                </div>
            </div>
            
            <div class="question-options">
                <?php foreach ($question->options as $index => $option): ?>
                    <div class="option-item <?php echo $atts['show_correct'] && $option->is_correct ? 'correct' : ''; ?>">
                        <?php if ($atts['interactive']): ?>
                            <label>
                                <input type="<?php echo $question->question_type === 'multiple_select' ? 'checkbox' : 'radio'; ?>" 
                                       name="question_<?php echo $question->id; ?>" 
                                       value="<?php echo $option->id; ?>"
                                       <?php echo $atts['show_correct'] && $option->is_correct ? 'checked' : ''; ?>>
                                <span class="option-text"><?php echo esc_html($option->option_text); ?></span>
                            </label>
                        <?php else: ?>
                            <span class="option-marker"><?php echo chr(65 + $index); ?>.</span>
                            <span class="option-text"><?php echo esc_html($option->option_text); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($atts['show_explanation'] && $question->explanation): ?>
                <div class="question-explanation">
                    <h4>Explanation:</h4>
                    <p><?php echo esc_html($question->explanation); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['interactive']): ?>
                <div class="question-actions">
                    <button type="button" class="vefify-submit-answer button button-primary">
                        Submit Answer
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get model instance
     */
    public function get_model() {
        return $this->model;
    }
    
    /**
     * Get bank instance (admin only)
     */
    public function get_bank() {
        return $this->bank;
    }
    
    /**
     * Public API: Get questions for a campaign
     */
    public function get_campaign_questions($campaign_id, $count = 5, $options = array()) {
        $defaults = array(
            'difficulty' => '',
            'category' => '',
            'randomize' => true,
            'include_correct' => false
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $args = array(
            'campaign_id' => $campaign_id,
            'is_active' => 1,
            'per_page' => $options['randomize'] ? $count * 2 : $count,
            'page' => 1
        );
        
        if ($options['difficulty']) {
            $args['difficulty'] = $options['difficulty'];
        }
        
        if ($options['category']) {
            $args['category'] = $options['category'];
        }
        
        $result = $this->model->get_questions($args);
        $questions = $result['questions'];
        
        // Randomize if requested
        if ($options['randomize']) {
            shuffle($questions);
            $questions = array_slice($questions, 0, $count);
        }
        
        // Format questions
        $formatted_questions = array();
        foreach ($questions as $question) {
            $formatted_question = array(
                'id' => $question->id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'category' => $question->category,
                'difficulty' => $question->difficulty,
                'points' => $question->points,
                'explanation' => $question->explanation,
                'options' => array()
            );
            
            // Add options
            foreach ($question->options as $option) {
                $option_data = array(
                    'id' => $option->id,
                    'text' => $option->option_text,
                    'order_index' => $option->order_index
                );
                
                if ($options['include_correct']) {
                    $option_data['is_correct'] = (bool) $option->is_correct;
                }
                
                $formatted_question['options'][] = $option_data;
            }
            
            $formatted_questions[] = $formatted_question;
        }
        
        return $formatted_questions;
    }
}