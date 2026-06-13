<?php
/**
 * Freedom Index Core Meta Helpers
 *
 * Straight function replacement for the former FI\Core\Traits\Meta trait.
 *
 * Provides consistent JSON meta handling for FI custom tables.
 * Used by legislators, sessions, votes, reports, taxonomy records, and other
 * table-based records with a JSON/TEXT `meta` column.
 */

if (!defined('ABSPATH')) exit;

/**
 * Normalize a FI table name fragment and return the fully-prefixed table name.
 *
 * @param string $table Table name without WP prefix. Example: fi_legislators.
 * @return string Fully-prefixed table name or empty string on invalid input.
 */
function fi_meta_table_name(string $table): string {
	global $wpdb;

	$table = trim($table);
	$table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

	if ($table === '' || strpos($table, 'fi_') !== 0) {
		return '';
	}

	return $wpdb->prefix . $table;
}

/**
 * Decode a JSON meta value into an array.
 *
 * @param mixed $meta JSON string, array, object, or null.
 * @return array Meta array.
 */
function fi_meta_decode($meta): array {
	if (is_array($meta)) {
		return $meta;
	}

	if (is_object($meta)) {
		return (array) $meta;
	}

	if (is_string($meta) && $meta !== '') {
		$decoded = json_decode($meta, true);
		return is_array($decoded) ? $decoded : [];
	}

	return [];
}

/**
 * Encode a meta array for database storage.
 *
 * @param array $meta Meta data.
 * @return string|null JSON string or null when empty.
 */
function fi_meta_encode(array $meta): ?string {
	if (empty($meta)) {
		return null;
	}

	$encoded = wp_json_encode($meta);
	return is_string($encoded) ? $encoded : null;
}

/**
 * Get a specific meta value by key from a database record.
 *
 * @param object|array $record Database record with meta field.
 * @param string $key Meta key.
 * @param mixed $default Default value.
 * @return mixed Meta value or default.
 */
function fi_meta_get($record, string $key, $default = null) {
	$meta = fi_meta_get_all($record);
	return array_key_exists($key, $meta) ? $meta[$key] : $default;
}

/**
 * Get all meta from a database record as an associative array.
 *
 * @param object|array $record Database record with meta field.
 * @return array Meta array.
 */
function fi_meta_get_all($record): array {
	if (is_object($record)) {
		return fi_meta_decode($record->meta ?? null);
	}

	if (is_array($record)) {
		return fi_meta_decode($record['meta'] ?? null);
	}

	return [];
}

/**
 * Get all meta for a row by table and ID.
 *
 * @param int $record_id Record ID.
 * @param string $table Table name without WP prefix.
 * @return array Meta array.
 */
function fi_meta_get_all_by_id(int $record_id, string $table): array {
	global $wpdb;

	$record_id = absint($record_id);
	$table_name = fi_meta_table_name($table);

	if ($record_id <= 0 || $table_name === '') {
		return [];
	}

	$current_meta_json = $wpdb->get_var($wpdb->prepare(
		"SELECT meta FROM `{$table_name}` WHERE id = %d",
		$record_id
	));

	return fi_meta_decode($current_meta_json);
}

/**
 * Set entire meta array for a table row.
 *
 * @param int $record_id Record ID.
 * @param string $table Table name without WP prefix.
 * @param array $meta Meta array.
 * @return bool Success.
 */
function fi_meta_set_all(int $record_id, string $table, array $meta): bool {
	global $wpdb;

	$record_id = absint($record_id);
	$table_name = fi_meta_table_name($table);

	if ($record_id <= 0 || $table_name === '') {
		return false;
	}

	$result = $wpdb->update(
		$table_name,
		['meta' => fi_meta_encode($meta)],
		['id' => $record_id],
		['%s'],
		['%d']
	);

	return $result !== false;
}

/**
 * Update specific meta key(s) without affecting other keys.
 *
 * Uses JSON_MERGE_PATCH when available. Falls back to read/merge/write.
 *
 * @param int $record_id Record ID.
 * @param string $table Table name without WP prefix.
 * @param array $meta_updates Key/value pairs to merge.
 * @return bool Success.
 */
function fi_meta_update(int $record_id, string $table, array $meta_updates): bool {
	global $wpdb;

	$record_id = absint($record_id);
	$table_name = fi_meta_table_name($table);

	if ($record_id <= 0 || $table_name === '') {
		return false;
	}

	if (empty($meta_updates)) {
		return true;
	}

	$patch_json = wp_json_encode($meta_updates);
	if (!is_string($patch_json)) {
		return false;
	}

	// Fast path: database-level JSON merge.
	$sql = "UPDATE `{$table_name}` SET meta = JSON_MERGE_PATCH(IFNULL(meta, '{}'), %s) WHERE id = %d";
	$result = $wpdb->query($wpdb->prepare($sql, $patch_json, $record_id));

	// $wpdb->query returns 0 when no row changed; false means SQL failure.
	if ($result !== false) {
		return true;
	}

	// Fallback: merge in PHP for older DB engines or TEXT meta columns.
	$current_meta = fi_meta_get_all_by_id($record_id, $table);
	$updated_meta = fi_meta_merge_patch($current_meta, $meta_updates);

	return fi_meta_set_all($record_id, $table, $updated_meta);
}

/**
 * Delete specific meta key(s) from a table row.
 *
 * @param int $record_id Record ID.
 * @param string $table Table name without WP prefix.
 * @param string|array $keys Meta key or keys.
 * @return bool Success.
 */
function fi_meta_delete(int $record_id, string $table, $keys): bool {
	$record_id = absint($record_id);

	if ($record_id <= 0) {
		return false;
	}

	$current_meta = fi_meta_get_all_by_id($record_id, $table);
	if (empty($current_meta)) {
		return true;
	}

	$keys = is_array($keys) ? $keys : [$keys];
	foreach ($keys as $key) {
		unset($current_meta[(string) $key]);
	}

	return fi_meta_set_all($record_id, $table, $current_meta);
}

/**
 * Merge arrays using JSON Merge Patch semantics.
 *
 * Null values remove keys. Arrays/objects are recursively merged.
 *
 * @param array $target Existing meta.
 * @param array $patch Patch data.
 * @return array Merged meta.
 */
function fi_meta_merge_patch(array $target, array $patch): array {
	foreach ($patch as $key => $value) {
		if ($value === null) {
			unset($target[$key]);
			continue;
		}

		if (is_array($value) && isset($target[$key]) && is_array($target[$key]) && fi_meta_is_assoc($value)) {
			$target[$key] = fi_meta_merge_patch($target[$key], $value);
			continue;
		}

		$target[$key] = $value;
	}

	return $target;
}

/**
 * Determine whether an array is associative.
 *
 * @param array $array Array to inspect.
 * @return bool True if associative.
 */
function fi_meta_is_assoc(array $array): bool {
	if ($array === []) {
		return false;
	}

	return array_keys($array) !== range(0, count($array) - 1);
}

/* -------------------------------------------------------------------------
 * Compatibility aliases for old trait-style naming in refactored files.
 * ---------------------------------------------------------------------- */

function fi_get_meta($record, string $key, $default = null) {
	return fi_meta_get($record, $key, $default);
}

function fi_get_all_meta($record): array {
	return fi_meta_get_all($record);
}

function fi_update_meta(int $record_id, string $table, array $meta_updates): bool {
	return fi_meta_update($record_id, $table, $meta_updates);
}

function fi_delete_meta(int $record_id, string $table, $keys): bool {
	return fi_meta_delete($record_id, $table, $keys);
}

function fi_set_all_meta(int $record_id, string $table, array $meta): bool {
	return fi_meta_set_all($record_id, $table, $meta);
}