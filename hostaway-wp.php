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
add_action('admin_init', 'hostaway_wp_check_dependencies');

// Dependency check function
function hostaway_wp_check_dependencies() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'hostaway_wp_woocommerce_notice');
        return;
    }
}

// WooCommerce notice function
function hostaway_wp_woocommerce_notice() {
    echo '<div class="notice notice-error"><p>';
    echo esc_html__('Hostaway WP Rentals requires WooCommerce to be installed and activated.', 'hostaway-wp');
    echo '</p></div>';
}

// Autoloader
require_once HOSTAWAY_WP_PLUGIN_DIR . 'vendor/autoload.php';

// Activation hook
register_activation_hook(__FILE__, 'hostaway_wp_activate');

// Deactivation hook
register_deactivation_hook(__FILE__, 'hostaway_wp_deactivate');

// Uninstall hook
register_uninstall_hook(__FILE__, 'hostaway_wp_uninstall');

// Initialize the plugin
add_action('plugins_loaded', 'hostaway_wp_init');

// Activation function
function hostaway_wp_activate() {
    if (class_exists('HostawayWP\Install\Activator')) {
        HostawayWP\Install\Activator::activate();
    }
}

// Deactivation function
function hostaway_wp_deactivate() {
    if (class_exists('HostawayWP\Install\Deactivator')) {
        HostawayWP\Install\Deactivator::deactivate();
    }
}

// Uninstall function
function hostaway_wp_uninstall() {
    if (class_exists('HostawayWP\Install\Uninstaller')) {
        HostawayWP\Install\Uninstaller::uninstall();
    }
}

// Initialization function
function hostaway_wp_init() {
    // Load text domain
    load_plugin_textdomain('hostaway-wp', false, dirname(HOSTAWAY_WP_PLUGIN_BASENAME) . '/languages');
    
    // Initialize the main plugin class
    if (class_exists('HostawayWP\Plugin')) {
        HostawayWP\Plugin::getInstance();
    }
}

// Cron schedules function
function hostaway_wp_cron_schedules($schedules) {
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
}
