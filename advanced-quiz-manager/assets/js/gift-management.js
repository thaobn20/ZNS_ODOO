/**
 * Gift Management JavaScript
 * Handles all frontend interactions for gift management
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Gift Management Object
    const GiftManager = {
        
        // Initialize the gift management interface
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },
        
        // Bind all event handlers
        bindEvents: function() {
            // Modal controls
            $('#add-new-gift, #add-first-gift').on('click', this.openGiftModal.bind(this));
            $('.aqm-modal-close').on('click', this.closeGiftModal.bind(this));
            $(document).on('click', '.aqm-modal', this.handleModalBackdropClick.bind(this));
            
            // Gift CRUD operations
            $(document).on('click', '.edit-gift', this.editGift.bind(this));
            $(document).on('click', '.delete-gift', this.deleteGift.bind(this));
            $(document).on('click', '.duplicate-gift', this.duplicateGift.bind(this));
            
            // Form submission
            $('#gift-form').on('submit', this.saveGift.bind(this));
            
            // Gift actions
            $(document).on('click', '.generate-codes', this.generateCodes.bind(this));
            $(document).on('click', '.copy-code', this.copyGiftCode.bind(this));
            $(document).on('click', '.revoke-award', this.revokeAward.bind(this));
            
            // Bulk actions
            $('#export-awards').on('click', this.exportAwards.bind(this));
            $('#bulk-generate-codes').on('click', this.bulkGenerateCodes.bind(this));
            
            // Form field interactions
            $('#gift-type').on('change', this.handleGiftTypeChange.bind(this));
            $('#campaign-id').on('change', this.handleCampaignChange.bind(this));
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts.bind(this));
        },
        
        // Initialize components
        initializeComponents: function() {
            this.setupDateTimeInputs();
            this.setupTooltips();
            this.setupValidation();
        },
        
        // Open gift modal for create/edit
        openGiftModal: function(giftId = null) {
            if (giftId) {
                this.loadGiftData(giftId);
                $('#gift-form-title').text('Edit Gift');
            } else {
                this.resetGiftForm();
                $('#gift-form-title').text('Add New Gift');
            }
            
            $('#gift-form-modal').fadeIn(300);
            $('body').addClass('modal-open');
            
            // Focus first input
            setTimeout(() => {
                $('#campaign-id').focus();
            }, 300);
        },
        
        // Close gift modal
        closeGiftModal: function() {
            $('#gift-form-modal').fadeOut(300);
            $('body').removeClass('modal-open');
            this.clearFormErrors();
        },
        
        // Handle modal backdrop clicks
        handleModalBackdropClick: function(e) {
            if (e.target === e.currentTarget) {
                this.closeGiftModal();
            }
        },
        
        // Reset gift form
        resetGiftForm: function() {
            $('#gift-form')[0].reset();
            $('#gift-id').val('');
            $('#is-active').prop('checked', true);
            this.clearFormErrors();
            this.handleGiftTypeChange();
        },
        
        // Load gift data for editing
        loadGiftData: function(giftId) {
            const self = this;
            
            this.showFormLoading(true);
            
            $.ajax({
                url: aqm_gifts.ajax_url,
                type: 'POST',
                data: {
                    action: 'aqm_get_gift',
                    nonce: aqm_gifts.nonce,
                    gift_id: giftId
                },
                success: function(response) {
                    self.showFormLoading(false);
                    
                    if (response.success) {
                        self.populateForm(response.data);
                    } else {
                        self.showNotification('Error loading gift data: ' + response.data, 'error');
                        self.closeGiftModal();
                    }
                },
                error: function() {
                    self.showFormLoading(false);
                    self.showNotification('Network error occurred', 'error');
                    self.closeGiftModal();
                }
            });
        },
        
        // Populate form with gift data
        populateForm: function(gift) {
            $('#gift-id').val(gift.id);
            $('#campaign-id').val(gift.campaign_id);
            $('#gift-name').val(gift.gift_name);
            $('#gift-type').val(gift.gift_type);
            $('#gift-value').val(gift.gift_value);
            $('#gift-description').val(gift.description);
            $('#quantity-total').val(gift.quantity_total);
            $('#min-score').val(gift.min_score);
            $('#max-score').val(gift.max_score);
            $('#probability').val(gift.probability);
            $('#valid-from').val(gift.valid_from);
            $('#valid-until').val(gift.valid_until);
            $('#gift-code-prefix').val(gift.gift_code_prefix);
            $('#is-active').prop('checked', gift.is_active == 1);
            
            this.handleGiftTypeChange();
        },
        
        // Save gift (create or update)
        saveGift: function(e) {
            e.preventDefault();
            
            if (!this.validateForm()) {
                return;
            }
            
            const formData = $('#gift-form').serialize();
            const self = this;
            
            this.showFormLoading(true);
            
            $.ajax({
                url: aqm_gifts.ajax_url,
                type: 'POST',
                data: formData + '&action=aqm_save_gift&nonce=' + aqm_gifts.nonce,
                success: function(response) {
                    self.showFormLoading(false);
                    
                    if (response.success) {
                        self.showNotification(response.data.message, 'success');
                        self.closeGiftModal();
                        self.refreshGiftsList();
                    } else {
                        self.showFormError(response.data);
                    }
                },
                error: function() {
                    self.showFormLoading(false);
                    self.showFormError('Network error occurred. Please try again.');
                }
            });
        },
        
        // Edit gift
        editGift: function(e) {
            e.preventDefault();
            const giftId = $(e.currentTarget).data('gift-id');
            this.openGiftModal(giftId);
        },
        
        // Delete gift
        deleteGift: function(e) {
            e.preventDefault();
            
            if (!confirm(aqm_gifts.confirm_delete)) {
                return;
            }
            
            const giftId = $(e.currentTarget).data('gift-id');
            const $row = $(e.currentTarget).closest('tr');
            const self = this;
            
            $.ajax({
                url: aqm_gifts.ajax_url,
                type: 'POST',
                data: {
                    action: 'aqm_delete_gift',
                    nonce: aqm_gifts.nonce,
                    gift_id: giftId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            self.updateStatsAfterDelete();
                        });
                        self.showNotification('Gift deleted successfully', 'success');
                    } else {
                        self.showNotification('Error: ' + response.data, 'error');
                    }
                },
                error: function() {
                    self.showNotification('Network error occurred', 'error');
                }
            });
        },
        
        // Duplicate gift
        duplicateGift: function(e) {
            e.preventDefault();
            
            const giftId = $(e.currentTarget).data('gift-id');
            const self = this;
            
            $.ajax({
                url: aqm_gifts.ajax_url,
                type: 'POST',
                data: {
                    action: 'aqm_duplicate_gift',
                    nonce: aqm_gifts.nonce,
                    gift_id: giftId
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('Gift duplicated successfully', 'success');
                        self.refreshGiftsList();
                    } else {
                        self.showNotification('Error: ' + response.data, 'error');
                    }
                },
                error: function() {
                    self.showNotification('Network error occurred', 'error');
                }
            });
        },
        
        // Generate gift codes
        generateCodes: function(e) {
            e.preventDefault();
            
            const giftId = $(e.currentTarget).data('gift-id');
            const quantity = prompt('How many gift codes would you like to generate?', '10');
            
            if (!quantity || isNaN(quantity) || quantity <= 0) {
                return;
            }
            
            const self = this;
            
            $.ajax({
                url: aqm_gifts.ajax_url,
                type: 'POST',
                data: {
                    action: 'aqm_generate_gift_codes',
                    nonce: aqm_gifts.nonce,
                    gift_id: giftId,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success) {
                        self.showCodesModal(response.data.codes);
                    } else {
                        self.showNotification('Error: ' + response.data, 'error');
                    }
                },
                error: function() {
                    self.showNotification('Network error occurred', 'error');
                }
            });
        },
        
        // Copy gift code to clipboard
        copyGiftCode: function(e) {
            e.preventDefault();
            
            const code = $(e.currentTarget).data('code');
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(() => {
                    this.showNotification('Gift code copied to clipboard!', 'success');
                    $(e.currentTarget).addClass('copied');
                    setTimeout(() => {
                        $(e.currentTarget).removeClass('copied');
                    }, 2000);
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = code;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                this.showNotification('Gift code copied to clipboard!', 'success');
            }
        },
        
        // Revoke gift award
        revokeAward: function(e) {
            e.preventDefault();
            
            if (!confirm(aqm_gifts.confirm_revoke)) {
                return;
            }
            
            const awardId = $(e.currentTarget).data('award-id');
            const self = this;
            
            $.ajax({
                url: aqm_gifts.ajax_url,
                type: 'POST',
                data: {
                    action: 'aqm_revoke_gift_award',
                    nonce: aqm_gifts.nonce,
                    award_id: awardId
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('Gift award revoked successfully', 'success');
                        location.reload(); // Refresh to update status
                    } else {
                        self.showNotification('Error: ' + response.data, 'error');
                    }
                },
                error: function() {
                    self.showNotification('Network error occurred', 'error');
                }
            });
        },
        
        // Export awards
        exportAwards: function(e) {
            e.preventDefault();
            
            const params = new URLSearchParams(window.location.search);
            params.set('action', 'aqm_export_gift_awards');
            params.set('nonce', aqm_gifts.nonce);
            
            window.open(aqm_gifts.ajax_url + '?' + params.toString());
        },
        
        // Bulk generate codes
        bulkGenerateCodes: function(e) {
            e.preventDefault();
            
            this.showNotification('Bulk code generation feature coming soon!', 'info');
        },
        
        // Handle gift type changes
        handleGiftTypeChange: function() {
            const giftType = $('#gift-type').val();
            const $valueField = $('#gift-value');
            
            // Update placeholder based on gift type
            const placeholders = {
                'voucher': 'e.g., $50, 100.000 VND',
                'discount': 'e.g., 10%, 25%',
                'physical': 'e.g., T-shirt, Mug',
                'points': 'e.g., 500 points',
                'custom': 'Enter custom value'
            };
            
            $valueField.attr('placeholder', placeholders[giftType] || 'Enter value');
            
            // Update prefix suggestions
            const prefixes = {
                'voucher': 'VOUCHER',
                'discount': 'DISCOUNT',
                'physical': 'PRIZE',
                'points': 'POINTS',
                'custom': 'GIFT'
            };
            
            if (!$('#gift-code-prefix').val()) {
                $('#gift-code-prefix').val(prefixes[giftType] || 'GIFT');
            }
        },
        
        // Handle campaign changes
        handleCampaignChange: function() {
            const campaignId = $('#campaign-id').val();
            const $option = $('#campaign-id option:selected');
            const status = $option.data('status');
            
            if (status === 'inactive') {
                this.showFormWarning('Warning: This campaign is inactive. Gifts will not be awarded until the campaign is activated.');
            } else {
                this.clearFormWarnings();
            }
        },
        
        // Keyboard shortcuts
        handleKeyboardShortcuts: function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if ($('#gift-form-modal').is(':visible')) {
                    $('#gift-form').submit();
                }
            }
            
            // Escape to close modal
            if (e.key === 'Escape') {
                if ($('#gift-form-modal').is(':visible')) {
                    this.closeGiftModal();
                }
            }
        },
        
        // Form validation
        validateForm: function() {
            this.clearFormErrors();
            
            let isValid = true;
            const errors = [];
            
            // Required fields
            const requiredFields = {
                'campaign-id': 'Campaign',
                'gift-name': 'Gift Name'
            };
            
            Object.keys(requiredFields).forEach(fieldId => {
                const $field = $('#' + fieldId);
                if (!$field.val().trim()) {
                    errors.push(requiredFields[fieldId] + ' is required');
                    $field.addClass('error');
                    isValid = false;
                }
            });
            
            // Validate scores
            const minScore = parseInt($('#min-score').val());
            const maxScore = parseInt($('#max-score').val());
            
            if (minScore > maxScore) {
                errors.push('Minimum score cannot be greater than maximum score');
                $('#min-score, #max-score').addClass('error');
                isValid = false;
            }
            
            // Validate probability
            const probability = parseFloat($('#probability').val());
            if (probability < 0 || probability > 100) {
                errors.push('Probability must be between 0 and 100');
                $('#probability').addClass('error');
                isValid = false;
            }
            
            // Validate dates
            const validFrom = $('#valid-from').val();
            const validUntil = $('#valid-until').val();
            
            if (validFrom && validUntil && new Date(validFrom) > new Date(validUntil)) {
                errors.push('Valid from date cannot be after valid until date');
                $('#valid-from, #valid-until').addClass('error');
                isValid = false;
            }
            
            if (!isValid) {
                this.showFormError(errors.join('<br>'));
            }
            
            return isValid;
        },
        
        // Show form loading state
        showFormLoading: function(loading) {
            const $submitBtn = $('#gift-form button[type="submit"]');
            
            if (loading) {
                $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Saving...');
            } else {
                $submitBtn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Save Gift');
            }
        },
        
        // Show form error
        showFormError: function(message) {
            $('#gift-form').prepend(`
                <div class="aqm-form-error">
                    <span class="dashicons dashicons-warning"></span>
                    ${message}
                </div>
            `);
        },
        
        // Show form warning
        showFormWarning: function(message) {
            $('#gift-form').prepend(`
                <div class="aqm-form-warning">
                    <span class="dashicons dashicons-info"></span>
                    ${message}
                </div>
            `);
        },
        
        // Clear form errors and warnings
        clearFormErrors: function() {
            $('.aqm-form-error, .aqm-form-warning').remove();
            $('.error').removeClass('error');
        },
        
        // Clear form warnings only
        clearFormWarnings: function() {
            $('.aqm-form-warning').remove();
        },
        
        // Show notification
        showNotification: function(message, type = 'info') {
            const $notification = $(`
                <div class="aqm-notification aqm-notification-${type}">
                    <span class="dashicons dashicons-${this.getNotificationIcon(type)}"></span>
                    ${message}
                    <button class="aqm-notification-close">&times;</button>
                </div>
            `);
            
            $('body').append($notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual close
            $notification.find('.aqm-notification-close').on('click', function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },
        
        // Get notification icon
        getNotificationIcon: function(type) {
            const icons = {
                'success': 'yes-alt',
                'error': 'warning',
                'warning': 'info',
                'info': 'info'
            };
            return icons[type] || 'info';
        },
        
        // Show codes modal
        showCodesModal: function(codes) {
            const codesHtml = codes.map(code => `<code class="gift-code">${code}</code>`).join('');
            
            const modal = `
                <div class="aqm-modal aqm-codes-modal">
                    <div class="aqm-modal-content">
                        <div class="aqm-modal-header">
                            <h2>Generated Gift Codes</h2>
                            <span class="aqm-modal-close">&times;</span>
                        </div>
                        <div class="aqm-modal-body">
                            <p>Copy these codes and distribute them to your participants:</p>
                            <div class="codes-container">
                                ${codesHtml}
                            </div>
                            <div class="modal-actions">
                                <button class="button button-primary copy-all-codes">Copy All Codes</button>
                                <button class="button button-secondary download-codes">Download as Text</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modal);
            
            // Bind events for codes modal
            $('.aqm-codes-modal .aqm-modal-close').on('click', function() {
                $('.aqm-codes-modal').fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            $('.copy-all-codes').on('click', function() {
                const allCodes = codes.join('\n');
                navigator.clipboard.writeText(allCodes).then(() => {
                    GiftManager.showNotification('All codes copied to clipboard!', 'success');
                });
            });
            
            $('.download-codes').on('click', function() {
                const blob = new Blob([codes.join('\n')], { type: 'text/plain' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'gift-codes-' + new Date().toISOString().split('T')[0] + '.txt';
                a.click();
                window.URL.revokeObjectURL(url);
            });
        },
        
        // Refresh gifts list
        refreshGiftsList: function() {
            setTimeout(() => {
                location.reload();
            }, 1000);
        },
        
        // Update stats after delete
        updateStatsAfterDelete: function() {
            // Update total count
            const $totalStat = $('.aqm-stat-card .stat-number').first();
            const currentTotal = parseInt($totalStat.text());
            $totalStat.text(currentTotal - 1);
        },
        
        // Setup date time inputs
        setupDateTimeInputs: function() {
            // Set minimum date to today
            const today = new Date().toISOString().slice(0, 16);
            $('#valid-from, #valid-until').attr('min', today);
        },
        
        // Setup tooltips
        setupTooltips: function() {
            $('[title]').each(function() {
                $(this).attr('data-tooltip', $(this).attr('title'));
                $(this).removeAttr('title');
            });
        },
        
        // Setup validation
        setupValidation: function() {
            // Real-time validation
            $('#gift-form input, #gift-form select, #gift-form textarea').on('blur', function() {
                $(this).removeClass('error');
            });
            
            // Quantity validation
            $('#quantity-total').on('input', function() {
                const value = parseInt($(this).val());
                if (value < 0) {
                    $(this).val(0);
                }
            });
            
            // Score validation
            $('#min-score, #max-score').on('input', function() {
                const value = parseInt($(this).val());
                if (value < 0) $(this).val(0);
                if (value > 100) $(this).val(100);
            });
            
            // Probability validation
            $('#probability').on('input', function() {
                const value = parseFloat($(this).val());
                if (value < 0) $(this).val(0);
                if (value > 100) $(this).val(100);
            });
        }
    };
    
    // Initialize Gift Manager
    GiftManager.init();
    
    // Add custom CSS for notifications and loading states
    if ($('#aqm-gift-styles').length === 0) {
        $('head').append(`
            <style id="aqm-gift-styles">
                .aqm-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    min-width: 300px;
                    animation: slideInRight 0.3s ease;
                }
                
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                
                .aqm-notification-success {
                    background: #d4edda;
                    color: #155724;
                    border-left: 4px solid #28a745;
                }
                
                .aqm-notification-error {
                    background: #f8d7da;
                    color: #721c24;
                    border-left: 4px solid #dc3545;
                }
                
                .aqm-notification-warning {
                    background: #fff3cd;
                    color: #856404;
                    border-left: 4px solid #ffc107;
                }
                
                .aqm-notification-info {
                    background: #d1ecf1;
                    color: #0c5460;
                    border-left: 4px solid #17a2b8;
                }
                
                .aqm-notification-close {
                    background: none;
                    border: none;
                    font-size: 20px;
                    cursor: pointer;
                    margin-left: auto;
                    opacity: 0.7;
                }
                
                .aqm-notification-close:hover {
                    opacity: 1;
                }
                
                .aqm-form-error,
                .aqm-form-warning {
                    background: #f8d7da;
                    color: #721c24;
                    padding: 12px 15px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                    border-left: 4px solid #dc3545;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .aqm-form-warning {
                    background: #fff3cd;
                    color: #856404;
                    border-left-color: #ffc107;
                }
                
                .error {
                    border-color: #dc3545 !important;
                    box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2) !important;
                }
                
                .spin {
                    animation: spin 1s linear infinite;
                }
                
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                
                .codes-container {
                    max-height: 300px;
                    overflow-y: auto;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 6px;
                    margin: 15px 0;
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 10px;
                }
                
                .codes-container .gift-code {
                    display: block;
                    padding: 8px 12px;
                    background: white;
                    border: 1px solid #dee2e6;
                    border-radius: 4px;
                    font-family: monospace;
                    text-align: center;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                
                .codes-container .gift-code:hover {
                    background: #e9ecef;
                    border-color: #adb5bd;
                }
                
                .modal-actions {
                    text-align: center;
                    padding-top: 15px;
                    border-top: 1px solid #dee2e6;
                }
                
                .modal-actions .button {
                    margin: 0 5px;
                }
                
                .copy-code.copied {
                    background: #28a745 !important;
                    color: white !important;
                }
                
                body.modal-open {
                    overflow: hidden;
                }
            </style>
        `);
    }
});