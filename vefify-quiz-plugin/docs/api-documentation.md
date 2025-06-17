<?php
/**
 * API Endpoints Documentation
 * File: docs/api-documentation.md
 */
?>

# Vefify Quiz Plugin API Documentation

## Base URL
All API endpoints are prefixed with: `/wp-json/vefify/v1/`

## Authentication
Currently, all endpoints are public. Future versions may include API key authentication.

## Endpoints

### 1. Campaigns

#### GET `/campaigns`
Get all active campaigns.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Health Quiz 2024",
      "slug": "health-quiz-2024",
      "description": "Test your health knowledge",
      "start_date": "2024-01-01 00:00:00",
      "end_date": "2024-12-31 23:59:59",
      "is_active": 1,
      "questions_per_quiz": 5,
      "pass_score": 3
    }
  ]
}
```

#### GET `/campaigns/{id}`
Get single campaign with questions and gifts.

**Parameters:**
- `id` (integer, required): Campaign ID

**Response:**
```json
{
  "success": true,
  "data": {
    "campaign": {
      "id": 1,
      "name": "Health Quiz 2024",
      "description": "Test your health knowledge",
      "questions_per_quiz": 5
    },
    "questions": [
      {
        "id": 1,
        "question_text": "What is Aspirin used for?",
        "question_type": "multiple_select",
        "options": [
          {
            "id": 1,
            "option_text": "Pain relief",
            "order_index": 1
          }
        ]
      }
    ],
    "gifts": [
      {
        "id": 1,
        "gift_name": "50K Voucher",
        "gift_type": "voucher",
        "min_score": 5,
        "max_quantity": 20
      }
    ]
  }
}
```

### 2. Quiz Operations

#### POST `/quiz/check-participation`
Check if phone number already participated in campaign.

**Request Body:**
```json
{
  "phone": "0901234567",
  "campaign_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "participated": false,
  "message": "You can participate"
}
```

#### POST `/quiz/start`
Start a new quiz session.

**Request Body:**
```json
{
  "campaign_id": 1,
  "user_data": {
    "full_name": "Nguyen Van A",
    "phone_number": "0901234567",
    "province": "hanoi",
    "pharmacy_code": "PH001",
    "email": "test@example.com"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "session_id": "vq_abc123_def456",
    "user_id": 1,
    "campaign": {
      "id": 1,
      "name": "Health Quiz 2024",
      "time_limit": 600
    },
    "questions": [
      {
        "id": 1,
        "question_text": "What is Aspirin used for?",
        "question_type": "multiple_select",
        "options": [
          {
            "id": 1,
            "option_text": "Pain relief"
          }
        ]
      }
    ]
  }
}
```

#### POST `/quiz/submit`
Submit quiz answers and get results.

**Request Body:**
```json
{
  "session_id": "vq_abc123_def456",
  "answers": {
    "1": [1, 2],
    "2": [3],
    "3": [1, 2]
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "score": 4,
    "total_questions": 5,
    "percentage": 80,
    "completion_time": 245,
    "gift": {
      "has_gift": true,
      "gift_name": "50K Voucher",
      "gift_code": "GIFT50K123",
      "gift_type": "voucher",
      "gift_value": "50000 VND"
    },
    "detailed_results": {
      "1": {
        "user_answers": [1, 2],
        "correct_answers": [1, 2],
        "is_correct": true
      }
    }
  }
}
```

### 3. Gift Management

#### POST `/gifts/claim`
Claim a gift (Phase 2 - API integration).

**Request Body:**
```json
{
  "user_id": 1,
  "gift_code": "GIFT50K123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Gift claimed successfully",
  "external_response": {
    "voucher_code": "EXTERNAL123",
    "redemption_url": "https://partner.com/redeem/EXTERNAL123"
  }
}
```

## Error Responses

All endpoints may return error responses in this format:

```json
{
  "code": "error_code",
  "message": "Human readable error message",
  "data": {
    "status": 400
  }
}
```

## Common Error Codes

- `missing_data`: Required fields are missing
- `invalid_campaign`: Campaign not found or inactive
- `already_participated`: Phone number already participated
- `invalid_session`: Quiz session not found
- `already_completed`: Quiz already completed
- `no_questions`: No questions available
- `db_error`: Database operation failed

## Rate Limiting

Currently no rate limiting is implemented. Consider implementing rate limiting in production.

## CORS

The API supports CORS for frontend JavaScript applications.

<?php
/**
 * Installation Guide
 * File: docs/installation.md
 */
?>

# Vefify Quiz Plugin Installation Guide

## Prerequisites

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.2+
- Modern web browser with JavaScript enabled

## Installation Steps

### 1. Plugin Installation

#### Option A: Manual Installation
1. Download the plugin zip file
2. Extract to `wp-content/plugins/vefify-quiz-plugin/`
3. Go to WordPress Admin → Plugins
4. Activate "Vefify Quiz Campaign Manager"

#### Option B: WordPress Admin Upload
1. Go to WordPress Admin → Plugins → Add New
2. Click "Upload Plugin"
3. Choose the plugin zip file
4. Click "Install Now" then "Activate"

### 2. Initial Setup

After activation, the plugin will:
- Create necessary database tables
- Insert sample campaign and questions
- Create admin menu items

### 3. Configuration

#### Basic Settings
1. Go to **Vefify Quiz → Dashboard**
2. Review the sample campaign
3. Configure default settings in **Settings**

#### Campaign Setup
1. Go to **Vefify Quiz → Campaigns**
2. Edit the sample campaign or create new ones
3. Set date ranges, participant limits, scoring rules

#### Question Bank
1. Go to **Vefify Quiz → Questions**
2. Review sample questions
3. Add your own questions or import from CSV

#### Gift Management
1. Go to **Vefify Quiz → Gifts**
2. Configure gifts for different score ranges
3. Set inventory limits and gift codes

### 4. Frontend Integration

#### Using Shortcodes
```php
// Basic quiz shortcode
[vefify_quiz campaign_id="1"]

// With template option
[vefify_quiz campaign_id="1" template="mobile"]

// Campaign info shortcode
[vefify_campaign campaign_id="1"]
```

#### Custom Page Template
1. Create a new page: **Quiz**
2. Add the shortcode: `[vefify_quiz campaign_id="1"]`
3. Publish the page

#### Direct Template Integration
```php
// In your theme files
<?php
if (function_exists('vefify_display_quiz')) {
    vefify_display_quiz(1); // Campaign ID
}
?>
```

### 5. URL Structure

- Quiz page: `yoursite.com/quiz/`
- Campaign specific: `yoursite.com/quiz/?campaign_id=1`
- Admin: `yoursite.com/wp-admin/admin.php?page=vefify-quiz`

## Configuration Examples

### Sample Campaign JSON
```json
{
  "name": "Health Knowledge Quiz 2024",
  "slug": "health-quiz-2024", 
  "description": "Test your health and wellness knowledge",
  "start_date": "2024-01-01 00:00:00",
  "end_date": "2024-12-31 23:59:59",
  "questions_per_quiz": 5,
  "time_limit": 600,
  "pass_score": 3,
  "max_participants": 1000
}
```

### Gift Configuration
```json
{
  "gifts": [
    {
      "gift_name": "10% Discount",
      "gift_type": "discount",
      "gift_value": "10%",
      "min_score": 3,
      "max_score": 4,
      "max_quantity": 100,
      "gift_code_prefix": "SAVE10"
    },
    {
      "gift_name": "50K VND Voucher", 
      "gift_type": "voucher",
      "gift_value": "50000 VND",
      "min_score": 5,
      "max_quantity": 20,
      "gift_code_prefix": "GIFT50K"
    }
  ]
}
```

### Question Import CSV Format
```csv
question_text,option1,option2,option3,option4,correct_options,category,difficulty
"What is Aspirin used for?","Pain relief","Fever reduction","Sleep aid","Anxiety","1,2","medication","easy"
"Which vitamin helps bone health?","Vitamin A","Vitamin C","Vitamin D","Vitamin E","3","nutrition","medium"
```

## Advanced Configuration

### Database Customization
```php
// Custom table prefix
define('VEFIFY_QUIZ_TABLE_PREFIX', 'custom_');

// Custom database settings
add_filter('vefify_quiz_db_settings', function($settings) {
    $settings['charset'] = 'utf8mb4';
    $settings['collate'] = 'utf8mb4_unicode_ci';
    return $settings;
});
```

### API Configuration
```php
// Enable API key authentication (future feature)
add_filter('vefify_quiz_require_api_key', '__return_true');

// Custom API endpoints
add_action('rest_api_init', function() {
    register_rest_route('vefify/v1', '/custom-endpoint', [
        'methods' => 'GET',
        'callback' => 'custom_endpoint_handler',
        'permission_callback' => '__return_true'
    ]);
});
```

### Frontend Customization
```php
// Custom CSS
add_action('wp_enqueue_scripts', function() {
    if (is_quiz_page()) {
        wp_enqueue_style('custom-quiz-style', 
            get_stylesheet_directory_uri() . '/custom-quiz.css'
        );
    }
});

// Custom JavaScript
add_action('wp_enqueue_scripts', function() {
    if (is_quiz_page()) {
        wp_enqueue_script('custom-quiz-script',
            get_stylesheet_directory_uri() . '/custom-quiz.js',
            ['vefify-quiz-script'], '1.0.0', true
        );
    }
});
```

### Gift API Integration (Phase 2)
```php
// Custom gift API handler
add_filter('vefify_gift_api_handler', function($handler, $gift_data) {
    // Custom API integration logic
    return new CustomGiftApiHandler($gift_data);
}, 10, 2);

// API endpoint configuration
add_filter('vefify_gift_api_config', function($config) {
    $config['timeout'] = 45;
    $config['retry_attempts'] = 3;
    $config['headers']['Authorization'] = 'Bearer ' . get_option('gift_api_token');
    return $config;
});
```

## Troubleshooting

### Common Issues

1. **Database tables not created**
   - Check file permissions
   - Verify MySQL user has CREATE privileges
   - Manually run SQL from `/database/migrations/`

2. **Quiz not loading**
   - Check JavaScript console for errors
   - Verify REST API is enabled: `/wp-json/vefify/v1/campaigns`
   - Clear browser cache

3. **Permission errors**
   - Ensure WordPress user has `manage_options` capability
   - Check .htaccess for REST API blocks

4. **Mobile display issues**
   - Check viewport meta tag
   - Verify responsive CSS is loading
   - Test on actual mobile devices

### Debug Mode
```php
// Enable debug logging
add_filter('vefify_quiz_debug', '__return_true');

// Custom log location
add_filter('vefify_quiz_log_file', function() {
    return WP_CONTENT_DIR . '/debug/vefify-quiz.log';
});
```

### Performance Optimization
```php
// Enable caching
add_filter('vefify_quiz_enable_cache', '__return_true');

// Cache duration (seconds)
add_filter('vefify_quiz_cache_duration', function() {
    return 3600; // 1 hour
});

// Database optimization
add_action('init', function() {
    if (defined('VEFIFY_QUIZ_OPTIMIZE_DB')) {
        // Add database indexes
        global $wpdb;
        $wpdb->query("CREATE INDEX idx_phone_lookup ON {$wpdb->prefix}vefify_quiz_users (phone_number)");
    }
});
```

## Security Considerations

1. **Input Validation**: All user inputs are sanitized and validated
2. **SQL Injection**: All database queries use prepared statements
3. **CSRF Protection**: WordPress nonces are used for form submissions
4. **Rate Limiting**: Consider implementing for production use
5. **Phone Privacy**: Phone numbers are stored securely and never exposed in frontend

## Support and Updates

- Plugin documentation: `/docs/`
- GitHub repository: [Your repository URL]
- Support email: [Your support email]
- Version updates: Check WordPress admin for notifications

## License

This plugin is released under GPL v2 or later license.