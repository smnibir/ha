<?php
/**
 * Uninstall script for Hostaway Real-Time Sync
 * 
 * This file is executed when the plugin is uninstalled (not deactivated).
 * It removes all plugin data from the database.
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove database tables
global $wpdb;

$tables = array(
    $wpdb->prefix . 'hostaway_properties',
    $wpdb->prefix . 'hostaway_rates',
    $wpdb->prefix . 'hostaway_availability',
    $wpdb->prefix . 'hostaway_reservations',
    $wpdb->prefix . 'hostaway_sync_log'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Remove plugin options
$options = array(
    'hostaway_sync_hostaway_api_key',
    'hostaway_sync_hostaway_api_secret',
    'hostaway_sync_google_maps_api_key',
    'hostaway_sync_auto_sync_enabled',
    'hostaway_sync_selected_amenities',
    'hostaway_sync_properties_per_page',
    'hostaway_sync_cache_duration',
    'hostaway_sync_sync_frequency',
    'hostaway_sync_last_sync',
    'hostaway_sync_db_version'
);

foreach ($options as $option) {
    delete_option($option);
}

// Remove transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hostaway_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_hostaway_%'");

// Clear any scheduled events
wp_clear_scheduled_hook('hostaway_sync_cron');

// Remove user meta (if any plugin-specific user data exists)
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'hostaway_%'");

// Remove post meta (if any plugin-specific post data exists)
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_hostaway_%'");
