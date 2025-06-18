<?php
/**
 * Debug Script - Find Duplicate Class Declarations
 * Create this as: wp-content/plugins/vefify-quiz-plugin/debug-classes.php
 * Run it by visiting: yoursite.com/wp-content/plugins/vefify-quiz-plugin/debug-classes.php
 */

// Check if we can access WordPress
if (!defined('ABSPATH')) {
    // Load WordPress if not already loaded
    require_once '../../../wp-load.php';
}

echo "<h1>🔍 Vefify Quiz Plugin - Class Declaration Debug</h1>";

// Define the plugin directory
$plugin_dir = dirname(__FILE__);
echo "<p><strong>Plugin Directory:</strong> " . $plugin_dir . "</p>";

// Files to check for class declarations
$files_to_check = array(
    'modules/questions/class-question-module.php',
    'modules/questions/class-question-module.php.bk',
    'modules/questions/class-question-model.php',
    'modules/questions/class-question-model.php.bk',
    'modules/questions/class-question-bank.php',
    'modules/questions/class-question-bank.php.bk',
    'includes/class-analytics-summaries.php'
);

echo "<h2>📁 File Existence Check</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>File</th><th>Exists</th><th>Size</th><th>Modified</th></tr>";

foreach ($files_to_check as $file) {
    $full_path = $plugin_dir . '/' . $file;
    $exists = file_exists($full_path);
    $size = $exists ? filesize($full_path) : 'N/A';
    $modified = $exists ? date('Y-m-d H:i:s', filemtime($full_path)) : 'N/A';
    
    echo "<tr>";
    echo "<td>{$file}</td>";
    echo "<td>" . ($exists ? '✅ Yes' : '❌ No') . "</td>";
    echo "<td>{$size} bytes</td>";
    echo "<td>{$modified}</td>";
    echo "</tr>";
}

echo "</table>";

// Classes to look for
$classes_to_find = array(
    'Vefify_Question_Module',
    'Vefify_Question_Model', 
    'Vefify_Question_Bank',
    'Vefify_Question_Endpoints'
);

echo "<h2>🔍 Class Declaration Search</h2>";

foreach ($classes_to_find as $class_name) {
    echo "<h3>Searching for: <code>{$class_name}</code></h3>";
    
    $found_in_files = array();
    
    foreach ($files_to_check as $file) {
        $full_path = $plugin_dir . '/' . $file;
        
        if (file_exists($full_path)) {
            $content = file_get_contents($full_path);
            
            // Look for class declarations
            if (preg_match('/class\s+' . preg_quote($class_name) . '\s*\{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $line_number = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
                
                $found_in_files[] = array(
                    'file' => $file,
                    'line' => $line_number,
                    'declaration' => trim($matches[0][0])
                );
            }
        }
    }
    
    if (empty($found_in_files)) {
        echo "<p>❌ <strong>{$class_name}</strong> not found in any files</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>File</th><th>Line</th><th>Declaration</th></tr>";
        
        foreach ($found_in_files as $found) {
            $status = count($found_in_files) > 1 ? '🔴 DUPLICATE' : '✅ OK';
            echo "<tr>";
            echo "<td>{$status} {$found['file']}</td>";
            echo "<td>{$found['line']}</td>";
            echo "<td><code>{$found['declaration']}</code></td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        if (count($found_in_files) > 1) {
            echo "<p><strong>⚠️ PROBLEM:</strong> <code>{$class_name}</code> is declared in " . count($found_in_files) . " files!</p>";
        }
    }
}

echo "<h2>📋 Recommendations</h2>";

// Check for common issues
$has_backup_files = false;
$duplicate_classes = false;

foreach ($files_to_check as $file) {
    if (strpos($file, '.bk') !== false && file_exists($plugin_dir . '/' . $file)) {
        $has_backup_files = true;
        break;
    }
}

// Count duplicates
foreach ($classes_to_find as $class_name) {
    $count = 0;
    foreach ($files_to_check as $file) {
        $full_path = $plugin_dir . '/' . $file;
        if (file_exists($full_path)) {
            $content = file_get_contents($full_path);
            if (preg_match('/class\s+' . preg_quote($class_name) . '\s*\{/', $content)) {
                $count++;
            }
        }
    }
    if ($count > 1) {
        $duplicate_classes = true;
        break;
    }
}

echo "<ul>";

if ($has_backup_files) {
    echo "<li>🔴 <strong>Remove backup files (.bk)</strong> - They may be causing conflicts</li>";
}

if ($duplicate_classes) {
    echo "<li>🔴 <strong>Fix duplicate class declarations</strong> - Each class should only be declared once</li>";
}

echo "<li>✅ <strong>Check error logs</strong> for more details in: <code>/wp-content/debug.log</code></li>";
echo "<li>✅ <strong>Use only one version</strong> of each file (not both original and fixed versions)</li>";
echo "</ul>";

echo "<h2>🛠️ Current WordPress Status</h2>";
echo "<p><strong>WordPress Version:</strong> " . get_bloginfo('version') . "</p>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>Plugin Active:</strong> " . (is_plugin_active('vefify-quiz-plugin/vefify-quiz-plugin.php') ? 'Yes' : 'No') . "</p>";

// Check if classes are currently loaded in memory
echo "<h2>🧠 Classes Currently in Memory</h2>";
echo "<ul>";
foreach ($classes_to_find as $class_name) {
    $exists = class_exists($class_name, false); // false = don't autoload
    echo "<li><code>{$class_name}</code>: " . ($exists ? '✅ Loaded' : '❌ Not loaded') . "</li>";
}
echo "</ul>";

echo "<p><em>Debug completed at: " . date('Y-m-d H:i:s') . "</em></p>";
?>