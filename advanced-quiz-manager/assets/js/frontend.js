// Frontend JavaScript for Advanced Quiz Manager
// File: assets/js/frontend.js

jQuery(document).ready(function($) {
    
    // Initialize quiz functionality
    initializeQuiz();
    
    function initializeQuiz() {
        // Handle province selection
        $('.aqm-provinces-select').on('change', function() {
            const provinceCode = $(this).val();
            const questionContainer = $(this).closest('.aqm-question-container');
            const districtSelect = questionContainer.find('.aqm-districts-select');
            const wardSelect = questionContainer.find('.aqm-wards-select');
            
            if (provinceCode) {
                loadDistricts(provinceCode, districtSelect);
                districtSelect.show().prop('required', true);
                wardSelect.hide().prop('required', false).html('<option value="">Select Ward</option>');
            } else {
                districtSelect.hide().prop('required', false).html('<option value="">Select District</option>');
                wardSelect.hide().prop('required', false).html('<option value="">Select Ward</option>');
            }
        });
        
        // Handle district selection
        $(document).on('change', '.aqm-districts-select', function() {
            const districtCode = $(this).val();
            const questionContainer = $(this).closest('.aqm-question-container');
            const wardSelect = questionContainer.find('.aqm-wards-select');
            
            if (districtCode) {
                loadWards(districtCode, wardSelect);
                wardSelect.show().prop('required', true);
            } else {
                wardSelect.hide().prop('required', false).html('<option value="">Select Ward</option>');
            }
        });
        
        // Handle rating interactions
        $('.aqm-rating-container').each(function() {
            const container = $(this);
            const stars = container.find('.aqm-rating-label');
            
            stars.hover(
                function() {
                    const index = stars.index(this);
                    highlightStars(container, index);
                },
                function() {
                    const checked = container.find('input:checked');
                    if (checked.length) {
                        const checkedIndex = stars.index(checked.parent());
                        highlightStars(container, checkedIndex);
                    } else {
                        highlightStars(container, -1);
                    }
                }
            );
            
            stars.on('click', function() {
                const index = stars.index(this);
                highlightStars(container, index);
            });
        });
        
        // Handle form submission
        $('.aqm-quiz-form').on('submit', function(e) {
            e.preventDefault();
            submitQuiz($(this));
        });
        
        // Track analytics events
        trackQuizStart();
        trackQuestionInteractions();
    }
    
    function loadDistricts(provinceCode, districtSelect) {
        // Use the provinces data from localized script
        const provincesData = aqm_front.provinces_data;
        const province = provincesData.find(p => p.code === provinceCode);
        
        districtSelect.html('<option value="">Select District</option>');
        
        if (province && province.districts) {
            province.districts.forEach(function(district) {
                districtSelect.append(
                    `<option value="${district.code}">${district.name}</option>`
                );
            });
        }
    }
    
    function loadWards(districtCode, wardSelect) {
        // For demo purposes, adding some sample wards
        // In a real implementation, you would load this from your database
        const sampleWards = [
            { code: '001', name: 'Phường 1' },
            { code: '002', name: 'Phường 2' },
            { code: '003', name: 'Phường 3' },
            { code: '004', name: 'Phường 4' },
            { code: '005', name: 'Phường 5' }
        ];
        
        wardSelect.html('<option value="">Select Ward</option>');
        
        sampleWards.forEach(function(ward) {
            wardSelect.append(
                `<option value="${ward.code}">${ward.name}</option>`
            );
        });
    }
    
    function highlightStars(container, index) {
        const stars = container.find('.aqm-star');
        stars.removeClass('highlighted');
        
        if (index >= 0) {
            stars.slice(0, index + 1).addClass('highlighted');
        }
    }
    
    function submitQuiz(form) {
        const formData = new FormData(form[0]);
        const campaignId = form.find('input[name="campaign_id"]').val();
        const submitBtn = form.find('.aqm-submit-btn');
        
        // Disable submit button
        submitBtn.prop('disabled', true).text('Submitting...');
        
        // Collect answers
        const answers = {};
        form.find('[name^="question_"]').each(function() {
            const name = $(this).attr('name');
            const questionId = name.replace('question_', '').split('_')[0];
            let value = $(this).val();
            
            if ($(this).attr('type') === 'radio' && !$(this).is(':checked')) {
                return;
            }
            
            // Handle province/district/ward combinations
            if (name.includes('_district') || name.includes('_ward')) {
                const baseQuestionId = name.replace('_district', '').replace('_ward', '').replace('question_', '');
                if (!answers[baseQuestionId]) {
                    answers[baseQuestionId] = {};
                }
                
                if (name.includes('_district')) {
                    answers[baseQuestionId].district = value;
                } else if (name.includes('_ward')) {
                    answers[baseQuestionId].ward = value;
                }
            } else {
                if (typeof answers[questionId] === 'object') {
                    answers[questionId].province = value;
                } else {
                    answers[questionId] = value;
                }
            }
        });
        
        // Convert object answers to JSON strings
        Object.keys(answers).forEach(questionId => {
            if (typeof answers[questionId] === 'object') {
                answers[questionId] = JSON.stringify(answers[questionId]);
            }
        });
        
        formData.append('action', 'aqm_submit_quiz');
        formData.append('nonce', aqm_front.nonce);
        formData.append('answers', JSON.stringify(answers));
        
        // Track submission start
        trackEvent('quiz_submission_started', { campaign_id: campaignId });
        
        $.ajax({
            url: aqm_front.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showQuizResults(form, response.data);
                    trackEvent('quiz_completed', {
                        campaign_id: campaignId,
                        score: response.data.score,
                        response_id: response.data.response_id
                    });
                    
                    // Check for gift eligibility
                    checkGiftEligibility(campaignId, response.data.score);
                } else {
                    showError(form, response.data.message || 'Submission failed');
                }
            },
            error: function() {
                showError(form, 'Network error. Please try again.');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Submit Quiz');
            }
        });
    }
    
    function showQuizResults(form, data) {
        const container = form.closest('.aqm-quiz-container');
        const resultDiv = container.find('.aqm-quiz-result');
        
        let resultHTML = `
            <div class="aqm-success-message">
                <h3>🎉 Quiz Completed Successfully!</h3>
                <p>Thank you for participating in our quiz.</p>
                <div class="aqm-score-display">
                    <strong>Your Score: ${data.score} points</strong>
                </div>
            </div>
        `;
        
        resultDiv.html(resultHTML).show();
        form.hide();
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: resultDiv.offset().top - 100
        }, 500);
    }
    
    function showError(form, message) {
        const container = form.closest('.aqm-quiz-container');
        let errorDiv = container.find('.aqm-error-message');
        
        if (errorDiv.length === 0) {
            errorDiv = $('<div class="aqm-error-message"></div>');
            container.prepend(errorDiv);
        }
        
        errorDiv.html(`<p class="error">❌ ${message}</p>`).show();
        
        setTimeout(() => {
            errorDiv.fadeOut();
        }, 5000);
    }
    
    function checkGiftEligibility(campaignId, score) {
        $.ajax({
            url: aqm_front.ajax_url,
            type: 'POST',
            data: {
                action: 'aqm_check_gift_eligibility',
                nonce: aqm_front.nonce,
                campaign_id: campaignId,
                score: score
            },
            success: function(response) {
                if (response.success && response.data.gift) {
                    showGiftPopup(response.data.gift);
                }
            }
        });
    }
    
    function showGiftPopup(gift) {
        const popup = $(`
            <div class="aqm-gift-popup-overlay">
                <div class="aqm-gift-popup">
                    <div class="aqm-gift-header">
                        <h3>🎁 Congratulations!</h3>
                        <button class="aqm-close-popup">&times;</button>
                    </div>
                    <div class="aqm-gift-content">
                        <h4>${gift.name}</h4>
                        <p>${gift.description}</p>
                        ${gift.image_url ? `<img src="${gift.image_url}" alt="${gift.name}" class="aqm-gift-image">` : ''}
                        ${gift.claim_code ? `<div class="aqm-claim-code">
                            <strong>Claim Code: ${gift.claim_code}</strong>
                            <button class="aqm-copy-code" data-code="${gift.claim_code}">Copy Code</button>
                        </div>` : ''}
                    </div>
                    <div class="aqm-gift-actions">
                        <button class="aqm-claim-gift" data-gift-id="${gift.id}">Claim Gift</button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(popup);
        popup.fadeIn();
        
        // Handle popup close
        popup.find('.aqm-close-popup, .aqm-gift-popup-overlay').on('click', function(e) {
            if (e.target === this) {
                popup.fadeOut(() => popup.remove());
            }
        });
        
        // Handle code copy
        popup.find('.aqm-copy-code').on('click', function() {
            const code = $(this).data('code');
            navigator.clipboard.writeText(code).then(() => {
                $(this).text('Copied!').addClass('copied');
                setTimeout(() => {
                    $(this).text('Copy Code').removeClass('copied');
                }, 2000);
            });
        });
        
        // Handle gift claim
        popup.find('.aqm-claim-gift').on('click', function() {
            const giftId = $(this).data('gift-id');
            claimGift(giftId, popup);
        });
    }
    
    function claimGift(giftId, popup) {
        $.ajax({
            url: aqm_front.ajax_url,
            type: 'POST',
            data: {
                action: 'aqm_claim_gift',
                nonce: aqm_front.nonce,
                gift_id: giftId
            },
            success: function(response) {
                if (response.success) {
                    popup.find('.aqm-gift-actions').html('<p class="success">Gift claimed successfully!</p>');
                    trackEvent('gift_claimed', { gift_id: giftId });
                } else {
                    popup.find('.aqm-gift-actions').html(`<p class="error">${response.data.message}</p>`);
                }
            }
        });
    }
    
    function trackQuizStart() {
        $('.aqm-quiz-container').each(function() {
            const campaignId = $(this).data('campaign-id');
            trackEvent('quiz_started', { campaign_id: campaignId });
        });
    }
    
    function trackQuestionInteractions() {
        $('.aqm-question-container input, .aqm-question-container select').on('change', function() {
            const questionContainer = $(this).closest('.aqm-question-container');
            const questionId = questionContainer.data('question-id');
            const questionType = questionContainer.data('question-type');
            
            trackEvent('question_answered', {
                question_id: questionId,
                question_type: questionType,
                answer_length: $(this).val().length
            });
        });
    }
    
    function trackEvent(eventType, eventData) {
        $.ajax({
            url: aqm_front.ajax_url,
            type: 'POST',
            data: {
                action: 'aqm_track_event',
                nonce: aqm_front.nonce,
                event_type: eventType,
                event_data: JSON.stringify(eventData)
            }
        });
    }
    
    // Auto-save functionality for long quizzes
    function initAutoSave() {
        let autoSaveTimer;
        
        $('.aqm-quiz-form input, .aqm-quiz-form select, .aqm-quiz-form textarea').on('change input', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                saveQuizProgress($(this).closest('.aqm-quiz-form'));
            }, 3000); // Save after 3 seconds of inactivity
        });
    }
    
    function saveQuizProgress(form) {
        const formData = new FormData(form[0]);
        formData.append('action', 'aqm_save_progress');
        formData.append('nonce', aqm_front.nonce);
        
        $.ajax({
            url: aqm_front.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showProgressSaved();
                }
            }
        });
    }
    
    function showProgressSaved() {
        let indicator = $('.aqm-progress-indicator');
        if (indicator.length === 0) {
            indicator = $('<div class="aqm-progress-indicator">Progress saved</div>');
            $('body').append(indicator);
        }
        
        indicator.show().fadeOut(2000);
    }
    
    // Initialize auto-save for longer quizzes
    if ($('.aqm-question-container').length > 5) {
        initAutoSave();
    }
    
    // Progress bar for multi-step quizzes
    function updateProgressBar() {
        const totalQuestions = $('.aqm-question-container').length;
        const answeredQuestions = $('.aqm-question-container').filter(function() {
            const inputs = $(this).find('input, select, textarea');
            return inputs.filter(function() {
                return $(this).val() !== '';
            }).length > 0;
        }).length;
        
        const progress = (answeredQuestions / totalQuestions) * 100;
        
        let progressBar = $('.aqm-progress-bar');
        if (progressBar.length === 0) {
            progressBar = $(`
                <div class="aqm-progress-bar-container">
                    <div class="aqm-progress-bar">
                        <div class="aqm-progress-fill"></div>
                    </div>
                    <span class="aqm-progress-text">${answeredQuestions}/${totalQuestions} questions completed</span>
                </div>
            `);
            $('.aqm-quiz-form').prepend(progressBar);
        }
        
        progressBar.find('.aqm-progress-fill').css('width', progress + '%');
        progressBar.find('.aqm-progress-text').text(`${answeredQuestions}/${totalQuestions} questions completed`);
    }
    
    // Update progress on any input change
    $(document).on('change input', '.aqm-question-container input, .aqm-question-container select, .aqm-question-container textarea', function() {
        updateProgressBar();
    });
    
    // Initialize progress bar
    updateProgressBar();
    
    // Smooth scrolling for long forms
    $('.aqm-question-container').each(function(index) {
        if (index > 0) {
            $(this).hide();
        }
    });
    
    // Next/Previous navigation for step-by-step quizzes
    if ($('.aqm-question-container').length > 3) {
        addStepNavigation();
    }
    
    function addStepNavigation() {
        let currentStep = 0;
        const totalSteps = $('.aqm-question-container').length;
        
        // Add navigation buttons
        $('.aqm-quiz-actions').prepend(`
            <div class="aqm-step-navigation">
                <button type="button" class="aqm-prev-btn" disabled>Previous</button>
                <span class="aqm-step-indicator">Step 1 of ${totalSteps}</span>
                <button type="button" class="aqm-next-btn">Next</button>
            </div>
        `);
        
        // Handle navigation
        $('.aqm-next-btn').on('click', function() {
            if (validateCurrentStep(currentStep)) {
                currentStep++;
                updateStepDisplay();
            }
        });
        
        $('.aqm-prev-btn').on('click', function() {
            currentStep--;
            updateStepDisplay();
        });
        
        function updateStepDisplay() {
            $('.aqm-question-container').hide();
            $('.aqm-question-container').eq(currentStep).show();
            
            $('.aqm-prev-btn').prop('disabled', currentStep === 0);
            $('.aqm-next-btn').toggle(currentStep < totalSteps - 1);
            $('.aqm-submit-btn').toggle(currentStep === totalSteps - 1);
            $('.aqm-step-indicator').text(`Step ${currentStep + 1} of ${totalSteps}`);
        }
        
        function validateCurrentStep(step) {
            const currentQuestion = $('.aqm-question-container').eq(step);
            const requiredInputs = currentQuestion.find('[required]');
            
            let isValid = true;
            requiredInputs.each(function() {
                if (!$(this).val()) {
                    $(this).addClass('error');
                    isValid = false;
                } else {
                    $(this).removeClass('error');
                }
            });
            
            if (!isValid) {
                showError($('.aqm-quiz-form'), 'Please complete all required fields before proceeding.');
            }
            
            return isValid;
        }
    }
});

// Utility functions
function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}