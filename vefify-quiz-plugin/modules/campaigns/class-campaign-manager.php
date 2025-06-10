<?php
/**
 * Campaign Manager Module
 * File: modules/campaigns/class-campaign-manager.php
 */

namespace VefifyQuiz;

class CampaignManager {
    private $db;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }
    
    /**
     * Get all active campaigns
     */
    public function get_campaigns($request = null) {
        $table = $this->db->prefix . 'vefify_campaigns';
        
        $where = "WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()";
        
        $campaigns = $this->db->get_results("SELECT * FROM {$table} {$where} ORDER BY created_at DESC");
        
        return rest_ensure_response([
            'success' => true,
            'data' => $campaigns
        ]);
    }
    
    /**
     * Get single campaign with questions and gifts
     */
    public function get_campaign($request) {
        $campaign_id = $request['id'] ?? $request->get_param('id');
        
        if (!$campaign_id) {
            return new \WP_Error('missing_id', 'Campaign ID is required', ['status' => 400]);
        }
        
        $campaign = $this->get_campaign_data($campaign_id);
        
        if (!$campaign) {
            return new \WP_Error('not_found', 'Campaign not found', ['status' => 404]);
        }
        
        // Get campaign questions
        $questions = $this->get_campaign_questions($campaign_id);
        
        // Get campaign gifts
        $gifts = $this->get_campaign_gifts($campaign_id);
        
        return rest_ensure_response([
            'success' => true,
            'data' => [
                'campaign' => $campaign,
                'questions' => $questions,
                'gifts' => $gifts
            ]
        ]);
    }
    
    /**
     * Get campaign basic data
     */
    public function get_campaign_data($campaign_id) {
        $table = $this->db->prefix . 'vefify_campaigns';
        
        return $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND is_active = 1",
            $campaign_id
        ), ARRAY_A);
    }
    
    /**
     * Get campaign questions with options
     */
    public function get_campaign_questions($campaign_id, $limit = null) {
        $questions_table = $this->db->prefix . 'vefify_questions';
        $options_table = $this->db->prefix . 'vefify_question_options';
        
        $limit_clause = $limit ? "LIMIT " . intval($limit) : '';
        
        $questions = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$questions_table} 
             WHERE campaign_id = %d AND is_active = 1 
             ORDER BY order_index ASC, RAND() {$limit_clause}",
            $campaign_id
        ), ARRAY_A);
        
        foreach ($questions as &$question) {
            $question['options'] = $this->db->get_results($this->db->prepare(
                "SELECT * FROM {$options_table} 
                 WHERE question_id = %d 
                 ORDER BY order_index ASC",
                $question['id']
            ), ARRAY_A);
        }
        
        return $questions;
    }
    
    /**
     * Get campaign gifts
     */
    public function get_campaign_gifts($campaign_id) {
        $table = $this->db->prefix . 'vefify_gifts';
        
        return $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$table} 
             WHERE campaign_id = %d AND is_active = 1 
             ORDER BY min_score ASC",
            $campaign_id
        ), ARRAY_A);
    }
    
    /**
     * Create new campaign
     */
    public function create_campaign($data) {
        $table = $this->db->prefix . 'vefify_campaigns';
        
        $result = $this->db->insert($table, [
            'name' => sanitize_text_field($data['name']),
            'slug' => sanitize_title($data['slug']),
            'description' => sanitize_textarea_field($data['description']),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'max_participants' => intval($data['max_participants']),
            'questions_per_quiz' => intval($data['questions_per_quiz']),
            'pass_score' => intval($data['pass_score']),
            'meta_data' => json_encode($data['meta_data'] ?? [])
        ]);
        
        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to create campaign');
        }
        
        return $this->db->insert_id;
    }
}