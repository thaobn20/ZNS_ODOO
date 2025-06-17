<?php
/**
 * Analytics Module
 * File: modules/analytics/class-analytics-module.php
 * Simple analytics module for menu navigation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Analytics_Module {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize hooks if needed
    }
    
    /**
     * Admin page router
     */
    public function admin_page_router() {
        $action = $_GET['action'] ?? 'dashboard';
        
        switch ($action) {
            case 'campaigns':
                $this->render_campaign_analytics();
                break;
            case 'participants':
                $this->render_participant_analytics();
                break;
            case 'export':
                $this->handle_export();
                break;
            default:
                $this->render_analytics_dashboard();
                break;
        }
    }
    
    /**
     * Render main analytics dashboard
     */
    private function render_analytics_dashboard() {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">📈 Analytics & Reports</h1>';
        echo '<a href="' . add_query_arg('action', 'export') . '" class="page-title-action">Export Data</a>';
        echo '<hr class="wp-header-end">';
        
        // Get basic statistics
        global $wpdb;
        $table_prefix = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX;
        
        $campaigns_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}campaigns") ?: 0;
        $participants_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}participants") ?: 0;
        $questions_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}questions WHERE is_active = 1") ?: 0;
        $completed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}participants WHERE quiz_status = 'completed'") ?: 0;
        
        // Overview cards
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
        
        $stats = array(
            array('title' => 'Total Campaigns', 'value' => $campaigns_count, 'icon' => '📋', 'color' => '#007cba'),
            array('title' => 'Total Participants', 'value' => $participants_count, 'icon' => '👥', 'color' => '#46b450'),
            array('title' => 'Active Questions', 'value' => $questions_count, 'icon' => '❓', 'color' => '#826eb4'),
            array('title' => 'Completed Quizzes', 'value' => $completed_count, 'icon' => '✅', 'color' => '#f56e28')
        );
        
        foreach ($stats as $stat) {
            echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid ' . $stat['color'] . ';">';
            echo '<div style="display: flex; align-items: center;">';
            echo '<div style="font-size: 2em; margin-right: 15px;">' . $stat['icon'] . '</div>';
            echo '<div>';
            echo '<div style="font-size: 2em; font-weight: bold; margin-bottom: 5px;">' . $stat['value'] . '</div>';
            echo '<div style="color: #666; font-size: 0.9em;">' . $stat['title'] . '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Recent activity
        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        echo '<h3>📊 Recent Activity</h3>';
        
        $recent_participants = $wpdb->get_results("
            SELECT p.participant_name, p.final_score, p.quiz_status, p.created_at, 
                   c.name as campaign_name
            FROM {$table_prefix}participants p
            LEFT JOIN {$table_prefix}campaigns c ON p.campaign_id = c.id
            ORDER BY p.created_at DESC
            LIMIT 10
        ");
        
        if ($recent_participants) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Participant</th><th>Campaign</th><th>Status</th><th>Score</th><th>Date</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($recent_participants as $participant) {
                $status_colors = array(
                    'completed' => '#46b450',
                    'in_progress' => '#f56e28', 
                    'started' => '#007cba',
                    'abandoned' => '#dc3232'
                );
                
                $status_color = $status_colors[$participant->quiz_status] ?? '#666';
                
                echo '<tr>';
                echo '<td>' . esc_html($participant->participant_name ?: 'Anonymous') . '</td>';
                echo '<td>' . esc_html($participant->campaign_name ?: 'Unknown') . '</td>';
                echo '<td><span style="color: ' . $status_color . '; font-weight: bold;">' . esc_html(ucfirst($participant->quiz_status)) . '</span></td>';
                echo '<td>' . esc_html($participant->final_score) . '</td>';
                echo '<td>' . esc_html(date('M j, Y H:i', strtotime($participant->created_at))) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>No activity data available yet.</p>';
        }
        
        echo '</div>';
        
        // Quick actions
        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        echo '<h3>🚀 Quick Actions</h3>';
        echo '<a href="' . admin_url('admin.php?page=vefify-analytics&action=campaigns') . '" class="button button-primary">Campaign Analytics</a> ';
        echo '<a href="' . admin_url('admin.php?page=vefify-analytics&action=participants') . '" class="button">Participant Analysis</a> ';
        echo '<a href="' . admin_url('admin.php?page=vefify-analytics&action=export') . '" class="button">Export Data</a>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render campaign analytics
     */
    private function render_campaign_analytics() {
        echo '<div class="wrap">';
        echo '<h1>📋 Campaign Analytics</h1>';
        echo '<p>Detailed campaign performance metrics coming soon...</p>';
        echo '<a href="' . admin_url('admin.php?page=vefify-analytics') . '" class="button">← Back to Analytics</a>';
        echo '</div>';
    }
    
    /**
     * Render participant analytics
     */
    private function render_participant_analytics() {
        echo '<div class="wrap">';
        echo '<h1>👥 Participant Analytics</h1>';
        echo '<p>Detailed participant behavior analysis coming soon...</p>';
        echo '<a href="' . admin_url('admin.php?page=vefify-analytics') . '" class="button">← Back to Analytics</a>';
        echo '</div>';
    }
    
    /**
     * Handle data export
     */
    private function handle_export() {
        echo '<div class="wrap">';
        echo '<h1>📤 Export Data</h1>';
        echo '<p>Data export functionality coming soon...</p>';
        echo '<a href="' . admin_url('admin.php?page=vefify-analytics') . '" class="button">← Back to Analytics</a>';
        echo '</div>';
    }
}