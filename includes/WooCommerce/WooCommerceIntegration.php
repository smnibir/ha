<?php

namespace HostawaySync\WooCommerce;

use HostawaySync\Database\Database;
use HostawaySync\API\HostawayClient;

/**
 * WooCommerce Integration
 */
class WooCommerceIntegration {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('woocommerce_checkout_process', array($this, 'validate_booking'));
        add_action('woocommerce_checkout_order_processed', array($this, 'process_booking'));
        add_action('woocommerce_order_status_completed', array($this, 'create_hostaway_reservation'));
        add_action('woocommerce_order_status_cancelled', array($this, 'cancel_hostaway_reservation'));
        add_action('woocommerce_before_checkout_form', array($this, 'add_booking_terms'));
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'));
        add_action('woocommerce_thankyou', array($this, 'display_booking_details'));
    }
    
    /**
     * Initialize WooCommerce integration
     */
    public function init() {
        // Ensure WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Register custom order meta fields
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_custom_line_item_data'), 10, 4);
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_order_meta'));
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_hostaway_reservation_info'));
    }
    
    /**
     * Validate booking data
     */
    public function validate_booking() {
        if (!isset($_POST['hostaway_booking'])) {
            return;
        }
        
        $booking_data = $_POST['hostaway_booking'];
        
        // Validate required fields
        if (empty($booking_data['property_id'])) {
            wc_add_notice(__('Please select a property.', 'hostaway-sync'), 'error');
            return;
        }
        
        if (empty($booking_data['checkin_date']) || empty($booking_data['checkout_date'])) {
            wc_add_notice(__('Please select check-in and check-out dates.', 'hostaway-sync'), 'error');
            return;
        }
        
        // Validate dates
        $checkin = new \DateTime($booking_data['checkin_date']);
        $checkout = new \DateTime($booking_data['checkout_date']);
        $today = new \DateTime();
        
        if ($checkin < $today) {
            wc_add_notice(__('Check-in date cannot be in the past.', 'hostaway-sync'), 'error');
            return;
        }
        
        if ($checkout <= $checkin) {
            wc_add_notice(__('Check-out date must be after check-in date.', 'hostaway-sync'), 'error');
            return;
        }
        
        // Validate guest count
        if (empty($booking_data['guest_count']) || $booking_data['guest_count'] < 1) {
            wc_add_notice(__('Please specify the number of guests.', 'hostaway-sync'), 'error');
            return;
        }
        
        // Check availability
        if (!$this->is_property_available($booking_data['property_id'], $booking_data['checkin_date'], $booking_data['checkout_date'])) {
            wc_add_notice(__('The selected dates are not available for this property.', 'hostaway-sync'), 'error');
            return;
        }
    }
    
    /**
     * Process booking after order creation
     */
    public function process_booking($order_id) {
        if (!isset($_POST['hostaway_booking'])) {
            return;
        }
        
        $order = wc_get_order($order_id);
        $booking_data = $_POST['hostaway_booking'];
        
        // Store booking data in order meta
        $order->update_meta_data('_hostaway_booking_data', $booking_data);
        $order->update_meta_data('_hostaway_property_id', $booking_data['property_id']);
        $order->update_meta_data('_hostaway_checkin_date', $booking_data['checkin_date']);
        $order->update_meta_data('_hostaway_checkout_date', $booking_data['checkout_date']);
        $order->update_meta_data('_hostaway_guest_count', $booking_data['guest_count']);
        $order->update_meta_data('_hostaway_extras', $booking_data['extras'] ?? array());
        $order->update_meta_data('_hostaway_reservation_status', 'pending');
        
        $order->save();
    }
    
    /**
     * Create Hostaway reservation when order is completed
     */
    public function create_hostaway_reservation($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_meta('_hostaway_reservation_status') === 'created') {
            return;
        }
        
        $booking_data = $order->get_meta('_hostaway_booking_data');
        if (empty($booking_data)) {
            return;
        }
        
        try {
            $api_client = new HostawayClient();
            
            $reservation_data = array(
                'checkin_date' => $booking_data['checkin_date'],
                'checkout_date' => $booking_data['checkout_date'],
                'guest_count' => $booking_data['guest_count'],
                'total_amount' => $order->get_total(),
                'currency' => $order->get_currency(),
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'notes' => $order->get_customer_note()
            );
            
            $hostaway_property_id = $this->get_hostaway_property_id($booking_data['property_id']);
            $reservation = $api_client->create_reservation($hostaway_property_id, $reservation_data);
            
            if (isset($reservation['result']) && isset($reservation['result']['id'])) {
                $reservation_id = $reservation['result']['id'];
                
                // Store reservation ID
                $order->update_meta_data('_hostaway_reservation_id', $reservation_id);
                $order->update_meta_data('_hostaway_reservation_status', 'created');
                
                // Store in our database
                $this->store_reservation($order_id, $reservation_id, $booking_data, $reservation);
                
                // Add order note
                $order->add_order_note(
                    sprintf(
                        __('Hostaway reservation created successfully. Reservation ID: %s', 'hostaway-sync'),
                        $reservation_id
                    )
                );
                
            } else {
                throw new \Exception('Invalid reservation response from Hostaway API');
            }
            
        } catch (\Exception $e) {
            // Log error
            Database::log_sync('reservation_creation', 'error', $e->getMessage(), array(
                'order_id' => $order_id,
                'booking_data' => $booking_data
            ));
            
            // Add order note
            $order->add_order_note(
                sprintf(
                    __('Failed to create Hostaway reservation: %s', 'hostaway-sync'),
                    $e->getMessage()
                )
            );
        }
        
        $order->save();
    }
    
    /**
     * Cancel Hostaway reservation when order is cancelled
     */
    public function cancel_hostaway_reservation($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $reservation_id = $order->get_meta('_hostaway_reservation_id');
        if (empty($reservation_id)) {
            return;
        }
        
        try {
            $api_client = new HostawayClient();
            $result = $api_client->cancel_reservation($reservation_id, 'Order cancelled in WooCommerce');
            
            // Update reservation status
            $order->update_meta_data('_hostaway_reservation_status', 'cancelled');
            
            // Update database
            $this->update_reservation_status($order_id, 'cancelled');
            
            // Add order note
            $order->add_order_note(
                sprintf(
                    __('Hostaway reservation cancelled successfully. Reservation ID: %s', 'hostaway-sync'),
                    $reservation_id
                )
            );
            
        } catch (\Exception $e) {
            // Log error
            Database::log_sync('reservation_cancellation', 'error', $e->getMessage(), array(
                'order_id' => $order_id,
                'reservation_id' => $reservation_id
            ));
            
            // Add order note
            $order->add_order_note(
                sprintf(
                    __('Failed to cancel Hostaway reservation: %s', 'hostaway-sync'),
                    $e->getMessage()
                )
            );
        }
        
        $order->save();
    }
    
    /**
     * Add booking terms to checkout
     */
    public function add_booking_terms() {
        if (!isset($_POST['hostaway_booking'])) {
            return;
        }
        
        ?>
        <div class="hostaway-booking-terms">
            <h3><?php _e('Booking Terms & Conditions', 'hostaway-sync'); ?></h3>
            <ul>
                <li><?php _e('Check-in time: 3:00 PM', 'hostaway-sync'); ?></li>
                <li><?php _e('Check-out time: 11:00 AM', 'hostaway-sync'); ?></li>
                <li><?php _e('Cancellation policy: Free cancellation up to 24 hours before check-in', 'hostaway-sync'); ?></li>
                <li><?php _e('No smoking allowed on the property', 'hostaway-sync'); ?></li>
                <li><?php _e('Pets are not allowed unless specified', 'hostaway-sync'); ?></li>
                <li><?php _e('Additional charges may apply for damages or excessive cleaning', 'hostaway-sync'); ?></li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Customize checkout fields for booking
     */
    public function customize_checkout_fields($fields) {
        if (!isset($_POST['hostaway_booking'])) {
            return $fields;
        }
        
        // Make some fields required for bookings
        $fields['billing']['billing_phone']['required'] = true;
        
        // Add special instructions field
        $fields['order']['order_comments']['label'] = __('Special Instructions', 'hostaway-sync');
        $fields['order']['order_comments']['placeholder'] = __('Any special requests or instructions for your stay...', 'hostaway-sync');
        
        return $fields;
    }
    
    /**
     * Add custom line item data
     */
    public function add_custom_line_item_data($item, $cart_item_key, $values, $order) {
        if (!isset($values['hostaway_booking'])) {
            return;
        }
        
        $booking_data = $values['hostaway_booking'];
        
        $item->add_meta_data('_hostaway_property_id', $booking_data['property_id']);
        $item->add_meta_data('_hostaway_checkin_date', $booking_data['checkin_date']);
        $item->add_meta_data('_hostaway_checkout_date', $booking_data['checkout_date']);
        $item->add_meta_data('_hostaway_guest_count', $booking_data['guest_count']);
        
        if (!empty($booking_data['extras'])) {
            $item->add_meta_data('_hostaway_extras', $booking_data['extras']);
        }
    }
    
    /**
     * Display order meta in admin
     */
    public function display_order_meta($order) {
        $property_id = $order->get_meta('_hostaway_property_id');
        $checkin_date = $order->get_meta('_hostaway_checkin_date');
        $checkout_date = $order->get_meta('_hostaway_checkout_date');
        $guest_count = $order->get_meta('_hostaway_guest_count');
        
        if (!$property_id) {
            return;
        }
        
        $property = $this->get_property_details($property_id);
        
        ?>
        <div class="hostaway-booking-info">
            <h3><?php _e('Booking Information', 'hostaway-sync'); ?></h3>
            <p><strong><?php _e('Property:', 'hostaway-sync'); ?></strong> <?php echo esc_html($property->name); ?></p>
            <p><strong><?php _e('Check-in:', 'hostaway-sync'); ?></strong> <?php echo esc_html($checkin_date); ?></p>
            <p><strong><?php _e('Check-out:', 'hostaway-sync'); ?></strong> <?php echo esc_html($checkout_date); ?></p>
            <p><strong><?php _e('Guests:', 'hostaway-sync'); ?></strong> <?php echo esc_html($guest_count); ?></p>
        </div>
        <?php
    }
    
    /**
     * Display Hostaway reservation info in admin
     */
    public function display_hostaway_reservation_info($order) {
        $reservation_id = $order->get_meta('_hostaway_reservation_id');
        $reservation_status = $order->get_meta('_hostaway_reservation_status');
        
        if (!$reservation_id) {
            return;
        }
        
        ?>
        <div class="hostaway-reservation-info">
            <h3><?php _e('Hostaway Reservation', 'hostaway-sync'); ?></h3>
            <p><strong><?php _e('Reservation ID:', 'hostaway-sync'); ?></strong> <?php echo esc_html($reservation_id); ?></p>
            <p><strong><?php _e('Status:', 'hostaway-sync'); ?></strong> <?php echo esc_html(ucfirst($reservation_status)); ?></p>
            
            <?php if ($reservation_status === 'pending'): ?>
                <p class="description"><?php _e('Reservation will be created when payment is completed.', 'hostaway-sync'); ?></p>
            <?php elseif ($reservation_status === 'created'): ?>
                <p class="description"><?php _e('Reservation has been successfully created in Hostaway.', 'hostaway-sync'); ?></p>
            <?php elseif ($reservation_status === 'cancelled'): ?>
                <p class="description"><?php _e('Reservation has been cancelled in Hostaway.', 'hostaway-sync'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Display booking details on thank you page
     */
    public function display_booking_details($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || !$order->get_meta('_hostaway_property_id')) {
            return;
        }
        
        $property_id = $order->get_meta('_hostaway_property_id');
        $checkin_date = $order->get_meta('_hostaway_checkin_date');
        $checkout_date = $order->get_meta('_hostaway_checkout_date');
        $guest_count = $order->get_meta('_hostaway_guest_count');
        $reservation_id = $order->get_meta('_hostaway_reservation_id');
        
        $property = $this->get_property_details($property_id);
        
        ?>
        <div class="hostaway-booking-confirmation">
            <h2><?php _e('Booking Confirmation', 'hostaway-sync'); ?></h2>
            
            <div class="booking-details">
                <div class="property-info">
                    <h3><?php echo esc_html($property->name); ?></h3>
                    <p><?php echo esc_html($property->city . ', ' . $property->country); ?></p>
                </div>
                
                <div class="booking-dates">
                    <p><strong><?php _e('Check-in:', 'hostaway-sync'); ?></strong> <?php echo esc_html($checkin_date); ?></p>
                    <p><strong><?php _e('Check-out:', 'hostaway-sync'); ?></strong> <?php echo esc_html($checkout_date); ?></p>
                    <p><strong><?php _e('Guests:', 'hostaway-sync'); ?></strong> <?php echo esc_html($guest_count); ?></p>
                </div>
                
                <?php if ($reservation_id): ?>
                <div class="reservation-info">
                    <p><strong><?php _e('Reservation ID:', 'hostaway-sync'); ?></strong> <?php echo esc_html($reservation_id); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="booking-instructions">
                <h3><?php _e('What\'s Next?', 'hostaway-sync'); ?></h3>
                <ul>
                    <li><?php _e('You will receive a confirmation email shortly', 'hostaway-sync'); ?></li>
                    <li><?php _e('Check-in details will be sent 24 hours before arrival', 'hostaway-sync'); ?></li>
                    <li><?php _e('Contact the property directly for any special requests', 'hostaway-sync'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Create WooCommerce cart item for booking
     */
    public function create_booking_cart_item($property_id, $booking_data) {
        $property = $this->get_property_details($property_id);
        
        if (!$property) {
            return false;
        }
        
        // Calculate total price
        $nights = $this->calculate_nights($booking_data['checkin_date'], $booking_data['checkout_date']);
        $base_price = $property->base_price * $nights;
        $extras_total = $this->calculate_extras_total($booking_data['extras'] ?? array());
        $total_price = $base_price + $extras_total;
        
        // Create cart item data
        $cart_item_data = array(
            'hostaway_booking' => array(
                'property_id' => $property_id,
                'property_name' => $property->name,
                'checkin_date' => $booking_data['checkin_date'],
                'checkout_date' => $booking_data['checkout_date'],
                'guest_count' => $booking_data['guest_count'],
                'nights' => $nights,
                'base_price' => $base_price,
                'extras' => $booking_data['extras'] ?? array(),
                'extras_total' => $extras_total,
                'total_price' => $total_price
            )
        );
        
        // Add to cart
        $cart_item_key = WC()->cart->add_to_cart(
            0, // No product ID for custom items
            1, // Quantity
            0, // Variation ID
            array(), // Variation
            $cart_item_data
        );
        
        return $cart_item_key;
    }
    
    /**
     * Check if property is available for given dates
     */
    private function is_property_available($property_id, $checkin_date, $checkout_date) {
        global $wpdb;
        
        $availability_table = Database::get_availability_table();
        
        $checkin = new \DateTime($checkin_date);
        $checkout = new \DateTime($checkout_date);
        
        $date_range = array();
        while ($checkin < $checkout) {
            $date_range[] = $checkin->format('Y-m-d');
            $checkin->add(new \DateInterval('P1D'));
        }
        
        $placeholders = implode(',', array_fill(0, count($date_range), '%s'));
        
        $unavailable_dates = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT date FROM $availability_table WHERE property_id = %d AND date IN ($placeholders) AND available = 0",
                array_merge(array($property_id), $date_range)
            )
        );
        
        return empty($unavailable_dates);
    }
    
    /**
     * Get Hostaway property ID from our database ID
     */
    private function get_hostaway_property_id($property_id) {
        global $wpdb;
        
        $properties_table = Database::get_properties_table();
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT hostaway_id FROM $properties_table WHERE id = %d",
                $property_id
            )
        );
    }
    
    /**
     * Store reservation in database
     */
    private function store_reservation($order_id, $reservation_id, $booking_data, $hostaway_response) {
        global $wpdb;
        
        $reservations_table = Database::get_reservations_table();
        
        $wpdb->insert(
            $reservations_table,
            array(
                'woocommerce_order_id' => $order_id,
                'hostaway_reservation_id' => $reservation_id,
                'property_id' => $booking_data['property_id'],
                'checkin_date' => $booking_data['checkin_date'],
                'checkout_date' => $booking_data['checkout_date'],
                'guest_count' => $booking_data['guest_count'],
                'total_amount' => $booking_data['total_price'],
                'status' => 'confirmed',
                'hostaway_response' => wp_json_encode($hostaway_response)
            ),
            array('%d', '%s', '%d', '%s', '%s', '%d', '%f', '%s', '%s')
        );
    }
    
    /**
     * Update reservation status
     */
    private function update_reservation_status($order_id, $status) {
        global $wpdb;
        
        $reservations_table = Database::get_reservations_table();
        
        $wpdb->update(
            $reservations_table,
            array('status' => $status),
            array('woocommerce_order_id' => $order_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Get property details
     */
    private function get_property_details($property_id) {
        global $wpdb;
        
        $properties_table = Database::get_properties_table();
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $properties_table WHERE id = %d",
                $property_id
            )
        );
    }
    
    /**
     * Calculate nights between dates
     */
    private function calculate_nights($checkin_date, $checkout_date) {
        $checkin = new \DateTime($checkin_date);
        $checkout = new \DateTime($checkout_date);
        $diff = $checkin->diff($checkout);
        return $diff->days;
    }
    
    /**
     * Calculate extras total
     */
    private function calculate_extras_total($extras) {
        $total = 0;
        
        foreach ($extras as $extra) {
            if (isset($extra['price'])) {
                $total += floatval($extra['price']);
            }
        }
        
        return $total;
    }
}
