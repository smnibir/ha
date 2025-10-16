<?php

namespace HostawaySync\API;

use HostawaySync\Database\Database;

/**
 * Hostaway API Client
 */
class HostawayClient {
    
    /**
     * API base URL
     */
    const API_BASE_URL = 'https://api.hostaway.com/v1';
    
    /**
     * API credentials
     */
    private $account_id;
    private $api_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->account_id = get_option('hostaway_sync_hostaway_account_id', '');
        $this->api_key = get_option('hostaway_sync_hostaway_api_key', '');
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'GET', $data = null) {
        if (empty($this->account_id) || empty($this->api_key)) {
            throw new \Exception('Hostaway API credentials not configured');
        }
        
        $url = self::API_BASE_URL . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_access_token(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = wp_json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('Hostaway API Request Error: ' . $error_message);
            throw new \Exception('API request failed: ' . $error_message);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('Hostaway API Status: ' . $status_code . ', Body: ' . $body);
        
        if ($status_code >= 400) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'API request failed';
            error_log('Hostaway API Error Response: ' . $body);
            throw new \Exception("API Error ($status_code): $error_message");
        }
        
        $decoded_body = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        return $decoded_body;
    }
    
    /**
     * Get access token using client credentials
     */
    private function get_access_token() {
        $cache_key = 'hostaway_access_token';
        $cached_token = get_transient($cache_key);
        
        if ($cached_token) {
            return $cached_token;
        }
        
        $url = 'https://api.hostaway.com/v1/accessTokens';
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            ),
            'body' => http_build_query(array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->account_id,
                'client_secret' => $this->api_key,
                'scope' => 'general'
            )),
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('Hostaway Token Request Error: ' . $error_message);
            throw new \Exception('Failed to get access token: ' . $error_message);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('Hostaway Token Response Status: ' . $status_code . ', Body: ' . $body);
        
        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Failed to get access token';
            error_log('Hostaway Token Error Response: ' . $body);
            throw new \Exception("Token Error ($status_code): $error_message");
        }
        
        $token_data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response for token: ' . json_last_error_msg());
        }
        
        if (!isset($token_data['access_token'])) {
            error_log('Hostaway Token Response Missing access_token: ' . $body);
            throw new \Exception('Invalid token response - missing access_token');
        }
        
        $access_token = $token_data['access_token'];
        // Access token is valid for up to 24 months, cache for 12 months
        $expires_in = 12 * 30 * 24 * 60 * 60; // 12 months in seconds
        
        set_transient($cache_key, $access_token, $expires_in);
        
        return $access_token;
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        try {
            // First test the access token
            $token = $this->get_access_token();
            if (!$token) {
                return array(
                    'success' => false,
                    'message' => __('Failed to get access token', 'hostaway-sync')
                );
            }
            
            // Test the listings endpoint
            $response = $this->make_request('/listings');
            
            // Log the response for debugging
            error_log('Hostaway API Response: ' . wp_json_encode($response));
            
            if (is_array($response) && (isset($response['data']) || isset($response['result']) || isset($response['status']))) {
                return array(
                    'success' => true,
                    'message' => __('Connection successful', 'hostaway-sync'),
                    'data' => array(
                        'token_received' => true,
                        'response_keys' => array_keys($response),
                        'sample_data' => array_slice($response, 0, 2)
                    )
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Invalid API response format: ' . wp_json_encode($response), 'hostaway-sync')
                );
            }
        } catch (\Exception $e) {
            error_log('Hostaway API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get all properties
     */
    public function get_properties($limit = 100, $offset = 0) {
        try {
            $endpoint = "/listings?limit=$limit&offset=$offset";
            $response = $this->make_request($endpoint);
            
            // Handle different response structures
            if (isset($response['result'])) {
                return array('result' => $response['result']);
            } elseif (isset($response['data'])) {
                return array('result' => $response['data']);
            } else {
                return $response;
            }
        } catch (\Exception $e) {
            error_log('Hostaway get_properties error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get property details
     */
    public function get_property_details($listing_id) {
        $endpoint = "/listings/$listing_id";
        return $this->make_request($endpoint);
    }
    
    /**
     * Get property rates
     */
    public function get_property_rates($listing_id, $date_from = null, $date_to = null) {
        $endpoint = "/listings/$listing_id/calendarPricing";
        
        if ($date_from && $date_to) {
            $endpoint .= "?dateFrom=$date_from&dateTo=$date_to";
        }
        
        return $this->make_request($endpoint);
    }
    
    /**
     * Get property availability
     */
    public function get_property_availability($listing_id, $date_from = null, $date_to = null) {
        $endpoint = "/listings/$listing_id/calendar";
        
        if ($date_from && $date_to) {
            $endpoint .= "?dateFrom=$date_from&dateTo=$date_to";
        }
        
        return $this->make_request($endpoint);
    }
    
    /**
     * Get property amenities
     */
    public function get_property_amenities($listing_id) {
        $endpoint = "/listings/$listing_id";
        $response = $this->make_request($endpoint);
        
        if (isset($response['result']) && isset($response['result']['amenities'])) {
            return $response['result']['amenities'];
        }
        
        return array();
    }
    
    /**
     * Get all available amenities
     */
    public function get_all_amenities() {
        $amenities = array();
        
        try {
            // Get a sample of properties to extract amenities
            $properties = $this->get_properties(50);
            
            if (isset($properties['result']) && is_array($properties['result'])) {
                foreach ($properties['result'] as $property) {
                    if (isset($property['amenities']) && is_array($property['amenities'])) {
                        foreach ($property['amenities'] as $amenity) {
                            if (isset($amenity['id']) && isset($amenity['name'])) {
                                $amenities[$amenity['id']] = $amenity['name'];
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Database::log_sync('amenities_fetch', 'error', $e->getMessage());
        }
        
        return $amenities;
    }
    
    /**
     * Create reservation
     */
    public function create_reservation($listing_id, $reservation_data) {
        $endpoint = "/reservations";
        
        $data = array(
            'listingId' => $listing_id,
            'arrivalDate' => $reservation_data['checkin_date'],
            'departureDate' => $reservation_data['checkout_date'],
            'guestCount' => $reservation_data['guest_count'],
            'totalPrice' => $reservation_data['total_amount'],
            'currency' => $reservation_data['currency'] ?? 'USD',
            'firstName' => $reservation_data['first_name'],
            'lastName' => $reservation_data['last_name'],
            'email' => $reservation_data['email'],
            'phone' => $reservation_data['phone'] ?? '',
            'notes' => $reservation_data['notes'] ?? ''
        );
        
        return $this->make_request($endpoint, 'POST', $data);
    }
    
    /**
     * Get reservation details
     */
    public function get_reservation($reservation_id) {
        $endpoint = "/reservations/$reservation_id";
        return $this->make_request($endpoint);
    }
    
    /**
     * Update reservation
     */
    public function update_reservation($reservation_id, $data) {
        $endpoint = "/reservations/$reservation_id";
        return $this->make_request($endpoint, 'PUT', $data);
    }
    
    /**
     * Cancel reservation
     */
    public function cancel_reservation($reservation_id, $reason = '') {
        $endpoint = "/reservations/$reservation_id";
        $data = array(
            'status' => 'cancelled',
            'cancellationReason' => $reason
        );
        
        return $this->make_request($endpoint, 'PUT', $data);
    }
    
    /**
     * Search properties
     */
    public function search_properties($filters = array()) {
        $endpoint = '/listings';
        $query_params = array();
        
        // Add filters to query parameters
        if (isset($filters['location'])) {
            $query_params[] = 'location=' . urlencode($filters['location']);
        }
        
        if (isset($filters['checkin']) && isset($filters['checkout'])) {
            $query_params[] = 'dateFrom=' . $filters['checkin'];
            $query_params[] = 'dateTo=' . $filters['checkout'];
        }
        
        if (isset($filters['guests'])) {
            $query_params[] = 'guestCount=' . $filters['guests'];
        }
        
        if (isset($filters['property_type'])) {
            $query_params[] = 'propertyType=' . urlencode($filters['property_type']);
        }
        
        if (isset($filters['amenities']) && is_array($filters['amenities'])) {
            foreach ($filters['amenities'] as $amenity_id) {
                $query_params[] = 'amenityIds[]=' . $amenity_id;
            }
        }
        
        if (isset($filters['min_price'])) {
            $query_params[] = 'minPrice=' . $filters['min_price'];
        }
        
        if (isset($filters['max_price'])) {
            $query_params[] = 'maxPrice=' . $filters['max_price'];
        }
        
        if (isset($filters['limit'])) {
            $query_params[] = 'limit=' . $filters['limit'];
        }
        
        if (isset($filters['offset'])) {
            $query_params[] = 'offset=' . $filters['offset'];
        }
        
        if (!empty($query_params)) {
            $endpoint .= '?' . implode('&', $query_params);
        }
        
        return $this->make_request($endpoint);
    }
    
    /**
     * Get all available cities
     */
    public function get_available_cities() {
        $cache_key = 'hostaway_available_cities';
        $cached_cities = get_transient($cache_key);
        
        if ($cached_cities) {
            return $cached_cities;
        }
        
        try {
            // Get all properties to extract unique cities
            $properties = $this->get_properties(1000);
            
            if (!isset($properties['result']) || !is_array($properties['result'])) {
                return array();
            }
            
            $cities = array();
            foreach ($properties['result'] as $property) {
                if (isset($property['city']) && !empty($property['city'])) {
                    $city = trim($property['city']);
                    if (!in_array($city, $cities)) {
                        $cities[] = $city;
                    }
                }
            }
            
            // Sort cities alphabetically
            sort($cities);
            
            // Cache for 1 hour
            set_transient($cache_key, $cities, 3600);
            
            return $cities;
            
        } catch (\Exception $e) {
            Database::log_sync('cities_fetch', 'error', $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get location suggestions
     */
    public function get_location_suggestions($query) {
        $endpoint = '/locations?query=' . urlencode($query);
        return $this->make_request($endpoint);
    }
    
    /**
     * AJAX handler for testing connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('hostaway_test_connection', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hostaway-sync'));
        }
        
        $result = $this->test_connection();
        
        wp_send_json($result);
    }
}
