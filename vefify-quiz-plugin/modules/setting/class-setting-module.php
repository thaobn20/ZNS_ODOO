<?php
/**
 * Settings Module
 * File: modules/settings/class-setting-module.php
 * Centralized configuration management for the entire platform
 */

class Vefify_Setting_Module {
    
    private static $instance = null;
    private $model;
    private $manager;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        $this->load_components();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_vefify_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_vefify_reset_settings', array($this, 'ajax_reset_settings'));
    }
    
    private function load_components() {
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/settings/class-setting-model.php';
        require_once VEFIFY_QUIZ_PLUGIN_DIR . 'modules/settings/class-setting-manager.php';
        
        $this->model = new Vefify_Setting_Model();
        if (is_admin()) {
            $this->manager = new Vefify_Setting_Manager();
        }
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'vefify-quiz',
            'Settings',
            '⚙️ Settings',
            'manage_options',
            'vefify-settings',
            array($this, 'admin_page_router')
        );
    }
    
    public function admin_page_router() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        $this->manager->display_settings_page($tab);
    }
    
    public function register_settings() {
        // Register all plugin settings
        $settings_groups = array(
            'vefify_general_settings',
            'vefify_appearance_settings',
            'vefify_notification_settings',
            'vefify_integration_settings',
            'vefify_security_settings',
            'vefify_advanced_settings'
        );
        
        foreach ($settings_groups as $group) {
            register_setting($group, $group);
        }
    }
    
    public function get_module_analytics() {
        $stats = $this->model->get_settings_statistics();
        
        return array(
            'title' => 'Settings & Configuration',
            'description' => 'Centralized platform configuration and customization options',
            'icon' => '⚙️',
            'stats' => array(
                'configured_modules' => array(
                    'label' => 'Configured Modules',
                    'value' => $stats['configured_modules'],
                    'trend' => '100% setup'
                ),
                'active_integrations' => array(
                    'label' => 'Active Integrations',
                    'value' => $stats['active_integrations'],
                    'trend' => '8 services'
                ),
                'customization_level' => array(
                    'label' => 'Customization',
                    'value' => $stats['customization_level'] . '%',
                    'trend' => 'Fully configured'
                )
            ),
            'quick_actions' => array(
                array(
                    'label' => 'General Settings',
                    'url' => admin_url('admin.php?page=vefify-settings'),
                    'class' => 'button-primary'
                ),
                array(
                    'label' => 'Backup Settings',
                    'url' => admin_url('admin.php?page=vefify-settings&tab=backup'),
                    'class' => 'button-secondary'
                )
            )
        );
    }
    
    public function ajax_save_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_settings_ajax')) {
            wp_send_json_error('Security check failed');
        }
        
        $settings_group = sanitize_text_field($_POST['settings_group']);
        $settings_data = $_POST['settings_data'];
        
        $result = $this->model->save_settings($settings_group, $settings_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Settings saved successfully');
    }
    
    public function ajax_reset_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'vefify_settings_ajax')) {
            wp_send_json_error('Security check failed');
        }
        
        $result = $this->model->reset_to_defaults();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Settings reset to defaults');
    }
    
    public function get_model() {
        return $this->model;
    }
    
    public function get_manager() {
        return $this->manager;
    }
}