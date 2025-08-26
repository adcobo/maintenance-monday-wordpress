<?php
/**
 * Maintenance Monday Settings Class
 * Handles the plugin settings page and configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MaintenanceMonday_Settings {

    /**
     * API instance
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new MaintenanceMonday_API();
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['maintenance_monday_nonce'], 'maintenance_monday_settings')) {
            $this->save_settings();
        }

        // Handle test connection
        if (isset($_POST['test_connection']) && wp_verify_nonce($_POST['maintenance_monday_nonce'], 'maintenance_monday_settings')) {
            $test_result = $this->api->test_connection();
        }

        // Handle fetch sites
        if (isset($_POST['fetch_sites']) && wp_verify_nonce($_POST['maintenance_monday_nonce'], 'maintenance_monday_settings')) {
            $sites_result = $this->api->get_sites();
        }

        // Enqueue scripts for settings page
        wp_enqueue_script(
            'maintenance-monday-settings',
            MAINTENANCE_MONDAY_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MAINTENANCE_MONDAY_VERSION,
            true
        );

        wp_localize_script('maintenance-monday-settings', 'maintenanceMondayAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('maintenance_monday_nonce'),
            'strings' => array(
                'sending' => __('Sending...', 'maintenance-monday'),
                'success' => __('Connection successful!', 'maintenance-monday'),
                'error' => __('Connection failed. Please try again.', 'maintenance-monday'),
            )
        ));

        // Get current settings with defaults (in case plugin was already activated)
        $api_url = get_option('maintenance_monday_api_url', 'http://localhost:8000');
        $api_key = get_option('maintenance_monday_api_key', 'mm_f1N9yRZYZs7DTBF2CThpusTXReDjWrl6');
        $site_id = get_option('maintenance_monday_site_id', '');
        $enabled = get_option('maintenance_monday_enabled', '1');

        ?>
        <div class="wrap">
            <h1><?php _e('Maintenance Monday Settings', 'maintenance-monday'); ?></h1>

            <?php if (isset($test_result)): ?>
                <div class="notice <?php echo $test_result['success'] ? 'notice-success' : 'notice-error'; ?> is-dismissible">
                    <p><?php echo esc_html($test_result['message']); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($sites_result)): ?>
                <div class="notice <?php echo $sites_result['success'] ? 'notice-success' : 'notice-error'; ?> is-dismissible">
                    <p><?php echo esc_html($sites_result['message']); ?></p>
                    <?php if ($sites_result['success'] && isset($sites_result['data'])): ?>
                        <p><?php printf(__('Found %d sites', 'maintenance-monday'), count($sites_result['data'])); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('maintenance_monday_settings', 'maintenance_monday_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="maintenance_monday_enabled"><?php _e('Enable Plugin', 'maintenance-monday'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="maintenance_monday_enabled" name="maintenance_monday_enabled" value="1" <?php checked($enabled ?: '1', '1'); ?> />
                            <p class="description"><?php _e('Enable the Maintenance Monday dashboard widget and functionality', 'maintenance-monday'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="maintenance_monday_api_url"><?php _e('API URL', 'maintenance-monday'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="maintenance_monday_api_url" name="maintenance_monday_api_url" value="<?php echo esc_attr($api_url ?: 'http://localhost:8000'); ?>" class="regular-text" placeholder="http://localhost:8000" />
                            <p class="description"><?php _e('URL for your Maintenance Monday API', 'maintenance-monday'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="maintenance_monday_api_key"><?php _e('API Key', 'maintenance-monday'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="maintenance_monday_api_key" name="maintenance_monday_api_key" value="<?php echo esc_attr($api_key ?: 'mm_f1N9yRZYZs7DTBF2CThpusTXReDjWrl6'); ?>" class="regular-text" />
                            <p class="description"><?php _e('API key for Maintenance Monday', 'maintenance-monday'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes', 'maintenance-monday'); ?>" />
                    <input type="submit" name="test_connection" id="test_connection" class="button" value="<?php _e('Test Connection', 'maintenance-monday'); ?>" />
                    <input type="submit" name="fetch_sites" id="fetch_sites" class="button" value="<?php _e('Fetch Sites', 'maintenance-monday'); ?>" />
                </p>

                <?php if (!empty($api_url) && !empty($api_key)): ?>
                    <h2><?php _e('Site Selection', 'maintenance-monday'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="maintenance_monday_site_id"><?php _e('Connect to Site', 'maintenance-monday'); ?></label>
                            </th>
                            <td>
                                <?php $this->render_site_selector($site_id); ?>
                                <p class="description"><?php _e('Select which Maintenance Monday site to connect to', 'maintenance-monday'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="submit" class="button button-primary" value="<?php _e('Save Site Selection', 'maintenance-monday'); ?>" />
                    </p>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render site selector dropdown
     */
    private function render_site_selector($selected_site_id) {
        $sites_result = $this->api->get_sites();

        if (!$sites_result['success']) {
            echo '<p style="color: red;">' . esc_html($sites_result['message']) . '</p>';
            echo '<input type="hidden" name="maintenance_monday_site_id" value="" />';
            return;
        }

        $sites = $sites_result['data'] ?? array();

        if (empty($sites)) {
            echo '<p>' . __('No sites found. Please check your API configuration.', 'maintenance-monday') . '</p>';
            echo '<input type="hidden" name="maintenance_monday_site_id" value="" />';
            return;
        }

        echo '<select id="maintenance_monday_site_id" name="maintenance_monday_site_id">';
        echo '<option value="">' . __('Select a site...', 'maintenance-monday') . '</option>';

        foreach ($sites as $site) {
            $site_id = isset($site['id']) ? $site['id'] : '';
            $site_name = isset($site['name']) ? $site['name'] : '';
            $site_url = isset($site['url']) ? $site['url'] : '';

            if (empty($site_id) || empty($site_name)) {
                continue;
            }

            $display_name = $site_name;
            if (!empty($site_url)) {
                $display_name .= ' (' . $site_url . ')';
            }

            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($site_id),
                selected($selected_site_id, $site_id, false),
                esc_html($display_name)
            );
        }

        echo '</select>';
    }

    /**
     * Save settings
     */
    private function save_settings() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to save these settings.'));
        }

        // Sanitize and save options
        $enabled = isset($_POST['maintenance_monday_enabled']) ? '1' : '0';
        $api_url = sanitize_url($_POST['maintenance_monday_api_url'] ?? '');
        $api_key = sanitize_text_field($_POST['maintenance_monday_api_key'] ?? '');
        $site_id = sanitize_text_field($_POST['maintenance_monday_site_id'] ?? '');

        // Use defaults if fields are empty
        if (empty($api_url)) {
            $api_url = 'http://localhost:8000';
        }
        if (empty($api_key)) {
            $api_key = 'mm_f1N9yRZYZs7DTBF2CThpusTXReDjWrl6';
        }

        update_option('maintenance_monday_enabled', $enabled);
        update_option('maintenance_monday_api_url', $api_url);
        update_option('maintenance_monday_api_key', $api_key);
        update_option('maintenance_monday_site_id', $site_id);

        // Show success message
        add_settings_error(
            'maintenance-monday',
            'settings_updated',
            __('Settings saved successfully!', 'maintenance-monday'),
            'success'
        );
    }
}
