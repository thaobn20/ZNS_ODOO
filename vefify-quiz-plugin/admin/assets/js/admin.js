/**
 * Enhanced Frontend JavaScript
 * File: frontend/assets/js/quiz.js
 */

class VefifyQuizApp {
    constructor() {
        this.apiBase = vefifyAjax.resturl;
        this.campaignId = this.getCampaignId();
        this.sessionId = null;
        this.userId = null;
        this.currentQuestion = 0;
        this.questions = [];
        this.userAnswers = {};
        this.startTime = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.updateProgress(0);
    }
    
    getCampaignId() {
        // Get from URL parameter or data attribute
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('campaign_id') || 
               document.querySelector('[data-campaign-id]')?.dataset.campaignId || 
               1; // Default campaign
    }
    
    bindEvents() {
        // Form submission
        document.getElementById('userForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmission();
        });
        
        // Navigation buttons
        document.getElementById('prevBtn').addEventListener('click', () => {
            this.previousQuestion();
        });
        
        document.getElementById('nextBtn').addEventListener('click', () => {
            if (this.currentQuestion === this.questions.length - 1) {
                this.submitQuiz();
            } else {
                this.nextQuestion();
            }
        });
        
        // Touch gestures (from original code)
        this.initTouchGestures();
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
                this.sessionId = quizResponse.data.session_id;
                this.userId = quizResponse.data.user_id;
                this.questions = quizResponse.data.questions;
                
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
        const response = await fetch(`${this.apiBase}quiz/check-participation`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                phone: phone,
                campaign_id: this.campaignId
            })
        });
        
        const data = await response.json();
        return data;
    }
    
    async startQuiz(userData) {
        const response = await fetch(`${this.apiBase}quiz/start`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                campaign_id: this.campaignId,
                user_data: userData
            })
        });
        
        const data = await response.json();
        return data;
    }
    
    async submitQuiz() {
        try {
            this.showLoading();
            
            const response = await fetch(`${this.apiBase}quiz/submit`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: this.sessionId,
                    answers: this.userAnswers
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showResults(result.data);
            } else {
                throw new Error(result.message || 'Failed to submit quiz');
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
            pharmacy_code: document.getElementById('pharmacyCode').value.trim(),
            email: '' // Can be added later
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
        
        // Validate phone number
        if (!phoneNumber || !/^[0-9]{10,11}$/.test(phoneNumber)) {
            this.showFieldError('phoneNumber', 'phoneError', 'Please enter a valid phone number');
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
        input.classList.add('error');
        error.textContent = message;
        error.style.display = 'block';
    }
    
    clearFieldError(inputId, errorId) {
        const input = document.getElementById(inputId);
        const error = document.getElementById(errorId);
        input.classList.remove('error');
        error.style.display = 'none';
    }
    
    showQuizInterface() {
        document.getElementById('registrationForm').style.display = 'none';
        document.getElementById('quizContainer').style.display = 'block';
        document.getElementById('quizContainer').classList.add('fade-in');
        
        this.currentQuestion = 0;
        this.startTime = Date.now();
        this.loadQuestion(0);
        this.updateProgress(20);
    }
    
    loadQuestion(index) {
        const question = this.questions[index];
        
        // Update question counter and title
        document.getElementById('questionCounter').textContent = 
            `Question ${index + 1} of ${this.questions.length}`;
        document.getElementById('questionTitle').textContent = question.question_text;
        
        // Load answer options
        const answersContainer = document.getElementById('answersContainer');
        answersContainer.innerHTML = '';
        
        question.options.forEach(option => {
            const answerDiv = document.createElement('div');
            answerDiv.className = 'answer-option';
            answerDiv.innerHTML = `
                <input type="checkbox" id="option_${option.id}" value="${option.id}">
                <div class="answer-text">${option.option_text}</div>
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
        
        this.updateNavigationButtons();
    }
    
    handleAnswerSelect(questionId, optionId, answerDiv) {
        const checkbox = answerDiv.querySelector('input[type="checkbox"]');
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
    }
    
    updateNavigationButtons() {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        
        prevBtn.disabled = this.currentQuestion === 0;
        
        if (this.currentQuestion === this.questions.length - 1) {
            nextBtn.textContent = 'Submit Quiz';
        } else {
            nextBtn.textContent = 'Next →';
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
        document.getElementById('quizContainer').style.display = 'none';
        document.getElementById('resultContainer').style.display = 'block';
        document.getElementById('resultContainer').classList.add('fade-in');
        
        this.updateProgress(100);
        
        // Update result display
        document.getElementById('resultScore').textContent = 
            `${resultData.score}/${resultData.total_questions}`;
        
        const percentage = resultData.percentage;
        let message = '';
        
        if (percentage >= 80) {
            message = 'Outstanding! You really know your health facts!';
        } else if (percentage >= 60) {
            message = 'Great job! You have good health knowledge.';
        } else {
            message = 'Good effort! Keep learning about health and wellness.';
        }
        
        document.getElementById('resultMessage').textContent = message;
        
        // Show gift if available
        if (resultData.gift && resultData.gift.has_gift) {
            const rewardCard = document.getElementById('rewardCard');
            rewardCard.style.display = 'block';
            document.getElementById('rewardCode').textContent = resultData.gift.gift_code;
            
            // Update reward title and description
            const rewardTitle = rewardCard.querySelector('.reward-title');
            rewardTitle.textContent = `🎁 ${resultData.gift.gift_name}`;
        }
    }
    
    showLoading() {
        document.getElementById('registrationForm').style.display = 'none';
        document.getElementById('quizContainer').style.display = 'none';
        document.getElementById('loadingState').style.display = 'block';
    }
    
    hideLoading() {
        document.getElementById('loadingState').style.display = 'none';
    }
    
    showError(message) {
        alert(message); // Can be enhanced with better error display
    }
    
    showPopup(popupId) {
        const popup = document.getElementById(popupId);
        popup.style.display = 'flex';
        setTimeout(() => popup.classList.add('show'), 10);
    }
    
    updateProgress(percentage) {
        document.getElementById('progressFill').style.width = percentage + '%';
    }
    
    initTouchGestures() {
        let touchStartX = 0;
        let touchEndX = 0;
        
        document.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        });
        
        document.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            this.handleSwipe();
        });
        
        this.handleSwipe = () => {
            const swipeThreshold = 50;
            const quizContainer = document.getElementById('quizContainer');
            
            if (quizContainer.style.display === 'block') {
                if (touchEndX < touchStartX - swipeThreshold) {
                    // Swipe left - next question
                    if (this.currentQuestion < this.questions.length - 1) {
                        this.nextQuestion();
                    }
                }
                
                if (touchEndX > touchStartX + swipeThreshold) {
                    // Swipe right - previous question
                    if (this.currentQuestion > 0) {
                        this.previousQuestion();
                    }
                }
            }
        };
    }
    
    restartQuiz() {
        // Reset all states
        this.currentQuestion = 0;
        this.userAnswers = {};
        this.sessionId = null;
        this.userId = null;
        this.questions = [];
        
        // Reset form
        document.getElementById('userForm').reset();
        
        // Show registration form
        document.getElementById('resultContainer').style.display = 'none';
        document.getElementById('registrationForm').style.display = 'block';
        document.getElementById('registrationForm').classList.add('fade-in');
        
        this.updateProgress(0);
    }
}

// Global functions for compatibility
function closePopup() {
    const popup = document.querySelector('.popup.show');
    if (popup) {
        popup.classList.remove('show');
        setTimeout(() => popup.style.display = 'none', 300);
    }
}

function restartQuiz() {
    if (window.vefifyQuizApp) {
        window.vefifyQuizApp.restartQuiz();
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.vefifyQuizApp = new VefifyQuizApp();
});