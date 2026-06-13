<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Freedom Index capabilities + role bootstrap.
 *
 * Goal: allow FI staff to manage FI without being full WP administrators.
 */
function fi_admin_caps_bootstrap(): void {
	// Ensure the capability exists for Administrators (site owners).
	$admin_role = get_role('administrator');
	if ($admin_role && defined('FI_CAP_MANAGE')) {
		$admin_role->add_cap(FI_CAP_MANAGE);
	}

	// Create/update FI Manager role.
	if (!defined('FI_ROLE_MANAGER') || !defined('FI_CAP_MANAGE')) {
		return;
	}

	$role = get_role(FI_ROLE_MANAGER);
	if (!$role) {
		add_role(FI_ROLE_MANAGER, 'Freedom Index Manager', [
			'read' => true,
			FI_CAP_MANAGE => true,
		]);
	} else {
		$role->add_cap(FI_CAP_MANAGE);
		$role->add_cap('read');
	}
}
add_action('init', 'fi_admin_caps_bootstrap');