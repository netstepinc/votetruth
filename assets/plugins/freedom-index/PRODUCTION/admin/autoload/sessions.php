<?php if (!defined('ABSPATH')) { exit; }

/**
 * Render sessions page
 */
function fi_admin_sessions_render(): void {
	include __DIR__ . '/../views/sessions.php';
}

/**
 * Render session add/edit form
 */
function fi_admin_sessions_render_form(array $scope, string $action): void {
	include __DIR__ . '/../views/session-edit.php';
}

/**
 * Default session blueprint for new entries
 */
function fi_admin_sessions_get_defaults(array $scope): object {
	return (object) [
		'id' => null,
		'parent_id' => null,
		'legiscan_id' => null,
		'gov' => strtoupper($scope['gov'] ?? 'US'),
		'slug' => '',
		'name' => '',
		'date_start' => '',
		'date_end' => '',
		'is_current' => 0,
		'status' => 'draft',
		'meta' => [],
	];
}

/**
 * Get parent session options for select
 */
function fi_admin_sessions_get_parent_options(string $gov, ?int $exclude_id = null): array {
	$sessions = fi_sessions_get_by_gov($gov, [
		'orderby' => 'date_start',
		'order' => 'DESC',
		'parent_id' => null,
	]);

	//FORCE TOP LEVEL ONLY: Chidren can't have children.
	$options = ['' => 'None (Top-level Session)'];
	foreach ($sessions as $session) {
		if ($exclude_id && (int) $session->id === $exclude_id) {
			continue;
		}
		if ($session->parent_id) {
			continue;
		}
//' (' . ($session->date_start ? date('Y', strtotime($session->date_start)) : '') . ')'
		$dates = '';
		if ($session->date_start) {
			$dates .= date('Y', strtotime($session->date_start));
		}
		if ($session->date_end) {
			$dates .= ' - ' . date('Y', strtotime($session->date_end));
		}
		if ($dates) {
			$dates = ' (' . $dates . ')';
		}

		$options[$session->id] = $session->name . $dates;
	}

	return $options;
}

/**
 * Meta entries not surfaced via the form
 */
function fi_admin_sessions_get_extra_meta(object $session): array {
	$meta = is_array($session->meta ?? null) ? $session->meta : [];
	return $meta;
}

/**
 * Handle POST submissions for sessions
 */
function fi_admin_sessions_maybe_handle_save(array $scope): void {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}

	if (!isset($_POST['fi_session_nonce'])) {
		return;
	}

	fi_admin_sessions_handle_save($scope);
}

/**
 * Persist session data
 */
function fi_admin_sessions_handle_save(array $scope): void {
	if (!wp_verify_nonce($_POST['fi_session_nonce'], 'fi_save_session')) {
		wp_die('Security check failed.');
	}

	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die('Insufficient permissions.');
	}

	$session_id = isset($_POST['session_id']) ? absint($_POST['session_id']) : null;
	// Summary: Always respect the posted gov for this record (gov is readonly on the form).
	// The admin scope can be stale/mismatched when someone opens an edit link in a new tab.
	$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? ($scope['gov'] ?? 'US')));
	$name = sanitize_text_field($_POST['name'] ?? '');

	if ($name === '') {
		wp_die('Session name is required.');
	}

	$slug = sanitize_title($_POST['slug'] ?? '');
	if ($slug === '') {
		$slug = sanitize_title($name);
	}

	$status = sanitize_text_field($_POST['status'] ?? 'draft');
	if (!in_array($status, ['publish', 'draft'], true)) {
		$status = 'draft';
	}
	
	$data = [
		'gov' => $gov,
		'name' => $name,
		'slug' => $slug,
		'parent_id' => (!empty($_POST['parent_id']) && absint($_POST['parent_id']) > 0) ? absint($_POST['parent_id']) : null,
		'legiscan_id' => (!empty($_POST['legiscan_id']) && absint($_POST['legiscan_id']) > 0) ? absint($_POST['legiscan_id']) : null,
		'date_start' => sanitize_text_field($_POST['date_start'] ?? '') ?: null,
		'date_end' => sanitize_text_field($_POST['date_end'] ?? '') ?: null,
		'is_current' => !empty($_POST['is_current']) ? 1 : 0,
		'status' => $status,
	];

	// Sessions::save() will handle unsetting other current sessions automatically
	$saved_id = fi_session_save($data, $session_id);
	if (!$saved_id) {
		global $wpdb;
		wp_die('Unable to save session.' . (!empty($wpdb->last_error) ? '<br><br><code>' . esc_html($wpdb->last_error) . '</code>' : ''));
	}

	$redirect = fi_admin_edit_session_url($saved_id, ['updated' => 1]);

	// Summary: If headers have already been sent (rare, but can happen with BOM/whitespace in other code),
	// wp_redirect() will fail silently and the user sees a blank screen due to exit. Provide a JS fallback.
	if (headers_sent()) {
		echo '<script>window.location.href=' . wp_json_encode($redirect) . ';</script>';
		echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr($redirect) . '"></noscript>';
		exit;
	}

	wp_safe_redirect($redirect);
	exit;
}

/**
 * Get session statistics
 */
function fi_admin_sessions_get_stats(int $session_id): array {
	return fi_session_get_stats($session_id);
}

/**
 * Handle session deletion. Must run on admin_init (before any output) so wp_safe_redirect() works.
 */
function fi_admin_sessions_maybe_handle_delete(): void {
	if (!isset($_GET['page']) || $_GET['page'] !== 'fi-sessions') {
		return;
	}
	if (!isset($_GET['action']) || $_GET['action'] !== 'delete') {
		return;
	}

	$session_id = absint($_GET['session_id'] ?? 0);
	if (!$session_id) {
		return;
	}
	// Verify nonce
	if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'fi_delete_session_' . $session_id)) {
		wp_die('Security check failed.');
	}

	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die('Insufficient permissions.');
	}

	$session = fi_session_get($session_id);
	if (!$session) {
		wp_die('Session not found.');
	}

	$session_name = $session->name;
	$result = fi_session_delete($session_id);
	$redirect_url = fi_admin_url('fi-sessions', [
		'deleted' => $result ? '1' : '0',
		'session_name' => urlencode($session_name)
	]);

	wp_safe_redirect($redirect_url);
	exit;
}
add_action('admin_init', 'fi_admin_sessions_maybe_handle_delete');

/**
 * Handle adding child session legislators to parent session
 */
function fi_admin_sessions_handle_add_child_legislators(): void {
	// Verify nonce
	$session_id = absint($_POST['session_id'] ?? 0);
	
	if (!wp_verify_nonce($_POST['fi_add_child_legislators_nonce'] ?? '', 'fi_add_child_legislators_' . $session_id)) {
		wp_die('Security check failed.');
	}
	
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die('Insufficient permissions.');
	}
	
	if (!$session_id) {
		wp_die('Missing session ID.');
	}
	
	global $wpdb;
	
	// Get all child session IDs
	$child_session_ids = $wpdb->get_col($wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}fi_sessions WHERE parent_id = %d ORDER BY date_start DESC",
		$session_id
	));
	
	if (empty($child_session_ids)) {
		// No children, redirect back
		wp_safe_redirect(fi_admin_edit_session_url($session_id, ['message' => 'no_children']));
		exit;
	}
	
	// Get unique legislator IDs from all child sessions (most recent session data per legislator)
	$placeholders = implode(',', array_fill(0, count($child_session_ids), '%d'));
	$query = $wpdb->prepare("
		SELECT 
			ls.legislator_id,
			ls.gov,
			ls.state,
			ls.party,
			ls.office,
			ls.district,
			ls.image_id,
			ls.date_start,
			ls.date_end
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		WHERE ls.session_id IN ($placeholders)
		GROUP BY ls.legislator_id
		ORDER BY s.date_start DESC
	", ...$child_session_ids);
	
	$child_legislators = $wpdb->get_results($query);
	
	if (empty($child_legislators)) {
		// No legislators in children
		wp_safe_redirect(fi_admin_edit_session_url($session_id, ['message' => 'no_legislators']));
		exit;
	}
	
	// Get existing parent session assignments
	$existing_legislator_ids = $wpdb->get_col($wpdb->prepare(
		"SELECT legislator_id FROM {$wpdb->prefix}fi_legislator_sessions WHERE session_id = %d",
		$session_id
	));
	
	$added_count = 0;
	$skipped_count = 0;
	
	// Add each unique legislator to parent session
	foreach ($child_legislators as $leg) {
		// Skip if already assigned to parent
		if (in_array($leg->legislator_id, $existing_legislator_ids)) {
			$skipped_count++;
			continue;
		}
		
		// Insert new parent session assignment (copy data from child)
		$result = $wpdb->insert(
			$wpdb->prefix . 'fi_legislator_sessions',
			[
				'legislator_id' => $leg->legislator_id,
				'session_id' => $session_id,
				'gov' => $leg->gov,
				'state' => $leg->state,
				'party' => $leg->party,
				'chamber' => $leg->chamber,
				'district' => $leg->district,
				'image_id' => $leg->image_id,
				'date_start' => $leg->date_start,
				'date_end' => $leg->date_end,
				'score' => null, // Will be calculated
				'score_data' => null, // Will be calculated
				'score_date' => null
			],
			['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s']
		);
		
		if ($result) {
			$added_count++;
		}
	}
	
	// Trigger score recalculation for the parent session
	if ($added_count > 0) {
		fi_score_calculate_session($session_id);
	}
	
	// Redirect back with success message
	$message = $added_count > 0 
		? "added_{$added_count}" 
		: 'all_exist';
	
	wp_safe_redirect(fi_admin_edit_session_url($session_id, [
		'message' => $message,
		'skipped' => $skipped_count
	]));
	exit;
}

// Register admin-post hook
add_action('admin_post_fi_add_child_legislators', 'fi_admin_sessions_handle_add_child_legislators');




/**
 * Copies or updates legislator session assignments from a selected child session to a parent session.
 *
 * This handler is triggered by a POST request, for example, via an admin "Merge" button on the session edit screen.
 * 
 * 1. It validates the request and nonce, checks for valid session IDs, and loads all legislator-session assignments
 *    ("fi_legislator_sessions" rows) for both the parent and child sessions.
 * 2. For each legislator found in the child session assignment:
 *    - If the legislator already exists in the parent session, it updates key fields
 *      (gov, state, chamber, district, party) in the parent only if they differ from the child.
 *    - If the legislator does NOT exist in the parent session, it inserts a new assignment for that legislator
 *      into the parent session (by copying the row but removing the primary key and setting session_id to the parent).
 * 3. After updating/inserting, it redirects back to the parent session edit page, including counts of updated and inserted rows in the URL.
 *
 * In summary: This function ensures that the parent session inherits all the legislator assignments of a selected child session,
 * updating or adding them as needed.
 */
function fi_admin_sessions_handle_update_parent_legislators(): void {
	global $wpdb;

	// Sanity check: Confirm required POST parameters exist and are integers
	if (
		!isset($_POST['child_session_id'], $_POST['parent_session_id'], $_POST['fi_update_parent_legislators_nonce']) ||
		!is_numeric($_POST['child_session_id']) || !is_numeric($_POST['parent_session_id'])
	) {
		wp_die('Missing or invalid parameters.');
	}

	$child_session_id = absint($_POST['child_session_id']);
	$parent_session_id = absint($_POST['parent_session_id']);

	// Sanity check: Both IDs must be positive integers
	if ($child_session_id <= 0 || $parent_session_id <= 0) {
		wp_die('Invalid session IDs.');
	}

	// Verify nonce
	if (!wp_verify_nonce($_POST['fi_update_parent_legislators_nonce'], 'fi_update_parent_legislators_' . $child_session_id)) {
		wp_die('Security check failed.');
	}

	// Get all assignments for the parent session
	$parent_assignments = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}fi_legislator_sessions WHERE session_id = %d",
		$parent_session_id
	));
	$parents = [];
	if (is_array($parent_assignments)) {
		foreach ($parent_assignments as $parent) {
			$p = [
				'id' => $parent->id,
				'gov' => $parent->gov,
				'state' => $parent->state,
				'chamber' => $parent->chamber,
				'district' => $parent->district,
				'party' => $parent->party,
			];
			$parents[$parent->legislator_id] = $p;
		}
	}

	// Get all assignments for the child session
	$child_assignments = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}fi_legislator_sessions WHERE session_id = %d",
		$child_session_id
	));
	if (empty($child_assignments) || !is_array($child_assignments)) {
		wp_die('No assignments found for child session.');
	}

	$updated_count = 0;
	$inserted_count = 0;
	foreach ($child_assignments as $child) {
		if (!isset($child->legislator_id)) {
			continue; // Sanity: skip malformed record
		}
		$cLid = $child->legislator_id;
		if (isset($parents[$cLid])) {
			$u = [];
			if ($parents[$cLid]['gov'] !== $child->gov) {
				$u['gov'] = $child->gov;
			}
			if ($parents[$cLid]['state'] !== $child->state) {
				$u['state'] = $child->state;
			}
			if ($parents[$cLid]['chamber'] !== $child->chamber) {
				$u['chamber'] = $child->chamber;
			}
			if ($parents[$cLid]['district'] !== $child->district) {
				$u['district'] = $child->district;
			}
			if ($parents[$cLid]['party'] !== $child->party) {
				$u['party'] = $child->party;
			}
			// Parent Row ID
			$pId = $parents[$cLid]['id'] ?? null;

			// Update DELTA only: gov, state, chamber, district, party
			if (!empty($u) && $pId) {
				$wpdb->update(
					$wpdb->prefix . 'fi_legislator_sessions',
					$u,
					['id' => $pId]
				);
				$updated_count++;
			}
		} else {
			// New fi_legislator_sessions record
			$child_insert = clone $child;
			unset($child_insert->id); // Remove id so a new PK is created

			// Ensure assignment for parent session
			$child_insert->session_id = $parent_session_id;

			$wpdb->insert(
				$wpdb->prefix . 'fi_legislator_sessions',
				(array) $child_insert
			);
			$inserted_count++;
		}
	}

	// Redirect with update/insert count for UI feedback
	wp_safe_redirect(fi_admin_edit_session_url($parent_session_id, [
		'message' => 'updated',
		'updated' => $updated_count,
		'inserted' => $inserted_count,
	]));
	exit;
}
add_action('admin_post_fi_update_parent_legislators', 'fi_admin_sessions_handle_update_parent_legislators');