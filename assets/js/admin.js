/**
 * Hostaway Real-Time Sync - Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initializeAdminFunctions();
    });

    /**
     * Initialize admin functionality
     */
    function initializeAdminFunctions() {
        initializeConnectionTests();
        initializeManualSync();
        initializeAmenitiesLoader();
        initializeCacheClear();
        initializeSettingsValidation();
    }

    /**
     * Initialize connection tests
     */
    function initializeConnectionTests() {
        // Test Hostaway connection
        $('#test-hostaway-connection').on('click', function() {
            testHostawayConnection();
        });

        // Test Google Maps connection
        $('#test-maps-connection').on('click', function() {
            testGoogleMapsConnection();
        });
    }

    /**
     * Test Hostaway API connection
     */
    function testHostawayConnection() {
        const $button = $('#test-hostaway-connection');
        const $results = $('#connection-results');
        
        // Validate required fields
        const accountId = $('#hostaway_account_id').val();
        const apiKey = $('#hostaway_api_key').val();
        
        if (!accountId || !apiKey) {
            showConnectionResult('error', hostawayAdmin.strings.credentialsRequired || 'Please enter Account ID and API Key first');
            return;
        }

        // Show loading state
        $button.prop('disabled', true).text(hostawayAdmin.strings.testingConnection || 'Testing...');
        $results.removeClass('success error').addClass('loading').text(hostawayAdmin.strings.testingConnection || 'Testing connection...');

        $.ajax({
            url: hostawayAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hostaway_test_connection',
                nonce: hostawayAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showConnectionResult('success', response.message || hostawayAdmin.strings.connectionSuccess || 'Connection successful!');
                } else {
                    showConnectionResult('error', response.message || hostawayAdmin.strings.connectionFailed || 'Connection failed');
                }
            },
            error: function() {
                showConnectionResult('error', hostawayAdmin.strings.connectionFailed || 'Connection failed');
            },
            complete: function() {
                $button.prop('disabled', false).text(hostawayAdmin.strings.testHostawayConnection || 'Test Hostaway Connection');
            }
        });
    }

    /**
     * Test Google Maps connection
     */
    function testGoogleMapsConnection() {
        const $button = $('#test-maps-connection');
        const $results = $('#connection-results');
        
        // Validate API key
        const apiKey = $('#google_maps_api_key').val();
        
        if (!apiKey) {
            showConnectionResult('error', hostawayAdmin.strings.mapsKeyRequired || 'Please enter Google Maps API key first');
            return;
        }

        // Show loading state
        $button.prop('disabled', true).text(hostawayAdmin.strings.testingMaps || 'Testing...');
        $results.removeClass('success error').addClass('loading').text(hostawayAdmin.strings.testingMaps || 'Testing Google Maps...');

        // Test Google Maps API via AJAX
        $.ajax({
            url: hostawayAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hostaway_test_maps',
                nonce: hostawayAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showConnectionResult('success', response.data || hostawayAdmin.strings.mapsConnectionSuccess || 'Google Maps connection successful!');
                } else {
                    showConnectionResult('error', response.data || hostawayAdmin.strings.mapsConnectionFailed || 'Google Maps connection failed');
                }
            },
            error: function() {
                showConnectionResult('error', hostawayAdmin.strings.mapsConnectionFailed || 'Google Maps connection failed');
            },
            complete: function() {
                $button.prop('disabled', false).text(hostawayAdmin.strings.testGoogleMaps || 'Test Google Maps');
            }
        });
    }


    /**
     * Show connection test result
     */
    function showConnectionResult(type, message) {
        const $results = $('#connection-results');
        $results.removeClass('success error loading')
               .addClass(type)
               .text(message);
    }

    /**
     * Initialize manual sync functionality
     */
    function initializeManualSync() {
        $('#manual-sync').on('click', function() {
            const $button = $(this);
            
            // Show loading state
            $button.prop('disabled', true).text(hostawayAdmin.strings.syncing || 'Syncing...');
            
            $.ajax({
                url: hostawayAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'hostaway_manual_sync',
                    nonce: hostawayAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification(hostawayAdmin.strings.syncComplete || 'Sync completed successfully', 'success');
                        updateRecentLogs();
                        updateStats();
                    } else {
                        showNotification(response.data.message || hostawayAdmin.strings.syncFailed || 'Sync failed', 'error');
                    }
                },
                error: function() {
                    showNotification(hostawayAdmin.strings.syncFailed || 'Sync failed', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(hostawayAdmin.strings.syncNow || 'Sync Now');
                }
            });
        });
    }

    /**
     * Initialize amenities loader
     */
    function initializeAmenitiesLoader() {
        $('#load-amenities').on('click', function() {
            const $button = $(this);
            
            // Show loading state
            $button.prop('disabled', true).text(hostawayAdmin.strings.loadingAmenities || 'Loading...');
            
            $.ajax({
                url: hostawayAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'hostaway_get_amenities',
                    nonce: hostawayAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        renderAmenitiesList(response.data);
                        showNotification(hostawayAdmin.strings.amenitiesLoaded || 'Amenities loaded successfully', 'success');
                    } else {
                        showNotification(response.data.message || hostawayAdmin.strings.amenitiesLoadFailed || 'Failed to load amenities', 'error');
                    }
                },
                error: function() {
                    showNotification(hostawayAdmin.strings.amenitiesLoadFailed || 'Failed to load amenities', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(hostawayAdmin.strings.loadAmenities || 'Load Amenities from Hostaway');
                }
            });
        });
    }

    /**
     * Render amenities list
     */
    function renderAmenitiesList(amenities) {
        const $container = $('#amenities-list');
        const selectedAmenities = getSelectedAmenities();
        
        if (Object.keys(amenities).length === 0) {
            $container.html('<p>' + (hostawayAdmin.strings.noAmenitiesFound || 'No amenities found') + '</p>');
            return;
        }
        
        let html = '';
        Object.entries(amenities).forEach(([id, name]) => {
            const isSelected = selectedAmenities.includes(id);
            html += `
                <div class="amenity-item">
                    <label>
                        <input type="checkbox" name="hostaway_sync_selected_amenities[]" value="${id}" ${isSelected ? 'checked' : ''}>
                        ${name}
                    </label>
                </div>
            `;
        });
        
        $container.html(html);
    }

    /**
     * Get currently selected amenities
     */
    function getSelectedAmenities() {
        const selected = [];
        $('input[name="hostaway_sync_selected_amenities[]"]:checked').each(function() {
            selected.push($(this).val());
        });
        return selected;
    }

    /**
     * Initialize cache clear functionality
     */
    function initializeCacheClear() {
        $('#clear-cache').on('click', function() {
            const $button = $(this);
            
            if (!confirm(hostawayAdmin.strings.clearCacheConfirm || 'Are you sure you want to clear all cache? This will force a fresh sync on the next run.')) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).text(hostawayAdmin.strings.clearingCache || 'Clearing...');
            
            $.ajax({
                url: hostawayAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'hostaway_clear_cache',
                    nonce: hostawayAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification(hostawayAdmin.strings.cacheCleared || 'Cache cleared successfully', 'success');
                    } else {
                        showNotification(response.data.message || hostawayAdmin.strings.cacheClearFailed || 'Failed to clear cache', 'error');
                    }
                },
                error: function() {
                    showNotification(hostawayAdmin.strings.cacheClearFailed || 'Failed to clear cache', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(hostawayAdmin.strings.clearCache || 'Clear Cache');
                }
            });
        });
    }

    /**
     * Initialize settings validation
     */
    function initializeSettingsValidation() {
        // Real-time validation for API keys
        $('#hostaway_account_id, #hostaway_api_key').on('blur', function() {
            validateApiCredentials();
        });
        
        // Auto-save selected amenities
        $(document).on('change', 'input[name="hostaway_sync_selected_amenities[]"]', function() {
            saveSelectedAmenities();
        });
        
        // Form submission validation
        $('form').on('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Validate API credentials
     */
    function validateApiCredentials() {
        const accountId = $('#hostaway_account_id').val();
        const apiKey = $('#hostaway_api_key').val();
        
        if (accountId && apiKey) {
            // Enable test connection button
            $('#test-hostaway-connection').prop('disabled', false);
        } else {
            // Disable test connection button
            $('#test-hostaway-connection').prop('disabled', true);
        }
    }

    /**
     * Save selected amenities
     */
    function saveSelectedAmenities() {
        const selectedAmenities = {};
        $('input[name="hostaway_sync_selected_amenities[]"]:checked').each(function() {
            const id = $(this).val();
            const name = $(this).parent().text().trim();
            selectedAmenities[id] = name;
        });
        
        // Store in hidden field for form submission
        let $hiddenField = $('input[name="hostaway_sync_selected_amenities_data"]');
        if ($hiddenField.length === 0) {
            $hiddenField = $('<input type="hidden" name="hostaway_sync_selected_amenities_data">');
            $('form').append($hiddenField);
        }
        $hiddenField.val(JSON.stringify(selectedAmenities));
    }

    /**
     * Validate form before submission
     */
    function validateForm() {
        let isValid = true;
        
        // Clear previous validation messages
        $('.validation-error').remove();
        $('.form-field').removeClass('error');
        
        // Validate required fields
        const requiredFields = [
            { id: 'hostaway_account_id', name: 'Hostaway Account ID' },
            { id: 'hostaway_api_key', name: 'Hostaway API Key' }
        ];
        
        requiredFields.forEach(field => {
            const $field = $(`#${field.id}`);
            if (!$field.val().trim()) {
                showFieldError($field, `${field.name} is required`);
                isValid = false;
            }
        });
        
        // Validate numeric fields
        const numericFields = [
            { id: 'properties_per_page', min: 1, max: 100 },
            { id: 'cache_duration', min: 1, max: 60 }
        ];
        
        numericFields.forEach(field => {
            const $field = $(`#${field.id}`);
            const value = parseInt($field.val());
            if (isNaN(value) || value < field.min || value > field.max) {
                showFieldError($field, `Value must be between ${field.min} and ${field.max}`);
                isValid = false;
            }
        });
        
        return isValid;
    }

    /**
     * Show field validation error
     */
    function showFieldError($field, message) {
        $field.addClass('error');
        $field.after(`<div class="validation-error">${message}</div>`);
    }

    /**
     * Update recent logs display
     */
    function updateRecentLogs() {
        $.ajax({
            url: hostawayAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hostaway_get_recent_logs',
                nonce: hostawayAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#recent-logs').html(response.data);
                }
            }
        });
    }

    /**
     * Update statistics display
     */
    function updateStats() {
        $.ajax({
            url: hostawayAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hostaway_get_stats',
                nonce: hostawayAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatsDisplay(response.data);
                }
            }
        });
    }

    /**
     * Update statistics display
     */
    function updateStatsDisplay(stats) {
        // Update last sync time
        if (stats.last_sync) {
            $('.hostaway-stats-widget li:last-child').html(
                `<strong>Last Sync:</strong> ${new Date(stats.last_sync).toLocaleString()}`
            );
        }
        
        // Update property counts
        $('.hostaway-stats-widget li:nth-child(1)').html(`<strong>Total Properties:</strong> ${stats.total_properties}`);
        $('.hostaway-stats-widget li:nth-child(2)').html(`<strong>Active Properties:</strong> ${stats.active_properties}`);
        $('.hostaway-stats-widget li:nth-child(3)').html(`<strong>Properties with Rates:</strong> ${stats.properties_with_rates}`);
        $('.hostaway-stats-widget li:nth-child(4)').html(`<strong>Properties with Availability:</strong> ${stats.properties_with_availability}`);
    }

    /**
     * Show admin notification
     */
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        $('.hostaway-admin-notification').remove();
        
        const notification = $(`
            <div class="hostaway-admin-notification hostaway-admin-notification-${type}">
                <span class="message">${message}</span>
                <button class="close">&times;</button>
            </div>
        `);
        
        $('.wrap').prepend(notification);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            notification.fadeOut(() => notification.remove());
        }, 5000);
        
        // Close button
        notification.find('.close').on('click', () => {
            notification.fadeOut(() => notification.remove());
        });
    }

    // Add admin notification styles
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .hostaway-admin-notification {
                background: #fff;
                border-radius: 8px;
                padding: 15px 20px;
                margin-bottom: 20px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                display: flex;
                align-items: center;
                gap: 10px;
                animation: slideDown 0.3s ease;
            }
            
            .hostaway-admin-notification-info {
                border-left: 4px solid #007cba;
            }
            
            .hostaway-admin-notification-error {
                border-left: 4px solid #dc3545;
            }
            
            .hostaway-admin-notification-success {
                border-left: 4px solid #28a745;
            }
            
            .hostaway-admin-notification .close {
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                color: #666;
                padding: 0;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-left: auto;
            }
            
            .hostaway-admin-notification .message {
                font-weight: 500;
            }
            
            .validation-error {
                color: #dc3545;
                font-size: 12px;
                margin-top: 5px;
                display: block;
            }
            
            .form-field.error input,
            .form-field.error select {
                border-color: #dc3545;
            }
            
            @keyframes slideDown {
                from {
                    transform: translateY(-20px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
        `)
        .appendTo('head');

})(jQuery);
