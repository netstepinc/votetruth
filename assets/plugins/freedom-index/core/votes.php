<?php
namespace FI\Core{
	if (!defined('ABSPATH')) exit;
	
	// Load Meta trait
	require_once __DIR__ . '/traits/meta.php';
	
	/**
	* Votes Table I/O Operations
	* All database operations for the fi_votes table.
	*/
	final class Votes {
		
		// Use unified meta handling trait
		use \FI\Core\Traits\Meta;

		/**
		 * Request-level cache for get() results
		 * Key: md5 hash of serialized args
		 */
		private static $cache_get = [];

		/**
		* Get votes with optional filtering
		* 
		* @param array $args {
		*     Optional. Arguments to filter votes.
		* 
		*     @type array<int>    $ids           Filter by vote IDs
		*     @type int          $session_id    Filter by session ID
		*     @type array<int>   $session_ids   Filter by explicit session IDs (no hierarchy expansion)
		*     @type string       $gov           Filter by government code
		*     @type string       $chamber     Filter by chamber (H/S)
		*     @type string       $constitutional Filter by good position (Y/N)
		*     @type string       $status        Filter by status (publish, draft, etc.)
		*     @type bool         $has_rollcall  Require vote rollcall_data to be non-null
		*     @type int          $tag_id        Filter by tag ID (adds join)
		*     @type int          $legislator_id Filter by legislator ID (adds join to voterc + adds cast fields)
		*     @type string       $search        Search in titles
		*     @type string       $orderby       Order by field (date, title, etc.)
		*     @type string       $order         Order direction (ASC/DESC)
		*     @type int          $per_page      Number per page
		*     @type int          $page          Page number
		*     @type int|null     $limit         Limit results (overrides per_page/page)
		*     @type int          $offset        Offset results (used with limit)
		*     @type bool         $count         Return count only
		* }
		* @return array|int Array of vote objects or count if $count is true
		*/
		public static function get(array $args = []): array|int {
			global $wpdb;

			// Check if keys were explicitly provided BEFORE merging with defaults
			$status_key_provided = array_key_exists('status', $args);
			$session_status_key_provided = array_key_exists('session_status', $args);
			
			$defaults = [
				'gov' => null,
				'chamber' => null,
				'ids' => null,
				'legislator_id' => null,
				'session_id' => null,
				'session_ids' => null,
				'constitutional' => null,
				'status' => null,
				'session_status' => null,
				'has_rollcall' => false,
				'tag_id' => null,
				'search' => null, //title, bill_number, rollcall_number, meta
				'orderby' => 'date_voted',
				'order' => 'DESC',
				'per_page' => -1,
				'page' => 1,
				'limit' => null,
				'offset' => 0,
				'count' => false,
			];
			
			$args = wp_parse_args($args, $defaults);

			// Apply default filters BEFORE caching
			if (!$status_key_provided) {
				$args['status'] = 'publish';
			}
			
			// Default session_status to 'publish' for public queries only
			// Admin queries should see all sessions unless explicitly filtered
			if (!$session_status_key_provided) {
				// Check if this is a public AJAX request (from frontend)
				// Following the same pattern as fi_cache() - DOING_AJAX means public query
				$is_public_query = defined('DOING_AJAX') && DOING_AJAX;
				
				// DEBUG
				self::log("Votes::get() - DOING_AJAX: " . (defined('DOING_AJAX') && DOING_AJAX ? 'YES' : 'NO'));
				self::log("Votes::get() - is_public_query: " . ($is_public_query ? 'YES' : 'NO'));
				
				// Only filter to published sessions on public queries (AJAX from frontend)
				if ($is_public_query) {
					$args['session_status'] = 'publish';
					self::log("Votes::get() - Setting session_status to 'publish' for public query");
				} else {
					self::log("Votes::get() - Admin context, NOT filtering session_status");
				}
			} else {
				self::log("Votes::get() - session_status explicitly provided: " . ($args['session_status'] ?? 'NULL'));
			}

			// Check request-level cache first (fastest)
			$cache_key = md5(serialize($args));
			if (isset(self::$cache_get[$cache_key])) {
				return self::$cache_get[$cache_key];
			}

			// Cache query results (file system cache)
			$cacheKey = fi_cache_key('votes/get', $args);
			$results = fi_cache($cacheKey); //1 day
			if($results){
				// Store in request-level cache
				self::$cache_get[$cache_key] = $results;
				return $results;
			}

			// -----------------------------------------------------------------
			// Single query builder for votes (supports tag + voterc join variants)
			// -----------------------------------------------------------------

			$tag_id = absint($args['tag_id'] ?? 0);
			$legislator_id = absint($args['legislator_id'] ?? 0);
			$distinct = ($tag_id > 0);

			$select_parts = ['v.*', 's.name as session_name', 's.status as session_status'];
			$join_parts = [
				"LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id",
			];

			if ($tag_id > 0) {
				$join_parts[] = "INNER JOIN {$wpdb->prefix}fi_vote_tags vt ON v.id = vt.vote_id";
			}

			if ($legislator_id > 0) {
				$join_parts[] = "INNER JOIN {$wpdb->prefix}fi_voterc vr ON v.id = vr.vote_id";
				$select_parts[] = 'vr.cast';
				$select_parts[] = 'vr.is_override';
			}

			// Build WHERE clause
			$where_conditions = [];
			$where_values = [];

			if (!empty($args['ids']) && is_array($args['ids'])) {
				$ids = array_values(array_filter(array_map('absint', $args['ids'])));
				if (!empty($ids)) {
					$placeholders = implode(',', array_fill(0, count($ids), '%d'));
					$where_conditions[] = "v.id IN ($placeholders)";
					$where_values = array_merge($where_values, $ids);
				} else {
					$where_conditions[] = "1 = 0";
				}
			}

			if (!empty($args['session_ids']) && is_array($args['session_ids'])) {
				$session_ids = array_values(array_filter(array_map('absint', $args['session_ids'])));
				if (!empty($session_ids)) {
					$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
					$where_conditions[] = "v.session_id IN ($placeholders)";
					$where_values = array_merge($where_values, $session_ids);
				} else {
					$where_conditions[] = "1 = 0";
				}
			} elseif ($args['session_id']) {
				// Get all session IDs in the hierarchy (parent + children)
				$session_ids = fi_sessions_get_hierarchy_ids($args['session_id']);
				if (!empty($session_ids)) {
					$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
					$where_conditions[] = "v.session_id IN ($placeholders)";
					$where_values = array_merge($where_values, $session_ids);
				} else {
					// No sessions found in hierarchy, return empty result
					$where_conditions[] = "1 = 0";
				}
			}

			if ($tag_id > 0) {
				$where_conditions[] = 'vt.tag_id = %d';
				$where_values[] = $tag_id;
			}

			if ($legislator_id > 0) {
				$where_conditions[] = 'vr.legislator_id = %d';
				$where_values[] = $legislator_id;
			}

			if ($args['gov']) {
				$where_conditions[] = "v.gov = %s";
				$where_values[] = $args['gov'];
			}

			if ($args['chamber']) {
				$where_conditions[] = "v.chamber = %s";
				$where_values[] = $args['chamber'];
			}

			if ($args['constitutional']) {
				$where_conditions[] = "v.constitutional = %s";
				$where_values[] = $args['constitutional'];
			}

			if (!empty($args['has_rollcall'])) {
				$where_conditions[] = "v.rollcall_data IS NOT NULL";
			}

			if ($args['status']) {
				$where_conditions[] = "v.status = %s";
				$where_values[] = $args['status'];
			}

			// Filter by session status (default set above before caching)
			if ($args['session_status']) {
				self::log("Votes::get() - Adding WHERE condition: s.status = " . $args['session_status']);
				$where_conditions[] = "s.status = %s";
				$where_values[] = $args['session_status'];
			} else {
				self::log("Votes::get() - NOT filtering by session_status (value is null/empty)");
			}

			// Search: title, bill_number, rollcall_number, meta (JSON; search via CAST to string)
			if ($args['search']) {
				$where_conditions[] = "(v.title LIKE %s OR v.bill_number LIKE %s OR v.rollcall_number LIKE %s OR CAST(v.meta AS CHAR) LIKE %s)";
				$search_term = '%' . $wpdb->esc_like($args['search']) . '%';
				$where_values[] = $search_term;
				$where_values[] = $search_term;
				$where_values[] = $search_term;
				$where_values[] = $search_term;
			}

			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

			// Map common orderby fields to use table alias
			$orderby_field = $args['orderby'];
			$orderby_map = [
				'id' => 'v.id',
				'date_voted' => 'v.date_voted',
				'title' => 'v.title',
				'bill_number' => 'v.bill_number',
				'bill_key' => 'v.bill_key',
				'slug' => 'v.slug',
				'gov' => 'v.gov',
				'chamber' => 'v.chamber',
				'constitutional' => 'v.constitutional',
				'session_id' => 'v.session_id',
			];

			// Validate order direction
			$order_direction = strtoupper($args['order']);
			if (!in_array($order_direction, ['ASC', 'DESC'], true)) {
				$order_direction = 'ASC';
			}

			// Get the field name (with table alias if mapped)
			if (isset($orderby_map[$orderby_field])) {
				$field_name = $orderby_map[$orderby_field];
			} else {
				// Sanitize field name if not in map
				$field_name = preg_replace('/[^a-zA-Z0-9_\.]/', '', (string) $orderby_field);
				if (strpos($field_name, '.') === false) {
					$field_name = 'v.' . $field_name;
				}
			}

			$orderby = $field_name . ' ' . $order_direction . ', v.rollcall_number DESC';

			// LIMIT/OFFSET: support (limit, offset) for "by legislator" style paging; otherwise use (per_page, page)
			$limit_clause = '';
			$limit = isset($args['limit']) ? (int) $args['limit'] : null;
			$offset = max(0, (int) ($args['offset'] ?? 0));
			if ($limit !== null && $limit > 0) {
				$limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", $limit, $offset);
			} elseif ($args['per_page'] > 0) {
				$page_offset = ((int) $args['page'] - 1) * (int) $args['per_page'];
				$page_offset = max(0, $page_offset);
				$limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", (int) $args['per_page'], $page_offset);
			}

			$join_sql = implode("\n", $join_parts);
			$select_sql = implode(', ', $select_parts);

			if ($args['count']) {
				$count_expr = $distinct ? 'COUNT(DISTINCT v.id)' : 'COUNT(*)';
				$sql = "SELECT {$count_expr} FROM {$wpdb->prefix}fi_votes v {$join_sql} {$where_clause}";
				$prepared = !empty($where_values) ? $wpdb->prepare($sql, $where_values) : $sql;
				// Log the SQL using fi_log() (votes area)
				$log_prepared = str_replace(["\r\n", "\n", "\r","\t"], ' ', $prepared);
				self::log($log_prepared, __FILE__, __LINE__);
				return (int) $wpdb->get_var($prepared);
			}

			$distinct_sql = $distinct ? 'DISTINCT ' : '';
			$sql = "
				SELECT {$distinct_sql}{$select_sql}
				FROM {$wpdb->prefix}fi_votes v
				{$join_sql}
				{$where_clause}
				ORDER BY {$orderby}
				{$limit_clause}
			";

			$prepared = !empty($where_values) ? $wpdb->prepare($sql, $where_values) : $sql;

			// Log the SQL using fi_log() (votes area)
			$log_prepared = str_replace(["\r\n", "\n", "\r","\t"], ' ', $prepared);
			self::log("Votes::get() SQL: " . $log_prepared);
			//self::log($log_prepared, __FILE__, __LINE__);

			$results = $wpdb->get_results($prepared);
			
			self::log("Votes::get() - Query returned " . count($results) . " results");
			if ($wpdb->last_error) {
				self::log("Votes::get() - DB ERROR: " . $wpdb->last_error);
			}
			
			fi_cache($cacheKey,$results);
			// Store in request-level cache
			self::$cache_get[$cache_key] = $results;
			return $results;
			}

			/**
			* Get a single vote by ID
			* 
			* @param int $vote_id
			* @return object|null
			*/
			public static function get_by_id(int $vote_id): ?object {
				global $wpdb;
				
				//SESSIONSLUG: Remove 's.slug as session_slug' from SELECT - no longer needed in vote objects
				$sql = "
					SELECT v.*, s.name as session_name
					FROM {$wpdb->prefix}fi_votes v
					LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
					WHERE v.id = %d 
					LIMIT 1
				";
				
				return $wpdb->get_row($wpdb->prepare($sql, $vote_id));
			}

			/**
			* Get a single vote by Legiscan Rollcall ID
			* 
			* @param int $legiscan_rcid Legiscan rollcall ID
			* @param int|null $session_id Optional session ID for additional filtering
			* @return object|null
			*/
			public static function get_by_legiscan_rcid(int $legiscan_rcid, ?int $session_id = null): ?object {
				global $wpdb;
				
				$where_conditions = ['v.legiscan_rcid = %d'];
				$where_values = [$legiscan_rcid];
				
				if ($session_id) {
					$where_conditions[] = 'v.session_id = %d';
					$where_values[] = $session_id;
				}
				
				$where_clause = implode(' AND ', $where_conditions);
				
				//SESSIONSLUG: Remove 's.slug as session_slug' from SELECT - no longer needed in vote objects
				$sql = "
					SELECT v.*, s.gov, s.name as session_name
					FROM {$wpdb->prefix}fi_votes v
					INNER JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
					WHERE {$where_clause}
					LIMIT 1
				";
				
				return $wpdb->get_row($wpdb->prepare($sql, $where_values));
			}

			/**
			* Get a single vote by slug
			* 
			* @param string $slug Vote slug
			* @param string|null $gov Optional government code for validation
			* @return object|null
			*/
			public static function get_by_slug(string $slug, ?string $gov = null): ?object {
				global $wpdb;
				
				$where_conditions = ['v.slug = %s'];
				$where_values = [$slug];
				
				if ($gov) {
					$where_conditions[] = 's.gov = %s';
					$where_values[] = strtoupper($gov);
				}
				
				$where_clause = implode(' AND ', $where_conditions);
				
				//SESSIONSLUG: Remove 's.slug as session_slug' from SELECT - no longer needed in vote objects
				$sql = "
					SELECT v.*, s.gov, s.name as session_name
					FROM {$wpdb->prefix}fi_votes v
					INNER JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
					WHERE {$where_clause}
					LIMIT 1
				";
				
				return $wpdb->get_row($wpdb->prepare($sql, $where_values));
			}

			/**
			* Get votes by session with session details
			* 
			* @param int $session_id
			* @param array $filters Additional filters
			* @return array
			*/
			public static function get_by_session(int $session_id, array $filters = []): array {
				// Default behavior:
				// - If status key is missing: public -> publish, admin -> all (status => null)
				// - If status key is present: caller controls behavior (including null for "all")
				if (!array_key_exists('status', $filters)) {
					$filters['status'] = is_admin() ? null : 'publish';
				}
				$args = array_merge([
					'session_id' => $session_id,
					'orderby' => 'date_voted',
					'order' => 'DESC'
				], $filters);
				return self::get($args);
			}

			/**
			* Get votes by government
			* 
			* @param string $gov
			* @param array $filters Additional filters
			* @return array
			*/
			public static function get_by_gov(string $gov, array $filters = []): array {
				// Default behavior:
				// - If status key is missing: public -> publish, admin -> all (status => null)
				// - If status key is present: caller controls behavior (including null for "all")
				if (!array_key_exists('status', $filters)) {
					$filters['status'] = is_admin() ? null : 'publish';
				}
				$args = array_merge($filters, ['gov' => $gov]);
				return self::get($args);
			}

			/**
			* Get votes by tag ID with optional filters
			* 
			* @param int $tag_id Tag ID
			* @param array $filters {
			*     Optional. Additional filters.
			*     @type array    $session_ids Array of session IDs (supports multiple)
			*     @type string   $chamber    Filter by chamber (R/S)
			*     @type string   $status      Filter by status (publish, draft, etc.)
			*     @type string   $gov         Filter by government code
			*     @type string   $orderby    Order by field (default: date_voted)
			*     @type string   $order      Order direction (ASC/DESC, default: DESC)
			* }
			* @return array Array of vote objects
			*/
			public static function get_by_tag(int $tag_id, array $filters = []): array {
				// Default behavior:
				// - If status key is missing: public -> publish (via get()), admin -> all (status => null)
				// - If status key is present: caller controls behavior (including null for "all")
				if (!array_key_exists('status', $filters) && is_admin()) {
					$filters['status'] = null;
				}

				$filters['tag_id'] = $tag_id;
				return self::get($filters);
			}

			/**
			* Save/Update vote with duplicate checking
			* 
			* @param array $data Vote data
			* @param int|null $vote_id Update existing if provided
			* @return int|false Vote ID on success, false on failure
			*/
			public static function save(array $data, ?int $vote_id = null): int|false {
				global $wpdb;
				
				self::log('Votes::save() called with vote_id=' . ($vote_id ?? 'null'), __FILE__, __LINE__);
				self::log('Votes::save() data keys: ' . implode(', ', array_keys($data)), __FILE__, __LINE__);
				self::log('Votes::save() session_id=' . ($data['session_id'] ?? 'MISSING') . ', title=' . ($data['title'] ?? 'MISSING') . ', slug=' . ($data['slug'] ?? 'MISSING'), __FILE__, __LINE__);

				// Unslash incoming text/meta to avoid stored escaped quotes.
				if (isset($data['title']) && is_string($data['title'])) {
					$data['title'] = wp_unslash($data['title']);
				}
				if (isset($data['bill_number']) && is_string($data['bill_number'])) {
					$data['bill_number'] = wp_unslash($data['bill_number']);
				}
				if (isset($data['meta']) && is_array($data['meta'])) {
					$data['meta'] = array_map(static function ($item) {
						return is_string($item) ? wp_unslash($item) : $item;
					}, $data['meta']);
				}
				
				// Validate required fields
				if (empty($data['session_id']) || empty($data['title'])) {
					self::log('Votes::save() VALIDATION FAILED: session_id=' . ($data['session_id'] ?? 'MISSING') . ', title=' . ($data['title'] ?? 'MISSING'), __FILE__, __LINE__);
					return false;
				}

				// Ensure meta is an ARRAY in PHP. Prevents double-encoding; tolerates one level of over-escaping.
				if (isset($data['meta'])) {
					if (is_string($data['meta'])) {
						$decoded = json_decode($data['meta'], true);
						if (!is_array($decoded)) {
							$decoded = json_decode(stripslashes($data['meta']), true);
						}
						$data['meta'] = is_array($decoded) ? $decoded : [];
					} elseif (!is_array($data['meta'])) {
						$data['meta'] = [];
					}

					if (!empty($data['meta'])) {
						$data['meta'] = self::normalize_meta_descriptions_for_storage($data['meta']);
					}
					if (empty($data['meta'])) {
						unset($data['meta']); // Keep DB clean (NULL) when no meta
					}
				}

				// Ensure rollcall_data is an ARRAY in PHP (encoded once here).
				if (isset($data['rollcall_data'])) {
					if (is_string($data['rollcall_data'])) {
						$decoded = json_decode($data['rollcall_data'], true);
						$data['rollcall_data'] = is_array($decoded) ? $decoded : [];
					} elseif (!is_array($data['rollcall_data'])) {
						$data['rollcall_data'] = [];
					}

					if (empty($data['rollcall_data'])) {
						unset($data['rollcall_data']);
					}
				}
				
				// Check for duplicates only when creating new votes (not when updating)
				if (!$vote_id) {
					self::log('Votes::save() Checking for duplicates (new vote)...', __FILE__, __LINE__);
					$duplicate_check = self::check_duplicates($data, $vote_id);
					if ($duplicate_check['is_duplicate']) {
						self::log('Votes::save() Duplicate found, returning existing_id: ' . $duplicate_check['existing_id'], __FILE__, __LINE__);
						return $duplicate_check['existing_id'];
					}
				} else {
					self::log('Votes::save() Skipping duplicate check (updating existing vote_id=' . $vote_id . ')', __FILE__, __LINE__);
				}
					
				// Prepare data for database
				self::log('Votes::save() Preparing db_data...', __FILE__, __LINE__);
				$db_data = [
					'legacy_id' => $data['legacy_id'] ?? null,
					'session_id' => $data['session_id'],
					'legiscan_bid' => $data['legiscan_bid'] ?? null,
					'legiscan_rcid' => $data['legiscan_rcid'] ?? null,
					'gov' => $data['gov'] ?? null,
					'chamber' => $data['chamber'] ?? null,
					'title' => $data['title'],
					'slug' => $data['slug'],
					'bill_number' => $data['bill_number'] ?? '',
					'constitutional' => $data['constitutional'] ?? 'U',
					'rollcall_number' => $data['rollcall_number'] ?? null,
					'rollcall_data' => !empty($data['rollcall_data']) ? json_encode($data['rollcall_data']) : null,
					'status' => $data['status'] ?? 'publish',
					'date_voted' => $data['date_voted'] ?? null,
					'meta' => !empty($data['meta']) ? json_encode($data['meta']) : null
				];
				
				// Define format map for each field
				$format_map = [
					'legacy_id' => '%s',
					'session_id' => '%d',
					'legiscan_bid' => '%d',
					'legiscan_rcid' => '%d',
					'gov' => '%s',
					'chamber' => '%s',
					'title' => '%s',
					'slug' => '%s',
					'bill_number' => '%s',
					'constitutional' => '%s',
					'rollcall_number' => '%s',
					'rollcall_data' => '%s',
					'status' => '%s',
					'date_voted' => '%s',
					'meta' => '%s'
				];
				
				// Remove null values to use database defaults
				$db_data = array_filter($db_data, function($value) {
					return $value !== null;
				});
				
				// Generate format strings based on actual data keys
				$formats = [];
				foreach (array_keys($db_data) as $key) {
					$formats[] = $format_map[$key] ?? '%s';
				}
				
				if ($vote_id) {
					// Update existing
					self::log('Votes::save() Updating existing vote_id=' . $vote_id, __FILE__, __LINE__);
					self::log('Votes::save() db_data keys: ' . implode(', ', array_keys($db_data)), __FILE__, __LINE__);
					$result = $wpdb->update(
						$wpdb->prefix . 'fi_votes',
						$db_data,
						['id' => $vote_id],
						$formats,
						['%d']
					);
					
					self::log('Votes::save() Update result: ' . ($result !== false ? $result : 'FALSE'), __FILE__, __LINE__);
					if ($wpdb->last_error) {
						self::log('Votes::save() Update DB error: ' . $wpdb->last_error, __FILE__, __LINE__);
						self::log('Votes::save() Update DB query: ' . $wpdb->last_query, __FILE__, __LINE__);
					}
					
					return $result !== false ? $vote_id : false;
				} else {
					// Insert new - need all fields with proper formats
					self::log('Votes::save() Inserting new vote', __FILE__, __LINE__);
					$insert_data = [
						'legacy_id' => $data['legacy_id'] ?? null,
						'session_id' => $data['session_id'],
						'legiscan_bid' => $data['legiscan_bid'] ?? null,
						'legiscan_rcid' => $data['legiscan_rcid'] ?? null,
						'gov' => $data['gov'] ?? null,
						'chamber' => $data['chamber'] ?? null,
						'title' => $data['title'],
						'slug' => $data['slug'],
						'bill_number' => $data['bill_number'] ?? '',
						'constitutional' => $data['constitutional'] ?? 'U',
						'rollcall_number' => $data['rollcall_number'] ?? null,
						'rollcall_data' => !empty($data['rollcall_data']) ? json_encode($data['rollcall_data']) : null,
						'status' => $data['status'] ?? 'publish',
						'date_voted' => $data['date_voted'] ?? null,
						'meta' => !empty($data['meta']) ? json_encode($data['meta']) : null
					];
				
				$insert_formats = [];
				foreach (array_keys($insert_data) as $key) {
					$insert_formats[] = $format_map[$key] ?? '%s';
				}
				
				self::log('Votes::save() Insert data keys: ' . implode(', ', array_keys($insert_data)), __FILE__, __LINE__);
				$result = $wpdb->insert(
					$wpdb->prefix . 'fi_votes',
					$insert_data,
					$insert_formats
				);
				
				self::log('Votes::save() Insert result: ' . ($result !== false ? $result : 'FALSE') . ', insert_id: ' . $wpdb->insert_id, __FILE__, __LINE__);
				if ($wpdb->last_error) {
					self::log('Votes::save() Insert DB error: ' . $wpdb->last_error, __FILE__, __LINE__);
					self::log('Votes::save() Insert DB query: ' . $wpdb->last_query, __FILE__, __LINE__);
				}
				
				return $result !== false ? $wpdb->insert_id : false;
			}
		}

		/**
		* Update vote
		* 
		* @param int $vote_id
		* @param array $data
		* @return bool
		*/
		public static function update(int $vote_id, array $data): bool {
			return self::save($data, $vote_id) !== false;
		}

		/**
		* Delete vote
		* 
		* @param int $vote_id
		* @return bool
		*/
		public static function delete(int $vote_id): bool {
			global $wpdb;
			
			// Check if vote exists
			$vote = self::get_by_id($vote_id);
			if (!$vote) {
				return false;
			}
			
			// Delete related records first
			$wpdb->delete($wpdb->prefix . 'fi_voterc', ['vote_id' => $vote_id]);
			
			// Delete vote
			$result = $wpdb->delete($wpdb->prefix . 'fi_votes', ['id' => $vote_id]);
			
			return $result !== false;
		}

		/**
		* Check for duplicate votes
		* 
		* @param array $data
		* @param int|null $exclude_id Exclude this ID from duplicate check
		* @return array ['is_duplicate' => bool, 'existing_id' => int|null]
		*/
		public static function check_duplicates(array $data, ?int $exclude_id = null): array {
			global $wpdb;
			
			$conditions = [];
			$values = [];

			// Check by legacy_id + gov (migration safety; multisite IDs collide without gov prefix)
			if (!empty($data['legacy_id']) && !empty($data['gov'])) {
				$conditions[] = "(legacy_id = %s AND gov = %s)";
				$values[] = (string) $data['legacy_id'];
				$values[] = strtoupper((string) $data['gov']);
			}
			
			// Check by legiscan_rcid (most reliable for Legiscan imports)
			if (!empty($data['legiscan_rcid'])) {
				$conditions[] = "legiscan_rcid = %d";
				$values[] = (int) $data['legiscan_rcid'];
			}
			
			// Check by session_id and slug combination
			if (!empty($data['session_id']) && !empty($data['slug'])) {
				$conditions[] = "(session_id = %d AND slug = %s)";
				$values[] = $data['session_id'];
				$values[] = $data['slug'];
			}
			
			if (empty($conditions)) {
				return ['is_duplicate' => false, 'existing_id' => null];
			}
			
			$where_clause = implode(' OR ', $conditions);
			$sql = "SELECT id FROM {$wpdb->prefix}fi_votes WHERE {$where_clause}";
			
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
		* Get vote statistics
		* 
		* @param string|null $gov
		* @param int|null $session_id
		* @return array
		*/
		public static function get_stats(?string $gov = null, ?int $session_id = null): array {
			global $wpdb;
			
			$where_conditions = [];
			$values = [];
			
			if ($gov) {
				$where_conditions[] = "gov = %s";
				$values[] = $gov;
			}
			
			if ($session_id) {
				// Get all session IDs in the hierarchy (parent + children)
				$session_ids = fi_sessions_get_hierarchy_ids($session_id);
				$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
				$where_conditions[] = "session_id IN ($placeholders)";
				$values = array_merge($values, $session_ids);
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
			//CHAMBERFLAG
			$sql = "
				SELECT 
					COUNT(*) as total,
					COUNT(CASE WHEN constitutional = 'Y' THEN 1 END) as good_votes,
					COUNT(CASE WHEN constitutional = 'N' THEN 1 END) as bad_votes,
					COUNT(CASE WHEN chamber = 'H' THEN 1 END) as house_votes,
					COUNT(CASE WHEN chamber = 'S' THEN 1 END) as senate_votes
				FROM {$wpdb->prefix}fi_votes
				{$where_clause}
			";
			
			if (!empty($values)) {
				$sql = $wpdb->prepare($sql, $values);
			}
//fi_log(str_replace("\n", ' ', $sql),__FILE__,__LINE__, 'debug');			
			return (array) $wpdb->get_row($sql);
		}

		/**
		* Validate vote data
		* 
		* @param array $data
		* @return array ['valid' => bool, 'errors' => array]
		*/
		public static function validate_vote_data(array $data): array {
			$errors = [];
			
			// Required fields
			if (empty($data['session_id'])) {
				$errors[] = 'Session ID is required';
			}
			
			if (empty($data['slug'])) {
				$errors[] = 'Slug is required';
			}
			
			if (empty($data['title'])) {
				$errors[] = 'Title is required';
			}
			
			// Validate constitutional
			if (!empty($data['constitutional']) && !in_array($data['constitutional'], ['Y', 'N', 'U'])) {
				$errors[] = 'Constitutional position must be Y, N, or U (Unknown)';
			}
			
			//CHAMBERFLAG
			// Validate chamber
			if (!empty($data['chamber']) && !in_array($data['chamber'], ['S', 'H'])) {
				$errors[] = 'Chamber must be S (Senate) or H (House)';
			}
			
			// Validate weight
			if (!empty($data['weight']) && (!is_numeric($data['weight']) || $data['weight'] < 1)) {
				$errors[] = 'Weight must be a positive number';
			}
			
			// Validate date
			if (!empty($data['date_voted']) && !self::validate_date($data['date_voted'])) {
				$errors[] = 'Invalid date format (use YYYY-MM-DD)';
			}
			
			return [
				'valid' => empty($errors),
				'errors' => $errors
			];
		}

		/**
		* Validate date format
		* 
		* @param string $date
		* @return bool
		*/
		private static function validate_date(string $date): bool {
			$d = \DateTime::createFromFormat('Y-m-d', $date);
			return $d && $d->format('Y-m-d') === $date;
		}

		/**
		* Get votes with roll-call data
		* 
		* @param array $args Filter arguments
		* @return array
		*/
		public static function get_votes_with_rollcall(array $args = []): array {
			$args['has_rollcall'] = true;
			return self::get($args);
		}

		/**
		* Check if vote has roll-call data
		* 
		* @param int $vote_id
		* @return bool
		*/
		public static function has_rollcall(int $vote_id): bool {
			global $wpdb;
			
			$sql = "SELECT rollcall_data FROM {$wpdb->prefix}fi_votes WHERE id = %d LIMIT 1";
			$rollcall = $wpdb->get_var($wpdb->prepare($sql, $vote_id));
			
			return !empty($rollcall);
		}

		/**
		* Decode vote meta JSON to array.
		* If direct decode fails (e.g. meta was over-escaped on save or by DB layer), tries stripslashes once.
		*
		* @param object $vote Vote object with meta property
		* @return array Decoded meta array
		*/
		public static function decode_meta(object $vote): array {
			if (isset($vote->meta)) {
				if (is_array($vote->meta)) {
					return $vote->meta;
				}

				if (is_string($vote->meta)) {
					$decoded = json_decode($vote->meta, true);
					if (is_array($decoded)) {
						return $decoded;
					}
					// One level of over-escaping (e.g. addslashes on whole JSON, or paste from Word with \\\' in text)
					$unslashed = stripslashes($vote->meta);
					if ($unslashed !== $vote->meta) {
						$decoded = json_decode($unslashed, true);
						if (is_array($decoded)) {
							return $decoded;
						}
					}
				}
			}

			return [];
		}

		/**
		 * Normalize one description HTML string for storage (paste-from-Word / TinyMCE cleanup).
		 * Line endings to \n, smart quotes to straight, no-break spaces to normal space, then wp_kses_post.
		 */
		public static function normalize_meta_description_string(string $value): string {
			if ($value === '') {
				return '';
			}
			$value = str_replace(["\r\n", "\r"], "\n", $value);
			$value = str_replace(["\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99"], ['"', '"', "'", "'"], $value);
			// TinyMCE/paste can insert U+00A0 or &nbsp;; normalize to plain space so output isn't littered with &nbsp;
			$value = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $value);
			return wp_kses_post($value);
		}

		/**
		 * Normalize description_short, description_medium, description_long in meta array before json_encode.
		 */
		public static function normalize_meta_descriptions_for_storage(array $meta): array {
			$keys = ['description_short', 'description_medium', 'description_long'];
			foreach ($keys as $key) {
				if (isset($meta[$key]) && is_string($meta[$key])) {
					$meta[$key] = self::normalize_meta_description_string($meta[$key]);
				}
			}
			return $meta;
		}

		/**
		 * Get description text with fallback logic
		 * Checks description_short, description_medium, description_long in order
		 * 
		 * @param array $meta Vote meta array
		 * @param string $format Format type: 'scorecard' uses description_short, 'freedomindex' uses description_long
		 * @return string Description text or empty string
		 */
		public static function get_description(array $meta): array {
			// Legacy key support (fallback for old data)
			$short = fi_clean_content($meta['description_short'] ?? '');
			$medium = fi_clean_content($meta['description_medium'] ?? '');
			$long = fi_clean_content($meta['description_long'] ?? '');
			//Short to long and long to short. If only long then
			$text = [
				'short' => '',
				'medium' => '',
				'long' => '',
			];
			//short = try: short, medium, long
			if (!empty($short)) {
				$text['short'] = $short;
			} elseif (!empty($medium)) {
				$text['short'] = $medium;
				$medium = null;
			} elseif (!empty($long)) {
				$text['short'] = $long;
				$long = null;
			}
			//medium = try: medium, long
			if (!empty($medium)) {
				$text['medium'] = $medium;
			//} elseif (!empty($long)) {
			//	$text['medium'] = $long;
			//	$long = null;
			}
			//long = try: long if not already used
			if (!empty($long)) {
				$text['long'] = $long;
			}
			return $text;
		}

		/**
		* Get vote status options
		* 
		* @return array Status options
		*/
		public static function get_status_options(): array {
			return [
				'publish' => 'Published',
				'draft' => 'Draft',
				'archived' => 'Archived',
			];
		}

		/** Get Rollcall Data */
		public static function get_rollcall_data(int $vote_id): string {
			global $wpdb;
			
			$sql = "SELECT rollcall_data FROM {$wpdb->prefix}fi_votes WHERE id = %d LIMIT 1";
			return $wpdb->get_var($wpdb->prepare($sql, $vote_id));
		}

		/* Get Rollcall Data for a batch of vote IDs from fi_voterc table and return array: vote_id => [{legislator_id => {case},...]*/
		public static function get_rollcall_map(array $vote_ids): array {
			global $wpdb;
			$sql = "SELECT vote_id, legislator_id, cast FROM {$wpdb->prefix}fi_voterc WHERE vote_id IN (" . implode(',', $vote_ids) . ")";
			$rollcalls = $wpdb->get_results($sql);

			$rollcall_map = [];
			foreach ($rollcalls as $rollcall) {
				$rollcall_map[$rollcall->vote_id][$rollcall->legislator_id] = $rollcall->cast;
			}

			return $rollcall_map;
		}

		/**
		* Get votes for a specific legislator
		* Joins with fi_voterc table to get votes cast by a legislator
		* 
		* @param int $legislator_id
		* @param array $args {
		*     Optional. Filter arguments.
		*     @type int|string $session_id Filter by session ID (supports session hierarchy)
		*     @type string $search Search in vote titles or bill keys
		*     @type int $limit Number of results (default: 50)
		*     @type int $offset Offset for pagination (default: 0)
		* }
		* @return array Array of vote objects with voter record data
		*/
		public static function get_by_legislator(int $legislator_id, array $args = []): array {
			// Default behavior:
			// - If status key is missing: public -> publish (via get()), admin -> all (status => null)
			// - If status key is present: caller controls behavior (including null for "all")
			$defaults = [
				'session_id' => null,
				'search' => null,
				'status' => is_admin() ? null : 'publish',
				'limit' => 50,
				'offset' => 0,
				'orderby' => 'date_voted',
				'order' => 'DESC',
			];

			$args = wp_parse_args($args, $defaults);
			$args['legislator_id'] = $legislator_id;

			return self::get($args);
		}

		/** Format cost HTML */
		public static function format_cost(string $cost): array {
			$cost_data = [
				'raw' => $cost,
				'num' => '',
				'formatted' => '',
				'class' => '',
				'html' => '',
				'class-text' => '',
				'effect-text' => 'effect',
				//'minor' => true,
				'sentence' => '',
			];
			if(empty($cost)) {
				return $cost_data;
			}
			//Remove decimals and ignore if < 1
			// Implemented in PDF but Web shows all values.
			//$num = floatval(trim($cost, '+-'));
			// If cost is greater than -1 and less than 1, treat as zero—ignore values between -1 and 1 exclusive
			//if ($num < -1 && $num > 1) {
			//	$cost_data['minor'] = false;
			//}

			// remove + or - or $ and remove commas from X,000.00 leave decimal
			$cost_data['num'] = trim(str_replace(['+', '-', '$', ','], '', $cost));
			//If rounded to nearest whole number = 0 then skip
			//if(round($cost_data['raw']) != 0) {
			if($cost_data['num'] != '') {
				$cost_data['formatted'] = '$' . number_format_i18n((float) $cost_data['num'], 2);
				$cost_data['rounded'] = '$' . number_format_i18n((float) $cost_data['num'], 0);
				if(substr(trim($cost),0,1) == '+'){
					$cost_data['indicator'] = '+';
					$cost_data['class'] = 'text-success';
					$cost_data['class-text'] = 'text-success';
					$cost_data['effect-text'] = 'benefit';
				} else {
					$cost_data['indicator'] = '-';
					$cost_data['class'] = 'text-danger';
					$cost_data['class-text'] = 'text-danger';
					$cost_data['effect-text'] = 'cost';
				}
				$cost_data['sentence'] = '<div class="vote-cost ' . $cost_data['class-text'] . '">Estimated ' . $cost_data['effect-text'] . ' per household: <b>' . $cost_data['indicator'].$cost_data['formatted'] . '/year.</b></div>';
				$cost_data['html'] = '<span class="' . esc_attr($cost_data['class-text']) . '">' . $cost_data['indicator'].wp_kses_post($cost_data['formatted']) . '</span>';
			}
			return $cost_data;
		}

		/**
		* Wrap fi_log in function so we can log this class only if necessary.
		*/
		public static function log(string $message, string $file = '', int $line = 0, string $level = 'debug'): void {
			//fi_log_area('votes', $message, $file, $line, $level);
		}
	}
}

//namespace for global functions
namespace {

	/* Get votes with optional filtering */
	function fi_votes_get(array $args = []): array|int {
		return \FI\Core\Votes::get($args);
	}

	/* Get a single vote by ID */
	function fi_vote_get(int $vote_id): ?object {
		return \FI\Core\Votes::get_by_id($vote_id);
	}

	/* Get votes by tag ID with optional filters */
	function fi_votes_get_by_tag(int $tag_id, array $filters = []): array {
		$filters['tag_id'] = $tag_id;
		return fi_votes_get($filters);
	}

	/* Save/Update vote */
	function fi_vote_save(array $data, ?int $vote_id = null): int|false {
		return \FI\Core\Votes::save($data, $vote_id);
	}

	/* Update vote */
	function fi_vote_update(int $vote_id, array $data): bool {
		return \FI\Core\Votes::update($vote_id, $data);
	}

	/* Delete vote */
	function fi_vote_delete(int $vote_id): bool {
		return \FI\Core\Votes::delete($vote_id);
	}

	/* Get votes by session */
	function fi_votes_get_by_session(int $session_id, array $filters = []): array {
		return \FI\Core\Votes::get_by_session($session_id, $filters);
	}

	/* Get votes by government */
	function fi_votes_get_by_gov(string $gov, array $filters = []): array {
		return \FI\Core\Votes::get_by_gov($gov, $filters);
	}

	/* Check if vote has roll-call data */
	function fi_vote_has_rollcall(int $vote_id): bool {
		return \FI\Core\Votes::has_rollcall($vote_id);
	}

	/* Get vote statistics */
	function fi_votes_stats(?string $gov = null, ?int $session_id = null): array {
		return \FI\Core\Votes::get_stats($gov, $session_id);
	}

	/* Decode vote meta JSON to array */
	function fi_vote_decode_meta(object $vote): array {
		return \FI\Core\Votes::decode_meta($vote);
	}

	/**
	 * Get description text with fallback logic
	 * 
	 * @param array $meta Vote meta array
	 * @param string $format Format type: 'scorecard' (default) or 'freedomindex'
	 * @return array Description text or empty array
	 */
	function fi_vote_get_description(array $meta, string $format = 'scorecard'): array {
		return \FI\Core\Votes::get_description($meta);
	}

	/* Get vote status options */
	function fi_vote_get_status_options(): array {
		return \FI\Core\Votes::get_status_options();
	}

	/* Get vote rollcall JSON */
	function fi_vote_rollcall_data(int $vote_id): string {
		return \FI\Core\Votes::get_rollcall_data($vote_id);
	}

	/* Get rollcall data for a batch of vote IDs */
	function fi_vote_rollcall_map(array $vote_ids): array {
		return \FI\Core\Votes::get_rollcall_map($vote_ids);
	}

	/* Get votes for a specific legislator */
	function fi_votes_get_by_legislator(int $legislator_id, array $args = []): array {
		return \FI\Core\Votes::get_by_legislator($legislator_id, $args);
	}

	/* Get vote by Legiscan Rollcall ID */
	function fi_vote_get_by_legiscan_rcid(int $legiscan_rcid, ?int $session_id = null): ?object {
		return \FI\Core\Votes::get_by_legiscan_rcid($legiscan_rcid, $session_id);
	}

	/**
	 * Update Legiscan IDs for a vote
	 * Uses direct database update to avoid save() method validation requirements
	 * 
	 * @param int $vote_id FI vote ID
	 * @param int|null $legiscan_bid Legiscan bill ID
	 * @param int|null $legiscan_rcid Legiscan rollcall ID
	 * @return bool True on success, false on failure
	 */
	function fi_votes_update_legiscan(int $vote_id, ?int $legiscan_bid = null, ?int $legiscan_rcid = null): bool {
		global $wpdb;
		
		$update_data = [];
		$update_format = [];
		$result = false;
		
		if ($legiscan_bid !== null) {
			$update_data['legiscan_bid'] = $legiscan_bid;
			$update_format[] = '%d';
		}
		
		if ($legiscan_rcid !== null) {
			$update_data['legiscan_rcid'] = $legiscan_rcid;
			$update_format[] = '%d';
		}
		
		if (empty($update_data)) {
			return false; // Nothing to update
		}
		
		// Perform direct database update (no need to verify existence - update will return 0 if not found)
		/* Temp disable until we confirm accurate matching is working
		$result = $wpdb->update(
			$wpdb->prefix . 'fi_votes',
			$update_data,
			['id' => $vote_id],
			$update_format,
			['%d']
		);
		*/
		return $result !== false;
	}

	/**
	 * Merge/patch vote meta WITHOUT requiring a full vote update payload.
	 * This avoids re-validating required fields when we only want to store import/audit metadata.
	 * Wrapper for backward compatibility - uses unified trait
	 */
	function fi_vote_meta_merge(int $vote_id, array $patch): bool {
		if ($vote_id <= 0) {
			return false;
		}
		return \FI\Core\Votes::update_meta($vote_id, 'fi_votes', $patch);
	}

	/* Get vote result CSS class */
	function fi_vote_get_class(string $cast, string $constitutional): string {
		// 4-state casts: Y, N, P (present), X (not voted). P and X are "no vote" for scoring.
		if ($cast === 'P' || $cast === 'X' || $cast === '') {
			return 'vote-none';
		}
		
		if ($cast === $constitutional) {
			return 'vote-correct';
		}
		
		return 'vote-incorrect';
	}

	/**
	 * Format vote display with Bootstrap icons and colors.
	 * Handles vote indicators and vote cast indicators.
	 *
	 * @param array $args {
	 *     @type string $cast           Legislator's vote ('Y', 'N', 'P', 'X')
	 *     @type string $constitutional Good vote position ('Y', 'N')
	 *     @type string $format         Output format: 'icon', 'badge', 'text', 'full'
	 * }
	 * @return array|string
	 */
	function fi_vote_format(array $args = []): array {
		// Check if cast is provided - if not, skip cast evaluation
		$cast_provided = isset($args['cast']);
		$cast = $cast_provided ? strtoupper($args['cast']) : '';
		$constitutional = strtoupper($args['constitutional'] ?? '');
		$format = $args['format'] ?? 'full';

		// Static maps cached after first call (performance optimization)
		static $cast_map = null;
		static $constitutional_map = null;
		static $default_cast = null;
		static $default_constitutional = null;

		if ($cast_map === null) {
			$cast_map = [
				'Y' => ['label' => 'Yes', 'icon' => 'bi bi-hand-thumbs-up'],
				'N' => ['label' => 'No', 'icon' => 'bi bi-hand-thumbs-down'],
				'P' => ['label' => 'Present', 'icon' => 'bi bi-x-circle'],
				'X' => ['label' => 'None', 'icon' => 'bi bi-x-circle'],
			];
			$default_cast = ['label' => '--', 'icon' => 'bi-x-circle'];
			$constitutional_map = [
				'Y' => ['label' => 'Yes', 'icon' => 'bi bi-hand-thumbs-up'],
				'N' => ['label' => 'No', 'icon' => 'bi bi-hand-thumbs-down'],
				'U' => ['label' => 'Unknown', 'icon' => 'bi bi-question-circle'],
			];
			$default_constitutional = ['label' => 'Unknown', 'icon' => 'bi-question-circle'];
		}

		$constitutional_info = $constitutional_map[$constitutional] ?? $default_constitutional;
		
		// Constitutional position: always default/black (no color class)
		$constitutional_class = '';
		
		// If cast is not provided, skip cast evaluation and return constitutional-only info
		if (!$cast_provided) {
			$constitutional_icon_esc = esc_attr($constitutional_info['icon']);
			$constitutional_label_esc = esc_html($constitutional_info['label']);
			
			// Early returns for simple formats
			if ($format === 'text') {
				return ['text' => $constitutional_label_esc];
			}
			
			if ($format === 'icon') {
				return ['icon' => '<i class="' . esc_attr($constitutional_info['icon']) . '"></i>'];
			}
			
			if ($format === 'badge') {
				$badge_class = ($constitutional === 'Y') ? 'bg-success' : (($constitutional === 'N') ? 'bg-danger' : 'bg-secondary');
				return ['badge' => '<span class="badge ' . esc_attr($badge_class) . ' text-white rounded-pill fs-7">' . $constitutional_label_esc . '</span>'];
			}
			
			// Full format - return constitutional info only
			return [
				'raw' => '',
				'is_counted' => 0,
				'is_match' => 0,
				'is_no_vote' => 0,
				'vote_text' => $constitutional_label_esc,
				'vote_class' => $constitutional_class,
				'vote_class_icon' => $constitutional_icon_esc,
				'cast_text' => '',
				'cast_class' => '',
				'cast_class_icon' => '',
				'cast_bg-class' => '',
				'table_symbol' => '',
				'table_class' => '',
				'icon' => '<i class="' . esc_attr($constitutional_info['icon']) . '"></i>',
				'badge' => '<span class="badge ' . esc_attr(($constitutional === 'Y') ? 'bg-success' : (($constitutional === 'N') ? 'bg-danger' : 'bg-secondary')) . ' text-white rounded-pill fs-7">' . $constitutional_label_esc . '</span>',
			];
		}
		
		// Cast is provided - proceed with normal evaluation
		$cast_info = $cast_map[$cast] ?? $default_cast;
		
		// Determine colors based on new logic:
		// Constitutional: default/black (no color class)
		// Cast: green if matches constitutional, red if doesn't match (for Y/N), muted for non-votes
		$is_no_vote = ($cast === 'P' || $cast === 'X' || $cast === '');
		$is_valid_vote = ($cast === 'Y' || $cast === 'N');
		$is_match = ($is_valid_vote && $cast === $constitutional);
		
		// Vote cast: green if match, red if no match (for valid votes), muted for non-votes
		if ($is_no_vote) {
			$cast_class = 'text-muted';
		} elseif ($is_match) {
			$cast_class = 'text-success';
		} else {
			// Valid vote but doesn't match constitutional
			$cast_class = 'text-danger';
		}

		// Determine if vote is counted for scoring
		$is_counted = $is_valid_vote ? 1 : 0;

		// Early returns for simple formats (avoids building full array, but still returns array for consistency)
		if ($format === 'text') {
			return ['text' => $cast_info['label']];
		}

		if ($format === 'icon') {
			return ['icon' => '<i class="' . esc_attr($cast_info['icon']) . ' ' . esc_attr($cast_class) . '"></i>'];
		}

		// Calculate status for table/badge display
		if ($is_no_vote) {
			$status_class = 'text-secondary';
			$status_bg = 'bg-secondary text-white';
			$table_symbol = '<i class="bi bi-ban"></i>';
			$table_class = 'text-muted';
			$cast_bg_class = 'bg-secondary text-white';
			$cast_img_bi = 'bi-vote-none.png';
			$cast_img_icon = 'fi_vote-none.png';
		} elseif ($is_match) {
			$status_class = 'text-success';
			$status_bg = 'bg-success text-white';
			$table_symbol = '<i class="fa-solid fa-star"></i>';
			$table_class = 'text-success';
			$cast_bg_class = 'bg-success text-white';
			//$cast_img_thumb = 'bi-hand-thumbs-up-success.png';
			//$cast_img_thumb_fill = 'bi-hand-thumbs-up-success-fill.png';
			$cast_img_bi = 'bi-vote-good.png';
			$cast_img_icon = 'fi_vote-good.png';
		} else {
			$status_class = 'text-danger';
			$status_bg = 'bg-danger text-white';
			$table_symbol = '<i class="fa-solid fa-x"></i>';
			$table_class = 'text-danger';
			$cast_bg_class = 'bg-danger text-white';
			//$cast_img_thumb = 'bi-hand-thumbs-down-success.png';
			//$cast_img_thumb_fill = 'bi-hand-thumbs-down-success-fill.png';
			$cast_img_bi = 'bi-vote-bad.png';
			$cast_img_icon = 'fi_vote-bad.png';
		}

		// Early return for badge format (avoids building full array, but still returns array for consistency)
		if ($format === 'badge') {
			return ['badge' => '<span class="badge ' . esc_attr($status_bg) . ' rounded-pill fs-7 fiv-'.$cast.'">' . esc_html($cast_info['label']) . '</span>'];
		}

		// Full format: build complete result array
		$constitutional_icon_esc = esc_attr($constitutional_info['icon']);
		$constitutional_label_esc = esc_html($constitutional_info['label']);
		$cast_icon_esc = esc_attr($cast_info['icon']);
		$cast_label_esc = esc_html($cast_info['label']);

		return [
			'raw' => $cast,
			'is_counted' => $is_counted,
			'is_match' => $is_match ? 1 : 0,
			'is_no_vote' => $is_no_vote ? 1 : 0,
			'vote_text' => $constitutional_label_esc,
			'vote_class' => $constitutional_class, // Default/black (no color class)
			'vote_class_icon' => $constitutional_icon_esc, // Icon class only (no color) - already includes "bi bi-"
			'cast_text' => $cast_label_esc,
			'cast_class' => $cast_class . ' fiv-'.$cast, // Green/red/muted based on match
			'cast_bg-class' => $cast_bg_class,
			'cast_class_icon' => $cast_icon_esc, // Icon class only (color is in cast_class) - already includes "bi bi-"
			'cast_img_bi' => $cast_img_bi,
			'cast_img_icon' => $cast_img_icon,
			// Table format keys (always available)
			'table_symbol' => $table_symbol,
			'table_class' => $table_class,
			'icon' => '<i class="' . esc_attr($cast_info['icon']) . ' ' . esc_attr($cast_class) . '"></i>',
			'badge' => '<span class="badge ' . esc_attr($status_bg) . ' rounded-pill fs-7 fiv-'.$cast.'">' . esc_html($cast_info['label']) . '</span>',
		];
	}

	//Formate score display. Append classes if >=90
	//Full score matrix classes not necessary because we are only color coding 90+ | fi_score_class_bg($score) . fi_score_class_bg_text($score);
	function fi_score_format($score): array {
		//Handle score = 'NA'
		if($score == 0 || ($score != null && $score != false && $score != '')){
			if( is_numeric($score) ){
				$text = esc_html($score).'%';
				$class = ($score >= 90 ? 'bg-success text-white' : 'bg-primary text-white');
			}else{
				$text = 'N/A';
				$class = 'bg-secondary text-white';
			}
			$badge = '<span class="badge '.$class.' fs-7">'.$text.'</span>';
			$button = '<span id="fi-vote-score-btn" class="btn btn-sm '.$class.' fs-7 fw-bold rounded-start-0 flex-fill" style="pointer-events: none; cursor: default;">'
				.	'Score: '.$text.'</span>';
		}else{
			$text = '';
			$badge = '';
			$button = '';
		}

		$data = [
			'score' => $score,
			'text' => $text,
			'badge' => $badge,
			'button' => $button,
		];
		return $data;
	}


	function fi_vote_cost_format(string $cost): array {
		return \FI\Core\Votes::format_cost($cost);
	}

	/**
	 * Helper function: Returns vote label as string for easy template use
	 * 
	 * @param array $args Same arguments as fi_vote_format()
	 * @return string Vote label (e.g., "Yes", "No", "No Vote")
	 */
	function fi_vote_format_text(array $args = []): string {
		$result = fi_vote_format(array_merge($args, ['format' => 'text']));
		return $result['text'] ?? '';
	}

	/**
	 * Helper function: Returns vote icon HTML as string for easy template use
	 * 
	 * @param array $args Same arguments as fi_vote_format()
	 * @return string Icon HTML (e.g., '<i class="bi bi-hand-thumbs-up"></i>')
	 */
	function fi_vote_format_icon(array $args = []): string {
		$result = fi_vote_format(array_merge($args, ['format' => 'icon']));
		return $result['icon'] ?? '';
	}

	/**
	 * Helper function: Returns vote badge HTML as string for easy template use
	 * 
	 * @param array $args Same arguments as fi_vote_format()
	 * @return string Badge HTML (e.g., '<span class="badge">...</span>')
	 */
	function fi_vote_format_badge(array $args = []): string {
		$result = fi_vote_format(array_merge($args, ['format' => 'badge']));
		return $result['badge'] ?? '';
	}

	/* Get vote meta value by key */
	function fi_vote_get_meta($record, string $key, $default = null) {
		return \FI\Core\Votes::get_meta($record, $key, $default);
	}

	/* Get all vote meta */
	function fi_vote_get_all_meta($record): array {
		return \FI\Core\Votes::get_all_meta($record);
	}

	/* Update vote meta key(s) without affecting other keys */
	function fi_vote_update_meta(int $record_id, array $meta_updates): bool {
		return \FI\Core\Votes::update_meta($record_id, 'fi_votes', $meta_updates);
	}

	/* Delete vote meta key(s) */
	function fi_vote_delete_meta(int $record_id, $keys): bool {
		return \FI\Core\Votes::delete_meta($record_id, 'fi_votes', $keys);
	}

	/* Set entire vote meta (replaces all) */
	function fi_vote_set_all_meta(int $record_id, array $meta): bool {
		return \FI\Core\Votes::set_all_meta($record_id, 'fi_votes', $meta);
	}

	function fi_vote_img(string $type,$size=[48,48],$class='',$style=''): string {
		$img = '<img src="'.FI_URL_IMG;
		switch($type){
			case 'good':
				//$img .= 'bi-vote-good.png';
				$img .= 'fi_vote-good.png';
				$alt = 'Constitutional Vote';
				$width = $size[0];
				$height = $size[1];
				break;
			case 'bad':
				//$img .= 'bi-vote-bad.png';
				$img .= 'fi_vote-bad.png';
				$alt = 'Unconstitutional Vote';
				$width = $size[0];
				$height = $size[1];
				break;
			case 'none':
				//$img .= 'bi-vote-none.png';
				$img .= 'fi_vote-none.png';
				$alt = 'Did not Vote';
				$width = $size[0];
				$height = $size[1];
				break;
		}
		$img .= '" alt="'.$alt.'" height="'.$height.'" width="'.$width.'" class="'.$class.'" style="'.$style.'">';
		return $img;
	}

}