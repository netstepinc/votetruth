<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Lightweight request tracer for diagnosing admin timeouts.
 *
 * Usage:
 * - Append `&fi_trace=1` to any wp-admin URL (especially `admin.php?page=fi-*`)
 * - Then check the PHP error log for `[FI_TRACE] ...` lines.
 *
 * Summary:
 * - Uses error_log() (not FI file logger) to avoid filesystem lock contention.
 * - Logs early in admin_init and at shutdown to show the last breadcrumb before a timeout/fatal.
 */

if (!function_exists('fi_trace_enabled')) {
	function fi_trace_enabled(): bool {
		// Intentionally simple: opt-in per-request only.
		return is_admin() && isset($_GET['fi_trace']) && (string) $_GET['fi_trace'] === '1';
	}
}

if (!function_exists('fi_trace')) {
	function fi_trace(string $message, array $context = []): void {
		if (!fi_trace_enabled()) {
			return;
		}
		static $t0 = null;
		if ($t0 === null) {
			$t0 = microtime(true);
		}
		$elapsed_ms = (int) round((microtime(true) - $t0) * 1000);
		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
		$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
		$user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

		$payload = [
			'ms' => $elapsed_ms,
			'page' => $page,
			'user_id' => $user_id,
			'uri' => $uri,
		] + $context;

		error_log('[FI_TRACE] ' . $message . ' ' . wp_json_encode($payload));
	}
}

// Earliest safe admin breadcrumb.
add_action('admin_init', function () {
	fi_trace('admin_init:start', [
		'mem' => function_exists('memory_get_usage') ? memory_get_usage(true) : null,
		'mem_peak' => function_exists('memory_get_peak_usage') ? memory_get_peak_usage(true) : null,
	]);
}, 0);

// Record where we ended up even on fatal/timeout.
register_shutdown_function(function () {
	if (!fi_trace_enabled()) {
		return;
	}
	$err = error_get_last();
	fi_trace('shutdown', [
		'last_error' => $err,
		'mem' => function_exists('memory_get_usage') ? memory_get_usage(true) : null,
		'mem_peak' => function_exists('memory_get_peak_usage') ? memory_get_peak_usage(true) : null,
	]);
});

