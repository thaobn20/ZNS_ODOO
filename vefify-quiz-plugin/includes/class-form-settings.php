<?php
/**
 * Form Settings Admin Page
 * File: admin/form-settings.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Form_Settings_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('wp_ajax_vefify_form_preview', array($this, 'ajax_form_preview'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'vefify-dashboard',
            'Form Settings',
            '📝 Form Settings',
            'manage_options',
            'vefify-form-settings',
            array($this, 'display_settings_page')
        );
    }
    
    public function init_settings() {
        register_setting('vefify_form_settings', 'vefify_form_config');
        
        // Field visibility settings
        add_settings_section(
            'vefify_form_fields',
            'Form Field Configuration',
            array($this, 'fields_section_callback'),
            'vefify_form_settings'
        );
        
        // Form behavior settings
        add_settings_section(
            'vefify_form_behavior', 
            'Form Behavior Settings',
            array($this, 'behavior_section_callback'),
            'vefify_form_settings'
        );
        
        // Gift display settings
        add_settings_section(
            'vefify_gift_display',
            'Gift Display Settings', 
            array($this, 'gift_section_callback'),
            'vefify_form_settings'
        );
    }
    
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1>📝 Quiz Form Settings</h1>
            <div class="vefify-settings-container">
                <div class="settings-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('vefify_form_settings');
                        do_settings_sections('vefify_form_settings');
                        ?>
                        
                        <div class="form-field-settings">
                            <h2>📋 Form Field Configuration</h2>
                            
                            <!-- Pharmacist Code Settings -->
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Pharmacist Code Field</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="vefify_form_config[show_pharmacist_code]" value="1" 
                                                   <?php checked(1, $this->get_option('show_pharmacist_code', 1)); ?>>
                                            Show Pharmacist Code field
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox" name="vefify_form_config[require_pharmacist_code]" value="1"
                                                   <?php checked(1, $this->get_option('require_pharmacist_code', 0)); ?>>
                                            Make Pharmacist Code required
                                        </label>
                                        <p class="description">Pharmacist license code (6-12 alphanumeric characters)</p>
                                    </td>
                                </tr>
                                
                                <!-- Email Settings -->
                                <tr>
                                    <th scope="row">Email Field</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="vefify_form_config[show_email]" value="1"
                                                   <?php checked(1, $this->get_option('show_email', 1)); ?>>
                                            Show Email field
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox" name="vefify_form_config[require_email]" value="1"
                                                   <?php checked(1, $this->get_option('require_email', 0)); ?>>
                                            Make Email required
                                        </label>
                                        <p class="description">For quiz results and notifications</p>
                                    </td>
                                </tr>
                                
                                <!-- District Selection -->
                                <tr>
                                    <th scope="row">Province Selection</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="vefify_form_config[enable_district_selection]" value="1"
                                                   <?php checked(1, $this->get_option('enable_district_selection', 1)); ?>>
                                            Enable District selection (2-level: Province → District)
                                        </label>
                                        <p class="description">Users can select both province and district</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <h2>🎁 Gift Display Settings</h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Show Gift Preview</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="vefify_form_config[show_gift_preview]" value="1"
                                                   <?php checked(1, $this->get_option('show_gift_preview', 1)); ?>>
                                            Show available gifts before quiz starts
                                        </label>
                                        <p class="description">Display gift information to motivate participants</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Gift Preview Text</th>
                                    <td>
                                        <textarea name="vefify_form_config[gift_preview_text]" rows="3" cols="50" class="large-text"><?php 
                                            echo esc_textarea($this->get_option('gift_preview_text', 'Complete the quiz to win exciting prizes!')); 
                                        ?></textarea>
                                        <p class="description">Text shown above gift preview</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <h2>⚙️ Form Behavior</h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Real-time Validation</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="vefify_form_config[enable_realtime_validation]" value="1"
                                                   <?php checked(1, $this->get_option('enable_realtime_validation', 1)); ?>>
                                            Enable real-time form validation
                                        </label>
                                        <p class="description">Validate fields as user types (improves UX)</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Phone Uniqueness Check</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="vefify_form_config[check_phone_uniqueness]" value="1"
                                                   <?php checked(1, $this->get_option('check_phone_uniqueness', 1)); ?>>
                                            Check phone number uniqueness per campaign
                                        </label>
                                        <p class="description">Prevent duplicate participation by phone number</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <?php submit_button('Save Form Settings'); ?>
                    </form>
                </div>
                
                <!-- Live Preview Sidebar -->
                <div class="settings-preview">
                    <h3>📱 Form Preview</h3>
                    <div id="form-preview-container">
                        <div class="preview-loading">Loading preview...</div>
                    </div>
                    <button type="button" class="button" id="refresh-preview">Refresh Preview</button>
                </div>
            </div>
        </div>
        
        <style>
        .vefify-settings-container {
            display: flex;
            gap: 20px;
        }
        .settings-main {
            flex: 2;
        }
        .settings-preview {
            flex: 1;
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            max-width: 400px;
        }
        #form-preview-container {
            border: 1px solid #ccc;
            padding: 15px;
            background: white;
            border-radius: 4px;
            min-height: 300px;
        }
        .form-field-settings h2 {
            border-top: 1px solid #ddd;
            padding-top: 20px;
            margin-top: 30px;
        }
        .form-field-settings h2:first-child {
            border-top: none;
            padding-top: 0;
            margin-top: 0;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Live preview updates
            $('#refresh-preview').click(function() {
                loadFormPreview();
            });
            
            // Auto-refresh preview when settings change
            $('input[name^="vefify_form_config"]').change(function() {
                setTimeout(loadFormPreview, 500);
            });
            
            function loadFormPreview() {
                $('#form-preview-container').html('<div class="preview-loading">Loading...</div>');
                
                var formData = $('form').serialize();
                
                $.post(ajaxurl, {
                    action: 'vefify_form_preview',
                    nonce: '<?php echo wp_create_nonce('vefify_form_preview'); ?>',
                    settings: formData
                }, function(response) {
                    if (response.success) {
                        $('#form-preview-container').html(response.data.html);
                    } else {
                        $('#form-preview-container').html('<div class="preview-error">Preview unavailable</div>');
                    }
                });
            }
            
            // Load initial preview
            loadFormPreview();
        });
        </script>
        <?php
    }
    
    public function fields_section_callback() {
        echo '<p>Configure which fields to show/hide and their requirements.</p>';
    }
    
    public function behavior_section_callback() {
        echo '<p>Control form validation and behavior settings.</p>';
    }
    
    public function gift_section_callback() {
        echo '<p>Configure how gifts are displayed to participants.</p>';
    }
    
    private function get_option($key, $default = '') {
        $options = get_option('vefify_form_config', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    public function ajax_form_preview() {
        check_ajax_referer('vefify_form_preview', 'nonce');
        
        // Generate preview HTML based on current settings
        ob_start();
        $this->render_form_preview();
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    private function render_form_preview() {
        $config = get_option('vefify_form_config', array());
        ?>
        <div class="vefify-form-preview">
            <div class="preview-form">
                <h4>Registration Form Preview</h4>
                
                <!-- Always show required fields -->
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" placeholder="Enter your full name" disabled>
                </div>
                
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" placeholder="0912345678" disabled>
                </div>
                
                <div class="form-group">
                    <label>Province/City *</label>
                    <select disabled>
                        <option>Select Province/City</option>
                        <option>Ho Chi Minh City</option>
                        <option>Hanoi</option>
                    </select>
                </div>
                
                <?php if (!empty($config['enable_district_selection'])): ?>
                <div class="form-group">
                    <label>District</label>
                    <select disabled>
                        <option>Select District</option>
                        <option>District 1</option>
                        <option>District 3</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($config['show_pharmacist_code'])): ?>
                <div class="form-group">
                    <label>Pharmacist Code <?php echo !empty($config['require_pharmacist_code']) ? '*' : ''; ?></label>
                    <input type="text" placeholder="PH123456" disabled>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($config['show_email'])): ?>
                <div class="form-group">
                    <label>Email <?php echo !empty($config['require_email']) ? '*' : ''; ?></label>
                    <input type="email" placeholder="your.email@example.com" disabled>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($config['show_gift_preview'])): ?>
                <div class="gift-preview">
                    <h5>🎁 Available Rewards</h5>
                    <p><?php echo esc_html($config['gift_preview_text'] ?? 'Complete the quiz to win exciting prizes!'); ?></p>
                    <div class="gift-list">
                        <span class="gift-badge">50K Voucher</span>
                        <span class="gift-badge">Health Discount</span>
                    </div>
                </div>
                <?php endif; ?>
                
                <button type="button" class="preview-button" disabled>Start Quiz</button>
            </div>
        </div>
        
        <style>
        .vefify-form-preview {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .preview-form {
            max-width: 100%;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 5px;
            color: #333;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background: #f9f9f9;
        }
        .gift-preview {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e6f3ff;
            margin: 15px 0;
        }
        .gift-preview h5 {
            margin: 0 0 8px 0;
            color: #0066cc;
        }
        .gift-list {
            margin-top: 10px;
        }
        .gift-badge {
            display: inline-block;
            background: #0066cc;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 8px;
            margin-bottom: 4px;
        }
        .preview-button {
            background: #0066cc;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            cursor: not-allowed;
            opacity: 0.7;
        }
        </style>
        <?php
    }
}

// Initialize the admin class
new Vefify_Form_Settings_Admin();