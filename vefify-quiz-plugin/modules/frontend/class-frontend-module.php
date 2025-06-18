<?php
/**
 * FIXED: Frontend Module (No Class Conflicts)
 * File: modules/frontend/class-frontend-module.php
 * 
 * Handles REST API and frontend functionality WITHOUT shortcode class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Frontend_Module {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Frontend meta tags
        add_action('wp_head', array($this, 'add_frontend_meta'));
        
        // Body classes
        add_filter('body_class', array($this, 'add_body_classes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        $namespace = 'vefify/v1';
        
        // Test endpoint
        register_rest_route($namespace, '/test', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_test'),
            'permission_callback' => '__return_true'
        ));
        
        // Check participation endpoint
        register_rest_route($namespace, '/check-participation', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_check_participation'),
            'permission_callback' => '__return_true'
        ));
        
        // Start quiz endpoint
        register_rest_route($namespace, '/start-quiz', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_start_quiz'),
            'permission_callback' => '__return_true'
        ));
        
        // Submit quiz endpoint
        register_rest_route($namespace, '/submit-quiz', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_submit_quiz'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * REST: Test endpoint
     */
    public function rest_test($request) {
        return rest_ensure_response(array(
            'status' => 'success',
            'message' => 'Frontend module active',
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * REST: Check participation
     */
    public function rest_check_participation($request) {
        $phone = sanitize_text_field($request->get_param('phone'));
        $campaign_id = intval($request->get_param('campaign_id'));
        
        if (!$phone || !$campaign_id) {
            return new WP_Error('missing_data', 'Phone and campaign ID required', array('status' => 400));
        }
        
        // Simple check for now
        return rest_ensure_response(array(
            'can_participate' => true,
            'message' => 'Participation allowed'
        ));
    }
    
    /**
     * REST: Start quiz
     */
    public function rest_start_quiz($request) {
        $campaign_id = intval($request->get_param('campaign_id'));
        $user_data = $request->get_param('user_data');
        
        if (!$campaign_id || !$user_data) {
            return new WP_Error('missing_data', 'Campaign ID and user data required', array('status' => 400));
        }
        
        // Generate session
        $session_id = 'vq_' . uniqid() . '_' . wp_generate_password(8, false);
        
        return rest_ensure_response(array(
            'success' => true,
            'session_id' => $session_id,
            'questions' => $this->get_sample_questions()
        ));
    }
    
    /**
     * REST: Submit quiz
     */
    public function rest_submit_quiz($request) {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $answers = $request->get_param('answers');
        
        if (!$session_id || !is_array($answers)) {
            return new WP_Error('missing_data', 'Session ID and answers required', array('status' => 400));
        }
        
        // Calculate basic score
        $score = count($answers);
        $total = 5;
        
        return rest_ensure_response(array(
            'success' => true,
            'score' => $score,
            'total_questions' => $total,
            'percentage' => round(($score / $total) * 100),
            'gift' => array('has_gift' => false)
        ));
    }
    
    /**
     * Get sample questions
     */
    private function get_sample_questions() {
        return array(
            array(
                'id' => 1,
                'question_text' => 'What is Aspirin commonly used for?',
                'question_type' => 'multiple_choice',
                'options' => array(
                    array('id' => 1, 'option_text' => 'Pain relief'),
                    array('id' => 2, 'option_text' => 'Fever reduction'),
                    array('id' => 3, 'option_text' => 'Sleep aid'),
                    array('id' => 4, 'option_text' => 'Anxiety treatment')
                )
            ),
            array(
                'id' => 2,
                'question_text' => 'Which vitamin is essential for bone health?',
                'question_type' => 'multiple_choice',
                'options' => array(
                    array('id' => 5, 'option_text' => 'Vitamin A'),
                    array('id' => 6, 'option_text' => 'Vitamin C'),
                    array('id' => 7, 'option_text' => 'Vitamin D'),
                    array('id' => 8, 'option_text' => 'Vitamin E')
                )
            )
        );
    }
    
    /**
     * Add meta tags for quiz pages
     */
    public function add_frontend_meta() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vefify_quiz')) {
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">';
            echo '<meta name="mobile-web-app-capable" content="yes">';
            echo '<meta name="apple-mobile-web-app-capable" content="yes">';
        }
    }
    
    /**
     * Add body classes for quiz pages
     */
    public function add_body_classes($classes) {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vefify_quiz')) {
            $classes[] = 'vefify-quiz-page';
            $classes[] = 'mobile-optimized';
        }
        
        return $classes;
    }
}

// Initialize the frontend module
new Vefify_Frontend_Module();