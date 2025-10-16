<?php
/**
 * Plugin Name: Hostaway Real-Time Sync
 * Plugin URI: https://yourwebsite.com/hostaway-sync
 * Description: Real-time synchronization of Hostaway property data with WordPress, featuring WooCommerce integration for bookings and payments.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hostaway-sync
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HOSTAWAY_SYNC_VERSION', '1.0.0');
define('HOSTAWAY_SYNC_PLUGIN_FILE', __FILE__);
define('HOSTAWAY_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HOSTAWAY_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HOSTAWAY_SYNC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'HostawaySync\\';
    $base_dir = HOSTAWAY_SYNC_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main plugin class
 */
class HostawayRealTimeSync {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    public $admin;
    public $api;
    public $sync;
    public $frontend;
    public $woocommerce;
    public $database;
    
    /**
     * Get single instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init'), 10);
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('hostaway-sync', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Check dependencies
        if (!$this->check_dependencies()) {
            return;
        }
        
        // Initialize components
        $this->init_components();
        
        // Initialize hooks
        $this->init_component_hooks();
    }
    
    /**
     * Check plugin dependencies
     */
    private function check_dependencies() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->database = new HostawaySync\Database\Database();
        $this->api = new HostawaySync\API\HostawayClient();
        $this->sync = new HostawaySync\Sync\Synchronizer();
        $this->admin = new HostawaySync\Admin\Admin();
        $this->frontend = new HostawaySync\Frontend\Frontend();
        $this->woocommerce = new HostawaySync\WooCommerce\WooCommerceIntegration();
    }
    
    /**
     * Initialize component hooks
     */
    private function init_component_hooks() {
        // Database hooks
        add_action('init', array($this->database, 'create_tables'));
        
        // Sync hooks
        add_action('hostaway_sync_cron', array($this->sync, 'sync_properties'));
        
        // Frontend hooks (AJAX handlers registered in Frontend class constructor)
        add_action('wp_enqueue_scripts', array($this->frontend, 'enqueue_scripts'));
        
        // WooCommerce hooks
        add_action('woocommerce_checkout_process', array($this->woocommerce, 'validate_booking'));
        add_action('woocommerce_checkout_order_processed', array($this->woocommerce, 'process_booking'));
        add_action('woocommerce_order_status_completed', array($this->woocommerce, 'create_hostaway_reservation'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->database = new HostawaySync\Database\Database();
        $this->database->create_tables();
        
        // Schedule cron job
        if (!wp_next_scheduled('hostaway_sync_cron')) {
            wp_schedule_event(time(), 'hostaway_10min', 'hostaway_sync_cron');
        }
        
        // Add custom cron interval
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron job
        wp_clear_scheduled_hook('hostaway_sync_cron');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Add custom cron interval
     */
    public function add_cron_interval($schedules) {
        $schedules['hostaway_10min'] = array(
            'interval' => 600, // 10 minutes
            'display' => __('Every 10 Minutes', 'hostaway-sync')
        );
        return $schedules;
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'hostaway_account_id' => '',
            'hostaway_api_key' => '',
            'google_maps_api_key' => '',
            'auto_sync_enabled' => true,
            'selected_amenities' => array(),
            'sync_frequency' => 'hostaway_10min',
            'cache_duration' => 600, // 10 minutes
            'properties_per_page' => 15
        );
        
        foreach ($defaults as $key => $value) {
            if (!get_option('hostaway_sync_' . $key)) {
                add_option('hostaway_sync_' . $key, $value);
            }
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Hostaway Real-Time Sync requires WooCommerce to be installed and active.', 'hostaway-sync'); ?></p>
        </div>
        <?php
    }
}

// Initialize the plugin
function hostaway_sync() {
    return HostawayRealTimeSync::instance();
}

// Start the plugin
hostaway_sync();
