<?php
namespace FI\Core {
	if ( ! defined( 'ABSPATH' ) ) { exit; }

	/**
	 * Freedom Index Logger
	 * Custom logging system for Freedom Index plugin
	 * 
	 * @author Sam Mittelstaedt <smittelstaedt@jbs.org>
	 */
	final class Logger {
		
		private static $log_file = null;
		
		/**
		 * Get log file path
		 * 
		 * @return string Log file path
		 */
		public static function get_log_file(): string {
			if (self::$log_file === null) {
				self::$log_file = FI_DIR_CACHE . 'log/freedom-index.log';
			}
			return self::$log_file;
		}
		
		/**
		 * Write log entry
		 * 
		 * @param string $message Log message
		 * @param string $level Log level (info, warning, error, debug)
		 * @return bool Success
		 */
		public static function log(string $message,string $file='',int $line=0,string $level = 'info'): bool {
			$log_file = self::get_log_file();
			$log_dir = dirname($log_file);
			
			// Create log directory if it doesn't exist
			if (!file_exists($log_dir)) {
				wp_mkdir_p($log_dir);
			}
			
			// Format log entry
			$timestamp = current_time('Y-m-d H:i:s');
			$level = strtoupper($level);
			$file = str_replace(ABSPATH.'wp-content/plugins/freedom-index/', '', $file);
			$entry = "[{$timestamp}] [{$level}] {$file}:{$line} {$message}" . PHP_EOL;
		
			$result = file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
			return $result !== false;
		}
		
		/**
		 * Get log contents
		 * 
		 * @param int $lines Number of lines to retrieve (0 = all)
		 * @return array Array of log lines
		 */
		public static function get_logs(int $lines = 0): array {
			$log_file = self::get_log_file();
			
			if (!file_exists($log_file)) {
				return [];
			}
			
			$content = file_get_contents($log_file);
			if ($content === false) {
				return [];
			}
			
			$log_lines = explode(PHP_EOL, $content);
			$log_lines = array_filter($log_lines); // Remove empty lines
			
			if ($lines > 0) {
				$log_lines = array_slice($log_lines, -$lines);
			}
			
			return array_values($log_lines);
		}
		
		/**
		 * Clear log file
		 * 
		 * @return bool Success
		 */
		public static function clear_log(): bool {
			$log_file = self::get_log_file();
			
			if (file_exists($log_file)) {
				return unlink($log_file);
			}
			
			return true;
		}
		
		/**
		 * Get log file size
		 * 
		 * @return int File size in bytes
		 */
		public static function get_log_size(): int {
			$log_file = self::get_log_file();
			
			if (!file_exists($log_file)) {
				return 0;
			}
			
			return filesize($log_file);
		}
		
	}
}

// Global namespace for convenience function
namespace {
	if ( ! defined( 'ABSPATH' ) ) { exit; }
	/**
	 * Log a message with an explicit area.
	 * This lets templates (helpers-only rule) and class wrappers opt into a shared toggle.
	 */
	function fi_log_area(string $area, string $message, string $file = '', int $line = 0, string $level = 'info'): bool {
		$area = sanitize_key($area ?: 'general');
		return \FI\Core\Logger::log("[{$area}] {$message}", $file, $line, $level);
	}
	
	/**
	 * Log a message to Freedom Index log file
	 * 
	 * @param string $message Log message
	 * @param string $file Optional file path (use __FILE__)
	 * @param int $line Optional line number (use __LINE__)
	 * @param string $level Log level (debug, info, warning, error)
	 * @return bool Success
	 */
	function fi_log(string $message, $file='', int $line=0, string $level = 'info'): bool {
		return \FI\Core\Logger::log($message, $file, $line, $level);
	}

	/**
	 * Format file size for display
	 * 
	 * @param int $bytes File size in bytes
	 * @return string Formatted size
	 */
	function fi_format_size(int $bytes): string {
		if ($bytes >= 1073741824) {
			return number_format($bytes / 1073741824, 2) . ' GB';
		} elseif ($bytes >= 1048576) {
			return number_format($bytes / 1048576, 2) . ' MB';
		} elseif ($bytes >= 1024) {
			return number_format($bytes / 1024, 2) . ' KB';
		} else {
			return $bytes . ' bytes';
		}
	}
}