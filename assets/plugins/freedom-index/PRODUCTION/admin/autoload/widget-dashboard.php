<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Dashboard widgets for each government
 * Staff can enable/disable widgets for governments they work with
 * 
 * @author Sam Mittelstaedt <smittelstaedt@jbs.org>
 */

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
	$stats = \FI\Admin\Dashboard::get_stats($gov_code);
	
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
	// Build URL with gov parameter
	// The admin init hook will set the persistent scope when the page loads
	return admin_url('admin.php?page=' . $page . '&gov=' . strtoupper($gov_code));
}
