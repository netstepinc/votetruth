<?php if (!defined('ABSPATH')) { exit; }

/**
 * Handle admin actions
 */
function fi_admin_actions_handle(): void {
	// Handle legislator form submissions early (before any output)
	if (isset($_GET['page']) && $_GET['page'] === 'fi-legislators' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fi_legislator_nonce'])) {
		$scope = fi_scope_get_current();
		fi_admin_legislators_handle_save($scope);
		// handle_legislator_save will redirect and exit, so we never reach here on successful save
	}
	// Handle vote form submissions early (before admin header output)
	// Summary: votes were previously saved during page render (too late to redirect); this runs on admin_init.
	if (isset($_GET['page']) && $_GET['page'] === 'fi-votes' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fi_vote_nonce'])) {
		$scope = fi_scope_get_current();
		fi_admin_votes_handle_save($scope);
		// On successful save the handler will redirect+exit; on error it returns so the page can render errors.
	}
	// Handle report form submissions early (before admin header output)
	if (isset($_GET['page']) && $_GET['page'] === 'fi-reports' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fi_report_nonce'])) {
		$scope = fi_scope_get_current();
		fi_admin_reports_handle_save($scope);
	}
	if (!isset($_GET['page']) || $_GET['page'] === null || strpos($_GET['page'], 'fi-') !== 0) {
		return;
	}
	
	$action = $_GET['action'] ?? '';
	
	switch ($action) {
		case 'delete':
			fi_admin_actions_handle_delete();
			break;
		case 'recalculate_scores':
			fi_admin_actions_handle_recalculate_scores();
			break;
	}
}
add_action('admin_init', 'fi_admin_actions_handle');

/**
 * POST handler: delete legislator (with nonce + capability check)
 */
function fi_admin_post_delete_legislator(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die('Insufficient permissions.');
	}

	check_admin_referer('fi_delete_legislator', 'fi_delete_legislator_nonce');

	$legislator_id = absint($_POST['legislator_id'] ?? 0);
	if (!$legislator_id) {
		wp_die('Missing legislator ID.');
	}

	$deleted = fi_legislator_delete($legislator_id);

	$return_url = '';
	if (isset($_POST['return_url']) && is_string($_POST['return_url'])) {
		$candidate = esc_url_raw(wp_unslash($_POST['return_url']));
		$return_url = wp_validate_redirect($candidate, '');
	}

	$redirect_base = $return_url ?: fi_admin_url('fi-legislators');
	$redirect = add_query_arg([
		'deleted' => $deleted ? 1 : 0,
	], $redirect_base);
	wp_safe_redirect($redirect);
	exit;
}
add_action('admin_post_fi_delete_legislator', 'fi_admin_post_delete_legislator');

/**
 * POST handler: add/update legislator session assignment
 *
 * Summary:
 * - Creates or updates a fi_legislator_sessions row for (legislator_id, session_id).
 * - Chamber mapping: rep => R, sen => S (user selects explicitly in UI).
 */
function fi_admin_post_legislator_session_save(): void {
	global $wpdb;
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die('Insufficient permissions.');
	}

	check_admin_referer('fi_legislator_session_save', 'fi_legislator_session_nonce');

	$ls_id = absint($_POST['ls_id'] ?? 0);
	$legislator_id = absint($_POST['legislator_id'] ?? 0);
	$session_id = absint($_POST['session_id'] ?? 0);
	$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? ''));
	$state = strtoupper(sanitize_text_field($_POST['state'] ?? ''));
	$chamber = strtoupper(sanitize_text_field($_POST['chamber'] ?? ''));
	$party = strtoupper(sanitize_text_field($_POST['party'] ?? ''));
	$district_id = absint($_POST['district_id'] ?? 0);

	if (!$legislator_id || !$session_id || $gov === '') {
		wp_die('Missing required fields (legislator, session, or gov).');
	}
	//CHAMBERFLAG
	if (!in_array($chamber, ['H', 'S'], true)) {
		wp_die('Invalid chamber. Must be H or S.');
	}

	// For Senate entries (US or state), district may be legitimately blank.
	$district = $district_id ? (string) $district_id : null;

	// Summary: validate state code if provided; keep null when not applicable/invalid.
	if ($state !== '') {
		$state_options = function_exists('fi_state_options') ? (array) fi_state_options() : [];
		if (!isset($state_options[$state])) {
			$state = '';
		}
	}

	// Optional date range for assignment (chamber change within session); store as Y-m-d or null.
	$raw_start = sanitize_text_field($_POST['date_start'] ?? '');
	$raw_end   = sanitize_text_field($_POST['date_end'] ?? '');
	$date_start = null;
	$date_end   = null;
	if ($raw_start !== '') {
		$t = strtotime($raw_start);
		$date_start = ($t !== false) ? gmdate('Y-m-d', $t) : null;
	}
	if ($raw_end !== '') {
		$t = strtotime($raw_end);
		$date_end = ($t !== false) ? gmdate('Y-m-d', $t) : null;
	}

	$table = defined('TBFI_LEGISLATOR_SESSIONS') ? TBFI_LEGISLATOR_SESSIONS : $wpdb->prefix . 'fi_legislator_sessions';

	if ($ls_id > 0) {
		// Update existing row by fi_legislator_sessions.id; verify it belongs to this legislator.
		$row_legislator_id = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT legislator_id FROM {$table} WHERE id = %d LIMIT 1",
			$ls_id
		));
		if ($row_legislator_id !== $legislator_id) {
			wp_die('Session assignment does not belong to this legislator.');
		}
		$update_fields = [
			'gov' => $gov,
			'state' => ($state !== '') ? $state : null,
			'chamber' => $chamber,
			'district' => $district,
			'party' => ($party !== '') ? $party : null,
			'date_start' => $date_start,
			'date_end' => $date_end,
		];
		$result = $wpdb->update(
			$table,
			$update_fields,
			['id' => $ls_id],
			['%s', '%s', '%s', '%s', '%s', '%s', '%s'],
			['%d']
		);
		if ($result === false) {
			wp_die('Failed to update session assignment.');
		}
	} else {
		// Insert new assignment.
		$data = [
			'legislator_id' => $legislator_id,
			'session_id' => $session_id,
			'gov' => $gov,
			'state' => ($state !== '') ? $state : null,
			'chamber' => $chamber,
			'district' => $district,
			'party' => ($party !== '') ? $party : null,
			'date_start' => $date_start,
			'date_end' => $date_end,
		];
		$new_id = fi_legislator_session_save($data, null);
		if (!$new_id) {
			wp_die('Failed to create session assignment.');
		}
	}

	// Summary: clear cached legislator lists/searches so UI reflects updates immediately.
	if (function_exists('fi_cache_clear')) {
		fi_cache_clear('legislators');
	}

	// Redirect back without edit_ls_id so we are not stuck in edit-assignment mode.
	$redirect = add_query_arg([
		'page' => 'fi-legislators',
		'action' => 'edit',
		'legislator_id' => $legislator_id,
		'session_updated' => 1,
	], admin_url('admin.php'));
	if (!empty($_POST['return_url'])) {
		$return_url = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['return_url'])), '');
		if ($return_url) {
			$redirect = add_query_arg(['return_url' => urlencode($return_url)], $redirect);
		}
	}
	$redirect .= '#fi-session-assignments';
	wp_safe_redirect($redirect);
	exit;
}
add_action('admin_post_fi_legislator_session_save', 'fi_admin_post_legislator_session_save');

/**
 * POST handler: delete legislator session assignment
 *
 * Summary:
 * - Deletes a single fi_legislator_sessions row by ID.
 * - Redirects back to the legislator edit screen (anchored to Session Assignments).
 */
function fi_admin_post_legislator_session_delete(): void {
	global $wpdb;
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die('Insufficient permissions.');
	}

	check_admin_referer('fi_legislator_session_delete', 'fi_legislator_session_delete_nonce');

	$ls_id = absint($_POST['ls_id'] ?? 0);
	$legislator_id = absint($_POST['legislator_id'] ?? 0);

	if (!$ls_id || !$legislator_id) {
		wp_die('Missing required fields (session assignment id or legislator).');
	}

	// Verify the row belongs to this legislator before deleting.
	$table = defined('TBFI_LEGISLATOR_SESSIONS') ? TBFI_LEGISLATOR_SESSIONS : $wpdb->prefix . 'fi_legislator_sessions';
	$row_legislator_id = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT legislator_id FROM {$table} WHERE id = %d LIMIT 1",
		$ls_id
	));
	if ($row_legislator_id !== $legislator_id) {
		wp_die('Session assignment does not belong to this legislator.');
	}

	$deleted = function_exists('fi_legislator_session_delete') ? fi_legislator_session_delete($ls_id) : false;

	$redirect = add_query_arg([
		'page' => 'fi-legislators',
		'action' => 'edit',
		'legislator_id' => $legislator_id,
		'session_deleted' => $deleted ? 1 : 0,
	], admin_url('admin.php'));
	if (!empty($_POST['return_url'])) {
		$return_url = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['return_url'])), '');
		if ($return_url) {
			$redirect = add_query_arg(['return_url' => urlencode($return_url)], $redirect);
		}
	}
	$redirect .= '#fi-session-assignments';
	wp_safe_redirect($redirect);
	exit;
}
add_action('admin_post_fi_legislator_session_delete', 'fi_admin_post_legislator_session_delete');

/**
 * Handle delete action
 */
function fi_admin_actions_handle_delete(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}
	
	$entity_type = $_GET['entity_type'] ?? '';
	$entity_id = absint($_GET['entity_id'] ?? 0);
	
	if (!$entity_type || !$entity_id) {
		return;
	}
	
	global $wpdb;
	
	switch ($entity_type) {
		case 'session':
			$wpdb->delete("{$wpdb->prefix}fi_sessions", ['id' => $entity_id], ['%d']);
			break;
		case 'legislator':
			// Use proper delete function (clears related rows and cache).
			fi_legislator_delete($entity_id);
			break;
		case 'vote':
			// Use proper delete function which handles rollcalls
			fi_vote_delete($entity_id);
			break;
		case 'report':
			$wpdb->delete("{$wpdb->prefix}fi_reports", ['id' => $entity_id], ['%d']);
			break;
	}
	
	wp_redirect(remove_query_arg(['action', 'entity_type', 'entity_id']));
	exit;
}

/**
 * Handle recalculate scores
 */
function fi_admin_actions_handle_recalculate_scores(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}
	$scope = function_exists('fi_scope_get_current') ? fi_scope_get_current() : [];
	$gov = strtoupper((string) ($scope['gov'] ?? ''));
	$session_id = absint($_GET['session_id'] ?? 0);
	$calculated = fi_score_calculate_all($gov ?: null, $session_id ?: null);
	add_settings_error('fi_scoring', 'scores_calculated', "Calculated {$calculated} scores.", 'updated');
	wp_redirect(add_query_arg('_fi_ts', time(), remove_query_arg(['action', 'gov', 'session_id', '_fi_ts'])));
	exit;
}