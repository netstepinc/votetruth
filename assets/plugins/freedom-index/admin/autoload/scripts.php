<?php if (!defined('ABSPATH')) { exit; }

/**
 * Enqueue admin scripts and styles
 */
function fi_admin_scripts_enqueue(string $hook): void {
	if ($hook === null || strpos($hook, 'fi-') === false) {
		return;
	}

	// Bootstrap 5 (scoped for Freedom Index admin pages)
	wp_enqueue_style(
		'fi-bootstrap',
		'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
		[],
		'5.3.3'
	);

	wp_enqueue_style('bootstrap-icons', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.css', [], null, 'all');

	// Enqueue CSS
	$admin_css = 'assets/css/admin.css';
	if (file_exists(FI_DIR . $admin_css)) {
		$version = filemtime(FI_DIR . $admin_css);
		wp_enqueue_style(
			'fi-admin',
			FI_URL . 'assets/css/admin.css',
			['fi-bootstrap'],
			$version
		);
	}

	wp_enqueue_script(
		'fi-bootstrap',
		'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
		[],
		'5.3.3',
		true
	);

	wp_enqueue_editor();

	// Enqueue WordPress media library (for image picker on legislator and vote edit)
	$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
	$admin_deps = ['jquery', 'wp-util', 'fi-bootstrap'];
	if ($page === 'fi-legislators' || $page === 'fi-votes') {
		wp_enqueue_media();
		$admin_deps[] = 'media-editor';
	}

	// Enqueue JavaScript (media-editor dep ensures wp.media exists when vote/legislator image picker runs)
	$admin_js = 'assets/js/admin.js';
	$js_version = '1.0.0';
	if (file_exists(FI_DIR . $admin_js)) {
		$js_version = (string) filemtime(FI_DIR . $admin_js);
	}
	wp_enqueue_script(
		'fi-admin',
		FI_URL . 'assets/js/admin.js',
		$admin_deps,
		$js_version,
		true
	);

	// Localize script
	wp_localize_script('fi-admin', 'fiAdmin', [
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('fi_admin_nonce'),
		'strings' => [
			'confirmDelete' => 'Are you sure you want to delete this item?',
			'saving' => 'Saving...',
			'saved' => 'Saved successfully',
			'error' => 'An error occurred'
		]
	]);
}
add_action('admin_enqueue_scripts', 'fi_admin_scripts_enqueue');

