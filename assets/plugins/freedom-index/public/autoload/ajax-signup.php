<?php
/**
 * Freedom Index Public AJAX: User Signup Handler
 *
 * Handles user registration via admin-post action.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register user signup handler.
 *
 * @return void
 */
function fi_public_ajax_signup_init(): void {
	add_action('admin_post_fi_user_signup', 'fi_public_ajax_handle_user_signup');
	add_action('admin_post_nopriv_fi_user_signup', 'fi_public_ajax_handle_user_signup');
}
add_action('init', 'fi_public_ajax_signup_init');

/**
 * Handle user signup via admin-post.
 *
 * Expected POST:
 * - fi_signup_nonce
 * - username
 * - email
 * - password
 * - password_confirm
 * - redirect_to optional
 *
 * @return void
 */
function fi_public_ajax_handle_user_signup(): void {
	if (!get_option('users_can_register')) {
		wp_die('Registration is disabled.');
	}

	check_admin_referer('fi_signup', 'fi_signup_nonce');

	$username = sanitize_user(wp_unslash($_POST['username'] ?? ''));
	$email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
	$password = (string) ($_POST['password'] ?? '');
	$password_confirm = (string) ($_POST['password_confirm'] ?? '');
	$redirect_to = esc_url_raw(wp_unslash($_POST['redirect_to'] ?? home_url('/account/dashboard/')));
	$error_url = home_url('/account/');

	if ($username === '' || $email === '' || $password === '') {
		wp_safe_redirect(add_query_arg('error', 'missing_fields', $error_url));
		exit;
	}

	if ($password !== $password_confirm) {
		wp_safe_redirect(add_query_arg('error', 'password_mismatch', $error_url));
		exit;
	}

	if (strlen($password) < 8) {
		wp_safe_redirect(add_query_arg('error', 'password_short', $error_url));
		exit;
	}

	if (!is_email($email)) {
		wp_safe_redirect(add_query_arg('error', 'invalid_email', $error_url));
		exit;
	}

	if (username_exists($username)) {
		wp_safe_redirect(add_query_arg('error', 'username_exists', $error_url));
		exit;
	}

	if (email_exists($email)) {
		wp_safe_redirect(add_query_arg('error', 'email_exists', $error_url));
		exit;
	}

	$user_id = wp_create_user($username, $password, $email);

	if (is_wp_error($user_id)) {
		wp_safe_redirect(add_query_arg('error', 'registration_failed', $error_url));
		exit;
	}

	wp_set_current_user((int) $user_id);
	wp_set_auth_cookie((int) $user_id);

	wp_safe_redirect($redirect_to ?: home_url('/account/dashboard/'));
	exit;
}
