<?php
/*
 * Freedom Index LegiScan Integration Helpers
 *
 * Straight function version of the former FIAdmin\Legiscan class file.
 *
 * Handles LegiScan API requests, JSON cache files, dataset extraction,
 * legislator import, vote/rollcall import, and local cache lookup helpers.
 *
 * This file is admin-workflow oriented, but most of the logic is reusable import
 * infrastructure. Keep screen rendering and button/form handling in admin UI files.
 * Refactored the LegiScan admin integration into straight functions.

Key adjustments:

Removed the FIAdmin\Legiscan class/namespace wrapper.
Preserved the existing public API:
	fi_legiscan_get_datasets()
	fi_legiscan_fetch_dataset()
	fi_legiscan_process_directory()
	fi_legiscan_process_zip()
	fi_legiscan_cleanup_extract_dir()
	fi_legiscan_abbreviations()
	fi_legiscan_abbreviation()
	fi_legiscan_create_vote()
	fi_legiscan_session_dir()
	fi_legiscan_vote_data()
	fi_legiscan_people_normalize_name()
	fi_legiscan_people_name_keys()
Added reusable helpers:
	fi_legiscan_cache()
	fi_legiscan_get_api_key()
	fi_legiscan_get_state_list()
	fi_legiscan_cache_key_from_args()
	fi_legiscan_get()
	fi_legiscan_unzip()
	fi_legiscan_dir_tree()
	fi_legiscan_list_people()
	fi_legiscan_create_legislator()
	fi_legiscan_list_votes()
	fi_legiscan_api_request()
	fi_legiscan_extract_zip()
	fi_legiscan_rename_session_directory()
	fi_legiscan_recursive_copy()
	fi_legiscan_log()
Tuning:
	Replaced the duplicated cURL request logic with wp_remote_get() inside fi_legiscan_api_request().
	Centralized cache-key generation with fi_legiscan_cache_key_from_args().
	Removed the unreachable API-fetch fallback from get_state_list() because the hardcoded LegiScan state order was returned before that code could ever execute.
	Removed vote slug generation in fi_legiscan_create_vote() because your system is now ID-based.
	Replaced the old \ApiIntegration::build_updates_for_legislator() call with the refactored function:
	fi_api_build_updates_for_legislator()
	Architectural note: this file is named/admin-located, but most of it is reusable import infrastructure rather than UI. Long term, I would move it to something like:
	/core/integrations/legiscan.php
	and leave only the admin screen controller in /admin/autoload.
 */

if (!defined('ABSPATH')) exit;


function fi_legiscan_cache(string $key, $data = '', int $expires_days = 7) {
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


function fi_legiscan_get_state_list() : array {
	// LegiScan state IDs are 1-based against this stable list.
	// If DC/US handling changes upstream, adjust here intentionally.
	return [
		'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
		'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
		'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
		'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
		'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
		'DC', 'US',
	];
}


/**
 * Build a stable human-readable LegiScan cache key from request args.
 *
 * @param array $args LegiScan request args.
 * @return string Cache key without .json suffix.
 */
function fi_legiscan_cache_key_from_args(array $args): string {
	$op = strtolower((string) ($args['op'] ?? ''));
	$cache_key = (string) ($args['key'] ?? '');

	if ($cache_key !== '') {
		return $cache_key;
	}

	$key_parts = [$op];
	if (!empty($args['params']) && is_array($args['params'])) {
		ksort($args['params']);
		foreach ($args['params'] as $key => $val) {
			$key_parts[] = sanitize_key((string) $key) . '-' . sanitize_file_name((string) $val);
		}
	}

	return implode('_', array_filter($key_parts));
}


function fi_legiscan_abbreviations() : array {
			$state_list = fi_legiscan_get_state_list();
			return array_map(function($state) {
				return strtoupper($state);
			}, $state_list);
		
}

function fi_legiscan_abbreviation($state_id) : string {
			$abbreviations = fi_legiscan_abbreviations();
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

function fi_legiscan_get(array $args) : array|false {
	$op = strtolower((string) ($args['op'] ?? ''));
	$data = fi_legiscan_api_request($args);

	if (!$data) {
		return false;
	}

	if ($op === 'getdataset') {
		$cache_key = fi_legiscan_cache_key_from_args($args);
		if (isset($data['dataset']['zip'])) {
			$extract_dir = fi_legiscan_unzip(FI_DIR_LEGISCAN . $cache_key, (string) $data['dataset']['zip']);
			if ($extract_dir) {
				return fi_legiscan_dir_tree(FI_DIR_LEGISCAN . $cache_key);
			}
		}
		return false;
	}

	return $data;
}

function fi_legiscan_unzip(string $zip_dir, string $zip_data) : string|false {
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

function fi_legiscan_dir_tree(string $path) : array {
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

function fi_legiscan_list_people(array $args = []) : array {
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

function fi_legiscan_create_legislator(array $args) : int|false {
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
				$dataset_gov = fi_legiscan_abbreviation((int) $dataset_state_id);
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
					$session_id = $session ? $session['id'] : null;
					
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
						if (function_exists('fi_api_build_updates_for_legislator') && function_exists('fi_admin_legislators_apply_api_updates')) {
							$updates = fi_api_build_updates_for_legislator((int) $legislator_id, 'legiscan_local', is_array($person) ? $person : [], true);
							if (!empty($updates) && is_array($updates)) {
								fi_admin_legislators_apply_api_updates((int) $legislator_id, 'legiscan_local', $updates);
							}
						}
					}
				}
			}
			
			return $legislator_id ?: false;
		
}

function fi_legiscan_list_votes(array $args = []) : array {
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
				if ($vote['legiscan_rcid']) {
					$votes[$vote['legiscan_rcid']] = [
						'id' => $vote['id'],
						'legiscan_bid' => $vote['legiscan_bid'],
						'legiscan_rcid' => $vote['legiscan_rcid'],
					];
				}
			}
			
			return $votes;
		
}

function fi_legiscan_create_vote(array $args) : int|false {
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
			$session_id = $session ? $session['id'] : null;
			
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

			// Slug generation intentionally omitted. Vote URLs are ID-based.
			
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

function fi_legiscan_api_request(array $args) : array|false {
	$api_key = fi_get_api_key('legiscan_key', 'API_KEY_LEGISCAN');
	if (!$api_key) {
		return false;
	}

	$op = strtolower((string) ($args['op'] ?? ''));
	if ($op === '') {
		return false;
	}

	$expires = isset($args['expires']) ? (int) $args['expires'] : 30;
	$cache_key = fi_legiscan_cache_key_from_args($args);

	$cached = fi_legiscan_cache($cache_key, '', $expires);
	if ($cached !== false) {
		fi_legiscan_log('Cache Hit: ' . $cache_key, __FILE__, __LINE__);
		return is_array($cached) ? $cached : false;
	}

	fi_legiscan_log('API Fetch: ' . $cache_key, __FILE__, __LINE__);

	$query_args = [
		'key' => $api_key,
		'op'  => $op,
	];

	if (!empty($args['params']) && is_array($args['params'])) {
		foreach ($args['params'] as $key => $val) {
			$query_args[(string) $key] = $val;
		}
	}

	$url = add_query_arg($query_args, 'https://api.legiscan.com/');

	$response = wp_remote_get($url, [
		'timeout'     => 300,
		'redirection' => 3,
	]);

	if (is_wp_error($response)) {
		fi_legiscan_log('LegiScan API WP error: ' . $response->get_error_message(), __FILE__, __LINE__, 'error');
		return false;
	}

	$http_code = (int) wp_remote_retrieve_response_code($response);
	$body = (string) wp_remote_retrieve_body($response);

	if ($http_code !== 200 || $body === '') {
		fi_legiscan_log('LegiScan API HTTP error: ' . $http_code . ' | key=' . $cache_key, __FILE__, __LINE__, 'error');
		return false;
	}

	$data = json_decode($body, true);
	if (!is_array($data) || (isset($data['status']) && $data['status'] !== 'OK')) {
		fi_legiscan_log('LegiScan API invalid response | key=' . $cache_key, __FILE__, __LINE__, 'error');
		return false;
	}

	fi_legiscan_cache($cache_key, $body, $expires);

	return $data;
}

function fi_legiscan_get_datasets(string $state) : array|false {
		$datasets = fi_legiscan_api_request([
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

			$dataset['directory'] = fi_legiscan_session_dir($dataset);
//fi_log("DATASET: " . json_encode($dataset),__FILE__,__LINE__);

			// Convert numeric state_id to 2-letter state code for reliable path construction
			// Dataset files are stored under this state directory (e.g., /legiscan/AZ/...)
			$state_id = (int) ($dataset['state_id'] ?? 0);
			if ($state_id > 0) {
				$dataset['state'] = fi_legiscan_abbreviation($state_id);
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

function fi_legiscan_fetch_dataset(int $session_id, string $access_key, array $dataset = []) : string|false {
		$response = fi_legiscan_api_request([
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
		$extract_dir = fi_legiscan_extract_zip($zip_file, $dataset);
		if (!$extract_dir) {
			return false;
		}
		
		// Cleanup ZIP file
		@unlink($zip_file);
		
		return $extract_dir;
	
}

function fi_legiscan_extract_zip(string $zip_path, array $dataset = []) : string|false {
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
			$extract_dir = fi_legiscan_rename_session_directory($extract_dir, $dataset);
			if (!$extract_dir) {
				return false;
			}
		}

		return $extract_dir;
	
}

function fi_legiscan_session_dir(array $dataset) : string {
		$session_name = (string) ($dataset['session_name'] ?? '');
		$session_title = (string) ($dataset['session_title'] ?? '');
		if($session_title){
			$directory = str_replace(' ', '_', $session_title);
		}else{
			$directory = str_replace(' ', '_', $session_name);
		}
		return $directory;
	
}

function fi_legiscan_rename_session_directory(string $extract_dir, array $dataset) : string|false {
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
		$new_session_dir = fi_legiscan_session_dir($dataset);
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

function fi_legiscan_recursive_copy(string $src, string $dst) : bool {
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
				fi_legiscan_recursive_copy($src_path, $dst_path);
			} else {
				copy($src_path, $dst_path);
			}
		}

		closedir($dir);
		return true;
	
}

function fi_legiscan_process_directory(string $extract_dir, string $gov) : array {
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

function fi_legiscan_process_zip(string $zip_path, string $gov) : array {
			$extract_dir = fi_legiscan_extract_zip($zip_path);
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
				$results = fi_legiscan_process_directory($extract_dir, $gov);
			} finally {
				fi_legiscan_cleanup_extract_dir($extract_dir);
			}

			return $results;
		
}

function fi_legiscan_cleanup_extract_dir(string $extract_dir) : void {
			if (!is_dir($extract_dir)) {
				return;
			}

			$files = array_diff(scandir($extract_dir), ['.', '..']);
			foreach ($files as $file) {
				$file_path = $extract_dir . $file;
				if (is_file($file_path)) {
					unlink($file_path);
				} elseif (is_dir($file_path)) {
					fi_legiscan_cleanup_extract_dir($file_path . '/');
					if (is_dir($file_path)) {
						@rmdir($file_path);
					}
				}
			}
			if (is_dir($extract_dir)) {
				@rmdir($extract_dir);
			}
		
}

function fi_legiscan_log(string $message, string $file = '', int $line = 0, string $level = 'debug') : void {
			fi_log_area('Legiscan', $message, $file, $line, $level);
		
}

function fi_legiscan_vote_data(array $args) : array {
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
			if ($vote && !empty($vote['session_id'])) {
				$session = fi_session_get((int) $vote['session_id']);
				if ($session && !empty($session['legiscan_id'])) {
					$LS_session_id = (int) $session['legiscan_id'];
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
			return ['error' => 'Bill file not found: ' . $bill_file];
		}
		
		$bill = json_decode(file_get_contents($bill_file), true);
		if (!$bill) {
			return ['error' => 'Could not parse bill file: ' . $bill_file];
		}
		
		// Find the roll call in the bill's roll_calls array
		$roll_call = null;
		if (isset($bill['roll_calls']) && is_array($bill['roll_calls'])) {
			foreach ($bill['roll_calls'] as $rc) {
				if (isset($rc['roll_call_id']) && (int) $rc['roll_call_id'] === (int) $LS_roll_call_id) {
					$roll_call = $rc;
					break;
				}
			}
		}
		
		if (!$roll_call) {
			return ['error' => 'Roll call not found in bill file. Roll Call ID: ' . $LS_roll_call_id];
		}
		
		// Get the detailed roll call file (if exists) to get individual votes
		$rollcall_file = $data_dir . 'roll_call/' . $LS_roll_call_id . '.json';
		if (file_exists($rollcall_file)) {
			$rollcall_detail = json_decode(file_get_contents($rollcall_file), true);
			if ($rollcall_detail && isset($rollcall_detail['votes'])) {
				$roll_call['votes'] = $rollcall_detail['votes'];
			}
		}
		
		return [
			'roll_call_id' => (int) $LS_roll_call_id,
			'bill' => $bill,
			'roll_call' => $roll_call,
		];
	
}

function fi_legiscan_people_normalize_name(string $name) : string {
		// Remove common honorifics and normalize punctuation/spacing.
		$name = trim($name);
		$name = preg_replace('/^(Sen\.?|Rep\.?|Representative|Senator)\s+/i', '', $name);
		if (function_exists('remove_accents')) {
			$name = remove_accents($name);
		}
		$name = preg_replace('/[^a-z0-9\s]/i', ' ', $name);
		$name = preg_replace('/\s+/', ' ', $name);
		return strtolower(trim($name));
	
}

function fi_legiscan_people_name_keys(array $person) : array {
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