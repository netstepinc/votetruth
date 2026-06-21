<?php
namespace FI\Admin {

	if (!defined('ABSPATH')) exit;

	/**
	* Legiscan Integration for Admin Import Workflow
	* 
	* Replicates V2 workflow with granular control for importing legislators and votes.
	* Adapted to work with new database structure (fi_legislators, fi_votes tables).
	*/
	final class Legiscan {

		/**
		* Legiscan-specific cache wrapper
		* Handles 'legiscan/' prefix, days-to-seconds conversion, and never-expire logic
		* Stores JSON files directly (not serialized) for easy identification
		* 
		* @param string $key Cache key (will be prefixed with 'legiscan/')
		* @param string|array|false $data JSON string or array to cache, or false/empty to read
		* @param int $expires_days Expiration in days (0 = never expire, use cache if exists)
		* @return string|array|false Cached data or false/empty if not found/expired
		*/
		private static function legiscan_cache(string $key, $data = '', int $expires_days = 7) {
			if (!defined('FI_DIR_CACHE')) {
				return $data === '' ? false : false;
			}

			// Store API "reference" JSON files in a dedicated folder:
			// - FI_DIR_LEGISCAN_JSON/{key}.json
			$full_key = (string) $key;
			$full_key = ltrim($full_key, "/\\");
			if (substr($full_key, -5) !== '.json') {
				$full_key .= '.json';
			}

			$file = FI_DIR_LEGISCAN_JSON . $full_key;
			
			// Read from cache
			if ($data === '' || $data === false) {
				if (!file_exists($file)) {
					return false;
				}
				
				// For never-expire (0), always use cache if exists
				if ($expires_days === 0) {
					$contents = file_get_contents($file);
					return json_decode($contents, true);
				}
				
				// For time-based expiration, check age
				$cache_age = (time() - filemtime($file)) / (24 * 60 * 60);
				if ($cache_age < $expires_days) {
					$contents = file_get_contents($file);
					return json_decode($contents, true);
				}
				return false;
			}
			
			// Write to cache
			// Convert array to JSON string if needed
			$json_data = is_array($data) ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : $data;
			
			// Ensure directory exists
			$dir = dirname($file);
			if (!is_dir($dir)) {
				wp_mkdir_p($dir);
			}
			
			// Write JSON file directly (not using fi_cache to avoid serialization)
			file_put_contents($file, $json_data);
			
			return true;
		}

		/**
		* Get Legiscan API key from settings with fallback to constant
		*/
		private static function get_api_key(): ?string {
			return fi_get_api_key('legiscan_key', 'API_KEY_LEGISCAN');
		}

		/**
		* Get cached state list from Legiscan
		* Maps Legiscan state_id numbers to state codes
		*/
		private static function get_state_list(): array {
			//Legiscan states do not change, and if we add or remove a US state, we'll deal with it
			//Hard code array from Legiscan API	
			return [
				"AL", "AK", "AZ", "AR", "CA", "CO", "CT", "DE", "FL", "GA",
				"HI", "ID", "IL", "IN", "IA", "KS", "KY", "LA", "ME", "MD",
				"MA", "MI", "MN", "MS", "MO", "MT", "NE", "NV", "NH", "NJ",
				"NM", "NY", "NC", "ND", "OH", "OK", "OR", "PA", "RI", "SC",
				"SD", "TN", "TX", "UT", "VT", "VA", "WA", "WV", "WI", "WY",
				"DC", "US"
				];

			// Check cache (365 days)
			$cached = self::legiscan_cache('getStateList', '', 365);
			if ($cached !== false && isset($cached['states'])) {
				return $cached['states'];
			}
			
			// Fetch from API
			$api_key = self::get_api_key();
			if (!$api_key) {
				return [];
			}
			
			$url = 'https://api.legiscan.com/?key=' . urlencode($api_key) . '&op=getStateList';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			$response = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			
			if ($http_code === 200 && $response) {
				$data = json_decode($response, true);
				if ($data && isset($data['states'])) {
					// Cache the full response
					self::legiscan_cache('getStateList', $response, 365);
					return $data['states'];
				}
			}
			return [];
		}

		/*Convert legiscan state list to reference array: legiscan state_id => state_code
		* @param array $state_list Legiscan state list
		* @return array Reference array
		*/
		public static function abbreviations(): array {
			$state_list = self::get_state_list();
			return array_map(function($state) {
				return strtoupper($state);
			}, $state_list);
		}

		/**
		* Get abbreviation for a state
		* @param int $state_id Legiscan state ID (1-based)
		* @return string State abbreviation (2-letter code)
		*/
		public static function abbreviation($state_id): string {
			$abbreviations = self::abbreviations();
			$index = (int) $state_id - 1; // Convert 1-based to 0-based array index
			
			if (!isset($abbreviations[$index])) {
				$url = isset($_SERVER['REQUEST_URI']) ? esc_html($_SERVER['REQUEST_URI']) : 'N/A';
				wp_die(
					sprintf(
						'<h1>Legiscan State ID Error</h1><p><strong>Invalid state_id:</strong> %d</p><p><strong>URL:</strong> %s</p><p>Please contact <a href="mailto:%s">%s</a> with a screenshot of this error.</p>',
						$state_id,
						$url,
						defined('FI_AUTHOR_EMAIL') ? FI_AUTHOR_EMAIL : 'support@example.com',
						defined('FI_AUTHOR') ? FI_AUTHOR : 'Support'
					),
					'Legiscan State ID Error',
					['response' => 500]
				);
			}
			
			return $abbreviations[$index];
		}

		/**
		* Make API request to Legiscan (V2-compatible method signature)
		* 
		* @param array $args {
		*   op: Operation name (getDatasetList, getDataset, getStateList, etc.)
		*   key: Cache key for response
		*   expires: Cache expiration in days (0 = no cache, 365 = 1 year)
		*   params: Array of URL parameters
		* }
		* @return array|false API response or false on error
		*/
		public function getLegiscan(array $args): array|false {
			$api_key = self::get_api_key();
			if (!$api_key) {
				return false;
			}
			
			$op = strtolower($args['op'] ?? '');
			$scope = $args['scope'] ?? ($args['params']['gov'] ?? $args['params']['state'] ?? 'global');
			
			// Build human-readable cache key
			$cache_key = $args['key'] ?? '';
			if (empty($cache_key)) {
				// Build key from operation and params
				$key_parts = [$op];
				if (!empty($args['params'])) {
					foreach ($args['params'] as $key => $val) {
						$key_parts[] = $key . '-' . $val;
					}
				}
				$cache_key = implode('_', $key_parts);
			}

			// All default cache expiration is 7 days
			$default_expires = 7;
			$expires = $args['expires'] ?? $default_expires;
			
			// Check cache
			$cached = self::legiscan_cache($cache_key, '', $expires);
			if ($cached !== false) {
				return $cached;
			}
			
			// Build API URL
			$url = 'https://api.legiscan.com/?key=' . urlencode($api_key) . '&op=' . $op;
			if (!empty($args['params'])) {
				foreach ($args['params'] as $key => $val) {
					$url .= '&' . urlencode($key) . '=' . urlencode($val);
				}
			}
			
			// Make request
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_TIMEOUT, 300);
			$response = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			
			if ($http_code !== 200 || !$response) {
				return false;
			}
			
			$data = json_decode($response, true);
			if (!$data || (isset($data['status']) && $data['status'] !== 'OK')) {
				return false;
			}
			
			// Cache response (always cache, expires controls when to use it)
			self::legiscan_cache($cache_key, $response, $expires);
			
			// Process each type of request (V2-style)
			switch ($op):
				case 'getstatelist':
				case 'getdatasetlist':
					return $data;
				break;
				
				case 'getdataset':
					if (isset($data['dataset']['zip'])) {
						$extract_dir = $this->unzip(FI_DIR_LEGISCAN . $cache_key, $data['dataset']['zip']);
						if ($extract_dir) {
							return $this->dir_tree(FI_DIR_LEGISCAN . $cache_key);
						}
					}
					return false;
				break;
			endswitch;
			
			return $data;
		}

		/**
		* Unzip dataset
		* 
		* @param string $zip_dir Directory to extract to (without trailing slash)
		* @param string $zip_data Base64-encoded ZIP data
		* @return string|false Path to extracted directory or false on error
		*/
		public function unzip(string $zip_dir, string $zip_data): string|false {
			// Ensure directory path doesn't have trailing slash for zip file naming
			$zip_dir = rtrim($zip_dir, '/\\');
			$zip_file = $zip_dir . '.zip';
			
			// Convert JSON ZIP data to zip file
			if (!file_exists($zip_file)) {
				$blob = base64_decode($zip_data);
				if ($blob === false) {
					return false;
				}
				
				// Ensure parent directory exists
				$parent_dir = dirname($zip_file);
				if (!is_dir($parent_dir)) {
					wp_mkdir_p($parent_dir);
				}
				
				$written = file_put_contents($zip_file, $blob);
				unset($blob);
				
				if ($written === false) {
					return false;
				}
			}
			
			// Extract file
			if (file_exists($zip_file)) {
				if (!is_dir($zip_dir)) {
					wp_mkdir_p($zip_dir);
					
					$zip = new \ZipArchive;
					$result = $zip->open($zip_file);
					if ($result === true) {
						$zip->extractTo($zip_dir);
						$zip->close();
					} else {
						error_log("Legiscan unzip error: Failed to open ZIP file (code: {$result})");
						return false;
					}
				}
			}
			
			// Check if extraction was successful
			if (is_dir($zip_dir) && is_readable($zip_dir)) {
				return $zip_dir;
			}
			
			return false;
		}

		/**
		* Traverse directory and inventory files (V2-compatible)
		* 
		* @param string $path Path to extracted directory
		* @return array Array with 'bill', 'people', 'vote' keys containing file paths
		*/
		public function dir_tree(string $path): array {
			$files = [
				'bill' => [],
				'people' => [],
				'vote' => [],
			];
			
			if (!is_dir($path) || !is_readable($path)) {
				return $files;
			}
			
			try {
				$objects = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
					\RecursiveIteratorIterator::SELF_FIRST
				);
				
				foreach ($objects as $name => $object) {
					// Skip directories
					if ($object->isDir()) {
						continue;
					}
					
					// Skip non-JSON files and unwanted file types
					$basename = basename($name);
					$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
					
					if ($extension !== 'json') {
						continue;
					}
					
					// Skip unwanted files (md5 checksums, markdown, LICENSE)
					if (substr($basename, -3, 3) === 'md5' || 
						substr($basename, -2, 2) === 'md' || 
						stripos($basename, 'LICENSE') !== false) {
						continue;
					}
					
					// Categorize files by pattern (case-insensitive)
					$name_lower = strtolower($name);
					
					if (strpos($name_lower, 'bill') !== false) {
						$files['bill'][] = $name;
					} elseif (strpos($name_lower, 'people') !== false || strpos($name_lower, 'person') !== false) {
						$files['people'][] = $name;
					} elseif (strpos($name_lower, 'vote') !== false || strpos($name_lower, 'rollcall') !== false || strpos($name_lower, 'roll_call') !== false) {
						$files['vote'][] = $name;
					}
				}
			} catch (\Exception $e) {
				// Log error but return what we have so far
				error_log("Legiscan dir_tree error: " . $e->getMessage());
			}
			
			return $files;
		}

		/**
		* List existing people/legislators (V2-compatible)
		* Returns array keyed by legiscan_id (people_id)
		* 
		* @param array $args Optional arguments (unused for now)
		* @return array Array of legislators keyed by legiscan_id
		*/
		public function list_people(array $args = []): array {
			global $wpdb;
			
			$people = [];
			$legislators = $wpdb->get_results(
				"SELECT id, legiscan_id, display_name FROM {$wpdb->prefix}fi_legislators 
				WHERE legiscan_id IS NOT NULL AND legiscan_id != 0",
				OBJECT
			);
			
			foreach ($legislators as $leg) {
				if ($leg->legiscan_id) {
					$people[$leg->legiscan_id] = [
						'id' => $leg->id,
						'name' => $leg->display_name,
						'status' => 'publish', // All in DB are considered published
					];
				}
			}
			return $people;
		}

		/**
		* Create or update a legislator (V2-compatible)
		* Adapted to use new database structure
		* 
		* @param array $args {
		*   person: Array of person data from Legiscan
		*   session: Array of session data from Legiscan
		* }
		* @return int|false Legislator ID or false on error
		*/
		public function create_legislator(array $args): int|false {
			global $wpdb;
			
			$person = $args['person'] ?? [];
			$session = $args['session'] ?? [];
			
			$legiscan_id = $person['people_id'] ?? null;
			if (!$legiscan_id) {
				return false;
			}
			
			// Resolve gov + state_code robustly.
			// Summary of choices:
			// - LegiScan uses numeric state_id (1-based) for both datasets and people; getStateList is the mapping source.
			// - For US people, we MUST derive member state from district (HD-AZ-7 => AZ), not from state_id (which is US=52).
			$dataset_state_id = $session['state_id'] ?? null; // numeric for datasets
			$dataset_gov = (string) ($args['gov'] ?? ''); // optional override from caller
			$dataset_gov = strtoupper(trim($dataset_gov));
			if ($dataset_gov === '' && is_numeric($dataset_state_id) && (int) $dataset_state_id > 0) {
				$dataset_gov = self::abbreviation((int) $dataset_state_id);
			}
			if ($dataset_gov === '') {
				$dataset_gov = 'US';
			}

			$gov = $dataset_gov;

			// State code (used primarily for US member records): prefer parsing district prefix.
			$state_code = null;
			$district_raw = strtoupper((string) ($person['district'] ?? ''));
			if ($district_raw !== '' && preg_match('/^(?:HD|SD)-([A-Z]{2})\b/', $district_raw, $m)) {
				$state_code = $m[1];
			}
			// If we still don't have state_code, and we're importing a state gov dataset, use that gov.
			if (!$state_code && $gov !== 'US' && preg_match('/^[A-Z]{2}$/', $gov)) {
				$state_code = $gov;
			}
			
			$first_name = $person['first_name'] ?? '';
			$middle_name = $person['middle_name'] ?? '';
			$last_name = $person['last_name'] ?? '';
			$display_name = trim("{$first_name} {$middle_name} {$last_name}");
			
			if (!$first_name || !$last_name) {
				return false;
			}
			
			// Check for existing legislator by legiscan_id
			$existing = $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}fi_legislators WHERE legiscan_id = %d LIMIT 1",
				$legiscan_id
			));
			
			// For Congress, also check by Bioguide ID
			if (!$existing && $gov === 'US' && !empty($person['bioguide_id'])) {
				$existing = $wpdb->get_var($wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}fi_legislators WHERE bioguide_id = %s LIMIT 1",
					$person['bioguide_id']
				));
			}
			
			// Prepare data for save (slug field removed - using ID as slug)
			$data = [
				'first_name' => $first_name,
				'middle_name' => $middle_name ?: null,
				'last_name' => $last_name,
				'display_name' => $display_name,
				'legiscan_id' => $legiscan_id,
			];
			
			// Add external IDs
			if (!empty($person['bioguide_id'])) {
				$data['bioguide_id'] = $person['bioguide_id'];
			}
			if (!empty($person['votesmart_id'])) {
				$data['votesmart_id'] = $person['votesmart_id'];
			}
			if (!empty($person['govtrack_id'])) {
				$data['govtrack_id'] = $person['govtrack_id'];
			}
			if (!empty($person['ballotpedia'])) {
				$data['ballotpedia_id'] = $person['ballotpedia'];
			}
			
			// Meta data (array; encoded by fi_legislator_save)
			$meta = [
				'legiscan_data' => $person,
				'opensecrets_id' => $person['opensecrets_id'] ?? null,
				'url_wikipedia' => $person['url_wikipedia'] ?? null,
				'knowwho_pid' => $person['knowwho_pid'] ?? null,
				'ftm_eid' => $person['ftm_eid'] ?? null,
			];
			$data['meta'] = $meta;
			
			// Save legislator
			$legislator_id = fi_legislator_save($data, $existing);
			
			if ($legislator_id && !empty($session)) {
				// Create/update legislator session
				$session_id_legiscan = $session['session_id'] ?? null;
				if ($session_id_legiscan) {
					// Find session by legiscan_id column
					$session = fi_session_get_by_legiscan_id((int) $session_id_legiscan, $gov);
					$session_id = $session ? $session->id : null;
					
					if ($session_id) {
						//CHAMBERFLAG
						// Determine chamber
						$role = $person['role'] ?? '';
						$chamber = 'H';
						if (stripos($role, 'sen') !== false) {
							$chamber = 'S';
						}
						
						// Get or create district taxonomy
						$district = $person['district'] ?? null;
						$district_id = null;
						if ($gov === 'US' && $chamber === 'S') {
							// US Senators are at-large; do not store districts.
							$district_id = null;
						} elseif (!empty($district)) {
							$district_id = function_exists('fi_district_id_from_legiscan')
								? fi_district_id_from_legiscan((string) $district, (string) $gov, (string) $chamber)
								: null;
							// Continue without district if not found
						}
						
						// Create/update legislator session record (use core save API)
						$session_data = [
							'legislator_id' => $legislator_id,
							'session_id' => $session_id,
							'gov' => $gov,
							'chamber' => $chamber,
							'party' => strtoupper($person['party'] ?? ''),
							'district' => $district_id,
							'state' => $state_code,
						];

						$existing_session_id = fi_legislator_session_id($legislator_id, $session_id);
						fi_legislator_session_save($session_data, $existing_session_id);

						// Apply the same LegiScan-local->FI field mapping used by the Legislator Edit "API Fetch/Add" flow.
						// Summary: populate available fields automatically on import, but only when our values are missing.
						if (class_exists('\\FI\\Core\\ApiIntegration') && method_exists('\\FI\\Core\\ApiIntegration', 'build_updates_for_legislator')
							&& function_exists('fi_admin_legislators_apply_api_updates')) {
							$updates = \FI\Core\ApiIntegration::build_updates_for_legislator((int) $legislator_id, 'legiscan_local', is_array($person) ? $person : [], true);
							if (!empty($updates) && is_array($updates)) {
								fi_admin_legislators_apply_api_updates((int) $legislator_id, 'legiscan_local', $updates);
							}
						}
					}
				}
			}
			
			return $legislator_id ?: false;
		}

		/**
		* List existing votes (V2-compatible)
		* Returns array keyed by legiscan vote_id (roll_call_id)
		* 
		* @param array $args Optional arguments (unused for now)
		* @return array Array of votes keyed by legiscan vote_id
		*/
		public function list_votes(array $args = []): array {
			global $wpdb;
			
			$session_id = $args['session_id'] ?? null;
			$votes = [];
			
			$where = ['legiscan_rcid IS NOT NULL'];
			$where_values = [];
			
			if ($session_id) {
				$where[] = 'session_id = %d';
				$where_values[] = $session_id;
			}
			
			$sql = "SELECT id, legiscan_bid, legiscan_rcid FROM {$wpdb->prefix}fi_votes 
				WHERE " . implode(' AND ', $where);
			
			if (!empty($where_values)) {
				$sql = $wpdb->prepare($sql, $where_values);
			}
			
			$vote_records = $wpdb->get_results($sql, OBJECT);
			
			foreach ($vote_records as $vote) {
				if ($vote->legiscan_rcid) {
					$votes[$vote->legiscan_rcid] = [
						'id' => $vote->id,
						'legiscan_bid' => $vote->legiscan_bid,
						'legiscan_rcid' => $vote->legiscan_rcid,
					];
				}
			}
			
			return $votes;
		}

		/**
		* Create or update a vote (V2-compatible)
		* Adapted to use new database structure
		* 
		* @param array $args {
		*   roll_call_id: Legiscan roll_call_id
		*   bill: Array of bill data from Legiscan
		*   roll_call: Array of roll_call data from Legiscan
		* }
		* @return int|false Vote ID (fi_votes.id) or false on error
		*/
		public static function create_vote(array $args): int|false {
			global $wpdb;
			
			$roll_call_id_legiscan = $args['roll_call_id'] ?? null;
			$bill = $args['bill'] ?? [];
			$roll_call = $args['roll_call'] ?? [];
			
			// Debug: log entry into create_vote
			//fi_log(sprintf('create_vote ENTRY: roll_call_id=%s bill_session_id=%s bill_state=%s',$roll_call_id_legiscan ?? 'null',$bill['session_id'] ?? 'null',$bill['state'] ?? 'null'),__FILE__,__LINE__);
			
			if (!$roll_call_id_legiscan) {
				return false;
			}
			
			// Get session
			$session_id_legiscan = $bill['session_id'] ?? null;
			if (!$session_id_legiscan) {
				return false;
			}
			
			// Get state code
			$state_code = $bill['state'] ?? null;
			if (!$state_code || strlen($state_code) > 2) {
				$state_code = 'US'; // Default to Congress if unclear
			}
			$gov = strtoupper($state_code);
			
			// Find session by legiscan_id column
			$session = fi_session_get_by_legiscan_id((int) $session_id_legiscan, $gov);
			$session_id = $session ? $session->id : null;
			
			if (!$session_id) {
				return false;
			}
			//CHAMBERFLAG
			// Determine chamber from vote chamber (roll_call), not bill body — bills can be amended/voted in either chamber
			$chamber = strtoupper((string) ($roll_call['chamber'] ?? ''));
			if ($chamber === '') {
				$chamber = strtoupper((string) ($bill['body'] ?? ''));
			}
			$chamber = ($chamber === 'S') ? 'S' : 'H';
			
			$title = $roll_call['desc'] ?? $bill['title'] ?? '';
			$bill_number = $bill['bill_number'] ?? '';
			
			// Get vote date: prefer roll_call date, fallback to matching vote in bill's votes array
			$date_voted = $roll_call['date'] ?? null;
			if (!$date_voted && isset($bill['votes']) && is_array($bill['votes'])) {
				// Search bill votes array for matching roll_call_id
				foreach ($bill['votes'] as $vote) {
					if (isset($vote['roll_call_id']) && (int)$vote['roll_call_id'] === (int)$roll_call_id_legiscan) {
						$date_voted = $vote['date'] ?? null;
						break;
					}
				}
			}
			
			if (!$title || !$date_voted) {
				return false;
			}
				
			// Extract rollcall number from description (e.g., "On Passage RC# 12" -> "12") - Example: On the Motion to Proceed S.J.Res. 117 RC# 295 = 295
			$rollcall_number = '';
			$roll_call_desc = $roll_call['desc'] ?? '';
			if (preg_match('/RC#?\s*(\d+)/i', $roll_call_desc, $matches)) {
				$rollcall_number = $matches[1];
			}
//TODO: State staff using Legiscan RC ID instead of the governmet number. We need to do better moving forward. Leave it blank if not found.


			// Build slug
			$slug_base = sanitize_title($title);
			$slug = $slug_base;
			$counter = 1;
			while ($wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}fi_votes WHERE session_id = %d AND slug = %s LIMIT 1",
				$session_id, $slug
			))) {
				$slug = $slug_base . '-' . $counter;
				$counter++;
			}
			
			// Check if vote exists by legiscan_rcid column
			$existing_vote = fi_vote_get_by_legiscan_rcid((int) $roll_call_id_legiscan, $session_id);
			$existing = $existing_vote ? $existing_vote->id : null;
			
			// Prepare rollcall data (V3 contract):
			// - fi_votes.rollcall_data stores JSON map: fi_legislators.id => Y/N/P/X
			// - fi_voterc stores individual records keyed by fi_legislators.id
			$rollcall_votes = $roll_call['votes'] ?? [];
			$rollcall_data_by_people = []; // people_id => cast (temporary, for mapping)
			$rollcall_data_for_table = []; // people_id + cast (temporary, for insertion)
			
			foreach ($rollcall_votes as $person_vote) {
				$people_id = $person_vote['people_id'] ?? null;
				$vote_text = $person_vote['vote_text'] ?? '';
				$vote_id = $person_vote['vote_id'] ?? null;
				
				if ($people_id) {
					// Normalize vote text to 4-state values: Y, N, P, X
					$cast = 'X'; // Default to 'X' (Not Voted)
					// Prefer vote_id when available (stable), fallback to vote_text.
					// Common mapping: 1=Yea, 2=Nay, 3=NV, 4=Absent
					if ($vote_id !== null && $vote_id !== '') {
						$vote_id_int = (int) $vote_id;
						if ($vote_id_int === 1) {
							$cast = 'Y';
						} elseif ($vote_id_int === 2) {
							$cast = 'N';
						} elseif ($vote_id_int === 3) {
							$cast = 'X';
						} elseif ($vote_id_int === 4) {
							$cast = 'X';
						}
					}

					if ($cast === 'X') {
						if (stripos($vote_text, 'yea') !== false) {
							$cast = 'Y';
						} elseif (stripos($vote_text, 'nay') !== false) {
							$cast = 'N';
						} elseif (stripos($vote_text, 'present') !== false || stripos($vote_text, 'paired') !== false) {
							$cast = 'P';
						} elseif (stripos($vote_text, 'abstain') !== false) {
							$cast = 'P'; // Abstain maps to Present
						} elseif (stripos($vote_text, 'excused') !== false) {
							$cast = 'X'; // Excused maps to Not Voted
						} elseif (stripos($vote_text, 'nv') !== false) {
							$cast = 'X'; // NV maps to X (Not Voted)
						} elseif (stripos($vote_text, 'absent') !== false) {
							$cast = 'X'; // Absent maps to Not Voted
						}
					}
					
					$rollcall_data_by_people[(int) $people_id] = $cast;
					$rollcall_data_for_table[] = [
						'people_id' => (int) $people_id,
						'cast' => $cast,
					];
				}
			}

			// Retrieve the people_id => fi_legislators.id mapping from the cache.
			// Use $session_id (Freedom Index session ID, resolved at line 657) and 
			// $session_id_legiscan (LegiScan session ID, resolved at line 643).
			$cacheKeyXREF = 'legiscan/_people/xref_fis-' . $session_id . '_lid-' . $session_id_legiscan;
			$legislator_id_map = fi_cache($cacheKeyXREF, '', 86400, true);
			
			// Debug logging for cache lookup
			//fi_log(sprintf('create_vote CACHE: key=%s fi_session=%s ls_session=%s hit=%s count=%d',$cacheKeyXREF,$session_id,$session_id_legiscan,(!empty($legislator_id_map) ? 'true' : 'false'),(is_array($legislator_id_map) ? count($legislator_id_map) : 0)),__FILE__,__LINE__);

			// Try the old way if the cache is not found.
			if (!$legislator_id_map) {

				// Resolve people_id -> fi_legislators.id mapping in one query and build rollcall snapshot
				$people_ids = array_values(array_unique(array_keys($rollcall_data_by_people)));
				$legislator_id_map = [];
				if (!empty($people_ids)) {
					$placeholders = implode(',', array_fill(0, count($people_ids), '%d'));
					$sql = "SELECT id, legiscan_id FROM {$wpdb->prefix}fi_legislators WHERE legiscan_id IN ({$placeholders})";
					$rows = $wpdb->get_results($wpdb->prepare($sql, $people_ids));
					foreach ($rows as $row) {
						$legislator_id_map[(int) $row->legiscan_id] = (int) $row->id;
					}
				}

				$missing_people_ids = [];
				foreach ($people_ids as $pid) {
					if (empty($legislator_id_map[(int) $pid])) {
						$missing_people_ids[] = (int) $pid;
					}
				}
/* ROLLCALL includes delegates from territories and WA-DC. 
We've already filtered them out in the UI and made sure all the state legislators are in the system.
Therefore we will not throw an error if we miss a mapping and just ignore the missing legislators.

				// Full stop on mapping failures - no workarounds.
				if (!empty($missing_people_ids)) {
					// Debug: Log to both FI log and PHP error log
					$debug_msg = sprintf(
						'[FI DEBUG] create_vote MISS: cacheKey=%s fi_session=%s ls_session=%s missing_ids=%s',
						$cacheKeyXREF,
						$session_id,
						$session_id_legiscan,
						implode(',', $missing_people_ids)
					);
					fi_log($debug_msg, __FILE__, __LINE__, 'error');
					
					throw new \RuntimeException(
						'Legiscan import halted: missing fi_legislators mapping for people_id(s): ' . implode(', ', $missing_people_ids)
					);
				}
*/
			}

			$rollcall_data = []; // fi_legislators.id => cast
			foreach ($rollcall_data_by_people as $pid => $cast) {
				$legislator_id = $legislator_id_map[(int) $pid] ?? 0;
				if ($legislator_id) {
					$rollcall_data[(int) $legislator_id] = $cast;
				}
			}
			
			// Get rollcall URL from roll_call's state_link (from compiled data)
			// The state_link comes from bill:roll_calls[]:state_link where roll_call_id matches
			$url_rollcall = $roll_call['state_link'] ?? null;
			
			// Start with copies of bill and roll_call data for processing
			$bill_for_meta = $bill;
			$roll_call_for_meta = $roll_call;
			
			// Remove fields that go to dedicated columns (to avoid redundancy)
			unset($bill_for_meta['bill_id']); // Goes to legiscan_bid column
			unset($bill_for_meta['bill_number']); // Goes to bill_number column
			unset($bill_for_meta['state']); // Goes to gov column
			unset($bill_for_meta['session_id']); // Will be saved as legiscan_session_id in meta
			unset($roll_call_for_meta['roll_call_id']); // Goes to legiscan_rcid column
			unset($roll_call_for_meta['bill_id']); // Goes to legiscan_bid column
			unset($roll_call_for_meta['date']); // Goes to date_voted column
			unset($roll_call_for_meta['chamber']); // Goes to chamber column
			unset($roll_call_for_meta['votes']); // Goes to rollcall_data column
			
			// Extract vote counts with votes_ prefix
			$votes_yea = $roll_call['yea'] ?? null;
			$votes_nay = $roll_call['nay'] ?? null;
			$votes_nv = $roll_call['nv'] ?? null;
			$votes_absent = $roll_call['absent'] ?? null;
			$votes_total = $roll_call['total'] ?? null;
			
			// Remove vote counts from roll_call_for_meta (they'll be saved separately)
			unset($roll_call_for_meta['yea']);
			unset($roll_call_for_meta['nay']);
			unset($roll_call_for_meta['nv']);
			unset($roll_call_for_meta['absent']);
			unset($roll_call_for_meta['total']);
			
			// Extract and remove mapped fields from bill_for_meta
			$bill_title = $bill_for_meta['title'] ?? null;
			$bill_description = $bill_for_meta['description'] ?? null;
			$url_bill = $bill_for_meta['state_link'] ?? $bill_for_meta['url'] ?? null;
			$url_legiscan = $bill_for_meta['url'] ?? null;
			$legiscan_session_id = $bill['session_id'] ?? null; // Use original bill array for session_id
			
			unset($bill_for_meta['title']);
			unset($bill_for_meta['description']);
			unset($bill_for_meta['state_link']);
			unset($bill_for_meta['url']);
			
			// Extract and remove mapped fields from roll_call_for_meta
			$vote_title = $roll_call_for_meta['desc'] ?? null;
			unset($roll_call_for_meta['desc']);
			unset($roll_call_for_meta['state_link']); // Already extracted as url_rollcall
			
			// Build meta structure (array; encoded by fi_vote_save)
			$meta = [];
			
			// Add mapped meta fields at top level
			if ($bill_title !== null) {
				$meta['bill_title'] = $bill_title;
			}
			if ($bill_description !== null) {
				$meta['bill_description'] = $bill_description;
			}
			if ($url_bill !== null) {
				$meta['url_bill'] = $url_bill;
			}
			if ($url_rollcall !== null) {
				$meta['url_rollcall'] = $url_rollcall;
			}
			if ($url_legiscan !== null) {
				$meta['url_legiscan'] = $url_legiscan;
			}
			if ($vote_title !== null) {
				$meta['vote_title'] = $vote_title;
			}
			
			// Add vote counts with votes_ prefix
			if ($votes_yea !== null) {
				$meta['votes_yea'] = (int) $votes_yea;
			}
			if ($votes_nay !== null) {
				$meta['votes_nay'] = (int) $votes_nay;
			}
			if ($votes_nv !== null) {
				$meta['votes_nv'] = (int) $votes_nv;
			}
			if ($votes_absent !== null) {
				$meta['votes_absent'] = (int) $votes_absent;
			}
			if ($votes_total !== null) {
				$meta['votes_total'] = (int) $votes_total;
			}
			
			// Add legiscan_session_id with prefix (ambiguous with fi_sessions:id)
			if ($legiscan_session_id !== null) {
				$meta['legiscan_session_id'] = (int) $legiscan_session_id;
			}
			
			// Add remaining unmapped Legiscan data in 'legiscan' sub-array
			$legiscan_remaining = [];
			if (!empty($bill_for_meta)) {
				$legiscan_remaining['bill'] = $bill_for_meta;
			}
			if (!empty($roll_call_for_meta)) {
				$legiscan_remaining['roll_call'] = $roll_call_for_meta;
			}
			if (!empty($legiscan_remaining)) {
				$meta['legiscan'] = $legiscan_remaining;
			}
			
			// Prepare vote data (for fi_votes table)
			// Use roll_call:desc as title if title is empty (for import)
			$vote_title_for_column = $title ?: $vote_title;
			
			$vote_data = [
				'session_id' => $session_id,
				'legiscan_bid' => (int) ($bill['bill_id'] ?? 0),
				'legiscan_rcid' => (int) $roll_call_id_legiscan,
				'gov' => $gov,
				'chamber' => $chamber,
				'title' => $vote_title_for_column, // Use roll_call:desc if title is empty
				'slug' => $slug,
				'bill_number' => $bill_number ?: $vote_title_for_column,
				'constitutional' => 'U',
				'rollcall_number' => $rollcall_number,
				'rollcall_data' => !empty($rollcall_data) ? $rollcall_data : null, // JSON data for rollcall_data column (will be encoded by Votes::save)
				'date_voted' => $date_voted,
				'meta' => $meta,
				'status' => 'draft', // Imported votes start as draft
			];
			
			// Save vote
			$vote_id = fi_vote_save($vote_data, $existing);
			
			// Populate fi_voterc table with individual rollcall records
			if ($vote_id && !empty($rollcall_data_for_table)) {
				// At this point, we already validated that all people_id values map to fi_legislators.id.
				foreach ($rollcall_data_for_table as $rollcall_entry) {
					$people_id = (int) ($rollcall_entry['people_id'] ?? 0);
					$cast = (string) ($rollcall_entry['cast'] ?? 'X');

					if(isset($legislator_id_map[$people_id])){
						$legislator_id = (int) ($legislator_id_map[$people_id] ?? 0);

						/* We've already validated the state legislators exist. Do not stop on included DC/Territory that are not in the legislator map.
						if (!$legislator_id) {
							// Full stop (should be unreachable because we validated earlier)
							throw new \RuntimeException('Legiscan import halted: missing fi_legislators mapping for people_id ' . $people_id);
						}
						*/
						fi_rollcall_save([
							'vote_id' => $vote_id,
							'legislator_id' => $legislator_id,
							'cast' => $cast,
							'is_override' => 0,
						]);
					}
				}

				// Audit: compare LegiScan summary vs snapshot vs inserted DB rollcalls (non-overrides only).
				$legiscan_yea = (int) ($votes_yea ?? 0);
				$legiscan_nay = (int) ($votes_nay ?? 0);
				$legiscan_nv = (int) ($votes_nv ?? 0);
				$legiscan_absent = (int) ($votes_absent ?? 0);
				$legiscan_total = (int) ($votes_total ?? 0);

				$expected_not_voted = $legiscan_nv + $legiscan_absent; // NV+Absent rolled into Not Voted (X) for audit

				// Snapshot summary (what we got from LegiScan votes[] after mapping to Y/N/P/X).
				$snapshot_counts = ['Y' => 0, 'N' => 0, 'P' => 0, 'X' => 0, 'total' => 0];
				if (!empty($rollcall_data) && is_array($rollcall_data)) {
					foreach ($rollcall_data as $_legislator_id => $_cast) {
						$_c = fi_rollcall_cast_normalize((string) $_cast);
						if (isset($snapshot_counts[$_c])) {
							$snapshot_counts[$_c]++;
						}
						$snapshot_counts['total']++;
					}
				}

				// DB summary (non-overrides only, so future manual overrides won't break the audit record).
				$db_summary = fi_rollcall_summary((int) $vote_id, 0);

				$audit = [
					'time' => time(),
					'legiscan' => [
						'total' => $legiscan_total,
						'yea' => $legiscan_yea,
						'nay' => $legiscan_nay,
						'nv' => $legiscan_nv,
						'absent' => $legiscan_absent,
						'expected_not_voted' => $expected_not_voted,
					],
					'snapshot' => $snapshot_counts,
					'db' => [
						'total' => (int) ($db_summary['total_votes'] ?? 0),
						'Y' => (int) ($db_summary['yes'] ?? 0),
						'N' => (int) ($db_summary['no'] ?? 0),
						'P' => (int) ($db_summary['present'] ?? 0),
						'X' => (int) ($db_summary['not_voting'] ?? 0),
						'overrides' => (int) ($db_summary['overrides'] ?? 0),
						'missing_legislators' => 0,
					],
				];

				$audit['matches_legiscan'] = (
					$audit['db']['Y'] === $legiscan_yea &&
					$audit['db']['N'] === $legiscan_nay &&
					$audit['db']['X'] === $expected_not_voted &&
					$audit['db']['total'] === $legiscan_total
				);

				$audit['matches_snapshot'] = (
					$audit['snapshot']['Y'] === $audit['db']['Y'] &&
					$audit['snapshot']['N'] === $audit['db']['N'] &&
					$audit['snapshot']['P'] === $audit['db']['P'] &&
					$audit['snapshot']['X'] === $audit['db']['X'] &&
					$audit['snapshot']['total'] === $audit['db']['total']
				);

				$audit['matches'] = ($audit['matches_legiscan'] && $audit['matches_snapshot']);

				// Store audit in vote meta (preserve LegiScan raw counts separately as-is).
				fi_vote_meta_merge((int) $vote_id, [
					'legiscan_rollcall_audit' => $audit,
				]);
			}
			
			return $vote_id ?: false;
		}

		/**
		* Static API request method (from core class)
		* 
		* @param array $args Request arguments
		* @return array|false API response or false on error
		*/
		public static function api_request(array $args): array|false {
			$api_key = self::get_api_key();
			if (!$api_key) {
				return false;
			}
			$op = strtolower($args['op'] ?? '');
			$expires = $args['expires'] ?? 30;
			
			// Build human-readable cache key
			$cache_key = $args['key'] ?? '';
			if (empty($cache_key)) {
				// Build key from operation and params
				$key_parts = [$op];
				if (!empty($args['params'])) {
					foreach ($args['params'] as $key => $val) {
						$key_parts[] = $key . '-' . $val;
					}
				}
				$cache_key = implode('_', $key_parts);
			}
			
			// Check cache
			$cached = self::legiscan_cache($cache_key, '', $expires);
			if ($cached !== false) {
				return $cached;
				self::log('Cache Hit: '.$cache_key,__FILE__,__LINE__);
			}else{
				self::log('API Fetch: '.$cache_key,__FILE__,__LINE__,);
			}
		
			// Build API URL
			$url = 'https://api.legiscan.com/?key=' . urlencode($api_key) . '&op=' . $op;
			if (!empty($args['params'])) {
				foreach ($args['params'] as $key => $val) {
					$url .= '&' . urlencode($key) . '=' . urlencode($val);
				}
			}
			
			// Make request
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_TIMEOUT, 300);
			$response = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			
			if ($http_code !== 200 || !$response) {
				return false;
			}
			
			$data = json_decode($response, true);
			if (!$data || (isset($data['status']) && $data['status'] !== 'OK')) {
				return false;
			}
			
			// Cache response (always cache, expires controls when to use it)
			self::legiscan_cache($cache_key, $response, $expires);
			
			return $data;
		}

		/**
		* Get list of available datasets for a state (static method from core class)
		* 
		* @param string $state State code (e.g., 'WI', 'TX', 'US')
		* @return array|false Dataset list array (each entry is a dataset row) or false on error
		*/
	public static function get_datasets(string $state): array|false {
		$datasets = self::api_request([
			'op' => 'getDatasetList',
			'key' => 'getdatasetlist_' . strtoupper($state),
			'expires' => 1,
			'params' => ['state' => $state]
		]);
		
		if (!is_array($datasets) || !isset($datasets['datasetlist']) || !is_array($datasets['datasetlist'])) {
			return false;
		}

		// Normalize + re-key in a single pass.
		// - Adds missing "directory" field for consistent path construction.
		// - Adds "state" field for reliable path resolution (dataset files are stored by state).
		// - Returns dataset list only (status wrapper is not useful to callers), keyed by session_id.
		$indexed = [];
		foreach ($datasets['datasetlist'] as $dataset) {
			if (!is_array($dataset)) {
				continue;
			}

			$dataset['directory'] = self::session_dir_name($dataset);
//fi_log("DATASET: " . json_encode($dataset),__FILE__,__LINE__);

			// Convert numeric state_id to 2-letter state code for reliable path construction
			// Dataset files are stored under this state directory (e.g., /legiscan/AZ/...)
			$state_id = (int) ($dataset['state_id'] ?? 0);
			if ($state_id > 0) {
				$dataset['state'] = self::abbreviation($state_id);
			} else {
				// Fallback to the state parameter used to query the API
				$dataset['state'] = strtoupper($state);
			}
			
			$session_id = (int) ($dataset['session_id'] ?? 0);
			if ($session_id > 0) {
				$indexed[$session_id] = $dataset;
			} else {
				$indexed[] = $dataset;
			}
		}
		return $indexed;
	}

		/**
		* Fetch and extract a dataset (static method from core class)
		* 
		* @param int $session_id Legiscan session ID
		* @param string $access_key Dataset access key
		* @return string|false Path to extracted directory or false on error
		*/
	public static function fetch_dataset(int $session_id, string $access_key, array $dataset = []): string|false {
		$response = self::api_request([
			'op' => 'getDataset',
			'key' => 'getdataset_session-' . $session_id . '_access-' . substr($access_key, 0, 8),
			'expires' => 0,
			'params' => [
				'id' => $session_id,
				'access_key' => $access_key
			]
		]);
		
		if (!$response || !isset($response['dataset']['zip'])) {
			return false;
		}
		
		// Extract ZIP from base64
		$zip_data = base64_decode($response['dataset']['zip']);
		if (!$zip_data) {
			return false;
		}
		
		// Save ZIP to temp file
		// get_temp_dir() is a WordPress core function (wp-includes/functions.php)
		$temp_dir = (defined('FI_DIR_LEGISCAN_ZIP') ? FI_DIR_LEGISCAN_ZIP : (rtrim(FI_DIR_CACHE, "/\\") . DIRECTORY_SEPARATOR . 'legiscan' . DIRECTORY_SEPARATOR . '_zip' . DIRECTORY_SEPARATOR)) . uniqid() . '/';
		if (!wp_mkdir_p($temp_dir)) {
			return false;
		}
		
		$zip_file = $temp_dir . 'dataset.zip';
		file_put_contents($zip_file, $zip_data);
		
		// Extract ZIP and rename session directory to standardized name
		$extract_dir = self::extract_zip($zip_file, $dataset);
		if (!$extract_dir) {
			return false;
		}
		
		// Cleanup ZIP file
		@unlink($zip_file);
		
		return $extract_dir;
	}

	/**
	* Extract zip file to temporary directory and rename session folder (static method from core class)
	* 
	* @param string $zip_path Path to zip file
	* @param array $dataset Dataset info for generating standardized directory name
	* @return string|false Path to extracted directory or false on error
	*/
	private static function extract_zip(string $zip_path, array $dataset = []): string|false {
		$extract_dir = get_temp_dir() . 'fi_legiscan_' . uniqid() . '/';
		
		if (!wp_mkdir_p($extract_dir)) {
			return false;
		}

		$zip = new \ZipArchive();
		if ($zip->open($zip_path) !== true) {
			return false;
		}

		$zip->extractTo($extract_dir);
		$zip->close();

		// Rename session directory to standardized name if dataset info provided
		if (!empty($dataset)) {
			$extract_dir = self::rename_session_directory($extract_dir, $dataset);
			if (!$extract_dir) {
				return false;
			}
		}

		return $extract_dir;
	}

	public static function session_dir_name(array $dataset): string {
		$session_name = (string) ($dataset['session_name'] ?? '');
		$session_title = (string) ($dataset['session_title'] ?? '');
		if($session_title){
			$directory = str_replace(' ', '_', $session_title);
		}else{
			$directory = str_replace(' ', '_', $session_name);
		}
		return $directory;
	}


	/**
	* Rename extracted session directory to standardized format
	* ZIP extracts to: {state}/{year_start}-{year_end}_{verbose_session_name}/
	* We rename to: {state}/{year_start}_{session_title}/
	* 
	* @param string $extract_dir Path to extraction directory
	* @param array $dataset Dataset info containing year_start, session_title, etc.
	* @return string|false Path to extract directory with renamed session folder, or false on error
	*/
	public static function rename_session_directory(string $extract_dir, array $dataset): string|false {
		// Find state directory (should be only directory in extract_dir)
		$items = array_diff(scandir($extract_dir), ['.', '..']);
		if (empty($items)) {
			return false;
		}

		$state_dir = null;
		foreach ($items as $item) {
			$item_path = rtrim($extract_dir, '/\\') . DIRECTORY_SEPARATOR . $item;
			if (is_dir($item_path)) {
				$state_dir = $item;
				break;
			}
		}

		if (!$state_dir) {
			return false;
		}

		$state_path = rtrim($extract_dir, '/\\') . DIRECTORY_SEPARATOR . $state_dir . DIRECTORY_SEPARATOR;

		// Find session directory inside state directory
		$session_items = array_diff(scandir($state_path), ['.', '..']);
		if (empty($session_items)) {
			return false;
		}

		$old_session_dir = null;
		foreach ($session_items as $item) {
			$item_path = rtrim($state_path, '/\\') . DIRECTORY_SEPARATOR . $item;
			if (is_dir($item_path)) {
				$old_session_dir = $item;
				break;
			}
		}

		if (!$old_session_dir) {
			return false;
		}

		// Generate standardized directory name using fi_legiscan_session_dir()
		$new_session_dir = self::session_dir_name($dataset);
		if ($new_session_dir === $old_session_dir) {
			// Already has correct name
			return $extract_dir;
		}

		// Rename session directory
		$old_path = rtrim($state_path, '/\\') . DIRECTORY_SEPARATOR . $old_session_dir;
		$new_path = rtrim($state_path, '/\\') . DIRECTORY_SEPARATOR . $new_session_dir;

		if (!rename($old_path, $new_path)) {
			return false;
		}

		return $extract_dir;
	}

	/**
	* Recursively copy directory (fallback for cross-filesystem moves)
	* 
	* @param string $src Source directory
	* @param string $dst Destination directory
	* @return bool Success
	*/
	public static function recursive_copy(string $src, string $dst): bool {
		if (!is_dir($src)) {
			return false;
		}

		if (!is_dir($dst)) {
			if (!wp_mkdir_p($dst)) {
				return false;
			}
		}

		$dir = opendir($src);
		if (!$dir) {
			return false;
		}

		while (($file = readdir($dir)) !== false) {
			if ($file === '.' || $file === '..') {
				continue;
			}

			$src_path = rtrim($src, '/\\') . DIRECTORY_SEPARATOR . $file;
			$dst_path = rtrim($dst, '/\\') . DIRECTORY_SEPARATOR . $file;

			if (is_dir($src_path)) {
				self::recursive_copy($src_path, $dst_path);
			} else {
				copy($src_path, $dst_path);
			}
		}

		closedir($dir);
		return true;
	}

		/**
		* Process Legiscan data from an extracted directory (static method from core class)
		* 
		* @param string $extract_dir Path to extracted directory
		* @param string $gov Government code (e.g., 'US', 'TX')
		* @return array Processing results with stats and errors
		*/
		public static function process_directory(string $extract_dir, string $gov): array {
			// This method processes bulk data - for now, return empty results
			// The admin workflow uses granular import methods instead
			return [
				'stats' => [
					'legislators' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
					'votes' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
					'rollcalls' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
					'sessions' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
					'errors' => []
				],
				'log' => [],
				'success' => true
			];
		}

		/**
		* Process a Legiscan zip file (static method from core class)
		* 
		* @param string $zip_path Path to uploaded zip file
		* @param string $gov Government code (e.g., 'US', 'TX')
		* @return array Processing results with stats and errors
		*/
		public static function process_zip(string $zip_path, string $gov): array {
			$extract_dir = self::extract_zip($zip_path);
			if (!$extract_dir) {
				return [
					'stats' => [
						'legislators' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
						'votes' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
						'rollcalls' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
						'sessions' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
						'errors' => ['Failed to extract ZIP file']
					],
					'log' => [],
					'success' => false
				];
			}

			try {
				$results = self::process_directory($extract_dir, $gov);
			} finally {
				self::cleanup_extract_dir($extract_dir);
			}

			return $results;
		}

		/**
		* Cleanup extracted directory (static method from core class)
		* 
		* @param string $extract_dir Path to extracted directory
		*/
		public static function cleanup_extract_dir(string $extract_dir): void {
			if (!is_dir($extract_dir)) {
				return;
			}

			$files = array_diff(scandir($extract_dir), ['.', '..']);
			foreach ($files as $file) {
				$file_path = $extract_dir . $file;
				if (is_file($file_path)) {
					unlink($file_path);
				} elseif (is_dir($file_path)) {
					self::cleanup_extract_dir($file_path . '/');
					if (is_dir($file_path)) {
						@rmdir($file_path);
					}
				}
			}
			if (is_dir($extract_dir)) {
				@rmdir($extract_dir);
			}
		}

		/**
		* Wrap fi_log in function so we can log this class only if necessary.
		*/
		public static function log(string $message, string $file = '', int $line = 0, string $level = 'debug'): void {
			fi_log_area('Legiscan', $message, $file, $line, $level);
		}
	}
}

/* Public helper functions */
namespace {
	/**
	 * Get available LegiScan datasets for a state/gov.
	 * Returns the dataset list array only (not the status wrapper).
	 */
	function fi_legiscan_get_datasets(string $state): array|false {
		return \FI\Admin\Legiscan::get_datasets($state);
	}

	function fi_legiscan_fetch_dataset(int $session_id, string $access_key, array $dataset = []): string|false {
		return \FI\Admin\Legiscan::fetch_dataset($session_id, $access_key, $dataset);
	}

	function fi_legiscan_process_directory(string $extract_dir, string $gov): array {
		return \FI\Admin\Legiscan::process_directory($extract_dir, $gov);
	}

	function fi_legiscan_process_zip(string $zip_path, string $gov): array {
		return \FI\Admin\Legiscan::process_zip($zip_path, $gov);
	}

	function fi_legiscan_cleanup_extract_dir(string $extract_dir): void {
		\FI\Admin\Legiscan::cleanup_extract_dir($extract_dir);
	}

	function fi_legiscan_abbreviations(): array {
		return \FI\Admin\Legiscan::abbreviations();
	}

	function fi_legiscan_abbreviation($state_id): string {
		return \FI\Admin\Legiscan::abbreviation($state_id);
	}

	function fi_legiscan_create_vote(array $args): int|false {
		return \FI\Admin\Legiscan::create_vote($args);
	}


	/*
	* Zip data unpacks to {gov}/{year_start}-{year_end}_{session_name}/ but this directory name doesn't appear in the dataset list.
	* This function builds the directory name from the dataset list.
	* EXAMPLE: jbsfi/legiscan/AZ/2021-2021_Fifty-fifth_Legislature_1st_Regular 
	* ...sucks because nothing in this example can be used to match this directory name.
	* RETURNS: 2021-2021_Fifty-fifth_Legislature_-_First_Regular_Session_(2021)
	/*
	{
		"state_id": 43,
		"session_id": 2223,
		"year_start": 2025,
		"year_end": 2025,
		"prefile": 0,
		"sine_die": 1,
		"prior": 0,
		"special": 1,
		"session_tag": "2nd Special Session",
		"session_title": "2025 2nd Special Session",
		"session_name": "89th Legislature 2nd Special Session",
		"dataset_date": "2025-12-14",
		"dataset_hash": "24b99c05036102b915c9ae7794ad645b",
		"dataset_size": 2039036,
		"dataset_size_csv": 187574,
		"access_key": "311JOeEPsVbnzhALqebke5",
		"directory": "2025-2025_89th_Legislature_2nd_Special_Session"
	}
	*/
	function fi_legiscan_session_dir($data){
		return \FI\Admin\Legiscan::session_dir_name($data);
	}

	/**
	 * Get vote data from Legiscan cache directory
	 * Can be called from Legiscan import page or FI Votes Edit page
	 * 
	 * @param array $args {
	 *   @type string $gov Government code (e.g., 'US', 'TX')
	 *   @type int $fi_vote_id FI vote ID (optional, used to get session if LS_session_id not provided)
	 *   @type int $LS_session_id Legiscan session ID (optional, will be retrieved from vote if not provided)
	 *   @type string $LS_bill_id Legiscan bill number (e.g., 'HB1005') - required
	 *   @type int $LS_roll_call_id Legiscan roll call ID - required
	 * }
	 * @return array Array with 'roll_call_id', 'bill', 'roll_call' keys on success, or 'error' key with message on failure
	 */
	function fi_legiscan_vote_data(array $args): array {
		$gov = $args['gov'] ?? null;
		$fi_vote_id = $args['fi_vote_id'] ?? null;
		$LS_session_id = $args['LS_session_id'] ?? null;
		$LS_bill_id = $args['LS_bill_id'] ?? null; // This is the bill_number (e.g., 'HB1005')
		$LS_roll_call_id = $args['LS_roll_call_id'] ?? null;
		
		// Validate required parameters
		if (!$gov || !$LS_bill_id || !$LS_roll_call_id) {
			return ['error' => 'Missing required parameters: gov, LS_bill_id, and LS_roll_call_id are required.'];
		}
		
		// If we don't have LS_session_id, try to get it from the vote
		if (!$LS_session_id && $fi_vote_id) {
			$vote = fi_vote_get((int) $fi_vote_id);
			if ($vote && !empty($vote->session_id)) {
				$session = fi_session_get((int) $vote->session_id);
				if ($session && !empty($session->legiscan_id)) {
					$LS_session_id = (int) $session->legiscan_id;
				}
			}
		}
		
		// If we still don't have LS_session_id, we can't proceed
		if (!$LS_session_id) {
			return ['error' => 'Could not determine Legiscan session ID. Please provide Legiscan Session ID or Freedom Index Vote ID.'];
		}
		
		// Get dataset list from cache
		$legiscan_state = ($gov === 'US') ? 'US' : $gov;
		$dataset_list_file = (defined('FI_DIR_LEGISCAN_JSON') ? FI_DIR_LEGISCAN_JSON : (rtrim(FI_DIR_CACHE, "/\\") . DIRECTORY_SEPARATOR . 'legiscan' . DIRECTORY_SEPARATOR . '_json' . DIRECTORY_SEPARATOR))
			. 'getdatasetlist_' . $legiscan_state . '.json';
		
		if (!file_exists($dataset_list_file)) {
			return ['error' => 'Dataset list file not found. Please fetch the dataset list first.'];
		}
		
		$dataset_list_data = json_decode(file_get_contents($dataset_list_file), true);
		if (!$dataset_list_data || !isset($dataset_list_data['datasetlist'])) {
			return ['error' => 'Could not parse dataset list file or dataset list is empty.'];
		}
		
		// Find the session in the dataset list and build directory name using standardized function
		$data_dir_name = '';
		foreach ($dataset_list_data['datasetlist'] as $dataset) {
			if (isset($dataset['session_id']) && (int) $dataset['session_id'] === (int) $LS_session_id) {
				$data_dir_name = fi_legiscan_session_dir($dataset);
				break;
			}
		}
		
		if (!$data_dir_name) {
			return ['error' => 'Could not find session directory for vote import (session/dataset not found in dataset list).'];
		}
		
		// Build the cache directory path - use legiscan_state (not gov) since dataset was fetched under that state
		$data_dir = (defined('FI_DIR_LEGISCAN') ? FI_DIR_LEGISCAN : (rtrim(FI_DIR_CACHE, "/\\") . DIRECTORY_SEPARATOR . 'legiscan' . DIRECTORY_SEPARATOR)) . $legiscan_state . '/' . $data_dir_name . '/';
		$fi_votes_dir = $data_dir . 'fi/';
		
		// Get the bill file (using bill_number, not bill_id)
		$bill_file = $fi_votes_dir . $LS_bill_id . '.json';
		if (!file_exists($bill_file)) {
			return ['error' => 'Bill file not found: ' . esc_html($LS_bill_id) . '.json'];
		}
		
		$bill_data = json_decode(file_get_contents($bill_file), true);
		if (!$bill_data) {
			return ['error' => 'Failed to parse bill file.'];
		}
		
		// Extract roll_call data from bill's votes array
		$roll_calls = $bill_data['votes'] ?? [];
		if (empty($roll_calls)) {
			return ['error' => 'No roll calls found in bill file.'];
		}
		
		// Find the roll_call by ID (check both numeric and string keys)
		$roll_call_data = null;
		if (isset($roll_calls[$LS_roll_call_id])) {
			$roll_call_data = $roll_calls[$LS_roll_call_id];
		} elseif (isset($roll_calls[(string)$LS_roll_call_id])) {
			$roll_call_data = $roll_calls[(string)$LS_roll_call_id];
		}
		
		if (!$roll_call_data) {
			return ['error' => 'Roll call ID not found in bill file.'];
		}
		
		// Remove votes from bill_data before returning
		$bill_data_for_return = $bill_data;
		unset($bill_data_for_return['votes']);
		
		// Return in the same format as create_vote expects
		return [
			'roll_call_id' => $LS_roll_call_id,
			'bill' => $bill_data_for_return,
			'roll_call' => $roll_call_data,
		];
	}

	// -----------------------------------------------------------------------------
	// Matching helpers
	// -----------------------------------------------------------------------------
	// Summary: normalize human names for deterministic matching (accent-insensitive, punctuation-insensitive).
	function fi_legiscan_people_normalize_name(string $name): string {
		$name = trim($name);
		if ($name === '') return '';
		if (function_exists('remove_accents')) {
			$name = remove_accents($name);
		}
		$name = preg_replace('/[^a-z0-9\s]/i', ' ', $name);
		$name = preg_replace('/\s+/', ' ', $name);
		return strtolower(trim($name));
	}

	// Summary: build multiple matching keys for a LegiScan person (full name + first/last fallback).
	function fi_legiscan_people_name_keys(array $person): array {
		$keys = [];
		$name = (string) ($person['name'] ?? '');
		$first = (string) ($person['first_name'] ?? '');
		$middle = (string) ($person['middle_name'] ?? '');
		$last = (string) ($person['last_name'] ?? '');
		$suffix = (string) ($person['suffix'] ?? '');

		$full = trim(trim($name) ?: trim(implode(' ', array_filter([$first, $middle, $last, $suffix]))));
		$k1 = fi_legiscan_people_normalize_name($full);
		if ($k1 !== '') $keys[] = $k1;

		// Common FI display_name format: "First Last"
		$k2 = fi_legiscan_people_normalize_name(trim(implode(' ', array_filter([$first, $last]))));
		if ($k2 !== '' && $k2 !== $k1) $keys[] = $k2;

		return array_values(array_unique($keys));
	}
}