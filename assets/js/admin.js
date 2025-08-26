/**
 * Maintenance Monday Admin JavaScript
 * Handles AJAX requests and form interactions
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Reset any stuck loading states on page load
        $('#submit_update').prop('disabled', false).removeClass('maintenance-monday-loading');
        $('#update-status').hide();

        // Fetch and populate tags on page load
        fetchAndPopulateTags();

        // Fetch and display site status on page load
        console.log('Maintenance Monday: Initializing site status display');

        // Debug: Log current configuration
        console.log('Maintenance Monday: AJAX URL:', maintenanceMondayAjax.ajaxurl);
        console.log('Maintenance Monday: Nonce:', maintenanceMondayAjax.nonce);
        console.log('Maintenance Monday: Date Format:', maintenanceMondayAjax.date_format);
        console.log('Maintenance Monday: Time Format:', maintenanceMondayAjax.time_format);

        fetchAndDisplaySiteStatus();

        // Handle dashboard widget form submission via AJAX
        $('#maintenance-monday-widget form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitButton = $form.find('#submit_update');
            var $status = $('#update-status');
            var originalButtonText = $submitButton.val();

            // Show loading state
            $submitButton.prop('disabled', true).addClass('maintenance-monday-loading');
            $status.show();

            // Failsafe: hide spinner after 30 seconds in case of hanging request
            var failsafeTimeout = setTimeout(function() {
                console.warn('Maintenance Monday: Failsafe timeout triggered - resetting spinner');
                $submitButton.prop('disabled', false).removeClass('maintenance-monday-loading');
                $status.hide();
                showNotice('error', 'Request timed out. Please try again.');
            }, 30000);

            // Get selected tags
            var selectedTags = [];
            $('input[name="update_tags[]"]:checked').each(function() {
                selectedTags.push($(this).val());
            });

            // Prepare form data
            var formData = new FormData($form[0]);
            formData.append('action', 'maintenance_monday_send_update');
            formData.append('nonce', maintenanceMondayAjax.nonce);
            formData.append('update_tags', selectedTags.join(','));

            // Send AJAX request
            console.log('Maintenance Monday: Starting AJAX request');
            $.ajax({
                url: maintenanceMondayAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Maintenance Monday: AJAX Success received', response);
                    try {
                        if (response.success) {
                            // Show success message
                            showNotice('success', response.data.message || maintenanceMondayAjax.strings.success);

                            // Reset form
                            $form[0].reset();

                            // Clear tags container
                            $('#tags-container').html('<p class="description">Loading tags...</p>');

                            // Refresh site status after successful update
                            setTimeout(function() {
                                fetchAndDisplaySiteStatus();
                            }, 1000);

                            // Try to refresh dashboard (safely)
                            try {
                                if (typeof wp !== 'undefined' && wp.dashboard && wp.dashboard.widgets) {
                                    wp.dashboard.widgets.refresh();
                                }
                            } catch (e) {
                                console.log('Dashboard refresh failed:', e);
                            }
                        } else {
                            // Show error message
                            showNotice('error', response.data.message || maintenanceMondayAjax.strings.error);
                        }
                    } catch (e) {
                        console.error('Success handler error:', e);
                        showNotice('error', 'An error occurred while processing the response.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Maintenance Monday AJAX Error:', {
                        xhr: xhr,
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    try {
                        showNotice('error', maintenanceMondayAjax.strings.error);
                    } catch (e) {
                        console.error('Error handler error:', e);
                    }
                },
                complete: function(xhr, status) {
                    console.log('Maintenance Monday AJAX Complete:', status);
                    // Clear the failsafe timeout
                    clearTimeout(failsafeTimeout);

                    // Always reset loading state, no matter what
                    try {
                        $submitButton.prop('disabled', false).removeClass('maintenance-monday-loading');
                        $status.hide();
                        console.log('Loading state reset successfully');
                    } catch (e) {
                        console.error('Complete handler error:', e);
                        // Fallback: try to reset by element ID
                        $('#submit_update').prop('disabled', false).removeClass('maintenance-monday-loading');
                        $('#update-status').hide();
                    }
                },
                timeout: 30000 // 30 second timeout to prevent hanging requests
            });
        });

        // Handle settings page test connection
        $('#test_connection').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $spinner = $button.siblings('.spinner');
            var originalText = $button.val();

            // Show loading state
            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            // Prepare form data
            var formData = new FormData($('form')[0]);
            formData.append('action', 'maintenance_monday_test_connection');
            formData.append('nonce', maintenanceMondayAjax.nonce);

            // Send AJAX request
            $.ajax({
                url: maintenanceMondayAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message || 'Connection successful!');
                    } else {
                        showNotice('error', response.data.message || 'Connection failed');
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('error', 'Connection failed: ' + error);
                    console.error('Maintenance Monday Test Connection Error:', error);
                },
                complete: function() {
                    // Hide loading state
                    $button.prop('disabled', false).val(originalText);
                    $spinner.removeClass('is-active');
                }
            });
        });

        // Handle settings page fetch sites
        $('#fetch_sites').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $spinner = $button.siblings('.spinner');
            var originalText = $button.val();

            // Show loading state
            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            // Prepare form data
            var formData = new FormData($('form')[0]);
            formData.append('action', 'maintenance_monday_fetch_sites');
            formData.append('nonce', maintenanceMondayAjax.nonce);

            // Send AJAX request
            $.ajax({
                url: maintenanceMondayAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message || 'Sites fetched successfully!');
                        // Reload page to show updated site list
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showNotice('error', response.data.message || 'Failed to fetch sites');
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('error', 'Failed to fetch sites: ' + error);
                    console.error('Maintenance Monday Fetch Sites Error:', error);
                },
                complete: function() {
                    // Hide loading state
                    $button.prop('disabled', false).val(originalText);
                    $spinner.removeClass('is-active');
                }
            });
        });

        // Global error handler to catch any unhandled errors and reset spinner
        $(window).on('error', function() {
            console.error('Global error caught, resetting spinner state');
            $('#submit_update').prop('disabled', false).removeClass('maintenance-monday-loading');
            $('#update-status').hide();
        });

        // Handle browser navigation/back button to reset spinner
        $(window).on('popstate', function() {
            $('#submit_update').prop('disabled', false).removeClass('maintenance-monday-loading');
            $('#update-status').hide();
        });
    });

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        // Remove existing notices
        $('.maintenance-monday-notice').remove();

        // Create new notice
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible maintenance-monday-notice">' +
            '<p>' + message + '</p>' +
            '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Dismiss this notice.</span>' +
            '</button>' +
        '</div>');

        // Add to page
        if ($('.wrap h1').length > 0) {
            $('.wrap h1').after($notice);
        } else if ($('#wpbody-content').length > 0) {
            $('#wpbody-content').prepend($notice);
        } else {
            $('form').before($notice);
        }

        // Handle dismiss
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut();
        });

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
    }

    /**
     * Fetch tags from Laravel API and populate checkboxes
     */
    function fetchAndPopulateTags() {
        var $tagsContainer = $('#tags-container');
        var $refreshBtn = $('#refresh_tags_btn');

        // Show loading state
        $tagsContainer.html('<p class="description">' + (maintenanceMondayAjax.strings ? maintenanceMondayAjax.strings.loading_tags || 'Loading tags...' : 'Loading tags...') + '</p>');

        // Prepare form data
        var formData = new FormData();
        formData.append('action', 'maintenance_monday_fetch_tags');
        formData.append('nonce', maintenanceMondayAjax.nonce);

        // Send AJAX request
        $.ajax({
            url: maintenanceMondayAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Tags API response:', response);

                if (response.success) {
                    // Clear container
                    $tagsContainer.empty();

                    // Handle double-nested data structure from WordPress AJAX
                    var tagsData = response.data.data || response.data;

                    if (tagsData && tagsData.length > 0) {
                        // Create checkboxes for each tag
                        var $tagList = $('<div class="maintenance-monday-tag-list">');

                        tagsData.forEach(function(tag) {
                            var $tagItem = $('<div class="maintenance-monday-tag-item">');
                            var $checkbox = $('<input>')
                                .attr('type', 'checkbox')
                                .attr('id', 'tag_' + tag.id)
                                .attr('name', 'update_tags[]')
                                .attr('value', tag.id);
                            var $label = $('<label>')
                                .attr('for', 'tag_' + tag.id)
                                .text(tag.name);

                            $tagItem.append($checkbox).append($label);
                            $tagList.append($tagItem);
                        });

                        $tagsContainer.append($tagList);
                        $refreshBtn.show();
                    } else {
                        $tagsContainer.html('<p class="description">No tags available. Tags will be created automatically when you create updates.</p>');
                        $refreshBtn.hide();
                    }
                } else {
                    $tagsContainer.html('<p class="description">Error loading tags: ' + (response.data.message || 'Unknown error') + '</p>');
                    console.error('Tags API returned error:', response);
                }
            },
            error: function(xhr, status, error) {
                $tagsContainer.html('<p class="description">Error loading tags: ' + error + '</p>');
                console.error('Maintenance Monday Fetch Tags Error:', {
                    xhr: xhr,
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
            }
        });
    }

    /**
     * Fetch site status from Laravel API and display it
     */
    function fetchAndDisplaySiteStatus() {
        var $statusSection = $('#site-status-section');
        var $lastUpdateContainer = $('#site-last-update');

        // Show loading state
        $statusSection.hide();
        $lastUpdateContainer.html('<p class="description">Loading site status...</p>');

        console.log('Maintenance Monday: Starting to load site status');
        console.log('Maintenance Monday: Status section element:', $statusSection.length);
        console.log('Maintenance Monday: Last update container:', $lastUpdateContainer.length);

        // Prepare form data
        var formData = new FormData();
        formData.append('action', 'maintenance_monday_fetch_site_status');
        formData.append('nonce', maintenanceMondayAjax.nonce);

        // Send AJAX request
        $.ajax({
            url: maintenanceMondayAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Site status API response:', response);
                console.log('Site status response success:', response.success);
                console.log('Site status response data:', response.data);

                if (response.success && response.data) {
                    // Handle nested API response structure
                    var siteData = response.data.data ? response.data.data.data : response.data;
                    console.log('Site data:', siteData);
                    console.log('Update status:', siteData.update_status);
                    console.log('Last update:', siteData.last_update);
                    console.log('Last update created_at:', siteData.last_update ? siteData.last_update.created_at : 'NO CREATED_AT');
                    console.log('Raw response data:', JSON.stringify(response.data, null, 2));

                    // Build status HTML with coloring logic
                    var statusHtml = '';

                    // Update status badge
                    var statusClass = siteData.update_status || 'gray';
                    var statusText = getStatusText(statusClass);
                    var statusStyle = getStatusStyle(statusClass);
                    console.log('Processing status - Class:', statusClass, 'Text:', statusText, 'Style:', statusStyle);
                    statusHtml += '<span class="maintenance-monday-update-status ' + statusClass + '" style="' + statusStyle + '">' + statusText + '</span>';

                    // Update date and message
                    console.log('Checking last_update condition:', {
                        'last_update exists': !!siteData.last_update,
                        'created_at exists': !!(siteData.last_update && siteData.last_update.created_at),
                        'created_at value': siteData.last_update ? siteData.last_update.created_at : 'NO LAST_UPDATE'
                    });

                    if (siteData.last_update && siteData.last_update.created_at) {
                        // Use WordPress date formatting if available, fallback to browser formatting
                        var updateDate = new Date(siteData.last_update.created_at);
                        var formattedDate = formatWordPressDate(updateDate);
                        console.log('Formatted date:', formattedDate);
                        statusHtml += '<span class="maintenance-monday-update-date">Last updated: ' + formattedDate + '</span>';
                    } else {
                        console.log('No valid last_update found, showing "Never updated"');
                        statusHtml += '<span class="maintenance-monday-update-date">Never updated</span>';
                    }

                    // Add interval info if available
                    if (siteData.interval) {
                        var intervalText = siteData.interval == 1 ? '1 day' : siteData.interval + ' days';
                        statusHtml += '<div class="maintenance-monday-update-message">Update interval: ' + intervalText + '</div>';
                    }

                    // Show current WordPress PHP version with support info
                    var currentPhpVersion = getCurrentPhpVersion();
                    if (currentPhpVersion) {
                        // Get support info for WordPress PHP version
                        getPhpVersionSupportInfo(currentPhpVersion, function(phpInfo) {
                            var phpStatus = getPhpVersionStatus(phpInfo);
                            var phpStatusStyle = getPhpVersionStatusStyle(phpInfo);

                            var phpVersionHtml = '<div class="maintenance-monday-php-version" style="' + phpStatusStyle + '">';
                            phpVersionHtml += '<strong>PHP ' + currentPhpVersion + '</strong> - ' + phpStatus;

                            if (phpInfo.end_of_support_date) {
                                var endSupportDate = new Date(phpInfo.end_of_support_date);
                                var formattedEndSupport = formatWordPressDate(endSupportDate, false);
                                phpVersionHtml += '<br><small>Security support until: ' + formattedEndSupport + '</small>';
                            }

                            // Add helpful advice based on support status
                            var adviceMessage = getPhpVersionAdvice(phpInfo);
                            if (adviceMessage) {
                                phpVersionHtml += '<br><small class="maintenance-monday-php-advice">' + adviceMessage + '</small>';
                            }

                            phpVersionHtml += '</div>';

                            // Append PHP version info to the main status HTML
                            statusHtml += phpVersionHtml;

                            // Set the container HTML with both status and PHP version info
                            $lastUpdateContainer.html(statusHtml);
                            $statusSection.show();
                        });
                    } else {
                        // No PHP version available, just show the status
                        $lastUpdateContainer.html(statusHtml);
                        $statusSection.show();
                    }

                    console.log('Maintenance Monday: Status HTML set:', statusHtml);
                    console.log('Maintenance Monday: Status section shown');
                    console.log('Maintenance Monday: Container element:', $lastUpdateContainer);
                    console.log('Maintenance Monday: Section element:', $statusSection);

                    // Check if elements are visible and force display if needed
                    setTimeout(function() {
                        console.log('Maintenance Monday: After timeout - Section visible:', $statusSection.is(':visible'));
                        console.log('Maintenance Monday: After timeout - Section CSS display:', $statusSection.css('display'));
                        console.log('Maintenance Monday: After timeout - Container HTML:', $lastUpdateContainer.html());

                        // Force display if still hidden
                        if (!$statusSection.is(':visible')) {
                            console.log('Maintenance Monday: Forcing section visibility');
                            $statusSection.css('display', 'block');
                        }
                    }, 100);
                } else {
                    $lastUpdateContainer.html('<p class="description">Unable to load site status.</p>');
                    console.error('Site status API returned error:', response);
                }
            },
            error: function(xhr, status, error) {
                $lastUpdateContainer.html('<p class="description">Error loading site status: ' + error + '</p>');
                console.error('Site status fetch error:', {
                    xhr: xhr,
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
            }
        });
    }



    /**
     * Get inline styles for status badge
     */
    function getStatusStyle(statusClass) {
        switch(statusClass) {
            case 'success':
                return 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;';
            case 'warning':
                return 'background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7;';
            case 'danger':
                return 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;';
            case 'gray':
            default:
                return 'background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6;';
        }
    }

    /**
     * Get PHP version status text
     */
    function getPhpVersionStatus(phpInfo) {
        console.log('getPhpVersionStatus called with:', phpInfo);

        var today = new Date();
        var endOfSupport = new Date(phpInfo.end_of_support_date);
        var endOfLife = new Date(phpInfo.end_of_life_date);

        console.log('Today:', today);
        console.log('End of Support:', endOfSupport);
        console.log('End of Life:', endOfLife);

        if (today < endOfSupport) {
            console.log('Returning "Full Support"');
            return 'Full Support';
        } else if (today < endOfLife) {
            console.log('Returning "Security Support Only"');
            return 'Security Support Only';
        } else {
            console.log('Returning "No Support"');
            return 'No Support';
        }
    }

    /**
     * Get PHP version status styling
     */
    function getPhpVersionStatusStyle(phpInfo) {
        var today = new Date();
        var endOfSupport = new Date(phpInfo.end_of_support_date);
        var endOfLife = new Date(phpInfo.end_of_life_date);

        if (today < endOfSupport) {
            // Green - Full Support
            return 'background-color: #d4edda; color: #155724; padding: 8px; border-radius: 4px; border: 1px solid #c3e6cb; margin-top: 8px;';
        } else if (today < endOfLife) {
            // Yellow - Security Support Only
            return 'background-color: #fff3cd; color: #856404; padding: 8px; border-radius: 4px; border: 1px solid #ffeaa7; margin-top: 8px;';
        } else {
            // Red - No Support
            return 'background-color: #f8d7da; color: #721c24; padding: 8px; border-radius: 4px; border: 1px solid #f5c6cb; margin-top: 8px;';
        }
    }

    /**
     * Get helpful advice message based on PHP version support status
     */
    function getPhpVersionAdvice(phpInfo) {
        var today = new Date();
        var endOfSupport = new Date(phpInfo.end_of_support_date);
        var endOfLife = new Date(phpInfo.end_of_life_date);

        if (today >= endOfLife) {
            // No support - urgent message
            return '⚠️ Please update ASAP - No security updates available';
        } else if (today >= endOfSupport && today < endOfLife) {
            // Security support only - advisory message
            return '💡 Consider updating - Only critical security fixes until ' + endOfLife.toLocaleDateString();
        }
        // Full support - no message needed
        return null;
    }

    /**
     * Get current PHP version from WordPress
     */
    function getCurrentPhpVersion() {
        // Try to get PHP version from WordPress globals or maintenanceMondayAjax
        if (typeof maintenanceMondayAjax !== 'undefined' && maintenanceMondayAjax.php_version) {
            return maintenanceMondayAjax.php_version;
        }
        // Fallback - we can add this to the localized script data
        return null;
    }

    /**
     * Get PHP version support information from API
     */
    function getPhpVersionSupportInfo(version, callback) {
        var formData = new FormData();
        formData.append('action', 'maintenance_monday_get_php_version_info');
        formData.append('nonce', maintenanceMondayAjax.nonce);
        formData.append('version', version);

        console.log('Making PHP version info API call for version:', version);
        console.log('API URL:', maintenanceMondayAjax.ajaxurl);
        console.log('Nonce:', maintenanceMondayAjax.nonce);

        $.ajax({
            url: maintenanceMondayAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('PHP version info API response:', response);
                console.log('PHP version info response data:', response.data);
                console.log('Response success:', response.success);

                if (response.success && response.data) {
                    console.log('Using API data for PHP version:', response.data);
                    // Check if response.data is the PHP info directly or wrapped in another data object
                    var phpInfo = response.data;
                    if (phpInfo.data && phpInfo.version === undefined) {
                        // API returns wrapped data structure
                        phpInfo = phpInfo.data;
                        console.log('Using wrapped API data:', phpInfo);
                    }
                    console.log('Final PHP info to callback:', phpInfo);
                    callback(phpInfo);
                } else {
                    console.log('API call failed, using fallback for PHP version:', version);
                    // Fallback with smart defaults for common supported versions
                    var fallbackData = getPhpVersionFallback(version);
                    callback(fallbackData);
                }
            },
            error: function(xhr, status, error) {
                console.error('PHP version info API error:', error);
                console.log('Using fallback for PHP version:', version);
                // Fallback with smart defaults for common supported versions
                var fallbackData = getPhpVersionFallback(version);
                callback(fallbackData);
            }
        });
    }

    /**
     * Get fallback PHP version data for common supported versions
     */
    function getPhpVersionFallback(version) {
        // Known supported PHP versions with approximate end dates
        var supportedVersions = {
            '8.3': {
                is_active_support: true,
                is_security_support: false,
                end_of_support_date: '2026-12-31',
                end_of_life_date: '2027-12-31'
            },
            '8.2': {
                is_active_support: true,
                is_security_support: false,
                end_of_support_date: '2025-12-31',
                end_of_life_date: '2026-12-31'
            },
            '8.1': {
                is_active_support: false,
                is_security_support: true,
                end_of_support_date: '2024-12-31',
                end_of_life_date: '2025-12-31'
            },
            '8.0': {
                is_active_support: false,
                is_security_support: true,
                end_of_support_date: '2023-12-31',
                end_of_life_date: '2024-12-31'
            },
            '7.4': {
                is_active_support: false,
                is_security_support: true,
                end_of_support_date: '2022-12-31',
                end_of_life_date: '2023-12-31'
            }
        };

        // Check if version is in our known supported list
        if (supportedVersions[version]) {
            console.log('Using fallback data for known supported PHP version:', version);
            return {
                version: version,
                is_active_support: supportedVersions[version].is_active_support,
                is_security_support: supportedVersions[version].is_security_support,
                end_of_support_date: supportedVersions[version].end_of_support_date,
                end_of_life_date: supportedVersions[version].end_of_life_date
            };
        }

        // For newer/unknown versions, assume active support until we know otherwise
        // This ensures new PHP versions show as supported even if API is temporarily down
        var majorVersion = version.split('.')[0];
        if (parseInt(majorVersion) >= 8) {
            console.log('Unknown newer PHP version, assuming active support:', version);
            return {
                version: version,
                is_active_support: true,
                is_security_support: true,
                end_of_support_date: '2029-12-31', // Future date
                end_of_life_date: '2030-12-31'     // Future date
            };
        }

        // Extract major version for older versions
        if (parseInt(majorVersion) < 7) {
            console.log('PHP version appears to be end of life:', version);
            return {
                version: version,
                is_active_support: false,
                is_security_support: false,
                end_of_support_date: null,
                end_of_life_date: null
            };
        }

        // Default fallback for unknown versions
        console.log('Using default fallback for unknown PHP version:', version);
        return {
            version: version,
            is_active_support: true, // Assume newer versions are supported
            is_security_support: false,
            end_of_support_date: null,
            end_of_life_date: null
        };
    }

    /**
     * Format date using WordPress settings
     */
    function formatWordPressDate(date, includeTime) {
        // Ensure we have a valid Date object and handle timezone conversion
        if (typeof date === 'string') {
            date = new Date(date);
        }

        // Convert to local timezone (handles UTC to local conversion)
        var localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

        // Danish date format - clean and simple
        var day = localDate.getDate();
        var monthNames = ['januar', 'februar', 'marts', 'april', 'maj', 'juni',
                         'juli', 'august', 'september', 'oktober', 'november', 'december'];
        var month = monthNames[localDate.getMonth()];
        var year = localDate.getFullYear();

        var formattedDate = day + '. ' + month + ' ' + year;

        // Only include time if explicitly requested
        if (includeTime === true) {
            var hours = ('0' + localDate.getHours()).slice(-2);
            var minutes = ('0' + localDate.getMinutes()).slice(-2);
            formattedDate += ' ' + hours + ':' + minutes;
        }

        return formattedDate;
    }

    /**
     * Simple date formatting function (similar to PHP's date function)
     */
    function dateFormat(date, format) {
        var tokens = {
            'Y': date.getFullYear(),
            'm': ('0' + (date.getMonth() + 1)).slice(-2),
            'd': ('0' + date.getDate()).slice(-2),
            'H': ('0' + date.getHours()).slice(-2),
            'i': ('0' + date.getMinutes()).slice(-2),
            's': ('0' + date.getSeconds()).slice(-2),
            'F': ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'][date.getMonth()],
            'M': ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][date.getMonth()],
            'j': date.getDate(),
            'n': date.getMonth() + 1,
            'g': date.getHours() % 12 || 12,
            'G': date.getHours(),
            'a': date.getHours() < 12 ? 'am' : 'pm',
            'A': date.getHours() < 12 ? 'AM' : 'PM'
        };

        return format.replace(/[YmdHisFMonjngaGA]/g, function(match) {
            return tokens[match] || match;
        });
    }

    /**
     * Get human-readable status text from status class
     */
    function getStatusText(statusClass) {
        switch(statusClass) {
            case 'success':
                return 'On Track';
            case 'warning':
                return 'Overdue - Consider updating the site';
            case 'danger':
                return 'Critical - Update the site ASAP';
            case 'gray':
            default:
                return 'Unknown';
        }
    }

    /**
     * Debug function to test element visibility
     * Call this from browser console: maintenanceMondayDebugElements()
     */
    window.maintenanceMondayDebugElements = function() {
        console.log('=== Element Visibility Debug ===');
        var $section = $('#site-status-section');
        var $container = $('#site-last-update');

        console.log('Section exists:', $section.length > 0);
        console.log('Container exists:', $container.length > 0);
        console.log('Section visible:', $section.is(':visible'));
        console.log('Section display:', $section.css('display'));
        console.log('Container HTML:', $container.html());
        console.log('Section element:', $section);
        console.log('Container element:', $container);
    };

    /**
     * Debug function to test site configuration
     * Call this from browser console: maintenanceMondayDebug()
     */
    window.maintenanceMondayDebug = function() {
        console.log('=== Maintenance Monday Debug Info ===');
        console.log('AJAX URL:', maintenanceMondayAjax.ajaxurl);
        console.log('Nonce:', maintenanceMondayAjax.nonce);
        console.log('Date Format:', maintenanceMondayAjax.date_format);
        console.log('Time Format:', maintenanceMondayAjax.time_format);
        console.log('Current PHP Version:', maintenanceMondayAjax.php_version);

        // Test the API call directly
        var formData = new FormData();
        formData.append('action', 'maintenance_monday_fetch_site_status');
        formData.append('nonce', maintenanceMondayAjax.nonce);

        $.ajax({
            url: maintenanceMondayAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('=== Direct API Test Result ===');
                console.log('Response:', response);
                if (response.success && response.data) {
                    var siteData = response.data.data ? response.data.data.data : response.data;
                    console.log('Extracted site data:', siteData);
                    console.log('Status:', siteData.update_status);
                    console.log('Last update:', siteData.last_update);
                    console.log('PHP version info:', siteData.php_version_info);
                }
            },
            error: function(xhr, status, error) {
                console.log('=== Direct API Test Error ===');
                console.log('Error:', error);
                console.log('Status:', status);
                console.log('XHR:', xhr);
            }
        });
    };

})(jQuery);
