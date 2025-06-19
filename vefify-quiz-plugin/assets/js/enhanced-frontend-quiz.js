/**
 * 🚀 FIXED FRONTEND JAVASCRIPT - Handle Direct Quiz Start
 * File: assets/js/enhanced-frontend-quiz.js
 * 
 * Enhanced to handle both AJAX and direct form submission
 */

var VefifyQuiz = {
    
    // Configuration
    config: {
        currentQuestionIndex: 0,
        totalQuestions: 0,
        participantId: null,
        sessionToken: null,
        sessionId: null,
        campaignId: null,
        questions: [],
        answers: {},
        timeLimit: 0,
        timeRemaining: 0,
        timerInterval: null,
        quizStartTime: null,
        passScore: 3,
        questionStartTime: null,
        autoSaveInterval: null
    },
    
    /**
     * 🎯 ENHANCED INITIALIZATION - Handle both AJAX and direct start
     */
    init: function(containerId, options) {
        console.log('🚀 Initializing Enhanced VefifyQuiz...', options);
        
        this.containerId = containerId;
        this.container = jQuery('#' + containerId);
        
        if (this.container.length === 0) {
            console.error('❌ Quiz container not found:', containerId);
            return;
        }
        
        // Store configuration
        this.config.campaignId = options.campaignId;
        this.config.timeLimit = options.timeLimit || 0;
        this.config.passScore = options.passScore || 3;
        
        // Bind events
        this.bindEvents();
        
        console.log('✅ Enhanced VefifyQuiz initialized successfully');
    },
    
    /**
     * 🚀 NEW: Initialize with data (for direct quiz start)
     */
    initializeWithData: function(data) {
        console.log('🎯 Initializing quiz with data:', data);
        
        this.config.participantId = data.participantId;
        this.config.sessionToken = data.sessionToken;
        this.config.sessionId = data.sessionId;
        this.config.campaignId = data.campaignId;
        this.config.questions = data.questions;
        this.config.totalQuestions = data.questions.length;
        this.config.timeLimit = data.timeLimit;
        this.config.timeRemaining = data.timeLimit;
        this.config.passScore = data.passScore;
        this.config.quizStartTime = Date.now();
        
        // Start the quiz interface
        this.startQuizInterface();
        
        // Start timer if enabled
        if (this.config.timeLimit > 0) {
            this.startTimer();
        }
        
        console.log('✅ Quiz initialized with data successfully');
    },
    
    /**
     * 🎮 START QUIZ INTERFACE - Direct quiz start
     */
    startQuizInterface: function() {
        // Display first question
        this.displayQuestion(0);
        
        // Update progress
        this.updateProgress();
        
        console.log('🎯 Quiz interface started');
    },
    
    /**
     * 🔗 BIND EVENTS
     */
    bindEvents: function() {
        var self = this;
        
        // Registration form submission (if present)
        jQuery(document).on('submit', '.vefify-form', function(e) {
            // Let form submit normally (no preventDefault)
            console.log('📝 Form submitted normally');
        });
        
        // Quiz navigation
        jQuery(document).on('click', '#vefify-next-question', function() {
            self.nextQuestion();
        });
        
        jQuery(document).on('click', '#vefify-prev-question', function() {
            self.prevQuestion();
        });
        
        jQuery(document).on('click', '#vefify-finish-quiz', function() {
            self.finishQuiz();
        });
        
        // Answer selection
        jQuery(document).on('change', '.vefify-question-option input', function() {
            self.handleAnswerChange(this);
        });
        
        // Phone number formatting
        jQuery(document).on('input', '#vefify_phone', function() {
            self.formatPhoneNumber(this);
        });
        
        // Pharmacy code formatting
        jQuery(document).on('input', '#vefify_pharmacy_code', function() {
            self.formatPharmacyCode(this);
        });
        
        // Prevent accidental page leave during active quiz
        window.addEventListener('beforeunload', function(e) {
            if (self.config.sessionId && self.config.currentQuestionIndex >= 0) {
                e.preventDefault();
                e.returnValue = 'You have an active quiz. Are you sure you want to leave?';
                return 'You have an active quiz. Are you sure you want to leave?';
            }
        });
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
        
        // Record question start time
        this.config.questionStartTime = Date.now();
        
        var html = this.buildQuestionHTML(question, index);
        jQuery('#vefify-question-container').html(html);
        
        // Update interface
        this.updateNavigation();
        this.updateProgress();
        
        // Focus management for accessibility
        jQuery('#vefify-question-container').focus();
        
        // Load saved answer if exists
        this.loadSavedAnswer(question.id);
        
        console.log('📝 Displayed question:', index + 1);
    },
    
    /**
     * 🏗️ BUILD QUESTION HTML
     */
    buildQuestionHTML: function(question, index) {
        var html = '<div class="vefify-question" data-question-id="' + question.id + '">';
        
        // Question header
        html += '<div class="vefify-question-header">';
        html += '<div class="question-meta">';
        html += '<span class="question-number">Question ' + (index + 1) + ' of ' + this.config.totalQuestions + '</span>';
        html += '<span class="question-difficulty difficulty-' + question.difficulty + '">' + question.difficulty + '</span>';
        if (question.points > 1) {
            html += '<span class="question-points">' + question.points + ' points</span>';
        }
        html += '</div>';
        html += '<h3 class="vefify-question-title">' + question.text + '</h3>';
        html += '</div>';
        
        // Question options
        html += '<div class="vefify-question-options">';
        html += this.buildOptionsHTML(question);
        html += '</div>';
        
        html += '</div>';
        
        return html;
    },
    
    /**
     * 🎯 BUILD OPTIONS HTML
     */
    buildOptionsHTML: function(question) {
        var html = '';
        var inputType = question.type === 'multiple_choice' ? 'checkbox' : 'radio';
        var inputName = 'question_' + question.id;
        
        for (var i = 0; i < question.options.length; i++) {
            var option = question.options[i];
            var optionId = 'option_' + question.id + '_' + option.id;
            var checked = this.isOptionSelected(question.id, option.id) ? 'checked' : '';
            
            html += '<div class="vefify-question-option">';
            html += '<input type="' + inputType + '" ';
            html += 'id="' + optionId + '" ';
            html += 'name="' + inputName + '" ';
            html += 'value="' + option.id + '" ';
            html += checked + '>';
            html += '<label for="' + optionId + '">';
            html += '<span class="vefify-option-marker"></span>';
            html += '<span class="vefify-option-text">' + option.text + '</span>';
            html += '</label>';
            html += '</div>';
        }
        
        return html;
    },
    
    /**
     * ✅ CHECK IF OPTION IS SELECTED
     */
    isOptionSelected: function(questionId, optionId) {
        var answer = this.config.answers[questionId];
        if (!answer) return false;
        
        if (Array.isArray(answer)) {
            return answer.includes(optionId);
        } else {
            return answer == optionId;
        }
    },
    
    /**
     * 🔄 HANDLE ANSWER CHANGE
     */
    handleAnswerChange: function(input) {
        var $input = jQuery(input);
        var questionId = $input.closest('.vefify-question').data('question-id');
        
        this.saveCurrentAnswer();
        
        // Submit answer to server if we have session
        if (this.config.sessionId) {
            this.submitAnswerToServer(questionId);
        }
        
        this.updateNavigation();
        
        // Provide immediate feedback
        this.showAnswerFeedback($input);
    },
    
    /**
     * 💾 SAVE CURRENT ANSWER
     */
    saveCurrentAnswer: function() {
        var currentQuestion = this.config.questions[this.config.currentQuestionIndex];
        var questionId = currentQuestion.id;
        var questionType = currentQuestion.type;
        
        var selectedValues = [];
        var inputSelector = 'input[name="question_' + questionId + '"]:checked';
        
        jQuery(inputSelector).each(function() {
            selectedValues.push(parseInt(jQuery(this).val()));
        });
        
        if (selectedValues.length > 0) {
            if (questionType === 'multiple_choice') {
                this.config.answers[questionId] = selectedValues;
            } else {
                this.config.answers[questionId] = selectedValues[0];
            }
        } else {
            delete this.config.answers[questionId];
        }
        
        this.updateProgress();
    },
    
    /**
     * 📤 SUBMIT ANSWER TO SERVER
     */
    submitAnswerToServer: function(questionId) {
        if (!this.config.sessionId) return;
        
        var timeSpent = Date.now() - this.config.questionStartTime;
        var answer = this.config.answers[questionId];
        
        jQuery.post(vefifyAjax.ajaxUrl, {
            action: 'vefify_submit_answer',
            vefify_nonce: vefifyAjax.nonce,
            participant_id: this.config.participantId,
            session_id: this.config.sessionId,
            question_id: questionId,
            answer: answer,
            time_spent: Math.round(timeSpent / 1000)
        })
        .done(function(response) {
            if (response.success) {
                console.log('💾 Answer saved:', response.data);
            }
        })
        .fail(function() {
            console.warn('⚠️ Failed to save answer for question', questionId);
        });
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
     * ⬅️ PREVIOUS QUESTION
     */
    prevQuestion: function() {
        this.saveCurrentAnswer();
        
        if (this.config.currentQuestionIndex > 0) {
            this.displayQuestion(this.config.currentQuestionIndex - 1);
        }
    },
    
    /**
     * 🔄 UPDATE NAVIGATION
     */
    updateNavigation: function() {
        var $prevBtn = jQuery('#vefify-prev-question');
        var $nextBtn = jQuery('#vefify-next-question');
        var $finishBtn = jQuery('#vefify-finish-quiz');
        
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
        
        // Update progress bar
        jQuery('.vefify-progress-fill').css('width', progressPercent + '%');
        
        // Update progress text
        jQuery('.vefify-progress-text .current').text(this.config.currentQuestionIndex + 1);
        jQuery('.vefify-progress-text .total').text(this.config.questions.length);
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
            jQuery('#vefify-time-remaining').text(timeDisplay);
            
            // Change color when time is running low
            if (self.config.timeRemaining <= 60) {
                jQuery('.vefify-timer').addClass('vefify-timer-critical');
            } else if (self.config.timeRemaining <= 300) {
                jQuery('.vefify-timer').addClass('vefify-timer-warning');
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
        this.showMessage('⏰ Time is up! Submitting your quiz...', 'warning');
        
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
        
        this.showLoading('Calculating your score...');
        
        // Stop timer
        if (this.config.timerInterval) {
            clearInterval(this.config.timerInterval);
        }
        
        // Remove page leave warning
        window.removeEventListener('beforeunload', this.beforeUnloadHandler);
        
        jQuery.post(vefifyAjax.ajaxUrl, {
            action: 'vefify_finish_quiz',
            vefify_nonce: vefifyAjax.nonce,
            participant_id: this.config.participantId,
            session_token: this.config.sessionToken,
            session_id: this.config.sessionId
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
        var html = '<div class="vefify-results-content">';
        html += '<div class="vefify-results-header">';
        html += '<h2>' + (results.passed ? '🎉 Congratulations!' : '😔 Keep Trying!') + '</h2>';
        html += '<p class="result-message">' + this.getResultMessage(results) + '</p>';
        html += '</div>';
        
        // Score display
        html += '<div class="vefify-score-display">';
        html += '<div class="vefify-score-circle ' + (results.passed ? 'passed' : 'failed') + '">';
        html += '<div class="vefify-score-number">' + results.correct + '/' + results.total + '</div>';
        html += '<div class="vefify-score-percentage">' + results.percentage + '%</div>';
        html += '</div>';
        html += '</div>';
        
        // Gift section
        if (results.gift) {
            html += '<div class="vefify-gift-section">';
            html += '<h3>🎁 You\'ve Earned a Gift!</h3>';
            html += '<div class="gift-card">';
            html += '<h4>' + results.gift.gift_name + '</h4>';
            html += '<p>' + results.gift.description + '</p>';
            html += '<div class="gift-code">Code: <strong>' + results.gift.gift_code + '</strong></div>';
            html += '</div>';
            html += '</div>';
        }
        
        // Action buttons
        html += '<div class="vefify-results-actions">';
        html += '<button type="button" class="vefify-btn vefify-btn-primary" onclick="window.print()">🖨️ Print Results</button>';
        html += '<button type="button" class="vefify-btn vefify-btn-secondary" onclick="location.reload()">🔄 Take Another Quiz</button>';
        html += '</div>';
        
        html += '</div>';
        
        jQuery('.vefify-results-section').html(html).show();
        
        // Hide quiz content
        jQuery('#vefify-question-container, .vefify-quiz-navigation').hide();
        
        this.showMessage('🎉 Quiz completed successfully!', 'success');
    },
    
    /**
     * 🎯 GET RESULT MESSAGE
     */
    getResultMessage: function(results) {
        if (results.passed) {
            if (results.percentage >= 90) {
                return 'Excellent performance! You really know your stuff!';
            } else if (results.percentage >= 80) {
                return 'Great job! You passed with a good score!';
            } else {
                return 'Well done! You passed the quiz!';
            }
        } else {
            return 'Don\'t worry! Review the material and try again. You need ' + this.config.passScore + ' correct answers to pass.';
        }
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
     * ✅ LOAD SAVED ANSWER
     */
    loadSavedAnswer: function(questionId) {
        // Implementation for loading saved answers
    },
    
    /**
     * 💬 SHOW ANSWER FEEDBACK
     */
    showAnswerFeedback: function($input) {
        // Brief visual feedback
        $input.closest('.vefify-question-option').addClass('selected-feedback');
        setTimeout(function() {
            $input.closest('.vefify-question-option').removeClass('selected-feedback');
        }, 200);
    },
    
    /**
     * ⏳ SHOW LOADING
     */
    showLoading: function(message) {
        jQuery('body').append('<div class="vefify-loading-overlay"><div class="vefify-loading-content"><div class="vefify-spinner"></div><p>' + message + '</p></div></div>');
    },
    
    /**
     * ❌ HIDE LOADING
     */
    hideLoading: function() {
        jQuery('.vefify-loading-overlay').remove();
    },
    
    /**
     * 💬 SHOW MESSAGE
     */
    showMessage: function(message, type) {
        var $message = jQuery('<div class="vefify-message vefify-message-' + type + '">' + message + '</div>');
        jQuery('body').prepend($message);
        
        setTimeout(function() {
            $message.fadeOut(function() {
                $message.remove();
            });
        }, 5000);
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
    }
};

// Auto-initialize if quiz data is present
jQuery(document).ready(function($) {
    // Check if we have a quiz container with active class
    if ($('.vefify-quiz-container.vefify-quiz-active').length > 0) {
        console.log('🎯 Quiz container with active class found - quiz should auto-start');
    }
    
    // Enhanced responsive handling
    function handleResize() {
        if ($(window).width() <= 768) {
            $('.vefify-quiz-container').addClass('vefify-mobile');
        } else {
            $('.vefify-quiz-container').removeClass('vefify-mobile');
        }
    }
    
    handleResize();
    $(window).resize(handleResize);
});

// Global debug functions
window.vefifyDebug = {
    getConfig: function() {
        return VefifyQuiz.config;
    },
    
    getAnswers: function() {
        return VefifyQuiz.config.answers;
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