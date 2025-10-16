<?php

namespace HostawayWP\Admin;

/**
 * Admin settings page
 */
class Settings {
    
    /**
     * Render settings page
     */
    public function renderPage() {
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['hostaway_settings_nonce'], 'hostaway_settings')) {
            $this->saveSettings();
        }
        
        // Get current settings
        $settings = $this->getSettings();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Hostaway Settings', 'hostaway-wp'); ?></h1>
            
            <?php $this->renderNotices(); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('hostaway_settings', 'hostaway_settings_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="hostaway_account_id"><?php esc_html_e('Account ID', 'hostaway-wp'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="hostaway_account_id" 
                                       name="hostaway_account_id" 
                                       value="<?php echo esc_attr($settings['account_id']); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e('Your Hostaway Account ID (Client ID)', 'hostaway-wp'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="hostaway_api_key"><?php esc_html_e('API Key', 'hostaway-wp'); ?></label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="hostaway_api_key" 
                                       name="hostaway_api_key" 
                                       value="<?php echo esc_attr($settings['api_key']); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e('Your Hostaway API Key (Client Secret)', 'hostaway-wp'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="google_maps_api_key"><?php esc_html_e('Google Maps API Key', 'hostaway-wp'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="google_maps_api_key" 
                                       name="google_maps_api_key" 
                                       value="<?php echo esc_attr($settings['google_maps_api_key']); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e('Your Google Maps API key for map functionality', 'hostaway-wp'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="currency"><?php esc_html_e('Currency', 'hostaway-wp'); ?></label>
                            </th>
                            <td>
                                <select id="currency" name="currency">
                                    <?php
                                    $currencies = [
                                        'USD' => 'US Dollar ($)',
                                        'EUR' => 'Euro (€)',
                                        'GBP' => 'British Pound (£)',
                                        'CAD' => 'Canadian Dollar (C$)',
                                        'AUD' => 'Australian Dollar (A$)',
                                    ];
                                    
                                    foreach ($currencies as $code => $name) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($code),
                                            selected($settings['currency'], $code, false),
                                            esc_html($name)
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="locale"><?php esc_html_e('Locale', 'hostaway-wp'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="locale" 
                                       name="locale" 
                                       value="<?php echo esc_attr($settings['locale']); ?>" 
                                       class="regular-text" 
                                       placeholder="en_US" />
                                <p class="description">
                                    <?php esc_html_e('Default locale for date and number formatting', 'hostaway-wp'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="timezone"><?php esc_html_e('Timezone', 'hostaway-wp'); ?></label>
                            </th>
                            <td>
                                <select id="timezone" name="timezone">
                                    <?php
                                    $timezones = timezone_identifiers_list();
                                    foreach ($timezones as $timezone) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($timezone),
                                            selected($settings['timezone'], $timezone, false),
                                            esc_html($timezone)
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="sync_interval"><?php esc_html_e('Sync Interval', 'hostaway-wp'); ?></label>
                            </th>
                            <td>
                                <select id="sync_interval" name="sync_interval">
                                    <option value="15min" <?php selected($settings['sync_interval'], '15min'); ?>>
                                        <?php esc_html_e('Every 15 minutes', 'hostaway-wp'); ?>
                                    </option>
                                    <option value="hourly" <?php selected($settings['sync_interval'], 'hourly'); ?>>
                                        <?php esc_html_e('Hourly', 'hostaway-wp'); ?>
                                    </option>
                                    <option value="6hourly" <?php selected($settings['sync_interval'], '6hourly'); ?>>
                                        <?php esc_html_e('Every 6 hours', 'hostaway-wp'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="properties_per_page"><?php esc_html_e('Properties per Page', 'hostaway-wp'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="properties_per_page" 
                                       name="properties_per_page" 
                                       value="<?php echo esc_attr($settings['properties_per_page']); ?>" 
                                       min="1" 
                                       max="50" 
                                       class="small-text" />
                                <p class="description">
                                    <?php esc_html_e('Number of properties to show per page', 'hostaway-wp'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="enable_map"><?php esc_html_e('Enable Map', 'hostaway-wp'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="enable_map" 
                                           name="enable_map" 
                                           value="1" 
                                           <?php checked($settings['enable_map']); ?> />
                                    <?php esc_html_e('Enable map functionality on properties page', 'hostaway-wp'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="enable_filters"><?php esc_html_e('Enable Filters', 'hostaway-wp'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="enable_filters" 
                                           name="enable_filters" 
                                           value="1" 
                                           <?php checked($settings['enable_filters']); ?> />
                                    <?php esc_html_e('Enable property filters', 'hostaway-wp'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="enable_instant_booking"><?php esc_html_e('Enable Instant Booking', 'hostaway-wp'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="enable_instant_booking" 
                                           name="enable_instant_booking" 
                                           value="1" 
                                           <?php checked($settings['enable_instant_booking']); ?> />
                                    <?php esc_html_e('Allow instant booking without approval', 'hostaway-wp'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button(__('Save Settings', 'hostaway-wp')); ?>
            </form>
            
            <hr />
            
            <h2><?php esc_html_e('API Testing', 'hostaway-wp'); ?></h2>
            <p><?php esc_html_e('Test your API connection and sync properties manually.', 'hostaway-wp'); ?></p>
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Test API Connection', 'hostaway-wp'); ?></th>
                        <td>
                            <button type="button" 
                                    id="test-api-connection" 
                                    class="button button-secondary">
                                <?php esc_html_e('Test Connection', 'hostaway-wp'); ?>
                            </button>
                            <span id="api-test-result"></span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Manual Sync', 'hostaway-wp'); ?></th>
                        <td>
                            <button type="button" 
                                    id="manual-sync" 
                                    class="button button-secondary">
                                <?php esc_html_e('Sync Now', 'hostaway-wp'); ?>
                            </button>
                            <span id="sync-result"></span>
                            <p class="description">
                                <?php esc_html_e('Manually sync all properties, rates, and availability data', 'hostaway-wp'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <hr />
            
            <h2><?php esc_html_e('Shortcodes', 'hostaway-wp'); ?></h2>
            <p><?php esc_html_e('Use these shortcodes to display Hostaway functionality on your pages:', 'hostaway-wp'); ?></p>
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Search Form', 'hostaway-wp'); ?></th>
                        <td>
                            <code>[hostaway_search]</code>
                            <p class="description">
                                <?php esc_html_e('Displays the property search form', 'hostaway-wp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Properties List', 'hostaway-wp'); ?></th>
                        <td>
                            <code>[hostaway_properties]</code>
                            <p class="description">
                                <?php esc_html_e('Displays the properties listing page with search and filters', 'hostaway-wp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Single Property', 'hostaway-wp'); ?></th>
                        <td>
                            <code>[hostaway_property id="123"]</code>
                            <p class="description">
                                <?php esc_html_e('Displays a single property (optional, uses post template by default)', 'hostaway-wp'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Test API connection
            $('#test-api-connection').on('click', function() {
                var button = $(this);
                var result = $('#api-test-result');
                
                button.prop('disabled', true).text('<?php esc_js_e('Testing...', 'hostaway-wp'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hostaway_test_api',
                        nonce: '<?php echo wp_create_nonce('hostaway_admin_nonce'); ?>',
                        account_id: $('#hostaway_account_id').val(),
                        api_key: $('#hostaway_api_key').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                        } else {
                            result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        result.html('<span style="color: red;"><?php esc_js_e('Connection test failed', 'hostaway-wp'); ?></span>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php esc_js_e('Test Connection', 'hostaway-wp'); ?>');
                    }
                });
            });
            
            // Manual sync
            $('#manual-sync').on('click', function() {
                var button = $(this);
                var result = $('#sync-result');
                
                button.prop('disabled', true).text('<?php esc_js_e('Syncing...', 'hostaway-wp'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hostaway_sync_now',
                        nonce: '<?php echo wp_create_nonce('hostaway_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<span style="color: green;">✓ <?php esc_js_e('Sync completed successfully', 'hostaway-wp'); ?></span>');
                        } else {
                            result.html('<span style="color: red;">✗ <?php esc_js_e('Sync failed', 'hostaway-wp'); ?></span>');
                        }
                    },
                    error: function() {
                        result.html('<span style="color: red;"><?php esc_js_e('Sync request failed', 'hostaway-wp'); ?></span>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php esc_js_e('Sync Now', 'hostaway-wp'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get current settings
     */
    private function getSettings() {
        return [
            'account_id' => get_option('hostaway_wp_account_id', ''),
            'api_key' => get_option('hostaway_wp_api_key', ''),
            'google_maps_api_key' => get_option('hostaway_wp_google_maps_api_key', ''),
            'currency' => get_option('hostaway_wp_currency', 'USD'),
            'locale' => get_option('hostaway_wp_locale', 'en_US'),
            'timezone' => get_option('hostaway_wp_timezone', 'UTC'),
            'sync_interval' => get_option('hostaway_wp_sync_interval', 'hourly'),
            'properties_per_page' => get_option('hostaway_wp_properties_per_page', 15),
            'enable_map' => get_option('hostaway_wp_enable_map', true),
            'enable_filters' => get_option('hostaway_wp_enable_filters', true),
            'enable_instant_booking' => get_option('hostaway_wp_enable_instant_booking', true),
        ];
    }
    
    /**
     * Save settings
     */
    private function saveSettings() {
        $settings = [
            'hostaway_wp_account_id' => sanitize_text_field($_POST['hostaway_account_id'] ?? ''),
            'hostaway_wp_api_key' => sanitize_text_field($_POST['hostaway_api_key'] ?? ''),
            'hostaway_wp_google_maps_api_key' => sanitize_text_field($_POST['google_maps_api_key'] ?? ''),
            'hostaway_wp_currency' => sanitize_text_field($_POST['currency'] ?? 'USD'),
            'hostaway_wp_locale' => sanitize_text_field($_POST['locale'] ?? 'en_US'),
            'hostaway_wp_timezone' => sanitize_text_field($_POST['timezone'] ?? 'UTC'),
            'hostaway_wp_sync_interval' => sanitize_text_field($_POST['sync_interval'] ?? 'hourly'),
            'hostaway_wp_properties_per_page' => intval($_POST['properties_per_page'] ?? 15),
            'hostaway_wp_enable_map' => !empty($_POST['enable_map']),
            'hostaway_wp_enable_filters' => !empty($_POST['enable_filters']),
            'hostaway_wp_enable_instant_booking' => !empty($_POST['enable_instant_booking']),
        ];
        
        foreach ($settings as $option => $value) {
            update_option($option, $value);
        }
        
        // Update sync interval
        $this->updateSyncSchedule($settings['hostaway_wp_sync_interval']);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('Settings saved successfully!', 'hostaway-wp') . '</p>';
            echo '</div>';
        });
    }
    
    /**
     * Update sync schedule
     */
    private function updateSyncSchedule($interval) {
        // Clear existing schedules
        wp_clear_scheduled_hook('hostaway_wp_sync_properties');
        wp_clear_scheduled_hook('hostaway_wp_sync_rates');
        wp_clear_scheduled_hook('hostaway_wp_sync_availability');
        
        // Set new schedule
        $schedule_map = [
            '15min' => 'hostaway_wp_15min',
            'hourly' => 'hourly',
            '6hourly' => 'hostaway_wp_6hourly',
        ];
        
        $schedule = $schedule_map[$interval] ?? 'hourly';
        
        if (!wp_next_scheduled('hostaway_wp_sync_properties')) {
            wp_schedule_event(time(), $schedule, 'hostaway_wp_sync_properties');
        }
        
        if (!wp_next_scheduled('hostaway_wp_sync_rates')) {
            wp_schedule_event(time(), $schedule, 'hostaway_wp_sync_rates');
        }
        
        if (!wp_next_scheduled('hostaway_wp_sync_availability')) {
            wp_schedule_event(time(), $schedule, 'hostaway_wp_sync_availability');
        }
        
        // Register custom cron intervals
        add_filter('cron_schedules', function($schedules) {
            $schedules['hostaway_wp_15min'] = [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display' => __('Every 15 minutes', 'hostaway-wp'),
            ];
            
            $schedules['hostaway_wp_6hourly'] = [
                'interval' => 6 * HOUR_IN_SECONDS,
                'display' => __('Every 6 hours', 'hostaway-wp'),
            ];
            
            return $schedules;
        });
    }
    
    /**
     * Render admin notices
     */
    private function renderNotices() {
        $account_id = get_option('hostaway_wp_account_id');
        $api_key = get_option('hostaway_wp_api_key');
        
        if (!$account_id || !$api_key) {
            echo '<div class="notice notice-warning">';
            echo '<p>' . esc_html__('Please configure your Hostaway Account ID and API Key to enable synchronization.', 'hostaway-wp') . '</p>';
            echo '</div>';
        }
        
        $google_maps_key = get_option('hostaway_wp_google_maps_api_key');
        if (!$google_maps_key) {
            echo '<div class="notice notice-info">';
            echo '<p>' . esc_html__('Google Maps API key is required for map functionality.', 'hostaway-wp') . '</p>';
            echo '</div>';
        }
    }
}
