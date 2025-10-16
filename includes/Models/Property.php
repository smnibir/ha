<?php

namespace HostawayWP\Models;

/**
 * Property model
 */
class Property {
    
    /**
     * Database table name
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'hostaway_properties';
    }
    
    /**
     * Get property by ID
     */
    public function getById($id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        );
        
        return $wpdb->get_row($query, ARRAY_A);
    }
    
    /**
     * Get property by Hostaway ID
     */
    public function getByHostawayId($hostaway_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE hostaway_id = %s",
            $hostaway_id
        );
        
        return $wpdb->get_row($query, ARRAY_A);
    }
    
    /**
     * Get property by slug
     */
    public function getBySlug($slug) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE slug = %s",
            $slug
        );
        
        return $wpdb->get_row($query, ARRAY_A);
    }
    
    /**
     * Create or update property
     */
    public function save($data) {
        global $wpdb;
        
        // Check if property exists
        $existing = $this->getByHostawayId($data['hostaway_id']);
        
        $property_data = [
            'hostaway_id' => $data['hostaway_id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'type' => $data['type'],
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'rooms' => $data['rooms'] ?? 0,
            'bathrooms' => $data['bathrooms'] ?? 0,
            'guests' => $data['guests'] ?? 0,
            'base_price' => $data['base_price'] ?? 0.00,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'gallery_json' => $data['gallery_json'] ?? null,
            'amenities_json' => $data['amenities_json'] ?? null,
            'features_json' => $data['features_json'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];
        
        if ($existing) {
            // Update existing property
            $property_data['id'] = $existing['id'];
            $wpdb->update(
                $this->table_name,
                $property_data,
                ['id' => $existing['id']],
                [
                    '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f',
                    '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s'
                ],
                ['%d']
            );
            
            $property_id = $existing['id'];
        } else {
            // Create new property
            $wpdb->insert(
                $this->table_name,
                $property_data,
                [
                    '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f',
                    '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );
            
            $property_id = $wpdb->insert_id;
        }
        
        // Create or update WordPress post
        $this->createOrUpdatePost($property_id, $property_data);
        
        return $property_id;
    }
    
    /**
     * Create or update WordPress post
     */
    private function createOrUpdatePost($property_id, $data) {
        $existing_post = get_posts([
            'meta_query' => [
                [
                    'key' => '_hostaway_property_id',
                    'value' => $property_id,
                    'compare' => '=',
                ],
            ],
            'post_type' => 'hostaway_property',
            'posts_per_page' => 1,
        ]);
        
        $post_data = [
            'post_title' => $data['title'],
            'post_name' => $data['slug'],
            'post_content' => $data['description'],
            'post_type' => 'hostaway_property',
            'post_status' => 'publish',
        ];
        
        if (!empty($existing_post)) {
            $post_data['ID'] = $existing_post[0]->ID;
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }
        
        if ($post_id && !is_wp_error($post_id)) {
            // Set meta fields
            update_post_meta($post_id, '_hostaway_property_id', $property_id);
            update_post_meta($post_id, '_hostaway_id', $data['hostaway_id']);
            update_post_meta($post_id, '_property_type', $data['type']);
            update_post_meta($post_id, '_location', $data['city'] . ', ' . $data['country']);
            update_post_meta($post_id, '_rooms', $data['rooms']);
            update_post_meta($post_id, '_bathrooms', $data['bathrooms']);
            update_post_meta($post_id, '_guests', $data['guests']);
            update_post_meta($post_id, '_base_price', $data['base_price']);
            
            // Set featured image
            if ($data['thumbnail_url']) {
                $this->setFeaturedImage($post_id, $data['thumbnail_url']);
            }
            
            // Set gallery
            if ($data['gallery_json']) {
                $gallery = json_decode($data['gallery_json'], true);
                $this->setGallery($post_id, $gallery);
            }
        }
        
        return $post_id;
    }
    
    /**
     * Set featured image from URL
     */
    private function setFeaturedImage($post_id, $image_url) {
        $existing_attachment = get_posts([
            'meta_query' => [
                [
                    'key' => '_hostaway_image_url',
                    'value' => $image_url,
                    'compare' => '=',
                ],
            ],
            'post_type' => 'attachment',
            'posts_per_page' => 1,
        ]);
        
        if (!empty($existing_attachment)) {
            set_post_thumbnail($post_id, $existing_attachment[0]->ID);
            return $existing_attachment[0]->ID;
        }
        
        // Download and create attachment
        $attachment_id = $this->downloadImage($image_url);
        
        if ($attachment_id && !is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
            update_post_meta($attachment_id, '_hostaway_image_url', $image_url);
            update_post_meta($attachment_id, 'hostaway_wp_uploaded', '1');
        }
        
        return $attachment_id;
    }
    
    /**
     * Set gallery from URLs
     */
    private function setGallery($post_id, $gallery_urls) {
        $gallery_ids = [];
        
        foreach ($gallery_urls as $image_url) {
            $existing_attachment = get_posts([
                'meta_query' => [
                    [
                        'key' => '_hostaway_image_url',
                        'value' => $image_url,
                        'compare' => '=',
                    ],
                ],
                'post_type' => 'attachment',
                'posts_per_page' => 1,
            ]);
            
            if (!empty($existing_attachment)) {
                $gallery_ids[] = $existing_attachment[0]->ID;
                continue;
            }
            
            // Download and create attachment
            $attachment_id = $this->downloadImage($image_url);
            
            if ($attachment_id && !is_wp_error($attachment_id)) {
                $gallery_ids[] = $attachment_id;
                update_post_meta($attachment_id, '_hostaway_image_url', $image_url);
                update_post_meta($attachment_id, 'hostaway_wp_uploaded', '1');
            }
        }
        
        update_post_meta($post_id, '_gallery_ids', $gallery_ids);
    }
    
    /**
     * Download image from URL
     */
    private function downloadImage($url) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        
        $file_array = [
            'name' => basename($url),
            'tmp_name' => $tmp,
        ];
        
        $attachment_id = media_handle_sideload($file_array, 0);
        
        // Clean up temp file
        @unlink($tmp);
        
        return $attachment_id;
    }
    
    /**
     * Search properties
     */
    public function search($params = []) {
        global $wpdb;
        
        $where_conditions = ['status = "active"'];
        $where_values = [];
        
        // Search by text
        if (!empty($params['search'])) {
            $where_conditions[] = '(title LIKE %s OR description LIKE %s OR address LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($params['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        // Search by location
        if (!empty($params['location'])) {
            $where_conditions[] = '(city LIKE %s OR country LIKE %s OR address LIKE %s)';
            $location_term = '%' . $wpdb->esc_like($params['location']) . '%';
            $where_values[] = $location_term;
            $where_values[] = $location_term;
            $where_values[] = $location_term;
        }
        
        // Filter by guests
        if (!empty($params['guests']) && $params['guests'] > 0) {
            $where_conditions[] = 'guests >= %d';
            $where_values[] = $params['guests'];
        }
        
        // Filter by rooms
        if (!empty($params['rooms']) && $params['rooms'] > 0) {
            $where_conditions[] = 'rooms >= %d';
            $where_values[] = $params['rooms'];
        }
        
        // Filter by bathrooms
        if (!empty($params['bathrooms']) && $params['bathrooms'] > 0) {
            $where_conditions[] = 'bathrooms >= %d';
            $where_values[] = $params['bathrooms'];
        }
        
        // Filter by price range
        if (!empty($params['price_min']) && $params['price_min'] > 0) {
            $where_conditions[] = 'base_price >= %f';
            $where_values[] = $params['price_min'];
        }
        
        if (!empty($params['price_max']) && $params['price_max'] > 0) {
            $where_conditions[] = 'base_price <= %f';
            $where_values[] = $params['price_max'];
        }
        
        // Filter by amenities
        if (!empty($params['amenities']) && is_array($params['amenities'])) {
            $amenity_conditions = [];
            foreach ($params['amenities'] as $amenity) {
                $amenity_conditions[] = 'amenities_json LIKE %s';
                $where_values[] = '%' . $wpdb->esc_like($amenity) . '%';
            }
            if (!empty($amenity_conditions)) {
                $where_conditions[] = '(' . implode(' OR ', $amenity_conditions) . ')';
            }
        }
        
        // Build query
        $where_clause = implode(' AND ', $where_conditions);
        
        // Count total results
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        $total = $wpdb->get_var($count_query);
        
        // Get paginated results
        $page = max(1, intval($params['page'] ?? 1));
        $per_page = max(1, min(50, intval($params['per_page'] ?? 15)));
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$per_page, $offset]);
        $query = $wpdb->prepare($query, $query_values);
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return [
            'properties' => $results,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ];
    }
    
    /**
     * Get properties for map
     */
    public function getForMap($params = []) {
        global $wpdb;
        
        $where_conditions = ['status = "active"'];
        $where_values = [];
        
        // Only get properties with coordinates
        $where_conditions[] = 'latitude IS NOT NULL AND longitude IS NOT NULL';
        
        // Apply same filters as search
        if (!empty($params['search'])) {
            $where_conditions[] = '(title LIKE %s OR description LIKE %s OR address LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($params['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($params['location'])) {
            $where_conditions[] = '(city LIKE %s OR country LIKE %s OR address LIKE %s)';
            $location_term = '%' . $wpdb->esc_like($params['location']) . '%';
            $where_values[] = $location_term;
            $where_values[] = $location_term;
            $where_values[] = $location_term;
        }
        
        if (!empty($params['guests']) && $params['guests'] > 0) {
            $where_conditions[] = 'guests >= %d';
            $where_values[] = $params['guests'];
        }
        
        if (!empty($params['rooms']) && $params['rooms'] > 0) {
            $where_conditions[] = 'rooms >= %d';
            $where_values[] = $params['rooms'];
        }
        
        if (!empty($params['bathrooms']) && $params['bathrooms'] > 0) {
            $where_conditions[] = 'bathrooms >= %d';
            $where_values[] = $params['bathrooms'];
        }
        
        if (!empty($params['price_min']) && $params['price_min'] > 0) {
            $where_conditions[] = 'base_price >= %f';
            $where_values[] = $params['price_min'];
        }
        
        if (!empty($params['price_max']) && $params['price_max'] > 0) {
            $where_conditions[] = 'base_price <= %f';
            $where_values[] = $params['price_max'];
        }
        
        if (!empty($params['amenities']) && is_array($params['amenities'])) {
            $amenity_conditions = [];
            foreach ($params['amenities'] as $amenity) {
                $amenity_conditions[] = 'amenities_json LIKE %s';
                $where_values[] = '%' . $wpdb->esc_like($amenity) . '%';
            }
            if (!empty($amenity_conditions)) {
                $where_conditions[] = '(' . implode(' OR ', $amenity_conditions) . ')';
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT id, title, slug, type, latitude, longitude, base_price, thumbnail_url, guests, rooms, bathrooms FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get all properties
     */
    public function getAll($limit = 50, $offset = 0) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE status = 'active' ORDER BY updated_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Delete property
     */
    public function delete($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            ['id' => $id],
            ['%d']
        );
    }
}
