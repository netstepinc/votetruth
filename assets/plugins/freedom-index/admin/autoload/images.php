<?php
namespace FI\Admin;

if (!defined('ABSPATH')) exit;

/**
 * Image Fallback Helper for Freedom Index Admin
 * 
 * Provides image fallback system with WP Media Library integration.
 * Handles session-specific images falling back to career default images.
 */
final class ImageHelper {

    /**
     * Get legislator image with fallback
     */
    public static function get_legislator_image(int $legislator_id, ?int $session_id = null, string $size = 'medium'): ?string {
        // Try session-specific image first
        if ($session_id) {
            $session_image = self::get_session_image($legislator_id, $session_id, $size);
            if ($session_image) {
                return $session_image;
            }
        }
        
        // Fall back to career default image
        $career_image = self::get_career_image($legislator_id, $size);
        if ($career_image) {
            return $career_image;
        }
        
        // Return default placeholder
        return self::get_default_image($size);
    }

    /**
     * Get session-specific image
     */
    private static function get_session_image(int $legislator_id, int $session_id, string $size): ?string {
        global $wpdb;
        
        $session_data = $wpdb->get_row($wpdb->prepare(
            "SELECT image_id FROM {$wpdb->prefix}fi_legislator_sessions 
             WHERE legislator_id = %d AND session_id = %d",
            $legislator_id, $session_id
        ));
        
        if (!$session_data) {
            return null;
        }
        
        // Use WP Media Library image
        if ($session_data->image_id) {
            $image_url = wp_get_attachment_image_url($session_data->image_id, $size);
            if ($image_url) {
                return $image_url;
            }
        }
        
        return null;
    }

    /**
     * Get career default image
     */
    private static function get_career_image(int $legislator_id, string $size): ?string {
        global $wpdb;
        
        $legislator = $wpdb->get_row($wpdb->prepare(
            "SELECT image_id FROM {$wpdb->prefix}fi_legislators WHERE id = %d",
            $legislator_id
        ));
        
        if (!$legislator || !$legislator->image_id) {
            return null;
        }
        
        $image_url = wp_get_attachment_image_url($legislator->image_id, $size);
        return $image_url ?: null;
    }

    /**
     * Get default placeholder image
     */
    private static function get_default_image(string $size): string {
        $default_images = [
            'thumbnail' => plugin_dir_url(__FILE__) . '../assets/default-thumbnail.jpg',
            'medium' => plugin_dir_url(__FILE__) . '../assets/default-medium.jpg',
            'large' => plugin_dir_url(__FILE__) . '../assets/default-large.jpg',
            'full' => plugin_dir_url(__FILE__) . '../assets/default-full.jpg'
        ];
        
        return $default_images[$size] ?? $default_images['medium'];
    }

    /**
     * Upload image to WP Media Library
     */
    public static function upload_image(string $image_url, string $title = '', string $alt = ''): ?int {
        if (empty($image_url)) {
            return null;
        }
        
        // Check if image already exists
        $existing_id = self::get_attachment_by_url($image_url);
        if ($existing_id) {
            return $existing_id;
        }
        
        // Download image
        $image_data = wp_remote_get($image_url);
        if (is_wp_error($image_data)) {
            return null;
        }
        
        $image_body = wp_remote_retrieve_body($image_data);
        if (empty($image_body)) {
            return null;
        }
        
        // Get file extension
        $file_extension = pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (empty($file_extension)) {
            $file_extension = 'jpg'; // Default
        }
        
        // Create filename
        $filename = sanitize_file_name($title ?: 'image') . '.' . $file_extension;
        
        // Upload to media library
        $upload = wp_upload_bits($filename, null, $image_body);
        if ($upload['error']) {
            return null;
        }
        
        // Create attachment
        $attachment = [
            'post_title' => $title ?: 'Legislator Image',
            'post_content' => '',
            'post_status' => 'inherit',
            'post_mime_type' => wp_check_filetype($filename)['type']
        ];
        
        $attachment_id = wp_insert_attachment($attachment, $upload['file']);
        if (!$attachment_id) {
            return null;
        }
        
        // Generate attachment metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // Set alt text
        if ($alt) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        }
        
        return $attachment_id;
    }

    /**
     * Get attachment by URL
     */
    private static function get_attachment_by_url(string $url): ?int {
        global $wpdb;
        
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}postmeta 
             WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
            basename($url)
        ));
        
        return $attachment_id ? (int) $attachment_id : null;
    }

    /**
     * Resize image using JBS Image Resizer
     */
    public static function resize_image(int $attachment_id, string $size): ?string {
        // Check if JBS Image Resizer is available
        if (!function_exists('jbs_resize_image')) {
            return wp_get_attachment_image_url($attachment_id, $size);
        }
        
        $original_url = wp_get_attachment_url($attachment_id);
        if (!$original_url) {
            return null;
        }
        
        // Get size dimensions
        $size_data = self::get_image_size_data($size);
        if (!$size_data) {
            return $original_url;
        }
        
        // Resize image
        $resized_url = jbs_resize_image($original_url, $size_data['width'], $size_data['height'], true);
        
        return $resized_url ?: $original_url;
    }

    /**
     * Get image size data
     */
    private static function get_image_size_data(string $size): ?array {
        $sizes = [
            'thumbnail' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 300, 'height' => 300],
            'large' => ['width' => 600, 'height' => 600],
            'full' => ['width' => 1200, 'height' => 1200]
        ];
        
        return $sizes[$size] ?? null;
    }

    /**
     * Get image HTML with fallback
     */
    public static function get_legislator_image_html(int $legislator_id, ?int $session_id = null, string $size = 'medium', array $attributes = []): string {
        $image_url = self::get_legislator_image($legislator_id, $session_id, $size);
        
        if (!$image_url) {
            return '';
        }
        
        $default_attributes = [
            'src' => $image_url,
            'alt' => 'Legislator Image',
            'class' => 'fi-legislator-image'
        ];
        
        $attributes = array_merge($default_attributes, $attributes);
        
        $html = '<img';
        foreach ($attributes as $key => $value) {
            $html .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        $html .= '>';
        
        return $html;
    }

    /**
     * Get image dimensions
     */
    public static function get_image_dimensions(string $image_url): ?array {
        $image_data = wp_remote_get($image_url);
        if (is_wp_error($image_data)) {
            return null;
        }
        
        $image_body = wp_remote_retrieve_body($image_data);
        if (empty($image_body)) {
            return null;
        }
        
        $image_info = getimagesizefromstring($image_body);
        if (!$image_info) {
            return null;
        }
        
        return [
            'width' => $image_info[0],
            'height' => $image_info[1],
            'mime_type' => $image_info['mime']
        ];
    }

    /**
     * Validate image URL
     */
    public static function validate_image_url(string $url): bool {
        if (empty($url)) {
            return false;
        }
        
        // Check if URL is valid
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Check if URL is accessible
        $response = wp_remote_head($url);
        if (is_wp_error($response)) {
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return false;
        }
        
        // Check if it's an image
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (!str_starts_with($content_type, 'image/')) {
            return false;
        }
        
        return true;
    }

    /**
     * Get image metadata
     */
    public static function get_image_metadata(int $attachment_id): array {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata) {
            return [];
        }
        
        $file_url = wp_get_attachment_url($attachment_id);
        $file_size = filesize(get_attached_file($attachment_id));
        
        return [
            'id' => $attachment_id,
            'url' => $file_url,
            'width' => $metadata['width'] ?? 0,
            'height' => $metadata['height'] ?? 0,
            'file_size' => $file_size,
            'mime_type' => get_post_mime_type($attachment_id),
            'alt_text' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'caption' => wp_get_attachment_caption($attachment_id),
            'description' => get_post_field('post_content', $attachment_id)
        ];
    }

    /**
     * Clean up orphaned images
     */
    public static function cleanup_orphaned_images(): int {
        global $wpdb;
        
        $cleaned = 0;
        
        // Find orphaned attachments
        $orphaned_attachments = $wpdb->get_results(
            "SELECT p.ID FROM {$wpdb->prefix}posts p
             WHERE p.post_type = 'attachment'
             AND p.ID NOT IN (
                 SELECT DISTINCT image_id FROM {$wpdb->prefix}fi_legislators WHERE image_id IS NOT NULL
                 UNION
                 SELECT DISTINCT image_id FROM {$wpdb->prefix}fi_legislator_sessions WHERE image_id IS NOT NULL
             )"
        );
        
        foreach ($orphaned_attachments as $attachment) {
            if (wp_delete_attachment($attachment->ID, true)) {
                $cleaned++;
            }
        }
        
        return $cleaned;
    }

    /**
     * Get image statistics
     */
    public static function get_image_stats(): array {
        global $wpdb;
        
        $stats = [];
        
        // Count images in use
        $stats['legislator_images'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}fi_legislators WHERE image_id IS NOT NULL"
        );
        
        $stats['session_images'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}fi_legislator_sessions WHERE image_id IS NOT NULL"
        );
        
        // Count total attachments
        $stats['total_attachments'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'attachment'"
        );
        
        // Count orphaned images
        $stats['orphaned_images'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}posts p
             WHERE p.post_type = 'attachment'
             AND p.ID NOT IN (
                 SELECT DISTINCT image_id FROM {$wpdb->prefix}fi_legislators WHERE image_id IS NOT NULL
                 UNION
                 SELECT DISTINCT image_id FROM {$wpdb->prefix}fi_legislator_sessions WHERE image_id IS NOT NULL
             )"
        );
        
        return $stats;
    }

    /**
     * Generate image sizes
     */
    public static function generate_image_sizes(int $attachment_id): bool {
        if (!function_exists('jbs_resize_image')) {
            return false;
        }
        
        $original_url = wp_get_attachment_url($attachment_id);
        if (!$original_url) {
            return false;
        }
        
        $sizes = ['thumbnail', 'medium', 'large'];
        $generated = 0;
        
        foreach ($sizes as $size) {
            $size_data = self::get_image_size_data($size);
            if (!$size_data) {
                continue;
            }
            
            $resized_url = jbs_resize_image($original_url, $size_data['width'], $size_data['height'], true);
            if ($resized_url) {
                $generated++;
            }
        }
        
        return $generated > 0;
    }
}
