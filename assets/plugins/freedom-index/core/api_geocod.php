<?php
namespace FI\Core{

	if (!defined('ABSPATH')) exit;

	/**
	* Government Constants and Helpers
	* 
	* Centralized government management for the Freedom Index system.
	* All government codes are 2-letter state abbreviations or 'US' for Congress.
	* https://api.geocod.io/v1.9/geocode?q=75645&fields=cd,stateleg&api_key=73713543404bb2183071611b86a4605a8666a15
	*/

	final class Geocod {

		public static function address() {
			$zip = isset($_POST['zip']) ? sanitize_text_field($_POST['zip']) : '';
			if (empty($zip)) {
				wp_send_json_error(array('message' => 'ZIP code is required'));
			}
			
			// Check if full address fields are provided
			$address = '';
			if (isset($_POST['address']) && !empty($_POST['address']) && 
			    isset($_POST['city']) && !empty($_POST['city']) && 
			    isset($_POST['state']) && !empty($_POST['state'])) {
				// Full address provided
				$address = sanitize_text_field($_POST['address']) . ' ' . 
				           sanitize_text_field($_POST['city']) . ' ' . 
				           sanitize_text_field($_POST['state']) . ' ' . 
				           $zip;
			} else {
				// ZIP only
				$address = $zip;
			}
			return $address;
		}

		public static function address_encoded($address) {
			return urlencode($address);
		}

		public static function party_code($party_name) {
			$party = '';
			switch($party_name){
				case 'Democrat':
					$party = 'D';
					break;
				case 'Republican':
					$party = 'R';
					break;
				case 'Libertarian':
					$party = 'L';
					break;
			}
			return $party;
		}


		public static function geocod_get_officials($address_encoded) {
			$cacheKey = 'findmy/' . $address_encoded;
			$officials = fi_cache($cacheKey); //default to 1 day for testing then extend: , '', (30 * 24 * 60 * 60)); //30 days
			if ($officials) {
				return $officials;
			}
			//API call
			$geocode_url = 'https://api.geocod.io/v1.9/geocode?q=' . $address_encoded . '&fields=cd,stateleg&api_key=' . API_KEY_GEOCOD;
			$geocode_response = wp_remote_get($geocode_url);
			$geocode_data = json_decode(wp_remote_retrieve_body($geocode_response), true);

			$senators = [];
			$representatives = [];

			// Check if we have results and fields
			if (isset($geocode_data['results'][0]['fields'])) {
				$fields = $geocode_data['results'][0]['fields'];

				// Process congressional legislators (Congressional Districts)
				if (isset($fields['congressional_districts']) && !empty($fields['congressional_districts'])) {
					foreach ($fields['congressional_districts'] as $district) {
						if (isset($district['current_legislators']) && !empty($district['current_legislators'])) {
							foreach ($district['current_legislators'] as $legislator) {
								//Zip only may return multiple districts with overlapping senators, so we need to check for duplicates
								$key = $legislator['type'] . $legislator['bio']['first_name'] . $legislator['bio']['last_name'];
								$party_name = $legislator['bio']['party'];
								$party = self::party_code($party_name);
								$references = isset($legislator['references']) ? $legislator['references'] : null;
								$photo_url_from_api = isset($legislator['bio']['photo_url']) ? $legislator['bio']['photo_url'] : null;

								$official = [
									'name' => $legislator['bio']['first_name'] . ' ' . $legislator['bio']['last_name'],
									'party' => $party,
									'party_name' => $party_name,
									'chamber' => ucfirst($legislator['type']) . ' - ' . $district['name'],
									'division' => $district['name'],
									'contact' => $legislator['contact'],
									'social' => $legislator['social'],
									'bio' => $legislator['bio'],
									'photo_url' => isset($legislator['bio']['photo_url']) ? $legislator['bio']['photo_url'] : null,
									'birthday' => isset($legislator['bio']['birthday']) ? $legislator['bio']['birthday'] : null,
									'gender' => isset($legislator['bio']['gender']) ? $legislator['bio']['gender'] : null,
									'seniority' => isset($legislator['seniority']) ? $legislator['seniority'] : null,
									'references' => isset($legislator['references']) ? $legislator['references'] : null,
								];
								if($legislator['type'] == 'representative') {
									$representatives[$key] = $official;
								} else {
									$senators[$key] = $official;
								}
							}
						}
					}
				}

				// Remove duplicate keys from $representatives array
				$representatives = array_unique($representatives, SORT_REGULAR);
				$senators = array_unique($senators, SORT_REGULAR);

				// Create unique of congressional and state officials
				$officials = array_merge($senators, $representatives);

				// Process state legislators
				if (isset($fields['state_legislative_districts'])) {
					$state_districts = $fields['state_legislative_districts'];

					// State Senators
					if (isset($state_districts['senate']) && !empty($state_districts['senate'])) {
						foreach ($state_districts['senate'] as $district) {
							if (isset($district['current_legislators']) && !empty($district['current_legislators'])) {
								foreach ($district['current_legislators'] as $legislator) {
									$party_name = $legislator['bio']['party'];
									$party = self::party_code($party_name);
									$references = isset($legislator['references']) ? $legislator['references'] : null;
									$photo_url_from_api = isset($legislator['bio']['photo_url']) ? $legislator['bio']['photo_url'] : null;

									$official = [
										'name' => $legislator['bio']['first_name'] . ' ' . $legislator['bio']['last_name'],
										'party' => $party,
										'party_name' => $party_name,
										'chamber' => 'State Senator - ' . $district['name'],
										'division' => $district['name'],
										'contact' => $legislator['contact'],
										'social' => $legislator['social'],
										'bio' => $legislator['bio'],
										'photo_url' => isset($legislator['bio']['photo_url']) ? $legislator['bio']['photo_url'] : null,
										'birthday' => isset($legislator['bio']['birthday']) ? $legislator['bio']['birthday'] : null,
										'gender' => isset($legislator['bio']['gender']) ? $legislator['bio']['gender'] : null,
										'seniority' => isset($legislator['seniority']) ? $legislator['seniority'] : null,
										'references' => isset($legislator['references']) ? $legislator['references'] : null,
									];
									$officials[] = $official;
								}
							}
						}
					}

					// State House Representatives
					if (isset($state_districts['house']) && !empty($state_districts['house'])) {
						foreach ($state_districts['house'] as $district) {
							if (isset($district['current_legislators']) && !empty($district['current_legislators'])) {
								foreach ($district['current_legislators'] as $legislator) {
									$party_name = $legislator['bio']['party'];
									$party = self::party_code($party_name);
									$references = isset($legislator['references']) ? $legislator['references'] : null;
									$photo_url_from_api = isset($legislator['bio']['photo_url']) ? $legislator['bio']['photo_url'] : null;
									$official = [
										'name' => $legislator['bio']['first_name'] . ' ' . $legislator['bio']['last_name'],
										'party' => $party,
										'party_name' => $party_name,
										'chamber' => 'State Representative - ' . $district['name'],
										'division' => $district['name'],
										'contact' => $legislator['contact'],
										'social' => $legislator['social'],
										'bio' => $legislator['bio'],
										'photo_url' => isset($legislator['bio']['photo_url']) ? $legislator['bio']['photo_url'] : null,
										'birthday' => isset($legislator['bio']['birthday']) ? $legislator['bio']['birthday'] : null,
										'gender' => isset($legislator['bio']['gender']) ? $legislator['bio']['gender'] : null,
										'seniority' => isset($legislator['seniority']) ? $legislator['seniority'] : null,
										'references' => isset($legislator['references']) ? $legislator['references'] : null,
									];
									$officials[] = $official;
								}
							}
						}
					}
				}
			}

			//Merge with Freedom Index legislators
			$officials = self::merge_legislators_to_officials($officials);
			fi_cache($cacheKey,$officials);
			return $officials;
		}

		//Fetch all Freedom Index legislators and merge with geocod API results
		public static function merge_legislators_to_officials($officials) {
			$merged = [];
			foreach($officials as $official) {
				//fi_log('MY-fetch: ' . json_encode($official), __FILE__, __LINE__);
				$legislator = self::get_legislator($official['references']);
				$official = array_merge($official, $legislator);
				if(	isset($official['id']) && $official['id'] > 0) {
					self::legislator_update($official);
				}
				$merged[] = $official;
			}
			return $merged;
		}


		public static function get_legislator($references) {
			// Use helper function to get legislator by external ID (no custom queries)
			$legislator = fi_legislator_get_by_external_id($references ?? []);

			// Flatten the legislator array into a single array of most recent data
			$legislator_id = $legislator ? ($legislator->id ?? 0) : 0;
			$legislator_url = '';
			if ($legislator_id > 0) {
				// Generate URL using ID if not already set
				if (isset($legislator->url) && !empty($legislator->url)) {
					$legislator_url = $legislator->url;
				} elseif (function_exists('fi_get_legislator_url')) {
					$legislator_url = fi_get_legislator_url($legislator_id);
				} else {
					$legislator_url = home_url('/legislator/' . $legislator_id . '/');
				}
				//Are we missing their image still?
				if($legislator->image_id && $legislator->image_id > 0 && !$legislator->image_url){
					$image_url = jis_get_attachment_image_src($legislator->image_id, [200,250],true);
					if($image_url['src'] != ''){
						$legislator->image_url = $image_url['src'];
					}
				}


				//Merge geocod fields + add ['legislator'] with our values
				$legislator_data = array(
					'name' => $legislator->display_name ?? '',
					//'party' => $legislator->party ?? '',
					//'party_name' => $legislator->party_name ?? '',
					//'chamber' => $legislator->chamber_label ?? '',
					//'division' => $legislator->district ?? '',
					'score' => $legislator->freedom_score ?? '',
					'score_label' => 'Freedom Score',
					'legislator' => [
						'id' => $legislator_id,
						'url' => $legislator_url,
						'image_id' => $legislator->image_id ?? '',
						'first_name' => $legislator->first_name ?? '',
						'last_name' => $legislator->last_name ?? '',
						'district' => $legislator->district ?? '',
						'gov' => $legislator->gov ?? '',
						'state' => $legislator->state ?? '',
						'state_name' => $legislator->state_name ?? '',
					],
				);
				if($legislator->image_url && $legislator->image_url != '') {
					$legislator_data['photo_url'] = $legislator->image_url;
				}

			}else{
				$legislator_data = [];
			}
			//fi_log('MY-legislator: ' . json_encode($legislator_data), __FILE__, __LINE__);
			return $legislator_data;
		}

		/* Take this opportunity to update the legislator data
		*/
		public static function legislator_update($official) {
			$legislator_id = $official['id'] ??	0;
			if($legislator_id > 0) {
				// Reference fields that go directly on the legislator table (must exist in schema)
				$direct_fields = [
					'bioguide_id',
					'legiscan_id',
					'govtrack_id',
					'votesmart_id',
					'ballotpedia_id',
					'openstates_id',
				];
				$legislatorUpdate = [];
				foreach ($direct_fields as $field) {
					if (isset($official['references'][$field]) && $official['references'][$field] !== '') {
						$legislatorUpdate[$field] = $official['references'][$field];
					}
				}

				// Meta fields for the legislator meta column (will be merged, not replaced)
				$meta_fields = [
					//'chamber',
					//'division',
					'contact' => [
						'url',
						'address',
						'phone',
						'contact_form',
					],
					'social' => [
						'rss_url',
						'twitter',
						'facebook',
						'youtube',
						'youtube_id',
					],
					'bio' => [
						'birthday',
						'gender',
						'party',
						'photo_url',
						'photo_attribution',
					],
					//'photo_url',
					'birthday',
					'gender',
					'seniority',
					'references' => [
						'openstates_id' => 'openstates_id',
						'wikipedia_id' => 'wikipedia_id',
						'thomas_id' => 'thomas_id',
						'opensecrets_id' => 'opensecrets_id',
						'lis_id' => 'lis_id',
						'washington_post_id' => 'washington_post_id',
					],
				];
						
				$meta_data = [];
				// Consume $meta_fields and flatten their values into $meta_data if they exist and have a value
				foreach ($meta_fields as $key => $field) {
					if (is_array($field)) {
						// This is a group; check the subfields in $official[$key] (or $official['references'] for group keys that are 'references')
						$section = ($key === 'references') ? ($official['references'] ?? []) : ($official[$key] ?? []);
						foreach ($field as $subkey => $subfield) {
							// If associative, $subkey is the name, else $subfield is the field name
							$target = is_int($subkey) ? $subfield : $subkey;
							if (isset($section[$target]) && $section[$target] !== '') {
								$meta_data[$target] = $section[$target];
							}
						}
					} else {
						// Flat field, just check $official[$field]
						if (isset($official[$field]) && $official[$field] !== '') {
							$meta_data[$field] = $official[$field];
						}
					}
				}
				$legislatorUpdate['meta'] = array_merge($legislatorUpdate['meta'] ?? [], $meta_data);
				
				// Update direct fields if any
				if (!empty($legislatorUpdate)) {
					fi_legislator_update($legislator_id, $legislatorUpdate);
				}				
			}
		}
	}
}

namespace{
	function fi_geocod_get_officials() {
		$address = \FI\Core\Geocod::address();
		$address_encoded = \FI\Core\Geocod::address_encoded($address);
		$officials = \FI\Core\Geocod::geocod_get_officials($address_encoded);
		//fi_log('fi_geocod_get_officials: ' . $address_encoded . '|'. count($officials) . ' found', __FILE__, __LINE__);
		$data = [
			'address' => $address,
			'officials' => $officials,
		];
		if(get_current_user_id() == 1 && isset($_GET['TEST']) && $_GET['TEST'] == 'geocod'){
			echo '<textarea style="width: 100%; height: 400px;">';print_r($data);echo '</textarea>';exit;
		}
		return $data;
	}

	function fi_geocod_get_officials_for_user_dashboard(string $address) {
		$address_encoded = urlencode($address);

		if($address_encoded){
			$officials = \FI\Core\Geocod::geocod_get_officials($address_encoded);
			//fi_log('fi_geocod for dashboard: ' . $address_encoded . '|'. count($officials) . ' found', __FILE__, __LINE__);
			$data = [
				'address' => $address,
				'officials' => $officials,
			];
			return $data;
		}
	}

}