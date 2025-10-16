<?php

namespace HostawayWP\Rest;

use HostawayWP\Models\Property;
use HostawayWP\Models\Rate;
use HostawayWP\Models\Availability;

/**
 * REST API endpoints
 */
class Endpoints {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'registerEndpoints']);
    }
    
    /**
     * Register REST endpoints
     */
    public function registerEndpoints() {
        register_rest_route('hostaway/v1', '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'searchProperties'],
            'permission_callback' => '__return_true',
            'args' => [
                'search' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'location' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'checkin' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'checkout' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'guests' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'amenities' => [
                    'required' => false,
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'rooms' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'bathrooms' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'price_min' => [
                    'required' => false,
                    'type' => 'number',
                    'sanitize_callback' => 'floatval',
                ],
                'price_max' => [
                    'required' => false,
                    'type' => 'number',
                    'sanitize_callback' => 'floatval',
                ],
                'page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 15,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
        
        register_rest_route('hostaway/v1', '/filters', [
            'methods' => 'GET',
            'callback' => [$this, 'getFilters'],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route('hostaway/v1', '/properties', [
            'methods' => 'GET',
            'callback' => [$this, 'getProperties'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'slug' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 50,
                    'sanitize_callback' => 'absint',
                ],
                'offset' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
        
        register_rest_route('hostaway/v1', '/availability/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getAvailability'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'year' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => date('Y'),
                    'sanitize_callback' => 'absint',
                ],
                'month' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => date('n'),
                    'sanitize_callback' => 'absint',
                ],
                'start_date' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'end_date' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        register_rest_route('hostaway/v1', '/rates/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getRates'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'start_date' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'end_date' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        register_rest_route('hostaway/v1', '/calculate-price', [
            'methods' => 'POST',
            'callback' => [$this, 'calculatePrice'],
            'permission_callback' => '__return_true',
            'args' => [
                'property_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'checkin' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'checkout' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'guests' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }
    
    /**
     * Search properties
     */
    public function searchProperties($request) {
        $params = $request->get_params();
        
        $property_model = new Property();
        $results = $property_model->search($params);
        
        // Format results for API
        $formatted_properties = array_map([$this, 'formatPropertyForApi'], $results['properties']);
        
        return rest_ensure_response([
            'properties' => $formatted_properties,
            'total' => $results['total'],
            'page' => $results['page'],
            'per_page' => $results['per_page'],
            'total_pages' => $results['total_pages'],
        ]);
    }
    
    /**
     * Get filters
     */
    public function getFilters($request) {
        $amenities = $this->getAvailableAmenities();
        
        return rest_ensure_response([
            'amenities' => $amenities,
            'price_range' => $this->getPriceRange(),
            'rooms_range' => $this->getRoomsRange(),
            'bathrooms_range' => $this->getBathroomsRange(),
        ]);
    }
    
    /**
     * Get properties
     */
    public function getProperties($request) {
        $params = $request->get_params();
        
        $property_model = new Property();
        
        if (!empty($params['id'])) {
            $property = $property_model->getById($params['id']);
            if (!$property) {
                return new \WP_Error('property_not_found', __('Property not found.', 'hostaway-wp'), ['status' => 404]);
            }
            return rest_ensure_response($this->formatPropertyForApi($property));
        }
        
        if (!empty($params['slug'])) {
            $property = $property_model->getBySlug($params['slug']);
            if (!$property) {
                return new \WP_Error('property_not_found', __('Property not found.', 'hostaway-wp'), ['status' => 404]);
            }
            return rest_ensure_response($this->formatPropertyForApi($property));
        }
        
        $properties = $property_model->getAll($params['limit'], $params['offset']);
        $formatted_properties = array_map([$this, 'formatPropertyForApi'], $properties);
        
        return rest_ensure_response($formatted_properties);
    }
    
    /**
     * Get availability
     */
    public function getAvailability($request) {
        $params = $request->get_params();
        $property_id = $params['id'];
        
        $availability_model = new Availability();
        
        if (!empty($params['start_date']) && !empty($params['end_date'])) {
            $availability = $availability_model->getByPropertyAndDateRange(
                $property_id,
                $params['start_date'],
                $params['end_date']
            );
            
            $formatted_availability = [];
            foreach ($availability as $day) {
                $formatted_availability[$day['date']] = [
                    'is_booked' => (bool) $day['is_booked'],
                    'is_available' => (bool) $day['is_available'],
                ];
            }
            
            return rest_ensure_response($formatted_availability);
        }
        
        // Return calendar data for specific month
        $year = $params['year'];
        $month = $params['month'];
        
        $calendar_data = $availability_model->getCalendarData($property_id, $year, $month);
        
        return rest_ensure_response($calendar_data);
    }
    
    /**
     * Get rates
     */
    public function getRates($request) {
        $params = $request->get_params();
        $property_id = $params['id'];
        
        $rate_model = new Rate();
        
        if (!empty($params['start_date']) && !empty($params['end_date'])) {
            $rates = $rate_model->getByPropertyAndDateRange(
                $property_id,
                $params['start_date'],
                $params['end_date']
            );
        } else {
            $rates = $rate_model->getAllByProperty($property_id);
        }
        
        return rest_ensure_response($rates);
    }
    
    /**
     * Calculate price
     */
    public function calculatePrice($request) {
        $params = $request->get_params();
        
        $rate_model = new Rate();
        $price = $rate_model->calculateTotalPrice(
            $params['property_id'],
            $params['checkin'],
            $params['checkout'],
            $params['guests']
        );
        
        if ($price === null) {
            return new \WP_Error('price_calculation_failed', __('Unable to calculate price for selected dates.', 'hostaway-wp'), ['status' => 400]);
        }
        
        // Get minimum nights
        $min_nights = $rate_model->getMinNights($params['property_id'], $params['checkin']);
        
        // Calculate nights
        $start = new \DateTime($params['checkin']);
        $end = new \DateTime($params['checkout']);
        $nights = $start->diff($end)->days;
        
        return rest_ensure_response([
            'total_price' => $price,
            'nights' => $nights,
            'min_nights' => $min_nights,
            'price_per_night' => $nights > 0 ? $price / $nights : 0,
            'currency' => get_option('hostaway_wp_currency', 'USD'),
        ]);
    }
    
    /**
     * Format property for API response
     */
    private function formatPropertyForApi($property) {
        return [
            'id' => intval($property['id']),
            'hostaway_id' => $property['hostaway_id'],
            'title' => $property['title'],
            'slug' => $property['slug'],
            'type' => $property['type'],
            'location' => [
                'country' => $property['country'],
                'city' => $property['city'],
                'address' => $property['address'],
                'latitude' => $property['latitude'] ? floatval($property['latitude']) : null,
                'longitude' => $property['longitude'] ? floatval($property['longitude']) : null,
            ],
            'rooms' => intval($property['rooms']),
            'bathrooms' => intval($property['bathrooms']),
            'guests' => intval($property['guests']),
            'base_price' => floatval($property['base_price']),
            'thumbnail_url' => $property['thumbnail_url'],
            'gallery' => json_decode($property['gallery_json'], true) ?: [],
            'amenities' => json_decode($property['amenities_json'], true) ?: [],
            'features' => json_decode($property['features_json'], true) ?: [],
            'description' => $property['description'],
            'status' => $property['status'],
            'updated_at' => $property['updated_at'],
            'created_at' => $property['created_at'],
        ];
    }
    
    /**
     * Get available amenities
     */
    private function getAvailableAmenities() {
        $amenities = get_transient('hostaway_api_amenities');
        
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
            
            set_transient('hostaway_api_amenities', $amenities, HOUR_IN_SECONDS);
        }
        
        return $amenities;
    }
    
    /**
     * Get price range
     */
    private function getPriceRange() {
        global $wpdb;
        
        $query = "SELECT MIN(base_price) as min_price, MAX(base_price) as max_price 
                  FROM {$wpdb->prefix}hostaway_properties 
                  WHERE status = 'active' AND base_price > 0";
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        return [
            'min' => floatval($result['min_price'] ?? 0),
            'max' => floatval($result['max_price'] ?? 1000),
        ];
    }
    
    /**
     * Get rooms range
     */
    private function getRoomsRange() {
        global $wpdb;
        
        $query = "SELECT MIN(rooms) as min_rooms, MAX(rooms) as max_rooms 
                  FROM {$wpdb->prefix}hostaway_properties 
                  WHERE status = 'active' AND rooms > 0";
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        return [
            'min' => intval($result['min_rooms'] ?? 1),
            'max' => intval($result['max_rooms'] ?? 10),
        ];
    }
    
    /**
     * Get bathrooms range
     */
    private function getBathroomsRange() {
        global $wpdb;
        
        $query = "SELECT MIN(bathrooms) as min_bathrooms, MAX(bathrooms) as max_bathrooms 
                  FROM {$wpdb->prefix}hostaway_properties 
                  WHERE status = 'active' AND bathrooms > 0";
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        return [
            'min' => intval($result['min_bathrooms'] ?? 1),
            'max' => intval($result['max_bathrooms'] ?? 5),
        ];
    }
}
