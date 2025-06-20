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
		add_action('wp_ajax_vefify_export_participants_csv', array($this, 'ajax_export_csv'));
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
 * Render participant details page
 */
private function render_participant_details() {
    $participant_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$participant_id) {
        wp_die('Invalid participant ID');
    }
    
    global $wpdb;
    $participants_table = $this->database->get_table_name('participants');
    $campaigns_table = $this->database->get_table_name('campaigns');
    $gifts_table = $this->database->get_table_name('gifts');
    
    // Get participant details
    $participant = $wpdb->get_row($wpdb->prepare("
        SELECT 
            p.*,
            c.name as campaign_name,
            c.questions_per_quiz,
            c.pass_score,
            g.gift_name,
            g.gift_value,
            g.gift_description
        FROM {$participants_table} p
        LEFT JOIN {$campaigns_table} c ON p.campaign_id = c.id
        LEFT JOIN {$gifts_table} g ON p.gift_id = g.id
        WHERE p.id = %d
    ", $participant_id));
    
    if (!$participant) {
        wp_die('Participant not found');
    }
    
    ?>
    <div class="wrap">
        <h1>👤 Participant Details</h1>
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
            
            <!-- Main Participant Info -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2>📋 Participant Information</h2>
                </div>
                <div class="inside">
                    <table class="form-table">
                        <tr>
                            <th>Name</th>
                            <td><strong><?php echo esc_html($participant->participant_name); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo esc_html($participant->participant_email); ?></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><?php echo esc_html($participant->participant_phone); ?></td>
                        </tr>
                        <tr>
                            <th>Province</th>
                            <td><?php echo esc_html($participant->province); ?></td>
                        </tr>
                        <tr>
                            <th>Pharmacy Code</th>
                            <td><?php echo esc_html($participant->pharmacy_code); ?></td>
                        </tr>
                        <tr>
                            <th>Campaign</th>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=view&id=' . $participant->campaign_id); ?>">
                                    <?php echo esc_html($participant->campaign_name); ?>
                                </a>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Quiz Performance -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2>📊 Quiz Performance</h2>
                </div>
                <div class="inside">
                    <div style="text-align: center; margin: 20px 0;">
                        <div style="font-size: 48px; font-weight: bold; color: #0073aa;">
                            <?php echo $participant->final_score; ?>/<?php echo $participant->total_questions; ?>
                        </div>
                        <div style="margin-top: 10px;">
                            <?php 
                            $percentage = $participant->total_questions > 0 ? 
                                round(($participant->final_score / $participant->total_questions) * 100, 1) : 0;
                            echo $percentage . '%';
                            ?>
                        </div>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th>Status</th>
                            <td>
                                <?php 
                                $status_colors = array(
                                    'completed' => '#28a745',
                                    'in_progress' => '#ffc107',
                                    'started' => '#17a2b8',
                                    'abandoned' => '#dc3545'
                                );
                                $color = $status_colors[$participant->quiz_status] ?? '#6c757d';
                                echo '<span style="color: ' . $color . '; font-weight: bold;">' . ucfirst($participant->quiz_status) . '</span>';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Started</th>
                            <td><?php echo $participant->start_time ? date('M j, Y H:i', strtotime($participant->start_time)) : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <th>Completed</th>
                            <td><?php echo $participant->completed_at ? date('M j, Y H:i', strtotime($participant->completed_at)) : 'Not completed'; ?></td>
                        </tr>
                        <tr>
                            <th>Time Taken</th>
                            <td>
                                <?php 
                                if ($participant->completion_time) {
                                    $minutes = floor($participant->completion_time / 60);
                                    $seconds = $participant->completion_time % 60;
                                    echo $minutes . 'm ' . $seconds . 's';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php if ($participant->pass_score): ?>
                        <tr>
                            <th>Pass Status</th>
                            <td>
                                <?php 
                                $passed = $participant->final_score >= $participant->pass_score;
                                echo '<span style="color: ' . ($passed ? '#28a745' : '#dc3545') . '; font-weight: bold;">';
                                echo $passed ? '✅ Passed' : '❌ Failed';
                                echo '</span>';
                                echo ' (Required: ' . $participant->pass_score . ')';
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
        </div>
        
        <!-- Gift Information -->
        <?php if ($participant->gift_id): ?>
        <div class="postbox" style="margin-top: 20px;">
            <div class="postbox-header">
                <h2>🎁 Gift Information</h2>
            </div>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th>Gift Name</th>
                        <td><strong><?php echo esc_html($participant->gift_name); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Gift Value</th>
                        <td><?php echo esc_html($participant->gift_value); ?></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><?php echo esc_html($participant->gift_description); ?></td>
                    </tr>
                    <tr>
                        <th>Gift Code</th>
                        <td>
                            <?php if ($participant->gift_code): ?>
                                <code><?php echo esc_html($participant->gift_code); ?></code>
                            <?php else: ?>
                                <em>No code assigned</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Gift Status</th>
                        <td>
                            <?php 
                            $gift_status_colors = array(
                                'assigned' => '#17a2b8',
                                'claimed' => '#28a745',
                                'expired' => '#dc3545',
                                'none' => '#6c757d'
                            );
                            $gift_color = $gift_status_colors[$participant->gift_status] ?? '#6c757d';
                            echo '<span style="color: ' . $gift_color . '; font-weight: bold;">' . ucfirst($participant->gift_status) . '</span>';
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Answer Details (if available) -->
        <?php if ($participant->answers_data): ?>
        <div class="postbox" style="margin-top: 20px;">
            <div class="postbox-header">
                <h2>📝 Answer Details</h2>
            </div>
            <div class="inside">
                <?php 
                $answers = json_decode($participant->answers_data, true);
                if ($answers && is_array($answers)):
                ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                        <h4>Submitted Answers:</h4>
                        <pre style="background: white; padding: 10px; border: 1px solid #ddd; border-radius: 4px; max-height: 300px; overflow-y: auto;"><?php echo esc_html(json_encode($answers, JSON_PRETTY_PRINT)); ?></pre>
                    </div>
                <?php else: ?>
                    <p><em>Answer data not available or invalid format</em></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccd0d4;">
            <a href="<?php echo admin_url('admin.php?page=vefify-participants'); ?>" class="button button-secondary">
                ← Back to Participants
            </a>
            
            <?php if ($participant->quiz_status === 'completed'): ?>
            <a href="<?php echo admin_url('admin.php?page=vefify-participants&action=resend_gift&id=' . $participant->id); ?>" 
               class="button" onclick="return confirm('Resend gift notification to this participant?')">
                🎁 Resend Gift
            </a>
            <?php endif; ?>
            
            <a href="<?php echo admin_url('admin.php?page=vefify-participants&action=delete&id=' . $participant->id); ?>" 
               class="button button-link-delete" 
               onclick="return confirm('Are you sure you want to delete this participant? This action cannot be undone.')">
                🗑️ Delete Participant
            </a>
        </div>
    </div>
    
    <style>
    .form-table th {
        width: 150px;
        font-weight: 600;
    }
    
    .postbox {
        margin-bottom: 20px;
    }
    
    .postbox-header h2 {
        font-size: 16px;
        margin: 0;
        padding: 12px;
    }
    
    .inside {
        padding: 0 12px 12px;
    }
    </style>
    <?php
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
        
        // FIXED: Use quiz_status instead of completed_at
        if ($status_filter === 'completed') {
            $where_conditions[] = 'p.quiz_status = "completed"';
        } elseif ($status_filter === 'incomplete') {
            $where_conditions[] = 'p.quiz_status != "completed"';
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
        
        // Get participants list
        global $wpdb;
        
        if (!empty($params)) {
            $participants_query = $wpdb->prepare("
                SELECT p.*, c.name as campaign_name, g.gift_name, g.gift_value
                FROM {$participants_table} p
                JOIN {$campaigns_table} c ON p.campaign_id = c.id
                LEFT JOIN {$gifts_table} g ON p.gift_id = g.id
                WHERE {$where_clause}
                ORDER BY p.created_at DESC
                LIMIT 50
            ", $params);
        } else {
            $participants_query = "
                SELECT p.*, c.name as campaign_name, g.gift_name, g.gift_value
                FROM {$participants_table} p
                JOIN {$campaigns_table} c ON p.campaign_id = c.id
                LEFT JOIN {$gifts_table} g ON p.gift_id = g.id
                WHERE {$where_clause}
                ORDER BY p.created_at DESC
                LIMIT 50
            ";
        }
        
        $participants = $wpdb->get_results($participants_query);
        
        // FIXED: Get summary stats using direct wpdb query
        if (!empty($params)) {
            $summary_query = $wpdb->prepare("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed,
                    COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as with_gifts,
                    AVG(CASE WHEN final_score > 0 THEN final_score END) as avg_score
                FROM {$participants_table} p
                WHERE {$where_clause}
            ", $params);
        } else {
            $summary_query = "
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed,
                    COUNT(CASE WHEN gift_id IS NOT NULL THEN 1 END) as with_gifts,
                    AVG(CASE WHEN final_score > 0 THEN final_score END) as avg_score
                FROM {$participants_table} p
                WHERE {$where_clause}
            ";
        }
        
        $summary = $wpdb->get_row($summary_query);
        
        // Ensure we have a valid summary object
        if (!$summary) {
            $summary = (object) array(
                'total' => 0,
                'completed' => 0,
                'with_gifts' => 0,
                'avg_score' => 0
            );
        }
        
        // Ensure all values are properly typed
        $summary->total = intval($summary->total ?? 0);
        $summary->completed = intval($summary->completed ?? 0);
        $summary->with_gifts = intval($summary->with_gifts ?? 0);
        $summary->avg_score = floatval($summary->avg_score ?? 0);
        
        // Get filter options
        $campaigns = $wpdb->get_results("SELECT id, name FROM {$campaigns_table} ORDER BY name");
        $provinces = $wpdb->get_results("SELECT DISTINCT province FROM {$participants_table} WHERE province IS NOT NULL ORDER BY province");
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Participants & Results</h1>
            <a href="<?php echo admin_url('admin-ajax.php?action=vefify_export_participants_csv&nonce=' . wp_create_nonce('export_csv')); ?>" 
   class="page-title-action" target="_blank">
   📥 Export Data
</a>
            
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
                        <h3><?php echo round($summary->avg_score, 1); ?></h3>
                        <p>Average Score</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select name="campaign_id" id="campaign-filter">
                        <option value="">All Campaigns</option>
                        <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?php echo $campaign->id; ?>" <?php selected($campaign_filter, $campaign->id); ?>>
                                <?php echo esc_html($campaign->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="province" id="province-filter">
                        <option value="">All Provinces</option>
                        <?php foreach ($provinces as $province): ?>
                            <option value="<?php echo esc_attr($province->province); ?>" <?php selected($province_filter, $province->province); ?>>
                                <?php echo esc_html($province->province); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status" id="status-filter">
                        <option value="">All Status</option>
                        <option value="completed" <?php selected($status_filter, 'completed'); ?>>Completed</option>
                        <option value="incomplete" <?php selected($status_filter, 'incomplete'); ?>>Incomplete</option>
                    </select>
                    
                    <input type="submit" name="filter_action" id="post-query-submit" class="button" value="Filter">
                </div>
            </div>

            <!-- Participants Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Campaign</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($participants)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">
                                No participants found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($participants as $participant): ?>
                            <tr>
                                <td><strong><?php echo esc_html($participant->participant_name); ?></strong></td>
                                <td><?php echo esc_html($participant->participant_email); ?></td>
                                <td><?php echo esc_html($participant->participant_phone); ?></td>
                                <td><?php echo esc_html($participant->campaign_name); ?></td>
                                <td><?php echo $participant->final_score . '/' . $participant->total_questions; ?></td>
                                <td>
                                    <?php 
                                    $status_colors = array(
                                        'completed' => '#28a745',
                                        'in_progress' => '#ffc107',
                                        'started' => '#17a2b8',
                                        'abandoned' => '#dc3545'
                                    );
                                    $color = $status_colors[$participant->quiz_status] ?? '#6c757d';
                                    echo '<span style="color: ' . $color . '; font-weight: bold;">' . ucfirst($participant->quiz_status) . '</span>';
                                    ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($participant->created_at)); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=vefify-participants&action=view&id=' . $participant->id); ?>" class="button button-small">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .participants-summary { margin: 20px 0; }
        .summary-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0; }
        .stat-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { margin: 0 0 10px; font-size: 28px; color: #0073aa; }
        .stat-card p { margin: 0; color: #666; font-size: 14px; }
        </style>
        <?php
    }
    
    /**
     * Handle export functionality
     */
    private function handle_export() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Check if headers have already been sent
    if (headers_sent($file, $line)) {
        // Headers already sent, show download link instead
        $this->show_export_download_link();
        return;
    }
    
    global $wpdb;
    $participants_table = $this->database->get_table_name('participants');
    $campaigns_table = $this->database->get_table_name('campaigns');
    $gifts_table = $this->database->get_table_name('gifts');
    
    // Get data
    $data = $wpdb->get_results("
        SELECT 
            p.participant_name,
            p.participant_email,
            p.participant_phone,
            p.province,
            p.pharmacy_code,
            p.quiz_status,
            p.final_score,
            p.total_questions,
            p.completion_time,
            p.start_time,
            p.end_time,
            p.completed_at,
            p.created_at,
            c.name as campaign_name,
            g.gift_name,
            g.gift_value,
            p.gift_code,
            p.gift_status
        FROM {$participants_table} p
        JOIN {$campaigns_table} c ON p.campaign_id = c.id
        LEFT JOIN {$gifts_table} g ON p.gift_id = g.id
        ORDER BY p.created_at DESC
    ", ARRAY_A);
    
    if (empty($data)) {
        ?>
        <div class="wrap">
            <h1>📥 Export Results</h1>
            <div class="notice notice-warning">
                <p><strong>No participants found to export.</strong></p>
                <a href="<?php echo admin_url('admin.php?page=vefify-participants'); ?>" class="button">← Back to Participants</a>
            </div>
        </div>
        <?php
        return;
    }
    
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh output buffering
    ob_start();
    
    // Set headers for CSV download
    $filename = 'vefify_participants_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    header('Pragma: public');
    
    // Generate CSV content
    $csv_content = $this->generate_csv_content($data);
    
    // Output the CSV
    echo $csv_content;
    
    // Clean up and exit
    ob_end_flush();
    exit();
}

/**
 * Generate CSV content as string
 */
private function generate_csv_content($data) {
    // Create temporary file in memory
    $output = fopen('php://temp', 'r+');
    
    // Add UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    // CSV Headers
    $headers = array(
        'Name',
        'Email', 
        'Phone',
        'Province',
        'Pharmacy Code',
        'Campaign',
        'Quiz Status',
        'Final Score',
        'Total Questions',
        'Completion Time (seconds)',
        'Start Time',
        'End Time',
        'Completed At',
        'Created At',
        'Gift Name',
        'Gift Value',
        'Gift Code',
        'Gift Status'
    );
    
    fputcsv($output, $headers);
    
    // Add data rows
    foreach ($data as $row) {
        $csv_row = array(
            $row['participant_name'] ?: '',
            $row['participant_email'] ?: '',
            $row['participant_phone'] ?: '',
            $row['province'] ?: '',
            $row['pharmacy_code'] ?: '',
            $row['campaign_name'] ?: '',
            ucfirst($row['quiz_status'] ?: 'unknown'),
            $row['final_score'] ?: '0',
            $row['total_questions'] ?: '0',
            $row['completion_time'] ?: '',
            $row['start_time'] ? date('Y-m-d H:i:s', strtotime($row['start_time'])) : '',
            $row['end_time'] ? date('Y-m-d H:i:s', strtotime($row['end_time'])) : '',
            $row['completed_at'] ? date('Y-m-d H:i:s', strtotime($row['completed_at'])) : '',
            $row['created_at'] ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : '',
            $row['gift_name'] ?: '',
            $row['gift_value'] ?: '',
            $row['gift_code'] ?: '',
            ucfirst($row['gift_status'] ?: 'none')
        );
        
        fputcsv($output, $csv_row);
    }
    
    // Get the CSV content
    rewind($output);
    $csv_content = stream_get_contents($output);
    fclose($output);
    
    return $csv_content;
}

/**
 * Show download link when headers are already sent
 */
private function show_export_download_link() {
    ?>
    <div class="wrap">
        <h1>📥 Export Participants</h1>
        
        <div class="notice notice-success">
            <p><strong>✅ Export is ready!</strong> Your participant data has been generated successfully.</p>
        </div>
        
        <div class="card" style="max-width: 600px;">
            <h3>📊 Export Options</h3>
            <p>Due to technical constraints, please use one of these methods to download your data:</p>
            
            <div style="margin: 20px 0;">
                <h4>Method 1: Direct Download (Recommended)</h4>
                <p>Click the button below to download your CSV file directly:</p>
                <a href="<?php echo admin_url('admin.php?page=vefify-participants&action=export&direct=1'); ?>" 
                   class="button button-primary button-large" target="_blank">
                    📥 Download CSV File
                </a>
            </div>
            
            <div style="margin: 20px 0;">
                <h4>Method 2: Copy Data</h4>
                <p>Copy the CSV data below and paste it into a text file:</p>
                <textarea style="width: 100%; height: 200px; font-family: monospace;" readonly onclick="this.select();"><?php 
                    echo $this->get_csv_data_for_display(); 
                ?></textarea>
                <p><small>Copy this text and save as a .csv file</small></p>
            </div>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="<?php echo admin_url('admin.php?page=vefify-participants'); ?>" class="button">
                ← Back to Participants
            </a>
        </div>
    </div>
    <?php
}

/**
 * Get CSV data for display purposes
 */
private function get_csv_data_for_display() {
    global $wpdb;
    $participants_table = $this->database->get_table_name('participants');
    $campaigns_table = $this->database->get_table_name('campaigns');
    $gifts_table = $this->database->get_table_name('gifts');
    
    $data = $wpdb->get_results("
        SELECT 
            p.participant_name,
            p.participant_email,
            p.participant_phone,
            p.province,
            p.pharmacy_code,
            p.quiz_status,
            p.final_score,
            p.total_questions,
            p.completion_time,
            p.start_time,
            p.end_time,
            p.completed_at,
            p.created_at,
            c.name as campaign_name,
            g.gift_name,
            g.gift_value,
            p.gift_code,
            p.gift_status
        FROM {$participants_table} p
        JOIN {$campaigns_table} c ON p.campaign_id = c.id
        LEFT JOIN {$gifts_table} g ON p.gift_id = g.id
        ORDER BY p.created_at DESC
        LIMIT 100
    ", ARRAY_A);
    
    if (empty($data)) {
        return "No data available for export.";
    }
    
    return $this->generate_csv_content($data);
}

/**
 * Alternative: AJAX-based export (if headers issue persists)
 */
public function ajax_export_participants() {
    // Verify nonce and permissions
    if (!wp_verify_nonce($_POST['nonce'], 'vefify_export_nonce') || !current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    global $wpdb;
    $participants_table = $this->database->get_table_name('participants');
    $campaigns_table = $this->database->get_table_name('campaigns');
    $gifts_table = $this->database->get_table_name('gifts');
    
    $data = $wpdb->get_results("
        SELECT 
            p.participant_name,
            p.participant_email,
            p.participant_phone,
            p.province,
            p.quiz_status,
            p.final_score,
            p.total_questions,
            c.name as campaign_name
        FROM {$participants_table} p
        JOIN {$campaigns_table} c ON p.campaign_id = c.id
        ORDER BY p.created_at DESC
    ", ARRAY_A);
    
    if (empty($data)) {
        wp_send_json_error('No data to export');
    }
    
    // Generate CSV content
    $csv_content = $this->generate_csv_content($data);
    
    wp_send_json_success(array(
        'csv_data' => $csv_content,
        'filename' => 'participants_' . date('Y-m-d') . '.csv',
        'count' => count($data)
    ));
}


/**
 * AJAX CSV Export - handles clean CSV download
 */
public function ajax_export_csv() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    // Verify nonce if you want extra security
    if (isset($_GET['nonce']) && !wp_verify_nonce($_GET['nonce'], 'export_csv')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $participants_table = $this->database->get_table_name('participants');
    $campaigns_table = $this->database->get_table_name('campaigns');
    $gifts_table = $this->database->get_table_name('gifts');
    
    // Get the data using your corrected column names
    $data = $wpdb->get_results("
        SELECT 
            p.participant_name as 'Name',
            p.participant_email as 'Email',
            p.participant_phone as 'Phone',
            p.province as 'Province',
            p.pharmacy_code as 'Pharmacy Code',
            c.name as 'Campaign',
            p.quiz_status as 'Status',
            p.final_score as 'Score',
            p.total_questions as 'Total Questions',
            p.completion_time as 'Completion Time',
            DATE(p.created_at) as 'Date Joined',
            p.gift_code as 'Gift Code',
            g.gift_name as 'Gift Name'
        FROM {$participants_table} p
        LEFT JOIN {$campaigns_table} c ON p.campaign_id = c.id
        LEFT JOIN {$gifts_table} g ON p.gift_id = g.id
        ORDER BY p.created_at DESC
    ", ARRAY_A);
    
    // Check if we have data
    if (empty($data)) {
        wp_die('No participants found to export. <a href="' . admin_url('admin.php?page=vefify-participants') . '">← Back</a>');
    }
    
    // Set CSV headers - this works because no output has been sent yet
    $filename = 'vefify_participants_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers (using the aliased column names from the query)
    fputcsv($output, array_keys($data[0]));
    
    // Write data
    foreach ($data as $row) {
        // Clean up the data for better CSV format
        $clean_row = array();
        foreach ($row as $value) {
            $clean_row[] = $value ?: '';
        }
        fputcsv($output, $clean_row);
    }
    
    fclose($output);
    exit; // Important: stop execution here
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