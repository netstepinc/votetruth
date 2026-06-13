<?php
/*
 * Freedom Index Admin Dashboard Statistics
 *
 * Straight function version of the former FIAdmin\Dashboard class file.
 *
 * Provides dashboard statistics and analytics helpers.
 * This file remains admin-oriented, but the individual functions are safe to reuse
 * wherever dashboard summary data is needed.
 * Refactored the admin dashboard statistics file into straight functions.
Key adjustments:
	Removed the FIAdmin\Dashboard class/namespace wrapper.
Preserved existing public/admin helpers:
	fi_dashboard_get_stats()
	fi_dashboard_get_overview()
	fi_dashboard_get_recent_activity()
	fi_dashboard_get_top_legislators()
	fi_admin_dashboard_render()
Added the previously missing wrapper as a real function:
	fi_dashboard_get_voting_stats()
Tuning:
	Kept the performance-safe fi_dashboard_get_stats() approach that avoids touching fi_voterc.
	Normalized $gov with strtoupper(sanitize_key()).
	Capped dashboard query limits to 1–100.
	Changed recent activity to avoid duplicate legislator rows caused by multiple session joins by grouping legislator results.
	Added current_session in fi_dashboard_get_overview() using fi_session_get_current() when available.
	Added a safe fallback message if admin/views/dashboard.php is missing.
 */

if (!defined('ABSPATH')) exit;

/**
 * Get dashboard statistics using targeted performance-safe queries.
 *
 * Notes:
 * - Avoids multi-table LEFT JOINs against fi_voterc.
 * - Uses COUNT(*) and COUNT(DISTINCT ...) only on targeted indexed columns.
 * - roll_calls means votes with rollcall_data, not fi_voterc row count.
 *
 * @param string|null $gov Government code.
 * @param int|null $session_id Optional session ID.
 * @return array Dashboard statistics.
 */
function fi_dashboard_get_stats(?string $gov = null, ?int $session_id = null): array {
	global $wpdb;

	$gov = $gov ? strtoupper(sanitize_key($gov)) : null;
	$session_id = $session_id ? absint($session_id) : null;

	$session_ids = null;
	if ($session_id) {
		$session_ids = function_exists('fi_sessions_get_hierarchy_ids') ? fi_sessions_get_hierarchy_ids($session_id) : [$session_id];
		$session_ids = is_array($session_ids) ? array_values(array_filter(array_map('absint', $session_ids))) : [];
		if (empty($session_ids)) {
			$session_ids = [$session_id];
		}
	}

	$sessions = 0;
	if ($gov) {
		$sessions = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}fi_sessions WHERE gov = %s",
			$gov
		));
	}

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

	$votes = 0;
	$roll_calls = 0;
	if ($gov) {
		$where = ['gov = %s'];
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
			...$vals
		));

		$votes = (int) ($row->total_votes ?? 0);
		$roll_calls = (int) ($row->votes_with_rollcall ?? 0);
	}

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
		'sessions'    => (int) $sessions,
		'votes'       => (int) $votes,
		'roll_calls'  => (int) $roll_calls,
		'reports'     => (int) $reports,
		'scores'      => (int) $scores,
	];
}

/**
 * Get comprehensive dashboard overview by government.
 *
 * @return array Overview data.
 */
function fi_dashboard_get_overview(): array {
	$overview = [];
	$governments = function_exists('fi_govs') ? fi_govs() : [];

	foreach ($governments as $gov_code => $gov_name) {
		$gov_code = strtoupper(sanitize_key((string) $gov_code));
		if ($gov_code === '') {
			continue;
		}

		$overview[$gov_code] = [
			'name'            => $gov_name,
			'stats'           => fi_dashboard_get_stats($gov_code),
			'current_session' => function_exists('fi_session_get_current') ? fi_session_get_current($gov_code) : null,
		];
	}

	return $overview;
}

/**
 * Get recent admin dashboard activity.
 *
 * @param int $limit Max rows.
 * @return array Recent activity rows.
 */
function fi_dashboard_get_recent_activity(int $limit = 10): array {
	global $wpdb;

	$limit = max(1, min(100, absint($limit)));

	$sql = "
		SELECT * FROM (
			SELECT
				'legislator' as type,
				l.id,
				l.first_name,
				l.last_name,
				l.display_name as title,
				l.date_created,
				MIN(s.gov) as gov
			FROM {$wpdb->prefix}fi_legislators l
			LEFT JOIN {$wpdb->prefix}fi_legislator_sessions ls ON l.id = ls.legislator_id
			LEFT JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
			WHERE l.date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
			GROUP BY l.id

			UNION ALL

			SELECT
				'vote' as type,
				v.id,
				v.title as first_name,
				'' as last_name,
				v.title as title,
				v.date_created,
				s.gov
			FROM {$wpdb->prefix}fi_votes v
			LEFT JOIN {$wpdb->prefix}fi_sessions s ON v.session_id = s.id
			WHERE v.date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
		) recent
		ORDER BY date_created DESC
		LIMIT %d
	";

	return $wpdb->get_results($wpdb->prepare($sql, $limit));
}

/**
 * Get top-performing legislators by score.
 *
 * @param string|null $gov Optional government code.
 * @param int $limit Max rows.
 * @return array Legislator rows.
 */
function fi_dashboard_get_top_legislators(?string $gov = null, int $limit = 10): array {
	global $wpdb;

	$gov = $gov ? strtoupper(sanitize_key($gov)) : null;
	$limit = max(1, min(100, absint($limit)));

	$where_clause = '';
	$params = [];

	if ($gov) {
		$where_clause = ' AND s.gov = %s';
		$params[] = $gov;
	}

	$params[] = $limit;

	$sql = "
		SELECT
			l.id,
			l.first_name,
			l.last_name,
			l.display_name,
			ls.score,
			ls.votes_total,
			ls.votes_good,
			ls.votes_bad,
			ls.votes_not,
			s.name as session_name,
			s.gov
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		INNER JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
		INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
		WHERE ls.score IS NOT NULL {$where_clause}
		ORDER BY ls.score DESC, l.last_name ASC, l.first_name ASC
		LIMIT %d
	";

	return $wpdb->get_results($wpdb->prepare($sql, ...$params));
}

/**
 * Get voting statistics.
 *
 * @param string|null $gov Optional government code.
 * @param int|null $session_id Optional session ID.
 * @return array Voting stats.
 */
function fi_dashboard_get_voting_stats(?string $gov = null, ?int $session_id = null): array {
	global $wpdb;

	$gov = $gov ? strtoupper(sanitize_key($gov)) : null;
	$session_id = $session_id ? absint($session_id) : null;

	$where_clause = '';
	$params = [];

	if ($gov) {
		$where_clause .= ' AND s.gov = %s';
		$params[] = $gov;
	}

	if ($session_id) {
		$where_clause .= ' AND v.session_id = %d';
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

	$result = !empty($params)
		? $wpdb->get_row($wpdb->prepare($sql, ...$params))
		: $wpdb->get_row($sql);

	return [
		'total_votes'     => (int) ($result->total_votes ?? 0),
		'good_votes'      => (int) ($result->good_votes ?? 0),
		'bad_votes'       => (int) ($result->bad_votes ?? 0),
		'good_percentage' => round((float) ($result->good_percentage ?? 0), 2),
	];
}

/**
 * Render admin dashboard view.
 *
 * @return void
 */
function fi_admin_dashboard_render(): void {
	$view = defined('FI_DIR') ? FI_DIR . 'admin/views/dashboard.php' : '';

	if ($view && file_exists($view)) {
		include $view;
		return;
	}

	echo '<div class="wrap"><h1>Freedom Index Dashboard</h1><p>Dashboard view file not found.</p></div>';
}



// Register dashboard widgets for each government
add_action('wp_dashboard_setup', 'fi_admin_dashboard_widgets_register');

// Collapse FI widgets by default (user can open and WP will remember).
// Summary: uses WP's per-user "closed postboxes" persistence for the Dashboard screen.
add_filter('default_closed_postboxes', 'fi_admin_dashboard_widgets_default_closed', 10, 2);

function fi_admin_dashboard_widgets_default_closed(array $closed, $screen): array {
	if (!is_object($screen) || (($screen->id ?? '') !== 'dashboard')) {
		return $closed;
	}
	if (!current_user_can(FI_CAP_MANAGE)) {
		return $closed;
	}

	$governments = function_exists('fi_govs') ? fi_govs() : [];
	foreach ($governments as $gov_code => $_name) {
		$closed[] = 'fi_dashboard_' . strtolower((string) $gov_code);
	}

	return array_values(array_unique($closed));
}

function fi_admin_dashboard_widgets_register() {
	// Only show to users with FI management capability
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}
	
	// Get all governments (already in alpha order: US, then states A-Z)
	$governments = fi_govs();
	
	foreach ($governments as $gov_code => $gov_name) {
		// Widget ID must be unique and URL-safe
		$widget_id = 'fi_dashboard_' . strtolower($gov_code);
		$widget_name = 'FI: ' . $gov_name;
		
		// Register widget (enabled by default)
		wp_add_dashboard_widget(
			$widget_id,
			$widget_name,
			function() use ($gov_code, $gov_name) {
				fi_admin_dashboard_widget_render($gov_code, $gov_name);
			}
		);
	}
}

/**
 * Render individual government dashboard widget
 */
function fi_admin_dashboard_widget_render(string $gov_code, string $gov_name) {
	// Check cache first (1 day retention)
	$cache_key = 'widget/dash_' . strtolower($gov_code);
	$cached = fi_cache($cache_key);
	if ($cached) {
		echo $cached;
		return;
	}
	
	// Get stats for this government
	$stats = fi_dashboard_get_stats($gov_code);
	
	// Build widget HTML
	ob_start();
	?>
	<div class="fi-dashboard-widget" style="font-size: 13px;">
		<style>
			.fi-dashboard-widget .fi-stat-row {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 8px 0;
				border-bottom: 1px solid #f0f0f0;
			}
			.fi-dashboard-widget .fi-stat-row:last-child {
				border-bottom: none;
			}
			.fi-dashboard-widget .fi-stat-label {
				font-weight: 500;
				color: #50575e;
				text-decoration: none;
			}
			.fi-dashboard-widget a.fi-stat-label:hover {
				color: #135e96;
				text-decoration: underline;
			}
			.fi-dashboard-widget .fi-stat-value {
				font-weight: 600;
				color: #2271b1;
				text-decoration: none;
			}
			.fi-dashboard-widget .fi-stat-value:hover {
				color: #135e96;
			}
		</style>
		
		<div class="fi-stat-row">
			<a class="fi-stat-label" href="<?php echo esc_url(fi_admin_dashboard_widget_link('fi-legislators', $gov_code)); ?>">Legislators</a>
			<span class="fi-stat-value"><?php echo number_format($stats['legislators']); ?></span>
		</div>
		
		<div class="fi-stat-row">
			<a class="fi-stat-label" href="<?php echo esc_url(fi_admin_dashboard_widget_link('fi-votes', $gov_code)); ?>">Votes</a>
			<span class="fi-stat-value"><?php echo number_format($stats['votes']); ?></span>
		</div>
		
		<div class="fi-stat-row">
			<a class="fi-stat-label" href="<?php echo esc_url(fi_admin_dashboard_widget_link('fi-reports', $gov_code)); ?>">Reports</a>
			<span class="fi-stat-value"><?php echo number_format($stats['reports']); ?></span>
		</div>
	</div>
	<?php
	$output = ob_get_clean();
	
	// Cache for 1 day (default retention)
	fi_cache($cache_key, $output);
	
	echo $output;
}

/**
 * Build admin link that sets persistent gov scope
 */
function fi_admin_dashboard_widget_link(string $page, string $gov_code): string {
	$redirect_to = admin_url('admin.php?page=' . rawurlencode($page));
	$args = [
		'action' => 'fi_switch_scope',
		'gov' => strtoupper($gov_code),
		'redirect_to' => rawurlencode($redirect_to),
	];
	return wp_nonce_url(add_query_arg($args, admin_url('admin-post.php')), 'fi_switch_scope', 'fi_scope_nonce');
}