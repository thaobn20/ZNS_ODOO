<?php
/**
 * Clean Admin Class for Advanced Quiz Manager - No Duplicates
 * File: admin/class-admin.php
 */

class AQM_Admin {
    
    private $db;
    
    public function __construct() {
        $this->db = new AQM_Database();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_aqm_save_campaign', array($this, 'ajax_save_campaign'));
        add_action('wp_ajax_aqm_save_question', array($this, 'ajax_save_question'));
        add_action('wp_ajax_aqm_delete_question', array($this, 'ajax_delete_question'));
        add_action('wp_ajax_aqm_save_gift', array($this, 'ajax_save_gift'));
        add_action('wp_ajax_aqm_delete_gift', array($this, 'ajax_delete_gift'));
        add_action('wp_ajax_aqm_export_responses', array($this, 'ajax_export_responses'));
    }
    
    public function add_admin_menus() {
        add_menu_page(
            'Quiz Manager',
            'Quiz Manager',
            'manage_options',
            'quiz-manager',
            array($this, 'dashboard_page'),
            'dashicons-feedback',
            30
        );
        
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
        
		// Add this menu item to your add_admin_menus() method:
		add_submenu_page(
			'quiz-manager',
			'Province Config',
			'Province Config',
			'manage_options',
			'quiz-manager-provinces',
			array($this, 'provinces_config_page')
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
            'Gifts & Rewards',
            'Gifts & Rewards',
            'manage_options',
            'quiz-manager-gifts',
            array($this, 'gifts_page')
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
            'Analytics',
            'Analytics',
            'manage_options',
            'quiz-manager-analytics',
            array($this, 'analytics_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'quiz-manager') !== false) {
            wp_enqueue_script('aqm-admin-js', AQM_PLUGIN_URL . 'assets/js/admin.js', 
                array('jquery', 'jquery-ui-sortable'), AQM_VERSION, true);
            wp_enqueue_style('aqm-admin-css', AQM_PLUGIN_URL . 'assets/css/admin.css', 
                array(), AQM_VERSION);
            
            wp_localize_script('aqm-admin-js', 'aqm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqm_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'advanced-quiz'),
                    'saved' => __('Saved successfully!', 'advanced-quiz'),
                    'error' => __('An error occurred. Please try again.', 'advanced-quiz'),
                    'loading' => __('Loading...', 'advanced-quiz'),
                    'save_first' => __('Please save the campaign first.', 'advanced-quiz')
                )
            ));
        }
    }
    
    // PAGE HANDLERS
    
    public function dashboard_page() {
        $stats = $this->db->get_dashboard_stats();
        ?>
        <div class="wrap aqm-admin-page">
            <h1>📊 Quiz Manager Dashboard</h1>
            
            <div class="aqm-stats-grid">
                <div class="aqm-stat-card">
                    <div class="aqm-stat-icon">🎯</div>
                    <div class="aqm-stat-content">
                        <h3><?php echo esc_html($stats['total_campaigns']); ?></h3>
                        <p>Total Campaigns</p>
                    </div>
                </div>
                
                <div class="aqm-stat-card">
                    <div class="aqm-stat-icon">✅</div>
                    <div class="aqm-stat-content">
                        <h3><?php echo esc_html($stats['active_campaigns']); ?></h3>
                        <p>Active Campaigns</p>
                    </div>
                </div>
                
                <div class="aqm-stat-card">
                    <div class="aqm-stat-icon">👥</div>
                    <div class="aqm-stat-content">
                        <h3><?php echo esc_html($stats['total_responses']); ?></h3>
                        <p>Total Responses</p>
                    </div>
                </div>
                
                <div class="aqm-stat-card">
                    <div class="aqm-stat-icon">🎁</div>
                    <div class="aqm-stat-content">
                        <h3><?php echo esc_html($stats['gifts_claimed']); ?></h3>
                        <p>Gifts Claimed</p>
                    </div>
                </div>
            </div>
            
            <div class="aqm-dashboard-widgets">
                <div class="aqm-widget">
                    <h3>🎯 Recent Campaigns</h3>
                    <?php $this->render_recent_campaigns(); ?>
                </div>
                
                <div class="aqm-widget">
                    <h3>📈 Quick Actions</h3>
                    <div style="padding: 20px;">
                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=add'); ?>" 
                           class="button button-primary">Create New Campaign</a>
                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-responses'); ?>" 
                           class="button">View All Responses</a>
                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-analytics'); ?>" 
                           class="button">View Analytics</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function campaigns_page() {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'add':
            case 'edit':
                $this->render_campaign_form($action);
                break;
            default:
                $this->render_campaigns_list();
        }
    }
    
    public function questions_page() {
        $campaign_id = $_GET['campaign_id'] ?? 0;
        
        if (!$campaign_id) {
            $this->render_select_campaign_page('questions');
            return;
        }
        
        $campaign = $this->db->get_campaign($campaign_id);
        if (!$campaign) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Campaign not found.</p></div></div>';
            return;
        }
        
        $this->render_questions_management($campaign);
    }
    
    public function gifts_page() {
        $campaign_id = $_GET['campaign_id'] ?? 0;
        
        if (!$campaign_id) {
            $this->render_select_campaign_page('gifts');
            return;
        }
        
        $campaign = $this->db->get_campaign($campaign_id);
        if (!$campaign) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Campaign not found.</p></div></div>';
            return;
        }
        
        $this->render_gifts_management($campaign);
    }
    
    public function responses_page() {
        $campaign_id = $_GET['campaign_id'] ?? 0;
        
        if (!$campaign_id) {
            $this->render_select_campaign_page('responses');
            return;
        }
        
        $campaign = $this->db->get_campaign($campaign_id);
        if (!$campaign) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Campaign not found.</p></div></div>';
            return;
        }
        
        $this->render_responses_page($campaign);
    }
    
    public function analytics_page() {
        $campaign_id = $_GET['campaign_id'] ?? 0;
        
        if (!$campaign_id) {
            $this->render_select_campaign_page('analytics');
            return;
        }
        
        $campaign = $this->db->get_campaign($campaign_id);
        if (!$campaign) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Campaign not found.</p></div></div>';
            return;
        }
        
        $this->render_analytics_page($campaign);
    }
    
    // RENDERING METHODS
    
    private function render_campaigns_list() {
        $campaigns = $this->db->get_campaigns();
        
        // Show success message if available
        if (isset($_GET['message'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($_GET['message']) . '</p></div>';
        }
        ?>
        <div class="wrap aqm-admin-page">
            <h1>
                🎯 Campaigns 
                <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=add'); ?>" 
                   class="button button-primary">Add New Campaign</a>
            </h1>
            
            <?php if (!empty($campaigns)): ?>
                <div class="aqm-table-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Participants</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $campaign): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($campaign->title); ?></strong>
                                        <?php if ($campaign->description): ?>
                                            <div style="color: #666; font-size: 0.9em;">
                                                <?php echo esc_html(wp_trim_words($campaign->description, 10)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="aqm-status aqm-status-<?php echo esc_attr($campaign->status); ?>">
                                            <?php echo esc_html(ucfirst($campaign->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $analytics = $this->db->get_response_analytics($campaign->id);
                                        echo esc_html($analytics['total_responses']);
                                        if ($campaign->max_participants > 0) {
                                            echo ' / ' . esc_html($campaign->max_participants);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($campaign->created_at))); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=edit&id=' . $campaign->id); ?>" 
                                           class="button button-small">Edit</a>
                                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-questions&campaign_id=' . $campaign->id); ?>" 
                                           class="button button-small">Questions</a>
                                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-gifts&campaign_id=' . $campaign->id); ?>" 
                                           class="button button-small">Gifts</a>
                                        <a href="<?php echo admin_url('admin.php?page=quiz-manager-responses&campaign_id=' . $campaign->id); ?>" 
                                           class="button button-small">Responses</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="aqm-empty-state">
                    <h3>🎯 No campaigns yet</h3>
                    <p>Create your first quiz campaign to get started!</p>
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=add'); ?>" 
                       class="button button-primary">Create Campaign</a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_campaign_form($action) {
        $campaign_id = $action === 'edit' ? intval($_GET['id']) : 0;
        $campaign = $campaign_id ? $this->db->get_campaign($campaign_id) : null;
        
        if ($action === 'edit' && !$campaign) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Campaign not found.</p></div></div>';
            return;
        }
        
        // Handle form submission
        if (isset($_POST['submit_campaign'])) {
            $this->handle_campaign_save();
            return;
        }
        ?>
        <div class="wrap aqm-admin-page">
            <h1><?php echo $action === 'edit' ? '✏️ Edit Campaign' : '➕ Create New Campaign'; ?></h1>
            
            <form id="aqm-campaign-form" method="post" class="aqm-form">
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
                        
                        <tr>
                            <th scope="row"><label for="max_participants">Max Participants</label></th>
                            <td>
                                <input type="number" id="max_participants" name="max_participants" 
                                       value="<?php echo esc_attr($campaign ? $campaign->max_participants : 0); ?>" min="0">
                                <p class="description">Set to 0 for unlimited participants.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit_campaign" class="button button-primary" value="<?php echo $action === 'edit' ? 'Update Campaign' : 'Create Campaign'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function handle_campaign_save() {
        if (!wp_verify_nonce($_POST['aqm_nonce'], 'aqm_save_campaign')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $data = array(
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description']),
            'status' => sanitize_text_field($_POST['status']),
            'max_participants' => intval($_POST['max_participants'])
        );
        
        // Validate required fields
        if (empty($data['title'])) {
            wp_die('Campaign title is required');
        }
        
        if ($campaign_id) {
            $result = $this->db->update_campaign($campaign_id, $data);
            $message = 'Campaign updated successfully!';
        } else {
            $data['created_by'] = get_current_user_id();
            $campaign_id = $this->db->create_campaign($data);
            $result = $campaign_id !== false;
            $message = 'Campaign created successfully!';
        }
        
        if ($result) {
            $redirect_url = admin_url('admin.php?page=quiz-manager-campaigns&message=' . urlencode($message));
            wp_redirect($redirect_url);
            exit;
        } else {
            wp_die('Failed to save campaign');
        }
    }
    
    private function render_select_campaign_page($context) {
        $campaigns = $this->db->get_campaigns(array('status' => 'active'));
        $page_titles = array(
            'questions' => '❓ Select Campaign for Questions',
            'gifts' => '🎁 Select Campaign for Gifts',
            'responses' => '📊 Select Campaign for Responses',
            'analytics' => '📈 Select Campaign for Analytics'
        );
        ?>
        <div class="wrap aqm-admin-page">
            <h1><?php echo esc_html($page_titles[$context]); ?></h1>
            
            <?php if (!empty($campaigns)): ?>
                <div class="aqm-campaign-grid">
                    <?php foreach ($campaigns as $campaign): ?>
                        <div class="aqm-campaign-card">
                            <h3><?php echo esc_html($campaign->title); ?></h3>
                            <p><?php echo esc_html($campaign->description); ?></p>
                            <a href="<?php echo admin_url("admin.php?page=quiz-manager-{$context}&campaign_id={$campaign->id}"); ?>" 
                               class="button button-primary">Manage <?php echo ucfirst($context); ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="aqm-empty-state">
                    <h3>🎯 No active campaigns</h3>
                    <p>Create and activate a campaign first to manage <?php echo $context; ?>.</p>
                    <a href="<?php echo admin_url('admin.php?page=quiz-manager-campaigns&action=add'); ?>" 
                       class="button button-primary">Create Campaign</a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    // PLACEHOLDER METHODS FOR OTHER PAGES
	/**
	 * Enhanced Questions Management with HTML Support
	 * Replace the render_questions_management method in admin/class-admin.php
	 */

	private function render_questions_management($campaign) {
		$questions = $this->db->get_campaign_questions($campaign->id);
		?>
		<div class="wrap aqm-admin-page">
			<h1>
				❓ Questions for "<?php echo esc_html($campaign->title); ?>"
				<button type="button" class="button button-primary" id="aqm-add-question">Add Question</button>
			</h1>
			
			<div class="aqm-campaign-info" style="background: #f0f8ff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
				<strong>Campaign:</strong> <?php echo esc_html($campaign->title); ?> | 
				<strong>Status:</strong> <span class="aqm-status aqm-status-<?php echo esc_attr($campaign->status); ?>"><?php echo esc_html(ucfirst($campaign->status)); ?></span> |
				<strong>Questions:</strong> <?php echo count($questions); ?>
			</div>
			
			<div id="aqm-questions-container" class="aqm-sortable-container">
				<?php if (!empty($questions)): ?>
					<?php foreach ($questions as $index => $question): ?>
						<?php $this->render_question_editor($question, $index + 1); ?>
					<?php endforeach; ?>
				<?php else: ?>
					<div class="aqm-empty-questions">
						<h3>📝 No questions yet</h3>
						<p>Add your first question to get started!</p>
					</div>
				<?php endif; ?>
			</div>
			
			<!-- Question Template -->
			<div id="aqm-question-template" style="display: none;">
				<?php $this->render_question_editor(null, 0); ?>
			</div>
			
			<div class="aqm-questions-footer" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
				<h3>💡 Question Tips:</h3>
				<ul>
					<li><strong>HTML Support:</strong> Use basic HTML tags like &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;, &lt;span&gt; in questions</li>
					<li><strong>Multiple Choice:</strong> Users can select multiple correct answers</li>
					<li><strong>Single Choice:</strong> Users can only select one answer</li>
					<li><strong>Drag & Drop:</strong> Reorder questions by dragging the question headers</li>
				</ul>
			</div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			const campaignId = <?php echo esc_js($campaign->id); ?>;
			
			// Add new question
			$('#aqm-add-question').on('click', function() {
				const template = $('#aqm-question-template').html();
				const questionCount = $('#aqm-questions-container .aqm-question-builder').length + 1;
				const newQuestion = $(template);
				
				// Update question number
				newQuestion.find('.aqm-question-header h4').text('New Question #' + questionCount);
				newQuestion.find('[name="order_index"]').val(questionCount);
				
				$('#aqm-questions-container .aqm-empty-questions').remove();
				$('#aqm-questions-container').append(newQuestion);
				
				// Focus on question text
				newQuestion.find('[name="question_text"]').focus();
			});
			
			// Save question
			$(document).on('click', '.aqm-save-question', function() {
				const questionBuilder = $(this).closest('.aqm-question-builder');
				saveQuestion(questionBuilder, campaignId);
			});
			
			// Delete question
			$(document).on('click', '.aqm-delete-question', function() {
				const questionBuilder = $(this).closest('.aqm-question-builder');
				const questionId = questionBuilder.data('question-id');
				
				if (confirm('Are you sure you want to delete this question?')) {
					if (questionId && questionId !== '0') {
						deleteQuestion(questionId, questionBuilder);
					} else {
						questionBuilder.remove();
						updateQuestionNumbers();
					}
				}
			});
			
			// Add option
			$(document).on('click', '.aqm-add-option', function() {
				const optionsList = $(this).siblings('.aqm-options-list');
				addNewOption(optionsList);
			});
			
			// Remove option
			$(document).on('click', '.aqm-remove-option', function() {
				const optionsList = $(this).closest('.aqm-options-list');
				if (optionsList.children().length > 1) {
					$(this).closest('.aqm-option-item').remove();
					updateOptionIndexes(optionsList);
				} else {
					alert('At least one option is required.');
				}
			});
			
			// Question type change
			$(document).on('change', '[name="question_type"]', function() {
				const questionBuilder = $(this).closest('.aqm-question-builder');
				const questionType = $(this).val();
				
				if (questionType === 'single_choice') {
					questionBuilder.find('.aqm-multiple-note').hide();
					questionBuilder.find('.aqm-single-note').show();
				} else {
					questionBuilder.find('.aqm-multiple-note').show();
					questionBuilder.find('.aqm-single-note').hide();
				}
			});
			
			// Make questions sortable
			$('#aqm-questions-container').sortable({
				handle: '.aqm-question-header',
				placeholder: 'aqm-question-placeholder',
				update: function() {
					updateQuestionNumbers();
					updateQuestionOrder();
				}
			});
			
			function saveQuestion(questionBuilder, campaignId) {
				const questionId = questionBuilder.data('question-id') || 0;
				const formData = new FormData();
				
				formData.append('action', 'aqm_save_question');
				formData.append('nonce', aqm_ajax.nonce);
				formData.append('campaign_id', campaignId);
				formData.append('question_id', questionId);
				formData.append('question_text', questionBuilder.find('[name="question_text"]').val());
				formData.append('question_type', questionBuilder.find('[name="question_type"]').val());
				formData.append('points', questionBuilder.find('[name="points"]').val());
				formData.append('order_index', questionBuilder.find('[name="order_index"]').val());
				
				// Collect options
				questionBuilder.find('.aqm-option-item').each(function(index) {
					const optionText = $(this).find('[name="option_text[]"]').val();
					const isCorrect = $(this).find('[name="option_correct[]"]').is(':checked');
					
					if (optionText.trim()) {
						formData.append('option_text[]', optionText);
						formData.append('option_correct[]', isCorrect ? index : '');
					}
				});
				
				const saveBtn = questionBuilder.find('.aqm-save-question');
				const originalText = saveBtn.text();
				
				$.ajax({
					url: aqm_ajax.ajax_url,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					beforeSend: function() {
						saveBtn.prop('disabled', true).text('Saving...');
					},
					success: function(response) {
						if (response.success) {
							showNotice('success', response.data.message);
							
							if (response.data.question_id && questionId === 0) {
								questionBuilder.data('question-id', response.data.question_id);
								questionBuilder.find('.aqm-question-header h4').text('Question #' + response.data.question_id);
							}
						} else {
							showNotice('error', response.data.message || 'Failed to save question');
						}
					},
					error: function() {
						showNotice('error', 'Network error occurred');
					},
					complete: function() {
						saveBtn.prop('disabled', false).text(originalText);
					}
				});
			}
			
			function deleteQuestion(questionId, questionBuilder) {
				$.ajax({
					url: aqm_ajax.ajax_url,
					type: 'POST',
					data: {
						action: 'aqm_delete_question',
						question_id: questionId,
						nonce: aqm_ajax.nonce
					},
					beforeSend: function() {
						questionBuilder.addClass('deleting');
					},
					success: function(response) {
						if (response.success) {
							questionBuilder.fadeOut(300, function() {
								$(this).remove();
								updateQuestionNumbers();
							});
							showNotice('success', response.data.message);
						} else {
							showNotice('error', response.data.message || 'Failed to delete question');
							questionBuilder.removeClass('deleting');
						}
					},
					error: function() {
						showNotice('error', 'Network error occurred');
						questionBuilder.removeClass('deleting');
					}
				});
			}
			
			function addNewOption(optionsList) {
				const optionIndex = optionsList.children().length;
				const optionHtml = `
					<div class="aqm-option-item">
						<input type="text" name="option_text[]" placeholder="Enter answer option" required>
						<label>
							<input type="checkbox" name="option_correct[]" value="${optionIndex}">
							<span>Correct Answer</span>
						</label>
						<button type="button" class="button button-small aqm-remove-option">Remove</button>
					</div>
				`;
				optionsList.append(optionHtml);
			}
			
			function updateOptionIndexes(optionsList) {
				optionsList.find('.aqm-option-item').each(function(index) {
					$(this).find('[name="option_correct[]"]').val(index);
				});
			}
			
			function updateQuestionNumbers() {
				$('#aqm-questions-container .aqm-question-builder').each(function(index) {
					$(this).find('.aqm-question-number').text('#' + (index + 1));
					$(this).find('[name="order_index"]').val(index + 1);
				});
			}
			
			function updateQuestionOrder() {
				const orders = {};
				$('#aqm-questions-container .aqm-question-builder').each(function(index) {
					const questionId = $(this).data('question-id');
					if (questionId && questionId !== '0') {
						orders[questionId] = index + 1;
					}
				});
				
				if (Object.keys(orders).length > 0) {
					$.ajax({
						url: aqm_ajax.ajax_url,
						type: 'POST',
						data: {
							action: 'aqm_reorder_questions',
							campaign_id: campaignId,
							orders: orders,
							nonce: aqm_ajax.nonce
						},
						success: function(response) {
							if (response.success) {
								showTempNotice('Questions reordered', 2000);
							}
						}
					});
				}
			}
			
			function showNotice(type, message) {
				const notice = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
				$('.wrap').first().prepend(notice);
				setTimeout(() => notice.fadeOut(), 5000);
			}
			
			function showTempNotice(message, duration = 3000) {
				const notice = $(`<div class="aqm-temp-notice">${message}</div>`);
				$('body').append(notice);
				notice.css({
					position: 'fixed',
					top: '32px',
					right: '20px',
					background: '#00a32a',
					color: '#fff',
					padding: '10px 15px',
					borderRadius: '4px',
					zIndex: 999999
				}).fadeIn(200);
				
				setTimeout(() => {
					notice.fadeOut(200, function() {
						$(this).remove();
					});
				}, duration);
			}
		});
		</script>
		<?php
	}

	private function render_question_editor($question = null, $question_number = 0) {
		$options = $question ? json_decode($question->options, true) : array();
		$question_id = $question ? $question->id : 0;
		?>
		<div class="aqm-question-builder" data-question-id="<?php echo esc_attr($question_id); ?>">
			<div class="aqm-question-header">
				<h4>
					<span class="aqm-question-number"><?php echo $question_number > 0 ? "#$question_number" : 'New'; ?></span>
					<?php echo $question ? esc_html($question->question_text) : 'New Question'; ?>
				</h4>
				<div class="aqm-question-controls">
					<button type="button" class="button button-small aqm-save-question">💾 Save</button>
					<button type="button" class="button button-small aqm-delete-question">🗑️ Delete</button>
				</div>
			</div>
			
			<div class="aqm-question-content">
				<div class="aqm-question-field">
					<label><strong>Question Text (HTML Supported)</strong> *</label>
					<textarea name="question_text" rows="3" class="large-text" required placeholder="Enter your question here. You can use HTML tags like <strong>, <em>, <br>, <span style='color:red'>"><?php echo $question ? esc_textarea($question->question_text) : ''; ?></textarea>
					<p class="description">💡 You can use HTML tags: &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;, &lt;span style="color:red"&gt;, etc.</p>
				</div>
				
				<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
					<div class="aqm-question-field">
						<label><strong>Question Type</strong></label>
						<select name="question_type">
							<option value="multiple_choice" <?php selected($question ? $question->question_type : 'multiple_choice', 'multiple_choice'); ?>>Multiple Choice</option>
							<option value="single_choice" <?php selected($question ? $question->question_type : '', 'single_choice'); ?>>Single Choice</option>
						</select>
						<p class="description aqm-multiple-note" <?php echo ($question && $question->question_type === 'single_choice') ? 'style="display:none"' : ''; ?>>✅ Multiple correct answers allowed</p>
						<p class="description aqm-single-note" <?php echo (!$question || $question->question_type !== 'single_choice') ? 'style="display:none"' : ''; ?>>⭕ Only one correct answer</p>
					</div>
					
					<div class="aqm-question-field">
						<label><strong>Points</strong></label>
						<input type="number" name="points" value="<?php echo esc_attr($question ? $question->points : 1); ?>" min="1" max="10">
						<p class="description">Points awarded for correct answer</p>
					</div>
					
					<div class="aqm-question-field">
						<label><strong>Order</strong></label>
						<input type="number" name="order_index" value="<?php echo esc_attr($question ? $question->order_index : $question_number); ?>" min="1">
						<p class="description">Display order (drag to reorder)</p>
					</div>
				</div>
				
				<div class="aqm-question-options">
					<label><strong>Answer Options</strong></label>
					<div class="aqm-options-list">
						<?php if (!empty($options)): ?>
							<?php foreach ($options as $index => $option): ?>
								<div class="aqm-option-item">
									<input type="text" name="option_text[]" value="<?php echo esc_attr($option['text']); ?>" placeholder="Enter answer option" required>
									<label>
										<input type="checkbox" name="option_correct[]" value="<?php echo $index; ?>" <?php checked(!empty($option['correct'])); ?>>
										<span>Correct Answer</span>
									</label>
									<button type="button" class="button button-small aqm-remove-option">Remove</button>
								</div>
							<?php endforeach; ?>
						<?php else: ?>
							<div class="aqm-option-item">
								<input type="text" name="option_text[]" placeholder="Enter answer option" required>
								<label>
									<input type="checkbox" name="option_correct[]" value="0">
									<span>Correct Answer</span>
								</label>
								<button type="button" class="button button-small aqm-remove-option">Remove</button>
							</div>
							<div class="aqm-option-item">
								<input type="text" name="option_text[]" placeholder="Enter answer option" required>
								<label>
									<input type="checkbox" name="option_correct[]" value="1">
									<span>Correct Answer</span>
								</label>
								<button type="button" class="button button-small aqm-remove-option">Remove</button>
							</div>
						<?php endif; ?>
					</div>
					<button type="button" class="button button-small aqm-add-option">➕ Add Option</button>
					<p class="description">💡 Mark correct answers by checking the checkbox. At least one correct answer is required.</p>
				</div>
			</div>
		</div>
		<?php
	}
    
	/**
	 * Gifts Management Interface
	 * Replace the render_gifts_management method in admin/class-admin.php
	 */
		private function render_gifts_management($campaign) {
			$gifts = $this->db->get_campaign_gifts($campaign->id);
			?>
			<div class="wrap aqm-admin-page">
				<h1>
					🎁 Gifts & Rewards for "<?php echo esc_html($campaign->title); ?>"
					<button type="button" class="button button-primary" id="aqm-add-gift">Add New Gift</button>
				</h1>
				
				<div class="aqm-campaign-info" style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
					<strong>Campaign:</strong> <?php echo esc_html($campaign->title); ?> | 
					<strong>Status:</strong> <span class="aqm-status aqm-status-<?php echo esc_attr($campaign->status); ?>"><?php echo esc_html(ucfirst($campaign->status)); ?></span> |
					<strong>Total Gifts:</strong> <?php echo count($gifts); ?>
				</div>
				
				<?php if (!empty($gifts)): ?>
					<div class="aqm-gifts-grid">
						<?php foreach ($gifts as $gift): ?>
							<?php $this->render_gift_card($gift); ?>
						<?php endforeach; ?>
					</div>
				<?php else: ?>
					<div class="aqm-empty-state">
						<h3>🎁 No gifts configured yet</h3>
						<p>Add rewards to motivate quiz participation!</p>
						<button type="button" class="button button-primary" onclick="document.getElementById('aqm-add-gift').click()">Add Your First Gift</button>
					</div>
				<?php endif; ?>
				
				<!-- Gift Modal -->
				<div id="aqm-gift-modal" class="aqm-modal" style="display: none;">
					<div class="aqm-modal-content">
						<div class="aqm-modal-header">
							<h2 id="aqm-gift-modal-title">🎁 Add New Gift</h2>
							<button type="button" class="aqm-modal-close" onclick="aqmCloseGiftModal()">&times;</button>
						</div>
						
						<form id="aqm-gift-form">
							<input type="hidden" id="gift_id" name="gift_id" value="0">
							
							<div class="aqm-modal-body">
								<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
									<div class="aqm-form-group">
										<label for="gift_title"><strong>Gift Title</strong> *</label>
										<input type="text" id="gift_title" name="title" class="large-text" required placeholder="e.g., 50,000 VND Voucher">
									</div>
									
									<div class="aqm-form-group">
										<label for="gift_type"><strong>Gift Type</strong></label>
										<select id="gift_type" name="gift_type">
											<option value="voucher">💳 Voucher/Coupon</option>
											<option value="topup">📱 Mobile Top-up</option>
											<option value="discount">💰 Discount Code</option>
											<option value="physical">📦 Physical Gift</option>
										</select>
									</div>
								</div>
								
								<div class="aqm-form-group">
									<label for="gift_description"><strong>Description</strong></label>
									<textarea id="gift_description" name="description" rows="3" class="large-text" placeholder="Describe how to use this gift..."></textarea>
								</div>
								
								<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
									<div class="aqm-form-group">
										<label for="gift_value"><strong>Value (VND)</strong></label>
										<input type="number" id="gift_value" name="gift_value" placeholder="50000" min="0" step="1000">
										<p class="description">Monetary value of the gift</p>
									</div>
									
									<div class="aqm-form-group">
										<label for="code_prefix"><strong>Code Prefix</strong></label>
										<input type="text" id="code_prefix" name="code_prefix" placeholder="HEALTH" maxlength="10" style="text-transform: uppercase;">
										<p class="description">e.g., HEALTH → HEALTH-ABC123</p>
									</div>
									
									<div class="aqm-form-group">
										<label for="quantity"><strong>Quantity Available</strong></label>
										<input type="number" id="quantity" name="quantity" value="100" min="1" required>
										<p class="description">How many can be awarded</p>
									</div>
								</div>
								
								<div class="aqm-form-group">
									<label for="min_score_percentage"><strong>Minimum Score Required (%)</strong></label>
									<div style="display: flex; align-items: center; gap: 10px;">
										<input type="range" id="min_score_range" min="0" max="100" value="70" style="flex: 1;">
										<input type="number" id="min_score_percentage" name="min_score_percentage" value="70" min="0" max="100" style="width: 80px;">
										<span>%</span>
									</div>
									<p class="description">Users must score at least this percentage to win this gift</p>
								</div>
								
								<div class="aqm-gift-preview" style="background: #f0f8ff; padding: 15px; border-radius: 8px; margin-top: 20px;">
									<h4>🔍 Preview:</h4>
									<div id="gift_preview_content">
										<strong id="preview_title">50,000 VND Voucher</strong><br>
										<span id="preview_description">Gift description will appear here</span><br>
										<em>Required score: <span id="preview_score">70</span>% | Available: <span id="preview_quantity">100</span></em>
									</div>
								</div>
							</div>
							
							<div class="aqm-modal-footer">
								<button type="button" class="button" onclick="aqmCloseGiftModal()">Cancel</button>
								<button type="submit" class="button button-primary">💾 Save Gift</button>
							</div>
						</form>
					</div>
				</div>
				
				<div class="aqm-gifts-help" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
					<h3>💡 Gift Configuration Tips:</h3>
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
						<div>
							<h4>🎯 Score Requirements:</h4>
							<ul>
								<li><strong>0-30%:</strong> Participation gifts</li>
								<li><strong>50-70%:</strong> Good performance</li>
								<li><strong>80-100%:</strong> Excellent performance</li>
							</ul>
						</div>
						<div>
							<h4>🎁 Gift Types:</h4>
							<ul>
								<li><strong>Voucher:</strong> Discount codes for stores</li>
								<li><strong>Top-up:</strong> Mobile phone credit</li>
								<li><strong>Discount:</strong> Percentage off products</li>
								<li><strong>Physical:</strong> Actual items to ship</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
			
			<script>
			jQuery(document).ready(function($) {
				const campaignId = <?php echo esc_js($campaign->id); ?>;
				
				// Add new gift
				$('#aqm-add-gift').on('click', function() {
					aqmOpenGiftModal();
				});
				
				// Edit gift
				$(document).on('click', '.aqm-edit-gift', function() {
					const giftData = $(this).data('gift');
					aqmOpenGiftModal(giftData);
				});
				
				// Delete gift
				$(document).on('click', '.aqm-delete-gift', function() {
					const giftId = $(this).data('id');
					const giftTitle = $(this).data('title');
					
					if (confirm(`Are you sure you want to delete "${giftTitle}"?`)) {
						deleteGift(giftId);
					}
				});
				
				// Form submission
				$('#aqm-gift-form').on('submit', function(e) {
					e.preventDefault();
					saveGift();
				});
				
				// Range slider sync
				$('#min_score_range').on('input', function() {
					$('#min_score_percentage').val($(this).val());
					updatePreview();
				});
				
				$('#min_score_percentage').on('input', function() {
					$('#min_score_range').val($(this).val());
					updatePreview();
				});
				
				// Live preview update
				$('#gift_title, #gift_description, #quantity').on('input', updatePreview);
				
				function updatePreview() {
					$('#preview_title').text($('#gift_title').val() || 'Gift Title');
					$('#preview_description').text($('#gift_description').val() || 'Gift description');
					$('#preview_score').text($('#min_score_percentage').val() || '0');
					$('#preview_quantity').text($('#quantity').val() || '0');
				}
				
				function saveGift() {
					const formData = new FormData($('#aqm-gift-form')[0]);
					formData.append('action', 'aqm_save_gift');
					formData.append('campaign_id', campaignId);
					formData.append('nonce', aqm_ajax.nonce);
					
					const submitBtn = $('#aqm-gift-form button[type="submit"]');
					const originalText = submitBtn.text();
					
					$.ajax({
						url: aqm_ajax.ajax_url,
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						beforeSend: function() {
							submitBtn.prop('disabled', true).text('💾 Saving...');
						},
						success: function(response) {
							if (response.success) {
								showNotice('success', response.data.message);
								aqmCloseGiftModal();
								location.reload(); // Refresh to show updated gifts
							} else {
								showNotice('error', response.data.message || 'Failed to save gift');
							}
						},
						error: function() {
							showNotice('error', 'Network error occurred');
						},
						complete: function() {
							submitBtn.prop('disabled', false).text(originalText);
						}
					});
				}
				
				function deleteGift(giftId) {
					$.ajax({
						url: aqm_ajax.ajax_url,
						type: 'POST',
						data: {
							action: 'aqm_delete_gift',
							gift_id: giftId,
							nonce: aqm_ajax.nonce
						},
						success: function(response) {
							if (response.success) {
								showNotice('success', 'Gift deleted successfully');
								location.reload();
							} else {
								showNotice('error', response.data.message || 'Failed to delete gift');
							}
						},
						error: function() {
							showNotice('error', 'Network error occurred');
						}
					});
				}
				
				function showNotice(type, message) {
					const notice = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
					$('.wrap').first().prepend(notice);
					setTimeout(() => notice.fadeOut(), 5000);
				}
			});
			
			// Global functions for modal
			window.aqmOpenGiftModal = function(giftData = null) {
				if (giftData) {
					// Edit mode
					$('#aqm-gift-modal-title').text('✏️ Edit Gift');
					$('#gift_id').val(giftData.id);
					$('#gift_title').val(giftData.title);
					$('#gift_description').val(giftData.description);
					$('#gift_type').val(giftData.gift_type);
					$('#gift_value').val(giftData.gift_value);
					$('#code_prefix').val(giftData.code_prefix);
					$('#quantity').val(giftData.quantity);
					
					const requirements = JSON.parse(giftData.requirements || '{}');
					const minScore = requirements.min_score_percentage || 70;
					$('#min_score_percentage').val(minScore);
					$('#min_score_range').val(minScore);
				} else {
					// Add mode
					$('#aqm-gift-modal-title').text('🎁 Add New Gift');
					$('#aqm-gift-form')[0].reset();
					$('#gift_id').val(0);
					$('#min_score_percentage').val(70);
					$('#min_score_range').val(70);
				}
				
				updatePreview();
				$('#aqm-gift-modal').show();
			};
			
			window.aqmCloseGiftModal = function() {
				$('#aqm-gift-modal').hide();
			};
			
			function updatePreview() {
				$('#preview_title').text($('#gift_title').val() || 'Gift Title');
				$('#preview_description').text($('#gift_description').val() || 'Gift description');
				$('#preview_score').text($('#min_score_percentage').val() || '0');
				$('#preview_quantity').text($('#quantity').val() || '0');
			}
			</script>
			
			<style>
			.aqm-gifts-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
				gap: 20px;
				margin-bottom: 30px;
			}
			
			.aqm-modal {
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				background: rgba(0, 0, 0, 0.5);
				z-index: 100000;
				display: flex;
				align-items: center;
				justify-content: center;
				padding: 20px;
			}
			
			.aqm-modal-content {
				background: white;
				border-radius: 8px;
				width: 100%;
				max-width: 600px;
				max-height: 90vh;
				overflow-y: auto;
			}
			
			.aqm-modal-header {
				padding: 20px;
				border-bottom: 1px solid #ddd;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}
			
			.aqm-modal-header h2 {
				margin: 0;
			}
			
			.aqm-modal-close {
				background: none;
				border: none;
				font-size: 24px;
				cursor: pointer;
				color: #666;
			}
			
			.aqm-modal-body {
				padding: 20px;
			}
			
			.aqm-modal-footer {
				padding: 20px;
				border-top: 1px solid #ddd;
				text-align: right;
			}
			
			.aqm-modal-footer .button {
				margin-left: 10px;
			}
			
			.aqm-form-group {
				margin-bottom: 15px;
			}
			
			.aqm-form-group label {
				display: block;
				margin-bottom: 5px;
				font-weight: 600;
			}
			</style>
			<?php
		}

		private function render_gift_card($gift) {
			$requirements = json_decode($gift->requirements, true) ?: array();
			$min_score = $requirements['min_score_percentage'] ?? 0;
			
			$type_icons = array(
				'voucher' => '💳',
				'topup' => '📱', 
				'discount' => '💰',
				'physical' => '📦'
			);
			
			$type_colors = array(
				'voucher' => '#e3f2fd',
				'topup' => '#f3e5f5',
				'discount' => '#e8f5e8', 
				'physical' => '#fff3e0'
			);
			
			$icon = $type_icons[$gift->gift_type] ?? '🎁';
			$bg_color = $type_colors[$gift->gift_type] ?? '#f5f5f5';
			?>
			<div class="aqm-gift-card" style="background: <?php echo $bg_color; ?>; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
				<div class="aqm-gift-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
					<div>
						<h3 style="margin: 0; color: #333;"><?php echo $icon; ?> <?php echo esc_html($gift->title); ?></h3>
						<span class="aqm-gift-type" style="background: rgba(0,0,0,0.1); padding: 3px 8px; border-radius: 12px; font-size: 12px; text-transform: uppercase;">
							<?php echo esc_html($gift->gift_type); ?>
						</span>
					</div>
					<div class="aqm-gift-actions">
						<button type="button" class="button button-small aqm-edit-gift" 
								data-gift='<?php echo json_encode($gift); ?>'>Edit</button>
						<button type="button" class="button button-small aqm-delete-gift" 
								data-id="<?php echo esc_attr($gift->id); ?>" 
								data-title="<?php echo esc_attr($gift->title); ?>">Delete</button>
					</div>
				</div>
				
				<div class="aqm-gift-details">
					<p style="margin: 0 0 10px 0; color: #666;"><?php echo esc_html($gift->description); ?></p>
					
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
						<div>
							<strong>💰 Value:</strong> <?php echo number_format($gift->gift_value); ?> VND
						</div>
						<div>
							<strong>📦 Available:</strong> <?php echo esc_html($gift->quantity); ?>
						</div>
					</div>
					
					<div style="background: rgba(255,255,255,0.7); padding: 10px; border-radius: 4px;">
						<strong>🎯 Required Score:</strong> <?php echo esc_html($min_score); ?>%
						<div style="background: #ddd; height: 4px; border-radius: 2px; margin-top: 5px;">
							<div style="background: #4caf50; height: 100%; width: <?php echo $min_score; ?>%; border-radius: 2px;"></div>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
			
    private function render_responses_page($campaign) {
        echo '<div class="wrap aqm-admin-page">';
        echo '<h1>📊 Responses for "' . esc_html($campaign->title) . '"</h1>';
        echo '<p>Response analytics will be implemented here.</p>';
        echo '</div>';
    }
    
    private function render_analytics_page($campaign) {
        echo '<div class="wrap aqm-admin-page">';
        echo '<h1>📈 Analytics for "' . esc_html($campaign->title) . '"</h1>';
        echo '<p>Analytics dashboard will be implemented here.</p>';
        echo '</div>';
    }
    
    public function handle_admin_actions() {
        // Handle form submissions and other admin actions
        if (isset($_POST['submit_campaign']) && isset($_POST['aqm_nonce'])) {
            $this->handle_campaign_save();
        }
    }
    
    // AJAX HANDLERS
    
    public function ajax_save_campaign() {
        try {
            $this->verify_nonce('aqm_admin_nonce');
            $this->check_admin_permissions();
            
            $campaign_id = intval($_POST['campaign_id']);
            $data = array(
                'title' => sanitize_text_field($_POST['title']),
                'description' => sanitize_textarea_field($_POST['description']),
                'status' => sanitize_text_field($_POST['status']),
                'max_participants' => intval($_POST['max_participants']),
                'start_date' => !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null,
                'end_date' => !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null,
                'settings' => json_encode($_POST['settings'] ?? array())
            );
            
            // Validate required fields
            if (empty($data['title'])) {
                throw new Exception('Campaign title is required');
            }
            
            if ($campaign_id) {
                $result = $this->db->update_campaign($campaign_id, $data);
                $message = 'Campaign updated successfully!';
            } else {
                $data['created_by'] = get_current_user_id();
                $campaign_id = $this->db->create_campaign($data);
                $result = $campaign_id !== false;
                $message = 'Campaign created successfully!';
            }
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => $message,
                    'campaign_id' => $campaign_id,
                    'redirect_url' => admin_url('admin.php?page=quiz-manager-campaigns')
                ));
            } else {
                throw new Exception('Failed to save campaign');
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function ajax_save_question() {
        // Placeholder for question saving
        wp_send_json_error(array('message' => 'Question saving not implemented yet'));
    }
    
    public function ajax_delete_question() {
        // Placeholder for question deletion
        wp_send_json_error(array('message' => 'Question deletion not implemented yet'));
    }
    
    public function ajax_save_gift() {
        // Placeholder for gift saving
        wp_send_json_error(array('message' => 'Gift saving not implemented yet'));
    }
    
    public function ajax_delete_gift() {
        // Placeholder for gift deletion
        wp_send_json_error(array('message' => 'Gift deletion not implemented yet'));
    }
    
    public function ajax_export_responses() {
        // Placeholder for response export
        wp_send_json_error(array('message' => 'Response export not implemented yet'));
    }
    
    private function verify_nonce($action) {
        $nonce = $_POST['nonce'] ?? $_POST['aqm_nonce'] ?? '';
        if (!wp_verify_nonce($nonce, $action)) {
            throw new Exception('Security verification failed');
        }
    }
    
    private function check_admin_permissions() {
        if (!current_user_can('manage_options')) {
            throw new Exception('Insufficient permissions');
        }
    }
    
    // Helper methods
    
    private function render_recent_campaigns() {
        $campaigns = $this->db->get_campaigns(array('limit' => 5));
        
        if (empty($campaigns)) {
            echo '<div style="padding: 20px; text-align: center; color: #666;">No campaigns yet.</div>';
            return;
        }
        
        echo '<ul class="aqm-recent-list">';
        foreach ($campaigns as $campaign) {
            $responses = $this->db->get_response_analytics($campaign->id);
            echo '<li>';
            echo '<a href="' . admin_url('admin.php?page=quiz-manager-campaigns&action=edit&id=' . $campaign->id) . '">';
            echo esc_html($campaign->title);
            echo '</a>';
            echo '<span class="aqm-response-count">' . esc_html($responses['total_responses']) . ' responses</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
	
	/****
	// Add this method to your admin class:
	***/
		
	public function provinces_config_page() {
		$provinces = $this->db->get_provinces();
		$provinces_count = count($provinces);
		
		// Handle JSON import
		if (isset($_POST['import_provinces_json']) && isset($_POST['provinces_json'])) {
			$this->handle_provinces_import();
			return;
		}
		?>
		<div class="wrap aqm-admin-page">
			<h1>🗺️ Vietnam Provinces Configuration</h1>
			
			<div class="aqm-stats-grid" style="margin-bottom: 30px;">
				<div class="aqm-stat-card">
					<div class="aqm-stat-icon">🏙️</div>
					<div class="aqm-stat-content">
						<h3><?php echo esc_html($provinces_count); ?></h3>
						<p>Provinces/Cities</p>
					</div>
				</div>
				
				<div class="aqm-stat-card">
					<div class="aqm-stat-icon">🏘️</div>
					<div class="aqm-stat-content">
						<h3><?php 
						$districts_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_districts");
						echo esc_html($districts_count ?: 0); 
						?></h3>
						<p>Districts</p>
					</div>
				</div>
				
				<div class="aqm-stat-card">
					<div class="aqm-stat-icon">🏠</div>
					<div class="aqm-stat-content">
						<h3><?php 
						$wards_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_wards");
						echo esc_html($wards_count ?: 0); 
						?></h3>
						<p>Wards/Communes</p>
					</div>
				</div>
				
				<div class="aqm-stat-card">
					<div class="aqm-stat-icon">✅</div>
					<div class="aqm-stat-content">
						<h3><?php echo $provinces_count >= 63 ? 'Complete' : 'Partial'; ?></h3>
						<p>Data Status</p>
					</div>
				</div>
			</div>
			
			<?php if ($provinces_count < 63): ?>
				<div class="notice notice-warning">
					<p><strong>⚠️ Incomplete Province Data:</strong> You have <?php echo $provinces_count; ?> provinces. Vietnam has 63 provinces/cities. Consider importing complete data.</p>
				</div>
			<?php endif; ?>
			
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
				<!-- Current Provinces -->
				<div class="aqm-widget">
					<h3>📍 Current Provinces (<?php echo $provinces_count; ?>)</h3>
					
					<?php if (!empty($provinces)): ?>
						<div style="max-height: 400px; overflow-y: auto; padding: 10px;">
							<?php foreach ($provinces as $province): ?>
								<div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #eee;">
									<div>
										<strong><?php echo esc_html($province->name); ?></strong>
										<small style="color: #666;">(<?php echo esc_html($province->code); ?>)</small>
									</div>
									<div style="font-size: 12px; color: #666;">
										<?php 
										$district_count = $this->wpdb->get_var($this->wpdb->prepare(
											"SELECT COUNT(*) FROM {$this->wpdb->prefix}aqm_districts WHERE province_code = %s", 
											$province->code
										));
										echo $district_count . ' districts';
										?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else: ?>
						<div style="padding: 20px; text-align: center; color: #666;">
							<p>❌ No provinces data found!</p>
							<p>Import province data to enable location features.</p>
						</div>
					<?php endif; ?>
				</div>
				
				<!-- Import Section -->
				<div class="aqm-widget">
					<h3>📥 Import Complete Vietnam Data</h3>
					
					<div style="padding: 20px;">
						<h4>🚀 Quick Import (Sample Data)</h4>
						<p>Import basic 5 major cities for testing:</p>
						<button type="button" class="button button-primary" onclick="aqmImportSampleProvinces()">
							Import Sample Data (5 Cities)
						</button>
						
						<hr style="margin: 20px 0;">
						
						<h4>📊 Full Import (JSON)</h4>
						<p>Import complete Vietnam administrative data:</p>
						
						<form method="post" id="provinces-import-form">
							<?php wp_nonce_field('import_provinces', 'import_nonce'); ?>
							
							<div style="margin-bottom: 15px;">
								<label for="provinces_json"><strong>JSON Data:</strong></label>
								<textarea name="provinces_json" id="provinces_json" rows="8" class="large-text" 
										  placeholder='Paste Vietnam provinces JSON data here...'></textarea>
								<p class="description">
									Paste JSON data in format: 
									<code>[{"code":"01","name":"Hà Nội","districts":[...]}, ...]</code>
								</p>
							</div>
							
							<div style="margin-bottom: 15px;">
								<label>
									<input type="checkbox" name="clear_existing" value="1"> 
									Clear existing data before import
								</label>
							</div>
							
							<button type="submit" name="import_provinces_json" class="button button-primary">
								📥 Import JSON Data
							</button>
						</form>
						
						<hr style="margin: 20px 0;">
						
						<h4>🔗 Get Complete Data</h4>
						<p>Download complete Vietnam administrative data:</p>
						<ul>
							<li><a href="https://raw.githubusercontent.com/madnh/hanhchinhvn/master/dist/tinh_tp.json" target="_blank">Provinces Only (JSON)</a></li>
							<li><a href="https://raw.githubusercontent.com/madnh/hanhchinhvn/master/dist/tree.json" target="_blank">Complete Tree (JSON)</a></li>
							<li><a href="https://danhmuc.gso.gov.vn/" target="_blank">Official GSO Data</a></li>
						</ul>
					</div>
				</div>
			</div>
			
			<!-- Province Usage in Quizzes -->
			<div class="aqm-widget" style="margin-top: 30px;">
				<h3>📊 Province Usage in Quiz Responses</h3>
				
				<div style="padding: 20px;">
					<?php 
					$province_usage = $this->wpdb->get_results(
						"SELECT province, COUNT(*) as count 
						 FROM {$this->wpdb->prefix}aqm_responses 
						 WHERE province IS NOT NULL AND province != '' 
						 GROUP BY province 
						 ORDER BY count DESC 
						 LIMIT 10"
					);
					
					if (!empty($province_usage)): ?>
						<h4>🏆 Top 10 Provinces by Quiz Participation:</h4>
						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
							<?php foreach ($province_usage as $usage): ?>
								<div style="background: #f0f8ff; padding: 10px; border-radius: 4px; text-align: center;">
									<strong><?php echo esc_html($usage->province); ?></strong><br>
									<span style="color: #666;"><?php echo esc_html($usage->count); ?> responses</span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else: ?>
						<p style="text-align: center; color: #666;">
							📭 No quiz responses with province data yet.<br>
							Province statistics will appear here once users start taking quizzes.
						</p>
					<?php endif; ?>
				</div>
			</div>
			
			<!-- Test Province Selection -->
			<div class="aqm-widget" style="margin-top: 30px;">
				<h3>🧪 Test Province Selection</h3>
				<div style="padding: 20px;">
					<p>Test how the province dropdown will work in your quiz:</p>
					
					<div style="max-width: 300px;">
						<label for="test_province"><strong>Select Province:</strong></label>
						<select id="test_province" class="regular-text">
							<option value="">Choose province...</option>
							<?php foreach ($provinces as $province): ?>
								<option value="<?php echo esc_attr($province->code); ?>">
									<?php echo esc_html($province->name); ?>
								</option>
							<?php endforeach; ?>
						</select>
						
						<div id="test_result" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px; display: none;">
							Selected: <strong id="selected_province"></strong>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			// Test province selection
			$('#test_province').on('change', function() {
				const selectedText = $(this).find('option:selected').text();
				const selectedValue = $(this).val();
				
				if (selectedValue) {
					$('#selected_province').text(selectedText + ' (' + selectedValue + ')');
					$('#test_result').show();
				} else {
					$('#test_result').hide();
				}
			});
			
			// Import form submission
			$('#provinces-import-form').on('submit', function(e) {
				const jsonData = $('#provinces_json').val().trim();
				
				if (!jsonData) {
					e.preventDefault();
					alert('Please paste JSON data first!');
					return false;
				}
				
				try {
					JSON.parse(jsonData);
				} catch (error) {
					e.preventDefault();
					alert('Invalid JSON format! Please check your data.');
					return false;
				}
				
				if (!confirm('Are you sure you want to import this data? This action may take a few moments.')) {
					e.preventDefault();
					return false;
				}
			});
		});
		
		// Quick sample import
		window.aqmImportSampleProvinces = function() {
			if (!confirm('Import sample province data (5 major cities)?')) return;
			
			const sampleData = [
				{"code":"01","name":"Hà Nội","name_en":"Hanoi","full_name":"Thành phố Hà Nội"},
				{"code":"79","name":"Hồ Chí Minh","name_en":"Ho Chi Minh","full_name":"Thành phố Hồ Chí Minh"},
				{"code":"48","name":"Đà Nẵng","name_en":"Da Nang","full_name":"Thành phố Đà Nẵng"},
				{"code":"31","name":"Hải Phòng","name_en":"Hai Phong","full_name":"Thành phố Hải Phòng"},
				{"code":"92","name":"Cần Thơ","name_en":"Can Tho","full_name":"Thành phố Cần Thơ"}
			];
			
			jQuery('#provinces_json').val(JSON.stringify(sampleData, null, 2));
			jQuery('#provinces-import-form').submit();
		};
		</script>
		<?php
	}

	private function handle_provinces_import() {
		if (!wp_verify_nonce($_POST['import_nonce'], 'import_provinces')) {
			wp_die('Security check failed');
		}
		
		if (!current_user_can('manage_options')) {
			wp_die('Unauthorized');
		}
		
		$json_data = stripslashes($_POST['provinces_json']);
		$clear_existing = isset($_POST['clear_existing']);
		
		if (empty($json_data)) {
			echo '<div class="notice notice-error"><p>No JSON data provided!</p></div>';
			return;
		}
		
		// Validate JSON
		$provinces_data = json_decode($json_data, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			echo '<div class="notice notice-error"><p>Invalid JSON format: ' . json_last_error_msg() . '</p></div>';
			return;
		}
		
		if (!is_array($provinces_data)) {
			echo '<div class="notice notice-error"><p>JSON data should be an array of provinces.</p></div>';
			return;
		}
		
		// Clear existing data if requested
		if ($clear_existing) {
			$this->wpdb->query("DELETE FROM {$this->wpdb->prefix}aqm_wards");
			$this->wpdb->query("DELETE FROM {$this->wpdb->prefix}aqm_districts");
			$this->wpdb->query("DELETE FROM {$this->wpdb->prefix}aqm_provinces");
		}
		
		// Import data
		$result = $this->db->import_provinces_data($provinces_data);
		
		echo '<div class="notice notice-success"><p>';
		echo '<strong>✅ Import Successful!</strong><br>';
		echo 'Imported: ' . $result['provinces'] . ' provinces, ';
		echo $result['districts'] . ' districts, ';
		echo $result['wards'] . ' wards.';
		echo '</p></div>';
		
		// Refresh the page to show new data
		echo '<script>setTimeout(function() { location.reload(); }, 2000);</script>';
	}
	
	
	
}
?>