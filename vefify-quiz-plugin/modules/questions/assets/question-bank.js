/**
 * OVERRIDE FIX for question-bank.js
 * If your original JS file still has issues, replace it with this content
 * File: modules/questions/assets/question-bank.js
 */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('Question Bank JavaScript Override Loaded');
    
    // Ensure vefifyQuestionBank exists
    if (typeof window.vefifyQuestionBank === 'undefined') {
        console.warn('vefifyQuestionBank not found, creating fallback...');
        window.vefifyQuestionBank = {
            strings: {
                selectOne: 'Select the correct answer',
                selectMultiple: 'Select all correct answers',
                selectTrueFalse: 'Select True or False',
                trueFalseMode: 'True/False Mode - Options are automatically set',
                true: 'True',
                false: 'False',
                loading: 'Loading...'
            }
        };
    }
    
    // Global variables
    let optionCount = 0;
    let currentQuestionType = 'single_select';
    
    // Initialize
    function init() {
        optionCount = $('#answer-options .option-row').length;
        currentQuestionType = $('#question_type').val() || 'single_select';
        
        console.log('Question Bank initialized:', {
            optionCount: optionCount,
            questionType: currentQuestionType
        });
        
        setupEventHandlers();
        
        // Initialize question type with delay
        setTimeout(function() {
            updateQuestionType();
        }, 300);
    }
    
    // Setup event handlers
    function setupEventHandlers() {
        // Question type change
        $('#question_type').off('change.vefify').on('change.vefify', function() {
            currentQuestionType = $(this).val();
            console.log('Question type changed to:', currentQuestionType);
            updateQuestionType();
        });
        
        // Add option
        $('#add-option').off('click.vefify').on('click.vefify', handleAddOption);
        
        // Remove option (delegated)
        $(document).off('click.vefify', '.remove-option').on('click.vefify', '.remove-option', handleRemoveOption);
        
        // Correct answer checkbox
        $(document).off('change.vefify', '.option-correct-checkbox').on('change.vefify', '.option-correct-checkbox', handleCorrectAnswerChange);
        
        // Form submission
        $('#question-form').off('submit.vefify').on('submit.vefify', handleFormSubmit);
        
        // Select all checkbox
        $('#cb-select-all').change(function() {
            $('input[name="question_ids[]"]').prop('checked', this.checked);
        });
    }
    
    // Update question type UI
    function updateQuestionType() {
        const type = $('#question_type').val();
        const $container = $('#answer-options');
        const $addSection = $('#add-option-section');
        const $helpText = $('#options-help');
        
        console.log('Updating question type UI for:', type);
        
        // Safely get help text
        const helpTexts = {
            'single_select': 'Select the correct answer',
            'multiple_select': 'Select all correct answers',
            'true_false': 'Select True or False'
        };
        
        if ($helpText.length) {
            $helpText.text(helpTexts[type] || helpTexts.single_select);
        }
        
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
    
    // Handle True/False question type
    function handleTrueFalseType($container, $addSection) {
        console.log('Setting up True/False question type');
        
        const $allOptions = $container.find('.option-row');
        
        // Remove options beyond first 2
        $allOptions.each(function(index) {
            if (index >= 2) {
                $(this).remove();
            }
        });
        
        // Ensure exactly 2 options
        let remainingCount = $container.find('.option-row').length;
        while (remainingCount < 2) {
            const optionHtml = createOptionHtml(remainingCount);
            $container.append(optionHtml);
            remainingCount++;
        }
        
        // Set True/False values
        const $options = $container.find('.option-row');
        if ($options.length >= 2) {
            $options.eq(0).find('.option-text')
                .val('True')
                .prop('readonly', true)
                .addClass('readonly-option');
            
            $options.eq(1).find('.option-text')
                .val('False')
                .prop('readonly', true)
                .addClass('readonly-option');
        }
        
        // Hide controls
        $container.find('.remove-option').hide();
        $addSection.hide();
        
        // Add styling
        $container.addClass('question-type-true-false');
        $container.attr('data-mode-text', 'True/False Mode - Options are automatically set');
        
        optionCount = 2;
    }
    
    // Handle choice question types
    function handleChoiceType($container, $addSection, type) {
        console.log('Setting up choice question type:', type);
        
        // Remove readonly and show controls
        $container.find('.option-text')
            .prop('readonly', false)
            .removeClass('readonly-option');
        $container.find('.remove-option').show();
        $addSection.show();
        
        // Clear True/False text if switching
        const $options = $container.find('.option-row');
        $options.each(function() {
            const $optionText = $(this).find('.option-text');
            const currentValue = $optionText.val();
            
            if (currentValue === 'True' || currentValue === 'False') {
                $optionText.val('');
            }
        });
        
        // Ensure minimum 4 options
        let currentCount = $container.find('.option-row').length;
        while (currentCount < 4) {
            const optionHtml = createOptionHtml(currentCount);
            $container.append(optionHtml);
            currentCount++;
        }
        
        optionCount = currentCount;
    }
    
    // Handle add option
    function handleAddOption(e) {
        e.preventDefault();
        
        const questionType = $('#question_type').val();
        const maxOptions = questionType === 'true_false' ? 2 : 6;
        const currentOptions = $('#answer-options .option-row:visible').length;
        
        if (currentOptions >= maxOptions) {
            alert(`You can have at most ${maxOptions} options for ${questionType.replace('_', ' ')} questions.`);
            return;
        }
        
        const optionHtml = createOptionHtml(optionCount);
        $('#answer-options').append(optionHtml);
        optionCount++;
        
        updateOptionNumbers();
        updateOptionCount();
        
        $('#answer-options .option-row:last .option-text').focus();
    }
    
    // Handle remove option
    function handleRemoveOption(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const questionType = $('#question_type').val();
        const minOptions = questionType === 'true_false' ? 2 : 2;
        const currentOptions = $('#answer-options .option-row:visible').length;
        
        if (currentOptions <= minOptions) {
            alert(`You need at least ${minOptions} options for ${questionType.replace('_', ' ')} questions.`);
            return;
        }
        
        const $row = $(this).closest('.option-row');
        $row.fadeOut(300, function() {
            $row.remove();
            updateOptionNumbers();
            updateOptionCount();
        });
    }
    
    // Handle correct answer change
    function handleCorrectAnswerChange() {
        const $row = $(this).closest('.option-row');
        const questionType = $('#question_type').val();
        
        if (questionType === 'single_select' && this.checked) {
            $('.option-correct-checkbox').not(this).prop('checked', false);
            $('.option-row').removeClass('correct');
        }
        
        $row.toggleClass('correct', this.checked);
    }
    
    // Handle form submit
    function handleFormSubmit(e) {
        console.log('Form submission triggered');
        
        const errors = validateForm();
        if (errors.length > 0) {
            e.preventDefault();
            alert('Error: ' + errors.join('\n'));
            return false;
        }
        
        const $submitBtn = $(this).find('input[type="submit"]');
        $submitBtn.prop('disabled', true).val('Saving...');
        
        return true;
    }
    
    // Create option HTML
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
    
    // Update option numbers
    function updateOptionNumbers() {
        $('#answer-options .option-row').each(function(index) {
            const $row = $(this);
            $row.attr('data-index', index);
            $row.find('.option-number').text(String.fromCharCode(65 + index));
            
            $row.find('input, textarea').each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                if (name) {
                    const newName = name.replace(/options\[\d+\]/, `options[${index}]`);
                    $input.attr('name', newName);
                }
            });
        });
    }
    
    // Update option count
    function updateOptionCount() {
        optionCount = $('#answer-options .option-row').length;
    }
    
    // Validate form
    function validateForm() {
        const errors = [];
        
        const questionText = $('#question_text').val().trim();
        if (!questionText) {
            errors.push('Question text is required.');
        }
        
        const $visibleOptions = $('.option-row:visible');
        const checkedOptions = $visibleOptions.find('.option-correct-checkbox:checked').length;
        const filledOptions = $visibleOptions.find('.option-text').filter(function() {
            return $(this).val().trim() !== '';
        }).length;
        
        const questionType = $('#question_type').val();
        const minOptions = questionType === 'true_false' ? 2 : 2;
        
        if (filledOptions < minOptions) {
            errors.push(`You need at least ${minOptions} answer options.`);
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
    
    // Initialize everything
    init();
    
    console.log('Question Bank JavaScript Override loaded successfully');
});