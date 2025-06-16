<?php
/**
 * Report Manager Class
 * File: modules/reports/class-report-manager.php
 */
class Vefify_Report_Manager {
    
    private $model;
    
    public function __construct() {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/reports/class-report-model.php';
        $this->model = new Vefify_Report_Model();
    }
    
    public function display_reports_dashboard() {
        $analytics = $this->model->get_comprehensive_analytics('30days');
        ?>
        <div class="wrap">
            <h1>📊 Reports Dashboard</h1>
            
            <!-- Quick Stats -->
            <div class="reports-overview">
                <div class="overview-cards">
                    <div class="overview-card">
                        <h3><?php echo number_format($analytics['overview']['total_participants']); ?></h3>
                        <p>Total Participants</p>
                        <span class="trend positive">+23% this month</span>
                    </div>
                    <div class="overview-card">
                        <h3><?php echo number_format($analytics['overview']['completed_quizzes']); ?></h3>
                        <p>Completed Quizzes</p>
                        <span class="trend positive">+15% completion rate</span>
                    </div>
                    <div class="overview-card">
                        <h3><?php echo number_format($analytics['overview']['gifts_distributed']); ?></h3>
                        <p>Gifts Distributed</p>
                        <span class="trend positive">+45% engagement</span>
                    </div>
                    <div class="overview-card">
                        <h3><?php echo number_format($analytics['overview']['active_campaigns']); ?></h3>
                        <p>Active Campaigns</p>
                        <span class="trend">Running now</span>
                    </div>
                </div>
            </div>
            
            <!-- Report Categories -->
            <div class="report-categories">
                <h2>📋 Available Reports</h2>
                <div class="categories-grid">
                    <div class="category-card">
                        <h3>📋 Campaign Reports</h3>
                        <p>Performance analysis, conversion rates, and campaign effectiveness metrics</p>
                        <a href="<?php echo admin_url('admin.php?page=vefify-reports&action=campaign'); ?>" class="button button-primary">View Reports</a>
                    </div>
                    
                    <div class="category-card">
                        <h3>👥 Participant Analytics</h3>
                        <p>User behavior, engagement patterns, demographics and completion analysis</p>
                        <a href="<?php echo admin_url('admin.php?page=vefify-reports&action=participant'); ?>" class="button button-primary">View Reports</a>
                    </div>
                    
                    <div class="category-card">
                        <h3>🎯 Performance Metrics</h3>
                        <p>Score distributions, question difficulty analysis, and learning outcomes</p>
                        <a href="<?php echo admin_url('admin.php?page=vefify-reports&action=performance'); ?>" class="button button-primary">View Reports</a>
                    </div>
                    
                    <div class="category-card">
                        <h3>🎁 Gift Distribution</h3>
                        <p>Reward effectiveness, redemption rates, and inventory management</p>
                        <a href="<?php echo admin_url('admin.php?page=vefify-gifts&action=distribution'); ?>" class="button button-primary">View Reports</a>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>⚡ Quick Actions</h2>
                <div class="actions-grid">
                    <a href="<?php echo admin_url('admin.php?page=vefify-reports&action=custom'); ?>" class="action-button">
                        <span class="dashicons dashicons-chart-bar"></span>
                        Create Custom Report
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=vefify-reports&action=scheduled'); ?>" class="action-button">
                        <span class="dashicons dashicons-clock"></span>
                        Schedule Reports
                    </a>
                    <button type="button" id="export-all-data" class="action-button">
                        <span class="dashicons dashicons-download"></span>
                        Export All Data
                    </button>
                    <button type="button" id="generate-summary" class="action-button">
                        <span class="dashicons dashicons-analytics"></span>
                        Generate Summary
                    </button>
                </div>
            </div>
            
            <!-- Recent Trends Chart -->
            <div class="trends-section">
                <h2>📈 Recent Trends</h2>
                <div class="chart-container">
                    <canvas id="trends-chart" width="800" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <style>
        .reports-overview { margin: 20px 0; }
        .overview-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .overview-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; text-align: center; }
        .overview-card h3 { font-size: 32px; margin: 0; color: #0073aa; }
        .overview-card p { margin: 8px 0; color: #666; }
        .trend { font-size: 12px; font-weight: bold; }
        .trend.positive { color: #46b450; }
        .categories-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin: 20px 0; }
        .category-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; }
        .category-card h3 { margin: 0 0 10px; color: #0073aa; }
        .category-card p { color: #666; margin: 10px 0 15px; }
        .actions-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
        .action-button { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; text-decoration: none; color: #333; transition: all 0.3s; }
        .action-button:hover { background: #0073aa; color: white; transform: translateY(-2px); }
        .chart-container { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; margin: 20px 0; }
        </style>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
        <script>
        // Render trends chart
        const trendsCtx = document.getElementById('trends-chart').getContext('2d');
        const trendsData = <?php echo json_encode($analytics['trends']['participation_trends']); ?>;
        
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: trendsData.map(item => item.date),
                datasets: [{
                    label: 'Participants',
                    data: trendsData.map(item => item.participants),
                    borderColor: '#0073aa',
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Completed',
                    data: trendsData.map(item => item.completed),
                    borderColor: '#46b450',
                    backgroundColor: 'rgba(70, 180, 80, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    title: {
                        display: true,
                        text: 'Participation Trends (Last 30 Days)'
                    }
                }
            }
        });
        </script>
        <?php
    }
    
    public function display_campaign_report() {
        ?>
        <div class="wrap">
            <h1>📋 Campaign Performance Report</h1>
            <!-- Campaign-specific reporting interface -->
            <p>Detailed campaign performance metrics and analysis will be displayed here.</p>
        </div>
        <?php
    }
    
    public function display_participant_report() {
        ?>
        <div class="wrap">
            <h1>👥 Participant Analysis Report</h1>
            <!-- Participant analytics interface -->
            <p>Comprehensive participant behavior and engagement analysis will be displayed here.</p>
        </div>
        <?php
    }
    
    public function display_performance_report() {
        ?>
        <div class="wrap">
            <h1>🎯 Performance Metrics Report</h1>
            <!-- Performance metrics interface -->
            <p>Detailed performance analysis and learning outcomes will be displayed here.</p>
        </div>
        <?php
    }
    
    public function display_custom_report_builder() {
        ?>
        <div class="wrap">
            <h1>🔧 Custom Report Builder</h1>
            <!-- Custom report builder interface -->
            <p>Custom report creation tools will be available here.</p>
        </div>
        <?php
    }
    
    public function display_scheduled_reports() {
        ?>
        <div class="wrap">
            <h1>⏰ Scheduled Reports</h1>
            <!-- Scheduled reports management interface -->
            <p>Automated report scheduling and management will be available here.</p>
        </div>
        <?php
    }
}