<?php
/**
 * Freedom Index AJAX Logging Utilities
 *
 * Straight function version of the former FICore\AJAX class file.
 *
 * Summary:
 * - Centralized opt-in logger for AJAX diagnostics.
 * - Uses the existing FI logging settings area toggle.
 * - AJAX logs are disabled unless the ajax area is explicitly enabled.
 */

if (!defined('ABSPATH')) exit;

/**
 * Check whether AJAX logging is enabled.
 *
 * Controlled by the existing FI logging settings.
 * This intentionally does not use fi_log_enabled() because that may default to allow
 * for unknown areas, which would make AJAX debugging too noisy.
 *
 * @return bool True if AJAX logging is explicitly enabled.
 */
function fi_ajax_log_enabled(): bool {
	if (!function_exists('fi_log_settings_get')) {
		return false;
	}

	$cfg = fi_log_settings_get();

	if (empty($cfg['enabled'])) {
		return false;
	}

	$areas = $cfg['areas'] ?? [];
	if (!is_array($areas)) {
		return false;
	}

	return !empty($areas['ajax']);
}

/**
 * Public helper to log AJAX diagnostics.
 *
 * @param string $message Log message.
 * @param array $context Structured context data.
 * @param string $level Log level: debug, info, warning, error.
 * @param string $file Optional file path. Use __FILE__.
 * @param int $line Optional line number. Use __LINE__.
 * @return bool True if written, false if disabled or unavailable.
 */
function fi_ajax_log(string $message, array $context = [], string $level = 'debug', string $file = '', int $line = 0): bool {
	if (!fi_ajax_log_enabled()) {
		return false;
	}

	if (!function_exists('fi_log_area')) {
		return false;
	}

	$payload = [
		'message' => $message,
		'context' => $context,
	];

	return fi_log_area('ajax', wp_json_encode($payload), $file, $line, $level);
}