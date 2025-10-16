<?php

namespace HostawayWP\Sync;

use HostawayWP\API\HostawayClient;
use HostawayWP\Models\Property;
use HostawayWP\Models\Rate;
use HostawayWP\Models\Availability;

/**
 * Data synchronization system
 */
class Synchronizer {
    
    /**
     * API client
     */
    private $api_client;
    
    /**
     * Models
     */
    private $property_model;
    private $rate_model;
    private $availability_model;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new HostawayClient();
        $this->property_model = new Property();
        $this->rate_model = new Rate();
        $this->availability_model = new Availability();
        
        // Add custom cron intervals
        add_filter('cron_schedules', [$this, 'addCustomCronIntervals']);
    }
    
    /**
     * Add custom cron intervals
     */
    public function addCustomCronIntervals($schedules) {
        $schedules['hostaway_10min'] = [
            'interval' => 10 * MINUTE_IN_SECONDS,
            'display' => __('Every 10 minutes', 'hostaway-wp'),
        ];
        
        return $schedules;
    }
    
    /**
     * Sync all data
     */
    public function syncAll() {
        $this->logSync('sync_all', 'started', 'Starting full synchronization');
        
        try {
            // Sync properties first
            $properties_synced = $this->syncProperties();
            
            // Sync rates for all properties
            $rates_synced = $this->syncRates();
            
            // Sync availability for all properties
            $availability_synced = $this->syncAvailability();
            
            $this->logSync('sync_all', 'completed', sprintf(
                'Sync completed: %d properties, %d rates, %d availability records',
                $properties_synced,
                $rates_synced,
                $availability_synced
            ));
            
            return [
                'success' => true,
                'properties' => $properties_synced,
                'rates' => $rates_synced,
                'availability' => $availability_synced,
            ];
            
        } catch (Exception $e) {
            $this->logSync('sync_all', 'error', 'Sync failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Sync properties
     */
    public function syncProperties() {
        $this->logSync('properties', 'started', 'Starting properties sync');
        
        try {
            $synced_count = 0;
            $page = 1;
            $limit = 100;
            
            do {
                $response = $this->api_client->getProperties($page, $limit);
                
                if (!$response || !isset($response['data']) || !is_array($response['data'])) {
                    break;
                }
                
                foreach ($response['data'] as $property_data) {
                    try {
                        $this->syncSingleProperty($property_data);
                        $synced_count++;
                    } catch (Exception $e) {
                        $this->logSync('properties', 'error', 'Failed to sync property ' . $property_data['id'] . ': ' . $e->getMessage());
                    }
                }
                
                $page++;
                
                // Check if there are more pages
                if (count($response['data']) < $limit) {
                    break;
                }
                
            } while (true);
            
            $this->logSync('properties', 'completed', "Synced {$synced_count} properties");
            
            return $synced_count;
            
        } catch (Exception $e) {
            $this->logSync('properties', 'error', 'Properties sync failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Sync single property
     */
    private function syncSingleProperty($api_data) {
        // Get detailed property information
        $property_details = $this->api_client->getProperty($api_data['id']);
        
        if (!$property_details || !isset($property_details['data'])) {
            throw new Exception('Failed to get property details');
        }
        
        $property = $property_details['data'];
        
        // Get property images
        $images = $this->api_client->getPropertyImages($property['id']);
        $gallery_urls = [];
        $thumbnail_url = '';
        
        if ($images && isset($images['data'])) {
            foreach ($images['data'] as $image) {
                if ($image['is_primary']) {
                    $thumbnail_url = $image['url'];
                }
                $gallery_urls[] = $image['url'];
            }
        }
        
        // Get property amenities
        $amenities = $this->api_client->getPropertyAmenities($property['id']);
        $amenities_list = [];
        
        if ($amenities && isset($amenities['data'])) {
            foreach ($amenities['data'] as $amenity) {
                $amenities_list[] = $amenity['name'];
            }
        }
        
        // Prepare data for database
        $property_data = [
            'hostaway_id' => $property['id'],
            'title' => $property['title'],
            'slug' => $this->generateSlug($property['title'], $property['id']),
            'type' => $property['type'] ?? 'Property',
            'country' => $property['address']['country'] ?? '',
            'city' => $property['address']['city'] ?? '',
            'address' => $this->formatAddress($property['address']),
            'latitude' => $property['coordinates']['latitude'] ?? null,
            'longitude' => $property['coordinates']['longitude'] ?? null,
            'rooms' => $property['bedrooms'] ?? 0,
            'bathrooms' => $property['bathrooms'] ?? 0,
            'guests' => $property['max_guests'] ?? 0,
            'base_price' => $property['base_price'] ?? 0.00,
            'thumbnail_url' => $thumbnail_url,
            'gallery_json' => json_encode($gallery_urls),
            'amenities_json' => json_encode($amenities_list),
            'features_json' => json_encode($property['features'] ?? []),
            'description' => $property['description'] ?? '',
            'status' => $property['status'] === 'active' ? 'active' : 'inactive',
        ];
        
        // Save to database
        $property_id = $this->property_model->save($property_data);
        
        return $property_id;
    }
    
    /**
     * Sync rates
     */
    public function syncRates() {
        $this->logSync('rates', 'started', 'Starting rates sync');
        
        try {
            $synced_count = 0;
            
            // Get all properties
            $properties = $this->property_model->getAll(1000, 0);
            
            foreach ($properties as $property) {
                try {
                    // Get rates for next 365 days
                    $start_date = date('Y-m-d');
                    $end_date = date('Y-m-d', strtotime('+365 days'));
                    
                    $rates_response = $this->api_client->getPropertyRates(
                        $property['hostaway_id'],
                        $start_date,
                        $end_date
                    );
                    
                    if ($rates_response && isset($rates_response['data'])) {
                        $rates_data = [];
                        
                        foreach ($rates_response['data'] as $rate_data) {
                            $rates_data[] = [
                                'property_id' => $property['id'],
                                'date' => $rate_data['date'],
                                'price' => $rate_data['price'],
                                'min_nights' => $rate_data['min_nights'] ?? 1,
                                'max_guests' => $rate_data['max_guests'] ?? null,
                                'currency' => $rate_data['currency'] ?? 'USD',
                            ];
                        }
                        
                        // Delete existing rates for this date range
                        $this->rate_model->deleteByPropertyAndDateRange(
                            $property['id'],
                            $start_date,
                            $end_date
                        );
                        
                        // Save new rates
                        $saved = $this->rate_model->saveMultiple($rates_data);
                        $synced_count += $saved;
                    }
                    
                } catch (Exception $e) {
                    $this->logSync('rates', 'error', 'Failed to sync rates for property ' . $property['hostaway_id'] . ': ' . $e->getMessage());
                }
            }
            
            $this->logSync('rates', 'completed', "Synced {$synced_count} rate records");
            
            return $synced_count;
            
        } catch (Exception $e) {
            $this->logSync('rates', 'error', 'Rates sync failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Sync availability
     */
    public function syncAvailability() {
        $this->logSync('availability', 'started', 'Starting availability sync');
        
        try {
            $synced_count = 0;
            
            // Get all properties
            $properties = $this->property_model->getAll(1000, 0);
            
            foreach ($properties as $property) {
                try {
                    // Get availability for next 365 days
                    $start_date = date('Y-m-d');
                    $end_date = date('Y-m-d', strtotime('+365 days'));
                    
                    $availability_response = $this->api_client->getPropertyAvailability(
                        $property['hostaway_id'],
                        $start_date,
                        $end_date
                    );
                    
                    if ($availability_response && isset($availability_response['data'])) {
                        $availability_data = [];
                        
                        foreach ($availability_response['data'] as $avail_data) {
                            $availability_data[] = [
                                'property_id' => $property['id'],
                                'date' => $avail_data['date'],
                                'is_booked' => $avail_data['is_booked'] ?? false,
                                'is_available' => $avail_data['is_available'] ?? true,
                            ];
                        }
                        
                        // Delete existing availability for this date range
                        $this->availability_model->deleteByPropertyAndDateRange(
                            $property['id'],
                            $start_date,
                            $end_date
                        );
                        
                        // Save new availability
                        $saved = $this->availability_model->saveMultiple($availability_data);
                        $synced_count += $saved;
                    }
                    
                } catch (Exception $e) {
                    $this->logSync('availability', 'error', 'Failed to sync availability for property ' . $property['hostaway_id'] . ': ' . $e->getMessage());
                }
            }
            
            $this->logSync('availability', 'completed', "Synced {$synced_count} availability records");
            
            return $synced_count;
            
        } catch (Exception $e) {
            $this->logSync('availability', 'error', 'Availability sync failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate unique slug
     */
    private function generateSlug($title, $hostaway_id) {
        $slug = sanitize_title($title);
        
        // Add hostaway ID to ensure uniqueness
        $slug = $slug . '-' . $hostaway_id;
        
        return $slug;
    }
    
    /**
     * Format address
     */
    private function formatAddress($address) {
        if (!is_array($address)) {
            return '';
        }
        
        $parts = array_filter([
            $address['street'] ?? '',
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['country'] ?? '',
        ]);
        
        return implode(', ', $parts);
    }
    
    /**
     * Log sync activity
     */
    private function logSync($action, $status, $message) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'hostaway_sync_log';
        
        $wpdb->insert(
            $table_name,
            [
                'action' => $action,
                'status' => $status,
                'message' => $message,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s']
        );
        
        // Keep only last 1000 log entries
        $wpdb->query(
            "DELETE FROM {$table_name} WHERE id NOT IN (
                SELECT id FROM (
                    SELECT id FROM {$table_name} ORDER BY created_at DESC LIMIT 1000
                ) AS temp
            )"
        );
    }
    
    /**
     * Get sync status
     */
    public function getSyncStatus() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'hostaway_sync_log';
        
        // Get last sync for each action
        $last_syncs = $wpdb->get_results(
            "SELECT action, status, message, created_at 
             FROM {$table_name} 
             WHERE id IN (
                 SELECT MAX(id) FROM {$table_name} GROUP BY action
             ) 
             ORDER BY created_at DESC",
            ARRAY_A
        );
        
        return $last_syncs;
    }
    
    /**
     * Schedule sync jobs
     */
    public function scheduleSyncJobs() {
        // Clear existing schedules
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
     * Clear sync jobs
     */
    public function clearSyncJobs() {
        wp_clear_scheduled_hook('hostaway_wp_sync_properties');
        wp_clear_scheduled_hook('hostaway_wp_sync_rates');
        wp_clear_scheduled_hook('hostaway_wp_sync_availability');
    }
}
