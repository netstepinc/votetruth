<?php
/**
 * Freedom Index Public Handlers: PDF Contacts
 *
 * Declassified replacement for the former FI\Public\AjaxHandlersPdfContactsTrait.
 *
 * Handles:
 * - admin-post contact save/default actions from account/personalize forms
 * - AJAX contact save/delete/get/default actions
 *
 * Recommended location:
 * /public/autoload/pdf-contacts-handlers.php
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register PDF contact admin-post and AJAX handlers.
 *
 * @return void
 */
function fi_public_pdf_contacts_handlers_init(): void {
	// Admin-post handlers.
	add_action('admin_post_fi_set_default_pdf_contact', 'fi_public_handle_set_default_pdf_contact');
	add_action('admin_post_fi_save_pdf_contact', 'fi_public_handle_save_pdf_contact');

	// AJAX handlers. Logged-in only; PDF contacts are user-owned account data.
	add_action('wp_ajax_fi_save_pdf_contact', 'fi_public_ajax_handle_save_pdf_contact');
	add_action('wp_ajax_fi_delete_pdf_contact', 'fi_public_ajax_handle_delete_pdf_contact');
	add_action('wp_ajax_fi_get_pdf_contact', 'fi_public_ajax_handle_get_pdf_contact');
	add_action('wp_ajax_fi_set_default_pdf_contact', 'fi_public_ajax_handle_set_default_pdf_contact');
}
add_action('init', 'fi_public_pdf_contacts_handlers_init');

/**
 * Get personalize page URL.
 *
 * @param array $args Optional query args.
 * @return string URL.
 */
function fi_public_pdf_contacts_personalize_url(array $args = []): string {
	$url = home_url('/account/personalize/');

	if (!empty($args)) {
		$url = add_query_arg($args, $url);
	}

	return $url;
}

/**
 * Require a logged-in user for PDF contact actions.
 *
 * @param bool $ajax Whether to send JSON response instead of redirect/die.
 * @return int Current user ID.
 */
function fi_public_pdf_contacts_require_user(bool $ajax = true): int {
	$user_id = get_current_user_id();

	if ($user_id > 0 && is_user_logged_in()) {
		return $user_id;
	}

	if ($ajax) {
		wp_send_json_error(['message' => 'You must be logged in.']);
	}

	wp_safe_redirect(home_url('/account/'));
	exit;
}

/**
 * Normalize contact data from POST or another array source.
 *
 * @param array $source Source array.
 * @param string $prefix Optional field prefix, e.g. contact_ for admin-post forms.
 * @return array Contact data.
 */
function fi_public_pdf_contacts_normalize_contact_data(array $source, string $prefix = ''): array {
	$name_key  = $prefix . 'name';
	$phone_key = $prefix . 'phone';
	$email_key = $prefix . 'email';

	$name = sanitize_text_field(wp_unslash($source[$name_key] ?? ''));
	$phone = sanitize_text_field(wp_unslash($source[$phone_key] ?? ''));
	$email = sanitize_email(wp_unslash($source[$email_key] ?? ''));

	return [
		'name'  => $name,
		'phone' => $phone,
		'email' => $email,
	];
}

/**
 * Normalize nullable contact index from request value.
 *
 * @param mixed $raw Raw request value.
 * @return int|null Contact index or null.
 */
function fi_public_pdf_contacts_normalize_index($raw): ?int {
	if ($raw === null) {
		return null;
	}

	if (is_string($raw)) {
		$raw = sanitize_text_field(wp_unslash($raw));
	}

	if ($raw === '') {
		return null;
	}

	return absint($raw);
}

/**
 * Send current contacts/default index as AJAX success.
 *
 * @param int $user_id User ID.
 * @param string $message Message.
 * @param array $extra Extra response fields.
 * @return void
 */
function fi_public_pdf_contacts_send_success(int $user_id, string $message, array $extra = []): void {
	$contacts = function_exists('fi_pdf_contacts_get') ? fi_pdf_contacts_get($user_id) : [];
	$default_index = function_exists('fi_pdf_contacts_default_index_get') ? fi_pdf_contacts_default_index_get($user_id) : null;

	wp_send_json_success(array_merge([
		'message'       => $message,
		'contacts'      => is_array($contacts) ? $contacts : [],
		'default_index' => $default_index,
	], $extra));
}

/**
 * Set default PDF contact via admin-post.
 *
 * @return void
 */
function fi_public_handle_set_default_pdf_contact(): void {
	$user_id = fi_public_pdf_contacts_require_user(false);

	check_admin_referer('fi_set_default_pdf_contact', 'fi_default_contact_nonce');

	$index = fi_public_pdf_contacts_normalize_index($_POST['default_index'] ?? null);
	$result = function_exists('fi_pdf_contacts_default_index_set')
		? fi_pdf_contacts_default_index_set($user_id, $index)
		: false;

	wp_safe_redirect(fi_public_pdf_contacts_personalize_url(
		$result ? ['default_updated' => '1'] : ['error' => 'default_failed']
	));
	exit;
}

/**
 * Save PDF contact via admin-post.
 *
 * @return void
 */
function fi_public_handle_save_pdf_contact(): void {
	$user_id = fi_public_pdf_contacts_require_user(false);

	check_admin_referer('fi_save_pdf_contact', 'fi_pdf_contact_nonce');

	$contact_data = fi_public_pdf_contacts_normalize_contact_data($_POST, 'contact_');
	$edit_index = fi_public_pdf_contacts_normalize_index($_POST['edit_index'] ?? null);

	$result = function_exists('fi_pdf_contacts_save')
		? fi_pdf_contacts_save($user_id, $contact_data, $edit_index)
		: false;

	wp_safe_redirect(fi_public_pdf_contacts_personalize_url(
		$result ? ['updated' => '1'] : ['error' => 'save_failed']
	));
	exit;
}

/**
 * Save PDF contact via AJAX.
 *
 * @return void
 */
function fi_public_ajax_handle_save_pdf_contact(): void {
	$user_id = fi_public_pdf_contacts_require_user(true);

	check_ajax_referer('fi_save_pdf_contact', 'nonce');

	$contact_data = fi_public_pdf_contacts_normalize_contact_data($_POST);
	$edit_index = fi_public_pdf_contacts_normalize_index($_POST['edit_index'] ?? null);

	$result = function_exists('fi_pdf_contacts_save')
		? fi_pdf_contacts_save($user_id, $contact_data, $edit_index)
		: false;

	if ($result) {
		fi_public_pdf_contacts_send_success($user_id, 'Contact saved successfully.');
	}

	wp_send_json_error(['message' => 'Failed to save contact.']);
}

/**
 * Delete PDF contact via AJAX.
 *
 * @return void
 */
function fi_public_ajax_handle_delete_pdf_contact(): void {
	$user_id = fi_public_pdf_contacts_require_user(true);

	check_ajax_referer('fi_delete_pdf_contact', 'nonce');

	$index = fi_public_pdf_contacts_normalize_index($_POST['index'] ?? null);
	if ($index === null) {
		wp_send_json_error(['message' => 'Invalid contact index.']);
	}

	$result = function_exists('fi_pdf_contacts_delete')
		? fi_pdf_contacts_delete($user_id, $index)
		: false;

	if ($result) {
		fi_public_pdf_contacts_send_success($user_id, 'Contact deleted successfully.');
	}

	wp_send_json_error(['message' => 'Failed to delete contact.']);
}

/**
 * Get one PDF contact by index via AJAX.
 *
 * @return void
 */
function fi_public_ajax_handle_get_pdf_contact(): void {
	$user_id = fi_public_pdf_contacts_require_user(true);

	check_ajax_referer('fi_get_pdf_contact', 'nonce');

	$index = fi_public_pdf_contacts_normalize_index($_POST['index'] ?? null);
	if ($index === null) {
		wp_send_json_error(['message' => 'Invalid contact index.']);
	}

	$contact = function_exists('fi_pdf_contacts_get_by_index')
		? fi_pdf_contacts_get_by_index($user_id, $index)
		: null;

	if ($contact === null) {
		wp_send_json_error(['message' => 'Contact not found.']);
	}

	wp_send_json_success([
		'contact' => $contact,
		'index'   => $index,
	]);
}

/**
 * Set default PDF contact via AJAX.
 *
 * @return void
 */
function fi_public_ajax_handle_set_default_pdf_contact(): void {
	$user_id = fi_public_pdf_contacts_require_user(true);

	check_ajax_referer('fi_set_default_pdf_contact', 'nonce');

	$index = fi_public_pdf_contacts_normalize_index($_POST['default_index'] ?? null);
	$result = function_exists('fi_pdf_contacts_default_index_set')
		? fi_pdf_contacts_default_index_set($user_id, $index)
		: false;

	if ($result) {
		fi_public_pdf_contacts_send_success($user_id, 'Default updated.');
	}

	wp_send_json_error(['message' => 'Failed to set default.']);
}