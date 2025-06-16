<?php
/**
 * Question Module Loader
 * File: modules/questions/class-question-module.php
 * 
 * Main class that loads and initializes all question-related functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Module {
    
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
        
        // Load core components
        require_once $module_path . 'class-question-model.php';
        require_once $module_path . 'class-question-bank.php';
        require_once $module_path . 'question-endpoints.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize model (data handling)
        $this->model = new Vefify_Question_Model();
        
        // Initialize admin interface (only in admin)
        if (is_admin()) {
            $this->bank = new Vefify_Question_Bank();
        }
        
        // Initialize REST API endpoints
        $this->endpoints = new Vefify_Question_Endpoints();
        
        // Hook into WordPress
        $this->init_hooks();
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
     * Register legacy functions for backward compatibility
     */
    public function register_legacy_functions() {
        // These functions maintain compatibility with existing code
        if (!function_exists('vefify_get_question')) {
            function vefify_get_question($question_id) {
                $module = Vefify_Question_Module::get_instance();
                return $module->get_model()->get_question($question_id);
            }
        }
        
        if (!function_exists('vefify_create_question')) {
            function vefify_create_question($data) {
                $module = Vefify_Question_Module::get_instance();
                return $module->get_model()->create_question($data);
            }
        }
        
        if (!function_exists('vefify_update_question')) {
            function vefify_update_question($question_id, $data) {
                $module = Vefify_Question_Module::get_instance();
                return $module->get_model()->update_question($question_id, $data);
            }
        }
        
        if (!function_exists('vefify_delete_question')) {
            function vefify_delete_question($question_id) {
                $module = Vefify_Question_Module::get_instance();
                return $module->get_model()->delete_question($question_id);
            }
        }
        
        if (!function_exists('vefify_get_questions')) {
            function vefify_get_questions($args = array()) {
                $module = Vefify_Question_Module::get_instance();
                return $module->get_model()->get_questions($args);
            }
        }
        
        if (!function_exists('vefify_validate_question_data')) {
            function vefify_validate_question_data($data) {
                $module = Vefify_Question_Module::get_instance();
                return $module->get_model()->validate_question_data($data);
            }
        }
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
            
            // Localize script with API endpoints
            wp_localize_script('vefify-question-frontend', 'vefifyQuestions', array(
                'restUrl' => rest_url('vefify/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'strings' => array(
                    'loading' => __('Loading questions...', 'vefify-quiz'),
                    'error' => __('An error occurred. Please try again.', 'vefify-quiz'),
                    'submit' => __('Submit Answer', 'vefify-quiz'),
                    'next' => __('Next Question', 'vefify-quiz'),
                    'previous' => __('Previous Question', 'vefify-quiz'),
                    'finish' => __('Finish Quiz', 'vefify-quiz')
                )
            ));
        }
    }
    
    /**
     * AJAX handler to get quiz questions
     */
    public function ajax_get_quiz_questions() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wp_rest')) {
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
            // Use the model to get questions
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
            
            // Format for frontend (remove sensitive data)
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
                
                // Get options without correct answers
                global $wpdb;
                $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
                $options = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, option_text, order_index 
                     FROM {$table_prefix}question_options 
                     WHERE question_id = %d 
                     ORDER BY order_index",
                    $question->id
                ));
                
                foreach ($options as $option) {
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
     * AJAX handler to validate quiz answers
     */
    public function ajax_validate_quiz_answers() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wp_rest')) {
            wp_send_json_error('Security check failed');
        }
        
        $answers = $_POST['answers'] ?? array();
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
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
                    'user_answers' => $user_answers,
                    'correct_answers' => $correct_answers,
                    'is_correct' => $is_correct,
                    'points_earned' => $is_correct ? $question->points : 0,
                    'max_points' => $question->points,
                    'explanation' => $question->explanation
                );
            }
            
            // Calculate percentage
            $max_possible_score = array_sum(array_column($results, 'max_points'));
            $percentage = $max_possible_score > 0 ? round(($total_score / $max_possible_score) * 100, 2) : 0;
            
            wp_send_json_success(array(
                'results' => $results,
                'summary' => array(
                    'total_questions' => $total_questions,
                    'correct_answers' => count(array_filter($results, function($r) { return $r['is_correct']; })),
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
                        
                        <?php if ($atts['show_correct'] && $option->explanation): ?>
                            <div class="option-explanation"><?php echo esc_html($option->explanation); ?></div>
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
     * Get endpoints instance
     */
    public function get_endpoints() {
        return $this->endpoints;
    }
    
    /**
     * Utility method to get questions for a campaign
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
            
            // Get options
            global $wpdb;
            $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
            $options_query = "
                SELECT id, option_text, order_index" . 
                ($options['include_correct'] ? ", is_correct" : "") . "
                FROM {$table_prefix}question_options 
                WHERE question_id = %d 
                ORDER BY order_index
            ";
            
            $question_options = $wpdb->get_results($wpdb->prepare($options_query, $question->id));
            
            foreach ($question_options as $option) {
                $option_data = array(
                    'id' => $option->id,
                    'text' => $option->option_text,
                    'order_index' => $option->order_index
                );
                
                if ($options['include_correct'] && isset($option->is_correct)) {
                    $option_data['is_correct'] = (bool) $option->is_correct;
                }
                
                $formatted_question['options'][] = $option_data;
            }
            
            $formatted_questions[] = $formatted_question;
        }
        
        return $formatted_questions;
    }
    
    /**
     * Validate answers and return detailed results
     */
    public function validate_answers($answers, $include_explanations = true) {
        if (!is_array($answers) || empty($answers)) {
            return new WP_Error('invalid_answers', 'Invalid answers format');
        }
        
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
            
            $result = array(
                'question_id' => $question_id,
                'question_text' => $question->question_text,
                'user_answers' => $user_answers,
                'correct_answers' => $correct_answers,
                'is_correct' => $is_correct,
                'points_earned' => $is_correct ? $question->points : 0,
                'max_points' => $question->points
            );
            
            if ($include_explanations && $question->explanation) {
                $result['explanation'] = $question->explanation;
            }
            
            $results[$question_id] = $result;
        }
        
        // Calculate summary statistics
        $max_possible_score = array_sum(array_column($results, 'max_points'));
        $correct_count = count(array_filter($results, function($r) { return $r['is_correct']; }));
        $percentage = $max_possible_score > 0 ? round(($total_score / $max_possible_score) * 100, 2) : 0;
        
        return array(
            'results' => $results,
            'summary' => array(
                'total_questions' => $total_questions,
                'correct_answers' => $correct_count,
                'incorrect_answers' => $total_questions - $correct_count,
                'total_score' => $total_score,
                'max_possible_score' => $max_possible_score,
                'percentage' => $percentage,
                'grade' => $this->calculate_grade($percentage)
            )
        );
    }
    
    /**
     * Calculate grade based on percentage
     */
    private function calculate_grade($percentage) {
        if ($percentage >= 90) return 'A';
        if ($percentage >= 80) return 'B';
        if ($percentage >= 70) return 'C';
        if ($percentage >= 60) return 'D';
        return 'F';
    }
	public function get_module_analytics() {
        global $wpdb;
        $questions_table = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'questions';
        
        $total_questions = $wpdb->get_var("SELECT COUNT(*) FROM {$questions_table} WHERE is_active = 1");
        
        return array(
            'title' => 'Question Bank',
            'description' => 'Manage questions with multiple types and HTML support',
            'icon' => '❓',
            'stats' => array(
                'total_questions' => array(
                    'label' => 'Active Questions',
                    'value' => $total_questions,
                    'trend' => '+8 added this week'
                )
                // Add more stats...
            ),
            'quick_actions' => array(
                array(
                    'label' => 'Add Question',
                    'url' => admin_url('admin.php?page=vefify-questions&action=new'),
                    'class' => 'button-primary'
                )
            )
        );
    }
}