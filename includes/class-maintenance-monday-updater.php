<?php
/**
 * Plugin Update Checker
 * 
 * Integrates with WordPress's native plugin update system to automatically
 * check for updates from GitHub releases.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MaintenanceMonday_Updater {
    
    /**
     * Plugin slug
     */
    private $plugin_slug;
    
    /**
     * Plugin file path
     */
    private $plugin_file;
    
    /**
     * GitHub repository information
     */
    private $github_repo;
    private $github_username;
    
    /**
     * Constructor
     */
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        
        // Set your GitHub repository details here
        $this->github_username = defined('MAINTENANCE_MONDAY_GITHUB_USERNAME') ? MAINTENANCE_MONDAY_GITHUB_USERNAME : 'yourusername';
        $this->github_repo = defined('MAINTENANCE_MONDAY_GITHUB_REPO') ? MAINTENANCE_MONDAY_GITHUB_REPO : 'maintenance-monday';
        
        // Hook into WordPress update system
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        
        // Add settings link to show current version info
        add_action('admin_notices', array($this, 'version_info_notice'));
    }
    
    /**
     * Check for plugin updates
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Get current plugin version
        $current_version = $transient->checked[$this->plugin_slug];
        
        // Get latest version from GitHub
        $latest_version = $this->get_latest_version();
        
        if ($latest_version && version_compare($latest_version, $current_version, '>')) {
            $plugin_data = get_plugin_data($this->plugin_file);
            
            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => $this->plugin_slug,
                'new_version' => $latest_version,
                'url' => "https://github.com/{$this->github_username}/{$this->github_repo}",
                'package' => "https://github.com/{$this->github_username}/{$this->github_repo}/releases/latest/download/maintenance-monday-{$latest_version}.zip",
                'requires' => '5.0',
                'requires_php' => '7.4',
                'tested' => '6.4',
                'last_updated' => $this->get_latest_release_date(),
                'sections' => array(
                    'description' => $plugin_data['Description'],
                    'changelog' => $this->get_changelog()
                )
            );
        }
        
        return $transient;
    }
    
    /**
     * Get plugin information for the update screen
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if ($args->slug !== $this->plugin_slug) {
            return $result;
        }
        
        $plugin_data = get_plugin_data($this->plugin_file);
        $latest_version = $this->get_latest_version();
        
        if (!$latest_version) {
            return $result;
        }
        
        $response = wp_remote_get("https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest");
        
        if (is_wp_error($response)) {
            return $result;
        }
        
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
        
        $result = (object) array(
            'name' => $plugin_data['Name'],
            'slug' => $this->plugin_slug,
            'version' => $latest_version,
            'author' => $plugin_data['Author'],
            'author_profile' => $plugin_data['AuthorURI'],
            'last_updated' => $this->get_latest_release_date(),
            'homepage' => $plugin_data['PluginURI'],
            'requires' => '5.0',
            'requires_php' => '7.4',
            'tested' => '6.4',
            'sections' => array(
                'description' => $plugin_data['Description'],
                'installation' => $this->get_installation_instructions(),
                'changelog' => $this->get_changelog(),
                'screenshots' => ''
            ),
            'download_link' => "https://github.com/{$this->github_username}/{$this->github_repo}/releases/latest/download/maintenance-monday-{$latest_version}.zip"
        );
        
        return $result;
    }
    
    /**
     * Get latest version from GitHub
     */
    private function get_latest_version() {
        $response = wp_remote_get("https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest");
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($release_data['tag_name'])) {
            // Remove 'v' prefix if present
            return ltrim($release_data['tag_name'], 'v');
        }
        
        return false;
    }
    
    /**
     * Get latest release date
     */
    private function get_latest_release_date() {
        $response = wp_remote_get("https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest");
        
        if (is_wp_error($response)) {
            return '';
        }
        
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($release_data['published_at'])) {
            return date('Y-m-d', strtotime($release_data['published_at']));
        }
        
        return '';
    }
    
    /**
     * Get changelog from GitHub release
     */
    private function get_changelog() {
        $response = wp_remote_get("https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest");
        
        if (is_wp_error($response)) {
            return 'Unable to fetch changelog.';
        }
        
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($release_data['body'])) {
            return $release_data['body'];
        }
        
        return 'No changelog available.';
    }
    
    /**
     * Get installation instructions
     */
    private function get_installation_instructions() {
        return '
        <ol>
            <li>Download the plugin zip file</li>
            <li>Go to WordPress Admin > Plugins > Add New</li>
            <li>Click "Upload Plugin" and select the zip file</li>
            <li>Click "Install Now"</li>
            <li>Activate the plugin</li>
        </ol>
        
        <p><strong>Note:</strong> This plugin will automatically check for updates from GitHub and notify you when new versions are available.</p>
        ';
    }
    
    /**
     * After plugin installation
     */
    public function after_install($response, $hook_extra, $result) {
        if ($hook_extra['plugin'] === $this->plugin_slug) {
            // Clear any cached update data
            delete_site_transient('update_plugins');
        }
        
        return $result;
    }
    
    /**
     * Show version information notice
     */
    public function version_info_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $current_version = get_plugin_data($this->plugin_file)['Version'];
        $latest_version = $this->get_latest_version();
        
        if ($latest_version && version_compare($latest_version, $current_version, '>')) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>Maintenance Monday:</strong> A new version (' . esc_html($latest_version) . ') is available. <a href="' . admin_url('plugins.php') . '">Update now</a> or <a href="' . admin_url('update-core.php') . '">check for updates</a>.</p>';
            echo '</div>';
        }
    }
}
