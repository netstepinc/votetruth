<?php
namespace FI\Public{
	/**
	* Freedom Index Image Manager
	* 
	* Handles image downloads, storage, and placeholder generation
	*/
	if (!defined('ABSPATH')) exit;

	class ImageManager {
		
		/**
		* Download and store image from URL
		* 
		* @param string $image_url URL of image to download
		* @param string $alt_text Alt text for the image
		* @return int|null WordPress attachment ID or null on failure
		*/
		public static function download_and_store_image(string $image_url, string $alt_text = ''): ?int {
			if (empty($image_url)) {
				return null;
			}
			
			// Check if image already exists
			$existing_id = self::get_attachment_by_url($image_url);
			if ($existing_id) {
				return $existing_id;
			}
			
			// Download image
			$response = wp_remote_get($image_url, [
				'timeout' => 30,
				'headers' => [
					'User-Agent' => 'Freedom Index Plugin/1.0'
				]
			]);
			
			if (is_wp_error($response)) {
				self::log('Failed to download image: ' . $response->get_error_message() . ' | URL: ' . $image_url, __FILE__, __LINE__, 'warning');
				return null;
			}
			
			$image_data = wp_remote_retrieve_body($response);
			if (empty($image_data)) {
				return null;
			}
			
			// Get file extension
			$file_extension = self::get_file_extension_from_url($image_url);
			if (!$file_extension) {
				$file_extension = 'jpg'; // Default fallback
			}
			
			// Generate filename
			$filename = sanitize_file_name($alt_text) . '-' . uniqid() . '.' . $file_extension;
			
			// Upload to WordPress media library
			$upload = wp_upload_bits($filename, null, $image_data);
			
			if ($upload['error']) {
				self::log('Failed to upload image: ' . $upload['error'] . ' | URL: ' . $image_url . ' | Filename: ' . $filename, __FILE__, __LINE__, 'error');
				return null;
			}
			
			// Create attachment
			$attachment = [
				'post_mime_type' => wp_check_filetype($filename)['type'],
				'post_title' => sanitize_text_field($alt_text),
				'post_content' => '',
				'post_status' => 'inherit'
			];
			
			$attachment_id = wp_insert_attachment($attachment, $upload['file']);
			
			if (is_wp_error($attachment_id)) {
				self::log('Failed to create attachment: ' . $attachment_id->get_error_message() . ' | URL: ' . $image_url . ' | Filename: ' . $filename, __FILE__, __LINE__, 'error');
				return null;
			}
			
			// Generate attachment metadata
			require_once(ABSPATH . 'wp-admin/includes/image.php');
			$attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
			wp_update_attachment_metadata($attachment_id, $attachment_data);
			
			// Store original URL in meta for reference
			update_post_meta($attachment_id, '_fi_original_url', $image_url);
			
			self::log('Image successfully downloaded and stored | URL: ' . $image_url . ' | Attachment ID: ' . $attachment_id, __FILE__, __LINE__, 'info');
			
			return $attachment_id;
		}
		
		/**
		* Get attachment ID by original URL
		*/
		private static function get_attachment_by_url(string $url): ?int {
			global $wpdb;
			
			$attachment_id = $wpdb->get_var($wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_fi_original_url' AND meta_value = %s",
				$url
			));
			
			return $attachment_id ? (int) $attachment_id : null;
		}
		
		/**
		* Get file extension from URL
		*/
		private static function get_file_extension_from_url(string $url): ?string {
			$path = parse_url($url, PHP_URL_PATH);
			$extension = pathinfo($path, PATHINFO_EXTENSION);
			
			// Validate extension
			$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
			if (in_array(strtolower($extension), $allowed_extensions)) {
				return strtolower($extension);
			}
			
			return null;
		}
		
		/**
		* Get placeholder image HTML
		*/
		public static function get_placeholder_image(string $name): string {
			$initials = self::get_initials($name);
			$color = self::get_color_from_name($name);
			
			return sprintf(
				'<div class="fi-placeholder-image" style="background-color: %s; color: white; width: 100%%; height: 200px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; border-radius: 0.375rem;">
					%s
				</div>',
				esc_attr($color),
				esc_html($initials)
			);
		}
		
		/**
		* Get initials from name
		*/
		private static function get_initials(string $name): string {
			$words = explode(' ', trim($name));
			$initials = '';
			
			foreach ($words as $word) {
				if (!empty($word)) {
					$initials .= strtoupper(substr($word, 0, 1));
				}
			}
			
			return substr($initials, 0, 2); // Max 2 initials
		}
		
		/**
		* Get color from name (consistent color for same name)
		*/
		private static function get_color_from_name(string $name): string {
			$colors = [
				'#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6',
				'#1abc9c', '#34495e', '#e67e22', '#95a5a6', '#f1c40f'
			];
			
			$hash = crc32($name);
			$index = abs($hash) % count($colors);
			
			return $colors[$index];
		}
		
		/**
		* Get legislator image with fallback
		*/
		public static function get_legislator_image(int $legislator_id, string $size = 'medium'): string {
			global $wpdb;
			
			// Get image_id from legislator record
			$image_id = $wpdb->get_var($wpdb->prepare(
				"SELECT image_id FROM {$wpdb->prefix}fi_legislators WHERE id = %d",
				$legislator_id
			));
			
			if ($image_id) {
				$image_url = wp_get_attachment_image_url($image_id, $size);
				if ($image_url) {
					return $image_url;
				}
			}
			
			// Fallback to placeholder
			$legislator = $wpdb->get_row($wpdb->prepare(
				"SELECT display_name FROM {$wpdb->prefix}fi_legislators WHERE id = %d",
				$legislator_id
			));
			
			if ($legislator) {
				return self::get_placeholder_image($legislator->display_name);
			}
			
			return self::get_placeholder_image('Unknown');
		}

		/**
		* Wrap fi_log in function so we can log this class only if necessary.
		*/
		public static function log(string $message, string $file = '', int $line = 0, string $level = 'debug'): void {
			//fi_log($message, $file, $line, $level);
		}

	}
}

namespace{
	function fi_url_image_exists($url){
		$headers = @get_headers($url, 1);
		if ($headers === false) {
			return false;
		}
		return strpos($headers[0], '200') !== false;
	}
}