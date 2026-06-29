<?php
/**
 * Reports Table I/O Operations
 *
 * Straight function version of the former FI\Core\Reports class wrapper.
 *
 * Handles database operations for the fi_reports table and keeps the existing
 * public fi_* function API intact.
 *
 * Reconciled:
 * - Merged reports-payload.php into this file.
 * - Kept the richer payload implementation with report options/order fields.
 * - Removed duplicate narrow payload normalize/validate implementations.
 * - Removed active slug save/check behavior; deprecated slug helpers remain harmless.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Normalize report payload from JSON string, array, object, or null into standard structure.
 *
 * This is the keeper version because it preserves all report-builder options, not just votes_h/votes_s.
 *
 * @param mixed $payload Raw payload data.
 * @return array Normalized payload array.
 */
function fi_report_payload_normalize($payload): array {
	if (is_string($payload)) {
		$decoded = json_decode($payload, true);
		if (!is_array($decoded)) {
			$decoded = [];
		}
	} elseif (is_array($payload)) {
		$decoded = $payload;
	} elseif (is_object($payload)) {
		$decoded = (array) $payload;
	} else {
		$decoded = [];
	}

	$format = isset($decoded['format']) ? sanitize_key((string) $decoded['format']) : 'scorecard';
	if (!in_array($format, ['scorecard', 'freedomindex'], true)) {
		$format = 'scorecard';
	}

	$cph = isset($decoded['cph']) ? sanitize_key((string) $decoded['cph']) : 'hide';
	if (!in_array($cph, ['show', 'hide'], true)) {
		$cph = 'hide';
	}

	$contact = isset($decoded['contact']) ? sanitize_key((string) $decoded['contact']) : 'back';
	if (!in_array($contact, ['front', 'back'], true)) {
		$contact = 'back';
	}

	$constitution_qr = isset($decoded['constitution_qr']) ? sanitize_key((string) $decoded['constitution_qr']) : 'none';
	if (!in_array($constitution_qr, ['none', 'front', 'back'], true)) {
		$constitution_qr = 'none';
	}

	$normalized = [
		'content'         => $decoded['content'] ?? '',
		'format'          => $format,
		'cph'             => $cph,
		'vote_start'      => isset($decoded['vote_start']) ? sanitize_text_field((string) $decoded['vote_start']) : '1',
		'contact'         => $contact,
		'constitution_qr' => $constitution_qr,
		'fi_vote_paging'  => isset($decoded['fi_vote_paging']) ? sanitize_text_field((string) $decoded['fi_vote_paging']) : '2,3,3,2',
		'votes_h'         => array_values(array_unique(array_filter(array_map('absint', (array) ($decoded['votes_h'] ?? []))))),
		'votes_s'         => array_values(array_unique(array_filter(array_map('absint', (array) ($decoded['votes_s'] ?? []))))),
		'votes_h_order'   => array_values(array_unique(array_filter(array_map('absint', (array) ($decoded['votes_h_order'] ?? []))))),
		'votes_s_order'   => array_values(array_unique(array_filter(array_map('absint', (array) ($decoded['votes_s_order'] ?? []))))),
		'report_pdf_url'  => isset($decoded['report_pdf_url']) ? esc_url_raw((string) $decoded['report_pdf_url']) : '',
	];

	if (isset($decoded['legacy_votes_h'])) {
		$normalized['legacy_votes_h'] = array_values(array_unique(array_filter(array_map('absint', (array) $decoded['legacy_votes_h']))));
	}

	if (isset($decoded['legacy_votes_s'])) {
		$normalized['legacy_votes_s'] = array_values(array_unique(array_filter(array_map('absint', (array) $decoded['legacy_votes_s']))));
	}

	foreach ($decoded as $key => $value) {
		if (!array_key_exists($key, $normalized) && !in_array($key, ['legacy_votes_h', 'legacy_votes_s'], true)) {
			$normalized[$key] = $value;
		}
	}

	return $normalized;
}

/**
 * Build report payload from form submission data.
 *
 * @param array $submitted_data Form submission data.
 * @param array|null $existing_payload Existing payload to merge with.
 * @return array Normalized payload ready for storage.
 */
function fi_report_payload_build(array $submitted_data, ?array $existing_payload = null): array {
	$payload = $existing_payload !== null
		? fi_report_payload_normalize($existing_payload)
		: fi_report_payload_normalize([]);

	if (isset($submitted_data['intro_text'])) {
		$payload['content'] = function_exists('fi_prepare_richedit_save')
			? fi_prepare_richedit_save($submitted_data['intro_text'])
			: wp_kses_post(wp_unslash($submitted_data['intro_text']));
	}

	if (isset($submitted_data['report_format'])) {
		$format = sanitize_text_field(wp_unslash($submitted_data['report_format']));
		$payload['format'] = in_array($format, ['scorecard', 'freedomindex'], true) ? $format : 'scorecard';
	}

	if (isset($submitted_data['report_cph'])) {
		$cph = sanitize_text_field(wp_unslash($submitted_data['report_cph']));
		$payload['cph'] = in_array($cph, ['show', 'hide'], true) ? $cph : 'hide';
	}

	if (isset($submitted_data['vote_start'])) {
		$payload['vote_start'] = sanitize_text_field(wp_unslash($submitted_data['vote_start']));
	}

	if (isset($submitted_data['contact_location'])) {
		$contact = sanitize_text_field(wp_unslash($submitted_data['contact_location']));
		$payload['contact'] = in_array($contact, ['front', 'back'], true) ? $contact : 'back';
	}

	if (isset($submitted_data['constitution_qr'])) {
		$qr = sanitize_text_field(wp_unslash($submitted_data['constitution_qr']));
		$payload['constitution_qr'] = in_array($qr, ['none', 'front', 'back'], true) ? $qr : 'none';
	}

	if (isset($submitted_data['fi_vote_paging'])) {
		$payload['fi_vote_paging'] = sanitize_text_field(wp_unslash($submitted_data['fi_vote_paging']));
	}

	if (isset($submitted_data['report_pdf_url'])) {
		$payload['report_pdf_url'] = esc_url_raw(wp_unslash($submitted_data['report_pdf_url']));
	}

	if (isset($submitted_data['selected_votes_h']) && is_array($submitted_data['selected_votes_h'])) {
		$payload['votes_h'] = array_values(array_unique(array_filter(array_map('absint', $submitted_data['selected_votes_h']))));
	}

	if (isset($submitted_data['selected_votes_s']) && is_array($submitted_data['selected_votes_s'])) {
		$payload['votes_s'] = array_values(array_unique(array_filter(array_map('absint', $submitted_data['selected_votes_s']))));
	}

	if (isset($submitted_data['votes_h_order']) && is_array($submitted_data['votes_h_order'])) {
		$payload['votes_h_order'] = array_values(array_unique(array_filter(array_map('absint', $submitted_data['votes_h_order']))));
	}

	if (isset($submitted_data['votes_s_order']) && is_array($submitted_data['votes_s_order'])) {
		$payload['votes_s_order'] = array_values(array_unique(array_filter(array_map('absint', $submitted_data['votes_s_order']))));
	}

	return fi_report_payload_normalize($payload);
}

/**
 * Get a specific value from normalized report payload.
 *
 * @param mixed $payload Payload data.
 * @param string $key Key to retrieve.
 * @param mixed $default Default value.
 * @return mixed
 */
function fi_report_payload_get($payload, string $key, $default = null) {
	$normalized = fi_report_payload_normalize($payload);
	return array_key_exists($key, $normalized) ? $normalized[$key] : $default;
}

/**
 * Validate report payload structure.
 *
 * @param mixed $payload Payload to validate.
 * @return array{valid:bool,errors:array}
 */
function fi_report_payload_validate($payload): array {
	$payload = fi_report_payload_normalize($payload);
	$errors = [];

	if (!in_array($payload['format'] ?? '', ['scorecard', 'freedomindex'], true)) {
		$errors[] = 'Invalid report format';
	}

	if (!in_array($payload['cph'] ?? '', ['show', 'hide'], true)) {
		$errors[] = 'Invalid CPH value';
	}

	if (!in_array($payload['contact'] ?? '', ['front', 'back'], true)) {
		$errors[] = 'Invalid contact location';
	}

	if (!in_array($payload['constitution_qr'] ?? '', ['none', 'front', 'back'], true)) {
		$errors[] = 'Invalid constitution QR location';
	}

	foreach (['votes_h', 'votes_s', 'votes_h_order', 'votes_s_order'] as $key) {
		if (!isset($payload[$key]) || !is_array($payload[$key])) {
			$errors[] = $key . ' must be an array';
		}
	}

	return [
		'valid'  => empty($errors),
		'errors' => $errors,
	];
}

/**
 * Encode report payload after normalization/validation.
 *
 * @param mixed $payload Payload array or JSON string.
 * @return string JSON payload.
 */
function fi_report_payload_encode($payload): string {
	$normalized = fi_report_payload_normalize($payload);
	$validation = fi_report_payload_validate($normalized);

	if (!$validation['valid'] && defined('WP_DEBUG') && WP_DEBUG) {
		error_log('FI Reports: Payload validation errors: ' . implode(', ', $validation['errors']));
	}

	$encoded = wp_json_encode($normalized);
	return is_string($encoded) ? $encoded : '{}';
}

/**
 * Query reports with optional filtering (DB-only, no cache).
 *
 * Default: published-only.
 * Admin UIs must explicitly pass ['status' => null] to see all statuses.
 */
function fi_reports_query(array $args = []): array|int {
	global $wpdb;

	$status_key_provided = array_key_exists('status', $args);

	$defaults = [
		'id'         => null,
		'session_id' => null,
		'gov'        => null,
		'search'     => null,
		'status'     => null,
		'format'     => null,
		'orderby'    => 'date_publish',
		'order'      => 'DESC',
		'per_page'   => -1,
		'page'       => 1,
		'count'      => false,
	];

	$args = wp_parse_args($args, $defaults);

	if (!$status_key_provided) {
		$args['status'] = 'publish';
	}

	$where_conditions = [];
	$where_values     = [];

	if ($args['id']) {
		$where_conditions[] = 'r.id = %d';
		$where_values[]     = absint($args['id']);
	}

	if ($args['session_id']) {
		$where_conditions[] = 'r.session_id = %d';
		$where_values[]     = absint($args['session_id']);
	}

	if ($args['gov']) {
		$where_conditions[] = 'r.gov = %s';
		$where_values[]     = strtoupper(sanitize_key((string) $args['gov']));
	}

	if ($args['format']) {
		$where_conditions[] = 'r.format = %s';
		$where_values[]     = sanitize_key((string) $args['format']);
	}

	if ($args['status'] !== null) {
		$where_conditions[] = 'r.status = %s';
		$where_values[]     = sanitize_key((string) $args['status']);

		if ($args['status'] === 'publish') {
			$where_conditions[] = '(r.date_publish IS NULL OR r.date_publish <= %s)';
			$where_values[]     = current_time('mysql');
		}
	}

	if ($args['search']) {
		$where_conditions[] = 'r.title LIKE %s';
		$where_values[]     = '%' . $wpdb->esc_like((string) $args['search']) . '%';
	}

	$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

	$order_dir = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
	$allowed_orderby = [
		'id'           => 'r.id',
		'title'        => 'r.title',
		'date_publish' => 'r.date_publish',
		'date_created' => 'r.date_created',
		'format'       => 'r.format',
		'session_id'   => 'r.session_id',
	];
	$orderby_col = $allowed_orderby[(string) $args['orderby']] ?? 'r.date_publish';
	$orderby = $orderby_col . ' ' . $order_dir . ', r.id ' . $order_dir;

	$limit_clause = '';
	if ((int) $args['per_page'] > 0) {
		$page         = max(1, (int) $args['page']);
		$per_page     = (int) $args['per_page'];
		$offset       = ($page - 1) * $per_page;
		$limit_clause = $wpdb->prepare('LIMIT %d OFFSET %d', $per_page, $offset);
	}

	if ($args['count']) {
		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}fi_reports r {$where_clause}";

		if (!empty($where_values)) {
			$sql = $wpdb->prepare($sql, ...$where_values);
		}

		$count = (int) $wpdb->get_var($sql);
		$cache_get[$cache_key] = $count;
		return $count;
	}

	$sql = "
		SELECT r.*, s.name as session_name
		FROM {$wpdb->prefix}fi_reports r
		LEFT JOIN {$wpdb->prefix}fi_sessions s ON r.session_id = s.id
		{$where_clause}
		ORDER BY {$orderby}
		{$limit_clause}
	";

	if (!empty($where_values)) {
		$sql = $wpdb->prepare($sql, ...$where_values);
	}

	return $wpdb->get_results($sql, ARRAY_A);
}



/**
 * Get reports with optional filtering (cached for front-end).
 *
 * @param array $args Optional query arguments.
 * @return array|int Array of report objects or count if count=true.
 */
function fi_reports_get(array $args = []): array|int {
	$cacheKey = fi_cache_key('reports/get', $args);

	if ($cacheKey) {
		$results = fi_cache($cacheKey,'',DAY_IN_SECONDS);
		if ($results){
			return $results;
		}
	}

	$results = fi_reports_query($args);
	fi_cache($cacheKey, $results);
	return $results;
}

/** Get a single report by ID. */
function fi_report_get(int $report_id): ?array {
	$report_id = absint($report_id);
	if ($report_id <= 0) {
		return null;
	}

	$results = fi_reports_get([
		'id'       => $report_id,
		'per_page' => 1,
		'status'   => null,
	]);

	return is_array($results) ? ($results[0] ?? null) : null;
}

/** Save/Update report with duplicate checking. */
function fi_report_save(array $data, ?int $report_id = null): int|false {
	global $wpdb;

	if (empty($data['title']) || empty($data['session_id']) || empty($data['gov'])) {
		return false;
	}

	$report_id = $report_id ? absint($report_id) : null;
	$data['gov'] = strtoupper(sanitize_key((string) $data['gov']));

	$duplicate_check = fi_report_check_duplicates($data, $report_id);
	if ($duplicate_check['is_duplicate']) {
		return $duplicate_check['existing_id'];
	}

	$payload_json = '{}';
	if (array_key_exists('payload_json', $data) && $data['payload_json'] !== null && $data['payload_json'] !== '') {
		$payload_json = fi_report_payload_encode($data['payload_json']);
	}

	$payload = fi_report_payload_normalize($payload_json);
	$format = isset($data['format']) ? sanitize_key((string) $data['format']) : ($payload['format'] ?? 'scorecard');
	if (!in_array($format, ['scorecard', 'freedomindex'], true)) {
		$format = 'scorecard';
	}

	// Keep payload and column format synchronized.
	$payload['format'] = $format;
	$payload_json = fi_report_payload_encode($payload);

	$status = isset($data['status']) ? sanitize_key((string) $data['status']) : 'draft';
	if (!in_array($status, ['publish', 'draft', 'pending', 'trash'], true)) {
		$status = 'draft';
	}

	$db_data = [
		'legacy_id'     => !empty($data['legacy_id']) ? (string) $data['legacy_id'] : null,
		'session_id'    => absint($data['session_id']),
		'gov'           => $data['gov'],
		'title'         => sanitize_text_field((string) $data['title']),
		'title_menu'    => isset($data['title_menu']) ? sanitize_text_field((string) $data['title_menu']) : null,
		'payload_json'  => $payload_json,
		'format'        => $format,
		'status'        => $status,
		'date_publish'  => !empty($data['date_publish']) ? sanitize_text_field((string) $data['date_publish']) : null,
		'meta'          => !empty($data['meta']) ? (is_string($data['meta']) ? $data['meta'] : wp_json_encode($data['meta'])) : null,
		'owner_user_id' => !empty($data['owner_user_id']) ? absint($data['owner_user_id']) : null,
	];

	$db_data = array_filter($db_data, static function ($value) {
		return $value !== null;
	});

	$formats = [];
	foreach ($db_data as $key => $value) {
		$formats[] = in_array($key, ['session_id', 'owner_user_id'], true) ? '%d' : '%s';
	}

	if ($report_id) {
		$result = $wpdb->update(
			$wpdb->prefix . 'fi_reports',
			$db_data,
			['id' => $report_id],
			$formats,
			['%d']
		);

		if ($result !== false) {
			do_action('fi_report_saved', $report_id, $db_data);
		}
		return $result !== false ? $report_id : false;
	}

	$result = $wpdb->insert(
		$wpdb->prefix . 'fi_reports',
		$db_data,
		$formats
	);

	$new_id = $result !== false ? (int) $wpdb->insert_id : false;
	if ($new_id) {
		do_action('fi_report_saved', $new_id, $db_data);
	}

	return $new_id;
}

/** Update report. */
function fi_report_update(int $report_id, array $data): bool {
	return fi_report_save($data, $report_id) !== false;
}

/** Delete report. */
function fi_report_delete(int $report_id): bool {
	global $wpdb;

	$report_id = absint($report_id);
	if ($report_id <= 0 || !fi_report_get($report_id)) {
		return false;
	}

	$result = $wpdb->delete($wpdb->prefix . 'fi_reports', ['id' => $report_id], ['%d']);

	return $result !== false;
}

/** Get reports by session. */
function fi_reports_get_by_session(int $session_id, array $filters = []): array {
	$args = array_merge($filters, ['session_id' => absint($session_id)]);
	$results = fi_reports_get($args);

	return is_array($results) ? $results : [];
}

/**
 * Fetch published reports for multiple sessions in one WHERE IN query.
 * Returns array keyed by session_id; each value is an array of report rows.
 * payload_json decoded via fi_report_payload_normalize(); raw JSON dropped.
 *
 * @param array  $session_ids  List of session IDs.
 * @param string $status       Report status filter (default 'publish').
 * @return array<int, array[]> [ session_id => [ report, ... ] ]
 */
function fi_reports_get_by_session_ids(array $session_ids, string $status = 'publish'): array {
	global $wpdb;
	if (empty($session_ids)) return [];

	$session_ids  = array_values(array_unique(array_filter(array_map('absint', $session_ids))));
	$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));

	$params = $session_ids;
	$extra  = '';
	if ($status !== '') {
		$extra   = ' AND r.status = %s AND (r.date_publish IS NULL OR r.date_publish <= %s)';
		$params[] = $status;
		$params[] = current_time('mysql');
	}

	$sql = $wpdb->prepare(
		"SELECT r.id, r.session_id, r.gov, r.title, r.title_menu, r.slug, r.format,
		        r.status, r.date_publish, r.payload_json, r.score, r.score_data
		 FROM {$wpdb->prefix}fi_reports r
		 WHERE r.session_id IN ($placeholders){$extra}
		 ORDER BY r.date_publish DESC",
		...$params
	);

	$rows = $wpdb->get_results($sql, ARRAY_A);
	if (!is_array($rows)) return [];

	$out = [];
	foreach ($rows as $r) {
		$sid             = (int) $r['session_id'];
		$r['payload']    = fi_report_payload_normalize($r['payload_json'] ?? '');
		$r['score_data'] = !empty($r['score_data']) ? (json_decode($r['score_data'], true) ?: []) : [];
		unset($r['payload_json']);
		$out[$sid][] = $r;
	}
	return $out;
}

/** Get the most recent published report for a government/jurisdiction. */
function fi_report_latest(string $gov): ?array {
	$gov = strtoupper(sanitize_key($gov));
	$is_manager = defined('FI_CAP_MANAGE') ? current_user_can(FI_CAP_MANAGE) : current_user_can('manage_options');

	$results = fi_reports_get([
		'gov'      => $gov,
		'status'   => $is_manager ? null : 'publish',
		'orderby'  => 'date_publish',
		'order'    => 'DESC',
		'per_page' => 1,
	]);

	$report = is_array($results) ? ($results[0] ?? null) : null;
	if (!$report) {
		return null;
	}

	$format = !empty($report['format']) ? (string) $report['format'] : 'scorecard';
	$format_arg = $format === 'freedomindex' ? 'fia' : 'scb';

	if (function_exists('fi_url_report')) {
		$url = fi_url_report((int) $report['id'], strtolower($gov));
	} elseif (function_exists('fi_report_url')) {
		$url = fi_report_url(strtolower($gov), (int) $report['id']);
	} else {
		$url = home_url('/' . strtolower($gov) . '/report/' . (int) $report['id'] . '/');
	}

	$report['format']     = $format;
	$report['format_arg'] = $format_arg;
	$report['url']        = $url;

	return $report;
}

/** Get report statistics. */
function fi_reports_stats(?string $gov = null, ?int $session_id = null, bool $by_status = false): array {
	global $wpdb;

	$where_conditions = [];
	$values           = [];

	if ($gov) {
		$where_conditions[] = 'gov = %s';
		$values[]           = strtoupper(sanitize_key($gov));
	}

	if ($session_id) {
		$where_conditions[] = 'session_id = %d';
		$values[]           = absint($session_id);
	}

	$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

	if ($by_status) {
		$sql = "
			SELECT
				COUNT(*) as total,
				COUNT(CASE WHEN status = 'publish' THEN 1 END) as publish,
				COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
				COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
				COUNT(CASE WHEN status = 'trash' THEN 1 END) as trash
			FROM {$wpdb->prefix}fi_reports
			{$where_clause}
		";
	} else {
		$sql = "SELECT COUNT(*) as total FROM {$wpdb->prefix}fi_reports {$where_clause}";
	}

	if (!empty($values)) {
		$sql = $wpdb->prepare($sql, ...$values);
	}

	return $wpdb->get_row($sql, ARRAY_A) ?: [];
}

/** Decode selected vote IDs from report payload. */
function fi_report_decode_selected_votes(array $report): array {
	$payload = fi_report_payload_normalize($report['payload_json'] ?? null);
	$combined = array_merge($payload['votes_h'] ?? [], $payload['votes_s'] ?? []);

	if (!empty($combined)) {
		return array_values(array_unique(array_map('absint', $combined)));
	}

	$raw = $report['selected_votes'] ?? '[]';
	if (is_string($raw)) {
		$decoded = json_decode($raw, true);
	} elseif (is_array($raw)) {
		$decoded = $raw;
	} else {
		$decoded = [];
	}

	return is_array($decoded) ? array_values(array_unique(array_filter(array_map('absint', $decoded)))) : [];
}

/** Check for duplicate reports. */
function fi_report_check_duplicates(array $data, ?int $exclude_id = null): array {
	global $wpdb;

	$conditions = [];
	$values     = [];

	if (!empty($data['legacy_id']) && !empty($data['gov'])) {
		$conditions[] = '(legacy_id = %s AND gov = %s)';
		$values[]     = (string) $data['legacy_id'];
		$values[]     = strtoupper(sanitize_key((string) $data['gov']));
	}

	if (empty($conditions)) {
		return [
			'is_duplicate' => false,
			'existing_id'   => null,
		];
	}

	$where_clause = implode(' OR ', $conditions);
	$sql = "SELECT id FROM {$wpdb->prefix}fi_reports WHERE ({$where_clause})";

	if ($exclude_id) {
		$sql .= ' AND id != %d';
		$values[] = absint($exclude_id);
	}

	$sql .= ' LIMIT 1';

	$existing_id = $wpdb->get_var($wpdb->prepare($sql, ...$values));

	return [
		'is_duplicate' => !empty($existing_id),
		'existing_id'   => $existing_id ? (int) $existing_id : null,
	];
}

/** Validate report data. */
function fi_report_validate_data(array $data): array {
	$errors = [];

	if (empty($data['title'])) {
		$errors[] = 'Title is required';
	}

	if (empty($data['session_id'])) {
		$errors[] = 'Session ID is required';
	}

	if (empty($data['gov'])) {
		$errors[] = 'Government is required';
	}

	if (isset($data['payload_json'])) {
		$payload_validation = fi_report_payload_validate($data['payload_json']);
		if (!$payload_validation['valid']) {
			$errors = array_merge($errors, $payload_validation['errors']);
		}
	}

	return [
		'valid'  => empty($errors),
		'errors' => $errors,
	];
}

/** Get latest scorecard by gov and session. */
function fi_report_latest_scorecard(string $gov, int $session_id): ?array {
	$results = fi_reports_get([
		'gov'        => strtoupper(sanitize_key($gov)),
		'session_id' => absint($session_id),
		'format'     => 'scorecard',
		'status'     => 'publish',
		'orderby'    => 'date_publish',
		'order'      => 'DESC',
		'per_page'   => 1,
	]);

	return is_array($results) ? ($results[0] ?? null) : null;
}

/** Get the latest Freedom Index report. */
function fi_report_latest_freedom_index(): ?array {
	$results = fi_reports_get([
		'format'   => 'freedomindex',
		'status'   => 'publish',
		'orderby'  => 'id',
		'order'    => 'DESC',
		'per_page' => 1,
	]);

	return is_array($results) ? ($results[0] ?? null) : null;
}

/**
 * Get reports by vote ID.
 * Uses JSON_CONTAINS on payload_json votes_h/votes_s.
 */
function fi_report_get_by_vote_id(int $vote_id, string $chamber): array {
	global $wpdb;

	$vote_id = absint($vote_id);
	if ($vote_id <= 0) {
		return [];
	}

	$chamber = strtoupper(trim($chamber));
	$vote_json = (string) $vote_id;

	if ($chamber === 'H') {
		$sql = $wpdb->prepare(
			"SELECT id, title, payload_json FROM {$wpdb->prefix}fi_reports
			 WHERE JSON_CONTAINS(payload_json, %s, '$.votes_h') = 1
			 ORDER BY id DESC",
			$vote_json
		);
	} elseif ($chamber === 'S') {
		$sql = $wpdb->prepare(
			"SELECT id, title, payload_json FROM {$wpdb->prefix}fi_reports
			 WHERE JSON_CONTAINS(payload_json, %s, '$.votes_s') = 1
			 ORDER BY id DESC",
			$vote_json
		);
	} else {
		$sql = $wpdb->prepare(
			"SELECT id, title, payload_json FROM {$wpdb->prefix}fi_reports
			 WHERE (
				JSON_CONTAINS(payload_json, %s, '$.votes_h') = 1
				OR JSON_CONTAINS(payload_json, %s, '$.votes_s') = 1
			 )
			 ORDER BY id DESC",
			$vote_json,
			$vote_json
		);
	}

	$reports = $wpdb->get_results($sql, ARRAY_A);
	if (empty($reports)) {
		return [];
	}

	foreach ($reports as &$report) {
		$report['payload_json'] = fi_report_payload_normalize($report['payload_json']);
	}
	unset($report);

	return $reports;
}

/** Order: Scorecards first, then Freedom Index; unknown format last. */
function fi_reports_sort_by_format($gov, array $reports): array {
	if (strtoupper((string) $gov) !== 'US') {
		return $reports;
	}

	$sc    = [];
	$fi    = [];
	$other = [];

	foreach ($reports as $report) {
		$format = isset($report['format']) ? strtolower(trim((string) $report['format'])) : '';

		if ($format === 'scorecard') {
			$sc[] = $report;
		} elseif ($format === 'freedomindex') {
			$fi[] = $report;
		} else {
			$other[] = $report;
		}
	}

	return array_merge($sc, $fi, $other);
}

/**
 * Report Title Reformatting.
 * Converts titles like 'MA Scorecard 2025' to '2025 MA Legislative Scorecard'.
 */
function fi_report_title_reformat(string $gov, string $title): string {
	$gov = strtoupper($gov);

	if ($gov === 'US') {
		if (strpos($title, 'Congressional') === false) {
			$title = str_replace(' Scorecard', ' Congressional Scorecard', $title);
		}
	} else {
		if (strpos($title, 'Legislative') === false) {
			$title = str_replace(' Scorecard', ' Legislative Scorecard', $title);
		}
	}

	if (is_numeric(substr($title, -4))) {
		$year  = substr($title, -4);
		$title = trim(str_replace($year, '', $title));
		$title = $year . ' ' . $title;
	}

	return $title;
}

/** Get report meta value by key. */
function fi_report_get_meta($record, string $key, $default = null) {
	$meta = fi_report_get_all_meta($record);
	return array_key_exists($key, $meta) ? $meta[$key] : $default;
}

/** Get all report meta. */
function fi_report_get_all_meta($record): array {
	if (function_exists('fi_meta_get_all')) {
		if (is_numeric($record)) {
			$report = fi_report_get((int) $record);
			return $report ? fi_meta_get_all($report) : [];
		}
		return fi_meta_get_all($record);
	}

	if (is_object($record)) {
		$raw_meta = $record->meta ?? null;
	} elseif (is_array($record)) {
		$raw_meta = $record['meta'] ?? null;
	} elseif (is_numeric($record)) {
		$report = fi_report_get((int) $record);
		$raw_meta = $report['meta'] ?? null;
	} else {
		$raw_meta = null;
	}

	if (empty($raw_meta)) {
		return [];
	}

	if (is_array($raw_meta)) {
		return $raw_meta;
	}

	if (!is_string($raw_meta)) {
		return [];
	}

	$decoded = json_decode($raw_meta, true);
	return is_array($decoded) ? $decoded : [];
}

/** Update report meta key(s) without affecting other keys. */
function fi_report_update_meta(int $record_id, array $meta_updates): bool {
	if (function_exists('fi_meta_update')) {
		return fi_meta_update($record_id, 'fi_reports', $meta_updates);
	}

	$meta = fi_report_get_all_meta($record_id);
	foreach ($meta_updates as $key => $value) {
		if ($value === null) {
			unset($meta[$key]);
		} else {
			$meta[$key] = $value;
		}
	}

	return fi_report_set_all_meta($record_id, $meta);
}

/** Delete report meta key(s). */
function fi_report_delete_meta(int $record_id, $keys): bool {
	if (function_exists('fi_meta_delete')) {
		return fi_meta_delete($record_id, 'fi_reports', $keys);
	}

	$keys = is_array($keys) ? $keys : [$keys];
	$meta = fi_report_get_all_meta($record_id);

	foreach ($keys as $key) {
		unset($meta[$key]);
	}

	return fi_report_set_all_meta($record_id, $meta);
}

/** Set entire report meta array. */
function fi_report_set_all_meta(int $record_id, array $meta): bool {
	if (function_exists('fi_meta_set_all')) {
		return fi_meta_set_all($record_id, 'fi_reports', $meta);
	}

	return fi_report_update_meta_table($record_id, 'fi_reports', $meta);
}

/** Shared meta column updater for table-based JSON meta columns. Deprecated fallback; prefer fi_meta_set_all(). */
function fi_report_update_meta_table(int $record_id, string $table, array $meta): bool {
	global $wpdb;

	$record_id = absint($record_id);
	if ($record_id <= 0 || $table === '') {
		return false;
	}

	$table_name = $wpdb->prefix . preg_replace('/[^a-zA-Z0-9_]/', '', $table);
	$encoded = !empty($meta) ? wp_json_encode($meta) : null;

	$result = $wpdb->update(
		$table_name,
		['meta' => $encoded],
		['id' => $record_id],
		['%s'],
		['%d']
	);

	return $result !== false;
}