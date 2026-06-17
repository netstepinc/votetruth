<?php if(!defined('ABSPATH')) exit;
/*
Include this file in /freedom-index.php if necessary.
*/

// -----------------------------------------------------------------------------
// Opt-in bootstrap tracing (diagnose hard timeouts where wp-admin/front-end never render)
//
// Usage: append `?fi_trace=1` (or `&fi_trace=1`) to any request and inspect the PHP error log.
// Summary:
// - Uses error_log() only (avoids FI file logger lock contention).
// - Logs around module includes to pinpoint the exact file where execution stalls.
// -----------------------------------------------------------------------------
if (!function_exists('fi_boot_trace_enabled')) {
	function fi_boot_trace_enabled(): bool {
		return isset($_GET['fi_trace']) && (string) $_GET['fi_trace'] === '1';
	}
}
if (!function_exists('fi_boot_trace')) {
	function fi_boot_trace(string $message, array $context = []): void {
		if (!fi_boot_trace_enabled()) {
			return;
		}
		static $t0 = null;
		if ($t0 === null) {
			$t0 = microtime(true);
		}
		$elapsed_ms = (int) round((microtime(true) - $t0) * 1000);
		$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
		$payload = ['ms' => $elapsed_ms, 'uri' => $uri] + $context;
		@error_log('[FI_BOOT_TRACE] ' . $message . ' ' . wp_json_encode($payload));
	}
}
fi_boot_trace('bootstrap:start', [
	'is_admin' => function_exists('is_admin') ? (bool) is_admin() : null,
	'is_multisite' => function_exists('is_multisite') ? (bool) is_multisite() : null,
	'blog_id' => function_exists('get_current_blog_id') ? (int) get_current_blog_id() : null,
]);
register_shutdown_function(function () {
	if (!fi_boot_trace_enabled()) {
		return;
	}
	$err = error_get_last();
	fi_boot_trace('bootstrap:shutdown', ['last_error' => $err]);
});