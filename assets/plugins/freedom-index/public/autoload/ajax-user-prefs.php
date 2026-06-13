<?php
/**
 * Freedom Index Public AJAX: User Preferences
 *
 * Declassified replacement for the former FI\Public\AjaxHandlersPrefsTrait.
 *
 * Recommended location:
 * /public/autoload/ajax-user-prefs.php
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register public AJAX handlers for user preferences.
 *
 * @return void
 */
function fi_public_ajax_user_prefs_init(): void {
	add_action('wp_ajax_fi_save_prefs', 'fi_public_ajax_handle_save_prefs');
	add_action('wp_ajax_fi_sync_prefs', 'fi_public_ajax_handle_sync_prefs');
}
add_action('init', 'fi_public_ajax_user_prefs_init');

/**
 * Require a logged-in user for preference AJAX actions.
 *
 * @return int Current user ID.
 */
function fi_public_ajax_user_prefs_require_user(): int {
	$user_id = get_current_user_id();

	if ($user_id <= 0 || !is_user_logged_in()) {
		wp_send_json_error(['message' => 'Must be logged in']);
	}

	return $user_id;
}

/**
 * Normalize user preference data.
 *
 * @param array $data Raw preference data.
 * @return array Normalized preferences.
 */
function fi_public_ajax_user_prefs_normalize(array $data): array {
	return [
		'name'  => sanitize_text_field(wp_unslash($data['name'] ?? '')),
		'phone' => sanitize_text_field(wp_unslash($data['phone'] ?? '')),
		'email' => sanitize_email(wp_unslash($data['email'] ?? '')),
		'zip'   => sanitize_text_field(wp_unslash($data['zip'] ?? '')),
	];
}

/**
 * Save user preferences through the canonical helper.
 *
 * @param int $user_id User ID.
 * @param array $prefs Preferences.
 * @return bool Success.
 */
function fi_public_ajax_user_prefs_save(int $user_id, array $prefs): bool {
	$user_id = absint($user_id);

	if ($user_id <= 0) {
		return false;
	}

	if (function_exists('fi_user_prefs_save')) {
		return fi_user_prefs_save($user_id, $prefs) !== false;
	}

	return (bool) update_user_meta($user_id, 'fi_user_prefs', $prefs);
}

/**
 * Handle saving explicit user preferences.
 *
 * Expected POST:
 * - nonce
 * - name
 * - phone
 * - email
 * - zip
 *
 * @return void
 */
function fi_public_ajax_handle_save_prefs(): void {
	$user_id = fi_public_ajax_user_prefs_require_user();
	check_ajax_referer('fi_ajax_nonce', 'nonce');

	$prefs = fi_public_ajax_user_prefs_normalize($_POST);
	$result = fi_public_ajax_user_prefs_save($user_id, $prefs);

	if ($result) {
		wp_send_json_success([
			'prefs' => $prefs,
		]);
	}

	wp_send_json_error(['message' => 'Failed to save preferences']);
}

/**
 * Handle syncing localStorage preferences to user meta.
 *
 * Expected POST:
 * - nonce
 * - prefs JSON string or array
 *
 * @return void
 */
function fi_public_ajax_handle_sync_prefs(): void {
	$user_id = fi_public_ajax_user_prefs_require_user();
	check_ajax_referer('fi_ajax_nonce', 'nonce');

	$raw = $_POST['prefs'] ?? '';

	if (is_string($raw)) {
		$decoded = json_decode(wp_unslash($raw), true);
		$prefs = is_array($decoded) ? $decoded : [];
	} elseif (is_array($raw)) {
		$prefs = wp_unslash($raw);
	} else {
		$prefs = [];
	}

	if (empty($prefs)) {
		wp_send_json_success([
			'prefs' => [],
		]);
	}

	$prefs = fi_public_ajax_user_prefs_normalize($prefs);
	$result = fi_public_ajax_user_prefs_save($user_id, $prefs);

	if ($result) {
		wp_send_json_success([
			'prefs' => $prefs,
		]);
	}

	wp_send_json_error(['message' => 'Failed to sync preferences']);
}
