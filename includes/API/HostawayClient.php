<?php

namespace HostawayWP\API;

/**
 * Hostaway API Client
 */
class HostawayClient {
    
    /**
     * API base URL
     */
    private $base_url = 'https://api.hostaway.com/v1';
    
    /**
     * API credentials
     */
    private $api_key;
    private $api_secret;
    
    /**
     * Request timeout
     */
    private $timeout = 30;
    
    /**
     * Constructor
     */
    public function __construct($api_key = null, $api_secret = null) {
        $this->api_key = $api_key ?: get_option('hostaway_wp_api_key');
        $this->api_secret = $api_secret ?: get_option('hostaway_wp_api_secret');
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            $response = $this->makeRequest('GET', '/listings');
            
            if ($response && isset($response['status']) && $response['status'] === 'success') {
                return [
                    'success' => true,
                    'message' => __('API connection successful', 'hostaway-wp'),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('API connection failed', 'hostaway-wp'),
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('API connection error: %s', 'hostaway-wp'), $e->getMessage()),
            ];
        }
    }
    
    /**
     * Get all properties
     */
    public function getProperties($page = 1, $limit = 100) {
        $params = [
            'page' => $page,
            'limit' => $limit,
        ];
        
        return $this->makeRequest('GET', '/listings', $params);
    }
    
    /**
     * Get single property
     */
    public function getProperty($property_id) {
        return $this->makeRequest('GET', "/listings/{$property_id}");
    }
    
    /**
     * Get property images
     */
    public function getPropertyImages($property_id) {
        return $this->makeRequest('GET', "/listings/{$property_id}/images");
    }
    
    /**
     * Get property rates
     */
    public function getPropertyRates($property_id, $start_date = null, $end_date = null) {
        $params = [];
        
        if ($start_date) {
            $params['startDate'] = $start_date;
        }
        
        if ($end_date) {
            $params['endDate'] = $end_date;
        }
        
        return $this->makeRequest('GET', "/listings/{$property_id}/calendar/rates", $params);
    }
    
    /**
     * Get property availability
     */
    public function getPropertyAvailability($property_id, $start_date = null, $end_date = null) {
        $params = [];
        
        if ($start_date) {
            $params['startDate'] = $start_date;
        }
        
        if ($end_date) {
            $params['endDate'] = $end_date;
        }
        
        return $this->makeRequest('GET', "/listings/{$property_id}/calendar/availability", $params);
    }
    
    /**
     * Get property amenities
     */
    public function getPropertyAmenities($property_id) {
        return $this->makeRequest('GET', "/listings/{$property_id}/amenities");
    }
    
    /**
     * Create reservation
     */
    public function createReservation($property_id, $data) {
        $reservation_data = [
            'listingId' => $property_id,
            'checkIn' => $data['checkin'],
            'checkOut' => $data['checkout'],
            'guests' => $data['guests'],
            'firstName' => $data['first_name'],
            'lastName' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'notes' => $data['notes'] ?? '',
            'source' => 'wordpress_plugin',
        ];
        
        return $this->makeRequest('POST', '/reservations', $reservation_data);
    }
    
    /**
     * Get reservation details
     */
    public function getReservation($reservation_id) {
        return $this->makeRequest('GET', "/reservations/{$reservation_id}");
    }
    
    /**
     * Cancel reservation
     */
    public function cancelReservation($reservation_id, $reason = '') {
        $data = [];
        if ($reason) {
            $data['cancellationReason'] = $reason;
        }
        
        return $this->makeRequest('DELETE', "/reservations/{$reservation_id}", $data);
    }
    
    /**
     * Get all amenities
     */
    public function getAmenities() {
        return $this->makeRequest('GET', '/amenities');
    }
    
    /**
     * Get property types
     */
    public function getPropertyTypes() {
        return $this->makeRequest('GET', '/listingTypes');
    }
    
    /**
     * Make HTTP request
     */
    private function makeRequest($method, $endpoint, $params = []) {
        if (!$this->api_key || !$this->api_secret) {
            throw new Exception(__('API credentials not configured', 'hostaway-wp'));
        }
        
        $url = $this->base_url . $endpoint;
        
        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        
        // Prepare request arguments
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => $this->timeout,
        ];
        
        // Add parameters based on method
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        } elseif (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($params)) {
            $args['body'] = json_encode($params);
        }
        
        // Make request
        $response = wp_remote_request($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Parse response
        $data = json_decode($body, true);
        
        // Handle HTTP errors
        if ($status_code >= 400) {
            $error_message = __('API request failed', 'hostaway-wp');
            
            if (isset($data['message'])) {
                $error_message = $data['message'];
            } elseif (isset($data['error'])) {
                $error_message = $data['error'];
            }
            
            throw new Exception(sprintf(__('HTTP %d: %s', 'hostaway-wp'), $status_code, $error_message));
        }
        
        return $data;
    }
    
    /**
     * Get API credentials
     */
    public function getCredentials() {
        return [
            'api_key' => $this->api_key,
            'api_secret' => $this->api_secret,
        ];
    }
    
    /**
     * Set API credentials
     */
    public function setCredentials($api_key, $api_secret) {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
    }
    
    /**
     * Set base URL
     */
    public function setBaseUrl($url) {
        $this->base_url = rtrim($url, '/');
    }
    
    /**
     * Set timeout
     */
    public function setTimeout($timeout) {
        $this->timeout = $timeout;
    }
}
