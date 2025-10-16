<?php

namespace HostawayWP\Frontend;

/**
 * Frontend assets manager
 */
class Assets {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_head', [$this, 'addInlineStyles']);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueueAssets() {
        // Only load on pages with our shortcodes
        global $post;
        
        if (!$post || !has_shortcode($post->post_content, 'hostaway_search') && 
            !has_shortcode($post->post_content, 'hostaway_properties') && 
            !has_shortcode($post->post_content, 'hostaway_property')) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'hostaway-frontend',
            HOSTAWAY_WP_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            HOSTAWAY_WP_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'hostaway-frontend',
            HOSTAWAY_WP_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            HOSTAWAY_WP_VERSION,
            true
        );
        
        // Google Maps API
        $google_maps_api_key = get_option('hostaway_wp_google_maps_api_key');
        if ($google_maps_api_key) {
            wp_enqueue_script(
                'google-maps',
                'https://maps.googleapis.com/maps/api/js?key=' . $google_maps_api_key . '&libraries=places',
                [],
                null,
                true
            );
        }
        
        // Localize script
        wp_localize_script('hostaway-frontend', 'hostawayFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hostaway_frontend_nonce'),
            'currency' => get_option('hostaway_wp_currency', 'USD'),
            'locale' => get_option('hostaway_wp_locale', 'en_US'),
            'strings' => [
                'loading' => __('Loading...', 'hostaway-wp'),
                'noResults' => __('No properties found', 'hostaway-wp'),
                'error' => __('An error occurred', 'hostaway-wp'),
                'selectDates' => __('Please select check-in and check-out dates', 'hostaway-wp'),
                'invalidDates' => __('Check-out must be after check-in', 'hostaway-wp'),
                'minNights' => __('Minimum %d nights required', 'hostaway-wp'),
                'maxGuests' => __('Maximum %d guests allowed', 'hostaway-wp'),
                'bookingSuccess' => __('Booking successful!', 'hostaway-wp'),
                'bookingError' => __('Booking failed. Please try again.', 'hostaway-wp'),
            ],
        ]);
    }
    
    /**
     * Add inline styles
     */
    public function addInlineStyles() {
        global $post;
        
        if (!$post || !has_shortcode($post->post_content, 'hostaway_search') && 
            !has_shortcode($post->post_content, 'hostaway_properties') && 
            !has_shortcode($post->post_content, 'hostaway_property')) {
            return;
        }
        
        ?>
        <style>
        :root {
            --hostaway-primary: #d4a574;
            --hostaway-secondary: #2c3e50;
            --hostaway-success: #27ae60;
            --hostaway-error: #e74c3c;
            --hostaway-warning: #f39c12;
            --hostaway-light: #ecf0f1;
            --hostaway-dark: #2c3e50;
            --hostaway-border: #bdc3c7;
            --hostaway-shadow: 0 2px 10px rgba(0,0,0,0.1);
            --hostaway-radius: 8px;
        }
        
        .hostaway-search-form,
        .hostaway-properties-page,
        .hostaway-single-property {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
        }
        
        /* Loading states */
        .hostaway-loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .hostaway-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid var(--hostaway-border);
            border-top-color: var(--hostaway-primary);
            border-radius: 50%;
            animation: hostaway-spin 1s linear infinite;
        }
        
        @keyframes hostaway-spin {
            to { transform: rotate(360deg); }
        }
        
        /* Error states */
        .hostaway-error {
            background: var(--hostaway-error);
            color: white;
            padding: 10px 15px;
            border-radius: var(--hostaway-radius);
            margin: 10px 0;
        }
        
        /* Success states */
        .hostaway-success {
            background: var(--hostaway-success);
            color: white;
            padding: 10px 15px;
            border-radius: var(--hostaway-radius);
            margin: 10px 0;
        }
        
        /* Responsive utilities */
        @media (max-width: 768px) {
            .hostaway-properties-grid.with-map {
                grid-template-columns: 1fr;
            }
            
            .hostaway-properties-grid.no-map {
                grid-template-columns: 1fr;
            }
            
            .hostaway-property-content {
                flex-direction: column;
            }
            
            .hostaway-property-sidebar {
                order: -1;
            }
        }
        
        @media (min-width: 769px) and (max-width: 1024px) {
            .hostaway-properties-grid.with-map {
                grid-template-columns: 1fr 1fr;
            }
            
            .hostaway-properties-grid.no-map {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (min-width: 1025px) {
            .hostaway-properties-grid.with-map {
                grid-template-columns: 1fr 1fr;
            }
            
            .hostaway-properties-grid.no-map {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }
        </style>
        <?php
    }
}
