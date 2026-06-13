<?php
/*
 * Freedom Index REST API
 *
 * Straight function version of the former FIAdmin\RestAPI class file.
 *
 * Provides REST endpoints for Freedom Index data access.
 * Includes API key authentication, CORS support, and conservative cache headers.
 *
 * Notes:
 * - Routes are ID-based where entities have IDs.
 * - Legacy slug-based report route is intentionally removed/replaced with report ID route.
 * - List creation uses fi_list_save() instead of direct SQL/slug generation.
 * Refactored and tuned the REST API file.
Key adjustments:
	Removed the FIAdmin\RestAPI class/namespace wrapper.
Added procedural route registration:
	fi_rest_api_init()
	fi_rest_register_routes()
Converted endpoint callbacks:
	fi_rest_health_check()
	fi_rest_get_legislators()
	fi_rest_get_legislator()
	fi_rest_get_votes()
	fi_rest_get_vote()
	fi_rest_get_zip_lookup()
	fi_rest_get_report()
	fi_rest_create_list()
	fi_rest_get_list()
	fi_rest_global_search()
Converted private helpers:
	fi_rest_get_legislators_by_gov()
	fi_rest_get_votes_by_gov()
	fi_rest_get_legislator_scores()
	fi_rest_search_legislators()
	fi_rest_search_votes()
	fi_rest_search_reports()
Important fixes:
	Removed stale slug-based report route:
	/reports/(?P<slug>[a-zA-Z0-9-]+)
Replaced with ID-based:
	/reports/(?P<id>\d+)
Fixed the original bug where the route used slug but get_report() tried to read id.

Removed list slug generation:
SlugGenerator::generate_list_slug()
Replaced direct list SQL insert with:
fi_list_save()
Removed v.slug from vote search/select/order logic.
Changed API key comparison to hash_equals().
Changed permission failures from bare false to WP_Error responses.
Added per-page validation with a max of 100.

Added settings-based CORS origin support via:

fi_settings_get('US', 'api.cors_origins', [])
Kept fallback support for the old FI\Public\LegislatorLookup::get_officials_with_scores() if that file has not been refactored yet.
 */

if (!defined('ABSPATH')) exit;

/**
 * Initialize FI REST API hooks.
 *
 * @return void
 */
function fi_rest_api_init(): void {
	add_action('rest_api_init', 'fi_rest_register_routes');
	add_action('rest_api_init', 'fi_rest_add_cors_support');
	add_filter('rest_pre_serve_request', 'fi_rest_add_cache_headers', 10, 4);
}
add_action('plugins_loaded', 'fi_rest_api_init');

/**
 * Register FI REST API routes.
 *
 * @return void
 */
function fi_rest_register_routes(): void {
	$namespace = 'fi/v1';

	register_rest_route($namespace, '/health', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'fi_rest_health_check',
		'permission_callback' => '__return_true',
	]);

	register_rest_route($namespace, '/legislators', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'fi_rest_get_legislators',
		'permission_callback' => 'fi_rest_check_api_key',
		'args'                => fi_rest_collection_args([
			'gov'     => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
			'session' => ['type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint'],
			'chamber' => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
			'q'       => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
		]),
	]);

	register_rest_route($namespace, '/legislators/(?P<id>\d+)', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'fi_rest_get_legislator',
		'permission_callback' => 'fi_rest_check_api_key',
		'args'                => [
			'id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
		],
	]);

	register_rest_route($namespace, '/votes', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'fi_rest_get_votes',
		'permission_callback' => 'fi_rest_check_api_key',
		'args'                => fi_rest_collection_args([
			'gov'     => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
			'session' => ['type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint'],
			'chamber' => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
		]),
	]);

	register_rest_route($namespace, '/votes/(?P<id>\d+)', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'fi_rest_get_vote',
		'permission_callback' => 'fi_rest_check_api_key',
		'args'                => [
			'id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
		],
	]);

	register_rest_route($namespace, '/zip/(?P<zip>\d{5})', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'fi_rest_get_zip_lookup',
		'permission_callback' => 'fi_rest_check_api_key',
		'args'                => [
			'zip' => [
				'type'              => 'string',
				'required'          => true,
				'pattern'           => '\d{5}',
				'sanitize_callback' => 'sanitize_text_field',
			],
		],
	]);

	register_rest_route($namespace, '/reports/(?P<id>\d+)', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'fi_rest_get_report',
		'permission_callback' => 'fi_rest_check_api_key',
		'args'                => [
			'id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
		],
	]);

	register_rest_route($namespace, '/lists', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'fi_rest_create_list',
		'permission_callback' => 'fi_rest_check_api_key',
		'args'                => [
			'name' => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'legislators' => [
				'type'     => 'array',
				'required' => true,
				'items'    => ['type' => 'integer'],
			],
		],
	]);

	register_rest_route($namespace, '/lists/(?P<id>\d+)', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'fi_rest_get_list',
		'permission_callback' => 'fi_rest_check_api_key',
		'args'                => [
			'id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
		],
	]);

	register_rest_route($namespace, '/search', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'fi_rest_global_search',
		'permission_callback' => 'fi_rest_check_api_key',
		'args'                => fi_rest_collection_args([
			'q' => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'type' => [
				'type'              => 'string',
				'default'           => 'all',
				'sanitize_callback' => 'sanitize_text_field',
			],
		]),
	]);
}

/**
 * Standard collection pagination args.
 *
 * @param array $args Endpoint args.
 * @return array Args with page/per_page added.
 */
function fi_rest_collection_args(array $args = []): array {
	$args['page'] = [
		'type'              => 'integer',
		'default'           => 1,
		'sanitize_callback' => 'absint',
		'validate_callback' => static function($value) { return (int) $value >= 1; },
	];

	$args['per_page'] = [
		'type'              => 'integer',
		'default'           => 20,
		'sanitize_callback' => 'absint',
		'validate_callback' => static function($value) { return (int) $value >= 1 && (int) $value <= 100; },
	];

	return $args;
}

/**
 * Return a REST response with paginated data.
 *
 * @param array $items Items.
 * @param int $page Page.
 * @param int $per_page Per page.
 * @return WP_REST_Response
 */
function fi_rest_paginated_response(array $items, int $page, int $per_page): WP_REST_Response {
	$page = max(1, $page);
	$per_page = max(1, min(100, $per_page));
	$total = count($items);
	$offset = ($page - 1) * $per_page;
	$paged_items = array_slice($items, $offset, $per_page);

	return new WP_REST_Response([
		'data'       => $paged_items,
		'pagination' => [
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
		],
	]);
}

/**
 * Health check endpoint.
 *
 * @return WP_REST_Response
 */
function fi_rest_health_check(): WP_REST_Response {
	return new WP_REST_Response([
		'ok'        => true,
		'version'   => '1.0.0',
		'timestamp' => current_time('mysql'),
	]);
}

/**
 * Get legislators collection.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function fi_rest_get_legislators(WP_REST_Request $request) {
	$gov = strtoupper((string) $request->get_param('gov'));
	$session_id = absint($request->get_param('session'));
	$chamber = strtoupper((string) $request->get_param('chamber'));
	$search = (string) $request->get_param('q');
	$page = absint($request->get_param('page')) ?: 1;
	$per_page = absint($request->get_param('per_page')) ?: 20;

	$filters = [];
	if (in_array($chamber, ['H', 'S'], true)) {
		$filters['chamber'] = $chamber;
	}
	if ($search !== '') {
		$filters['search'] = $search;
	}

	if ($session_id > 0 && function_exists('fi_legislators_get_by_session')) {
		$legislators = fi_legislators_get_by_session($session_id, $filters);
	} elseif ($gov !== '') {
		$legislators = fi_rest_get_legislators_by_gov($gov, $filters);
	} else {
		return new WP_Error('missing_filter', 'Provide either session or gov.', ['status' => 400]);
	}

	return fi_rest_paginated_response(is_array($legislators) ? $legislators : [], $page, $per_page);
}

/**
 * Get single legislator.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function fi_rest_get_legislator(WP_REST_Request $request) {
	$legislator_id = absint($request->get_param('id'));
	$legislator = function_exists('fi_legislator_get') ? fi_legislator_get($legislator_id) : null;

	if (!$legislator) {
		return new WP_Error('not_found', 'Legislator not found.', ['status' => 404]);
	}

	$sessions = function_exists('fi_legislator_sessions_get') ? fi_legislator_sessions_get($legislator_id) : [];
	$scores = fi_rest_get_legislator_scores($legislator_id);

	return new WP_REST_Response([
		'data' => [
			'legislator' => $legislator,
			'sessions'   => $sessions,
			'scores'     => $scores,
		],
	]);
}

/**
 * Get votes collection.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function fi_rest_get_votes(WP_REST_Request $request) {
	$gov = strtoupper((string) $request->get_param('gov'));
	$session_id = absint($request->get_param('session'));
	$chamber = strtoupper((string) $request->get_param('chamber'));
	$page = absint($request->get_param('page')) ?: 1;
	$per_page = absint($request->get_param('per_page')) ?: 20;

	$filters = [
		'status' => null,
		'cache'  => false,
	];

	if (in_array($chamber, ['H', 'S'], true)) {
		$filters['chamber'] = $chamber;
	}

	if ($session_id > 0 && function_exists('fi_votes_get_by_session')) {
		$votes = fi_votes_get_by_session($session_id, $filters);
	} elseif ($gov !== '') {
		$votes = fi_rest_get_votes_by_gov($gov, $filters);
	} else {
		return new WP_Error('missing_filter', 'Provide either session or gov.', ['status' => 400]);
	}

	return fi_rest_paginated_response(is_array($votes) ? $votes : [], $page, $per_page);
}

/**
 * Get single vote.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function fi_rest_get_vote(WP_REST_Request $request) {
	global $wpdb;

	$vote_id = absint($request->get_param('id'));

	$vote = $wpdb->get_row($wpdb->prepare(
		"SELECT v.*, s.name as session_name, s.gov
		FROM {$wpdb->prefix}fi_votes v
		INNER JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
		WHERE v.id = %d",
		$vote_id
	));

	if (!$vote) {
		return new WP_Error('not_found', 'Vote not found.', ['status' => 404]);
	}

	$roll_calls = function_exists('fi_rollcalls_get_by_vote') ? fi_rollcalls_get_by_vote($vote_id) : [];

	return new WP_REST_Response([
		'data' => [
			'vote'       => $vote,
			'roll_calls' => $roll_calls,
		],
	]);
}

/**
 * ZIP lookup endpoint.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function fi_rest_get_zip_lookup(WP_REST_Request $request) {
	$zip = sanitize_text_field((string) $request->get_param('zip'));

	$officials = [];

	if (function_exists('fi_legislator_lookup_get_officials_with_scores')) {
		$officials = fi_legislator_lookup_get_officials_with_scores($zip);
	}

	if (empty($officials)) {
		return new WP_Error('not_found', 'No officials found for ZIP code.', ['status' => 404]);
	}

	return new WP_REST_Response([
		'data' => [
			'zip'       => $zip,
			'officials' => $officials,
		],
	]);
}

/**
 * Get report by ID.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function fi_rest_get_report(WP_REST_Request $request) {
	$report_id = absint($request->get_param('id'));
	$report = function_exists('fi_report_get') ? fi_report_get($report_id) : null;

	if (!$report) {
		return new WP_Error('not_found', 'Report not found.', ['status' => 404]);
	}

	$payload_raw = $report->payload_json ?? [];
	$payload = function_exists('fi_report_payload_normalize')
		? fi_report_payload_normalize($payload_raw)
		: (is_string($payload_raw) ? json_decode($payload_raw, true) : (array) $payload_raw);

	return new WP_REST_Response([
		'data' => [
			'report'  => $report,
			'payload' => is_array($payload) ? $payload : [],
		],
	]);
}

/**
 * Create user list.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function fi_rest_create_list(WP_REST_Request $request) {
	$user_id = get_current_user_id();
	if ($user_id <= 0) {
		return new WP_Error('not_logged_in', 'You must be logged in to create a list.', ['status' => 401]);
	}

	$name = sanitize_text_field((string) $request->get_param('name'));
	$legislators = $request->get_param('legislators');
	$legislators = array_values(array_unique(array_filter(array_map('absint', is_array($legislators) ? $legislators : []))));

	if ($name === '') {
		return new WP_Error('missing_name', 'List name is required.', ['status' => 400]);
	}

	if (empty($legislators)) {
		return new WP_Error('missing_legislators', 'At least one legislator is required.', ['status' => 400]);
	}

	if (!function_exists('fi_list_save')) {
		return new WP_Error('unavailable', 'List system is unavailable.', ['status' => 500]);
	}

	$list_id = fi_list_save([
		'user_id'     => $user_id,
		'name'        => $name,
		'legislators' => $legislators,
	], null);

	if (!$list_id) {
		return new WP_Error('creation_failed', 'Failed to create list.', ['status' => 500]);
	}

	return new WP_REST_Response([
		'data' => [
			'id'          => (int) $list_id,
			'name'        => $name,
			'legislators' => $legislators,
		],
	], 201);
}

/**
 * Get user list by ID.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function fi_rest_get_list(WP_REST_Request $request) {
	$list_id = absint($request->get_param('id'));
	$list = function_exists('fi_list_get_by_id') ? fi_list_get_by_id($list_id) : null;

	if (!$list) {
		return new WP_Error('not_found', 'List not found.', ['status' => 404]);
	}

	$legislators = [];
	if (!empty($list->legislators)) {
		$decoded = json_decode((string) $list->legislators, true);
		$legislators = is_array($decoded) ? array_values(array_filter(array_map('absint', $decoded))) : [];
	}

	return new WP_REST_Response([
		'data' => [
			'list'        => $list,
			'legislators' => $legislators,
		],
	]);
}

/**
 * Global search endpoint.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function fi_rest_global_search(WP_REST_Request $request): WP_REST_Response {
	$query = sanitize_text_field((string) $request->get_param('q'));
	$type = sanitize_key((string) $request->get_param('type'));
	if (!in_array($type, ['all', 'legislators', 'votes', 'reports'], true)) {
		$type = 'all';
	}

	$results = [];

	if ($type === 'all' || $type === 'legislators') {
		$results['legislators'] = fi_rest_search_legislators($query);
	}

	if ($type === 'all' || $type === 'votes') {
		$results['votes'] = fi_rest_search_votes($query);
	}

	if ($type === 'all' || $type === 'reports') {
		$results['reports'] = fi_rest_search_reports($query);
	}

	return new WP_REST_Response([
		'data'  => $results,
		'query' => $query,
		'type'  => $type,
	]);
}

/**
 * Check REST API key.
 *
 * @param WP_REST_Request|null $request Request.
 * @return bool|WP_Error
 */
function fi_rest_check_api_key($request = null) {
	$api_key = '';

	if ($request instanceof WP_REST_Request) {
		$api_key = (string) $request->get_header('x-api-key');
		if ($api_key === '') {
			$api_key = sanitize_text_field((string) $request->get_param('api_key'));
		}
	}

	if ($api_key === '') {
		$api_key = sanitize_text_field((string) ($_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '')));
	}

	if ($api_key === '') {
		return new WP_Error('missing_api_key', 'Missing API key.', ['status' => 401]);
	}

	$valid_keys = get_option('fi_api_keys', []);
	if (!is_array($valid_keys)) {
		$valid_keys = [];
	}

	foreach ($valid_keys as $valid_key) {
		if (is_string($valid_key) && hash_equals($valid_key, $api_key)) {
			return true;
		}
	}

	return new WP_Error('invalid_api_key', 'Invalid API key.', ['status' => 403]);
}

/**
 * Add CORS support for allowed origins.
 *
 * @return void
 */
function fi_rest_add_cors_support(): void {
	$allowed_origins = fi_rest_allowed_origins();
	$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');

	if ($origin !== '' && in_array($origin, $allowed_origins, true)) {
		header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
		header('Vary: Origin', false);
		header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
		header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
		header('Access-Control-Max-Age: 86400');
	}
}

/**
 * Get allowed CORS origins.
 *
 * @return array Origins.
 */
function fi_rest_allowed_origins(): array {
	$origins = [
		'https://thefreedomindex.org',
		'https://thenewamerican.com',
		'https://freedomindex.app',
		'https://votestellthetruth.us',
	];

	if (function_exists('fi_settings_get')) {
		$settings_origins = fi_settings_get('US', 'api.cors_origins', []);
		if (is_array($settings_origins)) {
			$origins = array_merge($origins, array_filter(array_map('esc_url_raw', $settings_origins)));
		}
	}

	return array_values(array_unique($origins));
}

/**
 * Add REST cache headers.
 *
 * @param bool $served Served flag.
 * @param mixed $result REST result.
 * @param WP_REST_Request $request Request.
 * @param WP_REST_Server $server Server.
 * @return bool
 */
function fi_rest_add_cache_headers($served, $result, $request, $server): bool {
	$route = $request instanceof WP_REST_Request ? $request->get_route() : '';

	if ($route === '/fi/v1/health') {
		header('Cache-Control: no-cache, no-store, must-revalidate');
		return (bool) $served;
	}

	if (str_starts_with($route, '/fi/v1/')) {
		header('Cache-Control: public, max-age=300');
		header('ETag: "' . md5(wp_json_encode($result)) . '"');
	}

	return (bool) $served;
}

/**
 * Get legislators by government.
 *
 * @param string $gov Government code.
 * @param array $filters Filters.
 * @return array
 */
function fi_rest_get_legislators_by_gov(string $gov, array $filters = []): array {
	global $wpdb;

	$gov = strtoupper(sanitize_key($gov));
	$where = ['s.gov = %s'];
	$params = [$gov];

	if (!empty($filters['chamber']) && in_array($filters['chamber'], ['H', 'S'], true)) {
		$where[] = 'ls.chamber = %s';
		$params[] = $filters['chamber'];
	}

	if (!empty($filters['search'])) {
		$where[] = '(l.first_name LIKE %s OR l.last_name LIKE %s OR l.display_name LIKE %s)';
		$search = '%' . $wpdb->esc_like((string) $filters['search']) . '%';
		$params[] = $search;
		$params[] = $search;
		$params[] = $search;
	}

	$where_clause = implode(' AND ', $where);

	$query = "
		SELECT l.*, ls.*, s.name as session_name, s.gov
		FROM {$wpdb->prefix}fi_legislators l
		INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
		INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		WHERE {$where_clause}
		ORDER BY l.last_name ASC, l.first_name ASC
	";

	return $wpdb->get_results($wpdb->prepare($query, $params));
}

/**
 * Get votes by government.
 *
 * @param string $gov Government code.
 * @param array $filters Filters.
 * @return array
 */
function fi_rest_get_votes_by_gov(string $gov, array $filters = []): array {
	global $wpdb;

	$gov = strtoupper(sanitize_key($gov));
	$where = ['v.gov = %s'];
	$params = [$gov];

	if (!empty($filters['chamber']) && in_array($filters['chamber'], ['H', 'S'], true)) {
		$where[] = 'v.chamber = %s';
		$params[] = $filters['chamber'];
	}

	$where_clause = implode(' AND ', $where);

	$query = "
		SELECT v.*, s.name as session_name, s.gov
		FROM {$wpdb->prefix}fi_votes v
		INNER JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
		WHERE {$where_clause}
		ORDER BY v.date_voted DESC, v.id DESC
	";

	return $wpdb->get_results($wpdb->prepare($query, $params));
}

/**
 * Get legislator scores/session records.
 *
 * @param int $legislator_id Legislator ID.
 * @return array
 */
function fi_rest_get_legislator_scores(int $legislator_id): array {
	global $wpdb;

	return $wpdb->get_results($wpdb->prepare(
		"SELECT ls.*, ses.name as session_name, ses.gov
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		INNER JOIN {$wpdb->prefix}fi_sessions ses ON ls.session_id = ses.id
		WHERE ls.legislator_id = %d
		ORDER BY ses.date_start DESC",
		$legislator_id
	));
}

/**
 * Search legislators.
 *
 * @param string $query Search query.
 * @return array
 */
function fi_rest_search_legislators(string $query): array {
	global $wpdb;

	$like = '%' . $wpdb->esc_like($query) . '%';

	return $wpdb->get_results($wpdb->prepare(
		"SELECT l.id, l.display_name, ls.chamber, ls.district, ls.party
		FROM {$wpdb->prefix}fi_legislators l
		INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
		WHERE (l.first_name LIKE %s OR l.last_name LIKE %s OR l.display_name LIKE %s)
		GROUP BY l.id
		ORDER BY l.last_name ASC, l.first_name ASC
		LIMIT 20",
		$like,
		$like,
		$like
	));
}

/**
 * Search votes.
 *
 * @param string $query Search query.
 * @return array
 */
function fi_rest_search_votes(string $query): array {
	global $wpdb;

	$like = '%' . $wpdb->esc_like($query) . '%';

	return $wpdb->get_results($wpdb->prepare(
		"SELECT v.id, v.bill_number, v.title, v.date_voted as date, v.chamber, s.gov
		FROM {$wpdb->prefix}fi_votes v
		INNER JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
		WHERE (v.bill_number LIKE %s OR v.title LIKE %s)
		ORDER BY v.date_voted DESC, v.id DESC
		LIMIT 20",
		$like,
		$like
	));
}

/**
 * Search reports by ID/gov/format payload context.
 *
 * @param string $query Search query.
 * @return array
 */
function fi_rest_search_reports(string $query): array {
	global $wpdb;

	$like = '%' . $wpdb->esc_like($query) . '%';
	$id = is_numeric($query) ? absint($query) : 0;

	if ($id > 0) {
		return $wpdb->get_results($wpdb->prepare(
			"SELECT id, gov, date_created
			FROM {$wpdb->prefix}fi_reports
			WHERE id = %d
			LIMIT 20",
			$id
		));
	}

	return $wpdb->get_results($wpdb->prepare(
		"SELECT id, gov, date_created
		FROM {$wpdb->prefix}fi_reports
		WHERE gov LIKE %s OR payload_json LIKE %s
		ORDER BY id DESC
		LIMIT 20",
		$like,
		$like
	));
}