<?php
/**
 * Freedom Index by Sam Mittelstaedt <smittelstaedt@jbs.org>
 *
 * AJAX handlers: search + find-my-reps
 */

namespace FI\Public {
	if ( ! defined( 'ABSPATH' ) ) { exit; }

	trait AjaxHandlersSearchTrait {

		/**
		* Handle search autocomplete (suggestions only, no results)
		*/
		public function handle_search_autocomplete() {
			check_ajax_referer('fi_ajax_nonce', 'nonce');
			
			$term = sanitize_text_field($_POST['term'] ?? '');
			$limit = intval($_POST['limit'] ?? 10);
			
			if (strlen($term) < 3) {
				wp_send_json_success(array());
			}

			$cache_key = 'fi_ac_' . md5($term . '|' . $limit);
			$cached = get_transient($cache_key);
			if ($cached !== false) {
				wp_send_json_success($cached);
				return;
			}

			global $wpdb;
			
			// Search legislators across all governments — join to most recent session only
			$sql = "
				SELECT
					l.id, l.first_name, l.last_name, l.display_name,
					ls.party, ls.chamber, s.gov
				FROM {$wpdb->prefix}fi_legislators l
				INNER JOIN (
					SELECT legislator_id, MAX(session_id) AS max_session_id
					FROM {$wpdb->prefix}fi_legislator_sessions
					GROUP BY legislator_id
				) latest ON l.id = latest.legislator_id
				INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls
					ON ls.legislator_id = latest.legislator_id
					AND ls.session_id = latest.max_session_id
				INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
				WHERE (l.first_name LIKE %s OR l.last_name LIKE %s OR l.display_name LIKE %s)
				ORDER BY l.last_name, l.first_name
				LIMIT %d
			";

			$search_term = '%' . $wpdb->esc_like($term) . '%';
			$results = $wpdb->get_results($wpdb->prepare($sql, $search_term, $search_term, $search_term, $limit));

			$suggestions = array();
			foreach ($results as $result) {
				$chamber_label = !empty($result->chamber) ? fi_chamber_label($result->gov, $result->chamber) : '';
				$label = $result->display_name;
				if (!empty($result->party)) {
					$label .= ' (' . strtoupper($result->party) . ')';
					if (!empty($chamber_label)) {
						$label .= ' ' . $result->gov . ' ' . $chamber_label;
					} else {
						$label .= ' ' . $result->gov;
					}
				}
				
				$suggestions[] = array(
					'type' => 'legislator',
					'label' => $label,
					'value' => $result->display_name,
					'url' => home_url('/legislator/' . $result->id . '/')
				);
			}
			
			set_transient($cache_key, $suggestions, 60);
			wp_send_json_success($suggestions);
		}

		/**
		* Handle legislator name search (full results with cards)
		*/
		public function handle_legislator_search() {
			// Prevent any output before JSON response
			if (ob_get_level()) {
				ob_clean();
			}
			check_ajax_referer('fi_ajax_nonce', 'nonce');
			$search = sanitize_text_field($_POST['search'] ?? '');
			$clear = isset($_POST['clear']) && $_POST['clear'] === '1';

			// Handle clear search request
			if ($clear || (empty($search) && isset($_POST['clear']))) {
				$html = '';
				wp_send_json_success(array(
					'html' => $html,
					'count' => 0,
					'cleared' => true
				));
				return;
			}

			$cacheKey = 'search/_';
			if (strlen($search) > 3) {
				$cacheKey .= urlencode(strtolower($search));
				$value = ''; //fi_cache($cacheKey);
				if ($value) {
					wp_send_json_success($value);
				}
			}

			try {
				// Allow empty search to return cleared state
				if (empty($search)) {
					$html = '';
					wp_send_json_success(array(
						'html' => $html,
						'count' => 0,
						'cleared' => true
					));
					return;
				}
				
				if (strlen($search) < 3) {
					ob_start();
					?>
					<div class="row">
						<div class="col-12 col-md-8 col-lg-6 mx-auto">
							<div class="alert alert-warning text-center py-3">
								<h4 class="mb-3">Search Too Short</h4>
								<p class="mb-0">Please enter at least 3 characters to search.</p>
							</div>
						</div>
					</div>
					<?php
					$html = ob_get_clean();
					
					wp_send_json_success(array(
						'html' => $html,
						'count' => 0,
						'message' => 'Please enter at least 3 characters'
					));
					return;
				}
				// Search legislators across all governments (no gov filter)
				$results = fi_legislators(['search' => $search, 'limit' => 50, 'include_session_gov' => false]);

				// Ensure results is an array
				if (!is_array($results)) {
					$results = array();
				}
				
				ob_start();
				$count = 0;
				$legislators = array();
				//echo '<div class="container-xl px-0">
				echo '<div class="row">';
				if(count($results) == 0) {
					echo '<div class="col-12 col-md-8 col-lg-6 mx-auto">
							<div class="alert alert-info text-center py-3">
								<h4 class="mb-3">No results found for search: <strong>' . $search . '</strong></h4>
								<p class="mb-0">Please try again with different or partial name of any state or federal legislator.</p>
							</div>
						</div>';
				}

				foreach ($results as $result) {
					if (!is_object($result)) {
						continue; // Skip invalid results
					}
					//There are a few very old legislators that do not appear to be assigned to sessions and thus trigger "no gov" error. Skip if no gov.
					if (empty($result->gov)) {
						continue;
					}
					$count++;
					// Safely get photo URL
					$photo_url = '';
					if (!empty($result->image_id) && function_exists('jis_get_attachment_image_src')) {
						$photo = jis_get_attachment_image_src($result->image_id, [80, 100]);
						if (is_array($photo) && isset($photo['src'])) {
							$photo_url = $photo['src'];
						}
					}

					// Get legislator URL
					$legislator_url = '';
					if (!empty($result->url)) {
						$legislator_url = $result->url;
					} elseif (function_exists('fi_get_legislator_url') && !empty($result->id)) {
						$legislator_url = fi_get_legislator_url($result->id);
					} elseif (!empty($result->id)) {
						$legislator_url = home_url('/legislator/' . $result->id . '/');
					}
					if($result->gov == 'US'){
						$result->gov_name = 'U.S. Congress (' . $result->state_name . ')';
					}
/*
public/ajax/trait-search.php:184 Debug legislator search result: {
"id":1414,"first_name":"Thomas","middle_name":null,"last_name":"Massie","display_name":"Thomas Massie","image_id":15676,
"image_url":"https:\/\/freedomindex.us\/assets\/sites\/5\/img\/15676\/1414_Massie_Thomas_119th_Congress_28cropped_229-200x250-f50_50.jpg",
"bioguide_id":"M001184","lis_id":"","legiscan_id":14026,"govtrack_id":"412503","votesmart_id":"132068","ballotpedia_id":"Thomas Massie",
"url":"https:\/\/freedomindex.us\/legislator\/1414\/","freedom_score":99,
"freedom_score_data":{"score":99,"total":250,"good":240,"bad":3,"not":7,"scored":243},
"freedom_score_date":"2026-02-20 23:21:35",
"meta":{"legacy_post":{"id":142971,"post_name":"M001184","image_url":""},
	"contact":{"phone":"(202) 225-3465"},"address":[{"name":"Capitol Office","type":"capitol","address":"2371 Rayburn House Office Building","state":"DC","zip":"20515-1704"},
		{"name":"Crescent Springs Office","type":"district","address":"541 Buttermilk Pike","city":"Crescent Springs","state":"KY","zip":"41017","phone":"859-426-0080"},
		{"name":"LaGrange Office","type":"district","address":"110 W. Jefferson St.","city":"LaGrange","state":"KY","zip":"40031","phone":"502-265-9119"}],
	"website":["https:\/\/massie.house.gov"],"hometown":"Garrison","opensecrets_id":"N00034041",
	"legiscan_data":{"people_id":14026,"person_hash":"zz9y056y","party_id":"2","state_id":52,"party":"R","role_id":1,"role":"Rep","name":"Thomas Massie","first_name":"Thomas","middle_name":"","last_name":"Massie","suffix":"","nickname":"","district":"HD-KY-4","ftm_eid":9195688,
		"votesmart_id":132068,"opensecrets_id":"N00034041","knowwho_pid":391510,"ballotpedia":"Thomas_Massie","bioguide_id":"M001184",
		"committee_sponsor":0,"committee_id":0,"state_federal":0,
		"bio":{"social":{"capitol_phone":"202-225-3465","district_phone":"","email":"","webmail":"https:\/\/massie.house.gov","biography":"https:\/\/clerk.house.gov\/members\/M001184","image":"https:\/\/clerk.house.gov\/content\/assets\/img\/members\/M001184","ballotpedia":"https:\/\/ballotpedia.org\/Thomas_Massie",
		"votesmart":"https:\/\/justfacts.votesmart.org\/candidate\/biography\/132068"},"capitol_address":{"address1":"2371 Rayburn House Office Building","address2":"","city":"Washington","state":"DC","zip":"20515"},"links":{"official":{"bluesky":"","facebook":"https:\/\/www.facebook.com\/RepThomasMassie","instagram":"https:\/\/www.instagram.com\/repthomasmassie\/","linkedin":"","tiktok":"","twitter":"https:\/\/www.twitter.com\/RepThomasMassie",
		"website":"https:\/\/clerk.house.gov\/members\/M001184","youtube":"https:\/\/www.youtube.com\/user\/RepThomasMassie\/"},"personal":{"bluesky":"","facebook":"https:\/\/www.facebook.com\/thomas.massie.31","instagram":"","linkedin":"","tiktok":"","twitter":"","website":"","youtube":""}}}},
		"knowwho_pid":391510,"ftm_eid":9195688,"social":{"facebook":"https:\/\/www.facebook.com\/RepThomasMassie","instagram":"https:\/\/www.instagram.com\/repthomasmassie","twitter":"https:\/\/www.x.com\/RepThomasMassie","youtube":"https:\/\/www.youtube.com\/user\/repthomasmassie"},"url_wikipedia":"https:\/\/en.wikipedia.org\/wiki\/Thomas_Massie","url_wikidata":"https:\/\/www.wikidata.org\/wiki\/Q2426031","birth_date":"1971-01-13","gender":"male","phone":"202-225-3465",
"legacy":{"hometown":"Garrison"}},"audit_log":null,
"date_created":"2026-01-25 22:03:45",
"date_updated":"2026-02-22 10:57:56","score":99,
"district_info":{"id":"361","gov":"US","name":"KY 4th","slug":"ky-4","name_short":"4th"},
"state_name":"Kentucky","session_id":14,
"session_name":"119th Congress","gov":"US",
"state":"KY","party":"R","chamber":"H","district":"361",
"score_data":{"total":20,"good":17,"bad":0,"not":3,"scored":17},"session_image_id":14643,"date_start":null,"date_end":null,
"gov_name":"Congress",
"party_name":"Republican","chamber_label":"House","chamber_title":"Representative","session_score":100,"sessions":{"14":{"session_id":14,"parent_id":null,"legiscan_id":2199,"session_name":"119th Congress","gov":"US","state":"KY","party":"R","chamber":"H","district":"361","score":100,"score_data":{"total":20,"good":17,"bad":0,"not":3,"scored":17},"date_start":"2025-01-03","date_end":"2026-12-31","image_id":14643,"status":"publish","gov_name":"Congress","party_name":"Republican","chamber_label":"House","chamber_title":"Representative","state_name":"Kentucky"},"13":{"session_id":13,"parent_id":null,"legiscan_id":2041,"session_name":"118th Congress","gov":"US","state":"KY","party":"R","chamber":"H","district":"361","score":97,"score_data":{"total":40,"good":36,"bad":1,"not":3,"scored":37},"date_start":"2023-01-01","date_end":"2024-12-31","image_id":null,"status":"publish","gov_name":"Congress","party_name":"Republican","chamber_label":"House","chamber_title":"Representative","state_name":"Kentucky"},"12":{"session_id":12,"parent_id":null,"legiscan_id":null,"session_name":"117th Congress","gov":"US","state":"KY","party":"R","chamber":"H","district":"361","score":100,"score_data":{"total":40,"good":40,"bad":0,"not":0,"scored":40},"date_start":null,"date_end":null,"image_id":null,"status":"publish","gov_name":"Congress","party_name":"Republican","chamber_label":"House","chamber_title":"Representative","state_name":"Kentucky"},"11":{"session_id":11,"parent_id":null,"legiscan_id":null,"session_name":"116th Congress","gov":"US","state":"KY","party":"R","chamber":"H","district":"361","score":100,"score_data":{"total":30,"good":30,"bad":0,"not":0,"scored":30},"date_start":null,"date_end":null,"image_id":null,"status":"publish","gov_name":"Congress","party_name":"Republican","chamber_label":"House","chamber_title":"Representative","state_name":"Kentucky"},"10":{"session_id":10,"parent_id":null,"legiscan_id":null,"session_name":"115th Congress","gov":"US","state":"KY","party":"R","chamber":"H","district":"361","score":98,"score_data":{"total":40,"good":39,"bad":1,"not":0,"scored":40},"date_start":null,"date_end":null,"image_id":null,"status":"publish","gov_name":"Congress","party_name":"Republican","chamber_label":"House","chamber_title":"Representative","state_name":"Kentucky"},"9":{"session_id":9,"parent_id":null,"legiscan_id":null,"session_name":"114th Congress","gov":"US","state":"KY","party":"R","chamber":"H","district":"361","score":100,"score_data":{"total":40,"good":39,"bad":0,"not":1,"scored":39},"date_start":null,"date_end":null,"image_id":null,"status":"publish","gov_name":"Congress","party_name":"Republican","chamber_label":"House","chamber_title":"Representative","state_name":"Kentucky"},"8":{"session_id":8,"parent_id":null,"legiscan_id":null,"session_name":"113th Congress","gov":"US","state":"KY","party":"R","chamber":"H","district":"361","score":98,"score_data":{"total":40,"good":39,"bad":1,"not":0,"scored":40},"date_start":null,"date_end":null,"image_id":null,"status":"publish","gov_name":"Congress","party_name":"Republican","chamber_label":"House","chamber_title":"Representative","state_name":"Kentucky"}}}
*/


					$card_args = [
						'name' => $result->display_name ?? '',
						'gov_name' => $result->gov_name ?? $result->gov ?? '',
						'party' => $result->party_name ?? $result->party ?? '',
						'chamber' => $result->chamber_label ?? $result->chamber ?? '',
						'photo_url' => $photo_url,
						'score' => $result->freedom_score ?? null,
						'score_label' => 'Freedom Score',
						'legislator' => [
							'id' => $result->id ?? 0,
							'url' => $legislator_url,
						],
					];
// Debug JSON dump removed.
					fi_get_template('partials/legislator-card-sm', $card_args);
				}
				echo '</div>';
				//echo '</div>';
				$html = ob_get_clean();

				$result = array(
					'html' => $html,
					'count' => $count
				);
				fi_cache($cacheKey, $result );
				wp_send_json_success($result);
			} catch (\Exception $e) {
				// Log error for debugging
				self::log('Legislator search error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine() . ' | Trace: ' . $e->getTraceAsString(), __FILE__, __LINE__, 'error');
				wp_send_json_error(array('message' => 'An error occurred while searching. Please try again.'));
			} catch (\Error $e) {
				self::log('Legislator search fatal error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine() . ' | Trace: ' . $e->getTraceAsString(), __FILE__, __LINE__, 'error');
				wp_send_json_error(array('message' => 'An error occurred while searching. Please try again.'));
			}
		}

		/**
		* Handle find my representatives lookup
		*/
		public function handle_find_representatives() {
			self::log('handle_find_representatives:post: ' . json_encode($_POST), __FILE__, __LINE__);

			//core/api_geocod
			$data = fi_geocod_get_officials();
			//self::log('handle_find_representatives:data: ' . json_encode($data), __FILE__, __LINE__);
			self::log('handle_find_representatives:data: ' . count($data) . ' officials found', __FILE__, __LINE__);

			// Generate HTML for results using compact card partial
			ob_start();
			if ( isset($data['officials']) && !empty($data['officials']) && is_array($data['officials'])) {
				echo '<div class="container-xl"><div class="row">';
				foreach ($data['officials'] as $official) {
					fi_get_template('partials/legislator-card-sm', $official);
				}
				echo '</div></div>';
			} else {
				echo '<div class="alert alert-warning">No officials found for the provided address: ' . esc_html($address) . '</div>';
			}
			$html = ob_get_clean();
			
			wp_send_json_success(array(
				'html' => $html,
				'address' => $data['address'],
			));
		}
	}
}

