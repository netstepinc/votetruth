<?php
namespace FI\Admin {

	if (!defined('ABSPATH')) exit;

	/**
	* Slug Generator for Freedom Index Admin
	* 
	* Generates unique slugs for reports and lists.
	* Note: Legislator slugs are now set to their ID (numeric) - no generation needed.
	*/
	final class SlugGenerator {

		/**
		* Generate report slug
		*/
		public static function generate_report_slug(): string {
			// Generate 8-character random slug
			$characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
			$slug = '';
			
			for ($i = 0; $i < 8; $i++) {
				$slug .= $characters[random_int(0, strlen($characters) - 1)];
			}
			
			// Ensure uniqueness
			return self::ensure_unique_report_slug($slug);
		}

		/**
		* Ensure report slug is unique
		*/
		private static function ensure_unique_report_slug(string $base_slug): string {
			global $wpdb;
			
			$slug = $base_slug;
			$counter = 2;
			
			while (self::report_slug_exists($slug)) {
				$slug = $base_slug . $counter;
				$counter++;
			}
			
			return $slug;
		}

		/**
		* Check if report slug exists
		*/
		private static function report_slug_exists(string $slug): bool {
			global $wpdb;
			
			$count = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}fi_reports WHERE slug = %s",
				$slug
			));
			
			return $count > 0;
		}

	/**
	* Generate list slug (deprecated - slugs are now generated dynamically)
	* Kept for backward compatibility but no longer used
	*/
	public static function generate_list_slug(string $name): string {
		// Slugs are now generated dynamically as {user_id}{list_id}
		// This method is kept for backward compatibility but returns empty string
		return '';
	}
	}
}

/* Public functions for the Freedom Index plugin */
namespace {
	/**
	 * Generate report slug
	 */
	function fi_slug_generate_report(): string {
		return \FI\Admin\SlugGenerator::generate_report_slug();
	}
}