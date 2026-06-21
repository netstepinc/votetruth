<?php
namespace FI\Admin{

	if (!defined('ABSPATH')) exit;

	/**
	* Dashboard Statistics
	* 
	* Provides dashboard statistics and analytics functions.
	* May be expanded for extensive dashboard widgets.
	*/
	final class Dashboard {
		
		/**
		* Get dashboard statistics (performance-safe)
		*
		* Summary (why this changed):
		* - The previous implementation used multi-table LEFT JOINs including fi_voterc and COUNT(DISTINCT ...).
		* - On large imports (US rollcalls), that explodes row counts and can time out admin pages.
		* - This version uses targeted COUNT(*) queries on indexed columns and avoids touching fi_voterc entirely.
		*/
		public static function get_stats(string $gov = null, int $session_id = null): array {
			global $wpdb;

			$gov = $gov ? strtoupper((string) $gov) : null;
			$session_id = $session_id ? (int) $session_id : null;

			// Session scope expansion (parent + children) for vote/report counts.
			$session_ids = null;
			if ($session_id) {
				$session_ids = fi_sessions_get_hierarchy_ids($session_id);
				$session_ids = is_array($session_ids) ? array_values(array_filter(array_map('absint', $session_ids))) : [];
				if (empty($session_ids)) {
					$session_ids = [$session_id];
				}
			}

			// Sessions count (fi_sessions is small).
			$sessions = 0;
			if ($gov) {
				$sessions = (int) $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}fi_sessions WHERE gov = %s",
					$gov
				));
			}

			// Legislators count (distinct legislator_id in legislator_sessions joined to sessions for gov).
			$legislators = 0;
			if ($gov) {
				if ($session_id) {
					$legislators = (int) $wpdb->get_var($wpdb->prepare(
						"SELECT COUNT(DISTINCT ls.legislator_id)
						FROM {$wpdb->prefix}fi_legislator_sessions ls
						WHERE ls.session_id = %d",
						$session_id
					));
				} else {
					$legislators = (int) $wpdb->get_var($wpdb->prepare(
						"SELECT COUNT(DISTINCT ls.legislator_id)
						FROM {$wpdb->prefix}fi_legislator_sessions ls
						INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
						WHERE s.gov = %s",
						$gov
					));
				}
			}

			// Votes + rollcall coverage (avoid fi_voterc; use votes.rollcall_data presence instead).
			$votes = 0;
			$roll_calls = 0;
			if ($gov) {
				$where = ["gov = %s"];
				$vals = [$gov];
				if (is_array($session_ids) && !empty($session_ids)) {
					$placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
					$where[] = "session_id IN ({$placeholders})";
					$vals = array_merge($vals, $session_ids);
				}
				$where_sql = 'WHERE ' . implode(' AND ', $where);

				$row = $wpdb->get_row($wpdb->prepare(
					"SELECT
						COUNT(*) as total_votes,
						COUNT(CASE WHEN rollcall_data IS NOT NULL THEN 1 END) as votes_with_rollcall
					FROM {$wpdb->prefix}fi_votes
					{$where_sql}",
					$vals
				));
				$votes = (int) ($row->total_votes ?? 0);
				$roll_calls = (int) ($row->votes_with_rollcall ?? 0);
			}

			// Reports count (by gov, optionally by session).
			$reports = 0;
			if ($gov) {
				if ($session_id) {
					$reports = (int) $wpdb->get_var($wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}fi_reports WHERE gov = %s AND session_id = %d",
						$gov,
						$session_id
					));
				} else {
					$reports = (int) $wpdb->get_var($wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}fi_reports WHERE gov = %s",
						$gov
					));
				}
			}

			// Scores count (session-scoped scores live on fi_legislator_sessions.score).
			$scores = 0;
			if ($gov) {
				if ($session_id) {
					$scores = (int) $wpdb->get_var($wpdb->prepare(
						"SELECT COUNT(*)
						FROM {$wpdb->prefix}fi_legislator_sessions ls
						WHERE ls.session_id = %d AND ls.score IS NOT NULL",
						$session_id
					));
				} else {
					$scores = (int) $wpdb->get_var($wpdb->prepare(
						"SELECT COUNT(*)
						FROM {$wpdb->prefix}fi_legislator_sessions ls
						INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
						WHERE s.gov = %s AND ls.score IS NOT NULL",
						$gov
					));
				}
			}

			return [
				'legislators' => (int) $legislators,
				'sessions' => (int) $sessions,
				'votes' => (int) $votes,
				// NOTE: this is "votes with rollcall data", not fi_voterc row count (performance-safe).
				'roll_calls' => (int) $roll_calls,
				'reports' => (int) $reports,
				'scores' => (int) $scores,
			];
		}
		
		/**
		* Get comprehensive dashboard overview
		*/
		public static function get_overview(): array {
			$overview = [];
			
			// Get stats for all governments
			$governments = fi_govs();
			foreach ($governments as $gov_code => $gov_name) {
				$overview[$gov_code] = [
					'name' => $gov_name,
					'stats' => self::get_stats($gov_code),
					'current_session' => null // Determined programmatically in front-end
				];
			}
			
			return $overview;
		}
		
		/**
		* Get recent activity
		*/
		public static function get_recent_activity(int $limit = 10): array {
			global $wpdb;
			
			$sql = "
				SELECT 
					'legislator' as type,
					l.id,
					l.first_name,
					l.last_name,
					l.date_created,
					s.gov
				FROM {$wpdb->prefix}fi_legislators l
				LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
				LEFT JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
				WHERE l.date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
				
				UNION ALL
				
				SELECT 
					'vote' as type,
					v.id,
					v.title as first_name,
					'' as last_name,
					v.date_created,
					s.gov
				FROM {$wpdb->prefix}fi_votes v
				LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
				WHERE v.date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
				
				ORDER BY date_created DESC
				LIMIT %d
			";
			
			return $wpdb->get_results($wpdb->prepare($sql, $limit));
		}
		
		/**
		* Get top performing legislators
		*/
		public static function get_top_legislators(string $gov = null, int $limit = 10): array {
			global $wpdb;
			
			$where_clause = '';
			$params = [];
			
			if ($gov) {
				$where_clause .= " AND s.gov = %s";
				$params[] = $gov;
			}
			
			$params[] = $limit;
			
			$sql = "
				SELECT 
					l.id, l.first_name, l.last_name, l.display_name,
					ls.score, ls.votes_total, ls.votes_good, ls.votes_bad, ls.votes_not,
					s.name as session_name, s.gov
				FROM {$wpdb->prefix}fi_legislator_sessions ls
				INNER JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
				INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
				WHERE ls.score IS NOT NULL {$where_clause}
				ORDER BY ls.score DESC
				LIMIT %d
			";
			
			return $wpdb->get_results($wpdb->prepare($sql, $params));
		}
		
		/**
		* Get voting statistics
		*/
		public static function get_voting_stats(string $gov = null, int $session_id = null): array {
			global $wpdb;
			
			$where_clause = '';
			$params = [];
			
			if ($gov) {
				$where_clause .= " AND s.gov = %s";
				$params[] = $gov;
			}
			
			if ($session_id) {
				$where_clause .= " AND v.session_id = %d";
				$params[] = $session_id;
			}
			
			$sql = "
				SELECT 
					COUNT(*) as total_votes,
					SUM(CASE WHEN v.constitutional = 'Y' THEN 1 ELSE 0 END) as good_votes,
					SUM(CASE WHEN v.constitutional = 'N' THEN 1 ELSE 0 END) as bad_votes,
					AVG(CASE WHEN v.constitutional = 'Y' THEN 1 ELSE 0 END) * 100 as good_percentage
				FROM {$wpdb->prefix}fi_votes v
				LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
				WHERE 1=1 {$where_clause}
			";
			
			$result = $wpdb->get_row($wpdb->prepare($sql, $params));
			
			return [
				'total_votes' => (int) $result->total_votes,
				'good_votes' => (int) $result->good_votes,
				'bad_votes' => (int) $result->bad_votes,
				'good_percentage' => round((float) $result->good_percentage, 2)
			];
		}
	}
}

namespace{
	function fi_dashboard_get_stats(string $gov = null, int $session_id = null): array {
		return \FI\Admin\Dashboard::get_stats($gov, $session_id);
	}
	function fi_dashboard_get_overview(): array {
		return \FI\Admin\Dashboard::get_overview();
	}
	function fi_dashboard_get_recent_activity(int $limit = 10): array {
		return \FI\Admin\Dashboard::get_recent_activity($limit);
	}
	function fi_dashboard_get_top_legislators(string $gov = null, int $limit = 10): array {
		return \FI\Admin\Dashboard::get_top_legislators($gov, $limit);
	}

	/**
	* Render dashboard
	*/
	function fi_admin_dashboard_render(): void {
		include FI_DIR . 'admin/views/dashboard.php';
	}
}