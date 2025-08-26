# Maintenance Monday WordPress Plugin

Connect your WordPress site to adcobo Maintenance Monday.

## Automated Build Process

This plugin automatically builds and releases when you push to the `master` branch. Each push triggers:

1. **Automatic ZIP creation** - Creates a plugin zip file
2. **GitHub Release** - Creates a new release with the zip file attached
3. **Consistent URLs** - Each release maintains the same download structure

## Download Links

Once you push to master, you'll get a permanent download link in this format:
```
https://github.com/[your-username]/[repo-name]/releases/latest/download/maintenance-monday-[version].zip
```

For example:
```
https://github.com/yourusername/maintenance-monday/releases/latest/download/maintenance-monday-1.0.0.zip
```

## Using in Laravel Project

You can use these permanent download links in your Laravel project:

```php
// Example: Download the latest version
$downloadUrl = "https://github.com/yourusername/maintenance-monday/releases/latest/download/maintenance-monday-1.0.0.zip";

// Or use the releases API to get the latest version dynamically
$response = Http::get('https://api.github.com/repos/yourusername/maintenance-monday/releases/latest');
$latestVersion = $response->json('tag_name');
$downloadUrl = "https://github.com/yourusername/maintenance-monday/releases/latest/download/maintenance-monday-{$latestVersion}.zip";
```

## Development Workflow

1. Make changes to your plugin
2. Update version in `maintenance-monday.php` if needed
3. Commit and push to `master` branch
4. GitHub Actions automatically builds and releases
5. Use the permanent download link in your Laravel project

## Manual Release

You can also trigger a manual release from the GitHub Actions tab in your repository.

## Plugin Structure

```
maintenance-monday/
├── maintenance-monday.php    # Main plugin file
├── includes/                 # PHP classes and functions
├── assets/                   # CSS, JS, and images
└── README.md                # This file
```

## Version Management

**Automatic Version Bumping**: The plugin automatically increments version numbers based on [Conventional Commits](https://github.com/BryanLomerio/conventional-commit-cheatsheet)!

### Version Bump Rules:
- **`feat:`** → Minor version bump (1.0.0 → 1.1.0) - New features
- **`fix:`, `sec:`, `perf:`** → Patch version bump (1.0.0 → 1.0.1) - Bug fixes, security, performance
- **`BREAKING CHANGE:`, `refactor:`** → Major version bump (1.0.0 → 2.0.0) - Breaking changes
- **Other types** → Patch version bump (1.0.0 → 1.0.1) - Default behavior

### Examples:
```bash
# New feature - minor version bump
git commit -m "feat(dashboard): add new update tracking widget"
git push origin master

# Bug fix - patch version bump  
git commit -m "fix(api): resolve connection timeout issue"
git push origin master

# Security fix - patch version bump
git commit -m "sec(auth): add CSRF protection to forms"
git push origin master

# Breaking change - major version bump
git commit -m "refactor(api): BREAKING CHANGE: restructure API endpoints"
git push origin master

# Performance improvement - patch version bump
git commit -m "perf(database): optimize query performance"
git push origin master
```

The version is automatically updated in both the plugin header and the `MAINTENANCE_MONDAY_VERSION` constant.

## Self-Updating Plugin

This plugin automatically checks for updates from GitHub and integrates with WordPress's native update system. Users will see update notifications in:

- **WordPress Admin Dashboard** - Update notices when new versions are available
- **Plugins Page** - Update buttons and version information
- **Updates Page** - Centralized update management

### Configuration

Before using the self-update feature, update the GitHub repository details in `includes/plugin-config.php`:

```php
define('MAINTENANCE_MONDAY_GITHUB_USERNAME', 'yourusername'); // Your GitHub username
define('MAINTENANCE_MONDAY_GITHUB_REPO', 'maintenance-monday'); // Your repository name
```

## Requirements

- WordPress 5.0+
- PHP 7.4+
- GitHub repository with Actions enabled

## GitHub Actions Setup

### Workflow Permissions

The workflow automatically bumps version numbers but may not have permission to push back to the repository. This is normal and won't affect the build process.

**To enable auto-push of version bumps:**

1. Go to your repository **Settings** → **Actions** → **General**
2. Scroll down to **Workflow permissions**
3. Select **Read and write permissions**
4. Check **Allow GitHub Actions to create and approve pull requests**
5. Click **Save**

**Note**: Version bumps are still applied to the build even if the push fails.
