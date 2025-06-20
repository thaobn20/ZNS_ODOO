/**
 * 🚀 ENHANCED VEFIFY QUIZ FRONTEND JAVASCRIPT
 * File: assets/js/enhanced-frontend-quiz.js
 * 
 * COMPREHENSIVE SOLUTION:
 * ✅ Handles AJAX registration
 * ✅ Supports direct quiz start (non-AJAX fallback)
 * ✅ Complete quiz navigation
 * ✅ Timer functionality
 * ✅ Answer tracking and scoring
 * ✅ Results display
 * ✅ Mobile responsive
 * ✅ Vietnamese phone formatting
 */

var VefifyQuiz = {
    
    // Configuration object to store quiz state
    config: {
        currentQuestionIndex: 0,
        totalQuestions: 0,
        participantId: null,
        sessionId: null,
        campaignId: null,
        questions: [],
        answers: {},
        timeLimit: 0,
        timeRemaining: 0,
        timerInterval: null,
        quizStartTime: null,
        passScore: 3,
        container: null,
        containerId: null
    },
    
    /**
     * 🎯 MAIN INITIALIZATION METHOD
     * Called from shortcode for AJAX-enabled quizzes
     */
    init: function(containerId, options) {
        console.log('🚀 Initializing VefifyQuiz...', options);
        
        this.config.containerId = containerId;
        this.config.container = jQuery('#' + containerId);
        this.config.campaignId = options.campaignId;
        this.config.timeLimit = options.timeLimit || 0;
        this.config.passScore = options.passScore || 3;
        
        if (this.config.container.length === 0) {
            console.error('❌ Quiz container not found:', containerId);
            return;
        }
        
        this.bindEvents();
        console.log('✅ VefifyQuiz initialized successfully');
    },
    
    /**
     * 🚀 DIRECT QUIZ INITIALIZATION
     * Called when quiz starts immediately (non-AJAX registration)
     */
    initializeWithData: function(data) {
        console.log('🎯 Initializing quiz with data:', data);
        
        // Store all the quiz data
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
        
        // Find the quiz container automatically
        this.config.container = jQuery('.vefify-quiz-container.vefify-quiz-active');
        if (this.config.container.length === 0) {
            this.config.container = jQuery('.vefify-quiz-container');
        }
        
        if (this.config.container.length === 0) {
            console.error('❌ Quiz container not found');
            return;
        }
        
        // Bind events for this specific container
        this.bindEventsForContainer();
        
        // Start the quiz interface immediately
        this.startQuizInterface();
        
        // Start timer if enabled
        if (this.config.timeLimit > 0) {
            this.startTimer();
        }
        
        console.log('✅ Quiz initialized with data successfully');
    },
    
    /**
     * 🔗 BIND ALL EVENTS (for AJAX-enabled quizzes)
     */
    bindEvents: function() {
        var self = this;
        
        // Registration form submission via AJAX
        this.config.container.on('submit', '#vefify-registration-form', function(e) {
            e.preventDefault();
            self.handleRegistration(this);
        });
        
        // Bind quiz navigation events
        this.bindEventsForContainer();
        
        // Phone number formatting (Vietnamese format)
        this.config.container.on('input', '#vefify_phone', function() {
            self.formatPhoneNumber(this);
        });
        
        // Pharmacy code formatting
        this.config.container.on('input', '#vefify_pharmacy_code', function() {
            self.formatPharmacyCode(this);
        });
        
        // Prevent page leave during quiz
        jQuery(window).on('beforeunload', function(e) {
            if (self.config.sessionId && self.config.currentQuestionIndex >= 0) {
                return 'You have an active quiz. Are you sure you want to leave?';
            }
        });
    },
    
    /**
     * 🔗 BIND QUIZ NAVIGATION EVENTS
     * Used by both AJAX and direct quiz initialization
     */
    bindEventsForContainer: function() {
        var self = this;
        
        // Quiz navigation buttons
        this.config.container.on('click', '#vefify-next-question', function() {
            self.nextQuestion();
        });
        
        this.config.container.on('click', '#vefify-prev-question', function() {
            self.prevQuestion();
        });
        
        this.config.container.on('click', '#vefify-finish-quiz', function() {
            self.finishQuiz();
        });
        
        // Answer selection handling
        this.config.container.on('change', '.vefify-question-option input', function() {
            self.handleAnswerChange(this);
        });
    },
    
    /**
     * 📝 HANDLE REGISTRATION FORM SUBMISSION
     */
    handleRegistration: function(form) {
        var self = this;
        var $form = jQuery(form);
        var $submitBtn = $form.find('#vefify-start-btn');
        var originalText = $submitBtn.text();
        
        // Show loading state
        $submitBtn.prop('disabled', true).text('⏳ Registering...');
        this.showLoading('Registering participant...');
        
        // Prepare form data for AJAX
        var formData = new FormData(form);
        
        // Submit via AJAX
        jQuery.ajax({
            url: vefifyAjax.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                self.hideLoading();
                
                if (response.success) {
                    // Store session data from successful registration
                    self.config.participantId = response.data.participant_id;
                    self.config.sessionId = response.data.session_id;
					self.config.questions = response.data.questions;       // CRUCIAL
                    self.config.questions = response.data.questions;
                    self.config.totalQuestions = response.data.total_questions;
                    self.config.timeLimit = response.data.time_limit;
                    self.config.timeRemaining = response.data.time_limit;
                    self.config.passScore = response.data.pass_score;
					
					// Debug log
					console.log('Questions received:', response.data.questions);
                    // Show success message
                    self.showMessage(response.data.message, 'success');
                    
                    // Start quiz after short delay
                    setTimeout(function() {
                        self.startQuizInterface();
                    }, 1000);
                    
                } else {
                    // Show error message
                    self.showMessage(response.data || 'Registration failed', 'error');
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                self.hideLoading();
                self.showMessage('Connection error. Please try again.', 'error');
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    },
    
    /**
     * 🎮 START QUIZ INTERFACE
     */
    startQuizInterface: function() {
        console.log('🎯 Starting quiz interface');
        
        // Hide registration section with smooth animation
        this.config.container.find('#vefify-registration-section').slideUp();
        
        // Show quiz section
        this.config.container.find('#vefify-quiz-section').slideDown();
        
        // Display first question
        this.displayQuestion(0);
        
        // Start timer if enabled
        if (this.config.timeLimit > 0) {
            this.startTimer();
        }
        
        // Update progress bar
        this.updateProgress();
        
        // Record start time
        this.config.quizStartTime = Date.now();
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
        
        var html = this.buildQuestionHTML(question, index);
        this.config.container.find('#vefify-question-container').html(html);
        
        // Update navigation and progress
        this.updateNavigation();
        this.updateProgress();
        
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
        html += '<h3 class="vefify-question-title">Question ' + (index + 1) + '</h3>';
        html += '<div class="vefify-question-text">' + question.question_text + '</div>';
        html += '</div>';
        
        // Question options
        html += '<div class="vefify-question-options">';
        
        if (question.options && question.options.length > 0) {
            for (var i = 0; i < question.options.length; i++) {
                var option = question.options[i];
                var optionId = 'option_' + question.id + '_' + i;
                var checked = this.isOptionSelected(question.id, option.option_value) ? 'checked' : '';
                
                html += '<div class="vefify-question-option">';
                html += '<input type="radio" id="' + optionId + '" name="question_' + question.id + '" value="' + option.option_value + '" ' + checked + '>';
                html += '<label for="' + optionId + '">';
                html += '<span class="vefify-option-marker"></span>';
                html += '<span class="vefify-option-text">' + option.option_text + '</span>';
                html += '</label>';
                html += '</div>';
            }
        } else {
            html += '<div class="vefify-no-options">No options available for this question.</div>';
        }
        
        html += '</div>';
        html += '</div>';
        
        return html;
    },
    
    /**
     * ✅ CHECK IF OPTION IS SELECTED
     */
    isOptionSelected: function(questionId, optionValue) {
        var answer = this.config.answers[questionId];
        if (!answer) return false;
        
        if (Array.isArray(answer)) {
            return answer.includes(optionValue);
        } else {
            return answer == optionValue;
        }
    },
    
    /**
     * 🔄 HANDLE ANSWER CHANGE
     */
    handleAnswerChange: function(input) {
        var $input = jQuery(input);
        var questionId = $input.closest('.vefify-question').data('question-id');
        
        // Save the current answer
        this.saveCurrentAnswer();
        
        // Update navigation buttons
        this.updateNavigation();
        
        // Visual feedback
        $input.closest('.vefify-question-option').addClass('selected-animation');
        setTimeout(function() {
            $input.closest('.vefify-question-option').removeClass('selected-animation');
        }, 300);
        
        console.log('📝 Answer changed for question', questionId);
    },
    
    /**
     * 💾 SAVE CURRENT ANSWER
     */
    saveCurrentAnswer: function() {
        var currentQuestion = this.config.questions[this.config.currentQuestionIndex];
        if (!currentQuestion) return;
        
        var questionId = currentQuestion.id;
        var selectedOption = this.config.container.find('input[name="question_' + questionId + '"]:checked');
        
        if (selectedOption.length > 0) {
            this.config.answers[questionId] = selectedOption.val();
        } else {
            delete this.config.answers[questionId];
        }
        
        this.updateProgress();
        console.log('💾 Answer saved for question', questionId, ':', this.config.answers[questionId]);
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
        var $prevBtn = this.config.container.find('#vefify-prev-question');
        var $nextBtn = this.config.container.find('#vefify-next-question');
        var $finishBtn = this.config.container.find('#vefify-finish-quiz');
        
        // Previous button visibility
        if (this.config.currentQuestionIndex === 0) {
            $prevBtn.hide();
        } else {
            $prevBtn.show();
        }
        
        // Next/Finish button logic
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
     * 📊 UPDATE PROGRESS BAR
     */
    updateProgress: function() {
        var answeredCount = Object.keys(this.config.answers).length;
        var progressPercent = (answeredCount / this.config.questions.length) * 100;
        
        // Update progress bar width
        this.config.container.find('.vefify-progress-fill').css('width', progressPercent + '%');
        
        // Update progress text
        this.config.container.find('.vefify-progress-text .current').text(this.config.currentQuestionIndex + 1);
        this.config.container.find('.vefify-progress-text .total').text(this.config.questions.length);
    },
    
    /**
     * ⏱️ START TIMER
     */
    startTimer: function() {
        var self = this;
        
        // Show timer
        this.config.container.find('#vefify-timer').show();
        
        this.config.timerInterval = setInterval(function() {
            self.config.timeRemaining--;
            
            var minutes = Math.floor(self.config.timeRemaining / 60);
            var seconds = self.config.timeRemaining % 60;
            
            var timeDisplay = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            self.config.container.find('#vefify-time-remaining').text(timeDisplay);
            
            // Change color when time is running low
            if (self.config.timeRemaining <= 60) {
                self.config.container.find('.vefify-timer').addClass('vefify-timer-warning');
            }
            
            // Time's up!
            if (self.config.timeRemaining <= 0) {
                self.timeUp();
            }
        }, 1000);
    },
    
    /**
     * ⏰ TIME UP HANDLER
     */
    timeUp: function() {
        clearInterval(this.config.timerInterval);
        this.showMessage('⏰ Time is up! Submitting your quiz...', 'warning');
        
        // Auto-submit after 2 seconds
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
        var answeredCount = Object.keys(this.config.answers).length;
        if (answeredCount < this.config.questions.length) {
            if (!confirm('You have not answered all questions. Are you sure you want to finish?')) {
                return;
            }
        }
        
        this.showLoading('Calculating your score...');
        
        // Stop timer
        if (this.config.timerInterval) {
            clearInterval(this.config.timerInterval);
        }
        
        // Calculate score locally for immediate feedback
        var score = this.calculateScore();
        
        // Show results after calculation
        setTimeout(function() {
            self.hideLoading();
            self.showResults(score);
        }, 2000);
    },
    
    /**
     * 📊 CALCULATE SCORE
     */
    calculateScore: function() {
        var totalQuestions = this.config.questions.length;
        var correctAnswers = 0;
        
        // Check each question's answer
        for (var i = 0; i < this.config.questions.length; i++) {
            var question = this.config.questions[i];
            var userAnswer = this.config.answers[question.id];
            
            if (question.options && question.options.length > 0) {
                // Find correct answer
                for (var j = 0; j < question.options.length; j++) {
                    if (question.options[j].is_correct == 1 && question.options[j].option_value == userAnswer) {
                        correctAnswers++;
                        break;
                    }
                }
            }
        }
        
        var percentage = Math.round((correctAnswers / totalQuestions) * 100);
        var passed = correctAnswers >= this.config.passScore;
        
        return {
            correct: correctAnswers,
            total: totalQuestions,
            percentage: percentage,
            passed: passed
        };
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
        
        // Score display circle
        html += '<div class="vefify-score-display">';
        html += '<div class="vefify-score-circle ' + (results.passed ? 'passed' : 'failed') + '">';
        html += '<div class="vefify-score-number">' + results.correct + '/' + results.total + '</div>';
        html += '<div class="vefify-score-percentage">' + results.percentage + '%</div>';
        html += '</div>';
        html += '</div>';
        
        // Results details
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
        
        // Action buttons
        html += '<div class="vefify-results-actions">';
        html += '<button type="button" class="vefify-btn vefify-btn-primary" onclick="window.print()">🖨️ Print Results</button>';
        html += '<button type="button" class="vefify-btn vefify-btn-secondary" onclick="location.reload()">🔄 Take Another Quiz</button>';
        html += '</div>';
        
        html += '</div>';
        
        // Hide quiz section and show results
        this.config.container.find('#vefify-quiz-section').slideUp();
        this.config.container.find('#vefify-results-section').html(html).slideDown();
        
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
     * 📱 FORMAT PHONE NUMBER (Vietnamese)
     */
    formatPhoneNumber: function(input) {
        var value = input.value.replace(/\D/g, ''); // Remove non-digits
        
        // Limit to 11 digits maximum (Vietnamese format)
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
        var answer = this.config.answers[questionId];
        if (answer) {
            this.config.container.find('input[name="question_' + questionId + '"][value="' + answer + '"]').prop('checked', true);
        }
    },
    
    /**
     * ⏳ SHOW LOADING OVERLAY
     */
    showLoading: function(message) {
        var $overlay = this.config.container.find('.vefify-loading-overlay');
        if (message) {
            $overlay.find('p').text(message);
        }
        $overlay.fadeIn();
    },
    
    /**
     * ❌ HIDE LOADING OVERLAY
     */
    hideLoading: function() {
        this.config.container.find('.vefify-loading-overlay').fadeOut();
    },
    
    /**
     * 💬 SHOW MESSAGE
     */
    showMessage: function(message, type) {
        var $messageContainer = this.config.container.find('#vefify-messages');
        
        // Create message element
        var $message = jQuery('<div class="vefify-message vefify-message-' + type + '">' + message + '</div>');
        
        // Clear existing messages and add new one
        $messageContainer.empty().append($message).slideDown();
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $message.remove();
                if ($messageContainer.children().length === 0) {
                    $messageContainer.hide();
                }
            });
        }, 5000);
    }
};

// 🚀 AUTO-INITIALIZATION
jQuery(document).ready(function($) {
    console.log('🎯 VefifyQuiz JavaScript loaded and ready');
    
    // Check if we have a quiz container with active class (direct quiz start)
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

// 🔍 GLOBAL DEBUG FUNCTIONS (for development)
window.vefifyDebug = {
    getConfig: function() {
        return VefifyQuiz.config;
    },
    
    getAnswers: function() {
        return VefifyQuiz.config.answers;
    },
    
    skipToResults: function() {
        VefifyQuiz.showResults({
            correct: 3,
            total: 5,
            percentage: 60,
            passed: true
        });
    },
    
    finishQuiz: function() {
        VefifyQuiz.finishQuiz();
    },
    
    nextQuestion: function() {
        VefifyQuiz.nextQuestion();
    }
};