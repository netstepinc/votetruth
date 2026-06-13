<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin-post handler: clear FI caches under wp-content/jbsfi/
 * Kept separate to keep dashboard/controller files small and task-focused.
 */
function fi_admin_cache_tools_handle_clear(): void {
	if ( ! current_user_can( FI_CAP_MANAGE ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'freedom-scorecard' ) );
	}

	check_admin_referer( 'fi_cache_clear' );

	$type = sanitize_key( $_POST['cache_type'] ?? 'all' );
	if ( $type === '' ) {
		$type = 'all';
	}

	$result = fi_cache_clear( $type );

	// Build a simple notice via query args (dashboard already uses wp-admin UI).
	$args = [
		'page' => 'fi-dashboard',
		'fi_cache_cleared' => 1,
		'fi_cache_type' => $result['type'],
		'fi_cache_files' => $result['cleared'],
		'fi_cache_errors' => $result['errors'],
		'fi_cache_skipped' => $result['skipped'] ? 1 : 0,
	];

	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_fi_cache_clear', 'fi_admin_cache_tools_handle_clear' );

/**
 * Add Admin Bar link: Clear FI Cache (runs fi_clear_disk_cache, then redirects back).
 */
add_action('admin_bar_menu', function ($wp_admin_bar) {
	if (!current_user_can(FI_CAP_MANAGE)) {
		return;
	}
	$url = add_query_arg([
		'action' => 'fi_clear_disk_cache',
		'_wpnonce' => wp_create_nonce('fi_clear_disk_cache'),
	], admin_url('admin-post.php'));
	$wp_admin_bar->add_node([
		'id' => 'fi-clear-cache',
		'parent' => 'root-default',
		'title' => __('Clear FI Cache', 'freedom-scorecard'),
		'meta'  => [
			'title' => 'Clear the public query cache',
			'class' => 'bg-danger me-2 fw-bold',
		],
		'href' => $url,
	]);
}, 99);

/**
 * Admin-post handler for Clear FI Cache (from Admin Bar or direct link).
 */
function fi_admin_post_clear_disk_cache(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die(__('Insufficient permissions.', 'freedom-scorecard'));
	}
	if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'fi_clear_disk_cache')) {
		wp_die(__('Invalid nonce.', 'freedom-scorecard'));
	}
	$result = fi_clear_disk_cache();
	$redirect = wp_get_referer() ?: admin_url();
	$redirect = add_query_arg('fi_cache_cleared', $result['errors'] ? '0' : '1', $redirect);
	wp_safe_redirect($redirect);
	exit;
}
add_action('admin_post_fi_clear_disk_cache', 'fi_admin_post_clear_disk_cache');

/**
 * Clear fi_cache disk cache (same effect as Settings > Clear Disk Cache).
 * Deletes files in FI_DIR_CACHE subdirs: findmy, ajax, legislators, reports, rollcalls, search, sessions, taxonomy, votes, user.
 *
 * @return array{cleared: int, errors: string[]}
 */
function fi_clear_disk_cache(): array {
	$cache_dirs = ['findmy', 'ajax', 'legislators', 'reports', 'rollcalls', 'search', 'sessions', 'taxonomy', 'votes', 'user'];
	$cleared = 0;
	$errors = [];
	if (!defined('FI_DIR_CACHE')) {
		return ['cleared' => 0, 'errors' => ['FI_DIR_CACHE not defined.']];
	}
	foreach ($cache_dirs as $dir) {
		$dir_path = FI_DIR_CACHE . $dir . '/';
		if (!is_dir($dir_path)) {
			continue;
		}
		$files = glob($dir_path . '*');
		if ($files === false) {
			$errors[] = "Failed to read directory: {$dir}";
			continue;
		}
		foreach ($files as $file) {
			if (is_file($file) && unlink($file)) {
				$cleared++;
			} elseif (is_file($file)) {
				$errors[] = 'Failed to delete: ' . basename($file);
			}
		}
	}
	return ['cleared' => $cleared, 'errors' => $errors];
}