# Hostaway WP Plugin - Troubleshooting Guide

## Plugin Activation Issues

If you're getting a "Plugin could not be activated because it triggered a fatal error" message, follow these steps:

### Step 1: Check WordPress Error Logs

1. Enable WordPress debug mode by adding these lines to your `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

2. Check the error log at `/wp-content/debug.log`

### Step 2: Manual Activation

If the plugin still won't activate, try manual activation:

1. Upload the plugin files to `/wp-content/plugins/hostaway-wp/`
2. Navigate to `/wp-content/plugins/hostaway-wp/manual-activation.php` in your browser
3. Follow the on-screen instructions to manually set up the plugin

### Step 3: Use Safe Plugin File

If you continue to have issues, replace the main plugin file:

1. Rename `hostaway-wp.php` to `hostaway-wp-backup.php`
2. Rename `hostaway-wp-safe.php` to `hostaway-wp.php`
3. Try activating the plugin again

### Step 4: Check Requirements

Ensure your WordPress installation meets the requirements:

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **WooCommerce**: 5.0 or higher (must be installed and activated)

### Step 5: File Permissions

Check that the plugin files have correct permissions:

```bash
# Set correct permissions
chmod 755 /wp-content/plugins/hostaway-wp/
chmod 644 /wp-content/plugins/hostaway-wp/*.php
chmod 644 /wp-content/plugins/hostaway-wp/includes/*.php
chmod 644 /wp-content/plugins/hostaway-wp/assets/css/*.css
chmod 644 /wp-content/plugins/hostaway-wp/assets/js/*.js
```

### Step 6: Database Issues

If you're having database-related issues:

1. Check that your database user has CREATE TABLE permissions
2. Ensure the database charset is UTF-8
3. Check for any existing tables with conflicting names

### Step 7: Plugin Conflicts

Test for plugin conflicts:

1. Deactivate all other plugins
2. Try activating Hostaway WP
3. If it works, reactivate plugins one by one to find the conflict

### Step 8: Theme Conflicts

Test for theme conflicts:

1. Switch to a default WordPress theme (Twenty Twenty-Three)
2. Try activating the plugin
3. If it works, the issue is with your theme

## Common Error Messages and Solutions

### "Class not found" errors

**Solution**: The autoloader isn't working properly
1. Check that `vendor/autoload.php` exists
2. Verify file permissions
3. Try manual activation

### "Call to undefined function" errors

**Solution**: WordPress functions aren't available
1. Ensure the plugin is being loaded in WordPress context
2. Check that WordPress is properly loaded
3. Verify plugin file structure

### Database errors

**Solution**: Database permission or structure issues
1. Check database user permissions
2. Verify database charset and collation
3. Run manual activation script

### Memory limit errors

**Solution**: Increase PHP memory limit
1. Add to `wp-config.php`: `ini_set('memory_limit', '256M');`
2. Or increase in `php.ini`: `memory_limit = 256M`

### Timeout errors

**Solution**: Increase PHP execution time
1. Add to `wp-config.php`: `set_time_limit(300);`
2. Or increase in `php.ini`: `max_execution_time = 300`

## Getting Help

If you're still having issues:

1. **Check the error logs** - Look for specific error messages
2. **Run the test script** - Use `test-plugin.php` to verify file structure
3. **Use manual activation** - Use `manual-activation.php` to set up manually
4. **Check WordPress compatibility** - Ensure your WordPress version is supported

## Plugin Structure Verification

Run this command to verify all files are present:

```bash
cd /wp-content/plugins/hostaway-wp/
php test-plugin.php
```

All files should show green checkmarks. If any show red X's, those files are missing or corrupted.

## Manual Database Setup

If automatic table creation fails, you can create the tables manually:

```sql
-- Properties table
CREATE TABLE wp_hostaway_properties (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    hostaway_id varchar(100) NOT NULL,
    title varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    type varchar(100) NOT NULL,
    country varchar(100) DEFAULT NULL,
    city varchar(100) DEFAULT NULL,
    address text DEFAULT NULL,
    latitude decimal(10,8) DEFAULT NULL,
    longitude decimal(11,8) DEFAULT NULL,
    rooms int(11) DEFAULT 0,
    bathrooms int(11) DEFAULT 0,
    guests int(11) DEFAULT 0,
    base_price decimal(10,2) DEFAULT 0.00,
    thumbnail_url varchar(500) DEFAULT NULL,
    thumbnail_id bigint(20) DEFAULT NULL,
    gallery_json longtext DEFAULT NULL,
    amenities_json longtext DEFAULT NULL,
    features_json longtext DEFAULT NULL,
    description longtext DEFAULT NULL,
    status varchar(20) DEFAULT 'active',
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY hostaway_id (hostaway_id),
    KEY slug (slug),
    KEY type (type),
    KEY location (country, city),
    KEY coordinates (latitude, longitude),
    KEY status (status)
);

-- Rates table
CREATE TABLE wp_hostaway_rates (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    property_id bigint(20) NOT NULL,
    date date NOT NULL,
    price decimal(10,2) NOT NULL,
    min_nights int(11) DEFAULT 1,
    max_guests int(11) DEFAULT NULL,
    currency varchar(3) DEFAULT 'USD',
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY property_date (property_id, date),
    KEY property_id (property_id),
    KEY date (date),
    KEY price (price)
);

-- Availability table
CREATE TABLE wp_hostaway_availability (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    property_id bigint(20) NOT NULL,
    date date NOT NULL,
    is_booked tinyint(1) DEFAULT 0,
    is_available tinyint(1) DEFAULT 1,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY property_date (property_id, date),
    KEY property_id (property_id),
    KEY date (date),
    KEY is_booked (is_booked),
    KEY is_available (is_available)
);

-- Sync log table
CREATE TABLE wp_hostaway_sync_log (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    action varchar(50) NOT NULL,
    status varchar(20) NOT NULL,
    message text DEFAULT NULL,
    data longtext DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY action (action),
    KEY status (status),
    KEY created_at (created_at)
);
```

## Success Indicators

Once the plugin is working correctly, you should see:

1. ✅ Plugin activates without errors
2. ✅ Database tables are created
3. ✅ Properties and Search pages are created
4. ✅ Hostaway menu appears in WordPress admin
5. ✅ Settings page loads without errors
6. ✅ Shortcodes work on frontend

If all these indicators are present, your plugin is successfully installed and ready for configuration!
