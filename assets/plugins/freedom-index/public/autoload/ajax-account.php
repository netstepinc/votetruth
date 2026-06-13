<?php
/**
 * Freedom Index Public AJAX: Account Profile + Address
 *
 * Declassified replacement for the former FI\Public\AjaxHandlersAccountTrait.
 *
 * Recommended location:
 * /public/autoload/ajax-account.php
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register public AJAX handlers for account profile/address updates.
 *
 * @return void
 */
function fi_public_ajax_account_init(): void {
	add_action('wp_ajax_fi_update_profile', 'fi_public_ajax_handle_update_profile');
	add_action('wp_ajax_fi_update_address', 'fi_public_ajax_handle_update_address');
}
add_action('init', 'fi_public_ajax_account_init');

/**
 * Require a logged-in user for account AJAX actions.
 *
 * @return int Current user ID.
 */
function fi_public_ajax_account_require_user(): int {
	$user_id = get_current_user_id();

	if ($user_id <= 0 || !is_user_logged_in()) {
		wp_send_json_error([
			'message' => 'You must be logged in.',
		]);
	}

	return $user_id;
}

/**
 * AJAX scoped logger for account updates.
 *
 * @param string $message Log message.
 * @param array $context Context data.
 * @param string $level Log level.
 * @param string $file File path.
 * @param int $line Line number.
 * @return void
 */
function fi_public_ajax_account_log(string $message, array $context = [], string $level = 'debug', string $file = '', int $line = 0): void {
	if (function_exists('fi_ajax_log')) {
		fi_ajax_log($message, $context, $level, $file, $line);
		return;
	}

	if (function_exists('fi_log_area')) {
		fi_log_area('public_ajax_account', $message . (!empty($context) ? ' | ' . wp_json_encode($context) : ''), $file, $line, $level);
	}
}

/**
 * Normalize profile update data from POST.
 *
 * @param array $source Raw request source.
 * @return array Profile update data.
 */
function fi_public_ajax_account_normalize_profile_data(array $source): array {
	$update_data = [];

	if (isset($source['user_email'])) {
		$update_data['user_email'] = sanitize_email(wp_unslash($source['user_email']));
	}

	if (isset($source['display_name'])) {
		$update_data['display_name'] = sanitize_text_field(wp_unslash($source['display_name']));
	}

	if (!empty($source['user_pass'])) {
		// Do not sanitize passwords with sanitize_text_field(); preserve intended characters.
		$update_data['user_pass'] = (string) wp_unslash($source['user_pass']);
		$update_data['user_pass_confirm'] = isset($source['user_pass_confirm'])
			? (string) wp_unslash($source['user_pass_confirm'])
			: '';
	}

	return $update_data;
}

/**
 * Normalize address update data from POST.
 *
 * Accepts either FI-prefixed fields or Woo-style shipping_* fields.
 * Empty optional values are preserved so saved blank fields overwrite prior values.
 *
 * @param array $source Raw request source.
 * @return array Address data.
 */
function fi_public_ajax_account_normalize_address_data(array $source): array {
	$field_map = [
		'first_name' => ['fi_first_name', 'shipping_first_name'],
		'last_name'  => ['fi_last_name', 'shipping_last_name'],
		'address_1'  => ['fi_address_1', 'shipping_address_1'],
		'address_2'  => ['fi_address_2', 'shipping_address_2'],
		'city'       => ['fi_city', 'shipping_city'],
		'state'      => ['fi_state', 'shipping_state'],
		'postcode'   => ['fi_postcode', 'shipping_postcode'],
		'country'    => ['fi_country', 'shipping_country'],
	];

	$address_data = [];

	foreach ($field_map as $canonical_key => $source_keys) {
		$value = '';

		foreach ($source_keys as $source_key) {
			if (array_key_exists($source_key, $source)) {
				$value = $source[$source_key];
				break;
			}
		}

		$address_data[$canonical_key] = sanitize_text_field(wp_unslash($value));
	}

	$address_data['state'] = strtoupper($address_data['state']);
	$address_data['country'] = strtoupper($address_data['country']);

	return $address_data;
}

/**
 * Handle profile update via AJAX.
 *
 * Expected POST:
 * - nonce: fi_update_profile
 * - user_email optional
 * - display_name optional
 * - user_pass optional
 * - user_pass_confirm optional
 *
 * @return void
 */
function fi_public_ajax_handle_update_profile(): void {
	$user_id = fi_public_ajax_account_require_user();
	check_ajax_referer('fi_update_profile', 'nonce');

	$update_data = fi_public_ajax_account_normalize_profile_data($_POST);

	if (empty($update_data)) {
		wp_send_json_error([
			'message' => 'No profile changes submitted.',
		]);
	}

	if (!function_exists('fi_user_profile_update')) {
		wp_send_json_error([
			'message' => 'Profile update is unavailable.',
		]);
	}

	$result = fi_user_profile_update($user_id, $update_data);

	if (is_wp_error($result)) {
		fi_public_ajax_account_log('Profile update failed', [
			'user_id' => $user_id,
			'code'    => $result->get_error_code(),
			'message' => $result->get_error_message(),
		], 'error', __FILE__, __LINE__);

		wp_send_json_error([
			'message' => $result->get_error_message(),
			'code'    => $result->get_error_code(),
		]);
	}

	fi_public_ajax_account_log('Profile updated', [
		'user_id' => $user_id,
		'fields'  => array_keys($update_data),
	], 'debug', __FILE__, __LINE__);

	wp_send_json_success([
		'message' => 'Profile updated.',
	]);
}

/**
 * Handle address update via AJAX.
 *
 * Expected POST:
 * - nonce: fi_update_address
 * - FI-prefixed address fields or shipping_* address fields
 *
 * @return void
 */
function fi_public_ajax_handle_update_address(): void {
	$user_id = fi_public_ajax_account_require_user();
	check_ajax_referer('fi_update_address', 'nonce');

	$address_data = fi_public_ajax_account_normalize_address_data($_POST);

	if (!function_exists('fi_user_meta_save')) {
		wp_send_json_error([
			'message' => 'Address update is unavailable.',
		]);
	}

	// Do not array_filter: address_2 and other optional fields may be intentionally blank.
	$result = fi_user_meta_save($user_id, $address_data);

	if (!$result) {
		fi_public_ajax_account_log('Address update failed', [
			'user_id' => $user_id,
		], 'error', __FILE__, __LINE__);

		wp_send_json_error([
			'message' => 'Failed to save address.',
		]);
	}

	fi_public_ajax_account_log('Address updated', [
		'user_id' => $user_id,
	], 'debug', __FILE__, __LINE__);

	wp_send_json_success([
		'message' => 'Address saved.',
	]);
}
