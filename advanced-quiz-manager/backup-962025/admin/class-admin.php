<?php
/**
 * Admin Interface for Advanced Quiz Manager
 * File: admin/class-admin.php
 */

class AQM_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_aqm_save_campaign', array($this, 'save_campaign'));
        add_action('wp_ajax_aqm_delete_campaign', array($this, 'delete_campaign'));
        add_action('wp_ajax_aqm_save_question', array($this, 'save_question'));
        add_action('wp_ajax_aqm_delete_question', array($this, 'delete_question'));
        add_action('wp_ajax_aqm_save_gift', array($this, 'save_gift'));
        add_action('wp_ajax_aqm_get_analytics_data', array($this, 'get_analytics_data'));
        add_action('wp_ajax_aqm_export_responses', array($this, 'export_responses'));
        add_action('wp_ajax_aqm_import_provinces_json', array($this, 'import_provinces_json'));
    }
    
    public function admin_init() {
        // Register settings
        register_setting('aqm_settings', 'aqm_general_settings');
        register_setting('aqm_settings', 'aqm_notification_settings');
        register_setting('aqm_settings', 'aqm_provinces_data');
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'Quiz Manager',
            'Quiz Manager',
            'manage_options',
            'quiz-manager',
            array($this, 'admin_dashboard_page'),
            'dashicons-feedback',
            30
        );
		
		    // ADD THESE NEW MENU ITEMS:
		add_submenu_page(
			'quiz-manager',
			'Question Management',
			'Question Management',
			'manage_options', 
			'quiz-manager-questions',
			array($this, 'questions_page_redirect')
		);
		
		add_submenu_page(
			'quiz-manager',
			'Gift Management', 
			'Gift Management',
			'manage_options',
			'quiz-manager-gifts', 
			array($this, 'gifts_page_redirect')
		);
	}
		
        // Submenu pages
        add_submenu_page(
            'quiz-manager',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'quiz-manager',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Campaigns',
            'Campaigns',
            'manage_options',
            'quiz-manager-campaigns',
            array($this, 'campaigns_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Questions',
            'Questions',
            'manage_options',
            'quiz-manager-questions',
            array($this, 'questions_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Responses',
            'Responses',
            'manage_options',
            'quiz-manager-responses',
            array($this, 'responses_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Gifts & Rewards',
            'Gifts & Rewards',
            'manage_options',
            'quiz-manager-gifts',
            array($this, 'gifts_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Analytics',
            'Analytics',
            'manage_options',
            'quiz-manager-analytics',
            array($this, 'analytics_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Provinces Data',
            'Provinces Data',
            'manage_options',
            'quiz-manager-provinces',
            array($this, 'provinces_page')
        );
        
        add_submenu_page(
            'quiz-manager',
            'Settings',
            'Settings',
            'manage_options',
            'quiz-manager-settings',
            array($this, 'settings_page')
        );
    }
    
    public function dashboard_page() {
        $db = new AQM_Database();
        $overview_stats = $this->get_overview_stats();
        ?>
        <div class="wrap aqm-admin-page">
            <h1>Quiz Manager Dashboard</h1>
            
            <!-- Stats Overview -->
            <div class="aqm-stats-grid">
                <div class="aqm-stat-card">
                    <div class="aqm-stat-icon">🎯</div>
                    <div class="aqm-stat-content">
                        <h3><?php echo esc_html($overview_stats['total_campaigns']); ?></h3>
                        <p>Total Campaigns</p>
                    </div>
                </div>
                
                <div class="aqm-stat-card">
                    <div class="aqm-stat-icon">👥</div>
                    <div class="aqm-stat-content">
                        <h3><?php echo esc_html($overview_stats['total_responses']); ?></h3>
                        <p>Total Responses</p>
                    </div>
                </div>
                
                <div class="aqm-stat-card">
                    <div class="aqm-stat-icon">✅</div>
                    <div class="aqm-stat-content">
                        <h3><?php echo esc_html($overview_stats['completion_rate']); ?>%</h3>
                        <p>Completion Rate</p>
                    </div>
                </div>
                
                <div class="aqm-stat-card">
                    <div class="aqm-stat-icon">🎁</div>
                    <div class="aqm-stat-content">
                        <h3><?php echo esc_html($overview_stats['gifts_awarded']); ?></h3>
                        <p>Gifts Awarded</p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="aqm-dashboard-widgets">
                <div class="aqm-widget">
                    <h3>Recent Campaigns</h3>
                    <?php $this->render_recent_campaigns(); ?>
                </div>
                
                <div class="aqm-widget">
                    <h3>Today's Activity</h3>
                    <?php $this->render_todays_activity(); ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="aqm-quick-actions">
                <h3>Quick Actions</h3>
                <div class="aqm-action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=new'); ?>" class="button button-primary">
                        <span class="dashicons dashicons-plus"></span> Create New Campaign
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-provinces'); ?>" class="button">
                        <span class="dashicons dashicons-location"></span> Import Provinces Data
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-analytics'); ?>" class="button">
                        <span class="dashicons dashicons-chart-bar"></span> View Analytics
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function campaigns_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'new':
            case 'edit':
                $this->render_campaign_form($action);
                break;
            default:
                $this->render_campaigns_list();
                break;
        }
    }
    
    private function render_campaigns_list() {
        $db = new AQM_Database();
        $campaigns = $db->get_campaigns();
        ?>
        <div class="wrap aqm-admin-page">
            <h1>
                Quiz Campaigns 
                <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=new'); ?>" class="page-title-action">Add New</a>
            </h1>
            
            <div class="aqm-campaigns-table-wrapper">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Responses</th>
                            <th>Completion Rate</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($campaigns)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    <div class="aqm-empty-state">
                                        <span class="dashicons dashicons-feedback" style="font-size: 48px; color: #ddd;"></span>
                                        <h3>No campaigns yet</h3>
                                        <p>Create your first quiz campaign to get started!</p>
                                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=new'); ?>" class="button button-primary">Create Campaign</a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($campaigns as $campaign): ?>
                                <?php
                                $stats = $db->get_campaign_stats($campaign->id);
                                $status_class = $campaign->status === 'active' ? 'active' : ($campaign->status === 'inactive' ? 'inactive' : 'draft');
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=edit&id=' . $campaign->id); ?>">
                                                <?php echo esc_html($campaign->title); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=edit&id=' . $campaign->id); ?>">Edit</a> |
                                            </span>
                                            <span class="view">
                                                <a href="<?php echo home_url('/?quiz_preview=' . $campaign->id); ?>" target="_blank">Preview</a> |
                                            </span>
                                            <span class="delete">
                                                <a href="#" class="aqm-delete-campaign" data-id="<?php echo $campaign->id; ?>" style="color: #a00;">Delete</a>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="aqm-status aqm-status-<?php echo esc_attr($status_class); ?>">
                                            <?php echo esc_html(ucfirst($campaign->status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($campaign->start_date ? date('M j, Y', strtotime($campaign->start_date)) : '-'); ?></td>
                                    <td><?php echo esc_html($campaign->end_date ? date('M j, Y', strtotime($campaign->end_date)) : '-'); ?></td>
                                    <td><?php echo esc_html($stats['total_participants']); ?></td>
                                    <td><?php echo esc_html($stats['completion_rate']); ?>%</td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-questions&campaign_id=' . $campaign->id); ?>" class="button button-small">Questions</a>
                                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-responses&campaign_id=' . $campaign->id); ?>" class="button button-small">Responses</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    private function render_campaign_form($action) {
        $campaign_id = $action === 'edit' ? intval($_GET['id']) : 0;
        $campaign = null;
        
        if ($campaign_id) {
            $db = new AQM_Database();
            $campaign = $db->get_campaign($campaign_id);
        }
        
        $title = $action === 'edit' ? 'Edit Campaign' : 'Create New Campaign';
        ?>
        <div class="wrap aqm-admin-page">
            <h1><?php echo esc_html($title); ?></h1>
            
            <form id="aqm-campaign-form" class="aqm-form">
                <?php wp_nonce_field('aqm_save_campaign', 'aqm_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>">
                
                <div class="aqm-form-section">
                    <h3>Campaign Details</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="campaign_title">Campaign Title *</label></th>
                            <td>
                                <input type="text" id="campaign_title" name="title" class="regular-text" 
                                       value="<?php echo esc_attr($campaign ? $campaign->title : ''); ?>" required>
                                <p class="description">Enter a descriptive title for your quiz campaign.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="campaign_description">Description</label></th>
                            <td>
                                <textarea id="campaign_description" name="description" class="large-text" rows="4"><?php echo esc_textarea($campaign ? $campaign->description : ''); ?></textarea>
                                <p class="description">Provide a brief description of the quiz.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="campaign_status">Status</label></th>
                            <td>
                                <select id="campaign_status" name="status">
                                    <option value="draft" <?php selected($campaign ? $campaign->status : 'draft', 'draft'); ?>>Draft</option>
                                    <option value="active" <?php selected($campaign ? $campaign->status : '', 'active'); ?>>Active</option>
                                    <option value="inactive" <?php selected($campaign ? $campaign->status : '', 'inactive'); ?>>Inactive</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="aqm-form-section">
                    <h3>Schedule & Limits</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="start_date">Start Date</label></th>
                            <td>
                                <input type="datetime-local" id="start_date" name="start_date" 
                                       value="<?php echo esc_attr($campaign && $campaign->start_date ? date('Y-m-d\TH:i', strtotime($campaign->start_date)) : ''); ?>">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="end_date">End Date</label></th>
                            <td>
                                <input type="datetime-local" id="end_date" name="end_date" 
                                       value="<?php echo esc_attr($campaign && $campaign->end_date ? date('Y-m-d\TH:i', strtotime($campaign->end_date)) : ''); ?>">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="max_participants">Maximum Participants</label></th>
                            <td>
                                <input type="number" id="max_participants" name="max_participants" 
                                       value="<?php echo esc_attr($campaign ? $campaign->max_participants : 0); ?>" min="0">
                                <p class="description">Set to 0 for unlimited participants.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="aqm-form-section">
                    <h3>Quiz Settings</h3>
                    
                    <?php
                    $settings = $campaign ? json_decode($campaign->settings, true) : array();
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Quiz Options</th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="settings[allow_multiple_attempts]" value="1" 
                                               <?php checked(isset($settings['allow_multiple_attempts']) ? $settings['allow_multiple_attempts'] : false, 1); ?>>
                                        Allow multiple attempts
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="settings[show_results_immediately]" value="1" 
                                               <?php checked(isset($settings['show_results_immediately']) ? $settings['show_results_immediately'] : true, 1); ?>>
                                        Show results immediately after submission
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="settings[collect_email]" value="1" 
                                               <?php checked(isset($settings['collect_email']) ? $settings['collect_email'] : true, 1); ?>>
                                        Collect participant email addresses
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="settings[require_login]" value="1" 
                                               <?php checked(isset($settings['require_login']) ? $settings['require_login'] : false, 1); ?>>
                                        Require user login to participate
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="settings[randomize_questions]" value="1" 
                                               <?php checked(isset($settings['randomize_questions']) ? $settings['randomize_questions'] : false, 1); ?>>
                                        Randomize question order
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="settings[enable_timer]" value="1" 
                                               <?php checked(isset($settings['enable_timer']) ? $settings['enable_timer'] : false, 1); ?>>
                                        Enable quiz timer
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr class="aqm-timer-settings" style="display: <?php echo isset($settings['enable_timer']) && $settings['enable_timer'] ? 'table-row' : 'none'; ?>;">
                            <th scope="row"><label for="timer_minutes">Timer Duration (minutes)</label></th>
                            <td>
                                <input type="number" id="timer_minutes" name="settings[timer_minutes]" 
                                       value="<?php echo esc_attr(isset($settings['timer_minutes']) ? $settings['timer_minutes'] : 30); ?>" min="1" max="180">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php echo $action === 'edit' ? 'Update Campaign' : 'Create Campaign'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Show/hide timer settings
            $('input[name="settings[enable_timer]"]').change(function() {
                $('.aqm-timer-settings').toggle(this.checked);
            });
            
            // Handle form submission
            $('#aqm-campaign-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'aqm_save_campaign');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            window.location.href = '<?php echo admin_url('admin.php?page=quiz-manager-campaigns'); ?>';
                        } else {
                            alert('Error: ' + (response.data.message || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Network error. Please try again.');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function provinces_page() {
        ?>
        <div class="wrap aqm-admin-page">
            <h1>Vietnamese Provinces Data Management</h1>
            
            <div class="aqm-provinces-section">
                <div class="aqm-card">
                    <h3>Import Provinces JSON Data</h3>
                    <p>Upload or paste your Vietnamese provinces JSON data to update the system database.</p>
                    
                    <form id="aqm-provinces-import-form" enctype="multipart/form-data">
                        <?php wp_nonce_field('aqm_import_provinces', 'aqm_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="provinces_file">Upload JSON File</label></th>
                                <td>
                                    <input type="file" id="provinces_file" name="provinces_file" accept=".json">
                                    <p class="description">Select a JSON file containing provinces, districts, and wards data.</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="provinces_json">Or Paste JSON Data</label></th>
                                <td>
                                    <textarea id="provinces_json" name="provinces_json" class="large-text code" rows="15" placeholder='[
  {
    "code": "01",
    "name": "Hà Nội",
    "name_en": "Hanoi",
    "full_name": "Thành phố Hà Nội",
    "districts": [
      {
        "code": "001",
        "name": "Ba Đình",
        "name_en": "Ba Dinh",
        "wards": [...]
      }
    ]
  }
]'></textarea>
                                    <p class="description">Paste your JSON data directly into this field.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="Import Data">
                            <button type="button" id="aqm-validate-json" class="button">Validate JSON</button>
                        </p>
                    </form>
                </div>
                
                <div class="aqm-card">
                    <h3>Current Data Statistics</h3>
                    <?php $this->render_provinces_stats(); ?>
                </div>
                
                <div class="aqm-card">
                    <h3>Sample JSON Structure</h3>
                    <pre class="aqm-code-sample">[
  {
    "code": "01",
    "name": "Hà Nội",
    "name_en": "Hanoi",
    "full_name": "Thành phố Hà Nội",
    "full_name_en": "Hanoi City",
    "code_name": "ha_noi",
    "districts": [
      {
        "code": "001",
        "name": "Ba Đình",
        "name_en": "Ba Dinh",
        "full_name": "Quận Ba Đình",
        "wards": [
          {
            "code": "00001",
            "name": "Phúc Xá",
            "name_en": "Phuc Xa",
            "full_name": "Phường Phúc Xá"
          }
        ]
      }
    ]
  }
]</pre>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#aqm-validate-json').on('click', function() {
                const jsonData = $('#provinces_json').val();
                if (!jsonData.trim()) {
                    alert('Please enter JSON data to validate.');
                    return;
                }
                
                try {
                    const parsed = JSON.parse(jsonData);
                    if (Array.isArray(parsed)) {
                        alert('✅ Valid JSON structure! Found ' + parsed.length + ' provinces.');
                    } else {
                        alert('❌ JSON should be an array of provinces.');
                    }
                } catch (e) {
                    alert('❌ Invalid JSON: ' + e.message);
                }
            });
            
            $('#aqm-provinces-import-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'aqm_import_provinces_json');
                
                const submitBtn = $(this).find('input[type="submit"]');
                submitBtn.prop('disabled', true).val('Importing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('✅ Data imported successfully!\n\n' + response.data.message);
                            location.reload();
                        } else {
                            alert('❌ Import failed: ' + (response.data.message || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('❌ Network error. Please try again.');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).val('Import Data');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    // AJAX Handlers
    public function save_campaign() {
        check_ajax_referer('aqm_save_campaign', 'aqm_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_textarea_field($_POST['description']);
        $status = sanitize_text_field($_POST['status']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $max_participants = intval($_POST['max_participants']);
        
        // Process settings
        $settings = array();
        if (isset($_POST['settings']) && is_array($_POST['settings'])) {
            foreach ($_POST['settings'] as $key => $value) {
                $settings[sanitize_key($key)] = sanitize_text_field($value);
            }
        }
        
        $data = array(
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'start_date' => $start_date ? date('Y-m-d H:i:s', strtotime($start_date)) : null,
            'end_date' => $end_date ? date('Y-m-d H:i:s', strtotime($end_date)) : null,
            'max_participants' => $max_participants,
            'settings' => json_encode($settings),
            'created_by' => get_current_user_id()
        );
        
        global $wpdb;
        
        if ($campaign_id) {
            // Update existing campaign
            $result = $wpdb->update(
                $wpdb->prefix . 'aqm_campaigns',
                $data,
                array('id' => $campaign_id),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d'),
                array('%d')
            );
        } else {
            // Create new campaign
            $result = $wpdb->insert(
                $wpdb->prefix . 'aqm_campaigns',
                $data,
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d')
            );
            $campaign_id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Campaign saved successfully!',
                'campaign_id' => $campaign_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save campaign.'));
        }
    }
    
    public function import_provinces_json() {
        check_ajax_referer('aqm_import_provinces', 'aqm_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $json_data = '';
        
        // Check if file was uploaded
        if (isset($_FILES['provinces_file']) && $_FILES['provinces_file']['error'] === UPLOAD_ERR_OK) {
            $json_data = file_get_contents($_FILES['provinces_file']['tmp_name']);
        } elseif (!empty($_POST['provinces_json'])) {
            $json_data = stripslashes($_POST['provinces_json']);
        }
        
        if (empty($json_data)) {
            wp_send_json_error(array('message' => 'No JSON data provided.'));
        }
        
        // Validate JSON
        $provinces_data = json_decode($json_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => 'Invalid JSON format: ' . json_last_error_msg()));
        }
        
        if (!is_array($provinces_data)) {
            wp_send_json_error(array('message' => 'JSON data should be an array of provinces.'));
        }
        
        // Import data to database
        $result = $this->import_provinces_to_database($provinces_data);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    private function import_provinces_to_database($provinces_data) {
        global $wpdb;
        
        $provinces_imported = 0;
        $districts_imported = 0;
        $wards_imported = 0;
        
        // Clear existing data
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aqm_provinces");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aqm_districts");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aqm_wards");
        
        foreach ($provinces_data as $province) {
            // Insert province
            $province_result = $wpdb->insert(
                $wpdb->prefix . 'aqm_provinces',
                array(
                    'code' => sanitize_text_field($province['code']),
                    'name' => sanitize_text_field($province['name']),
                    'name_en' => sanitize_text_field($province['name_en'] ?? ''),
                    'full_name' => sanitize_text_field($province['full_name'] ?? ''),
                    'full_name_en' => sanitize_text_field($province['full_name_en'] ?? ''),
                    'code_name' => sanitize_text_field($province['code_name'] ?? '')
                )
            );
            
            if ($province_result) {
                $provinces_imported++;
                
                // Insert districts
                if (isset($province['districts']) && is_array($province['districts'])) {
                    foreach ($province['districts'] as $district) {
                        $district_result = $wpdb->insert(
                            $wpdb->prefix . 'aqm_districts',
                            array(
                                'code' => sanitize_text_field($district['code']),
                                'name' => sanitize_text_field($district['name']),
                                'name_en' => sanitize_text_field($district['name_en'] ?? ''),
                                'full_name' => sanitize_text_field($district['full_name'] ?? ''),
                                'province_code' => sanitize_text_field($province['code'])
                            )
                        );
                        
                        if ($district_result) {
                            $districts_imported++;
                            
                            // Insert wards
                            if (isset($district['wards']) && is_array($district['wards'])) {
                                foreach ($district['wards'] as $ward) {
                                    $ward_result = $wpdb->insert(
                                        $wpdb->prefix . 'aqm_wards',
                                        array(
                                            'code' => sanitize_text_field($ward['code']),
                                            'name' => sanitize_text_field($ward['name']),
                                            'name_en' => sanitize_text_field($ward['name_en'] ?? ''),
                                            'full_name' => sanitize_text_field($ward['full_name'] ?? ''),
                                            'district_code' => sanitize_text_field($district['code'])
                                        )
                                    );
                                    
                                    if ($ward_result) {
                                        $wards_imported++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return array(
            'success' => true,
            'message' => sprintf(
                'Successfully imported %d provinces, %d districts, and %d wards.',
                $provinces_imported,
                $districts_imported,
                $wards_imported
            ),
            'stats' => array(
                'provinces' => $provinces_imported,
                'districts' => $districts_imported,
                'wards' => $wards_imported
            )
        );
    }
    
    private function get_overview_stats() {
        global $wpdb;
        
        $total_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_campaigns");
        $total_responses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses");
        $completed_responses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE status = 'completed'");
        $gifts_awarded = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_gift_awards");
        
        $completion_rate = $total_responses > 0 ? round(($completed_responses / $total_responses) * 100, 1) : 0;
        
        return array(
            'total_campaigns' => $total_campaigns,
            'total_responses' => $total_responses,
            'completion_rate' => $completion_rate,
            'gifts_awarded' => $gifts_awarded
        );
    }
    
    private function render_recent_campaigns() {
        global $wpdb;
        
        $recent_campaigns = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}aqm_campaigns ORDER BY created_at DESC LIMIT 5"
        );
        
        if (empty($recent_campaigns)) {
            echo '<p>No campaigns yet.</p>';
            return;
        }
        
        echo '<ul class="aqm-recent-list">';
        foreach ($recent_campaigns as $campaign) {
            $status_class = 'aqm-status-' . $campaign->status;
            echo '<li>';
            echo '<a href="' . admin_url('admin.php?page=quiz-manager-campaigns&action=edit&id=' . $campaign->id) . '">';
            echo esc_html($campaign->title);
            echo '</a>';
            echo '<span class="aqm-status ' . esc_attr($status_class) . '">' . esc_html(ucfirst($campaign->status)) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    private function render_todays_activity() {
        global $wpdb;
        
        $today = date('Y-m-d');
        $todays_responses = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE DATE(submitted_at) = %s",
            $today
        ));
        
        $todays_completions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_responses WHERE DATE(submitted_at) = %s AND status = 'completed'",
            $today
        ));
        
        echo '<div class="aqm-activity-stats">';
        echo '<div class="aqm-activity-item">';
        echo '<strong>' . esc_html($todays_responses) . '</strong>';
        echo '<span>New Responses</span>';
        echo '</div>';
        echo '<div class="aqm-activity-item">';
        echo '<strong>' . esc_html($todays_completions) . '</strong>';
        echo '<span>Completions</span>';
        echo '</div>';
        echo '</div>';
    }
    
    private function render_provinces_stats() {
        global $wpdb;
        
        $provinces_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_provinces");
        $districts_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_districts");
        $wards_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_wards");
        
        echo '<div class="aqm-provinces-stats">';
        echo '<div class="aqm-stat-item">';
        echo '<strong>' . esc_html($provinces_count) . '</strong>';
        echo '<span>Provinces/Cities</span>';
        echo '</div>';
        echo '<div class="aqm-stat-item">';
        echo '<strong>' . esc_html($districts_count) . '</strong>';
        echo '<span>Districts</span>';
        echo '</div>';
        echo '<div class="aqm-stat-item">';
        echo '<strong>' . esc_html($wards_count) . '</strong>';
        echo '<span>Wards/Communes</span>';
        echo '</div>';
        echo '</div>';
    }
    
    // Placeholder methods for other pages
    public function questions_page() {
        echo '<div class="wrap"><h1>Question Management</h1><p>Question management interface will be here.</p></div>';
    }
    
    public function responses_page() {
        echo '<div class="wrap"><h1>Response Management</h1><p>Response viewing and export interface will be here.</p></div>';
    }
    
    public function gifts_page() {
        echo '<div class="wrap"><h1>Gift Management</h1><p>Gift and reward management interface will be here.</p></div>';
    }
    
    public function analytics_page() {
        echo '<div class="wrap"><h1>Analytics Dashboard</h1><p>Analytics charts and reports will be here.</p></div>';
    }
    
    public function settings_page() {
        echo '<div class="wrap"><h1>Quiz Settings</h1><p>Global plugin settings will be here.</p></div>';
    }
	
	// ADD THESE NEW METHODS:
	public function questions_page_redirect() {
		wp_redirect(admin_url('admin.php?page=quiz-manager-questions'));
	}

	public function gifts_page_redirect() {
		wp_redirect(admin_url('admin.php?page=quiz-manager-gifts'));
	}
	
}
?>