# Hostaway WP Rentals Plugin

A comprehensive WordPress plugin that integrates Hostaway property management with WordPress, featuring real-time synchronization, advanced search and filtering, Google Maps integration, and WooCommerce booking system.

## Features

### 🔄 Real-Time Synchronization
- **10-minute sync intervals** for near real-time accuracy
- Automatic property, rates, and availability updates
- Manual sync option with admin controls
- Comprehensive sync logging and monitoring

### 🏠 Property Management
- Custom database tables for optimal performance
- WordPress CPT integration for SEO
- Automatic image downloading and media library integration
- Property categorization and amenity management

### 🔍 Advanced Search & Filtering
- Location-based search with autocomplete
- Date range selection with availability checking
- Guest capacity filtering (adults, children, infants)
- Amenity-based filtering
- Price range filtering
- Room and bathroom filtering

### 🗺️ Interactive Maps
- Google Maps integration with custom markers
- Property hover cards with images and pricing
- Map/list view toggle
- Responsive map design

### 💳 WooCommerce Integration
- Seamless booking flow through WooCommerce
- Stripe payment processing
- Automatic order creation and management
- Hostaway reservation synchronization
- Email notifications with booking details

### 📱 Responsive Design
- Mobile-first responsive layout
- Touch-friendly interface
- Optimized for all screen sizes
- Modern, clean UI matching yacht rental aesthetics

## Installation

1. **Upload Plugin Files**
   ```bash
   # Upload the entire plugin folder to:
   /wp-content/plugins/hostaway-wp/
   ```

2. **Install Dependencies**
   ```bash
   cd /wp-content/plugins/hostaway-wp/
   composer install
   ```

3. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Activate "Hostaway WP Rentals"

4. **Configure Settings**
   - Navigate to Hostaway → Settings
   - Enter your Hostaway API credentials
   - Configure Google Maps API key
   - Set currency and locale preferences

## Configuration

### Required API Keys

1. **Hostaway API**
   - Get your API key and secret from Hostaway dashboard
   - Enter in Hostaway → Settings

2. **Google Maps API**
   - Enable Maps JavaScript API
   - Enable Places API (for location autocomplete)
   - Enter API key in plugin settings

### WooCommerce Setup

1. **Install WooCommerce**
   - Plugin requires WooCommerce to be installed and activated

2. **Configure Stripe**
   - Install WooCommerce Stripe Gateway
   - Configure your Stripe API keys
   - Enable Stripe for payments

3. **Create Pages**
   - The plugin automatically creates "Properties" and "Search" pages
   - Customize these pages as needed

## Usage

### Shortcodes

#### Search Form
```
[hostaway_search]
```
Displays a property search form with location, dates, and guest selection.

#### Properties Listing
```
[hostaway_properties]
```
Shows the complete properties listing page with search, filters, and map.

#### Single Property
```
[hostaway_property id="123"]
```
Displays a single property (optional, uses post template by default).

### URL Structure

- **Properties Page**: `/properties/`
- **Single Property**: `/properties/property-slug/`
- **Search with Parameters**: `/properties/?location=miami&checkin=2025-10-20&checkout=2025-10-25&adults=2`

### Admin Features

#### Settings Page
- API configuration and testing
- Sync interval management
- Currency and locale settings
- Map and filter options

#### Property Management
- View all synced properties
- Individual property sync
- Property details and images
- Sync status monitoring

#### Sync Log
- Complete sync history
- Error tracking and debugging
- Performance monitoring

## API Endpoints

The plugin provides REST API endpoints for frontend functionality:

- `GET /wp-json/hostaway/v1/search` - Search properties
- `GET /wp-json/hostaway/v1/filters` - Get available filters
- `GET /wp-json/hostaway/v1/properties` - Get properties
- `GET /wp-json/hostaway/v1/availability/{id}` - Get property availability
- `POST /wp-json/hostaway/v1/calculate-price` - Calculate booking price

## Database Structure

### Custom Tables

#### wp_hostaway_properties
- Property details and metadata
- Location coordinates
- Amenities and features
- Gallery images

#### wp_hostaway_rates
- Daily pricing information
- Minimum stay requirements
- Guest capacity limits
- Currency information

#### wp_hostaway_availability
- Booking availability
- Date-specific availability
- Real-time updates

#### wp_hostaway_sync_log
- Sync operation history
- Error tracking
- Performance metrics

## Customization

### Styling
- CSS variables for easy theming
- Responsive design system
- Customizable color scheme
- Override templates in theme

### Templates
Templates can be overridden by copying to your theme:
```
your-theme/hostaway-wp/
├── search-form.php
├── properties-listing.php
├── property-tile.php
├── single-property.php
└── booking-widget.php
```

### Hooks and Filters

#### Actions
- `hostaway_wp_before_property_display` - Before property rendering
- `hostaway_wp_after_property_display` - After property rendering
- `hostaway_wp_before_booking_form` - Before booking form
- `hostaway_wp_after_booking_form` - After booking form

#### Filters
- `hostaway_wp_property_data` - Modify property data
- `hostaway_wp_search_params` - Modify search parameters
- `hostaway_wp_booking_total` - Modify booking total calculation
- `hostaway_wp_email_content` - Modify email content

## Troubleshooting

### Common Issues

1. **API Connection Failed**
   - Verify API credentials in settings
   - Check API key permissions
   - Ensure proper network connectivity

2. **Properties Not Syncing**
   - Check sync log for errors
   - Verify cron jobs are running
   - Test manual sync functionality

3. **Maps Not Loading**
   - Verify Google Maps API key
   - Check API key restrictions
   - Ensure required APIs are enabled

4. **Booking Issues**
   - Verify WooCommerce is active
   - Check Stripe configuration
   - Review order creation process

### Debug Mode

Enable WordPress debug mode for detailed error logging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Support

For technical support and documentation:
- Check the sync log for error details
- Review WordPress error logs
- Verify API credentials and permissions
- Test with minimal configuration

## Performance Optimization

### Caching
- Transient caching for API responses
- Database query optimization
- Image lazy loading
- Map marker clustering

### Database
- Indexed custom tables
- Optimized queries
- Pagination support
- Efficient data structures

## Security

### Data Protection
- Prepared SQL statements
- Input sanitization
- Output escaping
- Nonce verification
- Capability checks

### API Security
- Secure credential storage
- HTTPS enforcement
- Request validation
- Error handling

## Changelog

### Version 1.0.0
- Initial release
- Real-time synchronization
- Advanced search and filtering
- Google Maps integration
- WooCommerce booking system
- Responsive design
- Admin management interface

## License

GPL-2.0-or-later

## Requirements

- WordPress 5.0+
- PHP 7.4+
- WooCommerce 5.0+
- MySQL 5.6+

## Credits

Developed for Hostaway integration with WordPress and WooCommerce.
