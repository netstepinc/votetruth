<?php
/**
 * Legislator Profile Template — Controller
 *
 * Data strategy (no DB calls in templates):
 *  1. fi_legislator_get($id, true)   — legislator row + published session history
 *  2. fi_session_votes_cache_get()   — session votes + rollcalls + tags (transient-cached)
 *  3. Single career JOIN             — tag scores across all published votes (1 query)
 *
 * NOTE: fi_legislator_sync_cached_session() also lacks the published filter.
 * The cached session_id/chamber in fi_legislators may point to a draft session.
 * This controller uses fi_legislator_sessions_get_history() (now filtered) as the
 * authoritative source, so the cached fields are intentionally ignored for display.
 * Fix fi_legislator_sync_cached_session() in a separate admin/scoring sprint.
 */

if (!defined('ABSPATH')) exit;

$legislator_id = (int) get_query_var('fi_legislator_id');
if ($legislator_id <= 0) {
	wp_redirect(home_url('/legislators/'));
	exit;
}

// 1. Legislator + published session history (most-recent first)
$legislator = fi_legislator_get($legislator_id, true);
if (!$legislator) {
	wp_redirect(home_url('/legislators/'));
	exit;
}

$sessions = $legislator['sessions']; // ARRAY_A rows from fi_legislator_sessions_get_history()

// 2. Active session: URL param → first published session
$url_session_id  = fi_public_get_legislator_session_id();
$current_session = null;

if ($url_session_id) {
	foreach ($sessions as $s) {
		if ((int) $s['session_id'] === $url_session_id) {
			$current_session = $s;
			break;
		}
	}
}

if (!$current_session && !empty($sessions)) {
	$current_session = $sessions[0];
}

$current_session_id = $current_session ? (int) $current_session['session_id'] : 0;
$chamber            = $current_session ? (string) ($current_session['chamber'] ?? '') : '';
$gov                = $current_session ? (string) ($current_session['gov'] ?? '') : ($legislator['gov'] ?? '');

// 3. Session votes cache → server-rendered initial view
$session_cache     = $current_session_id > 0 ? fi_session_votes_cache_get($current_session_id) : [];
$session_votes     = $session_cache['votes']    ?? [];
$session_rollcalls = $session_cache['rollcalls'] ?? [];

// Single pass: filter to legislator's chamber + attach cast + normalize id key
$display_votes = [];
foreach ($session_votes as $vote) {
	if (!$chamber || ($vote['chamber'] ?? '') !== $chamber) {
		continue;
	}
	$cast_data    = $session_rollcalls[(int) $vote['vote_id']][$legislator_id] ?? null;
	$vote['cast'] = $cast_data ? (string) $cast_data['cast'] : 'X';
	$vote['is_override'] = (bool) ($cast_data['is_override'] ?? false);
	$vote['id']   = (int) $vote['vote_id']; // normalize for fi_public_ajax_vote_history_prepare_vote_card_data()
	$display_votes[] = $vote;
}

// 4. Career tag scores — one JOIN, single accumulation pass
$tag_scores = [];
$all_tags   = [];

if ($chamber) {
	global $wpdb;

	$career_rows = $wpdb->get_results($wpdb->prepare(
		"SELECT v.id AS vote_id, v.constitutional, rc.cast, vt.tag_id, t.name AS tag_name
		 FROM {$wpdb->prefix}fi_voterc rc
		 INNER JOIN {$wpdb->prefix}fi_votes v    ON v.id     = rc.vote_id
		 INNER JOIN {$wpdb->prefix}fi_vote_tags vt ON vt.vote_id = v.id
		 INNER JOIN {$wpdb->prefix}fi_taxonomy t   ON t.id    = vt.tag_id AND t.taxonomy = 'tag'
		 WHERE rc.legislator_id = %d
		   AND v.chamber = %s
		   AND v.status = 'publish'",
		$legislator_id,
		$chamber
	), ARRAY_A) ?: [];

	// Accumulate per-tag scoring data
	$accumulators = [];
	foreach ($career_rows as $row) {
		$tag_id = (int) $row['tag_id'];
		if (!isset($accumulators[$tag_id])) {
			$accumulators[$tag_id] = ['name' => $row['tag_name'], 'votes' => [], 'rollcalls' => []];
		}
		$vid = (int) $row['vote_id'];
		$accumulators[$tag_id]['votes'][$vid]     = ['id' => $vid, 'constitutional' => $row['constitutional']];
		$accumulators[$tag_id]['rollcalls'][$vid] = $row['cast'];
	}

	// Compute score, build all_tags list sorted by vote_count DESC
	foreach ($accumulators as $tag_id => $data) {
		$result     = fi_score_calculate_from_votes(array_values($data['votes']), $data['rollcalls']);
		$all_tags[] = [
			'id'         => $tag_id,
			'name'       => $data['name'],
			'vote_count' => count($data['votes']),
			'score'      => $result['score'],
			'grade'      => $result['grade'],
			'scored'     => $result['scored'],
		];
	}

	usort($all_tags, fn($a, $b) => $b['vote_count'] - $a['vote_count']);

	// Top 8 tags for hero display
	$tag_scores = array_slice($all_tags, 0, 8);
}

// 5. Contact data from decoded meta (no extra query)
$meta    = is_array($legislator['meta'] ?? null) ? $legislator['meta'] : [];
$contact = [
	'phone'   => $meta['contact']['phone'] ?? ($meta['phone'] ?? ''),
	'email'   => $meta['contact']['email'] ?? ($meta['email'] ?? ''),
	'website' => is_array($meta['website'] ?? null) ? (string) ($meta['website'][0] ?? '') : (string) ($meta['website'] ?? ''),
	'social'  => is_array($meta['social']  ?? null) ? $meta['social']  : [],
	'offices' => is_array($meta['address'] ?? null) ? $meta['address'] : [],
];

// 5b. Reports for all sessions — one WHERE IN query
$session_ids     = array_values(array_filter(array_map('intval', array_column($sessions, 'session_id'))));
$session_reports = fi_reports_get_by_session_ids($session_ids);

// 5c. Current report + URL for print modal
$current_report_id = fi_public_get_legislator_report_id();
$current_url       = home_url('/legislator/' . $legislator_id . '/')
	. ($current_session_id ? 'session/' . $current_session_id . '/' : '')
	. ($current_report_id  ? 'report/'  . $current_report_id  . '/' : '');

// 5d. User-specific data for authenticated modals
$current_user_id = get_current_user_id();
$user_lists      = $current_user_id ? (fi_lists_get_by_user($current_user_id) ?: []) : [];
$pdf_contacts    = $current_user_id ? fi_pdf_contacts_get($current_user_id) : [];
$pdf_default_idx = $current_user_id ? fi_pdf_contacts_default_index_get($current_user_id) : null;

// 6. SEO
$base_url    = home_url("/legislator/{$legislator_id}/");
$score       = $legislator['score'];
$page_title  = ($legislator['display_name'] ?? 'Legislator') . ' | Freedom Index';
$description = sprintf(
	'%s (%s, %s) — Freedom Score: %s. View full voting record, issue scores, and session reports.',
	$legislator['display_name'] ?? '',
	$current_session['chamber_label'] ?? ($legislator['chamber_label'] ?? ''),
	$legislator['party_name'] ?? '',
	$score !== null ? $score . '%' : 'N/A'
);

fi_seo_tags([
	'title'       => $page_title,
	'description' => $description,
	'canonical'   => $base_url,
	'robots'      => 'index, follow',
	'og'          => [
		'og:title'       => $page_title,
		'og:description' => $description,
		'og:url'         => $base_url,
		'og:type'        => 'profile',
	],
]);

// 6. Render partials — all DB work is done
get_header();

fi_get_template('legislator-header', [
	'legislator'      => $legislator,
	'sessions'        => $sessions,
	'current_session' => $current_session,
	'tag_scores'      => $tag_scores,
	'base_url'        => $base_url,
	'legislator_id'   => $legislator_id,
	'gov'             => $gov,
	'contact'         => $contact,
]);

fi_get_template('legislator-vote-history', [
	'legislator'        => $legislator,
	'sessions'          => $sessions,
	'current_session'   => $current_session,
	'display_votes'     => $display_votes,
	'all_tags'          => $all_tags,
	'session_reports'   => $session_reports,
	'current_report_id' => $current_report_id,
	'current_tag_id'    => fi_public_get_legislator_tag_id(),
	'base_url'          => $base_url,
	'gov'               => $gov,
	'chamber'           => $chamber,
	'legislator_id'     => $legislator_id,
]);

fi_get_template('legislator-modals', [
	'legislator'        => $legislator,
	'base_url'          => $base_url,
	'current_session'   => $current_session,
	'contact'           => $contact,
	'session_reports'   => $session_reports,
	'current_report_id' => $current_report_id,
	'current_url'       => $current_url,
	'user_lists'        => $user_lists,
	'pdf_contacts'      => $pdf_contacts,
	'pdf_default_idx'   => $pdf_default_idx,
	'current_user_id'   => $current_user_id,
	'legislator_id'     => $legislator_id,
	'gov'               => $gov,
]);

get_footer();
