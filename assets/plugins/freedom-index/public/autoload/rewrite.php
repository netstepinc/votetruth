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
function fi_get_legislator_url(int $legislator_id, ?string $gov = null): string {
	return home_url('/legislator/' . $legislator_id . '/');
}

/**
 * Get legislator short URL: /{id}/
 * 
 * @param int $legislator_id Legislator ID
 * @return string Short URL (redirects to full URL)
 */
function fi_get_legislator_short_url(int $legislator_id): string {
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
function fi_get_report_url(int $report_id, ?string $gov = null, array $args = []): string {
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
function fi_get_vote_url(int $vote_id, ?string $gov = null): string {
	return home_url('/' . ($gov ?: 'us') . '/vote/' . $vote_id . '/');
}

/**
 * Get legislators list URL
 * 
 * @param string|null $gov Government code
 * @param array $args Query arguments
 * @return string URL
 */
function fi_get_legislators_url(?string $gov = null, array $args = []): string {
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
	fi_rewrite_log('Adding rewrite rules');
	
	// Legislator routes (stable URLs, no gov prefix)
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
		'index.php?fi_gov=$matches[1]',
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
	$vars[] = 'fi_session_id';
	$vars[] = 'fi_chamber';
	$vars[] = 'fi_party';
	$vars[] = 'fi_state';
	$vars[] = 'fi_search';
	$vars[] = 'fi_sort';
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
	fi_rewrite_log('Handling request: ' . $request_uri);
	
	// Get query vars
	$gov = get_query_var('fi_gov');
	$entity = get_query_var('fi_entity');
	$legislator_id = get_query_var('fi_legislator_id');
	$short_id = get_query_var('fi_short_id');
	
	fi_rewrite_log("Vars: gov={$gov}, entity={$entity}, legislator_id={$legislator_id}, short_id={$short_id}");
	
	// Handle short URL redirect: /{id}/ -> /legislator/{id}/
	if ($short_id && is_numeric($short_id)) {
		wp_redirect(fi_get_legislator_url((int)$short_id), 301);
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
			default:
				// Default to legislators list for gov root
				fi_rewrite_handle_legislators_list($gov);
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
	fi_rewrite_log('handle_legislator: START, legislator_id=' . $legislator_id);
	
	// Check if function exists
	if (!function_exists('fi_legislator_get_with_sessions')) {
		fi_rewrite_log('handle_legislator: fi_legislator_get_with_sessions not found');
		fi_rewrite_handle_404();
		return;
	}
	
	// Get legislator data
	$legislator = fi_legislator_get_with_sessions($legislator_id);
	
	if (!$legislator) {
		fi_rewrite_log('handle_legislator: Legislator not found, 404');
		fi_rewrite_handle_404();
		return;
	}
	
	fi_rewrite_log('handle_legislator: Found legislator: ' . $legislator['display_name']);
	
	// Make available to template
	global $fi_legislator;
	$fi_legislator = $legislator;
	
	// Load template
	fi_rewrite_load_template('legislator');
}

/**
 * Handle legislators list page
 * 
 * @param string $gov Government code
 */
function fi_rewrite_handle_legislators_list(string $gov): void {
	fi_rewrite_log('handle_legislators_list: gov=' . $gov);
	
	global $fi_gov;
	$fi_gov = $gov;
	
	fi_rewrite_load_template('legislators');
}

/**
 * Handle reports list page
 * 
 * @param string $gov Government code
 */
function fi_rewrite_handle_reports(string $gov): void {
	fi_rewrite_log('handle_reports: gov=' . $gov);
	
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
	fi_rewrite_log('handle_single_report: report_id=' . $report_id . ', gov=' . $gov);
	
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
	fi_rewrite_log('handle_votes: gov=' . $gov);
	
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
	fi_rewrite_log('handle_single_vote: vote_id=' . $vote_id . ', gov=' . $gov);
	
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
		fi_rewrite_log('Loading theme template: ' . $theme_template);
		include $theme_template;
		exit;
	}
	
	// Fall back to plugin template
	$plugin_template = FI_DIR . 'public/templates/' . $template . '.php';
	
	if (file_exists($plugin_template)) {
		fi_rewrite_log('Loading plugin template: ' . $plugin_template);
		include $plugin_template;
		exit;
	}
	
	fi_rewrite_log('Template not found: ' . $template);
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

/**
 * Logging helper
 * 
 * @param string $message Log message
 */
function fi_rewrite_log(string $message): void {
	if (defined('FI_DEBUG') && FI_DEBUG) {
		$log_file = FI_DIR . 'assets/cache/log/freedom-index.log';
		$date = date('Y-m-d H:i:s');
		@file_put_contents($log_file, "[{$date}] [rewrite] {$message}\n", FILE_APPEND | LOCK_EX);
	}
}

// Backward compatibility - class methods as function wrappers
if (!class_exists('FI\Public\Rewrite')) {
	class Rewrite {
		public static function get_legislator_url(int $legislator_id, ?string $gov = null): string {
			return fi_get_legislator_url($legislator_id, $gov);
		}
		
		public static function get_legislator_short_url(int $legislator_id): string {
			return fi_get_legislator_short_url($legislator_id);
		}
		
		public static function url_report(int $report_id, ?string $gov = null, array $args = []): string {
			return fi_get_report_url($report_id, $gov, $args);
		}
		
		public static function flush_rules(): void {
			update_option('fi_flush_rewrite_rules', true);
		}
	}
}
