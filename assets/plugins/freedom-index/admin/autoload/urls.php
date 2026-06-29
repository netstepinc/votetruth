<?php
/*
 * Freedom Index Admin/Public URL Helpers
 *
 * Straight function version of the former FIAdmin\URLs class file.
 *
 * Generates canonical admin URLs and a small number of public ID-based URLs.
 *
 * Notes:
 * - Removed invalid/obsolete session, generic PDF, and generic share URL helpers.
 * - Public entity URLs are ID-based.
 * - Report URLs are ID-based: /{gov}/report/{report_id}/
 * Refactored the URL helper into straight functions.
Key adjustments:
	Removed the FIAdmin\URLs class/namespace wrapper.
Preserved valid helpers:
	fi_admin_url()
	fi_admin_edit_legislator_url()
	fi_admin_legislator_sessions_url()
	fi_admin_edit_vote_url()
	fi_admin_roll_call_edit_url()
	fi_admin_edit_session_url()
	fi_admin_recalculate_scores_url()
	fi_report_url()
Added/kept useful helpers:
	fi_url_legislator()
	fi_url_legislator_by_id()
	fi_list_url()
	fi_admin_import_url()
	fi_admin_settings_url()
	fi_api_url()
Removed invalid/obsolete helpers:
	get_session_url()
	get_pdf_url()
	get_share_url()
Those were already marked as not valid in the source, and keeping them would preserve misleading behavior.
Tuning:
	Kept public entity URLs ID-based.
	Sanitized admin page slugs and government codes.
	Used absint() for entity IDs.

Added conservative compatibility aliases with names that will not collide with better canonical helpers:
	fi_get_admin_helper_legislator_url()
	fi_get_admin_helper_list_url()
 */

if (!defined('ABSPATH')) exit;

/**
 * Get public legislator URL by ID.
 *
 * @param int|string $id Legislator ID.
 * @return string URL.
 */
function fi_url_legislator($id): string {
	$legislator_id = is_numeric($id) ? absint($id) : 0;

	if ($legislator_id <= 0) {
		return home_url('/');
	}

	if (function_exists('fi_get_legislator_url')) {
		return fi_legislator_get_url($legislator_id);
	}

	return home_url('/legislator/' . $legislator_id . '/');
}

/**
 * Get public report URL.
 *
 * @param string $gov Government code.
 * @param int $report_id Report ID.
 * @return string URL.
 */
function fi_report_url(string $gov, int $report_id): string {
	$gov = strtolower(sanitize_key($gov));
	$report_id = absint($report_id);

	if ($gov === '' || $report_id <= 0) {
		return home_url('/');
	}

	return home_url('/' . $gov . '/report/' . $report_id . '/');
}

/**
 * Get public list URL by ID.
 *
 * @param int|string $id List ID.
 * @return string URL.
 */
function fi_list_url($id): string {
	$list_id = is_numeric($id) ? absint($id) : 0;

	if ($list_id <= 0) {
		return home_url('/');
	}

	return home_url('/list/' . $list_id . '/');
}

/**
 * Get admin URL for a specific Freedom Index page with optional args.
 *
 * @param string $page Admin page slug.
 * @param array $args Query args.
 * @return string URL.
 */
function fi_admin_url(string $page = 'fi-dashboard', array $args = []): string {
	$page = sanitize_key($page ?: 'fi-dashboard');
	$url = admin_url('admin.php?page=' . rawurlencode($page));

	if (!empty($args)) {
		$url = add_query_arg($args, $url);
	}

	return $url;
}

/**
 * Get edit legislator admin URL.
 *
 * @param int $legislator_id Legislator ID.
 * @param array $args Extra query args.
 * @return string URL.
 */
function fi_admin_edit_legislator_url(int $legislator_id, array $args = []): string {
	$defaults = [
		'action'        => 'edit',
		'legislator_id' => absint($legislator_id),
	];

	return fi_admin_url('fi-legislators', array_merge($defaults, $args));
}

/**
 * Get legislator sessions management admin URL.
 *
 * @param int $legislator_id Legislator ID.
 * @param array $args Extra query args.
 * @return string URL.
 */
function fi_admin_legislator_sessions_url(int $legislator_id, array $args = []): string {
	$defaults = [
		'legislator_id' => absint($legislator_id),
	];

	return fi_admin_url('fi-sessions', array_merge($defaults, $args));
}

/**
 * Get edit vote admin URL.
 *
 * @param int $vote_id Vote ID.
 * @param array $args Extra query args.
 * @return string URL.
 */
function fi_admin_edit_vote_url(int $vote_id, array $args = []): string {
	$defaults = [
		'action'  => 'edit',
		'vote_id' => absint($vote_id),
	];

	return fi_admin_url('fi-votes', array_merge($defaults, $args));
}

/**
 * Get roll-call editor admin URL.
 *
 * @param int $vote_id Vote ID.
 * @param array $args Extra query args.
 * @return string URL.
 */
function fi_admin_roll_call_edit_url(int $vote_id, array $args = []): string {
	$defaults = [
		'action'  => 'rollcall',
		'vote_id' => absint($vote_id),
	];

	return fi_admin_url('fi-votes', array_merge($defaults, $args));
}

/**
 * Get edit session admin URL.
 *
 * @param int $session_id Session ID.
 * @param array $args Extra query args.
 * @return string URL.
 */
function fi_admin_edit_session_url(int $session_id, array $args = []): string {
	$defaults = [
		'action'     => 'edit',
		'session_id' => absint($session_id),
	];

	return fi_admin_url('fi-sessions', array_merge($defaults, $args));
}

/**
 * Get recalculate scores admin URL.
 *
 * @param string|null $gov Optional government code.
 * @param int|null $session_id Optional session ID.
 * @return string URL.
 */
function fi_admin_recalculate_scores_url(?string $gov = null, ?int $session_id = null): string {
	$args = ['action' => 'recalculate-scores'];

	if ($gov) {
		$args['gov'] = strtoupper(sanitize_key($gov));
	}

	if ($session_id) {
		$args['session_id'] = absint($session_id);
	}

	return fi_admin_url('fi-dashboard', $args);
}

/**
 * Get import admin URL.
 *
 * @param int $blog_id Optional blog ID.
 * @return string URL.
 */
function fi_admin_import_url(int $blog_id = 0): string {
	$args = [];

	if ($blog_id > 0) {
		$args = [
			'action'  => 'import',
			'blog_id' => absint($blog_id),
		];
	}

	return fi_admin_url('fi-import', $args);
}

/**
 * Get settings admin URL.
 *
 * @param string $gov Optional government code.
 * @return string URL.
 */
function fi_admin_settings_url(string $gov = ''): string {
	$args = [];

	if ($gov !== '') {
		$args['gov'] = strtoupper(sanitize_key($gov));
	}

	return fi_admin_url('fi-settings', $args);
}

/**
 * Get FI REST API URL.
 *
 * @param string $endpoint Optional endpoint path.
 * @return string URL.
 */
function fi_api_url(string $endpoint = ''): string {
	$endpoint = trim($endpoint, '/');
	$url = home_url('/wp-json/fi/v1/');

	if ($endpoint !== '') {
		$url = trailingslashit($url) . $endpoint;
	}

	return $url;
}

/* -------------------------------------------------------------------------
 * Conservative compatibility wrappers for old names.
 * ---------------------------------------------------------------------- */
