<?php

namespace HostawaySync\Sync;

use HostawaySync\Database\Database;
use HostawaySync\API\HostawayClient;

/**
 * Data Synchronizer
 */
class Synchronizer {
    
    /**
     * Hostaway API client
     */
    private $api_client;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new HostawayClient();
    }
    
    /**
     * Sync properties from Hostaway
     */
    public function sync_properties() {
        $start_time = microtime(true);
        $sync_id = Database::log_sync('properties', 'started', 'Starting property synchronization');
        
        try {
            // Check if sync is enabled
            if (!get_option('hostaway_sync_auto_sync_enabled', true)) {
                Database::log_sync('properties', 'skipped', 'Auto sync is disabled');
                return;
            }
            
            // Get all properties from Hostaway
            $properties = $this->api_client->get_properties(1000);
            
            if (!isset($properties['result']) || !is_array($properties['result'])) {
                throw new \Exception('Invalid properties response from API');
            }
            
            $synced_count = 0;
            $updated_count = 0;
            $errors = array();
            
            foreach ($properties['result'] as $property_data) {
                try {
                    $result = $this->sync_single_property($property_data);
                    
                    if ($result['action'] === 'created') {
                        $synced_count++;
                    } elseif ($result['action'] === 'updated') {
                        $updated_count++;
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = "Property {$property_data['id']}: " . $e->getMessage();
                }
            }
            
            // Sync rates and availability for all properties
            $this->sync_rates_and_availability();
            
            $execution_time = microtime(true) - $start_time;
            $message = sprintf(
                'Sync completed. Created: %d, Updated: %d, Errors: %d',
                $synced_count,
                $updated_count,
                count($errors)
            );
            
            Database::log_sync('properties', 'completed', $message, array(
                'synced_count' => $synced_count,
                'updated_count' => $updated_count,
                'errors' => $errors
            ), $execution_time);
            
            // Update last sync time
            update_option('hostaway_sync_last_sync', current_time('mysql'));
            
        } catch (\Exception $e) {
            $execution_time = microtime(true) - $start_time;
            Database::log_sync('properties', 'error', $e->getMessage(), null, $execution_time);
        }
    }
    
    /**
     * Sync single property
     */
    private function sync_single_property($property_data) {
        global $wpdb;
        
        $table = Database::get_properties_table();
        
        // Check if property exists
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE hostaway_id = %d",
                $property_data['id']
            )
        );
        
        // Prepare property data
        $property = array(
            'hostaway_id' => $property_data['id'],
            'name' => $property_data['name'] ?? '',
            'description' => $property_data['description'] ?? '',
            'location' => $property_data['location'] ?? '',
            'latitude' => $property_data['latitude'] ?? null,
            'longitude' => $property_data['longitude'] ?? null,
            'address' => $property_data['address'] ?? '',
            'city' => $property_data['city'] ?? '',
            'state' => $property_data['state'] ?? '',
            'country' => $property_data['country'] ?? '',
            'postal_code' => $property_data['postalCode'] ?? '',
            'property_type' => $property_data['propertyType'] ?? '',
            'room_count' => $property_data['roomCount'] ?? 0,
            'bathroom_count' => $property_data['bathroomCount'] ?? 0,
            'guest_capacity' => $property_data['guestCount'] ?? 0,
            'base_price' => $property_data['basePrice'] ?? 0,
            'currency' => $property_data['currency'] ?? 'USD',
            'amenities' => wp_json_encode($property_data['amenities'] ?? array()),
            'images' => wp_json_encode($property_data['photos'] ?? array()),
            'status' => $property_data['status'] === 'active' ? 'active' : 'inactive'
        );
        
        if ($existing) {
            // Update existing property
            $wpdb->update(
                $table,
                $property,
                array('id' => $existing->id),
                array('%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            return array('action' => 'updated', 'property_id' => $existing->id);
        } else {
            // Create new property
            $wpdb->insert(
                $table,
                $property,
                array('%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s')
            );
            
            return array('action' => 'created', 'property_id' => $wpdb->insert_id);
        }
    }
    
    /**
     * Sync rates and availability
     */
    private function sync_rates_and_availability() {
        global $wpdb;
        
        $properties_table = Database::get_properties_table();
        $rates_table = Database::get_rates_table();
        $availability_table = Database::get_availability_table();
        
        // Get all active properties
        $properties = $wpdb->get_results(
            "SELECT id, hostaway_id FROM $properties_table WHERE status = 'active'"
        );
        
        foreach ($properties as $property) {
            try {
                $this->sync_property_rates($property->id, $property->hostaway_id);
                $this->sync_property_availability($property->id, $property->hostaway_id);
            } catch (\Exception $e) {
                Database::log_sync('rates_availability', 'error', "Property {$property->id}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Sync property rates
     */
    private function sync_property_rates($property_id, $hostaway_id) {
        global $wpdb;
        
        $rates_table = Database::get_rates_table();
        
        // Get rates for the next 365 days
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d', strtotime('+365 days'));
        
        $rates_data = $this->api_client->get_property_rates($hostaway_id, $date_from, $date_to);
        
        if (!isset($rates_data['result']) || !is_array($rates_data['result'])) {
            return;
        }
        
        // Clear existing rates for this property
        $wpdb->delete($rates_table, array('property_id' => $property_id));
        
        // Insert new rates
        foreach ($rates_data['result'] as $rate) {
            $wpdb->insert(
                $rates_table,
                array(
                    'property_id' => $property_id,
                    'hostaway_rate_id' => $rate['id'] ?? 0,
                    'date_from' => $rate['dateFrom'] ?? $date_from,
                    'date_to' => $rate['dateTo'] ?? $date_to,
                    'price' => $rate['price'] ?? 0,
                    'minimum_nights' => $rate['minimumNights'] ?? 1
                ),
                array('%d', '%d', '%s', '%s', '%f', '%d')
            );
        }
    }
    
    /**
     * Sync property availability
     */
    private function sync_property_availability($property_id, $hostaway_id) {
        global $wpdb;
        
        $availability_table = Database::get_availability_table();
        
        // Get availability for the next 365 days
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d', strtotime('+365 days'));
        
        $availability_data = $this->api_client->get_property_availability($hostaway_id, $date_from, $date_to);
        
        if (!isset($availability_data['result']) || !is_array($availability_data['result'])) {
            return;
        }
        
        // Clear existing availability for this property
        $wpdb->delete($availability_table, array('property_id' => $property_id));
        
        // Insert new availability
        foreach ($availability_data['result'] as $day) {
            $wpdb->insert(
                $availability_table,
                array(
                    'property_id' => $property_id,
                    'date' => $day['date'],
                    'available' => $day['available'] ? 1 : 0,
                    'minimum_nights' => $day['minimumNights'] ?? 1,
                    'checkin_allowed' => $day['checkinAllowed'] ? 1 : 0,
                    'checkout_allowed' => $day['checkoutAllowed'] ? 1 : 0
                ),
                array('%d', '%s', '%d', '%d', '%d', '%d')
            );
        }
    }
    
    /**
     * Manual sync handler
     */
    public function manual_sync() {
        check_ajax_referer('hostaway_manual_sync', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hostaway-sync'));
        }
        
        // Run sync
        $this->sync_properties();
        
        // Get recent logs
        $logs = Database::get_recent_logs(10);
        
        wp_send_json_success(array(
            'message' => __('Manual sync completed', 'hostaway-sync'),
            'logs' => $logs
        ));
    }
    
    /**
     * Get sync status
     */
    public function get_sync_status() {
        $last_sync = get_option('hostaway_sync_last_sync');
        $logs = Database::get_recent_logs(5);
        
        return array(
            'last_sync' => $last_sync,
            'auto_sync_enabled' => get_option('hostaway_sync_auto_sync_enabled', true),
            'recent_logs' => $logs
        );
    }
    
    /**
     * Clear cache
     */
    public function clear_cache() {
        global $wpdb;
        
        // Clear transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hostaway_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_hostaway_%'");
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        Database::log_sync('cache', 'cleared', 'Cache cleared manually');
    }
    
    /**
     * Get property statistics
     */
    public function get_property_stats() {
        global $wpdb;
        
        $properties_table = Database::get_properties_table();
        $rates_table = Database::get_rates_table();
        $availability_table = Database::get_availability_table();
        
        $stats = array();
        
        // Total properties
        $stats['total_properties'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $properties_table"
        );
        
        // Active properties
        $stats['active_properties'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $properties_table WHERE status = 'active'"
        );
        
        // Properties with rates
        $stats['properties_with_rates'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT property_id) FROM $rates_table"
        );
        
        // Properties with availability
        $stats['properties_with_availability'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT property_id) FROM $availability_table WHERE date >= CURDATE()"
        );
        
        // Last sync time
        $stats['last_sync'] = get_option('hostaway_sync_last_sync');
        
        return $stats;
    }
}
