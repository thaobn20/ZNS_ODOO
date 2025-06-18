<?php
if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Form_Settings {
    
    public function __construct() {
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_menu', array($this, 'add_settings_menu'));
        add_action('admin_notices', array($this, 'show_success_notice'));
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            'vefify_quiz_settings_group',
            'vefify_quiz_settings',
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Add settings menu
     */
    public function add_settings_menu() {
        add_submenu_page(
            'vefify-quiz',
            'Quiz Settings',
            '⚙️ Settings',
            'manage_options',
            'vefify-quiz-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Show success notice
     */
    public function show_success_notice() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] && 
            isset($_GET['page']) && $_GET['page'] === 'vefify-quiz-settings') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Settings saved successfully!</strong></p>';
            echo '</div>';
        }
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Get current settings
        $settings = get_option('vefify_quiz_settings', array());
        
        // Default values
        $defaults = array(
            'show_province' => 1,
            'require_province' => 1,
            'show_pharmacist_code' => 1,
            'require_pharmacist_code' => 0,
            'show_email' => 1,
            'require_email' => 1,
            'show_company' => 1,
            'require_company' => 0,
            'enable_retakes' => 0,
            'show_progress' => 1,
            'randomize_questions' => 1,
            'quiz_theme' => 'default'
        );
        
        $settings = wp_parse_args($settings, $defaults);
        ?>
        
        <div class="wrap">
            <h1>🎯 Vefify Quiz Settings</h1>
            
            <div class="settings-info">
                <p>Configure your quiz form fields and behavior settings.</p>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('vefify_quiz_settings_group');
                do_settings_sections('vefify_quiz_settings_group');
                ?>
                
                <table class="form-table">
                    <tbody>
                        <!-- Province Settings -->
                        <tr>
                            <th scope="row">
                                <label>Province Field</label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="vefify_quiz_settings[show_province]" value="1" 
                                               <?php checked(1, $settings['show_province']); ?>>
                                        Show Province/City field
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="vefify_quiz_settings[require_province]" value="1"
                                               <?php checked(1, $settings['require_province']); ?>>
                                        Make Province required
                                    </label>
                                </fieldset>
                                <p class="description">Province/City selection for Vietnamese locations</p>
                            </td>
                        </tr>
                        
                        <!-- Pharmacist Code Settings -->
                        <tr>
                            <th scope="row">
                                <label>Pharmacist Code</label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="vefify_quiz_settings[show_pharmacist_code]" value="1"
                                               <?php checked(1, $settings['show_pharmacist_code']); ?>>
                                        Show Pharmacist Code field
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="vefify_quiz_settings[require_pharmacist_code]" value="1"
                                               <?php checked(1, $settings['require_pharmacist_code']); ?>>
                                        Make Pharmacist Code required
                                    </label>
                                </fieldset>
                                <p class="description">Professional pharmacist license code (6-12 characters)</p>
                            </td>
                        </tr>
                        
                        <!-- Email Settings -->
                        <tr>
                            <th scope="row">
                                <label>Email Field</label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="vefify_quiz_settings[show_email]" value="1"
                                               <?php checked(1, $settings['show_email']); ?>>
                                        Show Email field
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="vefify_quiz_settings[require_email]" value="1"
                                               <?php checked(1, $settings['require_email']); ?>>
                                        Make Email required
                                    </label>
                                </fieldset>
                                <p class="description">Email address for notifications and results</p>
                            </td>
                        </tr>
                        
                        <!-- Company Settings -->
                        <tr>
                            <th scope="row">
                                <label>Company Field</label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="vefify_quiz_settings[show_company]" value="1"
                                               <?php checked(1, $settings['show_company']); ?>>
                                        Show Company/Organization field
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="vefify_quiz_settings[require_company]" value="1"
                                               <?php checked(1, $settings['require_company']); ?>>
                                        Make Company required
                                    </label>
                                </fieldset>
                                <p class="description">Workplace or organization information</p>
                            </td>
                        </tr>
                        
                        <!-- Quiz Behavior -->
                        <tr>
                            <th scope="row">
                                <label>Quiz Behavior</label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="vefify_quiz_settings[enable_retakes]" value="1"
                                               <?php checked(1, $settings['enable_retakes']); ?>>
                                        Allow participants to retake quiz
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="vefify_quiz_settings[show_progress]" value="1"
                                               <?php checked(1, $settings['show_progress']); ?>>
                                        Show progress bar during quiz
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="vefify_quiz_settings[randomize_questions]" value="1"
                                               <?php checked(1, $settings['randomize_questions']); ?>>
                                        Randomize question order
                                    </label>
                                </fieldset>
                                <p class="description">Configure quiz behavior and user experience</p>
                            </td>
                        </tr>
                        
                        <!-- Theme Selection -->
                        <tr>
                            <th scope="row">
                                <label for="quiz_theme">Quiz Theme</label>
                            </th>
                            <td>
                                <select name="vefify_quiz_settings[quiz_theme]" id="quiz_theme">
                                    <option value="default" <?php selected('default', $settings['quiz_theme']); ?>>Default</option>
                                    <option value="modern" <?php selected('modern', $settings['quiz_theme']); ?>>Modern</option>
                                    <option value="minimal" <?php selected('minimal', $settings['quiz_theme']); ?>>Minimal</option>
                                    <option value="colorful" <?php selected('colorful', $settings['quiz_theme']); ?>>Colorful</option>
                                </select>
                                <p class="description">Choose the visual theme for your quiz forms</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <!-- Settings Preview -->
            <div class="settings-preview">
                <h2>🔍 Settings Preview</h2>
                <div class="preview-box">
                    <h3>Current Configuration:</h3>
                    <ul>
                        <li><strong>Province Field:</strong> 
                            <?php echo $settings['show_province'] ? '✅ Shown' : '❌ Hidden'; ?>
                            <?php echo $settings['require_province'] ? ' (Required)' : ' (Optional)'; ?>
                        </li>
                        <li><strong>Pharmacist Code:</strong> 
                            <?php echo $settings['show_pharmacist_code'] ? '✅ Shown' : '❌ Hidden'; ?>
                            <?php echo $settings['require_pharmacist_code'] ? ' (Required)' : ' (Optional)'; ?>
                        </li>
                        <li><strong>Email Field:</strong> 
                            <?php echo $settings['show_email'] ? '✅ Shown' : '❌ Hidden'; ?>
                            <?php echo $settings['require_email'] ? ' (Required)' : ' (Optional)'; ?>
                        </li>
                        <li><strong>Company Field:</strong> 
                            <?php echo $settings['show_company'] ? '✅ Shown' : '❌ Hidden'; ?>
                            <?php echo $settings['require_company'] ? ' (Required)' : ' (Optional)'; ?>
                        </li>
                        <li><strong>Retakes:</strong> <?php echo $settings['enable_retakes'] ? '✅ Allowed' : '❌ Not Allowed'; ?></li>
                        <li><strong>Progress Bar:</strong> <?php echo $settings['show_progress'] ? '✅ Shown' : '❌ Hidden'; ?></li>
                        <li><strong>Question Order:</strong> <?php echo $settings['randomize_questions'] ? '🔀 Randomized' : '📋 Fixed'; ?></li>
                        <li><strong>Theme:</strong> <?php echo ucfirst($settings['quiz_theme']); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <style>
        .settings-info {
            background: #e7f3ff;
            border-left: 4px solid #0073aa;
            padding: 15px;
            margin: 20px 0;
        }
        
        .form-table th {
            width: 200px;
            vertical-align: top;
            padding-top: 15px;
        }
        
        .form-table fieldset {
            margin: 0;
        }
        
        .form-table fieldset label {
            display: block;
            margin-bottom: 8px;
            font-weight: normal;
        }
        
        .form-table .description {
            margin-top: 10px;
            font-style: italic;
            color: #666;
        }
        
        .settings-preview {
            margin-top: 40px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }
        
        .preview-box {
            background: #fff;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #00a32a;
        }
        
        .preview-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .preview-box li {
            margin-bottom: 5px;
        }
        </style>
        <?php
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($settings) {
        $sanitized = array();
        
        // Checkboxes - ensure they're 1 or 0
        $checkboxes = array(
            'show_province', 'require_province',
            'show_pharmacist_code', 'require_pharmacist_code',
            'show_email', 'require_email',
            'show_company', 'require_company',
            'enable_retakes', 'show_progress', 'randomize_questions'
        );
        
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = isset($settings[$checkbox]) ? 1 : 0;
        }
        
        // Theme selection
        $sanitized['quiz_theme'] = sanitize_text_field($settings['quiz_theme'] ?? 'default');
        
        return $sanitized;
    }
}