<?php if (!defined('ABSPATH')) {exit;}

$scope  = fi_scope_get_current();
$gov = strtoupper((string) ($scope['gov'] ?? ''));

$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';

if (in_array($action, ['add', 'edit'], true)) {
	include __DIR__ . '/legislator-edit.php';
	return;
}

$filter_inputs = [
	'chamber' => isset($_GET['chamber']) ? strtoupper(sanitize_text_field($_GET['chamber'])) : '',
	'party'    => isset($_GET['party']) ? strtoupper(sanitize_text_field($_GET['party'])) : '',
	'search'   => sanitize_text_field($_GET['search'] ?? ''),
	'state'    => isset($_GET['state']) ? strtoupper(sanitize_text_field($_GET['state'])) : '',
];

$sessions_for_filter = $gov !== '' ? fi_sessions_get_by_gov($gov) : [];
$session_ids_for_filter = [];
foreach ($sessions_for_filter as $s) {
	$session_ids_for_filter[(int) ($s['id'] ?? 0)] = true;
}

$resolved = function_exists('fi_admin_session_filter_resolve')
	? fi_admin_session_filter_resolve($gov, $session_ids_for_filter)
	: ['session_id' => (int) (fi_session_get_current_id($gov) ?? 0), 'source' => ''];
$session_id = (int) ($resolved['session_id'] ?? 0);

$orderby_raw = sanitize_key($_GET['orderby'] ?? '');
$order_raw   = strtoupper(sanitize_text_field($_GET['order'] ?? ''));
$allowed_orderby = ['id', 'name', 'party', 'chamber', 'state', 'updated', 'created'];
$allowed_order   = ['ASC', 'DESC'];

// Default sort like WP posts: newest first.
$orderby = $orderby_raw !== '' && in_array($orderby_raw, $allowed_orderby, true) ? $orderby_raw : 'id';
$order   = in_array($order_raw, $allowed_order, true) ? $order_raw : 'DESC';

$query_filters = array_filter($filter_inputs, static function ($value) {
	return $value !== '' && $value !== null;
});

// Search box: support direct lookup by Legislator ID.
// If the search term is a positive integer, treat it as an ID filter (instead of a name search).
$search_raw = (string) ($filter_inputs['search'] ?? '');
$search_trim = trim($search_raw);
if ($search_trim !== '' && ctype_digit($search_trim) && (int) $search_trim > 0) {
	unset($query_filters['search']);
	$query_filters['id'] = (int) $search_trim;
}

// Wire sorting into the unified Legislators::get() query builder.
$query_filters['orderby'] = $orderby;
$query_filters['order']   = $order;

$legislators = [];
if ($session_id > 0) {
	// Filter by specific session (shows all legislators in that session)
	$legislators = fi_legislators_get_by_session($session_id, $query_filters);
} elseif ($gov !== '') {
	// Filter by gov - show ALL legislators with any session assignment in this gov (even if their latest session differs)
	$query_filters['gov'] = $gov;
	$query_filters['gov_mode'] = 'any';
	$legislators = fi_legislators_query($query_filters);
}

// Total count in scope (no search/chamber/party) for "Showing X of Y" summary.
$total_in_scope = 0;
if ($gov !== '') {
	global $wpdb;
	if ($session_id > 0) {
		$total_in_scope = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(DISTINCT legislator_id) FROM {$wpdb->prefix}fi_legislator_sessions WHERE session_id = %d",
			$session_id
		));
	} else {
		$total_in_scope = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(DISTINCT legislator_id) FROM {$wpdb->prefix}fi_legislator_sessions WHERE gov = %s",
			$gov
		));
	}
}
$showing_count = count($legislators);
$gov_label = $gov !== '' && function_exists('fi_gov_name') ? fi_gov_name($gov) : $gov;

$chamber_options = fi_chamber_options($gov ?: 'US');
$party_options = [];
$parties = fi_parties();
foreach ($parties as $abbr => $data) {
	$party_options[strtoupper($abbr)] = $data['name'] ?? strtoupper($abbr);
}

$filters = $filter_inputs;

$chamber_options = $chamber_options ?? [];
$party_options    = $party_options ?? [];
$current_session  = (int) $session_id;
$has_session      = $current_session > 0;
$has_gov          = $gov !== '';
$is_us_gov        = $gov === 'US';

// Summary: used to return to this exact list state after editing.
$return_query = $_GET;
unset($return_query['action'], $return_query['legislator_id']);
$return_query['page'] = 'fi-legislators';
$return_url = add_query_arg($return_query, admin_url('admin.php'));

$build_sort_link = static function (string $label, string $key) use ($orderby, $order, $is_us_gov): string {
	$key = sanitize_key($key);
	$allowed = ['id', 'name', 'party', 'chamber', 'state', 'updated', 'created'];
	if (!in_array($key, $allowed, true)) {
		return esc_html($label);
	}
	if ($key === 'state' && !$is_us_gov) {
		return esc_html($label);
	}
	
	$current_is_key = ($orderby === $key);
	$next_order = ($current_is_key && $order === 'ASC') ? 'DESC' : 'ASC';
	$url = add_query_arg([
		'orderby' => $key,
		'order'   => $next_order,
	]);
	
	$indicator = '';
	if ($current_is_key) {
		$indicator = $order === 'ASC' ? ' ▲' : ' ▼';
	}
	
	return '<a href="' . esc_url($url) . '">' . esc_html($label . $indicator) . '</a>';
};

fi_scope_render_selector();

if(isset($_GET['reference']) && $_GET['reference'] === 'US'):
	include __DIR__ . '/legislators-ref.php';
else:
?>
<div class="wrap fi-legislators-admin">
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
		<div class="d-flex align-items-center gap-2 flex-wrap">
			<h1 class="wp-heading-inline">Legislators</h1>
		</div>
		<div class="d-flex align-items-center gap-2">
			<a href="<?php echo esc_url(fi_admin_url('fi-legislators', ['action' => 'add'])); ?>" class="btn btn-sm btn-primary px-5">
				Add New
			</a>
		<?php if ($has_gov): ?>
			<?php if ($has_session): ?>
				<button
					type="button"
					class="btn btn-sm btn-outline-success fi-recalculate-scores"
					data-gov="<?php echo esc_attr((string) $gov); ?>"
					data-session-id="<?php echo esc_attr((string) $session_id); ?>"
				>
					Calculate Scores
				</button>
			<?php else: ?>
				<button
					type="button"
					class="btn btn-sm btn-outline-success fi-calculate-scores-gov"
					data-gov="<?php echo esc_attr((string) $scope['gov']); ?>"
				>
					Calculate Scores
				</button>
			<?php endif; ?>

			<span id="fi-score-progress" class="small text-muted"></span>
		<?php endif; ?>
		</div>
	</div>
	<hr class="wp-header-end">
	<?php if (!$has_gov): ?>
		<div class="notice notice-warning is-dismissible">
			<p>Select a government to manage legislators. (Optional: select a session to view one session roster.)</p>
		</div>
	<?php endif; ?>

	<?php if ($has_gov): ?>
		<div class="mb-3">
			<small class="text-muted">
				Showing <strong><?php echo esc_html((string) $showing_count); ?></strong> of <strong><?php echo esc_html((string) $total_in_scope); ?></strong> <?php echo esc_html($gov_label); ?> Legislators.
			</small>
		</div>
	<?php endif; ?>

	<form method="get" class="card mb-4 bg-light shadow-sm fi-filters">
	<div class="card-body w-100">
		<input type="hidden" name="page" value="fi-legislators">
		<input type="hidden" name="gov" value="<?php echo esc_attr($gov); ?>">
		<div class="row g-3 align-items-end">
			<div class="col-md-4 col-lg-3">
				<label for="fi-filter-search" class="form-label">Search</label>
				<input
					type="search"
					class="form-control"
					id="fi-filter-search"
					name="search"
					value="<?php echo esc_attr($filters['search']); ?>"
					placeholder="Name, ID, district…"
				>
			</div>
			<div class="col-md-3 col-lg-2">
				<label for="fi-filter-session" class="form-label">Session</label>
				<select id="fi-filter-session" class="form-select" name="session_id">
					<option value="">All Sessions</option>
					<?php foreach ($sessions_for_filter as $s): ?>
						<option value="<?php echo esc_attr((string) ($s['id'] ?? '')); ?>" <?php selected((int) ($s['id'] ?? 0), (int) $session_id); ?>>
							<?php echo ($s['parent_id'] != null ? '↳ ' : ''). esc_html((string) ($s['name'] ?? '')); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2 col-lg-1">
				<label for="fi-filter-chamber" class="form-label">Chamber</label>
				<select id="fi-filter-chamber" class="form-select" name="chamber">
					<option value="">All Chambers</option>
					<?php foreach ($chamber_options as $abbr => $label): ?>
						<?php 
						// Handle arrays from chamber options (extract 'name' or 'short' key)
						$label_str = is_array($label) ? ($label['chamber'] ?? $label['short'] ?? (string) $abbr) : (string) ($label ?? $abbr);
						?>
						<option value="<?php echo esc_attr($abbr); ?>" <?php selected(strtoupper($filters['chamber']), strtoupper($abbr)); ?>>
							<?php echo esc_html($label_str); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2 col-lg-1">
				<label for="fi-filter-party" class="form-label">Party</label>
				<select id="fi-filter-party" class="form-select" name="party">
					<option value="">All Parties</option>
					<?php foreach ($party_options as $abbr => $label): ?>
						<?php 
						// Ensure label is a string (handle arrays if any)
						$label_str = is_array($label) ? ($label['name'] ?? (string) $abbr) : (string) ($label ?? $abbr);
						?>
						<option value="<?php echo esc_attr($abbr); ?>" <?php selected(strtoupper($filters['party']), strtoupper($abbr)); ?>>
							<?php echo esc_html($label_str); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php if ($is_us_gov): ?>
			<div class="col-md-2 col-lg-1">
				<label for="fi-filter-state" class="form-label">State</label>
				<select id="fi-filter-state" class="form-select" name="state">
					<option value="">All States</option>
					<?php foreach (fi_state_options() as $abbr => $name): ?>
						<option value="<?php echo esc_attr($abbr); ?>" <?php selected($filters['state'], $abbr); ?>>
							<?php echo esc_html($abbr); ?> &mdash; <?php echo esc_html($name); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>
		<div class="col-md-3 col-lg-2 d-flex gap-2">
				<button type="submit" class="btn btn-primary align-self-end w-100">Filter</button>
				<?php
				// Summary: Reset should clear the persisted session cookie as well (by explicitly setting session_id=0).
				$reset_base = remove_query_arg(['search', 'chamber', 'party', 'state', 'orderby', 'order']);
				$reset_url = add_query_arg(['session_id' => 0], $reset_base);
				?>
				<a href="<?php echo esc_url($reset_url); ?>" class="btn btn-secondary align-self-end w-100">Reset</a>
			</div>
		</div>
	</div>
	</form>

	<?php if (($has_session || $has_gov) && empty($legislators)): ?>
		<div class="notice notice-info">
			<p>No legislators found for this scope. Import data or adjust your filters.</p>
		</div>
	<?php endif; ?>

	<?php if (($has_session || $has_gov) && !empty($legislators)): ?>
	<div class="table-responsive">
		<table class="wp-list-table widefat fixed striped table table-hover align-middle">
			<thead>
				<tr>
					<th width="200">Actions</th>
					<th class="legislator-name"><?php echo $build_sort_link('Name', 'name'); ?></th>
					<th class="legislator-id"><?php echo $build_sort_link('ID', 'id'); ?></th>
					<th class="legislator-party"><?php echo $build_sort_link('Party', 'party'); ?></th>
					<th class="legislator-chamber"><?php echo $build_sort_link('Chamber', 'chamber'); ?></th>
					<?php if ($is_us_gov): ?>
						<th class="legislator-state"><?php echo $build_sort_link('State', 'state'); ?></th>
					<?php endif; ?>
					<th class="legislator-district">District</th>
					<th class="legislator-score">Score</th>
					<th class="legislator-updated"><?php echo $build_sort_link('Updated', 'updated'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($legislators as $legislator): ?>
					<?php
					$party_abbr = strtoupper($legislator['party'] ?? '');
					$party_label = $party_options[$party_abbr] ?? $party_abbr ?: '—';
					$party_class = fi_party_bg_class($party_abbr);
					$chamber_code = strtoupper($legislator['chamber'] ?? '');
					$gov = $legislator['gov'] ?? ($scope['gov'] ?? 'US');
					$chamber_label = $chamber_code ? fi_chamber_label($gov, $chamber_code) : '—';
					$state_code = strtoupper((string) ($legislator['state'] ?? ''));
					$district_name = $legislator['district_info']['name'] ?? $legislator['district'] ?? '—';
					$score = isset($legislator['score']) ? sprintf('%s%%%s', esc_html($legislator['score']), isset($legislator['grade']) && $legislator['grade'] ? ' (' . esc_html($legislator['grade']) . ')' : '') : '—';
					$updated = !empty($legislator['date_updated']) ? mysql2date('M j, Y', $legislator['date_updated']) : '—';
					?>
					<tr>
						<td>
							<div class="d-flex flex-wrap gap-2">
								<a href="<?php echo esc_url(fi_admin_edit_legislator_url((int) $legislator['id'], ['return_url' => $return_url])); ?>" class="btn btn-sm btn-primary">Edit</a>
								<a href="<?php echo esc_url(fi_admin_legislator_sessions_url($legislator['id'])); ?>" class="btn btn-sm btn-secondary">Sessions</a>
								<a href="<?php echo esc_url(fi_get_legislator_url($legislator['id'])); ?>" class="btn btn-sm btn-secondary" target="_blank">View</a>
							</div>
						</td>
						<td><strong><?php echo esc_html($legislator['display_name'] ?? ''); ?></strong></td>
						<td><small class="text-muted">ID: <?php echo esc_html($legislator['id'] ?? ''); ?></small></td>
						<td><span class="badge <?= esc_attr($party_class); ?>"><?php echo esc_html($party_label); ?></span></td>
						<td><?php echo esc_html($chamber_label); ?></td>
						<?php if ($is_us_gov): ?>
							<td><?php echo esc_html($state_code !== '' ? $state_code : '—'); ?></td>
						<?php endif; ?>
						<td><?php echo $district_name; ?></td>
						<td><?php echo $score; ?></td>
						<td><?php echo esc_html($updated); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>
</div>
<?php endif; ?>