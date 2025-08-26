<?php
/**
 * Maintenance Monday Dashboard Class
 * Handles the dashboard widget functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MaintenanceMonday_Dashboard {

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
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        // Check if plugin is enabled
        if (get_option('maintenance_monday_enabled') !== '1') {
            echo '<p>' . __('Maintenance Monday plugin is disabled. Please enable it in the settings.', 'maintenance-monday') . '</p>';
            return;
        }

        // Check if API is configured
        if (empty(get_option('maintenance_monday_api_url')) || empty(get_option('maintenance_monday_api_key'))) {
            echo '<p>' . __('Please configure the API settings to use this widget.', 'maintenance-monday') . ' ';
            echo '<a href="' . admin_url('options-general.php?page=maintenance-monday-settings') . '">' . __('Go to Settings', 'maintenance-monday') . '</a></p>';
            return;
        }

        // Check if site is selected in settings
        if (empty(get_option('maintenance_monday_site_id'))) {
            echo '<p>' . __('Please select a site to connect to in the settings.', 'maintenance-monday') . ' ';
            echo '<a href="' . admin_url('options-general.php?page=maintenance-monday-settings') . '">' . __('Go to Settings', 'maintenance-monday') . '</a></p>';
            return;
        }

        // Handle form submission
        if (isset($_POST['submit_update']) && wp_verify_nonce($_POST['maintenance_monday_nonce'], 'dashboard_update')) {
            $this->handle_update_submission();
        }

        // Show success/error messages
        if (isset($_GET['update_success'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Update sent successfully!', 'maintenance-monday') . '</p></div>';
        } elseif (isset($_GET['update_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Error sending update. Please try again.', 'maintenance-monday') . '</p></div>';
        }

        // Show connected site information
        $this->display_connected_site_info();

        // Enqueue scripts for dashboard widget
        wp_enqueue_script(
            'maintenance-monday-dashboard',
            MAINTENANCE_MONDAY_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MAINTENANCE_MONDAY_VERSION,
            true
        );

        // Get current PHP version (major.minor format)
        $full_php_version = phpversion();
        $php_version_parts = explode('.', $full_php_version);
        $php_version = $php_version_parts[0] . '.' . $php_version_parts[1];

        wp_localize_script('maintenance-monday-dashboard', 'maintenanceMondayAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('maintenance_monday_nonce'),
            'php_version' => $php_version,
            'php_version_full' => $full_php_version,
            'date_format' => get_option('date_format', 'F j, Y'),
            'time_format' => get_option('time_format', 'g:i a'),
            'strings' => array(
                'sending' => __('Sending update...', 'maintenance-monday'),
                'success' => __('Update sent successfully!', 'maintenance-monday'),
                'error' => __('Error sending update. Please try again.', 'maintenance-monday'),
            )
        ));

        // CSS is now loaded from external file

        ?>

        <!-- Site Status Section -->
        <div id="site-status-section" style="display: none; margin-bottom: 15px;">
            <div class="maintenance-monday-site-status">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #23282d;">
                    <?php _e('Update Status', 'maintenance-monday'); ?>
                </h4>
                <div id="site-last-update" class="maintenance-monday-last-update">
                    <!-- Last update info will be populated here -->
                </div>
            </div>
        </div>

        <div id="maintenance-monday-widget">
            <form method="post" action="">
                <?php wp_nonce_field('dashboard_update', 'maintenance_monday_nonce'); ?>

                <div class="maintenance-monday-form-group">
                    <label for="update_description"><?php _e('Description:', 'maintenance-monday'); ?></label>
                    <textarea id="update_description" name="update_description" class="widefat" rows="4" placeholder="<?php _e('Describe what was updated...', 'maintenance-monday'); ?>" required></textarea>
                </div>

                <div class="maintenance-monday-form-group">
                    <label><?php _e('Tags:', 'maintenance-monday'); ?></label>
                    <div id="tags-container">
                        <p class="description"><?php _e('Loading tags...', 'maintenance-monday'); ?></p>
                    </div>
                    <button type="button" id="refresh_tags_btn" class="button button-secondary" style="margin-top: 5px; display: none;">
                        <?php _e('Refresh Tags', 'maintenance-monday'); ?>
                    </button>
                </div>

                <div class="maintenance-monday-form-actions">
                    <input type="submit" name="submit_update" id="submit_update" class="button button-primary" value="<?php _e('Send Update', 'maintenance-monday'); ?>" />
                    <span id="update-status" style="display: none; margin-left: 10px;">
                        <span class="spinner is-active" style="float: none;"></span>
                        <?php _e('Sending...', 'maintenance-monday'); ?>
                    </span>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Handle update submission
     */
    private function handle_update_submission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['maintenance_monday_nonce'], 'dashboard_update')) {
            wp_die(__('Security check failed', 'maintenance-monday'));
        }

        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to perform this action.'));
        }

        // Get site ID from settings
        $site_id = intval(get_option('maintenance_monday_site_id'));

        // Prepare update data
        $update_data = array(
            'site_id' => $site_id,
            'description' => sanitize_textarea_field($_POST['update_description'] ?? ''),
            'tags' => sanitize_text_field($_POST['update_tags'] ?? ''),
            'performed_by' => wp_get_current_user()->display_name,
        );

        // Send update via API
        $result = $this->api->send_update($update_data);

        // Redirect with result
        $redirect_url = add_query_arg(
            array(
                $result['success'] ? 'update_success' : 'update_error' => '1'
            ),
            wp_get_referer()
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Display connected site information
     */
    private function display_connected_site_info() {
        $site_id = get_option('maintenance_monday_site_id');
        $api_url = get_option('maintenance_monday_api_url');
        $api_key = get_option('maintenance_monday_api_key');

        // Don't show anything if not configured
        if (empty($api_url) || empty($api_key)) {
            return;
        }

        echo '<div class="maintenance-monday-site-info" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px; margin-bottom: 15px; font-size: 12px; color: #495057;">';

        if (empty($site_id)) {
            echo '<div style="display: flex; align-items: center; gap: 5px;">';
            echo '<span style="color: #ffc107;">⚠️</span>';
            echo '<strong>' . __('Not Connected:', 'maintenance-monday') . '</strong> ';
            echo __('No site selected. ', 'maintenance-monday');
            echo '<a href="' . admin_url('options-general.php?page=maintenance-monday-settings') . '" style="color: #007cba; text-decoration: none;">' . __('Configure now', 'maintenance-monday') . '</a>';
            echo '</div>';
        } else {
            // Try to get site information
            $sites_result = $this->api->get_sites();

            if ($sites_result['success'] && !empty($sites_result['data'])) {
                $connected_site = null;
                foreach ($sites_result['data'] as $site) {
                    if (isset($site['id']) && $site['id'] == $site_id) {
                        $connected_site = $site;
                        break;
                    }
                }

                if ($connected_site) {
                    echo '<div style="display: flex; align-items: center; gap: 5px;">';
                    echo '<span style="color: #28a745;">✅</span>';
                    echo '<strong>' . __('Connected to:', 'maintenance-monday') . '</strong> ';
                    echo esc_html($connected_site['name']);
                    if (!empty($connected_site['url'])) {
                        echo ' (' . esc_html($connected_site['url']) . ')';
                    }
                    echo '</div>';
                } else {
                    echo '<div style="display: flex; align-items: center; gap: 5px;">';
                    echo '<span style="color: #dc3545;">❌</span>';
                    echo '<strong>' . __('Connection Error:', 'maintenance-monday') . '</strong> ';
                    echo __('Selected site not found. ', 'maintenance-monday');
                    echo '<a href="' . admin_url('options-general.php?page=maintenance-monday-settings') . '" style="color: #007cba; text-decoration: none;">' . __('Reconfigure', 'maintenance-monday') . '</a>';
                    echo '</div>';
                }
            } else {
                echo '<div style="display: flex; align-items: center; gap: 5px;">';
                echo '<span style="color: #ffc107;">⏳</span>';
                echo '<strong>' . __('Checking connection...', 'maintenance-monday') . '</strong> ';
                echo __('Unable to verify site connection.', 'maintenance-monday');
                echo '</div>';
            }
        }

        echo '</div>';
    }

    /**
     * Render dashboard widget control (settings)
     */
    public function render_dashboard_widget_control() {
        // Widget control options can be added here if needed
        // For now, users can configure via the main settings page
        echo '<p>' . sprintf(__('Configure this widget via the %s settings page.', 'maintenance-monday'), '<a href="' . admin_url('options-general.php?page=maintenance-monday-settings') . '">Maintenance Monday</a>') . '</p>';
    }
}
