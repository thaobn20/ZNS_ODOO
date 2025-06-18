/**
 * Vefify Quiz - Enhanced Form Validation
 * File: frontend/assets/js/form-validation.js
 * Mobile-optimized with fast loading
 */

class VefifyFormValidator {
    constructor(formElement) {
        this.form = formElement;
        this.campaignId = this.form.querySelector('input[name="campaign_id"]').value;
        this.submitBtn = this.form.querySelector('.btn-start-quiz');
        this.isValidating = false;
        this.validationCache = new Map(); // Cache validation results for speed
        
        this.validationRules = {
            full_name: { required: true, minLength: 2, maxLength: 100 },
            phone_number: { required: true, pattern: /^(84|0)[3-9][0-9]{8}$/, unique: true },
            province: { required: true },
            pharmacist_code: { required: false, pattern: /^[A-Z0-9]{6,12}$/ },
            email: { required: false, pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/ },
            terms_agreed: { required: true }
        };
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.preloadValidation(); // For faster mobile experience
    }
    
    setupEventListeners() {
        // Real-time validation with debouncing for mobile performance
        this.form.querySelectorAll('.form-input, .form-select').forEach(field => {
            const fieldName = field.name;
            
            // Immediate feedback on blur (mobile-friendly)
            field.addEventListener('blur', () => {
                this.validateField(fieldName, field.value);
            });
            
            // Debounced input validation (300ms delay for mobile)
            let timeout;
            field.addEventListener('input', () => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    this.validateField(fieldName, field.value);
                }, 300);
            });
        });
        
        // Phone number formatting on input
        const phoneInput = this.form.querySelector('#phone_number');
        phoneInput.addEventListener('input', (e) => {
            this.formatPhoneInput(e.target);
        });
        
        // Pharmacist code formatting (uppercase)
        const pharmacistInput = this.form.querySelector('#pharmacist_code');
        if (pharmacistInput) {
            pharmacistInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.toUpperCase();
            });
        }
        
        // Terms checkbox
        const termsCheckbox = this.form.querySelector('#terms_agreed');
        termsCheckbox.addEventListener('change', () => {
            this.validateField('terms_agreed', termsCheckbox.checked);
        });
        
        // Form submission
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmission();
        });
    }
    
    async validateField(fieldName, value) {
        const rules = this.validationRules[fieldName];
        if (!rules) return true;
        
        // Create cache key for performance
        const cacheKey = `${fieldName}_${value}`;
        if (this.validationCache.has(cacheKey)) {
            const cachedResult = this.validationCache.get(cacheKey);
            this.showFieldFeedback(fieldName, cachedResult.isValid, cachedResult.message);
            return cachedResult.isValid;
        }
        
        let isValid = true;
        let message = '';
        
        // Required field validation
        if (rules.required && (!value || value.trim() === '')) {
            isValid = false;
            message = `${this.getFieldLabel(fieldName)} is required`;
        }
        // Length validation
        else if (value && rules.minLength && value.length < rules.minLength) {
            isValid = false;
            message = `${this.getFieldLabel(fieldName)} must be at least ${rules.minLength} characters`;
        }
        else if (value && rules.maxLength && value.length > rules.maxLength) {
            isValid = false;
            message = `${this.getFieldLabel(fieldName)} must not exceed ${rules.maxLength} characters`;
        }
        // Pattern validation
        else if (value && rules.pattern && !rules.pattern.test(value)) {
            isValid = false;
            message = this.getPatternErrorMessage(fieldName);
        }
        // Unique validation (AJAX)
        else if (isValid && value && rules.unique && fieldName === 'phone_number') {
            const uniqueResult = await this.validatePhoneUniqueness(value);
            isValid = uniqueResult.isValid;
            message = uniqueResult.message;
        }
        // Pharmacist code validation (AJAX)
        else if (isValid && value && fieldName === 'pharmacist_code') {
            const codeResult = await this.validatePharmacistCode(value);
            isValid = codeResult.isValid;
            message = codeResult.message;
        }
        else if (isValid && value) {
            message = '✅ Valid';
        }
        
        // Cache result for performance
        this.validationCache.set(cacheKey, { isValid, message });
        
        this.showFieldFeedback(fieldName, isValid, message);
        this.updateSubmitButton();
        
        return isValid;
    }
    
    async validatePhoneUniqueness(phone) {
        if (this.isValidating) return { isValid: false, message: 'Please wait...' };
        
        this.isValidating = true;
        this.showFieldFeedback('phone_number', null, '🔄 Checking availability...');
        
        try {
            const formData = new FormData();
            formData.append('action', 'vefify_check_phone_uniqueness');
            formData.append('phone', phone);
            formData.append('campaign_id', this.campaignId);
            formData.append('nonce', vefifyAjax.nonce);
            
            const response = await fetch(vefifyAjax.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                return { isValid: true, message: '✅ Phone number available' };
            } else {
                return { isValid: false, message: result.data.message };
            }
        } catch (error) {
            console.error('Phone validation error:', error);
            return { isValid: false, message: 'Validation error. Please try again.' };
        } finally {
            this.isValidating = false;
        }
    }
    
    async validatePharmacistCode(code) {
        if (!code) return { isValid: true, message: 'Optional field' };
        
        try {
            const formData = new FormData();
            formData.append('action', 'vefify_validate_pharmacist_code');
            formData.append('pharmacist_code', code);
            formData.append('nonce', vefifyAjax.nonce);
            
            const response = await fetch(vefifyAjax.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                return { isValid: true, message: '✅ Valid format' };
            } else {
                return { isValid: false, message: result.data.message };
            }
        } catch (error) {
            return { isValid: false, message: 'Validation error' };
        }
    }
    
    formatPhoneInput(input) {
        let value = input.value.replace(/\D/g, ''); // Remove non-digits
        
        // Auto-format Vietnamese phone numbers for better UX
        if (value.length > 0) {
            if (value.startsWith('84') && value.length > 2) {
                value = '0' + value.substring(2);
            }
            
            // Format display (add spaces for readability)
            if (value.length > 6) {
                value = value.substring(0, 4) + ' ' + value.substring(4, 7) + ' ' + value.substring(7, 10);
            } else if (value.length > 3) {
                value = value.substring(0, 4) + ' ' + value.substring(4);
            }
        }
        
        input.value = value;
    }
    
    showFieldFeedback(fieldName, isValid, message) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        const feedback = this.form.querySelector(`#${fieldName}_feedback`);
        
        if (!field || !feedback) return;
        
        // Remove existing classes
        field.classList.remove('valid', 'invalid', 'validating');
        feedback.classList.remove('success', 'error', 'info');
        
        if (isValid === null) {
            // Validating state
            field.classList.add('validating');
            feedback.classList.add('info');
        } else if (isValid) {
            // Valid state
            field.classList.add('valid');
            feedback.classList.add('success');
        } else {
            // Invalid state
            field.classList.add('invalid');
            feedback.classList.add('error');
        }
        
        feedback.textContent = message;
    }
    
    updateSubmitButton() {
        const requiredFields = ['full_name', 'phone_number', 'province', 'terms_agreed'];
        let allValid = true;
        
        for (const fieldName of requiredFields) {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (!field || !field.classList.contains('valid')) {
                allValid = false;
                break;
            }
        }
        
        this.submitBtn.disabled = !allValid || this.isValidating;
        
        if (allValid && !this.isValidating) {
            this.submitBtn.classList.add('ready');
        } else {
            this.submitBtn.classList.remove('ready');
        }
    }
    
    async handleFormSubmission() {
        // Final validation
        const formData = new FormData(this.form);
        let allValid = true;
        
        for (const [fieldName] of formData.entries()) {
            if (this.validationRules[fieldName]) {
                const isValid = await this.validateField(fieldName, formData.get(fieldName));
                if (!isValid) allValid = false;
            }
        }
        
        if (!allValid) {
            this.showFormError('Please fix the errors above');
            return;
        }
        
        // Show loading state
        this.submitBtn.querySelector('.btn-text').style.display = 'none';
        this.submitBtn.querySelector('.btn-loader').style.display = 'inline';
        this.submitBtn.disabled = true;
        
        // Start quiz (connect to existing quiz logic)
        this.startQuiz(formData);
    }
    
    startQuiz(formData) {
        // Hide registration screen
        const regScreen = this.form.closest('.registration-screen');
        const quizContent = document.querySelector('.quiz-content');
        const progressBar = document.querySelector('.quiz-progress');
        
        regScreen.classList.remove('active');
        quizContent.style.display = 'block';
        progressBar.style.display = 'block';
        
        // Initialize quiz with participant data
        if (typeof VefifyQuiz !== 'undefined') {
            VefifyQuiz.setParticipantData(Object.fromEntries(formData));
            VefifyQuiz.startQuiz();
        }
    }
    
    showFormError(message) {
        // Create or update form error message
        let errorDiv = this.form.querySelector('.form-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'form-error';
            this.form.insertBefore(errorDiv, this.form.querySelector('.form-actions'));
        }
        
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
    }
    
    getFieldLabel(fieldName) {
        const labelMap = {
            full_name: 'Full name',
            phone_number: 'Phone number',
            province: 'Province',
            pharmacist_code: 'Pharmacist code',
            email: 'Email',
            terms_agreed: 'Terms agreement'
        };
        return labelMap[fieldName] || fieldName;
    }
    
    getPatternErrorMessage(fieldName) {
        const messages = {
            phone_number: 'Please enter a valid Vietnamese phone number (10 digits)',
            pharmacist_code: 'Pharmacist code must be 6-12 alphanumeric characters',
            email: 'Please enter a valid email address'
        };
        return messages[fieldName] || 'Invalid format';
    }
    
    preloadValidation() {
        // Preload common validation for faster mobile experience
        // This can be extended based on usage patterns
    }
}

// Initialize form validation when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const registrationForm = document.getElementById('vefify-registration-form');
    if (registrationForm) {
        new VefifyFormValidator(registrationForm);
    }
});

// Utility function for gift code copying
function copyGiftCode() {
    const codeElement = document.querySelector('.code-value');
    if (codeElement) {
        const code = codeElement.textContent;
        
        // Modern clipboard API with fallback
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(code).then(() => {
                showCopyFeedback('Gift code copied!');
            });
        } else {
            // Fallback for older browsers/non-HTTPS
            const textArea = document.createElement('textarea');
            textArea.value = code;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showCopyFeedback('Gift code copied!');
        }
    }
}

function showCopyFeedback(message) {
    const button = document.querySelector('.copy-code-btn');
    const originalText = button.textContent;
    
    button.textContent = message;
    button.style.background = '#4CAF50';
    
    setTimeout(() => {
        button.textContent = originalText;
        button.style.background = '';
    }, 2000);
}