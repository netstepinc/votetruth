<?php
namespace FI\Core{
	if ( ! defined( 'ABSPATH' ) ) { exit; }

	/**
	* Settings System for Freedom Index Admin
	* 
	* Provides centralized settings management with per-gov overrides.
	* Modernizes the V1 constants approach to database storage.
	*/
	final class Settings {

		private static $cache = [];
		private static $defaults = [
			'images' => [
				'default_size' => 'medium',
				'fallback_image' => null
			],
			'api' => [
				'version' => '1.0',
				'rate_limit' => 1000,
				'cors_origins' => [],
				'legiscan_key' => '',
				'geocod_key' => '',
				'govtrack_key' => '',
				'votesmart_key' => '',
				'openstates_key' => ''
			],
			'logging' => [
				// Logging settings are global (read from 'US' settings).
				'enabled' => false,
				'min_level' => 'debug', // debug|info|warning|error
				'areas' => [
					'general' => true,
					'ajax' => false,
					'rewrite' => false,
					'template_loader' => false,
					'legislators' => false,
					'filters' => false,
					'votes' => false,
					'lists' => false,
					'legiscan' => false,
					'taxonomy' => false,
					'geocod' => false,
					'images' => false,
					'fluentcrm' => false,
					'cache' => false,
				],
			]
		];

		/**
		* Get all settings for a gov
		*/
		public static function all(string $gov = 'US'): array {
			$cache_key = "fi_settings_{$gov}";
			
			if (isset(self::$cache[$cache_key])) {
				return self::$cache[$cache_key];
			}

			global $wpdb;
			$settings = get_option("fi_settings_{$gov}", []);
			
			// Merge with defaults
			$merged = array_merge(self::$defaults, $settings);
			
			// Cache for this request
			self::$cache[$cache_key] = $merged;
			
			return $merged;
		}

		/**
		* Get a specific setting value
		*/
		public static function get(string $gov, string $key, $default = null) {
			$settings = self::all($gov);
			
			// Support dot notation for nested keys
			$keys = explode('.', $key);
			$value = $settings;
			
			foreach ($keys as $k) {
				if (!is_array($value) || !array_key_exists($k, $value)) {
					return $default;
				}
				$value = $value[$k];
			}
			
			return $value;
		}

		/**
		* Set a specific setting value
		*/
		public static function set(string $gov, string $key, $value): bool {
			$settings = self::all($gov);
			
			// Support dot notation for nested keys
			$keys = explode('.', $key);
			$target = &$settings;
			
			// Navigate to the target key
			for ($i = 0; $i < count($keys) - 1; $i++) {
				if (!isset($target[$keys[$i]]) || !is_array($target[$keys[$i]])) {
					$target[$keys[$i]] = [];
				}
				$target = &$target[$keys[$i]];
			}
			
			// Set the final value
			$target[$keys[count($keys) - 1]] = $value;
			
			// Save to database
			$result = update_option("fi_settings_{$gov}", $settings);
			
			// Clear cache
			unset(self::$cache["fi_settings_{$gov}"]);
			
			return $result;
		}

		/**
		* Get all governments with settings
		*/
		public static function get_governments(): array {
			global $wpdb;
			
			// Get governments that have settings in the database
			$results = $wpdb->get_results(
				"SELECT option_name FROM {$wpdb->options} 
				WHERE option_name LIKE 'fi_settings_%' 
				ORDER BY option_name"
			);
			
			$db_governments = [];
			foreach ($results as $result) {
				$gov = str_replace('fi_settings_', '', $result->option_name ?? '');
				$db_governments[] = $gov;
			}
			
			// Define all possible governments (Congress + all 50 states)
			$all_governments = [
				'US', 'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI',
				'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN',
				'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH',
				'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA',
				'WV', 'WI', 'WY'
			];
			
			// Return all governments, but prioritize those with settings
			$governments = [];
			
			// First add governments that have settings
			foreach ($db_governments as $gov) {
				if (in_array($gov, $all_governments)) {
					$governments[] = $gov;
				}
			}
			
			// Then add governments that don't have settings yet
			foreach ($all_governments as $gov) {
				if (!in_array($gov, $governments)) {
					$governments[] = $gov;
				}
			}
			
			return $governments;
		}

		/**
		* Reset settings to defaults for a government
		*/
		public static function reset(string $gov): bool {
			$result = delete_option("fi_settings_{$gov}");
			
			// Clear cache
			unset(self::$cache["fi_settings_{$gov}"]);
			
			return $result;
		}

		/**
		* Validate settings data
		*/
		public static function validate(array $settings): array {
			$errors = [];
			
			// Validate images
			if (isset($settings['images']) && !is_array($settings['images'])) {
				$errors['images'] = 'Images settings must be an array.';
			}
			
			// Validate api
			if (isset($settings['api']) && !is_array($settings['api'])) {
				$errors['api'] = 'API settings must be an array.';
			}

			// Validate logging
			if (isset($settings['logging'])) {
				if (!is_array($settings['logging'])) {
					$errors['logging'] = 'Logging settings must be an array.';
				} else {
					$min_level = $settings['logging']['min_level'] ?? 'debug';
					$allowed = ['debug', 'info', 'warning', 'error'];
					if (!in_array($min_level, $allowed, true)) {
						$errors['logging'] = 'Logging min_level must be one of: debug, info, warning, error.';
					}
				}
			}
			
			return $errors;
		}

		/**
		* Sanitize settings data
		*/
		public static function sanitize(array $settings): array {
			$sanitized = [];
			
			// Sanitize images
			if (isset($settings['images']) && is_array($settings['images'])) {
				$sanitized['images'] = [
					'default_size' => sanitize_text_field($settings['images']['default_size'] ?? 'medium'),
					'fallback_image' => isset($settings['images']['fallback_image']) ? absint($settings['images']['fallback_image']) : null,
				];
			}
			
			// Sanitize api
			if (isset($settings['api']) && is_array($settings['api'])) {
				$cors_origins = [];
				if (isset($settings['api']['cors_origins'])) {
					if (is_array($settings['api']['cors_origins'])) {
						$cors_origins = array_filter(array_map('trim', array_map('sanitize_text_field', $settings['api']['cors_origins'])));
					} else {
						$lines = explode("\n", (string) $settings['api']['cors_origins']);
						$cors_origins = array_filter(array_map('trim', array_map('sanitize_text_field', $lines)));
					}
				}

				$sanitized['api'] = [
					'version' => sanitize_text_field($settings['api']['version'] ?? '1.0'),
					'rate_limit' => isset($settings['api']['rate_limit']) ? absint($settings['api']['rate_limit']) : 1000,
					'cors_origins' => $cors_origins,
					'legiscan_key' => sanitize_text_field($settings['api']['legiscan_key'] ?? ''),
					'geocod_key' => sanitize_text_field($settings['api']['geocod_key'] ?? ''),
					'govtrack_key' => sanitize_text_field($settings['api']['govtrack_key'] ?? ''),
					'votesmart_key' => sanitize_text_field($settings['api']['votesmart_key'] ?? ''),
					'openstates_key' => sanitize_text_field($settings['api']['openstates_key'] ?? ''),
				];
			}

			// Sanitize logging
			if (isset($settings['logging']) && is_array($settings['logging'])) {
				$enabled_raw = $settings['logging']['enabled'] ?? false;
				$min_level = sanitize_text_field($settings['logging']['min_level'] ?? 'debug');
				$areas_raw = $settings['logging']['areas'] ?? [];

				$allowed_levels = ['debug', 'info', 'warning', 'error'];
				if (!in_array($min_level, $allowed_levels, true)) {
					$min_level = 'debug';
				}

				$areas = [];
				if (is_array($areas_raw)) {
					foreach ($areas_raw as $area_key => $area_enabled) {
						$area_key = sanitize_key((string) $area_key);
						$areas[$area_key] = (bool) $area_enabled;
					}
				}

				$sanitized['logging'] = [
					'enabled' => (bool) $enabled_raw,
					'min_level' => $min_level,
					'areas' => $areas,
				];
			}
			
			return $sanitized;
		}


		/**
		* Get API key from settings with fallback to constant
		* API keys are global (not gov-specific), always read from 'US' settings
		* 
		* @param string $key_name The API key name (legiscan_key, geocod_key, etc.)
		* @param string $constant_name The constant name to fallback to (e.g., API_KEY_LEGISCAN)
		* @return string|null
		*/
		public static function get_api_key(string $key_name, string $constant_name): ?string {
			// API keys are global, always read from 'US' settings
			$key = self::get('US', "api.{$key_name}", '');
			if (!empty($key)) {
				return $key;
			}
			
			// Fallback to constant
			if (defined($constant_name)) {
				return constant($constant_name);
			}
			
			return null;
		}
	}
}

/* Public functions for the Freedom Index plugin */
namespace{
	function fi_settings_get_governments(): array {
		return \FI\Core\Settings::get_governments();
	}

	/**
	 * Get API key from settings with fallback to constant
	 * API keys are global (not gov-specific)
	 * 
	 * @param string $key_name The API key name (legiscan_key, geocod_key, etc.)
	 * @param string $constant_name The constant name to fallback to (e.g., API_KEY_LEGISCAN)
	 * @return string|null
	 */
	function fi_get_api_key(string $key_name, string $constant_name): ?string {
		return \FI\Core\Settings::get_api_key($key_name, $constant_name);
	}

	/**
	 * Get all settings for a government
	 */
	function fi_settings_all(string $gov = 'US'): array {
		return \FI\Core\Settings::all($gov);
	}

	/**
	 * Get a specific setting value
	 */
	function fi_settings_get(string $gov, string $key, $default = null) {
		return \FI\Core\Settings::get($gov, $key, $default);
	}

	/**
	 * Set a specific setting value
	 */
	function fi_settings_set(string $gov, string $key, $value): bool {
		return \FI\Core\Settings::set($gov, $key, $value);
	}

	/**
	 * Sanitize settings data
	 */
	function fi_settings_sanitize(array $settings): array {
		return \FI\Core\Settings::sanitize($settings);
	}

	/**
	 * Validate settings data
	 */
	function fi_settings_validate(array $settings): array {
		return \FI\Core\Settings::validate($settings);
	}
}