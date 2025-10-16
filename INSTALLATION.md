# Hostaway WP Plugin - Installation Guide

## Quick Start

1. **Upload Plugin**
   - Upload the entire `hostaway-wp` folder to `/wp-content/plugins/`
   - Ensure all files are uploaded correctly

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Hostaway WP Rentals" and click "Activate"
   - Or run `/wp-content/plugins/hostaway-wp/install.php` for automated setup

3. **Configure Settings**
   - Navigate to **Hostaway → Settings**
   - Enter your Hostaway API Key and Secret
   - Add Google Maps API Key
   - Test connections and run initial sync

4. **Set Up WooCommerce**
   - Ensure WooCommerce is installed and active
   - Configure Stripe payment gateway
   - Test booking flow

## Detailed Installation

### 1. Prerequisites

- WordPress 5.0+
- PHP 7.4+
- WooCommerce 5.0+
- MySQL 5.6+

### 2. File Upload

Upload the plugin files to your WordPress installation:

```
/wp-content/plugins/hostaway-wp/
├── hostaway-wp.php
├── includes/
├── assets/
├── vendor/
└── README.md
```

### 3. Plugin Activation

**Method 1: WordPress Admin**
1. Go to Plugins → Installed Plugins
2. Find "Hostaway WP Rentals"
3. Click "Activate"

**Method 2: Installation Script**
1. Navigate to `/wp-content/plugins/hostaway-wp/install.php`
2. Follow the on-screen instructions

### 4. Database Setup

The plugin automatically creates the following tables:
- `wp_hostaway_properties` - Property data
- `wp_hostaway_rates` - Pricing information
- `wp_hostaway_availability` - Booking availability
- `wp_hostaway_sync_log` - Sync history

### 5. Page Creation

The plugin creates these pages automatically:
- **Properties** (`/properties/`) - Main listing page
- **Search** (`/search/`) - Search form page

### 6. API Configuration

#### Hostaway API Setup
1. Get API credentials from Hostaway dashboard
2. Go to Hostaway → Settings
3. Enter API Key and Secret
4. Click "Test Connection"

#### Google Maps Setup
1. Create Google Cloud project
2. Enable Maps JavaScript API and Places API
3. Create API key with proper restrictions
4. Enter API key in plugin settings

### 7. WooCommerce Integration

#### Stripe Setup
1. Install WooCommerce Stripe Gateway plugin
2. Configure Stripe API keys
3. Enable Stripe for payments
4. Test payment processing

#### Product Types
The plugin creates a custom "Hostaway Property" product type for bookings.

### 8. Initial Sync

1. Go to Hostaway → Settings
2. Click "Sync Now" to import properties
3. Monitor sync progress in Sync Log
4. Verify properties appear in Properties list

## Configuration Options

### General Settings
- **Currency**: USD, EUR, GBP, CAD, AUD
- **Locale**: Date/number formatting
- **Timezone**: Property timezone
- **Sync Interval**: 15min, hourly, or 6-hourly

### Display Settings
- **Properties per Page**: 15 (default)
- **Enable Map**: Show/hide map functionality
- **Enable Filters**: Show/hide filter sidebar
- **Enable Instant Booking**: Allow direct bookings

### API Settings
- **Hostaway API Key**: Your Hostaway API key
- **Hostaway API Secret**: Your Hostaway API secret
- **Google Maps API Key**: For map functionality

## Usage

### Shortcodes

Add these shortcodes to your pages:

```php
// Search form
[hostaway_search]

// Properties listing
[hostaway_properties]

// Single property
[hostaway_property id="123"]
```

### URL Structure

- Properties: `/properties/`
- Single Property: `/properties/property-slug/`
- Search: `/properties/?location=miami&checkin=2025-10-20&checkout=2025-10-25&adults=2`

### Admin Features

#### Property Management
- View all synced properties
- Individual property sync
- Property details and images
- Sync status monitoring

#### Sync Management
- Manual sync trigger
- Sync interval configuration
- Comprehensive sync logging
- Error tracking and debugging

## Troubleshooting

### Common Issues

**API Connection Failed**
- Verify API credentials
- Check API key permissions
- Ensure network connectivity

**Properties Not Syncing**
- Check sync log for errors
- Verify cron jobs are running
- Test manual sync

**Maps Not Loading**
- Verify Google Maps API key
- Check API restrictions
- Ensure required APIs are enabled

**Booking Issues**
- Verify WooCommerce is active
- Check Stripe configuration
- Review order creation process

### Debug Mode

Enable WordPress debug mode:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Support

1. Check sync log for error details
2. Review WordPress error logs
3. Verify API credentials and permissions
4. Test with minimal configuration

## Performance Tips

### Optimization
- Enable caching plugins
- Optimize database queries
- Use CDN for images
- Enable lazy loading

### Monitoring
- Monitor sync performance
- Track API usage
- Review error logs
- Check database size

## Security

### Best Practices
- Keep API keys secure
- Use HTTPS for all connections
- Regular plugin updates
- Monitor access logs

### Data Protection
- Secure credential storage
- Input sanitization
- Output escaping
- Nonce verification

## Maintenance

### Regular Tasks
- Monitor sync status
- Review error logs
- Update API keys if needed
- Backup database regularly

### Updates
- Keep WordPress core updated
- Update WooCommerce regularly
- Monitor plugin compatibility
- Test after updates

## Support Resources

- Plugin documentation: README.md
- WordPress codex: WordPress.org
- WooCommerce docs: WooCommerce.com
- Hostaway API docs: Hostaway.com

## Next Steps

After installation:
1. Configure API credentials
2. Run initial property sync
3. Set up WooCommerce Stripe
4. Test booking flow
5. Customize styling if needed
6. Add shortcodes to pages
7. Configure email notifications
8. Set up monitoring

Your Hostaway WP plugin is now ready to use!
