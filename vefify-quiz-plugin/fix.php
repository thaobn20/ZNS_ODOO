<?php
/**
 * 🔧 DATABASE UPDATE SCRIPT
 * Add missing columns to wp_vefify_participants table
 * 
 * USAGE:
 * 1. Upload this file to your plugin directory
 * 2. Access it via browser: yoursite.com/wp-content/plugins/vefify-quiz-plugin/update-database.php
 * 3. Run it once to add missing columns
 * 4. Delete this file after running
 */

// Load WordPress
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

global $wpdb;

echo '<h1>🔧 Vefify Quiz Database Update</h1>';
echo '<style>body { font-family: sans-serif; padding: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>';

$participants_table = $wpdb->prefix . 'vefify_participants';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$participants_table'") === $participants_table;

if (!$table_exists) {
    echo '<p class="error">❌ Table ' . $participants_table . ' does not exist!</p>';
    echo '<p>Please run the main database creation script first.</p>';
    exit;
}

echo '<h2>1. Current Table Structure</h2>';
$current_columns = $wpdb->get_results("SHOW COLUMNS FROM $participants_table");
echo '<p><strong>Existing columns:</strong></p>';
echo '<ul>';
foreach ($current_columns as $column) {
    echo '<li>' . esc_html($column->Field) . ' (' . esc_html($column->Type) . ')</li>';
}
echo '</ul>';

// Define columns that should exist
$required_columns = array(
    'company' => 'VARCHAR(255) DEFAULT NULL',
    'occupation' => 'VARCHAR(100) DEFAULT NULL', 
    'age' => 'INT(11) DEFAULT NULL',
    'user_agent' => 'TEXT DEFAULT NULL',
    'quiz_status' => 'ENUM("registered", "started", "completed") DEFAULT "registered"'
);

echo '<h2>2. Adding Missing Columns</h2>';

$existing_column_names = array();
foreach ($current_columns as $column) {
    $existing_column_names[] = $column->Field;
}

$added_columns = 0;

foreach ($required_columns as $column_name => $column_definition) {
    if (!in_array($column_name, $existing_column_names)) {
        echo '<p class="info">Adding column: ' . $column_name . '</p>';
        
        $sql = "ALTER TABLE $participants_table ADD COLUMN $column_name $column_definition";
        $result = $wpdb->query($sql);
        
        if ($result !== false) {
            echo '<p class="success">✅ Added column: ' . $column_name . '</p>';
            $added_columns++;
        } else {
            echo '<p class="error">❌ Failed to add column: ' . $column_name . ' - ' . $wpdb->last_error . '</p>';
        }
    } else {
        echo '<p class="success">✅ Column already exists: ' . $column_name . '</p>';
    }
}

echo '<h2>3. Updated Table Structure</h2>';
$updated_columns = $wpdb->get_results("SHOW COLUMNS FROM $participants_table");
echo '<p><strong>Current columns after update:</strong></p>';
echo '<ul>';
foreach ($updated_columns as $column) {
    $is_new = in_array($column->Field, array_keys($required_columns));
    echo '<li>' . esc_html($column->Field) . ' (' . esc_html($column->Type) . ')' . 
         ($is_new ? ' <strong style="color: green;">← NEW</strong>' : '') . '</li>';
}
echo '</ul>';

echo '<h2>4. Test Insert</h2>';
echo '<p class="info">Testing if all columns work with a sample insert...</p>';

// Test insert with all columns
$test_data = array(
    'campaign_id' => 1,
    'name' => 'Test User',
    'email' => 'test@example.com',
    'phone' => '0123456789',
    'company' => 'Test Company',
    'province' => 'hanoi',
    'pharmacy_code' => 'XX-123456',
    'occupation' => 'pharmacist',
    'age' => 30,
    'session_token' => 'test_' . time(),
    'registered_at' => current_time('mysql'),
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Test Browser',
    'quiz_status' => 'registered'
);

$test_result = $wpdb->insert($participants_table, $test_data);

if ($test_result !== false) {
    $test_id = $wpdb->insert_id;
    echo '<p class="success">✅ Test insert successful! ID: ' . $test_id . '</p>';
    
    // Clean up test record
    $wpdb->delete($participants_table, array('id' => $test_id));
    echo '<p class="info">Test record cleaned up.</p>';
} else {
    echo '<p class="error">❌ Test insert failed: ' . $wpdb->last_error . '</p>';
}

echo '<h2>5. Summary</h2>';
echo '<p><strong>Columns added:</strong> ' . $added_columns . '</p>';
echo '<p><strong>Status:</strong> ' . ($added_columns > 0 || $test_result !== false ? 
    '<span class="success">✅ Database update successful!</span>' : 
    '<span class="error">❌ Database update had issues</span>') . '</p>';

echo '<h2>6. Next Steps</h2>';
echo '<ol>';
echo '<li>✅ Database columns updated</li>';
echo '<li>🔄 Now update your shortcode file to handle 404 redirects</li>';
echo '<li>🧪 Test the quiz form with all fields</li>';
echo '<li>🗑️ Delete this file for security</li>';
echo '</ol>';

echo '<p style="background: #ffffcc; padding: 15px; border-radius: 5px;"><strong>⚠️ Security Note:</strong> Please delete this file after running it.</p>';

?>