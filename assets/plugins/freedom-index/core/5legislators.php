<?php
/**
 * Legislators - Single Legislator Data Functions
 * 
 * Clean, procedural functions for fetching single legislator data.
 * Designed for the legislator detail page (header, sessions, votes).
 * 
 * @package FreedomIndex
 */

if (!defined('ABSPATH')) exit;

// =============================================================================
// PUBLIC API FUNCTIONS
// =============================================================================

/**
 * Get single legislator by ID (base data only)
 * 
 * @param int $id Legislator ID
 * @return object|null Legislator object or null if not found
 */
function fi_legislator_get(int $id): ?object {
	global $wpdb;
	
	$table = $wpdb->prefix . 'fi_legislators';
	$row = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$table} WHERE id = %d",
		$id
	));
	
	if (!$row) {
		return null;
	}
	
	return _fi_legislator_format_base($row);
}

/**
 * Get legislator with all sessions (for header display)
 * 
 * @param int $id Legislator ID  
 * @return object|null Legislator object enriched with session data
 */
function fi_legislator_get_with_sessions(int $id): ?object {
	global $wpdb;
	
	// Get base legislator data
	$legislator = fi_legislator_get($id);
	if (!$legislator) {
		return null;
	}
	
	// Get all sessions for this legislator
	$sessions_table = $wpdb->prefix . 'fi_legislator_sessions';
	$fi_sessions_table = $wpdb->prefix . 'fi_sessions';
	
	$sessions = $wpdb->get_results($wpdb->prepare("
		SELECT 
			s.id as session_id,
			s.name as session_name,
			s.date_start,
			s.date_end,
			s.gov,
			ls.score as session_score,
			ls.score_data as session_score_data,
			ls.chamber,
			ls.district,
			ls.party,
			ls.image_id,
			ls.score as lifetime_score
		FROM {$sessions_table} ls
		INNER JOIN {$fi_sessions_table} s ON ls.session_id = s.id
		WHERE ls.legislator_id = %d
			AND s.parent_id IS NULL
		ORDER BY s.date_end DESC, s.id DESC
	", $id));
	
	// Add lookup values to each session
	foreach ($sessions as &$session) {
		$session->gov_name = FI_GOVERNMENTS[$session->gov]['name'] ?? $session->gov;
		$session->state_name = FI_GOVERNMENTS[$session->gov]['state_name'] ?? '';
		$session->party_name = FI_PARTIES[$session->party] ?? $session->party;
		$session->chamber_label = FI_CHAMBERS[$session->chamber]['label'] ?? $session->chamber;
		$session->chamber_title = FI_CHAMBERS[$session->chamber]['title'] ?? '';
	}
	
	$legislator->sessions = $sessions;
	
	// Flatten most recent session data to legislator object (like API does)
	if (!empty($sessions)) {
		$current = $sessions[0];
		$legislator->session_id = $current->session_id;
		$legislator->session_name = $current->session_name;
		$legislator->gov = $current->gov;
		$legislator->state = FI_GOVERNMENTS[$current->gov]['state'] ?? '';
		$legislator->party = $current->party;
		$legislator->chamber = $current->chamber;
		$legislator->district = $current->district;
		$legislator->session_score = $current->session_score;
		$legislator->session_score_data = $current->session_score_data;
		$legislator->image_id = $current->image_id;
		$legislator->date_start = $current->date_start;
		$legislator->date_end = $current->date_end;
		
		// Add lookup values for header display
		$legislator->gov_name = FI_GOVERNMENTS[$current->gov]['name'] ?? $current->gov;
		$legislator->state_name = FI_GOVERNMENTS[$current->gov]['state_name'] ?? '';
		$legislator->party_name = FI_PARTIES[$current->party] ?? $current->party;
		$legislator->chamber_label = FI_CHAMBERS[$current->chamber]['label'] ?? $current->chamber;
		$legislator->chamber_title = FI_CHAMBERS[$current->chamber]['title'] ?? '';
		$legislator->lifetime_score = $current->lifetime_score;
	}
	
	return $legislator;
}

/**
 * Get sessions for a legislator
 * 
 * @param int $id Legislator ID
 * @return array Array of session objects
 */
function fi_legislator_get_sessions(int $id): array {
	global $wpdb;
	
	$sessions_table = $wpdb->prefix . 'fi_legislator_sessions';
	$fi_sessions_table = $wpdb->prefix . 'fi_sessions';
	
	$sessions = $wpdb->get_results($wpdb->prepare("
		SELECT 
			s.id as session_id,
			s.name as session_name,
			s.date_start,
			s.date_end,
			s.gov,
			ls.score as session_score,
			ls.score_data as session_score_data,
			ls.chamber,
			ls.district,
			ls.party,
			ls.image_id,
			ls.score as lifetime_score
		FROM {$sessions_table} ls
		INNER JOIN {$fi_sessions_table} s ON ls.session_id = s.id
		WHERE ls.legislator_id = %d
			AND s.parent_id IS NULL
		ORDER BY s.date_end DESC, s.id DESC
	", $id));
	
	// Add lookup values
	foreach ($sessions as &$session) {
		$session->gov_name = FI_GOVERNMENTS[$session->gov]['name'] ?? $session->gov;
		$session->state_name = FI_GOVERNMENTS[$session->gov]['state_name'] ?? '';
		$session->party_name = FI_PARTIES[$session->party] ?? $session->party;
		$session->chamber_label = FI_CHAMBERS[$session->chamber]['label'] ?? $session->chamber;
		$session->chamber_title = FI_CHAMBERS[$session->chamber]['title'] ?? '';
	}
	
	return $sessions;
}

// =============================================================================
// PRIVATE HELPER FUNCTIONS
// =============================================================================

/**
 * Format base legislator row into clean object
 * 
 * @param object $row Database row
 * @return object Formatted legislator object
 */
function _fi_legislator_format_base(object $row): object {
	$legislator = new stdClass();
	
	// Core identity
	$legislator->id = (int)$row->id;
	$legislator->first_name = $row->first_name ?? '';
	$legislator->middle_name = $row->middle_name ?? '';
	$legislator->last_name = $row->last_name ?? '';
	$legislator->display_name = $row->display_name ?? '';
	$legislator->sort_name = $row->sort_name ?? '';
	$legislator->slug = $row->slug ?? '';
	
	// Contact info
	$legislator->email = $row->email ?? '';
	$legislator->phone = $row->phone ?? '';
	$legislator->website = $row->website ?? '';
	$legislator->address = $row->address ?? '';
	$legislator->twitter = $row->twitter ?? '';
	$legislator->facebook = $row->facebook ?? '';
	
	// Meta
	$legislator->meta = !empty($row->meta) ? json_decode($row->meta, true) : [];
	
	// Sessions array (populated by fi_legislator_get_with_sessions)
	$legislator->sessions = [];
	
	return $legislator;
}