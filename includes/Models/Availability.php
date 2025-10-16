<?php

namespace HostawayWP\Models;

/**
 * Availability model
 */
class Availability {
    
    /**
     * Database table name
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'hostaway_availability';
    }
    
    /**
     * Get availability by property ID and date
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
     * Get availability by property ID and date range
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
     * Save availability
     */
    public function save($data) {
        global $wpdb;
        
        $availability_data = [
            'property_id' => $data['property_id'],
            'date' => $data['date'],
            'is_booked' => $data['is_booked'] ? 1 : 0,
            'is_available' => $data['is_available'] ? 1 : 0,
        ];
        
        // Check if availability exists
        $existing = $this->getByPropertyAndDate($data['property_id'], $data['date']);
        
        if ($existing) {
            // Update existing availability
            $wpdb->update(
                $this->table_name,
                $availability_data,
                ['id' => $existing['id']],
                ['%d', '%s', '%d', '%d'],
                ['%d']
            );
            
            return $existing['id'];
        } else {
            // Create new availability
            $wpdb->insert(
                $this->table_name,
                $availability_data,
                ['%d', '%s', '%d', '%d']
            );
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Save multiple availability records
     */
    public function saveMultiple($availability_data) {
        global $wpdb;
        
        $saved_count = 0;
        
        foreach ($availability_data as $data) {
            try {
                $this->save($data);
                $saved_count++;
            } catch (Exception $e) {
                error_log('Failed to save availability: ' . $e->getMessage());
            }
        }
        
        return $saved_count;
    }
    
    /**
     * Delete availability by property ID and date range
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
     * Check if date range is available
     */
    public function isDateRangeAvailable($property_id, $start_date, $end_date) {
        $availability = $this->getByPropertyAndDateRange($property_id, $start_date, $end_date);
        
        if (empty($availability)) {
            return false;
        }
        
        // Check if any date in range is booked or unavailable
        foreach ($availability as $day) {
            if ($day['is_booked'] || !$day['is_available']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get next available dates
     */
    public function getNextAvailableDates($property_id, $start_date, $days_ahead = 30) {
        global $wpdb;
        
        $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $days_ahead . ' days'));
        
        $query = $wpdb->prepare(
            "SELECT date FROM {$this->table_name} WHERE property_id = %d AND date >= %s AND date <= %s AND is_booked = 0 AND is_available = 1 ORDER BY date ASC",
            $property_id,
            $start_date,
            $end_date
        );
        
        return $wpdb->get_col($query);
    }
    
    /**
     * Get availability calendar data
     */
    public function getCalendarData($property_id, $year, $month) {
        global $wpdb;
        
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $query = $wpdb->prepare(
            "SELECT date, is_booked, is_available FROM {$this->table_name} WHERE property_id = %d AND date >= %s AND date <= %s ORDER BY date ASC",
            $property_id,
            $start_date,
            $end_date
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        $calendar_data = [];
        foreach ($results as $row) {
            $calendar_data[$row['date']] = [
                'is_booked' => (bool) $row['is_booked'],
                'is_available' => (bool) $row['is_available'],
            ];
        }
        
        return $calendar_data;
    }
    
    /**
     * Get all availability for property
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
     * Delete all availability for property
     */
    public function deleteByProperty($property_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            ['property_id' => $property_id],
            ['%d']
        );
    }
    
    /**
     * Mark date range as booked
     */
    public function markAsBooked($property_id, $start_date, $end_date) {
        global $wpdb;
        
        $start = new \DateTime($start_date);
        $end = new \DateTime($end_date);
        
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($start, $interval, $end);
        
        $updated_count = 0;
        
        foreach ($period as $date) {
            $date_str = $date->format('Y-m-d');
            
            $result = $wpdb->update(
                $this->table_name,
                ['is_booked' => 1],
                [
                    'property_id' => $property_id,
                    'date' => $date_str,
                ],
                ['%d'],
                ['%d', '%s']
            );
            
            if ($result !== false) {
                $updated_count++;
            }
        }
        
        return $updated_count;
    }
}
