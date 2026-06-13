<?php
namespace FI\Core{

	if (!defined('ABSPATH')) exit;

	// Load Meta trait
	require_once __DIR__ . '/traits/meta.php';

	/**
	* Reports Table I/O Operations
	* All database operations for the fi_reports table.
	*/
	final class Reports {
		
		// Use unified meta handling trait
		use \FI\Core\Traits\Meta;

		/**
		 * Request-level cache for get() results
		 * Key: md5 hash of serialized args
		 */
		private static $cache_get = [];


		/**
		* Get reports with optional filtering
		* Default: published-only.
		* Admin UIs must explicitly pass ['status' => null] to see all statuses.
		*/
		public static function get(array $args = []): array|int {
			global $wpdb;

			// If caller does NOT provide a status key, default to published only.
			// Admin UIs must explicitly pass ['status' => null] to see all statuses.
			$status_key_provided = array_key_exists('status', $args);
			
			$defaults = [
				'id' => null,
				'session_id' => null,
				'gov' => null,
				'search' => null,
				'status' => null,
				'orderby' => 'date_publish',
				'order' => 'DESC',
				'per_page' => -1,
				'page' => 1,
				'count' => false
			];
			
			$args = wp_parse_args($args, $defaults);

			// Check request-level cache first (fastest)
			$cache_key = md5(serialize($args));
			if (isset(self::$cache_get[$cache_key])) {
				return self::$cache_get[$cache_key];
			}

			// Cache query results (file system cache)
			// IMPORTANT: orderby/order MUST be part of the cache key, otherwise admin sorting appears broken.
			$cacheKey = fi_cache_key('reports/get', $args);
			$results = fi_cache($cacheKey); //1 day
			if($results){
				// Store in request-level cache
				self::$cache_get[$cache_key] = $results;
				return $results;
			}

			if (!$status_key_provided) {
				$args['status'] = 'publish';
			}
			
			// Build WHERE clause
			$where_conditions = [];
			$where_values = [];
			
			if ($args['id']) {
				$where_conditions[] = "r.id = %d";
				$where_values[] = $args['id'];
			}
			
			if ($args['session_id']) {
				$where_conditions[] = "r.session_id = %d";
				$where_values[] = $args['session_id'];
			}
			
			if ($args['gov']) {
				$where_conditions[] = "r.gov = %s";
				$where_values[] = $args['gov'];
			}
			
			// Status filtering
			if ($args['status'] !== null) {
				$where_conditions[] = "r.status = %s";
				$where_values[] = $args['status'];
				
				// For published reports, also filter by date_publish (show if NULL or <= now)
				if ($args['status'] === 'publish') {
					$where_conditions[] = "(r.date_publish IS NULL OR r.date_publish <= %s)";
					$where_values[] = current_time('mysql');
				}
			}
			
			if ($args['search']) {
				$where_conditions[] = "r.title LIKE %s";
				$search_term = '%' . $wpdb->esc_like($args['search']) . '%';
				$where_values[] = $search_term;
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
			// Build ORDER BY clause
			$orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
			if (!$orderby) {
				$orderby = 'r.date_publish DESC, r.id DESC';
			}
			
			// Stabilize ordering for "latest" queries (tie-break on id)
			// Keeps ordering deterministic when multiple reports share the same date_publish.
			if ($args['orderby'] === 'date_publish') {
				$order_dir = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
				$orderby = 'r.date_publish ' . $order_dir . ', r.id ' . $order_dir;
			}
			
			// Build LIMIT clause
			$limit_clause = '';
			if ($args['per_page'] > 0) {
				$offset = ($args['page'] - 1) * $args['per_page'];
				$limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", $args['per_page'], $offset);
			}
			
			if ($args['count']) {
				$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}fi_reports r {$where_clause}";
				
				if (!empty($where_values)) {
					$sql = $wpdb->prepare($sql, $where_values);
				}
				
				return (int) $wpdb->get_var($sql);
			}
			
			$sql = "
				SELECT r.*, s.name as session_name 
				FROM {$wpdb->prefix}fi_reports r
				LEFT JOIN {$wpdb->prefix}fi_sessions s ON r.session_id = s.id
				{$where_clause}
				ORDER BY {$orderby}
				{$limit_clause}
			";
			
			if (!empty($where_values)) {
				$sql = $wpdb->prepare($sql, $where_values);
			}
//fi_log('Reports SQL: '.str_replace('"\n"', ' ', $sql), __FILE__, __LINE__);
			$results = $wpdb->get_results($sql);
			fi_cache($cacheKey,$results);
			// Store in request-level cache
			self::$cache_get[$cache_key] = $results;
			return $results;

		}

		/**
		* Get a single report by ID
		* Respects status filtering (admins see all, public users see only published)
		*/
		public static function get_by_id(int $report_id): ?object {
			$results = self::get(['id' => $report_id, 'per_page' => 1]);
			return $results[0] ?? null;
		}

		/**
		* Get report by slug
		* Public users only see published reports, admins see all statuses
		*/
		/*DEPRECATED
		public static function get_by_slug(string $slug, ?string $gov = null): ?object {
			$args = ['slug' => $slug, 'per_page' => 1];
			if ($gov !== null && $gov !== '') {
				$args['gov'] = strtoupper($gov);
			}
			$results = self::get($args);
			return $results[0] ?? null;
		}
		*/

		/**
		* Get reports by session
		*/
		public static function get_by_session(int $session_id, array $filters = []): array {
			$args = array_merge($filters, ['session_id' => $session_id]);
			return self::get($args);
		}

		/**
		* Get the most recent published report for a government/jurisdiction
		* Efficient single-query method that returns the latest report by date_publish
		* 
		* @param string $gov Government code (e.g., 'US', 'TX')
		* @return object|null Report object with additional computed fields (url, format, etc.) or null if none found
		*/
		public static function get_latest(string $gov): ?object {
			$gov = strtoupper($gov);
			$is_manager = defined('FI_CAP_MANAGE') ? current_user_can(FI_CAP_MANAGE) : current_user_can('manage_options');
			
			// Use the class get() query builder for consistency.
			// - Managers see latest regardless of status (status => null)
			// - Public sees latest published (status => publish) with date_publish gating handled by get()
			$results = self::get([
				'gov' => $gov,
				'status' => $is_manager ? null : 'publish',
				'orderby' => 'date_publish',
				'order' => 'DESC',
				'per_page' => 1,
			]);
			
			$report = is_array($results) ? ($results[0] ?? null) : null;
			
			if (!$report) {
				return null;
			}
			
			// Format from DB column (no payload read)
			$format = !empty($report->format) ? $report->format : 'scorecard';
			$format_arg = $format === 'freedomindex' ? 'fia' : 'scb';
			
		// Build URL using existing helper if available
		$url = '';
		if (function_exists('fi_url_report')) {
			$url = fi_url_report($report->id, strtolower($gov));
		} else {
			// Fallback URL construction
			$url = home_url('/' . strtolower($gov) . '/report/' . $report->id . '/');
		}
			
			// Add computed fields to the report object
			$report->format = $format;
			$report->format_arg = $format_arg;
			$report->url = $url;
			
			return $report;
		}

		/**
		* Save/Update report with duplicate checking
		*/
		public static function save(array $data, ?int $report_id = null): int|false {
			global $wpdb;
			
			// Validate required fields
			if (empty($data['title']) || empty($data['session_id']) || empty($data['gov'])) {
				return false;
			}
			$data['gov'] = strtoupper((string) $data['gov']);
			
			// Check for duplicates
			$duplicate_check = self::check_duplicates($data, $report_id);
			if ($duplicate_check['is_duplicate']) {
				return $duplicate_check['existing_id'];
			}
			
			//RMFORMAT
			// Normalize and validate payload_json if provided
			$payload_json = '{}';
			if (!empty($data['payload_json'])) {
				if (is_string($data['payload_json'])) {
					// Validate it's valid JSON
					$decoded = json_decode($data['payload_json'], true);
					if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
						// Normalize before storing
						$normalized = ReportsPayload::normalize($decoded);
						// Validate normalized payload
						$validation = ReportsPayload::validate($normalized);
						if ($validation['valid']) {
							$payload_json = json_encode($normalized);
						} else {
							// Log validation errors but still save (graceful degradation)
							//fi_log('FI Reports: Payload validation errors: ' . implode(', ', $validation['errors']), __FILE__, __LINE__);
							$payload_json = json_encode($normalized);
						}
					} else {
						$payload_json = $data['payload_json']; // Keep as-is if invalid JSON
					}
				} elseif (is_array($data['payload_json'])) {
					// Normalize array before encoding
					$normalized = ReportsPayload::normalize($data['payload_json']);
					// Validate normalized payload
					$validation = ReportsPayload::validate($normalized);
					if ($validation['valid']) {
						$payload_json = json_encode($normalized);
					} else {
						// Log validation errors but still save
						if (defined('WP_DEBUG') && WP_DEBUG) {
							error_log('FI Reports: Payload validation errors: ' . implode(', ', $validation['errors']));
						}
						$payload_json = json_encode($normalized);
					}
				}
			}
			
			// Prepare data for database (format from column, not payload)
//REMOVE legacy_id references
			$format = isset($data['format']) && in_array($data['format'], ['scorecard', 'freedomindex'], true)
				? $data['format'] : 'scorecard';
			$db_data = [
				'legacy_id' => !empty($data['legacy_id']) ? (string) $data['legacy_id'] : null,
				'session_id' => $data['session_id'],
				'gov' => $data['gov'] ?? null,
				'title' => sanitize_text_field($data['title']),
				'title_menu' => isset($data['title_menu']) ? sanitize_text_field($data['title_menu']) : null,
				'slug' => !empty($data['slug']) ? sanitize_title($data['slug']) : null,
				'payload_json' => $payload_json,
				'format' => $format,
				'status' => $data['status'] ?? 'draft',
				'date_publish' => !empty($data['date_publish']) ? $data['date_publish'] : null,
				'meta' => !empty($data['meta']) ? (is_string($data['meta']) ? $data['meta'] : json_encode($data['meta'])) : null,
				'owner_user_id' => !empty($data['owner_user_id']) ? (int)$data['owner_user_id'] : null
			];
			
			// Build format array dynamically based on what fields are being set
			$formats = [];
			foreach ($db_data as $key => $value) {
				if ($value !== null) {
					if (in_array($key, ['session_id', 'owner_user_id'])) {
						$formats[] = '%d';
					} elseif ($key === 'legacy_id') {
						$formats[] = '%s';
					} elseif (in_array($key, ['date_publish'])) {
						$formats[] = '%s'; // DATETIME
					} else {
						$formats[] = '%s';
					}
				}
			}
			
			// Remove null values to use database defaults
			$db_data = array_filter($db_data, function($value) {
				return $value !== null;
			});
			
			if ($report_id) {
				// Update existing
				$result = $wpdb->update(
					$wpdb->prefix . 'fi_reports',
					$db_data,
					['id' => $report_id],
					$formats,
					['%d']
				);
				
				return $result !== false ? $report_id : false;
			} else {
				// Insert new
				$result = $wpdb->insert(
					$wpdb->prefix . 'fi_reports',
					$db_data,
					$formats
				);
				
				return $result !== false ? $wpdb->insert_id : false;
			}
		}

		/**
		* Update report
		*/
		public static function update(int $report_id, array $data): bool {
			return self::save($data, $report_id) !== false;
		}

		/**
		* Delete report
		*/
		public static function delete(int $report_id): bool {
			global $wpdb;
			
			// Check if report exists
			$report = self::get_by_id($report_id);
			if (!$report) {
				return false;
			}
			
			// Delete report
			$result = $wpdb->delete($wpdb->prefix . 'fi_reports', ['id' => $report_id]);
			
			return $result !== false;
		}

		/**
		* Check for duplicate reports
		*/
		public static function check_duplicates(array $data, ?int $exclude_id = null): array {
			global $wpdb;
			
			$conditions = [];
			$values = [];
			
			// Check by slug
			if (!empty($data['slug']) && !empty($data['gov'])) {
				$conditions[] = "(slug = %s AND gov = %s)";
				$values[] = $data['slug'];
				$values[] = strtoupper((string) $data['gov']);
			}

			// Check by legacy_id (migration safety)
			if (!empty($data['legacy_id']) && !empty($data['gov'])) {
				$conditions[] = "(legacy_id = %s AND gov = %s)";
				$values[] = (string) $data['legacy_id'];
				$values[] = strtoupper((string) $data['gov']);
			}
			
			if (empty($conditions)) {
				return ['is_duplicate' => false, 'existing_id' => null];
			}
			
			$where_clause = implode(' OR ', $conditions);
			$sql = "SELECT id FROM {$wpdb->prefix}fi_reports WHERE {$where_clause}";
			
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
		* Generate unique slug for report
		*/
		public static function generate_slug(string $title, string $gov): string {
			$base_slug = sanitize_title($title);
			
			$slug = $base_slug;
			$counter = 1;
			
			while (self::slug_exists($slug, $gov)) {
				$slug = $base_slug . '-' . $counter;
				$counter++;
			}
			
			return $slug;
		}

		/**
		* Check if slug exists
		*/
		public static function slug_exists(string $slug, string $gov, ?int $exclude_id = null): bool {
			global $wpdb;
			
			$sql = "SELECT id FROM {$wpdb->prefix}fi_reports WHERE slug = %s AND gov = %s";
			$values = [$slug, strtoupper($gov)];
			
			if ($exclude_id) {
				$sql .= " AND id != %d";
				$values[] = $exclude_id;
			}
			
			$sql .= " LIMIT 1";
			
			return !empty($wpdb->get_var($wpdb->prepare($sql, $values)));
		}

		/**
		* Get report statistics
		* 
		* @param string|null $gov Government code
		* @param int|null $session_id Session ID
		* @param bool $by_status Include status breakdown
		* @return array Statistics
		*/
		public static function get_stats(?string $gov = null, ?int $session_id = null, bool $by_status = false): array {
			global $wpdb;
			
			$where_conditions = [];
			$values = [];
			
			if ($gov) {
				$where_conditions[] = "gov = %s";
				$values[] = $gov;
			}
			
			if ($session_id) {
				$where_conditions[] = "session_id = %d";
				$values[] = $session_id;
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
			if ($by_status) {
				$sql = "
					SELECT 
						COUNT(*) as total,
						COUNT(CASE WHEN status = 'publish' THEN 1 END) as publish,
						COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
						COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
						COUNT(CASE WHEN status = 'trash' THEN 1 END) as trash
					FROM {$wpdb->prefix}fi_reports
					{$where_clause}
				";
			} else {
				$sql = "SELECT COUNT(*) as total FROM {$wpdb->prefix}fi_reports {$where_clause}";
			}
			
			if (!empty($values)) {
				$sql = $wpdb->prepare($sql, $values);
			}
			
			return (array) $wpdb->get_row($sql);
		}

		/**
		* Decode selected vote IDs from report
		* Reads from payload_json votes_h and votes_s arrays (new format) or selected_votes (legacy)
		* Uses ReportsPayload::normalize() for consistent data handling
		* 
		* @param object $report Report object
		* @return array Array of vote IDs
		*/
		public static function decode_selected_votes(object $report): array {
			// Use ReportsPayload to normalize payload_json
			$payload = ReportsPayload::normalize($report->payload_json ?? null);
			
			// Get votes from normalized payload
			$votes_h = $payload['votes_h'] ?? [];
			$votes_s = $payload['votes_s'] ?? [];
			$combined = array_merge($votes_h, $votes_s);
			
			if (!empty($combined)) {
				return array_values(array_unique($combined));
			}
			
			// Fallback to legacy selected_votes field
			$raw = $report->selected_votes ?? '[]';
			if (is_string($raw)) {
				$decoded = json_decode($raw, true);
			} elseif (is_array($raw)) {
				$decoded = $raw;
			} else {
				$decoded = [];
			}

			if (!is_array($decoded)) {
				return [];
			}

			return array_values(array_unique(array_map('intval', $decoded)));
		}

		/**
		* Count selected votes for a report
		* 
		* @param object $report Report object
		* @return int Count of selected votes
		*/
		public static function count_selected_votes(object $report): int {
			return count(self::decode_selected_votes($report));
		}

		/**
		* Validate report data
		*/
		public static function validate_data(array $data): array {
			$errors = [];
			
			// Required fields
			if (empty($data['title'])) {
				$errors[] = 'Title is required';
			}
			
			if (empty($data['session_id'])) {
				$errors[] = 'Session ID is required';
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


		/** Get the latest Freedom Index report (by format column). */
		public static function get_latest_freedom_index(): ?object {
			global $wpdb;
			$sql = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fi_reports WHERE status = 'publish' AND format = %s ORDER BY id DESC LIMIT 1",
				'freedomindex'
			);
			return $wpdb->get_row($sql);
		}

		/** Get latest scorecard by gov and session (format column; date_publish gated). */
		public static function get_latest_scorecard(string $gov, int $session_id): ?object {
			global $wpdb;
			$gov = strtoupper($gov);
			$now = current_time('mysql');
			$sql = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fi_reports 
				WHERE status = 'publish' AND format = %s AND gov = %s AND session_id = %d 
				AND (date_publish IS NULL OR date_publish <= %s)
				ORDER BY date_publish DESC, id DESC LIMIT 1",
				'scorecard',
				$gov,
				$session_id,
				$now
			);
//fi_log(preg_replace('/\s+/', ' ', trim($sql)), __FILE__, __LINE__);
			return $wpdb->get_row($sql);
		}

	}
}

//namespace for global functions
namespace {

	/* Get reports with optional filtering */
	function fi_reports_get(array $args = []): array|int {
		return \FI\Core\Reports::get($args);
	}

	/* Get a single report by ID */
	function fi_report_get(int $report_id): ?object {
		return \FI\Core\Reports::get_by_id($report_id);
	}

	/* Get report by slug */
	/*DEPRECATED
	function fi_report_get_by_slug(string $slug, ?string $gov = null): ?object {
		return \FI\Core\Reports::get_by_slug($slug, $gov);
	}
	*/

	/* Decode and normalize report payload_json */
	function fi_report_decode_payload($payload_json): array {
		return \FI\Core\ReportsPayload::normalize($payload_json);
	}


	/* Save/Update report */
	function fi_report_save(array $data, ?int $report_id = null): int|false {
		return \FI\Core\Reports::save($data, $report_id);
	}

	/* Update report */
	function fi_report_update(int $report_id, array $data): bool {
		return \FI\Core\Reports::update($report_id, $data);
	}

	/* Delete report */
	function fi_report_delete(int $report_id): bool {
		return \FI\Core\Reports::delete($report_id);
	}

	/* Get reports by session */
	function fi_reports_get_by_session(int $session_id, array $filters = []): array {
		return \FI\Core\Reports::get_by_session($session_id, $filters);
	}

	/* Get the most recent published report for a government/jurisdiction */
	function fi_report_latest(string $gov): ?object {
		return \FI\Core\Reports::get_latest($gov);
	}

	/* Get report statistics */
	function fi_reports_stats(?string $gov = null, ?int $session_id = null, bool $by_status = false): array {
		return \FI\Core\Reports::get_stats($gov, $session_id, $by_status);
	}

	/* Decode selected vote IDs from report */
	function fi_report_decode_selected_votes(object $report): array {
		return \FI\Core\Reports::decode_selected_votes($report);
	}

	/* Count selected votes for a report */
	function fi_report_count_selected_votes(object $report): int {
		return \FI\Core\Reports::count_selected_votes($report);
	}

	/* Get report meta value by key */
	function fi_report_get_meta($record, string $key, $default = null) {
		return \FI\Core\Reports::get_meta($record, $key, $default);
	}

	/* Get all report meta */
	function fi_report_get_all_meta($record): array {
		return \FI\Core\Reports::get_all_meta($record);
	}

	/* Update report meta key(s) without affecting other keys */
	function fi_report_update_meta(int $record_id, array $meta_updates): bool {
		return \FI\Core\Reports::update_meta($record_id, 'fi_reports', $meta_updates);
	}

	/* Delete report meta key(s) */
	function fi_report_delete_meta(int $record_id, $keys): bool {
		return \FI\Core\Reports::delete_meta($record_id, 'fi_reports', $keys);
	}

	/* Set entire report meta (replaces all) */
	function fi_report_set_all_meta(int $record_id, array $meta): bool {
		return \FI\Core\Reports::set_all_meta($record_id, 'fi_reports', $meta);
	}

	/* Get latest scorecard by gov and session */
	function fi_report_latest_scorecard(string $gov, int $session_id): ? object {
		return \FI\Core\Reports::get_latest_scorecard($gov, $session_id);
	}

	/* Get the latest Freedom Index report */
	function fi_report_latest_freedom_index(): ?object {
		return \FI\Core\Reports::get_latest_freedom_index();
	}

	/* Get reports by vote ID: id,chamber. Uses JSON_CONTAINS on payload_json votes_h/votes_s. */
	function fi_report_get_by_vote_id(int $vote_id, string $chamber): array {
		global $wpdb;
		if ($vote_id <= 0) {
			return [];
		}
		$chamber = strtoupper(trim($chamber));
		$list_key = ($chamber === 'H' || $chamber === 'S') ? 'votes_' . strtolower($chamber) : null;
		// MariaDB does not support CAST(... AS JSON); pass vote_id as string so JSON_CONTAINS treats it as JSON number.
		$vote_json = (string) $vote_id;
		if ($list_key === 'votes_h') {
			$sql = $wpdb->prepare(
				"SELECT id, title, payload_json FROM {$wpdb->prefix}fi_reports
				 WHERE JSON_CONTAINS(payload_json, %s, '$.votes_h') = 1 ORDER BY id DESC",
				$vote_json
			);
		} elseif ($list_key === 'votes_s') {
			$sql = $wpdb->prepare(
				"SELECT id, title, payload_json FROM {$wpdb->prefix}fi_reports
				 WHERE JSON_CONTAINS(payload_json, %s, '$.votes_s') = 1 ORDER BY id DESC",
				$vote_json
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT id, title, payload_json FROM {$wpdb->prefix}fi_reports
				 WHERE (JSON_CONTAINS(payload_json, %s, '$.votes_h') = 1 OR JSON_CONTAINS(payload_json, %s, '$.votes_s') = 1) ORDER BY id DESC",
				$vote_json,
				$vote_json
			);
		}
		$reports = $wpdb->get_results($sql);
		if (empty($reports)) {
			return [];
		}
		foreach ($reports as $report) {
			$report->payload_json = fi_report_payload_normalize($report->payload_json);
		}
		return $reports;
	}

	/** Order: Scorecards first, then Freedom Index; unknown format last. Uses fi_reports.format column. */
	function fi_reports_sort_by_format($gov, array $reports): array {
		if ($gov !== 'US') {
			return $reports;
		}
		$sc = [];
		$fi = [];
		$other = [];
		foreach ($reports as $report) {
			$format = isset($report->format) ? strtolower(trim((string) $report->format)) : '';
			if ($format === 'scorecard') {
				$sc[] = $report;
			} elseif ($format === 'freedomindex') {
				$fi[] = $report;
			} else {
				$other[] = $report;
			}
		}
		return array_merge($sc, $fi, $other);
	}

	/* Report Title Reformatting
	Many state report titles are like 'MA Scorecard 2025' but Peter wants '2025 MA Legislative Scorecard'
	*/
	function fi_report_title_reformat(string $gov,string $title): string {
		if($gov === 'US') {
			if(strpos($report->title,'Congressional') !== false) {
				//do nothing
			}else{
				$title = str_replace(' Scorecard',' Congressional Scorecard',$title);
			}
		}else{
			if(strpos($title,'Legislative') !== false) {
				//do nothing
			}else{
				$title = str_replace(' Scorecard',' Legislative Scorecard',$title);
			}
		}
		//Move the year from end to beginning. Check last 4 characters and check is a number.
		if(is_numeric(substr($title, -4))) {
			$year = substr($title, -4);
			$title = trim(str_replace($year,'',$title));
			$title = $year . ' ' . $title;
		}
		return $title;
	}


}