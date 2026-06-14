<?php
/**
 * Vote Meta Functions
 * 
 * Functions for handling vote metadata
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Decode vote meta JSON to array
 * 
 * @param object $vote Vote object with meta property
 * @return array Decoded meta array
 */
function fi_vote_decode_meta(object $vote): array {
	if (isset($vote['meta'])) {
		if (is_array($vote['meta'])) {
			return $vote['meta'];
		}
		if (is_string($vote['meta'])) {
			$decoded = json_decode($vote['meta'], true);
			return is_array($decoded) ? $decoded : [];
		}
	}
	return [];
}

/**
 * Normalize one description HTML string for storage
 * 
 * @param string $value HTML content
 * @return string Normalized content
 */
function fi_vote_normalize_meta_description_string(string $value): string {
	if ($value === '') {
		return '';
	}
	
	// Line endings to \n
	$value = str_replace(["\r\n", "\r"], "\n", $value);
	
	// Smart quotes to straight
	$value = str_replace(
		['"', '"', '\'', '\'', '–', '—'],
		['"', '"', "'", "'", '-', '-'],
		$value
	);
	
	// No-break spaces to normal space
	$value = str_replace("\xC2\xA0", ' ', $value);
	
	// wp_kses_post
	return wp_kses_post($value);
}

/**
 * Normalize description fields in meta array before storage
 * 
 * @param array $meta Meta array
 * @return array Normalized meta array
 */
function fi_vote_normalize_meta_descriptions_for_storage(array $meta): array {
	$keys = ['description_short', 'description_medium', 'description_long'];
	foreach ($keys as $key) {
		if (isset($meta[$key]) && is_string($meta[$key])) {
			$meta[$key] = fi_vote_normalize_meta_description_string($meta[$key]);
		}
	}
	return $meta;
}

/**
 * Get description text from meta with fallback logic
 * 
 * @param array $meta Vote meta array
 * @param string $format Format type: 'scorecard' or 'freedomindex'
 * @return array Description text or empty array
 */
function fi_vote_get_description(array $meta, string $format = 'scorecard'): array {
	// Legacy key support
	$short = fi_format_clean_content($meta['description_short'] ?? '');
	$medium = fi_format_clean_content($meta['description_medium'] ?? '');
	$long = fi_format_clean_content($meta['description_long'] ?? '');
	
	// Choose based on format
	if ($format === 'scorecard') {
		// Scorecard uses short description
		return !empty($short) ? ['text' => $short] : 
			(!empty($medium) ? ['text' => $medium] : 
			(!empty($long) ? ['text' => $long] : []));
	} else {
		// Freedom Index uses long description
		return !empty($long) ? ['text' => $long] : 
			(!empty($medium) ? ['text' => $medium] : 
			(!empty($short) ? ['text' => $short] : []));
	}
}

/**
 * Update vote meta (wrapper for unified meta handling)
 * 
 * @param int $vote_id Vote ID
 * @param string $meta_key Meta key
 * @param mixed $meta_value Meta value
 * @return bool True on success, false on failure
 */
function fi_vote_update_meta(int $vote_id, string $meta_key, $meta_value): bool {
	return update_metadata('fi_vote', $vote_id, $meta_key, $meta_value);
}

/**
 * Get vote meta (wrapper for unified meta handling)
 * 
 * @param int $vote_id Vote ID
 * @param string $meta_key Meta key
 * @param bool $single Return single value
 * @return mixed Meta value
 */
function fi_vote_get_meta(int $vote_id, string $meta_key = '', bool $single = true) {
	return get_metadata('fi_vote', $vote_id, $meta_key, $single);
}

/**
 * Delete vote meta (wrapper for unified meta handling)
 * 
 * @param int $vote_id Vote ID
 * @param string $meta_key Meta key
 * @param mixed $meta_value Optional specific value to delete
 * @return bool True on success, false on failure
 */
function fi_vote_delete_meta(int $vote_id, string $meta_key, $meta_value = ''): bool {
	return delete_metadata('fi_vote', $vote_id, $meta_key, $meta_value);
}
