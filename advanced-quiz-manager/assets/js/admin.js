// Enhanced Admin JavaScript for Advanced Quiz Manager
// File: assets/js/admin.js

jQuery(document).ready(function($) {
    
    // Initialize admin functionality
    initializeAdmin();
    
    function initializeAdmin() {
        initializeCampaignManagement();
        initializeQuestionBuilder();
        initializeGiftManagement();
        initializeDataTables();
        initializeAnalytics();
        initializeFormValidation();
        initializeTabs();
        initializeTooltips();
        initializeConfirmDialogs();
        initializeAutoSave();
    }
    
    // CAMPAIGN MANAGEMENT
    function initializeCampaignManagement() {
        // Campaign form submission
        $('#aqm-campaign-form').on('submit', function(e) {
            e.preventDefault();
            saveCampaign();
        });
        
        // Campaign status toggle
        $(document).on('change', '.aqm-campaign-status-toggle', function() {
            const campaignId = $(this).data('id');
            const newStatus = $(this).val();
            updateCampaignStatus(campaignId, newStatus);
        });
        
        // Delete campaign
        $(document).on('click', '.aqm-delete-campaign', function(e) {
            e.preventDefault();
            
            const campaignId = $(this).data('id');
            const campaignTitle = $(this).closest('tr').find('.campaign-title').text();
            
            if (confirm(`Are you sure you want to delete "${campaignTitle}"? This action cannot be undone and will remove all associated questions, responses, and gifts.`)) {
                deleteCampaign(campaignId);
            }
        });
        
        // Duplicate campaign
        $(document).on('click', '.aqm-duplicate-campaign', function(e) {
            e.preventDefault();
            
            const campaignId = $(this).data('id');
            duplicateCampaign(campaignId);
        });
        
        // Campaign preview
        $(document).on('click', '.aqm-preview-campaign', function(e) {
            e.preventDefault();
            
            const campaignId = $(this).data('id');
            const previewUrl = `${window.location.origin}?aqm_preview=1&campaign_id=${campaignId}`;
            window.open(previewUrl, '_blank');
        });
    }
    
    function saveCampaign() {
        const form = $('#aqm-campaign-form');
        const formData = new FormData(form[0]);
        formData.append('action', 'aqm_save_campaign');
        formData.append('nonce', aqm_ajax.nonce);
        
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.text();
        
        $.ajax({
            url: aqm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                submitBtn.prop('disabled', true).text('Saving...');
                showLoadingOverlay();
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    
                    if (response.data.redirect_url) {
                        setTimeout(() => {
                            window.location.href = response.data.redirect_url;
                        }, 1500);
                    }
                    
                    // Update campaign ID if new campaign
                    if (response.data.campaign_id && !form.find('[name="campaign_id"]').val()) {
                        form.find('[name="campaign_id"]').val(response.data.campaign_id);
                    }
                } else {
                    showNotice('error', response.data.message || 'Failed to save campaign');
                }
            },
            error: function() {
                showNotice('error', 'Network error occurred');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text(originalText);
                hideLoadingOverlay();
            }
        });
    }
    
    function updateCampaignStatus(campaignId, status) {
        $.ajax({
            url: aqm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aqm_update_campaign_status',
                campaign_id: campaignId,
                status: status,
                nonce: aqm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Campaign status updated successfully');
                } else {
                    showNotice('error', response.data.message || 'Failed to update status');
                }
            }
        });
    }
    
    function deleteCampaign(campaignId) {
        $.ajax({
            url: aqm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aqm_delete_campaign',
                campaign_id: campaignId,
                nonce: aqm_ajax.nonce
            },
            beforeSend: function() {
                showLoadingOverlay();
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    location.reload();
                } else {
                    showNotice('error', response.data.message || 'Failed to delete campaign');
                }
            },
            error: function() {
                showNotice('error', 'Network error occurred');
            },
            complete: function() {
                hideLoadingOverlay();
            }
        });
    }
    
    // QUESTION BUILDER
    function initializeQuestionBuilder() {
        // Add new question
        $(document).on('click', '#aqm-add-question', function(e) {
            e.preventDefault();
            addNewQuestion();
        });
        
        // Save question
        $(document).on('click', '.aqm-save-question', function(e) {
            e.preventDefault();
            
            const questionContainer = $(this).closest('.aqm-question-builder');
            saveQuestion(questionContainer);
        });
        
        // Delete question
        $(document).on('click', '.aqm-delete-question', function(e) {
            e.preventDefault();
            
            const questionContainer = $(this).closest('.aqm-question-builder');
            const questionId = questionContainer.data('question-id');
            
            if (confirm('Are you sure you want to delete this question?')) {
                if (questionId && questionId !== '0') {
                    deleteQuestion(questionId, questionContainer);
                } else {
                    questionContainer.remove();
                }
            }
        });
        
        // Add option
        $(document).on('click', '.aqm-add-option', function(e) {
            e.preventDefault();
            
            const optionsList = $(this).siblings('.aqm-options-list');
            const optionIndex = optionsList.children().length;
            
            const optionHtml = `
                <div class="aqm-option-item">
                    <input type="text" name="option_text[]" placeholder="Option text" required>
                    <label>
                        <input type="checkbox" name="option_correct[]" value="${optionIndex}">
                        Correct
                    </label>
                    <button type="button" class="button button-small aqm-remove-option">Remove</button>
                </div>
            `;
            
            optionsList.append(optionHtml);
        });
        
        // Remove option
        $(document).on('click', '.aqm-remove-option', function(e) {
            e.preventDefault();
            
            const optionsList = $(this).closest('.aqm-options-list');
            
            if (optionsList.children().length > 1) {
                $(this).closest('.aqm-option-item').remove();
                
                // Update checkbox values
                optionsList.find('.aqm-option-item').each(function(index) {
                    $(this).find('input[name="option_correct[]"]').val(index);
                });
            } else {
                alert('At least one option is required.');
            }
        });
        
        // Question type change
        $(document).on('change', 'select[name="question_type"]', function() {
            const questionType = $(this).val();
            const questionContainer = $(this).closest('.aqm-question-builder');
            
            if (questionType === 'single_choice') {
                questionContainer.find('.aqm-options-list').addClass('single-choice');
            } else {
                questionContainer.find('.aqm-options-list').removeClass('single-choice');
            }
        });
        
        // Make questions sortable
        if ($('#aqm-questions-container').length) {
            $('#aqm-questions-container').sortable({
                handle: '.aqm-question-header',
                placeholder: 'aqm-question-placeholder',
                update: function() {
                    updateQuestionOrder();
                }
            });
        }
    }
    
    function addNewQuestion() {
        const template = $('#aqm-question-template').html();
        const questionsContainer = $('#aqm-questions-container');
        
        questionsContainer.append(template);
        
        // Focus on the new question text area
        questionsContainer.find('.aqm-question-builder:last-child textarea[name="question_text"]').focus();
    }
    
    function saveQuestion(questionContainer) {
        const campaignId = new URLSearchParams(window.location.search).get('campaign_id');
        const questionId = questionContainer.data('question-id');
        
        if (!campaignId) {
            showNotice('error', 'Campaign ID is required');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'aqm_save_question');
        formData.append('nonce', aqm_ajax.nonce);
        formData.append('campaign_id', campaignId);
        formData.append('question_id', questionId);
        
        // Collect form data
        formData.append('question_text', questionContainer.find('[name="question_text"]').val());
        formData.append('question_type', questionContainer.find('[name="question_type"]').val());
        formData.append('points', questionContainer.find('[name="points"]').val());
        
        // Collect options
        questionContainer.find('[name="option_text[]"]').each(function(index) {
            formData.append('option_text[]', $(this).val());
        });
        
        const correctAnswers = [];
        questionContainer.find('[name="option_correct[]"]:checked').each(function() {
            correctAnswers.push($(this).val());
        });
        
        correctAnswers.forEach(answer => {
            formData.append('option_correct[]', answer);
        });
        
        const saveBtn = questionContainer.find('.aqm-save-question');
        const originalText = saveBtn.text();
        
        $.ajax({
            url: aqm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                saveBtn.prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    
                    // Update question ID if new question
                    if (response.data.question_id && questionId === '0') {
                        questionContainer.data('question-id', response.data.question_id);
                        questionContainer.find('.aqm-question-header h4').text(`Question #${response.data.question_id}`);
                    }
                } else {
                    showNotice('error', response.data.message || 'Failed to save question');
                }
            },
            error: function() {
                showNotice('error', 'Network error occurred');
            },
            complete: function() {
                saveBtn.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function deleteQuestion(questionId, questionContainer) {
        $.ajax({
            url: aqm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aqm_delete_question',
                question_id: questionId,
                nonce: aqm_ajax.nonce
            },
            beforeSend: function() {
                questionContainer.addClass('deleting');
            },
            success: function(response) {
                if (response.success) {
                    questionContainer.fadeOut(300, function() {
                        $(this).remove();
                    });
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message || 'Failed to delete question');
                    questionContainer.removeClass('deleting');
                }
            },
            error: function() {
                showNotice('error', 'Network error occurred');
                questionContainer.removeClass('deleting');
            }
        });
    }
    
    function updateQuestionOrder() {
        const orders = {};
        $('#aqm-questions-container .aqm-question-builder').each(function(index) {
            const questionId = $(this).data('question-id');
            if (questionId && questionId !== '0') {
                orders[questionId] = index + 1;
            }
        });
        
        if (Object.keys(orders).length > 0) {
            const campaignId = new URLSearchParams(window.location.search).get('campaign_id');
            
            $.ajax({
                url: aqm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'aqm_reorder_questions',
                    campaign_id: campaignId,
                    orders: orders,
                    nonce: aqm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showTempNotice('Questions reordered', 2000);
                    }
                }
            });
        }
    }
    
    // GIFT MANAGEMENT
    function initializeGiftManagement() {
        // Save gift
        $(document).on('click', '.aqm-save-gift', function(e) {
            e.preventDefault();
            
            const giftForm = $(this).closest('.aqm-gift-form');
            saveGift(giftForm);
        });
        
        // Delete gift
        $(document).on('click', '.aqm-delete-gift', function(e) {
            e.preventDefault();
            
            const giftId = $(this).data('id');
            const giftTitle = $(this).closest('.aqm-gift-item').find('.gift-title').text();
            
            if (confirm(`Are you sure you want to delete "${giftTitle}"?`)) {
                deleteGift(giftId);
            }
        });
        
        // Add new gift
        $(document).on('click', '#aqm-add-gift', function(e) {
            e.preventDefault();
            showGiftModal();
        });
    }
    
    function saveGift(giftForm) {
        const campaignId = new URLSearchParams(window.location.search).get('campaign_id');
        const giftId = giftForm.data('gift-id') || 0;
        
        const formData = new FormData(giftForm[0]);
        formData.append('action', 'aqm_save_gift');
        formData.append('nonce', aqm_ajax.nonce);
        formData.append('campaign_id', campaignId);
        formData.append('gift_id', giftId);
        
        const saveBtn = giftForm.find('.aqm-save-gift');
        const originalText = saveBtn.text();
        
        $.ajax({
            url: aqm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                saveBtn.prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    location.reload(); // Refresh to show updated gift list
                } else {
                    showNotice('error', response.data.message || 'Failed to save gift');
                }
            },
            error: function() {
                showNotice('error', 'Network error occurred');
            },
            complete: function() {
                saveBtn.prop('disabled', false).text(originalText);
            }
        });
    }
    
    // DATA EXPORT
    $(document).on('click', '.aqm-export-responses', function(e) {
        e.preventDefault();
        
        const campaignId = $(this).data('campaign-id');
        exportResponses(campaignId);
    });
    
    function exportResponses(campaignId) {
        const form = $('<form>', {
            method: 'POST',
            action: aqm_ajax.ajax_url
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'aqm_export_responses'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'campaign_id',
            value: campaignId
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: aqm_ajax.nonce
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        showNotice('info', 'Export started. Download will begin shortly.');
    }
    
    // ANALYTICS
    function initializeAnalytics() {
        // Refresh analytics
        $(document).on('click', '.aqm-refresh-analytics', function(e) {
            e.preventDefault();
            refreshAnalytics();
        });
        
        // Date range picker for analytics
        if ($('.aqm-date-range').length) {
            $('.aqm-date-range').datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
                onSelect: function() {
                    refreshAnalytics();
                }
            });
        }
    }
    
    function refreshAnalytics() {
        const campaignId = new URLSearchParams(window.location.search).get('campaign_id');
        
        if (!campaignId) return;
        
        $.ajax({
            url: aqm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aqm_get_analytics',
                campaign_id: campaignId,
                nonce: aqm_ajax.nonce
            },
            beforeSend: function() {
                $('.aqm-analytics-container').addClass('loading');
            },
            success: function(response) {
                if (response.success) {
                    updateAnalyticsDisplay(response.data);
                } else {
                    showNotice('error', 'Failed to load analytics');
                }
            },
            complete: function() {
                $('.aqm-analytics-container').removeClass('loading');
            }
        });
    }
    
    function updateAnalyticsDisplay(data) {
        // Update statistics cards
        $('.aqm-stat-total-responses').text(data.total_responses || 0);
        $('.aqm-stat-avg-score').text(data.average_score || 0);
        $('.aqm-stat-gifts-claimed').text(data.gifts_claimed || 0);
        $('.aqm-stat-completion-rate').text((data.completion_rate || 0) + '%');
        
        // Update charts if available
        if (typeof Chart !== 'undefined' && data.chart_data) {
            updateCharts(data.chart_data);
        }
    }
    
    // AUTO-SAVE FUNCTIONALITY
    function initializeAutoSave() {
        let autoSaveTimer;
        
        $(document).on('input change', '.aqm-auto-save', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                autoSaveForm();
            }, 3000);
        });
    }
    
    function autoSaveForm() {
        const form = $('.aqm-auto-save').closest('form');
        if (form.length && form.find('[name="campaign_id"]').val()) {
            const formData = new FormData(form[0]);
            formData.append('action', 'aqm_auto_save');
            formData.append('nonce', aqm_ajax.nonce);
            
            $.ajax({
                url: aqm_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showTempNotice('Draft saved', 1500);
                    }
                }
            });
        }
    }
    
    // UI HELPERS
    function initializeDataTables() {
        $('.aqm-data-table').each(function() {
            if ($.fn.DataTable) {
                $(this).DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [[0, 'desc']],
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    }
                });
            }
        });
    }
    
    function initializeTabs() {
        $('.aqm-tabs').each(function() {
            const tabsContainer = $(this);
            const tabs = tabsContainer.find('.aqm-tab');
            const panels = tabsContainer.find('.aqm-tab-panel');
            
            tabs.on('click', function(e) {
                e.preventDefault();
                
                const targetPanel = $(this).data('panel');
                
                tabs.removeClass('active');
                panels.removeClass('active');
                
                $(this).addClass('active');
                $('#' + targetPanel).addClass('active');
            });
        });
    }
    
    function initializeTooltips() {
        $('.aqm-tooltip').each(function() {
            $(this).hover(
                function() {
                    const tooltip = $('<div class="aqm-tooltip-content">' + $(this).data('tooltip') + '</div>');
                    $('body').append(tooltip);
                    
                    const pos = $(this).offset();
                    tooltip.css({
                        top: pos.top - tooltip.outerHeight() - 10,
                        left: pos.left + ($(this).outerWidth() / 2) - (tooltip.outerWidth() / 2)
                    }).fadeIn(200);
                },
                function() {
                    $('.aqm-tooltip-content').remove();
                }
            );
        });
    }
    
    function initializeConfirmDialogs() {
        $('.aqm-confirm').on('click', function(e) {
            const message = $(this).data('confirm') || 'Are you sure?';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    function initializeFormValidation() {
        $('.aqm-form').each(function() {
            const form = $(this);
            
            form.on('submit', function(e) {
                let isValid = true;
                
                // Validate required fields
                form.find('[required]').each(function() {
                    if (!$(this).val().trim()) {
                        $(this).addClass('error');
                        isValid = false;
                    } else {
                        $(this).removeClass('error');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    showNotice('error', 'Please fill in all required fields');
                    
                    // Focus on first error field
                    form.find('.error').first().focus();
                }
            });
            
            // Remove error class on input
            form.find('[required]').on('input', function() {
                if ($(this).val().trim()) {
                    $(this).removeClass('error');
                }
            });
        });
    }
    
    // NOTIFICATION SYSTEM
    function showNotice(type, message) {
        const notice = $(`
            <div class="notice notice-${type} is-dismissible aqm-notice">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.wrap').first().prepend(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            notice.fadeOut();
        }, 5000);
        
        // Manual dismiss
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut();
        });
    }
    
    function showTempNotice(message, duration = 3000) {
        const notice = $(`
            <div class="aqm-temp-notice">
                ${message}
            </div>
        `);
        
        $('body').append(notice);
        
        notice.css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: '#00a32a',
            color: '#fff',
            padding: '10px 15px',
            borderRadius: '4px',
            zIndex: 999999
        }).fadeIn(200);
        
        setTimeout(() => {
            notice.fadeOut(200, function() {
                $(this).remove();
            });
        }, duration);
    }
    
    function showLoadingOverlay() {
        if ($('.aqm-loading-overlay').length === 0) {
            const overlay = $('<div class="aqm-loading-overlay"><div class="aqm-spinner"></div></div>');
            
            overlay.css({
                position: 'fixed',
                top: 0,
                left: 0,
                width: '100%',
                height: '100%',
                background: 'rgba(255, 255, 255, 0.8)',
                zIndex: 999999,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center'
            });
            
            $('body').append(overlay);
        }
    }
    
    function hideLoadingOverlay() {
        $('.aqm-loading-overlay').fadeOut(300, function() {
            $(this).remove();
        });
    }
});