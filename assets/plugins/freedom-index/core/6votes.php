<?php
/**
 * Core Votes Functions
 * 
 * Query functions for fetching votes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
exit;
}

/**
 * Request-level cache for vote queries
 */
static $fi_votes_cache_get = [];

/**
 * Get votes with optional filtering
 * 
 * @param array $args Filter arguments
 * @return array|int Array of vote objects or count if $count is true
 */
function fi_votes_get(array $args = []): array|int {
global $wpdb;
static $cache = [];

// Check if keys were explicitly provided BEFORE merging with defaults
$provided_keys = array_keys($args);

$defaults = [
'gov' => null,
'session_id' => null,
'tag_id' => null,
'search' => null,
'status' => null,
'limit' => 50,
'offset' => 0,
'orderby' => 'date_voted',
'order' => 'DESC',
'count' => false,
'has_rollcall' => null,
'legislator_id' => null,
];

$args = wp_parse_args($args, $defaults);

// Handle status default based on context (admin vs public)
// Only apply default if status key was NOT explicitly provided
if (!in_array('status', $provided_keys)) {
$args['status'] = is_admin() ? null : 'publish';
}

// Build cache key from args
$cache_key = md5(serialize($args));

// Check request-level cache
if (isset($cache[$cache_key])) {
return $cache[$cache_key];
}

// Build query
$votes_table = $wpdb->prefix . 'fi_votes';
$sessions_table = $wpdb->prefix . 'fi_sessions';
$tags_table = $wpdb->prefix . 'fi_vote_tags';
$voterc_table = $wpdb->prefix . 'fi_voterc';

$select_fields = $args['count'] ? "COUNT(*)" : "v.*, s.name as session_name, s.gov as session_gov";

$join_clauses = [];
$where_conditions = [];
$where_values = [];

// Base FROM
$from_clause = "FROM {$votes_table} v";

// Session join (always needed for session_name and session_gov)
$join_clauses[] = "LEFT JOIN {$sessions_table} s ON v.session_id = s.id";

// Government filter
if (!empty($args['gov'])) {
$where_conditions[] = "s.gov = %s";
$where_values[] = $args['gov'];
}

// Session filter (supports single ID or array of IDs for hierarchy)
if (!empty($args['session_id'])) {
$session_ids = is_array($args['session_id']) ? $args['session_id'] : [$args['session_id']];
$placeholders = implode(', ', array_fill(0, count($session_ids), '%d'));
$where_conditions[] = "v.session_id IN ({$placeholders})";
$where_values = array_merge($where_values, $session_ids);
}

// Tag filter
if (!empty($args['tag_id'])) {
$join_clauses[] = "INNER JOIN {$tags_table} vt ON v.id = vt.vote_id";
$where_conditions[] = "vt.tag_id = %d";
$where_values[] = $args['tag_id'];
}

// Search filter
if (!empty($args['search'])) {
$search_term = '%' . $wpdb->esc_like($args['search']) . '%';
$where_conditions[] = "(v.title LIKE %s OR v.bill_key LIKE %s)";
$where_values[] = $search_term;
$where_values[] = $search_term;
}

// Status filter (support array or single value)
if (!empty($args['status'])) {
if (is_array($args['status'])) {
$placeholders = implode(', ', array_fill(0, count($args['status']), '%s'));
$where_conditions[] = "v.status IN ({$placeholders})";
$where_values = array_merge($where_values, $args['status']);
} else {
$where_conditions[] = "v.status = %s";
$where_values[] = $args['status'];
}
}

// Has rollcall filter
if ($args['has_rollcall'] !== null) {
if ($args['has_rollcall']) {
$where_conditions[] = "v.rollcall_data IS NOT NULL AND v.rollcall_data != ''";
} else {
$where_conditions[] = "(v.rollcall_data IS NULL OR v.rollcall_data = '')";
}
}

// Legislator filter (join with voterc)
if (!empty($args['legislator_id'])) {
$join_clauses[] = "INNER JOIN {$voterc_table} vr ON v.id = vr.vote_id";
$where_conditions[] = "vr.legislator_id = %d";
$where_values[] = $args['legislator_id'];
}

// Build WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Build JOINs
$joins = implode("\n", $join_clauses);

// Build ORDER BY
$orderby = sanitize_key($args['orderby']);
$order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
$order_clause = $args['count'] ? '' : "ORDER BY v.{$orderby} {$order}";

// Build LIMIT
$limit_clause = '';
if (!$args['count'] && $args['limit'] > 0) {
$limit_clause = $wpdb->prepare("LIMIT %d, %d", $args['offset'], $args['limit']);
}

// Build complete query
if ($args['count']) {
$sql = "SELECT {$select_fields} {$from_clause} {$joins} {$where_clause}";
} else {
$sql = "SELECT {$select_fields} {$from_clause} {$joins} {$where_clause} {$order_clause} {$limit_clause}";
}

// Prepare and execute
if (!empty($where_values)) {
$sql = $wpdb->prepare($sql, $where_values);
}

if ($args['count']) {
$results = (int) $wpdb->get_var($sql);
} else {
$results = $wpdb->get_results($sql);
}

// Store in request-level cache
$cache[$cache_key] = $results;

return $results;
}

/**
 * Get a single vote by ID
 * 
 * @param int $vote_id
 * @return object|null
 */
function fi_vote_get(int $vote_id): ?object {
global $wpdb;

$sql = "
SELECT v.*, s.name as session_name
FROM {$wpdb->prefix}fi_votes v
LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
WHERE v.id = %d 
LIMIT 1
";

return $wpdb->get_row($wpdb->prepare($sql, $vote_id));
}

/**
 * Get a single vote by Legiscan Rollcall ID
 * 
 * @param int $legiscan_rcid Legiscan rollcall ID
 * @param int|null $session_id Optional session ID for additional filtering
 * @return object|null
 */
function fi_vote_get_by_legiscan_rcid(int $legiscan_rcid, ?int $session_id = null): ?object {
global $wpdb;

$where_conditions = ['v.legiscan_rcid = %d'];
$where_values = [$legiscan_rcid];

if ($session_id) {
$where_conditions[] = 'v.session_id = %d';
$where_values[] = $session_id;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$sql = "
SELECT v.*, s.name as session_name
FROM {$wpdb->prefix}fi_votes v
LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
{$where_clause}
LIMIT 1
";

return $wpdb->get_row($wpdb->prepare($sql, $where_values));
}

/**
 * Get votes by session
 * 
 * @param int $session_id Session ID
 * @param array $filters Additional filters
 * @return array
 */
function fi_votes_get_by_session(int $session_id, array $filters = []): array {
$filters['session_id'] = $session_id;
return fi_votes_get($filters);
}

/**
 * Get votes by government
 * 
 * @param string $gov Government code
 * @param array $filters Additional filters
 * @return array
 */
function fi_votes_get_by_gov(string $gov, array $filters = []): array {
$filters['gov'] = $gov;
return fi_votes_get($filters);
}

/**
 * Get votes by tag
 * 
 * @param int $tag_id Tag ID
 * @param array $filters Additional filters
 * @return array
 */
function fi_votes_get_by_tag(int $tag_id, array $filters = []): array {
$filters['tag_id'] = $tag_id;
return fi_votes_get($filters);
}

/**
 * Get votes with rollcall data
 * 
 * @param array $args Filter arguments
 * @return array
 */
function fi_votes_get_with_rollcall(array $args = []): array {
$args['has_rollcall'] = true;
return fi_votes_get($args);
}

/**
 * Get votes for a specific legislator
 * 
 * @param int $legislator_id
 * @param array $args Optional filters
 * @return array
 */
function fi_votes_get_by_legislator(int $legislator_id, array $args = []): array {
global $wpdb;

$defaults = [
'session_id' => null,
'search' => null,
'status' => is_admin() ? null : 'publish',
'limit' => 50,
'offset' => 0,
'orderby' => 'date_voted',
'order' => 'DESC',
];

$args = wp_parse_args($args, $defaults);
$args['legislator_id'] = $legislator_id;

return fi_votes_get($args);
}
