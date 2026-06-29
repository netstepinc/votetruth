<?php
/**
 * Freedom Index Public AJAX: User Lists
 *
 * Declassified replacement for the former FI\Public\AjaxHandlersListsTrait.
 *
 * Recommended location:
 * /public/autoload/ajax-lists.php
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!defined('FI_USER_LIST_MAX_LEGISLATORS')) {
	define('FI_USER_LIST_MAX_LEGISLATORS', 20);
}

/**
 * Register public AJAX handlers for user lists.
 *
 * @return void
 */
function fi_public_ajax_lists_init(): void {
	$handlers = [
		'fi_create_list'              => 'fi_public_ajax_handle_create_list',
		'fi_modal_create_list'        => 'fi_public_ajax_handle_modal_create_list',
		'fi_save_list'                => 'fi_public_ajax_handle_save_list',
		'fi_update_list'              => 'fi_public_ajax_handle_update_list',
		'fi_update_list_name'         => 'fi_public_ajax_handle_update_list_name',
		'fi_update_list_contact'      => 'fi_public_ajax_handle_update_list_contact',
		'fi_delete_list'              => 'fi_public_ajax_handle_delete_list',
	];

	foreach ($handlers as $action => $handler) {
		add_action('wp_ajax_' . $action, $handler);
	}
}
add_action('init', 'fi_public_ajax_lists_init');

/**
 * Public AJAX scoped logger for lists.
 *
 * @param string $message Log message.
 * @param array $context Context data.
 * @param string $level Log level.
 * @param string $file File path.
 * @param int $line Line number.
 * @return void
 */
function fi_public_ajax_lists_log(string $message, array $context = [], string $level = 'debug', string $file = '', int $line = 0): void {
	if (function_exists('fi_ajax_log')) {
		fi_ajax_log($message, $context, $level, $file, $line);
		return;
	}

	if (function_exists('fi_log_area')) {
		fi_log_area('public_ajax_lists', $message . (!empty($context) ? ' | ' . wp_json_encode($context) : ''), $file, $line, $level);
	}
}

/**
 * Require logged-in user for list actions.
 *
 * @return int Current user ID.
 */
function fi_public_ajax_lists_require_user(): int {
	$user_id = get_current_user_id();

	if ($user_id <= 0 || !is_user_logged_in()) {
		wp_send_json_error(['message' => 'Must be logged in']);
	}

	return $user_id;
}

/**
 * Normalize list name.
 *
 * @param mixed $name Raw name.
 * @return string Sanitized name.
 */
function fi_public_ajax_lists_normalize_name($name): string {
	return trim(sanitize_text_field(wp_unslash((string) $name)));
}

/**
 * Normalize legislator IDs from mixed POST formats.
 *
 * Supports:
 * - array of IDs
 * - JSON string: [1,2,3]
 * - comma-separated string: 1,2,3
 *
 * @param mixed $raw Raw value.
 * @return array Legislator IDs.
 */
function fi_public_ajax_lists_normalize_legislator_ids($raw): array {
	if (is_string($raw)) {
		$raw = wp_unslash($raw);
		$trimmed = trim($raw);

		if ($trimmed === '') {
			return [];
		}

		if ($trimmed[0] === '[' || $trimmed[0] === '{') {
			$decoded = json_decode($trimmed, true);
			$raw = is_array($decoded) ? $decoded : [];
		} else {
			$raw = explode(',', $trimmed);
		}
	}

	if (!is_array($raw)) {
		return [];
	}

	$ids = array_map('absint', $raw);
	$ids = array_values(array_unique(array_filter($ids)));

	return $ids;
}

/**
 * Validate list legislator count.
 *
 * @param array $legislator_ids Legislator IDs.
 * @return void
 */
function fi_public_ajax_lists_validate_legislator_limit(array $legislator_ids): void {
	if (count($legislator_ids) > FI_USER_LIST_MAX_LEGISLATORS) {
		wp_send_json_error(['message' => 'Maximum ' . FI_USER_LIST_MAX_LEGISLATORS . ' legislators per list']);
	}
}

/**
 * Get a user-owned list.
 *
 * @param int $list_id List ID.
 * @param int $user_id User ID.
 * @return array|null List row (ARRAY_A) or null if not found or not owned.
 */
function fi_public_ajax_lists_get_owned_list(int $list_id, int $user_id): ?array {
	$list_id = absint($list_id);
	$user_id = absint($user_id);

	if ($list_id <= 0 || $user_id <= 0) {
		return null;
	}

	$list = fi_list_get_by_id($list_id);
	if (is_array($list) && (int) ($list['user_id'] ?? 0) === $user_id) {
		return $list;
	}
	return null;
}

/**
 * Create list through canonical helper.
 *
 * @param string $name List name.
 * @param array $legislator_ids Legislator IDs.
 * @return array Result payload.
 */
function fi_public_ajax_lists_create(string $name, array $legislator_ids = []): array {
	$name = fi_public_ajax_lists_normalize_name($name);
	$legislator_ids = fi_public_ajax_lists_normalize_legislator_ids($legislator_ids);

	if ($name === '') {
		return ['message' => 'List name required'];
	}

	if (count($legislator_ids) > FI_USER_LIST_MAX_LEGISLATORS) {
		return ['message' => 'Maximum ' . FI_USER_LIST_MAX_LEGISLATORS . ' legislators per list'];
	}

	if (function_exists('fi_list_create')) {
		$result = fi_list_create($name, $legislator_ids);
		return is_array($result) ? $result : ['message' => 'Failed to create list'];
	}

	$list_id = fi_list_save([
		'user_id'     => get_current_user_id(),
		'name'        => $name,
		'legislators' => $legislator_ids,
	], null);

	return $list_id ? ['list_id' => $list_id, 'name' => $name, 'legislators' => $legislator_ids] : ['message' => 'Failed to create list'];
}

/**
 * Handle create list from account lists page.
 *
 * Nonce: fi_list_manage.
 *
 * @return void
 */
function fi_public_ajax_handle_create_list(): void {
	fi_public_ajax_lists_require_user();
	check_ajax_referer('fi_list_manage', 'nonce');

	$name = fi_public_ajax_lists_normalize_name($_POST['name'] ?? '');
	$legislator_ids = fi_public_ajax_lists_normalize_legislator_ids($_POST['legislator_ids'] ?? $_POST['legislators'] ?? []);
	fi_public_ajax_lists_validate_legislator_limit($legislator_ids);

	$result = fi_public_ajax_lists_create($name, $legislator_ids);

	if (isset($result['list_id'])) {
		wp_send_json_success($result);
	}

	wp_send_json_error($result);
}

/**
 * Handle create list from legislator modal.
 *
 * Nonce: fi_list_nonce.
 *
 * @return void
 */
function fi_public_ajax_handle_modal_create_list(): void {
	fi_public_ajax_lists_require_user();
	check_ajax_referer('fi_list_nonce', 'nonce');

	$name = fi_public_ajax_lists_normalize_name($_POST['name'] ?? '');
	$legislator_ids = fi_public_ajax_lists_normalize_legislator_ids($_POST['legislators'] ?? []);
	fi_public_ajax_lists_validate_legislator_limit($legislator_ids);

	$result = fi_public_ajax_lists_create($name, $legislator_ids);

	if (isset($result['list_id'])) {
		wp_send_json_success($result);
	}

	wp_send_json_error($result);
}

/**
 * Handle saving list from modal/inline UI.
 *
 * Nonce: fi_list_nonce.
 *
 * @return void
 */
function fi_public_ajax_handle_save_list(): void {
	fi_public_ajax_lists_require_user();
	check_ajax_referer('fi_list_nonce', 'nonce');

	$name = fi_public_ajax_lists_normalize_name($_POST['name'] ?? '');
	$legislator_ids = fi_public_ajax_lists_normalize_legislator_ids($_POST['legislators'] ?? $_POST['legislator_ids'] ?? []);
	fi_public_ajax_lists_validate_legislator_limit($legislator_ids);

	$result = fi_public_ajax_lists_create($name, $legislator_ids);

	fi_public_ajax_lists_log('save_list', [
		'name' => $name,
		'legislators' => $legislator_ids,
		'result' => $result,
	], 'debug', __FILE__, __LINE__);

	if (isset($result['list_id'])) {
		wp_send_json_success($result);
	}

	wp_send_json_error($result);
}

/**
 * Handle updating list membership.
 *
 * Nonce: fi_list_nonce.
 *
 * @return void
 */
function fi_public_ajax_handle_update_list(): void {
	$user_id = fi_public_ajax_lists_require_user();
	check_ajax_referer('fi_list_nonce', 'nonce');

	$list_id = absint($_POST['list_id'] ?? 0);
	$legislator_id = absint($_POST['legislator_id'] ?? 0);
	$add = isset($_POST['add']) && (string) $_POST['add'] === '1';

	if ($list_id <= 0 || $legislator_id <= 0) {
		wp_send_json_error(['message' => 'Invalid list ID or legislator ID']);
	}

	$list = fi_public_ajax_lists_get_owned_list($list_id, $user_id);
	if (!$list) {
		wp_send_json_error(['message' => 'List not found']);
	}

	$legislator_ids = fi_public_ajax_lists_normalize_legislator_ids($list['legislators'] ?? []);

	if ($add) {
		if (!in_array($legislator_id, $legislator_ids, true)) {
			if (count($legislator_ids) >= FI_USER_LIST_MAX_LEGISLATORS) {
				wp_send_json_error(['message' => 'Maximum ' . FI_USER_LIST_MAX_LEGISLATORS . ' legislators per list']);
			}
			$legislator_ids[] = $legislator_id;
		}
	} else {
		$legislator_ids = array_values(array_filter($legislator_ids, static function($id) use ($legislator_id) {
			return (int) $id !== $legislator_id;
		}));
	}

	$result = fi_list_save([
		'user_id'     => $user_id,
		'name'        => (string) ($list['name'] ?? ''),
		'legislators' => $legislator_ids,
		'meta'        => fi_meta_decode($list['meta'] ?? null),
	], $list_id);

	fi_public_ajax_lists_log('update_list', [
		'list_id' => $list_id,
		'legislator_id' => $legislator_id,
		'add' => $add,
		'result' => $result,
	], 'debug', __FILE__, __LINE__);

	if ($result !== false) {
		wp_send_json_success(['count' => count($legislator_ids)]);
	}

	wp_send_json_error(['message' => 'Failed to update list']);
}

/**
 * Handle update list name.
 *
 * Nonce: fi_list_manage.
 *
 * @return void
 */
function fi_public_ajax_handle_update_list_name(): void {
	$user_id = fi_public_ajax_lists_require_user();
	check_ajax_referer('fi_list_manage', 'nonce');

	$list_id = absint($_POST['list_id'] ?? 0);
	$name = fi_public_ajax_lists_normalize_name($_POST['name'] ?? '');

	if ($list_id <= 0 || $name === '') {
		wp_send_json_error(['message' => 'Invalid list ID or name']);
	}

	$list = fi_public_ajax_lists_get_owned_list($list_id, $user_id);
	if (!$list) {
		wp_send_json_error(['message' => 'List not found']);
	}

	$result = fi_list_save([
		'user_id'     => $user_id,
		'name'        => $name,
		'legislators' => fi_public_ajax_lists_normalize_legislator_ids($list['legislators'] ?? []),
		'meta'        => fi_meta_decode($list['meta'] ?? null),
	], $list_id);

	fi_public_ajax_lists_log('update_list_name', [
		'user_id' => $user_id,
		'list_id' => $list_id,
		'name' => $name,
		'result' => $result,
	], 'debug', __FILE__, __LINE__);

	if ($result !== false) {
		wp_send_json_success(['message' => 'List name updated successfully']);
	}

	wp_send_json_error(['message' => 'Failed to update list name']);
}

/**
 * Handle update list contact selection.
 *
 * Nonce: fi_list_manage.
 *
 * @return void
 */
function fi_public_ajax_handle_update_list_contact(): void {
	$user_id = fi_public_ajax_lists_require_user();
	check_ajax_referer('fi_list_manage', 'nonce');

	$list_id = absint($_POST['list_id'] ?? 0);
	$contact_index_raw = isset($_POST['contact_index']) ? wp_unslash($_POST['contact_index']) : '';
	$contact_index = ($contact_index_raw !== '' && $contact_index_raw !== null) ? absint($contact_index_raw) : null;

	if ($list_id <= 0) {
		wp_send_json_error(['message' => 'Invalid list ID']);
	}

	$list = fi_public_ajax_lists_get_owned_list($list_id, $user_id);
	if (!$list) {
		wp_send_json_error(['message' => 'You do not have permission to update this list']);
	}

	if ($contact_index !== null) {
		$pdf_contacts = function_exists('fi_pdf_contacts_get') ? fi_pdf_contacts_get($user_id) : [];
		if (!is_array($pdf_contacts) || !isset($pdf_contacts[$contact_index])) {
			wp_send_json_error(['message' => 'Invalid contact selection']);
		}
	}

	$meta = fi_meta_decode($list['meta'] ?? null);

	if ($contact_index !== null) {
		$meta['contact_index'] = $contact_index;
	} else {
		unset($meta['contact_index']);
	}

	$result = fi_list_save([
		'user_id'     => $user_id,
		'name'        => (string) ($list['name'] ?? ''),
		'legislators' => fi_public_ajax_lists_normalize_legislator_ids($list['legislators'] ?? []),
		'meta'        => $meta,
	], $list_id);

	fi_public_ajax_lists_log('update_list_contact', [
		'list_id' => $list_id,
		'contact_index' => $contact_index,
		'result' => $result,
	], 'debug', __FILE__, __LINE__);

	if ($result !== false) {
		wp_send_json_success(['message' => 'Contact selection updated successfully']);
	}

	wp_send_json_error(['message' => 'Failed to update contact selection']);
}

/**
 * Handle delete list.
 *
 * Nonce: fi_delete_list.
 *
 * @return void
 */
function fi_public_ajax_handle_delete_list(): void {
	$user_id = fi_public_ajax_lists_require_user();
	check_ajax_referer('fi_delete_list', 'nonce');

	$list_id = absint($_POST['list_id'] ?? 0);
	if ($list_id <= 0) {
		wp_send_json_error(['message' => 'Invalid list ID']);
	}

	$list = fi_public_ajax_lists_get_owned_list($list_id, $user_id);
	if (!$list) {
		wp_send_json_error(['message' => 'List not found']);
	}

	$result = fi_list_delete($list_id);

	if ($result) {
		wp_send_json_success();
	}

	wp_send_json_error(['message' => 'Failed to delete list']);
}
