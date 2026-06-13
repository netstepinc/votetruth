<?php
/*
 * Freedom Index Sessions Table I/O Operations
 *
 * Straight function version of the former FICore\Sessions class file.
 * Handles all database operations for the fi_sessions table.
 *
 * Notes:
 * - This refactor intentionally omits slug-based public helpers/lookups.
 * - Public URL behavior should remain ID-based.
 Removed slug from fi_sessions_get() filters.
Removed slug search from keyword search.
Removed slug ordering.
Removed slug generation.
Removed slug duplicate checks.
Removed deprecated slug wrappers:
fi_session_get_by_slug()
fi_session_get_id_from_slug()
Removed slug validation from fi_session_validate_data().

Still preserved the ID-based/session behavior:

fi_session_get()
fi_sessions_get()
fi_sessions_get_by_gov()
fi_session_get_current()
fi_session_get_current_id()
fi_session_get_by_legiscan_id()
save/update/delete
current session assignment
hierarchy helpers
parent/child helpers
direct JSON meta helpers
 */

if (!defined('ABSPATH')) exit;

/**
 * Internal request-level cache store for session helpers.
 *
 * @param string $group Cache group.
 * @param string|null $key Cache key.
 * @param mixed $value Value to set. Pass null to read.
 * @param bool $set Whether to set the value.
 * @return mixed
 */
function fi_sessions_request_cache(string $group, ?string $key = null, $value = null, bool $set = false) {
	static $cache = [
		'get'    => [],
		'by_gov' => [],
	];

	if ($group === 'clear') {
		$cache = [
			'get'    => [],
			'by_gov' => [],
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
 * Clear sessions request-level cache.
 *
 * @return void
 */
function fi_sessions_clear_cache(): void {
	fi_sessions_request_cache('clear');
}

/**
 * Internal logging shim for sessions.
 *
 * @param string $message Log message.
 * @param string $file File path.
 * @param int $line Line number.
 * @param string $level Log level.
 * @return void
 */
function fi_sessions_log(string $message, string $file = '', int $line = 0, string $level = 'info'): void {
	// fi_log_area('sessions', $message, $file, $line, $level);
}

/**
 * Add the Congress suffix to US session names when missing.
 *
 * @param object|null $session Session object.
 * @return object|null
 */
function fi_session_format_name(?object $session): ?object {
	if ($session && !empty($session->name) && strtoupper($session->gov ?? '') === 'US' && strpos($session->name, 'Congress') === false) {
		$session->name .= ' Congress';
	}

	return $session;
}

/**
 * Add formatted names to a session result array.
 *
 * @param array $sessions Session objects.
 * @return array
 */
function fi_sessions_format_names(array $sessions): array {
	foreach ($sessions as $session) {
		fi_session_format_name($session);
	}

	return $sessions;
}

/**
 * Build database format specifiers for fi_sessions data.
 *
 * @param array $db_data Data being written.
 * @return array Format specifiers.
 */
function fi_session_db_formats(array $db_data): array {
	$formats = [];

	foreach ($db_data as $key => $value) {
		if (in_array($key, ['parent_id', 'legiscan_id', 'is_current'], true)) {
			$formats[] = '%d';
		} else {
			$formats[] = '%s';
		}
	}

	return $formats;
}

/**
 * Get sessions with optional filtering.
 *
 * @param array $args Optional query arguments.
 * @return array|int Array of session objects or count if count is true.
 */
function fi_sessions_get(array $args = []): array|int {
	global $wpdb;

	$defaults = [
		'id'         => null,
		'gov'        => null,
		'is_current' => null,
		'status'     => null,
		'search'     => null,
		'parent_id'  => null,
		'orderby'    => 'date_start',
		'order'      => 'DESC',
		'per_page'   => -1,
		'page'       => 1,
		'count'      => false,
	];

	$args = wp_parse_args($args, $defaults);

	if (!is_admin() && $args['status'] === null) {
		$args['status'] = 'publish';
	}

	$cache_key = md5(serialize($args));
	$cached = fi_sessions_request_cache('get', $cache_key);
	if ($cached !== null) {
		return $cached;
	}

	$cacheKey = fi_cache_key('sessions/get', $args);
	fi_sessions_log('Sessions::get:Cache key: ' . $cacheKey, __FILE__, __LINE__);

	$results = fi_cache($cacheKey);
	if ($results) {
		fi_sessions_request_cache('get', $cache_key, $results, true);
		return $results;
	}

	$where_conditions = [];
	$where_values = [];

	if (!empty($args['id'])) {
		$where_conditions[] = 'id = %d';
		$where_values[] = absint($args['id']);
	}

	if (!empty($args['gov'])) {
		$where_conditions[] = 'gov = %s';
		$where_values[] = strtoupper((string) $args['gov']);
	}

	if (!is_admin()) {
		if ($args['parent_id'] === null) {
			$where_conditions[] = 'parent_id IS NULL';
		} elseif ((int) $args['parent_id'] > 0) {
			$where_conditions[] = 'parent_id = %d';
			$where_values[] = absint($args['parent_id']);
		}
	}

	if ($args['status'] !== null) {
		$where_conditions[] = 'status = %s';
		$where_values[] = (string) $args['status'];
	}

	if ($args['is_current'] !== null) {
		$where_conditions[] = 'is_current = %d';
		$where_values[] = $args['is_current'] ? 1 : 0;
	}

	if (!empty($args['search'])) {
		$where_conditions[] = 'name LIKE %s';
		$where_values[] = '%' . $wpdb->esc_like((string) $args['search']) . '%';
	}

	$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

	$dir = strtoupper((string) ($args['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
	$orderby_field = sanitize_key((string) ($args['orderby'] ?? 'date_start'));
	$allowed_fields = ['date_start', 'date_end', 'name', 'id'];

	if (!in_array($orderby_field, $allowed_fields, true)) {
		$orderby_field = 'date_start';
	}

	if ($orderby_field === 'date_start') {
		$orderby = "date_start IS NULL ASC, date_start {$dir}, name {$dir}, id DESC";
	} elseif ($orderby_field === 'date_end') {
		$orderby = "date_end IS NULL ASC, date_end {$dir}, name {$dir}, id DESC";
	} elseif ($orderby_field === 'name') {
		$orderby = "name {$dir}, id DESC";
	} else {
		$orderby = "id {$dir}";
	}

	$limit_clause = '';
	if ((int) $args['per_page'] > 0) {
		$per_page = absint($args['per_page']);
		$page = max(1, absint($args['page']));
		$offset = ($page - 1) * $per_page;
		$limit_clause = $wpdb->prepare('LIMIT %d OFFSET %d', $per_page, $offset);
	}

	if (!empty($args['count'])) {
		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}fi_sessions {$where_clause}";

		if (!empty($where_values)) {
			$sql = $wpdb->prepare($sql, $where_values);
		}

		$results = (int) $wpdb->get_var($sql);
		fi_sessions_request_cache('get', $cache_key, $results, true);
		fi_cache($cacheKey, $results);
		return $results;
	}

	$sql = "
		SELECT * FROM {$wpdb->prefix}fi_sessions
		{$where_clause}
		ORDER BY {$orderby}
		{$limit_clause}
	";

	if (!empty($where_values)) {
		$sql = $wpdb->prepare($sql, $where_values);
	}

	fi_sessions_log('Sessions::get:SQL: ' . str_replace("\n", ' ', $sql), __FILE__, __LINE__);

	$results = fi_sessions_format_names($wpdb->get_results($sql));

	fi_cache($cacheKey, $results);
	fi_sessions_request_cache('get', $cache_key, $results, true);

	return $results;
}

/**
 * Get a single session by ID.
 *
 * @param int $session_id Session ID.
 * @return object|null
 */
function fi_session_get(int $session_id): ?object {
	$results = fi_sessions_get([
		'id'       => $session_id,
		'per_page' => 1,
	]);

	return is_array($results) ? ($results[0] ?? null) : null;
}

/**
 * Get sessions by government.
 *
 * @param string $gov Government code.
 * @param array $filters Additional filters.
 * @return array
 */
function fi_sessions_get_by_gov(string $gov, array $filters = []): array {
	$gov = strtoupper($gov);
	$cache_key = $gov . '|' . md5(serialize($filters));
	$cached = fi_sessions_request_cache('by_gov', $cache_key);

	if ($cached !== null) {
		return $cached;
	}

	$args = array_merge($filters, ['gov' => $gov]);
	fi_sessions_log('Sessions::get_by_gov:Args: ' . wp_json_encode($args), __FILE__, __LINE__);

	$result = fi_sessions_get($args);
	$result = is_array($result) ? $result : [];

	fi_sessions_request_cache('by_gov', $cache_key, $result, true);

	return $result;
}

/**
 * Get current session for a government.
 *
 * First checks is_current = 1, then falls back to most recent/top-level session.
 *
 * @param string $gov Government code.
 * @return object|null
 */
function fi_session_get_current(string $gov): ?object {
	$gov = strtoupper($gov);

	$results = fi_sessions_get([
		'gov'        => $gov,
		'is_current' => true,
		'orderby'    => 'date_start',
		'order'      => 'DESC',
		'per_page'   => 1,
	]);

	$session = is_array($results) ? ($results[0] ?? null) : null;

	if (!$session) {
		$sessions = fi_sessions_get_by_gov($gov, [
			'orderby' => 'date_start',
			'order'   => 'DESC',
		]);

		foreach ($sessions as $s) {
			if ($s->parent_id === null || (int) $s->parent_id === 0) {
				$session = $s;
				break;
			}
		}

		if (!$session && !empty($sessions)) {
			$session = $sessions[0];
		}
	}

	return fi_session_format_name($session);
}

/**
 * Get current session ID for a government.
 *
 * @param string $gov Government code.
 * @return int|null
 */
function fi_session_get_current_id(string $gov): ?int {
	$session = fi_session_get_current($gov);
	return $session ? (int) $session->id : null;
}

/**
 * Get session by Legiscan ID.
 *
 * @param int $legiscan_id Legiscan session ID.
 * @param string|null $gov Optional government code.
 * @return object|null
 */
function fi_session_get_by_legiscan_id(int $legiscan_id, ?string $gov = null): ?object {
	global $wpdb;

	$where_conditions = ['legiscan_id = %d'];
	$where_values = [$legiscan_id];

	if ($gov) {
		$where_conditions[] = 'gov = %s';
		$where_values[] = strtoupper($gov);
	}

	$where_clause = implode(' AND ', $where_conditions);
	$sql = "SELECT * FROM {$wpdb->prefix}fi_sessions WHERE {$where_clause} LIMIT 1";

	$session = $wpdb->get_row($wpdb->prepare($sql, $where_values));

	if ($session && !empty($session->meta)) {
		$decoded = json_decode($session->meta, true);
		$session->meta = is_array($decoded) ? $decoded : [];
	}

	return fi_session_format_name($session ?: null);
}

/**
 * Save or update session with duplicate checking.
 *
 * Slugs are intentionally not generated or used for duplicate detection.
 *
 * @param array $data Session data.
 * @param int|null $session_id Existing session ID.
 * @return int|false Session ID on success, false on failure.
 */
function fi_session_save(array $data, ?int $session_id = null): int|false {
	global $wpdb;

	if (empty($data['name']) || empty($data['gov'])) {
		return false;
	}

	$data['gov'] = strtoupper((string) $data['gov']);

	$is_current = !empty($data['is_current']);
	if ($is_current) {
		$wpdb->update(
			$wpdb->prefix . 'fi_sessions',
			['is_current' => 0],
			['gov' => $data['gov']],
			['%d'],
			['%s']
		);
	}

	$duplicate_check = fi_session_check_duplicates($data, $session_id);
	if ($duplicate_check['is_duplicate']) {
		return $duplicate_check['existing_id'];
	}

	$db_data = [
		'gov'  => $data['gov'],
		'name' => sanitize_text_field($data['name']),
	];

	if (array_key_exists('parent_id', $data)) {
		$db_data['parent_id'] = (!empty($data['parent_id']) && (int) $data['parent_id'] > 0) ? (int) $data['parent_id'] : null;
	}

	if (array_key_exists('legacy_id', $data)) {
		$db_data['legacy_id'] = $data['legacy_id'] ?: null;
	}

	if (array_key_exists('legiscan_id', $data)) {
		$db_data['legiscan_id'] = $data['legiscan_id'] ?: null;
	}

	if (array_key_exists('date_start', $data)) {
		$db_data['date_start'] = $data['date_start'] ?: null;
	}

	if (array_key_exists('date_end', $data)) {
		$db_data['date_end'] = $data['date_end'] ?: null;
	}

	if (array_key_exists('is_current', $data)) {
		$db_data['is_current'] = !empty($data['is_current']) ? 1 : 0;
	}

	if (array_key_exists('status', $data)) {
		$db_data['status'] = in_array($data['status'], ['publish', 'draft'], true) ? $data['status'] : 'draft';
	}

	if (array_key_exists('meta', $data)) {
		$db_data['meta'] = !empty($data['meta']) ? (is_string($data['meta']) ? $data['meta'] : wp_json_encode($data['meta'])) : null;
	}

	$formats = fi_session_db_formats($db_data);

	if ($session_id) {
		$result = $wpdb->update(
			$wpdb->prefix . 'fi_sessions',
			$db_data,
			['id' => $session_id],
			$formats,
			['%d']
		);

		if ($result !== false) {
			fi_sessions_clear_cache();
		}

		return $result !== false ? $session_id : false;
	}

	$final_check = fi_session_check_duplicates($data, null);
	if ($final_check['is_duplicate']) {
		return $final_check['existing_id'];
	}

	$result = $wpdb->insert(
		$wpdb->prefix . 'fi_sessions',
		$db_data,
		$formats
	);

	if ($result === false && !empty($wpdb->last_error)) {
		if (strpos($wpdb->last_error, 'Duplicate entry') !== false || strpos($wpdb->last_error, 'UNIQUE') !== false) {
			$existing = fi_session_check_duplicates($data, null);
			if ($existing['is_duplicate']) {
				return $existing['existing_id'];
			}
		}
	}

	if ($result !== false) {
		fi_sessions_clear_cache();
	}

	return $result !== false ? (int) $wpdb->insert_id : false;
}

/**
 * Update session.
 *
 * @param int $session_id Session ID.
 * @param array $data Data to update.
 * @return bool
 */
function fi_session_update(int $session_id, array $data): bool {
	return fi_session_save($data, $session_id) !== false;
}

/**
 * Delete session.
 *
 * @param int $session_id Session ID.
 * @return bool
 */
function fi_session_delete(int $session_id): bool {
	global $wpdb;

	fi_log('fi_session_delete: ' . $session_id, __FILE__, __LINE__);

	$session = fi_session_get($session_id);
	if (!$session) {
		return false;
	}

	$wpdb->delete(
		$wpdb->prefix . 'fi_legislator_sessions',
		['session_id' => $session_id],
		['%d']
	);

	$wpdb->update(
		$wpdb->prefix . 'fi_reports',
		['session_id' => 0],
		['session_id' => $session_id],
		['%d'],
		['%d']
	);

	$wpdb->update(
		$wpdb->prefix . 'fi_votes',
		['session_id' => 0],
		['session_id' => $session_id],
		['%d'],
		['%d']
	);

	$result = $wpdb->delete(
		$wpdb->prefix . 'fi_sessions',
		['id' => $session_id],
		['%d']
	);

	if ($result !== false) {
		fi_sessions_clear_cache();
	}

	return $result !== false;
}

/**
 * Check for duplicate sessions.
 *
 * Slugs are intentionally ignored. Duplicate checks use legacy_id and legiscan_id only.
 *
 * @param array $data Session data.
 * @param int|null $exclude_id Exclude this ID from duplicate check.
 * @return array{is_duplicate:bool,existing_id:int|null}
 */
function fi_session_check_duplicates(array $data, ?int $exclude_id = null): array {
	global $wpdb;

	$conditions = [];
	$values = [];
	$gov = !empty($data['gov']) ? strtoupper((string) $data['gov']) : null;

	if (!empty($data['legacy_id']) && !empty($gov)) {
		$conditions[] = '(legacy_id = %s AND gov = %s)';
		$values[] = (string) $data['legacy_id'];
		$values[] = $gov;
	}

	if (!empty($data['legiscan_id']) && !empty($gov)) {
		$conditions[] = '(legiscan_id = %d AND gov = %s)';
		$values[] = (int) $data['legiscan_id'];
		$values[] = $gov;
	}

	if (empty($conditions)) {
		return ['is_duplicate' => false, 'existing_id' => null];
	}

	$where_clause = implode(' OR ', $conditions);
	$sql = "SELECT id FROM {$wpdb->prefix}fi_sessions WHERE ({$where_clause})";

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
 * Set current session for a government.
 *
 * @param string $gov Government code.
 * @param int $session_id Session ID.
 * @return bool
 */
function fi_session_set_current(string $gov, int $session_id): bool {
	global $wpdb;

	$gov = strtoupper($gov);

	$wpdb->update(
		$wpdb->prefix . 'fi_sessions',
		['is_current' => 0],
		['gov' => $gov],
		['%d'],
		['%s']
	);

	$result = $wpdb->update(
		$wpdb->prefix . 'fi_sessions',
		['is_current' => 1],
		['id' => $session_id, 'gov' => $gov],
		['%d'],
		['%d', '%s']
	);

	if ($result !== false) {
		fi_sessions_clear_cache();
	}

	return $result !== false;
}

/**
 * Get session statistics.
 *
 * @param string|null $gov Government code.
 * @return array
 */
function fi_sessions_stats(?string $gov = null): array {
	global $wpdb;

	$where_clause = $gov ? 'WHERE gov = %s' : '';
	$values = $gov ? [strtoupper($gov)] : [];

	$sql = "
		SELECT
			COUNT(*) as total,
			COUNT(CASE WHEN is_current = 1 THEN 1 END) as current,
			COUNT(CASE WHEN date_start IS NOT NULL THEN 1 END) as with_dates
		FROM {$wpdb->prefix}fi_sessions
		{$where_clause}
	";

	if (!empty($values)) {
		$sql = $wpdb->prepare($sql, $values);
	}

	$result = $wpdb->get_row($sql, ARRAY_A);

	return is_array($result) ? $result : [
		'total'      => 0,
		'current'    => 0,
		'with_dates' => 0,
	];
}

/**
 * Get statistics for a specific session.
 *
 * @param int $session_id Session ID.
 * @return array{legislators:int,votes:int,reports:int}
 */
function fi_session_get_stats(int $session_id): array {
	global $wpdb;

	$legislators = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(DISTINCT legislator_id) FROM {$wpdb->prefix}fi_legislator_sessions WHERE session_id = %d",
		$session_id
	));

	$votes = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}fi_votes WHERE session_id = %d AND status = 'publish'",
		$session_id
	));

	$reports = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}fi_reports WHERE session_id = %d",
		$session_id
	));

	return [
		'legislators' => $legislators,
		'votes'       => $votes,
		'reports'     => $reports,
	];
}

/**
 * Validate session data.
 *
 * @param array $data Session data.
 * @return array{valid:bool,errors:array}
 */
function fi_session_validate_data(array $data): array {
	$errors = [];

	if (empty($data['name'])) {
		$errors[] = 'Session name is required';
	}

	if (empty($data['gov'])) {
		$errors[] = 'Government code is required';
	}

	if (!empty($data['gov']) && !preg_match('/^[A-Z]{2}$/', strtoupper((string) $data['gov']))) {
		$errors[] = 'Government code must be 2 uppercase letters';
	}

	if (!empty($data['date_start']) && !fi_session_validate_date($data['date_start'])) {
		$errors[] = 'Invalid start date format';
	}

	if (!empty($data['date_end']) && !fi_session_validate_date($data['date_end'])) {
		$errors[] = 'Invalid end date format';
	}

	return [
		'valid'  => empty($errors),
		'errors' => $errors,
	];
}

/**
 * Validate date format.
 *
 * @param string $date Date in Y-m-d format.
 * @return bool
 */
function fi_session_validate_date(string $date): bool {
	$d = DateTime::createFromFormat('Y-m-d', $date);
	return $d && $d->format('Y-m-d') === $date;
}

/**
 * Get child sessions of a parent session.
 *
 * @param int $parent_id Parent session ID.
 * @return array
 */
function fi_sessions_get_children(int $parent_id): array {
	global $wpdb;

	$results = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}fi_sessions
		WHERE parent_id = %d
		ORDER BY date_start ASC, name ASC",
		$parent_id
	));

	return fi_sessions_format_names($results);
}

/**
 * Get parent session of a child session.
 *
 * @param int $child_id Child session ID.
 * @return object|null
 */
function fi_session_get_parent(int $child_id): ?object {
	global $wpdb;

	$session = $wpdb->get_row($wpdb->prepare(
		"SELECT p.* FROM {$wpdb->prefix}fi_sessions p
		INNER JOIN {$wpdb->prefix}fi_sessions c ON c.parent_id = p.id
		WHERE c.id = %d",
		$child_id
	));

	return fi_session_format_name($session ?: null);
}

/**
 * Get all sessions in a hierarchy: parent + children.
 *
 * @param int $session_id Session ID.
 * @return array
 */
function fi_sessions_get_hierarchy(int $session_id): array {
	$session = fi_session_get($session_id);
	if (!$session) {
		return [];
	}

	$hierarchy = [$session];

	if ($session->parent_id === null || (int) $session->parent_id === 0) {
		$children = fi_sessions_get_children($session_id);
		$hierarchy = array_merge($hierarchy, $children);
	} else {
		$parent = fi_session_get_parent($session_id);
		if ($parent) {
			$hierarchy = [$parent];
			$siblings = fi_sessions_get_children((int) $parent->id);
			$hierarchy = array_merge($hierarchy, $siblings);
		}
	}

	return $hierarchy;
}

/**
 * Get all session IDs in a hierarchy.
 *
 * @param int $session_id Session ID.
 * @return array
 */
function fi_sessions_get_hierarchy_ids(int $session_id): array {
	$hierarchy = fi_sessions_get_hierarchy($session_id);

	return array_map(static function($session) {
		return (int) $session->id;
	}, $hierarchy);
}

/**
 * Check if session is a parent.
 *
 * @param int $session_id Session ID.
 * @return bool
 */
function fi_session_is_parent(int $session_id): bool {
	global $wpdb;

	$count = $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}fi_sessions WHERE parent_id = %d",
		$session_id
	));

	return (int) $count > 0;
}

/**
 * Check if session is a child.
 *
 * @param int $session_id Session ID.
 * @return bool
 */
function fi_session_is_child(int $session_id): bool {
	global $wpdb;

	$parent_id = $wpdb->get_var($wpdb->prepare(
		"SELECT parent_id FROM {$wpdb->prefix}fi_sessions WHERE id = %d",
		$session_id
	));

	return (int) $parent_id > 0;
}

/**
 * Get decoded session meta array from a session object or session ID.
 *
 * @param object|int $session Session object or ID.
 * @return array
 */
function fi_session_get_all_meta($session): array {
	global $wpdb;

	if (is_numeric($session)) {
		$session = $wpdb->get_row($wpdb->prepare(
			"SELECT meta FROM {$wpdb->prefix}fi_sessions WHERE id = %d LIMIT 1",
			absint($session)
		));
	}

	if (empty($session) || !isset($session->meta) || $session->meta === null || $session->meta === '') {
		return [];
	}

	if (is_array($session->meta)) {
		return $session->meta;
	}

	$decoded = json_decode((string) $session->meta, true);
	return is_array($decoded) ? $decoded : [];
}

/**
 * Get session meta value by key.
 *
 * @param object|int $session Session object or ID.
 * @param string $key Meta key.
 * @param mixed $default Default value.
 * @return mixed
 */
function fi_session_get_meta($session, string $key, $default = null) {
	$meta = fi_session_get_all_meta($session);
	return array_key_exists($key, $meta) ? $meta[$key] : $default;
}

/**
 * Set entire session meta array.
 *
 * @param int $session_id Session ID.
 * @param array $meta Meta array.
 * @return bool
 */
function fi_session_set_all_meta(int $session_id, array $meta): bool {
	global $wpdb;

	$result = $wpdb->update(
		$wpdb->prefix . 'fi_sessions',
		['meta' => wp_json_encode($meta)],
		['id' => $session_id],
		['%s'],
		['%d']
	);

	if ($result !== false) {
		fi_sessions_clear_cache();
	}

	return $result !== false;
}

/**
 * Update session meta key(s) without affecting other keys.
 *
 * @param int $session_id Session ID.
 * @param array $meta_updates Meta key/value updates.
 * @return bool
 */
function fi_session_update_meta(int $session_id, array $meta_updates): bool {
	$meta = fi_session_get_all_meta($session_id);
	$meta = array_merge($meta, $meta_updates);

	return fi_session_set_all_meta($session_id, $meta);
}

/**
 * Delete session meta key(s).
 *
 * @param int $session_id Session ID.
 * @param string|array $keys Meta key or keys.
 * @return bool
 */
function fi_session_delete_meta(int $session_id, $keys): bool {
	$meta = fi_session_get_all_meta($session_id);
	$keys = is_array($keys) ? $keys : [$keys];

	foreach ($keys as $key) {
		unset($meta[$key]);
	}

	return fi_session_set_all_meta($session_id, $meta);
}
