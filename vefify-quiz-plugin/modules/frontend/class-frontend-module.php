<?php
/**
 * Frontend Module Class
 * File: modules/frontend/class-frontend-module.php
 * 
 * Handles frontend enhancements, REST API endpoints, and public-facing functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Frontend_Module {
    
    private $plugin;
    private $database;
    
    public function __construct() {
        $this->plugin = vefify_quiz();
        $this->database = $this->plugin->get_database();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // AJAX handlers for logged-out users
        add_action('wp_ajax_nopriv_vefify_get_leaderboard', array($this, 'ajax_get_leaderboard'));
        add_action('wp_ajax_vefify_get_leaderboard', array($this, 'ajax_get_leaderboard'));
        
        // Frontend head enhancements
        add_action('wp_head', array($this, 'add_frontend_meta'));
        
        // Body class for quiz pages
        add_filter('body_class', array($this, 'add_body_classes'));
        
        // Social sharing meta tags
        add_action('wp_head', array($this, 'add_social_meta_tags'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        $namespace = 'vefify/v1';
        
        // Get campaign info
        register_rest_route($namespace, '/campaign/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_campaign'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
        
        // Get quiz questions (authenticated)
        register_rest_route($namespace, '/quiz/(?P<campaign_id>\d+)/questions', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_get_questions'),
            'permission_callback' => array($this, 'verify_quiz_session'),
            'args' => array(
                'campaign_id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
                'session_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // Submit quiz answers
        register_rest_route($namespace, '/quiz/submit', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_submit_quiz'),
            'permission_callback' => array($this, 'verify_quiz_session'),
            'args' => array(
                'session_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'answers' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_array($param);
                    }
                )
            )
        ));
        
        // Get leaderboard
        register_rest_route($namespace, '/leaderboard/(?P<campaign_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_leaderboard'),
            'permission_callback' => '__return_true',
            'args' => array(
                'campaign_id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
                'limit' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        // Get quiz statistics
        register_rest_route($namespace, '/stats/(?P<campaign_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_stats'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * REST: Get campaign information
     */
    public function rest_get_campaign($request) {
        $campaign_id = $request->get_param('id');
        
        if (!$this->database) {
            return new WP_Error('database_error', 'Database not available', array('status' => 500));
        }
        
        try {
            global $wpdb;
            $campaigns_table = $this->database->get_table_name('campaigns');
            
            $campaign = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$campaigns_table} WHERE id = %d AND is_active = 1",
                $campaign_id
            ));
            
            if (!$campaign) {
                return new WP_Error('not_found', 'Campaign not found', array('status' => 404));
            }
            
            // Get additional stats
            $stats = $this->get_campaign_stats($campaign_id);
            
            return rest_ensure_response(array(
                'campaign' => array(
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'description' => $campaign->description,
                    'questions_per_quiz' => $campaign->questions_per_quiz,
                    'time_limit' => $campaign->time_limit,
                    'pass_score' => $campaign->pass_score,
                    'max_participants' => $campaign->max_participants
                ),
                'stats' => $stats
            ));
            
        } catch (Exception $e) {
            return new WP_Error('server_error', 'Internal server error', array('status' => 500));
        }
    }
    
    /**
     * REST: Get quiz questions (requires valid session)
     */
    public function rest_get_questions($request) {
        $campaign_id = $request->get_param('campaign_id');
        $session_id = $request->get_param('session_id');
        
        try {
            global $wpdb;
            $questions_table = $this->database->get_table_name('questions');
            $options_table = $this->database->get_table_name('question_options');
            $participants_table = $this->database->get_table_name('participants');
            
            // Verify session belongs to this campaign
            $participant = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$participants_table} WHERE session_id = %s AND campaign_id = %d AND quiz_status = 'started'",
                $session_id, $campaign_id
            ));
            
            if (!$participant) {
                return new WP_Error('invalid_session', 'Invalid session', array('status' => 403));
            }
            
            // Get campaign info for question limit
            $campaigns_table = $this->database->get_table_name('campaigns');
            $campaign = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$campaigns_table} WHERE id = %d",
                $campaign_id
            ));
            
            $limit = $campaign->questions_per_quiz ?? 5;
            
            // Get questions
            $questions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$questions_table} 
                 WHERE (campaign_id = %d OR campaign_id IS NULL) AND is_active = 1 
                 ORDER BY RAND() 
                 LIMIT %d",
                $campaign_id, $limit
            ));
            
            // Get options for each question (without correct answers)
            foreach ($questions as &$question) {
                $question->options = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, option_text, order_index FROM {$options_table} 
                     WHERE question_id = %d ORDER BY order_index",
                    $question->id
                ));
                
                // Remove sensitive data
                unset($question->explanation);
            }
            
            return rest_ensure_response(array(
                'questions' => $questions,
                'session_info' => array(
                    'time_limit' => $campaign->time_limit,
                    'total_questions' => count($questions)
                )
            ));
            
        } catch (Exception $e) {
            return new WP_Error('server_error', 'Failed to get questions', array('status' => 500));
        }
    }
    
    /**
     * REST: Submit quiz answers
     */
    public function rest_submit_quiz($request) {
        $session_id = $request->get_param('session_id');
        $answers = $request->get_param('answers');
        
        try {
            global $wpdb;
            $participants_table = $this->database->get_table_name('participants');
            
            // Get participant
            $participant = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$participants_table} WHERE session_id = %s AND quiz_status = 'started'",
                $session_id
            ));
            
            if (!$participant) {
                return new WP_Error('invalid_session', 'Invalid session or quiz already completed', array('status' => 403));
            }
            
            // Calculate score
            $score_result = $this->calculate_quiz_score($participant->campaign_id, $answers);
            
            // Update participant record
            $update_result = $wpdb->update(
                $participants_table,
                array(
                    'quiz_status' => 'completed',
                    'score' => $score_result['score'],
                    'total_questions' => $score_result['total_questions'],
                    'completion_percentage' => $score_result['percentage'],
                    'answers_data' => json_encode($answers),
                    'completed_at' => current_time('mysql')
                ),
                array('id' => $participant->id)
            );
            
            if ($update_result === false) {
                return new WP_Error('update_failed', 'Failed to save results', array('status' => 500));
            }
            
            // Check for gifts
            $gift_result = $this->assign_quiz_gift($participant->campaign_id, $participant->id, $score_result['score']);
            
            return rest_ensure_response(array(
                'score' => $score_result['score'],
                'total_questions' => $score_result['total_questions'],
                'percentage' => $score_result['percentage'],
                'gift' => $gift_result,
                'completion_time' => current_time('mysql')
            ));
            
        } catch (Exception $e) {
            return new WP_Error('server_error', 'Submission failed', array('status' => 500));
        }
    }
    
    /**
     * REST: Get leaderboard
     */
    public function rest_get_leaderboard($request) {
        $campaign_id = $request->get_param('campaign_id');
        $limit = $request->get_param('limit');
        
        try {
            global $wpdb;
            $participants_table = $this->database->get_table_name('participants');
            
            $leaderboard = $wpdb->get_results($wpdb->prepare(
                "SELECT score, total_questions, completion_percentage, province, completed_at,
                        CASE 
                            WHEN score = total_questions THEN 'Perfect Score!'
                            WHEN completion_percentage >= 80 THEN 'Excellent'
                            WHEN completion_percentage >= 60 THEN 'Good'
                            ELSE 'Participated'
                        END as performance_level
                 FROM {$participants_table} 
                 WHERE campaign_id = %d AND quiz_status = 'completed'
                 ORDER BY score DESC, completed_at ASC
                 LIMIT %d",
                $campaign_id, $limit
            ));
            
            // Add rank and anonymize
            $ranked_leaderboard = array();
            foreach ($leaderboard as $index => $entry) {
                $ranked_leaderboard[] = array(
                    'rank' => $index + 1,
                    'score' => $entry->score,
                    'total_questions' => $entry->total_questions,
                    'percentage' => $entry->completion_percentage,
                    'province' => $entry->province,
                    'performance_level' => $entry->performance_level,
                    'completed_date' => mysql2date('M j, Y', $entry->completed_at)
                );
            }
            
            return rest_ensure_response($ranked_leaderboard);
            
        } catch (Exception $e) {
            return new WP_Error('server_error', 'Failed to get leaderboard', array('status' => 500));
        }
    }
    
    /**
     * REST: Get campaign statistics
     */
    public function rest_get_stats($request) {
        $campaign_id = $request->get_param('campaign_id');
        
        try {
            $stats = $this->get_campaign_stats($campaign_id);
            return rest_ensure_response($stats);
        } catch (Exception $e) {
            return new WP_Error('server_error', 'Failed to get statistics', array('status' => 500));
        }
    }
    
    /**
     * Verify quiz session for protected endpoints
     */
    public function verify_quiz_session($request) {
        $session_id = $request->get_param('session_id');
        
        if (!$session_id) {
            return false;
        }
        
        if (!$this->database) {
            return false;
        }
        
        global $wpdb;
        $participants_table = $this->database->get_table_name('participants');
        
        $session_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$participants_table} WHERE session_id = %s",
            $session_id
        ));
        
        return $session_exists > 0;
    }
    
    /**
     * Get campaign statistics
     */
    private function get_campaign_stats($campaign_id) {
        global $wpdb;
        
        $participants_table = $this->database->get_table_name('participants');
        $questions_table = $this->database->get_table_name('questions');
        $gifts_table = $this->database->get_table_name('gifts');
        
        return array(
            'total_participants' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$participants_table} WHERE campaign_id = %d",
                $campaign_id
            )) ?: 0,
            
            'completed_participants' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$participants_table} WHERE campaign_id = %d AND quiz_status = 'completed'",
                $campaign_id
            )) ?: 0,
            
            'average_score' => $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(score) FROM {$participants_table} WHERE campaign_id = %d AND quiz_status = 'completed'",
                $campaign_id
            )) ?: 0,
            
            'total_questions' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$questions_table} WHERE (campaign_id = %d OR campaign_id IS NULL) AND is_active = 1",
                $campaign_id
            )) ?: 0,
            
            'available_gifts' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$gifts_table} WHERE campaign_id = %d AND is_active = 1",
                $campaign_id
            )) ?: 0,
            
            'gifts_claimed' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$participants_table} WHERE campaign_id = %d AND gift_id IS NOT NULL",
                $campaign_id
            )) ?: 0
        );
    }
    
    /**
     * Calculate quiz score
     */
    private function calculate_quiz_score($campaign_id, $answers) {
        global $wpdb;
        
        $questions_table = $this->database->get_table_name('questions');
        $options_table = $this->database->get_table_name('question_options');
        
        $score = 0;
        $total_questions = count($answers);
        
        foreach ($answers as $question_id => $user_answers) {
            // Get correct answers for this question
            $correct_answers = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$options_table} WHERE question_id = %d AND is_correct = 1",
                $question_id
            ));
            
            // Ensure arrays for comparison
            $user_answers = is_array($user_answers) ? $user_answers : array($user_answers);
            $user_answers = array_map('intval', $user_answers);
            $correct_answers = array_map('intval', $correct_answers);
            
            // Sort for accurate comparison
            sort($user_answers);
            sort($correct_answers);
            
            // Check if answer is correct
            if ($user_answers === $correct_answers) {
                $score++;
            }
        }
        
        $percentage = $total_questions > 0 ? round(($score / $total_questions) * 100, 2) : 0;
        
        return array(
            'score' => $score,
            'total_questions' => $total_questions,
            'percentage' => $percentage
        );
    }
    
    /**
     * Assign gift based on score
     */
    private function assign_quiz_gift($campaign_id, $participant_id, $score) {
        global $wpdb;
        
        $gifts_table = $this->database->get_table_name('gifts');
        $participants_table = $this->database->get_table_name('participants');
        
        // Find eligible gift
        $gift = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$gifts_table} 
             WHERE campaign_id = %d AND is_active = 1 
             AND min_score <= %d AND (max_score IS NULL OR max_score >= %d)
             AND (max_quantity IS NULL OR used_count < max_quantity)
             ORDER BY min_score DESC, gift_value DESC
             LIMIT 1",
            $campaign_id, $score, $score
        ));
        
        if (!$gift) {
            return array('has_gift' => false, 'message' => 'No gift available for your score');
        }
        
        // Generate gift code
        $gift_code = $this->generate_gift_code($gift->gift_code_prefix ?? 'GIFT');
        
        // Update participant with gift
        $wpdb->update(
            $participants_table,
            array(
                'gift_id' => $gift->id,
                'gift_code' => $gift_code,
                'gift_status' => 'assigned'
            ),
            array('id' => $participant_id)
        );
        
        // Update gift usage
        $wpdb->update(
            $gifts_table,
            array('used_count' => $gift->used_count + 1),
            array('id' => $gift->id)
        );
        
        return array(
            'has_gift' => true,
            'gift_name' => $gift->gift_name,
            'gift_code' => $gift_code,
            'gift_value' => $gift->gift_value,
            'gift_description' => $gift->gift_description,
            'gift_type' => $gift->gift_type
        );
    }
    
    /**
     * Generate unique gift code
     */
    private function generate_gift_code($prefix = 'GIFT', $length = 8) {
        $code = $prefix . strtoupper(wp_generate_password($length, false, false));
        
        // Ensure uniqueness
        global $wpdb;
        $participants_table = $this->database->get_table_name('participants');
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$participants_table} WHERE gift_code = %s",
            $code
        ));
        
        if ($exists) {
            return $this->generate_gift_code($prefix, $length); // Recursive if exists
        }
        
        return $code;
    }
    
    /**
     * AJAX: Get leaderboard for frontend
     */
    public function ajax_get_leaderboard() {
        $campaign_id = intval($_POST['campaign_id'] ?? 0);
        $limit = intval($_POST['limit'] ?? 10);
        
        if (!$campaign_id) {
            wp_send_json_error('Invalid campaign ID');
        }
        
        try {
            $request = new WP_REST_Request('GET', '/vefify/v1/leaderboard/' . $campaign_id);
            $request->set_param('limit', $limit);
            
            $response = $this->rest_get_leaderboard($request);
            
            if (is_wp_error($response)) {
                wp_send_json_error($response->get_error_message());
            }
            
            wp_send_json_success($response->get_data());
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to get leaderboard');
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on pages with quiz shortcode
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vefify_quiz')) {
            // Additional frontend styles
            wp_enqueue_style(
                'vefify-frontend-enhancements',
                VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/frontend-enhancements.css',
                array(),
                VEFIFY_QUIZ_VERSION
            );
            
            // Additional frontend scripts
            wp_enqueue_script(
                'vefify-frontend-enhancements',
                VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/frontend-enhancements.js',
                array('jquery'),
                VEFIFY_QUIZ_VERSION,
                true
            );
            
            // Localize script with REST API info
            wp_localize_script('vefify-frontend-enhancements', 'vefifyAPI', array(
                'restUrl' => rest_url('vefify/v1/'),
                'restNonce' => wp_create_nonce('wp_rest'),
                'currentUrl' => get_permalink(),
                'siteUrl' => home_url()
            ));
        }
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
            echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
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
    
    /**
     * Add social sharing meta tags
     */
    public function add_social_meta_tags() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vefify_quiz')) {
            $title = get_the_title() . ' - Interactive Quiz';
            $description = 'Test your knowledge with our interactive quiz and win amazing prizes!';
            $image = VEFIFY_QUIZ_PLUGIN_URL . 'assets/images/quiz-social-share.jpg';
            $url = get_permalink();
            
            echo '<meta property="og:title" content="' . esc_attr($title) . '">';
            echo '<meta property="og:description" content="' . esc_attr($description) . '">';
            echo '<meta property="og:image" content="' . esc_url($image) . '">';
            echo '<meta property="og:url" content="' . esc_url($url) . '">';
            echo '<meta property="og:type" content="website">';
            
            echo '<meta name="twitter:card" content="summary_large_image">';
            echo '<meta name="twitter:title" content="' . esc_attr($title) . '">';
            echo '<meta name="twitter:description" content="' . esc_attr($description) . '">';
            echo '<meta name="twitter:image" content="' . esc_url($image) . '">';
        }
    }
}

// Initialize the frontend module
new Vefify_Frontend_Module();