<?php
/**
 * Minimal activation script for Hostaway WP Plugin
 * This can be run manually if the plugin fails to activate
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Try to load WordPress
    $wp_load_paths = [
        __DIR__ . '/../../../../wp-load.php',
        __DIR__ . '/../../../../../wp-load.php',
        __DIR__ . '/../../../../../../wp-load.php',
    ];
    
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
    
    if (!defined('ABSPATH')) {
        die('WordPress not found. Please run this script from within WordPress admin.');
    }
}

// Check if user has permission
if (!current_user_can('manage_options')) {
    die('You do not have permission to run this script.');
}

echo "<h1>Hostaway WP Plugin - Manual Activation</h1>";

// Step 1: Create database tables
echo "<h2>Step 1: Creating Database Tables</h2>";
try {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Properties table
    $properties_table = $wpdb->prefix . 'hostaway_properties';
    $properties_sql = "CREATE TABLE IF NOT EXISTS `$properties_table` (
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
    
    $result = $wpdb->query($properties_sql);
    if ($result !== false) {
        echo "<p style='color: green;'>✓ Properties table created</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create properties table: " . $wpdb->last_error . "</p>";
    }
    
    // Rates table
    $rates_table = $wpdb->prefix . 'hostaway_rates';
    $rates_sql = "CREATE TABLE IF NOT EXISTS `$rates_table` (
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
    
    $result = $wpdb->query($rates_sql);
    if ($result !== false) {
        echo "<p style='color: green;'>✓ Rates table created</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create rates table: " . $wpdb->last_error . "</p>";
    }
    
    // Availability table
    $availability_table = $wpdb->prefix . 'hostaway_availability';
    $availability_sql = "CREATE TABLE IF NOT EXISTS `$availability_table` (
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
    
    $result = $wpdb->query($availability_sql);
    if ($result !== false) {
        echo "<p style='color: green;'>✓ Availability table created</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create availability table: " . $wpdb->last_error . "</p>";
    }
    
    // Sync log table
    $sync_log_table = $wpdb->prefix . 'hostaway_sync_log';
    $sync_log_sql = "CREATE TABLE IF NOT EXISTS `$sync_log_table` (
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
    
    $result = $wpdb->query($sync_log_sql);
    if ($result !== false) {
        echo "<p style='color: green;'>✓ Sync log table created</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create sync log table: " . $wpdb->last_error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
}

// Step 2: Create pages
echo "<h2>Step 2: Creating Pages</h2>";
try {
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
            echo "<p style='color: green;'>✓ Properties page created (ID: $page_id)</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to create properties page</p>";
        }
    } else {
        update_option('hostaway_wp_properties_page_id', $existing_properties_page[0]->ID);
        echo "<p style='color: green;'>✓ Properties page already exists (ID: {$existing_properties_page[0]->ID})</p>";
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
            echo "<p style='color: green;'>✓ Search page created (ID: $page_id)</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to create search page</p>";
        }
    } else {
        update_option('hostaway_wp_search_page_id', $existing_search_page[0]->ID);
        echo "<p style='color: green;'>✓ Search page already exists (ID: {$existing_search_page[0]->ID})</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Page creation error: " . $e->getMessage() . "</p>";
}

// Step 3: Set default options
echo "<h2>Step 3: Setting Default Options</h2>";
try {
    $defaults = [
        'hostaway_wp_version' => '1.0.0',
        'hostaway_wp_db_version' => '1.0.0',
        'hostaway_wp_api_key' => '',
        'hostaway_wp_api_secret' => '',
        'hostaway_wp_google_maps_api_key' => '',
        'hostaway_wp_currency' => 'USD',
        'hostaway_wp_locale' => 'en_US',
        'hostaway_wp_timezone' => 'UTC',
        'hostaway_wp_sync_interval' => 'hostaway_10min',
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
            echo "<p style='color: green;'>✓ Set default option: $option</p>";
        } else {
            echo "<p style='color: blue;'>- Option already exists: $option</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Options error: " . $e->getMessage() . "</p>";
}

// Step 4: Register custom post type
echo "<h2>Step 4: Registering Custom Post Type</h2>";
try {
    register_post_type('hostaway_property', [
        'labels' => [
            'name' => 'Properties',
            'singular_name' => 'Property',
            'menu_name' => 'Properties',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Property',
            'edit_item' => 'Edit Property',
            'new_item' => 'New Property',
            'view_item' => 'View Property',
            'search_items' => 'Search Properties',
            'not_found' => 'No properties found',
            'not_found_in_trash' => 'No properties found in trash',
        ],
        'public' => true,
        'has_archive' => false,
        'show_in_menu' => false,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
        'rewrite' => [
            'slug' => 'property',
            'with_front' => false,
        ],
    ]);
    
    echo "<p style='color: green;'>✓ Custom post type registered</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Post type registration error: " . $e->getMessage() . "</p>";
}

// Step 5: Flush rewrite rules
echo "<h2>Step 5: Flushing Rewrite Rules</h2>";
try {
    flush_rewrite_rules();
    echo "<p style='color: green;'>✓ Rewrite rules flushed</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Rewrite rules error: " . $e->getMessage() . "</p>";
}

echo "<h2>Manual Activation Complete!</h2>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>Go to <strong>Hostaway → Settings</strong> in WordPress admin</li>";
echo "<li>Enter your Hostaway API credentials</li>";
echo "<li>Configure Google Maps API key</li>";
echo "<li>Test the API connection</li>";
echo "<li>Run manual sync to import properties</li>";
echo "</ol>";

echo "<p style='margin-top: 30px;'><strong>If you still get errors, check:</strong></p>";
echo "<ul>";
echo "<li>WordPress error logs</li>";
echo "<li>PHP error logs</li>";
echo "<li>Database permissions</li>";
echo "<li>Plugin file permissions</li>";
echo "</ul>";
?>
