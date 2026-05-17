<?php
namespace FI\Core\Traits;

if (!defined('ABSPATH')) exit;

/**
 * Meta Trait - Unified JSON meta handling for all FI classes
 * 
 * Provides consistent get/set/update/delete operations for JSON meta fields
 * Used by: Legislators, Sessions, Votes, Tags, etc.
 */
trait Meta {
	
	/**
	 * Get a specific meta value by key
	 * 
	 * @param object $record Database record with meta field
	 * @param string $key Meta key to retrieve
	 * @param mixed $default Default value if key doesn't exist
	 * @return mixed Meta value or default
	 */
	public static function get_meta($record, string $key, $default = null) {
		if (!isset($record->meta)) {
			return $default;
		}
		
		$meta = is_string($record->meta) ? json_decode($record->meta, true) : (array) $record->meta;
		
		return $meta[$key] ?? $default;
	}
	
	/**
	 * Get all meta as associative array
	 * 
	 * @param object $record Database record with meta field
	 * @return array Meta array
	 */
	public static function get_all_meta($record): array {
		if (!isset($record->meta)) {
			return [];
		}
		
		return is_string($record->meta) ? json_decode($record->meta, true) : (array) $record->meta;
	}
	
	/**
	 * Update specific meta key(s) without affecting other keys
	 * Uses JSON_MERGE_PATCH for efficiency when available (MariaDB 10.2.3+/MySQL 5.7+)
	 * 
	 * @param int $record_id Record ID
	 * @param string $table Table name (without prefix)
	 * @param array $meta_updates Associative array of key => value pairs to update
	 * @return bool Success
	 */
	public static function update_meta(int $record_id, string $table, array $meta_updates): bool {
		global $wpdb;
		
		// If no meta data to update, do nothing
		if (empty($meta_updates)) {
			return true;
		}
		
		// Try JSON_MERGE_PATCH first (fastest, database-level merge)
		$patch_json = wp_json_encode($meta_updates);
		$sql = "UPDATE {$wpdb->prefix}{$table} SET meta = JSON_MERGE_PATCH(IFNULL(meta, '{}'), %s) WHERE id = %d";
		$result = $wpdb->query($wpdb->prepare($sql, $patch_json, $record_id));
		
		// $wpdb->query returns 0 for "no change", so check for error (false)
		if ($result !== false) {
			return true;
		}
		
		// Fallback: DB may not support JSON_MERGE_PATCH or meta may not be a JSON column
		// Read/merge/write in PHP to ensure meta updates still persist
		$current_meta_json = $wpdb->get_var($wpdb->prepare(
			"SELECT meta FROM {$wpdb->prefix}{$table} WHERE id = %d",
			$record_id
		));
		
		$current_meta = [];
		if (is_string($current_meta_json) && $current_meta_json !== '') {
			$decoded = json_decode($current_meta_json, true);
			$current_meta = is_array($decoded) ? $decoded : [];
		}
		
		// Use array_replace_recursive for deep merge (matches JSON_MERGE_PATCH behavior)
		$updated_meta = array_replace_recursive($current_meta, $meta_updates);
		
		// Save back to database
		$result = $wpdb->update(
			$wpdb->prefix . $table,
			['meta' => wp_json_encode($updated_meta)],
			['id' => $record_id],
			['%s'],
			['%d']
		);
		
		return $result !== false;
	}
	
	/**
	 * Delete specific meta key(s)
	 * 
	 * @param int $record_id Record ID
	 * @param string $table Table name (without prefix)
	 * @param string|array $keys Meta key(s) to delete
	 * @return bool Success
	 */
	public static function delete_meta(int $record_id, string $table, $keys): bool {
		global $wpdb;
		
		// Get current meta
		$current_meta_json = $wpdb->get_var($wpdb->prepare(
			"SELECT meta FROM {$wpdb->prefix}{$table} WHERE id = %d",
			$record_id
		));
		
		$current_meta = $current_meta_json ? json_decode($current_meta_json, true) : [];
		if (!is_array($current_meta)) {
			return true; // Nothing to delete
		}
		
		// Remove specified keys
		$keys = (array) $keys;
		foreach ($keys as $key) {
			unset($current_meta[$key]);
		}
		
		// Save back to database
		$result = $wpdb->update(
			$wpdb->prefix . $table,
			['meta' => json_encode($current_meta)],
			['id' => $record_id],
			['%s'],
			['%d']
		);
		
		return $result !== false;
	}
	
	/**
	 * Set entire meta (replaces all existing meta)
	 * 
	 * @param int $record_id Record ID
	 * @param string $table Table name (without prefix)
	 * @param array $meta New meta array
	 * @return bool Success
	 */
	public static function set_all_meta(int $record_id, string $table, array $meta): bool {
		global $wpdb;
		
		$result = $wpdb->update(
			$wpdb->prefix . $table,
			['meta' => json_encode($meta)],
			['id' => $record_id],
			['%s'],
			['%d']
		);
		
		return $result !== false;
	}
}
