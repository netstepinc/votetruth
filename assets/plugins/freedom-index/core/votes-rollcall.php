<?php
/**
 * Vote Rollcall Functions
 * 
 * Functions for handling vote rollcall data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Check if a vote has rollcall data
 * 
 * @param int $vote_id Vote ID
 * @return bool True if rollcall data exists
 */
function fi_vote_has_rollcall(int $vote_id): bool {
	global $wpdb;
	
	$sql = "SELECT rollcall_data FROM {$wpdb->prefix}fi_votes WHERE id = %d LIMIT 1";
	$data = $wpdb->get_var($wpdb->prepare($sql, $vote_id));
	
	return !empty($data);
}

/**
 * Get rollcall data for a vote
 * 
 * @param int $vote_id Vote ID
 * @return string Raw rollcall JSON data
 */
function fi_vote_rollcall_data(int $vote_id): string {
	global $wpdb;
	
	$sql = "SELECT rollcall_data FROM {$wpdb->prefix}fi_votes WHERE id = %d LIMIT 1";
	return $wpdb->get_var($wpdb->prepare($sql, $vote_id)) ?: '';
}

/**
 * Get rollcall map for a batch of vote IDs
 * 
 * @param array $vote_ids Array of vote IDs
 * @return array Map of vote_id => [legislator_id => cast, ...]
 */
function fi_vote_rollcall_map(array $vote_ids): array {
	global $wpdb;
	
	if (empty($vote_ids)) {
		return [];
	}
	
	$placeholders = implode(', ', array_fill(0, count($vote_ids), '%d'));
	$sql = "SELECT vote_id, legislator_id, cast FROM {$wpdb->prefix}fi_voterc WHERE vote_id IN ({$placeholders})";
	$rollcalls = $wpdb->get_results($wpdb->prepare($sql, $vote_ids));
	
	$rollcall_map = [];
	foreach ($rollcalls as $rollcall) {
		$rollcall_map[$rollcall['vote_id']][$rollcall['legislator_id']] = $rollcall['cast'];
	}
	
	return $rollcall_map;
}
