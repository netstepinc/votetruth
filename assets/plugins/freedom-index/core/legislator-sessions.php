<?php
namespace FI\Core{

	if (!defined('ABSPATH')) exit;

	// Load Meta trait
	require_once __DIR__ . '/traits/meta.php';

	/**
	* Legislator Sessions Table I/O Operations
	* All database operations for the fi_legislator_sessions table.
	* Handles legislator-session relationships and embedded scoring.
	*/
	final class LegislatorSessions {
		
		// Use unified meta handling trait
		use \FI\Core\Traits\Meta;

		/**
		 * Request-level cache for get() results
		 * Key: md5 hash of serialized args
		 */
		private static $cache_get = [];


		/**
		* Get legislator sessions with optional filtering
		* 
		* @param array $args {
		*     Optional. Arguments to filter legislator sessions.
		*     @type int          $legislator_id  Filter by legislator ID
		*     @type int          $session_id     Filter by session ID
		*     @type string       $gov            Filter by government code
		*     @type string       $chamber      Filter by chamber (H or S)
		*     @type string       $party          Filter by party
		*     @type string       $orderby        Order by field (score, date_created, etc.)
		*     @type string       $order          Order direction (ASC/DESC)
		*     @type int          $per_page       Number per page
		*     @type int          $page           Page number
		*     @type bool         $count          Return count only
		* }
		* @return array|int Array of legislator session objects or count if $count is true
		*/
		public static function get(array $args = []): array|int {
			global $wpdb;
			
			$defaults = [
				'id' => null,
				'legislator_id' => null,
				'session_id' => null,
				'session_ids' => null,
				'gov' => null,
				'state' => null,
				'chamber' => null,
				'district' => null,
				'party' => null,
				'orderby' => 'score',
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
			$cacheKey = fi_cache_key('legislators/sessions', $args);
			$results = fi_cache($cacheKey); //1 day
			if($results){
				// Store in request-level cache
				self::$cache_get[$cache_key] = $results;
				return $results;
			}

			// Build WHERE clause
			$where_conditions = [];
			$where_values = [];
			
			if (!empty($args['id'])) {
				$where_conditions[] = 'ls.id = %d';
				$where_values[] = $args['id'];
			}
			
			if (!empty($args['legislator_id'])) {
				$where_conditions[] = 'ls.legislator_id = %d';
				$where_values[] = $args['legislator_id'];
			}
			
			if (!empty($args['session_id'])) {
				// Get all session IDs in the hierarchy (parent + children)
				$session_ids = fi_sessions_get_hierarchy_ids($args['session_id']);
				$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
				$where_conditions[] = "ls.session_id IN ($placeholders)";
				$where_values = array_merge($where_values, $session_ids);
			} elseif (!empty($args['session_ids']) && is_array($args['session_ids'])) {
				// Explicit session IDs (no hierarchy expansion)
				$session_ids = array_values(array_filter(array_map('absint', $args['session_ids'])));
				if (!empty($session_ids)) {
					$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
					$where_conditions[] = "ls.session_id IN ($placeholders)";
					$where_values = array_merge($where_values, $session_ids);
				}
			}
			
			if (!empty($args['gov'])) {
				$where_conditions[] = 'ls.gov = %s';
				$where_values[] = $args['gov'];
			}
			
			if (!empty($args['chamber'])) {
				$where_conditions[] = 'ls.chamber = %s';
				$where_values[] = $args['chamber'];
			}
			
			if (!empty($args['party'])) {
				$where_conditions[] = 'ls.party = %s';
				$where_values[] = $args['party'];
			}
			
			if (!empty($args['state'])) {
				$where_conditions[] = 'ls.state = %s';
				$where_values[] = strtoupper($args['state']);
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
			// Build ORDER BY clause; date_end supports secondary sort by id for chamber-change ordering.
			$allowed_orderby = ['id', 'score', 'date_created', 'date_updated', 'date_end'];
			$orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'id';
			$order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

			$order_clause = ($orderby === 'date_end')
				? "ORDER BY s.date_end {$order}, s.id DESC"
				: "ORDER BY ls.{$orderby} {$order}";
/*
			$order_clause = ($orderby === 'date_end')
				? "ORDER BY ls.date_end {$order}, ls.id DESC"
				: "ORDER BY ls.{$orderby} {$order}";
*/

			// Build LIMIT clause
			$limit_clause = '';
			if ($args['per_page'] > 0) {
				$offset = ($args['page'] - 1) * $args['per_page'];
				$limit_clause = "LIMIT {$offset}, {$args['per_page']}";
			}
			
			if ($args['count']) {
				$sql = "
					SELECT COUNT(*)
					FROM {$wpdb->prefix}fi_legislator_sessions ls
					{$where_clause}
				";
				
				$results = (int) $wpdb->get_var($wpdb->prepare($sql, $where_values));
				fi_cache($cacheKey,$results);
				self::$cache_get[$cache_key] = $results;
				return $results;
			}
			
			$sql = "
				SELECT 
					ls.*,
					l.first_name, l.last_name, l.display_name, 
					s.name AS session_name, s.gov, s.parent_id, s.status
				FROM {$wpdb->prefix}fi_legislator_sessions ls
				LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
				LEFT JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
				{$where_clause}
				{$order_clause}
				{$limit_clause}
			";
			//Cache and return results
			$results = $wpdb->get_results($wpdb->prepare($sql, $where_values));
			fi_cache($cacheKey,$results);
			self::$cache_get[$cache_key] = $results;
			return $results;
		}

		/**
		* Get a single legislator session by ID
		*/
		public static function get_by_id(int $session_id): ?object {
			$results = self::get(['id' => $session_id, 'per_page' => 1]);
			return $results[0] ?? null;
		}

		/**
		* Get legislator sessions by session ID
		*/
		public static function get_by_session(int $session_id): array {
			return self::get(['session_id' => $session_id]);
		}

		/**
		* Get legislator's chamber for a specific session
		* 
		* @param int $legislator_id Legislator ID
		* @param int $session_id Session ID
		* @return string|null chamber (H or S) or null if not found
		*/
		public static function get_legislator_chamber(int $legislator_id, int $session_id): ?string {
			// Exact pair lookup (no session hierarchy expansion)
			$results = self::get([
				'legislator_id' => $legislator_id,
				'session_ids' => [$session_id],
				'per_page' => 1,
			]);

			$row = $results[0] ?? null;
			$chamber = $row->chamber ?? null;

			return $chamber && in_array($chamber, ['H', 'S'], true) ? $chamber : null;
		}

		/**
		* Get fi_legislator_sessions.id for an exact legislator/session pair (no hierarchy).
		*/
		public static function get_id_by_pair(int $legislator_id, int $session_id): ?int {
			// Exact pair lookup (no session hierarchy expansion)
			$results = self::get([
				'legislator_id' => $legislator_id,
				'session_ids' => [$session_id],
				'per_page' => 1,
			]);

			$row = $results[0] ?? null;
			return !empty($row->id) ? (int) $row->id : null;
		}

		/**
		* Save/Update legislator session
		*/
		public static function save(array $data, ?int $session_id = null): int|false {
			global $wpdb;
			
			// Validate required fields
			if (empty($data['legislator_id']) || empty($data['session_id'])) {
				return false;
			}
			
			$fields = [
				'legislator_id' => $data['legislator_id'],
				'session_id' => $data['session_id'],
				'gov' => $data['gov'] ?? '',
				'state' => $data['state'] ?? null, // State code for congressional legislators (US only)
				'chamber' => $data['chamber'] ?? null,
				'district' => $data['district'] ?? null,
				'party' => $data['party'] ?? null,
				'image_id' => $data['image_id'] ?? null,
				'date_start' => $data['date_start'] ?? null,
				'date_end' => $data['date_end'] ?? null,
				'score' => $data['score'] ?? null,
				'score_data' => isset($data['score_data']) ? (is_string($data['score_data']) ? $data['score_data'] : json_encode($data['score_data'])) : null,
				'score_date' => $data['score_date'] ?? null,
				'meta' => isset($data['meta']) ? json_encode($data['meta']) : null
			];
			
			$legislator_id = $data['legislator_id'];
			
			if ($session_id) {
				// Update existing session
				$result = $wpdb->update(
					"{$wpdb->prefix}fi_legislator_sessions",
					$fields,
					['id' => $session_id],
					['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s'],
					['%d']
				);
				
				if ($result !== false) {
					return $session_id;
				}
				
				return false;
			} else {
				// Insert new session
				$result = $wpdb->insert(
					"{$wpdb->prefix}fi_legislator_sessions",
					$fields,
					['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s']
				);
				
				if ($result !== false) {
					return (int) $wpdb->insert_id;
				}
				
				return false;
			}
		}

		/**
		* Update legislator session
		*/
		public static function update(int $session_id, array $data): bool {
			return self::save($data, $session_id) !== false;
		}

		/**
		* Delete legislator session
		*/
		public static function delete(int $session_id): bool {
			global $wpdb;
			$result = $wpdb->delete(
				"{$wpdb->prefix}fi_legislator_sessions",
				['id' => $session_id],
				['%d']
			);
			return $result !== false;
		}

		/**
		* Update score for a legislator session
		*/
		public static function update_score(int $legislator_id, int $session_id, array $score_data): bool {
			global $wpdb;
			
			// Build score_data JSON object (without votes_ prefix)
			// Support both old (votes_*) and new (no prefix) format for backward compatibility
			$score_data_json = [
				'total' => (int) ($score_data['total'] ?? $score_data['votes_total'] ?? 0),
				'good' => (int) ($score_data['good'] ?? $score_data['votes_good'] ?? 0),
				'bad' => (int) ($score_data['bad'] ?? $score_data['votes_bad'] ?? 0),
				'not' => (int) ($score_data['not'] ?? $score_data['votes_not'] ?? 0),
				'scored' => (int) ($score_data['scored'] ?? $score_data['votes_scored'] ?? 0)
			];
			
			$fields = [
				'score' => $score_data['score'] ?? null,
				'score_data' => json_encode($score_data_json),
				'score_date' => current_time('mysql')
			];
			
			$result = $wpdb->update(
				"{$wpdb->prefix}fi_legislator_sessions",
				$fields,
				['legislator_id' => $legislator_id, 'session_id' => $session_id],
				['%d', '%s', '%s'],
				['%d', '%d']
			);
			
			return $result !== false;
		}

		/**
		* Get scores by session with legislator details
		*/
		public static function get_scores_by_session(int $session_id): array {
			global $wpdb;
			
			// Get all session IDs in the hierarchy (parent + children)
			$session_ids = fi_sessions_get_hierarchy_ids($session_id);
			$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
			
			$sql = "
				SELECT 
					ls.*, l.first_name, l.last_name, l.display_name, l.slug
				FROM {$wpdb->prefix}fi_legislator_sessions ls
				INNER JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
				WHERE ls.session_id IN ($placeholders) AND ls.score IS NOT NULL
				ORDER BY ls.score DESC, l.last_name
			";
			
			return $wpdb->get_results($wpdb->prepare($sql, $session_ids));
		}

		/**
		* Get statistics
		*/
		public static function get_stats(?string $gov = null, ?int $session_id = null): array {
			global $wpdb;
			
			$where_conditions = [];
			$where_values = [];
			
			if ($gov) {
				$where_conditions[] = 'ls.gov = %s';
				$where_values[] = $gov;
			}
			
			if ($session_id) {
				// Get all session IDs in the hierarchy (parent + children)
				$session_ids = fi_sessions_get_hierarchy_ids($session_id);
				$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
				$where_conditions[] = "ls.session_id IN ($placeholders)";
				$where_values = array_merge($where_values, $session_ids);
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
			$sql = "
				SELECT 
					COUNT(*) as total_sessions,
					AVG(ls.score) as avg_score,
					MIN(ls.score) as min_score,
					MAX(ls.score) as max_score,
					SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.total'), JSON_EXTRACT(ls.score_data, '$.votes_total'), 0) AS UNSIGNED)) as total_votes,
					SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.good'), JSON_EXTRACT(ls.score_data, '$.votes_good'), 0) AS UNSIGNED)) as total_good_votes,
					SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.bad'), JSON_EXTRACT(ls.score_data, '$.votes_bad'), 0) AS UNSIGNED)) as total_bad_votes,
					SUM(CAST(COALESCE(JSON_EXTRACT(ls.score_data, '$.not'), JSON_EXTRACT(ls.score_data, '$.votes_not'), 0) AS UNSIGNED)) as total_not_votes
				FROM {$wpdb->prefix}fi_legislator_sessions ls
				WHERE ls.score_data IS NOT NULL {$where_clause}
			";
			
			$result = $wpdb->get_row($wpdb->prepare($sql, $where_values));
			
			return [
				'total_sessions' => (int) $result->total_sessions,
				'avg_score' => round((float) $result->avg_score, 2),
				'min_score' => round((float) $result->min_score, 2),
				'max_score' => round((float) $result->max_score, 2),
				'total_votes' => (int) $result->total_votes,
				'total_good_votes' => (int) $result->total_good_votes,
				'total_bad_votes' => (int) $result->total_bad_votes,
				'total_not_votes' => (int) $result->total_not_votes
			];
		}
	}
}

//namespace for global functions
namespace {

	/* Get legislator sessions with optional filtering */
	function fi_legislator_sessions_get(array $args = []): array|int {
		return \FI\Core\LegislatorSessions::get($args);
	}

	/* Get a single legislator session by ID */
	function fi_legislator_session_get(int $session_id): ?object {
		return \FI\Core\LegislatorSessions::get_by_id($session_id);
	}

	/* Get legislator sessions by legislator ID */
	function fi_legislator_sessions_get_by_legislator(int $legislator_id,$args=[]): array {
		$args = array_merge(['legislator_id' => $legislator_id],$args);
		return \FI\Core\LegislatorSessions::get($args);
	}

	/* Get legislator sessions by session ID */
	function fi_legislator_sessions_get_by_session(int $session_id): array {
		return \FI\Core\LegislatorSessions::get_by_session($session_id);
	}

	/* Get legislator chamber for a specific session */
	function fi_legislator_chamber(int $legislator_id, int $session_id): ?string {
		return \FI\Core\LegislatorSessions::get_legislator_chamber($legislator_id, $session_id);
	}

	/* Get exact fi_legislator_sessions.id for a legislator/session pair */
	function fi_legislator_session_id(int $legislator_id, int $session_id): ?int {
		return \FI\Core\LegislatorSessions::get_id_by_pair($legislator_id, $session_id);
	}

	/* Save/Update legislator session */
	function fi_legislator_session_save(array $data, ?int $session_id = null): int|false {
		return \FI\Core\LegislatorSessions::save($data, $session_id);
	}

	/* Update legislator session */
	function fi_legislator_session_update(int $session_id, array $data): bool {
		return \FI\Core\LegislatorSessions::update($session_id, $data);
	}

	/* Delete legislator session */
	function fi_legislator_session_delete(int $session_id): bool {
		return \FI\Core\LegislatorSessions::delete($session_id);
	}

	/* Update score for a legislator session */
	function fi_legislator_session_update_score(int $legislator_id, int $session_id, array $score_data): bool {
		return \FI\Core\LegislatorSessions::update_score($legislator_id, $session_id, $score_data);
	}

	/* Get scores by session with legislator details */
	function fi_legislator_sessions_get_scores_by_session(int $session_id): array {
		return \FI\Core\LegislatorSessions::get_scores_by_session($session_id);
	}

	/* Get legislator session statistics */
	function fi_legislator_sessions_stats(?string $gov = null, ?int $session_id = null): array {
		return \FI\Core\LegislatorSessions::get_stats($gov, $session_id);
	}

	/* Get legislator session meta value by key */
	function fi_legislator_session_get_meta($record, string $key, $default = null) {
		return \FI\Core\LegislatorSessions::get_meta($record, $key, $default);
	}

	/* Get all legislator session meta */
	function fi_legislator_session_get_all_meta($record): array {
		return \FI\Core\LegislatorSessions::get_all_meta($record);
	}

	/* Update legislator session meta key(s) without affecting other keys */
	function fi_legislator_session_update_meta(int $record_id, array $meta_updates): bool {
		return \FI\Core\LegislatorSessions::update_meta($record_id, 'fi_legislator_sessions', $meta_updates);
	}

	/* Delete legislator session meta key(s) */
	function fi_legislator_session_delete_meta(int $record_id, $keys): bool {
		return \FI\Core\LegislatorSessions::delete_meta($record_id, 'fi_legislator_sessions', $keys);
	}

	/* Set entire legislator session meta (replaces all) */
	function fi_legislator_session_set_all_meta(int $record_id, array $meta): bool {
		return \FI\Core\LegislatorSessions::set_all_meta($record_id, 'fi_legislator_sessions', $meta);
	}
}