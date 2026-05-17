<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Add "Edit Legislator" link to WordPress admin bar when viewing a legislator page
 * 
 * @author Sam Mittelstaedt <smittelstaedt@jbs.org>
 */

add_action('admin_bar_menu', 'fi_public_admin_bar_edit_legislator', 100);

function fi_public_admin_bar_edit_legislator($wp_admin_bar) {
	// Only show to users with FI management capability
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}
	
	// Only show on legislator single pages (check if fi_entity=legislator)
	if (get_query_var('fi_entity') !== 'legislator') {
		return;
	}
	
	// Get legislator ID from query var
	$legislator_id = (int) get_query_var('fi_legislator_id');
	if (!$legislator_id) {
		return;
	}
	
	// Build edit URL
	$edit_url = admin_url('admin.php?page=fi-legislators&action=edit&legislator_id=' . $legislator_id);
	
	// Add the node to the admin bar
	$wp_admin_bar->add_node([
		'id'    => 'fi-edit-legislator',
		'title' => 'Edit Legislator',
		'href'  => $edit_url,
		'meta'  => [
			'title' => 'Edit this legislator in Freedom Index admin',
			'class' => 'bg-success me-2',
			'target' => '_blank',
		],
	]);
}

add_action('admin_bar_menu', 'fi_public_admin_bar_edit_vote', 100);

function fi_public_admin_bar_edit_vote($wp_admin_bar) {
	// Only show to users with FI management capability
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}
	
	// Only show on vote single pages (check if fi_entity=vote)
	if (get_query_var('fi_entity') !== 'vote') {
		return;
	}
	
	// Get vote ID from query var
	$vote_id = (int) get_query_var('fi_vote_id');
	if (!$vote_id) {
		return;
	}
	
	// Build edit URL using the helper function
	$edit_url = admin_url('admin.php?page=fi-votes&action=edit&vote_id=' . $vote_id);
	
	// Add the node to the admin bar
	$wp_admin_bar->add_node([
		'id'    => 'fi-edit-vote',
		'title' => 'Edit Vote',
		'href'  => $edit_url,
		'meta'  => [
			'title' => 'Edit this vote in Freedom Index admin',
			'class' => 'bg-success me-2',
			'target' => '_blank',
		],
	]);
}

add_action('admin_bar_menu', 'fi_public_admin_bar_edit_report', 100);

function fi_public_admin_bar_edit_report($wp_admin_bar) {
	// Only show to users with FI management capability
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}
	
	// Only show on report single pages (check if fi_entity=report)
	if (get_query_var('fi_entity') !== 'report') {
		return;
	}
	
	// Get report ID from query var
	$report_id = (int) get_query_var('fi_report_id');
	if (!$report_id) {
		return;
	}
	
	// Build edit URL
	$edit_url = admin_url('admin.php?page=fi-reports&action=edit&report_id=' . $report_id);
	
	// Add the node to the admin bar
	$wp_admin_bar->add_node([
		'id'    => 'fi-edit-report',
		'title' => 'Edit Report',
		'href'  => $edit_url,
		'meta'  => [
			'title' => 'Edit this report in Freedom Index admin',
			'class' => 'bg-success me-2',
			'target' => '_blank',
		],
	]);
}