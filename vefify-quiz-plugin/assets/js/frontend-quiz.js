/**
 * Enhanced Frontend Quiz JavaScript - Phase 1
 * File: assets/js/frontend-quiz.js
 * 
 * Features:
 * - Real-time form validation
 * - Phone uniqueness checking
 * - Dynamic district loading
 * - Loading states and animations
 * - Mobile-optimized interactions
 */

(function($) {
    'use strict';
    
    // Main Quiz Application Class
    class VefifyQuizApp {
        constructor() {
            this.campaignId = null;
            this.currentStep = 'registration';
            this.validationRules = {
                full_name: { required: true, minLength: 2 },
                phone_number: { required: true, pattern: /^(84|0)[3-9][0-9]{8}$/ },
                province: { required: true },
                pharmacist_code: { required: false, pattern: /^[A-Z0-9]{6,12}$/ },
                email: { required: false, pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/ }
            };
            this.phoneValidationTimeout = null;
            this.isValidating = false;
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.initializeForm();
            this.setupValidation();
        }
        
        bindEvents() {
            // Form submission
            $(document).on('submit', '#vefify-registration-form', (e) => {
                e.preventDefault();
                this.handleRegistrationSubmit();
            });
            
            // Real-time validation
            $(document).on('input', '.vefify-form-input', (e) => {
                this.handleInputChange($(e.target));
            });
            
            $(document).on('change', '.vefify-form-select', (e) => {
                this.handleSelectChange($(e.target));
            });
            
            // Phone number specific validation
            $(document).on('input', '#phone_number', (e) => {
                this.handlePhoneInput($(e.target));
            });
            
            $(document).on('blur', '#phone_number', (e) => {
                this.validatePhoneUniqueness($(e.target).val());
            });
            
            // Province change for district loading
            $(document).on('change', '#province', (e) => {
                this.loadDistricts($(e.target).val());
            });
            
            // Checkbox validation
            $(document).on('change', '.vefify-checkbox', (e) => {
                this.validateCheckbox($(e.target));
            });
            
            // Form field focus effects
            $(document).on('focus', '.vefify-form-input, .vefify-form-select', (e) => {
                this.handleFieldFocus($(e.target));
            });
            
            $(document).on('blur', '.vefify-form-input, .vefify-form-select', (e) => {
                this.handleFieldBlur($(e.target));
            });
        }
        
        initializeForm() {
            const container = $('#vefify-quiz-container');
            if (container.length) {
                this.campaignId = container.data('campaign-id');
                this.theme = container.data('theme') || 'default';
            }
            
            // Initialize form state
            this.clearAllValidation();
            
            // Auto-format pharmacist code to uppercase
            $('#pharmacist_code').on('input', function() {
                this.value = this.value.toUpperCase();
            });
        }
        
        setupValidation() {
            // Update validation rules based on form settings
            const form = $('#vefify-registration-form');
            
            form.find('[required]').each((index, element) => {
                const fieldName = $(element).attr('name');
                if (this.validationRules[fieldName]) {
                    this.validationRules[fieldName].required = true;
                }
            });
        }
        
        handleInputChange($input) {
            const fieldName = $input.attr('name');
            const value = $input.val().trim();
            
            // Clear previous validation state
            this.clearFieldValidation($input);
            
            // Real-time validation
            if (value.length > 0) {
                this.validateField($input, value);
            }
        }
        
        handleSelectChange($select) {
            const value = $select.val();
            
            this.clearFieldValidation($select);
            
            if (value) {
                this.markFieldValid($select, 'Selection made');
            }
        }
        
        handlePhoneInput($input) {
            const value = $input.val().trim();
            
            // Clear any existing timeout
            if (this.phoneValidationTimeout) {
                clearTimeout(this.phoneValidationTimeout);
            }
            
            // Format phone number as user types
            const formatted = this.formatPhoneNumber(value);
            if (formatted !== value) {
                $input.val(formatted);
            }
            
            // Set timeout for uniqueness check
            if (value.length >= 10) {
                this.phoneValidationTimeout = setTimeout(() => {
                    this.validatePhoneUniqueness(value);
                }, 1000);
            }
        }
        
        handleFieldFocus($field) {
            $field.closest('.vefify-form-group').addClass('focused');
        }
        
        handleFieldBlur($field) {
            $field.closest('.vefify-form-group').removeClass('focused');
            
            const value = $field.val().trim();
            if (value.length > 0) {
                this.validateField($field, value);
            }
        }
        
        validateField($field, value) {
            const fieldName = $field.attr('name');
            const rules = this.validationRules[fieldName];
            
            if (!rules) return true;
            
            // Required validation
            if (rules.required && !value) {
                this.markFieldInvalid($field, vefifyAjax.strings.requiredField);
                return false;
            }
            
            // Pattern validation
            if (rules.pattern && value && !rules.pattern.test(value)) {
                let message = 'Invalid format';
                
                if (fieldName === 'phone_number') {
                    message = vefifyAjax.strings.invalidPhone;
                } else if (fieldName === 'email') {
                    message = vefifyAjax.strings.invalidEmail;
                } else if (fieldName === 'pharmacist_code') {
                    message = 'Please enter 6-12 alphanumeric characters';
                }
                
                this.markFieldInvalid($field, message);
                return false;
            }
            
            // Minimum length validation
            if (rules.minLength && value.length < rules.minLength) {
                this.markFieldInvalid($field, `Minimum ${rules.minLength} characters required`);
                return false;
            }
            
            // If we get here, field is valid
            this.markFieldValid($field, '✓ Looks good!');
            return true;
        }
        
        validatePhoneUniqueness(phone) {
            if (!phone || phone.length < 10 || this.isValidating) {
                return;
            }
            
            this.isValidating = true;
            const $phoneField = $('#phone_number');
            const $status = $('#phone_validation_status');
            
            // Show checking status
            $status.removeClass('available unavailable')
                   .addClass('checking')
                   .text(vefifyAjax.strings.phoneValidating);
            
            $.ajax({
                url: vefifyAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vefify_check_phone_uniqueness',
                    phone: phone,
                    campaign_id: this.campaignId,
                    nonce: vefifyAjax.nonce
                },
                success: (response) => {
                    this.isValidating = false;
                    
                    if (response.success) {
                        this.markFieldValid($phoneField, vefifyAjax.strings.phoneAvailable);
                        $status.removeClass('checking unavailable')
                               .addClass('available')
                               .text('✓');
                    } else {
                        this.markFieldInvalid($phoneField, response.data || vefifyAjax.strings.phoneInUse);
                        $status.removeClass('checking available')
                               .addClass('unavailable')
                               .text('✗');
                    }
                },
                error: () => {
                    this.isValidating = false;
                    $status.removeClass('checking available unavailable');
                    console.error('Phone validation failed');
                }
            });
        }
        
        loadDistricts(provinceCode) {
            if (!provinceCode) {
                $('#district_group').hide();
                return;
            }
            
            const $districtSelect = $('#district');
            const $districtGroup = $('#district_group');
            
            // Show loading state
            $districtSelect.html('<option value="">Loading districts...</option>');
            $districtGroup.show();
            
            $.ajax({
                url: vefifyAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vefify_get_districts',
                    province_code: provinceCode,
                    nonce: vefifyAjax.nonce
                },
                success: (response) => {
                    if (response.success && response.data) {
                        let options = '<option value="">Select District</option>';
                        
                        $.each(response.data, (code, name) => {
                            options += `<option value="${code}">${name}</option>`;
                        });
                        
                        $districtSelect.html(options);
                    } else {
                        $districtSelect.html('<option value="">No districts available</option>');
                    }
                },
                error: () => {
                    $districtSelect.html('<option value="">Error loading districts</option>');
                }
            });
        }
        
        validateCheckbox($checkbox) {
            const $group = $checkbox.closest('.checkbox-group');
            const isChecked = $checkbox.is(':checked');
            const isRequired = $checkbox.attr('required');
            
            if (isRequired && !isChecked) {
                this.showFieldError($group, 'This field is required');
                return false;
            } else {
                this.clearFieldValidation($group);
                return true;
            }
        }
        
        handleRegistrationSubmit() {
            const $form = $('#vefify-registration-form');
            const $submitBtn = $('#vefify-registration-submit');
            
            // Validate all fields
            let isValid = true;
            const formData = {};
            
            $form.find('.vefify-form-input, .vefify-form-select').each((index, element) => {
                const $field = $(element);
                const fieldName = $field.attr('name');
                const value = $field.val().trim();
                
                formData[fieldName] = value;
                
                if (!this.validateField($field, value)) {
                    isValid = false;
                }
            });
            
            // Validate checkboxes
            $form.find('.vefify-checkbox[required]').each((index, element) => {
                if (!this.validateCheckbox($(element))) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                this.showFormError('Please correct the errors above');
                return;
            }
            
            // Check if phone validation is still in progress
            if (this.isValidating) {
                this.showFormError('Please wait for phone validation to complete');
                return;
            }
            
            // Show loading state
            this.showLoadingState($submitBtn);
            
            // Submit form data
            this.submitRegistration(formData);
        }
        
        submitRegistration(formData) {
            const $form = $('#vefify-registration-form');
            
            // Add form data
            const submitData = {
                action: 'vefify_submit_registration',
                campaign_id: this.campaignId,
                nonce: vefifyAjax.nonce,
                ...formData
            };
            
            $.ajax({
                url: vefifyAjax.ajaxUrl,
                type: 'POST',
                data: submitData,
                success: (response) => {
                    this.hideLoadingState();
                    
                    if (response.success) {
                        this.proceedToQuiz(response.data);
                    } else {
                        this.showFormError(response.data || 'Registration failed. Please try again.');
                    }
                },
                error: () => {
                    this.hideLoadingState();
                    this.showFormError('Network error. Please check your connection and try again.');
                }
            });
        }
        
        proceedToQuiz(registrationData) {
            // Hide registration step
            $('#vefify-registration-step').removeClass('active');
            
            // Show quiz step
            $('#vefify-quiz-step').addClass('active');
            
            // Load quiz content
            this.loadQuizContent(registrationData.session_id);
        }
        
        loadQuizContent(sessionId) {
            const $quizContent = $('#vefify-quiz-content');
            
            $quizContent.html('<div class="loading-quiz">Loading quiz questions...</div>');
            
            $.ajax({
                url: vefifyAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vefify_load_quiz',
                    session_id: sessionId,
                    campaign_id: this.campaignId,
                    nonce: vefifyAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $quizContent.html(response.data.quiz_html);
                        this.initializeQuizEvents();
                    } else {
                        $quizContent.html('<div class="quiz-error">Failed to load quiz. Please refresh and try again.</div>');
                    }
                },
                error: () => {
                    $quizContent.html('<div class="quiz-error">Network error. Please check your connection.</div>');
                }
            });
        }
        
        initializeQuizEvents() {
            // Initialize quiz-specific events here
            console.log('Quiz events initialized');
        }
        
        // Utility Methods
        formatPhoneNumber(phone) {
            // Remove all non-digits
            phone = phone.replace(/\D/g, '');
            
            // Handle different formats
            if (phone.startsWith('84')) {
                return phone.substring(0, 11);
            } else if (phone.startsWith('0')) {
                return phone.substring(0, 10);
            }
            
            return phone;
        }
        
        markFieldValid($field, message = '') {
            $field.removeClass('error').addClass('success');
            
            const $feedback = $field.closest('.vefify-form-group').find('.vefify-form-feedback');
            if (message) {
                $feedback.removeClass('error warning')
                        .addClass('success show')
                        .text(message);
            }
        }
        
        markFieldInvalid($field, message) {
            $field.removeClass('success').addClass('error');
            
            const $feedback = $field.closest('.vefify-form-group').find('.vefify-form-feedback');
            $feedback.removeClass('success warning')
                    .addClass('error show')
                    .text(message);
        }
        
        clearFieldValidation($field) {
            $field.removeClass('error success');
            
            const $feedback = $field.closest('.vefify-form-group').find('.vefify-form-feedback');
            $feedback.removeClass('show error success warning').text('');
        }
        
        clearAllValidation() {
            $('.vefify-form-input, .vefify-form-select').removeClass('error success');
            $('.vefify-form-feedback').removeClass('show error success warning').text('');
        }
        
        showFieldError($element, message) {
            const $feedback = $element.find('.vefify-form-feedback');
            if ($feedback.length === 0) {
                $element.append(`<div class="vefify-form-feedback error show">${message}</div>`);
            } else {
                $feedback.removeClass('success warning')
                        .addClass('error show')
                        .text(message);
            }
        }
        
        showFormError(message) {
            // Remove existing error
            $('.vefify-form-error').remove();
            
            // Add new error
            const errorHtml = `
                <div class="vefify-form-error">
                    <div class="error-icon">⚠️</div>
                    <div class="error-message">${message}</div>
                </div>
            `;
            
            $('#vefify-registration-form').prepend(errorHtml);
            
            // Scroll to error
            $('.vefify-form-error')[0].scrollIntoView({ behavior: 'smooth' });
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $('.vefify-form-error').fadeOut();
            }, 5000);
        }
        
        showLoadingState($button) {
            $button.addClass('loading').prop('disabled', true);
            this.showLoadingOverlay();
        }
        
        hideLoadingState() {
            $('#vefify-registration-submit').removeClass('loading').prop('disabled', false);
            this.hideLoadingOverlay();
        }
        
        showLoadingOverlay(message = 'Processing...') {
            const $overlay = $('#vefify-loading-overlay');
            $overlay.find('.vefify-loading-text').text(message);
            $overlay.addClass('show');
        }
        
        hideLoadingOverlay() {
            $('#vefify-loading-overlay').removeClass('show');
        }
    }
    
    // Additional Form Enhancement Class
    class FormEnhancements {
        constructor() {
            this.init();
        }
        
        init() {
            this.addFloatingLabels();
            this.addInputAnimations();
            this.improveAccessibility();
        }
        
        addFloatingLabels() {
            $('.vefify-form-input, .vefify-form-select').each(function() {
                const $input = $(this);
                const $group = $input.closest('.vefify-form-group');
                
                $input.on('focus blur', function() {
                    const value = $(this).val();
                    $group.toggleClass('has-value', value.length > 0);
                });
                
                // Check initial value
                if ($input.val().length > 0) {
                    $group.addClass('has-value');
                }
            });
        }
        
        addInputAnimations() {
            // Add ripple effect to buttons
            $('.vefify-btn').on('click', function(e) {
                const $button = $(this);
                const $ripple = $('<span class="ripple"></span>');
                
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                $ripple.css({
                    width: size,
                    height: size,
                    left: x,
                    top: y
                });
                
                $button.append($ripple);
                
                setTimeout(() => $ripple.remove(), 600);
            });
        }
        
        improveAccessibility() {
            // Add ARIA labels and descriptions
            $('.vefify-form-input, .vefify-form-select').each(function() {
                const $input = $(this);
                const $label = $input.closest('.vefify-form-group').find('.vefify-form-label');
                const $help = $input.closest('.vefify-form-group').find('.vefify-form-help');
                const $feedback = $input.closest('.vefify-form-group').find('.vefify-form-feedback');
                
                // Connect label to input
                if ($label.length && !$input.attr('aria-labelledby')) {
                    const labelId = 'label-' + Math.random().toString(36).substr(2, 9);
                    $label.attr('id', labelId);
                    $input.attr('aria-labelledby', labelId);
                }
                
                // Connect help text
                if ($help.length) {
                    const helpId = 'help-' + Math.random().toString(36).substr(2, 9);
                    $help.attr('id', helpId);
                    $input.attr('aria-describedby', helpId);
                }
                
                // Connect feedback
                if ($feedback.length) {
                    const feedbackId = 'feedback-' + Math.random().toString(36).substr(2, 9);
                    $feedback.attr('id', feedbackId);
                    $input.attr('aria-describedby', ($input.attr('aria-describedby') || '') + ' ' + feedbackId);
                }
            });
            
            // Announce validation errors to screen readers
            $(document).on('DOMNodeInserted', '.vefify-form-feedback.error.show', function() {
                $(this).attr('role', 'alert');
            });
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only initialize if quiz container exists
        if ($('#vefify-quiz-container').length) {
            window.vefifyQuizApp = new VefifyQuizApp();
            window.vefifyFormEnhancements = new FormEnhancements();
        }
    });
    
    // CSS for additional elements
    const additionalCSS = `
        <style>
        .vefify-form-error {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            color: #991b1b;
            animation: slideInDown 0.3s ease-out;
        }
        
        .error-icon {
            font-size: 18px;
        }
        
        .error-message {
            flex: 1;
            font-weight: 500;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        }
        
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        .vefify-form-group.has-value .vefify-form-label {
            color: #3b82f6;
        }
        
        .loading-quiz {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        
        .quiz-error {
            text-align: center;
            padding: 40px 20px;
            color: #ef4444;
            background: #fef2f2;
            border-radius: 8px;
            border: 1px solid #fecaca;
        }
        </style>
    `;
    
    // Add additional CSS to head
    $('head').append(additionalCSS);
    
})(jQuery);