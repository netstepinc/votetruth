<?php
/**
 * Freedom Index Legislator Votes Data Management
 *
 * Builds comprehensive session cache with votes + rollcalls for fast page loads.
 * Calculates report scores from cached data.
 *
 * Cache Strategy:
 * - Cache key: fi_session_votes_{session_id}
 * - Contains: all published scored votes for session + rollcalls for those votes
 * - Invalidated when votes/rollcalls are updated
 *
 * Notes:
 * - Slug fields are intentionally omitted from returned arrays.
 */

if (!defined('ABSPATH')) exit;

/**
 * Request-level cache for legislator tag lookups.
 *
 * @param string|null $key Cache key.
 * @param mixed $value Value to set.
 * @param bool $set Whether to set the value.
 * @return mixed
 */
function fi_legislator_votes_tags_request_cache(?string $key = null, $value = null, bool $set = false) {
	static $cache = [];

	if ($key === null) {
		return $cache;
	}

	if ($set) {
		$cache[$key] = $value;
		return $value;
	}

	return $cache[$key] ?? null;
}

/**
 * Initialize cache invalidation hooks.
 *
 * @return void
 */
function fi_legislator_votes_init(): void {
	add_action('fi_vote_saved', 'fi_legislator_votes_on_vote_saved', 10, 2);
	add_action('fi_rollcall_saved', 'fi_legislator_votes_on_rollcall_saved', 10, 2);
	add_action('fi_report_saved', 'fi_legislator_votes_on_report_saved', 10, 2);
}
add_action('init', 'fi_legislator_votes_init', 10);

/**
 * Dump legislator-level vote cache.
 *
 * @param int $legislator_id Legislator ID.
 * @return void
 */
function fi_legislator_votes_cache_invalidate(int $legislator_id): void {
	$legislator_id = absint($legislator_id);
	if ($legislator_id <= 0) {
		return;
	}
	fi_cache('legislator/' . $legislator_id . '-votes', 'DUMP');
}

/**
 * Invalidate legislator vote caches for every legislator in a session.
 *
 * @param int $session_id Session ID.
 * @return void
 */
function fi_legislator_votes_invalidate_session_legislators(int $session_id): void {
	global $wpdb;

	$session_id = absint($session_id);
	if ($session_id <= 0) {
		return;
	}

	$legislator_ids = $wpdb->get_col($wpdb->prepare(
		"SELECT DISTINCT legislator_id FROM {$wpdb->prefix}fi_legislator_sessions WHERE session_id = %d",
		$session_id
	));

	foreach ((array) $legislator_ids as $legislator_id) {
		fi_legislator_votes_cache_invalidate((int) $legislator_id);
	}
}

/**
 * Handle vote saved and invalidate session + legislator caches.
 *
 * @param int $vote_id Vote ID.
 * @param array $data Vote data.
 * @return void
 */
function fi_legislator_votes_on_vote_saved(int $vote_id, array $data): void {
	if (!empty($data['session_id'])) {
		fi_session_votes_cache_invalidate((int) $data['session_id']);
	}

	global $wpdb;
	$legislator_ids = $wpdb->get_col($wpdb->prepare(
		"SELECT DISTINCT legislator_id FROM {$wpdb->prefix}fi_voterc WHERE vote_id = %d",
		$vote_id
	));
	foreach ((array) $legislator_ids as $legislator_id) {
		fi_legislator_votes_cache_invalidate((int) $legislator_id);
	}
}

/**
 * Handle rollcall saved and invalidate session + legislator caches.
 *
 * @param int $rollcall_id Rollcall ID.
 * @param array $data Rollcall data.
 * @return void
 */
function fi_legislator_votes_on_rollcall_saved(int $rollcall_id, array $data): void {
	if (empty($data['vote_id'])) {
		return;
	}

	$vote = fi_vote_get((int) $data['vote_id']);
	$vote_session_id = (int) ($vote['session_id'] ?? 0);
	if ($vote_session_id > 0) {
		fi_session_votes_cache_invalidate($vote_session_id);
	}

	if (!empty($data['legislator_id'])) {
		fi_legislator_votes_cache_invalidate((int) $data['legislator_id']);
	}
}

/**
 * Handle report saved and invalidate legislator caches for the session.
 *
 * @param int $report_id Report ID.
 * @param array $data Report data.
 * @return void
 */
function fi_legislator_votes_on_report_saved(int $report_id, array $data): void {
	if (!empty($data['session_id'])) {
		fi_legislator_votes_invalidate_session_legislators((int) $data['session_id']);
	}
}

/**
 * Decode a JSON meta/payload value to array.
 *
 * @param mixed $value JSON string or array.
 * @return array
 */
function fi_legislator_votes_decode_array($value): array {
	if (is_array($value)) {
		return $value;
	}

	if (is_string($value) && $value !== '') {
		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : [];
	}

	return [];
}

/**
 * Get votes for a session through core API.
 *
 * @param int $session_id Session ID.
 * @param array $args Query args.
 * @return array
 */
function fi_legislator_votes_get_votes_by_session(int $session_id, array $args = []): array {
	if (function_exists('fi_votes_get_by_session')) {
		$results = fi_votes_get_by_session($session_id, $args);
		return is_array($results) ? $results : [];
	}

	if (function_exists('fi_votes_get')) {
		$args = array_merge($args, ['session_id' => $session_id]);
		$results = fi_votes_get($args);
		return is_array($results) ? $results : [];
	}

	return [];
}

/**
 * Get votes for a tag through core API.
 *
 * @param int $tag_id Tag ID.
 * @param array $args Query args.
 * @return array
 */
function fi_legislator_votes_get_votes_by_tag_public(int $tag_id, array $args = []): array {
	if (function_exists('fi_votes_get_by_tag')) {
		$results = fi_votes_get_by_tag($tag_id, $args);
		return is_array($results) ? $results : [];
	}

	return [];
}

/**
 * Get rollcalls by vote IDs through core API.
 *
 * @param array $vote_ids Vote IDs.
 * @return array
 */
function fi_legislator_votes_get_rollcalls_by_vote_ids(array $vote_ids): array {
	$vote_ids = array_values(array_filter(array_map('absint', $vote_ids)));
	if (empty($vote_ids)) {
		return [];
	}

	if (function_exists('fi_rollcalls_get_by_vote_ids')) {
		$results = fi_rollcalls_get_by_vote_ids($vote_ids);
		return is_array($results) ? $results : [];
	}

	return [];
}

/**
 * Get rollcalls through core API.
 *
 * @param array $args Query args.
 * @return array
 */
function fi_legislator_votes_get_rollcalls(array $args = []): array {
	if (function_exists('fi_rollcalls_get')) {
		$results = fi_rollcalls_get($args);
		return is_array($results) ? $results : [];
	}

	return [];
}

/**
 * Get one rollcall by vote and legislator through core API.
 *
 * @param int $vote_id Vote ID.
 * @param int $legislator_id Legislator ID.
 * @return object|array|null
 */
function fi_legislator_votes_get_rollcall(int $vote_id, int $legislator_id) {
	if (function_exists('fi_rollcall_get')) {
		return fi_rollcall_get($vote_id, $legislator_id);
	}

	if (function_exists('fi_rollcall')) {
		return fi_rollcall($vote_id, $legislator_id);
	}

	return null;
}

/**
 * Get vote tags by vote IDs through core API.
 *
 * @param array $vote_ids Vote IDs.
 * @return array
 */
function fi_legislator_votes_get_tags_by_vote_ids(array $vote_ids): array {
	$vote_ids = array_values(array_filter(array_map('absint', $vote_ids)));
	if (empty($vote_ids)) {
		return [];
	}

	if (function_exists('fi_vote_tags_get_by_vote_ids')) {
		$results = fi_vote_tags_get_by_vote_ids($vote_ids);
		return is_array($results) ? $results : [];
	}

	if (function_exists('fi_vote_tags_get_tags_by_vote_ids')) {
		$results = fi_vote_tags_get_tags_by_vote_ids($vote_ids);
		return is_array($results) ? $results : [];
	}

	return [];
}

/**
 * Normalize a rollcall result into array form.
 *
 * @param array|null $rollcall Rollcall result.
 * @return array|null Rollcall array.
 */
function fi_legislator_votes_normalize_rollcall(?array $rollcall): ?array {
	if (!$rollcall) {
		return null;
	}

	return [
		'cast'         => (string) ($rollcall['cast'] ?? ''),
		'is_override'  => (bool) ($rollcall['is_override'] ?? false),
		'date_created' => $rollcall['date_created'] ?? null,
	];
}

/**
 * Format a vote (array) for legislator vote display/cache arrays.
 *
 * @param array      $vote         Vote array.
 * @param array|null $session_cache Optional session cache data keyed by session ID.
 * @return array
 */
function fi_legislator_votes_format_vote(array $vote, ?array $session_cache = null): array {
	$meta = fi_legislator_votes_decode_array($vote['meta'] ?? []);
	$session_id = (int) ($vote['session_id'] ?? 0);

	$formatted = [
		'vote_id'            => (int) ($vote['id'] ?? 0),
		'title'              => $vote['title'] ?? '',
		'bill_number'        => $vote['bill_number'] ?? ($meta['bill_number'] ?? $meta['bill_key'] ?? null),
		'date_voted'         => $vote['date_voted'] ?? null,
		'constitutional'     => $vote['constitutional'] ?? '',
		'chamber'            => $vote['chamber'] ?? '',
		'gov'                => $vote['gov'] ?? '',
		'session_id'         => $session_id,
		'description_short'  => $meta['description_short'] ?? $meta['text_scorecard'] ?? null,
		'description_medium' => $meta['description_medium'] ?? $meta['text_freedomindex'] ?? null,
		'description_long'   => $meta['description_long'] ?? $meta['text_scorecard_more'] ?? null,
		'url_source'         => $meta['url'] ?? $meta['url_source'] ?? null,
		'url_bill'           => $meta['url_bill'] ?? null,
		'url_rollcall'       => $meta['url_rollcall'] ?? null,
		'meta'               => $meta,
	];

	if ($session_cache !== null && isset($session_cache[$session_id])) {
		$formatted['session_name'] = $session_cache[$session_id]['name'] ?? '';
	}

	return $formatted;
}

/**
 * Get complete vote structure for a legislator.
 *
 * @param int $legislator_id Legislator ID.
 * @param string $chamber Legislator chamber H or S.
 * @return array Structured vote data.
 */
/** @deprecated 0 active callers — superseded by fi_session_votes_cache_get() + controller-level accumulation */
function x_fi_legislator_votes_get(int $legislator_id, string $chamber): array {
	$legislator_id = absint($legislator_id);
	$chamber = strtoupper($chamber);

	if ($legislator_id <= 0 || !in_array($chamber, ['H', 'S'], true)) {
		return [];
	}

	$legislator = fi_legislator_get($legislator_id);
	if (!$legislator || empty($legislator['sessions'])) {
		return [];
	}

	$sessions_data = [];

	foreach ($legislator['sessions'] as $session) {
		$session_id = (int) ($session['session_id'] ?? 0);
		if ($session_id <= 0) {
			continue;
		}

		$session_cache = fi_session_votes_cache_get($session_id);
		if (empty($session_cache)) {
			continue;
		}

		$reports = fi_reports_get_by_session($session_id);
		$reports = is_array($reports) ? $reports : [];

		$score_data_raw = $session['score_session_data'] ?? null;
		$session_data = [
			'session_id'   => $session_id,
			'session_name' => $session['session_name'] ?? ($session_cache['session_name'] ?? ''),
			'gov'          => $session['gov'] ?? ($session_cache['gov'] ?? ''),
			'date_start'   => $session['date_start'] ?? null,
			'date_end'     => $session['date_end'] ?? null,
			'score'        => $session['score_session'] ?? null,
			'score_data'   => is_string($score_data_raw) ? json_decode($score_data_raw, true) : $score_data_raw,
			'reports'      => [],
			'all_votes'    => [],
		];

		foreach ($reports as $report) {
			$report_data = fi_legislator_votes_build_report_data($report, $session_cache, $legislator_id, $chamber);
			if ($report_data) {
				$session_data['reports'][] = $report_data;
			}
		}

		$report_vote_ids = [];
		foreach ($session_data['reports'] as $report) {
			foreach ($report['votes'] as $vote) {
				$report_vote_ids[] = (int) $vote['vote_id'];
			}
		}
		$report_vote_ids = array_values(array_unique($report_vote_ids));

		foreach (($session_cache['votes'] ?? []) as $vote) {
			if (!in_array((int) $vote['vote_id'], $report_vote_ids, true)) {
				$vote['rollcall'] = fi_legislator_votes_get_legislator_rollcall((int) $vote['vote_id'], $legislator_id, $session_cache['rollcalls'] ?? []);
				$session_data['all_votes'][] = $vote;
			}
		}

		$sessions_data[] = $session_data;
	}

	return $sessions_data;
}

/**
 * Build report data with calculated score.
 *
 * @param array  $report       Report array.
 * @param array  $session_cache Cached session data.
 * @param int    $legislator_id Legislator ID.
 * @param string $chamber       Legislator chamber H or S.
 * @return array|null Report data or null if invalid.
 */
function fi_legislator_votes_build_report_data(array $report, array $session_cache, int $legislator_id, string $chamber): ?array {
	$payload = fi_legislator_votes_decode_array($report['payload_json'] ?? '{}');
	if (empty($payload)) {
		return null;
	}

	$vote_ids = [];
	if ($chamber === 'H' && !empty($payload['votes_h'])) {
		$vote_ids = array_map('absint', (array) $payload['votes_h']);
	} elseif ($chamber === 'S' && !empty($payload['votes_s'])) {
		$vote_ids = array_map('absint', (array) $payload['votes_s']);
	}

	$vote_ids = array_values(array_filter($vote_ids));
	if (empty($vote_ids)) {
		return null;
	}

	$report_votes = [];
	foreach ($vote_ids as $vote_id) {
		$vote = fi_legislator_votes_find_vote_in_cache($vote_id, $session_cache['votes'] ?? []);
		if ($vote) {
			$vote['rollcall'] = fi_legislator_votes_get_legislator_rollcall($vote_id, $legislator_id, $session_cache['rollcalls'] ?? []);
			if (!empty($vote['rollcall']['cast'])) {
				$vote['cast'] = $vote['rollcall']['cast'];
			}
			$report_votes[] = $vote;
		}
	}

	$score_data = fi_legislator_votes_calculate_score_from_votes($report_votes, $legislator_id);

	return [
		'report_id'   => (int) ($report['id'] ?? 0),
		'report_name' => $report['title'] ?? '',
		'content'     => $payload['content'] ?? '',
		'format'      => $payload['format'] ?? ($report['format'] ?? 'scorecard'),
		'score'       => $score_data['score'] ?? null,
		'score_data'  => $score_data,
		'votes'       => $report_votes,
	];
}

/**
 * Get or build session vote cache.
 *
 * @param int $session_id Session ID.
 * @return array Cached session data.
 */
function fi_session_votes_cache_get(int $session_id): array {
	$session_id = absint($session_id);
	if ($session_id <= 0) {
		return [];
	}

	$cache_key = 'fi_session_votes_' . $session_id;

	if (!defined('FI_DEV') || !FI_DEV) {
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			return is_array($cached) ? $cached : [];
		}
	}

	$cache_data = fi_session_votes_cache_build($session_id);

	if (!defined('FI_DEV') || !FI_DEV) {
		set_transient($cache_key, $cache_data, MONTH_IN_SECONDS);
	}

	return $cache_data;
}

/**
 * Build comprehensive session cache.
 *
 * @param int $session_id Session ID.
 * @return array Session cache data.
 */
function fi_session_votes_cache_build(int $session_id): array {
	$session_id = absint($session_id);
	$session = fi_session_get($session_id);
	if (!$session) {
		return [];
	}

	$votes = fi_legislator_votes_get_votes_by_session($session_id, [
		'status'   => 'publish',
		'orderby'  => 'date_voted',
		'order'    => 'ASC',
		'per_page' => -1,
	]);

	$formatted_votes = [];
	$vote_ids = [];

	foreach ($votes as $vote) {
		$vote = is_object($vote) ? (array) $vote : $vote;
		if (!in_array($vote['constitutional'] ?? '', ['Y', 'N'], true)) {
			continue;
		}

		$vote_ids[] = (int) ($vote['id'] ?? 0);
		$formatted_votes[] = fi_legislator_votes_format_vote($vote);
	}

	$rollcalls = [];
	if (!empty($vote_ids)) {
		$rollcall_results = fi_legislator_votes_get_rollcalls_by_vote_ids($vote_ids);

		foreach ($rollcall_results as $rc) {
			$rc = is_object($rc) ? (array) $rc : $rc;
			$vote_id = (int) ($rc['vote_id'] ?? 0);
			$legislator_id = (int) ($rc['legislator_id'] ?? 0);
			if ($vote_id <= 0 || $legislator_id <= 0) {
				continue;
			}

			if (!isset($rollcalls[$vote_id])) {
				$rollcalls[$vote_id] = [];
			}

			$rollcalls[$vote_id][$legislator_id] = [
				'cast'         => (string) ($rc['cast'] ?? ''),
				'is_override'  => (bool) ($rc['is_override'] ?? false),
				'date_created' => $rc['date_created'] ?? null,
			];
		}
	}

	$vote_tags = [];
	if (!empty($vote_ids)) {
		$tag_results = fi_legislator_votes_get_tags_by_vote_ids($vote_ids);

		foreach ($tag_results as $tag) {
			$tag = is_object($tag) ? (array) $tag : $tag;
			$vote_id = (int) ($tag['vote_id'] ?? 0);
			if ($vote_id <= 0) {
				continue;
			}

			if (!isset($vote_tags[$vote_id])) {
				$vote_tags[$vote_id] = [];
			}

			$vote_tags[$vote_id][] = [
				'id'   => (int) ($tag['tag_id'] ?? 0),
				'name' => (string) ($tag['tag_name'] ?? ''),
			];
		}
	}

	foreach ($formatted_votes as &$vote) {
		$vote['tags'] = $vote_tags[$vote['vote_id']] ?? [];
	}
	unset($vote);

	$session_arr = is_array($session) ? $session : (array) $session;

	return [
		'session_id'   => $session_id,
		'session_name' => $session_arr['name'] ?? '',
		'gov'          => $session_arr['gov'] ?? '',
		'votes'        => $formatted_votes,
		'rollcalls'    => $rollcalls,
		'tags'         => $vote_tags,
	];
}

/**
 * Find vote in cache by ID.
 *
 * @param int $vote_id Vote ID.
 * @param array $votes_cache Votes cache.
 * @return array|null Vote data or null.
 */
function fi_legislator_votes_find_vote_in_cache(int $vote_id, array $votes_cache): ?array {
	foreach ($votes_cache as $vote) {
		if ((int) ($vote['vote_id'] ?? 0) === $vote_id) {
			return $vote;
		}
	}

	return null;
}

/**
 * Get legislator's rollcall for a vote from cache.
 *
 * @param int $vote_id Vote ID.
 * @param int $legislator_id Legislator ID.
 * @param array $rollcalls_cache Rollcalls cache indexed by vote ID then legislator ID.
 * @return array|null Rollcall data or null.
 */
function fi_legislator_votes_get_legislator_rollcall(int $vote_id, int $legislator_id, array $rollcalls_cache): ?array {
	return $rollcalls_cache[$vote_id][$legislator_id] ?? null;
}

/**
 * Calculate score from formatted votes and rollcalls.
 *
 * @param array $votes Vote data with rollcall/cast.
 * @param int $legislator_id Legislator ID.
 * @return array Score data.
 */
function fi_legislator_votes_calculate_score_from_votes(array $votes, int $legislator_id): array {
	$votes_good = 0;
	$votes_bad = 0;
	$votes_not = 0;
	$votes_scored = 0;
	$votes_total = count($votes);

	foreach ($votes as $vote) {
		$cast = $vote['cast'] ?? ($vote['rollcall']['cast'] ?? 'X');
		$constitutional = $vote['constitutional'] ?? '';

		if (in_array($cast, ['P', 'A', 'X', ''], true) || empty($constitutional)) {
			$votes_not++;
			continue;
		}

		$votes_scored++;
		if ($cast === $constitutional) {
			$votes_good++;
		} else {
			$votes_bad++;
		}
	}

	$score = ($votes_scored > 0) ? (int) round(($votes_good / $votes_scored) * 100, 0) : 0;
	$grade = function_exists('fi_score_calculate_grade') ? fi_score_calculate_grade($score) : '';

	return [
		'score'  => $score,
		'grade'  => $grade,
		'total'  => $votes_total,
		'good'   => $votes_good,
		'bad'    => $votes_bad,
		'not'    => $votes_not,
		'scored' => $votes_scored,
	];
}

/**
 * Invalidate session vote cache.
 *
 * @param int $session_id Session ID.
 * @return bool Success.
 */
function fi_session_votes_cache_invalidate(int $session_id): bool {
	$session_id = absint($session_id);
	return $session_id > 0 ? delete_transient('fi_session_votes_' . $session_id) : false;
}

/**
 * Invalidate all session vote caches.
 *
 * @return int Number of caches deleted.
 */
function fi_session_votes_cache_invalidate_all(): int {
	global $wpdb;

	$sessions = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}fi_sessions");
	$deleted = 0;

	foreach ($sessions as $session_id) {
		if (fi_session_votes_cache_invalidate((int) $session_id)) {
			$deleted++;
		}
	}

	return $deleted;
}

/**
 * Get all unique tags used across all votes for a legislator.
 *
 * @param int $legislator_id Legislator ID.
 * @param string $chamber Legislator chamber H or S.
 * @return array Tag objects with vote_count.
 */
/** @deprecated 0 active callers — superseded by career JOIN query in legislator.php controller */
function x_fi_legislator_tags_get(int $legislator_id, string $chamber): array {
	$legislator_id = absint($legislator_id);
	$chamber = strtoupper($chamber);

	if ($legislator_id <= 0 || !in_array($chamber, ['H', 'S'], true)) {
		return [];
	}

	$cache_key = "{$legislator_id}_{$chamber}";
	$cached = fi_legislator_votes_tags_request_cache($cache_key);
	if ($cached !== null) {
		return $cached;
	}

	global $wpdb;

	$legislator = fi_legislator_get($legislator_id);
	if (!$legislator || empty($legislator['sessions'])) {
		fi_legislator_votes_tags_request_cache($cache_key, [], true);
		return [];
	}

	$session_ids = [];
	foreach ($legislator['sessions'] as $session) {
		$session_ids[] = (int) ($session['session_id'] ?? 0);
	}

	$session_ids = array_values(array_filter(array_map('absint', $session_ids)));
	if (empty($session_ids)) {
		fi_legislator_votes_tags_request_cache($cache_key, [], true);
		return [];
	}

	$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
	$params = array_merge($session_ids, [$chamber, $legislator_id]);

	$vote_ids = $wpdb->get_col($wpdb->prepare(
		"SELECT DISTINCT v.id
		FROM {$wpdb->prefix}fi_votes v
		INNER JOIN {$wpdb->prefix}fi_voterc rc ON v.id = rc.vote_id
		WHERE v.session_id IN ($placeholders)
		AND v.chamber = %s
		AND v.status = 'publish'
		AND rc.legislator_id = %d",
		...$params
	));

	$vote_ids = array_values(array_filter(array_map('absint', (array) $vote_ids)));
	if (empty($vote_ids)) {
		fi_legislator_votes_tags_request_cache($cache_key, [], true);
		return [];
	}

	$vote_placeholders = implode(',', array_fill(0, count($vote_ids), '%d'));

	$tags = $wpdb->get_results($wpdb->prepare(
		"SELECT
			t.id,
			t.name,
			COUNT(DISTINCT vt.vote_id) as vote_count
		FROM {$wpdb->prefix}fi_taxonomy t
		INNER JOIN {$wpdb->prefix}fi_vote_tags vt ON t.id = vt.tag_id
		WHERE vt.vote_id IN ($vote_placeholders)
		AND t.taxonomy = 'tag'
		GROUP BY t.id, t.name
		ORDER BY t.name ASC",
		...$vote_ids
	));

	$result = $tags ?: [];
	fi_legislator_votes_tags_request_cache($cache_key, $result, true);

	return $result;
}

/**
 * Get votes for a legislator filtered by tag.
 *
 * @param int $legislator_id Legislator ID.
 * @param string $chamber Legislator chamber H or S.
 * @param int $tag_id Tag ID.
 * @return array Vote data with rollcalls.
 */
function fi_legislator_votes_get_by_tag(int $legislator_id, string $chamber, int $tag_id): array {
	$legislator_id = absint($legislator_id);
	$tag_id = absint($tag_id);
	$chamber = strtoupper($chamber);

	if ($legislator_id <= 0 || $tag_id <= 0 || !in_array($chamber, ['H', 'S'], true)) {
		return [];
	}

	$legislator = fi_legislator_get($legislator_id);
	if (!$legislator || empty($legislator['sessions'])) {
		return [];
	}

	$session_ids = [];
	foreach ($legislator['sessions'] as $session) {
		$session_ids[] = (int) ($session['session_id'] ?? 0);
	}
	$session_ids = array_values(array_filter(array_map('absint', $session_ids)));

	if (empty($session_ids)) {
		return [];
	}

	$tag_votes = fi_legislator_votes_get_votes_by_tag_public($tag_id, [
		'session_ids' => $session_ids,
		'chamber'     => $chamber,
		'status'      => 'publish',
		'orderby'     => 'date_voted',
		'order'       => 'DESC',
	]);

	if (empty($tag_votes)) {
		return [];
	}

	$vote_ids = array_values(array_filter(array_map(static function($vote) {
		$vote = is_object($vote) ? (array) $vote : $vote;
		return absint($vote['id'] ?? 0);
	}, $tag_votes)));

	if (empty($vote_ids)) {
		return [];
	}

	// Load only rollcalls for the relevant vote IDs, then filter to this legislator.
	$rollcall_results = fi_legislator_votes_get_rollcalls_by_vote_ids($vote_ids);
	$rollcalls_by_vote = [];

	foreach ($rollcall_results as $rc) {
		$rc = is_object($rc) ? (array) $rc : $rc;
		if ((int) ($rc['legislator_id'] ?? 0) !== $legislator_id) {
			continue;
		}

		$rollcalls_by_vote[(int) ($rc['vote_id'] ?? 0)] = [
			'cast'         => (string) ($rc['cast'] ?? ''),
			'is_override'  => (bool) ($rc['is_override'] ?? false),
			'date_created' => $rc['date_created'] ?? null,
		];
	}

	if (empty($rollcalls_by_vote)) {
		return [];
	}

	$tag_results = fi_legislator_votes_get_tags_by_vote_ids($vote_ids);
	$tags_by_vote = [];

	foreach ($tag_results as $tag) {
		$tag = is_object($tag) ? (array) $tag : $tag;
		$vote_id = (int) ($tag['vote_id'] ?? 0);
		if ($vote_id <= 0) {
			continue;
		}

		if (!isset($tags_by_vote[$vote_id])) {
			$tags_by_vote[$vote_id] = [];
		}

		$tags_by_vote[$vote_id][] = [
			'id'   => (int) ($tag['tag_id'] ?? 0),
			'name' => (string) ($tag['tag_name'] ?? ''),
		];
	}

	$session_cache = [];
	foreach ($tag_votes as $vote) {
		$vote = is_object($vote) ? (array) $vote : $vote;
		$session_id = (int) ($vote['session_id'] ?? 0);
		if ($session_id > 0 && !isset($session_cache[$session_id])) {
			$session_obj = fi_session_get($session_id);
			$session_arr = is_array($session_obj) ? $session_obj : (array) $session_obj;
			$session_cache[$session_id] = [
				'name' => $session_arr['name'] ?? '',
			];
		}
	}

	$formatted_votes = [];
	foreach ($tag_votes as $vote) {
		$vote = is_object($vote) ? (array) $vote : $vote;
		$vote_id = (int) ($vote['id'] ?? 0);
		if ($vote_id <= 0 || empty($rollcalls_by_vote[$vote_id])) {
			continue;
		}

		$formatted = fi_legislator_votes_format_vote($vote, $session_cache);
		$formatted['rollcall'] = $rollcalls_by_vote[$vote_id];
		$formatted['cast'] = $rollcalls_by_vote[$vote_id]['cast'] ?? '';
		$formatted['tags'] = $tags_by_vote[$vote_id] ?? [];
		$formatted_votes[] = $formatted;
	}

	return $formatted_votes;
}

/**
 * Normalize legislator cast to Y, N, or X.
 *
 * @param string $cast Raw cast value.
 * @return string
 */
function fi_legislator_votes_normalize_cast(string $cast): string {
	$cast = strtoupper(trim($cast));
	return in_array($cast, ['Y', 'N'], true) ? $cast : 'X';
}

/**
 * Published child + parent session IDs for vote queries.
 *
 * @param int $parent_session_id Parent session ID.
 * @return array
 */
function fi_legislator_votes_child_session_ids(int $parent_session_id): array {
	global $wpdb;

	$parent_session_id = absint($parent_session_id);
	if ($parent_session_id <= 0) {
		return [];
	}

	$children = $wpdb->get_col($wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}fi_sessions WHERE parent_id = %d AND status = 'publish'",
		$parent_session_id
	));

	$ids = array_map('intval', (array) $children);
	$ids[] = $parent_session_id;

	return array_values(array_unique($ids));
}

/**
 * Score a batch of vote IDs using precomputed matched/counted flags.
 *
 * @param array $scoring_map vote_id => scoring fields.
 * @param array $vote_ids Vote IDs to score.
 * @return array
 */
function fi_legislator_votes_calc_score(array $scoring_map, array $vote_ids): array {
	$vote_ids = array_values(array_unique(array_map('intval', $vote_ids)));
	$total = count($vote_ids);
	$counted = 0;
	$matched = 0;

	foreach ($vote_ids as $vote_id) {
		if (empty($scoring_map[$vote_id]['counted'])) {
			continue;
		}
		$counted++;
		if (!empty($scoring_map[$vote_id]['matched'])) {
			$matched++;
		}
	}

	if ($counted === 0) {
		return ['total' => $total, 'counted' => 0, 'matched' => 0, 'score' => 'NA'];
	}

	$half = (int) round($total / 2, 0);
	$score = ($counted < $half) ? 'NA' : (int) round(($matched / $counted) * 100, 0);

	return [
		'total'   => $total,
		'counted' => $counted,
		'matched' => $matched,
		'score'   => $score,
	];
}

/**
 * Sort vote IDs by date_voted DESC (newest first), then id DESC.
 *
 * @param array $vote_ids Vote IDs to sort.
 * @param array $votes    Compiled votes map keyed by vote ID.
 * @return array
 */
function fi_legislator_votes_sort_ids_by_date(array $vote_ids, array $votes): array {
	$vote_ids = array_values(array_unique(array_map('intval', $vote_ids)));
	usort($vote_ids, static function ($a, $b) use ($votes) {
		$da = strtotime((string) ($votes[$a]['date_voted'] ?? '')) ?: 0;
		$db = strtotime((string) ($votes[$b]['date_voted'] ?? '')) ?: 0;
		if ($db !== $da) {
			return $db <=> $da;
		}
		return $b <=> $a;
	});
	return $vote_ids;
}

/**
 * Format vote date for card display.
 *
 * @param string $date_voted Raw datetime.
 * @return string
 */
function fi_legislator_votes_format_date(string $date_voted): string {
	if ($date_voted === '') {
		return '';
	}
	$timestamp = strtotime($date_voted);
	return $timestamp !== false ? date('n/j/Y', $timestamp) : $date_voted;
}

/**
 * Extract ordered report vote IDs for a chamber.
 *
 * @param array $payload Report payload.
 * @param string $chamber Legislator chamber H or S.
 * @return array
 */
function fi_legislator_votes_extract_report_vote_ids(array $payload, string $chamber): array {
	$chamber = strtoupper($chamber);

	if ($chamber === 'S') {
		if (!empty($payload['votes_s_order']) && is_array($payload['votes_s_order'])) {
			return array_values(array_filter(array_map('intval', $payload['votes_s_order'])));
		}
		if (!empty($payload['votes_s']) && is_array($payload['votes_s'])) {
			return array_values(array_filter(array_map('intval', $payload['votes_s'])));
		}
	} elseif ($chamber === 'H') {
		if (!empty($payload['votes_h_order']) && is_array($payload['votes_h_order'])) {
			return array_values(array_filter(array_map('intval', $payload['votes_h_order'])));
		}
		if (!empty($payload['votes_h']) && is_array($payload['votes_h'])) {
			return array_values(array_filter(array_map('intval', $payload['votes_h'])));
		}
	}

	return [];
}

/**
 * Build vote-card template args from a compiled vote row.
 *
 * @param array $vote Compiled vote row.
 * @param array $context Render context.
 * @return array
 */
function fi_legislator_votes_prepare_card_data(array $vote, array $context = []): array {
	$gov = (string) ($context['gov'] ?? ($vote['gov'] ?? 'US'));
	$report_format = (string) ($context['report_format'] ?? 'scorecard');
	$meta = fi_legislator_votes_decode_array($vote['meta'] ?? []);

	$desc_arr = fi_vote_get_description($meta, 'scorecard');
	$description = $desc_arr['text'] ?? ($meta['description_short'] ?? '');

	$more_arr = fi_vote_get_description($meta, 'freedomindex');
	$text_more = $more_arr['text'] ?? ($meta['description_long'] ?? '');

	$cast = (string) ($vote['cast'] ?? 'X');
	$vote_format = fi_vote_format([
		'cast'           => $cast,
		'constitutional' => $vote['constitutional'] ?? '',
		'format'         => 'full',
	]);

	$cost_html = '';
	$cost_sentence = '';
	$cost_value = (string) ($meta['cost'] ?? '');
	if ($cost_value !== '') {
		$cost = function_exists('fi_vote_cost_format') ? fi_vote_cost_format($cost_value) : fi_vote_format_cost($cost_value);
		$cost_html = is_array($cost) ? (string) ($cost['html'] ?? '') : '';
		$cost_sentence = is_array($cost) ? (string) ($cost['sentence'] ?? '') : '';
	}

	$vote_id = (int) ($vote['id'] ?? ($vote['vote_id'] ?? 0));
	$url_vote = function_exists('fi_url_vote')
		? fi_url_vote($gov, $vote_id)
		: home_url('/' . strtolower($gov) . '/vote/' . $vote_id . '/');

	$vote_chamber = (string) ($vote['chamber'] ?? '');
	$chamber_label = (string) ($vote['chamber_label'] ?? '');
	if ($chamber_label === '' && function_exists('fi_chamber_label')) {
		$chamber_label = fi_chamber_label($gov, $vote_chamber);
	}

	$tags = $vote['tags'] ?? [];
	if (!is_array($tags)) {
		$tags = [];
	}

	$search_text = (string) ($vote['search_text'] ?? '');
	if ($search_text === '') {
		$search_text = strtolower(
			(string) ($vote['title'] ?? '') . ' ' .
			(string) ($vote['bill_number'] ?? '') . ' ' .
			wp_strip_all_tags((string) $description)
		);
	}

	return [
		'id'              => $vote_id,
		'gov'             => $gov,
		'title'           => (string) ($vote['title'] ?? ''),
		'text'            => (string) $description,
		'text_more'       => (string) $text_more,
		'tags'            => $tags,
		'bill_number'     => (string) ($vote['bill_number'] ?? ''),
		'bill_url'        => (string) ($meta['url_bill'] ?? ''),
		'constitutional'  => (string) ($vote['constitutional'] ?? ''),
		'date_voted'      => (string) ($vote['date_voted'] ?? ''),
		'date_formatted'  => (string) ($vote['date_formatted'] ?? fi_legislator_votes_format_date((string) ($vote['date_voted'] ?? ''))),
		'vote_format'     => $vote_format,
		'cost_html'       => $cost_html,
		'cost_sentence'   => $cost_sentence,
		'url_vote'        => $url_vote,
		'search_text'     => $search_text,
		'report_format'   => $report_format,
		'chamber_title'   => true,
		'chamber_label'   => $chamber_label,
		'show_cast'       => true,
		'cast'            => $cast,
		'modal_mode'      => 'page',
	];
}

/**
 * Build vote_groups navigation payload for client-side filtering.
 *
 * @param array $compiled Compiled legislator votes payload.
 * @param int $legislator_id Legislator ID.
 * @param mixed $freedom_score Legislator freedom score.
 * @return array
 */
function fi_legislator_votes_build_vote_groups(array $compiled, int $legislator_id, $freedom_score): array {
	$votes = $compiled['votes'] ?? [];
	$votes_cast = $compiled['votes_cast'] ?? [];
	$sessions_meta = $compiled['sessions_meta'] ?? [];
	$tag_rows = $compiled['tag_rows'] ?? [];

	$all_vote_ids = fi_legislator_votes_sort_ids_by_date(array_keys($votes_cast), $votes);

	$scoring = function_exists('fi_score_format') ? fi_score_format($freedom_score) : ['score' => $freedom_score, 'text' => '', 'badge' => '', 'button' => ''];
	$vote_groups = [
		'all' => [
			'menu'        => 'All Votes',
			'title'       => 'Complete Vote History',
			'subtitle'    => '',
			'content'     => null,
			'actions'     => ['search' => true],
			'count'       => count($all_vote_ids),
			'score'       => $scoring['score'] ?? null,
			'score_text'  => $scoring['text'] ?? '',
			'score_badge' => $scoring['badge'] ?? '',
			'votes'       => $all_vote_ids,
		],
		'tags'     => [],
		'sessions' => [],
	];

	foreach ($tag_rows as $tag) {
		$tag_id = (int) ($tag['id'] ?? 0);
		if ($tag_id <= 0) {
			continue;
		}
		$tag_scoring = function_exists('fi_score_format') ? fi_score_format($tag['score'] ?? null) : ['score' => null, 'text' => '', 'badge' => '', 'button' => ''];
		$vote_groups['tags'][$tag_id] = [
			'menu'         => (string) ($tag['name'] ?? ''),
			'title'        => 'Voting on ' . (string) ($tag['name'] ?? ''),
			'subtitle'     => null,
			'content'      => null,
			'actions'      => [
				'share' => true,
				'score' => $tag_scoring['button'] ?? '',
			],
			'count'        => (int) ($tag['vote_count'] ?? 0),
			'score'        => $tag_scoring['score'] ?? null,
			'score_text'   => $tag_scoring['text'] ?? '',
			'score_badge'  => $tag_scoring['badge'] ?? '',
			'votes'        => fi_legislator_votes_sort_ids_by_date((array) ($tag['votes'] ?? []), $votes),
		];
	}

	foreach ($sessions_meta as $session_id => $session) {
		$session_id = (int) $session_id;
		$session_scoring = function_exists('fi_score_format') ? fi_score_format($session['score'] ?? null) : ['score' => null, 'text' => '', 'badge' => '', 'button' => ''];
		$reports = [];

		foreach (($session['reports'] ?? []) as $report) {
			$report_id = (int) ($report['id'] ?? 0);
			if ($report_id <= 0) {
				continue;
			}
			$report_scoring = function_exists('fi_score_format') ? fi_score_format($report['score'] ?? null) : ['score' => null, 'text' => '', 'badge' => '', 'button' => ''];
			$actions = ['share' => true, 'score' => $report_scoring['button'] ?? ''];

			$payload = fi_legislator_votes_decode_array($report['payload'] ?? []);
			if (!empty($payload['report_pdf_url'])) {
				$actions['pdf'] = (string) $payload['report_pdf_url'];
			} else {
				$actions['pdfa'] = home_url('/legislator/' . $legislator_id . '/session/' . $session_id . '/report/' . $report_id . '/pdf/sca/');
				$actions['pdfb'] = home_url('/legislator/' . $legislator_id . '/session/' . $session_id . '/report/' . $report_id . '/pdf/scb/');
			}

			$content = null;
			if (!empty($report['content'])) {
				$content = wp_kses_post(wpautop((string) $report['content']));
			}

			$menu_title = !empty($report['title_menu']) ? (string) $report['title_menu'] : (string) ($report['title'] ?? '');
			$report_title = fi_report_title_reformat((string) ($session['gov'] ?? 'US'), (string) ($report['title'] ?? ''));

			$reports[] = [
				'id'          => $report_id,
				'menu'        => $menu_title,
				'title'       => $report_title,
				'subtitle'    => trim((string) ($session['gov'] ?? '') . ' ' . (string) ($session['chamber_label'] ?? '')),
				'content'     => $content,
				'format'      => (string) ($report['format'] ?? 'scorecard'),
				'actions'     => $actions,
				'score'       => $report_scoring['score'] ?? null,
				'score_text'  => $report_scoring['text'] ?? '',
				'score_badge' => $report_scoring['badge'] ?? '',
				'votes'       => array_values(array_map('intval', (array) ($report['votes'] ?? []))),
			];
		}

		$vote_groups['sessions'][$session_id] = [
			'menu'        => (string) ($session['session_name'] ?? ''),
			'title'       => (string) ($session['session_name'] ?? ''),
			'subtitle'    => trim((string) ($session['gov'] ?? '') . ' ' . (string) ($session['chamber_label'] ?? '') . ' ' . (string) ($session['chamber_title'] ?? '')),
			'content'     => null,
			'actions'     => [
				'share' => true,
				'score' => $session_scoring['button'] ?? '',
			],
			'score'       => $session_scoring['score'] ?? null,
			'score_text'  => $session_scoring['text'] ?? '',
			'score_badge' => $session_scoring['badge'] ?? '',
			'votes'       => fi_legislator_votes_sort_ids_by_date((array) ($session['votes'] ?? []), $votes),
			'reports'     => $reports,
		];
	}

	return $vote_groups;
}

/**
 * Compile full legislator vote payload for page load + client filtering.
 *
 * @param int $legislator_id Legislator ID.
 * @return array
 */
function fi_legislator_votes_query(int $legislator_id): array {
	global $wpdb;

	$legislator_id = absint($legislator_id);
	$empty = [
		'votes'         => [],
		'votes_cast'    => [],
		'vote_groups'   => ['all' => ['votes' => [], 'title' => 'Complete Vote History', 'actions' => ['search' => true]], 'tags' => [], 'sessions' => []],
		'sessions_meta' => [],
		'tag_rows'      => [],
		'all_tags'      => [],
		'tag_scores'    => [],
	];

	if ($legislator_id <= 0) {
		return $empty;
	}

	$legislator = fi_legislator_get($legislator_id);
	$sessions = fi_legislator_sessions_get_history($legislator_id);
	if (empty($sessions)) {
		return $empty;
	}

	$rollcall_rows = $wpdb->get_results($wpdb->prepare(
		"SELECT vote_id, `cast` FROM {$wpdb->prefix}fi_voterc WHERE legislator_id = %d",
		$legislator_id
	), ARRAY_A) ?: [];

	$votes_cast = [];
	foreach ($rollcall_rows as $row) {
		$vote_id = (int) ($row['vote_id'] ?? 0);
		if ($vote_id <= 0) {
			continue;
		}
		$votes_cast[$vote_id] = fi_legislator_votes_normalize_cast((string) ($row['cast'] ?? ''));
	}

	$votes_cast_ids = array_keys($votes_cast);
	if (empty($votes_cast_ids)) {
		return $empty;
	}

	$placeholders = implode(',', array_fill(0, count($votes_cast_ids), '%d'));
	$vote_rows = $wpdb->get_results($wpdb->prepare(
		"SELECT v.*, s.name AS session_name
		 FROM {$wpdb->prefix}fi_votes v
		 LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
		 WHERE v.id IN ($placeholders)
		   AND v.status = 'publish'
		 ORDER BY v.date_voted DESC, v.id DESC",
		...$votes_cast_ids
	), ARRAY_A) ?: [];

	$tags_by_vote = [];
	foreach (fi_legislator_votes_get_tags_by_vote_ids($votes_cast_ids) as $tag_row) {
		$tag_row = is_object($tag_row) ? (array) $tag_row : $tag_row;
		$vote_id = (int) ($tag_row['vote_id'] ?? 0);
		if ($vote_id <= 0) {
			continue;
		}
		$tags_by_vote[$vote_id][] = [
			'id'   => (int) ($tag_row['tag_id'] ?? 0),
			'name' => (string) ($tag_row['tag_name'] ?? ''),
		];
	}

	$votes = [];
	$scoring_map = [];

	foreach ($vote_rows as $vote_row) {
		$vote_id = (int) ($vote_row['id'] ?? 0);
		if ($vote_id <= 0) {
			continue;
		}

		$meta = fi_legislator_votes_decode_array($vote_row['meta'] ?? []);
		unset($meta['legacy'], $meta['legiscan'], $meta['legiscan_rollcall_audit'], $meta['legiscan_session_id']);

		$cast = $votes_cast[$vote_id] ?? 'X';
		$constitutional = (string) ($vote_row['constitutional'] ?? '');
		$counted = null;
		$matched = null;
		if ($cast !== 'X' && in_array($constitutional, ['Y', 'N'], true)) {
			$counted = true;
			$matched = ($cast === $constitutional);
		}

		$gov = (string) ($vote_row['gov'] ?? 'US');
		$chamber = (string) ($vote_row['chamber'] ?? '');
		$desc_arr = fi_vote_get_description($meta, 'scorecard');
		$description = $desc_arr['text'] ?? ($meta['description_short'] ?? '');
		$more_arr = fi_vote_get_description($meta, 'freedomindex');
		$search_description = $more_arr['text'] ?? ($meta['description_long'] ?? $description);

		$vote_entry = [
			'id'             => $vote_id,
			'vote_id'        => $vote_id,
			'session_id'     => (int) ($vote_row['session_id'] ?? 0),
			'session_name'   => (string) ($vote_row['session_name'] ?? ''),
			'gov'            => $gov,
			'chamber'        => $chamber,
			'chamber_label'  => function_exists('fi_chamber_label') ? fi_chamber_label($gov, $chamber) : $chamber,
			'title'          => (string) ($vote_row['title'] ?? ''),
			'bill_number'    => (string) ($vote_row['bill_number'] ?? ''),
			'constitutional' => $constitutional,
			'date_voted'     => (string) ($vote_row['date_voted'] ?? ''),
			'date_formatted' => fi_legislator_votes_format_date((string) ($vote_row['date_voted'] ?? '')),
			'meta'           => $meta,
			'tags'           => $tags_by_vote[$vote_id] ?? [],
			'cast'           => $cast,
			'matched'        => $matched,
			'counted'        => $counted,
			'search_text'    => strtolower(
				(string) ($vote_row['title'] ?? '') . ' ' .
				(string) ($vote_row['bill_number'] ?? '') . ' ' .
				wp_strip_all_tags((string) $search_description)
			),
		];

		$votes[$vote_id] = $vote_entry;
		$scoring_map[$vote_id] = [
			'cast'           => $cast,
			'matched'        => $matched,
			'counted'        => $counted,
			'constitutional' => $constitutional,
		];
	}

	$sessions_meta = [];
	foreach ($sessions as $session) {
		$session_id = (int) ($session['session_id'] ?? 0);
		$chamber = (string) ($session['chamber'] ?? '');
		if ($session_id <= 0 || $chamber === '') {
			continue;
		}

		$query_session_ids = fi_legislator_votes_child_session_ids($session_id);
		$session_vote_ids = [];
		foreach ($votes as $vote_id => $vote_entry) {
			if (in_array((int) $vote_entry['session_id'], $query_session_ids, true) && ($vote_entry['chamber'] ?? '') === $chamber) {
				$session_vote_ids[] = $vote_id;
			}
		}

		$session_vote_ids = fi_legislator_votes_sort_ids_by_date($session_vote_ids, $votes);

		$session_score = fi_legislator_votes_calc_score($scoring_map, $session_vote_ids);
		$session_name = (string) ($session['session_name'] ?? '');
		if ($session_name !== '' && strtoupper((string) ($session['gov'] ?? '')) === 'US' && stripos($session_name, 'Congress') === false) {
			$session_name .= ' Congress';
		}

		$reports_raw = fi_reports_get([
			'session_id' => $session_id,
			'status'     => 'publish',
		]);
		$reports_raw = is_array($reports_raw) ? fi_reports_sort_by_format((string) ($session['gov'] ?? 'US'), $reports_raw) : [];
		$compiled_reports = [];

		foreach ($reports_raw as $report) {
			$report = is_object($report) ? (array) $report : $report;
			$payload = fi_legislator_votes_decode_array($report['payload_json'] ?? '{}');
			$report_vote_ids = fi_legislator_votes_extract_report_vote_ids($payload, $chamber);
			$report_score = fi_legislator_votes_calc_score($scoring_map, $report_vote_ids);

			$compiled_reports[] = [
				'id'         => (int) ($report['id'] ?? 0),
				'title'      => (string) ($report['title'] ?? ''),
				'title_menu' => (string) ($report['title_menu'] ?? ''),
				'format'     => (string) ($report['format'] ?? 'scorecard'),
				'content'    => (string) ($payload['content'] ?? ''),
				'payload'    => $payload,
				'score'      => $report_score['score'],
				'score_data' => $report_score,
				'votes'      => $report_vote_ids,
			];
		}

		$sessions_meta[$session_id] = array_merge($session, [
			'session_name' => $session_name,
			'votes'        => $session_vote_ids,
			'score'        => $session_score['score'],
			'score_data'   => $session_score,
			'reports'      => $compiled_reports,
		]);
	}

	$tag_rows = [];
	if (!empty($votes_cast_ids)) {
		$tag_placeholders = implode(',', array_fill(0, count($votes_cast_ids), '%d'));
		$tag_rows = $wpdb->get_results($wpdb->prepare(
			"SELECT t.id, t.name, COUNT(DISTINCT vt.vote_id) AS vote_count
			 FROM {$wpdb->prefix}fi_taxonomy t
			 INNER JOIN {$wpdb->prefix}fi_vote_tags vt ON t.id = vt.tag_id
			 WHERE vt.vote_id IN ($tag_placeholders)
			   AND t.taxonomy = 'tag'
			 GROUP BY t.id, t.name
			 ORDER BY t.name ASC",
			...$votes_cast_ids
		), ARRAY_A) ?: [];

		$link_rows = $wpdb->get_results($wpdb->prepare(
			"SELECT tag_id, vote_id
			 FROM {$wpdb->prefix}fi_vote_tags
			 WHERE vote_id IN ($tag_placeholders)
			 ORDER BY tag_id ASC, vote_id ASC",
			...$votes_cast_ids
		), ARRAY_A) ?: [];

		$tag_votes_map = [];
		foreach ($link_rows as $link) {
			$tag_id = (int) ($link['tag_id'] ?? 0);
			$tag_votes_map[$tag_id][] = (int) ($link['vote_id'] ?? 0);
		}

		foreach ($tag_rows as &$tag_row) {
			$tag_id = (int) ($tag_row['id'] ?? 0);
			$tag_vote_ids = fi_legislator_votes_sort_ids_by_date($tag_votes_map[$tag_id] ?? [], $votes);
			$tag_score = fi_legislator_votes_calc_score($scoring_map, $tag_vote_ids);
			$tag_row['votes'] = $tag_vote_ids;
			$tag_row['score'] = $tag_score['score'];
			$tag_row['score_data'] = $tag_score;
			$tag_row['grade'] = is_numeric($tag_score['score']) && function_exists('fi_score_calculate_grade')
				? fi_score_calculate_grade((int) $tag_score['score'])
				: null;
		}
		unset($tag_row);
	}

	$all_tags = array_map(static function ($tag) {
		return [
			'id'         => (int) ($tag['id'] ?? 0),
			'name'       => (string) ($tag['name'] ?? ''),
			'vote_count' => (int) ($tag['vote_count'] ?? 0),
			'score'      => $tag['score'] ?? null,
			'grade'      => $tag['grade'] ?? null,
			'scored'     => (int) ($tag['score_data']['counted'] ?? 0),
		];
	}, $tag_rows);

	usort($all_tags, static fn($a, $b) => $b['vote_count'] <=> $a['vote_count']);
	$tag_scores = array_slice($all_tags, 0, 8);

	$compiled = [
		'votes'         => $votes,
		'votes_cast'    => $votes_cast,
		'sessions_meta' => $sessions_meta,
		'tag_rows'      => $tag_rows,
		'all_tags'      => $all_tags,
		'tag_scores'    => $tag_scores,
	];

	$compiled['vote_groups'] = fi_legislator_votes_build_vote_groups(
		$compiled,
		$legislator_id,
		$legislator['score'] ?? null
	);

	return $compiled;
}

/**
 * Get cached legislator vote payload (30-day file cache).
 *
 * @param int $legislator_id Legislator ID.
 * @return array
 */
function fi_legislator_votes_cache_get(int $legislator_id): array {
	$legislator_id = absint($legislator_id);
	if ($legislator_id <= 0) {
		return [];
	}

	$cache_key = 'legislator/' . $legislator_id . '-votes';
	$cached = fi_cache($cache_key, '', 30);
	if ($cached !== false && is_array($cached)) {
		return $cached;
	}

	$data = fi_legislator_votes_query($legislator_id);

	if (!defined('FI_DEV') || !FI_DEV) {
		fi_cache($cache_key, $data, 30);
	}

	return $data;
}