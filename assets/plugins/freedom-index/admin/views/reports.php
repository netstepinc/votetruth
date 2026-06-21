<?php if (!defined('ABSPATH')) {exit;}

// Handle save BEFORE getting scope (in case scope outputs anything)
fi_admin_reports_maybe_handle_save_early();

$scope = fi_scope_get_current();

// Already handled above, but keep this for clarity
// fi_admin_reports_maybe_handle_save($scope);

$action = $_GET['action'] ?? 'list';

if (in_array($action, ['add', 'edit'], true)) {
	fi_admin_reports_render_edit();
	return;
}
$gov = strtoupper((string) ($scope['gov'] ?? ''));

$session_id = 0;

$filters = [
	'search' => sanitize_text_field($_GET['search'] ?? ''),
	'status' => sanitize_key($_GET['status'] ?? ''),
	'session_id' => $session_id,
];

$sessions = $gov ? fi_sessions_get_by_gov($gov, [
	'orderby' => 'date_start',
	'order' => 'DESC',
]) : [];

// On this page only: default to "All Sessions" unless user explicitly sets session_id via GET
if (isset($_GET['session_id'])) {
	// User explicitly selected a session - use it (even if 0 = "All")
	$filters['session_id'] = absint($_GET['session_id']);
} else {
	// First load or no session param - default to All Sessions (0)
	$filters['session_id'] = 0;
}

$session_lookup = [];
foreach ($sessions as $session) {
	$session_lookup[(int) $session['id']] = $session;
}

$reports = [];
$stats = [
	'total' => 0,
	'public' => 0,
	'unlisted' => 0,
];

if ($gov) {
	global $wpdb;
	$where = ["r.gov = %s"];
	$values = [$gov];

	if ($filters['session_id']) {
		$where[] = "r.session_id = %d";
		$values[] = $filters['session_id'];
	}

	if ($filters['status']) {
		$where[] = "r.status = %s";
		$values[] = $filters['status'];
	}

	if ($filters['search']) {
		$where[] = "(r.title LIKE %s OR r.slug LIKE %s)";
		$search_like = '%' . $wpdb->esc_like($filters['search']) . '%';
		$values[] = $search_like;
		$values[] = $search_like;
	}

	$where_clause = 'WHERE ' . implode(' AND ', $where);

	//SESSIONSLUG: Remove 's.slug as session_slug' from SELECT - no longer needed in report objects
	$sql = "
		SELECT r.*, s.name as session_name, s.parent_id as session_parent_id 
		FROM {$wpdb->prefix}fi_reports r
		LEFT JOIN {$wpdb->prefix}fi_sessions s ON r.session_id = s.id
		{$where_clause}
		ORDER BY r.date_publish DESC, r.id DESC
	";

	$reports = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

	//RMFORMAT
	// Parse payload_json to get vote counts for each report
	foreach ($reports as &$report) {
		$report['session_name'] = $report['session_name']
			?? ($session_lookup[(int)($report['session_id'] ?? 0)]['name'] ?? '—');
		
		// Decode payload_json to get vote counts
		$payload = fi_report_payload_normalize($report['payload_json'] ?? null);
		$votes_s = $payload['votes_s'] ?? [];
		$votes_h = $payload['votes_h'] ?? [];

		// Count votes separately for Senate and House
		$report['senate_count'] = is_array($votes_s) ? count(array_filter($votes_s)) : 0;
		$report['house_count'] = is_array($votes_h) ? count(array_filter($votes_h)) : 0;
		$report['haspdf'] = isset($payload['report_pdf_url']) && !empty($payload['report_pdf_url']) ? '<i class="bi bi-file-pdf"></i>' : '';
	}

	unset($report);
	$stats = fi_admin_reports_get_stats($gov);
}


$filters = $filters ?? ['search' => '', 'session_id' => 0, 'status' => ''];
$sessions = $sessions ?? [];
$stats = $stats ?? ['total' => 0, 'publish' => 0, 'draft' => 0];
$scope = $scope ?? [];
?>
<?php fi_scope_render_selector($sessions); ?>
<div class="wrap fi-reports-admin">
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
		<div class="d-flex align-items-center gap-2 flex-wrap">
			<h1 class="wp-heading-inline">Reports</h1>
		</div>
		<div class="d-flex align-items-center gap-2">
			<a href="<?php echo esc_url(fi_admin_url('fi-reports', ['action' => 'add'])); ?>" class="btn btn-sm btn-primary px-5">Create Report</a>
		</div>
	</div>
	<hr class="wp-header-end">
	<?php if (empty($scope['gov'])): ?>
		<div class="notice notice-warning is-dismissible">
			<p>Select a government using the scope selector to manage reports.</p>
		</div>
	<?php endif; ?>

	<?php if (!empty($scope['gov'])): ?>
		<div class="mb-3">
			<small class="text-muted">
				Total: <strong><?php echo esc_html($stats['total']); ?></strong> | 
				Published: <strong class="text-success"><?php echo esc_html($stats['publish'] ?? 0); ?></strong> | 
				Draft: <strong class="text-secondary"><?php echo esc_html($stats['draft'] ?? 0); ?></strong>
			</small>
		</div>


	<form method="get" class="card mb-4 bg-light shadow-sm fi-filters">
		<div class="card-body w-100">
			<input type="hidden" name="page" value="fi-reports">
			<input type="hidden" name="gov" value="<?php echo esc_attr($gov); ?>">
			<div class="row g-3 align-items-end">
				<div class="col-md-4">
					<label for="fi-report-search" class="form-label">Search</label>
					<input
						type="search"
						id="fi-report-search"
						name="search"
						class="form-control"
						value="<?php echo esc_attr($filters['search']); ?>"
						placeholder="Title or slug"
					>
				</div>
				<div class="col-md-4">
					<label for="fi-report-session" class="form-label">Session</label>
					<select id="fi-report-session-filter" name="session_id" class="form-select">
						<option value="">All Sessions</option>
						<?php foreach ($sessions as $session): ?>
							<option value="<?php echo esc_attr($session['id']); ?>" <?php selected($filters['session_id'], $session['id']); ?>>
								<?php echo ($session['parent_id'] ? '— ' : '') . esc_html($session['name']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-2">
					<label for="fi-report-status" class="form-label">Status</label>
					<select id="fi-report-status" name="status" class="form-select">
						<option value="">All</option>
						<option value="publish" <?php selected($filters['status'], 'publish'); ?>>Published</option>
						<option value="draft" <?php selected($filters['status'], 'draft'); ?>>Draft</option>
						<option value="pending" <?php selected($filters['status'], 'pending'); ?>>Pending</option>
						<option value="trash" <?php selected($filters['status'], 'trash'); ?>>Trash</option>
					</select>
				</div>
				<div class="col-md-2 d-flex gap-2">
					<button type="submit" class="btn btn-primary align-self-end w-100">Filter</button>
					<?php
					// Summary: Reset should clear the persisted session cookie as well (by explicitly setting session_id=0).
					$reset_base = remove_query_arg(['search', 'status', 'orderby', 'order']);
					$reset_url = add_query_arg(['session_id' => 0], $reset_base);
					?>
					<a href="<?php echo esc_url($reset_url); ?>" class="btn btn-link text-danger align-self-end w-100">Reset</a>
				</div>
			</div>
		</div>
		</form>

		<?php if (empty($reports)): ?>
			<div class="notice notice-info">
				<p>No reports found for this government. Use the btn above to create one.</p>
			</div>
		<?php else: ?>
			<div class="table-responsive">
				<table class="wp-list-table widefat fixed striped table table-hover align-middle">
					<thead>
						<tr>
							<th width="180">Actions</th>
							<th>Title</th>
							<th>ID</th>
							<th>Session</th>
							<th>Senate</th>
							<th>House</th>
							<th>Status</th>
							<th>Report Date</th>
							<th>Format</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($reports as $report): ?>
							<?php
							$status_class = match($report['status'] ?? 'draft') {
								'publish' => 'bg-success',
								'draft' => 'bg-secondary',
								'pending' => 'bg-warning',
								'trash' => 'bg-danger',
								default => 'bg-secondary'
							};

							$title = '<strong>' . esc_html($report['title']) . '</strong>';

							//Move reports to parent session if they are assigned to a child session
							if($report['session_parent_id']){
								global $wpdb;
								$wpdb->update($wpdb->prefix . 'fi_reports', ['session_id' => $report['session_parent_id']], ['id' => $report['id']]);
								wp_cache_delete($report['id'], 'fi_reports');
								$title .= '<div class="alert alert-danger fw-bold p-1 text-center">Moved to Parent Session<br><span style="font-size:14px;">REFRESH PAGE</span></div>';
							}

							if($report['title_menu']){
								$title .= '<div class="ps-2">Menu: <span class="text-danger fw-bold">' . esc_html($report['title_menu']) . '</span></div>';
							}
							?>
							<tr>
								<td>
									<div class="d-flex flex-wrap gap-2">
										<a href="<?php echo esc_url(fi_admin_url('fi-reports', ['action' => 'edit', 'report_id' => $report['id']])); ?>" class="btn btn-sm btn-primary">Edit</a>
										<a href="<?php echo esc_url(fi_report_url($scope['gov'], $report['id'])); ?>" class="btn btn-sm btn-secondary" target="_blank" rel="noopener">View</a>
									</div>
								</td>
								<td><?= $title; ?></td>
								<td>
									<code><?php echo esc_html($report['id']); ?></code>
								</td>
								<td><?php echo esc_html($report['session_name'] ?? '—'); ?></td>
								<td><?php echo esc_html($report['senate_count'] ?? 0); ?></td>
								<td><?php echo esc_html($report['house_count'] ?? 0); ?></td>
								<td>
									<span class="badge <?php echo esc_attr($status_class); ?>">
										<?php echo esc_html(ucfirst($report['status'] ?? 'draft')); ?>
									</span>
								</td>
								<td><?php echo esc_html($report['date_publish'] ? mysql2date('M j, Y', $report['date_publish']) : '—'); ?></td>
								<td><?php echo ucwords(esc_html($report['format'] ?? '')); ?><?php echo $report['haspdf'];?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>