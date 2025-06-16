<?php
/**
 * Centralized Admin Menu Manager
 * File: includes/class-admin-menu.php
 * 
 * Handles all admin menu registration and routing
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Admin_Menu {
    
    private $pages = array();
    private $current_page = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->define_menu_structure();
    }
    
    /**
     * Define the complete menu structure
     */
    private function define_menu_structure() {
        $this->pages = array(
            'main' => array(
                'page_title' => 'Vefify Quiz Dashboard',
                'menu_title' => 'Vefify Quiz',
                'capability' => 'manage_options',
                'menu_slug' => 'vefify-quiz',
                'callback' => array($this, 'render_dashboard'),
                'icon_url' => 'dashicons-forms',
                'position' => 30
            ),
            'submenus' => array(
                'dashboard' => array(
                    'parent_slug' => 'vefify-quiz',
                    'page_title' => 'Dashboard',
                    'menu_title' => '📊 Dashboard',
                    'capability' => 'manage_options',
                    'menu_slug' => 'vefify-quiz',
                    'callback' => array($this, 'render_dashboard')
                ),
                'campaigns' => array(
                    'parent_slug' => 'vefify-quiz',
                    'page_title' => 'Campaign Management',
                    'menu_title' => '📋 Campaigns',
                    'capability' => 'manage_options',
                    'menu_slug' => 'vefify-campaigns',
                    'callback' => array($this, 'render_campaigns')
                ),
                'questions' => array(
                    'parent_slug' => 'vefify-quiz',
                    'page_title' => 'Question Bank',
                    'menu_title' => '❓ Questions',
                    'capability' => 'manage_options',
                    'menu_slug' => 'vefify-questions',
                    'callback' => array($this, 'render_questions')
                ),
                'participants' => array(
                    'parent_slug' => 'vefify-quiz',
                    'page_title' => 'Participants & Results',
                    'menu_title' => '👥 Participants',
                    'capability' => 'manage_options',
                    'menu_slug' => 'vefify-participants',
                    'callback' => array($this, 'render_participants')
                ),
                'gifts' => array(
                    'parent_slug' => 'vefify-quiz',
                    'page_title' => 'Gift Management',
                    'menu_title' => '🎁 Gifts',
                    'capability' => 'manage_options',
                    'menu_slug' => 'vefify-gifts',
                    'callback' => array($this, 'render_gifts')
                ),
                'analytics' => array(
                    'parent_slug' => 'vefify-quiz',
                    'page_title' => 'Analytics & Reports',
                    'menu_title' => '📈 Analytics',
                    'capability' => 'manage_options',
                    'menu_slug' => 'vefify-analytics',
                    'callback' => array($this, 'render_analytics')
                ),
                'settings' => array(
                    'parent_slug' => 'vefify-quiz',
                    'page_title' => 'Settings & Configuration',
                    'menu_title' => '⚙️ Settings',
                    'capability' => 'manage_options',
                    'menu_slug' => 'vefify-settings',
                    'callback' => array($this, 'render_settings')
                )
            )
        );
    }
    
    /**
     * Register all WordPress menus
     */
    public function register_menus() {
        // Main menu
        $main = $this->pages['main'];
        add_menu_page(
            $main['page_title'],
            $main['menu_title'],
            $main['capability'],
            $main['menu_slug'],
            $main['callback'],
            $main['icon_url'],
            $main['position']
        );
        
        // Submenus
        foreach ($this->pages['submenus'] as $submenu) {
            add_submenu_page(
                $submenu['parent_slug'],
                $submenu['page_title'],
                $submenu['menu_title'],
                $submenu['capability'],
                $submenu['menu_slug'],
                $submenu['callback']
            );
        }
        
        // Store current page for navigation
        $this->current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : null;
    }
    
    /**
     * Render Dashboard page
     */
    public function render_dashboard() {
        $this->render_page_header('Dashboard', 'Comprehensive overview of your quiz campaigns');
        
        ?>
        <div class="vefify-dashboard">
            <!-- Quick Stats Grid -->
            <div class="dashboard-stats" id="dashboard-stats">
                <div class="loading-placeholder">
                    <div class="spinner"></div>
                    <p><?php esc_html_e('Loading dashboard data...', 'vefify-quiz'); ?></p>
                </div>
            </div>
            
            <!-- Main Dashboard Content -->
            <div class="dashboard-content">
                <div class="dashboard-left">
                    <!-- Recent Activity -->
                    <div class="dashboard-section">
                        <h3><?php esc_html_e('🕒 Recent Activity', 'vefify-quiz'); ?></h3>
                        <div id="recent-activity">
                            <div class="loading-placeholder">Loading recent activity...</div>
                        </div>
                    </div>
                    
                    <!-- Active Campaigns -->
                    <div class="dashboard-section">
                        <h3><?php esc_html_e('🔥 Active Campaigns', 'vefify-quiz'); ?></h3>
                        <div id="active-campaigns">
                            <div class="loading-placeholder">Loading campaigns...</div>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-right">
                    <!-- Quick Actions -->
                    <div class="dashboard-section">
                        <h3><?php esc_html_e('⚡ Quick Actions', 'vefify-quiz'); ?></h3>
                        <div class="quick-actions">
                            <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=new'); ?>" class="action-button primary">
                                <span class="icon">➕</span>
                                <span class="text"><?php esc_html_e('Create Campaign', 'vefify-quiz'); ?></span>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=vefify-questions&action=new'); ?>" class="action-button">
                                <span class="icon">❓</span>
                                <span class="text"><?php esc_html_e('Add Questions', 'vefify-quiz'); ?></span>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=vefify-participants'); ?>" class="action-button">
                                <span class="icon">👥</span>
                                <span class="text"><?php esc_html_e('View Results', 'vefify-quiz'); ?></span>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=vefify-analytics'); ?>" class="action-button">
                                <span class="icon">📊</span>
                                <span class="text"><?php esc_html_e('Analytics', 'vefify-quiz'); ?></span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- System Status -->
                    <div class="dashboard-section">
                        <h3><?php esc_html_e('🔧 System Status', 'vefify-quiz'); ?></h3>
                        <div id="system-status">
                            <div class="loading-placeholder">Checking system status...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Load dashboard data via AJAX
            loadDashboardData();
        });
        
        function loadDashboardData() {
            jQuery.post(ajaxurl, {
                action: 'vefify_dashboard_data',
                nonce: vefifyAdmin.nonce
            }, function(response) {
                if (response.success) {
                    renderDashboardStats(response.data);
                    renderRecentActivity(response.data);
                    renderActiveCampaigns(response.data);
                    renderSystemStatus(response.data);
                } else {
                    console.error('Failed to load dashboard data:', response.data);
                }
            });
        }
        
        function renderDashboardStats(data) {
            // Implementation for stats rendering
            const statsHtml = `
                <div class="stat-card">
                    <h3>📊 Total Campaigns</h3>
                    <div class="stat-number">${data.campaigns?.stats?.total_campaigns?.value || 0}</div>
                    <div class="stat-subtitle">${data.campaigns?.stats?.active_campaigns?.value || 0} active</div>
                </div>
                <div class="stat-card">
                    <h3>👥 Participants</h3>
                    <div class="stat-number">${data.participants?.stats?.total_participants?.value || 0}</div>
                    <div class="stat-subtitle">This month</div>
                </div>
                <div class="stat-card">
                    <h3>❓ Questions</h3>
                    <div class="stat-number">${data.questions?.stats?.total_questions?.value || 0}</div>
                    <div class="stat-subtitle">In question bank</div>
                </div>
                <div class="stat-card">
                    <h3>🎁 Gifts</h3>
                    <div class="stat-number">${data.gifts?.stats?.distributed_gifts?.value || 0}</div>
                    <div class="stat-subtitle">Distributed</div>
                </div>
            `;
            
            jQuery('#dashboard-stats').html(statsHtml);
        }
        
        function renderRecentActivity(data) {
            // Implementation for recent activity
            jQuery('#recent-activity').html('<p>Recent activity will be displayed here.</p>');
        }
        
        function renderActiveCampaigns(data) {
            // Implementation for active campaigns
            jQuery('#active-campaigns').html('<p>Active campaigns will be displayed here.</p>');
        }
        
        function renderSystemStatus(data) {
            // Implementation for system status
            const statusHtml = `
                <div class="status-item">
                    <span class="status-icon">✅</span>
                    <span class="status-text">Database: Connected</span>
                </div>
                <div class="status-item">
                    <span class="status-icon">✅</span>
                    <span class="status-text">Cache: Active</span>
                </div>
                <div class="status-item">
                    <span class="status-icon">✅</span>
                    <span class="status-text">Email: Configured</span>
                </div>
            `;
            
            jQuery('#system-status').html(statusHtml);
        }
        </script>
        <?php
    }
    
    /**
     * Render Campaigns page
     */
    public function render_campaigns() {
        $plugin = Vefify_Quiz_Plugin::get_instance();
        $campaigns_module = $plugin->get_module('campaigns');
        
        if ($campaigns_module && method_exists($campaigns_module, 'admin_page_router')) {
            $campaigns_module->admin_page_router();
        } else {
            $this->render_module_placeholder('Campaigns', 'Campaign management functionality is being loaded...');
        }
    }
    
    /**
     * Render Questions page
     */
    public function render_questions() {
        $plugin = Vefify_Quiz_Plugin::get_instance();
        $questions_module = $plugin->get_module('questions');
        
        if ($questions_module && method_exists($questions_module, 'admin_page_router')) {
            $questions_module->admin_page_router();
        } else {
            $this->render_module_placeholder('Question Bank', 'Question management functionality is being loaded...');
        }
    }
    
    /**
     * Render Participants page
     */
    public function render_participants() {
        $plugin = Vefify_Quiz_Plugin::get_instance();
        $participants_module = $plugin->get_module('participants');
        
        if ($participants_module && method_exists($participants_module, 'admin_page_router')) {
            $participants_module->admin_page_router();
        } else {
            $this->render_module_placeholder('Participants', 'Participant management functionality is being loaded...');
        }
    }
    
    /**
     * Render Gifts page
     */
    public function render_gifts() {
        $plugin = Vefify_Quiz_Plugin::get_instance();
        $gifts_module = $plugin->get_module('gifts');
        
        if ($gifts_module && method_exists($gifts_module, 'admin_page_router')) {
            $gifts_module->admin_page_router();
        } else {
            $this->render_module_placeholder('Gift Management', 'Gift management functionality is being loaded...');
        }
    }
    
    /**
     * Render Analytics page
     */
    public function render_analytics() {
        $plugin = Vefify_Quiz_Plugin::get_instance();
        $analytics_module = $plugin->get_module('analytics');
        
        if ($analytics_module && method_exists($analytics_module, 'admin_page_router')) {
            $analytics_module->admin_page_router();
        } else {
            $this->render_module_placeholder('Analytics', 'Analytics functionality is being loaded...');
        }
    }
    
    /**
     * Render Settings page - FIXED IMPLEMENTATION
     */
    public function render_settings() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        $this->render_page_header('Settings', 'Configure your quiz platform');
        
        ?>
        <div class="vefify-settings">
            <!-- Settings Navigation Tabs -->
            <nav class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=vefify-settings&tab=general'); ?>" 
                   class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('General', 'vefify-quiz'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=vefify-settings&tab=appearance'); ?>" 
                   class="nav-tab <?php echo $tab === 'appearance' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Appearance', 'vefify-quiz'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=vefify-settings&tab=notifications'); ?>" 
                   class="nav-tab <?php echo $tab === 'notifications' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Notifications', 'vefify-quiz'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=vefify-settings&tab=integrations'); ?>" 
                   class="nav-tab <?php echo $tab === 'integrations' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Integrations', 'vefify-quiz'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=vefify-settings&tab=advanced'); ?>" 
                   class="nav-tab <?php echo $tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Advanced', 'vefify-quiz'); ?>
                </a>
            </nav>
            
            <div class="settings-content">
                <?php
                switch ($tab) {
                    case 'general':
                        $this->render_general_settings();
                        break;
                    case 'appearance':
                        $this->render_appearance_settings();
                        break;
                    case 'notifications':
                        $this->render_notification_settings();
                        break;
                    case 'integrations':
                        $this->render_integration_settings();
                        break;
                    case 'advanced':
                        $this->render_advanced_settings();
                        break;
                    default:
                        $this->render_general_settings();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render General Settings
     */
    private function render_general_settings() {
        $settings = get_option('vefify_quiz_settings', array());
        
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('vefify_general_settings');
            do_settings_sections('vefify_general_settings');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Default Questions per Quiz', 'vefify-quiz'); ?></th>
                    <td>
                        <input type="number" name="vefify_quiz_settings[default_questions_per_quiz]" 
                               value="<?php echo esc_attr($settings['default_questions_per_quiz'] ?? 5); ?>" 
                               min="1" max="50" class="small-text">
                        <p class="description"><?php esc_html_e('Default number of questions to show in each quiz', 'vefify-quiz'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Default Time Limit', 'vefify-quiz'); ?></th>
                    <td>
                        <input type="number" name="vefify_quiz_settings[default_time_limit]" 
                               value="<?php echo esc_attr(($settings['default_time_limit'] ?? 600) / 60); ?>" 
                               min="1" max="120" class="small-text">
                        <span><?php esc_html_e('minutes', 'vefify-quiz'); ?></span>
                        <p class="description"><?php esc_html_e('Default time limit for quizzes (0 for no limit)', 'vefify-quiz'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Default Pass Score', 'vefify-quiz'); ?></th>
                    <td>
                        <input type="number" name="vefify_quiz_settings[default_pass_score]" 
                               value="<?php echo esc_attr($settings['default_pass_score'] ?? 3); ?>" 
                               min="1" class="small-text">
                        <p class="description"><?php esc_html_e('Default minimum score required to pass', 'vefify-quiz'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Required Fields', 'vefify-quiz'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="vefify_quiz_settings[phone_required]" 
                                       value="1" <?php checked($settings['phone_required'] ?? true); ?>>
                                <?php esc_html_e('Phone number required', 'vefify-quiz'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="vefify_quiz_settings[province_required]" 
                                       value="1" <?php checked($settings['province_required'] ?? true); ?>>
                                <?php esc_html_e('Province/City required', 'vefify-quiz'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="vefify_quiz_settings[pharmacy_code_required]" 
                                       value="1" <?php checked($settings['pharmacy_code_required'] ?? false); ?>>
                                <?php esc_html_e('Pharmacy code required', 'vefify-quiz'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Features', 'vefify-quiz'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="vefify_quiz_settings[enable_retakes]" 
                                       value="1" <?php checked($settings['enable_retakes'] ?? false); ?>>
                                <?php esc_html_e('Allow quiz retakes', 'vefify-quiz'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="vefify_quiz_settings[enable_analytics]" 
                                       value="1" <?php checked($settings['enable_analytics'] ?? true); ?>>
                                <?php esc_html_e('Enable analytics tracking', 'vefify-quiz'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="vefify_quiz_settings[enable_gift_api]" 
                                       value="1" <?php checked($settings['enable_gift_api'] ?? false); ?>>
                                <?php esc_html_e('Enable gift API integration', 'vefify-quiz'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * Render other settings tabs (placeholder implementations)
     */
    private function render_appearance_settings() {
        echo '<div class="settings-placeholder">';
        echo '<h3>' . esc_html__('Appearance Settings', 'vefify-quiz') . '</h3>';
        echo '<p>' . esc_html__('Customize the look and feel of your quizzes.', 'vefify-quiz') . '</p>';
        echo '<p><em>' . esc_html__('Coming soon in future updates.', 'vefify-quiz') . '</em></p>';
        echo '</div>';
    }
    
    private function render_notification_settings() {
        echo '<div class="settings-placeholder">';
        echo '<h3>' . esc_html__('Notification Settings', 'vefify-quiz') . '</h3>';
        echo '<p>' . esc_html__('Configure email and SMS notifications.', 'vefify-quiz') . '</p>';
        echo '<p><em>' . esc_html__('Coming soon in future updates.', 'vefify-quiz') . '</em></p>';
        echo '</div>';
    }
    
    private function render_integration_settings() {
        echo '<div class="settings-placeholder">';
        echo '<h3>' . esc_html__('Integration Settings', 'vefify-quiz') . '</h3>';
        echo '<p>' . esc_html__('Connect with third-party services and APIs.', 'vefify-quiz') . '</p>';
        echo '<p><em>' . esc_html__('Coming soon in future updates.', 'vefify-quiz') . '</em></p>';
        echo '</div>';
    }
    
    private function render_advanced_settings() {
        echo '<div class="settings-placeholder">';
        echo '<h3>' . esc_html__('Advanced Settings', 'vefify-quiz') . '</h3>';
        echo '<p>' . esc_html__('Advanced configuration options for developers.', 'vefify-quiz') . '</p>';
        echo '<p><em>' . esc_html__('Coming soon in future updates.', 'vefify-quiz') . '</em></p>';
        echo '</div>';
    }
    
    /**
     * Render page header with breadcrumbs
     */
    private function render_page_header($title, $description = '') {
        ?>
        <div class="vefify-page-header">
            <div class="page-title-section">
                <h1 class="page-title">
                    <span class="page-icon"><?php echo $this->get_page_icon(); ?></span>
                    <?php echo esc_html($title); ?>
                </h1>
                <?php if ($description): ?>
                    <p class="page-description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="page-breadcrumb">
                <a href="<?php echo admin_url('admin.php?page=vefify-quiz'); ?>">
                    <?php esc_html_e('Dashboard', 'vefify-quiz'); ?>
                </a>
                <span class="separator">›</span>
                <span class="current"><?php echo esc_html($title); ?></span>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get icon for current page
     */
    private function get_page_icon() {
        $icons = array(
            'vefify-quiz' => '📊',
            'vefify-campaigns' => '📋',
            'vefify-questions' => '❓',
            'vefify-participants' => '👥',
            'vefify-gifts' => '🎁',
            'vefify-analytics' => '📈',
            'vefify-settings' => '⚙️'
        );
        
        return $icons[$this->current_page] ?? '📊';
    }
    
    /**
     * Render module placeholder
     */
    private function render_module_placeholder($module_name, $message) {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($module_name); ?></h1>
            <div class="notice notice-info">
                <p><?php echo esc_html($message); ?></p>
                <p>
                    <strong><?php esc_html_e('Next Steps:', 'vefify-quiz'); ?></strong>
                    <?php esc_html_e('The module files are being loaded. Please ensure all module files are properly uploaded.', 'vefify-quiz'); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get current page slug
     */
    public function get_current_page() {
        return $this->current_page;
    }
    
    /**
     * Check if current page is a plugin page
     */
    public function is_plugin_page() {
        return strpos($this->current_page, 'vefify') === 0;
    }
    
    /**
     * Get navigation menu HTML
     */
    public function get_navigation_menu() {
        if (!$this->is_plugin_page()) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="vefify-nav-menu">
            <?php foreach ($this->pages['submenus'] as $slug => $page): ?>
                <a href="<?php echo admin_url('admin.php?page=' . $page['menu_slug']); ?>" 
                   class="nav-item <?php echo $this->current_page === $page['menu_slug'] ? 'active' : ''; ?>">
                    <?php echo esc_html($page['menu_title']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}