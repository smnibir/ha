<?php

namespace HostawaySync\Frontend;

use HostawaySync\Database\Database;

/**
 * Frontend functionality
 */
class Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init_shortcodes'));
        add_action('wp_ajax_hostaway_search_properties', array($this, 'ajax_search_properties'));
        add_action('wp_ajax_nopriv_hostaway_search_properties', array($this, 'ajax_search_properties'));
        add_action('wp_ajax_hostaway_get_property_details', array($this, 'ajax_get_property_details'));
        add_action('wp_ajax_nopriv_hostaway_get_property_details', array($this, 'ajax_get_property_details'));
        add_action('wp_ajax_hostaway_get_availability', array($this, 'ajax_get_availability'));
        add_action('wp_ajax_nopriv_hostaway_get_availability', array($this, 'ajax_get_availability'));
        add_action('wp_ajax_hostaway_get_location_suggestions', array($this, 'ajax_get_location_suggestions'));
        add_action('wp_ajax_nopriv_hostaway_get_location_suggestions', array($this, 'ajax_get_location_suggestions'));
    }
    
    /**
     * Initialize shortcodes
     */
    public function init_shortcodes() {
        add_shortcode('hostaway_search', array($this, 'search_shortcode'));
        add_shortcode('hostaway_properties', array($this, 'properties_shortcode'));
        add_shortcode('hostaway_property', array($this, 'single_property_shortcode'));
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'hostaway-frontend',
            HOSTAWAY_SYNC_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            HOSTAWAY_SYNC_VERSION,
            true
        );
        
        wp_enqueue_style(
            'hostaway-frontend',
            HOSTAWAY_SYNC_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            HOSTAWAY_SYNC_VERSION
        );
        
        wp_localize_script('hostaway-frontend', 'hostawayFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hostaway_frontend_nonce'),
            'googleMapsApiKey' => get_option('hostaway_sync_google_maps_api_key', ''),
            'strings' => array(
                'loading' => __('Loading...', 'hostaway-sync'),
                'noResults' => __('No properties found', 'hostaway-sync'),
                'error' => __('An error occurred', 'hostaway-sync'),
                'bookNow' => __('Book Now', 'hostaway-sync'),
                'instantBooking' => __('Instant Booking', 'hostaway-sync'),
                'perNight' => __('per night', 'hostaway-sync'),
                'total' => __('Total', 'hostaway-sync')
            )
        ));
    }
    
    /**
     * Search shortcode
     */
    public function search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'style' => 'default',
            'show_guests' => 'true',
            'show_dates' => 'true'
        ), $atts);
        
        ob_start();
        ?>
        <div class="hostaway-search-widget hostaway-style-<?php echo esc_attr($atts['style']); ?>">
            <form class="hostaway-search-form" method="get" action="<?php echo esc_url(home_url('/properties')); ?>">
                <div class="search-row">
                    <div class="search-field location-field">
                        <label for="location"><?php _e('Location', 'hostaway-sync'); ?></label>
                        <select id="location" name="location">
                            <option value=""><?php _e('Select a city', 'hostaway-sync'); ?></option>
                            <?php $this->render_city_options(); ?>
                        </select>
                    </div>
                    
                    <?php if ($atts['show_dates'] === 'true'): ?>
                    <div class="search-field dates-field">
                        <label for="checkin"><?php _e('Check-in', 'hostaway-sync'); ?></label>
                        <input type="date" id="checkin" name="checkin" />
                    </div>
                    
                    <div class="search-field dates-field">
                        <label for="checkout"><?php _e('Check-out', 'hostaway-sync'); ?></label>
                        <input type="date" id="checkout" name="checkout" />
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_guests'] === 'true'): ?>
                    <div class="search-field guests-field">
                        <label for="guests"><?php _e('Guests', 'hostaway-sync'); ?></label>
                        <select id="guests" name="guests">
                            <?php for ($i = 1; $i <= 20; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php printf(_n('%d Guest', '%d Guests', $i, 'hostaway-sync'), $i); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="search-field submit-field">
                        <button type="submit" class="search-submit">
                            <span class="search-icon">🔍</span>
                            <?php _e('Search', 'hostaway-sync'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Properties shortcode
     */
    public function properties_shortcode($atts) {
        $atts = shortcode_atts(array(
            'per_page' => get_option('hostaway_sync_properties_per_page', 15),
            'show_map' => 'true',
            'show_filters' => 'true'
        ), $atts);
        
        // Get search parameters
        $search_params = $this->get_search_params();
        
        // Get properties
        $properties = $this->get_properties($search_params, $atts['per_page']);
        
        ob_start();
        ?>
        <div class="hostaway-properties-page">
            <!-- Search Bar -->
            <div class="properties-search-bar">
                <?php echo $this->search_shortcode(array('style' => 'compact')); ?>
            </div>
            
            <!-- Controls -->
            <div class="properties-controls">
                <div class="controls-left">
                    <?php if ($atts['show_filters'] === 'true'): ?>
                    <button type="button" class="toggle-filters">
                        <span class="filter-icon">⚙️</span>
                        <?php _e('Show Filters', 'hostaway-sync'); ?>
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_map'] === 'true'): ?>
                    <button type="button" class="toggle-map">
                        <span class="map-icon">🗺️</span>
                        <span class="map-text"><?php _e('Show Map', 'hostaway-sync'); ?></span>
                    </button>
                    <?php endif; ?>
                    
                    <button type="button" class="reset-filters">
                        <?php _e('Reset', 'hostaway-sync'); ?>
                    </button>
                </div>
                
                <div class="controls-right">
                    <span class="properties-count">
                        <?php printf(_n('%d Property', '%d Properties', $properties['total'], 'hostaway-sync'), $properties['total']); ?>
                    </span>
                </div>
            </div>
            
            <!-- Filters Sidebar -->
            <?php if ($atts['show_filters'] === 'true'): ?>
            <div class="filters-sidebar">
                <div class="filters-content">
                    <h3><?php _e('Filters', 'hostaway-sync'); ?></h3>
                    
                    <!-- Amenities -->
                    <div class="filter-group">
                        <h4><?php _e('Amenities', 'hostaway-sync'); ?></h4>
                        <div class="amenities-list">
                            <?php $this->render_amenities_filter(); ?>
                        </div>
                    </div>
                    
                    <!-- Property Type -->
                    <div class="filter-group">
                        <h4><?php _e('Property Type', 'hostaway-sync'); ?></h4>
                        <select name="property_type" class="filter-select">
                            <option value=""><?php _e('All Types', 'hostaway-sync'); ?></option>
                            <?php $this->render_property_types(); ?>
                        </select>
                    </div>
                    
                    <!-- Price Range -->
                    <div class="filter-group">
                        <h4><?php _e('Price Range', 'hostaway-sync'); ?></h4>
                        <div class="price-range">
                            <input type="number" name="min_price" placeholder="<?php _e('Min Price', 'hostaway-sync'); ?>" />
                            <span>-</span>
                            <input type="number" name="max_price" placeholder="<?php _e('Max Price', 'hostaway-sync'); ?>" />
                        </div>
                    </div>
                    
                    <!-- Rooms -->
                    <div class="filter-group">
                        <h4><?php _e('Rooms', 'hostaway-sync'); ?></h4>
                        <select name="rooms" class="filter-select">
                            <option value=""><?php _e('Any', 'hostaway-sync'); ?></option>
                            <option value="1">1+</option>
                            <option value="2">2+</option>
                            <option value="3">3+</option>
                            <option value="4">4+</option>
                            <option value="5">5+</option>
                        </select>
                    </div>
                    
                    <!-- Bathrooms -->
                    <div class="filter-group">
                        <h4><?php _e('Bathrooms', 'hostaway-sync'); ?></h4>
                        <select name="bathrooms" class="filter-select">
                            <option value=""><?php _e('Any', 'hostaway-sync'); ?></option>
                            <option value="1">1+</option>
                            <option value="2">2+</option>
                            <option value="3">3+</option>
                            <option value="4">4+</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" class="apply-filters"><?php _e('Apply', 'hostaway-sync'); ?></button>
                        <button type="button" class="clear-filters"><?php _e('Clear', 'hostaway-sync'); ?></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Main Content -->
            <div class="properties-main <?php echo $atts['show_map'] === 'true' ? 'with-map' : 'without-map'; ?>">
                <!-- Properties Grid -->
                <div class="properties-grid">
                    <?php if (empty($properties['data'])): ?>
                        <div class="no-results">
                            <h3><?php _e('No properties found', 'hostaway-sync'); ?></h3>
                            <p><?php _e('Try adjusting your search criteria or filters.', 'hostaway-sync'); ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($properties['data'] as $property): ?>
                            <div class="property-card" data-property-id="<?php echo $property->id; ?>">
                                <div class="property-images">
                                    <?php $this->render_property_images($property); ?>
                                    <div class="property-price">
                                        <?php $this->render_property_price($property, $search_params); ?>
                                    </div>
                                </div>
                                
                                <div class="property-info">
                                    <h3 class="property-name"><?php echo esc_html($property->name); ?></h3>
                                    <div class="property-type">
                                        <?php echo esc_html($property->property_type); ?>
                                    </div>
                                    
                                    <div class="property-details">
                                        <span class="location">📍 <?php echo esc_html($property->city . ', ' . $property->country); ?></span>
                                        <span class="rooms">🛏️ <?php echo $property->room_count; ?> <?php _e('rooms', 'hostaway-sync'); ?></span>
                                        <span class="bathrooms">🚿 <?php echo $property->bathroom_count; ?> <?php _e('baths', 'hostaway-sync'); ?></span>
                                        <span class="guests">👥 <?php echo $property->guest_capacity; ?> <?php _e('guests', 'hostaway-sync'); ?></span>
                                    </div>
                                    
                                    <a href="<?php echo $this->get_property_url($property->id, $search_params); ?>" 
                                       class="property-link"><?php _e('View Details', 'hostaway-sync'); ?></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($properties['total_pages'] > 1): ?>
                <div class="properties-pagination">
                    <?php $this->render_pagination($properties['current_page'], $properties['total_pages']); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Map -->
            <?php if ($atts['show_map'] === 'true'): ?>
            <div class="properties-map">
                <div id="hostaway-map"></div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Single property shortcode
     */
    public function single_property_shortcode($atts) {
        $atts = shortcode_atts(array(
            'property_id' => 0
        ), $atts);
        
        $property_id = $atts['property_id'] ?: get_query_var('property_id', 0);
        
        if (!$property_id) {
            return '<p>' . __('Property not found.', 'hostaway-sync') . '</p>';
        }
        
        $property = $this->get_property_details($property_id);
        
        if (!$property) {
            return '<p>' . __('Property not found.', 'hostaway-sync') . '</p>';
        }
        
        $search_params = $this->get_search_params();
        
        ob_start();
        ?>
        <div class="hostaway-single-property">
            <!-- Property Header -->
            <div class="property-header">
                <div class="property-gallery">
                    <?php $this->render_property_gallery($property); ?>
                </div>
                
                <div class="property-booking-box">
                    <?php $this->render_booking_box($property, $search_params); ?>
                </div>
            </div>
            
            <!-- Property Content -->
            <div class="property-content">
                <div class="property-main">
                    <!-- Property Info -->
                    <div class="property-info-section">
                        <h1><?php echo esc_html($property->name); ?></h1>
                        <div class="property-meta">
                            <span class="location">📍 <?php echo esc_html($property->city . ', ' . $property->country); ?></span>
                            <span class="type">🏠 <?php echo esc_html($property->property_type); ?></span>
                            <span class="rooms">🛏️ <?php echo $property->room_count; ?> <?php _e('rooms', 'hostaway-sync'); ?></span>
                            <span class="bathrooms">🚿 <?php echo $property->bathroom_count; ?> <?php _e('baths', 'hostaway-sync'); ?></span>
                            <span class="guests">👥 <?php echo $property->guest_capacity; ?> <?php _e('guests', 'hostaway-sync'); ?></span>
                        </div>
                        
                        <?php if (!empty($property->description)): ?>
                        <div class="property-description">
                            <?php echo wp_kses_post($property->description); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Property Tabs -->
                    <div class="property-tabs">
                        <div class="tab-nav">
                            <button class="tab-button active" data-tab="amenities"><?php _e('Amenities', 'hostaway-sync'); ?></button>
                            <button class="tab-button" data-tab="availability"><?php _e('Availability', 'hostaway-sync'); ?></button>
                            <button class="tab-button" data-tab="reviews"><?php _e('Reviews', 'hostaway-sync'); ?></button>
                            <button class="tab-button" data-tab="map"><?php _e('Map', 'hostaway-sync'); ?></button>
                        </div>
                        
                        <div class="tab-content">
                            <div class="tab-panel active" id="amenities">
                                <?php $this->render_property_amenities($property); ?>
                            </div>
                            
                            <div class="tab-panel" id="availability">
                                <?php $this->render_availability_calendar($property->id); ?>
                            </div>
                            
                            <div class="tab-panel" id="reviews">
                                <p><?php _e('Reviews coming soon...', 'hostaway-sync'); ?></p>
                            </div>
                            
                            <div class="tab-panel" id="map">
                                <div id="property-map"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get search parameters from URL
     */
    private function get_search_params() {
        return array(
            'location' => sanitize_text_field($_GET['location'] ?? ''),
            'checkin' => sanitize_text_field($_GET['checkin'] ?? ''),
            'checkout' => sanitize_text_field($_GET['checkout'] ?? ''),
            'guests' => intval($_GET['guests'] ?? 1),
            'adults' => intval($_GET['adults'] ?? 1),
            'children' => intval($_GET['children'] ?? 0),
            'infants' => intval($_GET['infants'] ?? 0),
            'amenities' => array_filter((array)($_GET['amenities'] ?? array())),
            'property_type' => sanitize_text_field($_GET['property_type'] ?? ''),
            'min_price' => floatval($_GET['min_price'] ?? 0),
            'max_price' => floatval($_GET['max_price'] ?? 0),
            'rooms' => intval($_GET['rooms'] ?? 0),
            'bathrooms' => intval($_GET['bathrooms'] ?? 0)
        );
    }
    
    /**
     * Get properties with filters
     */
    private function get_properties($params, $per_page = 15) {
        global $wpdb;
        
        $properties_table = Database::get_properties_table();
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;
        
        $where_conditions = array("status = 'active'");
        $where_values = array();
        
        // Location filter
        if (!empty($params['location'])) {
            $where_conditions[] = "(city LIKE %s OR country LIKE %s OR location LIKE %s)";
            $where_values[] = "%{$params['location']}%";
            $where_values[] = "%{$params['location']}%";
            $where_values[] = "%{$params['location']}%";
        }
        
        // Property type filter
        if (!empty($params['property_type'])) {
            $where_conditions[] = "property_type = %s";
            $where_values[] = $params['property_type'];
        }
        
        // Price range filter
        if (!empty($params['min_price'])) {
            $where_conditions[] = "base_price >= %f";
            $where_values[] = $params['min_price'];
        }
        
        if (!empty($params['max_price'])) {
            $where_conditions[] = "base_price <= %f";
            $where_values[] = $params['max_price'];
        }
        
        // Rooms filter
        if (!empty($params['rooms'])) {
            $where_conditions[] = "room_count >= %d";
            $where_values[] = $params['rooms'];
        }
        
        // Bathrooms filter
        if (!empty($params['bathrooms'])) {
            $where_conditions[] = "bathroom_count >= %d";
            $where_values[] = $params['bathrooms'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM $properties_table WHERE $where_clause";
        if (!empty($where_values)) {
            $total_query = $wpdb->prepare($total_query, $where_values);
        }
        $total = $wpdb->get_var($total_query);
        
        // Get properties
        $query = "SELECT * FROM $properties_table WHERE $where_clause ORDER BY last_updated DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        $properties = $wpdb->get_results($wpdb->prepare($query, $query_values));
        
        return array(
            'data' => $properties,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        );
    }
    
    /**
     * Get property details
     */
    private function get_property_details($property_id) {
        global $wpdb;
        
        $properties_table = Database::get_properties_table();
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $properties_table WHERE id = %d AND status = 'active'",
                $property_id
            )
        );
    }
    
    /**
     * Render property images
     */
    private function render_property_images($property) {
        $images = json_decode($property->images, true);
        
        if (empty($images)) {
            echo '<div class="property-image-placeholder">🏠</div>';
            return;
        }
        
        echo '<div class="property-image-slider">';
        foreach (array_slice($images, 0, 5) as $index => $image) {
            $active_class = $index === 0 ? 'active' : '';
            echo '<img src="' . esc_url($image['url'] ?? $image) . '" alt="' . esc_attr($property->name) . '" class="' . $active_class . '" loading="lazy" />';
        }
        echo '</div>';
    }
    
    /**
     * Render property price
     */
    private function render_property_price($property, $search_params) {
        $price = $property->base_price;
        $currency = $property->currency;
        
        if (!empty($search_params['checkin']) && !empty($search_params['checkout'])) {
            $nights = $this->calculate_nights($search_params['checkin'], $search_params['checkout']);
            $total = $price * $nights;
            echo '<span class="price-total">' . $currency . ' ' . number_format($total, 2) . '</span>';
            echo '<span class="price-period">' . sprintf(_n('for %d night', 'for %d nights', $nights, 'hostaway-sync'), $nights) . '</span>';
        } else {
            echo '<span class="price-per-night">' . $currency . ' ' . number_format($price, 2) . '</span>';
            echo '<span class="price-period">' . __('per night', 'hostaway-sync') . '</span>';
        }
    }
    
    /**
     * Calculate nights between dates
     */
    private function calculate_nights($checkin, $checkout) {
        $checkin_date = new \DateTime($checkin);
        $checkout_date = new \DateTime($checkout);
        $diff = $checkin_date->diff($checkout_date);
        return $diff->days;
    }
    
    /**
     * Get property URL
     */
    private function get_property_url($property_id, $search_params) {
        $base_url = home_url('/property/' . $property_id);
        
        $params = array_filter($search_params);
        if (!empty($params)) {
            $base_url .= '?' . http_build_query($params);
        }
        
        return $base_url;
    }
    
    /**
     * Render amenities filter
     */
    private function render_amenities_filter() {
        $selected_amenities = get_option('hostaway_sync_selected_amenities', array());
        
        if (empty($selected_amenities)) {
            echo '<p>' . __('No amenities configured. Please configure amenities in the admin settings.', 'hostaway-sync') . '</p>';
            return;
        }
        
        foreach ($selected_amenities as $amenity_id => $amenity_name) {
            echo '<label class="amenity-checkbox">';
            echo '<input type="checkbox" name="amenities[]" value="' . esc_attr($amenity_id) . '" />';
            echo '<span>' . esc_html($amenity_name) . '</span>';
            echo '</label>';
        }
    }
    
    /**
     * Render city options
     */
    private function render_city_options() {
        global $wpdb;
        
        $properties_table = Database::get_properties_table();
        
        $cities = $wpdb->get_col(
            "SELECT DISTINCT city FROM $properties_table WHERE status = 'active' AND city != '' ORDER BY city"
        );
        
        foreach ($cities as $city) {
            echo '<option value="' . esc_attr($city) . '">' . esc_html($city) . '</option>';
        }
    }
    
    /**
     * Render property types
     */
    private function render_property_types() {
        global $wpdb;
        
        $properties_table = Database::get_properties_table();
        
        $types = $wpdb->get_col(
            "SELECT DISTINCT property_type FROM $properties_table WHERE status = 'active' AND property_type != '' ORDER BY property_type"
        );
        
        foreach ($types as $type) {
            echo '<option value="' . esc_attr($type) . '">' . esc_html($type) . '</option>';
        }
    }
    
    /**
     * Render pagination
     */
    private function render_pagination($current_page, $total_pages) {
        if ($total_pages <= 1) {
            return;
        }
        
        $base_url = remove_query_arg('paged');
        
        echo '<div class="pagination">';
        
        // Previous page
        if ($current_page > 1) {
            $prev_url = add_query_arg('paged', $current_page - 1, $base_url);
            echo '<a href="' . esc_url($prev_url) . '" class="page-link prev">&laquo; ' . __('Previous', 'hostaway-sync') . '</a>';
        }
        
        // Page numbers
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            $url = add_query_arg('paged', $i, $base_url);
            $active_class = $i === $current_page ? 'active' : '';
            echo '<a href="' . esc_url($url) . '" class="page-link ' . $active_class . '">' . $i . '</a>';
        }
        
        // Next page
        if ($current_page < $total_pages) {
            $next_url = add_query_arg('paged', $current_page + 1, $base_url);
            echo '<a href="' . esc_url($next_url) . '" class="page-link next">' . __('Next', 'hostaway-sync') . ' &raquo;</a>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render property gallery
     */
    private function render_property_gallery($property) {
        $images = json_decode($property->images, true);
        
        if (empty($images)) {
            echo '<div class="property-gallery-placeholder">🏠</div>';
            return;
        }
        
        echo '<div class="gallery-main">';
        echo '<img src="' . esc_url($images[0]['url'] ?? $images[0]) . '" alt="' . esc_attr($property->name) . '" class="main-image" />';
        echo '</div>';
        
        if (count($images) > 1) {
            echo '<div class="gallery-thumbnails">';
            foreach (array_slice($images, 1, 4) as $image) {
                echo '<img src="' . esc_url($image['url'] ?? $image) . '" alt="' . esc_attr($property->name) . '" class="thumbnail" />';
            }
            echo '</div>';
        }
    }
    
    /**
     * Render booking box
     */
    private function render_booking_box($property, $search_params) {
        ?>
        <div class="booking-box">
            <div class="booking-price">
                <span class="price"><?php echo $property->currency . ' ' . number_format($property->base_price, 2); ?></span>
                <span class="period"><?php _e('per night', 'hostaway-sync'); ?></span>
            </div>
            
            <form class="booking-form" method="post" action="">
                <div class="booking-fields">
                    <div class="field-group">
                        <label for="booking_checkin"><?php _e('Check-in', 'hostaway-sync'); ?></label>
                        <input type="date" id="booking_checkin" name="checkin" 
                               value="<?php echo esc_attr($search_params['checkin']); ?>" required />
                    </div>
                    
                    <div class="field-group">
                        <label for="booking_checkout"><?php _e('Check-out', 'hostaway-sync'); ?></label>
                        <input type="date" id="booking_checkout" name="checkout" 
                               value="<?php echo esc_attr($search_params['checkout']); ?>" required />
                    </div>
                    
                    <div class="field-group">
                        <label for="booking_guests"><?php _e('Guests', 'hostaway-sync'); ?></label>
                        <select id="booking_guests" name="guests" required>
                            <?php for ($i = 1; $i <= $property->guest_capacity; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php selected($search_params['guests'], $i); ?>>
                                    <?php printf(_n('%d Guest', '%d Guests', $i, 'hostaway-sync'), $i); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="booking-total">
                    <div class="total-line">
                        <span><?php _e('Total', 'hostaway-sync'); ?></span>
                        <span class="total-amount"><?php echo $property->currency; ?> <span class="amount">0.00</span></span>
                    </div>
                </div>
                
                <button type="submit" class="booking-submit">
                    <?php _e('Instant Booking', 'hostaway-sync'); ?>
                </button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render property amenities
     */
    private function render_property_amenities($property) {
        $amenities = json_decode($property->amenities, true);
        
        if (empty($amenities)) {
            echo '<p>' . __('No amenities listed.', 'hostaway-sync') . '</p>';
            return;
        }
        
        echo '<div class="amenities-grid">';
        foreach ($amenities as $amenity) {
            echo '<div class="amenity-item">';
            echo '<span class="amenity-icon">✓</span>';
            echo '<span class="amenity-name">' . esc_html($amenity['name'] ?? $amenity) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    /**
     * Render availability calendar
     */
    private function render_availability_calendar($property_id) {
        echo '<div class="availability-calendar" data-property-id="' . $property_id . '">';
        echo '<div class="calendar-loading">' . __('Loading availability...', 'hostaway-sync') . '</div>';
        echo '</div>';
    }
    
    /**
     * AJAX search properties
     */
    public function ajax_search_properties() {
        check_ajax_referer('hostaway_frontend_nonce', 'nonce');
        
        $params = $this->get_search_params();
        $per_page = intval($_POST['per_page'] ?? 15);
        
        $properties = $this->get_properties($params, $per_page);
        
        wp_send_json_success($properties);
    }
    
    /**
     * AJAX get property details
     */
    public function ajax_get_property_details() {
        check_ajax_referer('hostaway_frontend_nonce', 'nonce');
        
        $property_id = intval($_POST['property_id']);
        $property = $this->get_property_details($property_id);
        
        if (!$property) {
            wp_send_json_error(__('Property not found', 'hostaway-sync'));
        }
        
        wp_send_json_success($property);
    }
    
    /**
     * AJAX get availability
     */
    public function ajax_get_availability() {
        check_ajax_referer('hostaway_frontend_nonce', 'nonce');
        
        $property_id = intval($_POST['property_id']);
        
        global $wpdb;
        $availability_table = Database::get_availability_table();
        
        $availability = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $availability_table WHERE property_id = %d AND date >= CURDATE() ORDER BY date LIMIT 365",
                $property_id
            )
        );
        
        wp_send_json_success($availability);
    }
    
    /**
     * AJAX get location suggestions
     */
    public function ajax_get_location_suggestions() {
        check_ajax_referer('hostaway_frontend_nonce', 'nonce');
        
        $query = sanitize_text_field($_POST['query']);
        
        global $wpdb;
        $properties_table = Database::get_properties_table();
        
        $suggestions = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT city FROM $properties_table WHERE city LIKE %s AND status = 'active' ORDER BY city LIMIT 10",
                "%$query%"
            )
        );
        
        wp_send_json_success($suggestions);
    }
}
