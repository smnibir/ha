/**
 * Hostaway WP Frontend JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        HostawayFrontend.init();
    });
    
    // Main frontend object
    window.HostawayFrontend = {
        
        // Initialize all components
        init: function() {
            this.initSearchForm();
            this.initGuestsSelector();
            this.initFilters();
            this.initMap();
            this.initTabs();
            this.initBookingForm();
            this.initPropertyGallery();
        },
        
        // Initialize search form
        initSearchForm: function() {
            $('.hostaway-search-form').each(function() {
                var $form = $(this);
                
                // Handle form submission
                $form.on('submit', function(e) {
                    e.preventDefault();
                    
                    var formData = $(this).serialize();
                    var action = $(this).attr('action');
                    
                    // Redirect with parameters
                    window.location.href = action + '?' + formData;
                });
                
                // Handle date validation
                $form.find('input[type="date"]').on('change', function() {
                    var checkin = $form.find('input[name="checkin"]').val();
                    var checkout = $form.find('input[name="checkout"]').val();
                    
                    if (checkin && checkout) {
                        if (new Date(checkout) <= new Date(checkin)) {
                            alert(hostawayFrontend.strings.invalidDates);
                            $form.find('input[name="checkout"]').val('');
                        }
                    }
                });
            });
        },
        
        // Initialize guests selector
        initGuestsSelector: function() {
            $('.guests-selector').each(function() {
                var $selector = $(this);
                var $input = $selector.find('input[name="guests_display"]');
                var $dropdown = $selector.find('.guests-dropdown');
                var $totalInput = $selector.find('input[name="guests"]');
                
                // Toggle dropdown
                $input.on('click', function() {
                    $dropdown.toggleClass('active');
                });
                
                // Handle guest counter buttons
                $selector.find('.guest-counter button').on('click', function() {
                    var $button = $(this);
                    var type = $button.data('type');
                    var $input = $button.siblings('input');
                    var currentValue = parseInt($input.val());
                    var min = parseInt($input.attr('min'));
                    var max = parseInt($input.attr('max'));
                    
                    if ($button.hasClass('increase')) {
                        if (currentValue < max) {
                            $input.val(currentValue + 1);
                        }
                    } else if ($button.hasClass('decrease')) {
                        if (currentValue > min) {
                            $input.val(currentValue - 1);
                        }
                    }
                    
                    updateGuestsDisplay();
                });
                
                // Handle manual input
                $selector.find('.guest-counter input').on('change', function() {
                    updateGuestsDisplay();
                });
                
                // Close dropdown
                $selector.find('.close-guests').on('click', function() {
                    $dropdown.removeClass('active');
                });
                
                // Update guests display
                function updateGuestsDisplay() {
                    var adults = parseInt($selector.find('input[name="adults"]').val()) || 0;
                    var children = parseInt($selector.find('input[name="children"]').val()) || 0;
                    var infants = parseInt($selector.find('input[name="infants"]').val()) || 0;
                    var total = adults + children + infants;
                    
                    var displayText = [];
                    if (adults > 0) {
                        displayText.push(adults + ' ' + (adults === 1 ? 'adult' : 'adults'));
                    }
                    if (children > 0) {
                        displayText.push(children + ' ' + (children === 1 ? 'child' : 'children'));
                    }
                    if (infants > 0) {
                        displayText.push(infants + ' ' + (infants === 1 ? 'infant' : 'infants'));
                    }
                    
                    $input.val(displayText.join(', '));
                    $totalInput.val(total);
                }
                
                // Close dropdown when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.guests-selector').length) {
                        $dropdown.removeClass('active');
                    }
                });
            });
        },
        
        // Initialize filters
        initFilters: function() {
            // Toggle filters drawer
            $('.toggle-filters').on('click', function() {
                $('.filters-drawer').toggleClass('active');
                $('.filters-overlay').toggleClass('active');
                $('body').toggleClass('no-scroll');
            });
            
            // Close filters drawer
            $('.filters-overlay, .filters-drawer .close').on('click', function() {
                $('.filters-drawer').removeClass('active');
                $('.filters-overlay').removeClass('active');
                $('body').removeClass('no-scroll');
            });
            
            // Apply filters
            $('.apply-filters').on('click', function() {
                var filters = getFiltersData();
                var url = updateUrlWithFilters(window.location.href, filters);
                window.location.href = url;
            });
            
            // Clear filters
            $('.clear-filters').on('click', function() {
                var url = new URL(window.location.href);
                var paramsToRemove = ['amenities', 'rooms', 'bathrooms', 'price_min', 'price_max'];
                paramsToRemove.forEach(function(param) {
                    url.searchParams.delete(param);
                });
                window.location.href = url.toString();
            });
            
            // Toggle map
            $('.toggle-map').on('click', function() {
                var $button = $(this);
                var $grid = $('.properties-grid');
                var $map = $('.properties-map');
                
                if ($button.hasClass('active')) {
                    $button.removeClass('active');
                    $button.find('.map-text').text('Show Map');
                    $grid.removeClass('with-map').addClass('no-map');
                    $map.hide();
                } else {
                    $button.addClass('active');
                    $button.find('.map-text').text('Hide Map');
                    $grid.removeClass('no-map').addClass('with-map');
                    $map.show();
                    
                    // Initialize map if not already done
                    if (typeof google !== 'undefined' && !window.hostawayMap) {
                        HostawayFrontend.initMap();
                    }
                }
            });
            
            // Reset all filters
            $('.reset-filters').on('click', function() {
                var url = new URL(window.location.href);
                var paramsToRemove = ['location', 'checkin', 'checkout', 'adults', 'children', 'infants', 'guests', 'amenities', 'rooms', 'bathrooms', 'price_min', 'price_max', 'page'];
                paramsToRemove.forEach(function(param) {
                    url.searchParams.delete(param);
                });
                window.location.href = url.toString();
            });
        },
        
        // Initialize map
        initMap: function() {
            if (typeof google === 'undefined') {
                console.error('Google Maps API not loaded');
                return;
            }
            
            // Properties map
            var $mapContainer = $('#hostaway-map');
            if ($mapContainer.length) {
                var properties = $mapContainer.data('properties') || [];
                this.createPropertiesMap($mapContainer[0], properties);
            }
            
            // Single property map
            var $singleMap = $('#single-property-map');
            if ($singleMap.length) {
                var lat = parseFloat($singleMap.data('lat'));
                var lng = parseFloat($singleMap.data('lng'));
                var title = $singleMap.data('title');
                this.createSinglePropertyMap($singleMap[0], lat, lng, title);
            }
        },
        
        // Create properties map
        createPropertiesMap: function(container, properties) {
            if (!properties || properties.length === 0) {
                return;
            }
            
            // Calculate bounds
            var bounds = new google.maps.LatLngBounds();
            var markers = [];
            
            properties.forEach(function(property) {
                if (property.latitude && property.longitude) {
                    var position = new google.maps.LatLng(property.latitude, property.longitude);
                    bounds.extend(position);
                    
                    var marker = new google.maps.Marker({
                        position: position,
                        map: window.hostawayMap,
                        title: property.title,
                        icon: {
                            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(createMarkerIcon()),
                            scaledSize: new google.maps.Size(30, 40)
                        }
                    });
                    
                    // Create info window
                    var infoWindow = new google.maps.InfoWindow({
                        content: createInfoWindowContent(property)
                    });
                    
                    // Show info window on hover
                    marker.addListener('mouseover', function() {
                        infoWindow.open(window.hostawayMap, marker);
                    });
                    
                    markers.push(marker);
                }
            });
            
            // Fit bounds
            if (bounds.isEmpty() === false) {
                window.hostawayMap.fitBounds(bounds);
            }
            
            // Initialize map if not exists
            if (!window.hostawayMap) {
                window.hostawayMap = new google.maps.Map(container, {
                    zoom: 10,
                    center: bounds.getCenter(),
                    styles: [
                        {
                            featureType: 'poi',
                            elementType: 'labels',
                            stylers: [{ visibility: 'off' }]
                        }
                    ]
                });
            }
        },
        
        // Create single property map
        createSinglePropertyMap: function(container, lat, lng, title) {
            var position = new google.maps.LatLng(lat, lng);
            
            window.singlePropertyMap = new google.maps.Map(container, {
                zoom: 15,
                center: position
            });
            
            var marker = new google.maps.Marker({
                position: position,
                map: window.singlePropertyMap,
                title: title
            });
            
            var infoWindow = new google.maps.InfoWindow({
                content: '<h3>' + title + '</h3>'
            });
            
            infoWindow.open(window.singlePropertyMap, marker);
        },
        
        // Initialize tabs
        initTabs: function() {
            $('.tab-btn').on('click', function() {
                var $button = $(this);
                var tabId = $button.data('tab');
                var $tabs = $button.closest('.property-tabs');
                
                // Update active tab
                $tabs.find('.tab-btn').removeClass('active');
                $button.addClass('active');
                
                // Update active panel
                $tabs.find('.tab-panel').removeClass('active');
                $tabs.find('#' + tabId).addClass('active');
            });
        },
        
        // Initialize booking form
        initBookingForm: function() {
            $('#booking-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var propertyId = $form.data('property-id');
                var formData = $form.serializeArray();
                
                // Validate required fields
                var checkin = $form.find('input[name="checkin"]').val();
                var checkout = $form.find('input[name="checkout"]').val();
                
                if (!checkin || !checkout) {
                    alert(hostawayFrontend.strings.selectDates);
                    return;
                }
                
                if (new Date(checkout) <= new Date(checkin)) {
                    alert(hostawayFrontend.strings.invalidDates);
                    return;
                }
                
                // Show loading state
                var $submitBtn = $form.find('.booking-submit');
                var originalText = $submitBtn.html();
                $submitBtn.prop('disabled', true).html(hostawayFrontend.strings.loading);
                
                // Submit booking
                $.ajax({
                    url: hostawayFrontend.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'hostaway_create_booking',
                        nonce: hostawayFrontend.nonce,
                        property_id: propertyId,
                        form_data: formData
                    },
                    success: function(response) {
                        if (response.success) {
                            // Redirect to checkout
                            window.location.href = response.data.checkout_url;
                        } else {
                            alert(response.data.message || hostawayFrontend.strings.bookingError);
                        }
                    },
                    error: function() {
                        alert(hostawayFrontend.strings.bookingError);
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false).html(originalText);
                    }
                });
            });
            
            // Calculate total price
            function calculateTotal() {
                var $form = $('#booking-form');
                var checkin = $form.find('input[name="checkin"]').val();
                var checkout = $form.find('input[name="checkout"]').val();
                var adults = parseInt($form.find('input[name="adults"]').val()) || 0;
                var children = parseInt($form.find('input[name="children"]').val()) || 0;
                var infants = parseInt($form.find('input[name="infants"]').val()) || 0;
                var guests = adults + children + infants;
                
                if (!checkin || !checkout) {
                    $('#base-price, #extras-price, #total-price').text(hostawayFrontend.currency + ' 0');
                    return;
                }
                
                // Calculate nights
                var nights = Math.ceil((new Date(checkout) - new Date(checkin)) / (1000 * 60 * 60 * 24));
                
                // Calculate extras
                var extrasTotal = 0;
                $form.find('input[type="checkbox"]:checked').each(function() {
                    extrasTotal += parseFloat($(this).val()) * nights;
                });
                
                // Get base price from API
                $.ajax({
                    url: hostawayFrontend.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'hostaway_calculate_price',
                        nonce: hostawayFrontend.nonce,
                        property_id: $form.data('property-id'),
                        checkin: checkin,
                        checkout: checkout,
                        guests: guests
                    },
                    success: function(response) {
                        if (response.success) {
                            var basePrice = response.data.base_price;
                            var totalPrice = basePrice + extrasTotal;
                            
                            $('#base-price').text(hostawayFrontend.currency + ' ' + basePrice.toFixed(2));
                            
                            if (extrasTotal > 0) {
                                $('#extras-price').text(hostawayFrontend.currency + ' ' + extrasTotal.toFixed(2));
                                $('.total-item.extras').show();
                            } else {
                                $('.total-item.extras').hide();
                            }
                            
                            $('#total-price').text(hostawayFrontend.currency + ' ' + totalPrice.toFixed(2));
                        }
                    }
                });
            }
            
            // Trigger calculation on form changes
            $('#booking-form input, #booking-form select').on('change', calculateTotal);
            
            // Initial calculation
            calculateTotal();
        },
        
        // Initialize property gallery
        initPropertyGallery: function() {
            $('.property-gallery-slider').each(function() {
                var $slider = $(this);
                var $slides = $slider.find('.gallery-slide');
                
                if ($slides.length <= 1) {
                    return;
                }
                
                // Simple gallery implementation
                var currentSlide = 0;
                
                // Add navigation
                $slider.append('<div class="gallery-nav"><button class="prev-slide">‹</button><button class="next-slide">›</button></div>');
                
                // Add dots
                var $dots = $('<div class="gallery-dots"></div>');
                for (var i = 0; i < $slides.length; i++) {
                    $dots.append('<button class="dot" data-slide="' + i + '"></button>');
                }
                $slider.append($dots);
                
                function showSlide(index) {
                    $slides.hide().eq(index).show();
                    $slider.find('.dot').removeClass('active').eq(index).addClass('active');
                    currentSlide = index;
                }
                
                // Navigation handlers
                $slider.find('.prev-slide').on('click', function() {
                    var prev = currentSlide > 0 ? currentSlide - 1 : $slides.length - 1;
                    showSlide(prev);
                });
                
                $slider.find('.next-slide').on('click', function() {
                    var next = currentSlide < $slides.length - 1 ? currentSlide + 1 : 0;
                    showSlide(next);
                });
                
                $slider.find('.dot').on('click', function() {
                    showSlide(parseInt($(this).data('slide')));
                });
                
                // Show first slide
                showSlide(0);
            });
        }
    };
    
    // Helper functions
    function getFiltersData() {
        var filters = {};
        
        // Get amenity filters
        var amenities = [];
        $('.amenity-filter input:checked').each(function() {
            amenities.push($(this).val());
        });
        if (amenities.length > 0) {
            filters.amenities = amenities;
        }
        
        // Get other filters
        var rooms = $('select[name="rooms"]').val();
        if (rooms) filters.rooms = rooms;
        
        var bathrooms = $('select[name="bathrooms"]').val();
        if (bathrooms) filters.bathrooms = bathrooms;
        
        var priceMin = $('input[name="price_min"]').val();
        if (priceMin) filters.price_min = priceMin;
        
        var priceMax = $('input[name="price_max"]').val();
        if (priceMax) filters.price_max = priceMax;
        
        return filters;
    }
    
    function updateUrlWithFilters(url, filters) {
        var urlObj = new URL(url);
        
        // Clear existing filter parameters
        var paramsToRemove = ['amenities', 'rooms', 'bathrooms', 'price_min', 'price_max', 'page'];
        paramsToRemove.forEach(function(param) {
            urlObj.searchParams.delete(param);
        });
        
        // Add new filter parameters
        Object.keys(filters).forEach(function(key) {
            if (Array.isArray(filters[key])) {
                filters[key].forEach(function(value) {
                    urlObj.searchParams.append(key + '[]', value);
                });
            } else {
                urlObj.searchParams.set(key, filters[key]);
            }
        });
        
        return urlObj.toString();
    }
    
    function createMarkerIcon() {
        return '<svg width="30" height="40" viewBox="0 0 30 40" xmlns="http://www.w3.org/2000/svg">' +
               '<path d="M15 0C6.7 0 0 6.7 0 15c0 15 15 25 15 25s15-10 15-25c0-8.3-6.7-15-15-15z" fill="#d4a574"/>' +
               '<circle cx="15" cy="15" r="6" fill="white"/>' +
               '</svg>';
    }
    
    function createInfoWindowContent(property) {
        var content = '<div class="map-info-window">';
        if (property.thumbnail_url) {
            content += '<img src="' + property.thumbnail_url + '" alt="' + property.title + '" style="width: 100px; height: 60px; object-fit: cover; border-radius: 4px;">';
        }
        content += '<h4 style="margin: 8px 0 4px 0; font-size: 14px;">' + property.title + '</h4>';
        content += '<p style="margin: 0; font-size: 12px; color: #666;">' + property.type + '</p>';
        content += '<p style="margin: 4px 0 0 0; font-size: 12px; font-weight: bold; color: #d4a574;">' + hostawayFrontend.currency + ' ' + property.base_price + ' / night</p>';
        content += '</div>';
        return content;
    }
    
})(jQuery);
