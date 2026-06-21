<?php
namespace FI\Admin;

if (!defined('ABSPATH')) exit;

/**
 * REST API for Freedom Index Admin
 * 
 * Provides comprehensive REST API endpoints for data access.
 * Includes CORS support, caching, and API key authentication.
 */
final class RestAPI {

    /**
     * Initialize REST API
     */
    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
        add_action('rest_api_init', [self::class, 'add_cors_support']);
        add_filter('rest_pre_serve_request', [self::class, 'add_cache_headers'], 10, 4);
    }

    /**
     * Register REST routes
     */
    public static function register_routes(): void {
        $namespace = 'fi/v1';
        
        // Health check
        register_rest_route($namespace, '/health', [
            'methods' => 'GET',
            'callback' => [self::class, 'health_check'],
            'permission_callback' => '__return_true'
        ]);
        
        // Legislators endpoints
        register_rest_route($namespace, '/legislators', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_legislators'],
            'permission_callback' => [self::class, 'check_api_key'],
            'args' => [
                'gov' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'session' => [
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint'
                ],
                // Summary: chamber is the canonical filter (S/H).
                'chamber' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'q' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        register_rest_route($namespace, '/legislators/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_legislator'],
            'permission_callback' => [self::class, 'check_api_key'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Votes endpoints
        register_rest_route($namespace, '/votes', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_votes'],
            'permission_callback' => [self::class, 'check_api_key'],
            'args' => [
                'gov' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'session' => [
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint'
                ],
                // Summary: chamber is the canonical filter (S/H).
                'chamber' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        register_rest_route($namespace, '/votes/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_vote'],
            'permission_callback' => [self::class, 'check_api_key'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // ZIP lookup endpoint
        register_rest_route($namespace, '/zip/(?P<zip>\d{5})', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_zip_lookup'],
            'permission_callback' => [self::class, 'check_api_key'],
            'args' => [
                'zip' => [
                    'type' => 'string',
                    'required' => true,
                    'pattern' => '\d{5}',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Reports endpoint
        register_rest_route($namespace, '/reports/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_report'],
            'permission_callback' => [self::class, 'check_api_key'],
            'args' => [
                'slug' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Lists endpoints
        register_rest_route($namespace, '/lists', [
            'methods' => 'POST',
            'callback' => [self::class, 'create_list'],
            'permission_callback' => [self::class, 'check_api_key'],
            'args' => [
                'name' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'legislators' => [
                    'type' => 'array',
                    'required' => true,
                    'items' => [
                        'type' => 'integer'
                    ]
                ]
            ]
        ]);
        
        register_rest_route($namespace, '/lists/(?P<id>[0-9]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_list'],
            'permission_callback' => [self::class, 'check_api_key'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Search endpoint
        register_rest_route($namespace, '/search', [
            'methods' => 'GET',
            'callback' => [self::class, 'global_search'],
            'permission_callback' => [self::class, 'check_api_key'],
            'args' => [
                'q' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'type' => [
                    'type' => 'string',
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
    }

    /**
     * Health check endpoint
     */
    public static function health_check(): \WP_REST_Response {
        return new \WP_REST_Response([
            'ok' => true,
            'version' => '1.0.0',
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Get legislators
     */
    public static function get_legislators(\WP_REST_Request $request): \WP_REST_Response {
        $gov = $request->get_param('gov');
        $session_id = $request->get_param('session');
        $chamber = $request->get_param('chamber');
        $search = $request->get_param('q');
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        
        $filters = array_filter([
            'chamber' => $chamber,
            'search' => $search
        ]);
        
        if ($session_id) {
            $legislators = fi_legislators_get_by_session($session_id, $filters);
        } else {
            $legislators = self::get_legislators_by_gov($gov, $filters);
        }
        
        // Paginate results
        $total = count($legislators);
        $offset = ($page - 1) * $per_page;
        $legislators = array_slice($legislators, $offset, $per_page);
        
        return new \WP_REST_Response([
            'data' => $legislators,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => ceil($total / $per_page)
            ]
        ]);
    }

    /**
     * Get single legislator
     */
    public static function get_legislator(\WP_REST_Request $request): \WP_REST_Response {
        $legislator_id = $request->get_param('id');
        
        $legislator = fi_legislator_get($legislator_id);
        if (!$legislator) {
            return new \WP_Error('not_found', 'Legislator not found', ['status' => 404]);
        }
        
        // Get legislator sessions
        $sessions = fi_legislator_sessions_get($legislator_id);
        
        // Get scores
        $scores = self::get_legislator_scores($legislator_id);
        
        return new \WP_REST_Response([
            'data' => [
                'legislator' => $legislator,
                'sessions' => $sessions,
                'scores' => $scores
            ]
        ]);
    }

    /**
     * Get votes
     */
    public static function get_votes(\WP_REST_Request $request): \WP_REST_Response {
        $gov = $request->get_param('gov');
        $session_id = $request->get_param('session');
        $chamber = $request->get_param('chamber');
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        
        $filters = array_filter([
            'chamber' => $chamber
        ]);
        // Admin API should return all statuses unless explicitly filtered.
        $filters['status'] = null;
        $filters['cache'] = false;
        
        if ($session_id) {
            $votes = fi_votes_get_by_session($session_id, $filters);
        } else {
            $votes = self::get_votes_by_gov($gov, $filters);
        }
        
        // Paginate results
        $total = count($votes);
        $offset = ($page - 1) * $per_page;
        $votes = array_slice($votes, $offset, $per_page);
        
        return new \WP_REST_Response([
            'data' => $votes,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => ceil($total / $per_page)
            ]
        ]);
    }

    /**
     * Get single vote
     */
    public static function get_vote(\WP_REST_Request $request): \WP_REST_Response {
        $vote_id = $request->get_param('id');
        
        global $wpdb;
        $vote = $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, s.name as session_name, s.gov
             FROM {$wpdb->prefix}fi_votes v
             INNER JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
             WHERE v.id = %d",
            $vote_id
        ));
        
        if (!$vote) {
            return new \WP_Error('not_found', 'Vote not found', ['status' => 404]);
        }
        
        // Get roll call data
        $roll_calls = fi_rollcalls_get_by_vote($vote_id);
        
        return new \WP_REST_Response([
            'data' => [
                'vote' => $vote,
                'roll_calls' => $roll_calls
            ]
        ]);
    }

    /**
     * Get ZIP lookup via Geocod.io API
     */
    public static function get_zip_lookup(\WP_REST_Request $request): \WP_REST_Response {
        $zip = $request->get_param('zip');
        
        // Use Geocod.io API for real-time legislator lookup
        $officials = \FI\Public\LegislatorLookup::get_officials_with_scores($zip);
        
        if (empty($officials)) {
            return new \WP_Error('not_found', 'No officials found for ZIP code', ['status' => 404]);
        }
        
        return new \WP_REST_Response([
            'data' => [
                'zip' => $zip,
                'officials' => $officials
            ]
        ]);
    }

    /**
     * Get report
     */
    public static function get_report(\WP_REST_Request $request): \WP_REST_Response {
        $report_id = $request->get_param('id');
        
        $report = fi_report_get((int)$report_id);
        if (!$report) {
            return new \WP_Error('not_found', 'Report not found', ['status' => 404]);
        }
        
        $payload = json_decode($report->payload_json, true);
        
        return new \WP_REST_Response([
            'data' => [
                'report' => $report,
                'payload' => $payload
            ]
        ]);
    }

    /**
     * Create list
     */
    public static function create_list(\WP_REST_Request $request): \WP_REST_Response {
        $name = $request->get_param('name');
        $legislators = $request->get_param('legislators');
        
        $slug = SlugGenerator::generate_list_slug($name);
        
        global $wpdb;
        $result = $wpdb->insert(
            "{$wpdb->prefix}fi_user_lists",
            [
                'user_id' => get_current_user_id(),
                'name' => $name,
                'slug' => $slug,
                'legislators' => json_encode($legislators)
            ],
            ['%d', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return new \WP_Error('creation_failed', 'Failed to create list', ['status' => 500]);
        }
        
        return new \WP_REST_Response([
            'data' => [
                'id' => $wpdb->insert_id,
                'slug' => $slug,
                'name' => $name,
                'legislators' => $legislators
            ]
        ], 201);
    }

    /**
     * Get list
     */
    public static function get_list(\WP_REST_Request $request): \WP_REST_Response {
        $list_id = (int) $request->get_param('id');
        $list = fi_list_get_by_id($list_id);
        if (!$list) {
            return new \WP_Error('not_found', 'List not found', ['status' => 404]);
        }
        
        $legislators = json_decode($list->legislators, true);
        
        return new \WP_REST_Response([
            'data' => [
                'list' => $list,
                'legislators' => $legislators
            ]
        ]);
    }

    /**
     * Global search
     */
    public static function global_search(\WP_REST_Request $request): \WP_REST_Response {
        $query = $request->get_param('q');
        $type = $request->get_param('type');
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        
        $results = [];
        
        if ($type === 'all' || $type === 'legislators') {
            $results['legislators'] = self::search_legislators($query);
        }
        
        if ($type === 'all' || $type === 'votes') {
            $results['votes'] = self::search_votes($query);
        }
        
        if ($type === 'all' || $type === 'reports') {
            $results['reports'] = self::search_reports($query);
        }
        
        return new \WP_REST_Response([
            'data' => $results,
            'query' => $query,
            'type' => $type
        ]);
    }

    /**
     * Check API key
     */
    public static function check_api_key(): bool {
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
        
        if (empty($api_key)) {
            return false;
        }
        
        // Check against stored API keys
        $valid_keys = get_option('fi_api_keys', []);
        
        return in_array($api_key, $valid_keys);
    }

    /**
     * Add CORS support
     */
    public static function add_cors_support(): void {
        $allowed_origins = [
            'https://thefreedomindex.org',
            'https://thenewamerican.com',
            'https://freedomindex.app'
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowed_origins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
            header('Access-Control-Max-Age: 86400');
        }
    }

    /**
     * Add cache headers
     */
    public static function add_cache_headers($served, $result, $request, $server): bool {
        if ($request->get_route() === '/fi/v1/health') {
            header('Cache-Control: no-cache');
            return $served;
        }
        
        // Add cache headers for other endpoints
        header('Cache-Control: public, max-age=300'); // 5 minutes
        header('ETag: "' . md5(serialize($result)) . '"');
        
        return $served;
    }

    /**
     * Get legislators by gov
     */
    private static function get_legislators_by_gov(string $gov, array $filters = []): array {
        global $wpdb;
        
        $where = ["s.gov = %s"];
        $params = [$gov];
        
        // Summary: chamber is the canonical filter.
        if (!empty($filters['chamber'])) {
            $where[] = "ls.chamber = %s";
            $params[] = $filters['chamber'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(l.first_name LIKE %s OR l.last_name LIKE %s OR l.display_name LIKE %s)";
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
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
            ORDER BY l.last_name, l.first_name
        ";
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Get votes by gov
     */
    private static function get_votes_by_gov(string $gov, array $filters = []): array {
        global $wpdb;
        
        $where = ["v.gov = %s"];
        $params = [$gov];
        
        if (!empty($filters['chamber'])) {
            $where[] = "v.chamber = %s";
            $params[] = $filters['chamber'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "
            SELECT v.*, s.name as session_name, s.gov
            FROM {$wpdb->prefix}fi_votes v
            INNER JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
            WHERE {$where_clause}
            ORDER BY v.date_voted DESC, v.slug
        ";
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Get legislator scores
     */
    private static function get_legislator_scores(int $legislator_id): array {
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
     * Get legislators by ZIP
     */
    private static function get_legislators_by_zip(string $zip): array {
        // This would need to be implemented based on ZIP mapping data
        // For now, return empty array
        return [];
    }

    /**
     * Search legislators
     */
    private static function search_legislators(string $query): array {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.id, l.display_name, ls.chamber, ls.district, ls.party
             FROM {$wpdb->prefix}fi_legislators l
             INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
             WHERE (l.first_name LIKE %s OR l.last_name LIKE %s OR l.display_name LIKE %s)
             LIMIT 20",
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%'
        ));
    }

    /**
     * Search votes
     */
    private static function search_votes(string $query): array {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT v.id, v.slug, v.bill_number, v.title, v.date_voted as date, v.chamber, s.gov
             FROM {$wpdb->prefix}fi_votes v
             INNER JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
             WHERE (v.bill_number LIKE %s OR v.title LIKE %s OR v.slug LIKE %s)
             LIMIT 20",
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%'
        ));
    }

    /**
     * Search reports
     */
    private static function search_reports(string $query): array {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, slug, gov, date_created
             FROM {$wpdb->prefix}fi_reports
             WHERE slug LIKE %s
             LIMIT 20",
            '%' . $wpdb->esc_like($query) . '%'
        ));
    }
}

// Initialize REST API
RestAPI::init();
