<?php
/**
 * Freedom Index Public AJAX: Unified Search + Find My Representatives
 *
 * Merged procedural replacement for:
 * - /public/autoload/ajax-search.php
 * - /public/ajax/trait-search.php
 *
 * Supports:
 * - fi_unified_search: one-box home page search router
 * - fi_search_autocomplete: legislator autocomplete
 * - fi_legislator_search: legislator name search cards
 * - fi_find_representatives: ZIP/address representative lookup
 *
 * Recommended location:
 * /public/autoload/ajax-search.php
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register public AJAX handlers for search and find-my-reps.
 *
 * @return void
 */
function fi_public_ajax_search_init(): void {
	add_action('wp_ajax_fi_unified_search', 'fi_public_ajax_handle_unified_search');
	add_action('wp_ajax_nopriv_fi_unified_search', 'fi_public_ajax_handle_unified_search');

	add_action('wp_ajax_fi_search_autocomplete', 'fi_public_ajax_handle_search_autocomplete');
	add_action('wp_ajax_nopriv_fi_search_autocomplete', 'fi_public_ajax_handle_search_autocomplete');

	add_action('wp_ajax_fi_legislator_search', 'fi_public_ajax_handle_legislator_search');
	add_action('wp_ajax_nopriv_fi_legislator_search', 'fi_public_ajax_handle_legislator_search');

	add_action('wp_ajax_fi_find_representatives', 'fi_public_ajax_handle_find_representatives');
	add_action('wp_ajax_nopriv_fi_find_representatives', 'fi_public_ajax_handle_find_representatives');
}
add_action('init', 'fi_public_ajax_search_init');

/**
 * Public AJAX scoped logger.
 *
 * @param string $message Log message.
 * @param string $file File path.
 * @param int $line Line number.
 * @param string $level Log level.
 * @param array $context Optional context.
 * @return void
 */
function fi_public_ajax_search_log(string $message, string $file = '', int $line = 0, string $level = 'debug', array $context = []): void {
	$payload = $message;
	if (!empty($context)) {
		$payload .= ' | ' . wp_json_encode($context);
	}
	fi_log_area('public_ajax', $payload, $file, $line, $level);
}

/**
 * Handle one-box unified search.
 *
 * Routing rule:
 * - 5-digit or ZIP+4 input => representative lookup by ZIP.
 * - Any input containing letters => legislator name search.
 * - Address-like input with digits + commas/spaces and no obvious person-name letters can also route to reps.
 *
 * Expected POST:
 * - nonce
 * - query or search
 * - optional address/city/state/zip, if your form has expanded address fields
 *
 * @return void
 */
function fi_public_ajax_handle_unified_search(): void {
	check_ajax_referer('fi_ajax_nonce', 'nonce');

var_dump($_POST); exit;


	$query = sanitize_text_field(wp_unslash($_POST['query'] ?? $_POST['search'] ?? $_POST['zip'] ?? ''));
	$query = trim($query);

	if ($query === '') {
		wp_send_json_success([
			'mode'    => 'empty',
			'html'    => '',
			'count'   => 0,
			'cleared' => true,
		]);
	}

	$route = fi_public_ajax_unified_search_route($query, $_POST);

	fi_log('[unified_search] query=' . $query . ' route=' . $route, __FILE__, __LINE__, 'debug');

	if ($route === 'representatives') {
		$result = fi_public_ajax_unified_find_representatives($query, $_POST);
		if (is_wp_error($result)) {
			fi_log('[unified_search] find_representatives WP_Error: ' . $result->get_error_message(), __FILE__, __LINE__, 'error');
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		fi_log('[unified_search] find_representatives count=' . ($result['count'] ?? 0), __FILE__, __LINE__, 'debug');
		wp_send_json_success($result);
	}

	$result = fi_public_ajax_unified_legislator_search($query);
	if (is_wp_error($result)) {
		fi_log('[unified_search] legislator_search WP_Error: ' . $result->get_error_message(), __FILE__, __LINE__, 'error');
		wp_send_json_error(['message' => $result->get_error_message()]);
	}

	fi_log('[unified_search] legislator_search count=' . ($result['count'] ?? 0), __FILE__, __LINE__, 'debug');
	wp_send_json_success($result);
}

/**
 * Decide whether unified search should run representative lookup or legislator search.
 *
 * @param string $query Raw search query.
 * @param array $source Request source.
 * @return string representatives|legislators
 */
function fi_public_ajax_unified_search_route(string $query, array $source = []): string {
	$query = trim($query);
	$zip = fi_public_ajax_extract_zip($query);

	// Explicit ZIP field always means find representatives.
	if (!empty($source['zip']) && fi_public_ajax_extract_zip((string) wp_unslash($source['zip']))) {
		return 'representatives';
	}

	// Pure ZIP or ZIP+4.
	if ($zip && preg_match('/^\s*\d{5}(?:-\d{4})?\s*$/', $query)) {
		return 'representatives';
	}

	// Has ZIP + any address component (street, city, state) = address search
	$has_address_part = !empty($source['address']) || !empty($source['city']) || !empty($source['state']);
	if ($zip && $has_address_part) {
		return 'representatives';
	}

	// Has ZIP + comma (typical address format: "City, ST 12345") = address search
	if ($zip && strpos($query, ',') !== false) {
		return 'representatives';
	}

	// Has ZIP + 2-letter state code = likely address
	if ($zip && preg_match('/\b[A-Za-z]{2}\b/', $query)) {
		return 'representatives';
	}

	// Has ZIP and more than just the ZIP (any text before/after) = try geocod
	if ($zip && !preg_match('/^\s*\d{5}(?:-\d{4})?\s*$/', $query)) {
		return 'representatives';
	}

	return 'legislators';
}

/**
 * Extract ZIP or ZIP+4 from a string.
 *
 * @param string $value Input value.
 * @return string ZIP or empty string.
 */
function fi_public_ajax_extract_zip(string $value): string {
	$value = trim($value);

	if (preg_match('/\b(\d{5})(?:-\d{4})?\b/', $value, $m)) {
		return $m[1];
	}

	return '';
}

/**
 * Run unified legislator search and return normalized response.
 *
 * @param string $query Legislator search query.
 * @return array|WP_Error
 */
function fi_public_ajax_unified_legislator_search(string $query) {
	if (strlen($query) < 3) {
		return [
			'mode'    => 'legislators',
			'html'    => fi_public_ajax_search_too_short_html(),
			'count'   => 0,
			'message' => 'Please enter at least 3 characters',
		];
	}

	$cache_key = 'search/_' . rawurlencode(strtolower($query));
	if (function_exists('fi_cache')) {
		$cached = fi_cache($cache_key);
		if (is_array($cached) && isset($cached['html'], $cached['count'])) {
			$cached['mode'] = 'legislators';
			return $cached;
		}
	}

	try {
		$result = fi_public_ajax_search_legislators($query);
		$result['mode'] = 'legislators';

		if (function_exists('fi_cache')) {
			fi_cache($cache_key, $result);
		}

		return $result;
	} catch (Throwable $e) {
		fi_public_ajax_search_log('Unified legislator search error: ' . $e->getMessage(), __FILE__, __LINE__, 'error', [
			'query' => $query,
			'file'  => $e->getFile(),
			'line'  => $e->getLine(),
			'trace' => $e->getTraceAsString(),
		]);

		return new WP_Error('fi_legislator_search_failed', 'An error occurred while searching. Please try again.');
	}
}

/**
 * Run unified find-representatives lookup and return normalized response.
 *
 * @param string $query ZIP/address query.
 * @param array $source Request source.
 * @return array|WP_Error
 */
function fi_public_ajax_unified_find_representatives(string $query, array $source = []) {
	fi_log('[find_reps] start query=' . $query, __FILE__, __LINE__, 'debug');

	$address = fi_public_ajax_unified_build_address($query, $source);
	fi_log('[find_reps] resolved address=' . $address, __FILE__, __LINE__, 'debug');
	if ($address === '') {
		return new WP_Error('fi_address_required', 'ZIP code or address is required.');
	}

	$cache_key = 'findmy/' . rawurlencode(strtolower($address));
	if (function_exists('fi_cache')) {
		$cached = fi_cache($cache_key);
		if (is_array($cached) && isset($cached['officials'])) {
			$officials = is_array($cached['officials']) ? $cached['officials'] : [];
			$district_check = fi_public_ajax_detect_multiple_districts($officials);
			$has_multiple = $district_check['has_multiple'];
			return [
				'mode'      => 'representatives',
				'html'      => fi_public_ajax_find_representatives_render_html($officials, $address, $has_multiple, $district_check),
				'address'   => $address,
				'count'     => count($officials),
				'officials' => $officials,
				'has_multiple_districts' => $has_multiple,
				'multiple_districts_type' => $district_check['type'],
				'multiple_districts_message' => $has_multiple 
					? "Your zip code spans {$district_check['count']} " . ($district_check['type'] === 'federal' ? 'congressional' : 'state legislative') . " districts." 
					: '',
			];
		}
	}

	$officials = fi_geocod_get_officials(rawurlencode($address));
	if (!is_array($officials)) {
		fi_log('[find_reps] geocod returned non-array: ' . gettype($officials), __FILE__, __LINE__, 'error');
		$officials = [];
	}

	fi_log('[find_reps] geocod officials count=' . count($officials), __FILE__, __LINE__, 'debug');
	$data = [
		'address'   => $address,
		'officials' => $officials,
	];

	if (function_exists('fi_cache')) {
//		fi_cache($cache_key, $data);
	}

	// Detect multiple congressional districts in results
	$district_check = fi_public_ajax_detect_multiple_districts($officials);
	$has_multiple = $district_check['has_multiple'];
	
	return [
		'mode'                   => 'representatives',
		'html'                   => fi_public_ajax_find_representatives_render_html($officials, $address, $has_multiple, $district_check),
		'address'                => $address,
		'count'                  => count($officials),
		'officials'              => $officials,
		'has_multiple_districts' => $has_multiple,
		'multiple_districts_type' => $district_check['type'],
		'multiple_districts_message' => $has_multiple 
			? "Your zip code spans {$district_check['count']} " . ($district_check['type'] === 'federal' ? 'congressional' : 'state legislative') . " districts." 
			: '',
	];
}

/**
 * Detect if officials array contains multiple US reps or multiple state reps
 * This happens when a zip code spans multiple districts
 *
 * @param array $officials Array of official arrays
 * @return array Detection result with 'has_multiple' flag and 'type' (US or state)
 */
function fi_public_ajax_detect_multiple_districts(array $officials): array {
	$us_districts = [];
	$state_districts = [];
	
	foreach ($officials as $official) {
		$chamber = strtolower($official['chamber'] ?? '');
		$type = strtolower($official['type'] ?? '');
		$gov = $official['legislator']['gov'] ?? '';
		
		// District could be in division or legislator.district
		$district = $official['division'] ?? $official['legislator']['district'] ?? '';
		
		// US Representative (federal, not state)
		$is_us_rep = ($gov === 'US' || $type === 'representative') && strpos($chamber, 'state') === false;
		// State Representative
		$is_state_rep = strpos($chamber, 'state') !== false || ($gov !== 'US' && $type === 'representative');
		
		if ($is_us_rep && $district) {
			$us_districts[$district] = true;
		} elseif ($is_state_rep && $district) {
			$state_districts[$district] = true;
		}
	}
	
	if (count($us_districts) > 1) {
		return ['has_multiple' => true, 'type' => 'federal', 'count' => count($us_districts)];
	}
	if (count($state_districts) > 1) {
		return ['has_multiple' => true, 'type' => 'state', 'count' => count($state_districts)];
	}
	
	return ['has_multiple' => false, 'type' => '', 'count' => 0];
}

/**
 * Build representative lookup address from unified search source.
 *
 * @param string $query Main query.
 * @param array $source Request source.
 * @return string Address or ZIP.
 */
function fi_public_ajax_unified_build_address(string $query, array $source = []): string {
	$zip = sanitize_text_field(wp_unslash($source['zip'] ?? ''));
	$street = sanitize_text_field(wp_unslash($source['address'] ?? ''));
	$city = sanitize_text_field(wp_unslash($source['city'] ?? ''));
	$state = sanitize_text_field(wp_unslash($source['state'] ?? ''));

	// ORIGINAL: Build from source fields if provided
	if ($zip !== '' && $street !== '' && $city !== '' && $state !== '') {
		return trim($street . ' ' . $city . ' ' . $state . ' ' . $zip);
	}

	if ($zip !== '') {
		return $zip;
	}

	// Fallback to query string
	return trim($query);
}

/**
 * Handle search autocomplete suggestions.
 *
 * Expected POST:
 * - nonce
 * - term
 * - limit optional
 *
 * @return void
 */
function fi_public_ajax_handle_search_autocomplete(): void {
	check_ajax_referer('fi_ajax_nonce', 'nonce');

	$term = sanitize_text_field(wp_unslash($_POST['term'] ?? ''));
	$limit = absint($_POST['limit'] ?? 10);
	$limit = max(1, min(25, $limit));

	if (strlen($term) < 3) {
		wp_send_json_success([]);
	}

	$cache_key = 'fi_ac_' . md5(strtolower($term) . '|' . $limit);
	$cached = get_transient($cache_key);
	if ($cached !== false && is_array($cached)) {
		wp_send_json_success($cached);
	}

	$suggestions = fi_public_ajax_search_get_autocomplete_suggestions($term, $limit);

	set_transient($cache_key, $suggestions, MINUTE_IN_SECONDS);
	wp_send_json_success($suggestions);
}

/**
 * Get autocomplete suggestions.
 *
 * @param string $term Search term.
 * @param int $limit Max suggestions.
 * @return array Suggestions.
 */
function fi_public_ajax_search_get_autocomplete_suggestions(string $term, int $limit = 10): array {
	global $wpdb;

	$limit = max(1, min(25, absint($limit)));
	$search_term = '%' . $wpdb->esc_like($term) . '%';

	$sql = "
		SELECT
			l.id,
			l.first_name,
			l.last_name,
			l.display_name,
			ls.party,
			ls.chamber,
			s.gov
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
		ORDER BY l.last_name ASC, l.first_name ASC
		LIMIT %d
	";

	$results = $wpdb->get_results($wpdb->prepare($sql, $search_term, $search_term, $search_term, $limit));
	if (empty($results)) {
		return [];
	}

	$suggestions = [];

	foreach ($results as $result) {
		$chamber_label = !empty($result->chamber) && function_exists('fi_chamber_label')
			? fi_chamber_label($result->gov, $result->chamber)
			: '';

		$label = (string) ($result->display_name ?? '');
		if (!empty($result->party)) {
			$label .= ' (' . strtoupper((string) $result->party) . ')';
		}

		if (!empty($result->gov)) {
			$label .= ' ' . strtoupper((string) $result->gov);
			if (!empty($chamber_label)) {
				$label .= ' ' . $chamber_label;
			}
		}

		$url = function_exists('fi_get_legislator_url')
			? fi_get_legislator_url((int) $result->id)
			: home_url('/legislator/' . (int) $result->id . '/');

		$suggestions[] = [
			'type'  => 'legislator',
			'label' => $label,
			'value' => (string) ($result->display_name ?? ''),
			'url'   => $url,
		];
	}

	return $suggestions;
}

/**
 * Handle legislator name search with rendered cards.
 *
 * Expected POST:
 * - nonce
 * - search
 * - clear optional
 *
 * @return void
 */
function fi_public_ajax_handle_legislator_search(): void {
	if (ob_get_level()) {
		ob_clean();
	}

	check_ajax_referer('fi_ajax_nonce', 'nonce');

	$search = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
	$clear = isset($_POST['clear']) && (string) $_POST['clear'] === '1';

	if ($clear || ($search === '' && isset($_POST['clear']))) {
		wp_send_json_success([
			'html'    => '',
			'count'   => 0,
			'cleared' => true,
		]);
	}

	if ($search === '') {
		wp_send_json_success([
			'html'    => '',
			'count'   => 0,
			'cleared' => true,
		]);
	}

	$result = fi_public_ajax_unified_legislator_search($search);
	if (is_wp_error($result)) {
		wp_send_json_error(['message' => $result->get_error_message()]);
	}

	wp_send_json_success($result);
}

/**
 * Cross-gov legislator name search.
 *
 * Queries fi_legislators + fi_legislator_sessions directly, restricted to
 * current sessions only. Returns arrays formatted by legislators_list_format_legislator().
 *
 * @param string $search Search term (min 3 chars).
 * @param int    $limit  Max results.
 * @return array Array of formatted legislator arrays.
 */
function fi_search_legislators_by_name(string $search, int $limit = 50): array {
	global $wpdb;

	$search = trim($search);
	if (strlen($search) < 3) {
		return [];
	}

	$like = '%' . $wpdb->esc_like($search) . '%';
	$limit = min(max(1, $limit), 200);

	fi_log('[name_search] term=' . $search, __FILE__, __LINE__, 'debug');

	if (!function_exists('legislators_list_format_legislator')) {
		fi_log('[name_search] legislators_list_format_legislator() not found — legislators_list.php may not be loaded', __FILE__, __LINE__, 'error');
		return [];
	}

	// Use latest session per legislator (same strategy as autocomplete) rather than
	// relying on is_current=1 which may not be set correctly.
	$sql = $wpdb->prepare("
		SELECT
			ls.legislator_id AS id,
			ls.gov,
			l.display_name,
			l.first_name,
			l.last_name,
			l.image_id,
			l.image_url,
			l.legacy_image_url,
			ls.chamber,
			ls.party,
			ls.state,
			ls.district,
			ls.score,
			ls.session_id,
			s.name AS session_name,
			s.parent_id AS session_parent_id,
			t.name AS district_name
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
		LEFT JOIN {$wpdb->prefix}fi_taxonomy t
			ON ls.district = t.slug
			AND t.gov = ls.gov
			AND t.taxonomy = 'district'
		WHERE (l.display_name LIKE %s OR l.first_name LIKE %s OR l.last_name LIKE %s)
		ORDER BY l.last_name ASC, l.first_name ASC
		LIMIT %d
	", $like, $like, $like, $limit);

	$rows = $wpdb->get_results($sql);

	fi_log('[name_search] rows=' . count((array) $rows) . ' last_db_error=' . $wpdb->last_error, __FILE__, __LINE__, 'debug');

	if (empty($rows)) {
		return [];
	}

	return array_map('legislators_list_format_legislator', $rows);
}

/**
 * Search legislators and render cards.
 *
 * @param string $search Search term.
 * @return array{html:string,count:int}
 */
function fi_public_ajax_search_legislators(string $search): array {
	$results = fi_search_legislators_by_name($search, 50);

	ob_start();
	$count = 0;

	echo '<div class="row g-3">';

	if (empty($results)) {
		echo fi_public_ajax_search_no_results_html($search);
	} else {
		foreach ($results as $result) {
			if (!is_array($result) || empty($result['gov'])) {
				continue;
			}

			$count++;
			echo '<div class="col-12 col-md-6">';
			fi_get_public_template('legislators-card', ['legislator' => $result, 'gov' => $result['gov']]);
			echo '</div>';
		}

		if ($count === 0) {
			echo fi_public_ajax_search_no_results_html($search);
		}
	}

	echo '</div>';

	$html = (string) ob_get_clean();

	return [
		'html'  => $html,
		'count' => $count,
	];
}


/**
 * Render search-too-short message.
 *
 * @return string HTML.
 */
function fi_public_ajax_search_too_short_html(): string {
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
	return (string) ob_get_clean();
}

/**
 * Render no-results message.
 *
 * @param string $search Search term.
 * @return string HTML.
 */
function fi_public_ajax_search_no_results_html(string $search): string {
	ob_start();
	?>
	<div class="col-12 col-md-8 col-lg-6 mx-auto">
		<div class="alert alert-info text-center py-3">
			<h4 class="mb-3">No results found for search: <strong><?php echo esc_html($search); ?></strong></h4>
			<p class="mb-0">Please try again with a different or partial name of any state or federal legislator.</p>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Handle find my representatives lookup.
 *
 * Expected POST:
 * - nonce
 * - zip
 * - address/city/state optional
 *
 * @return void
 */
function fi_public_ajax_handle_find_representatives(): void {
	check_ajax_referer('fi_ajax_nonce', 'nonce');

	fi_public_ajax_search_log('handle_find_representatives:start', __FILE__, __LINE__, 'debug', [
		'post_keys' => array_keys($_POST),
	]);

	$query = sanitize_text_field(wp_unslash($_POST['zip'] ?? $_POST['query'] ?? $_POST['search'] ?? ''));
	$result = fi_public_ajax_unified_find_representatives($query, $_POST);

	if (is_wp_error($result)) {
		wp_send_json_error(['message' => $result->get_error_message()]);
	}

	fi_public_ajax_search_log('handle_find_representatives:complete', __FILE__, __LINE__, 'debug', [
		'official_count' => $result['count'] ?? 0,
		'address'        => $result['address'] ?? '',
	]);

	$json = wp_json_encode($result);
	fi_public_ajax_search_log('handle_find_representatives:json_length', __FILE__, __LINE__, 'debug', ['length' => strlen($json)]);

	wp_send_json_success($result);
}

/**
 * Render find-my-representatives result HTML.
 *
 * @param array $officials Officials.
 * @param string $address Address searched.
 * @param bool $has_multiple Whether zip spans multiple districts.
 * @param array $district_check District detection details.
 * @return string HTML.
 */
function fi_public_ajax_find_representatives_render_html(array $officials, string $address = '', bool $has_multiple = false, array $district_check = []): string {
	ob_start();

	// Show multi-district warning with address form option
	if ($has_multiple) {
		// Extract zip from address for the refine form
		$zip = '';
		if (preg_match('/\b(\d{5}(-\d{4})?)\b/', $address, $matches)) {
			$zip = $matches[1];
		}
		?>
<div class="alert alert-warning p-2 mb-3">
	<div class="d-flex align-items-start gap-2">
		<span class="alert-icon ps-0">⚠️</span>
		<div>
			<span class="text-muted">Your zip code spans multiple districts. Enter your full address for precise results.</span>
		</div>
	</div>
	<form class="row g-2 mt-1 fi-address-refine-form" onsubmit="return false;">
		<div class="col-12 col-sm-6 col-md-4"><input type="text" class="form-control form-control-sm" name="street" placeholder="Street Address" required style="font-size: 0.875rem;"></div>
		<div class="col-6 col-sm-3 col-md-2"><input type="text" class="form-control form-control-sm" name="city" placeholder="City" required style="font-size: 0.875rem;"></div>
		<div class="col-3 col-sm-2 col-md-2"><select class="form-control form-control-sm" name="state" placeholder="State" maxlength="2" required style="font-size: 0.875rem;">
			<?php
			foreach (FI_GOVERNMENTS as $abbr => $state){
				if($abbr != 'US'){
					echo '<option value="' . esc_attr($abbr) . '">' . esc_html($state) . '</option>';
				}
			}
			?>
		</select></div>
		<div class="col-3 col-sm-3 col-md-2"><input type="text" class="form-control form-control-sm" name="zip" placeholder="Zip" value="<?= esc_attr($zip);?>" style="font-size: 0.875rem;"></div>
		<div class="col-12 col-sm-4 col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100" style="font-size: 0.875rem; white-space: nowrap;">Find</button></div>
	</form>
</div>
		<?php
	}

	if (!empty($officials)) {
		echo '<div class="container-xl"><div class="row g-3">';
		foreach ($officials as $official) {
			if (!is_array($official)) {
				continue;
			}

			// Build legislator data array from API response
			$bio = $official['bio'] ?? [];
			$chamber = $official['chamber'] ?? '';

			// Derive gov from chamber label
			$gov = $official['legislator']['gov'] ?? '';
			if ($gov === '') {
				$gov = (strpos(strtolower($chamber), 'state') !== false) ? 'state' : 'US';
			}

			$score = $official['score'] ?? null;

			$leg = [
				'id'              => (int) ($official['id'] ?? 0),
				'display_name'    => $official['name'] ?? '',
				'first_name'      => $bio['first_name'] ?? '',
				'last_name'       => $bio['last_name'] ?? '',
				'image_url'       => $official['photo_url'] ?? ($bio['photo_url'] ?? null),
				'image_id'        => null,
				'session_image_id'=> null,
				'lazy_load'       => true,
				'score'           => ($score !== null && $score !== '') ? (int) $score : null,
				'chamber'         => $chamber,
				'party'           => $official['party'] ?? '',
				'state'           => $official['legislator']['state'] ?? '',
				'district'        => $official['legislator']['district'] ?? '',
				'district_name'   => $official['division'] ?? '',
				'gov'             => $gov,
				'url'             => $official['legislator']['url'] ?? ($official['contact']['url'] ?? ''),
			];

			fi_log('[find_reps_render] ' . $leg['display_name'] . ' gov=' . $leg['gov'] . ' score=' . $leg['score'], __FILE__, __LINE__, 'debug');

			echo '<div class="col-12 col-md-6">';
			fi_get_public_template('legislators-card', ['legislator' => $leg, 'gov' => $leg['gov']]);
			echo '</div>';
		}
		echo '</div></div>';
	} else {
		echo '<div class="alert alert-warning">No officials found for the provided address';
		if ($address !== '') {
			echo ': ' . esc_html($address);
		}
		echo '.</div>';
	}

	return (string) ob_get_clean();
}

/**
 * AJAX handler for loading state/federal selector content in bottom sheet
 */
add_action('wp_ajax_fi_load_selector', 'fi_public_ajax_handle_load_selector');
add_action('wp_ajax_nopriv_fi_load_selector', 'fi_public_ajax_handle_load_selector');

function fi_public_ajax_handle_load_selector(): void {
	check_ajax_referer('fi_ajax_nonce', 'nonce');

	$type = sanitize_text_field($_POST['type'] ?? '');
	if (!in_array($type, ['federal', 'state'], true)) {
		wp_send_json_error(['message' => 'Invalid selector type']);
	}

	// Start output buffer to capture template part
	ob_start();

	// Use combined sheet-select-state template for both list and map
	get_template_part('template-parts/sheet-select-state', '', ['type' => $type]);

	$html = ob_get_clean();

	wp_send_json_success(['html' => $html, 'type' => $type]);
}

