<?php
if (!defined('ABSPATH')) {
	exit;
}

$scope = fi_scope_get_current();

// Delete is handled on admin_init in sessions.php so redirect fires before any output.

fi_admin_sessions_maybe_handle_save($scope);

$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';

if (in_array($action, ['add', 'edit'], true)) {
	fi_admin_sessions_render_form($scope, $action);
	return;
}

// Summary: staff rarely visits Sessions admin. When they do, clear the filter transient for this gov
// so the next front load rebuilds via the public path (top-level sessions only). Do not rebuild here—
// admin context can produce different session lists than the front and would overwrite correct cache.
$show_filters_updated_notice = false;
if (!empty($scope['gov']) && function_exists('fi_filter_clear_cache')) {
	fi_filter_clear_cache((string) $scope['gov']);
	$show_filters_updated_notice = true;
}

$sessions = fi_sessions_get_by_gov($scope['gov'] ?? 'US', [
	'orderby' => 'date_start',
	'order' => 'DESC',
]);

// Build hierarchy and stats
$session_stats = [];
$parent_sessions = [];
$child_sessions = [];

foreach ($sessions as $session) {
	$session_id = (int) $session->id;
	$session_stats[$session_id] = fi_admin_sessions_get_stats($session_id);

	if (empty($session->parent_id)) {
		$parent_sessions[] = $session;
	} else {
		$parent_id = (int) $session->parent_id;
		if (!isset($child_sessions[$parent_id])) {
			$child_sessions[$parent_id] = [];
		}
		$child_sessions[$parent_id][] = $session;
	}
}

$parent_sessions = $parent_sessions ?? [];
$child_sessions = $child_sessions ?? [];
$session_stats = $session_stats ?? [];
$scope = $scope ?? [];
$gov = $scope['gov'] ?? 'US';

// Flatten sessions for table display (parent sessions first, then children)
$all_sessions = [];
foreach ($parent_sessions as $parent) {
	$all_sessions[] = [
		'session' => $parent,
		'is_parent' => true,
		'level' => 0
	];
	$parent_id = (int) $parent->id;
	$children = $child_sessions[$parent_id] ?? [];
	foreach ($children as $child) {
		$all_sessions[] = [
			'session' => $child,
			'is_parent' => false,
			'level' => 1
		];
	}
}
?>
<?php fi_scope_render_selector(); ?>
<div class="wrap fi-sessions-admin">
	<h1 class="wp-heading-inline">Sessions</h1>
	<a href="<?php echo esc_url(fi_admin_url('fi-sessions', ['action' => 'add'])); ?>" class="btn btn-sm btn-outline-primary">
		Add Session
	</a>
	<hr class="wp-header-end">
	<?php if (empty($scope['gov'])): ?>
		<div class="notice notice-warning is-dismissible">
			<p>Select a government using the scope selector to manage sessions.</p>
		</div>
	<?php endif; ?>

	<?php if (!empty($_GET['updated'])): ?>
		<div class="notice notice-success is-dismissible">
			<p>Session saved successfully.</p>
		</div>
	<?php endif; ?>

	<?php if (!empty($_GET['deleted'])): ?>
		<?php if ($_GET['deleted'] === '1'): ?>
			<div class="notice notice-success is-dismissible">
				<p>Session "<?php echo esc_html(urldecode($_GET['session_name'] ?? '')); ?>" deleted successfully.</p>
			</div>
		<?php else: ?>
			<div class="notice notice-error is-dismissible">
				<p>Failed to delete session "<?php echo esc_html(urldecode($_GET['session_name'] ?? '')); ?>".</p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ($show_filters_updated_notice): ?>
		<div class="notice notice-success is-dismissible">
			<p>Legislator Filters Cleared and will rebuild on next front load.</p>
		</div>
	<?php endif; ?>

	<?php if (empty($all_sessions) && !empty($scope['gov'])): ?>
		<div class="notice notice-info">
			<p>No sessions found for this government. <a href="<?php echo esc_url(fi_admin_url('fi-sessions', ['action' => 'add'])); ?>">Create the first session</a>.</p>
		</div>
	<?php endif; ?>

	<?php if (!empty($all_sessions)): ?>
	<div class="table-responsive">
		<table class="wp-list-table widefat fixed striped table table-hover align-middle">
			<thead>
				<tr>
					<th width="240">Actions</th>
					<th>Name</th>
					<th>ID</th>
					<th>Type</th>
					<th>Status</th>
					<th>Dates</th>
					<th>Legislators</th>
					<th>Votes</th>
					<th>Reports</th>
					<th>Legiscan</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($all_sessions as $item): ?>
					<?php
					$session = $item['session'];
					$session_id = (int) $session->id;
					$stats = $session_stats[$session_id] ?? ['legislators' => 0, 'votes' => 0, 'reports' => 0];
					$is_current = !empty($session->is_current);
					$is_parent = $item['is_parent'];
					$level = $item['level'];
					
					// Format dates
					$date_range = '—';
					if ($session->date_start && $session->date_end) {
						$date_range = date('M Y', strtotime($session->date_start)) . ' - ' . date('M Y', strtotime($session->date_end));
					} elseif ($session->date_start) {
						$date_range = date('M Y', strtotime($session->date_start)) . ' - Present';
					}
					
					// Session type
					$type_label = $is_parent ? 'Parent' : 'Child';
					
					// Status badge
					$status = $session->status ?? 'draft';
					$status_badge_class = $status === 'publish' ? 'bg-success' : 'bg-warning text-dark';
					$status_label = ucfirst($status);
					?>
					<tr>
						<td>
							<div class="d-flex flex-wrap gap-2">
								<a href="<?php echo esc_url(fi_admin_edit_session_url($session_id)); ?>" class="btn btn-sm btn-primary">Edit</a>
								<?php if ($is_parent): ?>
									<a href="<?php echo esc_url(fi_admin_url('fi-sessions', ['action' => 'add', 'parent_id' => $session_id])); ?>" class="btn btn-sm btn-secondary">Add Child</a>
								<?php endif; ?>
							</div>
						</td>
						<td>
							<?php if ($is_current): ?>
								<span class="badge bg-primary me-1">Current</span>
							<?php endif; ?>
							<?php if ($level > 0): ?>
								<span class="text-muted me-1">└─</span>
							<?php endif; ?>
							<strong><?php echo esc_html($session->name); ?></strong>
						</td>
						<td><code><?php echo esc_html($session->id); ?></code></td>
						<td><span class="badge bg-secondary"><?php echo esc_html($type_label); ?></span></td>
						<td><span class="badge <?php echo esc_attr($status_badge_class); ?>"><?php echo esc_html($status_label); ?></span></td>
						<td><?php echo esc_html($date_range); ?></td>
						<td><?php echo esc_html($stats['legislators']); ?></td>
						<td><?php echo esc_html($stats['votes']); ?></td>
						<td><?php echo esc_html($stats['reports']); ?></td>
						<td><?php echo esc_html($session->legiscan_id ?? '-'); ?></td>
					</tr>
					<?php if ($is_parent && $stats['legislators'] === 0): ?>
						<?php
						// Check if any child sessions have legislators
						$parent_id_check = (int) $session->id;
						$children_check = $child_sessions[$parent_id_check] ?? [];
						$child_legislator_count = 0;
						foreach ($children_check as $child_check) {
							$child_stats = $session_stats[(int) $child_check->id] ?? ['legislators' => 0];
							$child_legislator_count += (int) $child_stats['legislators'];
						}
						?>
						<?php if ($child_legislator_count > 0): ?>
						<tr>
							<td colspan="10" class="bg-danger bg-opacity-25 fw-bold">
								The child session legislators MUST be assigned to the parent session. Open the parent session to fix.
							</td>
						</tr>
						<?php endif; ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

<?php /* if(get_current_user_id() == 1): ?>
<div class="card shadow-sm mb-4 col-lg-8 me-auto" style="margin-top: 3rem !important">
	<div class="card-header border-0 shadow-sm bg-danger text-white">
		<h3>Repair: Legislators from Child Sessions error excluded 'gov' parameter</h3>
	</div>
	<div class="card-body">
<?php
//Get parent sessions
global $wpdb;
$parent_sessions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fi_sessions WHERE gov='{$gov}' and parent_id IS NULL");
foreach($parent_sessions as $session){
	echo '<div>' . $session->id . ' - ' . $session->name . '</div>';
	$update = "UPDATE {$wpdb->prefix}fi_legislator_sessions SET gov='{$gov}' WHERE session_id={$session->id}";
	echo '<div>' . $update . '</div>';
	//echo '<div>' . $wpdb->query($update) . '</div>';
}
?>
	</div>
</div>
<?php endif; */ ?>





	<?php
	/* DIAGNOSTIC AID: Which legislators from this gov have top level sessions with no score.
	fi_legslators > fi_legislator_sessions:score is null > fi_sessions
	*/
	global $wpdb;
	// To group by legislator and avoid duplicates, select only fields that are functionally dependent on l.id,
	// or aggregate other fields, e.g. use GROUP_CONCAT or subqueries for related data.
	// Here, let's show just the legislator info and optionally the *first* unscored session/chamber for demonstration.

	$sql = "
		SELECT
			l.id,
			l.display_name,
			MIN(s.name) as session_name,      -- Pick one session name per legislator (could use GROUP_CONCAT if desired)
			MIN(ls.chamber) as session_chamber  -- Pick one session chamber per legislator
		FROM {$wpdb->prefix}fi_legislator_sessions ls
		INNER JOIN {$wpdb->prefix}fi_sessions s ON ls.session_id = s.id
			AND s.gov = %s
			AND s.parent_id IS NULL
		INNER JOIN {$wpdb->prefix}fi_legislators l ON ls.legislator_id = l.id
		WHERE ls.score IS NULL
		GROUP BY l.id, l.display_name
		ORDER BY l.display_name
	";
	$legislators = $wpdb->get_results($wpdb->prepare($sql, $scope['gov']));
//print_r($legislators);
	if (!empty($legislators)):
	?>
	<div class="card shadow-sm mb-4 col-lg-8 me-auto" style="margin-top: 3rem !important">
		<div class="card-header border-0 shadow-sm bg-warning">
			<h2 class="text-dark">Legislator Session Score Check</h2>
		</div>
		<div class="card-body">
			<p class="lead text-danger fw-bold">This is a diagnostic tool to help identify legislators with sessions that are not properly assigned to their chamber.</p>
			<p>It checks for legislator sessions where the <b>score failed to calculate</b>. This is likely due to a an incorrect chamber assigned to that legislator for that session.</p>
			<p><b>Click the legislator ID</b> to determine if the legislator session chambers need to be corrected. If yes, edit the session to <b>assign the correct chamber and district</b>.</p>
			<div class="table-responsive">
				<table class="wp-list-table widefat fixed striped table table-hover align-middle">
					<thead>
						<tr>
							<th>ID</th>
							<th>Name</th>
							<th>Session Chamber</th>
							<th>Session Name</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($legislators as $legislator): ?>
							<tr>
								<td><a href="<?php echo fi_admin_url('fi-legislators', ['action' => 'edit', 'legislator_id' => $legislator->id]); ?>" target="_blank" rel="noopener" class="fw-bold"><?php echo esc_html($legislator->id); ?></a></td>
								<td><?php echo esc_html($legislator->display_name); ?></td>
								<td><?php echo esc_html($legislator->session_chamber); ?></td>
								<td><?php echo esc_html($legislator->session_name); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<?php endif; ?>

</div>