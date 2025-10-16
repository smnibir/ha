# Hostaway Real-Time Sync Plugin

## Installation Instructions

1. **Upload Plugin Files**
   - Upload the entire `hostaway-real-time-sync` folder to `/wp-content/plugins/`
   - Ensure all files maintain proper directory structure

2. **Activate Plugin**
   - Go to WordPress Admin > Plugins
   - Find "Hostaway Real-Time Sync" and click "Activate"

3. **Install Dependencies**
   - Ensure WooCommerce is installed and activated
   - Install Stripe payment gateway for WooCommerce

4. **Configure API Credentials**
   - Go to Hostaway Sync > Settings
   - Enter your Hostaway API Key and Secret
   - Enter your Google Maps API Key
   - Test connections to verify setup

5. **Initial Sync**
   - Click "Sync Now" to perform initial data synchronization
   - Check Properties page to verify data is loaded

## Quick Setup Guide

### Step 1: API Configuration
```
Hostaway Account ID: [Your Account ID]
Hostaway API Key: [Your API Key]
Google Maps API Key: [Your Maps API Key]
```

### Step 2: Enable Auto-Sync
- Check "Enable automatic synchronization every 10 minutes"
- Set cache duration to 10 minutes
- Configure properties per page (default: 15)

### Step 3: Configure Amenities
- Click "Load Amenities from Hostaway"
- Select which amenities should appear in frontend filters
- Save settings

### Step 4: Test Frontend
- Create a page with `[hostaway_search]` shortcode
- Create a page with `[hostaway_properties]` shortcode
- Test search and booking functionality

## Usage Examples

### Homepage Search Widget
```php
[hostaway_search style="default" show_guests="true" show_dates="true"]
```

### Properties Listing Page
```php
[hostaway_properties per_page="15" show_map="true" show_filters="true"]
```

### Single Property Page
```php
[hostaway_property property_id="123"]
```

## Troubleshooting

### Common Issues

1. **Properties Not Syncing**
   - Check API credentials
   - Verify WP-Cron is working
   - Check sync logs in admin

2. **Maps Not Loading**
   - Verify Google Maps API key
   - Check browser console for errors
   - Ensure Maps JavaScript API is enabled

3. **Booking Not Working**
   - Ensure WooCommerce is active
   - Check Stripe configuration
   - Verify Hostaway reservation creation

### Debug Mode
Add to wp-config.php:
```php
define('HOSTAWAY_SYNC_DEBUG', true);
```

## Support

For technical support:
- Check plugin logs in Hostaway Sync > Logs
- Review WordPress error logs
- Contact plugin developer

## Version Information
- Plugin Version: 1.0.0
- WordPress Required: 5.0+
- PHP Required: 7.4+
- WooCommerce Required: 5.0+
