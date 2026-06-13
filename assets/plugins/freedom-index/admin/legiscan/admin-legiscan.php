<?php
if (!defined('ABSPATH')) exit;

/**
 * Legiscan Import - Main View
 * Replicates V2 workflow with granular control
 */

// Subtask definitions
$fi_subtasks = [
	'people' => [
		'task' => 'legiscan',
		'url' => 'people',
		'label' => 'People',
	],
/*
	'bills' => [
		'task' => 'legiscan',
		'url' => 'bills',
		'label' => 'Bills',
	],
*/
	'votes' => [
		'task' => 'legiscan',
		'url' => 'votes',
		'label' => 'Votes',
	],
	'refresh' => [
		'task' => 'legiscan',
		'url' => 'refresh',
		'label' => 'Delete Saved Legiscan Data and Refresh',
		'requires_data' => true, // Only show when data directory exists
	],
];

// Get request parameters
$data_fetch = sanitize_text_field($_REQUEST['fetch'] ?? '');
$subtask = sanitize_text_field($_REQUEST['subtask'] ?? '');
$import_person_id = ($_REQUEST['people_id'] ?? '') === 'ALL' ? 'ALL' : (int) ($_REQUEST['people_id'] ?? 0);
$roll_call_id = (int) sanitize_text_field($_GET['roll_call_id'] ?? null);
$bill_number = sanitize_text_field($_GET['bill_number'] ?? '');
$compiler_auth_key = md5(strtotime(date('Y-m-d') . ' 00:00:01'));

// Variables are set in render_legiscan_import() function scope
// $gov, $governments, $datasets, $existing_sessions are available
$scope = fi_scope_get_current();
$gov = strtoupper((string) ($scope['gov'] ?? 'US'));
$governments = fi_govs();

// Ensure gov directory exists in cache (for checking unpacked datasets)
$govdir = FI_DIR_LEGISCAN . $gov . '/';
if (!is_dir($govdir)) {
	wp_mkdir_p($govdir);
}

// NOTE: Legiscan actions that redirect (fetch/sync/refresh/add vote/add person) must run via admin-post.php
// to avoid "headers already sent" issues in wp-admin. This view is read-only UI.

// Initialize legiscan instance early (needed for vote import and dataset loading)
$legiscan = fi_legiscan_create_legislator();

// Get dataset list early (needed for vote/person import and display)
$legiscan_state = ($gov === 'US') ? 'US' : $gov;
$datasets = fi_legiscan_get_datasets($legiscan_state);

/*TEST*/ //echo '<pre>'; print_r($datasets); echo '</pre>';

// Get existing sessions for this gov
$existing_sessions = [];
$sessions = fi_sessions_get_by_gov($gov);
foreach ($sessions as $session) {
	if (!empty($session->legiscan_id)) {
		$existing_sessions[$session->legiscan_id] = $session->id;
	}
}

// State list is now hardcoded in fi_legiscan_abbreviations()
// No need to fetch from API anymore

fi_admin_legiscan_notice_render();
settings_errors('fi_legiscan');
?>
<?php fi_scope_render_selector(); ?>
<div class="wrap">
	<h1><?php echo esc_html($governments[$gov] ?? $gov); ?> Legislative Datasets</h1>
	<p><a href="https://legiscan.com/gaits/documentation/legiscan" target="_blank">Legiscan API Documentation</a></p>
	
<?php if (is_array($datasets)): ?>
	<div class="table-responsive">
		<table class="table table-striped table-hover align-middle">
			<thead>
				<?php ob_start(); ?>
				<tr>
					<th scope="col" style="width:10%; white-space:nowrap;">Action</th>
					<th scope="col" style="width:5%;">ID</th>
					<th scope="col" style="width:15%;">Title</th>
					<th scope="col" style="width:25%;">Name</th>
					<th scope="col" style="width:20%;">Directory</th>
					<th scope="col" style="width:5%;">Start</th>
					<th scope="col" style="width:5%;">End</th>
					<th scope="col" style="width:15%;">Session Tag</th>
					<th scope="col" style="width:10%;">Updated</th>
				</tr>
				<?php $header = ob_get_clean(); echo $header; ?>
			</thead>
			<tbody id="the-list">
				<?php foreach ($datasets as $data):
					$dataset_hash = $data['dataset_hash'] ?? '';
					$is_expanded = ($data_fetch === $dataset_hash);

					$session_exists = isset($existing_sessions[$data['session_id']]);
					if ($session_exists){
						$fi_session_id = $existing_sessions[$data['session_id']];
					}else{
						$fi_session_id = null;
					}
					// Construct full data directory path
					$data_dir_name = $data['directory'];
					$data_dir = FI_DIR_LEGISCAN . $gov . '/' . $data_dir_name . '/';
					$data_dir_exists = is_dir($data_dir);
//echo '<div class="alert alert-warning">DATA DIR: ' . $data_dir . '</div>';
					?>
				<tr<?php echo $is_expanded ? ' class="table-info"' : ''; ?>>
					<td>
					<?php if ($session_exists && $data_dir_exists): ?>
						<!-- State 1: Session exists AND data directory exists: Button opens subtask panel -->
						<a href="<?php echo esc_url(add_query_arg([
							'page' => 'fi-legiscan-import',
							'gov'  => $gov,
							'fetch' => $dataset_hash,
						], admin_url('admin.php'))); ?>" class="btn btn-sm btn-success">View Data</a>
					<?php elseif ($session_exists): ?>
						<!-- State 2: Session exists but NO data directory -->
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="d-inline">
							<?php wp_nonce_field('fi_legiscan_fetch_dataset'); ?>
							<input type="hidden" name="action" value="fi_legiscan_fetch_dataset">
							<input type="hidden" name="gov" value="<?php echo esc_attr($gov); ?>">
							<input type="hidden" name="session_id" value="<?php echo esc_attr((int) ($data['session_id'] ?? 0)); ?>">
							<input type="hidden" name="state_id" value="<?php echo esc_attr((int) ($data['state_id'] ?? 0)); ?>">
							<input type="hidden" name="access_key" value="<?php echo esc_attr((string) ($data['access_key'] ?? '')); ?>">
							<input type="hidden" name="year_start" value="<?php echo esc_attr((int) ($data['year_start'] ?? 0)); ?>">
							<input type="hidden" name="year_end" value="<?php echo esc_attr((int) ($data['year_end'] ?? 0)); ?>">
							<input type="hidden" name="session_name" value="<?php echo esc_attr((string) ($data['session_name'] ?? '')); ?>">
							<input type="hidden" name="session_title" value="<?php echo esc_attr((string) ($data['session_title'] ?? '')); ?>">
							<button type="submit" class="btn btn-sm btn-warning">Fetch Legiscan Data</button>
						</form>
					<?php else: ?>
						<!-- State 3: Session does NOT exist (regardless of data directory) -->
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="d-inline">
							<?php wp_nonce_field('fi_legiscan_sync_session'); ?>
							<input type="hidden" name="action" value="fi_legiscan_sync_session">
							<input type="hidden" name="gov" value="<?php echo esc_attr($gov); ?>">
							<input type="hidden" name="dataset_hash" value="<?php echo esc_attr($dataset_hash); ?>">
							<button type="submit" class="btn btn-sm btn-dark">Add Session</button>
						</form>
					<?php endif; ?>
					</td>
					<td><?php echo esc_html($data['session_id'] ?? ''); ?></td>
					<td><strong><?php echo esc_html($data['session_title'] ?? ''); ?></strong></td>
					<td><strong><?php echo esc_html($data['session_name'] ?? ''); ?></strong></td>
					<td class="<?php echo ($data_dir_exists ? 'text-success fw-bold' : 'text-danger'); ?>"><?php echo esc_html($data_dir_name); ?><!-- <?= $data_dir;?> --></td>
					<td><?php echo esc_html($data['year_start'] ?? ''); ?></td>
					<td><?php echo esc_html($data['year_end'] ?? ''); ?></td>
					<td><?php echo esc_html($data['session_tag'] ?? ''); ?></td>
					<td><?php echo esc_html($data['dataset_date'] ?? ''); ?></td>
				</tr>
				<?php if ($is_expanded && $session_exists): ?>
				<tr>
					<td colspan="9" class="p-3">
					<?php include FI_DIR . 'admin/legiscan/admin-legiscan-subtasks.php'; ?>
					</td>
				</tr>
				<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<?php echo $header; ?>
			</tfoot>
		</table>
	</div>
	<?php else: ?>
	<div class="notice notice-error">
		<p>OOPS! The data set list cannot be loaded. Please check:</p>
		<ul>
			<li>Legiscan API key is configured in Settings</li>
			<li>API key has access to <?php echo esc_html($governments[$gov] ?? $gov); ?> data</li>
			<li>Network connection is available</li>
		</ul>
	</div>
	<?php endif; ?>
</div>