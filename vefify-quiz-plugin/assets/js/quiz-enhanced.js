/**
 * Enhanced Quiz JavaScript
 * File: assets/js/quiz-enhanced.js
 * 
 * Handles all frontend quiz functionality including:
 * - Registration form validation
 * - Quiz flow management
 * - Database communication
 * - Timer functionality
 * - Results display
 */

var VefifyQuizEnhanced = (function($) {
    'use strict';
    
    var quiz = {
        container: null,
        settings: null,
        currentQuestion: 0,
        answers: {},
        timer: null,
        timeRemaining: 0,
        participantId: null,
        sessionId: null,
        questions: [],
        startTime: null
    };
    
    /**
     * Initialize quiz
     */
    function init(containerId, options) {
        quiz.container = $('#' + containerId);
        quiz.settings = options.settings;
        
        if (!quiz.container.length) {
            console.error('Quiz container not found:', containerId);
            return;
        }
        
        setupEventHandlers();
        initializeProvinceDistricts();
        
        console.log('VefifyQuizEnhanced initialized for container:', containerId);
    }
    
    /**
     * Setup event handlers
     */
    function setupEventHandlers() {
        var $container = quiz.container;
        
        // Registration form submission
        $container.on('submit', '#vefify-registration-form', handleRegistrationSubmit);
        
        // Phone number validation
        $container.on('blur', '#phone', validatePhoneNumber);
        
        // Province change handler
        $container.on('change', '#province', handleProvinceChange);
        
        // Start quiz button
        $container.on('click', '.start-quiz-btn', startQuiz);
        
        // Question navigation
        $container.on('click', '.next-btn', nextQuestion);
        $container.on('click', '.prev-btn', previousQuestion);
        $container.on('click', '.submit-btn', submitQuiz);
        
        // Option selection
        $container.on('click', '.option-item', selectOption);
        
        // Results actions
        $container.on('click', '.restart-btn', restartQuiz);
        $container.on('click', '.share-btn', shareResults);
        
        // Form validation on blur
        $container.on('blur', 'input[required], select[required]', validateField);
    }
    
    /**
     * Handle registration form submission
     */
    function handleRegistrationSubmit(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('[type="submit"]');
        
        // Validate form
        if (!validateRegistrationForm($form)) {
            return;
        }
        
        // Show loading
        showButtonLoading($submitBtn);
        
        // Collect form data
        var participantData = {
            full_name: $form.find('#full_name').val(),
            email: $form.find('#email').val(),
            phone: $form.find('#phone').val(),
            province: $form.find('#province').val(),
            district: $form.find('#district').val(),
            pharmacist_code: $form.find('#pharmacist_code').val(),
            company: $form.find('#company').val()
        };
        
        // Submit to server
        $.ajax({
            url: vefifyAjax.ajaxUrl,
            method: 'POST',
            data: {
                action: 'vefify_start_quiz',
                nonce: vefifyAjax.nonce,
                campaign_id: quiz.container.data('campaign-id'),
                participant_data: participantData
            },
            success: function(response) {
                if (response.success) {
                    quiz.participantId = response.data.participant_id;
                    quiz.sessionId = response.data.session_id;
                    quiz.questions = response.data.questions;
                    
                    showScreen('question-screen');
                    startQuizTimer();
                    displayQuestion(0);
                    updateProgress();
                    
                    showNotification('success', 'Quiz started successfully!');
                } else {
                    showNotification('error', response.data.message || 'Failed to start quiz');
                }
            },
            error: function() {
                showNotification('error', 'Connection error. Please try again.');
            },
            complete: function() {
                hideButtonLoading($submitBtn);
            }
        });
    }
    
    /**
     * Validate registration form
     */
    function validateRegistrationForm($form) {
        var isValid = true;
        
        // Check required fields
        $form.find('input[required], select[required]').each(function() {
            if (!validateField.call(this)) {
                isValid = false;
            }
        });
        
        // Additional phone validation
        var phone = $form.find('#phone').val();
        if (phone && !isValidVietnamesePhone(phone)) {
            showFieldError($form.find('#phone'), 'Please enter a valid Vietnamese phone number');
            isValid = false;
        }
        
        // Email validation
        var email = $form.find('#email').val();
        if (email && !isValidEmail(email)) {
            showFieldError($form.find('#email'), 'Please enter a valid email address');
            isValid = false;
        }
        
        return isValid;
    }
    
    /**
     * Validate individual field
     */
    function validateField() {
        var $field = $(this);
        var value = $field.val().trim();
        var isValid = true;
        
        // Required field check
        if ($field.prop('required') && !value) {
            showFieldError($field, 'This field is required');
            isValid = false;
        } else {
            hideFieldError($field);
        }
        
        return isValid;
    }
    
    /**
     * Show field error
     */
    function showFieldError($field, message) {
        $field.addClass('error');
        var $error = $field.siblings('.field-error');
        $error.text(message).addClass('show');
    }
    
    /**
     * Hide field error
     */
    function hideFieldError($field) {
        $field.removeClass('error');
        var $error = $field.siblings('.field-error');
        $error.removeClass('show');
    }
    
    /**
     * Validate phone number
     */
    function validatePhoneNumber() {
        var $phone = $(this);
        var phone = $phone.val().trim();
        
        if (!phone) return;
        
        if (!isValidVietnamesePhone(phone)) {
            showFieldError($phone, vefifyAjax.strings.invalidPhone);
            return;
        }
        
        // Check if phone already exists
        $.ajax({
            url: vefifyAjax.ajaxUrl,
            method: 'POST',
            data: {
                action: 'vefify_check_phone',
                nonce: vefifyAjax.nonce,
                phone: phone,
                campaign_id: quiz.container.data('campaign-id')
            },
            success: function(response) {
                if (!response.success) {
                    showFieldError($phone, response.data || vefifyAjax.strings.phoneExists);
                } else {
                    hideFieldError($phone);
                }
            }
        });
    }
    
    /**
     * Check if phone number is valid Vietnamese format
     */
    function isValidVietnamesePhone(phone) {
        // Remove all non-digit characters
        var cleanPhone = phone.replace(/\D/g, '');
        
        // Vietnamese phone patterns
        var patterns = [
            /^(84|0)(3[2-9]|5[6|8|9]|7[0|6-9]|8[1-6|8|9]|9[0-4|6-9])[0-9]{7}$/, // Mobile
            /^(84|0)(2[0-9])[0-9]{8}$/ // Landline
        ];
        
        return patterns.some(pattern => pattern.test(cleanPhone));
    }
    
    /**
     * Check if email is valid
     */
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    /**
     * Initialize province-district functionality
     */
    function initializeProvinceDistricts() {
        // Vietnam districts data (simplified)
        var districts = {
            'Hà Nội': ['Ba Đình', 'Hoàn Kiếm', 'Tây Hồ', 'Long Biên', 'Cầu Giấy', 'Đống Đa', 'Hai Bà Trưng', 'Hoàng Mai', 'Thanh Xuân', 'Nam Từ Liêm', 'Bắc Từ Liêm', 'Hà Đông'],
            'Hồ Chí Minh': ['Quận 1', 'Quận 2', 'Quận 3', 'Quận 4', 'Quận 5', 'Quận 6', 'Quận 7', 'Quận 8', 'Quận 9', 'Quận 10', 'Quận 11', 'Quận 12', 'Bình Thạnh', 'Gò Vấp', 'Phú Nhuận', 'Tân Bình', 'Tân Phú', 'Thủ Đức'],
            'Đà Nẵng': ['Hải Châu', 'Thanh Khê', 'Sơn Trà', 'Ngũ Hành Sơn', 'Liên Chiểu', 'Cẩm Lệ'],
            'Hải Phòng': ['Hồng Bàng', 'Ngô Quyền', 'Lê Chân', 'Hải An', 'Kiến An', 'Đồ Sơn', 'Dương Kinh'],
            'Cần Thơ': ['Ninh Kiều', 'Ô Môn', 'Bình Thuỷ', 'Cái Răng', 'Thốt Nốt']
        };
        
        window.vietnamDistricts = districts;
    }
    
    /**
     * Handle province change
     */
    function handleProvinceChange() {
        var $province = $(this);
        var $district = quiz.container.find('#district');
        var selectedProvince = $province.val();
        
        // Clear district options
        $district.html('<option value="">Select District</option>');
        
        if (selectedProvince && window.vietnamDistricts[selectedProvince]) {
            var districts = window.vietnamDistricts[selectedProvince];
            
            districts.forEach(function(district) {
                $district.append('<option value="' + district + '">' + district + '</option>');
            });
            
            $district.prop('disabled', false);
        } else {
            $district.prop('disabled', true);
        }
    }
    
    /**
     * Start quiz
     */
    function startQuiz() {
        showScreen('question-screen');
        quiz.startTime = Date.now();
        startQuizTimer();
        displayQuestion(0);
        updateProgress();
    }
    
    /**
     * Start quiz timer
     */
    function startQuizTimer() {
        quiz.timeRemaining = quiz.settings.timeLimit;
        updateTimerDisplay();
        
        quiz.timer = setInterval(function() {
            quiz.timeRemaining--;
            updateTimerDisplay();
            
            if (quiz.timeRemaining <= 0) {
                clearInterval(quiz.timer);
                autoSubmitQuiz();
            }
        }, 1000);
    }
    
    /**
     * Update timer display
     */
    function updateTimerDisplay() {
        var minutes = Math.floor(quiz.timeRemaining / 60);
        var seconds = quiz.timeRemaining % 60;
        var timeText = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
        
        quiz.container.find('.timer-display').text(timeText);
        quiz.container.find('.time-remaining').text(quiz.timeRemaining);
        
        // Warning colors
        if (quiz.timeRemaining <= 60) {
            quiz.container.find('.timer-display').css('color', '#dc3545');
        } else if (quiz.timeRemaining <= 300) {
            quiz.container.find('.timer-display').css('color', '#ffc107');
        }
    }
    
    /**
     * Display question
     */
    function displayQuestion(questionIndex) {
        if (!quiz.questions || questionIndex >= quiz.questions.length) {
            console.error('Invalid question index:', questionIndex);
            return;
        }
        
        var question = quiz.questions[questionIndex];
        var $questionScreen = quiz.container.find('.question-screen');
        
        // Update question content
        $questionScreen.find('.question-text').text(question.question_text);
        $questionScreen.find('.question-type').text(question.question_type.replace('_', ' '));
        $questionScreen.find('.question-difficulty').text(question.difficulty || 'medium');
        
        // Clear and populate options
        var $optionsContainer = $questionScreen.find('.question-options');
        $optionsContainer.empty();
        
        if (question.options && question.options.length > 0) {
            question.options.forEach(function(option) {
                var isSelected = quiz.answers[question.id] == option.id;
                var $option = $('<div class="option-item' + (isSelected ? ' selected' : '') + '" data-question-id="' + question.id + '" data-option-id="' + option.id + '">' + 
                    '<div class="option-text">' + option.option_text + '</div>' +
                    '</div>');
                
                $optionsContainer.append($option);
            });
        }
        
        // Update navigation buttons
        var $prevBtn = $questionScreen.find('.prev-btn');
        var $nextBtn = $questionScreen.find('.next-btn');
        var $submitBtn = $questionScreen.find('.submit-btn');
        
        if (questionIndex === 0) {
            $prevBtn.hide();
        } else {
            $prevBtn.show();
        }
        
        if (questionIndex === quiz.questions.length - 1) {
            $nextBtn.hide();
            $submitBtn.show();
        } else {
            $nextBtn.show();
            $submitBtn.hide();
        }
        
        quiz.currentQuestion = questionIndex;
        updateProgress();
    }
    
    /**
     * Select answer option
     */
    function selectOption() {
        var $option = $(this);
        var questionId = $option.data('question-id');
        var optionId = $option.data('option-id');
        
        // Remove selection from other options
        $option.siblings('.option-item').removeClass('selected');
        
        // Select this option
        $option.addClass('selected');
        
        // Store answer
        quiz.answers[questionId] = optionId;
        
        // Enable next/submit button
        var $nextBtn = quiz.container.find('.next-btn');
        var $submitBtn = quiz.container.find('.submit-btn');
        
        if ($nextBtn.is(':visible')) {
            $nextBtn.prop('disabled', false);
        }
        if ($submitBtn.is(':visible')) {
            $submitBtn.prop('disabled', false);
        }
    }
    
    /**
     * Go to next question
     */
    function nextQuestion() {
        if (quiz.currentQuestion < quiz.questions.length - 1) {
            displayQuestion(quiz.currentQuestion + 1);
        }
    }
    
    /**
     * Go to previous question
     */
    function previousQuestion() {
        if (quiz.currentQuestion > 0) {
            displayQuestion(quiz.currentQuestion - 1);
        }
    }
    
    /**
     * Submit quiz
     */
    function submitQuiz() {
        if (Object.keys(quiz.answers).length === 0) {
            showNotification('warning', 'Please answer at least one question before submitting.');
            return;
        }
        
        clearInterval(quiz.timer);
        
        var $submitBtn = quiz.container.find('.submit-btn');
        showButtonLoading($submitBtn);
        
        var timeTaken = Math.round((Date.now() - quiz.startTime) / 1000);
        
        $.ajax({
            url: vefifyAjax.ajaxUrl,
            method: 'POST',
            data: {
                action: 'vefify_submit_quiz',
                nonce: vefifyAjax.nonce,
                session_id: quiz.sessionId,
                answers: quiz.answers,
                time_taken: timeTaken
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                    showScreen('results-screen');
                } else {
                    showNotification('error', response.data.message || 'Failed to submit quiz');
                }
            },
            error: function() {
                showNotification('error', 'Connection error. Please try again.');
            },
            complete: function() {
                hideButtonLoading($submitBtn);
            }
        });
    }
    
    /**
     * Auto submit quiz when time runs out
     */
    function autoSubmitQuiz() {
        showNotification('warning', vefifyAjax.strings.timeUp);
        setTimeout(submitQuiz, 2000);
    }
    
    /**
     * Display quiz results
     */
    function displayResults(results) {
        var $resultsScreen = quiz.container.find('.results-screen');
        
        // Update score display
        $resultsScreen.find('.score-number').text(results.score);
        $resultsScreen.find('.score-total').text(results.total_questions);
        $resultsScreen.find('.score-percentage').text(results.percentage + '%');
        
        // Update result details
        $resultsScreen.find('.correct-count').text(results.correct_answers);
        $resultsScreen.find('.time-taken').text(formatTime(results.time_taken));
        $resultsScreen.find('.accuracy').text(results.percentage + '%');
        
        // Update status
        var $statusIcon = $resultsScreen.find('.status-icon');
        var $statusText = $resultsScreen.find('.status-text');
        
        if (results.passed) {
            $statusIcon.text('🎉');
            $statusText.text('Congratulations! You passed the quiz!');
            $statusText.css('color', '#28a745');
        } else {
            $statusIcon.text('😔');
            $statusText.text('You didn\'t pass this time. Try again!');
            $statusText.css('color', '#dc3545');
        }
        
        // Show gift section if applicable
        if (results.gift && results.gift.has_gift) {
            var $giftSection = $resultsScreen.find('.gift-section');
            $giftSection.find('.gift-name').text(results.gift.gift_name);
            $giftSection.find('.gift-code').html('<strong>Code:</strong> ' + results.gift.gift_code);
            $giftSection.find('.gift-instructions').text(results.gift.gift_description || 'Use this code to claim your reward!');
            $giftSection.show();
        }
        
        // Update progress to 100%
        quiz.container.find('.progress-fill').css('width', '100%');
        quiz.container.find('.current-step').text(quiz.questions.length);
    }
    
    /**
     * Format time in seconds to readable format
     */
    function formatTime(seconds) {
        var minutes = Math.floor(seconds / 60);
        var remainingSeconds = seconds % 60;
        
        if (minutes > 0) {
            return minutes + 'm ' + remainingSeconds + 's';
        } else {
            return remainingSeconds + 's';
        }
    }
    
    /**
     * Restart quiz
     */
    function restartQuiz() {
        // Reset quiz state
        quiz.currentQuestion = 0;
        quiz.answers = {};
        quiz.participantId = null;
        quiz.sessionId = null;
        quiz.questions = [];
        quiz.startTime = null;
        
        if (quiz.timer) {
            clearInterval(quiz.timer);
        }
        
        // Reset UI
        quiz.container.find('.progress-fill').css('width', '0%');
        quiz.container.find('.current-step').text('1');
        quiz.container.find('.timer-display').css('color', '');
        
        // Show registration screen
        showScreen('registration-screen');
        
        // Clear form
        quiz.container.find('#vefify-registration-form')[0].reset();
        hideAllFieldErrors();
    }
    
    /**
     * Share results
     */
    function shareResults() {
        var shareText = 'I just completed a quiz and scored ' + 
            quiz.container.find('.score-number').text() + '/' + 
            quiz.container.find('.score-total').text() + '! ';
        
        var shareUrl = window.location.href;
        
        if (navigator.share) {
            navigator.share({
                title: 'Quiz Results',
                text: shareText,
                url: shareUrl
            });
        } else {
            // Fallback: copy to clipboard
            var textArea = document.createElement('textarea');
            textArea.value = shareText + shareUrl;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            showNotification('success', 'Results copied to clipboard!');
        }
    }
    
    /**
     * Show specific screen
     */
    function showScreen(screenClass) {
        quiz.container.find('.quiz-screen').removeClass('active');
        quiz.container.find('.' + screenClass).addClass('active');
        
        // Show/hide progress bar
        if (screenClass === 'question-screen') {
            quiz.container.find('.quiz-progress').show();
        } else {
            quiz.container.find('.quiz-progress').hide();
        }
    }
    
    /**
     * Update progress bar
     */
    function updateProgress() {
        if (!quiz.questions.length) return;
        
        var progress = ((quiz.currentQuestion + 1) / quiz.questions.length) * 100;
        quiz.container.find('.progress-fill').css('width', progress + '%');
        quiz.container.find('.current-step').text(quiz.currentQuestion + 1);
        quiz.container.find('.total-steps').text(quiz.questions.length);
    }
    
    /**
     * Show button loading state
     */
    function showButtonLoading($button) {
        $button.prop('disabled', true);
        $button.find('.btn-text').hide();
        $button.find('.btn-loading').show();
    }
    
    /**
     * Hide button loading state
     */
    function hideButtonLoading($button) {
        $button.prop('disabled', false);
        $button.find('.btn-text').show();
        $button.find('.btn-loading').hide();
    }
    
    /**
     * Show notification
     */
    function showNotification(type, message) {
        // Remove existing notifications
        $('.vefify-notification').remove();
        
        var $notification = $('<div class="vefify-notification notification-' + type + '">' + 
            '<span class="notification-icon">' + getNotificationIcon(type) + '</span>' +
            '<span class="notification-message">' + message + '</span>' +
            '<button class="notification-close">&times;</button>' +
            '</div>');
        
        $('body').append($notification);
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(function() {
                $notification.remove();
            });
        }, 5000);
        
        // Manual close
        $notification.find('.notification-close').on('click', function() {
            $notification.fadeOut(function() {
                $notification.remove();
            });
        });
        
        // Add notification styles if not present
        if (!$('#vefify-notification-styles').length) {
            var notificationStyles = `
                <style id="vefify-notification-styles">
                .vefify-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    padding: 15px 20px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    z-index: 10000;
                    max-width: 400px;
                    border-left: 4px solid #007cba;
                }
                .notification-success { border-left-color: #28a745; }
                .notification-error { border-left-color: #dc3545; }
                .notification-warning { border-left-color: #ffc107; }
                .notification-icon { font-size: 18px; }
                .notification-message { flex: 1; font-size: 14px; }
                .notification-close { 
                    background: none; 
                    border: none; 
                    font-size: 18px; 
                    cursor: pointer; 
                    color: #666; 
                    padding: 0;
                    margin-left: 10px;
                }
                @media (max-width: 480px) {
                    .vefify-notification {
                        top: 10px;
                        right: 10px;
                        left: 10px;
                        max-width: none;
                    }
                }
                </style>
            `;
            $('head').append(notificationStyles);
        }
    }
    
    /**
     * Get notification icon
     */
    function getNotificationIcon(type) {
        var icons = {
            'success': '✅',
            'error': '❌',
            'warning': '⚠️',
            'info': 'ℹ️'
        };
        return icons[type] || icons.info;
    }
    
    /**
     * Hide all field errors
     */
    function hideAllFieldErrors() {
        quiz.container.find('.field-error').removeClass('show');
        quiz.container.find('input, select').removeClass('error');
    }
    
    // Public API
    return {
        init: init,
        showNotification: showNotification
    };
    
})(jQuery);

// Initialize when document is ready
jQuery(document).ready(function($) {
    // Auto-initialize quiz containers
    $('.vefify-quiz-container.enhanced-version').each(function() {
        var $container = $(this);
        var containerId = $container.attr('id');
        
        if (containerId && typeof window.VefifyQuizEnhanced !== 'undefined') {
            // Quiz will be initialized by the shortcode's inline script
            console.log('Quiz container ready:', containerId);
        }
    });
});

// Make it globally available
window.VefifyQuizEnhanced = VefifyQuizEnhanced;