<?php
/**
 * Questions Module Placeholder/Backup
 * File: modules/questions/class-question-module.php
 * Create this file if your existing questions module isn't loading
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Question_Module {
    
    private static $instance = null;
    private $bank;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize question bank
        $this->bank = $this;
    }
    
    /**
     * Get bank instance for compatibility
     */
    public function get_bank() {
        return $this->bank;
    }
    
    /**
     * Admin page router
     */
    public function admin_page_router() {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'new':
            case 'edit':
                $this->display_question_form();
                break;
            case 'import':
                $this->display_import_page();
                break;
            default:
                $this->display_questions_list();
                break;
        }
    }
    
    /**
     * Display questions list
     */
    private function display_questions_list() {
        global $wpdb;
        $questions_table = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'questions';
        
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">❓ Questions</h1>';
        echo '<a href="' . add_query_arg('action', 'new') . '" class="page-title-action">Add New Question</a>';
        echo '<hr class="wp-header-end">';
        
        // Get questions
        $questions = $wpdb->get_results("
            SELECT q.*, c.name as campaign_name
            FROM {$questions_table} q
            LEFT JOIN {$wpdb->prefix}" . VEFIFY_QUIZ_TABLE_PREFIX . "campaigns c ON q.campaign_id = c.id
            WHERE q.is_active = 1
            ORDER BY q.created_at DESC
            LIMIT 50
        ");
        
        if ($questions) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Question</th>';
            echo '<th>Type</th>';
            echo '<th>Category</th>';
            echo '<th>Difficulty</th>';
            echo '<th>Points</th>';
            echo '<th>Campaign</th>';
            echo '<th>Actions</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($questions as $question) {
                echo '<tr>';
                echo '<td>' . esc_html(wp_trim_words($question->question_text, 8)) . '</td>';
                echo '<td>' . esc_html(ucfirst(str_replace('_', ' ', $question->question_type))) . '</td>';
                echo '<td>' . esc_html($question->category ?: 'None') . '</td>';
                echo '<td>';
                $difficulty_colors = array('easy' => '#46b450', 'medium' => '#f56e28', 'hard' => '#dc3232');
                $color = $difficulty_colors[$question->difficulty] ?? '#666';
                echo '<span style="color: ' . $color . '; font-weight: bold;">' . esc_html(ucfirst($question->difficulty)) . '</span>';
                echo '</td>';
                echo '<td>' . esc_html($question->points) . '</td>';
                echo '<td>' . esc_html($question->campaign_name ?: 'General') . '</td>';
                echo '<td>';
                echo '<a href="' . add_query_arg(array('action' => 'edit', 'id' => $question->id)) . '" class="button button-small">Edit</a> ';
                echo '<a href="' . wp_nonce_url(add_query_arg(array('action' => 'delete', 'id' => $question->id)), 'delete_question_' . $question->id) . '" class="button button-small" onclick="return confirm(\'Delete this question?\')">Delete</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<div class="notice notice-info"><p>No questions found. <a href="' . add_query_arg('action', 'new') . '">Add your first question</a></p></div>';
        }
        
        // Quick stats
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$questions_table} WHERE is_active = 1"),
            'easy' => $wpdb->get_var("SELECT COUNT(*) FROM {$questions_table} WHERE is_active = 1 AND difficulty = 'easy'"),
            'medium' => $wpdb->get_var("SELECT COUNT(*) FROM {$questions_table} WHERE is_active = 1 AND difficulty = 'medium'"),
            'hard' => $wpdb->get_var("SELECT COUNT(*) FROM {$questions_table} WHERE is_active = 1 AND difficulty = 'hard'"),
            'categories' => $wpdb->get_var("SELECT COUNT(DISTINCT category) FROM {$questions_table} WHERE is_active = 1")
        );
        
        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        echo '<h3>📊 Quick Statistics</h3>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">';
        
        echo '<div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 6px;">';
        echo '<div style="font-size: 1.5em; font-weight: bold; color: #007cba;">' . $stats['total'] . '</div>';
        echo '<div style="font-size: 0.85em; color: #666;">Total Questions</div>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 6px;">';
        echo '<div style="font-size: 1.5em; font-weight: bold; color: #46b450;">' . $stats['easy'] . '</div>';
        echo '<div style="font-size: 0.85em; color: #666;">Easy</div>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 6px;">';
        echo '<div style="font-size: 1.5em; font-weight: bold; color: #f56e28;">' . $stats['medium'] . '</div>';
        echo '<div style="font-size: 0.85em; color: #666;">Medium</div>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 6px;">';
        echo '<div style="font-size: 1.5em; font-weight: bold; color: #dc3232;">' . $stats['hard'] . '</div>';
        echo '<div style="font-size: 0.85em; color: #666;">Hard</div>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 6px;">';
        echo '<div style="font-size: 1.5em; font-weight: bold; color: #826eb4;">' . $stats['categories'] . '</div>';
        echo '<div style="font-size: 0.85em; color: #666;">Categories</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Display question form
     */
    private function display_question_form() {
        echo '<div class="wrap">';
        echo '<h1>❓ Add New Question</h1>';
        echo '<p>Question form functionality coming soon...</p>';
        echo '<a href="' . admin_url('admin.php?page=vefify-questions') . '" class="button">← Back to Questions</a>';
        echo '</div>';
    }
    
    /**
     * Display import page
     */
    private function display_import_page() {
        echo '<div class="wrap">';
        echo '<h1>📥 Import Questions</h1>';
        echo '<p>CSV import functionality coming soon...</p>';
        echo '<a href="' . admin_url('admin.php?page=vefify-questions') . '" class="button">← Back to Questions</a>';
        echo '</div>';
    }
}