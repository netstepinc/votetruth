<?php
/*
 * Freedom Index Database Schema
 *
 * Straight function version of the former FIAdmin\Schema class file.
 *
 * Handles:
 * - Creating/updating FI custom tables through dbDelta().
 * - Lightweight schema upgrade checks.
 * - Opt-in expensive legacy ALTER operations.
 * - Admin-only schema version checks with a transient lock.
 *
 * Notes:
 * - Slug columns remain for backward compatibility/import reference, but are deprecated.
 * - Public/entity routing is ID-based.
 * - Removed broken indexes that referenced columns not present in their tables.
 * Refactored and tuned the schema file.
Key adjustments:
	Removed the FIAdmin\Schema class/namespace wrapper.
Preserved the public schema entry point:
	fi_schema_ensure()
Added schema helpers:
	fi_schema_column_exists()
	fi_schema_index_exists()
	fi_schema_ensure_gov_scoped_slug_indexes()
	fi_schema_ensure_legacy_id_varchar_columns()
	fi_schema_ensure_reports_format_column()
	fi_schema_ensure_legacy_image_url_column()
	fi_schema_ensure_legacy_redirect_entity_columns()
	fi_schema_ensure_votes_date_voted_datetime()
	fi_schema_should_run_admin_check()
	fi_schema_admin_init_maybe_ensure()
Important defects fixed:
	gov_idx and gov_chamber indexes re-added to fi_legislators:
	gov column now exists as a cached session field (added 2026-06).
Fixed fi_legacy_redirects by adding:
	entity_type VARCHAR(50) NULL,
	entity_id BIGINT UNSIGNED NULL
because the schema had:
	KEY entity_idx (entity_type, entity_id)
but those columns did not exist.
Preserved slug columns/indexes only as deprecated compatibility fields.
Kept expensive ALTER operations behind:

apply_filters('fi_schema_allow_expensive_alters', false)
Wrapped the schema lock cleanup in finally so a failed schema run does not leave the transient lock stuck.
Restored $wpdb->suppress_errors() to its previous state instead of hard-setting it to false.
*/

if (!defined('ABSPATH')) exit;

/**
 * Ensure FI database schema is up to date.
 *
 * @return void
 */
function fi_schema_ensure(): void {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	global $wpdb;

	$charset = $wpdb->get_charset_collate();
	$previous_suppress = $wpdb->suppress_errors(true);
	$sql = [];

	// Legislators: career-spanning person records.
	// Cached session fields (session_id, gov, state, chamber, district, party) mirror the most
	// recently-served fi_legislator_sessions row for fast lookups. Always NULL-able for existing rows.
	// Written by the import process. Never points to a child session.
	$sql[] = "CREATE TABLE {$wpdb->prefix}fi_legislators (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		legacy_id VARCHAR(32) NULL,
		first_name VARCHAR(120) NOT NULL,
		middle_name VARCHAR(120) NULL,
		last_name VARCHAR(120) NOT NULL,
		display_name VARCHAR(255) NOT NULL,
		bioguide_id VARCHAR(16) NULL,
		lis_id VARCHAR(16) NULL,
		legiscan_id BIGINT UNSIGNED NULL,
		govtrack_id VARCHAR(20) NULL,
		votesmart_id VARCHAR(20) NULL,
		ballotpedia_id VARCHAR(100) NULL,
		openstates_id VARCHAR(100) NULL,
		image_id BIGINT UNSIGNED NULL,
		image_url VARCHAR(255) NULL,
		session_id BIGINT UNSIGNED NULL,
		gov VARCHAR(2) NULL,
		state VARCHAR(2) NULL,
		chamber ENUM('S','H') NULL,
		district VARCHAR(32) NULL,
		party VARCHAR(64) NULL,
		score TINYINT UNSIGNED NULL,
		score_data JSON NULL,
		score_date DATETIME NULL,
		audit_log JSON NULL,
		meta JSON NULL,
		date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		date_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		legacy_image_url VARCHAR(255) NULL,
		PRIMARY KEY (id),
		UNIQUE KEY bioguide_unique (bioguide_id),
		KEY legacy_id_idx (legacy_id),
		KEY legiscan_id_idx (legiscan_id),
		KEY votesmart_id_idx (votesmart_id),
		KEY ballotpedia_id_idx (ballotpedia_id),
		KEY openstates_id_idx (openstates_id),
		KEY last_first (last_name, first_name),
		KEY display_name_idx (display_name(64)),
		KEY legacy_image_url_idx (legacy_image_url(32)),
		KEY image_id_idx (image_id),
		KEY score_idx (score),
		KEY session_id_idx (session_id),
		KEY gov_idx (gov),
		KEY gov_chamber (gov, chamber)
	) $charset;";

	// Sessions: government-bounded time buckets.
	$sql[] = "CREATE TABLE {$wpdb->prefix}fi_sessions (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		parent_id BIGINT UNSIGNED NULL DEFAULT NULL,
		legacy_id VARCHAR(32) NULL,
		legiscan_id BIGINT UNSIGNED NULL,
		gov VARCHAR(2) NOT NULL,
		slug VARCHAR(190) NULL,
		name VARCHAR(255) NOT NULL,
		date_start DATE NULL,
		date_end DATE NULL,
		is_current TINYINT(1) NOT NULL DEFAULT 0,
		meta JSON NULL,
		status ENUM('publish','draft') NOT NULL DEFAULT 'draft',
		date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		date_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY legacy_id_idx (legacy_id),
		KEY legiscan_id_idx (legiscan_id),
		KEY parent_id_idx (parent_id),
		KEY gov_status_idx (gov, status),
		KEY gov_current_idx (gov, is_current),
		KEY dates (date_start, date_end),
		KEY gov_dates (gov, date_start, date_end)
	) $charset;";

	// Legislator/session records: chamber/party/district/image per session.
	$sql[] = "CREATE TABLE {$wpdb->prefix}fi_legislator_sessions (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		legislator_id BIGINT UNSIGNED NOT NULL,
		session_id BIGINT UNSIGNED NOT NULL,
		legacy_id VARCHAR(32) NULL,
		gov VARCHAR(2) NOT NULL,
		state VARCHAR(2) NULL,
		chamber ENUM('S','H') NOT NULL,
		district VARCHAR(32) NULL,
		party VARCHAR(64) NULL,
		image_id BIGINT UNSIGNED NULL,
		date_start DATE NULL,
		date_end DATE NULL,
		score TINYINT UNSIGNED NULL,
		score_data JSON NULL,
		score_date DATETIME NULL,
		meta JSON NULL,
		date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		date_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_leg_sess_chamber (legislator_id, session_id, chamber, district, date_start, date_end),
		KEY legacy_id_idx (legacy_id),
		KEY sess_lookup (session_id, chamber, district),
		KEY gov_session (gov, session_id),
		KEY gov_chamber (gov, chamber),
		KEY state_idx (state),
		KEY party_idx (party),
		KEY image_id_idx (image_id),
		KEY score_idx (score),
		KEY leg_score (legislator_id, score)
	) $charset;";

	// Votes: scored items.
	$sql[] = "CREATE TABLE {$wpdb->prefix}fi_votes (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		legacy_id VARCHAR(32) NULL,
		legiscan_bid BIGINT UNSIGNED NULL,
		legiscan_rcid BIGINT UNSIGNED NULL,
		session_id BIGINT UNSIGNED NOT NULL,
		gov VARCHAR(2) NOT NULL,
		chamber ENUM('S','H') NOT NULL,
		title VARCHAR(512) NOT NULL,
		slug VARCHAR(190) NULL,
		bill_number VARCHAR(512) NOT NULL,
		constitutional ENUM('Y','N','U') NOT NULL,
		rollcall_number VARCHAR(64) NULL,
		rollcall_data JSON NULL,
		status ENUM('publish','draft','pending','trash') NOT NULL DEFAULT 'publish',
		date_voted DATETIME NOT NULL,
		date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		date_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		meta JSON NULL,
		PRIMARY KEY (id),
		KEY legacy_id_idx (legacy_id),
		KEY legiscan_bid_idx (legiscan_bid),
		KEY legiscan_rcid_idx (legiscan_rcid),
		KEY gov_session (gov, session_id),
		KEY session_chamber (session_id, chamber),
		KEY gov_chamber_status (gov, chamber, status),
		KEY status_idx (status),
		KEY date_voted_idx (date_voted),
		KEY bill_number_idx (bill_number(64))
	) $charset;";

	// Roll-call records: who voted how.
	$sql[] = "CREATE TABLE {$wpdb->prefix}fi_voterc (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		vote_id BIGINT UNSIGNED NOT NULL,
		legislator_id BIGINT UNSIGNED NOT NULL,
		`cast` ENUM('Y','N','P','X') NOT NULL,
		is_override TINYINT(1) NOT NULL DEFAULT 0,
		date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_vote_leg (vote_id, legislator_id),
		KEY leg_idx (legislator_id),
		KEY vote_cast_idx (vote_id, `cast`),
		KEY override_idx (is_override)
	) $charset;";

	$sql[] = "CREATE TABLE {$wpdb->prefix}fi_vote_tags (
		vote_id BIGINT UNSIGNED NOT NULL,
		tag_id BIGINT UNSIGNED NOT NULL,
		PRIMARY KEY (vote_id, tag_id),
		KEY tag_idx (tag_id)
	) $charset;";

	// System logging table.
	$sql[] = "CREATE TABLE {$wpdb->prefix}fi_log (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		type VARCHAR(50) NOT NULL,
		severity ENUM('info','warning','error') NOT NULL DEFAULT 'info',
		message TEXT NOT NULL,
		context JSON NULL,
		gov CHAR(2) NULL,
		session_id BIGINT UNSIGNED NULL,
		status ENUM('open','closed','dismissed') NOT NULL DEFAULT 'open',
		date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY type_idx (type),
		KEY severity_idx (severity),
		KEY status_idx (status),
		KEY gov_idx (gov),
		KEY session_idx (session_id),
		KEY date_created_idx (date_created)
	) $charset;";

	// Reports: shareable public report payloads.
	$sql[] = "CREATE TABLE {$wpdb->prefix}fi_reports (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		legacy_id VARCHAR(32) NULL,
		title VARCHAR(255) NOT NULL,
		title_menu VARCHAR(64) NULL DEFAULT NULL,
		slug VARCHAR(32) NULL,
		gov CHAR(2) NOT NULL,
		session_id BIGINT UNSIGNED NOT NULL,
		owner_user_id BIGINT UNSIGNED NULL,
		payload_json MEDIUMTEXT NOT NULL,
		format VARCHAR(32) NOT NULL DEFAULT 'scorecard',
		status ENUM('publish','draft','pending','trash') NOT NULL DEFAULT 'draft',
		date_publish DATETIME NULL,
		date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		meta JSON NULL,
		PRIMARY KEY (id),
		KEY legacy_id_idx (legacy_id),
		KEY session_idx (session_id),
		KEY gov_session (gov, session_id),
		KEY format_idx (format),
		KEY owner_idx (owner_user_id),
		KEY status_idx (status),
		KEY date_publish_idx (date_publish)
	) $charset;";

	// Legacy redirects.
	// NOTE: entity_type/entity_id columns are included because entity_idx references them.
	$sql[] = "CREATE TABLE {$wpdb->prefix}fi_legacy_redirects (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		gov CHAR(2) NULL,
		legacy_path VARCHAR(255) NOT NULL,
		entity_type VARCHAR(50) NULL,
		entity_id BIGINT UNSIGNED NULL,
		target_slug VARCHAR(190) NULL,
		status SMALLINT UNSIGNED NOT NULL DEFAULT 301,
		hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
		date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY legacy_path_unique (legacy_path),
		KEY gov_idx (gov),
		KEY entity_idx (entity_type, entity_id),
		KEY hits_idx (hits)
	) $charset;";

	// Consolidated taxonomy: tags and districts.
	$sql[] = "CREATE TABLE {$wpdb->prefix}fi_taxonomy (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		legacy_id VARCHAR(32) NULL,
		gov CHAR(2) NOT NULL,
		taxonomy ENUM('tag', 'district') NOT NULL,
		name VARCHAR(255) NOT NULL,
		slug VARCHAR(255) NULL,
		meta JSON NULL,
		date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		date_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY legacy_id_idx (legacy_id),
		KEY gov_idx (gov),
		KEY taxonomy_idx (taxonomy),
		KEY name_idx (name),
		KEY gov_taxonomy (gov, taxonomy)
	) $charset;";

	// User Lists: personal legislator lists.
	$sql[] = "CREATE TABLE {$wpdb->prefix}fi_user_lists (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT UNSIGNED NOT NULL,
		name VARCHAR(255) NOT NULL,
		description TEXT NULL,
		legislators JSON NULL,
		is_public TINYINT(1) NOT NULL DEFAULT 1,
		meta JSON NULL,
		date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		date_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY user_idx (user_id),
		KEY public_idx (is_public),
		KEY date_created_idx (date_created)
	) $charset;";

	foreach ($sql as $statement) {
		dbDelta($statement);
	}

	$allow_expensive_alters = (bool) apply_filters('fi_schema_allow_expensive_alters', false);
	if ($allow_expensive_alters) {
		fi_schema_ensure_gov_scoped_slug_indexes();
		fi_schema_ensure_legacy_id_varchar_columns();
		fi_schema_ensure_legacy_image_url_column();
		fi_schema_ensure_legacy_redirect_entity_columns();
	}

	// Lightweight checks that should not lock large tables for long.
	fi_schema_ensure_reports_format_column();
	fi_schema_ensure_votes_date_voted_datetime();

	$wpdb->suppress_errors($previous_suppress);
}

/**
 * Check whether a column exists.
 *
 * @param string $table Table name.
 * @param string $column Column name.
 * @return bool True if column exists.
 */
function fi_schema_column_exists(string $table, string $column): bool {
	global $wpdb;

	$exists = $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*)
		FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
		DB_NAME,
		$table,
		$column
	));

	return (int) $exists > 0;
}

/**
 * Check whether an index exists.
 *
 * @param string $table Table name.
 * @param string $index Index name.
 * @return bool True if index exists.
 */
function fi_schema_index_exists(string $table, string $index): bool {
	global $wpdb;

	$exists = $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*)
		FROM INFORMATION_SCHEMA.STATISTICS
		WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
		DB_NAME,
		$table,
		$index
	));

	return (int) $exists > 0;
}

/**
 * Ensure session/report slug indexes are gov-scoped.
 *
 * @return void
 */
function fi_schema_ensure_gov_scoped_slug_indexes(): void {
	global $wpdb;

	$tables = [
		$wpdb->prefix . 'fi_sessions' => ['gov', 'slug'],
		$wpdb->prefix . 'fi_reports'  => ['gov', 'slug'],
	];

	foreach ($tables as $table => $cols) {
		$indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}` WHERE Key_name = 'slug'");
		if (!is_array($indexes) || empty($indexes)) {
			$wpdb->query("ALTER TABLE `{$table}` ADD UNIQUE KEY slug (`" . implode('`,`', $cols) . "`)");
			continue;
		}

		$is_unique = true;
		$existing_cols = [];
		foreach ($indexes as $idx) {
			if ((int) ($idx->Non_unique ?? 1) !== 0) {
				$is_unique = false;
			}
			$seq = (int) ($idx->Seq_in_index ?? 0);
			$col = (string) ($idx->Column_name ?? '');
			if ($seq > 0 && $col !== '') {
				$existing_cols[$seq] = $col;
			}
		}

		ksort($existing_cols);
		$existing_cols = array_values($existing_cols);

		if ($is_unique && $existing_cols === $cols) {
			continue;
		}

		$wpdb->query("ALTER TABLE `{$table}` DROP INDEX slug");
		$wpdb->query("ALTER TABLE `{$table}` ADD UNIQUE KEY slug (`" . implode('`,`', $cols) . "`)");
	}
}

/**
 * Ensure legacy_id columns are VARCHAR(32).
 *
 * @return void
 */
function fi_schema_ensure_legacy_id_varchar_columns(): void {
	global $wpdb;

	$tables = [
		$wpdb->prefix . 'fi_legislators',
		$wpdb->prefix . 'fi_sessions',
		$wpdb->prefix . 'fi_legislator_sessions',
		$wpdb->prefix . 'fi_votes',
		$wpdb->prefix . 'fi_reports',
		$wpdb->prefix . 'fi_taxonomy',
	];

	foreach ($tables as $table) {
		$info = $wpdb->get_row($wpdb->prepare(
			"SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
			FROM INFORMATION_SCHEMA.COLUMNS
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'legacy_id'",
			DB_NAME,
			$table
		));

		if (!$info) {
			continue;
		}

		$data_type = strtolower((string) ($info->DATA_TYPE ?? ''));
		$max_len = (int) ($info->CHARACTER_MAXIMUM_LENGTH ?? 0);

		if ($data_type !== 'varchar' || $max_len < 32) {
			$wpdb->query("ALTER TABLE `{$table}` MODIFY COLUMN legacy_id VARCHAR(32) NULL");
		}
	}
}

/**
 * Ensure fi_reports has format column and index.
 *
 * @return void
 */
function fi_schema_ensure_reports_format_column(): void {
	global $wpdb;

	$table = $wpdb->prefix . 'fi_reports';

	if (!fi_schema_column_exists($table, 'format')) {
		$wpdb->query("ALTER TABLE `{$table}` ADD COLUMN format VARCHAR(32) NOT NULL DEFAULT 'scorecard' AFTER payload_json");
	}

	if (!fi_schema_index_exists($table, 'format_idx')) {
		$wpdb->query("ALTER TABLE `{$table}` ADD KEY format_idx (format)");
	}
}

/**
 * Ensure fi_legislators has legacy_image_url column and index.
 *
 * @return void
 */
function fi_schema_ensure_legacy_image_url_column(): void {
	global $wpdb;

	$table = $wpdb->prefix . 'fi_legislators';

	if (!fi_schema_column_exists($table, 'legacy_image_url')) {
		$after = fi_schema_column_exists($table, 'openstates_id') ? ' AFTER openstates_id' : '';
		$wpdb->query("ALTER TABLE `{$table}` ADD COLUMN legacy_image_url VARCHAR(255) NULL{$after}");
	}

	if (!fi_schema_index_exists($table, 'legacy_image_url_idx')) {
		$wpdb->query("ALTER TABLE `{$table}` ADD INDEX legacy_image_url_idx (legacy_image_url(32))");
	}
}

/**
 * Ensure legacy redirect entity columns exist because entity_idx references them.
 *
 * @return void
 */
function fi_schema_ensure_legacy_redirect_entity_columns(): void {
	global $wpdb;

	$table = $wpdb->prefix . 'fi_legacy_redirects';

	if (!fi_schema_column_exists($table, 'entity_type')) {
		$wpdb->query("ALTER TABLE `{$table}` ADD COLUMN entity_type VARCHAR(50) NULL AFTER legacy_path");
	}

	if (!fi_schema_column_exists($table, 'entity_id')) {
		$wpdb->query("ALTER TABLE `{$table}` ADD COLUMN entity_id BIGINT UNSIGNED NULL AFTER entity_type");
	}

	if (!fi_schema_index_exists($table, 'entity_idx')) {
		$wpdb->query("ALTER TABLE `{$table}` ADD KEY entity_idx (entity_type, entity_id)");
	}
}

/**
 * Ensure votes.date_voted is DATETIME.
 *
 * @return void
 */
function fi_schema_ensure_votes_date_voted_datetime(): void {
	global $wpdb;

	$table = $wpdb->prefix . 'fi_votes';

	$column_info = $wpdb->get_row($wpdb->prepare(
		"SELECT DATA_TYPE
		FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'date_voted'",
		DB_NAME,
		$table
	));

	if ($column_info && strtolower((string) $column_info->DATA_TYPE) === 'date') {
		$wpdb->query("ALTER TABLE `{$table}` MODIFY COLUMN date_voted DATETIME NOT NULL");
	}
}

/**
 * Decide whether schema ensure should run on the current admin request.
 *
 * @return bool True when schema ensure should run.
 */
function fi_schema_should_run_admin_check(): bool {
	if (!is_admin()) {
		return false;
	}

	$cap = defined('FI_CAP_MANAGE') ? FI_CAP_MANAGE : 'manage_options';
	if (!current_user_can($cap)) {
		return false;
	}

	$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
	return $page !== '' && str_starts_with($page, 'fi-');
}

/**
 * Run schema ensure on FI admin pages when version changed.
 *
 * @return void
 */
function fi_schema_admin_init_maybe_ensure(): void {
	if (function_exists('fi_trace')) {
		fi_trace('schema:admin_init:enter');
	}

	if (!fi_schema_should_run_admin_check()) {
		if (function_exists('fi_trace')) {
			$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
			fi_trace('schema:admin_init:skip:not_applicable', ['page' => $page]);
		}
		return;
	}

	$target = defined('FI_VERSION') ? (string) FI_VERSION : '0';
	$current = (string) get_option('fi_schema_version', '');

	if ($current === $target) {
		if (function_exists('fi_trace')) {
			fi_trace('schema:admin_init:skip:version_ok', ['current' => $current, 'target' => $target]);
		}
		return;
	}

	if (get_transient('fi_schema_ensure_lock')) {
		if (function_exists('fi_trace')) {
			fi_trace('schema:admin_init:skip:lock_present');
		}
		return;
	}

	set_transient('fi_schema_ensure_lock', 1, 5 * MINUTE_IN_SECONDS);

	try {
		if (function_exists('fi_trace')) {
			fi_trace('schema:ensure:start', ['current' => $current, 'target' => $target]);
		}

		fi_schema_ensure();
		update_option('fi_schema_version', $target, false);

		if (function_exists('fi_trace')) {
			fi_trace('schema:ensure:done');
		}
	} finally {
		delete_transient('fi_schema_ensure_lock');
	}
}
add_action('admin_init', 'fi_schema_admin_init_maybe_ensure');