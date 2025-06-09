<?php
/**
 * Frontend Class for Advanced Quiz Manager
 * File: public/class-frontend.php
 */

class AQM_Frontend {
    
    private $db;
    
    public function __construct() {
        $this->db = new AQM_Database();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Shortcode registration
        add_shortcode('quiz_form', array($this, 'quiz_form_shortcode'));
        add_shortcode('quiz_results', array($this, 'quiz_results_shortcode'));
        add_shortcode('quiz_stats', array($this, 'quiz_stats_shortcode'));
        
        // Frontend scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Handle quiz preview
        add_action('template_redirect', array($this, 'handle_quiz_preview'));
        
        // Widget support
        add_action('widgets_init', array($this, 'register_widgets'));
        
        // Gutenberg block support
        add_action('init', array($this, 'register_blocks'));
    }
    
    public function enqueue_scripts() {
        // Only enqueue on pages with quiz shortcodes or widgets
        if ($this->should_enqueue_scripts()) {
            wp_enqueue_script(
                'aqm-frontend-js', 
                AQM_PLUGIN_URL . 'assets/js/frontend.js', 
                array('jquery'), 
                AQM_VERSION, 
                true
            );
            
            wp_enqueue_style(
                'aqm-frontend-css', 
                AQM_PLUGIN_URL . 'assets/css/frontend.css', 
                array(), 
                AQM_VERSION
            );
            
            // Localize script with data
            wp_localize_script('aqm-frontend-js', 'aqm_front', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqm_front_nonce'),
                'provinces_data' => $this->get_provinces_data(),
                'strings' => array(
                    'loading' => __('Loading...', 'advanced-quiz'),
                    'submitting' => __('Submitting...', 'advanced-quiz'),
                    'error' => __('An error occurred. Please try again.', 'advanced-quiz'),
                    'required_field' => __('This field is required.', 'advanced-quiz'),
                    'invalid_email' => __('Please enter a valid email address.', 'advanced-quiz'),
                    'quiz_completed' => __('Quiz completed successfully!', 'advanced-quiz'),
                    'gift_won' => __('Congratulations! You won a gift!', 'advanced-quiz')
                )
            ));
        }
    }
    
    private function should_enqueue_scripts() {
        global $post;
        
        // Check if current page has quiz shortcodes
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'quiz_form')) {
            return true;
        }
        
        // Check for quiz preview
        if (isset($_GET['quiz_preview'])) {
            return true;
        }
        
        // Check for widgets
        if (is_active_widget(false, false, 'aqm_quiz_widget')) {
            return true;
        }
        
        return false;
    }
    
    // SHORTCODE HANDLERS
    
    public function quiz_form_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'style' => 'default',
            'show_title' => 'true',
            'show_description' => 'true',
            'show_progress' => 'true',
            'theme' => 'light',
            'class' => ''
        ), $atts, 'quiz_form');
        
        $campaign_id = intval($atts['campaign_id']);
        
        if (!$campaign_id) {
            return $this->render_error(__('Campaign ID is required.', 'advanced-quiz'));
        }
        
        $campaign = $this->db->get_campaign($campaign_id);
        
        if (!$campaign) {
            return $this->render_error(__('Campaign not found.', 'advanced-quiz'));
        }
        
        // Check if campaign is accessible
        if (!$this->is_campaign_accessible($campaign)) {
            return $this->render_campaign_unavailable($campaign);
        }
        
        return $this->render_quiz_form($campaign, $atts);
    }
    
    public function quiz_results_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'show_charts' => 'true',
            'show_stats' => 'true',
            'limit' => 10,
            'show_responses' => 'false'
        ), $atts, 'quiz_results');
        
        $campaign_id = intval($atts['campaign_id']);
        
        if (!$campaign_id) {
            return $this->render_error(__('Campaign ID is required.', 'advanced-quiz'));
        }
        
        return $this->render_quiz_results($campaign_id, $atts);
    }
    
    public function quiz_stats_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'style' => 'cards',
            'show_labels' => 'true'
        ), $atts, 'quiz_stats');
        
        $campaign_id = intval($atts['campaign_id']);
        
        if (!$campaign_id) {
            return $this->render_error(__('Campaign ID is required.', 'advanced-quiz'));
        }
        
        return $this->render_quiz_stats($campaign_id, $atts);
    }
    
    // RENDERING METHODS
    
    private function render_quiz_form($campaign, $atts) {
        $questions = $this->db->get_campaign_questions($campaign->id);
        $settings = json_decode($campaign->settings, true) ?: array();
        
        ob_start();
        ?>
        <div class="aqm-quiz-container aqm-style-<?php echo esc_attr($atts['style']); ?> aqm-theme-<?php echo esc_attr($atts['theme']); ?> <?php echo esc_attr($atts['class']); ?>" 
             data-campaign-id="<?php echo esc_attr($campaign->id); ?>" 
             data-style="<?php echo esc_attr($atts['style']); ?>">
            
            <?php if ($atts['show_title'] === 'true' || $atts['show_description'] === 'true'): ?>
            <div class="aqm-quiz-header">
                <?php if ($atts['show_title'] === 'true'): ?>
                    <h2 class="aqm-quiz-title"><?php echo esc_html($campaign->title); ?></h2>
                <?php endif; ?>
                
                <?php if ($atts['show_description'] === 'true' && $campaign->description): ?>
                    <div class="aqm-quiz-description"><?php echo wp_kses_post(wpautop($campaign->description)); ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_progress'] === 'true' && count($questions) > 1): ?>
            <div class="aqm-progress-bar-container">
                <div class="aqm-progress-bar">
                    <div class="aqm-progress-fill" style="width: 0%;"></div>
                </div>
                <span class="aqm-progress-text">0/<?php echo count($questions); ?> <?php _e('questions completed', 'advanced-quiz'); ?></span>
            </div>
            <?php endif; ?>
            
            <form class="aqm-quiz-form" id="aqm-quiz-form-<?php echo esc_attr($campaign->id); ?>" data-start-time="<?php echo time(); ?>">
                <?php wp_nonce_field('aqm_submit_quiz', 'aqm_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->id); ?>">
                
                <?php if (isset($settings['enable_timer']) && $settings['enable_timer']): ?>
                <div class="aqm-timer-container">
                    <div class="aqm-timer" data-minutes="<?php echo intval($settings['timer_minutes'] ?? 30); ?>">
                        <span class="aqm-timer-label"><?php _e('Time remaining:', 'advanced-quiz'); ?></span>
                        <span class="aqm-timer-display">--:--</span>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="aqm-questions-container">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="aqm-question-container <?php echo count($questions) > 3 ? 'aqm-step-question' : ''; ?>" 
                             data-question-id="<?php echo esc_attr($question->id); ?>" 
                             data-question-type="<?php echo esc_attr($question->question_type); ?>"
                             data-question-index="<?php echo esc_attr($index); ?>"
                             <?php if (count($questions) > 3 && $index > 0): ?>style="display: none;"<?php endif; ?>>
                            
                            <label class="aqm-question-label">
                                <span class="aqm-question-number"><?php echo ($index + 1); ?>.</span>
                                <span class="aqm-question-text"><?php echo esc_html($question->question_text); ?></span>
                                <?php if ($question->is_required): ?>
                                    <span class="aqm-required">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <div class="aqm-question-field">
                                <?php $this->render_question_field($question); ?>
                            </div>
                            
                            <?php if (count($questions) > 3): ?>
                            <div class="aqm-question-navigation">
                                <?php if ($index > 0): ?>
                                    <button type="button" class="aqm-prev-question button"><?php _e('Previous', 'advanced-quiz'); ?></button>
                                <?php endif; ?>
                                
                                <?php if ($index < count($questions) - 1): ?>
                                    <button type="button" class="aqm-next-question button"><?php _e('Next', 'advanced-quiz'); ?></button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="aqm-quiz-actions">
                    <?php if (count($questions) <= 3): ?>
                        <button type="submit" class="aqm-submit-btn"><?php _e('Submit Quiz', 'advanced-quiz'); ?></button>
                    <?php else: ?>
                        <button type="submit" class="aqm-submit-btn" style="display: none;"><?php _e('Submit Quiz', 'advanced-quiz'); ?></button>
                    <?php endif; ?>
                </div>
            </form>
            
            <div class="aqm-quiz-result" id="aqm-quiz-result-<?php echo esc_attr($campaign->id); ?>" style="display: none;">
                <!-- Results will be loaded here via AJAX -->
            </div>
            
            <div class="aqm-quiz-loading" style="display: none;">
                <div class="aqm-loading-spinner"></div>
                <p><?php _e('Processing your quiz...', 'advanced-quiz'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_question_field($question) {
        $options = json_decode($question->options, true) ?: array();
        $required = $question->is_required ? 'required' : '';
        $field_name = 'question_' . $question->id;
        
        switch ($question->question_type) {
            case 'provinces':
                echo '<select name="' . esc_attr($field_name) . '" class="aqm-provinces-select" ' . $required . '>';
                echo '<option value="">' . esc_html($options['placeholder'] ?? __('Select Province', 'advanced-quiz')) . '</option>';
                
                $provinces = $this->db->get_provinces();
                foreach ($provinces as $province) {
                    echo '<option value="' . esc_attr($province->code) . '">' . esc_html($province->name) . '</option>';
                }
                echo '</select>';
                
                if (isset($options['load_districts']) && $options['load_districts']) {
                    echo '<select name="' . esc_attr($field_name) . '_district" class="aqm-districts-select" style="display:none; margin-top: 10px;">';
                    echo '<option value="">' . __('Select District', 'advanced-quiz') . '</option>';
                    echo '</select>';
                }
                
                if (isset($options['load_wards']) && $options['load_wards']) {
                    echo '<select name="' . esc_attr($field_name) . '_ward" class="aqm-wards-select" style="display:none; margin-top: 10px;">';
                    echo '<option value="">' . __('Select Ward', 'advanced-quiz') . '</option>';
                    echo '</select>';
                }
                break;
                
            case 'districts':
                echo '<select name="' . esc_attr($field_name) . '" class="aqm-districts-select" ' . $required . '>';
                echo '<option value="">' . __('Select District', 'advanced-quiz') . '</option>';
                // Districts will be loaded via AJAX
                echo '</select>';
                break;
                
            case 'wards':
                echo '<select name="' . esc_attr($field_name) . '" class="aqm-wards-select" ' . $required . '>';
                echo '<option value="">' . __('Select Ward', 'advanced-quiz') . '</option>';
                // Wards will be loaded via AJAX
                echo '</select>';
                break;
                
            case 'multiple_choice':
                if (isset($options['choices']) && is_array($options['choices'])) {
                    foreach ($options['choices'] as $choice) {
                        echo '<label class="aqm-radio-label">';
                        echo '<input type="radio" name="' . esc_attr($field_name) . '" value="' . esc_attr($choice['value']) . '" ' . $required . '>';
                        echo '<span>' . esc_html($choice['label']) . '</span>';
                        echo '</label>';
                    }
                }
                break;
                
            case 'rating':
                $max_rating = isset($options['max_rating']) ? intval($options['max_rating']) : 5;
                $icon = isset($options['icon']) ? $options['icon'] : 'star';
                
                $icon_symbol = '★';
                if ($icon === 'heart') $icon_symbol = '♥';
                if ($icon === 'thumb') $icon_symbol = '👍';
                
                echo '<div class="aqm-rating-container" data-max-rating="' . esc_attr($max_rating) . '">';
                for ($i = 1; $i <= $max_rating; $i++) {
                    echo '<label class="aqm-rating-label">';
                    echo '<input type="radio" name="' . esc_attr($field_name) . '" value="' . $i . '" ' . $required . '>';
                    echo '<span class="aqm-rating-icon aqm-' . esc_attr($icon) . '">' . $icon_symbol . '</span>';
                    echo '</label>';
                }
                echo '</div>';
                break;
                
            case 'text':
                $placeholder = isset($options['placeholder']) ? $options['placeholder'] : '';
                echo '<input type="text" name="' . esc_attr($field_name) . '" class="aqm-text-input" placeholder="' . esc_attr($placeholder) . '" ' . $required . '>';
                break;
                
            case 'email':
                echo '<input type="email" name="' . esc_attr($field_name) . '" class="aqm-email-input" placeholder="' . esc_attr__('Enter your email', 'advanced-quiz') . '" ' . $required . '>';
                break;
                
            case 'phone':
                echo '<input type="tel" name="' . esc_attr($field_name) . '" class="aqm-phone-input" placeholder="' . esc_attr__('Enter your phone number', 'advanced-quiz') . '" ' . $required . '>';
                break;
                
            case 'number':
                $min = isset($options['min']) ? 'min="' . esc_attr($options['min']) . '"' : '';
                $max = isset($options['max']) ? 'max="' . esc_attr($options['max']) . '"' : '';
                echo '<input type="number" name="' . esc_attr($field_name) . '" class="aqm-number-input" ' . $min . ' ' . $max . ' ' . $required . '>';
                break;
                
            case 'date':
                echo '<input type="date" name="' . esc_attr($field_name) . '" class="aqm-date-input" ' . $required . '>';
                break;
                
            case 'file_upload':
                $accept = isset($options['accept']) ? 'accept="' . esc_attr($options['accept']) . '"' : '';
                echo '<input type="file" name="' . esc_attr($field_name) . '" class="aqm-file-input" ' . $accept . ' ' . $required . '>';
                break;
                
            default:
                echo '<input type="text" name="' . esc_attr($field_name) . '" class="aqm-text-input" ' . $required . '>';
                break;
        }
    }
    
    private function render_quiz_results($campaign_id, $atts) {
        $stats = $this->db->get_campaign_stats($campaign_id);
        $campaign = $this->db->get_campaign($campaign_id);
        
        if (!$campaign) {
            return $this->render_error(__('Campaign not found.', 'advanced-quiz'));
        }
        
        ob_start();
        ?>
        <div class="aqm-results-container">
            <div class="aqm-results-header">
                <h3><?php printf(__('Results for "%s"', 'advanced-quiz'), esc_html($campaign->title)); ?></h3>
            </div>
            
            <?php if ($atts['show_stats'] === 'true'): ?>
            <div class="aqm-stats-grid">
                <div class="aqm-stat-box">
                    <h4><?php _e('Total Participants', 'advanced-quiz'); ?></h4>
                    <span class="aqm-stat-number"><?php echo esc_html($stats['total_participants']); ?></span>
                </div>
                
                <div class="aqm-stat-box">
                    <h4><?php _e('Completion Rate', 'advanced-quiz'); ?></h4>
                    <span class="aqm-stat-number"><?php echo esc_html($stats['completion_rate']); ?>%</span>
                </div>
                
                <div class="aqm-stat-box">
                    <h4><?php _e('Average Score', 'advanced-quiz'); ?></h4>
                    <span class="aqm-stat-number"><?php echo esc_html($stats['average_score']); ?></span>
                </div>
                
                <div class="aqm-stat-box">
                    <h4><?php _e('Gifts Awarded', 'advanced-quiz'); ?></h4>
                    <span class="aqm-stat-number"><?php echo esc_html($stats['gifts_awarded']); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_charts'] === 'true'): ?>
            <div class="aqm-charts-container">
                <div class="aqm-chart-wrapper">
                    <h4><?php _e('Response Distribution', 'advanced-quiz'); ?></h4>
                    <canvas id="aqm-response-chart-<?php echo esc_attr($campaign_id); ?>" class="aqm-chart-canvas" 
                            data-chart-type="line" 
                            data-campaign-id="<?php echo esc_attr($campaign_id); ?>"></canvas>
                </div>
                
                <div class="aqm-chart-wrapper">
                    <h4><?php _e('Province Distribution', 'advanced-quiz'); ?></h4>
                    <canvas id="aqm-province-chart-<?php echo esc_attr($campaign_id); ?>" class="aqm-chart-canvas" 
                            data-chart-type="doughnut" 
                            data-campaign-id="<?php echo esc_attr($campaign_id); ?>"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_responses'] === 'true' && current_user_can('manage_options')): ?>
            <div class="aqm-recent-responses">
                <h4><?php _e('Recent Responses', 'advanced-quiz'); ?></h4>
                <?php $this->render_recent_responses($campaign_id, intval($atts['limit'])); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_quiz_stats($campaign_id, $atts) {
        $stats = $this->db->get_campaign_stats($campaign_id);
        
        ob_start();
        ?>
        <div class="aqm-stats-widget aqm-stats-style-<?php echo esc_attr($atts['style']); ?>">
            <?php if ($atts['style'] === 'cards'): ?>
                <div class="aqm-stats-cards">
                    <div class="aqm-stat-card">
                        <?php if ($atts['show_labels'] === 'true'): ?>
                            <span class="aqm-stat-label"><?php _e('Participants', 'advanced-quiz'); ?></span>
                        <?php endif; ?>
                        <span class="aqm-stat-value"><?php echo esc_html($stats['total_participants']); ?></span>
                    </div>
                    
                    <div class="aqm-stat-card">
                        <?php if ($atts['show_labels'] === 'true'): ?>
                            <span class="aqm-stat-label"><?php _e('Completion Rate', 'advanced-quiz'); ?></span>
                        <?php endif; ?>
                        <span class="aqm-stat-value"><?php echo esc_html($stats['completion_rate']); ?>%</span>
                    </div>
                    
                    <div class="aqm-stat-card">
                        <?php if ($atts['show_labels'] === 'true'): ?>
                            <span class="aqm-stat-label"><?php _e('Average Score', 'advanced-quiz'); ?></span>
                        <?php endif; ?>
                        <span class="aqm-stat-value"><?php echo esc_html($stats['average_score']); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="aqm-stats-list">
                    <div class="aqm-stat-item">
                        <strong><?php echo esc_html($stats['total_participants']); ?></strong> <?php _e('participants', 'advanced-quiz'); ?>
                    </div>
                    <div class="aqm-stat-item">
                        <strong><?php echo esc_html($stats['completion_rate']); ?>%</strong> <?php _e('completion rate', 'advanced-quiz'); ?>
                    </div>
                    <div class="aqm-stat-item">
                        <strong><?php echo esc_html($stats['average_score']); ?></strong> <?php _e('average score', 'advanced-quiz'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_recent_responses($campaign_id, $limit) {
        $responses = $this->db->get_campaign_responses($campaign_id, array(
            'status' => 'completed',
            'limit' => $limit,
            'orderby' => 'submitted_at',
            'order' => 'DESC'
        ));
        
        if (empty($responses)) {
            echo '<p>' . __('No responses yet.', 'advanced-quiz') . '</p>';
            return;
        }
        
        echo '<div class="aqm-responses-table">';
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Name', 'advanced-quiz') . '</th>';
        echo '<th>' . __('Email', 'advanced-quiz') . '</th>';
        echo '<th>' . __('Score', 'advanced-quiz') . '</th>';
        echo '<th>' . __('Date', 'advanced-quiz') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($responses as $response) {
            echo '<tr>';
            echo '<td>' . esc_html($response->user_name ?: __('Anonymous', 'advanced-quiz')) . '</td>';
            echo '<td>' . esc_html($response->user_email ?: '-') . '</td>';
            echo '<td>' . esc_html($response->total_score) . '</td>';
            echo '<td>' . esc_html(date('M j, Y', strtotime($response->submitted_at))) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    
    // UTILITY METHODS
    
    private function is_campaign_accessible($campaign) {
        // Check status
        if ($campaign->status !== 'active') {
            return false;
        }
        
        // Check start date
        if ($campaign->start_date && strtotime($campaign->start_date) > time()) {
            return false;
        }
        
        // Check end date
        if ($campaign->end_date && strtotime($campaign->end_date) < time()) {
            return false;
        }
        
        // Check participant limit
        if ($campaign->max_participants > 0) {
            $stats = $this->db->get_campaign_stats($campaign->id);
            if ($stats['total_participants'] >= $campaign->max_participants) {
                return false;
            }
        }
        
        // Check login requirement
        $settings = json_decode($campaign->settings, true) ?: array();
        if (isset($settings['require_login']) && $settings['require_login'] && !is_user_logged_in()) {
            return false;
        }
        
        return true;
    }
    
    private function render_campaign_unavailable($campaign) {
        $message = __('This quiz is currently unavailable.', 'advanced-quiz');
        
        if ($campaign->status !== 'active') {
            $message = __('This quiz is not currently active.', 'advanced-quiz');
        } elseif ($campaign->start_date && strtotime($campaign->start_date) > time()) {
            $message = sprintf(
                __('This quiz will be available starting %s.', 'advanced-quiz'),
                date('F j, Y \a\t g:i A', strtotime($campaign->start_date))
            );
        } elseif ($campaign->end_date && strtotime($campaign->end_date) < time()) {
            $message = __('This quiz has ended.', 'advanced-quiz');
        } elseif ($campaign->max_participants > 0) {
            $stats = $this->db->get_campaign_stats($campaign->id);
            if ($stats['total_participants'] >= $campaign->max_participants) {
                $message = __('This quiz has reached the maximum number of participants.', 'advanced-quiz');
            }
        }
        
        $settings = json_decode($campaign->settings, true) ?: array();
        if (isset($settings['require_login']) && $settings['require_login'] && !is_user_logged_in()) {
            $message = __('You must be logged in to take this quiz.', 'advanced-quiz');
        }
        
        return '<div class="aqm-quiz-unavailable"><p>' . esc_html($message) . '</p></div>';
    }
    
    private function render_error($message) {
        return '<div class="aqm-quiz-error"><p>' . esc_html($message) . '</p></div>';
    }
    
    private function get_provinces_data() {
        // Return simplified provinces data for frontend
        $provinces = $this->db->get_provinces();
        $provinces_data = array();
        
        foreach ($provinces as $province) {
            $districts = $this->db->get_districts($province->code);
            $districts_data = array();
            
            foreach ($districts as $district) {
                $wards = $this->db->get_wards($district->code);
                $wards_data = array();
                
                foreach ($wards as $ward) {
                    $wards_data[] = array(
                        'code' => $ward->code,
                        'name' => $ward->name,
                        'name_en' => $ward->name_en
                    );
                }
                
                $districts_data[] = array(
                    'code' => $district->code,
                    'name' => $district->name,
                    'name_en' => $district->name_en,
                    'wards' => $wards_data
                );
            }
            
            $provinces_data[] = array(
                'code' => $province->code,
                'name' => $province->name,
                'name_en' => $province->name_en,
                'districts' => $districts_data
            );
        }
        
        return $provinces_data;
    }
    
    // PREVIEW HANDLER
    
    public function handle_quiz_preview() {
        if (isset($_GET['quiz_preview']) && current_user_can('manage_options')) {
            $campaign_id = intval($_GET['quiz_preview']);
            $campaign = $this->db->get_campaign($campaign_id);
            
            if ($campaign) {
                // Temporarily make campaign accessible for preview
                add_filter('aqm_is_campaign_accessible', '__return_true');
                
                // Load preview template
                $this->load_preview_template($campaign);
                exit;
            }
        }
    }
    
    private function load_preview_template($campaign) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php printf(__('Preview: %s', 'advanced-quiz'), esc_html($campaign->title)); ?></title>
            <?php wp_head(); ?>
            <style>
                body { margin: 0; padding: 20px; background: #f1f1f1; font-family: Arial, sans-serif; }
                .preview-header { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .preview-notice { background: #e7f3ff; color: #004085; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class="preview-header">
                <h1><?php _e('Quiz Preview', 'advanced-quiz'); ?></h1>
                <div class="preview-notice">
                    <?php _e('This is a preview of your quiz. Responses will not be saved.', 'advanced-quiz'); ?>
                </div>
            </div>
            
            <?php echo do_shortcode('[quiz_form campaign_id="' . $campaign->id . '"]'); ?>
            
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
    
    // WIDGET REGISTRATION
    
    public function register_widgets() {
        register_widget('AQM_Quiz_Widget');
    }
    
    // GUTENBERG BLOCK REGISTRATION
    
    public function register_blocks() {
        if (function_exists('register_block_type')) {
            register_block_type('aqm/quiz-form', array(
                'render_callback' => array($this, 'render_quiz_block'),
                'attributes' => array(
                    'campaignId' => array(
                        'type' => 'number',
                        'default' => 0
                    ),
                    'style' => array(
                        'type' => 'string',
                        'default' => 'default'
                    ),
                    'showTitle' => array(
                        'type' => 'boolean',
                        'default' => true
                    ),
                    'showDescription' => array(
                        'type' => 'boolean',
                        'default' => true
                    )
                )
            ));
        }
    }
    
    public function render_quiz_block($attributes) {
        $atts = array(
            'campaign_id' => $attributes['campaignId'] ?? 0,
            'style' => $attributes['style'] ?? 'default',
            'show_title' => $attributes['showTitle'] ?? true ? 'true' : 'false',
            'show_description' => $attributes['showDescription'] ?? true ? 'true' : 'false'
        );
        
        return $this->quiz_form_shortcode($atts);
    }
}

// Quiz Widget Class
class AQM_Quiz_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'aqm_quiz_widget',
            __('Quiz Form', 'advanced-quiz'),
            array('description' => __('Display a quiz form in your sidebar', 'advanced-quiz'))
        );
    }
    
    public function widget($args, $instance) {
        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }
        
        $campaign_id = !empty($instance['campaign_id']) ? intval($instance['campaign_id']) : 0;
        
        if ($campaign_id) {
            echo do_shortcode('[quiz_form campaign_id="' . $campaign_id . '" style="compact"]');
        } else {
            echo '<p>' . __('Please select a quiz campaign in the widget settings.', 'advanced-quiz') . '</p>';
        }
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $campaign_id = !empty($instance['campaign_id']) ? $instance['campaign_id'] : '';
        
        $db = new AQM_Database();
        $campaigns = $db->get_campaigns(array('status' => 'active'));
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:', 'advanced-quiz'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('campaign_id')); ?>"><?php _e('Quiz Campaign:', 'advanced-quiz'); ?></label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('campaign_id')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('campaign_id')); ?>">
                <option value=""><?php _e('Select a campaign', 'advanced-quiz'); ?></option>
                <?php foreach ($campaigns as $campaign): ?>
                    <option value="<?php echo esc_attr($campaign->id); ?>" <?php selected($campaign_id, $campaign->id); ?>>
                        <?php echo esc_html($campaign->title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['campaign_id'] = (!empty($new_instance['campaign_id'])) ? intval($new_instance['campaign_id']) : '';
        
        return $instance;
    }
}
?>