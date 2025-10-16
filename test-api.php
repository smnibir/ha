<?php
/**
 * Simple API test script for debugging Hostaway API issues
 * 
 * Place this file in your WordPress root directory and access it via browser
 * to test the API connection directly.
 */

// Load WordPress
require_once('wp-config.php');

// Simple test function
function test_hostaway_api() {
    echo "<h1>Hostaway API Test</h1>\n";
    
    // Get credentials
    $account_id = get_option('hostaway_sync_hostaway_account_id', '');
    $api_key = get_option('hostaway_sync_hostaway_api_key', '');
    
    echo "<h2>Configuration</h2>\n";
    echo "Account ID: " . ($account_id ? '✅ Set' : '❌ Missing') . "<br>\n";
    echo "API Key: " . ($api_key ? '✅ Set' : '❌ Missing') . "<br>\n";
    
    if (empty($account_id) || empty($api_key)) {
        echo "<p style='color: red;'>❌ Missing credentials. Please configure in WordPress admin.</p>\n";
        return;
    }
    
    echo "<h2>Step 1: Getting Access Token</h2>\n";
    
    // Test token request
    $token_url = 'https://api.hostaway.com/v1/accessTokens';
    $token_data = array(
        'grant_type' => 'client_credentials',
        'client_id' => $account_id,
        'client_secret' => $api_key,
        'scope' => 'general'
    );
    
    $token_response = wp_remote_post($token_url, array(
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json'
        ),
        'body' => http_build_query($token_data),
        'timeout' => 30
    ));
    
    if (is_wp_error($token_response)) {
        echo "<p style='color: red;'>❌ Token request failed: " . $token_response->get_error_message() . "</p>\n";
        return;
    }
    
    $token_status = wp_remote_retrieve_response_code($token_response);
    $token_body = wp_remote_retrieve_body($token_response);
    
    echo "Token Status: " . $token_status . "<br>\n";
    echo "Token Response: <pre>" . htmlspecialchars($token_body) . "</pre>\n";
    
    if ($token_status !== 200) {
        echo "<p style='color: red;'>❌ Token request failed with status: " . $token_status . "</p>\n";
        return;
    }
    
    $token_data = json_decode($token_body, true);
    if (!isset($token_data['access_token'])) {
        echo "<p style='color: red;'>❌ No access token in response</p>\n";
        return;
    }
    
    $access_token = $token_data['access_token'];
    echo "<p style='color: green;'>✅ Access token received: " . substr($access_token, 0, 20) . "...</p>\n";
    
    echo "<h2>Step 2: Testing Listings Endpoint</h2>\n";
    
    // Test listings request
    $listings_url = 'https://api.hostaway.com/v1/listings';
    $listings_response = wp_remote_get($listings_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($listings_response)) {
        echo "<p style='color: red;'>❌ Listings request failed: " . $listings_response->get_error_message() . "</p>\n";
        return;
    }
    
    $listings_status = wp_remote_retrieve_response_code($listings_response);
    $listings_body = wp_remote_retrieve_body($listings_response);
    
    echo "Listings Status: " . $listings_status . "<br>\n";
    echo "Listings Response: <pre>" . htmlspecialchars($listings_body) . "</pre>\n";
    
    if ($listings_status === 200) {
        $listings_data = json_decode($listings_body, true);
        if (is_array($listings_data)) {
            echo "<p style='color: green;'>✅ API connection successful!</p>\n";
            echo "Response keys: " . implode(', ', array_keys($listings_data)) . "<br>\n";
            
            if (isset($listings_data['data'])) {
                echo "Number of listings: " . count($listings_data['data']) . "<br>\n";
            } elseif (isset($listings_data['result'])) {
                echo "Number of listings: " . count($listings_data['result']) . "<br>\n";
            }
        }
    } else {
        echo "<p style='color: red;'>❌ Listings request failed with status: " . $listings_status . "</p>\n";
    }
}

// Run the test
test_hostaway_api();
?>
