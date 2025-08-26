<?php
/**
 * Maintenance Monday AJAX Handler Class
 * Handles AJAX requests from the frontend
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MaintenanceMonday_AJAX {

    /**
     * API instance
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new MaintenanceMonday_API();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_maintenance_monday_send_update', array($this, 'handle_send_update'));
        add_action('wp_ajax_maintenance_monday_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_maintenance_monday_fetch_tags', array($this, 'handle_fetch_tags'));
        add_action('wp_ajax_maintenance_monday_fetch_site_status', array($this, 'handle_fetch_site_status'));
        add_action('wp_ajax_maintenance_monday_get_php_version_info', array($this, 'handle_get_php_version_info'));
    }

    /**
     * Handle send update AJAX request
     */
    public function handle_send_update() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'maintenance_monday_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'maintenance-monday')));
            return;
        }

        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'maintenance-monday')));
            return;
        }

        // Check if plugin is enabled
        if (get_option('maintenance_monday_enabled') !== '1') {
            wp_send_json_error(array('message' => __('Plugin is disabled', 'maintenance-monday')));
            return;
        }

        // Validate required fields
        if (empty($_POST['update_description'])) {
            wp_send_json_error(array('message' => __('Update description is required', 'maintenance-monday')));
            return;
        }

        // Get site_id from settings
        $site_id = get_option('maintenance_monday_site_id');
        if (empty($site_id)) {
            wp_send_json_error(array('message' => __('Site ID is required. Please configure it in the plugin settings.', 'maintenance-monday')));
            return;
        }

        // Prepare update data
        $update_data = array(
            'site_id' => $site_id,
            'description' => sanitize_textarea_field($_POST['update_description'] ?? ''),
            'performed_by' => wp_get_current_user()->display_name,
            'tags' => sanitize_text_field($_POST['update_tags'] ?? ''),
        );

        // Send update via API
        $result = $this->api->send_update($update_data);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'data' => $result['data']
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Handle test connection AJAX request
     */
    public function handle_test_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'maintenance_monday_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'maintenance-monday')));
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'maintenance-monday')));
            return;
        }

        // Test connection
        $result = $this->api->test_connection();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'data' => $result['data']
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Handle fetch tags AJAX request
     */
    public function handle_fetch_tags() {
        // Debug logging
        error_log('Maintenance Monday: Fetch tags request received');
        error_log('POST data: ' . print_r($_POST, true));

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'maintenance_monday_nonce')) {
            error_log('Maintenance Monday: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed', 'maintenance-monday')));
            return;
        }

        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            error_log('Maintenance Monday: Insufficient permissions');
            wp_send_json_error(array('message' => __('Insufficient permissions', 'maintenance-monday')));
            return;
        }

        // Fetch tags
        $result = $this->api->get_tags();
        error_log('Maintenance Monday: API result: ' . print_r($result, true));

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => sprintf(__('Successfully fetched %d tags', 'maintenance-monday'), count($result['data'])),
                'data' => $result['data']
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Handle get PHP version info AJAX request
     */
    public function handle_get_php_version_info() {
        // Debug logging
        error_log('Maintenance Monday: Get PHP version info request received');
        error_log('POST data: ' . print_r($_POST, true));

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'maintenance_monday_nonce')) {
            error_log('Maintenance Monday: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed', 'maintenance-monday')));
            return;
        }

        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            error_log('Maintenance Monday: Insufficient permissions');
            wp_send_json_error(array('message' => __('Insufficient permissions', 'maintenance-monday')));
            return;
        }

        // Get PHP version
        $version = sanitize_text_field($_POST['version'] ?? '');
        if (empty($version)) {
            error_log('Maintenance Monday: No PHP version provided');
            wp_send_json_error(array('message' => __('PHP version is required', 'maintenance-monday')));
            return;
        }

        // Fetch PHP version info from Laravel API
        error_log('Maintenance Monday: Calling API get_php_version_info with version: ' . $version);
        $result = $this->api->get_php_version_info($version);
        error_log('Maintenance Monday: API result: ' . print_r($result, true));

        if ($result['success']) {
            error_log('Maintenance Monday: API call successful, data: ' . print_r($result['data'], true));
            wp_send_json_success($result['data']);
        } else {
            error_log('Maintenance Monday: API call failed: ' . $result['message']);
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Handle fetch site status AJAX request
     */
    public function handle_fetch_site_status() {
        // Debug logging
        error_log('Maintenance Monday: Fetch site status request received');
        error_log('POST data: ' . print_r($_POST, true));

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'maintenance_monday_nonce')) {
            error_log('Maintenance Monday: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed', 'maintenance-monday')));
            return;
        }

        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            error_log('Maintenance Monday: Insufficient permissions');
            wp_send_json_error(array('message' => __('Insufficient permissions', 'maintenance-monday')));
            return;
        }

        // Get site ID from settings
        $site_id = get_option('maintenance_monday_site_id');
        error_log('Maintenance Monday: Site ID from settings: ' . $site_id);

        if (empty($site_id)) {
            error_log('Maintenance Monday: No site ID configured');
            wp_send_json_error(array('message' => __('No site ID configured', 'maintenance-monday')));
            return;
        }

        // Fetch site information from Laravel API
        error_log('Maintenance Monday: Calling API get_site with ID: ' . $site_id);
        $result = $this->api->get_site($site_id);
        error_log('Maintenance Monday: API result: ' . print_r($result, true));

        if ($result['success']) {
            error_log('Maintenance Monday: API call successful, data: ' . print_r($result['data'], true));
            wp_send_json_success(array(
                'message' => 'Site status retrieved successfully',
                'data' => $result['data']
            ));
        } else {
            error_log('Maintenance Monday: API call failed: ' . $result['message']);
            wp_send_json_error(array('message' => $result['message']));
        }
    }


}

// Initialize AJAX handler
new MaintenanceMonday_AJAX();
