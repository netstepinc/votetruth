<?php if (!defined('ABSPATH')) { exit; }

/**
 * Render import page
 */
function fi_admin_import_render(): void {
	include __DIR__ . '/../views/import.php';
}

/**
 * Render Legiscan import page
 */
function fi_admin_legiscan_render(): void {
	include __DIR__ . '/../legiscan/admin-legiscan.php';
}

/**
 * Store a short-lived admin notice for the current user (shown after redirect).
 */
function fi_admin_legiscan_notice_set(string $type, string $message): void {
	$user_id = get_current_user_id();
	if (!$user_id) {
		return;
	}

	$type = in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info';
	set_transient('fi_legiscan_notice_' . $user_id, [
		'type' => $type,
		'message' => wp_kses_post($message),
	], 60);
}

/**
 * Render and clear the stored admin notice for the current user.
 */
function fi_admin_legiscan_notice_render(): void {
	$user_id = get_current_user_id();
	if (!$user_id) {
		return;
	}

	$notice = get_transient('fi_legiscan_notice_' . $user_id);
	if (empty($notice) || !is_array($notice)) {
		return;
	}

	delete_transient('fi_legiscan_notice_' . $user_id);

	$type = $notice['type'] ?? 'info';
	$message = $notice['message'] ?? '';
	if ($message === '') {
		return;
	}

	$class = 'notice notice-' . sanitize_html_class($type);
	echo '<div class="' . esc_attr($class) . '"><p>' . $message . '</p></div>';
}

/**
 * Build a safe redirect URL back to the Legiscan import page.
 *
 * @param array $args Query args to add to admin.php
 * @param string $hash Optional fragment (without #)
 */
function fi_admin_legiscan_redirect_url(array $args, string $hash = ''): string {
	$url = add_query_arg($args, admin_url('admin.php'));
	if ($hash !== '') {
		$url .= '#' . rawurlencode($hash);
	}
	return $url;
}

/**
 * Handle "Add Vote" from Legiscan import (POST, nonce-protected).
 */
function fi_admin_legiscan_handle_add_vote(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die(esc_html__('Sorry, you are not allowed to do that.', 'freedom-scorecard'));
	}

	check_admin_referer('fi_legiscan_add_vote');

	$scope = fi_scope_get_current();
	$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? $scope['gov'] ?? 'US'));
	$fetch = sanitize_text_field($_POST['fetch'] ?? '');
	$subtask = sanitize_text_field($_POST['subtask'] ?? 'votes');
	$roll_call_id = absint($_POST['roll_call_id'] ?? 0);
	$bill_number = sanitize_text_field($_POST['bill_number'] ?? '');
	$ls_session_id = absint($_POST['LS_session_id'] ?? 0); // Legiscan session_id

	$redirect_url = fi_admin_legiscan_redirect_url([
		'page' => 'fi-legiscan-import',
		'gov'  => $gov,
		'fetch' => $fetch,
		'subtask' => $subtask,
	]);

	if (!$roll_call_id || $bill_number === '' || !$ls_session_id) {
		fi_admin_legiscan_notice_set('error', 'Missing required vote parameters (session, bill number, or roll call ID).');
		wp_safe_redirect($redirect_url);
		exit;
	}

	$args_new = fi_legiscan_vote_data([
		'gov' => $gov,
		'LS_session_id' => $ls_session_id,
		'LS_bill_id' => $bill_number,
		'LS_roll_call_id' => $roll_call_id,
	]);

	if (isset($args_new['error'])) {
		fi_admin_legiscan_notice_set('error', esc_html($args_new['error']));
		wp_safe_redirect($redirect_url);
		exit;
	}

	try {
		//fi_log('ARGS fi_legiscan_create_vote: '.json_encode($args_new),__FILE__,__LINE__);
		$new_vote_id = fi_legiscan_create_vote($args_new);
	} catch (\Throwable $e) {
		fi_admin_legiscan_notice_set('error', 'Vote creation halted: ' . esc_html($e->getMessage()));
		wp_safe_redirect($redirect_url);
		exit;
	}

	if ($new_vote_id) {
		fi_admin_legiscan_notice_set(
			'success',
			'Vote created successfully. <a href="' . esc_url(fi_admin_edit_vote_url($new_vote_id)) . '" target="_blank">Edit Vote</a>'
		);
	} else {
		fi_admin_legiscan_notice_set('error', 'Vote creation failed. Please check the data and try again.');
	}

	wp_safe_redirect($redirect_url);
	exit;
}
add_action('admin_post_fi_legiscan_add_vote', 'fi_admin_legiscan_handle_add_vote');

/**
 * Handle "Add Person" (single or ALL) from Legiscan import (POST, nonce-protected).
 */
function fi_admin_legiscan_handle_add_person(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die(esc_html__('Sorry, you are not allowed to do that.', 'freedom-scorecard'));
	}

	check_admin_referer('fi_legiscan_add_person');

	$scope = fi_scope_get_current();
	$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? $scope['gov'] ?? 'US'));
	$fetch = sanitize_text_field($_POST['fetch'] ?? '');
	$subtask = sanitize_text_field($_POST['subtask'] ?? 'people');
	$people_id_raw = sanitize_text_field($_POST['people_id'] ?? '');
	$people_id = ($people_id_raw === 'ALL') ? 'ALL' : (string) absint($people_id_raw);

	// Get dataset state and directory from form submission (passed from the view)
	$dataset_state = strtoupper(sanitize_text_field($_POST['dataset_state'] ?? ''));
	$data_dir_name = sanitize_text_field($_POST['data_dir_name'] ?? '');

	$redirect_url = fi_admin_legiscan_redirect_url([
		'page'    => 'fi-legiscan-import',
		'gov'     => $gov,
		'fetch'   => $fetch,
		'subtask' => $subtask,
	]);

	if ($people_id === '' || $fetch === '' || $dataset_state === '' || $data_dir_name === '') {
		fi_admin_legiscan_notice_set('error', 'Missing required person parameters.');
		wp_safe_redirect($redirect_url);
		exit;
	}

	// Build path directly from submitted parameters (no lookup needed)
	$data_dir = (defined('FI_DIR_LEGISCAN') ? FI_DIR_LEGISCAN : (rtrim(FI_DIR_CACHE, "/\\") . DIRECTORY_SEPARATOR . 'legiscan' . DIRECTORY_SEPARATOR)) . $dataset_state . '/' . $data_dir_name . '/';
	$people_dir = $data_dir . 'people/';
	
	// Load dataset metadata for session info (still needed for creating legislators)
	$datasets = fi_legiscan_get_datasets($dataset_state);
	$session_data_for_import = [];
	if (is_array($datasets)) {
		foreach ($datasets as $data) {
			if ($fetch === ($data['dataset_hash'] ?? '')) {
				$session_data_for_import = $data;
				break;
			}
		}
	}

	$people_files = glob($people_dir . '*.json');
	if (empty($people_files)) {
		fi_admin_legiscan_notice_set('error', 'No people files found for this dataset.');
		wp_safe_redirect($redirect_url);
		exit;
	}

	$imported_count = 0;
	$failed_count = 0;
	$skipped_count = 0;
	$target_id = ($people_id === 'ALL') ? 0 : (int) $people_id;

	foreach ($people_files as $file) {
		if (!file_exists($file)) {
			continue;
		}

		$file_data = json_decode((string) file_get_contents($file), true);
		$person = $file_data['person'] ?? [];
		$personID = $person['people_id'] ?? null;
		if (!$personID) {
			continue;
		}

		if ($people_id === 'ALL' || (int) $personID === $target_id) {
			// For US (Congress), skip non-voting entities (territories + DC) based on district state code.
			if ($gov === 'US') {
				$district_raw = (string) ($person['district'] ?? '');
				$state_code = '';
				if ($district_raw !== '' && preg_match('/^(?:HD|SD)-([A-Z]{2})\b/', strtoupper($district_raw), $m)) {
					$state_code = $m[1];
				}
				if ($state_code !== '' && in_array($state_code, ['DC', 'PR', 'VI', 'GU', 'MP', 'AS'], true)) {
					$skipped_count++;
					if ($people_id !== 'ALL') {
						break;
					}
					continue;
				}
			}

			$new_legislator_id = fi_legiscan_create_legislator([
				'person' => $person,
				'session' => $session_data_for_import,
				'gov' => $gov,
			]);

			if ($new_legislator_id) {
				$imported_count++;
			} else {
				$failed_count++;
			}

			if ($people_id !== 'ALL') {
				break;
			}
		}
	}

	if ($people_id === 'ALL') {
		if ($imported_count > 0) {
			fi_admin_legiscan_notice_set(
				'success',
				esc_html($imported_count) . ' person(s) imported successfully.'
				. ($skipped_count > 0 ? ' ' . esc_html($skipped_count) . ' skipped.' : '')
				. ($failed_count > 0 ? ' ' . esc_html($failed_count) . ' failed.' : '')
			);
		} else {
			fi_admin_legiscan_notice_set(
				'error',
				'No people were imported.'
				. ($skipped_count > 0 ? ' ' . esc_html($skipped_count) . ' skipped.' : '')
				. ($failed_count > 0 ? ' ' . esc_html($failed_count) . ' failed.' : '')
			);
		}
	} else {
		if ($imported_count > 0) {
			global $wpdb;
			$legislator_id = $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}fi_legislators WHERE legiscan_id = %d LIMIT 1",
				$target_id
			));
			$edit_link = $legislator_id ? '<a href="' . esc_url(fi_admin_edit_legislator_url((int) $legislator_id)) . '" target="_blank">Edit Legislator</a>' : '';
			fi_admin_legiscan_notice_set('success', 'Person imported successfully. ' . $edit_link);
		} else {
			fi_admin_legiscan_notice_set('error', 'Person import failed. Please check the data and try again.');
		}
	}

	wp_safe_redirect($redirect_url);
	exit;
}
add_action('admin_post_fi_legiscan_add_person', 'fi_admin_legiscan_handle_add_person');

/**
 * Add one Legiscan person (by people_id) to an FI session. Shared by single and bulk handlers.
 * Does NOT redirect or set notices.
 *
 * Optional preloads (used by bulk): pass legislator map, existing-session set, and/or district cache
 * to avoid repeated queries. When provided, single-add skips the corresponding DB lookups.
 *
 * @param int    $people_id      Legiscan people_id
 * @param int    $session_id     FI session id
 * @param string $gov            Government code (e.g. US)
 * @param string $data_dir_name  Dataset directory name under legiscan/{state}/
 * @param string $legiscan_state State key for path (US or state code)
 * @param array|null $legislator_id_by_people  Optional: map people_id => fi_legislators.id (skip legislator query)
 * @param array|null $existing_legislator_ids_in_session  Optional: set of legislator_id already in session (skip get_id_by_pair)
 * @param array|null $district_cache  Optional: by-ref cache key => district_id for fi_district_id_from_legiscan
 * @return array ['ok' => true, 'legislator_id' => int] or ['ok' => false, 'error' => string]
 */
function fi_admin_legiscan_add_one_person_to_session(
	int $people_id,
	int $session_id,
	string $gov,
	string $data_dir_name,
	string $legiscan_state,
	?array $legislator_id_by_people = null,
	?array $existing_legislator_ids_in_session = null,
	?array &$district_cache = null
): array {
	$base = defined('FI_DIR_LEGISCAN') ? FI_DIR_LEGISCAN : (rtrim(FI_DIR_CACHE, "/\\") . DIRECTORY_SEPARATOR . 'legiscan' . DIRECTORY_SEPARATOR);
	$people_file = $base . $legiscan_state . '/' . $data_dir_name . '/people/' . $people_id . '.json';
	if (!is_readable($people_file)) {
		return ['ok' => false, 'error' => 'People file not found'];
	}
	$raw = json_decode((string) file_get_contents($people_file), true);
	$person = is_array($raw) ? ($raw['person'] ?? null) : null;
	if (!is_array($person)) {
		return ['ok' => false, 'error' => 'Invalid person JSON'];
	}

	if (is_array($legislator_id_by_people) && array_key_exists($people_id, $legislator_id_by_people)) {
		$legislator_id = (int) $legislator_id_by_people[$people_id];
	} else {
		global $wpdb;
		$legislator_id = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}fi_legislators WHERE legiscan_id = %d LIMIT 1",
			$people_id
		));
	}
	if ($legislator_id <= 0) {
		return ['ok' => false, 'error' => 'Legislator not found for people_id ' . $people_id];
	}

	if (is_array($existing_legislator_ids_in_session) && isset($existing_legislator_ids_in_session[$legislator_id])) {
		return ['ok' => true, 'legislator_id' => $legislator_id, 'skipped' => true];
	}

	$role = (string) ($person['role'] ?? '');
	$chamber = (stripos($role, 'sen') !== false) ? 'S' : 'H';
	$state_code = $gov;
	$district_raw = (string) ($person['district'] ?? '');
	if ($gov === 'US' && $district_raw !== '' && preg_match('/^(?:HD|SD)-([A-Z]{2})\b/', strtoupper($district_raw), $m)) {
		$state_code = $m[1];
	}
	$district_id = null;
	if ($district_raw !== '') {
		$cache_key = $district_raw . '|' . $gov;
		if (is_array($district_cache) && array_key_exists($cache_key, $district_cache)) {
			$district_id = $district_cache[$cache_key];
		} else {
			$district_id = function_exists('fi_district_id_from_legiscan') ? fi_district_id_from_legiscan($district_raw, $gov) : null;
			if (is_array($district_cache)) {
				$district_cache[$cache_key] = $district_id;
			}
		}
		if (!$district_id) {
			return ['ok' => false, 'error' => 'District not found: ' . $district_raw];
		}
	}

	if (!is_array($existing_legislator_ids_in_session)) {
		$existing_session_id = function_exists('fi_legislator_session_id') ? fi_legislator_session_id($legislator_id, $session_id) : null;
		if ($existing_session_id) {
			return ['ok' => true, 'legislator_id' => $legislator_id, 'skipped' => true];
		}
	}

	$session_data = [
		'legislator_id' => $legislator_id,
		'session_id' => $session_id,
		'gov' => $gov,
		'chamber' => $chamber,
		'party' => strtoupper((string) ($person['party'] ?? '')),
		'district' => $district_id,
		'state' => (string) $state_code,
	];
	fi_legislator_session_save($session_data, null);
	return ['ok' => true, 'legislator_id' => $legislator_id];
}

/**
 * Resolve FI session_id and dataset path from fetch hash and gov. Returns null on failure.
 *
 * @param string $fetch Fetch hash from POST
 * @param string $gov   Government code
 * @return array|null ['session_id' => int, 'data_dir_name' => string, 'legiscan_state' => string] or null
 */
function fi_admin_legiscan_resolve_session_from_fetch(string $fetch, string $gov): ?array {
	$legiscan_state = ($gov === 'US') ? 'US' : $gov;
	$datasets = fi_legiscan_get_datasets($legiscan_state);
	if (!is_array($datasets)) {
		return null;
	}
	$dataset = null;
	foreach ($datasets as $d) {
		if (($d['dataset_hash'] ?? '') === $fetch) {
			$dataset = $d;
			break;
		}
	}
	if (!is_array($dataset)) {
		return null;
	}
	$data_dir_name = (string) ($dataset['directory'] ?? '');
	$session_id_legiscan = (int) ($dataset['session_id'] ?? 0);
	if ($data_dir_name === '' || $session_id_legiscan <= 0) {
		return null;
	}
	$session = fi_session_get_by_legiscan_id($session_id_legiscan, $gov);
	$session_id = $session ? (int) ($session['id'] ?? 0) : 0;
	if ($session_id <= 0) {
		return null;
	}
	return ['session_id' => $session_id, 'data_dir_name' => $data_dir_name, 'legiscan_state' => $legiscan_state];
}

/**
 * Handle "Add to Session" (session assignment only) from Legiscan people list.
 * This does NOT re-import/update the legislator record; it only ensures the legislator is linked to the FI session.
 */
function fi_admin_legiscan_handle_add_person_to_session(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die(esc_html__('Sorry, you are not allowed to do that.', 'freedom-scorecard'));
	}
	check_admin_referer('fi_legiscan_add_person_to_session');

	$scope = fi_scope_get_current();
	$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? $scope['gov'] ?? 'US'));
	$fetch = sanitize_text_field($_POST['fetch'] ?? '');
	$subtask = sanitize_text_field($_POST['subtask'] ?? 'people');
	$people_id = absint($_POST['people_id'] ?? 0);

	$redirect_url = fi_admin_legiscan_redirect_url(['page' => 'fi-legiscan-import', 'gov' => $gov, 'fetch' => $fetch, 'subtask' => $subtask]);

	if (!$people_id || $fetch === '') {
		fi_admin_legiscan_notice_set('error', 'Missing required parameters (people_id / fetch).');
		wp_safe_redirect($redirect_url);
		exit;
	}

	$resolved = fi_admin_legiscan_resolve_session_from_fetch($fetch, $gov);
	if (!$resolved) {
		fi_admin_legiscan_notice_set('error', 'Could not find dataset or FI session for this fetch. Add Session first.');
		wp_safe_redirect($redirect_url);
		exit;
	}

	$result = fi_admin_legiscan_add_one_person_to_session($people_id, $resolved['session_id'], $gov, $resolved['data_dir_name'], $resolved['legiscan_state']);
	if (!$result['ok']) {
		fi_admin_legiscan_notice_set('error', $result['error']);
		wp_safe_redirect($redirect_url);
		exit;
	}
	if (!empty($result['skipped'])) {
		fi_admin_legiscan_notice_set('success', 'Legislator is already assigned to this session.');
	} else {
		fi_admin_legiscan_notice_set('success', 'Session assignment saved for legislator ID ' . esc_html((string) $result['legislator_id']) . '.');
	}
	wp_safe_redirect($redirect_url);
	exit;
}
add_action('admin_post_fi_legiscan_add_person_to_session', 'fi_admin_legiscan_handle_add_person_to_session');

if (!defined('FI_LEGISCAN_BULK_ADD_TO_SESSION_MAX')) {
	define('FI_LEGISCAN_BULK_ADD_TO_SESSION_MAX', 550);
}

/**
 * Handle bulk "Add to Session" (only the people_ids[] in the form).
 * Preloads legislator ids and existing session assignments in two queries to avoid N+1.
 */
function fi_admin_legiscan_handle_bulk_add_person_to_session(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die(esc_html__('Sorry, you are not allowed to do that.', 'freedom-scorecard'));
	}
	check_admin_referer('fi_legiscan_add_person_to_session');

	$scope = fi_scope_get_current();
	$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? $scope['gov'] ?? 'US'));
	$fetch = sanitize_text_field($_POST['fetch'] ?? '');
	$subtask = sanitize_text_field($_POST['subtask'] ?? 'people');
	$people_ids = isset($_POST['people_ids']) && is_array($_POST['people_ids']) ? array_map('absint', $_POST['people_ids']) : [];
	$people_ids = array_values(array_filter($people_ids));
	$people_ids = array_slice($people_ids, 0, FI_LEGISCAN_BULK_ADD_TO_SESSION_MAX);

	$redirect_url = fi_admin_legiscan_redirect_url(['page' => 'fi-legiscan-import', 'gov' => $gov, 'fetch' => $fetch, 'subtask' => $subtask]);

	if (empty($people_ids) || $fetch === '') {
		fi_admin_legiscan_notice_set('error', 'Missing required parameters (people_ids / fetch).');
		wp_safe_redirect($redirect_url);
		exit;
	}

	$resolved = fi_admin_legiscan_resolve_session_from_fetch($fetch, $gov);
	if (!$resolved) {
		fi_admin_legiscan_notice_set('error', 'Could not find dataset or FI session for this fetch. Add Session first.');
		wp_safe_redirect($redirect_url);
		exit;
	}

	global $wpdb;
	$pre = $wpdb->prefix;
	$legislator_id_by_people = [];
	$placeholders = implode(',', array_fill(0, count($people_ids), '%d'));
	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT id, legiscan_id FROM {$pre}fi_legislators WHERE legiscan_id IN ($placeholders)",
		...$people_ids
	));
	foreach ($rows as $r) {
		$legislator_id_by_people[(int) $r->legiscan_id] = (int) $r->id;
	}

	$legislator_ids = array_values($legislator_id_by_people);
	$existing_legislator_ids_in_session = [];
	if (!empty($legislator_ids)) {
		$placeholders_l = implode(',', array_fill(0, count($legislator_ids), '%d'));
		$existing = $wpdb->get_col($wpdb->prepare(
			"SELECT legislator_id FROM {$pre}fi_legislator_sessions WHERE session_id = %d AND legislator_id IN ($placeholders_l)",
			$resolved['session_id'],
			...$legislator_ids
		));
		foreach ($existing as $lid) {
			$existing_legislator_ids_in_session[(int) $lid] = true;
		}
	}

	$district_cache = [];
	$added = 0;
	$skipped = 0;
	$errors = [];
	foreach ($people_ids as $pid) {
		$result = fi_admin_legiscan_add_one_person_to_session(
			$pid,
			$resolved['session_id'],
			$gov,
			$resolved['data_dir_name'],
			$resolved['legiscan_state'],
			$legislator_id_by_people,
			$existing_legislator_ids_in_session,
			$district_cache
		);
		if ($result['ok']) {
			if (!empty($result['skipped'])) {
				$skipped++;
			} else {
				$added++;
				$existing_legislator_ids_in_session[(int) $result['legislator_id']] = true;
			}
		} else {
			$errors[] = $result['error'];
		}
	}

	$msg = sprintf('%d legislator(s) added to session.', $added);
	if ($skipped > 0) {
		$msg .= ' ' . $skipped . ' already assigned.';
	}
	if (!empty($errors)) {
		fi_admin_legiscan_notice_set('warning', $msg . ' ' . count($errors) . ' failed: ' . implode('; ', array_slice($errors, 0, 3)) . (count($errors) > 3 ? '…' : ''));
	} else {
		fi_admin_legiscan_notice_set('success', $msg);
	}
	wp_safe_redirect($redirect_url);
	exit;
}
add_action('admin_post_fi_legiscan_bulk_add_person_to_session', 'fi_admin_legiscan_handle_bulk_add_person_to_session');

/**
 * Handle "Fetch Session Data" (download + unzip dataset) from Legiscan import.
 */
function fi_admin_legiscan_handle_fetch_dataset(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die(esc_html__('Sorry, you are not allowed to do that.', 'freedom-scorecard'));
	}

	check_admin_referer('fi_legiscan_fetch_dataset');

	$scope = fi_scope_get_current();
	$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? $scope['gov'] ?? 'US'));
	$session_id = absint($_POST['session_id'] ?? 0);
	$state_id = absint($_POST['state_id'] ?? 0);
	$access_key = sanitize_text_field($_POST['access_key'] ?? '');
	$year_start = absint($_POST['year_start'] ?? 0);
	$year_end = absint($_POST['year_end'] ?? 0);
	$session_name = sanitize_text_field($_POST['session_name'] ?? '');
	$session_title = sanitize_text_field($_POST['session_title'] ?? '');

	$redirect_url = fi_admin_legiscan_redirect_url([
		'page' => 'fi-legiscan-import',
		'gov'  => $gov,
	]);

	if (!$session_id || !$state_id || $access_key === '' || !$year_start || !$year_end || $session_title === '') {
		fi_admin_legiscan_notice_set('error', 'Missing required parameters (session_id, state_id, access_key, year_start, year_end, session_title).');
		wp_safe_redirect($redirect_url);
		exit;
	}

	// Build dataset array for standardized directory naming
	$dataset = [
		'session_id' => $session_id,
		'state_id' => $state_id,
		'year_start' => $year_start,
		'year_end' => $year_end,
		'session_name' => $session_name,
		'session_title' => $session_title,
		'access_key' => $access_key,
	];

	// Use standardized directory structure: legiscan/{gov}/{year_start}_{session_title}/
	$legiscan_base = defined('FI_DIR_LEGISCAN') ? FI_DIR_LEGISCAN : (rtrim(FI_DIR_CACHE, "/\\") . DIRECTORY_SEPARATOR . 'legiscan' . DIRECTORY_SEPARATOR);
	$govdir = rtrim($legiscan_base, "/\\") . DIRECTORY_SEPARATOR . $gov . '/';
	$datadir = fi_legiscan_session_dir($dataset);
	$data_dir = $govdir . $datadir . '/';

	// Ensure base directory exists.
	if (!is_dir($legiscan_base)) {
		wp_mkdir_p($legiscan_base);
	}
	// Ensure dedicated subdirs exist for reference JSON + ZIPs.
	if (defined('FI_DIR_LEGISCAN_JSON') && !is_dir(FI_DIR_LEGISCAN_JSON)) {
		wp_mkdir_p(FI_DIR_LEGISCAN_JSON);
	}
	if (defined('FI_DIR_LEGISCAN_ZIP') && !is_dir(FI_DIR_LEGISCAN_ZIP)) {
		wp_mkdir_p(FI_DIR_LEGISCAN_ZIP);
	}

	// Use dataID for ZIP filename only (for uniqueness)
	$dataID = $gov . '_' . $datadir;
	$temp_zip = (defined('FI_DIR_LEGISCAN_ZIP') ? FI_DIR_LEGISCAN_ZIP : (rtrim($legiscan_base, "/\\") . DIRECTORY_SEPARATOR . '_zip' . DIRECTORY_SEPARATOR)) . $dataID . '.zip';

	// If unpacked content exists, skip API call.
	$skip_api_call = false;
	if (is_dir($data_dir)) {
		$has_content = false;
		foreach (['people', 'bill', 'vote'] as $subdir) {
			$subdir_path = $data_dir . $subdir . '/';
			if (is_dir($subdir_path)) {
				$files = glob($subdir_path . '*.json');
				if (!empty($files)) {
					$has_content = true;
					break;
				}
			}
		}
		if ($has_content) {
			$skip_api_call = true;
			fi_admin_legiscan_notice_set('info', 'Dataset already exists on disk. Using cached files from: <code>' . esc_html($data_dir) . '</code>. No API call needed.');
		}
	}

	if (!$skip_api_call) {
		// Cache for 7 days when not unpacked; never expire when unpacked.
		$cache_expires = is_dir($data_dir) ? 0 : 7;

		$response = fi_legiscan_api_request([
			'op' => 'getDataset',
			'key' => 'getDataset_' . $dataID,
			'expires' => $cache_expires,
			'params' => [
				'id' => $session_id,
				'access_key' => $access_key,
			],
		]);

		if (!$response || !isset($response['dataset']['zip'])) {
			fi_admin_legiscan_notice_set('error', 'Failed to fetch dataset from Legiscan API.');
			wp_safe_redirect($redirect_url);
			exit;
		}

		$zip_data = base64_decode($response['dataset']['zip']);
		if ($zip_data === false) {
			fi_admin_legiscan_notice_set('error', 'Failed to decode ZIP data from API response.');
			wp_safe_redirect($redirect_url);
			exit;
		}

		if (file_put_contents($temp_zip, $zip_data) === false) {
			fi_admin_legiscan_notice_set('error', 'Failed to save ZIP file to disk.');
			wp_safe_redirect($redirect_url);
			exit;
		}
	}

	// If directory already has content, skip extraction; otherwise extract ZIP.
	$needs_extraction = true;
	if (is_dir($data_dir)) {
		$has_content = false;
		foreach (['people', 'bill', 'vote'] as $subdir) {
			$subdir_path = $data_dir . $subdir . '/';
			if (is_dir($subdir_path)) {
				$files = glob($subdir_path . '*.json');
				if (!empty($files)) {
					$has_content = true;
					break;
				}
			}
		}
		if ($has_content) {
			$needs_extraction = false;
		}
	}

	if ($needs_extraction) {
		$zip = new \ZipArchive();
		$result = $zip->open($temp_zip);
		if ($result === true) {
			// Extract to temp directory first
			$temp_extract = get_temp_dir() . 'fi_legiscan_extract_' . uniqid() . '/';
			if (!wp_mkdir_p($temp_extract)) {
				fi_admin_legiscan_notice_set('error', 'Failed to create temporary extraction directory.');
				wp_safe_redirect($redirect_url);
				exit;
			}

			$zip->extractTo($temp_extract);
			$zip->close();

			// Rename session directory to standardized format
			$renamed_extract = fi_legiscan_rename_session_directory($temp_extract, $dataset);
			if (!$renamed_extract) {
				fi_admin_legiscan_notice_set('error', 'Failed to rename session directory after extraction.');
				wp_safe_redirect($redirect_url);
				exit;
			}

			// Move renamed session to permanent location
			// Find state directory in temp extract
			$items = array_diff(scandir($temp_extract), ['.', '..']);
			$state_dir = null;
			foreach ($items as $item) {
				$item_path = rtrim($temp_extract, '/\\') . DIRECTORY_SEPARATOR . $item;
				if (is_dir($item_path)) {
					$state_dir = $item;
					break;
				}
			}

			if ($state_dir) {
				$temp_state_path = rtrim($temp_extract, '/\\') . DIRECTORY_SEPARATOR . $state_dir . DIRECTORY_SEPARATOR;
				$perm_state_path = rtrim($legiscan_base, '/\\') . DIRECTORY_SEPARATOR . $state_dir . DIRECTORY_SEPARATOR;

				// Ensure permanent state directory exists
				if (!is_dir($perm_state_path)) {
					wp_mkdir_p($perm_state_path);
				}

				// Move session directory from temp to permanent location
				$session_items = array_diff(scandir($temp_state_path), ['.', '..']);
				foreach ($session_items as $session_item) {
					$temp_session_path = rtrim($temp_state_path, '/\\') . DIRECTORY_SEPARATOR . $session_item;
					$perm_session_path = rtrim($perm_state_path, '/\\') . DIRECTORY_SEPARATOR . $session_item;
					
					if (is_dir($temp_session_path)) {
						// Use rename for atomic move
						if (!rename($temp_session_path, $perm_session_path)) {
							// Fallback to recursive copy if rename fails (cross-filesystem)
							fi_legiscan_recursive_copy($temp_session_path, $perm_session_path);
						}
					}
				}

				// Cleanup temp directory
				fi_legiscan_cleanup_extract_dir($temp_extract);
			}

			if (is_dir($data_dir)) {
				fi_admin_legiscan_notice_set('success', 'Dataset fetched and unpacked successfully. Files available in: <code>' . esc_html(str_replace(ABSPATH, '', $data_dir)) . '</code>');
			} else {
				fi_admin_legiscan_notice_set('warning', 'Dataset extracted but expected directory not found: <code>' . esc_html($data_dir) . '</code>. Check ZIP structure.');
			}
		} else {
			fi_admin_legiscan_notice_set('error', 'Failed to extract ZIP file (code: ' . esc_html((string) $result) . ').');
			wp_safe_redirect($redirect_url);
			exit;
		}
	}

	wp_safe_redirect($redirect_url);
	exit;
}
add_action('admin_post_fi_legiscan_fetch_dataset', 'fi_admin_legiscan_handle_fetch_dataset');

/**
 * Handle "Add Session" from Legiscan import (create FI session record from dataset hash).
 */
function fi_admin_legiscan_handle_sync_session(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die(esc_html__('Sorry, you are not allowed to do that.', 'freedom-scorecard'));
	}

	check_admin_referer('fi_legiscan_sync_session');

	$scope = fi_scope_get_current();
	$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? $scope['gov'] ?? 'US'));
	$dataset_hash = sanitize_text_field($_POST['dataset_hash'] ?? '');

	$redirect_url = fi_admin_legiscan_redirect_url([
		'page' => 'fi-legiscan-import',
		'gov'  => $gov,
	]);

	if ($dataset_hash === '') {
		fi_admin_legiscan_notice_set('error', 'Missing dataset hash.');
		wp_safe_redirect($redirect_url);
		exit;
	}

	$legiscan_state = ($gov === 'US') ? 'US' : $gov;
	$datasets = fi_legiscan_get_datasets($legiscan_state);
	if (!$datasets || !is_array($datasets)) {
		fi_admin_legiscan_notice_set('error', 'Could not retrieve dataset list.');
		wp_safe_redirect($redirect_url);
		exit;
	}

	$dataset = null;
	foreach ($datasets as $ds) {
		if (($ds['dataset_hash'] ?? '') === $dataset_hash) {
			$dataset = $ds;
			break;
		}
	}

	if (!$dataset) {
		fi_admin_legiscan_notice_set('error', 'Dataset not found.');
		wp_safe_redirect($redirect_url);
		exit;
	}

	$session_name = $dataset['session_name'] ?? $dataset['session_title'] ?? '';
	$session_title = $dataset['session_title'] ?? '';
	$legiscan_session_id = (int) ($dataset['session_id'] ?? 0);
	$year_start = (int) ($dataset['year_start'] ?? 0);
	$year_end = (int) ($dataset['year_end'] ?? 0);
	
	// Use standardized directory naming via fi_legiscan_session_dir()
	$legiscan_folder = fi_legiscan_session_dir($dataset);

	if ($session_title === '' || !$legiscan_session_id) {
		fi_admin_legiscan_notice_set('error', 'Invalid session data.');
		wp_safe_redirect($redirect_url);
		exit;
	}

	$existing_session = fi_session_get_by_legiscan_id($legiscan_session_id, $gov);

	if ($existing_session) {
		// Update existing session meta with dataset info
		$existing_meta = !empty($existing_session->meta) ? json_decode($existing_session->meta, true) : [];
		if (!is_array($existing_meta)) {
			$existing_meta = [];
		}
		$existing_meta['legiscan'] = $dataset; // Save entire dataset
		$existing_meta['name_lg'] = $session_name; // Save verbose legiscan name
		if ($legiscan_folder !== '') {
			$existing_meta['legiscan_folder'] = $legiscan_folder;
		}

		$update_data = ['meta' => $existing_meta];
		if (empty($existing_session->legiscan_id)) {
			$update_data['legiscan_id'] = $legiscan_session_id;
		}

		fi_session_update($existing_session->id, $update_data);
		fi_admin_legiscan_notice_set('info', "Session '{$session_title}' already exists. Dataset will be available for import.");
		wp_safe_redirect($redirect_url);
		exit;
	}

	$date_start = $year_start ? "{$year_start}-01-01" : null;
	$date_end = $year_end ? "{$year_end}-12-31" : null;

	// Create new session using session_title as name.
	$new_session_id = fi_session_save([
		'gov' => (string) $gov,
		'legiscan_id' => $legiscan_session_id,
		'name' => $session_title,
		'date_start' => $date_start,
		'date_end' => $date_end,
		'meta' => [
			'legiscan' => $dataset,
			'name_lg' => $session_name,
			'legiscan_folder' => $legiscan_folder,
		],
	]);

	if ($new_session_id) {
		fi_admin_legiscan_notice_set('success', "Session '{$session_title}' added successfully.");
	} else {
		fi_admin_legiscan_notice_set('error', "Failed to create session '{$session_title}'.");
	}

	wp_safe_redirect($redirect_url);
	exit;
}
add_action('admin_post_fi_legiscan_sync_session', 'fi_admin_legiscan_handle_sync_session');

/**
 * Handle "Refresh Data" from Legiscan import (delete unpacked directory + cached API response).
 */
function fi_admin_legiscan_handle_refresh_data(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die(esc_html__('Sorry, you are not allowed to do that.', 'freedom-scorecard'));
	}

	check_admin_referer('fi_legiscan_refresh_data');

	$scope = fi_scope_get_current();
	$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? $scope['gov'] ?? 'US'));
	$data_fetch = sanitize_text_field($_POST['fetch'] ?? '');

	$redirect_url = fi_admin_legiscan_redirect_url([
		'page' => 'fi-legiscan-import',
		'gov'  => $gov,
	]);

	if ($data_fetch === '') {
		fi_admin_legiscan_notice_set('error', 'Missing dataset hash parameter.');
		wp_safe_redirect($redirect_url);
		exit;
	}

	$legiscan_state = ($gov === 'US') ? 'US' : $gov;
	$datasets = fi_legiscan_get_datasets($legiscan_state);
	if (!is_array($datasets)) {
		fi_admin_legiscan_notice_set('error', 'Could not load dataset list.');
		wp_safe_redirect($redirect_url);
		exit;
	}

	$target_dataset = null;
	foreach ($datasets as $data) {
		if (($data['dataset_hash'] ?? '') === $data_fetch) {
			$target_dataset = $data;
			break;
		}
	}

	if (!$target_dataset) {
		fi_admin_legiscan_notice_set('error', 'Dataset not found.');
		wp_safe_redirect($redirect_url);
		exit;
	}

	$datadir = (string) ($target_dataset['directory'] ?? '');
	$legiscan_base = defined('FI_DIR_LEGISCAN') ? FI_DIR_LEGISCAN : (rtrim(FI_DIR_CACHE, "/\\") . DIRECTORY_SEPARATOR . 'legiscan' . DIRECTORY_SEPARATOR);
	// Use legiscan_state (not current scope's gov) to build path since dataset was fetched under that state
	$data_dir = rtrim($legiscan_base, "/\\") . DIRECTORY_SEPARATOR . $legiscan_state . '/' . $datadir . '/';

	$dataID = $legiscan_state . '_' . $datadir;
	$cache_file = (defined('FI_DIR_LEGISCAN_JSON') ? FI_DIR_LEGISCAN_JSON : (rtrim($legiscan_base, "/\\") . DIRECTORY_SEPARATOR . '_json' . DIRECTORY_SEPARATOR)) . 'getDataset_' . $dataID . '.json';

	$deleted_items = [];

	if (is_dir($data_dir)) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($data_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($files as $fileinfo) {
			$todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
			@$todo($fileinfo->getRealPath());
		}

		@rmdir($data_dir);
		if (!is_dir($data_dir)) {
			$deleted_items[] = 'Session directory: ' . $datadir;
		}
	}

	if (file_exists($cache_file) && @unlink($cache_file)) {
		$deleted_items[] = 'Cached API response';
	}

	if (!empty($deleted_items)) {
		fi_admin_legiscan_notice_set('success', 'Deleted: ' . esc_html(implode(', ', $deleted_items)) . '. Next "Fetch Session Data" will fetch fresh data from Legiscan API.');
	} else {
		fi_admin_legiscan_notice_set('info', 'No cached data found to delete.');
	}

	wp_safe_redirect($redirect_url);
	exit;
}
add_action('admin_post_fi_legiscan_refresh_data', 'fi_admin_legiscan_handle_refresh_data');

/**
 * Render migrate page
 */
function fi_admin_migrate_render(): void {
	require_once __DIR__ . '/../migrate/json-migrate.php';
	fi_admin_migrate_json_render_page();
}

/**
 * Handle import action with visual output
 */
function fi_admin_import_handle_action(): void {
	$phase = sanitize_text_field($_GET['phase'] ?? '1');
	include __DIR__ . '/../views/import-action.php';
	exit;
}

/**
 * Handle simple import action
 */
function fi_admin_import_handle_simple_action(): void {
	$blog_id = intval($_GET['blog_id'] ?? 0);
	
	if (!$blog_id) {
		wp_die('Blog ID required');
	}
	include __DIR__ . '/../views/import-action-simple.php';
	exit;
}