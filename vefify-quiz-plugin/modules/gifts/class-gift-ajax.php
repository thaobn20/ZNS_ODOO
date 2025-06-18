<?php
/**
 * Gift AJAX Handler & Form Processing
 * File: modules/gifts/class-gift-ajax.php
 * 
 * Handles AJAX requests for gift management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Gift_Ajax {
    
    private $model;
    
    public function __construct() {
        $this->model = new Vefify_Gift_Model();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX handlers for logged-in users
        add_action('wp_ajax_vefify_save_gift', array($this, 'ajax_save_gift'));
        add_action('wp_ajax_vefify_delete_gift', array($this, 'ajax_delete_gift'));
        add_action('wp_ajax_vefify_get_gift', array($this, 'ajax_get_gift'));
        add_action('wp_ajax_vefify_validate_gift', array($this, 'ajax_validate_gift'));
        add_action('wp_ajax_vefify_generate_gift_codes', array($this, 'ajax_generate_gift_codes'));
        add_action('wp_ajax_vefify_check_gift_inventory', array($this, 'ajax_check_inventory'));
        
        // Form processing
        add_action('admin_post_vefify_gift_form', array($this, 'process_gift_form'));
    }
    
    /**
     * ✅ MAIN: Save gift via AJAX
     */
    public function ajax_save_gift() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_gift_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            // Get form data
            $gift_data = $this->sanitize_gift_form_data($_POST);
            $gift_id = !empty($_POST['gift_id']) ? intval($_POST['gift_id']) : null;
            
            // Save gift
            $result = $this->model->save_gift($gift_data, $gift_id);
            
            if (is_array($result) && isset($result['errors'])) {
                // Validation errors
                wp_send_json_error(array(
                    'message' => 'Validation failed',
                    'errors' => $result['errors']
                ));
            } elseif ($result === false) {
                // Save failed
                wp_send_json_error(array(
                    'message' => 'Failed to save gift. Please try again.'
                ));
            } else {
                // Success
                $saved_gift = $this->model->get_gift_by_id($result);
                
                wp_send_json_success(array(
                    'message' => $gift_id ? 'Gift updated successfully!' : 'Gift created successfully!',
                    'gift_id' => $result,
                    'gift' => $saved_gift,
                    'redirect' => admin_url('admin.php?page=vefify-gifts&message=saved')
                ));
            }
            
        } catch (Exception $e) {
            error_log('Gift save error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'An error occurred while saving the gift.'
            ));
        }
    }
    
    /**
     * ✅ Delete gift via AJAX
     */
    public function ajax_delete_gift() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_gift_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $gift_id = intval($_POST['gift_id']);
        
        if (!$gift_id) {
            wp_send_json_error('Invalid gift ID');
        }
        
        $result = $this->model->delete_gift($gift_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Gift deleted successfully!'
            ));
        } else {
            wp_send_json_error('Failed to delete gift');
        }
    }
    
    /**
     * ✅ Get gift data via AJAX
     */
    public function ajax_get_gift() {
        if (!wp_verify_nonce($_GET['nonce'], 'vefify_gift_nonce')) {
            wp_die('Security check failed');
        }
        
        $gift_id = intval($_GET['gift_id']);
        $gift = $this->model->get_gift_by_id($gift_id);
        
        if ($gift) {
            wp_send_json_success($gift);
        } else {
            wp_send_json_error('Gift not found');
        }
    }
    
    /**
     * ✅ Validate gift data via AJAX (for real-time validation)
     */
    public function ajax_validate_gift() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_gift_nonce')) {
            wp_die('Security check failed');
        }
        
        $gift_data = $this->sanitize_gift_form_data($_POST);
        $validation_result = $this->model->validate_gift_data($gift_data);
        
        if ($validation_result === true) {
            wp_send_json_success(array('valid' => true));
        } else {
            wp_send_json_success(array(
                'valid' => false,
                'errors' => $validation_result['errors']
            ));
        }
    }
    
    /**
     * ✅ Generate gift codes in bulk
     */
    public function ajax_generate_gift_codes() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_gift_nonce')) {
            wp_die('Security check failed');
        }
        
        $gift_id = intval($_POST['gift_id']);
        $quantity = intval($_POST['quantity']);
        
        if ($quantity > 100) {
            wp_send_json_error('Maximum 100 codes per batch');
        }
        
        $codes = array();
        for ($i = 0; $i < $quantity; $i++) {
            $code = $this->model->generate_unique_gift_code($gift_id);
            if ($code) {
                $codes[] = $code;
            }
        }
        
        wp_send_json_success(array(
            'codes' => $codes,
            'count' => count($codes)
        ));
    }
    
    /**
     * ✅ Check gift inventory status
     */
    public function ajax_check_inventory() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_gift_nonce')) {
            wp_die('Security check failed');
        }
        
        $gift_id = intval($_POST['gift_id']);
        $inventory = $this->model->get_gift_inventory($gift_id);
        
        wp_send_json_success($inventory);
    }
    
    /**
     * ✅ Process traditional form submission (fallback)
     */
    public function process_gift_form() {
        // Security check
        if (!wp_verify_nonce($_POST['vefify_gift_nonce'], 'vefify_gift_form')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $gift_data = $this->sanitize_gift_form_data($_POST);
        $gift_id = !empty($_POST['gift_id']) ? intval($_POST['gift_id']) : null;
        
        $result = $this->model->save_gift($gift_data, $gift_id);
        
        if (is_array($result) && isset($result['errors'])) {
            // Validation errors - redirect back with errors
            $error_message = implode(', ', $result['errors']);
            wp_redirect(admin_url('admin.php?page=vefify-gifts&action=edit&id=' . $gift_id . '&error=' . urlencode($error_message)));
        } elseif ($result === false) {
            // Save failed
            wp_redirect(admin_url('admin.php?page=vefify-gifts&action=edit&id=' . $gift_id . '&error=save_failed'));
        } else {
            // Success
            wp_redirect(admin_url('admin.php?page=vefify-gifts&message=saved&id=' . $result));
        }
        
        exit;
    }
    
    /**
     * ✅ Sanitize form data
     */
    private function sanitize_gift_form_data($data) {
        return array(
            'campaign_id' => intval($data['campaign_id'] ?? 0),
            'gift_name' => sanitize_text_field($data['gift_name'] ?? ''),
            'gift_type' => sanitize_text_field($data['gift_type'] ?? ''),
            'gift_value' => sanitize_text_field($data['gift_value'] ?? ''),
            'gift_description' => wp_kses_post($data['gift_description'] ?? ''),
            'min_score' => intval($data['min_score'] ?? 0),
            'max_score' => !empty($data['max_score']) ? intval($data['max_score']) : null,
            'max_quantity' => !empty($data['max_quantity']) ? intval($data['max_quantity']) : null,
            'gift_code_prefix' => sanitize_text_field($data['gift_code_prefix'] ?? ''),
            'api_endpoint' => esc_url_raw($data['api_endpoint'] ?? ''),
            'api_params' => wp_kses_post($data['api_params'] ?? ''),
            'is_active' => isset($data['is_active']) ? 1 : 0
        );
    }
}

// Initialize if we're in admin
if (is_admin()) {
    new Vefify_Gift_Ajax();
}

/**
 * ===== JAVASCRIPT FOR GIFT FORM =====
 * Add this to your gift form page
 */
?>

<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // ✅ AJAX form submission
    $('#gift-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('[type="submit"]');
        const originalText = $submitBtn.text();
        
        // Show loading
        $submitBtn.prop('disabled', true).text('Saving...');
        $('.error-message').hide();
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: $form.serialize() + '&action=vefify_save_gift',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showMessage('success', response.data.message);
                    
                    // Redirect if specified
                    if (response.data.redirect) {
                        setTimeout(() => {
                            window.location.href = response.data.redirect;
                        }, 1500);
                    }
                } else {
                    // Show errors
                    showErrors(response.data.errors || [response.data.message]);
                }
            },
            error: function() {
                showMessage('error', 'An error occurred. Please try again.');
            },
            complete: function() {
                // Reset button
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // ✅ Real-time validation
    $('#gift-form input, #gift-form select, #gift-form textarea').on('blur', function() {
        validateForm();
    });
    
    // ✅ Delete gift
    $('.delete-gift').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this gift?')) {
            return;
        }
        
        const giftId = $(this).data('gift-id');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'vefify_delete_gift',
                gift_id: giftId,
                nonce: vefifyGift.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                    // Remove row or redirect
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage('error', response.data || 'Delete failed');
                }
            }
        });
    });
    
    // ✅ Generate gift codes
    $('#generate-codes').on('click', function() {
        const giftId = $(this).data('gift-id');
        const quantity = prompt('How many codes to generate? (Max 100)');
        
        if (!quantity || quantity > 100) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'vefify_generate_gift_codes',
                gift_id: giftId,
                quantity: quantity,
                nonce: vefifyGift.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Display generated codes
                    const codes = response.data.codes.join('\n');
                    $('#generated-codes').val(codes).show();
                    showMessage('success', `Generated ${response.data.count} codes`);
                }
            }
        });
    });
    
    // ===== HELPER FUNCTIONS =====
    
    function validateForm() {
        const formData = $('#gift-form').serialize();
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: formData + '&action=vefify_validate_gift',
            success: function(response) {
                if (response.success && !response.data.valid) {
                    showErrors(response.data.errors);
                } else {
                    $('.error-message').hide();
                }
            }
        });
    }
    
    function showMessage(type, message) {
        const className = type === 'success' ? 'notice-success' : 'notice-error';
        const $notice = $('<div class="notice ' + className + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        setTimeout(() => {
            $notice.fadeOut();
        }, 5000);
    }
    
    function showErrors(errors) {
        if (!errors || !Array.isArray(errors)) return;
        
        errors.forEach(error => {
            showMessage('error', error);
        });
    }
});
</script>

<?php
/**
 * ===== HTML FORM EXAMPLE =====
 * Use this structure in your gift manager class
 */
?>

<!-- Gift Form Example -->
<form id="gift-form" class="gift-config-form">
    <?php wp_nonce_field('vefify_gift_form', 'vefify_gift_nonce'); ?>
    
    <input type="hidden" name="gift_id" value="<?php echo esc_attr($gift['id'] ?? ''); ?>">
    
    <table class="form-table">
        <tr>
            <th><label for="campaign_id">Campaign *</label></th>
            <td>
                <select name="campaign_id" id="campaign_id" required>
                    <option value="">Select Campaign</option>
                    <?php foreach ($campaigns as $campaign): ?>
                        <option value="<?php echo $campaign['id']; ?>" 
                                <?php selected($gift['campaign_id'] ?? '', $campaign['id']); ?>>
                            <?php echo esc_html($campaign['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        
        <tr>
            <th><label for="gift_name">Gift Name *</label></th>
            <td>
                <input type="text" name="gift_name" id="gift_name" 
                       value="<?php echo esc_attr($gift['gift_name'] ?? ''); ?>" 
                       class="regular-text" required>
            </td>
        </tr>
        
        <tr>
            <th><label for="gift_type">Gift Type *</label></th>
            <td>
                <select name="gift_type" id="gift_type" required>
                    <option value="">Select Type</option>
                    <option value="voucher" <?php selected($gift['gift_type'] ?? '', 'voucher'); ?>>Voucher</option>
                    <option value="discount" <?php selected($gift['gift_type'] ?? '', 'discount'); ?>>Discount</option>
                    <option value="product" <?php selected($gift['gift_type'] ?? '', 'product'); ?>>Product</option>
                    <option value="points" <?php selected($gift['gift_type'] ?? '', 'points'); ?>>Points</option>
                </select>
            </td>
        </tr>
        
        <tr>
            <th><label for="gift_value">Gift Value *</label></th>
            <td>
                <input type="text" name="gift_value" id="gift_value" 
                       value="<?php echo esc_attr($gift['gift_value'] ?? ''); ?>" 
                       class="regular-text" required>
                <p class="description">e.g., "50000 VND", "10%", "Free Product"</p>
            </td>
        </tr>
        
        <tr>
            <th><label for="min_score">Minimum Score *</label></th>
            <td>
                <input type="number" name="min_score" id="min_score" 
                       value="<?php echo esc_attr($gift['min_score'] ?? 0); ?>" 
                       min="0" required>
            </td>
        </tr>
        
        <tr>
            <th><label for="max_score">Maximum Score</label></th>
            <td>
                <input type="number" name="max_score" id="max_score" 
                       value="<?php echo esc_attr($gift['max_score'] ?? ''); ?>" 
                       min="0">
                <p class="description">Leave empty for no maximum</p>
            </td>
        </tr>
        
        <tr>
            <th><label for="max_quantity">Maximum Quantity</label></th>
            <td>
                <input type="number" name="max_quantity" id="max_quantity" 
                       value="<?php echo esc_attr($gift['max_quantity'] ?? ''); ?>" 
                       min="1">
                <p class="description">Leave empty for unlimited</p>
            </td>
        </tr>
        
        <tr>
            <th><label for="gift_code_prefix">Code Prefix</label></th>
            <td>
                <input type="text" name="gift_code_prefix" id="gift_code_prefix" 
                       value="<?php echo esc_attr($gift['gift_code_prefix'] ?? ''); ?>" 
                       maxlength="10">
                <p class="description">Prefix for generated gift codes</p>
            </td>
        </tr>
        
        <tr>
            <th><label for="is_active">Status</label></th>
            <td>
                <label>
                    <input type="checkbox" name="is_active" value="1" 
                           <?php checked($gift['is_active'] ?? 1, 1); ?>>
                    Active
                </label>
            </td>
        </tr>
    </table>
    
    <p class="submit">
        <input type="submit" class="button-primary" value="Save Gift">
        <a href="<?php echo admin_url('admin.php?page=vefify-gifts'); ?>" class="button">Cancel</a>
    </p>
</form>

<script>
// Localize script for AJAX
var vefifyGift = {
    nonce: '<?php echo wp_create_nonce('vefify_gift_nonce'); ?>',
    ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>'
};
</script>