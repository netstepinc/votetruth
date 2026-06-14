<?php
/*
 * Freedom Index Score Calculation and Management
 *
 * Straight function version of the former FICore\Scoring class file.
 *
 * Handles:
 * - Session score calculation, including child session votes via hierarchy.
 * - Freedom score calculation, aggregating top-level session scores.
 * - Bulk recalculation for admin UI.
 * - AJAX handlers for async processing.
 Refactored the scoring file into straight functions.

Key adjustments:

Removed the FICore\Scoring class/namespace wrapper.
Registered AJAX callbacks directly:
fi_score_ajax_calculate_scores()
fi_score_ajax_calculate_freedom_scores()
fi_score_ajax_calculate_gov_scores()
fi_score_ajax_get_score_stats()
Preserved the existing public API:
fi_score_calculate_all()
fi_score_calculate_session()
fi_score_calculate_freedom()
fi_score_calculate_grade()
fi_score_calculate_batch()
fi_score_calculate_average()
fi_score_calculate_average_by_party()
fi_score_calculate_average_by_chamber()
fi_score_get_stats()
score class/CSS helpers
Converted former private helpers into reusable functions:
fi_score_get_sessions_for_calculation()
fi_score_get_legislators_for_session()
fi_score_get_votes_for_session()
fi_score_get_rollcalls_bulk()
fi_score_calculate_from_votes()
fi_score_save_session()
Corrected the save_score_session()
 */

if (!defined('ABSPATH')) exit;

/**
 * Internal logging shim for scoring.
 *
 * @param string $message Log message.
 * @param string $file File path.
 * @param int $line Line number.
 * @param string $level Log level.
 * @return void
 */
function fi_score_log(string $message, string $file = '', int $line = 0, string $level = 'info'): void {
	// fi_log_area('score', $message, $file, $line, $level);
}

//Scoring function here to be shared with the API
//Standardize the score calc with pre-determined vote counts.
function fi_score_calc($votes_good, $votes_scored): int|null {
	if($votes_scored <= 0){
		return null;
	}
	$score_rounded = 0;
	if ($votes_scored > 0) {
		$score_percentage = ($votes_good / $votes_scored) * 100;
		$score_rounded = round($score_percentage, 0);
	}
	return $score_rounded;
}

/**
 * Initialize AJAX hooks for scoring tools.
 *
 * @return void
 */
function fi_score_init(): void {
	add_action('wp_ajax_fi_calculate_scores', 'fi_score_ajax_calculate_scores');
	add_action('wp_ajax_fi_calculate_freedom_scores', 'fi_score_ajax_calculate_freedom_scores');
	add_action('wp_ajax_fi_calculate_gov_scores', 'fi_score_ajax_calculate_gov_scores');
	add_action('wp_ajax_fi_get_score_stats', 'fi_score_ajax_get_score_stats');
}

add_action('init', function() {
	if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
		fi_score_init();
	}
}, 10);

/**
 * Recalculate scores for sessions and Freedom totals.
 *
 * @param string|null $gov Government code or null for all.
 * @param int|null $session_id Specific session ID or null.
 * @return int Total number of scores calculated.
 */
function fi_score_calculate_all(?string $gov = null, ?int $session_id = null): int {
	$calculated = 0;

	$sessions = fi_score_get_sessions_for_calculation($gov, $session_id);
	fi_score_log('calculate_scores_all:sessions: ' . wp_json_encode($sessions), __FILE__, __LINE__);

	if (empty($sessions)) {
		return 0;
	}

	foreach ($sessions as $session) {
		$sid = (int) $session['id'];
		if ($sid > 0) {
			$calculated += fi_score_calculate_session($sid);
		}
	}

	if ($calculated > 0) {
		fi_score_calculate_freedom($gov);
	}

	return $calculated;
}

/**
 * Recalculate scores for a single session.
 *
 * @param int $session_id Session ID.
 * @return int Number of scores calculated.
 */
function fi_score_calculate_session(int $session_id): int {
	global $wpdb;

	fi_score_log("  → calculate_scores_session({$session_id}) START", __FILE__, __LINE__);

	$session = $wpdb->get_row($wpdb->prepare(
		"SELECT id, gov FROM {$wpdb->prefix}fi_sessions WHERE id = %d",
		$session_id
	));

	if (!$session) {
		fi_score_log("  → Session {$session_id} not found", __FILE__, __LINE__);
		return 0;
	}

	fi_score_log("  → Session found: Gov={$session['gov']}", __FILE__, __LINE__);

	$legislators = fi_score_get_legislators_for_session($session_id);
	if (empty($legislators)) {
		fi_score_log("  → No legislators found for session {$session_id}", __FILE__, __LINE__);
		return 0;
	}

	fi_score_log('  → Found ' . count($legislators) . ' legislators', __FILE__, __LINE__);

	$by_chamber = ['H' => [], 'S' => []];
	foreach ($legislators as $leg) {
		$chamber = strtoupper($leg->chamber ?? '');
		if (isset($by_chamber[$chamber])) {
			$by_chamber[$chamber][] = $leg;
		}
	}

	$calculated = 0;

	foreach ($by_chamber as $chamber => $chamber_legislators) {
		if (empty($chamber_legislators)) {
			continue;
		}

		fi_score_log("  → Processing chamber: {$chamber} with " . count($chamber_legislators) . ' legislators', __FILE__, __LINE__);

		$votes = fi_score_get_votes_for_session($session_id, $chamber);
		if (empty($votes)) {
			fi_score_log("  → No votes found for chamber {$chamber}", __FILE__, __LINE__);
			continue;
		}

		fi_score_log("  → Found " . count($votes) . " votes for chamber {$chamber}", __FILE__, __LINE__);

		$unique_votes = [];
		foreach ($votes as $vote) {
			$unique_votes[(int) $vote['id']] = $vote;
		}
		$votes = array_values($unique_votes);

		$vote_ids = array_map(static fn($v) => (int) $v->id, $votes);
		$legislator_ids = array_map(static fn($l) => (int) $l->legislator_id, $chamber_legislators);

		fi_score_log('  → Bulk rollcall query: ' . count($legislator_ids) . ' legislators, ' . count($vote_ids) . ' votes', __FILE__, __LINE__);
		fi_score_log('  → Vote IDs sample (first 10): ' . wp_json_encode(array_slice($vote_ids, 0, 10)), __FILE__, __LINE__);
		fi_score_log('  → Legislator IDs sample (first 10): ' . wp_json_encode(array_slice($legislator_ids, 0, 10)), __FILE__, __LINE__);

		$rollcalls_lookup = fi_score_get_rollcalls_bulk($legislator_ids, $vote_ids);

		foreach ($chamber_legislators as $leg) {
			$legislator_id = (int) $leg->legislator_id;
			$ls_id = (int) $leg->ls_id;

			if ($legislator_id <= 0 || $ls_id <= 0) {
				continue;
			}

			$legislator_rollcalls = $rollcalls_lookup[$legislator_id] ?? [];
			fi_score_log("    → Legislator {$legislator_id}: " . count($legislator_rollcalls) . ' rollcalls', __FILE__, __LINE__);

			$score_data = fi_score_calculate_from_votes($votes, $legislator_rollcalls);
			fi_score_log('    → Score calculated: ' . ($score_data['score'] ?? 'N/A') . ' (scored: ' . ($score_data['scored'] ?? 0) . ')', __FILE__, __LINE__);

			if (($score_data['scored'] ?? 0) > 0) {
				$saved = fi_score_save_session($legislator_id, $session_id, $score_data);
				if ($saved) {
					$calculated++;
					fi_score_log("    → Score SAVED for legislator {$legislator_id}", __FILE__, __LINE__);
				} else {
					fi_score_log("    → Score SAVE FAILED for legislator {$legislator_id}", __FILE__, __LINE__, 'error');
				}
			} else {
				fi_score_log('    → No scored votes, skipping save', __FILE__, __LINE__);
			}
		}
	}

	fi_score_log("  → calculate_scores_session({$session_id}) END: {$calculated} scores saved", __FILE__, __LINE__);

	return $calculated;
}

/**
 * Recalculate Freedom scores for all legislators in a government.
 *
 * @param string|null $gov Government code or null for all.
 * @return int Number of Freedom scores updated.
 */
function fi_score_calculate_freedom(?string $gov = null): int {
	global $wpdb;

	$where_conditions = [];
	$params = [];

	if ($gov) {
		$where_conditions[] = 'gov = %s';
		$params[] = strtoupper($gov);
	}

	$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

	$sql = "
		SELECT DISTINCT legislator_id
		FROM {$wpdb->prefix}fi_legislator_sessions
		{$where_clause}
		ORDER BY legislator_id
	";

	$legislator_ids = !empty($params) ? $wpdb->get_col($wpdb->prepare($sql, ...$params)) : $wpdb->get_col($sql);
	if (empty($legislator_ids)) {
		return 0;
	}

	$updated = 0;
	foreach ($legislator_ids as $legislator_id) {
		$legislator_id = (int) $legislator_id;
		if ($legislator_id > 0) {
			fi_legislator_update_freedom_score($legislator_id);
			$updated++;
		}
	}

	return $updated;
}

/**
 * Get sessions for score calculation.
 *
 * Returns only top-level sessions. Child session votes are included during hierarchy lookup.
 *
 * @param string|null $gov Government code filter.
 * @param int|null $session_id Specific session ID filter.
 * @return array Session objects.
 */
function fi_score_get_sessions_for_calculation(?string $gov = null, ?int $session_id = null): array {
	global $wpdb;

	$where_conditions = ['parent_id IS NULL'];
	$params = [];

	if ($session_id) {
		$where_conditions[] = 'id = %d';
		$params[] = $session_id;
	} elseif ($gov) {
		$where_conditions[] = 'gov = %s';
		$params[] = strtoupper($gov);
	}

	$where_clause = implode(' AND ', $where_conditions);
	$sql = "
		SELECT id, gov, name
		FROM {$wpdb->prefix}fi_sessions
		WHERE {$where_clause}
		ORDER BY id DESC
	";

	$sessions = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, ...$params)) : $wpdb->get_results($sql);

	return $sessions ?: [];
}

/**
 * Get legislators assigned to a session.
 *
 * @param int $session_id Session ID.
 * @return array Objects with ls_id, legislator_id, chamber.
 */
function fi_score_get_legislators_for_session(int $session_id): array {
	global $wpdb;

	$legislators = $wpdb->get_results($wpdb->prepare(
		"SELECT id as ls_id, legislator_id, chamber
		FROM {$wpdb->prefix}fi_legislator_sessions
		WHERE session_id = %d",
		$session_id
	));

	return $legislators ?: [];
}

/**
 * Get votes for a session, hierarchy-aware and chamber-filtered.
 *
 * @param int $session_id Session ID.
 * @param string $chamber Chamber H or S.
 * @return array Vote objects with id, constitutional, chamber.
 */
function fi_score_get_votes_for_session(int $session_id, string $chamber): array {
	global $wpdb;

	$chamber = strtoupper($chamber);
	if (!in_array($chamber, ['H', 'S'], true)) {
		return [];
	}

	$session_ids = fi_sessions_get_hierarchy_ids($session_id);
	$session_ids = array_values(array_filter(array_map('absint', (array) $session_ids)));
	if (empty($session_ids)) {
		return [];
	}

	$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
	$params = array_merge($session_ids, [$chamber]);

	$votes = $wpdb->get_results($wpdb->prepare(
		"SELECT DISTINCT v.id, v.constitutional, v.chamber
		FROM {$wpdb->prefix}fi_votes v
		LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
		WHERE v.session_id IN ($placeholders)
			AND v.chamber = %s
			AND v.status = 'publish'
			AND s.status = 'publish'
			AND v.constitutional IN ('Y', 'N')
		ORDER BY v.id",
		...$params
	));

	return $votes ?: [];
}

/**
 * Get rollcalls in bulk for multiple legislators and votes.
 *
 * @param array $legislator_ids Legislator IDs.
 * @param array $vote_ids Vote IDs.
 * @return array Nested lookup [legislator_id][vote_id] => cast.
 */
function fi_score_get_rollcalls_bulk(array $legislator_ids, array $vote_ids): array {
	global $wpdb;

	$legislator_ids = array_values(array_filter(array_map('absint', $legislator_ids)));
	$vote_ids = array_values(array_filter(array_map('absint', $vote_ids)));

	if (empty($legislator_ids) || empty($vote_ids)) {
		return [];
	}

	$vote_placeholders = implode(',', array_fill(0, count($vote_ids), '%d'));
	$leg_placeholders = implode(',', array_fill(0, count($legislator_ids), '%d'));
	$params = array_merge($vote_ids, $legislator_ids);

	$rollcalls = $wpdb->get_results($wpdb->prepare(
		"SELECT vote_id, legislator_id, cast
		FROM {$wpdb->prefix}fi_voterc
		WHERE vote_id IN ($vote_placeholders)
			AND legislator_id IN ($leg_placeholders)",
		...$params
	));

	fi_score_log('  → get_rollcalls_bulk: Query returned ' . count($rollcalls ?: []) . ' rollcall records', __FILE__, __LINE__);
	if (empty($rollcalls)) {
		fi_score_log('  → WARNING: No rollcalls found for ' . count($legislator_ids) . ' legislators and ' . count($vote_ids) . ' votes', __FILE__, __LINE__, 'warning');
	}

	$lookup = [];
	foreach (($rollcalls ?: []) as $rc) {
		$leg_id = (int) $rc->legislator_id;
		$vote_id = (int) $rc->vote_id;

		if ($leg_id > 0 && $vote_id > 0) {
			if (!isset($lookup[$leg_id])) {
				$lookup[$leg_id] = [];
			}
			$lookup[$leg_id][$vote_id] = (string) $rc->cast;
		}
	}

	fi_score_log('  → get_rollcalls_bulk: Built lookup for ' . count($lookup) . ' legislators', __FILE__, __LINE__);

	return $lookup;
}

/**
 * Calculate score from votes and rollcalls.
 *
 * @param array $votes Vote objects with id and constitutional.
 * @param array $rollcalls Lookup [vote_id] => cast.
 * @return array Score data.
 */
function fi_score_calculate_from_votes(array $votes, array $rollcalls): array {
	$votes_total = 0;
	$votes_good = 0;
	$votes_bad = 0;
	$votes_not = 0;
	$votes_scored = 0;

	foreach ($votes as $vote) {
		$votes_total++;
		$vote_id = (int) $vote['id'];

		if (!isset($rollcalls[$vote_id])) {
			$votes_not++;
			continue;
		}

		$cast = strtoupper((string) $rollcalls[$vote_id]);

		if (in_array($cast, ['P', 'A', 'X'], true)) {
			$votes_not++;
			continue;
		}

		$votes_scored++;

		if ($cast === strtoupper((string) $vote['constitutional'])) {
			$votes_good++;
		} else {
			$votes_bad++;
		}
	}

	$score_rounded = 0;
	$grade = 'N/A';

	if ($votes_scored > 0) {
		$score_rounded = (int) round(($votes_good / $votes_scored) * 100, 0);
		$grade = fi_score_calculate_grade($score_rounded);
	}

	return [
		'score'  => $score_rounded,
		'grade'  => $grade,
		'total'  => $votes_total,
		'good'   => $votes_good,
		'bad'    => $votes_bad,
		'not'    => $votes_not,
		'scored' => $votes_scored,
	];
}

/**
 * Calculate letter grade from numeric score.
 *
 * @param float $score Numeric score 0-100.
 * @return string Letter grade.
 */
function fi_score_calculate_grade(float $score): string {
	if ($score >= 90) return 'A';
	if ($score >= 80) return 'B';
	if ($score >= 70) return 'C';
	if ($score >= 60) return 'D';
	return 'F';
}

/**
 * Get background class for score.
 *
 * @param int $score Numeric score.
 * @return string CSS class.
 */
function fi_score_class_bg(int $score): string {
	return fi_score_grade_class_bg(fi_score_calculate_grade($score));
}

/**
 * Get readable text class for score background.
 *
 * @param int $score Numeric score.
 * @return string CSS class.
 */
function fi_score_class_bg_text(int $score): string {
	return fi_score_grade_class_bg_text(fi_score_calculate_grade($score));
}

/**
 * Get text color class for score.
 *
 * @param int $score Numeric score.
 * @return string CSS class.
 */
function fi_score_class_text(int $score): string {
	return fi_score_grade_class_text(fi_score_calculate_grade($score));
}

/**
 * Get CSS variable for score.
 *
 * @param int $score Numeric score.
 * @return string CSS variable name.
 */
function fi_score_css_var(int $score): string {
	return fi_score_grade_css_var(fi_score_calculate_grade($score));
}

/**
 * Get grade background class.
 *
 * @param string $grade Letter grade.
 * @return string CSS class.
 */
function fi_score_grade_class_bg(string $grade): string {
	return 'fi-bg-' . strtolower($grade) . ' ';
}

/**
 * Get grade background text class.
 *
 * @param string $grade Letter grade.
 * @return string CSS class.
 */
function fi_score_grade_class_bg_text(string $grade): string {
	return 'fi-bg-text-' . strtolower($grade) . ' ';
}

/**
 * Get grade text class.
 *
 * @param string $grade Letter grade.
 * @return string CSS class.
 */
function fi_score_grade_class_text(string $grade): string {
	return 'fi-text-' . strtolower($grade) . ' ';
}

/**
 * Get grade CSS variable.
 *
 * @param string $grade Letter grade.
 * @return string CSS variable name.
 */
function fi_score_grade_css_var(string $grade): string {
	return '--fi-color-' . strtolower($grade);
}

/**
 * Calculate score from a batch of pre-formatted vote data.
 *
 * @param array $votes Vote data with good/constitutional and cast values.
 * @return int|null Score 0-100, or null if not enough votes counted.
 */
function fi_score_calculate_batch(array $votes): ?int {
	if (empty($votes)) {
		return 0;
	}

	$votes_good = 0;
	$votes_scored = 0;
	$total = count($votes);
	$half = (int) round($total / 2, 0);

	foreach ($votes as $vote) {
		$constitutional = is_object($vote)
			? ($vote['good'] ?? $vote['constitutional'] ?? '')
			: ($vote['good'] ?? $vote['constitutional'] ?? '');
		$cast = is_object($vote)
			? ($vote['cast'] ?? 'X')
			: ($vote['cast'] ?? 'X');

		$constitutional = strtoupper((string) $constitutional);
		$cast = strtoupper((string) $cast);

		if (!in_array($constitutional, ['Y', 'N'], true)) {
			continue;
		}

		if (!in_array($cast, ['Y', 'N'], true)) {
			continue;
		}

		$votes_scored++;

		if ($cast === $constitutional) {
			$votes_good++;
		}
	}

	if ($votes_scored >= $half) {
		return (int) round(($votes_good / $votes_scored) * 100, 0);
	}

	return null;
}

/**
 * Standard score calculation with predetermined vote counts.
 *
 * @param int|float $votes_good Good votes.
 * @param int|float $votes_scored Scored votes.
 * @return int|null
 */
function fi_score_calculate($votes_good, $votes_scored): int|null {
	return fi_score_calc($votes_good, $votes_scored);
}

/**
 * Save session score to database.
 *
 * @param int $legislator_id Legislator ID.
 * @param int $session_id Session ID.
 * @param array $score_data Score data.
 * @return bool True if saved successfully.
 */
function fi_score_save_session(int $legislator_id, int $session_id, array $score_data): bool {
	global $wpdb;

	$score_data_json = [
		'total'  => $score_data['total'] ?? 0,
		'good'   => $score_data['good'] ?? 0,
		'bad'    => $score_data['bad'] ?? 0,
		'not'    => $score_data['not'] ?? 0,
		'scored' => $score_data['scored'] ?? 0,
	];

	$score = $score_data['score'] ?? 0;
	$grade = $score_data['grade'] ?? 'N/A';

	fi_score_log("      → save_score_session: legislator={$legislator_id}, session={$session_id}, score={$score}, grade={$grade}", __FILE__, __LINE__);

	$updated = $wpdb->update(
		$wpdb->prefix . 'fi_legislator_sessions',
		[
			'score'      => $score,
			'score_data' => wp_json_encode($score_data_json),
		],
		[
			'legislator_id' => $legislator_id,
			'session_id'    => $session_id,
		],
		['%d', '%s'],
		['%d', '%d']
	);

	if ($updated === false) {
		fi_score_log('      → UPDATE FAILED: ' . $wpdb->last_error, __FILE__, __LINE__);
	} elseif ($updated === 0) {
		fi_score_log('      → UPDATE returned 0 (no rows changed - possibly same values)', __FILE__, __LINE__);
	} else {
		fi_score_log("      → UPDATE SUCCESS: {$updated} row(s) affected", __FILE__, __LINE__);
	}

	return $updated !== false;
}

/**
 * Calculate average score from array of legislators.
 *
 * @param array $legislators Legislator objects with score property.
 * @param string|null $filter_by Optional property filter.
 * @param string|null $filter_value Filter value.
 * @return array Average data.
 */
function fi_score_calculate_average(array $legislators, ?string $filter_by = null, ?string $filter_value = null): array {
	$sum = 0;
	$count = 0;

	foreach ($legislators as $legislator) {
		if ($filter_by && $filter_value) {
			$legislator_value = $legislator[$filter_by] ?? null;
			if ($legislator_value !== $filter_value) {
				continue;
			}
		}

		$score = (float) ($legislator['score'] ?? 0);
		if ($score > 0) {
			$sum += $score;
			$count++;
		}
	}

	return [
		'average' => $count > 0 ? round($sum / $count, 1) : 0,
		'count'   => $count,
		'sum'     => $sum,
	];
}

/**
 * Calculate average scores by party.
 *
 * @param array $legislators Legislator objects.
 * @return array Party averages.
 */
function fi_score_calculate_average_by_party(array $legislators): array {
	if (empty($legislators)) {
		return [];
	}

	$party_sums = [];
	$party_counts = [];

	foreach ($legislators as $leg) {
		$score = $leg->score ?? null;
		$party = $leg->party ?? '';

		if ($score === null || $score === '' || !$party) {
			continue;
		}

		if (!isset($party_sums[$party])) {
			$party_sums[$party] = 0;
			$party_counts[$party] = 0;
		}

		$party_sums[$party] += (float) $score;
		$party_counts[$party]++;
	}

	$result = [];
	foreach ($party_sums as $party => $sum) {
		$result[$party] = [
			'average' => $party_counts[$party] > 0 ? round($sum / $party_counts[$party], 0) : 0,
			'count'   => $party_counts[$party],
		];
	}

	return $result;
}

/**
 * Calculate average scores by chamber.
 *
 * @param array $legislators Legislator objects.
 * @return array Chamber averages.
 */
function fi_score_calculate_average_by_chamber(array $legislators): array {
	if (empty($legislators)) {
		return [];
	}

	$chamber_sums = [];
	$chamber_counts = [];

	foreach ($legislators as $leg) {
		$score = $leg->score ?? null;
		$chamber = $leg->chamber ?? '';

		if ($score === null || $score === '') {
			continue;
		}

		$normalized_chamber = ($chamber === 'R' || $chamber === 'H') ? 'H' : ($chamber === 'S' ? 'S' : '');
		if (!$normalized_chamber) {
			continue;
		}

		if (!isset($chamber_sums[$normalized_chamber])) {
			$chamber_sums[$normalized_chamber] = 0;
			$chamber_counts[$normalized_chamber] = 0;
		}

		$chamber_sums[$normalized_chamber] += (float) $score;
		$chamber_counts[$normalized_chamber]++;
	}

	$result = [];
	foreach ($chamber_sums as $chamber => $sum) {
		$result[$chamber] = [
			'average' => $chamber_counts[$chamber] > 0 ? round($sum / $chamber_counts[$chamber], 0) : 0,
			'count'   => $chamber_counts[$chamber],
		];
	}

	return $result;
}

/**
 * Get score statistics for a government or session.
 *
 * @param string|null $gov Government code.
 * @param int|null $session_id Session ID.
 * @return array Statistics array.
 */
function fi_score_get_stats(?string $gov = null, ?int $session_id = null): array {
	global $wpdb;

	$where_conditions = [];
	$params = [];

	if ($session_id) {
		$session_ids = fi_sessions_get_hierarchy_ids($session_id);
		$session_ids = array_values(array_filter(array_map('absint', (array) $session_ids)));
		if (!empty($session_ids)) {
			$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
			$where_conditions[] = "ls.session_id IN ($placeholders)";
			$params = array_merge($params, $session_ids);
		}
	} elseif ($gov) {
		$where_conditions[] = 'ls.gov = %s';
		$params[] = strtoupper($gov);
	}

	$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

	$sql = "SELECT
			COUNT(*) as total_scores,
			AVG(ls.score) as avg_score,
			MIN(ls.score) as min_score,
			MAX(ls.score) as max_score,
			SUM(CASE WHEN ls.score >= 90 THEN 1 ELSE 0 END) as grade_a,
			SUM(CASE WHEN ls.score >= 80 AND ls.score < 90 THEN 1 ELSE 0 END) as grade_b,
			SUM(CASE WHEN ls.score >= 70 AND ls.score < 80 THEN 1 ELSE 0 END) as grade_c,
			SUM(CASE WHEN ls.score >= 60 AND ls.score < 70 THEN 1 ELSE 0 END) as grade_d,
			SUM(CASE WHEN ls.score < 60 THEN 1 ELSE 0 END) as grade_f
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		{$where_clause}";

	$stats = $wpdb->get_row(!empty($params) ? $wpdb->prepare($sql, ...$params) : $sql, ARRAY_A);

	return $stats ?: [];
}

/**
 * AJAX: Recalculate scores for gov/session.
 *
 * @return void
 */
function fi_score_ajax_calculate_scores(): void {
	check_ajax_referer('fi_admin_nonce', 'nonce');
	fi_score_log('START: ajax_calculate_scores', __FILE__, __LINE__);

	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_send_json_error('Insufficient permissions');
	}

	$gov = sanitize_text_field($_POST['gov'] ?? '');
	$session_id = absint($_POST['session_id'] ?? 0);
	fi_score_log("gov: {$gov}, session_id: {$session_id}", __FILE__, __LINE__);

	$calculated = fi_score_calculate_all($gov ?: null, $session_id ?: null);
	fi_score_log("calculated: {$calculated}", __FILE__, __LINE__);

	$message = $session_id > 0
		? "Success! The scores for this session and freedom scores for this session's legislators have been updated."
		: 'Success! The scores for all sessions and freedom scores have been updated.';

	wp_send_json_success([
		'calculated' => $calculated,
		'message'    => $message,
	]);
}

/**
 * AJAX: Recalculate Freedom scores in batches.
 *
 * @return void
 */
function fi_score_ajax_calculate_freedom_scores(): void {
	check_ajax_referer('fi_admin_nonce', 'nonce');

	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_send_json_error('Insufficient permissions');
	}

	$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? ''));
	if ($gov === '') {
		wp_send_json_error('Missing gov');
	}

	$offset = max(0, absint($_POST['offset'] ?? 0));
	$limit_raw = absint($_POST['limit'] ?? 100);
	$limit = min(500, max(10, $limit_raw));

	global $wpdb;

	$total = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(DISTINCT legislator_id)
		FROM {$wpdb->prefix}fi_legislator_sessions
		WHERE gov = %s",
		$gov
	));

	if ($total <= 0) {
		wp_send_json_success([
			'gov'        => $gov,
			'total'      => 0,
			'processed'  => 0,
			'offset'     => $offset,
			'next_offset'=> 0,
			'done'       => true,
			'message'    => 'No legislators found for this government.',
		]);
	}

	$ids = $wpdb->get_col($wpdb->prepare(
		"SELECT DISTINCT legislator_id
		FROM {$wpdb->prefix}fi_legislator_sessions
		WHERE gov = %s
		ORDER BY legislator_id ASC
		LIMIT %d OFFSET %d",
		$gov,
		$limit,
		$offset
	));

	$processed = 0;
	foreach (($ids ?: []) as $legislator_id) {
		$legislator_id = (int) $legislator_id;
		if ($legislator_id > 0) {
			fi_legislator_update_freedom_score($legislator_id);
			$processed++;
		}
	}

	$next_offset = $offset + count(($ids ?: []));
	$done = $next_offset >= $total || empty($ids);

	wp_send_json_success([
		'gov'         => $gov,
		'total'       => $total,
		'processed'   => $processed,
		'offset'      => $offset,
		'next_offset' => $next_offset,
		'done'        => $done,
		'message'     => $done ? "Freedom scores updated ({$total})." : "Freedom score batch processed ({$processed}).",
	]);
}

/**
 * AJAX: Recalculate all session scores for a government in batches.
 *
 * @return void
 */
function fi_score_ajax_calculate_gov_scores(): void {
	check_ajax_referer('fi_admin_nonce', 'nonce');

	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_send_json_error('Insufficient permissions');
	}

	$gov = strtoupper(sanitize_text_field($_POST['gov'] ?? ''));
	if ($gov === '') {
		wp_send_json_error('Missing gov');
	}

	$offset = max(0, absint($_POST['offset'] ?? 0));
	$limit_raw = absint($_POST['limit'] ?? 1);
	$limit = min(10, max(1, $limit_raw));

	fi_score_log('=== AJAX CALCULATE GOV SCORES START ===', __FILE__, __LINE__);
	fi_score_log("Gov: {$gov}, Offset: {$offset}, Limit: {$limit}", __FILE__, __LINE__);

	$sessions = fi_score_get_sessions_for_calculation($gov);
	$total = count($sessions);

	fi_score_log("Total sessions found: {$total}", __FILE__, __LINE__);

	if ($total <= 0) {
		wp_send_json_success([
			'gov'                => $gov,
			'total_sessions'     => 0,
			'processed_sessions' => 0,
			'offset'             => $offset,
			'next_offset'        => 0,
			'done'               => true,
			'calculated'         => 0,
			'message'            => 'No sessions found for this government.',
		]);
	}

	$batch = array_slice($sessions, $offset, $limit);
	$calculated = 0;
	$processed_sessions = 0;
	$last_session_id = 0;

	fi_score_log('Processing batch: ' . count($batch) . ' sessions', __FILE__, __LINE__);

	foreach ($batch as $session) {
		$last_session_id = (int) ($session['id'] ?? 0);
		if ($last_session_id > 0) {
			fi_score_log('Processing session ID: ' . $last_session_id . ', Name: ' . ($session['name'] ?? 'N/A'), __FILE__, __LINE__);
			$session_calculated = fi_score_calculate_session($last_session_id);
			$calculated += $session_calculated;
			$processed_sessions++;
			fi_score_log("Session {$last_session_id} calculated {$session_calculated} scores", __FILE__, __LINE__);
		}
	}

	$next_offset = $offset + $processed_sessions;
	$done = $next_offset >= $total || empty($batch);

	fi_score_log('=== AJAX CALCULATE GOV SCORES END ===', __FILE__, __LINE__);
	fi_score_log('Next offset: ' . $next_offset . ', Done: ' . ($done ? 'YES' : 'NO'), __FILE__, __LINE__);

	wp_send_json_success([
		'gov'                => $gov,
		'total_sessions'     => $total,
		'processed_sessions' => $processed_sessions,
		'offset'             => $offset,
		'next_offset'        => $next_offset,
		'last_session_id'    => $last_session_id,
		'done'               => $done,
		'calculated'         => $calculated,
		'message'            => $done ? "Session scores updated ({$total})." : "Session score batch processed ({$processed_sessions}).",
	]);
}

/**
 * AJAX: Get score statistics.
 *
 * @return void
 */
function fi_score_ajax_get_score_stats(): void {
	check_ajax_referer('fi_admin_nonce', 'nonce');

	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_send_json_error('Insufficient permissions');
	}

	$gov = sanitize_text_field($_POST['gov'] ?? '');
	$session_id = absint($_POST['session_id'] ?? 0);

	wp_send_json_success(fi_score_get_stats($gov ?: null, $session_id ?: null));
}