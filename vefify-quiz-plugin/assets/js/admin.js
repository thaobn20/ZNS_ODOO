/**
 * Admin JavaScript
 * File: assets/js/admin.js
 * WordPress admin interface functionality for Vefify Quiz
 */

(function($) {
    'use strict';

    // Admin Application Class
    class VefifyAdmin {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeComponents();
        }

        bindEvents() {
            // Campaign management
            $(document).on('click', '.toggle-campaign', this.toggleCampaign.bind(this));
            $(document).on('click', '.duplicate-campaign', this.duplicateCampaign.bind(this));
            $(document).on('submit', '#campaign-form', this.validateCampaignForm.bind(this));
            
            // Gift management
            $(document).on('click', '.generate-codes', this.generateGiftCodes.bind(this));
            $(document).on('click', '#generate-bulk-codes', this.generateBulkCodes.bind(this));
            $(document).on('click', '#copy-codes', this.copyToClipboard.bind(this));
            $(document).on('click', '.check-inventory', this.checkGiftInventory.bind(this));
            
            // Participant management
            $(document).on('click', '.send-message', this.sendParticipantMessage.bind(this));
            $(document).on('click', '#export-all', this.exportAllParticipants.bind(this));
            $(document).on('submit', '#participants-form', this.handleParticipantBulkActions.bind(this));
            
            // Report generation
            $(document).on('click', '#generate-summary', this.generateSummaryReport.bind(this));
            $(document).on('click', '#export-all-data', this.exportAllData.bind(this));
            
            // Settings management
            $(document).on('click', '#reset-settings', this.resetSettings.bind(this));
            $(document).on('click', '#export-settings', this.exportSettings.bind(this));
            $(document).on('click', '#import-settings', this.importSettings.bind(this));
            $(document).on('click', '#upload-logo', this.uploadLogo.bind(this));
            
            // General UI
            $(document).on('click', '.dismiss-notice', this.dismissNotice.bind(this));
            $(document).on('change', '.auto-save', this.autoSave.bind(this));
            
            // Accessibility
            $(document).on('keydown', this.handleKeyboardNavigation.bind(this));
        }

        initializeComponents() {
            this.initializeDataTables();
            this.initializeCharts();
            this.initializeTooltips();
            this.initializeDatePickers();
            this.initializeColorPickers();
            this.initializeMediaUploader();
            this.initializeNotifications();
        }

        // Campaign Management
        toggleCampaign(e) {
            e.preventDefault();
            const button = $(e.target);
            const campaignId = button.data('campaign-id');
            const currentStatus = button.data('current-status');
            
            button.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'vefify_campaign_action',
                    campaign_id: campaignId,
                    campaign_action: 'toggle_status',
                    current_status: currentStatus,
                    nonce: vefifyAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const newStatus = response.data.new_status;
                        button.text(newStatus ? 'Deactivate' : 'Activate');
                        button.data('current-status', newStatus);
                        this.showNotice('Campaign status updated successfully', 'success');
                    } else {
                        this.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: () => {
                    this.showNotice('Network error occurred', 'error');
                },
                complete: () => {
                    button.prop('disabled', false);
                }
            });
        }

        duplicateCampaign(e) {
            e.preventDefault();
            const campaignId = $(e.target).data('campaign-id');
            
            if (!confirm('Are you sure you want to duplicate this campaign?')) {
                return;
            }
            
            this.showLoading('Duplicating campaign...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'vefify_duplicate_campaign',
                    campaign_id: campaignId,
                    nonce: vefifyAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        this.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: () => {
                    this.showNotice('Network error occurred', 'error');
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        validateCampaignForm(e) {
            const form = $(e.target);
            let isValid = true;
            
            // Clear previous errors
            $('.form-error').remove();
            
            // Validate required fields
            form.find('[required]').each(function() {
                const field = $(this);
                if (!field.val().trim()) {
                    field.after('<span class="form-error" style="color: red; font-size: 12px;">This field is required</span>');
                    isValid = false;
                }
            });
            
            // Validate dates
            const startDate = new Date(form.find('#start_date').val());
            const endDate = new Date(form.find('#end_date').val());
            
            if (endDate <= startDate) {
                form.find('#end_date').after('<span class="form-error" style="color: red; font-size: 12px;">End date must be after start date</span>');
                isValid = false;
            }
            
            // Validate scores
            const passScore = parseInt(form.find('#pass_score').val());
            const questionsPerQuiz = parseInt(form.find('#questions_per_quiz').val());
            
            if (passScore > questionsPerQuiz) {
                form.find('#pass_score').after('<span class="form-error" style="color: red; font-size: 12px;">Pass score cannot be higher than questions per quiz</span>');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                this.showNotice('Please fix the form errors before submitting', 'error');
                // Scroll to first error
                $('html, body').animate({
                    scrollTop: $('.form-error:first').offset().top - 100
                }, 500);
            }
        }

        // Gift Management
        generateGiftCodes(e) {
            e.preventDefault();
            const giftId = $(e.target).data('gift-id');
            
            const quantity = prompt('How many gift codes would you like to generate?', '10');
            if (!quantity || quantity < 1 || quantity > 100) {
                this.showNotice('Please enter a valid quantity (1-100)', 'warning');
                return;
            }
            
            this.showLoading('Generating gift codes...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'vefify_generate_gift_codes',
                    gift_id: giftId,
                    quantity: quantity,
                    nonce: vefifyAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayGeneratedCodes(response.data.codes);
                        this.showNotice(`Successfully generated ${response.data.count} gift codes`, 'success');
                    } else {
                        this.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: () => {
                    this.showNotice('Network error occurred', 'error');
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        generateBulkCodes() {
            const giftId = $('#bulk-gift-select').val();
            const quantity = $('#bulk-quantity').val();
            
            if (!giftId || !quantity) {
                this.showNotice('Please select a gift and enter quantity', 'warning');
                return;
            }
            
            if (quantity > 100) {
                this.showNotice('Maximum 100 codes per batch', 'warning');
                return;
            }
            
            this.showLoading('Generating codes...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'vefify_generate_gift_codes',
                    gift_id: giftId,
                    quantity: quantity,
                    nonce: vefifyAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const codes = response.data.codes.join('\n');
                        $('#codes-output').val(codes);
                        $('#generated-codes-display').show();
                        this.showNotice(`Generated ${response.data.count} codes successfully`, 'success');
                    } else {
                        this.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: () => {
                    this.showNotice('Network error occurred', 'error');
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        copyToClipboard(e) {
            e.preventDefault();
            const textarea = $('#codes-output')[0];
            textarea.select();
            
            try {
                document.execCommand('copy');
                this.showNotice('Codes copied to clipboard!', 'success');
            } catch (err) {
                this.showNotice('Copy failed. Please select and copy manually.', 'error');
            }
        }

        checkGiftInventory(e) {
            e.preventDefault();
            const giftId = $(e.target).data('gift-id');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'vefify_check_gift_inventory',
                    gift_id: giftId,
                    nonce: vefifyAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const inventory = response.data;
                        this.showInventoryModal(inventory);
                    } else {
                        this.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: () => {
                    this.showNotice('Network error occurred', 'error');
                }
            });
        }

        displayGeneratedCodes(codes) {
            const modal = $(`
                <div class="vefify-modal" id="codes-modal">
                    <div class="vefify-modal-content">
                        <div class="vefify-modal-header">
                            <h3>Generated Gift Codes</h3>
                            <span class="vefify-modal-close">&times;</span>
                        </div>
                        <div class="vefify-modal-body">
                            <textarea rows="10" cols="50" readonly>${codes.join('\n')}</textarea>
                            <div class="modal-actions">
                                <button type="button" class="button button-primary" id="copy-modal-codes">Copy All</button>
                                <button type="button" class="button" id="download-csv">Download CSV</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            
            // Bind modal events
            modal.find('.vefify-modal-close, .vefify-modal').on('click', function(e) {
                if (e.target === this) {
                    modal.remove();
                }
            });
            
            modal.find('#copy-modal-codes').on('click', function() {
                modal.find('textarea')[0].select();
                document.execCommand('copy');
                alert('Codes copied to clipboard!');
            });
            
            modal.find('#download-csv').on('click', function() {
                const csvContent = 'Gift Code\n' + codes.join('\n');
                const blob = new Blob([csvContent], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.setAttribute('hidden', '');
                a.setAttribute('href', url);
                a.setAttribute('download', 'gift-codes.csv');
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            });
        }

        showInventoryModal(inventory) {
            const modal = $(`
                <div class="vefify-modal" id="inventory-modal">
                    <div class="vefify-modal-content">
                        <div class="vefify-modal-header">
                            <h3>Inventory Status: ${inventory.gift_name}</h3>
                            <span class="vefify-modal-close">&times;</span>
                        </div>
                        <div class="vefify-modal-body">
                            <table class="widefat">
                                <tr><th>Max Quantity:</th><td>${inventory.max_quantity || 'Unlimited'}</td></tr>
                                <tr><th>Distributed:</th><td>${inventory.distributed}</td></tr>
                                <tr><th>Remaining:</th><td>${inventory.remaining}</td></tr>
                                <tr><th>Status:</th><td><span class="inventory-status ${inventory.status}">${inventory.status.replace('_', ' ').toUpperCase()}</span></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            
            modal.find('.vefify-modal-close, .vefify-modal').on('click', function(e) {
                if (e.target === this) {
                    modal.remove();
                }
            });
        }

        // Participant Management
        sendParticipantMessage(e) {
            e.preventDefault();
            const participantId = $(e.target).data('participant-id');
            const email = $(e.target).data('email');
            
            const subject = prompt('Message Subject:', 'Thank you for participating!');
            if (!subject) return;
            
            const message = prompt('Message:', 'Thank you for taking our quiz!');
            if (!message) return;
            
            this.showLoading('Sending message...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'vefify_participant_action',
                    participant_action: 'send_message',
                    participant_ids: [participantId],
                    subject: subject,
                    message: message,
                    nonce: vefifyAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('Message sent successfully', 'success');
                    } else {
                        this.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: () => {
                    this.showNotice('Network error occurred', 'error');
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        exportAllParticipants() {
            const form = $('<form>', {
                method: 'POST',
                action: ajaxurl
            }).append(
                $('<input>', { type: 'hidden', name: 'action', value: 'vefify_export_participants' }),
                $('<input>', { type: 'hidden', name: 'nonce', value: vefifyAjax.nonce }),
                $('<input>', { type: 'hidden', name: 'status', value: 'all' })
            );
            
            $('body').append(form);
            form.submit();
            form.remove();
            
            this.showNotice('Export started. Download will begin shortly.', 'info');
        }

        handleParticipantBulkActions(e) {
            const form = $(e.target);
            const action = form.find('[name="bulk_action"]').val();
            const selectedIds = form.find('input[name="participant_ids[]"]:checked').map(function() {
                return this.value;
            }).get();
            
            if (action === '-1') {
                e.preventDefault();
                this.showNotice('Please select an action', 'warning');
                return;
            }
            
            if (selectedIds.length === 0) {
                e.preventDefault();
                this.showNotice('Please select participants', 'warning');
                return;
            }
            
            if (!confirm(`Are you sure you want to ${action} ${selectedIds.length} participants?`)) {
                e.preventDefault();
                return;
            }
        }

        // Report Generation
        generateSummaryReport() {
            this.showLoading('Generating summary report...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'vefify_generate_report',
                    report_type: 'comprehensive',
                    parameters: { date_range: '30days' },
                    nonce: vefifyAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayReportModal(response.data);
                    } else {
                        this.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: () => {
                    this.showNotice('Network error occurred', 'error');
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        exportAllData() {
            if (!confirm('This will export all platform data. Continue?')) {
                return;
            }
            
            const form = $('<form>', {
                method: 'POST',
                action: ajaxurl
            }).append(
                $('<input>', { type: 'hidden', name: 'action', value: 'vefify_export_all_data' }),
                $('<input>', { type: 'hidden', name: 'nonce', value: vefifyAjax.nonce })
            );
            
            $('body').append(form);
            form.submit();
            form.remove();
            
            this.showNotice('Data export started. This may take a few minutes.', 'info');
        }

        displayReportModal(reportData) {
            const modal = $(`
                <div class="vefify-modal vefify-modal-large" id="report-modal">
                    <div class="vefify-modal-content">
                        <div class="vefify-modal-header">
                            <h3>Summary Report</h3>
                            <span class="vefify-modal-close">&times;</span>
                        </div>
                        <div class="vefify-modal-body">
                            ${this.formatReportHtml(reportData)}
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            
            modal.find('.vefify-modal-close, .vefify-modal').on('click', function(e) {
                if (e.target === this) {
                    modal.remove();
                }
            });
        }

        formatReportHtml(data) {
            return `
                <div class="report-summary">
                    <h4>Platform Overview</h4>
                    <div class="report-stats">
                        <div class="stat-item">
                            <strong>${data.overview.total_campaigns}</strong>
                            <span>Total Campaigns</span>
                        </div>
                        <div class="stat-item">
                            <strong>${data.overview.total_participants}</strong>
                            <span>Total Participants</span>
                        </div>
                        <div class="stat-item">
                            <strong>${data.overview.completed_quizzes}</strong>
                            <span>Completed Quizzes</span>
                        </div>
                        <div class="stat-item">
                            <strong>${data.overview.gifts_distributed}</strong>
                            <span>Gifts Distributed</span>
                        </div>
                    </div>
                </div>
            `;
        }

        // Settings Management
        resetSettings() {
            if (!confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
                return;
            }
            
            this.showLoading('Resetting settings...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'vefify_reset_settings',
                    nonce: vefifyAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        this.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: () => {
                    this.showNotice('Network error occurred', 'error');
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        exportSettings() {
            const form = $('<form>', {
                method: 'POST',
                action: ajaxurl
            }).append(
                $('<input>', { type: 'hidden', name: 'action', value: 'vefify_export_settings' }),
                $('<input>', { type: 'hidden', name: 'nonce', value: vefifyAjax.nonce })
            );
            
            $('body').append(form);
            form.submit();
            form.remove();
        }

        importSettings() {
            const input = $('<input type="file" accept=".json" style="display: none;">');
            $('body').append(input);
            
            input.on('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        const settings = JSON.parse(e.target.result);
                        this.processSettingsImport(settings);
                    } catch (error) {
                        this.showNotice('Invalid settings file', 'error');
                    }
                };
                reader.readAsText(file);
            });
            
            input.click();
            input.remove();
        }

        processSettingsImport(settings) {
            if (!confirm('This will overwrite current settings. Continue?')) {
                return;
            }
            
            this.showLoading('Importing settings...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'vefify_import_settings',
                    settings: settings,
                    nonce: vefifyAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        this.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: () => {
                    this.showNotice('Network error occurred', 'error');
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        uploadLogo() {
            if (typeof wp !== 'undefined' && wp.media) {
                const frame = wp.media({
                    title: 'Select Logo',
                    button: { text: 'Use this logo' },
                    multiple: false,
                    library: { type: 'image' }
                });
                
                frame.on('select', () => {
                    const attachment = frame.state().get('selection').first().toJSON();
                    $('[name="vefify_appearance_settings[logo_url]"]').val(attachment.url);
                });
                
                frame.open();
            } else {
                this.showNotice('Media uploader not available', 'error');
            }
        }

        // Component Initialization
        initializeDataTables() {
            if ($.fn.DataTable) {
                $('.wp-list-table.campaigns, .wp-list-table.participants').DataTable({
                    pageLength: 20,
                    responsive: true,
                    order: [[1, 'desc']],
                    language: {
                        search: 'Search:',
                        lengthMenu: 'Show _MENU_ entries',
                        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                        paginate: {
                            first: 'First',
                            last: 'Last',
                            next: 'Next',
                            previous: 'Previous'
                        }
                    }
                });
            }
        }

        initializeCharts() {
            // Initialize Chart.js charts if present
            if (typeof Chart !== 'undefined') {
                this.initializeTrendsChart();
                this.initializeDistributionCharts();
            }
        }

        initializeTrendsChart() {
            const canvas = document.getElementById('trends-chart');
            if (!canvas) return;
            
            // Chart initialization would go here
            // This is handled in the PHP template with inline JavaScript
        }

        initializeDistributionCharts() {
            const canvases = document.querySelectorAll('.distribution-chart');
            canvases.forEach(canvas => {
                // Individual chart initialization
            });
        }

        initializeTooltips() {
            $('[data-tooltip]').each(function() {
                const element = $(this);
                const text = element.data('tooltip');
                
                element.attr('title', text).tooltip({
                    position: { my: 'center bottom-20', at: 'center top' },
                    show: { delay: 500 },
                    hide: { delay: 100 }
                });
            });
        }

        initializeDatePickers() {
            if ($.fn.datepicker) {
                $('input[type="datetime-local"]').each(function() {
                    // Enhanced date picker functionality
                    $(this).on('change', function() {
                        $(this).removeClass('error');
                    });
                });
            }
        }

        initializeColorPickers() {
            if ($.fn.wpColorPicker) {
                $('input[type="color"]').wpColorPicker();
            }
        }

        initializeMediaUploader() {
            // Media uploader functionality is handled in uploadLogo method
        }

        initializeNotifications() {
            // Auto-hide notices after 5 seconds
            $('.notice.is-dismissible').delay(5000).fadeOut();
        }

        // Utility Functions
        showLoading(message = 'Loading...') {
            if (!$('#vefify-loading').length) {
                $('body').append(`
                    <div id="vefify-loading" class="vefify-loading-overlay">
                        <div class="vefify-loading-content">
                            <div class="spinner is-active"></div>
                            <p>${message}</p>
                        </div>
                    </div>
                `);
            } else {
                $('#vefify-loading p').text(message);
            }
        }

        hideLoading() {
            $('#vefify-loading').remove();
        }

        showNotice(message, type = 'info') {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible vefify-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.wrap > h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);
        }

        dismissNotice(e) {
            $(e.target).closest('.notice').fadeOut(() => {
                $(e.target).closest('.notice').remove();
            });
        }

        autoSave() {
            // Auto-save functionality for forms
            const form = $(this).closest('form');
            clearTimeout(form.data('autoSaveTimeout'));
            
            form.data('autoSaveTimeout', setTimeout(() => {
                // Perform auto-save
                this.performAutoSave(form);
            }, 2000));
        }

        performAutoSave(form) {
            const formData = form.serialize();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=vefify_auto_save&nonce=' + vefifyAjax.nonce,
                success: (response) => {
                    if (response.success) {
                        this.showTemporaryMessage('Draft saved', 'success');
                    }
                }
            });
        }

        showTemporaryMessage(message, type) {
            const messageEl = $(`<div class="temporary-message ${type}">${message}</div>`);
            $('body').append(messageEl);
            
            setTimeout(() => {
                messageEl.fadeOut(() => messageEl.remove());
            }, 2000);
        }

        handleKeyboardNavigation(e) {
            // Implement keyboard shortcuts
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 's':
                        e.preventDefault();
                        $('form:visible').submit();
                        break;
                    case 'n':
                        e.preventDefault();
                        const newButton = $('.page-title-action:visible:first');
                        if (newButton.length) {
                            window.location.href = newButton.attr('href');
                        }
                        break;
                }
            }
        }
    }

    // Initialize when document ready
    $(document).ready(function() {
        window.vefifyAdmin = new VefifyAdmin();
        
        // Add loading styles if not present
        if (!$('#vefify-admin-styles').length) {
            $('head').append(`
                <style id="vefify-admin-styles">
                .vefify-loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .vefify-loading-content {
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    text-align: center;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                }
                .vefify-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .vefify-modal-content {
                    background: white;
                    border-radius: 8px;
                    max-width: 500px;
                    width: 90%;
                    max-height: 80%;
                    overflow-y: auto;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                }
                .vefify-modal-large .vefify-modal-content {
                    max-width: 800px;
                }
                .vefify-modal-header {
                    padding: 20px;
                    border-bottom: 1px solid #e0e0e0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .vefify-modal-close {
                    font-size: 24px;
                    cursor: pointer;
                    color: #666;
                }
                .vefify-modal-close:hover {
                    color: #333;
                }
                .vefify-modal-body {
                    padding: 20px;
                }
                .temporary-message {
                    position: fixed;
                    top: 32px;
                    right: 20px;
                    padding: 10px 15px;
                    border-radius: 4px;
                    color: white;
                    z-index: 999999;
                    font-weight: bold;
                }
                .temporary-message.success {
                    background: #46b450;
                }
                .temporary-message.error {
                    background: #dc3232;
                }
                .report-stats {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 15px;
                    margin: 20px 0;
                }
                .stat-item {
                    text-align: center;
                    padding: 15px;
                    background: #f9f9f9;
                    border-radius: 4px;
                }
                .stat-item strong {
                    display: block;
                    font-size: 24px;
                    color: #0073aa;
                    margin-bottom: 5px;
                }
                </style>
            `);
        }
    });

})(jQuery);