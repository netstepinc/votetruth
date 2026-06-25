<?php if (!defined('ABSPATH')) { exit; }

/**
 * Taxonomy Admin (fi_taxonomy) - Districts + Tags management screens
 * Minimal CRUD: list + search/filter + add/edit + delete
 */

function fi_admin_taxonomy_render_districts(): void {
	$_GET['fi_taxonomy'] = 'district';
	fi_admin_taxonomy_render();
}

function fi_admin_taxonomy_render_tags(): void {
	$_GET['fi_taxonomy'] = 'tag';
	fi_admin_taxonomy_render();
}

function fi_admin_taxonomy_render(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die('Insufficient permissions.');
	}
	include __DIR__ . '/../views/taxonomy.php';
}

/**
 * Save taxonomy item (add/edit).
 */
function fi_admin_post_save_taxonomy_item(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die('Insufficient permissions.');
	}
	check_admin_referer('fi_save_taxonomy_item');

	$taxonomy_id = absint($_POST['taxonomy_id'] ?? 0);
	$taxonomy = sanitize_key($_POST['taxonomy'] ?? '');
	// Summary: districts are gov-scoped; tags are global (use US for schema compatibility).
	$scope = fi_scope_get_current();
	$scope_gov = strtoupper((string) ($scope['gov'] ?? ''));
	$scope_gov = ($scope_gov !== '' && function_exists('fi_gov_validate') && fi_gov_validate($scope_gov)) ? $scope_gov : '';
	$post_gov = strtoupper(sanitize_text_field($_POST['gov'] ?? ''));
	$post_gov = fi_gov_validate($post_gov) ? $post_gov : '';
	$gov = $post_gov !== '' ? $post_gov : $scope_gov;
	if ($taxonomy === 'tag') {
		$gov = 'US';
	}
	$name = sanitize_text_field($_POST['name'] ?? '');
	$description = sanitize_textarea_field($_POST['description'] ?? '');

	if ($taxonomy === '' || $name === '' || ($taxonomy !== 'tag' && $gov === '')) {
		wp_die('Missing required fields (taxonomy, name, gov).');
	}

	$data = [
		'gov'         => $gov,
		'taxonomy'    => $taxonomy,
		'name'        => $name,
		'description' => $description,
	];
	$saved_id = fi_taxonomy_save($data, $taxonomy_id ?: null);
	if (!$saved_id) {
		wp_die('Failed to save taxonomy item.');
	}

	$redirect = add_query_arg([
		'page' => ($taxonomy === 'district') ? 'fi-districts' : 'fi-tags',
		//'gov' => $gov,
		'updated' => 1,
	], admin_url('admin.php'));

	wp_safe_redirect($redirect);
	exit;
}
add_action('admin_post_fi_save_taxonomy_item', 'fi_admin_post_save_taxonomy_item');

/**
 * Delete taxonomy item.
 */
function fi_admin_post_delete_taxonomy_item(): void {
	if (!current_user_can(FI_CAP_MANAGE)) {
		wp_die('Insufficient permissions.');
	}
	check_admin_referer('fi_delete_taxonomy_item');

	$taxonomy_id = absint($_POST['taxonomy_id'] ?? 0);
	$taxonomy = sanitize_key($_POST['taxonomy'] ?? '');
	if (!$taxonomy_id) {
		wp_die('Missing taxonomy id.');
	}

	fi_taxonomy_delete($taxonomy_id);

	$scope = function_exists('fi_scope_get_current') ? fi_scope_get_current() : [];
	$gov = strtoupper((string) ($scope['gov'] ?? ''));

	$redirect = add_query_arg([
		'page' => ($taxonomy === 'district') ? 'fi-districts' : 'fi-tags',
		'gov' => ($taxonomy === 'district' && $gov !== '') ? $gov : null,
		'deleted' => 1,
	], admin_url('admin.php'));

	wp_safe_redirect($redirect);
	exit;
}
add_action('admin_post_fi_delete_taxonomy_item', 'fi_admin_post_delete_taxonomy_item');

