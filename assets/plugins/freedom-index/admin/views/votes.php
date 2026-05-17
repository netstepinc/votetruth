<?php if (!defined('ABSPATH')) {exit;}

$scope = fi_scope_get_current();
// Summary: vote saves are handled on admin_init (see admin/autoload/actions.php) so redirects can work.

$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
if (in_array($action, ['add', 'edit'], true)) {
	fi_admin_votes_render_form($scope, $action);
	return;
}

if ($action === 'rollcall') {
	$vote_id = absint($_GET['vote_id'] ?? 0);
	if (!$vote_id) {
		wp_die('Invalid vote ID');
	}
	include FI_DIR . '/admin/views/vote-rollcall.php';
	return;
}

$gov = strtoupper((string) ($scope['gov'] ?? ''));
$sessions_for_filter = $gov !== '' ? fi_sessions_get_by_gov($gov) : [];
$session_ids_for_filter = [];
foreach ($sessions_for_filter as $s) {
	$session_ids_for_filter[(int) ($s->id ?? 0)] = true;
}

$resolved = function_exists('fi_admin_session_filter_resolve')
	? fi_admin_session_filter_resolve($gov, $session_ids_for_filter)
	: ['session_id' => (int) (fi_session_get_current_id($gov) ?? 0), 'source' => ''];
$session_id = (int) ($resolved['session_id'] ?? 0);

$filters = [
	'search' => sanitize_text_field($_GET['search'] ?? ''),
	'session_id' => $session_id,
	'chamber' => strtoupper(sanitize_text_field($_GET['chamber'] ?? '')),
	'constitutional' => strtoupper(sanitize_text_field($_GET['constitutional'] ?? '')),
	'status' => sanitize_key($_GET['status'] ?? ''),
	'tag_id' => absint($_GET['tag_id'] ?? 0),
];

// Always pass a status key for admin queries.
// The core vote getter defaults to publish-only when status key is missing.
// Passing status => null means "all statuses".
$query_filters = [
	'chamber' => $filters['chamber'] ?: null,
	'constitutional' => $filters['constitutional'] ?: null,
	'status' => $filters['status'] ?: null,
	'search' => $filters['search'] ?: null,
	// Performance: always paginate admin vote lists (prevents loading huge rollcall_data JSON sets).
	// Staff can filter/search to narrow further.
	'per_page' => 100,
	'page' => max(1, (int) ($_GET['paged'] ?? 1)),
	'cache' => false,
	//Admin order by ID descending like WP:posts
	'orderby' => 'date_voted',
	'order' => 'DESC',
];

$has_session = (int) $session_id > 0;
$has_gov = $gov !== '';
$votes = [];
$tags_by_vote = [];
$rollcall_counts = [];
$chamber_options = fi_chamber_options($gov ?: 'US');
$status_options = fi_admin_votes_get_status_options();
$constitutional_options = ['Y' => 'Constitutional (Y)', 'N' => 'Unconstitutional (N)', 'U' => 'Unknown (U)'];
$tag_filter_options = fi_admin_votes_get_filter_tags($scope);
$stats = fi_votes_stats($gov ?: null, $session_id ?: null);

//Default order by date_voted descending, then id descending
if ($has_session) {
	if ($filters['tag_id']) {
		$session_ids = fi_sessions_get_hierarchy_ids((int) $session_id) ?: [(int) $session_id];
		$tag_query_filters = [
			'session_ids' => $session_ids,
			'gov' => $gov ?: null,
			'chamber' => $filters['chamber'] ?: null,
			'status' => $filters['status'] ?: null,
			'orderby' => 'date_voted',
			'order' => 'DESC',
		];
		$votes = fi_votes_get_by_tag($filters['tag_id'], $tag_query_filters);
		$votes = fi_admin_votes_apply_collection_filters($votes, $filters);
	} else {
		$votes = fi_votes_get_by_session((int) $session_id, $query_filters);
	}
} elseif ($has_gov) {
	// Gov-only scope: show all votes in this government (session optional).
	if ($filters['tag_id']) {
		$tag_query_filters = [
			'gov' => $gov,
			'chamber' => $filters['chamber'] ?: null,
			'status' => $filters['status'] ?: null,
			'orderby' => 'date_voted',
			'order' => 'DESC',
		];
		$votes = fi_votes_get_by_tag($filters['tag_id'], $tag_query_filters);
		$votes = fi_admin_votes_apply_collection_filters($votes, $filters);
	} else {
		$votes = fi_votes_get_by_gov((string) $gov, $query_filters);
	}
}

if (!empty($votes)) {
	$vote_ids = array_map(static fn($vote) => (int) ($vote->id ?? 0), $votes);
	$vote_ids = array_filter($vote_ids);

	if (!empty($vote_ids)) {
		$tag_rows = fi_vote_tags_get_tags_by_vote_ids($vote_ids);
		foreach ($tag_rows as $row) {
			$tags_by_vote[$row->vote_id][] = $row;
		}

		$rollcall_counts = fi_rollcalls_get_counts_by_vote_ids($vote_ids);
	}
}

// Count Senate and House votes from current list
$senate_count = 0;
$house_count = 0;
$total_count = 0;
foreach ($votes as $vote) {
	$chamber = strtoupper($vote->chamber ?? '');
	if ($chamber === 'S') {
		$senate_count++;
	} elseif ($chamber === 'H') {
		$house_count++;
	}
	$total_count++;
}

$has_session = (int) $session_id > 0;
$has_gov = $gov !== '';
$chamber_options = $chamber_options ?? [];
$status_options = $status_options ?? [];
$constitutional_options = $constitutional_options ?? [];
$tag_filter_options = $tag_filter_options ?? [];
$rollcall_counts = $rollcall_counts ?? [];
$tags_by_vote = $tags_by_vote ?? [];
$stats = $stats ?? [];
$scope = $scope ?? [];
$sessions_for_filter = $sessions_for_filter ?? [];
?>
<?php fi_scope_render_selector(); ?>
<div class="wrap fi-votes-admin">
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
		<div class="d-flex align-items-center gap-2 flex-wrap">
			<h1 class="wp-heading-inline">Votes</h1>
		</div>
		<div class="d-flex align-items-center gap-2">
			<a href="<?php echo esc_url(fi_admin_url('fi-votes', ['action' => 'add'])); ?>" class="btn btn-sm btn-primary px-5">
				Add Vote
			</a>
		</div>
	</div>
	<hr class="wp-header-end">
	<?php if (!$has_gov): ?>
		<div class="notice notice-warning is-dismissible">
			<p>Select a government to manage votes. (Optional: select a session to view one session roster.)</p>
		</div>
	<?php endif; ?>

	<?php if ($has_gov): ?>
		<div class="mb-3">
			<small class="text-muted">
				Total: <strong><?php echo esc_html($stats['total'] ?? 0); ?></strong> | 
				Constitutional: <strong class="text-success"><?php echo esc_html($stats['good_votes'] ?? 0); ?></strong> | 
				Unconstitutional: <strong class="text-danger"><?php echo esc_html($stats['bad_votes'] ?? 0); ?></strong> | 
				Unknown: <strong class="text-secondary"><?php echo esc_html($stats['unknown_votes'] ?? 0); ?></strong> | 
				Senate: <strong><?php echo esc_html($senate_count); ?></strong> | 
				House: <strong><?php echo esc_html($house_count); ?></strong>
			</small>
		</div>
	<?php endif; ?>

	<form method="get" class="card mb-4 bg-light shadow-sm fi-filters">
		<div class="card-body w-100">
			<input type="hidden" name="page" value="fi-votes">
			<input type="hidden" name="gov" value="<?php echo esc_attr($gov); ?>">
			<div class="row g-2 align-items-end w-100">
				<div class="col-lg-4 col-md-3">
					<label for="fi-filter-search" class="form-label small mb-1">Search</label>
					<input
						type="search"
						class="form-control form-control-sm"
						id="fi-filter-search"
						name="search"
						value="<?php echo esc_attr($filters['search']); ?>" 
						placeholder="Title, bill, rollcall number..."
					>
				</div>
				<div class="col-lg-2 col-md-3">
					<label for="fi-filter-session" class="form-label small mb-1">Session</label>
					<select class="form-select form-select-sm" id="fi-filter-session" name="session_id">
						<option value="">All Sessions</option>
						<?php foreach ($sessions_for_filter as $s): ?>
							<option value="<?php echo esc_attr((string) ($s->id ?? '')); ?>" <?php selected((int) ($s->id ?? 0), (int) ($filters['session_id'] ?? 0)); ?>>
								<?php echo esc_html((string) ($s->name ?? '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-2 col-lg-1">
					<label for="fi-filter-chamber" class="form-label small mb-1">Chamber</label>
					<select class="form-select form-select-sm" id="fi-filter-chamber" name="chamber">
						<option value="">All</option>
						<?php foreach ($chamber_options as $code => $label): ?>
							<?php 
							// Handle arrays from chamber options (extract 'name' or 'short' key)
							$label_str = is_array($label) ? ($label['chamber'] ?? $label['title'] ?? (string) $code) : (string) ($label ?? $code);
							?>
							<option value="<?php echo esc_attr($code); ?>" <?php selected($filters['chamber'], $code); ?>>
								<?php echo esc_html($label_str); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-2 col-lg-1">
					<label for="fi-filter-constitutional" class="form-label small mb-1">Constitutional</label>
					<select class="form-select form-select-sm" id="fi-filter-constitutional" name="constitutional">
						<option value="">All</option>
						<?php foreach ($constitutional_options as $key => $label): ?>
							<option value="<?php echo esc_attr($key); ?>" <?php selected($filters['constitutional'], $key); ?>>
								<?php echo esc_html($label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-2 col-lg-1">
					<label for="fi-filter-status" class="form-label small mb-1">Status</label>
					<select class="form-select form-select-sm" id="fi-filter-status" name="status">
						<option value="">All</option>
						<?php foreach ($status_options as $key => $label): ?>
							<option value="<?php echo esc_attr($key); ?>" <?php selected($filters['status'], $key); ?>>
								<?php echo esc_html($label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-2 col-lg-1">
					<label for="fi-filter-tag" class="form-label small mb-1">Tag</label>
					<select class="form-select form-select-sm" id="fi-filter-tag" name="tag_id">
						<option value="">All Tags</option>
						<?php foreach ($tag_filter_options as $tag_id => $label): ?>
							<option value="<?php echo esc_attr($tag_id); ?>" <?php selected($filters['tag_id'], $tag_id); ?>>
								<?php echo esc_html($label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-3 col-lg-2 d-flex gap-2">
					<button type="submit" class="btn btn-primary align-self-end flex-fill">Filter</button>
					<?php
					// Summary: Reset should clear the persisted session cookie as well (by explicitly setting session_id=0).
					$reset_base = remove_query_arg(['search', 'chamber', 'constitutional', 'status', 'tag_id', 'orderby', 'order']);
					$reset_url = add_query_arg(['session_id' => 0], $reset_base);
					?>
					<a href="<?php echo esc_url($reset_url); ?>" class="btn btn-secondary align-self-end flex-fill text-center">Reset</a>
				</div>
			</div>
		</div>
	</form>

	<?php if (($has_session || $has_gov) && empty($votes)): ?>
		<div class="notice notice-info">
			<p>No votes found for this scope. Try importing data or adjusting your filters.</p>
		</div>
	<?php endif; ?>

	<?php if (($has_session || $has_gov) && !empty($votes)): ?>
		<div class="table-responsive">
			<table class="wp-list-table widefat fixed striped table table-hover align-middle">
				<thead>
					<tr>
						<th width="140px">Actions</th>
						<th width="240px">Title</th>
						<th>Date</th>
						<th>Session</th>
						<th>Chamber</th>
						<th>Constitutional</th>
						<th>Tags</th>
						<th style="width: 80px;">RC#</th>
						<th>Roll Calls</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($votes as $vote): ?>
						<?php
						$vote_id = (int) $vote->id;
						$tags = $tags_by_vote[$vote_id] ?? [];
						$rollcall_total = $rollcall_counts[$vote_id] ?? 0;
						//Is this vote in a report?
						$report_link = '';
						if($vote->chamber != ''){
							$reports = fi_report_get_by_vote_id($vote_id, $vote->chamber);
							if(!empty($reports)){
								foreach($reports as $report){
									$report_link .= '<br><a href="'.esc_url(fi_admin_url('fi-reports', ['action' => 'edit', 'report_id' => $report->id])).'" target="_blank">'.$report->title.'</a>';
								}
							}
						}

						$is_constitutional = '<span class="badge ';
						$const = $vote->constitutional;
						if($const === 'Y'){
							$is_constitutional .= 'bg-success">Yes';
						}elseif( $const === 'N'){
							$is_constitutional .= 'bg-danger">No';
						}else{
							$is_constitutional .= 'bg-secondary">Unknown';
						}
						$is_constitutional .= '</span>';
						?>
						<tr>
							<td>
								<div class="d-flex flex-wrap gap-2">
									<a href="<?php echo esc_url(fi_admin_edit_vote_url($vote_id)); ?>" class="btn btn-sm btn-primary">Edit</a>
									<a href="<?php echo esc_url(fi_admin_roll_call_edit_url($vote_id)); ?>" class="btn btn-sm btn-secondary">Roll Call</a>
								</div>
							</td>
							<td>
								<strong><?php echo esc_html($vote->title ?? 'Untitled'); ?></strong><br>
								<small class="text-muted">ID: <?= $vote->id;?> <?php echo esc_html($vote->bill_number ?? '') . ($vote->rollcall_number ? '<span class="mx-3">RC#'.esc_html($vote->rollcall_number ?? '').'</span>' : ''); ?></small>
							</td>
							<td>
								<?php
									if (!empty($vote->date_voted) && $vote->date_voted !== '0000-00-00 00:00:00') {
										echo esc_html(date('m/d/Y', strtotime($vote->date_voted)));
									} else {
										echo '—';
									}
								?>
							</td>
							<td><?php echo esc_html((string) ($vote->session_name ?? '—')).$report_link; ?></td>
							<td><?php 
								$chamber = $vote->chamber ?? '';
								$gov = $vote->gov ?? ($scope['gov'] ?? 'US');
								if ($chamber) {
									$chamber_label = fi_chamber_label($gov, $chamber);
									echo esc_html((string) ($chamber_label ?? $chamber));
								} else {
									echo '—';
								}
							?></td>
							<td>
								<?= $is_constitutional;?>
							</td>
							<td>
								<?php if (empty($tags)): ?>
									<span class="text-muted">—</span>
								<?php else: ?>
									<div class="d-flex flex-wrap gap-1">
										<?php foreach ($tags as $tag): ?>
											<span class="badge bg-secondary"><?php echo esc_html($tag->tag_name ?? $tag->tag_slug); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</td>
							<td><?php echo $vote->rollcall_number ? esc_html($vote->rollcall_number) : '—'; ?></td>
							<td><?php echo esc_html($rollcall_total); ?></td>
							<td><?php 
								$status_value = $vote->status ?? 'publish';
								$status_display = $status_options[$status_value] ?? ucfirst($status_value);
								// Ensure status_display is a string
								$status_display = is_array($status_display) ? (string) $status_value : (string) ($status_display ?? $status_value);
								echo esc_html($status_display);
							?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="text-muted small">Total votes: <?php echo esc_html($total_count); ?></div>
	<?php endif; ?>
</div>