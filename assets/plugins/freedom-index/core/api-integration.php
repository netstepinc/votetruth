<?php
/**
 * Freedom Index External API Integration Helpers
 *
 * Straight function version of the former FICore\ApiIntegration class file.
 *
 * Fetches and compares legislator data from external APIs such as VoteSmart,
 * GovTrack, and local/API LegiScan payloads.
 */

if (!defined('ABSPATH')) exit;

/**
 * Fetch data from all available APIs for a legislator.
 *
 * @param int $legislator_id Legislator ID.
 * @return array API data keyed by source.
 */
function fi_api_fetch_all(int $legislator_id): array {
	$legislator = function_exists('fi_legislator_get') ? fi_legislator_get($legislator_id) : null;
	if (!$legislator) {
		return [];
	}

	$results = [];

	if (!empty($legislator->votesmart_id)) {
		$results['votesmart'] = fi_api_fetch_votesmart((string) $legislator->votesmart_id);
	} elseif (!empty($legislator->bioguide_id) || !empty($legislator->display_name)) {
		$results['votesmart'] = fi_api_search_votesmart($legislator);
	}

	if (!empty($legislator->gov) && $legislator->gov === 'US') {
		if (!empty($legislator->bioguide_id)) {
			$results['govtrack'] = fi_api_fetch_govtrack_by_bioguide((string) $legislator->bioguide_id);
		} elseif (!empty($legislator->govtrack_id)) {
			$results['govtrack'] = fi_api_fetch_govtrack((string) $legislator->govtrack_id);
		} elseif (!empty($legislator->display_name)) {
			$results['govtrack'] = fi_api_search_govtrack($legislator);
		}
	}

	if (!empty($legislator->legiscan_id)) {
		$results['legiscan'] = fi_api_fetch_legiscan((string) $legislator->legiscan_id);
	}

	return $results;
}

/**
 * Compare API data with existing legislator data.
 *
 * @param int $legislator_id Legislator ID.
 * @param string $source API source.
 * @param array $api_data API data.
 * @return array Diff results keyed by our field.
 */
function fi_api_compare(int $legislator_id, string $source, array $api_data): array {
	$legislator = function_exists('fi_legislator_get') ? fi_legislator_get($legislator_id) : null;
	if (!$legislator) {
		return [];
	}

	$meta = fi_api_legislator_meta_array($legislator);
	$comparison = [];
	$field_mappings = fi_api_field_mappings($source);

	foreach ($field_mappings as $our_field => $api_field) {
		$api_value = fi_api_get_nested_value($api_data, $api_field);
		$our_value = fi_api_get_our_value($legislator, $our_field, $meta);
		$status = fi_api_compare_values($our_value, $api_value);

		$api_value_display = (is_array($api_value) || is_object($api_value)) ? wp_json_encode($api_value) : $api_value;
		$our_value_display = (is_array($our_value) || is_object($our_value)) ? wp_json_encode($our_value) : $our_value;

		$comparison[$our_field] = [
			'status'    => $status,
			'our_value' => $our_value_display,
			'api_value' => $api_value_display,
			'api_field' => $api_field,
			'label'     => fi_api_field_label($our_field),
		];
	}

	$additional = fi_api_find_additional_fields($api_data, $field_mappings, $meta);
	foreach ($additional as $field => $data) {
		$comparison[$field] = [
			'status'    => 'missing',
			'our_value' => null,
			'api_value' => $data['value'],
			'api_field' => $data['path'],
			'label'     => fi_api_field_label($field),
		];
	}

	return $comparison;
}

/**
 * Build an updates array for a given legislator/source using mapped API fields.
 *
 * @param int $legislator_id Legislator ID.
 * @param string $source API source.
 * @param array $api_data API data.
 * @param bool $only_missing Return only fields currently missing locally.
 * @return array Updates keyed by our field.
 */
function fi_api_build_updates_for_legislator(int $legislator_id, string $source, array $api_data, bool $only_missing = true): array {
	$legislator = function_exists('fi_legislator_get') ? fi_legislator_get($legislator_id) : null;
	if (!$legislator) {
		return [];
	}

	$meta = fi_api_legislator_meta_array($legislator);

	if ($source === 'legiscan_local') {
		$api_data = fi_api_normalize_legiscan_local_person($api_data);
	}

	$mappings = fi_api_field_mappings($source);
	if (empty($mappings)) {
		return [];
	}

	$updates = [];
	foreach ($mappings as $our_field => $api_field) {
		$api_value = fi_api_get_nested_value($api_data, $api_field);
		if ($api_value === null || $api_value === '' || $api_value === []) {
			continue;
		}

		if ($only_missing) {
			$our_value = fi_api_get_our_value($legislator, $our_field, $meta);
			$status = fi_api_compare_values($our_value, $api_value);
			if ($status !== 'missing') {
				continue;
			}
		}

		if ($our_field === 'addresses.capitol' && is_array($api_value)) {
			$updates[$our_field] = wp_json_encode($api_value);
			continue;
		}

		$updates[$our_field] = $api_value;
	}

	return $updates;
}

/**
 * Get normalized legislator meta array from object.
 *
 * @param object $legislator Legislator object.
 * @return array
 */
function fi_api_legislator_meta_array(object $legislator): array {
	if (function_exists('fi_legislator_meta_from_object')) {
		return fi_legislator_meta_from_object($legislator);
	}

	$meta = $legislator->meta ?? [];
	if (is_string($meta) && $meta !== '') {
		$decoded = json_decode($meta, true);
		return is_array($decoded) ? $decoded : [];
	}

	return is_array($meta) ? $meta : [];
}

/**
 * Get field mappings for a source.
 *
 * @param string $source API source.
 * @return array Our field => API field.
 */
function fi_api_field_mappings(string $source): array {
	$mappings = [
		'votesmart' => [
			'first_name'         => 'firstName',
			'last_name'          => 'lastName',
			'middle_name'        => 'middleName',
			'display_name'       => 'nickName',
			'votesmart_id'       => 'candidateId',
			'bioguide_id'        => 'bioguideId',
			'ballotpedia_id'     => 'ballotpedia',
			'image_id'           => 'photo',
			'meta.email'         => 'email',
			'meta.phone'         => 'phone',
			'meta.office_address'=> 'office',
			'meta.website'       => 'website',
			'meta.twitter'       => 'twitter',
			'meta.facebook'      => 'facebook',
		],
		'govtrack' => [
			'first_name'      => 'firstname',
			'last_name'       => 'lastname',
			'middle_name'     => 'middlename',
			'bioguide_id'     => 'bioguide_id',
			'govtrack_id'     => 'id',
			'image_id'        => 'image',
			'meta.wikipedia'  => 'wikipedia',
			'meta.birth_date' => 'birthday',
			'meta.gender'     => 'gender',
		],
		'legiscan' => [
			'first_name'         => 'first_name',
			'last_name'          => 'last_name',
			'middle_name'        => 'middle_name',
			'legiscan_id'        => 'people_id',
			'bioguide_id'        => 'bioguide_id',
			'votesmart_id'       => 'votesmart_id',
			'ballotpedia_id'     => 'ballotpedia_id',
			'meta.party_history' => 'party_history',
			'meta.role_history'  => 'role_history',
		],
		'legiscan_local' => [
			'first_name'             => 'first_name',
			'last_name'              => 'last_name',
			'middle_name'            => 'middle_name',
			'legiscan_id'            => 'people_id',
			'bioguide_id'            => 'bioguide_id',
			'votesmart_id'           => 'votesmart_id',
			'ballotpedia_id'         => 'ballotpedia',
			'meta.opensecrets_id'    => 'opensecrets_id',
			'websites.biography'     => 'bio.social.biography',
			'websites.webmail'       => 'bio.social.webmail',
			'session_image_url'      => 'bio.social.image',
			'addresses.capitol'      => 'bio.capitol_address',
			'meta.email'             => 'bio.social.email',
			'meta.phone'             => 'bio.social.capitol_phone',
			'meta.facebook'          => 'bio.links.official.facebook',
			'meta.instagram'         => 'bio.links.official.instagram',
			'meta.linkedin'          => 'bio.links.official.linkedin',
			'meta.tiktok'            => 'bio.links.official.tiktok',
			'meta.twitter'           => 'bio.links.official.twitter',
			'meta.youtube'           => 'bio.links.official.youtube',
		],
	];

	return $mappings[$source] ?? [];
}

/**
 * Normalize LegiScan local person payload into compare/update shape.
 *
 * @param array $person Person payload.
 * @return array Normalized payload.
 */
function fi_api_normalize_legiscan_local_person(array $person): array {
	if (isset($person['bio']['capitol_address']) && is_array($person['bio']['capitol_address'])) {
		$ca = $person['bio']['capitol_address'];
		$address1 = (string) ($ca['address1'] ?? '');
		$address2 = (string) ($ca['address2'] ?? '');
		$combined = trim($address1 . ($address2 !== '' ? (' ' . $address2) : ''));

		if ($combined !== '') {
			$person['bio']['capitol_address']['address'] = $combined;
		}

		unset($person['bio']['capitol_address']['address1'], $person['bio']['capitol_address']['address2']);
	}

	return $person;
}

/**
 * Get nested value from array using dot notation.
 *
 * @param array $data Source data.
 * @param string $path Dot path.
 * @return mixed|null
 */
function fi_api_get_nested_value(array $data, string $path) {
	$keys = explode('.', $path);
	$value = $data;

	foreach ($keys as $key) {
		if (!is_array($value) || !array_key_exists($key, $value)) {
			return null;
		}
		$value = $value[$key];
	}

	return $value;
}

/**
 * Get local value from legislator object or normalized meta.
 *
 * @param object $legislator Legislator object.
 * @param string $field Our field key.
 * @param array $meta Normalized meta.
 * @return mixed|null
 */
function fi_api_get_our_value(object $legislator, string $field, array $meta) {
	if ($field === 'websites.biography') {
		$websites = $meta['website'] ?? [];
		return is_array($websites) ? $websites : [];
	}

	if ($field === 'addresses.capitol') {
		$addr = function_exists('fi_legislator_meta_get_capitol_address') ? fi_legislator_meta_get_capitol_address($legislator) : null;
		if (!is_array($addr)) {
			return null;
		}

		return [
			'address' => (string) ($addr['address'] ?? ''),
			'city'    => (string) ($addr['city'] ?? ''),
			'state'   => (string) ($addr['state'] ?? ''),
			'zip'     => (string) ($addr['zip'] ?? ''),
		];
	}

	if ($field === 'session_image_url') {
		$sessions = $legislator->sessions ?? null;
		if (is_array($sessions) && !empty($sessions)) {
			$latest = reset($sessions);
			if (is_object($latest) && !empty($latest->image_id) && function_exists('wp_get_attachment_url')) {
				$url = wp_get_attachment_url((int) $latest->image_id);
				return $url ?: null;
			}
		}
		return null;
	}

	if (strpos($field, 'meta.') === 0) {
		$meta_key = substr($field, 5);

		if (in_array($meta_key, ['email', 'phone', 'fax'], true)) {
			if (isset($meta['contact']) && is_array($meta['contact']) && array_key_exists($meta_key, $meta['contact'])) {
				return $meta['contact'][$meta_key];
			}
		}

		$social_key = $meta_key;
		if ($meta_key === 'truth_social') {
			$social_key = 'truthsocial';
		} elseif (strpos($meta_key, '_') !== false) {
			$social_key = str_replace('_', '', $meta_key);
		}

		if (isset($meta['social']) && is_array($meta['social'])) {
			if (array_key_exists($social_key, $meta['social'])) {
				return $meta['social'][$social_key];
			}
			if (array_key_exists($meta_key, $meta['social'])) {
				return $meta['social'][$meta_key];
			}
		}

		return $meta[$meta_key] ?? null;
	}

	return $legislator->{$field} ?? null;
}

/**
 * Compare two values.
 *
 * Returns: match, diff, or missing.
 *
 * @param mixed $our_value Local value.
 * @param mixed $api_value API value.
 * @return string
 */
function fi_api_compare_values($our_value, $api_value): string {
	if ($api_value === null) {
		return 'missing';
	}

	if (is_array($our_value) && (is_scalar($api_value) || $api_value === null)) {
		if (empty($our_value)) {
			return 'missing';
		}

		$api_norm = fi_api_normalize_value($api_value);
		foreach ($our_value as $v) {
			if (fi_api_normalize_value($v) === $api_norm) {
				return 'match';
			}
		}

		return 'diff';
	}

	if ($our_value === null || $our_value === '') {
		return 'missing';
	}

	return fi_api_normalize_value($our_value) === fi_api_normalize_value($api_value) ? 'match' : 'diff';
}

/**
 * Normalize value for comparison.
 *
 * @param mixed $value Value.
 * @return string Normalized value.
 */
function fi_api_normalize_value($value): string {
	if (is_array($value)) {
		$value = wp_json_encode($value);
	}

	return strtolower(trim((string) $value));
}

/**
 * Find additional unmapped fields in API data.
 *
 * @param array $api_data API data.
 * @param array $mappings Field mappings.
 * @param array $meta Local meta.
 * @return array Additional fields.
 */
function fi_api_find_additional_fields(array $api_data, array $mappings, array $meta): array {
	$additional = [];
	$mapped_paths = array_values($mappings);
	$flattened = fi_api_flatten_array($api_data);

	foreach ($flattened as $path => $value) {
		if (!in_array($path, $mapped_paths, true) && $value !== null && $value !== '') {
			$field_name = 'meta.' . str_replace('.', '_', $path);
			$meta_key = str_replace('meta.', '', $field_name);
			if (!isset($meta[$meta_key])) {
				$additional[$field_name] = [
					'value' => $value,
					'path'  => $path,
				];
			}
		}
	}

	return $additional;
}

/**
 * Flatten nested array with dot notation keys.
 *
 * @param array $array Array to flatten.
 * @param string $prefix Prefix.
 * @return array Flattened array.
 */
function fi_api_flatten_array(array $array, string $prefix = ''): array {
	$result = [];

	foreach ($array as $key => $value) {
		$new_key = $prefix ? "{$prefix}.{$key}" : (string) $key;

		if (is_array($value)) {
			$result = array_merge($result, fi_api_flatten_array($value, $new_key));
		} else {
			$result[$new_key] = $value;
		}
	}

	return $result;
}

/**
 * Get human-readable field label.
 *
 * @param string $field Field key.
 * @return string Label.
 */
function fi_api_field_label(string $field): string {
	$labels = [
		'first_name'         => 'First Name',
		'last_name'          => 'Last Name',
		'middle_name'        => 'Middle Name',
		'display_name'       => 'Display Name',
		'bioguide_id'        => 'Bioguide ID',
		'legiscan_id'        => 'Legiscan ID',
		'govtrack_id'        => 'GovTrack ID',
		'votesmart_id'       => 'VoteSmart ID',
		'ballotpedia_id'     => 'Ballotpedia ID',
		'image_id'           => 'Image',
		'websites.biography' => 'Biography (Website)',
		'addresses.capitol'  => 'Capitol Address',
		'session_image_url'  => 'Session Image',
	];

	if (strpos($field, 'meta.') === 0) {
		$meta_key = substr($field, 5);
		return ucwords(str_replace('_', ' ', $meta_key));
	}

	return $labels[$field] ?? ucwords(str_replace('_', ' ', $field));
}

/**
 * Fetch VoteSmart data.
 *
 * @param string $votesmart_id VoteSmart candidate ID.
 * @return array|null Bio data or null.
 */
function fi_api_fetch_votesmart(string $votesmart_id): ?array {
	$api_key = defined('FI_API_KEY_VOTESMART') ? FI_API_KEY_VOTESMART : '';
	if (empty($api_key)) {
		return null;
	}

	$url = add_query_arg([
		'key'         => $api_key,
		'candidateId' => $votesmart_id,
		'o'           => 'JSON',
	], 'https://api.votesmart.org/CandidateBio.getBio');

	$response = wp_remote_get($url, [
		'timeout'   => 10,
		'sslverify' => true,
	]);

	if (is_wp_error($response)) {
		return null;
	}

	$data = json_decode(wp_remote_retrieve_body($response), true);

	return is_array($data) ? ($data['bio'] ?? null) : null;
}

/**
 * Search VoteSmart by legislator info.
 *
 * Placeholder retained from original implementation.
 *
 * @param object $legislator Legislator object.
 * @return array|null
 */
function fi_api_search_votesmart(object $legislator): ?array {
	return null;
}

/**
 * Fetch GovTrack data by Bioguide ID.
 *
 * @param string $bioguide_id Bioguide ID.
 * @return array|null
 */
function fi_api_fetch_govtrack_by_bioguide(string $bioguide_id): ?array {
	$url = add_query_arg([
		'bioguide_id' => $bioguide_id,
		'format'      => 'json',
	], 'https://www.govtrack.us/api/v2/person');

	$response = wp_remote_get($url, [
		'timeout'   => 10,
		'sslverify' => true,
	]);

	if (is_wp_error($response)) {
		return null;
	}

	$data = json_decode(wp_remote_retrieve_body($response), true);

	return is_array($data) ? ($data['objects'][0] ?? null) : null;
}

/**
 * Fetch GovTrack data by GovTrack ID.
 *
 * @param string $govtrack_id GovTrack ID.
 * @return array|null
 */
function fi_api_fetch_govtrack(string $govtrack_id): ?array {
	$url = 'https://www.govtrack.us/api/v2/person/' . rawurlencode($govtrack_id) . '?format=json';

	$response = wp_remote_get($url, [
		'timeout'   => 10,
		'sslverify' => true,
	]);

	if (is_wp_error($response)) {
		return null;
	}

	$data = json_decode(wp_remote_retrieve_body($response), true);

	return is_array($data) ? $data : null;
}

/**
 * Search GovTrack by legislator display name.
 *
 * @param object $legislator Legislator object.
 * @return array|null First match or null.
 */
function fi_api_search_govtrack(object $legislator): ?array {
	$name = $legislator->display_name ?? trim(($legislator->first_name ?? '') . ' ' . ($legislator->last_name ?? ''));
	if ($name === '') {
		return null;
	}

	$url = add_query_arg([
		'name'   => $name,
		'format' => 'json',
	], 'https://www.govtrack.us/api/v2/person');

	$response = wp_remote_get($url, [
		'timeout'   => 10,
		'sslverify' => true,
	]);

	if (is_wp_error($response)) {
		return null;
	}

	$data = json_decode(wp_remote_retrieve_body($response), true);

	return is_array($data) ? ($data['objects'][0] ?? null) : null;
}

/**
 * Fetch LegiScan data.
 *
 * Placeholder retained from original implementation.
 *
 * @param string $legiscan_id LegiScan people ID.
 * @return array|null
 */
function fi_api_fetch_legiscan(string $legiscan_id): ?array {
	return null;
}