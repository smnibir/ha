<?php
/**
 * Test plugin activation
 */

// Mock WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

// Mock WordPress functions
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback) {
        // Mock function
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        // Mock function
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
        // Mock function
    }
}

if (!function_exists('register_uninstall_hook')) {
    function register_uninstall_hook($file, $callback) {
        // Mock function
    }
}

if (!function_exists('class_exists')) {
    function class_exists($class) {
        return true;
    }
}

// Mock WordPress constants
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

echo "<h1>Testing Plugin Activation</h1>";

// Test autoloader
echo "<h2>Testing Autoloader</h2>";
$autoloader_path = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader_path)) {
    echo "<p style='color: green;'>✓ Autoloader file exists</p>";
    
    require_once $autoloader_path;
    echo "<p style='color: green;'>✓ Autoloader loaded</p>";
    
    // Test class loading
    $classes = [
        'HostawayWP\\Plugin',
        'HostawayWP\\Install\\Activator',
        'HostawayWP\\Install\\Deactivator',
        'HostawayWP\\Install\\Uninstaller',
        'HostawayWP\\API\\HostawayClient',
        'HostawayWP\\Models\\Property',
        'HostawayWP\\Models\\Rate',
        'HostawayWP\\Models\\Availability',
        'HostawayWP\\Sync\\Synchronizer',
        'HostawayWP\\Admin\\Admin',
        'HostawayWP\\Admin\\Settings',
        'HostawayWP\\Frontend\\Frontend',
        'HostawayWP\\Frontend\\Shortcodes',
        'HostawayWP\\Frontend\\Assets',
        'HostawayWP\\Checkout\\WooBridge',
        'HostawayWP\\Rest\\Endpoints',
    ];
    
    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo "<p style='color: green;'>✓ Class $class loaded</p>";
        } else {
            echo "<p style='color: red;'>✗ Class $class not found</p>";
        }
    }
} else {
    echo "<p style='color: red;'>✗ Autoloader not found</p>";
}

// Test main plugin file
echo "<h2>Testing Main Plugin File</h2>";
$plugin_file = __DIR__ . '/hostaway-wp.php';
if (file_exists($plugin_file)) {
    echo "<p style='color: green;'>✓ Main plugin file exists</p>";
    
    // Check for syntax errors
    $output = shell_exec("php -l '$plugin_file' 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
        echo "<p style='color: green;'>✓ Main plugin file syntax is valid</p>";
    } else {
        echo "<p style='color: red;'>✗ Main plugin file has syntax errors:</p>";
        echo "<pre>$output</pre>";
    }
} else {
    echo "<p style='color: red;'>✗ Main plugin file not found</p>";
}

// Test Activator class methods
echo "<h2>Testing Activator Class</h2>";
if (class_exists('HostawayWP\\Install\\Activator')) {
    echo "<p style='color: green;'>✓ Activator class exists</p>";
    
    $reflection = new ReflectionClass('HostawayWP\\Install\\Activator');
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC);
    
    foreach ($methods as $method) {
        echo "<p style='color: green;'>✓ Method {$method->getName()} exists</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Activator class not found</p>";
}

echo "<h2>Summary</h2>";
echo "<p><strong>Plugin structure test completed.</strong> If all items show green checkmarks, the plugin should activate without fatal errors.</p>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>Upload the plugin to your WordPress site</li>";
echo "<li>Activate the plugin in WordPress admin</li>";
echo "<li>Check the sync log for any errors</li>";
echo "<li>Configure API credentials</li>";
echo "</ol>";
?>
