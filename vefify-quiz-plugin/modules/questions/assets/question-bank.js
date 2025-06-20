/**
 * Question Bank JavaScript - BULLETPROOF VERSION
 * File: modules/questions/assets/question-bank.js
 * 
 * This version includes robust error handling and fallbacks to prevent undefined errors
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Global variables
    let optionCount = 0;
    let currentQuestionType = 'single_select';
    
    // CRITICAL: Preview state management to prevent flickering
    let previewStates = {}; // Tracks each question's preview state
    let isAnyPreviewLoading = false; // Prevents multiple AJAX calls
    
    // DEFENSIVE: Safe access to WordPress globals with fallbacks
    const safeGlobals = {
        ajaxurl: window.ajaxurl || (window.vefifyQuestionBank && window.vefifyQuestionBank.ajaxurl) || '/wp-admin/admin-ajax.php',
        nonce: getSecureNonce(),
        restUrl: (window.vefifyQuestionBank && window.vefifyQuestionBank.restUrl) || '/wp-json/vefify/v1/'
    };
    
    /**
     * Safely get nonce from multiple possible sources
     */
    function getSecureNonce() {
        // Try multiple sources for the nonce in order of preference
        if (window.vefifyQuestionBank && window.vefifyQuestionBank.nonce) {
            return window.vefifyQuestionBank.nonce;
        }
        
        if (window.wp && window.wp.ajax && window.wp.ajax.settings && window.wp.ajax.settings.nonce) {
            return window.wp.ajax.settings.nonce;
        }
        
        // Try jQuery AJAX settings
        if ($.ajaxSetup().headers && $.ajaxSetup().headers['X-WP-Nonce']) {
            return $.ajaxSetup().headers['X-WP-Nonce'];
        }
        
        // Look for nonce in meta tags (common WordPress pattern)
        const metaNonce = $('meta[name="wp-nonce"]').attr('content');
        if (metaNonce) {
            return metaNonce;
        }
        
        // Look for nonce in any form on the page
        const formNonce = $('input[name="_wpnonce"]').first().val();
        if (formNonce) {
            return formNonce;
        }
        
        // Last resort: empty string (will likely cause backend validation to fail gracefully)
        console.warn('Vefify Quiz: No WordPress nonce found. Preview functionality may be limited.');
        return '';
    }
    
    // Initialize
    init();
    
    /**
     * Initialize all functionality with enhanced error handling
     */
    function init() {
        try {
            optionCount = $('#answer-options .option-row').length;
            currentQuestionType = $('#question_type').val() || 'single_select';
            
            console.log('Question Bank initialized:', {
                optionCount: optionCount,
                questionType: currentQuestionType,
                ajaxUrl: safeGlobals.ajaxurl,
                hasNonce: !!safeGlobals.nonce
            });
            
            // Setup event handlers
            setupEventHandlers();
            
            // Initialize question type (with delay to ensure DOM is ready)
            setTimeout(function() {
                updateQuestionType();
            }, 300);
            
            // Initialize preview states for existing questions
            initializePreviewStates();
            
        } catch (error) {
            console.error('Vefify Quiz initialization error:', error);
            showError('Failed to initialize question bank. Please refresh the page.');
        }
    }
    
    /**
     * Initialize preview states for all questions on the page
     */
    function initializePreviewStates() {
        try {
            $('.toggle-preview').each(function() {
                const questionId = $(this).data('question-id');
                if (questionId) {
                    // Initialize each question with closed state
                    previewStates[questionId] = {
                        isOpen: false,
                        isLoading: false,
                        hasLoaded: false,
                        button: $(this),
                        previewRow: $('#preview-' + questionId)
                    };
                    
                    // Ensure preview row is hidden initially
                    $('#preview-' + questionId).hide();
                    
                    console.log('Initialized preview state for question:', questionId);
                }
            });
            
            console.log('All preview states initialized:', Object.keys(previewStates).length, 'questions');
            
        } catch (error) {
            console.error('Error initializing preview states:', error);
        }
    }
    
    /**
     * Setup all event handlers with error boundaries
     */
    function setupEventHandlers() {
        try {
            // Question type change
            $('#question_type').off('change.vefify').on('change.vefify', function() {
                try {
                    currentQuestionType = $(this).val();
                    console.log('Question type changed to:', currentQuestionType);
                    updateQuestionType();
                } catch (error) {
                    console.error('Error handling question type change:', error);
                }
            });
            
            // Add option button
            $('#add-option').off('click.vefify').on('click.vefify', handleAddOption);
            
            // Remove option button (delegated)
            $(document).off('click.vefify', '.remove-option').on('click.vefify', '.remove-option', handleRemoveOption);
            
            // Correct answer checkbox (delegated)
            $(document).off('change.vefify', '.option-correct-checkbox').on('change.vefify', '.option-correct-checkbox', handleCorrectAnswerChange);
            
            // Form submission
            $('#question-form').off('submit.vefify').on('submit.vefify', handleFormSubmit);
            
            // FIXED: Preview button handler with comprehensive error handling
            $(document).off('click.vefify', '.toggle-preview').on('click.vefify', '.toggle-preview', handlePreviewToggle);
            
            // Dismiss notices
            $(document).off('click.vefify', '.notice-dismiss').on('click.vefify', '.notice-dismiss', function() {
                $(this).parent().fadeOut();
            });
            
            // Add retry preview handler
            $(document).off('click.vefify', '.retry-preview').on('click.vefify', '.retry-preview', handleRetryPreview);
            
        } catch (error) {
            console.error('Error setting up event handlers:', error);
        }
    }
    
    /**
     * BULLETPROOF: Handle preview toggle with comprehensive error handling
     */
    function handlePreviewToggle(e) {
        try {
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(this);
            const questionId = $button.data('question-id');
            
            console.log('Preview toggle clicked for question:', questionId);
            
            // Validate question ID
            if (!questionId) {
                console.error('No question ID found on preview button');
                showError('Invalid question ID');
                return;
            }
            
            // Initialize state if it doesn't exist
            if (!previewStates[questionId]) {
                previewStates[questionId] = {
                    isOpen: false,
                    isLoading: false,
                    hasLoaded: false,
                    button: $button,
                    previewRow: $('#preview-' + questionId)
                };
            }
            
            const state = previewStates[questionId];
            
            // Prevent action if currently loading or if another preview is loading
            if (state.isLoading || isAnyPreviewLoading) {
                console.log('Preview action blocked - loading in progress');
                return;
            }
            
            // Check if preview row exists in DOM
            if (state.previewRow.length === 0) {
                console.error('Preview row not found for question:', questionId);
                showError('Preview container not found');
                return;
            }
            
            console.log('Current state for question', questionId, ':', {
                isOpen: state.isOpen,
                isLoading: state.isLoading,
                hasLoaded: state.hasLoaded,
                rowVisible: state.previewRow.is(':visible')
            });
            
            if (state.isOpen) {
                // Hide the preview
                hidePreview(questionId);
            } else {
                // Show the preview
                showPreview(questionId);
            }
            
        } catch (error) {
            console.error('Error in preview toggle handler:', error);
            showError('An error occurred while toggling preview. Please try again.');
        }
    }
    
    /**
     * Show preview for a question with enhanced error handling
     */
    function showPreview(questionId) {
        try {
            const state = previewStates[questionId];
            
            console.log('Showing preview for question:', questionId);
            
            // Update state
            state.isLoading = true;
            state.isOpen = true;
            isAnyPreviewLoading = true;
            
            // Update button to loading state
            updateButtonState(questionId, 'loading');
            
            // If content hasn't been loaded yet, load it
            if (!state.hasLoaded) {
                // Show loading placeholder first
                state.previewRow.find('.question-preview-content').html(`
                    <div class="preview-loading">
                        <div class="loading-spinner"></div>
                        <span class="loading-text">Loading question preview...</span>
                    </div>
                `);
                
                // Show the row with animation, then load content
                state.previewRow.slideDown(400, function() {
                    console.log('Preview row visible, loading content for question:', questionId);
                    loadPreviewContent(questionId);
                });
            } else {
                // Content already loaded, just show it
                state.previewRow.slideDown(400, function() {
                    finishShowPreview(questionId);
                });
            }
            
        } catch (error) {
            console.error('Error showing preview for question', questionId, ':', error);
            showPreviewError(questionId, 'Error displaying preview');
        }
    }
    
    /**
     * Hide preview for a question with error handling
     */
    function hidePreview(questionId) {
        try {
            const state = previewStates[questionId];
            
            console.log('Hiding preview for question:', questionId);
            
            // Update state
            state.isOpen = false;
            
            // Update button immediately to provide instant feedback
            updateButtonState(questionId, 'default');
            
            // Hide the row with animation
            state.previewRow.slideUp(400, function() {
                console.log('Preview hidden for question:', questionId);
            });
            
        } catch (error) {
            console.error('Error hiding preview for question', questionId, ':', error);
            // Even if there's an error, try to reset the button state
            updateButtonState(questionId, 'default');
        }
    }
    
    /**
     * BULLETPROOF: Load preview content via AJAX with comprehensive error handling
     */
    function loadPreviewContent(questionId) {
        try {
            console.log('Loading preview content via AJAX for question:', questionId);
            
            // Validate we have the necessary data
            if (!safeGlobals.ajaxurl) {
                throw new Error('AJAX URL not available');
            }
            
            // Prepare AJAX data with safe defaults
            const ajaxData = {
                action: 'vefify_load_question_preview',
                question_id: questionId,
                nonce: safeGlobals.nonce,
                // Add a timestamp to prevent caching issues
                _timestamp: new Date().getTime()
            };
            
            console.log('AJAX request data:', ajaxData);
            
            // Make AJAX request with comprehensive error handling
            $.ajax({
                url: safeGlobals.ajaxurl,
                type: 'POST',
                data: ajaxData,
                timeout: 30000, // 30 second timeout
                dataType: 'json', // Expect JSON response
                
                beforeSend: function(xhr) {
                    // Set additional headers if available
                    if (safeGlobals.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', safeGlobals.nonce);
                    }
                },
                
                success: function(response, textStatus, xhr) {
                    try {
                        console.log('AJAX response for question', questionId, ':', response);
                        
                        // Handle successful response
                        if (response && response.success && response.data) {
                            const state = previewStates[questionId];
                            if (state && state.previewRow) {
                                state.previewRow.find('.question-preview-content').html(response.data);
                                state.hasLoaded = true;
                                finishShowPreview(questionId);
                            } else {
                                throw new Error('Preview state or row not found after successful AJAX');
                            }
                        } else {
                            // Handle error response
                            const errorMsg = (response && response.data) 
                                ? response.data 
                                : (response && response.message) 
                                    ? response.message 
                                    : 'Unknown error occurred';
                            showPreviewError(questionId, errorMsg);
                        }
                        
                    } catch (error) {
                        console.error('Error processing AJAX success response:', error);
                        showPreviewError(questionId, 'Error processing preview data');
                    }
                },
                
                error: function(xhr, textStatus, errorThrown) {
                    console.error('AJAX error for question', questionId, ':', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        textStatus: textStatus,
                        errorThrown: errorThrown,
                        responseText: xhr.responseText
                    });
                    
                    let errorMsg = 'Network error. Please try again.';
                    
                    // Provide specific error messages based on the type of error
                    if (textStatus === 'timeout') {
                        errorMsg = 'Request timed out. Please try again.';
                    } else if (xhr.status === 403) {
                        errorMsg = 'Permission denied. Please refresh the page and try again.';
                    } else if (xhr.status === 404) {
                        errorMsg = 'Preview service not found. Please contact support.';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Server error. Please try again later.';
                    } else if (textStatus === 'parsererror') {
                        errorMsg = 'Invalid response format. Please try again.';
                    } else if (xhr.status === 0) {
                        errorMsg = 'Network connection failed. Please check your internet connection.';
                    }
                    
                    showPreviewError(questionId, errorMsg);
                },
                
                complete: function(xhr, textStatus) {
                    // This runs regardless of success or failure
                    console.log('AJAX request completed for question', questionId, 'with status:', textStatus);
                }
            });
            
        } catch (error) {
            console.error('Error initiating AJAX request for question', questionId, ':', error);
            showPreviewError(questionId, 'Failed to load preview. Please try again.');
        }
    }
    
    /**
     * Finish showing preview (after content loaded successfully)
     */
    function finishShowPreview(questionId) {
        try {
            const state = previewStates[questionId];
            
            console.log('Finishing show preview for question:', questionId);
            
            // Update state
            state.isLoading = false;
            isAnyPreviewLoading = false;
            
            // Update button to active state (this should show the "Hide Preview" text)
            updateButtonState(questionId, 'active');
            
        } catch (error) {
            console.error('Error finishing show preview:', error);
            // Try to reset loading state even if there's an error
            isAnyPreviewLoading = false;
            updateButtonState(questionId, 'default');
        }
    }
    
    /**
     * Show preview error with enhanced error information
     */
    function showPreviewError(questionId, errorMessage) {
        try {
            const state = previewStates[questionId];
            
            console.error('Preview error for question', questionId, ':', errorMessage);
            
            // Show user-friendly error content
            const errorHtml = `
                <div class="preview-error">
                    <div class="error-icon">⚠️</div>
                    <div class="error-message">${errorMessage}</div>
                    <div class="error-details">
                        <small>Question ID: ${questionId} | Time: ${new Date().toLocaleTimeString()}</small>
                    </div>
                    <button type="button" class="button button-small retry-preview" data-question-id="${questionId}">
                        Retry Preview
                    </button>
                </div>
            `;
            
            if (state && state.previewRow) {
                state.previewRow.find('.question-preview-content').html(errorHtml);
            }
            
            // Reset state
            state.isLoading = false;
            state.isOpen = false; // Set to false so clicking the button will try to show again
            state.hasLoaded = false; // Allow retry
            isAnyPreviewLoading = false;
            
            // Update button to default state
            updateButtonState(questionId, 'error');
            
        } catch (error) {
            console.error('Error showing preview error:', error);
            // Last resort: try to reset global state
            isAnyPreviewLoading = false;
        }
    }
    
    /**
     * ENHANCED: Update button state and appearance with error handling
     */
    function updateButtonState(questionId, state) {
        try {
            const buttonState = previewStates[questionId];
            if (!buttonState || !buttonState.button || buttonState.button.length === 0) {
                console.warn('Button state or button element not found for question:', questionId);
                return;
            }
            
            const $button = buttonState.button;
            
            // Remove all state classes
            $button.removeClass('loading active error');
            
            switch (state) {
                case 'loading':
                    $button.addClass('loading')
                           .prop('disabled', true)
                           .text('Loading...');
                    break;
                    
                case 'active':
                    $button.addClass('active')
                           .prop('disabled', false)
                           .text('Hide Preview');
                    // Update internal state
                    buttonState.isOpen = true;
                    break;
                    
                case 'error':
                    $button.addClass('error')
                           .prop('disabled', false)
                           .text('Preview (Error)');
                    break;
                    
                case 'default':
                default:
                    $button.prop('disabled', false)
                           .text('Preview');
                    // Update internal state
                    buttonState.isOpen = false;
                    break;
            }
            
            console.log('Button state updated for question', questionId, 'to:', state);
            
        } catch (error) {
            console.error('Error updating button state for question', questionId, ':', error);
        }
    }
    
    /**
     * Handle retry preview button with error handling
     */
    function handleRetryPreview(e) {
        try {
            e.preventDefault();
            e.stopPropagation();
            
            const questionId = $(this).data('question-id');
            console.log('Retry preview clicked for question:', questionId);
            
            if (previewStates[questionId]) {
                // Reset the loaded state and try again
                previewStates[questionId].hasLoaded = false;
                previewStates[questionId].isOpen = false;
                showPreview(questionId);
            } else {
                console.error('No preview state found for question:', questionId);
                showError('Unable to retry preview. Please refresh the page.');
            }
            
        } catch (error) {
            console.error('Error handling retry preview:', error);
            showError('Error retrying preview. Please refresh the page.');
        }
    }
    
    // [ALL OTHER FUNCTIONS REMAIN THE SAME - THEY DON'T NEED CHANGES]
    
    /**
     * Update question type and adjust interface
     */
    function updateQuestionType() {
        const type = $('#question_type').val();
        const $container = $('#answer-options');
        const $addSection = $('#add-option-section');
        const $helpText = $('#options-help');
        
        console.log('Updating question type UI for:', type);
        
        // Update help text with safe fallbacks
        const helpTexts = {
            'single_select': 'Select one correct answer',
            'multiple_select': 'Select all correct answers',
            'true_false': 'Select True or False'
        };
        
        $helpText.text(helpTexts[type] || helpTexts.single_select);
        
        // Remove previous styling
        $container.removeClass('question-type-true-false');
        $container.removeAttr('data-mode-text');
        
        if (type === 'true_false') {
            handleTrueFalseType($container, $addSection);
        } else {
            handleChoiceType($container, $addSection, type);
        }
        
        updateOptionNumbers();
        updateOptionCount();
    }
    
    /**
     * Handle True/False question type
     */
    function handleTrueFalseType($container, $addSection) {
        console.log('Setting up True/False question type');
        
        // Get all options
        const $allOptions = $container.find('.option-row');
        const currentCount = $allOptions.length;
        
        console.log('Current options before True/False setup:', currentCount);
        
        // FORCE REMOVE options beyond the first 2
        $allOptions.each(function(index) {
            if (index >= 2) {
                console.log('Removing option at index:', index);
                $(this).remove();
            }
        });
        
        // Ensure we have exactly 2 options
        let remainingCount = $container.find('.option-row').length;
        console.log('Options remaining after removal:', remainingCount);
        
        while (remainingCount < 2) {
            console.log('Adding missing True/False option at index:', remainingCount);
            const optionHtml = createOptionHtml(remainingCount);
            $container.append(optionHtml);
            remainingCount++;
        }
        
        // Double check - if still more than 2, force remove again
        const finalCheck = $container.find('.option-row');
        if (finalCheck.length > 2) {
            console.log('Final cleanup - removing excess options');
            finalCheck.slice(2).remove();
        }
        
        // Set True/False values
        const $options = $container.find('.option-row');
        if ($options.length >= 2) {
            // Set option A to "True"
            $options.eq(0).find('.option-text')
                .val('True')
                .prop('readonly', true)
                .addClass('readonly-option');
            
            // Set option B to "False"  
            $options.eq(1).find('.option-text')
                .val('False')
                .prop('readonly', true)
                .addClass('readonly-option');
            
            console.log('Set True/False values successfully');
        }
        
        // Hide controls
        $container.find('.remove-option').hide();
        $addSection.hide();
        
        // Add styling and indicator
        $container.addClass('question-type-true-false');
        $container.attr('data-mode-text', 'True/False Mode - Options are locked');
        
        // Update global count
        optionCount = 2;
        
        console.log('True/False setup complete. Final option count:', $container.find('.option-row').length);
    }
    
    /**
     * Handle choice question types (single/multiple)
     */
    function handleChoiceType($container, $addSection, type) {
        console.log('Setting up choice question type:', type);
        
        // Remove readonly and show controls
        $container.find('.option-text')
            .prop('readonly', false)
            .removeClass('readonly-option');
        $container.find('.remove-option').show();
        $addSection.show();
        
        // Clear True/False text if switching from True/False
        const $options = $container.find('.option-row');
        $options.each(function(index) {
            const $optionText = $(this).find('.option-text');
            const currentValue = $optionText.val();
            
            if (currentValue === 'True' || currentValue === 'False') {
                console.log('Clearing True/False text from option', index);
                $optionText.val('');
            }
        });
        
        // Ensure minimum 4 options for choice questions
        let currentCount = $container.find('.option-row').length;
        while (currentCount < 4) {
            console.log('Adding choice option at index:', currentCount);
            const optionHtml = createOptionHtml(currentCount);
            $container.append(optionHtml);
            currentCount++;
        }
        
        // Update global count
        optionCount = currentCount;
        
        console.log('Choice question setup complete. Final option count:', currentCount);
    }
    
    /**
     * Handle add option button click
     */
    function handleAddOption(e) {
        e.preventDefault();
        
        const questionType = $('#question_type').val();
        const maxOptions = questionType === 'true_false' ? 2 : 6;
        const currentOptions = $('#answer-options .option-row:visible').length;
        
        console.log('Add option clicked:', {
            questionType: questionType,
            current: currentOptions,
            max: maxOptions
        });
        
        if (currentOptions >= maxOptions) {
            const message = `You can have at most ${maxOptions} options for ${questionType.replace('_', ' ')} questions.`;
            showError(message);
            return;
        }
        
        // Create new option
        const optionHtml = createOptionHtml(optionCount);
        $('#answer-options').append(optionHtml);
        optionCount++;
        
        updateOptionNumbers();
        updateOptionCount();
        
        console.log('Option added. New count:', optionCount);
        
        // Focus on the new option text field
        $('#answer-options .option-row:last .option-text').focus();
    }
    
    /**
     * Handle remove option button click
     */
    function handleRemoveOption(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const questionType = $('#question_type').val();
        const minOptions = questionType === 'true_false' ? 2 : 2;
        const currentOptions = $('#answer-options .option-row:visible').length;
        
        console.log('Remove option clicked:', {
            questionType: questionType,
            current: currentOptions,
            min: minOptions
        });
        
        if (currentOptions <= minOptions) {
            const message = `You need at least ${minOptions} options for ${questionType.replace('_', ' ')} questions.`;
            showError(message);
            return;
        }
        
        const $row = $(this).closest('.option-row');
        $row.fadeOut(300, function() {
            $row.remove();
            updateOptionNumbers();
            updateOptionCount();
        });
        
        console.log('Option removed');
    }
    
    /**
     * Handle correct answer checkbox change
     */
    function handleCorrectAnswerChange() {
        const $row = $(this).closest('.option-row');
        const questionType = $('#question_type').val();
        
        if (questionType === 'single_select' && this.checked) {
            // For single choice, uncheck all other options
            $('.option-correct-checkbox').not(this).each(function() {
                if (this.checked) {
                    $(this).prop('checked', false);
                    $(this).closest('.option-row').removeClass('correct');
                }
            });
        }
        
        // Update visual state
        $row.toggleClass('correct', this.checked);
    }
    
    /**
     * Handle form submission
     */
    function handleFormSubmit(e) {
        console.log('Form submission triggered');
        
        // Save TinyMCE content before validation
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('question_text')) {
            tinyMCE.get('question_text').save();
        }
        
        const errors = validateForm();
        if (errors.length > 0) {
            e.preventDefault();
            showError(errors.join('<br>'));
            console.log('Form validation failed:', errors);
            return false;
        }
        
        console.log('Form validation passed, submitting...');
        
        // Show loading state
        const $submitBtn = $(this).find('input[type="submit"]');
        $submitBtn.prop('disabled', true).val('Saving...');
        
        // Form will submit normally
        return true;
    }
    
    /**
     * Create option HTML
     */
    function createOptionHtml(index) {
        return `
            <div class="option-row" data-index="${index}">
                <div class="option-header">
                    <div class="option-number">${String.fromCharCode(65 + index)}</div>
                    <div class="option-controls">
                        <label class="option-correct">
                            <input type="checkbox" name="options[${index}][is_correct]" value="1" class="option-correct-checkbox">
                            <span class="checkmark"></span>
                            Correct Answer
                        </label>
                        <button type="button" class="remove-option" title="Remove this option">×</button>
                    </div>
                </div>
                <div class="option-content">
                    <label class="option-label">Answer Option:</label>
                    <input type="text" name="options[${index}][text]" placeholder="Enter answer option..." class="option-text widefat" required>
                    <label class="option-label">Explanation (Optional):</label>
                    <textarea name="options[${index}][explanation]" placeholder="Optional: Explain why this answer is correct/incorrect..." rows="2" class="option-explanation widefat"></textarea>
                </div>
            </div>
        `;
    }
    
    /**
     * Update option numbers and input names
     */
    function updateOptionNumbers() {
        $('#answer-options .option-row').each(function(index) {
            const $row = $(this);
            
            // Update data attribute
            $row.attr('data-index', index);
            
            // Update option letter (A, B, C, D...)
            $row.find('.option-number').text(String.fromCharCode(65 + index));
            
            // Update input names
            $row.find('input, textarea').each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                if (name) {
                    const newName = name.replace(/options\[\d+\]/, `options[${index}]`);
                    $input.attr('name', newName);
                }
            });
        });
        
        console.log('Option numbers updated');
    }
    
    /**
     * Update global option count
     */
    function updateOptionCount() {
        optionCount = $('#answer-options .option-row').length;
        console.log('Updated option count to:', optionCount);
    }
    
    /**
     * Validate form before submission
     */
    function validateForm() {
        const errors = [];
        
        // Check question text
        let questionText = '';
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('question_text')) {
            questionText = tinyMCE.get('question_text').getContent().trim();
        } else {
            questionText = $('#question_text').val().trim();
        }
        
        if (!questionText) {
            errors.push('Question text is required.');
        }
        
        // Check options (only visible ones)
        const $visibleOptions = $('.option-row:visible');
        const checkedOptions = $visibleOptions.find('.option-correct-checkbox:checked').length;
        const filledOptions = $visibleOptions.find('.option-text').filter(function() {
            return $(this).val().trim() !== '';
        }).length;
        
        const questionType = $('#question_type').val();
        const minOptions = questionType === 'true_false' ? 2 : 2;
        const maxOptions = questionType === 'true_false' ? 2 : 6;
        
        console.log('Form validation:', {
            questionType: questionType,
            filled: filledOptions,
            checked: checkedOptions,
            visible: $visibleOptions.length
        });
        
        if (filledOptions < minOptions) {
            errors.push(`You need at least ${minOptions} answer options for this question type.`);
        }
        
        if (filledOptions > maxOptions) {
            errors.push(`You can have at most ${maxOptions} answer options for this question type.`);
        }
        
        if (checkedOptions === 0) {
            errors.push('You need to mark at least one correct answer.');
        }
        
        if (questionType === 'single_select' && checkedOptions > 1) {
            errors.push('Single choice questions can only have one correct answer.');
        }
        
        if (questionType === 'true_false' && checkedOptions !== 1) {
            errors.push('True/False questions must have exactly one correct answer.');
        }
        
        return errors;
    }
    
    /**
     * Show error notification
     */
    function showError(message) {
        // Remove existing notices
        $('.vefify-notice').remove();
        
        const errorHtml = `
            <div class="vefify-notice notice notice-error is-dismissible">
                <p><strong>Error:</strong> ${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                    <span aria-hidden="true">×</span>
                </button>
            </div>
        `;
        
        // Add error at top of page
        $(errorHtml).prependTo('.wrap').hide().fadeIn();
        
        // Scroll to top
        $('html, body').animate({
            scrollTop: $('.wrap').offset().top - 50
        }, 500);
        
        // Auto-hide after 8 seconds
        setTimeout(function() {
            $('.vefify-notice').fadeOut();
        }, 8000);
    }
    
    /**
     * Show success notification
     */
    function showSuccess(message) {
        $('.vefify-notice').remove();
        
        const successHtml = `
            <div class="vefify-notice notice notice-success is-dismissible">
                <p><strong>Success:</strong> ${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                    <span aria-hidden="true">×</span>
                </button>
            </div>
        `;
        
        $(successHtml).prependTo('.wrap').hide().fadeIn();
        
        setTimeout(function() {
            $('.vefify-notice').fadeOut();
        }, 5000);
    }
    
    // Make functions globally available for debugging
    if (window.console && window.console.log) {
        window.vefifyQuestionBank = {
            updateQuestionType: updateQuestionType,
            showError: showError,
            showSuccess: showSuccess,
            validateForm: validateForm,
            optionCount: function() { return optionCount; },
            currentType: function() { return currentQuestionType; },
            previewStates: previewStates,
            showPreview: showPreview,
            hidePreview: hidePreview,
            safeGlobals: safeGlobals
        };
    }
    
    console.log('Question Bank JavaScript loaded successfully with bulletproof error handling');
});