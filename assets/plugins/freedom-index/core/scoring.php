<?php
namespace FI\Core{

	if (!defined('ABSPATH')) exit;

	/**
	 * Score Calculation and Management
	 * 
	 * Handles all legislator scoring operations:
	 * - Session score calculation (includes child session votes via hierarchy)
	 * - Freedom score calculation (aggregates top-level session scores)
	 * - Bulk recalculation for admin UI
	 * - AJAX handlers for async processing
	 * 
	 * @package FreedomIndex
	 * @since 1.0.0
	 */
	final class Scoring {

		/**
		 * Initialize AJAX hooks
		 */
		public static function init(): void {
			add_action('wp_ajax_fi_calculate_scores', [self::class, 'ajax_calculate_scores']);
			add_action('wp_ajax_fi_calculate_freedom_scores', [self::class, 'ajax_calculate_freedom_scores']);
			add_action('wp_ajax_fi_calculate_gov_scores', [self::class, 'ajax_calculate_gov_scores']);
			add_action('wp_ajax_fi_get_score_stats', [self::class, 'ajax_get_score_stats']);
		}

		// ============================================================================
		// MAIN ORCHESTRATION - Entry points for score recalculation
		// ============================================================================

		/**
		 * Recalculate scores for sessions and lifetime
		 * 
		 * Main entry point for score recalculation. Processes session scores first,
		 * then triggers freedom score updates.
		 * 
		 * @param string|null $gov Government code (e.g., 'US', 'FL') or null for all
		 * @param int|null $session_id Specific session ID or null for all sessions in gov
		 * @return int Total number of scores calculated
		 */
		public static function calculate_scores_all(?string $gov = null, ?int $session_id = null): int {
			$calculated = 0;
			
			// Get sessions to process (top-level only)
			$sessions = self::get_sessions_for_calculation($gov, $session_id);
			self::log("calculate_scores_all:sessions: " . json_encode($sessions), __FILE__, __LINE__);

			if (empty($sessions)) {
				return 0;
			}
			
			// Recalculate each session
			foreach ($sessions as $session) {
				$sid = (int) $session->id;
				if ($sid > 0) {
					$calculated += self::calculate_scores_session($sid);
				}
			}
			
			// Recalculate Freedom scores after all sessions are done
			if ($calculated > 0) {
				self::calculate_scores_freedom($gov);
			}
			
			return $calculated;
		}

		/**
		 * Recalculate scores for a single session
		 * 
		 * Processes all legislators in the session, calculating their scores based on
		 * votes from the session and its children (hierarchy-aware).
		 * 
		 * @param int $session_id Session ID to recalculate
		 * @return int Number of scores calculated
		 */
		public static function calculate_scores_session(int $session_id): int {
			global $wpdb;
			
			self::log("  → calculate_scores_session({$session_id}) START", __FILE__, __LINE__);
			
			// Validate session exists
			$session = $wpdb->get_row($wpdb->prepare(
				"SELECT id, gov FROM {$wpdb->prefix}fi_sessions WHERE id = %d",
				$session_id
			));
			
			if (!$session) {
				self::log("  → Session {$session_id} not found", __FILE__, __LINE__);
				return 0;
			}
			
			self::log("  → Session found: Gov={$session->gov}", __FILE__, __LINE__);
			
			// Get legislators for this session, grouped by chamber
			$legislators = self::get_legislators_for_session($session_id);
			
			if (empty($legislators)) {
				self::log("  → No legislators found for session {$session_id}", __FILE__, __LINE__);
				return 0;
			}
			
			self::log("  → Found " . count($legislators) . " legislators", __FILE__, __LINE__);
				
			//CHAMBERFLAG
			// Group legislators by chamber (H=House, S=Senate)
			$by_chamber = ['H' => [], 'S' => []];
			foreach ($legislators as $leg) {
				$chamber = strtoupper($leg->chamber ?? '');
				if (isset($by_chamber[$chamber])) {
					$by_chamber[$chamber][] = $leg;
				}
			}
			
			$calculated = 0;
				
			// Process each chamber group separately (different vote sets)
			foreach ($by_chamber as $chamber => $chamber_legislators) {
				if (empty($chamber_legislators)) {
					continue;
				}
				
				self::log("  → Processing chamber: {$chamber} with " . count($chamber_legislators) . " legislators", __FILE__, __LINE__);
				
				// Get votes for this session+chamber (includes child session votes)
				$votes = self::get_votes_for_session($session_id, $chamber);
				
				if (empty($votes)) {
					self::log("  → No votes found for chamber {$chamber}", __FILE__, __LINE__);
					continue;
				}
				
				self::log("  → Found " . count($votes) . " votes for chamber {$chamber}", __FILE__, __LINE__);
					
				// Deduplicate votes by ID (hierarchy may return duplicates)
				$unique_votes = [];
				foreach ($votes as $vote) {
					$unique_votes[$vote->id] = $vote;
				}
				$votes = array_values($unique_votes);
				
				// Extract vote IDs and legislator IDs for bulk rollcall fetch
				$vote_ids = array_map(fn($v) => (int) $v->id, $votes);
				$legislator_ids = array_map(fn($l) => (int) $l->legislator_id, $chamber_legislators);

				self::log("  → Bulk rollcall query: " . count($legislator_ids) . " legislators, " . count($vote_ids) . " votes", __FILE__, __LINE__);
				self::log("  → Vote IDs sample (first 10): " . json_encode(array_slice($vote_ids, 0, 10)), __FILE__, __LINE__);
				self::log("  → Legislator IDs sample (first 10): " . json_encode(array_slice($legislator_ids, 0, 10)), __FILE__, __LINE__);

				// Bulk fetch all rollcalls for these legislators+votes (single query)
				$rollcalls_lookup = self::get_rollcalls_bulk($legislator_ids, $vote_ids);
					
				// Calculate score for each legislator
				foreach ($chamber_legislators as $leg) {
					$legislator_id = (int) $leg->legislator_id;
					$ls_id = (int) $leg->ls_id;

					if ($legislator_id <= 0 || $ls_id <= 0) {
						continue;
					}
					
					// Get this legislator's rollcalls
					$legislator_rollcalls = $rollcalls_lookup[$legislator_id] ?? [];
					self::log("    → Legislator {$legislator_id}: " . count($legislator_rollcalls) . " rollcalls", __FILE__, __LINE__);					
					// Calculate score
					$score_data = self::calculate_score($votes, $legislator_rollcalls);
					self::log("    → Score calculated: " . ($score_data['score'] ?? 'N/A') . " (scored: " . ($score_data['scored'] ?? 0) . ")", __FILE__, __LINE__);
					// Only save if there are scored votes
					if (($score_data['scored'] ?? 0) > 0) {
						$saved = self::save_score_session($legislator_id, $session_id, $score_data);
						if ($saved) {
							$calculated++;
							self::log("    → Score SAVED for legislator {$legislator_id}", __FILE__, __LINE__);
						} else {
							self::log("    → Score SAVE FAILED for legislator {$legislator_id}", __FILE__, __LINE__, 'error');
						}
					} else {
						self::log("    → No scored votes, skipping save", __FILE__, __LINE__);
					}
				}
			}
			
			self::log("  → calculate_scores_session({$session_id}) END: {$calculated} scores saved", __FILE__, __LINE__);
			
			return $calculated;
		}

		/**
		 * Recalculate Freedom scores for all legislators in a government
		 * 
		 * Aggregates scores from all top-level sessions to calculate Freedom totals.
		 * Only processes top-level sessions (child session votes are already included
		 * in parent session scores via hierarchy).
		 * 
		 * @param string|null $gov Government code or null for all
		 * @return int Number of Freedom scores updated
		 */
		public static function calculate_scores_freedom(?string $gov = null): int {
			global $wpdb;
			
			// Build WHERE clause
			$where_conditions = [];
			$params = [];
			
			if ($gov) {
				$where_conditions[] = 'gov = %s';
				$params[] = $gov;
			}
			
			$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
			
			// Get all unique legislator IDs for this gov
			$sql = "SELECT DISTINCT legislator_id 
					FROM {$wpdb->prefix}fi_legislator_sessions 
					{$where_clause}
					ORDER BY legislator_id";
			
			$legislator_ids = $wpdb->get_col(
				!empty($params) ? $wpdb->prepare($sql, ...$params) : $sql
			);
			
			if (empty($legislator_ids)) {
				return 0;
			}
			
			$updated = 0;
			
			// Update Freedom score for each legislator
			foreach ($legislator_ids as $legislator_id) {
				$legislator_id = (int) $legislator_id;
				if ($legislator_id > 0) {
					// Use existing helper function (aggregates top-level sessions only)
					fi_legislator_update_freedom_score($legislator_id);
					$updated++;
				}
			}
			
			return $updated;
		}

		// ============================================================================
		// DATA FETCHING - Query functions for score calculation
		// ============================================================================

		/**
		 * Get sessions for score calculation
		 * 
		 * Returns only top-level sessions (parent_id = 0). Child session votes are
		 * automatically included via hierarchy lookup during vote fetching.
		 * 
		 * @param string|null $gov Government code filter
		 * @param int|null $session_id Specific session ID filter
		 * @return array Array of session objects
		 */
		private static function get_sessions_for_calculation(?string $gov = null, ?int $session_id = null): array {
			global $wpdb;
			
			$where_conditions = ['parent_id IS NULL']; // Only top-level sessions
			$params = [];
			
			if ($session_id) {
				$where_conditions[] = 'id = %d';
				$params[] = $session_id;
			} elseif ($gov) {
				$where_conditions[] = 'gov = %s';
				$params[] = $gov;
			}
			
			$where_clause = implode(' AND ', $where_conditions);
			
			$sql = "SELECT id, gov, name 
					FROM {$wpdb->prefix}fi_sessions 
					WHERE {$where_clause}
					ORDER BY id DESC";
			
			$sessions = $wpdb->get_results(
				!empty($params) ? $wpdb->prepare($sql, ...$params) : $sql
			);
			
			return $sessions ?: [];
		}

		/**
		 * Get legislators assigned to a session
		 * 
		 * Returns legislator_session records with IDs and chamber needed for scoring.
		 * 
		 * @param int $session_id Session ID
		 * @return array Array of objects with ls_id, legislator_id, chamber
		 */
		private static function get_legislators_for_session(int $session_id): array {
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
		 * Get votes for a session (hierarchy-aware)
		 * 
		 * Returns votes from the session and all its children, filtered by chamber.
		 * CRITICAL: Must filter by chamber because sessions contain both House and Senate votes.
		 * 
		 * @param int $session_id Session ID
		 * @param string $chamber Chamber filter ('H' for House, 'S' for Senate)
		 * @return array Array of vote objects with id, constitutional, chamber
		 */
		private static function get_votes_for_session(int $session_id, string $chamber): array {
			global $wpdb;
			if (!in_array($chamber, ['H', 'S'], true)) {
				return [];
			}
			
			// Get session hierarchy (parent + all children)
			$session_ids = fi_sessions_get_hierarchy_ids($session_id);
			$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
			
			// Query votes from all sessions in hierarchy, filtered by chamber
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
		 * Get rollcalls in bulk for multiple legislators and votes
		 * 
		 * Single query to fetch all rollcalls, returned as nested lookup array.
		 * This is much more efficient than querying per-legislator.
		 * 
		 * @param array $legislator_ids Array of legislator IDs
		 * @param array $vote_ids Array of vote IDs
		 * @return array Nested array [legislator_id][vote_id] => cast ('Y', 'N', 'P', 'A', 'X')
		 */
		private static function get_rollcalls_bulk(array $legislator_ids, array $vote_ids): array {
			global $wpdb;
			
			if (empty($legislator_ids) || empty($vote_ids)) {
				return [];
			}
			
			// Build placeholders
			$vote_placeholders = implode(',', array_fill(0, count($vote_ids), '%d'));
			$leg_placeholders = implode(',', array_fill(0, count($legislator_ids), '%d'));

			// Merge params (votes first, then legislators)
			$params = array_merge($vote_ids, $legislator_ids);
			
			// Bulk fetch all rollcalls
			$rollcalls = $wpdb->get_results($wpdb->prepare(
				"SELECT vote_id, legislator_id, cast
				FROM {$wpdb->prefix}fi_voterc
				WHERE vote_id IN ($vote_placeholders)
					AND legislator_id IN ($leg_placeholders)",
				...$params
			));

			self::log("  → get_rollcalls_bulk: Query returned " . count($rollcalls ?: []) . " rollcall records", __FILE__, __LINE__);
			if (empty($rollcalls)) {
				self::log("  → WARNING: No rollcalls found for " . count($legislator_ids) . " legislators and " . count($vote_ids) . " votes", __FILE__, __LINE__, 'warning');
			}

			// Build nested lookup array
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
			self::log("  → get_rollcalls_bulk: Built lookup for " . count($lookup) . " legislators", __FILE__, __LINE__);
			return $lookup;
		}

		// ============================================================================
		// CALCULATION - Pure math functions (no database queries)
		// ============================================================================

		/**
		 * Calculate score from votes and rollcalls
		 * 
		 * Pure calculation function - no database queries.
		 * Takes vote data and rollcall data, returns score metrics.
		 * 
		 * Scoring logic:
		 * - good: Legislator voted same as constitutional position (Y=Y or N=N)
		 * - bad: Legislator voted opposite of constitutional position (Y=N or N=Y)
		 * - not: Legislator didn't vote or voted P/A/X (Present/Absent/Excused)
		 * - scored: Total of good + bad (only Y/N votes count toward score)
		 * - score: (good / scored) * 100, rounded to nearest integer
		 * 
		 * @param array $votes Array of vote objects with id and constitutional
		 * @param array $rollcalls Lookup array [vote_id] => cast
		 * @return array Score data: [score, grade, total, good, bad, not, scored]
		 */
		private static function calculate_score(array $votes, array $rollcalls): array {
			$votes_total = 0;
			$votes_good = 0;
			$votes_bad = 0;
			$votes_not = 0;
			$votes_scored = 0;
			
			foreach ($votes as $vote) {
				$votes_total++;
				
				// Check if legislator has a rollcall for this vote
				if (!isset($rollcalls[$vote->id])) {
					$votes_not++;
					continue;
				}
				
				$cast = $rollcalls[$vote->id];
				
				// Only Y/N count toward scored votes (P/A/X are "not voted")
				if (in_array($cast, ['P', 'A', 'X'], true)) {
					$votes_not++;
					continue;
				}
				
				$votes_scored++;
				
				// Compare legislator's vote to constitutional position
				if ($cast === $vote->constitutional) {
					$votes_good++;
				} else {
					$votes_bad++;
				}
			}
			
			// Calculate percentage score
			$score_rounded = 0;
			$grade = 'N/A';
			
			if ($votes_scored > 0) {
				$score_percentage = ($votes_good / $votes_scored) * 100;
				$score_rounded = round($score_percentage, 0);
				$grade = self::calculate_grade($score_rounded);
			}
			
			return [
				'score' => $score_rounded,
				'grade' => $grade,
				'total' => $votes_total,
				'good' => $votes_good,
				'bad' => $votes_bad,
				'not' => $votes_not,
				'scored' => $votes_scored,
			];
		}

	/**
	 * Calculate letter grade from numeric score
	 * 
	 * @param float $score Numeric score (0-100)
	 * @return string Letter grade (A, B, C, D, F)
	 */
	public static function calculate_grade(float $score): string {
		if ($score >= 90) return 'A';
		if ($score >= 80) return 'B';
		if ($score >= 70) return 'C';
		if ($score >= 60) return 'D';
		return 'F';
	}

	public static function grade_class_bg(string $grade): string {
		return 'fi-bg-'.strtolower($grade).' ';
	}
	public static function grade_class_bg_text(string $grade): string {
		return 'fi-bg-text-'.strtolower($grade).' ';
	}
	public static function grade_class_text(string $grade): string {
		return 'fi-text-'.strtolower($grade).' ';
	}
	public static function grade_css_var(string $grade): string {
		return '--fi-color-'.strtolower($grade);
	}


	/**
	 * Calculate score from a batch of pre-formatted vote data
	 * 
	 * Used for report scoring where vote data is already prepared with cast values.
	 * This is a simplified scoring function for cases where rollcalls are already embedded.
	 * 
	 * @param array $votes Array of vote data, each with:
	 *   - 'good' or 'constitutional': Constitutional position (Y or N)
	 *   - 'cast': Legislator's vote (Y, N, P, A, X)
	 * @return int Score as integer (0-100)
	 */
	public static function calculate_batch(array $votes): ?string {
		if (empty($votes)) {
			return 0;
		}
		
		$votes_good = 0;
		$votes_scored = 0;
		$total = count($votes);
		$half = round($total / 2,0);
		
		foreach ($votes as $vote) {
			// Handle both object and array formats
			$constitutional = is_object($vote) 
				? ($vote->good ?? $vote->constitutional ?? '') 
				: ($vote['good'] ?? $vote['constitutional'] ?? '');
			$cast = is_object($vote) 
				? ($vote->cast ?? 'X') 
				: ($vote['cast'] ?? 'X');
			
			$constitutional = strtoupper((string) $constitutional);
			$cast = strtoupper((string) $cast);
			
			// Only count votes where constitutional position is Y or N (scored votes)
			if (!in_array($constitutional, ['Y', 'N'], true)) {
				continue;
			}
			
			// Only Y/N count toward scored votes (P/A/X are abstentions)
			if (!in_array($cast, ['Y', 'N'], true)) {
				continue;
			}
			
			$votes_scored++;
			
			// Count as good if cast matches constitutional position
			if ($cast === $constitutional) {
				$votes_good++;
			}
		}
		
		// Calculate score percentage (rounded to whole number)
		if($votes_scored >= $half) {
			return (int) round(($votes_good / $votes_scored) * 100, 0);
		}
		return null;
	}

		// ============================================================================
		// PERSISTENCE - Save score data to database
		// ============================================================================

		/**
		 * Save session score to database
		 * 
		 * Updates fi_legislator_sessions table with calculated score data.
		 * Stores both the numeric score and full score_data JSON.
		 * 
		 * @param int $legislator_id Legislator ID
		 * @param int $session_id Session ID
		 * @param array $score_data Score data from calculate_score()
		 * @return bool True if saved successfully
		 */
	private static function save_score_session(int $legislator_id, int $session_id, array $score_data): bool {
		global $wpdb;
		
		// Prepare score_data JSON (remove score/grade as they're in separate columns)
		$score_data_json = [
			'total' => $score_data['total'] ?? 0,
			'good' => $score_data['good'] ?? 0,
			'bad' => $score_data['bad'] ?? 0,
			'not' => $score_data['not'] ?? 0,
			'scored' => $score_data['scored'] ?? 0,
		];
		
		$score = $score_data['score'] ?? 0;
		$grade = $score_data['grade'] ?? 'N/A';
		
		self::log("      → save_score_session: legislator={$legislator_id}, session={$session_id}, score={$score}, grade={$grade}", __FILE__, __LINE__);
		
		// Update the record
		$updated = $wpdb->update(
			$wpdb->prefix . 'fi_legislator_sessions',
			[
				'score' => $score,
				//'grade' => $grade,
				'score_data' => wp_json_encode($score_data_json),
			],
			[
				'legislator_id' => $legislator_id,
				'session_id' => $session_id,
			],
			['%d', '%s', '%s'],
			['%d', '%d']
		);
		
		if ($updated === false) {
			self::log("      → UPDATE FAILED: " . $wpdb->last_error, __FILE__, __LINE__);
		} elseif ($updated === 0) {
			self::log("      → UPDATE returned 0 (no rows changed - possibly same values)", __FILE__, __LINE__);
		} else {
			self::log("      → UPDATE SUCCESS: {$updated} row(s) affected", __FILE__, __LINE__);
		}
		
		return $updated !== false;
	}

		// ============================================================================
		// UTILITIES - Helper functions for averages and stats
		// ============================================================================

	/**
	 * Calculate average score from array of legislators
	 * 
	 * @param array $legislators Array of legislator objects with score property
	 * @param string|null $filter_by Optional filter: 'party', 'chamber', or null
	 * @param string|null $filter_value Filter value (e.g., 'R', 'D', 'S')
	 * @return array [average, count, sum]
	 */
	public static function calculate_average(array $legislators, ?string $filter_by = null, ?string $filter_value = null): array {
		$sum = 0;
		$count = 0;
		
		foreach ($legislators as $legislator) {
			// Apply filter if specified
			if ($filter_by && $filter_value) {
				$legislator_value = $legislator->{$filter_by} ?? null;
				if ($legislator_value !== $filter_value) {
					continue;
				}
			}
			
			$score = (float) ($legislator->score ?? 0);
			if ($score > 0) {
				$sum += $score;
				$count++;
			}
		}
		
		$average = $count > 0 ? round($sum / $count, 1) : 0;
		
		return [
			'average' => $average,
			'count' => $count,
			'sum' => $sum,
		];
	}

	/**
	 * Calculate average scores by party
	 * 
	 * @param array $legislators Array of legislator objects with score and party properties
	 * @return array Associative array: party_code => ['average' => int, 'count' => int]
	 */
	public static function calculate_average_by_party(array $legislators): array {
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
				'count' => $party_counts[$party]
			];
		}
		
		return $result;
	}

	/**
	 * Calculate average scores by chamber
	 * 
	 * @param array $legislators Array of legislator objects with score and chamber properties
	 * @return array Associative array: chamber => ['average' => int, 'count' => int]
	 */
	public static function calculate_average_by_chamber(array $legislators): array {
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
			//CHAMBERFLAG
			// Normalize chamber (R and H both mean House/Representative)
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
				'count' => $chamber_counts[$chamber]
			];
		}
		
		return $result;
	}

		/**
		 * Get score statistics for a government or session
		 * 
		 * @param string|null $gov Government code
		 * @param int|null $session_id Session ID
		 * @return array Statistics array
		 */
		public static function get_score_stats(?string $gov = null, ?int $session_id = null): array {
			global $wpdb;
			
			$where_conditions = [];
			$params = [];
			
			if ($session_id) {
				// Get hierarchy for session
				$session_ids = fi_sessions_get_hierarchy_ids($session_id);
				$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
				$where_conditions[] = "ls.session_id IN ($placeholders)";
				$params = array_merge($params, $session_ids);
			} elseif ($gov) {
				$where_conditions[] = "ls.gov = %s";
				$params[] = $gov;
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
			
			$stats = $wpdb->get_row(
				!empty($params) ? $wpdb->prepare($sql, ...$params) : $sql,
				ARRAY_A
			);
			
			return $stats ?: [];
		}

		// ============================================================================
		// AJAX HANDLERS - Async processing for admin UI
		// ============================================================================

		/**
		 * AJAX: Recalculate scores for gov/session
		 */
		public static function ajax_calculate_scores(): void {
			check_ajax_referer('fi_admin_nonce', 'nonce');
			self::log("START: ajax_calculate_scores", __FILE__, __LINE__);
			if (!current_user_can(FI_CAP_MANAGE)) {
				wp_send_json_error('Insufficient permissions');
			}
			
			$gov = sanitize_text_field($_POST['gov'] ?? '');
			$session_id = absint($_POST['session_id'] ?? 0);
			self::log("gov: {$gov}, session_id: {$session_id}", __FILE__, __LINE__);
			$calculated = self::calculate_scores_all($gov ?: null, $session_id ?: null);
			self::log("calculated: {$calculated}", __FILE__, __LINE__);
			// Generate success message based on whether a specific session was selected
			if ($session_id > 0) {
				$message = "Success! The scores for this session and freedom scores for this session's legislators have been updated.";
			} else {
				$message = "Success! The scores for all sessions and freedom scores have been updated.";
			}
			self::log("message: {$message}", __FILE__, __LINE__);
			wp_send_json_success([
				'calculated' => $calculated,
				'message' => $message
			]);
		}

		/**
		 * AJAX: Recalculate freedom scores (batched)
		 * 
		 * Processes legislators in batches to avoid timeouts.
		 */
		public static function ajax_calculate_freedom_scores(): void {
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
			
			// Get total count
			$total = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(DISTINCT legislator_id) 
				FROM {$wpdb->prefix}fi_legislator_sessions 
				WHERE gov = %s",
				$gov
			));
			
			if ($total <= 0) {
				wp_send_json_success([
					'gov' => $gov,
					'total' => 0,
					'processed' => 0,
					'offset' => $offset,
					'next_offset' => 0,
					'done' => true,
					'message' => 'No legislators found for this government.'
				]);
			}
			
			// Get batch of legislator IDs
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
			
			// Process batch
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
				'gov' => $gov,
				'total' => $total,
				'processed' => $processed,
				'offset' => $offset,
				'next_offset' => $next_offset,
				'done' => $done,
				'message' => $done ? "Freedom scores updated ({$total})." : "Freedom score batch processed ({$processed})."
			]);
		}

		/**
		 * AJAX: Recalculate all session scores for a government (batched)
		 */
	public static function ajax_calculate_gov_scores(): void {
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
		
		self::log("=== AJAX CALCULATE GOV SCORES START ===", __FILE__, __LINE__);
		self::log("Gov: {$gov}, Offset: {$offset}, Limit: {$limit}", __FILE__, __LINE__);
		
		// Get sessions to process
		$sessions = self::get_sessions_for_calculation($gov);
		$total = count($sessions);
		
		self::log("Total sessions found: {$total}", __FILE__, __LINE__);
			
			if ($total <= 0) {
				wp_send_json_success([
					'gov' => $gov,
					'total_sessions' => 0,
					'processed_sessions' => 0,
					'offset' => $offset,
					'next_offset' => 0,
					'done' => true,
					'calculated' => 0,
					'message' => 'No sessions found for this government.'
				]);
			}
			
		// Process batch of sessions
		$batch = array_slice($sessions, $offset, $limit);
		$calculated = 0;
		$processed_sessions = 0;
		$last_session_id = 0;
		
		self::log("Processing batch: " . count($batch) . " sessions", __FILE__, __LINE__);
		
		foreach ($batch as $session) {
			$last_session_id = (int) ($session->id ?? 0);
			if ($last_session_id > 0) {
				self::log("Processing session ID: {$last_session_id}, Name: " . ($session->name ?? 'N/A'), __FILE__, __LINE__);
				$session_calculated = self::calculate_scores_session($last_session_id);
				$calculated += $session_calculated;
				$processed_sessions++;
				self::log("Session {$last_session_id} calculated {$session_calculated} scores", __FILE__, __LINE__);
			}
		}
		
		self::log("Batch complete. Total calculated: {$calculated}", __FILE__, __LINE__);
			
		$next_offset = $offset + $processed_sessions;
		$done = $next_offset >= $total || empty($batch);
		
		self::log("=== AJAX CALCULATE GOV SCORES END ===", __FILE__, __LINE__);
		self::log("Next offset: {$next_offset}, Done: " . ($done ? 'YES' : 'NO'), __FILE__, __LINE__);
		
		// Exit after first batch to avoid excessive logging
/*
		if ($offset === 0) {
			self::log("*** FIRST BATCH COMPLETE - EXITING TO REVIEW LOGS ***", __FILE__, __LINE__);
			wp_send_json_error([
				'message' => 'First batch logged successfully. Check logs and remove this exit to continue.',
				'debug' => [
					'gov' => $gov,
					'total_sessions' => $total,
					'processed_sessions' => $processed_sessions,
					'calculated' => $calculated
				]
			]);
		}
*/
		
		wp_send_json_success([
			'gov' => $gov,
			'total_sessions' => $total,
			'processed_sessions' => $processed_sessions,
			'offset' => $offset,
			'next_offset' => $next_offset,
			'last_session_id' => $last_session_id,
			'done' => $done,
			'calculated' => $calculated,
			'message' => $done ? "Session scores updated ({$total})." : "Session score batch processed ({$processed_sessions})."
		]);
	}

		/**
		 * AJAX: Get score statistics
		 */
		public static function ajax_get_score_stats(): void {
			check_ajax_referer('fi_admin_nonce', 'nonce');
			
			if (!current_user_can(FI_CAP_MANAGE)) {
				wp_send_json_error('Insufficient permissions');
			}
			
			$gov = sanitize_text_field($_POST['gov'] ?? '');
			$session_id = absint($_POST['session_id'] ?? 0);
			
			$stats = self::get_score_stats($gov ?: null, $session_id ?: null);
			
			wp_send_json_success($stats);
		}


		//Log wrapper for scoring
		public static function log(string $message, string $file='', int $line=0, string $level = 'info'): void {
			//fi_log_area('score', $message, $file, $line, $level);
		}

	}
}

	// ============================================================================
	// HELPER FUNCTIONS - External API (never call class methods directly)
	// ============================================================================
	
// Register hooks
namespace {
	add_action('init', function() {
		// Only register if in admin area or doing AJAX
		if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
			\FI\Core\Scoring::init();
		}
	}, 10);
	
	/**
	 * Recalculate scores for all sessions and lifetime
	 * 
	 * @param string|null $gov Government code or null for all
	 * @param int|null $session_id Specific session or null for all
	 * @return int Number of scores calculated
	 */
	function fi_score_calculate_all(?string $gov = null, ?int $session_id = null): int {
		return \FI\Core\Scoring::calculate_scores_all($gov, $session_id);
	}
	
	/**
	 * Recalculate scores for a single session
	 * 
	 * @param int $session_id Session ID
	 * @return int Number of scores calculated
	 */
	function fi_score_calculate_session(int $session_id): int {
		return \FI\Core\Scoring::calculate_scores_session($session_id);
	}
	
	/**
	 * Recalculate freedom scores for a government
	 * 
	 * @param string|null $gov Government code or null for all
	 * @return int Number of freedom scores updated
	 */
	function fi_score_calculate_freedom(?string $gov = null): int {
		return \FI\Core\Scoring::calculate_scores_freedom($gov);
	}
	
	/**
	 * Calculate letter grade from numeric score
	 * 
	 * @param float $score Numeric score (0-100)
	 * @return string Letter grade (A, B, C, D, F)
	 */
	function fi_score_calculate_grade(float $score): string {
		return \FI\Core\Scoring::calculate_grade($score);
	}

	function fi_score_class_bg(int $score) : string {
		return \FI\Core\Scoring::grade_class_bg(fi_score_calculate_grade($score));
	}
	//What color text on top of score background?
	function fi_score_class_bg_text(int $score) : string {
		return \FI\Core\Scoring::grade_class_bg_text(fi_score_calculate_grade($score));
	}

	function fi_score_class_text(int $score) : string {
		return \FI\Core\Scoring::grade_class_text(fi_score_calculate_grade($score));
	}
	function fi_score_css_var(int $score) : string {
		return \FI\Core\Scoring::grade_css_var(fi_score_calculate_grade($score));
	}

	/**
	 * Calculate average score from array of legislators
	 * 
	 * @param array $legislators Array of legislator objects with score property
	 * @param string|null $filter_by Optional filter: 'party', 'chamber', or null
	 * @param string|null $filter_value Filter value (e.g., 'R', 'D', 'S')
	 * @return array [average, count, sum]
	 */
	function fi_score_calculate_average(array $legislators, ?string $filter_by = null, ?string $filter_value = null): array {
		return \FI\Core\Scoring::calculate_average($legislators, $filter_by, $filter_value);
	}
	
	/**
	 * Get score statistics for a government or session
	 * 
	 * @param string|null $gov Government code
	 * @param int|null $session_id Session ID
	 * @return array Statistics array
	 */
	function fi_score_get_stats(?string $gov = null, ?int $session_id = null): array {
		return \FI\Core\Scoring::get_score_stats($gov, $session_id);
	}
	
	/**
	 * Calculate score from a batch of pre-formatted vote data
	 * 
	 * Used for report scoring where vote data is already prepared with cast values.
	 * 
	 * @param array $votes Array of vote data, each with 'good'/'constitutional' and 'cast'
	 * @return int Score as integer (0-100) or NULL if not enough votes counted
	 */
	function fi_score_calculate_batch(array $votes): ?string {
		return \FI\Core\Scoring::calculate_batch($votes);
	}
	
	/**
	 * Calculate average scores by party
	 * 
	 * @param array $legislators Array of legislator objects with score and party properties
	 * @return array Associative array: party_code => ['average' => int, 'count' => int]
	 */
	function fi_score_calculate_average_by_party(array $legislators): array {
		return \FI\Core\Scoring::calculate_average_by_party($legislators);
	}
	
	/**
	 * Calculate average scores by chamber
	 * 
	 * @param array $legislators Array of legislator objects with score and chamber properties
	 * @return array Associative array: chamber => ['average' => int, 'count' => int]
	 */
	function fi_score_calculate_average_by_chamber(array $legislators): array {
		return \FI\Core\Scoring::calculate_average_by_chamber($legislators);
	}


	//Standardize the score calc with pre-determined vote counts.
	function fi_score_calculate($votes_good, $votes_scored): int|null {
		return fi_score_calc($votes_good, $votes_scored);
	}

/*MOVED TO _reference.php to be shared with the API
	function fi_score_calculate($votes_good, $votes_scored): int|null {
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
*/
}