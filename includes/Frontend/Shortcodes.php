<?php

namespace HostawayWP\Frontend;

/**
 * Frontend shortcodes
 */
class Shortcodes {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'registerShortcodes']);
    }
    
    /**
     * Register shortcodes
     */
    public function registerShortcodes() {
        add_shortcode('hostaway_search', [$this, 'renderSearchShortcode']);
        add_shortcode('hostaway_properties', [$this, 'renderPropertiesShortcode']);
        add_shortcode('hostaway_property', [$this, 'renderSinglePropertyShortcode']);
    }
    
    /**
     * Render search shortcode
     */
    public function renderSearchShortcode($atts) {
        $atts = shortcode_atts([
            'style' => 'default',
            'show_guests' => true,
            'show_dates' => true,
            'show_location' => true,
        ], $atts);
        
        ob_start();
        
        // Get current search parameters from URL
        $location = sanitize_text_field($_GET['location'] ?? '');
        $checkin = sanitize_text_field($_GET['checkin'] ?? '');
        $checkout = sanitize_text_field($_GET['checkout'] ?? '');
        $adults = intval($_GET['adults'] ?? 2);
        $children = intval($_GET['children'] ?? 0);
        $infants = intval($_GET['infants'] ?? 0);
        
        $properties_page_url = get_permalink(get_option('hostaway_wp_properties_page_id'));
        
        ?>
        <div class="hostaway-search-form" data-style="<?php echo esc_attr($atts['style']); ?>">
            <form method="GET" action="<?php echo esc_url($properties_page_url); ?>" class="hostaway-search">
                <div class="search-fields">
                    <?php if ($atts['show_location']): ?>
                        <div class="field-group location-field">
                            <label for="search-location"><?php esc_html_e('Location', 'hostaway-wp'); ?></label>
                            <input type="text" 
                                   id="search-location" 
                                   name="location" 
                                   value="<?php echo esc_attr($location); ?>" 
                                   placeholder="<?php esc_attr_e('Where are you going?', 'hostaway-wp'); ?>" 
                                   autocomplete="off" />
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_dates']): ?>
                        <div class="field-group dates-field">
                            <label for="search-checkin"><?php esc_html_e('Check-in', 'hostaway-wp'); ?></label>
                            <input type="date" 
                                   id="search-checkin" 
                                   name="checkin" 
                                   value="<?php echo esc_attr($checkin); ?>" 
                                   min="<?php echo esc_attr(date('Y-m-d')); ?>" />
                        </div>
                        
                        <div class="field-group dates-field">
                            <label for="search-checkout"><?php esc_html_e('Check-out', 'hostaway-wp'); ?></label>
                            <input type="date" 
                                   id="search-checkout" 
                                   name="checkout" 
                                   value="<?php echo esc_attr($checkout); ?>" 
                                   min="<?php echo esc_attr(date('Y-m-d')); ?>" />
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_guests']): ?>
                        <div class="field-group guests-field">
                            <label for="search-guests"><?php esc_html_e('Guests', 'hostaway-wp'); ?></label>
                            <div class="guests-selector">
                                <input type="text" 
                                       id="search-guests" 
                                       name="guests_display" 
                                       value="<?php echo esc_attr($this->formatGuestsDisplay($adults, $children, $infants)); ?>" 
                                       readonly 
                                       placeholder="<?php esc_attr_e('Guests', 'hostaway-wp'); ?>" />
                                
                                <div class="guests-dropdown">
                                    <div class="guest-type">
                                        <label><?php esc_html_e('Adults', 'hostaway-wp'); ?></label>
                                        <div class="guest-counter">
                                            <button type="button" class="decrease" data-type="adults">-</button>
                                            <input type="number" name="adults" value="<?php echo esc_attr($adults); ?>" min="1" max="16" />
                                            <button type="button" class="increase" data-type="adults">+</button>
                                        </div>
                                    </div>
                                    
                                    <div class="guest-type">
                                        <label><?php esc_html_e('Children', 'hostaway-wp'); ?></label>
                                        <div class="guest-counter">
                                            <button type="button" class="decrease" data-type="children">-</button>
                                            <input type="number" name="children" value="<?php echo esc_attr($children); ?>" min="0" max="16" />
                                            <button type="button" class="increase" data-type="children">+</button>
                                        </div>
                                    </div>
                                    
                                    <div class="guest-type">
                                        <label><?php esc_html_e('Infants', 'hostaway-wp'); ?></label>
                                        <div class="guest-counter">
                                            <button type="button" class="decrease" data-type="infants">-</button>
                                            <input type="number" name="infants" value="<?php echo esc_attr($infants); ?>" min="0" max="5" />
                                            <button type="button" class="increase" data-type="infants">+</button>
                                        </div>
                                    </div>
                                    
                                    <div class="guests-actions">
                                        <button type="button" class="close-guests"><?php esc_html_e('Done', 'hostaway-wp'); ?></button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hidden field for total guests -->
                            <input type="hidden" name="guests" value="<?php echo esc_attr($adults + $children + $infants); ?>" />
                        </div>
                    <?php endif; ?>
                    
                    <div class="field-group submit-field">
                        <button type="submit" class="search-submit">
                            <span class="search-icon">🔍</span>
                            <?php esc_html_e('Search', 'hostaway-wp'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render properties shortcode
     */
    public function renderPropertiesShortcode($atts) {
        $atts = shortcode_atts([
            'per_page' => 15,
            'show_filters' => true,
            'show_map' => true,
            'show_search' => true,
        ], $atts);
        
        ob_start();
        
        // Get search parameters
        $search_params = $this->getSearchParameters();
        
        // Get properties
        $properties_model = new \HostawayWP\Models\Property();
        $results = $properties_model->search(array_merge($search_params, [
            'per_page' => intval($atts['per_page']),
        ]));
        
        $properties = $results['properties'];
        $total = $results['total'];
        $page = $results['page'];
        $total_pages = $results['total_pages'];
        
        ?>
        <div class="hostaway-properties-page">
            <?php if ($atts['show_search']): ?>
                <div class="properties-search">
                    <?php echo $this->renderSearchShortcode(['show_guests' => true, 'show_dates' => true, 'show_location' => true]); ?>
                </div>
            <?php endif; ?>
            
            <div class="properties-controls">
                <div class="controls-left">
                    <?php if ($atts['show_filters']): ?>
                        <button type="button" class="toggle-filters">
                            <span class="filter-icon">☰</span>
                            <?php esc_html_e('Show Filters', 'hostaway-wp'); ?>
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_map']): ?>
                        <button type="button" class="toggle-map">
                            <span class="map-icon">🗺️</span>
                            <span class="map-text"><?php esc_html_e('Show Map', 'hostaway-wp'); ?></span>
                        </button>
                    <?php endif; ?>
                    
                    <button type="button" class="reset-filters">
                        <?php esc_html_e('Reset', 'hostaway-wp'); ?>
                    </button>
                </div>
                
                <div class="controls-right">
                    <span class="total-count">
                        <?php printf(_n('%d property', '%d properties', $total, 'hostaway-wp'), $total); ?>
                    </span>
                </div>
            </div>
            
            <?php if ($atts['show_filters']): ?>
                <div class="filters-drawer">
                    <div class="filters-content">
                        <?php $this->renderFilters($search_params); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="properties-layout">
                <div class="properties-grid <?php echo $atts['show_map'] ? 'with-map' : 'no-map'; ?>">
                    <?php if (empty($properties)): ?>
                        <div class="no-properties">
                            <h3><?php esc_html_e('No properties found', 'hostaway-wp'); ?></h3>
                            <p><?php esc_html_e('Try adjusting your search criteria or filters.', 'hostaway-wp'); ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($properties as $property): ?>
                            <?php $this->renderPropertyTile($property, $search_params); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($atts['show_map']): ?>
                    <div class="properties-map">
                        <div id="hostaway-map" data-properties="<?php echo esc_attr(json_encode($properties)); ?>"></div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="properties-pagination">
                    <?php $this->renderPagination($page, $total_pages, $search_params); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render single property shortcode
     */
    public function renderSinglePropertyShortcode($atts) {
        $atts = shortcode_atts([
            'id' => '',
        ], $atts);
        
        if (empty($atts['id'])) {
            return '<p>' . esc_html__('Property ID is required.', 'hostaway-wp') . '</p>';
        }
        
        $property_model = new \HostawayWP\Models\Property();
        $property = $property_model->getById($atts['id']);
        
        if (!$property) {
            return '<p>' . esc_html__('Property not found.', 'hostaway-wp') . '</p>';
        }
        
        ob_start();
        
        // Get search parameters for booking form
        $search_params = $this->getSearchParameters();
        
        ?>
        <div class="hostaway-single-property">
            <div class="property-gallery">
                <?php $this->renderPropertyGallery($property); ?>
            </div>
            
            <div class="property-content">
                <div class="property-main">
                    <div class="property-header">
                        <h1 class="property-title"><?php echo esc_html($property['title']); ?></h1>
                        <div class="property-meta">
                            <span class="property-type"><?php echo esc_html($property['type']); ?></span>
                            <span class="property-location"><?php echo esc_html($property['city'] . ', ' . $property['country']); ?></span>
                        </div>
                    </div>
                    
                    <div class="property-tabs">
                        <div class="tab-nav">
                            <button type="button" class="tab-btn active" data-tab="details"><?php esc_html_e('Details', 'hostaway-wp'); ?></button>
                            <button type="button" class="tab-btn" data-tab="amenities"><?php esc_html_e('Amenities', 'hostaway-wp'); ?></button>
                            <button type="button" class="tab-btn" data-tab="availability"><?php esc_html_e('Availability', 'hostaway-wp'); ?></button>
                            <button type="button" class="tab-btn" data-tab="map"><?php esc_html_e('Map', 'hostaway-wp'); ?></button>
                        </div>
                        
                        <div class="tab-content">
                            <div class="tab-panel active" id="details">
                                <?php $this->renderPropertyDetails($property); ?>
                            </div>
                            
                            <div class="tab-panel" id="amenities">
                                <?php $this->renderPropertyAmenities($property); ?>
                            </div>
                            
                            <div class="tab-panel" id="availability">
                                <?php $this->renderPropertyAvailability($property); ?>
                            </div>
                            
                            <div class="tab-panel" id="map">
                                <?php $this->renderPropertyMap($property); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="property-sidebar">
                    <?php $this->renderBookingWidget($property, $search_params); ?>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get search parameters from URL
     */
    private function getSearchParameters() {
        return [
            'search' => sanitize_text_field($_GET['search'] ?? ''),
            'location' => sanitize_text_field($_GET['location'] ?? ''),
            'checkin' => sanitize_text_field($_GET['checkin'] ?? ''),
            'checkout' => sanitize_text_field($_GET['checkout'] ?? ''),
            'adults' => intval($_GET['adults'] ?? 0),
            'children' => intval($_GET['children'] ?? 0),
            'infants' => intval($_GET['infants'] ?? 0),
            'guests' => intval($_GET['guests'] ?? 0),
            'amenities' => array_map('sanitize_text_field', $_GET['amenities'] ?? []),
            'rooms' => intval($_GET['rooms'] ?? 0),
            'bathrooms' => intval($_GET['bathrooms'] ?? 0),
            'price_min' => intval($_GET['price_min'] ?? 0),
            'price_max' => intval($_GET['price_max'] ?? 0),
            'page' => intval($_GET['page'] ?? 1),
        ];
    }
    
    /**
     * Format guests display
     */
    private function formatGuestsDisplay($adults, $children, $infants) {
        $parts = [];
        
        if ($adults > 0) {
            $parts[] = sprintf(_n('%d adult', '%d adults', $adults, 'hostaway-wp'), $adults);
        }
        
        if ($children > 0) {
            $parts[] = sprintf(_n('%d child', '%d children', $children, 'hostaway-wp'), $children);
        }
        
        if ($infants > 0) {
            $parts[] = sprintf(_n('%d infant', '%d infants', $infants, 'hostaway-wp'), $infants);
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Render filters
     */
    private function renderFilters($search_params) {
        // Get available amenities
        $amenities = $this->getAvailableAmenities();
        
        ?>
        <div class="filters-section">
            <h3><?php esc_html_e('Amenities', 'hostaway-wp'); ?></h3>
            <div class="amenity-filters">
                <?php foreach ($amenities as $amenity): ?>
                    <label class="amenity-filter">
                        <input type="checkbox" 
                               name="amenities[]" 
                               value="<?php echo esc_attr($amenity); ?>"
                               <?php checked(in_array($amenity, $search_params['amenities'])); ?> />
                        <span><?php echo esc_html($amenity); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="filters-section">
            <h3><?php esc_html_e('Rooms', 'hostaway-wp'); ?></h3>
            <select name="rooms">
                <option value=""><?php esc_html_e('Any', 'hostaway-wp'); ?></option>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <option value="<?php echo esc_attr($i); ?>" <?php selected($search_params['rooms'], $i); ?>>
                        <?php printf(_n('%d room', '%d rooms', $i, 'hostaway-wp'), $i); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="filters-section">
            <h3><?php esc_html_e('Bathrooms', 'hostaway-wp'); ?></h3>
            <select name="bathrooms">
                <option value=""><?php esc_html_e('Any', 'hostaway-wp'); ?></option>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <option value="<?php echo esc_attr($i); ?>" <?php selected($search_params['bathrooms'], $i); ?>>
                        <?php printf(_n('%d bathroom', '%d bathrooms', $i, 'hostaway-wp'), $i); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="filters-section">
            <h3><?php esc_html_e('Price Range', 'hostaway-wp'); ?></h3>
            <div class="price-range">
                <input type="number" 
                       name="price_min" 
                       placeholder="<?php esc_attr_e('Min price', 'hostaway-wp'); ?>"
                       value="<?php echo esc_attr($search_params['price_min']); ?>" />
                <span>-</span>
                <input type="number" 
                       name="price_max" 
                       placeholder="<?php esc_attr_e('Max price', 'hostaway-wp'); ?>"
                       value="<?php echo esc_attr($search_params['price_max']); ?>" />
            </div>
        </div>
        
        <div class="filters-actions">
            <button type="button" class="apply-filters"><?php esc_html_e('Apply Filters', 'hostaway-wp'); ?></button>
            <button type="button" class="clear-filters"><?php esc_html_e('Clear All', 'hostaway-wp'); ?></button>
        </div>
        <?php
    }
    
    /**
     * Get available amenities
     */
    private function getAvailableAmenities() {
        // Get cached amenities or fetch from database
        $amenities = get_transient('hostaway_amenities');
        
        if ($amenities === false) {
            global $wpdb;
            
            $query = "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(amenities_json, CONCAT('$[', numbers.n, ']'))) as amenity
                      FROM {$wpdb->prefix}hostaway_properties
                      CROSS JOIN (
                          SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                          UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
                      ) numbers
                      WHERE JSON_EXTRACT(amenities_json, CONCAT('$[', numbers.n, ']')) IS NOT NULL
                      AND amenities_json != 'null' AND amenities_json != ''
                      ORDER BY amenity";
            
            $results = $wpdb->get_col($query);
            $amenities = array_filter($results);
            
            set_transient('hostaway_amenities', $amenities, HOUR_IN_SECONDS);
        }
        
        return $amenities;
    }
    
    /**
     * Render property tile
     */
    private function renderPropertyTile($property, $search_params) {
        $currency = get_option('hostaway_wp_currency', 'USD');
        $price = floatval($property['base_price']);
        
        // Calculate total price if dates are provided
        $total_price = null;
        if (!empty($search_params['checkin']) && !empty($search_params['checkout'])) {
            $rate_model = new \HostawayWP\Models\Rate();
            $total_price = $rate_model->calculateTotalPrice(
                $property['id'],
                $search_params['checkin'],
                $search_params['checkout'],
                $search_params['guests']
            );
        }
        
        ?>
        <div class="property-tile" data-property-id="<?php echo esc_attr($property['id']); ?>">
            <div class="tile-image">
                <?php if ($property['thumbnail_url']): ?>
                    <img src="<?php echo esc_url($property['thumbnail_url']); ?>" 
                         alt="<?php echo esc_attr($property['title']); ?>" />
                <?php endif; ?>
                
                <div class="tile-price">
                    <?php if ($total_price !== null): ?>
                        <span class="total-price"><?php echo esc_html($currency . ' ' . number_format($total_price)); ?></span>
                        <span class="price-period"><?php esc_html_e('total', 'hostaway-wp'); ?></span>
                    <?php else: ?>
                        <span class="per-night-price"><?php echo esc_html($currency . ' ' . number_format($price)); ?></span>
                        <span class="price-period"><?php esc_html_e('per night', 'hostaway-wp'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="tile-content">
                <h3 class="tile-title">
                    <a href="<?php echo esc_url(get_permalink(get_option('hostaway_wp_properties_page_id')) . '?property=' . $property['slug']); ?>">
                        <?php echo esc_html($property['title']); ?>
                    </a>
                </h3>
                
                <div class="tile-meta">
                    <span class="tile-type"><?php echo esc_html($property['type']); ?></span>
                    
                    <div class="tile-features">
                        <span class="feature">
                            <span class="feature-icon">📍</span>
                            <?php echo esc_html($property['city'] . ', ' . $property['country']); ?>
                        </span>
                        
                        <?php if ($property['rooms'] > 0): ?>
                            <span class="feature">
                                <span class="feature-icon">🛏️</span>
                                <?php printf(_n('%d room', '%d rooms', $property['rooms'], 'hostaway-wp'), $property['rooms']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($property['bathrooms'] > 0): ?>
                            <span class="feature">
                                <span class="feature-icon">🛁</span>
                                <?php printf(_n('%d bathroom', '%d bathrooms', $property['bathrooms'], 'hostaway-wp'), $property['bathrooms']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($property['guests'] > 0): ?>
                            <span class="feature">
                                <span class="feature-icon">👥</span>
                                <?php printf(_n('%d guest', '%d guests', $property['guests'], 'hostaway-wp'), $property['guests']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render pagination
     */
    private function renderPagination($current_page, $total_pages, $search_params) {
        $base_url = remove_query_arg('page');
        
        ?>
        <div class="pagination">
            <?php if ($current_page > 1): ?>
                <a href="<?php echo esc_url(add_query_arg('page', $current_page - 1, $base_url)); ?>" class="prev-page">
                    <?php esc_html_e('← Previous', 'hostaway-wp'); ?>
                </a>
            <?php endif; ?>
            
            <div class="page-numbers">
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="<?php echo esc_url(add_query_arg('page', $i, $base_url)); ?>" 
                       class="page-number <?php echo $i === $current_page ? 'current' : ''; ?>">
                        <?php echo esc_html($i); ?>
                    </a>
                <?php endfor; ?>
            </div>
            
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo esc_url(add_query_arg('page', $current_page + 1, $base_url)); ?>" class="next-page">
                    <?php esc_html_e('Next →', 'hostaway-wp'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render property gallery
     */
    private function renderPropertyGallery($property) {
        $gallery = json_decode($property['gallery_json'], true);
        
        if (empty($gallery)) {
            return;
        }
        
        ?>
        <div class="property-gallery-slider">
            <?php foreach ($gallery as $image_url): ?>
                <div class="gallery-slide">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($property['title']); ?>" />
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render property details
     */
    private function renderPropertyDetails($property) {
        ?>
        <div class="property-description">
            <?php echo wp_kses_post($property['description']); ?>
        </div>
        
        <div class="property-specs">
            <h3><?php esc_html_e('Property Details', 'hostaway-wp'); ?></h3>
            <div class="specs-grid">
                <div class="spec-item">
                    <span class="spec-label"><?php esc_html_e('Type', 'hostaway-wp'); ?></span>
                    <span class="spec-value"><?php echo esc_html($property['type']); ?></span>
                </div>
                
                <div class="spec-item">
                    <span class="spec-label"><?php esc_html_e('Rooms', 'hostaway-wp'); ?></span>
                    <span class="spec-value"><?php echo esc_html($property['rooms']); ?></span>
                </div>
                
                <div class="spec-item">
                    <span class="spec-label"><?php esc_html_e('Bathrooms', 'hostaway-wp'); ?></span>
                    <span class="spec-value"><?php echo esc_html($property['bathrooms']); ?></span>
                </div>
                
                <div class="spec-item">
                    <span class="spec-label"><?php esc_html_e('Max Guests', 'hostaway-wp'); ?></span>
                    <span class="spec-value"><?php echo esc_html($property['guests']); ?></span>
                </div>
                
                <div class="spec-item">
                    <span class="spec-label"><?php esc_html_e('Location', 'hostaway-wp'); ?></span>
                    <span class="spec-value"><?php echo esc_html($property['address']); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render property amenities
     */
    private function renderPropertyAmenities($property) {
        $amenities = json_decode($property['amenities_json'], true);
        
        if (empty($amenities)) {
            echo '<p>' . esc_html__('No amenities listed.', 'hostaway-wp') . '</p>';
            return;
        }
        
        ?>
        <div class="property-amenities">
            <div class="amenities-grid">
                <?php foreach ($amenities as $amenity): ?>
                    <div class="amenity-item">
                        <span class="amenity-icon">✓</span>
                        <span class="amenity-name"><?php echo esc_html($amenity); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render property availability
     */
    private function renderPropertyAvailability($property) {
        ?>
        <div class="property-availability">
            <div id="availability-calendar" data-property-id="<?php echo esc_attr($property['id']); ?>"></div>
        </div>
        <?php
    }
    
    /**
     * Render property map
     */
    private function renderPropertyMap($property) {
        if (!$property['latitude'] || !$property['longitude']) {
            echo '<p>' . esc_html__('Location not available.', 'hostaway-wp') . '</p>';
            return;
        }
        
        ?>
        <div class="property-map">
            <div id="single-property-map" 
                 data-lat="<?php echo esc_attr($property['latitude']); ?>"
                 data-lng="<?php echo esc_attr($property['longitude']); ?>"
                 data-title="<?php echo esc_attr($property['title']); ?>"></div>
        </div>
        <?php
    }
    
    /**
     * Render booking widget
     */
    private function renderBookingWidget($property, $search_params) {
        $currency = get_option('hostaway_wp_currency', 'USD');
        $price = floatval($property['base_price']);
        
        ?>
        <div class="booking-widget">
            <div class="booking-price">
                <span class="price-amount"><?php echo esc_html($currency . ' ' . number_format($price)); ?></span>
                <span class="price-period"><?php esc_html_e('per night', 'hostaway-wp'); ?></span>
            </div>
            
            <div class="booking-form">
                <form id="booking-form" data-property-id="<?php echo esc_attr($property['id']); ?>">
                    <div class="booking-field">
                        <label for="booking-checkin"><?php esc_html_e('Check-in', 'hostaway-wp'); ?></label>
                        <input type="date" 
                               id="booking-checkin" 
                               name="checkin" 
                               value="<?php echo esc_attr($search_params['checkin']); ?>"
                               min="<?php echo esc_attr(date('Y-m-d')); ?>" 
                               required />
                    </div>
                    
                    <div class="booking-field">
                        <label for="booking-checkout"><?php esc_html_e('Check-out', 'hostaway-wp'); ?></label>
                        <input type="date" 
                               id="booking-checkout" 
                               name="checkout" 
                               value="<?php echo esc_attr($search_params['checkout']); ?>"
                               min="<?php echo esc_attr(date('Y-m-d')); ?>" 
                               required />
                    </div>
                    
                    <div class="booking-field">
                        <label for="booking-guests"><?php esc_html_e('Guests', 'hostaway-wp'); ?></label>
                        <div class="guests-selector">
                            <input type="text" 
                                   id="booking-guests" 
                                   name="guests_display" 
                                   value="<?php echo esc_attr($this->formatGuestsDisplay(
                                       $search_params['adults'], 
                                       $search_params['children'], 
                                       $search_params['infants']
                                   )); ?>" 
                                   readonly />
                            
                            <div class="guests-dropdown">
                                <div class="guest-type">
                                    <label><?php esc_html_e('Adults', 'hostaway-wp'); ?></label>
                                    <div class="guest-counter">
                                        <button type="button" class="decrease" data-type="adults">-</button>
                                        <input type="number" name="adults" value="<?php echo esc_attr($search_params['adults']); ?>" min="1" max="16" />
                                        <button type="button" class="increase" data-type="adults">+</button>
                                    </div>
                                </div>
                                
                                <div class="guest-type">
                                    <label><?php esc_html_e('Children', 'hostaway-wp'); ?></label>
                                    <div class="guest-counter">
                                        <button type="button" class="decrease" data-type="children">-</button>
                                        <input type="number" name="children" value="<?php echo esc_attr($search_params['children']); ?>" min="0" max="16" />
                                        <button type="button" class="increase" data-type="children">+</button>
                                    </div>
                                </div>
                                
                                <div class="guest-type">
                                    <label><?php esc_html_e('Infants', 'hostaway-wp'); ?></label>
                                    <div class="guest-counter">
                                        <button type="button" class="decrease" data-type="infants">-</button>
                                        <input type="number" name="infants" value="<?php echo esc_attr($search_params['infants']); ?>" min="0" max="5" />
                                        <button type="button" class="increase" data-type="infants">+</button>
                                    </div>
                                </div>
                                
                                <div class="guests-actions">
                                    <button type="button" class="close-guests"><?php esc_html_e('Done', 'hostaway-wp'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="booking-extras">
                        <h4><?php esc_html_e('Optional Extras', 'hostaway-wp'); ?></h4>
                        
                        <label class="extra-option">
                            <input type="checkbox" name="extra_catering" value="100" />
                            <span class="extra-name"><?php esc_html_e('Catering', 'hostaway-wp'); ?></span>
                            <span class="extra-price"><?php echo esc_html($currency . ' 100'); ?> <?php esc_html_e('per night', 'hostaway-wp'); ?></span>
                        </label>
                        
                        <label class="extra-option">
                            <input type="checkbox" name="extra_bedding" value="50" />
                            <span class="extra-name"><?php esc_html_e('Bedding', 'hostaway-wp'); ?></span>
                            <span class="extra-price"><?php echo esc_html($currency . ' 50'); ?> <?php esc_html_e('per night', 'hostaway-wp'); ?></span>
                        </label>
                    </div>
                    
                    <div class="booking-total">
                        <div class="total-breakdown">
                            <div class="total-item">
                                <span><?php esc_html_e('Base price', 'hostaway-wp'); ?></span>
                                <span id="base-price"><?php echo esc_html($currency . ' 0'); ?></span>
                            </div>
                            <div class="total-item extras" style="display: none;">
                                <span><?php esc_html_e('Extras', 'hostaway-wp'); ?></span>
                                <span id="extras-price"><?php echo esc_html($currency . ' 0'); ?></span>
                            </div>
                            <div class="total-item total">
                                <span><?php esc_html_e('Total', 'hostaway-wp'); ?></span>
                                <span id="total-price"><?php echo esc_html($currency . ' 0'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="booking-submit">
                        <span class="booking-icon">⚡</span>
                        <?php esc_html_e('Instant Booking', 'hostaway-wp'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
}
