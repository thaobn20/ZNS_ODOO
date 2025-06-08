// Admin JavaScript for Advanced Quiz Manager
// File: assets/js/admin.js

jQuery(document).ready(function($) {
    
    // Initialize admin functionality
    initializeAdmin();
    
    function initializeAdmin() {
        initializeCampaignManagement();
        initializeQuestionBuilder();
        initializeDataTables();
        initializeDragAndDrop();
        initializeAnalytics();
        initializeFileUploads();
        initializeTooltips();
        initializeConfirmDialogs();
    }
    
    // Campaign Management
    function initializeCampaignManagement() {
        // Delete campaign confirmation
        $(document).on('click', '.aqm-delete-campaign', function(e) {
            e.preventDefault();
            
            const campaignId = $(this).data('id');
            const campaignTitle = $(this).closest('tr').find('strong a').text();
            
            if (confirm(`Are you sure you want to delete the campaign "${campaignTitle}"? This action cannot be undone.`)) {
                deleteCampaign(campaignId);
            }
        });
        
        // Campaign status toggle
        $(document).on('change', '.aqm-campaign-status-toggle', function() {
            const campaignId = $(this).data('id');
            const newStatus = $(this).val();
            updateCampaignStatus(campaignId, newStatus);
        });
        
        // Auto-save campaign drafts
        let autoSaveTimer;
        $(document).on('input change', '#aqm-campaign-form input, #aqm-campaign-form textarea, #aqm-campaign-form select', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                autoSaveCampaign();
            }, 3000);
        });
    }
    
    function deleteCampaign(campaignId) {
        $.ajax({
            url: ajaxurl,
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
    
    function updateCampaignStatus(campaignId, status) {
        $.ajax({
            url: ajaxurl,
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
    
    function autoSaveCampaign() {
        const form = $('#aqm-campaign-form');
        if (form.length && form.find('[name="campaign_id"]').val()) {
            const formData = new FormData(form[0]);
            formData.append('action', 'aqm_auto_save_campaign');
            formData.append('nonce', aqm_ajax.nonce);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showTempNotice('Draft saved', 2000);
                    }
                }
            });
        }
    }
    
    // Question Builder
    function initializeQuestionBuilder() {
        // Add new question
        $(document).on('click', '.aqm-add-question', function(e) {
            e.preventDefault();
            addNewQuestion();
        });
        
        // Delete question
        $(document).on('click', '.aqm-delete-question', function(e) {
            e.preventDefault();
            
            const questionContainer = $(this).closest('.aqm-question-builder');
            const questionId = questionContainer.data('question-id');
            
            if (confirm('Are you sure you want to delete this question?')) {
                if (questionId) {
                    deleteQuestion(questionId);
                } else {
                    questionContainer.fadeOut(300, function() {
                        $(this).remove();
                        updateQuestionOrder();
                    });
                }
            }
        });
        
        // Question type change
        $(document).on('change', '.aqm-question-type', function() {
            const questionContainer = $(this).closest('.aqm-question-builder');
            const questionType = $(this).val();
            updateQuestionOptions(questionContainer, questionType);
        });
        
        // Add option for multiple choice questions
        $(document).on('click', '.aqm-add-option', function(e) {
            e.preventDefault();
            addQuestionOption($(this).closest('.aqm-question-options'));
        });
        
        // Remove option
        $(document).on('click', '.aqm-remove-option', function(e) {
            e.preventDefault();
            $(this).closest('.aqm-option-item').fadeOut(200, function() {
                $(this).remove();
            });
        });
        
        // Make questions sortable
        if ($('.aqm-questions-container').length) {
            $('.aqm-questions-container').sortable({
                handle: '.aqm-drag-handle',
                placeholder: 'aqm-question-placeholder',
                update: function() {
                    updateQuestionOrder();
                }
            });
        }
    }
    
    function addNewQuestion() {
        const questionTemplate = `
            <div class="aqm-question-builder" data-question-id="">
                <div class="aqm-question-header">
                    <h4>
                        <span class="aqm-drag-handle dashicons dashicons-menu"></span>
                        New Question
                    </h4>
                    <div class="aqm-question-controls">
                        <button type="button" class="button aqm-toggle-question">Collapse</button>
                        <button type="button" class="button aqm-delete-question">Delete</button>
                    </div>
                </div>
                <div class="aqm-question-content">
                    <div class="aqm-question-field">
                        <label>Question Text *</label>
                        <textarea name="question_text" rows="3" required></textarea>
                    </div>
                    <div class="aqm-question-field">
                        <label>Question Type *</label>
                        <select name="question_type" class="aqm-question-type" required>
                            <option value="text">Text Input</option>
                            <option value="email">Email</option>
                            <option value="phone">Phone Number</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="rating">Rating (Stars)</option>
                            <option value="provinces">Vietnamese Provinces</option>
                            <option value="districts">Districts</option>
                            <option value="wards">Wards</option>
                        </select>
                    </div>
                    <div class="aqm-question-field">
                        <label>
                            <input type="checkbox" name="is_required" value="1">
                            Required Question
                        </label>
                    </div>
                    <div class="aqm-question-field">
                        <label>Points (for scoring)</label>
                        <input type="number" name="points" value="0" min="0">
                    </div>
                    <div class="aqm-question-options-container" style="display: none;">
                        <label>Answer Options</label>
                        <div class="aqm-question-options">
                            <!-- Options will be added here -->
                        </div>
                        <button type="button" class="button aqm-add-option">Add Option</button>
                    </div>
                </div>
            </div>
        `;
        
        $('.aqm-questions-container').append(questionTemplate);
        updateQuestionOrder();
    }
    
    function updateQuestionOptions(questionContainer, questionType) {
        const optionsContainer = questionContainer.find('.aqm-question-options-container');
        const optionsDiv = questionContainer.find('.aqm-question-options');
        
        // Clear existing options
        optionsDiv.empty();
        
        if (questionType === 'multiple_choice') {
            optionsContainer.show();
            // Add default options
            addQuestionOption(optionsDiv);
            addQuestionOption(optionsDiv);
        } else if (questionType === 'rating') {
            optionsContainer.show();
            optionsDiv.html(`
                <div class="aqm-option-item">
                    <label>Maximum Rating:</label>
                    <input type="number" name="max_rating" value="5" min="1" max="10">
                </div>
                <div class="aqm-option-item">
                    <label>Rating Icon:</label>
                    <select name="rating_icon">
                        <option value="star">Star (★)</option>
                        <option value="heart">Heart (♥)</option>
                        <option value="thumb">Thumbs Up (👍)</option>
                    </select>
                </div>
            `);
        } else if (questionType === 'provinces') {
            optionsContainer.show();
            optionsDiv.html(`
                <div class="aqm-option-item">
                    <label>
                        <input type="checkbox" name="load_districts" value="1" checked>
                        Also load districts
                    </label>
                </div>
                <div class="aqm-option-item">
                    <label>
                        <input type="checkbox" name="load_wards" value="1">
                        Also load wards/communes
                    </label>
                </div>
                <div class="aqm-option-item">
                    <label>Placeholder Text:</label>
                    <input type="text" name="placeholder" value="Select your province">
                </div>
            `);
        } else {
            optionsContainer.hide();
        }
    }
    
    function addQuestionOption(optionsContainer) {
        const optionHtml = `
            <div class="aqm-option-item">
                <input type="text" name="option_text[]" placeholder="Option text" required>
                <input type="text" name="option_value[]" placeholder="Value" required>
                <button type="button" class="button aqm-remove-option">Remove</button>
            </div>
        `;
        optionsContainer.append(optionHtml);
    }
    
    function updateQuestionOrder() {
        $('.aqm-question-builder').each(function(index) {
            $(this).find('.aqm-question-header h4').html(
                '<span class="aqm-drag-handle dashicons dashicons-menu"></span>' +
                'Question ' + (index + 1)
            );
        });
    }
    
    function deleteQuestion(questionId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aqm_delete_question',
                question_id: questionId,
                nonce: aqm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $(`[data-question-id="${questionId}"]`).fadeOut(300, function() {
                        $(this).remove();
                        updateQuestionOrder();
                    });
                    showNotice('success', 'Question deleted successfully');
                } else {
                    showNotice('error', response.data.message || 'Failed to delete question');
                }
            }
        });
    }
    
    // Data Tables Enhancement
    function initializeDataTables() {
        // Add search functionality
        $('.aqm-table-search').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            const table = $(this).closest('.aqm-table-container').find('table tbody');
            
            table.find('tr').each(function() {
                const rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.includes(searchTerm));
            });
        });
        
        // Add sorting functionality
        $('.aqm-sortable-header').on('click', function() {
            const column = $(this).data('column');
            const table = $(this).closest('table');
            const tbody = table.find('tbody');
            const rows = tbody.find('tr').toArray();
            
            const isAscending = !$(this).hasClass('sorted-asc');
            
            // Remove existing sort classes
            table.find('.aqm-sortable-header').removeClass('sorted-asc sorted-desc');
            
            // Add new sort class
            $(this).addClass(isAscending ? 'sorted-asc' : 'sorted-desc');
            
            rows.sort(function(a, b) {
                const aText = $(a).find(`td:eq(${column})`).text().trim();
                const bText = $(b).find(`td:eq(${column})`).text().trim();
                
                if (isAscending) {
                    return aText.localeCompare(bText);
                } else {
                    return bText.localeCompare(aText);
                }
            });
            
            tbody.empty().append(rows);
        });
    }
    
    // Drag and Drop File Uploads
    function initializeDragAndDrop() {
        $('.aqm-upload-area').on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });
        
        $('.aqm-upload-area').on('dragleave dragend', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });
        
        $('.aqm-upload-area').on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            
            const files = e.originalEvent.dataTransfer.files;
            const fileInput = $(this).find('input[type="file"]');
            
            if (files.length > 0) {
                fileInput[0].files = files;
                fileInput.trigger('change');
            }
        });
    }
    
    // Analytics Dashboard
    function initializeAnalytics() {
        // Load analytics data
        if ($('.aqm-analytics-container').length) {
            loadAnalyticsData();
        }
        
        // Refresh analytics
        $(document).on('click', '.aqm-refresh-analytics', function(e) {
            e.preventDefault();
            loadAnalyticsData();
        });
        
        // Export data
        $(document).on('click', '.aqm-export-data', function(e) {
            e.preventDefault();
            
            const campaignId = $(this).data('campaign-id');
            const format = $(this).data('format') || 'csv';
            
            exportAnalyticsData(campaignId, format);
        });
    }
    
    function loadAnalyticsData() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aqm_get_analytics_data',
                nonce: aqm_ajax.nonce
            },
            beforeSend: function() {
                $('.aqm-analytics-container').addClass('aqm-loading');
            },
            success: function(response) {
                if (response.success) {
                    renderAnalyticsCharts(response.data);
                } else {
                    showNotice('error', 'Failed to load analytics data');
                }
            },
            complete: function() {
                $('.aqm-analytics-container').removeClass('aqm-loading');
            }
        });
    }
    
    function renderAnalyticsCharts(data) {
        // Render participation chart
        if (data.participation && $('#participation-chart').length) {
            renderParticipationChart(data.participation);
        }
        
        // Render completion rates
        if (data.completion_rates && $('#completion-chart').length) {
            renderCompletionChart(data.completion_rates);
        }
        
        // Render province distribution
        if (data.province_distribution && $('#province-chart').length) {
            renderProvinceChart(data.province_distribution);
        }
    }
    
    function renderParticipationChart(data) {
        // This would integrate with Chart.js or similar
        // For now, just update the numbers
        $('#total-participants').text(data.total);
        $('#todays-participants').text(data.today);
        $('#weekly-participants').text(data.week);
    }
    
    function exportAnalyticsData(campaignId, format) {
        const form = $('<form>', {
            'method': 'POST',
            'action': ajaxurl
        });
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'action',
            'value': 'aqm_export_responses'
        }));
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'campaign_id',
            'value': campaignId
        }));
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'format',
            'value': format
        }));
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'nonce',
            'value': aqm_ajax.nonce
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
    }
    
    // File Upload Handlers
    function initializeFileUploads() {
        // Handle file input changes
        $(document).on('change', 'input[type="file"]', function() {
            const file = this.files[0];
            const container = $(this).closest('.aqm-upload-area');
            
            if (file) {
                const fileName = file.name;
                const fileSize = formatFileSize(file.size);
                
                container.find('.aqm-upload-text').html(`
                    <strong>${fileName}</strong><br>
                    <small>${fileSize}</small>
                `);
                
                // Validate file type if needed
                const allowedTypes = $(this).data('allowed-types');
                if (allowedTypes) {
                    const fileType = file.type || file.name.split('.').pop();
                    if (!allowedTypes.includes(fileType)) {
                        showNotice('error', `File type ${fileType} is not allowed`);
                        $(this).val('');
                        return;
                    }
                }
                
                // Auto-upload if specified
                if ($(this).data('auto-upload')) {
                    uploadFile(this, file);
                }
            }
        });
    }
    
    function uploadFile(input, file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', $(input).data('upload-action') || 'aqm_upload_file');
        formData.append('nonce', aqm_ajax.nonce);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        updateUploadProgress(input, percentComplete);
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'File uploaded successfully');
                    $(input).trigger('aqm:upload:success', [response.data]);
                } else {
                    showNotice('error', response.data.message || 'Upload failed');
                }
            },
            error: function() {
                showNotice('error', 'Network error during upload');
            }
        });
    }
    
    function updateUploadProgress(input, percent) {
        const container = $(input).closest('.aqm-upload-area');
        let progressBar = container.find('.aqm-progress-bar');
        
        if (progressBar.length === 0) {
            progressBar = $('<div class="aqm-progress-bar"><div class="aqm-progress-fill"></div></div>');
            container.append(progressBar);
        }
        
        progressBar.find('.aqm-progress-fill').css('width', percent + '%');
        
        if (percent >= 100) {
            setTimeout(() => {
                progressBar.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 1000);
        }
    }
    
    // Tooltips
    function initializeTooltips() {
        // Initialize tooltips for elements with data-tooltip attribute
        $(document).on('mouseenter', '[data-tooltip]', function() {
            const tooltip = $(this).attr('data-tooltip');
            if (tooltip) {
                showTooltip($(this), tooltip);
            }
        });
        
        $(document).on('mouseleave', '[data-tooltip]', function() {
            hideTooltip();
        });
    }
    
    function showTooltip(element, text) {
        const tooltip = $('<div class="aqm-tooltip-popup">' + text + '</div>');
        $('body').append(tooltip);
        
        const offset = element.offset();
        const elementHeight = element.outerHeight();
        
        tooltip.css({
            position: 'absolute',
            top: offset.top - tooltip.outerHeight() - 5,
            left: offset.left + (element.outerWidth() / 2) - (tooltip.outerWidth() / 2),
            zIndex: 10000
        });
        
        tooltip.fadeIn(200);
    }
    
    function hideTooltip() {
        $('.aqm-tooltip-popup').fadeOut(200, function() {
            $(this).remove();
        });
    }
    
    // Confirmation Dialogs
    function initializeConfirmDialogs() {
        $(document).on('click', '[data-confirm]', function(e) {
            const message = $(this).data('confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Utility Functions
    function showNotice(type, message) {
        const notice = $(`
            <div class="aqm-notice aqm-notice-${type}">
                ${message}
                <button type="button" class="aqm-notice-dismiss">&times;</button>
            </div>
        `);
        
        $('.aqm-admin-page').prepend(notice);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual dismiss
        notice.find('.aqm-notice-dismiss').on('click', function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    function showTempNotice(message, duration = 3000) {
        const notice = $(`<div class="aqm-temp-notice">${message}</div>`);
        
        notice.css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: '#2271b1',
            color: '#fff',
            padding: '10px 15px',
            borderRadius: '4px',
            zIndex: 10000,
            fontSize: '14px'
        });
        
        $('body').append(notice);
        
        setTimeout(() => {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, duration);
    }
    
    function showLoadingOverlay() {
        if ($('.aqm-loading-overlay').length === 0) {
            const overlay = $(`
                <div class="aqm-loading-overlay">
                    <div class="aqm-loading-spinner"></div>
                </div>
            `);
            
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
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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
    
    // Initialize on page load
    $(window).on('load', function() {
        // Fade out any loading states
        $('.aqm-loading').removeClass('aqm-loading');
        
        // Initialize any charts or complex widgets
        if (typeof Chart !== 'undefined') {
            initializeCharts();
        }
    });
    
    function initializeCharts() {
        // Initialize Chart.js charts if available
        $('.aqm-chart-canvas').each(function() {
            const canvas = this;
            const chartType = $(canvas).data('chart-type');
            const chartData = $(canvas).data('chart-data');
            
            if (chartData) {
                new Chart(canvas, {
                    type: chartType,
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
        });
    }
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl+S to save forms
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            const activeForm = $('.aqm-form:visible');
            if (activeForm.length) {
                e.preventDefault();
                activeForm.find('input[type="submit"]').click();
            }
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            $('.aqm-modal:visible').fadeOut();
            hideTooltip();
        }
    });
    
    // Auto-refresh data every 5 minutes on dashboard
    if ($('body').hasClass('toplevel_page_quiz-manager')) {
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                // Refresh dashboard stats silently
                refreshDashboardStats();
            }
        }, 300000); // 5 minutes
    }
    
    function refreshDashboardStats() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aqm_refresh_dashboard_stats',
                nonce: aqm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateDashboardStats(response.data);
                }
            }
        });
    }
    
    function updateDashboardStats(stats) {
        // Update stat cards with new data
        $('.aqm-stat-card').each(function() {
            const statType = $(this).data('stat-type');
            if (stats[statType]) {
                $(this).find('h3').text(stats[statType]);
            }
        });
    }
});