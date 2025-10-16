<?php

namespace HostawayWP\Admin;

/**
 * Admin functionality
 */
class Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', [$this, 'init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }
    
    /**
     * Initialize admin
     */
    public function init() {
        // Add meta boxes for property post type
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'saveMetaBoxes']);
        
        // Add custom columns to properties list
        add_filter('manage_hostaway_property_posts_columns', [$this, 'addCustomColumns']);
        add_action('manage_hostaway_property_posts_custom_column', [$this, 'renderCustomColumns'], 10, 2);
        
        // Add bulk actions
        add_filter('bulk_actions-edit-hostaway_property', [$this, 'addBulkActions']);
        add_filter('handle_bulk_actions-edit-hostaway_property', [$this, 'handleBulkActions'], 10, 3);
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueueScripts($hook) {
        if (strpos($hook, 'hostaway') !== false || $hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_script('jquery');
            wp_enqueue_media();
            
            wp_enqueue_script(
                'hostaway-admin',
                HOSTAWAY_WP_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                HOSTAWAY_WP_VERSION,
                true
            );
            
            wp_enqueue_style(
                'hostaway-admin',
                HOSTAWAY_WP_PLUGIN_URL . 'assets/css/admin.css',
                [],
                HOSTAWAY_WP_VERSION
            );
            
            wp_localize_script('hostaway-admin', 'hostawayAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hostaway_admin_nonce'),
                'strings' => [
                    'syncSuccess' => __('Sync completed successfully', 'hostaway-wp'),
                    'syncError' => __('Sync failed', 'hostaway-wp'),
                    'testSuccess' => __('API connection successful', 'hostaway-wp'),
                    'testError' => __('API connection failed', 'hostaway-wp'),
                ],
            ]);
        }
    }
    
    /**
     * Add meta boxes
     */
    public function addMetaBoxes() {
        add_meta_box(
            'hostaway-property-details',
            __('Property Details', 'hostaway-wp'),
            [$this, 'renderPropertyDetailsMetaBox'],
            'hostaway_property',
            'normal',
            'high'
        );
        
        add_meta_box(
            'hostaway-property-images',
            __('Property Images', 'hostaway-wp'),
            [$this, 'renderPropertyImagesMetaBox'],
            'hostaway_property',
            'side',
            'high'
        );
        
        add_meta_box(
            'hostaway-property-sync',
            __('Sync Status', 'hostaway-wp'),
            [$this, 'renderSyncStatusMetaBox'],
            'hostaway_property',
            'side',
            'default'
        );
    }
    
    /**
     * Render property details meta box
     */
    public function renderPropertyDetailsMetaBox($post) {
        wp_nonce_field('hostaway_property_meta', 'hostaway_property_meta_nonce');
        
        $meta = get_post_meta($post->ID);
        $hostaway_id = $meta['_hostaway_id'][0] ?? '';
        $property_type = $meta['_property_type'][0] ?? '';
        $location = $meta['_location'][0] ?? '';
        $rooms = $meta['_rooms'][0] ?? 0;
        $bathrooms = $meta['_bathrooms'][0] ?? 0;
        $guests = $meta['_guests'][0] ?? 0;
        $base_price = $meta['_base_price'][0] ?? 0;
        $amenities = $meta['_amenities'][0] ?? '';
        $features = $meta['_features'][0] ?? '';
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="hostaway_id"><?php esc_html_e('Hostaway ID', 'hostaway-wp'); ?></label></th>
                <td>
                    <input type="text" id="hostaway_id" name="hostaway_id" value="<?php echo esc_attr($hostaway_id); ?>" class="regular-text" readonly />
                    <p class="description"><?php esc_html_e('This ID is automatically set from Hostaway', 'hostaway-wp'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th><label for="property_type"><?php esc_html_e('Property Type', 'hostaway-wp'); ?></label></th>
                <td>
                    <input type="text" id="property_type" name="property_type" value="<?php echo esc_attr($property_type); ?>" class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th><label for="location"><?php esc_html_e('Location', 'hostaway-wp'); ?></label></th>
                <td>
                    <input type="text" id="location" name="location" value="<?php echo esc_attr($location); ?>" class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th><label for="rooms"><?php esc_html_e('Rooms', 'hostaway-wp'); ?></label></th>
                <td>
                    <input type="number" id="rooms" name="rooms" value="<?php echo esc_attr($rooms); ?>" min="0" class="small-text" />
                </td>
            </tr>
            
            <tr>
                <th><label for="bathrooms"><?php esc_html_e('Bathrooms', 'hostaway-wp'); ?></label></th>
                <td>
                    <input type="number" id="bathrooms" name="bathrooms" value="<?php echo esc_attr($bathrooms); ?>" min="0" class="small-text" />
                </td>
            </tr>
            
            <tr>
                <th><label for="guests"><?php esc_html_e('Max Guests', 'hostaway-wp'); ?></label></th>
                <td>
                    <input type="number" id="guests" name="guests" value="<?php echo esc_attr($guests); ?>" min="1" class="small-text" />
                </td>
            </tr>
            
            <tr>
                <th><label for="base_price"><?php esc_html_e('Base Price', 'hostaway-wp'); ?></label></th>
                <td>
                    <input type="number" id="base_price" name="base_price" value="<?php echo esc_attr($base_price); ?>" step="0.01" min="0" class="small-text" />
                    <span><?php echo esc_html(get_option('hostaway_wp_currency', 'USD')); ?></span>
                </td>
            </tr>
            
            <tr>
                <th><label for="amenities"><?php esc_html_e('Amenities', 'hostaway-wp'); ?></label></th>
                <td>
                    <textarea id="amenities" name="amenities" rows="5" class="large-text"><?php echo esc_textarea($amenities); ?></textarea>
                    <p class="description"><?php esc_html_e('One amenity per line', 'hostaway-wp'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th><label for="features"><?php esc_html_e('Features', 'hostaway-wp'); ?></label></th>
                <td>
                    <textarea id="features" name="features" rows="5" class="large-text"><?php echo esc_textarea($features); ?></textarea>
                    <p class="description"><?php esc_html_e('One feature per line', 'hostaway-wp'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render property images meta box
     */
    public function renderPropertyImagesMetaBox($post) {
        $gallery_ids = get_post_meta($post->ID, '_gallery_ids', true);
        $gallery_ids = is_array($gallery_ids) ? $gallery_ids : [];
        
        ?>
        <div id="property-gallery">
            <div class="gallery-preview">
                <?php if (!empty($gallery_ids)): ?>
                    <?php foreach ($gallery_ids as $id): ?>
                        <?php $image = wp_get_attachment_image($id, 'thumbnail'); ?>
                        <?php if ($image): ?>
                            <div class="gallery-item" data-id="<?php echo esc_attr($id); ?>">
                                <?php echo $image; ?>
                                <button type="button" class="remove-gallery-item">×</button>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" id="add-gallery-images" class="button button-secondary">
                <?php esc_html_e('Add Images', 'hostaway-wp'); ?>
            </button>
            
            <input type="hidden" id="gallery_ids" name="gallery_ids" value="<?php echo esc_attr(implode(',', $gallery_ids)); ?>" />
        </div>
        <?php
    }
    
    /**
     * Render sync status meta box
     */
    public function renderSyncStatusMetaBox($post) {
        $last_sync = get_post_meta($post->ID, '_last_sync', true);
        $sync_status = get_post_meta($post->ID, '_sync_status', true);
        
        ?>
        <div class="sync-status">
            <p>
                <strong><?php esc_html_e('Last Sync:', 'hostaway-wp'); ?></strong>
                <?php echo $last_sync ? esc_html($last_sync) : __('Never', 'hostaway-wp'); ?>
            </p>
            
            <p>
                <strong><?php esc_html_e('Status:', 'hostaway-wp'); ?></strong>
                <span class="status-<?php echo esc_attr($sync_status ?: 'unknown'); ?>">
                    <?php echo esc_html(ucfirst($sync_status ?: 'unknown')); ?>
                </span>
            </p>
            
            <button type="button" class="button button-secondary sync-single-property" data-property-id="<?php echo esc_attr($post->ID); ?>">
                <?php esc_html_e('Sync Now', 'hostaway-wp'); ?>
            </button>
        </div>
        <?php
    }
    
    /**
     * Save meta boxes
     */
    public function saveMetaBoxes($post_id) {
        // Check nonce
        if (!isset($_POST['hostaway_property_meta_nonce']) || 
            !wp_verify_nonce($_POST['hostaway_property_meta_nonce'], 'hostaway_property_meta')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check post type
        if (get_post_type($post_id) !== 'hostaway_property') {
            return;
        }
        
        // Save meta fields
        $fields = [
            'hostaway_id' => '_hostaway_id',
            'property_type' => '_property_type',
            'location' => '_location',
            'rooms' => '_rooms',
            'bathrooms' => '_bathrooms',
            'guests' => '_guests',
            'base_price' => '_base_price',
            'amenities' => '_amenities',
            'features' => '_features',
        ];
        
        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                update_post_meta($post_id, $meta_key, $value);
            }
        }
        
        // Save gallery IDs
        if (isset($_POST['gallery_ids'])) {
            $gallery_ids = array_filter(array_map('intval', explode(',', $_POST['gallery_ids'])));
            update_post_meta($post_id, '_gallery_ids', $gallery_ids);
        }
    }
    
    /**
     * Add custom columns
     */
    public function addCustomColumns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'title') {
                $new_columns['hostaway_id'] = __('Hostaway ID', 'hostaway-wp');
                $new_columns['property_type'] = __('Type', 'hostaway-wp');
                $new_columns['location'] = __('Location', 'hostaway-wp');
                $new_columns['price'] = __('Price', 'hostaway-wp');
                $new_columns['sync_status'] = __('Sync Status', 'hostaway-wp');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render custom columns
     */
    public function renderCustomColumns($column, $post_id) {
        switch ($column) {
            case 'hostaway_id':
                echo esc_html(get_post_meta($post_id, '_hostaway_id', true));
                break;
                
            case 'property_type':
                echo esc_html(get_post_meta($post_id, '_property_type', true));
                break;
                
            case 'location':
                echo esc_html(get_post_meta($post_id, '_location', true));
                break;
                
            case 'price':
                $price = get_post_meta($post_id, '_base_price', true);
                $currency = get_option('hostaway_wp_currency', 'USD');
                echo esc_html($currency . ' ' . $price);
                break;
                
            case 'sync_status':
                $status = get_post_meta($post_id, '_sync_status', true);
                $class = $status ?: 'unknown';
                echo '<span class="status-' . esc_attr($class) . '">' . esc_html(ucfirst($class)) . '</span>';
                break;
        }
    }
    
    /**
     * Add bulk actions
     */
    public function addBulkActions($actions) {
        $actions['sync_properties'] = __('Sync Properties', 'hostaway-wp');
        return $actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handleBulkActions($redirect_to, $doaction, $post_ids) {
        if ($doaction === 'sync_properties') {
            $synced = 0;
            
            foreach ($post_ids as $post_id) {
                // Trigger individual property sync
                do_action('hostaway_wp_sync_single_property', $post_id);
                $synced++;
            }
            
            $redirect_to = add_query_arg('synced', $synced, $redirect_to);
        }
        
        return $redirect_to;
    }
}
