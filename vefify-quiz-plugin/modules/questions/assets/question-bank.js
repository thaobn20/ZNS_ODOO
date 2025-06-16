/**
 * Question Bank JavaScript
 * File: modules/questions/assets/question-bank.js
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Global variables
    let optionCount = 0;
    let currentQuestionType = 'single_select';
    
    // Initialize
    init();
    
    /**
     * Initialize all functionality
     */
    function init() {
        optionCount = $('#answer-options .option-row').length;
        currentQuestionType = $('#question_type').val() || 'single_select';
        
        console.log('Question Bank initialized:', {
            optionCount: optionCount,
            questionType: currentQuestionType
        });
        
        // Setup event handlers
        setupEventHandlers();
        
        // Initialize question type (with delay to ensure DOM is ready)
        setTimeout(function() {
            updateQuestionType();
        }, 300);
    }
    
    /**
     * Setup all event handlers
     */
    function setupEventHandlers() {
        // Question type change
        $('#question_type').off('change.vefify').on('change.vefify', function() {
            currentQuestionType = $(this).val();
            console.log('Question type changed to:', currentQuestionType);
            updateQuestionType();
        });
        
        // Add option button
        $('#add-option').off('click.vefify').on('click.vefify', handleAddOption);
        
        // Remove option button (delegated)
        $(document).off('click.vefify', '.remove-option').on('click.vefify', '.remove-option', handleRemoveOption);
        
        // Correct answer checkbox (delegated)
        $(document).off('change.vefify', '.option-correct-checkbox').on('change.vefify', '.option-correct-checkbox', handleCorrectAnswerChange);
        
        // Form submission
        $('#question-form').off('submit.vefify').on('submit.vefify', handleFormSubmit);
        
        // Preview button (delegated)
        $(document).off('click.vefify', '.toggle-preview').on('click.vefify', '.toggle-preview', handlePreviewToggle);
        
        // Dismiss notices
        $(document).off('click.vefify', '.notice-dismiss').on('click.vefify', '.notice-dismiss', function() {
            $(this).parent().fadeOut();
        });
    }
    
    /**
     * Update question type and adjust interface
     */
    function updateQuestionType() {
        const type = $('#question_type').val();
        const $container = $('#answer-options');
        const $addSection = $('#add-option-section');
        const $helpText = $('#options-help');
        
        console.log('Updating question type UI for:', type);
        
        // Update help text
        const helpTexts = {
            'single_select': vefifyQuestionBank.strings.selectOne,
            'multiple_select': vefifyQuestionBank.strings.selectMultiple,
            'true_false': vefifyQuestionBank.strings.selectTrueFalse
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
                .val(vefifyQuestionBank.strings.true)
                .prop('readonly', true)
                .addClass('readonly-option');
            
            // Set option B to "False"  
            $options.eq(1).find('.option-text')
                .val(vefifyQuestionBank.strings.false)
                .prop('readonly', true)
                .addClass('readonly-option');
            
            console.log('Set True/False values successfully');
        }
        
        // Hide controls
        $container.find('.remove-option').hide();
        $addSection.hide();
        
        // Add styling and indicator
        $container.addClass('question-type-true-false');
        $container.attr('data-mode-text', vefifyQuestionBank.strings.trueFalseMode);
        
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
            
            if (currentValue === vefifyQuestionBank.strings.true || 
                currentValue === vefifyQuestionBank.strings.false ||
                currentValue === 'True' || 
                currentValue === 'False') {
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
     * Handle preview toggle
     */
    function handlePreviewToggle(e) {
        e.preventDefault();
        
        const questionId = $(this).data('question-id');
        const $previewRow = $('#preview-' + questionId);
        const $button = $(this);
        
        console.log('Preview toggle clicked for question:', questionId);
        
        if ($previewRow.is(':visible')) {
            $previewRow.slideUp(300);
            $button.text('Preview');
        } else {
            // Show loading
            $previewRow.find('.question-preview-content').html(
                '<div class="preview-loading">' + vefifyQuestionBank.strings.loading + '</div>'
            );
            $previewRow.slideDown(300);
            $button.text(vefifyQuestionBank.strings.loading);
            
            // Load preview via AJAX
            $.post(vefifyQuestionBank.ajaxurl, {
                action: 'vefify_load_question_preview',
                question_id: questionId,
                nonce: vefifyQuestionBank.nonce
            })
            .done(function(response) {
                console.log('Preview loaded:', response);
                
                if (response.success) {
                    $previewRow.find('.question-preview-content').html(response.data);
                    $button.text('Hide');
                } else {
                    $previewRow.find('.question-preview-content').html(
                        '<div class="preview-error">' + 
                        (response.data || vefifyQuestionBank.strings.errorLoading) + 
                        '</div>'
                    );
                    $button.text('Preview');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Preview AJAX failed:', status, error);
                $previewRow.find('.question-preview-content').html(
                    '<div class="preview-error">Network error. Please try again.</div>'
                );
                $button.text('Preview');
            });
        }
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
            currentType: function() { return currentQuestionType; }
        };
    }
    
    console.log('Question Bank JavaScript loaded successfully');
});