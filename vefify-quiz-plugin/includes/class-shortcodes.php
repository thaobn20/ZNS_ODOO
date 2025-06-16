<?php
/**
 * Shortcodes Manager
 * File: includes/class-shortcodes.php
 * 
 * Handles all quiz shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Shortcodes {
    
    private $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new Vefify_Quiz_Database();
    }
    
    /**
     * Register all shortcodes
     */
    public function register_all() {
        add_shortcode('vefify_quiz', array($this, 'render_quiz_shortcode'));
        add_shortcode('vefify_campaign', array($this, 'render_campaign_shortcode'));
        add_shortcode('vefify_campaign_list', array($this, 'render_campaign_list_shortcode'));
        add_shortcode('vefify_quiz_question', array($this, 'render_question_shortcode'));
        add_shortcode('vefify_leaderboard', array($this, 'render_leaderboard_shortcode'));
    }
    
    /**
     * Main quiz shortcode [vefify_quiz campaign_id="1"]
     */
    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 1,
            'template' => 'mobile',
            'theme' => 'default',
            'show_progress' => true,
            'show_timer' => true,
            'auto_submit' => false
        ), $atts);
        
        $campaign_id = intval($atts['campaign_id']);
        if (!$campaign_id) {
            return $this->render_error('Campaign ID is required');
        }
        
        // Get campaign data
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return $this->render_error('Campaign not found or inactive');
        }
        
        // Enqueue assets
        $this->enqueue_quiz_assets();
        
        // Generate unique container ID
        $container_id = 'vefify-quiz-' . $campaign_id . '-' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>" 
             class="vefify-quiz-container theme-<?php echo esc_attr($atts['theme']); ?>" 
             data-campaign-id="<?php echo esc_attr($campaign_id); ?>"
             data-config='<?php echo esc_attr($this->get_quiz_config($campaign, $atts)); ?>'>
            
            <!-- Quiz Header -->
            <div class="quiz-header">
                <h1 class="quiz-title"><?php echo esc_html($campaign->name); ?></h1>
                <?php if ($campaign->description): ?>
                    <p class="quiz-description"><?php echo esc_html($campaign->description); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Progress Bar -->
            <?php if ($atts['show_progress']): ?>
                <div class="quiz-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="progress-text">
                        <span class="current-question">0</span> / 
                        <span class="total-questions"><?php echo $campaign->questions_per_quiz; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Timer -->
            <?php if ($atts['show_timer'] && $campaign->time_limit): ?>
                <div class="quiz-timer">
                    <span class="timer-icon">⏱️</span>
                    <span class="timer-display" data-seconds="<?php echo $campaign->time_limit; ?>">
                        <?php echo gmdate('i:s', $campaign->time_limit); ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <!-- Registration Form -->
            <div class="quiz-step quiz-registration active">
                <div class="step-content">
                    <h2><?php esc_html_e('Join the Quiz', 'vefify-quiz'); ?></h2>
                    <form class="quiz-registration-form">
                        <div class="form-group">
                            <label for="participant-name"><?php esc_html_e('Full Name', 'vefify-quiz'); ?> *</label>
                            <input type="text" id="participant-name" name="full_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="participant-phone"><?php esc_html_e('Phone Number', 'vefify-quiz'); ?> *</label>
                            <input type="tel" id="participant-phone" name="phone_number" required 
                                   placeholder="0901234567">
                        </div>
                        
                        <div class="form-group">
                            <label for="participant-province"><?php esc_html_e('Province/City', 'vefify-quiz'); ?> *</label>
                            <select id="participant-province" name="province" required>
                                <option value=""><?php esc_html_e('Select your province/city', 'vefify-quiz'); ?></option>
                                <?php echo $this->get_vietnam_provinces_options(); ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="participant-pharmacy"><?php esc_html_e('Pharmacy Code', 'vefify-quiz'); ?></label>
                            <input type="text" id="participant-pharmacy" name="pharmacy_code" 
                                   placeholder="<?php esc_attr_e('Optional', 'vefify-quiz'); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-large">
                            <?php esc_html_e('Start Quiz', 'vefify-quiz'); ?> →
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Loading Step -->
            <div class="quiz-step quiz-loading">
                <div class="step-content">
                    <div class="loading-spinner"></div>
                    <h2><?php esc_html_e('Loading Questions...', 'vefify-quiz'); ?></h2>
                    <p><?php esc_html_e('Please wait while we prepare your quiz.', 'vefify-quiz'); ?></p>
                </div>
            </div>
            
            <!-- Quiz Questions Step -->
            <div class="quiz-step quiz-questions">
                <div class="step-content">
                    <div class="question-container">
                        <!-- Questions will be dynamically loaded here -->
                    </div>
                    
                    <div class="quiz-navigation">
                        <button type="button" class="btn btn-secondary btn-prev" disabled>
                            ← <?php esc_html_e('Previous', 'vefify-quiz'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-next">
                            <?php esc_html_e('Next', 'vefify-quiz'); ?> →
                        </button>
                        <button type="button" class="btn btn-success btn-submit" style="display: none;">
                            <?php esc_html_e('Submit Quiz', 'vefify-quiz'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Results Step -->
            <div class="quiz-step quiz-results">
                <div class="step-content">
                    <div class="results-header">
                        <div class="results-icon">🎉</div>
                        <h2 class="results-title"><?php esc_html_e('Quiz Complete!', 'vefify-quiz'); ?></h2>
                    </div>
                    
                    <div class="results-summary">
                        <div class="score-display">
                            <span class="score-number">0</span>
                            <span class="score-total">/ <?php echo $campaign->questions_per_quiz; ?></span>
                        </div>
                        <div class="score-percentage">0%</div>
                        <div class="score-message"></div>
                    </div>
                    
                    <!-- Gift Section (shown if applicable) -->
                    <div class="results-gift" style="display: none;">
                        <div class="gift-icon">🎁</div>
                        <h3 class="gift-title"><?php esc_html_e('Congratulations!', 'vefify-quiz'); ?></h3>
                        <div class="gift-details">
                            <div class="gift-name"></div>
                            <div class="gift-code"></div>
                            <div class="gift-description"></div>
                        </div>
                    </div>
                    
                    <div class="results-actions">
                        <button type="button" class="btn btn-primary btn-share">
                            <?php esc_html_e('Share Results', 'vefify-quiz'); ?>
                        </button>
                        <button type="button" class="btn btn-secondary btn-restart">
                            <?php esc_html_e('Take Another Quiz', 'vefify-quiz'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Error Step -->
            <div class="quiz-step quiz-error">
                <div class="step-content">
                    <div class="error-icon">❌</div>
                    <h2 class="error-title"><?php esc_html_e('Something went wrong', 'vefify-quiz'); ?></h2>
                    <p class="error-message"></p>
                    <button type="button" class="btn btn-primary btn-retry">
                        <?php esc_html_e('Try Again', 'vefify-quiz'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize quiz
            new VefifyQuiz('<?php echo esc_js($container_id); ?>');
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Campaign info shortcode [vefify_campaign id="1"]
     */
    public function render_campaign_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'show_description' => true,
            'show_stats' => false,
            'show_dates' => true,
            'template' => 'card'
        ), $atts);
        
        $campaign_id = intval($atts['id']);
        if (!$campaign_id) {
            return $this->render_error('Campaign ID is required');
        }
        
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return $this->render_error('Campaign not found');
        }
        
        ob_start();
        ?>
        <div class="vefify-campaign-card template-<?php echo esc_attr($atts['template']); ?>">
            <div class="campaign-header">
                <h3 class="campaign-title"><?php echo esc_html($campaign->name); ?></h3>
                <div class="campaign-status <?php echo $campaign->is_active ? 'active' : 'inactive'; ?>">
                    <?php echo $campaign->is_active ? esc_html__('Active', 'vefify-quiz') : esc_html__('Inactive', 'vefify-quiz'); ?>
                </div>
            </div>
            
            <?php if ($atts['show_description'] && $campaign->description): ?>
                <div class="campaign-description">
                    <?php echo wpautop(esc_html($campaign->description)); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_dates']): ?>
                <div class="campaign-dates">
                    <div class="date-item">
                        <span class="date-label"><?php esc_html_e('Start:', 'vefify-quiz'); ?></span>
                        <span class="date-value"><?php echo date_i18n(get_option('date_format'), strtotime($campaign->start_date)); ?></span>
                    </div>
                    <div class="date-item">
                        <span class="date-label"><?php esc_html_e('End:', 'vefify-quiz'); ?></span>
                        <span class="date-value"><?php echo date_i18n(get_option('date_format'), strtotime($campaign->end_date)); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="campaign-meta">
                <div class="meta-item">
                    <span class="meta-icon">❓</span>
                    <span class="meta-text"><?php echo $campaign->questions_per_quiz; ?> <?php esc_html_e('questions', 'vefify-quiz'); ?></span>
                </div>
                <?php if ($campaign->time_limit): ?>
                    <div class="meta-item">
                        <span class="meta-icon">⏱️</span>
                        <span class="meta-text"><?php echo floor($campaign->time_limit / 60); ?> <?php esc_html_e('minutes', 'vefify-quiz'); ?></span>
                    </div>
                <?php endif; ?>
                <div class="meta-item">
                    <span class="meta-icon">🎯</span>
                    <span class="meta-text"><?php echo $campaign->pass_score; ?> <?php esc_html_e('to pass', 'vefify-quiz'); ?></span>
                </div>
            </div>
            
            <?php if ($atts['show_stats']): ?>
                <div class="campaign-stats">
                    <?php
                    $stats = $this->get_campaign_stats($campaign_id);
                    ?>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($stats['participants']); ?></span>
                        <span class="stat-label"><?php esc_html_e('Participants', 'vefify-quiz'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['completion_rate']; ?>%</span>
                        <span class="stat-label"><?php esc_html_e('Completion Rate', 'vefify-quiz'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($campaign->is_active): ?>
                <div class="campaign-actions">
                    <a href="<?php echo esc_url(add_query_arg('campaign_id', $campaign_id, get_permalink())); ?>" 
                       class="btn btn-primary">
                        <?php esc_html_e('Take Quiz', 'vefify-quiz'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Campaign list shortcode [vefify_campaign_list]
     */
    public function render_campaign_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'status' => 'active',
            'template' => 'grid',
            'show_description' => true,
            'orderby' => 'created',
            'order' => 'DESC'
        ), $atts);
        
        $campaigns = $this->get_campaigns($atts);
        
        if (empty($campaigns)) {
            return '<div class="vefify-no-campaigns">' . esc_html__('No campaigns found.', 'vefify-quiz') . '</div>';
        }
        
        ob_start();
        ?>
        <div class="vefify-campaigns-list template-<?php echo esc_attr($atts['template']); ?>">
            <?php foreach ($campaigns as $campaign): ?>
                <div class="campaign-list-item">
                    <div class="campaign-item-header">
                        <h4 class="campaign-item-title">
                            <a href="<?php echo esc_url(add_query_arg('campaign_id', $campaign->id, get_permalink())); ?>">
                                <?php echo esc_html($campaign->name); ?>
                            </a>
                        </h4>
                        <span class="campaign-item-status <?php echo $campaign->is_active ? 'active' : 'inactive'; ?>">
                            <?php echo $campaign->is_active ? esc_html__('Active', 'vefify-quiz') : esc_html__('Inactive', 'vefify-quiz'); ?>
                        </span>
                    </div>
                    
                    <?php if ($atts['show_description'] && $campaign->description): ?>
                        <div class="campaign-item-description">
                            <?php echo esc_html(wp_trim_words($campaign->description, 20)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="campaign-item-meta">
                        <span class="meta-questions"><?php echo $campaign->questions_per_quiz; ?> <?php esc_html_e('questions', 'vefify-quiz'); ?></span>
                        <?php if ($campaign->time_limit): ?>
                            <span class="meta-time"><?php echo floor($campaign->time_limit / 60); ?> <?php esc_html_e('min', 'vefify-quiz'); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($campaign->is_active): ?>
                        <div class="campaign-item-actions">
                            <a href="<?php echo esc_url(add_query_arg('campaign_id', $campaign->id, get_permalink())); ?>" 
                               class="btn btn-primary btn-small">
                                <?php esc_html_e('Join Quiz', 'vefify-quiz'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Single question shortcode [vefify_quiz_question id="1"]
     */
    public function render_question_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'show_answer' => false,
            'show_explanation' => false,
            'interactive' => true
        ), $atts);
        
        $question_id = intval($atts['id']);
        if (!$question_id) {
            return $this->render_error('Question ID is required');
        }
        
        $question = $this->get_question($question_id);
        if (!$question) {
            return $this->render_error('Question not found');
        }
        
        ob_start();
        ?>
        <div class="vefify-single-question" data-question-id="<?php echo $question->id; ?>">
            <div class="question-header">
                <div class="question-meta">
                    <span class="question-category"><?php echo esc_html(ucfirst($question->category ?: 'General')); ?></span>
                    <span class="question-difficulty difficulty-<?php echo esc_attr($question->difficulty); ?>">
                        <?php echo esc_html(ucfirst($question->difficulty)); ?>
                    </span>
                    <span class="question-points"><?php echo $question->points; ?> point<?php echo $question->points !== 1 ? 's' : ''; ?></span>
                </div>
            </div>
            
            <div class="question-text">
                <h3><?php echo esc_html($question->question_text); ?></h3>
            </div>
            
            <div class="question-options">
                <?php
                $options = $this->get_question_options($question_id);
                foreach ($options as $index => $option):
                ?>
                    <div class="option-item <?php echo ($atts['show_answer'] && $option->is_correct) ? 'correct' : ''; ?>">
                        <?php if ($atts['interactive']): ?>
                            <label>
                                <input type="<?php echo $question->question_type === 'multiple_select' ? 'checkbox' : 'radio'; ?>" 
                                       name="question_<?php echo $question->id; ?>" 
                                       value="<?php echo $option->id; ?>"
                                       <?php echo ($atts['show_answer'] && $option->is_correct) ? 'checked' : ''; ?>>
                                <span class="option-text"><?php echo esc_html($option->option_text); ?></span>
                            </label>
                        <?php else: ?>
                            <span class="option-marker"><?php echo chr(65 + $index); ?>.</span>
                            <span class="option-text"><?php echo esc_html($option->option_text); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($atts['show_explanation'] && $question->explanation): ?>
                <div class="question-explanation">
                    <h4><?php esc_html_e('Explanation:', 'vefify-quiz'); ?></h4>
                    <p><?php echo esc_html($question->explanation); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Leaderboard shortcode [vefify_leaderboard campaign_id="1"]
     */
    public function render_leaderboard_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'limit' => 10,
            'anonymous' => true,
            'show_scores' => true,
            'show_times' => true,
            'template' => 'list'
        ), $atts);
        
        $leaderboard = $this->get_leaderboard($atts);
        
        if (empty($leaderboard)) {
            return '<div class="vefify-no-results">' . esc_html__('No results found.', 'vefify-quiz') . '</div>';
        }
        
        ob_start();
        ?>
        <div class="vefify-leaderboard template-<?php echo esc_attr($atts['template']); ?>">
            <h3 class="leaderboard-title"><?php esc_html_e('🏆 Leaderboard', 'vefify-quiz'); ?></h3>
            
            <div class="leaderboard-list">
                <?php foreach ($leaderboard as $index => $entry): ?>
                    <div class="leaderboard-item rank-<?php echo $index + 1; ?>">
                        <div class="rank-badge">
                            <?php
                            if ($index === 0) echo '🥇';
                            elseif ($index === 1) echo '🥈';
                            elseif ($index === 2) echo '🥉';
                            else echo '#' . ($index + 1);
                            ?>
                        </div>
                        
                        <div class="participant-info">
                            <div class="participant-name">
                                <?php 
                                if ($atts['anonymous']) {
                                    echo esc_html__('Anonymous User', 'vefify-quiz');
                                } else {
                                    echo esc_html($entry->full_name);
                                }
                                ?>
                            </div>
                            <div class="participant-location">
                                <?php echo esc_html(ucfirst($entry->province)); ?>
                            </div>
                        </div>
                        
                        <?php if ($atts['show_scores']): ?>
                            <div class="score-info">
                                <div class="score"><?php echo $entry->score; ?>/<?php echo $entry->total_questions; ?></div>
                                <div class="percentage"><?php echo round(($entry->score / $entry->total_questions) * 100); ?>%</div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($atts['show_times'] && $entry->completion_time): ?>
                            <div class="time-info">
                                <?php echo gmdate('i:s', $entry->completion_time); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Helper Methods
     */
    
    private function get_campaign($campaign_id) {
        return $this->database->get_results(
            "SELECT * FROM {$this->database->get_table_name('campaigns')} WHERE id = %d AND is_active = 1",
            array($campaign_id)
        )[0] ?? null;
    }
    
    private function get_campaigns($args) {
        $defaults = array(
            'limit' => 10,
            'status' => 'active',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array();
        $params = array();
        
        if ($args['status'] === 'active') {
            $where[] = 'is_active = 1';
        } elseif ($args['status'] === 'inactive') {
            $where[] = 'is_active = 0';
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order_clause = "ORDER BY {$args['orderby']} {$args['order']}";
        $limit_clause = "LIMIT " . intval($args['limit']);
        
        $query = "SELECT * FROM {$this->database->get_table_name('campaigns')} {$where_clause} {$order_clause} {$limit_clause}";
        
        return $this->database->get_results($query, $params);
    }
    
    private function get_question($question_id) {
        return $this->database->get_results(
            "SELECT * FROM {$this->database->get_table_name('questions')} WHERE id = %d AND is_active = 1",
            array($question_id)
        )[0] ?? null;
    }
    
    private function get_question_options($question_id) {
        return $this->database->get_results(
            "SELECT * FROM {$this->database->get_table_name('question_options')} WHERE question_id = %d ORDER BY order_index",
            array($question_id)
        );
    }
    
    private function get_campaign_stats($campaign_id) {
        $participants = $this->database->get_results(
            "SELECT COUNT(*) as total, COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed FROM {$this->database->get_table_name('participants')} WHERE campaign_id = %d",
            array($campaign_id)
        )[0] ?? null;
        
        return array(
            'participants' => $participants->total ?? 0,
            'completion_rate' => $participants->total > 0 ? round(($participants->completed / $participants->total) * 100) : 0
        );
    }
    
    private function get_leaderboard($args) {
        $where = array('completed_at IS NOT NULL');
        $params = array();
        
        if ($args['campaign_id']) {
            $where[] = 'campaign_id = %d';
            $params[] = intval($args['campaign_id']);
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where);
        $limit_clause = 'LIMIT ' . intval($args['limit']);
        
        $query = "SELECT * FROM {$this->database->get_table_name('participants')} {$where_clause} ORDER BY score DESC, completion_time ASC {$limit_clause}";
        
        return $this->database->get_results($query, $params);
    }
    
    private function get_quiz_config($campaign, $atts) {
        return json_encode(array(
            'campaign_id' => $campaign->id,
            'questions_per_quiz' => $campaign->questions_per_quiz,
            'time_limit' => $campaign->time_limit,
            'pass_score' => $campaign->pass_score,
            'show_progress' => $atts['show_progress'],
            'show_timer' => $atts['show_timer'],
            'auto_submit' => $atts['auto_submit'],
            'api_endpoints' => array(
                'check_participation' => rest_url('vefify/v1/check-participation'),
                'start_quiz' => rest_url('vefify/v1/start-quiz'),
                'submit_quiz' => rest_url('vefify/v1/submit-quiz')
            )
        ));
    }
    
    private function get_vietnam_provinces_options() {
        $provinces = array(
            'hanoi' => 'Hanoi',
            'hcm' => 'Ho Chi Minh City',
            'danang' => 'Da Nang',
            'haiphong' => 'Hai Phong',
            'cantho' => 'Can Tho',
            // Add more provinces as needed
        );
        
        $options = '';
        foreach ($provinces as $value => $label) {
            $options .= '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        
        return $options;
    }
    
    private function enqueue_quiz_assets() {
        if (!wp_script_is('vefify-quiz-frontend', 'registered')) {
            wp_enqueue_style('vefify-quiz-frontend', VEFIFY_QUIZ_PLUGIN_URL . 'assets/css/quiz-frontend.css', array(), VEFIFY_QUIZ_VERSION);
            wp_enqueue_script('vefify-quiz-frontend', VEFIFY_QUIZ_PLUGIN_URL . 'assets/js/quiz-frontend.js', array('jquery'), VEFIFY_QUIZ_VERSION, true);
        }
    }
    
    private function render_error($message) {
        return '<div class="vefify-error alert alert-error">' . esc_html($message) . '</div>';
    }
}