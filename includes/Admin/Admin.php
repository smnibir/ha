<?php

namespace HostawaySync\Admin;

use HostawaySync\Database\Database;
use HostawaySync\API\HostawayClient;
use HostawaySync\Sync\Synchronizer;

/**
 * Admin functionality
 */
class Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_hostaway_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_hostaway_manual_sync', array($this, 'ajax_manual_sync'));
        add_action('wp_ajax_hostaway_get_amenities', array($this, 'ajax_get_amenities'));
        add_action('wp_ajax_hostaway_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_hostaway_test_maps', array($this, 'ajax_test_maps'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Hostaway Sync', 'hostaway-sync'),
            __('Hostaway Sync', 'hostaway-sync'),
            'manage_options',
            'hostaway-sync',
            array($this, 'admin_page'),
            'dashicons-update',
            30
        );
        
        add_submenu_page(
            'hostaway-sync',
            __('Settings', 'hostaway-sync'),
            __('Settings', 'hostaway-sync'),
            'manage_options',
            'hostaway-sync',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'hostaway-sync',
            __('Properties', 'hostaway-sync'),
            __('Properties', 'hostaway-sync'),
            'manage_options',
            'hostaway-sync-properties',
            array($this, 'properties_page')
        );
        
        add_submenu_page(
            'hostaway-sync',
            __('Sync Logs', 'hostaway-sync'),
            __('Sync Logs', 'hostaway-sync'),
            'manage_options',
            'hostaway-sync-logs',
            array($this, 'logs_page')
        );
        
        add_submenu_page(
            'hostaway-sync',
            __('Debug', 'hostaway-sync'),
            __('Debug', 'hostaway-sync'),
            'manage_options',
            'hostaway-sync-debug',
            array($this, 'debug_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('hostaway_sync_settings', 'hostaway_sync_hostaway_account_id');
        register_setting('hostaway_sync_settings', 'hostaway_sync_hostaway_api_key');
        register_setting('hostaway_sync_settings', 'hostaway_sync_google_maps_api_key');
        register_setting('hostaway_sync_settings', 'hostaway_sync_auto_sync_enabled');
        register_setting('hostaway_sync_settings', 'hostaway_sync_selected_amenities');
        register_setting('hostaway_sync_settings', 'hostaway_sync_properties_per_page');
        register_setting('hostaway_sync_settings', 'hostaway_sync_cache_duration');
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'hostaway-sync') === false) {
            return;
        }
        
        wp_enqueue_script(
            'hostaway-admin',
            HOSTAWAY_SYNC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            HOSTAWAY_SYNC_VERSION,
            true
        );
        
        wp_enqueue_style(
            'hostaway-admin',
            HOSTAWAY_SYNC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            HOSTAWAY_SYNC_VERSION
        );
        
        wp_localize_script('hostaway-admin', 'hostawayAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hostaway_admin_nonce'),
            'strings' => array(
                'testingConnection' => __('Testing connection...', 'hostaway-sync'),
                'connectionSuccess' => __('Connection successful!', 'hostaway-sync'),
                'connectionFailed' => __('Connection failed', 'hostaway-sync'),
                'syncing' => __('Syncing...', 'hostaway-sync'),
                'syncComplete' => __('Sync completed', 'hostaway-sync'),
                'syncFailed' => __('Sync failed', 'hostaway-sync'),
                'clearingCache' => __('Clearing cache...', 'hostaway-sync'),
                'cacheCleared' => __('Cache cleared', 'hostaway-sync')
            )
        ));
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $stats = $this->get_sync_stats();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Hostaway Sync Settings', 'hostaway-sync'); ?></h1>
            
            <?php $this->display_admin_notices(); ?>
            
            <div class="hostaway-admin-container">
                <div class="hostaway-main-content">
                    <form method="post" action="">
                        <?php wp_nonce_field('hostaway_sync_settings', 'hostaway_sync_nonce'); ?>
                        
                        <div class="hostaway-settings-section">
                            <h2><?php _e('API Configuration', 'hostaway-sync'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="hostaway_account_id"><?php _e('Hostaway Account ID', 'hostaway-sync'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="hostaway_account_id" name="hostaway_sync_hostaway_account_id" 
                                               value="<?php echo esc_attr(get_option('hostaway_sync_hostaway_account_id', '')); ?>" 
                                               class="regular-text" />
                                        <p class="description">
                                            <?php _e('Your Hostaway Account ID from Settings > Hostaway API.', 'hostaway-sync'); ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="hostaway_api_key"><?php _e('Hostaway API Key', 'hostaway-sync'); ?></label>
                                    </th>
                                    <td>
                                        <input type="password" id="hostaway_api_key" name="hostaway_sync_hostaway_api_key" 
                                               value="<?php echo esc_attr(get_option('hostaway_sync_hostaway_api_key', '')); ?>" 
                                               class="regular-text" />
                                        <p class="description">
                                            <?php _e('Your Hostaway API Key from Settings > Hostaway API.', 'hostaway-sync'); ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="google_maps_api_key"><?php _e('Google Maps API Key', 'hostaway-sync'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="google_maps_api_key" name="hostaway_sync_google_maps_api_key" 
                                               value="<?php echo esc_attr(get_option('hostaway_sync_google_maps_api_key', '')); ?>" 
                                               class="regular-text" />
                                        <p class="description">
                                            <?php _e('Your Google Maps API Key for map functionality.', 'hostaway-sync'); ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Test Connections', 'hostaway-sync'); ?></th>
                                    <td>
                                        <button type="button" id="test-hostaway-connection" class="button">
                                            <?php _e('Test Hostaway Connection', 'hostaway-sync'); ?>
                                        </button>
                                        <button type="button" id="test-maps-connection" class="button">
                                            <?php _e('Test Google Maps', 'hostaway-sync'); ?>
                                        </button>
                                        <div id="connection-results"></div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="hostaway-settings-section">
                            <h2><?php _e('Sync Configuration', 'hostaway-sync'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Auto Sync', 'hostaway-sync'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="hostaway_sync_auto_sync_enabled" value="1" 
                                                   <?php checked(get_option('hostaway_sync_auto_sync_enabled', true)); ?> />
                                            <?php _e('Enable automatic synchronization every 10 minutes', 'hostaway-sync'); ?>
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Manual Sync', 'hostaway-sync'); ?></th>
                                    <td>
                                        <button type="button" id="manual-sync" class="button button-primary">
                                            <?php _e('Sync Now', 'hostaway-sync'); ?>
                                        </button>
                                        <p class="description">
                                            <?php _e('Manually trigger property synchronization.', 'hostaway-sync'); ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Cache Management', 'hostaway-sync'); ?></th>
                                    <td>
                                        <button type="button" id="clear-cache" class="button">
                                            <?php _e('Clear Cache', 'hostaway-sync'); ?>
                                        </button>
                                        <p class="description">
                                            <?php _e('Clear all cached data and force fresh sync.', 'hostaway-sync'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="hostaway-settings-section">
                            <h2><?php _e('Amenity Filter Settings', 'hostaway-sync'); ?></h2>
                            <p><?php _e('Select which amenities should appear in the frontend filter sidebar.', 'hostaway-sync'); ?></p>
                            
                            <div id="amenities-container">
                                <button type="button" id="load-amenities" class="button">
                                    <?php _e('Load Amenities from Hostaway', 'hostaway-sync'); ?>
                                </button>
                                <div id="amenities-list"></div>
                            </div>
                        </div>
                        
                        <div class="hostaway-settings-section">
                            <h2><?php _e('Display Settings', 'hostaway-sync'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="properties_per_page"><?php _e('Properties per Page', 'hostaway-sync'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="properties_per_page" name="hostaway_sync_properties_per_page" 
                                               value="<?php echo esc_attr(get_option('hostaway_sync_properties_per_page', 15)); ?>" 
                                               min="1" max="100" class="small-text" />
                                        <p class="description">
                                            <?php _e('Number of properties to display per page.', 'hostaway-sync'); ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="cache_duration"><?php _e('Cache Duration (minutes)', 'hostaway-sync'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="cache_duration" name="hostaway_sync_cache_duration" 
                                               value="<?php echo esc_attr(get_option('hostaway_sync_cache_duration', 10)); ?>" 
                                               min="1" max="60" class="small-text" />
                                        <p class="description">
                                            <?php _e('How long to cache API responses.', 'hostaway-sync'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <?php submit_button(__('Save Settings', 'hostaway-sync')); ?>
                    </form>
                </div>
                
                <div class="hostaway-sidebar">
                    <div class="hostaway-stats-widget">
                        <h3><?php _e('Sync Statistics', 'hostaway-sync'); ?></h3>
                        <ul>
                            <li><strong><?php _e('Total Properties:', 'hostaway-sync'); ?></strong> <?php echo $stats['total_properties']; ?></li>
                            <li><strong><?php _e('Active Properties:', 'hostaway-sync'); ?></strong> <?php echo $stats['active_properties']; ?></li>
                            <li><strong><?php _e('Properties with Rates:', 'hostaway-sync'); ?></strong> <?php echo $stats['properties_with_rates']; ?></li>
                            <li><strong><?php _e('Properties with Availability:', 'hostaway-sync'); ?></strong> <?php echo $stats['properties_with_availability']; ?></li>
                            <li><strong><?php _e('Last Sync:', 'hostaway-sync'); ?></strong> <?php echo $stats['last_sync'] ? date('Y-m-d H:i:s', strtotime($stats['last_sync'])) : __('Never', 'hostaway-sync'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="hostaway-recent-logs-widget">
                        <h3><?php _e('Recent Sync Logs', 'hostaway-sync'); ?></h3>
                        <div id="recent-logs">
                            <?php $this->display_recent_logs(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Properties page
     */
    public function properties_page() {
        global $wpdb;
        
        $properties_table = Database::get_properties_table();
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($search)) {
            $where_conditions[] = "(name LIKE %s OR city LIKE %s OR country LIKE %s)";
            $where_values[] = "%$search%";
            $where_values[] = "%$search%";
            $where_values[] = "%$search%";
        }
        
        if (!empty($status)) {
            $where_conditions[] = "status = %s";
            $where_values[] = $status;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM $properties_table WHERE $where_clause";
        if (!empty($where_values)) {
            $total_query = $wpdb->prepare($total_query, $where_values);
        }
        $total = $wpdb->get_var($total_query);
        
        // Get properties
        $query = "SELECT * FROM $properties_table WHERE $where_clause ORDER BY last_updated DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        $properties = $wpdb->get_results($wpdb->prepare($query, $query_values));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Properties', 'hostaway-sync'); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="hostaway-sync-properties" />
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search properties...', 'hostaway-sync'); ?>" />
                        <select name="status">
                            <option value=""><?php _e('All Statuses', 'hostaway-sync'); ?></option>
                            <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active', 'hostaway-sync'); ?></option>
                            <option value="inactive" <?php selected($status, 'inactive'); ?>><?php _e('Inactive', 'hostaway-sync'); ?></option>
                        </select>
                        <?php submit_button(__('Filter', 'hostaway-sync'), 'secondary', 'submit', false); ?>
                    </form>
                </div>
                
                <div class="tablenav-pages">
                    <?php
                    $total_pages = ceil($total / $per_page);
                    if ($total_pages > 1) {
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $page
                        ));
                    }
                    ?>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'hostaway-sync'); ?></th>
                        <th><?php _e('Location', 'hostaway-sync'); ?></th>
                        <th><?php _e('Type', 'hostaway-sync'); ?></th>
                        <th><?php _e('Rooms/Baths', 'hostaway-sync'); ?></th>
                        <th><?php _e('Guests', 'hostaway-sync'); ?></th>
                        <th><?php _e('Price', 'hostaway-sync'); ?></th>
                        <th><?php _e('Status', 'hostaway-sync'); ?></th>
                        <th><?php _e('Last Updated', 'hostaway-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($properties)): ?>
                        <tr>
                            <td colspan="8"><?php _e('No properties found.', 'hostaway-sync'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($properties as $property): ?>
                            <tr>
                                <td><strong><?php echo esc_html($property->name); ?></strong></td>
                                <td><?php echo esc_html($property->city . ', ' . $property->country); ?></td>
                                <td><?php echo esc_html($property->property_type); ?></td>
                                <td><?php echo $property->room_count . '/' . $property->bathroom_count; ?></td>
                                <td><?php echo $property->guest_capacity; ?></td>
                                <td><?php echo $property->currency . ' ' . number_format($property->base_price, 2); ?></td>
                                <td>
                                    <span class="status-<?php echo $property->status; ?>">
                                        <?php echo ucfirst($property->status); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($property->last_updated)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        $logs = Database::get_recent_logs(100);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Sync Logs', 'hostaway-sync'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'hostaway-sync'); ?></th>
                        <th><?php _e('Type', 'hostaway-sync'); ?></th>
                        <th><?php _e('Status', 'hostaway-sync'); ?></th>
                        <th><?php _e('Message', 'hostaway-sync'); ?></th>
                        <th><?php _e('Execution Time', 'hostaway-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5"><?php _e('No logs found.', 'hostaway-sync'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log->created_at)); ?></td>
                                <td><?php echo esc_html($log->sync_type); ?></td>
                                <td>
                                    <span class="status-<?php echo $log->status; ?>">
                                        <?php echo ucfirst($log->status); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log->message); ?></td>
                                <td><?php echo number_format($log->execution_time, 4); ?>s</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['hostaway_sync_nonce'], 'hostaway_sync_settings')) {
            wp_die(__('Security check failed', 'hostaway-sync'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hostaway-sync'));
        }
        
        $fields = array(
            'hostaway_sync_hostaway_account_id',
            'hostaway_sync_hostaway_api_key',
            'hostaway_sync_google_maps_api_key',
            'hostaway_sync_auto_sync_enabled',
            'hostaway_sync_selected_amenities',
            'hostaway_sync_properties_per_page',
            'hostaway_sync_cache_duration'
        );
        
        foreach ($fields as $field) {
            $value = isset($_POST[$field]) ? $_POST[$field] : '';
            
            if ($field === 'hostaway_sync_auto_sync_enabled') {
                $value = !empty($value) ? 1 : 0;
            } elseif ($field === 'hostaway_sync_selected_amenities') {
                $value = is_array($value) ? $value : array();
            } else {
                $value = sanitize_text_field($value);
            }
            
            update_option($field, $value);
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully.', 'hostaway-sync') . '</p></div>';
        });
    }
    
    /**
     * Display admin notices
     */
    private function display_admin_notices() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-warning"><p>';
            _e('WooCommerce is required for booking functionality.', 'hostaway-sync');
            echo '</p></div>';
        }
        
        // Check API configuration
        if (empty(get_option('hostaway_sync_hostaway_account_id')) || empty(get_option('hostaway_sync_hostaway_api_key'))) {
            echo '<div class="notice notice-error"><p>';
            _e('Please configure your Hostaway Account ID and API Key to enable synchronization.', 'hostaway-sync');
            echo '</p></div>';
        }
    }
    
    /**
     * Get sync statistics
     */
    private function get_sync_stats() {
        $sync = new Synchronizer();
        return $sync->get_property_stats();
    }
    
    /**
     * Display recent logs
     */
    private function display_recent_logs() {
        $logs = Database::get_recent_logs(5);
        
        if (empty($logs)) {
            echo '<p>' . __('No recent logs.', 'hostaway-sync') . '</p>';
            return;
        }
        
        echo '<ul>';
        foreach ($logs as $log) {
            $status_class = 'status-' . $log->status;
            echo '<li class="' . $status_class . '">';
            echo '<strong>' . date('H:i:s', strtotime($log->created_at)) . '</strong> - ';
            echo esc_html($log->message);
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('hostaway_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hostaway-sync'));
        }
        
        $api_client = new HostawayClient();
        $result = $api_client->test_connection();
        
        wp_send_json($result);
    }
    
    /**
     * AJAX manual sync
     */
    public function ajax_manual_sync() {
        check_ajax_referer('hostaway_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hostaway-sync'));
        }
        
        $sync = new Synchronizer();
        $sync->sync_properties();
        
        wp_send_json_success(__('Manual sync completed', 'hostaway-sync'));
    }
    
    /**
     * AJAX get amenities
     */
    public function ajax_get_amenities() {
        check_ajax_referer('hostaway_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hostaway-sync'));
        }
        
        $api_client = new HostawayClient();
        $amenities = $api_client->get_all_amenities();
        
        wp_send_json_success($amenities);
    }
    
    /**
     * AJAX clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('hostaway_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hostaway-sync'));
        }
        
        $sync = new Synchronizer();
        $sync->clear_cache();
        
        wp_send_json_success(__('Cache cleared', 'hostaway-sync'));
    }
    
    /**
     * AJAX test maps
     */
    public function ajax_test_maps() {
        check_ajax_referer('hostaway_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hostaway-sync'));
        }
        
        $api_key = get_option('hostaway_sync_google_maps_api_key', '');
        
        if (empty($api_key)) {
            wp_send_json_error(__('Google Maps API key not configured', 'hostaway-sync'));
        }
        
        // Simple test by making a request to Google Maps API
        $test_url = "https://maps.googleapis.com/maps/api/js?key={$api_key}&libraries=places";
        $response = wp_remote_get($test_url);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            wp_send_json_success(__('Google Maps connection successful', 'hostaway-sync'));
        } else {
            wp_send_json_error(__('Google Maps connection failed', 'hostaway-sync'));
        }
    }
    
    /**
     * Debug page
     */
    public function debug_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Debug Information', 'hostaway-sync'); ?></h1>
            
            <div class="hostaway-debug-info">
                <h2><?php _e('API Configuration', 'hostaway-sync'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Account ID', 'hostaway-sync'); ?></th>
                        <td><?php echo get_option('hostaway_sync_hostaway_account_id') ? '✅ Configured' : '❌ Missing'; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('API Key', 'hostaway-sync'); ?></th>
                        <td><?php echo get_option('hostaway_sync_hostaway_api_key') ? '✅ Configured' : '❌ Missing'; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Google Maps API Key', 'hostaway-sync'); ?></th>
                        <td><?php echo get_option('hostaway_sync_google_maps_api_key') ? '✅ Configured' : '❌ Missing'; ?></td>
                    </tr>
                </table>
                
                <h2><?php _e('API Test', 'hostaway-sync'); ?></h2>
                <button type="button" id="debug-test-api" class="button button-primary">
                    <?php _e('Test API Connection', 'hostaway-sync'); ?>
                </button>
                <div id="debug-results"></div>
                
                <h2><?php _e('Recent Error Logs', 'hostaway-sync'); ?></h2>
                <div id="debug-logs">
                    <?php
                    $log_file = WP_CONTENT_DIR . '/debug.log';
                    if (file_exists($log_file)) {
                        $logs = file_get_contents($log_file);
                        $hostaway_logs = array_filter(explode("\n", $logs), function($line) {
                            return strpos($line, 'Hostaway') !== false;
                        });
                        $recent_logs = array_slice($hostaway_logs, -10);
                        
                        if (!empty($recent_logs)) {
                            echo '<pre>' . esc_html(implode("\n", $recent_logs)) . '</pre>';
                        } else {
                            echo '<p>' . __('No Hostaway-related logs found.', 'hostaway-sync') . '</p>';
                        }
                    } else {
                        echo '<p>' . __('Debug log file not found.', 'hostaway-sync') . '</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#debug-test-api').on('click', function() {
                var $button = $(this);
                var $results = $('#debug-results');
                
                $button.prop('disabled', true).text('Testing...');
                $results.html('<p>Testing API connection...</p>');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'hostaway_test_connection',
                        nonce: '<?php echo wp_create_nonce('hostaway_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        $results.html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
                    },
                    error: function(xhr, status, error) {
                        $results.html('<p style="color: red;">Error: ' + error + '</p>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Test API Connection');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
