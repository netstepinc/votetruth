<?php
/**
 * Vote Stats and Formatting Functions
 * 
 * Functions for vote statistics and formatting
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Get vote statistics
 * 
 * @param string|null $gov Government code filter
 * @param int|null $session_id Session ID filter
 * @return array Statistics array
 */
function fi_votes_stats(?string $gov = null, ?int $session_id = null): array {
	global $wpdb;
	
	$where_conditions = [];
	$where_values = [];
	
	if ($gov) {
		$where_conditions[] = 's.gov = %s';
		$where_values[] = $gov;
	}
	
	if ($session_id) {
		$where_conditions[] = 'v.session_id = %d';
		$where_values[] = $session_id;
	}
	
	$where_clause = '';
	if (!empty($where_conditions)) {
		$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
	}
	
	// Total count
	$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}fi_votes v LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id {$where_clause}";
	$total = $wpdb->get_var($wpdb->prepare($sql, $where_values));
	
	// By status
	$sql = "SELECT v.status, COUNT(*) as count FROM {$wpdb->prefix}fi_votes v LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id {$where_clause} GROUP BY v.status";
	$by_status = $wpdb->get_results($wpdb->prepare($sql, $where_values));
	
	// With rollcall
	$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}fi_votes v LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id {$where_clause} AND rollcall_data IS NOT NULL AND rollcall_data != ''";
	$with_rollcall = $wpdb->get_var($wpdb->prepare($sql, $where_values));
	
	return [
		'total' => (int) $total,
		'by_status' => $by_status,
		'with_rollcall' => (int) $with_rollcall,
	];
}

/**
 * Format cost data
 * 
 * @param string $cost Cost string (e.g., "+1000000" or "-50000")
 * @return array Formatted cost data
 */
function fi_vote_format_cost(string $cost): array {
	$cost_data = [
		'raw' => $cost,
		'num' => '',
		'formatted' => '',
		'class' => '',
		'html' => '',
		'class-text' => '',
		'effect-text' => 'effect',
		'sentence' => '',
	];
	
	if (empty($cost)) {
		return $cost_data;
	}
	
	// Parse number
	$cost_data['num'] = trim(str_replace(['+', '-', '$', ','], '', $cost));
	
	if ($cost_data['num'] != '') {
		$cost_data['formatted'] = '$' . number_format_i18n((float) $cost_data['num'], 2);
		$cost_data['rounded'] = '$' . number_format_i18n((float) $cost_data['num'], 0);
		
		if (substr(trim($cost), 0, 1) == '+') {
			$cost_data['indicator'] = '+';
			$cost_data['class'] = 'text-success';
			$cost_data['class-text'] = 'text-success';
			$cost_data['effect-text'] = 'benefit';
		} else {
			$cost_data['indicator'] = '-';
			$cost_data['class'] = 'text-danger';
			$cost_data['class-text'] = 'text-danger';
			$cost_data['effect-text'] = 'cost';
		}
		
		$cost_data['sentence'] = '<div class="vote-cost ' . $cost_data['class-text'] . '">Estimated ' . $cost_data['effect-text'] . ' per household: <b>' . $cost_data['indicator'] . $cost_data['formatted'] . '/year.</b></div>';
		$cost_data['html'] = '<span class="' . esc_attr($cost_data['class-text']) . '">' . $cost_data['indicator'] . wp_kses_post($cost_data['formatted']) . '</span>';
	}
	
	return $cost_data;
}

/**
 * Get status options
 * 
 * @return array Status options array
 */
function fi_vote_get_status_options(): array {
	return [
		'publish' => 'Published',
		'draft' => 'Draft',
		'archived' => 'Archived',
	];
}
