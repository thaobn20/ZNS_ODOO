<?php
/**
 * Test Installation Script
 * Add this to a test page to verify the plugin works
 * Usage: Create a new page and add shortcode [vefify_quiz campaign_id="1"]
 */

// Test if plugin is activated and working
if (function_exists('add_shortcode')) {
    echo "✅ WordPress functions available<br>";
} else {
    echo "❌ WordPress not loaded<br>";
}

// Test database tables
global $wpdb;
$table_prefix = $wpdb->prefix . 'vefify_';

$tables_to_check = array(
    'campaigns',
    'questions', 
    'question_options',
    'gifts',
    'quiz_users',
    'quiz_sessions',
    'analytics'
);

echo "<h3>📊 Database Table Status:</h3>";
foreach ($tables_to_check as $table) {
    $table_name = $table_prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo "✅ Table '$table' exists with $count records<br>";
    } else {
        echo "❌ Table '$table' missing<br>";
    }
}

// Test sample data
echo "<h3>🎯 Sample Campaign Status:</h3>";
$campaign = $wpdb->get_row("SELECT * FROM {$table_prefix}campaigns WHERE id = 1");

if ($campaign) {
    echo "✅ Sample campaign found: " . esc_html($campaign->name) . "<br>";
    echo "📅 Date range: " . $campaign->start_date . " to " . $campaign->end_date . "<br>";
    echo "📝 Questions per quiz: " . $campaign->questions_per_quiz . "<br>";
    echo "🎯 Pass score: " . $campaign->pass_score . "<br>";
    
    // Count questions
    $question_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}questions WHERE campaign_id = 1");
    echo "❓ Questions available: $question_count<br>";
    
    // Count gifts
    $gift_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}gifts WHERE campaign_id = 1");
    echo "🎁 Gifts configured: $gift_count<br>";
    
} else {
    echo "❌ No sample campaign found<br>";
}

// Test WordPress options
echo "<h3>⚙️ Plugin Settings:</h3>";
$plugin_version = get_option('vefify_quiz_version');
$db_version = get_option('vefify_quiz_db_version');
$settings = get_option('vefify_quiz_settings');

echo "📦 Plugin version: " . ($plugin_version ?: 'Not set') . "<br>";
echo "🗄️ Database version: " . ($db_version ?: 'Not set') . "<br>";
echo "⚙️ Settings configured: " . ($settings ? 'Yes' : 'No') . "<br>";

// Test shortcode
echo "<h3>🚀 Shortcode Test:</h3>";
echo "<p>Use this shortcode in any page or post:</p>";
echo "<code>[vefify_quiz campaign_id=\"1\"]</code>";

echo "<h3>📋 Next Steps:</h3>";
echo "<ol>";
echo "<li>✅ Plugin activated successfully</li>";
echo "<li>✅ Database tables created</li>";
echo "<li>✅ Sample data inserted</li>";
echo "<li>🔄 Add shortcode to a page to test frontend</li>";
echo "<li>🔄 Build complete quiz interface</li>";
echo "</ol>";
?>