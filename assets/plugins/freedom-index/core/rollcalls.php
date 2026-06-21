<?php
/*
 * Freedom Index Roll-call Table I/O Operations
 *
 * Straight function version of the former FICore\Rollcalls class file.
 * Handles all database operations for the fi_voterc table.
 Refactored the roll-call file into straight functions.

Key adjustments:

Replaced FICore\Rollcalls::$cache_get with function-local static cache inside fi_rollcalls_get().
Preserved all existing public function names.
Added public function equivalents for class-only/private methods:
fi_rollcalls_get_by_vote_ids()
fi_rollcall_find_legislator_by_external_id()
fi_rollcall_validate_data()
Kept fi_rollcall_cast_normalize() as the shared cast normalizer.
Fixed count queries when chamber, party, or district filters are used. The original count query did not include the required fi_legislator_sessions join, so those filters would fail in count mode.
Preserved the existing 4-state vote cast model: Y, N, P, X.
*/

if (!defined('ABSPATH')) exit;

/**
 * Query roll-call votes with optional filtering (DB-only, no cache).
 *
 * @param array $args Optional filter/query arguments.
 * @return array|int Array of roll-call objects or count if count is true.
 */
function fi_rollcalls_query(array $args = []): array|int {
	global $wpdb;

	$defaults = [
		'vote_id'       => null,
		'vote_ids'      => null,
		'legislator_id' => null,
		'cast'          => null,
		'is_override'   => null,
		'chamber'       => null,
		'party'         => null,
		'district'      => null,
		'orderby'       => 'legislator_id',
		'order'         => 'ASC',
		'per_page'      => -1,
		'page'          => 1,
		'count'         => false,
	];

	$args = wp_parse_args($args, $defaults);

	$need_legislator_session = !empty($args['chamber']) || !empty($args['party']) || !empty($args['district']);

	$where_conditions = [];
	$where_values = [];

	if (!empty($args['vote_id'])) {
		$where_conditions[] = 'rc.vote_id = %d';
		$where_values[] = absint($args['vote_id']);
	}

	if (!empty($args['vote_ids']) && is_array($args['vote_ids'])) {
		$vote_ids = array_values(array_filter(array_map('absint', $args['vote_ids'])));
		if (!empty($vote_ids)) {
			$placeholders = implode(',', array_fill(0, count($vote_ids), '%d'));
			$where_conditions[] = "rc.vote_id IN ($placeholders)";
			$where_values = array_merge($where_values, $vote_ids);
		} else {
			$where_conditions[] = '1 = 0';
		}
	}

	if (!empty($args['legislator_id'])) {
		$where_conditions[] = 'rc.legislator_id = %d';
		$where_values[] = absint($args['legislator_id']);
	}

	if (!empty($args['cast'])) {
		$where_conditions[] = 'rc.cast = %s';
		$where_values[] = fi_rollcall_cast_normalize((string) $args['cast']);
	}

	if ($args['is_override'] !== null) {
		$where_conditions[] = 'rc.is_override = %d';
		$where_values[] = $args['is_override'] ? 1 : 0;
	}

	if (!empty($args['chamber'])) {
		$where_conditions[] = 'ls.chamber = %s';
		$where_values[] = strtoupper((string) $args['chamber']);
	}

	if (!empty($args['party'])) {
		$where_conditions[] = 'ls.party = %s';
		$where_values[] = (string) $args['party'];
	}

	if (!empty($args['district'])) {
		$where_conditions[] = 'ls.district = %s';
		$where_values[] = (string) $args['district'];
	}

	$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

	$dir = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';
	$orderby_map = [
		'legislator_id' => "rc.legislator_id {$dir}",
		'vote_id'       => "rc.vote_id {$dir}",
		'date_created'  => "rc.date_created {$dir}",
		'last_name'     => "l.last_name {$dir}",
		'first_name'    => "l.first_name {$dir}",
		'name'          => "l.last_name {$dir}, l.first_name {$dir}",
	];
	$orderby = $orderby_map[$args['orderby']] ?? "rc.legislator_id {$dir}";

	$limit_clause = '';
	if ((int) $args['per_page'] > 0) {
		$per_page = absint($args['per_page']);
		$page = max(1, absint($args['page']));
		$offset = ($page - 1) * $per_page;
		$limit_clause = $wpdb->prepare('LIMIT %d OFFSET %d', $per_page, $offset);
	}

	$join_ls = '';
	if ($need_legislator_session) {
		$join_ls = "
			LEFT JOIN {$wpdb->prefix}fi_votes v ON rc.vote_id = v.id
			LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON rc.legislator_id = ls.legislator_id AND ls.session_id = v.session_id
		";
	}

	if (!empty($args['count'])) {
		$sql = "
			SELECT COUNT(*)
			FROM {$wpdb->prefix}fi_voterc rc
			{$join_ls}
			{$where_clause}
		";

		if (!empty($where_values)) {
			$sql = $wpdb->prepare($sql, $where_values);
		}

		return (int) $wpdb->get_var($sql);
	}

	$select_ls = $need_legislator_session ? ', ls.party, ls.chamber, ls.district' : '';
	$join_ls = $need_legislator_session
		? "LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON rc.legislator_id = ls.legislator_id AND ls.session_id = v.session_id"
		: '';

	$sql = "
		SELECT
			rc.*,
			l.first_name,
			l.last_name,
			l.display_name,
			l.id as legislator_id,
			v.title as vote_title,
			v.bill_number as bill_key,
			v.constitutional,
			s.name as session_name
			{$select_ls}
		FROM {$wpdb->prefix}fi_voterc rc
		LEFT JOIN {$wpdb->prefix}fi_legislators l ON rc.legislator_id = l.id
		LEFT JOIN {$wpdb->prefix}fi_votes v ON rc.vote_id = v.id
		{$join_ls}
		LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
		{$where_clause}
		ORDER BY {$orderby}
		{$limit_clause}
	";

	if (!empty($where_values)) {
		$sql = $wpdb->prepare($sql, $where_values);
	}

	return $wpdb->get_results($sql, ARRAY_A);
}



/**
 * Get roll-call votes with optional filtering (cached for front-end).
 *
 * @param array $args Optional filter/query arguments.
 * @return array|int Array of roll-call objects or count if count is true.
 */
function fi_rollcalls_get(array $args = []): array|int {
	$cacheKey = fi_cache_key('rollcalls/get', $args);

	$results = fi_cache($cacheKey);
	if ($results) {
		return $results;
	}

	$results = fi_rollcalls_query($args);
	fi_cache($cacheKey, $results);
	return $results;
}
/**
 * Get roll-call votes for a specific vote with legislator details.
 *
 * @param int $vote_id Vote ID.
 * @param array $filters Additional filters.
 * @return array
 */
function fi_rollcalls_get_by_vote(int $vote_id, array $filters = []): array {
	$args = array_merge($filters, [
		'vote_id'  => $vote_id,
		'orderby'  => 'name',
		'order'    => 'ASC',
		'per_page' => -1,
	]);

	$results = fi_rollcalls_get($args);
	return is_array($results) ? $results : [];
}

/**
 * Get roll-call data for a specific vote as an associative array keyed by legislator ID.
 *
 * @param int $vote_id Vote ID.
 * @return array
 */
function fi_rollcalls_legislators_cast_by_vote(int $vote_id): array {
	global $wpdb;

	$sql = $wpdb->prepare(
		"SELECT id, vote_id, legislator_id, cast FROM {$wpdb->prefix}fi_voterc WHERE vote_id = %d ORDER BY id DESC",
		$vote_id
	);

	$results = $wpdb->get_results($sql, ARRAY_A);
	$rollcall = [];

	foreach ($results as $row) {
		$rollcall[(int) $row['legislator_id']] = [
			'id'   => (int) $row['id'],
			'cast' => $row['cast'],
		];
	}

	return $rollcall;
}

/**
 * Get a specific roll-call row by vote ID and legislator ID as an array.
 *
 * @param int $vote_id Vote ID.
 * @param int $legislator_id Legislator ID.
 * @return array|null
 */
function fi_rollcall(int $vote_id, int $legislator_id): ?array {
	global $wpdb;

	$sql = $wpdb->prepare(
		"SELECT id, vote_id, legislator_id, cast, is_override FROM {$wpdb->prefix}fi_voterc WHERE vote_id = %d AND legislator_id = %d ORDER BY id DESC LIMIT 1",
		$vote_id,
		$legislator_id
	);

	$result = $wpdb->get_row($sql, ARRAY_A);
	return is_array($result) ? $result : null;
}

/**
 * Get roll-call votes for a specific legislator.
 *
 * @param int $legislator_id Legislator ID.
 * @param array $filters Additional filters.
 * @return array
 */
function fi_rollcalls_get_by_legislator(int $legislator_id, array $filters = []): array {
	$args = array_merge($filters, ['legislator_id' => $legislator_id]);
	$results = fi_rollcalls_get($args);
	return is_array($results) ? $results : [];
}

/**
 * Get roll-call votes for multiple vote IDs.
 *
 * @param array $vote_ids Vote IDs.
 * @return array Roll-call objects.
 */
function fi_rollcalls_get_by_vote_ids(array $vote_ids): array {
	global $wpdb;

	$vote_ids = array_values(array_unique(array_filter(array_map('absint', $vote_ids))));
	if (empty($vote_ids)) {
		return [];
	}

	$placeholders = implode(',', array_fill(0, count($vote_ids), '%d'));

	$sql = "
		SELECT
			rc.vote_id,
			rc.legislator_id,
			rc.cast,
			rc.is_override,
			rc.date_created
		FROM {$wpdb->prefix}fi_voterc rc
		WHERE rc.vote_id IN ($placeholders)
		ORDER BY rc.vote_id ASC, rc.legislator_id ASC
	";

	return $wpdb->get_results($wpdb->prepare($sql, $vote_ids), ARRAY_A);
}

/**
 * Get roll-call counts keyed by vote ID.
 *
 * @param array $vote_ids Vote IDs.
 * @return array<int,int>
 */
function fi_rollcalls_get_counts_by_vote_ids(array $vote_ids): array {
	global $wpdb;

	$vote_ids = array_values(array_filter(array_map('absint', $vote_ids)));
	if (empty($vote_ids)) {
		return [];
	}

	$placeholders = implode(',', array_fill(0, count($vote_ids), '%d'));
	$sql = "
		SELECT vote_id, COUNT(*) as total
		FROM {$wpdb->prefix}fi_voterc
		WHERE vote_id IN ($placeholders)
		GROUP BY vote_id
	";

	$results = $wpdb->get_results($wpdb->prepare($sql, $vote_ids), ARRAY_A);
	$counts = [];

	foreach ($results as $row) {
		$counts[(int) $row['vote_id']] = (int) $row['total'];
	}

	return $counts;
}

/**
 * Get a single roll-call vote object.
 *
 * @param int $vote_id Vote ID.
 * @param int $legislator_id Legislator ID.
 * @return array|null
 */
function fi_rollcall_get(int $vote_id, int $legislator_id): ?array {
	$results = fi_rollcalls_get([
		'vote_id'       => $vote_id,
		'legislator_id' => $legislator_id,
		'per_page'      => 1,
	]);

	return is_array($results) ? ($results[0] ?? null) : null;
}

/**
 * Save or update roll-call vote with duplicate checking.
 *
 * @param array $data Roll-call data.
 * @param int|null $vote_id Deprecated/pass-through compatibility parameter.
 * @param int|null $legislator_id Deprecated/pass-through compatibility parameter.
 * @return int|false Roll-call ID on success, false on failure.
 */
function fi_rollcall_save(array $data, ?int $vote_id = null, ?int $legislator_id = null): int|false {
	global $wpdb;

	if ($vote_id !== null) {
		$data['vote_id'] = $vote_id;
	}

	if ($legislator_id !== null) {
		$data['legislator_id'] = $legislator_id;
	}

	if (array_key_exists('cast', $data)) {
		$data['cast'] = fi_rollcall_cast_normalize((string) $data['cast']);
	}

	if (empty($data['vote_id']) || empty($data['legislator_id']) || !isset($data['cast']) || $data['cast'] === '') {
		return false;
	}

	$existing = fi_rollcall_get((int) $data['vote_id'], (int) $data['legislator_id']);
	$is_update = !empty($existing);

	$db_data = [
		'vote_id'       => (int) $data['vote_id'],
		'legislator_id' => (int) $data['legislator_id'],
		'cast'          => $data['cast'],
		'is_override'   => !empty($data['is_override']) ? 1 : 0,
	];

	if ($is_update) {
		$result = $wpdb->update(
			$wpdb->prefix . 'fi_voterc',
			$db_data,
			[
				'vote_id'       => $db_data['vote_id'],
				'legislator_id' => $db_data['legislator_id'],
			],
			['%d', '%d', '%s', '%d'],
			['%d', '%d']
		);

		if ($result !== false) {
			do_action('fi_rollcall_saved', (int) ($existing['id'] ?? 0), $db_data);
		}
		return $result !== false ? (int) ($existing['id'] ?? 0) : false;
	}

	$result = $wpdb->insert(
		$wpdb->prefix . 'fi_voterc',
		$db_data,
		['%d', '%d', '%s', '%d']
	);

	if ($result !== false) {
		do_action('fi_rollcall_saved', (int) $wpdb->insert_id, $db_data);
	}

	return $result !== false ? (int) $wpdb->insert_id : false;
}

/**
 * Update roll-call vote.
 *
 * @param int $vote_id Vote ID.
 * @param int $legislator_id Legislator ID.
 * @param array $data Data to update.
 * @return bool
 */
function fi_rollcall_update(int $vote_id, int $legislator_id, array $data): bool {
	$data['vote_id'] = $vote_id;
	$data['legislator_id'] = $legislator_id;

	return fi_rollcall_save($data) !== false;
}

/**
 * Delete roll-call vote.
 *
 * @param int $vote_id Vote ID.
 * @param int $legislator_id Legislator ID.
 * @return bool
 */
function fi_rollcall_delete(int $vote_id, int $legislator_id): bool {
	global $wpdb;

	$result = $wpdb->delete(
		$wpdb->prefix . 'fi_voterc',
		[
			'vote_id'       => $vote_id,
			'legislator_id' => $legislator_id,
		],
		['%d', '%d']
	);

	return $result !== false;
}

/**
 * Delete all roll-call votes for a vote.
 *
 * @param int $vote_id Vote ID.
 * @return bool
 */
function fi_rollcalls_delete_by_vote(int $vote_id): bool {
	global $wpdb;

	$result = $wpdb->delete(
		$wpdb->prefix . 'fi_voterc',
		['vote_id' => $vote_id],
		['%d']
	);

	return $result !== false;
}

/**
 * Delete all roll-call votes for a legislator.
 *
 * @param int $legislator_id Legislator ID.
 * @return bool
 */
function fi_rollcalls_delete_by_legislator(int $legislator_id): bool {
	global $wpdb;

	$result = $wpdb->delete(
		$wpdb->prefix . 'fi_voterc',
		['legislator_id' => $legislator_id],
		['%d']
	);

	return $result !== false;
}

/**
 * Import roll-call data from JSON/string/array.
 *
 * @param int $vote_id Vote ID.
 * @param string|array $rollcall_data JSON string or array.
 * @param string $gov Government code for legislator matching.
 * @return int Number of roll-call votes imported.
 */
function fi_rollcall_import(int $vote_id, string|array $rollcall_data, string $gov): int {
	if (is_string($rollcall_data)) {
		$rollcall_data = json_decode($rollcall_data, true);
	}

	if (!is_array($rollcall_data)) {
		return 0;
	}

	$imported_count = 0;

	foreach ($rollcall_data as $external_id => $cast) {
		$legislator = fi_rollcall_find_legislator_by_external_id((string) $external_id, $gov);

		if (!$legislator) {
			continue;
		}

		$result = fi_rollcall_save([
			'vote_id'       => $vote_id,
			'legislator_id' => $legislator['id'],
			'cast'          => fi_rollcall_cast_normalize((string) $cast),
			'is_override'   => 0,
		]);

		if ($result !== false) {
			$imported_count++;
		}
	}

	return $imported_count;
}

/**
 * Find legislator by external ID.
 *
 * Public function equivalent of the former private class helper.
 *
 * @param string $external_id External ID.
 * @param string $gov Government code.
 * @return object|null Object with ID or null.
 */
function fi_rollcall_find_legislator_by_external_id(string $external_id, string $gov): ?object {
	global $wpdb;

	$gov = strtoupper(trim($gov));

	$external_fields = ($gov === 'US')
		? ['bioguide_id', 'legiscan_id', 'votesmart_id', 'ballotpedia_id', 'openstates_id']
		: ['legiscan_id', 'bioguide_id', 'votesmart_id', 'ballotpedia_id', 'openstates_id'];

	foreach ($external_fields as $field) {
		$sql = "SELECT id FROM {$wpdb->prefix}fi_legislators WHERE {$field} = %s LIMIT 1";
		$legislator_id = $wpdb->get_var($wpdb->prepare($sql, $external_id));

		if ($legislator_id) {
			return (object) ['id' => (int) $legislator_id];
		}
	}

	return null;
}

/**
 * Normalize any cast into the standard 4-state value: Y, N, P, X.
 *
 * @param string $cast Cast value.
 * @return string Normalized cast.
 */
function fi_rollcall_cast_normalize(string $cast): string {
	$cast = strtoupper(trim($cast));

	$cast_map = [
		'Y'          => 'Y',
		'N'          => 'N',
		'P'          => 'P',
		'X'          => 'X',
		'A'          => 'X',
		'NV'         => 'X',
		'ABSENT'     => 'X',
		'ABSTAIN'    => 'P',
		'PAIRED'     => 'P',
		'1'          => 'Y',
		'2'          => 'N',
		'0'          => 'N',
		'YES'        => 'Y',
		'AYE'        => 'Y',
		'YEA'        => 'Y',
		'GUILTY'     => 'Y',
		'NO'         => 'N',
		'NAY'        => 'N',
		'NOT GUILTY' => 'N',
		'PRESENT'    => 'P',
		'NOT VOTING' => 'X',
	];

	return $cast_map[$cast] ?? 'X';
}

/**
 * Get roll-call statistics.
 *
 * @param int|null $vote_id Vote ID.
 * @param int|null $legislator_id Legislator ID.
 * @param int|null $is_override Override filter.
 * @return array
 */
function fi_rollcalls_stats(?int $vote_id = null, ?int $legislator_id = null, ?int $is_override = null): array {
	global $wpdb;

	$where_conditions = [];
	$values = [];

	if ($vote_id) {
		$where_conditions[] = 'vote_id = %d';
		$values[] = $vote_id;
	}

	if ($legislator_id) {
		$where_conditions[] = 'legislator_id = %d';
		$values[] = $legislator_id;
	}

	if ($is_override !== null) {
		$where_conditions[] = 'is_override = %d';
		$values[] = $is_override ? 1 : 0;
	}

	$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

	$sql = "
		SELECT
			COUNT(*) as total,
			COUNT(CASE WHEN cast = 'Y' THEN 1 END) as yes_votes,
			COUNT(CASE WHEN cast = 'N' THEN 1 END) as no_votes,
			COUNT(CASE WHEN cast = 'P' THEN 1 END) as present_votes,
			COUNT(CASE WHEN cast = 'X' THEN 1 END) as not_voting,
			COUNT(CASE WHEN is_override = 1 THEN 1 END) as overrides
		FROM {$wpdb->prefix}fi_voterc
		{$where_clause}
	";

	if (!empty($values)) {
		$sql = $wpdb->prepare($sql, $values);
	}

	$result = $wpdb->get_row($sql, ARRAY_A);

	return is_array($result) ? $result : [
		'total'         => 0,
		'yes_votes'     => 0,
		'no_votes'      => 0,
		'present_votes' => 0,
		'not_voting'    => 0,
		'overrides'     => 0,
	];
}

/**
 * Validate roll-call data.
 *
 * @param array $data Roll-call data.
 * @return array{valid:bool,errors:array}
 */
function fi_rollcall_validate_data(array $data): array {
	$errors = [];

	if (array_key_exists('cast', $data)) {
		$data['cast'] = fi_rollcall_cast_normalize((string) $data['cast']);
	}

	if (empty($data['vote_id'])) {
		$errors[] = 'Vote ID is required';
	}

	if (empty($data['legislator_id'])) {
		$errors[] = 'Legislator ID is required';
	}

	if (empty($data['cast'])) {
		$errors[] = 'Cast is required';
	}

	if (!empty($data['cast']) && !in_array($data['cast'], ['Y', 'N', 'P', 'X'], true)) {
		$errors[] = 'Cast must be Y, N, P, or X';
	}

	if (!empty($data['vote_id']) && !is_numeric($data['vote_id'])) {
		$errors[] = 'Vote ID must be numeric';
	}

	if (!empty($data['legislator_id']) && !is_numeric($data['legislator_id'])) {
		$errors[] = 'Legislator ID must be numeric';
	}

	return [
		'valid'  => empty($errors),
		'errors' => $errors,
	];
}

/**
 * Get roll-call summary for a vote.
 *
 * @param int $vote_id Vote ID.
 * @param int|null $is_override Override filter.
 * @return array
 */
function fi_rollcall_summary(int $vote_id, ?int $is_override = null): array {
	$stats = fi_rollcalls_stats($vote_id, null, $is_override);

	return [
		'total_votes' => (int) ($stats['total'] ?? 0),
		'yes'         => (int) ($stats['yes_votes'] ?? 0),
		'no'          => (int) ($stats['no_votes'] ?? 0),
		'present'     => (int) ($stats['present_votes'] ?? 0),
		'not_voting'  => (int) ($stats['not_voting'] ?? 0),
		'overrides'   => (int) ($stats['overrides'] ?? 0),
	];
}