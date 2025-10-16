<?php

namespace HostawayWP\Frontend;

/**
 * Frontend functionality
 */
class Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize frontend
     */
    private function init() {
        // Initialize components
        new Assets();
        new Shortcodes();
        
        // Add template redirect for single property pages
        add_action('template_redirect', [$this, 'handleSinglePropertyTemplate']);
        
        // Add custom query vars
        add_filter('query_vars', [$this, 'addQueryVars']);
        
        // Handle property parameter in URL
        add_action('pre_get_posts', [$this, 'handlePropertyQuery']);
    }
    
    /**
     * Handle single property template
     */
    public function handleSinglePropertyTemplate() {
        global $wp_query;
        
        // Check if we're on the properties page with a property parameter
        if (is_page(get_option('hostaway_wp_properties_page_id'))) {
            $property_slug = get_query_var('property');
            
            if ($property_slug) {
                // Get property by slug
                $property_model = new \HostawayWP\Models\Property();
                $property = $property_model->getBySlug($property_slug);
                
                if ($property) {
                    // Set up single property display
                    $this->displaySingleProperty($property);
                    return;
                } else {
                    // Property not found, show 404
                    $wp_query->set_404();
                    status_header(404);
                    get_template_part('404');
                    exit;
                }
            }
        }
    }
    
    /**
     * Display single property
     */
    private function displaySingleProperty($property) {
        // Get search parameters for booking form
        $search_params = [
            'checkin' => sanitize_text_field($_GET['checkin'] ?? ''),
            'checkout' => sanitize_text_field($_GET['checkout'] ?? ''),
            'adults' => intval($_GET['adults'] ?? 2),
            'children' => intval($_GET['children'] ?? 0),
            'infants' => intval($_GET['infants'] ?? 0),
        ];
        
        // Set page title
        add_filter('wp_title', function($title) use ($property) {
            return $property['title'] . ' - ' . get_bloginfo('name');
        });
        
        // Add meta description
        add_action('wp_head', function() use ($property) {
            $description = wp_trim_words($property['description'], 30);
            echo '<meta name="description" content="' . esc_attr($description) . '">';
        });
        
        // Override page content
        add_filter('the_content', function($content) use ($property, $search_params) {
            return $this->renderSinglePropertyContent($property, $search_params);
        });
    }
    
    /**
     * Render single property content
     */
    private function renderSinglePropertyContent($property, $search_params) {
        ob_start();
        
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
                            <span class="property-location">
                                <span class="location-icon">📍</span>
                                <?php echo esc_html($property['city'] . ', ' . $property['country']); ?>
                            </span>
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
     * Add query vars
     */
    public function addQueryVars($vars) {
        $vars[] = 'property';
        return $vars;
    }
    
    /**
     * Handle property query
     */
    public function handlePropertyQuery($query) {
        if (!is_admin() && $query->is_main_query()) {
            if (is_page(get_option('hostaway_wp_properties_page_id'))) {
                $property_slug = get_query_var('property');
                
                if ($property_slug) {
                    // Add rewrite rule for property slug
                    add_rewrite_rule(
                        '^properties/([^/]+)/?$',
                        'index.php?page_id=' . get_option('hostaway_wp_properties_page_id') . '&property=$matches[1]',
                        'top'
                    );
                }
            }
        }
    }
    
    /**
     * Render property gallery
     */
    private function renderPropertyGallery($property) {
        $gallery = json_decode($property['gallery_json'], true);
        
        if (empty($gallery)) {
            if ($property['thumbnail_url']) {
                $gallery = [$property['thumbnail_url']];
            } else {
                return;
            }
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
            <?php if ($property['description']): ?>
                <?php echo wp_kses_post($property['description']); ?>
            <?php else: ?>
                <p><?php esc_html_e('No description available for this property.', 'hostaway-wp'); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="property-specs">
            <h3><?php esc_html_e('Property Details', 'hostaway-wp'); ?></h3>
            <div class="specs-grid">
                <div class="spec-item">
                    <span class="spec-label"><?php esc_html_e('Type', 'hostaway-wp'); ?></span>
                    <span class="spec-value"><?php echo esc_html($property['type']); ?></span>
                </div>
                
                <?php if ($property['rooms'] > 0): ?>
                    <div class="spec-item">
                        <span class="spec-label"><?php esc_html_e('Rooms', 'hostaway-wp'); ?></span>
                        <span class="spec-value"><?php echo esc_html($property['rooms']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($property['bathrooms'] > 0): ?>
                    <div class="spec-item">
                        <span class="spec-label"><?php esc_html_e('Bathrooms', 'hostaway-wp'); ?></span>
                        <span class="spec-value"><?php echo esc_html($property['bathrooms']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($property['guests'] > 0): ?>
                    <div class="spec-item">
                        <span class="spec-label"><?php esc_html_e('Max Guests', 'hostaway-wp'); ?></span>
                        <span class="spec-value"><?php echo esc_html($property['guests']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($property['address']): ?>
                    <div class="spec-item">
                        <span class="spec-label"><?php esc_html_e('Location', 'hostaway-wp'); ?></span>
                        <span class="spec-value"><?php echo esc_html($property['address']); ?></span>
                    </div>
                <?php endif; ?>
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
}
