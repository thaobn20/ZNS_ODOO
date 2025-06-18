/**
 * Frontend Quiz JavaScript
 * File: assets/js/frontend-quiz.js
 * 
 * Handles all frontend quiz interactions:
 * - Form validation and submission
 * - Province/district selection
 * - Phone number validation
 * - Quiz navigation and submission
 * - Gift code functionality
 */

(function($) {
    'use strict';
    
    // Quiz App Object
    const VefifyQuiz = {
        
        // Properties
        currentQuestion: 0,
        totalQuestions: 0,
        answers: {},
        phoneCheckTimeout: null,
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.initProvinceSelection();
            this.initQuizNavigation();
            this.initFormValidation();
        },
        
        // Bind all events
        bindEvents: function() {
            // Registration form
            $(document).on('submit', '#vefify-registration', this.handleRegistration.bind(this));
            $(document).on('input', '#participant_phone', this.handlePhoneInput.bind(this));
            $(document).on('change', '#province_city', this.handleProvinceChange.bind(this));
            
            // Quiz form
            $(document).on('submit', '#vefify-quiz-form', this.handleQuizSubmission.bind(this));
            $(document).on('click', '.next-question', this.nextQuestion.bind(this));
            $(document).on('click', '.prev-question', this.prevQuestion.bind(this));
            $(document).on('change', '.option-input', this.handleAnswerSelection.bind(this));
            
            // Gift actions
            $(document).on('click', '.claim-gift-btn', this.claimGift.bind(this));
            $(document).on('click', '.share-results-btn', this.shareResults.bind(this));
        },
        
        // Initialize province/district selection
        initProvinceSelection: function() {
            const $provinceSelect = $('#province_city');
            const $districtSelect = $('#province_district');
            
            if ($provinceSelect.length && $districtSelect.length) {
                // Populate provinces
                const provinces = vefifyQuizAjax.provinces;
                for (const code in provinces) {
                    $provinceSelect.append(`<option value="${code}">${provinces[code].name}</option>`);
                }
            }
        },
        
        // Handle province change
        handleProvinceChange: function(e) {
            const provinceCode = $(e.target).val();
            const $districtSelect = $('#province_district');
            
            // Clear and disable district select
            $districtSelect.empty().append('<option value="">Chọn quận/huyện</option>').prop('disabled', true);
            
            if (provinceCode && vefifyQuizAjax.provinces[provinceCode]) {
                const districts = vefifyQuizAjax.provinces[provinceCode].districts;
                
                // Populate districts
                for (const code in districts) {
                    $districtSelect.append(`<option value="${code}">${districts[code]}</option>`);
                }
                
                $districtSelect.prop('disabled', false);
            }
        },
        
        // Initialize quiz navigation
        initQuizNavigation: function() {
            const $questions = $('.vefify-question');
            this.totalQuestions = $questions.length;
            
            if (this.totalQuestions > 0) {
                this.updateProgress();
            }
        },
        
        // Initialize form validation
        initFormValidation: function() {
            // Real-time validation
            $('.vefify-input, .vefify-select').on('blur', function() {
                VefifyQuiz.validateField($(this));
            });
            
            // Clear errors on input
            $('.vefify-input, .vefify-select').on('input change', function() {
                VefifyQuiz.clearFieldError($(this));
            });
        },
        
        // Handle phone input with validation
        handlePhoneInput: function(e) {
            const $input = $(e.target);
            const phone = $input.val();
            const campaignId = $('#vefify-quiz-container').data('campaign-id');
            
            // Clear previous timeout
            if (this.phoneCheckTimeout) {
                clearTimeout(this.phoneCheckTimeout);
            }
            
            // Debounced phone validation
            this.phoneCheckTimeout = setTimeout(() => {
                if (phone.length >= 10) {
                    this.validatePhone(phone, campaignId, $input);
                }
            }, 500);
        },
        
        // Validate phone number
        validatePhone: function(phone, campaignId, $input) {
            // Show loading state
            $input.addClass('validating');
            
            $.ajax({
                url: vefifyQuizAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'vefify_check_phone',
                    phone: phone,
                    campaign_id: campaignId,
                    nonce: vefifyQuizAjax.nonce
                },
                success: (response) => {
                    $input.removeClass('validating');
                    
                    if (response.success) {
                        if (response.data.exists) {
                            this.showFieldError($input, vefifyQuizAjax.messages.phone_exists);
                        } else {
                            this.clearFieldError($input);
                            // Update input with formatted phone
                            $input.val(response.data.formatted_phone);
                        }
                    } else {
                        this.showFieldError($input, vefifyQuizAjax.messages.phone_invalid);
                    }
                },
                error: () => {
                    $input.removeClass('validating');
                    this.showFieldError($input, 'Lỗi kiểm tra số điện thoại');
                }
            });
        },
        
        // Handle registration form submission
        handleRegistration: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Validate form
            if (!this.validateForm($form)) {
                return false;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true).addClass('vefify-loading');
            const originalText = $submitBtn.text();
            $submitBtn.text(vefifyQuizAjax.messages.loading);
            
            // Get form data
            const formData = new FormData($form[0]);
            formData.append('action', 'vefify_submit_registration');
            formData.append('nonce', vefifyQuizAjax.nonce);
            formData.append('session_id', $('#vefify-quiz-container').data('session-id'));
            
            $.ajax({
                url: vefifyQuizAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        // Reload page to show quiz
                        window.location.reload();
                    } else {
                        this.showFormError($form, response.data.message);
                    }
                },
                error: () => {
                    this.showFormError($form, 'Có lỗi xảy ra, vui lòng thử lại');
                },
                complete: () => {
                    $submitBtn.prop('disabled', false).removeClass('vefify-loading').text(originalText);
                }
            });
        },
        
        // Handle answer selection
        handleAnswerSelection: function(e) {
            const $input = $(e.target);
            const $option = $input.closest('.vefify-option');
            const $question = $input.closest('.vefify-question');
            const questionId = $question.data('question-id');
            const isMultiple = $input.attr('type') === 'checkbox';
            
            if (!isMultiple) {
                // Radio: clear other selections
                $question.find('.vefify-option').removeClass('selected');
            }
            
            // Toggle selection
            if ($input.is(':checked')) {
                $option.addClass('selected');
            } else {
                $option.removeClass('selected');
            }
            
            // Store answer
            if (!this.answers[questionId]) {
                this.answers[questionId] = [];
            }
            
            if (isMultiple) {
                // Checkbox: add/remove from array
                const value = $input.val();
                if ($input.is(':checked')) {
                    if (!this.answers[questionId].includes(value)) {
                        this.answers[questionId].push(value);
                    }
                } else {
                    this.answers[questionId] = this.answers[questionId].filter(v => v !== value);
                }
            } else {
                // Radio: replace array
                this.answers[questionId] = [$input.val()];
            }
        },
        
        // Navigate to next question
        nextQuestion: function(e) {
            e.preventDefault();
            
            if (this.currentQuestion < this.totalQuestions - 1) {
                // Hide current question
                $(`.vefify-question`).eq(this.currentQuestion).hide();
                
                // Show next question
                this.currentQuestion++;
                $(`.vefify-question`).eq(this.currentQuestion).show();
                
                // Update progress
                this.updateProgress();
                
                // Scroll to top
                this.scrollToTop();
            }
        },
        
        // Navigate to previous question
        prevQuestion: function(e) {
            e.preventDefault();
            
            if (this.currentQuestion > 0) {
                // Hide current question
                $(`.vefify-question`).eq(this.currentQuestion).hide();
                
                // Show previous question
                this.currentQuestion--;
                $(`.vefify-question`).eq(this.currentQuestion).show();
                
                // Update progress
                this.updateProgress();
                
                // Scroll to top
                this.scrollToTop();
            }
        },
        
        // Update progress bar
        updateProgress: function() {
            const progress = ((this.currentQuestion + 1) / this.totalQuestions) * 100;
            $('.progress-fill').css('width', `${progress}%`);
            $('.progress-text').text(`Câu hỏi ${this.currentQuestion + 1} / ${this.totalQuestions}`);
        },
        
        // Handle quiz submission
        handleQuizSubmission: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitBtn = $form.find('.submit-quiz');
            
            // Show confirmation
            if (!confirm('Bạn có chắc chắn muốn nộp bài? Bạn sẽ không thể thay đổi câu trả lời sau khi nộp.')) {
                return false;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true).addClass('vefify-loading');
            const originalText = $submitBtn.text();
            $submitBtn.text(vefifyQuizAjax.messages.loading);
            
            // Prepare data
            const formData = new FormData($form[0]);
            formData.append('action', 'vefify_submit_quiz');
            formData.append('nonce', vefifyQuizAjax.nonce);
            
            // Add answers data
            for (const questionId in this.answers) {
                const answers = this.answers[questionId];
                answers.forEach((answer, index) => {
                    formData.append(`answers[${questionId}][]`, answer);
                });
            }
            
            $.ajax({
                url: vefifyQuizAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        // Show success message
                        this.showSuccess(vefifyQuizAjax.messages.quiz_submitted);
                        
                        // Reload to show results
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    this.showError('Có lỗi xảy ra khi nộp bài, vui lòng thử lại');
                },
                complete: () => {
                    $submitBtn.prop('disabled', false).removeClass('vefify-loading').text(originalText);
                }
            });
        },
        
        // Claim gift
        claimGift: function(e) {
            e.preventDefault();
            
            const $btn = $(e.target);
            const giftId = $btn.data('gift-id');
            const participantId = $btn.data('participant-id');
            
            $btn.prop('disabled', true).text('Đang xử lý...');
            
            $.ajax({
                url: vefifyQuizAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'vefify_claim_gift',
                    gift_id: giftId,
                    participant_id: participantId,
                    nonce: vefifyQuizAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $btn.text('✅ Đã nhận').removeClass('vefify-btn-primary').addClass('vefify-btn-success');
                        this.showSuccess('Quà tặng đã được gửi thành công!');
                    } else {
                        $btn.prop('disabled', false).text('Nhận quà ngay');
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    $btn.prop('disabled', false).text('Nhận quà ngay');
                    this.showError('Có lỗi xảy ra, vui lòng thử lại');
                }
            });
        },
        
        // Share results
        shareResults: function(e) {
            e.preventDefault();
            
            const $container = $('#vefify-quiz-results');
            const campaignName = $container.find('h2').text();
            const score = $container.find('.score-number').text();
            const total = $container.find('.score-total').text().replace('/', '');
            
            const shareText = `Tôi vừa hoàn thành quiz "${campaignName}" với điểm số ${score}${total}! 🎉`;
            const shareUrl = window.location.href;
            
            if (navigator.share) {
                // Use native sharing if available
                navigator.share({
                    title: campaignName,
                    text: shareText,
                    url: shareUrl
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(`${shareText}\n${shareUrl}`).then(() => {
                    this.showSuccess('Đã sao chép liên kết chia sẻ!');
                });
            }
        },
        
        // Form validation
        validateForm: function($form) {
            let isValid = true;
            
            $form.find('[required]').each(function() {
                const $field = $(this);
                if (!VefifyQuiz.validateField($field)) {
                    isValid = false;
                }
            });
            
            return isValid;
        },
        
        // Validate individual field
        validateField: function($field) {
            const value = $field.val().trim();
            const fieldName = $field.attr('name');
            
            // Required field check
            if ($field.prop('required') && !value) {
                this.showFieldError($field, vefifyQuizAjax.messages.required_field);
                return false;
            }
            
            // Specific field validations
            switch (fieldName) {
                case 'participant_phone':
                    if (value && !this.isValidVietnamesePhone(value)) {
                        this.showFieldError($field, vefifyQuizAjax.messages.phone_invalid);
                        return false;
                    }
                    break;
                    
                case 'participant_email':
                    if (value && !this.isValidEmail(value)) {
                        this.showFieldError($field, 'Email không đúng định dạng');
                        return false;
                    }
                    break;
            }
            
            this.clearFieldError($field);
            return true;
        },
        
        // Show field error
        showFieldError: function($field, message) {
            const $errorDiv = $field.siblings('.vefify-field-error');
            $errorDiv.text(message).addClass('show');
            $field.addClass('error');
        },
        
        // Clear field error
        clearFieldError: function($field) {
            const $errorDiv = $field.siblings('.vefify-field-error');
            $errorDiv.removeClass('show').text('');
            $field.removeClass('error');
        },
        
        // Show form error
        showFormError: function($form, message) {
            let $errorDiv = $form.find('.form-error');
            if (!$errorDiv.length) {
                $errorDiv = $('<div class="form-error vefify-error"></div>');
                $form.prepend($errorDiv);
            }
            $errorDiv.text(message).show();
        },
        
        // Show success message
        showSuccess: function(message) {
            const $success = $('<div class="vefify-success"></div>').text(message);
            $('#vefify-quiz-container').prepend($success);
            
            setTimeout(() => {
                $success.fadeOut(() => $success.remove());
            }, 3000);
        },
        
        // Show error message
        showError: function(message) {
            const $error = $('<div class="vefify-error"></div>').text(message);
            $('#vefify-quiz-container').prepend($error);
            
            setTimeout(() => {
                $error.fadeOut(() => $error.remove());
            }, 5000);
        },
        
        // Scroll to top smoothly
        scrollToTop: function() {
            $('html, body').animate({
                scrollTop: $('#vefify-quiz-container').offset().top - 20
            }, 300);
        },
        
        // Validation helpers
        isValidVietnamesePhone: function(phone) {
            // Remove non-digits
            const cleanPhone = phone.replace(/\D/g, '');
            
            // Vietnamese mobile patterns
            const patterns = [
                /^0(3[2-9]|5[689]|7[06-9]|8[1-689]|9[0-46-9])[0-9]{7}$/, // Mobile
                /^0(2[0-9])[0-9]{8}$/ // Landline
            ];
            
            return patterns.some(pattern => pattern.test(cleanPhone));
        },
        
        isValidEmail: function(email) {
            const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return pattern.test(email);
        }
    };
    
    // Copy gift code function (global)
    window.copyGiftCode = function() {
        const giftCode = document.getElementById('gift-code').textContent;
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(giftCode).then(() => {
                VefifyQuiz.showSuccess('Đã sao chép mã quà tặng: ' + giftCode);
            });
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = giftCode;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            VefifyQuiz.showSuccess('Đã sao chép mã quà tặng: ' + giftCode);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        VefifyQuiz.init();
    });
    
    // Auto-save answers (for page refresh protection)
    $(window).on('beforeunload', function() {
        if (Object.keys(VefifyQuiz.answers).length > 0) {
            localStorage.setItem('vefify_quiz_answers', JSON.stringify(VefifyQuiz.answers));
        }
    });
    
    // Restore answers on page load
    $(document).ready(function() {
        const savedAnswers = localStorage.getItem('vefify_quiz_answers');
        if (savedAnswers) {
            try {
                const answers = JSON.parse(savedAnswers);
                VefifyQuiz.answers = answers;
                
                // Restore form state
                for (const questionId in answers) {
                    const questionAnswers = answers[questionId];
                    questionAnswers.forEach(answerId => {
                        $(`input[name="answers[${questionId}][]"][value="${answerId}"], input[name="answers[${questionId}]"][value="${answerId}"]`).prop('checked', true).trigger('change');
                    });
                }
            } catch (e) {
                console.warn('Failed to restore quiz answers:', e);
            }
        }
    });
    
    // Clear saved answers when quiz is completed
    $(document).on('quiz-completed', function() {
        localStorage.removeItem('vefify_quiz_answers');
    });
    
})(jQuery);