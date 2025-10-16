<?php

namespace HostawayWP\Models;

/**
 * Rate model
 */
class Rate {
    
    /**
     * Database table name
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'hostaway_rates';
    }
    
    /**
     * Get rate by property ID and date
     */
    public function getByPropertyAndDate($property_id, $date) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE property_id = %d AND date = %s",
            $property_id,
            $date
        );
        
        return $wpdb->get_row($query, ARRAY_A);
    }
    
    /**
     * Get rates by property ID and date range
     */
    public function getByPropertyAndDateRange($property_id, $start_date, $end_date) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE property_id = %d AND date >= %s AND date <= %s ORDER BY date ASC",
            $property_id,
            $start_date,
            $end_date
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Save rate
     */
    public function save($data) {
        global $wpdb;
        
        $rate_data = [
            'property_id' => $data['property_id'],
            'date' => $data['date'],
            'price' => $data['price'],
            'min_nights' => $data['min_nights'] ?? 1,
            'max_guests' => $data['max_guests'] ?? null,
            'currency' => $data['currency'] ?? 'USD',
        ];
        
        // Check if rate exists
        $existing = $this->getByPropertyAndDate($data['property_id'], $data['date']);
        
        if ($existing) {
            // Update existing rate
            $wpdb->update(
                $this->table_name,
                $rate_data,
                ['id' => $existing['id']],
                ['%d', '%s', '%f', '%d', '%d', '%s'],
                ['%d']
            );
            
            return $existing['id'];
        } else {
            // Create new rate
            $wpdb->insert(
                $this->table_name,
                $rate_data,
                ['%d', '%s', '%f', '%d', '%d', '%s']
            );
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Save multiple rates
     */
    public function saveMultiple($rates_data) {
        global $wpdb;
        
        $saved_count = 0;
        
        foreach ($rates_data as $rate_data) {
            try {
                $this->save($rate_data);
                $saved_count++;
            } catch (Exception $e) {
                error_log('Failed to save rate: ' . $e->getMessage());
            }
        }
        
        return $saved_count;
    }
    
    /**
     * Delete rates by property ID and date range
     */
    public function deleteByPropertyAndDateRange($property_id, $start_date, $end_date) {
        global $wpdb;
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE property_id = %d AND date >= %s AND date <= %s",
                $property_id,
                $start_date,
                $end_date
            )
        );
    }
    
    /**
     * Calculate total price for date range
     */
    public function calculateTotalPrice($property_id, $start_date, $end_date, $guests = 1) {
        $rates = $this->getByPropertyAndDateRange($property_id, $start_date, $end_date);
        
        if (empty($rates)) {
            return null;
        }
        
        $total_price = 0;
        $start = new \DateTime($start_date);
        $end = new \DateTime($end_date);
        
        // Create date iterator
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($start, $interval, $end);
        
        foreach ($period as $date) {
            $date_str = $date->format('Y-m-d');
            
            // Find rate for this date
            $rate = null;
            foreach ($rates as $r) {
                if ($r['date'] === $date_str) {
                    $rate = $r;
                    break;
                }
            }
            
            if ($rate) {
                // Check guest limit
                if ($rate['max_guests'] && $guests > $rate['max_guests']) {
                    return null; // Exceeds guest limit
                }
                
                $total_price += floatval($rate['price']);
            } else {
                return null; // No rate available for this date
            }
        }
        
        return $total_price;
    }
    
    /**
     * Get minimum nights for date range
     */
    public function getMinNights($property_id, $start_date) {
        $rate = $this->getByPropertyAndDate($property_id, $start_date);
        
        return $rate ? intval($rate['min_nights']) : 1;
    }
    
    /**
     * Get price range for property
     */
    public function getPriceRange($property_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT MIN(price) as min_price, MAX(price) as max_price FROM {$this->table_name} WHERE property_id = %d",
            $property_id
        );
        
        return $wpdb->get_row($query, ARRAY_A);
    }
    
    /**
     * Get all rates for property
     */
    public function getAllByProperty($property_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE property_id = %d ORDER BY date ASC",
            $property_id
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Delete all rates for property
     */
    public function deleteByProperty($property_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            ['property_id' => $property_id],
            ['%d']
        );
    }
}
