<?php
/**
 * Installation script for Hostaway WP Plugin
 * Run this file once after uploading the plugin to WordPress
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

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    die('WooCommerce is required but not active. Please install and activate WooCommerce first.');
}

echo "<h1>Hostaway WP Plugin Installation</h1>";

// Run activation
if (class_exists('HostawayWP\\Install\\Activator')) {
    try {
        HostawayWP\Install\Activator::activate();
        echo "<p style='color: green;'>✓ Plugin activated successfully!</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Activation failed: " . esc_html($e->getMessage()) . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Activator class not found. Please check plugin installation.</p>";
}

// Check database tables
global $wpdb;

$tables = [
    $wpdb->prefix . 'hostaway_properties',
    $wpdb->prefix . 'hostaway_rates',
    $wpdb->prefix . 'hostaway_availability',
    $wpdb->prefix . 'hostaway_sync_log',
];

echo "<h2>Database Tables</h2>";
foreach ($tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    if ($exists) {
        echo "<p style='color: green;'>✓ Table $table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Table $table missing</p>";
    }
}

// Check pages
echo "<h2>Created Pages</h2>";
$properties_page_id = get_option('hostaway_wp_properties_page_id');
if ($properties_page_id) {
    $properties_page = get_post($properties_page_id);
    if ($properties_page) {
        echo "<p style='color: green;'>✓ Properties page created: <a href='" . get_permalink($properties_page_id) . "'>" . esc_html($properties_page->post_title) . "</a></p>";
    } else {
        echo "<p style='color: red;'>✗ Properties page not found</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Properties page not created</p>";
}

$search_page_id = get_option('hostaway_wp_search_page_id');
if ($search_page_id) {
    $search_page = get_post($search_page_id);
    if ($search_page) {
        echo "<p style='color: green;'>✓ Search page created: <a href='" . get_permalink($search_page_id) . "'>" . esc_html($search_page->post_title) . "</a></p>";
    } else {
        echo "<p style='color: red;'>✗ Search page not found</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Search page not created</p>";
}

// Check cron jobs
echo "<h2>Cron Jobs</h2>";
$cron_jobs = [
    'hostaway_wp_sync_properties',
    'hostaway_wp_sync_rates',
    'hostaway_wp_sync_availability',
];

foreach ($cron_jobs as $job) {
    $scheduled = wp_next_scheduled($job);
    if ($scheduled) {
        echo "<p style='color: green;'>✓ Cron job $job scheduled for " . date('Y-m-d H:i:s', $scheduled) . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Cron job $job not scheduled</p>";
    }
}

// Check permissions
echo "<h2>File Permissions</h2>";
$plugin_dir = plugin_dir_path(__FILE__);
if (is_writable($plugin_dir)) {
    echo "<p style='color: green;'>✓ Plugin directory is writable</p>";
} else {
    echo "<p style='color: red;'>✗ Plugin directory is not writable</p>";
}

$uploads_dir = wp_upload_dir();
if (is_writable($uploads_dir['path'])) {
    echo "<p style='color: green;'>✓ Uploads directory is writable</p>";
} else {
    echo "<p style='color: red;'>✗ Uploads directory is not writable</p>";
}

echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>Go to <strong>Hostaway → Settings</strong> in WordPress admin</li>";
echo "<li>Enter your Hostaway API credentials</li>";
echo "<li>Configure Google Maps API key</li>";
echo "<li>Test the API connection</li>";
echo "<li>Run manual sync to import properties</li>";
echo "<li>Configure WooCommerce Stripe gateway</li>";
echo "<li>Test the booking flow</li>";
echo "</ol>";

echo "<h2>Shortcodes</h2>";
echo "<p>Use these shortcodes on your pages:</p>";
echo "<ul>";
echo "<li><code>[hostaway_search]</code> - Property search form</li>";
echo "<li><code>[hostaway_properties]</code> - Properties listing page</li>";
echo "<li><code>[hostaway_property id=\"123\"]</code> - Single property display</li>";
echo "</ul>";

echo "<p style='margin-top: 30px;'><strong>Installation complete!</strong> You can now configure the plugin in WordPress admin.</p>";
?>
