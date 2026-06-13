<?php if (!defined('ABSPATH')) { exit; }

/**
 * Render settings page
 */
function fi_admin_settings_render(): void {
	include __DIR__ . '/../views/settings.php';
}

/**
 * Get cache status for all governments
 */
function fi_admin_settings_get_cache_status(): array {
	$governments = fi_govs();
	$cache_data = [];
	
	foreach ($governments as $gov_code => $gov_name) {
		$key = "fi_filter_options_{$gov_code}";
		$cached = get_transient($key);
		
		$cache_data[$gov_code] = [
			'name' => $gov_name,
			'key' => $key,
			'exists' => $cached !== false,
			'data' => $cached !== false ? $cached : null
		];
	}
	
	return $cache_data;
}

