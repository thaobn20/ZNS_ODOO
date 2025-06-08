<?php
/**
 * Plugin Name: Simple Quiz Test
 * Plugin URI: https://yourwebsite.com
 * Description: Test plugin to verify menu functionality
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Simple test class
class SimpleQuizTest {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function add_menu() {
        add_menu_page(
            'Test Quiz',
            'Test Quiz',
            'manage_options',
            'test-quiz',
            array($this, 'admin_page'),
            'dashicons-feedback',
            30
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>🎉 Success!</h1>
            <p>If you can see this page, the plugin system is working correctly.</p>
            <p>This means there might be an issue with the main Advanced Quiz Manager plugin files.</p>
            <h2>Next Steps:</h2>
            <ol>
                <li>Deactivate this test plugin</li>
                <li>Check the main plugin file structure</li>
                <li>Look for PHP errors in the error log</li>
                <li>Make sure all files are uploaded correctly</li>
            </ol>
        </div>
        <?php
    }
    
    public function activate() {
        // Simple activation test
        add_option('simple_quiz_test_activated', time());
    }
}

// Initialize the test plugin
new SimpleQuizTest();
?>