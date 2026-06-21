<?php if (!defined('ABSPATH')) { exit; }

/**
 * Register admin menu pages
 */
function fi_admin_menus_register(): void {
	// Main dashboard
	add_menu_page(
		'Freedom Index',
		'Freedom Index',
		FI_CAP_MANAGE,
		'fi-dashboard',
		'fi_admin_dashboard_render',
		'dashicons-chart-line',
		3
	);

	// Submenu pages
	add_submenu_page(
		'fi-dashboard',
		'Dashboard',
		'Dashboard',
		FI_CAP_MANAGE,
		'fi-dashboard',
		'fi_admin_dashboard_render'
	);

	add_submenu_page(
		'fi-dashboard',
		'Votes',
		'Votes',
		FI_CAP_MANAGE,
		'fi-votes',
		'fi_admin_votes_render'
	);

	add_submenu_page(
		'fi-dashboard',
		'Reports',
		'Reports',
		FI_CAP_MANAGE,
		'fi-reports',
		'fi_admin_reports_render'
	);

	add_submenu_page(
		'fi-dashboard',
		'Legislators',
		'Legislators',
		FI_CAP_MANAGE,
		'fi-legislators',
		'fi_admin_legislators_render'
	);

	add_submenu_page(
		'fi-dashboard',
		'Sessions',
		'Sessions',
		FI_CAP_MANAGE,
		'fi-sessions',
		'fi_admin_sessions_render'
	);

	add_submenu_page(
		'fi-dashboard',
		'Districts',
		'Districts',
		FI_CAP_MANAGE,
		'fi-districts',
		'fi_admin_taxonomy_render_districts'
	);

	add_submenu_page(
		'fi-dashboard',
		'Tags',
		'Tags',
		FI_CAP_MANAGE,
		'fi-tags',
		'fi_admin_taxonomy_render_tags'
	);

	add_submenu_page(
		'fi-dashboard',
		'Legiscan Import',
		'Legiscan',
		FI_CAP_MANAGE,
		'fi-legiscan-import',
		'fi_admin_legiscan_render'
	);
/*
if(get_current_user_id() == 1){
	add_submenu_page(
		'fi-dashboard',
		'Legacy Site Migration',
		'Migrate',
		'manage_network_options',
		'fi-migrate',
		'fi_admin_migrate_render'
	);
}
*/
	add_submenu_page(
		'fi-dashboard',
		'Settings',
		'Settings',
		FI_CAP_MANAGE,
		'fi-settings',
		'fi_admin_settings_render'
	);
}

add_action('admin_menu', 'fi_admin_menus_register');