<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * reports_get – list or single report via FI API.
 * GET vars aligned with rewrite (public/autoload/rewrite.php): [gov]/reports, [gov]/report/[id], url_report().
 */

// Report GET vars: list (gov/session) and single report with chamber/compare
$report_get_vars = [
	'gov',           // Government code (us, tx, wi, …); required for list
	'report_id',    // Single report (rewrite fi_report_id)
	'session_id',  // Session filter for reports list
	'chamber',     // Chamber H | S for single report view (rewrite fi_chamber)
	'format',      // Report format (scorecard | freedomindex)
	'compare',     // Compare view (rewrite fi_compare=1)
	'sort',
	'order',
];