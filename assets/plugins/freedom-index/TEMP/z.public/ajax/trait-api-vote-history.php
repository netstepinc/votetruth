<?php
/**
 * Freedom Index by Sam Mittelstaedt <smittelstaedt@jbs.org>
 *
 * AJAX handlers: legislator vote history
 */

namespace FI\Public {
	if ( ! defined( 'ABSPATH' ) ) { exit; }

	trait AjaxHandlersApiVoteHistoryTrait {

		/**
		 * Handle legislator vote history loading (all votes, session, report, tag)
		 */
		public function handle_api_legislator_vote_history() {
			check_ajax_referer('fi_ajax_nonce', 'nonce');
			
			$legislator_id = intval($_POST['legislator_id'] ?? 0);
			$chamber = strtoupper(sanitize_text_field($_POST['chamber'] ?? ''));
			$party = strtoupper(sanitize_text_field($_POST['party'] ?? ''));
			$view = sanitize_text_field($_POST['view'] ?? 'all');
			$session_id = !empty($_POST['session_id']) ? intval($_POST['session_id']) : null;
			$report_id = !empty($_POST['report_id']) ? intval($_POST['report_id']) : null;
			$tag_id = !empty($_POST['tag_id']) ? intval($_POST['tag_id']) : null;
			
			// Get gov from session if available
			$gov = null;
			if ($session_id) {
				$session = fi_session_get($session_id);
				$gov = $session->gov ?? null;
			}

			//Chambers
			$chambers = fi_chamber_options($gov);

			// For Sessions and Reports we need to get the legislator's State, Chamber and District during that session. i.e. subtitle: Florida Senator
/* Let's try to get this from the API instead.
			if($gov && $chamber){
				$chamber_label = $chambers[$chamber]['chamber'] ?? ''; //fi_chamber_label($gov, $chamber);
				$party = $party ? ' ('.$party.')' : '';
				$gov_name = fi_gov_name($gov);
				if($gov == 'US'){
					$gov_name = 'United States';
				}
				$subtitle = $gov_name . ' ' . $chamber_label . $party;
			}else{
				$subtitle = '';
			}
*/

			if (!$legislator_id || !$chamber) {
				wp_send_json_error('Invalid parameters');
			}
			
			$result = [
				'title' => '',
				'subtitle' => '',
				'score' => 0,
				'report_header' => null,
				'votes' => []
			];
			
			// Get votes based on view type. vote status will always be 'publish'. Default limit is 1000.
			// Payload will include title, subtitle, score, votes.
			// Report format is not relevant to the legislator vote history.
			switch ($view) {
				case 'all':
					// Get all votes for legislator: no gov restriction.
					$result = fi_api_request([
						'action' => 'votes_legislator',
						'legislator_id' => $legislator_id,
					]);
					break;
					
				case 'tag':
					if (!$tag_id) {
						wp_send_json_error('Tag ID required');
					}
					// Get votes by tag and only returns votes where legislator has a rollcall.
					$result = fi_api_request([
						'action' => 'votes_tag_legislator',
						'tag_id' => $tag_id,
						'legislator_id' => $legislator_id,
					]);
					break;
					
				case 'session':
					if (!$session_id) {
						wp_send_json_error('Session ID required');
					}
					$result = fi_api_request([
						'action' => 'votes_session_legislator',
						'session_id' => $session_id,
						'legislator_id' => $legislator_id,
					]);
					break;
					
				case 'report':
					if (!$session_id || !$report_id) {
						wp_send_json_error('Session ID and Report ID required');
					}
					$result = fi_api_request([
						'action' => 'votes_report_legislator',
						'report_id' => $report_id,
						'legislator_id' => $legislator_id,
					]);
					break;
					
				default:
					wp_send_json_error('Invalid view type');
			}

/* Let's try to get this from the API instead.
			// Calculate score inline
			$votes_good = 0;
			$votes_scored = 0;
			
			foreach ($votes as $vote) {
				// Ensure we have rollcall data (cast)
				if (empty($vote->cast)) {
					$rollcall = fi_rollcall_get($vote->id ?? 0, $legislator_id);
					if ($rollcall) {
						$vote->cast = $rollcall->cast ?? 'X';
					} else {
						$vote->cast = 'X';
					}
				}
				
				$cast = $vote->cast ?? 'X';
				$constitutional = $vote->constitutional ?? '';
				
				// Only Y/N count toward scored votes (P/A/X are "not voted")
				if (!in_array($cast, ['P', 'A', 'X', ''], true) && !empty($constitutional)) {
					$votes_scored++;
					if ($cast === $constitutional) {
						$votes_good++;
					}
				}
			}
			
			// Calculate percentage score
			$result['score'] = fi_score_calculate($votes_good, $votes_scored);//($votes_scored > 0) ? round(($votes_good / $votes_scored) * 100, 0) : 0;
*/

			// Render vote cards HTML server-side
			ob_start();
			
			// Render report header if present
			if (!empty($result['report_header'])) {
				echo '<div class="mb-3 p-2 p-lg-3">';
				echo '<div class="fs-7">' . wp_kses_post($result['report_header']['content'] ?? '') . '</div>';
				echo '</div>';
			}
			
			// Report format from API response (set from fi_reports.format in vote-history handler)
			$report_format = $result['report_format'] ?? 'scorecard';

			// Render vote cards
			if (empty($result['votes'])) {
				echo '<div class="alert alert-info">No votes found for this selection.</div>';
			} else {
				echo '<div class="row g-3" id="fi-vote-cards-container">';
				
				foreach ($result['votes'] as $vote) {
					// Process vote data for vote-card partial
					$vote_meta = fi_vote_decode_meta($vote);

					// Get description
					$description = fi_vote_get_description($vote_meta, 'small');

					$chamber = $vote->chamber;
					$chamber_label = fi_chamber_label($gov, $chamber);
					
					// Format date
					$formatted_date = '';
					if (!empty($vote->date_voted)) {
						$timestamp = strtotime($vote->date_voted);
						if ($timestamp !== false) {
							$formatted_date = date('n/j/Y', $timestamp);
						} else {
							$formatted_date = $vote->date_voted;
						}
					}
					
					// Get vote format (with cast for legislator vote history)
					$cast = $vote->cast ?? 'X';
					$vote_format = fi_vote_format([
						'cast' => $cast,
						'constitutional' => $vote->constitutional ?? '',
						'format' => 'full'
					]);
					
					// Format cost
					$cost_html = '';
					$cost_value = $vote_meta['cost'] ?? '';
					if (!empty($cost_value)) {
						$cost = fi_vote_cost_format($cost_value);
						$cost_html = $cost['html'] ?? '';
					}
					
					// Build URLs
					$url_vote = fi_url_vote($vote->gov ?? '', $vote->id ?? 0);
					$bill_url = $vote_meta['url_bill'] ?? '';
					$text_more = fi_vote_get_description($vote_meta, 'freedomindex');
					$tags = $vote_meta['tags'] ?? [];
					
					// Build search text
					$search_text = strtolower(($vote->title ?? '') . ' ' . ($vote->bill_number ?? '') . ' ' . strip_tags($description));
					
					// Prepare vote card data
					$vote_data = [
						'id' => $vote->id ?? 0,
						'gov' => $vote->gov ?? '',
						'title' => $vote->title ?? '',
						'text' => $description,
						'text_more' => $text_more,
						'tags' => $tags,
						'bill_number' => $vote->bill_number ?? '',
						'constitutional' => $vote->constitutional ?? '',
						'date_voted' => $vote->date_voted ?? '',
						'date_formatted' => $formatted_date,
						'vote_format' => $vote_format,
						'bill_url' => $bill_url,
						'cost_html' => $cost_html,
						'url_vote' => $url_vote,
						'search_text' => $search_text,
						'report_format' => $report_format,
						'chamber_title' => true,
						'chamber_label' => $chambers[$vote->chamber]['chamber'],
						// For legislator vote history, show cast and link
						'show_cast' => true,
						'show_link' => true,
						'cast' => $cast,
					];
					fi_get_template('partials/vote-card', $vote_data);
				}
				
				echo '</div>';
			}
			
			$html = ob_get_clean();
			
			$result['html'] = $html;
			$result['title'] = $result['title'] ?? 'Votes';
			
			// Write debug output to log file
			$debug = "Total votes in result: " . count($votes) . " | Score: " . ($result['score'] ?? 'NULL') . " | Title: " . ($result['title'] ?? 'NULL') . " | HTML length: " . strlen($html) . " bytes\n";
			self::log($debug, __FILE__, __LINE__, 'debug');
			wp_send_json_success($result);
		}
	}
}