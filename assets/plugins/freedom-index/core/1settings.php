<?php
/*
 * Freedom Index Settings System
 *
 * Straight function version of the former FICore\Settings class file.
 *
 * Provides centralized settings management with per-government overrides.
 * Uses WordPress options as storage and request-level static cache for reads.
 * Refactored and tuned the settings file.
 * Key adjustments:

Removed the FICore\Settings class/namespace wrapper.
Preserved existing public API:
fi_settings_get_governments()
fi_get_api_key()
fi_settings_all()
fi_settings_get()
fi_settings_set()
fi_settings_sanitize()
fi_settings_validate()
Added:
fi_settings_defaults()
fi_settings_cache()
fi_settings_cache_clear()
fi_settings_normalize_gov()
fi_settings_option_name()
fi_settings_reset()
fi_settings_sanitize_cors_origins()
fi_settings_save()
fi_log_settings_get()

Important tuning:

Replaced shallow default merging with recursive merging:

array_replace_recursive(fi_settings_defaults(), $settings)
Made settings cache function-based.
Normalized government codes consistently.
Used FI_GOVERNMENTS when available instead of hardcoding the government list.
Added fi_log_settings_get() so the refactored AJAX logger can call it.
Made update_option() calls use autoload = false.
 */

if (!defined('ABSPATH')) exit;

/**
 * Get default FI settings.
 *
 * @return array Default settings array.
 */
function fi_settings_defaults(): array {
	return [
		'images' => [
			'default_size'   => 'medium',
			'fallback_image' => null,
		],
		'api' => [
			'version'        => '1.0',
			'rate_limit'     => 1000,
			'cors_origins'   => [],
			'legiscan_key'   => '',
			'geocod_key'     => '',
			'govtrack_key'   => '',
			'votesmart_key'  => '',
			'openstates_key' => '',
		],
		'logging' => [
			'enabled'   => false,
			'min_level' => 'debug',
			'areas'     => [
				'general'         => true,
				'ajax'            => false,
				'rewrite'         => false,
				'template_loader' => false,
				'legislators'     => false,
				'filters'         => false,
				'votes'           => false,
				'lists'           => false,
				'legiscan'        => false,
				'taxonomy'        => false,
				'geocod'          => false,
				'images'          => false,
				'fluentcrm'       => false,
				'cache'           => false,
			],
		],
	];
}

/**
 * Request-level settings cache helper.
 *
 * @param string|null $key Cache key. Null returns full cache.
 * @param mixed $value Value to set.
 * @param bool $set Whether to set value.
 * @return mixed
 */
function fi_settings_cache(?string $key = null, $value = null, bool $set = false) {
	static $cache = [];

	if ($key === null) {
		return $cache;
	}

	if ($key === '__clear__') {
		$cache = [];
		return null;
	}

	if ($set) {
		$cache[$key] = $value;
		return $value;
	}

	return $cache[$key] ?? null;
}

/**
 * Clear settings request cache.
 *
 * @param string|null $gov Optional government code. Null clears all.
 * @return void
 */
function fi_settings_cache_clear(?string $gov = null): void {
	if ($gov === null) {
		fi_settings_cache('__clear__');
		return;
	}

	$cache = fi_settings_cache();
	$cache_key = 'fi_settings_' . fi_settings_normalize_gov($gov);
	unset($cache[$cache_key]);

	fi_settings_cache('__clear__');
	foreach ($cache as $key => $value) {
		fi_settings_cache($key, $value, true);
	}
}

/**
 * Normalize government code for settings option names.
 *
 * @param string $gov Government code.
 * @return string Normalized government code.
 */
function fi_settings_normalize_gov(string $gov = 'US'): string {
	$gov = strtoupper(sanitize_key($gov));
	return $gov !== '' ? $gov : 'US';
}

/**
 * Get WordPress option name for a government's FI settings.
 *
 * @param string $gov Government code.
 * @return string Option name.
 */
function fi_settings_option_name(string $gov = 'US'): string {
	return 'fi_settings_' . fi_settings_normalize_gov($gov);
}

/**
 * Get all settings for a government.
 *
 * @param string $gov Government code.
 * @return array Settings array recursively merged with defaults.
 */
function fi_settings_all(string $gov = 'US'): array {
	$gov = fi_settings_normalize_gov($gov);
	$cache_key = fi_settings_option_name($gov);

	$cached = fi_settings_cache($cache_key);
	if ($cached !== null) {
		return is_array($cached) ? $cached : [];
	}

	$settings = get_option($cache_key, []);
	if (!is_array($settings)) {
		$settings = [];
	}

	$merged = array_replace_recursive(fi_settings_defaults(), $settings);

	fi_settings_cache($cache_key, $merged, true);

	return $merged;
}

/**
 * Get a specific setting value using dot notation.
 *
 * @param string $gov Government code.
 * @param string $key Dot-notation key.
 * @param mixed $default Default value.
 * @return mixed
 */
function fi_settings_get(string $gov, string $key, $default = null) {
	$settings = fi_settings_all($gov);
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
 * Set a specific setting value using dot notation.
 *
 * @param string $gov Government code.
 * @param string $key Dot-notation key.
 * @param mixed $value Setting value.
 * @return bool True when saved or value already matches existing option.
 */
function fi_settings_set(string $gov, string $key, $value): bool {
	$gov = fi_settings_normalize_gov($gov);
	$settings = fi_settings_all($gov);
	$keys = explode('.', $key);
	$target = &$settings;

	for ($i = 0; $i < count($keys) - 1; $i++) {
		$part = $keys[$i];
		if (!isset($target[$part]) || !is_array($target[$part])) {
			$target[$part] = [];
		}
		$target = &$target[$part];
	}

	$target[$keys[count($keys) - 1]] = $value;

	$option_name = fi_settings_option_name($gov);
	$result = update_option($option_name, $settings, false);

	fi_settings_cache_clear($gov);

	return $result || get_option($option_name) === $settings;
}

/**
 * Get all governments with settings, followed by all supported governments.
 *
 * @return array Government codes.
 */
function fi_settings_get_governments(): array {
	global $wpdb;

	$results = $wpdb->get_results(
		"SELECT option_name FROM {$wpdb->options}
		WHERE option_name LIKE 'fi_settings_%'
		ORDER BY option_name"
	);

	$db_governments = [];
	foreach ($results as $result) {
		$gov = str_replace('fi_settings_', '', $result->option_name ?? '');
		$gov = fi_settings_normalize_gov($gov);
		if ($gov !== '') {
			$db_governments[] = $gov;
		}
	}

	$all_governments = defined('FI_GOVERNMENTS') && is_array(FI_GOVERNMENTS)
		? array_keys(FI_GOVERNMENTS)
		: [
			'US', 'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI',
			'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN',
			'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH',
			'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA',
			'WV', 'WI', 'WY',
		];

	$governments = [];

	foreach ($db_governments as $gov) {
		if (in_array($gov, $all_governments, true) && !in_array($gov, $governments, true)) {
			$governments[] = $gov;
		}
	}

	foreach ($all_governments as $gov) {
		$gov = fi_settings_normalize_gov($gov);
		if (!in_array($gov, $governments, true)) {
			$governments[] = $gov;
		}
	}

	return $governments;
}

/**
 * Reset settings to defaults for a government.
 *
 * @param string $gov Government code.
 * @return bool True if deleted or no option existed.
 */
function fi_settings_reset(string $gov): bool {
	$gov = fi_settings_normalize_gov($gov);
	$option_name = fi_settings_option_name($gov);
	$result = delete_option($option_name);

	fi_settings_cache_clear($gov);

	return $result || get_option($option_name, null) === null;
}

/**
 * Validate settings data.
 *
 * @param array $settings Settings data.
 * @return array Validation errors keyed by section.
 */
function fi_settings_validate(array $settings): array {
	$errors = [];

	if (isset($settings['images']) && !is_array($settings['images'])) {
		$errors['images'] = 'Images settings must be an array.';
	}

	if (isset($settings['api']) && !is_array($settings['api'])) {
		$errors['api'] = 'API settings must be an array.';
	}

	if (isset($settings['api']) && is_array($settings['api'])) {
		if (isset($settings['api']['rate_limit']) && !is_numeric($settings['api']['rate_limit'])) {
			$errors['api_rate_limit'] = 'API rate limit must be numeric.';
		}

		if (isset($settings['api']['cors_origins']) && !is_array($settings['api']['cors_origins']) && !is_string($settings['api']['cors_origins'])) {
			$errors['api_cors_origins'] = 'CORS origins must be an array or newline-separated string.';
		}
	}

	if (isset($settings['logging'])) {
		if (!is_array($settings['logging'])) {
			$errors['logging'] = 'Logging settings must be an array.';
		} else {
			$min_level = $settings['logging']['min_level'] ?? 'debug';
			$allowed = ['debug', 'info', 'warning', 'error'];
			if (!in_array($min_level, $allowed, true)) {
				$errors['logging'] = 'Logging min_level must be one of: debug, info, warning, error.';
			}

			if (isset($settings['logging']['areas']) && !is_array($settings['logging']['areas'])) {
				$errors['logging_areas'] = 'Logging areas must be an array.';
			}
		}
	}

	return $errors;
}

/**
 * Sanitize CORS origins from array or newline-separated string.
 *
 * @param mixed $origins Raw origins.
 * @return array Sanitized origins.
 */
function fi_settings_sanitize_cors_origins($origins): array {
	if (is_array($origins)) {
		$items = $origins;
	} else {
		$items = explode("\n", (string) $origins);
	}

	$clean = [];
	foreach ($items as $origin) {
		$origin = trim(sanitize_text_field((string) $origin));
		if ($origin !== '') {
			$clean[] = $origin;
		}
	}

	return array_values(array_unique($clean));
}

/**
 * Sanitize settings data.
 *
 * @param array $settings Raw settings.
 * @return array Sanitized settings.
 */
function fi_settings_sanitize(array $settings): array {
	$sanitized = [];

	if (isset($settings['images']) && is_array($settings['images'])) {
		$fallback_image = $settings['images']['fallback_image'] ?? null;

		$sanitized['images'] = [
			'default_size'   => sanitize_text_field($settings['images']['default_size'] ?? 'medium'),
			'fallback_image' => $fallback_image !== null && $fallback_image !== '' ? absint($fallback_image) : null,
		];
	}

	if (isset($settings['api']) && is_array($settings['api'])) {
		$sanitized['api'] = [
			'version'        => sanitize_text_field($settings['api']['version'] ?? '1.0'),
			'rate_limit'     => isset($settings['api']['rate_limit']) ? absint($settings['api']['rate_limit']) : 1000,
			'cors_origins'   => fi_settings_sanitize_cors_origins($settings['api']['cors_origins'] ?? []),
			'legiscan_key'   => sanitize_text_field($settings['api']['legiscan_key'] ?? ''),
			'geocod_key'     => sanitize_text_field($settings['api']['geocod_key'] ?? ''),
			'govtrack_key'   => sanitize_text_field($settings['api']['govtrack_key'] ?? ''),
			'votesmart_key'  => sanitize_text_field($settings['api']['votesmart_key'] ?? ''),
			'openstates_key' => sanitize_text_field($settings['api']['openstates_key'] ?? ''),
		];
	}

	if (isset($settings['logging']) && is_array($settings['logging'])) {
		$min_level = sanitize_text_field($settings['logging']['min_level'] ?? 'debug');
		$allowed_levels = ['debug', 'info', 'warning', 'error'];
		if (!in_array($min_level, $allowed_levels, true)) {
			$min_level = 'debug';
		}

		$areas = fi_settings_defaults()['logging']['areas'];
		$areas_raw = $settings['logging']['areas'] ?? [];
		if (is_array($areas_raw)) {
			foreach ($areas_raw as $area_key => $area_enabled) {
				$area_key = sanitize_key((string) $area_key);
				if ($area_key !== '') {
					$areas[$area_key] = (bool) $area_enabled;
				}
			}
		}

		$sanitized['logging'] = [
			'enabled'   => !empty($settings['logging']['enabled']),
			'min_level' => $min_level,
			'areas'     => $areas,
		];
	}

	return array_replace_recursive(fi_settings_defaults(), $sanitized);
}

/**
 * Save a whole settings array for a government after sanitization.
 *
 * @param string $gov Government code.
 * @param array $settings Raw settings.
 * @return bool True when saved or existing option already matches.
 */
function fi_settings_save(string $gov, array $settings): bool {
	$gov = fi_settings_normalize_gov($gov);
	$sanitized = fi_settings_sanitize($settings);
	$option_name = fi_settings_option_name($gov);

	$result = update_option($option_name, $sanitized, false);
	fi_settings_cache_clear($gov);

	return $result || get_option($option_name) === $sanitized;
}

/**
 * Get API key from settings with fallback to constant.
 *
 * API keys are global and always read from US settings.
 *
 * @param string $key_name API key name, e.g. legiscan_key.
 * @param string $constant_name Constant fallback, e.g. API_KEY_LEGISCAN.
 * @return string|null API key or null.
 */
function fi_get_api_key(string $key_name, string $constant_name): ?string {
	$key_name = sanitize_key($key_name);
	$key = fi_settings_get('US', 'api.' . $key_name, '');

	if (!empty($key)) {
		return (string) $key;
	}

	if (defined($constant_name)) {
		$value = constant($constant_name);
		return $value !== '' && $value !== null ? (string) $value : null;
	}

	return null;
}

/**
 * Get global logging settings.
 *
 * Logging is global and read from US settings. This function is used by AJAX/logging helpers.
 *
 * @return array Logging settings merged with defaults.
 */
function fi_log_settings_get(): array {
	$logging = fi_settings_get('US', 'logging', []);
	return is_array($logging) ? array_replace_recursive(fi_settings_defaults()['logging'], $logging) : fi_settings_defaults()['logging'];
}