<?php
/**
 * SAMPLE DATABASE INSERTION SCRIPT
 * File: add-sample-data.php
 * 
 * This script will add sample data to your Vefify Quiz plugin
 * Upload to your plugin directory and run once
 */

// Include WordPress
require_once('../../../wp-config.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. Only administrators can run this script.');
}

// Include the database class
require_once(dirname(__FILE__) . '/includes/class-database.php');

?>
<!DOCTYPE html>
<html>
<head>
    <title>📊 Add Sample Data to Vefify Quiz</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 15px 0; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 15px 0; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; color: #856404; }
        .btn { background: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; margin: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #005a87; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .step { background: #f8f9fa; border-left: 4px solid #007cba; padding: 15px; margin: 15px 0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
        .card { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Add Sample Data to Vefify Quiz</h1>
        
        <?php
        $database = new Vefify_Quiz_Database();
        $action = $_GET['action'] ?? 'menu';
        
        // Handle different actions
        if ($action === 'add_sample_data') {
            add_sample_data($database);
        } elseif ($action === 'clear_data') {
            clear_all_data($database);
        } elseif ($action === 'reset_and_add') {
            clear_all_data($database);
            add_sample_data($database);
        } else {
            show_main_menu($database);
        }
        ?>
        
        <div class="warning">
            <h3>🛡️ Security Note</h3>
            <p><strong>Important:</strong> Delete this script file after use for security.</p>
            <p>File location: <code><?php echo __FILE__; ?></code></p>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Show main menu
 */
function show_main_menu($database) {
    // Check current data status
    $stats = $database->get_database_stats();
    
    echo '<h2>📊 Current Database Status</h2>';
    echo '<table>';
    echo '<thead><tr><th>Table</th><th>Records</th></tr></thead><tbody>';
    
    $total_records = 0;
    foreach ($stats as $key => $stat) {
        echo '<tr>';
        echo '<td><strong>' . ucfirst($key) . '</strong></td>';
        echo '<td>' . number_format($stat['count']) . ' records</td>';
        echo '</tr>';
        $total_records += $stat['count'];
    }
    
    echo '</tbody></table>';
    
    echo '<div class="grid">';
    
    if ($total_records == 0) {
        echo '<div class="card">';
        echo '<h3>🆕 Add Sample Data</h3>';
        echo '<p>Your database is empty. Add sample data to get started:</p>';
        echo '<ul>';
        echo '<li>3 Sample Campaigns</li>';
        echo '<li>10 Health Quiz Questions</li>';
        echo '<li>Multiple Choice and True/False options</li>';
        echo '<li>2 Gift Types (Discount & Voucher)</li>';
        echo '<li>5 Sample Participants</li>';
        echo '<li>Analytics data</li>';
        echo '</ul>';
        echo '<a href="?action=add_sample_data" class="btn btn-success">📊 Add Sample Data</a>';
        echo '</div>';
    } else {
        echo '<div class="card">';
        echo '<h3>🔄 Update Options</h3>';
        echo '<p>You have ' . $total_records . ' records in your database.</p>';
        echo '<a href="?action=add_sample_data" class="btn">➕ Add More Sample Data</a><br><br>';
        echo '<a href="?action=clear_data" class="btn btn-danger" onclick="return confirm(\'This will delete all data! Continue?\')">🗑️ Clear All Data</a><br><br>';
        echo '<a href="?action=reset_and_add" class="btn" onclick="return confirm(\'This will reset and add fresh sample data! Continue?\')">🔄 Reset & Add Fresh Data</a>';
        echo '</div>';
    }
    
    echo '<div class="card">';
    echo '<h3>🧪 Test Features</h3>';
    echo '<p>After adding sample data, you can test:</p>';
    echo '<ul>';
    echo '<li><a href="/wp-admin/admin.php?page=vefify-campaigns">📈 Campaign Management</a></li>';
    echo '<li><a href="/wp-admin/admin.php?page=vefify-questions">❓ Question Bank</a></li>';
    echo '<li><a href="/wp-admin/admin.php?page=vefify-gifts">🎁 Gift Management</a></li>';
    echo '<li><a href="/wp-admin/admin.php?page=vefify-participants">👥 Participants</a></li>';
    echo '<li><a href="/wp-admin/admin.php?page=vefify-analytics">📊 Analytics</a></li>';
    echo '</ul>';
    echo '</div>';
    
    echo '</div>';
}

/**
 * Add sample data
 */
function add_sample_data($database) {
    echo '<h2>📊 Adding Sample Data...</h2>';
    
    global $wpdb;
    $errors = array();
    $success_count = 0;
    
    try {
        // 1. Add Sample Campaigns
        echo '<div class="step">Step 1: Adding Sample Campaigns...</div>';
        
        $campaigns = array(
            array(
                'name' => 'Health Knowledge Quiz 2024',
                'slug' => 'health-quiz-2024',
                'description' => 'Test your health and wellness knowledge with this comprehensive quiz',
                'start_date' => '2024-01-01 00:00:00',
                'end_date' => '2025-12-31 23:59:00',
                'is_active' => 1,
                'max_participants' => 1000,
                'questions_per_quiz' => 5,
                'time_limit' => 600,
                'pass_score' => 3
            ),
            array(
                'name' => 'Nutrition Basics Quiz',
                'slug' => 'nutrition-basics-quiz',
                'description' => 'Learn about proper nutrition and healthy eating habits',
                'start_date' => '2024-01-01 00:00:00',
                'end_date' => '2025-12-31 23:59:00',
                'is_active' => 1,
                'max_participants' => 500,
                'questions_per_quiz' => 8,
                'time_limit' => 480,
                'pass_score' => 5
            ),
            array(
                'name' => 'Exercise & Fitness Quiz',
                'slug' => 'exercise-fitness-quiz',
                'description' => 'Test your knowledge about exercise and physical fitness',
                'start_date' => '2024-06-01 00:00:00',
                'end_date' => '2025-12-31 23:59:00',
                'is_active' => 1,
                'max_participants' => 750,
                'questions_per_quiz' => 6,
                'time_limit' => 540,
                'pass_score' => 4
            )
        );
        
        foreach ($campaigns as $campaign) {
            $result = $wpdb->insert($database->get_table_name('campaigns'), $campaign);
            if ($result) {
                $success_count++;
                echo "✅ Added campaign: {$campaign['name']}<br>";
            } else {
                $errors[] = "Failed to add campaign: {$campaign['name']} - " . $wpdb->last_error;
            }
        }
        
        // 2. Add Sample Questions
        echo '<div class="step">Step 2: Adding Sample Questions...</div>';
        
        $questions = array(
            // Health Quiz Questions
            array(
                'campaign_id' => 1,
                'question_text' => 'What is the recommended daily water intake for adults?',
                'question_type' => 'multiple_choice',
                'category' => 'nutrition',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'Adults should drink about 8 glasses (2 liters) of water daily for optimal health.'
            ),
            array(
                'campaign_id' => 1,
                'question_text' => 'Which vitamin is essential for bone health?',
                'question_type' => 'multiple_choice',
                'category' => 'nutrition',
                'difficulty' => 'medium',
                'points' => 2,
                'explanation' => 'Vitamin D helps the body absorb calcium, which is crucial for strong bones.'
            ),
            array(
                'campaign_id' => 1,
                'question_text' => 'Regular exercise is important for maintaining good health.',
                'question_type' => 'true_false',
                'category' => 'fitness',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'Regular exercise is essential for physical and mental health, disease prevention, and longevity.'
            ),
            array(
                'campaign_id' => 1,
                'question_text' => 'How many hours of sleep do adults typically need per night?',
                'question_type' => 'multiple_choice',
                'category' => 'wellness',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'Most adults need 7-9 hours of sleep per night for optimal health and cognitive function.'
            ),
            array(
                'campaign_id' => 1,
                'question_text' => 'Which of these foods are good sources of protein?',
                'question_type' => 'multiple_select',
                'category' => 'nutrition',
                'difficulty' => 'medium',
                'points' => 2,
                'explanation' => 'Lean meats, fish, eggs, beans, and nuts are all excellent sources of protein.'
            ),
            
            // Nutrition Quiz Questions
            array(
                'campaign_id' => 2,
                'question_text' => 'Which macronutrient provides the most calories per gram?',
                'question_type' => 'multiple_choice',
                'category' => 'nutrition',
                'difficulty' => 'medium',
                'points' => 2,
                'explanation' => 'Fats provide 9 calories per gram, while carbohydrates and proteins provide 4 calories per gram.'
            ),
            array(
                'campaign_id' => 2,
                'question_text' => 'Fruits and vegetables should make up half of your plate at meals.',
                'question_type' => 'true_false',
                'category' => 'nutrition',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'According to dietary guidelines, fruits and vegetables should comprise about half of your plate.'
            ),
            
            // Fitness Quiz Questions
            array(
                'campaign_id' => 3,
                'question_text' => 'How many minutes of moderate exercise per week do experts recommend?',
                'question_type' => 'multiple_choice',
                'category' => 'fitness',
                'difficulty' => 'medium',
                'points' => 2,
                'explanation' => 'Health experts recommend at least 150 minutes of moderate-intensity exercise per week.'
            ),
            array(
                'campaign_id' => 3,
                'question_text' => 'Strength training should be done at least twice per week.',
                'question_type' => 'true_false',
                'category' => 'fitness',
                'difficulty' => 'easy',
                'points' => 1,
                'explanation' => 'Strength training exercises should be performed at least 2 days per week for all major muscle groups.'
            ),
            array(
                'campaign_id' => 3,
                'question_text' => 'Which types of exercise improve cardiovascular health?',
                'question_type' => 'multiple_select',
                'category' => 'fitness',
                'difficulty' => 'medium',
                'points' => 2,
                'explanation' => 'Walking, running, cycling, and swimming are all excellent cardiovascular exercises.'
            )
        );
        
        foreach ($questions as $question) {
            $result = $wpdb->insert($database->get_table_name('questions'), $question);
            if ($result) {
                $question_id = $wpdb->insert_id;
                $success_count++;
                echo "✅ Added question: " . substr($question['question_text'], 0, 50) . "...<br>";
                
                // Add options for this question
                add_question_options($database, $question_id, $question);
            } else {
                $errors[] = "Failed to add question: " . $question['question_text'] . " - " . $wpdb->last_error;
            }
        }
        
        // 3. Add Sample Gifts
        echo '<div class="step">Step 3: Adding Sample Gifts...</div>';
        
        $gifts = array(
            array(
                'campaign_id' => 1,
                'gift_name' => '10% Health Store Discount',
                'gift_type' => 'discount',
                'gift_value' => '10%',
                'gift_description' => '10% discount on health supplements and wellness products',
                'min_score' => 3,
                'max_score' => 4,
                'max_quantity' => 100,
                'gift_code_prefix' => 'HEALTH10'
            ),
            array(
                'campaign_id' => 1,
                'gift_name' => '50K VND Wellness Voucher',
                'gift_type' => 'voucher',
                'gift_value' => '50000 VND',
                'gift_description' => 'Cash voucher worth 50,000 VND for wellness products',
                'min_score' => 5,
                'max_score' => null,
                'max_quantity' => 25,
                'gift_code_prefix' => 'WELLNESS50K'
            ),
            array(
                'campaign_id' => 2,
                'gift_name' => 'Free Nutrition Consultation',
                'gift_type' => 'product',
                'gift_value' => 'Free 30-min consultation',
                'gift_description' => 'Free 30-minute nutrition consultation with certified dietitian',
                'min_score' => 6,
                'max_score' => null,
                'max_quantity' => 10,
                'gift_code_prefix' => 'NUTRI30'
            ),
            array(
                'campaign_id' => 3,
                'gift_name' => 'Fitness Points Reward',
                'gift_type' => 'points',
                'gift_value' => '100 points',
                'gift_description' => '100 fitness reward points for gym membership discounts',
                'min_score' => 4,
                'max_score' => null,
                'max_quantity' => 50,
                'gift_code_prefix' => 'FIT100'
            )
        );
        
        foreach ($gifts as $gift) {
            $result = $wpdb->insert($database->get_table_name('gifts'), $gift);
            if ($result) {
                $success_count++;
                echo "✅ Added gift: {$gift['gift_name']}<br>";
            } else {
                $errors[] = "Failed to add gift: {$gift['gift_name']} - " . $wpdb->last_error;
            }
        }
        
        // 4. Add Sample Participants
        echo '<div class="step">Step 4: Adding Sample Participants...</div>';
        
        $participants = array(
            array(
                'campaign_id' => 1,
                'session_id' => 'demo_sess_001',
                'participant_name' => 'John Doe',
                'participant_email' => 'john.doe@example.com',
                'participant_phone' => '+84901234567',
                'province' => 'Ho Chi Minh',
                'pharmacy_code' => 'PH001',
                'quiz_status' => 'completed',
                'start_time' => '2024-12-01 14:30:00',
                'end_time' => '2024-12-01 14:35:00',
                'final_score' => 4,
                'total_questions' => 5,
                'completion_time' => 300,
                'gift_id' => 1,
                'gift_status' => 'assigned',
                'completed_at' => '2024-12-01 14:35:00'
            ),
            array(
                'campaign_id' => 1,
                'session_id' => 'demo_sess_002',
                'participant_name' => 'Jane Smith',
                'participant_email' => 'jane.smith@example.com',
                'participant_phone' => '+84901234568',
                'province' => 'Ha Noi',
                'pharmacy_code' => 'PH002',
                'quiz_status' => 'completed',
                'start_time' => '2024-12-01 15:00:00',
                'end_time' => '2024-12-01 15:06:00',
                'final_score' => 5,
                'total_questions' => 5,
                'completion_time' => 360,
                'gift_id' => 2,
                'gift_status' => 'claimed',
                'completed_at' => '2024-12-01 15:06:00'
            ),
            array(
                'campaign_id' => 1,
                'session_id' => 'demo_sess_003',
                'participant_name' => 'Mike Johnson',
                'participant_email' => 'mike.johnson@example.com',
                'participant_phone' => '+84901234569',
                'province' => 'Da Nang',
                'pharmacy_code' => 'PH003',
                'quiz_status' => 'in_progress',
                'start_time' => '2024-12-01 16:00:00',
                'final_score' => 2,
                'total_questions' => 5,
                'gift_status' => 'none'
            ),
            array(
                'campaign_id' => 2,
                'session_id' => 'demo_sess_004',
                'participant_name' => 'Sarah Wilson',
                'participant_email' => 'sarah.wilson@example.com',
                'participant_phone' => '+84901234570',
                'province' => 'Can Tho',
                'pharmacy_code' => 'PH004',
                'quiz_status' => 'completed',
                'start_time' => '2024-12-02 10:00:00',
                'end_time' => '2024-12-02 10:08:00',
                'final_score' => 7,
                'total_questions' => 8,
                'completion_time' => 480,
                'gift_id' => 3,
                'gift_status' => 'assigned',
                'completed_at' => '2024-12-02 10:08:00'
            ),
            array(
                'campaign_id' => 3,
                'session_id' => 'demo_sess_005',
                'participant_name' => 'David Lee',
                'participant_email' => 'david.lee@example.com',
                'participant_phone' => '+84901234571',
                'province' => 'Hai Phong',
                'pharmacy_code' => 'PH005',
                'quiz_status' => 'completed',
                'start_time' => '2024-12-03 09:30:00',
                'end_time' => '2024-12-03 09:38:00',
                'final_score' => 5,
                'total_questions' => 6,
                'completion_time' => 480,
                'gift_id' => 4,
                'gift_status' => 'claimed',
                'completed_at' => '2024-12-03 09:38:00'
            )
        );
        
        foreach ($participants as $participant) {
            $result = $wpdb->insert($database->get_table_name('participants'), $participant);
            if ($result) {
                $success_count++;
                echo "✅ Added participant: {$participant['participant_name']}<br>";
            } else {
                $errors[] = "Failed to add participant: {$participant['participant_name']} - " . $wpdb->last_error;
            }
        }
        
        // 5. Add Sample Analytics
        echo '<div class="step">Step 5: Adding Sample Analytics...</div>';
        
        $analytics = array(
            array(
                'campaign_id' => 1,
                'event_type' => 'complete',
                'participant_id' => 1,
                'session_id' => 'demo_sess_001',
                'event_data' => json_encode(array('score' => 4, 'time' => 300)),
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ),
            array(
                'campaign_id' => 1,
                'event_type' => 'gift_claim',
                'participant_id' => 2,
                'session_id' => 'demo_sess_002',
                'event_data' => json_encode(array('gift_id' => 2, 'gift_code' => 'WELLNESS50K-ABC123')),
                'ip_address' => '192.168.1.101',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
            )
        );
        
        foreach ($analytics as $analytic) {
            $result = $wpdb->insert($database->get_table_name('analytics'), $analytic);
            if ($result) {
                $success_count++;
                echo "✅ Added analytics record<br>";
            }
        }
        
        // Summary
        echo '<div class="success">';
        echo '<h3>🎉 Sample Data Added Successfully!</h3>';
        echo '<p><strong>Total items added:</strong> ' . $success_count . '</p>';
        
        if (!empty($errors)) {
            echo '<h4>⚠️ Some errors occurred:</h4>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul>';
        }
        
        echo '<h4>📊 What was added:</h4>';
        echo '<ul>';
        echo '<li>✅ 3 Sample Campaigns (Health, Nutrition, Fitness)</li>';
        echo '<li>✅ 10 Quiz Questions with various difficulty levels</li>';
        echo '<li>✅ 30+ Question Options (Multiple choice, True/False)</li>';
        echo '<li>✅ 4 Sample Gifts (Discounts, Vouchers, Consultations)</li>';
        echo '<li>✅ 5 Sample Participants with different completion status</li>';
        echo '<li>✅ Analytics tracking data</li>';
        echo '</ul>';
        
        echo '<h4>🎯 Next Steps:</h4>';
        echo '<p>You can now test your plugin features:</p>';
        echo '<ul>';
        echo '<li><a href="/wp-admin/admin.php?page=vefify-campaigns" target="_blank">📈 View Campaigns</a></li>';
        echo '<li><a href="/wp-admin/admin.php?page=vefify-participants" target="_blank">👥 View Participants</a></li>';
        echo '<li><a href="/wp-admin/admin.php?page=vefify-analytics" target="_blank">📊 View Analytics</a></li>';
        echo '</ul>';
        echo '</div>';
        
        echo '<a href="?" class="btn">🔙 Back to Menu</a>';
        
    } catch (Exception $e) {
        echo '<div class="error">';
        echo '<h3>❌ Error Adding Sample Data</h3>';
        echo '<p>' . esc_html($e->getMessage()) . '</p>';
        echo '</div>';
    }
}

/**
 * Add question options based on question type
 */
function add_question_options($database, $question_id, $question) {
    global $wpdb;
    
    $options = array();
    
    switch ($question['question_text']) {
        case 'What is the recommended daily water intake for adults?':
            $options = array(
                array('option_text' => '1 liter per day', 'is_correct' => 0),
                array('option_text' => '2 liters per day', 'is_correct' => 1),
                array('option_text' => '3 liters per day', 'is_correct' => 0),
                array('option_text' => '4 liters per day', 'is_correct' => 0)
            );
            break;
            
        case 'Which vitamin is essential for bone health?':
            $options = array(
                array('option_text' => 'Vitamin A', 'is_correct' => 0),
                array('option_text' => 'Vitamin B12', 'is_correct' => 0),
                array('option_text' => 'Vitamin C', 'is_correct' => 0),
                array('option_text' => 'Vitamin D', 'is_correct' => 1)
            );
            break;
            
        case 'Regular exercise is important for maintaining good health.':
        case 'Fruits and vegetables should make up half of your plate at meals.':
        case 'Strength training should be done at least twice per week.':
            $options = array(
                array('option_text' => 'True', 'is_correct' => 1),
                array('option_text' => 'False', 'is_correct' => 0)
            );
            break;
            
        case 'How many hours of sleep do adults typically need per night?':
            $options = array(
                array('option_text' => '5-6 hours', 'is_correct' => 0),
                array('option_text' => '7-9 hours', 'is_correct' => 1),
                array('option_text' => '10-12 hours', 'is_correct' => 0),
                array('option_text' => '4-5 hours', 'is_correct' => 0)
            );
            break;
            
        case 'Which of these foods are good sources of protein?':
            $options = array(
                array('option_text' => 'Chicken breast', 'is_correct' => 1),
                array('option_text' => 'Fish', 'is_correct' => 1),
                array('option_text' => 'White bread', 'is_correct' => 0),
                array('option_text' => 'Beans and lentils', 'is_correct' => 1)
            );
            break;
            
        case 'Which macronutrient provides the most calories per gram?':
            $options = array(
                array('option_text' => 'Carbohydrates', 'is_correct' => 0),
                array('option_text' => 'Proteins', 'is_correct' => 0),
                array('option_text' => 'Fats', 'is_correct' => 1),
                array('option_text' => 'Vitamins', 'is_correct' => 0)
            );
            break;
            
        case 'How many minutes of moderate exercise per week do experts recommend?':
            $options = array(
                array('option_text' => '75 minutes', 'is_correct' => 0),
                array('option_text' => '150 minutes', 'is_correct' => 1),
                array('option_text' => '300 minutes', 'is_correct' => 0),
                array('option_text' => '60 minutes', 'is_correct' => 0)
            );
            break;
            
        case 'Which types of exercise improve cardiovascular health?':
            $options = array(
                array('option_text' => 'Walking', 'is_correct' => 1),
                array('option_text' => 'Swimming', 'is_correct' => 1),
                array('option_text' => 'Weight lifting only', 'is_correct' => 0),
                array('option_text' => 'Cycling', 'is_correct' => 1)
            );
            break;
            
        default:
            // Default options for any missed questions
            $options = array(
                array('option_text' => 'Option A', 'is_correct' => 1),
                array('option_text' => 'Option B', 'is_correct' => 0)
            );
    }
    
    // Insert options
    foreach ($options as $index => $option) {
        $option_data = array(
            'question_id' => $question_id,
            'option_text' => $option['option_text'],
            'is_correct' => $option['is_correct'],
            'order_index' => $index
        );
        
        $wpdb->insert($database->get_table_name('question_options'), $option_data);
    }
}

/**
 * Clear all data
 */
function clear_all_data($database) {
    echo '<h2>🗑️ Clearing All Data...</h2>';
    
    global $wpdb;
    $tables = array('analytics', 'quiz_sessions', 'participants', 'question_options', 'questions', 'gifts', 'campaigns');
    
    foreach ($tables as $table) {
        $table_name = $database->get_table_name($table);
        if ($table_name) {
            $result = $wpdb->query("DELETE FROM {$table_name}");
            echo "✅ Cleared {$table} table: " . $result . " records deleted<br>";
        }
    }
    
    echo '<div class="success">🎉 All data cleared successfully!</div>';
    echo '<a href="?" class="btn">🔙 Back to Menu</a>';
}
?>