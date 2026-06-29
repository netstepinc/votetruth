<?php
/**
 * Freedom Index — Legislator Votes Data Management
 *
 * Two cache layers:
 *   1. Session cache     — transient fi_session_votes_{id}  (reports pages + other consumers)
 *   2. Legislator payload — fi_cache  legislator/{id}-votes  (legislator profile pages, 30 days)
 *
 * Notes:
 * - Slug fields are intentionally omitted from returned arrays.
 * - fi_cache writes are TEMP DISABLED in cache.php for dev (reads always miss → fresh query).
 */

if (!defined('ABSPATH')) exit;

// ─────────────────────────────────────────────────────────────────────────────
// Hook Init + Cache Invalidation
// ─────────────────────────────────────────────────────────────────────────────

function fi_legislator_votes_init(): void {
	add_action('fi_vote_saved',     'fi_legislator_votes_on_vote_saved',    10, 2);
	add_action('fi_rollcall_saved', 'fi_legislator_votes_on_rollcall_saved', 10, 2);
	add_action('fi_report_saved',   'fi_legislator_votes_on_report_saved',   10, 2);
}
add_action('init', 'fi_legislator_votes_init', 10);

/** Dump legislator-level vote compile cache. */
function fi_legislator_votes_cache_invalidate(int $legislator_id): void {
	$legislator_id = absint($legislator_id);
	if ($legislator_id > 0) {
		fi_cache('legislator/' . $legislator_id . '-votes', 'DUMP');
	}
}

/** Dump legislator vote caches for every legislator assigned to a session. */
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

	foreach ((array) $legislator_ids as $lid) {
		fi_legislator_votes_cache_invalidate((int) $lid);
	}
}

/** Invalidate session transient + legislator file cache when a vote is saved. */
function fi_legislator_votes_on_vote_saved(int $vote_id, array $data): void {
	if (!empty($data['session_id'])) {
		fi_session_votes_cache_invalidate((int) $data['session_id']);
	}

	global $wpdb;
	$legislator_ids = $wpdb->get_col($wpdb->prepare(
		"SELECT DISTINCT legislator_id FROM {$wpdb->prefix}fi_voterc WHERE vote_id = %d",
		$vote_id
	));
	foreach ((array) $legislator_ids as $lid) {
		fi_legislator_votes_cache_invalidate((int) $lid);
	}
}

/** Invalidate session transient + legislator file cache when a rollcall is saved. */
function fi_legislator_votes_on_rollcall_saved(int $rollcall_id, array $data): void {
	if (empty($data['vote_id'])) {
		return;
	}

	$vote            = fi_vote_get((int) $data['vote_id']);
	$vote_session_id = (int) ($vote['session_id'] ?? 0);
	if ($vote_session_id > 0) {
		fi_session_votes_cache_invalidate($vote_session_id);
	}

	if (!empty($data['legislator_id'])) {
		fi_legislator_votes_cache_invalidate((int) $data['legislator_id']);
	}
}

/** Dump all legislator caches in a session when a report is saved. */
function fi_legislator_votes_on_report_saved(int $report_id, array $data): void {
	if (!empty($data['session_id'])) {
		fi_legislator_votes_invalidate_session_legislators((int) $data['session_id']);
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// Shared Helper
// ─────────────────────────────────────────────────────────────────────────────

/** Decode a JSON meta/payload value to array; pass-through if already an array. */
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

// ─────────────────────────────────────────────────────────────────────────────
// Session Cache  (reports pages + other consumers)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Format a vote row for session cache storage.
 * Only used by fi_session_votes_cache_build().
 */
function fi_legislator_votes_format_vote(array $vote): array {
	$meta = fi_legislator_votes_decode_array($vote['meta'] ?? []);
	return [
		'vote_id'            => (int) ($vote['id'] ?? 0),
		'title'              => $vote['title'] ?? '',
		'bill_number'        => $vote['bill_number'] ?? ($meta['bill_number'] ?? $meta['bill_key'] ?? null),
		'date_voted'         => $vote['date_voted'] ?? null,
		'constitutional'     => $vote['constitutional'] ?? '',
		'chamber'            => $vote['chamber'] ?? '',
		'gov'                => $vote['gov'] ?? '',
		'session_id'         => (int) ($vote['session_id'] ?? 0),
		'description_short'  => $meta['description_short'] ?? $meta['text_scorecard'] ?? null,
		'description_medium' => $meta['description_medium'] ?? $meta['text_freedomindex'] ?? null,
		'description_long'   => $meta['description_long'] ?? $meta['text_scorecard_more'] ?? null,
		'url_source'         => $meta['url'] ?? $meta['url_source'] ?? null,
		'url_bill'           => $meta['url_bill'] ?? null,
		'url_rollcall'       => $meta['url_rollcall'] ?? null,
		'meta'               => $meta,
	];
}

/**
 * Get or build session vote cache.
 * Cache key: fi_session_votes_{session_id} (transient, 30 days).
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

/** Build comprehensive session vote cache. */
function fi_session_votes_cache_build(int $session_id): array {
	$session_id = absint($session_id);
	$session    = fi_session_get($session_id);
	if (!$session) {
		return [];
	}

	$votes = fi_votes_get_by_session($session_id, [
		'status'   => 'publish',
		'orderby'  => 'date_voted',
		'order'    => 'ASC',
		'per_page' => -1,
	]);

	$formatted_votes = [];
	$vote_ids        = [];

	foreach ($votes as $vote) {
		if (!in_array($vote['constitutional'] ?? '', ['Y', 'N'], true)) {
			continue;
		}
		$vote_ids[]        = (int) ($vote['id'] ?? 0);
		$formatted_votes[] = fi_legislator_votes_format_vote($vote);
	}

	$rollcalls = [];
	if (!empty($vote_ids)) {
		foreach (fi_rollcalls_get_by_vote_ids($vote_ids) as $rc) {
			$vote_id       = (int) ($rc['vote_id'] ?? 0);
			$legislator_id = (int) ($rc['legislator_id'] ?? 0);
			if ($vote_id <= 0 || $legislator_id <= 0) {
				continue;
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
		foreach (fi_vote_tags_get_tags_by_vote_ids($vote_ids) as $tag) {
			$vote_id = (int) ($tag['vote_id'] ?? 0);
			if ($vote_id <= 0) {
				continue;
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

/** Invalidate a single session vote transient. */
function fi_session_votes_cache_invalidate(int $session_id): bool {
	$session_id = absint($session_id);
	return $session_id > 0 ? delete_transient('fi_session_votes_' . $session_id) : false;
}

/** Invalidate all session vote transients. */
function fi_session_votes_cache_invalidate_all(): int {
	global $wpdb;

	$sessions = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}fi_sessions");
	$deleted  = 0;
	foreach ($sessions as $session_id) {
		if (fi_session_votes_cache_invalidate((int) $session_id)) {
			$deleted++;
		}
	}
	return $deleted;
}

// ─────────────────────────────────────────────────────────────────────────────
// Legislator Compile Pass  (legislator profile pages)
// ─────────────────────────────────────────────────────────────────────────────

/** Normalize legislator cast to Y, N, or X (X = any non-Y/N). */
function fi_legislator_votes_normalize_cast(string $cast): string {
	$cast = strtoupper(trim($cast));
	return in_array($cast, ['Y', 'N'], true) ? $cast : 'X';
}

/** Resolve parent session ID + published child session IDs for vote queries. */
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

	$ids   = array_map('intval', (array) $children);
	$ids[] = $parent_session_id;
	return array_values(array_unique($ids));
}

/**
 * Score a batch of vote IDs from a precomputed scoring_map.
 * Returns score 'NA' when fewer than half the votes are scoreable.
 */
function fi_legislator_votes_calc_score(array $scoring_map, array $vote_ids): array {
	$vote_ids = array_values(array_unique(array_map('intval', $vote_ids)));
	$total    = count($vote_ids);
	$counted  = 0;
	$matched  = 0;

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

	$half  = (int) round($total / 2, 0);
	$score = ($counted < $half) ? 'NA' : (int) round(($matched / $counted) * 100, 0);
	return ['total' => $total, 'counted' => $counted, 'matched' => $matched, 'score' => $score];
}

/** Sort vote IDs by date_voted DESC, tie-break by vote ID DESC. */
function fi_legislator_votes_sort_ids_by_date(array $vote_ids, array $votes): array {
	$vote_ids = array_values(array_unique(array_map('intval', $vote_ids)));
	usort($vote_ids, static function ($a, $b) use ($votes) {
		$da = strtotime((string) ($votes[$a]['date_voted'] ?? '')) ?: 0;
		$db = strtotime((string) ($votes[$b]['date_voted'] ?? '')) ?: 0;
		return $db !== $da ? $db <=> $da : $b <=> $a;
	});
	return $vote_ids;
}

function fi_legislator_votes_format_date(string $date_voted): string {
	if ($date_voted === '') {
		return '';
	}
	$ts = strtotime($date_voted);
	return $ts !== false ? date('n/j/Y', $ts) : $date_voted;
}

/**
 * Extract ordered report vote IDs for a chamber from a report payload.
 * Prefers *_order field over plain *_ids when present.
 */
function fi_legislator_votes_extract_report_vote_ids(array $payload, string $chamber): array {
	$chamber = strtoupper($chamber);
	if ($chamber === 'S') {
		$src = !empty($payload['votes_s_order']) ? $payload['votes_s_order'] : ($payload['votes_s'] ?? []);
	} else {
		$src = !empty($payload['votes_h_order']) ? $payload['votes_h_order'] : ($payload['votes_h'] ?? []);
	}
	return is_array($src) ? array_values(array_filter(array_map('intval', $src))) : [];
}


/**
 * Build vote_groups navigation payload for client-side JS filtering.
 * Called once per compile pass; result is cached in the legislator fi_cache file.
 */
function fi_legislator_votes_build_vote_groups(array $compiled, int $legislator_id, $freedom_score): array {
	$votes         = $compiled['votes'] ?? [];
	$votes_cast    = $compiled['votes_cast'] ?? [];
	$sessions_meta = $compiled['sessions_meta'] ?? [];
	$tag_rows      = $compiled['tag_rows'] ?? [];

	$all_vote_ids = fi_legislator_votes_sort_ids_by_date(array_keys($votes_cast), $votes);
	$scoring      = function_exists('fi_score_format')
		? fi_score_format($freedom_score)
		: ['score' => $freedom_score, 'text' => '', 'badge' => '', 'button' => ''];

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
		$ts = function_exists('fi_score_format')
			? fi_score_format($tag['score'] ?? null)
			: ['score' => null, 'text' => '', 'badge' => '', 'button' => ''];

		$vote_groups['tags'][$tag_id] = [
			'menu'        => (string) ($tag['name'] ?? ''),
			'title'       => 'Votes on ' . (string) ($tag['name'] ?? ''),
			'description' => (string) ($tag['description'] ?? ''),
			'subtitle'    => null,
			'content'     => null,
			'actions'     => ['share' => true, 'score' => $ts['button'] ?? ''],
			'count'       => (int) ($tag['vote_count'] ?? 0),
			'score'       => $ts['score'] ?? null,
			'score_text'  => $ts['text'] ?? '',
			'score_badge' => $ts['badge'] ?? '',
			'votes'       => fi_legislator_votes_sort_ids_by_date((array) ($tag['votes'] ?? []), $votes),
		];
	}

	foreach ($sessions_meta as $session_id => $session) {
		$session_id = (int) $session_id;
		$ss = function_exists('fi_score_format')
			? fi_score_format($session['score'] ?? null)
			: ['score' => null, 'text' => '', 'badge' => '', 'button' => ''];
		$reports = [];

		foreach (($session['reports'] ?? []) as $report) {
			$report_id = (int) ($report['id'] ?? 0);
			if ($report_id <= 0) {
				continue;
			}
			$rs = function_exists('fi_score_format')
				? fi_score_format($report['score'] ?? null)
				: ['score' => null, 'text' => '', 'badge' => '', 'button' => ''];

			$actions = ['share' => true, 'score' => $rs['button'] ?? ''];
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

			$menu_title   = !empty($report['title_menu']) ? (string) $report['title_menu'] : (string) ($report['title'] ?? '');
			$report_title = fi_report_title_reformat((string) ($session['gov'] ?? 'US'), (string) ($report['title'] ?? ''));

			$reports[] = [
				'id'          => $report_id,
				'menu'        => $menu_title,
				'title'       => $report_title,
				'subtitle'    => trim(fi_gov_name((string) ($session['gov'] ?? '')) . ': ' . fi_chamber_label((string) ($session['gov'] ?? ''), (string) ($session['chamber'] ?? ''))),
				'content'     => $content,
				'format'      => (string) ($report['format'] ?? 'scorecard'),
				'actions'     => $actions,
				'score'       => $rs['score'] ?? null,
				'score_text'  => $rs['text'] ?? '',
				'score_badge' => $rs['badge'] ?? '',
				'votes'       => array_values(array_map('intval', (array) ($report['votes'] ?? []))),
			];
		}

		$vote_groups['sessions'][$session_id] = [
			'menu'        => (string) ($session['session_name'] ?? ''),
			'title'       => (string) ($session['session_name'] ?? ''),
			'subtitle'    => trim(fi_gov_name((string) ($session['gov'] ?? '')) . ': ' . fi_chamber_label((string) ($session['gov'] ?? ''), (string) ($session['chamber'] ?? ''))),
			'content'     => null,
			'actions'     => ['share' => true, 'score' => $ss['button'] ?? ''],
			'score'       => $ss['score'] ?? null,
			'score_text'  => $ss['text'] ?? '',
			'score_badge' => $ss['badge'] ?? '',
			'votes'       => fi_legislator_votes_sort_ids_by_date((array) ($session['votes'] ?? []), $votes),
			'reports'     => $reports,
		];
	}

	return $vote_groups;
}

/**
 * Compile full legislator vote payload for page load + client-side filtering.
 *
 * Single DB pass:
 *   1. All rollcalls for legislator → cast index (votes_cast)
 *   2. Hydrate vote rows; compute matched/counted per vote (scoring_map)
 *   3. Tags for all voted votes (tags_by_vote)
 *   4. Per parent session: child session IDs, vote ID list, report scoring
 *   5. Tag rows: scores + vote ID lists
 *   6. Build vote_groups for JS nav
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
	$sessions   = fi_legislator_sessions_get_history($legislator_id);
	if (empty($sessions)) {
		return $empty;
	}

	// Step 1: All rollcasts for this legislator → cast index.
	$rollcall_rows = $wpdb->get_results($wpdb->prepare(
		"SELECT vote_id, `cast` FROM {$wpdb->prefix}fi_voterc WHERE legislator_id = %d",
		$legislator_id
	), ARRAY_A) ?: [];

	$votes_cast = [];
	foreach ($rollcall_rows as $row) {
		$vote_id = (int) ($row['vote_id'] ?? 0);
		if ($vote_id > 0) {
			$votes_cast[$vote_id] = fi_legislator_votes_normalize_cast((string) ($row['cast'] ?? ''));
		}
	}

	$votes_cast_ids = array_keys($votes_cast);
	if (empty($votes_cast_ids)) {
		return $empty;
	}

	// Step 2: Hydrate vote rows; compute matched/counted per vote.
	$placeholders = implode(',', array_fill(0, count($votes_cast_ids), '%d'));
	$vote_rows    = $wpdb->get_results($wpdb->prepare(
		"SELECT v.*, s.name AS session_name
		 FROM {$wpdb->prefix}fi_votes v
		 LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
		 WHERE v.id IN ($placeholders) AND v.status = 'publish'
		 ORDER BY v.date_voted DESC, v.id DESC",
		...$votes_cast_ids
	), ARRAY_A) ?: [];

	// Step 3: Tags for all voted votes — index by vote_id.
	$tags_by_vote = [];
	foreach (fi_vote_tags_get_tags_by_vote_ids($votes_cast_ids) as $tag_row) {
		$vote_id = (int) ($tag_row['vote_id'] ?? 0);
		if ($vote_id <= 0) {
			continue;
		}
		$tags_by_vote[$vote_id][] = [
			'id'   => (int) ($tag_row['tag_id'] ?? 0),
			'name' => (string) ($tag_row['tag_name'] ?? ''),
		];
	}

	// Step 4: Build compiled votes map + scoring_map.
	$votes       = [];
	$scoring_map = [];

	foreach ($vote_rows as $vote_row) {
		$vote_id = (int) ($vote_row['id'] ?? 0);
		if ($vote_id <= 0) {
			continue;
		}

		$meta = fi_legislator_votes_decode_array($vote_row['meta'] ?? []);
		unset($meta['legacy'], $meta['legiscan'], $meta['legiscan_rollcall_audit'], $meta['legiscan_session_id']);

		$cast           = $votes_cast[$vote_id] ?? 'X';
		$constitutional = (string) ($vote_row['constitutional'] ?? '');
		$counted        = null;
		$matched        = null;
		if ($cast !== 'X' && in_array($constitutional, ['Y', 'N'], true)) {
			$counted = true;
			$matched = ($cast === $constitutional);
		}

		$gov     = (string) ($vote_row['gov'] ?? 'US');
		$chamber = (string) ($vote_row['chamber'] ?? '');

		$desc_arr    = fi_vote_get_description($meta, 'scorecard');
		$description = $desc_arr['text'] ?? ($meta['description_short'] ?? '');
		$more_arr    = fi_vote_get_description($meta, 'freedomindex');
		$text_more   = $more_arr['text'] ?? ($meta['description_long'] ?? '');
		$search_desc = $text_more ?: $description;

		// Pre-compute JS payload display fields — avoids render-time transform on page load.
		$url_vote = function_exists('fi_url_vote')
			? fi_url_vote($gov, $vote_id)
			: home_url('/' . strtolower($gov) . '/vote/' . $vote_id . '/');

		$cost_badge = $cost_badge_class = $cost_sentence = '';
		$cost_value = (string) ($meta['cost'] ?? '');
		if ($cost_value !== '') {
			$compact          = fi_vote_cost_compact_badge($cost_value);
			$cost_badge       = $compact['badge'];
			$cost_badge_class = $compact['class'];
			$cost_fmt         = fi_vote_format_cost($cost_value);
			$cost_sentence    = is_array($cost_fmt) ? (string) ($cost_fmt['sentence'] ?? '') : '';
		}

		// Vote counts come from Legiscan votes_yea / votes_nay.
		$votes_for     = isset($meta['votes_yea']) ? (string) (int) $meta['votes_yea'] : '';
		$votes_against = isset($meta['votes_nay']) ? (string) (int) $meta['votes_nay'] : '';

		$votes[$vote_id] = [
			'id'               => $vote_id,
			'vote_id'          => $vote_id,
			'session_id'       => (int) ($vote_row['session_id'] ?? 0),
			'session_name'     => (string) ($vote_row['session_name'] ?? ''),
			'gov'              => $gov,
			'chamber'          => $chamber,
			'chamber_label'    => function_exists('fi_chamber_label') ? fi_chamber_label($gov, $chamber) : $chamber,
			'title'            => (string) ($vote_row['title'] ?? ''),
			'bill_number'      => (string) ($vote_row['bill_number'] ?? ''),
			'bill_url'         => (string) ($meta['url_bill'] ?? ''),
		'rollcall_number'  => (string) ($vote_row['rollcall_number'] ?? ''),
			'constitutional'   => $constitutional,
			'date_voted'       => (string) ($vote_row['date_voted'] ?? ''),
			'date_formatted'   => fi_legislator_votes_format_date((string) ($vote_row['date_voted'] ?? '')),
			'text'             => $description,
			'text_more'        => $text_more,
			'impact_summary'   => (string) ($meta['impact_summary'] ?? ''),
			'vote_outcome'     => ($votes_for !== '' && $votes_against !== '') ? ($votes_for > $votes_against ? '1' : ($votes_for < $votes_against ? '0' : '')) : '',
			'votes_yea'        => $votes_for,
			'votes_nay'        => $votes_against,
			'citation'         => $meta['citation'] ?? [],
			'cost_value'       => (string) ($meta['cost'] ?? ''),
			'url_vote'         => $url_vote,
			'cost_badge'       => $cost_badge,
			'cost_badge_class' => $cost_badge_class,
			'cost_sentence'    => $cost_sentence,
			'is_match'         => $matched === true ? 1 : 0,
			'is_no_vote'       => $cast === 'X' ? 1 : 0,
			'meta'             => $meta,
			'tags'             => $tags_by_vote[$vote_id] ?? [],
			'cast'             => $cast,
			'matched'          => $matched,
			'counted'          => $counted,
			'search_text'      => strtolower(
				(string) ($vote_row['title'] ?? '') . ' ' .
				(string) ($vote_row['bill_number'] ?? '') . ' ' .
				wp_strip_all_tags((string) $search_desc)
			),
		];

		$scoring_map[$vote_id] = [
			'cast'           => $cast,
			'matched'        => $matched,
			'counted'        => $counted,
			'constitutional' => $constitutional,
		];
	}

	// Step 5: Per parent session — vote IDs, report scoring, report compile.
	$sessions_meta = [];

	foreach ($sessions as $session) {
		$session_id = (int) ($session['session_id'] ?? 0);
		$chamber    = (string) ($session['chamber'] ?? '');
		if ($session_id <= 0 || $chamber === '') {
			continue;
		}

		$query_session_ids = fi_legislator_votes_child_session_ids($session_id);
		$session_vote_ids  = [];
		foreach ($votes as $vote_id => $vote_entry) {
			if (
				in_array((int) $vote_entry['session_id'], $query_session_ids, true) &&
				($vote_entry['chamber'] ?? '') === $chamber
			) {
				$session_vote_ids[] = $vote_id;
			}
		}
		$session_vote_ids = fi_legislator_votes_sort_ids_by_date($session_vote_ids, $votes);
		$session_score    = fi_legislator_votes_calc_score($scoring_map, $session_vote_ids);

		$session_name = (string) ($session['session_name'] ?? '');
		if ($session_name !== '' && strtoupper((string) ($session['gov'] ?? '')) === 'US' && stripos($session_name, 'Congress') === false) {
			$session_name .= ' Congress';
		}

		$reports_raw = fi_reports_get(['session_id' => $session_id, 'status' => 'publish']);
		$reports_raw = is_array($reports_raw) ? fi_reports_sort_by_format((string) ($session['gov'] ?? 'US'), $reports_raw) : [];

		$compiled_reports = [];
		foreach ($reports_raw as $report) {
			$report     = is_array($report) ? $report : (array) $report;
			$payload    = fi_legislator_votes_decode_array($report['payload_json'] ?? '{}');
			$r_vote_ids = fi_legislator_votes_extract_report_vote_ids($payload, $chamber);
			$r_score    = fi_legislator_votes_calc_score($scoring_map, $r_vote_ids);

			$compiled_reports[] = [
				'id'         => (int) ($report['id'] ?? 0),
				'title'      => (string) ($report['title'] ?? ''),
				'title_menu' => (string) ($report['title_menu'] ?? ''),
				'format'     => (string) ($report['format'] ?? 'scorecard'),
				'content'    => (string) ($payload['content'] ?? ''),
				'payload'    => $payload,
				'score'      => $r_score['score'],
				'score_data' => $r_score,
				'votes'      => $r_vote_ids,
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

	// Step 6: Tag rows — scores + vote ID lists.
	$tag_rows = [];
	if (!empty($votes_cast_ids)) {
		$tag_placeholders = implode(',', array_fill(0, count($votes_cast_ids), '%d'));

		$tag_rows = $wpdb->get_results($wpdb->prepare(
			"SELECT t.id, t.name, t.description, COUNT(DISTINCT vt.vote_id) AS vote_count
			 FROM {$wpdb->prefix}fi_taxonomy t
			 INNER JOIN {$wpdb->prefix}fi_vote_tags vt ON t.id = vt.tag_id
			 WHERE vt.vote_id IN ($tag_placeholders) AND t.taxonomy = 'tag'
			 GROUP BY t.id, t.name, t.description ORDER BY t.name ASC",
			...$votes_cast_ids
		), ARRAY_A) ?: [];

		$link_rows = $wpdb->get_results($wpdb->prepare(
			"SELECT tag_id, vote_id FROM {$wpdb->prefix}fi_vote_tags
			 WHERE vote_id IN ($tag_placeholders)
			 ORDER BY tag_id ASC, vote_id ASC",
			...$votes_cast_ids
		), ARRAY_A) ?: [];

		$tag_votes_map = [];
		foreach ($link_rows as $link) {
			$tag_votes_map[(int) ($link['tag_id'] ?? 0)][] = (int) ($link['vote_id'] ?? 0);
		}

		foreach ($tag_rows as &$tag_row) {
			$tag_id    = (int) ($tag_row['id'] ?? 0);
			$tag_vids  = fi_legislator_votes_sort_ids_by_date($tag_votes_map[$tag_id] ?? [], $votes);
			$tag_score = fi_legislator_votes_calc_score($scoring_map, $tag_vids);

			$tag_row['votes']      = $tag_vids;
			$tag_row['score']      = $tag_score['score'];
			$tag_row['score_data'] = $tag_score;
			$tag_row['grade']      = is_numeric($tag_score['score']) && function_exists('fi_score_calculate_grade')
				? fi_score_calculate_grade((int) $tag_score['score'])
				: null;
		}
		unset($tag_row);
	}

	$all_tags = array_map(static fn($t) => [
		'id'          => (int) ($t['id'] ?? 0),
		'name'        => (string) ($t['name'] ?? ''),
		'description' => (string) ($t['description'] ?? ''),
		'vote_count'  => (int) ($t['vote_count'] ?? 0),
		'score'       => $t['score'] ?? null,
		'grade'       => $t['grade'] ?? null,
		'scored'      => (int) ($t['score_data']['counted'] ?? 0),
	], $tag_rows);

	usort($all_tags, static fn($a, $b) => $b['vote_count'] <=> $a['vote_count']);
	$tag_scores = array_slice($all_tags, 0, 8);

	// Step 7: Pre-render card HTML — single source of truth (vote-card.php); JS reads vote.card_html.
	if (function_exists('fi_get_template_html') && !empty($votes)) {
		foreach ($votes as $vote_id => &$vote) {
			$vote['card_html'] = fi_get_template_html('vote-card', [
				'id'               => $vote_id,
				'gov'              => $vote['gov'],
				'title'            => $vote['title'],
				'text'             => $vote['text'],
				'text_more'        => $vote['text_more'],
				'impact_summary'   => $vote['impact_summary'],
				'vote_outcome'     => $vote['vote_outcome'],
				'votes_for'        => $vote['votes_yea'],
				'votes_against'    => $vote['votes_nay'],
				'citation'         => $vote['citation'],
				'cost_value'       => $vote['cost_value'],
				'bill_number'      => $vote['bill_number'],
				'bill_url'         => $vote['bill_url'],
				'rollcall_number'  => $vote['rollcall_number'],
				'constitutional'   => $vote['constitutional'],
				'date_voted'       => $vote['date_voted'],
				'date_formatted'   => $vote['date_formatted'],
				'cast'             => $vote['cast'],
				'chamber_label'    => $vote['chamber_label'],
				'url_vote'         => $vote['url_vote'],
				'search_text'      => $vote['search_text'],
				'cost_badge'       => $vote['cost_badge'],
				'cost_badge_class' => $vote['cost_badge_class'],
				'cost_sentence'    => $vote['cost_sentence'],
				'vote_format'      => ['is_match' => $vote['is_match'], 'is_no_vote' => $vote['is_no_vote']],
				'tags'             => $vote['tags'],
				'show_cast'        => true,
				'modal_mode'       => 'page',
			]);
		}
		unset($vote);
	}

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
 * Get cached legislator vote payload (30-day fi_cache file).
 * Dev: fi_cache writes are TEMP DISABLED in cache.php — always queries fresh in dev.
 */
function fi_legislator_votes_cache_get(int $legislator_id): array {
	$legislator_id = absint($legislator_id);
	if ($legislator_id <= 0) {
		return [];
	}

	$cache_key = 'legislator/' . $legislator_id . '-votes';
	$cached    = fi_cache($cache_key, '', 30);
	if ($cached !== false && is_array($cached)) {
		return $cached;
	}

	$data = fi_legislator_votes_query($legislator_id);
	fi_cache($cache_key, $data, 30);
	return $data;
}
