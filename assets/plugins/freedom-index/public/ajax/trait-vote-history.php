<?php
/**
 * Freedom Index by Sam Mittelstaedt <smittelstaedt@jbs.org>
 *
 * AJAX handlers: legislator vote history
 */

namespace FI\Public {
	if ( ! defined( 'ABSPATH' ) ) { exit; }

	trait AjaxHandlersVoteHistoryTrait {

		/**
		 * Handle legislator vote history loading (all votes, session, report, tag)
		 */
		public function handle_legislator_vote_history() {
			check_ajax_referer('fi_ajax_nonce', 'nonce');
			
			$legislator_id = intval($_POST['legislator_id'] ?? 0);
			$chamber = strtoupper(sanitize_text_field($_POST['chamber'] ?? ''));
			$party = strtoupper(sanitize_text_field($_POST['party'] ?? ''));
			$view = sanitize_text_field($_POST['view'] ?? 'all');
			$session_id = !empty($_POST['session_id']) ? intval($_POST['session_id']) : null;
			$report_id = !empty($_POST['report_id']) ? intval($_POST['report_id']) : null;
			$tag_id = !empty($_POST['tag_id']) ? intval($_POST['tag_id']) : null;
			
			if (!$legislator_id || !$chamber) {
				wp_send_json_error('Invalid parameters');
			}
			
			// Resolve gov for display without requiring a session (fallback to legislator record).
			$gov = null;
			if ($session_id) {
				$session = fi_session_get($session_id);
				$gov = $session->gov ?? null;
			}
			if (!$gov) {
				$legislator = fi_legislator_get($legislator_id);
				$gov = $legislator->gov ?? null;
			}

			// For Sessions and Reports we need the legislator's current context (subtitle only).
			if ($gov && $chamber) {
				$chambers = fi_chamber_options($gov);
				$chamber_label = $chambers[$chamber]['chamber'] ?? ''; //fi_chamber_label($gov, $chamber);
				$party = $party ? ' ('.$party.')' : '';
				$gov_name = fi_gov_name($gov);
				if ($gov == 'US') {
					$gov_name = 'United States';
				}
				$subtitle = $gov_name . ' ' . $chamber_label . $party;
			} else {
				$subtitle = '';
			}
			
			$result = [
				'title' => '',
				'subtitle' => '',
				'score' => 0,
				'report_header' => null,
				'votes' => []
			];
			
			// Get votes based on view type
			switch ($view) {
				case 'all':
					// Get all votes for legislator using Votes class
					$votes = fi_votes_get_by_legislator($legislator_id, [
						'limit' => 1000, // Get all votes
						'offset' => 0
					]);
					$result['title'] = 'Complete Vote History';
					break;
					
				case 'tag':
					if (!$tag_id) {
						wp_send_json_error('Tag ID required');
					}
					// Get votes by tag
					$tag_votes = fi_votes_get_by_tag($tag_id, [
						'chamber' => $chamber,
						'status' => 'publish',
						'per_page' => 1000
					]);
					// Filter to only votes where legislator has a rollcall
					$votes = [];
					foreach ($tag_votes as $vote) {
						$rollcall = fi_rollcall_get($vote->id, $legislator_id);
						if ($rollcall) {
							$vote->cast = $rollcall->cast ?? 'X';
							$votes[] = $vote;
						}
					}
					$tag = fi_taxonomy_get($tag_id);
					$result['title'] = 'Votes: ' . ($tag->name ?? 'Issue');
					break;
					
				case 'session':
					if (!$session_id) {
						wp_send_json_error('Session ID required');
					}
					$session = fi_session_get($session_id);
					// Get votes for legislator in this session
					$votes = fi_votes_get_by_legislator($legislator_id, [
						'session_id' => $session_id,
						'limit' => 1000,
						'offset' => 0
					]);
					$result['title'] = ($session->name ?? 'Session') . ' Votes';
					$result['subtitle'] = $subtitle;
					break;
					
				case 'report':
					if (!$session_id || !$report_id) {
						wp_send_json_error('Session ID and Report ID required');
					}
					
					$report = fi_report_get($report_id);
					
					if (!$report) {
						//self::log("Report not found: id={$report_id}");
						wp_send_json_error('Report not found');
					}
					//self::log("VIEW=report: ID={$report->id}, Title={$report->title} session_id={$session_id}, report_id={$report_id}, chamber={$chamber}");				
					$session = fi_session_get($session_id);
					
					// Get report payload
					$payload = json_decode($report->payload_json, true) ?: [];
					$report_vote_ids = array_map('intval', (array) ($payload['votes_' . strtolower($chamber)] ?? []));
					//fi_log("Report vote IDs for chamber {$chamber} report_id={$report_id}: " . json_encode($report_vote_ids), __FILE__, __LINE__);
					
					// Get order array for this chamber (H or S)
					$vote_order_key = 'votes_' . strtolower($chamber) . '_order';
					$vote_order = array_map('intval', (array) ($payload[$vote_order_key] ?? []));
					//self::log("Vote order: " . json_encode($vote_order));
					
					// DEBUG: Log report vote IDs and session vote IDs for comparison
					if (defined('WP_DEBUG') && WP_DEBUG) {
						//self::log("Report Vote IDs: Report ID={$report->id}, Report Slug={$report->slug}, Chamber={$chamber}, Vote IDs=" . json_encode($report_vote_ids));
						//self::log("Vote Order: " . json_encode($vote_order));
					}
					
					if (empty($report_vote_ids)) {
						$votes = [];
						//self::log("Report has NO vote IDs for chamber {$chamber}");
					} else {
						//self::log("Fetching votes for legislator {$legislator_id} in session {$session_id}");
						
						// Get votes for legislator in this session
						$all_votes = fi_votes_get_by_legislator($legislator_id, [
							'session_id' => $session_id,
							'limit' => 1000,
							'offset' => 0
						]);
						
						//self::log("All votes returned: " . count($all_votes));
						
						// Get all session vote IDs for comparison
						$session_vote_ids = array_map(function($v) { return (int) ($v->id ?? 0); }, $all_votes);
						
						// DEBUG: Compare report vote IDs with session vote IDs
						if (defined('WP_DEBUG') && WP_DEBUG) {
							$missing_ids = array_diff($report_vote_ids, $session_vote_ids);
							$found_ids = array_intersect($report_vote_ids, $session_vote_ids);
							//self::log("Vote ID Comparison: Session ID={$session_id}, Legislator ID={$legislator_id}, Chamber={$chamber}");
							//self::log("Session Vote IDs (" . count($session_vote_ids) . "): " . json_encode(array_slice($session_vote_ids, 0, 20)) . (count($session_vote_ids) > 20 ? '...' : ''));
							//self::log("Report Vote IDs (" . count($report_vote_ids) . "): " . json_encode($report_vote_ids));
							//self::log("Found IDs (" . count($found_ids) . "): " . json_encode($found_ids));
							if (!empty($missing_ids)) {
								self::log("Missing IDs (not in session) (" . count($missing_ids) . "): " . json_encode($missing_ids));
							}
						}
						
						// Filter to report votes only
						$filtered_votes = array_filter($all_votes, function($vote) use ($report_vote_ids) {
							return in_array($vote->id ?? 0, $report_vote_ids);
						});
						
						// Sort votes according to order array if it exists
						if (!empty($vote_order)) {
							// Create a map of vote_id => vote object for quick lookup
							$votes_map = [];
							foreach ($filtered_votes as $vote) {
								$votes_map[(int) ($vote->id ?? 0)] = $vote;
							}
							
							// Build ordered array using order array
							$ordered_votes = [];
							foreach ($vote_order as $vote_id) {
								if (isset($votes_map[$vote_id])) {
									$ordered_votes[] = $votes_map[$vote_id];
									unset($votes_map[$vote_id]); // Remove from map so we know which ones are left
								}
							}
							
							// Append any votes that weren't in the order array (shouldn't happen, but safety)
							foreach ($votes_map as $vote) {
								$ordered_votes[] = $vote;
							}
							
							$votes = $ordered_votes;
						} else {
							// No order array, use filtered votes as-is
							$votes = array_values($filtered_votes);
						}

//Add cache here with precise key?

						if (defined('WP_DEBUG') && WP_DEBUG) {
							self::log("Filtered votes count: " . count($votes) . " out of " . count($all_votes) . " session votes");
						}
					}
					
					$result['title'] = $report->title ?? 'Report';
					$result['subtitle'] = $subtitle;
					// Convert newlines to <p>/<br> then sanitize (same as PDF report intro)
					$result['report_header'] = [
						'title' => $report->title ?? '',
						'content' => wp_kses_post(wpautop($payload['content'] ?? ''))
					];
					$result['report_format'] = $report->format ?? 'scorecard';
					break;
					
				default:
					wp_send_json_error('Invalid view type');
			}
			
			// Expose votes array so client can show search and cache for All Votes view
			$result['votes'] = $votes;
			
			//RMFORMAT
			// Get report format for description fallback (default to scorecard)
			$report_format = $result['report_format'] ?? 'scorecard';
			
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
			
			// Render vote cards HTML server-side
			ob_start();
			
			// Render report header if present
			if (!empty($result['report_header'])) {
				echo '<div class="mb-3 p-2 p-lg-3">';
				echo '<div class="fs-7">' . wp_kses_post($result['report_header']['content'] ?? '') . '</div>';
				echo '</div>';
			}
			
			// Render vote cards
			if (empty($votes)) {
				echo '<div class="alert alert-info">No votes found for this selection.</div>';
			} else {
				echo '<div class="row g-3" id="fi-vote-cards-container">';
				
				foreach ($votes as $vote) {
					// Process vote data for vote-card partial
					$vote_meta = fi_vote_decode_meta($vote);

					// Get description
					$descriptions = fi_vote_get_description($vote_meta);
					$text = $descriptions['short'] ?? '';
					$text_more = $descriptions['long'] ?? '';

					//Get Image: Default proportion is 1800x1200 = 3:2 | Display size is 300x200
					$img_tag = '';
					$attachment_id = $vote_meta['image_id'] ?? null;
					if($attachment_id) {
						$img_tag = '<div class="float-end col-4 ms-3 mb-2 vote-image">'.jis_get_attachment_image($attachment_id,[300,200],true,['retina' => true,'alt' => $vote->title ?? '','class' => 'img-fluid','id' => 'vote-image-'.$attachment_id]).'</div>';
						$text_more = $img_tag . $text_more;
					}

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
					$tags = $vote_meta['tags'] ?? [];
					
					// Build search text
					$search_text = strtolower(($vote->title ?? '') . ' ' . ($vote->bill_number ?? '') . ' ' . strip_tags($descriptions['long'] ?? $descriptions['medium'] ?? $descriptions['short']));
					
					// Prepare vote card data
					$vote_data = [
						'id' => $vote->id ?? 0,
						'gov' => $vote->gov ?? '',
						'slug' => $vote->slug ?? '',
						'title' => $vote->title ?? '',
						'text' => $text,
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
						'report_format' => $report_format, //RMFORMAT
						'chamber_title' => true,
						'chamber_label' => $chamber_label,
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