<?php
/**
 * Legislators - All Data Functions
 *
 * Single record (fi_legislator_get) and multi-record queries (fi_legislators_get)
 * for the fi_legislators + fi_legislator_sessions tables.
 *
 * @package FreedomIndex
 */

if (!defined('ABSPATH')) exit;

const LEGISLATORS_DEFAULT_LIMIT = 24;
const LEGISLATORS_MAX_LIMIT     = 600;

// =============================================================================
// SINGLE RECORD
// =============================================================================

/**
 * Get a legislator by ID.
 *
 * Always includes full session history (parent sessions only, date_end DESC).
 * Top-level keys are flattened from the most recent session for fast access.
 * Pass $with_sessions = false to skip the session query when only base data is needed.
 *
 * @param int  $id            Legislator ID.
 * @param bool $with_sessions Include session history. Default true.
 * @return array|null
 */
function fi_legislator_get(int $id, bool $with_sessions = true): ?array {
	global $wpdb;

	$row = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}fi_legislators WHERE id = %d",
		$id
	), ARRAY_A);

	if (!$row) {
		return null;
	}

	$legislator = _fi_legislator_format_base($row);

	// Populate current-session overlay from the cached fields on fi_legislators.
	// These are written by fi_legislator_sync_cached_session() and are always
	// authoritative for "latest session" data without a second JOIN.
	$gov = $row['gov'] ?? '';
	if ($gov) {
		$legislator['session_id']    = (int) ($row['session_id'] ?? 0);
		$legislator['gov']           = $gov;
		$legislator['state']         = $row['state'] ?? '';
		$legislator['chamber']       = $row['chamber'] ?? '';
		$legislator['district']      = $row['district'] ?? '';
		$legislator['party']         = $row['party'] ?? '';
		$legislator['gov_name']      = FI_GOVERNMENTS[$gov]['name'] ?? $gov;
		$legislator['state_name']    = FI_GOVERNMENTS[$gov]['state_name'] ?? '';
		$legislator['party_name']    = FI_PARTIES[$row['party'] ?? ''] ?? ($row['party'] ?? '');
		$legislator['chamber_label'] = FI_CHAMBERS[$row['chamber'] ?? '']['label'] ?? ($row['chamber'] ?? '');
		$legislator['chamber_title'] = FI_CHAMBERS[$row['chamber'] ?? '']['title'] ?? '';
	}

	if (!$with_sessions) {
		if (!empty($legislator['session_id'])) {
			$legislator['session_name'] = (string) $wpdb->get_var($wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}fi_sessions WHERE id = %d",
				$legislator['session_id']
			));
		}
		return $legislator;
	}

	// Full session history — second query, only when explicitly needed.
	$sessions = _fi_legislator_query_sessions($id);
	$legislator['sessions'] = $sessions;

	// Supplement with per-session score/image/date fields from the most recent row
	// since those are NOT stored on fi_legislators.
	if (!empty($sessions)) {
		$current = $sessions[0];
		$legislator['session_name']       = $current['session_name'];
		$legislator['session_score']      = $current['session_score'];
		$legislator['session_score_data'] = $current['session_score_data'];
		$legislator['image_id']           = $current['image_id'] ?: ($legislator['image_id'] ?? null);
		$legislator['date_start']         = $current['date_start'];
		$legislator['date_end']           = $current['date_end'];
		$legislator['lifetime_score']     = $current['lifetime_score'];
	}

	return $legislator;
}

/**
 * Get a legislator by one or more external reference IDs.
 *
 * Tries each supplied reference field as an OR condition and returns the
 * record matched against the most-recent session.
 *
 * @param array $references Keys: bioguide_id, lis_id, legiscan_id, votesmart_id, ballotpedia_id, openstates_id
 * @return array|null
 */
function fi_legislator_get_by_external_id(array $references): ?array {
	global $wpdb;

	if (empty($references)) {
		return null;
	}

	$where  = [];
	$params = [];

	foreach (['bioguide_id', 'lis_id', 'legiscan_id', 'votesmart_id', 'ballotpedia_id', 'openstates_id'] as $field) {
		if (!empty($references[$field])) {
			$where[]  = "l.{$field} = %s";
			$params[] = $references[$field];
		}
	}

	if (empty($where)) {
		return null;
	}

	// Cached session fields on fi_legislators make the JOIN unnecessary.
	$sql = "SELECT * FROM {$wpdb->prefix}fi_legislators WHERE " . implode(' OR ', $where) . " LIMIT 1";

	$row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

	if (!$row) {
		return null;
	}

	return fi_legislator_get((int) $row['id'], false);
}

// =============================================================================
// MULTI-RECORD QUERIES
// =============================================================================

/**
 * Get a paginated, filtered list of legislators.
 *
 * @param array $args {
 *     @type string $gov        Required. Government code (US, TX, etc.)
 *     @type int    $session_id Optional. Parent session ID (child sessions auto-included).
 *     @type string $chamber    Optional. 'S' or 'H'
 *     @type string $party      Optional. Party abbreviation.
 *     @type string $state      Optional. 2-letter state code (US only).
 *     @type string $search     Optional. Name search term.
 *     @type string $sort       Optional. na|nd|sa|sd|pa|pd|oa|od (default: na)
 *     @type int    $limit      Optional. Items per page (default 24, max 600).
 *     @type int    $offset     Optional. Pagination offset.
 * }
 * @return array
 */
function fi_legislators_query(array $args): array {
	global $wpdb;

	$args = wp_parse_args($args, [
		'gov'        => '',
		'session_id' => 0,
		'chamber'    => '',
		'party'      => '',
		'state'      => '',
		'search'     => '',
		'sort'       => 'na',
		'limit'      => LEGISLATORS_DEFAULT_LIMIT,
		'offset'     => 0,
	]);

	$gov = strtoupper(sanitize_text_field($args['gov']));
	if (empty($gov)) {
		return [];
	}

	[$where, $params] = _fi_legislators_build_where($args, $gov);

	$order_by = _fi_legislators_build_order_by($args['sort']);
	$raw_limit = (int) $args['limit'];
	$limit     = ($raw_limit <= 0) ? LEGISLATORS_MAX_LIMIT : min($raw_limit, LEGISLATORS_MAX_LIMIT);
	$offset    = max(0, (int) $args['offset']);
	$where_sql = implode(' AND ', $where);

	$sql = "
		SELECT
			ls.legislator_id AS id,
			ls.gov,
			l.display_name,
			l.first_name,
			l.last_name,
			l.image_id,
			l.image_url,
			l.legacy_image_url,
			l.date_updated,
			ls.chamber,
			ls.party,
			ls.state,
			ls.district,
			ls.score,
			ls.grade,
			ls.session_id,
			s.name AS session_name,
			s.parent_id AS session_parent_id,
			t.name AS district_name
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
		JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		LEFT JOIN {$wpdb->prefix}fi_taxonomy t
			ON ls.district = t.slug
			AND t.gov = ls.gov
			AND t.taxonomy = 'district'
		WHERE {$where_sql}
		ORDER BY {$order_by}
		LIMIT {$limit} OFFSET {$offset}
	";

	$results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

	if (!is_array($results)) {
		return [];
	}

	return array_map('fi_legislators_format_row', $results);
}

/**
 * Front-end wrapper: checks fi_cache first, falls back to fi_legislators_query().
 *
 * @param array $args Same as fi_legislators_query().
 * @return array
 */
function fi_legislators_get(array $args): array {
	$cache_key = _fi_legislators_build_cache_key($args);

	$cached = fi_cache($cache_key);
	if ($cached !== null && $cached !== '' && $cached !== false) {
		if (is_string($cached)) {
			$cached = maybe_unserialize($cached);
		}
		if (is_array($cached)) {
			return $cached;
		}
	}

	$results = fi_legislators_query($args);
	fi_cache($cache_key, $results, HOUR_IN_SECONDS);
	return $results;
}

/**
 * Get legislators for a specific session (cached for front-end).
 *
 * @param int   $session_id Session ID.
 * @param array $filters    Additional filters (chamber, sort, etc.).
 * @return array
 */
function fi_legislators_get_by_session(int $session_id, array $filters = []): array {
	$args = array_merge($filters, ['session_id' => $session_id]);
	if (empty($args['gov'])) {
		global $wpdb;
		$gov = $wpdb->get_var($wpdb->prepare(
			"SELECT gov FROM {$wpdb->prefix}fi_sessions WHERE id = %d LIMIT 1",
			$session_id
		));
		if ($gov) {
			$args['gov'] = strtoupper($gov);
		}
	}
	if (empty($args['limit'])) {
		$args['limit'] = LEGISLATORS_MAX_LIMIT;
	}

	$cache_key = _fi_legislators_build_cache_key($args);

	$cached = fi_cache($cache_key);
	if ($cached !== null && $cached !== '' && $cached !== false) {
		if (is_string($cached)) {
			$cached = maybe_unserialize($cached);
		}
		if (is_array($cached)) {
			return $cached;
		}
	}

	$results = fi_legislators_query($args);
	fi_cache($cache_key, $results, HOUR_IN_SECONDS);
	return $results;
}

/**
 * Get total count of legislators matching the given args (for pagination).
 *
 * @param array $args Same filter keys as fi_legislators_get(), minus sort/limit/offset.
 * @return int
 */
function fi_legislators_count_query(array $args): int {
	global $wpdb;

	$args = wp_parse_args($args, [
		'gov'        => '',
		'session_id' => 0,
		'chamber'    => '',
		'party'      => '',
		'state'      => '',
		'search'     => '',
	]);

	$gov = strtoupper(sanitize_text_field($args['gov']));
	if (empty($gov)) {
		return 0;
	}

	[$where, $params] = _fi_legislators_build_where($args, $gov);
	$where_sql = implode(' AND ', $where);

	$sql = "
		SELECT COUNT(DISTINCT ls.legislator_id)
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
		WHERE {$where_sql}
	";

	return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
}

/**
 * Front-end wrapper: checks fi_cache first, falls back to fi_legislators_count_query().
 *
 * @param array $args Same as fi_legislators_count_query().
 * @return int
 */
function fi_legislators_count(array $args): int {
	$cache_key = _fi_legislators_build_cache_key($args, 'count');

	$cached = fi_cache($cache_key);
	if ($cached !== null && $cached !== '' && $cached !== false) {
		if (is_string($cached)) {
			$cached = maybe_unserialize($cached);
		}
		if (is_int($cached) || is_numeric($cached)) {
			return (int) $cached;
		}
	}

	$count = fi_legislators_count_query($args);
	fi_cache($cache_key, $count, HOUR_IN_SECONDS);
	return $count;
}

// =============================================================================
// ALIASES & CROSS-REFERENCE HELPERS
// =============================================================================

/**
 * Alias for fi_legislator_get() with sessions always included.
 * Exists so templates/rewrite.php that call this name continue to work.
 *
 * @param int $legislator_id
 * @return array|null
 */
function fi_legislator_get_with_sessions(int $legislator_id): ?array {
	return fi_legislator_get($legislator_id, true);
}

/**
 * Get multiple legislators by an array of IDs, each with full session data.
 *
 * @param int[] $ids                 Legislator IDs.
 * @param bool  $include_session_data Kept for call-site compatibility; always true.
 * @return array
 */
function fi_legislators_get_by_ids(array $ids, bool $include_session_data = true): array {
	$ids = array_values(array_filter(array_map('intval', $ids)));
	if (empty($ids)) {
		return [];
	}
	$results = [];
	foreach ($ids as $id) {
		$legislator = fi_legislator_get($id, $include_session_data);
		if ($legislator) {
			$results[] = $legislator;
		}
	}
	return $results;
}

/**
 * Build a bioguide_id → legislator_id lookup map for a session.
 * Used by the US House rollcall XML importer.
 *
 * @param int $session_id
 * @return array  ['A000001' => 42, ...]
 */
function fi_legislators_get_bioguide_xref(int $session_id): array {
	global $wpdb;
	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT l.bioguide_id, ls.legislator_id
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
		WHERE ls.session_id = %d",
		$session_id
	), ARRAY_A);
	$xref = [];
	foreach ($rows as $row) {
		if (!empty($row['bioguide_id'])) {
			$xref[$row['bioguide_id']] = (int) $row['legislator_id'];
		}
	}
	return $xref;
}

/**
 * Build a lis_id → legislator_id lookup map for a session.
 * Used by the US Senate rollcall importer.
 *
 * @param int $session_id
 * @return array  ['S001' => 42, ...]
 */
function fi_legislators_get_lis_xref(int $session_id): array {
	global $wpdb;
	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT l.lis_id, ls.legislator_id
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
		WHERE ls.session_id = %d",
		$session_id
	), ARRAY_A);
	$xref = [];
	foreach ($rows as $row) {
		if (!empty($row['lis_id'])) {
			$xref[$row['lis_id']] = (int) $row['legislator_id'];
		}
	}
	return $xref;
}

// =============================================================================
// CACHED SESSION SYNC
// =============================================================================

/**
 * Resolve and write the latest-session cache fields on a single fi_legislators row.
 *
 * "Latest" = the parent fi_sessions row (parent_id IS NULL) linked via fi_legislator_sessions
 * that has the highest date_start, with fi_sessions.id DESC as the tiebreaker (insert order
 * reliably reflects creation order when dates are absent).
 *
 * fi_legislator_sessions.date_start is deliberately NOT used for ranking — it marks
 * mid-session role changes, not session chronology.
 *
 * Fields written to fi_legislators:
 *   session_id, gov, state, chamber, district, party
 *
 * All six are set to NULL when the legislator has no session assignments.
 *
 * @param int $legislator_id
 * @return bool True on successful write (including a no-op UPDATE), false on DB error.
 */
function fi_legislator_sync_cached_session(int $legislator_id): bool {
	global $wpdb;

	$row = $wpdb->get_row($wpdb->prepare(
		"SELECT
			ls.session_id,
			ls.gov,
			ls.state,
			ls.chamber,
			ls.district,
			ls.party
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		INNER JOIN {$wpdb->prefix}fi_sessions s
			ON s.id = ls.session_id
			AND s.parent_id IS NULL
		WHERE ls.legislator_id = %d
		ORDER BY
			COALESCE(s.date_start, '9999-12-31') DESC,
			s.id DESC
		LIMIT 1",
		$legislator_id
	), ARRAY_A);

	$cache = $row
		? [
			'session_id' => (int) $row['session_id'],
			'gov'        => $row['gov'],
			'state'      => $row['state'],
			'chamber'    => $row['chamber'],
			'district'   => $row['district'],
			'party'      => $row['party'],
		]
		: [
			'session_id' => null,
			'gov'        => null,
			'state'      => null,
			'chamber'    => null,
			'district'   => null,
			'party'      => null,
		];

	$result = $wpdb->update(
		$wpdb->prefix . 'fi_legislators',
		$cache,
		['id' => $legislator_id],
		['%d', '%s', '%s', '%s', '%s', '%s'],
		['%d']
	);

	return $result !== false;
}

/**
 * Bulk-sync the cached session fields for every legislator.
 *
 * Runs a single JOIN query to fetch all latest-session rows, then issues one
 * UPDATE per legislator that needs a change. Suitable for a one-time backfill
 * or a periodic admin action. Not for per-request use.
 *
 * @param string|null $gov  Limit to legislators whose latest session is in this gov (e.g. 'US', 'WI').
 *                          NULL = process all legislators.
 * @return int Number of legislators updated.
 */
function fi_legislators_sync_cached_sessions(?string $gov = null): int {
	global $wpdb;

	// One query: for every legislator, find their latest parent-session row.
	// Uses a subquery to rank sessions per legislator then takes rank = 1.
	$gov_join  = $gov ? $wpdb->prepare(' AND ls.gov = %s', strtoupper($gov)) : '';

	$rows = $wpdb->get_results(
		"SELECT
			ls.legislator_id,
			ls.session_id,
			ls.gov,
			ls.state,
			ls.chamber,
			ls.district,
			ls.party
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		INNER JOIN {$wpdb->prefix}fi_sessions s
			ON s.id = ls.session_id
			AND s.parent_id IS NULL
		INNER JOIN (
			SELECT
				ls2.legislator_id,
				MAX(
					CONCAT(
						LPAD(UNIX_TIMESTAMP(COALESCE(s2.date_start, '9999-12-31')), 12, '0'),
						LPAD(s2.id, 12, '0')
					)
				) AS best_key
			FROM {$wpdb->prefix}fi_legislator_sessions ls2
			INNER JOIN {$wpdb->prefix}fi_sessions s2
				ON s2.id = ls2.session_id
				AND s2.parent_id IS NULL
			GROUP BY ls2.legislator_id
		) ranked
			ON ranked.legislator_id = ls.legislator_id
			AND CONCAT(
				LPAD(UNIX_TIMESTAMP(COALESCE(s.date_start, '9999-12-31')), 12, '0'),
				LPAD(s.id, 12, '0')
			) = ranked.best_key
		{$gov_join}",
		ARRAY_A
	);

	if (empty($rows)) {
		return 0;
	}

	$updated = 0;

	foreach ($rows as $row) {
		$result = $wpdb->update(
			$wpdb->prefix . 'fi_legislators',
			[
				'session_id' => (int) $row['session_id'],
				'gov'        => $row['gov'],
				'state'      => $row['state'],
				'chamber'    => $row['chamber'],
				'district'   => $row['district'],
				'party'      => $row['party'],
			],
			['id' => (int) $row['legislator_id']],
			['%d', '%s', '%s', '%s', '%s', '%s'],
			['%d']
		);

		if ($result !== false) {
			$updated++;
		}
	}

	// Legislators with NO session assignments at all — NULL out their cached fields.
	$wpdb->query(
		"UPDATE {$wpdb->prefix}fi_legislators l
		LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON ls.legislator_id = l.id
		SET l.session_id = NULL, l.gov = NULL, l.state = NULL,
		    l.chamber = NULL, l.district = NULL, l.party = NULL
		WHERE ls.id IS NULL
		  AND (l.session_id IS NOT NULL OR l.gov IS NOT NULL)"
	);

	fi_cache_clear('legislators');

	return $updated;
}

/**
 * Self-healing sync: only fills in rows where session_id IS NULL.
 *
 * Safe to call on every admin list page load — skips legislators that already
 * have cached data so it touches only rows that genuinely need it.
 * Caps at $limit rows per call to stay well under the Cloudflare 100s timeout.
 *
 * @param int  $limit       Max legislators to fix per call. Default 50.
 * @param bool $return_rows When true, return the written rows (with display_name) instead of a plain count.
 * @return int|array Count of updated legislators, or array of row data when $return_rows is true.
 */
function fi_legislators_sync_missing_cached_sessions(int $limit = 50, bool $return_rows = false): int|array {
	global $wpdb;

	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT
			ls.legislator_id,
			ls.session_id,
			ls.gov,
			ls.state,
			ls.chamber,
			ls.district,
			ls.party,
			l.display_name
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		INNER JOIN {$wpdb->prefix}fi_sessions s
			ON s.id = ls.session_id
			AND s.parent_id IS NULL
		INNER JOIN {$wpdb->prefix}fi_legislators l
			ON l.id = ls.legislator_id
			AND l.session_id IS NULL
		INNER JOIN (
			SELECT
				ls2.legislator_id,
				MAX(
					CONCAT(
						LPAD(UNIX_TIMESTAMP(COALESCE(s2.date_start, '9999-12-31')), 12, '0'),
						LPAD(s2.id, 12, '0')
					)
				) AS best_key
			FROM {$wpdb->prefix}fi_legislator_sessions ls2
			INNER JOIN {$wpdb->prefix}fi_sessions s2
				ON s2.id = ls2.session_id
				AND s2.parent_id IS NULL
			GROUP BY ls2.legislator_id
		) ranked
			ON ranked.legislator_id = ls.legislator_id
			AND CONCAT(
				LPAD(UNIX_TIMESTAMP(COALESCE(s.date_start, '9999-12-31')), 12, '0'),
				LPAD(s.id, 12, '0')
			) = ranked.best_key
		ORDER BY ls.legislator_id ASC
		LIMIT %d",
		$limit
	), ARRAY_A);

	$updated = 0;
	$written = [];

	if (!empty($rows)) {
		// Write cacheable rows and collect the ID range they cover.
		$min_id = PHP_INT_MAX;
		$max_id = 0;
		foreach ($rows as $row) {
			$lid = (int) $row['legislator_id'];
			if ($lid < $min_id) $min_id = $lid;
			if ($lid > $max_id) $max_id = $lid;

			$result = $wpdb->update(
				$wpdb->prefix . 'fi_legislators',
				[
					'session_id' => (int) $row['session_id'],
					'gov'        => $row['gov'],
					'state'      => $row['state'],
					'chamber'    => $row['chamber'],
					'district'   => $row['district'],
					'party'      => $row['party'],
				],
				['id' => $lid],
				['%d', '%s', '%s', '%s', '%s', '%s'],
				['%d']
			);
			if ($result !== false) {
				$updated++;
				if ($return_rows) {
					$written[] = [
						'id'           => $lid,
						'display_name' => $row['display_name'],
						'cached'       => implode(' | ', array_filter([
							$row['gov'],
							$row['state'],
							$row['chamber'],
							$row['district'],
							$row['party'],
							'session:' . $row['session_id'],
						])),
						'skipped'      => false,
					];
				}
			}
		}

		// Also handle no-session legislators up to and including the current batch ceiling.
		if ($return_rows && $max_id > 0) {
			$skipped_rows = $wpdb->get_results($wpdb->prepare(
				"SELECT l.id, l.display_name
				FROM {$wpdb->prefix}fi_legislators l
				WHERE l.session_id IS NULL
				AND l.id <= %d
				AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->prefix}fi_legislator_sessions ls
					WHERE ls.legislator_id = l.id
				)
				ORDER BY l.id ASC",
				$max_id
			), ARRAY_A);

			foreach ($skipped_rows as $sr) {
				$wpdb->update(
					$wpdb->prefix . 'fi_legislators',
					['session_id' => 0],
					['id' => (int) $sr['id']],
					['%d'], ['%d']
				);
				$written[] = [
					'id'           => (int) $sr['id'],
					'display_name' => $sr['display_name'],
					'cached'       => '',
					'skipped'      => true,
					'reason'       => 'no session assignments',
				];
			}
		}
	} else {
		// No cacheable rows at all — stamp any remaining no-session legislators.
		if (!$return_rows) return 0;

		$skipped_rows = $wpdb->get_results($wpdb->prepare(
			"SELECT l.id, l.display_name
			FROM {$wpdb->prefix}fi_legislators l
			WHERE l.session_id IS NULL
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->prefix}fi_legislator_sessions ls
				WHERE ls.legislator_id = l.id
			)
			ORDER BY l.id ASC
			LIMIT %d",
			$limit
		), ARRAY_A);

		foreach ($skipped_rows as $sr) {
			$wpdb->update(
				$wpdb->prefix . 'fi_legislators',
				['session_id' => 0],
				['id' => (int) $sr['id']],
				['%d'], ['%d']
			);
			$written[] = [
				'id'           => (int) $sr['id'],
				'display_name' => $sr['display_name'],
				'cached'       => '',
				'skipped'      => true,
				'reason'       => 'no session assignments',
			];
		}
	}

	return $return_rows ? $written : $updated;
}

/**
 * Purge all cached session fields from fi_legislators.
 * Used by the full rebuild tool before re-running fi_legislators_sync_cached_sessions().
 *
 * @param string|null $gov Limit purge to one gov's legislators. NULL = all.
 * @return int Rows affected.
 */
function fi_legislators_purge_cached_sessions(?string $gov = null): int {
	global $wpdb;

	if ($gov) {
		return (int) $wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->prefix}fi_legislators
			SET session_id = NULL, gov = NULL, state = NULL,
			    chamber = NULL, district = NULL, party = NULL
			WHERE gov = %s",
			strtoupper($gov)
		));
	}

	return (int) $wpdb->query(
		"UPDATE {$wpdb->prefix}fi_legislators
		SET session_id = NULL, gov = NULL, state = NULL,
		    chamber = NULL, district = NULL, party = NULL"
	);
}

/**
 * Get count of legislators with NULL session cache (for the rebuild UI progress display).
 * Never filters by gov — after a purge all cached gov fields are NULL.
 *
 * @return int
 */
function fi_legislators_count_uncached(): int {
	global $wpdb;

	return (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->prefix}fi_legislators WHERE session_id IS NULL"
	);
}

// =============================================================================
// SAVE / UPDATE / DELETE
// =============================================================================

/**
 * Save or update a legislator record.
 *
 * INSERT when $legislator_id is null; UPDATE when provided.
 * Only keys present in $data are written — nulls are stripped unless
 * the field is in the explicit-clear list (image_id, legacy_image_url).
 *
 * The `meta` key is handled specially on UPDATE: its value is deep-merged
 * into the existing JSON column rather than overwriting it, so callers can
 * patch individual meta keys without losing others.
 *
 * @param array    $data          Field values to save.
 * @param int|null $legislator_id Existing record ID for updates, null for inserts.
 * @return int|false New or existing legislator ID on success, false on failure.
 */
function fi_legislator_save(array $data, ?int $legislator_id = null): int|false {
	global $wpdb;

	// INSERT: require name fields
	if (!$legislator_id && (empty($data['first_name']) || empty($data['last_name']))) {
		return false;
	}

	// INSERT: duplicate check on external IDs / legacy_id
	if (!$legislator_id) {
		$dupe = fi_legislator_check_duplicates($data);
		if ($dupe['is_duplicate']) {
			return $dupe['existing_id'];
		}
	}

	// Normalise meta to array before any processing
	if (isset($data['meta'])) {
		if (is_string($data['meta'])) {
			$decoded = json_decode($data['meta'], true);
			$data['meta'] = is_array($decoded) ? $decoded : [];
		} elseif (!is_array($data['meta'])) {
			$data['meta'] = [];
		}
		if (empty($data['meta'])) {
			unset($data['meta']);
		}
	}

	// On UPDATE, pull meta out for a separate merge so we don't overwrite it
	$meta_patch = null;
	if ($legislator_id && isset($data['meta'])) {
		$meta_patch = $data['meta'];
		unset($data['meta']);
	}

	// Map allowed columns → format
	$format_map = [
		'legacy_id'       => '%s',
		'first_name'      => '%s',
		'middle_name'     => '%s',
		'last_name'       => '%s',
		'display_name'    => '%s',
		'sort_name'       => '%s',
		'slug'            => '%s',
		'email'           => '%s',
		'phone'           => '%s',
		'website'         => '%s',
		'address'         => '%s',
		'twitter'         => '%s',
		'facebook'        => '%s',
		'bioguide_id'     => '%s',
		'lis_id'          => '%s',
		'legiscan_id'     => '%d',
		'govtrack_id'     => '%s',
		'votesmart_id'    => '%s',
		'ballotpedia_id'  => '%s',
		'openstates_id'   => '%s',
		'legacy_image_url'=> '%s',
		'image_id'        => '%d',
		'image_url'       => '%s',
		'score'           => '%d',
		'score_data'      => '%s',
		'score_date'      => '%s',
		'audit_log'       => '%s',
		'meta'            => '%s',
	];

	// Build db_data from whitelisted keys only
	$db_data  = [];
	$formats  = [];
	// Fields that may be explicitly set to NULL/empty to clear them
	$clearable = ['image_id', 'legacy_image_url'];

	foreach ($format_map as $col => $fmt) {
		if (!array_key_exists($col, $data)) {
			continue;
		}
		$val = $data[$col];

		// Handle explicit clears
		if (in_array($col, $clearable, true) && ($val === null || $val === '' || $val === 0)) {
			$db_data[$col] = null;
			$formats[]     = $fmt;
			continue;
		}

		if ($val === null) {
			continue; // skip unset fields
		}

		// JSON-encode array fields
		if (in_array($col, ['score_data', 'audit_log', 'meta'], true) && is_array($val)) {
			$val = wp_json_encode($val);
		}

		// Auto-generate display_name on INSERT if not supplied
		if ($col === 'display_name' && empty($val) && !$legislator_id) {
			$val = trim(($data['first_name'] ?? '') . ' ' . ($data['middle_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
		}

		$db_data[$col] = $val;
		$formats[]     = $fmt;
	}

	// Auto display_name on INSERT when not in $data at all
	if (!$legislator_id && !isset($db_data['display_name'])) {
		$dn = trim(($data['first_name'] ?? '') . ' ' . ($data['middle_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
		if ($dn !== '') {
			$db_data['display_name'] = $dn;
			$formats[]               = '%s';
		}
	}

	// UPDATE
	if ($legislator_id) {
		// Meta-only patch with no other columns
		if (empty($db_data) && $meta_patch !== null) {
			$ok = _fi_legislator_merge_meta($legislator_id, $meta_patch);
			if ($ok) {
				fi_cache_clear('legislators');
			}
			return $ok ? $legislator_id : false;
		}

		if (empty($db_data) && $meta_patch === null) {
			return $legislator_id; // nothing to do
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'fi_legislators',
			$db_data,
			['id' => $legislator_id],
			$formats,
			['%d']
		);

		if ($result === false) {
			return false;
		}

		if ($meta_patch !== null) {
			_fi_legislator_merge_meta($legislator_id, $meta_patch);
		}

		fi_cache_clear('legislators');
		return $legislator_id;
	}

	// INSERT
	$result = $wpdb->insert($wpdb->prefix . 'fi_legislators', $db_data, $formats);

	if (!$result) {
		return false;
	}

	$new_id = (int) $wpdb->insert_id;
	fi_cache_clear('legislators');
	return $new_id;
}

/**
 * Thin wrapper: update an existing legislator.
 *
 * @param int   $legislator_id
 * @param array $data
 * @return bool
 */
function fi_legislator_update(int $legislator_id, array $data): bool {
	return fi_legislator_save($data, $legislator_id) !== false;
}

/**
 * Delete a legislator and their session records.
 *
 * @param int $legislator_id
 * @return bool
 */
function fi_legislator_delete(int $legislator_id): bool {
	global $wpdb;

	$exists = $wpdb->get_var($wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}fi_legislators WHERE id = %d",
		$legislator_id
	));

	if (!$exists) {
		return false;
	}

	$wpdb->delete($wpdb->prefix . 'fi_legislator_sessions', ['legislator_id' => $legislator_id], ['%d']);
	$wpdb->delete($wpdb->prefix . 'fi_voterc',              ['legislator_id' => $legislator_id], ['%d']);

	$result = $wpdb->delete($wpdb->prefix . 'fi_legislators', ['id' => $legislator_id], ['%d']);

	if ($result !== false) {
		fi_cache_clear('legislators');
		return true;
	}

	return false;
}

/**
 * Check for duplicate legislators by external ID or legacy_id.
 * No name matching — too many false positives with common names.
 *
 * @param array    $data       Field data for the new record.
 * @param int|null $exclude_id Exclude this ID (useful for re-import checks).
 * @return array{is_duplicate: bool, existing_id: int|null}
 */
function fi_legislator_check_duplicates(array $data, ?int $exclude_id = null): array {
	global $wpdb;

	$conditions = [];
	$values     = [];

	if (!empty($data['legacy_id'])) {
		$conditions[] = 'legacy_id = %s';
		$values[]     = (string) $data['legacy_id'];
	}

	foreach (['bioguide_id', 'lis_id', 'legiscan_id', 'votesmart_id', 'ballotpedia_id', 'openstates_id'] as $field) {
		if (!empty($data[$field])) {
			$conditions[] = "{$field} = %s";
			$values[]     = $data[$field];
		}
	}

	if (empty($conditions)) {
		return ['is_duplicate' => false, 'existing_id' => null];
	}

	$where = implode(' OR ', $conditions);
	if ($exclude_id) {
		$where   .= ' AND id != %d';
		$values[] = $exclude_id;
	}

	$existing_id = $wpdb->get_var($wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}fi_legislators WHERE {$where} LIMIT 1",
		$values
	));

	return [
		'is_duplicate' => !empty($existing_id),
		'existing_id'  => $existing_id ? (int) $existing_id : null,
	];
}

/**
 * Deep-merge $meta_updates into the existing JSON meta column.
 * Tries JSON_MERGE_PATCH at DB level first; falls back to PHP read-merge-write.
 *
 * @param int   $legislator_id
 * @param array $meta_updates
 * @return bool
 */
function _fi_legislator_merge_meta(int $legislator_id, array $meta_updates): bool {
	global $wpdb;

	if (empty($meta_updates)) {
		return true;
	}

	$patch_json = wp_json_encode($meta_updates);
	$result     = $wpdb->query($wpdb->prepare(
		"UPDATE {$wpdb->prefix}fi_legislators SET meta = JSON_MERGE_PATCH(IFNULL(meta, '{}'), %s) WHERE id = %d",
		$patch_json, $legislator_id
	));

	if ($result !== false) {
		return true;
	}

	// PHP fallback
	$current_json = $wpdb->get_var($wpdb->prepare(
		"SELECT meta FROM {$wpdb->prefix}fi_legislators WHERE id = %d",
		$legislator_id
	));
	$current = is_string($current_json) && $current_json !== '' ? (json_decode($current_json, true) ?: []) : [];
	$merged  = array_replace_recursive($current, $meta_updates);

	$r = $wpdb->update(
		$wpdb->prefix . 'fi_legislators',
		['meta' => wp_json_encode($merged)],
		['id'   => $legislator_id],
		['%s'],
		['%d']
	);

	return $r !== false;
}

// =============================================================================
// ROW FORMATTER  (shared by single-record external-ID lookup and list queries)
// =============================================================================

/**
 * Format a joined DB row (ARRAY_A from fi_legislator_sessions + fi_legislators)
 * into the standard legislator display array used by cards, admin lists, etc.
 *
 * @param array $row Raw joined database row.
 * @return array
 */
function fi_legislators_format_row(array $row): array {
	$gov      = $row['gov'] ?? '';
	$chamber  = $row['chamber'] ?? null;
	$party    = $row['session_party'] ?? $row['party'] ?? null;
	$state    = $row['session_state']  ?? $row['state']  ?? null;
	$district = $row['session_district'] ?? $row['district'] ?? null;

	$leg = [
		'id'                => (int) ($row['id'] ?? 0),
		'gov'               => $gov,
		'display_name'      => $row['display_name'] ?: trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
		'first_name'        => $row['first_name'] ?? '',
		'last_name'         => $row['last_name'] ?? '',
		'image_id'          => !empty($row['image_id']) ? (int) $row['image_id'] : null,
		'image_url'         => $row['image_url'] ?: ($row['legacy_image_url'] ?? null) ?: null,
		'score'             => isset($row['score']) && $row['score'] !== null ? (int) $row['score'] : null,
		'grade'             => $row['grade'] ?? null,
		'party'             => $party,
		'chamber'           => $chamber,
		'state'             => $state,
		'district'          => $district,
		'district_name'     => $row['district_name'] ?? null,
		'session_id'        => (int) ($row['session_id'] ?? 0),
		'session_name'      => $row['session_name'] ?? null,
		'session_parent_id' => !empty($row['session_parent_id']) ? (int) $row['session_parent_id'] : null,
		'date_updated'      => $row['date_updated'] ?? null,
	];

	$leg['url']           = fi_get_legislator_url($leg['id']);
	$leg['party_name']    = $leg['party']   ? fi_party_name($leg['party'])            : '';
	$leg['chamber_label'] = $leg['chamber'] ? fi_chamber_label($gov, $leg['chamber']) : '';
	$leg['chamber_title'] = $leg['chamber'] ? fi_chamber_title($gov, $leg['chamber']) : '';
	$leg['state_name']    = $leg['state']   ? fi_state_name($leg['state'])             : '';

	return $leg;
}

// =============================================================================
// PRIVATE HELPERS
// =============================================================================

/**
 * Format a raw fi_legislators table row into the base legislator array.
 * Used by fi_legislator_get() for full single-record retrieval.
 *
 * @param array $row Raw database row from fi_legislators.
 * @return array
 */
function _fi_legislator_format_base(array $row): array {
	return [
		'id'             => (int) ($row['id'] ?? 0),
		'first_name'     => $row['first_name'] ?? '',
		'middle_name'    => $row['middle_name'] ?? '',
		'last_name'      => $row['last_name'] ?? '',
		'display_name'   => $row['display_name'] ?? '',
		'sort_name'      => $row['sort_name'] ?? '',
		'slug'           => $row['slug'] ?? '',
		'email'          => $row['email'] ?? '',
		'phone'          => $row['phone'] ?? '',
		'website'        => $row['website'] ?? '',
		'address'        => $row['address'] ?? '',
		'twitter'        => $row['twitter'] ?? '',
		'facebook'       => $row['facebook'] ?? '',
		'image_id'       => (int) ($row['image_id'] ?? 0),
		'image_url'      => $row['image_url'] ?? '',
		'bioguide_id'    => $row['bioguide_id'] ?? '',
		'lis_id'         => $row['lis_id'] ?? '',
		'legiscan_id'    => $row['legiscan_id'] ?? '',
		'govtrack_id'    => $row['govtrack_id'] ?? '',
		'votesmart_id'   => $row['votesmart_id'] ?? '',
		'ballotpedia_id' => $row['ballotpedia_id'] ?? '',
		'openstates_id'  => $row['openstates_id'] ?? '',
		'meta'           => !empty($row['meta']) ? json_decode($row['meta'], true) : [],
		'sessions'       => [],
	];
}

/**
 * Query parent sessions for a single legislator, most-recent first.
 *
 * @param int $legislator_id
 * @return array
 */
function _fi_legislator_query_sessions(int $legislator_id): array {
	global $wpdb;

	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT
			s.id           AS session_id,
			s.name         AS session_name,
			s.date_start,
			s.date_end,
			s.gov,
			ls.score       AS session_score,
			ls.score_data  AS session_score_data,
			ls.chamber,
			ls.district,
			ls.party,
			ls.image_id,
			ls.score       AS lifetime_score
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		WHERE ls.legislator_id = %d
			AND s.parent_id IS NULL
		ORDER BY
			COALESCE(s.date_start, '9999-12-31') DESC,
			s.id DESC",
		$legislator_id
	), ARRAY_A);

	if (!$rows) {
		return [];
	}

	foreach ($rows as &$session) {
		$gov = $session['gov'] ?? '';
		$session['session_id']    = (int) $session['session_id'];
		$session['image_id']      = (int) ($session['image_id'] ?? 0);
		$session['gov_name']      = FI_GOVERNMENTS[$gov]['name'] ?? $gov;
		$session['state_name']    = FI_GOVERNMENTS[$gov]['state_name'] ?? '';
		$session['party_name']    = FI_PARTIES[$session['party']] ?? $session['party'];
		$session['chamber_label'] = FI_CHAMBERS[$session['chamber']]['label'] ?? $session['chamber'];
		$session['chamber_title'] = FI_CHAMBERS[$session['chamber']]['title'] ?? '';
	}
	unset($session);

	return $rows;
}

/**
 * Build shared WHERE conditions + params array for fi_legislators_get() and fi_legislators_count().
 * Returns [$where_array, $params_array].
 *
 * @param array  $args Validated args.
 * @param string $gov  Sanitized gov code.
 * @return array{0: array, 1: array}
 */
function _fi_legislators_build_where(array $args, string $gov): array {
	global $wpdb;

	$where  = ['ls.gov = %s'];
	$params = [$gov];

	// If explicit session_id provided, use it directly. Skip scope resolution for admin queries.
	if ( ! empty( $args['session_id'] ) ) {
		$where[]  = 'ls.session_id = %d';
		$params[] = (int) $args['session_id'];
	}

	if (!empty($args['chamber'])) {
		$chamber = strtoupper(substr($args['chamber'], 0, 1));
		if (in_array($chamber, ['S', 'H'], true)) {
			$where[]  = 'ls.chamber = %s';
			$params[] = $chamber;
		}
	}

	if (!empty($args['party'])) {
		$party    = sanitize_text_field($args['party']);
		$where[]  = '(ls.party = %s OR ls.party LIKE %s)';
		$params[] = $party;
		$params[] = '%' . $wpdb->esc_like($party) . '%';
	}

	if (!empty($args['state'])) {
		$where[]  = 'ls.state = %s';
		$params[] = strtoupper(substr($args['state'], 0, 2));
	}

	if (!empty($args['search'])) {
		$like     = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
		$where[]  = '(l.display_name LIKE %s OR l.first_name LIKE %s OR l.last_name LIKE %s)';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}

	return [$where, $params];
}

/**
 * Resolve session scope: returns the parent session ID + all child IDs,
 * auto-detecting the current session when none is supplied.
 *
 * @param int|null $session_id Requested session ID (0 = auto-detect).
 * @param string   $gov        Government code.
 * @return int[]
 */
function fi_legislators_resolve_session_scope(?int $session_id, string $gov): array {
	global $wpdb;

	if ($session_id === null || $session_id <= 0) {
		$session_id = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}fi_sessions
			WHERE gov = %s AND is_current = 1 AND (parent_id = 0 OR parent_id IS NULL)
			ORDER BY id DESC LIMIT 1",
			$gov
		));

		if ($session_id <= 0) {
			$session_id = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}fi_sessions
				WHERE gov = %s AND (parent_id = 0 OR parent_id IS NULL)
				ORDER BY date_end DESC, id DESC LIMIT 1",
				$gov
			));
		}

		if ($session_id <= 0) {
			return [];
		}
	}

	$cache_key = "fi_session_scope/{$gov}/{$session_id}";
	$cached    = fi_cache($cache_key);
	if ($cached !== null && is_array($cached)) {
		return $cached;
	}

	$parent = $wpdb->get_row($wpdb->prepare(
		"SELECT id, parent_id FROM {$wpdb->prefix}fi_sessions WHERE id = %d AND gov = %s",
		$session_id, $gov
	), ARRAY_A);

	if (!$parent) {
		return [];
	}

	$parent_id = (int) ($parent['parent_id'] ?: $parent['id']);

	$ids = $wpdb->get_col($wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}fi_sessions WHERE gov = %s AND (id = %d OR parent_id = %d)",
		$gov, $parent_id, $parent_id
	));

	$result = array_map('intval', $ids);
	fi_cache($cache_key, $result, HOUR_IN_SECONDS);

	return $result;
}

/**
 * Build ORDER BY clause from a sort code.
 *
 * @param string $sort Sort code: na|nd|sa|sd|pa|pd|oa|od
 * @return string
 */
function _fi_legislators_build_order_by(string $sort): string {
	$map = [
		'na' => 'l.last_name ASC,  l.first_name ASC',
		'nd' => 'l.last_name DESC, l.first_name DESC',
		'sa' => 'ls.score ASC,  l.last_name ASC, l.first_name ASC',
		'sd' => 'ls.score DESC, l.last_name ASC, l.first_name ASC',
		'pa' => 'ls.party ASC,  l.last_name ASC, l.first_name ASC',
		'pd' => 'ls.party DESC, l.last_name ASC, l.first_name ASC',
		'oa' => 'ls.chamber ASC,  ls.district ASC, l.last_name ASC, l.first_name ASC',
		'od' => 'ls.chamber DESC, ls.district ASC, l.last_name ASC, l.first_name ASC',
	];
	return $map[strtolower(trim($sort))] ?? $map['na'];
}

/**
 * Build a deterministic cache key from query args.
 *
 * @param array  $args Query args.
 * @param string $type 'list' or 'count'.
 * @return string
 */
function _fi_legislators_build_cache_key(array $args, string $type = 'list'): string {
	return implode('/', [
		'legislators',
		$type,
		strtolower($args['gov'] ?? ''),
		(int) ($args['session_id'] ?? 0),
		strtolower($args['chamber'] ?? ''),
		strtolower($args['party'] ?? ''),
		strtoupper($args['state'] ?? ''),
		md5($args['search'] ?? ''),
		$args['sort'] ?? 'na',
		(int) ($args['limit'] ?? LEGISLATORS_DEFAULT_LIMIT),
		(int) ($args['offset'] ?? 0),
	]);
}