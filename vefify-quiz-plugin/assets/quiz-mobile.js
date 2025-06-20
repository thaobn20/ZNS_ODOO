/**
 * Vefify Quiz Mobile JavaScript
 * File: Create as assets/quiz-mobile.js
 */

class VefifyMobileQuiz {
    constructor() {
        this.campaignId = null;
        this.sessionId = null;
        this.userId = null;
        this.currentQuestion = 0;
        this.questions = [];
        this.userAnswers = {};
        this.startTime = null;
        this.touchStartX = 0;
        this.touchEndX = 0;
        
        this.init();
    }
    
    init() {
        // Get campaign ID from wrapper
        const wrapper = document.querySelector('.vefify-quiz-wrapper');
        if (wrapper) {
            this.campaignId = wrapper.dataset.campaignId;
        }
        
        if (!this.campaignId) {
            console.error('Campaign ID not found');
            return;
        }
        
        this.bindEvents();
        this.updateProgress(0);
        this.initTouchGestures();
        
        console.log('Vefify Mobile Quiz initialized for campaign:', this.campaignId);
    }
    
    bindEvents() {
        // Form submission
        const userForm = document.getElementById('userForm');
        if (userForm) {
            userForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmission();
            });
        }
        
        // Navigation buttons
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.previousQuestion());
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (this.currentQuestion === this.questions.length - 1) {
                    this.submitQuiz();
                } else {
                    this.nextQuestion();
                }
            });
        }
    }
    
    async handleFormSubmission() {
        if (!this.validateForm()) return;
        
        const userData = this.collectUserData();
        
        try {
            this.showLoading();
            
            // Check participation first
            const participationCheck = await this.checkParticipation(userData.phone_number);
            
            if (participationCheck.participated) {
                this.hideLoading();
                this.showPopup('alreadyParticipatedPopup');
                return;
            }
            
            // Start quiz
            const quizResponse = await this.startQuiz(userData);
            
            if (quizResponse.success) {
                this.sessionId = quizResponse.session_id;
                this.userId = quizResponse.user_id;
                this.questions = quizResponse.questions;
                this.startTime = Date.now();
                
                this.hideLoading();
                this.showQuizInterface();
            } else {
                throw new Error(quizResponse.message || 'Failed to start quiz');
            }
            
        } catch (error) {
            this.hideLoading();
            this.showError('An error occurred: ' + error.message);
        }
    }
    
    async checkParticipation(phone) {
        const response = await fetch(vefifyQuiz.restUrl + 'check-participation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': vefifyQuiz.nonce
            },
            body: JSON.stringify({
                phone: phone,
                campaign_id: this.campaignId
            })
        });
        
        if (!response.ok) {
            throw new Error('Network error checking participation');
        }
        
        return await response.json();
    }
    
    async startQuiz(userData) {
        const response = await fetch(vefifyQuiz.restUrl + 'start-quiz', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': vefifyQuiz.nonce
            },
            body: JSON.stringify({
                campaign_id: this.campaignId,
                user_data: userData
            })
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || 'Failed to start quiz');
        }
        
        return await response.json();
    }
    
    async submitQuiz() {
        try {
            this.showLoading();
            
            const response = await fetch(vefifyQuiz.restUrl + 'submit-quiz', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': vefifyQuiz.nonce
                },
                body: JSON.stringify({
                    session_id: this.sessionId,
                    answers: this.userAnswers
                })
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to submit quiz');
            }
            
            const result = await response.json();
            
            if (result.success) {
                this.showResults(result);
            } else {
                throw new Error(result.message || 'Failed to process quiz results');
            }
            
        } catch (error) {
            this.hideLoading();
            this.showError('Failed to submit quiz: ' + error.message);
        }
    }
    
    collectUserData() {
        return {
            full_name: document.getElementById('fullName').value.trim(),
            phone_number: document.getElementById('phoneNumber').value.trim(),
            province: document.getElementById('province').value,
            pharmacy_code: document.getElementById('pharmacyCode').value.trim()
        };
    }
    
    validateForm() {
        const fullName = document.getElementById('fullName').value.trim();
        const phoneNumber = document.getElementById('phoneNumber').value.trim();
        const province = document.getElementById('province').value;
        
        let isValid = true;
        
        // Validate full name
        if (!fullName) {
            this.showFieldError('fullName', 'nameError', 'Please enter your full name');
            isValid = false;
        } else {
            this.clearFieldError('fullName', 'nameError');
        }
        
        // Validate phone number (Vietnamese format)
        const phoneRegex = /^(84|0)[3-9][0-9]{8}$/;
        const cleanPhone = phoneNumber.replace(/\D/g, '');
        if (!cleanPhone || !phoneRegex.test(cleanPhone)) {
            this.showFieldError('phoneNumber', 'phoneError', 'Please enter a valid Vietnamese phone number');
            isValid = false;
        } else {
            this.clearFieldError('phoneNumber', 'phoneError');
        }
        
        // Validate province
        if (!province) {
            this.showFieldError('province', 'provinceError', 'Please select your province/city');
            isValid = false;
        } else {
            this.clearFieldError('province', 'provinceError');
        }
        
        return isValid;
    }
    
    showFieldError(inputId, errorId, message) {
        const input = document.getElementById(inputId);
        const error = document.getElementById(errorId);
        
        if (input && error) {
            input.classList.add('error');
            error.textContent = message;
            error.classList.add('show');
        }
    }
    
    clearFieldError(inputId, errorId) {
        const input = document.getElementById(inputId);
        const error = document.getElementById(errorId);
        
        if (input && error) {
            input.classList.remove('error');
            error.classList.remove('show');
        }
    }
    
    showQuizInterface() {
        const registrationForm = document.getElementById('registrationForm');
        const quizContainer = document.getElementById('quizContainer');
        
        if (registrationForm) registrationForm.style.display = 'none';
        if (quizContainer) {
            quizContainer.style.display = 'block';
            quizContainer.classList.add('fade-in');
        }
        
        this.currentQuestion = 0;
        this.loadQuestion(0);
        this.updateProgress(20);
    }
    
    loadQuestion(index) {
        const question = this.questions[index];
        if (!question) return;
        
        // Update question counter and title
        const questionCounter = document.getElementById('questionCounter');
        const questionTitle = document.getElementById('questionTitle');
        
        if (questionCounter) {
            questionCounter.textContent = `Question ${index + 1} of ${this.questions.length}`;
        }
        
        if (questionTitle) {
            questionTitle.textContent = question.question_text;
        }
        
        // Load answer options
        this.loadAnswerOptions(question);
        
        // Update navigation buttons
        this.updateNavigationButtons();
    }
    
    loadAnswerOptions(question) {
        const answersContainer = document.getElementById('answersContainer');
        if (!answersContainer) return;
        
        answersContainer.innerHTML = '';
        
        if (!question.options || !Array.isArray(question.options)) {
            console.error('Invalid question options:', question);
            return;
        }
        
        question.options.forEach(option => {
            const answerDiv = document.createElement('div');
            answerDiv.className = 'answer-option';
            answerDiv.innerHTML = `
                <input type="checkbox" id="option_${option.id}" value="${option.id}">
                <div class="answer-text">${this.escapeHtml(option.text)}</div>
            `;
            
            answerDiv.addEventListener('click', () => {
                this.handleAnswerSelect(question.id, option.id, answerDiv);
            });
            
            answersContainer.appendChild(answerDiv);
        });
        
        // Restore previous answers
        if (this.userAnswers[question.id]) {
            this.userAnswers[question.id].forEach(answerId => {
                const checkbox = document.getElementById(`option_${answerId}`);
                const answerDiv = checkbox?.closest('.answer-option');
                if (checkbox && answerDiv) {
                    checkbox.checked = true;
                    answerDiv.classList.add('selected');
                }
            });
        }
    }
    
    handleAnswerSelect(questionId, optionId, answerDiv) {
        const checkbox = answerDiv.querySelector('input[type="checkbox"]');
        const question = this.questions[this.currentQuestion];
        
        // For multiple choice, clear other selections
        if (question.question_type === 'multiple_choice') {
            // Clear all other selections in this question
            const allOptions = answerDiv.parentNode.querySelectorAll('.answer-option');
            allOptions.forEach(opt => {
                opt.classList.remove('selected');
                opt.querySelector('input').checked = false;
            });
            this.userAnswers[questionId] = [];
        }
        
        // Toggle current selection
        checkbox.checked = !checkbox.checked;
        answerDiv.classList.toggle('selected', checkbox.checked);
        
        // Store answer
        if (!this.userAnswers[questionId]) {
            this.userAnswers[questionId] = [];
        }
        
        if (checkbox.checked) {
            if (!this.userAnswers[questionId].includes(optionId)) {
                this.userAnswers[questionId].push(optionId);
            }
        } else {
            this.userAnswers[questionId] = this.userAnswers[questionId].filter(id => id !== optionId);
        }
        
        console.log('Answer updated:', this.userAnswers);
    }
    
    updateNavigationButtons() {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        
        if (prevBtn) {
            prevBtn.disabled = this.currentQuestion === 0;
        }
        
        if (nextBtn) {
            if (this.currentQuestion === this.questions.length - 1) {
                nextBtn.textContent = 'Submit Quiz';
            } else {
                nextBtn.textContent = 'Next →';
            }
        }
    }
    
    previousQuestion() {
        if (this.currentQuestion > 0) {
            this.currentQuestion--;
            this.loadQuestion(this.currentQuestion);
            this.updateProgress(20 + (this.currentQuestion / this.questions.length) * 60);
        }
    }
    
    nextQuestion() {
        if (this.currentQuestion < this.questions.length - 1) {
            this.currentQuestion++;
            this.loadQuestion(this.currentQuestion);
            this.updateProgress(20 + (this.currentQuestion / this.questions.length) * 60);
        }
    }
    
    showResults(resultData) {
        this.hideLoading();
        
        const quizContainer = document.getElementById('quizContainer');
        const resultContainer = document.getElementById('resultContainer');
        
        if (quizContainer) quizContainer.style.display = 'none';
        if (resultContainer) {
            resultContainer.style.display = 'block';
            resultContainer.classList.add('fade-in');
        }
        
        this.updateProgress(100);
        
        // Update result display
        const resultScore = document.getElementById('resultScore');
        const resultMessage = document.getElementById('resultMessage');
        
        if (resultScore) {
            resultScore.textContent = `${resultData.score}/${resultData.total_questions}`;
        }
        
        const percentage = resultData.percentage;
        let message = '';
        
        if (percentage >= 80) {
            message = 'Outstanding! You really know your health facts!';
        } else if (percentage >= 60) {
            message = 'Great job! You have good health knowledge.';
        } else {
            message = 'Good effort! Keep learning about health and wellness.';
        }
        
        if (resultMessage) {
            resultMessage.textContent = message;
        }
        
        // Show gift if available
        if (resultData.gift && resultData.gift.has_gift) {
            this.showGiftCard(resultData.gift);
        }
        
        // Add success animation
        if (resultContainer) {
            resultContainer.classList.add('success-bounce');
        }
    }
    
    showGiftCard(giftData) {
        const rewardCard = document.getElementById('rewardCard');
        const rewardCode = document.getElementById('rewardCode');
        const rewardDescription = document.getElementById('rewardDescription');
        
        if (rewardCard) {
            rewardCard.style.display = 'block';
            
            if (rewardCode) {
                rewardCode.textContent = giftData.gift_code;
            }
            
            if (rewardDescription) {
                rewardDescription.textContent = giftData.gift_description || giftData.gift_value;
            }
            
            // Update title with gift name
            const rewardTitle = rewardCard.querySelector('.reward-title');
            if (rewardTitle) {
                rewardTitle.textContent = `🎁 ${giftData.gift_name}`;
            }
        }
    }
    
    showLoading() {
        const loadingState = document.getElementById('loadingState');
        const registrationForm = document.getElementById('registrationForm');
        const quizContainer = document.getElementById('quizContainer');
        
        if (registrationForm) registrationForm.style.display = 'none';
        if (quizContainer) quizContainer.style.display = 'none';
        if (loadingState) loadingState.classList.add('show');
    }
    
    hideLoading() {
        const loadingState = document.getElementById('loadingState');
        if (loadingState) loadingState.classList.remove('show');
    }
    
    showError(message) {
        const errorPopup = document.getElementById('errorPopup');
        const errorMessage = document.getElementById('errorMessage');
        
        if (errorMessage) {
            errorMessage.textContent = message;
        }
        
        if (errorPopup) {
            this.showPopup('errorPopup');
        } else {
            // Fallback to alert if popup not available
            alert(message);
        }
    }
    
    showPopup(popupId) {
        const popup = document.getElementById(popupId);
        if (popup) {
            popup.style.display = 'flex';
            setTimeout(() => popup.classList.add('show'), 10);
        }
    }
    
    updateProgress(percentage) {
        const progressFill = document.getElementById('progressFill');
        if (progressFill) {
            progressFill.style.width = percentage + '%';
        }
    }
    
    initTouchGestures() {
        document.addEventListener('touchstart', (e) => {
            this.touchStartX = e.changedTouches[0].screenX;
        });
        
        document.addEventListener('touchend', (e) => {
            this.touchEndX = e.changedTouches[0].screenX;
            this.handleSwipe();
        });
    }
    
    handleSwipe() {
        const swipeThreshold = 50;
        const quizContainer = document.getElementById('quizContainer');
        
        if (quizContainer && quizContainer.style.display === 'block') {
            if (this.touchEndX < this.touchStartX - swipeThreshold) {
                // Swipe left - next question
                if (this.currentQuestion < this.questions.length - 1) {
                    this.nextQuestion();
                }
            }
            
            if (this.touchEndX > this.touchStartX + swipeThreshold) {
                // Swipe right - previous question
                if (this.currentQuestion > 0) {
                    this.previousQuestion();
                }
            }
        }
    }
    
    restartQuiz() {
        // Reset all states
        this.currentQuestion = 0;
        this.userAnswers = {};
        this.sessionId = null;
        this.userId = null;
        this.questions = [];
        this.startTime = null;
        
        // Reset form
        const userForm = document.getElementById('userForm');
        if (userForm) userForm.reset();
        
        // Clear any error states
        const errorInputs = document.querySelectorAll('.form-input.error');
        errorInputs.forEach(input => input.classList.remove('error'));
        
        const errorMessages = document.querySelectorAll('.error-message.show');
        errorMessages.forEach(msg => msg.classList.remove('show'));
        
        // Show registration form
        const resultContainer = document.getElementById('resultContainer');
        const registrationForm = document.getElementById('registrationForm');
        
        if (resultContainer) resultContainer.style.display = 'none';
        if (registrationForm) {
            registrationForm.style.display = 'block';
            registrationForm.classList.add('fade-in');
        }
        
        this.updateProgress(0);
    }
    
    // Utility function to escape HTML
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Global functions for compatibility with existing code
function closePopup() {
    const popup = document.querySelector('.popup.show');
    if (popup) {
        popup.classList.remove('show');
        setTimeout(() => popup.style.display = 'none', 300);
    }
}

function restartQuiz() {
    if (window.vefifyMobileQuiz) {
        window.vefifyMobileQuiz.restartQuiz();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if we're on a page with the quiz wrapper
    if (document.querySelector('.vefify-quiz-wrapper')) {
        window.vefifyMobileQuiz = new VefifyMobileQuiz();
        console.log('Vefify Mobile Quiz ready');
    }
});

// Handle WordPress AJAX errors gracefully
window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
    
    if (window.vefifyMobileQuiz) {
        window.vefifyMobileQuiz.hideLoading();
        window.vefifyMobileQuiz.showError('An unexpected error occurred. Please try again.');
    }
});

// Export for potential external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VefifyMobileQuiz;
}