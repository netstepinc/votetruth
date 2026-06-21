<?php if (!defined('ABSPATH')) { exit; }

/**
 * Admin session filter (gov-scoped cookie)
 *
 * Summary:
 * - `session_id` is a per-page list filter, but we persist the last-used session per gov so staff can resume work.
 * - Cookie is set on `admin_init` (before admin header output) so headers are still mutable.
 */

/**
 * Cookie name for a gov-scoped admin session selection.
 */
function fi_admin_session_cookie_name(string $gov): string {
	$gov = strtoupper(trim($gov));
	if ($gov === '') {
		return '';
	}
	return 'fi_admin_' . $gov . '_session';
}

/**
 * Set the gov-scoped admin session cookie.
 *
 * Summary: path is `/` so it applies across all admin list pages and survives navigation.
 */
function fi_admin_session_cookie_set(string $gov, int $session_id): void {
	$cookie_name = fi_admin_session_cookie_name($gov);
	if ($cookie_name === '') {
		return;
	}
	if (headers_sent()) {
		return;
	}

	$val = (string) max(0, (int) $session_id);
	setcookie($cookie_name, $val, [
		'expires'  => time() + (60 * 60 * 24 * 30),
		'path'     => '/',
		'domain'   => '',
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	]);

	// Also update current request state.
	$_COOKIE[$cookie_name] = $val;
}

/**
 * Early hook: persist session_id filter in a gov-scoped cookie.
 */
function fi_admin_session_cookie_handle(): void {
	if (!is_admin()) {
		return;
	}
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}

	$page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
	if ($page === '' || strpos($page, 'fi-') !== 0) {
		return;
	}

	// Only set cookie when session_id is explicitly provided in the URL (including 0 to clear).
	if (!array_key_exists('session_id', $_GET)) {
		return;
	}

	$scope = function_exists('fi_scope_get_current') ? fi_scope_get_current() : [];
	$gov = strtoupper((string) ($scope['gov'] ?? ''));
	if ($gov === '') {
		return;
	}

	fi_admin_session_cookie_set($gov, absint($_GET['session_id']));
}
add_action('admin_init', 'fi_admin_session_cookie_handle', 1);

/**
 * Resolve the effective session filter for the current page.
 *
 * Summary:
 * - GET wins (even if 0).
 * - Else cookie (even if 0).
 * - Else default current/latest for gov.
 * - Optionally validates against a provided session_id lookup map.
 *
 * @param string $gov
 * @param array<int,bool>|null $valid_session_ids
 * @return array{session_id:int, source:string}
 */
function fi_admin_session_filter_resolve(string $gov, ?array $valid_session_ids = null): array {
	$gov = strtoupper((string) $gov);
	$cookie_name = fi_admin_session_cookie_name($gov);

	$session_id = 0;
	$source = '';

	if (array_key_exists('session_id', $_GET)) {
		$session_id = absint($_GET['session_id']);
		$source = 'get';
	} elseif ($cookie_name !== '' && isset($_COOKIE[$cookie_name]) && is_string($_COOKIE[$cookie_name])) {
		$cookie_val = trim((string) $_COOKIE[$cookie_name]);
		if ($cookie_val !== '' && ctype_digit($cookie_val)) {
			$session_id = (int) $cookie_val;
			$source = 'cookie';
		}
	}

	// Validate session belongs to this gov if we have a lookup map.
	if ($session_id > 0 && is_array($valid_session_ids) && empty($valid_session_ids[$session_id])) {
		$session_id = 0;
		$source = '';
	}

	// Default only when no explicit selection exists.
	if ($source === '' && $session_id <= 0 && $gov !== '') {
		$session_id = (int) (fi_session_get_current_id($gov) ?? 0);
	}

	return [
		'session_id' => (int) $session_id,
		'source' => (string) $source,
	];
}

