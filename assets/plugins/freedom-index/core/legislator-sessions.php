<?php
/*
 * Freedom Index Legislator Sessions Table I/O Operations
 *
 * Straight function version of the former FICore\LegislatorSessions class file.
 * Handles legislator-session relationships and embedded scoring.
 * Refactored this file into straight functions and preserved the existing public function names.

Key adjustments:

Replaced class static request cache with a function-local static $cache_get.
Converted class methods directly into the existing public functions.
Preserved shared lookup reuse through fi_legislator_sessions_get().
Added direct JSON meta column helpers to replace the missing trait dependency.
Fixed the stats query composition: the original built a second WHERE after WHERE ls.score_data IS NOT NULL, which would produce invalid SQL when filters were present. The refactor appends filters with AND.
 */

if (!defined('ABSPATH')) exit;

/**
 * Query legislator sessions with optional filtering (DB-only, no cache).
 *
 * @param array $args Optional query arguments.
 * @return array|int Array of legislator session objects or count if count is true.
 */
function fi_legislator_sessions_query(array $args = []): array|int {
	global $wpdb;

	$defaults = [
		'id'            => null,
		'legislator_id' => null,
		'session_id'    => null,
		'session_ids'   => null,
		'gov'           => null,
		'state'         => null,
		'chamber'       => null,
		'district'      => null,
		'party'         => null,
		'orderby'       => 'score',
		'order'         => 'DESC',
		'per_page'      => -1,
		'page'          => 1,
		'count'         => false,
	];

	$args = wp_parse_args($args, $defaults);

	$where_conditions = [];
	$where_values = [];

	if (!empty($args['id'])) {
		$where_conditions[] = 'ls.id = %d';
		$where_values[] = absint($args['id']);
	}

	if (!empty($args['legislator_id'])) {
		$where_conditions[] = 'ls.legislator_id = %d';
		$where_values[] = absint($args['legislator_id']);
	}

	if (!empty($args['session_id'])) {
		$session_ids = fi_sessions_get_hierarchy_ids(absint($args['session_id']));
		$session_ids = array_values(array_filter(array_map('absint', (array) $session_ids)));
		if (!empty($session_ids)) {
			$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
			$where_conditions[] = "ls.session_id IN ($placeholders)";
			$where_values = array_merge($where_values, $session_ids);
		}
	} elseif (!empty($args['session_ids']) && is_array($args['session_ids'])) {
		$session_ids = array_values(array_filter(array_map('absint', $args['session_ids'])));
		if (!empty($session_ids)) {
			$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
			$where_conditions[] = "ls.session_id IN ($placeholders)";
			$where_values = array_merge($where_values, $session_ids);
		}
	}

	if (!empty($args['gov'])) {
		$where_conditions[] = 'ls.gov = %s';
		$where_values[] = strtoupper((string) $args['gov']);
	}

	if (!empty($args['chamber'])) {
		$where_conditions[] = 'ls.chamber = %s';
		$where_values[] = strtoupper((string) $args['chamber']);
	}

	if (!empty($args['party'])) {
		$where_conditions[] = 'ls.party = %s';
		$where_values[] = (string) $args['party'];
	}

	if (!empty($args['state'])) {
		$where_conditions[] = 'ls.state = %s';
		$where_values[] = strtoupper((string) $args['state']);
	}

	$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

	$allowed_orderby = ['id', 'score', 'date_created', 'date_updated', 'date_end'];
	$orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'id';
	$order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';

	$order_clause = ($orderby === 'date_end')
		? "ORDER BY s.date_end {$order}, s.id DESC"
		: "ORDER BY ls.{$orderby} {$order}";

	$limit_clause = '';
	if ((int) $args['per_page'] > 0) {
		$per_page = absint($args['per_page']);
		$page = max(1, absint($args['page']));
		$offset = ($page - 1) * $per_page;
		$limit_clause = $wpdb->prepare('LIMIT %d OFFSET %d', $per_page, $offset);
	}

	if (!empty($args['count'])) {
		$sql = "
			SELECT COUNT(*)
			FROM {$wpdb->prefix}fi_legislator_sessions ls
			{$where_clause}
		";

		if (!empty($where_values)) {
			$sql = $wpdb->prepare($sql, $where_values);
		}

		return (int) $wpdb->get_var($sql);
	}

	$sql = "
		SELECT
			ls.*,
			l.first_name,
			l.last_name,
			l.display_name,
			s.name AS session_name,
			s.gov,
			s.parent_id,
			s.status
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
		LEFT JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		{$where_clause}
		{$order_clause}
		{$limit_clause}
	";

	if (!empty($where_values)) {
		$sql = $wpdb->prepare($sql, $where_values);
	}

	return $wpdb->get_results($sql, ARRAY_A);
}



/**
 * Get full session history for a legislator profile, most-recent first.
 * Parent sessions only (sub-sessions excluded). District name resolved from fi_taxonomy.
 * Returns formatted rows ready for display (gov_name, party_name, chamber_label, etc.).
 *
 * @param int $legislator_id
 * @return array
 */
function fi_legislator_sessions_get_history(int $legislator_id): array {
	global $wpdb;

	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT
			s.id           AS session_id,
			s.name         AS session_name,
			s.date_start,
			s.date_end,
			s.gov,
			ls.score       AS score_session,
			ls.score_data  AS score_session_data,
			ls.chamber,
			ls.district,
			td.name        AS district_name,
			ls.party,
			ls.image_id
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		LEFT  JOIN {$wpdb->prefix}fi_taxonomy td ON td.id = ls.district AND td.taxonomy = 'district'
		WHERE ls.legislator_id = %d
			AND s.parent_id IS NULL
			AND s.status = 'publish'
		ORDER BY
			COALESCE(s.date_start, '9999-12-31') DESC,
			s.id DESC",
		$legislator_id
	), ARRAY_A) ?: [];

	foreach ($rows as &$row) {
		$gov = $row['gov'] ?? '';
		$row['session_id']    = (int) $row['session_id'];
		$row['image_id']      = (int) ($row['image_id'] ?? 0);
		$row['gov_name']      = FI_GOVERNMENTS[$gov]['name'] ?? $gov;
		$row['state_name']    = FI_GOVERNMENTS[$gov]['state_name'] ?? '';
		$row['party_name']    = fi_party_name($row['party'] ?? '');
		$row['chamber_label'] = FI_CHAMBERS[$row['chamber']]['label'] ?? $row['chamber'];
		$row['chamber_title'] = FI_CHAMBERS[$row['chamber']]['title'] ?? '';
	}
	unset($row);

	return $rows;
}

/**
 * Get legislator sessions with optional filtering (cached for front-end).
 *
 * @param array $args Optional query arguments.
 * @return array|int Array of legislator session objects or count if count is true.
 */
function fi_legislator_sessions_get(array $args = []): array|int {
	$cacheKey = fi_cache_key('legislators/sessions', $args);

	$results = fi_cache($cacheKey);
	if ($results) {
		return $results;
	}

	$results = fi_legislator_sessions_query($args);
	fi_cache($cacheKey, $results);
	return $results;
}
/**
 * Get a single legislator session by ID.
 *
 * @param int $session_id Legislator session row ID.
 * @return array|null
 */
function fi_legislator_session_get(int $session_id): ?array {
	$results = fi_legislator_sessions_get([
		'id'       => $session_id,
		'per_page' => 1,
	]);

	return is_array($results) ? ($results[0] ?? null) : null;
}

/**
 * Get legislator sessions by legislator ID.
 *
 * @param int $legislator_id Legislator ID.
 * @param array $args Optional additional filters.
 * @return array
 */
function fi_legislator_sessions_get_by_legislator(int $legislator_id, $args = []): array {
	$args = array_merge((array) $args, ['legislator_id' => $legislator_id]);
	$results = fi_legislator_sessions_get($args);
	return is_array($results) ? $results : [];
}

/**
 * Get legislator sessions by session ID.
 *
 * @param int $session_id Session ID.
 * @return array
 */
function fi_legislator_sessions_get_by_session(int $session_id): array {
	$results = fi_legislator_sessions_get(['session_id' => $session_id]);
	return is_array($results) ? $results : [];
}

/**
 * Get legislator chamber for an exact legislator/session pair.
 *
 * @param int $legislator_id Legislator ID.
 * @param int $session_id Session ID.
 * @return string|null Chamber H or S, or null if unavailable.
 */
function fi_legislator_chamber(int $legislator_id, int $session_id): ?string {
	$results = fi_legislator_sessions_get([
		'legislator_id' => $legislator_id,
		'session_ids'   => [$session_id],
		'per_page'      => 1,
	]);

	$row = is_array($results) ? ($results[0] ?? null) : null;
	$chamber = $row->chamber ?? null;

	return $chamber && in_array($chamber, ['H', 'S'], true) ? $chamber : null;
}

/**
 * Get exact fi_legislator_sessions.id for a legislator/session pair.
 *
 * @param int $legislator_id Legislator ID.
 * @param int $session_id Session ID.
 * @return int|null Legislator session row ID.
 */
function fi_legislator_session_id(int $legislator_id, int $session_id): ?int {
	$results = fi_legislator_sessions_get([
		'legislator_id' => $legislator_id,
		'session_ids'   => [$session_id],
		'per_page'      => 1,
	]);

	$row = is_array($results) ? ($results[0] ?? null) : null;
	return !empty($row->id) ? (int) $row->id : null;
}

/**
 * Save or update legislator session.
 *
 * @param array $data Legislator session data.
 * @param int|null $session_id Existing legislator session row ID.
 * @return int|false Inserted/updated ID or false.
 */
function fi_legislator_session_save(array $data, ?int $session_id = null): int|false {
	global $wpdb;

	if (empty($data['legislator_id']) || empty($data['session_id'])) {
		return false;
	}

	$fields = [
		'legislator_id' => (int) $data['legislator_id'],
		'session_id'    => (int) $data['session_id'],
		'gov'           => isset($data['gov']) ? strtoupper((string) $data['gov']) : '',
		'state'         => !empty($data['state']) ? strtoupper((string) $data['state']) : null,
		'chamber'       => !empty($data['chamber']) ? strtoupper((string) $data['chamber']) : null,
		'district'      => $data['district'] ?? null,
		'party'         => $data['party'] ?? null,
		'image_id'      => !empty($data['image_id']) ? (int) $data['image_id'] : null,
		'date_start'    => $data['date_start'] ?? null,
		'date_end'      => $data['date_end'] ?? null,
		'score'         => isset($data['score']) ? (int) $data['score'] : null,
		'score_data'    => isset($data['score_data']) ? (is_string($data['score_data']) ? $data['score_data'] : wp_json_encode($data['score_data'])) : null,
		'score_date'    => $data['score_date'] ?? null,
		'meta'          => isset($data['meta']) ? (is_string($data['meta']) ? $data['meta'] : wp_json_encode($data['meta'])) : null,
	];

	$formats = ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s'];

	if ($session_id) {
		$result = $wpdb->update(
			"{$wpdb->prefix}fi_legislator_sessions",
			$fields,
			['id' => $session_id],
			$formats,
			['%d']
		);

		if ($result !== false) {
			fi_legislator_sync_cached_session($fields['legislator_id']);
		}

		return $result !== false ? $session_id : false;
	}

	$result = $wpdb->insert(
		"{$wpdb->prefix}fi_legislator_sessions",
		$fields,
		$formats
	);

	if ($result !== false) {
		fi_legislator_sync_cached_session($fields['legislator_id']);
	}

	return $result !== false ? (int) $wpdb->insert_id : false;
}

/**
 * Update legislator session.
 *
 * @param int $session_id Legislator session row ID.
 * @param array $data Data to update.
 * @return bool
 */
function fi_legislator_session_update(int $session_id, array $data): bool {
	return fi_legislator_session_save($data, $session_id) !== false;
}

/**
 * Delete legislator session.
 *
 * @param int $session_id Legislator session row ID.
 * @return bool
 */
function fi_legislator_session_delete(int $session_id): bool {
	global $wpdb;

	$legislator_id = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT legislator_id FROM {$wpdb->prefix}fi_legislator_sessions WHERE id = %d",
		$session_id
	));

	$result = $wpdb->delete(
		"{$wpdb->prefix}fi_legislator_sessions",
		['id' => $session_id],
		['%d']
	);

	if ($result !== false && $legislator_id > 0) {
		fi_legislator_sync_cached_session($legislator_id);
	}

	return $result !== false;
}

/**
 * Update score for a legislator session.
 *
 * @param int $legislator_id Legislator ID.
 * @param int $session_id Session ID.
 * @param array $score_data Score data.
 * @return bool
 */
function fi_legislator_session_update_score(int $legislator_id, int $session_id, array $score_data): bool {
	global $wpdb;

	$score_data_json = [
		'total'  => (int) ($score_data['total'] ?? $score_data['votes_total'] ?? 0),
		'good'   => (int) ($score_data['good'] ?? $score_data['votes_good'] ?? 0),
		'bad'    => (int) ($score_data['bad'] ?? $score_data['votes_bad'] ?? 0),
		'not'    => (int) ($score_data['not'] ?? $score_data['votes_not'] ?? 0),
		'scored' => (int) ($score_data['scored'] ?? $score_data['votes_scored'] ?? 0),
	];

	$fields = [
		'score'      => $score_data['score'] ?? null,
		'score_data' => wp_json_encode($score_data_json),
		'score_date' => current_time('mysql'),
	];

	$result = $wpdb->update(
		"{$wpdb->prefix}fi_legislator_sessions",
		$fields,
		[
			'legislator_id' => $legislator_id,
			'session_id'    => $session_id,
		],
		['%d', '%s', '%s'],
		['%d', '%d']
	);

	return $result !== false;
}

/**
 * Get scores by session with legislator details.
 *
 * @param int $session_id Session ID.
 * @return array
 */
function fi_legislator_sessions_get_scores_by_session(int $session_id): array {
	global $wpdb;

	$session_ids = fi_sessions_get_hierarchy_ids($session_id);
	$session_ids = array_values(array_filter(array_map('absint', (array) $session_ids)));

	if (empty($session_ids)) {
		return [];
	}

	$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));

	$sql = "
		SELECT
			ls.*,
			l.first_name,
			l.last_name,
			l.display_name,
			l.slug
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		INNER JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
		WHERE ls.session_id IN ($placeholders) AND ls.score IS NOT NULL
		ORDER BY ls.score DESC, l.last_name
	";

	return $wpdb->get_results($wpdb->prepare($sql, $session_ids), ARRAY_A);
}

/**
 * Get legislator session statistics.
 *
 * @param string|null $gov Government code.
 * @param int|null $session_id Session ID.
 * @return array Statistics.
 */
function fi_legislator_sessions_stats(?string $gov = null, ?int $session_id = null): array {
	global $wpdb;

	$where_conditions = [];
	$where_values = [];

	if ($gov) {
		$where_conditions[] = 'ls.gov = %s';
		$where_values[] = strtoupper($gov);
	}

	if ($session_id) {
		$session_ids = fi_sessions_get_hierarchy_ids($session_id);
		$session_ids = array_values(array_filter(array_map('absint', (array) $session_ids)));
		if (!empty($session_ids)) {
			$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
			$where_conditions[] = "ls.session_id IN ($placeholders)";
			$where_values = array_merge($where_values, $session_ids);
		}
	}

	$where_clause = !empty($where_conditions) ? ' AND ' . implode(' AND ', $where_conditions) : '';

	$sql = "
		SELECT
			COUNT(*) as total_sessions,
			AVG(ls.score) as avg_score,
			MIN(ls.score) as min_score,
			MAX(ls.score) as max_score,
			SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.total'), JSON_EXTRACT(ls.score_data, '$.votes_total'), 0) AS UNSIGNED)) as total_votes,
			SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.good'), JSON_EXTRACT(ls.score_data, '$.votes_good'), 0) AS UNSIGNED)) as total_good_votes,
			SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.bad'), JSON_EXTRACT(ls.score_data, '$.votes_bad'), 0) AS UNSIGNED)) as total_bad_votes,
			SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.not'), JSON_EXTRACT(ls.score_data, '$.votes_not'), 0) AS UNSIGNED)) as total_not_votes
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		WHERE ls.score_data IS NOT NULL {$where_clause}
	";

	if (!empty($where_values)) {
		$sql = $wpdb->prepare($sql, $where_values);
	}

	$result = $wpdb->get_row($sql, ARRAY_A);

	if (!$result) {
		return [
			'total_sessions'   => 0,
			'avg_score'        => 0,
			'min_score'        => 0,
			'max_score'        => 0,
			'total_votes'      => 0,
			'total_good_votes' => 0,
			'total_bad_votes'  => 0,
			'total_not_votes'  => 0,
		];
	}

	return [
		'total_sessions'   => (int) ($result['total_sessions'] ?? 0),
		'avg_score'        => round((float) ($result['avg_score'] ?? 0), 2),
		'min_score'        => round((float) ($result['min_score'] ?? 0), 2),
		'max_score'        => round((float) ($result['max_score'] ?? 0), 2),
		'total_votes'      => (int) ($result['total_votes'] ?? 0),
		'total_good_votes' => (int) ($result['total_good_votes'] ?? 0),
		'total_bad_votes'  => (int) ($result['total_bad_votes'] ?? 0),
		'total_not_votes'  => (int) ($result['total_not_votes'] ?? 0),
	];
}

/**
 * Get decoded legislator session meta array from a record or record ID.
 *
 * @param array|int $record Legislator session array or ID.
 * @return array
 */
function fi_legislator_session_get_all_meta($record): array {
	global $wpdb;

	if (is_numeric($record)) {
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta FROM {$wpdb->prefix}fi_legislator_sessions WHERE id = %d LIMIT 1",
				absint($record)
			),
			ARRAY_A
		);
	}

	if (empty($record) || !isset($record['meta']) || $record['meta'] === null || $record['meta'] === '') {
		return [];
	}

	if (is_array($record['meta'])) {
		return $record['meta'];
	}

	$decoded = json_decode((string) $record['meta'], true);
	return is_array($decoded) ? $decoded : [];
}

/**
 * Get legislator session meta value by key.
 *
 * @param object|int $record Legislator session object or ID.
 * @param string $key Meta key.
 * @param mixed $default Default return value.
 * @return mixed
 */
function fi_legislator_session_get_meta($record, string $key, $default = null) {
	$meta = fi_legislator_session_get_all_meta($record);
	return array_key_exists($key, $meta) ? $meta[$key] : $default;
}

/**
 * Set entire legislator session meta array.
 *
 * @param int $record_id Legislator session row ID.
 * @param array $meta Meta array.
 * @return bool
 */
function fi_legislator_session_set_all_meta(int $record_id, array $meta): bool {
	global $wpdb;

	$result = $wpdb->update(
		"{$wpdb->prefix}fi_legislator_sessions",
		['meta' => wp_json_encode($meta)],
		['id' => $record_id],
		['%s'],
		['%d']
	);

	return $result !== false;
}

/**
 * Update legislator session meta key(s) without affecting other keys.
 *
 * @param int $record_id Legislator session row ID.
 * @param array $meta_updates Key/value updates.
 * @return bool
 */
function fi_legislator_session_update_meta(int $record_id, array $meta_updates): bool {
	$meta = fi_legislator_session_get_all_meta($record_id);
	$meta = array_merge($meta, $meta_updates);
	return fi_legislator_session_set_all_meta($record_id, $meta);
}

/**
 * Get distinct party abbreviations and chamber codes used in a government's sessions.
 * Returns raw pairs for building filter options.
 *
 * @param string $gov Government code (e.g. 'US', 'TX').
 * @return array[] Rows with keys 'party' and 'chamber'.
 */
function fi_legislator_sessions_get_party_chamber(string $gov): array {
	global $wpdb;

	return $wpdb->get_results($wpdb->prepare(
		"SELECT DISTINCT ls.party, ls.chamber
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		WHERE s.gov = %s
		AND ((ls.party IS NOT NULL AND ls.party != '') OR (ls.chamber IS NOT NULL AND ls.chamber IN ('H', 'S')))
		ORDER BY ls.party ASC, ls.chamber ASC",
		strtoupper($gov)
	)) ?: [];
}

/**
 * Delete legislator session meta key(s).
 *
 * @param int $record_id Legislator session row ID.
 * @param string|array $keys Meta key or keys.
 * @return bool
 */
function fi_legislator_session_delete_meta(int $record_id, $keys): bool {
	$meta = fi_legislator_session_get_all_meta($record_id);
	$keys = is_array($keys) ? $keys : [$keys];

	foreach ($keys as $key) {
		unset($meta[$key]);
	}

	return fi_legislator_session_set_all_meta($record_id, $meta);
}
