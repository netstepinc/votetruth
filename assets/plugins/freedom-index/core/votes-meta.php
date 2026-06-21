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
 * @param array $vote Vote row array with meta key
 * @return array Decoded meta array
 */
function fi_vote_decode_meta(array $vote): array {
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
 * Format vote cast and constitutional alignment for display.
 *
 * Returns a complete set of display values (labels, CSS classes, icons, badge HTML)
 * for a single vote cast against its constitutional position.
 *
 * $args keys:
 *   cast           string  Y|N|P|X — how the legislator voted.
 *   constitutional string  Y|N|U   — the constitutionally-correct direction.
 *   format         string  full|text|icon|badge (default: full)
 *
 * Cast is optional. If omitted, returns constitutional-only display values.
 *
 * @param array $args
 * @return array
 */
function fi_vote_format(array $args = []): array {
	$cast_provided   = array_key_exists('cast', $args);
	$cast            = $cast_provided ? strtoupper((string) $args['cast']) : '';
	$constitutional  = strtoupper((string) ($args['constitutional'] ?? ''));
	$format          = (string) ($args['format'] ?? 'full');

	static $cast_map = null;
	static $constitutional_map = null;

	if ($cast_map === null) {
		$cast_map = [
			'Y' => ['label' => 'Yes',     'icon' => 'bi bi-hand-thumbs-up'],
			'N' => ['label' => 'No',      'icon' => 'bi bi-hand-thumbs-down'],
			'P' => ['label' => 'Present', 'icon' => 'bi bi-dash-circle'],
			'X' => ['label' => 'None',    'icon' => 'bi bi-x-circle'],
		];
		$constitutional_map = [
			'Y' => ['label' => 'Yes',     'icon' => 'bi bi-hand-thumbs-up'],
			'N' => ['label' => 'No',      'icon' => 'bi bi-hand-thumbs-down'],
			'U' => ['label' => 'Unknown', 'icon' => 'bi bi-question-circle'],
		];
	}

	$const_info  = $constitutional_map[$constitutional] ?? ['label' => 'Unknown', 'icon' => 'bi bi-question-circle'];
	$const_label = esc_html($const_info['label']);
	$const_icon  = esc_attr($const_info['icon']);

	if (!$cast_provided) {
		$badge_bg = ($constitutional === 'Y') ? 'bg-success' : (($constitutional === 'N') ? 'bg-danger' : 'bg-secondary');
		if ($format === 'text')  return ['text'  => $const_label];
		if ($format === 'icon')  return ['icon'  => '<i class="' . $const_icon . '"></i>'];
		if ($format === 'badge') return ['badge' => '<span class="badge ' . esc_attr($badge_bg) . ' text-white rounded-pill fs-7">' . $const_label . '</span>'];
		return [
			'raw'            => '',
			'is_counted'     => 0,
			'is_match'       => 0,
			'is_no_vote'     => 0,
			'vote_text'      => $const_label,
			'vote_class'     => '',
			'vote_class_icon'=> $const_icon,
			'cast_text'      => '',
			'cast_class'     => '',
			'cast_bg-class'  => '',
			'cast_class_icon'=> '',
			'table_symbol'   => '',
			'table_class'    => '',
			'icon'           => '<i class="' . $const_icon . '"></i>',
			'badge'          => '<span class="badge ' . esc_attr($badge_bg) . ' text-white rounded-pill fs-7">' . $const_label . '</span>',
		];
	}

	$cast_info    = $cast_map[$cast] ?? ['label' => '--', 'icon' => 'bi bi-x-circle'];
	$cast_label   = esc_html($cast_info['label']);
	$cast_icon    = esc_attr($cast_info['icon']);
	$is_no_vote   = in_array($cast, ['P', 'X', ''], true);
	$is_valid     = in_array($cast, ['Y', 'N'], true);
	$is_match     = $is_valid && ($cast === $constitutional);

	if ($is_no_vote) {
		$cast_class  = 'text-muted';
		$cast_bg     = 'bg-secondary text-white';
		$table_sym   = '<i class="bi bi-ban"></i>';
		$table_class = 'text-muted';
	} elseif ($is_match) {
		$cast_class  = 'text-success';
		$cast_bg     = 'bg-success text-white';
		$table_sym   = '<i class="bi bi-check-circle-fill"></i>';
		$table_class = 'text-success';
	} else {
		$cast_class  = 'text-danger';
		$cast_bg     = 'bg-danger text-white';
		$table_sym   = '<i class="bi bi-x-circle-fill"></i>';
		$table_class = 'text-danger';
	}

	if ($format === 'text')  return ['text'  => $cast_label];
	if ($format === 'icon')  return ['icon'  => '<i class="' . $cast_icon . ' ' . esc_attr($cast_class) . '"></i>'];
	if ($format === 'badge') return ['badge' => '<span class="badge ' . esc_attr($cast_bg) . ' rounded-pill fs-7">' . $cast_label . '</span>'];

	return [
		'raw'            => $cast,
		'is_counted'     => $is_valid ? 1 : 0,
		'is_match'       => $is_match ? 1 : 0,
		'is_no_vote'     => $is_no_vote ? 1 : 0,
		'vote_text'      => $const_label,
		'vote_class'     => '',
		'vote_class_icon'=> $const_icon,
		'cast_text'      => $cast_label,
		'cast_class'     => $cast_class . ' fiv-' . $cast,
		'cast_bg-class'  => $cast_bg,
		'cast_class_icon'=> $cast_icon,
		'table_symbol'   => $table_sym,
		'table_class'    => $table_class,
		'icon'           => '<i class="' . $cast_icon . ' ' . esc_attr($cast_class) . '"></i>',
		'badge'          => '<span class="badge ' . esc_attr($cast_bg) . ' rounded-pill fs-7">' . $cast_label . '</span>',
	];
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
