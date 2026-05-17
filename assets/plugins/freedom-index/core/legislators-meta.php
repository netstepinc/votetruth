<?php
namespace FI\Core {

	if (!defined('ABSPATH')) exit;

	/**
	* Legislator Meta Data Handler
	* 
	* Handles all legislator metadata operations including normalization, extraction, and formatting.
	* This class is used internally by the Legislators class to provide a cohesive meta handling system.
	*/
	final class LegislatorsMeta {

		/**
		* Normalize meta array from flat structure to organized groups (address, social, contact, website, etc.)
		* Automatically migrates old flat structure to new organized structure while preserving legacy data.
		* 
		* @param array $meta Raw meta array from database
		* @return array Normalized meta array with organized groups
		*/
		public static function normalize(array $meta): array {
			// If already normalized (has 'address', 'social', 'contact', 'website' keys), return as-is
			// Also check for old 'offices' key and migrate it
			if (isset($meta['address']) || isset($meta['social']) || isset($meta['contact']) || isset($meta['website'])) {
				// Migrate old 'offices' to 'address' if needed
				if (isset($meta['offices']) && !isset($meta['address'])) {
					$meta['address'] = $meta['offices'];
					unset($meta['offices']);
				}
				return $meta;
			}
			
			// Migrate from flat structure
			$normalized = [];
			
			// Extract contact info (email, phone, fax - but NOT website)
			$contact = [];
			if (!empty($meta['email'])) $contact['email'] = $meta['email'];
			if (!empty($meta['phone'])) $contact['phone'] = $meta['phone'];
			if (!empty($meta['fax'])) $contact['fax'] = $meta['fax'];
			if (!empty($contact)) $normalized['contact'] = $contact;
			
			// Extract websites as array
			$websites = [];
			if (!empty($meta['website'])) {
				// If it's already an array, use it; otherwise convert single value to array
				if (is_array($meta['website'])) {
					$websites = $meta['website'];
				} else {
					$websites[] = $meta['website'];
				}
			}
			// Check for multiple website fields (website2, website3, etc.)
			for ($i = 2; $i <= 10; $i++) {
				$key = 'website' . $i;
				if (!empty($meta[$key])) {
					$websites[] = $meta[$key];
				}
			}
			if (!empty($websites)) {
				$normalized['website'] = array_values(array_filter($websites)); // Remove empty and reindex
			}
			
			// Extract addresses (changed from 'offices' to 'address')
			$addresses = [];
			
			// Build capitol address from flat keys
			if (!empty($meta['office_address']) || !empty($meta['office_city'])) {
				$capitol_address = [
					'name' => 'Capitol Office',
					'type' => 'capitol',
					'is_primary' => true,
				];
				if (!empty($meta['office_address'])) $capitol_address['address'] = $meta['office_address'];
				if (!empty($meta['office_city'])) $capitol_address['city'] = $meta['office_city'];
				if (!empty($meta['office_state'])) $capitol_address['state'] = $meta['office_state'];
				if (!empty($meta['office_zip'])) $capitol_address['zip'] = $meta['office_zip'];
				if (!empty($meta['phone'])) $capitol_address['phone'] = $meta['phone'];
				if (!empty($meta['email'])) $capitol_address['email'] = $meta['email'];
				$addresses[] = $capitol_address;
			}
			
			// Build district address from flat keys
			if (!empty($meta['district_address']) || !empty($meta['district_city'])) {
				$district_address = [
					'name' => 'District Office',
					'type' => 'district',
					'is_primary' => false,
				];
				if (!empty($meta['district_address'])) $district_address['address'] = $meta['district_address'];
				if (!empty($meta['district_city'])) $district_address['city'] = $meta['district_city'];
				if (!empty($meta['district_state'])) $district_address['state'] = $meta['district_state'];
				if (!empty($meta['district_zip'])) $district_address['zip'] = $meta['district_zip'];
				if (!empty($meta['district_phone'])) $district_address['phone'] = $meta['district_phone'];
				elseif (!empty($meta['local_phone'])) $district_address['phone'] = $meta['local_phone'];
				if (!empty($meta['district_email'])) $district_address['email'] = $meta['district_email'];
				elseif (!empty($meta['local_email'])) $district_address['email'] = $meta['local_email'];
				$addresses[] = $district_address;
			}
			
			// Check for 'local' field (old format with pipe separators)
			if (!empty($meta['local']) && empty($district_address)) {
				$local_parts = explode('|', $meta['local']);
				if (count($local_parts) >= 2) {
					$addresses[] = [
						'name' => 'Local Office',
						'type' => 'local',
						'is_primary' => false,
						'address' => trim($local_parts[0] ?? ''),
						'city' => trim($local_parts[1] ?? ''),
						'state' => trim($local_parts[2] ?? ''),
						'zip' => trim($local_parts[3] ?? ''),
						'phone' => trim($local_parts[4] ?? ''),
					];
				}
			}
			
			if (!empty($addresses)) $normalized['address'] = $addresses;
			
			// Extract social media links
			$social = [];
			$social_keys = [
				'twitter' => ['twitter', 'twitter-x', 'social_twitter'],
				'facebook' => ['facebook', 'social_facebook'],
				'instagram' => ['instagram', 'social_instagram'],
				'linkedin' => ['linkedin', 'social_linkedin'],
				'youtube' => ['youtube', 'social_youtube'],
				'gab' => ['gab', 'social_gab'],
				'truthsocial' => ['truthsocial', 'social_truthsocial'],
				'tiktok' => ['tiktok', 'social_tiktok'],
				'telegram' => ['telegram', 'social_telegram'],
			];
			
			foreach ($social_keys as $platform => $possible_keys) {
				foreach ($possible_keys as $key) {
					// Check nested social_links array
					if (isset($meta['social_links'][$platform]) && !empty($meta['social_links'][$platform])) {
						$social[$platform] = $meta['social_links'][$platform];
						break;
					}
					// Check flat keys
					if (isset($meta[$key]) && !empty($meta[$key])) {
						$social[$platform] = $meta[$key];
						break;
					}
				}
			}
			
			if (!empty($social)) $normalized['social'] = $social;
			
			// Extract personal info
			$personal = [];
			if (!empty($meta['birthdate']) || !empty($meta['legislator_birthdate'])) {
				$personal['birthdate'] = $meta['birthdate'] ?? $meta['legislator_birthdate'];
			}
			if (!empty($meta['hometown']) || !empty($meta['townname']) || !empty($meta['legislator_hometown'])) {
				$personal['hometown'] = $meta['hometown'] ?? $meta['townname'] ?? $meta['legislator_hometown'];
			}
			if (!empty($meta['bio']) || !empty($meta['legislator_bio'])) {
				$personal['bio'] = $meta['bio'] ?? $meta['legislator_bio'];
			}
			if (!empty($meta['education'])) $personal['education'] = $meta['education'];
			if (!empty($meta['profession'])) $personal['profession'] = $meta['profession'];
			if (!empty($personal)) $normalized['personal'] = $personal;
			
			// Preserve biographical meta fields
			$bio_keys = ['gender', 'birth_date', 'death_date', 'url_openstates'];
			foreach ($bio_keys as $bio_key) {
				if (!empty($meta[$bio_key])) {
					$normalized[$bio_key] = $meta[$bio_key];
				}
			}
			// Migrate legacy birthdate into birth_date if not already set
			if (empty($normalized['birth_date']) && !empty($personal['birthdate'])) {
				$normalized['birth_date'] = $personal['birthdate'];
			}
			
			// Preserve legacy data (but avoid duplicating large source payloads).
			// Summary: we keep a legacy snapshot for backward compatibility, but we do NOT mirror raw source blobs
			// like legiscan_data or api_* which are already stored explicitly elsewhere.
			$legacy = $meta;
			if (isset($legacy['legiscan_data'])) {
				unset($legacy['legiscan_data']);
			}
			// Don't nest legacy inside itself if present.
			if (isset($legacy['legacy'])) {
				unset($legacy['legacy']);
			}
			// Remove api_* audit payloads (can be huge and are already tracked at top-level meta keys).
			foreach (array_keys($legacy) as $k) {
				if (is_string($k) && str_starts_with($k, 'api_')) {
					unset($legacy[$k]);
				}
			}
			if (!empty($legacy)) {
				$normalized['legacy'] = $legacy;
			}
			
			return $normalized;
		}

		/**
		* Get addresses array from legislator meta.
		* 
		* @param object $legislator Legislator object with meta property
		* @return array Array of address arrays
		*/
		public static function get_addresses(object $legislator): array {
			$meta = is_array($legislator->meta ?? null) ? $legislator->meta : [];
			$meta = self::normalize($meta);
			
			// Ensure address is always an array
			$address = $meta['address'] ?? [];
			
			// If address is a string (JSON or otherwise), try to decode it
			if (is_string($address)) {
				$decoded = json_decode($address, true);
				if (is_array($decoded)) {
					return $decoded;
				}
				// If not JSON, return empty array
				return [];
			}
			
			// If address is not an array, return empty array
			if (!is_array($address)) {
				return [];
			}
			
			return $address;
		}

		/**
		* Get primary address (is_primary = true or first capitol address).
		* 
		* @param object $legislator Legislator object
		* @return array|null Address array or null
		*/
		public static function get_primary_address(object $legislator): ?array {
			$addresses = self::get_addresses($legislator);
			
			// Find explicitly marked primary
			foreach ($addresses as $address) {
				if (!empty($address['is_primary'])) {
					return $address;
				}
			}
			
			// Fall back to first capitol address
			foreach ($addresses as $address) {
				if (($address['type'] ?? '') === 'capitol') {
					return $address;
				}
			}
			
			// Fall back to first address
			return !empty($addresses) ? $addresses[0] : null;
		}

		/**
		* Get capitol address.
		* 
		* @param object $legislator Legislator object
		* @return array|null Address array or null
		*/
		public static function get_capitol_address(object $legislator): ?array {
			$addresses = self::get_addresses($legislator);
			
			foreach ($addresses as $address) {
				if (($address['type'] ?? '') === 'capitol') {
					return $address;
				}
			}
			
			return null;
		}

		/**
		* Get websites array from legislator meta.
		* 
		* @param object $legislator Legislator object
		* @return array Array of website URLs
		*/
		public static function get_websites(object $legislator): array {
			$meta = is_array($legislator->meta ?? null) ? $legislator->meta : [];
			$meta = self::normalize($meta);
			return is_array($meta['website'] ?? null) ? $meta['website'] : [];
		}

		/**
		* Get social media links.
		* 
		* @param object $legislator Legislator object
		* @return array Associative array of social platform => URL
		*/
		public static function get_social(object $legislator): array {
			$meta = is_array($legislator->meta ?? null) ? $legislator->meta : [];
			$meta = self::normalize($meta);
			return $meta['social'] ?? [];
		}

		/**
		* Get primary contact information.
		* 
		* @param object $legislator Legislator object
		* @return array Contact info array
		*/
		public static function get_contact(object $legislator): array {
			$meta = is_array($legislator->meta ?? null) ? $legislator->meta : [];
			$meta = self::normalize($meta);
			return $meta['contact'] ?? [];
		}

		/**
		* Get personal information (bio, hometown, etc.).
		* 
		* @param object $legislator Legislator object
		* @return array Personal info array
		*/
		public static function get_personal(object $legislator): array {
			$meta = is_array($legislator->meta ?? null) ? $legislator->meta : [];
			$meta = self::normalize($meta);
			return $meta['personal'] ?? [];
		}

		/**
		* Format address for display.
		* Returns formatted HTML string with address, city, state, zip.
		* 
		* @param array $address Address array with address, city, state, zip
		* @return string Formatted address HTML
		*/
		public static function format_address(array $address): string {
			$parts = [];
			
			if (!empty($address['address'])) {
				$parts[] = esc_html($address['address']);
			}
			
			$city_state_zip = '';
			if (!empty($address['city'])) {
				$city_state_zip .= esc_html($address['city']);
			}
			if (!empty($address['state'])) {
				$city_state_zip .= ', ' . esc_html($address['state']);
			}
			if (!empty($address['zip'])) {
				$city_state_zip .= ' ' . esc_html($address['zip']);
			}
			$parts[] = $city_state_zip;
			return !empty($parts) ? implode('<br>', $parts) : '';
		}

		/**
		* Format full address as single line string.
		* 
		* @param array $address Address array
		* @return string Formatted address string
		*/
		public static function format_full_address(array $address): string {
			$parts = [];
			
			if (!empty($address['address'])) {
				$parts[] = $address['address'];
			}
			
			$city_state_zip = [];
			if (!empty($address['city'])) {
				$city_state_zip[] = $address['city'];
			}
			if (!empty($address['state'])) {
				$city_state_zip[] = $address['state'];
			}
			if (!empty($address['zip'])) {
				$city_state_zip[] = $address['zip'];
			}
			
			if (!empty($city_state_zip)) {
				$parts[] = implode(', ', $city_state_zip);
			}
			
			return implode(', ', $parts);
		}
	}

} // End FI\Core namespace

// Global helper functions
namespace {
    /**
     * Format address for display with HTML sanitization (global helper)
     * 
     * @param array $address Address array
     * @return string Formatted and sanitized address HTML
     */
    if (!function_exists('fi_legislator_address_format')) {
        function fi_legislator_address_format(array $address): string {
            return wp_kses_post(\FI\Core\LegislatorsMeta::format_address($address));
        }
    }

    /**
     * Normalize legislator meta array (global helper)
     * 
     * @param array $meta Raw meta array from database
     * @return array Normalized meta array with organized groups
     */
    function fi_legislator_meta_normalize(array $meta): array {
        return \FI\Core\LegislatorsMeta::normalize($meta);
    }
}
