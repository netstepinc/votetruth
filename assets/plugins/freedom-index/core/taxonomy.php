<?php
/*
 * Freedom Index Taxonomy Table I/O Operations
 *
 * Straight function version of the former FICore\Taxonomy class file.
 * Handles all database operations for fi_taxonomy.
 *
 * Notes:
 * - Slugs are retained here as internal taxonomy identifiers for tags/districts/import matching.
 * - Public entity routing should remain ID-based where applicable.
Refactored the taxonomy file into straight functions.

Key adjustments:

Removed the FICore\Taxonomy class/namespace wrapper.
Preserved the existing public API:
fi_taxonomies_get()
fi_taxonomy_get()
fi_taxonomy_get_by_slug()
fi_taxonomy_save()
fi_taxonomy_update()
fi_taxonomy_delete()
fi_taxonomy_stats()
fi_taxonomy_tree()
fi_tags_get()
fi_districts_get()
fi_district_get()
fi_district_id_from_legiscan()
fi_tag_url()
Added reusable helpers:
fi_taxonomy_request_cache()
fi_taxonomy_clear_cache()
fi_taxonomy_log()
fi_taxonomy_generate_district_slug()
fi_taxonomy_slug_exists()
fi_taxonomy_generate_slug()
fi_taxonomy_check_duplicates()
fi_taxonomy_validate_data()
Fixed two wrapper defects from the original:
fi_taxonomy_tree() pointed to get_tree(), but the class method was named get_taxonomy_tree().
get_taxonomy_tree() called self::get_taxonomy(), but the actual method was get().

I kept slug support here because taxonomy tags and districts still use slugs as internal matching keys, especially for imports and issue URLs. That is separate from using slugs for public entity routing. 
*/

if (!defined('ABSPATH')) exit;

/**
 * Request-level taxonomy cache store.
 *
 * @param string $group Cache group.
 * @param string|null $key Cache key.
 * @param mixed $value Value to set.
 * @param bool $set Whether to set value.
 * @return mixed
 */
function fi_taxonomy_request_cache(string $group, ?string $key = null, $value = null, bool $set = false) {
	static $cache = [
		'get'   => [],
		'by_id' => [],
	];

	if ($group === 'clear') {
		$cache = [
			'get'   => [],
			'by_id' => [],
		];
		return null;
	}

	if (!isset($cache[$group])) {
		$cache[$group] = [];
	}

	if ($key === null) {
		return $cache[$group];
	}

	if ($set) {
		$cache[$group][$key] = $value;
		return $value;
	}

	return $cache[$group][$key] ?? null;
}

/**
 * Clear request-level taxonomy caches.
 *
 * @return void
 */
function fi_taxonomy_clear_cache(): void {
	fi_taxonomy_request_cache('clear');
}

/**
 * Taxonomy-specific logging wrapper.
 *
 * @param string $message Log message.
 * @param string $file File path.
 * @param int $line Line number.
 * @param string $level Log level.
 * @return void
 */
function fi_taxonomy_log(string $message, string $file = '', int $line = 0, string $level = 'debug'): void {
	if (function_exists('fi_log')) {
		fi_log($message, $file, $line, $level);
	}
}

/**
 * Query taxonomies with optional filtering (DB-only, no cache).
 *
 * @param array $args Query arguments.
 * @return array|int Array of taxonomy objects or count if count=true.
 */
function fi_taxonomies_query(array $args = []): array|int {
	global $wpdb;

	$defaults = [
		'id'       => null,
		'taxonomy' => null,
		'gov'      => null,
		'search'   => null,
		'orderby'  => 'name',
		'order'    => 'ASC',
		'per_page' => -1,
		'page'     => 1,
		'count'    => false,
	];

	$args = wp_parse_args($args, $defaults);

	if ($args['taxonomy'] === 'tag') {
		$args['gov'] = null;
	}

	$where_conditions = [];
	$where_values = [];

	if (!empty($args['id'])) {
		$where_conditions[] = 'id = %d';
		$where_values[] = absint($args['id']);
	}

	if (!empty($args['taxonomy'])) {
		$where_conditions[] = 'taxonomy = %s';
		$where_values[] = sanitize_key($args['taxonomy']);
	}

	if (!empty($args['gov'])) {
		$where_conditions[] = 'gov = %s';
		$where_values[] = strtoupper((string) $args['gov']);
	}

	if (!empty($args['search'])) {
		$where_conditions[] = 'name LIKE %s';
		$search_term = '%' . $wpdb->esc_like((string) $args['search']) . '%';
		$where_values[] = $search_term;
	}

	$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

	$allowed_orderby = ['id', 'name', 'taxonomy', 'gov', 'date_created', 'date_updated'];
	$orderby_field = sanitize_key((string) $args['orderby']);
	if (!in_array($orderby_field, $allowed_orderby, true)) {
		$orderby_field = 'name';
	}

	$order = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';
	$orderby = "{$orderby_field} {$order}";

	$limit_clause = '';
	if ((int) $args['per_page'] > 0) {
		$per_page = absint($args['per_page']);
		$page = max(1, absint($args['page']));
		$offset = ($page - 1) * $per_page;
		$limit_clause = $wpdb->prepare('LIMIT %d OFFSET %d', $per_page, $offset);
	}

	if (!empty($args['count'])) {
		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}fi_taxonomy {$where_clause}";

		if (!empty($where_values)) {
			$sql = $wpdb->prepare($sql, $where_values);
		}

		return (int) $wpdb->get_var($sql);
	}

	$sql = "
		SELECT * FROM {$wpdb->prefix}fi_taxonomy
		{$where_clause}
		ORDER BY {$orderby}
		{$limit_clause}
	";

	if (!empty($where_values)) {
		$sql = $wpdb->prepare($sql, $where_values);
	}

	return $wpdb->get_results($sql, ARRAY_A);
}



/**
 * Get taxonomies with optional filtering (cached for front-end).
 *
 * @param array $args Query arguments.
 * @return array|int Array of taxonomy objects or count if count=true.
 */
function fi_taxonomies_get(array $args = []): array|int {
	$cacheKey = fi_cache_key('taxonomy/get', $args);

	$results = fi_cache($cacheKey);
	if ($results) {
		return $results;
	}

	$results = fi_taxonomies_query($args);
	fi_cache($cacheKey, $results);
	return $results;
}
/**
 * Get a single taxonomy item by ID.
 *
 * @param int $taxonomy_id Taxonomy item ID.
 * @return array|null
 */
function fi_taxonomy_get(int $taxonomy_id): ?array {
	$cached = fi_taxonomy_request_cache('by_id', (string) $taxonomy_id);
	if ($cached !== null) {
		return $cached ?: null;
	}

	$results = fi_taxonomies_get([
		'id'       => $taxonomy_id,
		'per_page' => 1,
	]);

	$result = is_array($results) ? ($results[0] ?? null) : null;
	fi_taxonomy_request_cache('by_id', (string) $taxonomy_id, $result ?: false, true);

	return $result;
}

/**
 * Get taxonomy item by slug.
 *
 * @param string $slug Slug.
 * @param string $taxonomy Taxonomy type.
 * @param string|null $gov Optional government code.
 * @return object|null
 */
/*DEPRECATED
function fi_taxonomy_get_by_slug(string $slug, string $taxonomy, ?string $gov = null): ?object {
	$args = [
		'slug'     => $slug,
		'taxonomy' => $taxonomy,
		'per_page' => 1,
	];

	if ($gov && $taxonomy !== 'tag') {
		$args['gov'] = strtoupper($gov);
	}

	$results = fi_taxonomies_get($args);
	return is_array($results) ? ($results[0] ?? null) : null;
}
*/

/**
 * Generate a stable district slug from district name.
 *
 * Examples: TN 7th, TN 7, TN 7th District -> tn-7.
 *
 * @param string $name District name.
 * @return string Stable district slug.
 */
/* DEPRECATED
function fi_taxonomy_generate_district_slug(string $name): string {
	$name = strtoupper(trim($name));
	$state = '';
	$num = '';

	if (preg_match('/^([A-Z]{2})\s+(\d+)/', $name, $m)) {
		$state = $m[1];
		$num = $m[2];
	} elseif (preg_match('/\b([A-Z]{2})\b.*?\b(\d+)\b/', $name, $m)) {
		$state = $m[1];
		$num = $m[2];
	}

	if ($state !== '' && $num !== '') {
		return strtolower($state . '-' . ltrim($num, '0'));
	}

	return sanitize_title($name);
}
*/

/**
 * Check if slug exists for taxonomy and government.
 *
 * @param string $slug Slug.
 * @param string $taxonomy Taxonomy type.
 * @param string|null $gov Government code.
 * @param int|null $exclude_id Exclude ID.
 * @return bool
 */
/* DEPRECATED
function fi_taxonomy_slug_exists(string $slug, string $taxonomy, ?string $gov, ?int $exclude_id = null): bool {
	global $wpdb;

	$sql = "SELECT id FROM {$wpdb->prefix}fi_taxonomy WHERE slug = %s AND taxonomy = %s";
	$values = [$slug, $taxonomy];

	if ($taxonomy !== 'tag' && $gov) {
		$sql .= ' AND gov = %s';
		$values[] = strtoupper($gov);
	}

	if ($exclude_id) {
		$sql .= ' AND id != %d';
		$values[] = $exclude_id;
	}

	$sql .= ' LIMIT 1';

	return !empty($wpdb->get_var($wpdb->prepare($sql, $values)));
}
*/

/**
 * Generate unique slug for taxonomy item.
 *
 * @param string $name Taxonomy item name.
 * @param string $taxonomy Taxonomy type.
 * @param string|null $gov Government code.
 * @return string Unique slug.
 */
/* DEPRECATED
function fi_taxonomy_generate_slug(string $name, string $taxonomy, ?string $gov): string {
	$base_slug = sanitize_title($name);
	$slug = $base_slug;
	$counter = 1;

	while (fi_taxonomy_slug_exists($slug, $taxonomy, $gov)) {
		$slug = $base_slug . '-' . $counter;
		$counter++;
	}

	return $slug;
}
*/

/**
 * Check for duplicate taxonomy items.
 *
 * @param array $data Taxonomy data.
 * @param int|null $exclude_id Exclude ID.
 * @return array{is_duplicate:bool,existing_id:int|null}
 */
function fi_taxonomy_check_duplicates(array $data, ?int $exclude_id = null): array {
	global $wpdb;

	$conditions = [];
	$values = [];

	if (!empty($data['name']) && !empty($data['taxonomy'])) {
		if (($data['taxonomy'] ?? '') === 'tag') {
			$conditions[] = '(name = %s AND taxonomy = %s)';
			$values[] = $data['name'];
			$values[] = $data['taxonomy'];
		} elseif (!empty($data['gov'])) {
			$conditions[] = '(name = %s AND taxonomy = %s AND gov = %s)';
			$values[] = $data['name'];
			$values[] = $data['taxonomy'];
			$values[] = strtoupper((string) $data['gov']);
		}
	}

	if (empty($conditions)) {
		return ['is_duplicate' => false, 'existing_id' => null];
	}

	$where_clause = implode(' OR ', $conditions);
	$sql = "SELECT id FROM {$wpdb->prefix}fi_taxonomy WHERE {$where_clause}";

	if ($exclude_id) {
		$sql .= ' AND id != %d';
		$values[] = $exclude_id;
	}

	$sql .= ' LIMIT 1';

	$existing_id = $wpdb->get_var($wpdb->prepare($sql, $values));

	return [
		'is_duplicate' => !empty($existing_id),
		'existing_id'   => $existing_id ? (int) $existing_id : null,
	];
}

/**
 * Save or update taxonomy item.
 *
 * @param array $data Taxonomy data.
 * @param int|null $taxonomy_id Existing taxonomy ID.
 * @return int|false Taxonomy ID on success, false on failure.
 */
function fi_taxonomy_save(array $data, ?int $taxonomy_id = null): int|false {
	global $wpdb;

	$taxonomy = sanitize_key((string) ($data['taxonomy'] ?? ''));
	$is_tag = ($taxonomy === 'tag');

	$data['taxonomy'] = $taxonomy;

	if ($is_tag && empty($data['gov'])) {
		$data['gov'] = 'US';
	}

	if (empty($data['taxonomy']) || empty($data['name']) || (!$is_tag && empty($data['gov']))) {
		return false;
	}

	$duplicate_check = fi_taxonomy_check_duplicates($data, $taxonomy_id);
	if ($duplicate_check['is_duplicate']) {
		return $duplicate_check['existing_id'];
	}

	$gov = strtoupper(trim((string) $data['gov']));
	if (strlen($gov) > 2) {
		$gov = substr($gov, 0, 2);
	}

	$db_data = [
		'gov'      => $gov,
		'taxonomy' => $taxonomy,
		'name'     => sanitize_text_field($data['name']),
	];

	if (array_key_exists('meta', $data)) {
		$db_data['meta'] = !empty($data['meta']) ? (is_array($data['meta']) ? wp_json_encode($data['meta']) : (string) $data['meta']) : null;
	}

	fi_taxonomy_log('FI Taxonomy Debug - Input data: ' . wp_json_encode($data), __FILE__, __LINE__, 'debug');
	fi_taxonomy_log('FI Taxonomy Debug - DB data: ' . wp_json_encode($db_data), __FILE__, __LINE__, 'debug');

	$format_array = array_fill(0, count($db_data), '%s');

	try {
		if ($taxonomy_id) {
			$result = $wpdb->update(
				$wpdb->prefix . 'fi_taxonomy',
				$db_data,
				['id' => $taxonomy_id],
				$format_array,
				['%d']
			);

			if ($wpdb->last_error) {
				fi_taxonomy_log('FI Taxonomy Debug - Last error: ' . $wpdb->last_error, __FILE__, __LINE__, 'error');
			}

			if ($result !== false) {
				fi_taxonomy_clear_cache();
			}

			return $result !== false ? $taxonomy_id : false;
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'fi_taxonomy',
			$db_data,
			$format_array
		);

		if ($wpdb->last_error) {
			fi_taxonomy_log('FI Taxonomy Debug - Last error: ' . $wpdb->last_error, __FILE__, __LINE__, 'error');
		}

		if ($result !== false) {
			fi_taxonomy_clear_cache();
		}

		return $result !== false ? (int) $wpdb->insert_id : false;
	} catch (Throwable $e) {
		fi_taxonomy_log('FI Taxonomy Debug - EXCEPTION: ' . $e->getMessage(), __FILE__, __LINE__, 'error');
		fi_taxonomy_log('FI Taxonomy Debug - Exception Trace: ' . $e->getTraceAsString(), __FILE__, __LINE__, 'error');
		throw $e;
	}
}

/**
 * Update taxonomy item.
 *
 * @param int $taxonomy_id Taxonomy ID.
 * @param array $data Taxonomy data.
 * @return bool
 */
function fi_taxonomy_update(int $taxonomy_id, array $data): bool {
	return fi_taxonomy_save($data, $taxonomy_id) !== false;
}

/**
 * Delete taxonomy item.
 *
 * @param int $taxonomy_id Taxonomy ID.
 * @return bool
 */
function fi_taxonomy_delete(int $taxonomy_id): bool {
	global $wpdb;

	$taxonomy = fi_taxonomy_get($taxonomy_id);
	if (!$taxonomy) {
		return false;
	}

	$result = $wpdb->delete(
		$wpdb->prefix . 'fi_taxonomy',
		['id' => $taxonomy_id],
		['%d']
	);

	if ($result !== false) {
		fi_taxonomy_clear_cache();
	}

	return $result !== false;
}

/**
 * Get taxonomy statistics.
 *
 * @param string|null $gov Government code.
 * @param string|null $taxonomy Taxonomy type.
 * @return array
 */
function fi_taxonomy_stats(?string $gov = null, ?string $taxonomy = null): array {
	global $wpdb;

	$where_conditions = [];
	$values = [];

	if ($gov) {
		$where_conditions[] = 'gov = %s';
		$values[] = strtoupper($gov);
	}

	if ($taxonomy) {
		$where_conditions[] = 'taxonomy = %s';
		$values[] = sanitize_key($taxonomy);
	}

	$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

	$sql = "
		SELECT
			COUNT(*) as total,
			COUNT(CASE WHEN taxonomy = 'party' THEN 1 END) as parties,
			COUNT(CASE WHEN taxonomy = 'tag' THEN 1 END) as tags,
			COUNT(CASE WHEN taxonomy = 'district' THEN 1 END) as districts
		FROM {$wpdb->prefix}fi_taxonomy
		{$where_clause}
	";

	if (!empty($values)) {
		$sql = $wpdb->prepare($sql, $values);
	}

	$result = $wpdb->get_row($sql, ARRAY_A);

	return is_array($result) ? $result : [
		'total'     => 0,
		'parties'   => 0,
		'tags'      => 0,
		'districts' => 0,
	];
}

/**
 * Validate taxonomy data.
 *
 * @param array $data Taxonomy data.
 * @return array{valid:bool,errors:array}
 */
function fi_taxonomy_validate_data(array $data): array {
	$errors = [];
	$taxonomy = sanitize_key((string) ($data['taxonomy'] ?? ''));
	$is_tag = ($taxonomy === 'tag');

	if (empty($taxonomy)) {
		$errors[] = 'Type is required';
	}

	if (empty($data['name'])) {
		$errors[] = 'Name is required';
	}

	if (!$is_tag && empty($data['gov'])) {
		$errors[] = 'Government code is required';
	}

	if (!empty($taxonomy) && !in_array($taxonomy, ['party', 'tag', 'district'], true)) {
		$errors[] = 'Taxonomy must be party, tag, or district';
	}

	if (!$is_tag && !empty($data['gov']) && !preg_match('/^[A-Z]{2}$/', strtoupper((string) $data['gov']))) {
		$errors[] = 'Government code must be 2 uppercase letters';
	}


	return [
		'valid'  => empty($errors),
		'errors' => $errors,
	];
}

/**
 * Get taxonomy tree structure.
 *
 * @param string|null $gov Government code.
 * @return array
 */
function fi_taxonomy_tree(?string $gov = null): array {
	$args = [
		'gov'      => $gov,
		'per_page' => -1,
	];

	$items = fi_taxonomies_get($args);
	$items = is_array($items) ? $items : [];

	$tree = [
		'party'    => [],
		'tag'      => [],
		'district' => [],
	];

	foreach ($items as $item) {
		if (isset($tree[$item->taxonomy])) {
			$tree[$item->taxonomy][] = $item;
		}
	}

	return $tree;
}

/**
 * Get vote tags.
 *
 * @param string|null $gov Ignored for tags because tags are global.
 * @param array $filters Additional filters.
 * @return array
 */
function fi_tags_get(?string $gov = null, array $filters = []): array {
	$args = array_merge($filters, ['taxonomy' => 'tag']);
	$results = fi_taxonomies_get($args);

	return is_array($results) ? $results : [];
}

/**
 * Get districts.
 *
 * @param string|null $gov Government code.
 * @param array $filters Additional filters.
 * @return array
 */
function fi_districts_get(?string $gov = null, array $filters = []): array {
	$args = array_merge($filters, ['taxonomy' => 'district']);

	if ($gov) {
		$args['gov'] = strtoupper($gov);
	}

	$results = fi_taxonomies_get($args);

	return is_array($results) ? $results : [];
}

/**
 * Look up a district name by taxonomy ID.
 *
 * Loads ALL district names in a single query and caches them as id=>name.
 * If the requested ID is not in the cache, the cache is refreshed once.
 *
 * @param int $id District taxonomy ID.
 * @return string District name, or empty string if not found.
 */
function fi_district_name(int $id): string {
	if ($id <= 0) return '';

	static $names = null;

	$cache_key = 'reference/district-names';

	if ($names === null) {
		$cached = fi_cache($cache_key);
		$names  = is_array($cached) ? $cached : [];
	}

	if (!isset($names[$id])) {
		global $wpdb;
		$rows  = $wpdb->get_results(
			"SELECT id, gov, name FROM {$wpdb->prefix}fi_taxonomy WHERE taxonomy = 'district'",
			ARRAY_A
		) ?: [];
		$names = [];
		foreach ($rows as $row) {
			$display = $row['name'];
			if (strtoupper((string) $row['gov']) === 'US'
				&& preg_match('/^([A-Z]{2}) (.+)$/', $display, $m)
				&& defined('FI_GOVERNMENTS')
				&& isset(FI_GOVERNMENTS[$m[1]])
			) {
				$display = FI_GOVERNMENTS[$m[1]] . ' ' . $m[2] . ' District';
			}
			$names[(int) $row['id']] = $display;
		}
		fi_cache($cache_key, $names, DAY_IN_SECONDS);
	}

	return $names[$id] ?? '';
}

/**
 * Get district by ID, trimmed for front-end use.
 *
 * @param int $district_id District taxonomy ID.
 * @return array|null
 */
function fi_district_get(int $district_id): ?array {
	$district = fi_taxonomy_get($district_id);
	if (!$district) {
		return null;
	}

	unset($district['legacy_id'], $district['taxonomy'], $district['meta'], $district['date_created'], $district['date_updated']);
	$district['name_short'] = preg_replace('/^[A-Z]{2} /', '', $district['name'] ?? '');

	return $district;
}

/**
 * Resolve a LegiScan-style district string into fi_taxonomy district ID.
 *
 * Expected mapping:
 * - HD-TN-7 or SD-TN-7 input.
 * - Target slug contains tn-7.
 *
 * @param string $district_raw Raw LegiScan district string.
 * @param string $gov Government code.
 * @param string|null $chamber Chamber code.
 * @return int|null Taxonomy ID or null.
 */
function fi_district_id_from_legiscan(string $district_raw, string $gov = 'US', ?string $chamber = null): ?int {
	global $wpdb;

	$district_raw = trim($district_raw);
	if ($district_raw === '') {
		return null;
	}

	$gov = strtoupper(trim($gov));
	if ($gov === '') {
		$gov = 'US';
	}

	$chamber = $chamber ? strtoupper(trim($chamber)) : null;

	// No slug column — fall through to name-based lookup below.

	if ($gov !== 'US') {
		return null;
	}

	$parts = array_values(array_filter(explode('-', $district_raw), static fn($p) => $p !== ''));
	$prefix = strtoupper((string) ($parts[0] ?? ''));
	$state = '';
	$num = '';

	if (count($parts) >= 2) {
		$state = strtoupper((string) ($parts[1] ?? ''));
		$num = (string) ($parts[2] ?? '');
	} elseif (preg_match('/\b([A-Z]{2})\b.*?(\d+)\b/', strtoupper($district_raw), $m)) {
		$state = strtoupper($m[1]);
		$num = (string) $m[2];
	}

	if (!preg_match('/^[A-Z]{2}$/', $state)) {
		return null;
	}

	if ($gov === 'US' && ($chamber === 'S' || $prefix === 'SD')) {
		return null;
	}

	if ($gov === 'US' && ($chamber === 'H' || $chamber === null) && $prefix === 'HD' && ($num === '' || !preg_match('/^\d+$/', $num))) {
		$count = function_exists('fi_district_congressional_count') ? fi_district_congressional_count($state) : null;
		if ((int) $count !== 1) {
			return null;
		}

		$at_large_name = strtoupper($state) . ' At Large';
		$existing = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}fi_taxonomy WHERE taxonomy='district' AND gov=%s AND name=%s LIMIT 1",
			$gov,
			$at_large_name
		));
		if ($existing) {
			return $existing;
		}

		$new_id = fi_taxonomy_save([
			'gov'      => $gov,
			'taxonomy' => 'district',
			'name'     => $at_large_name,
			'meta'     => [
				'created_from' => 'legiscan',
				'state'        => strtoupper($state),
				'at_large'     => 1,
			],
		]);

		return $new_id ? (int) $new_id : null;
	}

	if (!preg_match('/^\d+$/', (string) $num)) {
		return null;
	}

	$ordinal = function_exists('fi_format_ordinal') ? fi_format_ordinal((int) $num) : (string) ((int) $num);
	$district_name = strtoupper($state) . ' ' . $ordinal;

	$row = $wpdb->get_row($wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}fi_taxonomy
		WHERE taxonomy = 'district' AND gov = %s AND name = %s
		LIMIT 1",
		$gov,
		$district_name
	), ARRAY_A);

	if ($row && !empty($row['id'])) {
		return (int) $row['id'];
	}

	$like = $wpdb->esc_like(strtoupper($state) . ' ' . ltrim($num, '0')) . '%';
	$row2 = $wpdb->get_row($wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}fi_taxonomy
		WHERE taxonomy = 'district' AND gov = %s AND name LIKE %s
		ORDER BY id ASC
		LIMIT 1",
		$gov,
		$like
	), ARRAY_A);

	if ($row2 && !empty($row2['id'])) {
		return (int) $row2['id'];
	}

	$new_id = fi_taxonomy_save([
		'gov'      => $gov,
		'taxonomy' => 'district',
		'name'     => $district_name,
		'meta'     => [
			'created_from' => 'legiscan',
			'state'        => strtoupper($state),
			'number'       => (int) $num,
		],
	]);

	return $new_id ? (int) $new_id : null;
}

/**
 * Get tag URL for votes filtered by tag.
 *
 * @param string $tag_slug Tag slug.
 * @param string|null $gov Government code.
 * @return string Tag URL.
 */
function fi_tag_url(int $tag_id, ?string $gov = null): string {
	if (!$gov) {
		$tag = fi_taxonomy_get($tag_id);
		$gov = ($tag && !empty($tag['gov'])) ? strtolower($tag['gov']) : 'us';
	}

	return home_url('/' . strtolower($gov) . '/votes/issue/' . $tag_id . '/');
}