<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Log a message to the Freedom Index log file.
 *
 * @param string $message Log message.
 * @param string $file Optional file path. Use __FILE__.
 * @param int    $line Optional line number. Use __LINE__.
 * @param string $level Log level: debug, info, warning, error.
 * @return bool Success.
 */
function fi_log( string $message, string $file = '', int $line = 0, string $level = 'info' ): bool {
	$log_file = fi_log_get_file();
	$log_dir  = dirname( $log_file );

	if ( ! file_exists( $log_dir ) ) {
		wp_mkdir_p( $log_dir );
	}

	if ( ! is_dir( $log_dir ) || ! is_writable( $log_dir ) ) {
		return false;
	}

	$timestamp = current_time( 'Y-m-d H:i:s' );
	$level     = strtoupper( sanitize_key( $level ) );

	if ( $file !== '' ) {
		$file = str_replace( ABSPATH . 'wp-content/plugins/freedom-index/', '', $file );
		$file = str_replace( ABSPATH, '', $file );
	}

	$entry = "[{$timestamp}] [{$level}] {$file}:{$line} {$message}" . PHP_EOL;

	$result = file_put_contents( $log_file, $entry, FILE_APPEND | LOCK_EX );

	return $result !== false;
}

/**
 * Log a message with an explicit area.
 *
 * This lets templates and class/function wrappers opt into a shared toggle.
 *
 * @param string $area Area key.
 * @param string $message Log message.
 * @param string $file Optional file path. Use __FILE__.
 * @param int    $line Optional line number. Use __LINE__.
 * @param string $level Log level: debug, info, warning, error.
 * @return bool Success.
 */
function fi_log_area( string $area, string $message, string $file = '', int $line = 0, string $level = 'info' ): bool {
	$area = sanitize_key( $area ?: 'general' );

	return fi_log( "[{$area}] {$message}", $file, $line, $level );
}

/**
 * Format file size for display.
 *
 * @param int $bytes File size in bytes.
 * @return string Formatted size.
 */
function fi_format_size( int $bytes ): string {
	if ( $bytes >= 1073741824 ) {
		return number_format( $bytes / 1073741824, 2 ) . ' GB';
	}

	if ( $bytes >= 1048576 ) {
		return number_format( $bytes / 1048576, 2 ) . ' MB';
	}

	if ( $bytes >= 1024 ) {
		return number_format( $bytes / 1024, 2 ) . ' KB';
	}

	return $bytes . ' bytes';
}

/**
 * Get log contents.
 *
 * @param int $lines Number of lines to retrieve. 0 = all.
 * @return array Array of log lines.
 */
function fi_log_get( int $lines = 0 ): array {
	$log_file = fi_log_get_file();

	if ( ! file_exists( $log_file ) || ! is_readable( $log_file ) ) {
		return [];
	}

	$content = file_get_contents( $log_file );

	if ( $content === false || $content === '' ) {
		return [];
	}

	$log_lines = explode( PHP_EOL, $content );
	$log_lines = array_filter( $log_lines );

	if ( $lines > 0 ) {
		$log_lines = array_slice( $log_lines, -$lines );
	}

	return array_values( $log_lines );
}

/**
 * Get log file path.
 *
 * @return string Log file path.
 */
function fi_log_get_file(): string {
	static $log_file = null;

	if ( $log_file === null ) {
		$log_file = trailingslashit( FI_DIR_CACHE ) . 'log/freedom-index.log';
	}

	return $log_file;
}

/**
 * Clear log file.
 *
 * @return bool Success.
 */
function fi_log_clear(): bool {
	$log_file = fi_log_get_file();

	if ( file_exists( $log_file ) ) {
		return unlink( $log_file );
	}

	return true;
}

/**
 * Get log file size.
 *
 * @return int File size in bytes.
 */
function fi_log_get_size(): int {
	$log_file = fi_log_get_file();

	if ( ! file_exists( $log_file ) ) {
		return 0;
	}

	$size = filesize( $log_file );

	return $size !== false ? (int) $size : 0;
}