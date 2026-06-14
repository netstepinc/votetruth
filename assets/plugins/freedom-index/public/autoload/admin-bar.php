<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Add "Edit Legislator" link to WordPress admin bar when viewing a legislator page
 * 
 * @author Sam Mittelstaedt <smittelstaedt@jbs.org>
 */

/**
 * Build a scope-switching admin URL.
 * Routes through admin-post fi_switch_scope so the gov is set before the edit screen loads.
 */
function fi_admin_bar_scope_edit_url(string $gov, string $redirect_to): string {
	$args = [
		'action'      => 'fi_switch_scope',
		'gov'         => strtoupper($gov),
		'redirect_to' => rawurlencode($redirect_to),
	];
	return wp_nonce_url(
		add_query_arg($args, admin_url('admin-post.php')),
		'fi_switch_scope',
		'fi_scope_nonce'
	);
}

add_action('admin_bar_menu', 'fi_public_admin_bar_edit_legislator', 100);

function fi_public_admin_bar_edit_legislator($wp_admin_bar) {
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}
	if (get_query_var('fi_entity') !== 'legislator') {
		return;
	}
	$legislator_id = (int) get_query_var('fi_legislator_id');
	if (!$legislator_id) {
		return;
	}

	$legislator = function_exists('fi_legislator_get') ? fi_legislator_get($legislator_id, false) : null;
	$gov = strtoupper((string) ($legislator['gov'] ?? get_query_var('fi_gov') ?? ''));
	$edit_target = admin_url('admin.php?page=fi-legislators&action=edit&legislator_id=' . $legislator_id);
	$href = $gov ? fi_admin_bar_scope_edit_url($gov, $edit_target) : $edit_target;

	$wp_admin_bar->add_node([
		'id'    => 'fi-edit-legislator',
		'title' => 'Edit Legislator',
		'href'  => $href,
		'meta'  => ['title' => 'Edit this legislator in Freedom Index admin'],
	]);
}

add_action('admin_bar_menu', 'fi_public_admin_bar_edit_vote', 100);

function fi_public_admin_bar_edit_vote($wp_admin_bar) {
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}
	if (get_query_var('fi_entity') !== 'vote') {
		return;
	}
	$vote_id = (int) get_query_var('fi_vote_id');
	if (!$vote_id) {
		return;
	}

	$vote = function_exists('fi_vote_get') ? fi_vote_get($vote_id) : null;
	$gov = strtoupper((string) ($vote['gov'] ?? get_query_var('fi_gov') ?? ''));
	$edit_target = admin_url('admin.php?page=fi-votes&action=edit&vote_id=' . $vote_id);
	$href = $gov ? fi_admin_bar_scope_edit_url($gov, $edit_target) : $edit_target;

	$wp_admin_bar->add_node([
		'id'    => 'fi-edit-vote',
		'title' => 'Edit Vote',
		'href'  => $href,
		'meta'  => ['title' => 'Edit this vote in Freedom Index admin'],
	]);
}

add_action('admin_bar_menu', 'fi_public_admin_bar_edit_report', 100);

function fi_public_admin_bar_edit_report($wp_admin_bar) {
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}
	if (get_query_var('fi_entity') !== 'report') {
		return;
	}
	$report_id = (int) get_query_var('fi_report_id');
	if (!$report_id) {
		return;
	}

	$report = function_exists('fi_report_get') ? fi_report_get($report_id) : null;
	$gov = strtoupper((string) ($report['gov'] ?? get_query_var('fi_gov') ?? ''));
	$edit_target = admin_url('admin.php?page=fi-reports&action=edit&report_id=' . $report_id);
	$href = $gov ? fi_admin_bar_scope_edit_url($gov, $edit_target) : $edit_target;

	$wp_admin_bar->add_node([
		'id'    => 'fi-edit-report',
		'title' => 'Edit Report',
		'href'  => $href,
		'meta'  => ['title' => 'Edit this report in Freedom Index admin'],
	]);
}