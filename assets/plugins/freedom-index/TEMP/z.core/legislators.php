<?php
namespace FI\Core {

	if (!defined('ABSPATH')) exit;

	// Load Meta trait
	require_once __DIR__ . '/traits/meta.php';

	/**
	* Legislators Table I/O Operations
	* One query. One rich object. Session-aware. Template-friendly.
	* 
	* Primary fields reflect the requested session (if provided) or latest session (if not).
	* Full session history always included in $legislator->sessions array.
	*/
	final class Legislators {
		
		// Use unified meta handling trait
		use \FI\Core\Traits\Meta;
		
		/**
		 * Request-level cache for get() results
		 * Key: md5 hash of serialized args
		 */
		private static $cache_get = [];

		/**
		 * Optional timing instrumentation for get()
		 * Summary: enabled only when explicitly requested (debug_timing).
		 */
		private static bool $timing_active = false;
		private static array $timing_stats = [
			'json_decode_ms' => 0.0,
			'json_decode_calls' => 0,
		];

		/**
		* Master retrieval method — returns rich, session-aware legislator objects
		* 
		* When session_id is provided: Primary fields reflect that specific session's data
		* When session_id is NOT provided: Primary fields reflect latest session data
		* Always includes full session history in $legislator->sessions array
		* 
		* Fixed: GROUP BY compliance, window function fallback, session hierarchy, JSON null safety
		*/
		public static function get(array $args = []): array {
			global $wpdb;

			$defaults = [
				'session_id' => null,
				'gov'        => null,
				// How to interpret gov filter when session_id is not provided:
				// - 'primary' (default): only legislators whose MOST RECENT session is in this gov.
				// - 'any': legislators who have ANY session assignment in this gov.
				'gov_mode'   => 'primary',
				'state'      => null,
				'party'      => null,
				'chamber'   => null,
				'search'     => null,
				'id'         => null,
				'bioguide_id' => null,
				'lis_id' => null,
				'legiscan_id' => null,
				'govtrack_id' => null,
				'votesmart_id' => null,
				'ballotpedia_id' => null,
				'orderby'    => 'last_name',
				'order'      => 'ASC',
				'per_page'   => 600,
				'page'       => 1,
				'limit'      => null,
				'offset'     => 0,
				// Disable filesystem caching for this call (useful for admin edit screens).
				'no_cache'   => false,
				// Debug: emit fi_log timing metrics (disabled by default).
				'debug_timing' => false,
			];
			$args = wp_parse_args($args, $defaults);

			// Timing mode: disable caches so numbers reflect real work.
			$timing_on = !empty($args['debug_timing']) || (defined('FI_DEBUG_TIMING') && FI_DEBUG_TIMING);
			if ($timing_on) {
				$args['no_cache'] = true;
			}

			$t0 = $timing_on ? microtime(true) : 0.0;
			$last = $t0;
			$marks = [];
			$mark = static function(string $label) use (&$marks, &$last, $t0): void {
				$now = microtime(true);
				$marks[$label] = [
					'ms' => round(($now - $t0) * 1000, 2),
					'dt_ms' => round(($now - $last) * 1000, 2),
				];
				$last = $now;
			};
			if ($timing_on) {
				self::$timing_active = true;
				self::$timing_stats = ['json_decode_ms' => 0.0, 'json_decode_calls' => 0];
				$mark('start');
			}

			// Check request-level cache first (fastest)
			$cache_key = md5(serialize($args));
			if (!$timing_on && isset(self::$cache_get[$cache_key])) {
				return self::$cache_get[$cache_key];
			}

			// Cache query results (file system cache)
			$cacheKey = fi_cache_key('legislators/get', $args);
			//$results = fi_cache($cacheKey,'',0,true); //Force for testing
			$results = fi_cache($cacheKey); //1 day
			if($results){
				// Store in request-level cache
				self::$cache_get[$cache_key] = $results;
				return $results;
			}

			if ($timing_on) {
				$mark('after_cache_checks');
			}

			// NEVER run an unconstrained query
			if (empty($args['session_id']) && empty($args['gov']) && empty($args['search']) && empty($args['id']) 
				&& empty($args['bioguide_id']) && empty($args['lis_id']) && empty($args['legiscan_id']) && empty($args['govtrack_id']) && empty($args['votesmart_id']) && empty($args['ballotpedia_id'])) {
				if ($timing_on) {
					self::$timing_active = false;
				}
				return [];
			}

			$where_conditions = [];
			$where_values     = [];
			$session_ids      = [];

			// Single legislator
			if (!empty($args['id'])) {
				$where_conditions[] = 'l.id = %d';
				$where_values[]     = $args['id'];
			}

			// External ID filters (for API syncing)
			if (!empty($args['bioguide_id'])) {
				$where_conditions[] = 'l.bioguide_id = %s';
				$where_values[]     = $args['bioguide_id'];
			}
			if (!empty($args['lis_id'])) {
				$where_conditions[] = 'l.lis_id = %s';
				$where_values[]     = $args['lis_id'];
			}
			if (!empty($args['legiscan_id'])) {
				$where_conditions[] = 'l.legiscan_id = %d';
				$where_values[]     = (int)$args['legiscan_id'];
			}
			if (!empty($args['govtrack_id'])) {
				$where_conditions[] = 'l.govtrack_id = %s';
				$where_values[]     = $args['govtrack_id'];
			}
			if (!empty($args['votesmart_id'])) {
				$where_conditions[] = 'l.votesmart_id = %s';
				$where_values[]     = $args['votesmart_id'];
			}
			if (!empty($args['ballotpedia_id'])) {
				$where_conditions[] = 'l.ballotpedia_id = %s';
				$where_values[]     = $args['ballotpedia_id'];
			}
			// Session-specific roster - get hierarchy once
			if (!empty($args['session_id'])) {
//PROBLEM: We need to limit this to only the parent sessions on the front end.
				$session_ids = []; //fi_sessions_get_hierarchy_ids($args['session_id']);

//fi_log('GOV='.$args['gov'].' SESSION_ID='.$args['session_id'].' SESSION_IDS='.implode(',', $session_ids),__FILE__,__LINE__);

				// If hierarchy lookup fails, fall back to just the session_id itself
				if (empty($session_ids)) {
					$session_ids = [$args['session_id']];
				}
				$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
				$session_exists = "EXISTS (
					SELECT 1 FROM {$wpdb->prefix}fi_legislator_sessions ls_check
					JOIN {$wpdb->prefix}fi_sessions s_check ON ls_check.session_id = s_check.id
					WHERE ls_check.legislator_id = l.id 
					AND ls_check.session_id IN ($placeholders)";
				
				// Add filters to session EXISTS if provided (state, party, chamber)
				if (!empty($args['state'])) {
					$session_exists .= " AND ls_check.state = %s";
				}
				if (!empty($args['party'])) {
					$session_exists .= " AND ls_check.party = %s";
				}
				if (!empty($args['chamber'])) {
					$session_exists .= " AND ls_check.chamber = %s";
				}
				
				$session_exists .= ")";
				$where_conditions[] = $session_exists;
				// Add values in correct order: session_ids first, then filters (state, party, chamber)
				$where_values = array_merge($where_values, $session_ids);
				if (!empty($args['state'])) {
					$where_values[] = strtoupper($args['state']);
				}
				if (!empty($args['party'])) {
					$where_values[] = strtoupper($args['party']);
				}
				if (!empty($args['chamber'])) {
					$where_values[] = strtoupper($args['chamber']);
				}
			}

			// Most recent session is in this government? (only if session_id not provided, since session already filters by gov)
			// Uses same logic as primary_gov calculation (most recent session by date_start DESC)
			if (!empty($args['gov']) && empty($args['session_id'])) {
				$gov = strtoupper((string) $args['gov']);
				$gov_mode = strtolower((string) ($args['gov_mode'] ?? 'primary'));
				if ($gov_mode === 'any') {
					// Any session assignment in this gov.
					$where_conditions[] = "EXISTS (
						SELECT 1
						FROM {$wpdb->prefix}fi_legislator_sessions ls_g
						JOIN {$wpdb->prefix}fi_sessions s_g ON ls_g.session_id = s_g.id
						WHERE ls_g.legislator_id = l.id AND s_g.gov = %s
					)";
					$where_values[] = $gov;
				} else {
					// Default: most recent session is in this gov.
					$where_conditions[] = "(
						SELECT ctx_s.gov
						FROM {$wpdb->prefix}fi_legislator_sessions ctx_ls
						JOIN {$wpdb->prefix}fi_sessions ctx_s ON ctx_ls.session_id = ctx_s.id
						WHERE ctx_ls.legislator_id = l.id
						ORDER BY ctx_s.date_start DESC, ctx_s.id DESC
						LIMIT 1
					) = %s";
					$where_values[] = $gov;
				}
			}

			// State filter (only if session_id not provided, since session filter already handles state)
			if (!empty($args['state']) && empty($args['session_id'])) {
				$where_conditions[] = "EXISTS (
					SELECT 1 FROM {$wpdb->prefix}fi_legislator_sessions ls_state
					WHERE ls_state.legislator_id = l.id AND ls_state.state = %s
				)";
				$where_values[] = strtoupper($args['state']);
			}

			// Party filter (only if session_id not provided, since session filter already handles party)
			if (!empty($args['party']) && empty($args['session_id'])) {
				$where_conditions[] = "EXISTS (
					SELECT 1 FROM {$wpdb->prefix}fi_legislator_sessions ls_party
					WHERE ls_party.legislator_id = l.id AND ls_party.party = %s
				)";
				$where_values[] = strtoupper($args['party']);
			}

			// chamber filter (only if session_id not provided, since session filter already handles chamber)
			if (!empty($args['chamber']) && empty($args['session_id'])) {
				$where_conditions[] = "EXISTS (
					SELECT 1 FROM {$wpdb->prefix}fi_legislator_sessions ls_chamber
					WHERE ls_chamber.legislator_id = l.id AND ls_chamber.chamber = %s
				)";
				$where_values[] = strtoupper($args['chamber']);
			}

			// Name search
			$search_term_raw = null;
			if (!empty($args['search'])) {
				// Pull raw input safely (handles slashes + weird whitespace)
				$search_term_raw = (string) $args['search'];
				$search_term_raw = wp_unslash($search_term_raw);

				// Normalize unicode whitespace + NBSP -> normal spaces
				$search_term_raw = preg_replace(
					'/[\x{00A0}\x{1680}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}]/u',
					' ',
					$search_term_raw
				);

				// Collapse all whitespace runs to a single normal space
				$search_term_raw = trim(preg_replace('/\s+/u', ' ', $search_term_raw));

				$search = '%' . $wpdb->esc_like($search_term_raw) . '%';

				$where_conditions[] = "(
					l.first_name LIKE %s
					OR l.last_name LIKE %s
					OR l.display_name LIKE %s
					OR CONCAT_WS(' ', l.first_name, l.last_name) LIKE %s
					OR CONCAT_WS(' ', l.first_name, l.middle_name, l.last_name) LIKE %s
				)";

				$where_values = array_merge($where_values, [$search, $search, $search, $search, $search]);
			}
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

			$order        = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
			$orderby      = sanitize_key((string) ($args['orderby'] ?? ''));
			if ($orderby === '') {
				$orderby = 'last_name';
			}

			// Build session selection logic - use correlated subquery for compatibility
			// When session_id provided: Prioritize that session hierarchy (priority 0), then latest (priority 1)
			// When session_id NOT provided: Just get latest by date
			$session_priority = '';
			if (!empty($session_ids)) {
				// Session ID provided: prioritize requested session hierarchy
				// Escape session IDs for CASE clause in ORDER BY (can't use prepare() in ORDER BY)
				// But we MUST keep placeholders in EXISTS clause for wpdb->prepare()
				$session_ids_escaped = array_map('intval', $session_ids);
				$session_placeholders_escaped = implode(',', $session_ids_escaped);
				$session_priority = "CASE WHEN ctx_ls.session_id IN ($session_placeholders_escaped) THEN 0 ELSE 1 END,";
			}
			// If no session_ids, $session_priority stays empty = just order by date (latest first)
			
			// Status filter for primary session selection (public side: only published sessions)
			$status_filter = '';
			if (!is_admin() && !defined('DOING_AJAX')) {
				$status_filter = "AND ctx_s.status = 'publish'";
			}
			
			// Parent session filter (only apply when no specific session requested)
			// When viewing a specific child session, we want to show that session's data
			$parent_filter = '';
			if (empty($session_ids)) {
				$parent_filter = "AND ctx_s.parent_id IS NULL";
			}

			// Main query - select specific columns to avoid GROUP BY issues
			// Use correlated subqueries for context session (works on MySQL 5.7+ and MariaDB 10.2+)
			// Wrapped in derived table to allow computed columns for sorting
			$sql = "
				SELECT 
					base_query.*,
					-- Computed columns for sorting (extracted from primary_session_json)
					JSON_UNQUOTE(JSON_EXTRACT(base_query.primary_session_json, '$.party')) AS primary_party,
					JSON_UNQUOTE(JSON_EXTRACT(base_query.primary_session_json, '$.chamber')) AS primary_chamber,
					JSON_UNQUOTE(JSON_EXTRACT(base_query.primary_session_json, '$.state')) AS primary_state
				FROM (
					SELECT
						l.id,
						l.first_name,
						l.middle_name,
						l.last_name,
						l.display_name,
						l.image_id,
						l.image_url,
						l.bioguide_id,
						l.lis_id,
						l.legiscan_id,
						l.govtrack_id,
						l.votesmart_id,
						l.ballotpedia_id,
						l.score AS freedom_score,
						l.score_data AS freedom_score_data,
						l.score_date AS freedom_score_date,
						l.meta,
						l.audit_log,
						l.date_created,
						l.date_updated,

					-- Primary session (requested session if provided, otherwise latest) - single optimized subquery
					(
						SELECT JSON_OBJECT(
							'session_id', ctx_ls.session_id,
							'session_name', ctx_s.name,
							'gov', ctx_s.gov,
							'state', ctx_ls.state,
							'party', ctx_ls.party,
							'chamber', ctx_ls.chamber,
							'district', ctx_ls.district,
							'score', ctx_ls.score,
							'score_data', ctx_ls.score_data,
							'date_start', ctx_ls.date_start,
							'date_end', ctx_ls.date_end,
							'image_id', ctx_ls.image_id
						)
					FROM {$wpdb->prefix}fi_legislator_sessions ctx_ls
					JOIN {$wpdb->prefix}fi_sessions ctx_s ON ctx_ls.session_id = ctx_s.id
					WHERE ctx_ls.legislator_id = l.id
					$status_filter
					$parent_filter
					ORDER BY $session_priority ctx_s.date_end DESC, ctx_s.id DESC
					LIMIT 1
					) AS primary_session_json,

					-- All sessions as JSON (aggregated subquery)
					COALESCE(
						(
							SELECT JSON_ARRAYAGG(
								JSON_OBJECT(
									'session_id',     all_ls.session_id,
									'parent_id',      all_s.parent_id,
									'legiscan_id',    all_s.legiscan_id,
									'session_name',   all_s.name,
									'gov',            all_s.gov,
									'state',          all_ls.state,
									'party',          all_ls.party,
									'chamber',         all_ls.chamber,
									'district',       all_ls.district,
									'score',          all_ls.score,
									'score_data',     all_ls.score_data,
									'date_start',     all_s.date_start,
									'date_end',       all_s.date_end,
									'image_id',       all_ls.image_id,
									'status',         all_s.status
								) ORDER BY all_s.date_start DESC, all_s.id DESC
							)
							FROM {$wpdb->prefix}fi_legislator_sessions all_ls
							JOIN {$wpdb->prefix}fi_sessions all_s ON all_ls.session_id = all_s.id
							WHERE all_ls.legislator_id = l.id
						),
						JSON_ARRAY()
					) AS sessions_json

					FROM {$wpdb->prefix}fi_legislators l
					$where_clause
				) AS base_query
				ORDER BY " . self::build_order_by($search_term_raw, $orderby, $order) . "
			";
			if ($timing_on) {
				$mark('sql_built');
			}

			// Pagination - add before prepare() so it's part of the prepared query
			if ($args['limit'] > 0) {
				$limit = intval($args['limit']);
				$offset = intval($args['offset']);
				$sql .= " LIMIT {$limit} OFFSET {$offset}";
			} elseif ($args['per_page'] > 0) {
				$limit = intval($args['per_page']);
				$offset = intval(($args['page'] - 1) * $args['per_page']);
				$sql .= " LIMIT {$limit} OFFSET {$offset}";
			}

			// Prepare main query (including LIMIT clause)
			if (!empty($where_values)) {
				// Debug: Log the query and values for troubleshooting
				self::log('Legislators::get() - SQL before prepare: ' . $sql, __FILE__, __LINE__, 'debug');
				self::log('Legislators::get() - Where values count: ' . count($where_values) . ' | Values: ' . json_encode($where_values), __FILE__, __LINE__, 'debug');
				
				// Count placeholders in SQL
				$placeholder_count = substr_count($sql, '%d') + substr_count($sql, '%s') + substr_count($sql, '%f');
				self::log('Legislators::get() - Placeholder count in SQL: ' . $placeholder_count, __FILE__, __LINE__, 'debug');
				$sql = $wpdb->prepare($sql, $where_values);
			
				if ($wpdb->last_error) {
					self::log('Legislators::get() - wpdb error: ' . $wpdb->last_error, __FILE__, __LINE__, 'error');
				}
			}
			if ($timing_on) {
				$mark('sql_prepared');
			}

			$results = $wpdb->get_results($sql);
			self::log('Legislators::get() - wpdb last_query: ' . ($wpdb->last_query ?? ''), __FILE__, __LINE__, 'debug');
			if ($timing_on) {
				$mark('db_get_results');
			}

			// Ensure $results is an array before mapping
			if (!is_array($results)) {
				if ($timing_on) {
					self::$timing_active = false;
				}
				return [];
			}
			$results = array_map([self::class, 'format_object'], $results);

			if ($timing_on) {
				$mark('format_object_map');
			}
			fi_cache($cacheKey,$results);
			// Store in request-level cache
			self::$cache_get[$cache_key] = $results;

			if ($timing_on && function_exists('fi_log')) {
				$mark('cached_and_return');
				$payload = [
					'marks' => $marks,
					'counts' => [
						'rows' => is_array($results) ? count($results) : 0,
						'where_values' => is_array($where_values) ? count($where_values) : 0,
					],
					'decode' => [
						'json_decode_ms' => round((float) (self::$timing_stats['json_decode_ms'] ?? 0.0), 2),
						'json_decode_calls' => (int) (self::$timing_stats['json_decode_calls'] ?? 0),
					],
					'args' => [
						'gov' => $args['gov'] ?? null,
						'session_id' => $args['session_id'] ?? null,
						'state' => $args['state'] ?? null,
						'party' => $args['party'] ?? null,
						'chamber' => $args['chamber'] ?? null,
						'search' => $args['search'] ?? null,
						'limit' => $args['limit'] ?? null,
						'per_page' => $args['per_page'] ?? null,
						'offset' => $args['offset'] ?? null,
					],
				];

				self::log('TIMING Legislators::get ' . wp_json_encode($payload), __FILE__, __LINE__, 'debug');
			}
			self::$timing_active = false;

			return $results;
		}


		/**
		* Build ORDER BY clause (supports explicit admin sorting and search prioritization).
		* 
		* - If no search term: honors $orderby ($order) with sensible secondary sorts
		* - If search term present and ordering by name: prioritizes exact/word/start/contains matches
		*/
		private static function build_order_by(?string $search_term, string $orderby, string $order): string {
			global $wpdb;

			// Normalize allowed orderby keys (defensive: ORDER BY cannot be parameterized).
			$orderby = sanitize_key($orderby);
			if ($orderby === 'slug') {
				// Legacy/admin column label; slug no longer exists, treat as ID.
				$orderby = 'id';
			}
			$allowed = ['id', 'name', 'last_name', 'party', 'chamber', 'state', 'score', 'updated', 'created'];
			if (!in_array($orderby, $allowed, true)) {
				$orderby = 'last_name';
			}

			// Map orderby keys to safe SQL expressions.
			// Note: For fields that may be NULL, use NULLS LAST pattern (NULL values sort last)
			// Note: These references are in the outer query. Columns from base_query.* are available directly,
			// and computed columns (primary_party, etc.) are defined in the outer SELECT.
			$order_expr = match ($orderby) {
				'id' => "id {$order}",
				'created' => "date_created {$order}, id DESC",
				'updated' => "date_updated {$order}, id DESC",
				'party' => "primary_party {$order}, last_name ASC, first_name ASC",
				'chamber' => "primary_chamber {$order}, last_name ASC, first_name ASC",
				'state' => "primary_state {$order}, last_name ASC, first_name ASC",
				'score' => "freedom_score {$order}, last_name ASC, first_name ASC",
				// name / last_name default
				default => "last_name {$order}, first_name ASC",
			};

			// If no search term, use requested ordering.
			if (empty($search_term)) {
				return $order_expr;
			}

			// If we're not ordering by name, don't do match-priority ordering.
			if (!in_array($orderby, ['name', 'last_name'], true)) {
				return $order_expr;
			}
			
			// Escape search term for SQL injection protection
			// Use esc_sql for values that will be in ORDER BY (not in prepared statement placeholders)
			$search_escaped_sql = esc_sql($search_term);
			$search_escaped_like = $wpdb->esc_like($search_term);
			
			// For word boundary regex, escape special regex characters
			$search_regex_escaped = preg_quote($search_escaped_sql, '/');
			
			// Build CASE-based priority ordering
			// Priority 1: Exact match on first_name or last_name (case-insensitive)
			// Priority 2: Whole word match (word boundaries) - uses MySQL word boundary syntax
			// Priority 3: Starts with search term (case-insensitive)
			// Priority 4: Contains search term (everything else)
			// Note: These references are in the outer query. Columns from base_query.* are available directly.
			$order_by = "
				CASE 
					WHEN LOWER(first_name) = LOWER('{$search_escaped_sql}') 
						OR LOWER(last_name) = LOWER('{$search_escaped_sql}') THEN 1
					WHEN first_name REGEXP CONCAT('[[:<:]]', '{$search_regex_escaped}', '[[:>:]]')
						OR last_name REGEXP CONCAT('[[:<:]]', '{$search_regex_escaped}', '[[:>:]]') THEN 2
					WHEN LOWER(first_name) LIKE LOWER('{$search_escaped_like}%')
						OR LOWER(last_name) LIKE LOWER('{$search_escaped_like}%') THEN 3
					ELSE 4
				END ASC,
				last_name {$order}, 
				first_name ASC
			";
			
			return trim($order_by);
		}

		/**
		* Format raw row into rich, template-ready object
		* Primary fields reflect requested session (if provided) or latest session (if not)
		*/
		private static function format_object($row): object {
			$timing_on = self::$timing_active;
			$decode_ms = 0.0;
			$decode_calls = 0;
			$decode = static function($json, bool $assoc = false) use ($timing_on, &$decode_ms, &$decode_calls): mixed {
				if (!$timing_on) {
					return json_decode($json, $assoc);
				}
				$start = microtime(true);
				$out = json_decode($json, $assoc);
				$decode_ms += (microtime(true) - $start) * 1000;
				$decode_calls++;
				return $out;
			};

			// Build display_name if blank
			$display_name = $row->display_name;
			if (empty($display_name)) {
				$name_parts = array_filter([$row->first_name, $row->middle_name, $row->last_name]);
				$display_name = !empty($name_parts) ? implode(' ', $name_parts) : '';
			}
			
			$leg = (object)[
				'id'           => (int)$row->id,
				'first_name'   => $row->first_name,
				'middle_name'  => $row->middle_name,
				'last_name'    => $row->last_name,
				'display_name' => $display_name,
				'image_id'     => $row->image_id ? (int)$row->image_id : null,
				'image_url'    => $row->image_url ?? null,
				'bioguide_id'  => $row->bioguide_id ?? null,
				'lis_id'       => $row->lis_id ?? null,
				'legiscan_id'  => $row->legiscan_id ? (int)$row->legiscan_id : null,
				'govtrack_id'  => $row->govtrack_id ?? null,
				'votesmart_id' => $row->votesmart_id ?? null,
				'ballotpedia_id' => $row->ballotpedia_id ?? null,
				'url'          => fi_get_legislator_url($row->id),
				'freedom_score' => $row->freedom_score !== null ? (int)$row->freedom_score : null,
				'freedom_score_data' => $row->freedom_score_data ? $decode($row->freedom_score_data, false) : null,
				'freedom_score_date' => $row->freedom_score_date,
				'meta'         => $row->meta ? $decode($row->meta, true) : null,
				'audit_log'    => $row->audit_log ? $decode($row->audit_log, true) : null,
				'date_created' => $row->date_created,
				'date_updated' => $row->date_updated,
			];

			// Primary session data (requested session if provided, otherwise latest) - parse from JSON
			$primary_json = $row->primary_session_json ?? null;
			if ($primary_json && is_string($primary_json)) {
				$primary_data = $decode($primary_json, true);
				if (!is_array($primary_data)) {
					$primary_data = [];
				}
			} else {
				$primary_data = [];
			}
			
			$primary = (object)[
				'session_id'        => !empty($primary_data['session_id']) ? (int)$primary_data['session_id'] : null,
				'session_name'      => $primary_data['session_name'] ?? null,
				'gov'               => $primary_data['gov'] ?? null,
				'state'             => $primary_data['state'] ?? null,
				'party'             => $primary_data['party'] ?? null,
				'chamber'            => $primary_data['chamber'] ?? null,
				'district'          => $primary_data['district'] ?? null,
				'score'             => isset($primary_data['score']) && $primary_data['score'] !== null ? (int)$primary_data['score'] : null,
				'score_data'        => !empty($primary_data['score_data']) ? (is_string($primary_data['score_data']) ? $decode($primary_data['score_data'], false) : (object)$primary_data['score_data']) : null,
				'session_image_id'  => !empty($primary_data['image_id']) ? (int)$primary_data['image_id'] : null,
				'date_start'        => $primary_data['date_start'] ?? null,
				'date_end'          => $primary_data['date_end'] ?? null,
			];

			// Human-readable labels (zero queries - uses static arrays)
			// The leading backslash (\) forces the use of the global namespace, ensuring we call the global fi_gov_name() function.
			$primary->gov_name       = $primary->gov ? \fi_gov_name($primary->gov) : null;
			$primary->party_name     = $primary->party ? \fi_party_name($primary->party) : null;
			$primary->chamber_label = $primary->chamber && $primary->gov ? \fi_chamber_label($primary->gov, $primary->chamber) : null;
			$primary->chamber_title = $primary->chamber && $primary->gov ? \fi_chamber_title($primary->gov, $primary->chamber) : null;
			// Summary: canonical chamber abbreviation used for displays like "AK-H" / "H".
			$primary->state_name     = $primary->state ? \fi_state_name($primary->state) : null;

			// Set main score to freedom_score (not session score)
			// The Freedom score represents the legislator's overall performance across all sessions
			$leg->score = $leg->freedom_score;
			
			// Store session score separately for reference
			$primary->session_score = $primary->score;
			
			// Get district_info if district exists and is numeric (fi_district_get expects int)
			if (!empty($primary->district) && is_numeric($primary->district) && function_exists('fi_district_get')) {
				$leg->district_info = fi_district_get((int) $primary->district);
			} else {
				$leg->district_info = null;
			}

			//Get state full name if primary state defined.
			if (!empty($primary->state) && function_exists('fi_state_name')) {
				$leg->state_name = fi_state_name($primary->state);
			} else {
				$leg->state_name = null;
			}
			
			// Flatten primary session data onto main object — templates just use $legislator->party, etc.
			// Note: image_id and score are NOT overwritten - we keep the main legislator image_id and freedom score
			foreach ($primary as $key => $value) {
				// Skip image_id and score from primary (we have session_image_id and freedom_score instead)
				if ($key !== 'image_id' && $key !== 'score') {
					$leg->{$key} = $value;
				}
			}

			// Full session history with labels - safe JSON handling
			// Indexed by session_id for direct access: $leg->sessions[$session_id]
			$sessions_json = $row->sessions_json ?? null;
			if ($sessions_json && is_string($sessions_json)) {
				$all = $decode($sessions_json, true);
				if (!is_array($all)) {
					$all = [];
				}
			} else {
				$all = [];
			}

			$leg->sessions = [];
			foreach ($all as $s) {
				if (!is_array($s)) {
					continue;
				}
				// Skip child sessions on frontend (public display)
				$parent_id = $s['parent_id'] ?? null;
				if (!is_admin() && !empty($parent_id)) {
					continue;
				}
				// Skip draft sessions on frontend (public display)
				$status = $s['status'] ?? 'draft';
				if (!is_admin() && $status !== 'publish') {
					continue;
				}

				$sess = (object)$s;
				$sess->gov_name       = !empty($sess->gov) ? \fi_gov_name($sess->gov) : null;
				$sess->party_name     = !empty($sess->party) ? \fi_party_name($sess->party) : null;
				$sess->chamber_label = !empty($sess->chamber) && !empty($sess->gov) ? \fi_chamber_label($sess->gov, $sess->chamber) : null;
				$sess->chamber_title = !empty($sess->chamber) && !empty($sess->gov) ? \fi_chamber_title($sess->gov, $sess->chamber) : null;
				// Summary: canonical chamber abbreviation used for displays like "AK-H" / "H".
				$sess->state_name     = !empty($sess->state) ? \fi_state_name($sess->state) : null;
				// Add " Congress" suffix for US sessions
				if (!empty($sess->session_name) && strtoupper($sess->gov ?? '') === 'US' && strpos($sess->session_name, 'Congress') === false) {
					$sess->session_name .= ' Congress';
				}
				// Use session_id as array key for direct access
				$session_id = $sess->session_id ?? null;
				if ($session_id !== null) {
					$leg->sessions[$session_id] = $sess;
				}
			}

			if ($timing_on) {
				self::$timing_stats['json_decode_ms'] = (float) (self::$timing_stats['json_decode_ms'] ?? 0.0) + (float) $decode_ms;
				self::$timing_stats['json_decode_calls'] = (int) (self::$timing_stats['json_decode_calls'] ?? 0) + (int) $decode_calls;
			}

			return $leg;
		}

		// Simple internal getters
		public static function get_by_id(int $id): ?object {
			// Direct simple lookup - don't use complex get() for single ID
			global $wpdb;
			$table = $wpdb->prefix . 'fi_legislators';
			$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
			if (!$row) {
				return null;
			}
			// Format raw row into rich object with session data
			return self::format_object($row);
		}

		/**
		* Get legislator with all sessions (efficient single query for header)
		* Returns legislator object enriched with session data
		*/
		public static function get_with_sessions(int $legislator_id): ?object {
			global $wpdb;
			
			fi_log("get_with_sessions: Starting for legislator_id={$legislator_id}");
			
			// Get legislator base data
			$legislator = self::get_by_id($legislator_id);
			if (!$legislator) {
				fi_log("get_with_sessions: get_by_id returned null");
				return null;
			}
			fi_log("get_with_sessions: Got legislator base data, display_name={$legislator->display_name}");
			
			// Get all sessions for this legislator
			$sessions_table = $wpdb->prefix . 'fi_legislator_sessions';
			$fi_sessions_table = $wpdb->prefix . 'fi_sessions';
			
			$sessions = $wpdb->get_results($wpdb->prepare("
				SELECT 
					s.id as session_id,
					s.name as session_name,
					s.date_start,
					s.date_end,
					s.gov,
					ls.session_score,
					ls.session_score_data,
					ls.chamber,
					ls.district,
					ls.party,
					ls.image_id,
					ls.score as lifetime_score
				FROM {$sessions_table} ls
				INNER JOIN {$fi_sessions_table} s ON ls.session_id = s.id
				WHERE ls.legislator_id = %d
					AND s.parent_id IS NULL
				ORDER BY s.date_end DESC, s.id DESC
			", $legislator_id));
			fi_log("get_with_sessions: Sessions query returned " . count($sessions) . " sessions");
			
			// Add lookup values
			foreach ($sessions as &$session) {
				$session->gov_name = FI_GOVERNMENTS[$session->gov]['name'] ?? $session->gov;
				$session->state_name = FI_GOVERNMENTS[$session->gov]['state_name'] ?? '';
				$session->party_name = FI_PARTIES[$session->party] ?? $session->party;
				$session->chamber_label = FI_CHAMBERS[$session->chamber]['label'] ?? $session->chamber;
				$session->chamber_title = FI_CHAMBERS[$session->chamber]['title'] ?? '';
			}
			
			$legislator->sessions = $sessions;
			fi_log("get_with_sessions: Set sessions on legislator");
			
			// Set current/most recent session data on legislator (flatten like API does)
			if (!empty($sessions)) {
				$current = $sessions[0];
				fi_log("get_with_sessions: Current session - party={$current->party}, chamber={$current->chamber}, district={$current->district}, score={$current->lifetime_score}");
				$legislator->session_id = $current->session_id;
				$legislator->session_name = $current->session_name;
				$legislator->gov = $current->gov;
				$legislator->state = FI_GOVERNMENTS[$current->gov]['state'] ?? '';
				$legislator->party = $current->party;
				$legislator->chamber = $current->chamber;
				$legislator->district = $current->district;
				$legislator->session_score = $current->session_score;
				$legislator->session_score_data = $current->session_score_data;
				$legislator->image_id = $current->image_id;
				$legislator->date_start = $current->date_start;
				$legislator->date_end = $current->date_end;
				// Add lookup values for header display
				$legislator->gov_name = FI_GOVERNMENTS[$current->gov]['name'] ?? $current->gov;
				$legislator->state_name = FI_GOVERNMENTS[$current->gov]['state_name'] ?? '';
				$legislator->party_name = FI_PARTIES[$current->party] ?? $current->party;
				$legislator->chamber_label = FI_CHAMBERS[$current->chamber]['label'] ?? $current->chamber;
				$legislator->chamber_title = FI_CHAMBERS[$current->chamber]['title'] ?? '';
				// Lifetime score from first session
				$legislator->lifetime_score = $current->lifetime_score;
				fi_log("get_with_sessions: Set flattened data - party_name={$legislator->party_name}, state_name={$legislator->state_name}, lifetime_score={$legislator->lifetime_score}");
			} else {
				fi_log("get_with_sessions: NO SESSIONS FOUND!");
			}
			
			return $legislator;
		}

		/**
		* Get legislator by ID (formerly get_by_slug)
		* Accepts numeric string or integer ID
		*/
		public static function get_by_slug(string|int $id): ?object {
			$legislator_id = is_numeric($id) ? (int)$id : null;
			if (!$legislator_id) {
				return null;
			}
			return self::get_by_id($legislator_id);
		}

		public static function get_by_session(int $session_id, array $filters = []): array {
			return self::get(array_merge($filters, ['session_id' => $session_id]));
		}

		/**
		* Get a single field value by legislator ID
		*/
		public static function get_field_by_id(int $legislator_id, string $field): ?string {
			// Use the single get() query builder for consistency. Returns a full object; we pluck the field.
			// Note: This is less efficient than a single-field query but keeps behavior consistent and benefits from caching.
			if (empty($field)) {
				return null;
			}

			$leg = self::get_by_id($legislator_id);
			if (!$leg) {
				return null;
			}

			return isset($leg->{$field}) ? (string) $leg->{$field} : null;
		}

		/**
		* Get legislator by external ID(s)
		* Uses get() method with external ID fields
		*/
		public static function get_by_external_id(array $references): ?object {
			if (empty($references) || !is_array($references)) {
				return null;
			}
			
			// Try each external ID type in priority order
			$priority_order = ['bioguide_id', 'votesmart_id', 'ballotpedia_id', 'openstates_id', 'legiscan_id','lis_id'];
			
			foreach ($priority_order as $ref_type) {
				if (!empty($references[$ref_type])) {
					// Route through get() (single query builder)
					$args = [$ref_type => sanitize_text_field((string) $references[$ref_type]), 'limit' => 1];
					$results = self::get($args);
					if (!empty($results)) {
						return $results[0] ?? null;
					}
				}
			}
			
			return null;
		}

		/**
		* Save/Update legislator with duplicate checking
		* 
		* @param array $data Legislator data
		* @param int|null $legislator_id Update existing if provided
		* @return int|false Legislator ID on success, false on failure
		*/
		public static function save(array $data, ?int $legislator_id = null): int|false {
			global $wpdb;
			
			$action = $legislator_id ? 'UPDATE' : 'INSERT';
			self::log('Legislators::save: ' . $action . ' attempt | Legislator ID: ' . ($legislator_id ?? 'new') . ' | Input data: ' . json_encode($data), __FILE__, __LINE__, 'debug');
			
			// INSERT-specific validation and processing
			// UPDATE operations can accept any array of data - only update what's provided
			if (!$legislator_id) {
				// Validate required fields for new records
				if (empty($data['first_name']) || empty($data['last_name'])) {
					self::log('Legislators::save: Validation failed | Missing required fields (first_name or last_name)', __FILE__, __LINE__, 'warning');
					return false;
				}
				
				// Slug will be set to ID after insert - no generation needed
				
				// Check for duplicates only on INSERT (not UPDATE)
				$duplicate_check = self::check_duplicates($data, null);
				if ($duplicate_check['is_duplicate']) {
					self::log('Legislators::save: Duplicate found | Existing ID: ' . $duplicate_check['existing_id'] . ' | Returning existing ID instead', __FILE__, __LINE__, 'debug');
					return $duplicate_check['existing_id'];
				}
			}

			// Ensure meta is an ARRAY in PHP. This prevents accidental double-encoding.
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

			// Extract meta field for separate handling (merge instead of overwrite), but only for UPDATE
			$meta_data = null;
			if ($legislator_id && isset($data['meta'])) {
				$meta_data = is_array($data['meta']) ? $data['meta'] : [];
				unset($data['meta']); // Remove from $data so it doesn't get overwritten
			}
			
			// Prepare data for database
			$db_data = [
				'legacy_id' => (isset($data['legacy_id']) ? (string) $data['legacy_id'] : null),
				'first_name' => (isset($data['first_name']) ? $data['first_name'] : null),
				'middle_name' => (isset($data['middle_name']) ? $data['middle_name'] : null),
				'last_name' => (isset($data['last_name']) ? $data['last_name'] : null),
				'display_name' => (isset($data['display_name']) ? $data['display_name'] : (isset($data['first_name']) && isset($data['last_name']) ? trim($data['first_name'] . ' ' . ($data['middle_name'] ?? '') . ' ' . $data['last_name']) : null)),
				'bioguide_id' => (isset($data['bioguide_id']) ? $data['bioguide_id'] : null),
				'lis_id' => (isset($data['lis_id']) ? $data['lis_id'] : null),
				'legiscan_id' => (isset($data['legiscan_id']) ? $data['legiscan_id'] : null),
				'govtrack_id' => (isset($data['govtrack_id']) ? $data['govtrack_id'] : null),
				'votesmart_id' => (isset($data['votesmart_id']) ? $data['votesmart_id'] : null),
				'ballotpedia_id' => (isset($data['ballotpedia_id']) ? $data['ballotpedia_id'] : null),
				'openstates_id' => (isset($data['openstates_id']) ? $data['openstates_id'] : null),
				'legacy_image_url' => (array_key_exists('legacy_image_url', $data) ? (string) ($data['legacy_image_url'] ?? '') : null),
				'image_id' => (isset($data['image_id']) ? $data['image_id'] : null),
				'image_url' => (isset($data['image_url']) ? $data['image_url'] : null),
				'score' => (array_key_exists('score', $data) ? $data['score'] : null),
				'score_data' => (array_key_exists('score_data', $data) ? json_encode($data['score_data']) : null),
				'score_date' => (array_key_exists('score_date', $data) ? $data['score_date'] : null),
				'audit_log' => (isset($data['audit_log']) ? json_encode($data['audit_log']) : null),
				'meta' => (isset($data['meta']) ? json_encode($data['meta']) : null)
			];
			
			// Remove null values to use database defaults, except when a field was explicitly provided.
			$explicit_nulls = [];
			if (array_key_exists('legacy_image_url', $data) && ($data['legacy_image_url'] === null || $data['legacy_image_url'] === '')) {
				$db_data['legacy_image_url'] = null;
				$explicit_nulls['legacy_image_url'] = true;
			}
			// Summary: allow explicit clearing of image_id (set NULL) from admin UI/API.
			if (array_key_exists('image_id', $data) && ($data['image_id'] === null || (int) $data['image_id'] <= 0)) {
				$db_data['image_id'] = null;
				$explicit_nulls['image_id'] = true;
			}
			$db_data = array_filter($db_data, function ($value, $key) use ($explicit_nulls) {
				return ($value !== null) || !empty($explicit_nulls[$key]);
			}, ARRAY_FILTER_USE_BOTH);
			
			// Build format array dynamically based on actual data types
			// Field type mapping: string=%s, integer=%d, JSON=%s
			// Note: openstates_id is not in schema (index exists but field doesn't)
			$format_map = [
				'legacy_id' => '%s',
				'first_name' => '%s',
				'middle_name' => '%s',
				'last_name' => '%s',
				'display_name' => '%s',
				'bioguide_id' => '%s',
				'lis_id' => '%s',
				'legiscan_id' => '%d',
				'govtrack_id' => '%s',
				'votesmart_id' => '%s',
				'ballotpedia_id' => '%s',
				'openstates_id' => '%s',
				'legacy_image_url' => '%s',
				'image_id' => '%d',
				'image_url' => '%s',
				'score' => '%d',
				'score_data' => '%s',
				'score_date' => '%s',
				'audit_log' => '%s',
				'meta' => '%s'
			];
			
			$formats = [];
			foreach ($db_data as $key => $value) {
				$formats[] = $format_map[$key] ?? '%s';
			}
			
			if ($legislator_id) {
				// Meta-only updates (common for API "Add"/"Update") should still persist.
				// $wpdb->update() cannot run with an empty $db_data array, so handle meta merge directly.
				if (empty($db_data) && $meta_data !== null) {
					$meta_saved = self::save_json_meta($legislator_id, $meta_data);
					
					if ($meta_saved && function_exists('fi_cache_clear')) {
						fi_cache_clear('legislators');
					}
					
					return $meta_saved ? $legislator_id : false;
				}

				// Summary: do not execute an empty UPDATE (it generates invalid SQL: "UPDATE ... SET  WHERE ...").
				// This can happen when callers pass only unsupported keys (e.g., 'score' before it was mapped)
				// or when all provided values are null/empty and get filtered out.
				if (empty($db_data) && $meta_data === null) {
					return $legislator_id;
				}

				// Update existing
				self::log('Legislators::save: UPDATE | Legislator ID: ' . $legislator_id . ' | DB data: ' . json_encode($db_data) . ' | Formats: ' . json_encode($formats), __FILE__, __LINE__, 'debug');
				
				$result = $wpdb->update(
					$wpdb->prefix . 'fi_legislators',
					$db_data,
					['id' => $legislator_id],
					$formats,
					['%d']
				);
				
				if ($result !== false) {
					// Handle meta field separately using merge (preserves existing meta data)
					if ($meta_data !== null) {
						self::save_json_meta($legislator_id, $meta_data);
					}

					// Clear cached legislator lists/searches so admin UI reflects updates immediately.
					if (function_exists('fi_cache_clear')) {
						fi_cache_clear('legislators');
					}
					
					self::log('Legislators::save: UPDATE SUCCESS | Legislator ID: ' . $legislator_id . ' | Rows affected: ' . $result, __FILE__, __LINE__, 'debug');
					return $legislator_id;
				}
				
				self::log('Legislators::save: UPDATE FAILED | Legislator ID: ' . $legislator_id . ' | Error: ' . $wpdb->last_error, __FILE__, __LINE__, 'error');
				return false;
			} else {
				// Insert new
				self::log('Legislators::save: INSERT | DB data: ' . json_encode($db_data) . ' | Formats: ' . json_encode($formats), __FILE__, __LINE__, 'debug');
				
				$result = $wpdb->insert(
					$wpdb->prefix . 'fi_legislators',
					$db_data,
					$formats
				);
				
				if ($result !== false) {
					$new_legislator_id = $wpdb->insert_id;

					// Clear cached legislator lists/searches so new record appears immediately.
					if (function_exists('fi_cache_clear')) {
						fi_cache_clear('legislators');
					}
					
					self::log('Legislators::save: INSERT SUCCESS | New Legislator ID: ' . $new_legislator_id, __FILE__, __LINE__, 'debug');
					return $new_legislator_id;
				}
				
				self::log('Legislators::save: INSERT FAILED | Error: ' . $wpdb->last_error, __FILE__, __LINE__, 'error');
				return false;
			}
		}

		/**
		* Update legislator
		* 
		* @param int $legislator_id
		* @param array $data
		* @return bool
		*/
		public static function update(int $legislator_id, array $data): bool {
			return self::save($data, $legislator_id) !== false;
		}

		/**
		* Delete legislator
		* 
		* @param int $legislator_id
		* @return bool
		*/
		public static function delete(int $legislator_id): bool {
			global $wpdb;
			
			// Check if legislator exists
			$legislator = self::get_by_id($legislator_id);
			if (!$legislator) {
				return false;
			}
			
			// Delete related records first
			$wpdb->delete($wpdb->prefix . 'fi_legislator_sessions', ['legislator_id' => $legislator_id]);
			$wpdb->delete($wpdb->prefix . 'fi_voterc', ['legislator_id' => $legislator_id]);
			
			// Delete legislator
			$result = $wpdb->delete($wpdb->prefix . 'fi_legislators', ['id' => $legislator_id]);

			if ($result !== false) {
				// Clear cached legislator lists/searches so deleted record disappears immediately.
				if (function_exists('fi_cache_clear')) {
					fi_cache_clear('legislators');
				}
				return true;
			}

			return false;
		}

		/**
		* Check for duplicate legislators
		* 
		* @param array $data
		* @param int|null $exclude_id Exclude this ID from duplicate check
		* @return array ['is_duplicate' => bool, 'existing_id' => int|null]
		*/
		public static function check_duplicates(array $data, ?int $exclude_id = null): array {
			global $wpdb;
			
			$conditions = [];
			$values = [];
			
			// Check by legacy_id (migration safety)
			if (!empty($data['legacy_id'])) {
				$conditions[] = "legacy_id = %s";
				$values[] = (string) $data['legacy_id'];
			}
			
			// Check by external IDs only (no name matching - too many false positives with common names)
			$external_id_fields = ['bioguide_id', 'lis_id', 'legiscan_id', 'votesmart_id', 'ballotpedia_id', 'openstates_id'];
			foreach ($external_id_fields as $field) {
				if (!empty($data[$field])) {
					$conditions[] = "{$field} = %s";
					$values[] = $data[$field];
				}
			}
			
			if (empty($conditions)) {
				return ['is_duplicate' => false, 'existing_id' => null];
			}
			
			$where_clause = implode(' OR ', $conditions);
			$sql = "SELECT id FROM {$wpdb->prefix}fi_legislators WHERE {$where_clause}";
			
			if ($exclude_id) {
				$sql .= " AND id != %d";
				$values[] = $exclude_id;
			}
			
			$sql .= " LIMIT 1";
			
			$existing_id = $wpdb->get_var($wpdb->prepare($sql, $values));
			
			return [
				'is_duplicate' => !empty($existing_id),
				'existing_id' => $existing_id ? (int) $existing_id : null
			];
		}



		/**
		* Get legislator statistics
		* 
		* @param string|null $gov
		* @return array
		*/
		public static function get_stats(?string $gov = null): array {
			global $wpdb;
			
			// Note: gov field removed from schema, so we can't filter by it directly
			// If gov filtering is needed, we'd need to join with sessions
			$where_clause = "";
			$values = [];
			
			$sql = "
				SELECT 
					COUNT(*) as total,
					COUNT(CASE WHEN image_id IS NOT NULL THEN 1 END) as with_images,
					COUNT(CASE WHEN bioguide_id IS NOT NULL THEN 1 END) as with_bioguide,
					COUNT(CASE WHEN legiscan_id IS NOT NULL THEN 1 END) as with_legiscan
				FROM {$wpdb->prefix}fi_legislators
				{$where_clause}
			";
			
			if (!empty($values)) {
				$sql = $wpdb->prepare($sql, $values);
			}
			
			return (array) $wpdb->get_row($sql);
		}

		/**
		* Search legislators by name
		* 
		* @param string $query Search query
		* @param int $limit Maximum results (default 20)
		* @return array Array of legislator objects with id, display_name, slug, and session info
		*/
		public static function search(string $query, int $limit = 20): array {
			global $wpdb;
			
			$results = $wpdb->get_results($wpdb->prepare(
				"SELECT DISTINCT l.id, l.display_name, ls.chamber as chamber, ls.district, ls.party
				FROM {$wpdb->prefix}fi_legislators l
				INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
				WHERE (l.first_name LIKE %s OR l.last_name LIKE %s OR l.display_name LIKE %s)
				LIMIT %d",
				'%' . $wpdb->esc_like($query) . '%',
				'%' . $wpdb->esc_like($query) . '%',
				'%' . $wpdb->esc_like($query) . '%',
				$limit
			));
			
			return $results ?: [];
		}

		/**
		* Validate legislator data
		* 
		* @param array $data
		* @return array ['valid' => bool, 'errors' => array]
		*/
		public static function validate_data(array $data): array {
			$errors = [];
			
			// Required fields
			if (empty($data['first_name'])) {
				$errors[] = 'First name is required';
			}
			
			if (empty($data['last_name'])) {
				$errors[] = 'Last name is required';
			}
			
			// Validate external IDs
			$external_id_fields = [
				'bioguide_id' => 16,
				'lis_id' => 20,
				'legiscan_id' => 20,
				'votesmart_id' => 20,
				'ballotpedia_id' => 100,
				'openstates_id' => 100
			];
			
			foreach ($external_id_fields as $field => $max_length) {
				if (!empty($data[$field]) && strlen($data[$field]) > $max_length) {
					$errors[] = ucfirst($field) . " must be {$max_length} characters or less";
				}
			}
			
			return [
				'valid' => empty($errors),
				'errors' => $errors
			];
		}

		/**
		* Get legislator sessions
		* 
		* @param int $legislator_id
		* @return array
		*/
		public static function get_sessions(int $legislator_id): array {
			global $wpdb;
			
			//SESSIONSLUG: Remove 's.slug as session_slug' from SELECT - no longer needed in data objects
			$sql = "
				SELECT ls.*, s.name as session_name
				FROM {$wpdb->prefix}fi_legislator_sessions ls
				LEFT JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
				WHERE ls.legislator_id = %d
				ORDER BY s.date_start DESC
			";
			
			return $wpdb->get_results($wpdb->prepare($sql, $legislator_id));
		}

		/**
		* Update freedom score for a legislator
		*/
		public static function update_freedom_score(int $legislator_id): array {
			global $wpdb;
			
			// Calculate freedom score from all session scores
			// Extract vote counts from score_data JSON (support both old and new format)
			$sql = "
				SELECT 
					SUM(CAST(COALESCE(JSON_EXTRACT(score_data, '$.total'), JSON_EXTRACT(score_data, '$.votes_total'), 0) AS UNSIGNED)) as freedom_total,
					SUM(CAST(COALESCE(JSON_EXTRACT(score_data, '$.good'), JSON_EXTRACT(score_data, '$.votes_good'), 0) AS UNSIGNED)) as freedom_good,
					SUM(CAST(COALESCE(JSON_EXTRACT(score_data, '$.bad'), JSON_EXTRACT(score_data, '$.votes_bad'), 0) AS UNSIGNED)) as freedom_bad,
					SUM(CAST(COALESCE(JSON_EXTRACT(score_data, '$.not'), JSON_EXTRACT(score_data, '$.votes_not'), 0) AS UNSIGNED)) as freedom_not_votes,
					SUM(CAST(COALESCE(JSON_EXTRACT(score_data, '$.scored'), JSON_EXTRACT(score_data, '$.votes_scored'), 0) AS UNSIGNED)) as freedom_scored
				FROM {$wpdb->prefix}fi_legislator_sessions 
				WHERE legislator_id = %d AND score_data IS NOT NULL
			";
			
			$result = $wpdb->get_row($wpdb->prepare($sql, $legislator_id));
			
			if (!$result || !$result->freedom_total) {
				return [];
			}
			
			// Round freedom score to whole number (no decimals)
			$freedom_score = ($result->freedom_scored > 0) 
				? round(($result->freedom_good / $result->freedom_scored) * 100, 0) 
				: 0;
			
			// Build score_data JSON object (without votes_ prefix)
			$score_data_json = [
				'score' => $freedom_score,
				'total' => (int) $result->freedom_total,
				'good' => (int) $result->freedom_good,
				'bad' => (int) $result->freedom_bad,
				'not' => (int) $result->freedom_not_votes,
				'scored' => (int) $result->freedom_scored
			];
			
			// Update legislator with freedom score data
			$result = $wpdb->update(
				$wpdb->prefix . 'fi_legislators',
				[
					'score' => $freedom_score,
					'score_data' => json_encode($score_data_json),
					'score_date' => current_time('mysql')
				],
				['id' => $legislator_id],
				['%d', '%s', '%s'],
				['%d']
			);
			
			return $score_data_json;
		}
		
		/**
		* Get legislators ranked by freedom score
		*/
		public static function get_by_freedom_score(array $args = []): array {
			global $wpdb;
			
			$defaults = [
				'gov' => null,
				'limit' => 50,
				'order' => 'DESC'
			];
			
			$args = wp_parse_args($args, $defaults);
			
			$where_conditions = [];
			$where_values = [];
			
			// If gov is provided, filter by sessions
			if (!empty($args['gov'])) {
				$where_conditions[] = "EXISTS (
					SELECT 1 FROM {$wpdb->prefix}fi_legislator_sessions ls2
					JOIN {$wpdb->prefix}fi_sessions s2 ON ls2.session_id = s2.id
					WHERE ls2.legislator_id = l.id AND s2.gov = %s
				)";
				$where_values[] = strtoupper($args['gov']);
			}
			
			$where_clause = !empty($where_conditions) ? 'AND ' . implode(' AND ', $where_conditions) : '';
			$order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
			$limit = intval($args['limit']);
			
			$sql = "
				SELECT 
					l.*,
					SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.total'), JSON_EXTRACT(ls.score_data, '$.votes_total'), 0) AS UNSIGNED)) as freedom_total,
					SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.good'), JSON_EXTRACT(ls.score_data, '$.votes_good'), 0) AS UNSIGNED)) as freedom_good,
					SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.bad'), JSON_EXTRACT(ls.score_data, '$.votes_bad'), 0) AS UNSIGNED)) as freedom_bad,
					SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.not'), JSON_EXTRACT(ls.score_data, '$.votes_not'), 0) AS UNSIGNED)) as freedom_not_votes,
					SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.scored'), JSON_EXTRACT(ls.score_data, '$.votes_scored'), 0) AS UNSIGNED)) as freedom_scored,
					ROUND((SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.good'), JSON_EXTRACT(ls.score_data, '$.votes_good'), 0) AS UNSIGNED)) / SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.scored'), JSON_EXTRACT(ls.score_data, '$.votes_scored'), 0) AS UNSIGNED))) * 100, 0) as calculated_freedom_score
				FROM {$wpdb->prefix}fi_legislators l
				INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
				WHERE ls.score_data IS NOT NULL {$where_clause}
				GROUP BY l.id
				HAVING freedom_scored > 0
				ORDER BY calculated_freedom_score {$order}
				LIMIT %d
			";
			
			$values = array_merge($where_values, [$limit]);
			return $wpdb->get_results($wpdb->prepare($sql, $values));
		}

		/**
		* Handle JSON meta data - add/update specific key=>value pairs.
		* If $legislator_id get meta data then merge new data with existing and update.
		* 
		* Update legislator meta JSON, merging provided data into existing, using MariaDB/MySQL JSON_MERGE_PATCH for efficiency if possible.
		*
		* This approach reduces PHP-side read/merge/write roundtrip, especially with modern MySQL/MariaDB.
		*/
	/**
	 * Save/update legislator meta (surgical update via trait)
	 * Wrapper for backward compatibility
	 */
	public static function save_json_meta(int $legislator_id, array $meta_data): bool {
		return self::update_meta($legislator_id, 'fi_legislators', $meta_data);
	}

		// ============================================================================
		// Meta Data Operations (delegated to LegislatorsMeta class)
		// ============================================================================

		/**
		* Normalize meta array from flat structure to organized groups.
		* Delegates to LegislatorsMeta for all meta operations.
		* 
		* @param array $meta Raw meta array from database
		* @return array Normalized meta array with organized groups
		*/
		public static function normalize_meta(array $meta): array {
			return LegislatorsMeta::normalize($meta);
		}

		/**
		* Wrap fi_log in function so we can log this class only if necessary.
		*/
		public static function log(string $message, string $file = '', int $line = 0, string $level = 'debug'): void {
			//fi_log($message, $file, $line, $level);
		}
	}
}

// Public helper functions - enforce constraints and provide simple API
namespace {
    /**
     * Get legislators by session ID
     * Primary fields reflect the requested session's data
     * Only returns legislators who served in that session
     */
    function fi_legislators_get_by_session(int $session_id, array $filters = []): array {
        return \FI\Core\Legislators::get_by_session($session_id, $filters);
    }

    /**
     * Get legislators ranked by freedom score
     */
    function fi_legislators_get_by_freedom_score(array $args = []): array {
        return \FI\Core\Legislators::get_by_freedom_score($args);
    }

    /**
     * Get legislators by array of IDs with session data
     * Uses new get() method - always includes session data
     */
    function fi_legislators_get_by_ids(array $ids, bool $include_session_data = true): array {
        if (empty($ids)) {
            return [];
        }
        
        // Filter to valid integers
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            return [];
        }
        
        // Use get() method - it always includes session data now
        $results = [];
        foreach ($ids as $id) {
            $legislator = \FI\Core\Legislators::get_by_id($id);
            if ($legislator) {
                $results[] = $legislator;
            }
        }
        
        return $results;
    }

    /**
     * Get legislators with filters
     * Must provide at least one: session_id, gov, search, id, or slug
     * Returns latest session data in primary fields (unless session_id provided)
     */
    function fi_legislators(array $args = []): array {
        // Normalize parameter names for compatibility
        if (isset($args['session']) && !isset($args['session_id'])) {
            $args['session_id'] = $args['session'];
        }
        
        // Enforce constraint: must have at least one filter
        if (empty($args['session_id']) && empty($args['gov']) && empty($args['search']) && empty($args['id'])) {
            return [];
        }
        
        return \FI\Core\Legislators::get($args);
    }

   /**
     * Get a single legislator by ID
     * Returns latest session data in primary fields, full history in sessions array
     */
    function fi_legislator_get(int $legislator_id): ?object {
        return \FI\Core\Legislators::get_by_id($legislator_id);
    }

    /**
     * Get legislator with all sessions for header display
     * Efficient single query for page load
     */
    function fi_legislator_get_with_sessions(int $legislator_id): ?object {
        return \FI\Core\Legislators::get_with_sessions($legislator_id);
    }

    /**
     * Get a single field value by legislator ID
     */
    function fi_legislator_get_field(int $legislator_id, string $field): ?string {
        return \FI\Core\Legislators::get_field_by_id($legislator_id, $field);
    }

    /**
     * Get a single legislator by slug
     * Returns latest session data in primary fields, full history in sessions array
     */
    function fi_legislator_get_by_slug(string $slug): ?object {
        return \FI\Core\Legislators::get_by_slug($slug);
    }

    /**
     * Get legislator by external ID(s)
     * Accepts array with keys: bioguide_id, lis_id, legiscan_id, votesmart_id, ballotpedia_id, openstates_id
     */
    function fi_legislator_get_by_external_id(array $references): ?object {
        return \FI\Core\Legislators::get_by_external_id($references);
    }

    /**
     * Get single legislator by slug with session data (alias for get_by_slug)
     * Returns latest session data in primary fields, full history in sessions array
     */
    function fi_legislator_get_by_slug_with_session(string $slug): ?object {
        return \FI\Core\Legislators::get_by_slug($slug);
    }

    /**
     * Get freedom score for a legislator
     */
    function fi_legislator_get_freedom_score(int $legislator_id): ?array {
        $legislator = \FI\Core\Legislators::get_by_id($legislator_id);
        if ($legislator && isset($legislator->freedom_score)) {
            return ['score' => $legislator->freedom_score];
        }
        return null;
    }

    /**
     * Save/Update legislator
     */
    function fi_legislator_save(array $data, ?int $legislator_id = null): int|false {
        return \FI\Core\Legislators::save($data, $legislator_id);
    }

    /**
     * Update legislator
     */
    function fi_legislator_update(int $legislator_id, array $data): bool {
        return \FI\Core\Legislators::update($legislator_id, $data);
    }

    /**
     * Delete legislator
     */
    function fi_legislator_delete(int $legislator_id): bool {
        return \FI\Core\Legislators::delete($legislator_id);
    }

    /**
     * Update freedom score for a legislator
     */
    function fi_legislator_update_freedom_score(int $legislator_id): array {
        return \FI\Core\Legislators::update_freedom_score($legislator_id);
    }

    /**
     * Get score CSS class
     */
    function fi_score_class($score): string {
        if ($score >= 90) return 'fi-score-excellent';
        if ($score >= 70) return 'fi-score-good';
        if ($score >= 50) return 'fi-score-fair';
        return 'fi-score-poor';
    }

	/* Single image function handles all image conditions: Returns HTML image tag */
	function fi_legislator_image($image_id=null,$session_image_id=null, array $args = []): string {
		// If session_image_id is provided, use it, otherwise use image_id or placeholder
		if ($session_image_id) {
			$attachment_id = $session_image_id;
		}elseif ($image_id) {
			$attachment_id = $image_id;
		}else {
			$placeholder_url = FI_URL . 'assets/img/placeholder.png';
			$alt = $args['alt'] ?? '';
			return '<img src="' . esc_url($placeholder_url) . '" alt="' . esc_attr($alt) . '" class="fi-legislator-image fi-placeholder img-fluid">';
		}

		$defaults = [
			'size' => [200, 250],
			'crop' => false,
			'retina' => true,
			'alt' => '',
			'class' => 'img-fluid',
			'id' => 'image-' . $attachment_id
		];
        $args = wp_parse_args($args, $defaults);
		//fi_log('ATTACHMENT: '.$attachement_id . ' | '.json_encode($args),__FILE__,__LINE__);

        $html = jis_get_attachment_image(
            $attachment_id,
            $args['size'],
            $args['crop'],
            [
                'retina' => $args['retina'],
                'alt' => $args['alt'],
                'class' => $args['class'],
                'id' => $args['id']
            ],
        );
		//fi_log('IMAGE HTML: '.$html,__FILE__,__LINE__);
       return $html;
	}

    /**
     * Search legislators by name
     */
    function fi_legislators_search(string $query, int $limit = 20): array {
        return \FI\Core\Legislators::search($query, $limit);
    }

    /**
     * Get addresses array from legislator meta (global helper)
     */
    function fi_legislator_addresses(object $legislator): array {
        return \FI\Core\LegislatorsMeta::get_addresses($legislator);
    }

    /**
     * Get primary address from legislator meta (global helper)
     */
    function fi_legislator_primary_address(object $legislator): ?array {
        return \FI\Core\LegislatorsMeta::get_primary_address($legislator);
    }

    /**
     * Get capitol address from legislator meta (global helper)
     */
    function fi_legislator_capitol_address(object $legislator): ?array {
        return \FI\Core\LegislatorsMeta::get_capitol_address($legislator);
    }

    /**
     * Get websites array from legislator meta (global helper)
     */
    function fi_legislator_websites(object $legislator): array {
        return \FI\Core\LegislatorsMeta::get_websites($legislator);
    }

    /**
     * Get social media links from legislator meta (global helper)
     */
    function fi_legislator_social(object $legislator): array {
        return \FI\Core\LegislatorsMeta::get_social($legislator);
    }

    /**
     * Get primary contact info from legislator meta (global helper)
     */
    function fi_legislator_contact(object $legislator): array {
        return \FI\Core\LegislatorsMeta::get_contact($legislator);
    }

    /**
     * Format address for display with HTML sanitization (global helper)
     */
    function fi_legislator_format_address(array $address): string {
        return wp_kses_post(\FI\Core\LegislatorsMeta::format_address($address));
    }

    /**
     * Format full address (global helper)
     */
    function fi_legislator_format_full_address(array $address): string {
        return \FI\Core\LegislatorsMeta::format_full_address($address);
    }

	/* Get legislator meta value by key */
	function fi_legislator_get_meta($record, string $key, $default = null) {
		return \FI\Core\Legislators::get_meta($record, $key, $default);
	}

	/* Get all legislator meta */
	function fi_legislator_get_all_meta($record): array {
		return \FI\Core\Legislators::get_all_meta($record);
	}

	/* Update legislator meta key(s) without affecting other keys */
	function fi_legislator_update_meta(int $record_id, array $meta_updates): bool {
		return \FI\Core\Legislators::update_meta($record_id, 'fi_legislators', $meta_updates);
	}

	/* Delete legislator meta key(s) */
	function fi_legislator_delete_meta(int $record_id, $keys): bool {
		return \FI\Core\Legislators::delete_meta($record_id, 'fi_legislators', $keys);
	}

	/* Set entire legislator meta (replaces all) */
	function fi_legislator_set_all_meta(int $record_id, array $meta): bool {
		return \FI\Core\Legislators::set_all_meta($record_id, 'fi_legislators', $meta);
	}

	function fi_legislators_sort_options(): array {
		return [
			'na' => 'Name (A-Z)',
			'nd' => 'Name (Z-A)',
			'sa' => 'Score (Low-High)',
			'sd' => 'Score (High-Low)',
			'pa' => 'Party (A-Z)',
			'pd' => 'Party (Z-A)',
			'ca' => 'Chamber (A-Z)',
			'cd' => 'Chamber (Z-A)',
		];
	}

	function fi_legislators_get_bioguide_xref($session_id){
		global $wpdb;
		$xref = [];
		$query = $wpdb->prepare(
			"SELECT l.bioguide_id, ls.legislator_id
			FROM {$wpdb->prefix}fi_legislator_sessions ls
			LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
			WHERE ls.session_id = %d",
			$session_id
		);
		$results = $wpdb->get_results($query);
		foreach($results as $row){
			$xref[$row->bioguide_id] = $row->legislator_id;
		}
		return $xref;
	}

	function fi_legislators_get_lis_xref($session_id){
		global $wpdb;
		$xref = [];
		$query = $wpdb->prepare(
			"SELECT l.lis_id, ls.legislator_id
			FROM {$wpdb->prefix}fi_legislator_sessions ls
			LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
			WHERE ls.session_id = %d",
			$session_id
		);
		$results = $wpdb->get_results($query);
		foreach($results as $row){
			$xref[$row->lis_id] = $row->legislator_id;
		}
		return $xref;
	}

}