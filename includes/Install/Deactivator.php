<?php

namespace HostawayWP\Install;

/**
 * Plugin deactivation handler
 */
class Deactivator {
    
    /**
     * Deactivate plugin
     */
    public static function deactivate() {
        self::clearCronJobs();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Clear scheduled cron jobs
     */
    private static function clearCronJobs() {
        wp_clear_scheduled_hook('hostaway_wp_sync_properties');
        wp_clear_scheduled_hook('hostaway_wp_sync_rates');
        wp_clear_scheduled_hook('hostaway_wp_sync_availability');
    }
}
