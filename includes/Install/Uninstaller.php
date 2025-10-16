<?php

namespace HostawayWP\Install;

/**
 * Plugin uninstall handler
 */
class Uninstaller {
    
    /**
     * Uninstall plugin
     */
    public static function uninstall() {
        // Only run if user has proper permissions
        if (!current_user_can('delete_plugins')) {
            return;
        }
        
        // Check if we should remove data
        $remove_data = get_option('hostaway_wp_remove_data_on_uninstall', false);
        
        if ($remove_data) {
            self::removeTables();
            self::removeOptions();
            self::removePosts();
            self::removeMedia();
        }
    }
    
    /**
     * Remove custom database tables
     */
    private static function removeTables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'hostaway_properties',
            $wpdb->prefix . 'hostaway_rates',
            $wpdb->prefix . 'hostaway_availability',
            $wpdb->prefix . 'hostaway_sync_log',
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Remove plugin options
     */
    private static function removeOptions() {
        $options = [
            'hostaway_wp_version',
            'hostaway_wp_db_version',
            'hostaway_wp_api_key',
            'hostaway_wp_api_secret',
            'hostaway_wp_google_maps_api_key',
            'hostaway_wp_currency',
            'hostaway_wp_locale',
            'hostaway_wp_timezone',
            'hostaway_wp_sync_interval',
            'hostaway_wp_properties_per_page',
            'hostaway_wp_enable_map',
            'hostaway_wp_map_zoom',
            'hostaway_wp_enable_filters',
            'hostaway_wp_filter_amenities',
            'hostaway_wp_enable_instant_booking',
            'hostaway_wp_booking_redirect',
            'hostaway_wp_enable_reviews',
            'hostaway_wp_enable_sharing',
            'hostaway_wp_properties_page_id',
            'hostaway_wp_search_page_id',
            'hostaway_wp_sync_log',
            'hostaway_wp_remove_data_on_uninstall',
        ];
        
        foreach ($options as $option) {
            delete_option($option);
        }
    }
    
    /**
     * Remove custom post types
     */
    private static function removePosts() {
        $posts = get_posts([
            'post_type' => 'hostaway_property',
            'numberposts' => -1,
            'post_status' => 'any',
        ]);
        
        foreach ($posts as $post) {
            wp_delete_post($post->ID, true);
        }
    }
    
    /**
     * Remove uploaded media
     */
    private static function removeMedia() {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'meta_query' => [
                [
                    'key' => 'hostaway_wp_uploaded',
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
            'numberposts' => -1,
            'post_status' => 'any',
        ]);
        
        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true);
        }
    }
}
