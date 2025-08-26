<?php
/**
 * Plugin Name: Maintenance Monday
 * Plugin URI: https://adcobo.com
 * Description: Connect your WordPress site to adcobo Maintenance Monday.
 * Version: 1.0.6
 * Author: adcobo ApS
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: maintenance-monday
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MAINTENANCE_MONDAY_VERSION', '1.0.6');
define('MAINTENANCE_MONDAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAINTENANCE_MONDAY_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include required files
require_once MAINTENANCE_MONDAY_PLUGIN_PATH . 'includes/plugin-config.php';
require_once MAINTENANCE_MONDAY_PLUGIN_PATH . 'includes/class-maintenance-monday-api.php';
require_once MAINTENANCE_MONDAY_PLUGIN_PATH . 'includes/class-maintenance-monday-dashboard.php';
require_once MAINTENANCE_MONDAY_PLUGIN_PATH . 'includes/class-maintenance-monday-settings.php';
require_once MAINTENANCE_MONDAY_PLUGIN_PATH . 'includes/class-maintenance-monday-ajax.php';
require_once MAINTENANCE_MONDAY_PLUGIN_PATH . 'includes/class-maintenance-monday-updater.php';

/**
 * Main plugin class
 */
class MaintenanceMonday {

    /**
     * Single instance of the plugin
     */
    private static $instance = null;

    /**
     * Get single instance of the plugin
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize the plugin
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_ajax_handler();
        $this->init_updater();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'update_laravel_status_on_activation'));
    }

    /**
     * Initialize AJAX handler
     */
    private function init_ajax_handler() {
        if (is_admin()) {
            new MaintenanceMonday_AJAX();
        }
    }

    /**
     * Initialize plugin updater
     */
    private function init_updater() {
        new MaintenanceMonday_Updater(__FILE__);
    }

    /**
     * Update Laravel status when plugin is activated
     */
    public function update_laravel_status_on_activation() {
        $api = new MaintenanceMonday_API();
        $api->update_laravel_status();
    }

    /**
     * Plugin initialization
     */
    public function init() {
        // Load text domain for internationalization
        load_plugin_textdomain('maintenance-monday', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Maintenance Monday Settings', 'maintenance-monday'),
            __('Maintenance Monday', 'maintenance-monday'),
            'manage_options',
            'maintenance-monday-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Settings page callback
     */
    public function settings_page() {
        $settings = new MaintenanceMonday_Settings();
        $settings->render_settings_page();
    }

    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'maintenance_monday_updates',
            __('Maintenance Monday - Quick Update', 'maintenance-monday'),
            array($this, 'dashboard_widget_callback'),
            array($this, 'dashboard_widget_control_callback')
        );
    }

    /**
     * Dashboard widget callback
     */
    public function dashboard_widget_callback() {
        $dashboard = new MaintenanceMonday_Dashboard();
        $dashboard->render_dashboard_widget();
    }

    /**
     * Dashboard widget control callback
     */
    public function dashboard_widget_control_callback() {
        $dashboard = new MaintenanceMonday_Dashboard();
        $dashboard->render_dashboard_widget_control();
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on dashboard and settings pages
        if ($hook === 'index.php' || $hook === 'settings_page_maintenance-monday-settings') {
            wp_enqueue_style(
                'maintenance-monday-admin',
                MAINTENANCE_MONDAY_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                MAINTENANCE_MONDAY_VERSION
            );
        }
    }
}

/**
 * Initialize the plugin
 */
function maintenance_monday_init() {
    return MaintenanceMonday::getInstance();
}

// Start the plugin
add_action('plugins_loaded', 'maintenance_monday_init');

/**
 * Activation hook
 */
function maintenance_monday_activate() {
    // Create plugin directories if they don't exist
    $plugin_path = plugin_dir_path(__FILE__);
    $directories = array(
        $plugin_path . 'assets',
        $plugin_path . 'assets/css',
        $plugin_path . 'assets/js',
        $plugin_path . 'includes'
    );

    foreach ($directories as $directory) {
        if (!file_exists($directory)) {
            wp_mkdir_p($directory);
        }
    }

    // Create default options with pre-populated values
    add_option('maintenance_monday_api_url', 'https://maintenance.adcobo.com');
    add_option('maintenance_monday_api_key', 'mm_f1N9yRZYZs7DTBF2CThpusTXReDjWrl6');
    add_option('maintenance_monday_site_id', '');
    add_option('maintenance_monday_enabled', '1'); // Enable by default

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'maintenance_monday_activate');

/**
 * Deactivation hook
 */
function maintenance_monday_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'maintenance_monday_deactivate');
