<?php

/**
 * API Handler for Maintenance Monday WordPress Plugin
 */

class MaintenanceMonday_API {
    
    /**
     * Initialize the API
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('maintenance-monday/v1', '/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'health_check'),
            'permission_callback' => '__return_true', // Public endpoint
        ));
        
        register_rest_route('maintenance-monday/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_status'),
            'permission_callback' => array($this, 'check_api_key'),
        ));
    }
    
    /**
     * Health check endpoint - public, no authentication required
     */
    public function health_check() {
        return array(
            'status' => 'ok',
            'plugin_version' => MAINTENANCE_MONDAY_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'site_url' => get_site_url(),
            'timestamp' => current_time('c'),
        );
    }
    
    /**
     * Get plugin status - requires API key authentication
     */
    public function get_status() {
        $api_key = get_option('maintenance_monday_api_key');
        $api_url = get_option('maintenance_monday_api_url');
        $site_id = get_option('maintenance_monday_site_id');
        $enabled = get_option('maintenance_monday_enabled');
        
        return array(
            'status' => 'ok',
            'plugin_version' => MAINTENANCE_MONDAY_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'site_url' => get_site_url(),
            'api_configured' => !empty($api_key) && !empty($api_url),
            'site_id' => $site_id,
            'enabled' => $enabled === '1',
            'last_check' => get_option('maintenance_monday_last_check', ''),
            'timestamp' => current_time('c'),
        );
    }

    /**
     * Update plugin status on Laravel side
     */
    public function update_laravel_status() {
        $api_key = get_option('maintenance_monday_api_key');
        $api_url = get_option('maintenance_monday_api_url');
        $site_id = get_option('maintenance_monday_site_id');
        
        // Debug: Log the values we're working with
        error_log('Maintenance Monday API: update_laravel_status called');
        error_log('Maintenance Monday API: api_key = ' . (!empty($api_key) ? 'set' : 'empty'));
        error_log('Maintenance Monday API: api_url = ' . $api_url);
        error_log('Maintenance Monday API: site_id = ' . $site_id);
        
        if (empty($api_key) || empty($api_url) || empty($site_id)) {
            error_log('Maintenance Monday API: Missing required data - api_key: ' . (!empty($api_key) ? 'set' : 'empty') . ', api_url: ' . (!empty($api_url) ? 'set' : 'empty') . ', site_id: ' . (!empty($site_id) ? 'set' : 'empty'));
            return false;
        }
        
        $status_data = array(
            'site_id' => $site_id,
            'plugin_installed' => true,
            'plugin_version' => MAINTENANCE_MONDAY_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'message' => 'Plugin active and connected',
        );
        
        error_log('Maintenance Monday API: Sending status data: ' . wp_json_encode($status_data));
        error_log('Maintenance Monday API: Making request to: ' . $api_url . '/api/wordpress/plugin-status');
        
        $response = wp_remote_post($api_url . '/api/wordpress/plugin-status', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($status_data),
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            error_log('Maintenance Monday API: wp_remote_post failed: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log('Maintenance Monday API: Response code: ' . $response_code);
        error_log('Maintenance Monday API: Response body: ' . $response_body);
        
        return $response_code === 200;
    }
    
    /**
     * Check if API key is valid
     */
    public function check_api_key() {
        $headers = getallheaders();
        $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (empty($auth_header)) {
            return false;
        }
        
        // Check if it's a Bearer token
        if (strpos($auth_header, 'Bearer ') === 0) {
            $token = substr($auth_header, 7);
        } else {
            $token = $auth_header;
        }
        
        $stored_key = get_option('maintenance_monday_api_key');
        return $token === $stored_key;
    }

    /**
     * Test connection to Laravel API
     */
    public function test_connection() {
        $api_url = get_option('maintenance_monday_api_url');
        $api_key = get_option('maintenance_monday_api_key');
        
        if (empty($api_url) || empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('API URL and API Key are required', 'maintenance-monday')
            );
        }

        $response = wp_remote_get($api_url . '/api/test-connection', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
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
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : sprintf(__('Connection failed with status %d', 'maintenance-monday'), $response_code);
            
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
    }

    /**
     * Get available sites from Laravel API
     */
    public function get_sites() {
        $api_url = get_option('maintenance_monday_api_url');
        $api_key = get_option('maintenance_monday_api_key');
        
        if (empty($api_url) || empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('API URL and API Key are required', 'maintenance-monday')
            );
        }

        $response = wp_remote_get($api_url . '/api/sites', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
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
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : sprintf(__('Failed to fetch sites. Status: %d', 'maintenance-monday'), $response_code);
            
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
    }
}

// Initialize the API
new MaintenanceMonday_API();
