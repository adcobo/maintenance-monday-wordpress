<?php
/**
 * Maintenance Monday API Class
 * Handles communication with the Laravel Maintenance Monday system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MaintenanceMonday_API {

    /**
     * API base URL
     */
    private $api_url;

    /**
     * API key for authentication
     */
    private $api_key;

    /**
     * Site ID to connect to
     */
    private $site_id;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_url = get_option('maintenance_monday_api_url');
        $this->api_key = get_option('maintenance_monday_api_key');
        $this->site_id = get_option('maintenance_monday_site_id');
    }

    /**
     * Test connection to Laravel API
     */
    public function test_connection() {
        if (empty($this->api_url) || empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('API URL and API Key are required', 'maintenance-monday')
            );
        }

        $response = wp_remote_get($this->api_url . '/api/test-connection', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            $data = json_decode($body, true);
            return array(
                'success' => true,
                'message' => __('Connection successful!', 'maintenance-monday'),
                'data' => $data
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('Connection failed with status %d', 'maintenance-monday'), $response_code)
            );
        }
    }



    /**
     * Get available sites from Laravel API
     */
    public function get_sites() {
        if (empty($this->api_url) || empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('API URL and API Key are required', 'maintenance-monday')
            );
        }

        $response = wp_remote_get($this->api_url . '/api/sites', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            $data = json_decode($body, true);
            return array(
                'success' => true,
                'data' => $data
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to fetch sites. Status: %d', 'maintenance-monday'), $response_code)
            );
        }
    }

    /**
     * Get available tags from Laravel API
     */
    public function get_tags() {
        error_log('Maintenance Monday API: get_tags called');
        error_log('API URL: ' . $this->api_url);
        error_log('API Key: ' . substr($this->api_key, 0, 8) . '...');

        if (empty($this->api_url) || empty($this->api_key)) {
            error_log('Maintenance Monday API: Missing API URL or Key');
            return array(
                'success' => false,
                'message' => __('API URL and API Key are required', 'maintenance-monday')
            );
        }

        $url = $this->api_url . '/api/tags';
        error_log('Maintenance Monday API: Making request to: ' . $url);

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            error_log('Maintenance Monday API: Request failed: ' . $response->get_error_message());
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        error_log('Maintenance Monday API: Response code: ' . $response_code);
        error_log('Maintenance Monday API: Response body: ' . $body);

        if ($response_code === 200) {
            $data = json_decode($body, true);
            error_log('Maintenance Monday API: Decoded data: ' . print_r($data, true));
            return array(
                'success' => true,
                'data' => $data
            );
        } else {
            error_log('Maintenance Monday API: Request failed with code: ' . $response_code);
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to fetch tags. Status: %d', 'maintenance-monday'), $response_code)
            );
        }
    }

    /**
     * Send update to Laravel API
     */
    public function send_update($update_data) {
        if (empty($this->api_url) || empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('API URL and API Key are required', 'maintenance-monday')
            );
        }

        // Use site_id from update_data, fallback to settings
        $site_id = !empty($update_data['site_id']) ? $update_data['site_id'] : $this->site_id;

        if (empty($site_id)) {
            return array(
                'success' => false,
                'message' => __('Site ID is required', 'maintenance-monday')
            );
        }

        // Get current user information
        $current_user = wp_get_current_user();

        // Get current PHP version (only major.minor)
        $full_php_version = phpversion();
        $php_version_parts = explode('.', $full_php_version);
        $php_version = $php_version_parts[0] . '.' . $php_version_parts[1];

        // Prepare the data
        $data = array(
            'site_id' => $site_id,
            'description' => sanitize_textarea_field($update_data['description'] ?? ''),
            'tags' => sanitize_text_field($update_data['tags'] ?? ''),
            'performed_by' => sanitize_text_field($update_data['performed_by'] ?? $current_user->display_name),
            'user_email' => sanitize_email($current_user->user_email),
            'performed_at' => current_time('mysql'),
            'php_version' => $php_version,
            'server_info' => array(
                'php_version_full' => $full_php_version,
                'php_version' => $php_version,
                'wordpress_version' => get_bloginfo('version'),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            ),
        );

        $response = wp_remote_post($this->api_url . '/api/updates', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($data),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code === 201 || $response_code === 200) {
            $response_data = json_decode($body, true);
            return array(
                'success' => true,
                'message' => __('Update sent successfully!', 'maintenance-monday'),
                'data' => $response_data
            );
        } else {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : sprintf(__('Failed to send update. Status: %d', 'maintenance-monday'), $response_code);

            return array(
                'success' => false,
                'message' => $error_message
            );
        }
    }

    /**
     * Get specific site information from Laravel API
     */
    public function get_site($site_id) {
        if (empty($this->api_url) || empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('API URL and API Key are required', 'maintenance-monday')
            );
        }

        if (empty($site_id)) {
            return array(
                'success' => false,
                'message' => __('Site ID is required', 'maintenance-monday')
            );
        }

        $response = wp_remote_get($this->api_url . '/api/sites/' . $site_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            $data = json_decode($body, true);
            return array(
                'success' => true,
                'data' => $data
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to fetch site information. Status: %d', 'maintenance-monday'), $response_code)
            );
        }
    }

    /**
     * Get PHP version support information from Laravel API
     */
    public function get_php_version_info($version) {
        if (empty($this->api_url) || empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('API URL and API Key are required', 'maintenance-monday')
            );
        }

        if (empty($version)) {
            return array(
                'success' => false,
                'message' => __('PHP version is required', 'maintenance-monday')
            );
        }

        $response = wp_remote_get($this->api_url . '/api/php-version-info?version=' . urlencode($version), array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            $data = json_decode($body, true);
            return array(
                'success' => true,
                'data' => $data
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to get PHP version info. Status: %d', 'maintenance-monday'), $response_code)
            );
        }
    }

    /**
     * Validate API credentials
     */
    public function validate_credentials() {
        return $this->test_connection();
    }
}
