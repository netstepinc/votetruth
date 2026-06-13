<?php
namespace FI\Core{

	if (!defined('ABSPATH')) exit;

	/**
	* API Integration for External Data Sources
	* 
	* Fetches and compares legislator data from external APIs (VoteSmart, GovTrack, Legiscan, etc.)
	* Provides diff comparison and update suggestions.
	*/
	final class ApiIntegration {

		/**
		* Fetch data from all available APIs for a legislator
		* 
		* @param int $legislator_id
		* @return array API data keyed by source
		*/
		public static function fetch_all(int $legislator_id): array {
			$legislator = fi_legislator_get($legislator_id);
			if (!$legislator) {
				return [];
			}

			$results = [];

			// VoteSmart
			if (!empty($legislator->votesmart_id)) {
				$results['votesmart'] = self::fetch_votesmart($legislator->votesmart_id);
			} elseif (!empty($legislator->bioguide_id) || !empty($legislator->display_name)) {
				$results['votesmart'] = self::search_votesmart($legislator);
			}

			// GovTrack (Congress only)
			if (!empty($legislator->gov) && $legislator->gov === 'US') {
				if (!empty($legislator->bioguide_id)) {
					$results['govtrack'] = self::fetch_govtrack_by_bioguide($legislator->bioguide_id);
				} elseif (!empty($legislator->govtrack_id)) {
					$results['govtrack'] = self::fetch_govtrack($legislator->govtrack_id);
				} elseif (!empty($legislator->display_name)) {
					$results['govtrack'] = self::search_govtrack($legislator);
				}
			}

			// Legiscan (if legiscan_id exists)
			if (!empty($legislator->legiscan_id)) {
				$results['legiscan'] = self::fetch_legiscan($legislator->legiscan_id);
			}

			return $results;
		}

		/**
		* Compare API data with existing legislator data
		* 
		* @param int $legislator_id
		* @param string $source API source (votesmart, govtrack, legiscan)
		* @param array $api_data Data from API
		* @return array Diff results with match/diff/missing status
		*/
		public static function compare_data(int $legislator_id, string $source, array $api_data): array {
			$legislator = fi_legislator_get($legislator_id);
			if (!$legislator) {
				return [];
			}

			$meta = is_array($legislator->meta ?? null) ? $legislator->meta : [];
			$comparison = [];

			// Field mappings: our_field => api_field
			$field_mappings = self::get_field_mappings($source);

			foreach ($field_mappings as $our_field => $api_field) {
				$api_value = self::get_nested_value($api_data, $api_field);
				$our_value = self::get_our_value($legislator, $our_field, $meta);

				$status = self::compare_values($our_value, $api_value);
				
				// Ensure values are displayable in the admin UI (JS diff renderer expects strings/scalars).
				$api_value_display = (is_array($api_value) || is_object($api_value)) ? wp_json_encode($api_value) : $api_value;
				$our_value_display = (is_array($our_value) || is_object($our_value)) ? wp_json_encode($our_value) : $our_value;
				
				$comparison[$our_field] = [
					'status' => $status,
					'our_value' => $our_value_display,
					'api_value' => $api_value_display,
					'api_field' => $api_field,
					'label' => self::get_field_label($our_field)
				];
			}

			// Check for additional fields in API data not in our mapping
			$additional = self::find_additional_fields($api_data, $field_mappings, $meta);
			foreach ($additional as $field => $data) {
				$comparison[$field] = [
					'status' => 'missing',
					'our_value' => null,
					'api_value' => $data['value'],
					'api_field' => $data['path'],
					'label' => self::get_field_label($field)
				];
			}

			return $comparison;
		}

		/**
		 * Build an updates array (our_field => api_value) for a given legislator/source.
		 *
		 * Summary of choices:
		 * - Uses the same field map as the admin "API Data Checks" diff UI.
		 * - Optionally returns ONLY fields that are missing on our side (safer for auto-import).
		 * - Encodes repeater-ish values (like addresses.capitol) as JSON strings to match admin update handlers.
		 */
		public static function build_updates_for_legislator(int $legislator_id, string $source, array $api_data, bool $only_missing = true): array {
			$legislator = fi_legislator_get($legislator_id);
			if (!$legislator) {
				return [];
			}

			$meta = is_array($legislator->meta ?? null) ? $legislator->meta : [];

			// Normalize LegiScan-local payload to match how the admin fetch handler shapes it.
			if ($source === 'legiscan_local') {
				$api_data = self::normalize_legiscan_local_person($api_data);
			}

			$mappings = self::get_field_mappings($source);
			if (empty($mappings)) {
				return [];
			}

			$updates = [];
			foreach ($mappings as $our_field => $api_field) {
				$api_value = self::get_nested_value($api_data, $api_field);
				if ($api_value === null || $api_value === '' || $api_value === []) {
					continue;
				}

				if ($only_missing) {
					$our_value = self::get_our_value($legislator, $our_field, $meta);
					$status = self::compare_values($our_value, $api_value);
					if ($status !== 'missing') {
						continue;
					}
				}

				// Admin update handler expects JSON string for this field.
				if ($our_field === 'addresses.capitol' && is_array($api_value)) {
					$updates[$our_field] = wp_json_encode($api_value);
					continue;
				}

				$updates[$our_field] = $api_value;
			}

			return $updates;
		}

		/**
		* Get field mappings for a source
		*/
		private static function get_field_mappings(string $source): array {
			$mappings = [
				'votesmart' => [
					'first_name' => 'firstName',
					'last_name' => 'lastName',
					'middle_name' => 'middleName',
					'display_name' => 'nickName',
					'votesmart_id' => 'candidateId',
					'bioguide_id' => 'bioguideId',
					'ballotpedia_id' => 'ballotpedia',
					'image_id' => 'photo', // URL, needs processing
					'meta.email' => 'email',
					'meta.phone' => 'phone',
					'meta.office_address' => 'office',
					'meta.website' => 'website',
					'meta.twitter' => 'twitter',
					'meta.facebook' => 'facebook',
				],
				'govtrack' => [
					'first_name' => 'firstname',
					'last_name' => 'lastname',
					'middle_name' => 'middlename',
					'bioguide_id' => 'bioguide_id',
					'govtrack_id' => 'id',
					'image_id' => 'image', // URL, needs processing
					'meta.wikipedia' => 'wikipedia',
					// Use our canonical meta key used by the admin UI + meta normalizer.
					'meta.birth_date' => 'birthday',
					'meta.gender' => 'gender',
				],
				'legiscan' => [
					'first_name' => 'first_name',
					'last_name' => 'last_name',
					'middle_name' => 'middle_name',
					'legiscan_id' => 'people_id',
					'bioguide_id' => 'bioguide_id',
					'votesmart_id' => 'votesmart_id',
					'ballotpedia_id' => 'ballotpedia_id',
					'meta.party_history' => 'party_history',
					'meta.role_history' => 'role_history',
				],
				// Local LegiScan cache (people/{people_id}.json) — richer fields, nested under bio.*.
				'legiscan_local' => [
					'first_name' => 'first_name',
					'last_name' => 'last_name',
					'middle_name' => 'middle_name',
					'legiscan_id' => 'people_id',
					'bioguide_id' => 'bioguide_id',
					'votesmart_id' => 'votesmart_id',
					// Note: LegiScan uses "ballotpedia" as slug (not ballotpedia_id).
					'ballotpedia_id' => 'ballotpedia',
					'meta.opensecrets_id' => 'opensecrets_id',
					// Special mappings (require custom update logic)
					'websites.biography' => 'bio.social.biography',
					'websites.webmail' => 'bio.social.webmail',
					'session_image_url' => 'bio.social.image',
					'addresses.capitol' => 'bio.capitol_address',
					// Contact + social (mapped into normalized meta groups by admin updater).
					'meta.email' => 'bio.social.email',
					'meta.phone' => 'bio.social.capitol_phone',
					'meta.facebook' => 'bio.links.official.facebook',
					'meta.instagram' => 'bio.links.official.instagram',
					'meta.linkedin' => 'bio.links.official.linkedin',
					'meta.tiktok' => 'bio.links.official.tiktok',
					'meta.twitter' => 'bio.links.official.twitter',
					'meta.youtube' => 'bio.links.official.youtube',
				],
			];

			return $mappings[$source] ?? [];
		}

		/**
		 * Normalize LegiScan local person payload into the shape expected by our compare/update logic.
		 * - Combine capitol_address.address1/address2 into capitol_address.address
		 * - Unset address1/address2 to avoid UI updates to individual parts
		 */
		private static function normalize_legiscan_local_person(array $person): array {
			if (isset($person['bio']['capitol_address']) && is_array($person['bio']['capitol_address'])) {
				$ca = $person['bio']['capitol_address'];
				$address1 = (string) ($ca['address1'] ?? '');
				$address2 = (string) ($ca['address2'] ?? '');
				$combined = trim($address1 . ($address2 !== '' ? (' ' . $address2) : ''));
				if ($combined !== '') {
					$person['bio']['capitol_address']['address'] = $combined;
				}
				unset($person['bio']['capitol_address']['address1'], $person['bio']['capitol_address']['address2']);
			}
			return $person;
		}

		/**
		* Get nested value from array using dot notation
		*/
		private static function get_nested_value(array $data, string $path) {
			$keys = explode('.', $path);
			$value = $data;

			foreach ($keys as $key) {
				if (!is_array($value) || !array_key_exists($key, $value)) {
					return null;
				}
				$value = $value[$key];
			}

			return $value;
		}

		/**
		* Get our value from legislator object or meta
		*/
		private static function get_our_value(object $legislator, string $field, array $meta) {
			// Special pseudo-fields (used for repeater/session-image updates)
			if ($field === 'websites.biography') {
				$meta_norm = is_array($meta) ? LegislatorsMeta::normalize($meta) : [];
				$websites = $meta_norm['website'] ?? [];
				return is_array($websites) ? $websites : [];
			}
			if ($field === 'addresses.capitol') {
				$addr = LegislatorsMeta::get_capitol_address($legislator);
				if (!is_array($addr)) {
					return null;
				}
				// Represent in a LegiScan-like structure for compare/update.
				// Use a single combined address field (no address1/address2 parts).
				return [
					'address' => (string) ($addr['address'] ?? ''),
					'city' => (string) ($addr['city'] ?? ''),
					'state' => (string) ($addr['state'] ?? ''),
					'zip' => (string) ($addr['zip'] ?? ''),
				];
			}
			if ($field === 'session_image_url') {
				$sessions = $legislator->sessions ?? null;
				if (is_array($sessions) && !empty($sessions)) {
					$latest = reset($sessions);
					if (is_object($latest) && !empty($latest->image_id) && function_exists('wp_get_attachment_url')) {
						$url = wp_get_attachment_url((int) $latest->image_id);
						return $url ?: null;
					}
				}
				return null;
			}

			if (strpos($field, 'meta.') === 0) {
				$meta_key = substr($field, 5);
				
				// Meta is normalized into groups (contact/social/etc). Prefer those when present.
				if (in_array($meta_key, ['email', 'phone', 'fax'], true)) {
					if (isset($meta['contact']) && is_array($meta['contact']) && array_key_exists($meta_key, $meta['contact'])) {
						return $meta['contact'][$meta_key];
					}
				}
				
				// Social keys: normalize truth_social -> truthsocial, and remove underscores for storage.
				$social_key = $meta_key;
				if ($meta_key === 'truth_social') {
					$social_key = 'truthsocial';
				} elseif (strpos($meta_key, '_') !== false) {
					$social_key = str_replace('_', '', $meta_key);
				}
				if (isset($meta['social']) && is_array($meta['social'])) {
					if (array_key_exists($social_key, $meta['social'])) {
						return $meta['social'][$social_key];
					}
					if (array_key_exists($meta_key, $meta['social'])) {
						return $meta['social'][$meta_key];
					}
				}
				
				return $meta[$meta_key] ?? null;
			}

			return $legislator->{$field} ?? null;
		}

		/**
		* Compare two values
		* Returns: 'match', 'diff', or 'missing'
		*/
		private static function compare_values($our_value, $api_value): string {
			if ($api_value === null) {
				return 'missing';
			}

			// Special case: our value is a list (e.g., websites array) and API provides a scalar URL.
			if (is_array($our_value) && (is_scalar($api_value) || $api_value === null)) {
				if (empty($our_value)) {
					return 'missing';
				}
				$api_norm = self::normalize_value($api_value);
				foreach ($our_value as $v) {
					if (self::normalize_value($v) === $api_norm) {
						return 'match';
					}
				}
				return 'diff';
			}

			if ($our_value === null || $our_value === '') {
				return 'missing';
			}

			// Normalize for comparison
			$our_normalized = self::normalize_value($our_value);
			$api_normalized = self::normalize_value($api_value);

			return $our_normalized === $api_normalized ? 'match' : 'diff';
		}

		/**
		* Normalize value for comparison
		*/
		private static function normalize_value($value): string {
			if (is_array($value)) {
				$value = json_encode($value);
			}
			return strtolower(trim((string) $value));
		}

		/**
		* Find additional fields in API data
		*/
		private static function find_additional_fields(array $api_data, array $mappings, array $meta): array {
			$additional = [];
			$mapped_paths = array_values($mappings);

			// Flatten API data and check for unmapped fields
			$flattened = self::flatten_array($api_data);

			foreach ($flattened as $path => $value) {
				if (!in_array($path, $mapped_paths) && $value !== null && $value !== '') {
					$field_name = 'meta.' . str_replace('.', '_', $path);
					if (!isset($meta[str_replace('meta.', '', $field_name)])) {
						$additional[$field_name] = [
							'value' => $value,
							'path' => $path
						];
					}
				}
			}

			return $additional;
		}

		/**
		* Flatten nested array with dot notation keys
		*/
		private static function flatten_array(array $array, string $prefix = ''): array {
			$result = [];

			foreach ($array as $key => $value) {
				$new_key = $prefix ? "{$prefix}.{$key}" : $key;

				if (is_array($value)) {
					$result = array_merge($result, self::flatten_array($value, $new_key));
				} else {
					$result[$new_key] = $value;
				}
			}

			return $result;
		}

		/**
		* Get human-readable field label
		*/
		private static function get_field_label(string $field): string {
			$labels = [
				'first_name' => 'First Name',
				'last_name' => 'Last Name',
				'middle_name' => 'Middle Name',
				'display_name' => 'Display Name',
				'bioguide_id' => 'Bioguide ID',
				'legiscan_id' => 'Legiscan ID',
				'govtrack_id' => 'GovTrack ID',
				'votesmart_id' => 'VoteSmart ID',
				'ballotpedia_id' => 'Ballotpedia ID',
				'image_id' => 'Image',
				'websites.biography' => 'Biography (Website)',
				'addresses.capitol' => 'Capitol Address',
				'session_image_url' => 'Session Image',
			];

			if (strpos($field, 'meta.') === 0) {
				$meta_key = substr($field, 5);
				return ucwords(str_replace('_', ' ', $meta_key));
			}

			return $labels[$field] ?? ucwords(str_replace('_', ' ', $field));
		}

		/**
		* Fetch VoteSmart data
		*/
		public static function fetch_votesmart(string $votesmart_id): ?array {
			$api_key = defined('FI_API_KEY_VOTESMART') ? FI_API_KEY_VOTESMART : '';
			if (empty($api_key)) {
				return null;
			}

			$url = "https://api.votesmart.org/CandidateBio.getBio?key={$api_key}&candidateId={$votesmart_id}&o=JSON";
			
			$response = wp_remote_get($url, [
				'timeout' => 10,
				'sslverify' => true
			]);

			if (is_wp_error($response)) {
				return null;
			}

			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);

			return $data['bio'] ?? null;
		}

		/**
		* Search VoteSmart by legislator info
		*/
		private static function search_votesmart(object $legislator): ?array {
			// VoteSmart search requires more complex logic
			// For now, return null if no votesmart_id
			return null;
		}

		/**
		* Fetch GovTrack data by bioguide ID
		*/
		public static function fetch_govtrack_by_bioguide(string $bioguide_id): ?array {
			$url = "https://www.govtrack.us/api/v2/person?bioguide_id={$bioguide_id}&format=json";
			
			$response = wp_remote_get($url, [
				'timeout' => 10,
				'sslverify' => true
			]);

			if (is_wp_error($response)) {
				return null;
			}

			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);

			return $data['objects'][0] ?? null;
		}

		/**
		* Fetch GovTrack data by GovTrack ID
		*/
		public static function fetch_govtrack(string $govtrack_id): ?array {
			$url = "https://www.govtrack.us/api/v2/person/{$govtrack_id}?format=json";
			
			$response = wp_remote_get($url, [
				'timeout' => 10,
				'sslverify' => true
			]);

			if (is_wp_error($response)) {
				return null;
			}

			$body = wp_remote_retrieve_body($response);
			return json_decode($body, true);
		}

		/**
		* Search GovTrack by name
		*/
		private static function search_govtrack(object $legislator): ?array {
			$name = urlencode($legislator->display_name ?? "{$legislator->first_name} {$legislator->last_name}");
			$url = "https://www.govtrack.us/api/v2/person?name={$name}&format=json";
			
			$response = wp_remote_get($url, [
				'timeout' => 10,
				'sslverify' => true
			]);

			if (is_wp_error($response)) {
				return null;
			}

			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);

			// Return first match if available
			return $data['objects'][0] ?? null;
		}

		/**
		* Fetch Legiscan data (placeholder - would need Legiscan API integration)
		*/
		private static function fetch_legiscan(string $legiscan_id): ?array {
			// Legiscan API requires authentication and specific endpoints
			// This is a placeholder for future implementation
			return null;
		}
	}
}

/* Public helper functions */
namespace{
	function fi_api_fetch_all(int $legislator_id): array {
		return \FI\Core\ApiIntegration::fetch_all($legislator_id);
	}

	function fi_api_compare(int $legislator_id, string $source, array $api_data): array {
		return \FI\Core\ApiIntegration::compare_data($legislator_id, $source, $api_data);
	}

	function fi_api_fetch_votesmart(string $votesmart_id): ?array {
		return \FI\Core\ApiIntegration::fetch_votesmart($votesmart_id);
	}

	function fi_api_fetch_govtrack_by_bioguide(string $bioguide_id): ?array {
		return \FI\Core\ApiIntegration::fetch_govtrack_by_bioguide($bioguide_id);
	}

	function fi_api_fetch_govtrack(string $govtrack_id): ?array {
		return \FI\Core\ApiIntegration::fetch_govtrack($govtrack_id);
	}
}

