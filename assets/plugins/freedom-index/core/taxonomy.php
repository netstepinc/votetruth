<?php
namespace FI\Core{

	if (!defined('ABSPATH')) exit;

	/*
	* Taxonomy Table I/O Operations
	* All database operations for the fi_taxonomy table.
	*/
	final class Taxonomy {

		/**
		 * Request-level cache for get() results
		 * Key: md5 hash of serialized args
		 */
		private static $cache_get = [];

		/**
		* Static cache for taxonomy items by ID
		* @var array
		*/
		private static $cache_by_id = [];

		/**
		* Get taxonomies with optional filtering
		* 
		* @param array $args {
		*     Optional. Arguments to filter taxonomy items.
		* 
		*     @type string       $taxonomy          Filter by type (party, tag, district)
		*     @type string       $gov           Filter by government code
		*     @type string       $search        Search in names
		*     @type string       $orderby       Order by field (name, slug, etc.)
		*     @type string       $order         Order direction (ASC/DESC)
		*     @type int          $per_page      Number per page
		*     @type int          $page          Page number
		*     @type bool         $count         Return count only
		* }
		* @return array|int Array of taxonomy objects or count if $count is true
		*/
		public static function get(array $args = []): array|int {
			global $wpdb;
			
			$defaults = [
				'id' => null,
				'slug' => null,
				'taxonomy' => null,
				'gov' => null,
				'search' => null,
				'orderby' => 'name',
				'order' => 'ASC',
				'per_page' => -1,
				'page' => 1,
				'count' => false
			];
			
			$args = wp_parse_args($args, $defaults);
			if ($args['taxonomy'] === 'tag') {
				// Summary: tags are global; ignore gov filters.
				$args['gov'] = null;
			}

			// Check request-level cache first (fastest)
			$cache_key = md5(serialize($args));
			if (isset(self::$cache_get[$cache_key])) {
				return self::$cache_get[$cache_key];
			}

			// Cache query results (file system cache)
			// IMPORTANT: orderby/order MUST be part of the cache key, otherwise admin sorting appears broken.
			$cacheKey = fi_cache_key('taxonomy/get', $args);
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
				$where_values[] = (int) $args['id'];
			}

			if ($args['slug']) {
				$where_conditions[] = "slug = %s";
				$where_values[] = (string) $args['slug'];
			}
			
			if ($args['taxonomy']) {
				$where_conditions[] = "taxonomy = %s";
				$where_values[] = $args['taxonomy'];
			}
			
			if ($args['gov']) {
				$where_conditions[] = "gov = %s";
				$where_values[] = $args['gov'];
			}
			
			if ($args['search']) {
				$where_conditions[] = "(name LIKE %s OR slug LIKE %s)";
				$search_term = '%' . $wpdb->esc_like($args['search']) . '%';
				$where_values[] = $search_term;
				$where_values[] = $search_term;
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
			// Build ORDER BY clause
			$orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
			if (!$orderby) {
				$orderby = 'name ASC';
			}
			
			// Build LIMIT clause
			$limit_clause = '';
			if ($args['per_page'] > 0) {
				$offset = ($args['page'] - 1) * $args['per_page'];
				$limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", $args['per_page'], $offset);
			}
			
			if ($args['count']) {
				$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}fi_taxonomy {$where_clause}";
				
				if (!empty($where_values)) {
					$sql = $wpdb->prepare($sql, $where_values);
				}
				
				return (int) $wpdb->get_var($sql);
			}
			
			$sql = "
				SELECT * FROM {$wpdb->prefix}fi_taxonomy
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
		* Get a single taxonomy by ID
		* Uses static cache to prevent duplicate queries
		* 
		* @param int $taxonomy_id
		* @return object|null
		*/
		public static function get_by_id(int $taxonomy_id): ?object {
			// Check cache first
			if (isset(self::$cache_by_id[$taxonomy_id])) {
				return self::$cache_by_id[$taxonomy_id];
			}

			$results = self::get(['id' => $taxonomy_id, 'per_page' => 1]);
			$result = $results[0] ?? null;
			
			// Cache result (even if null to prevent repeated queries)
			self::$cache_by_id[$taxonomy_id] = $result;
			
			return $result;
		}
		
		/**
		* Clear the taxonomy cache (useful for testing or after updates)
		*/
		public static function clear_cache(): void {
			self::$cache_get = [];
		}

		/**
		* Get taxonomy by slug
		* 
		* @param string $slug
		* @param string $taxonomy
		* @param string|null $gov
		* @return object|null
		*/
		public static function get_by_slug(string $slug, string $taxonomy, ?string $gov = null): ?object {
			$args = [
				'slug' => $slug,
				'taxonomy' => $taxonomy,
				'per_page' => 1,
			];
			if ($gov && $taxonomy !== 'tag') {
				$args['gov'] = $gov;
			}
			$results = self::get($args);
			return $results[0] ?? null;
		}





		/**
		* Save/Update taxonomy item with duplicate checking
		* 
		* @param array $data Taxonomy data
		* @param int|null $taxonomy_id Update existing if provided
		* @return int|false Taxonomy ID on success, false on failure
		*/
		public static function save(array $data, ?int $taxonomy_id = null): int|false {
			global $wpdb;
			$taxonomy = $data['taxonomy'] ?? '';
			$is_tag = ($taxonomy === 'tag');
			if ($is_tag && empty($data['gov'])) {
				// Summary: tags are global; default gov to US for schema compatibility.
				$data['gov'] = 'US';
			}
			
			// Validate required fields
			if (empty($data['taxonomy']) || empty($data['name']) || (!$is_tag && empty($data['gov']))) {
				return false;
			}

			// District slug policy:
			// - Slugs are auto/stable and MUST NOT include ordinal suffixes ("st/nd/rd/th").
			// - Use "{state}-{number}" (e.g. "tn-7"). Ordinal belongs in the NAME only.
			if (($data['taxonomy'] ?? '') === 'district') {
				// Normalize a provided slug (strip trailing ordinal suffix if present).
				if (!empty($data['slug']) && is_string($data['slug'])) {
					$slug = strtolower(trim($data['slug']));
					if (preg_match('/^([a-z]{2})-(\d+)(st|nd|rd|th)$/', $slug, $m)) {
						$slug = $m[1] . '-' . ltrim($m[2], '0');
					}
					$data['slug'] = $slug;
				}
			}
			
			// Generate slug if not provided
			if (empty($data['slug'])) {
				if (($data['taxonomy'] ?? '') === 'district') {
					$data['slug'] = self::generate_district_slug($data['name']);
				} else {
					$data['slug'] = self::generate_slug($data['name'], $data['taxonomy'], $is_tag ? null : $data['gov']);
				}
			}
			
			// Check for duplicates
			$duplicate_check = self::check_duplicates($data, $taxonomy_id);
			if ($duplicate_check['is_duplicate']) {
				return $duplicate_check['existing_id'];
			}
			
			// Prepare data for database
			$db_data = [
				'gov' => $data['gov'],
				'taxonomy' => $data['taxonomy'],
				'name' => $data['name'],
				'slug' => $data['slug'],
				'meta' => !empty($data['meta']) ? json_encode($data['meta']) : null
			];

			// Only remove null values for optional fields
			if ($db_data['meta'] === null) {
				unset($db_data['meta']);
			}
			
			// Debug: Log the data being inserted
			self::log('FI Taxonomy Debug - Input data: ' . json_encode($data), __FILE__, __LINE__, 'debug');
			self::log('FI Taxonomy Debug - DB data: ' . json_encode($db_data), __FILE__, __LINE__, 'debug');
			self::log('FI Taxonomy Debug - Gov value: "' . $db_data['gov'] . '" (length: ' . strlen($db_data['gov']) . ')', __FILE__, __LINE__, 'debug');
			
			// Ensure gov is properly formatted for CHAR(2)
			$db_data['gov'] = strtoupper(trim($db_data['gov']));
			if (strlen($db_data['gov']) > 2) {
				$db_data['gov'] = substr($db_data['gov'], 0, 2);
			}

			// Debug: Show $db_data and exit if debugging
			self::log('FI Taxonomy Debug - Gov after formatting: "' . $db_data['gov'] . '" (length: ' . strlen($db_data['gov']) . ')', __FILE__, __LINE__, 'debug');
			self::log('FI Taxonomy Debug - DB data array (pre-update): ' . print_r($db_data, true), __FILE__, __LINE__, 'debug');
			self::log('FI Taxonomy Debug - taxonomy_id: ' . print_r($taxonomy_id, true), __FILE__, __LINE__, 'debug');

			try {
				if ($taxonomy_id) {
					// Dynamically build formats for update (should match $db_data size)
					$format_array = array_fill(0, count($db_data), '%s');

					$result = $wpdb->update(
						$wpdb->prefix . 'fi_taxonomy',
						$db_data,
						['id' => $taxonomy_id],
						$format_array,
						['%d']
					);

					self::log('FI Taxonomy Debug - Update result: ' . ($result !== false ? 'SUCCESS' : 'FAILED'), __FILE__, __LINE__, 'debug');
					if ($wpdb->last_error) {
						self::log('FI Taxonomy Debug - Last error: ' . $wpdb->last_error, __FILE__, __LINE__, 'error');
					}
					return $result !== false ? $taxonomy_id : false;
				} else {
					// Insert new
					self::log('FI Taxonomy Debug - About to insert: ' . json_encode($db_data), __FILE__, __LINE__, 'debug');
					
					// Generate format array dynamically based on data
					$format_array = [];
					foreach ($db_data as $key => $value) {
						$format_array[] = '%s';
					}
					
					self::log('FI Taxonomy Debug - Format array: ' . json_encode($format_array), __FILE__, __LINE__, 'debug');
					self::log('FI Taxonomy Debug - Final gov value before insert: "' . $db_data['gov'] . '"', __FILE__, __LINE__, 'debug');
					
					$result = $wpdb->insert(
						$wpdb->prefix . 'fi_taxonomy',
						$db_data,
						$format_array
					);
					
					self::log('FI Taxonomy Debug - Insert result: ' . ($result !== false ? 'SUCCESS' : 'FAILED'), __FILE__, __LINE__, 'debug');
					if ($wpdb->last_error) {
						self::log('FI Taxonomy Debug - Last error: ' . $wpdb->last_error, __FILE__, __LINE__, 'error');
					}
					
					return $result !== false ? $wpdb->insert_id : false;
				}
			} catch (\Throwable $e) {
				self::log('FI Taxonomy Debug - EXCEPTION: ' . $e->getMessage(), __FILE__, __LINE__, 'error');
				self::log('FI Taxonomy Debug - Exception Trace: ' . $e->getTraceAsString(), __FILE__, __LINE__, 'error');
				//echo '<pre>EXCEPTION: ' . htmlspecialchars($e->getMessage()) . "\n" . $e->getTraceAsString() . "\n" . print_r($db_data, 1) . '</pre>'; exit;
				throw $e;
			}
		}

		/**
		* Update taxonomy item
		* 
		* @param int $taxonomy_id
		* @param array $data
		* @return bool
		*/
		public static function update(int $taxonomy_id, array $data): bool {
			return self::save($data, $taxonomy_id) !== false;
		}

		/**
		* Delete taxonomy item
		* 
		* @param int $taxonomy_id
		* @return bool
		*/
		public static function delete(int $taxonomy_id): bool {
			global $wpdb;
			
			// Check if taxonomy item exists
			$taxonomy = self::get_by_id($taxonomy_id);
			if (!$taxonomy) {
				return false;
			}
			
			// Note: We don't delete related records here as taxonomy items
			// are referenced by other tables but not as foreign keys
			
			// Delete taxonomy item
			$result = $wpdb->delete($wpdb->prefix . 'fi_taxonomy', ['id' => $taxonomy_id]);
			
			return $result !== false;
		}

		/**
		* Check for duplicate taxonomy items
		* 
		* @param array $data
		* @param int|null $exclude_id Exclude this ID from duplicate check
		* @return array ['is_duplicate' => bool, 'existing_id' => int|null]
		*/
		public static function check_duplicates(array $data, ?int $exclude_id = null): array {
			global $wpdb;
			
			$conditions = [];
			$values = [];
			
			// Check by slug + taxonomy; tags are global, districts are gov-scoped.
			if (!empty($data['slug']) && !empty($data['taxonomy'])) {
				if (($data['taxonomy'] ?? '') === 'tag') {
					$conditions[] = "(slug = %s AND taxonomy = %s)";
					$values[] = $data['slug'];
					$values[] = $data['taxonomy'];
				} elseif (!empty($data['gov'])) {
					$conditions[] = "(slug = %s AND taxonomy = %s AND gov = %s)";
					$values[] = $data['slug'];
					$values[] = $data['taxonomy'];
					$values[] = $data['gov'];
				}
			}
			
			if (empty($conditions)) {
				return ['is_duplicate' => false, 'existing_id' => null];
			}
			
			$where_clause = implode(' OR ', $conditions);
			$sql = "SELECT id FROM {$wpdb->prefix}fi_taxonomy WHERE {$where_clause}";
			
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
		* Generate unique slug for taxonomy item
		* 
		* @param string $name
		* @param string $taxonomy
		* @param string $gov
		* @return string
		*/
		public static function generate_slug(string $name, string $taxonomy, ?string $gov): string {
			$base_slug = sanitize_title($name);
			
			$slug = $base_slug;
			$counter = 1;
			
			while (self::slug_exists($slug, $taxonomy, $gov)) {
				$slug = $base_slug . '-' . $counter;
				$counter++;
			}
			
			return $slug;
		}

		/**
		 * Generate a stable district slug from the district name.
		 * Example names: "TN 7th", "TN 7", "TN 7th District" -> slug: "tn-7"
		 */
		private static function generate_district_slug(string $name): string {
			$name = strtoupper(trim($name));
			$state = '';
			$num = '';

			if (preg_match('/^([A-Z]{2})\s+(\d+)/', $name, $m)) {
				$state = $m[1];
				$num = $m[2];
			} elseif (preg_match('/\b([A-Z]{2})\b.*?\b(\d+)\b/', $name, $m)) {
				$state = $m[1];
				$num = $m[2];
			}

			if ($state !== '' && $num !== '') {
				return strtolower($state . '-' . ltrim($num, '0'));
			}

			// Fallback when name isn't parseable.
			return sanitize_title($name);
		}

		/**
		* Check if slug exists for a taxonomy and gov
		* 
		* @param string $slug
		* @param string $taxonomy
		* @param string $gov
		* @param int|null $exclude_id
		* @return bool
		*/
		public static function slug_exists(string $slug, string $taxonomy, ?string $gov, ?int $exclude_id = null): bool {
			global $wpdb;
			
			$sql = "SELECT id FROM {$wpdb->prefix}fi_taxonomy WHERE slug = %s AND taxonomy = %s";
			$values = [$slug, $taxonomy];
			if ($taxonomy !== 'tag' && $gov) {
				$sql .= " AND gov = %s";
				$values[] = $gov;
			}
			
			if ($exclude_id) {
				$sql .= " AND id != %d";
				$values[] = $exclude_id;
			}
			
			$sql .= " LIMIT 1";
			
			return !empty($wpdb->get_var($wpdb->prepare($sql, $values)));
		}

		/**
		* Get taxonomy statistics
		* 
		* @param string|null $gov
		* @param string|null $taxonomy
		* @return array
		*/
		public static function get_stats(?string $gov = null, ?string $taxonomy = null): array {
			global $wpdb;
			
			$where_conditions = [];
			$values = [];
			
			if ($gov) {
				$where_conditions[] = "gov = %s";
				$values[] = $gov;
			}
			
			if ($taxonomy) {
				$where_conditions[] = "taxonomy = %s";
				$values[] = $taxonomy;
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
			$sql = "
				SELECT 
					COUNT(*) as total,
					COUNT(CASE WHEN taxonomy = 'party' THEN 1 END) as parties,
					COUNT(CASE WHEN taxonomy = 'tag' THEN 1 END) as tags,
					COUNT(CASE WHEN taxonomy = 'district' THEN 1 END) as districts
				FROM {$wpdb->prefix}fi_taxonomy
				{$where_clause}
			";
			
			if (!empty($values)) {
				$sql = $wpdb->prepare($sql, $values);
			}
			
			return (array) $wpdb->get_row($sql);
		}

		/**
		* Validate taxonomy data
		* 
		* @param array $data
		* @return array ['valid' => bool, 'errors' => array]
		*/
		public static function validate_taxonomy_data(array $data): array {
			$errors = [];
			$taxonomy = $data['taxonomy'] ?? '';
			$is_tag = ($taxonomy === 'tag');
			
			// Required fields
			if (empty($data['taxonomy'])) {
				$errors[] = 'Type is required';
			}
			
			if (empty($data['name'])) {
				$errors[] = 'Name is required';
			}
			
			if (!$is_tag && empty($data['gov'])) {
				$errors[] = 'Government code is required';
			}
			
			// Validate taxonomy
			if (!empty($data['taxonomy']) && !in_array($data['taxonomy'], ['party', 'tag', 'district'])) {
				$errors[] = 'Taxonomy must be party, tag, or district';
			}
			
			// Validate gov format
			if (!$is_tag && !empty($data['gov']) && !preg_match('/^[A-Z]{2}$/', $data['gov'])) {
				$errors[] = 'Government code must be 2 uppercase letters';
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
		* Get taxonomy tree structure
		* 
		* @param string|null $gov
		* @return array
		*/
		public static function get_taxonomy_tree(?string $gov = null): array {
			$args = ['gov' => $gov, 'per_page' => -1];
			$items = self::get_taxonomy($args);
			
			$tree = [
				'party' => [],
				'tag' => [],
				'district' => []
			];
			
			foreach ($items as $item) {
				$tree[$item->taxonomy][] = $item;
			}
			
			return $tree;
		}

		/**
		* Wrap fi_log in function so we can log this class only if necessary.
		*/
		public static function log(string $message, string $file = '', int $line = 0, string $level = 'debug'): void {
			fi_log($message, $file, $line, $level);
		}
	}
}

//namespace for global functions
namespace {

	/* Get taxonomies with optional filtering */
	function fi_taxonomies_get(array $args = []): array|int {
		return \FI\Core\Taxonomy::get($args);
	}

	/* Get a single taxonomy by ID */
	function fi_taxonomy_get(int $taxonomy_id): ?object {
		return \FI\Core\Taxonomy::get_by_id($taxonomy_id);
	}

	/* Get taxonomy item by slug */
	function fi_taxonomy_get_by_slug(string $slug, string $taxonomy, ?string $gov = null): ?object {
		return \FI\Core\Taxonomy::get_by_slug($slug, $taxonomy, $gov);
	}

	/* Save/Update taxonomy item */
	function fi_taxonomy_save(array $data, ?int $taxonomy_id = null): int|false {
		return \FI\Core\Taxonomy::save($data, $taxonomy_id);
	}

	/* Update taxonomy item */
	function fi_taxonomy_update(int $taxonomy_id, array $data): bool {
		return \FI\Core\Taxonomy::update($taxonomy_id, $data);
	}

	/* Delete taxonomy item */
	function fi_taxonomy_delete(int $taxonomy_id): bool {
		return \FI\Core\Taxonomy::delete($taxonomy_id);
	}

	/* Get taxonomy statistics */
	function fi_taxonomy_stats(?string $gov = null, ?string $taxonomy = null): array {
		return \FI\Core\Taxonomy::get_stats($gov, $taxonomy);
	}

	/* Get taxonomy tree structure */
	function fi_taxonomy_tree(?string $gov = null): array {
		return \FI\Core\Taxonomy::get_tree($gov);
	}

	/* Get vote tags */
	function fi_tags_get(?string $gov = null, array $filters = []): array {
		$args = array_merge($filters, ['taxonomy' => 'tag']);
		return \FI\Core\Taxonomy::get($args);
	}

	/* Get districts */
	function fi_districts_get(?string $gov = null, array $filters = []): array {
		$args = array_merge($filters, ['taxonomy' => 'district']);
		if ($gov) {
			$args['gov'] = $gov;
		}
		return \FI\Core\Taxonomy::get($args);
	}

	/* Get district by ID */
	function fi_district_get(int $district_id): ?object {
		$district = \FI\Core\Taxonomy::get_by_id($district_id);
		if (!$district) {
			// Summary: callers may legitimately reference a missing district id; return null instead of fatal.
			return null;
		}
		//Remove fields: legacy_id, taxonomy, meta, date_created, date_updated
		unset($district->legacy_id);
		unset($district->taxonomy);
		unset($district->meta);
		unset($district->date_created);
		unset($district->date_updated);
		//District name: AL 14th > Add property: name-short = 14th
		$district->name_short = preg_replace('/^[A-Z]{2} /', '', $district->name);
		return $district;
	}

	/**
	 * Resolve a LegiScan-style district string (e.g. "HD-TN-7") into our fi_taxonomy district id.
	 *
	 * Expected mapping:
	 * - Input: HD-TN-7 (or SD-TN-7)
	 * - Target slug contains: tn-7 (e.g. "tn-7th")
	 *
	 * @return int|null fi_taxonomy.id or null if not found / not parseable
	 */
	function fi_district_id_from_legiscan(string $district_raw, string $gov = 'US', ?string $chamber = null): ?int {
		global $wpdb;

		$district_raw = trim((string) $district_raw);
		if ($district_raw === '') {
			return null;
		}

		$gov = strtoupper(trim($gov));
		if ($gov === '') {
			$gov = 'US';
		}

		$chamber = $chamber ? strtoupper(trim((string) $chamber)) : null;

		// Try direct slug match first for any gov: fi_taxonomy.slug = lowercased(Legiscan district).
		$slug = strtolower($district_raw);
		$id = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}fi_taxonomy WHERE taxonomy = 'district' AND gov = %s AND slug = %s LIMIT 1",
			$gov,
			$slug
		));
		if ($id > 0) {
			return $id;
		}

		// Fallback: US Congress parsing (HD-STATE-NUM, at-large, slug like "tn-7").
		if ($gov !== 'US') {
			return null;
		}

		// Prefer explicit HD/SD format (US Congress):
		// - House: HD-TN-7 (numbered) OR HD-AK (at-large)
		// - Senate: SD-FL (no districts; should be NULL)
		$parts = array_values(array_filter(explode('-', $district_raw), static fn($p) => $p !== ''));
		$prefix = strtoupper((string) ($parts[0] ?? ''));
		$state = '';
		$num = '';
		if (count($parts) >= 2) {
			$state = strtoupper((string) ($parts[1] ?? ''));
			$num = (string) ($parts[2] ?? '');
		} elseif (preg_match('/\b([A-Z]{2})\b.*?(\d+)\b/', strtoupper($district_raw), $m)) {
			$state = strtoupper($m[1]);
			$num = (string) $m[2];
		}

		if (!preg_match('/^[A-Z]{2}$/', $state)) {
			return null;
		}

		// US Senators are elected at-large statewide; we never store districts for them.
		// LegiScan encodes this as "SD-FL", "SD-UT", etc. Treat as NULL to avoid bogus district creation.
		if ($gov === 'US' && ($chamber === 'S' || $prefix === 'SD')) {
			return null;
		}

		// Handle House at-large (e.g., "HD-AK", "HD-VT"). Only valid for states with exactly 1 congressional district.
		if ($gov === 'US' && ($chamber === 'H' || $chamber === null) && $prefix === 'HD' && ($num === '' || !preg_match('/^\d+$/', $num))) {
			$count = function_exists('fi_district_congressional_count') ? fi_district_congressional_count($state) : null;
			if ((int) $count !== 1) {
				// Not an at-large state; do not create bogus districts.
				return null;
			}

			$slug = strtolower($state . '-at-large');
			$existing = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}fi_taxonomy WHERE taxonomy='district' AND gov=%s AND slug=%s LIMIT 1",
				$gov,
				$slug
			));
			if ($existing) {
				return $existing;
			}

			$new_id = fi_taxonomy_save([
				'gov' => $gov,
				'taxonomy' => 'district',
				'name' => strtoupper($state) . ' At Large',
				'slug' => $slug,
				'meta' => [
					'created_from' => 'legiscan',
					'state' => strtoupper($state),
					'at_large' => 1,
				],
			]);
			return $new_id ? (int) $new_id : null;
		}

		// Numbered districts only beyond this point.
		if (!preg_match('/^\d+$/', (string) $num)) {
			return null;
		}

		$needle = strtolower($state . '-' . ltrim($num, '0')); // canonical slug: "tn-7"

		// 1) Exact match on canonical slug.
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT id, slug
			 FROM {$wpdb->prefix}fi_taxonomy
			 WHERE taxonomy = 'district' AND gov = %s AND slug = %s
			 LIMIT 1",
			$gov,
			$needle
		));
		if ($row && !empty($row->id)) {
			return (int) $row->id;
		}

		// 2) Back-compat: find older slugs like "tn-7th" and normalize them to "tn-7" (safe; references use ID).
		$like = $wpdb->esc_like($needle) . '%';
		$row2 = $wpdb->get_row($wpdb->prepare(
			"SELECT id, slug
			 FROM {$wpdb->prefix}fi_taxonomy
			 WHERE taxonomy = 'district' AND gov = %s AND slug LIKE %s
			 ORDER BY id DESC
			 LIMIT 1",
			$gov,
			$like
		));
		if ($row2 && !empty($row2->id)) {
			$existing_slug = (string) ($row2->slug ?? '');
			if ($existing_slug !== '' && $existing_slug !== $needle) {
				$exists_target = (int) $wpdb->get_var($wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}fi_taxonomy WHERE taxonomy='district' AND gov=%s AND slug=%s LIMIT 1",
					$gov,
					$needle
				));
				if (!$exists_target) {
					$wpdb->update(
						$wpdb->prefix . 'fi_taxonomy',
						['slug' => $needle],
						['id' => (int) $row2->id],
						['%s'],
						['%d']
					);
				}
			}
			return (int) $row2->id;
		}

		// 3) Not found: create it (ordinal suffix goes in name, not slug).
		$ordinal = function_exists('fi_format_ordinal') ? fi_format_ordinal((int) $num) : ((string) ((int) $num));
		$name = strtoupper($state) . ' ' . $ordinal;

		$new_id = fi_taxonomy_save([
			'gov' => $gov,
			'taxonomy' => 'district',
			'name' => $name,
			'slug' => $needle,
			'meta' => [
				'created_from' => 'legiscan',
				'state' => strtoupper($state),
				'number' => (int) $num,
			],
		]);

		return $new_id ? (int) $new_id : null;
	}

	/* Get tag URL for votes filtered by tag */
	function fi_tag_url(string $tag_slug, ?string $gov = null): string {
		if (!$gov) {
			// Try to get gov from tag
			$tag = \FI\Core\Taxonomy::get_by_slug($tag_slug, 'tag', null);
			if ($tag && !empty($tag->gov)) {
				$gov = strtolower($tag->gov);
			} else {
				$gov = 'us'; // Default
			}
		}
		return home_url('/' . strtolower($gov) . '/votes/issue/' . $tag_slug . '/');
	}

}