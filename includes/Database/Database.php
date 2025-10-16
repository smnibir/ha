<?php

namespace HostawaySync\Database;

/**
 * Database management class
 */
class Database {
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Properties table
        $properties_table = $wpdb->prefix . 'hostaway_properties';
        $properties_sql = "CREATE TABLE $properties_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            hostaway_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            description longtext,
            location varchar(255),
            latitude decimal(10,8),
            longitude decimal(11,8),
            address longtext,
            city varchar(100),
            state varchar(100),
            country varchar(100),
            postal_code varchar(20),
            property_type varchar(100),
            room_count int(11) DEFAULT 0,
            bathroom_count int(11) DEFAULT 0,
            guest_capacity int(11) DEFAULT 0,
            base_price decimal(10,2) DEFAULT 0.00,
            currency varchar(3) DEFAULT 'USD',
            amenities longtext,
            images longtext,
            status varchar(20) DEFAULT 'active',
            last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY hostaway_id (hostaway_id),
            KEY location (city, state, country),
            KEY status (status),
            KEY property_type (property_type)
        ) $charset_collate;";
        
        // Rates table
        $rates_table = $wpdb->prefix . 'hostaway_rates';
        $rates_sql = "CREATE TABLE $rates_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            hostaway_rate_id bigint(20) unsigned NOT NULL,
            date_from date NOT NULL,
            date_to date NOT NULL,
            price decimal(10,2) NOT NULL,
            minimum_nights int(11) DEFAULT 1,
            last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY property_id (property_id),
            KEY date_range (date_from, date_to),
            UNIQUE KEY property_rate_date (property_id, hostaway_rate_id, date_from),
            FOREIGN KEY (property_id) REFERENCES $properties_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Availability table
        $availability_table = $wpdb->prefix . 'hostaway_availability';
        $availability_sql = "CREATE TABLE $availability_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            date date NOT NULL,
            available tinyint(1) DEFAULT 1,
            minimum_nights int(11) DEFAULT 1,
            checkin_allowed tinyint(1) DEFAULT 1,
            checkout_allowed tinyint(1) DEFAULT 1,
            last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY property_date (property_id, date),
            KEY date (date),
            KEY available (available),
            FOREIGN KEY (property_id) REFERENCES $properties_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Reservations table
        $reservations_table = $wpdb->prefix . 'hostaway_reservations';
        $reservations_sql = "CREATE TABLE $reservations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            woocommerce_order_id bigint(20) unsigned NOT NULL,
            hostaway_reservation_id varchar(100),
            property_id bigint(20) unsigned NOT NULL,
            checkin_date date NOT NULL,
            checkout_date date NOT NULL,
            guest_count int(11) DEFAULT 1,
            total_amount decimal(10,2) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            hostaway_response longtext,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY woocommerce_order_id (woocommerce_order_id),
            KEY hostaway_reservation_id (hostaway_reservation_id),
            KEY property_id (property_id),
            KEY status (status),
            FOREIGN KEY (property_id) REFERENCES $properties_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Sync log table
        $sync_log_table = $wpdb->prefix . 'hostaway_sync_log';
        $sync_log_sql = "CREATE TABLE $sync_log_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sync_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message longtext,
            data longtext,
            execution_time decimal(8,4) DEFAULT 0.0000,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sync_type (sync_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($properties_sql);
        dbDelta($rates_sql);
        dbDelta($availability_sql);
        dbDelta($reservations_sql);
        dbDelta($sync_log_sql);
        
        // Update database version
        update_option('hostaway_sync_db_version', '1.0.0');
    }
    
    /**
     * Get properties table name
     */
    public static function get_properties_table() {
        global $wpdb;
        return $wpdb->prefix . 'hostaway_properties';
    }
    
    /**
     * Get rates table name
     */
    public static function get_rates_table() {
        global $wpdb;
        return $wpdb->prefix . 'hostaway_rates';
    }
    
    /**
     * Get availability table name
     */
    public static function get_availability_table() {
        global $wpdb;
        return $wpdb->prefix . 'hostaway_availability';
    }
    
    /**
     * Get reservations table name
     */
    public static function get_reservations_table() {
        global $wpdb;
        return $wpdb->prefix . 'hostaway_reservations';
    }
    
    /**
     * Get sync log table name
     */
    public static function get_sync_log_table() {
        global $wpdb;
        return $wpdb->prefix . 'hostaway_sync_log';
    }
    
    /**
     * Log sync activity
     */
    public static function log_sync($sync_type, $status, $message = '', $data = null, $execution_time = 0) {
        global $wpdb;
        
        $table = self::get_sync_log_table();
        
        $wpdb->insert(
            $table,
            array(
                'sync_type' => $sync_type,
                'status' => $status,
                'message' => $message,
                'data' => $data ? wp_json_encode($data) : null,
                'execution_time' => $execution_time
            ),
            array('%s', '%s', '%s', '%s', '%f')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get recent sync logs
     */
    public static function get_recent_logs($limit = 50) {
        global $wpdb;
        
        $table = self::get_sync_log_table();
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Clean old sync logs
     */
    public static function clean_old_logs($days = 30) {
        global $wpdb;
        
        $table = self::get_sync_log_table();
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
}
