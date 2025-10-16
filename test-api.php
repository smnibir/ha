<?php
/**
 * Hostaway API Test Script
 * 
 * This script helps test the Hostaway API connection outside of WordPress
 * Run this from command line: php test-api.php
 */

// Include WordPress
require_once('../../../wp-config.php');

// Include the plugin
require_once('includes/API/HostawayClient.php');

use HostawayWP\API\HostawayClient;

echo "=== Hostaway API Test ===\n\n";

// Get credentials from WordPress options
$account_id = get_option('hostaway_wp_account_id');
$api_key = get_option('hostaway_wp_api_key');

if (empty($account_id) || empty($api_key)) {
    echo "❌ Error: Account ID or API Key not configured in WordPress\n";
    echo "Please set these in WordPress Admin > Hostaway > Settings\n\n";
    exit(1);
}

echo "Account ID: " . substr($account_id, 0, 8) . "...\n";
echo "API Key: " . substr($api_key, 0, 8) . "...\n\n";

try {
    // Test API client
    $client = new HostawayClient($account_id, $api_key);
    
    echo "1. Testing API connection...\n";
    $test_result = $client->testConnection();
    
    if ($test_result['success']) {
        echo "✅ " . $test_result['message'] . "\n\n";
        
        echo "2. Fetching properties...\n";
        $properties = $client->getProperties(1, 5); // Get first 5 properties
        
        if ($properties && isset($properties['data'])) {
            echo "✅ Found " . count($properties['data']) . " properties\n";
            
            if (!empty($properties['data'])) {
                $first_property = $properties['data'][0];
                echo "   First property: " . ($first_property['name'] ?? 'Unknown') . "\n";
                echo "   Property ID: " . ($first_property['id'] ?? 'Unknown') . "\n";
            }
        } else {
            echo "❌ No properties found or invalid response\n";
            echo "Response: " . print_r($properties, true) . "\n";
        }
        
    } else {
        echo "❌ " . $test_result['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
