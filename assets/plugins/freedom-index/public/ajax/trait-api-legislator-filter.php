<?php
namespace FI\Public;
if (!defined('ABSPATH')) exit;

trait AjaxHandlersApiLegislatorFilterTrait {

	public function handle_api_legislator_filter() {

		//Time this function and output to console in seconds
		$start_time = microtime(true);
		try {
			check_ajax_referer('fi_ajax_nonce', 'nonce');

			$gov        = strtoupper(sanitize_text_field($_REQUEST['gov'] ?? ''));
			$session_id = isset($_REQUEST['session']) ? (int) $_REQUEST['session'] : 0;

			// Optional filters
			$party   = sanitize_text_field($_REQUEST['party'] ?? '');
			$state   = sanitize_text_field($_REQUEST['state'] ?? '');
			$chamber = strtoupper(sanitize_text_field($_REQUEST['chamber'] ?? ($_REQUEST['chamber'] ?? ''))); // allow legacy "chamber"
			$search  = sanitize_text_field($_REQUEST['search'] ?? '');
			$sort    = sanitize_text_field($_REQUEST['sort'] ?? '');

			// Map existing UI sort codes to API sort fields
			// API supports: name|score|party|chamber|district|state (adjust if your API differs)


			// If session missing/0, choose current session unless user is doing a bounded search
			// Keep behavior consistent with current UX.
			// If user wants "All Sessions" explicitly, they'll pass session=0 and must provide a bound.
			if ($session_id <= 0) {
				$is_bounded = ($search !== '' || $party !== '' || $chamber !== '' || ($gov === 'US' && $state !== ''));
				if (!$is_bounded) {
					$current = function_exists('fi_session_get_current_id') ? fi_session_get_current_id($gov) : 0;
					if ($current) $session_id = (int) $current;
				}
			}

			// Guardrail: prevent unbounded All Sessions query
			if ($session_id === 0) {
				$is_bounded = ($search !== '' || $party !== '' || $chamber !== '' || ($gov === 'US' && $state !== ''));
				if (!$is_bounded) {
					wp_send_json_error([
						'message' => 'Please select a session or enter a name / filter (party, chamber, state).'
					]);
					return;
				}
			}

			$cacheKey = 'ajax/api_legislators_'.$gov.'_'.$session_id.'_'.$party.'_'.$chamber.'_'.$search.'_'.$state.'_'.$sort;
			$cached = fi_cache($cacheKey);
			if ($cached) {
				wp_send_json_success($cached);
				return;
			}

			$query = [
				'key'        => FI_API_KEY,
				'action'     => 'legislators_cards',
				'gov'        => strtolower($gov),
			];

			// session_id optional; omit if 0 (All Sessions mode in API)
			if ($session_id > 0) $query['session_id'] = $session_id;
			if ($party !== '')   $query['party']   = strtolower($party);
			if ($state !== '')   $query['state']   = strtolower($state);
			if ($chamber !== '') $query['chamber'] = strtolower($chamber);
			if ($search !== '')  $query['name']    = $search;
			if ($sort !== '')  $query['sort']    = $sort;

			$url = add_query_arg($query, FI_API_URL);

			// Call API
			$res = wp_remote_get($url, [
				'timeout' => 10,
				'redirection' => 0,
				'headers' => [
					'Accept' => 'application/json',
				],
			]);

			if (is_wp_error($res)) {
				wp_send_json_error(['message' => 'API request failed.']);
				return;
			}

			$code = (int) wp_remote_retrieve_response_code($res);
			$body = (string) wp_remote_retrieve_body($res);
			if ($code !== 200 || $body === '') {
				wp_send_json_error(['message' => 'API returned an invalid response.']);
				return;
			}

			$json = json_decode($body, true);
			if (!is_array($json) || empty($json['success'])) {
				wp_send_json_error(['message' => $json['message'] ?? 'API error.']);
				return;
			}

			// Convert API results -> existing template output
			$cards = $json['results'] ?? [];
			$count = (int) ($json['count'] ?? count($cards));

			// Build normalized objects for the existing partial template
			$normalized = [];
			foreach ($cards as $row) {
				$row = is_array($row) ? $row : [];
				$normalized[] = (object) [
					'id' => (int)($row['id'] ?? $row['legislator_id'] ?? 0),
					'display_name' => $row['display_name'] ?? '',
					'first_name' => $row['first_name'] ?? '',
					'last_name' => $row['last_name'] ?? '',
					'chamber' => $row['chamber'] ?? '',
					'state' => $row['state'] ?? '',
					'state_name' => $row['state_name'] ?? '',
					'district' => $row['district'] ?? '',
					'district_name' => $row['district_name'] ?? '',
					'party' => $row['party'] ?? '',
					'score' => isset($row['score']) ? (int)$row['score'] : 0,
					'image_id' => $row['image_id'] ?? null,
					'image_url' => $row['image_url'] ?? '',
					'session_image_id' => $row['session_image_id'] ?? null,
					'photo_url' => $row['photo_url'] ?? '',
					'url' => $row['url'] ?? (function_exists('fi_get_legislator_url') ? fi_get_legislator_url((int)($row['id'] ?? 0)) : ''),
					'gov' => $gov,
				];
			}

			// Render HTML using existing partial (so UI stays identical)
			ob_start();
			if (empty($normalized)) {
				?>
				<div class="row">
					<div class="col-12">
						<div class="alert alert-warning text-center py-5">
							<h3>No Legislators Found</h3>
							<p class="mb-0">No legislators match your search criteria.</p>
<!-- <?php echo $url;?> -->
						</div>
					</div>
				</div>
				<?php
			} else {
				?>
				<div class="row g-4">
					<?php
					foreach ($normalized as $legislator) {
						fi_get_template('partials/legislator-card', [
							'legislator' => $legislator,
							'gov' => $gov,
						]);
					}
					?>
				</div>
				<?php
			}
			$html = ob_get_clean();

			$end_time = microtime(true);
			$execution_time = $end_time - $start_time;

			$success = [
				'html' => $html,
				'count' => $count,
				'has_results' => $count > 0,
				'filter_description' => $json['filter_description'],
				// Keep these keys so your existing JS won’t break:
				'report_links' => $json['report_links'] ?? [],
				'reports_nav_html' => $json['reports_nav_html'] ?? '',
				'url' => $json['url'] ?? '',
				'execution_time' => $execution_time,
			];

			fi_cache($cacheKey, $success);
			wp_send_json_success($success);

		} catch (\Throwable $e) {
			wp_send_json_error(['message' => 'An error occurred.']);
		}
	}
}