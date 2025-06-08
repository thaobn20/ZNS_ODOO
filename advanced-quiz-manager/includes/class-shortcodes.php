<?php
/**
 * Shortcodes Class for Advanced Quiz Manager
 * File: includes/class-shortcodes.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQM_Shortcodes {
    
    public function __construct() {
        add_shortcode('aqm_quiz', array($this, 'render_quiz_shortcode'));
        add_shortcode('aqm_quiz_mobile', array($this, 'render_mobile_quiz_shortcode'));
        add_shortcode('aqm_quiz_results', array($this, 'render_quiz_results_shortcode'));
        add_shortcode('aqm_gift_claim', array($this, 'render_gift_claim_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_shortcode_assets'));
    }
    
    public function enqueue_shortcode_assets() {
        // Only enqueue on pages that have our shortcodes
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'aqm_quiz')) {
            $this->enqueue_quiz_assets();
        }
    }
    
    private function enqueue_quiz_assets() {
        wp_enqueue_script('aqm-quiz-frontend', AQM_PLUGIN_URL . 'assets/js/quiz-frontend.js', array('jquery'), AQM_VERSION, true);
        wp_enqueue_style('aqm-quiz-frontend', AQM_PLUGIN_URL . 'assets/css/quiz-frontend.css', array(), AQM_VERSION);
        
        wp_localize_script('aqm-quiz-frontend', 'aqm_quiz_config', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aqm_front_nonce'),
            'messages' => array(
                'loading' => __('Loading...', 'advanced-quiz'),
                'error' => __('An error occurred. Please try again.', 'advanced-quiz'),
                'completed' => __('Quiz completed successfully!', 'advanced-quiz'),
                'gift_won' => __('Congratulations! You won a gift!', 'advanced-quiz'),
                'copy_success' => __('Gift code copied to clipboard!', 'advanced-quiz'),
                'share_success' => __('Results shared successfully!', 'advanced-quiz'),
                'required_field' => __('This field is required.', 'advanced-quiz'),
                'invalid_email' => __('Please enter a valid email address.', 'advanced-quiz'),
                'duplicate_submission' => __('You have already completed this quiz.', 'advanced-quiz')
            )
        ));
    }
    
    /**
     * Render quiz shortcode
     * Usage: [aqm_quiz campaign_id="1" theme="default" show_progress="true"]
     */
    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 1,
            'theme' => 'default',
            'show_progress' => 'true',
            'show_results' => 'true',
            'auto_start' => 'false',
            'redirect_url' => '',
            'css_class' => '',
            'width' => '100%',
            'height' => 'auto'
        ), $atts);
        
        $campaign_id = intval($atts['campaign_id']);
        
        // Verify campaign exists and is active
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aqm_campaigns WHERE id = %d",
            $campaign_id
        ));
        
        if (!$campaign) {
            return '<div class="aqm-error">Quiz not found.</div>';
        }
        
        if ($campaign->status !== 'active') {
            return '<div class="aqm-notice">This quiz is currently not available.</div>';
        }
        
        // Check if campaign has started/ended
        $now = current_time('mysql');
        if ($campaign->start_date && $campaign->start_date > $now) {
            return '<div class="aqm-notice">This quiz has not started yet. Start date: ' . date('M j, Y H:i', strtotime($campaign->start_date)) . '</div>';
        }
        
        if ($campaign->end_date && $campaign->end_date < $now) {
            return '<div class="aqm-notice">This quiz has ended. End date: ' . date('M j, Y H:i', strtotime($campaign->end_date)) . '</div>';
        }
        
        // Enqueue assets
        $this->enqueue_quiz_assets();
        
        // Get questions count
        $questions_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aqm_questions WHERE campaign_id = %d",
            $campaign_id
        ));
        
        if ($questions_count == 0) {
            return '<div class="aqm-error">This quiz has no questions configured.</div>';
        }
        
        // Prepare container classes
        $container_classes = array('aqm-quiz-container', 'aqm-theme-' . sanitize_html_class($atts['theme']));
        if (!empty($atts['css_class'])) {
            $container_classes[] = sanitize_html_class($atts['css_class']);
        }
        
        // Prepare container styles
        $container_styles = array();
        if ($atts['width'] !== '100%') {
            $container_styles[] = 'width: ' . esc_attr($atts['width']);
        }
        if ($atts['height'] !== 'auto') {
            $container_styles[] = 'height: ' . esc_attr($atts['height']);
        }
        
        $style_attr = !empty($container_styles) ? ' style="' . implode('; ', $container_styles) . '"' : '';
        
        // Localize script with campaign-specific data
        wp_localize_script('aqm-quiz-frontend', 'aqm_campaign_' . $campaign_id, array(
            'campaign_id' => $campaign_id,
            'campaign_title' => $campaign->title,
            'campaign_description' => $campaign->description,
            'questions_count' => $questions_count,
            'show_progress' => $atts['show_progress'] === 'true',
            'show_results' => $atts['show_results'] === 'true',
            'auto_start' => $atts['auto_start'] === 'true',
            'redirect_url' => $atts['redirect_url'],
            'max_participants' => $campaign->max_participants
        ));
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $container_classes)); ?>" 
             data-campaign-id="<?php echo esc_attr($campaign_id); ?>"
             data-config="aqm_campaign_<?php echo esc_attr($campaign_id); ?>"
             <?php echo $style_attr; ?>>
            
            <!-- Quiz Header -->
            <div class="aqm-quiz-header">
                <h2 class="aqm-quiz-title"><?php echo esc_html($campaign->title); ?></h2>
                <?php if ($campaign->description): ?>
                    <p class="aqm-quiz-description"><?php echo esc_html($campaign->description); ?></p>
                <?php endif; ?>
                
                <div class="aqm-quiz-meta">
                    <span class="aqm-questions-count"><?php echo $questions_count; ?> questions</span>
                    <?php if ($campaign->max_participants > 0): ?>
                        <span class="aqm-max-participants">Max participants: <?php echo $campaign->max_participants; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Progress Bar (if enabled) -->
            <?php if ($atts['show_progress'] === 'true'): ?>
                <div class="aqm-progress-container" style="display: none;">
                    <div class="aqm-progress-bar">
                        <div class="aqm-progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="aqm-progress-text">Question 1 of <?php echo $questions_count; ?></div>
                </div>
            <?php endif; ?>
            
            <!-- Quiz Content Area -->
            <div class="aqm-quiz-content">
                <div class="aqm-loading-state">
                    <div class="aqm-spinner"></div>
                    <p>Loading quiz...</p>
                </div>
            </div>
            
            <!-- Quiz Navigation -->
            <div class="aqm-quiz-navigation" style="display: none;">
                <button type="button" class="aqm-btn aqm-btn-secondary aqm-btn-prev" style="display: none;">
                    ← Previous
                </button>
                <button type="button" class="aqm-btn aqm-btn-primary aqm-btn-next">
                    Next →
                </button>
            </div>
            
            <!-- Error Messages -->
            <div class="aqm-error-message" style="display: none;"></div>
            
            <!-- Success Messages -->
            <div class="aqm-success-message" style="display: none;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            if (typeof AQMQuizManager !== 'undefined') {
                new AQMQuizManager($('.aqm-quiz-container[data-campaign-id="<?php echo esc_js($campaign_id); ?>"]'));
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render mobile-optimized quiz
     * Usage: [aqm_quiz_mobile campaign_id="1" theme="mobile"]
     */
    public function render_mobile_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 1,
            'theme' => 'mobile',
            'fullscreen' => 'false'
        ), $atts);
        
        $campaign_id = intval($atts['campaign_id']);
        
        // Verify campaign
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aqm_campaigns WHERE id = %d AND status = 'active'",
            $campaign_id
        ));
        
        if (!$campaign) {
            return '<div class="aqm-error">Mobile quiz not available.</div>';
        }
        
        // Check for mobile template file
        $template_path = get_template_directory() . '/templates/aqm-mobile-quiz.html';
        $plugin_template_path = AQM_PLUGIN_PATH . 'templates/mobile-quiz.html';
        
        // Try theme template first, then plugin template
        if (file_exists($template_path)) {
            $template_content = file_get_contents($template_path);
        } elseif (file_exists($plugin_template_path)) {
            $template_content = file_get_contents($plugin_template_path);
        } else {
            // Fallback to basic template
            $template_content = $this->get_mobile_quiz_template();
        }
        
        // Replace template variables
        $replacements = array(
            '{{CAMPAIGN_ID}}' => $campaign_id,
            '{{CAMPAIGN_TITLE}}' => esc_html($campaign->title),
            '{{CAMPAIGN_DESCRIPTION}}' => esc_html($campaign->description),
            '{{AJAX_URL}}' => admin_url('admin-ajax.php'),
            '{{NONCE}}' => wp_create_nonce('aqm_front_nonce'),
            '{{PLUGIN_URL}}' => AQM_PLUGIN_URL
        );
        
        $template_content = str_replace(array_keys($replacements), array_values($replacements), $template_content);
        
        // Add fullscreen class if enabled
        if ($atts['fullscreen'] === 'true') {
            $template_content = str_replace('class="quiz-container"', 'class="quiz-container quiz-fullscreen"', $template_content);
        }
        
        return $template_content;
    }
    
    /**
     * Render quiz results shortcode
     * Usage: [aqm_quiz_results response_id="123"]
     */
    public function render_quiz_results_shortcode($atts) {
        $atts = shortcode_atts(array(
            'response_id' => 0,
            'show_answers' => 'false',
            'show_certificate' => 'true'
        ), $atts);
        
        $response_id = intval($atts['response_id']);
        
        if (!$response_id) {
            return '<div class="aqm-error">Invalid response ID.</div>';
        }
        
        global $wpdb;
        
        // Get response data
        $response = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, c.title as campaign_title 
             FROM {$wpdb->prefix}aqm_responses r
             LEFT JOIN {$wpdb->prefix}aqm_campaigns c ON r.campaign_id = c.id
             WHERE r.id = %d",
            $response_id
        ));
        
        if (!$response) {
            return '<div class="aqm-error">Quiz response not found.</div>';
        }
        
        // Get gift awards for this response
        $gift_awards = $wpdb->get_results($wpdb->prepare(
            "SELECT ga.*, g.gift_name, g.gift_value, g.gift_type
             FROM {$wpdb->prefix}aqm_gift_awards ga
             LEFT JOIN {$wpdb->prefix}aqm_gifts g ON ga.gift_id = g.id
             WHERE ga.response_id = %d",
            $response_id
        ));
        
        ob_start();
        ?>
        <div class="aqm-quiz-results">
            <div class="aqm-results-header">
                <h2>Quiz Results</h2>
                <p class="aqm-campaign-title"><?php echo esc_html($response->campaign_title); ?></p>
            </div>
            
            <div class="aqm-results-content">
                <div class="aqm-score-display">
                    <div class="aqm-score-circle">
                        <span class="aqm-score-number"><?php echo esc_html($response->final_score); ?>%</span>
                    </div>
                    <p class="aqm-score-label">Your Score</p>
                </div>
                
                <div class="aqm-results-meta">
                    <div class="aqm-meta-item">
                        <span class="label">Participant:</span>
                        <span class="value"><?php echo esc_html($response->participant_name ?: 'Anonymous'); ?></span>
                    </div>
                    <div class="aqm-meta-item">
                        <span class="label">Completed:</span>
                        <span class="value"><?php echo esc_html(date('M j, Y H:i', strtotime($response->completed_at))); ?></span>
                    </div>
                    <?php if ($response->province_selected): ?>
                        <div class="aqm-meta-item">
                            <span class="label">Location:</span>
                            <span class="value"><?php echo esc_html($response->province_selected); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($gift_awards)): ?>
                    <div class="aqm-gift-awards">
                        <h3>🎁 Gifts Awarded</h3>
                        <?php foreach ($gift_awards as $award): ?>
                            <div class="aqm-gift-award">
                                <div class="aqm-gift-info">
                                    <h4><?php echo esc_html($award->gift_name); ?></h4>
                                    <?php if ($award->gift_value): ?>
                                        <p class="aqm-gift-value"><?php echo esc_html($award->gift_value); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="aqm-gift-code">
                                    <label>Gift Code:</label>
                                    <code><?php echo esc_html($award->gift_code); ?></code>
                                    <button class="aqm-btn aqm-btn-small aqm-copy-code" data-code="<?php echo esc_attr($award->gift_code); ?>">
                                        Copy
                                    </button>
                                </div>
                                <div class="aqm-gift-status">
                                    <span class="aqm-status aqm-status-<?php echo esc_attr($award->claim_status); ?>">
                                        <?php echo esc_html(ucfirst($award->claim_status)); ?>
                                    </span>
                                    <?php if ($award->expiry_date): ?>
                                        <span class="aqm-expiry">
                                            Expires: <?php echo esc_html(date('M j, Y', strtotime($award->expiry_date))); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($atts['show_certificate'] === 'true'): ?>
                    <div class="aqm-certificate-section">
                        <h3>Certificate</h3>
                        <a href="<?php echo admin_url('admin-ajax.php?action=aqm_download_certificate&response_id=' . $response_id . '&nonce=' . wp_create_nonce('aqm_certificate_' . $response_id)); ?>" 
                           class="aqm-btn aqm-btn-primary" target="_blank">
                            📜 Download Certificate
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="aqm-results-actions">
                    <button class="aqm-btn aqm-btn-secondary aqm-share-results" data-response-id="<?php echo esc_attr($response_id); ?>">
                        📤 Share Results
                    </button>
                    <button class="aqm-btn aqm-btn-secondary aqm-print-results">
                        🖨️ Print Results
                    </button>
                </div>
            </div>
        </div>
        
        <style>
        .aqm-quiz-results {
            max-width: 600px;
            margin: 20px auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .aqm-results-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .aqm-score-display {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .aqm-score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
        }
        
        .aqm-score-number {
            font-size: 32px;
            font-weight: bold;
            color: white;
        }
        
        .aqm-results-meta {
            margin-bottom: 30px;
        }
        
        .aqm-meta-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .aqm-gift-awards {
            margin-bottom: 30px;
        }
        
        .aqm-gift-award {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .aqm-gift-code {
            margin: 10px 0;
        }
        
        .aqm-gift-code code {
            background: #e9ecef;
            padding: 5px 10px;
            border-radius: 4px;
            font-family: monospace;
            margin: 0 10px;
        }
        
        .aqm-results-actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .aqm-btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            margin: 0 5px;
        }
        
        .aqm-btn-primary {
            background: #007cba;
            color: white;
        }
        
        .aqm-btn-secondary {
            background: #6c757d;
            color: white;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.aqm-copy-code').on('click', function() {
                var code = $(this).data('code');
                navigator.clipboard.writeText(code).then(function() {
                    alert('Gift code copied to clipboard!');
                });
            });
            
            $('.aqm-share-results').on('click', function() {
                var url = window.location.href;
                var text = 'Check out my quiz results!';
                
                if (navigator.share) {
                    navigator.share({
                        title: 'Quiz Results',
                        text: text,
                        url: url
                    });
                } else {
                    navigator.clipboard.writeText(text + ' ' + url).then(function() {
                        alert('Results link copied to clipboard!');
                    });
                }
            });
            
            $('.aqm-print-results').on('click', function() {
                window.print();
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render gift claim shortcode
     * Usage: [aqm_gift_claim]
     */
    public function render_gift_claim_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Claim Your Gift',
            'show_verification' => 'true'
        ), $atts);
        
        ob_start();
        ?>
        <div class="aqm-gift-claim-form">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            
            <form id="aqm-gift-claim-form">
                <div class="aqm-form-group">
                    <label for="gift-code">Gift Code:</label>
                    <input type="text" id="gift-code" name="gift_code" required 
                           placeholder="Enter your gift code" class="aqm-form-control">
                </div>
                
                <div class="aqm-form-group">
                    <label for="claimer-email">Email (optional):</label>
                    <input type="email" id="claimer-email" name="email" 
                           placeholder="your@email.com" class="aqm-form-control">
                </div>
                
                <button type="submit" class="aqm-btn aqm-btn-primary">
                    🎁 Claim Gift
                </button>
            </form>
            
            <?php if ($atts['show_verification'] === 'true'): ?>
                <div class="aqm-gift-verification">
                    <h4>Verify Gift Code</h4>
                    <p>Enter a gift code above to verify its status or claim your gift.</p>
                </div>
            <?php endif; ?>
            
            <div class="aqm-claim-result" style="display: none;"></div>
        </div>
        
        <style>
        .aqm-gift-claim-form {
            max-width: 400px;
            margin: 20px auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .aqm-form-group {
            margin-bottom: 20px;
        }
        
        .aqm-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .aqm-form-control {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .aqm-form-control:focus {
            outline: none;
            border-color: #007cba;
        }
        
        .aqm-gift-verification {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#aqm-gift-claim-form').on('submit', function(e) {
                e.preventDefault();
                
                var giftCode = $('#gift-code').val();
                var email = $('#claimer-email').val();
                
                if (!giftCode) {
                    alert('Please enter a gift code.');
                    return;
                }
                
                $.ajax({
                    url: aqm_quiz_config.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'aqm_claim_gift',
                        gift_code: giftCode,
                        email: email,
                        nonce: aqm_quiz_config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.aqm-claim-result').html(
                                '<div class="aqm-success">' +
                                '<h4>🎉 Gift Claimed Successfully!</h4>' +
                                '<p><strong>Gift:</strong> ' + response.data.gift_name + '</p>' +
                                '<p><strong>Value:</strong> ' + response.data.gift_value + '</p>' +
                                '<p><strong>Claimed:</strong> ' + response.data.claimed_at + '</p>' +
                                '</div>'
                            ).show();
                            $('#aqm-gift-claim-form')[0].reset();
                        } else {
                            $('.aqm-claim-result').html(
                                '<div class="aqm-error">' +
                                '<h4>❌ Claim Failed</h4>' +
                                '<p>' + response.data + '</p>' +
                                '</div>'
                            ).show();
                        }
                    },
                    error: function() {
                        $('.aqm-claim-result').html(
                            '<div class="aqm-error">' +
                            '<h4>❌ Error</h4>' +
                            '<p>An error occurred. Please try again.</p>' +
                            '</div>'
                        ).show();
                    }
                });
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get fallback mobile quiz template
     */
    private function get_mobile_quiz_template() {
        return '
        <div class="aqm-mobile-quiz" data-campaign-id="{{CAMPAIGN_ID}}">
            <div class="aqm-mobile-header">
                <h1>{{CAMPAIGN_TITLE}}</h1>
                <p>{{CAMPAIGN_DESCRIPTION}}</p>
            </div>
            
            <div class="aqm-mobile-content">
                <div class="aqm-loading">
                    <div class="aqm-spinner"></div>
                    <p>Loading quiz...</p>
                </div>
            </div>
            
            <script>
            // Mobile quiz initialization
            jQuery(document).ready(function($) {
                var campaignId = "{{CAMPAIGN_ID}}";
                var ajaxUrl = "{{AJAX_URL}}";
                var nonce = "{{NONCE}}";
                
                // Load quiz questions and initialize mobile interface
                $.ajax({
                    url: ajaxUrl,
                    type: "POST",
                    data: {
                        action: "aqm_get_campaign_questions",
                        campaign_id: campaignId
                    },
                    success: function(response) {
                        if (response.success) {
                            // Initialize mobile quiz with questions
                            initializeMobileQuiz(response.data);
                        } else {
                            $(".aqm-mobile-content").html("<p>Error loading quiz.</p>");
                        }
                    }
                });
                
                function initializeMobileQuiz(questions) {
                    // Mobile quiz implementation here
                    $(".aqm-mobile-content").html("<p>Mobile quiz interface would be rendered here.</p>");
                }
            });
            </script>
            
            <style>
            .aqm-mobile-quiz {
                max-width: 100%;
                margin: 0;
                padding: 20px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            
            .aqm-mobile-header {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .aqm-loading {
                text-align: center;
                padding: 50px 20px;
            }
            
            .aqm-spinner {
                border: 3px solid #f3f3f3;
                border-top: 3px solid #3498db;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: aqm-spin 1s linear infinite;
                margin: 0 auto 20px;
            }
            
            @keyframes aqm-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            </style>
        </div>';
    }
}

// Initialize Shortcodes
new AQM_Shortcodes();