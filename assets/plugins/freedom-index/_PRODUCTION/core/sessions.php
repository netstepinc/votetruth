<?php
namespace FI\Core{

	if (!defined('ABSPATH')) exit;

	// Load Meta trait
	require_once __DIR__ . '/traits/meta.php';

	/**
	* Sessions Table I/O Operations
	* All database operations for the fi_sessions table.
	*/
	final class Sessions {
		
		// Use unified meta handling trait
		use \FI\Core\Traits\Meta;

		/**
		 * Request-level cache for get() results
		 * Key: md5 hash of serialized args
		 */
		private static $cache_get = [];

		/**
		 * Request-level cache for get_by_gov() results
		 * Key: gov + serialized filters
		 */
		private static $cache_by_gov = [];

		/**
		* Get sessions with optional filtering
		* 
		* @param array $args {
		*     Optional. Arguments to filter sessions.
		* 
		*     @type string       $gov           Filter by government code
		*     @type bool         $is_current    Filter by current status
		*     @type string       $search        Search in names
		*     @type string       $orderby       Order by field (name, date_start, etc.)
		*     @type string       $order         Order direction (ASC/DESC)
		*     @type int          $per_page      Number per page
		*     @type int          $page          Page number
		*     @type bool         $count         Return count only
		* }
		* @return array|int Array of session objects or count if $count is true
		*/
		public static function get(array $args = []): array|int {
			global $wpdb;
			
			$defaults = [
				'id' => null,
				'slug' => null,
				'gov' => null,
				'is_current' => null,
				'status' => null, // Auto-set to 'publish' for public, null for admin
				'search' => null,
				'parent_id' => null, // null = top-level only on frontend, specific ID = children of that parent
				'orderby' => 'date_start',
				'order' => 'DESC',
				'per_page' => -1,
				'page' => 1,
				'count' => false
			];
				
			$args = wp_parse_args($args, $defaults);
			
			// Auto-filter by status='publish' for public queries (unless explicitly overridden)
			if (!is_admin() && $args['status'] === null) {
				$args['status'] = 'publish';
			}

			// Check request-level cache first (fastest)
			$cache_key = md5(serialize($args));
			if (isset(self::$cache_get[$cache_key])) {
				return self::$cache_get[$cache_key];
			}

			// Cache query results (file system cache)
			$cacheKey = fi_cache_key('sessions/get', $args);
			self::log('Sessions::get:Cache key: ' . $cacheKey, __FILE__, __LINE__);
			$results = fi_cache($cacheKey); //1 day
			if($results){
				// Store in request-level cache
				self::$cache_get[$cache_key] = $results;
				return $results;
			}

			// Build WHERE clause
			$where_conditions = [];
			$where_values = [];
			
			if ($args['id']) {
				$where_conditions[] = "id = %d";
				$where_values[] = $args['id'];
			}
			
			if ($args['slug']) {
				$where_conditions[] = "slug = %s";
				$where_values[] = $args['slug'];
			}
			
			if ($args['gov']) {
				$where_conditions[] = "gov = %s";
				$where_values[] = $args['gov'];
			}

			// Parent/child session filtering
			// Frontend: Show only top-level sessions (parent_id = 0) unless explicitly requesting children
			// Admin: Show all sessions (no automatic filtering)
			if (!is_admin()) {
			if ($args['parent_id'] === null) {
				// Default: show only top-level sessions on frontend
				$where_conditions[] = "parent_id IS NULL";
				} elseif ($args['parent_id'] > 0) {
					// Explicitly requesting children of a specific parent
					$where_conditions[] = "parent_id = %d";
					$where_values[] = $args['parent_id'];
				}
				// If parent_id === -1, show all sessions (no filter)
			}
		
			// Status filter (publish/draft)
			if ($args['status'] !== null) {
				$where_conditions[] = "status = %s";
				$where_values[] = $args['status'];
			}

			if ($args['is_current'] !== null) {
				$where_conditions[] = "is_current = %d";
				$where_values[] = $args['is_current'] ? 1 : 0;
			}
			
			if ($args['search']) {
				$where_conditions[] = "(name LIKE %s OR slug LIKE %s)";
				$search_term = '%' . $wpdb->esc_like($args['search']) . '%';
				$where_values[] = $search_term;
				$where_values[] = $search_term;
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
			// Build ORDER BY clause
			// Summary: date_start is not always known (especially right after JSON migration).
			// We avoid "guessing" dates. Instead:
			// - Prefer sessions WITH dates first, ordered by date_start DESC
			// - For sessions WITHOUT dates, fall back to name/slug ordering (often begins with year range)
			// - Always add id as a stable final tie-breaker
			$dir = strtoupper((string) ($args['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
			$orderby_field = sanitize_key((string) ($args['orderby'] ?? 'date_start'));
			$allowed_fields = ['date_start', 'date_end', 'name', 'slug', 'id'];
			if (!in_array($orderby_field, $allowed_fields, true)) {
				$orderby_field = 'date_start';
			}

			// NOTE: We intentionally do NOT use sanitize_sql_orderby() here because we need a multi-column sort
			// with a NULL-first/last expression; we keep it safe by fully controlling the allowed fields/directions.
			if ($orderby_field === 'date_start') {
				$orderby = "date_start IS NULL ASC, date_start {$dir}, name {$dir}, id DESC";
			} elseif ($orderby_field === 'date_end') {
				$orderby = "date_end IS NULL ASC, date_end {$dir}, name {$dir}, id DESC";
			} elseif ($orderby_field === 'name') {
				$orderby = "name {$dir}, id DESC";
			} elseif ($orderby_field === 'slug') {
				$orderby = "slug {$dir}, id DESC";
			} else { // id
				$orderby = "id {$dir}";
			}
			
			// Build LIMIT clause
			$limit_clause = '';
			if ($args['per_page'] > 0) {
				$offset = ($args['page'] - 1) * $args['per_page'];
				$limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", $args['per_page'], $offset);
			}
			
			if ($args['count']) {
				$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}fi_sessions {$where_clause}";
				
				if (!empty($where_values)) {
					$sql = $wpdb->prepare($sql, $where_values);
				}
				
				return (int) $wpdb->get_var($sql);
			}
			
			$sql = "
				SELECT * FROM {$wpdb->prefix}fi_sessions
				{$where_clause}
				ORDER BY {$orderby}
				{$limit_clause}
			";
			
			if (!empty($where_values)) {
				$sql = $wpdb->prepare($sql, $where_values);
			}
			self::log('Sessions::get:SQL: ' . str_replace("\n", ' ', $sql), __FILE__, __LINE__);
			$results = $wpdb->get_results($sql);
			
			// Add " Congress" suffix for US sessions
			foreach ($results as $session) {
				if (!empty($session->name) && strtoupper($session->gov ?? '') === 'US' && strpos($session->name, 'Congress') === false) {
					$session->name .= ' Congress';
				}
			}

			//Cache and return results
			fi_cache($cacheKey,$results);
			self::$cache_get[$cache_key] = $results;
			return $results;
		}

		/**
		* Get a single session by ID
		* 
		* @param int $session_id
		* @return object|null
		*/
		public static function get_by_id(int $session_id): ?object {
			$results = self::get(['id' => $session_id, 'per_page' => 1]);
			return $results[0] ?? null;
		}

		/**
		* Get session by slug
		* 
		* @param string $slug
		* @param string|null $gov
		* @return object|null
		*/
		//SESSIONSLUG: Mark this function as deprecated for public use - keep only for admin/import reference, add deprecation notice
		public static function get_by_slug(string $slug, ?string $gov = null): ?object {
			$args = ['slug' => $slug, 'per_page' => 1];
			if ($gov) {
				$args['gov'] = $gov;
			}
			$results = self::get($args);
			return $results[0] ?? null;
		}

		/**
		* Get sessions by government
		* 
		* @param string $gov
		* @param array $filters Additional filters
		* @return array
		*/
		public static function get_by_gov(string $gov, array $filters = []): array {
			// Create cache key from gov and filters
			$cache_key = $gov . '|' . md5(serialize($filters));
			
			// Check cache first
			if (isset(self::$cache_by_gov[$cache_key])) {
				return self::$cache_by_gov[$cache_key];
			}
			
			$args = array_merge($filters, ['gov' => $gov]);
			self::log('Sessions::get_by_gov:Args: ' . json_encode($args), __FILE__, __LINE__);
			$result = self::get($args);
			
			// Cache result
			self::$cache_by_gov[$cache_key] = $result;
			
			return $result;
		}
		
		/**
		* Clear the sessions cache (useful for testing or after updates)
		*/
		public static function clear_cache(): void {
			self::$cache_by_gov = [];
			self::$cache_get = []; // Clear request-level cache too
		}

		/**
		* Get current session for a government
		* First checks for is_current = 1, then falls back to programmatic determination
		* 
		* @param string $gov
		* @return object|null
		*/
		public static function get_current(string $gov): ?object {
			$gov = strtoupper($gov);
			
			// First, try to get session marked as current (via get() query builder)
			$results = self::get([
				'gov' => $gov,
				'is_current' => true,
				'orderby' => 'date_start',
				'order' => 'DESC',
				'per_page' => 1,
			]);
			$session = is_array($results) ? ($results[0] ?? null) : null;
			
			// If no session is marked as current, determine programmatically
			if (!$session) {
			$sessions = self::get_by_gov($gov, ['orderby' => 'date_start', 'order' => 'DESC']);
			
		// Look for main session (parent_id IS NULL)
		foreach ($sessions as $s) {
			if ($s->parent_id === null || (int)$s->parent_id === 0) {
					$session = $s;
					break;
					}
				}
				
				// Fallback to first session if no parent found
				if (!$session && !empty($sessions)) {
					$session = $sessions[0];
				}
			}
			
			// Add " Congress" suffix for US sessions
			if ($session && !empty($session->name) && strtoupper($session->gov ?? '') === 'US' && strpos($session->name, 'Congress') === false) {
				$session->name .= ' Congress';
			}
			
			return $session;
		}
		
		/**
		* Get current session ID for a government (convenience method)
		* 
		* @param string $gov
		* @return int|null
		*/
		public static function get_current_id(string $gov): ?int {
			$session = self::get_current($gov);
			return $session ? (int) $session->id : null;
		}

		/**
		* Get session by Legiscan ID
		* 
		* @param int $legiscan_id Legiscan session ID
		* @param string|null $gov Optional government code for additional filtering
		* @return object|null Session object or null if not found
		*/
		public static function get_by_legiscan_id(int $legiscan_id, ?string $gov = null): ?object {
			global $wpdb;
			
			$where_conditions = ["legiscan_id = %d"];
			$where_values = [$legiscan_id];
			
			if ($gov) {
				$where_conditions[] = "gov = %s";
				$where_values[] = $gov;
			}
			
			$where_clause = implode(' AND ', $where_conditions);
			$sql = "SELECT * FROM {$wpdb->prefix}fi_sessions WHERE {$where_clause} LIMIT 1";
			
			$session = $wpdb->get_row($wpdb->prepare($sql, $where_values));
			
			if ($session) {
				// Decode meta JSON
				if (!empty($session->meta)) {
					$session->meta = json_decode($session->meta, true);
				}
			}
			
			return $session ?: null;
		}

		/**
		* Save/Update session with duplicate checking
		* 
		* @param array $data Session data
		* @param int|null $session_id Update existing if provided
		* @return int|false Session ID on success, false on failure
		*/
		public static function save(array $data, ?int $session_id = null): int|false {
			global $wpdb;
			
			// Validate required fields
			if (empty($data['name']) || empty($data['gov'])) {
				return false;
			}
			
			// Normalize gov to string (handle integer 0 case)
			$data['gov'] = (string) $data['gov'];
			
			// Generate slug if not provided
			if (empty($data['slug'])) {
				$data['slug'] = self::generate_slug($data['name'], $data['gov']);
			}
			
			// If marking as current, unset current for all other sessions in this gov
			$is_current = !empty($data['is_current']);
			if ($is_current) {
				$wpdb->update(
					$wpdb->prefix . 'fi_sessions',
					['is_current' => 0],
					['gov' => $data['gov']],
					['%d'],
					['%s']
				);
			}
			
			// Check for duplicates BEFORE attempting insert/update
			$duplicate_check = self::check_duplicates($data, $session_id);
			if ($duplicate_check['is_duplicate']) {
				// Return existing ID - don't attempt insert
				return $duplicate_check['existing_id'];
			}
				
			// Prepare data for database - only include fields that were explicitly provided
			$db_data = [];
			
			// Required fields (always included)
			$db_data['gov'] = $data['gov'];
			$db_data['name'] = $data['name'];
			$db_data['slug'] = $data['slug'];
			
			// Optional fields - only include if key exists in input (allows clearing via empty string/0)
			if (array_key_exists('parent_id', $data)) {
				$db_data['parent_id'] = (!empty($data['parent_id']) && (int)$data['parent_id'] > 0) ? (int)$data['parent_id'] : null;
			}
			if (array_key_exists('legacy_id', $data)) {
				$db_data['legacy_id'] = $data['legacy_id'] ?: null;
			}
			if (array_key_exists('legiscan_id', $data)) {
				$db_data['legiscan_id'] = $data['legiscan_id'] ?: null;
			}
			if (array_key_exists('date_start', $data)) {
				$db_data['date_start'] = $data['date_start'] ?: null;
			}
			if (array_key_exists('date_end', $data)) {
				$db_data['date_end'] = $data['date_end'] ?: null;
			}
			if (array_key_exists('is_current', $data)) {
				$db_data['is_current'] = $data['is_current'] ?? 0;
			}
			if (array_key_exists('status', $data)) {
				$db_data['status'] = in_array($data['status'], ['publish', 'draft'], true) ? $data['status'] : 'draft';
			}
			if (array_key_exists('meta', $data)) {
				$db_data['meta'] = !empty($data['meta']) ? json_encode($data['meta']) : null;
			}
				
			if ($session_id) {
				// Build format specifiers dynamically based on what's in $db_data
				$formats = [];
				foreach ($db_data as $key => $value) {
					if (in_array($key, ['parent_id', 'legiscan_id', 'is_current'])) {
						$formats[] = '%d';
					} elseif (in_array($key, ['legacy_id', 'date_start', 'date_end', 'meta', 'status'])) {
						$formats[] = '%s';
					} else {
						$formats[] = '%s';
					}
				}
				
				// Update existing
				$result = $wpdb->update(
					$wpdb->prefix . 'fi_sessions',
					$db_data,
					['id' => $session_id],
					$formats,
					['%d']
				);
				
				// Clear cache after successful update
				if ($result !== false) {
					self::clear_cache();
				}
				
				return $result !== false ? $session_id : false;
			} else {
				// Insert new - double-check for duplicates right before insert to prevent race conditions
				$final_check = self::check_duplicates($data, null);
				if ($final_check['is_duplicate']) {
					// Duplicate was created between our first check and now - return existing ID
					return $final_check['existing_id'];
				}
				
				// Ensure gov is string for database
				$db_data['gov'] = (string) $db_data['gov'];
				
			// Build format specifiers dynamically based on what's in $db_data
			$formats = [];
			foreach ($db_data as $key => $value) {
				if (in_array($key, ['parent_id', 'legiscan_id', 'is_current'])) {
					$formats[] = '%d';
				} elseif (in_array($key, ['legacy_id', 'date_start', 'date_end', 'meta', 'status'])) {
					$formats[] = '%s';
				} else {
					$formats[] = '%s';
				}
			}
			
			$result = $wpdb->insert(
				$wpdb->prefix . 'fi_sessions',
				$db_data,
				$formats
			);
				
			// Check if insert failed due to duplicate key error
			if ($result === false && !empty($wpdb->last_error)) {
				// If duplicate key error, try to find the existing record
				if (strpos($wpdb->last_error, 'Duplicate entry') !== false || strpos($wpdb->last_error, 'UNIQUE') !== false) {
					$existing = self::check_duplicates($data, null);
					if ($existing['is_duplicate']) {
						return $existing['existing_id'];
					}
				}
			}
			
			// Clear cache after successful insert
			if ($result !== false) {
				self::clear_cache();
			}
			
			return $result !== false ? $wpdb->insert_id : false;
			}
		}

		/**
		* Update session
		* 
		* @param int $session_id
		* @param array $data
		* @return bool
		*/
		public static function update(int $session_id, array $data): bool {
			return self::save($data, $session_id) !== false;
		}

		/**
		* Delete session
		* 
		* @param int $session_id
		* @return bool
		*/
		public static function delete(int $session_id): bool {
			global $wpdb;
			// Check if session exists
			$session = self::get_by_id($session_id);
			if (!$session) {
				return false;
			}
			// Legislator Sessions: delete all assignments for this session only (no orphans)
			$result = $wpdb->delete(
				$wpdb->prefix . 'fi_legislator_sessions',
				['session_id' => $session_id],
				['%d']
			);
			// Reports: Orphan reports (keep data, just clear session link)
			$result = $wpdb->update(
				$wpdb->prefix . 'fi_reports',
				['session_id' => 0],
				['session_id' => $session_id],
				['%d'],
				['%d']
			);
			// Votes: Orphan votes (keep data, just clear session link)
			$result = $wpdb->update(
				$wpdb->prefix . 'fi_votes',
				['session_id' => 0],
				['session_id' => $session_id],
				['%d'],
				['%d']
			);
			// Delete session
			$result = $wpdb->delete($wpdb->prefix . 'fi_sessions', ['id' => $session_id]);
			// Clear cache after successful delete
			if ($result !== false) {
				self::clear_cache();
			}
			return $result !== false;
		}

		/**
		* Check for duplicate sessions
		* 
		* @param array $data
		* @param int|null $exclude_id Exclude this ID from duplicate check
		* @return array ['is_duplicate' => bool, 'existing_id' => int|null]
		*/
		public static function check_duplicates(array $data, ?int $exclude_id = null): array {
			global $wpdb;
			
			$conditions = [];
			$values = [];
			
			// Normalize gov to string for comparison
			$gov = !empty($data['gov']) ? (string) $data['gov'] : null;
			
			// Check by slug and gov combination (primary check)
			if (!empty($data['slug']) && !empty($gov)) {
				$conditions[] = "(slug = %s AND gov = %s)";
				$values[] = $data['slug'];
				$values[] = $gov;
			}

			// Check by legacy_id (migration safety)
			if (!empty($data['legacy_id']) && !empty($gov)) {
				$conditions[] = "(legacy_id = %s AND gov = %s)";
				$values[] = (string) $data['legacy_id'];
				$values[] = $gov;
			}
			
			// Also check by legiscan_id column if available
			if (!empty($data['legiscan_id']) && !empty($gov)) {
				$conditions[] = "(legiscan_id = %d AND gov = %s)";
				$values[] = (int) $data['legiscan_id'];
				$values[] = $gov;
			}
			
			if (empty($conditions)) {
				return ['is_duplicate' => false, 'existing_id' => null];
			}
			
			// Summary: conditions are OR-ed; when excluding an ID we must wrap the OR group so the exclusion
			// applies to the entire group (SQL AND/OR precedence would otherwise exclude only the last condition).
			$where_clause = implode(' OR ', $conditions);
			$sql = "SELECT id FROM {$wpdb->prefix}fi_sessions WHERE ({$where_clause})";
			
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
		* Generate unique slug for session
		* 
		* @param string $name
		* @param string $gov
		* @return string
		*/
		public static function generate_slug(string $name, string $gov): string {
			$base_slug = sanitize_title($name);
			
			$slug = $base_slug;
			$counter = 1;
			
			while (self::slug_exists($slug, $gov)) {
				$slug = $base_slug . '-' . $counter;
				$counter++;
			}
			
			return $slug;
		}

		/**
		* Check if slug exists for a government
		* 
		* @param string $slug
		* @param string $gov
		* @param int|null $exclude_id
		* @return bool
		*/
		public static function slug_exists(string $slug, string $gov, ?int $exclude_id = null): bool {
			global $wpdb;
			
			$sql = "SELECT id FROM {$wpdb->prefix}fi_sessions WHERE slug = %s AND gov = %s";
			$values = [$slug, $gov];
			
			if ($exclude_id) {
				$sql .= " AND id != %d";
				$values[] = $exclude_id;
			}
			
			$sql .= " LIMIT 1";
			
			return !empty($wpdb->get_var($wpdb->prepare($sql, $values)));
		}

		/**
		* Set current session for a government
		* 
		* @param string $gov
		* @param int $session_id
		* @return bool
		*/
		public static function set_current(string $gov, int $session_id): bool {
			global $wpdb;
			
			// First, unset all current sessions for this gov
			$wpdb->update(
				$wpdb->prefix . 'fi_sessions',
				['is_current' => 0],
				['gov' => $gov],
				['%d'],
				['%s']
			);
			
			// Set the specified session as current
			$result = $wpdb->update(
				$wpdb->prefix . 'fi_sessions',
				['is_current' => 1],
				['id' => $session_id, 'gov' => $gov],
				['%d'],
				['%d', '%s']
			);
			
			return $result !== false;
		}

		/**
		* Get session statistics
		* 
		* @param string|null $gov
		* @return array
		*/
		public static function get_stats(?string $gov = null): array {
			global $wpdb;
			
			$where_clause = $gov ? "WHERE gov = %s" : "";
			$values = $gov ? [$gov] : [];
			
			$sql = "
				SELECT 
					COUNT(*) as total,
					COUNT(CASE WHEN is_current = 1 THEN 1 END) as current,
					COUNT(CASE WHEN date_start IS NOT NULL THEN 1 END) as with_dates
				FROM {$wpdb->prefix}fi_sessions
				{$where_clause}
			";
			
			if (!empty($values)) {
				$sql = $wpdb->prepare($sql, $values);
			}
			
			return (array) $wpdb->get_row($sql);
		}

		/**
		* Get statistics for a specific session
		* Returns counts of legislators, votes, and reports for the session
		* 
		* @param int $session_id
		* @return array ['legislators' => int, 'votes' => int, 'reports' => int]
		*/
		public static function get_session_stats(int $session_id): array {
			global $wpdb;

			$legislators = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(DISTINCT legislator_id) FROM {$wpdb->prefix}fi_legislator_sessions WHERE session_id = %d",
				$session_id
			));

			$votes = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}fi_votes WHERE session_id = %d AND status = 'publish'",
				$session_id
			));

			$reports = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}fi_reports WHERE session_id = %d",
				$session_id
			));

			return [
				'legislators' => $legislators,
				'votes' => $votes,
				'reports' => $reports,
			];
		}

		/**
		* Validate session data
		* 
		* @param array $data
		* @return array ['valid' => bool, 'errors' => array]
		*/
		public static function validate_session_data(array $data): array {
			$errors = [];
			
			// Required fields
			if (empty($data['name'])) {
				$errors[] = 'Session name is required';
			}
			
			if (empty($data['gov'])) {
				$errors[] = 'Government code is required';
			}
			
			// Validate gov format
			if (!empty($data['gov']) && !preg_match('/^[A-Z]{2}$/', $data['gov'])) {
				$errors[] = 'Government code must be 2 uppercase letters';
			}
			
			// Validate dates
			if (!empty($data['date_start']) && !self::validate_date($data['date_start'])) {
				$errors[] = 'Invalid start date format';
			}
			
			if (!empty($data['date_end']) && !self::validate_date($data['date_end'])) {
				$errors[] = 'Invalid end date format';
			}
			
			// Validate slug
			if (!empty($data['slug']) && !preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
				$errors[] = 'Slug must contain only lowercase letters, numbers, and hyphens';
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
		* Get child sessions of a parent session
		* 
		* @param int $parent_id
		* @return array
		*/
		public static function get_children(int $parent_id): array {
			global $wpdb;
			
			$results = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fi_sessions 
				WHERE parent_id = %d 
				ORDER BY date_start ASC, name ASC",
				$parent_id
			));
			
			// Add " Congress" suffix for US sessions
			foreach ($results as $session) {
				if (!empty($session->name) && strtoupper($session->gov ?? '') === 'US' && strpos($session->name, 'Congress') === false) {
					$session->name .= ' Congress';
				}
			}
			
			return $results;
		}

		/**
		* Get parent session of a child session
		* 
		* @param int $child_id
		* @return ?object
		*/
		public static function get_parent(int $child_id): ?object {
			global $wpdb;
			
			$session = $wpdb->get_row($wpdb->prepare(
				"SELECT p.* FROM {$wpdb->prefix}fi_sessions p
				INNER JOIN {$wpdb->prefix}fi_sessions c ON c.parent_id = p.id
				WHERE c.id = %d",
				$child_id
			));
			
			// Add " Congress" suffix for US sessions
			if ($session && !empty($session->name) && strtoupper($session->gov ?? '') === 'US' && strpos($session->name, 'Congress') === false) {
				$session->name .= ' Congress';
			}
			
			return $session;
		}

		/**
		* Get all sessions in a hierarchy (parent + children)
		* 
		* @param int $session_id
		* @return array
		*/
		public static function get_hierarchy(int $session_id): array {
			global $wpdb;
			
			// Get the session
			$session = self::get_by_id($session_id);
			if (!$session) {
				return [];
			}
			
			$hierarchy = [$session];
		
			// If it's a parent (parent_id IS NULL), get children
			if ($session->parent_id === null || (int)$session->parent_id === 0) {
				$children = self::get_children($session_id);
				$hierarchy = array_merge($hierarchy, $children);
			} else {
				// If it's a child, get parent and siblings
				$parent = self::get_parent($session_id);
				if ($parent) {
					$hierarchy = [$parent];
					$siblings = self::get_children($parent->id);
					$hierarchy = array_merge($hierarchy, $siblings);
				}
			}
			//The hierarchy must be sorted by date_end,id DESC
//fi_log('HIERARCHY='.json_encode($hierarchy),__FILE__,__LINE__);			

			return $hierarchy;
		}

		/**
		* Get all session IDs in a hierarchy (for vote rollup queries)
		* 
		* @param int $session_id
		* @return array
		*/
		public static function get_hierarchy_ids(int $session_id): array {
			$hierarchy = self::get_hierarchy($session_id);
			return array_map(function($session) {
				return $session->id;
			}, $hierarchy);
		}

		/**
		* Check if session is a parent (has children)
		* 
		* @param int $session_id
		* @return bool
		*/
		public static function is_parent(int $session_id): bool {
			global $wpdb;
			
			$count = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}fi_sessions WHERE parent_id = %d",
				$session_id
			));
			
			return $count > 0;
		}

		/**
		* Check if session is a child (has parent)
		* 
		* @param int $session_id
		* @return bool
		*/
		public static function is_child(int $session_id): bool {
			global $wpdb;
			
			$parent_id = $wpdb->get_var($wpdb->prepare(
				"SELECT parent_id FROM {$wpdb->prefix}fi_sessions WHERE id = %d",
				$session_id
			));
			
			return (int)$parent_id > 0;
		}

		public static function log(string $message, string $file='', int $line=0, string $level = 'info'): void {
			//fi_log_area('sessions', $message, $file, $line, $level);
		}
	}
}


namespace {

	/* Get sessions with optional filtering */
	function fi_sessions_get(array $args = []): array|int {
		return \FI\Core\Sessions::get($args);
	}

	/* Get a single session by ID */
	function fi_session_get(int $session_id): ?object {
		return \FI\Core\Sessions::get_by_id($session_id);
	}

	//SESSIONSLUG: Mark this function as deprecated for public use - keep only for admin/import reference, add deprecation notice
	/* Get session by slug */
	function fi_session_get_by_slug(string $slug, ?string $gov = null): ?object {
		return \FI\Core\Sessions::get_by_slug($slug, $gov);
	}

	/* Get session by Legiscan ID */
	function fi_session_get_by_legiscan_id(int $legiscan_id, ?string $gov = null): ?object {
		return \FI\Core\Sessions::get_by_legiscan_id($legiscan_id, $gov);
	}

	/* Save/Update session */
	function fi_session_save(array $data, ?int $session_id = null): int|false {
		return \FI\Core\Sessions::save($data, $session_id);
	}

	/* Update session */
	function fi_session_update(int $session_id, array $data): bool {
		return \FI\Core\Sessions::update($session_id, $data);
	}

	/* Delete session */
	function fi_session_delete(int $session_id): bool {
fi_log('fi_session_delete: ' . $session_id, __FILE__, __LINE__);
		return \FI\Core\Sessions::delete($session_id);
	}

	/* Get sessions by government */
	function fi_sessions_get_by_gov(string $gov, array $filters = []): array {
		return \FI\Core\Sessions::get_by_gov($gov, $filters);
	}

/* Get current session for a government */
function fi_session_get_current(string $gov): ?object {
	return \FI\Core\Sessions::get_current($gov);
}

/* Get current session ID for a government */
function fi_session_get_current_id(string $gov): ?int {
	return \FI\Core\Sessions::get_current_id($gov);
}

	/* Set current session for a government */
	function fi_session_set_current(string $gov, int $session_id): bool {
		return \FI\Core\Sessions::set_current($gov, $session_id);
	}

	/* Get session statistics */
	function fi_sessions_stats(?string $gov = null): array {
		return \FI\Core\Sessions::get_stats($gov);
	}

	/* Get statistics for a specific session */
	function fi_session_get_stats(int $session_id): array {
		return \FI\Core\Sessions::get_session_stats($session_id);
	}

	/* Get child sessions of a parent session */
	function fi_sessions_get_children(int $parent_id): array {
		return \FI\Core\Sessions::get_children($parent_id);
	}

	/* Get parent session of a child session */
	function fi_session_get_parent(int $child_id): ?object {
		return \FI\Core\Sessions::get_parent($child_id);
	}

	/* Get all sessions in a hierarchy (parent + children) */
	function fi_sessions_get_hierarchy(int $session_id): array {
		return \FI\Core\Sessions::get_hierarchy($session_id);
	}

	/* Get all session IDs in a hierarchy (for vote rollup queries) */
	function fi_sessions_get_hierarchy_ids(int $session_id): array {
		return \FI\Core\Sessions::get_hierarchy_ids($session_id);
	}

	/* Check if session is a parent (has children) */
	function fi_session_is_parent(int $session_id): bool {
		return \FI\Core\Sessions::is_parent($session_id);
	}

	/* Check if session is a child (has parent) */
	function fi_session_is_child(int $session_id): bool {
		return \FI\Core\Sessions::is_child($session_id);
	}

	//SESSIONSLUG: Remove this function entirely - no longer needed since we use IDs directly
	/* Get session ID from slug */
	function fi_session_get_id_from_slug(string $slug, ?string $gov = null): ?int {
		$session = \FI\Core\Sessions::get_by_slug($slug, $gov);
		return $session ? (int) $session->id : null;
	}

	/* Get session meta value by key */
	function fi_session_get_meta($session, string $key, $default = null) {
		return \FI\Core\Sessions::get_meta($session, $key, $default);
	}

	/* Get all session meta */
	function fi_session_get_all_meta($session): array {
		return \FI\Core\Sessions::get_all_meta($session);
	}

	/* Update session meta key(s) without affecting other keys */
	function fi_session_update_meta(int $session_id, array $meta_updates): bool {
		return \FI\Core\Sessions::update_meta($session_id, 'fi_sessions', $meta_updates);
	}

	/* Delete session meta key(s) */
	function fi_session_delete_meta(int $session_id, $keys): bool {
		return \FI\Core\Sessions::delete_meta($session_id, 'fi_sessions', $keys);
	}

	/* Set entire session meta (replaces all) */
	function fi_session_set_all_meta(int $session_id, array $meta): bool {
		return \FI\Core\Sessions::set_all_meta($session_id, 'fi_sessions', $meta);
	}
}