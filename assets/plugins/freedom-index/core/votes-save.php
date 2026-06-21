<?php
/**
 * Vote Save/Update/Delete Functions
 * 
 * Functions for saving, updating, and deleting votes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Save or update a vote
 * 
 * @param array $data Vote data
 * @param int|null $vote_id Update existing if provided
 * @return int|false Vote ID on success, false on failure
 */
function fi_vote_save(array $data, ?int $vote_id = null): int|false {
	global $wpdb;
	
	// Start with base fields
	$base_fields = [
		'gov' => $data['gov'] ?? null,
		'session_id' => $data['session_id'] ?? null,
		'bill_id' => $data['bill_id'] ?? null,
		'bill_key' => $data['bill_key'] ?? null,
		'title' => $data['title'] ?? null,
		'date_voted' => $data['date_voted'] ?? null,
		'constitutional' => $data['constitutional'] ?? null,
		'description' => $data['description'] ?? null,
		'url' => $data['url'] ?? null,
		'status' => $data['status'] ?? 'draft',
		'rollcall_data' => $data['rollcall_data'] ?? null,
		'meta' => isset($data['meta']) ? wp_json_encode($data['meta']) : null,
	];
	
	// Remove null values
	$base_fields = array_filter($base_fields, fn($v) => $v !== null);
	
	// Generate slug if not provided
	if (empty($base_fields['slug']) && !empty($base_fields['title'])) {
		$base_fields['slug'] = sanitize_title($base_fields['title']);
	}
	
	// Validate
	$validation = fi_vote_validate_data($base_fields);
	if (!$validation['valid']) {
		return false;
	}
	
	// Check for duplicates
	if (!$vote_id) {
		$duplicate_check = fi_vote_check_duplicates($base_fields);
		if ($duplicate_check['is_duplicate']) {
			return $duplicate_check['existing_id'];
		}
	}
	
	// Prepare data
	$fields = [];
	$formats = [];
	foreach ($base_fields as $key => $value) {
		$fields[$key] = $value;
		$formats[] = is_int($value) ? '%d' : (is_float($value) ? '%f' : '%s');
	}
	
	if ($vote_id) {
		// Update
		$result = $wpdb->update(
			$wpdb->prefix . 'fi_votes',
			$fields,
			['id' => $vote_id],
			$formats,
			['%d']
		);
		if ($result !== false) {
			do_action('fi_vote_saved', $vote_id, array_merge($base_fields, ['id' => $vote_id]));
		}
		return $result !== false ? $vote_id : false;
	} else {
		// Insert
		$result = $wpdb->insert(
			$wpdb->prefix . 'fi_votes',
			$fields,
			$formats
		);
		$new_id = $result ? (int) $wpdb->insert_id : false;
		if ($new_id) {
			do_action('fi_vote_saved', $new_id, array_merge($base_fields, ['id' => $new_id]));
		}
		return $new_id;
	}
}

/**
 * Update an existing vote
 * 
 * @param int $vote_id Vote ID
 * @param array $data Vote data to update
 * @return bool True on success, false on failure
 */
function fi_vote_update(int $vote_id, array $data): bool {
	return fi_vote_save($data, $vote_id) !== false;
}

/**
 * Delete a vote
 * 
 * @param int $vote_id Vote ID
 * @return bool True on success, false on failure
 */
function fi_vote_delete(int $vote_id): bool {
	global $wpdb;
	
	// Check if vote exists
	$exists = $wpdb->get_var($wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}fi_votes WHERE id = %d",
		$vote_id
	));
	
	if (!$exists) {
		return false;
	}
	
	// Delete the vote
	$result = $wpdb->delete(
		$wpdb->prefix . 'fi_votes',
		['id' => $vote_id],
		['%d']
	);
	
	return $result !== false;
}

/**
 * Check for duplicate votes
 * 
 * @param array $data Vote data to check
 * @param int|null $exclude_id Exclude this ID from duplicate check
 * @return array ['is_duplicate' => bool, 'existing_id' => int|null]
 */
function fi_vote_check_duplicates(array $data, ?int $exclude_id = null): array {
	global $wpdb;
	
	$conditions = [];
	$values = [];
	
	// Check by title + date combination
	if (!empty($data['title']) && !empty($data['date_voted'])) {
		$conditions[] = 'title = %s AND date_voted = %s';
		$values[] = $data['title'];
		$values[] = $data['date_voted'];
	}
	
	// Check by bill_key
	if (!empty($data['bill_key'])) {
		$conditions[] = 'bill_key = %s';
		$values[] = $data['bill_key'];
	}
	
	if (empty($conditions)) {
		return ['is_duplicate' => false, 'existing_id' => null];
	}
	
	$where = implode(' OR ', $conditions);
	
	if ($exclude_id) {
		$where = "({$where}) AND id != %d";
		$values[] = $exclude_id;
	}
	
	$sql = "SELECT id FROM {$wpdb->prefix}fi_votes WHERE {$where} LIMIT 1";
	$existing_id = $wpdb->get_var($wpdb->prepare($sql, $values));
	
	return [
		'is_duplicate' => !empty($existing_id),
		'existing_id' => $existing_id ? (int) $existing_id : null,
	];
}

/**
 * Validate vote data
 * 
 * @param array $data Vote data to validate
 * @return array ['valid' => bool, 'errors' => array]
 */
function fi_vote_validate_data(array $data): array {
	$errors = [];
	
	// Required fields
	if (empty($data['title'])) {
		$errors[] = 'Title is required';
	}
	
	if (empty($data['session_id'])) {
		$errors[] = 'Session ID is required';
	}
	
	// Validate date format if provided
	if (!empty($data['date_voted']) && !fi_vote_validate_date($data['date_voted'])) {
		$errors[] = 'Invalid date format (use YYYY-MM-DD)';
	}
	
	// Validate constitutional value
	if (!empty($data['constitutional']) && !in_array($data['constitutional'], ['Y', 'N', 'U'])) {
		$errors[] = 'Constitutional must be Y, N, or U';
	}
	
	return [
		'valid' => empty($errors),
		'errors' => $errors,
	];
}

/**
 * Validate date format
 * 
 * @param string $date Date string
 * @return bool True if valid Y-m-d format
 */
function fi_vote_validate_date(string $date): bool {
	$d = \DateTime::createFromFormat('Y-m-d', $date);
	return $d && $d->format('Y-m-d') === $date;
}
