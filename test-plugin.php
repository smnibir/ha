<?php
/**
 * Plugin structure test
 * Run this to verify all files are in place
 */

$plugin_dir = __DIR__;
$required_files = [
    'hostaway-wp.php',
    'composer.json',
    'includes/Plugin.php',
    'includes/Install/Activator.php',
    'includes/Install/Deactivator.php',
    'includes/Install/Uninstaller.php',
    'includes/API/HostawayClient.php',
    'includes/Models/Property.php',
    'includes/Models/Rate.php',
    'includes/Models/Availability.php',
    'includes/Sync/Synchronizer.php',
    'includes/Admin/Admin.php',
    'includes/Admin/Settings.php',
    'includes/Frontend/Frontend.php',
    'includes/Frontend/Shortcodes.php',
    'includes/Frontend/Assets.php',
    'includes/Checkout/WooBridge.php',
    'includes/Rest/Endpoints.php',
    'assets/css/frontend.css',
    'assets/css/admin.css',
    'assets/js/frontend.js',
    'assets/js/admin.js',
    'vendor/autoload.php',
    'README.md',
];

echo "<h1>Hostaway WP Plugin Structure Test</h1>";

$missing_files = [];
$existing_files = [];

foreach ($required_files as $file) {
    $file_path = $plugin_dir . '/' . $file;
    if (file_exists($file_path)) {
        $existing_files[] = $file;
        echo "<p style='color: green;'>✓ $file</p>";
    } else {
        $missing_files[] = $file;
        echo "<p style='color: red;'>✗ $file</p>";
    }
}

echo "<h2>Summary</h2>";
echo "<p><strong>Existing files:</strong> " . count($existing_files) . "/" . count($required_files) . "</p>";

if (!empty($missing_files)) {
    echo "<p style='color: red;'><strong>Missing files:</strong></p>";
    echo "<ul>";
    foreach ($missing_files as $file) {
        echo "<li style='color: red;'>$file</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: green;'><strong>All required files are present!</strong></p>";
}

// Test autoloader
echo "<h2>Autoloader Test</h2>";
if (file_exists($plugin_dir . '/vendor/autoload.php')) {
    require_once $plugin_dir . '/vendor/autoload.php';
    echo "<p style='color: green;'>✓ Autoloader loaded</p>";
    
    // Test class loading
    $test_classes = [
        'HostawayWP\\Plugin',
        'HostawayWP\\Install\\Activator',
        'HostawayWP\\API\\HostawayClient',
        'HostawayWP\\Models\\Property',
        'HostawayWP\\Admin\\Settings',
        'HostawayWP\\Frontend\\Shortcodes',
    ];
    
    foreach ($test_classes as $class) {
        if (class_exists($class)) {
            echo "<p style='color: green;'>✓ Class $class loaded</p>";
        } else {
            echo "<p style='color: red;'>✗ Class $class not found</p>";
        }
    }
} else {
    echo "<p style='color: red;'>✗ Autoloader not found</p>";
}

echo "<h2>Directory Structure</h2>";
echo "<pre>";
function printDirectory($dir, $prefix = '') {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            echo $prefix . "📁 $item/\n";
            printDirectory($path, $prefix . '  ');
        } else {
            echo $prefix . "📄 $item\n";
        }
    }
}

printDirectory($plugin_dir);
echo "</pre>";

echo "<p style='margin-top: 30px;'><strong>Plugin structure test complete!</strong></p>";
?>
