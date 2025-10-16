<?php
/**
 * Plugin Name: Hostaway WP Rentals
 * Plugin URI: https://hostaway.com
 * Description: WordPress plugin for Hostaway property rentals with WooCommerce integration
 * Version: 1.0.0
 * Author: Hostaway Team
 * License: GPL-2.0-or-later
 * Text Domain: hostaway-wp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HOSTAWAY_WP_VERSION', '1.0.0');
define('HOSTAWAY_WP_PLUGIN_FILE', __FILE__);
define('HOSTAWAY_WP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HOSTAWAY_WP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HOSTAWAY_WP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check for required dependencies
add_action('admin_init', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Hostaway WP Rentals requires WooCommerce to be installed and activated.', 'hostaway-wp');
            echo '</p></div>';
        });
        return;
    }
});

// Autoloader
if (file_exists(HOSTAWAY_WP_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once HOSTAWAY_WP_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Hostaway WP Plugin: Autoloader not found. Please reinstall the plugin.', 'hostaway-wp');
        echo '</p></div>';
    });
    return;
}

// Activation hook with error handling
register_activation_hook(__FILE__, function() {
    try {
        if (class_exists('HostawayWP\Install\Activator')) {
            HostawayWP\Install\Activator::activate();
        } else {
            error_log('Hostaway WP: Activator class not found during activation');
        }
    } catch (Exception $e) {
        error_log('Hostaway WP Plugin activation error: ' . $e->getMessage());
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Plugin activation failed: ' . $e->getMessage());
    }
});

// Deactivation hook with error handling
register_deactivation_hook(__FILE__, function() {
    try {
        if (class_exists('HostawayWP\Install\Deactivator')) {
            HostawayWP\Install\Deactivator::deactivate();
        }
    } catch (Exception $e) {
        error_log('Hostaway WP Plugin deactivation error: ' . $e->getMessage());
    }
});

// Uninstall hook with error handling
register_uninstall_hook(__FILE__, function() {
    try {
        if (class_exists('HostawayWP\Install\Uninstaller')) {
            HostawayWP\Install\Uninstaller::uninstall();
        }
    } catch (Exception $e) {
        error_log('Hostaway WP Plugin uninstall error: ' . $e->getMessage());
    }
});

// Initialize the plugin with error handling
add_action('plugins_loaded', function() {
    try {
        // Load text domain
        load_plugin_textdomain('hostaway-wp', false, dirname(HOSTAWAY_WP_PLUGIN_BASENAME) . '/languages');
        
        // Initialize the main plugin class
        if (class_exists('HostawayWP\Plugin')) {
            HostawayWP\Plugin::getInstance();
        } else {
            error_log('Hostaway WP: Main Plugin class not found');
        }
    } catch (Exception $e) {
        error_log('Hostaway WP Plugin initialization error: ' . $e->getMessage());
        
        // Show admin notice
        if (is_admin()) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('Hostaway WP Plugin initialization failed: ', 'hostaway-wp') . esc_html($e->getMessage());
                echo '</p></div>';
            });
        }
    }
});

// Add custom cron intervals
add_filter('cron_schedules', function($schedules) {
    $schedules['hostaway_10min'] = [
        'interval' => 10 * MINUTE_IN_SECONDS,
        'display' => __('Every 10 minutes', 'hostaway-wp'),
    ];
    
    $schedules['hostaway_15min'] = [
        'interval' => 15 * MINUTE_IN_SECONDS,
        'display' => __('Every 15 minutes', 'hostaway-wp'),
    ];
    
    $schedules['hostaway_6hourly'] = [
        'interval' => 6 * HOUR_IN_SECONDS,
        'display' => __('Every 6 hours', 'hostaway-wp'),
    ];
    
    return $schedules;
});

// Register custom post type early
add_action('init', function() {
    try {
        register_post_type('hostaway_property', [
            'labels' => [
                'name' => __('Properties', 'hostaway-wp'),
                'singular_name' => __('Property', 'hostaway-wp'),
                'menu_name' => __('Properties', 'hostaway-wp'),
                'add_new' => __('Add New', 'hostaway-wp'),
                'add_new_item' => __('Add New Property', 'hostaway-wp'),
                'edit_item' => __('Edit Property', 'hostaway-wp'),
                'new_item' => __('New Property', 'hostaway-wp'),
                'view_item' => __('View Property', 'hostaway-wp'),
                'search_items' => __('Search Properties', 'hostaway-wp'),
                'not_found' => __('No properties found', 'hostaway-wp'),
                'not_found_in_trash' => __('No properties found in trash', 'hostaway-wp'),
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
    } catch (Exception $e) {
        error_log('Hostaway WP: Failed to register post type: ' . $e->getMessage());
    }
}, 5);
