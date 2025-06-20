/**
 * 🚀 VEFIFY QUIZ FRONTEND JAVASCRIPT
 * File: assets/js/frontend-quiz.js
 * 
 * Handles all frontend quiz functionality
 */

var VefifyQuiz = {
    
    // Configuration
    config: {
        currentQuestionIndex: 0,
        totalQuestions: 0,
        participantId: null,
        sessionToken: null,
        campaignId: null,
        questions: [],
        answers: {},
        timeLimit: 0,
        timeRemaining: 0,
        timerInterval: null,
        quizStartTime: null
    },
    
    /**
     * 🎯 INITIALIZE QUIZ
     */
    init: function(containerId, options) {
        console.log('Initializing VefifyQuiz...', options);
        
        this.containerId = containerId;
        this.container = jQuery('#' + containerId);
        
        if (this.container.length === 0) {
            console.error('Quiz container not found:', containerId);
            return;
        }
        
        // Store configuration
        this.config.campaignId = options.campaignId;
        this.config.timeLimit = options.timeLimit || 0;
        
        // Bind events
        this.bindEvents();
        
        console.log('VefifyQuiz initialized successfully');
    },
    
    /**
     * 🔗 BIND EVENTS
     */
    bindEvents: function() {
        var self = this;
        
        // Registration form submission
        this.container.on('submit', '#vefify-registration-form', function(e) {
            e.preventDefault();
            self.handleRegistration(this);
        });
        
        // Quiz navigation
        this.container.on('click', '#vefify-next-question', function() {
            self.nextQuestion();
        });
        
        this.container.on('click', '#vefify-prev-question', function() {
            self.prevQuestion();
        });
        
        this.container.on('click', '#vefify-finish-quiz', function() {
            self.finishQuiz();
        });
        
        // Answer selection
        this.container.on('change', '.vefify-question-option input', function() {
            self.saveCurrentAnswer();
        });
        
        // Phone number formatting
        this.container.on('input', '#vefify_phone', function() {
            self.formatPhoneNumber(this);
        });
        
        // Pharmacy code formatting
        this.container.on('input', '#vefify_pharmacy_code', function() {
            self.formatPharmacyCode(this);
        });
    },
    
    /**
     * 📝 HANDLE REGISTRATION
     */
    handleRegistration: function(form) {
        var self = this;
        var $form = jQuery(form);
        var $submitBtn = $form.find('button[type="submit"]');
        
        // Show loading
        $submitBtn.prop('disabled', true).html('🔄 Registering...');
        this.showLoading('Registering participant...');
        
        // Collect form data
        var formData = {
            action: 'vefify_register_participant',
            vefify_nonce: $form.find('[name="vefify_nonce"]').val(),
            campaign_id: $form.find('[name="campaign_id"]').val()
        };
        
        // Add all form fields
        $form.find('input, select, textarea').each(function() {
            var $field = jQuery(this);
            if ($field.attr('name') && $field.attr('name') !== 'vefify_nonce') {
                formData[$field.attr('name')] = $field.val();
            }
        });
        
        // Validate required fields
        var validation = this.validateRegistrationForm(formData);
        if (!validation.valid) {
            this.hideLoading();
            $submitBtn.prop('disabled', false).html('🚀 Start Quiz');
            this.showError(validation.message);
            return;
        }
        
        // Submit registration
        jQuery.post(vefifyAjax.ajaxUrl, formData)
            .done(function(response) {
                if (response.success) {
                    self.config.participantId = response.data.participant_id;
                    self.config.sessionToken = response.data.session_token;
                    
                    self.showSuccess('Registration successful! Starting quiz...');
                    
                    setTimeout(function() {
                        self.startQuiz();
                    }, 1000);
                } else {
                    self.hideLoading();
                    $submitBtn.prop('disabled', false).html('🚀 Start Quiz');
                    self.showError(response.data || 'Registration failed');
                }
            })
            /*.fail(function() {
                self.hideLoading();
                $submitBtn.prop('disabled', false).html('🚀 Start Quiz');
                self.showError('Connection error. Please try again.');
            });*/
    },
    
    /**
     * ✅ VALIDATE REGISTRATION FORM
     */
    validateRegistrationForm: function(data) {
        // Check required fields
        if (!data.name || data.name.trim() === '') {
            return { valid: false, message: 'Please enter your full name' };
        }
        
        if (!data.phone || data.phone.trim() === '') {
            return { valid: false, message: 'Please enter your phone number' };
        }
        
        // Validate phone format (Vietnamese)
		var phonePattern = /^(0[0-9]{9}|84[0-9]{9})$/;
			if (!phonePattern.test(data.phone.replace(/\s/g, ''))) {
				return { valid: false, message: 'Định dạng: 0938474356 hoặc 84938474356' };
			}        
        // Validate email if provided
        if (data.email && data.email.trim() !== '') {
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(data.email)) {
                return { valid: false, message: 'Please enter a valid email address' };
            }
        }
        
        // Validate pharmacy code format if provided
        if (data.pharmacy_code && data.pharmacy_code.trim() !== '') {
            var pharmacyPattern = /^[A-Z]{2}-[0-9]{6}$/;
            if (!pharmacyPattern.test(data.pharmacy_code)) {
                return { valid: false, message: 'Pharmacy code must be in format XX-######' };
            }
        }
        
        return { valid: true };
    },
    
    /**
     * 🚀 START QUIZ
     */
    startQuiz: function() {
        var self = this;
        
        this.showLoading('Loading quiz questions...');
        
        jQuery.post(vefifyAjax.ajaxUrl, {
            action: 'vefify_start_quiz',
            vefify_nonce: vefifyAjax.nonce,
            participant_id: this.config.participantId,
            session_token: this.config.sessionToken
        })
        .done(function(response) {
            if (response.success) {
                self.config.questions = response.data.questions;
                self.config.totalQuestions = response.data.total_questions;
                self.config.timeLimit = response.data.time_limit;
                self.config.timeRemaining = response.data.time_limit;
                self.config.quizStartTime = Date.now();
                
                self.hideLoading();
                self.showQuizSection();
                self.displayQuestion(0);
                
                if (self.config.timeLimit > 0) {
                    self.startTimer();
                }
                
                self.showSuccess('Quiz started! Good luck! 🍀');
            } else {
                self.hideLoading();
                self.showError(response.data || 'Failed to start quiz');
            }
        })
        .fail(function() {
            self.hideLoading();
            self.showError('Connection error. Please try again.');
        });
    },
    
    /**
     * 📱 SHOW QUIZ SECTION
     */
    showQuizSection: function() {
        this.container.find('.vefify-registration-section').fadeOut();
        this.container.find('.vefify-quiz-section').fadeIn();
        
        // Update progress
        this.updateProgress();
    },
    
    /**
     * 📝 DISPLAY QUESTION
     */
    displayQuestion: function(index) {
        if (index < 0 || index >= this.config.questions.length) {
            return;
        }
        
        this.config.currentQuestionIndex = index;
        var question = this.config.questions[index];
        
        var html = '<div class="vefify-question" data-question-id="' + question.id + '">';
        html += '<div class="vefify-question-header">';
        html += '<h3 class="vefify-question-title">Question ' + (index + 1) + '</h3>';
        html += '<div class="vefify-question-text">' + question.question_text + '</div>';
        html += '</div>';
        
        html += '<div class="vefify-question-options">';
        
        for (var i = 0; i < question.options.length; i++) {
            var option = question.options[i];
            var optionId = 'option_' + question.id + '_' + i;
            var checked = this.config.answers[question.id] === option.option_value ? 'checked' : '';
            
            html += '<div class="vefify-question-option">';
            html += '<input type="radio" id="' + optionId + '" name="question_' + question.id + '" value="' + option.option_value + '" ' + checked + '>';
            html += '<label for="' + optionId + '">';
            html += '<span class="vefify-option-marker"></span>';
            html += '<span class="vefify-option-text">' + option.option_text + '</span>';
            html += '</label>';
            html += '</div>';
        }
        
        html += '</div>';
        html += '</div>';
        
        this.container.find('#vefify-question-container').html(html);
        
        // Update navigation buttons
        this.updateNavigation();
        this.updateProgress();
    },
    
    /**
     * ⬅️ PREVIOUS QUESTION
     */
    prevQuestion: function() {
        this.saveCurrentAnswer();
        
        if (this.config.currentQuestionIndex > 0) {
            this.displayQuestion(this.config.currentQuestionIndex - 1);
        }
    },
    
    /**
     * ➡️ NEXT QUESTION
     */
    nextQuestion: function() {
        this.saveCurrentAnswer();
        
        if (this.config.currentQuestionIndex < this.config.questions.length - 1) {
            this.displayQuestion(this.config.currentQuestionIndex + 1);
        }
    },
    
    /**
     * 💾 SAVE CURRENT ANSWER
     */
    saveCurrentAnswer: function() {
        var currentQuestion = this.config.questions[this.config.currentQuestionIndex];
        var selectedOption = this.container.find('input[name="question_' + currentQuestion.id + '"]:checked');
        
        if (selectedOption.length > 0) {
            this.config.answers[currentQuestion.id] = selectedOption.val();
        }
        
        this.updateProgress();
        this.updateNavigation();
    },
    
    /**
     * 🔄 UPDATE NAVIGATION
     */
    updateNavigation: function() {
        var $prevBtn = this.container.find('#vefify-prev-question');
        var $nextBtn = this.container.find('#vefify-next-question');
        var $finishBtn = this.container.find('#vefify-finish-quiz');
        
        // Previous button
        if (this.config.currentQuestionIndex === 0) {
            $prevBtn.hide();
        } else {
            $prevBtn.show();
        }
        
        // Next/Finish button
        if (this.config.currentQuestionIndex === this.config.questions.length - 1) {
            $nextBtn.hide();
            $finishBtn.show();
        } else {
            $nextBtn.show();
            $finishBtn.hide();
        }
        
        // Enable finish button only if all questions answered
        var answeredCount = Object.keys(this.config.answers).length;
        if (answeredCount === this.config.questions.length) {
            $finishBtn.prop('disabled', false);
        } else {
            $finishBtn.prop('disabled', true);
        }
    },
    
    /**
     * 📊 UPDATE PROGRESS
     */
    updateProgress: function() {
        var answeredCount = Object.keys(this.config.answers).length;
        var progressPercent = (answeredCount / this.config.questions.length) * 100;
        
        this.container.find('.vefify-progress-fill').css('width', progressPercent + '%');
        this.container.find('.vefify-progress-text .current').text(this.config.currentQuestionIndex + 1);
        this.container.find('.vefify-progress-text .total').text(this.config.questions.length);
    },
    
    /**
     * ⏱️ START TIMER
     */
    startTimer: function() {
        var self = this;
        
        this.config.timerInterval = setInterval(function() {
            self.config.timeRemaining--;
            
            var minutes = Math.floor(self.config.timeRemaining / 60);
            var seconds = self.config.timeRemaining % 60;
            
            var timeDisplay = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            self.container.find('#vefify-time-remaining').text(timeDisplay);
            
            // Change color when time is running low
            if (self.config.timeRemaining <= 60) {
                self.container.find('.vefify-timer').addClass('vefify-timer-warning');
            }
            
            if (self.config.timeRemaining <= 0) {
                self.timeUp();
            }
        }, 1000);
    },
    
    /**
     * ⏰ TIME UP
     */
    timeUp: function() {
        clearInterval(this.config.timerInterval);
        this.showError('⏰ Time is up! Submitting your quiz...');
        
        setTimeout(() => {
            this.finishQuiz();
        }, 2000);
    },
    
    /**
     * 🏁 FINISH QUIZ
     */
    finishQuiz: function() {
        var self = this;
        
        // Save current answer
        this.saveCurrentAnswer();
        
        // Check if all questions answered
        if (Object.keys(this.config.answers).length < this.config.questions.length) {
            if (!confirm('You have not answered all questions. Are you sure you want to finish?')) {
                return;
            }
        }
        
        this.showLoading('Submitting your quiz...');
        
        // Stop timer
        if (this.config.timerInterval) {
            clearInterval(this.config.timerInterval);
        }
        
        jQuery.post(vefifyAjax.ajaxUrl, {
            action: 'vefify_finish_quiz',
            vefify_nonce: vefifyAjax.nonce,
            participant_id: this.config.participantId,
            session_token: this.config.sessionToken,
            answers: this.config.answers
        })
        .done(function(response) {
            self.hideLoading();
            
            if (response.success) {
                self.showResults(response.data);
            } else {
                self.showError(response.data || 'Failed to submit quiz');
            }
        })
        .fail(function() {
            self.hideLoading();
            self.showError('Connection error. Please try again.');
        });
    },
    
    /**
     * 📊 SHOW RESULTS
     */
    showResults: function(results) {
        this.container.find('.vefify-quiz-section').fadeOut();
        
        var html = '<div class="vefify-results-content">';
        html += '<div class="vefify-results-header">';
        html += '<h2>🎉 Quiz Completed!</h2>';
        html += '</div>';
        
        html += '<div class="vefify-score-display">';
        html += '<div class="vefify-score-circle">';
        html += '<div class="vefify-score-number">' + results.score + '/' + results.total + '</div>';
        html += '<div class="vefify-score-percentage">' + results.percentage + '%</div>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="vefify-results-details">';
        html += '<div class="vefify-result-item">';
        html += '<span class="vefify-result-label">Correct Answers:</span>';
        html += '<span class="vefify-result-value">' + results.correct + ' out of ' + results.total + '</span>';
        html += '</div>';
        
        html += '<div class="vefify-result-item">';
        html += '<span class="vefify-result-label">Score:</span>';
        html += '<span class="vefify-result-value">' + results.percentage + '%</span>';
        html += '</div>';
        
        html += '<div class="vefify-result-item">';
        html += '<span class="vefify-result-label">Status:</span>';
        html += '<span class="vefify-result-value ' + (results.passed ? 'passed' : 'failed') + '">';
        html += results.passed ? '✅ Passed' : '❌ Not Passed';
        html += '</span>';
        html += '</div>';
        html += '</div>';
        
        // Show gift information if available
        if (results.gift) {
            html += '<div class="vefify-gift-section">';
            html += '<h3>🎁 Congratulations!</h3>';
            html += '<p>You have earned a gift: <strong>' + results.gift.name + '</strong></p>';
            html += '<p>Your gift code: <code>' + results.gift.code + '</code></p>';
            html += '</div>';
        }
        
        html += '<div class="vefify-results-actions">';
        html += '<button type="button" class="vefify-btn vefify-btn-primary" onclick="window.print()">🖨️ Print Results</button>';
        html += '<button type="button" class="vefify-btn vefify-btn-secondary" onclick="location.reload()">🔄 Take Another Quiz</button>';
        html += '</div>';
        
        html += '</div>';
        
        this.container.find('.vefify-results-section').html(html).fadeIn();
        
        this.showSuccess('Quiz completed successfully! 🎉');
    },
    
    /**
     * 📱 FORMAT PHONE NUMBER
     */
    formatPhoneNumber: function(input) {
        var value = input.value.replace(/\D/g, '');
        
        // Limit to 11 digits
        if (value.length > 11) {
            value = value.substring(0, 11);
        }
        
        /* Format: 0123 456 789
        if (value.length >= 4) {
            value = value.substring(0, 4) + ' ' + value.substring(4);
        }
        if (value.length >= 8) {
            value = value.substring(0, 8) + ' ' + value.substring(8);
        }*/
        
        input.value = value;
    },
    
    /**
     * 🏥 FORMAT PHARMACY CODE
     */
    formatPharmacyCode: function(input) {
        var value = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        
        // Format: XX-######
        if (value.length >= 2) {
            value = value.substring(0, 2) + '-' + value.substring(2, 8);
        }
        
        input.value = value;
    },
    
    /**
     * ⏳ SHOW LOADING
     */
    showLoading: function(message) {
        var $overlay = this.container.find('.vefify-loading-overlay');
        if (message) {
            $overlay.find('p').text(message);
        }
        $overlay.fadeIn();
    },
    
    /**
     * ❌ HIDE LOADING
     */
    hideLoading: function() {
        this.container.find('.vefify-loading-overlay').fadeOut();
    },
    
    /**
     * ✅ SHOW SUCCESS MESSAGE
     */
    showSuccess: function(message) {
        this.showMessage(message, 'success');
    },
    
    /**
     * ❌ SHOW ERROR MESSAGE
     */
    showError: function(message) {
        this.showMessage(message, 'error');
    },
    
    /**
     * 💬 SHOW MESSAGE
     */
    showMessage: function(message, type) {
        var $container = this.container;
        
        // Remove existing messages
        $container.find('.vefify-message').remove();
        
        // Create message element
        var $message = jQuery('<div class="vefify-message vefify-message-' + type + '">' + message + '</div>');
        
        // Insert at top of container
        $container.prepend($message);
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $message.remove();
            });
        }, 5000);
        
        // Scroll to top
        jQuery('html, body').animate({
            scrollTop: $container.offset().top - 20
        }, 300);
    },
    
    /**
     * 🔄 RESTART QUIZ
     */
    restartQuiz: function() {
        if (confirm('Are you sure you want to restart the quiz? All progress will be lost.')) {
            // Reset configuration
            this.config.currentQuestionIndex = 0;
            this.config.answers = {};
            this.config.timeRemaining = this.config.timeLimit;
            
            // Stop timer
            if (this.config.timerInterval) {
                clearInterval(this.config.timerInterval);
            }
            
            // Hide all sections
            this.container.find('.vefify-quiz-section, .vefify-results-section').hide();
            this.container.find('.vefify-registration-section').show();
            
            // Reset form
            this.container.find('#vefify-registration-form')[0].reset();
            this.container.find('button[type="submit"]').prop('disabled', false).html('🚀 Start Quiz');
        }
    }
};

/**
 * 🎯 GLOBAL FUNCTIONS FOR EASY ACCESS
 */
window.startQuiz = function(campaignId) {
    // Simple function to start quiz with campaign ID
    var shortcode = '[vefify_quiz campaign_id="' + campaignId + '"]';
    console.log('Starting quiz with shortcode:', shortcode);
    
    // You can implement logic to dynamically load quiz here
    alert('To start this quiz, add this shortcode to a page: ' + shortcode);
};

window.restartQuiz = function() {
    if (typeof VefifyQuiz !== 'undefined' && VefifyQuiz.restartQuiz) {
        VefifyQuiz.restartQuiz();
    }
};

/**
 * 📱 RESPONSIVE UTILITIES
 */
jQuery(document).ready(function($) {
    // Add mobile class for responsive design
    if ($(window).width() <= 768) {
        $('.vefify-quiz-container').addClass('vefify-mobile');
    }
    
    // Handle window resize
    $(window).resize(function() {
        if ($(window).width() <= 768) {
            $('.vefify-quiz-container').addClass('vefify-mobile');
        } else {
            $('.vefify-quiz-container').removeClass('vefify-mobile');
        }
    });
    
    // Smooth scrolling for quiz navigation
    $('.vefify-quiz-container').on('click', '.vefify-btn', function() {
        var $container = $(this).closest('.vefify-quiz-container');
        $('html, body').animate({
            scrollTop: $container.offset().top - 20
        }, 300);
    });
});

/**
 * 🔍 DEBUG FUNCTIONS (Remove in production)
 */
if (typeof console !== 'undefined') {
    window.vefifyDebug = {
        getConfig: function() {
            return VefifyQuiz.config;
        },
        
        getAnswers: function() {
            return VefifyQuiz.config.answers;
        },
        
        setAnswer: function(questionId, answer) {
            VefifyQuiz.config.answers[questionId] = answer;
            console.log('Answer set:', questionId, '=', answer);
        },
        
        skipToResults: function() {
            VefifyQuiz.showResults({
                score: 3,
                total: 5,
                correct: 3,
                percentage: 60,
                passed: true,
                gift: null
            });
        }
    };
}