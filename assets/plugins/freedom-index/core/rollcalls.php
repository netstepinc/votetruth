<?php
namespace FI\Core{

	if (!defined('ABSPATH')) exit;

	/**
	* Roll-calls Table I/O Operations
	* All database operations for the fi_voterc table.
	*/
	final class Rollcalls {

		/**
		 * Request-level cache for get() results
		 * Key: md5 hash of serialized args
		 */
		private static $cache_get = [];

		/**
		* Get roll-call votes with optional filtering
		* 
		* @param array $args {
		*     Optional. Arguments to filter roll-call votes.
		* 
		*     @type int          $vote_id       Filter by vote ID
		*     @type array<int>   $vote_ids      Filter by multiple vote IDs
		*     @type int          $legislator_id Filter by legislator ID
		*     @type string       $cast         Filter by cast (Y/N/P/X)
		*     @type bool         $is_override   Filter by override status
		*     @type string       $chamber       Filter by legislator chamber (H/S) for the vote's session
		*     @type string       $party        Filter by legislator party for the vote's session
		*     @type string       $district     Filter by legislator district for the vote's session
		*     @type string       $orderby       Order by field (chamber, legislator_id, etc.)
		*     @type string       $order         Order direction (ASC/DESC)
		*     @type int          $per_page      Number per page
		*     @type int          $page          Page number
		*     @type bool         $count         Return count only
		* }
		* @return array|int Array of roll-call objects or count if $count is true
		*/
		public static function get(array $args = []): array|int {
			global $wpdb;
			
			$defaults = [
				'vote_id' => null,
				'vote_ids' => null,
				'legislator_id' => null,
				'cast' => null,
				'is_override' => null,
				'chamber' => null,
				'party' => null,
				'district' => null,
				'orderby' => 'legislator_id',
				'order' => 'ASC',
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
			$cacheKey = fi_cache_key('rollcalls/get', $args);
			$results = fi_cache($cacheKey); //1 day
			if($results){
				// Store in request-level cache
				self::$cache_get[$cache_key] = $results;
				return $results;
			}

			$need_legislator_session = !empty($args['chamber']) || !empty($args['party']) || !empty($args['district']);
			
			// Build WHERE clause
			$where_conditions = [];
			$where_values = [];
			
			if ($args['vote_id']) {
				$where_conditions[] = "rc.vote_id = %d";
				$where_values[] = $args['vote_id'];
			}

			if (!empty($args['vote_ids']) && is_array($args['vote_ids'])) {
				$vote_ids = array_values(array_filter(array_map('intval', $args['vote_ids'])));
				if (!empty($vote_ids)) {
					$placeholders = implode(',', array_fill(0, count($vote_ids), '%d'));
					$where_conditions[] = "rc.vote_id IN ($placeholders)";
					$where_values = array_merge($where_values, $vote_ids);
				} else {
					$where_conditions[] = "1 = 0";
				}
			}
			
			if ($args['legislator_id']) {
				$where_conditions[] = "rc.legislator_id = %d";
				$where_values[] = $args['legislator_id'];
			}
			
			if ($args['cast']) {
				$where_conditions[] = "rc.cast = %s";
				$where_values[] = $args['cast'];
			}
			
			if ($args['is_override'] !== null) {
				$where_conditions[] = "rc.is_override = %d";
				$where_values[] = $args['is_override'] ? 1 : 0;
			}

			// Legislator-session filters (must join fi_legislator_sessions)
			if (!empty($args['chamber'])) {
				$where_conditions[] = "ls.chamber = %s";
				$where_values[] = $args['chamber'];
			}

			if (!empty($args['party'])) {
				$where_conditions[] = "ls.party = %s";
				$where_values[] = $args['party'];
			}

			if (!empty($args['district'])) {
				$where_conditions[] = "ls.district = %s";
				$where_values[] = $args['district'];
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
			// Build ORDER BY clause (mapped)
			$dir = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';
			$orderby_map = [
				'legislator_id' => "rc.legislator_id {$dir}",
				'vote_id' => "rc.vote_id {$dir}",
				'date_created' => "rc.date_created {$dir}",
				'last_name' => "l.last_name {$dir}",
				'first_name' => "l.first_name {$dir}",
				'name' => "l.last_name {$dir}, l.first_name {$dir}",
			];
			$orderby = $orderby_map[$args['orderby']] ?? "rc.legislator_id {$dir}";
			
			// Build LIMIT clause
			$limit_clause = '';
			if ($args['per_page'] > 0) {
				$offset = ($args['page'] - 1) * $args['per_page'];
				$limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", $args['per_page'], $offset);
			}
			
			if ($args['count']) {
				$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}fi_voterc rc {$where_clause}";
				
				if (!empty($where_values)) {
					$sql = $wpdb->prepare($sql, $where_values);
				}
				
				return (int) $wpdb->get_var($sql);
			}

			$select_ls = '';
			$join_ls = '';
			if ($need_legislator_session) {
				$select_ls = ", ls.party, ls.chamber, ls.district";
				// Join legislator_sessions for the same session as the vote.
				$join_ls = "LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON rc.legislator_id = ls.legislator_id AND ls.session_id = v.session_id";
			}
			
			$sql = "
				SELECT rc.*, 
					l.first_name, l.last_name, l.display_name, l.id as legislator_id,
					v.title as vote_title, v.bill_number as bill_key, v.constitutional,
					s.name as session_name 
					{$select_ls}
				FROM {$wpdb->prefix}fi_voterc rc
				LEFT JOIN {$wpdb->prefix}fi_legislators l ON rc.legislator_id = l.id
				LEFT JOIN {$wpdb->prefix}fi_votes v ON rc.vote_id = v.id
				{$join_ls}
				LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
				{$where_clause}
				ORDER BY {$orderby}
				{$limit_clause}
			";
			
			if (!empty($where_values)) {
				$sql = $wpdb->prepare($sql, $where_values);
			}

			$results = $wpdb->get_results($sql);
			fi_cache($cacheKey,$results);
			// Store in request-level cache
			self::$cache_get[$cache_key] = $results;
			return $results;
		}

		/**
		* Get roll-call votes for a specific vote with legislator details
		* 
		* @param int $vote_id
		* @param array $filters Additional filters
		* @return array
		*/
		public static function get_by_vote(int $vote_id, array $filters = []): array {
			$args = array_merge($filters, [
				'vote_id' => $vote_id,
				'orderby' => 'name',
				'order' => 'ASC',
				'per_page' => -1,
			]);

			return self::get($args);
		}

		/**
		* Get roll-call data for a specific vote as an associative array::Direct minimal query
		*	[0] => Array ( [id] => 491377 [vote_id] => 3476 [legislator_id] => 12703 [cast] => Y ) 
		*	[1] => Array ( [id] => 491376 [vote_id] => 3476 [legislator_id] => 12704 [cast] => N ) 
		* @param int $vote_id
		* @return array
		*/
		public static function legislators_cast_by_vote(int $vote_id): array {
			global $wpdb;
			$sql = $wpdb->prepare("SELECT id, vote_id, legislator_id, cast FROM {$wpdb->prefix}fi_voterc WHERE vote_id = %d ORDER BY id DESC", $vote_id);
			$results = $wpdb->get_results($sql, ARRAY_A);
			$rollcall = [];
			foreach($results as $row){
				$rollcall[$row['legislator_id']] = ['id' => $row['id'], 'cast' => $row['cast']];
			}
			return $rollcall;
		}

		public static function get_by_vote_and_legislator(int $vote_id, int $legislator_id): ?array {
			global $wpdb;
			$sql = $wpdb->prepare("SELECT id, vote_id, legislator_id, cast, is_override FROM {$wpdb->prefix}fi_voterc WHERE vote_id = %d AND legislator_id = %d ORDER BY id DESC", $vote_id, $legislator_id);
			$results = $wpdb->get_results($sql, ARRAY_A);
			return $results[0] ?? null;
		}

		/**
		* Get roll-call votes for a specific legislator
		* 
		* @param int $legislator_id
		* @param array $filters Additional filters
		* @return array
		*/
		public static function get_by_legislator(int $legislator_id, array $filters = []): array {
			$args = array_merge($filters, ['legislator_id' => $legislator_id]);
			return self::get($args);
		}

		/**
		* Get roll-call votes for multiple vote IDs (bulk fetch)
		* Optimized for fetching all rollcalls for a set of votes
		* 
		* @param array $vote_ids Array of vote IDs
		* @return array Array of rollcall objects
		*/
		public static function get_by_vote_ids(array $vote_ids): array {
			global $wpdb;
			
			if (empty($vote_ids)) {
				return [];
			}
			
			$vote_ids = array_map('intval', $vote_ids);
			$vote_ids = array_unique($vote_ids);
			$placeholders = implode(',', array_fill(0, count($vote_ids), '%d'));
			
			$sql = "
				SELECT 
					rc.vote_id,
					rc.legislator_id,
					rc.cast,
					rc.is_override,
					rc.date_created
				FROM {$wpdb->prefix}fi_voterc rc
				WHERE rc.vote_id IN ($placeholders)
				ORDER BY rc.vote_id ASC, rc.legislator_id ASC
			";
			
			return $wpdb->get_results($wpdb->prepare($sql, $vote_ids));
		}

		/**
		* Get roll-call counts keyed by vote ID
		*
		* @param array $vote_ids
		* @return array<int,int>
		*/
		public static function get_counts_by_vote_ids(array $vote_ids): array {
			global $wpdb;

			if (empty($vote_ids)) {
				return [];
			}

			$vote_ids = array_map('intval', $vote_ids);
			$vote_ids = array_filter($vote_ids);

			if (empty($vote_ids)) {
				return [];
			}

			$placeholders = implode(',', array_fill(0, count($vote_ids), '%d'));
			$sql = "
				SELECT vote_id, COUNT(*) as total
				FROM {$wpdb->prefix}fi_voterc
				WHERE vote_id IN ($placeholders)
				GROUP BY vote_id
			";

			$results = $wpdb->get_results($wpdb->prepare($sql, $vote_ids));
			$counts = [];

			foreach ($results as $row) {
				$counts[(int) $row->vote_id] = (int) $row->total;
			}

			return $counts;
		}

		/**
		* Get a single roll-call vote
		* 
		* @param int $vote_id
		* @param int $legislator_id
		* @return object|null
		*/
		public static function get_by_ids(int $vote_id, int $legislator_id): ?object {
			$results = self::get([
				'vote_id' => $vote_id,
				'legislator_id' => $legislator_id,
				'per_page' => 1,
			]);

			return $results[0] ?? null;
		}

		/**
		* Save/Update roll-call vote with duplicate checking
		* 
		* @param array $data Roll-call data
		* @param int|null $vote_id Update existing if provided
		* @param int|null $legislator_id Update existing if provided
		* @return int|false Roll-call ID on success, false on failure
		*/
		public static function save(array $data, ?int $vote_id = null, ?int $legislator_id = null): int|false {
			global $wpdb;

			// Normalize cast into 4-state system (Y/N/P/X) before any validation/writes.
			// This keeps all rollcall records consistent, regardless of import source.
			if (array_key_exists('cast', $data)) {
				$data['cast'] = self::normalize_cast((string) $data['cast']);
			}
			
			// Validate required fields
			if (empty($data['vote_id']) || empty($data['legislator_id']) || !isset($data['cast']) || $data['cast'] === '') {
				return false;
			}
			
			// Check for duplicates
			$existing = self::get_by_ids($data['vote_id'], $data['legislator_id']);
			$is_update = !empty($existing);
			
			// Prepare data for database
			$db_data = [
				'vote_id' => $data['vote_id'],
				'legislator_id' => $data['legislator_id'],
				'cast' => $data['cast'],
				'is_override' => $data['is_override'] ?? 0
			];
			
			if ($is_update) {
				// Update existing
				$result = $wpdb->update(
					$wpdb->prefix . 'fi_voterc',
					$db_data,
					['vote_id' => $data['vote_id'], 'legislator_id' => $data['legislator_id']],
					['%d', '%d', '%s', '%d'],
					['%d', '%d']
				);
				
				return $result !== false ? $existing->id : false;
			} else {
				// Insert new
				$result = $wpdb->insert(
					$wpdb->prefix . 'fi_voterc',
					$db_data,
					['%d', '%d', '%s', '%d']
				);
				
				return $result !== false ? $wpdb->insert_id : false;
			}
		}

		/**
		* Update roll-call vote
		* 
		* @param int $vote_id
		* @param int $legislator_id
		* @param array $data
		* @return bool
		*/
		public static function update(int $vote_id, int $legislator_id, array $data): bool {
			$data['vote_id'] = $vote_id;
			$data['legislator_id'] = $legislator_id;
			return self::save($data) !== false;
		}

		/**
		* Delete roll-call vote
		* 
		* @param int $vote_id
		* @param int $legislator_id
		* @return bool
		*/
		public static function delete(int $vote_id, int $legislator_id): bool {
			global $wpdb;
			
			$result = $wpdb->delete(
				$wpdb->prefix . 'fi_voterc',
				['vote_id' => $vote_id, 'legislator_id' => $legislator_id],
				['%d', '%d']
			);
			
			return $result !== false;
		}

		/**
		* Delete all roll-call votes for a vote
		* 
		* @param int $vote_id
		* @return bool
		*/
		public static function delete_rollcalls_by_vote(int $vote_id): bool {
			global $wpdb;
			
			$result = $wpdb->delete(
				$wpdb->prefix . 'fi_voterc',
				['vote_id' => $vote_id],
				['%d']
			);
			
			return $result !== false;
		}

		/**
		* Delete all roll-call votes for a legislator
		* 
		* @param int $legislator_id
		* @return bool
		*/
		public static function delete_rollcalls_by_legislator(int $legislator_id): bool {
			global $wpdb;
			
			$result = $wpdb->delete(
				$wpdb->prefix . 'fi_voterc',
				['legislator_id' => $legislator_id],
				['%d']
			);
			
			return $result !== false;
		}

		/**
		* Import roll-call data from JSON
		* 
		* @param int $vote_id
		* @param string|array $rollcall_data JSON string or array
		* @param string $gov Government code for legislator matching
		* @return int Number of roll-call votes imported
		*/
		public static function import_data(int $vote_id, string|array $rollcall_data, string $gov): int {
			global $wpdb;
			
			// Parse JSON if string
			if (is_string($rollcall_data)) {
				$rollcall_data = json_decode($rollcall_data, true);
			}
			
			if (!is_array($rollcall_data)) {
				return 0;
			}
			
			$imported_count = 0;
			
			foreach ($rollcall_data as $external_id => $cast) {
				// Find legislator by external ID
				$legislator = self::find_legislator_by_external_id($external_id, $gov);
				
				if (!$legislator) {
					continue;
				}
				
				// Normalize cast
				$normalized_cast = self::normalize_cast($cast);
				
				// Save roll-call vote
				$result = self::save_rollcall([
					'vote_id' => $vote_id,
					'legislator_id' => $legislator->id,
					'cast' => $normalized_cast,
					'is_override' => 0
				]);
				
				if ($result !== false) {
					$imported_count++;
				}
			}
			
			return $imported_count;
		}

		/**
		* Find legislator by external ID
		* 
		* @param string $external_id
		* @param string $gov Government code
		* @return object|null
		*/
		private static function find_legislator_by_external_id(string $external_id, string $gov): ?object {
			global $wpdb;
			
			// Try different external ID fields based on government
			$external_fields = [];
			
			if ($gov === 'US') {
				// Congress - try bioguide_id first
				$external_fields = ['bioguide_id', 'legiscan_id', 'votesmart_id', 'ballotpedia_id', 'openstates_id'];
			} else {
				// State - try legiscan_id first
				$external_fields = ['legiscan_id', 'bioguide_id', 'votesmart_id', 'ballotpedia_id', 'openstates_id'];
			}
			
			foreach ($external_fields as $field) {
				$sql = "SELECT id FROM {$wpdb->prefix}fi_legislators WHERE {$field} = %s LIMIT 1";
				$legislator_id = $wpdb->get_var($wpdb->prepare($sql, $external_id));
				
				if ($legislator_id) {
					return (object) ['id' => (int) $legislator_id];
				}
			}
			
			return null;
		}

		/**
		* Normalize vote cast
		* 
		* @param string $cast
		* @return string
		*/
		public static function normalize_cast(string $cast): string {
			$cast = strtoupper(trim($cast));
			
			// Map various cast values to standard 4-state values:
			// Y = Yes, N = No, P = Present (includes Abstain), X = Not Voted (includes Absent)
			$cast_map = [
				'Y' => 'Y',    // Yes
				'N' => 'N',    // No
				'P' => 'P',    // Present
				'X' => 'X',    // Not voting
				'A' => 'X',    // Legacy: A was used for Absent in older imports
				'NV' => 'X',   // Legacy: NV => Not Voted
				'ABSENT' => 'X',
				'ABSTAIN' => 'P',
				'PAIRED' => 'P',
				'1' => 'Y',    // Yes (numeric)
				'2' => 'N',    // No (numeric)
				'0' => 'N',    // No (numeric legacy)
				'YES' => 'Y',
				'AYE' => 'Y',
				'YEA' => 'Y',
				'GUILTY' => 'Y',
				'NO' => 'N',
				'NAY' => 'N',
				'NOT GUILTY' => 'N',
				'PRESENT' => 'P',
				'NOT VOTING' => 'X',
			];
			
			return $cast_map[$cast] ?? 'X';
		}

		/**
		* Get roll-call statistics
		* 
		* @param int|null $vote_id
		* @param int|null $legislator_id
		* @return array
		* 4-state system only: Y/N/P/X
		*/
		public static function get_stats(?int $vote_id = null, ?int $legislator_id = null, ?int $is_override = null): array {
			global $wpdb;
			
			$where_conditions = [];
			$values = [];
			
			if ($vote_id) {
				$where_conditions[] = "vote_id = %d";
				$values[] = $vote_id;
			}
			
			if ($legislator_id) {
				$where_conditions[] = "legislator_id = %d";
				$values[] = $legislator_id;
			}

			if ($is_override !== null) {
				$where_conditions[] = "is_override = %d";
				$values[] = $is_override ? 1 : 0;
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
			$sql = "
				SELECT 
					COUNT(*) as total,
					COUNT(CASE WHEN cast = 'Y' THEN 1 END) as yes_votes,
					COUNT(CASE WHEN cast = 'N' THEN 1 END) as no_votes,
					COUNT(CASE WHEN cast = 'P' THEN 1 END) as present_votes,
					COUNT(CASE WHEN cast = 'X' THEN 1 END) as not_voting,
					COUNT(CASE WHEN is_override = 1 THEN 1 END) as overrides
				FROM {$wpdb->prefix}fi_voterc
				{$where_clause}
			";
			
			if (!empty($values)) {
				$sql = $wpdb->prepare($sql, $values);
			}
			
			return (array) $wpdb->get_row($sql);
		}

		/**
		* Validate roll-call data
		* 
		* @param array $data
		* @return array ['valid' => bool, 'errors' => array]
		*/
		public static function validate_data(array $data): array {
			$errors = [];

			// Normalize cast before validation to keep all write paths consistent.
			if (array_key_exists('cast', $data)) {
				$data['cast'] = self::normalize_cast((string) $data['cast']);
			}
			
			// Required fields
			if (empty($data['vote_id'])) {
				$errors[] = 'Vote ID is required';
			}
			
			if (empty($data['legislator_id'])) {
				$errors[] = 'Legislator ID is required';
			}
			
			if (empty($data['cast'])) {
				$errors[] = 'Cast is required';
			}
			
			// Validate cast
			if (!empty($data['cast']) && !in_array($data['cast'], ['Y', 'N', 'P', 'X'], true)) {
				$errors[] = 'Cast must be Y, N, P, or X';
			}
			
			// Validate IDs are numeric
			if (!empty($data['vote_id']) && !is_numeric($data['vote_id'])) {
				$errors[] = 'Vote ID must be numeric';
			}
			
			if (!empty($data['legislator_id']) && !is_numeric($data['legislator_id'])) {
				$errors[] = 'Legislator ID must be numeric';
			}
			
			return [
				'valid' => empty($errors),
				'errors' => $errors
			];
		}

		/**
		* Get roll-call summary for a vote
		* 
		* @param int $vote_id
		* @return array
		*/
		public static function get_vote_summary(int $vote_id, ?int $is_override = null): array {
			$stats = self::get_stats($vote_id, null, $is_override);
			
			return [
				'total_votes' => $stats['total'],
				'yes' => $stats['yes_votes'],
				'no' => $stats['no_votes'],
				'present' => $stats['present_votes'],
				'not_voting' => $stats['not_voting'],
				'overrides' => $stats['overrides']
			];
		}
	}
}

//namespace for global functions
namespace {
	/* Get roll-call votes with optional filtering */
	function fi_rollcalls_get(array $args = []): array|int {
		return \FI\Core\Rollcalls::get($args);
	}

	/* Get roll-call votes for a specific vote */
	function fi_rollcalls_get_by_vote(int $vote_id, array $filters = []): array {
		return \FI\Core\Rollcalls::get_by_vote($vote_id, $filters);
	}

	/* Get roll-call data for a specific vote */
	function fi_rollcalls_legislators_cast_by_vote(int $vote_id): array {
		return \FI\Core\Rollcalls::legislators_cast_by_vote($vote_id);
	}


	/* Get roll-call votes for a specific legislator */
	function fi_rollcalls_get_by_legislator(int $legislator_id, array $filters = []): array {
		return \FI\Core\Rollcalls::get_by_legislator($legislator_id, $filters);
	}

	/* Get specific rollcall by vote_id and legislator_id */
	function fi_rollcall(int $vote_id, int $legislator_id): ?array {
		return \FI\Core\Rollcalls::get_by_vote_and_legislator($vote_id, $legislator_id);
	}

	/* Get a single roll-call vote */
	function fi_rollcall_get(int $vote_id, int $legislator_id): ?object {
		return \FI\Core\Rollcalls::get_by_ids($vote_id, $legislator_id);
	}

	/* Save/Update roll-call vote */
	function fi_rollcall_save(array $data, ?int $vote_id = null, ?int $legislator_id = null): int|false {
		return \FI\Core\Rollcalls::save($data, $vote_id, $legislator_id);
	}

	/* Update roll-call vote */
	function fi_rollcall_update(int $vote_id, int $legislator_id, array $data): bool {
		return \FI\Core\Rollcalls::update($vote_id, $legislator_id, $data);
	}

	/* Delete roll-call vote */
	function fi_rollcall_delete(int $vote_id, int $legislator_id): bool {
		return \FI\Core\Rollcalls::delete($vote_id, $legislator_id);
	}

	/* Import roll-call data from JSON */
	function fi_rollcall_import(int $vote_id, string|array $rollcall_data, string $gov): int {
		return \FI\Core\Rollcalls::import_data($vote_id, $rollcall_data, $gov);
	}

	/* Get roll-call statistics */
	function fi_rollcalls_stats(?int $vote_id = null, ?int $legislator_id = null, ?int $is_override = null): array {
		return \FI\Core\Rollcalls::get_stats($vote_id, $legislator_id, $is_override);
	}

	/* Get roll-call summary for a vote */
	function fi_rollcall_summary(int $vote_id, ?int $is_override = null): array {
		return \FI\Core\Rollcalls::get_vote_summary($vote_id, $is_override);
	}

	/**
	 * Normalize any cast into the standard 4-state value: Y, N, P, X.
	 * Templates should use this instead of hand-rolling strtoupper/trim/default logic.
	 */
	function fi_rollcall_cast_normalize(string $cast): string {
		return \FI\Core\Rollcalls::normalize_cast($cast);
	}

	/* Get roll-call counts grouped by vote ID */
	function fi_rollcalls_get_counts_by_vote_ids(array $vote_ids): array {
		return \FI\Core\Rollcalls::get_counts_by_vote_ids($vote_ids);
	}

	/* Delete all roll-call votes for a vote */
	function fi_rollcalls_delete_by_vote(int $vote_id): bool {
		return \FI\Core\Rollcalls::delete_rollcalls_by_vote($vote_id);
	}

	/* Delete all roll-call votes for a legislator */
	function fi_rollcalls_delete_by_legislator(int $legislator_id): bool {
		return \FI\Core\Rollcalls::delete_rollcalls_by_legislator($legislator_id);
	}
}