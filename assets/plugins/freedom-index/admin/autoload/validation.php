<?php
/*
 * Freedom Index Admin Data Validation
 *
 * Straight function version of the former FIAdmin\DataValidation class file.
 *
 * Provides on-demand data validation and limited data repair tools for admin use.
 * Validation is intentionally not run automatically on every admin page because several
 * checks can be expensive on large imports.
 *
 * Notes:
 * - Slug-based checks were removed because the system is now ID-based.
 * - Expensive validation result sets are capped for safety.
 * - Fix actions are intentionally conservative and only delete clearly orphaned rows.
 * Refactored the admin data validation file into straight functions.
Key adjustments:
	Removed the FIAdmin\DataValidation class/namespace wrapper.
Added initialization:
	fi_admin_data_validation_init()
Converted validation methods:
	fi_admin_data_validation_validate_all()
	fi_admin_data_validation_validate_legislators()
	fi_admin_data_validation_validate_sessions()
	fi_admin_data_validation_validate_legislator_sessions()
	fi_admin_data_validation_validate_votes()
	fi_admin_data_validation_validate_roll_calls()
	fi_admin_data_validation_validate_scores()
	fi_admin_data_validation_validate_reports()
	fi_admin_data_validation_validate_user_lists()
Converted fix methods:
	fi_admin_data_validation_fix_data_issues()
	fi_admin_data_validation_fix_orphaned_legislators()
	fi_admin_data_validation_fix_missing_required_fields()
	fi_admin_data_validation_fix_orphaned_legislator_sessions()
	fi_admin_data_validation_fix_legislator_sessions_without_sessions()
	fi_admin_data_validation_fix_orphaned_votes()
	fi_admin_data_validation_fix_orphaned_roll_calls()
	fi_admin_data_validation_fix_invalid_legislator_roll_calls()
	fi_admin_data_validation_fix_duplicate_roll_calls()
	fi_admin_data_validation_fix_orphaned_reports()
	fi_admin_data_validation_fix_orphaned_lists()
Important cleanup:
	Removed vote/report/list slug validation checks.
	Replaced “votes without roll calls” check against fi_voterc with a cheaper rollcall_data check.
	Capped validation result payloads to avoid huge AJAX responses.

Fixed the multisite user-table bug in fix_orphaned_lists() by using:
	$wpdb->base_prefix . 'users'
Made score validation avoid duplicating orphaned legislator-session checks already handled elsewhere.
Added safe compatibility aliases:
	fi_data_validation_validate_all()
	fi_data_validation_get_summary()
	fi_data_validation_fix_data_issues()
*/

if (!defined('ABSPATH')) exit;

/**
 * Initialize data validation hooks.
 *
 * @return void
 */
function fi_admin_data_validation_init(): void {
	add_action('admin_notices', 'fi_admin_data_validation_show_notices');
	add_action('wp_ajax_fi_validate_data', 'fi_admin_data_validation_ajax_validate_data');
	add_action('wp_ajax_fi_fix_data_issues', 'fi_admin_data_validation_ajax_fix_data_issues');
}
add_action('plugins_loaded', 'fi_admin_data_validation_init');

/**
 * Standard validation issue row.
 *
 * @param string $type Issue type.
 * @param string $severity high|medium|low.
 * @param string $message Human-readable message.
 * @param array $data Issue data rows.
 * @param bool $fixable Whether issue has a repair action.
 * @param int|null $total_count Optional true total count when data is capped.
 * @return array Issue array.
 */
function fi_admin_data_validation_issue(string $type, string $severity, string $message, array $data, bool $fixable = false, ?int $total_count = null): array {
	return [
		'type'        => $type,
		'severity'    => $severity,
		'message'     => $message,
		'count'       => $total_count ?? count($data),
		'data'        => $data,
		'fixable'     => $fixable,
		'is_limited'  => ($total_count !== null && $total_count > count($data)),
	];
}

/**
 * Limit helper for validation scans.
 *
 * @param int $limit Requested limit.
 * @return int Safe limit.
 */
function fi_admin_data_validation_limit(int $limit = 100): int {
	return max(1, min(1000, absint($limit)));
}

/**
 * Run all validation checks.
 *
 * @param int $limit Max rows per issue payload.
 * @return array Issues.
 */
function fi_admin_data_validation_validate_all(int $limit = 100): array {
	$issues = [];

	$issues = array_merge($issues, fi_admin_data_validation_validate_legislators($limit));
	$issues = array_merge($issues, fi_admin_data_validation_validate_sessions($limit));
	$issues = array_merge($issues, fi_admin_data_validation_validate_legislator_sessions($limit));
	$issues = array_merge($issues, fi_admin_data_validation_validate_votes($limit));
	$issues = array_merge($issues, fi_admin_data_validation_validate_roll_calls($limit));
	$issues = array_merge($issues, fi_admin_data_validation_validate_scores($limit));
	$issues = array_merge($issues, fi_admin_data_validation_validate_reports($limit));
	$issues = array_merge($issues, fi_admin_data_validation_validate_user_lists($limit));

	return $issues;
}

/**
 * Validate legislators.
 *
 * @param int $limit Max rows per issue payload.
 * @return array Issues.
 */
function fi_admin_data_validation_validate_legislators(int $limit = 100): array {
	global $wpdb;

	$issues = [];
	$limit = fi_admin_data_validation_limit($limit);

	$total_orphaned = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_legislators l
		LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
		WHERE ls.legislator_id IS NULL"
	);

	if ($total_orphaned > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT l.id, l.display_name
			FROM {$wpdb->prefix}fi_legislators l
			LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
			WHERE ls.legislator_id IS NULL
			ORDER BY l.id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'orphaned_legislators',
			'high',
			'Legislators without sessions found',
			$data,
			true,
			$total_orphaned
		);
	}

	$total_missing = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_legislators
		WHERE first_name = '' OR first_name IS NULL OR last_name = '' OR last_name IS NULL OR display_name = '' OR display_name IS NULL"
	);

	if ($total_missing > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT id, display_name, first_name, last_name
			FROM {$wpdb->prefix}fi_legislators
			WHERE first_name = '' OR first_name IS NULL OR last_name = '' OR last_name IS NULL OR display_name = '' OR display_name IS NULL
			ORDER BY id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'missing_required_fields',
			'medium',
			'Legislators with missing required fields',
			$data,
			true,
			$total_missing
		);
	}

	return $issues;
}

/**
 * Validate sessions.
 *
 * @param int $limit Max rows per issue payload.
 * @return array Issues.
 */
function fi_admin_data_validation_validate_sessions(int $limit = 100): array {
	global $wpdb;

	$issues = [];
	$limit = fi_admin_data_validation_limit($limit);

	$total_empty = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_sessions s
		LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON s.id = ls.session_id
		WHERE ls.session_id IS NULL"
	);

	if ($total_empty > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT s.id, s.name, s.gov
			FROM {$wpdb->prefix}fi_sessions s
			LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON s.id = ls.session_id
			WHERE ls.session_id IS NULL
			ORDER BY s.gov ASC, s.id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'empty_sessions',
			'medium',
			'Sessions without legislators found',
			$data,
			false,
			$total_empty
		);
	}

	$total_overlaps = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_sessions s1
		INNER JOIN {$wpdb->prefix}fi_sessions s2 ON s1.gov = s2.gov AND s1.id < s2.id
		WHERE s1.date_start IS NOT NULL
		AND s1.date_end IS NOT NULL
		AND s2.date_start IS NOT NULL
		AND s2.date_end IS NOT NULL
		AND s1.date_start <= s2.date_end
		AND s1.date_end >= s2.date_start"
	);

	if ($total_overlaps > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT s1.id as session1_id, s1.name as session1_name, s2.id as session2_id, s2.name as session2_name, s1.gov
			FROM {$wpdb->prefix}fi_sessions s1
			INNER JOIN {$wpdb->prefix}fi_sessions s2 ON s1.gov = s2.gov AND s1.id < s2.id
			WHERE s1.date_start IS NOT NULL
			AND s1.date_end IS NOT NULL
			AND s2.date_start IS NOT NULL
			AND s2.date_end IS NOT NULL
			AND s1.date_start <= s2.date_end
			AND s1.date_end >= s2.date_start
			ORDER BY s1.gov ASC, s1.id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'overlapping_sessions',
			'low',
			'Overlapping sessions found',
			$data,
			false,
			$total_overlaps
		);
	}

	return $issues;
}

/**
 * Validate legislator sessions.
 *
 * @param int $limit Max rows per issue payload.
 * @return array Issues.
 */
function fi_admin_data_validation_validate_legislator_sessions(int $limit = 100): array {
	global $wpdb;

	$issues = [];
	$limit = fi_admin_data_validation_limit($limit);

	$total_orphaned_legislators = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
		WHERE l.id IS NULL"
	);

	if ($total_orphaned_legislators > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT ls.id, ls.legislator_id, ls.session_id
			FROM {$wpdb->prefix}fi_legislator_sessions ls
			LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
			WHERE l.id IS NULL
			ORDER BY ls.id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'orphaned_legislator_sessions',
			'high',
			'Legislator sessions without legislators found',
			$data,
			true,
			$total_orphaned_legislators
		);
	}

	$total_orphaned_sessions = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		LEFT JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		WHERE s.id IS NULL"
	);

	if ($total_orphaned_sessions > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT ls.id, ls.legislator_id, ls.session_id
			FROM {$wpdb->prefix}fi_legislator_sessions ls
			LEFT JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
			WHERE s.id IS NULL
			ORDER BY ls.id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'legislator_sessions_without_sessions',
			'high',
			'Legislator sessions without sessions found',
			$data,
			true,
			$total_orphaned_sessions
		);
	}

	$total_overlaps = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_legislator_sessions ls1
		INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls2 ON ls1.legislator_id = ls2.legislator_id AND ls1.id < ls2.id
		WHERE ls1.date_start IS NOT NULL
		AND ls1.date_end IS NOT NULL
		AND ls2.date_start IS NOT NULL
		AND ls2.date_end IS NOT NULL
		AND ls1.date_start <= ls2.date_end
		AND ls1.date_end >= ls2.date_start"
	);

	if ($total_overlaps > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT ls1.legislator_id, ls1.session_id as session1_id, ls2.session_id as session2_id
			FROM {$wpdb->prefix}fi_legislator_sessions ls1
			INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls2 ON ls1.legislator_id = ls2.legislator_id AND ls1.id < ls2.id
			WHERE ls1.date_start IS NOT NULL
			AND ls1.date_end IS NOT NULL
			AND ls2.date_start IS NOT NULL
			AND ls2.date_end IS NOT NULL
			AND ls1.date_start <= ls2.date_end
			AND ls1.date_end >= ls2.date_start
			ORDER BY ls1.legislator_id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'overlapping_legislator_sessions',
			'medium',
			'Overlapping legislator sessions found',
			$data,
			false,
			$total_overlaps
		);
	}

	return $issues;
}

/**
 * Validate votes.
 *
 * @param int $limit Max rows per issue payload.
 * @return array Issues.
 */
function fi_admin_data_validation_validate_votes(int $limit = 100): array {
	global $wpdb;

	$issues = [];
	$limit = fi_admin_data_validation_limit($limit);

	$total_orphaned = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_votes v
		LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
		WHERE s.id IS NULL"
	);

	if ($total_orphaned > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT v.id, v.title, v.session_id
			FROM {$wpdb->prefix}fi_votes v
			LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
			WHERE s.id IS NULL
			ORDER BY v.id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'orphaned_votes',
			'high',
			'Votes without sessions found',
			$data,
			true,
			$total_orphaned
		);
	}

	$total_without_roll = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_votes v
		WHERE v.rollcall_data IS NULL OR v.rollcall_data = '' OR v.rollcall_data = '[]' OR v.rollcall_data = '{}'"
	);

	if ($total_without_roll > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT v.id, v.title, v.session_id
			FROM {$wpdb->prefix}fi_votes v
			WHERE v.rollcall_data IS NULL OR v.rollcall_data = '' OR v.rollcall_data = '[]' OR v.rollcall_data = '{}'
			ORDER BY v.id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'votes_without_rollcall_data',
			'medium',
			'Votes without rollcall_data found',
			$data,
			false,
			$total_without_roll
		);
	}

	return $issues;
}

/**
 * Validate roll calls.
 *
 * @param int $limit Max rows per issue payload.
 * @return array Issues.
 */
function fi_admin_data_validation_validate_roll_calls(int $limit = 100): array {
	global $wpdb;

	$issues = [];
	$limit = fi_admin_data_validation_limit($limit);

	$total_orphaned_votes = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_voterc vr
		WHERE NOT EXISTS (
			SELECT 1 FROM {$wpdb->prefix}fi_votes v WHERE v.id = vr.vote_id
		)"
	);

	if ($total_orphaned_votes > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT vr.id, vr.vote_id, vr.legislator_id
			FROM {$wpdb->prefix}fi_voterc vr
			WHERE NOT EXISTS (
				SELECT 1 FROM {$wpdb->prefix}fi_votes v WHERE v.id = vr.vote_id
			)
			ORDER BY vr.id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'orphaned_roll_calls',
			'high',
			'Roll calls without votes found',
			$data,
			true,
			$total_orphaned_votes
		);
	}

	$total_invalid_legislators = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_voterc vr
		LEFT JOIN {$wpdb->prefix}fi_legislators l ON vr.legislator_id = l.id
		WHERE l.id IS NULL"
	);

	if ($total_invalid_legislators > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT vr.id, vr.vote_id, vr.legislator_id
			FROM {$wpdb->prefix}fi_voterc vr
			LEFT JOIN {$wpdb->prefix}fi_legislators l ON vr.legislator_id = l.id
			WHERE l.id IS NULL
			ORDER BY vr.id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'invalid_legislator_roll_calls',
			'high',
			'Roll calls with invalid legislators found',
			$data,
			true,
			$total_invalid_legislators
		);
	}

	$total_duplicates = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM (
			SELECT vote_id, legislator_id
			FROM {$wpdb->prefix}fi_voterc
			GROUP BY vote_id, legislator_id
			HAVING COUNT(*) > 1
		) d"
	);

	if ($total_duplicates > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT vote_id, legislator_id, COUNT(*) as count
			FROM {$wpdb->prefix}fi_voterc
			GROUP BY vote_id, legislator_id
			HAVING COUNT(*) > 1
			ORDER BY vote_id ASC, legislator_id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'duplicate_roll_calls',
			'medium',
			'Duplicate roll calls found',
			$data,
			true,
			$total_duplicates
		);
	}

	return $issues;
}

/**
 * Validate scores.
 *
 * @param int $limit Max rows per issue payload.
 * @return array Issues.
 */
function fi_admin_data_validation_validate_scores(int $limit = 100): array {
	global $wpdb;

	$issues = [];
	$limit = fi_admin_data_validation_limit($limit);

	$total_without_scores = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		WHERE ls.score IS NULL"
	);

	if ($total_without_scores > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT l.id, l.display_name, s.name as session_name, ls.session_id
			FROM {$wpdb->prefix}fi_legislators l
			INNER JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
			INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
			WHERE ls.score IS NULL
			ORDER BY s.gov ASC, s.id DESC, l.last_name ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'legislators_without_scores',
			'medium',
			'Legislator session records without scores found',
			$data,
			true,
			$total_without_scores
		);
	}

	return $issues;
}

/**
 * Validate reports.
 *
 * @param int $limit Max rows per issue payload.
 * @return array Issues.
 */
function fi_admin_data_validation_validate_reports(int $limit = 100): array {
	global $wpdb;

	$issues = [];
	$limit = fi_admin_data_validation_limit($limit);

	$total_orphaned = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_reports r
		LEFT JOIN {$wpdb->prefix}fi_sessions s ON r.session_id = s.id
		WHERE s.id IS NULL"
	);

	if ($total_orphaned > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT r.id, r.gov, r.session_id
			FROM {$wpdb->prefix}fi_reports r
			LEFT JOIN {$wpdb->prefix}fi_sessions s ON r.session_id = s.id
			WHERE s.id IS NULL
			ORDER BY r.id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'orphaned_reports',
			'medium',
			'Reports without sessions found',
			$data,
			true,
			$total_orphaned
		);
	}

	$reports = $wpdb->get_results($wpdb->prepare(
		"SELECT id, gov, payload_json
		FROM {$wpdb->prefix}fi_reports
		WHERE payload_json IS NOT NULL AND payload_json != ''
		ORDER BY id ASC
		LIMIT %d",
		$limit
	));

	$invalid = [];
	foreach ($reports as $report) {
		json_decode((string) $report->payload_json, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$invalid[] = $report;
		}
	}

	if (!empty($invalid)) {
		$issues[] = fi_admin_data_validation_issue(
			'invalid_json_reports',
			'medium',
			'Reports with invalid JSON found',
			$invalid,
			true
		);
	}

	return $issues;
}

/**
 * Validate user lists.
 *
 * @param int $limit Max rows per issue payload.
 * @return array Issues.
 */
function fi_admin_data_validation_validate_user_lists(int $limit = 100): array {
	global $wpdb;

	$issues = [];
	$limit = fi_admin_data_validation_limit($limit);

	$total_orphaned = (int) $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}fi_user_lists uc
		LEFT JOIN {$wpdb->base_prefix}users u ON uc.user_id = u.ID
		WHERE u.ID IS NULL"
	);

	if ($total_orphaned > 0) {
		$data = $wpdb->get_results($wpdb->prepare(
			"SELECT uc.id, uc.name, uc.user_id
			FROM {$wpdb->prefix}fi_user_lists uc
			LEFT JOIN {$wpdb->base_prefix}users u ON uc.user_id = u.ID
			WHERE u.ID IS NULL
			ORDER BY uc.id ASC
			LIMIT %d",
			$limit
		));

		$issues[] = fi_admin_data_validation_issue(
			'orphaned_lists',
			'medium',
			'Lists without users found',
			$data,
			true,
			$total_orphaned
		);
	}

	$lists = $wpdb->get_results($wpdb->prepare(
		"SELECT id, name, legislators
		FROM {$wpdb->prefix}fi_user_lists
		WHERE legislators IS NOT NULL AND legislators != ''
		ORDER BY id ASC
		LIMIT %d",
		$limit
	));

	$invalid = [];
	foreach ($lists as $list) {
		json_decode((string) $list->legislators, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$invalid[] = $list;
		}
	}

	if (!empty($invalid)) {
		$issues[] = fi_admin_data_validation_issue(
			'invalid_json_lists',
			'medium',
			'Lists with invalid JSON found',
			$invalid,
			true
		);
	}

	return $issues;
}

/**
 * Fix selected data issue types.
 *
 * @param array $issue_types Issue type strings.
 * @return int Number of rows fixed/deleted.
 */
function fi_admin_data_validation_fix_data_issues(array $issue_types): int {
	$fixed = 0;
	$issue_types = array_values(array_unique(array_map('sanitize_key', $issue_types)));

	foreach ($issue_types as $issue_type) {
		switch ($issue_type) {
			case 'orphaned_legislators':
				$fixed += fi_admin_data_validation_fix_orphaned_legislators();
				break;
			case 'missing_required_fields':
				$fixed += fi_admin_data_validation_fix_missing_required_fields();
				break;
			case 'orphaned_legislator_sessions':
			case 'orphaned_scores':
				$fixed += fi_admin_data_validation_fix_orphaned_legislator_sessions();
				break;
			case 'legislator_sessions_without_sessions':
			case 'sessions_without_sessions':
				$fixed += fi_admin_data_validation_fix_legislator_sessions_without_sessions();
				break;
			case 'orphaned_votes':
				$fixed += fi_admin_data_validation_fix_orphaned_votes();
				break;
			case 'orphaned_roll_calls':
				$fixed += fi_admin_data_validation_fix_orphaned_roll_calls();
				break;
			case 'invalid_legislator_roll_calls':
				$fixed += fi_admin_data_validation_fix_invalid_legislator_roll_calls();
				break;
			case 'duplicate_roll_calls':
				$fixed += fi_admin_data_validation_fix_duplicate_roll_calls();
				break;
			case 'orphaned_reports':
				$fixed += fi_admin_data_validation_fix_orphaned_reports();
				break;
			case 'orphaned_lists':
				$fixed += fi_admin_data_validation_fix_orphaned_lists();
				break;
		}
	}

	return $fixed;
}

/**
 * Delete legislators with no session records.
 *
 * @return int Rows affected.
 */
function fi_admin_data_validation_fix_orphaned_legislators(): int {
	global $wpdb;

	$result = $wpdb->query(
		"DELETE l FROM {$wpdb->prefix}fi_legislators l
		LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
		WHERE ls.legislator_id IS NULL"
	);

	return (int) $result;
}

/**
 * Fill missing display names where first/last names exist.
 *
 * This intentionally does not invent first/last names.
 *
 * @return int Rows affected.
 */
function fi_admin_data_validation_fix_missing_required_fields(): int {
	global $wpdb;

	$result = $wpdb->query(
		"UPDATE {$wpdb->prefix}fi_legislators
		SET display_name = TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))
		WHERE (display_name = '' OR display_name IS NULL)
		AND first_name IS NOT NULL AND first_name != ''
		AND last_name IS NOT NULL AND last_name != ''"
	);

	return (int) $result;
}

/**
 * Delete legislator session rows whose legislator no longer exists.
 *
 * @return int Rows affected.
 */
function fi_admin_data_validation_fix_orphaned_legislator_sessions(): int {
	global $wpdb;

	$result = $wpdb->query(
		"DELETE ls FROM {$wpdb->prefix}fi_legislator_sessions ls
		LEFT JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
		WHERE l.id IS NULL"
	);

	return (int) $result;
}

/**
 * Delete legislator session rows whose session no longer exists.
 *
 * @return int Rows affected.
 */
function fi_admin_data_validation_fix_legislator_sessions_without_sessions(): int {
	global $wpdb;

	$result = $wpdb->query(
		"DELETE ls FROM {$wpdb->prefix}fi_legislator_sessions ls
		LEFT JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		WHERE s.id IS NULL"
	);

	return (int) $result;
}

/**
 * Delete votes whose session no longer exists.
 *
 * @return int Rows affected.
 */
function fi_admin_data_validation_fix_orphaned_votes(): int {
	global $wpdb;

	$result = $wpdb->query(
		"DELETE v FROM {$wpdb->prefix}fi_votes v
		LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
		WHERE s.id IS NULL"
	);

	return (int) $result;
}

/**
 * Delete roll-call rows whose vote no longer exists.
 *
 * @return int Rows affected.
 */
function fi_admin_data_validation_fix_orphaned_roll_calls(): int {
	global $wpdb;

	$result = $wpdb->query(
		"DELETE vr FROM {$wpdb->prefix}fi_voterc vr
		LEFT JOIN {$wpdb->prefix}fi_votes v ON vr.vote_id = v.id
		WHERE v.id IS NULL"
	);

	return (int) $result;
}

/**
 * Delete roll-call rows whose legislator no longer exists.
 *
 * @return int Rows affected.
 */
function fi_admin_data_validation_fix_invalid_legislator_roll_calls(): int {
	global $wpdb;

	$result = $wpdb->query(
		"DELETE vr FROM {$wpdb->prefix}fi_voterc vr
		LEFT JOIN {$wpdb->prefix}fi_legislators l ON vr.legislator_id = l.id
		WHERE l.id IS NULL"
	);

	return (int) $result;
}

/**
 * Remove duplicate roll-call rows, keeping the lowest ID for each vote/legislator pair.
 *
 * @return int Rows affected.
 */
function fi_admin_data_validation_fix_duplicate_roll_calls(): int {
	global $wpdb;

	$result = $wpdb->query(
		"DELETE vr1 FROM {$wpdb->prefix}fi_voterc vr1
		INNER JOIN {$wpdb->prefix}fi_voterc vr2
			ON vr1.vote_id = vr2.vote_id
			AND vr1.legislator_id = vr2.legislator_id
			AND vr1.id > vr2.id"
	);

	return (int) $result;
}

/**
 * Delete reports whose session no longer exists.
 *
 * @return int Rows affected.
 */
function fi_admin_data_validation_fix_orphaned_reports(): int {
	global $wpdb;

	$result = $wpdb->query(
		"DELETE r FROM {$wpdb->prefix}fi_reports r
		LEFT JOIN {$wpdb->prefix}fi_sessions s ON r.session_id = s.id
		WHERE s.id IS NULL"
	);

	return (int) $result;
}

/**
 * Delete user lists whose user no longer exists.
 *
 * @return int Rows affected.
 */
function fi_admin_data_validation_fix_orphaned_lists(): int {
	global $wpdb;

	$result = $wpdb->query(
		"DELETE uc FROM {$wpdb->prefix}fi_user_lists uc
		LEFT JOIN {$wpdb->base_prefix}users u ON uc.user_id = u.ID
		WHERE u.ID IS NULL"
	);

	return (int) $result;
}

/**
 * Admin notices intentionally disabled by default.
 *
 * @return void
 */
function fi_admin_data_validation_show_notices(): void {
	return;
}

/**
 * AJAX handler for data validation.
 *
 * @return void
 */
function fi_admin_data_validation_ajax_validate_data(): void {
	check_ajax_referer('fi_admin_nonce', 'nonce');

	$cap = defined('FI_CAP_MANAGE') ? FI_CAP_MANAGE : 'manage_options';
	if (!current_user_can($cap)) {
		wp_send_json_error('Insufficient permissions');
	}

	$limit = isset($_POST['limit']) ? absint($_POST['limit']) : 100;
	$issues = fi_admin_data_validation_validate_all($limit);

	wp_send_json_success($issues);
}

/**
 * AJAX handler for fixing data issues.
 *
 * @return void
 */
function fi_admin_data_validation_ajax_fix_data_issues(): void {
	check_ajax_referer('fi_admin_nonce', 'nonce');

	$cap = defined('FI_CAP_MANAGE') ? FI_CAP_MANAGE : 'manage_options';
	if (!current_user_can($cap)) {
		wp_send_json_error('Insufficient permissions');
	}

	$issue_types_raw = $_POST['issue_types'] ?? [];
	if (!is_array($issue_types_raw)) {
		$issue_types_raw = [];
	}

	$issue_types = array_map(static function($value) {
		return sanitize_key(wp_unslash((string) $value));
	}, $issue_types_raw);

	$fixed = fi_admin_data_validation_fix_data_issues($issue_types);

	wp_send_json_success([
		'fixed'   => $fixed,
		'message' => "Fixed {$fixed} data issues",
	]);
}

/**
 * Get validation summary.
 *
 * @param int $limit Max rows per validation payload.
 * @return array Summary.
 */
function fi_admin_data_validation_get_summary(int $limit = 100): array {
	$issues = fi_admin_data_validation_validate_all($limit);

	$summary = [
		'total_issues'    => count($issues),
		'high_severity'   => 0,
		'medium_severity' => 0,
		'low_severity'    => 0,
		'fixable_issues'  => 0,
		'by_type'         => [],
	];

	foreach ($issues as $issue) {
		$severity_key = ($issue['severity'] ?? 'low') . '_severity';
		if (isset($summary[$severity_key])) {
			$summary[$severity_key]++;
		}

		if (!empty($issue['fixable'])) {
			$summary['fixable_issues']++;
		}

		$type = (string) ($issue['type'] ?? 'unknown');
		if (!isset($summary['by_type'][$type])) {
			$summary['by_type'][$type] = 0;
		}
		$summary['by_type'][$type]++;
	}

	return $summary;
}

/* -------------------------------------------------------------------------
 * Compatibility aliases for old shorter names, if templates/tools expect them.
 * ---------------------------------------------------------------------- */

function fi_data_validation_validate_all(int $limit = 100): array {
	return fi_admin_data_validation_validate_all($limit);
}

function fi_data_validation_get_summary(int $limit = 100): array {
	return fi_admin_data_validation_get_summary($limit);
}

function fi_data_validation_fix_data_issues(array $issue_types): int {
	return fi_admin_data_validation_fix_data_issues($issue_types);
}