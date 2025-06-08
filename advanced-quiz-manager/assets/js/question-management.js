/**
 * Question Management JavaScript
 * Handles all frontend interactions for question management
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Question Management Object
    const QuestionManager = {
        
        // Current form state
        currentQuestionId: null,
        unsavedChanges: false,
        
        // Initialize the question management interface
        init: function() {
            this.bindEvents();
            this.initializeComponents();
            this.setupSortable();
        },
        
        // Bind all event handlers
        bindEvents: function() {
            // Modal controls
            $('#add-new-question').on('click', this.openQuestionModal.bind(this));
            $('.aqm-modal-close').on('click', this.closeQuestionModal.bind(this));
            $(document).on('click', '.aqm-modal', this.handleModalBackdropClick.bind(this));
            
            // Question CRUD operations
            $(document).on('click', '.edit-question', this.editQuestion.bind(this));
            $(document).on('click', '.delete-question', this.deleteQuestion.bind(this));
            $(document).on('click', '.duplicate-question', this.duplicateQuestion.bind(this));
            
            // Form submission
            $('#question-form').on('submit', this.saveQuestion.bind(this));
            
            // Tab navigation
            $('.tabs-nav a').on('click', this.switchTab.bind(this));
            
            // Question type handling
            $('#question-type').on('change', this.handleQuestionTypeChange.bind(this));
            
            // Multiple choice options
            $('#add-option').on('click', this.addOption.bind(this));
            $(document).on('click', '.remove-option', this.removeOption.bind(this));
            
            // Question list interactions
            $(document).on('click', '.question-header', this.toggleQuestionDetails.bind(this));
            
            // Toolbar actions
            $('#apply-bulk').on('click', this.applyBulkAction.bind(this));
            $('#export-questions').on('click', this.exportQuestions.bind(this));
            $('#import-questions').on('click', this.importQuestions.bind(this));
            $('#preview-campaign').on('click', this.previewCampaign.bind(this));
            
            // View options
            $('#group-by-type').on('change', this.toggleGroupByType.bind(this));
            $('#show-details').on('change', this.toggleShowDetails.bind(this));
            
            // Form change tracking
            $('#question-form input, #question-form select, #question-form textarea').on('change input', function() {
                QuestionManager.unsavedChanges = true;
            });
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts.bind(this));
            
            // Window beforeunload
            $(window).on('beforeunload', this.handleBeforeUnload.bind(this));
        },
        
        // Initialize components
        initializeComponents: function() {
            this.setupFormValidation();
            this.setupTooltips();
            this.loadProvinceData();
        },
        
        // Setup sortable functionality
        setupSortable: function() {
            if ($('#questions-container').length) {
                $('#questions-container').sortable({
                    handle: '.question-drag-handle',
                    placeholder: 'ui-state-highlight',
                    tolerance: 'pointer',
                    update: this.handleQuestionReorder.bind(this),
                    start: function(event, ui) {
                        ui.placeholder.height(ui.item.height());
                    }
                });
            }
        },
        
        // Open question modal
        openQuestionModal: function(questionId = null) {
            this.currentQuestionId = questionId;
            
            if (questionId) {
                this.loadQuestionData(questionId);
                $('#question-form-title').text('Edit Question');
            } else {
                this.resetQuestionForm();
                $('#question-form-title').text('Add New Question');
                this.setNextOrderIndex();
            }
            
            $('#question-form-modal').fadeIn(300);
            $('body').addClass('modal-open');
            this.unsavedChanges = false;
            
            // Focus first input
            setTimeout(() => {
                $('#question-text').focus();
            }, 300);
        },
        
        // Close question modal
        closeQuestionModal: function() {
            if (this.unsavedChanges && !confirm('You have unsaved changes. Are you sure you want to close?')) {
                return;
            }
            
            $('#question-form-modal').fadeOut(300);
            $('body').removeClass('modal-open');
            this.clearFormErrors();
            this.currentQuestionId = null;
            this.unsavedChanges = false;
        },
        
        // Handle modal backdrop clicks
        handleModalBackdropClick: function(e) {
            if (e.target === e.currentTarget) {
                this.closeQuestionModal();
            }
        },
        
        // Switch tabs
        switchTab: function(e) {
            e.preventDefault();
            
            const target = $(e.currentTarget).attr('href');
            
            // Update tab navigation
            $('.tab-item').removeClass('active');
            $(e.currentTarget).parent().addClass('active');
            
            // Update tab content
            $('.tab-content').removeClass('active');
            $(target).addClass('active');
        },
        
        // Reset question form
        resetQuestionForm: function() {
            $('#question-form')[0].reset();
            $('#question-id').val('');
            this.clearFormErrors();
            this.handleQuestionTypeChange();
            this.resetMultipleChoiceOptions();
            
            // Switch to first tab
            $('.tab-item').removeClass('active').first().addClass('active');
            $('.tab-content').removeClass('active').first().addClass('active');
        },
        
        // Load question data for editing
        loadQuestionData: function(questionId) {
            const self = this;
            
            this.showFormLoading(true);
            
            $.ajax({
                url: aqm_questions.ajax_url,
                type: 'POST',
                data: {
                    action: 'aqm_get_question',
                    nonce: aqm_questions.nonce,
                    question_id: questionId
                },
                success: function(response) {
                    self.showFormLoading(false);
                    
                    if (response.success) {
                        self.populateForm(response.data);
                    } else {
                        self.showNotification('Error loading question: ' + response.data, 'error');
                        self.closeQuestionModal();
                    }
                },
                error: function() {
                    self.showFormLoading(false);
                    self.showNotification('Network error occurred', 'error');
                    self.closeQuestionModal();
                }
            });
        },
        
        // Populate form with question data
        populateForm: function(question) {
            $('#question-id').val(question.id);
            $('#question-text').val(question.question_text);
            $('#question-type').val(question.question_type);
            $('#question-group').val(question.question_group);
            $('#order-index').val(question.order_index);
            $('#points').val(question.points);
            $('#scoring-weight').val(question.scoring_weight);
            $('#is-required').prop('checked', question.is_required == 1);
            $('#gift-eligibility').prop('checked', question.gift_eligibility == 1);
            
            // Handle question type specific options
            this.handleQuestionTypeChange();
            
            if (question.parsed_options) {
                this.populateTypeSpecificOptions(question.question_type, question.parsed_options);
            }
        },
        
        // Populate type-specific options
        populateTypeSpecificOptions: function(type, options) {
            switch (type) {
                case 'multiple_choice':
                    this.populateMultipleChoiceOptions(options);
                    break;
                case 'provinces':
                case 'districts':
                case 'wards':
                    this.populateLocationOptions(options);
                    break;
                case 'rating':
                    this.populateRatingOptions(options);
                    break;
                default:
                    if (options.placeholder) {
                        $('#placeholder').val(options.placeholder);
                    }
                    break;
            }
        },
        
        // Populate multiple choice options
        populateMultipleChoiceOptions: function(options) {
            if (options.choices) {
                $('#options-container').empty();
                options.choices.forEach((choice, index) => {
                    this.addOptionWithValue(choice, options.correct && options.correct.includes(index));
                });
            }
        },
        
        // Populate location options
        populateLocationOptions: function(options) {
            $('#load-districts').prop('checked', options.load_districts || false);
            $('#load-wards').prop('checked', options.load_wards || false);
            if (options.placeholder) {
                $('#placeholder').val(options.placeholder);
            }
        },
        
        // Populate rating options
        populateRatingOptions: function(options) {
            $('#max-rating').val(options.max_rating || 5);
            $('#rating-icon').val(options.icon || 'star');
        },
        
        // Save question
        saveQuestion: function(e) {
            e.preventDefault();
            
            if (!this.validateForm()) {
                return;
            }
            
            const formData = this.prepareFormData();
            const self = this;
            
            this.showFormLoading(true);
            
            $.ajax({
                url: aqm_questions.ajax_url,
                type: 'POST',
                data: formData + '&action=aqm_save_question&nonce=' + aqm_questions.nonce,
                success: function(response) {
                    self.showFormLoading(false);
                    
                    if (response.success) {
                        self.showNotification(response.data.message, 'success');
                        self.closeQuestionModal();
                        self.refreshQuestionsList();
                        self.unsavedChanges = false;
                    } else {
                        self.showFormError(response.data);
                    }
                },
                error: function() {
                    self.showFormLoading(false);
                    self.showFormError('Network error occurred. Please try again.');
                }
            });
        },
        
        // Prepare form data for submission
        prepareFormData: function() {
            let formData = $('#question-form').serialize();
            
            // Handle multiple choice options specially
            const questionType = $('#question-type').val();
            if (questionType === 'multiple_choice') {
                const options = [];
                const correctOptions = [];
                
                $('#options-container .option-row').each(function(index) {
                    const optionText = $(this).find('input[name="options[]"]').val().trim();
                    if (optionText) {
                        options.push(optionText);
                        if ($(this).find('input[name="correct_options[]"]').is(':checked')) {
                            correctOptions.push(index);
                        }
                    }
                });
                
                // Remove existing options from formData and add our processed ones
                formData = formData.replace(/&?options\[\]=[^&]*/g, '').replace(/&?correct_options\[\]=[^&]*/g, '');
                formData += '&options=' + encodeURIComponent(JSON.stringify(options));
                formData += '&correct_options=' + encodeURIComponent(JSON.stringify(correctOptions));
            }
            
            return formData;
        },
        
        // Edit question
        editQuestion: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const questionId = $(e.currentTarget).data('question-id');
            this.openQuestionModal(questionId);
        },
        
        // Delete question
        deleteQuestion: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (!confirm(aqm_questions.confirm_delete)) {
                return;
            }
            
            const questionId = $(e.currentTarget).data('question-id');
            const $item = $(e.currentTarget).closest('.question-item');
            const self = this;
            
            $.ajax({
                url: aqm_questions.ajax_url,
                type: 'POST',
                data: {
                    action: 'aqm_delete_question',
                    nonce: aqm_questions.nonce,
                    question_id: questionId
                },
                success: function(response) {
                    if (response.success) {
                        $item.fadeOut(300, function() {
                            $(this).remove();
                            self.updateQuestionNumbers();
                            self.updateQuestionsCount();
                        });
                        self.showNotification('Question deleted successfully', 'success');
                    } else {
                        self.showNotification('Error: ' + response.data, 'error');
                    }
                },
                error: function() {
                    self.showNotification('Network error occurred', 'error');
                }
            });
        },
        
        // Duplicate question
        duplicateQuestion: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (!confirm(aqm_questions.confirm_duplicate)) {
                return;
            }
            
            const questionId = $(e.currentTarget).data('question-id');
            const self = this;
            
            $.ajax({
                url: aqm_questions.ajax_url,
                type: 'POST',
                data: {
                    action: 'aqm_duplicate_question',
                    nonce: aqm_questions.nonce,
                    question_id: questionId
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification(response.data.message, 'success');
                        self.refreshQuestionsList();
                    } else {
                        self.showNotification('Error: ' + response.data, 'error');
                    }
                },
                error: function() {
                    self.showNotification('Network error occurred', 'error');
                }
            });
        },
        
        // Handle question type change
        handleQuestionTypeChange: function() {
            const questionType = $('#question-type').val();
            
            // Hide all type-specific sections
            $('#multiple-choice-options, #location-settings, #rating-settings').hide();
            
            // Show relevant sections based on type
            switch (questionType) {
                case 'multiple_choice':
                    $('#multiple-choice-options').show();
                    if ($('#options-container .option-row').length === 0) {
                        this.addOption();
                        this.addOption();
                    }
                    break;
                case 'provinces':
                case 'districts':
                case 'wards':
                    $('#location-settings').show();
                    break;
                case 'rating':
                    $('#rating-settings').show();
                    break;
            }
            
            // Update points placeholder
            this.updatePointsPlaceholder(questionType);
        },
        
        // Update points placeholder based on question type
        updatePointsPlaceholder: function(type) {
            const placeholders = {
                'multiple_choice': 'Points for correct answer',
                'rating': 'Max points for highest rating',
                'text': 'Points for completion',
                'email': 'Points for valid email',
                'phone': 'Points for valid phone',
                'provinces': 'Points for selection',
                'file_upload': 'Points for file upload'
            };
            
            $('#points').attr('placeholder', placeholders[type] || 'Points awarded');
        },
        
        // Add multiple choice option
        addOption: function(e) {
            if (e) e.preventDefault();
            
            const optionCount = $('#options-container .option-row').length;
            const optionHtml = `
                <div class="option-row">
                    <div class="option-input">
                        <input type="text" name="options[]" placeholder="Option ${optionCount + 1}" class="regular-text">
                    </div>
                    <div class="option-correct">
                        <label class="checkbox-label">
                            <input type="checkbox" name="correct_options[]" value="${optionCount}">
                            <span class="checkmark"></span>
                            Correct
                        </label>
                    </div>
                    <div class="option-actions">
                        <button type="button" class="button button-small remove-option">
                            <span class="dashicons dashicons-minus"></span>
                        </button>
                    </div>
                </div>
            `;
            
            $('#options-container').append(optionHtml);
            
            // Focus the new input
            $('#options-container .option-row:last input[type="text"]').focus();
        },
        
        // Add option with pre-filled value
        addOptionWithValue: function(value, isCorrect = false) {
            const optionCount = $('#options-container .option-row').length;
            const optionHtml = `
                <div class="option-row">
                    <div class="option-input">
                        <input type="text" name="options[]" placeholder="Option ${optionCount + 1}" 
                               class="regular-text" value="${value}">
                    </div>
                    <div class="option-correct">
                        <label class="checkbox-label">
                            <input type="checkbox" name="correct_options[]" value="${optionCount}" 
                                   ${isCorrect ? 'checked' : ''}>
                            <span class="checkmark"></span>
                            Correct
                        </label>
                    </div>
                    <div class="option-actions">
                        <button type="button" class="button button-small remove-option">
                            <span class="dashicons dashicons-minus"></span>
                        </button>
                    </div>
                </div>
            `;
            
            $('#options-container').append(optionHtml);
        },
        
        // Remove multiple choice option
        removeOption: function(e) {
            e.preventDefault();
            
            const $optionRow = $(e.currentTarget).closest('.option-row');
            
            // Don't allow removing if only one option left
            if ($('#options-container .option-row').length <= 1) {
                this.showNotification('At least one option is required', 'warning');
                return;
            }
            
            $optionRow.fadeOut(200, function() {
                $(this).remove();
                QuestionManager.updateOptionIndices();
            });
        },
        
        // Reset multiple choice options
        resetMultipleChoiceOptions: function() {
            $('#options-container').empty();
        },
        
        // Update option indices after removal
        updateOptionIndices: function() {
            $('#options-container .option-row').each(function(index) {
                $(this).find('input[name="options[]"]').attr('placeholder', `Option ${index + 1}`);
                $(this).find('input[name="correct_options[]"]').val(index);
            });
        },
        
        // Toggle question details
        toggleQuestionDetails: function(e) {
            // Don't toggle if clicking on buttons
            if ($(e.target).is('button, .dashicons, .badge')) {
                return;
            }
            
            const $details = $(e.currentTarget).siblings('.question-details');
            $details.slideToggle(300);
        },
        
        // Handle question reorder
        handleQuestionReorder: function(event, ui) {
            const questionIds = $('#questions-container').sortable('toArray', { 
                attribute: 'data-question-id' 
            });
            
            this.updateQuestionOrder(questionIds);
            this.updateQuestionNumbers();
        },
        
        // Update question order on server
        updateQuestionOrder: function(questionIds) {
            $.ajax({
                url: aqm_questions.ajax_url,
                type: 'POST',
                data: {
                    action: 'aqm_reorder_questions',
                    nonce: aqm_questions.nonce,
                    question_ids: questionIds
                },
                success: function(response) {
                    if (response.success) {
                        QuestionManager.showNotification('Questions reordered', 'success');
                    } else {
                        QuestionManager.showNotification('Error reordering: ' + response.data, 'error');
                        location.reload(); // Refresh to reset order
                    }
                },
                error: function() {
                    QuestionManager.showNotification('Network error occurred', 'error');
                    location.reload();
                }
            });
        },
        
        // Update question numbers in UI
        updateQuestionNumbers: function() {
            $('#questions-container .question-item').each(function(index) {
                $(this).find('.question-number .number').text(index + 1);
            });
        },
        
        // Update questions count
        updateQuestionsCount: function() {
            const count = $('#questions-container .question-item').length;
            $('.questions-count').text(`${count} questions`);
        },
        
        // Set next order index for new questions
        setNextOrderIndex: function() {
            const maxOrder = Math.max(...$('#questions-container .question-item').map(function() {
                return parseInt($(this).find('.question-header').attr('data-order') || 0);
            }).get());
            
            $('#order-index').val(isFinite(maxOrder) ? maxOrder + 1 : 1);
        },
        
        // Apply bulk action
        applyBulkAction: function(e) {
            e.preventDefault();
            
            const action = $('#bulk-action').val();
            if (!action) {
                this.showNotification('Please select an action', 'warning');
                return;
            }
            
            const selectedQuestions = $('.question-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedQuestions.length === 0) {
                this.showNotification('Please select questions', 'warning');
                return;
            }
            
            switch (action) {
                case 'delete':
                    this.bulkDeleteQuestions(selectedQuestions);
                    break;
                case 'duplicate':
                    this.bulkDuplicateQuestions(selectedQuestions);
                    break;
                default:
                    this.showNotification('Action not implemented yet', 'info');
            }
        },
        
        // Export questions
        exportQuestions: function(e) {
            e.preventDefault();
            
            const campaignId = $('input[name="campaign_id"]').val();
            if (!campaignId) {
                this.showNotification('No campaign selected', 'warning');
                return;
            }
            
            const url = aqm_questions.ajax_url + '?' + $.param({
                action: 'aqm_export_questions',
                campaign_id: campaignId,
                nonce: aqm_questions.nonce
            });
            
            window.open(url);
        },
        
        // Import questions
        importQuestions: function(e) {
            e.preventDefault();
            
            this.showNotification('Import functionality coming soon!', 'info');
        },
        
        // Preview campaign
        previewCampaign: function(e) {
            e.preventDefault();
            
            const campaignId = $('input[name="campaign_id"]').val();
            if (!campaignId) {
                this.showNotification('No campaign selected', 'warning');
                return;
            }
            
            // Open preview in new window/tab
            const previewUrl = window.location.origin + '?aqm_preview=' + campaignId;
            window.open(previewUrl, '_blank');
        },
        
        // Toggle group by type
        toggleGroupByType: function(e) {
            const isGrouped = $(e.target).is(':checked');
            
            if (isGrouped) {
                this.groupQuestionsByType();
            } else {
                this.ungroupQuestions();
            }
        },
        
        // Toggle show details
        toggleShowDetails: function(e) {
            const showDetails = $(e.target).is(':checked');
            
            if (showDetails) {
                $('.question-details').show();
            } else {
                $('.question-details').hide();
            }
        },
        
        // Group questions by type
        groupQuestionsByType: function() {
            const $container = $('#questions-container');
            const $questions = $container.find('.question-item').detach();
            
            // Group by type
            const groups = {};
            $questions.each(function() {
                const type = $(this).find('.question-type-badge').text().trim();
                if (!groups[type]) {
                    groups[type] = [];
                }
                groups[type].push(this);
            });
            
            // Add grouped questions back
            Object.keys(groups).sort().forEach(type => {
                $container.append(`<h4 class="question-group-header">${type}</h4>`);
                groups[type].forEach(question => {
                    $container.append(question);
                });
            });
        },
        
        // Ungroup questions
        ungroupQuestions: function() {
            const $container = $('#questions-container');
            const $questions = $container.find('.question-item').detach();
            
            // Remove group headers
            $container.find('.question-group-header').remove();
            
            // Sort by order index and add back
            $questions.sort((a, b) => {
                const orderA = parseInt($(a).find('.question-number .number').text());
                const orderB = parseInt($(b).find('.question-number .number').text());
                return orderA - orderB;
            });
            
            $questions.each(function() {
                $container.append(this);
            });
        },
        
        // Keyboard shortcuts
        handleKeyboardShortcuts: function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if ($('#question-form-modal').is(':visible')) {
                    $('#question-form').submit();
                }
            }
            
            // Escape to close modal
            if (e.key === 'Escape') {
                if ($('#question-form-modal').is(':visible')) {
                    this.closeQuestionModal();
                }
            }
            
            // Ctrl/Cmd + N for new question
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                this.openQuestionModal();
            }
        },
        
        // Handle before unload
        handleBeforeUnload: function(e) {
            if (this.unsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        },
        
        // Form validation
        validateForm: function() {
            this.clearFormErrors();
            
            let isValid = true;
            const errors = [];
            
            // Required fields
            if (!$('#question-text').val().trim()) {
                errors.push('Question text is required');
                $('#question-text').addClass('error');
                isValid = false;
            }
            
            // Multiple choice validation
            if ($('#question-type').val() === 'multiple_choice') {
                const options = $('#options-container input[name="options[]"]').map(function() {
                    return $(this).val().trim();
                }).get().filter(val => val);
                
                if (options.length < 2) {
                    errors.push('Multiple choice questions must have at least 2 options');
                    isValid = false;
                }
                
                const hasCorrect = $('#options-container input[name="correct_options[]"]:checked').length > 0;
                if (!hasCorrect) {
                    errors.push('At least one correct answer must be selected');
                    isValid = false;
                }
            }
            
            // Order validation
            const orderIndex = parseInt($('#order-index').val());
            if (orderIndex < 1) {
                errors.push('Order index must be greater than 0');
                $('#order-index').addClass('error');
                isValid = false;
            }
            
            // Points validation
            const points = parseInt($('#points').val());
            if (points < 0) {
                errors.push('Points cannot be negative');
                $('#points').addClass('error');
                isValid = false;
            }
            
            // Weight validation
            const weight = parseFloat($('#scoring-weight').val());
            if (weight < 0 || weight > 10) {
                errors.push('Scoring weight must be between 0 and 10');
                $('#scoring-weight').addClass('error');
                isValid = false;
            }
            
            if (!isValid) {
                this.showFormError(errors.join('<br>'));
                // Switch to first tab with error
                $('.tab-item').removeClass('active').first().addClass('active');
                $('.tab-content').removeClass('active').first().addClass('active');
            }
            
            return isValid;
        },
        
        // Show form loading state
        showFormLoading: function(loading) {
            const $submitBtn = $('#question-form button[type="submit"]');
            
            if (loading) {
                $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Saving...');
            } else {
                $submitBtn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Save Question');
            }
        },
        
        // Show form error
        showFormError: function(message) {
            this.clearFormErrors();
            $('#question-form').prepend(`
                <div class="aqm-form-error">
                    <span class="dashicons dashicons-warning"></span>
                    ${message}
                </div>
            `);
        },
        
        // Clear form errors
        clearFormErrors: function() {
            $('.aqm-form-error').remove();
            $('.error').removeClass('error');
        },
        
        // Show notification
        showNotification: function(message, type = 'info') {
            const $notification = $(`
                <div class="aqm-notification aqm-notification-${type}">
                    <span class="dashicons dashicons-${this.getNotificationIcon(type)}"></span>
                    ${message}
                    <button class="aqm-notification-close">&times;</button>
                </div>
            `);
            
            $('body').append($notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual close
            $notification.find('.aqm-notification-close').on('click', function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },
        
        // Get notification icon
        getNotificationIcon: function(type) {
            const icons = {
                'success': 'yes-alt',
                'error': 'warning',
                'warning': 'info',
                'info': 'info'
            };
            return icons[type] || 'info';
        },
        
        // Refresh questions list
        refreshQuestionsList: function() {
            setTimeout(() => {
                location.reload();
            }, 1000);
        },
        
        // Setup form validation
        setupFormValidation: function() {
            // Real-time validation
            $('#question-form input, #question-form select, #question-form textarea').on('blur', function() {
                $(this).removeClass('error');
            });
        },
        
        // Setup tooltips
        setupTooltips: function() {
            $('[title]').each(function() {
                $(this).attr('data-tooltip', $(this).attr('title'));
                $(this).removeAttr('title');
            });
        },
        
        // Load province data
        loadProvinceData: function() {
            if (typeof aqm_questions.provinces_data !== 'undefined') {
                this.provincesData = JSON.parse(aqm_questions.provinces_data);
            }
        }
    };
    
    // Initialize Question Manager
    QuestionManager.init();
    
    // Add custom CSS for notifications and loading states
    if ($('#aqm-question-styles').length === 0) {
        $('head').append(`
            <style id="aqm-question-styles">
                .aqm-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    min-width: 300px;
                    animation: slideInRight 0.3s ease;
                }
                
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                
                .aqm-notification-success {
                    background: #d4edda;
                    color: #155724;
                    border-left: 4px solid #28a745;
                }
                
                .aqm-notification-error {
                    background: #f8d7da;
                    color: #721c24;
                    border-left: 4px solid #dc3545;
                }
                
                .aqm-notification-warning {
                    background: #fff3cd;
                    color: #856404;
                    border-left: 4px solid #ffc107;
                }
                
                .aqm-notification-info {
                    background: #d1ecf1;
                    color: #0c5460;
                    border-left: 4px solid #17a2b8;
                }
                
                .aqm-notification-close {
                    background: none;
                    border: none;
                    font-size: 20px;
                    cursor: pointer;
                    margin-left: auto;
                    opacity: 0.7;
                }
                
                .aqm-notification-close:hover {
                    opacity: 1;
                }
                
                .aqm-form-error {
                    background: #f8d7da;
                    color: #721c24;
                    padding: 12px 15px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                    border-left: 4px solid #dc3545;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .error {
                    border-color: #dc3545 !important;
                    box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2) !important;
                }
                
                .spin {
                    animation: spin 1s linear infinite;
                }
                
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                
                body.modal-open {
                    overflow: hidden;
                }
                
                .question-group-header {
                    background: #f8f9fa;
                    padding: 10px 15px;
                    margin: 20px 0 10px 0;
                    border-left: 4px solid #667eea;
                    color: #333;
                    font-size: 14px;
                    text-transform: uppercase;
                    font-weight: 600;
                }
            </style>
        `);
    }
});