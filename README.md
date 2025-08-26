# Maintenance Monday WordPress Plugin

A WordPress plugin that connects your WordPress site to your Maintenance Monday Laravel system for easy update tracking and management.

## Features

- **Dashboard Widget**: Quick update submission directly from the WordPress dashboard
- **API Integration**: Secure connection to your Laravel Maintenance Monday system
- **Site Selection**: Choose which Laravel site this WordPress site connects to
- **Real-time Updates**: Send updates instantly with AJAX-powered form submission
- **User-friendly Interface**: Intuitive form with validation and feedback
- **Security**: Nonce verification and proper WordPress security practices

## Installation

1. Download or clone this plugin to your WordPress installation
2. Upload the `maintenance-monday` folder to `wp-content/plugins/`
3. Activate the plugin through the WordPress admin dashboard
4. The plugin comes pre-configured with default settings:
   - **API URL**: `https://maintenance-monday.test`
   - **API Key**: `mm_f1N9yRZYZs7DTBF2CThpusTXReDjWrl6`
   - **Plugin Enabled**: Yes
5. Navigate to **Settings > Maintenance Monday** to configure the plugin (update URL if needed)

## Configuration

### Pre-configured Settings
The plugin comes with the following default settings:
- **API URL**: `https://maintenance-monday.test`
- **API Key**: `mm_f1N9yRZYZs7DTBF2CThpusTXReDjWrl6`
- **Plugin Status**: Enabled

**Important**: If your Laravel application is running on a different URL, you'll need to update the API URL in the plugin settings.

### Step 1: Laravel API Setup
Before using this plugin, you need to set up the corresponding Laravel API endpoints:

1. Ensure your Laravel Maintenance Monday application has these API routes:
   - `GET /api/test-connection` - Test API connection
   - `GET /api/sites` - Get available sites
   - `POST /api/updates` - Create new updates

2. Generate an API key in your Laravel application for authentication

### Step 2: Plugin Configuration
1. Go to **Settings > Maintenance Monday** in your WordPress admin
2. Enter your Laravel API URL (e.g., `https://your-laravel-app.com`)
3. Enter your API key
4. Click **Test Connection** to verify the connection
5. Click **Fetch Sites** to load available sites from your Laravel system
6. Select which site this WordPress installation should connect to
7. Enable the plugin
8. Save your settings

## Usage

### Dashboard Widget
Once configured, you'll see a "Maintenance Monday - Quick Update" widget on your WordPress dashboard.

**To submit an update:**
1. Fill in the update title (required)
2. Add a description (optional)
3. Select the update type (Security, Maintenance, Plugin Update, etc.)
4. Choose the status (Completed, In Progress, Scheduled, etc.)
5. Add any additional notes (optional)
6. Click **Send Update**

The update will be sent to your Laravel system via API and recorded in your maintenance database.

### Update Types
- **Security**: Security patches and vulnerability fixes
- **Maintenance**: Regular maintenance tasks
- **Plugin Update**: WordPress plugin updates
- **Theme Update**: WordPress theme updates
- **Content Update**: Content changes and updates
- **Other**: Any other type of update

## API Requirements

Your Laravel application should accept these API endpoints:

### GET /api/test-connection
Tests the API connection and returns success/failure.

**Response:**
```json
{
    "success": true,
    "message": "Connection successful"
}
```

### GET /api/sites
Returns a list of available sites.

**Response:**
```json
[
    {
        "id": 1,
        "name": "My WordPress Site",
        "url": "https://example.com"
    }
]
```

### POST /api/updates
Creates a new update record.

**Request Body:**
```json
{
    "site_id": 1,
    "title": "Security Updates Applied",
    "description": "Updated WordPress core and plugins",
    "type": "security",
    "status": "completed",
    "performed_by": "Admin User",
    "performed_at": "2024-01-15 10:30:00",
    "notes": "Additional notes about the update"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Update created successfully",
    "data": {
        "id": 123,
        "site_id": 1,
        "title": "Security Updates Applied",
        "created_at": "2024-01-15 10:30:00"
    }
}
```

## Security Features

- **Nonce Verification**: All requests include WordPress nonces for CSRF protection
- **Input Sanitization**: All user inputs are sanitized before processing
- **API Key Authentication**: Secure API key-based authentication
- **User Capabilities**: Respects WordPress user roles and permissions
- **HTTPS Required**: All API communications should use HTTPS

## Troubleshooting

### Connection Issues
- Verify your Laravel API URL is correct and accessible
- Check that your API key is valid
- Ensure your Laravel application allows cross-origin requests from your WordPress domain
- Check server logs for any API errors

### Widget Not Appearing
- Ensure the plugin is activated
- Check that you have the required user permissions
- Verify the plugin is enabled in settings
- Clear any caching plugins

### Update Submission Fails
- Check the browser console for JavaScript errors
- Verify your API endpoints are working correctly
- Ensure the selected site ID is valid
- Check WordPress debug logs for PHP errors

## Support

For support or questions about this plugin, please contact your Maintenance Monday administrator or check the Laravel application logs for API-related issues.

## Changelog

### Version 1.0.0
- Initial release
- Dashboard widget functionality
- API integration
- Settings page
- Site selection
- AJAX form submission

## License

This plugin is licensed under the GPL v2 or later. See the license.txt file for details.
