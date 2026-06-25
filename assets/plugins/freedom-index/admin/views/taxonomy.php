<?php if (!defined('ABSPATH')) { exit; }

global $wpdb;

$taxonomy = sanitize_key($_GET['fi_taxonomy'] ?? '');
if (!in_array($taxonomy, ['district', 'tag'], true)) {
	$taxonomy = 'tag';
}
$is_tag = ($taxonomy === 'tag');

$scope  = fi_scope_get_current();
$gov = strtoupper((string) ($scope['gov'] ?? ''));
$gov_name = fi_gov_name($gov);
if ($is_tag) {
	// Summary: tags are global; keep a stable gov value for schema compatibility.
	$gov = 'US';
	$gov_name = '';
}

$search = sanitize_text_field($_GET['s'] ?? '');
$state = strtoupper(sanitize_text_field($_GET['state'] ?? ''));
$action = sanitize_key($_GET['action'] ?? '');
$taxonomy_id = absint($_GET['id'] ?? 0);

$page_slug = ($taxonomy === 'district') ? 'fi-districts' : 'fi-tags';
$title = ($taxonomy === 'district') ? 'Districts' : 'Tags';

// Edit mode (re-uses same form as add)
$is_edit = ($action === 'edit' && $taxonomy_id > 0);
$item = null;
if ($is_edit) {
	$item = fi_taxonomy_get($taxonomy_id);
	// Summary: prevent cross-gov or cross-taxonomy edits when scope changes.
	if (!$item || sanitize_key((string) ($item['taxonomy'] ?? '')) !== $taxonomy || (!$is_tag && strtoupper((string) ($item['gov'] ?? '')) !== $gov)) {
		$item = null;
		$is_edit = false;
	}
}

// List view
$rows = [];
if ($is_tag) {
	$where = ["taxonomy = %s"];
	$vals = [$taxonomy];
} else {
	$where = ["taxonomy = %s", "gov = %s"];
	$vals = [$taxonomy, $gov];
}

if ($search !== '') {
	$where[] = "name LIKE %s";
	$like = '%' . $wpdb->esc_like($search) . '%';
	$vals[] = $like;
	$vals[] = $like;
}

// Districts: allow state narrowing for US congressional districts.
if ($taxonomy === 'district' && $gov === 'US' && $state !== '') {
	$where[] = "name LIKE %s";
	$vals[] = $state . ' %';
}

$sql = "SELECT id, gov, taxonomy, name, description, date_updated
		FROM {$wpdb->prefix}fi_taxonomy
		WHERE " . implode(' AND ', $where) . "
		ORDER BY name ASC";

$rows = $wpdb->get_results($wpdb->prepare($sql, $vals));
?>
<?php fi_scope_render_selector(); ?>
<div class="wrap fi-taxonomy-admin">
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
		<div class="d-flex align-items-center gap-2 flex-wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
			<?php if (!$is_tag): ?>
				<span class="badge bg-secondary"><?php echo esc_html($gov_name); ?></span>
			<?php endif; ?>
		</div>
		<div class="d-flex align-items-center gap-2">
			<?php if ($is_edit): ?>
				<a href="<?php echo esc_url(add_query_arg(['page' => $page_slug], admin_url('admin.php'))); ?>" class="btn btn-sm btn-outline-secondary">Cancel edit</a>
			<?php endif; ?>
		</div>
	</div>
	<hr class="wp-header-end">
	<?php if (!empty($_GET['updated'])): ?>
		<div class="notice notice-success is-dismissible"><p>Saved.</p></div>
	<?php endif; ?>
	<?php if (!empty($_GET['deleted'])): ?>
		<div class="notice notice-success is-dismissible"><p>Deleted.</p></div>
	<?php endif; ?>

	<div class="row g-4">
		<div class="col-12 col-lg-3">
			<div class="card shadow-sm">
				<div class="card-header bg-white border-0">
					<h2 class="h5 mb-0"><?php echo esc_html($is_edit ? "Edit {$title}" : "Add {$title}"); ?></h2>
				</div>
				<div class="card-body">
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<?php wp_nonce_field('fi_save_taxonomy_item'); ?>
						<input type="hidden" name="action" value="fi_save_taxonomy_item">
						<input type="hidden" name="taxonomy_id" value="<?php echo esc_attr($is_edit ? (int) $taxonomy_id : 0); ?>">
						<input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy); ?>">
						<?php if (!$is_tag): ?>
							<input type="hidden" name="gov" value="<?php echo esc_attr($gov); ?>">
						<?php endif; ?>

						<div class="mb-3">
							<label class="form-label fw-semibold" for="fi-taxonomy-name">Name</label>
							<input
								name="name"
								id="fi-taxonomy-name"
								type="text"
								class="form-control"
								value="<?php echo esc_attr((string) ($item['name'] ?? '')); ?>"
								required
							>
						</div>

						<div class="mb-3">
							<label class="form-label fw-semibold" for="fi-taxonomy-description">Description</label>
							<textarea
								name="description"
								id="fi-taxonomy-description"
								class="form-control"
								rows="3"
							><?php echo esc_textarea((string) ($item['description'] ?? '')); ?></textarea>
							<div class="form-text text-muted">Shown above the vote list when this tag is active.</div>
						</div>

						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary"><?php echo esc_html($is_edit ? 'Save Changes' : 'Add New'); ?></button>
							<?php if ($is_edit): ?>
								<a class="btn btn-outline-secondary" href="<?php echo esc_url(add_query_arg(['page' => $page_slug], admin_url('admin.php'))); ?>">Cancel</a>
							<?php endif; ?>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div class="col-12 col-lg-9">
			<div class="card shadow-sm">
				<div class="card-header bg-white border-0">
					<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
						<h2 class="h5 mb-0">All <?php echo esc_html($title); ?></h2>
						<form method="get" class="d-flex align-items-center gap-2">
							<input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>">
							<?php if ($taxonomy === 'district' && $gov === 'US'): ?>
								<select name="state" class="form-select form-select-sm" style="width:160px;">
									<option value="">All States</option>
									<?php foreach (FI_GOVERNMENTS as $gov_code => $gov_label): ?>
										<?php if ($gov_code === 'US') continue; ?>
										<option value="<?php echo esc_attr($gov_code); ?>" <?php selected($state, $gov_code); ?>>
											<?php echo esc_html($gov_label); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
							<input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search name" class="form-control form-control-sm">
							<button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
						</form>
					</div>
				</div>

				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-striped table-hover align-middle mb-0">
							<thead>
								<tr>
									<th style="width:60px;">ID</th>
									<?php if (!$is_tag): ?>
										<th style="width:80px;">Gov</th>
									<?php endif; ?>
									<th>Name</th>
									<th>Description</th>
									<th style="width:140px;">Updated</th>
									<th style="width:100px;">Actions</th>
								</tr>
							</thead>
							<tbody>
								<?php if (empty($rows)): ?>
										<tr><td colspan="<?php echo $is_tag ? 3 : 4; ?>" class="text-muted">No results.</td></tr>
								<?php else: ?>
									<?php foreach ($rows as $row): ?>
										<tr>
											<td><?php echo esc_html((string) $row->id); ?></td>
											<?php if (!$is_tag): ?>
												<td><?php echo esc_html((string) $row->gov); ?></td>
											<?php endif; ?>
											<td class="fw-semibold"><?php echo esc_html((string) $row->name); ?></td>
											<td class="fw-normal text-truncate" style="max-width:260px;" title="<?php echo esc_attr((string) ($row->description ?? '')); ?>"><?php echo esc_html((string) ($row->description ?? '')); ?></td>
											<td class="text-muted small"><?php echo esc_html((string) ($row->date_updated ?? '')); ?></td>
											<td>
												<div class="d-flex align-items-center gap-2">
													<a class="btn btn-sm btn-outline-primary" href="<?php echo esc_url(add_query_arg(['page' => $page_slug, 'action' => 'edit', 'id' => (int) $row->id], admin_url('admin.php'))); ?>">Edit</a>
													<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="d-inline">
														<?php wp_nonce_field('fi_delete_taxonomy_item'); ?>
														<input type="hidden" name="action" value="fi_delete_taxonomy_item">
														<input type="hidden" name="taxonomy_id" value="<?php echo esc_attr((int) $row->id); ?>">
														<input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy); ?>">
														<button
															type="submit"
															class="btn btn-sm btn-outline-danger"
															title="Delete"
															aria-label="Delete"
															onclick="return confirm('Are you sure you want to delete this <?php echo esc_js(strtolower($taxonomy)); ?>?');"
														>X</button>
													</form>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

