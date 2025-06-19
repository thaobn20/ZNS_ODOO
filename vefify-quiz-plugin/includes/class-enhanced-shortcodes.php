<?php
/**
 * 🚀 PHASE 1: COMPLETE QUESTION FLOW IMPLEMENTATION
 * File: includes/class-enhanced-shortcodes.php
 * 
 * This enhances your existing shortcode system with complete question flow
 * Drop-in replacement for your current class-shortcodes.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Enhanced_Shortcodes extends Vefify_Quiz_Shortcodes {
    
    /**
     * 🎯 ENHANCED AJAX: START QUIZ - Complete Implementation
     */
    public function ajax_start_quiz() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['vefify_nonce'], 'vefify_quiz_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $participant_id = intval($_POST['participant_id']);
        $session_token = sanitize_text_field($_POST['session_token']);
        
        // Verify participant session
        $participant = $this->verify_participant_session($participant_id, $session_token);
        if (!$participant) {
            wp_send_json_error('Invalid session');
        }
        
        // Get campaign
        $campaign = $this->get_campaign($participant->campaign_id);
        if (!$campaign || !$this->is_campaign_active($campaign)) {
            wp_send_json_error('Campaign not available');
        }
        
        // Get questions for this campaign with enhanced randomization
        $questions = $this->get_enhanced_quiz_questions($participant->campaign_id, $campaign->questions_per_quiz);
        
        if (empty($questions)) {
            wp_send_json_error('No questions available for this campaign');
        }
        
        // Create quiz session for tracking
        $session_id = $this->create_quiz_session($participant_id, $questions);
        
        // Update participant status to started
        $this->wpdb->update(
            $this->database->get_table_name('participants'),
            array(
                'quiz_status' => 'started',
                'quiz_started_at' => current_time('mysql'),
                'quiz_session_id' => $session_id
            ),
            array('id' => $participant_id)
        );
        
        // Prepare questions for frontend (remove correct answers)
        $safe_questions = $this->prepare_questions_for_frontend($questions);
        
        wp_send_json_success(array(
            'questions' => $safe_questions,
            'session_id' => $session_id,
            'time_limit' => intval($campaign->time_limit),
            'total_questions' => count($questions),
            'pass_score' => intval($campaign->pass_score),
            'campaign_name' => $campaign->name
        ));
    }
    
    /**
     * 📝 ENHANCED GET QUIZ QUESTIONS - Better Randomization & Category Distribution
     */
    private function get_enhanced_quiz_questions($campaign_id, $limit) {
        $questions_table = $this->database->get_table_name('questions');
        $options_table = $this->database->get_table_name('question_options');
        
        // Get questions with better distribution across difficulties and categories
        $questions = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT q.*, 
                    COUNT(qo.id) as option_count,
                    q.difficulty,
                    q.category,
                    q.points
             FROM {$questions_table} q
             LEFT JOIN {$options_table} qo ON q.id = qo.question_id
             WHERE q.campaign_id = %d AND q.is_active = 1
             GROUP BY q.id
             HAVING option_count >= 2
             ORDER BY 
                CASE q.difficulty 
                    WHEN 'easy' THEN 1 
                    WHEN 'medium' THEN 2 
                    WHEN 'hard' THEN 3 
                END,
                RAND()
             LIMIT %d",
            $campaign_id, $limit
        ), ARRAY_A);
        
        // Get options for each question with enhanced data
        foreach ($questions as &$question) {
            $options = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, option_text, option_value, is_correct, option_order 
                 FROM {$options_table} 
                 WHERE question_id = %d 
                 ORDER BY option_order, id",
                $question['id']
            ), ARRAY_A);
            
            // Shuffle options to prevent pattern learning
            if (count($options) > 1) {
                // Keep correct answer positions random
                shuffle($options);
            }
            
            $question['options'] = $options;
            $question['question_type'] = $this->determine_question_type($options);
        }
        
        return $questions;
    }
    
    /**
     * 🎮 CREATE QUIZ SESSION - Track individual quiz attempts
     */
    private function create_quiz_session($participant_id, $questions) {
        $sessions_table = $this->database->get_table_name('quiz_sessions');
        $session_id = wp_generate_password(32, false);
        
        $question_ids = array_column($questions, 'id');
        
        $session_data = array(
            'session_id' => $session_id,
            'participant_id' => $participant_id,
            'question_ids' => json_encode($question_ids),
            'started_at' => current_time('mysql'),
            'is_active' => 1
        );
        
        $this->wpdb->insert($sessions_table, $session_data);
        
        return $session_id;
    }
    
    /**
     * 🔒 PREPARE QUESTIONS FOR FRONTEND - Remove sensitive data
     */
    private function prepare_questions_for_frontend($questions) {
        $safe_questions = array();
        
        foreach ($questions as $question) {
            $safe_options = array();
            
            // Remove correct answer flags from options
            foreach ($question['options'] as $option) {
                $safe_options[] = array(
                    'id' => $option['id'],
                    'text' => $option['option_text'],
                    'value' => $option['option_value']
                );
            }
            
            $safe_questions[] = array(
                'id' => $question['id'],
                'text' => $question['question_text'],
                'type' => $question['question_type'],
                'difficulty' => $question['difficulty'],
                'category' => $question['category'],
                'points' => intval($question['points']),
                'options' => $safe_options,
                'explanation' => $question['explanation'] ?? '',
                'time_limit' => intval($question['time_limit']) ?: 30
            );
        }
        
        return $safe_questions;
    }
    
    /**
     * 📊 ENHANCED SUBMIT ANSWER - Real-time validation and progress tracking
     */
    public function ajax_submit_answer() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['vefify_nonce'], 'vefify_quiz_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $participant_id = intval($_POST['participant_id']);
        $session_id = sanitize_text_field($_POST['session_id']);
        $question_id = intval($_POST['question_id']);
        $answer = $_POST['answer']; // Can be array for multiple choice
        $time_spent = intval($_POST['time_spent']); // Time spent on this question
        
        // Validate session
        $session = $this->get_quiz_session($session_id);
        if (!$session || $session['participant_id'] != $participant_id) {
            wp_send_json_error('Invalid session');
        }
        
        // Store answer with timing data
        $answer_data = array(
            'session_id' => $session_id,
            'participant_id' => $participant_id,
            'question_id' => $question_id,
            'answer_data' => is_array($answer) ? json_encode($answer) : $answer,
            'time_spent' => $time_spent,
            'answered_at' => current_time('mysql')
        );
        
        $answers_table = $this->database->get_table_name('quiz_answers');
        
        // Check if answer already exists (allow updates)
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$answers_table} 
             WHERE session_id = %s AND question_id = %d",
            $session_id, $question_id
        ));
        
        if ($existing) {
            // Update existing answer
            $this->wpdb->update(
                $answers_table,
                $answer_data,
                array('id' => $existing)
            );
        } else {
            // Insert new answer
            $this->wpdb->insert($answers_table, $answer_data);
        }
        
        // Get progress information
        $total_questions = count(json_decode($session['question_ids'], true));
        $answered_count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$answers_table} WHERE session_id = %s",
            $session_id
        ));
        
        wp_send_json_success(array(
            'message' => 'Answer saved successfully',
            'question_id' => $question_id,
            'progress' => array(
                'answered' => intval($answered_count),
                'total' => $total_questions,
                'percentage' => round(($answered_count / $total_questions) * 100, 1)
            )
        ));
    }
    
    /**
     * 🏁 ENHANCED FINISH QUIZ - Complete scoring with detailed analytics
     */
    public function ajax_finish_quiz() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['vefify_nonce'], 'vefify_quiz_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $participant_id = intval($_POST['participant_id']);
        $session_token = sanitize_text_field($_POST['session_token']);
        $session_id = sanitize_text_field($_POST['session_id']);
        
        // Verify participant session
        $participant = $this->verify_participant_session($participant_id, $session_token);
        if (!$participant) {
            wp_send_json_error('Invalid session');
        }
        
        // Get quiz session
        $session = $this->get_quiz_session($session_id);
        if (!$session) {
            wp_send_json_error('Quiz session not found');
        }
        
        // Calculate comprehensive score
        $score_result = $this->calculate_enhanced_quiz_score($session_id, $participant->campaign_id);
        
        // Update participant with final results
        $completion_data = array(
            'quiz_status' => 'completed',
            'final_score' => $score_result['score'],
            'total_questions' => $score_result['total'],
            'correct_answers' => $score_result['correct'],
            'percentage_score' => $score_result['percentage'],
            'time_taken' => $score_result['total_time'],
            'quiz_completed_at' => current_time('mysql')
        );
        
        $this->wpdb->update(
            $this->database->get_table_name('participants'),
            $completion_data,
            array('id' => $participant_id)
        );
        
        // Mark session as completed
        $this->wpdb->update(
            $this->database->get_table_name('quiz_sessions'),
            array('completed_at' => current_time('mysql'), 'is_active' => 0),
            array('session_id' => $session_id)
        );
        
        // Check for gifts
        $gift_result = $this->check_and_assign_gift($participant_id, $score_result['score'], $score_result['percentage']);
        
        // Store analytics data
        $this->store_quiz_analytics($participant_id, $session_id, $score_result);
        
        wp_send_json_success(array(
            'score' => $score_result['score'],
            'total' => $score_result['total'],
            'correct' => $score_result['correct'],
            'percentage' => $score_result['percentage'],
            'passed' => $score_result['passed'],
            'time_taken' => $score_result['total_time'],
            'detailed_results' => $score_result['question_details'],
            'gift' => $gift_result,
            'certificate_eligible' => $score_result['passed'],
            'message' => 'Quiz completed successfully!'
        ));
    }
    
    /**
     * 📊 ENHANCED QUIZ SCORING - Detailed analysis with question breakdown
     */
    private function calculate_enhanced_quiz_score($session_id, $campaign_id) {
        $session = $this->get_quiz_session($session_id);
        $question_ids = json_decode($session['question_ids'], true);
        
        $answers_table = $this->database->get_table_name('quiz_answers');
        $questions_table = $this->database->get_table_name('questions');
        $options_table = $this->database->get_table_name('question_options');
        
        // Get all answers for this session
        $answers = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT question_id, answer_data, time_spent 
             FROM {$answers_table} 
             WHERE session_id = %s",
            $session_id
        ), ARRAY_A);
        
        $answer_map = array();
        $total_time = 0;
        
        foreach ($answers as $answer) {
            $answer_map[$answer['question_id']] = json_decode($answer['answer_data'], true);
            $total_time += intval($answer['time_spent']);
        }
        
        $score = 0;
        $total_points = 0;
        $question_details = array();
        
        foreach ($question_ids as $question_id) {
            // Get question data
            $question = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT id, question_text, points, difficulty, correct_explanation 
                 FROM {$questions_table} 
                 WHERE id = %d",
                $question_id
            ), ARRAY_A);
            
            if (!$question) continue;
            
            $points = intval($question['points']) ?: 1;
            $total_points += $points;
            
            // Get correct answers
            $correct_options = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, option_text FROM {$options_table} 
                 WHERE question_id = %d AND is_correct = 1",
                $question_id
            ), ARRAY_A);
            
            $correct_ids = array_column($correct_options, 'id');
            $user_answer = $answer_map[$question_id] ?? array();
            
            if (!is_array($user_answer)) {
                $user_answer = array($user_answer);
            }
            
            // Check if answer is correct
            $is_correct = $this->compare_answers($correct_ids, $user_answer);
            
            if ($is_correct) {
                $score += $points;
            }
            
            $question_details[] = array(
                'question_id' => $question_id,
                'question_text' => $question['question_text'],
                'user_answer' => $user_answer,
                'correct_answer' => $correct_ids,
                'is_correct' => $is_correct,
                'points_earned' => $is_correct ? $points : 0,
                'max_points' => $points,
                'difficulty' => $question['difficulty'],
                'explanation' => $question['correct_explanation']
            );
        }
        
        $total_questions = count($question_ids);
        $correct_count = array_sum(array_column($question_details, 'is_correct'));
        $percentage = $total_points > 0 ? round(($score / $total_points) * 100, 1) : 0;
        
        // Get campaign pass score
        $campaign = $this->get_campaign($campaign_id);
        $pass_score = $campaign ? intval($campaign->pass_score) : 3;
        
        return array(
            'score' => $score,
            'total' => $total_questions,
            'correct' => $correct_count,
            'total_points' => $total_points,
            'percentage' => $percentage,
            'passed' => $correct_count >= $pass_score,
            'total_time' => $total_time,
            'question_details' => $question_details
        );
    }
    
    /**
     * 🔄 COMPARE ANSWERS - Handle different question types
     */
    private function compare_answers($correct_ids, $user_answers) {
        // Convert to integers for comparison
        $correct_ids = array_map('intval', $correct_ids);
        $user_answers = array_map('intval', $user_answers);
        
        // Sort both arrays for comparison
        sort($correct_ids);
        sort($user_answers);
        
        return $correct_ids === $user_answers;
    }
    
    /**
     * 🎯 DETERMINE QUESTION TYPE
     */
    private function determine_question_type($options) {
        $correct_count = 0;
        foreach ($options as $option) {
            if ($option['is_correct']) {
                $correct_count++;
            }
        }
        
        if ($correct_count > 1) {
            return 'multiple_select';
        } elseif (count($options) == 2) {
            return 'true_false';
        } else {
            return 'single_choice';
        }
    }
    
    /**
     * 🎁 ENHANCED GIFT ASSIGNMENT - Score and percentage based
     */
    private function check_and_assign_gift($participant_id, $score, $percentage) {
        $gifts_table = $this->database->get_table_name('gifts');
        $participants_table = $this->database->get_table_name('participants');
        
        // Get participant data
        $participant = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT campaign_id FROM {$participants_table} WHERE id = %d",
            $participant_id
        ));
        
        if (!$participant) {
            return null;
        }
        
        // Find eligible gifts based on score and percentage
        $eligible_gifts = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$gifts_table} 
             WHERE campaign_id = %d 
             AND is_active = 1 
             AND (min_score <= %d AND (max_score >= %d OR max_score = 0))
             AND (min_percentage <= %f AND (max_percentage >= %f OR max_percentage = 0))
             AND (max_quantity > current_quantity OR max_quantity = 0)
             ORDER BY min_score DESC, min_percentage DESC 
             LIMIT 1",
            $participant->campaign_id, $score, $score, $percentage, $percentage
        ));
        
        if (empty($eligible_gifts)) {
            return null;
        }
        
        $gift = $eligible_gifts[0];
        
        // Generate unique gift code
        $gift_code = $this->generate_gift_code($gift->gift_code_prefix);
        
        // Update participant with gift
        $this->wpdb->update(
            $participants_table,
            array(
                'gift_id' => $gift->id,
                'gift_code' => $gift_code,
                'gift_status' => 'assigned',
                'gift_assigned_at' => current_time('mysql')
            ),
            array('id' => $participant_id)
        );
        
        // Update gift quantity
        $this->wpdb->update(
            $gifts_table,
            array('current_quantity' => $gift->current_quantity + 1),
            array('id' => $gift->id)
        );
        
        return array(
            'gift_id' => $gift->id,
            'gift_name' => $gift->gift_name,
            'gift_type' => $gift->gift_type,
            'gift_value' => $gift->gift_value,
            'gift_code' => $gift_code,
            'description' => $gift->description
        );
    }
    
    /**
     * 🎫 GENERATE GIFT CODE
     */
    private function generate_gift_code($prefix = 'GIFT') {
        $timestamp = date('Ymd');
        $random = strtoupper(wp_generate_password(6, false));
        return $prefix . '-' . $timestamp . '-' . $random;
    }
    
    /**
     * 📈 STORE QUIZ ANALYTICS
     */
    private function store_quiz_analytics($participant_id, $session_id, $score_result) {
        $analytics_table = $this->database->get_table_name('quiz_analytics');
        
        $analytics_data = array(
            'participant_id' => $participant_id,
            'session_id' => $session_id,
            'final_score' => $score_result['score'],
            'total_questions' => $score_result['total'],
            'correct_answers' => $score_result['correct'],
            'percentage_score' => $score_result['percentage'],
            'time_taken' => $score_result['total_time'],
            'difficulty_breakdown' => json_encode($this->analyze_difficulty_performance($score_result['question_details'])),
            'created_at' => current_time('mysql')
        );
        
        $this->wpdb->insert($analytics_table, $analytics_data);
    }
    
    /**
     * 📊 ANALYZE DIFFICULTY PERFORMANCE
     */
    private function analyze_difficulty_performance($question_details) {
        $difficulty_stats = array('easy' => 0, 'medium' => 0, 'hard' => 0);
        $difficulty_totals = array('easy' => 0, 'medium' => 0, 'hard' => 0);
        
        foreach ($question_details as $detail) {
            $difficulty = $detail['difficulty'];
            $difficulty_totals[$difficulty]++;
            if ($detail['is_correct']) {
                $difficulty_stats[$difficulty]++;
            }
        }
        
        // Calculate percentages
        foreach ($difficulty_stats as $difficulty => $correct) {
            $total = $difficulty_totals[$difficulty];
            $difficulty_stats[$difficulty] = $total > 0 ? round(($correct / $total) * 100, 1) : 0;
        }
        
        return $difficulty_stats;
    }
    
    /**
     * 🔍 GET QUIZ SESSION
     */
    private function get_quiz_session($session_id) {
        $sessions_table = $this->database->get_table_name('quiz_sessions');
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$sessions_table} WHERE session_id = %s",
            $session_id
        ), ARRAY_A);
    }
    
    /**
     * 📊 NEW AJAX: GET QUIZ PROGRESS
     */
    public function ajax_get_quiz_progress() {
        if (!wp_verify_nonce($_POST['vefify_nonce'], 'vefify_quiz_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $session_id = sanitize_text_field($_POST['session_id']);
        $session = $this->get_quiz_session($session_id);
        
        if (!$session) {
            wp_send_json_error('Session not found');
        }
        
        $answers_table = $this->database->get_table_name('quiz_answers');
        $question_ids = json_decode($session['question_ids'], true);
        
        $answered_count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$answers_table} WHERE session_id = %s",
            $session_id
        ));
        
        wp_send_json_success(array(
            'answered' => intval($answered_count),
            'total' => count($question_ids),
            'percentage' => round(($answered_count / count($question_ids)) * 100, 1)
        ));
    }
}

// Initialize the enhanced shortcode system
if (class_exists('Vefify_Quiz_Shortcodes')) {
    new Vefify_Enhanced_Shortcodes();
}