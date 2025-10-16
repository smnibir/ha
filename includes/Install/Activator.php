<?php

namespace HostawayWP\Install;

/**
 * Plugin activation handler
 */
class Activator {
    
    /**
     * Activate plugin
     */
    public static function activate() {
        try {
            self::createTables();
            self::scheduleCronJobs();
            self::createPages();
            self::setDefaultOptions();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
        } catch (Exception $e) {
            // Log error and deactivate plugin
            error_log('Hostaway WP Plugin activation failed: ' . $e->getMessage());
            deactivate_plugins(plugin_basename(HOSTAWAY_WP_PLUGIN_FILE));
            wp_die('Plugin activation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Create custom database tables
     */
    private static function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Properties table
        $properties_table = $wpdb->prefix . 'hostaway_properties';
        $properties_sql = "CREATE TABLE `$properties_table` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `hostaway_id` varchar(100) NOT NULL,
            `title` varchar(255) NOT NULL,
            `slug` varchar(255) NOT NULL,
            `type` varchar(100) NOT NULL,
            `country` varchar(100) DEFAULT NULL,
            `city` varchar(100) DEFAULT NULL,
            `address` text DEFAULT NULL,
            `latitude` decimal(10,8) DEFAULT NULL,
            `longitude` decimal(11,8) DEFAULT NULL,
            `rooms` int(11) DEFAULT 0,
            `bathrooms` int(11) DEFAULT 0,
            `guests` int(11) DEFAULT 0,
            `base_price` decimal(10,2) DEFAULT 0.00,
            `thumbnail_url` varchar(500) DEFAULT NULL,
            `thumbnail_id` bigint(20) DEFAULT NULL,
            `gallery_json` longtext DEFAULT NULL,
            `amenities_json` longtext DEFAULT NULL,
            `features_json` longtext DEFAULT NULL,
            `description` longtext DEFAULT NULL,
            `status` varchar(20) DEFAULT 'active',
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `hostaway_id` (`hostaway_id`),
            KEY `slug` (`slug`),
            KEY `type` (`type`),
            KEY `location` (`country`, `city`),
            KEY `coordinates` (`latitude`, `longitude`),
            KEY `status` (`status`)
        ) $charset_collate;";
        
        // Rates table
        $rates_table = $wpdb->prefix . 'hostaway_rates';
        $rates_sql = "CREATE TABLE `$rates_table` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `property_id` bigint(20) NOT NULL,
            `date` date NOT NULL,
            `price` decimal(10,2) NOT NULL,
            `min_nights` int(11) DEFAULT 1,
            `max_guests` int(11) DEFAULT NULL,
            `currency` varchar(3) DEFAULT 'USD',
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `property_date` (`property_id`, `date`),
            KEY `property_id` (`property_id`),
            KEY `date` (`date`),
            KEY `price` (`price`)
        ) $charset_collate;";
        
        // Availability table
        $availability_table = $wpdb->prefix . 'hostaway_availability';
        $availability_sql = "CREATE TABLE `$availability_table` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `property_id` bigint(20) NOT NULL,
            `date` date NOT NULL,
            `is_booked` tinyint(1) DEFAULT 0,
            `is_available` tinyint(1) DEFAULT 1,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `property_date` (`property_id`, `date`),
            KEY `property_id` (`property_id`),
            KEY `date` (`date`),
            KEY `is_booked` (`is_booked`),
            KEY `is_available` (`is_available`)
        ) $charset_collate;";
        
        // Sync log table
        $sync_log_table = $wpdb->prefix . 'hostaway_sync_log';
        $sync_log_sql = "CREATE TABLE `$sync_log_table` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `action` varchar(50) NOT NULL,
            `status` varchar(20) NOT NULL,
            `message` text DEFAULT NULL,
            `data` longtext DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `action` (`action`),
            KEY `status` (`status`),
            KEY `created_at` (`created_at`)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        dbDelta($properties_sql);
        dbDelta($rates_sql);
        dbDelta($availability_sql);
        dbDelta($sync_log_sql);
        
        // Store database version
        update_option('hostaway_wp_db_version', '1.0.0');
    }
    
    /**
     * Schedule cron jobs
     */
    private static function scheduleCronJobs() {
        // Add custom cron intervals
        add_filter('cron_schedules', function($schedules) {
            $schedules['hostaway_10min'] = [
                'interval' => 10 * MINUTE_IN_SECONDS,
                'display' => __('Every 10 minutes', 'hostaway-wp'),
            ];
            
            $schedules['hostaway_15min'] = [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display' => __('Every 15 minutes', 'hostaway-wp'),
            ];
            
            $schedules['hostaway_6hourly'] = [
                'interval' => 6 * HOUR_IN_SECONDS,
                'display' => __('Every 6 hours', 'hostaway-wp'),
            ];
            
            return $schedules;
        });
        
        // Clear existing schedules first
        wp_clear_scheduled_hook('hostaway_wp_sync_properties');
        wp_clear_scheduled_hook('hostaway_wp_sync_rates');
        wp_clear_scheduled_hook('hostaway_wp_sync_availability');
        
        // Schedule new jobs with 10-minute interval
        if (!wp_next_scheduled('hostaway_wp_sync_properties')) {
            wp_schedule_event(time(), 'hostaway_10min', 'hostaway_wp_sync_properties');
        }
        
        if (!wp_next_scheduled('hostaway_wp_sync_rates')) {
            wp_schedule_event(time(), 'hostaway_10min', 'hostaway_wp_sync_rates');
        }
        
        if (!wp_next_scheduled('hostaway_wp_sync_availability')) {
            wp_schedule_event(time(), 'hostaway_10min', 'hostaway_wp_sync_availability');
        }
    }
    
    /**
     * Create default pages
     */
    private static function createPages() {
        // Properties page
        $existing_properties_page = get_posts([
            'name' => 'properties',
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);
        
        if (empty($existing_properties_page)) {
            $page_id = wp_insert_post([
                'post_title' => 'Properties',
                'post_content' => '[hostaway_properties]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'properties',
            ]);
            
            if ($page_id && !is_wp_error($page_id)) {
                update_option('hostaway_wp_properties_page_id', $page_id);
            }
        } else {
            update_option('hostaway_wp_properties_page_id', $existing_properties_page[0]->ID);
        }
        
        // Search page
        $existing_search_page = get_posts([
            'name' => 'search',
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);
        
        if (empty($existing_search_page)) {
            $page_id = wp_insert_post([
                'post_title' => 'Search Properties',
                'post_content' => '[hostaway_search]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'search',
            ]);
            
            if ($page_id && !is_wp_error($page_id)) {
                update_option('hostaway_wp_search_page_id', $page_id);
            }
        } else {
            update_option('hostaway_wp_search_page_id', $existing_search_page[0]->ID);
        }
    }
    
    /**
     * Set default options
     */
    private static function setDefaultOptions() {
        $defaults = [
            'hostaway_wp_api_key' => '',
            'hostaway_wp_api_secret' => '',
            'hostaway_wp_google_maps_api_key' => '',
            'hostaway_wp_currency' => 'USD',
            'hostaway_wp_locale' => 'en_US',
            'hostaway_wp_timezone' => 'UTC',
            'hostaway_wp_sync_interval' => 'hourly',
            'hostaway_wp_properties_per_page' => 15,
            'hostaway_wp_enable_map' => true,
            'hostaway_wp_map_zoom' => 10,
            'hostaway_wp_enable_filters' => true,
            'hostaway_wp_filter_amenities' => [],
            'hostaway_wp_enable_instant_booking' => true,
            'hostaway_wp_booking_redirect' => 'checkout',
            'hostaway_wp_enable_reviews' => true,
            'hostaway_wp_enable_sharing' => true,
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }
}
