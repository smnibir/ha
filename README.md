# Hostaway Real-Time Sync WordPress Plugin

A comprehensive WordPress plugin that synchronizes property data from Hostaway.com in real-time and integrates with WooCommerce for seamless booking and payment processing.

## Features

### 🔄 Real-Time Synchronization
- Automatic property sync every 10 minutes via WP-Cron
- Manual sync capability with admin controls
- Intelligent change detection (only updates modified data)
- Comprehensive error logging and status monitoring
- Configurable sync frequency and cache duration

### 🏠 Property Management
- Complete property information sync (details, images, amenities, rates)
- Availability calendar synchronization
- Property categorization and filtering
- High-performance database storage with optimized queries
- Direct image loading from Hostaway CDN (no local storage)

### 🔍 Advanced Search & Filtering
- Location-based search with city dropdown
- Date range and guest count filtering
- Amenity-based filtering (configurable by admin)
- Property type, room count, and price range filters
- Real-time search results with AJAX

### 🗺️ Interactive Maps
- Google Maps integration with property markers
- Map/list view toggle
- Property information on hover
- Single property location mapping
- Responsive map design

### 🛒 WooCommerce Integration
- Seamless booking flow through WooCommerce
- Stripe payment processing
- Automatic Hostaway reservation creation
- Order management with booking details
- Reservation status tracking
- Booking confirmation emails

### 📱 Responsive Design
- Mobile-first responsive layout
- Yacht-inspired modern design
- Touch-friendly interface
- Optimized for all device sizes
- Fast loading with lazy image loading

## Installation

1. Upload the plugin files to `/wp-content/plugins/hostaway-real-time-sync/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Install and activate WooCommerce (required for booking functionality)
4. Configure your API credentials in the admin settings

## Configuration

### API Setup

1. **Hostaway API Credentials**
   - Log in to your Hostaway account
   - Navigate to Settings > Hostaway API
   - Click "Create" to generate a new API Key
   - Copy your Account ID and API Key
   - Enter credentials in `Hostaway Sync > Settings`
   - Test connection to verify setup

2. **Google Maps API Key**
   - Create a Google Maps API key in Google Cloud Console
   - Enable Maps JavaScript API and Places API
   - Enter API key in plugin settings

### Admin Configuration

Navigate to `Hostaway Sync > Settings` to configure:

- **API Configuration**: Hostaway and Google Maps credentials
- **Sync Settings**: Auto-sync toggle, manual sync, cache management
- **Amenity Filter**: Select which amenities appear in frontend filters
- **Display Settings**: Properties per page, cache duration

## Shortcodes

### Search Widget
```
[hostaway_search]
```
Displays a search form with location, dates, and guest selection.

**Parameters:**
- `style`: Search form style (default, compact)
- `show_guests`: Show guest selection (true/false)
- `show_dates`: Show date selection (true/false)

### Properties Grid
```
[hostaway_properties]
```
Displays the main properties listing page with filters and map.

**Parameters:**
- `per_page`: Number of properties per page (default: 15)
- `show_map`: Enable map functionality (true/false)
- `show_filters`: Enable filter sidebar (true/false)

### Single Property
```
[hostaway_property property_id="123"]
```
Displays a single property page with booking form.

## Database Schema

The plugin creates the following database tables:

- `wp_hostaway_properties`: Property information and details
- `wp_hostaway_rates`: Property rates and pricing
- `wp_hostaway_availability`: Availability calendar data
- `wp_hostaway_reservations`: Booking and reservation tracking
- `wp_hostaway_sync_log`: Synchronization activity logs

## API Integration

### Hostaway API Endpoints Used

- `/listings`: Fetch property listings
- `/listings/{id}`: Get individual property details
- `/listings/{id}/calendarPricing`: Property rates
- `/listings/{id}/calendar`: Availability calendar
- `/reservations`: Create and manage reservations
- `/accessTokens`: OAuth authentication

### Data Synchronization

The plugin synchronizes the following data types:

1. **Properties**: Basic information, descriptions, location data
2. **Rates**: Dynamic pricing with date ranges
3. **Availability**: Calendar availability with booking rules
4. **Amenities**: Property features and facilities
5. **Images**: Property photos (loaded directly from Hostaway)

## Booking Flow

1. **Search**: User searches for properties with filters
2. **Selection**: User views property details and availability
3. **Booking**: User fills booking form with dates and guests
4. **Payment**: WooCommerce processes payment via Stripe
5. **Reservation**: Plugin creates reservation in Hostaway
6. **Confirmation**: User receives booking confirmation

## Customization

### Styling
- Modify `/assets/css/frontend.css` for frontend styling
- Modify `/assets/css/admin.css` for admin styling
- Use WordPress customizer for theme integration

### Functionality
- Extend classes in `/includes/` directory
- Hook into plugin actions and filters
- Customize shortcode output with template overrides

### Hooks and Filters

**Actions:**
- `hostaway_sync_before_property_update`: Before property sync
- `hostaway_sync_after_property_update`: After property sync
- `hostaway_booking_before_process`: Before booking processing
- `hostaway_booking_after_process`: After booking processing

**Filters:**
- `hostaway_property_search_args`: Modify search parameters
- `hostaway_property_display_data`: Customize property display
- `hostaway_booking_form_fields`: Modify booking form fields

## Performance Optimization

- **Caching**: Transient-based caching for API responses
- **Database**: Optimized queries with proper indexing
- **Images**: Lazy loading and CDN usage
- **AJAX**: Asynchronous data loading
- **Compression**: Minified CSS and JavaScript

## Security Features

- **Nonces**: CSRF protection for all forms
- **Sanitization**: Input validation and output escaping
- **Capabilities**: User permission checks
- **SQL Injection**: Prepared statements for all queries
- **API Security**: Secure credential storage

## Troubleshooting

### Common Issues

1. **Sync Not Working**
   - Check API credentials
   - Verify WP-Cron is running
   - Check sync logs in admin

2. **Maps Not Loading**
   - Verify Google Maps API key
   - Check API restrictions
   - Enable required APIs

3. **Booking Issues**
   - Ensure WooCommerce is active
   - Check Stripe configuration
   - Verify Hostaway reservation creation

### Debug Mode

Enable debug mode by adding to `wp-config.php`:
```php
define('HOSTAWAY_SYNC_DEBUG', true);
```

### Log Files

Check the following for detailed logs:
- WordPress error log
- Plugin sync logs (Admin > Hostaway Sync > Logs)
- WooCommerce order notes

## Support

For support and documentation:
- Plugin documentation: [Link to documentation]
- Hostaway API docs: https://api.hostaway.com/documentation
- WooCommerce integration: [WooCommerce docs]

## Changelog

### Version 1.0.0
- Initial release
- Real-time property synchronization
- WooCommerce booking integration
- Google Maps integration
- Responsive design
- Admin management interface

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- WooCommerce 5.0 or higher
- MySQL 5.6 or higher
- Hostaway API access
- Google Maps API key

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed for seamless integration between Hostaway property management and WordPress/WooCommerce booking systems.
