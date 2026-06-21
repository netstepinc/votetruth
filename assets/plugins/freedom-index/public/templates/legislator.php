<?php
/**
 * Legislator Profile Template — Controller
 *
 * Data strategy (no DB calls in templates):
 *  1. fi_legislator_get($id, true)           — legislator row + published session history
 *  2. fi_legislator_votes_cache_get($id)     — full votes payload + vote_groups (file cache)
 */

if (!defined('ABSPATH')) exit;

$legislator_id = (int) get_query_var('fi_legislator_id');
if ($legislator_id <= 0) {
	wp_redirect(home_url('/legislators/'));
	exit;
}

$legislator = fi_legislator_get($legislator_id, true);
if (!$legislator) {
	wp_redirect(home_url('/legislators/'));
	exit;
}

$sessions = $legislator['sessions'];

$url_session_id  = fi_public_get_legislator_session_id();
$url_report_id   = fi_public_get_legislator_report_id();
$url_tag_id      = fi_public_get_legislator_tag_id();
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

// Single compile pass — votes, vote_groups, issue scores
$votes_payload = fi_legislator_votes_cache_get($legislator_id);
$vote_groups     = $votes_payload['vote_groups'] ?? ['all' => [], 'tags' => [], 'sessions' => []];
$votes_map       = $votes_payload['votes'] ?? [];
$all_tags        = $votes_payload['all_tags'] ?? [];
$tag_scores      = $votes_payload['tag_scores'] ?? [];
$sessions_meta   = $votes_payload['sessions_meta'] ?? [];

// Default view: current session on base URL; deep links override
$default_view = 'session';
if ($url_tag_id) {
	$default_view = 'tag';
} elseif ($url_report_id && $url_session_id) {
	$default_view = 'report';
} elseif ($url_session_id) {
	$default_view = 'session';
}

$active_session_id = $url_session_id ?: $current_session_id;
$active_report_id  = $url_report_id;
$active_tag_id     = $url_tag_id;

// Resolve initial vote IDs for server-render (SEO)
$initial_group = $vote_groups['all'];
if ($default_view === 'tag' && $active_tag_id && !empty($vote_groups['tags'][$active_tag_id])) {
	$initial_group = $vote_groups['tags'][$active_tag_id];
} elseif ($default_view === 'report' && $active_session_id && $active_report_id && !empty($vote_groups['sessions'][$active_session_id]['reports'])) {
	foreach ($vote_groups['sessions'][$active_session_id]['reports'] as $report) {
		if ((int) ($report['id'] ?? 0) === $active_report_id) {
			$initial_group = $report;
			break;
		}
	}
} elseif ($active_session_id && !empty($vote_groups['sessions'][$active_session_id])) {
	$initial_group = $vote_groups['sessions'][$active_session_id];
}

$initial_vote_ids = array_values(array_map('intval', (array) ($initial_group['votes'] ?? [])));
$initial_limit    = ($default_view === 'all') ? 25 : count($initial_vote_ids);
$display_votes    = [];

foreach (array_slice($initial_vote_ids, 0, $initial_limit) as $vote_id) {
	if (empty($votes_map[$vote_id])) {
		continue;
	}
	$display_votes[] = fi_legislator_votes_prepare_card_data($votes_map[$vote_id], [
		'gov'           => $gov,
		'report_format' => (string) ($initial_group['format'] ?? 'scorecard'),
	]);
}

// Print modal default report base (latest scorecard on current session)
$default_print_modal_report_base = '';
foreach ($sessions as $session) {
	$sid = (int) ($session['session_id'] ?? 0);
	if ($sid <= 0 || empty($sessions_meta[$sid]['reports'])) {
		continue;
	}
	foreach ($sessions_meta[$sid]['reports'] as $report) {
		if (($report['format'] ?? '') === 'scorecard') {
			$default_print_modal_report_base = home_url('/legislator/' . $legislator_id . '/session/' . $sid . '/report/' . (int) $report['id'] . '/');
			break 2;
		}
	}
}

$meta    = is_array($legislator['meta'] ?? null) ? $legislator['meta'] : [];
$contact = [
	'phone'   => $meta['contact']['phone'] ?? ($meta['phone'] ?? ''),
	'email'   => $meta['contact']['email'] ?? ($meta['email'] ?? ''),
	'website' => is_array($meta['website'] ?? null) ? (string) ($meta['website'][0] ?? '') : (string) ($meta['website'] ?? ''),
	'social'  => is_array($meta['social']  ?? null) ? $meta['social']  : [],
	'offices' => is_array($meta['address'] ?? null) ? $meta['address'] : [],
];

$current_url = $base_url;
if ($url_tag_id) {
	$current_url = home_url('/legislator/' . $legislator_id . '/issue/' . $url_tag_id . '/');
} elseif ($url_session_id) {
	$current_url = home_url('/legislator/' . $legislator_id . '/session/' . $url_session_id . '/');
	if ($url_report_id) {
		$current_url .= 'report/' . $url_report_id . '/';
	}
}

$current_user_id = get_current_user_id();
$user_lists      = $current_user_id ? (fi_lists_get_by_user($current_user_id) ?: []) : [];
$pdf_contacts    = $current_user_id ? fi_pdf_contacts_get($current_user_id) : [];
$pdf_default_idx = $current_user_id ? fi_pdf_contacts_default_index_get($current_user_id) : null;

// SEO — canonical always base URL; og:url matches active variant
$base_url   = home_url("/legislator/{$legislator_id}/");
$score      = $legislator['score'];
$page_title = ($legislator['display_name'] ?? 'Legislator') . ' | Freedom Index';
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
		'og:url'         => $current_url,
		'og:type'        => 'profile',
	],
]);

// JSON-LD: Person + Issue Scores ItemList
$json_ld = [
	'@context' => 'https://schema.org',
	'@graph'   => [
		[
			'@type'       => 'Person',
			'name'        => $legislator['display_name'] ?? '',
			'url'         => $base_url,
			'jobTitle'    => $current_session['chamber_title'] ?? ($legislator['chamber_title'] ?? ''),
			'affiliation' => $legislator['party_name'] ?? '',
		],
	],
];

if (!empty($tag_scores)) {
	$issue_items = [];
	foreach ($tag_scores as $i => $tag) {
		$issue_items[] = [
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $tag['name'] ?? '',
			'additionalProperty' => [
				['@type' => 'PropertyValue', 'name' => 'score', 'value' => (string) ($tag['score'] ?? '')],
				['@type' => 'PropertyValue', 'name' => 'vote_count', 'value' => (string) ($tag['vote_count'] ?? 0)],
			],
		];
	}
	$json_ld['@graph'][] = [
		'@type'           => 'ItemList',
		'name'            => 'Issue Scores',
		'numberOfItems'   => count($issue_items),
		'itemListElement' => $issue_items,
	];
}

add_action('wp_head', static function () use ($json_ld) {
	echo '<script type="application/ld+json">' . wp_json_encode($json_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}, 20);

$session_reports = [];
foreach ($sessions_meta as $sid => $smeta) {
	$reports = [];
	foreach ($smeta['reports'] ?? [] as $report) {
		$report['session_id'] = (int) $sid;
		$reports[] = $report;
	}
	$session_reports[$sid] = $reports;
}

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
	'legislator'                      => $legislator,
	'sessions'                        => $sessions,
	'sessions_meta'                   => $sessions_meta,
	'current_session'                 => $current_session,
	'display_votes'                   => $display_votes,
	'votes_map'                       => $votes_map,
	'vote_groups'                     => $vote_groups,
	'all_tags'                        => $all_tags,
	'default_view'                    => $default_view,
	'active_session_id'               => $active_session_id,
	'active_report_id'                => $active_report_id,
	'active_tag_id'                   => $active_tag_id,
	'current_report_id'               => $url_report_id,
	'current_tag_id'                  => $url_tag_id,
	'initial_group'                   => $initial_group,
	'initial_vote_ids'                => $initial_vote_ids,
	'default_print_modal_report_base' => $default_print_modal_report_base,
	'base_url'                        => $base_url,
	'gov'                             => $gov,
	'chamber'                         => $chamber,
	'legislator_id'                   => $legislator_id,
]);

fi_get_template('legislator-modals', [
	'legislator'        => $legislator,
	'base_url'          => $base_url,
	'current_session'   => $current_session,
	'contact'           => $contact,
	'session_reports'   => $session_reports,
	'current_report_id' => $url_report_id,
	'current_url'       => $current_url,
	'user_lists'        => $user_lists,
	'pdf_contacts'      => $pdf_contacts,
	'pdf_default_idx'   => $pdf_default_idx,
	'current_user_id'   => $current_user_id,
	'legislator_id'     => $legislator_id,
	'gov'               => $gov,
]);

get_footer();
