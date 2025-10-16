<?php

namespace HostawayWP;

/**
 * Main plugin class
 */
class Plugin {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    private $components = [];
    
    /**
     * Get plugin instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Initialize components
        $this->initComponents();
        
        // Initialize hooks
        $this->initHooks();
    }
    
    /**
     * Initialize plugin components
     */
    private function initComponents() {
        // Admin components
        if (is_admin()) {
            $this->components['admin'] = new Admin\Admin();
            $this->components['settings'] = new Admin\Settings();
        }
        
        // API client
        $this->components['api'] = new API\HostawayClient();
        
        // Sync system
        $this->components['sync'] = new Sync\Synchronizer();
        
        // Frontend components
        $this->components['frontend'] = new Frontend\Frontend();
        $this->components['shortcodes'] = new Frontend\Shortcodes();
        $this->components['assets'] = new Frontend\Assets();
        
        // WooCommerce integration
        if (class_exists('WooCommerce')) {
            $this->components['woocommerce'] = new Checkout\WooBridge();
        }
        
        // REST API endpoints
        $this->components['rest'] = new Rest\Endpoints();
    }
    
    /**
     * Initialize hooks
     */
    private function initHooks() {
        // Custom post type
        add_action('init', [$this, 'registerPostTypes']);
        
        // Cron jobs
        add_action('hostaway_wp_sync_properties', [$this->components['sync'], 'syncProperties']);
        add_action('hostaway_wp_sync_rates', [$this->components['sync'], 'syncRates']);
        add_action('hostaway_wp_sync_availability', [$this->components['sync'], 'syncAvailability']);
        
        // Admin menu
        add_action('admin_menu', [$this, 'addAdminMenu']);
        
        // AJAX handlers
        add_action('wp_ajax_hostaway_test_api', [$this, 'ajaxTestApi']);
        add_action('wp_ajax_hostaway_sync_now', [$this, 'ajaxSyncNow']);
        add_action('wp_ajax_hostaway_get_properties', [$this, 'ajaxGetProperties']);
        add_action('wp_ajax_nopriv_hostaway_get_properties', [$this, 'ajaxGetProperties']);
    }
    
    /**
     * Register custom post types
     */
    public function registerPostTypes() {
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
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        add_menu_page(
            __('Hostaway Settings', 'hostaway-wp'),
            __('Hostaway', 'hostaway-wp'),
            'manage_options',
            'hostaway-settings',
            [$this->components['settings'], 'renderPage'],
            'dashicons-admin-home',
            30
        );
        
        add_submenu_page(
            'hostaway-settings',
            __('Properties', 'hostaway-wp'),
            __('Properties', 'hostaway-wp'),
            'manage_options',
            'edit.php?post_type=hostaway_property'
        );
        
        add_submenu_page(
            'hostaway-settings',
            __('Sync Log', 'hostaway-wp'),
            __('Sync Log', 'hostaway-wp'),
            'manage_options',
            'hostaway-sync-log',
            [$this, 'renderSyncLogPage']
        );
    }
    
    /**
     * Render sync log page
     */
    public function renderSyncLogPage() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Sync Log', 'hostaway-wp') . '</h1>';
        
        $logs = get_option('hostaway_wp_sync_log', []);
        if (empty($logs)) {
            echo '<p>' . esc_html__('No sync logs found.', 'hostaway-wp') . '</p>';
        } else {
            echo '<table class="widefat fixed">';
            echo '<thead><tr><th>' . esc_html__('Date', 'hostaway-wp') . '</th><th>' . esc_html__('Action', 'hostaway-wp') . '</th><th>' . esc_html__('Status', 'hostaway-wp') . '</th><th>' . esc_html__('Message', 'hostaway-wp') . '</th></tr></thead>';
            echo '<tbody>';
            foreach (array_reverse($logs) as $log) {
                echo '<tr>';
                echo '<td>' . esc_html($log['date']) . '</td>';
                echo '<td>' . esc_html($log['action']) . '</td>';
                echo '<td>' . esc_html($log['status']) . '</td>';
                echo '<td>' . esc_html($log['message']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }
    
    /**
     * AJAX handler for testing API connection
     */
    public function ajaxTestApi() {
        check_ajax_referer('hostaway_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $account_id = sanitize_text_field($_POST['account_id'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($account_id) || empty($api_key)) {
            wp_send_json([
                'success' => false,
                'message' => __('Account ID and API Key are required', 'hostaway-wp')
            ]);
        }
        
        $client = new API\HostawayClient($account_id, $api_key);
        $result = $client->testConnection();
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for manual sync
     */
    public function ajaxSyncNow() {
        check_ajax_referer('hostaway_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $result = $this->components['sync']->syncAll();
            
            wp_send_json([
                'success' => true,
                'message' => __('Sync completed successfully', 'hostaway-wp'),
                'data' => $result
            ]);
        } catch (Exception $e) {
            wp_send_json([
                'success' => false,
                'message' => sprintf(__('Sync failed: %s', 'hostaway-wp'), $e->getMessage())
            ]);
        }
    }
    
    /**
     * AJAX handler for getting properties (for frontend filtering)
     */
    public function ajaxGetProperties() {
        check_ajax_referer('hostaway_frontend_nonce', 'nonce');
        
        $params = [
            'search' => sanitize_text_field($_GET['search'] ?? ''),
            'location' => sanitize_text_field($_GET['location'] ?? ''),
            'checkin' => sanitize_text_field($_GET['checkin'] ?? ''),
            'checkout' => sanitize_text_field($_GET['checkout'] ?? ''),
            'guests' => intval($_GET['guests'] ?? 0),
            'amenities' => array_map('sanitize_text_field', $_GET['amenities'] ?? []),
            'rooms' => intval($_GET['rooms'] ?? 0),
            'bathrooms' => intval($_GET['bathrooms'] ?? 0),
            'price_min' => intval($_GET['price_min'] ?? 0),
            'price_max' => intval($_GET['price_max'] ?? 0),
            'page' => intval($_GET['page'] ?? 1),
            'per_page' => intval($_GET['per_page'] ?? 15),
        ];
        
        $properties = new Models\Property();
        $results = $properties->search($params);
        
        wp_send_json($results);
    }
    
    /**
     * Get component
     */
    public function getComponent($name) {
        return $this->components[$name] ?? null;
    }
}
