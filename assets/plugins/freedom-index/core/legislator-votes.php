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
}
add_action('init', 'fi_legislator_votes_init', 10);

/**
 * Handle vote saved and invalidate session cache.
 *
 * @param int $vote_id Vote ID.
 * @param array $data Vote data.
 * @return void
 */
function fi_legislator_votes_on_vote_saved(int $vote_id, array $data): void {
	if (!empty($data['session_id'])) {
		fi_session_votes_cache_invalidate((int) $data['session_id']);
	}
}

/**
 * Handle rollcall saved and invalidate session cache.
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
function fi_legislator_votes_get(int $legislator_id, string $chamber): array {
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
function fi_legislator_tags_get(int $legislator_id, string $chamber): array {
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