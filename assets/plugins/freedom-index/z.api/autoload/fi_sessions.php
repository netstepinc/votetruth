<?php
// /api/autoload/fi_sessions.php
// Standalone helpers for fi_sessions (NO WP).
// Parent session is strictly parent_id IS NULL (FK constraint).

if (!defined('ABSPATH')) exit;


/**
 * Fetch all session reference information indexed by session id
 * and also provide a child-to-parent map (for special sessions).
 *
 * @param $fidb Medoo db instance
 * @param array $gov_filter Optionally limit to a set of govs (government codes)
 * @return array [$sessions_by_id, $child_to_parent]
 */
function fi_api_sessions($fidb, $gov_filter = null) {
	$where = [];
	if ($gov_filter && is_array($gov_filter)) {
		$where['gov'] = $gov_filter;
	}
	// No need to select every column—just the key session fields
	$sessions = $fidb->select(TB_SESSIONS, [
		'id',
		'gov',
		'name',
		'date_start',
		'date_end',
		'parent_id'
	], [
		// Don't limit, fetch all for small reference set
	]);
	$sessions_by_id = [];
	$child_to_parent = [];
	if (is_array($sessions)) {
		foreach ($sessions as $sess) {
			$sessions_by_id[$sess['id']] = $sess;
			if (!empty($sess['parent_id'])) {
				$child_to_parent[$sess['id']] = $sess['parent_id'];
			}
		}
	}
	return [$sessions_by_id, $child_to_parent];
}


/**
 * Returns most recent parent session for a gov:
 * ORDER: date_end DESC, fallback id DESC.
 */
function fi_api_session_parent_latest(string $gov): ?array {
	global $fidb;
	if (!$fidb) return null;

	$gov = strtoupper(trim($gov));
	if ($gov === '') return null;

	$row = $fidb->get(TB_SESSIONS, [
		'id',
		'gov',
		'parent_id',
		'name',
		'date_start',
		'date_end',
		'is_current',
	], [
		'gov' => $gov,
		'parent_id' => null,
		'ORDER' => [
			'date_end' => 'DESC',
			'id' => 'DESC',
		],
		'LIMIT' => 1,
	]);

	return $row ?: null;
}

/**
 * Returns parent sessions list (optionally limited),
 * ordered by date_end DESC then id DESC.
 */
function fi_api_sessions_parent_list(string $gov, int $limit = 50): array {
	global $fidb;
	if (!$fidb) return [];

	$gov = strtoupper(trim($gov));
	if ($gov === '') return [];

	$limit = max(1, min(500, $limit));

	$rows = $fidb->select(TB_SESSIONS, [
		'id',
		'gov',
		'parent_id',
		'name',
		'date_start',
		'date_end',
		'is_current',
	], [
		'gov' => $gov,
		'parent_id' => null,
		'ORDER' => [
			'date_end' => 'DESC',
			'id' => 'DESC',
		],
		'LIMIT' => $limit,
	]);

	return is_array($rows) ? $rows : [];
}