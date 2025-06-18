<?php
/**
 * Frontend Module Initialization
 * File: modules/frontend/class-frontend-module.php
 * 
 * Main frontend module class that ties everything together
 */

class Vefify_Frontend_Module {
    
    private static $instance = null;
    private $quiz;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Include required files
        $this->include_files();
        
        // Initialize components
        $this->quiz = Vefify_Frontend_Quiz::get_instance();
        
        // Add hooks
        add_action('init', array($this, 'init_frontend'));
        add_action('wp_head', array($this, 'add_meta_tags'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    private function include_files() {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/frontend/class-frontend-quiz.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/participants/class-participant-model.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'includes/class-utilities.php';
    }
    
    public function init_frontend() {
        // Create necessary directories
        $this->create_directories();
        
        // Setup rewrite rules if needed
        $this->setup_rewrite_rules();
    }
    
    public function add_meta_tags() {
        if ($this->is_quiz_page()) {
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'vefify-quiz',
            'Frontend Preview',
            '👁️ Preview',
            'manage_options',
            'vefify-frontend-preview',
            array($this, 'render_preview_page')
        );
    }
    
    public function render_preview_page() {
        global $wpdb;
        
        // Get available campaigns
        $campaigns = $wpdb->get_results(
            "SELECT id, name, is_active FROM {$wpdb->prefix}vefify_campaigns ORDER BY created_at DESC"
        );
        
        ?>
        <div class="wrap">
            <h1>🎮 Frontend Quiz Preview</h1>
            
            <div class="notice notice-info">
                <p><strong>Preview Mode:</strong> Test how your quiz will look and behave for participants.</p>
            </div>
            
            <?php if (empty($campaigns)): ?>
                <div class="notice notice-warning">
                    <p>No campaigns found. <a href="<?php echo admin_url('admin.php?page=vefify-campaigns&action=new'); ?>">Create a campaign first</a>.</p>
                </div>
            <?php else: ?>
                
                <div class="preview-controls" style="background: #f1f1f1; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3>Preview Controls</h3>
                    
                    <form id="preview-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="preview-campaign">Campaign</label></th>
                                <td>
                                    <select id="preview-campaign" name="campaign_id">
                                        <?php foreach ($campaigns as $campaign): ?>
                                            <option value="<?php echo $campaign->id; ?>" <?php echo !$campaign->is_active ? 'style="color: #999;"' : ''; ?>>
                                                <?php echo esc_html($campaign->name); ?>
                                                <?php echo !$campaign->is_active ? ' (Inactive)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="preview-theme">Theme</label></th>
                                <td>
                                    <select id="preview-theme" name="theme">
                                        <option value="default">Default</option>
                                        <option value="modern">Modern</option>
                                        <option value="minimal">Minimal</option>
                                        <option value="colorful">Colorful</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="preview-progress">Show Progress</label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="preview-progress" name="show_progress" checked>
                                        Show progress bar during quiz
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <button type="button" class="button button-primary" onclick="generatePreview()">
                            🔄 Generate Preview
                        </button>
                        
                        <button type="button" class="button button-secondary" onclick="openInNewTab()">
                            🌐 Open in New Tab
                        </button>
                    </form>
                </div>
                
                <div class="preview-display">
                    <h3>Shortcode Preview</h3>
                    <div id="shortcode-display" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 20px;">
                        <code id="shortcode-text">[vefify_quiz campaign_id="<?php echo $campaigns[0]->id; ?>"]</code>
                        <button type="button" class="button button-small" onclick="copyShortcode()" style="margin-left: 10px;">
                            Copy Shortcode
                        </button>
                    </div>
                    
                    <div id="quiz-preview" style="border: 1px solid #ddd; min-height: 400px; background: #fff;">
                        <div style="text-align: center; padding: 40px; color: #666;">
                            Click "Generate Preview" to see how your quiz will look.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        function generatePreview() {
            const campaignId = document.getElementById('preview-campaign').value;
            const theme = document.getElementById('preview-theme').value;
            const showProgress = document.getElementById('preview-progress').checked;
            
            // Update shortcode display
            let shortcode = `[vefify_quiz campaign_id="${campaignId}"`;
            if (theme !== 'default') shortcode += ` theme="${theme}"`;
            if (!showProgress) shortcode += ` show_progress="false"`;
            shortcode += `]`;
            
            document.getElementById('shortcode-text').textContent = shortcode;
            
            // Generate preview (simplified)
            const preview = document.getElementById('quiz-preview');
            preview.innerHTML = `
                <div style="padding: 20px;">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <h2 style="color: #2c3e50;">Campaign Preview</h2>
                        <p style="color: #7f8c8d;">Theme: ${theme} | Progress: ${showProgress ? 'Enabled' : 'Disabled'}</p>
                    </div>
                    
                    <div style="max-width: 600px; margin: 0 auto; background: #f8f9fa; padding: 30px; border-radius: 8px;">
                        <h3>Registration Form Preview</h3>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Name *</label>
                            <input type="text" placeholder="Enter your full name" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Phone Number *</label>
                            <input type="tel" placeholder="0xxxxxxxxx" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Province *</label>
                            <select style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <option>Select province...</option>
                                <option>Ho Chi Minh City</option>
                                <option>Hanoi</option>
                            </select>
                        </div>
                        <button type="button" style="background: #0073aa; color: white; padding: 12px 24px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">
                            Start Quiz
                        </button>
                    </div>
                </div>
            `;
        }
        
        function copyShortcode() {
            const shortcode = document.getElementById('shortcode-text').textContent;
            navigator.clipboard.writeText(shortcode).then(() => {
                alert('Shortcode copied to clipboard!');
            });
        }
        
        function openInNewTab() {
            // This would open a preview page in a new tab
            alert('Preview in new tab functionality would be implemented here');
        }
        
        // Generate initial preview
        generatePreview();
        </script>
        <?php
    }
    
    private function create_directories() {
        $upload_dir = wp_upload_dir();
        $quiz_dir = $upload_dir['basedir'] . '/vefify-quiz';
        
        if (!file_exists($quiz_dir)) {
            wp_mkdir_p($quiz_dir);
        }
    }
    
    private function setup_rewrite_rules() {
        // Add custom rewrite rules if needed for quiz URLs
        // This is optional and can be implemented later
    }
    
    private function is_quiz_page() {
        global $post;
        return $post && has_shortcode($post->post_content, 'vefify_quiz');
    }
    
    public function get_quiz_instance() {
        return $this->quiz;
    }
}

// Initialize frontend module
Vefify_Frontend_Module::get_instance();