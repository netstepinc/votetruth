<?php
namespace FI\Core {
	if (!defined('ABSPATH')) { exit; }

	/**
	 * AJAX logging utilities
	 *
	 * Summary:
	 * - Centralized opt-in logger for AJAX diagnostics.
	 * - Intended for short-lived debugging; can be disabled via constant/filter.
	 */
	final class AJAX {

		/**
		 * Check whether AJAX logging is enabled.
		 *
		 * Controlled by the existing FI logging settings (Admin > Settings > Logging).
		 * This is the same toggle system used by fi_log()/fi_log_area().
		 */
		public static function enabled(): bool {
			// IMPORTANT: do NOT allow "ajax" logs unless the ajax area is explicitly enabled in settings.
			// fi_log_enabled() defaults to "allow" for unknown areas, which is too noisy for ajax debugging.
			if (function_exists('fi_log_settings_get')) {
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
			return false;
		}

		/**
		 * Write a structured AJAX log line to the FI log.
		 *
		 * @param string $message
		 * @param array $context
		 * @param string $level
		 */
		public static function log(string $message, array $context = [], string $level = 'debug', string $file = '', int $line = 0): bool {
			if (!self::enabled()) {
				return false;
			}

			$payload = [
				'message' => $message,
				'context' => $context,
			];

			if (function_exists('fi_log_area')) {
				return fi_log_area('ajax', wp_json_encode($payload), $file, $line, $level);
			}

			return false;
		}
	}
}

// Global namespace: convenience wrapper
namespace {
	if (!defined('ABSPATH')) { exit; }

	/**
	 * Public helper to log AJAX diagnostics (opt-in via FI_AJAX_LOG / filter).
	 */
	function fi_ajax_log(string $message, array $context = [], string $level = 'debug', string $file = '', int $line = 0): bool {
		return \FI\Core\AJAX::log($message, $context, $level, $file, $line);
	}
}

