/**
 * Hostaway Real-Time Sync - Frontend JavaScript
 */

(function($) {
    'use strict';

    // Global variables
    let map = null;
    let propertyMarkers = [];
    let locationSuggestions = [];
    let debounceTimer = null;

    // Initialize when document is ready
    $(document).ready(function() {
        initializeSearchWidget();
        initializePropertiesPage();
        initializeSingleProperty();
        initializeMaps();
    });

    /**
     * Initialize search widget functionality
     */
    function initializeSearchWidget() {
        // Date validation
        $('#checkin, #checkout').on('change', function() {
            validateDates();
            updateSearchForm();
        });

        // Guest count and location change
        $('#guests, #location').on('change', function() {
            updateSearchForm();
        });
    }

    /**
     * Initialize properties page functionality
     */
    function initializePropertiesPage() {
        // Toggle filters sidebar
        $('.toggle-filters').on('click', function() {
            $('.filters-sidebar').toggleClass('active');
            $(this).text($('.filters-sidebar').hasClass('active') ? 
                hostawayFrontend.strings.hideFilters || 'Hide Filters' : 
                hostawayFrontend.strings.showFilters || 'Show Filters'
            );
        });

        // Toggle map
        $('.toggle-map').on('click', function() {
            const $mapContainer = $('.properties-map');
            const $mainContent = $('.properties-main');
            
            if ($mapContainer.is(':visible')) {
                $mapContainer.hide();
                $mainContent.removeClass('with-map');
                $(this).find('.map-text').text(hostawayFrontend.strings.showMap || 'Show Map');
            } else {
                $mapContainer.show();
                $mainContent.addClass('with-map');
                $(this).find('.map-text').text(hostawayFrontend.strings.hideMap || 'Hide Map');
                initializePropertiesMap();
            }
        });

        // Apply filters
        $('.apply-filters').on('click', function() {
            applyFilters();
        });

        // Clear filters
        $('.clear-filters, .reset-filters').on('click', function() {
            clearFilters();
        });

        // Filter change handlers
        $('.filter-select, .amenity-checkbox input, .price-range input').on('change', function() {
            debounceFilter();
        });

        // Property card interactions
        $('.property-card').on('click', function(e) {
            if (!$(e.target).is('a, button')) {
                const propertyId = $(this).data('property-id');
                const searchParams = getCurrentSearchParams();
                const url = getPropertyUrl(propertyId, searchParams);
                window.location.href = url;
            }
        });

        // Image slider for property cards
        $('.property-image-slider').each(function() {
            initializeImageSlider($(this));
        });
    }

    /**
     * Initialize single property page functionality
     */
    function initializeSingleProperty() {
        // Property gallery
        initializePropertyGallery();

        // Booking form
        initializeBookingForm();

        // Property tabs
        initializePropertyTabs();

        // Availability calendar
        initializeAvailabilityCalendar();
    }

    /**
     * Initialize Google Maps
     */
    function initializeMaps() {
        if (typeof google === 'undefined' || !hostawayFrontend.googleMapsApiKey) {
            console.warn('Google Maps API not loaded or API key not configured');
            return;
        }

        // Initialize property map if on single property page
        if ($('#property-map').length) {
            initializePropertyMap();
        }

        // Initialize properties map if on properties page
        if ($('#hostaway-map').length && $('.properties-main').hasClass('with-map')) {
            initializePropertiesMap();
        }
    }


    /**
     * Validate dates in search form
     */
    function validateDates() {
        const checkinDate = new Date($('#checkin').val());
        const checkoutDate = new Date($('#checkout').val());
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if ($('#checkin').val() && checkinDate < today) {
            $('#checkin').val('');
            showNotification(hostawayFrontend.strings.checkinPastError || 'Check-in date cannot be in the past', 'error');
            return false;
        }

        if ($('#checkin').val() && $('#checkout').val() && checkoutDate <= checkinDate) {
            $('#checkout').val('');
            showNotification(hostawayFrontend.strings.checkoutBeforeCheckinError || 'Check-out date must be after check-in date', 'error');
            return false;
        }

        return true;
    }

    /**
     * Update search form with current parameters
     */
    function updateSearchForm() {
        const params = new URLSearchParams(window.location.search);
        
        if ($('#location').val()) {
            params.set('location', $('#location').val());
        }
        if ($('#checkin').val()) {
            params.set('checkin', $('#checkin').val());
        }
        if ($('#checkout').val()) {
            params.set('checkout', $('#checkout').val());
        }
        if ($('#guests').val()) {
            params.set('guests', $('#guests').val());
        }

        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.replaceState({}, '', newUrl);
    }

    /**
     * Apply filters to properties
     */
    function applyFilters() {
        const filters = getFilterValues();
        const currentParams = new URLSearchParams(window.location.search);
        
        // Update URL with filter parameters
        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                currentParams.set(key, filters[key]);
            } else {
                currentParams.delete(key);
            }
        });

        // Reset pagination
        currentParams.delete('paged');

        // Reload page with new parameters
        window.location.href = window.location.pathname + '?' + currentParams.toString();
    }

    /**
     * Clear all filters
     */
    function clearFilters() {
        $('.filter-select').val('');
        $('.amenity-checkbox input').prop('checked', false);
        $('.price-range input').val('');
        
        // Clear URL parameters except search params
        const params = new URLSearchParams(window.location.search);
        const searchParams = ['location', 'checkin', 'checkout', 'guests'];
        
        for (const key of params.keys()) {
            if (!searchParams.includes(key)) {
                params.delete(key);
            }
        }

        window.location.href = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    }

    /**
     * Get current filter values
     */
    function getFilterValues() {
        const filters = {};
        
        $('.filter-select').each(function() {
            const name = $(this).attr('name');
            const value = $(this).val();
            if (value) {
                filters[name] = value;
            }
        });

        const amenities = [];
        $('.amenity-checkbox input:checked').each(function() {
            amenities.push($(this).val());
        });
        if (amenities.length > 0) {
            filters.amenities = amenities;
        }

        const minPrice = $('input[name="min_price"]').val();
        const maxPrice = $('input[name="max_price"]').val();
        if (minPrice) {
            filters.min_price = minPrice;
        }
        if (maxPrice) {
            filters.max_price = maxPrice;
        }

        return filters;
    }

    /**
     * Debounced filter application
     */
    function debounceFilter() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(applyFilters, 500);
    }

    /**
     * Initialize image slider for property cards
     */
    function initializeImageSlider($slider) {
        const $images = $slider.find('img');
        if ($images.length <= 1) return;

        let currentIndex = 0;
        const totalImages = $images.length;

        // Auto-advance slider
        setInterval(() => {
            $images.eq(currentIndex).removeClass('active');
            currentIndex = (currentIndex + 1) % totalImages;
            $images.eq(currentIndex).addClass('active');
        }, 4000);

        // Click to advance
        $slider.on('click', function() {
            $images.eq(currentIndex).removeClass('active');
            currentIndex = (currentIndex + 1) % totalImages;
            $images.eq(currentIndex).addClass('active');
        });
    }

    /**
     * Initialize property gallery
     */
    function initializePropertyGallery() {
        $('.gallery-thumbnails img').on('click', function() {
            const src = $(this).attr('src');
            $('.gallery-main img').attr('src', src);
            $('.gallery-thumbnails img').removeClass('active');
            $(this).addClass('active');
        });

        // Set first thumbnail as active
        $('.gallery-thumbnails img:first').addClass('active');
    }

    /**
     * Initialize booking form
     */
    function initializeBookingForm() {
        const $form = $('.booking-form');
        if (!$form.length) return;

        // Update total when dates or guests change
        $form.find('input[name="checkin"], input[name="checkout"], select[name="guests"]').on('change', function() {
            updateBookingTotal();
        });

        // Form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            processBooking();
        });

        // Initial total calculation
        updateBookingTotal();
    }

    /**
     * Update booking total
     */
    function updateBookingTotal() {
        const checkin = $('input[name="checkin"]').val();
        const checkout = $('input[name="checkout"]').val();
        
        if (!checkin || !checkout) {
            $('.total-amount .amount').text('0.00');
            return;
        }

        const nights = calculateNights(checkin, checkout);
        const basePrice = parseFloat($('.booking-price .price').text().replace(/[^\d.]/g, ''));
        const total = basePrice * nights;

        $('.total-amount .amount').text(total.toFixed(2));
    }

    /**
     * Calculate nights between dates
     */
    function calculateNights(checkin, checkout) {
        const checkinDate = new Date(checkin);
        const checkoutDate = new Date(checkout);
        const diffTime = Math.abs(checkoutDate - checkinDate);
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    }

    /**
     * Process booking form
     */
    function processBooking() {
        const $form = $('.booking-form');
        const $submitBtn = $('.booking-submit');
        
        // Validate form
        if (!validateBookingForm()) {
            return;
        }

        // Show loading state
        $submitBtn.prop('disabled', true).text(hostawayFrontend.strings.processing || 'Processing...');

        // Get form data
        const formData = {
            property_id: $form.data('property-id') || getPropertyIdFromUrl(),
            checkin_date: $('input[name="checkin"]').val(),
            checkout_date: $('input[name="checkout"]').val(),
            guest_count: $('select[name="guests"]').val(),
            total_amount: $('.total-amount .amount').text()
        };

        // Create WooCommerce cart item
        $.ajax({
            url: hostawayFrontend.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hostaway_create_booking',
                booking_data: formData,
                nonce: hostawayFrontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Redirect to checkout
                    window.location.href = response.data.checkout_url;
                } else {
                    showNotification(response.data.message || 'Booking failed', 'error');
                }
            },
            error: function() {
                showNotification(hostawayFrontend.strings.error || 'An error occurred', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text(hostawayFrontend.strings.instantBooking || 'Instant Booking');
            }
        });
    }

    /**
     * Validate booking form
     */
    function validateBookingForm() {
        const checkin = $('input[name="checkin"]').val();
        const checkout = $('input[name="checkout"]').val();
        const guests = $('select[name="guests"]').val();

        if (!checkin) {
            showNotification('Please select check-in date', 'error');
            return false;
        }

        if (!checkout) {
            showNotification('Please select check-out date', 'error');
            return false;
        }

        if (!guests) {
            showNotification('Please select number of guests', 'error');
            return false;
        }

        return validateDates();
    }

    /**
     * Get property ID from URL
     */
    function getPropertyIdFromUrl() {
        const path = window.location.pathname;
        const matches = path.match(/\/property\/(\d+)/);
        return matches ? matches[1] : null;
    }

    /**
     * Initialize property tabs
     */
    function initializePropertyTabs() {
        $('.tab-button').on('click', function() {
            const tabId = $(this).data('tab');
            
            // Update button states
            $('.tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Update panel states
            $('.tab-panel').removeClass('active');
            $(`#${tabId}`).addClass('active');
            
            // Initialize tab content if needed
            if (tabId === 'availability') {
                loadAvailabilityCalendar();
            } else if (tabId === 'map') {
                initializePropertyMap();
            }
        });
    }

    /**
     * Initialize availability calendar
     */
    function initializeAvailabilityCalendar() {
        loadAvailabilityCalendar();
    }

    /**
     * Load availability calendar data
     */
    function loadAvailabilityCalendar() {
        const $container = $('.availability-calendar');
        const propertyId = $container.data('property-id');
        
        if (!propertyId) return;

        $container.html('<div class="calendar-loading">' + (hostawayFrontend.strings.loading || 'Loading...') + '</div>');

        $.ajax({
            url: hostawayFrontend.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hostaway_get_availability',
                property_id: propertyId,
                nonce: hostawayFrontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderAvailabilityCalendar(response.data);
                } else {
                    $container.html('<p>' + (hostawayFrontend.strings.error || 'Error loading availability') + '</p>');
                }
            },
            error: function() {
                $container.html('<p>' + (hostawayFrontend.strings.error || 'Error loading availability') + '</p>');
            }
        });
    }

    /**
     * Render availability calendar
     */
    function renderAvailabilityCalendar(availability) {
        const $container = $('.availability-calendar');
        
        // Create simple calendar view
        let html = '<div class="availability-grid">';
        
        availability.forEach(day => {
            const date = new Date(day.date);
            const isAvailable = day.available == 1;
            const className = isAvailable ? 'available' : 'unavailable';
            const status = isAvailable ? 'Available' : 'Unavailable';
            
            html += `
                <div class="availability-day ${className}">
                    <div class="date">${date.toLocaleDateString()}</div>
                    <div class="status">${status}</div>
                </div>
            `;
        });
        
        html += '</div>';
        $container.html(html);
    }

    /**
     * Initialize properties map
     */
    function initializePropertiesMap() {
        if (map) return; // Already initialized

        const mapElement = document.getElementById('hostaway-map');
        if (!mapElement) return;

        map = new google.maps.Map(mapElement, {
            zoom: 10,
            center: { lat: 25.7617, lng: -80.1918 }, // Default to Miami
            styles: [
                {
                    featureType: 'poi',
                    elementType: 'labels',
                    stylers: [{ visibility: 'off' }]
                }
            ]
        });

        loadPropertiesForMap();
    }

    /**
     * Initialize single property map
     */
    function initializePropertyMap() {
        const mapElement = document.getElementById('property-map');
        if (!mapElement) return;

        const propertyData = $(mapElement).data('property');
        if (!propertyData) return;

        const propertyLocation = {
            lat: parseFloat(propertyData.latitude),
            lng: parseFloat(propertyData.longitude)
        };

        const map = new google.maps.Map(mapElement, {
            zoom: 15,
            center: propertyLocation,
            styles: [
                {
                    featureType: 'poi',
                    elementType: 'labels',
                    stylers: [{ visibility: 'off' }]
                }
            ]
        });

        const marker = new google.maps.Marker({
            position: propertyLocation,
            map: map,
            title: propertyData.name
        });

        const infoWindow = new google.maps.InfoWindow({
            content: `
                <div class="property-info-window">
                    <h3>${propertyData.name}</h3>
                    <p>${propertyData.city}, ${propertyData.country}</p>
                    <p>${propertyData.currency} ${propertyData.base_price} per night</p>
                </div>
            `
        });

        marker.addListener('click', () => {
            infoWindow.open(map, marker);
        });
    }

    /**
     * Load properties for map display
     */
    function loadPropertiesForMap() {
        $.ajax({
            url: hostawayFrontend.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hostaway_get_map_properties',
                nonce: hostawayFrontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    addPropertiesToMap(response.data);
                }
            },
            error: function() {
                console.error('Failed to load properties for map');
            }
        });
    }

    /**
     * Add properties to map
     */
    function addPropertiesToMap(properties) {
        if (!map) return;

        // Clear existing markers
        propertyMarkers.forEach(marker => marker.setMap(null));
        propertyMarkers = [];

        // Add new markers
        properties.forEach(property => {
            if (property.latitude && property.longitude) {
                const marker = new google.maps.Marker({
                    position: { lat: parseFloat(property.latitude), lng: parseFloat(property.longitude) },
                    map: map,
                    title: property.name
                });

                const infoWindow = new google.maps.InfoWindow({
                    content: createPropertyInfoWindow(property)
                });

                marker.addListener('click', () => {
                    infoWindow.open(map, marker);
                });

                propertyMarkers.push(marker);
            }
        });

        // Fit map to show all markers
        if (propertyMarkers.length > 0) {
            const bounds = new google.maps.LatLngBounds();
            propertyMarkers.forEach(marker => {
                bounds.extend(marker.getPosition());
            });
            map.fitBounds(bounds);
        }
    }

    /**
     * Create property info window content
     */
    function createPropertyInfoWindow(property) {
        const image = property.images ? JSON.parse(property.images)[0] : null;
        const imageUrl = image ? (image.url || image) : '';
        
        return `
            <div class="property-info-window">
                ${imageUrl ? `<img src="${imageUrl}" alt="${property.name}" style="width: 100px; height: 75px; object-fit: cover; border-radius: 4px; margin-bottom: 8px;">` : ''}
                <h4 style="margin: 0 0 4px 0; font-size: 14px;">${property.name}</h4>
                <p style="margin: 0 0 4px 0; color: #666; font-size: 12px;">${property.city}, ${property.country}</p>
                <p style="margin: 0 0 8px 0; font-weight: bold; color: #007cba; font-size: 14px;">${property.currency} ${property.base_price} per night</p>
                <a href="${getPropertyUrl(property.id, getCurrentSearchParams())}" style="color: #007cba; text-decoration: none; font-size: 12px;">View Details</a>
            </div>
        `;
    }

    /**
     * Get current search parameters
     */
    function getCurrentSearchParams() {
        const params = new URLSearchParams(window.location.search);
        return Object.fromEntries(params.entries());
    }

    /**
     * Get property URL
     */
    function getPropertyUrl(propertyId, searchParams = {}) {
        const baseUrl = `${window.location.origin}/property/${propertyId}`;
        const params = new URLSearchParams(searchParams);
        return params.toString() ? `${baseUrl}?${params.toString()}` : baseUrl;
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        $('.hostaway-notification').remove();
        
        const notification = $(`
            <div class="hostaway-notification hostaway-notification-${type}">
                <span class="message">${message}</span>
                <button class="close">&times;</button>
            </div>
        `);
        
        $('body').append(notification);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            notification.fadeOut(() => notification.remove());
        }, 5000);
        
        // Close button
        notification.find('.close').on('click', () => {
            notification.fadeOut(() => notification.remove());
        });
    }

    // Add notification styles
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .hostaway-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #fff;
                border-radius: 8px;
                padding: 15px 20px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                z-index: 10000;
                display: flex;
                align-items: center;
                gap: 10px;
                min-width: 300px;
                animation: slideIn 0.3s ease;
            }
            
            .hostaway-notification-info {
                border-left: 4px solid #007cba;
            }
            
            .hostaway-notification-error {
                border-left: 4px solid #dc3545;
            }
            
            .hostaway-notification-success {
                border-left: 4px solid #28a745;
            }
            
            .hostaway-notification .close {
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                color: #666;
                padding: 0;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .hostaway-notification .message {
                flex: 1;
                font-weight: 500;
            }
            
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `)
        .appendTo('head');

})(jQuery);
