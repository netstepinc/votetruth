<?php
/**
 * Freedom Index by Sam Mittelstaedt <smittelstaedt@jbs.org>
 *
 * AJAX handlers: legislator filtering
 * To run an AJAX request via URL in WordPress (as in this example):
 * 1. Use the URL structure: 
 * IMPORTANT: To use this AJAX handler directly via a GET request (from logged-in or logged-out state),
 * call: https://freedomindex.us/wp-admin/admin-ajax.php?action=fi_legislator_filter&gov=US&session=119&party=R&chamber=S&search=&state=
 *
 * If you are being redirected to /admin.php or seeing an error, check the following:
 * - Make sure this AJAX action is registered with 'wp_ajax_' and/or 'wp_ajax_nopriv_' hook
 *   (it is, see ajax-handlers.php).
 * - Ensure you are not being blocked by a missing or invalid nonce check: 
 *   If you are not passing a 'nonce', comment out or remove the check_ajax_referer line
 *   (in this file it's commented out: //check_ajax_referer('fi_ajax_nonce', 'nonce');).
 * - If you are still redirected, a plugin or custom code may be forcing authentication for /wp-admin/*.
 *   This is a server/WordPress policy, not specific to this handler.
 *
 * If you want to test this in the browser, confirm that you are NOT being hit by a forced admin redirect or
 * session timeouts. AJAX requests to admin-ajax.php should NOT redirect you to the dashboard or require admin.php,
 * regardless of login, as long as the action is correctly hooked.
*/

namespace FI\Public {
	if ( ! defined( 'ABSPATH' ) ) { exit; }

	trait AjaxHandlersLegislatorFilterTrait {

		/**
		* Handle legislator filtering
		*/
		public function handle_legislator_filter() {
			try {
				check_ajax_referer('fi_ajax_nonce', 'nonce');			
				$gov = sanitize_text_field($_REQUEST['gov'] ?? '');
				//SESSIONSLUG: Change $session_slug to $session_id, validate as int, update cache key to use ID
				$session_id_raw = sanitize_text_field($_REQUEST['session'] ?? '');
				$party_slug = sanitize_text_field($_REQUEST['party'] ?? '');
				$chamber = strtoupper(sanitize_text_field($_REQUEST['chamber'] ?? ''));
				$search = sanitize_text_field($_REQUEST['search'] ?? '');
				$state = sanitize_text_field($_REQUEST['state'] ?? '');
				$sort = sanitize_text_field($_REQUEST['sort'] ?? '');

				// Parse sort parameter (format: "na", "nd", "sa", "sd", "pa", "pd", "oa", "od")
				$orderby = 'last_name'; // Default
				$order = 'ASC'; // Default
				if (!empty($sort)) {
					$sort = strtolower(sanitize_key($sort));
					
					// Map 2-character codes to orderby/order
					$sort_map = [
						'na' => ['orderby' => 'last_name', 'order' => 'ASC'],
						'nd' => ['orderby' => 'last_name', 'order' => 'DESC'],
						'sa' => ['orderby' => 'score', 'order' => 'ASC'],
						'sd' => ['orderby' => 'score', 'order' => 'DESC'],
						'pa' => ['orderby' => 'party', 'order' => 'ASC'],
						'pd' => ['orderby' => 'party', 'order' => 'DESC'],
						'oa' => ['orderby' => 'chamber', 'order' => 'ASC'],
						'od' => ['orderby' => 'chamber', 'order' => 'DESC'],
					];
					
					if (isset($sort_map[$sort])) {
						$orderby = $sort_map[$sort]['orderby'];
						$order = $sort_map[$sort]['order'];
					}
				}

				//SESSIONSLUG: Update cache key to use session_id instead of session_slug
				$session_id = is_numeric($session_id_raw) ? (int) $session_id_raw : 0;
				$cacheKey = 'ajax/legislators_'.$gov.'_'.$session_id.'_'.$party_slug.'_'.$chamber.'_'.$search.'_'.$state.'_'.$sort;
				$success = fi_cache($cacheKey);
				if($success){
					wp_send_json_success($success);
					return;
				}

				//SESSIONSLUG: Remove slug lookup logic, directly validate $session_id as int and verify session exists with fi_session_get()
				// Validate session_id and verify session exists
				if ($session_id > 0) {
					$session_obj = fi_session_get($session_id);
					if (!$session_obj || $session_obj->gov !== strtoupper($gov)) {
						$session_id = 0;
					}
				}
				
				// Allow "All Sessions": do not force a session when none selected.
				// if (!$session_id && !empty($gov)) {
				// 	$current_session_id = fi_session_get_current_id($gov);
				// 	if ($current_session_id) {
				// 		$session_id = $current_session_id;
				// 	}
				// }
				
				//SESSIONSLUG: Update log message to use session_id instead of session_slug
				// Debug logging
				self::log('FI AJAX Session Lookup - session_id: ' . ($session_id ?: 'null'), __FILE__, __LINE__, 'debug');
				
				// Party is now stored as abbreviation (e.g., 'R', 'D', 'L')
				// Convert slug to abbreviation if needed
				$party_abbr = null;
				if (!empty($party_slug)) {
					// Party must be a valid abbreviation
					if (fi_party_validate($party_slug)) {
						$party_abbr = strtoupper($party_slug);
					}
				}
				
				$args = array(
					'gov' => $gov,
					'party' => $party_abbr,
					'chamber' => $chamber,
					'search' => $search,
					'orderby' => $orderby,
					'order' => $order,
					'limit' => !empty($_POST['limit']) ? intval($_POST['limit']) : -1, // Default to no limit to match initial page load
					'offset' => intval($_POST['offset'] ?? 0)
				);

				// Debug: allow timing instrumentation per-request (keeps default behavior unchanged).
				// Summary: opt-in only; when enabled, Legislators::get() disables caches and logs step timings.
				if (!empty($_POST['debug_timing'])) {
					$args['debug_timing'] = true;
				}
				
				// Only add session if we have a valid session_id
				if (!empty($session_id) && $session_id > 0) {
					$args['session'] = $session_id;
				}
				
				// Ensure we have at least gov or session for the query
				if (empty($args['gov']) && empty($args['session'])) {
					wp_send_json_error(array('message' => 'Invalid request: government or session required.'));
					return;
				}
					
				// For Congress, add state filter
				if (strtoupper($gov) === 'US' && !empty($state)) {
					$args['state'] = strtoupper($state);
				}
							
				// Debug logging
				self::log('FI AJAX Filter Args: ' . json_encode($args), __FILE__, __LINE__, 'debug');
				self::log('FI AJAX POST Data: ' . json_encode($_POST), __FILE__, __LINE__, 'debug');
				
				$legislators = fi_legislators($args);
				
				// Get session ID for reports (use filtered session or default)
				$session_id_for_reports = $args['session'] ?? null;
				if (!$session_id_for_reports && !empty($gov)) {
					$session_id_for_reports = fi_session_get_current_id($gov);
				}
				
				// Get reports for this session and build report links array
				$report_links = [];
				if ($session_id_for_reports) {
				$reports = fi_reports_get(['session_id' => $session_id_for_reports, 'per_page' => -1]);
				if ($reports) {
					foreach ($reports as $report) {
						$report_url = fi_url_report($report->id, strtolower($report->gov ?? $gov));
						$report_title = $report->title ?? 'Report #' . $report->id;
							$report_links[] = [
								'url' => $report_url,
								'title' => $report_title,
							];
						}
					}
				}
				
				// Normalize legislator data for card view
				// This makes both views context-agnostic and allows easy view switching
				$i=0;
				$normalized_legislators = [];
				foreach ($legislators as $legislator) {
					$i++;
					// Get photo URL (used by both views)
					$photo_url = '';
					if (!empty($legislator->image_id)) {
						$photo_url = wp_get_attachment_image_url($legislator->image_id, 'thumbnail');
					}
					
					// Normalize to a consistent structure that both views can use
					$normalized_legislators[] = (object) [
						// Core IDs
						'id' => $legislator->id,
						
						// Display info
						'display_name' => $legislator->display_name ?? ($legislator->first_name . ' ' . $legislator->last_name),
						'first_name' => $legislator->first_name ?? '',
						'last_name' => $legislator->last_name ?? '',
						
						// chamber & location
						'chamber' => $legislator->chamber ?? '',
						'state' => $legislator->state ?? '',
						'state_name' => $legislator->state_name ?? '',
						'district' => $legislator->district ?? '',
						'district_info' => $legislator->district_info ?? null,
						
						// Images (both views need this)
						'photo_url' => $photo_url,
						'image_id' => $legislator->image_id ?? null,
						'session_image_id' => $legislator->session_image_id ?? null,
						
						// Scores
						'freedom_score' => $legislator->score ?? 0,
						'score' => $legislator->score ?? 0,
						
						// Contact info
						'phone' => $legislator->phone ?? '',
						'email' => $legislator->email ?? '',
						
						// Party
						'party' => $legislator->party ?? '',
						
						// URL
						'url' => $legislator->url ?? (function_exists('fi_get_legislator_url') ? fi_get_legislator_url($legislator->id ?? 0) : home_url('/legislator/' . ($legislator->id ?? 0) . '/')),
						
						// Government context
						'gov' => $legislator->gov ?? $args['gov'],
	//TODO: Temporarily add meta to the legislator object - remove after import is complete
	//					'meta' => $legislator->meta ?? null,
	//					'lazy_load' => ($i > 18) ? true : false,
					];
				}
				

				// Generate HTML for card view
				ob_start();
				if (empty($normalized_legislators)) {
					?>
					<div class="row">
						<div class="col-12">
							<div class="alert alert-warning text-center py-5">
								<h3>No Legislators Found</h3>
								<p class="mb-0">No legislators match your search criteria.</p>
							</div>
						</div>
					</div>
					<?php
				} else {
					// Card grid view - uses normalized data structure
					?>
					<div class="row g-4">
					<?php
					foreach ($normalized_legislators as $legislator) {
						fi_get_template('partials/legislator-card', [
							'legislator' => $legislator,
							'gov' => $args['gov'],
						]);
					}
					?>
					</div>
					<?php
				}
				$html = ob_get_clean();
				
				// Build pretty URL for sharing
				$url_filters = array();
				
				//SESSIONSLUG: Remove slug conversion, use $args['session'] directly as session_id in URL filters
				// Use session_id directly in URL filters
				if (!empty($args['session']) && is_numeric($args['session'])) {
					$url_filters['session_id'] = (int) $args['session'];
				}
				
				// Party is now stored as abbreviation - use it directly for URL
				if (!empty($args['party'])) {
					// Party should already be an abbreviation (R, D, L, etc.)
					$url_filters['party_slug'] = strtolower($args['party']);
				}
				
				if (!empty($args['chamber'])) {
					$url_filters['chamber'] = strtoupper($args['chamber']);
				}
				
				if (!empty($args['search'])) {
					$url_filters['search'] = $args['search'];
				}
				
				if (!empty($_POST['state'])) {
					$url_filters['state'] = strtolower(sanitize_text_field($_POST['state']));
				}
				
				// Build pretty URL
				$url = fi_build_filter_url($args['gov'], $url_filters);
				
				// Build filter description (same as rewrite handler)
				$party_slug = null;
				// Party is stored as abbreviation
				$party_abbr = null;
				if (!empty($args['party'])) {
					// Party must be a valid abbreviation
					if (fi_party_validate($args['party'])) {
						$party_abbr = strtoupper($args['party']);
					}
				}
				
				$filter_description = fi_filter_build_description(array(
					'gov' => $args['gov'],
					'session' => $session_id_for_reports,
					'party' => $party_abbr,
					'chamber' => $args['chamber'] ?? null,
					'state' => $args['state'] ?? null
				));
				
				$count = count($legislators);
				
				// Generate reports nav HTML using unified function
				$reports_nav_html = '';
				if (!empty($report_links)) {
					$reports_nav_html = fi_reports_nav_html($report_links);
				}

				$success = [
					'html' => $html,
					'count' => $count, // Number of results returned
					'url' => $url,
					'has_results' => $count > 0,
					'report_links' => $report_links,
					'reports_nav_html' => $reports_nav_html, // Pre-generated HTML
					'filter_description' => $filter_description
				];
				//Cache the entire response
				fi_cache($cacheKey, $success);
				wp_send_json_success($success);
			} catch (\Exception $e) {
				// Log error for debugging
				self::log('Legislator filter error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine() . ' | Trace: ' . $e->getTraceAsString(), __FILE__, __LINE__, 'error');
				wp_send_json_error(array('message' => 'An error occurred while filtering legislators. Please try again.'));
			} catch (\Error $e) {
				self::log('Legislator filter fatal error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine() . ' | Trace: ' . $e->getTraceAsString(), __FILE__, __LINE__, 'error');
				wp_send_json_error(array('message' => 'An error occurred while filtering legislators. Please try again.'));
			}
		}
	}
}