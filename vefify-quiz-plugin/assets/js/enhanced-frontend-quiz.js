/**
 * 🚀 ENHANCED VEFIFY QUIZ FRONTEND - COMPLETE QUESTION FLOW
 * File: assets/js/enhanced-frontend-quiz.js
 * 
 * Drop-in replacement for your existing frontend-quiz.js
 * Includes complete question flow, real-time validation, and progress tracking
 */

var VefifyQuiz = {
    
    // Enhanced Configuration
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
        questionTimeouts: {},
        autoSaveInterval: null,
        progressTracking: true
    },
    
    /**
     * 🎯 ENHANCED INITIALIZATION
     */
    init: function(containerId, options) {
        console.log('🚀 Initializing Enhanced VefifyQuiz...', options);
        
        this.containerId = containerId;
        this.container = jQuery('#' + containerId);
        
        if (this.container.length === 0) {
            console.error('❌ Quiz container not found:', containerId);
            return;
        }
        
        // Store enhanced configuration
        this.config.campaignId = options.campaignId;
        this.config.timeLimit = options.timeLimit || 0;
        this.config.passScore = options.passScore || 3;
        
        // Initialize components
        this.initializeComponents();
        this.bindEvents();
        this.setupAutoSave();
        
        console.log('✅ Enhanced VefifyQuiz initialized successfully');
    },
    
    /**
     * 🔧 INITIALIZE COMPONENTS
     */
    initializeComponents: function() {
        // Add loading states
        this.addLoadingOverlay();
        
        // Initialize progress tracking
        this.initProgressTracking();
        
        // Add keyboard navigation
        this.initKeyboardNavigation();
        
        // Add mobile optimizations
        this.initMobileOptimizations();
    },
    
    /**
     * 📱 ADD LOADING OVERLAY
     */
    addLoadingOverlay: function() {
        if (this.container.find('.vefify-loading-overlay').length === 0) {
            var overlay = `
                <div class="vefify-loading-overlay" style="display: none;">
                    <div class="vefify-loading-content">
                        <div class="vefify-spinner"></div>
                        <p class="vefify-loading-text">Loading...</p>
                    </div>
                </div>
            `;
            this.container.prepend(overlay);
        }
    },
    
    /**
     * 🔗 ENHANCED EVENT BINDING
     */
    bindEvents: function() {
        var self = this;
        
        // Registration form submission
        this.container.on('submit', '#vefify-registration-form', function(e) {
            e.preventDefault();
            self.handleRegistration(this);
        });
        
        // Enhanced quiz navigation
        this.container.on('click', '#vefify-next-question', function() {
            self.nextQuestion();
        });
        
        this.container.on('click', '#vefify-prev-question', function() {
            self.prevQuestion();
        });
        
        this.container.on('click', '#vefify-finish-quiz', function() {
            self.finishQuiz();
        });
        
        // Real-time answer tracking
        this.container.on('change', '.vefify-question-option input', function() {
            self.handleAnswerChange(this);
        });
        
        // Question navigation via number buttons
        this.container.on('click', '.question-nav-btn', function() {
            var questionIndex = parseInt($(this).data('question-index'));
            self.goToQuestion(questionIndex);
        });
        
        // Form field enhancements
        this.container.on('input', '#vefify_phone', function() {
            self.formatPhoneNumber(this);
        });
        
        this.container.on('input', '#vefify_pharmacy_code', function() {
            self.formatPharmacyCode(this);
        });
        
        // Prevent accidental page leave during quiz
        window.addEventListener('beforeunload', function(e) {
            if (self.config.sessionId && self.config.currentQuestionIndex >= 0) {
                e.preventDefault();
                e.returnValue = 'You have an active quiz. Are you sure you want to leave?';
                return 'You have an active quiz. Are you sure you want to leave?';
            }
        });
    },
    
    /**
     * 📝 ENHANCED REGISTRATION HANDLING
     */
    handleRegistration: function(form) {
        var self = this;
        var $form = jQuery(form);
        var $submitBtn = $form.find('button[type="submit"]');
        
        // Enhanced loading state
        $submitBtn.prop('disabled', true).html('🔄 Registering...');
        this.showLoading('Registering participant...');
        
        // Collect and validate form data
        var formData = this.collectFormData($form);
        var validation = this.validateRegistrationForm(formData);
        
        if (!validation.valid) {
            this.hideLoading();
            $submitBtn.prop('disabled', false).html('🚀 Start Quiz');
            this.showError(validation.message);
            this.focusFirstError($form);
            return;
        }
        
        // Submit registration with enhanced error handling
        jQuery.post(vefifyAjax.ajaxUrl, formData)
            .done(function(response) {
                if (response.success) {
                    self.config.participantId = response.data.participant_id;
                    self.config.sessionToken = response.data.session_token;
                    
                    self.showSuccess('✅ Registration successful! Starting quiz...');
                    
                    setTimeout(function() {
                        self.startQuiz();
                    }, 1500);
                } else {
                    self.handleRegistrationError(response.data, $submitBtn);
                }
            })
            .fail(function(xhr, status, error) {
                self.handleRegistrationError('Connection error. Please try again.', $submitBtn);
                console.error('Registration failed:', error);
            });
    },
    
    /**
     * 📊 COLLECT FORM DATA
     */
    collectFormData: function($form) {
        var formData = {
            action: 'vefify_register_participant',
            vefify_nonce: $form.find('[name="vefify_nonce"]').val(),
            campaign_id: $form.find('[name="campaign_id"]').val()
        };
        
        // Add all form fields
        $form.find('input, select, textarea').each(function() {
            var $field = jQuery(this);
            var name = $field.attr('name');
            if (name && name !== 'vefify_nonce') {
                formData[name] = $field.val();
            }
        });
        
        return formData;
    },
    
    /**
     * 🚀 ENHANCED START QUIZ
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
                self.config.sessionId = response.data.session_id;
                self.config.totalQuestions = response.data.total_questions;
                self.config.timeLimit = response.data.time_limit;
                self.config.timeRemaining = response.data.time_limit;
                self.config.passScore = response.data.pass_score;
                self.config.quizStartTime = Date.now();
                
                self.hideLoading();
                self.initializeQuizInterface();
                self.showQuizSection();
                self.displayQuestion(0);
                
                if (self.config.timeLimit > 0) {
                    self.startTimer();
                }
                
                self.showSuccess('🎯 Quiz started! Good luck!');
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
     * 🎮 INITIALIZE QUIZ INTERFACE
     */
    initializeQuizInterface: function() {
        this.createQuestionNavigation();
        this.updateProgress();
        this.addQuizKeyboardShortcuts();
    },
    
    /**
     * 🧭 CREATE QUESTION NAVIGATION
     */
    createQuestionNavigation: function() {
        var navigationHtml = '<div class="vefify-question-navigation">';
        navigationHtml += '<h4>Quick Navigation</h4>';
        navigationHtml += '<div class="question-nav-grid">';
        
        for (var i = 0; i < this.config.questions.length; i++) {
            var status = this.config.answers[this.config.questions[i].id] ? 'answered' : 'unanswered';
            navigationHtml += '<button type="button" class="question-nav-btn ' + status + '" data-question-index="' + i + '">' + (i + 1) + '</button>';
        }
        
        navigationHtml += '</div></div>';
        
        // Insert navigation panel
        if (this.container.find('.vefify-question-navigation').length === 0) {
            this.container.find('.vefify-quiz-section').append(navigationHtml);
        }
    },
    
    /**
     * 📝 ENHANCED QUESTION DISPLAY
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
        this.container.find('#vefify-question-container').html(html);
        
        // Add animations
        this.animateQuestionTransition();
        
        // Update interface
        this.updateNavigation();
        this.updateProgress();
        this.updateQuestionNavigation();
        
        // Focus management for accessibility
        this.container.find('#vefify-question-container').focus();
        
        // Auto-save current answer if exists
        this.loadSavedAnswer(question.id);
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
        
        // Question footer
        if (question.explanation && this.config.showExplanations) {
            html += '<div class="vefify-question-explanation" style="display: none;">';
            html += '<h4>Explanation:</h4>';
            html += '<p>' + question.explanation + '</p>';
            html += '</div>';
        }
        
        html += '</div>';
        
        return html;
    },
    
    /**
     * 🎯 BUILD OPTIONS HTML
     */
    buildOptionsHTML: function(question) {
        var html = '';
        var inputType = question.type === 'multiple_select' ? 'checkbox' : 'radio';
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
        this.submitAnswerToServer(questionId);
        this.updateQuestionNavigation();
        
        // Provide immediate feedback
        this.showAnswerFeedback($input);
    },
    
    /**
     * 💾 ENHANCED SAVE CURRENT ANSWER
     */
    saveCurrentAnswer: function() {
        var currentQuestion = this.config.questions[this.config.currentQuestionIndex];
        var questionId = currentQuestion.id;
        var questionType = currentQuestion.type;
        
        var selectedValues = [];
        var inputSelector = 'input[name="question_' + questionId + '"]:checked';
        
        this.container.find(inputSelector).each(function() {
            selectedValues.push(parseInt(jQuery(this).val()));
        });
        
        if (selectedValues.length > 0) {
            if (questionType === 'multiple_select') {
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
                console.log('Answer saved:', response.data);
            }
        })
        .fail(function() {
            console.warn('Failed to save answer for question', questionId);
        });
    },
    
    /**
     * ➡️ ENHANCED NEXT QUESTION
     */
    nextQuestion: function() {
        this.saveCurrentAnswer();
        
        if (this.config.currentQuestionIndex < this.config.questions.length - 1) {
            this.displayQuestion(this.config.currentQuestionIndex + 1);
            this.scrollToQuestion();
        }
    },
    
    /**
     * ⬅️ ENHANCED PREVIOUS QUESTION
     */
    prevQuestion: function() {
        this.saveCurrentAnswer();
        
        if (this.config.currentQuestionIndex > 0) {
            this.displayQuestion(this.config.currentQuestionIndex - 1);
            this.scrollToQuestion();
        }
    },
    
    /**
     * 🎯 GO TO SPECIFIC QUESTION
     */
    goToQuestion: function(index) {
        this.saveCurrentAnswer();
        this.displayQuestion(index);
        this.scrollToQuestion();
    },
    
    /**
     * 📊 ENHANCED UPDATE PROGRESS
     */
    updateProgress: function() {
        var answeredCount = Object.keys(this.config.answers).length;
        var progressPercent = (answeredCount / this.config.totalQuestions) * 100;
        
        // Update progress bar
        this.container.find('.vefify-progress-fill').css('width', progressPercent + '%');
        
        // Update progress text
        this.container.find('.vefify-progress-text .current').text(this.config.currentQuestionIndex + 1);
        this.container.find('.vefify-progress-text .total').text(this.config.totalQuestions);
        
        // Update completion status
        var completionText = answeredCount + ' of ' + this.config.totalQuestions + ' answered';
        this.container.find('.vefify-completion-status').text(completionText);
        
        // Update progress percentage display
        this.container.find('.vefify-progress-percentage').text(Math.round(progressPercent) + '%');
    },
    
    /**
     * 🧭 UPDATE QUESTION NAVIGATION
     */
    updateQuestionNavigation: function() {
        var self = this;
        
        this.container.find('.question-nav-btn').each(function(index) {
            var $btn = jQuery(this);
            var questionId = self.config.questions[index].id;
            var isAnswered = self.config.answers.hasOwnProperty(questionId);
            var isCurrent = index === self.config.currentQuestionIndex;
            
            $btn.removeClass('answered unanswered current');
            
            if (isCurrent) {
                $btn.addClass('current');
            } else if (isAnswered) {
                $btn.addClass('answered');
            } else {
                $btn.addClass('unanswered');
            }
        });
    },
    
    /**
     * ⏱️ ENHANCED TIMER
     */
    startTimer: function() {
        var self = this;
        
        this.config.timerInterval = setInterval(function() {
            self.config.timeRemaining--;
            
            var minutes = Math.floor(self.config.timeRemaining / 60);
            var seconds = self.config.timeRemaining % 60;
            
            var timeDisplay = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            self.container.find('#vefify-time-remaining').text(timeDisplay);
            
            // Add warning classes
            if (self.config.timeRemaining <= 60) {
                self.container.find('.vefify-timer').addClass('vefify-timer-critical');
            } else if (self.config.timeRemaining <= 300) {
                self.container.find('.vefify-timer').addClass('vefify-timer-warning');
            }
            
            // Flash warning
            if (self.config.timeRemaining <= 30) {
                self.container.find('.vefify-timer').addClass('flash');
            }
            
            if (self.config.timeRemaining <= 0) {
                self.timeUp();
            }
        }, 1000);
    },
    
    /**
     * 🏁 ENHANCED FINISH QUIZ
     */
    finishQuiz: function() {
        var self = this;
        
        // Save current answer
        this.saveCurrentAnswer();
        
        // Check completion
        var answeredCount = Object.keys(this.config.answers).length;
        var unansweredCount = this.config.totalQuestions - answeredCount;
        
        if (unansweredCount > 0) {
            var confirmMessage = 'You have ' + unansweredCount + ' unanswered questions. Are you sure you want to finish?';
            if (!confirm(confirmMessage)) {
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
     * 📊 ENHANCED RESULTS DISPLAY
     */
    showResults: function(results) {
        this.container.find('.vefify-quiz-section').fadeOut();
        
        var html = this.buildResultsHTML(results);
        this.container.find('.vefify-results-section').html(html).fadeIn();
        
        // Add celebration animation for passing
        if (results.passed) {
            this.addCelebrationAnimation();
        }
        
        this.scrollToResults();
        this.showSuccess('🎉 Quiz completed successfully!');
    },
    
    /**
     * 🏗️ BUILD RESULTS HTML
     */
    buildResultsHTML: function(results) {
        var html = '<div class="vefify-results-content">';
        
        // Results header
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
        
        // Detailed results
        html += '<div class="vefify-results-details">';
        html += this.buildDetailedResults(results);
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
        if (results.detailed_results && results.detailed_results.length > 0) {
            html += '<button type="button" class="vefify-btn vefify-btn-info" onclick="VefifyQuiz.showDetailedBreakdown()">📊 View Detailed Breakdown</button>';
        }
        html += '</div>';
        
        html += '</div>';
        
        return html;
    },
    
    /**
     * 📋 BUILD DETAILED RESULTS
     */
    buildDetailedResults: function(results) {
        var html = '<div class="result-stats-grid">';
        
        // Basic stats
        html += '<div class="result-stat">';
        html += '<span class="stat-label">Correct Answers</span>';
        html += '<span class="stat-value">' + results.correct + ' / ' + results.total + '</span>';
        html += '</div>';
        
        html += '<div class="result-stat">';
        html += '<span class="stat-label">Score</span>';
        html += '<span class="stat-value">' + results.percentage + '%</span>';
        html += '</div>';
        
        html += '<div class="result-stat">';
        html += '<span class="stat-label">Status</span>';
        html += '<span class="stat-value ' + (results.passed ? 'passed' : 'failed') + '">';
        html += results.passed ? '✅ Passed' : '❌ Not Passed';
        html += '</span>';
        html += '</div>';
        
        html += '<div class="result-stat">';
        html += '<span class="stat-label">Time Taken</span>';
        html += '<span class="stat-value">' + this.formatTime(results.time_taken) + '</span>';
        html += '</div>';
        
        html += '</div>';
        
        return html;
    },
    
    /**
     * 🎊 ADD CELEBRATION ANIMATION
     */
    addCelebrationAnimation: function() {
        // Simple confetti effect
        var colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#f9ca24', '#6c5ce7'];
        
        for (var i = 0; i < 20; i++) {
            setTimeout(function() {
                var confetti = jQuery('<div class="confetti"></div>');
                confetti.css({
                    'background-color': colors[Math.floor(Math.random() * colors.length)],
                    'left': Math.random() * 100 + '%',
                    'animation-delay': Math.random() * 3 + 's'
                });
                jQuery('body').append(confetti);
                
                setTimeout(function() {
                    confetti.remove();
                }, 3000);
            }, i * 100);
        }
    },
    
    /**
     * 📱 MOBILE OPTIMIZATIONS
     */
    initMobileOptimizations: function() {
        // Add mobile class
        if (window.innerWidth <= 768) {
            this.container.addClass('vefify-mobile');
        }
        
        // Handle orientation change
        window.addEventListener('orientationchange', function() {
            setTimeout(function() {
                window.scrollTo(0, 0);
            }, 100);
        });
    },
    
    /**
     * ⌨️ KEYBOARD NAVIGATION
     */
    initKeyboardNavigation: function() {
        var self = this;
        
        jQuery(document).on('keydown', function(e) {
            if (!self.config.sessionId) return;
            
            switch(e.key) {
                case 'ArrowLeft':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        self.prevQuestion();
                    }
                    break;
                case 'ArrowRight':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        self.nextQuestion();
                    }
                    break;
                case 'Enter':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        self.finishQuiz();
                    }
                    break;
            }
        });
    },
    
    /**
     * 💾 SETUP AUTO-SAVE
     */
    setupAutoSave: function() {
        var self = this;
        
        this.config.autoSaveInterval = setInterval(function() {
            if (self.config.sessionId && Object.keys(self.config.answers).length > 0) {
                self.autoSaveProgress();
            }
        }, 30000); // Auto-save every 30 seconds
    },
    
    /**
     * 🔄 AUTO-SAVE PROGRESS
     */
    autoSaveProgress: function() {
        jQuery.post(vefifyAjax.ajaxUrl, {
            action: 'vefify_get_quiz_progress',
            vefify_nonce: vefifyAjax.nonce,
            session_id: this.config.sessionId
        });
    },
    
    /**
     * 🕒 FORMAT TIME
     */
    formatTime: function(seconds) {
        var minutes = Math.floor(seconds / 60);
        var remainingSeconds = seconds % 60;
        
        if (minutes > 0) {
            return minutes + 'm ' + remainingSeconds + 's';
        } else {
            return remainingSeconds + 's';
        }
    },
    
    /**
     * 📊 SHOW DETAILED BREAKDOWN
     */
    showDetailedBreakdown: function() {
        // Implementation for detailed question-by-question breakdown
        alert('Detailed breakdown feature coming soon!');
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
    
    // ... (Keep all existing methods like showLoading, hideLoading, showError, etc.)
    
    /**
     * ⏳ SHOW LOADING
     */
    showLoading: function(message) {
        var $overlay = this.container.find('.vefify-loading-overlay');
        if (message) {
            $overlay.find('.vefify-loading-text').text(message);
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
     * 📜 SCROLL TO QUESTION
     */
    scrollToQuestion: function() {
        jQuery('html, body').animate({
            scrollTop: this.container.find('#vefify-question-container').offset().top - 20
        }, 300);
    },
    
    /**
     * 📜 SCROLL TO RESULTS
     */
    scrollToResults: function() {
        jQuery('html, body').animate({
            scrollTop: this.container.find('.vefify-results-section').offset().top - 20
        }, 300);
    }
};

// Initialize enhanced features when document ready
jQuery(document).ready(function($) {
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
    
    // Add CSS for enhanced features
    VefifyQuiz.addEnhancedStyles();
});

/**
 * 🎨 ADD ENHANCED STYLES
 */
VefifyQuiz.addEnhancedStyles = function() {
    var styles = `
        <style>
        .vefify-question-navigation {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .question-nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            gap: 8px;
            margin-top: 10px;
        }
        
        .question-nav-btn {
            width: 40px;
            height: 40px;
            border: 2px solid #ddd;
            border-radius: 50%;
            background: white;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s ease;
        }
        
        .question-nav-btn.answered {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .question-nav-btn.current {
            background: #007bff;
            border-color: #007bff;
            color: white;
        }
        
        .question-nav-btn.unanswered:hover {
            border-color: #007bff;
        }
        
        .vefify-timer-warning {
            color: #ff9800 !important;
        }
        
        .vefify-timer-critical {
            color: #f44336 !important;
        }
        
        .flash {
            animation: flash 1s infinite;
        }
        
        @keyframes flash {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.5; }
        }
        
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: #ff6b6b;
            animation: confetti-fall 3s linear forwards;
            z-index: 9999;
        }
        
        @keyframes confetti-fall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        .result-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .result-stat {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-label {
            display: block;
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-value {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-value.passed {
            color: #28a745;
        }
        
        .stat-value.failed {
            color: #dc3545;
        }
        </style>
    `;
    
    jQuery('head').append(styles);
};