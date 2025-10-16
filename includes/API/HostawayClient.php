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
    private $account_id;
    private $api_key;
    private $access_token;
    private $token_expires_at;
    
    /**
     * Request timeout
     */
    private $timeout = 30;
    
    /**
     * Constructor
     */
    public function __construct($account_id = null, $api_key = null) {
        $this->account_id = $account_id ?: get_option('hostaway_wp_account_id');
        $this->api_key = $api_key ?: get_option('hostaway_wp_api_key');
        
        // Load cached access token
        $this->access_token = get_transient('hostaway_wp_access_token');
        $this->token_expires_at = get_transient('hostaway_wp_token_expires_at');
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            // Get access token first
            $token_result = $this->getAccessToken();
            
            if (!$token_result['success']) {
                return [
                    'success' => false,
                    'message' => $token_result['message'],
                ];
            }
            
            // Test with a simple API call
            $response = $this->makeRequest('GET', '/listings', [], 1, 1);
            
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
     * Get access token using OAuth 2.0 Client Credentials Grant
     */
    private function getAccessToken() {
        // Check if we have a valid cached token
        if ($this->access_token && $this->token_expires_at && time() < $this->token_expires_at) {
            return ['success' => true, 'token' => $this->access_token];
        }
        
        if (!$this->account_id || !$this->api_key) {
            return [
                'success' => false,
                'message' => __('Account ID and API Key are required', 'hostaway-wp'),
            ];
        }
        
        $url = 'https://api.hostaway.com/v1/accessTokens';
        
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->account_id,
            'client_secret' => $this->api_key,
            'scope' => 'general',
        ];
        
        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query($data),
            'timeout' => $this->timeout,
        ];
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            error_log('Hostaway API Token Request Error: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            return [
                'success' => false,
                'message' => sprintf(__('Failed to get access token. HTTP %d: %s', 'hostaway-wp'), $status_code, $body),
            ];
        }
        
        $data = json_decode($body, true);
        
        if (!isset($data['access_token'])) {
            return [
                'success' => false,
                'message' => __('Invalid response from Hostaway API', 'hostaway-wp'),
            ];
        }
        
        // Cache the token
        $this->access_token = $data['access_token'];
        $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) : 3600;
        $this->token_expires_at = time() + $expires_in - 300; // 5 minutes buffer
        
        set_transient('hostaway_wp_access_token', $this->access_token, $expires_in - 300);
        set_transient('hostaway_wp_token_expires_at', $this->token_expires_at, $expires_in - 300);
        
        return ['success' => true, 'token' => $this->access_token];
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
        
        return $this->makeRequest('GET', "/listings/{$property_id}/calendar", $params);
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
        
        return $this->makeRequest('GET', "/listings/{$property_id}/calendar", $params);
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
    private function makeRequest($method, $endpoint, $params = [], $page = 1, $limit = 100) {
        // Ensure we have a valid access token
        $token_result = $this->getAccessToken();
        if (!$token_result['success']) {
            throw new Exception($token_result['message']);
        }
        
        $url = $this->base_url . $endpoint;
        
        // Add pagination parameters for GET requests
        if ($method === 'GET') {
            $params['page'] = $page;
            $params['limit'] = $limit;
        }
        
        // Prepare headers
        $headers = [
            'Authorization' => 'Bearer ' . $this->access_token,
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
            error_log('Hostaway API Request Error: ' . $response->get_error_message());
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
