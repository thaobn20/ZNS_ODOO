/**
 * Vefify Question Frontend JavaScript
 * File: modules/questions/assets/question-frontend.js
 */

(function($) {
    'use strict';
    
    // Question Manager Class
    class VefifyQuestionManager {
        constructor() {
            this.questions = [];
            this.currentIndex = 0;
            this.answers = {};
            this.startTime = null;
            this.timeLimit = null;
            this.timerInterval = null;
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.initializeSingleQuestions();
        }
        
        bindEvents() {
            // Single question submit handlers
            $(document).on('click', '.vefify-submit-answer', this.handleSingleQuestionSubmit.bind(this));
            
            // Quiz navigation
            $(document).on('click', '.vefify-next-question', this.nextQuestion.bind(this));
            $(document).on('click', '.vefify-prev-question', this.prevQuestion.bind(this));
            $(document).on('click', '.vefify-finish-quiz', this.finishQuiz.bind(this));
            
            // Answer selection
            $(document).on('change', '.vefify-question input[type="radio"], .vefify-question input[type="checkbox"]', 
                this.handleAnswerSelection.bind(this));
                
            // Quiz restart
            $(document).on('click', '.vefify-restart-quiz', this.restartQuiz.bind(this));
        }
        
        initializeSingleQuestions() {
            $('.vefify-single-question').each((index, element) => {
                this.setupSingleQuestion($(element));
            });
        }
        
        setupSingleQuestion($question) {
            const questionId = $question.data('question-id');
            
            // Add visual feedback for option selection
            $question.find('input[type="radio"], input[type="checkbox"]').on('change', function() {
                const $parent = $(this).closest('.question-options');
                $parent.find('.option-item').removeClass('selected');
                
                if ($(this).is(':checked')) {
                    $(this).closest('.option-item').addClass('selected');
                }
            });
        }
        
        handleSingleQuestionSubmit(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $question = $button.closest('.vefify-single-question');
            const questionId = $question.data('question-id');
            
            // Get selected answers
            const selectedAnswers = [];
            $question.find('input:checked').each(function() {
                selectedAnswers.push(parseInt($(this).val()));
            });
            
            if (selectedAnswers.length === 0) {
                this.showNotification('Please select an answer before submitting.', 'warning');
                return;
            }
            
            // Disable form while processing
            this.setQuestionState($question, 'loading');
            
            // Validate answer
            const answers = {};
            answers[questionId] = selectedAnswers;
            
            this.validateAnswers(answers).then(result => {
                this.displaySingleQuestionResult($question, result.results[questionId]);
            }).catch(error => {
                this.showNotification('Error validating answer: ' + error.message, 'error');
                this.setQuestionState($question, 'active');
            });
        }
        
        displaySingleQuestionResult($question, result) {
            const $options = $question.find('.option-item');
            
            // Show correct/incorrect for each option
            $options.each(function() {
                const $option = $(this);
                const $input = $option.find('input');
                const optionId = parseInt($input.val());
                
                // Disable inputs
                $input.prop('disabled', true);
                
                // Add result classes
                if (result.correct_answers.includes(optionId)) {
                    $option.addClass('correct-answer');
                }
                
                if (result.user_answers.includes(optionId)) {
                    if (result.correct_answers.includes(optionId)) {
                        $option.addClass('user-correct');
                    } else {
                        $option.addClass('user-incorrect');
                    }
                }
            });
            
            // Show result summary
            const resultHtml = `
                <div class="question-result">
                    <div class="result-status ${result.is_correct ? 'correct' : 'incorrect'}">
                        <span class="result-icon">${result.is_correct ? '✓' : '✗'}</span>
                        <span class="result-text">${result.is_correct ? 'Correct!' : 'Incorrect'}</span>
                        <span class="result-points">${result.points_earned}/${result.max_points} points</span>
                    </div>
                    ${result.explanation ? `<div class="result-explanation">${result.explanation}</div>` : ''}
                </div>
            `;
            
            $question.find('.question-actions').html(resultHtml);
            $question.addClass('answered');
        }
        
        loadQuizQuestions(campaignId, options = {}) {
            const params = {
                action: 'vefify_get_quiz_questions',
                campaign_id: campaignId,
                count: options.count || 5,
                difficulty: options.difficulty || '',
                category: options.category || '',
                nonce: vefifyQuestions.nonce
            };
            
            return $.post(vefifyQuestions.ajaxUrl, params).then(response => {
                if (response.success) {
                    this.questions = response.data.questions;
                    this.currentIndex = 0;
                    this.answers = {};
                    this.startTime = new Date();
                    
                    return response.data;
                } else {
                    throw new Error(response.data || 'Failed to load questions');
                }
            });
        }
        
        renderQuiz($container, options = {}) {
            if (this.questions.length === 0) {
                $container.html('<div class="vefify-error">No questions available</div>');
                return;
            }
            
            const quizHtml = `
                <div class="vefify-quiz-container">
                    <div class="quiz-header">
                        <div class="quiz-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 0%"></div>
                            </div>
                            <div class="progress-text">
                                <span class="current-question">1</span> of <span class="total-questions">${this.questions.length}</span>
                            </div>
                        </div>
                        ${options.timeLimit ? `<div class="quiz-timer" id="quiz-timer">Time: <span id="time-remaining">${options.timeLimit}</span></div>` : ''}
                    </div>
                    
                    <div class="quiz-content">
                        <div class="question-container" id="question-container">
                            <!-- Questions will be rendered here -->
                        </div>
                        
                        <div class="quiz-navigation">
                            <button type="button" class="vefify-prev-question" disabled>← Previous</button>
                            <button type="button" class="vefify-next-question">Next →</button>
                            <button type="button" class="vefify-finish-quiz" style="display: none;">Finish Quiz</button>
                        </div>
                    </div>
                    
                    <div class="quiz-results" id="quiz-results" style="display: none;">
                        <!-- Results will be shown here -->
                    </div>
                </div>
            `;
            
            $container.html(quizHtml);
            
            // Start timer if needed
            if (options.timeLimit) {
                this.startTimer(options.timeLimit);
            }
            
            // Render first question
            this.renderCurrentQuestion();
        }
        
        renderCurrentQuestion() {
            const question = this.questions[this.currentIndex];
            const $container = $('#question-container');
            
            const questionHtml = `
                <div class="vefify-question" data-question-id="${question.id}">
                    <div class="question-header">
                        <h3 class="question-text">${question.question_text}</h3>
                        <div class="question-meta">
                            <span class="category">${this.capitalizeFirst(question.category || 'General')}</span>
                            <span class="difficulty difficulty-${question.difficulty}">${this.capitalizeFirst(question.difficulty)}</span>
                            <span class="points">${question.points} point${question.points !== 1 ? 's' : ''}</span>
                        </div>
                    </div>
                    
                    <div class="question-options">
                        ${this.renderQuestionOptions(question)}
                    </div>
                </div>
            `;
            
            $container.html(questionHtml);
            
            // Update progress
            this.updateProgress();
            
            // Update navigation
            this.updateNavigation();
            
            // Restore previous answers if any
            this.restoreAnswers(question.id);
        }
        
        renderQuestionOptions(question) {
            const inputType = question.question_type === 'multiple_select' ? 'checkbox' : 'radio';
            const inputName = `question_${question.id}`;
            
            return question.options.map((option, index) => `
                <div class="option-item">
                    <label>
                        <input type="${inputType}" name="${inputName}" value="${option.id}">
                        <span class="option-marker">${String.fromCharCode(65 + index)}.</span>
                        <span class="option-text">${option.text}</span>
                    </label>
                </div>
            `).join('');
        }
        
        handleAnswerSelection(e) {
            const $input = $(e.target);
            const questionId = $input.closest('.vefify-question').data('question-id');
            
            // Update answers object
            if (!this.answers[questionId]) {
                this.answers[questionId] = [];
            }
            
            if ($input.is(':radio')) {
                // Radio button - single selection
                this.answers[questionId] = [$input.val()];
            } else {
                // Checkbox - multiple selection
                if ($input.is(':checked')) {
                    if (!this.answers[questionId].includes($input.val())) {
                        this.answers[questionId].push($input.val());
                    }
                } else {
                    this.answers[questionId] = this.answers[questionId].filter(val => val !== $input.val());
                }
            }
            
            // Visual feedback
            const $parent = $input.closest('.question-options');
            $parent.find('.option-item').removeClass('selected');
            $parent.find('input:checked').each(function() {
                $(this).closest('.option-item').addClass('selected');
            });
        }
        
        nextQuestion() {
            if (this.currentIndex < this.questions.length - 1) {
                this.currentIndex++;
                this.renderCurrentQuestion();
            } else {
                this.showFinishButton();
            }
        }
        
        prevQuestion() {
            if (this.currentIndex > 0) {
                this.currentIndex--;
                this.renderCurrentQuestion();
            }
        }
        
        showFinishButton() {
            $('.vefify-next-question').hide();
            $('.vefify-finish-quiz').show();
        }
        
        finishQuiz() {
            // Check if all questions are answered
            const unansweredQuestions = this.questions.filter(q => !this.answers[q.id] || this.answers[q.id].length === 0);
            
            if (unansweredQuestions.length > 0) {
                if (!confirm(`You have ${unansweredQuestions.length} unanswered questions. Do you want to finish anyway?`)) {
                    return;
                }
            }
            
            // Show loading state
            this.setQuizState('loading');
            
            // Validate all answers
            this.validateAnswers(this.answers).then(result => {
                this.displayQuizResults(result);
            }).catch(error => {
                this.showNotification('Error submitting quiz: ' + error.message, 'error');
                this.setQuizState('active');
            });
        }
        
        validateAnswers(answers) {
            const params = {
                action: 'vefify_validate_quiz_answers',
                answers: answers,
                session_id: this.generateSessionId(),
                nonce: vefifyQuestions.nonce
            };
            
            return $.post(vefifyQuestions.ajaxUrl, params).then(response => {
                if (response.success) {
                    return response.data;
                } else {
                    throw new Error(response.data || 'Validation failed');
                }
            });
        }
        
        displayQuizResults(result) {
            const summary = result.summary;
            const endTime = new Date();
            const completionTime = Math.floor((endTime - this.startTime) / 1000);
            
            const resultsHtml = `
                <div class="quiz-results-content">
                    <div class="results-header">
                        <div class="results-icon ${this.getGradeClass(summary.percentage)}">
                            ${this.getGradeIcon(summary.percentage)}
                        </div>
                        <h2>Quiz Complete!</h2>
                        <div class="results-score">
                            <span class="score-number">${summary.correct_answers}</span>
                            <span class="score-total">/ ${summary.total_questions}</span>
                        </div>
                        <div class="results-percentage">${summary.percentage}%</div>
                    </div>
                    
                    <div class="results-stats">
                        <div class="stat-item">
                            <div class="stat-label">Score</div>
                            <div class="stat-value">${summary.total_score} / ${summary.max_possible_score}</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Time</div>
                            <div class="stat-value">${this.formatTime(completionTime)}</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Grade</div>
                            <div class="stat-value grade-${this.getGradeClass(summary.percentage)}">${this.calculateGrade(summary.percentage)}</div>
                        </div>
                    </div>
                    
                    <div class="results-breakdown">
                        <h3>Question Breakdown</h3>
                        ${this.renderResultsBreakdown(result.results)}
                    </div>
                    
                    <div class="results-actions">
                        <button type="button" class="vefify-restart-quiz">Take Quiz Again</button>
                    </div>
                </div>
            `;
            
            $('.quiz-content').hide();
            $('#quiz-results').html(resultsHtml).show();
            
            // Stop timer
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
            }
        }
        
        renderResultsBreakdown(results) {
            return Object.values(results).map(result => `
                <div class="result-item ${result.is_correct ? 'correct' : 'incorrect'}">
                    <div class="result-question">
                        <span class="result-icon">${result.is_correct ? '✓' : '✗'}</span>
                        <span class="question-text">${result.question_text}</span>
                        <span class="points">${result.points_earned}/${result.max_points}</span>
                    </div>
                    ${result.explanation ? `<div class="result-explanation">${result.explanation}</div>` : ''}
                </div>
            `).join('');
        }
        
        restartQuiz() {
            this.currentIndex = 0;
            this.answers = {};
            this.startTime = new Date();
            
            $('.quiz-content').show();
            $('#quiz-results').hide();
            
            // Reset navigation
            $('.vefify-next-question').show();
            $('.vefify-finish-quiz').hide();
            
            this.renderCurrentQuestion();
        }
        
        updateProgress() {
            const progress = ((this.currentIndex + 1) / this.questions.length) * 100;
            $('.progress-fill').css('width', progress + '%');
            $('.current-question').text(this.currentIndex + 1);
            $('.total-questions').text(this.questions.length);
        }
        
        updateNavigation() {
            $('.vefify-prev-question').prop('disabled', this.currentIndex === 0);
            
            if (this.currentIndex === this.questions.length - 1) {
                $('.vefify-next-question').hide();
                $('.vefify-finish-quiz').show();
            } else {
                $('.vefify-next-question').show();
                $('.vefify-finish-quiz').hide();
            }
        }
        
        restoreAnswers(questionId) {
            if (this.answers[questionId]) {
                this.answers[questionId].forEach(answerId => {
                    $(`.vefify-question input[value="${answerId}"]`).prop('checked', true).trigger('change');
                });
            }
        }
        
        startTimer(seconds) {
            this.timeLimit = seconds;
            let remainingTime = seconds;
            
            this.timerInterval = setInterval(() => {
                remainingTime--;
                $('#time-remaining').text(this.formatTime(remainingTime));
                
                if (remainingTime <= 0) {
                    clearInterval(this.timerInterval);
                    this.finishQuiz();
                } else if (remainingTime <= 60) {
                    $('#quiz-timer').addClass('warning');
                }
            }, 1000);
        }
        
        setQuestionState($question, state) {
            $question.removeClass('loading active answered');
            $question.addClass(state);
            
            if (state === 'loading') {
                $question.find('input').prop('disabled', true);
                $question.find('.vefify-submit-answer').prop('disabled', true).text('Processing...');
            } else if (state === 'active') {
                $question.find('input').prop('disabled', false);
                $question.find('.vefify-submit-answer').prop('disabled', false).text('Submit Answer');
            }
        }
        
        setQuizState(state) {
            $('.vefify-quiz-container').removeClass('loading active completed');
            $('.vefify-quiz-container').addClass(state);
            
            if (state === 'loading') {
                $('.quiz-navigation button').prop('disabled', true);
            } else if (state === 'active') {
                $('.quiz-navigation button').prop('disabled', false);
                this.updateNavigation();
            }
        }
        
        showNotification(message, type = 'info') {
            const notification = $(`
                <div class="vefify-notification ${type}">
                    <span class="notification-message">${message}</span>
                    <button type="button" class="notification-close">×</button>
                </div>
            `);
            
            $('body').append(notification);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 5000);
            
            // Close button
            notification.find('.notification-close').on('click', () => {
                notification.fadeOut(() => notification.remove());
            });
        }
        
        generateSessionId() {
            return 'vq_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        
        formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }
        
        capitalizeFirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
        
        calculateGrade(percentage) {
            if (percentage >= 90) return 'A';
            if (percentage >= 80) return 'B';
            if (percentage >= 70) return 'C';
            if (percentage >= 60) return 'D';
            return 'F';
        }
        
        getGradeClass(percentage) {
            if (percentage >= 80) return 'excellent';
            if (percentage >= 60) return 'good';
            if (percentage >= 40) return 'fair';
            return 'poor';
        }
        
        getGradeIcon(percentage) {
            if (percentage >= 80) return '🏆';
            if (percentage >= 60) return '👍';
            if (percentage >= 40) return '👌';
            return '📝';
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Create global instance
        window.VefifyQuestions = new VefifyQuestionManager();
        
        // Auto-initialize quiz containers
        $('.vefify-quiz-auto').each(function() {
            const $container = $(this);
            const campaignId = $container.data('campaign-id');
            const options = $container.data('options') || {};
            
            if (campaignId) {
                window.VefifyQuestions.loadQuizQuestions(campaignId, options).then(() => {
                    window.VefifyQuestions.renderQuiz($container, options);
                }).catch(error => {
                    $container.html(`<div class="vefify-error">Failed to load quiz: ${error.message}</div>`);
                });
            }
        });
    });
    
})(jQuery);