// Frontend JavaScript for Advanced Quiz Manager - Mobile Implementation
// File: assets/js/frontend.js

jQuery(document).ready(function($) {
    
    // Quiz state management
    let currentQuestion = 0;
    let userAnswers = {};
    let userData = {};
    let quizData = {};
    
    // DOM elements
    const registrationForm = $('#aqm-registration-form');
    const quizContainer = $('#aqm-quiz-container');
    const loadingState = $('#aqm-loading-state');
    const resultContainer = $('#aqm-result-container');
    const progressFill = $('#aqm-progress-fill');
    
    // Initialize quiz
    initializeQuiz();
    
    function initializeQuiz() {
        // Load quiz data from script tag
        const quizDataElement = document.getElementById('aqm-quiz-data');
        if (quizDataElement) {
            quizData = JSON.parse(quizDataElement.textContent);
        }
        
        // Form submission handler
        $('#aqm-user-form').on('submit', function(e) {
            e.preventDefault();
            handleUserFormSubmission();
        });
        
        // Province selection handler
        $('#aqm-province').on('change', function() {
            const provinceCode = $(this).val();
            if (provinceCode) {
                loadDistricts(provinceCode);
            }
        });
        
        // Navigation buttons
        $('#aqm-prev-btn').on('click', function() {
            if (currentQuestion > 0) {
                currentQuestion--;
                loadQuestion(currentQuestion);
                updateProgress();
            }
        });
        
        $('#aqm-next-btn').on('click', function() {
            const currentAnswers = getCurrentQuestionAnswers();
            if (currentAnswers.length > 0) {
                userAnswers[quizData.questions[currentQuestion].id] = currentAnswers;
                
                if (currentQuestion < quizData.questions.length - 1) {
                    currentQuestion++;
                    loadQuestion(currentQuestion);
                    updateProgress();
                } else {
                    submitQuiz();
                }
            } else {
                showError('Please select at least one answer.');
            }
        });
        
        // Touch gesture support
        let touchStartX = 0;
        let touchEndX = 0;
        
        $(document).on('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        });
        
        $(document).on('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });
        
        // Initialize progress
        updateProgress(0);
    }
    
    function handleUserFormSubmission() {
        if (!validateForm()) {
            return;
        }
        
        // Get form data
        const formData = {
            full_name: $('#aqm-full-name').val().trim(),
            phone_number: $('#aqm-phone-number').val().trim(),
            province: $('#aqm-province').val(),
            pharmacy_code: $('#aqm-pharmacy-code').val().trim(),
            campaign_id: quizData.campaign_id
        };
        
        // Check phone number
        checkPhoneNumber(formData.phone_number, formData.campaign_id)
            .then(function(response) {
                if (response.success) {
                    userData = formData;
                    startQuiz();
                } else {
                    if (response.data && response.data.code === 'phone_exists') {
                        showAlreadyParticipatedPopup();
                    } else {
                        showError('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                    }
                }
            })
            .catch(function() {
                showError(aqm_front.strings.error);
            });
    }
    
    function validateForm() {
        let isValid = true;
        
        // Validate full name
        const fullName = $('#aqm-full-name').val().trim();
        if (!fullName) {
            showFieldError('aqm-full-name', 'aqm-name-error', aqm_front.strings.name_required);
            isValid = false;
        } else {
            hideFieldError('aqm-full-name', 'aqm-name-error');
        }
        
        // Validate phone number
        const phoneNumber = $('#aqm-phone-number').val().trim();
        const phoneRegex = /^[0-9]{10,11}$/;
        if (!phoneNumber || !phoneRegex.test(phoneNumber)) {
            showFieldError('aqm-phone-number', 'aqm-phone-error', aqm_front.strings.phone_required);
            isValid = false;
        } else {
            hideFieldError('aqm-phone-number', 'aqm-phone-error');
        }
        
        // Validate province
        const province = $('#aqm-province').val();
        if (!province) {
            showFieldError('aqm-province', 'aqm-province-error', aqm_front.strings.province_required);
            isValid = false;
        } else {
            hideFieldError('aqm-province', 'aqm-province-error');
        }
        
        return isValid;
    }
    
    function showFieldError(fieldId, errorId, message) {
        $('#' + fieldId).addClass('error');
        $('#' + errorId).text(message).show();
    }
    
    function hideFieldError(fieldId, errorId) {
        $('#' + fieldId).removeClass('error');
        $('#' + errorId).hide();
    }
    
    function checkPhoneNumber(phone, campaignId) {
        return $.ajax({
            url: aqm_front.ajax_url,
            type: 'POST',
            data: {
                action: 'aqm_check_phone',
                phone: phone,
                campaign_id: campaignId,
                nonce: aqm_front.nonce
            }
        });
    }
    
    function loadDistricts(provinceCode) {
        $.ajax({
            url: aqm_front.ajax_url,
            type: 'POST',
            data: {
                action: 'aqm_get_districts',
                province_code: provinceCode,
                nonce: aqm_front.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Populate districts dropdown if needed
                    console.log('Districts loaded:', response.data);
                }
            }
        });
    }
    
    function showLoading() {
        registrationForm.hide();
        loadingState.show();
        updateProgress(10);
    }
    
    function hideLoading() {
        loadingState.hide();
    }
    
    function startQuiz() {
        showLoading();
        
        setTimeout(function() {
            hideLoading();
            registrationForm.hide();
            quizContainer.show().addClass('aqm-fade-in');
            currentQuestion = 0;
            userAnswers = {};
            loadQuestion(currentQuestion);
            updateProgress(20);
        }, 1500);
    }
    
    function loadQuestion(index) {
        if (!quizData.questions || index >= quizData.questions.length) {
            return;
        }
        
        const question = quizData.questions[index];
        
        // Update question counter and title
        $('#aqm-question-counter').text(`Question ${index + 1} of ${quizData.questions.length}`);
        $('#aqm-question-title').text(question.question);
        
        // Clear and populate answers
        const answersContainer = $('#aqm-answers-container');
        answersContainer.empty();
        
        if (question.options && question.options.length > 0) {
            question.options.forEach(function(option, optionIndex) {
                const answerDiv = $(`
                    <div class="aqm-answer-option" data-option-id="${option.id}">
                        <input type="checkbox" id="aqm-option-${option.id}" value="${option.id}">
                        <div class="aqm-answer-text">${option.text}</div>
                    </div>
                `);
                
                answerDiv.on('click', function() {
                    const checkbox = $(this).find('input[type="checkbox"]');
                    const isChecked = checkbox.prop('checked');
                    
                    // For single choice questions, uncheck others
                    if (question.type === 'single_choice') {
                        answersContainer.find('.aqm-answer-option').removeClass('selected');
                        answersContainer.find('input[type="checkbox"]').prop('checked', false);
                    }
                    
                    checkbox.prop('checked', !isChecked);
                    $(this).toggleClass('selected', !isChecked);
                });
                
                answersContainer.append(answerDiv);
            });
        }
        
        // Restore previous answers if any
        if (userAnswers[question.id]) {
            userAnswers[question.id].forEach(function(answerId) {
                const option = answersContainer.find(`[data-option-id="${answerId}"]`);
                option.addClass('selected');
                option.find('input[type="checkbox"]').prop('checked', true);
            });
        }
        
        // Update navigation buttons
        $('#aqm-prev-btn').prop('disabled', index === 0);
        $('#aqm-next-btn').text(index === quizData.questions.length - 1 ? 'Submit' : 'Next →');
    }
    
    function getCurrentQuestionAnswers() {
        const answers = [];
        $('#aqm-answers-container .aqm-answer-option.selected').each(function() {
            answers.push($(this).data('option-id'));
        });
        return answers;
    }
    
    function updateProgress(percentage) {
        if (percentage === undefined) {
            const totalQuestions = quizData.questions ? quizData.questions.length : 1;
            percentage = 20 + ((currentQuestion + 1) / totalQuestions) * 60; // 20% for registration, 60% for questions
        }
        
        progressFill.css('width', percentage + '%');
    }
    
    function submitQuiz() {
        // Add final answer
        if (quizData.questions[currentQuestion]) {
            const currentAnswers = getCurrentQuestionAnswers();
            if (currentAnswers.length > 0) {
                userAnswers[quizData.questions[currentQuestion].id] = currentAnswers;
            }
        }
        
        updateProgress(90);
        
        $.ajax({
            url: aqm_front.ajax_url,
            type: 'POST',
            data: {
                action: 'aqm_submit_quiz',
                campaign_id: quizData.campaign_id,
                full_name: userData.full_name,
                phone_number: userData.phone_number,
                province: userData.province,
                pharmacy_code: userData.pharmacy_code,
                answers: JSON.stringify(userAnswers),
                nonce: aqm_front.nonce
            },
            beforeSend: function() {
                $('#aqm-next-btn').prop('disabled', true).text('Submitting...');
            },
            success: function(response) {
                if (response.success) {
                    updateProgress(100);
                    showResults(response.data);
                } else {
                    if (response.data && response.data.code === 'phone_exists') {
                        showAlreadyParticipatedPopup();
                    } else {
                        showError('Submission failed: ' + (response.data ? response.data.message : 'Unknown error'));
                    }
                }
            },
            error: function() {
                showError(aqm_front.strings.error);
            },
            complete: function() {
                $('#aqm-next-btn').prop('disabled', false).text('Submit');
            }
        });
    }
    
    function showResults(data) {
        quizContainer.hide();
        
        // Update result display
        $('#aqm-result-score').text(`${data.score}/${data.total}`);
        $('#aqm-result-message').text(data.message);
        
        // Show reward if available
        if (data.gift) {
            $('#aqm-reward-card').show();
            $('#aqm-reward-code').text(data.gift.code);
        }
        
        resultContainer.show().addClass('aqm-fade-in');
    }
    
    function showAlreadyParticipatedPopup() {
        $('#aqm-already-participated-popup').show().addClass('show');
    }
    
    function showError(message) {
        alert(message); // Simple error display, can be enhanced with modal
    }
    
    function handleSwipe() {
        const swipeThreshold = 50;
        
        if (quizContainer.is(':visible')) {
            if (touchEndX < touchStartX - swipeThreshold) {
                // Swipe left - next question
                if (currentQuestion < quizData.questions.length - 1) {
                    $('#aqm-next-btn').click();
                }
            }
            
            if (touchEndX > touchStartX + swipeThreshold) {
                // Swipe right - previous question
                if (currentQuestion > 0) {
                    $('#aqm-prev-btn').click();
                }
            }
        }
    }
    
    // Global functions
    window.aqmClosePopup = function() {
        $('.aqm-popup').removeClass('show').hide();
    };
    
    window.aqmRestartQuiz = function() {
        location.reload();
    };
});