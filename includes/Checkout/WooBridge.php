<?php

namespace HostawayWP\Checkout;

use HostawayWP\API\HostawayClient;
use HostawayWP\Models\Property;
use HostawayWP\Models\Rate;
use HostawayWP\Models\Availability;

/**
 * WooCommerce integration bridge
 */
class WooBridge {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_hostaway_create_booking', [$this, 'ajaxCreateBooking']);
        add_action('wp_ajax_nopriv_hostaway_create_booking', [$this, 'ajaxCreateBooking']);
        add_action('wp_ajax_hostaway_calculate_price', [$this, 'ajaxCalculatePrice']);
        add_action('wp_ajax_nopriv_hostaway_calculate_price', [$this, 'ajaxCalculatePrice']);
    }
    
    /**
     * Initialize WooCommerce integration
     */
    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Add custom product type for properties
        add_filter('product_type_selector', [$this, 'addPropertyProductType']);
        add_action('woocommerce_product_data_tabs', [$this, 'addPropertyProductTab']);
        add_action('woocommerce_product_data_panels', [$this, 'addPropertyProductFields']);
        add_action('woocommerce_process_product_meta', [$this, 'savePropertyProductFields']);
        
        // Handle order completion
        add_action('woocommerce_order_status_completed', [$this, 'handleOrderCompletion']);
        add_action('woocommerce_order_status_processing', [$this, 'handleOrderCompletion']);
        
        // Add order meta fields
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'displayOrderMeta']);
        
        // Add custom fields to checkout
        add_action('woocommerce_after_checkout_billing_form', [$this, 'addCheckoutFields']);
        add_action('woocommerce_checkout_process', [$this, 'validateCheckoutFields']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'saveCheckoutFields']);
        
        // Customize order emails
        add_action('woocommerce_email_order_details', [$this, 'addBookingDetailsToEmail'], 10, 4);
    }
    
    /**
     * Add property product type
     */
    public function addPropertyProductType($types) {
        $types['hostaway_property'] = __('Hostaway Property', 'hostaway-wp');
        return $types;
    }
    
    /**
     * Add property product tab
     */
    public function addPropertyProductTab($tabs) {
        $tabs['hostaway_property'] = [
            'label' => __('Property Details', 'hostaway-wp'),
            'target' => 'hostaway_property_data',
            'class' => ['show_if_hostaway_property'],
        ];
        
        return $tabs;
    }
    
    /**
     * Add property product fields
     */
    public function addPropertyProductFields() {
        global $post;
        
        $property_id = get_post_meta($post->ID, '_hostaway_property_id', true);
        $hostaway_id = get_post_meta($post->ID, '_hostaway_id', true);
        
        ?>
        <div id="hostaway_property_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label for="hostaway_property_id"><?php esc_html_e('Property ID', 'hostaway-wp'); ?></label>
                    <input type="text" 
                           id="hostaway_property_id" 
                           name="hostaway_property_id" 
                           value="<?php echo esc_attr($property_id); ?>" 
                           readonly />
                    <span class="description"><?php esc_html_e('Internal property ID', 'hostaway-wp'); ?></span>
                </p>
                
                <p class="form-field">
                    <label for="hostaway_id"><?php esc_html_e('Hostaway ID', 'hostaway-wp'); ?></label>
                    <input type="text" 
                           id="hostaway_id" 
                           name="hostaway_id" 
                           value="<?php echo esc_attr($hostaway_id); ?>" 
                           readonly />
                    <span class="description"><?php esc_html_e('Hostaway property ID', 'hostaway-wp'); ?></span>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save property product fields
     */
    public function savePropertyProductFields($post_id) {
        if (isset($_POST['hostaway_property_id'])) {
            update_post_meta($post_id, '_hostaway_property_id', sanitize_text_field($_POST['hostaway_property_id']));
        }
        
        if (isset($_POST['hostaway_id'])) {
            update_post_meta($post_id, '_hostaway_id', sanitize_text_field($_POST['hostaway_id']));
        }
    }
    
    /**
     * AJAX handler for creating booking
     */
    public function ajaxCreateBooking() {
        check_ajax_referer('hostaway_frontend_nonce', 'nonce');
        
        $property_id = intval($_POST['property_id']);
        $form_data = $_POST['form_data'];
        
        // Parse form data
        $booking_data = [];
        foreach ($form_data as $field) {
            $booking_data[$field['name']] = $field['value'];
        }
        
        // Validate required fields
        if (empty($booking_data['checkin']) || empty($booking_data['checkout'])) {
            wp_send_json_error(['message' => __('Please select check-in and check-out dates.', 'hostaway-wp')]);
        }
        
        if (empty($booking_data['adults']) || $booking_data['adults'] < 1) {
            wp_send_json_error(['message' => __('Please select at least 1 adult.', 'hostaway-wp')]);
        }
        
        // Get property details
        $property_model = new Property();
        $property = $property_model->getById($property_id);
        
        if (!$property) {
            wp_send_json_error(['message' => __('Property not found.', 'hostaway-wp')]);
        }
        
        // Check availability
        $availability_model = new Availability();
        $is_available = $availability_model->isDateRangeAvailable(
            $property_id,
            $booking_data['checkin'],
            $booking_data['checkout']
        );
        
        if (!$is_available) {
            wp_send_json_error(['message' => __('Selected dates are not available.', 'hostaway-wp')]);
        }
        
        // Calculate total price
        $rate_model = new Rate();
        $total_guests = intval($booking_data['adults']) + intval($booking_data['children'] ?? 0) + intval($booking_data['infants'] ?? 0);
        
        $base_price = $rate_model->calculateTotalPrice(
            $property_id,
            $booking_data['checkin'],
            $booking_data['checkout'],
            $total_guests
        );
        
        if ($base_price === null) {
            wp_send_json_error(['message' => __('Unable to calculate price for selected dates.', 'hostaway-wp')]);
        }
        
        // Calculate extras
        $extras_total = 0;
        $extras_details = [];
        
        if (!empty($booking_data['extra_catering'])) {
            $catering_price = floatval($booking_data['extra_catering']);
            $nights = $this->calculateNights($booking_data['checkin'], $booking_data['checkout']);
            $extras_total += $catering_price * $nights;
            $extras_details[] = sprintf(__('Catering (%d nights)', 'hostaway-wp'), $nights);
        }
        
        if (!empty($booking_data['extra_bedding'])) {
            $bedding_price = floatval($booking_data['extra_bedding']);
            $nights = $this->calculateNights($booking_data['checkin'], $booking_data['checkout']);
            $extras_total += $bedding_price * $nights;
            $extras_details[] = sprintf(__('Bedding (%d nights)', 'hostaway-wp'), $nights);
        }
        
        $total_price = $base_price + $extras_total;
        
        // Create WooCommerce order
        $order_id = $this->createWooCommerceOrder([
            'property' => $property,
            'booking_data' => $booking_data,
            'base_price' => $base_price,
            'extras_total' => $extras_total,
            'extras_details' => $extras_details,
            'total_price' => $total_price,
        ]);
        
        if (is_wp_error($order_id)) {
            wp_send_json_error(['message' => $order_id->get_error_message()]);
        }
        
        // Get checkout URL
        $order = wc_get_order($order_id);
        $checkout_url = $order->get_checkout_payment_url();
        
        wp_send_json_success([
            'order_id' => $order_id,
            'checkout_url' => $checkout_url,
        ]);
    }
    
    /**
     * AJAX handler for calculating price
     */
    public function ajaxCalculatePrice() {
        check_ajax_referer('hostaway_frontend_nonce', 'nonce');
        
        $property_id = intval($_POST['property_id']);
        $checkin = sanitize_text_field($_POST['checkin']);
        $checkout = sanitize_text_field($_POST['checkout']);
        $guests = intval($_POST['guests']);
        
        if (!$property_id || !$checkin || !$checkout || !$guests) {
            wp_send_json_error(['message' => __('Invalid parameters.', 'hostaway-wp')]);
        }
        
        $rate_model = new Rate();
        $price = $rate_model->calculateTotalPrice($property_id, $checkin, $checkout, $guests);
        
        if ($price === null) {
            wp_send_json_error(['message' => __('Unable to calculate price for selected dates.', 'hostaway-wp')]);
        }
        
        wp_send_json_success([
            'base_price' => $price,
        ]);
    }
    
    /**
     * Create WooCommerce order
     */
    private function createWooCommerceOrder($data) {
        try {
            // Create order
            $order = wc_create_order();
            
            if (is_wp_error($order)) {
                return $order;
            }
            
            $property = $data['property'];
            $booking_data = $data['booking_data'];
            
            // Create product for this booking
            $product = new \WC_Product_Simple();
            $product->set_name($property['title'] . ' - ' . $this->formatDateRange($booking_data['checkin'], $booking_data['checkout']));
            $product->set_price($data['total_price']);
            $product->set_regular_price($data['total_price']);
            $product->set_sold_individually(true);
            $product->set_virtual(true);
            $product->set_downloadable(false);
            $product->set_status('private');
            $product_id = $product->save();
            
            // Add product to order
            $order->add_product($product, 1);
            
            // Set order meta
            $order->update_meta_data('_hostaway_property_id', $property['id']);
            $order->update_meta_data('_hostaway_booking_checkin', $booking_data['checkin']);
            $order->update_meta_data('_hostaway_booking_checkout', $booking_data['checkout']);
            $order->update_meta_data('_hostaway_booking_adults', intval($booking_data['adults']));
            $order->update_meta_data('_hostaway_booking_children', intval($booking_data['children'] ?? 0));
            $order->update_meta_data('_hostaway_booking_infants', intval($booking_data['infants'] ?? 0));
            $order->update_meta_data('_hostaway_booking_guests', intval($booking_data['adults']) + intval($booking_data['children'] ?? 0) + intval($booking_data['infants'] ?? 0));
            $order->update_meta_data('_hostaway_booking_base_price', $data['base_price']);
            $order->update_meta_data('_hostaway_booking_extras_total', $data['extras_total']);
            $order->update_meta_data('_hostaway_booking_extras_details', json_encode($data['extras_details']));
            $order->update_meta_data('_hostaway_booking_status', 'pending');
            
            // Set order status
            $order->set_status('pending');
            $order->set_currency(get_option('hostaway_wp_currency', 'USD'));
            
            // Calculate totals
            $order->calculate_totals();
            
            // Save order
            $order->save();
            
            return $order->get_id();
            
        } catch (Exception $e) {
            return new \WP_Error('order_creation_failed', $e->getMessage());
        }
    }
    
    /**
     * Handle order completion
     */
    public function handleOrderCompletion($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $property_id = $order->get_meta('_hostaway_property_id');
        $hostaway_reservation_id = $order->get_meta('_hostaway_reservation_id');
        
        // Skip if already processed or no property ID
        if (!$property_id || $hostaway_reservation_id) {
            return;
        }
        
        // Get property details
        $property_model = new Property();
        $property = $property_model->getById($property_id);
        
        if (!$property) {
            error_log('Hostaway: Property not found for order ' . $order_id);
            return;
        }
        
        // Create reservation in Hostaway
        $this->createHostawayReservation($order, $property);
    }
    
    /**
     * Create reservation in Hostaway
     */
    private function createHostawayReservation($order, $property) {
        try {
            $api_client = new HostawayClient();
            
            $booking_data = [
                'checkin' => $order->get_meta('_hostaway_booking_checkin'),
                'checkout' => $order->get_meta('_hostaway_booking_checkout'),
                'guests' => $order->get_meta('_hostaway_booking_guests'),
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'notes' => 'Booking created via WordPress plugin',
            ];
            
            $response = $api_client->createReservation($property['hostaway_id'], $booking_data);
            
            if ($response && isset($response['data']['id'])) {
                // Save reservation ID
                $order->update_meta_data('_hostaway_reservation_id', $response['data']['id']);
                $order->update_meta_data('_hostaway_booking_status', 'confirmed');
                $order->save();
                
                // Mark dates as booked
                $availability_model = new Availability();
                $availability_model->markAsBooked(
                    $property['id'],
                    $booking_data['checkin'],
                    $booking_data['checkout']
                );
                
                // Add order note
                $order->add_order_note(sprintf(
                    __('Hostaway reservation created with ID: %s', 'hostaway-wp'),
                    $response['data']['id']
                ));
                
            } else {
                throw new Exception('Invalid response from Hostaway API');
            }
            
        } catch (Exception $e) {
            error_log('Hostaway: Failed to create reservation for order ' . $order->get_id() . ': ' . $e->getMessage());
            
            // Add order note
            $order->add_order_note(sprintf(
                __('Failed to create Hostaway reservation: %s', 'hostaway-wp'),
                $e->getMessage()
            ));
        }
    }
    
    /**
     * Display order meta in admin
     */
    public function displayOrderMeta($order) {
        $property_id = $order->get_meta('_hostaway_property_id');
        $reservation_id = $order->get_meta('_hostaway_reservation_id');
        
        if (!$property_id) {
            return;
        }
        
        ?>
        <div class="address">
            <p><strong><?php esc_html_e('Booking Details:', 'hostaway-wp'); ?></strong></p>
            
            <?php if ($property_id): ?>
                <p>
                    <strong><?php esc_html_e('Property ID:', 'hostaway-wp'); ?></strong>
                    <?php echo esc_html($property_id); ?>
                </p>
            <?php endif; ?>
            
            <?php if ($reservation_id): ?>
                <p>
                    <strong><?php esc_html_e('Hostaway Reservation ID:', 'hostaway-wp'); ?></strong>
                    <?php echo esc_html($reservation_id); ?>
                </p>
            <?php endif; ?>
            
            <p>
                <strong><?php esc_html_e('Check-in:', 'hostaway-wp'); ?></strong>
                <?php echo esc_html($order->get_meta('_hostaway_booking_checkin')); ?>
            </p>
            
            <p>
                <strong><?php esc_html_e('Check-out:', 'hostaway-wp'); ?></strong>
                <?php echo esc_html($order->get_meta('_hostaway_booking_checkout')); ?>
            </p>
            
            <p>
                <strong><?php esc_html_e('Guests:', 'hostaway-wp'); ?></strong>
                <?php 
                $adults = $order->get_meta('_hostaway_booking_adults');
                $children = $order->get_meta('_hostaway_booking_children');
                $infants = $order->get_meta('_hostaway_booking_infants');
                
                $guest_breakdown = [];
                if ($adults) $guest_breakdown[] = $adults . ' ' . ($adults == 1 ? 'adult' : 'adults');
                if ($children) $guest_breakdown[] = $children . ' ' . ($children == 1 ? 'child' : 'children');
                if ($infants) $guest_breakdown[] = $infants . ' ' . ($infants == 1 ? 'infant' : 'infants');
                
                echo esc_html(implode(', ', $guest_breakdown));
                ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Add checkout fields
     */
    public function addCheckoutFields($checkout) {
        // Additional fields can be added here if needed
        echo '<div id="hostaway_booking_fields">';
        echo '<input type="hidden" id="hostaway_booking_data" name="hostaway_booking_data" value="">';
        echo '</div>';
    }
    
    /**
     * Validate checkout fields
     */
    public function validateCheckoutFields() {
        // Validation logic can be added here
    }
    
    /**
     * Save checkout fields
     */
    public function saveCheckoutFields($order_id) {
        if (!empty($_POST['hostaway_booking_data'])) {
            update_post_meta($order_id, '_hostaway_booking_data', sanitize_text_field($_POST['hostaway_booking_data']));
        }
    }
    
    /**
     * Add booking details to email
     */
    public function addBookingDetailsToEmail($order, $sent_to_admin, $plain_text, $email) {
        $property_id = $order->get_meta('_hostaway_property_id');
        
        if (!$property_id) {
            return;
        }
        
        $property_model = new Property();
        $property = $property_model->getById($property_id);
        
        if (!$property) {
            return;
        }
        
        if ($plain_text) {
            echo "\n" . __('Property Booking Details:', 'hostaway-wp') . "\n";
            echo __('Property:', 'hostaway-wp') . ' ' . $property['title'] . "\n";
            echo __('Check-in:', 'hostaway-wp') . ' ' . $order->get_meta('_hostaway_booking_checkin') . "\n";
            echo __('Check-out:', 'hostaway-wp') . ' ' . $order->get_meta('_hostaway_booking_checkout') . "\n";
            echo __('Guests:', 'hostaway-wp') . ' ' . $order->get_meta('_hostaway_booking_guests') . "\n";
        } else {
            echo '<h3>' . __('Property Booking Details', 'hostaway-wp') . '</h3>';
            echo '<p><strong>' . __('Property:', 'hostaway-wp') . '</strong> ' . esc_html($property['title']) . '</p>';
            echo '<p><strong>' . __('Check-in:', 'hostaway-wp') . '</strong> ' . esc_html($order->get_meta('_hostaway_booking_checkin')) . '</p>';
            echo '<p><strong>' . __('Check-out:', 'hostaway-wp') . '</strong> ' . esc_html($order->get_meta('_hostaway_booking_checkout')) . '</p>';
            echo '<p><strong>' . __('Guests:', 'hostaway-wp') . '</strong> ' . esc_html($order->get_meta('_hostaway_booking_guests')) . '</p>';
        }
    }
    
    /**
     * Calculate number of nights
     */
    private function calculateNights($checkin, $checkout) {
        $start = new \DateTime($checkin);
        $end = new \DateTime($checkout);
        $diff = $start->diff($end);
        return $diff->days;
    }
    
    /**
     * Format date range
     */
    private function formatDateRange($checkin, $checkout) {
        $start = new \DateTime($checkin);
        $end = new \DateTime($checkout);
        
        return $start->format('M j') . ' - ' . $end->format('M j, Y');
    }
}
