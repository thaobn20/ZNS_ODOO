/**
 * Frontend JavaScript
 * File: assets/js/frontend.js
 * Handles quiz functionality, AJAX calls, and user interactions
 */

(function($) {
    'use strict';

    // Quiz Application Class
    class VefifyQuiz {
        constructor() {
            this.currentQuestion = 0;
            this.questions = [];
            this.answers = {};
            this.timer = null;
            this.timeRemaining = 0;
            this.participantId = null;
            this.campaignId = null;
            this.isSubmitting = false;
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeQuiz();
        }

        bindEvents() {
            // Start quiz form submission
            $(document).on('submit', '.participant-form', this.handleParticipantForm.bind(this));
            
            // Answer selection
            $(document).on('change', '.answer-option input', this.handleAnswerSelection.bind(this));
            
            // Navigation buttons
            $(document).on('click', '.btn-next', this.nextQuestion.bind(this));
            $(document).on('click', '.btn-prev', this.prevQuestion.bind(this));
            $(document).on('click', '.btn-submit', this.submitQuiz.bind(this));
            
            // Social sharing
            $(document).on('click', '.social-btn', this.handleSocialShare.bind(this));
            
            // Auto-save functionality
            $(document).on('change', '.answer-option input', this.autoSave.bind(this));
            
            // Keyboard navigation
            $(document).on('keydown', this.handleKeyboardNavigation.bind(this));
            
            // Window beforeunload warning
            $(window).on('beforeunload', this.handleBeforeUnload.bind(this));
        }

        initializeQuiz() {
            // Check if we're on a quiz page
            if (!$('.vefify-quiz-container').length) {
                return;
            }

            // Get campaign ID from URL or data attribute
            this.campaignId = this.getCampaignId();
            
            if (!this.campaignId) {
                this.showError('No campaign specified');
                return;
            }

            // Initialize components
            this.initializeTimer();
            this.initializeProgress();
            this.initializeAccessibility();
        }

        getCampaignId() {
            // Try to get from URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            let campaignId = urlParams.get('campaign_id');
            
            // Try to get from data attribute
            if (!campaignId) {
                campaignId = $('.vefify-quiz-container').data('campaign-id');
            }
            
            return campaignId ? parseInt(campaignId) : null;
        }

        handleParticipantForm(e) {
            e.preventDefault();
            
            if (this.isSubmitting) {
                return;
            }

            const form = $(e.target);
            const formData = this.getFormData(form);
            
            // Validate form
            if (!this.validateParticipantForm(formData)) {
                return;
            }

            this.isSubmitting = true;
            this.showLoading('Starting quiz...');

            // AJAX request to start quiz
            $.ajax({
                url: vefifyFrontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'vefify_start_quiz',
                    campaign_id: this.campaignId,
                    participant_name: formData.name,
                    participant_email: formData.email,
                    participant_phone: formData.phone,
                    nonce: vefifyFrontend.nonce
                },
                success: this.handleQuizStart.bind(this),
                error: this.handleAjaxError.bind(this),
                complete: () => {
                    this.isSubmitting = false;
                    this.hideLoading();
                }
            });
        }

        getFormData(form) {
            return {
                name: form.find('[name="participant_name"]').val().trim(),
                email: form.find('[name="participant_email"]').val().trim(),
                phone: form.find('[name="participant_phone"]').val().trim()
            };
        }

        validateParticipantForm(data) {
            let isValid = true;
            
            // Clear previous errors
            $('.error-message').remove();
            $('.form-group input').removeClass('error');

            // Validate name
            if (!data.name || data.name.length < 2) {
                this.showFieldError('[name="participant_name"]', 'Please enter your full name');
                isValid = false;
            }

            // Validate email
            if (!data.email) {
                this.showFieldError('[name="participant_email"]', 'Email address is required');
                isValid = false;
            } else if (!this.isValidEmail(data.email)) {
                this.showFieldError('[name="participant_email"]', 'Please enter a valid email address');
                isValid = false;
            }

            // Phone is optional but validate if provided
            if (data.phone && !this.isValidPhone(data.phone)) {
                this.showFieldError('[name="participant_phone"]', 'Please enter a valid phone number');
                isValid = false;
            }

            return isValid;
        }

        showFieldError(fieldSelector, message) {
            const field = $(fieldSelector);
            field.addClass('error');
            field.closest('.form-group').append(`<div class="error-message">${message}</div>`);
        }

        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        isValidPhone(phone) {
            const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
            return phoneRegex.test(phone.replace(/\s+/g, ''));
        }

        handleQuizStart(response) {
            if (response.success) {
                this.participantId = response.data.participant_id;
                this.questions = response.data.questions;
                this.timeRemaining = response.data.session_data.time_limit || 600;
                
                // Hide participant form and show quiz
                $('.participant-form').fadeOut(300, () => {
                    this.renderQuiz();
                    this.startTimer();
                });
            } else {
                this.showError(response.data || 'Failed to start quiz');
            }
        }

        renderQuiz() {
            const quizHtml = this.generateQuizHtml();
            $('.vefify-quiz-container').append(quizHtml);
            
            // Initialize first question
            this.showQuestion(0);
            this.updateProgress();
            
            // Announce to screen readers
            this.announceToScreenReader('Quiz started. Question 1 of ' + this.questions.length);
        }

        generateQuizHtml() {
            return `
                <div class="quiz-timer" id="quiz-timer">
                    <span id="timer-display">${this.formatTime(this.timeRemaining)}</span>
                </div>
                
                <div class="quiz-progress">
                    <div class="quiz-progress-bar" id="progress-bar"></div>
                    <div class="quiz-progress-text" id="progress-text">
                        Question <span id="current-question">1</span> of <span id="total-questions">${this.questions.length}</span>
                    </div>
                </div>
                
                <div id="quiz-questions-container">
                    ${this.questions.map((question, index) => this.generateQuestionHtml(question, index)).join('')}
                </div>
                
                <div class="quiz-actions">
                    <button type="button" class="btn btn-secondary btn-prev" id="btn-prev" disabled>
                        ← Previous
                    </button>
                    <button type="button" class="btn btn-primary btn-next" id="btn-next">
                        Next →
                    </button>
                    <button type="button" class="btn btn-success btn-submit" id="btn-submit" style="display: none;">
                        Submit Quiz
                    </button>
                </div>
            `;
        }

        generateQuestionHtml(question, index) {
            const isMultipleChoice = question.question_type === 'multiple_choice';
            const inputType = isMultipleChoice ? 'checkbox' : 'radio';
            
            return `
                <div class="question-card" id="question-${index}" style="display: none;">
                    <div class="question-number">${index + 1}</div>
                    <div class="question-difficulty ${question.difficulty}">${question.difficulty}</div>
                    <div class="question-text">${question.question_text}</div>
                    
                    <div class="answer-options">
                        ${question.options.map((option, optionIndex) => `
                            <div class="answer-option">
                                <input type="${inputType}" 
                                       id="q${index}_o${optionIndex}" 
                                       name="question_${question.id}" 
                                       value="${option.id}"
                                       data-question-index="${index}"
                                       aria-describedby="q${index}_label">
                                <label for="q${index}_o${optionIndex}" class="answer-option-label" id="q${index}_label">
                                    <span class="option-marker">${String.fromCharCode(65 + optionIndex)}</span>
                                    <span class="option-text">${option.option_text}</span>
                                </label>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        showQuestion(index) {
            // Hide all questions
            $('.question-card').hide();
            
            // Show current question
            $(`#question-${index}`).fadeIn(300);
            
            // Update navigation buttons
            $('#btn-prev').prop('disabled', index === 0);
            
            if (index === this.questions.length - 1) {
                $('#btn-next').hide();
                $('#btn-submit').show();
            } else {
                $('#btn-next').show();
                $('#btn-submit').hide();
            }
            
            // Scroll to top
            $('html, body').animate({ scrollTop: 0 }, 300);
            
            // Focus on first option for accessibility
            $(`#question-${index} .answer-option input:first`).focus();
        }

        handleAnswerSelection(e) {
            const input = $(e.target);
            const questionIndex = input.data('question-index');
            const questionId = input.attr('name').replace('question_', '');
            
            if (input.attr('type') === 'radio') {
                // Single choice - store single value
                this.answers[questionId] = input.val();
            } else {
                // Multiple choice - store array of values
                if (!this.answers[questionId]) {
                    this.answers[questionId] = [];
                }
                
                if (input.is(':checked')) {
                    this.answers[questionId].push(input.val());
                } else {
                    this.answers[questionId] = this.answers[questionId].filter(val => val !== input.val());
                }
            }
            
            // Provide feedback
            this.provideFeedback(questionIndex);
        }

        provideFeedback(questionIndex) {
            const questionCard = $(`#question-${questionIndex}`);
            const questionId = this.questions[questionIndex].id;
            const hasAnswer = this.answers[questionId] && 
                              (Array.isArray(this.answers[questionId]) ? 
                               this.answers[questionId].length > 0 : 
                               this.answers[questionId]);
            
            if (hasAnswer) {
                questionCard.addClass('answered');
                this.announceToScreenReader('Answer selected');
            } else {
                questionCard.removeClass('answered');
            }
        }

        nextQuestion() {
            if (this.currentQuestion < this.questions.length - 1) {
                this.currentQuestion++;
                this.showQuestion(this.currentQuestion);
                this.updateProgress();
                this.announceToScreenReader(`Question ${this.currentQuestion + 1} of ${this.questions.length}`);
            }
        }

        prevQuestion() {
            if (this.currentQuestion > 0) {
                this.currentQuestion--;
                this.showQuestion(this.currentQuestion);
                this.updateProgress();
                this.announceToScreenReader(`Question ${this.currentQuestion + 1} of ${this.questions.length}`);
            }
        }

        updateProgress() {
            const progress = ((this.currentQuestion + 1) / this.questions.length) * 100;
            $('#progress-bar').css('width', progress + '%');
            $('#current-question').text(this.currentQuestion + 1);
        }

        submitQuiz() {
            if (this.isSubmitting) {
                return;
            }

            // Check if all questions are answered
            const unansweredQuestions = this.getUnansweredQuestions();
            if (unansweredQuestions.length > 0) {
                const proceed = confirm(
                    `You have ${unansweredQuestions.length} unanswered questions. ` +
                    'Do you want to submit anyway?'
                );
                if (!proceed) {
                    return;
                }
            }

            this.isSubmitting = true;
            this.showLoading('Submitting your answers...');

            $.ajax({
                url: vefifyFrontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'vefify_submit_quiz',
                    participant_id: this.participantId,
                    answers: this.answers,
                    nonce: vefifyFrontend.nonce
                },
                success: this.handleQuizSubmit.bind(this),
                error: this.handleAjaxError.bind(this),
                complete: () => {
                    this.isSubmitting = false;
                    this.hideLoading();
                }
            });
        }

        getUnansweredQuestions() {
            return this.questions.filter(question => {
                const answer = this.answers[question.id];
                return !answer || (Array.isArray(answer) && answer.length === 0);
            });
        }

        handleQuizSubmit(response) {
            if (response.success) {
                this.stopTimer();
                this.showResults(response.data);
            } else {
                this.showError(response.data || 'Failed to submit quiz');
            }
        }

        showResults(results) {
            // Hide quiz interface
            $('#quiz-questions-container, .quiz-actions, .quiz-progress').fadeOut(300);
            
            // Generate and show results
            const resultsHtml = this.generateResultsHtml(results);
            $('.vefify-quiz-container').append(resultsHtml);
            
            // Initialize social sharing
            this.initializeSocialSharing(results);
            
            // Announce results to screen readers
            this.announceToScreenReader(
                `Quiz completed. Your score is ${results.final_score} out of ${results.max_score}. ` +
                `Percentage: ${results.percentage}%. ${results.passed ? 'Congratulations, you passed!' : 'Better luck next time!'}`
            );
        }

        generateResultsHtml(results) {
            const statusClass = results.passed ? 'passed' : 'failed';
            const statusText = results.passed ? 'Congratulations! You passed!' : 'Better luck next time!';
            
            return `
                <div class="quiz-results" id="quiz-results">
                    <div class="results-header">
                        <h2 class="results-title">Quiz Complete!</h2>
                        <p class="results-status ${statusClass}">${statusText}</p>
                    </div>
                    
                    <div class="score-display ${statusClass}">
                        ${results.final_score} / ${results.max_score}
                    </div>
                    
                    <div class="score-breakdown">
                        <div class="score-item">
                            <div class="score-item-value">${results.percentage}%</div>
                            <div class="score-item-label">Percentage</div>
                        </div>
                        <div class="score-item">
                            <div class="score-item-value">${results.final_score}</div>
                            <div class="score-item-label">Correct Answers</div>
                        </div>
                        <div class="score-item">
                            <div class="score-item-value">${results.max_score - results.final_score}</div>
                            <div class="score-item-label">Incorrect</div>
                        </div>
                    </div>
                    
                    ${this.generateGiftNotification(results)}
                    
                    <div class="social-sharing">
                        <h4>Share your results:</h4>
                        <div class="social-buttons">
                            <a href="#" class="social-btn facebook" data-platform="facebook">Share on Facebook</a>
                            <a href="#" class="social-btn twitter" data-platform="twitter">Share on Twitter</a>
                            <a href="#" class="social-btn linkedin" data-platform="linkedin">Share on LinkedIn</a>
                            <a href="#" class="social-btn whatsapp" data-platform="whatsapp">Share on WhatsApp</a>
                        </div>
                    </div>
                </div>
            `;
        }

        generateGiftNotification(results) {
            // This would be populated if a gift was awarded
            if (results.gift_code) {
                return `
                    <div class="gift-notification">
                        <h3>🎁 Congratulations!</h3>
                        <p>You've earned a reward!</p>
                        <div class="gift-code">${results.gift_code}</div>
                        <p>Save this code to claim your reward!</p>
                    </div>
                `;
            }
            return '';
        }

        // Timer functionality
        initializeTimer() {
            if (this.timeRemaining <= 0) {
                return;
            }
            
            this.updateTimerDisplay();
        }

        startTimer() {
            if (this.timeRemaining <= 0) {
                return;
            }
            
            this.timer = setInterval(() => {
                this.timeRemaining--;
                this.updateTimerDisplay();
                
                if (this.timeRemaining <= 0) {
                    this.handleTimeUp();
                } else if (this.timeRemaining <= 60) {
                    $('#quiz-timer').addClass('warning');
                }
            }, 1000);
        }

        stopTimer() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        }

        updateTimerDisplay() {
            $('#timer-display').text(this.formatTime(this.timeRemaining));
        }

        formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        }

        handleTimeUp() {
            this.stopTimer();
            alert('Time is up! Your quiz will be submitted automatically.');
            this.submitQuiz();
        }

        // Auto-save functionality
        autoSave() {
            if (!this.participantId) {
                return;
            }
            
            // Debounce auto-save to avoid excessive requests
            clearTimeout(this.autoSaveTimeout);
            this.autoSaveTimeout = setTimeout(() => {
                this.performAutoSave();
            }, 2000);
        }

        performAutoSave() {
            $.ajax({
                url: vefifyFrontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'vefify_auto_save',
                    participant_id: this.participantId,
                    answers: this.answers,
                    current_question: this.currentQuestion,
                    nonce: vefifyFrontend.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showTemporaryMessage('Progress saved', 'success');
                    }
                },
                error: () => {
                    this.showTemporaryMessage('Auto-save failed', 'error');
                }
            });
        }

        // Keyboard navigation
        handleKeyboardNavigation(e) {
            if (!$('.question-card:visible').length) {
                return;
            }
            
            switch(e.key) {
                case 'ArrowLeft':
                    if (!$('#btn-prev').prop('disabled')) {
                        e.preventDefault();
                        this.prevQuestion();
                    }
                    break;
                case 'ArrowRight':
                    if ($('#btn-next').is(':visible')) {
                        e.preventDefault();
                        this.nextQuestion();
                    }
                    break;
                case 'Enter':
                    if ($('#btn-submit').is(':visible')) {
                        e.preventDefault();
                        this.submitQuiz();
                    }
                    break;
            }
        }

        // Social sharing
        initializeSocialSharing(results) {
            this.shareData = {
                title: 'I just completed a quiz!',
                text: `I scored ${results.final_score}/${results.max_score} (${results.percentage}%) on this quiz!`,
                url: window.location.href
            };
        }

        handleSocialShare(e) {
            e.preventDefault();
            const platform = $(e.target).data('platform');
            const shareUrl = this.generateShareUrl(platform);
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }

        generateShareUrl(platform) {
            const encodedText = encodeURIComponent(this.shareData.text);
            const encodedUrl = encodeURIComponent(this.shareData.url);
            
            switch(platform) {
                case 'facebook':
                    return `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;
                case 'twitter':
                    return `https://twitter.com/intent/tweet?text=${encodedText}&url=${encodedUrl}`;
                case 'linkedin':
                    return `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`;
                case 'whatsapp':
                    return `https://wa.me/?text=${encodedText} ${encodedUrl}`;
                default:
                    return null;
            }
        }

        // Accessibility features
        initializeAccessibility() {
            // Add ARIA labels
            $('.quiz-progress').attr('aria-label', 'Quiz progress');
            $('#quiz-timer').attr('aria-label', 'Time remaining');
            
            // Create live region for announcements
            if (!$('#quiz-announcements').length) {
                $('body').append('<div id="quiz-announcements" aria-live="polite" aria-atomic="true" style="position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;"></div>');
            }
        }

        announceToScreenReader(message) {
            $('#quiz-announcements').text(message);
        }

        // Utility functions
        showLoading(message = 'Loading...') {
            if (!$('#quiz-loading').length) {
                $('.vefify-quiz-container').append(`
                    <div id="quiz-loading" class="quiz-loading">
                        <div class="loading"></div>
                        <p>${message}</p>
                    </div>
                `);
            } else {
                $('#quiz-loading p').text(message);
            }
        }

        hideLoading() {
            $('#quiz-loading').remove();
        }

        showError(message) {
            $('.quiz-error').remove();
            $('.vefify-quiz-container').prepend(`
                <div class="quiz-error critical">
                    <strong>Error:</strong> ${message}
                </div>
            `);
        }

        showTemporaryMessage(message, type = 'info') {
            const messageEl = $(`<div class="quiz-message ${type}">${message}</div>`);
            $('.vefify-quiz-container').prepend(messageEl);
            
            setTimeout(() => {
                messageEl.fadeOut(() => messageEl.remove());
            }, 3000);
        }

        handleAjaxError(xhr, status, error) {
            console.error('AJAX Error:', error);
            let message = 'An error occurred. Please try again.';
            
            if (xhr.responseJSON && xhr.responseJSON.data) {
                message = xhr.responseJSON.data;
            } else if (xhr.status === 0) {
                message = 'Connection error. Please check your internet connection.';
            } else if (xhr.status >= 500) {
                message = 'Server error. Please try again later.';
            }
            
            this.showError(message);
        }

        handleBeforeUnload(e) {
            if (this.participantId && !this.isSubmitting && Object.keys(this.answers).length > 0) {
                e.preventDefault();
                e.returnValue = 'You have unsaved progress. Are you sure you want to leave?';
                return e.returnValue;
            }
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Check if we're on a quiz page
        if ($('.vefify-quiz-container').length) {
            window.vefifyQuiz = new VefifyQuiz();
        }
        
        // Initialize other features
        initializeGlobalFeatures();
    });

    // Global features that work on all pages
    function initializeGlobalFeatures() {
        // Smooth scrolling for anchor links
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this.getAttribute('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 20
                }, 500);
            }
        });

        // Copy to clipboard functionality
        $(document).on('click', '[data-copy]', function() {
            const text = $(this).data('copy');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('Copied to clipboard!');
                });
            } else {
                // Fallback for older browsers
                const textarea = $('<textarea>').val(text).appendTo('body').select();
                document.execCommand('copy');
                textarea.remove();
                showToast('Copied to clipboard!');
            }
        });

        // Toast notification system
        function showToast(message, type = 'success') {
            const toast = $(`
                <div class="toast toast-${type}">
                    ${message}
                </div>
            `);
            
            $('body').append(toast);
            
            setTimeout(() => {
                toast.addClass('show');
            }, 100);
            
            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Lazy loading for images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        observer.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }

        // Form validation enhancement
        $('form').on('submit', function(e) {
            const form = $(this);
            let isValid = true;
            
            // Check required fields
            form.find('[required]').each(function() {
                const field = $(this);
                if (!field.val().trim()) {
                    field.addClass('error');
                    isValid = false;
                } else {
                    field.removeClass('error');
                }
            });
            
            // Check email fields
            form.find('input[type="email"]').each(function() {
                const field = $(this);
                if (field.val() && !isValidEmail(field.val())) {
                    field.addClass('error');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showToast('Please fix the errors in the form', 'error');
            }
        });

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // Responsive table wrapper
        $('table').wrap('<div class="table-responsive"></div>');

        // Auto-hide alerts
        $('.alert').each(function() {
            const alert = $(this);
            setTimeout(() => {
                alert.fadeOut();
            }, 5000);
        });
    }

    // Utility functions available globally
    window.vefifyUtils = {
        formatTime: function(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        },
        
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }
    };

})(jQuery);