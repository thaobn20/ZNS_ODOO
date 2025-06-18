/**
 * Vefify Quiz Mobile JavaScript
 * File: assets/js/quiz-mobile.js
 * 
 * Handles all frontend quiz functionality with modern ES6+ features
 */

class VefifyQuiz {
    constructor() {
        this.currentQuestion = 0;
        this.totalQuestions = 0;
        this.questions = [];
        this.answers = {};
        this.sessionId = null;
        this.participantId = null;
        this.campaignId = null;
        this.timeRemaining = null;
        this.timerInterval = null;
        this.autoStart = false;
        
        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }
    
    init() {
        console.log('🚀 Vefify Quiz initializing...');
        
        // Find quiz wrapper
        this.wrapper = document.querySelector('.vefify-quiz-wrapper');
        if (!this.wrapper) {
            console.warn('Quiz wrapper not found');
            return;
        }
        
        // Get configuration
        this.campaignId = this.wrapper.dataset.campaignId;
        this.autoStart = this.wrapper.dataset.autoStart === 'true';
        
        // Initialize elements
        this.initElements();
        
        // Bind events
        this.bindEvents();
        
        // Auto-start if enabled
        if (this.autoStart) {
            this.showRegistrationForm();
        }
        
        console.log('✅ Vefify Quiz initialized', {
            campaignId: this.campaignId,
            autoStart: this.autoStart
        });
    }
    
    initElements() {
        // Form elements
        this.registrationForm = document.getElementById('registrationForm');
        this.userForm = document.getElementById('userForm');
        this.loadingState = document.getElementById('loadingState');
        this.quizContainer = document.getElementById('quizContainer');
        this.resultContainer = document.getElementById('resultContainer');
        
        // Progress elements
        this.progressFill = document.getElementById('progressFill');
        this.progressText = document.getElementById('progressText');
        
        // Question elements
        this.questionCounter = document.getElementById('questionCounter');
        this.questionTimer = document.getElementById('questionTimer');
        this.timeRemaining = document.getElementById('timeRemaining');
        this.questionTitle = document.getElementById('questionTitle');
        this.answersContainer = document.getElementById('answersContainer');
        
        // Navigation elements
        this.prevBtn = document.getElementById('prevBtn');
        this.nextBtn = document.getElementById('nextBtn');
        this.submitBtn = document.getElementById('submitBtn');
        
        // Result elements
        this.resultIcon = document.getElementById('resultIcon');
        this.resultTitle = document.getElementById('resultTitle');
        this.resultScore = document.getElementById('resultScore');
        this.resultPercentage = document.getElementById('resultPercentage');
        this.resultMessage = document.getElementById('resultMessage');
        this.rewardSection = document.getElementById('rewardSection');
        this.rewardName = document.getElementById('rewardName');
        this.rewardCode = document.getElementById('rewardCode');
        this.rewardDescription = document.getElementById('rewardDescription');
        
        // Popup elements
        this.notificationPopup = document.getElementById('notificationPopup');
        this.popupIcon = document.getElementById('popupIcon');
        this.popupMessage = document.getElementById('popupMessage');
    }
    
    bindEvents() {
        // Form submission
        if (this.userForm) {
            this.userForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
        
        // Navigation buttons
        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', () => this.previousQuestion());
        }
        
        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', () => this.nextQuestion());
        }
        
        if (this.submitBtn) {
            this.submitBtn.addEventListener('click', () => this.submitQuiz());
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => this.handleKeyPress(e));
        
        // Form validation
        this.bindFormValidation();
        
        // Auto-save answers
        this.bindAnswerSaving();
    }
    
    bindFormValidation() {
        const inputs = this.userForm?.querySelectorAll('input, select');
        inputs?.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
    }
    
    bindAnswerSaving() {
        // Will be bound dynamically when questions are loaded
    }
    
    handleKeyPress(e) {
        if (this.quizContainer?.style.display !== 'none') {
            switch (e.key) {
                case 'ArrowLeft':
                    if (!this.prevBtn?.disabled) this.previousQuestion();
                    break;
                case 'ArrowRight':
                    if (!this.nextBtn?.disabled) this.nextQuestion();
                    break;
                case 'Enter':
                    if (this.currentQuestion === this.totalQuestions - 1) {
                        this.submitQuiz();
                    } else {
                        this.nextQuestion();
                    }
                    break;
            }
        }
    }
    
    async handleFormSubmit(e) {
        e.preventDefault();
        
        // Validate form
        if (!this.validateForm()) {
            this.showNotification('❌', 'Please fill in all required fields correctly', 'error');
            return;
        }
        
        // Get form data
        const formData = new FormData(this.userForm);
        const userData = Object.fromEntries(formData);
        
        // Show loading
        this.showLoading('Checking your information...');
        
        try {
            // Check if already participated
            const checkResponse = await this.checkParticipation(userData.phone_number);
            
            if (!checkResponse.can_participate) {
                this.hideLoading();
                this.showNotification('⚠️', checkResponse.message, 'warning');
                return;
            }
            
            // Start quiz
            const startResponse = await this.startQuiz(userData);
            
            if (startResponse.success) {
                this.sessionId = startResponse.session_id;
                this.participantId = startResponse.participant_id;
                this.questions = startResponse.questions;
                this.totalQuestions = this.questions.length;
                
                this.hideLoading();
                this.showQuiz();
            } else {
                throw new Error(startResponse.message || 'Failed to start quiz');
            }
            
        } catch (error) {
            console.error('Form submission error:', error);
            this.hideLoading();
            this.showNotification('❌', 'Failed to start quiz. Please try again.', 'error');
        }
    }
    
    validateForm() {
        const requiredFields = this.userForm.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    validateField(field) {
        const value = field.value.trim();
        const fieldName = field.name;
        let isValid = true;
        let errorMessage = '';
        
        // Check if required field is empty
        if (field.required && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        }
        
        // Field-specific validation
        if (value && isValid) {
            switch (fieldName) {
                case 'full_name':
                    if (value.length < 2) {
                        isValid = false;
                        errorMessage = 'Name must be at least 2 characters';
                    }
                    break;
                    
                case 'phone_number':
                    const phonePattern = /^0[3-9]\d{8}$/;
                    if (!phonePattern.test(value.replace(/\s/g, ''))) {
                        isValid = false;
                        errorMessage = 'Please enter a valid Vietnamese phone number';
                    }
                    break;
                    
                case 'email':
                    if (value) {
                        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailPattern.test(value)) {
                            isValid = false;
                            errorMessage = 'Please enter a valid email address';
                        }
                    }
                    break;
            }
        }
        
        // Show/hide error
        const formGroup = field.closest('.form-group');
        const errorElement = formGroup?.querySelector('.error-message');
        
        if (isValid) {
            formGroup?.classList.remove('error');
        } else {
            formGroup?.classList.add('error');
            if (errorElement) {
                errorElement.textContent = errorMessage;
            }
        }
        
        return isValid;
    }
    
    clearFieldError(field) {
        const formGroup = field.closest('.form-group');
        formGroup?.classList.remove('error');
    }
    
    async checkParticipation(phoneNumber) {
        const response = await this.apiRequest('vefify_check_participation', {
            phone: phoneNumber,
            campaign_id: this.campaignId
        });
        
        return response;
    }
    
    async startQuiz(userData) {
        const response = await this.apiRequest('vefify_start_quiz', {
            campaign_id: this.campaignId,
            user_data: userData
        });
        
        return response;
    }
    
    async submitQuiz() {
        // Confirm submission
        if (!confirm(vefifyQuiz.strings.confirm_submit)) {
            return;
        }
        
        this.showLoading('Submitting your answers...');
        
        try {
            const response = await this.apiRequest('vefify_submit_quiz', {
                session_id: this.sessionId,
                answers: this.answers
            });
            
            if (response.success) {
                this.hideLoading();
                this.showResult(response);
            } else {
                throw new Error(response.message || 'Submission failed');
            }
            
        } catch (error) {
            console.error('Quiz submission error:', error);
            this.hideLoading();
            this.showNotification('❌', 'Failed to submit quiz. Please try again.', 'error');
        }
    }
    
    showRegistrationForm() {
        this.hideAllSections();
        this.registrationForm.style.display = 'block';
        this.updateProgress(0, 'Ready to Start');
    }
    
    showLoading(message = 'Loading...') {
        this.hideAllSections();
        this.loadingState.style.display = 'block';
        
        const loadingText = this.loadingState.querySelector('.loading-text p');
        if (loadingText) {
            loadingText.textContent = message;
        }
    }
    
    hideLoading() {
        this.loadingState.style.display = 'none';
    }
    
    showQuiz() {
        this.hideAllSections();
        this.quizContainer.style.display = 'block';
        
        // Initialize quiz state
        this.currentQuestion = 0;
        this.loadQuestion(0);
        
        // Start timer if configured
        this.startTimer();
        
        // Update progress
        this.updateProgress(10, 'Quiz Started');
    }
    
    showResult(resultData) {
        this.hideAllSections();
        this.resultContainer.style.display = 'block';
        
        // Stop timer
        this.stopTimer();
        
        // Update progress to 100%
        this.updateProgress(100, 'Quiz Completed');
        
        // Display results
        const score = resultData.score;
        const totalQuestions = resultData.total_questions;
        const percentage = resultData.percentage;
        
        this.resultScore.textContent = `${score}/${totalQuestions}`;
        this.resultPercentage.textContent = `${percentage}%`;
        
        // Update result icon and message based on performance
        if (percentage >= 80) {
            this.resultIcon.textContent = '🎉';
            this.resultTitle.textContent = 'Excellent!';
            this.resultMessage.textContent = 'Outstanding performance! You really know your stuff.';
        } else if (percentage >= 60) {
            this.resultIcon.textContent = '👏';
            this.resultTitle.textContent = 'Well Done!';
            this.resultMessage.textContent = 'Good job! You have a solid understanding.';
        } else {
            this.resultIcon.textContent = '💪';
            this.resultTitle.textContent = 'Keep Learning!';
            this.resultMessage.textContent = 'Thanks for participating! There\'s always room to grow.';
        }
        
        // Show gift if available
        if (resultData.gift && resultData.gift.has_gift) {
            this.showGift(resultData.gift);
        }
        
        // Animate score circle
        this.animateScoreCircle(percentage);
    }
    
    showGift(giftData) {
        this.rewardSection.style.display = 'block';
        this.rewardName.textContent = giftData.gift_name;
        this.rewardCode.textContent = giftData.gift_code;
        this.rewardDescription.textContent = giftData.gift_description || '';
        
        // Add copy-to-clipboard functionality
        this.rewardCode.addEventListener('click', () => {
            navigator.clipboard.writeText(giftData.gift_code).then(() => {
                this.showNotification('📋', 'Gift code copied to clipboard!', 'success');
            });
        });
    }
    
    hideAllSections() {
        const sections = [
            this.registrationForm,
            this.loadingState,
            this.quizContainer,
            this.resultContainer
        ];
        
        sections.forEach(section => {
            if (section) section.style.display = 'none';
        });
    }
    
    loadQuestion(questionIndex) {
        if (questionIndex >= this.questions.length) {
            return;
        }
        
        const question = this.questions[questionIndex];
        this.currentQuestion = questionIndex;
        
        // Update question counter
        this.questionCounter.textContent = `Question ${questionIndex + 1} of ${this.totalQuestions}`;
        
        // Update question title
        this.questionTitle.textContent = question.question_text;
        
        // Load answer options
        this.loadAnswerOptions(question);
        
        // Update navigation buttons
        this.updateNavigationButtons();
        
        // Update progress
        const progress = ((questionIndex + 1) / this.totalQuestions) * 90; // 90% max for questions
        this.updateProgress(progress, `Question ${questionIndex + 1} of ${this.totalQuestions}`);
    }
    
    loadAnswerOptions(question) {
        this.answersContainer.innerHTML = '';
        
        const isMultipleSelect = question.question_type === 'multiple_select';
        const inputType = isMultipleSelect ? 'checkbox' : 'radio';
        const inputName = `question_${question.id}`;
        
        question.options.forEach((option, index) => {
            const optionElement = document.createElement('div');
            optionElement.className = 'answer-option';
            optionElement.innerHTML = `
                <label class="answer-label">
                    <input type="${inputType}" 
                           name="${inputName}" 
                           value="${option.id}" 
                           class="answer-input"
                           ${this.isAnswerSelected(question.id, option.id) ? 'checked' : ''}>
                    <span class="answer-text">${option.option_text}</span>
                </label>
            `;
            
            // Add click handler
            optionElement.addEventListener('click', (e) => {
                if (e.target.tagName !== 'INPUT') {
                    const input = optionElement.querySelector('input');
                    if (inputType === 'radio') {
                        // Clear other selections for radio buttons
                        this.answersContainer.querySelectorAll('.answer-option').forEach(opt => {
                            opt.classList.remove('selected');
                        });
                        optionElement.classList.add('selected');
                        input.checked = true;
                    } else {
                        // Toggle for checkboxes
                        input.checked = !input.checked;
                        optionElement.classList.toggle('selected', input.checked);
                    }
                    
                    this.saveAnswer(question.id, this.getSelectedAnswers(question.id));
                }
            });
            
            // Add input change handler
            const input = optionElement.querySelector('input');
            input.addEventListener('change', () => {
                optionElement.classList.toggle('selected', input.checked);
                
                if (inputType === 'radio' && input.checked) {
                    // Remove selected class from other options
                    this.answersContainer.querySelectorAll('.answer-option').forEach(opt => {
                        if (opt !== optionElement) {
                            opt.classList.remove('selected');
                        }
                    });
                }
                
                this.saveAnswer(question.id, this.getSelectedAnswers(question.id));
            });
            
            // Set initial selected state
            if (this.isAnswerSelected(question.id, option.id)) {
                optionElement.classList.add('selected');
            }
            
            this.answersContainer.appendChild(optionElement);
        });
    }
    
    getSelectedAnswers(questionId) {
        const inputs = this.answersContainer.querySelectorAll(`input[name="question_${questionId}"]:checked`);
        return Array.from(inputs).map(input => parseInt(input.value));
    }
    
    isAnswerSelected(questionId, optionId) {
        const questionAnswers = this.answers[questionId];
        return questionAnswers && questionAnswers.includes(optionId);
    }
    
    saveAnswer(questionId, selectedAnswers) {
        this.answers[questionId] = selectedAnswers;
        
        // Auto-advance for single-select questions
        const question = this.questions.find(q => q.id === questionId);
        if (question && question.question_type === 'multiple_choice' && selectedAnswers.length > 0) {
            setTimeout(() => {
                if (this.currentQuestion < this.totalQuestions - 1) {
                    this.nextQuestion();
                }
            }, 500);
        }
    }
    
    updateNavigationButtons() {
        // Previous button
        this.prevBtn.disabled = this.currentQuestion === 0;
        
        // Next/Submit button
        if (this.currentQuestion === this.totalQuestions - 1) {
            this.nextBtn.style.display = 'none';
            this.submitBtn.style.display = 'inline-flex';
        } else {
            this.nextBtn.style.display = 'inline-flex';
            this.submitBtn.style.display = 'none';
        }
    }
    
    previousQuestion() {
        if (this.currentQuestion > 0) {
            this.loadQuestion(this.currentQuestion - 1);
        }
    }
    
    nextQuestion() {
        if (this.currentQuestion < this.totalQuestions - 1) {
            this.loadQuestion(this.currentQuestion + 1);
        }
    }
    
    updateProgress(percentage, text) {
        if (this.progressFill) {
            this.progressFill.style.width = `${percentage}%`;
        }
        
        if (this.progressText) {
            this.progressText.textContent = text;
        }
    }
    
    startTimer() {
        // Timer implementation would go here if needed
        // For now, we'll just show a placeholder
        if (this.questionTimer) {
            this.questionTimer.style.display = 'block';
        }
    }
    
    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
    }
    
    animateScoreCircle(percentage) {
        // Simple animation for the score circle
        const circle = document.querySelector('.score-circle');
        if (circle) {
            circle.style.animation = 'bounce 0.6s ease';
        }
    }
    
    showNotification(icon, message, type = 'info') {
        if (!this.notificationPopup) return;
        
        this.popupIcon.textContent = icon;
        this.popupMessage.textContent = message;
        this.notificationPopup.style.display = 'flex';
        
        // Auto-hide after 3 seconds for success/info messages
        if (type === 'success' || type === 'info') {
            setTimeout(() => {
                this.notificationPopup.style.display = 'none';
            }, 3000);
        }
    }
    
    async apiRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', vefifyQuiz.nonce);
        
        // Add data to form
        Object.keys(data).forEach(key => {
            if (typeof data[key] === 'object') {
                formData.append(key, JSON.stringify(data[key]));
            } else {
                formData.append(key, data[key]);
            }
        });
        
        try {
            const response = await fetch(vefifyQuiz.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (!result.success && result.data) {
                throw new Error(result.data);
            }
            
            return result.data || result;
            
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }
}

// Initialize quiz when script loads
let vefifyQuizInstance;

// Make sure we have the localized data
if (typeof vefifyQuiz !== 'undefined') {
    vefifyQuizInstance = new VefifyQuiz();
} else {
    console.warn('vefifyQuiz localized data not found. Quiz may not work properly.');
}

// Global functions for backward compatibility
window.vefifyStartQuiz = function(campaignId) {
    if (vefifyQuizInstance) {
        vefifyQuizInstance.campaignId = campaignId;
        vefifyQuizInstance.showRegistrationForm();
    }
};

window.vefifyRestartQuiz = function() {
    if (vefifyQuizInstance) {
        vefifyQuizInstance.showRegistrationForm();
    }
};

// Utility functions
const VefifyUtils = {
    formatTime: function(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    },
    
    formatPhone: function(phone) {
        // Remove all non-digits
        const cleaned = phone.replace(/\D/g, '');
        
        // Format as Vietnamese phone number
        if (cleaned.length === 10 && cleaned.startsWith('0')) {
            return cleaned.replace(/(\d{4})(\d{3})(\d{3})/, '$1 $2 $3');
        }
        
        return phone;
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
        }
    }
};

// Export for use in other scripts
window.VefifyQuiz = VefifyQuiz;
window.VefifyUtils = VefifyUtils;