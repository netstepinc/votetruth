<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
Horizontal navigation menu showing all reports for this current session.
*/

// Get report links array from global variable (built in rewrite handler based on filtered session)
global $fi_report_links, $fi_gov, $fi_session;

if (!empty($fi_report_links)) {
	// Use pre-built report links array from rewrite handler (respects session filter)
	$report_links = $fi_report_links;
} else {
	// Build report links from session_id if global not set
	$session_id = isset($args['session_id']) ? $args['session_id'] : ($fi_session ?? null);
	$reports = fi_reports_get_by_session($session_id);
	if ($reports) {
		$gov = $fi_gov ?? (isset($reports[0]) ? strtolower($reports[0]->gov ?? 'us') : 'us');
		$report_links = [];
		foreach ($reports as $report) {
			$report_url = fi_get_report_url($report->slug, strtolower($report->gov ?? $gov));
			$report_title = $report->title ?? ucwords(str_replace('-', ' ', $report->slug));
			$report_links[] = [
				'url' => $report_url,
				'title' => $report_title,
			];
		}
	} else {
		$report_links = [];
	}
}

// Use unified HTML generation function
echo fi_reports_nav_html($report_links ?? []);