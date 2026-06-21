<?php
/**
 * URL Routing for Freedom Index Public Front-End
 * 
 * Handles rewrite rules for clean URLs.
 * Function-based architecture (refactored from class-based).
 * 
 * @package FreedomIndex
 */

if (!defined('ABSPATH')) exit;

// =============================================================================
// HOOK REGISTRATION (replaces __construct)
// =============================================================================

add_action('init', 'fi_rewrite_add_rules');
add_action('init', 'fi_rewrite_maybe_flush', 99);
add_filter('query_vars', 'fi_rewrite_add_query_vars');
add_action('template_redirect', 'fi_rewrite_handle_requests', 1);

// Prevent redirect_canonical from redirecting legislators URLs
add_filter('redirect_canonical', 'fi_rewrite_prevent_canonical_redirect', 10, 2);

// =============================================================================
// PUBLIC URL HELPER FUNCTIONS
// =============================================================================

/**
 * Get legislator URL
 * Returns stable URL without gov prefix: /legislator/{id}/
 * 
 * @param int $legislator_id Legislator ID
 * @param string|null $gov Deprecated, kept for compatibility
 * @return string Full URL
 */
function fi_legislator_get_url(int $legislator_id, ?string $gov = null): string {
	return home_url('/legislator/' . $legislator_id . '/');
}

/**
 * Get legislator short URL: /{id}/
 * 
 * @param int $legislator_id Legislator ID
 * @return string Short URL (redirects to full URL)
 */
function fi_legislator_get_short_url(int $legislator_id): string {
	return home_url('/' . $legislator_id . '/');
}

/**
 * Get report URL
 * 
 * @param int $report_id Report ID
 * @param string|null $gov Government code
 * @param array $args Optional arguments
 * @return string Report URL
 */
function fi_report_get_url(int $report_id, ?string $gov = null, array $args = []): string {
	if (!$gov) {
		$gov = 'us';
	}
	
	$chamber = !empty($args['chamber']) ? strtoupper($args['chamber']) : null;
	
	if ($chamber && ($chamber === 'H' || $chamber === 'S')) {
		$base_url = home_url('/' . $gov . '/report/' . $report_id . '/chamber/' . $chamber . '/');
	} else {
		$base_url = home_url('/' . $gov . '/report/' . $report_id . '/');
	}
	
	if (!empty($args['compare'])) {
		$base_url .= 'compare/';
	}
	
	return $base_url;
}

/**
 * Get vote URL
 * 
 * @param int $vote_id Vote ID
 * @param string|null $gov Government code
 * @return string Vote URL
 */
function fi_vote_get_url(int $vote_id, ?string $gov = null): string {
	return home_url('/' . strtolower($gov ?: 'us') . '/vote/' . $vote_id . '/');
}

/**
 * Get legislators list URL
 * 
 * @param string|null $gov Government code
 * @param array $args Query arguments
 * @return string URL
 */
function fi_legislators_get_url(?string $gov = null, array $args = []): string {
	if (!$gov) {
		return home_url('/legislators/');
	}
	
	$url = home_url('/' . $gov . '/legislators/');
	
	if (!empty($args)) {
		$url = add_query_arg($args, $url);
	}
	
	return $url;
}

// =============================================================================
// REWRITE RULES
// =============================================================================

/**
 * Add all rewrite rules
 */
function fi_rewrite_add_rules(): void {

	// Legislator filter URLs — most specific first (stable IDs, no gov prefix)
	add_rewrite_rule(
		'^legislator/([0-9]+)/session/([0-9]+)/report/([0-9]+)/pdf/([a-z0-9]+)/([0-9]+_[0-9\-]*)/?$',
		'index.php?fi_legislator_id=$matches[1]&fi_session=$matches[2]&fi_report_id=$matches[3]&fi_pdf=$matches[4]&fi_pdf_pers=$matches[5]',
		'top'
	);
	add_rewrite_rule(
		'^legislator/([0-9]+)/session/([0-9]+)/report/([0-9]+)/pdf/([a-z0-9]+)/?$',
		'index.php?fi_legislator_id=$matches[1]&fi_session=$matches[2]&fi_report_id=$matches[3]&fi_pdf=$matches[4]',
		'top'
	);
	add_rewrite_rule(
		'^legislator/([0-9]+)/session/([0-9]+)/report/([0-9]+)/?$',
		'index.php?fi_legislator_id=$matches[1]&fi_session=$matches[2]&fi_report_id=$matches[3]',
		'top'
	);
	add_rewrite_rule(
		'^legislator/([0-9]+)/session/([0-9]+)/?$',
		'index.php?fi_legislator_id=$matches[1]&fi_session=$matches[2]',
		'top'
	);
	add_rewrite_rule(
		'^legislator/([0-9]+)/issue/([0-9]+)/?$',
		'index.php?fi_legislator_id=$matches[1]&fi_tag_id=$matches[2]',
		'top'
	);
	add_rewrite_rule(
		'^legislator/([0-9]+)/report/([0-9]+)/pdf/([a-z0-9]+)/?$',
		'index.php?fi_legislator_id=$matches[1]&fi_report_id=$matches[2]&fi_pdf=$matches[3]',
		'top'
	);
	add_rewrite_rule(
		'^legislator/([0-9]+)/report/([0-9]+)/?$',
		'index.php?fi_legislator_id=$matches[1]&fi_report_id=$matches[2]',
		'top'
	);

	// Base legislator profile
	add_rewrite_rule(
		'^legislator/([0-9]+)/?$',
		'index.php?fi_legislator_id=$matches[1]',
		'top'
	);

	add_rewrite_rule(
		'^legislator/([0-9]+)/report/([^/]+)/?$',
		'index.php?fi_legislator_id=$matches[1]&fi_report_slug=$matches[2]',
		'top'
	);
	
	// Short URL: /{id}/ -> redirect to /legislator/{id}/
	add_rewrite_rule(
		'^([0-9]{1,10})/?$',
		'index.php?fi_short_id=$matches[1]',
		'top'
	);
	
	// Government routes
	add_rewrite_rule(
		'^([a-z]{2})/?$',
		'index.php?fi_gov=$matches[1]&fi_entity=government',
		'top'
	);
	
	// Legislators with optional filter path segments: /us/legislators/session/14/party/r/...
	add_rewrite_rule(
		'^([a-z]{2})/legislators/(.+)/?$',
		'index.php?fi_gov=$matches[1]&fi_entity=legislators&fi_leg_filters=$matches[2]',
		'top'
	);
	add_rewrite_rule(
		'^([a-z]{2})/legislators/?$',
		'index.php?fi_gov=$matches[1]&fi_entity=legislators',
		'top'
	);
	
	add_rewrite_rule(
		'^([a-z]{2})/reports/?$',
		'index.php?fi_gov=$matches[1]&fi_entity=reports',
		'top'
	);
	
	add_rewrite_rule(
		'^([a-z]{2})/report/([0-9]+)/?$',
		'index.php?fi_gov=$matches[1]&fi_entity=report&fi_report_id=$matches[2]',
		'top'
	);
	
	add_rewrite_rule(
		'^([a-z]{2})/votes/?$',
		'index.php?fi_gov=$matches[1]&fi_entity=votes',
		'top'
	);
	
	add_rewrite_rule(
		'^([a-z]{2})/vote/([0-9]+)/?$',
		'index.php?fi_gov=$matches[1]&fi_entity=vote&fi_vote_id=$matches[2]',
		'top'
	);
}

/**
 * Flush rewrite rules once when legislator filter routes change.
 */
function fi_rewrite_maybe_flush(): void {
	$version = '20250621-legislator-filters';
	if (get_option('fi_rewrite_version') === $version) {
		return;
	}
	flush_rewrite_rules(false);
	update_option('fi_rewrite_version', $version, false);
}

/**
 * Add custom query vars
 * 
 * @param array $vars Existing query vars
 * @return array Modified query vars
 */
function fi_rewrite_add_query_vars(array $vars): array {
	$vars[] = 'fi_legislator_id';
	$vars[] = 'fi_report_slug';
	$vars[] = 'fi_short_id';
	$vars[] = 'fi_gov';
	$vars[] = 'fi_entity';
	$vars[] = 'fi_report_id';
	$vars[] = 'fi_vote_id';
	$vars[] = 'fi_session';
	$vars[] = 'fi_session_id';
	$vars[] = 'fi_tag_id';
	$vars[] = 'fi_pdf';
	$vars[] = 'fi_pdf_pers';
	$vars[] = 'fi_chamber';
	$vars[] = 'fi_party';
	$vars[] = 'fi_state';
	$vars[] = 'fi_search';
	$vars[] = 'fi_sort';
	$vars[] = 'fi_leg_filters';
	return $vars;
}

// =============================================================================
// REQUEST HANDLERS
// =============================================================================

/**
 * Main request handler - routes to appropriate entity handler
 */
function fi_rewrite_handle_requests(): void {
	$request_uri = $_SERVER['REQUEST_URI'] ?? '';
	
	// Get query vars
	$gov = get_query_var('fi_gov');
	$entity = get_query_var('fi_entity');
	$legislator_id = get_query_var('fi_legislator_id');
	$short_id = get_query_var('fi_short_id');
	
	
	// Handle short URL redirect: /{id}/ -> /legislator/{id}/
	if ($short_id && is_numeric($short_id)) {
		wp_redirect(fi_legislator_get_url((int)$short_id), 301);
		exit;
	}
	
	// Handle legislator routes (no gov prefix required)
	if ($legislator_id) {
		fi_rewrite_handle_legislator((int)$legislator_id);
		return;
	}
	
	// Handle government routes
	if ($gov) {
		switch ($entity) {
			case 'legislators':
				fi_rewrite_handle_legislators_list($gov);
				break;
			case 'reports':
				fi_rewrite_handle_reports($gov);
				break;
			case 'report':
				$report_id = get_query_var('fi_report_id');
				if ($report_id) {
					fi_rewrite_handle_single_report((int)$report_id, $gov);
				}
				break;
			case 'votes':
				fi_rewrite_handle_votes($gov);
				break;
			case 'vote':
				$vote_id = get_query_var('fi_vote_id');
				if ($vote_id) {
					fi_rewrite_handle_single_vote((int)$vote_id, $gov);
				}
				break;
			case 'government':
			default:
				// Gov root — landing/scorecard page
				fi_rewrite_handle_government($gov);
		}
		return;
	}
}

/**
 * Handle legislator detail page
 * 
 * @param int $legislator_id Legislator ID
 */
function fi_rewrite_handle_legislator(int $legislator_id): void {
	
	// Get legislator data
	$legislator = fi_legislator_get($legislator_id, true);
	
	if (!$legislator) {
		fi_rewrite_handle_404();
		return;
	}
	
	
	// Make available to template
	global $fi_legislator;
	$fi_legislator = $legislator;
	
	// Load template
	fi_rewrite_load_template('legislator');
}

/**
 * Handle government landing/scorecard page (e.g. /us/, /tx/)
 *
 * @param string $gov Government code
 */
function fi_rewrite_handle_government(string $gov): void {

	$gov = strtoupper($gov);

	global $fi_gov, $fi_gov_name, $fi_current_session, $fi_sessions, $fi_reports, $fi_legislators;

	$fi_gov      = $gov;
	$fi_gov_name = fi_gov_name($gov);

	$current_session   = fi_session_get_current($gov);
	$fi_current_session = $current_session['id'] ?? null;

	$fi_sessions   = fi_sessions_get_by_gov($gov);

	$fi_legislators = $fi_current_session
		? fi_legislators_get(['gov' => $gov, 'session_id' => $fi_current_session, 'limit' => 600])
		: [];

	fi_rewrite_load_template('gov');
}

/**
 * Handle legislators list page
 * 
 * @param string $gov Government code
 */
function fi_rewrite_handle_legislators_list(string $gov): void {
	$gov = strtoupper($gov);

	global $fi_gov, $fi_gov_name, $fi_session, $fi_legislators, $fi_total_count, $fi_filter_description, $fi_filters;

	$fi_gov      = $gov;
	$fi_gov_name = fi_gov_name($gov);

	// Parse key/value segments from URL path: session/14/party/r/chamber/h/...
	$path     = (string) get_query_var('fi_leg_filters', '');
	$segments = array_values(array_filter(explode('/', trim($path, '/'))));
	$raw      = [];
	for ($i = 0, $n = count($segments) - 1; $i < $n; $i += 2) {
		$raw[$segments[$i]] = $segments[$i + 1];
	}

	$session_id = !empty($raw['session']) ? (int) $raw['session'] : 0;
	$chamber    = strtoupper(sanitize_key($raw['chamber'] ?? ''));
	$party      = strtoupper(sanitize_key($raw['party']   ?? ''));
	$state      = strtoupper(sanitize_key($raw['state']   ?? ''));
	$sort       = sanitize_key($raw['sort'] ?? 'na') ?: 'na';
	$search     = sanitize_text_field(urldecode($raw['search'] ?? ''));

	if ($session_id <= 0) {
		$current    = fi_session_get_current($gov);
		$session_id = $current ? (int) $current['id'] : 0;
	}
	$fi_session = $session_id;

	$fi_filters = [
		'session_id' => $session_id,
		'chamber'    => $chamber,
		'party_slug' => $party,
		'state'      => $state,
		'sort'       => $sort,
		'search'     => $search,
	];

	$fi_legislators = fi_legislators_get([
		'gov'        => $gov,
		'session_id' => $session_id,
		'chamber'    => $chamber,
		'party'      => $party,
		'state'      => $state,
		'sort'       => $sort,
		'search'     => $search,
		'limit'      => LEGISLATORS_LIMIT,
	]);
	$fi_total_count = count($fi_legislators);

	$fi_filter_description = fi_filter_build_description([
		'gov'     => $gov,
		'session' => $session_id,
		'party'   => $party,
		'chamber' => $chamber,
		'state'   => $state,
	]);

	// HTMX partial request — return only the grid, not the full page
	if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
		fi_get_template('legislators-grid', [
			'fi_legislators' => $fi_legislators,
			'fi_gov'         => $fi_gov,
			'fi_total_count' => $fi_total_count,
		]);
		exit;
	}

	fi_rewrite_load_template('legislators');
}

/**
 * Handle reports list page
 * 
 * @param string $gov Government code
 */
function fi_rewrite_handle_reports(string $gov): void {
	
	global $fi_gov;
	$fi_gov = $gov;
	
	fi_rewrite_load_template('reports');
}

/**
 * Handle single report page
 * 
 * @param int $report_id Report ID
 * @param string $gov Government code
 */
function fi_rewrite_handle_single_report(int $report_id, string $gov): void {
	
	global $fi_gov, $fi_report_id;
	$fi_gov = $gov;
	$fi_report_id = $report_id;
	
	fi_rewrite_load_template('report');
}

/**
 * Handle votes list page
 * 
 * @param string $gov Government code
 */
function fi_rewrite_handle_votes(string $gov): void {
	
	global $fi_gov;
	$fi_gov = $gov;
	
	fi_rewrite_load_template('votes');
}

/**
 * Handle single vote page
 * 
 * @param int $vote_id Vote ID
 * @param string $gov Government code
 */
function fi_rewrite_handle_single_vote(int $vote_id, string $gov): void {
	
	global $fi_gov, $fi_vote_id;
	$fi_gov = $gov;
	$fi_vote_id = $vote_id;
	
	fi_rewrite_load_template('vote');
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

/**
 * Handle 404
 */
function fi_rewrite_handle_404(): void {
	global $wp_query;
	$wp_query->set_404();
	status_header(404);
	get_template_part('404');
	exit;
}

/**
 * Load plugin template
 * 
 * @param string $template Template name (without .php)
 */
function fi_rewrite_load_template(string $template): void {
	// Check theme override first
	$theme_template = get_template_directory() . '/freedom-index/' . $template . '.php';
	
	if (file_exists($theme_template)) {
		include $theme_template;
		exit;
	}
	
	// Fall back to plugin template
	$plugin_template = FI_DIR . 'public/templates/' . $template . '.php';
	
	if (file_exists($plugin_template)) {
		include $plugin_template;
		exit;
	}
	
	fi_rewrite_handle_404();
}

/**
 * Prevent canonical redirect on Freedom Index URLs
 * 
 * @param string $redirect_url The redirect URL
 * @param string $requested_url The requested URL
 * @return string|false The redirect URL or false to prevent redirect
 */
function fi_rewrite_prevent_canonical_redirect($redirect_url, $requested_url) {
	// Don't redirect FI URLs
	if (strpos($requested_url, '/legislator/') !== false) {
		return false;
	}
	if (preg_match('#/[a-z]{2}/(?:legislators|reports|votes|report|vote)#', $requested_url)) {
		return false;
	}
	return $redirect_url;
}

