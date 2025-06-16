<?php
/**
 * Participant Module - FIXED VERSION
 * File: modules/participants/class-participant-module.php
 * 
 * Handles participant management and results
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Participant_Module {
    
    private static $instance = null;
    private $database;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->database = new Vefify_Quiz_Database();
        $this->init();
    }
    
    private function init() {
        // AJAX handlers
        add_action('wp_ajax_vefify_export_participants', array($this, 'ajax_export_participants'));
        add_action('wp_ajax_vefify_participant_details', array($this, 'ajax_participant_details'));
    }
    
    /**
     * Admin page router
     */
    public function admin_page_router() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'view':
                $this->render_participant_details();
                break;
            case 'export':
                $this->handle_export();
                break;
            default:
                $this->render_participants_list();
                break;
        }
    }
    
    /**
     * Render participants list
     */
    private function render_participants_list() {
        // Get filter parameters
        $campaign_filter = $_GET['campaign_id'] ?? '';
        $province_filter = $_GET['province'] ?? '';
        $status_filter = $_GET['status'] ?? '';
        $date_filter = $_GET['date_range'] ?? '';
        
        // Build query conditions
        $where_conditions = array('1=1');
        $params = array();
        
        if ($campaign_filter) {
            $where_conditions[] = 'p.campaign_id = %d';
            $params[] = $campaign_filter;
        }
        
        if ($province_filter) {
            $where_conditions[] = 'p.province = %s';
            $params[] = $province_filter;
        }
        
        if ($status_filter === 'completed') {
            $where_conditions[] = 'p.completed_at IS NOT NULL';
        } elseif ($status_filter === 'incomplete') {
            $where_conditions[] = 'p.completed_at IS NULL';
        }
        
        if ($date_filter === 'today') {
            $where_conditions[] = 'DATE(p.created_at) = CURDATE()';
        } elseif ($date_filter === 'week') {
            $where_conditions[] = 'p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get participants
        $participants_table = $this->database->get_table_name('participants');
        $campaigns_table = $this->database->get_table_name('campaigns');
        $gifts_table = $this->database->get_table_name('gifts');
        
        $participants = $this->database->get_results("
            SELECT p.*, c.name as campaign_name, g.gift_name, g.gift_value
            FROM {$participants_table} p
            JOIN {$campaigns_table} c ON p.campaign_id = c.id
            LEFT JOIN {$gifts_table} g ON p.gift_id = g.id
            WHERE {$where_clause}
            ORDER BY p.created_at DESC
            LIMIT 50
        ", $params);
        
        // Get summary stats
        $summary = $this->database->get_results("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed,
                COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as with_gifts,
                AVG(CASE WHEN score > 0 THEN score END) as avg_score
            FROM {$participants_table} p
            WHERE {$where_clause}
        ", $params);
        
        $summary = $summary[0] ?? (object)array('total' => 0, 'completed' => 0, 'with_gifts' => 0, 'avg_score' => 0);
        
        // Get filter options
        $campaigns = $this->database->get_results("SELECT id, name FROM {$campaigns_table} ORDER BY name");
        $provinces = $this->database->get_results("SELECT DISTINCT province FROM {$participants_table} WHERE province IS NOT NULL ORDER BY province");
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Participants & Results</h1>
            <a href="<?php echo admin_url('admin.php?page=vefify-participants&action=export'); ?>" class="page-title-action">Export Data</a>
            
            <!-- Summary Stats -->
            <div class="participants-summary">
                <div class="summary-stats">
                    <div class="stat-card">
                        <h3><?php echo number_format($summary->total); ?></h3>
                        <p>Total Participants</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($summary->completed); ?></h3>
                        <p>Completed Quizzes</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($summary->with_gifts); ?></h3>
                        <p>Gifts Awarded</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $summary->avg_score ? number_format($summary->avg_score, 1) : '0'; ?></h3>
                        <p>Average Score</p>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="participants-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="vefify-participants">
                    
                    <select name="campaign_id" onchange="this.form.submit()">
                        <option value="">All Campaigns</option>
                        <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?php echo $campaign->id; ?>" <?php selected($campaign_filter, $campaign->id); ?>>
                                <?php echo esc_html($campaign->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="province" onchange="this.form.submit()">
                        <option value="">All Provinces</option>
                        <?php foreach ($provinces as $province): ?>
                            <option value="<?php echo esc_attr($province->province); ?>" <?php selected($province_filter, $province->province); ?>>
                                <?php echo esc_html(ucfirst($province->province)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="completed" <?php selected($status_filter, 'completed'); ?>>Completed</option>
                        <option value="incomplete" <?php selected($status_filter, 'incomplete'); ?>>Incomplete</option>
                    </select>
                    
                    <select name="date_range" onchange="this.form.submit()">
                        <option value="">All Time</option>
                        <option value="today" <?php selected($date_filter, 'today'); ?>>Today</option>
                        <option value="week" <?php selected($date_filter, 'week'); ?>>This Week</option>
                    </select>
                    
                    <?php if (array_filter([$campaign_filter, $province_filter, $status_filter, $date_filter])): ?>
                        <a href="<?php echo admin_url('admin.php?page=vefify-participants'); ?>" class="button">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Participants Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Campaign</th>
                        <th>Province</th>
                        <th>Score</th>
                        <th>Gift</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($participants)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No participants found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($participants as $participant): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($participant->full_name); ?></strong><br>
                                <small><?php echo esc_html($participant->phone_number); ?></small>
                            </td>
                            <td><?php echo esc_html($participant->campaign_name); ?></td>
                            <td><?php echo esc_html(ucfirst($participant->province)); ?></td>
                            <td>
                                <?php if ($participant->completed_at): ?>
                                    <span class="score-badge score-<?php echo $participant->score; ?>">
                                        <?php echo $participant->score; ?>/<?php echo $participant->total_questions; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="incomplete-badge">Incomplete</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($participant->gift_name): ?>
                                    <div class="gift-info">
                                        <strong><?php echo esc_html($participant->gift_name); ?></strong><br>
                                        <code><?php echo esc_html($participant->gift_code); ?></code>
                                    </div>
                                <?php else: ?>
                                    <span class="no-gift">No gift</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo mysql2date('M j, Y g:i A', $participant->created_at); ?>
                                <?php if ($participant->completed_at): ?>
                                    <br><small>Completed: <?php echo mysql2date('M j, g:i A', $participant->completed_at); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="button button-small view-details" data-participant-id="<?php echo $participant->id; ?>">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .participants-summary { margin: 20px 0; }
        .summary-stats { display: flex; gap: 20px; }
        .stat-card { background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; text-align: center; flex: 1; }
        .stat-card h3 { margin: 0 0 10px; font-size: 24px; color: #0073aa; }
        .stat-card p { margin: 0; color: #666; }
        .participants-filters { margin: 20px 0; padding: 15px; background: #f9f9f9; }
        .participants-filters form { display: flex; gap: 10px; align-items: center; }
        .score-badge { padding: 4px 8px; border-radius: 3px; color: white; font-size: 12px; font-weight: bold; }
        .score-badge.score-5 { background: #00a32a; }
        .score-badge.score-4 { background: #4caf50; }
        .score-badge.score-3 { background: #ff9800; }
        .score-badge.score-2 { background: #f44336; }
        .score-badge.score-1 { background: #d32f2f; }
        .score-badge.score-0 { background: #666; }
        .incomplete-badge { background: #ddd; color: #666; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
        .gift-info { max-width: 150px; }
        .gift-info code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; font-size: 11px; }
        .no-gift { color: #999; font-style: italic; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.view-details').click(function() {
                const participantId = $(this).data('participant-id');
                alert('Participant details for ID: ' + participantId + '\n\nFull details view coming soon!');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle export functionality
     */
    private function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $participants_table = $this->database->get_table_name('participants');
        $campaigns_table = $this->database->get_table_name('campaigns');
        $gifts_table = $this->database->get_table_name('gifts');
        
        $data = $this->database->get_results("
            SELECT 
                p.full_name, p.phone_number, p.province, p.pharmacy_code,
                p.score, p.total_questions, p.completion_time,
                p.created_at, p.completed_at,
                c.name as campaign_name,
                g.gift_name, g.gift_value, p.gift_code
            FROM {$participants_table} p
            JOIN {$campaigns_table} c ON p.campaign_id = c.id
            LEFT JOIN {$gifts_table} g ON p.gift_id = g.id
            ORDER BY p.created_at DESC
        ");
        
        if (empty($data)) {
            echo '<div class="wrap"><h1>Export Results</h1><p>No data to export.</p></div>';
            return;
        }
        
        // Convert to array for CSV
        $csv_data = array();
        foreach ($data as $row) {
            $csv_data[] = (array)$row;
        }
        
        $filename = 'vefify_participants_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add headers
        if (!empty($csv_data)) {
            fputcsv($output, array_keys($csv_data[0]));
            
            foreach ($csv_data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get module analytics for dashboard
     */
    public function get_module_analytics() {
        $participants_table = $this->database->get_table_name('participants');
        
        $stats = $this->database->get_results("
            SELECT 
                COUNT(*) as total_participants,
                COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed,
                COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as with_gifts,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today
            FROM {$participants_table}
        ");
        
        $stats = $stats[0] ?? (object)array('total_participants' => 0, 'completed' => 0, 'with_gifts' => 0, 'today' => 0);
        
        return array(
            'title' => 'Participant Management',
            'description' => 'Track and manage quiz participants and their results',
            'icon' => '👥',
            'stats' => array(
                'total_participants' => array(
                    'label' => 'Total Participants',
                    'value' => number_format($stats->total_participants),
                    'trend' => '+' . $stats->today . ' today'
                ),
                'completed_quizzes' => array(
                    'label' => 'Completed Quizzes',
                    'value' => number_format($stats->completed),
                    'trend' => round($stats->total_participants > 0 ? ($stats->completed / $stats->total_participants) * 100 : 0, 1) . '% completion rate'
                ),
                'gifts_awarded' => array(
                    'label' => 'Gifts Awarded',
                    'value' => number_format($stats->with_gifts),
                    'trend' => round($stats->completed > 0 ? ($stats->with_gifts / $stats->completed) * 100 : 0, 1) . '% gift rate'
                )
            ),
            'quick_actions' => array(
                array(
                    'label' => 'View All Participants',
                    'url' => admin_url('admin.php?page=vefify-participants'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'Export Data',
                    'url' => admin_url('admin.php?page=vefify-participants&action=export'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
}