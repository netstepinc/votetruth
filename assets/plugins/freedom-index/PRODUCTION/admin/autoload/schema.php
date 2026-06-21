<?php
namespace FI\Admin{

	if (!defined('ABSPATH')) exit;
	use wpdb;

	final class Schema {

		public static function ensure(): void {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			global $wpdb;
			$charset = $wpdb->get_charset_collate();

			// Suppress dbDelta errors for duplicate keys (restore after)
			// Summary: dbDelta can emit noisy warnings; we still want activation to proceed.
			$wpdb->suppress_errors(true);
			
			// Initialize SQL array
			$sql = [];
			
			// Legislators (career-spanning person)
			$sql[] = "CREATE TABLE {$wpdb->prefix}fi_legislators (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				legacy_id VARCHAR(32) NULL,
				first_name VARCHAR(120) NOT NULL,
				middle_name VARCHAR(120) NULL,
				last_name VARCHAR(120) NOT NULL,
				display_name VARCHAR(255) NOT NULL,
				-- External Reference IDs
				bioguide_id VARCHAR(16) NULL,
				lis_id VARCHAR(16) NULL, -- Legislative Information System ID
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
				KEY legacy_image_url_idx (legacy_image_url(32)),
				KEY image_id_idx (image_id),
				KEY score_idx (score),
				KEY gov_idx (gov)
			) $charset;";

			// Sessions (government-bounded time buckets)
			$sql[] = "CREATE TABLE {$wpdb->prefix}fi_sessions (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				parent_id BIGINT UNSIGNED NULL DEFAULT NULL,
				legacy_id VARCHAR(32) NULL,
				legiscan_id BIGINT UNSIGNED NULL,
				gov VARCHAR(2) NOT NULL,
				slug VARCHAR(190) NOT NULL,
				name VARCHAR(255) NOT NULL,
				date_start DATE NULL,
				date_end DATE NULL,
				is_current TINYINT(1) NOT NULL DEFAULT 0,
				meta JSON NULL,
				status ENUM('publish','draft') NOT NULL DEFAULT 'draft',
				date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				date_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY slug (gov, slug),
				KEY legacy_id_idx (legacy_id),
				KEY legiscan_id_idx (legiscan_id),
				KEY gov_idx (gov),
				KEY parent_id_idx (parent_id),
				KEY status_idx (status),
				KEY dates (date_start, date_end)
			) $charset;";

			// Legislator ↔ Session (chamber/party/image per session)
			//chamber = chamber (H=House, S=Senate)
			//state = state code for congressional legislators (US only, NULL for state legislators)
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
				KEY sess_lookup (session_id, chamber, district),
				KEY leg_lookup (legislator_id),
				KEY gov_idx (gov),
				KEY state_idx (state),
				KEY party_idx (party),
				KEY score_idx (score),
				KEY leg_score (legislator_id, score)
			) $charset;";

			// Votes (scored items)
			$sql[] = "CREATE TABLE {$wpdb->prefix}fi_votes (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				legacy_id VARCHAR(32) NULL,
				legiscan_bid BIGINT UNSIGNED NULL,
				legiscan_rcid BIGINT UNSIGNED NULL,
				session_id BIGINT UNSIGNED NOT NULL,
				gov VARCHAR(2) NOT NULL,
				chamber ENUM('S','H') NOT NULL,
				title VARCHAR(512) NOT NULL,
				slug VARCHAR(190) NOT NULL,
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
				UNIQUE KEY uniq_vote_slug (session_id, slug),
				KEY legacy_id_idx (legacy_id),
				KEY legiscan_bid_idx (legiscan_bid),
				KEY legiscan_rcid_idx (legiscan_rcid),
				KEY gov_idx (gov),
				KEY gov_session (gov, session_id),
				KEY date_voted_idx (date_voted)
			) $charset;";

			// Roll-call (who voted how)
			$sql[] = "CREATE TABLE {$wpdb->prefix}fi_voterc (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				vote_id BIGINT UNSIGNED NOT NULL,
				legislator_id BIGINT UNSIGNED NOT NULL,
				`cast` ENUM('Y','N','P','X') NOT NULL,
				is_override TINYINT(1) NOT NULL DEFAULT 0,
				date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY uniq_vote_leg (vote_id, legislator_id),
				KEY vote_idx (vote_id),
				KEY leg_idx (legislator_id)
			) $charset;";

			$sql[] = "CREATE TABLE {$wpdb->prefix}fi_vote_tags (
				vote_id BIGINT UNSIGNED NOT NULL,
				tag_id BIGINT UNSIGNED NOT NULL,
				PRIMARY KEY (vote_id, tag_id),
				KEY vote_idx (vote_id),
				KEY tag_idx (tag_id)
			) $charset;";

			// System logging table
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
				KEY gov_idx (gov)
			) $charset;";

			// Shareable Reports (public by slug, scoped to session)
			$sql[] = "CREATE TABLE {$wpdb->prefix}fi_reports (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				legacy_id VARCHAR(32) NULL,
				title VARCHAR(255) NOT NULL,
				title_menu VARCHAR(64) NULL DEFAULT NULL,
				slug VARCHAR(32) NOT NULL,
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
				UNIQUE KEY slug (gov, slug),
				KEY legacy_id_idx (legacy_id),
				KEY gov_idx (gov),
				KEY session_idx (session_id),
				KEY gov_session (gov, session_id),
				KEY format_idx (format),
				KEY owner_idx (owner_user_id),
				KEY status_idx (status),
				KEY date_publish_idx (date_publish),
				KEY title_idx (title)
			) $charset;";

			// Legacy redirects
			// IRRELEVANT domain VARCHAR(128) NULL DEFAULT NULL, because redirects change the URL to the current domain
			$sql[] = "CREATE TABLE {$wpdb->prefix}fi_legacy_redirects (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				gov CHAR(2) NULL,
				legacy_path VARCHAR(255) NOT NULL,
				target_slug VARCHAR(190) NULL,
				status SMALLINT UNSIGNED NOT NULL DEFAULT 301,
				hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
				date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY legacy_path_unique (legacy_path),
				KEY gov_idx (gov),
				KEY entity_idx (entity_type, entity_id)
			) $charset;";

			// Consolidated Taxonomy (parties, tags, districts) - functions are specific to this table so 'tag' should not conflict with WP tag taxonomy.
			$sql[] = "CREATE TABLE {$wpdb->prefix}fi_taxonomy (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				legacy_id VARCHAR(32) NULL,
				gov CHAR(2) NOT NULL,
				taxonomy ENUM('tag', 'district') NOT NULL,
				name VARCHAR(255) NOT NULL,
				slug VARCHAR(255) NOT NULL,
				meta JSON NULL,
				date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				date_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY gov_taxonomy_slug (gov, taxonomy, slug),
				KEY legacy_id_idx (legacy_id),
				KEY gov_idx (gov),
				KEY taxonomy_idx (taxonomy),
				KEY name_idx (name),
				KEY gov_taxonomy (gov, taxonomy)
			) $charset;";

		// User Lists (personal legislator lists)
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
			KEY user_idx (user_id)
		) $charset;";


			foreach ($sql as $statement) {
				dbDelta($statement);
			}

			// Expensive upgrade steps (ALTER TABLE on large tables) are opt-in.
			// Summary: On production, these can exceed proxy timeouts and make plugin activation appear to "hang".
			$allow_expensive_alters = (bool) apply_filters('fi_schema_allow_expensive_alters', false);
			if ($allow_expensive_alters) {
				// Enforce gov-scoped uniqueness for slug indexes (dbDelta cannot reliably drop old indexes)
				self::ensure_gov_scoped_slug_indexes();
				// Ensure all legacy_id columns are VARCHAR (for gov-prefixed keys like "ND-1256")
				self::ensure_legacy_id_varchar_columns();
				// Ensure fi_legislators.legacy_image_url exists (for simple image import queueing)
				self::ensure_legacy_image_url_column();
			}
			
			// Ensure fi_reports.format column exists (indexed report type: scorecard | freedomindex)
			self::ensure_reports_format_column();

			// Migrate date_voted from DATE to DATETIME if needed
			$column_info = $wpdb->get_row($wpdb->prepare(
				"SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = %s 
				AND TABLE_NAME = %s 
				AND COLUMN_NAME = 'date_voted'",
				DB_NAME,
				$wpdb->prefix . 'fi_votes'
			));
			
			if ($column_info && $column_info->DATA_TYPE === 'date') {
				// Column exists as DATE, migrate to DATETIME
				$wpdb->query("ALTER TABLE {$wpdb->prefix}fi_votes MODIFY COLUMN date_voted DATETIME NOT NULL");
			}
			
			// Restore error suppression
			$wpdb->suppress_errors(false);
		}


	/**
	 * Ensure session/report slugs are unique per government (gov, slug), not globally.
	 * Summary: earlier schema had UNIQUE(slug) which causes collisions across states.
	 */
	private static function ensure_gov_scoped_slug_indexes(): void {
		global $wpdb;

		$tables = [
			$wpdb->prefix . 'fi_sessions' => ['gov', 'slug'],
			$wpdb->prefix . 'fi_reports' => ['gov', 'slug'],
		];

		foreach ($tables as $table => $cols) {
			// Find existing UNIQUE index named 'slug' and its column order.
			$indexes = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'slug'");
			if (!is_array($indexes) || empty($indexes)) {
				// No slug unique index yet; add it.
				$wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY slug (" . implode(',', $cols) . ")");
				continue;
			}

			$is_unique = true;
			$existing_cols = [];
			foreach ($indexes as $idx) {
				// Non_unique = 0 means UNIQUE index
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

			// If it's already UNIQUE(gov,slug) we're done.
			if ($is_unique && $existing_cols === $cols) {
				continue;
			}

			// Otherwise, drop and recreate as UNIQUE(gov,slug).
			$wpdb->query("ALTER TABLE {$table} DROP INDEX slug");
			$wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY slug (" . implode(',', $cols) . ")");
		}
	}

		/**
		 * Ensure all legacy_id columns are VARCHAR so we can store gov-prefixed keys like "ND-1256".
		 *
		 * Summary:
		 * - Multisite exports collide on numeric post IDs / term IDs across sites.
		 * - We store legacy_id as a deterministic string "{GOV}-{id}" for 100% accurate matching.
		 * - dbDelta is unreliable for MODIFY COLUMN operations, so we enforce via INFORMATION_SCHEMA + ALTER.
		 */
		private static function ensure_legacy_id_varchar_columns(): void {
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
					// Preserve NULLability.
					$wpdb->query("ALTER TABLE {$table} MODIFY COLUMN legacy_id VARCHAR(32) NULL");
				}
			}
		}

		/**
		 * Ensure fi_legislators has legacy_image_url column + index.
		 *
		 * Summary:
		 * - JSON migrator stores source image_url here (instead of meta queues).
		 * - A simple image fetcher can process one row at a time and clear legacy_image_url when done.
		 */
		/**
		 * Ensure fi_reports has format column (scorecard | freedomindex). Hard break from payload-based format.
		 */
		private static function ensure_reports_format_column(): void {
			global $wpdb;
			$table = $wpdb->prefix . 'fi_reports';
			$exists = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'format'",
				DB_NAME,
				$table
			));
			if (!$exists) {
				$wpdb->query("ALTER TABLE {$table} ADD COLUMN format VARCHAR(32) NOT NULL DEFAULT 'scorecard' AFTER payload_json");
				$wpdb->query("ALTER TABLE {$table} ADD KEY format_idx (format)");
			}
		}

		private static function ensure_legacy_image_url_column(): void {
			global $wpdb;

			$table = $wpdb->prefix . 'fi_legislators';

			$exists = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*)
				 FROM INFORMATION_SCHEMA.COLUMNS
				 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'legacy_image_url'",
				DB_NAME,
				$table
			));
			if (!$exists) {
				$wpdb->query("ALTER TABLE {$table} ADD COLUMN legacy_image_url VARCHAR(255) NULL AFTER openstates_id");
			}

			$idx = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*)
				 FROM INFORMATION_SCHEMA.STATISTICS
				 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'legacy_image_url_idx'",
				DB_NAME,
				$table
			));
			if (!$idx) {
				$wpdb->query("ALTER TABLE {$table} ADD INDEX legacy_image_url_idx (legacy_image_url(32))");
			}
		}

}
}

namespace {
	/**
	 * Ensure database schema is up to date
	 */
	function fi_schema_ensure(): void {
		\FI\Admin\Schema::ensure();
	}

	// Only run schema check in admin context, and only when needed.
	// Summary: dbDelta/INFORMATION_SCHEMA checks can be expensive on shared hosting; avoid running them on every admin request.
	add_action('admin_init', function() {
		if (function_exists('fi_trace')) {
			fi_trace('schema:admin_init:enter');
		}

		if (!current_user_can(FI_CAP_MANAGE)) {
			if (function_exists('fi_trace')) {
				fi_trace('schema:admin_init:skip:not_capable');
			}
			return;
		}

		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
		if ($page === '' || strpos($page, 'fi-') !== 0) {
			// Only run when visiting FI admin pages.
			if (function_exists('fi_trace')) {
				fi_trace('schema:admin_init:skip:not_fi_page', ['page' => $page]);
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

		// Simple lock to prevent concurrent schema runs.
		if (get_transient('fi_schema_ensure_lock')) {
			if (function_exists('fi_trace')) {
				fi_trace('schema:admin_init:skip:lock_present');
			}
			return;
		}
		set_transient('fi_schema_ensure_lock', 1, 5 * MINUTE_IN_SECONDS);

		if (function_exists('fi_trace')) {
			fi_trace('schema:ensure:start', ['current' => $current, 'target' => $target]);
		}
		fi_schema_ensure();
		if (function_exists('fi_trace')) {
			fi_trace('schema:ensure:done');
		}

		update_option('fi_schema_version', $target, false);
		delete_transient('fi_schema_ensure_lock');
		if (function_exists('fi_trace')) {
			fi_trace('schema:admin_init:exit', ['updated_version' => $target]);
		}
	});
}

