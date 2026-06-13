<?php
/**
 * Freedom Index Plugin Installer
 *
 * Handles plugin activation tasks.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Run plugin activation.
 *
 * Notes:
 * - Fresh installs create schema immediately.
 * - Existing installs avoid heavy dbDelta/ALTER work during activation.
 * - Schema upgrades should be run manually or through gated FI admin tooling.
 *
 * @return void
 */
function fi_plugin_activate(): void {
	global $wpdb;

	$option_key = 'fi_admin_db_version';
	$target     = defined('FI_VERSION') ? FI_VERSION : '0';

	$table  = $wpdb->prefix . 'fi_legislators';
	$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

	if (!$exists) {
		if (function_exists('fi_schema_ensure')) {
			fi_schema_ensure();
			update_option('fi_schema_version', $target, false);
		}
	} else {
		/*
		 * Existing installs may have large FI tables.
		 * Do not run schema upgrades during activation because dbDelta/ALTER
		 * can exceed proxy/server timeouts.
		 */
		update_option('fi_schema_version', $target, false);
		set_transient('fi_schema_deferred_notice', 1, DAY_IN_SECONDS);
	}

	update_option($option_key, $target, true);
}